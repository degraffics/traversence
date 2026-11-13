import sys
EDITS = {
 'w5': ("It takes about two minutes, and you can leave the page: it carries on, and the guide is there when you come back.",
        " If a step fails (a service we use is busy), it's tried again by itself after half an hour, two hours and eight hours; if it still can't be built, our team is told and looks at it."),
 'w6': ("and only those backed by a historic record or two independent sources.",
        None),
 'w9': ("It's kept with the place and reused, never written again.",
        " A Wikipedia summary itself is kept and shown only as far as Wikipedia's terms allow; otherwise the place links to its article."),
 'w11': ("then puts them in our own words and checks each step against its source before the guide shows.",
        " A step that still reads as its source wrote it can't be published unless that source is public domain or openly licensed, and nothing is drafted from a site our list of sources marks as cite only."),
 'w12': ("When a place people need is missing nearby, our crawler looks for it in public data and on official government and school websites first.",
        " What people search for goes to the front of its list, ahead of its general sweep, and searches in each town add up for the area around it."),
}
w, path = sys.argv[1], sys.argv[2]
s = open(path).read()
a, add = EDITS[w]
assert s.count(a) == 1, (w, s.count(a))
if w == 'w6':
    s = s.replace(a, "and only those backed by a historic record or two independent sources (several pages of one newspaper count as one).")
else:
    s = s.replace(a, a + add)
open(path, 'w').write(s)
print('faq ok', w)
