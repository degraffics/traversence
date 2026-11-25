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
    return pages, {'pages': expected, 'also_changes': also, 'canaries': []}

def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--mirror', required=True)
    ap.add_argument('--zip', help='a release zip: write its pages and expected list (beforeafter.mjs\'s inputs)')
    ap.add_argument('--out-pages', default='ba-pages.json')
    ap.add_argument('--out-expected', default='ba-expected.json')
    ap.add_argument('files', nargs='*')
    a = ap.parse_args()
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
