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

### 7. The views: how they're split, and their one contract (2026-11-22 22:30 UTC)

Built in parallel by helpers inside this session, each owning its files, all in `handoffs/control-board-build/` (a file
not yet there starts from its live copy at `7c99b2c`):
- **Gauges' data (G):** `app/lib/ControlGauges.php`; `tools/page-audit/gauges.py` writing `site-audit/results/gauges.json`
  (the 16 development gauges' draft configuration from `step3/sections/inventory.json`, the criteria from the check register,
  the 23 runtime gauges' setup and associations); `tools/control-board-tests/gauges.php`.
- **The board's views (B):** `app/admin/control-console.php`, `website/js/admin-control.js`, `website/js/tv-copy.js`,
  `app/lib/ControlReport.php`'s page: "Is it delivering?", Development, Incidents (IR-####), the folded Reports (D16),
  Copy by section (D15), the Open list keeping acknowledged trips (D5).
- **Workflows (W):** `app/admin/operations-console.php` (the Workflows page, `workflows-page.md` §5 and §7),
  `app/admin/automation-console.php` (W13 a list, W14 "Not built"), `app/admin/preflight-console.php`, D9's failed jobs.
- **Modules (M):** `app/admin/module-cards.php` (new): the four-zone card (03 §1–§4, §8–§9) for the Service register's
  modules and the hosts and endpoints as parts (D18), hooked into `operations-console.php`'s register.
- **The register's missing checks (C):** `tools/page-audit/tools-check.mjs`, `load-check.mjs`, `photos-shown.mjs`,
  `brand-check.mjs`, `audit-sample.py`, `.github/workflows/site-checks.yml` (`check-register.md`).
- **This session:** `app/ui/score-gauge.php` (done), `.github/workflows/heartbeat.yml`, the scenario test, the rendered
  checks, the FAQ, the summary and delivery.

**The contract** (PHP 8.0):
- `tv_score_gauge(?float $score, string $label, string $k, array $o = [])` and `tv_score_stage_pill($stage)`,
  `tv_score_gauge_css()` (`app/ui/score-gauge.php`). `$k`: a stage key `planning|phased|audit|impl|monitor`, or at
  Monitoring a band `good|below|poor|none`.
- `ControlGauges::runtime(PDO): array` — the 23 runtime gauges, each `id, box` (its promise), `title, measure, target`
  (null: "target: set by Jason"), `unit, reading` (?float), `reading_text, at` (?int), `live` (bool), `band`
  (`good|below|poor|none`), `stage, setup` (`probe, target, check, alarm` bools), `score` (0–100: setup % below
  Monitoring, the reading against its target at it), `paths` (`workflows`, `core`, `registered`: each a list of
  `[id, name, state, share|null, href]`), `fix` (list of `[label, href]`), `no_check` (check IDs missing).
- `ControlGauges::development(PDO): array` — the 16, each `id, title, stage, score, signed` (bool), `items, criteria,
  met, preview` (the draft's %), `no_check, paths`.
- `ControlGauges::workflows(PDO): array` — W0–W14, each `wf, name, kind` (`gauge|list|not_built`), `stage, score,
  setup, measure, target, reading_text, band`.
- `ControlGauges::source(PDO): array` — `ok, at, from` (where the configuration was read, its time).

### 8. Done by this session (2026-11-22, 22:25–22:40 UTC)
- **`app/ui/score-gauge.php`** (new): the Arc gauge, from `scorering.py`, on the site's tokens (no new tokens needed:
  taupe, `--tv-hold`, copper, river, sage's text shade, `--tv-fail`).
- **The outside heartbeat (D23):** `.github/workflows/heartbeat.yml` (every 20 min, on the branch only; scheduled
  workflows run only from `main`), `tools/heartbeat/heartbeat.py` and `test_heartbeat.py` (14 pass): page, databases and
  the watch (rows 53–55); a trip at once; one message while down; Recovered in the same thread; records in
  `site-audit/heartbeat/records.json`; "not set up (ALARM_TO)" until close.md A1's secrets (`ALARM_TO`,
  `HEARTBEAT_SES_KEY_ID`, `HEARTBEAT_SES_SECRET`). The site's side: `ControlIncidents::importHeartbeat` from the watch
  (`tick`), a closed record as a trip with its real times and its IR, source heartbeat, once, nothing sent
  (`tools/control-board-tests/heartbeat-import.php`, 7 pass).
- **Site health on one path** (check-register.md, Jason 2026-11-22): `Pulse::items` no longer adds the Site health case
  (claimed `app/lib/Pulse.php::items`); `SiteHealth::dispatch` (from the watch) sends each open finding once as a Pulse item
  with no push (`Notify` kind `sitehealth.finding`, in `NO_PUSH`), opening the audit's list on the board's Development tab
  (`#site-health`, helper B); a stale audit check, a line of the old case, is now a board warning `audit:stale` with its Run
  links. The case's actions (`sh_mark`, `sh_refresh`, `sh_run`) stay in `Pulse::manage` for the list's buttons.
- **The test runner:** `tools/control-board-tests/run-all.sh` (each test on a fresh `lv_main` with its Run line's schema;
  the deleted migrations' tables recreated). On the build: **1,151 pass, 3 known failures** (the bench's `zz_test`
  endpoint; two `register.php` checks against live files outside this build: `<dt id=…>Its key` and the decisions index
  quoting `/admin/config/services`). The 12 `.cjs` rendered tests run separately.

### 9. Helpers' results (open questions for the summary)
- **G, gauges' data:** done, 77 checks. `gauges.json` (34 KB, what the site caches) and `gauges-items.json` (each drawer's
  detail, `ControlGauges::detail`). Questions: the site can't record Jason's sign-off yet, so every development gauge stays
  in Planning until B10–B19 (gauge-lifecycle §5 is a proposal); "Trips reach a person" reads 24 hours; the dispatcher's
  link is the Communications page.
- **C, the register's checks:** done on fixtures; results in `site-audit/results/{load,photos-shown,brand,tools,audit}.json`.
  Hourly light run (`7 * * * *`) and the daily full run, as the register's approved schedule. Questions: how BRAND reads
  three standards (one header, filters as one dropdown, card anatomy); the format of `site-audit/tools.md`; the light
  probe's key pages (default `/`, `/discovery/`, `/directory/`, `/login.php`); `findings.py` doesn't read the new files yet.
  `site-checks.yml` starts live runs: it reaches `main` only with the release.

### 10. More helpers' results (22:50 UTC)
- **M, module cards:** done. `app/admin/module-cards.php` (74 + 25 rendered checks), hooked into the register; Check now
  for workers and endpoints through `ControlEngine::check` (`website/admin/api.php`, claimed); no doubled lists under the
  card (`app/ui/outside-services.php`, claimed); `incident_radius_rules` in the manifest; `task-on.php` and
  `register.php` read the card. Gaps left: Fly's slot "Documented · release #97"; a slot's why needs `setup_capture`.
- **W, Workflows page:** done (56 + 35 rendered checks). Left out as questions: half budget for 14 days (R153, no data
  source); failed identity jobs.
- **S, the scenario test:** done. `tools/control-board-tests/scenario/` (run.php, suites, `cells.md`): 173 of 173 cells
  covered; of 135 designed scenarios 66 pass, **39 fail on the engine**, 30 can't run on the bench (outside reads, raisers
  not built). 55 cells "views: pending". The FAILs are being fixed by helper E (engine files), the list in the session's
  scratch `s-report.md`, summarised in cells.md.
- **Suite:** 1,419 pass; known failures r1 (`zz_test`), register.php ×2; r3d (a literal amber in B's new CSS, being fixed).
- Claims renewed at 22:51 UTC (until 02:51).

## Hand-off at a hold (2026-11-22 23:25 UTC, session_01CXLi5mF3yZ2ZE7oz8RpDsq)

This session stops at a hold, its context long (CLAUDE.md, 2026-11-20). **The whole build is on the branch
`claude/control-board-build`, not in `main`; nothing is zipped, sent or live.** The next session takes it from here after
the session manager's review; this section is its brief, with the plan (`handoffs/control-board-build.md`).

**Built on:** the mirror at **Sync 2026-11-22 23:05 UTC (`8d3afbd`)**. Since 7c99b2c only `website/js/location-scope.js`
changed (Design and trips), not one of this build's files. Fetch again before zipping.

**Claims:** this session's, release `control-board.zip`, renewed 22:51 UTC (until 02:51 UTC): the 43 files of §3, plus
`app/lib/Pulse.php::items`, `website/admin/api.php`, `app/ui/outside-services.php`, `app/lib/Gemini.php::refused`,
`app/lib/ControlMaintenance.php`, `app/lib/ServiceStages.php::retire`. The next session releases them
(`checkout.py release --session session_01CXLi5mF3yZ2ZE7oz8RpDsq`) and claims the same at once. New files to claim as
add: `app/admin/module-cards.php`, `app/lib/ControlLedger.php` is a change (already claimed), `app/lib/SiteHealth.php`
(claimed). `app/ui/faq.md` is never claimed.

**Done (each in §5–§10 above):** the engine and its callers (D1–D4, D6–D8, D10–D14, D19–D22), **37 + 2 more scenario
fixes: the scenario test passes in full** (`scenario/run.php`: 173 of 173 cells, 135 designed scenarios: 0 fail, the
rest pass or can't run on the bench, listed in `scenario/cells.md`); the views: Is it delivering?, Development with Site
health's list, Incidents with Write the root cause (`ircause`), the folded report (D16), Copy by section (D15), D5, the
Workflows page (§7: 8 kept, 2 moved, 3 dropped; W13 a list, W14 Not built; D8, D9), the four-zone module cards (D18), the
Arc gauge, the gauges' data (`ControlGauges`, `gauges.py`, `gauges.json` + `gauges-items.json`), Site health on one path,
the outside heartbeat (D23) and its import, the register's five checks with `site-checks.yml`.
**Suite:** `run-all.sh` 1,549 pass, 3 known failures (r1 `zz_test`; register.php ×2 against live files outside the build).

**Left, in order:**
1. **Rendered sweep at 1280 and 390** over every board view and page: all `.cjs` tests (board-views, module-cards,
   workflows-page, board-rendered, board-tabs, board-phone, one-panel, one-place, lines, board-menu, peek-anchor, and
   dispatcher r2–r4) on one bench run; **press Write the root cause once** (not yet pressed in a rendered test); the
   board-rendered seed needs a founder (else the dispatcher's own "no one to tell" trip gives "Tripped 2", P11); the
   Arc bands within 1% (ring-check model); `layout-probe.mjs` on each page.
2. **The 55 "views: pending" cells** in `scenario/cells.md`: add their board/card/Pulse words now the views are in.
3. **FAQ** (plan §7): new entries (the gauges; Paused by the system; Copy by section; the Workflows page; Recovered;
   the heartbeat's email) and **rewrite the entries the build makes untrue**: `faq.md` "Where are the crawler's workflows
   and the Workflow board?", "What is the System Control Board?" (its tabs: Is it delivering?, Development, Incidents
   first), "Why does an alarm read the same…" (Recovered, Fixed by), any Site health entry (no longer a Pulse case),
   W13/W14 mentions. `grep -rn "faq.php#"` before rewording a question. On the newest live copy, just before zipping.
4. **The release summary:** every behaviour with its source (each helper's report is summarised in §9–§10 and the
   commits); one "Admin's test:" line per new state; kept/moved/dropped (03 §9, workflows-page §7, M's list, B's list);
   the **questions for Jason** gathered as one (playbook principle 7): the gauges' sign-off has no recorder yet (all 16
   stay in Planning; B10–B19); BRAND's three standards a browser can't read; `site-audit/tools.md`'s format; the light
   probe's key pages; half budget for 14 days (R153, no data source); row 124's contradiction (one email vs. a reply;
   built: nothing sent for a joining trip); a server's own check runs its module's whole pre-flight line (a per-server
   probe needs a manifest `probe`); the dispatcher's card links to Communications.
5. **The zip:** only files differing from the live mirror byte for byte, from `handoffs/control-board-build/` (check
   none is identical to live), with `release.json`: title, why, proposals, `"switch": ""`, **`"review": true`**,
   **`"data_reason"`** (Migration `2026-12-01_npi_switch.sql` has one guarded `UPDATE`). zipcheck, then
   `send.py <zip> --mirror <checkout> --note "… built on Sync 23:05 UTC"`, `deliveries.py record`.
6. **Repo files reach `main` only with the release's merge, after Jason's Approve:** `.github/workflows/heartbeat.yml`
   (starts email), `.github/workflows/site-checks.yml` (starts the new live probes, hourly), `workers/video/railway.json`
   (can redeploy). Everything else on the branch may go with the same PR.
7. **Staging run:** the read-only look at the Ledger tab on staging
   (https://staging.trvgo.us/admin/config/control?tab=ledger, and live's
   https://traversence.com/admin/config/control?tab=ledger only on Jason's call), and C15 (`irs_loads`), read-only.
8. **The session manager's review** (session_01CXLi5mGX2sg5QS11gg3Wrh) of the summary with its full proof; Jason's
   pop-up only after "clear to ask"; merge after his Approve; a Progress line in `handoffs/session-manager.md`.

**Outside this zip:** the crawler worker's 432 fix (`workers/crawler/crawler.py` `note_issue`), a worker release after
the board is live.

**The bench:** `bench.sh` in the session's scratch (recreate it: a root holding the mirror's `sync-app` as `app/` and
`sync-storage` as `website/`, the overlay over it, four folders deep, `.env` with `DB_DATABASE=lv_main`, `DB_USERNAME`,
`DB_PASSWORD`, `CRAWLER_API_TOKEN=testtoken`, `VIDEO_WORKER_TOKEN=vtoken`, `APP_URL=http://127.0.0.1:8765`,
**`GAUGES_URL=<repo>/site-audit/results/gauges.json`** so no test reaches GitHub). `run-all.sh <root>` writes the
`.env` itself. MariaDB: `apt-get install -y mariadb-server php8.3-mysql`, a user with all grants. Helpers used their own
databases (lv_b, lv_e…) so runs don't collide.

**Pitfalls learned this round:**
- `page_cache.body`'s live type is unknown: anything cached stays under 60 KB (`ControlGauges` keeps the compact file;
  bigger goes to `app/storage/`).
- A bench with no founder raises the dispatcher's own trip on every trip (P11): seeds that count trips must add one.
- Two helpers on one file: only with targeted edits; the register (M) and the Workflows page (W) shared
  `operations-console.php` this way without loss.

## Taken over (2026-11-22 23:24 UTC, session_01CXLi5iMkDf1xiJ4V9rScRe)

**The session manager's rulings (2026-11-22, recorded first):**
1. **Rows of the state model (`step3/02-state-model.md`) are approved sources:** Jason approved step 3 on 2026-11-22. The
   summary cites them by row number.
2. **Row 124:** a trip that joins an incident updates the incident's Pulse item and sends a reply in the incident's
   existing email thread; no new subject and no push (0176 §1: one thread per alarm, updated in place). This replaces
   the earlier build (a timeline note only, nothing sent) and closes the hand-off's "row 124's contradiction" question.
3. **The dispatcher card's link is confirmed from `app/admin/manifests.php`, not guessed.**

**Done:**
- **Claims:** the old session's 48 released; the same 48 plus `app/admin/module-cards.php` (add) claimed by this session
  (23:25 UTC, until 03:25 UTC, release `control-board.zip`).
- **Mirror:** Sync 2026-11-22 23:05 UTC (`8d3afbd`), the same the build is on: nothing to re-apply.
- **Branch:** `origin/main` merged into `claude/control-board-build` (no rebase, no force-push).
- **Row 124 built** (ruling 2): `ControlAlarms::mail` sends a joining trip on the first member's thread
  (`thing` `alarm:<first>`, its subject `SYSTEM ALARM NOTICE: <A>: Paused by the system`), its words first
  "IR-#### · Correlated · 2 parts: <A>, <B> · Paused by the system", then "<B> joined this incident (same host and kind
  within 15 minutes)" and B's notice; `Notify::send` takes `'no_push' => true` (Pulse and email only; the push route
  reads "A reply on a thread already pushed: no second push"). The email is a reply: `Notify::deliverEmail` already
  threads an in-place alarm's later emails (In-Reply-To the first). **Test:** `scenario/suites/correlation.php` S-F124,
  7 checks (one Pulse row updated in place, `times` 2, A's thing and subject; two emails, the same subject, the second's
  parent the first's id; one push; the skipped push route). The scenario runner's stand-in `Mailer` records
  `Mailer::$thread` now.
- **Ruling 3, the dispatcher card's link:** `manifests.php` `config` → `communications` ("Communications": senders,
  mailboxes, routing, domain health, message control; `tv_admin_mod_comms`, founder-only) is the dispatcher's settings,
  so the card's `/admin/config/communications` (`ControlGauges` parts.core.dispatcher; runtime o-signin, o-email,
  o-told) **is right and kept**. One fix was wrong: the dispatcher's own "reached no one" trip (P11) said "Name a founder
  in Communications" and opened `#cs-controls`, but founders are read from staff logins' level
  (`CommsSetup::founders` → `Workspaces::levelOf`), set at `manifests.php` `backoffice` → `levels` ("Staff levels",
  `/admin/staff-levels.php`). It now says "Give a staff login the Founder level in Staff levels" and opens
  `/admin/backoffice/levels`.

### The hand-off's Left list, done (2026-11-22 23:30 – 2026-11-23 00:00 UTC)
1. **Rendered sweep:** `tools/control-board-tests/rendered-all.sh` runs all 14 `.cjs` tests on one bench, each with its
   seed (`board-rendered-seed.php` new: a founder and W1's trip, so no "Tripped 2"): **310 pass, 0 fail**. Write the root
   cause pressed at 1280 and 390 (board-views.cjs). It found two defects: the IR page didn't load its script; the line
   stayed Unconfirmed until a reload. Both fixed.
2. **The 55 view cells:** `views()` in `scenario/run.php` draws every alarm a scenario leaves on the board's row, its
   Pulse item and its module card, and checks the words. 159 of 173 cells drawn; `cells.md` regenerated from the run
   (results and the views column; no "views: pending" left). It found: raw ids for jobs and Start's watch, the card's raw
   time token, the card's issue in words other than its alarm's. All fixed; 0176 has a note for the last.
3. **FAQ:** `handoffs/control-board-build-faq.py` (re-appliable), applied on Sync 23:26 UTC: 3 entries corrected, 7 added.
4. **Summary:** `handoffs/control-board-build-summary.md`.
5. **Zip:** zipcheck clean (`--drops OFF`, the approved D12), `send.py` → `releases/inbox/control-board.zip`, 43 files,
   recorded pending. Claims added: `app/lib/staging-tables.php` (top), `app/lib/Pulse.php::'square'`.
6. **Suite:** 1,549 PHP pass (3 known); scenario 135/135, 173/173 cells.
Left: the staging read-only look (Ledger tab, C15) once it lands; the session manager's review; Jason's pop-up; merge.

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

### After #258 live (2026-11-23)
- **#1275 (the health report's consistency, ledger and irs_loads) merged**, approved by Jason; reviewed by the session manager.
- **control-board-fix.zip (#1277), approved by Jason:** the false /api/0 trip (site_blocked is a count), closed as raised
  in error; plain endpoint names; money always in dollars. `handoffs/control-board-fix.md`.
- **Pre-existing, recorded (the session manager, 2026-11-23):** `lines.php` fails one check ("…and the pre-flight's
  result, with Run it again, is its own block for the Start-up stage") on the live code without the fix too.
- **Next in the queue:** (a) Insights → Crawler's task table clipped on the left in the case's side panel at phone width
  (Jason's screenshot); (b) W0's Railway cost from Railway's own usage, not the per-call estimate.

## 2026-11-23: the opening view (board-view)

- **Pre-existing, found by the sweep's new stop check:** `dispatcher/r3` (r3.cjs) stops at
  `#pb-dr-play li button … 'Open'`, Pulse's playbook drawer, on the live code as well as the build. The old rendered-all
  counted it as 16 passed. It isn't this build's code. It's recorded for the dispatcher's owner, via the session manager.
- **gauges.py --check** broke on main's `rendered.json` (rows of timed-out staff sign-ins with no `status`). Fixed in
  board.py in this release; regenerating the gauges waits on the session manager (it moves System Configuration's preview 48 → 49).
- **rendered-all.sh** reports a test that stops part way as a failure. Before, board-views read 16/0 after stopping at 16
  of 90.
