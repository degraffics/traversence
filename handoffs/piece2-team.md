# Piece 2: the Team, three ticket types and the agent runner, on staging only (design for review, 2026-11-25)

The Piece 2 build session (`session_01CXLi5kEx1NbamxWYoRxUDh`). **Design only: nothing is built yet.** Route: the session
manager (`session_01CXLi5mGX2sg5QS11gg3Wrh`) reviewed it (2026-11-25). The Design reviewer's checklist was run by this
session (§10), since the reviewer session is archived over the holiday. The session manager emails §9 to Jason (0179). **Built on Sync 2026-11-25 02:02 UTC (b1336ec).** Mockup: `piece2-team/mockup.html`, screens
`piece2-team/p2-{1280,390}.png`.

Sources read: Jason's spec (`handoffs/piece2/jason-spec.md`), decisions/0179, 0165, 0145 §3, 0164 §0.8, 0109, 0162's note,
CLAUDE.md, `builder-playbook.md`, the Work queue's hand-offs (`work-queue.md`, `task-scope.md`, `task-adrs.md`,
`task-seed.md`, `work-queue-phase2.md`, `control-board-2026-11-25.md`), and the live code: `GaugeTasks.php`, `Access.php`,
`Scheduler.php`, `Staging.php`, `StagingGate.php`, `AiClient.php`, `TestAccounts.php`, `SiteHealth.php`,
`staging-tables.php`, `manifests.php`, `theme-tokens.php`, `admin/api.php`.

Each behaviour below names its source. Anything no decision sets is marked **Suggestion** or **Question**.

---

## 1. What I found in the live code first (the spec asks for this critique, §0)

These change the design. Each one is checked against the mirror.

1. **The Work queue is each site's own (staging class `own`).** `control_gauge_tasks`, `_steps` and `_adrs` are in
   `StagingTables::OWN` (`staging-tables.php:78`). Staging never gets live's tasks, and nothing staging does to them reaches
   live. A runner that works "strictly on staging" would be working a different queue from the one Jason uses on live. This
   is the biggest seam. **Question 1** below has the options.
2. **`Access::can()` gives a scheduled job everything.** `Access::who()` returns `['founder', '']` when there's no staff
   session (`Access.php:33`), and a cron job has none, so `can()` is always true there. An agent can't be limited by
   `Access::can()` as it stands. The fix is additive: `Access::canAs($action, $level, $dept)`, the same rule read
   (`rule()`) for a level and department that's named rather than signed in. The runner calls only that. (Builds on the
   session manager's ruling: "Permissions come only from Access::can()".) **Outside Piece 2:** a separate Work queue task
   audits every other cron caller of `Access::can()` for the same hole. The session manager is queueing it.
3. **On staging, Claude has no key until a switch is on.** `AiClient::env()` returns '' for `ANTHROPIC_API_KEY` and
   `GEMINI_API_KEY` unless `Staging::allow('ai')` (the switch `staging_ai`) is on (`AiClient.php:45`). So agents run with no
   AI at all until Jason switches `staging_ai` on. That suits "internal first": every step in releases 1–4 is
   deterministic, and AI only comes in at release 6.
4. **Staging only runs a job that live also runs.** `Scheduler::syncStaging()` copies a cron line only for jobs that have a
   live line (`lineFor($lines, $job)`), and only while "Staging runs its own jobs" is on. So the runner needs a live line
   too. On live it does nothing and writes no log (it joins `Scheduler::QUIET`, so being quiet never counts as late).
5. **Live's alarms aren't on staging either.** `control_alarms` is `own` as well. An audit running on staging sees
   staging's alarms, not live's. Site health's findings are the same on both sides, because both read
   `site-audit/results/findings.json` from GitHub (`SiteHealth::SOURCE`). The audit reads live through the files the
   health report already publishes. It doesn't get a new path into live (CLAUDE.md: "never invent a new access path").
6. **A test account's work is thrown away at Publish.** 0165 §3: "Test data (never carried): anything a test account made
   or owns". That backs the session manager's ruling that agents never act through test accounts. Advocates are views
   only.
7. **Our code already has a `Team` class.** `app/lib/Team.php` is a business's own team (0102). The roster's class is
   `TeamRoster` and its table `team_roster`, so the two never get mixed up.
8. **`GaugeTasks::report()` hides any owner name that doesn't contain "session"** (it shows "a staff member",
   `GaugeTasks.php:400`). Owners written as `system · <role>` are added to what it lets through, because they're role
   names and not people.
9. **The spec's schema is mostly replaced by things we already have.** Its ENUMs, `is_production_founder`, the `can_*`
   flags, `allowed_modules` and the `/api/team/*.php` endpoints are each replaced by something the platform already has
   (§3). Its palette is from before 2026-11-18. `#F8F5EC` and `#EFE3C8` as surfaces were replaced by the tokens in
   `theme-tokens.php` (`--tv-canvas #E4DCC5`, `--tv-card-a #F6F1E4`, `--tv-ink #2A1D14`, `--tv-umber #3E2B1F`).
10. **The in-platform runner can't write code.** Code still comes from Claude Code sessions through git and `send.py`
    (0159 §5). On the platform, the "Architect/Builder" role writes the build brief and hands the ticket to the session
    manager by the hourly report. That's the same path Request a fix uses (`report()` → `build-requests.json`). It never
    opens a session (`work-queue-phase2.md`: "Start the build goes through the session manager").

## 2. The model in one picture

```
 + Add a task (light: what, why, source; type optional, Work by default)        the scheduled audit (daily, staging)
            │                                                                        │ internal sources first
            ▼                                                                        ▼
  WORK ticket: Planning → Building → Implementation → Confirmation → Done     AUDIT ticket: Found → Diagnosing → Confirmed ─┐
     (each move through its gate; back a stage with a reason)                    └──→ Cleared by its own check (noise)     │
            ▲                                                                                                             │
            └──────────── a confirmed Tier 1/2 finding opens a Work ticket ──────────────────────────────────────────────┤
                                                                                                                          │
  RESOLUTION ticket (Tier 3, 0179 rule 3): Brief drafted → Waiting for Jason → Decided (A, B or his words) ◄────────────┘
            └──→ a Work ticket "Record the precedent" (a decision note + a CLAUDE.md line) → closed when it's done
```

Each ticket is a row in the queue that already exists, never a second list (the session manager's ruling, and
`work-queue.md` rule 1).

## 3. Tables

All written for MySQL 5.7 and PHP 8.0. Each `CREATE` or `ALTER` goes in its own migration file. Each seed or `UPDATE` goes
in another file, with `data_reason` (0162's note: a seed in the same file as a CREATE is cleared unrun).

### 3.1 Tickets: columns on `control_gauge_tasks`

The guarded `ADD COLUMN`s are written in the pattern from CLAUDE.md.

| Column | Type | Meaning | Source |
|---|---|---|---|
| `ticket` | VARCHAR(12) NOT NULL DEFAULT 'work' | `work`, `audit` or `resolution` | Jason's requirement 1 |
| `stage` | VARCHAR(16) NULL | Work: `planning`, `building`, `implementation` or `confirmation`. Audit: `found`, `diagnosing` or `confirmed`. Resolution: `brief`, `waiting` or `decided` | requirement 1; spec §8 |
| `stage_at` | DATETIME NULL | when it entered its stage (for stalls) | spec §7 "SLA awareness" |
| `tier` | TINYINT NULL | 1, 2 or 3, from its rules (§6), never guessed | 0179 rule 3; spec §8 |
| `audit_key` | VARCHAR(120) NOT NULL DEFAULT '' | an Audit ticket's finding (`alarm:…`, `finding:…`, `job:…`), so one finding gives one ticket | spec §9 |
| `parent_id` | INT UNSIGNED NULL | the ticket it came from (Audit → Work, Resolution → its precedent ticket) | spec §8 "updates precedent" |

Key: `(ticket, stage)`. The existing states (open, taken, fixing, late, done, withdrawn) stay as they are. They say who has
a ticket. The stage says where it is.

New table `control_gauge_task_moves` (each stage move, inserts only): `id, task_id, at, tz, from_stage, to_stage, who,
why, run_id NULL`. The move is the ticket's History and the runner's Undo. Its class follows the tasks' class (Question 1).

New table `control_gauge_task_briefs` (one per Resolution ticket):

| Column | Type | Meaning |
|---|---|---|
| `task_id` | | UNIQUE |
| `decision` | TEXT | |
| `why_you` | TEXT | |
| `boundary` | VARCHAR(20) | `money`, `data`, `address`, `nations`, `privacy`, `mission` or `deadlock` (0179 rule 3) |
| `a_text`, `a_pros`, `a_cons`, `b_text`, `b_pros`, `b_cons` | TEXT | Options A and B, each with pros and cons |
| `recommend` | CHAR(1) | `a` or `b` |
| `delay` | TEXT | Impact if Delayed |
| `cost` | VARCHAR(255) | |
| `answer` | VARCHAR(1) | `a`, `b` or `o` (other) |
| `answer_words` | TEXT | |
| `answered_by`, `answered_at` | | |
| `precedent` | VARCHAR(255) | the decision number and the CLAUDE.md line once they're written |

Every change to a person's words in a brief is an Edit, with the old words kept in History (CLAUDE.md "Edit or Undo").

### 3.2 The roster: `team_roster` (class `records`, people make it)

| Column | Type | Notes / source |
|---|---|---|
| `id`, `slug` UNIQUE, `name` | | spec §11 |
| `department` | VARCHAR(20) | `leadership`, `operations`, `build` or `advocates`: the spec's four departments (§11) |
| `kind` | VARCHAR(12) | `founder`, `operator` (day to day), `keeper` (in the background), `auditor` or `advocate`. Requirement 3: "separates day-to-day operators from background keepers and auditors" |
| `made_of` | VARCHAR(10) | `human`, `ai`, `hybrid` or `persona` (the spec's `entity_type`, as VARCHAR) |
| `mandate` | TEXT | its job in a few lines |
| `rules` | TEXT | the decisions it works under, one per line ("0066: …"). Shown, never run |
| `stages` | VARCHAR(80) | which stages it works, comma-separated |
| `acts_level`, `acts_dept` | | the level and department it acts with, from `Access::RANK` / `DEPT_NAMES`. Its permissions come only from `Access::canAs()` (finding 2). Advocates and the Founder have none |
| `tier_max` | TINYINT | the highest tier it may act on alone: 1 or 2. Tier 3 is never an agent's (0179) |
| `test_account` | VARCHAR(30) | advocates only: the key in `TestAccounts::MEMBERS`/`STAFF`, or `signed_out` (0145 §3). Never a password, which stays in `CHECK_ACCOUNTS` |
| `reports_to` | VARCHAR(60) | a slug |
| `status` | VARCHAR(10) | `active`, `standby` or `inactive` |
| `sort`, `updated_at`, `updated_by` | | |

Left out of the spec's schema, with why:
- `is_production_founder`: the Founder is the real user and staff login (the ruling).
- `can_approve_target`, `can_sign_off_*`, `can_authorize_live`, `can_override_sla`: permissions come from `Access` alone.
- `allowed_modules`: `Workspaces::opens` already decides which modules a level and department can open.
- `pipeline_roles` and `guardrails` as JSON: plain columns, `stages` and `rules`.
- The `reports_to` foreign key: a slug instead, so catch-up and Publish need no new entry in the register of record
  numbers (0165 §4).

### 3.3 The runner's log: `team_runs` (class `own`, staging's own)

| Column | Notes |
|---|---|
| `id`, `at`, `role` (slug), `task_id`, `step` | `step` is the step kind, from §5's list |
| `read` | what it looked at, as a short line |
| `did` | what it did, as a short line |
| `state` | `done`, `proposed`, `held` (with why), `refused` (with why) or `failed` |
| `undo` | how it's undone: the move id, the ticket id, or the words saying why it can't be |
| `undone_at`, `undone_by` | |
| `cost_micros`, `ai_job` | 0 until release 6 |
| `touches` | the pages and records it changed. Piece 3's before-and-after check reads this |

Every run is also one line in the board's logbook (`ControlLog::add`, who `system · <role name>`).

Every new table goes in `staging-tables.php` in the same release (zip check R14).

## 4. Screens

All screens are on the admin kit, `design/table-standard.md` and the tokens in `theme-tokens.php`. The mockup is at 1280
and 390.

### 4.1 The Work queue (Development), extended in place

- **Two new columns on each row**:
  - **Type**: a quiet tag, Work, Audit or Resolution.
  - **Stage**: Planning, Building, Implementation or Confirmation, with its gate's state ("Building · release not named
    yet").
  - A Resolution ticket shows **Waiting for you** in amber (`--tv-hold`), and only that ticket.
- **The filter is still one button** (table-standard), with Type, Stage and Department added, each with its count.
- **`+ Add a task` stays light** (spec §8: "must not force the Founder to fill out a heavy Decision Brief"):
  - The fields stay as they are: place, what, why, source.
  - It adds one optional choice, Type (Work by default).
  - Choosing Resolution doesn't open a brief. The Translator drafts one (§5), or a person fills it on the ticket's page.
- **On a phone (390px)** each row becomes the table standard's card (table-standard §12): its status (Type · Stage, or
  Waiting for you), the name, the sub-line, its next step's button and ⋯, edge to edge (0058 §11).
- **A round Team icon** (`tv_icon('users')`, 30px, title "Open the Team") sits in the queue's header beside the filter. It
  opens System Configuration → Team in the side panel (`data-peek-frame`; the spec's `[👥]`).

### 4.2 A ticket's page (the side panel from #282), extended

- **The stage strip** at the top: the four stages as one line, with the current one marked and the gate in words ("To move
  to Building: a goal and Done when (set), a decision attached or 'none applies' (not yet), someone has it (yes)"). One
  button does the next move, and it's greyed with the reason until the gate passes. **✎** moves it back with a reason.
  **⋯** has the other valid moves (CLAUDE.md "More than one way forward").
- **A Resolution ticket:** the Decision Brief in four parts, plus Cost:
  - Each option shows its pros and cons, and the recommended one is marked.
  - Jason answers with **Choose A**, **Choose B** or **Answer in my words**, on the page, never in a pop-up (0179).
  - Once he's answered, the page shows "Decided: A · Jason · <time>" and the precedent ticket it opened.
- **The agents' runs** (staging only), each as one line:
  - The line reads "Project manager · moved to Building · gate passed · 14:05 MST", with **Undo**.
  - A run that can't be undone says so, with why.
  - A proposal shows **Accept** (applied as an Edit with History) and **Dismiss**.

### 4.3 System Configuration → Team (`/admin/config/team`), a new section

- Manifest: `'team' => $W('Team', 'users', [['table', 'config/team']])`.
- The tool `config/team` in `tools.php`, opened at `/admin/api/config/team` (0109). There are no `/api/team/*.php` files.
- **Columns:** Role (the name, as a link), Department, Kind, Works (its stages), Acts as (level and department, or "views
  only"), Status, Open tickets.
- **Group rows by department** (table-standard §8). The 13 standby roles sit in one folded group, "On standby (13)", so the
  day-to-day view stays short (spec §3, "Quiet by default").
- **Filter button:** Department, Kind and Status, with counts.
- **On a phone (390px)** each role is a card (table-standard §12): its status, the name, "Department · Kind · Acts as", ▾
  and ⋯.
- **A role's row opens its drawer:**
  - mandate, rules and stages, each with ✎ Edit and History;
  - its status, changed by **Put on standby** / **Make active**, with its way back on the same card;
  - for an advocate, its bound test account, by name only, linking to Test accounts
    (`/admin/config/testaccounts`);
  - its last 10 runs.
- **The view:** Executives (the Founder included) by default, through `Access::can('config.team')`. Edit needs the
  Founder (`config.team.edit`, `min founder`). Both can be changed in Permissions.

### 4.4 The daily batch (0179's summary as a page; release 5)

- It's on Development as a strip over the queue: "Today's batch: 4 releases waiting for your OK".
- Each release gets one line, with what it changes, why, its decision, its checks on staging and its way back (0179 "How
  it works").
- **Approve the batch** records it the way Approve the scope does (`GaugeTasks::approve`, a new kind `batch`). It travels
  in `report()` to the session manager within the hour. Until this release is live, the session manager keeps writing the
  summary in its chat (0179).

## 5. The runner: one job, one ticket, one step

- The job is `team_tick` in `Scheduler::JOBS` and `AUTO`, every 10 minutes (an existing preset), and in `QUIET`.
- Its script works like this:
  1. **Live:** `Staging::here()` is false, so it exits without a log.
  2. **Off:** `Switches::on('team_runner')` is false. The switch is shipped off and switched on from System Configuration
     → Switches (`https://staging.trvgo.us/admin/config/switches`), so the script exits.
  3. **Lock:** it takes `GET_LOCK('team_tick', 0)`, so two runs never overlap.
  4. **Pick one ticket:**
     - open, not done or withdrawn;
     - its stage worked by an `active` role;
     - no run on it in the last hour;
     - priority first, then age (the queue's own order, #282).
  5. **Do one step** from the list below, through the owning library, never SQL of its own. Every outside call asks
     `StagingGate::hold()` first.
  6. **Log it** in `team_runs` and the logbook, then stop.
- **A ticket a person has taken is never worked by an agent.** It's skipped while its owner is a person. Agents work only
  tickets that are unowned or owned by `system · …` ("never overwrite what a person entered", CLAUDE.md and 0164).
- **An agent never acts on Tier 3** (0179). On a Tier 3 ticket it can only draft the brief.

The steps in releases 3–4 are all deterministic, so none of them costs anything:

| Role (status at the start) | Step | What it reads first (internal) | What it does | Undo |
|---|---|---|---|---|
| Project manager (active) | `gate` | the ticket's scope, ADRs, owner, release, `site_releases` | moves the ticket one stage when the gate passes; Implementation → Confirmation when its release is live | the move back, logged |
| Project manager | `stall` | `stage_at` against the stage's limit | marks a stall ("In Building 5 days, nobody has it"), once | removes the mark |
| QA (active) | `confirm` | the ticket's steps and Done when, the checks named in them (Preflight, Site health, a gauge's check) | runs each named check again and records pass or fail per step. All passing makes it **Done**, with no "mark done" (CLAUDE.md four steps) | reopens it to Confirmation, logged |
| Translator / Brief (active) | `brief` | a Resolution ticket's what, why, source, attached decisions' titles and the 0179 boundary rule | drafts the brief's fields from the template (no AI), as a **proposal** | Dismiss (a proposal changes nothing until it's accepted) |
| System Architect (active) | `handoff` | Building tickets with a scope and no session | writes the build brief and marks it "With the session manager" (via `report()`), for the Full-Stack Builder, which is the Claude Code session that takes it | takes the brief back, logged |
| Auditor: Ops & Reconciliation (active) | `audit` | §6's sources | opens or updates Audit tickets | withdraws the ticket with "undone" |
| Content, Directory curator (active) | none yet | | listed with "no steps yet" until a step is written for them. Orders/Finance is on standby (§7): money is always Tier 3 | |
| Advocates (active, views only) | none | | bound to test accounts for piece 3's before-and-after check, and never act (finding 6) | |

### 5.1 The $40 cap

Jason, 2026-11-25, relayed by the session manager: "When the $40 paid AI cap is reached, pause paid external calls cleanly,
fall back to internal/deterministic work, and raise a Tier 3 Decision Brief only if paid AI is strictly needed to move
forward."

- **Every step is marked `paid` or `free`.** Every step in releases 3–4 is `free`. Before any `paid` step, the runner reads
  `AiClient::allow($pdo, 'task_assist')`. That's the same check every AI feature uses: the cap, the switch-offs, the daily
  runs and the shares. Any outside call also asks `StagingGate::hold()` first.
- **At the cap, only the paid steps wait.** A paid step is recorded as a run in state `held`, with `allow()`'s own words
  ("The month's AI cap ($40.00) is reached: it starts again on the 1st."). The runner then goes on to the next free step,
  on the same ticket or another one. Nothing is retried in a loop, and nothing fails.
- **One brief per ticket, never one per step.** A ticket that can't move without paid AI raises one Resolution ticket for
  it. That happens when its next step is paid, it has no free step left, and the cap still holds at the next day's audit.
  The brief:
  - names the ticket;
  - sets out Option A (raise the cap or wait for the 1st) and Option B (a person does the step), with pros and cons;
  - has a **Cost** line: this month's spend against $40, and the step's estimate (`AiClient::estimate`).
  - A second held step on the same ticket adds to that one brief (`audit_key = cap:task:<id>`).
- **It clears by itself.** On the 1st, or when the cap is raised, `allow()` passes again. The held step runs at its next
  turn, and the brief closes, with "No longer needed: the cap allowed it" (CLAUDE.md: an issue clears itself when its check
  passes).

### 5.2 Exception paths never skip the guards

Jason, 2026-11-25: 0179's urgent fixes for live harm still honour 0066 and privacy. No path in this design, whether a run,
the audit, a precedent or an urgent fix, reads or writes landmarks or nations' land except through `Landmarks::visibleSql()`
/ `visible()` and the `Nations` rules. None of them skips the privacy guards (test-account exclusions, 0145 §1's
"a staff member", `Gemini::refused()`). Agents act only through the owning libraries (§5), which already carry those
guards. The tests check that the runner makes no direct SQL on `landmarks` or `nations` (`runner.php`, a code grep).

**Why "one step and stop":** a ticket advances at most once an hour. Every move can be seen and undone. A fault stops one
step, not a chain. (The ruling: "each run takes one task, does one step, logs it, and stops.")

**When it can't act**, it says so on the ticket and never retries in a loop (spec §4.5, playbook principle 1):
- held by the gate: "held on staging: …";
- refused by `canAs`: "Needs a Director";
- a check that can't run: "Request the check".

## 6. The scheduled audit and the tiers

- The job is `team_audit_tick`, daily on staging. It's also run once after each "Release #N: Live" record is synced, and
  on demand with **Run the audit now** on the Team page. It does the Auditor's `audit` step for every source in one run.
- **Its sources, internal first** (spec §4.1, §9):
  1. Site health's open findings (`SiteHealth::open()`, the file both sites read).
  2. Jobs that are late or missed (`Scheduler::misses()`, `lastRuns()`).
  3. Staging's own alarms and held calls (`control_alarms`, `StagingGate::counts()`).
  4. Live's readings, but only those the health report already publishes to GitHub (the list is written down at build,
     with each file named, finding 5).
  5. Test-account isolation: `TestAccounts`' own check that nothing a test account made is counted.
- **Diagnose before raising** (spec §9.2): a new finding is `found`. The next day's run checks it again. If it has passed,
  it's **Cleared by its own check** ("noise"). If it still stands, it's `confirmed`. It only reaches a person once it's
  confirmed.
- **The tier comes from rules, never a guess.**
  - **Tier 3** when the finding's area or files touch:
    - orders, payments or billing (money);
    - a migration with DROP/MODIFY or a data change over the threshold (data);
    - a route in `site-audit/pages.md` (public addresses);
    - `Landmarks`, `Nations` or 0066 (nations);
    - people's details (privacy);
    - or when two open tickets cite decisions that disagree (deadlock).
  - **Tier 1** when it is clearly wording, FAQ, tests or layout (0179 rule 2's list).
  - **Tier 2** for everything else.
  - Anything unclear counts as Tier 3 (0179: "A release that is not clearly low-risk counts as needing approval").
- A confirmed Tier 1 or 2 finding opens a **Work ticket** at Planning, with `parent_id` pointing to the audit. A Tier 3
  finding opens a **Resolution ticket** and the Translator drafts its brief.

**Precedent** (requirement 2, 0179 rule 4): Jason's answer opens a Work ticket, "Record the precedent: a note in
decisions/NNNN and a line in CLAUDE.md". The Resolution ticket closes only when that ticket's check passes: the note can
be read through `DocsRepo` with the ticket's number in it. There's no second rule book.

**A Tier 3 never waits on the batch.** A Resolution ticket that reaches Waiting for you goes into the session manager's
daily summary word for word: all four parts plus Cost, with its ticket number. The session manager emails that summary
to Jason. This holds even while Publish hasn't carried the ticket to live (Question 1), because the summary is read from
staging's queue through the same `report()` the hourly fetch already reads. Jason's answer comes back to the session
manager, which records it on the ticket. That's the same path as the batch approval: `GaugeTasks::approve` with a new
kind `answer` (its value `a`, `b` or his words), reported by `report()`. The Choose A / Choose B / Answer in my words
buttons stay on the ticket's page for when he's at his desk. Either way, his answer is written once and kept in History.

**Notifications** go only to Jason, only for a Resolution ticket that reaches Waiting for you. That's one thread per
ticket, `Notify::send` with `thing = task:<id>` (0176 A1), and one push (0172 push-polish). Work and Audit tickets never
notify anyone (spec §3, "Quiet by default"). On staging the gate holds the notification anyway, so on live this happens
only once the tickets are records (Question 1).

## 7. The roster at the start (the session manager's ruling and its review of 2026-11-25; Jason's 33 in §11 of his spec)

One row per entity in the spec (33 rows), so every one of them is present and filterable (the spec's acceptance criteria).
**9 working:** the Founder and 8 agent roles, within Jason's "about 8".

| Spec # | Role | Department | Kind | Status | Acts as | Its steps |
|---|---|---|---|---|---|---|
| 1 | Executive Lead & Founder | leadership | founder | active | the real login, never an agent | answers Tier 3 |
| 2 | Executive Translator & Briefing Lead | leadership | operator | **active** | staff · ops | `brief` |
| 6 | Project Manager | operations | operator | **active** | manager · ops | `gate`, `stall` |
| 8 | Operations & Reconciliation Manager | operations | auditor | **active** | manager · ops | `audit` (spec §9 names it) |
| 14 | Regional Directory & Data Curator | operations | operator | **active** | staff · con | none yet |
| 15 | System Architect | build | operator | **active** | staff · eng | `handoff`: writes the build brief |
| 19 | Content & Copy Editor | build | operator | **active** | staff · con | none yet |
| 21 | Full-Stack Builder | build | operator | **active** | staff · eng | none on the platform: it's the Claude Code session the brief goes to (finding 10) |
| 22 | Technical QA & Security Gatekeeper | build | auditor | **active** | staff · eng | `confirm` |
| 23–33 | the 11 Advocates, each bound to its test account or `signed_out` | advocates | advocate | **active, views only** | none | none (piece 3) |
| 3, 4, 5, 7, 9, 10, 11, 12, 13, 16, 17, 18, 20 | the other 13 roles, Order & Fulfillment (10) and Finance (11) among them | as in the spec | keeper / operator | standby | set when made active | — |

Orders/Finance is on standby: it has no steps yet, and money is always Tier 3 (the session manager's call, 2026-11-25).
The count is 9 working, 11 advocates and 13 on standby, which is 33.

## 8. Every state and its ways out

| Ticket | State or stage | Ways forward | Way back | Exit |
|---|---|---|---|---|
| Work | Planning | Move to Building (gate: goal, Done when, a decision attached or "none applies", an owner) | — | Withdraw (with a reason) |
| Work | Building | Move to Implementation (gate: its release named) | ✎ back to Planning (reason) | Withdraw |
| Work | Implementation | goes to Confirmation by itself when its release is live (`site_releases`) | ✎ back to Building (reason) | Withdraw |
| Work | Confirmation | Done by itself when every step's check passes (QA) | reopened if a check fails | — |
| Audit | Found | its second look: confirmed or cleared by itself | — | Withdraw |
| Audit | Confirmed | opens a Work or Resolution ticket (linked); done when that one is | — | Withdraw |
| Resolution | Brief | Accept the draft field by field, or write it; **Send to Jason** once the four parts and the options are filled | ✎ Edit | Withdraw |
| Resolution | Waiting for you | Choose A, Choose B, Answer in my words | Jason's ✎ on his answer while its precedent ticket isn't done (kept in History) | — |
| Resolution | Decided | the precedent ticket; closed when its note exists | — | — |
| Role | Active / Standby / Inactive | Make active / Put on standby / Retire (inactive, kept) | the opposite button on the same card | never deleted |
| Run | done / proposed | Undo (done) or Accept / Dismiss (proposed) | Undo is logged; a second Undo is refused, with "already undone" | — |

Side effects on every move: a History line, a logbook line, `stage_at` reset, the gauge's count read again. Nothing claims
success without its check (CLAUDE.md "End to end").

## 9. Questions for Jason: five Decision Briefs (final text, for the session manager to email)

Each brief has the 0179 shape: the Decision, why it needs you, options A and B with their pros and cons and a
recommendation, the impact if delayed, and the cost.

### Q1. Decision (nothing changes on the site yet): where the agents' work lives
Goes live in P2-3, the runner, third in Piece 2's queue.
- **The Decision:** whether the Work queue's three tables are copied between live and staging, so agents on staging work
  the same queue you use on live.
- **Why it needs you:** it changes how staging and live share data (0165 §3) and the class two earlier builds recorded
  (`work-queue.md`, `task-scope.md`). Today the queue is each site's own, so staging can't see live's tasks.
- **Option A: the queue's tables become "records".**
  - Live's tasks come to staging by catch-up.
  - What agents do on staging reaches live only at Publish, inside the batch you approve.
  - Pros: one queue; agents stay on staging; you approve their work like any release.
  - Cons: a task changed on both sides at the same time goes on staging's conflicts list for you to choose; it needs a
    note on two earlier records.
- **Option B: the queue stays each site's own.**
  - Pros: nothing changes in how staging copies.
  - Cons: two queues; you would add tasks on staging to have them worked, which is against "one copy".
- **Recommended:** A.
- **Impact if delayed:** releases P2-1 and P2-2 go ahead; the runner (P2-3) waits.
- **Cost:** none.

### Q2. Decision (nothing changes on the site yet): Confirmation as a stage
Goes live in P2-1, ticket types and stages, first in Piece 2's queue.
- **The Decision:** whether a task is Done when its release is live (today, #281), or only after a Confirmation stage
  whose checks pass.
- **Why it needs you:** your four stages put Confirmation after the release, and that changes a rule you approved on
  2026-11-24.
- **Option A: Confirmation after live.**
  - A task reaches Confirmation when its release is live.
  - It's Done when each of its steps' checks passes again.
  - A task with no steps is Done at once, as today.
  - Pros: "done" means checked, not just shipped; it matches your four stages.
  - Cons: tasks stay open a little longer.
- **Option B: Done at live, as today.**
  - Pros: no change.
  - Cons: the fourth stage would only be a label.
- **Recommended:** A.
- **Impact if delayed:** P2-1 waits.
- **Cost:** none (the checks are our own).

### Q3. Decision (nothing changes on the site yet): how you answer a Tier 3 brief
Goes live in P2-1 (the brief and its buttons) and P2-5 (the answer travelling back).
- **The Decision:** how a Tier 3 brief reaches you, and how your answer comes back.
- **Why it needs you:** a Tier 3 is yours alone (0179 rule 3). Under Q1 A, a brief drafted on staging reaches live only at
  Publish, so it would otherwise wait for your own batch.
- **Option A: in the daily summary email, and on the ticket's page.**
  - Every brief waiting for you is in the session manager's daily summary, word for word (all four parts and Cost). The
    session manager emails it to you.
  - You reply, the session manager records your answer on the ticket, and it's kept in History.
  - At your desk you can also answer on the page: Choose A, Choose B, or Answer in my words.
  - A Tier 3 never waits on the batch.
  - Pros: it reaches you on holiday; nothing sits; your answer is in one place.
  - Cons: by email, the session manager records the answer for you, not you yourself.
- **Option B: on the ticket's page only.**
  - Pros: you record it yourself.
  - Cons: it waits until you open the admin, and under Q1 A until Publish.
- **Recommended:** A.
- **Impact if delayed:** P2-1 can go with the page buttons; P2-5 waits.
- **Cost:** none (system email).

### Q4. Decision (nothing changes on the site yet): paid AI for the agents
Goes live in P2-6, last in Piece 2's queue, and only if you say yes.
- **The Decision:** whether agents may use Claude for wording (drafting a brief, a scope, a step), or stay on free,
  rule-based steps.
- **Why it needs you:** it spends money ("No income, no expense"; the $40 cap) and needs staging's AI switch on.
- **Option A: one AI feature for all agents (`task_assist`).**
  - Claude only, inside the $40 cap, only while staging's AI switch is on.
  - At the cap, only the paid steps wait. The free work goes on. One brief per stuck ticket comes to you, with its cost
    (your rule, 2026-11-25).
  - Pros: better drafts; you'd write less.
  - Cons: a few cents a draft, from the same $40.
- **Option B: no AI for agents.**
  - Pros: costs nothing.
  - Cons: briefs and scopes are fill-in templates that a person polishes.
- **Recommended:** B until P2-1 to P2-5 have run a while. Then A, if the drafts need it.
- **Impact if delayed:** none. Releases P2-1 to P2-5 don't use AI.
- **Cost:** A: within the $40 Claude cap (a press costs a few cents at Haiku or Sonnet prices); B: $0.

### Q5. Decision (nothing changes on the site yet): staff's own text to Gemini's paid key
Not in a Piece 2 release. It would be its own release after your answer.
- **The Decision:** whether agents and staff tools may send internal text (a task's words, its scope, notes; never a
  member's or a client's) to Gemini on the paid key.
- **Why it needs you:** privacy (0179 rule 3). 0164 §0.8 says "nothing private to Gemini's free tier", and R5 says "a
  person's words only to Claude". The paid tier changes Google's terms (data not used for training), not our rule.
- **Option A: the paid key only, checked on every call.**
  - A new input class, `internal`, is allowed to Gemini only when the call uses the paid key.
  - `Gemini::refused()` checks the class and the tier on every call, and refuses when the tier can't be confirmed.
  - Members' and clients' words stay Claude only.
  - Pros: a second model for wording; work moves off the $40 Claude cap.
  - Cons: one more check on every call; a wrong key setting is a privacy risk, which the check exists to catch.
- **Option B: Gemini stays public-only (today's safe default).**
  - Pros: nothing changes; no new risk.
  - Cons: agents' wording uses Claude alone.
- **Recommended:** B now. A only once the tier check exists and its test proves a free key is refused.
- **Impact if delayed:** safe on hold. Nothing in Piece 2 uses Gemini.
- **Cost:** none now. Under A, Gemini's paid use comes from the $20 prepaid.

## 10. Design check against the Design reviewer's checklist

The Design reviewer session is archived over the holiday, so the session manager asked this session to run its checklist
(`handoffs/design-reviewer-2026-11-24.md`) against both screens, `p2-1280.png` and `p2-390.png`. It was checked against
the reviewer's checklist, **not by the reviewer session**.

| Check | Source | Result |
|---|---|---|
| One filled button per view, brand brown with page-colour text | brand.md §4 | Pass: **+ Add a task** only. Choose A / Choose B, Name its release and the rest are outlined |
| Umber only for bars | brand.md §4 | Pass: the top bar and the current stage in the stage strip only |
| Red only for trips and halts, amber for holds and waits | css-theme-brand.md | Pass: no red; "Waiting for you" and Today's batch are amber |
| Tokens as they stand (no `#F8F5EC`) | theme-tokens.php | Pass: canvas `#E4DCC5`, cards `#F6F1E4`, ink `#2A1D14` |
| One table, group rows with ▾ | table-standard §8; open design note 2 | Pass on Team. The queue keeps #281's owner groups (not drawn in the mockup) |
| ⋯ in place of a hover bar, on every row | table-standard | **Fix in the build:** the 1280 queue rows in the mockup leave out #281's ⋯ and ✎. The build keeps them as they are |
| Phones: rows become cards (status, name, sub-line, button, ⋯), 3px, no sideways scroll | table-standard §12; 0058 §11 | Pass: scrollWidth 390 at 390 for both lists |
| Paging and the count | table-standard §9 | Pass: "Showing 5 of 41 · Show 10 more", as #281 |
| Counts agree on every surface | the reviewer's checks | Pass: 41 open = Filter (41); 1 waiting = one row; Team 9 working + 11 advocates = Active (20); each group's count matches its rows |
| One fact said once | the reviewer's checks | Pass: the brief's state shows once in the strip, the recommendation once on option B |
| Every action is a task (see, do, confirmed, next) | CLAUDE.md #1308 | Pass: the gate says what's missing; one button does the next move; Done only by its check |
| Suggested action, ✎ and ⋯ on each task or state line, greyed with the reason | CLAUDE.md #1317 | Pass: Move to Implementation greyed "Name its release first", with ✎ and ⋯ |
| Edit for entered words, Undo only for actions | CLAUDE.md #1314–#1315 | Pass: brief fields and roles use ✎ with History; Undo only on runs (actions); a proposal has Dismiss |
| Icons with tooltips for inline actions; primary action and states in words | CLAUDE.md #1316 | Pass: Undo, ✎, ⋯ and the Team icon have `title` and `aria-label`. The mockup uses glyphs; the build uses `tv_icon()` (CLAUDE.md "never emoji") |
| A button's verb says what happens | this checklist | Pass: Choose B records now; the Architect's step says "With the session manager" (queued), never "Fix it" |
| Lines that carry tasks have no × | 0129 note | Pass: Today's batch and the gate line have none |
| Everything that pops open closes (×, Esc, a click outside) | CLAUDE.md | × drawn on the panel; Esc and a click outside are tested in `tickets.cjs` and `roster.cjs` (§12) |
| Times labelled in their stored zone | CLAUDE.md "Times" | Pass: "14:05 MST" |
| Decision or release, said every time | CLAUDE.md #1341 | Pass: every question in §9 opens "Decision (nothing changes on the site yet)" |
| Outside links open in the browser, ours in Peek | CLAUDE.md #1344 | Pass: the Team icon and the decisions open in Peek; there are no outside links |
| Never invent what's set; gaps as suggestions | CLAUDE.md #1334–#1336 | Pass: each behaviour cites its source; the gaps are §9's questions |
| What the system knows is applied, not asked | this checklist | Pass: the stage seed comes from today's states; the gate reads what's set |
| A failure is an error notice with its way forward | the reviewer's checks | Not shown in the mockup. A held or refused run says why and offers Request the check (§5) |

## 11. Releases, in order (each one coherent, each through the design checklist, the session manager and the daily batch)

| # | Release | What it holds | Where it's seen |
|---|---|---|---|
| P2-1 | `team-tickets.zip` | ticket type, stage, tier, moves and briefs (columns, 2 tables, 2 migrations: schema, then the stage seed from today's states: fixing → Implementation, taken with a scope → Building, the rest → Planning); the stage strip and gates; the Resolution brief filled by hand; the filter's Type and Stage | live, admin only |
| P2-2 | `team-roster.zip` | `team_roster` + its seed (2 migrations), `config/team` (manifest, tool, drawer, status), `Access::canAs()`, the Team icon on the queue | live, admin only |
| P2-3 | `team-runner.zip` | `team_tick`, `team_runs`, the switch `team_runner` (off), the Project manager's, QA's, Translator's and Builder's steps, Undo, runs on a ticket's page (staging) | staging only (live: nothing runs) |
| P2-4 | `team-audit.zip` | `team_audit_tick`, its sources, diagnose twice, the tier rules, Audit → Work / Resolution, the precedent ticket, Run the audit now | staging only |
| P2-5 | `team-batch.zip` | the daily batch strip and Approve the batch (`approve` kind `batch`); Jason's Tier 3 answer from the session manager (`approve` kind `answer`), both through `report()` | live, Jason |
| P2-6 | `team-ai.zip` (only if Question 4 is yes) | `task_assist` for the Translator's and Content's wording | staging only |

P2-3 waits for Question 1. Each release adds its FAQ entries (decisions/0057), its staging classes (R14) and claims
(`checkout.py`).

**Groundwork for piece 3, in these releases:**
- each run's `touches` (the pages and records it changed);
- Undo on every run;
- a run's AI cost per role, shown but not yet enforced (piece 3's spend limit per agent).

**Not built here:** the before-and-after check, and rule 2's automatic publishing (0179).

## 12. Tests (`tools/team-tests/`, on the MariaDB bench with the mirror and each release over it)

- `tickets.php`:
  - each gate passes and fails with its words;
  - moves forward and back, with History;
  - Withdraw from every stage;
  - Done at Confirmation only when the checks pass;
  - the stage seed run twice gives the same stages;
  - a seed beside an existing table still runs (0162's #284 case).
- `tickets.cjs` at 1280 and 390:
  - the Type and Stage columns, the filter's counts;
  - the stage strip's greyed button and its reason;
  - the brief's four parts, Choose A and its state line;
  - ×, Esc and a click outside on every panel;
  - no sideways scroll.
- `roster.php`:
  - the seed gives 33 rows (9 working, 11 advocates, 13 on standby), the same when run twice;
  - `canAs` against `Access::can` for every level and department;
  - an advocate has no `acts_*`;
  - Edit with History; standby and back.
- `roster.cjs`: the Team page and its drawer at 1280 and 390, the standby group folded, the Team icon opening it in the
  panel.
- `runner.php` (with the cap held: free steps go on, paid steps are held, one brief per ticket, cleared on the 1st; and no direct SQL on `landmarks` or `nations`):
  - it refuses on live (`StagingGate::pretend(false)`), with no log;
  - the switch off means no run;
  - the lock means two runs at once do one step;
  - one step per run;
  - a ticket a person has is skipped;
  - Tier 3 only gets a brief;
  - every step's Undo, and a second Undo refused;
  - a held outside call is recorded as held;
  - `report()` passes `system · …` owners.
- `audit.php`:
  - one finding gives one ticket, run twice;
  - a transient finding clears on its second look;
  - each tier rule;
  - Tier 3 opens a Resolution ticket with a drafted brief;
  - the precedent ticket closes the Resolution.
- The existing suites must still pass: `work-queue.cjs`, `task-scope.cjs`, `task-adrs.cjs`, `gauge-panel`, `board-views`.

## 13. The admin's test (one line per new state)

- **A row shows "Building · release not named yet".** Jason sees where it is and what's missing. He presses its name, then
  **Name its release**, in the panel.
- **A row shows "Waiting for you" (amber).** A Tier 3 ticket needs him. He opens it, reads the brief, and presses Choose A
  or Choose B.
- **The Team page's "On standby (13)" group.** Those roles exist but don't run. He can open one and press **Make active**,
  which works only once it has a step (greyed with that reason until then).
- **On staging, a run line reads "Project manager · moved to Confirmation · its release is live".** The system did it. He
  can press Undo if it's wrong.
- **"Today's batch: 4 waiting".** He reads each line and presses **Approve the batch**.
