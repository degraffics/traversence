#!/usr/bin/env python3
"""The page-to-decision map (decision 0145 Phase 4): each page and the decisions and docs that govern it.

    python3 tools/page-audit/page-map.py

The pages are every route in routes.md and every page in site-audit/page-tree.md (generated from the live mirror). For
each, it finds the decisions and governing docs that name it (its address, /listing/view.php, or its file,
website/listing/view.php), with the section each names it in; the decisions routes.md gives it; the site-wide standards
that apply to its kind of page (every page, content pages, admin pages, record pages, endpoints); and the paths that pass
through it (results/paths.json). A page no decision names is flagged (rule G02: every page is governed by a decision).

Writes site-audit/page-map.md and site-audit/results/page-map.json (which the page-review skill reads). Needs no
network and no mirror: run it after page-tree.md or the decisions change (the site checks' workflow runs it daily).
"""
import datetime, glob, json, os, re

HERE = os.path.dirname(os.path.abspath(__file__))
DOCS = os.path.abspath(os.path.join(HERE, '..', '..'))
# governing docs besides the decisions (README "Start here", "Site structure and admin", brand)
GOVERNING = ['CLAUDE.md', 'brand.md', 'ux-review.md', 'architecture.md', 'admin-architecture.md', 'business-portal-architecture.md',
             'marketplace-architecture.md', 'community-scope.md', 'master-aos.md', 'prd.md', 'toolkit.md', 'archive.md']
# what applies to a kind of page, whether or not a decision names it (CLAUDE.md's "every page" rules)
EVERY = ['CLAUDE.md: Phone layout and visual standard (0058 §11)', 'CLAUDE.md: No browser dialogs; one look for notices', 'CLAUDE.md: Never a dead end (rule T06)']
CONTENT = re.compile(r'^/(listing/view\.php|place/(landmark|recreation)\.php|experiences/view\.php|journeys/(view|suggested)\.php|hub/|user/profile\.php|user/post\.php|community/group\.php)')
KINDS = [
    (lambda a: bool(CONTENT.match(a)), 'content', ['0058 §20, §21: Content pages (hero, header, engage bar, comments)', 'CLAUDE.md: Times where they happened']),
    (lambda a: a.startswith('/admin/') and '/api' not in a, 'admin', ['0109: The admin (manifests, widgets, Access)']),
    (lambda a: a.startswith('/admin/') and re.search(r'\b(order|edit|view|item|client-order|listing-edit|guide-edit|content-template)\b', a) is not None, 'record', ['0129: Record pages: the command strip']),
    (lambda a: '/api/' in a or a.startswith('/api'), 'endpoint', ['CLAUDE.md: Links past Bluehost\'s ModSecurity', 'rule R07: the security token']),
    (lambda a: re.search(r'(login|register|forgot-password|reset-password|two-step)\.php', a) is not None, 'sign-in', ['CLAUDE.md: Password boxes (js/tv-password.js)', '0014: Registration flow']),
    (lambda a: a.startswith('/market/') or a.startswith('/shop/'), 'market', ['CLAUDE.md: No test moves real money (0145)']),
]


def read(p):
    try:
        return open(p, encoding='utf-8').read()
    except OSError:
        return ''


def pages():
    """address → {'file', 'routes_adr': [..], 'purpose', 'status', 'in': {'tree','routes'}}"""
    out = {}
    for line in read(os.path.join(DOCS, 'site-audit', 'page-tree.md')).split('\n'):
        m = re.match(r'^\| `([^`]+)` \| `(website/[^`]+)` \|', line)
        if m:
            out.setdefault(m.group(1), {'file': m.group(2), 'routes_adr': [], 'purpose': '', 'status': '', 'in': set()})['in'].add('tree')
    section = ''
    for line in read(os.path.join(DOCS, 'routes.md')).split('\n'):
        if line.startswith('## '):
            section = line[3:].strip()
        if not line.startswith('| `'):
            continue
        cells = [c.strip() for c in line.strip().strip('|').split('|')]
        if len(cells) < 2 or section.startswith(('7.', '8.')):   # "Left over: remove" and "Designed, not built" aren't pages
            continue
        adr = re.findall(r'\b(\d{4})\b', cells[2]) if len(cells) > 2 else []
        for addr in re.findall(r'`(/[^`\s]*)`', cells[0]):
            base = addr.split('?')[0]
            if not base or base == '/' and addr != '/':
                continue
            e = out.setdefault(base, {'file': '', 'routes_adr': [], 'purpose': '', 'status': '', 'in': set()})
            e['in'].add('routes'); e['routes_adr'] = sorted(set(e['routes_adr']) | set(adr))
            e['purpose'] = e['purpose'] or cells[1][:140]; e['status'] = e['status'] or (cells[3] if len(cells) > 3 else '')[:60]
    return out


def patterns(addr, file):
    if addr == '/':
        return [re.compile(r'(?:^|(?<=[\s(]))`/`(?=[\s,.;:)]|$)|website/index\.php|^# .*\bhome page\b', re.I | re.M)]   # `/` on its own (not `a`/`b`), its file, or "home page" in a decision's title
    if addr.endswith('*'):
        return [re.compile(r'(?<![\w.-])' + re.escape(addr[:-1]))]
    ps = [re.compile(r'(?<![\w.-])' + re.escape(addr) + (r'(index\.php)?(?![\w-])' if addr.endswith('/') else r'(?![\w-])'))]
    if file:
        ps.append(re.compile(re.escape(file) + r'(?![\w-])'))
    if addr.endswith('.php') and addr.count('/') >= 2:   # "listing/view.php" written without its leading slash
        ps.append(re.compile(r'(?<![\w/.-])' + re.escape(addr[1:]) + r'(?![\w-])'))
    return ps


def section_at(src, pos):
    """The heading a mention sits under: '§20' when the heading is numbered, else its words (short)."""
    heads = [m for m in re.finditer(r'^(#{2,4})\s+(.+)$', src[:pos], re.M)]
    if not heads:
        return ''
    t = heads[-1].group(2).strip()
    m = re.match(r'^(?:§\s*)?(\d+[a-z]?)[.)]\s', t)
    return '§' + m.group(1) if m else (t[:32] + ('…' if len(t) > 32 else ''))


def build():
    pg = pages()
    docs = []
    for f in sorted(glob.glob(os.path.join(DOCS, 'decisions', '[0-9][0-9][0-9][0-9]-*.md'))):
        src = read(f)
        st = re.search(r'\*\*Status:?\*\*:?\s*(.+)', src)
        docs.append((os.path.basename(f)[:4], os.path.basename(f), src, bool(st and re.search(r'supersed|replaced|retired|withdrawn', st.group(1), re.I))))
    for g in GOVERNING:
        docs.append((g, g, read(os.path.join(DOCS, g)), False))
    paths = {}
    try:
        for r in json.load(open(os.path.join(DOCS, 'site-audit', 'results', 'paths.json'), encoding='utf-8')).get('results', []):
            for p in r.get('pages', []):
                paths.setdefault(p.split('?')[0].split('#')[0], set()).add(r['title'] + ('' if r['kind'] == 'guide' else f' ({r["who"]})'))
    except (OSError, ValueError):
        pass
    out = []
    for addr in sorted(pg):
        e = pg[addr]
        ps = patterns(addr, e['file'])
        named = []
        for did, fname, src, replaced in docs:
            hits = [m for p in ps for m in p.finditer(src)]
            if not hits:
                continue
            secs = []
            for m in sorted(hits, key=lambda m: m.start()):
                s = section_at(src, m.start())
                if s and s not in secs:
                    secs.append(s)
            named.append({'doc': did, 'file': fname, 'sections': secs[:3], 'mentions': len(hits), 'replaced': replaced})
        named.sort(key=lambda n: (not n['doc'][:1].isdigit(), n['doc']))
        kinds = [k for test, k, _ in KINDS if test(addr)]
        std = list(EVERY) + [s for test, _, ss in KINDS if test(addr) for s in ss]
        dec = sorted({n['doc'] for n in named if n['doc'][:1].isdigit() and not n['replaced']} | set(e['routes_adr']))
        out.append({'page': addr, 'file': e['file'], 'in': sorted(e['in']), 'purpose': e['purpose'], 'status': e['status'], 'kinds': kinds,
                    'decisions': dec, 'named_in': named, 'standards': std, 'paths': sorted(paths.get(addr, set())), 'governed': bool(dec)})
    return out


def write(rows):
    now = datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%d %H:%M UTC')
    os.makedirs(os.path.join(DOCS, 'site-audit', 'results'), exist_ok=True)
    with open(os.path.join(DOCS, 'site-audit', 'results', 'page-map.json'), 'w', encoding='utf-8') as fh:   # a page a line: small diffs day to day
        fh.write('{"at": ' + json.dumps(now) + ', "pages": [\n' + ',\n'.join(json.dumps(r, ensure_ascii=False) for r in rows) + '\n]}\n')
    missing = [r for r in rows if not r['governed']]
    only_routes = [r for r in rows if r['in'] == ['routes']]
    L = ['# Page map', '', f'Decision 0145, Phase 4. Generated by `tools/page-audit/page-map.py` on {now} from `routes.md`, `page-tree.md`, `decisions/` and the governing docs. Don\'t edit by hand: edit the decision or doc, and this follows.', '',
         f'{len(rows)} pages: {sum(1 for r in rows if "tree" in r["in"])} on the live site (the page tree), {len(only_routes)} only in `routes.md` (endpoints, and routes the tree doesn\'t list as pages). '
         f'**{len(missing)} are named by no decision** (rule G02): the review proposes which decision should govern each, or a new one.', '',
         '**Columns:** *Decisions* are those that name the page (its address or file) with the section they name it in, and those `routes.md` gives it; a replaced decision is left out. *Docs* are governing docs that name it. '
         '*Standards* are what applies to every page of its kind (all pages: `CLAUDE.md`\'s phone layout, notices and never a dead end; content pages: 0058 §20–21; admin: 0109; record pages: 0129; endpoints: ModSecurity and the security token). *Paths* pass through it (`paths.md`).', '',
         '| Page | Kind | Decisions | Docs | Paths |', '|---|---|---|---|---|']
    for r in rows:
        dec = '; '.join(f"[{n['doc']}](../decisions/{n['file']})" + (' ' + ', '.join(n['sections']) if n['sections'] else '') for n in r['named_in'] if n['doc'][:1].isdigit() and not n['replaced'])
        extra = [d for d in r['decisions'] if not any(n['doc'] == d for n in r['named_in'])]
        if extra:
            dec = (dec + '; ' if dec else '') + ', '.join(extra) + ' (routes.md)'
        docs = ', '.join(n['doc'] for n in r['named_in'] if not n['doc'][:1].isdigit())
        L.append(f"| `{r['page']}` | {', '.join(r['kinds']) or 'page'} | {dec or '**none**'} | {docs} | {len(r['paths']) or ''} |")
    if missing:
        L += ['', '## Named by no decision', '', 'Each needs a governing decision (an existing one that should name it, or a new one). The review drafts which.', '']
        L += [f"- `{r['page']}`" + (f" ({r['purpose']})" if r['purpose'] else '') for r in missing]
    open(os.path.join(DOCS, 'site-audit', 'page-map.md'), 'w', encoding='utf-8').write('\n'.join(L) + '\n')
    return len(rows), len(missing)


if __name__ == '__main__':
    n, m = write(build())
    print(f'Page map: {n} pages, {m} named by no decision.')
