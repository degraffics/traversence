import sys
p=sys.argv[1]; s=open(p).read()
c="Coming next: the engage bar and a form as parts of the site."
assert s.count(c)==1, 'run editor4c-faqedit.py first'
s=s.replace(c,"On our own pages, Add → Parts of the site also has **Likes and comments** (the page's views, likes, Share and QR code, with its public comments or the bar alone) and **Form** (a contact form or a quote request; messages come to Marketplace & Storefronts → Form entries, as with a page's own form). They're offered only on our pages, where there's a page to like and to send the message about.",1)
open(p,'w').write(s)
