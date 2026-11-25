# task-seed: Jason's scope doc in the Work queue (2026-11-24)

Jason, 2026-11-24: "i need all the tasks in the que as well before usage runs out". Source: his doc "Work queue: task scopes"
(https://claude.ai/artifact/NgH5Vhw4uPadPkgSpfZuJu, rev 5), its three tables. `task-seed/seedgen.py` writes the migration from
the rows (re-run it on a newer read of the doc). One migration, no code: 39 rows; 19 match tasks already in the queue (their scope
set, his notes appended to their source: no notes field yet, a suggestion), 20 added once; 12 decisions attached from the names in
each row. `"; "` in his text is written ` · ` in the SQL (the doc keeps his words). Sent after task-adrs is live (zip check R14: the
decisions table's staging class is in task-adrs). Bench: run twice, the same 39 tasks and 12 decisions.

## #284 didn't run; task-seed-2.zip (2026-11-24)
#284 published, but its migration was cleared unrun: `DbUpdates::autoRun` skips a file whose `DbUpdates::checks()` all exist
(state `run`, logged "already there"), and the seed's one CREATE TABLE named `control_gauge_task_adrs`, made by #283 minutes
before. `2026-11-24_task_seed_2.sql` is the same seed without the CREATE (no checks: state `unknown`, so it runs).
**Suggest a change** (for the session manager): the zip check could refuse a migration that writes rows while every table and
column it names already exists on live, since the runner would clear it unrun.

## For the next seed (queued 2026-11-24, add with Add a task or the next seed migration)
1. **Priority 1, area control-board: "A data migration is never cleared as already there unrun."** The session manager,
   2026-11-24: the runner logging a row-writing migration "already there" without running it is a silent false success
   (CLAUDE.md: no action claims success without a real check). Goal: a file counts as run only by its own `db_updates_log`
   entry (decisions/0162), never because its tables exist. In scope: `DbUpdates::autoRun` / `all()` state; the zip check
   refusing such a file too. Done when: #284's case runs on the bench; a note on 0162. Source: #284 (task-seed).
2. **Area backlog: "Gemini on the paid tier."** Jason switched Gemini to paid (billing linked, $20 on Cloud Prepay; Google's
   $300 credit excludes Gemini). Goal: Gemini in the Service register as Paid tier, its cap the prepaid balance, shown on the
   Ledger. Note on 0164's "nothing private to Gemini's free tier": it still holds for the free tier, so check which key and
   tier each caller uses. Source: the session manager relaying Jason, 2026-11-24.
