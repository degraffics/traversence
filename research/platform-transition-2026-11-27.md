# Moving our business onto the platform: the transition plan (approved 2026-11-27)

Jason, 2026-11-27: "I want us moving away from the claude interface so we need to ensure our platform is where we conduct
our business and conversations. This will need to have a transition plan." Approved the same day: "Yes, the transition
plan; and shouldn't your team work out the design as a part of handling the plan through implementation?"
Reviewed by the session manager. Tier 5 (decisions/0180).

## Why
The Work queue (System Control Board → Development, https://traversence.com/admin/config/control?tab=development) was
seeded once (task-seed-3, 2026-11-25). Since then nothing writes rulings or plans into it, so 0181, the launch plan, the
Marketplace's four sections, Native-made, the magazine model and Brief D lived only in docs and chats.

## The steps
0. **task-seed-4**: every open item since 2026-11-25 as a task with its decision and doc. Data only, in the daily batch
   (0179); creates nothing that already exists and names its ids (CLAUDE.md, #284); its rows checked on live after publish.
   Until step 1's import is live, the session manager batches new rulings into one seed release a week.
1. **The thread** (`handoffs/work-queue-phase2.md` release 1: posts, system lines from History, no AI), with
   `releases/task-notes.json` (a session's "built / needs your answer" on its task) and `releases/task-rulings.json`
   (the session manager appends rulings; the hourly path makes each a task with its decision link).
2. **The Executive channel** (decided 2026-11-26, 0180's notes): Work queue "+ Add" To: the CEO; answers in its thread.
   Decision Briefs become tasks with Approve and Needs changes; P2-5's emailed answers stay the off-site reply. Up to an
   hour each way at first.
3. **Cutover** about 2026-12-15, after a parallel week, before the 2026-12-22 freeze (0181): the Claude chat becomes the
   fallback. Ask Claude (`task_assist`, release 2) later, inside the $40 cap.

## Design
The team works out the design as part of carrying out the plan (Jason, 2026-11-27). It's **Tier 3** (0180 §1, "non-protected
workflow redesigns"; Jason: "this doesn't seem like a t5 concern"), in the Project Manager's lane, through the four
stages (handoffs/piece2/jason-spec.md §8): Planning with the System Architect (ADR check), the UI/UX Designers, the
Content Writers and the Cognitive Load & Simplicity Guardian (simplicity veto); Building by Piece 2; Implementation on
staging with the before-and-after check; Confirmation by the advocates (including Staff Views, A12) and the Design
reviewer's fidelity check. Tier 4 only if the Prior Ruling Check flags a high-risk territory (the thread's staff
messages may touch §4's privacy and identity). Jason sees it as a line in the daily batch (0179), not as a question.

## Order, cost, gaps
- After the pipeline release and R2, alongside A3 (Piece 2's lane); the seed fits any gap. Launch work wins against
  0181's gates.
- $0 new spend: hourly paths and existing tables.
- Gaps (suggestions only): Gemini stays public-only until Jason rules on 0164 §0.8; the hourly lag could be shortened.

## Agents' inbox: role queues, not mailboxes (Jason, 2026-11-27: "Yes to role queues; mail only, OneDrive stays")
- No email accounts for agents. The inbox system is the role queues on the platform (`handoffs/piece2/jason-spec.md` §7):
  each department and role has its own queue view on the Work queue, with counts and SLA. An alarm or report the site
  emails Jason also lands as a row in the owning role's queue, so agents read it there. It joins step 1 (Tier 3, the
  PM's lane).
- degraffics.com is out of system mail: email to Jason goes through the site's own email (notify-founder) and push only,
  with no Microsoft 365 mail fallback and no agent reading his degraffics.com inbox. Mail he receives stays in his
  mailboxes (0167 §2a, My inbox in Pulse).
- The OneDrive working copy and the 3-way sync's Microsoft 365 use stay as they are.
