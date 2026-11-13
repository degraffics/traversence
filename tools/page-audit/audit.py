#!/usr/bin/env python3
"""The site audit's code checks (decision 0145, Phase 1). Standard library only.

    python3 tools/page-audit/audit.py --mirror <traverse-3way-sync checkout> [--docs <this repo>] [--write]
    python3 tools/page-audit/audit.py --mirror <...> --json out.json      (findings only, for zipcheck.py)

Reads the live mirror (sync-storage = website/, sync-app = app/, sync-trvgo = trvgo/). With --write it writes:
  site-audit/scorecard.md        each rule's failures, worst first, with file and line
  site-audit/page-tree.md        every page: address, shell and session, listed in site-audit/pages.md / archive.md, decisions
  decisions/index.md             the decision register: status, replaced by, files governed, stale references
  site-audit/results/latest.json all findings
Every check names its rule in site-audit/rules.md. A rule that blocks a delivery is marked "block" there and in RULES.
"""
import argparse, collections, datetime, json, os, re, sys

MAP = {'sync-storage': 'website', 'sync-app': 'app', 'sync-trvgo': 'trvgo'}

# rule id -> (title, blocks a delivery?)
RULES = {
    'R01': ('Every method a file calls exists', True),
    'R02': ('No browser dialogs (alert, confirm, prompt)', True),
    'R03': ('Uploads only through Uploads.php and /api/upload.php', True),
    'R04': ('No PHP newer than the server runs (site-audit/results/server.json; 8.0 until it is known)', True),
    'R05': ('Pages load the session and the shared shell', False),
    'R06': ('FAQ links point at questions that exist', True),
    'R07': ('Endpoints that change things check the security token', False),
    'R08': ('Migrations are add-only and safe for MySQL', True),
    'R09': ('Every page is in site-audit/pages.md or archive.md', False),
    'R10': ("The brand's icons, not emoji", False),
    'R11': ('No breadcrumbs on content pages', False),
}

def load(mirror):
    files = {}
    for top, as_ in MAP.items():
        base = os.path.join(mirror, top)
        for root, dirs, names in os.walk(base):
            dirs[:] = [d for d in dirs if not d.startswith('.') and d not in ('_htaccess-backups', '_errors', 'vendor', 'storage')]
            for n in names:
                if not n.endswith(('.php', '.js', '.md', '.sql')):
                    continue
                p = os.path.join(root, n)
                rel = as_ + '/' + os.path.relpath(p, base).replace(os.sep, '/')
                try:
                    with open(p, encoding='utf-8', errors='replace') as fh:
                        files[rel] = fh.read()
                except OSError:
                    pass
    return files

def strip_php_comments(src):
    src = re.sub(r'/\*.*?\*/', lambda m: '\n' * m.group(0).count('\n'), src, flags=re.S)
    out = []
    for line in src.split('\n'):
        t = line.lstrip()
        out.append('' if t.startswith(('//', '#', '*')) else line)
    return '\n'.join(out)

def line_of(src, pos):
    return src.count('\n', 0, pos) + 1

def find(findings, rule, path, line, msg):
    findings.append({'rule': rule, 'file': path, 'line': line, 'msg': msg})

# ---- R01: calls to methods that don't exist ------------------------------------------------------------------------
def check_calls(files, findings):
    classes, parents, methods, magic = {}, {}, collections.defaultdict(set), set()
    for path, src in files.items():
        if not path.endswith('.php'):
            continue
        code = strip_php_comments(src)
        for m in re.finditer(r'^\s*(?:final\s+|abstract\s+)?class\s+(\w+)(?:\s+extends\s+(\w+))?', code, re.M):
            classes[m.group(1)] = path
            if m.group(2):
                parents[m.group(1)] = m.group(2)
        names = set(re.findall(r'function\s+&?\s*(\w+)\s*\(', code))
        for c in re.findall(r'^\s*(?:final\s+|abstract\s+)?class\s+(\w+)', code, re.M):
            methods[c] |= names
            if '__callStatic' in names:
                magic.add(c)
    def has(c, m, seen=0):
        if c not in classes or seen > 8:
            return True   # not ours (PDO, DateTime ...): not checked
        if m in methods[c] or c in magic:
            return True
        return has(parents[c], m, seen + 1) if c in parents else False
    for path, src in files.items():
        if not path.endswith('.php'):
            continue
        code = strip_php_comments(src)
        for m in re.finditer(r'(?<![\w$\\])([A-Z]\w+)::(\w+)\s*\(', code):
            c, meth = m.group(1), m.group(2)
            if c in ('self', 'static', 'parent') or c not in classes:
                continue
            if not has(c, meth):
                find(findings, 'R01', path, line_of(code, m.start()), f'{c}::{meth}() is called but {classes[c]} has no {meth}()')

# ---- R02: browser dialogs ------------------------------------------------------------------------------------------
def check_dialogs(files, findings):
    for path, src in files.items():
        if not path.endswith(('.js', '.php')) or path.endswith('tv-ask.js'):
            continue
        for i, line in enumerate(src.split('\n'), 1):
            t = line.strip()
            if t.startswith(('//', '*', '/*', '#')):
                continue
            if re.search(r'function\s+(?:alert|confirm|prompt)\b|(?:alert|confirm|prompt)\s*:\s*function', line):
                continue
            for m in re.finditer(r'(?<![\w.$:>\s])(?:window\.)?(alert|confirm|prompt)\(|(?<=[\s;{(!=&|?,])(?<!::)(?<!->)(?:window\.)?(alert|confirm|prompt)\((?=[\'"`\w])', line):
                if re.search(r'[A-Za-z]\s+$', line[:m.start()]) and not re.search(r'(?:if|return|=|\(|&&|\|\||!|;|{)\s*$', line[:m.start()]):
                    continue   # a word in a sentence ("that nobody confirmed (…)"), not a call
                find(findings, 'R02', path, i, f'{m.group(1) or m.group(2)}() opens a browser dialog: use TvAsk.say / TvAsk.confirm / TvAsk.ask')

# ---- R03: uploads ----------------------------------------------------------------------------------------------------
def check_uploads(files, findings):
    ok = {'app/lib/Uploads.php', 'website/api/upload.php'}
    for path, src in files.items():
        if not path.endswith('.php') or path in ok:
            continue
        code = strip_php_comments(src)
        for m in re.finditer(r'\$_FILES|is_uploaded_file\s*\(|move_uploaded_file\s*\(', code):
            find(findings, 'R03', path, line_of(code, m.start()), 'handles an upload itself: send it through TvUpload and an upload target')

# ---- R01 too: a helper from app-shell.php called before the page start loads it -------------------------------------
# app/ui/app-shell.php (tv_icon, tv_count_item…) is loaded by tv_page_start(), not by page-shell.php itself: a page that
# calls one first stops with a fatal error (journeys/suggested.php did, 2026-11-13).
def check_shell_order(files, findings):
    shell = files.get('app/ui/app-shell.php', '')
    fns = re.findall(r'^function (\w+)', shell, re.M)
    if not fns:
        return
    pat = re.compile(r'\b(' + '|'.join(fns) + r')\s*\(')
    for path, src in files.items():
        if not (path.startswith('website/') and path.endswith('.php')) or re.search(r'app-shell\.php|context-switcher\.php|admin-hub\.php', src):
            continue
        lines = src.split('\n')
        starts = [i for i, l in enumerate(lines) if re.search(r'\btv_(page|app)_start\s*\(', l)]
        if not starts:
            continue
        for i, l in enumerate(lines[:starts[-1]]):
            m = pat.search(l)
            if not m or re.search(r'^\s*(//|\*|#)', l) or 'function_exists' in l:
                continue
            before = [st for st in starts if st < i]
            if before and 'exit' not in '\n'.join(lines[max(before):i]):
                continue   # after a start in the same branch
            find(findings, 'R01', path, i + 1, f'calls {m.group(1)}() before tv_page_start() loads app-shell.php, where it lives: call it after the page start')

# ---- R04: PHP newer than the server runs ---------------------------------------------------------------------------
# Each feature with the PHP version it needs. The server's version comes from the health report, saved daily by
# tools/page-audit/towns.mjs into site-audit/results/server.json (site audit P1, Jason 2026-11-08); 8.0 until it is known.
PHP81 = [
    (r'\breadonly\s+(?:public|protected|private|\$|\w+\s+\$)', 'readonly properties', (8, 1)),
    (r'^\s*enum\s+\w+', 'enum', (8, 1)),
    (r'\barray_is_list\s*\(', 'array_is_list()', (8, 1)),
    (r'\bjson_validate\s*\(', 'json_validate()', (8, 3)),
    (r'\)\s*:\s*never\b', "the 'never' return type", (8, 1)),
    (r'(?<![\w$])\w+\(\.\.\.\)', 'first-class callable syntax', (8, 1)),
    (r'\bconst\s+(?:int|string|bool|float|array)\s+[A-Z_]+\s*=', 'typed class constants', (8, 3)),
    (r'function\s+\w+\s*\([^)]*=\s*new\s+\w+', "'new' in a parameter default", (8, 1)),
]
def server_php(docs):
    """The server's PHP (major, minor) from the health report, else (8, 0)."""
    try:
        v = json.load(open(os.path.join(docs, 'site-audit', 'results', 'server.json'), encoding='utf-8')).get('php_major_minor') or ''
        a, b = v.split('.')[:2]
        return int(a), int(b)
    except Exception:
        return 8, 0
def check_php80(files, findings, server=(8, 0)):
    for path, src in files.items():
        if not path.endswith('.php'):
            continue
        code = strip_php_comments(src)
        for pat, what, need in PHP81:
            if need <= server:
                continue
            for m in re.finditer(pat, code, re.M):
                find(findings, 'R04', path, line_of(code, m.start()), f'uses {what} (PHP {need[0]}.{need[1]}); the server runs PHP {server[0]}.{server[1]}')

# ---- R05: pages load the session and the shell -------------------------------------------------------------------
def is_page(path):
    return path.startswith('website/') and path.endswith('.php') and '/api/' not in path and '/_' not in path \
        and not path.startswith('website/js/') and not path.startswith(('website/image/', 'website/uploads/'))

def page_info(path, src):
    html = bool(re.search(r'<html|<!DOCTYPE|tv_page_start\s*\(|tv_shell', src, re.I))
    boot = 'bootstrap.php' in src or 'tv_page_start(' in src or 'page-shell.php' in src
    bar = 'tv_acting_bar' in src or 'tv_page_start(' in src or 'page-shell.php' in src
    redirect = bool(re.search(r"header\(\s*['\"]Location:", src)) and not html
    return html, boot, bar, redirect

def with_includes(files, path, depth=2):
    """A page's own text plus the app/ files it requires (two levels deep), so a page that loads the session through a
    gate or shell file counts."""
    seen, todo, out = {path}, [path], [files.get(path, '')]
    for _ in range(depth):
        nxt = []
        for p in todo:
            for m in re.finditer(r"(app/[\w/.-]+\.php)", files.get(p, '')):
                q = m.group(1)
                if q in files and q not in seen:
                    seen.add(q); nxt.append(q); out.append(files[q])
        todo = nxt
    return '\n'.join(out)

def check_shell(files, findings):
    for path, src in files.items():
        if not is_page(path):
            continue
        html, _, _, redirect = page_info(path, src)
        _, boot, bar, _ = page_info(path, with_includes(files, path))
        if html and not boot:
            find(findings, 'R05', path, 1, 'draws a page without the session (bootstrap.php or tv_page_start): signed-in people look signed out')
        elif html and not bar:
            find(findings, 'R05', path, 1, 'draws a page without the account bar (tv_acting_bar or tv_page_start)')

# ---- R06: FAQ links ------------------------------------------------------------------------------------------------
def faq_slug(s):
    s = re.sub(r'<[^>]+>', '', s).lower()
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-') or 'q'

def faq_ids(md):
    ids, used = set(), set()
    for line in md.split('\n'):
        line = line.rstrip()
        if line.startswith('## '):
            ids.add(faq_slug(line[3:].strip()))
        elif line.startswith('### '):
            i = faq_slug(line[4:].strip())[:60]; base, n = i, 2
            while i in used:
                i = f'{base}-{n}'; n += 1
            used.add(i); ids.add(i)
    return ids

def check_faq(files, findings):
    md = files.get('app/ui/faq.md')
    if md is None:
        return
    ids = faq_ids(md)
    for path, src in files.items():
        for m in re.finditer(r'faq\.php#([a-z0-9-]+)', src):
            if m.group(1) not in ids:
                find(findings, 'R06', path, line_of(src, m.start()), f'links to faq.php#{m.group(1)}, which no FAQ question has')

# ---- R07: endpoints and the security token -----------------------------------------------------------------------
TOKENLESS = re.compile(r'webhook|callback|oauth|google|microsoft|facebook|apple|stripe|square|quickbooks|qbo|ses|cron|crawl|scan|beacon|count', re.I)
def check_csrf(files, findings):
    for path, src in files.items():
        if not (path.endswith('.php') and '/api/' in path):
            continue
        code = strip_php_comments(src)
        writes = re.search(r"requireMethod\(\s*'POST'|\$_POST|php://input|jsonBody\(", code)
        if writes and 'requireCsrf' not in code and not TOKENLESS.search(path) and 'hash_equals' not in code:
            find(findings, 'R07', path, line_of(code, writes.start()), 'accepts a POST without Response::requireCsrf() (or a signature check): say why, or add it')

# ---- R08: migrations -------------------------------------------------------------------------------------------------
def check_migrations(files, findings):
    bad = [(r'ADD\s+(?:COLUMN|INDEX|KEY)\s+IF\s+NOT\s+EXISTS', 'IF NOT EXISTS inside ALTER TABLE (MySQL rejects it; guard with information_schema)'),
           (r'\bDROP\s+(?:COLUMN|TABLE|INDEX|KEY)\b', 'drops something: a release is add-only, removals come in a later one'),
           (r'\bRENAME\s+(?:COLUMN|TABLE|TO)\b', 'renames something: add-only within a release'),
           (r'\bDELIMITER\b|\bCREATE\s+(?:PROCEDURE|FUNCTION|TRIGGER)\b', 'stored routines or DELIMITER (the runner splits on ;)')]
    for path, src in files.items():
        if not (path.startswith('app/migrations/') and path.endswith('.sql')):
            continue
        for pat, what in bad:
            for m in re.finditer(pat, src, re.I):
                if what.startswith('drops') and re.search(r"'DO 0'|IF\(COUNT", src[max(0, m.start() - 200):m.start()]):
                    continue
                find(findings, 'R08', path, line_of(src, m.start()), what)
        if re.search(r'\bCREATE\s+TABLE\b(?!\s+IF\s+NOT\s+EXISTS)', src, re.I):
            find(findings, 'R08', path, 1, 'CREATE TABLE without IF NOT EXISTS: not safe to run twice')

# ---- R09: page tree ------------------------------------------------------------------------------------------------
def page_url(path):
    p = path[len('website'):]
    return p[:-len('index.php')] if p.endswith('/index.php') else p

def check_listed(files, docs, findings, tree):
    listed = ''
    for name in (os.path.join('site-audit', 'pages.md'), 'archive.md'):   # pages.md replaced routes.md (P6, 2026-11-13)
        try:
            listed += open(os.path.join(docs, name), encoding='utf-8').read()
        except OSError:
            pass
    for path in sorted(p for p in files if is_page(p)):
        url = page_url(path)
        inlist = url in listed or path[len('website/'):] in listed or (url.rstrip('/') and url.rstrip('/') + '/' in listed)
        html, _, _, redirect = page_info(path, files[path])
        _, boot, bar, _ = page_info(path, with_includes(files, path))
        tree.append((url, path, html, boot, bar, redirect, inlist))
        if not inlist:
            find(findings, 'R09', path, 1, f'{url} is on the server but in neither site-audit/pages.md nor archive.md')

# ---- R10, R11: emoji and breadcrumbs -------------------------------------------------------------------------------
EMOJI = re.compile('[\U0001F000-\U0001FAFF]|\uFE0F')   # pictographs; typographic marks (✓ ✕ ✎ ☰ ▾) are allowed
def check_emoji_crumbs(files, findings):
    for path, src in files.items():
        if not (path.startswith('website/') or path.startswith('app/ui/')) or not path.endswith(('.php', '.js')):
            continue
        for i, line in enumerate(src.split('\n'), 1):
            t = line.strip()
            if t.startswith(('//', '*', '#', '/*')):
                continue
            if EMOJI.search(line) and ("'" in line or '"' in line or '>' in line):
                find(findings, 'R10', path, i, 'emoji in what the page shows: use tv_icon() (the brand\'s icons)')
            if is_page(path) and re.search(r'class=["\'][^"\']*breadcrumb', line, re.I):
                find(findings, 'R11', path, i, 'breadcrumbs on a page: content pages have none (decisions/0058)')

# ---- the decision register -----------------------------------------------------------------------------------------
def register(docs, files):
    out = []
    ddir = os.path.join(docs, 'decisions')
    for n in sorted(os.listdir(ddir)):
        m = re.match(r'^(\d{4})-.*\.md$', n)
        if not m:
            continue
        src = open(os.path.join(ddir, n), encoding='utf-8').read()
        title = (re.search(r'^#\s+(.+)$', src, re.M) or [None, n])[1]
        title = re.sub(r'^ADR\s+\d+:\s*', '', title).strip()
        st = re.search(r'\*\*Status:?\*\*:?\s*(.+)', src)
        status = st.group(1).strip() if st else ''
        low = status.lower()
        kind = 'replaced' if re.search(r'supersed|replaced|retired|withdrawn', low) else ('proposed' if 'propos' in low or 'draft' in low else 'accepted')
        by = sorted(set(re.findall(r'(?:superseded|replaced)\s+by[^.\n]{0,60}?(\d{4})', src, re.I)))
        notes = len(re.findall(r'^\*\*Note', src, re.M))
        refs = sorted(set(re.findall(r'\b((?:app|website|trvgo)/[\w./-]+\.(?:php|js|md|sql))\b', src)))
        gone = [r for r in refs if r not in files and not r.startswith('app/migrations/')]
        out.append({'id': m.group(1), 'file': n, 'title': title, 'status': kind, 'status_text': status[:120], 'replaced_by': by,
                    'notes': notes, 'files': refs, 'missing_files': gone})
    return out

# ---- writing --------------------------------------------------------------------------------------------------------
def write_all(docs, findings, tree, reg, mirror_sha, accepted=()):
    now = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M UTC')
    os.makedirs(os.path.join(docs, 'site-audit', 'results'), exist_ok=True)
    with open(os.path.join(docs, 'site-audit', 'results', 'latest.json'), 'w', encoding='utf-8') as fh:
        json.dump({'run': now, 'mirror': mirror_sha, 'findings': findings}, fh, indent=1)
    by = collections.defaultdict(list)
    for f in findings:
        by[f['rule']].append(f)
    L = [f'# Scorecard', '', f'Decision 0145. Generated by `tools/page-audit/audit.py` on {now}, from the mirror at `{mirror_sha}`. Don\'t edit by hand.', '',
         '| Rule | What | Failures | Blocks a delivery |', '|---|---|---|---|']
    for rid, (title, block) in RULES.items():
        L.append(f'| {rid} | {title} | {len(by[rid])} | {"yes" if block else "no"} |')
    files_hit = collections.Counter(f['file'] for f in findings)
    L += ['', '## Files with the most failures', '', '| File | Failures |', '|---|---|']
    L += [f'| `{p}` | {c} |' for p, c in files_hit.most_common(15)]
    for rid, (title, _) in RULES.items():
        if not by[rid]:
            continue
        L += ['', f'## {rid}: {title} ({len(by[rid])})', '']
        for f in sorted(by[rid], key=lambda x: (x['file'], x['line']))[:60]:
            L.append(f'- `{f["file"]}:{f["line"]}`: {f["msg"]}')
        if len(by[rid]) > 60:
            L.append(f'- … and {len(by[rid]) - 60} more (all in `results/latest.json`)')
    if accepted:
        L += ['', f'## Accepted exceptions ({len(accepted)})', '', 'Reviewed, with a reason, in `exceptions.json`. Not counted as failures.', '']
        L += [f'- {f["rule"]} `{f["file"]}`: {f["accepted"]}' for f in accepted]
    open(os.path.join(docs, 'site-audit', 'scorecard.md'), 'w', encoding='utf-8').write('\n'.join(L) + '\n')

    gov = collections.defaultdict(list)
    for d in reg:
        for r in d['files']:
            gov[r].append(d['id'])
    T = ['# Page tree', '', f'Decision 0145 §1a. Generated from the live mirror on {now} (`{mirror_sha}`). Checked against the',
         'register `site-audit/pages.md` (it replaced `routes.md`, P6). Columns: does the page load the session and the shell, does it draw the account bar, is it in',
         '`pages.md` / `archive.md`, and which decisions name its file.', '',
         '| Address | File | Session | Account bar | Listed | Decisions |', '|---|---|---|---|---|---|']
    for url, path, html, boot, bar, redirect, inlist in tree:
        kind = 'redirect' if redirect else ('yes' if boot else ('—' if not html else '**no**'))
        T.append(f'| `{url}` | `{path}` | {kind} | {"yes" if bar else ("—" if not html else "**no**")} | {"yes" if inlist else "**no**"} | {", ".join(gov.get(path, [])) or ""} |')
    open(os.path.join(docs, 'site-audit', 'page-tree.md'), 'w', encoding='utf-8').write('\n'.join(T) + '\n')

    R = ['# Decision register', '', f'Decision 0145 §1a. Generated on {now} from `decisions/` and the live mirror (`{mirror_sha}`). Don\'t edit by hand:',
         'edit the decision, and this follows. "Missing files" are files a decision names that the site no longer has: the',
         'decision may be stale, or the file moved.', '',
         '| # | Decision | Status | Replaced by | Notes | Files named | Missing files |', '|---|---|---|---|---|---|---|']
    for d in reg:
        R.append(f'| {d["id"]} | [{d["title"]}]({d["file"]}) | {d["status"]} | {", ".join(d["replaced_by"])} | {d["notes"] or ""} | {len(d["files"])} | '
                 + (', '.join(f'`{x}`' for x in d['missing_files'][:4]) + (' …' if len(d['missing_files']) > 4 else '')) + ' |')
    open(os.path.join(docs, 'decisions', 'index.md'), 'w', encoding='utf-8').write('\n'.join(R) + '\n')

def exceptions(docs):
    """Reviewed exceptions (site-audit/exceptions.json): [{"rule", "file", "reason", "by"}]. A finding that matches one
    is an accepted exception, not a failure. Every exception needs a reason; Jason approves them."""
    try:
        return json.load(open(os.path.join(docs, 'site-audit', 'exceptions.json'), encoding='utf-8'))
    except (OSError, ValueError):
        return []

def run(mirror, docs, keep_exceptions=False):
    files = load(mirror)
    findings, tree = [], []
    check_calls(files, findings); check_shell_order(files, findings); check_dialogs(files, findings); check_uploads(files, findings); check_php80(files, findings, server_php(docs))
    check_shell(files, findings); check_faq(files, findings); check_csrf(files, findings); check_migrations(files, findings)
    check_listed(files, docs, findings, tree); check_emoji_crumbs(files, findings)
    ex = {(e['rule'], e['file']): e for e in exceptions(docs) if e.get('reason')}
    kept = []
    for f in findings:
        e = ex.get((f['rule'], f['file']))
        if e:
            f['accepted'] = e['reason']
        if keep_exceptions or not e:
            kept.append(f)
    return files, kept, tree

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--mirror', required=True)
    ap.add_argument('--docs', default=os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..'))
    ap.add_argument('--write', action='store_true')
    ap.add_argument('--json')
    a = ap.parse_args()
    docs = os.path.abspath(a.docs)
    files, findings, tree = run(a.mirror, docs)
    accepted = [f for f in run(a.mirror, docs, True)[1] if f.get('accepted')]
    sha = os.popen(f'git -C "{a.mirror}" rev-parse --short HEAD 2>/dev/null').read().strip() or 'unknown'
    if a.json:
        json.dump(findings, open(a.json, 'w'), indent=1)
    if a.write:
        write_all(docs, findings, tree, register(docs, files), sha, accepted)
    c = collections.Counter(f['rule'] for f in findings)
    print(f'{len(files)} files, {len(tree)} pages, {len(findings)} findings: ' + ', '.join(f'{r} {c[r]}' for r in RULES if c[r]))

if __name__ == '__main__':
    main()
