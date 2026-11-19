#!/usr/bin/env python3
"""The place page's FAQ (decisions/0057): one answer updated, two questions added; no question reworded."""
import sys, os, re
p = os.path.join(sys.argv[1], 'app/ui/faq.md'); s = open(p, encoding='utf-8').read()
q = "### Can I see a place's photo bigger?\n"
m = re.search(re.escape(q) + r'(.*?)(\n\n### )', s, re.S); assert m
new = ("Yes. Tap the photo at the top of a place, a listing or a profile to open it full screen, with its credit. If there are several, swipe or use the arrows to move between them. "
       "The top of the photo says what it's of, with a link to its page, **See on the map** and **Copy link**; on Let's Explore, where the photos show different places, each one names its own. "
       "Under the photo are its views, likes and comments (zeros included): like it or go to its comments from there, and on its own page share it too. Tap outside the photo, or ×, to close it.")
s = s[:m.start(1)] + new + s[m.end(1):]
block = ("### What's under ⋯ at the foot of a page's photo?\n"
 "The photo's corner keeps its numbers: views, likes and comments (zeros included). Everything else is under **⋯**, in words: **Share** and **QR code**, **Share a photo or video** (or **Photos and video** for the page's owner), **Save to Address Book** on a business, then **See on the map**, **Plan a trip here**, **Add to a trip** and **Report** where the page has them. "
 "What the page already shows beside its card (Call, Directions, the map, Save contact, its QR code, Suggest an edit) isn't repeated there. Something a page can't offer shows greyed, with the reason. "
 "Close it with ×, Esc or a tap outside it.\n\n"
 "### How do I plan a trip to the place I'm looking at?\n"
 "Tap ⋯ on the page's photo, then **Plan a trip here**. It shows the ready-made journeys for that area first (a day there, the outdoors, history, food: each timed stop by stop), "
 "then **Open the Trip Planner** already set to the place. Saving a trip of your own and adding places to it (**Add to a trip**) are coming.")
m2 = re.search(re.escape(q) + r'.*?\n\n(?=### |## )', s, re.S); assert m2 and block.split('\n')[0] not in s
s = s[:m2.end()] + block + '\n\n' + s[m2.end():]
open(p, 'w', encoding='utf-8').write(s); print('built')
