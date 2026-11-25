# no-undo's FAQ edit, on the newest live faq.md just before zipping: Acknowledge has no Undo; who saw it, when, is its state line.
import sys
p=sys.argv[1]; s=open(p).read()
old="**Acknowledge** (and **Undo**), how many times"
assert s.count(old)==1, 'faq anchor'
s=s.replace(old,"**Acknowledge** (once seen, the row says \"Seen by <name>, <time>\": a record, so there's no Undo; a trip stays open while it holds), how many times")
open(p,'w').write(s)
