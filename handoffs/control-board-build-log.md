# The control board's build (step 4): the build session's log

The build session (session_01CXLi5ibQFnVmXNfkdFarwR) keeps this current as it goes: what's done, what's next, the sync
it's built on. It is the record, not a hand-over (playbook §5). The plan is `handoffs/control-board-build.md`.

**Built on:** the mirror at Sync 2026-11-22 21:29 UTC (`degraffics/traverse-3way-sync` `7c99b2c`). Fetched again before
each file is written and just before zipping.

## Done

### 1. Other sessions' claims (2026-11-22 21:35 UTC)
- Only open claim: `website/js/location-scope.js` (Design and trips, session_01CXLi5grMsUgrRMJv3a71F9,
  location-popup.zip). Not one of the board's files. 3e's market claims had lapsed.
- Design and trips' next (0174 R5, openrouteservice) is likely to touch `app/ui/faq.md`, and perhaps
  `app/admin/manifests.php` and `app/admin/tools.php`. `faq.md` is add-only, never claimed: this build re-applies only
  its own entries on the newest live copy just before zipping (CLAUDE.md).

### 2. Why the ledger shows 0 calls (read 2026-11-22 21:50 UTC)
- **The write path works.** `website/api/crawl/_auth.php` calls `ControlLedger::begin` for every worker call and the
  row is written at shutdown. Proof on live: the open alarm `ep:crawl/refresh:count` ("received 2, counted 3") was
  raised at 09:15:57 UTC today (`site-audit/results/alarms.json`), and `ControlLedger::alarms()` runs only after the
  row's `INSERT` succeeded. Proof here: `tools/control-board-tests/r2a.php` passes 21 of 21 on the live code.
- **The 0 is on the read side.** The board and the report count a workflow's calls only through
  `ControlRegister::of()`, which gives a workflow the endpoints whose `ENDPOINTS[…][1]` names the workflow or one of
  its tasks as `crawl_capture_tasks` holds them, plus the crawler's own `crawl/service` and `crawl/usage`. Reproduced
  here: with a `crawl/refresh` row in the ledger, W2's "What ran" counts only the `crawl/service` call, and "Records: 0
  in" stays 0, because the record-carrying endpoints are attributed by task name and the summary adds only received
  and stored.
- **Still to settle with one read on staging** (its `control_ledger` is copied from live nightly, class follows): the
  Ledger tab's newest rows, https://traversence.com/admin/config/control?tab=ledger (endpoint names, and whether
  `crawl/refresh`, `crawl/loads` and `crawl/results` rows are there this week), and `crawl_capture_tasks`' task names
  per workflow (C6). The engine's fix doesn't wait on it: each endpoint becomes a part with its own component in the
  manifest (D1, D6), so its calls are counted on the endpoint, then rolled up to the tasks that use it.
- **A second defect found:** `website/api/video/jobs.php` and `results.php` are measured (r2a's "the video worker's
  calls too" passes), but the video worker's endpoints are attributed to no workflow, so their calls show nowhere on
  the board. In the build: the video worker's own part.

### 3. Claims (2026-11-22 21:40 UTC, release `control-board.zip`)
- `--change`: the 33 files in the plan's §2.3, plus `website/api/video/jobs.php` and `website/api/video/results.php`.
- `--add`: `app/lib/staging-tables.php`, `app/admin/manifests.php`, `app/lib/ControlEngine.php`,
  `app/lib/ControlIncidents.php`, `app/lib/ControlGauges.php`, `app/ui/score-gauge.php`, `website/js/tv-copy.js`.
  (`faq.md` is never claimed.)
- `app/lib/Pulse.php` is claimed by function when the functions are known.
- Claims last four hours: renewed with `checkout.py renew` while the build runs.

### 4. The test bench
- MariaDB 10.11 and PHP 8.3 in the session; a root holding the mirror's `sync-app` as `app/` and `sync-storage` as
  `website/`, with a `.env` four folders above `app/lib/`.
- **Baseline on the live code: 1,050 checks pass, 3 fail** across `tools/control-board-tests/` and
  `tools/dispatcher-tests/`. The 3: `paused.php` (its 0175 migration was deleted from the server after it ran),
  `r1.php` (the r2a test endpoint left in the test root), `start-issue.php` ("a used-up quota answering again doesn't
  close its trip by itself", the rule the model's P4 replaces). `register.php` can't run: the 0173 migrations were
  deleted after they ran. The deleted migrations' tables are re-created in the bench from the code that writes them.

### 5. The engine and its callers (2026-11-22, 21:50–22:15 UTC)
Built in `handoffs/control-board-build/` (each file started from its live copy at `7c99b2c`):
- **`app/lib/ControlEngine.php` (new):** the component that failed (P2: `componentOf`, `classifyTask` from a task's own
  calls; a read timeout in a bulk load is `/api/crawl/loads`'s, IR-0001); the diagnostic clock (P3: tries at 5, 15, 35 min
  inside the hour, repeats inside them one fault; a part with no check of its own trips when its next run fails too,
  holding nothing, ruling 3); a trip's checks every 30 min after its tries (§7); the radius (P6: an endpoint holds the tasks
  that call it, a source the tasks with no working later source, the crawler all gathering, a job only its own task D8,
  a task's own code and a workflow's roll-up nothing); the check (P4, P7: a person's check past a back-off and a refused key
  or quota, never past a block or a 429; the system's own try past a back-off's wait only); words from state (P9, ruling 5).
- **`app/lib/ControlIncidents.php` (new):** IR-#### at every trip, joined within 15 min by host and kind (O1), radius,
  timeline, root cause Unconfirmed until written; kept 365 days (Q19).
- **Migrations:** `2026-12-01_control_engine.sql` (A), `2026-12-01_control_incidents.sql` (B), both run twice cleanly;
  `2026-12-01_npi_switch.sql` (D12: one guarded `UPDATE` and its log line; it needs `"data_reason"`).
- **`ControlAlarms`:** the store of the engine: `raise` asks `decide`, `stop` places `radius`, `expireTries` runs the
  engine's clock, `resolve` checks first (D3), the notice's action follows what's held ("Down: the system is still trying",
  "Tripped: nothing is held"), Fixed by a person when their press was confirmed by its next run.
- **Callers:** `CrawlService::report` passes what failed and drops "other tasks carry on" (D4); `ServiceHealth`'s
  back-off is on the engine's clock, its own case limit gone, `classify` reads the manifest's `answers` (Tavily 432, D10);
  `Preflight`: a workflow's line is a roll-up and raises nothing (02 §2.2, Lift (a)); `SystemStart`'s watch raises through
  the engine and never writes a switch (D21); `Automation::putBackTripSwitches`: Start's setting isn't a person's (ruling 4);
  the watch: a skipped job run is never good, no evidence is never a clear (g3 F1, J2); `_auth.php` answers `?check=1`
  before the ledger measures it.
- **Fixes decided:** D6 (the report counts every ledger call; calls no workflow claims get their own row), D7 (the Data
  browser lists every realm's tables, labelled), D11 (IRS: shape check before any write, no delete below `irs_floor_pct`,
  90%), D12 (NPI's switch), D13 (a refused Turnstile secret lets people through, a critical trip on sign-in), D14
  (`workers/video/railway.json`: on failure, up to 10; reaches `main` only with the release).
- **Tests:** `tools/control-board-tests/engine.php` (new, 39); 13 test files moved to the model's rules, each changed line
  naming the rule that replaced it. **1,094 checks pass**; the one failure is the bench's own test endpoint (as on live).
- **Claims added:** `website/api/crawl/irs.php`, `website/admin/crawler.php`, `website/admin/data.php`; released
  `website/api/video/jobs.php` and `results.php` (not needed).
- **Found, outside this zip:** the crawler worker drops a 432 before reporting it (`workers/crawler/crawler.py`
  `note_issue`: no kind, no report), so Tavily's plan limit reaches the site only once the worker reports any status ≥ 400.
  A worker release; for the session manager to place.

## Next, in the plan's order (§2.2)
1. Read C15 on staging (§6), and the Ledger tab's rows: read-only, wanted from a runner or Jason.
2. Remaining callers: Operations, ControlSensor, ControlMaintenance and ControlHistorian (they raise through the store,
   so through the engine already; their own words to move to state).
3. The dispatcher path (D19): a person's close updating Pulse and email; a trip to nobody; Site health on one path.
4. The views (D15–D17): the Arc gauges, the 16 development gauges, the four-zone cards, the Workflows page, the folded
   Reports, Copy by section; W14 "Not built" now that workflow lines raise nothing.
5. The outside heartbeat (D23, `.github/workflows/heartbeat.yml`, on this branch until the release merges).
6. The scenario test over the failure map, and the rendered checks at 1280 and 390.
7. FAQ, the release summary, zip check, send with `"review": true`.
