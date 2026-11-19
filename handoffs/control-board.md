# The System Control Board (the command center): hand-off for its build session (2026-11-18)

## Start here: the build is finished (2026-11-19)

All of decisions/0171 is built and live: releases #120–#173, then the links and Resolve fixes (#175), the off-region
sources (#177) and §12's process pages and four tables (control-board-12, the last). They're listed by section in the
decision's 2026-11-19 note. Every test is in `tools/control-board-tests/` (README there, how to run them on MariaDB).

**Kept as they are, by decision:** the Pre-flight page (Jason: "need a way to use preflight before start up"; the
board runs the same pre-flight) and §2's layout of stage tiles and separate buttons (the session manager's call when
Jason was unsure, 2026-11-19).

**Open on the board that needs Jason** (probe 11:54 UTC, `site-audit/results/alarms.json`):
- W1's trip from 09:00 (raised by the back-off bug #169 fixed): his **Check and start again** on W1's row.
- Common Crawl #25 (504s, a trip at its case limit, by the 3c rule) and W3's gate after it: his Resolve when Common
  Crawl answers (Check it now shows when it does).
- The vector index: Supabase isn't ready (its trip, timed 11:21).
- Every Overpass server held: waits on OpenStreetMap's answer to our statement.

**Rules learned in this build** (also in the decision's notes and `builder-playbook.md`):
- A trip is set by 0171 §6's 3c rule: held or out trips at once; a back-off trips at its case limit; slow and a 429 never
  trip. Don't propose changing it (Jason, 2026-11-19).
- Only an admin's Resolve clears a trip: no automatic lift, no bare Start on a tripped row, and a Check never reads as
  cleared.
- Reports are built on demand from what's kept; nothing is stored, so no staging class or retention.
- The probe (`search-probe.yml`, `towns.mjs`) writes `site-audit/results/alarms.json`: the open alarms, the report's
  summary and when the site last looked in git for releases.

## Earlier: release 3 (written at the end of release 2, 2026-11-18)

**Release 2 is complete and live.** It went out as these releases:

| # | Zip | What |
|---|---|---|
| #124 | `control-board-2a-4` | the ledger: `control_ledger`, `control_records` (`ControlLedger.php`, measured once in the endpoints' `_auth.php`) |
| #127 | `control-board-2b` | the board at `/admin/config/control`, first in System Configuration (its side-panel icon opens it): stages, controls, systems, processes, Start it (the interlock), Shut it down, Shut down in order, "stopped, safe" |
| #128 | `control-board-layout` | laid out after Jason's example board (`design/control-board-mockup.html`): status strip, Holding now, Query timings, rows in columns, the Needs table |
| #129 | `control-board-2c` | the service cards' fixes; crawler 2.12.1 (a held service ends a run ok, not Crashed) |
| #131 | `control-board-check-parts-2` | a check shown part by part, the red one first ("Holds it"); W0 Indexer 1.1.1 (a full test batch). Rebuilt on 17:04 after `notify-1`, which also carried `faq.md` |
| #132 | `control-board-closed` | everything starts closed (Jason: "these need to be collapsed on load"; "Close all on load"); Holding now and Query timings as folds with a one-line summary |
| (merged, #931) | `control-board-rows` | rows in aligned columns like Jason's second example (name and phases, stage pill, where it stands, last 24 hours, speed per record, OK rate, Check/Start/Shut down on the row); opened, short labelled lines with needs as chips. **Check it's live (17:14 sync or later) before building on it.** |

Alongside: `releases-self-withdraw` (#122), `releases-live-2` (#123), `releases-marks` (#125) and `releases-table-2` (#126) on the Releases page.

**Release 3 in this session** (Jason, 2026-11-18: "go with 3, but keep it here"; then one session through the whole board).
- PR #940 `board-w2-verify` (built on 19:23): W2's verify.php 500 no longer fails every run (each listing on its own, set
  aside with its error); the real error on W2's row; the held count's test time.
- `control-board-3a` (built on 19:36, after #940): `control_alarms` (OWN), `ControlAlarms` (raise, clear, ack, unack,
  list, counts, seed, tick, pulseBody, procOfEndpoint), the watch `control_tick` (Scheduler JOBS and AUTO), alarms from
  the ledger, crawler run reports, ServiceHealth and pre-flight lines; the queue replacing Holding now; the result line
  (procedure, time, names to alarms, Open the report, Run it again); rows that lead with why from their latest runs; the
  top-bar light; Notify `alarm.trip` / `alarm.warning` with Acknowledge in the message; Pulse items per alarm, Done =
  acknowledge. Comms (#135) owns Notify and Pulse's levels; its session agreed the kinds and the Pulse item.
  Live as #137 (Sync 19:49/19:51).
- `control-board-3b-2` (built on 20:07, after #137; replaces `control-board-3b`, never merged): a long line once, on one
  line with a ▸ toggle that opens it in place, fading at the edge, never "…" or More (`tv_cb_more`; Jason at review: "use
  a toggle instead of ... and more"), the check's other parts on a toggle, never repeated; old lines without parts say to
  press Check. Caps as 0171 §6 sets them (Jason: "how did we already determine their management?"): a warning at
  50/75/95%, a trip only when out of budget; the old cases' steps as buttons in place (fix kinds `ops` and `opsask`,
  api `opsdo` → Operations::pulseDo), and each alarm's Pulse item with the same buttons (ControlAlarms::pulseSteps/pulseDo,
  Pulse::playbook and manage). A fix list too long for its column drops whole buttons. Overpass is one trip `svc:osm:held` (ServiceHealth::alarm, raised by
  the watch from open issues, cleared by resolve()) with The statement (`?show=osm-statement`, research/osm-overpass §2),
  OpenStreetMap's card and Mark resolved (`svcresolve`, api.php); any `svc:issue:` alarm gets Mark resolved. Pulse:
  ops:cap and ops:empty are alarms (`raise(..., $event=false)` for readings), the pre-flight case only for a stopped run,
  a svc: case skipped when its alarm is open. The seed raises an endpoint's 5xx only if its latest call failed.
- `control-board-3b-3` (after 3b-2 is live): Overpass gets no contact at all while held (Jason, 2026-11-18, via the
  session manager: "no contact at all while they're held … never by itself"): `ServiceHealth::noProbe` (an `osm` refusal)
  stops its probe from the crawler and the site and Check it now; the alarm, case, FAQ and the statement to OSM say so;
  0171 §8 has the note. Pulse's `$rank` gains urgent and special (0172's levels, Communications' ask).
  Live as #139 (3b-2) and #140 (3b-3), verified in Sync 20:17 and 20:29.
- `control-board-3c` (built on 20:38, checked against 20:47): Jason's Trip rule (2026-11-18, pop-up, reviewed by the
  session manager; 0171 §5 and §7 notes). `ControlAlarms::raise` asks for a trip → a warning while the system tries
  (`control_fix_tries` 3 / `control_fix_minutes` 60, Thresholds; slow uses `control_slow_trips`), `AT_ONCE` (held, limit)
  trips at once; `tripped()` emails, `Pulse::escalateTo` (Director), `stop()` as narrow as its cause (wf/t via
  ControlBoard::stopOne, out of budget → service paused, back-off at CASE_AFTER → `ServiceHealth::holdForTrip`, the
  master switch only for `run:failed`/`silent:run`); `clear()` on a trip notes `good_at` and waits; `closeKey()` for a
  person's Mark resolved/Reactivate; `resolve()` (note, startOne after its check, red → `resolve_note`) and
  `unresolve()` (a day), on the board (api `resolve`/`unresolve`) and in Pulse (`alarm_resolve` form, `alarm_unresolve`).
  New columns by migration `2026-11-18_control_alarms_trips.sql`. Per-task failures `task:failed:<t>` (CrawlService);
  slow calls at the ledger (`ep:*:slow`) and slow jobs (`jobslow:*`) in the watch; job crashes counted per failed run;
  `Operations::alarms()` (caps, empty, Starting the system per ask `ops:start:<md5>`, W0 needs `ops:w0:*`, a stopped
  pre-flight run `ops:pf:stopped`), run by the watch; `ControlAlarms::opsDo` the one door for their buttons; a red
  required pre-flight line is a trip only while what it holds is on; `/api/health.php` `alarms` block. Tests r3c.php 28;
  r3a updated to the rule; the suites' `require_once Database.php` and r2a's `n_` columns fixed.
  Live as #143, verified in Sync 21:23.
- `control-board-3d` (built on 21:23): Jason's design directive, the board's part (via the session manager, 2026-11-18;
  the command header and the palette across the admin went to a design build). Alarm grouping on the board and in Pulse
  (`ControlAlarms::systemOf/familyOf/groupKey/groups/groupMembers/pulseGroups/ackGroup`; `Pulse::items` groups after its
  marks, `Pulse::mark` on `alarms:` marks each child, `playbook` 'alarms'; the board's `$one` row closure and the group
  row with Acknowledge all, api `ackgroup`; JS counts top-level rows). Colours: a red light only for an open trip or a core
  host; a red check otherwise → light `hold` (amber pause mark, "Held"/"Holding"); a trip marks its row red even where it
  switched it off ("Tripped"); tokens `--tv-fail`, `--tv-hold`, `--tv-canvas`, `--tv-line`, `--tv-ink` on `#cb` (the
  Communications session's names); the top bar's light in router.php. 0171 §8 notes. Tests r3d.php 17; r2d updated.
  Live as #144, verified in Sync 21:45.
- `control-board-3e` (built on 21:45): the cleanup item "each crawler run opens to the records it touched". Insights →
  Crawler: `?run=N` is the run's page (command strip; what each task did; its records by workflow, grouped Added /
  Changed / Sent to Review / Refused, each count narrowing it with `&what=`, each record in a Peek); "What a workflow
  touched" (`?records=W1&day=today|yesterday|7`, UTC days) lists a workflow's records by kind and by run; the last runs'
  table links each run with its count. `ControlLedger::whatKind/runCounts/records` (reads `control_records` with
  `control_ledger.run_id`; the workflow from the endpoint, `ControlAlarms::procOfEndpoint`). No new table. Not there:
  values before and after, and each record's source: the ledger keeps ids and field names only (0171 §4). Tests r3e.php 12.
- Cleanup items found done on live (Sync 21:23): AZCC's address in the pre-flight; crawler.php's cron note; the payment
  cards' key names (r2c); outside requests kept (r2c); the switched-off words (r2c); Start's watch counting "nothing to
  do" (r2a); self-withdraw of a failed release; a held-only run ending ok on Railway (worker 2.12.1); W1 coverage
  reading our Geofabrik copy (worker 2.9.0). Next, 3f: the release pipeline's two guards and `Vectors::setupSql()`'s row
  level security.
  Live as #145, verified in Sync 22:06.
- `control-board-3f` (built on 21:45, FAQ on 22:06): cleanup fixes. Staging's payment-key check
  (`StagingGate::PAY_NAMES`, each judged by value; staging could never charge live: `testHere()` on staging forces test
  mode, `MarketPay::key()` refuses live values, `StagingSite` deletes live `market_gateways`/`qb_connection` rows). The
  release pipeline: `fromUpload` reads "none"/"off"/"no" as no switch; `straightOn` notes why on the card (once per
  reason); `Releases::tick` takes a git build left at Planned or Plan approved (`fromGitBuild`). `Vectors::setupSql()`
  with row level security and tv_match for service_role only. Site health: Sources grouped in SQL (J01's timeout);
  `Scheduler::QUIET` and `RETIRED` (D03; the dashboard's status and health.php's `quiet`); the audit's D06 (migrations
  delete themselves), R07 (CSP reports), R12 (workers' tests, example.org). Tests r3f.php 12. Left: V01 card edges
  (design session), the path findings (journeys, hubs, open now: their owners), NPI (data). Next: the 62 unused names
  list for the session manager, then releases 4 and 5.
  Live as #148, verified in Sync 22:26 (0abc114).

**Release 4 in this session** (0171 §15.4, split in two, the session manager's OK 2026-11-18: "build 4a first").
- `control-board-4a` (built on Sync 22:26 UTC, 0abc114): maintenance (0171 §2.5, §1 "blue", §12 `#maintenance`).
  - `control_maintenance` (OWN; migration `2026-11-19_control_maintenance.sql`, `ensure()` too) and
    `app/lib/ControlMaintenance.php`: take (now, or a window ahead), goOut (shut down in order through
    `ControlBoard::stopOne`), putBack (its check first through `ControlBoard::startOne`; red keeps it out, the reason on
    its row, and at a window's end a warning `maint:back:<proc>`), cancel, tick (in `ControlAlarms::tick`, the 5-minute
    watch), out/outFor (a task under its workflow's window), words, listing, upkeep.
  - While out: `ControlAlarms::raise` makes what it raises a notice that says so (a trip already open stays a trip);
    `SystemStart` skips it in its firing and its watch, saying why; the board shows it blue (`maint`, a wrench, token
    `--cb-maint: var(--tv-maint,#1f5f99)`), stage "In maintenance", a system's line counts "in maintenance".
  - The session manager's three notes: a window the watch reached late still goes out and the log says how late; one
    whose Back by passed first is logged as missed (never skipped silently); the upkeep calendar reads only code and
    settings (crawl_capture_tasks' bulk tasks in `CrawlService::NIGHT`, read by reflection; Scheduler presets for
    `data_cleanup_tick` and `staging_night_tick`, "not set up" for a job outside `AUTO` that hasn't run;
    `Preflight::NIGHT_HOUR`, new; held services' `next_probe_at`), each entry opening its process in place or its job
    in a drawer; Pulse #31's list read only.
  - Controls: a row's **Take out for maintenance** (asks why, back in 2 hours) or **Put back**; the Maintenance panel
    (process, Goes out, Back by, why; Put back and Cancel per window). API `config/control`: `maintout`, `maintplan`,
    `maintback`, `maintcancel` (Automation's permission). `health.php`'s `maintenance` block.
  - Pulse #31: Database updates gets **No code names them** (`#unused`), the monthly check's tables and columns with
    their database and size, read only; the case and its "And N more" step link there (`SchemaUpkeep::keptUnused`).
  - The session manager's review (2026-11-18) added: the pre-flight result line amber unless a line really tripped
    (`tripped_at`) or a core host is down; the alarm list's filter as one dropdown button moved into the header
    (`tv_cb_afilter`, `data-ak-cmd`); maintenance in the brand's river blue (`--tv-river`); Take out's length as
    Thresholds' `control_maint_hours`; and zipcheck's lost-lines guard (`lost_lines`, `--base`; test
    `tools/page-audit/test_zipcheck_lost.py`) after 3f's faq.md took out 16 lines #147 had put live. Rebuilt on Sync
    22:50 UTC (2b5e447, the faq-restore #150).
  - Tests: `r4a.php` (39). Rendered at 390px and 1280px, no sideways scroll.
  - Live as #151, verified in Sync 22:55.
- `control-board-4b` (built on Sync 23:10 UTC, fd3a3b4): the historian (0171 §6, §14's Telemetry row).
  - `control_readings` and `control_daily` (OWN; migration `2026-11-19_control_history.sql`, `ensure()` too) and
    `app/lib/ControlHistorian.php`: tick (last in `ControlAlarms::tick`), lights (every process through
    `ControlBoard::process` and the new shared `ControlBoard::boardLight`, which the board now uses too), state written on
    change and hourly, each finished hour's counts once (`ControlBoard::calls`/`outcomes`/`jobRuns` take an optional
    `[from, to]` seconds-ago window; spend and cap % from `Operations::usage` for services), rollup of a finished day,
    trim (`control_readings_days`, Thresholds, 30; daily 400 days), durations, uptime (green or amber against due: off,
    maintenance and untested aren't due), trend (24h hourly, 7d six-hourly, 30d daily; a process or `sys:<key>`
    summed), html (range buttons, an inline SVG: bars of records in, else runs, else calls, over a state strip).
  - The board: a **Trend** line on each process and at the top of each system, loaded when it opens (API
    `config/control` action `trend`, read only), range buttons in place, a 5-minute cache through each redraw. The
    History stage is no longer "coming". Insights → Telemetry points to the board.
  - `health.php`'s `unused_schema` block (names, databases, sizes; the session manager's way to read Pulse #31's list).
  - Database updates' unused list says plainly what it means and that there's nothing to press (Jason, 2026-11-18:
    "not sure what these means to me as admin, i dont see how to act on it"), titled "Unused tables and columns (for
    Claude to sort)".
  - The session manager's checks: `unused_schema` only behind the token (tested with a real untokened request), the
    watch's history step timed (`hist_ms` on job:control_tick) and a slow warning when it grows (`ControlHistorian::timed`).
    Rebuilt on Sync 23:10 UTC (fd3a3b4).
  - Tests: `r4b.php` (35). Rendered at 390px and 1280px, no sideways scroll.
  - Live as #154, verified in Sync 00:09 UTC.
- `control-board-4c` (built on Sync 00:17 UTC, 65deb7b): two fixes from Jason's phone screenshot (the session manager,
  2026-11-19). (1) W0 showed "Tripped" with the passing gate text as its reason: its pre-flight trip was raised before
  checks were read part by part, and a trip never clears by itself, so it kept those words while its real red part (the
  test batch) became its own alarm. `Preflight::alarm` takes `$event` (false: the watch's refresh, words only through
  `ControlAlarms::retitle`, never a level); the watch refreshes every open `pf:` alarm from stored results; a held trip
  names its red part, says "held by its needs/its test batch, each its own alarm", or "its check passes now; it waits for
  an admin's Resolve". (2) A trend with nothing recorded says "No history yet: readings start now" (`trend()['history']`,
  a `.cb-tre` line over the empty bars), and one off or in maintenance all along says so; never "not due to be on".
  Tests: `r4c.php` (9).
  - Live as #156, verified in Sync 00:22 UTC.
- The unused names: `towns.mjs` (search-probe.yml's HEALTH_TOKEN step, PR #990) saves `site-audit/results/unused-schema.json`
  and `alarms.json`. The three lists went to the session manager (2026-11-19); its review asked for each column's rows
  holding a value first, so:
- `control-board-4d` (built on Sync 00:22 UTC, ed8fd78): `SchemaUpkeep::filled` (a listed column's rows holding a value,
  a listed table's exact rows; counts only, identifiers checked, capped at 1,000,000) in health.php's `unused_schema`
  (`filled`, `rows_exact`); towns.mjs saves them. Tests: `r4d.php` (7). Next: the lists to Jason with the counts (one
  question; removals as one release with "removes", copies first), then release 5 (the logbook and reports; with the
  scan's short-name fix: k, v, n, q, w reported as unused).
- `control-board-4e` (built on Sync 00:42 UTC, d08820a; Jason approved it in the pop-up), Jason 2026-11-19 at 5:33 PM MST on a slow trip that had stopped
  W1 and a Resolve that only asked "What fixed it?": "There is no way to fix this" and "no dead ends, system fixes
  first". So: a slow call or job is a warning that says how slow, stops nothing and clears itself (`raise` turns any slow
  trip into a warning; the ledger and the job check raise warnings; `control_slow_trips` retired, kept); the watch lifts
  a slow trip from before itself (`resolve` as "the system", checking first; while a check is red it stays a warning
  and is tried every watch); Resolve is **Check and start again** on the board and in Pulse, with no question (an empty
  note is "Checked and started again"). This changes 3c's rule (slow trips after 3), with Jason's OK. Tests: `r4e.php`
  (8); r3a and r3c updated where they tested the old rule.
- Live: 4c (#156), 4d (#157), 4e (#160); `unused-schema-removal` (#161: 5 tables, 8 columns, copies kept 14 days);
  the principles in `builder-playbook.md` §2 (PR #1004). Overnight 2026-11-19, trips went from 21 to 12 (the slow ones
  lifted; Claude's gates passed when re-tested).

**Release 5 in this session** (0171 §10, §11, §15.5; the session manager's OK and five guards, 2026-11-19).
- `control-board-5a` (built on Sync 00:56 UTC, ad17dec): the logbook, and the night's folded fixes.
  - `control_log` (OWN; migration `2026-11-19_control_log.sql`) and `app/lib/ControlLog.php`: add (who is staff or "the
    system", never a member; the device's zone kept when it's a real one), note (Log an entry), feed (control_log merged
    with automation_log, Start's sessions, preflight_runs, service issues opened and resolved, job_runs failures and
    site_releases going live; newest first; a system as the unit register groups it, or one process; Show older).
  - Writers: ControlAlarms (raised, acknowledged, cleared, resolved, Undo; maintenance's own notices left to
    ControlMaintenance), ControlBoard (start, shut down, the whole system), ControlMaintenance (out, back, missed,
    cancelled), the api (Run its check, Start the system, Pause everything, Check it now, Mark resolved, an alarm's step;
    logged only when the person may press it). `SystemStart::session` made public for the feed.
  - The board's #log panel: its one filter as a dropdown button (the whole system, a system, a process), each row's time
    in its own zone (data-tv-time, data-tz), Show older, Log an entry (spell-checked, with the device's zone). API
    `config/control`: `logfeed`, `lognote`.
  - Folded: (i) phase lines raise no alarm of their own (open ones close on the next watch); (ii) slow judged per record
    when a call carries records; (iii) the unused check skips 1–2 letter columns of used tables; (iv) #19, from 4f, never
    sent alone: a passing pre-flight call closes its host's back-off issue (`ServiceHealth::answered`, never a refusal
    or block), `Preflight::$now` lets a person's Check it now past a back-off's wait, the api's `svccheck` runs the
    service's check at once on a site back-off issue, and the watch rechecks a site back-off issue nothing calls, hourly,
    two a watch; (v) health.php's alarms carry `key` and `reading` (towns.mjs keeps them), to find why W0's old trip
    keeps its words; (vi) a 429 ("asked us to slow down", the session manager, 2026-11-19) is a warning, never a trip:
    `ServiceHealth::alarm` never counts it spent, `holdUntil` gives it twice the wait (20 minutes doubling, or its
    Retry-After), and the watch's `ServiceHealth::liftRateTrips` turns an open 429 trip (Wikidata's of the night) into a
    warning, its `hold_until` back from 2099 to its back-off, logged by the system. Its Check it now is greyed (`ServiceHealth::rateFix`, a `#wait:` fourth element the board renders disabled with its reason and next try in UTC, shown as text beside it since a phone has no hover); `Preflight::held` and the api's `svccheck` never go past a 429's wait (the session manager, 2026-11-19).
  - Tests: `r4f.php` (7, local server as the outside host), `r5a.php` (17), `r5a-rate.php` (18).
- 5a live as release #166 (Sync 04:19 UTC, 2026-11-19). The board then (04:22): Wikidata's 429 amber ✓, phase alarms
  gone ✓, #19 gone ✓; 16 open (6 trips, 10 warnings).
- `control-board-legacy` (built on Sync 04:19 UTC; files: `app/lib/Preflight.php`, `app/lib/ControlAlarms.php`,
  `app/ui/faq.md`): four red trips from before 3c (W0, W3, `rw:batch`, `t:vectors`; `tripped_at` null) kept old words.
  Cause: W0's line is amber, and a title came only from a red part, so it took the first sentence (its passing gate);
  the watch's refresh never changed a level. Now the watch tests each old trip again first (3 a watch, old trips first)
  and settles it with `Preflight::alarm(..., $settle = true)`: passes → closed; amber → its notice; red → a trip timed
  from now when what it holds is switched on, else a warning; logged by the system. Titles: red part, else amber part.
  A line left `wait` over 2 minutes is read back (W0's test batch was asked and never read back); `rw:batch` red over
  half an hour is asked again; a short batch names the indexer's version (`w0:beat.v`), Railway's link under 1.1.1. The
  125 result was from 2026-11-18 20:05, before 1.1.1: stale, not a settings gap. Test: `legacy.php` (11).
- Asked alongside it (the session manager, principle 7): outside servers that fail or time out stay a warning that keeps
  backing off, never a trip; a trip only for a refusal, a rejected key, a used-up quota or our own fault (changes 3c's
  "6 failures or 6 hours"). Built only on Jason's yes, its own zip with its test and the decisions/0171 note.
- Legacy live as #168 (Sync 11:20 UTC). Jason's answer on the rule change (2026-11-19): "We already have a rule for how
  trips get set": 0171 §6's 3c note stands (a back-off trips at its case limit); nothing built, never re-asked.
- `control-board-backoff-gate` (built on Sync 11:20 UTC; files: `app/lib/Operations.php`, `app/lib/ServiceHealth.php`,
  `app/lib/ControlAlarms.php`, `app/admin/control-console.php`, `website/admin/api.php`, `website/api/health.php`,
  `app/ui/faq.md`): Wikidata's 429 made Wikimedia "held" and W1 tripped at 09:00 through its gate. `Operations::status`
  now calls a back-off whose alarm isn't a trip `paced` (`ServiceHealth::spentHold`). From Jason's screenshot: a tripped
  row shows Check and start again (its Resolve) instead of Start, and its Check says the trip stays red. The 10-minute
  release job quiet for 30 minutes raises `rel:job:quiet` (legacy waited 25 minutes: the ping got "too soon");
  `health.php` carries `releases_looked`. W1's trip isn't lifted by the system: Jason's Check and start again. Test:
  `backoff-gate.php` (12).
- backoff-gate live as #169 (Sync 11:50 UTC). W1's trip waits for Jason's Check and start again.
- `control-board-5b`, the reports (built on Sync 11:58 UTC, its files unchanged since 11:50; files: `app/lib/ControlReport.php` (new),
  `app/admin/control-console.php`, `website/admin/api.php`, `website/js/admin-control.js`, `website/api/health.php`,
  `app/lib/DailyReport.php`, `app/ui/faq.md`): `ControlReport::build($pdo, $scope, 'day'|'week')`, built on demand from
  what's kept (no table, nothing stored, no AI, no outside call), so no staging class or retention is needed. The board's
  `#reports` panel, and the report as a query on config/control (`?report=<scope>&range=`); Download CSV and the page as
  a self-contained file through the api's `report` action (a Blob in the browser). Summary: the daily report's System
  status (Monday adds the week), and `health.php`'s `alarms_summary`. Scrubbed of emails, phones and IPs; no readings.
  Test: `r5b.php` (17).
- Next: the decision's releases are all built; what's left is in the review list.
- Known shortcut in 3c: a back-off held by a trip is `service_issues.hold_until = '2099-01-01'` (until a person resolves
  it; `ServiceHealth::holdForTrip`), not a flag of its own. A real flag (a column, `held_by_trip`) needs a migration on
  `service_issues`; do it with the next ServiceHealth change.
- Not in 3c, still open: the worker reporting its own retries to the ledger (0171 §5 "Retry", needs a worker change);
  admin Pulse read state (Communications' split, 0172); and the cleanup fixes below (the crawler-run page and "W1 today",
  off-region sources with a rule first, the release pipeline's two guards), for the release after 3c. Then releases 4–5.

**Release 3 (decisions/0171 §15.3): real-time monitoring, the alarms and Pulse.**
- `control_alarms` and `control_tick`; slow, retry and crash caught when they happen (§5).
- The annunciator (§7): the top-bar light, the email, the health report's `alarms` block and the hourly push with the
  health token.
- Pulse fixed (§8): one item per alarm, acted on in place, closed by itself; the five Pulse cases become alarms.
- Folded into 3 (below, "Cleanup fixes"): Pulse read state per person; the old Overpass/OSM cases; Pulse #22's Site
  health items; the release pipeline's guards (a bad `release.json` can't strand a release at Planned; a rebuilt zip
  under the same name isn't stuck at "Plan approved").

**Carried from release 2** (0171 §15.2 lists them; not built):
- **The crawler run records, part 2.** There is no command-strip page per run, and no "W1 today" filter on Insights →
  Crawler. Today the board's crawler row opens each run to its records, from the ledger.
- **The worker doesn't report its retries** to the ledger yet (§4), so §5's "retry" needs that first.
- **`control_state`** (§4) isn't built.
- **The Pre-flight page isn't archived.** It's a live section, and its lines also fold in at the board's foot.
  Archive it when release 3's alarms cover what it shows, and only with Jason's OK (archive.md).
- **Start's card still lives on Automation.** The board's Start the system calls the same `SystemStart::start`; the card
  can move to the board in release 3.

**From Jason's example board, not built** (he saw this list when approving the layout):
- **Fix / Disconnect / Add source on a need:** they would change what holds a workflow, against 0163's source map, so a
  decision comes first.
- **Maintenance windows** (release 4).
- **The health token and the Pulse panel** (release 3).
- **Uptime % and cost per record** (the historian, release 4).

**How this work runs now:**
- Sessions merge their own PR after Jason's Approve in their pop-up, then tell the session manager, which syncs
  (CLAUDE.md, #921).
- A build that fails staging is the session's to clear: send `-2` with `"replaces"` (builder-playbook).
- Every check that goes red must name the part that holds it (#924's lesson: never match on words).
- Jason judges the board by his example boards (`design/control-board-mockup.html`, and the row he sent on 2026-11-18:
  columns of metrics on the row, labelled one-line details, needs as chips). New parts follow that look: a summary
  line first, details on a toggle, nothing open on load.
- From the row example, still to build: **Log** and **Report** buttons on each row (release 5), **cost per record**
  and **uptime** (release 4; the OK rate stands in until then).

**Tests:** `tools/control-board-tests/`: r1 (33), r2a (21), self-withdraw (17), r2b (14), layout (8), r2c (12), r2d (7),
releases-live.cjs (7). The worker's: `workers/crawler/tests/` (test_held_task, test_indexer and the rest).

## What Jason asked for

Jason, 2026-11-18: "I see START as PREFLIGHT, ACTIVATION, MONITORING, REPORTING for the entire system (each step
accessible and able to see what is doing and the data its generating)." Then: "In powerplant management, the site is
under operations management with a control room where all of the plants information is monitored and controlled. The
control board is where an operator can see every sensor and status of the entire power plant operation so I want our
system to have a very similar system." And: "neither of them, especially START, does not include endpoints or a way to
see what data is being captured and or stored."

## The outline (agreed in the session manager's chat, 2026-11-18)

| Control room | Ours |
|---|---|
| **Mimic board**: the whole plant as a flow, each unit lit by its state | One diagram: sources → workers (W0 indexer, the crawler, the video worker on Railway) → the site's endpoints (`/api/crawl/*`, 21 today) → tables → Review / published / held. Each box green, amber, red or grey with its live numbers |
| **Sensors and gauges** | Every pre-flight line, service, workflow, task, endpoint and table is a gauge: its value, its limit, when it last read; click for the detail and the data behind it |
| **Controls at the board** | Start, pause or resume each unit, run a check, mark an issue resolved, in place (never another page; drawers for other tools) |
| **Interlocks** | The pre-flight and Start's confirm step: a unit switches on only when its needs are green; one that trips stops alone |
| **Startup sequence** | Start: confirm, then switch on in dependency order; the board shows each unit coming on |
| **Annunciator** | One alarm list by priority (trip, warning, notice): what happened, what to do, its button; acknowledged; clears itself when fixed; to Jason's phone and the session manager's reports, never a Pulse case for approvals |
| **Historian** | Each gauge's readings kept over time (with a size limit); a trend per unit: runs, records in, stored or refused, spend, speed |
| **Logbook** | Every switch, alarm and acknowledgement, with who and why |
| **Shift report** | Daily and weekly, for the whole plant and per unit, downloadable and phone-ready; a short summary for the morning brief |

Pre-flight and Start become parts of the board (its interlocks and startup sequence), not separate destinations; their
current pages stay reachable until the board replaces them (archived, not deleted: `archive.md`).

## The order

1. **A decision** (new ADR, numbered next after `decisions/0170`), amending 0163 §10 and folding in the pre-flight and
   Start: the board, its units and gauges, the register, the ledger, the historian, alarms, logbook, reports, where it
   lives in the admin (decisions/0109: a section from the manifests, built from widgets), access, and what it replaces.
   **Ask Jason for his OK in your own chat with the decision pop-up before any build, then tell the session manager.**
2. **Release 1: the register + the three pre-flight fixes.**
   - The register: in code, each unit (workflow, task, worker, service) names its site jobs, crawler tasks, services,
     endpoints and tables; the board, Start and the pre-flight read it, so a new workflow appears by itself.
   - Start's confirm step re-tests the source-map lines (`Preflight::NEEDS`) its workflows depend on before confirming
     them. Today `Preflight::workflow()` reads the last stored `nd:` results, so a stale or never-run need doesn't hold
     a workflow.
   - Start's confirm step runs the four query timings (`Preflight::TIMINGS`).
   - `SystemStart.php`'s header comment still describes the old one-at-a-time pacing: correct it.
3. **Release 2: the sensors and the ledger, and the board's live view.** Each `/api/crawl/*` call and each run records
   what it received, stored and refused, and the ids of the records it touched (the worker and the endpoints report
   them; new tables name their class in `app/lib/staging-tables.php`, R14). This takes in queue item "each crawler run
   opens to the records it touched" (handoffs/session-manager.md, cleanup stage).
4. **Release 3: the historian and the annunciator.**
5. **Release 4: the logbook and the shift reports.**

## Where it stands (kept current by the build session)

- **2026-11-18:** the decision is written: `decisions/0171-the-control-board.md` (proposed). It was checked against the
  live mirror at "Sync: 2026-11-18 02:36 UTC" and sent to the session manager for Jason's OK. Nothing is built.
  Release 1 starts after his answer.
- **2026-11-18, later:** Jason answered "needs changes". Revision 2 of 0171 is in the same PR (#896). It adds:
  - organization by system, then process, with each gauge holding its whole process;
  - six stages, with shut-down and maintenance new;
  - real-time monitoring of slow, retry and crash;
  - alerts three ways (system notification, Pulse, push), and the health token explained;
  - Pulse fixed: one item per alarm, closed by itself;
  - complete routing per process (fast, efficient, accurate);
  - five releases instead of four.

  Sent back to the session manager for his OK.
- **2026-11-18:** Jason's example board is saved as `design/control-board-mockup.html` ("for your reference … you
  will be the decision makers"). Decision 0171 §12 lists what Release 2 keeps from it and what it changes. A summary for
  sharing, corrected against the code, is at https://claude.ai/artifact/699FnCRCPZBR5N3HxxyW1v.
- **2026-11-18:** Jason approved 0171 revision 2. Next: Release 1 (the register and routes, plus the pre-flight fixes).
- **2026-11-18, Release 1 built** on "Sync: 2026-11-18 02:36 UTC" and sent as `control-board-1.zip`. It holds:
  - `app/lib/ControlRegister.php` (new): systems, processes, workers, the 23 worker endpoints with their tasks and
    tables, `of()`, `dependsOn()`, `route()` and `unclaimed()`;
  - `Preflight.php`: the Routes group, with one line per workflow (W0–W12, W14), one per worker and "Every endpoint
    claimed". They are optional and hold nothing. `refreshFor()` is new;
  - `SystemStart.php`: the header rewritten; the confirm step calls `refreshFor()` before each line; the four timings run
    once a session (`$st['timings']`); a failed query becomes a step on the card;
  - `Thresholds.php`: `pf_fresh_mins` (60);
  - `faq.md`: two entries.

  Repo changes: zip-check rule R15 (a worker endpoint must be in `ControlRegister::ENDPOINTS`) and
  `tools/control-board-tests/r1.php` (33 pass).

  Checked at 390px: the Pre-flight console was rendered with the route lines open, with no sideways scroll. The Start
  card only gains words on its confirm line. Nothing new to press: a route line's Test is the existing button, and it
  was run through `Preflight::run`.

  Next, after it's live: Release 2 (the ledger and the live board).

- **2026-11-18: Release 1 is live** as #120, synced at "Sync: 2026-11-18 13:57 UTC". All 5 files in the mirror match
  the zip byte for byte. **Release 2 builds on that sync.**

- **2026-11-18: Release 2 split** into 2a (the ledger, no page change) and 2b (the board page, start-up, shut-down),
  with Jason's "stay as current session until each phase of the build is completely built and live".
- **2026-11-18: Release 2a built** on "Sync: 2026-11-18 13:57 UTC" and sent as `control-board-2a.zip`. It holds:
  - `ControlLedger.php` (new): `begin()` from both `_auth.php` files, an output buffer, a row written at shutdown;
    `touched()` marks, one line per record; `counts()`; `trim()`; `forRun()`;
  - the migration `2026-11-18_control_ledger.sql` (`control_ledger`, `control_records`; both `follows`);
  - marks in `IntakeStager::stage` (Review item, listing stored or gaps filled, refreshed in place) and
    `CrawlerIngest::upsertMetadataKey` (a field updated, or kept because a person entered it);
  - three settings: `control_ledger_days` 90, `control_records_days` 30, `control_records_per_call` 500;
  - Start's `runs()` counts a task's own crawler outcomes (`crawl_run_items`: done or idle good, failed bad);
  - the AZCC pre-flight line tests `arizonabusinesscenter.azcc.gov` and says "blocked pending the records request"
    on its 403;
  - FAQ: one entry.

  Closes these folded-in fixes: Start's "nothing to do" watch, the AZCC line, and the recording half of "each crawler
  run opens to the records it touched" (2b draws it). Tests: `r2a.php` 21 pass, `r1.php` still passes, the old
  `start.php` the same as live. Counting rule: received = stored + updated + Review + refused, judged in release 3.
  When it's live, tell Jason that Resume on the AZCC service card is his call (it stays paused).

- **2026-11-18: releases-self-withdraw**, built on Sync 13:57 UTC, sent before the fixed 2a (Jason: "Once you do, we can
  talk about approving the correction"). It changes `Releases.php`:
  - `withdrawAs()` with `unstage()`;
  - `selfWithdraw()`, run as a build arrives from git and swept by `tick()`;
  - `replaceEarlier()` for `"replaces"`;
  - the card's words.

  It closes the folded-in fix "a release that fails its staging checks is withdrawn by itself", ahead of release 3. #905
  (control-board-2a-2) is closed until this is live; then rebuild it as `-3` on the new live copy (its `faq.md` must
  carry this release's entry) with `"replaces": ["control-board-2a"]`.

- **2026-11-18: #121 withdrew itself on live** at 15:00 UTC (#122 works).
- **2026-11-18: releases-live**, built on Sync 14:55 UTC. `js/admin-releases.js`: the page looks again every 15 seconds
  and redraws changed cards in place, and a press redraws its card without a reload (Jason: "release page needs to update
  to current states", "alerts and notices need to be cleared once fixed or changed"). After it's live, rebuild 2a as
  `control-board-2a-4`: its `faq.md` on the new live copy, `"replaces": ["control-board-2a"]`.

- **2026-11-18: #123 (releases-live-2) live**, synced at 15:14 UTC. The Releases page refreshes itself, and its checks fold
  into one line. `control-board-2a-4` was built on that sync (the `-3` build set aside, never merged), with `"replaces":
  ["control-board-2a"]`.

- **2026-11-18: #124 (control-board-2a-4) live**, synced at 15:21 UTC. The ledger is live. Then **releases-marks**, built
  on 15:21: a green check, red X or grey dash at the start of each release and each check (Jason). After it: 2b, the
  board page.

- **2026-11-18: #125 (releases-marks) live**, synced at 15:25 UTC. Then **releases-table-2**, built on 15:25 (Jason:
  "formatted like our standard table format"; "a release opens on toggle under the title"; "why keep the green
  notification?"). The Releases list is laid out as a table: search, one Shown filter, a header row, one row per release
  in columns, each opening on its toggle under its title, stacking on a phone. There is no green notice on a live release
  that's well. Only `sections.php` (the Releases section), `admin-releases.js` and `faq.md` change. The first
  `releases-table` (each release on its own page) was withdrawn before merge: Jason said a page of its own deviates from
  how our tables open. 2b waits behind it.

- **2026-11-18: #126 (releases-table-2) live.** Then **control-board-2b**, built on Sync 15:59 UTC (`ee082f7`): the
  board itself at `/admin/config/control`, first in System Configuration.
  - New: `app/lib/ControlBoard.php` (the stage, a process's readings from the ledger, Start it with the interlock,
    Shut it down, Shut down in order, "stopped, safe"), `app/admin/control-console.php` (the page),
    `website/js/admin-control.js` (the buttons; redrawn every 30 seconds, keeping what's open).
  - Changed: the `control` section in `manifests.php`, a require at the top of `sections.php`, the `config/control`
    tool in `website/admin/api.php` (check, start1, stop, start, shutdown, pauseall), and one FAQ entry.
  - No tables or migrations. Pre-flight stays its own section (not archived); its lines also fold in at the board's foot.
  - Tests: `r2b.php`, 14 pass. Rendered at 390px and 1280px with no sideways scroll; Run its check posts the right id.
- **2026-11-18: #127 (control-board-2b) live**, synced at 16:13 UTC. Jason: System Configuration's side-panel icon should
  open the board (it does: the router opens a module's first section). He then sent an example board made with Gemini
  ("review this. Please adjust the page layout and presentation of data so I can better use and understand our system
  board"). That gave **control-board-layout**, built on 16:13: a status strip (holds, core hosts, last tested), the stages,
  the controls with find and Open all / Close all, two panels (Holding now; Query timings with Re-test), each system a
  card of rows in columns (Process, Where it stands, Last 24 hours), and each row opening to labelled lines with a Needs
  table (sources in order, ours first, ✓/✗ by the need's own verdict, a Test each). New in ControlBoard: needs(),
  core(), usedBy(), timings(). Tests: `layout.php` (8). Left out of his example, for later or a decision first: Fix,
  Disconnect and Add source on a need (they'd change what holds a workflow, against 0163's source map), maintenance
  windows (R4), the health token and Pulse panel (R3), uptime % and cost per record (the historian, R4).
- **2026-11-18: control-board-layout live** (synced 16:50 UTC, all 4 files matched). Then **control-board-2c**, built on
  16:50, closing release 2's folded-in fixes:
  - `outside_requests`, the requests' own table (old `staff_settings` ones brought over with ids; #3113488 seeded);
  - the Stripe and Square cards read MarketPay's names (plain set live, `STRIPE_TEST_…`/`SQUARE_SANDBOX_…` for tests);
  - "Added switched off" reworded (`Operations::ADDED_OFF_WHY`, migration `2026-11-18_added_off_words.sql`, data_reason);
  - `/admin/crawler.php`'s cron steps: Start the system sets the schedule;
  - crawler 2.12.1: a task stopped by a held service reports held (the tripping call too; Common Crawl checks first),
    so a run whose only trouble is a held service ends ok, not Crashed.
  - Tests: `r2c.php` (12), `workers/crawler/tests/test_held_task.py` (5).
  - Release 2's fixes closed: "nothing to do" counts (2a), AZCC's host (2a), the held-run
    exit, cron words, Stripe/Square, Name.com's requests, the off words (2c). **Partly:** the crawler run records: the
    ledger keeps them (2a) and the board's crawler row opens each run to its records (2b), but there is no page per run
    yet, nor a "W1 today" filter on Insights → Crawler: carried to release 3. Not taken: Vectors RLS and coverage from
    Geofabrik (handed back, as planned).

### Where the folded-in cleanup fixes go (build session, 2026-11-18)

Release 1 closes none of them (it was built before they arrived). The plan:

- **Release 2: the ledger, the board, start-up and shut-down, the services' gauges**
  - each crawler run opens to the records it touched (the ledger's `control_records`);
  - Start's watch counts "nothing to do" as a good run;
  - a run whose only failure is a held service ends ok, not CRASHED;
  - `/admin/crawler.php`'s outdated "Cron Schedule */5" words;
  - the pre-flight's AZCC line tests the new host;
  - the "Added switched off" wording, by a data migration with `data_reason`;
  - the Stripe and Square cards read the keys `MarketPay` reads;
  - Name.com's outside requests that don't save (their own table, like the pre-flight's).
- **Release 3: the alarms and Pulse**
  - Pulse shows what each person has read;
  - the old Overpass and OSM cases resolved;
  - Pulse #22's Site health items;
  - the release pipeline as a process with alarms: a build that fails its staging checks is withdrawn by itself, a bad
    `release.json` can't strand a release at Planned, and a rebuilt zip under the same name isn't stuck at "Plan
    approved".
- **Release 4: maintenance and the upkeep calendar**
  - Pulse #31's 62 unused names (one list for Jason: safe, keep, unsure), and that case's blank tool view;
  - the leftovers `_moved_npi_orgs`, `_moved_irs_orgs` and `search_index_state.v`, removed under 0162's `"removes"`.
- **Rather leave out of the board's releases:**
  - **`Vectors::setupSql()`'s row level security:** a security fix, too small and too urgent to wait for Release 2.
    Its own small release.
  - **Coverage reading our Geofabrik copy before Overpass:** it changes how W1 gathers, which 0171 leaves alone. The
    source-map session's line of work.

## What exists to build on (live mirror, `sync-app/` = `app/`, `sync-storage/` = `website/`)

- **Pre-flight**: `app/lib/Preflight.php` (GROUPS: the site, outside services, W0 and the crawler, query timings, the
  source map `NEEDS`, the site's needs, workflows, tasks, phases `PHASES`, the switch-on check); page
  `app/admin/preflight-console.php`, https://traversence.com/admin/config/preflight.
- **Start the system**: `app/lib/SystemStart.php` (steps keys → watching → switch-on check → confirm operational →
  Railway's schedule → master → W0…W12 → tasks; `gate()` and `runs()` watch each unit alone; report download and
  "Operational: N on"); `app/lib/Automation.php` (WORKFLOWS, JOBS, switches); `app/admin/automation-console.php`,
  https://traversence.com/admin/config/automation#start.
- **Services and holds**: `ServiceHealth.php` (issues, kinds, probes, holds), `Operations.php` (the register of caps,
  `ADDED_OFF`, status), `OutsideServices.php` (COMPANIES, hosts), `Services.php`, `Thresholds.php`, `Clocks.php`
  (the 14-day ramp), `Scheduler.php` (site jobs, cron lines).
- **Crawler and W0**: `app/lib/crawler/CrawlService.php` (runs, `crawl_runs`), `Frontier.php`; consoles
  `app/admin/crawler-console.php` (Insights → Crawler), `app/admin/index-console.php` (Insights → The index (W0)),
  `app/admin/operations-console.php`, `app/admin/cleanup-console.php`.
- **Endpoints**: `website/api/crawl/` holds 21 endpoints plus `_auth.php`: commoncrawl, geocode, guides, heritage,
  identity, index, irs, jobs, landmarks, learn, loads, npi, places, rec, refresh, results, service, sources, targets,
  usage, verify.
- **Workers**: `workers/crawler/` (crawler and `indexer.py`), `workers/video/`, on Railway (project
  a445f0be-b6de-46bb-a422-51ff835ca6e4): `traversence` (cron */5, set by the site), `w0-indexer` (always on),
  `video-worker`. Never change a Railway cron or restart policy; the site sets them.
- **Pulse** carries notices, not approvals (Jason, 2026-11-17).

## What earlier build sessions did (summary, oldest first)

- **Nov 14.** These sessions laid the foundations:
  - **Crawler, migrations and switches:**
    - The crawler stopped retrying APIs that refuse us (406).
    - Migrations now run at publish, with safe rollback (0162; `handoffs/migration-safety.md`).
    - The automation switches arrived: master, workflow and service, shipped paused (#48; `handoffs/automation-switches.md`).
    - The data cleanup tool shipped (#50, #51; `handoffs/data-cleanup.md`).
  - **The Knowledge Engine's documents:** 0164 and its rule book; the rural research tests in Kayenta, St. Johns and Springerville; the gap review; and Jason's approvals applied to 0163 and 0164.
- **Nov 15.**
  - **Release A** (operations, stages 2–6) added the service register, the caps and the holds.
  - The **duplicates** cause was found and the self-correcting recheck planned (`handoffs/duplicates.md`).
  - **0165:** staging became live in safe mode, as one release (#56, #57; `handoffs/0165-staging.md`).
  - **Release B** (the Knowledge Engine, stages 7–11) shipped as #58–#67 with automation off (`handoffs/release-b.md`).
  - Smaller work: stock photos fixed (#68); Bluehost's bot check tested on every caller.
  - The first one-button **Start the system** was built.
- **Nov 17.**
  - **Domains:** domains and the wallet (#71); domain sales on their own switch (#89; `handoffs/domains.md`).
  - **W0 indexer:** every URL logged and always on, with its own Railway service (`handoffs/w0-indexer.md`).
  - **Admin:** the Releases page folds each release (#80); the favicon on every page.
  - **The pre-flight** (#88–#94, `handoffs/preflight.md`).
  - **Gates and switches:** the staging gate on all 36 outside calls (#102); scheduled tasks and their pages linked both ways (#105).
  - **Source map (b):** W1 reads our OpenStreetMap copy before Overpass (#101), and a single timeout is no longer a stop (#114).
  - Jason pressed Start at 18:44 UTC.
- **Nov 17–18.** The same source-map session rebuilt Start:
  - confirm operational within minutes, then switch on in order (#115);
  - the report download, a truthful notice and the phone top bar (#116);
  - fixes in place and in drawers (#117);
  - the card fits a phone, with "Switch W1 on" (#118);
  - service problems explained on the card, and the tables as cards on a phone (#119).
  - The system has read "Operational: 19 on" since.

## Lessons those sessions left (read `handoffs/builder-playbook.md` first)

Failed or stuck releases:
- MODIFY in a migration (#70);
- `"switch":"none"` (#108, #109);
- a re-sent zip name (#106).

`send.py` now refuses all three.

Deviations Jason had to correct:
- fixes that sent him to another page;
- an action with no way back;
- page reloads to the top;
- a card wider than a phone;
- stale wording;
- asking in Pulse or in plain lines instead of the pop-up;
- re-proposing what a decision already set;
- two PRs recording the same delivery.

The playbook covers each one.

## Cleanup fixes folded in (Jason, 2026-11-18)

Jason: "clean up fixes that touch the system should move into the system board session so they can be addressed as it
builds the board." Moved from the session manager's queue (cleanup stage). Fold each into the board release it
belongs to (most fit Release 1's register and routes, Release 2's ledger and Start/shut-down, Release 3's alarms and
Pulse, or Release 4's maintenance); say in each release which ones it closes, and list any you'd rather leave out.

- **Each crawler run opens to the records it touched** (Jason, 2026-11-18: "yes queue the crawler run records fix").
  Insights → Crawler (https://traversence.com/admin/insights/crawler, `app/admin/crawler-console.php`) counts what
  each run and task did, but nothing opens the records themselves, so "what did W1 bring in today" means searching
  Listings and Review by hand. Build: each run's task line and each count (added, changed, sent to Review, held,
  failed) opens a list of those records, each linked to its page (a listing, a Review item, a landmark, a guide),
  with what changed (fields before and after; under 0164 a run never overwrites what a person entered) and the
  source it came from; a filter for workflow and day ("W1 today").
  Find first whether runs already record the ids they touched (the run log, `crawl_*` tables, W0's audit log); if
  not, the worker and `api/crawl/` report them and a new table names its class in `staging-tables.php` (R14). A
  command-strip record page per run (0129), with Peek on each record.
- Pulse #31's 62 unused names (Jason, 2026-11-17: "just add it to the queue"): the monthly check found 62 tables
  and columns no live file names (0-row tables like `accuracy_reports`, `ai_credit_usage`,
  `gbp_addon_subscriptions`, `guide_mentions`; dated backups like `listing_actions_bak_20261014`). A cleanup
  session checks each against the live code, names built on the fly included, and gives Jason one list: safe to
  remove, keep (with the reason), unsure. On his OK, a release removes the safe ones (copied first, under 0162,
  named under "removes"), Keep it is pressed on the rest, and the case closes. With it, Jason's rule (2026-11-17):
  "we dont want a ton of non-used items in our dbase". Also: that case's "Open the tool" view is blank on the
  left (no tool drawn for it); give it its list, or open the case alone.
- Pulse #22's Site health items;
- `/admin/crawler.php`'s outdated "Cron Schedule */5" instruction (Start the system sets the cron now).
- Outside services' Stripe card checks `STRIPE_LIVE_SECRET_KEY`/`STRIPE_LIVE_CLIENT_ID` for live, but `MarketPay` reads
  live keys as `STRIPE_SECRET_KEY`… (no `LIVE_`), so a correct live setup shows "keys missing" (found 2026-11-16).
  Make the card read what `MarketPay::missingAll()` reads; same check for Square's card.
- `Vectors::setupSql()` (Supabase card) leaves `tv_vectors` without row level security and `tv_match` callable by
  Supabase's public roles. Add `enable row level security` and revoke `tv_match` from anon/authenticated/public with
  a grant to service_role (Jason ran these by hand on 2026-11-16).
- Outside requests don't save (Name.com ticket #3113488, after #84/#87/#89): likely the same cause the pre-flight
  found in `search_index_state.v`, a live column shorter than the code assumes, cutting the JSON silently; fix as
  the pre-flight did (their own table), confirmed by the card's "Can't keep requests" line.
- **A build merged while its zip name was already in the inbox stalls at "Plan approved"** (release #106,
  2026-11-17): `large-fallbacks.zip` was sent, then rebuilt under the same name; on merge the site recorded "taken
  from the inbox (it was already in)" and never put it on staging, and Check for builds said "nothing is waiting".
  Jason had to press Put it on staging, then Publish. Fix: a rebuilt zip (new bytes, same name) is taken as a new
  build and goes on to staging by itself; a release left at Plan approved with its zip present is picked up by the
  10-minute job and by Check for builds; and a Claude build that passes staging publishes itself as usual.
- **Unused database leftovers are removed, not kept** (Jason, 2026-11-17: "we dont want a ton of non-used items in
  our dbase"). First: `search_index_state.v`, the 200-character column left after #107 added `v_long` (add-only,
  since the release check refuses `MODIFY`). Once every reader uses `SearchIndex::state()` (grep for direct reads of
  `search_index_state`, e.g. health.php's listing_mark) and `v_long` holds every row, a later release copies any
  remaining `v` into `v_long`, moves reads and writes to `v_long` alone, and drops `v` as a recorded leftover under
  0162 (named under `"removes"` in release.json; Pulse #31's unused-schema list shows it). Same sweep for the other
  leftovers that list names.
- **The pre-flight's Arizona Corporation Commission line tests a dead address** (2026-11-17): `Preflight::test`
  case 'azcc' still calls `ecorp.azcc.gov`, which no longer resolves; the Commission moved to
  `arizonabusinesscenter.azcc.gov`, which answers our host 403 "Access Blocked". Test the new host and say it's
  blocked pending the records request (one line), not "No answer".
  When it's live, tell Jason that Resume on the AZCC service card is his call (it stays paused until then).
- **The "Added switched off" note is out of date** (2026-11-18, from Jason's phone): AZCC, NM SoS and the Arizona
  licence lists still read "Paused: Added switched off (Jason, 2026-11-15): nothing calls it until Release B and a
  person's switch". Release B has shipped. Reword `Operations::seed`'s reason (and the live rows' `paused_why`, by
  a data migration with `data_reason`) to "until a person switches it on" plus each one's real reason: AZCC and NM
  SoS, the address fix and the records request; the licence lists, Cloudflare (by hand, queue 3a).
- **OpenStreetMap coverage jobs wait forever on Overpass** (2026-11-18, the crawler's log): coverage jobs
  800000013 and 800000014 say "waits for OpenStreetMap, every OpenStreetMap server is held" (overpass-api.de's ban
  and the three mirrors held). W1 already reads our Geofabrik copy first (#101); coverage still asks only Overpass.
  Make coverage read the Geofabrik copy the same way, Overpass only as a fallback when one is open (never retried
  while held). Also: the crawler exits non-zero when its only task (Common Crawl, held by issue #13) fails, which
  Railway shows as CRASHED; a run whose only failure is a held service should end ok.
- **Start's watch counts "nothing to do" as a good run** (2026-11-18, the source-map session): the identity
  recheck and the vector index show "0 good runs" because their runs end "nothing to do", which
  `SystemStart::runs()` doesn't count (and `t:recheck` skips `crawl_runs` entirely), so their watch ends only at the
  6-hour mark. Count a task's own crawler outcomes, ok or nothing to do, as good. Small; Jason's OK as its own release.
- **A release that fails its staging checks is withdrawn by itself** (Jason, 2026-11-17: "withdrawing bad releases
  needs to be addressed so it can be automated upon fails"). Today a failed release sits on staging ("check failed:
  upload a fixed zip or put it on staging again") until someone presses Withdraw: #70, #78, #108 and #109 were all
  withdrawn by hand. Make it automatic: when a Claude build (from git) fails its staging checks, the site withdraws
  it ("Withdrawn: its checks failed: <which check and why>"), records that in its release record (so the building
  session sees it in git), and raises one Pulse item for staff; nothing on live changes. A build uploaded by a
  person isn't auto-withdrawn (they may fix it on staging), but gets the same Pulse item. A later build with the
  same name replaces it as usual.
- **A bad `release.json` can't strand a release at Planned** (#108, #109, 2026-11-17: `"switch": "none"` was stored
  as a switch key, `approve` found no such switch and threw, so both sat at Planned and never reached staging;
  Jason, on #109's card: "this is not how this is supposed to work"). `send.py`/zipcheck refuse a `switch` that
  isn't empty or a known `Switches::DEFS` key; `Releases::fromUpload` treats "none" as empty; a release that can't
  be approved says why on its card and in Pulse, never sits silently.
- **Pulse shows what each person has read** (Jason, 2026-11-17: "also need a way to know when Ive read something in
  the pulse"): Pulse items and cases have no read state per person today (only message threads and sources' rules
  do). Add it: an item is **unread** (bold, a dot) for each staff member until they open it; it goes unread again
  when something new happens on it (a step, a note, a change of status); a filter "Unread" in the list's one
  filter dropdown and a count on Pulse's tab; "Mark all read" and "Mark unread" per item. Read state is per person,
  so two people can see what the other hasn't opened; kept small (one row per person per item, last seen time).
- **Database leftovers:** the `_moved_npi_orgs` (38 MB) and `_moved_irs_orgs` (16 MB) tables in the health report:
  confirm they're leftovers of a finished move (decisions/0110, 0162's leftover check in Pulse) and nothing reads
  them, then drop them through a release's `"removes"` ⚑.
- **Pulse:** the old Overpass/OSM cases from before the pause (resolve, keeping the overpass-api.de one), and the
  62 unused names (above).

## decisions/0173 release 1, One home (2026-11-19, built on Sync 22:28 UTC, f0f3329)

`service-register-r1.zip`, 25 files (more than the decision's estimate of about 6: most are one-line moves of the 22 links
to `/admin/config/register`). Jason approved it, and the removal of `tv_admin_mod_services`, in the pop-up.
- The Service register draws Outside services' table and cards (`tv_outside_services($pdo, ['blocks', 'stages'])`), with
  the register's parts first on each card (`tv_ops_reg_block`), the Pre-flight count line kept on top, the month's totals
  and report at the foot. `router.php`'s `TV_ADMIN_MOVED` sends `config/services` there (302, query kept, the browser
  keeps the `#svc-`). Pulse.php's 8 links are left to the redirect (its items rule).
- Stages (`ServiceStages`): derived from Automation's switch, `Operations::keys`, `ADDED_OFF` and whether a person ever
  switched it on; `Automation::log` also writes `service_stages`. "Added" (not "Added, off"): a service on with no key is
  Added too, and its words say which.
- Board: a service's process has a switch (Pause, Activate; `startOne`/`stopOne` handle `svc:`); Watching Railway and the
  sync is the panel `#cb-watch` (api `watch`).
- Tests: `register.php` (36), `peek-anchor.cjs` (10, the old address too), `card-issue.php` reads the merged card.
- **Follow-up (harness only):** `r2b.php` fails the same on the live copy and this release: its schema (`r2a-schema.sql`
  plus the ledger) lacks `automation_switches`, `crawl_workflows` and what `CrawlService::pause` needs. Add them to
  `r2a-schema.sql` so r2b runs again.
- Next: release 2, the key on its card (`EnvSettings::setSecret()`, NPS first); then release 3, defined once.

## decisions/0173 release 2, the key on its card (2026-11-19, built on Sync 22:49 UTC, 58fe949)

`service-keys-r2.zip`, 8 files; Jason approved it in the pop-up (reviewed by the session manager).
- `EnvSettings::setKey()` is 0173 §3's writer (named setKey: `setSecret()` already writes the secrets the site makes). Its
  guards are inside: a key it knows (`secrets()`: Operations::KEYS kept in .env, not one also on Railway, plus
  MAIL_SECRETS), never on staging, the Founder only (`Access::who()`, since `Access::can` lets Executives through), a
  value .env can hold; logged as key and how, never the value. `removeKey()`, `putBack()` through the same guard;
  `setMailSecret()` calls it, so Executives no longer reset mailbox passwords (0167 §4: owner-only).
- `keepOld()` and `forgetOld()` now write through `swap()`: the same lock as `write()`, the old file's permissions.
- The card (`tv_ops_keys`): Add its key / Replace / Remove / Put the old one back / Forget, and Check (the service's
  pre-flight line, run at once after a save; a pass forgets the old value). Railway's keys: the full link to the
  crawler's Variables, and Check.
- `NPS_API_KEY` is optional (the photos; NPGallery needs none): it never holds a switch-on, and its check asks
  developer.nps.gov with it.
- Tests: `keys.php` (30). Next: release 3, defined once (`service-defs.php`, Proposed, Retire, Removed list).
