# task-seed: Jason's scope doc in the Work queue (2026-11-24)

Jason, 2026-11-24: "i need all the tasks in the que as well before usage runs out". Source: his doc "Work queue: task scopes"
(https://claude.ai/artifact/NgH5Vhw4uPadPkgSpfZuJu, rev 5), its three tables. `task-seed/seedgen.py` writes the migration from
the rows (re-run it on a newer read of the doc). One migration, no code: 39 rows; 19 match tasks already in the queue (their scope
set, his notes appended to their source: no notes field yet, a suggestion), 20 added once; 12 decisions attached from the names in
each row. `"; "` in his text is written ` · ` in the SQL (the doc keeps his words). Sent after task-adrs is live (zip check R14: the
decisions table's staging class is in task-adrs). Bench: run twice, the same 39 tasks and 12 decisions.
