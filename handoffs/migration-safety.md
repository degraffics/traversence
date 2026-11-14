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

## Release 2: `migration-safety2_20261114.zip` (code written, waiting on `Pulse.php`)

Written and tested (MariaDB); the draft is `handoffs/migration-safety-pending/SchemaUpkeep.php.txt` (start from the live copy and re-apply): `SchemaUpkeep` gains `scanUnused()`, `scanDue()`, `unused()`,
`pulseItems()`, `pulseSteps()`, `pulseKeep()` (hex-encoded key in the `do` action), `kept_unused`. Claimed:
`Pulse.php::manage`, `Scheduler.php::sync`. Waiting: `Pulse::items` and `playbook` are claimed by the crawler session for
`service-health3_20261114.zip`; once that's live and synced, rebuild on the live `Pulse.php` and send. If this session
has stopped, a fresh one re-applies the list below on the live files.

- Pulse: one case `schema:leftovers` (due leftovers, a step each: **Keep it** as a `do` button; removal is a release
  Claude builds, so its step says so and gives the drop) and one `schema:unused` (monthly scan). Wire into
  `Pulse::items`, `playbook`, `manage` (all three were claimed by the crawler session).
- Monthly unused-schema scan: `SchemaUpkeep::scanUnused()` from `Scheduler::sync` when the last scan is over 30 days old:
  identifiers in the live `app/`, `website/`, `trvgo/` code vs. the schema of main and the realms; result in
  `app/storage/schema/unused.json`; `removable()` then includes it.
- FAQ: drop "(a Pulse case for it is coming)".

## Notes

- A data-changing migration on a table over 300 MB waits forever (by design: never without its copy). If that happens,
  the way forward is a narrower migration or a raised cap in `DbCopies::MAX_BYTES` (a release).
