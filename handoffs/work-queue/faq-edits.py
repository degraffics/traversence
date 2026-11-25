# work-queue's FAQ entry, added on the newest live faq.md just before zipping (CLAUDE.md): a new question after
# "How do I fix something a gauge shows?", and Development's line in "Three views first" naming the queue.
import sys
p=sys.argv[1]; s=open(p).read()
anchor="### I'm on the Traversence team. What does \"Paused by the system\" mean?"
assert s.count(anchor)==1, 'faq anchor'
new=("### I'm on the Traversence team. What is the Work queue, and how do I add a task to it?\n"
"It's the first thing on the System Control Board's **Development** page (2026-11-24): everything we've found that needs doing, so nothing is forgotten and no gauge slips. "
"Its gauge reads how many open tasks are on time (a task is **late** when it's a day old and nobody has it); until Jason approves its target (proposed: nothing late, with **Approve the target**) it shows how much of its setup is done, like every gauge. "
"Under it, **Add a task**: choose the gauge or the area it belongs to (or **A new area…**), say what needs doing, why (what it fixes, or who asked) and where it came from (a decision, a hand-off, or whose words and when). "
"The list shows what needs you first, late at the top, the first 10 then **Show all**; **Everything open ▾** filters it (Late, Open, Taken, Being fixed). "
"Each task says where it stands: **Open** (nobody has it yet), **Late**, **Taken** (with whom), **Being fixed** in a named release, and it's **Done** by itself once that release is live, or, for a gauge's **Request a fix**, once its check passes. "
"Its one button is the next step (**Take it**, then **Name its release**); **✎** edits what it says, the old words kept in its **History** (the clock icon); **⋯** has the other moves: **Move to a session…**, **Name its release…** and **Withdraw, with a reason…**. "
"Done and withdrawn tasks stay a week under **Done and withdrawn this week**. Every hour the open tasks reach the session manager with the gauge's requests, and a late one is flagged to it.\n\n")
s=s.replace(anchor,new+anchor)
old="**Development** (the 16 development gauges and Site health's list)"
assert s.count(old)==1, 'faq 2'
s=s.replace(old,"**Development** (the Work queue first, then the 16 development gauges and Site health's list)")
open(p,'w').write(s)
