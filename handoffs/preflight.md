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
- **`preflight-tune.zip`** (2026-11-17, from Jason's first report: 58 ready, 24 not), built on Sync 14:36 UTC: Railway
  test reads the project (a team token can't ask `me`), OpenStreetMap asks each free server (6 s each), Fly sends a
  macaroon as FlyV1, Supabase's empty index is expected before switch-on, jobs shipped off get their line from Start the
  system, openrouteservice's 5xx is theirs. Real items found (for Jason): HF_TOKEN missing on Railway's `traversence`
  service; azliquor.gov's Cloudflare challenge; GITHUB_SYNC_TOKEN expires Dec 16, 2026; the switch-on check holds W1, W2,
  W3, W11, W12 for BRAVE_API_KEY though Tavily is the search (Operations::switchOnCheck; Jason's call); Domains::fly sends
  Bearer, so a macaroon FLY_API_TOKEN fails there too (domains session); 36 ungated outside calls (StagingGate::scan).
- **Release 2, `outside-table.zip`** (built on Sync 14:43 UTC, sent 2026-11-17): Outside services as a table: row per
  company (name, use, ✓/✗/— from its pre-flight line, this month's cost, last checked), search (names, uses, key names),
  one filter (status or kind, `OutsideServices::KINDS`/`KIND_NAMES`), sort (status default, name, cost), the card opens
  in place (`js/admin-outside.js`, kept in localStorage `os:table`), Test on each card (pre-flight `svc:<key>`); the
  pre-flight's Brave line checks BRAVE_API_KEY (Jason: Brave and Tavily both live and ready).
