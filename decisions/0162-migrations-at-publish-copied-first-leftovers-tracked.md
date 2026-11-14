# ADR 0162: Migrations at publish, copied first; leftovers after a rollback tracked and removed by release

**Status:** Accepted (Jason, 2026-11-14, "go", through the session manager). It amends `decisions/0145` §6
("Add-only database changes within a release") and keeps every other rule of 0145 and `decisions/0159` §5.

## Context

Until now a release's migrations reached the live `app/migrations` with its other files at Publish, and the site's
runner (`DbUpdates::autoRun`) ran them within the hour. So for up to an hour the new code was live on the old database,
and a migration that failed was found only after its code was out. Nothing was copied before a migration ran: an
`UPDATE` or `DELETE` that went wrong could only be undone by hand. Roll back put a release's files back but said
"migrations stay (they only added)", and nothing tracked what they had made, so tables and columns no code uses could pile
up unseen.

## Decision

1. **Migrations run as Publish's first step.** When a release carries migrations (already run on staging, where its
   checks passed), Publish puts them in the live `app/migrations` and runs `DbUpdates::autoRun` at once, unthrottled,
   before any other file goes live. If one fails, nothing else is published: the error is in the release's record, shown
   where Publish was pressed (a notice, never a dead end) and in Pulse (*Didn't go live*), and the files that didn't run
   are taken out again, so the hourly run never runs them without the release's code. Publish again is a real retry; a
   migration that ran on an earlier try isn't run twice. A release without migrations publishes as before. The hourly run
   (`Scheduler::sync`) stays as a backup for anything waiting.
2. **A copy before every migration.** Just before any migration runs (at Publish, on staging, hourly, or when Database
   updates opens), `DbCopies::before()` copies the tables it writes to (the targets of its `ALTER`, `UPDATE`, `DELETE`,
   `INSERT`, `REPLACE`, `DROP`, `TRUNCATE`, including those in the information_schema guard's strings), found in main or the
   database a table has moved to (decisions/0110, `Database::schemaOf`). Each is written compressed to
   `app/storage/db-copies/` (never served, never synced) and kept 14 days. System Configuration → Database updates lists
   them, each with **Restore** (a confirm, marked as dangerous; `Access::can('config.dbupdates.restore')`, Executives
   until a role is given it). Restore takes a copy of the tables as they are first, then, one transaction per table, puts
   the copy's rows back; columns added since stay, with their defaults. Bluehost's disk is minded: a table over 300 MB, a
   total over 1.5 GB of copies, or too little free disk skips that table with the reason recorded and shown.
3. **Leftovers after a rollback.** Roll back records what the release's migrations made that's still there
   (`DbUpdates::checks`) as *left by release #N* (`SchemaUpkeep`, `app/storage/schema/leftovers.json`), listed on
   Database updates. A later release whose migrations make the same thing takes it up. One still left after 30 days is
   proposed for removal, as one Pulse case for the chore with a step per item (release 2 of this build); **Keep it** stops
   the proposal. Removal goes as a normal release, which Jason approves: its `release.json` names each item under
   `"removes"` (`"table t"`, `"column t.c"`, `"index t.i"`), the site's checks allow a drop only of a recorded leftover so
   named, and the copy is taken first.
4. **Data changes need a reason.** A migration that changes rows (`UPDATE`, `DELETE`, `TRUNCATE`, a drop) needs a
   `"data_reason"` (a sentence) in `release.json`: the zip check (`zipcheck.py`, so `send.py` too) and the release's checks
   on staging block it otherwise. It never runs without its copy: if a table it touches can't be copied, it waits (not
   marked failed) and is tried again on the next run.
5. **A monthly check for unused schema.** Tables and columns no live code mentions (the live files, which the mirror
   records) are listed in one Pulse case; Jason decides what goes. Built in release 2 of this build.

## §6 of 0145, as amended

- **Add-only database changes within a release, copied first.** New tables and columns only; never a drop or rename in
  the same release that adds. Every migration's tables are copied just before it runs. What a rolled-back release's
  migrations made is tracked as its leftovers and, if the release isn't sent again within 30 days, removed by a later
  release that names them, copies first and Jason approves. The previous version still runs on the new database, so a
  rollback never loses data.

## Consequences

- No window where new code runs on an old database, and a bad migration stops its release before any file changes.
- A wrong `UPDATE` or `DELETE` is one Restore away for 14 days.
- Disk: copies are compressed and pruned at 14 days, with per-table and total caps.
- Release 2 (Pulse cases for leftovers and unused schema, the monthly check) waits for `Pulse.php`'s claim by the
  crawler session to end (decisions/0161).
