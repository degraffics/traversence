# Pre-flight (decisions/0163 §10's note of 2026-11-16; queue item 2 in handoffs/session-manager.md)

Session: session_01CXLi5kGG8gG4QBmC4uzMzN, started 2026-11-17 on Jason's go ("yes start the preflight session now").
Check-out release names: `preflight` (release 1), `outside-table` (release 2, not built yet).

## State

- **Release 1, `preflight.zip`:** built on Sync: 2026-11-17 14:05 UTC, zip check clean, sent to `releases/inbox/`
  (PR open, merged once Jason approves). Files: new `app/lib/Preflight.php`, `app/admin/preflight-console.php`,
  `app/scripts/preflight_tick.php`, `website/js/admin-preflight.js`; changed `app/lib/Operations.php` (the Pulse case's
  three hooks), `app/lib/SystemStart.php` (panel: the pre-flight's result first), `app/lib/Scheduler.php`
  (`preflight_tick`, 15 min, in AUTO), `app/lib/Thresholds.php` (`pf_ms_radius`, `pf_ms_rollup`, `pf_ms_graph`,
  `pf_ms_index`), `app/admin/manifests.php` (config → `preflight`), `app/admin/sections.php` (requires the console;
  Outside services links to it), `app/ui/app-shell.php` (`tv_icon` 'x' and 'dash'), `website/admin/api.php`
  (`/admin/api/config/preflight`: run, fix, done), `website/api/crawl/index.php` (the check-in answers `"test": true`
  once when asked). No migration (results moved to `preflight_results` in preflight-slow).
- **The worker, in the same PR:** `workers/crawler/indexer.py` 1.1.0 sends the full-size test batch when its check-in
  says `test` (W0 stays asleep); tests in `workers/crawler/tests/test_indexer.py`. Merging redeploys `w0-indexer` on
  Railway (it watches `/workers/crawler/**`). Until it runs 1.1.0, the pre-flight's batch line says so (red).
- **Fix, `preflight-fix.zip`** (Jason, 2026-11-17: "keep lines compact"; "a green check mark or red x is enough"): one row per line, no notice after a test (only when a request fails, in the line's body). Built on Sync 14:22 UTC; js and the console only.
- **`preflight-slow.zip`** (Jason, 2026-11-17), built on Sync 14:28 UTC: results moved to their own table
  `preflight_results` (made on first use; staging's own), because live's `search_index_state.v` cut long values short
  (the header showed "78 not run yet"); slow checks (over `pf_slow_ms`, 5 s) in amber with "Show the slow ones"; each
  line's last 10 times; a full run's start, end and length; **Report (text)**: `GET /admin/api/config/preflight?action=report`.
  Lesson: `search_index_state.v` is short on live; keep anything longer than a few hundred characters out of it.
- **Release 2, Outside services as a table:** waits for release 1 to be live (both touch `app/admin/sections.php`), then
  start from the live `OutsideServices.php`, `app/ui/outside-services.php` and `sections.php` (outside-requests is live
  as #84 and #87, so its claims have ended). Plan below.

## How it works

- `Preflight::lines()`: one list in run order: the site, every `OutsideServices::COMPANIES` key (`svc:<key>`: each
  card's Test, one status), Railway and W0 (`rw:*`), the query timings (`ms:*`), the workflows in `SystemStart::ORDER`
  plus W14 (`wf:*`), the new tasks (`t:*`), then 0164's phases (`ph:*`, last, since they roll up the workflows).
  Each: [group, name, needed for launch, company, workflow]. Needed for launch: the site's lines, Railway and W0, and
  companies W0 or W1 use (plus Bluehost, Railway, GitHub).
- Each test returns [ok|bad|na|wait, the service's own answer, what was tested, fixes]. Fixes: `['do', act, label]`
  (the site fixes it: `sched` = Scheduler::sync, `dbup` = DbUpdates::autoRun), `['url', label, url]`, `['tool', label,
  admin url]`. Answers pass through `clean()` (no key=value or JSON secret, no 32+ character token).
- Calls: `http()` asks `StagingGate::hold()` first and skips a host held by an open ServiceHealth issue; a service only
  paused on Automation is still tested (read-only). Nothing charges: Stripe only with an `sk_test_` key, Square and
  Name.com only their sandboxes, Places and Tavily never called (their keys are on Railway: the crawler's key report).
- W0's batch: the line stores `w0:test_ask`; the next check-in hands it out once (`Preflight::takeTestAsk`); the worker
  posts kind `test` (never stored); the line reads the receipt in `crawl_batches` after the ask.
- `preflight_tick` (15 min): a full run when `.env`'s time or size changed, and nightly after 2 am Arizona.
- Pulse: `ops:preflight` through `Operations::pulseItems/Steps/Do`; a step per red line ("Test again", or its fix),
  outside fixes carry their URL, and a "Run it all" step.

## Release 2 plan: Outside services as a table (Jason, 2026-11-17)

The admin's table widget, one row per company: name, what we use it for, status ✓/✗/— (from `Preflight::result('svc:'
. key)`, the same status), this month's cost, last checked. Search across names, uses and key names
(`OutsideServices::searchText`); one filter dropdown (status: needs a look, in use, not in use; kind: storage, email,
payments, AI, sign-in, data sources, domains, workers, hosting); sort by status (default), name, cost; a row opens to
today's full card in place, nothing dropped; filter and search remembered. The pre-flight's ✗ on a company links to its
row (`/admin/config/services#svc-<key>`, kept as the row's id). Each card's Test button runs `svc:<key>` through
`/admin/api/config/preflight`.
