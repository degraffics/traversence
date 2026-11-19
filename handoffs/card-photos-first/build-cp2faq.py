#!/usr/bin/env python3
"""The FAQ for the follow-up: one sentence on the stock-photo answer (decisions/0057); no question changes."""
import sys, os
p = os.path.join(sys.argv[1], 'app/ui/faq.md'); s = open(p, encoding='utf-8').read()
a = "only when there's none do we show a **stock photo** instead:"
assert s.count(a) == 1
s = s.replace(a, "the first time someone opens one still without it, we look for its photo first, within the hour; only when there's none do we show a **stock photo** instead:")
open(p, 'w', encoding='utf-8').write(s); print('built')
