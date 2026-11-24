# task-scope's FAQ edit, on the newest live faq.md just before zipping: the Work queue's question gains a task's own page.
import sys
p=sys.argv[1]; s=open(p).read()
old="Done and withdrawn tasks stay a week under **Done and withdrawn this week**."
assert s.count(old)==1, 'faq anchor'
new=("Press a task's name to open its page beside the list (2026-11-24): its **Scope** (goal, in scope, out of scope, done when), its **Priority** "
"(1 first, 2 next, 3 when there's room; open and late tasks are listed by it) and its **Steps**, each saying what to see, what to do and how it's confirmed, "
"with **Confirm it's done**. Every field has **✎** to edit it, the old words kept in the task's **History**; a step confirmed by mistake is corrected with ✎ too. "
"The list shows each task's priority, its goal and how many of its steps are confirmed. " + old)
s=s.replace(old,new); open(p,'w').write(s)
