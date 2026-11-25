# no-undo: no Undo after an alarm's acknowledgement (2026-11-25)

The Control Board build session; Work queue task #2 ("No Undo on alarm acknowledgements", Jason's scope doc: done when no Undo
on any alarm; rule #1314). CLAUDE.md "Edit or Undo" (Jason, 2026-11-23): a record someone entered is edited, never undone; Undo
only for an action that did something. Acknowledge records who saw a trip, so its Undo goes. Built on Sync 13:50 UTC, 2026-11-24.

| Where | Before | Now |
|---|---|---|
| The board's alarm row (`tv_cb_alarms`) | Undo after Acknowledge (except W1's whole-workflow trips); a red "Seen: by X" box | no Undo; the state line "Seen by X, <time>" (info), the trip open while it holds |
| A module's card (`tv_mc_radius`) | "Undo Acknowledge" | no button; "1 trip (seen by X)" as before |
| Pulse (`Pulse::mark`, Reopen) | Reopen on an alarm's case also un-acknowledged it | Reopen reopens the case; the acknowledgement stays |

Kept: `ControlAlarms::unack()` (r3a tests it) and the API's `unack` action, now with no button calling them (a later tidy, once
nothing else does). "Undo the Resolve" stays: Resolve is an action that lifted a trip (CLAUDE.md: Undo for actions).
Tests: `tools/control-board-tests/no-undo.php` (7; 4 fail on the live code); `board-views.php` D5 and `module-cards.php` updated;
run-all: nothing new (gauges.php 11a/11b and old-titles.php fail on the live code too, not this build).
