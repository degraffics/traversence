# The System Control Board's tests (decisions/0171)

- `r1.php`: release 1. It covers the unit register (systems, processes, a workflow's parts, what a line depends on), the
  Pre-flight's Routes lines (each hop checked, an unclaimed endpoint named), Start testing what each workflow depends on
  fresh before confirming it, and the four query timings once a Start. 33 tests.

- `r2a.php`: release 2a. It covers the ledger written after each worker call through the endpoints' own `_auth.php`
  (on PHP's built-in server), received, stored, updated, Review and refused by reason, the records touched (one line
  per record), an error answer, a call that isn't signed in, a call before the table exists, trimming, the run's link,
  and Start's watch counting a task's own "nothing to do" runs as good. 21 tests. Run it with `r2a-schema.sql`, the
  migration and `zz_test.php` (a test endpoint, placed at `website/api/crawl/` in the test root only, never shipped):
  `mariadb < r2a-schema.sql && mariadb lv_main < <app>/migrations/2026-11-18_control_ledger.sql`, then
  `(cd <root>/website && php -S 127.0.0.1:8765 &)` and `php r2a.php <root>/app`, with `CRAWLER_API_TOKEN=testtoken`
  and `VIDEO_WORKER_TOKEN=vtoken` in the `.env`.

- `self-withdraw.php`: a build from git that fails on staging withdraws itself; the job's sweep; `"replaces"`;
  Withdraw putting staging back (migrations off, live's copy back, new files off, a later release's copy kept). 17
  tests: `mariadb -e 'DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main' && php self-withdraw.php <root>/app`.

- `releases-live.cjs` (with `releases-live-page.js`): the Releases page keeping itself current (a release drawn again
  as it moves on, its stale notice gone, a new one at the top, open cards kept, a card being typed in waiting), at 390px.
  7 tests: `node releases-live.cjs <website/js/admin-releases.js>`.
- `r2b.php`: release 2b, the board: its stage, a process's worker calls and records from the ledger, Shut it down
  (tasks first), Start it refused while its check is red, Shut down in order (workflows in reverse start-up order, then
  the master switch) and "stopped, safe" once no crawler run is going. 14 tests: after `r2a`'s schema and 2a's
  migration, `php r2b.php <root>/app` (on a fresh `r2a-schema.sql`, which since 2026-11-19 has the switches and
  workflows it needs; not straight after r2a, whose crawler run is still going).

Run on MariaDB or MySQL, with a root that holds `app/` (the mirror's `sync-app` with the release's `app/` laid over it)
and `website/api/` (the mirror's `sync-storage/api/crawl` and `api/video`). Put a `.env` four folders up from `lib/`:
`mariadb < ../release-b-tests/start-schema.sql && php r1.php <root>/app`.
- `layout.php`: the board laid out after Jason's example: a need's sources marked by the need's own verdict, the core
  hosts, which needs use a company. No database: `php layout.php <root>/app`.
- `r2c.php`: release 2c: requests to outside companies in their own table (old ones brought over with their ids, the
  seed once, an answer closes one), the Stripe and Square cards' key names, the switched-off words' migration.
  12 tests: `mariadb < ../release-b-tests/start-schema.sql && php r2c.php <root>/app` (the root holding the release's
  `app/migrations/`). The worker's side: `python3 workers/crawler/tests/test_held_task.py`.
- `r2d.php`: a check shown part by part (Jason, 2026-11-18: "Red x but green checks too?"): W0's check with a red dry
  run names the dry run as what holds it, never the gate whose words say "no red line"; the board lists the parts with
  their marks, the red one first. 7 tests: after `r2a`'s schema and 2a's migration, `php r2d.php <root>/app`.
- `r3a.php`: release 3a, the alarms: raised once per problem (a repeat updates it, worse becomes a trip), cleared by
  itself saying how, acknowledged and undone; the ledger's server errors and wrong counts (by the process each endpoint
  serves); the 5-minute watch (a run that never reported, a job that stopped, W0 quiet); the seed (red pre-flight lines
  named by the part that holds them, W2's server error, off-region sources), the dispatcher's alarm kinds and the Pulse
  body with Acknowledge. 24 tests: after `r2a`'s schema and 2a's migration, `php r3a.php <root>/app`.
- `r3b.php`: release 3b: Overpass as one trip (raised once for every refusing server, a notice per server folded in,
  Acknowledge doesn't close it, cleared when the last refusal is resolved), a reading that repeats not counted as a new
  time, the seed's 5xx only when the latest call failed, the ▸ toggle (no "…", no More) and the check's other parts never
  repeated, old lines told to press Check, a long button list dropping whole buttons, an alarm's Pulse steps (Raise the
  cap as a form, Pause it, Mark resolved) and a step it doesn't carry refused. 17 tests: after `r2a`'s schema and 2a's migration, `php r3b.php <root>/app`.
- `r3c.php`: release 3c, the Trip rule: a crash a warning for its tries, a trip on the third (or after an hour);
  refusals at once; a trip stops only its own workflow, the master switch only when the crawler itself is down, a site job
  tied to no workflow only trips; its good reading noted, never cleared by itself; Resolve asks what fixed it, starts
  again what it stopped, a red check keeps it open with the reason, Undo; a back-off at its limit holds the server until a
  person resolves it; Overpass's trip not cleared by a 200; slow calls at the ledger; the steps' families; Pulse's
  Resolve and Undo; a red required line a warning while the system is stopped. 28 tests: after `r2a`'s schema and 2a's
  migration, `php r3c.php <root>/app`.
- `r3d.php`: release 3d: the group key (system, level, family), five engine feeds held as one group and a lone alarm on
  its own, Acknowledge all, Pulse's group item with its children (a done child leaves it), the board's group row with
  its alarms' own buttons and a fold the redraw keeps, the marks' red and amber. 17 tests: after `r2a`'s schema and 2a's
  migration, `php r3d.php <root>/app`.
- `r3e.php`: release 3e: a crawler run's page (the command strip, its records by workflow and kind, Peek links, a
  person's field said to be kept, a count narrowing it, a run no longer kept), "W1 today" and the last 7 days, a workflow
  with none. 12 tests: after `r2a`'s schema and 2a's migration, `php r3e.php <root>/app`.
- `r3f.php`: release 3f: staging's live payment keys by value under every name the code reads, the release pipeline's
  pieces, Supabase's row level security, Sources in one grouped query (and its search), quiet and retired jobs. 12 tests:
  after `start-schema.sql`, `php r3f.php <root>/app`.
- `w2-verify.php`: W2's second look: a listing whose check throws is set aside with its error; the board reads it.

- `r4a.php`: release 4a, maintenance: what can be taken out, refusals (no why, a Back by first, an overlap), taken out
  now (shut down in order, its warnings notices, a trip kept), a task under its workflow's window, new alarms as notices,
  Start skipping it, Put back with its check red (kept out, the reason on its row), a window ahead going out and coming
  back by the watch (kept out on a red check, with a warning), a late window and a missed one, Cancel, the upkeep
  calendar read from code, Database updates' read-only unused list, and the staging class. 35 tests:
  `mariadb < ../release-b-tests/start-schema.sql && php r4a.php <root>/app`.

- `r4b.php`: release 4b, the historian: state written on change and at least hourly, uptime (maintenance and off not
  counted), an hour's counts once (the windowed `ControlBoard::calls`), trends for 24h, 7d and 30d and for a system
  summed, the SVG and its words, the day's rollup and the trim, the board's lights through `boardLight`, the trend
  endpoint, health.php's `unused_schema` (and none without its token, on PHP's own server), the watch's history time, Telemetry
  and the staging class. 35 tests:
  `mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php r4b.php <root>/app`.

- `r4c.php`: release 4c: an open pre-flight alarm's words follow its line as it stands now (W0's trip named by its test
  batch, a red part by itself, a passing line "waits for an admin's Resolve"), the refresh never changing a level, and
  a trend with nothing recorded saying "No history yet". 9 tests: `mariadb < ../release-b-tests/start-schema.sql && php r4c.php <root>/app`.

- `r4d.php`: release 4d: how many rows hold a value in each unused column, and each unused table's exact rows
  (`SchemaUpkeep::filled`, counts only, only names on the list, identifiers checked), carried by health.php. 7 tests:
  `mariadb < ../release-b-tests/start-schema.sql && php r4d.php <root>/app`.

- `r4e.php`: release 4e: a slow call or job is a warning however many times in a row and stops nothing, a slow alarm
  asked for as a trip by any caller is a warning, the watch lifts an old slow trip itself (or keeps it a warning while a
  check is red), and Resolve needs no words ("Check and start again" on the board and in Pulse). 8 tests:
  `mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php r4e.php <root>/app`.

- `r4f.php`: #19's fix (in 5a): a passing check closes its host's back-off issue, Check it now goes past the wait, a refusal
  is never passed or closed by the system, and the watch's hourly recheck. PHP's own server is the outside host. 7 tests:
  `mariadb < ../release-b-tests/start-schema.sql && php r4f.php <root>/app`.
- `r5a.php`: release 5a, the logbook: what writes to it (alarms, acknowledgements, clears, shut-downs, switches), who
  (staff or the system), newest first, Log an entry (words, staff, its zone), the filters and Show older, the rows'
  labelled times; phase lines, speed per record and the short names. 17 tests:
  `mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php r5a.php <root>/app`.
- `r5a-rate.php`: release 5a, a 429: a warning however long it lasts, twice the wait (Retry-After obeyed), a timeout
  still trips at its limit, Check it now is greyed with the next try (never past the wait, only timeouts and server errors go past), the watch turns an open 429 trip into a warning (its hold back to its back-off, logged), and
  it clears when the host answers. 18 tests: `mariadb < ../release-b-tests/start-schema.sql && php r5a-rate.php <root>/app`.
- `legacy.php`: control-board-legacy, after 5a: a pre-flight trip from before 3c (no trip time) settled by the watch to
  what a fresh raise would be today (closed, its notice or warning, or a trip timed from now when switched on), logged;
  titles by the red part, else the amber part; a trip with its time keeps its level; W0's waiting or short test batch
  asked again, naming the indexer's version. 11 tests:
  `mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php legacy.php <root>/app`.
- `backoff-gate.php`: control-board-backoff-gate: a back-off in its wait is paced, not held (0171 §6's 3c note), a
  refusal or a back-off tripped at its limit still holds; a tripped row offers Check and start again, never a bare Start,
  and its Check says the trip stays red; the 10-minute release job quiet for 30 minutes warns. 12 tests:
  `mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php backoff-gate.php <root>/app`.
- `r5b.php`: release 5b, the reports (0171 §11): every section from seeded tables (uptime from the historian, alarms in
  the window with who acknowledged, when cleared and how long open, faults, the logbook's shut-downs, spend), a scope and a
  week, the summary's 3–5 lines, the CSV's columns as the page's, the page as a self-contained file, nothing private (a
  seeded email, phone and IP never appear), no new table or outside call. 17 tests:
  `mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php r5b.php <root>/app`.
- `links.php`: control-board-links (#175): Check and start again checks first; a Resolve acknowledges; a group's header
  opens it; Check it now closes a not-set-up issue; Start's card and the switch-on check link to the board. 9 tests.
- `offregion.php`: control-board-offregion: sources read outside our region (a country's address or places mostly
  outside AZ and NM; never a shortener or a generic ending), the welcome rules, Pause, Pause all and Undo for a day,
  shorteners followed (one try an hour, 429 backs off), the watch's recount; Acknowledge leaves the open list at once.
  17 tests. Run as the others.
- `r12.php`: control-board-12 (0171 §12): the four tables (alarms with their drawer's Acknowledge and Check and start
  again, greyed with the reason; logbook; ledger with the records each call touched; upkeep), executives only, as
  sections under the board; each process's own page from its path, an unknown one, and the row's drawer link. 12 tests.
- `filter.php`: control-board-filter: a system's header counts are buttons that filter its rows, Show all, kept through
  redraws (checked in a browser at 390 and 1280). 5 tests: `php filter.php <root>/app`.
- `routes.php`: control-board-routes (Jason, 2026-11-19: "I always hit a dead end"): every ControlAlarms raise in the
  code (read by the tokenizer) carries a fix, and each link lands on one item (a hash, a query or an id); the Ledger
  call, the run, the job, Railway as a link only, the pre-flight line's own fix; Supabase catching up measured; the
  stage tiles' routes; one pre-flight; the Runs line; one alarm count. 64 tests, static: `php routes.php <root>/app`.
- `critical.php`: control-board-routes, 0171 §5's note (Jason, 2026-11-19): duties on the register (defaults, changed,
  logged), a critical part's first failure a trip at once while still retrying, Recovered closing itself with nothing to
  press, the Escalation Notice when its tries run out (then only Resolve), any other part unchanged. 20 tests, on
  MariaDB: `php critical.php <root>/app`.
- `sensor.php`: control-board-critical-2 (0171 §5's second note): the hook (no connection, table missing, a watched
  path only, path and status only), one stray error a notice, 3 a critical trip, Recovered, a failed test and the
  Escalation Notice, QuickBooks held, the search test changing no table, 7 days kept. 25 tests:
  `php sensor.php <root>/app`. (Also tried for real: a 500 from /api/search.php under `php -S` is noted.)
- `proctable.php`: control-board-table (design/table-standard.md T1, 2026-11-19): the parts as group rows per system
  in `tv_table()`, each part's columns, no history said once, ⋯ with greyed reasons, the Show filter with counts, the
  search, a part's own page in a drawer (`?proc=`), the table's buttons through the board's actions; the component's two
  fixes (a table of only groups; a phone card's actions); a block named by its reason; an alarm link to its own row.
  27 tests: `php proctable.php <root>/app`. (r2d now reads W0's detail on its own page.)
- `itsdata.php`: control-board-itsdata (Jason's answers, 2026-11-19): W0 logging an address about another state as
  "Outside our region" and never reading it (a welcome rule brings it back), the sites that blocked us as one notice (a
  warning only past `w0_block_share`), Its data on a process's own page (its records, what happened, a link each, ids
  only), and Pulse's gathering time (the middle of the last 20, a warning past `control_pulse_ms`, never a trip). 22
  tests: `mariadb < ../release-b-tests/start-schema.sql && mariadb lv_main < <2a's ledger migration> && php itsdata.php
  <root>/app` (it makes a `crawl_frontier` with the columns it needs when the schema has none).
- `board-rendered.cjs` (with `board-page.php`): what the board did before its parts moved into the table (#192), tested
  in the rendered board at 1280 and 390 (playbook §3): a system's counts filter its parts and open it, again shows every
  part; the systems opened stay open through the table's redraw; Open every system / Close them; an alarm's Holds: link
  opens the part's own page; the search finds a part by a source's name; no sideways scroll. 20 tests (it fails on the
  board as #192 left it): after the start schema, 2a's migration, `render`'s seed and a trip on W1,
  `node board-rendered.cjs <root>`.
- `peek-anchor.cjs` (with `peek-anchor-page.php`) and `evidence.php`: control-board-landing (Jason, 2026-11-19: "Open card
  shows the specific place to fix issues"). The side panel keeps a link's anchor (its own settings go before the #), so
  an alarm's **Open its card** opens the real Service register on that one card, opened, in view and marked, at 1280 and
  390 (7 tests; it fails on the panel as it was). A workflow's, task's or the crawler's alarm opens its run: the one its
  fix names, else the run going when it was last seen, the list only with no run yet (6 tests). `node peek-anchor.cjs
  <root>`; `mariadb < ../release-b-tests/start-schema.sql && php evidence.php <root>/app`.
- `card-issue.php`: control-board-card (Jason, 2026-11-19: "how am I supposed to fix this?"). A service's card in the
  Service register shows its open issue (what came back, how often, since when, what it means, what to do) with the
  board's buttons: Check and start again on a trip, else Check it now (never for a 429) and Mark resolved. 9 tests:
  `mariadb < ../release-b-tests/start-schema.sql && php card-issue.php <root>/app`.
- `overpass-off.php`: overpass-off (Jason, 2026-11-19, "Stop entirely"): the site asks Overpass nothing (no request code
  left, `near()` reads our copy only), a job's kinds outside our copy are "not in our copy", no server handed out, probed
  or budgeted, the pre-flight sends nothing for it, the crawler's report becomes one quiet notice on W1, OpenStreetMap's
  card shows its servers retired; and Geofabrik's pre-flight line follows a redirect on its own host. 15 tests:
  `mariadb < ../release-b-tests/start-schema.sql && php overpass-off.php <root>/app`. (r3b, r3c, r3d, r1 and layout follow
  the retirement; the worker's side is `workers/crawler/tests/test_ours.py`.)
- `osm-copy.php`: osm-copy-wider (Jason, 2026-11-19): our OpenStreetMap copy holds five states (Arizona, New Mexico,
  Utah, Colorado, Nevada) and carries health care and guides; another office kind isn't in our copy; a Utah town's health
  care read from it; the states not loaded yet are due. 7 tests: `mariadb < ../release-b-tests/start-schema.sql && php
  osm-copy.php <root>/app`. The worker's side is in `workers/crawler/tests/test_ours.py`.
- `start-issue.php`: osm-copy-wider's Start-card fold-in (Jason, 2026-11-19: "If this was resolved by system or marked as
  resolved by admin it should be handled"): the Start card's issue carries the board's buttons, never Mark resolved;
  the issue and its alarm close together whichever way, saying how. 7 tests:
  `mariadb < ../release-b-tests/start-schema.sql && php start-issue.php <root>/app`. (r3c follows it.)
- `register.php`: decisions/0173 release 1 (One home): the update run twice, the stages from what's true now, one card a
  service with the register's parts, Pause and Activate on a card and on the board, Overpass retired while OpenStreetMap
  stays Active, Watching on the board, the old address's links moved. 36 tests: `mariadb < ../release-b-tests/start-schema.sql`,
  the release's migration in `<root>/app/migrations`, then `php register.php <root>/app`. `peek-anchor.cjs` also opens the
  old address and lands on the same card.
- `keys.php`: decisions/0173 release 2 (the key on its card): EnvSettings' writer (`setKey`) and its guards (a key it
  knows, never on staging, the Founder only, never logged), Add, Replace, Put back, Forget, Remove, the card's buttons
  (greyed for an Executive), Railway's keys with their link, the Park Service's optional key. 30 tests (the .env stays 0600 through every change); it writes the test
  root's `.env` and puts it back: `mariadb < ../release-b-tests/start-schema.sql && php keys.php <root>/app`.
- `defs.php`: decisions/0173 release 3 (defined once): `tools/page-audit/service-defs.php --check` passes and refuses a
  hand edit naming its fix; with the live app as a second argument, every list has the same values but Overpass's split;
  Overpass its own retired entry, OpenStreetMap kept; Retire, Bring it back, nothing switching a retired one on; Proposed
  only words; the register's Retire fold, Bring it back, Proposed and Removed. 32 tests:
  `mariadb < ../release-b-tests/start-schema.sql && php defs.php <root>/app <mirror>/sync-app`.
- `board-menu.cjs`: the board's parts table, every system open and the last row at the window's bottom: its ⋯ menu
  is wholly in the window, each item tappable, and Esc closes it, at 1280 and 390 (T4's fix, on the board itself). 6 tests:
  after the start schema and 2a's migration, `node board-menu.cjs <root>`.

## The control board's build (step 4, `handoffs/control-board-build.md`)
- `run-all.sh <root>`: every PHP test here and in `../dispatcher-tests/` on the bench, each on a fresh `lv_main` with
  its Run line's schema (the deleted migrations' tables recreated), with the worker endpoint served on 127.0.0.1:8765.
- `engine.php`: the one engine (P2–P12, D1–D4, D8). `gauges.php`: the gauges' data (`ControlGauges`, the lifecycle
  rule, the compact cache under 60 KB, each drawer's detail). `heartbeat-import.php`: the outside heartbeat's records
  numbered when the site is back.
- `module-cards.php` and `module-cards.cjs` (with `module-cards-page.php`): the four-zone card (03 §1–§9), its two rows,
  Fix it only for what a person can fix, Check now through the engine, at 1280 and 390.
- `workflows-page.php` and `workflows-page.cjs` (with `workflows-page-fixture.php`): the Workflows page
  (`workflows-page.md` §5, §7: 8 kept, 2 moved, 3 dropped), W13 a list, W14 Not built, D8, D9.
- `board-views.php` and `board-views.cjs` (with `board-views-page.php`, `board-views-seed.php`): Is it delivering?,
  Development with Site health's list, Incidents, the folded report, Copy by section, an acknowledged trip kept open (D5).
- `scenario/`: the scenario test over step 2's failure map (`scenario/cells.md`: 173 cells to 02 §4.9's 135 scenarios); after each scenario, `views()` draws every alarm it left on the board's row, in Pulse and on its module card and checks the words (159 of 173 cells).
- `rendered-all.sh <root>`: every rendered (`.cjs`) test of the board and the dispatcher on one bench, each on a fresh `lv_main` with its seed (`board-rendered-seed.php` adds the founder and W1's trip), at 1280 and 390. `board-views.cjs` presses Write the root cause on an Incident Report.
- Repo-side: `../heartbeat/test_heartbeat.py` (the outside heartbeat).
