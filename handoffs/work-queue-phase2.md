# Work queue phase 2: a task's thread, where Jason, the system, Claude and Gemini work it until it's done (design, 2026-11-25)

The Control Board build session. **Design only: nothing built.** Route: the Design reviewer → the session manager → Jason once.
Mockup: `work-queue-phase2/mockup.html`, screens `work-queue-phase2/p2-{1280,390}.png`.

## Jason's words (2026-11-24, relayed by the session manager)
1. "I need to be able to scope the tasks from the queue, so scope editor" (**built**: task-scope, #282).
2. "Each task must open and allow me to scope and set priorities and work that needs to be done" (**built**: #282, #283).
3. "I need to have the system, gemini ai, and claude api work on these similar to the cloud chat like function. We work
   until the task is complete." (**this design**)

His decisions: Claude's use in a task counts inside the existing **$40** monthly Claude cap, shown on the task, stopping at
the cap and saying so. **Start the build** goes through the session manager, never opening a session directly. Releases still
need his OK.

## What it builds on (each checked in the live code, Sync 2026-11-25)

| Part | Built on | Source |
|---|---|---|
| The task, its scope, steps, decisions, History | `control_gauge_tasks`, `control_gauge_task_steps`, `control_gauge_task_adrs`, the logbook | #281–#285 |
| One thread per thing, updated in place | the dispatcher's rule (A1); `Notify::send` with `thing`/`thread`; a push tagged by its thing replaces itself | 0176 §1, 0172 §4.1 |
| Claude, its cost and the cap | `AiClient::allow()` (cap, switch-offs, per-feature limits, shares), `claudeAsk()`, `record()` writes `ai_jobs` with a `ref_id` | 0160 §5's note ($40), 0163 §7 |
| Gemini, and what it may read | `Gemini::refused()` (input class must be `public`), `AiClient::INPUT` | 0164 §0.8, R5, R13 |
| The daily allowance | the monthly cap ÷ days; W5's on-demand builds take up to 50% a day | 0147, 0163 §7 |
| Start the build → the session manager | the task already reaches the session manager hourly: `report()` → `health.php` → `gauge-requests.mjs` → `build-requests.json`; a session's pickup comes back through `gauge-pickups.json` → `gauges.json` | kit-buttons, #281 |

## The design

**The thread** is the last section of a task's page (the side panel #282 opened), one column, newest at the foot, as a chat:
- **Jason's (or a staff member's) posts**, typed in the box at the foot (spell check, 0144).
- **The system's lines**, drawn from the logbook's History as it happens (added, taken, its release named, a step confirmed,
  decisions attached, Done when the release is live). No second record: the thread reads the History it already keeps.
- **Claude's posts**, each with what it cost ("Claude · $0.04"), only when someone presses **Ask Claude** on a post or on the
  task (0164 R5: AI acts on a person's words only when called).
- **Gemini's posts**, only for a public page linked in the task (below).
- **The build's progress**, from the session that has it: Taken by …, Being fixed in <release>, Live, as the queue already
  knows them (#281), each a system line.

**What Claude does here** (the brief's three): **Draft the scope** (fills the empty Goal / In / Out / Done when as a
suggestion the person accepts field by field, each acceptance an Edit with History), **Split into steps** (proposed steps,
each see → do → confirm, added only when accepted), and **Write the build brief** (the task, its scope, its decisions'
titles, its steps, as one text). Each is a button in the thread's tools, never automatic.

**Cost and the cap** (Jason's decision): a new AI feature `task_assist` in `AiClient::FEATURES`, input class `person`
(a person's words: Claude only, 0164 R5). Every press goes through `AiClient::allow('task_assist')`, so the $40 cap, the
switch-offs and the shares hold as they do for every feature. The thread shows **"Claude on this task: $0.12 · this month
$7.40 of $40"** (`ai_jobs` by `ref_id`). At the cap, Ask Claude is greyed with `allow()`'s own words ("The month's AI cap
($40.00) is reached: it starts again on the 1st.").

**Start the build** (one filled button, once the task has a scope and a brief): it marks the task "Brief sent" (a line in
the thread, logged) and its brief travels with the task in `report()` to `build-requests.json`, where the session manager
routes it within the hour (the same path Request a fix uses). It never opens a session. The button then reads the state:
"With the session manager · sent <time>", then the session's pickup ("Taken by the Orders session") when it lands.
The task is **Done** only when its release is live or its check passes (#281's rule); a release still needs Jason's OK in
the building session's own pop-up.

**Notifications** go through `Notify::send` with `thing = task:<id>` (0176 A1): a new post from Claude, the build taken, the
task done. One Pulse row per task, updated in place; a push only when it moves to Done or needs Jason (0172 push-polish).

## Gaps, flagged as suggestions (none is decided here)

1. **Gemini in the thread.** On the free tier it may read **public pages only** (0164 §0.8; `Gemini::refused()`), and a
   task's words are staff's own, not public. So the design gives Gemini one job: **Read a public page** linked in the task
   (a provider's docs, a status page), through `Gemini::safe()` first. Jason has linked billing (paid tier, data not used for
   training, relayed 2026-11-24). **Suggest a change to 0164 §0.8:** "nothing private to Gemini's **free** tier" could allow
   internal staff text to the **paid** key only, with the key and tier checked per call. Until Jason decides, Gemini stays
   public-only here. (The Gemini paid-tier task is queued.)
2. **Where the build session's own words land.** Today only its pickup (session, release) comes back. **Suggestion:** a
   `releases/task-notes.json` the session manager writes (task id, a line, a link), read into the thread by the same hourly
   path, so a session's "built, in review" or "needs your answer" shows on the task.
3. **A daily share for `task_assist`.** 0147 gives W5 up to 50% a day; nothing sets one for this. **Suggestion:** no share
   at first (it's pressed by a person, a few cents a press), the $40 cap holding as for every feature; add a share in AI use
   if it grows (0163 §7's shares).
4. **Which model.** **Suggestion:** Claude's cheapest that writes well for drafting (as `AiAssist` uses), set in AI use.

## Releases (after Jason's OK), each its own pop-up
1. **The thread:** posts, system lines from History, the box. No AI.
2. **Ask Claude:** `task_assist`, Draft the scope / Split into steps / Write the brief, cost on the task, the cap's words.
3. **Start the build:** the brief through `report()` to the session manager, its state lines; then Gemini's public-page read.

## Note (Jason, 2026-11-27): built inside the transition plan

Jason: "Yes, the transition plan; and shouldn't your team work out the design as a part of handling the plan through
implementation?", then "there's more agents involved in how something is redesigned, the this doesn't seem like a t5
concern." The four-stage redesign and the thread no longer wait on him: they are **Tier 3** (0180 §1, "non-protected
workflow redesigns"), in the Project Manager's (8) lane, through jason-spec §8's four stages: **Planning** (the System
Architect (20) for the ADR check, the Public UI/UX Designers (27), the Front-End Content Writers (26) for the words, the
Cognitive Load & Simplicity Guardian (15) with its simplicity veto); **Building** (Piece 2); **Implementation** (staging and
the before-and-after check); **Confirmation** (the advocates, the Staff Views Advocate A12 among them, and the Design
reviewer's fidelity check against brand.md, `design/css-theme-brand.md` and `design/table-standard.md`). Tier 4 only if
the Prior Ruling Check finds a high-risk territory (the thread holds staff messages, so territory 5 may apply; the PM
flags it). Jason sees it as a line in the daily batch (0179), unless it meets a §3 trigger. Release 1 also reads `releases/task-rulings.json` (the session manager's rulings,
each made a task by the hourly path) beside `releases/task-notes.json`. Order: task-seed-4, release 1, the Executive
channel, cutover about Dec 15; after the pipeline release and R2, alongside A3; launch work wins.
