# 0171: The Control Board: one control room for the whole system

Status: proposed (2026-11-18), waiting for Jason's OK through the session manager. Nothing is built before it.
Amends: 0163 §10 (switching back on), and its notes of 2026-11-15 (Start the system) and 2026-11-16 (the pre-flight).
Builds on: 0109 (the admin: manifests and widgets), 0163 §1–§9 (the service register, costs, warnings, switches), 0164
(the Knowledge Engine and its workflows W0–W14), 0165 (staging in safe mode), 0160 (the crawler as one service).
Hand-off: `handoffs/control-board.md`. Checked against the live mirror at "Sync: 2026-11-18 02:36 UTC".

## What Jason asked for

Jason, 2026-11-18:
- "I see START as PREFLIGHT, ACTIVATION, MONITORING, REPORTING for the entire system (each step accessible and able to
  see what is doing and the data its generating)."
- "In powerplant management, the site is under operations management with a control room where all of the plants
  information is monitored and controlled. The control board is where an operator can see every sensor and status of
  the entire power plant operation so I want our system to have a very similar system."
- "neither of them, especially START, does not include endpoints or a way to see what data is being captured and or
  stored."

## What exists (checked in the live code)

Most of what a control room needs already exists. It is spread over several pages, and nothing records what data comes
in or where it is stored.

- **Pre-flight** (`app/lib/Preflight.php`, https://traversence.com/admin/config/preflight). Its line groups are:
  the site, outside services, W0 and the crawler on Railway, query timings (`TIMINGS`), the source map (`NEEDS`), the
  site's needs, workflows, tasks, phases (`PHASES`) and the switch-on check. Results are kept in `preflight_results` and
  `preflight_runs`, which are staging's own tables. It runs nightly (`preflight_tick`), and its red lines become one
  Pulse case.
- **Start the system** (`app/lib/SystemStart.php`). Its steps are: the keys, watching, the switch-on check, confirming
  every workflow and task, Railway's schedule, the master switch, then W0–W12 and the tasks in order, each watched by
  itself.
  - Its state is one row in `search_index_state` (`ops:start`), and past sessions are kept under `start:sessions`.
  - It is carried on by `system_start_tick`.
  - The same card appears on Automation (https://traversence.com/admin/config/automation#start) and on the Workflow
    board (https://traversence.com/admin/config/board).
- **The switches** (`app/lib/Automation.php`) have four levels: master, workflow (`WORKFLOWS`, W0–W14), task and
  service. The site's jobs per workflow are in `JOBS`, and every change is written to `automation_log`.
- **Services** (`Operations.php`): the service register (`register()`: keys, caps, use, cost, status), the warnings at
  50/75/95% (`LEVELS`), "returns empty", watching Railway and the sync, and `switchOnCheck()` / `blocking()`.
  `ServiceHealth.php` covers issues, holds and probes, with one Pulse case per issue.
- **The crawler's runs** (`crawler/CrawlService.php`):
  - `crawl_runs`: one row per run;
  - `crawl_run_items`: per task, its outcome, seconds, fetches and `counts`;
  - `crawl_service_calls`: per host, kept 90 days;
  - `crawl_costs`.
- **The endpoints**: 21 under `website/api/crawl/` plus `_auth.php`: commoncrawl, geocode, guides, heritage, identity,
  index, irs, jobs, landmarks, learn, loads, npi, places, rec, refresh, results, service, sources, targets, usage,
  verify. The video worker has its own `website/api/video/`. **None of them records what it received, stored or
  refused, or which records it touched.**
- **The health report** (`website/api/health.php`, `HEALTH_TOKEN`) gives totals only, never anything about a person.
  **The daily report** (`DailyReport.php`) is emailed at 5 am Arizona time to the Founder.

Four gaps, each confirmed in the code:
1. Start's confirm step calls `Preflight::run()` for each workflow line. `Preflight::workflow()` then reads the stored
   `nd:` results (the source map) rather than testing them. A need that is stale or has never run therefore doesn't
   hold the workflow.
2. The confirm step doesn't run the four query timings.
3. `SystemStart.php`'s header comment (items 5–6) still says "one at a time … each starting only when the one before
   has run cleanly". Since release start-confirm, the code confirms everything first and then switches units on in
   order without waiting on runs.
4. No code says which endpoints and tables belong to which workflow. Each page puts that picture together its own way.

## Decision

The **Control Board** is the system's control room: one place to see every unit and sensor, control them in place,
start the system, hear its alarms, read its log and get its reports. The pre-flight and Start the system become two of
its parts:
- **the pre-flight** becomes its interlocks;
- **Start the system** becomes its startup sequence.

Their libraries stay and are read by the board. Nothing that works is rebuilt.

### 1. The board (the mimic)

One diagram of the whole flow, read left to right:

**sources** (our copies first, then the outside services) → **workers** (W0 Indexer, the crawler, the video worker, on
Railway) → **the site's endpoints** (`/api/crawl/*`, `/api/video/*`) → **tables** → **where it ends up** (Review,
published, held or refused).

- Each box shows its live numbers and is lit by its state:
  - **green**: running and within limits;
  - **amber**: on a backup, near a limit, or held for one part;
  - **red**: tripped, stopped or not ready;
  - **grey**: off by a switch. Paused is never shown as a failure (0163 §9).
- A line between boxes shows what passed along it in the last hour.
- **Tapping a box opens its unit** in a drawer (§2), never on another page.
- **On a phone** the board is drawn as one column, top to bottom, with the same colours and the same taps. No sideways
  scroll at 390px.

### 2. Units and gauges

- **A unit** is anything that can be on, off or broken: a workflow (W0–W14), a task (`SystemStart::TASKS`, and the
  crawler's capture tasks), a worker, an outside service, a site job (`Scheduler::JOBS`), an endpoint or a table.
- **A gauge** is one reading of a unit: its value, its limit, its state and when it was last read.
  - Every pre-flight line is a gauge under its existing id (`site:db`, `svc:gemini`, `nd:places`, `wf:W1`, `ms:radius`).
    New gauges follow the same pattern (`ep:results`, `tb:entities`, `wk:w0-indexer`, `job:listings_tick`).
  - Limits come from the settings the code already reads: Thresholds & gates and the service register's caps. They are
    never written into the board's code.
- **A unit's drawer** holds:
  - its gauges;
  - its controls (§2a);
  - its last runs;
  - the data behind it: the ledger rows for its endpoints, the records it touched (each opens in a peek, under that
    page's own access) and its tables' sizes;
  - its trend (§5) and its log entries (§7).

**2a. Controls at the board.** Each control acts in place and calls what exists:
- Start the system, Pause everything;
- pause or resume a workflow, task or service (`Automation`);
- run its check (`Preflight::run` for its lines);
- check it now and mark resolved (`ServiceHealth`).

Every control shows a spinner, gives its answer beside the button and stays where it was pressed. A control the
viewer can't use is greyed with the reason. Every action has its way back on the same drawer.

**2b. Interlocks.** The pre-flight's lines are the interlocks.
- A unit's Start or Resume is open only when its needs are green (`Preflight::workflow`, `Operations::blocking`).
  Otherwise the button names the red line and carries its fix.
- A unit that trips stops by itself; the rest carry on (0163 §10.2's note).

**2c. The startup sequence.** Start the system keeps its steps and order. The board shows each unit coming on in its
place in the diagram, with the step it is on.

### 3. The register (in code)

The unit register is one file, `app/lib/ControlRegister.php`. For each unit it names:
- its site jobs;
- its crawler tasks;
- its services;
- its endpoints;
- its tables;
- what it feeds.

It is called "the unit register" so it isn't confused with the service register (`Operations::register`).

- **What is already declared is read, not copied:** `Automation::WORKFLOWS` and `JOBS`, `SystemStart::ORDER` and
  `TASKS`, `Preflight::NEEDS` and `PHASES`, the service register, `CrawlService::tasks()`, and `StagingTables` for each
  table's class.
- **Only what nothing declares today is added:** each endpoint's unit and each table's unit.
  - Endpoints and tables that no unit claims are shown in the register's own "unclaimed" list.
  - The zip check gets a rule: a new `/api/crawl/` or `/api/video/` endpoint must name its unit.
- **The board, Start and the pre-flight all read it,** so a new workflow, task, endpoint or table appears on the board
  by itself.

### 4. The sensors and the ledger

Every call to a worker's endpoint, and every run, records what it received, stored and refused.

- **`control_ledger`**: one row per call. It holds:
  - when it happened, the endpoint and its unit;
  - the crawler run (`crawl_runs.id`), when there is one;
  - counts of what was received, stored, updated and refused, with the reasons for refusals as counts by reason;
  - how long the call took and its answer code.

  It is written by one helper, which is called at the end of each endpoint (next to `_auth.php`).
- **`control_records`**: the ids of the records each call or run touched. It holds the kind, the id and what happened
  (stored, updated, refused, sent to Review).
  - Each call keeps at most `control_records_per_call` ids (default 500); beyond that the count is kept.
  - The worker reports the ids it touched in its run report (`CrawlService::report`), so **each crawler run opens to
    the records it touched**. This takes in that queue item from `handoffs/session-manager.md`.
- **Ids and counts only.** The ledger never stores a person's name, email, phone or a page's text. A record's content
  stays in its own table, behind its own page's access.
- **Kept** for `control_ledger_days` (default 90) and `control_records_days` (default 30), which are Thresholds
  settings. Older rows are deleted in small batches, the same way as `crawl_service_calls`.
- **New tables are add-only and their class is named in `app/lib/staging-tables.php` (R14).** All of them are
  `follows`, like `crawl_runs` and `automation_log`, because the system runs on live only (Start refuses on staging).
  Staging shows live's picture, and its controls are greyed with the reason.

### 5. The historian

Each gauge's readings are kept over time, so every unit has a trend.

- **`control_readings`**: a gauge's value and state.
  - It is written by `control_tick` (a new job in `Scheduler::JOBS` and `AUTO`, every 10 minutes) when a value changes,
    and at least once an hour.
  - Raw rows are kept `control_readings_days` (default 30). After that, one row per gauge per day (min, max, last, and
    the time spent red or amber) is kept 400 days.
- **The trend for each unit** covers runs, records in, stored and refused, spend against its cap, and speed (seconds
  per item, query timings).
  - It is drawn as a small inline SVG with no library and no outside call.
  - It shows the last 24 hours, 7 days or 30 days.

### 6. The annunciator (alarms)

One alarm list for the whole system, by level.

| Level | When | Examples |
|---|---|---|
| **Trip** | A unit stopped, or can't run what's switched on | Start's watch stopped a unit; a ban or refused key holds a service a running unit needs; out of budget; a failed database update holding the rest; W0 not checking in; the crawler stalled |
| **Warning** | A gauge past its warning limit | a cap at 50/75/95% (`Operations::LEVELS`); "returns empty"; a back-off past its case limit; a red pre-flight line for a unit that isn't on; a query slower than its limit |
| **Notice** | Worth knowing; nothing to do | a unit came on; a need running on a backup; a trip that cleared by itself |

- **Every alarm says** what happened, what to do, and its fix as a button in place (or a drawer for another module's
  tool), never a link to another page.
- **Kept in `control_alarms`**: when it was raised, its unit and gauge, its level, its words, its fix, who acknowledged
  it and when (with a note), and when it cleared.
- **Acknowledging** means "I've seen it". It stops the phone repeating it. The alarm stays listed until its condition
  reads green.
- **Clearing** happens by itself, when the gauge behind the alarm reads green at the next look. A trip that cleared
  before anyone acknowledged it stays listed as "cleared, not seen" until it is acknowledged or 24 hours pass.
- **To Jason's phone**, at no cost:
  - each new trip is emailed to the Founder at once (the daily report's recipient; system emails are free). So is a
    warning still unacknowledged after 24 hours;
  - `/api/health.php` gains an `alarms` block (open trips and warnings: the unit, the level, the words and since when;
    totals only, nothing about a person). The session manager reads it on its hourly check, sends a push notification
    for each new trip, and lists the open ones in its reports. **One step for Jason:** `HEALTH_TOKEN` saved as a network
    secret for traversence.com on the session manager's environment. The session manager asks for it with its secure
    form.
- **No Pulse cases for approvals** (Jason, 2026-11-17: Pulse carries notices, not approvals). Today these Pulse cases
  exist:
  - the pre-flight's case;
  - "Starting the system";
  - the service issue cases (`svc:<id>`);
  - the cap warnings (`ops:cap:<service>`);
  - "returns empty" (`ops:empty`).

  They become alarms on the board. Pulse keeps one notice, "Control board: N trips, M warnings", which opens the board
  in a drawer. This amends ServiceHealth's "one Pulse case per issue" (2026-11-14): the issue, its evidence, "Check it
  now" and "Mark resolved" move onto its alarm.

### 7. The logbook

Every switch, alarm and acknowledgement, with who and why, newest first, filtered by unit.

- **Read where it is already kept:**
  - `automation_log` (switches);
  - Start's sessions (`start:sessions`);
  - `preflight_runs`;
  - service issues opened and resolved;
  - releases going live.
- **New, in `control_log`:** alarms raised, acknowledged and cleared; each control pressed on the board; and an
  operator's own note ("Log an entry", with spell check).
- **Times** are shown in the zone where they happened, always labelled ("Nov 18, 10:00 AM MST"), as `js/tv-time.js`
  does.

### 8. The shift reports

- **Daily** (at 5 am Arizona time, with the daily report) and **weekly** (Monday). Each covers the whole system and
  each unit: what ran, records in, stored and refused, spend against caps, alarms raised, acknowledged and cleared,
  switches changed, and the trends.
- **Each opens as a page** that reads well on a phone, with **Download** for a CSV and the page as a file. Both work on
  a phone.
- **A short summary** (three to five lines) goes in the daily report's System status section and in the `alarms` block
  of `/api/health.php`, for the morning brief and the session manager's reports.

### 9. Where it lives in the admin (0109)

- **A new section, System Configuration → Control board**, first in that module's menu:
  https://traversence.com/admin/config/control, with anchors `#board`, `#alarms`, `#start`, `#preflight`, `#log` and
  `#reports`. A unit's drawer is `/admin/config/control/<unit>` (the section's item route).
- **Built from the manifests and widgets:** a `mimic` widget is added to `app/admin/widgets.php`. The gauges use
  `metrics`. The alarms, logbook and ledger are `table` tools in `app/admin/tools.php` (`config/alarms`, `config/log`,
  `config/ledger`). The unit is the detail drawer. Each `h2.hb-h` part folds.
- **Dashboard → Today** gets one status line for the board (green, or "2 trips, 1 warning"), which opens it.

### 10. Access

- **Viewing the board, acknowledging and logging an entry:** executive and above, the same as the pre-flight and
  Automation (`Access::can('config.control', ['min' => 'executive'])`).
- **Each control keeps its own permission:** Start, pause and resume use `config.automation`, and checks use
  `config.preflight`. A control without permission is greyed with the reason.
- **The records a unit touched** open under their own pages' access. The ledger itself holds only ids and counts.
- **The `alarms` block in the health report** needs `HEALTH_TOKEN`, as the rest of that report does.

### 11. What it replaces or archives

Each page stays reachable until the board's part replaces it. It is then archived (`archive.md`, out of the menu,
still reachable), never deleted.

| Today | Becomes | When |
|---|---|---|
| Pre-flight page (`config/preflight`) | The board's interlocks (`#preflight`) and each unit's checks; `Preflight.php` stays | Release 2 |
| Start card on Automation and on the Workflow board | The board's startup sequence (`#start`); both pages link to it | Release 2 |
| The switch-on check on the Workflow board | The board's interlocks; the Workflow board keeps each workflow's settings and versions | Release 2 |
| Pulse cases: Pre-flight, Starting the system, `svc:<id>`, `ops:cap:<service>`, `ops:empty` | Alarms (§6); one Pulse notice | Release 3 |
| Insights → Telemetry (coming) | The historian's trends; the card points to the board | Release 3 |

Automation keeps its list of switches (the board's controls call the same code). The service register, Costs and
Outside services stay as they are; the board links to them in drawers.

### 12. The four releases, in order

Each release is one coherent zip, checked on a phone at 390px, with its new buttons pressed once, sent with `send.py`
and approved by Jason through the session manager.

1. **The register and the three pre-flight fixes.**
   - `ControlRegister.php`, read by Start and the pre-flight.
   - Start's confirm step first re-tests the source-map lines (`nd:`) its workflows depend on. It also re-tests their
     service lines (`svc:`) when they are older than `pf_fresh_mins` (default 60) or have never run, inside the step's
     existing time budget and carried on at the next look.
   - The confirm step runs the four timings (`TIMINGS`). A slow one is noted and holds nothing; a failed query is red.
   - `SystemStart.php`'s header comment is corrected.
   - No new tables.
2. **The sensors, the ledger and the board's live view.**
   - `control_ledger` and `control_records`; the endpoints' helper; the worker reports the ids it touched.
   - The section, the mimic, the units and gauges, their drawers and controls, the interlocks and the startup sequence.
   - The Pre-flight page is archived; the Start card and the switch-on check move to the board.
3. **The historian and the annunciator.**
   - `control_readings`, `control_alarms` and `control_tick`; the trends.
   - The alarm levels, acknowledgement and clearing; the email and the health report's `alarms` block.
   - The five Pulse cases become alarms; Telemetry points to the board.
4. **The logbook and the shift reports.**
   - `control_log`, the merged logbook and "Log an entry".
   - The daily and weekly reports with their downloads; the summary in the daily report and the health report.

## What this doesn't do

- It doesn't change how any workflow gathers, or any rule in 0164.
- It doesn't change Railway's cron or restart policy (the site sets them, as today).
- It doesn't spend money: no text messages and no paid push service.
- It doesn't move data between databases.

## Consequences

- **One place to look:**
  - what is on, what is wrong and what to do (the board and its alarms);
  - what came in and where it went (the ledger and its records);
  - how it has gone (the historian, the log and the reports).
- **Start and the pre-flight stop drifting apart:** both read the register, and Start re-tests what it relies on.
- **Pulse gets lighter:** five kinds of case become alarms, and Pulse keeps notices.
- **Five new tables, all add-only.** Each has its kept-days settings, so they stay small.
