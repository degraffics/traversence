# Case #51: findings and a held draft (not a release)

Built 2026-11-21 by the Operations session, then stopped by the plan (`handoffs/control-board-plan.md`: "No one-off board
patches. Case #51's causes go into the map, step 2"). Nothing here was claimed, zipped, sent or merged as site code. It's
kept as evidence for step 2's audit and as a starting point for step 4, never to be applied on its own.

## What happened

W1's `overture_bulk` timed out once (`TimeoutError`, sending rows to `/api/crawl/loads.php`), and the trip paused all of
W1, every other task with it.

## The three causes, against the code built on Sync 12:26 UTC

| # | What the code does | What the decisions say | Draft fix here |
|---|---|---|---|
| 1 | `ControlAlarms::stop()` pauses the whole workflow for `task:failed:<t>` (its fallback: `$pause('wf', …)`), though the alarm's own words say "the workflow's other tasks carry on" | 0175 §3: "a trip stops only what it holds" | `site.patch`: a `task:failed:<t>` trip pauses only `task:<t>` |
| 2 | Our own site timing out or giving a 5xx to the worker is reported as the task failing: a crash, counted toward a trip | 0171's note (Jason, 2026-11-19): slow is never a trip | crawler 2.16.2 `SiteSlow`; `CrawlService::report` raises `task:slow:<t>`, a warning that clears when the task does its work |
| 3 | `open_load` sends a load's rows in one large request, with no second try | (no decision: an operating fix) | 250 rows a batch; a batch that times out is sent once more after 30 s |

## A conflict found: tries by runs, against "Keep the hour"

The brief asked for a task's tries to count its own runs, not the clock. That reverses **0171's note of 2026-11-20**: a
nightly task "keeps the hour". Jason chose "Keep the hour" in the decision pop-up: after `control_fix_minutes` (60) with no
fix, it's a trip. So the draft keeps the hour. Whether to change it is a question for step 1, never a guess.

## Tests (with the draft applied, on MariaDB)

- New: `tools/control-board-tests/task-failures.php` (12) and `workers/crawler/tests/test_site_slow.py`.
- Updated to the task-only pause: `expire-tries.php` (9), `r3c.php`, `paused.php` (its workflow example is now a
  workflow-level failure) and `dispatcher-tests/r1.php` (43).
- Unchanged and passing: holds, routes, lines, paused-now, card-issue, critical, r4f, backoff-gate, legacy, r5a-rate,
  quiet-job, feeds-words, task-on, and dispatcher r2, r2-notice, r3 and r4.
- These already fail on the base tree as well, with or without the draft:
  - `r3a`, `r4e` and the end of `r3c` need the `control_ledger` migration, which ran on live and was deleted there;
  - `paused.php`'s "database update runs twice" needs `2026-11-20_system_pauses.sql`, also run and deleted.
  - A scenario test (step 4) needs these tables in a test schema.

## Files

- `site.patch`: `app/lib/ControlAlarms.php` and `app/lib/crawler/CrawlService.php`, against the live mirror (Sync 12:26 UTC).
- `crawler-and-tests.patch`: crawler 2.16.2 and the tests above, against this repo's `main` (d6b4427e).
