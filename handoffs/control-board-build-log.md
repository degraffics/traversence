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

### 6. The dispatcher path (22:20 UTC)
- A person's close (Check and start again that passes) updates the same thread on every channel: "…: Fixed by <name>"
  (P10; g4 F3). `Notify`'s push title knows the actions by name, since "Down: the system is still trying" holds a colon.
- A trip with nobody to receive it says "Reached no one" on its row and raises the dispatcher's own trip, routed to
  https://traversence.com/admin/config/communications#cs-controls (P11; g4 F4).
- An open trip counts whether or not it's been seen (g4 F1). **Still to match in the views:** the board's Open list
  and `control-console.php:781` filter on `ack_at IS NULL`, so an acknowledged trip must stay in them too (D5).
- 1,095 checks pass (the bench's own `zz_test` endpoint is the one failure, as on live).

## Hand-off at a hold (2026-11-22 22:25 UTC)

This session (session_01CXLi5ibQFnVmXNfkdFarwR) stops here at a hold, its context long (CLAUDE.md, 2026-11-20: hand off
at a hold, never mid-step). Everything below is on the branch `claude/control-board-build`, **not in `main`**, and
nothing is zipped, sent or live. A fresh session takes it over after the session manager's review, and builds the rest
of the one release (the plan's step 4); this file and `handoffs/control-board-build.md` are its brief.

**Built on:** Sync 2026-11-22 21:29 UTC (`7c99b2c`). Fetch the mirror again before touching any file: a file changed
there since gets only this build's change re-applied (CLAUDE.md, "Just before zipping").

**What's built (the zip's files so far, each started from its live copy), in `handoffs/control-board-build/`:**
- new: `app/lib/ControlEngine.php`, `app/lib/ControlIncidents.php`, `app/migrations/2026-12-01_control_engine.sql`,
  `2026-12-01_control_incidents.sql`, `2026-12-01_npi_switch.sql` (has an `UPDATE`: `release.json` needs
  `"data_reason"`).
- changed: `app/lib/` `Automation.php`, `Captcha.php`, `ControlAlarms.php`, `ControlBoard.php`, `ControlReport.php`,
  `Notify.php`, `Preflight.php`, `ServiceHealth.php`, `SystemStart.php`, `Thresholds.php` (`irs_floor_pct`),
  `service-defs.php` (`role`, Tavily's `answers`), `staging-tables.php` (`control_incidents` in OWN),
  `crawler/CrawlService.php`, `crawler/IrsBulk.php`, `crawler/NpiBulk.php`; `website/api/crawl/_auth.php`,
  `website/api/crawl/irs.php`, `website/admin/crawler.php`, `website/admin/data.php`, `website/admin/api/data.php`.
- repo: `workers/video/railway.json` (D14; reaches `main` only in the release's merge, after Jason's Approve);
  `tools/control-board-tests/engine.php` (new) and 13 moved test files, `tools/dispatcher-tests/r1.php`, `r2-notice.php`.

**Defects done (§4 of the plan):** D1, D2, D3, D4 (raisers' stale words; W13/W14's "Running" still in the views), D6,
D7, D8, D10 (site side), D11, D12, D13, D14, D19, D21, D22 (records; their page in the views). Partly: D5 (counts; the
Open list in the views), D18 (roles in the manifest; hosts and endpoints as parts on the board in the views), D20
(critical duties as before, now on the engine's clock).

**Left, in order (each sourced in `handoffs/control-board-build.md`):**
1. **Renew or take over the claims.** This session's claims (`releases/checkouts.json`, release `control-board.zip`)
   lapse between 01:40 and 02:15 UTC on 2026-11-23. The next session releases them
   (`checkout.py release --session session_01CXLi5ibQFnVmXNfkdFarwR <paths>`) and claims the same files as its own at
   once, plus the views' files (`app/admin/control-console.php`, `operations-console.php`, `preflight-console.php`,
   `automation-console.php`, `index-console.php`, `website/js/admin-control.js`, `app/ui/score-gauge.php`,
   `website/js/tv-copy.js`, `app/lib/ControlGauges.php`, `app/lib/Pulse.php` by function, `app/lib/Thread.php`,
   `app/lib/SiteHealth.php`, `app/lib/ControlRegister.php`, `app/lib/OutsideServices.php`). Check Design and trips'
   claims first (0174 R5).
2. **The views (D15–D17, D5's list, D9, D16, D18):** 03 §27 (Arc, Jason's choice), the 16 development gauges and the
   runtime lifecycle (`step3/sections/gauge-lifecycle.md`, `check-register.md`), the four-zone cards with PL's two rows
   (02 §0 PL, §8), the Workflows page (`step3/workflows-page.md` §7: 8 kept, 2 moved, 3 dropped), the folded Reports
   (D16), Copy by section (`tv-copy.js`, D15), the Incident Reports' page (IR-####), W13 as a list and W14 "Not built".
   **Since a workflow's line now raises nothing**, the two live notices that came from workflow lines ("The route of W14
   Authority sources: Nothing carries it yet" and "W1 Listings: Needs: …") close on the first read; their facts must show
   on the workflow's own page (W14 "Not built"; W1's needs on its card), never be lost (playbook: replacing a screen
   keeps everything it did).
3. **The outside heartbeat (D23):** `.github/workflows/heartbeat.yml`, on the branch only; its cell runs as "not set up
   (ALARM_TO)" until Jason sets the two secrets (close.md A1).
4. **The scenario test** over step 2's 173 cells and 02 §4.9's 135 scenarios, on the bench (below) with
   `ControlEngine::$checker` as the stand-in for outside checks; then the rendered checks at 1280 and 390.
5. FAQ entries (plan §7), the release summary (every behaviour with its source; the admin's test per new state; kept,
   moved, dropped), zip check, `send.py` with `"review": true`, the session manager's review, Jason's pop-up.

**Open items for the session manager to place:**
- **The ledger's live read:** the Ledger tab's newest rows and `crawl_capture_tasks`' names per workflow (read-only,
  runner or Jason), to confirm which endpoints the board was missing. The fix doesn't wait on it.
- **The crawler worker drops a 432** before reporting it (`workers/crawler/crawler.py` `note_issue` returns when its
  classifier gives no kind): Tavily's plan limit reaches the site only once the worker reports any status ≥ 400 with its
  status and body. A worker release, outside this zip.
- **C15 (`irs_loads` on staging)** is still unread; the IRS fix (D11) doesn't depend on it.

**Rulings this build applied where a test said otherwise (each in the test's own words):**
- A task failing in its own code holds nothing; its next run failing too is a trip that holds nothing (ruling 3),
  replacing 3c's "a trip stops its workflow" (r3c, paused, dispatcher r1, r2-notice).
- The system's tries are its own checks of what failed at 5, 15 and 35 minutes (ruling 2, P3), replacing the count of
  repeats and ServiceHealth's 6-failure case limit (expire-tries, critical, backoff-gate, r3c, r5a-rate).
- A workflow's line is a roll-up and raises nothing (02 §2.2), replacing its own trips and their settle (legacy, r4c,
  r3a's seed).
- A used-up quota whose probe answers again closes as Recovered (§3c; principle 1's exception is only a block): this
  test was already failing on the live code (start-issue).
- Start the system's setting isn't a person's (ruling 4): a switch only Start set stays off for a person (paused).
- A person's check goes past a back-off and a refused key or quota (P7, g2 F2), never past a block (principle 1, r4f).

**The bench (to run every test on the build):** MariaDB 10.11 and PHP 8.3 in the session. A root holding the mirror's
`sync-app` as `app/` and `sync-storage` as `website/`, with `handoffs/control-board-build/` laid over it, and a `.env`
four folders above `app/lib/` (`DB_*` for a local `lv_main`, `CRAWLER_API_TOKEN=testtoken`, `VIDEO_WORKER_TOKEN=vtoken`,
**`APP_URL=http://127.0.0.1:8765`**: without it the engine's endpoint check calls the live site). The ledger's tables'
migration was deleted after it ran; the bench makes them from `ControlLedger::finish`'s columns. Each test's `Run:` line
says which schema it needs.

**Pitfalls learned:**
- Tests that `DROP TABLE control_alarms` make it again through `ControlAlarms::ensure()`, which carries the engine's
  columns; on live they come from Migration A, which runs first at Publish.
- The dispatcher tests stub `Database`: an engine file must never `require` `Database.php` itself.
- A `job:` alarm's tries are its next runs; an `ep:video/*` is checked through the video worker's heartbeat (a call to
  `video/jobs` hands out work).

## Taken over (2026-11-22 22:22 UTC, session_01CXLi5mF3yZ2ZE7oz8RpDsq)

- The build session at the hold above is replaced by session_01CXLi5mF3yZ2ZE7oz8RpDsq, which owns the build until it's live.
- **Claims moved:** the old session's 43 claims released (`checkout.py release --session session_01CXLi5ibQFnVmXNfkdFarwR`)
  and the same files claimed at once as this session's, release `control-board.zip` (36 change, 7 add), until 02:22 UTC.
  The views' files were already among them. Design and trips (session_01CXLi5grMsUgrRMJv3a71F9) holds only
  `website/js/location-scope.js`: no clash. `app/lib/Pulse.php` is claimed by function once its functions are known.
- **Mirror:** still `7c99b2c` (Sync 2026-11-22 21:29 UTC), cloned shallow and sparse at `/home/user/traverse-3way-sync`.
  No file changed since the build started, so nothing to re-apply.
- **Outside this zip (recorded, per the brief):** the crawler worker's 432 fix (`workers/crawler/crawler.py`
  `note_issue`: report any status >= 400 with its status and body) is a separate worker release after the board is live.

## Next, in the plan's order (§2.2) (superseded by the hand-off above)
1. Read C15 on staging (§6), and the Ledger tab's rows: read-only, wanted from a runner or Jason.
2. Remaining callers: Operations, ControlSensor, ControlMaintenance and ControlHistorian (they raise through the store,
   so through the engine already; their own words to move to state).
3. The dispatcher path (D19): a person's close updating Pulse and email; a trip to nobody; Site health on one path.
4. The views (D15–D17): the Arc gauges, the 16 development gauges, the four-zone cards, the Workflows page, the folded
   Reports, Copy by section; W14 "Not built" now that workflow lines raise nothing.
5. The outside heartbeat (D23, `.github/workflows/heartbeat.yml`, on this branch until the release merges).
6. The scenario test over the failure map, and the rendered checks at 1280 and 390.
7. FAQ, the release summary, zip check, send with `"review": true`.
