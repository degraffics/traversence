# task-adrs: the decisions attached to a Work queue task (2026-11-24)

The Control Board build session. Jason, 2026-11-24: "need task to show what ADR is attached to it or have the ability to look and
assign adrs"; "build these two decisions please. 1. scope. then 2. adrs". Zip `task-adrs.zip`, **built on task-scope's files**:
sent after task-scope is live and synced (rebuild from the live copies, then `task-adrs/faq-edits.py`). Build in `task-adrs/build/`,
screens `task-adrs/ta-*.png`.

## What it does, and its source
- **Decisions on the task's page:** each attached one by number and title, opening on System Configuration → Decisions
  (`/admin/config/decisions?d=<num>`) in Peek (CLAUDE.md: our own pages open in Peek).
- **Attach a decision** searches the Decisions page's own list (`DocsRepo::all()`: the register plus the bundled
  `decisions-index.php`), by number or a word in the title; no second list (the session manager's brief). Already attached: greyed,
  saying so. The register's titles begin with their number; it's said once.
- **✎ detaches** (untick), each change logged in the task's History; a detached row is kept in `control_gauge_task_adrs` with
  who and when (CLAUDE.md "Edit or Undo": a record, edited). The queue's row names its decisions.
- New table `control_gauge_task_adrs` (staging class OWN, R14); migration `2026-11-24_task_adrs.sql` (safe twice, no data changed).

## Tests
`tools/control-board-tests/task-adrs.cjs` (32 at 1280 and 390); task-scope 36 still passes over it.
