#!/usr/bin/env python3
"""The location pop-up ("We need a location to search", Jason's drawing of 2026-11-12, js/location-scope.js): its two footer
words read 2.9:1 on the cream (css-theme-brand §3: 4.5:1 for text). Only those two text colours change, onto existing
tokens: Log in → --tv-link (#92400E, the site's link brown), Not sure? → --tv-meta. The drawing's orange borders, icon,
divider and Go are kept (the "!" is an icon on its disc, 3:1 for non-text). Reads the live mirror."""
import os
M = '/home/user/traverse-3way-sync/sync-storage/js/location-scope.js'; O = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'out/website/js/location-scope.js')
s = open(M, encoding='utf-8').read()
def rep(a, b):
    global s; assert s.count(a) == 1, a; s = s.replace(a, b)
rep(".tvpp-f a,.tvpp-f button{color:#E07A2F;", ".tvpp-f a,.tvpp-f button{color:var(--tv-link,#92400E);")
rep(".tvpp-f .tvpp-ns{color:#A09080;", ".tvpp-f .tvpp-ns{color:var(--tv-meta,#5E4F3C);")
os.makedirs(os.path.dirname(O), exist_ok=True); open(O, 'w', encoding='utf-8').write(s)
