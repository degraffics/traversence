#!/usr/bin/env python3
"""The admin kit release (design/css-theme-brand §3: "guarantee a minimum 4.5:1 contrast"; the queue in
handoffs/admin-design.md): metadata text in the admin's Shortcuts, the dashboard and Pulse onto --tv-meta (4.5:1 on every
surface) from #8A7355 (4.36:1 on the lightest cream, 3.93:1 on the panels); and the way-back bar (js/tv-trail.js) no
wider than the page on phones (392px at 390: it pulled out by the desktop padding)."""
import os, re, shutil
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def live(rel): return open(M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1), encoding='utf-8').read()
def put(rel, s):
    p = os.path.join(O, rel); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
def rep(s, a, b, n=1):
    assert s.count(a) == n, (a[:90], s.count(a)); return s.replace(a, b)
META = 'color:var(--tv-meta,#5E4F3C)'   # the token's own value (theme-tokens.php: #2A1D14 35% into #7A6A52) as its fallback

# Each text use of #8A7355, by the rule it sits in. Glyphs (▾, ›, the trend's =), icons and backgrounds keep theirs: a
# non-text mark needs 3:1, which #8A7355 has.
TEXT = {
    'app/ui/admin-hub.php': ['.hb-crumbs{', '.hb-row small{', '.hb-dl dt{', '.ab-g b{'],
    'app/ui/admin-dashboard.php': ['.ad-k{', '.ad-m small{', 'style="font-weight:400;color:#8A7355">· show all'],
    'app/ui/pulse-ui.php': ['.pq-n{', '.pq-t small{', '.ps-hd{', '.ps-sub small{', '.pb-case-hd small{', '.pb-tl li.order b{',
                            '.pb-tl small{', '.pb-next .lead{', '.pst small{'],
}
done = []
for f, keys in TEXT.items():
    s = live(f)
    for k in keys:
        # the one place this rule (or attribute) opens with #8A7355 in it: exactly one, or the build stops
        hits = [m.start() for m in re.finditer(re.escape(k), s)
                if 'color:#8A7355' in (s[m.start():m.start() + len(k)] if k.startswith('style=') else s[m.start():s.find('}', m.start())])]
        assert len(hits) == 1, (f, k, len(hits))
        i = hits[0]; j = s.find('color:#8A7355', i)
        s = s[:j] + META + s[j + len('color:#8A7355'):]
        done.append((f, k))
    put(f, s)

# pulse-ui.php: the rules found by their position (no unique opening): the step dots' labels and the case's small heads
f = 'app/ui/pulse-ui.php'; s = open(os.path.join(O, f), encoding='utf-8').read()
for k in ['.pb-play b::before{content:counter(pp) ". ";color:#8A7355', 'text-align:center;font-size:.72rem;font-weight:700;color:#8A7355', 'font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#8A7355']:
    s = rep(s, k, k.replace('color:#8A7355', META)); done.append((f, k[:40]))
put(f, s)

# ---- the way-back bar on phones: pulled out by the page's own padding (--tv-pad below 768px), never wider than it ----
f = 'website/js/tv-trail.js'; s = live(f)
s = rep(s, "+ '.ta-work>.tv-trl{margin:-1.1rem calc(-1 * clamp(1rem,3vw,2.5rem)) 1.1rem}'",
           "+ '.ta-work>.tv-trl{margin:-1.1rem calc(-1 * var(--tv-pad,clamp(1rem,3vw,2.5rem))) 1.1rem}'   /* the page's own side padding (.85rem on phones): at 390 the desktop clamp made it 392px wide (2026-11-20) */")
put(f, s)
for d in done: print('  meta:', d[0], d[1])
print('built', sum(len(fs) for _, _, fs in os.walk(O)), 'files')
