#!/usr/bin/env python3
"""Get Local R2, the FAQ (decisions/0057): what changed, from a visitor's view. Questions keep their wording (anchors);
answers updated, four new questions. Replayable: build-r2g.py <root> with app/ui/faq.md."""
import sys, os, re
R = sys.argv[1]; p = os.path.join(R, 'app/ui/faq.md'); s = open(p, encoding='utf-8').read()
def sub(q, new_answer):
    global s
    m = re.search(r'(### ' + re.escape(q) + r'\n)(.*?)(\n\n### |\n\n## )', s, re.S)
    assert m, q; s = s[:m.start(2)] + new_answer + s[m.end(2):]
def after(q, block):
    global s
    m = re.search(r'### ' + re.escape(q) + r'\n.*?(\n\n(?=### |## ))', s, re.S)
    assert m, q; assert block.split('\n')[0] not in s, block[:40]
    s = s[:m.end(1)] + block + '\n\n' + s[m.end(1):]

sub("How do I find a business in Get Local?",
 "Get Local opens with one bar, as the home page has: **what you're looking for** (a business's name, the kind of business, or what you need: \"pizza\", \"oil change\", \"Head Start\"), **where** (a town or ZIP, or tap the target for where you are), and the search button. As you type, it offers what the words could mean, the kind of business first (\"Barber shops · near St. Johns\", or \"· near me\"), then the businesses themselves; the list also follows what you type once you pause. Under the bar, one line says what you're seeing and lets you change it: \"**24 businesses** within **any distance** of **St. Johns**\". Tap **businesses** to pick a kind, **any distance** to choose how far (2, 5, 10, 25 or 50 miles, or any), or the place for the towns in these results or somewhere else; a kind or words you searched show there with × to clear them. On the right of that line: **Filters** (Open now, Has a phone, Has a website, ★ Favorites; one that's on shows in the line with × to turn it off), **Arrange** (see \"How do I arrange Get Local's results?\") and **Sort**. Each business is numbered, so you can match it to its pin on the map.")
sub("How do I switch between the list and the map?",
 "Use **List**, **Table** or **Map** at the top right of Get Local (a phone has List and Map). **Map** opens full screen with every result as a pin; **Exit map** brings you back to the list, which keeps any filters you changed on the map. **Table** shows the same results as rows: tap a column's name to sort it (A to Z, Z to A, or nearest first), search it, or untick values to hide them (\"Show Restaurants (6)\"); the column menus work on the results on the page, and **Clear the column filters** brings every row back. On a computer you may see the list **beside the map** instead: the numbered businesses on the left, their numbered pins on the right, a dashed ring for how far you chose, and **Search this area** once you move the map. We're trying both layouts to see which helps people more (we count which one a device has and what's tapped there, never who); **Arrange** lets you pick the one you like.")
sub("How do I filter the directory?",
 "In Get Local, the line over the list is the filter: tap its kind, distance or place to change them, × to clear one. **Filters** beside it has **Open now**, **Has a phone**, **Has a website** and **★ Favorites**: tick one and it shows in the line, with × to turn it off. Elsewhere, tap the search at the top: at the foot of **Quick Search** are the same filters, with **Near me**.")
sub("Can I comment on a business, a place or an experience?",
 "Yes. Most pages have **Comments** at the bottom: share a tip, or say what it was like. Signed in, the box is right there, with a few ideas to start from on a business (best thing to order, parking, good for kids, accessible?): tap one and it starts your comment for you. Not signed in, tapping the box offers to sign in or make an account. You need to be 18 or older. Comments are public and show your name. You can remove your own, and report one that breaks the rules. Stars and ratings aren't used: say what you think in words.")
after("How do I filter the directory?",
 "### How do I arrange Get Local's results?\n"
 "Tap **Arrange** beside Filters. **Results per page**: 10, 25, 50 or 100. **Show**: businesses closed for good show last, marked Closed; untick it to hide them for you (we never hide them for everyone). **On each card**: which details each business shows and in what order (its kind, distance, hours, address, phone number, website or email); drag them, or use ↑ ↓, × to take one off, **+ Add a detail** to bring one back. The first three share the top line. **On a computer**: the list beside the map, or List, Table or Map. It's remembered on this device, and **Put it all back as it was** undoes it all.\n\n"
 "### What are the buttons on a business's card?\n"
 "**Call** and **Directions** are the round buttons by its name; **⋯** holds **Email**, **Website** and **Peek** (a quick card without leaving the page), each in words, and says so when a business hasn't given an email or a website yet. The link button on the right links the business to your account. The small number beside the name is how sure we are of the listing's details: tap ⓘ to see why.\n\n"
 "### Why doesn't a business show Directions?\n"
 "Its only address is a mailbox (a PO Box or a private mailbox), so there's nowhere to drive to: the card says **Mailing address only**, and its pin on the map marks its town, not a street. If it's your business, add a street address in your listing's editor and Directions appears. Places that keep their address private for the safety of the people they help, like shelters, never show one: call them instead.")
after("How do I change where I'm looking?",
 "### Why is the search at the top only a ⌕ on Get Local?\n"
 "Get Local has its own search bar, so the one at the top folds to its ⌕ to keep one search on the page. Tap it (or press Ctrl K, ⌘K on a Mac) to search the whole site: places, stories and groups as well as businesses. On other pages the top search shows in full, with the place you're searching in beside it (tap it to change where).")
open(p, 'w', encoding='utf-8').write(s)
print('built')
