#!/usr/bin/env python3
"""The zip check (decision 0145, Phase 1): run before a zip is sent.

    python3 tools/page-audit/zipcheck.py <build.zip> --mirror <traverse-3way-sync checkout>

Lays the zip's files (website/, app/, trvgo/) over a copy of the live mirror and runs every code check on the result.
Reports only what the zip makes worse: new failures that weren't on the live site already. Also lists each file as
changed, unchanged (identical to live: leave it out of the zip) or new, and what it was built against. Exits 1 if a
blocking rule (RULES[...][1]) gets a new failure, so a broken zip can't go out.

Earlier work kept (Jason, 2026-11-10: "make it where previous work is never omitted and new creations try to replace over
it"): every function, constant and FAQ question on the live site must still be in the file the zip replaces it with. A
change extends or replaces in place; dropping one blocks the zip unless it's named with --drops (only with Jason's OK,
said in the delivery note). Each changed file also shows how many live lines it changes or removes.

Current first (Jason, 2026-11-11: "be sure you are checking main and faq before rendering zip files so we dont keep
overwriting other sessions"): it fetches the mirror and blocks when the checkout is behind its main (a newer sync is
there: rebuild on it), and blocks when another session's zip that isn't live yet carries one of this zip's files (most
often faq.md): wait for its upload and sync, then rebuild on the live copy. --allow-pending names such zips once checked.

Check-out (decisions/0161 §4, §8): it blocks a zip that changes a function or entry another session holds a change claim on
(`checkout.py`, `releases/checkouts.json`), and one that changes a function's arguments so that a call on the live site
(with this zip laid over it) no longer fits; --callers-checked names such functions once each call is checked. A change
no claim of this session covers is a warning until checkout.UNCLAIMED_BLOCKS_FROM, then it blocks. Adding never blocks.

Migrations (decisions/0162): one that changes rows (UPDATE, DELETE, TRUNCATE, a drop) blocks unless release.json gives a
"data_reason" (a sentence); a drop (R08) passes only when release.json names it under "removes" ("table t", "column t.c",
"index t.i"): the site then checks it's a recorded leftover of a rolled-back release, and copies the table first.
"""
import argparse, difflib, hashlib, json, os, re, shutil, sys, tempfile, zipfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import audit  # noqa: E402
import checkout  # noqa: E402

BACK = {v: k for k, v in audit.MAP.items()}


def _sql_clean(sql):
    """The statements without comments (outside quotes), as DbUpdates::statements() reads them."""
    out, cur, q, i, n = [], '', '', 0, len(sql)
    while i < n:
        c = sql[i]
        if q:
            cur += c
            if c == '\\' and q != '`':
                cur += sql[i + 1:i + 2]; i += 2; continue
            if c == q:
                if sql[i + 1:i + 2] == q:
                    cur += q; i += 2; continue
                q = ''
            i += 1; continue
        if sql.startswith('--', i) and sql[i + 2:i + 3] in (' ', '\t', '\n', ''):
            e = sql.find('\n', i); i = n if e < 0 else e; cur += '\n'; continue
        if c == '#':
            e = sql.find('\n', i); i = n if e < 0 else e; cur += '\n'; continue
        if sql.startswith('/*', i):
            e = sql.find('*/', i + 2); i = n if e < 0 else e + 2; continue
        if c in '\'"`':
            q = c
        if c == ';':
            if cur.strip(): out.append(cur.strip())
            cur = ''; i += 1; continue
        cur += c; i += 1
    if cur.strip(): out.append(cur.strip())
    return re.sub(r'\bON\s+(?:DUPLICATE\s+KEY\s+)?(?:UPDATE|DELETE)\b', ' ', ';\n'.join(out) + ';', flags=re.I)


def changes_data(sql):
    """As DbCopies::changesData(): UPDATE … SET, DELETE … FROM, TRUNCATE, or a drop of a table or column."""
    s = _sql_clean(sql)
    return bool(re.search(r"\bUPDATE\s+(?:LOW_PRIORITY\s+|IGNORE\s+)*`?\w+`?(?:\s*\.\s*`?\w+`?)?[^;]{0,4000}?\bSET\b|\bDELETE\b[^;']*?\bFROM\b|\bTRUNCATE\b"
                          r"|\bDROP\s+(?:TABLE|COLUMN)\b|\bALTER\s+TABLE\s+[^;]*?\bDROP\s+(?!INDEX|KEY|PRIMARY|FOREIGN)", s, re.I))


def drops(sql):
    """As SchemaUpkeep::drops(): ["table t", "column t.c", "index t.i"]."""
    s = _sql_clean(sql); q = r'`?(\w+)`?'; out = []
    out += ['table ' + m.lower() for m in re.findall(r'\bDROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?(?:`?\w+`?\.)?' + q, s, re.I)]
    for t, body in re.findall(r"\bALTER\s+TABLE\s+(?:`?\w+`?\.|`',\s*@\w+,\s*'`\.)?" + q + r"([^;']*)", s, re.I):
        for kind, nm in re.findall(r'\bDROP\s+(?:(COLUMN|INDEX|KEY)\s+)?' + q, body, re.I):
            if nm.upper() in ('PRIMARY', 'FOREIGN', 'CONSTRAINT', 'CHECK'):
                continue
            out.append(('index ' if kind.upper() in ('INDEX', 'KEY') else 'column ') + t.lower() + '.' + nm.lower())
    out += ['index %s.%s' % (t.lower(), i.lower()) for i, t in re.findall(r'\bDROP\s+INDEX\s+' + q + r'\s+ON\s+(?:`?\w+`?\.)?' + q, s, re.I)]
    return sorted(set(out))


def migration_review(zpath):
    """[(file, problem)] for the zip's migrations against its release.json, and the files whose drops it names."""
    probs, named_ok = [], set()
    with zipfile.ZipFile(zpath) as z:
        try:
            plan = json.loads(z.read('release.json').decode('utf-8'))
        except Exception:
            plan = {}
        reason = str(plan.get('data_reason', '') or '').strip()
        removes = {str(x).strip().lower() for x in (plan.get('removes') or [])}
        for n in z.namelist():
            if not (n.startswith('app/migrations/') and n.endswith('.sql')):
                continue
            sql = z.read(n).decode('utf-8', 'replace')
            if changes_data(sql) and len(reason) < 10:
                probs.append((n, 'changes rows (UPDATE, DELETE or a drop): release.json needs a "data_reason", a sentence saying why'))
            d = drops(sql)
            if d:
                unnamed = [x for x in d if x not in removes]
                if unnamed:
                    probs.append((n, 'drops ' + ', '.join(unnamed) + ': name each under "removes" in release.json (only a recorded leftover of a rolled-back release, with Jason\'s OK)'))
                else:
                    named_ok.add(n)
    return probs, named_ok

def key(f):
    return (f['rule'], f['file'], f['msg'])

NAMED = {'.php': [r'function\s+&?([A-Za-z_]\w*)\s*\(', r'\bconst\s+([A-Z_][A-Z0-9_]*)\s*='],
         '.js': [r'function\s+([A-Za-z_$][\w$]*)\s*\(', r'^\s*(?:var|let|const)\s+([A-Z_][A-Z0-9_]*)\s*='],
         '.md': [r'^###\s+(.+?)\s*$']}

def named(path, text):
    """The things a file defines that later work must keep: functions and constants, or an FAQ's questions."""
    ext = os.path.splitext(path)[1].lower()
    out = set()
    for rx in NAMED.get(ext, []):
        out |= set(re.findall(rx, text, re.M))
    return out

def current(mirror):
    """'' when the mirror checkout has its main's newest sync, else what it's missing."""
    if os.system(f'git -C "{mirror}" fetch -q origin main >/dev/null 2>&1') != 0:
        return ''   # offline: the build note's "built on" still says which sync it used
    here = os.popen(f'git -C "{mirror}" rev-parse HEAD 2>/dev/null').read().strip()
    there = os.popen(f'git -C "{mirror}" rev-parse origin/main 2>/dev/null').read().strip()
    if not here or not there or here == there:
        return ''
    if os.system(f'git -C "{mirror}" merge-base --is-ancestor origin/main HEAD') == 0:
        return ''
    return 'its main has ' + os.popen(f'git -C "{mirror}" log -1 --format="%s" origin/main').read().strip()

def others_pending(docs, zpath, allowed):
    """Other zips in the delivery log, not live yet, that carry a file this zip carries."""
    import json, glob
    with zipfile.ZipFile(zpath) as z:
        mine = {n for n in z.namelist() if not n.endswith('/')}
    me = os.path.splitext(os.path.basename(zpath))[0]
    out = []
    for f in sorted(glob.glob(os.path.join(docs, 'deliveries', '*.json'))):
        try:
            d = json.load(open(f))
        except Exception:
            continue
        name = os.path.splitext(os.path.basename(f))[0]
        if name == me or name in allowed or d.get('status') != 'pending':
            continue
        hit = sorted(p for p, v in (d.get('files') or {}).items() if p in mine and not v.get('live'))
        if hit:
            out.append((name, hit))
    return out

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('zip')
    ap.add_argument('--mirror', required=True)
    ap.add_argument('--docs', default=os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..'))
    ap.add_argument('--drops', default='', help='names this zip may remove, comma-separated (only with Jason\'s OK)')
    ap.add_argument('--allow-pending', default='', help='other pending zips (by name) already built on, comma-separated')
    ap.add_argument('--session', default='', help='the sending session (default: this one); its own claims never block it')
    ap.add_argument('--callers-checked', default='', help='functions whose changed arguments were checked against every live caller')
    a = ap.parse_args()
    docs = os.path.abspath(a.docs)
    work = tempfile.mkdtemp(prefix='zipcheck-')
    try:
        over = os.path.join(work, 'mirror')
        for top in audit.MAP:
            src = os.path.join(a.mirror, top)
            if os.path.isdir(src):
                shutil.copytree(src, os.path.join(over, top), ignore=shutil.ignore_patterns('.git'))
        stale = current(a.mirror)
        pending = others_pending(docs, a.zip, {d.strip() for d in a.allow_pending.split(',') if d.strip()})
        rows = []; dropped = []; churn = []
        allowed = {d.strip() for d in a.drops.split(',') if d.strip()}
        with zipfile.ZipFile(a.zip) as z:
            for n in z.namelist():
                if n.endswith('/') or '..' in n.split('/'):
                    continue
                top, _, rest = n.partition('/')
                if top not in BACK:
                    rows.append(('outside website/ app/ trvgo/', n)); continue
                data = z.read(n)
                live = os.path.join(a.mirror, BACK[top], rest)
                state = 'new'
                if os.path.exists(live):
                    was = open(live, 'rb').read()
                    state = 'unchanged (leave it out)' if was == data else 'changed'
                    if state == 'changed':
                        wt, nt = was.decode('utf-8', 'replace'), data.decode('utf-8', 'replace')
                        for nm in sorted(named(n, wt) - named(n, nt)):
                            if nm not in allowed:
                                dropped.append((n, nm))   # unless another file in this zip now has it (a move, kept: below)
                        d = [l for l in difflib.ndiff(wt.splitlines(), nt.splitlines()) if l[:2] in ('- ', '+ ')]
                        churn.append((n, sum(1 for l in d if l.startswith('- ')), sum(1 for l in d if l.startswith('+ '))))
                dst = os.path.join(over, BACK[top], rest)
                os.makedirs(os.path.dirname(dst), exist_ok=True)
                open(dst, 'wb').write(data)
                rows.append((state, n))
        # a name that leaves one file for another file in this same zip is moved, not removed (Jason, 2026-11-10: kept work)
        moved = []
        if dropped:
            with zipfile.ZipFile(a.zip) as z:
                have = {}
                for n in z.namelist():
                    if not n.endswith('/'):
                        for nm in named(n, z.read(n).decode('utf-8', 'replace')):
                            have.setdefault(nm, []).append(n)
            moved = [(n, nm, have[nm]) for n, nm in dropped if any(o != n for o in have.get(nm, []))]
            dropped = [(n, nm) for n, nm in dropped if not any(o != n for o in have.get(nm, []))]
        session = checkout.my_session(a.session)
        try:
            claimed, unclaimed = checkout.zip_review(a.zip, a.mirror, session, docs)
        except Exception as e:   # the claims can't be read: say so, never stop a zip for it
            claimed, unclaimed = [], ['check-out not read (%s)' % e]
        ok_callers = {x.strip() for x in a.callers_checked.split(',') if x.strip()}
        broken = [b for b in checkout.broken_callers(a.zip, a.mirror, over) if b[1] not in ok_callers]
        strict = checkout.iso(checkout.now())[:10] >= checkout.UNCLAIMED_BLOCKS_FROM
        unclaimed_block = strict and any('no change claim' in u for u in unclaimed)
        _, before, _ = audit.run(a.mirror, docs)
        _, after, _ = audit.run(over, docs)
        old = {key(f) for f in before}
        new = [f for f in after if key(f) not in old]
        fixed = len(old - {key(f) for f in after})
        sha = os.popen(f'git -C "{a.mirror}" log -1 --format="%h %s" 2>/dev/null').read().strip()
        print(f'Zip check: {os.path.basename(a.zip)}, against the live mirror {sha}')
        for state, n in sorted(rows):
            print(f'  {state:28} {n}')
        mig_probs, mig_named = migration_review(a.zip)
        new = [f for f in new if not (f['rule'] == 'R08' and f['file'] in mig_named and f['msg'].startswith('drops'))]   # a named removal (decisions/0162)
        blocking = [f for f in new if audit.RULES[f['rule']][1]]
        if churn:
            print('\nAgainst the live copy (lines replaced or removed / lines added):')
            for n, rm, ad in sorted(churn):
                print(f'  {n}: -{rm} +{ad}')
        if stale:
            print(f'\nBLOCK the mirror is behind: {stale}. Pull it and rebuild every file on the newest live copy.')
        if pending:
            print(f'\nBLOCK another session\'s zip, not live yet, has the same files: wait for its upload and the sync, then rebuild on the live copy')
            for z, fs in pending:
                print(f'  {z}: {", ".join(fs)}')
        if claimed:
            print('\nBLOCK checked out by another session (decisions/0161): build new beside old, or wait for its release')
            for c in claimed:
                print('  ' + c)
        if broken:
            print('\nBLOCK a live caller would break: these functions now take different arguments, and files still call them the old way')
            for n, fn, files in broken:
                print(f'  {n}: {fn}() called by {", ".join(files[:6])}' + (' …' if len(files) > 6 else ''))
            print('  Keep the old arguments working (a new optional parameter at the end, or a new function beside the old), '
                  'or move the callers in this zip; --callers-checked names one once every call is checked.')
        if unclaimed:
            print(('\nBLOCK' if unclaimed_block else '\nWarning') + ' check-out (decisions/0161):')
            for u in unclaimed:
                print('  ' + u)
        if moved:
            print(f'\nMoved, kept ({len(moved)}): each is now in another file of this zip')
            for n, nm, to in moved:
                print(f'  {n}: {nm} -> {", ".join(o for o in to if o != n)}')
        if dropped:
            print(f'\nBLOCK earlier work this zip would remove ({len(dropped)}): keep it, or name it with --drops once Jason agrees')
            for n, nm in dropped:
                print(f'  {n}: {nm}')
        if mig_probs:
            print('\nBLOCK migrations (decisions/0162):')
            for n, why in mig_probs:
                print(f'  {n}: {why}')
        if new:
            print(f'\n{len(new)} new failure(s) this zip would add:')
            for f in new:
                print(f'  {"BLOCK" if audit.RULES[f["rule"]][1] else "note "} {f["rule"]} {f["file"]}:{f["line"]}  {f["msg"]}')
        else:
            print('\nChecked clean: no new failures.')
        if fixed:
            print(f'It fixes {fixed} failure(s) that are on the live site now.')
        if any(s.startswith('unchanged') for s, _ in rows):
            print('Some files are identical to live: leave them out (a zip holds only files that differ).')
        sys.exit(1 if blocking or dropped or stale or pending or claimed or broken or unclaimed_block or mig_probs else 0)
    finally:
        shutil.rmtree(work, ignore_errors=True)

if __name__ == '__main__':
    main()
