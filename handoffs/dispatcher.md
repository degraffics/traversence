# Hand-off: the dispatcher (decisions/0176)

The dispatcher session (session_01CXLi5hJ4C32FpJfNTaTa2z) builds decisions/0176, "the dispatcher presents and routes
every thread", accepted 2026-11-20. One session for all four releases (CLAUDE.md). This file is the record as it goes.

## The releases

| # | Release | State |
|---|---|---|
| 1 | **The thread and the shape, for alarms** (`0176-thread.zip`) | sent, built on Sync 14:33 UTC (faq.md rebuilt after #223–#225); reviewed by the session manager; approved by Jason in the pop-up |
| 2 | Service issues as threads (the card, Pulse's `svc:` case, Outside services' lines; no ×; words from the state) | after the Control Board session's feeds release (it holds `ServiceHealth.php`, `operations-console.php`) |
| 3 | Fix it (the cause → landing map, one side panel with Back, a push opening the Pulse row, the check on the spot) | not started; it reuses the Control Board session's one-panel drawer from 0175 release 2a (`at_hand`), never its own, matching its words (session manager, 2026-11-20) |
| 4 | Uploads (each target names its thread and landing spot; the staff thread for order files first) | not started; the order file check's limits wait for Jason |

## Release 1: what it does

- `app/lib/Thread.php` (new): the one shape, drawn per surface. `Thread::html($facts, $surface, $buttonsHtml, $endpoint,
  $affectsHtml)`, `Thread::push()`, `Thread::text($facts, 'pulse'|'logbook')`. Times in the words are `{t:ISO}`: a
  labelled `<time data-tv-time>` on a page, "Nov 20, 09:12 UTC" in plain text. Its CSS once per page, tokens only.
- `ControlAlarms::facts($pdo, $alarm)`: the alarm's six parts read from its row now; `affects()` from what it paused and
  `Preflight::NEEDS`; `pulseBody()` draws through Thread; `notifyOne()` and `mail()` put the status first in the words.
  The trip, its Escalation Notice and Recovered share the thing `alarm:<id>`.
- `Notify::IN_PLACE`: the alarm kinds are one thread. A send for a thing already in the person's Pulse updates that
  message (kind, level, words, link, `pulse_ref`), unread again only when louder; email and push as the level says;
  the push carries the level it had, so a rise renotifies on the same tag. A thread's push body is its status line.
- Pulse's alarm item (`Pulse::items`) reads `Thread::text(…, 'pulse')`.
- The board's alarm row (`tv_cb_alarms`) is drawn by Thread, its buttons and Holds link as before.
- The logbook's cleared line keeps the finished story.

Replaced and kept (playbook §3): the row's pill, title, Holds link, cleared line, What it means (now What the system is
doing), What to do, Stopped (now What it affects: "Paused by the system: …"), Reading good again and Not resolved (in
Status), the reading and the kind / count / first / last line (in Details ▾), acknowledged by (in Status); every button
(fixes, Check and start again greyed with its reason, Undo the Resolve, the evidence drawer, Acknowledge, Undo, Seen).
The row's id, class, `data-astate` and `data-alevel` stay for the filter and the 30-second redraw.

Changed on purpose: the Escalation Notice no longer makes a second Pulse row. The old code's comment said "its own row:
the second message, as Jason asked"; no decision records that, and 0176 A1 (approved) says one thread, never re-sent.

## Tests

`tools/dispatcher-tests/r1.php`: 43 checks on MariaDB (the shape per surface, a fault on the board only, the trip's one
message and push, the same fault again sending nothing, Pulse's words and body, Recovered closing the same message
quietly with its route, the logbook's story, a critical part's Escalation Notice raising the same message with one more
push on its tag, the board's row keeping its parts and buttons). The Control Board tests give the same results as on
the live mirror (r3a, r3b, r3c, r3d, r4c, r4e, r5a, critical, paused, legacy, itsdata, card-issue, task-on and the rest).
The board's alarms at 390px: no sideways scroll, a long nginx reading wraps inside Details.

Not done here: `ControlSensor.php` still says "Only Resolve clears it" on a critical part's row (0175's note made
that untrue; the file is the Control Board session's).
