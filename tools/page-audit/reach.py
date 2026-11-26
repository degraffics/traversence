#!/usr/bin/env python3
"""The pages a changed file reaches (Piece 3, P3-1; handoffs/piece3-check.md §1.2 and gap G3): for each file a release
changes, the website/ pages that load it, directly or through the files between, on the live mirror.

    python3 tools/page-audit/reach.py --mirror <traverse-3way-sync checkout> app/lib/Orders.php website/js/tv-ask.js …

A file is reached by another when the other includes it by name (require, include, a script or stylesheet tag), names its
class (Class::, new Class, extends), or calls a function or global it defines (checkout.py's reach(): a class, or a
script's window globals). Walked back to the pages: website/*.php that aren't endpoints (api/, includes, _notes…), as
addresses the way Releases::pagesOf() writes them. The admin's sections are all drawn by website/admin/router.php, so a
file that reaches the router reaches "/admin/" (the check then looks at the sections its release names). Prints JSON:
{"pages": [nearest first], "expected": [within NEAR steps], "steps": {page: n}, "by_file": {...}}. Read only.
"""
import argparse, json, os, re, sys
from collections import defaultdict, deque

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from checkout import site_files, reach as defines  # noqa: E402  the mirror's files, and what each defines (shared)

USE = re.compile(r'([A-Za-z_$][\w$]*)\s*(?:\(|::|\.[A-Za-z_$])|\b(?:new|extends|instanceof|implements)\s+([A-Za-z_]\w*)')   # a name used: called, Class::, global.x, new/extends
FUNC = re.compile(r'^\s*function\s+([A-Za-z_]\w*)\s*\(', re.M)   # a PHP file's top-level functions (app/ui/*.php's tv_… parts)
COMMENTS = re.compile(r'/\*.*?\*/|(?<![:\'"\w])//[^\n]*|^\s*#[^\n]*', re.S | re.M)   # a name in a comment isn't a use
LOAD = re.compile(r'(?:require|include)(?:_once)?\b[^;]*?[\'"]([^\'"]+\.php)[\'"]|<script[^>]+src=[\\]?[\'"]([^\'"?]+\.js)|href=[\\]?[\'"]([^\'"?]+\.css)')   # what a file loads (never a plain link to a page)
tail2 = lambda p: '/'.join(p.split('/')[-2:])
NOT_PAGE = re.compile(r'^website/(?:.*/)?(?:api|includes|_[a-z]+)/|^website/.*\.(?!php$)[a-z]+$|(?:^|/)api\.php$')   # endpoints (api/, admin/api.php) aren't pages

def page_url(p):
    if not p.startswith('website/') or not p.endswith('.php') or NOT_PAGE.search(p):
        return None
    return '/' + re.sub(r'(^|/)index\.php$', r'\1', p[len('website/'):])

def index(mirror):
    texts = {}
    for p, full in site_files(mirror):
        try:
            texts[p] = open(full, encoding='utf-8', errors='replace').read()
        except Exception:
            continue
    for root in ('website',):   # stylesheets are loaded by name too
        base = os.path.join(mirror, 'sync-storage')
        for r, ds, fs in os.walk(base):
            for f in fs:
                if f.endswith('.css'):
                    p = 'website/' + os.path.relpath(os.path.join(r, f), base).replace(os.sep, '/')
                    texts.setdefault(p, '')
    code = {p: COMMENTS.sub(' ', t) for p, t in texts.items()}
    words = {p: {a or b for a, b in USE.findall(c)} for p, c in code.items()}
    def resolve(p, php, url):
        if php:   # __DIR__ . '/../lib/X.php', or a path relative to the file's folder
            r = os.path.normpath(os.path.join(os.path.dirname(p), php.lstrip('/') if not php.startswith('/') else php[1:]))
            return r.replace(os.sep, '/')
        return 'website/' + url.lstrip('/')   # a script's or stylesheet's address on the site
    loads = {p: {resolve(p, x, y or z) for x, y, z in LOAD.findall(c)} for p, c in code.items()}
    names = {}   # file -> the names others reach it by
    for p, t in texts.items():
        lang, owner = defines(p, t)
        n = set()
        if lang == 'php':
            if owner:
                n.add(owner)
            else:
                n |= set(FUNC.findall(t))
        elif lang == 'js' and owner:
            n |= set(owner)
        names[p] = {x for x in n if len(x) >= 4} if not p.startswith('website/') or p.endswith('.js') else ({owner} if lang == 'php' and owner else set())
        # short names (e, h, get) collide across files; a page's own helper functions are reached only by loading the page
    used_by = defaultdict(set)   # file -> files that use it
    for p in texts:
        w, ld = words[p], loads[p]
        for q in texts:
            if q == p:
                continue
            if (names[q] and names[q] & w) or q in ld:
                used_by[q].add(p)
    return texts, used_by

def pages_of(path, used_by):
    """{page: steps from the changed file} (a page's own file is 0 steps; through one file between, 2)."""
    seen, q, out = {path: 0}, deque([path]), {}
    while q:
        f = q.popleft()
        u = page_url(f)
        if u and u not in out:
            out[u] = seen[f]
        for g in used_by.get(f, ()):
            if g not in seen:
                seen[g] = seen[f] + 1
                q.append(g)
    return out

NEAR = 2   # a page this close is expected to change; farther ones are looked at, and a change there is drift (piece3-check.md §2.4)

CAP = 40   # pages a run looks at (the minutes' budget, DB4)

def for_release(mirror, zpath):
    """A release zip's pages and expected list for beforeafter.mjs: its own pages (Releases::pagesOf's rule, with / and
    /faq.php), the pages its files reach (nearest first), and every address its release.json's changes name; an admin
    section is named by its address there (the router draws them all). Expected: its own pages, the near reach, the
    addresses it names, and its "also_changes" (with their reasons)."""
    import zipfile
    z = zipfile.ZipFile(zpath)
    files = [n for n in z.namelist() if not n.endswith('/') and n != 'release.json']
    rel = json.loads(z.read('release.json')) if 'release.json' in z.namelist() else {}
    own = ['/', '/faq.php'] + [u for u in (page_url(f) for f in files) if u]
    named = []
    for c in rel.get('changes') or []:
        w = str(c.get('where') or '')
        m = re.match(r'https://(?:traversence\.com|staging\.trvgo\.us)(/[^\s#]*)', w)
        if m:
            named.append(m.group(1))
    _, used_by = index(mirror)
    best = {}
    for f in files:
        for u, n in pages_of(f, used_by).items():
            best[u] = min(n, best.get(u, n))
    reach_order = [u for u in sorted(best, key=lambda u: (best[u], u)) if u not in ('/admin/router.php', '/business/router.php')]
    pages = list(dict.fromkeys(own + named + reach_order))[:CAP]
    also = [x for x in rel.get('also_changes') or [] if isinstance(x, dict) and x.get('page') and x.get('why')]
    expected = list(dict.fromkeys(own + named + [u for u in reach_order if best[u] <= NEAR]))
    return pages, {'pages': expected, 'also_changes': also, 'canaries': [], 'files': files}

# ---- the fence (P3-6; decisions/0179's note "agents work on the backend first", Jason, 2026-11-26) -------------------------
# Backend: every /admin/ page and its parts, the site's internal code and the workers, where a change reaches no public page
# ("Admin does count for backend"). Front end: any page a visitor, member or business sees. A migration that only adds
# (CREATE TABLE IF NOT EXISTS, a guarded ADD) is backend; one that writes or changes rows or columns isn't proved backend.
WRITES = re.compile(r'\b(UPDATE|DELETE|DROP|MODIFY|RENAME|TRUNCATE|CHANGE|INSERT|REPLACE)\b', re.I)

def public(urls):
    return sorted(u for u in urls if not u.startswith('/admin'))

def fence_one(path, used_by, texts, new_text=None):
    """[] when the file stays in the backend, else the public pages it reaches (or why it isn't proved backend)."""
    if path.startswith(('website/admin/', 'app/admin/', 'workers/')):
        return []
    if path.startswith('app/migrations/'):
        sql = re.sub(r'--[^\n]*|/\*.*?\*/', ' ', new_text or texts.get(path, ''), flags=re.S)
        sql = re.sub(r'\bON\s+(UPDATE|DELETE)\b|\bDUPLICATE\s+KEY\s+UPDATE\b', ' ', sql, flags=re.I)
        m = WRITES.search(sql)
        return ['(a migration that ' + m.group(1).upper() + 's: rows or columns a public page may read)'] if m else []
    if path.startswith('trvgo/'):
        return ['(trvgo.us: public by definition)']
    if path.startswith('website/') and not path.endswith(('.js', '.css')):
        u = page_url(path)
        return [u or '/' + path[len('website/'):]]   # a public page or endpoint itself
    if path not in texts:   # a new file: it reaches pages only through its callers, which the release carries or the mirror has
        return []
    return public(pages_of(path, used_by))

def fence(mirror, files, new_texts=None):
    """{file: [public pages or reasons]} for the files that leave the backend (empty: all backend)."""
    texts, used_by = index(mirror)
    out = {}
    for f in files:
        r = fence_one(f, used_by, texts, (new_texts or {}).get(f))
        if r:
            out[f] = r[:8]
    return out

def fence_map(mirror):
    """The live mirror's backend-only files (for the site's check at the brief, carried in gauges.json as "fence")."""
    texts, used_by = index(mirror)
    back = sorted(p for p in texts if (p.startswith('app/') or p.startswith('website/')) and not fence_one(p, used_by, texts))
    return back

def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--mirror', required=True)
    ap.add_argument('--zip', help='a release zip: write its pages and expected list (beforeafter.mjs\'s inputs)')
    ap.add_argument('--out-pages', default='ba-pages.json')
    ap.add_argument('--out-expected', default='ba-expected.json')
    ap.add_argument('--fence-zip', help='P3-6: does a release zip stay in the backend? prints what leaves it; exit 1 if anything does')
    ap.add_argument('--fence-map', help='P3-6: write the mirror\'s backend-only files here (site-audit/results/fence.json)')
    ap.add_argument('files', nargs='*')
    a = ap.parse_args()
    if a.fence_map:
        import datetime
        back = fence_map(a.mirror)
        json.dump({'at': datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'), 'backend': back}, open(a.fence_map, 'w'), indent=0)
        print(f'{len(back)} backend-only files')
        return
    if a.fence_zip:
        import zipfile
        z = zipfile.ZipFile(a.fence_zip)
        names = [n for n in z.namelist() if not n.endswith('/') and n != 'release.json']
        out = fence(a.mirror, names, {n: z.read(n).decode('utf-8', 'replace') for n in names if n.endswith('.sql')})
        print(json.dumps({'backend': not out, 'front': out}, indent=1))
        sys.exit(1 if out else 0)
    if a.zip:
        pages, exp = for_release(a.mirror, a.zip)
        json.dump(pages, open(a.out_pages, 'w'), indent=1)
        json.dump(exp, open(a.out_expected, 'w'), indent=1)
        print(f'{len(pages)} pages to look at, {len(exp["pages"])} expected to change')
        return
    _, used_by = index(a.mirror)
    by = {f: pages_of(f, used_by) for f in a.files}
    best = {}
    for d in by.values():
        for u, n in d.items():
            best[u] = min(n, best.get(u, n))
    order = sorted(best, key=lambda u: (best[u], u))
    print(json.dumps({'pages': order, 'expected': [u for u in order if best[u] <= NEAR], 'steps': best,
                      'by_file': {f: sorted(d, key=lambda u: (d[u], u)) for f, d in by.items()}}, indent=1))

if __name__ == '__main__':
    main()
