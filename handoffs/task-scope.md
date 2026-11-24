# task-scope: a Work queue task's page (2026-11-24)

The Control Board build session. Jason, 2026-11-24: "I need to be able to scope the tasks from the queue, so scope editor";
"Each task must open and allow me to scope and set priorities and work that needs to be done"; "build these two decisions
please. 1. scope. then 2. adrs". Zip `task-scope.zip`, built on Sync 12:58 UTC (7558fa0), over work-queue (#281).
Build in `task-scope/build/`, screens `task-scope/ts-*.png`, FAQ `task-scope/faq-edits.py`.

## What it does, and its source
- **The row's name opens the task's page** in the board's side panel (03 §27's panels; loaded as it opens, `?wqtask=<id>`, as a
  gauge's detail is). The panel sits beside the queue's section, so reading the section again leaves it open.
- **Scope:** goal, in scope, out of scope, done when (the session manager's brief). Each ✎ Edit in place through
  `GaugeTasks::edit`, the old words logged as History (CLAUDE.md "Edit or Undo"); no Undo anywhere.
- **Priority 1–3**, each with its meaning (CLAUDE.md "Words and lists"; the meanings are **a suggestion**: "first: the next one
  taken", "next, once the 1s are taken", "when there's room"). Late and open tasks sort by it, then by age.
- **Steps**, each see → do → confirm (CLAUDE.md "Every action is a task"): Add a step; Confirm it's done records who and when;
  a confirmation made by mistake is corrected with ✎ ("not done after all", logged), never undone.
- **The row** shows P1–P3, the goal (160 characters) and "n of m steps confirmed".
- New table `control_gauge_task_steps` (staging class OWN, R14); five columns on `control_gauge_tasks`; migration
  `2026-11-24_task_scope.sql` (guarded, safe twice, no data changed).

- **The Design reviewer's notes (made):** a step reads See, Confirm, then its press; a missing See or Confirm says "Not set yet";
  the task's own next step (Take it, then Name its release, outlined) sits beside its state at the top of the page; its release
  is named in place (a dialog would open behind the panel's backdrop).

## Kept, moved, dropped
Everything on the row kept (Take it, ✎ for what/why/source, History, ⋯). The priority chooser is a form in the panel (a
`TvAsk.choose` dialog opened behind the panel's backdrop). Nothing dropped.

## Tests
`tools/control-board-tests/task-scope.cjs` (44 at 1280 and 390); work-queue 84, gauge-panel 76, board-views 90 unchanged.
