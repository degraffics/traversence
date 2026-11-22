#!/usr/bin/env python3
"""3f's leftovers (design/css-theme-brand.md §4 Release 3; admin-design.md "3f sign-in, long text, standalone pages,
leftovers"): sign-in, long text, standalone pages, member pages 3d didn't reach and the business portal's shell onto the
tokens, colours only, layouts kept. 3e's method: recolor.py (3d's) with 3e's three additions; each hex keeps its old value
as the fallback; what it can't place stays and is listed. The order pages (order.php, wo.php, user/orders.php) and vendor.php (its work orders) wait for the orders hold (Jason, 2026-11-21).
Reads the live mirror, so re-running it rebuilds on the newest sync."""
import os, shutil, sys
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import recolor as R
M = '/home/user/traverse-3way-sync'; S = os.path.dirname(os.path.abspath(__file__)); O = os.path.join(S, 'out')
shutil.rmtree(O, ignore_errors=True)
def mpath(rel): return M + '/' + rel.replace('website/', 'sync-storage/', 1).replace('app/', 'sync-app/', 1)
R.TEXT.update({'FFFBF2': 'on-umber', '7A6348': 'meta'}); R.SURF.update({'F7F1E3': 'card-a'}); R.SURF.update({'1E140C': 'void'}); R.LINE.update({'B91C1C': 'fail'})
FILES = '''login register verify-email reset-password two-step faq pages/view guide/how guide/resources plans q nexus
verify-listing claim ai/index listing/location listing/businessportal hub/build user/notice user/profile user/accounts
user/market/index user/experiences/index user/journeys/index user/journeys/compose user/trips/trip business/router'''.split()
FILES += ['js/tv-password.js']
FIX = {
  # "Create account": white on #C75A28 read 4.27:1; the site's primary button colour (--tv-btn, #181's standard; 7.1:1)
  'website/login.php': [('class="au-go w-full bg-[#C75A28] text-white', 'class="au-go w-full bg-[color:var(--tv-btn,#92400E)] text-white', ''),
                        ('hover:bg-[#A8481F]', 'hover:bg-[color:var(--tv-btn-hover,#78350F)]', '')],
  # a stop that's off: white "–" on #CBB98D read 1.93:1; meta on the section colour (6.4:1)
  'website/user/trips/trip.php': [('.tp-s.off .tp-n{background:#CBB98D}', '.tp-s.off .tp-n{background:var(--tv-sec,#EDE6D5);color:var(--tv-meta,#5E4F3C)}', '')],
}   # the password checklist every password box shares (CLAUDE.md's password rule): its words were 3.62:1
FILES += ['@app/business/portal.php']   # the business overview card (.bo-k, .bo-s read 3.93:1)
for n in FILES:
    f = n[1:] if n.startswith('@') else 'website/' + n + ('' if n.endswith('.js') else '.php'); a = open(mpath(f), encoding='utf-8').read()
    r = {'mapped': 0, 'left': {}}; s = R.recolor(a, r)
    for x, y, why in FIX.get(f, []):   # contrast failures fixed only with existing tokens (the session manager's review)
        assert s.count(x) == 1, (f, x); s = s.replace(x, y)
    if s != a:
        p = os.path.join(O, f); os.makedirs(os.path.dirname(p), exist_ok=True); open(p, 'w', encoding='utf-8').write(s)
    print('%-38s mapped %3d  left %s' % (f, r['mapped'], dict(sorted(r['left'].items(), key=lambda x: -x[1]))))
