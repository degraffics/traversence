# Old alarm titles redrawn (2026-11-23)

The Control Board build session's release after #1282 (spend-units). Built on Sync 04:13 UTC. The session manager put it ahead of (b): "redrawing
alarms raised before #258 from their state is your next item, with a test over the live snapshot."

## Why

An alarm keeps the title it was raised with. A held trip's raiser doesn't fire again, so alarms open since before #258
still read in the old words (live snapshot, `site-audit/results/alarms.json`):

| Alarm | Old title | Today's |
|---|---|---|
| `svc:issue:41` | index.commoncrawl.org: Its server failed (the crawler, on Railway) | Common Crawl's index isn't answering |
| `jobslow:guide_finds_tick` | The job "guide_finds_tick" is slow | (the job's plain name) is slow |
| `job:identity_recheck_tick:stuck` | The job "identity_recheck_tick" never finished | (plain name): its run never finished |
| `task:failed:overture_bulk`, `…foursquare_bulk` | W1: its task overture_bulk failed (run #1290) | /api/crawl/loads is slow |

## What changed (2 files, `handoffs/old-titles/`)

- `app/lib/ServiceHealth.php`: `alarmTitle()` (new, public): the title formula `alarm()` used, moved out so the redraw
  uses the same words. `alarm()` calls it; nothing else changes.
- `app/lib/ControlAlarms.php`:
  - the slow-job raiser words its title with `jobName()`;
  - `redrawTitles()` (new): for each open alarm, today's title from its state (a service's issue from its
    `service_issues` row, a job by `jobName()`, a task by its component as `CrawlService::report` words it). It updates
    the alarm and its Pulse thread's title (`noticeTitle`) in place. It sends nothing and leaves read rows read. An alarm
    already in today's words is left alone;
  - `tick()` calls it once per watch, before the slow-lift block, and logs "N alarm title(s) redrawn from their state".

Claims: `ControlAlarms::tick` and `::pulseSteps` (change; the slow-job raiser sits in pulseSteps), `ControlAlarms::redrawTitles` (add), `ServiceHealth::alarm` (change),
`ServiceHealth::alarmTitle` (add). No migration, no switch, no outside call.

## Proof

`tools/control-board-tests/old-titles.php` seeds the live snapshot's open alarms with their old titles, issue 41's row and
its read Pulse thread: **12 passed, 0 failed**:
- each kind redrawn as its raiser words it today;
- the rest unchanged;
- the Pulse title equals `noticeTitle` and is still read;
- nothing sent;
- a second pass changes nothing;
- `ControlConsistency::check` finds no raw name or title mismatch.

The full PHP suite (`run-all.sh`) on the mirror with the change: 1534 passed, 7 failed, the same 7 that fail on the live code without it (engine migrations A–B, lines, r1, r4b, register ×2). The first run caught a defect of this build: `alarm()` lost `$hp`, so Common Crawl's index and data host became one part (S-A5, S-A6); fixed before sending.

## After it's live

Within the hour the board's watch redraws the open alarms. The next health report's `consistency` section should show no
`names` or `title` mismatches for them (step 5's week of watching).
