# The control board's build (step 4): the hand-off for the build session

2026-11-22. Written by the Operations session (session_01CXLi5jC5aZcEZtdMWtd4Gb) at the close of step 3, for a **fresh
build session that owns the build from start to finish, until it's live.** It is a plan, not a build:
- nothing is claimed, built or zipped;
- it goes to the session manager first.

## Summary

- **One release** (`handoffs/control-board-plan.md`:74–81).
  - It builds **one engine** that applies step 3's state model (`step3/02-state-model.md`, 196 transition rows), and
    removes the separate copies of the map in nine libraries.
  - It draws the board Jason approved: Arc gauges, the 16 development gauges, the runtime lifecycle, the check register,
    the Workflows page and the four-zone cards.
  - It fixes 23 defects found in step 2 and in Jason's live reads of 2026-11-22.
- **Built on the live mirror at Sync 2026-11-22 20:55 UTC** (`degraffics/traverse-3way-sync` `73d3782`). Fetch again
  before writing anything (CLAUDE.md, "Before writing any file for a zip").
- **The file list (§2):**
  - 44 site files: 37 live files changed from their live copies, and 7 new (including the 2 migrations), plus
    token additions only if the stage colours are missing;
  - 2 migrations, both add-only and guarded;
  - 11 repo files: 3 live and 8 new.
- **The scenario test** causes every cell of the failure map on staging:
  - step 2's 173 cells (by area: crawler 35, services 31, jobs and hosts 38, channels 69);
  - 02 §4.9's 135 designed scenarios.

  Each cell asserts its hold, words, channels and recovery. One cell, the outside heartbeat, waits on Jason's two GitHub
  secrets.
- **Sent with `"review": true`.** Jason sees it on staging with the scenario results, and approves UI, UX and
  Functional before Publish.

**Read first** (this hand-off cites them; it doesn't replace them):
- `handoffs/control-board-plan.md`
- `handoffs/control-board-plan/step3/close.md`
- `step3/live-reads/C2-C3-board-2026-11-22.md`
- `step3/03-cards.md` §27
- `step3/workflows-page.md`
- `step3/sections/gauge-lifecycle.md`
- `step3/sections/check-register.md`
- `step3/association-map.md`
- `step2/README.md`
- `handoffs/builder-playbook.md`
- CLAUDE.md

---

## 1. What's live now (mirror `73d3782`, Sync 2026-11-22 20:55 UTC)

**The board's releases, all live** (`deliveries/releases/`):

| Releases | What |
|---|---|
| #151, #154, #156, #157, #160, #166 | 0171 R4a–R5a: maintenance and upkeep, the historian, alarms' words, rows in use, slow never stops, the logbook |
| #168, #169, #173, #175, #177, #179, #180, #183, #185, #189, #192, #197, #200, #203 | Old trips settled; back-off waits; the reports; links and Resolve; sources outside our region; each process's page; counts that filter; every alarm leads to its one item; the search, sign-in and payments sensors; the parts in the admin's table; blocked sites; a service's card |
| #209, #210, #212, #216 | 0173: the Service register, its keys, defs once; the board on a phone |
| #222, #223, #229, #231, #238, #239, #242, #243 | 0175 R1–R2b: a trip pauses, never switches off; words follow the state; the board as the one place to act; one board in the menu (parts A–C); Paused now |
| #226, #234, #236, #245, #246 | 0176 R1–R4, the dispatcher: one alarm thread shape; service issues as threads; the alarm notice; Fix it routing; uploads' staff threads |
| #244, #247 | The Census geocoder's backup; a source down holds only what uses it (with crawler 2.16.1) |

Releases #248–#256 since then are design and search releases outside the board. #256 (map tokens) was on staging at
this writing. The only open claims are map files (`website/js/map-view.js`, `website/user/trips/trip.php`), not the
board's (`releases/checkouts.json`).

**The alarm engine's pieces as live** (each applies part of the map today):

| Piece | Where | What it does now |
|---|---|---|
| The alarm store | `app/lib/ControlAlarms.php`:30 `ensure` (table `control_alarms`), :82 `raise`, :150 `clear`, :244 `stop`, :299 `resolve`, :492 `clearOthers`, :538 `counts`, :734 `fixOf`, :897 `expireTries`, :922 `tick` | Raises, stops (holds), tries, clears; Fix it routing; the 5-minute watch |
| Crawler reports | `app/lib/crawler/CrawlService.php`:183 `report`, :252–262 raises (run failed or starved; a task failed) | Writes its own words: "the workflow's other tasks carry on" (:262) |
| Services | `app/lib/ServiceHealth.php`:183 `classify`, :276 `holdUntil`, :350 raise, :408 `holdForTrip`, :462 `holds`, :496 `allowed` | Classifies answers, places host holds, its own back-off clock |
| Pre-flight | `app/lib/Preflight.php`:221 `results`, :270 `run`, :351 raise, :895 `reachAll`, :1110–1113 Common Crawl's two parts, :1652 `recordSwitchOn` | Red lines raise and gate workflows |
| Operations | `app/lib/Operations.php`:471 `empties`, :614–673 raises (caps, returns empty, Start, W0, the pre-flight's stop), :779 `switchOnCheck` | Caps, W0, Start's watch |
| The board's readings | `app/lib/ControlBoard.php`:344 `pausedHtml`, :450 `latest`, :648 `settle` | Reads runs, jobs and endpoints for what's wrong now |
| Sensors and ledger | `app/lib/ControlSensor.php`:156–213, `app/lib/ControlLedger.php`:38 `begin`, :119–138 raises, :146 `finish` | Critical paths; endpoint 5xx, count and slow |
| Maintenance and history | `app/lib/ControlMaintenance.php`:136, :188; `app/lib/ControlHistorian.php`:105 | Their own raises |
| Switches and jobs | `app/lib/Automation.php`:50–61 `JOBS`, :152 `sysOff`, :293 `wfOff`, :450 `setTask`; `app/lib/Scheduler.php`:95 `run`; `app/lib/SystemStart.php`:45–52 `TASKS` | Pauses and switches; a job tied to a workflow pauses it (`ControlAlarms.php`:279–285) |
| The dispatcher | `app/lib/Notify.php`:162 `send`, :299 `route`; `app/lib/Thread.php`:82 `shape`, :101 `html`, :137 `push`, :144 `text`; `ControlAlarms.php`:619–646 | One thread shape, Pulse, email and push (0176 R1–R4) |
| Site health | `app/lib/SiteHealth.php`; `app/lib/Pulse.php`:430–432 and :951 (`sitehealth:open`) | Its own Pulse case, beside the alarms |

## 2. The release, as ONE release

### 2.1 Files

**Site files (in the zip: `app/` and `website/` at their real paths).** "Live" files start from the live copy at the
newest sync and change it. "New" files are new.

| # | File | Live / new | What changes | Source |
|---|---|---|---|---|
| 1 | `app/lib/ControlEngine.php` | **new** | The one engine: state(), the hold (radius), the two clocks, tries on the component's check, recovery by a real check, words from state, Fix it routing, one alarm per thing, the dispatcher's one path | 02 §1 P1–P12, §5; plan step 3 |
| 2 | `app/lib/ControlIncidents.php` | **new** | IR-#### records: members, radius, evidence, root cause (Unconfirmed until read), close; the outside heartbeat's records imported with their numbers | 02 §2.6, P12; §0a ruling 6 |
| 3 | `app/lib/ControlGauges.php` | **new** | The gauge lifecycle: setup criteria, stages, scores; the check register's results read for "checked"; the workflow targets as approved | gauge-lifecycle §1–7; check-register; workflows-page §5 |
| 4 | `app/ui/score-gauge.php` | **new** | The Arc score gauge (from `step3/mockups/src/scorering.py`); Ring is not output | 03 §27 (Arc, Jason's choice) |
| 5 | `website/js/tv-copy.js` | **new** | Copy by section: each section's and card's text to the clipboard (headings and rows, no hidden values) | live-reads C6 ("copy by section") |
| 6 | `app/lib/ControlAlarms.php` | live | Becomes the engine's store: `raise` takes evidence and the engine decides; `stop`, `expireTries` and `tick`'s tries move to the engine; `resolve` checks first; `counts` counts one way on every surface | P1, P3, P4; live-reads C3 (14 against 17) |
| 7 | `app/lib/ControlBoard.php` | live | `latest`, `settle` and `pausedHtml` read the engine's state; Paused now's words from state | P9; R21–R22 |
| 8 | `app/lib/ControlRegister.php` | live | Duties and CRITICAL from the manifest; `/api/crawl/loads.php` and the other crawler endpoints as Core Services components | 02 §8; workflows-page §3 |
| 9 | `app/lib/ControlSensor.php` | live | Reports evidence to the engine | P1 |
| 10 | `app/lib/ControlLedger.php` | live | The write path recording every worker call (today 0) | live-reads C3, finding 2 |
| 11 | `app/lib/ControlReport.php` | live | The report folded: summary and spend first, uptime only below 100%, alarms grouped with counts, CSV unchanged | live-reads C3 ("ridiculously long") |
| 12 | `app/lib/ControlMaintenance.php` | live | Raises through the engine; maintenance can take out a module | P1; g3 F8 |
| 13 | `app/lib/ControlHistorian.php` | live | Raises through the engine | P1 |
| 14 | `app/lib/ServiceHealth.php` | live | `classify` reads a 432 as a quota; holds and the back-off clock through the engine | 03 §17 (Tavily); P2, P3 |
| 15 | `app/lib/Preflight.php` | live | A red line is a fault on its component (model 1.1–1.2), holding only what can't reroute | R71, R99 |
| 16 | `app/lib/Operations.php` | live | Raises through the engine; `switchOnCheck`'s red lines go on what they hold | P1; workflows-page §7 |
| 17 | `app/lib/Automation.php` | live | A job's fault holds only its task (`identity_recheck_tick` → W2's `recheck`); the hold is read, never written into a switch | P5, P6; live-reads C2 #5 |
| 18 | `app/lib/SystemStart.php` | live | The watch never writes a person's switch, and never credits the Founder | g3 F7; 02 §0a ruling 4 |
| 19 | `app/lib/Scheduler.php` | live | A skipped run is never a good run; job runs report to the engine | g3 F1, F4 |
| 20 | `app/lib/Thread.php` | live | Words from the engine's state on every surface | P9; R101–R104 |
| 21 | `app/lib/Notify.php` | live | The one path; a trip with nobody to receive it is recorded, and falls back to another channel | root cause 6; g4 F3, F4 |
| 22 | `app/lib/SiteHealth.php` | live | Its notices fold into the dispatcher: a build regression is a Pulse item with no push | gauge-lifecycle §8, 1 |
| 23 | `app/lib/Pulse.php` | live | One item per alarm; Site health's case through the one path | R129; Site health one path |
| 24 | `app/lib/service-defs.php` | live | The manifest: role, criticality, the eight stages, level, repair, probes, answers, `portal_links`, `setup_capture`, correlation | 02 §8; 04 §5 |
| 25 | `app/lib/OutsideServices.php` | live | Its lists generated from the defs (0173 §4) | 02 §8 |
| 26 | `app/lib/Captcha.php` | live | A refused secret lets people through, as an outage does, with a critical trip on sign-in's duty | 03 §24 3 (Jason); `Captcha.php`:55–58 |
| 27 | `app/lib/crawler/CrawlService.php` | live | `report` sends evidence; no words of its own (:252–262) | P1, P9 |
| 28 | `app/lib/crawler/NpiBulk.php` | live | `OFF` (:30) becomes the task's switch with its why ("Off on purpose: CMS took its file page down") | 03 §22 NPI 1 |
| 29 | `app/lib/crawler/IrsBulk.php` | live | `finish` (:60): the shape check before any write, and no delete below 90% of last month's rows | for-jason-review 5; 02 row 38b |
| 30 | `app/lib/Thresholds.php` | live | The des thresholds as editable settings: the 24-hour certificate notice, SES's 5% and 0.1%, the IRS 90% | close.md B4, B7 |
| 31 | `app/lib/staging-tables.php` | live | `control_incidents` named in OWN (zip check R14) | CLAUDE.md, staging |
| 32 | `app/admin/control-console.php` | live | The views: Is it delivering? (Arc runtime gauges, the Development rows), Modules (four-zone cards), Workflows, Diagnostics, Pages, the folded Reports, Copy on every section | 03 §1–§27; workflows-page |
| 33 | `app/admin/operations-console.php` | live | The Workflows page (`tv_admin_mod_workflows_crawler`, `tv_admin_mod_board`): 8 kept, 2 moved, 3 dropped | workflows-page §7 |
| 34 | `app/admin/preflight-console.php` | live | Lines read through the engine | R58–R60 |
| 35 | `app/admin/automation-console.php` | live | W13 as a list; W14 "Not built" (not "Running") | live-reads C6 |
| 36 | `app/admin/manifests.php` | live | Only if a section moves (the board's tabs); otherwise untouched | decisions/0109 |
| 37 | `app/ui/faq.md` | live | Entries for what changed (§7) | decisions/0057 |
| 38 | `website/js/admin-control.js` | live | The views' interactions, panels closing three ways, Copy | CLAUDE.md, no browser dialogs |
| 39 | `website/admin/api/data.php` | live | The Data browser lists every realm's tables (`dbTables`, :39–55), each labelled, keeping hidden tables and read-only rules | live-reads C6/C15 |
| 40 | `website/api/crawl/_auth.php` | live | The ledger's `begin` for every worker call (only if the write-path defect is here: read first) | live-reads C3, finding 2 |
| 41 | `website/api/crawl/loads.php` | live | Reports to its component's check (answers within the worker's 120 s) | workflows-page §3 |
| 42 | `app/admin/index-console.php` | live | Only where an alarm's link moves | g4 |
| 43 | `app/migrations/2026-12-01_control_engine.sql` | **new** | Migration A, below | — |
| 44 | `app/migrations/2026-12-01_control_incidents.sql` | **new** | Migration B, below | — |
| 45 | `website/css/` or `app/ui/` token additions for the Arc colours | **new** only if the tokens lack the stage colours; otherwise none | brand.md §4 |

**Repo files (this repo, with the release, not in the zip):**

| File | Live / new | What |
|---|---|---|
| `.github/workflows/heartbeat.yml` | **new** | The outside heartbeat every 15–30 min, email only through SES (`ALARM_TO`, the send-only key) |
| `tools/control-board-tests/scenario/` (runner, suites, fixtures) | **new** | The scenario test (§5) |
| `tools/control-board-tests/consistency.php` | **new** | The daily consistency check (plan step 5) |
| `tools/page-audit/tools-check.mjs`, `load-check.mjs`, `photos-shown.mjs`, `brand-check.mjs`, `audit-sample.py` | **new** (5) | The register's missing checks TOOLS, LOAD, PHOTOS-SHOWN, BRAND and AUDIT; PHOTOS' results in the one format |
| `.github/workflows/site-checks.yml` | live | Runs the new checks on the approved schedule |
| `workers/video/railway.json` | live | `restartPolicyType`: `NEVER` (:12) → on failure, up to 10 |
| `handoffs/session-manager.md` | live | A Progress line on the queue item |

**Migrations** (CLAUDE.md: run by `DbUpdates::autoRun`, safe to run twice, guarded through `information_schema`, no
`DELIMITER`, no `IF NOT EXISTS` inside `ALTER`, add-only. Find each table's realm first, `Database::t()`.)
- **A. `2026-12-01_control_engine.sql`:** `control_alarms` gains `component VARCHAR(80)`, `try_n TINYINT UNSIGNED`,
  `next_try_at DATETIME NULL` and `incident_id INT UNSIGNED NULL`. Each column is guarded by an `information_schema.COLUMNS`
  check (the pattern in CLAUDE.md).
  - Why: tries are counted on the component's check, on the diagnostic clock (P3); a trip belongs to its incident (P12).
  - Today `ControlAlarms::ensure` creates the table in code (:30–38). The build keeps `ensure` in step with the columns.
  - `control_alarms` is already **OWN** (`staging-tables.php`:78).
  - No `UPDATE`, so no `data_reason`.
- **B. `2026-12-01_control_incidents.sql`:** `CREATE TABLE IF NOT EXISTS control_incidents` (id, number, opened_at,
  closed_at, host, signature, radius JSON, evidence, root_cause, root_cause_state, source: board or heartbeat).
  - **OWN**: each site raises its own, like `control_alarms`, and is named in `staging-tables.php` (R14).
  - Kept 365 days where practical (02 §0a, Q19).
- **The settle step** (the 14 live alarms, live-reads C2) is done by the engine's first `tick` in code, never by a
  migration `UPDATE`. If the builder finds a migration must change rows, it carries `"data_reason"` (CLAUDE.md, decisions/0162).

### 2.2 Build order inside the release

1. Fetch the newest sync, and claim (§2.3). **First check Design and trips' claims** (it's on 0174 R5,
   openrouteservice, next, likely touching `faq.md` and possibly `manifests.php` and `tools.php`). On a clash, follow
   CLAUDE.md's `faq.md` rule: wait for its release to go live, then re-apply only this build's entries on the live copy.
   The session manager sequences the two.
2. **The ledger's 0 calls comes first** (session manager, 2026-11-22): read `control_ledger` on staging, and find why
   the workers' calls aren't recorded (`ControlLedger::begin` `:38` via `api/crawl/_auth.php`, `finish` `:146`), before
   the engine. Every "records in" gauge depends on it.
3. Read C15 on staging (§6).
4. **The manifest** (`service-defs.php`, file 24) and its generated lists (file 25), so the engine reads one definition.
5. **The engine** (files 1–2) and Migrations A and B, with its unit suite.
6. **The callers onto the engine**, one at a time with their tests rerun: CrawlService → ServiceHealth → Preflight →
   Operations → ControlSensor and ControlLedger → Maintenance and Historian → Scheduler and Automation and SystemStart.
7. **The dispatcher path** (Thread, Notify, Pulse, SiteHealth).
8. **The fixes decided:** IRS, NPI, Captcha, the ledger, the Data browser, Tavily's 432, the video worker's restart rule.
9. **The views:** the gauges, cards, Workflows page, Reports, Copy, and Arc (files 3–5, 32–35, 38).
10. **The scenario test and the rendered checks**, all green.
11. FAQ, the release summary, zip check, send (§7).

### 2.3 Claims at the start (`tools/page-audit/checkout.py claim`, decisions/0161)

```
checkout.py claim --change --why "control board build (step 4)" \
  app/lib/ControlAlarms.php app/lib/ControlBoard.php app/lib/ControlRegister.php app/lib/ControlSensor.php \
  app/lib/ControlLedger.php app/lib/ControlReport.php app/lib/ControlMaintenance.php app/lib/ControlHistorian.php \
  app/lib/ServiceHealth.php app/lib/Preflight.php app/lib/Operations.php app/lib/Automation.php app/lib/SystemStart.php \
  app/lib/Scheduler.php app/lib/Thread.php app/lib/Notify.php app/lib/SiteHealth.php app/lib/service-defs.php \
  app/lib/OutsideServices.php app/lib/Captcha.php app/lib/crawler/CrawlService.php app/lib/crawler/NpiBulk.php \
  app/lib/crawler/IrsBulk.php app/lib/Thresholds.php app/admin/control-console.php app/admin/operations-console.php \
  app/admin/preflight-console.php app/admin/automation-console.php app/admin/index-console.php website/js/admin-control.js \
  website/admin/api/data.php website/api/crawl/_auth.php website/api/crawl/loads.php
checkout.py claim --change --why "control board build (step 4): one item per alarm" app/lib/Pulse.php::<the functions changed>
checkout.py claim --add --why "control board build (step 4)" app/lib/staging-tables.php app/ui/faq.md app/admin/manifests.php
checkout.py claim --add --why "control board build (step 4)" app/lib/ControlEngine.php app/lib/ControlIncidents.php \
  app/lib/ControlGauges.php app/ui/score-gauge.php website/js/tv-copy.js
```

Notes on the claims:
- Claim `Pulse.php` by function (`::fn`), since other sessions edit it.
- `faq.md`, `staging-tables.php` and `manifests.php` are add-only claims.
- **Most likely to clash with other sessions:** `faq.md`, `manifests.php`, `tools.php`, `Pulse.php`, `Notify.php`,
  `Thread.php` (the dispatcher's tests) and `Thresholds.php` (CLAUDE.md, "Just before zipping").
- The plan (step 4) says no other session touches the board's files until it's live. The session manager holds the
  others to it.

## 3. The one engine, and the callers it replaces

Today the map is applied in **nine** places, each with its own raise, hold, clock or words (step 2's root causes; §1's
table):
1. `ControlAlarms` (store, stop, tries, tick, resolve, fixOf);
2. `CrawlService::report`;
3. `ServiceHealth` (classify, holdUntil, holdForTrip, holds, allowed);
4. `Preflight` (run, raise, reachAll, recordSwitchOn);
5. `Operations` (raises :614–673, switchOnCheck);
6. `ControlSensor` and `ControlLedger`;
7. `ControlMaintenance` and `ControlHistorian`;
8. `Scheduler`, `Automation::JOBS` and `SystemStart`;
9. the dispatcher (`Thread`, `Notify`, `Pulse`) with `SiteHealth` beside it.

**What moves into the engine** (02 §5; P1–P12):

| Moves in | From | Rule |
|---|---|---|
| **Holds:** the Incident Radius from the manifest (Held / On a backup / Unaffected) | ControlAlarms::stop :244, ServiceHealth::holds :462, Preflight gates, Automation::JOBS :279–285 | P5, P6, P7; R86–R90 |
| **The two clocks:** the run schedule is never a try; tries are the engine's checks of the failed component, at about 5, 15 and 35 min inside the hour | expireTries :897, ServiceHealth::holdUntil :276, the back-off's 6 failures or 6 h | P3; 02 §0a ruling 2; R67–R68 |
| **Recovery by a real check:** no close without calling what failed; Check and start again checks first | resolve :299, holdForTrip :408 | P4; g2 F1–F3 |
| **Words from state** on every surface | CrawlService :262, Thread, Paused now, card titles | P9; R101–R105 |
| **Fix it routing**, one spot per cause; their trouble gets no Fix it | fixOf :734, ServiceHealth::fixOf | R116–R118 (R118 wins) |
| **One alarm per thing**, one count everywhere | raise :82, counts :538 | R66, R129; live-reads C3 |
| **The dispatcher's one path:** trips to a person, and the rest board-only | ControlAlarms :619–646, Notify::send | R125–R134 |
| **Site health's notices folded in** | SiteHealth, Pulse :430, :951 | gauge-lifecycle §8, 1 |
| **Incidents:** an IR at each trip; correlation in 15 minutes | none today | P12; 02 §0a O1 |

## 4. Defects, each mapped to its fix in the release

| # | Defect (source) | Fix in the release (file #) |
|---|---|---|
| D1 | **Holds too wide:** W1 held whole by two task trips (step 2 root cause 2; live-reads C2) | `/api/crawl/loads.php` a component with its own check, holding only overture_bulk, foursquare_bulk and geofabrik_bulk (8, 41, engine 1) |
| D2 | **Tries on the wrong clock** (root cause 1; case #51; g2 F4; g3 F3) | The diagnostic clock in the engine (1, 6, 14, 19) |
| D3 | **Recovery without a check:** Check and start again closes in 5 ms (root cause 3; g2 F1) | `resolve` checks first, calling what failed (1, 6) |
| D4 | **Words not following the state:** Common Crawl's "Its server failed"; "other tasks carry on"; W13 and W14 "Running" (live-reads C2 #1, C6; root cause 5) | Words from state (1, 20, 27, 35) |
| D5 | **The acknowledged-alarm count:** 14 on the board against 17 in the report (live-reads C3, 1) | One count on every surface (6, 11) |
| D6 | **The ledger records 0 calls** for 7 days (live-reads C3, 2) | The write path (10, 40); read `control_ledger` on staging first |
| D7 | **The Data browser lists the main realm only** (live-reads, `data.php`:39–55) | Every realm, labelled (39) |
| D8 | **`identity_recheck_tick` a loose job warning:** it is W2's `recheck` (live-reads C2 #5; `Automation.php`:58) | Its hang shows on W2's page, holding only `recheck` (17) |
| D9 | **8 failed crawl jobs not on the board** (live-reads, Insights) | A count on their task's workflow, with Try again there (32, 33) |
| D10 | **Tavily's 432 classified as nothing** (`ServiceHealth.php`:199; 03 §17) | 432 is a quota (14) |
| D11 | **The IRS partial delete** (02 row 38b; for-jason-review 5) | The shape check and the 90% floor (29) |
| D12 | **NPI's `OFF` constant** (`NpiBulk.php`:30; 03 §22) | A real switch with its why (28) |
| D13 | **Turnstile refuses on a refused secret** (`Captcha.php`:55–58; 03 §24 3) | Lets people through, plus a critical trip on sign-in (26) |
| D14 | **The video worker's restart rule** (`workers/video/railway.json`:12 `NEVER`; 03 §23) | On failure, up to 10 (repo) |
| D15 | **Copy by section** (Jason, live-reads C6) | `tv-copy.js` on every section and card (5, 32, 38) |
| D16 | **The Reports page "ridiculously long"** (Jason, live-reads C3) | The folded report (11) |
| D17 | **The board's design** (03 §27; gauge-lifecycle; check-register; workflows-page) | Arc gauges, the 16 development gauges, the runtime lifecycle, the register's checks, the Workflows page (3, 4, 32–35; repo checks) |
| D18 | **Hosts and endpoints aren't parts with a role**; the worker down barely shows; the video worker has no heartbeat (root cause 4; g1 F2, F5; g3 F5) | The manifest's role and radius (24, 8) |
| D19 | **The channels lose the thread:** an acknowledged trip turns the light green; a person's close never updates Pulse or email; a trip to nobody is sent nowhere (root cause 6; g4 F1, F3, F4) | The dispatcher's one path (20–23) |
| D20 | **Critical duties can fail unseen** (root cause 7; g3 F2) | Duties from the manifest, critical trips at once (8, 24) |
| D21 | **Start's watch writes a person's switch** (g3 F7) | Never (18) |
| D22 | **No Incident Report** (g3 F11) | IR records (2) |
| D23 | **The outside heartbeat** (02 §0a ruling 6) | `heartbeat.yml` (repo) |
| — | **Excluded unless Jason decides:** the trvgo.us redirect while the edge is down (for-jason-review 7; close.md B6) | Not in this release |

Also in scope from close.md §3:
- the dispatcher registered critical for procedures (B3);
- the ledger export (B1);
- the des thresholds as settings (B4, B7);
- the Vault's portal links and source tags (04).

## 5. The scenario test

**How it's cut:**
- Every cell of step 2's failure map: **173 cells**, by area (`step2/README.md`, "The totals"):

  | Area | Cells | Matches | Differs | Missing | Other |
  |---|---|---|---|---|---|
  | Crawler side | 35 | 7 | 23 | 5 | — |
  | Sources and services | 31 | 7 | 20 | 4 | — |
  | Jobs, checks and hosts | 38 | 12 | 18 | 6 | 2 partial |
  | Channels | 69 | 35 | 25 | 9 | — |

- **Grouped by root cause** (step 2's seven):
  - **Where step 2's findings name their cells:**
    - Root cause 1, tries: g2 rows 16–19 (F4).
    - Root cause 3, recovery: g2 rows 1, 2, 14, 16, 20–23 (F1–F3).
    - Quotas and holds: g2 rows 3, 6, 7, 25, 29, 31 (F5, F9).
    - Backups: rows 27, 30 (F8).
    - g3's J- and C-rows by F1–F11.
  - **g1's and g4's findings don't list their cells.** The builder tags each of their cells by which column differs (holds,
    tries, words, Fix it, recovery). This is the test's first step, written into the test's own table.
- **Each cell:** stand up its starting state on staging (safe mode; `StagingGate::hold()` stand-ins for outside hosts),
  cause its failure, then assert 02 §4.9's five things:
  1. state;
  2. hold (exactly Held / On a backup / Unaffected, and no switch value changed);
  3. guard (the check called what the row names);
  4. words on the board, the card, Pulse, email and push;
  5. messages (counts, a close updating all three).

  Then cause recovery, and assert Recovered.
- **02 §4.9's suites** cover the 135 designed scenarios:

  | Suite | Scenarios | Count |
  |---|---|---|
  | lift | S-A1–15 | 15 |
  | faults | S-B16–82 | 67 |
  | clock | S-C83–90 | 8 |
  | actions | S-D91–120 | 30 |
  | escalation | S-E121–123 | 3 |
  | correlation | S-F124–127 | 4 |
  | portal | S-G128–135 | 8 |

  A words suite runs across all of them. Step 2's cells map onto these scenarios.
- **The case drawn end to end** (workflows-page §8, Common Crawl's index down holding only W3's per-site copy) is one
  named scenario.
- **The test schema:** every board table on staging's test database (step 1 §7; 02 §4.9's last line).

**The heartbeat cell** is "Bluehost is down: the outside heartbeat emails".
- It runs as **"not set up (ALARM_TO)"** until Jason sets `ALARM_TO` and the SES send-only key (close.md A1), then is
  rerun.
- Every other cell must pass.

**The rendered checks:**
- every board view and page at 1280 and 390;
- no sideways scroll;
- every panel closing by ×, Esc and a click outside;
- the validator;
- Arc gauges' bands within 1% of their scores, no needle (`step3/mockups/src/ring-check.cjs` as the model);
- Copy on every section.

**After it's live:** the daily consistency check (plan step 5; R189). Each open alarm's hold, words and Fix it are checked
against the map. A mismatch is a case for the building session and the session manager, never for Jason.

## 6. Live reads the builder still needs (`step3/asks.md`)

- **C1, IR-0001's timings.** The ledger is empty (D6), so read Bluehost's access log through the cPanel API
  (`app/lib/CPanel.php`), read-only, in a session Jason approves.
- **C15, `irs_loads`.** Read on staging, read-only, before settling whether the release carries the nonprofits' reload.
  It's class **follows**, copied nightly (live-reads C15).
- **Settled already:**
  - C2: 14 alarms, mapped;
  - C3: W1 and W3 switches;
  - C6: the workflow → task table;
  - C18: W0 is running.
- **Non-blocking** (read live by the build, or for a card's words):
  - C4: the lift;
  - C5: run #1290;
  - C7: Common Crawl's index (W3's switches wait on it);
  - C8: W2's count;
  - C9: the pre-flight's names;
  - C10: thresholds;
  - C11: modes;
  - C12: domains;
  - C13: Pulse cases;
  - C14: video jobs;
  - C16: load tables;
  - C17: `ALARM_TO`;
  - C19: Cloudflare's DNS;
  - C20: the CMS file.

## 7. Delivery (CLAUDE.md; builder-playbook §3)

1. **The zip** holds only files that differ from the live mirror byte for byte, plus `release.json`:
   - a title, why, proposals and switch (`""` if none);
   - **`"review": true`**;
   - `"data_reason"` only if a migration has an `UPDATE` (the plan has none).
2. **Just before zipping:** fetch the mirror again. Rebuild `faq.md` on the newest live copy with only this build's
   entries, then run `tools/page-audit/zipcheck.py <zip> --mirror <checkout>`.
3. **The FAQ entries** (visitor's view):
   - the board's gauges;
   - what "Paused by the system" means;
   - Copy by section;
   - the Workflows page;
   - what Recovered means;
   - the outside heartbeat's email when the site is down.

   Check `grep -rn "faq.php#"` before rewording any.
4. `tools/page-audit/send.py <zip> --mirror <checkout> --note "… built on Sync: <time>"`, then
   `tools/page-audit/deliveries.py record`.
5. **Repo files that act on their own go into main only with the release** (session manager, 2026-11-22). Merging
   `.github/workflows/heartbeat.yml` starts a schedule that sends email, and a change to the video worker's
   `railway.json` can redeploy on Railway. Both are live actions: they reach `main` only after Jason's Approve of the
   release, in the same merge as the zip, never before. Until then they stay on the build branch.
6. **Jason's approval in his own chat** (the decision pop-up, after the session manager's review) before the inbox PR
   merges. On staging he approves UI, UX and Functional before Publish.
7. Watch its record (`deliveries/releases/`). After "Release #N: Live", the session manager runs the sync. The builder
   checks the mirror matches the zip byte for byte. A release withdrawn on staging is fixed at its cause and sent as `-2`.

## 8. Rulings to honour, what's open, and pitfalls

**Jason's rulings** (close.md §1, 33 rows):

*2026-11-21:*
- 02 §0a rulings 1–7 and answers Q19, O1, O3, O5;
- 03 §2's stage badge;
- 03 §10's 13 words;
- 03 §12.6a's three GitHub decisions;
- 03 §13's setup capture;
- the video worker's restart rule;
- 04's locked link-label rules and its seven answers;
- the IRS fix;
- "Is it delivering?".

*2026-11-22:*
- every snapshot is a gauge card;
- one gauge, one measure;
- the 16 gauges;
- the lifecycle, read as the stage of the work;
- the criteria from the page audit;
- the runtime lifecycle;
- three drill paths;
- Site health on one path;
- "reviewed" per 0145;
- the register's targets and schedule;
- Arc;
- the Workflows page;
- its targets "as proposed";
- W3's switches after the re-read.

*The session manager:*
- R118 wins;
- loads.php is a component;
- W13 is a list;
- "alarm wired" means through the one engine.

**Open, and who decides:**
- **A1, Jason** (close.md): the heartbeat's two secrets.
- **The B items** (close.md §2) wait without blocking:
  - the licences (B5);
  - the trvgo.us redirect (B6);
  - the two sages (B9);
  - 1Password (B8);
  - the nine placements and the gauges' scope sign-off (B10–B19, Jason, after the build);
  - the runtime targets "set by Jason" (B20);
  - his Vault's pages (B21).
- **W3's two switches:** Jason, after C1 and C7.

**Pitfalls:**
- **Specs-only habits don't carry over:** the build changes live files, so every rule in CLAUDE.md's release section
  applies: claims, zipcheck, R13 and R14, migrations, the FAQ, PHP 8.0.
- **Every behaviour has a source, or it's a question** (playbook §3). The release summary lists each with its decision.
- **Replacing a screen keeps everything it did:** list the kept, moved and dropped controls (playbook §3; workflows-page
  §7 is the model).
- **No live action without Jason's explicit call:** no switch, no lift, no patch (plan, "While the plan runs").
- **Staging:** every outside call asks `StagingGate::hold()` first. Nothing is sent from staging (R134).
- **0145:** a page is "reviewed" only with his sign-off on record.
- **No force-push;** pull with rebase, and cherry-pick if a branch moved.
- **One release.** A follow-up only for something found live.
- **Keep the review pack's parts under 300 KB** if the mockups are regenerated (`step3/mockups/src/board.py`).

## 9. The session rules

- This hand-off goes to the **session manager first** for review against the plan, the decisions and close.md.
- A **fresh session** takes it and owns the build start to finish (plan, "Who"; CLAUDE.md, "The session manager").
  - It is one session, with no hand-offs mid-build.
  - It is archived once the release is live and the session manager has checked it against the decision and brief.
  - If it grows too large, it hands off at a hold with a complete hand-off (CLAUDE.md, 2026-11-20).
- It asks Jason only in its own chat with the decision pop-up, after the session manager's "clear to ask"
  (CLAUDE.md, "Each session asks Jason itself").
