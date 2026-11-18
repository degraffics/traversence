# Control Board: built, not yet sent

Release 3a (decisions/0171 §5, §7) in progress, built on Sync 2026-11-18 17:25 UTC: `r3a/` holds the files at their
real paths (`app/`, `website/`). Not sent yet: it waits for the comms session's `comms-all` release, which holds
`app/lib/Scheduler.php` (the `control_tick` job) and `app/lib/staging-tables.php` (the `control_alarms` class), and
defines `Notify::KINDS` `alarm.trip` / `alarm.warning`. When that's live: start each changed file again from the live
copy, re-apply this release's changes, add the job and the table class, test (`tools/control-board-tests/r3a.php`), send.
Removed from here once sent.
