# Hand-off: migrations at publish, copied first, leftovers tracked (decisions/0162)

Session `session_01CXLi5jMUt9pWN6nUfCFGaz`, started 2026-11-14 (Jason's "go", via the session manager). Builds
decisions/0162, which amends 0145 §6.

## Release 1: `migration-safety_20261114.zip` (built on Sync 13:21 UTC)

Files: `app/lib/Releases.php`, `app/lib/DbUpdates.php`, `app/lib/DbCopies.php` (new), `app/lib/SchemaUpkeep.php` (new),
`app/admin/sections.php` (`tv_admin_mod_dbupdates`), `app/ui/faq.md` (one entry: "What happens to the database when a
release goes out?"), `app/data/decisions-index.php` (regenerated, adds 0162 and notes since).

- **Publish runs migrations first** (`Releases::runMigrationsFirst`): puts them in live `app/migrations`, runs
  `DbUpdates::autoRun($pdo, false, 'publish of release #N')`, takes out any that didn't run, and on failure notes it,
  sets the release `vetted` + `paused` (`standing()`: "Didn't go live: …", tone bad, so Pulse shows it) and throws. A
  scheduled one drops back to vetted so `tick` doesn't retry every 10 minutes. `DbUpdates::retry()` forgets a failure so
  Publish again retries; `ranAlready()` stops a re-run. The file loops skip `app/migrations/`.
- **Copies** (`DbCopies`): `before()` from inside `autoRun`, just before each file runs; `targets()` parses the
  statements (via `DbUpdates::statements`, so comments are out); `where()` finds main/realm; gz files with a JSON header
  line + `INSERT INTO \`__T__\`` lines; caps 300 MB/table, 1.5 GB total, free disk; data changes wait (not logged
  failed) if a copy is missing. `restore()` copies now first, then DELETE + INSERTs in one transaction per table.
  Tested end to end on MariaDB 10 (1,234 rows with binary and quotes/newlines, a table with no key, the admin realm):
  rows identical after Restore.
- **Checks** (`Releases::migrationChecks`, staging and at Publish): add-only, or drops of recorded leftovers named under
  `"removes"`; a data change needs `"data_reason"` (≥10 chars). `zipcheck.py` mirrors it (`changes_data`, `drops`,
  `migration_review`).
- **Leftovers** (`SchemaUpkeep`): `recordLeftovers()` on Roll back, `takenUp()` on a later publish, `leftovers()`,
  `keep()`, `removable()`, `drops()`; `app/storage/schema/leftovers.json`. Listed on Database updates with Keep it.
- Permission: `config.dbupdates.restore` (Executives by default).

State: **live** as release #45 (2026-11-14 14:57 UTC), mirror verified byte for byte at Sync 14:59 UTC; delivery log live, claims released.

## Release 2: `migration-safety2_20261114.zip` (built on Sync 15:21 UTC, sent; waiting for Jason's OK to merge)

Files: `app/lib/SchemaUpkeep.php` (`scanUnused()`, `scanDue()`, `unused()`, `pulseItems()`, `pulseSteps()`, `pulseKeep()`,
`kept_unused`; `removable()` includes the scan's items), `app/lib/Pulse.php` (`items`: the `schema:leftovers` and
`schema:unused` cases; `playbook`: `case 'schema'`; `manage`: `schema_keep:<hex key>` → Keep it), `app/lib/Scheduler.php`
(`sync`: the scan when the last is over 30 days old), `app/ui/faq.md` (the entry's "coming" made current). Tested on
MariaDB (built names like `crawl_` + $x count as used). After it's live: run the sync, check byte for byte, release the
claims, and the build is done.

## Notes

- A data-changing migration on a table over 300 MB waits forever (by design: never without its copy). If that happens,
  the way forward is a narrower migration or a raised cap in `DbCopies::MAX_BYTES` (a release).
