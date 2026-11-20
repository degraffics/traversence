# 0171: The System Control Board: start-to-finish management and control of the whole system

Status: accepted (Jason, 2026-11-18, revision 2, approved with the decision pop-up). **Built (2026-11-19):** all five
releases are live: 1 (#120), 2a (#121, fixed #124), 2b (#127, layout #128), 2c (#129), fixes #131–#134, 3a (#137),
3b (#139, #140), 3c (#143), 3d (#144), 3e (#145), 3f (#148), 4a (#151), 4b (#154), 4c (#156), 4d (#157), 4e (#160), 5a
(#166), the old trips settled (#168), a back-off in its wait isn't a hold (#169) and 5b, the reports (#173); then the links and Resolve fixes (#175) and the off-region sources (#177). §12's process
pages and four tables: control-board-12 (2026-11-19, the session manager's call for Jason). Kept as they are (decided 2026-11-19): the Pre-flight page, available as the pre-start check, and §2's layout. Progress and what's left: `handoffs/control-board.md`.
Amends: 0163 §10 (switching back on) and its notes on Start the system and the pre-flight. Also amends ServiceHealth's
"one Pulse case per issue" (2026-11-14) and the Pulse cases listed in §8.
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

His answer to the first draft (revision 1, the same day), verbatim:

> "The system control board: start to finish system management and control. Organized by how an operator can see and
> understand each system and everything within its process. Each gauge holds the entire process for whatever system
> feature it represents along with the ability manage that feature for performance and reporting. System Board must
> oversee Preflight, Start-up, Observation & reporting, shut-down, maintenance, historical data at system level as well
> as individual process. The build must address: 1. real time monitoring, so any trip or issue (slow, retry, crash)
> produces an alert. Alerts are up to 1. system notifications, 2. in a pulse for operator review and action steps to
> address it 3. push it from the hourly check, which needs a site health token saved through my secure form (not sure
> what this means (free); 3. Complete routing for each process and must be fast, efficient, and accurate for the board
> and the system to be stable and operational, down time is bad, so we ensure we can handle and manage our system well
> to ensure it is healthy always. […] The Pulse case needs to be address because I am not finding them helpful either."

**What revision 2 changes:**
- the board is organized by system, then process, and each gauge holds its whole process;
- six stages, at system level and per process: shut-down and maintenance are new;
- real-time monitoring of slow, retry and crash, saying how each is detected and how fast;
- alerts go three ways;
- the health token is explained in plain words;
- Pulse is fixed: one item per alarm, actions in place, and it closes itself;
- every process is routed end to end and kept fast, efficient and accurate;
- five releases instead of four.

## What exists (checked in the live code)

Most of the parts exist. They are spread over several pages, and nothing records what data comes in or where it is
stored.

- **Pre-flight** (`app/lib/Preflight.php`, https://traversence.com/admin/config/preflight). Its line groups are: the
  site, outside services, W0 and the crawler on Railway, query timings (`TIMINGS`), the source map (`NEEDS`), the
  site's needs, workflows, tasks, phases (`PHASES`) and the switch-on check. Results are kept in `preflight_results` and
  `preflight_runs`. It runs nightly (`preflight_tick`), and its red lines become one Pulse case.
- **Start the system** (`app/lib/SystemStart.php`). Its steps are: the keys, watching, the switch-on check, confirming
  every workflow and task, Railway's schedule, the master switch, then W0–W12 and the tasks in order, each watched by
  itself (`gate()`, `runs()`). Its state is kept in `search_index_state` (`ops:start`, `start:sessions`), it is carried
  on by `system_start_tick`, and its card appears on Automation and on the Workflow board.
- **The only stop is Pause everything** (`SystemStart::pauseAll`): the master switch off, and Railway's schedule
  removed. There is no orderly per-system shut-down, and no maintenance state. A switch is either on or paused.
- **The switches** (`app/lib/Automation.php`) have four levels: master, workflow (`WORKFLOWS`, W0–W14), task and
  service. The site's jobs per workflow are in `JOBS`, and every change is written to `automation_log`.
- **The site's jobs** (`Scheduler.php`). Each run is kept in `job_runs` for 14 days: when it started and finished,
  whether it was ok, and its note. A job that stops with a fatal error is recorded as "Stopped".
- **Services:**
  - `Operations.php` holds the service register, the warnings at 50/75/95% (`LEVELS`), "returns empty", watching
    Railway and the sync, and `switchOnCheck()` / `blocking()`.
  - `ServiceHealth.php` classifies each failure (`KINDS`). It backs off from 10 minutes up to 6 hours, holds the service
    after a ban, a refused key or a used-up quota, and runs probes. It opens one Pulse case per issue.
- **The crawler's runs** (`crawler/CrawlService.php`):
  - `crawl_runs`: a run that never reports is marked "abandoned" after 40 minutes;
  - `crawl_run_items`: per task, its outcome, seconds, fetches and counts;
  - `crawl_service_calls`: calls and failures per host;
  - `crawl_costs`.
  - The monthly bulk loads run only in the night window (`NIGHT`, 1–5 am Arizona time).
- **The endpoints**: 21 under `website/api/crawl/` plus `_auth.php`, and the video worker's `website/api/video/`.
  **None of them records what it received, stored or refused, or which records it touched.**
- **Pulse** (`Pulse.php`):
  - items with a severity (`SEVERITY`: critical, alert, update, event) and a clock (`SLA_HOURS`);
  - done and undo, assign, escalate and notes (`mark()`);
  - steps that act in place (`manage()`).
- **The health report** (`website/api/health.php`, behind `HEALTH_TOKEN`) gives totals only.
- **The daily report** (`DailyReport.php`) is emailed at 5 am Arizona time to the Founder.

Four gaps, each confirmed in the code:
1. Start's confirm step calls `Preflight::run()` for each workflow. `Preflight::workflow()` then reads the stored `nd:`
   results (the source map) rather than testing them, so a stale or never-run need doesn't hold the workflow.
2. The confirm step doesn't run the four query timings.
3. `SystemStart.php`'s header comment (items 5–6) still describes the old one-at-a-time pacing.
4. No code says which endpoints and tables belong to which process, so no page can show a process end to end.

## Decision

The **System Control Board** is the control room for the whole system, from start to finish:
- **Organized by system, then process.** Each gauge holds the entire process it stands for.
- **One lifecycle**, at system level and for each process: pre-flight, start-up, observation and reporting, shut-down,
  maintenance, and history.
- **Monitoring in real time**: every trip or issue raises an alarm, and every alarm reaches the operator three ways.
- The pre-flight and Start the system become two of its stages. Their libraries stay and are read by the board.
  Nothing that works is rebuilt.

### 1. Systems and processes: how the operator sees it

The board is drawn the way an operator reads a plant: **systems**, and within each system its **processes**.

| System | Its processes (each one gauge) |
|---|---|
| **The Knowledge Engine** | W0 Indexer, W1–W14 (`Automation::WORKFLOWS`), and the tasks under them (`SystemStart::TASKS`, the crawler's capture tasks) |
| **Workers** | W0 Indexer, the crawler and the video worker on Railway |
| **Outside services** | Each company in the service register (`Operations::register`) |
| **The site** | The databases, `.env`, database updates, staging, the sync, and the site's own jobs (`Scheduler::JOBS`: billing, releases, email, Moments…) |

- **The systems come from the register (§3), not a hand list,** so a new workflow, worker, service or job appears by
  itself in its system.
- **The board's first view** shows each system as one row. Its light is the worst of its processes. It shows its
  numbers and its stage (§2).
- **Tapping a system** opens its processes as gauges.
- **On a phone**, the systems stack in one column. A system opens in place, a process opens in a drawer, and nothing
  scrolls sideways at 390px.

**A gauge holds the whole process.** Opened, it shows, top to bottom:
1. **Its route**: sources → worker or job → endpoint → tables → where it ends up (Review, published, held, refused).
   Each hop shows its state and what passed along it (§9).
2. **Its readings**: each value against its limit, and when it was read. The pre-flight lines keep their ids
   (`nd:places`, `svc:gemini`, `wf:W1`, `ms:radius`); new readings follow the same pattern (`ep:results`,
   `tb:entities`, `wk:w0-indexer`, `job:listings_tick`).
3. **Its stage and controls** (§2): run its check, start, shut down, take out for maintenance, put back.
4. **Its data**:
   - its last runs;
   - the ledger rows for its endpoints;
   - the records it touched (each opens in a peek, under that page's own access);
   - its tables' sizes.
5. **Its performance**: speed, throughput, refusals, retries, spend against its cap, and how long it has been up.
6. **Its alarms, log and reports**: the alarms open on it (§7), its log entries (§10), its trend (§6) and its report
   (§11).

**Lights:**
- **green**: running and within limits;
- **amber**: on a backup, near a limit, slow or retrying;
- **red**: tripped, crashed or not ready;
- **grey**: off by a switch;
- **blue**: in maintenance.

Off and in maintenance are never shown as failures (0163 §9).

**Limits** come from the settings the code already reads (Thresholds & gates, the service register's caps). They are
never written into the board's code.

### 2. The six stages, for the system and for each process

Each system and each process shows the stage it is in, and its controls for that stage. The same control works on the
whole system or on one process.

1. **Pre-flight** (the interlocks). The pre-flight's lines, read live.
   - A process can start only when its needs are green (`Preflight::workflow`, `Operations::blocking`).
   - Otherwise its Start button names the red line and carries its fix.
2. **Start-up** (Start the system). The same steps and order as today. On the board, each process comes on in its
   place with the step it is on. Start-up also works for one process: its checks, then on, then watched.
3. **Observation and reporting.** The process is running and watched live (§5). Its readings feed the historian, its
   alarms and its reports.
4. **Shut-down (new).** An orderly stop, for one process or the whole system.
   - **The order is the start-up order reversed:** the tasks first, then the workflow, then its worker's schedule.
   - **What is running finishes or is handed back unchanged:**
     - a crawler run ends at its next task;
     - `crawl_requests` leases go back, as `CrawlService` already does;
     - a site job skips its next run.
   - **The board shows "stopping"** until nothing is in flight, then **"stopped, safe"**. That state lists what it left:
     queues kept, nothing half-written, and the switch with who and why.
   - **Before it stops, it names what depends on it.** For example, "W2 rechecks what W1 publishes". Stopping is never
     refused.
   - **Pause everything stays as the emergency stop**: everything at once, as today.
5. **Maintenance (new).** A process is taken out of service on purpose, with a reason and a planned end ("Back by").
   - **While it is out:**
     - its alarms become notices;
     - Start skips it and says why;
     - it shows blue.
   - **Put back** runs its pre-flight lines first, then starts it.
   - **The upkeep calendar** lists what is due, from what the code already schedules:
     - the night window's bulk loads (`CrawlService::NIGHT`);
     - the monthly loads (IRS, NPI, Recreation.gov, landmarks);
     - Data cleanup's daily pass;
     - the probes due (`ServiceHealth::dueProbes`);
     - the nightly pre-flight;
     - staging's nightly pass.
   - **A maintenance window** can be set ahead. The process goes out and comes back by itself, and both are logged.
   - Kept in `control_maintenance`: the process, from, until, why, who, and when it was put back.
6. **History.** Every reading, alarm, stage change and control, kept over time (§6, §10), for the system and for each
   process.


> **Note (2026-11-19, control-board-table): the parts in the admin's one table.** Per the table standard Jason approved
> (`design/table-standard.md`, PR #1044, rule 6: "Other tables open the record's detail" in a drawer; CLAUDE.md: another
> module's tools open in a drawer), the board's process rows move onto `tv_table()`: the four systems are group rows; each
> part's row has its status, stage, where it stands, last 24 hours, speed and OK, **Check**, and **⋯** with words (a greyed
> item says why). A part's name opens its own page (§12) in a drawer, instead of opening in place under itself. The
> counts in each system's header (the filter release, #180) become the table's one **Show** filter (CLAUDE.md's filter
> rule); the board's own "Find a process" box and Open all / Close all give way to the table's search. The site's needs
> and each system's trend are panels of their own. A part's own page keeps its row opened in place.
> *Later (Jason, 2026-11-19, control-board-itsdata): "thought I could click listed items on the right to filter the rows?" The counts
> on each system's line are links again, as they were on the cards: a count picks its status in **Show** and opens that
> system; pressed again, every part shows (`admin-control.js`, beside the table component). With it, by the playbook's §3
> rule (a rebuilt screen keeps everything the old one did), the rest #192 had lost: the systems you opened stay open
> through the 30-second redraw, **Open every system** / **Close them** under the table, an alarm's **Holds:** link opens
> the part's own page, and the search finds a part by its sources' names. Tested in the rendered board
> (`board-rendered.cjs`).*

### 3. The register (in code)

The unit register is one file, `app/lib/ControlRegister.php`. It is called "the unit register" so it isn't confused
with the service register.

For each system and process it names:
- its site jobs;
- its crawler tasks;
- its services;
- its endpoints;
- its tables;
- what it feeds;
- its route (§9).

- **What is already declared is read, not copied:** `Automation::WORKFLOWS` and `JOBS`, `SystemStart::ORDER` and
  `TASKS`, `Preflight::NEEDS` and `PHASES`, the service register, `Scheduler::JOBS`, `CrawlService::tasks()`, and
  `StagingTables`.
- **Only what nothing declares today is added:** each endpoint's process and each table's process.
  - Anything no process claims is listed as **unclaimed**.
  - The zip check gets a rule: a new `/api/crawl/` or `/api/video/` endpoint must name its process.

### 4. The sensors and the ledger

Every call to a worker's endpoint, and every run, records what it received, stored and refused.

- **`control_ledger`**: one row per call. It holds:
  - when it happened, the endpoint and its process;
  - the crawler run (`crawl_runs.id`), when there is one;
  - counts of what was received, stored, updated and refused, with the reasons for refusals as counts by reason;
  - the retries the worker made;
  - how long the call took and its answer code.

  It is written by one helper, called at the end of each endpoint (next to `_auth.php`).
- **`control_records`**: the ids of the records each call or run touched. It holds the kind, the id and what happened
  (stored, updated, refused, sent to Review).
  - Each call keeps at most `control_records_per_call` ids (default 500); beyond that the count is kept.
  - The worker reports the ids it touched in its run report (`CrawlService::report`), so each crawler run opens to the
    records it touched. This takes in that queue item from `handoffs/session-manager.md`.
- **Ids and counts only.** The ledger never stores a person's name, email, phone or a page's text.
- **Kept** for `control_ledger_days` (default 90) and `control_records_days` (default 30), which are Thresholds
  settings. Older rows are deleted in small batches.

> **Note (Jason, 2026-11-19, control-board-itsdata): "Its data" on each process's own page.** His question, "How do I see
> what data the workflow is creating (Real Data)", and his answer in the pop-up (Build it): each process's own page has
> a table, the admin's one table (`config/proc_records`), of the records its calls touched: newest first, the record by
> its number (a listing, a Review item, a source…), what happened (Stored, Updated, Sent to Review, Refused), the call
> and run, and when, with **Show** by what happened. A record opens its own page (a listing's editor, a Review item),
> else the call in the Ledger, never a dead end. Ids and what happened only, as above. W0's page also counts its index
> by state, linked to Insights → Index.

### 5. Monitoring in real time: slow, retry, crash

Problems are caught **as they happen**, not at the hourly check.

**Where they are detected:**
- **At the moment of the event:**
  - each endpoint call, through the ledger helper;
  - each site job's end, through `Scheduler::run`'s end-of-run hook;
  - each service failure, through `ServiceHealth::failed`;
  - each crawler run report.

  Each of these checks its readings against their limits on the spot and raises or clears an alarm before it returns.
- **Within 5 minutes, for silence:** the watch job `control_tick` (new, in `Scheduler::JOBS` and `AUTO`, every 5
  minutes) catches what doesn't call in:
  - a worker that stopped checking in;
  - a crawler run that didn't report within its budget plus 5 minutes (today's "abandoned" waits 40);
  - a job whose run never finished;
  - a scheduled job that didn't start when due.

**What counts as each kind of issue:**

| Issue | Detected when | Level |
|---|---|---|
| **Slow** | A call, run, job or query takes longer than its limit (`ms:*` timings, an endpoint's or a job's usual time × `control_slow_factor`, default 3) | Warning; a trip after `control_slow_trips` in a row (default 3) |
| **Retry** | A service backs off (`ServiceHealth`, back-off kinds), the worker retried a call (the ledger), or a job ran again after a failure | Warning; a trip when a back-off reaches its case limit (`CASE_AFTER`) |
| **Crash** | A job recorded "Stopped" (`job_runs.ok = 0`), a run abandoned or failed, a worker silent, an endpoint answering 5xx, a database update failed | Trip |
| **Held or out** | A ban, refused key or used-up quota holding a service a running process needs; out of budget | Trip |
| **Near a limit** | Caps at 50/75/95% (`Operations::LEVELS`), "returns empty" | Warning |
| **Wrong count** | A call's received ≠ stored + updated + refused (§9) | Warning |

**Note (Jason, 2026-11-18, release 3c): the system tries first; a trip is what it couldn't fix.** A crash (above) is a
**warning** while the system tries again by itself, for `control_fix_tries` (default 3) or `control_fix_minutes`
(default 60) from the first failure, whichever comes first, then a **trip**; slow keeps `control_slow_trips`, and a
back-off trips at its case limit as above. Held or out (a refusal, a refused key, a used-up quota or budget) trips at
once: there's nothing to try. Both settings are in Thresholds & gates (0163). A red required pre-flight line is a trip
only while what it holds is switched on; otherwise a warning Start holds.

**How fast:** at the moment of the event for anything that calls in; at most 5 minutes for silence. The email goes out
in the same minute (§7). The phone push comes at the session manager's next hourly check, so the email is the fast path.

**Note (Jason, 2026-11-19, release 4e): slow is never a trip; the system fixes first.** A slow call or job does less; it
isn't broken, and stopping it made it do nothing ("There is no way to fix this"; "no dead ends, system fixes first"). So
slow is a warning that says how slow, stops nothing and clears itself when the speed returns; this replaces §5's "a trip
after control_slow_trips in a row" and 3c's rule for slow (control_slow_trips is retired, kept). A slow trip already open
is lifted by the system itself: it checks first, then starts again what the trip stopped, and while a check is red it
tries again every watch. An admin's Resolve is **Check and start again** (no question to answer); it's greyed with the
reason while what it would start has a red check. Every open pre-flight alarm's words follow its line as it stands now
(4c), and a line not tested in 6 hours is tested again by the watch, words only.

> **Note (Jason, 2026-11-19, control-board-routes): critical parts trip at once.** In the session manager's chat: "I am
> concerned if a system critical part goes into retry it misses the fact that it was system critical, this is one that
> should immediately trip even it is to be retried, may not require admin but because it causes the system to fail in
> its ability to perform it needs admin oversight immediately." What counts as critical, his words: "Critical is that our
> site can perform its duties. Search, page load, payment processing, database connections, system critical processing
> or procedures." And how it closes: "The system fixes it: the trip closes as 'Recovered…' … This is a notification, not
> need for me to acknowledge. Retries run out: it becomes an ordinary trip that only Resolve clears: Escalation Notice,
> retries unsuccessful, now admin required."
> - **The register:** each part carries a duty or none (`ControlRegister::DUTIES`: page, search, payments, database,
>   procedures; defaults in `ControlRegister::CRITICAL`, changed on Thresholds & gates → Critical parts, each change in
>   the logbook). The board marks a critical part with its duty ("Payments: critical").
> - **First failure:** a trip at once, to Pulse, the Founder's email and the push; the system keeps trying and nothing is
>   stopped (`retrying_at`). A critical part's retry warnings count as failures too.
> - **The system fixes it:** the trip closes itself, "Recovered: <part> was down <n> min, the system fixed it.", a notice
>   (Notify `alarm.recovered`) with nothing to press; it doesn't hold the bell; it's in the logbook.
> - **Its tries run out:** the Escalation Notice ("Retries unsuccessful, now admin required: <part>, <duty>", Notify
>   `alarm.escalated`, critical); it's an ordinary trip now, what it holds is stopped, and only Resolve clears it.
> - **Not critical by themselves:** the crawler, W0, the video worker, enrichment, and Railway or GitHub as hosts
>   (GitHub is critical through releases). They keep 3c: a warning while the system tries, a trip when it can't.
>
> **Note (Jason, 2026-11-19, the same release): the stages are tabs, and cleared alarms leave the list.** On the stage
> tiles: "these are jump links, not good design. When clicked that information is what is on page, everything else goes
> away." Each tile now shows only its part of the board (§2's layout otherwise kept); the board opens on the stage the
> system is in, and a link to a part opens its tab. On the list: "if alarms are cleared they should not be present, moved
> to log." The alarm list holds open alarms only; a cleared one is in the logbook (written when it clears) and the Alarms
> table (§12). This replaces §7's "cleared, not seen" listing: nothing waits to be marked Seen.
>
> **Note (control-board-critical-2, 2026-11-19): the duties visitors use, watched.** Search, sign-in and payments are
> called by browsers and by Intuit, so the ledger (workers' calls only) never saw them. `ControlSensor` adds three parts
> (`site:search` → search, `site:signin` → procedures, `site:payments` → payments), watched two ways, both free:
> - **What visitors hit:** a hook in `Database.php` notes a server error (5xx or fatal) on their paths, path and status
>   only (no query string, address or user), kept 7 days; it writes only with a connection already open, never throws,
>   and loads nothing on a good answer.
> - **A test every 5 minutes** (the board's watch): one search through the search library with `no_side` (no demand,
>   no crawler, no view, no AI); QuickBooks' token read on the site, asked of QuickBooks at most once an hour (held on
>   staging); refused or needing reconnecting is a trip at once (held).
> - **A failure** is a failed test, or server errors on `control_crit_requests` (3) or more requests in the 5 minutes;
>   fewer are a notice on the part's row. Then the critical rule above: a trip at once, Recovered, or the Escalation
>   Notice. Sign-in has no test of its own (it would need a sign-in); its server errors are its reading.

### 6. The historian

Each reading is kept over time, so every system and process has a trend.

- **`control_readings`**: a reading's value and state. It is written when the value changes, and at least once an hour.
  - Raw rows are kept `control_readings_days` (default 30).
  - After that, one row per reading per day (min, max, last, and the time spent red, amber or blue) is kept 400 days,
    in `control_daily`.
- **The trend for each process, and summed for its system:** runs, records in, stored and refused, retries, slow calls,
  spend against its cap, speed, and **uptime** (time green or amber against time it should have been on).
  - It is drawn as a small inline SVG with no library.
  - It shows the last 24 hours, 7 days or 30 days.

### 7. The annunciator: alarms, and three ways to reach the operator

One alarm list for the whole system, by level.

- **Trip:** a process stopped, crashed, or can't run what's switched on.
- **Warning:** slow, retrying, or near a limit.
- **Notice:** worth knowing, nothing to do. A process came on, a backup is in use, a trip cleared by itself, or a
  process in maintenance.

**Each alarm** says, in plain words:
- what happened;
- what it means;
- what to do;
- its fix as a button that acts in place, or a drawer for another module's tool. Never a link away.

**Kept in `control_alarms`:**
- when it was raised and its process;
- its reading, its level and its words;
- how many times it happened and when last;
- who acknowledged it, and when;
- when it cleared, and how.

**The same problem happening again** updates its open alarm (count and last seen) and never opens a second one.

**It clears by itself** when its reading is green at the next look, and says how it cleared. A trip that cleared before
anyone saw it stays listed as "cleared, not seen" until it is acknowledged or 24 hours pass.

**Note (Jason, 2026-11-18, release 3c): a trip doesn't clear by itself.** "A trip is when the system identifies a problem
and can't resolve it: 1. internal testing and procedures to systematically resolve the issue. 2. pushing the issue to an
admin and ceasing internal process until admin resolves it." So, for trips only:
- **It goes to an admin:** the Founder is emailed at once (as below); its Pulse item goes to the Director's level.
- **It stops what it holds, as narrow as its cause** (playbook §2): a task's failures stop that workflow; a task, that
  task; out of budget, that service; a back-off at its limit, that server's calls; W0 gone quiet, W0. The master switch
  only when the crawler itself is down (runs that fail outright or never report). A site job tied to no workflow can't be
  stopped from here: it only trips.
- **Only an executive's Resolve clears it** (§13): it asks what fixed it, runs the check, and starts again only what the
  trip stopped (Automation's permission). A check still red keeps it open with the reason on its row. **Undo** for a day.
  When its reading turns green, the row says so and waits. A person's own act on the cause (Mark resolved, Reactivate)
  closes it too. Warnings and notices still clear by themselves; a trip raised before 3c (no `tripped_at`) does as well.
  **Note (Jason, 2026-11-19, control-board-links):** "I shouldn't have check and then acknowledge": a Resolve is also its
  acknowledgement, and the row leaves the queue for the logbook at once. Check and start again checks first: a trip whose
  own check is still red stays open saying which part, its button greyed with it. An outside service's issue whose check
  finds it isn't set up (no key) is closed by that check, since nothing calls it.

**Three ways to the operator, all free:**
1. **System notification.**
   - Every admin page's top bar shows the board's light, with a count of open trips and warnings. Tapping it opens the
     alarm list.
   - Each new trip is emailed to the Founder at once, the same recipient as the daily report. (A warning still
     unacknowledged after 24 hours was too, until 2026-11-19: see the note below.)
   - System emails cost nothing.
2. **Pulse, for review and action** (§8). One Pulse item per trip, carrying its steps (warnings too until 2026-11-19:
   see the note below).
3. **A push to Jason's phone** from the session manager's hourly check.
   - `/api/health.php` gains an `alarms` block: open trips and warnings, the process, the level, the words and since
     when. It holds nothing about a person.
   - The session manager reads it and sends a push for each new trip.
   - **What the "site health token" is, in one sentence:** it is a password that lets the session manager read the
     site's alarm list. Jason saves it once in a secure form that the session manager opens for him; it's never shown
     in chat, it can't change anything on the site, and it costs nothing.
   - The token is `HEALTH_TOKEN`, the one the site's daily health report uses. If the site's `.env` doesn't have it
     yet, its pre-flight line (`site:health`) says so. The session manager asks for the same value.

**Acknowledging** in any of the three places (the board, Pulse, the email's link to the board) acknowledges all of them.
There is one record: the alarm. Acknowledging stops the email repeat. The alarm stays open until its reading is green.

> **Note (Jason, 2026-11-19, control-board-routes): only a trip reaches a person.** In the session manager's chat: "I
> think trips that require the admin are sent immediately because they wont clear unless the admin clears them.
> Everything else should be cleared by the system so why send and pulse?" So a trip goes to Pulse, the Founder's email
> and the push at once, as before; under 3c only a trip waits for a person (only Resolve clears it). Warnings, held
> lines that clear themselves and slow lines go nowhere but the board: its light and count in every admin page's top
> bar, and the reports. No Pulse item, no email (the "warning still unacknowledged after 24 hours" email above is
> dropped) and no push. Nothing is lost: 3c already turns a warning the system can't clear into a trip (a back-off at
> its case limit, a crash past its tries, a refusal or out-of-budget at once), and the trip then reaches him. Warning
> items already in Pulse leave it by themselves (Pulse reads open trips only). An alarm's email is no longer also its
> own stream row: it carries its alarm's Pulse item (`pulse_ref`), so Pulse shows the alarm in one place (§8's "one
> thing"; the messages already sent got the same mark in a migration).
>
> **Note (Jason, 2026-11-19, pulse-top): the light isn't on the Dashboard.** Asked of the red count on Pulse: "is this
> really needed here on the pulse?", then "Hide it on Pulse". The Dashboard (Today and the Pulse stream) lists the open
> trips as rows and its bell counts them, so the light repeated them there. Warnings, which Pulse doesn't list, are seen
> on the board and in the light on every other admin page. The whole Dashboard module, since Today draws the Pulse board
> itself (`tv_admin_mod_today` → `tv_pulse_board`). Built in `website/admin/router.php` (`$mk === 'dashboard'`).

> **Note (Jason, 2026-11-19, control-board-itsdata): sites that blocked us, and Pulse's own speed.** Sites refusing W0
> are no longer one warning per site: one notice, "N sites blocked us", each named with its reason (no crawling allowed,
> a firewall or bot check, or the failure's number), up to two **Reactivate** buttons in place and **Every blocked
> site** (Insights → Index, `#held`). It's a warning only when the sites blocking us in a day pass `w0_block_share` (5%
> unless changed, with at least 20 read), since that points at our side; never a trip. Pulse itself is a part on the
> board (`site:pulse`, under the site): `pulse-ui.php` times each gathering of its rows (`Pulse::items`, untouched) and
> keeps the time only (`control_readings`, `gather_ms`); its row shows the middle of the last 20, and past
> `control_pulse_ms` (2 s) it's a warning on the board only: "Pulse is slow to gather (2.4 s). The fix is the Pulse
> store: a communications build." Never a trip, never in Pulse.

> **Note (Jason, 2026-11-19, control-board-card): no Mark resolved; a service's card says how to fix its issue.** Asked
> what case #45's buttons did: "marked resolved - this should not be an option, it should be the result of an issue being
> fixed." So an outside service's alarm no longer offers Mark resolved, on the board, in Pulse or on its card: a passing
> check closes it (**Check it now**; on a trip **Check and start again**, which checks first), and a tripped alarm's
> Pulse case shows the board's own buttons. Every Overpass server held is closed by Check and start again once
> OpenStreetMap releases us. A service case with no check of its own keeps one button, "It's fixed: try it again"
> (its next call is the check). Asked of Common Crawl's card, "how am I supposed to fix this?": each service's card in
> the Service register shows its open issue (what came back, how often, since when, what it means, what to do) with the
> button that does it. Not changed here: the Start the system card's "Mark issue resolved", for Jason to decide.

> **Note (Jason, 2026-11-19, osm-copy-wider): an issue closed is closed everywhere.** On Start the system's "Mark issue
> resolved": "If this was resolved by system or marked as resolved by admin it should be handled." The Start card's
> service issue carries the board's one button (Check and start again on a trip, else Check issue now; It's fixed: try it
> again only where nothing can check), never a bare Mark resolved. And whichever way it closes, the issue and its alarm
> close together, saying how: a person's Check and start again closes the issue ("Fixed by <name>"). For a failure that
> clears by itself (a back-off kind: a timeout, a server error, a 429), the service answering its check closes its trip
> too ("Cleared by its check", through the board's resolve, which checks and starts again what the trip stopped), asked
> of him as its own question; a refusal, a refused key or a used-up quota always waits for a person's button (principle
> 1's exception).

### 8. Pulse, fixed: one item per alarm, acted on in place, closed by itself

Jason doesn't find today's Pulse cases helpful. The cases from these modules each gather many steps into one case, keep
lines after they're fixed, and send him elsewhere to see the cause. They are:
- the pre-flight's case;
- "Starting the system";
- the service issue cases (`svc:<id>`);
- the cap warnings (`ops:cap:<service>`);
- "returns empty" (`ops:empty`).

From Release 3:

- **They become alarms of five kinds:** Pre-flight, Starting the system, Service issues, Cap warnings and Returns empty.
  Pulse shows **one item per alarm line**, never one case gathering many.
- **Each Pulse item reads the same as its alarm:**
  - its title says what happened ("W1 stopped: Overture refused our key");
  - it says what it means and what to do;
  - its fix is a button that acts in place (`['do', …]` / `['form', …]` through `Pulse::manage`), with Peek to open
    the process's gauge in a drawer.
- **Severity follows the level:** a trip is critical, a warning is an alert (since 2026-11-19 only trips are in Pulse: §7's note). Each keeps its Pulse clock (`SLA_HOURS`)
  and can be assigned and escalated as today. Notices stay on the board and in the reports, not in Pulse.
- **It closes itself** when its alarm clears, with how it cleared written as its last note. **Done** in Pulse means
  acknowledged. **Undo** reopens it.
- **No approvals in Pulse** (Jason, 2026-11-17). Anything that needs Jason's yes goes through the session manager's
  pop-up.
- **The board and Pulse are one thing seen two ways.** The board shows where an alarm sits in the system. Pulse is the
  operator's queue of what to review and act on. Both read and write `control_alarms`.
- **The change to the Nov 14 rule:** ServiceHealth's "one Pulse case per issue" becomes one alarm, and one Pulse item,
  per issue. The issue keeps its evidence, "Check it now" and "Mark resolved", on both.
- **Grouped (Jason, 2026-11-18, "Board and Pulse"; release 3d):** this changes "one item per alarm line, never one
  case gathering many". Open alarms that share a group are one row on the board and one item in Pulse, opening to each
  alarm with its own buttons (Acknowledge, Resolve); the row's **Acknowledge all** (Done in Pulse) acknowledges each. The
  key is **system + level + kind's family**: the system from 0171's four, read from the alarm's process (a workflow or
  task, and a service a workflow's needs read → the Knowledge Engine; a worker → Workers; another service → Outside
  services; a site job, endpoint or stage → the site itself); the family: held (held, out, gone quiet), failing (crashed,
  retrying), slow, or a red check. Two or more make a group ("5 engine feeds held"); one stays its own row. In Pulse the
  item is `alarms:<system>:<level>:<family>` with its alarm items as `children` (drawn by the Pulse stream).
- **Colours and a new light (Jason, 2026-11-18, release 3d):** this changes §1's lights ("red: tripped, crashed or not ready"). Red (`--tv-fail`) only for a
  trip or a core host down (Bluehost, Railway, GitHub). A new light, **Held** (`hold`: amber, `--tv-hold`, a pause mark),
  for a process held by its check, a deferred source or a timeout: "Held", or "Holding" while switched on. A trip marks
  its process red and "Tripped" even where it switched it off. Warnings amber. Every colour comes from the palette's
  tokens (brand.md §4, shared with Pulse and the admin), never a literal, so a palette change is one edit.
- **Overpass (Jason, 2026-11-18, release 3b):** every OpenStreetMap server that refuses us is one trip, "Every Overpass
  server is held", resolved by a person: no contact at all while held (no `/api/status` probe, no Check it now), and it
  never clears by itself; it clears when a person presses Reactivate on OpenStreetMap's card after OpenStreetMap releases
  us, then Mark resolved. Other services keep their light probe.

### 9. Complete routing: fast, efficient, accurate

Every process is routed end to end, and the route is checked, so the board and the system stay stable.

- **Complete.** Every process's route in the register runs: source → worker or job → endpoint → tables → outcome.
  - A hop no process claims, an endpoint that doesn't answer, or a table that's missing is a red hop on its gauge, and
    an alarm.
  - The pre-flight gains one line per route, "route complete", read from the register.
- **Fast.** Each hop's time is in the ledger, and the slow rule (§5) applies to each hop.
  - The board itself stays fast. It reads `control_state`, one row per reading kept current by the sensors, so the first
    view is one small query (a target of under 300 ms on the server).
  - A gauge's data loads when it is opened.
- **Efficient.** The ledger counts fetches, calls and spend per record stored. A process whose cost per record climbs
  past `control_cost_factor` × its 30-day usual is a warning.
- **Accurate.** Every call's counts must add up: received = stored + updated + refused. A mismatch is a warning on its
  hop, so nothing is lost silently.
- **Kept healthy.** Uptime per process is on its gauge and in every report, so downtime is seen and counted. A trip
  stops only its own process (0163 §10.2's note); everything else stays up.

### 10. The logbook

Every stage change, switch, control, alarm and acknowledgement, with who and why, newest first, filtered by system or
process.

- **Read where it is already kept:**
  - `automation_log`;
  - Start's sessions;
  - `preflight_runs`;
  - service issues opened and resolved;
  - `job_runs` failures;
  - releases going live.
- **New, in `control_log`:**
  - alarms raised, acknowledged and cleared;
  - shut-downs and maintenance;
  - each control pressed on the board;
  - an operator's own note ("Log an entry", with spell check).
- **Times** are shown in the zone where they happened, always labelled (`js/tv-time.js`).

### 11. Reports, for the system and each process

- **When:**
  - **daily**, at 5 am Arizona time, with the daily report;
  - **weekly**, on Monday;
  - **on demand**, from any system or process.
- **What each covers:**
  - uptime;
  - what ran;
  - records in, stored and refused;
  - slow calls, retries and crashes;
  - spend against caps;
  - alarms raised, acknowledged and cleared, and how long each was open;
  - shut-downs and maintenance;
  - switches;
  - the trends.
- **Each opens as a page** that reads well on a phone, with **Download** (CSV, and the page as a file).
- **A short summary** (three to five lines) goes in the daily report's System status and in the health report's
  `alarms` block, for the morning brief and the session manager's reports.

### 12. Where it lives in the admin (0109)

- **A new section, System Configuration → System Control Board**, first in that module's menu:
  https://traversence.com/admin/config/control, with anchors `#systems`, `#alarms`, `#preflight`, `#start`,
  `#maintenance`, `#log` and `#reports`.
- **A process's gauge** is `/admin/config/control/<process>`, the section's item route, opened as a drawer.
- **Built from the manifests and widgets:**
  - a `gauge` widget (a process with its route, readings and controls) is added to `app/admin/widgets.php`;
  - readings use `metrics`;
  - alarms, the logbook, the ledger and the upkeep calendar are `table` tools in `app/admin/tools.php` (`config/alarms`,
    `config/log`, `config/ledger`, `config/upkeep`);
  - each `h2.hb-h` part folds.
- **The board's light** is in every admin page's top bar (§7). **Dashboard → Today** gets one status line.
- **Reference mock-up:** `design/control-board-mockup.html` (Jason, 2026-11-18: "for your reference"). It is a guide
  to the layout, not a spec.
  - **Kept from it:**
    - the light and the three channels in the top bar;
    - the six-stage strip;
    - each gauge card's route line, readings grid, and in/stored/updated/refused bar with its "accounted" check;
    - Maint, Log and Report on each card;
    - the query timings table;
    - the source map table;
    - the upkeep calendar;
    - the Pulse queue beside the board.
  - **Changed when built:**
    - the admin's own look and brand colours, not its dark theme;
    - one column at 390px (its cards are at least 400px);
    - answers in place (`TvAsk`) and drawers, never `alert()` or modals;
    - every value and the source map read live from the code (`Preflight::NEEDS`), not written in;
    - one gauge per workflow under the Knowledge Engine, with Workers meaning the three Railway services;
    - phases shown as rolling up several workflows each (`Preflight::PHASES`);
    - no token box on the board (the token is saved only in the session manager's secure form);
    - shut-down and maintenance as §2 says.

### 13. Access

- **Viewing the board, acknowledging and logging an entry:** executive and above, the same as the pre-flight and
  Automation (`Access::can('config.control', ['min' => 'executive'])`).
- **Each control keeps its own permission:**
  - start, shut down, maintenance and put back use `config.automation`;
  - checks use `config.preflight`.

  A control without permission is greyed with the reason.
- **The records a process touched** open under their own pages' access.
- **The health report's `alarms` block** needs `HEALTH_TOKEN`.
- **Staging:** the system runs on live only (Start refuses on staging). The new tables are `follows` in
  `app/lib/staging-tables.php` (R14), so staging shows live's picture with its controls greyed.

### 14. What it replaces or archives

Each page stays reachable until the board's part replaces it. It is then archived (`archive.md`), never deleted.

| Today | Becomes | When |
|---|---|---|
| Pre-flight page (`config/preflight`) | The board's pre-flight stage; `Preflight.php` stays | Release 2 |
| Start card on Automation and the Workflow board | The board's start-up stage; both pages link to it | Release 2 |
| The switch-on check on the Workflow board | The board's interlocks; the Workflow board keeps each workflow's settings and versions | Release 2 |
| Pulse cases: Pre-flight, Starting the system, `svc:<id>`, `ops:cap:<service>`, `ops:empty` | Alarms, one Pulse item per line (§8) | Release 3 |
| Insights → Telemetry (coming) | The historian's trends | Release 4 |

Automation keeps its list of switches, and the board's controls call the same code. The service register, Costs and
Outside services stay; the board opens them in drawers.

**Note (2026-11-19, the build finished):** what each section became, by release. §1–§3 (systems, stages, the register):
releases 1 and 2 (#120–#129). §4 (sensors and the ledger): 2a (#121, #124), 3e (#145). §5 (real-time monitoring): 3a–3c,
with Jason's notes there; slow is never a trip (4e, #160); a 429 is a warning that waits twice as long (5a, #166); a
back-off in its wait isn't a hold, so no gate goes red for it (#169; the 3c rule unchanged, Jason 2026-11-19: "We already
have a rule for how trips get set"). §6 (the historian): 4b (#154), 4c (#156). §7 (the annunciator): 3a–3d; a trip clears
only by its Resolve, and a tripped row offers Check and start again, never a bare Start (#169); old trips raised before
trips had their time are settled by the watch (#168). §8 (Pulse): 3a, 3c, 3d. §9 (routing): 2a, 3e. §10 (the logbook):
5a (#166). §11 (the reports): 5b (#173), built on demand from what's kept, nothing stored. §12 (where it lives):
the section, its anchors, the top-bar light and Today's line (2b, 3a); each process's own page
(`/admin/config/control/<kind>-<name>`, a drawer from its row) and the `config/alarms`, `config/log`, `config/ledger` and
`config/upkeep` tables as sections under the board: control-board-12. §13 (access): 2b onward. §14: Pulse cases became alarms (3a, 3c) and Telemetry points to the board (4b); the Pre-flight page
stays until Jason's OK to archive it, and the links from Automation and the Workflow board are still to do.

### 15. The releases, in order

Five releases, not four: shut-down, maintenance and real-time monitoring add one. Each is one coherent zip, checked on a
phone at 390px, with its new buttons pressed once, sent with `send.py`, and approved by Jason through the session
manager.

1. **The register, the routes and the pre-flight fixes.**
   - `ControlRegister.php` (systems, processes, routes), read by Start and the pre-flight.
   - The "route complete" lines.
   - Start's confirm step re-tests the `nd:` lines its workflows depend on, and their `svc:` lines when older than
     `pf_fresh_mins` (default 60), inside its time budget.
   - It runs the four timings (slow is noted, a failed query is red).
   - `SystemStart.php`'s header comment is corrected.
2. **The sensors, the ledger and the live board.**
   - `control_ledger`, `control_records` and `control_state`; the endpoints' helper; the worker reports ids and retries.
   - The section, with systems and processes, gauges and routes.
   - The pre-flight and start-up stages, and shut-down per process and for the system.
   - The Pre-flight page is archived; Start and the switch-on check move to the board.
3. **Real-time monitoring, the alarms and Pulse.**
   - `control_alarms` and `control_tick`; slow, retry and crash detection at the event.
   - The top-bar light, the email, the health report's `alarms` block.
   - Pulse fixed (§8); the five Pulse cases become alarms.
4. **Maintenance and the historian.**
   - `control_maintenance`, maintenance windows and the upkeep calendar.
   - `control_readings`, trends and uptime; Telemetry points to the board.
5. **The logbook and the reports.**
   - `control_log`, the merged logbook and "Log an entry".
   - Daily, weekly and on-demand reports with their downloads; the summaries.

## What this doesn't do

- It doesn't change how any workflow gathers, or any rule in 0164.
- It doesn't change Railway's cron or restart policy (the site sets them, as today).
- It doesn't spend money: no text messages and no paid push service.
- It doesn't move data between databases.

## Consequences

- **One place to see and run the whole system:**
  - every system and process, end to end;
  - every stage from pre-flight to shut-down and maintenance;
  - what came in and where it went;
  - how it has run over time.
- **Problems are caught as they happen,** reach the operator three ways, and close themselves when fixed.
- **Pulse becomes a working queue:** one item per alarm, with its fix in place.
- **Start and the pre-flight stop drifting apart:** both read the register, and Start re-tests what it relies on.
- **Eight new tables, all add-only,** each with its kept-days settings: ledger, records, state, alarms, readings, daily
  summaries, maintenance and log.

**Note to §8 (2026-11-19, Jason): an alarm's Pulse clock.** Alarms no longer show a clock on their row (decisions/0105's
note: clocks on work cases only). Asked "if no one resolves a Control Board trip, should it still be escalated?", Jason:
"YES". So an open trip still goes up by itself: to the Manager once its level's hours have passed, to the Director after
twice that, and it is in the bell's list throughout (it is Critical).

**Note (Jason, 2026-11-20, decisions/0175): a fault pauses, never switches off.** §7's trip no longer writes into a person's switch: the system pauses what its cause touches ("Paused by the system", with its reason) and it clears itself when its check passes. The escalation set in §5's note of 2026-11-19 and 3c's note stays exactly as it is. At a failure both the component and the workflow are checked; the workflow reroutes to a backup where one is named. Every alarm opens its fix in a drawer (at_hand): the board is the one place to act.

**Note (Jason, 2026-11-20, in the decision pop-up): a nightly task keeps the hour.** Asked how a nightly task's tries
should count (a bulk task runs only 1–5 AM Arizona, so a second try can't come inside the hour, and W1's alarm read
"1 of 3 tries, until 09:25 UTC" hours after that time had passed), he chose "Keep the hour": as 3c set it, after
`control_fix_minutes` (60) with no fix, it's a trip. Found with it: the trip only happened when the same failure was
raised again, so a warning whose hour ran out with no new failure stayed a warning, its words saying a time that had
passed. The board's watch now trips a warning whose tries or time ran out, and its words follow the state.
