import sys
p=sys.argv[1]; s=open(p).read()
k="▾ → **Versions** lists them, with who and when, and **Roll back** puts any earlier one live again, so nothing is lost."
assert s.count(k)==1
s=s.replace(k,k+" ▾ → **Publish later** picks a day and time instead: what's in the panel then goes live by itself as the next version, at the first visit to the page after that time, and your draft stays yours to keep working on (**Call it off** stops it). Two outlined places on the home page are on every page of the site: **Every page, under the header** and **Every page, above the footer**. What's published there shows on all our browsing pages (not on the admin, sign-in or payment pages), for notices like holiday hours or an event; the header's and footer's own menus and links stay as they are.",1)
c="Coming next: the engage bar and a form as parts of the site, the header and footer, and publishing on a schedule."
assert c in s
s=s.replace(c,"Coming next: the engage bar and a form as parts of the site.",1)
open(p,'w').write(s)
