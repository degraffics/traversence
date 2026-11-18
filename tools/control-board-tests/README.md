# The System Control Board's tests (decisions/0171)

- `r1.php`: release 1. It covers the unit register (systems, processes, a workflow's parts, what a line depends on), the
  Pre-flight's Routes lines (each hop checked, an unclaimed endpoint named), Start testing what each workflow depends on
  fresh before confirming it, and the four query timings once a Start. 33 tests.

- `r2a.php`: release 2a. It covers the ledger written after each worker call through the endpoints' own `_auth.php`
  (on PHP's built-in server), received, stored, updated, Review and refused by reason, the records touched (one line
  per record), an error answer, a call that isn't signed in, a call before the table exists, trimming, the run's link,
  and Start's watch counting a task's own "nothing to do" runs as good. 21 tests. Run it with `r2a-schema.sql`, the
  migration and `zz_test.php` (a test endpoint, placed at `website/api/crawl/` in the test root only, never shipped):
  `mariadb < r2a-schema.sql && mariadb lv_main < <app>/migrations/2026-11-18_control_ledger.sql`, then
  `(cd <root>/website && php -S 127.0.0.1:8765 &)` and `php r2a.php <root>/app`, with `CRAWLER_API_TOKEN=testtoken`
  and `VIDEO_WORKER_TOKEN=vtoken` in the `.env`.

- `self-withdraw.php`: a build from git that fails on staging withdraws itself; the job's sweep; `"replaces"`;
  Withdraw putting staging back (migrations off, live's copy back, new files off, a later release's copy kept). 17
  tests: `mariadb -e 'DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main' && php self-withdraw.php <root>/app`.

- `releases-live.cjs` (with `releases-live-page.js`): the Releases page keeping itself current (a release drawn again
  as it moves on, its stale notice gone, a new one at the top, open cards kept, a card being typed in waiting), at 390px.
  7 tests: `node releases-live.cjs <website/js/admin-releases.js>`.
- `r2b.php`: release 2b, the board: its stage, a process's worker calls and records from the ledger, Shut it down
  (tasks first), Start it refused while its check is red, Shut down in order (workflows in reverse start-up order, then
  the master switch) and "stopped, safe" once no crawler run is going. 14 tests: after `r2a`'s schema and 2a's
  migration, `php r2b.php <root>/app`.

Run on MariaDB or MySQL, with a root that holds `app/` (the mirror's `sync-app` with the release's `app/` laid over it)
and `website/api/` (the mirror's `sync-storage/api/crawl` and `api/video`). Put a `.env` four folders up from `lib/`:
`mariadb < ../release-b-tests/start-schema.sql && php r1.php <root>/app`.
- `layout.php`: the board laid out after Jason's example: a need's sources marked by the need's own verdict, the core
  hosts, which needs use a company. No database: `php layout.php <root>/app`.
- `r2c.php`: release 2c: requests to outside companies in their own table (old ones brought over with their ids, the
  seed once, an answer closes one), the Stripe and Square cards' key names, the switched-off words' migration.
  12 tests: `mariadb < ../release-b-tests/start-schema.sql && php r2c.php <root>/app` (the root holding the release's
  `app/migrations/`). The worker's side: `python3 workers/crawler/tests/test_held_task.py`.
- `r2d.php`: a check shown part by part (Jason, 2026-11-18: "Red x but green checks too?"): W0's check with a red dry
  run names the dry run as what holds it, never the gate whose words say "no red line"; the board lists the parts with
  their marks, the red one first. 7 tests: after `r2a`'s schema and 2a's migration, `php r2d.php <root>/app`.
