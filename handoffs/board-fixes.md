# Jobs and words (release 2, 2026-11-23)

The Control Board build session's release after the opening view (board-view-3, #268). It covers:
- items 1 and 4 from Jason's screenshot of the live board;
- old-titles (#1286, closed and folded in);
- Google's sign-in account;
- the opening view's Sync chip.

Built on Sync 14:53 UTC (8283c24). Build: `handoffs/board-fixes/`. The earlier branch `claude/board-fixes` held it in
progress.

## Why (item 1: jobs late at once)

The live alarms at 10:20 UTC showed 13 jobs late. Most of them last started at 03:00 Arizona, and Lead scores (`sales_tick`)
hadn't started since Nov 22 01:17 UTC. towns.md showed the cron did run at 03:15 (`listings_tick`, `guide_finds_tick`), yet
the 5-minute jobs did not start.

What was found:
- **Fix 1, the likely shared cause:** `Scheduler::run` → `sync()` ran five upkeep steps before its once-an-hour guard, on
  every job's start. That's about 40 jobs, ai_worker every minute, and `ListingConfidence::backfill(300)` re-scoring up to
  300 listings each time. Starts pile up on the server's processes and database connections.
- No script takes a lock before `Scheduler::run`. A start the database refuses writes no row, and the job runs unrecorded.
- **The lateness check (#258, row 65) had four defects:**
  - it read each job's default schedule, not its cron line;
  - it looked only at 2 days of runs, so a job that stopped more than 2 days ago dropped out silently;
  - it didn't exclude QUIET and RETIRED jobs, or one taken out on purpose;
  - its times were Arizona time, unlabelled.

## What changed (11 files)

- **`app/lib/Scheduler.php`:**
  - `sync()`: its upkeep is behind the hourly guard, batch sizes unchanged;
  - `run()`: a refused start is kept (`missed()`, `app/storage/scheduler-missed.json`, the last 200; `misses()`).
- **`app/lib/ControlAlarms.php`:**
  - the lateness check: its own cron line when cPanel's API reads it (one read, only when a job looks late), and words
    that say which it is (line missing, or the server didn't start it); 14 days of runs; QUIET, RETIRED and removed jobs
    left out; readings in UTC, labelled;
  - a hung run's reading is in UTC too;
  - "Its job:" buttons name the job plainly, their panel titled by it;
  - **`redrawTitles()`** (old-titles): each open alarm's title is redrawn from its state, with its Pulse title updated in
    place and nothing sent; it's called from `tick()`;
  - the slow-job title uses `jobName()`.
- **`app/lib/ServiceHealth.php`:** `alarmTitle()`, the raiser's own formula, which the redraw shares.
- **`app/lib/Operations.php`:** the cap alarm's title carries its reading ("Google Cloud at 61%: 608 of 1,000 checks").
- **`website/api/health.php`:** its jobs section gains:
  - each job's `last_utc`, `default`, `line` (as cPanel holds it; `missing`; null without the API) and `line_preset`;
  - `jobs_missed` (refused starts, last 24 h, and the recent ones);
  - `db_connections` (max_connections, max_user_connections, Threads_connected, Max_used_connections,
    Connection_errors_max_connections).
- **`tools/page-audit/towns.mjs`, `.github/workflows/search-probe.yml`:** the daily fetch saves it as
  `site-audit/results/jobs.json` (job names, times and counts only).
- **`app/lib/SignIns.php`** (new), **`app/admin/module-cards.php`**, **`app/admin/operations-console.php`:**
  - the card's top line: "Signs in as: <email> · project <id>", or "not recorded · Record it";
  - Setup capture: a one-field Record (with a project id where the provider's pages take one), and Undo for a day on the top
    line;
  - Google's Console, Credentials and Billing links carry `authuser` (and `project`, except on Billing) and say "opens as";
  - another provider's links name the account beside them;
  - the handler: `svc_signin`, `svc_signin_undo` (the register's permission).
- **`app/admin/control-console.php`:** the Sync chip. `ops:watch:*` alarms (Railway and the sync) get their own chip and
  are never also under Warnings.
- **`app/ui/faq.md`:** the Sync chip, and "Which account does each outside service sign in as?".

No migration, no switch. The one outside call is cPanel's API, through `CPanel::api2`'s own gate.

## Proof

- `old-titles.php` 12/12. It now seeds its own old-worded alarms; the live snapshot has since been rewritten.
- `board-words.php` 17/17:
  - Google's title through the real Services count, its register entry's words, the band climbing, clearing;
  - the lateness check on seeded runs, the Sync chip, and no raw names.
- `google-signin.cjs` 28/28 at 1280 and 390, on the real register's handler: Record, a refused email, the links,
  Undo, Tavily naming the account, no sideways scroll.
- The full sweeps: `rendered-all` 450 passed; the one failure is dispatcher/r3's pre-existing stop. `run-all` 1551 passed; its 7 failures are the same 7 that fail on the live code (engine migrations A–B, lines, r1's stray endpoint, r4b, register ×2).

## After it's live

- The next daily health fetch writes `site-audit/results/jobs.json`. Read Lead scores' line there: if it's `missing`, Set
  them up now on Scheduled tasks adds it.
- `jobs_missed` and `db_connections` say whether refused connections caused the gap.
- Report to the session manager, not Jason.
