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

## Next, in the plan's order (§2.2)
1. Read C15 on staging (§6). Read-only.
2. The manifest (`service-defs.php`) and its generated lists.
3. The engine (`ControlEngine`, `ControlIncidents`), Migrations A and B, its unit suite.
4. The callers onto the engine, one at a time, the baseline rerun after each.
5. The dispatcher path. 6. The decided fixes. 7. The views. 8. The scenario test and the rendered checks.
9. FAQ, the release summary, zip check, send with `"review": true`.
