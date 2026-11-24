# task-adrs's FAQ edit, on the newest live faq.md just before zipping (after task-scope's is live).
import sys
p=sys.argv[1]; s=open(p).read()
old="The list shows each task's priority, its goal and how many of its steps are confirmed. "
assert s.count(old)==1, 'faq anchor (task-scope live?)'
new=("Under **Decisions**, the task names the decision records it rests on, each with its number and title; press one to read it on "
"System Configuration → Decisions beside the board. **Attach a decision** finds one by its number or a word from its title, in the same list "
"that page has; **✎** detaches one, kept in the task's History. " + old.replace("and how many of its steps are confirmed. ", "how many of its steps are confirmed and its decisions. "))
s=s.replace(old,new); open(p,'w').write(s)
