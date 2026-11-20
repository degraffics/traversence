#!/usr/bin/env python3
"""T5 (design/table-standard.md §Order 3): the shared parts onto the table standard. Part 1, the cost tab: its price tiers
table (cost-tab.php draws it, js/tv-cost.js redraws it as you type) takes the standard through js/tv-table.js
(data-tv-table + tv_table_kit(), T4's way for tables the server writes); the current run stays marked; its labels'
#8A7355 onto --tv-meta (css-theme-brand §3, 4.5:1). Part 2, the vault's list (js/tv-vault.js, app/ui/vault.php;
decisions/0156): it keeps its own engine (its list is opened in the browser, so it can't page on the server: T2's
precedent, "Pulse keeps its own engine and takes the standard's look") and takes the standard's look: headings small in
the meta colour (§4), 44px rows, hairlines and the hover tint (§5), the header on the section colour and rows on the card
colour (§13), the list's colours on the tokens (css-theme-brand §3); dates through TvTime with their zone (§5; CLAUDE.md
"Times where they happened"). Reads the live mirror, so re-running it rebuilds on the newest sync."""
import os, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)
META = 'color:var(--tv-meta,#5E4F3C)'

# ---- the cost tab ----
f = 'app/ui/cost-tab.php'; s = live(f)
s = rep(s, "require_once __DIR__ . '/../lib/Costing.php';",
           "require_once __DIR__ . '/../lib/Costing.php';\nrequire_once __DIR__ . '/table-kit.php';   // the table standard for the price tiers (design/table-standard.md, T5)")
s = rep(s, "        $h .= '<table class=\"cx-tt\"><tr>", "        $h .= tv_table_kit() . '<table class=\"cx-tt\" data-tv-table><tr>")
# the tiers' own look gives way to the standard's (table.akt-s); the current run stays marked, on the tokens
old = s[s.index('.cx-tt{width:100%'):s.index('.cx-tt tr.here td{')]
s = s.replace(old, '', 1)
s = rep(s, ".cx-tt tr.here td{background:#EEF6F1;font-weight:700}",
           ".cx-tt tr.here td{background:color-mix(in srgb,var(--tv-sage,#8FBCA8) 22%,var(--tv-card-a,#FFFBF2));font-weight:700}")
s = rep(s, ".cx-row label{display:grid;gap:.1rem;font-size:.74rem;color:#8A7355}", ".cx-row label{display:grid;gap:.1rem;font-size:.74rem;" + META + "}")
s = rep(s, ".cx-l small{display:block;color:#8A7355;", ".cx-l small{display:block;" + META + ";")
put(f, s)
f = 'website/js/tv-cost.js'; s = live(f)
s = rep(s, "h += '<table class=\"cx-tt\"><tr>", "h += '<table class=\"cx-tt\" data-tv-table><tr>")
s = rep(s, "    var redo = function () { out.innerHTML = draw(sheet(recipe(f), run, items, true), price); };",
           "    var redo = function () { out.innerHTML = draw(sheet(recipe(f), run, items, true), price); if (window.TvTable) TvTable.all(out); };   // the tiers on the table standard again (T5)")
put(f, s)
# ---- the vault's list ----
import re
f = 'app/ui/vault.php'; s = live(f)
LIST = ('.tvv-th', '.tvv-tr', '.tvv-sort', '.tvv-thact', '.tvv-colsdd', '.tvv-colsl', '.tvv-c{', '.tvv-site', '.tvv-pager',
        '.tvv-pg', '.tvv-gap', '.tvv-perl', '#tvv-per{', '.tvv-tick', '.tvv-all')
MAP = {'#92400E': 'var(--tv-link,#92400E)', '#3E2B1F': 'var(--tv-ink,#2A1D14)', '#E7D9C4': 'var(--tv-line,#E7D9C4)',
       '#F0E6D6': 'var(--tv-line,#F0E6D6)', '#D6C7B0': 'var(--tv-line-strong,#D6C7B0)', '#CBB98D': 'var(--tv-line-strong,#CBB98D)',
       '#8A7355': 'var(--tv-meta,#5E4F3C)', '#57534E': 'var(--tv-meta,#57534E)', '#78716C': 'var(--tv-meta,#78716C)',
       '#A8A29E': 'var(--tv-taupe,#7A6A52)', '#F7F1E6': 'var(--tv-sec,#EDE6D5)', '#FFFBF2': 'var(--tv-card-a,#FFFBF2)',
       '#FFFDF8': 'color-mix(in srgb,var(--tv-sec,#EDE6D5) 60%,var(--tv-card-a,#F6F1E4))'}
out, n = [], 0
for line in s.split('\n'):
    if line.startswith('#tvv') or line.startswith('  #tvv'):
        rules = re.split(r'(?<=\})', line)
        new = []
        for r in rules:
            sel = r.split('{', 1)[0]
            if any(re.search(re.escape(k.rstrip('{')) + r'(?![\w-])', sel) for k in LIST) and not re.search(r'\.ak-b|\.tvv-flag', sel):
                for h, t in MAP.items():
                    if h in r: n += r.count(h); r = r.replace(h, t)
            new.append(r)
        line = ''.join(new)
    out.append(line)
s = '\n'.join(out); print('  vault list colours onto tokens:', n)
s = rep(s, "#tvv .tvv-sort{border:0;background:none;font:inherit;font-weight:700;color:var(--tv-ink,#2A1D14);",
           "#tvv .tvv-sort{border:0;background:none;font:600 .8rem var(--tv-font-ui);color:var(--tv-meta,#5E4F3C);")   # headings small, in the meta colour (§4)
s = rep(s, "#tvv .tvv-tr:has(.tvv-c0 input:checked){background:var(--tv-card-a,#FFFBF2);",
           "#tvv .tvv-tr:has(.tvv-c0 input:checked){background:color-mix(in srgb,var(--tv-link,#92400E) 7%,var(--tv-card-a,#F6F1E4));")   # a ticked row stays tinted, apart from the rest
s = rep(s, "#tvv .tvv-tr{border-bottom:1px solid var(--tv-line,#F0E6D6);min-height:3rem}",
           "#tvv .tvv-tr{border-bottom:1px solid var(--tv-line,#F0E6D6);min-height:2.75rem;background:var(--tv-card-a,#F6F1E4)}")   # 44px rows on the card colour (§5, §13)
put(f, s)
f = 'website/js/tv-vault.js'; s = live(f)
s = rep(s, "changed: ['Last changed', '7.5rem', true, function (it) { return '<span title=\"' + esc(when(it.updated)) + '\">' + esc(dshort(it.updated)) + '</span>'; }",
           "changed: ['Last changed', '10.5rem', true, function (it) { return esc(when(it.updated)); }")   # with its zone, never cut (table-standard §5)
s = rep(s, "added: ['Added', '7.5rem', false, function (it) { return esc(dshort(it.added)); }",
           "added: ['Added', '10.5rem', false, function (it) { return esc(when(it.added)); }")
# dshort() keeps no caller now; it stays (removing a live function needs Jason's OK, the zip check's --drops)
put(f, s)
print('built', sum(len(fs) for _, _, fs in os.walk(O)), 'files')
