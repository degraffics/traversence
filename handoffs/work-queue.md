# work-queue: the Work queue on Development (2026-11-24)

The Control Board build session (`session_01CXLi5gqPa5AQKoZXC7gZxx`), Jason's priority of 2026-11-24 (via the session manager):
"the work queue: Make a gauge for this and have its queue from the development board. We need to be able to add tasks to the
queue and manage them so we don't fall out of score or forget what we identified as needing to be done."
Zip: `work-queue.zip` (build in `work-queue/build/`, screens in `work-queue/`). **Built on board-phone-header** (its
`control-console.php` and `admin-control.js` carry board-phone-header's changes), so it goes after that release is live and
synced: then rebuild those two files from the live copies with only this build's change re-applied, run
`work-queue/faq-edits.py` on the live `faq.md`, zip check, send.
State: **built and tested on the bench; in review** (Design reviewer → session manager → Jason's pop-up).

## Files

| File | Change |
|---|---|
| `app/lib/GaugeTasks.php` | the queue on the same record as Request a fix (`control_gauge_tasks`): `add`, `task`, `edit` (old words logged as History), `assign` (Take, Move), `release`, `withdraw` (with a reason), `queue` (each task's state; done by itself when its release is live in `site_releases`), `counts`, `history`; `report()` carries why, source, owner (a session's name, else "a staff member": 0145 §1) and release; `gaugeOk` allows `area:` |
| `app/lib/ControlGauges.php` | `queueGauge()`: the Work queue as a runtime gauge (gauge-lifecycle §7) |
| `app/admin/control-console.php` | `tv_cb_workqueue()`, `tv_cb_wq_row()`, `tv_cb_wq_places()`: the section first on Development |
| `website/admin/api.php` | `config/control` actions `qadd`, `qedit`, `qassign`, `qrelease`, `qwithdraw`, `qhistory` |
| `website/js/admin-control.js` | the section's presses; after each, the section is read again from the server |
| `app/migrations/2026-11-24_work_queue.sql` | six columns (guarded, safe twice) and the 20 tasks seeded with `INSERT … WHERE NOT EXISTS` (`data_reason` in `release.json`) |
| `app/ui/faq.md` | a new question, "What is the Work queue, and how do I add a task to it?", and Development's line (`work-queue/faq-edits.py`) |

Repo: `tools/page-audit/gauge-requests.mjs` marks late as the queue does (a day old and nobody has it, no release named).

## Each behaviour and its source

1. **The same record as Request a fix**, not a second list: `control_gauge_tasks` (kind `fix`), the hourly
   `build-requests.json` and the sessions' `releases/gauge-pickups.json` (the session manager's brief: "Use the same record as
   Request a fix"; `handoffs/gauge-panel.md`, kit-buttons). A task's `bucket` is `task`, so Request a fix's own clearing
   (`GaugeTasks::forGauge`, by its item's bucket) never touches it.
2. **Its gauge is a runtime gauge under gauge-lifecycle §7**: its probe is the queue (built); its target is set by Jason
   (**Approve the target**, as every runtime gauge; proposed "nothing late", **a suggestion**: no decision sets one); its check
   passes while nothing is late; its alarm isn't wired, so it stops at Implementation and says why. Below Monitoring its score
   is the setup %, the reading ("19 of 20 on time") beside it (§7). **Suggest a change** for later: wire a late task to the
   board's alarms (a warning) so it can reach Monitoring.
3. **Late = a day old with nobody on it** (kit-buttons: "Not picked up yet after a day"; `GaugeTasks::LATE_HOURS`). A task
   someone has, or with its release named, isn't late.
4. **Add a task: gauge or area, what, why, source** (the brief's four fields). Areas: the ones tasks already name, plus
   **A new area…** (no list of areas exists in `decisions/`: flagged as a gap; the gauges come from `ControlGauges`).
5. **The four steps** (CLAUDE.md "Every action is a task"): the status in place (Open, Late, Taken by …, Being fixed in …,
   Done, Withdrawn); one button for the next step (Take it, then Name its release); done by its own check: its release live,
   or a fix request's check passing; never a "mark done".
6. **✎ Edit with History, never Undo** (CLAUDE.md "Edit or Undo": a record someone entered): the old words in the logbook,
   shown by the History icon. **⋯** for the other moves, Take greyed with the reason when someone has it (CLAUDE.md "More than
   one way forward"). Withdraw asks the reason and keeps it.
7. **A list, not a process** (CLAUDE.md "A list or a process?"): late first, each row its own cycle, counts dropping; the
   first 10 then Show all (the gauge panel's paging); the filter one dropdown button with counts.
8. **Seeded once** from `handoffs/decided-not-live.md` and `handoffs/control-board-2026-11-24.md` (the brief): the Control
   Board's next releases and the Orders session's M2b–M7 and Later, each with who has it. M2b is **Taken** by the Orders
   session with "paused by him the same day" in its why (Jason, 2026-11-24, #1354: "Pause for now. add to work queue."); the
   board's phone header is **Being fixed in board-phone-header.zip**, so it's done by itself once that release is live.

9. **The Design reviewer's changes (2026-11-24), made:** one filled button in the view (Add a task; each row's next step and
   Approve the target outlined: brand.md's button standard); taken rows grouped by who has them, one row each that opens to its
   members, the sentence said once on the group (table-standard §8; a group counts as one row for paging); the first 10 then
   "Show N more" kept after an add, the new task first and marked; the target said once ("Target: not set · proposed: nothing
   late"). **Not changed, with its source:** the late task's time stays in the zone it was added from (MST), the others
   (recorded without one) in the viewer's: CLAUDE.md "Times where they happened" ("Show it in that zone, always labeled …
   with no zone stored, in the viewer's"). Group dates are the day only.

## Kept, moved, dropped

- Request a fix on a gauge's panel: **kept** as it is; its open requests also show in the queue ("a fix request from its gauge").
- Development's 16 gauges and Site health's list: **kept**, under the queue.
- Nothing dropped.

## The admin's test

- Development: the Work queue first; its gauge, "19 of 20 on time · 1 late …", Approve the target.
- Add a task → it's in the list, Open, counted. Take it → Taken by you. Name its release → Being fixed. ✎ → new words; History
  shows the old. ⋯ → Move to a session; Withdraw with a reason → under Done and withdrawn this week.
- When the named release goes live, the task reads Done by itself at the next look.

## Tests (bench: MariaDB, the mirror with board-phone-header and this build over it)

- `tools/control-board-tests/work-queue.cjs` with `work-queue-seed.php` (80, at 1280 and 390; the migration run twice; the same task added twice stands once; groups, paging after an add, one filled button) and
  `board-panels-router.php`'s new actions; `gauge-requests.mjs` (7: late as the queue says it).
- `run-all.sh`: only the 7 known live failures. `board-views.cjs` takes the Work queue's header as a gauge's place (its pill beside it); `rendered-all.sh`: work-queue 68, gauge-panel 76, board-views 90, board-panels 43, board-opening 48, board-tabs 16, board-phone-header 42, no failures. Full sweep: 659 passed (work-queue 84 with the ▾ checks), the `lines` seed note as on live, and `dispatcher/r3` stopping on a click timeout at its 17th check on this bench, the live code alone too (not this build).
