# The control board: step 1, one requirements list (2026-11-21)

This is step 1 of `handoffs/control-board-plan.md`: every requirement Jason has given for the System Control Board, in
one list, each with where it comes from and how it will be tested. It merges four readings (A: decisions/0171; B: 0175
and 0173; C: 0176, 0172's alarm parts and 0163's board parts; D: the hand-offs, Jason's answers, his mockup and the
broken pieces he found). Every line traces to a source line; where two current lines disagree, or nothing decides a
point, it is a question for Jason (section 4), never a guess. Lines a later dated note replaced are in section 5, not
dropped. **This is a draft for the session manager's review, then Jason's.** No code was changed.

**Sources read.** `decisions/0171-the-control-board.md`, `0175-one-lifecycle-for-every-module.md`,
`0173-one-lifecycle-for-every-outside-service.md`, `0176-the-dispatcher-presents-and-routes-every-thread.md`,
`0172-communications-who-reaches-whom.md` (its alarm, push, quiet-hours and escalation parts),
`0163-operations-one-view-of-services-workflows-and-spend.md` (its board parts), `0105-dashboard-and-pulse.md` (its
clocks, for escalation), and the hand-offs. Code was checked only where a question turned on it, on the live mirror at
Sync 2026-11-21 12:26 UTC (`37a212e`).

**How to read a row.** `R#` is this list's number. "Jason's words" is his quote where a source records one ("—" where the
rule is written in the decision's words, not his). "Sources" gives the file and line (`L`), then the reader rows it
merges in brackets (A12, B30, C5, D40), so each can be traced back. "Test" says how step 2's audit and step 4's scenario
test will prove it: **Scenario** (cause it on staging and check the result), **Rendered** (draw the page at 1280px and
390px), **Unit** or **DB** (a check on the code or data), or **Review** (read, not run). "(see Q#)" marks a row that
depends on a question.

**Source keys.** Decision files by number (0171, 0175…). Hand-offs under `handoffs/`: CB = `control-board.md`; CB20 =
`control-board-2026-11-20.md`; OPS = `operations-2026-11-20.md`; DSP = `dispatcher.md`; PF = `preflight.md`; AS =
`automation-switches.md`; SM = `session-manager.md`; PB = `builder-playbook.md`; C51 =
`control-board-plan/case-51/NOTES.md`; PLAN = `control-board-plan.md`. MK = `design/control-board-mockup.html`.

## 2. What Jason wants

The plan's test: the finished board must answer these.

- "The control board is where an operator can see every sensor and status of the entire power plant operation so I
  want our system to have a very similar system." (0171 L19-21)
- "The system control board: start to finish system management and control. Organized by how an operator can see and
  understand each system and everything within its process." (0171 L27-28)
- "real time monitoring, so any trip or issue (slow, retry, crash) produces an alert." (0171 L31-32)
- "Complete routing for each process and must be fast, efficient, and accurate for the board and the system to be stable
  and operational, down time is bad." (0171 L34-36)
- "The board should provide me direct action capabilities to fix issue, by using, drawers, pop-ups, side-panel to bring
  me the component or tool to fix the issue at hand." (0175 L68-70)
- "Holds vs OFF: the app service is not turned off when its performance is not functional unless it requires it off for
  maintenance, hence PAUSED." (0175 L63-64)
- "no dead ends, system fixes first" (0171 L300)
- "Still struggling to get a clear picture of how well the system is performing." (0175 L472-473)
- "I want a plan that ensures one build, built right, and achieves what I want and need"; "I want visual mockups too."
  (PLAN L3-4, L61)

## 3. Requirements

### Area 1. Purpose, views and layout

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R1 | The board is a control room: every sensor and status of the whole operation is seen on it, and controlled from it. | "The control board is where an operator can see every sensor and status of the entire power plant operation…" | 0171 L19-21; CB 308-311 (A1, D2) | Audit: every part in the register (workflow, task, service, job, worker, duty, endpoint, table) has a row with its state and a control; list any part with none. |
| R2 | Start covers pre-flight, activation, monitoring and reporting for the whole system; each step can be opened and shows what it is doing and the data it makes. | "I see START as PREFLIGHT, ACTIVATION, MONITORING, REPORTING for the entire system (each step accessible and able to see what is doing and the data its generating)." | 0171 L17-18; CB 307-308 (A2, D1) | Rendered: each stage opens and shows its live activity and a link to the data it produced. |
| R3 | The board shows the endpoints and what data is captured and stored. | "…does not include endpoints or a way to see what data is being captured and or stored." | 0171 L22-23; CB 311-312 (A3, D3) | Rendered: each `/api/crawl/` and `/api/video/` endpoint is on its process page with received, stored and refused counts; each count opens its records. |
| R4 | Organised by system, then by everything inside each process; each gauge holds its whole process, with the means to manage it for performance and reporting. | "Organized by how an operator can see and understand each system and everything within its process. Each gauge holds the entire process…" | 0171 L27-29, L39, L97, L123; CB 357-358 (A4, A5, D27) | Rendered: the first view is the systems; opening one shows its processes; each process page holds route, readings, controls, data, performance, alarms, log and report. |
| R5 | Complete routing for each process, fast, efficient and accurate, so the system stays up: down time is bad. | "Complete routing for each process and must be fast, efficient, and accurate … down time is bad" | 0171 L34-36, §9 L524 (A101) | Covered by R49, R54, R55 and R64 (route checks) and R175 (uptime shown per process). |
| R6 | The board oversees six stages, for the whole system and for each process: pre-flight, start-up, observation and reporting, shut-down, maintenance, history. The same control works at both levels. | "System Board must oversee Preflight, Start-up, Observation & reporting, shut-down, maintenance, historical data at system level as well as individual process." | 0171 L29-30, L98-99, L151-154; CB 359; MK 443-450 (A6, A126, D16) | Rendered: each stage reachable for the system and for one process; a control pressed at either level acts on that level. |
| R7 | The pre-flight and Start the system are stages of the board, not separate places; their libraries are reused, nothing that works is rebuilt. | — | 0171 L101-102; CB 328-329 (A7, D5) | Review: the board calls `Preflight` and `SystemStart`; no copied logic. |
| R8 | Four systems: the Knowledge Engine (W0-W14 and their tasks), Workers (W0 Indexer, crawler, video worker), Outside services (each in the register), and the Site (databases, .env, database updates, staging, sync, site jobs). They come from the register, so a new workflow, worker, service or job appears by itself. | — | 0171 §1 L106-116; MK 518-571 (A8, A9, D21) | Scenario: add a job to `Scheduler::JOBS` on staging; it appears under the Site with no board edit. Unit: four systems with the named members. |
| R9 | The first view shows each system as one line: its light is the worst of its parts; it shows its counts and its stage. | — | 0171 §1 L117-118 (A10) | Scenario: one part amber, one red: the system line shows the worse; counts and stage shown. |
| R10 | Parts are rows in the admin's one table: the four systems as group rows; each row has status, stage, where it stands, last 24 hours, speed, OK rate, Check, and ⋯ with words (a greyed item says why). A part's name opens its own page in a drawer. | — (his example rows: "columns of metrics on the row") | 0171 L193-198; CB 41, 296-298 (A11, A23, A24, D10, D31) | Rendered 1280: columns align, group rows present, a greyed ⋯ item shows its reason; tapping a name opens the part's page. |
| R11 | A system line's counts are links: a count filters the rows to that status and opens the system; pressed again, every part shows. Opened systems stay open through the 30-second redraw; Open every system / Close them sits under the table; an alarm's Holds: link opens the part's page; search finds a part by its sources' names. | "thought I could click listed items on the right to filter the rows?" | 0171 L198-207; PB 98-103 (A25, A27, A28, K11) | Rendered (`board-rendered.cjs`): press a count, rows filter and the system opens; wait 30 s, still open; search a source's name finds its part. |
| R12 | The site's needs (each need's sources in order, ours first, and its fallback) and each system's trend are panels of their own, read live from `Preflight::NEEDS`, never a hand list. | — | 0171 L200, L608-612; MK 546-562; CB 459-460 (A26, A158, D24) | Rendered: needs panel matches `Preflight::NEEDS` row for row. |
| R13 | A process page shows first its route: sources → worker or job → endpoint → tables → outcome (Review, published, held, refused), each hop with its state and what passed along it. | — | 0171 §1 L124-125; CB 318 (A12, D4) | Rendered: each hop drawn with state and count. |
| R14 | A process page also shows: its readings (value, limit, when read; reading ids keep the `nd:`, `svc:`, `wf:`, `ms:`, `ep:`, `tb:`, `wk:`, `job:` pattern); its stage and controls; its data (last runs, ledger rows, records touched, table sizes); its performance (speed, throughput, refusals, retries, spend against cap, uptime); its open alarms, log, trend and report. | — | 0171 §1 L126-137; MK 827-868 (A13-A17, D22) | Rendered: every block present on one seeded process; Unit: reading ids match the pattern. |
| R15 | The stage tiles are tabs: a tile shows only its part of the board; the board opens on the stage the system is in; a link to a part opens its tab. | "these are jump links, not good design. When clicked that information is what is on page, everything else goes away." | 0171 L328-331 (A29) | Rendered: press a tile, only that stage shows; a deep link opens the right tab. |
| R16 | The board lives at System Configuration → System Control Board, first in that menu, https://traversence.com/admin/config/control, with its anchors; System Configuration's side-panel icon opens it; a part's own page is `/admin/config/control/<kind>-<name>`, opened as a drawer from its row. | — | 0171 L582-585, L652; CB 454-455 (A153, A154, D28) | Rendered: menu order; each anchor and part address lands. |
| R17 | One control board in the menu, three entries: **System Control Board** (tabs Alarms, Logbook, Ledger, Upkeep, Reports, Scheduled tasks; the master switch, Start the system and Pause everything as its Start-up and Shut-down stages), **Modules** (the Service register: each card holds its stage-2 pre-flight, caps, budget and cost), **Workflows** (Workflows and the Workflow board merged). Permissions and Data separation stay as configuration. | "arent we combining things into one control boards, if so why does the drop down look like we are segmenting everything?"; "yes. fold into 2" | 0175 L410-423, L437-444; CB20 108-121 (B97, B98, B99, B100, D33) | Rendered: the menu shows three entries; `board-tabs.cjs`, `modules.php`, `workflows.php`. |
| R18 | Costs is a fold at the top of Modules; By workflow also shows on Workflows; Automation's Services table and its Changes fold stay in Start-up. | "Top of Modules" | 0175 L464-467; CB20 110 (B102, D34, D35) | Rendered: Modules opens with Costs folded at the top; Start-up has Services and Changes. |
| R19 | Every page that moves keeps everything it did: each old address redirects to its new tab, card or anchor, landing on the same spot; old sections are archived in `archive.md`; each moved behaviour is listed in the hand-off. | "These issues need to be caught!" | 0175 L267-268, L425-429; 0173 L84-89; 0171 L630-638 (A160, B92, B101, B107, D130) | `moved.php`, `drawer-hash.cjs`, `peek-anchor.cjs`: every old address lands on its spot. |
| R20 | Everything on the board starts closed on load, each fold with a one-line summary; a long line shows once with a ▸ toggle that opens it in place, never "…" or More. | "these need to be collapsed on load"; "use a toggle instead of ... and more" | CB 40, 55-57, 296-298 (D29, D30) | Rendered: load the board, every fold closed; no "…" or "More" anywhere. |
| R21 | The board's top is live diagnostics: one health line in plain words, then "Paused now" (what is paused right now, by the system or a person, and why, each item opening its fix in a drawer). No stale snapshot ("over an hour old"). | "Still struggling to get a clear picture of how well the system is performing." | 0175 L371-372, L442-443, L454, L469-481; CB20 81-87 (B68, B69, B71, B72, D6, D71, D72, D74) | Rendered: top shows the health line and Paused now; no snapshot age text. |
| R22 | Paused now is in three groups: **Needs attention** (each trip, with its fix), **Waiting on those** (what a trip paused, folded under its trip), **Off on purpose** (switched off by a release or a person, with what each waits for, folded away). | (chose all three proposals) | 0175 L476-478 (B71, D72) | Rendered 1280/390 from a seeded state: three groups, the right items in each, folds. |
| R23 | Dashboard → Today carries one status line for the board. | — | 0171 L592 (A156) | Rendered: one line on Today. |
| R24 | Built from the admin's manifests and widgets: a gauge widget, readings as metrics, and the alarms, logbook, ledger and upkeep calendar as table tools (`config/alarms`, `config/log`, `config/ledger`, `config/upkeep`). Each `h2.hb-h` part folds. | — | 0171 L586-591, L652-653 (A150, A155) | Review: tools in `tools.php`; Rendered: headings fold. |
| R25 | From Jason's mockup, kept: the light and three channels in the top bar, the stage strip, each card's route line, readings, the in/stored/updated/refused bar with its "accounted" check, Maint/Log/Report per part, the query timings table, the source map, the upkeep calendar, the alarm queue beside the board. Changed: every value read live; one gauge per workflow; Workers are the three Railway services; no token box on the board; the admin's look and brand colours, not the mockup's dark theme. The mockup is a reference; the sessions decide. | "for your reference … you will be the decision makers" | 0171 L593-612; CB 367-368; MK 412-1026 (A157, A158, A151, D13, D14, D17, D20, D22, D25, D26) | Rendered: each kept element present, no literal values, no token box. (see Q16, Q21) |
| R26 | Access: seeing the board, acknowledging and logging need an executive; start, shut down, maintenance and put back need Automation's permission; checks need the pre-flight's; a control without permission is greyed with the reason; a record opens under its own page's access. | — | 0171 §13 L616-623 (A159) | Scenario per role: a manager can't open it; an executive without Automation sees greyed controls with the reason. |

### Area 2. Parts, register and lifecycle stages

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R27 | One unit register names, per system and process: its site jobs, crawler tasks, services, endpoints, tables, what it feeds and its route. It reads what is already declared (workflows, jobs, Start's order and tasks, the pre-flight's needs and phases, the service register, scheduler jobs, crawler tasks, staging tables), never a copy. | — | 0171 §3 L211-225 (A30, A31) | Unit: every process has all seven fields; change a source list, the register follows. |
| R28 | Anything no process claims is listed as unclaimed; the zip check refuses a new `/api/crawl/` or `/api/video/` endpoint that doesn't name its process. | — | 0171 §3 L226-228 (A32, A33) | Unit: add an unclaimed endpoint, it is listed; zip-check fixture with an unnamed endpoint fails. |
| R29 | Critical parts: each part carries a duty or none (page, search, payments, database, procedures). Defaults are set in code and changed on Thresholds & gates → Critical parts, each change logged; the board labels a critical part with its duty ("Payments: critical"). Not critical by themselves: the crawler, W0, the video worker, enrichment, Railway or GitHub as hosts (GitHub is critical through releases). | "Critical is that our site can perform its duties. Search, page load, payment processing, database connections, system critical processing or procedures." | 0171 L311-318, L325-326; 0175 L157-158 (A34, A35, B36) | Unit: the duty list; Rendered: label shown; change a duty, a logbook row appears. (see Q5) |
| R30 | Three parts for the duties visitors use: `site:search` (search), `site:signin` (procedures), `site:payments` (payments). Pulse is a part under the Site (`site:pulse`). Communications is a system on the board, a process per channel (Pulse, Bluehost email, SES, push, mailboxes). | — | 0171 L335-337, L449-450; 0172 L782-797, L835 (A36, A37, C65) | Unit: these parts exist with their duties; Scenario: refuse the push keys on staging, the push process raises an alarm. |
| R31 | Every module (our platform services and an account's apps) goes through the same eight stages, in Jason's order; the App store and its apps build on the same model. | "1. Service selection and its configuration 2. Integrated into our system (preflight) 3. Activation: switched on 4. Assigned to workflows to render support 5. Operational… 6. Maintenance 7. Deactivation 8. Removal (System keeps historical data even after removed)"; "the model I am seeing is the same model we will build upon for the app store and its apps." | 0175 L33-54, §1 L103-126 (B1, B2, B3, B15) | Unit: every entry in `service-defs.php` maps to one of the eight stages. Held: 0175 R3 (see Q15). |
| R32 | A module shows the one stage it is in, the same everywhere it is named (card, register, board, an account's Apps page); its card shows the eight stages as a strip: ✓ for each reached, the current one lit, an unreached one saying what is missing with its button ("2 Integrated: its key isn't in. Add its key"). | — | 0175 §1 L105-106, L119-120 (B4, B13) | Rendered 1280/390: the same stage text on every surface; the strip with ✓, lit stage and button. Held: R3. |
| R33 | What each stage holds: 1 selection and setup (what it does, plans and cost, what it can see and do, what you'll need, guided setup); 2 integrated (its pre-flight passes before it can be switched on); 3 activation by a person through the switch-on check; 4 assigned (the workflows, tasks and features it supports, each with its switch); 5 operational (monitoring, performance, diagnostics, reporting; a problem is an alarm with its fix); 6 maintenance (Take out, Put back after its check; new permissions asked again); 7 deactivation (off, not removed; why, who, when; back through stage 2's check); 8 removal (access revoked, a plain statement of what happens to the data, money owed still works, history kept). | "Removal (System keeps historical data even after removed)" | 0175 §1 L110-117 (B5-B12) | Staging: walk one module through each stage; each holds what the row lists. |
| R34 | Four actions, each with who presses it and its way back: **Activate** (a person; back: Deactivate); **Pause** (the system or a person: stops work for now, keeps key and settings, says why; back: the system's passing check or a person's Resume); **Deactivate** (a person; back: Activate through the check); **Delete** (a person; back: Bring it back while its code remains). Every press records who. | "Activate (person), Pause (System/person), Deactivate (person), Delete (person)" | 0175 L62, §2 L128-140 (B23, B24, B25, B26) | Unit: system code can call only Pause; Staging: each action then its way back restores the state; each row has who. (see Q14) |
| R35 | 0173's stage buttons are kept under the new names (Propose, Add its key, Activate, Pause, Retire → Delete, Bring it back); each stage change is logged (who, when, why) in `service_stages` and the card shows its history. A proposal is named on the register (what for, link, terms, cost, why), no code calls it; back is Drop it. | — | 0175 L121-122; 0173 §2 L97, L105, note L204 (B14, B17, B19) | Regression: each button still works; history rows written. |
| R36 | A new service arrives in a release switched off ("Added, off") with its card complete, so switching it on is the only step left; back is the release's Withdraw. | — | 0173 §2 L98, L64; 0163 L327-328 (B18, C134) | Unit: a new definition arrives paused. |
| R37 | Delete keeps the record: while code remains it reads Retired (code sends nothing, budget 0, alarms settled, card says why and what replaced it); once a release drops the code it moves to the register's Removed list. Data is never deleted. Overpass is the first Retired record. | — | 0175 L121; 0173 §2 L102-106, §4 L157-163 (B20, B21, B22, B110) | DB after Delete: history and data rows present; `overpass-off.php`, `defs.php`. |
| R38 | Standalone apps and integrations are told apart, with strict procedures for an integration: declared touch points, only the owning library's published hooks (never core tables), the data rules, StagingGate and no live gateway in tests, a fallback named and tested before activation. Hooks "to come" are built when the first integration needs them. | "…an app can be a standalone self contained solution or … an integration into our platform core components, so we need … strict procedures for integration." | 0175 L57-59, §5 L198-226, Appendix L322-344 (B121, B124, B125) | Review per integration; code scan: no integration touching core tables directly. Held: R4. |
| R39 | One definition shape per module: `level`, `kind`, `maker`, `touch_points`, `repair`, `fallback`, plus 0173's fields; the zip check fails a module missing `kind`, `repair` or (for an integration) `touch_points` and `fallback`. | — | 0175 §4 L182-196, L307 (B122, B123, B55) | Zip-check fixture without `kind` refused. Held: R4 (see Q15). |

### Area 3. Sensors, checks and pre-flight

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R40 | Every worker endpoint call and every run records what it received, stored, updated and refused (refusals counted by reason), worker retries, how long it took and its answer code, in one ledger written by one helper at the end of each endpoint; the ids it touched are kept too (at most `control_records_per_call`, then a count). | — | 0171 §4 L232-246 (A41, A42, A43) | Scenario: post to an endpoint; one ledger row with every field; 501 records → 500 ids + a count. |
| R41 | The ledger keeps ids and counts only, never a person's name, email, phone or a page's text; it is kept `control_ledger_days`, records `control_records_days`, older rows deleted in small batches. | — | 0171 §4 L247-249 (A44, A45) | DB: no such columns; Unit: old rows removed in batches. |
| R42 | Each process page has "Its data": the records its calls touched, newest first, with what happened (Stored, Updated, Sent to Review, Refused), the call and run, and when; each record opens its own page, else the call in the Ledger, never a dead end. W0's page counts its index by state, linked to Insights → Index. Each crawler run opens to its records ("W1 today"). | "How do I see what data the workflow is creating (Real Data)"; "yes queue the crawler run records fix" | 0171 L251-257; CB 591-600 (A46, A47, D121) | Rendered: table present, each row opens a page or ledger call; `?run=N` lists its records. Partial: values before and after, and each record's source, are not kept (CB 100-101). |
| R43 | Problems are caught at the event: each endpoint call, each site job's end, each service failure and each crawler run report checks its readings and raises or clears an alarm before it returns. | — | 0171 §5 L261-270 (A48) | Scenario per source: the alarm exists before the request returns. |
| R44 | A watch every 5 minutes catches silence: a worker that stopped checking in, a run not reported within its budget + 5 minutes, a job whose run never finished, a scheduled job that didn't start. Jobs that log only when they have work are never "late"; Start's watch counts "nothing to do" as a good run; a release job quiet for 30 minutes raises a warning. | — | 0171 §5 L271-276; CB20 38; CB 245-246, 649-652 (A49, D80, D81, D114) | Scenario each: alarm within 5 minutes; a quiet job with no work raises nothing. |
| R45 | Kinds of issue, each with its rule: Slow, Retry, Crash, Held or out, Near a limit (50/75/95%, "returns empty"), Wrong count. | — | 0171 §5 L280-287 (A50) | Scenario per kind: cause it; an alarm of that kind at its level (levels per area 4). (see Q3) |
| R46 | Retries are seen: a service backing off, the worker retrying a call (reported in the ledger), a job run again after a failure. | — | 0171 §5 L283; CB 261-262, 277 (A66, D38, H7) | Scenario: worker retries a call; the ledger shows it. Held: the worker doesn't report its retries yet. |
| R47 | Start's confirm step tests again the `nd:` lines its workflows depend on (not stored results), their `svc:` lines when older than `pf_fresh_mins`, and the four query timings (slow noted, a failed query red), inside its time budget. | — | 0171 L88-91, §15 L665-667 (A38, A39) | Scenario: a stale `nd:` line red at source holds the workflow at Start. |
| R48 | Query timings: four benchmark queries (50-mile radius around Kayenta, live listings per hub, a two-step graph walk, one W0 address lookup), each with measured time, limit (editable) and verdict, and Re-test. Over its limit is slow (a warning). | — | MK 474-515; 0171 L299-302 (D20) | Rendered: the four with value, limit and verdict; Re-test runs. |
| R49 | The pre-flight has one "route complete" line per route, read from the register. A hop no process claims, an endpoint that doesn't answer, or a missing table is a red hop and an alarm. | — | 0171 §9 L526-529, §15 L664 (A40, A61) | Unit: one line per route; Scenario: drop a table on staging → red hop and an alarm. |
| R50 | Every open pre-flight alarm's words follow its line as it is now; a line not tested in 6 hours is tested again by the watch, for its words only. | — | 0171 L305-306 (A54) | Scenario: fix a line at its source; the alarm's words change; an untested line is retested after 6 h. |
| R51 | The visitor duties are watched two ways, both free: a hook notes a server error (5xx or fatal) on their paths (path and status only, kept 7 days, never throws, nothing loaded on a good answer); and a test every 5 minutes (a search that records no demand or view; QuickBooks' token read on the site, asked of QuickBooks at most once an hour, held on staging). A duty fails on a failed test or `control_crit_requests` server errors in 5 minutes; fewer are a notice on its row. Sign-in has no test; its server errors are its reading. | — | 0171 L338-346 (A55, A56) | Unit: the hook stores no query string or user; the test search records nothing; Scenario: 2 errors → notice, 3 → critical alarm. |
| R52 | Pulse's own gathering is timed; its row shows the middle of the last 20; past `control_pulse_ms` (2 s) it is a board-only warning: "Pulse is slow to gather (2.4 s). The fix is the Pulse store: a communications build." Never a trip, never in Pulse. | "add Pulse's load-time warning" | 0171 L450-453; SM 290-297 (A57, D107) | Scenario: seed slow gathers → warning on the board only. |
| R53 | Sites that refuse W0 are one notice, "N sites blocked us", each named with its reason, up to two Reactivate buttons in place and Every blocked site; a warning only when the day's share passes `w0_block_share` (5%, at least 20 read); never a trip. | — | 0171 L445-449 (A58) | Scenario: 3 of 100 → notice; 6 of 100 → warning; never a trip. |
| R54 | Efficient: fetches, calls and spend per record stored are counted; cost per record past `control_cost_factor` × its 30-day usual is a warning. | — | 0171 §9 L534-535 (A59) | Scenario: raise cost per record → warning. (see Q19) |
| R55 | Accurate: every call's counts must add up, received = stored + updated + refused; a mismatch is a warning on its hop and shows on the part's bar. | — | 0171 §9 L536-537, §5 L287; CB 409; MK 831 (A60, D23) | Scenario: post counts that don't add up → warning on that hop. (see Q16) |
| R56 | Each check tests the servers its steps call, in order (Common Crawl's data host then index; the Census geocoder, TIGER file, data API; Wikidata's query service then records; GNIS file then topo; Overture's address; the NPI registry then monthly files). The first down is red; a later one down is amber "Partly working", naming only the step that waits. `CENSUS_API_KEY` is optional. Common Crawl's bulk reads wait only on its data host. | — | 0175 L528-533 (B48, B49, B50) | Unit per service: a second server down → amber "Partly working" naming that step; no Census key → not red. |
| R57 | A module's pre-flight applies only while it isn't active: before its first Activation (Activate offered only once it passes), and before Activate or Resume after a person paused or deactivated it (a fail says why, with its fix in place). Once active it is **Diagnostics**: the word "pre-flight" never appears for an active module, including one the system paused; diagnostics lift the pause when they pass. | "There's a difference between an app preflight and a system preflight…"; "once something is active it is not longer preflight, it is diagnostics." | 0175 L354-367, L455; 0176 L147-149 (B75-B78, C8, D71) | Rendered: an active (and a system-paused) card says Diagnostics, never pre-flight; Staging: Resume with a failing pre-flight refused, saying why. |
| R58 | The system pre-flight runs at Start the system (core hosts, the database, every active module's check, each workflow's dependencies and backups), shown in the Start-up stage with Run it again; never the board's top line. | — | 0175 L368-370 (B79) | Rendered: Start-up holds it; the top line doesn't. (see Q18) |
| R59 | The pre-flight list: grouped (site, engine services, email, storage, money, sign-in, GitHub, cPanel), then phases 1-6 and W1-W14 with their tasks in Start's order; one compact line per check with a ✓ or ✗ mark, its name and last run; ▾ opens status and fix; counts at the top open the red lines. Each item is a real test that costs nothing and touches no one; ready, not ready (with the reason) or not needed yet; it never starts, pauses or changes anything; Run all keeps going when the page is left, and an interrupted run asks Continue or Cancel. | "one line per check with a green check for confirmed and red x for failed"; "keep lines compact"; "Preflight only works if I stay on that page" | 0163 L364-413; PF 20, 148-152 (C115-C117, C119, C121, C122, D88, D115) | Rendered 1280/390: one line per check; Staging: close the page mid-run, it finishes; kill it, Continue/Cancel offered; no write or send by any item. |
| R60 | The switch-on check runs when any switch is turned on and on demand; each line is green or names what is missing with its fix as a button or link (keys, reachability, holds, terms, budget and pace, Railway schedule and deploy, database updates, clocks, hubs). Hub lines hold W14 only, never the master or W0-W13. | "make sure everything gets set up so when I flip the switch back on it doesn't break"; "we don't restrict data to fill where hubs exist, we just don't offer support in those hubs yet." | 0163 §10.1 L281-312 (C107, C108) | Scenario: each line red on staging shows its fix; a red hub line leaves W1 able to start. |
| R61 | The source map: every need is answered ours first, then outside free before paid, in order (the first that works wins, each switch to a backup logged); a paid backup only by a director's override with its own cap; a need with no second source says "no backup" ("no backup yet, planned: …"). The pre-flight judges by need: ✓ when the first source works, amber on a backup, ✗ only when nothing works. | "we should have backups for sources/apis if one fails that way it can still run in the event of failures. All workflows should be like this" | 0163 L415-506 (C123-C126, C128, C129) | Scenario: down the first source, the backup is used and logged; down all, red. |
| R62 | "Returns empty": a frequent task with 12 or more runs over 24 hours that returned nothing (a monthly load on its first empty run) raises a warning that clears itself. | (Jason's wording "Returns empty") | 0163 §4 L101-106; 0171 §8 L477-488 (C139) | Scenario: 12 empty runs on staging → warning on the board. |
| R63 | Railway's deploys and the sync's runs are watched by the site itself; one that fails or stalls raises an alarm. | — | 0163 §5 L110-112 (C130) | Scenario: a failed deploy on staging raises one. (see Q10) |
| R64 | Each hop's time is in the ledger and the slow rule applies per hop. | — | 0171 §9 L530 (A62) | Scenario: a slow hop → a slow warning on that hop. |

### Area 4. Alarms: levels, tries, trip, escalation

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R65 | Any trip or issue (slow, retry, crash) produces an alert. | "real time monitoring, so any trip or issue (slow, retry, crash) produces an alert." | 0171 L31-32, L100; CB 360-361 (A63, D38) | Scenario: a slow call, a retry and a crash each raise an alarm row. |
| R66 | One alarm list for the whole system, by level: **Trip** (stopped, can't run what's switched on), **Warning** (slow, retrying, near a limit), **Notice** (nothing to do: came on, backup in use, cleared by itself, in maintenance). | — | 0171 §7 L363-368; CB 323 (A64, D39) | Unit: three levels; Scenario: each case lands on its level. |
| R67 | The system tries first: a crash is a warning while the system tries again by itself, for `control_fix_tries` (3) or `control_fix_minutes` (60) from the first failure, whichever comes first; then a trip. Held or out (a refusal, a refused key, a used-up quota or budget) trips at once: there's nothing to try. | "A trip is when the system identifies a problem and can't resolve it: 1. internal testing and procedures to systematically resolve the issue. 2. pushing the issue to an admin…" | 0171 L289-293, L388-390; 0175 L160-164; 0176 L175-176, L190-191; CB 71-85 (A65, A73, B39, C43, C85, D40, D68) | Scenario: crash once → warning "Trying again (1 of 3)"; 3 fails → trip; refused key → trip at once. (see Q1, Q3) |
| R68 | **Keep the hour:** the watch trips a warning whose tries or time ran out even with no new failure, and its words follow the state; a nightly task keeps the hour too: after `control_fix_minutes` with no fix, it's a trip. | "Keep the hour" (decision pop-up) | 0171 L712-717; CB20 40; C51 20-24 (A74, D52) | Scenario: one failure, no repeat, wait 61 minutes → trip; words never show a passed time as to come. (see Q1) |
| R69 | Slow is never a trip: a slow call or job is a warning that says how slow, stops nothing and clears itself when speed returns. | "There is no way to fix this"; "no dead ends, system fixes first" | 0171 L299-302; CB 188-195 (A52, D42) | Scenario: slow call several times in a row → warning only, nothing paused; speed back → cleared. |
| R70 | A back-off trips when it reaches its case limit; but a back-off in its wait isn't a hold (no gate goes red for it), and a 429 ("asked us to slow down") is a warning that waits twice as long, never a trip, its Check greyed with the reason and the next try shown as text. | "We already have a rule for how trips get set" | 0171 §5 L283, L645-647; CB 21-22, 219-222, 235-239 (A66, A67, A68, D41, D43) | Scenario: back-off to its limit → trip; back-off waiting → gates green; repeated 429 → warning, wait doubles, no trip. |
| R71 | A red required pre-flight line is a trip only while what it holds is switched on (by a person's switch); otherwise a warning that Start holds. | — | 0171 L293-294 (A69) | Scenario: red line with the workflow off → warning; on → trip. |
| R72 | A critical part's first failure reaches a person at once (Pulse, the Founder's email, the push) while the system keeps trying and nothing is stopped; its retry warnings count as failures. | "this is one that should immediately trip even it is to be retried, may not require admin but because it causes the system to fail in its ability to perform it needs admin oversight immediately." | 0171 L308-320; 0175 L157-158; 0176 L193-194; DSP 41-45 (A70, B36, C44, D53) | Scenario: fail `site:search` once → alarm on all three channels at once, nothing paused. (see Q8) |
| R73 | When the system fixes it, it closes itself: "Recovered: <part> was down <n> min, the system fixed it.", a notice with nothing to press, in the logbook, not holding the bell. | "The system fixes it: the trip closes as 'Recovered…' … This is a notification, not need for me to acknowledge." | 0171 L313-314, L321-322; 0175 L159; 0176 L178 (A71, B37, C36) | Scenario: search recovers → closed with those words, no button, a logbook row. |
| R74 | When a critical part's tries run out: the Escalation Notice, "Retries unsuccessful, now admin required: <part>, <duty>"; what it holds is now paused. | "Retries run out: it becomes an ordinary trip … Escalation Notice, retries unsuccessful, now admin required." | 0171 L314-315, L323-324; 0175 L160-162; 0176 L179 (A72, B38, C37) | Scenario: keep search failing past 3 tries or 60 minutes → those exact words; its hold applied. (see Q7) |
| R75 | An escalated trip whose check later passes closes itself as Recovered, its pause lifts, and the Recovered notice goes to the Founders; nothing is left to press. | "Close as Recovered" | 0175 L380-384; 0176 L179 (B41, C46, D54) | Scenario: escalate, then pass the check → closed as Recovered, notice to the Founders. |
| R76 | An open trip still escalates by itself on Pulse's clock: to the Manager once its level's hours pass (Critical: 2 hours), to the Director after twice that; it stays in the bell's list throughout. Alarms show no clock on their row. The escalation stays exactly as 0171 set it; draft 1's "third trip in 24 hours" guard is dropped. | "YES" (to "if no one resolves a Control Board trip, should it still be escalated?"); "who set this? we have an escalation process already set/// the action and timeline is set!" | 0171 L705-708, L710; 0175 L66-67, L155-156, L165; 0105 L90, L186; 0176 L179 (A89, A90, B35, B40, C45) | Scenario: trip open 2 h → Manager; 4 h → Director; no clock on the row; no third-trip rule in code. (see Q7, Q9) |
| R77 | A fault is the part not working; a trip is the system placing its pause because of it. The board's word stays **Trip**; what it pauses reads "Paused by the system (a trip): …". | "I am good with this definition."; "A trip is when the PAUSE has been placed" | 0175 L375-378 (B42, D45) | Rendered: every paused part reads "Paused by the system (a trip): …". (see Q8) |
| R78 | An alarm record keeps when it was raised, its part, reading, level, words, count and last time, who acknowledged it and when, and when and how it cleared. The same problem again updates its open alarm (count, last seen), never a second one. | — | 0171 §7 L376-383; 0176 §1 L100-103 (A76, A77, C1, C2) | Scenario: raise twice → one row, count 2; one message per recipient, updated. |
| R79 | Warnings and notices clear by themselves when their reading is green at the next look, saying how. The alarm list holds open alarms only; a cleared one moves to the logbook and the Alarms table. | "if alarms are cleared they should not be present, moved to log."; "alerts and notices need to be cleared once fixed or changed" | 0171 L385, L331-333; CB 425-426 (A78, A80, D100) | Scenario: fix a cause → gone from the list without reload, present in the logbook. |
| R80 | A warning the system is still handling shows "!" and has no Acknowledge; only a trip waits for a person. | "This is a notification, not need for me to acknowledge" | 0175 L457; CB20 96-97 (B96, D51) | Rendered: a warning row has "!" and no Acknowledge. (see Q12) |
| R81 | Open alarms that share system + level + family (held, failing, slow, red check) are one group row on the board and one item in Pulse ("5 engine feeds held"), opening to each alarm with its own buttons; one alone stays its own row. | — (design directive, "Board and Pulse") | 0171 L504-511; 0172 L139-142; CB 86-90 (A91, C22, D63) | Scenario: 5 held feeds → one group row; 1 → its own row. (see Q11, Q12) |
| R82 | A tripped row offers **Check and start again**, never a bare Start: it checks first and starts only what the trip stopped; while that check is red it stays open, says which part, and the button is greyed with the reason. It is also the acknowledgement: the row leaves the list for the logbook at once. No question to answer. | "I shouldn't have check and then acknowledge"; "There is no way to fix this" | 0171 L304-305, L400-402, L648-649; CB 243-245 (A84, A85, A86, D64) | Rendered: no Start on a tripped row; Scenario: green check → closed and logged; red → greyed with reason. |
| R83 | Old trips raised before trips had their time are settled by the watch; an outside service's issue whose check finds it isn't set up (no key) is closed by that check. | — | 0171 L402-403, L649 (A87, A88) | Scenario: seed an old trip → settled; remove a key → issue closed. |
| R84 | Every red check names the part that holds it, read part by part, never matched on words. | — | CB 295 (D57) | Scenario: each red check's title names its red part. |
| R85 | The dispatcher only draws and routes: 0171 and 0175 decide when anything trips, pauses, escalates or reaches a person; no new levels, channels or kinds of push. | — | 0176 L294-298 (C89) | Review. |

### Area 5. What a fault holds, backups and recovery

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R86 | A fault pauses, never switches off. What isn't working is **Paused by the system**, shown in the same place a person's pause shows, with its reason and the check that lifts it ("Paused by the system: download.geofabrik.de failed 6 times, 09:12 MST. It resumes when its check passes."). The person's switch stays as they left it: the pause is a record of its own. Every page shows both: "On · Paused by the system: …". | "Holds vs OFF: the app service is not turned off when its performance is not functional unless it requires it off for maintenance, hence PAUSED." | 0175 L63-65, §2 L137-139, §3 L144-151, L296; 0171 L710; 0163 L625 (A94, B29, B30, B31, B33, B54, B133, C106, D46) | Scenario: a fault on each kind of part; no switch value changes; the pause row reads as shown. |
| R87 | A trip pauses only what its cause touches, as narrowly as its cause: a service's calls, a task, a workflow, or all gathering when the crawler itself is down (runs failing outright or never reporting); out of budget, that service; a back-off at its limit, that server's calls; W0 gone quiet, W0. A site job tied to no workflow only trips. Everything else stays up. | — | 0171 L392-395, §9 L538-539; 0175 §3 L145-149; 0176 L196; PB 32-33; PLAN 28 (A92, A93, B32, C88, D47, D67) | Scenario per cause: exactly that scope is held, nothing wider. **Broken today** for a task's failure (case #51, K1). (see Q2, Q5) |
| R88 | A source down holds only what uses it: a service's red line holds nothing when every need of the workflow still has a working source (ok, or on a backup); it holds only the tasks that call the service when the workflow has others; the whole workflow only when every task calls it. A service no need names and no task calls (a site job's, a paid backup) holds nothing. A red need pauses the tasks that call its services in every workflow it names, never the whole of the first one listed. A hold on one server holds only that server, unless nothing else answers. | "W3: what is it supposed to do?"; "reroute to a back up service or trip" | 0175 L514-524; 0163 L504-505 (B43-B46, C129, D48) | `holds.php`; Scenario: down a backup while the primary works → nothing held; down a sole source → only its tasks pause. |
| R89 | At a failure both the component and the workflow are checked. The component has its own check, pause and card; the workflow reroutes to a named backup ("On a backup": Tavily to Brave, our OpenStreetMap copy before any server) and is paused only when it can't work without the component. The alarm names which of the two is the source, and the board opens that one. | "So two things are happening at the time of a failure 1. component 2. workflow are checked."; "if a workflow has a component that is failing it needs to either reroute to a back up service or trip if it is not able to function properly." | 0175 §3 L167-171, §6 L255-259, L266; 0171 L710; CB20 88-90 (A95, B58, B59, B60, D49) | Scenario: Tavily down → the workflow uses Brave, reads "On a backup", not paused; the alarm names Tavily. |
| R90 | On a backup is a working state: never "Holding", never red, its backup named; a workflow whose failing task a backup covers reads "On a backup". | (same quote) | 0175 L456; 0176 L127, L153; CB20 88-90 (B61, C9, D50) | Rendered: W1 on a backup reads On a backup and names it. |
| R91 | A pause clears itself when its check passes: what it paused runs again, the logbook says so, and the trip's thread closes as Recovered with nothing to press. | "If this was resolved by system or marked as resolved by admin it should be handled." | 0175 L72, §3 L152-154; 0171 L710; CB20 36 (A94, B34, D55) | Scenario: cause a fault, then fix it; the pause lifts, a logbook line, Recovered, no buttons. (see Q6) |
| R92 | For a back-off kind of failure (a timeout, a server error, a 429), the service answering its check closes its trip ("Cleared by its check"); the site's 2xx at a host closes the crawler's back-off there too, but never a refusal, a key or a quota, and never a 429 still in its wait. | — | 0171 L469-473; 0175 L525-527; CB 214-218 (A96, B47, D76) | Scenario: a timeout trip, the service answers → closed "Cleared by its check"; a refused-key trip stays. |
| R93 | However an issue closes, the issue and its alarm close together, saying how; a person's Check and start again closes it "Fixed by <name>". A thread closes only when its check passes, or by a person's act on its cause (Reactivate, a key replaced). | "If this was resolved by system or marked as resolved by admin it should be handled." | 0171 L465-470; 0176 L161-162 (A97, C17) | Scenario: close by each path → the issue and the alarm both closed with the same words. |
| R94 | A site that blocks us is held and never retried by the system; it waits for a person's Reactivate. A banned service's daily probe runs at most 3 days, then a person judges; there is no automatic return: a passing probe raises "looks lifted" with Reactivate. | "waiting for review is purgatory, so we keep trying" (the ladder, never for a blocked site) | 0163 L150-154, L184-185, L581-583; PB 42-46 (C131, C132, D68) | Scenario: a host blocks us → no retries; a passing probe raises "looks lifted". (see Q6) |
| R95 | Two kinds of repair, declared per module: **Fix while running** (a key, a retry, a setting, a server added: nothing else stops; the module is paused by the system until its check passes; its dependants carry on through their fallback) and **Needs shut down** (repair in Maintenance: Take out tells its dependants and pauses them, saying why, until Put back, which runs the module's check first). | "Like a faulty headlight or dead battery, one can be changed while the system is running another might need the system shut down to replace or repair." | 0175 §3 L173-178, §4 L190 (B55, B56, B57) | Scenario: a key fault → dependants keep running; Take out → dependants show why; Put back with a failing check refused. Held: the declaration is R4. |
| R96 | The Census geocoder's backup: when the site's call fails on the connection (reset or no answer, or the site's hold of kind "timeout"), the batch goes from Railway; the worker sends it only to the Census batch address and posts the answers back; the site places only listings it handed off that still wait, marked `via: railway`. Any other refusal waits; a pause or staging never hands it on; a map point is never lost or invented; private listings are never in a batch. | "Yes"; "Yes, build it" | 0175 L386-408; CB20 123-129; OPS 28-34 (B62, B63, B64, D75) | `census-backup.php` (15), `test_census_backup.py` (10). Live #244. |
| R97 | Overture stays as a backup for places by kind; our own copies count as a need's first source while they have rows (no freshness limit for now). Paid backups (Google Places to confirm, Claude to read pages when Gemini can't) are off until a director sets their monthly cap. | "Keep it"; "Keep as is for now" | 0175 L540-542; 0163 L425-427, L502-503 (B65, B66, C145) | Unit: an empty copy doesn't count; a paid backup is off with no cap. (see Q13) |
| R98 | Switches a past trip left off, with no person's setting to go back to, stay off: listed under Needs attention as left off by a trip that has closed, each with its switch; nothing switches them back on by itself. Where a person had set one, it went back to their value, logged "Put back to what <name> set on <date>". | "Keep for a person" | 0175 §7 L270-280, L494-496; OPS 23-24 (B51, B53, D70) | `paused.php`, `paused-now.php`; Rendered: a left-off item under Needs attention with its switch. |
| R99 | The interlock: a process starts only when its needs are green; otherwise its Start names the red line and carries its fix. A red line holds only what it's for: a line for everything holds the master; a workflow's or service's line holds that workflow and its tasks; the Railway schedule's line holds nothing. A service issue fails a gate only when it holds the service. | — | 0171 §2 L156-158; 0163 L345-347, L361; CB 321 (A102, C109, C114, D65) | Scenario: one red need → only its workflow waits, its Start greyed naming the line, with its fix. |

### Area 6. Words (and lights)

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R100 | Each alarm says in plain words what happened, what it means and what to do, and carries its fix as a button. | — | 0171 §7 L370-374, §8 L489-491; 0176 L351, L375 (A75, A100, C27, D39) | Audit: every open alarm has all parts. |
| R101 | Words follow the state as it is now, redrawn when it changes: a passed time is never shown as to come, a wait that's over says so, a back-off's status is read now (never the stored hold date: no "Backing off until Jan 1"); "No data yet" is said as that. | "Words follow the state" | 0171 L305-306, L715-717; 0176 §2 L126, §7 L262-268; PB 58-60; DSP 103-105; CB20 37 (A98, C7, C13, C14, D82, D84) | Scenario: change a state under an open alarm; every surface's words change; no "Jan 1" or past time. |
| R102 | Every thread has one shape, in order: **Title** (what happened, never raw check text: "Common Crawl isn't answering"), **Status** (who paused it, since when, the next try, from the system's clock), **What it affects** (the workflows using it and each one's backup, or "none"), **What the system is doing** (trying again, on a backup, or waiting for a person, and why), **Buttons**, **Details ▾** (the raw evidence, folded). | — | 0176 §2 L121-153; DSP 48-78 (C5, C6, C9, C10, C11, D102) | Rendered 1280/390: six parts in order; Details closed; no status codes in the title. |
| R103 | Each surface shows its part of the thread: the push, title and status; Pulse, all but Details; the board and the card, all; the logbook, the finished story. | — | 0176 §2 L134-139 (C12) | One trip checked on all four surfaces. |
| R104 | Status words: a fault "Trying again (1 of 3)"; a trip "Paused by the system (a trip): …"; each check after a trip updates the status in place, "Checked 09:42 MST: still refused. Next check 10:12 MST." (no new message); a cause the system handles says "The system is handling it". | — | 0176 §4 L175-177, §5 L210, L403-404 (C33, C34, C35, C38) | Scenario: a fault, a trip, two checks: one message, words as shown, times with their zone. |
| R105 | Paused now's words are true: a pause the board's shutdown made after a trip is never "paused by a person"; it names its trip. "Its fix:" said once; "then Resolve" reads "then press Check and start again". | "this makes me feel like the system is not operating correctly." | 0175 L474, L484-488, L534; OPS 19-20 (B70, B74, D73, D87) | `paused-now.php`; grep: no "then Resolve". |
| R106 | The health line above Paused now: the critical duties first (search, sign-in and payments, email, other site jobs), then Data gathering, Workers, Outside services and the site's own parts, each part said once, e.g. "Site, search, sign-in and payments: working. Data gathering: 2 sources down (…), 9 parts waiting on them. 3 off on purpose." Red only when a critical duty is down. | "Critical part down" | 0175 L479-481, L489-491; OPS 22-23 (B72, B73, D61) | `paused-now.php`: no part counted twice; red only with a critical duty down. |
| R107 | Lights: green running within limits; amber backup, near a limit, slow or retrying; **Held** (amber, a pause mark) for a part held by its check, a deferred source or a timeout ("Held", or "Holding" while switched on); grey off by a switch; blue in maintenance; red for a trip or a core host down. Every colour from the palette's tokens, never a literal. | — | 0171 §1 L139-144, §8 L512-516; CB 86-93 (A18, A21, A22, D60) | Scenario per state: the colour token; grep: no literal colours in board files. (see Q5, Q8) |
| R108 | A system's hour on its strip turns red only when a critical part is down; any other part held or red makes it amber with the cause named ("1 of 20 held: Common Crawl"). A part's own hour is amber while it is held, never green. | "Critical part down" | 0175 L431-435, L458-459; CB20 98-101, 155-157 (B131, B132, D62) | `lines.php`; Rendered: one non-critical part held → amber and named. |
| R109 | Off and in maintenance are never shown as failures; a person's pause is "paused", never a failure, and raises nothing. | — | 0171 §1 L146; 0163 L273-274; AS 32-33 (A19, C105, D111) | Scenario: switch a workflow off and take one out → neither red nor counted as a failure, no alarm. |
| R110 | Every status carries its reason (active, paced to what, held why, off for no key or switched off, out of budget until when); silence is a status: nothing can be quietly off. | — | 0163 §1 L65-66, §6 L119 (C94, C97) | Scenario: each state on staging shows its reason; a service with no key shows "off: no key". |
| R111 | Levels show on the row wherever an item is filed: Critical red, Urgent amber, Alert marked; a trip is Critical, a warning Alert. | — | 0172 L243, L298-305, L313-316; 0171 §8 L494-495 (A121, C39) | Rendered: a trip's Pulse row is tinted Critical. |
| R112 | The site health token is explained in one plain sentence where it is asked for: a password that lets the session manager read the alarm list, saved once in a secure form, never in chat, can't change anything, costs nothing. | "…needs a site health token saved through my secure form (not sure what this means (free)" | 0171 L33-34, L418-420 (A99) | Review: the sentence appears where the token is asked for. |
| R113 | Words stay true: every notice, FAQ entry and code header describing old behaviour changes in the same release; a list with nothing to press says so plainly. | "not sure what these means to me as admin, i dont see how to act on it" | PB 34-35; CB 162-164 (D83, D86) | grep the old words after the release. |

### Area 7. Fix it and routing

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R114 | The board is the one place to act: no hunting across pages; it brings the component or tool to the issue in a drawer, pop-up or side panel. Fix it never takes him to another page. You don't fix the dashboard to fix the gas tank: the board shows readings; the component's card is where it's filled and fixed. | "I dont want multiple pages where one thing affects and another and I have to go hunting…"; "Fix it can't take me to another page. I already explained this."; "You don't fix the dashboard to fix the gas tank." | 0175 L46-48, L68-70, §6 L230-238; 0171 L710; 0163 L359-361; PB 25-27; PF 277-280; CB20 75-77 (A103, B81, B83, C84, D89, D91) | Scenario: press every alarm's first button; the page address never changes; the tool opens in the panel. |
| R115 | One side panel at a time: a link inside it replaces it, with ← Back; never a drawer inside a drawer; everything that pops open closes with a visible ×, Esc and a click outside. A tool pressed in a Pulse case also opens in the side panel. | (screenshot: "This is bad design…"); "anything that pops open should have a way to close it" | 0175 L446-450; 0176 §5 L216-218, L411-413; CB20 78-80; PB 104-106 (B93, C77, C83, D92, D131) | `one-panel.cjs`; Rendered 1280/390: open Fix it from the card and from Pulse; one panel; Back, ×, Esc, outside click each work. |
| R116 | An alarm's first button opens its component, routed by the component (a service's card, a pre-flight line, a workflow's or task's own page, a site job on Scheduled tasks): its key, its pre-flight line, its task switches, Take out/Put back, or Activate/Pause/Resume/Deactivate, as the cause needs. An alarm with no mapped cause (a crash, a red check, a payments token) keeps its component button. | — | 0175 §6 L239-244, L300, L446-450; 0176 L401-402 (B82, B93, C82) | Scenario per cause: the first button lands on the right component. |
| R117 | **Fix it** routes by cause to one exact spot: a refused key → the card's Its key with Replace (or Add its key) focused; a used-up quota or cap → the card's Caps and paces (or This month), the community budget → Costs; an outside service that refused us → its issue on its card with Check it now; a site that blocked W0 → its line on W0's page on the board, with Reactivate (Insights → Index lists blocked sites read-only, linking there); no backup named → the workflow's Steps and backups; repair needs shut-down → Maintenance → Take out. | "how I would click a fix it button and how the dispatcher would take me to the correct display to fix it."; "Insights vs systems services register?" | 0176 §5 L200-209, L386-398; OPS 42, 46-63; DSP 151-172 (B94, C67-C74, D93, D94) | Scenario per cause: Fix it lands on that anchor in the side panel. (No raiser yet sets "no backup" or "needs shut-down": test the map only.) |
| R118 | Fix it shows only when a person can fix something, and is first among the buttons. For their trouble (5xx, no answer, a 429, an unreadable answer): no Fix it; Check it now (not on a 429), Pause it (not for a service that can't be paused), Use backup greyed "to come". A spot still to come shows a greyed Fix it with its reason. | "a person is the last resort" | 0176 §5 L210-214, L284-285, L387-388, L403-405; PB 53-57 (C75, C76, C81, D90) | Scenario per failure kind: Fix it present only where the map gives a person's spot. (see Q17) |
| R119 | The spot's switch is the same switch as at its home and records who pressed it; a switch has one home, opened over the board. | — | 0175 §6 L247-249; 0176 L219 (B84, C78) | DB: a press from the panel and from its home write the same row with who. |
| R120 | After acting, the spot's check runs at once: a pass shows Fixed and updates the thread everywhere; a fail says what came back and offers the old value back (Put back). | — | 0176 §5 L224-227, L408-410; 0173 L121-122; OPS 60-63 (B113, C80, D95) | Staging: replace a key with a good and a bad value; set a cap. |
| R121 | From a push or email, a trip and its Escalation Notice open `/admin/dashboard/stream#case=alarm:<id>` (plus `&fix=1` when it has a Fix it, opening the panel on it); Recovered keeps the board's link. | — | 0176 §5 L221-222, L406-407 (C79) | Open each link at 390: lands on the case and its panel. |
| R122 | No **Mark resolved** anywhere for a service's alarm or issue (the board, Pulse, the card, the Start card): a passing check closes it (Check it now; on a trip, Check and start again). Where nothing can check, one button: "It's fixed: try it again" (its next call is the check). The issue's words never say "mark resolved". | "marked resolved - this should not be an option, it should be the result of an issue being fixed."; "If this was resolved by system or marked as resolved by admin it should be handled." | 0171 L455-468; 0176 L367 (A104, A106, C21, H6) | Rendered: "Mark resolved" appears nowhere. Audit note: `Pulse.php` L1293's fallback still names it (live mirror 37a212e). |
| R123 | Each service's card shows its open issue (what came back, how often, since when, what it means, what to do) with the button that does it; the same thread on the card, Pulse's `svc:` case, Outside services' line and the Start card's line; no × that looks like dismissing it; the card links "Its case in Pulse", never "Its alarm on the board"; issue lines link to `#issue-<id>`. | "how am I supposed to fix this?" | 0171 L461-463; 0176 §3 L157-166, L358-366; DSP 80-138 (A105, C15, C16, C18, C19, C20, D104) | Rendered 1280/390: same words on four places; no × on the issue box. |
| R124 | Each Pulse item's fix is a button acting in place, with Peek to open the part's page in a drawer; a case opens Look (quick actions), Work (drawer) and Fix (tool) views; tools never open inside the drawer. | "each have a unique interaction…" | 0171 §8 L492-493; 0172 L184-215 (A120, C64) | Rendered: buttons act in place; each view opens. |

### Area 8. Who is told: board, Pulse, email, push

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R125 | Only a trip reaches a person. A fault the system is still fixing (warnings, held lines that clear themselves, slow lines) goes only to the board and the card: its light, count and reports. No Pulse item, no email, no push. A stage change is never a Pulse case. | "I think trips that require the admin are sent immediately because they wont clear unless the admin clears them. Everything else should be cleared by the system so why send and pulse?" | 0171 L427-435; 0172 L815-818; 0176 §4 L175, L182-189; 0173 L108; DSP 39-46 (A111, A112, B109, C40, C41, D101) | Scenario: a warning → no Pulse row, email or push; a trip → all three. (see Q10) |
| R126 | A trip reaches a person at once on three channels: Pulse, the Founder's email (same minute) and the push. So does held or out (at once) and a critical part's first failure. | "Alerts are up to 1. system notifications, 2. in a pulse… 3. push it…" | 0171 L32-34, L319, L409-411, L429; 0176 L176, L190-194 (A108, A110, C42, C43, C44) | Scenario: a trip → email queued within the minute, Pulse row, push payload. (see Q9) |
| R127 | A trip never waits for quiet hours: trips and Escalation Notices are Critical. Critical always emails and pushes, goes during quiet hours and can't be turned off; quiet hours have no default, each person sets their own. | "any hour" (meaning immediate); "this is a feature the user can configure, not a default" | 0176 L344-345, L379; 0172 L298-305, L802-804 (C48, C55, C56) | Unit: a recipient in quiet hours still gets a trip; a new staff account has quiet hours off. |
| R128 | Every admin page's top bar shows the board's light with a count of open trips and warnings; tapping it opens the alarm list. Not on the Dashboard module, whose rows and bell already list the open trips. | "is this really needed here on the pulse?"; "Hide it on Pulse" | 0171 L407-408, L439-443, §12 L592; MK 412-423 (A109, A114, D14) | Rendered: light and count on an admin page; none on Dashboard pages. |
| R129 | One item per alarm in Pulse (never one case gathering many), reading the same as the alarm; it closes itself when the alarm clears, with how as its last note; Undo reopens it. The board and Pulse are one thing seen two ways: both read and write the same alarm. An alarm's email carries its Pulse item, so Pulse shows it once. | "The Pulse case needs to be address because I am not finding them helpful either." | 0171 L36, §8 L477-501, L435-437; 0176 L113-117; CB 269, 362 (A113, A119, A122, A124, C4, D99) | Scenario: a trip → one Pulse row; act in Pulse → the board shows it; clear → Pulse closed with note. |
| R130 | Alarm messages: email subject and Pulse row `SYSTEM ALARM NOTICE: <item or group>: <action>`; the action follows the state ("Paused by the system", "Retries unsuccessful, now admin required", "Recovered"); push title `SYSTEM ALARM: <item or group>`, its body starting with the action; the body on every channel says what's going on, what it means, what to do, with the link. Alarm emails drop " · Traversence"; the subject changes with the state even though Gmail may split the conversation. | "Wouldnt also be wise to send SYSTEM ALARM NOTICE: (Item or Group): (Action)…"; "Push shortened"; "Same shape as push"; "Subject changes (as built)" | 0176 L333-382; SM 153-154 (C24-C27, C30, C31, D103) | Staging trip → escalate → recover: subjects, titles and bodies exact. |
| R131 | One thread per alarm on each channel: later states update it (Pulse, push) or reply in it (email: stable Message-ID, In-Reply-To, References). A push is tagged by its thing and alerts again only when the level rises or for Critical; past 3 pushes in 5 minutes per person the rest fold into one quiet "N new in your Pulse" (Critical exempt). Staff get push at Alert and up by default. | — | 0176 L104, L333-338, L352, L377-378, L141-142; 0172 L21-25 (C28, C52, C53, C54) | Two states of one alarm: email headers chain; the push tag is the same; 5 Alert pushes → 3 + a summary. (see Q11) |
| R132 | Recovered is a Notice: it updates the trip's thread (a reply in email, the same Pulse row) with no push by default; an escalated trip's Recovered goes to the Founders. | — | 0176 L178-179, L337-338, L372-374; 0175 L153-154 (C46, C47, B34) | Push count after Recovered = 0; the email reply and Pulse row update. |
| R133 | The Escalation Notice's level rise brings one push; an item assigned or escalated becomes a notice to that person by their own channels. | — | 0176 L179; 0172 L409-410, L819 (C45, C61) | Scenario: escalate; one push; the assignee gets a message. (see Q7) |
| R134 | Alarms reach a person through the one dispatcher (Notify), so they appear in the same log and respect each person's channels; nothing is sent from staging; staff are never texted; nothing costs money. | — | 0172 §4.5 L813-815, §4.6 L837, L413; 0171 L687-690 (C60, C62, C66, A163) | Scenario: a staging trip's email and push are held and logged; a `comms_route` row exists for a live trip. |
| R135 | The bell counts everything unread at Alert or above, takes the highest level's colour, and a tap opens the row in place; Pulse lists newest first, the person sorts by importance. | "the notification bell should be used for these items…"; "default is by when received, on sort it should priority by most important or least" | 0172 L240-245, L939-944 (C58, C59) | Rendered: an open trip → bell red with a count. |
| R136 | Pulse shows what each person has read: unread bold with a dot, unread again on news, an Unread filter, Mark all read / Mark unread, per person. | "also need a way to know when Ive read something in the pulse" | CB 262, 666-671 (D106, H8) | Scenario: open an item → read for me, unread for another staff member. (State to prove in step 2.) |
| R137 | No approvals in Pulse: anything needing Jason's yes goes through the session's decision pop-up. | — (Jason, 2026-11-17) | 0171 §8 L498-499; 0172 L930; CB 529, 578 (A123, C63, D98, D108) | Audit: no Approve on any alarm case. |
| R138 | The health report gains an alarms block (open trips and warnings: part, level, words, since when; nothing about a person), read with `HEALTH_TOKEN` by the session manager; without the token in `.env`, the `site:health` pre-flight line says so. A 3-5 line summary goes into it and the daily report. | — | 0171 §7 L414-422, §11 L577-578, §13 L624 (A115, A117, A147) | Unit: block shape, no personal data, 401 without the token. |
| R139 | Uploads use the same thread shape (outside the board; kept so nothing is dropped): each upload target names its thread and landing spot; a place's photo stays post first, with a staff thread "New photo of <place>" (Open it, Hide with reason, Show again; a report joins it); an order file raises one thread per order for the order's team; upload kinds are Notice, never push. | "Keep post first"; "Incidents first" | 0176 §6 L231-253, L317-323, L417-439 (C150-C154) | `tools/dispatcher-tests/r4.*`. |

### Area 9. Switches, start-up, shut-down, maintenance

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R140 | Four layers, workflow → task → route → service, under a master switch: a call happens only when the master, its workflow, task and service are all on. Everything ships off until switched on. | "Please update the site settings to be off until switched back on, also give me a master switch… Also I see the services as routed services connected to a task so we should be able to turn those on and off as well." | 0163 §9 L247-253; AS 3-5 (C101, D109) | Unit: each layer off blocks the call. |
| R141 | The master switch stops all content gathering and creating, never business operations (orders, billing, email, releases, staging, a person's own press, weather and roads, the admin); those rows are greyed with the reason. | "give me a master switch to turn on and off" | 0163 §9 L254-261; AS 25-28 (C102, D110) | Scenario: master off; an order, sign-in and email still work. |
| R142 | Pausing a workflow stops its tasks, its site jobs (a shared job skips only when all its workflows are paused), its AI features and on-demand paths; with W5 paused a visitor's ask is queued with Notify me. A person may pause an outside service; a service that can't be paused is greyed with the reason. | — | 0163 §9 L262-272 (C103, C104) | Scenario: pause W5; ask for a guide → queued, no dead end; Pause greyed on billing. |
| R143 | Each switch shows its reason, who and when; pausing asks why; every change is logged. | — | 0163 L273-274; AS 36-37; CB 325 (C105, D112) | Scenario: pause with a reason → asked why, logged. |
| R144 | A task's switch lives on its workflow's own page on the board (e.g. https://traversence.com/admin/config/control/wf-W1), with each step's services and backup. The module's card lists what it serves and links there, and its in-place Switch on stays: it presses the same switch. Automation shows one line per workflow ("W1: 6 tasks, 5 on. Open W1"); Insights → Crawler's on/off are links to the same switch. | "Workflows are multi-step tasks or procedures that use services to render a specific action/result/outcome."; "Keep both" | 0175 §6 L255-265, L460-462; 0163 L625; CB20 68-69, 92-93 (B88-B91, C106, D96) | Rendered: wf-W1 lists steps, services, backups and switches; DB: the card's Switch on writes the same row. |
| R145 | The board's own adjustments stay: Check it now, Check and start again, pace or slow down, readings, logbook, ledger, reports. The board's controls call the same code as Automation's switches. | — | 0175 §6 L250-251; 0171 §14 L640 (A137, B85) | Regression: each still present; Review: controls call `Automation`. |
| R146 | Start the system is one button (with Pause everything beside it) that fires each step itself, carried on by a tick every 10 minutes so the page can close, never on staging; it confirms every workflow and task within minutes, then switches the confirmed ones on in order without waiting on runs, and watches each alone (a gate's 2 good runs or 6 hours); one that misbehaves is stopped alone, with its fixes in place. Its home is the board's Start-up stage. | "We need a ondemand system start which will fire each of the steps you expect me to flip."; "the start needs to confirm it's operational within minutes similar to the preflight…" | 0163 L319-344, L348-351, L357-362; 0171 §2 L159-160; 0175 L437-444; PF 242-245, 259-264 (A127, C110, C111, C112, D66, D116) | Scenario: Start on live-like staging data in dry mode: confirm phase within minutes; refuses on staging; a misbehaving one stopped alone. |
| R147 | Shut-down is orderly, for one process or the whole system, in start-up order reversed (tasks, workflow, worker's schedule); what is running finishes or is handed back unchanged (a crawler run ends at its next task, leases go back, a site job skips its next run); the board shows "stopping" until nothing is in flight, then "stopped, safe", listing what it left; before it stops it names what depends on it; stopping is never refused for a state reason. | — | 0171 §2 L163-172; MK 890-1026 (A128-A131, D26) | Scenario: shut down W1 mid-run → dependants named, leases returned, "stopped, safe" with its list. |
| R148 | Pause everything stays as the emergency stop. | — | 0171 §2 L173; MK 433-439 (A132, D15) | Rendered and Scenario: present and stops everything. |
| R149 | Shutting down one workflow or module from its row is recorded with who, as Deactivate. | — | 0175 §6 L253-254 (B87) | Staging: row Shut down writes who. (see Q14) |
| R150 | Maintenance: a part is taken out on purpose with a reason and "Back by"; while out its alarms are notices, Start skips it and says why, it shows blue; Put back runs its checks first (a red check keeps it out with the reason), then starts it. A window can be set ahead: it goes out and comes back by itself, both logged; a window reached late still goes out and logs how late, one whose Back by passed first is logged as missed. | — | 0171 §2 L174-188; CB 121-148, 130-131 (A133, A134, A136, D77, D78, B10) | Scenario: take W3 out → blue, notices, Start skips; window in 5 minutes → out and back, two log rows; run the watch late → "how late" logged. |
| R151 | The upkeep calendar lists what is due from what the code schedules (night-window bulk loads, monthly loads, Data cleanup's daily pass, probes, the nightly pre-flight, staging's nightly pass). | — | 0171 §2 L180-186; MK 573-612 (A135, D25) | Rendered: each scheduled source listed. |
| R152 | The system runs on live only (Start refuses on staging); staging shows live's picture with the controls greyed. | — | 0171 §13 L625-626 (A138) | Scenario: Start on staging refused; controls greyed. |
| R153 | Each workflow and service comes back at half budget for its first 14 days. | — | 0163 L317-318, L336 (C135) | Unit on the ramp. |
| R154 | Jason can change every workflow's settings in the admin (task frequency, share, limits); each save is a version with "go back to it" and passes the switch-on check before it runs; director-tuned settings keep who and when, with a Reset. | "confirm that the build regarding the knowledge engine will allow me to modify the workflows if I choose to" | 0163 §3 L84-94, §12 L539, L579, L593-599 (C98, C99, C100, C148) | Staging: edit a share, a version is kept, go back works. |
| R155 | The identity recheck is a W2 task on the switches, shipped off; its runs, merges, sends and leftovers are on the board. | — | 0163 §12 L614 (C133) | Rendered: the recheck task on W2's page. |
| R156 | Releases: a build that fails its staging checks withdraws itself; a bad `release.json` can't strand a release; a rebuilt zip under the same name isn't stuck; the Releases page updates to current states every 15 seconds. | "having to require me to remove bad releases is not helpful"; "release page needs to update to current states" | CB 424-426, 620-625, 653-665; PB 113-117 (D79, D117) | `self-withdraw.php`; Scenario: send a failing build → withdrawn by itself. |

### Area 10. Services: keys, caps, paces, spend

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R157 | One module handles adding, activating, deactivating and removing a service: the Service register (https://traversence.com/admin/config/register), opened from the board; each service one card under its company's group row in the admin's one table, its stage in one column with ⋯ for its buttons, every service listed once with the workflows and tasks using it and where its key lives (named, never shown). The card folds by section, issue and stage first. | "We need a module that handles this."; "Put it in the Control Board's Service register…" | 0173 L28-31, §1 L74-91, L198, L202; 0163 §1 L55-57 (B103, B105, B106, B120, C91) | `register.php`, `proctable.php`; Rendered: every service in `service-defs.php` once. |
| R158 | A key is added on its card through the site's password box (Show/Hide, nothing shown back), through one writer for every `.env` key a definition names: owner only, never on staging, the value never shown, logged or reported (the log names who, when, which key), the old value kept as `# OLD` until a Check passes. Check runs at once with the new key: a pass forgets the old one; a fail says what the service answered and offers Put the old one back. Remove goes through the same writer. | "See how api is reference is here. Same issue for add a service." | 0173 §3 L112-124 (B104, B111-B114) | `keys.php`: non-owner and staging refused; the log holds no value. |
| R159 | Railway's keys (Google Places, Tavily, Brave, RIDB, Hugging Face) stay on Railway: the card gives the link and its Check confirms from the worker's report. The Park Service's key comes from https://api.data.gov/signup/. | "Keep on Railway" | 0173 §3 L125-135 (B115, B116) | Rendered: the card's Railway link; Check reads the worker report. (see Q4) |
| R160 | Each service is defined once (company, purpose, price, hosts, keys and where each lives, notices, contact, ledger, caps, staging answer, probe and pre-flight test, stage on shipping); the code's lists are generated from it, and the zip check blocks a release whose lists differ. | — | 0173 §4 L139-155 (B117, B118) | `defs.php`; a hand-edited list refused. |
| R161 | Each service's cap and pace is one setting the code reads (Railway's and `.env`'s copies retired); every path, the website's own calls too, goes through the register's checks and budgets; outside calls pass the staging gate; a Check costs nothing. | — | 0163 §1 L58-60, L69-70, §6 L116, L120; 0173 §5 L167-169 (B119, C92, C96, C149) | grep: no caller bypasses `ServiceHealth::allowed()`; staging gate scan. |
| R162 | This month's use and cost, by our count and the provider's where it has a feed, side by side, a difference flagged (else "our count only"); what each service produced and its cost per result; costs per service, workflow and month against revenue and the community budget. | "…usage expense, service caps, active/inactive stats come together" | 0163 §1 L61-67, §2 L74-75, §7 L142-143 (C93, C95, C136) | Rendered card and Costs fold totals against the ledgers. |
| R163 | Caps and budgets warn at 50%, 75% and 95% (on the board, with raise, top up and pause as buttons); a trip only when out of budget. | "how did we already determine their management?" | 0163 §4 L98-100; 0171 §5 L285-286; CB 58-60 (C138, D59) | Scenario: push use to 51/76/96% → warnings; 100% → trip. |
| R164 | Every AI feature has its switch, model and provider, and runs a day and budget on AI use, each call counted, failed calls included; Gemini is paced through the day with 20% held for on-demand (background stops at 80%) and warns only on the unexpected (80% reached before 18:00 UTC three days running; reserve below 5%; a refusal or ban "held"); when its day is used it is held until the next day. Grounded search runs only if Jason funds it. | — | 0163 §7 L132-138, §11 L513-534 (C140-C144, C146) | Unit: a failed call is counted; pacing and warnings as stated. (see Q13) |
| R165 | Access to the register: `config.operations` to see, the notes and servers forms by their own permissions, the key field owner only. | — | 0173 §1 L90-91 (B108) | Staging as a non-owner: the key field greyed with its reason. |
| R166 | A hub's opening budget is a one-time line in the register and on the hub's readiness meter. | — | 0163 §2 L77-80 (C137) | Rendered: the line, or "not set". |

### Area 11. History, logbook, reports, incident reports

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R167 | Every reading, alarm, stage change and control is kept over time, for the system and each process. | — | 0171 §2 L189-190; CB 324 (A139, D119) | DB: rows for each kind. |
| R168 | Readings are written when a value changes and at least hourly, kept raw `control_readings_days` (30), then as daily rows (min, max, last, time red/amber/blue) for 400 days. | — | 0171 §6 L352-355 (A140) | Unit: a change → a row; no change for an hour → a row; rollup after 30 days. |
| R169 | Trends per process, summed per system: runs, records in, stored and refused, retries, slow calls, spend against cap, speed and uptime (hours green or amber over hours it should have been on); drawn inline, no library; 24 hours, 7 days, 30 days; each strip says what it counts; with no history, "No history yet: readings start now". | — | 0171 §6 L356-359; CB20 98-101; PB 58-60 (A141, D85, D119) | Rendered: three ranges, labelled strips; uptime matches the formula. |
| R170 | The logbook: every stage change, switch, control, alarm and acknowledgement, with who (staff or "the system", never a member) and why, newest first, filtered by system or process; read from the existing logs, with alarms, shut-downs, maintenance, each control pressed and an operator's own note ("Log an entry", spell-checked); times shown in the zone where they happened, labelled. | — | 0171 §10 L543-558; CB 201-212, 325 (A143, A144, D113) | Scenario: each kind of event → a row; filter by process works; times carry their zone. |
| R171 | A closed thread goes to the logbook as the finished story: raised, each check, how it closed, who. | — | 0176 §4 L180 (C87) | Logbook entry after a recovery. |
| R172 | Reports daily (5 am Arizona, with the daily report), weekly (Monday) and on demand for any system or process: uptime, what ran, records in/stored/refused, slow, retries and crashes, spend against caps, alarms raised/acknowledged/cleared and time open, shut-downs and maintenance, switches, trends. Each opens as a page that reads well on a phone, with Download (CSV and the page as a file); nothing private. | — | 0171 §11 L562-578; CB 249-256, 326; PB 36 (A145, A146, D120, D122) | Scenario: make each report; every section present; download at 390px; no emails, phones or addresses. |
| R173 | **Every trip gets an Incident Report**, numbered IR-####, opened when the trip happens and kept after it closes. In order: the warnings that led to it (each with its ID, when it began, what came back, what the system tried and each try's result); the trip with its ID (why, what it paused, what waited on it); what followed (the Escalation Notice, who acted and what they pressed, Recovered or the fix); plain explanations at each step; the evidence (the run, the records touched). Trips only: a warning that clears by itself keeps its ID in the logbook and appears in a report only when it led to a trip. | "A trip needs a trip gets an incident report, detailing exactly what happened leading up to the trip. Warning id and trip id with explanations that I can reference."; "Trips only" | 0175 L498-512; PLAN 89; SM 108-109 (B126-B129, D105) | Scenario: each trip creates one report with every section in order; a self-clearing warning creates none. **Not built** (see section 7). |
| R174 | The Incident Report is linked wherever the trip or its warnings are referred to: the board's alarm, Pulse, the SYSTEM ALARM NOTICE email and push, the card's issue box, the logbook and the reports; its number shows on each and opens it. | "I want these Incident Reports linked to the trip or warning when referring to the situation." | 0175 L500-509 (B130, D105) | Scenario: the IR number on all seven surfaces opens the report. |
| R175 | Uptime per process is on its page and in every report, so downtime is seen and counted. | "down time is bad" | 0171 §9 L538 (A142) | Rendered and in each report. |

### Area 12. Phones and rendering

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R176 | On a phone the systems stack in one column; a system opens in place, a part in a drawer; nothing is wider than the window at 390px with every tab, system, panel and alarm open. | — | 0171 §1 L120-121, §12 L606; CB20 35 (A148, D124) | `board-phone.cjs`, `layout-probe.mjs` at 390 with everything open. |
| R177 | Jason works mostly from his phone: short lines, one line per item; a greyed button's reason is shown as text beside it (a phone has no hover); the admin's top bar is solid on phones. | — | SM 4-5; CB 222; PF 269, 273 (D11, D126, D127) | Rendered 390: reasons visible; top bar solid. |
| R178 | The first view is one small query (under 300 ms on the server); a part's data loads when it is opened; the board redraws every 30 seconds. | — | 0171 §9 L531-533, L205 (A149) | Timing: first view under 300 ms; network: part data loads on open. |
| R179 | Every board view is checked at 1280px and 390px, and each release's new buttons are pressed once on staging. | — | 0171 §15 L658-660; PB 86-87; PF 284; PLAN 79 (A152, D125) | The release record lists each view and press. |

### Area 13. Build rules for the board (process)

| R# | Requirement | Jason's words | Sources | Test |
|---|---|---|---|---|
| R180 | One build, built right: one session through the whole board, one coherent release, sent with `"review": true`, with a scenario test that causes every cell of the failure map on staging and rendered checks of every view. | "I want a plan that ensures one build, built right, and achieves what I want and need"; "go with 3, but keep it here" | PLAN 3-4, 72-83; CB 45, 394; PB 107-109, 140-142 (D8, D128, D132) | Review: one release; scenario results attached. |
| R181 | Before building, visual mockups of every view at 1280px and 390px, filled with real data from the live site, and a page showing one case per kind of failure (the board, the Pulse row, the email and the push, then Fix it and recovery); Jason's mockup is the starting reference. Jason approves the one document once. | "I want visual mockups too" | PLAN 54-67 (D8) | Review: mockups use live values only. |
| R182 | Every behaviour is sourced; an unsourced one is a question, never a guess; his answer is written into the decision as a dated note before building; answers are read literally and never asked again to re-confirm. | "Why is this session inventing things instead of check what's already set by adr?"; "We dont need to reinvent rules" | CB20 11-15; PB 78-85; SM 40, 50-52 (D129, D137) | Release summary: every line has a source. |
| R183 | Replacing a screen keeps everything it did, with a list of every old control kept, moved or dropped; a drop needs `--drops` and his OK. What 0175 doesn't change stays (0173's register, keys and Retire; 0171's board, alarms, logbook, ledger, reports, maintenance and escalation; Start's order and gates). | "These issues need to be caught!" | PB 98-103; 0175 §8 L282-288 (B134, D130) | The kept/moved/dropped list, each kept behaviour rendered. |
| R184 | Each new state gets an "Admin's test" line (what he sees, what it means, what he presses). | — | PB 61-63 (D133) | Release summary has the lines. |
| R185 | No scope creep or drift: each step is reviewed against this whole list, not only the lines it cites. | "consistency = scope creep or drift" | SM 29-34; PLAN 97 (D134) | The session manager's review. |
| R186 | While the plan runs: no one-off board patches; a live problem that harms the site or its data is still fixed at once and recorded in the audit; clean-up fixes that touch the system belong to the board session. | "clean up fixes that touch the system should move into the system board session…" | PLAN 93-96; C51 3-5; CB 586-589 (D9, D139) | Review. |
| R187 | A build that fails staging is the session's to clear; releases may stack while Jason is away, each its own PR, merged only after his Approve; queued work gets progress lines. | "Since you create them, you need a way to deal with issues"; "if you need to stack releases in a que until I am available…" | PB 113-117; SM 132-137, 166-170 (D135, D136, D138) | Review. |
| R188 | The board doesn't change how any workflow gathers or any 0164 rule, doesn't change Railway's cron or restart policy (the site sets them), spends no money, and moves no data between databases. | — | 0171 L687-690; CB 527-528 (A163, D118) | Review: no new paid service; no cron change by a session. |
| R189 | After it's live: the scenario test runs again at every release touching the board; a daily check that each open alarm's hold, words and Fix it match the map (a mismatch is a case for the building session and the session manager, never Jason); after a week, a report to Jason of what tripped, what recovered and any mismatch. Jason should not have to find the broken pieces himself. | (2026-11-21, recorded in the plan) | PLAN 6-9, 33, 85-91 (D7) | Review after a week. |

## 4. Questions for Jason

Each was checked against its source lines; questions a later dated note already settles are in section 5. Ordered by
how much they change the failure map: what a fault holds, how tries count, when it trips, then who is told.

**Q1. "Keep the hour" for a task that can only run at night (case #51).**
- One side: 0171's note of 2026-11-20 (L712-717), which Jason chose in the pop-up: "Keep the hour: as 3c set it, after
  `control_fix_minutes` (60) with no fix, it's a trip", for a bulk task that "runs only 1–5 AM Arizona, so a second try
  can't come inside the hour".
- Other side: 0171 3c (L289-291) "the system tries again by itself, for `control_fix_tries` (default 3) or
  `control_fix_minutes`…, then a trip"; the playbook's "the system fixes first" (PB 42-46); case #51's brief asked for a
  task's tries to count its own runs (C51 20-24).
- Why it matters: case #51. `overture_bulk` timed out once; with no second try possible inside the hour, the hour ran
  out and it tripped (and, through Q2, paused all of W1). Under "Keep the hour" a night task never gets its 3 tries.
- **(Recommended)** Keep the hour for everything that can try again within it. For a task that only runs in a window,
  count its tries by its own runs: one failure is a warning until its next window ("Trying again tonight, 1 of 3");
  it trips only when its tries run out. Reason: it keeps the system-fixes-first rule and doesn't trip on a failure the
  system never had a chance to retry.

**Q2. What does one task's own failure hold: the task, or its whole workflow?**
- One side: 0171 3c (L392-393): "a task's failures stop that workflow; a task, that task". The live code does the first:
  `ControlAlarms::stop()` pauses the whole workflow for a failed task (`$pause('wf', …)`, L285 on the mirror at 37a212e),
  though the alarm's own words say "the workflow's other tasks carry on" (C51 table).
- Other side: 0175 §3 (L145-149) "It pauses only what its cause touches … a task; a workflow"; 0175's note of
  2026-11-21 (L520-521) "It holds the whole workflow only when every task calls it" (that note is about a source down);
  0176 L196 "as narrow as its cause"; PLAN 28 "A trip stops only what it holds".
- Why it matters: case #51 (W1 paused whole for one task's timeout). The same code also pauses only the *first*
  workflow of a site job tied to several (`$wfs[0]`, L281), the pattern 0175's note rejects for needs.
- **(Recommended)** A task's failure pauses that task only. Its workflow is paused only when every one of its tasks is
  paused, or when the failure is the workflow's own (its run failing outright). A site job tied to workflows pauses the
  tasks it feeds in each of them. Reason: 3c's two clauses can't both be read plainly; every later note narrows.

**Q3. Our own site timing out or failing (5xx) when the worker calls it: a crash, or slow?**
- One side: 0171 §5 (L284): a **Crash** includes "an endpoint answering 5xx", a warning while trying, then a trip.
  Today the worker reports our timeout as the task failing (C51 cause 2).
- Other side: 0171's note of 2026-11-19 (L299-302): "slow is never a trip … stops nothing and clears itself"; the
  playbook (PB 47-52): stop only for harm; slowness clears itself.
- Why it matters: case #51's timeout was ours (sending rows to `/api/crawl/loads.php`), yet it counted against the
  outside task and tripped it.
- **(Recommended)** Our endpoint's timeout is slow: a warning on our endpoint's part that clears when the task does its
  work; nothing paused. A 5xx from our endpoint is a crash of that endpoint (the Site part), with its tries, never
  counted against the outside source or the task. Reason: the fault is ours, so the alarm and Fix it belong on our part.

**Q4. A Railway key the crawler hasn't reported yet: does it hold a workflow?**
- Source: 0175's note of 2026-11-21 (L537), the audit's question "a Railway key not yet reported holding a workflow",
  left "still to ask" (L543). 0173 §3 (L125-133): Railway's keys stay on Railway, the card's Check confirms from the
  worker's report. Nothing decides what a missing report means.
- Why it matters: after a redeploy, until the worker reports, a workflow could be held (or a red line shown) for a key
  that is in fact there.
- **(Recommended)** Amber "Not checked yet: the worker hasn't reported its keys", holding nothing; it turns red (and
  holds the tasks that use the key) only when the worker reports the key missing or refused. Reason: a hold needs
  evidence (principle 1, "the system fixes first").

**Q5. When is a light red, and what does a core host down hold?**
- One side: 0171 3d (L512-513): "Red only for a trip or a core host down (Bluehost, Railway, GitHub)"; the mockup
  (MK 542, 738): "Core: … (Red holds everything)".
- Other side: 0171's critical note (L325-326): "Not critical by themselves: … Railway or GitHub as hosts"; 0175's notes
  (L431-435, L489-491): a system's hour and the health line are "red only when a critical part is down"; PB 32-33: a
  hold as narrow as its cause; 0171 3c (L394): the master only when the crawler itself is down.
- Why it matters: a non-critical trip's own row, a down Railway and a down GitHub each read differently on the row, the
  system line and the health line today, and "Red holds everything" would pause far more than its cause.
- **(Recommended)** Rows: red for an open trip (it needs a person). Summary lines (system hour, health line): red only
  for a critical duty down. Bluehost down is critical (its duties are page load and the database). Railway down means
  the crawler is down: all gathering pauses (3c), red on its row, amber on the health line. GitHub down holds releases
  only. No "red holds everything". Reason: matches every dated note; nothing is held wider than its cause.

**Q6. A site or service that refused us: when its check later passes, does it close by itself?**
- One side: 0175 §3 (L152): a pause "clears itself when its check passes"; 0175's note of 2026-11-20 (L380-384): even
  an escalated trip "closes as Recovered" once its check passes.
- Other side: 0171's osm-copy-wider note (L472-473): "a refusal, a refused key or a used-up quota always waits for a
  person's button (principle 1's exception)"; 0163 (L150-154, L184-185): a banned service has "no automatic return: a
  passing check raises 'looks lifted' with Reactivate"; a site that blocks us is "never retried by itself".
- Why it matters: a refused key or used-up quota can only pass its check after a person acts, so both rules agree there.
  A ban or a blocked site differs: under 0175 it could reopen by itself and reach a site that asked us to stop.
- **(Recommended)** Keep the person's button for a ban or a block: a passing probe says "looks lifted" with Reactivate,
  and Reactivate runs the check and closes the trip. A refused key and a used-up quota close when the person's own fix
  passes its check (as built). Reason: going back to a site that refused us is a person's call (0164 "curators").

**Q7. When a non-critical part's tries run out: is the Escalation Notice the same moment as the trip?**
- One side: 0176 §4 step 2 (L176): when the tries run out, the trip is raised "and since its tries ran out, 0175 §3's
  Escalation Notice", at once, on the same thread; step 4b (L179) then has "the level rise brings one push".
- Other side: 0175 §3 (L157-164): for a critical part the Escalation Notice comes when its tries run out *after* the
  first-failure trip; "Any other part: a warning while the system tries, Paused by the system when it can't. It's
  escalated the same way"; 0171's note to §8 (L705-708): escalation is "to the Manager once its level's hours have
  passed, to the Director after twice that". Trips and Escalation Notices are both Critical (0176 L379), so a "level
  rise" between them has no level to rise to.
- Why it matters: it decides how many messages Jason gets for one fault, and what "escalated" means on the board.
- **(Recommended)** For a non-critical part: one message at the trip ("Paused by the system"); no separate Escalation
  Notice. "Escalated" means Pulse's clock: the Manager at 2 hours, the Director at 4, each told by their own channels.
  The "Retries unsuccessful, now admin required" notice stays for critical parts only, as 0171 wrote it. Reason: fewer
  messages for one thing; Jason: "the action and timeline is set".

**Q8. A critical part's first failure pauses nothing: is it a "trip"?**
- One side: 0171's critical note (L319-320): "a trip at once … the system keeps trying and nothing is stopped".
- Other side: 0175's note (L375-378): "A trip is when the PAUSE has been placed"; 0171 3c (L388-390): a trip means
  "ceasing internal process until admin resolves it".
- Why it matters: the board then shows trips that hold nothing, and the top-bar count, the light and the Incident
  Report (R173, trips only) all depend on the word.
- **(Recommended)** Call it **"Critical part down"**: red, counted with the trips in the top bar, sent at once on all
  three channels, with its own Incident Report; status "Down: the system is retrying". It becomes a trip (a pause) only
  when its tries run out. Reason: the words stay true to "a trip is a pause".

**Q9. Who is told of a trip at once: the Founder only, or also that system's staff?**
- One side: 0172 §2.12 (L572): a trip is told to "Founder; the process's staff"; §1.7 (L402): Executives and Directors
  get "trips in their systems".
- Other side: 0171 §7 (L409-410) and 0176 §4 (L176): "the Founder's email"; others reach it only by escalation (0176
  L179, 0171 L705-708).
- Why it matters: whether a Director learns of a W1 trip at once or after 4 hours.
- **(Recommended)** The Founder at once by email, push and Pulse; the Pulse item sits in the system's own department
  queue, so its Manager and Director see it there at once without an email or push; they are told by their channels on
  escalation. Reason: one person paged, the team informed, no new channel.

**Q10. Railway's deploys and the sync's runs failing: a Pulse case, or the board's rule?**
- One side: 0163 §5 (L110-112): the site "raises a Pulse case when one fails or stalls".
- Other side: 0171's note (L427-435): "only a trip reaches a person"; §8 (L477-488) turned the other old Pulse cases
  into alarms, but not these.
- Why it matters: a failed deploy stops the crawler; today it reaches Jason outside the board's rules and words.
- **(Recommended)** Make them alarms on the Workers and Site parts under the same rules: a failed deploy is a crash
  (warning while Railway retries, a trip when it can't, and then the crawler-down hold); a stalled sync is a warning,
  a trip after its hour. Reason: one set of rules, one thread shape.

**Q11. Grouped alarms: one message per group, or per alarm?**
- One side: 0171 §8 3d (L504-511) and 0172 (L139-142): related alarms are one row on the board and one item in Pulse;
  0176's note (L346) titles a notice "<item or group>".
- Other side: 0176 §1 (L100-103) and its note (L352): "One thread per alarm on each channel".
- Why it matters: five feeds held by one cause could send five emails and pushes, or one.
- **(Recommended)** One thread per group while two or more share it: the group's title ("5 engine feeds held"),
  updated as members join or clear; an alarm that leaves the group keeps its own thread. Reason: Jason's own format
  names "(Item or Group)".

**Q12. What does Acknowledge do now?**
- One side: 0171 §7 (L424-425): acknowledging in any place acknowledges all and "stops the email repeat"; §8 (L496)
  "Done in Pulse means acknowledged"; 3d (L506-507) "Acknowledge all" on a group; the mockup's Done (MK 468, 954-956).
- Other side: the links note (L400-402): Check and start again "is also its acknowledgement"; 0175 (L457): a warning
  has no Acknowledge; the email repeat is dropped (L432); trips close themselves (0175 §3).
- Why it matters: a button with no clear effect is a dead end; and whether acknowledging stops the escalation.
- **(Recommended)** No Acknowledge on warnings or warning groups. On an open trip, Done/Acknowledge means "seen by me":
  logged, shown on the row with who, doesn't close it and doesn't stop the escalation (which runs until it closes).
  Reason: Jason's "YES" was to escalating a trip no one *resolves*.

**Q13. Gemini's day used up: does the work wait, or use the paid Claude backup?**
- One side: 0163 §11 (L528-529): "the work waits; no fallback to Claude".
- Other side: 0163's source map (L441): "Gemini (free tier) → $ Claude (AI cap)"; #103 (L502-503): "Claude reads pages
  when Gemini can't", off until a director sets its cap.
- Why it matters: the board's "What it affects" and "What the system is doing" for W1-W3, W10 and W11.
- **(Recommended)** "Can't" means a refusal or an outage, never a used-up day: a used-up day waits (amber "Waiting for
  tomorrow's allowance"), even with the paid backup's cap set. Reason: keeps the daily cap honest (no income, no
  expense).

**Q14. A person's quick stop: Pause or Deactivate?**
- One side: 0175 §6 (L253-254): "Shut down one workflow or module stays on its row, as Deactivate"; Deactivate needs
  its pre-flight to come back (L362).
- Other side: 0175 §2 (L133): Pause "stays active, keeps its key and settings"; today's register Pause is `svc_off`,
  "Nothing calls it", back by Activate through the gate (0173 L101), which is closer to 0175's Deactivate.
- Why it matters: in an incident, a stop that needs a full pre-flight to come back is slower; and R3 renames today's
  buttons.
- **(Recommended)** The board's row Shut down is a person's Pause (Resume runs its pre-flight, as 0175 L362 says);
  Deactivate is the card's on-purpose switch-off with why; today's register Pause maps to Pause. Reason: Pause is the
  light, reversible action; Deactivate is a decision.

**Q15. Are 0175 R3 and R4 part of this one board build?**
- Source: 0175's status (L5-6): R3 (the eight stages and actions on the card) and R4 (one definition: `kind`, `repair`,
  `fallback`, the zip check's refusal) are held; the plan wants one release (PLAN 72-83).
- Why it matters: R4's `repair` and `fallback` decide what a fault holds (R95), so the failure map needs them; R3 is the
  cards' look.
- **(Recommended)** Put R4's `repair` and `fallback` fields in this build, since the map reads them; leave R3 (the
  card's stage strip and renamed buttons) for its own release after. Reason: one coherent failure map without growing
  the release into the cards' redesign.

**Q16. The counts' check: is "Review" its own count?**
- One side: 0171 §9 (L536-537): "received = stored + updated + refused"; the mockup's bar (MK 831) the same.
- Other side: CB 409: "Counting rule: received = stored + updated + Review + refused, judged in release 3".
- Why it matters: a record sent to Review would read as lost ("Mismatch!") on every call that uses Review.
- **(Recommended)** Count Review on its own: received = stored + updated + Review + refused, shown as its own segment.

**Q17. "Use backup" as a button: what should it do?**
- Source: 0176 §5 (L210, L405): "Use backup as a button is to come (A5; 0175 §3 reroutes by itself)"; it shows greyed.
- Why it matters: a button greyed "to come" for good is a dead end; if the system reroutes by itself, the button has no
  job unless it forces the backup for a source that is slow but still answering.
- **(Recommended)** Drop the button; show "On a backup: <name>" when the system has rerouted, and nothing when it
  hasn't. Reason: the system fixes first.

**Q18. The 2 AM run: keep it, and does its failure raise anything?**
- Source: 0175 (L369-370): "The 2 AM run may stay as a nightly self-test that feeds the cards." Nothing says whether its
  red line raises an alarm, or which hour rule applies.
- **(Recommended)** Keep it as a self-test that updates the cards' Diagnostics; a red line raises the same alarm as any
  check, under R50 (its words follow its line). Reason: it's free and finds faults before the morning.

**Q19. Two numbers no decision sets.**
- 0171's Consequences (L702-703): every new table has "its kept-days settings", but none is named for alarms, the
  logbook or the maintenance windows; §9 (L534-535): `control_cost_factor` has no value.
- **(Recommended)** Alarms, the logbook and maintenance windows kept 400 days (as the daily readings); Incident Reports
  kept for good; `control_cost_factor` 3 (as `control_slow_factor`). Reason: the reports and Incident Reports read
  back that far.

**Q20. "Every press runs the Admin's test first": what is that test?**
- Source: 0173 §2 (L106): "Every press runs the Admin's test first, as the board's buttons do." Nothing defines it.
- **(Recommended)** Read it as: each stage button checks first and shows what it will do (and greys with the reason when
  it can't), as Check and start again does. Reason: matches the board's buttons; no new step.

**Q21. Three layout choices for step 3's mockups.**
- The one-line diagram ("mimic board": sources → workers → endpoints → tables → outcome, each box lit by its state; CB
  318, SM 176-178), "to discuss", never held.
- §2's stage tiles and separate buttons were "the session manager's call when Jason was unsure" (0171 L7; CB 9-11); his
  mockup puts numbered buttons beside a stage strip (MK 433-450).
- His five dashboard references (SM 172-189: tabs per system, a trend panel with a stats table, fault lights for
  critical duties, capacity bars per service; skip big gauges, pies, slicers).
- **(Recommended)** Show all three in step 3's mockups, with real data, and let him choose there. Reason: the plan's step
  3 is where he sees and approves the design once.

**Q22. Fix, Disconnect and Add source on a need (from his example board).**
- Source: CB 285-286, 461-463: not built, since they would change what holds a workflow, against 0163's source map; "a
  decision comes first".
- **(Recommended)** Not in this build. A need's sources stay in the code's source map; the board shows them and their
  order, and Fix it covers keys and caps. Reason: changing the map from a page needs its own decision.

## 5. Replaced lines and settled questions

| Old line | Replaced or settled by | Deciding words |
|---|---|---|
| 0171 §5 L282: slow "a trip after `control_slow_trips` in a row" (A51) | 0171 note 4e L299-302 | "slow is a warning that says how slow, stops nothing and clears itself … control_slow_trips is retired, kept" |
| 0171 §7 L385-386: "cleared, not seen" for 24 hours (A79) | 0171 note L331-333 | "a cleared one is in the logbook … This replaces §7's 'cleared, not seen' listing" |
| 0171 §7 L409-413: a warning unacknowledged 24 h is emailed; warnings get Pulse items (A111); 0172 §2.12 L573 (C51) | 0171 note routes L427-435; 0172 §4.5 L816 | "Warnings … go nowhere but the board … the 'warning still unacknowledged after 24 hours' email above is dropped" |
| 0171 §7 L424-425: acknowledging "stops the email repeat" (A118) | 0171 L432 | (the repeat email is dropped) — what Acknowledge does now is Q12 |
| 0171 3c L396-398: "Only an executive's Resolve clears it: it asks what fixed it"; critical note L324 "only Resolve clears it" (A83, D56) | 0171 note 4e L304; links note L400-402; 0175 §3 L152; 0175 note L380-384; 0171 L710 | "An admin's Resolve is Check and start again (no question to answer)"; "It clears itself when its check passes"; "Close as Recovered … once its check passes, nothing waits" |
| 0171 3c L391: a trip's Pulse item "goes to the Director's level" at once (A82; A's C6, C's Q6, D's Q10) | 0171 note to §8 L705-708, with 0105 L90, L186 | "to the Manager once its level's hours have passed, to the Director after twice that" (Critical: 2 hours) |
| 0171 3c L392 "It stops what it holds"; 3d L514-515 "A trip marks its process red and 'Tripped' even where it switched it off" (A's C5) | 0175 §3 L150-151; 0171 L710 | "It writes nothing into anyone's switch"; "a fault pauses, never switches off" |
| "Mark resolved": 0171 §8 L503 (A125), Overpass L519-520 (A107), 3c L398 "Mark resolved, Reactivate" closes it (A's C8); the Start card's "Mark issue resolved", listed as "Jason's own" in SM 333 (H6) | 0171 card note L455-458; osm-copy-wider L465-468 | "marked resolved - this should not be an option"; "The Start card's service issue carries the board's one button … never a bare Mark resolved". Built: `SystemStart::issueButtons` has none. Leftover: `Pulse.php` L1293's fallback step still reads "Mark resolved" (an audit item, not a question). SM 333's line is stale. |
| 0171 §8 L517-520: the Overpass trip "Every Overpass server is held" (A107; D's Q12) | 0173 §4 L157-163 (Overpass Retired); CLAUDE.md 2026-11-19 | "Overpass is the register's first Retired record"; no Overpass alarm remains to press |
| 0171 §5 L297, §7 L414 and 0172 L401, L572: the push "at the session manager's next hourly check" (A116, C49; A's C4, C's Q1) | 0171 critical note L319, routes note L429; 0176 §4 L176, note L344, L379 | "a trip goes to Pulse, the Founder's email and the push at once"; "a trip is an immediate notification". 0172's two lines need correcting (words true). |
| 0171 osm-copy-wider L469-472: "Cleared by its check" for back-off kinds, "asked of him as its own question" (A's C3) | 0175 §3 L152; 0175 note L525-527 | "It clears itself when its check passes"; "the site's 2xx at a host closes the crawler's back-off there too" |
| 0176 §4 4a L178 "closes quietly" vs note L337-338 "Recovered goes as a reply" (C's Q4) | both hold: 0176 L178, L337-338, L372-374 | "Notice level, so no push by default"; "later news … goes as a reply in that thread" |
| 0171 §1 L139-144: red "tripped, crashed or not ready" (A18) | 0171 §8 3d L512-515 | "Red … only for a trip or a core host down … A new light, Held" (what's left is Q5) |
| 0171 L193-200: the board's own find box and Open all/Close all give way to the table's search (A25) | 0171 itsdata note L201-207 | "The counts on each system's line are links … Open every system / Close them" restored |
| 0171 §1 L119: tapping a system opens its gauges (A11) | 0171 table note L193-197 | parts as rows of the admin's one table with group rows |
| 0171 §12 L585: a process page `/admin/config/control/<process>` (A154) | 0171 L652 | "`/admin/config/control/<kind>-<name>`" |
| 0171 §14 L634, §15 L673: "The Pre-flight page is archived" (A160; A's C15, D's H19) | 0171 L7 (kept, 2026-11-19); 0175 note L419-420 (moved into Modules and Start-up) | "the system pre-flight goes into the board's Start-up stage" |
| 0171 §14 L635, L654: links from Automation and the Workflow board "still to do" (A161) | 0175 note L421-422, L437-444 | the Workflow board merges into Workflows; "Automation's address leads there" |
| 0175 §6 L252-253: Start, Pause everything, Shut down "stay … on Automation" (B86) | 0175 note L437-444 | "from release 2b, the master's and those three presses' home is the board's Start-up and Shut-down stages" |
| 0175 §7 L277: switches no person set go back to what Start set (B52) | 0175 L494-496 | "Keep for a person … nothing switches them back on by itself" |
| 0175 §6 L262-263: the card links to the one switch, "never a second one" (B89; B's Q8) | 0175 note L461-462 | "Keep both" — the card's Switch on presses the same switch |
| 0173 §2 L93-103: seven stages Proposed … Removed (B16) | 0175 §1 L103-122; 0173 note L204 | Jason's eight stages; buttons and history kept |
| 0173 §5 L170-171: account apps keep their own lifecycle (B135) | 0175 §1 L123-126; L310-311 | "Two levels, one model"; sealed tokens unaffected |
| B's Q5: Delete vs Retired vs Removed | 0175 L121 | "Retire it… (now Delete, with Retired kept as Delete's record while code remains)" → Removed once a release drops the code (R37) |
| 0163 L317: "one workflow at a time" (C111; C's Q12) | 0163 note L319-326 | "confirms every workflow and task first … then switches the confirmed ones on in order without waiting on runs … and watches each one by itself afterwards (`start_gate_runs`, `start_gate_hours`)" |
| 0163 L352-355: one Pulse case "Starting the system" (C113) | 0163 L359-360; 0171 §8 L477-488 | "Pulse keeps only a notice"; the five Pulse cases "become alarms" |
| 0163 L98-106, L184, L401-402: Pulse cases for 50/75/95%, "returns empty", a blocked site, a pre-flight line turning red (C's Q3, except Railway and the sync: Q10) | 0171 §8 L477-488; routes note L427-435; itsdata note L445-449 | they became alarms; warnings go only to the board; blocked sites are one notice |
| 0163 L391-393: each pre-flight item says required or optional (C118; C's Q9) | 0163 #98 L411; 0175 L531-532 | "every workflow, task, phase and the services they use are needed for launch"; `CENSUS_API_KEY` "is optional" |
| 0176 note L335-336: subject "W3 Sources: Paused by the system" (C29) | 0176 note L346-348 | `SYSTEM ALARM NOTICE: <item or group>: <action>` |
| 0176 L59-60: Notify's "once read, the next send is a new row" | 0176 §1 L103, note L325-328 | `Notify::IN_PLACE`: updated in place, read or not |
| D44: outside servers that time out "stay a warning, never a trip" (proposed) | refused: CB 235-239 | "We already have a rule for how trips get set" (never re-ask) |
| A's C10: a refused key on a primary that has a backup | 0175 §3 L167-171 | "two things are checked": the component trips and needs a person (told at once, 0176 L190); the workflow carries on "On a backup" |
| A's C11: does a 429 trip at its case limit? | 0171 L645; CB 219-222 | "a 429 is a warning that waits twice as long"; never a trip (`r5a-rate.php`) |
| A's C13: a red line on a paused-by-system workflow: "switched on"? | 0175 §2 L139 | "Its Activate switch stays as the person left it" → it counts as switched on (a trip) |
| A's C16: "stopping is never refused" vs greyed controls | 0171 §2 L171-172, §13 L622 | both hold: greyed only for permission, never for a state (a test note, R147) |
| B's Q3: Pulse trips only, or Recovered too? | 0176 §4 L178-179; 0171 L427-435 | Recovered is the trip thread's last state in its own Pulse row; warnings never enter Pulse |
| B's Q9: "Waiting on those" under the pause record | 0175 §3 L150-151 with L476-478 | read as: the parts the trip's pause holds, folded under its trip (step 2 checks the build) |
| B's Q10: does the Census failure raise anything while the backup covers it? | 0175 L456; 0176 L210 | "On a backup" is a working state; their trouble goes to the board only |
| C's Q8: Fix it landing on W0's page vs "never another page" | 0176 §5 L216-218, L393 | it opens "in the side panel … one panel at a time", on the board |
| C's Q10: what a service's trip holds | 0175 note L514-524 | the gate: nothing, its tasks, or the workflow only when every task calls it (R88) |
| C's Q11: "paused, never a failure, raises no Pulse warning" | 0163 L273-274 (a person's pause); 0176 L176 | a person's pause raises nothing; a system pause that needs a person reaches one |
| C's Q13: the page named Pre-flight while running | 0175 L365-370 | lines read Diagnostics for active modules; the system pre-flight sits in Start-up |
| D's Q1: six stages or eight? | 0171 §2 L151-154; 0175 §1 L119 | both: the board's six run stages for the system and each process; a module's eight lifecycle stages on its card |
| D's Q2: the mockup's "Slow Trip" | 0171 note 4e L299-302 | slow is a warning (amber), never a trip |
| D's Q4: a Health Token box on the board | 0171 §12 L612 | "no token box on the board" |
| D's Q8: who hears a warning | 0176 §4 L182-189 | "a fault the system is still trying to fix goes to the board and the card only" |
| D's Q13: a fault shown as Maintenance in the mockup | 0171 §2 L174-178; 0175 §3 L176 | maintenance is "taken out on purpose with a reason"; a fault is Paused by the system |
| A's C12, B's Q1, D's Q6 | Q1 | (asked) |
| A's C2, B's Q1-Q2, C's Q10 (task part), D's Q9 | Q2, Q3 | (asked) |

## 6. Numbers and settings

| Setting | Value | Source |
|---|---|---|
| `control_fix_tries` | 3 | 0171 L290; 0175 L160; 0176 L176 |
| `control_fix_minutes` | 60 from the first failure; also for a nightly task (see Q1) | 0171 L291, L715; 0176 L176 |
| Fault status words | "Trying again (1 of 3)" | 0176 L175 |
| `control_tick` watch | every 5 minutes; silence caught in at most 5 minutes | 0171 L271, L296 |
| Run not reported | its budget + 5 minutes | 0171 L274 |
| `control_slow_factor` | 3 × the usual time | 0171 L282 |
| `control_slow_trips` | 3, retired by 4e, kept | 0171 L282, L302 |
| Near a limit | 50 / 75 / 95% of a cap or budget | 0171 L286; 0163 L98 |
| Returns empty | 12 or more runs over 24 hours with nothing; a monthly load on its first empty run | 0163 L102-105 |
| Pre-flight line retested by the watch | when untested for 6 hours | 0171 L306 |
| `pf_fresh_mins` | 60 | 0171 L666 |
| `control_crit_requests` | 3 server errors in 5 minutes | 0171 L344 |
| Duty test | every 5 minutes; QuickBooks asked at most once an hour | 0171 L341-342 |
| Server-error hook kept | 7 days | 0171 L340 |
| Email for a trip | the same minute | 0171 L296 |
| Push for a trip | at once (the old "hourly" is replaced, section 5) | 0176 L176, L344 |
| Escalation (Critical) | Manager after 2 hours, Director after 4 (Pulse's Critical clock) | 0171 L705-708; 0105 L90, L186 |
| Push pace | 3 per 5 minutes per person, then one "N new in your Pulse"; Critical exempt | 0172 L23-24 |
| Staff push default | Alert and up | 0176 L141-142 |
| Levels | `alarm.trip`, `alarm.escalated` Critical; `alarm.warning` Alert (not sent); Recovered Notice | 0176 L178, L379; 0172 L816 |
| `w0_block_share` | 5%, with at least 20 read | 0171 L448 |
| `control_pulse_ms` | 2 s, middle of the last 20 | 0171 L451-452 |
| `control_records_per_call` | 500 | 0171 L244 |
| `control_ledger_days` / `control_records_days` | 90 / 30 | 0171 L248 |
| `control_readings_days` / daily rows kept | 30 / 400 days | 0171 L353-355 |
| Alarms, logbook, maintenance windows kept | not set (see Q19) | 0171 L702-703 |
| `control_cost_factor` | × its 30-day usual; value not set (see Q19) | 0171 L534-535 |
| Trend ranges | 24 hours, 7 days, 30 days | 0171 L359 |
| Board first view / redraw | under 300 ms on the server / every 30 seconds | 0171 L532, L205 |
| Undo of a Resolve | one day | 0171 L397 |
| Crawler's night window | 1-5 AM Arizona | 0171 L713 |
| Nightly self-test | 2 AM (see Q18) | 0175 L369 |
| Daily and weekly reports | 5 am Arizona; Monday; summary 3-5 lines | 0171 L563-564, L577 |
| Start's gates | 2 good runs or 6 hours; failed no more than 1 in 10; tick every 10 minutes | 0163 L335, L348-351 |
| Ramp | half budget for 14 days | 0163 L317-318 |
| Banned service's probe | daily, at most 3 days | 0163 L150-152 |
| Retry ladder (engine items, never a blocked site) | 1-2 normal; after 3: 7 days; after 6: 30; after 10: 180, then every 180 | 0163 L585-591 |
| Gemini | 20% on-demand reserve; background stops at 80%; warn if 80% before 18:00 UTC 3 days or reserve under 5% | 0163 L517-527 |
| Release job quiet | 30 minutes | CB 245-246 |
| Releases page refresh | every 15 seconds | CB 424-426 |
| Phone width | 390px | 0171 L121, L606 |
| Counts | 4 systems; 6 run stages; 8 lifecycle stages; 3 alarm levels; 5 duties | 0171 §1, §2, §7; 0175 §1; 0171 L316-317 |
| Incident Report number | IR-#### | 0175 L503 |

## 7. Held and not built

| Item | What | Source | State |
|---|---|---|---|
| The Incident Report | R173-R174 | 0175 L498-512; SM 108-109; PLAN 89 | Decided 2026-11-21, **not built**. Checked: `git grep "Incident Report\|incident_report\|'IR-"` on the live mirror at Sync 2026-11-21 12:26 UTC (`37a212e`), `sync-app/` and `sync-storage/` (the local checkout is sparse to those two folders, which hold all the site's code but trvgo's router), found nothing. |
| 0175 R3 | the eight stages and actions on each card | 0175 L303; CB20 131-136; OPS 68-70 | Held (see Q15) |
| 0175 R4 | one definition (`kind`, `repair`, `fallback`, `touch_points`), the zip check's refusal | 0175 L307 | Held (see Q15) |
| W6 and W8 "Follow 0163" | W6: Library of Congress → Wikimedia → the Park Service, no Claude; W8: Tavily → Brave → then Claude | 0175 L542-543; 0163 L449, L451 | Answered; a follow-up release, not built. W6's Wikimedia Commons licence call (store with credit, or link) is still Jason's (PF 134-137; SM 333). |
| A Railway key not yet reported | — | 0175 L537, L543 | Still to ask: Q4 |
| Case #51's draft | a task's trip pauses only the task; crawler 2.16.2's `SiteSlow`; 250-row batches with one retry | C51 14-18 | Held as evidence; never applied alone (PLAN 93). Its choices wait on Q1-Q3. |
| The worker reporting its retries | 0171 §5 "Retry" | CB 261-262, 277 | Not built (R46) |
| `control_state` | the board's one-row-per-reading table | CB 278; 0171 §9 L531 | Not built per CB; to prove in step 2 |
| A real "held by trip" flag | today a trip's hold is `hold_until = '2099-01-01'` | CB 258-260; 0176 L74, L267 | Shortcut in place |
| Use backup button | greyed "to come" | 0176 L210, L405 | Q17 |
| "No backup" and "repair needs shut-down" causes | their Fix it spots exist; nothing raises them | 0176 L399-400 | Not raised by any caller |
| Planned backups | hosting, Railway, the sync, payments, marketplace | 0163 L484-493 | A future build |
| Pulse read state per person | R136 | CB 262, 666-671 | Not built per CB 262; prove in step 2 |
| The one-line diagram and dashboard ideas | — | CB 318; SM 172-189 | Discussion not held: Q21 |
| Fix / Disconnect / Add source on a need | — | CB 285-286, 461-463 | Q22 |
| Scenario test schema | tests need `control_ledger`, `system_pauses`, `automation_switches`, `crawl_workflows` in a test schema (their migrations ran and were deleted on live) | CB 692-694; C51 33-36; DSP 223-226 | Open: step 4 needs it |
| Zip check's race with releases Planned or On staging | the `faq.md` race (#234/#235) | SM 148-152 | Open, for whoever next touches `tools/page-audit` |
| AI cap shares; hub opening budget; grounded search | sizes not set; none set; not funded | 0163 L79, L137-138, L530-534 | Not set |
| Claude usage feed | "our count only" (no Admin API key) | 0163 L142-143 | As is |
| 0172's stale words | "the hourly push" and "being built" in §1.7 and §2.12 | 0172 L401, L572 | To correct (section 5) |
| 0173's writer name | `EnvSettings::setSecret()` in §3; built as `setKey()` | 0173 L6-7, L114 | Words to align |
| 0176 R4's order file check | minimum resolution, bleed and size | 0176 L319-321, L439; DSP 33-35 | Waiting for Jason's numbers ("I'll set the limits") |
| Pre-flight items for Jason | `GITHUB_SYNC_TOKEN` expires Dec 16, 2026 (https://github.com/settings/tokens); the Arizona Corporation Commission and New Mexico Secretary of State records requests | PF 50-52, 130-133; SM 333-335 | Open |

## 8. Broken pieces Jason found himself

| # | What he found | His words | Source | Fixed live? |
|---|---|---|---|---|
| K1 | W1 paused whole when one task (`overture_bulk`) timed out once | — | C51 7-10; PLAN 9 | **No.** Confirmed in the live code (`ControlAlarms::stop()` pauses the workflow for a failed task). Goes into step 2's failure map; Q1-Q3. |
| K2 | "Paused now" unclear: 7 parts "paused by a person (Shut down from the Control Board by the Control Board (a trip))" | "this makes me feel like the system is not operating correctly. Still struggling to get a clear picture…" | 0175 L469-474; OPS 17-24 | Yes, #242 |
| K3 | A down backup (Common Crawl, only W3's backup) held all of W3 | "W3: what is it supposed to do?"; "reroute to a back up service or trip" | 0175 L514-522 | Yes, #247 (verified live at Sync 12:26 UTC) |
| K4 | Reactivate for a site that blocked W0 sat on Insights instead of the board | "Insights vs systems services register?" | OPS 42; 0176 L394-398 | Yes, #245 |
| K5 | W1's row read Holding while on a backup | (on his reroute rule) | CB20 88-90 | Yes, #231 |
| K6 | A slow trip stopped W1, and Resolve only asked "What fixed it?" | "There is no way to fix this"; "no dead ends, system fixes first" | CB 188-195 | Yes, 4e (#160) |
| K7 | W0 showed "Tripped" with its passing gate text as the reason | — (phone screenshot) | CB 170-177, 226-234 | Yes, 4c (#156) and #168 |
| K8 | Wikidata's 429 made Wikimedia "held" and tripped W1; a tripped row offered Start | — (screenshot) | CB 240-247 | Yes, #169 |
| K9 | The unused-schema list gave him nothing to act on | "not sure what these means to me as admin, i dont see how to act on it" | CB 162-164 | Yes, #154, then #161 |
| K10 | The × on Common Crawl's card looked like it dismissed the problem | — | DSP 113-114, 194-199 | Yes, 0176 R2 (#234) |
| K11 | #192 dropped the board's filtering counts | "These issues need to be caught!" | PB 98-103; SM 28 | A test exists (`board-rendered.cjs`); live state to prove in step 2 |
| K12 | Fixes sent him to another page (the Start card's tool fixes) | "Fix it can't take me to another page. I already explained this." | PF 277-280; PB 25-27 | Yes, start-inline-fix |
| K13 | Start card: Report didn't download, notice inaccurate, "Starting" never "Operational", top bar see-through on a phone | — (phone screenshot) | PF 269-274 | Yes, start-report |
| K14 | Start card 428px wide on a phone | — | PF 284; PB 87 | Yes, start-phone |
| K15 | W1 stopped on a single Overpass timeout | — | PF 251-257; PB 32-33 | Yes, start-gate-fair |
| K16 | The switch-on check held the master on 20 hub lines | — | PF 209-220 | Yes, hub-gate-preflight-2 |
| K17 | Pre-flight only ran while he stayed on the page | "Preflight only works if I stay on that page" | PF 148-150 | Yes, #107 |
| K18 | A bad `release.json` stranded #108/#109; a same-name rebuild stalled #106; failed releases needed his Withdraw | "this is not how this is supposed to work"; "having to require me to remove bad releases is not helpful" | CB 620-625, 653-665; PB 113-117 | Yes, #122 and 3f |
| K19 | The Releases page didn't update | "release page needs to update to current states" | CB 424-426 | Yes, #123 |
| K20 | "Added switched off" notes stale after Release B | — (phone) | CB 638-642 | Yes, 2c |
| K21 | Board folds open on load; "…" and More instead of a toggle | "these need to be collapsed on load"; "use a toggle instead of ... and more" | CB 40, 55-57 | Yes, #132 and 3b-2 |
| K22 | The scheduler showed "Every minute" for a line matching no preset; the Park Service card had two "Active" boxes (the first wrong while its key is missing), "Retire it…" wrapping under its spinner, "Linked, not called" three times | — (screenshots, 2026-11-20) | SM 155-159; OPS 70-71 | **No**, queued |

## 9. Count

| Area | Rows |
|---|---|
| 1 Purpose, views and layout | 26 (R1-R26) |
| 2 Parts, register and lifecycle stages | 13 (R27-R39) |
| 3 Sensors, checks and pre-flight | 25 (R40-R64) |
| 4 Alarms: levels, tries, trip, escalation | 21 (R65-R85) |
| 5 What a fault holds, backups and recovery | 14 (R86-R99) |
| 6 Words (and lights) | 14 (R100-R113) |
| 7 Fix it and routing | 11 (R114-R124) |
| 8 Who is told | 15 (R125-R139) |
| 9 Switches, start-up, shut-down, maintenance | 17 (R140-R156) |
| 10 Services: keys, caps, paces, spend | 10 (R157-R166) |
| 11 History, logbook, reports, incident reports | 9 (R167-R175) |
| 12 Phones and rendering | 4 (R176-R179) |
| 13 Build rules | 10 (R180-R189) |
| **Requirements** | **189**, merged from 594 reader rows (A 165, B 136, C 154, D 139) |
| **Questions for Jason** | **22** (Q1-Q22), from about 60 raised by the four readers |
| **Replaced lines and settled questions** | **48** rows in section 5 |
| **Held and not built** | 22 items |
| **Broken pieces he found** | 22 (2 not fixed live: K1, K22) |
