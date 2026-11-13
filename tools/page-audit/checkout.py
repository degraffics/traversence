#!/usr/bin/env python3
"""Check-out for site files: the page manager (decisions/0161).

    python3 tools/page-audit/checkout.py claim <path>[::<fn>[,<fn>]]… --add|--change --why "<one line>" [--page <route>]
                                         [--mirror <checkout>] [--session <id>] [--release <zip>]
    python3 tools/page-audit/checkout.py release [<path>…] [--release <zip>] [--session <id>]   (no path: all of the session's)
    python3 tools/page-audit/checkout.py renew [--session <id>]
    python3 tools/page-audit/checkout.py list [--json]
    python3 tools/page-audit/checkout.py expire
    python3 tools/page-audit/checkout.py callers <path>::<fn> --mirror <checkout>
    python3 tools/page-audit/checkout.py release-live          (claims whose release the delivery log shows live)

No bottleneck (Jason, 2026-11-13):
- **add** (a new function, entry, section or file) is shared: any number of sessions add to one file at once.
- **change** (editing or removing what exists) is exclusive, on the functions or named entries changing
  (`app/lib/LocalWords.php::match`), never the whole file unless no name is given.
- Claim when you start writing a release's files, for that one release; reading is never claimed. A claim lapses after
  4 hours without a send or renew (reported, not silently dropped), and is released when its release is verified live.
- `SHARED` files (faq.md and other add-only files) are never claimed. The hotspot files take a change claim only on a
  named entry (`app/admin/manifests.php::'crawler'`).
- To change what a function does without waiting: build the new version beside the old (an add), move callers over, remove
  the old later (§3).

Claims live in `releases/checkouts.json` on `main`. Each command fetches main, applies itself to main's copy and pushes a
commit straight to main (git's push conflict makes it first come, first served; a lost push fetches and tries again).
It never touches your working tree or branch. `--local` (or CHECKOUT_LOCAL=1) works on the working copy instead, for tests.
Your session is read from CLAUDE_CODE_REMOTE_SESSION_ID unless `--session` names it.
"""
import argparse, datetime, glob, json, os, re, subprocess, sys, tempfile, time, zipfile

HERE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.normpath(os.path.join(HERE, '..', '..'))
REL = 'releases/checkouts.json'
MAP = {'website': 'sync-storage', 'app': 'sync-app', 'trvgo': 'sync-trvgo'}
HOURS = 4
SHARED = {'app/ui/faq.md'}   # add-only: never claimed (decisions/0161 §7)
HOTSPOTS = {'app/admin/manifests.php', 'app/admin/tools.php', 'app/lib/Pulse.php', 'website/admin/router.php',
            'trvgo/index.php', 'app/ui/section-rails.php', 'website/listing/portal.php', 'app/ui/portal.php'}
# The zip check warns on a change no claim covers for the first week, then blocks (decisions/0161 §8). Jason moves this
# date if the week shows trouble.
UNCLAIMED_BLOCKS_FROM = '2026-11-20'
LAPSED_KEPT = 50


# ---------------------------------------------------------------- time, session

def now():
    return datetime.datetime.now(datetime.timezone.utc)

def iso(t):
    return t.strftime('%Y-%m-%dT%H:%M:%SZ')

def parse(s):
    return datetime.datetime.strptime(s, '%Y-%m-%dT%H:%M:%SZ').replace(tzinfo=datetime.timezone.utc)

def my_session(given=None):
    s = given or os.environ.get('CLAUDE_CODE_REMOTE_SESSION_ID') or os.environ.get('CHECKOUT_SESSION') or ''
    return 'session_' + s[4:] if s.startswith('cse_') else s


# ---------------------------------------------------------------- the store

def empty():
    return {'about': 'Check-out for site files (decisions/0161). Written by tools/page-audit/checkout.py; don\'t edit by hand.',
            'claims': [], 'lapsed': []}

def norm(d):
    d = d or empty()
    d.setdefault('claims', []); d.setdefault('lapsed', [])
    return d

def git(*args, docs=DOCS, inp=None, env=None):
    r = subprocess.run(['git', '-C', docs] + list(args), input=inp, capture_output=True, text=True, env=env)
    return r.returncode, r.stdout.strip(), r.stderr.strip()

def local_mode(a=None):
    return bool(getattr(a, 'local', False)) or os.environ.get('CHECKOUT_LOCAL') == '1'

def read_local(docs=DOCS):
    p = os.path.join(docs, REL)
    try:
        return norm(json.load(open(p)))
    except Exception:
        return norm(None)

def write_local(d, docs=DOCS):
    p = os.path.join(docs, REL)
    os.makedirs(os.path.dirname(p), exist_ok=True)
    with open(p, 'w') as f:
        f.write(json.dumps(d, indent=1) + '\n')

def load(docs=DOCS, fetch=True, a=None):
    """The claims as main has them (fetched), else the working copy (offline, or a repo with no remote)."""
    if local_mode(a):
        return read_local(docs)
    if fetch:
        git('fetch', '-q', 'origin', 'main', docs=docs)
    rc, out, _ = git('show', 'origin/main:' + REL, docs=docs)
    if rc == 0:
        try:
            return norm(json.loads(out))
        except Exception:
            pass
    return read_local(docs)

def commit_to_main(apply, msg, docs=DOCS, tries=5):
    """Apply `apply(d) -> result` to main's checkouts.json and push the commit to main, retrying when someone else's
    push wins. Never touches the working tree. Returns apply's result from the run that landed."""
    for n in range(tries):
        rc, _, err = git('fetch', '-q', 'origin', 'main', docs=docs)
        if rc != 0:
            raise SystemExit('Couldn\'t fetch main: ' + err)
        rc, out, _ = git('show', 'origin/main:' + REL, docs=docs)
        d = norm(json.loads(out)) if rc == 0 and out else norm(None)
        res = apply(d)
        if res is None or (isinstance(res, dict) and res.get('refused')):
            return res
        body = json.dumps(d, indent=1) + '\n'
        _, blob, _ = git('hash-object', '-w', '--stdin', docs=docs, inp=body)
        idx = tempfile.mktemp(prefix='checkout-index-')
        env = dict(os.environ, GIT_INDEX_FILE=idx)
        try:
            git('read-tree', 'origin/main', docs=docs, env=env)
            git('update-index', '--add', '--cacheinfo', '100644,%s,%s' % (blob, REL), docs=docs, env=env)
            _, tree, _ = git('write-tree', docs=docs, env=env)
        finally:
            if os.path.exists(idx):
                os.remove(idx)
        _, base, _ = git('rev-parse', 'origin/main', docs=docs)
        _, ctree, _ = git('rev-parse', 'origin/main^{tree}', docs=docs)
        if tree == ctree:
            return res   # nothing changed
        _, c, err = git('commit-tree', tree, '-p', base, '-m', msg, docs=docs)
        if not c:
            raise SystemExit('Couldn\'t write the claim commit: ' + err)
        rc, _, err = git('push', '-q', 'origin', c + ':refs/heads/main', docs=docs)
        if rc == 0:
            git('fetch', '-q', 'origin', 'main', docs=docs)
            return res
        if 'rejected' not in err and 'non-fast-forward' not in err and 'fetch first' not in err:
            raise SystemExit('Couldn\'t push the claim to main: ' + err +
                             '\nNothing was claimed. If the git proxy refused the repository, see CLAUDE.md "Pushing from a cloud session".')
        time.sleep(1 + n * 2)
    raise SystemExit('Main kept moving: the claim didn\'t land after %d tries. Run it again.' % tries)

def save(apply, msg, a=None, docs=DOCS):
    if local_mode(a):
        d = read_local(docs)
        res = apply(d)
        if not (res is None or (isinstance(res, dict) and res.get('refused'))):
            write_local(d, docs)
        return res
    return commit_to_main(apply, msg, docs)


# ---------------------------------------------------------------- claims

def active(d, at=None):
    at = at or now()
    return [c for c in d['claims'] if parse(c['expires']) > at]

def lapse(d, at=None):
    """Move lapsed claims to d['lapsed'] (kept, so they're reported). Returns the ones moved."""
    at = at or now()
    gone = [c for c in d['claims'] if parse(c['expires']) <= at]
    if gone:
        d['claims'] = [c for c in d['claims'] if parse(c['expires']) > at]
        for c in gone:
            c['lapsed'] = iso(at)
        d['lapsed'] = (gone + d['lapsed'])[:LAPSED_KEPT]
    return gone

def split(spec):
    path, _, fns = spec.partition('::')
    path = path.strip().lstrip('/')
    return path, sorted({f.strip() for f in fns.split(',') if f.strip()}) if fns else []

def overlap(a_fns, b_fns):
    """Two change claims on one file clash when either is the whole file or they share a name."""
    if not a_fns or not b_fns:
        return sorted(set(a_fns) | set(b_fns)) or ['(the whole file)']
    return sorted(set(a_fns) & set(b_fns))

def clashes(d, session, path, fns, at=None):
    """Other sessions' change claims that this change claim on (path, fns) would collide with."""
    out = []
    for c in active(d, at):
        if c['session'] == session or c['path'] != path or c['kind'] != 'change':
            continue
        hit = overlap(fns, c['functions'])
        if hit:
            out.append((c, hit))
    return out

def caller_notes(d, session, path, at=None):
    """§4: this file calls a function another session is changing."""
    out = []
    for c in active(d, at):
        if c['session'] != session and c['kind'] == 'change':
            for fn, files in (c.get('callers') or {}).items():
                if path in files:
                    out.append('%s::%s is being changed by %s, for %s (since %s): build against the live version, then '
                               're-check your call once that release is live.' % (c['path'], fn, c['session'], c['why'], c['since']))
    return out

def describe(c):
    what = c['path'] + ('::' + ','.join(c['functions']) if c['functions'] else '')
    return '%s (%s) by %s, for "%s", since %s, until %s%s' % (
        what, c['kind'], c['session'], c['why'], c['since'], c['expires'], ', release ' + c['release'] if c.get('release') else '')

def take(d, session, items, why, release='', mirror=None, at=None):
    """Take every (path, fns, kind) together, or none. Returns {'refused': [...]} or {'taken': [...], 'skipped': [...], 'notes': [...]}."""
    at = at or now()
    lapse(d, at)
    refused, skipped, notes, taken = [], [], [], []
    for path, fns, kind in items:
        if path in SHARED:
            skipped.append(path + ': shared, add-only (decisions/0161 §7): never claimed. If another release carrying it is '
                           'pending, wait for it to go live and re-apply only your entries on the live copy.')
            continue
        if kind == 'change':
            if path in HOTSPOTS and not fns:
                refused.append(path + ' is a hotspot file: name the entries you change (%s::<entry>); adding to it needs no '
                               'change claim (--add).' % path)
                continue
            for c, hit in clashes(d, session, path, fns, at):
                refused.append('%s: %s is being changed by %s, for "%s", since %s (until %s). Build new beside old (an add), '
                               'work on something else, wait, or ask that session to fold your change in.' % (
                                   path, ', '.join(hit), c['session'], c['why'], c['since'], c['expires']))
        notes += caller_notes(d, session, path, at)
    if refused:
        return {'refused': refused}
    exp = iso(at + datetime.timedelta(hours=HOURS))
    for path, fns, kind in items:
        if path in SHARED:
            continue
        mine = [c for c in d['claims'] if c['session'] == session and c['path'] == path and c['kind'] == kind]
        callers = find_callers(mirror, path, fns) if (kind == 'change' and fns and mirror) else {}
        if mine:
            c = mine[0]
            whole = kind == 'change' and (not fns or not c['functions'])   # a whole-file change claim stays whole
            c['functions'] = [] if whole else sorted(set(c['functions']) | set(fns))
            c['expires'] = exp; c['why'] = why or c['why']
            if release:
                c['release'] = release
            c.setdefault('callers', {}).update(callers)
        else:
            c = {'path': path, 'functions': fns, 'kind': kind, 'session': session, 'why': why, 'since': iso(at),
                 'expires': exp, 'release': release, 'callers': callers}
            z = zones_for(path)
            if z:
                c['zones'] = z
                notes.append('%s carries editor zones (%s): the page builder\'s content there is in the database; check your '
                             'change against what\'s been built (decisions/0161 §9).' % (path, ', '.join(z)))
            d['claims'].append(c)
        taken.append(c)
    return {'taken': taken, 'skipped': skipped, 'notes': notes}

def release_where(d, session=None, paths=None, release=None):
    keep, gone = [], []
    for c in d['claims']:
        hit = ((session is None or c['session'] == session) and (not paths or c['path'] in paths)
               and (release is None or c.get('release') == release))
        (gone if hit else keep).append(c)
    d['claims'] = keep
    return gone


# ---------------------------------------------------------------- the live mirror: callers and signatures

def mirror_path(mirror, path):
    top, _, rest = path.partition('/')
    return os.path.join(mirror, MAP.get(top, top), rest)

def site_files(mirror):
    for top, there in MAP.items():
        base = os.path.join(mirror, there)
        for root, dirs, files in os.walk(base):
            dirs[:] = [x for x in dirs if x != '.git']
            for f in files:
                if f.endswith(('.php', '.js')):
                    p = os.path.join(root, f)
                    yield top + '/' + os.path.relpath(p, base).replace(os.sep, '/'), p

def bare(fn):
    """'LocalWords::match' or "'crawler'" -> the name to search for (None for a quoted entry)."""
    fn = fn.split('::')[-1].strip()
    return fn if re.fullmatch(r'[A-Za-z_$][\w$]*', fn) else None

def call_rx(name):
    if re.fullmatch(r'[A-Z_][A-Z0-9_]*', name):   # a constant
        return re.compile(r'(?<![\w$])' + re.escape(name) + r'(?![\w$])')
    return re.compile(r'(?<![\w$])' + re.escape(name) + r'\s*\(')

def find_callers(mirror, path, fns, files=None):
    """{fn: [files on the live site that call it]}, from the mirror, or from `files` ({path: text}) when given. A
    function's own definition isn't a call."""
    names = {fn: bare(fn) for fn in fns}
    names = {k: v for k, v in names.items() if v}
    if not names or (files is None and not mirror):
        return {}
    if files is None:
        def texts():
            for p, full in site_files(mirror):
                try:
                    yield p, open(full, encoding='utf-8', errors='replace').read()
                except Exception:
                    continue
        it = texts()
    else:
        it = files.items()
    out = {}
    for p, body in it:
        for fn, nm in names.items():
            for m in call_rx(nm).finditer(body):
                before = body[body.rfind('\n', 0, m.start()) + 1:m.start()]
                if re.search(r'\b(function\s+&?|const\s+)$', before):
                    continue
                out.setdefault(fn, []).append(p)
                break
    return {k: sorted(v) for k, v in out.items()}

DEF_RX = re.compile(r'function\s+&?([A-Za-z_$][\w$]*)\s*\(')

def match_paren(text, i):
    """Index just past the ')' matching the '(' at text[i]; strings skipped roughly."""
    depth, q = 0, None
    while i < len(text):
        ch = text[i]
        if q:
            if ch == '\\':
                i += 2; continue
            if ch == q:
                q = None
        elif ch in '\'"`':
            q = ch
        elif ch in '([{':
            depth += 1
        elif ch in ')]}':
            depth -= 1
            if depth == 0:
                return i + 1
        i += 1
    return len(text)

def split_args(inner):
    out, depth, q, cur = [], 0, None, ''
    i = 0
    while i < len(inner):
        ch = inner[i]
        if q:
            cur += ch
            if ch == '\\' and i + 1 < len(inner):
                cur += inner[i + 1]; i += 2; continue
            if ch == q:
                q = None
        elif ch in '\'"`':
            q = ch; cur += ch
        elif ch in '([{':
            depth += 1; cur += ch
        elif ch in ')]}':
            depth -= 1; cur += ch
        elif ch == ',' and depth == 0:
            out.append(cur.strip()); cur = ''
        else:
            cur += ch
        i += 1
    if cur.strip():
        out.append(cur.strip())
    return out

def signatures(text):
    """{name: (required, max)} for each function a file defines (max None when it takes a variadic ...)."""
    out = {}
    for m in DEF_RX.finditer(text):
        open_at = m.end() - 1
        args = split_args(text[open_at + 1:match_paren(text, open_at) - 1])
        req = sum(1 for x in args if '=' not in x and '...' not in x)
        mx = None if any('...' in x for x in args) else len(args)
        out[m.group(1)] = (req, mx)
    return out

def fits(sig, n):
    req, mx = sig
    return n >= req and (mx is None or n <= mx)

def call_counts(text, name):
    """The argument count of every call to `name` in text."""
    out = []
    for m in call_rx(name).finditer(text):
        line_start = text.rfind('\n', 0, m.start()) + 1
        if re.search(r'\bfunction\s+&?$', text[line_start:m.start()]):
            continue
        open_at = m.end() - 1
        inner = text[open_at + 1:match_paren(text, open_at) - 1]
        out.append(0 if not inner.strip() else len(split_args(inner)))
    return out

def blocks(text, ext):
    """[(name, start_line, end_line)] for each function (1-based, inclusive), by brace matching."""
    out = []
    if ext not in ('.php', '.js'):
        return out
    lines_at = [0]
    for i, ch in enumerate(text):
        if ch == '\n':
            lines_at.append(i + 1)
    import bisect
    for m in DEF_RX.finditer(text):
        open_at = m.end() - 1
        after = match_paren(text, open_at)
        brace = text.find('{', after)
        semi = text.find(';', after)
        if brace < 0 or (0 <= semi < brace):
            continue   # an abstract or interface declaration
        end = match_paren(text, brace)
        out.append((m.group(1), bisect.bisect_right(lines_at, m.start()), bisect.bisect_right(lines_at, end - 1)))
    return out

ENTRY_RX = re.compile(r'^\s*([\'"])([\w./:-]+)\1\s*=>')

def changed_names(path, old, new):
    """What a file's new version does to its live version: ('add', []) when every live line is kept in order, else
    ('change', [names of the functions or entries whose live lines changed or went])."""
    import difflib
    a, b = old.splitlines(), new.splitlines()
    sm = difflib.SequenceMatcher(None, a, b, autojunk=False)
    gone = []
    for tag, i1, i2, j1, j2 in sm.get_opcodes():
        if tag in ('replace', 'delete'):
            gone += [i for i in range(i1, i2) if a[i].strip()]
    if not gone:
        return 'add', []
    ext = os.path.splitext(path)[1].lower()
    bl = blocks(old, ext)
    names = set()
    for i in gone:
        ln = i + 1
        inside = [b_ for b_ in bl if b_[1] <= ln <= b_[2]]
        if inside:
            names.add(min(inside, key=lambda x: x[2] - x[1])[0])   # the innermost
            continue
        entry = None
        tops = [len(l) - len(l.lstrip()) for l in a if ENTRY_RX.match(l)]
        for k in range(i, -1, -1):
            m = ENTRY_RX.match(a[k])
            if m and len(a[k]) - len(a[k].lstrip()) == min(tops):   # the top-level entry it sits in
                entry = "'" + m.group(2) + "'"; break
        names.add(entry if (entry and path in HOTSPOTS) else '(top)')
    return 'change', sorted(names)


# ---------------------------------------------------------------- the page tree

def page_files(route, docs=DOCS, mirror=None):
    """The files a page in site-audit/pages.md is drawn by: `/directory/` -> website/directory/index.php."""
    route = route.strip()
    rows = open(os.path.join(docs, 'site-audit', 'pages.md'), encoding='utf-8').read().splitlines()
    found = None
    for r in rows:
        if not r.startswith('| `'):
            continue
        cell = r.split('|')[1]
        for code in re.findall(r'`([^`]+)`', cell):
            if code.split('?')[0].strip() == route.split('?')[0]:
                found = cell
                break
        if found:
            break
    if not found:
        raise SystemExit('No page %s in site-audit/pages.md (the page tree). Claim its files by path instead.' % route)
    out = []
    for code in re.findall(r'`([^`]+)`', found):
        p = code.split('?')[0].strip()
        if not p.startswith('/'):
            continue
        p = p.lstrip('/')
        if p == '' or p.endswith('/'):
            p += 'index.php'
        rel = 'website/' + p
        if mirror and not os.path.exists(mirror_path(mirror, rel)):
            continue
        if rel not in out:
            out.append(rel)
    if not out:
        raise SystemExit('The page %s has no file on the live mirror. Claim its files by path instead.' % route)
    return out

def zones_for(path, docs=DOCS):
    """Editor zones on a page's file (the page tree's "Editor zones" table, decisions/0161 §9)."""
    try:
        text = open(os.path.join(docs, 'site-audit', 'pages.md'), encoding='utf-8').read()
    except Exception:
        return []
    sec = text.split('## Editor zones', 1)
    if len(sec) < 2:
        return []
    out = []
    for r in sec[1].split('\n## ', 1)[0].splitlines():
        if r.startswith('| `'):
            cells = r.split('|')
            files = re.findall(r'`([^`]+)`', cells[2]) if len(cells) > 3 else []
            if path in files:
                out += re.findall(r'`([^`]+)`', cells[1])
    return out


# ---------------------------------------------------------------- a zip: what it changes, against the claims

def zip_changes(zpath, mirror):
    """[(path, kind, names)] for each site file in the zip: new files and kept-line edits are adds."""
    out = []
    with zipfile.ZipFile(zpath) as z:
        for n in z.namelist():
            if n.endswith('/') or n == 'release.json' or n.split('/')[0] not in MAP:
                continue
            live = mirror_path(mirror, n)
            new = z.read(n)
            if not os.path.exists(live):
                out.append((n, 'add', [])); continue
            old = open(live, 'rb').read()
            if old == new:
                continue
            kind, names = changed_names(n, old.decode('utf-8', 'replace'), new.decode('utf-8', 'replace'))
            out.append((n, kind, names))
    return out

def zip_review(zpath, mirror, session, docs=DOCS, d=None, at=None):
    """What the zip check says about claims: (blocks, warnings)."""
    d = d if d is not None else load(docs)
    at = at or now()
    blocks_, warns = [], []
    for path, kind, names in zip_changes(zpath, mirror):
        if path in SHARED:
            continue
        if kind == 'change':
            for c, hit in clashes(d, session, path, names, at):
                if hit == ['(the whole file)'] and not c['functions']:
                    hit = names
                blocks_.append('%s: %s is checked out for change by %s, for "%s", since %s. Build new beside old, or wait for '
                               'its release.' % (path, ', '.join(hit), c['session'], c['why'], c['since']))
            mine = [c for c in active(d, at) if c['session'] == session and c['path'] == path and c['kind'] == 'change']
            covered = any(not c['functions'] for c in mine) or set(names) <= {f for c in mine for f in c['functions']}
            if not covered:
                warns.append('%s: changes %s with no change claim (claim it: checkout.py claim %s::%s --change --why …)' % (
                    path, ', '.join(names), path, ','.join(n for n in names if not n.startswith('('))))
        for note in caller_notes(d, session, path, at):
            warns.append(path + ': ' + note)
    return blocks_, warns

def broken_callers(zpath, mirror, overlay):
    """§4: functions whose signature the zip changes so that a call on the (overlaid) live site no longer fits.
    Returns [(file, fn, callers)]. `overlay` is a directory laid out like the mirror with the zip's files over it."""
    out = []
    with zipfile.ZipFile(zpath) as z:
        for n in z.namelist():
            if n.endswith('/') or not n.endswith(('.php', '.js')) or n.split('/')[0] not in MAP:
                continue
            live = mirror_path(mirror, n)
            if not os.path.exists(live):
                continue
            old = signatures(open(live, encoding='utf-8', errors='replace').read())
            new = signatures(z.read(n).decode('utf-8', 'replace'))
            for fn, sig in old.items():
                if fn not in new or new[fn] == sig:
                    continue
                bad = []
                for p, full in site_files(overlay):
                    if not full.endswith(os.path.splitext(n)[1]):
                        continue
                    text = open(full, encoding='utf-8', errors='replace').read()
                    if any(fits(sig, k) and not fits(new[fn], k) for k in call_counts(text, fn)):
                        bad.append(p)
                if bad:
                    out.append((n, fn, sorted(bad)))
    return out


# ---------------------------------------------------------------- commands

def cmd_claim(a):
    session = my_session(a.session)
    if not session:
        sys.exit('No session id: pass --session <id>.')
    if not a.why:
        sys.exit('Say why in one line: --why "…".')
    kind = 'change' if a.change else 'add'
    items = []
    for spec in a.paths:
        path, fns = split(spec)
        items.append((path, fns, kind))
    if a.page:
        for p in page_files(a.page, mirror=a.mirror):
            items.append((p, [], 'add'))   # a page's own files, as add (decisions/0161 §2), unless --change names functions
    if not items:
        sys.exit('Name a file (path[::function]) or --page <route>.')
    res = save(lambda d: take(d, session, items, a.why, a.release or '', a.mirror),
               'Check-out: %s claims %s (%s)' % (session, ', '.join(i[0] for i in items)[:120], kind), a)
    if res.get('refused'):
        print('Refused (nothing was claimed):')
        for r in res['refused']:
            print('  ' + r)
        sys.exit(1)
    for c in res['taken']:
        print('Claimed: ' + describe(c))
        for fn, files in (c.get('callers') or {}).items():
            print('  %s is called on the live site by %d file(s): %s' % (fn, len(files), ', '.join(files[:8]) + (' …' if len(files) > 8 else '')))
    for s in res['skipped']:
        print('Not claimed: ' + s)
    for n in res['notes']:
        print('Note: ' + n)

def cmd_release(a):
    session = my_session(a.session)
    def apply(d):
        return release_where(d, session=session, paths=set(p.lstrip('/') for p in a.paths) or None, release=a.release)
    gone = save(apply, 'Check-in: %s releases %s' % (session, ', '.join(a.paths) or a.release or 'its claims'), a)
    print('Released %d claim(s).' % len(gone or []))
    for c in gone or []:
        print('  ' + describe(c))

def cmd_renew(a):
    session = my_session(a.session)
    def apply(d):
        exp = iso(now() + datetime.timedelta(hours=HOURS))
        mine = [c for c in d['claims'] if c['session'] == session and parse(c['expires']) > now()]
        for c in mine:
            c['expires'] = exp
        return mine
    mine = save(apply, 'Check-out: %s renews its claims' % session, a)
    print('Renewed %d claim(s) for 4 hours.' % len(mine or []))

def cmd_list(a):
    d = load(a=a)
    at = now()
    live = active(d, at)
    late = [c for c in d['claims'] if c not in live]
    if a.json:
        print(json.dumps({'claims': live, 'lapsed': late + d['lapsed']}, indent=1)); return
    if not live:
        print('Nothing is checked out.')
    for c in sorted(live, key=lambda c: c['since']):
        left = parse(c['expires']) - at
        print(('NEAR EXPIRY ' if left < datetime.timedelta(minutes=30) else '') + describe(c))
    for c in late + d['lapsed'][:10]:
        print('lapsed: ' + describe(c))

def cmd_expire(a):
    gone = save(lambda d: lapse(d), 'Check-out: lapsed claims reported', a)
    print('%d claim(s) lapsed.' % len(gone or []))
    for c in gone or []:
        print('  lapsed: ' + describe(c))

def live_releases(docs=DOCS):
    """Zip names (with and without .zip) the delivery log shows live."""
    out = set()
    for p in glob.glob(os.path.join(docs, 'deliveries', '*.json')):
        try:
            d = json.load(open(p))
        except Exception:
            continue
        if d.get('status') in ('live', 'replaced'):
            z = d.get('zip') or os.path.basename(p)[:-5] + '.zip'
            out |= {z, os.path.splitext(z)[0]}
    return out

def release_live(d, docs=DOCS):
    """Release every claim whose release is live, and report lapsed ones. Returns (released, lapsed)."""
    live = live_releases(docs)
    gone = [c for c in d['claims'] if c.get('release') and c['release'] in live]
    d['claims'] = [c for c in d['claims'] if c not in gone]
    return gone, lapse(d)

def cmd_release_live(a):
    res = save(lambda d: release_live(d), 'Check-in: claims of releases now live', a)
    gone, late = res
    for c in gone:
        print('Released (live): ' + describe(c))
    for c in late:
        print('Lapsed: ' + describe(c))
    if not gone and not late:
        print('Nothing to release.')

def cmd_callers(a):
    path, fns = split(a.spec)
    for fn, files in find_callers(a.mirror, path, fns).items():
        print('%s: %d file(s)' % (fn, len(files)))
        for f in files:
            print('  ' + f)

def main(argv=None):
    ap = argparse.ArgumentParser(description='Check-out for site files (decisions/0161).')
    sp = ap.add_subparsers(dest='cmd', required=True)
    c = sp.add_parser('claim'); c.add_argument('paths', nargs='*')
    g = c.add_mutually_exclusive_group(required=True); g.add_argument('--add', action='store_true'); g.add_argument('--change', action='store_true')
    c.add_argument('--why', required=True); c.add_argument('--page'); c.add_argument('--mirror'); c.add_argument('--release')
    r = sp.add_parser('release'); r.add_argument('paths', nargs='*'); r.add_argument('--release')
    sp.add_parser('renew'); l = sp.add_parser('list'); l.add_argument('--json', action='store_true')
    sp.add_parser('expire'); sp.add_parser('release-live')
    k = sp.add_parser('callers'); k.add_argument('spec'); k.add_argument('--mirror', required=True)
    for p in sp.choices.values():
        p.add_argument('--session'); p.add_argument('--local', action='store_true', help='the working copy, not main (tests)')
    a = ap.parse_args(argv)
    {'claim': cmd_claim, 'release': cmd_release, 'renew': cmd_renew, 'list': cmd_list, 'expire': cmd_expire,
     'release-live': cmd_release_live, 'callers': cmd_callers}[a.cmd](a)

if __name__ == '__main__':
    main()
