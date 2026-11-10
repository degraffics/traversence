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
"""
import argparse, difflib, hashlib, os, re, shutil, sys, tempfile, zipfile

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import audit  # noqa: E402

BACK = {v: k for k, v in audit.MAP.items()}

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

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('zip')
    ap.add_argument('--mirror', required=True)
    ap.add_argument('--docs', default=os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..'))
    ap.add_argument('--drops', default='', help='names this zip may remove, comma-separated (only with Jason\'s OK)')
    a = ap.parse_args()
    docs = os.path.abspath(a.docs)
    work = tempfile.mkdtemp(prefix='zipcheck-')
    try:
        over = os.path.join(work, 'mirror')
        for top in audit.MAP:
            src = os.path.join(a.mirror, top)
            if os.path.isdir(src):
                shutil.copytree(src, os.path.join(over, top), ignore=shutil.ignore_patterns('.git'))
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
                                dropped.append((n, nm))
                        d = [l for l in difflib.ndiff(wt.splitlines(), nt.splitlines()) if l[:2] in ('- ', '+ ')]
                        churn.append((n, sum(1 for l in d if l.startswith('- ')), sum(1 for l in d if l.startswith('+ '))))
                dst = os.path.join(over, BACK[top], rest)
                os.makedirs(os.path.dirname(dst), exist_ok=True)
                open(dst, 'wb').write(data)
                rows.append((state, n))
        _, before, _ = audit.run(a.mirror, docs)
        _, after, _ = audit.run(over, docs)
        old = {key(f) for f in before}
        new = [f for f in after if key(f) not in old]
        fixed = len(old - {key(f) for f in after})
        sha = os.popen(f'git -C "{a.mirror}" log -1 --format="%h %s" 2>/dev/null').read().strip()
        print(f'Zip check: {os.path.basename(a.zip)}, against the live mirror {sha}')
        for state, n in sorted(rows):
            print(f'  {state:28} {n}')
        blocking = [f for f in new if audit.RULES[f['rule']][1]]
        if churn:
            print('\nAgainst the live copy (lines replaced or removed / lines added):')
            for n, rm, ad in sorted(churn):
                print(f'  {n}: -{rm} +{ad}')
        if dropped:
            print(f'\nBLOCK earlier work this zip would remove ({len(dropped)}): keep it, or name it with --drops once Jason agrees')
            for n, nm in dropped:
                print(f'  {n}: {nm}')
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
        sys.exit(1 if blocking or dropped else 0)
    finally:
        shutil.rmtree(work, ignore_errors=True)

if __name__ == '__main__':
    main()
