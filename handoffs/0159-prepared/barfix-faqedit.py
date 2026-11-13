import sys
p=sys.argv[1]; s=open(p).read()
k="**Releases ▾** (what's on staging now, what's waiting, what's live and at which step)"
assert s.count(k)==1
s=s.replace(k,"**Releases ▾** (what's on staging now, what's waiting, what's live and at which step; the bar shows what's on staging now, or else the newest release, and one you pick there stays for that browser tab)",1)
open(p,'w').write(s)
