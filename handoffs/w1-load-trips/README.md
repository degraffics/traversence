# W1 load trips: #10 and #11 in one release (launch plan B.6, B.7)

Built 2026-11-27 by the Piece 2/3 build session (session_01CXLi5inRHU7bxiyPoYoi4v), on **Sync 01:35 UTC (7a839cb)**.
Order (the session manager, 2026-11-27): G.1, the Scheduler bundle, then this; D.1's town loads (Data & Content) wait on it.

## What it fixes, each with its source

- **#11, the receipt counts** ("/api/crawl/loads: received 100, accounted for 80"; Work queue ticket, `ControlLedger::counts`).
  Every record a load batch sees is skipped, closed, folded into its twin in the batch (`ListingIdentity::fold`), already known
  to the intake (`IntakeStager::stage`'s `already_known`) or staged. The reply left out folds and already-known, and the ledger
  read `closed` as nothing. Now `OpenLoads::batch` also answers `duplicates` and `already_known` (keys the ledger already reads
  as refused), and `ControlLedger::COUNTS` reads `closed` as updated. The open warning clears itself on the next good call
  (`ControlLedger::alarms`' own clear). `/api/crawl/places`' `closed` reads as updated too (the same family of warning).
- **#10, retry a 503 batch** (W1 run #2148, 2026-11-24: foursquare_bulk failed on one 503 after an hour). Worker 2.17.1
  (`workers/crawler/crawler.py`, `load_batch`): a 502, 503 or 504 waits 30, 90 and 180 s and resends the same numbered batch,
  the same waits as Geofabrik's batches (2.13.0, Jason 2026-11-19). After the third, it fails as before (0171 §5: a trip
  when the back-off reaches its limit). A wait past the task's time stops the town for this run: it isn't marked done, so
  the site hands it out again, and its taken batches answer what they did. The task reads idle, "the site was busy (503); its
  town goes on next run", never failed. Railway deploys the worker from `main` on merge.

## Files (the zip carries only these; `build/`)

- `app/lib/crawler/OpenLoads.php` (`batch`: -3 +7), `app/lib/ControlLedger.php` (`COUNTS`: -1 +1), `app/ui/faq.md` (+2, the
  ledger's entry: loads add up and a busy site is waited out). Claims: OpenLoads.php top and `batch`, ControlLedger.php top
  and `counts`.
- Not in the zip (on `main`): the worker 2.17.1 and its test; `tools/control-board-tests/load-counts.php`.

## Tests

- `php tools/control-board-tests/load-counts.php <app> handoffs/loads-reply/build/app/migrations/2026-11-24_open_load_batches.sql`:
  18/18 on the build; 10 of 18 fail on the live code (the defect reproduced). The real `OpenLoads::batch` and
  `ControlLedger::counts` run on MariaDB; the identity check and intake are stand-ins with known answers.
- `load-batches.php` 10/10 on the build. `w0-receipt.php`: the same 9/11 on the build and on the live code (the bench has no
  `index.php`).
- `python3 workers/crawler/tests/test_load_batches.py` 17/17; test_site_call, test_held_task, test_fallbacks, test_osm_places pass.

## Live state read (2026-11-27 02:09 UTC, live-read rows control_alarms q `_bulk`, log deleted)

Alarms #154 "overture_bulk failed in its own code (run #1290)" and #153 "foursquare_bulk failed in its own code (run #2148)"
are on record. The data search can't show whether they're open (status columns). Under the one engine a task's own-code trip
pauses only its task (`ControlEngine::radius`). After this is Live, each trip's **Check and start again** at
https://traversence.com/admin/config/control/wf-W1 starts its task again. That's an admin's press: live-site actions are
only on Jason's call. Each then closes as Fixed when its run works.

## To send

1. Fetch the mirror. If it has moved past 7a839cb, compare the three files with the newest live copies and re-apply.
2. Run `send.py`, then open the PR (it carries the worker too), merge it, and watch for Live (the hand-off's §6 watcher).
3. Tell the session manager and Data & Content when it's Live.
