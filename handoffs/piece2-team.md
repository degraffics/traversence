# Piece 2: the Team, three ticket types and the agent runner, on staging only (design for review, 2026-11-25)

The Piece 2 build session (`session_01CXLi5kEx1NbamxWYoRxUDh`). **Design only: nothing is built yet.** Route: the session
manager (`session_01CXLi5mGX2sg5QS11gg3Wrh`), then the Design reviewer for the mockups, then Jason once, written in chat for
him and Gemini (0179). **Built on Sync 2026-11-25 02:02 UTC (b1336ec).** Mockup: `piece2-team/mockup.html`, screens
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
   session manager's ruling: "Permissions come only from Access::can()".)
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
- **Group rows by department** (table-standard §8). The 25 standby roles sit in one folded group, "On standby (25)", so the
  day-to-day view stays short (spec §3, "Quiet by default").
- **Filter button:** Department, Kind and Status, with counts.
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
| Architect / Builder (active) | `handoff` | Building tickets with a scope and no session | writes the build brief and marks it "With the session manager" (via `report()`) | takes the brief back, logged |
| Auditor: Ops & Reconciliation (active) | `audit` | §6's sources | opens or updates Audit tickets | withdraws the ticket with "undone" |
| Content, Directory curator, Orders/Finance (active) | none yet | | listed with "no steps yet" until a step is written for them. Orders/Finance will only ever read (money is Tier 3) | |
| Advocates (active, views only) | none | | bound to test accounts for piece 3's before-and-after check, and never act (finding 6) | |

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

**Notifications** go only to Jason, only for a Resolution ticket that reaches Waiting for you. That's one thread per
ticket, `Notify::send` with `thing = task:<id>` (0176 A1), and one push (0172 push-polish). Work and Audit tickets never
notify anyone (spec §3, "Quiet by default"). On staging the gate holds the notification anyway, so on live this happens
only once the tickets are records (Question 1).

## 7. The roster at the start (the session manager's ruling; Jason's 33 in §11 of his spec)

| Spec # | Role | Department | Kind | Status | Acts as |
|---|---|---|---|---|---|
| 1 | Executive Lead & Founder | leadership | founder | active | the real login, never an agent |
| 2 | Executive Translator & Briefing Lead | leadership | operator | **active** | staff · ops |
| 6 | Project Manager | operations | operator | **active** | manager · ops |
| 15 + 21 | Architect / Builder (System Architect and Full-Stack Builder in one, until the work splits them) | build | operator | **active** | staff · eng |
| 22 | Technical QA & Security Gatekeeper | build | auditor | **active** | staff · eng |
| 19 | Content & Copy Editor | build | operator | **active** | staff · con |
| 14 | Regional Directory & Data Curator | operations | operator | **active** | staff · con |
| 10 + 11 | Orders / Finance (Order & Fulfillment and Finance, Tax & Billing, reading only) | operations | operator | **active** | staff · fin |
| 8 | Operations & Reconciliation Manager (runs the audit) | operations | auditor | **active** | manager · ops |
| 23–33 | the 11 Advocates, each bound to its test account or `signed_out` | advocates | advocate | **active** | views only |
| 3, 4, 5, 7, 9, 12, 13, 16, 17, 18, 20 | the other roles | as in the spec | keeper / operator | standby | set when made active |

**Question for the session manager:** the ruling counts about 8 working roles. I've added Ops & Reconciliation as the
audit's owner, because spec §9 names it. If that should be QA alone, it becomes standby.

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

## 9. Questions for Jason (through the session manager, written for him and Gemini)

1. **Decision (nothing changes on the site yet): where the agents' work lives.** The Work queue's tables are each site's own,
   so staging can't see live's tasks (finding 1).
   - **A, recommended:** make the queue's tables `records`. Live's tasks come to staging by catch-up. What agents do on
     staging reaches live only at Publish, inside your approved batch.
     - Pros: one queue; agents stay on staging; you approve what they did like any release.
     - Cons: a task changed on both sides at once goes on staging's conflicts list (0165 §3). It changes the class recorded
       in `work-queue.md` and `task-scope.md` (a note goes on each).
   - **B:** keep them separate, and agents work staging's own queue.
     - Pros: nothing changes in how staging copies.
     - Cons: two queues, and you'd add tasks on staging to have them worked (against "one copy").
   - **Impact if delayed:** releases 1–2 don't depend on it; release 3 (the runner) waits for it.
2. **Decision: Confirmation as a stage.** Today a task is Done the moment its release is live (#281, `work-queue.md` rule
   5). Your four stages put Confirmation after that.
   - **Recommended:** a task reaches Confirmation when its release is live, and it's Done when its steps' checks pass.
   - A task with no steps is Done at once, as today.
3. **Decision: where you answer a Resolution ticket.** **Recommended:** on the ticket's page on live (Choose A / B / your
   words), with one push, listed by the session manager under "Waiting for your OK", never in a pop-up (0179).
4. **Decision: AI for agents.** It stays off (releases 1–5 cost nothing). **Release 6** would use one AI feature for every
   agent's wording. It would be the held phase-2 design's `task_assist` (`work-queue-phase2.md`), Claude only (input class
   `person`, so Gemini is refused until your 0164 §0.8 ruling), inside the $40 cap, and only while `staging_ai` is on.

## 10. Releases, in order (each one coherent, each through the Design reviewer, the session manager and the daily batch)

| # | Release | What it holds | Where it's seen |
|---|---|---|---|
| P2-1 | `team-tickets.zip` | ticket type, stage, tier, moves and briefs (columns, 2 tables, 2 migrations: schema, then the stage seed from today's states: fixing → Implementation, taken with a scope → Building, the rest → Planning); the stage strip and gates; the Resolution brief filled by hand; the filter's Type and Stage | live, admin only |
| P2-2 | `team-roster.zip` | `team_roster` + its seed (2 migrations), `config/team` (manifest, tool, drawer, status), `Access::canAs()`, the Team icon on the queue | live, admin only |
| P2-3 | `team-runner.zip` | `team_tick`, `team_runs`, the switch `team_runner` (off), the Project manager's, QA's, Translator's and Builder's steps, Undo, runs on a ticket's page (staging) | staging only (live: nothing runs) |
| P2-4 | `team-audit.zip` | `team_audit_tick`, its sources, diagnose twice, the tier rules, Audit → Work / Resolution, the precedent ticket, Run the audit now | staging only |
| P2-5 | `team-batch.zip` | the daily batch strip and Approve the batch (`approve` kind `batch`, through `report()`) | live, Jason |
| P2-6 | `team-ai.zip` (only if Question 4 is yes) | `task_assist` for the Translator's and Content's wording | staging only |

P2-3 waits for Question 1. Each release adds its FAQ entries (decisions/0057), its staging classes (R14) and claims
(`checkout.py`).

**Groundwork for piece 3, in these releases:**
- each run's `touches` (the pages and records it changed);
- Undo on every run;
- a run's AI cost per role, shown but not yet enforced (piece 3's spend limit per agent).

**Not built here:** the before-and-after check, and rule 2's automatic publishing (0179).

## 11. Tests (`tools/team-tests/`, on the MariaDB bench with the mirror and each release over it)

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
  - the seed gives 33 rows, 10 of them active plus the Founder, run twice;
  - `canAs` against `Access::can` for every level and department;
  - an advocate has no `acts_*`;
  - Edit with History; standby and back.
- `roster.cjs`: the Team page and its drawer at 1280 and 390, the standby group folded, the Team icon opening it in the
  panel.
- `runner.php`:
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

## 12. The admin's test (one line per new state)

- **A row shows "Building · release not named yet".** Jason sees where it is and what's missing. He presses its name, then
  **Name its release**, in the panel.
- **A row shows "Waiting for you" (amber).** A Tier 3 ticket needs him. He opens it, reads the brief, and presses Choose A
  or Choose B.
- **The Team page's "On standby (25)" group.** Those roles exist but don't run. He can open one and press **Make active**,
  which works only once it has a step (greyed with that reason until then).
- **On staging, a run line reads "Project manager · moved to Confirmation · its release is live".** The system did it. He
  can press Undo if it's wrong.
- **"Today's batch: 4 waiting".** He reads each line and presses **Approve the batch**.
