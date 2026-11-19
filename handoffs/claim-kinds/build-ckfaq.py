#!/usr/bin/env python3
"""claim-kinds' FAQ (decisions/0057): "How do I claim my business?" updated; a new question for parks, agencies, nonprofits
and churches. No question reworded."""
import sys, os, re
p = os.path.join(sys.argv[1], 'app/ui/faq.md'); s = open(p, encoding='utf-8').read()
a = "Open your listing and tap **Claim this business**, at the top right of its photo."
assert s.count(a) == 1
s = s.replace(a, "Open your listing and tap **Claim it**, at the top right of its photo (on a park or a public office it reads **Staff? Claim it**; on a nonprofit or a church, **Run it? Claim it**).")
a2 = "3. **Choose a plan.** Free, or a paid plan (see below).\n"
assert s.count(a2) == 1
s = s.replace(a2, "3. **Choose a plan.** Free, or a paid plan (see below). Agencies and nonprofits stay on Free: plans for them are coming.\n")
block = ("### Who can claim a park, a public office, a nonprofit or a church?\n"
 "The people who run it. Each listing knows what kind of place it is, and its claim page asks the right things.\n"
 "- **A park or a public office** is claimed by the agency's staff: a site or park manager, agency staff, or whoever handles its public information. The page shows which agency runs it (for example, State of Arizona › Arizona State Parks & Trails). If that's wrong, or it doesn't know yet, you can say which agency it is and our team checks it. The quickest way to be confirmed is a code sent to your email at the agency's own website. A claim covers the place you look after; claiming for the whole agency, every place it runs, in one go is coming.\n"
 "- **A nonprofit or a church** is claimed by its director, pastor or leader, its staff, a board member or a volunteer.\n"
 "- **A place on a tribal nation's land** that the nation runs: the nation decides who speaks for it. Until the nation's own tools are ready, your request comes to our team, who check with the nation first.\n\n"
 "Agencies and nonprofits keep everything a claimed listing gets on Free; plans made for them are coming.")
m = re.search(r'### How do I claim my business\?\n.*?\n\n(?=### )', s, re.S); assert m and block.split('\n')[0] not in s
s = s[:m.end()] + block + '\n\n' + s[m.end():]
open(p, 'w', encoding='utf-8').write(s); print('built')
