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
- `r3a.php`: release 3a, the alarms: raised once per problem (a repeat updates it, worse becomes a trip), cleared by
  itself saying how, acknowledged and undone; the ledger's server errors and wrong counts (by the process each endpoint
  serves); the 5-minute watch (a run that never reported, a job that stopped, W0 quiet); the seed (red pre-flight lines
  named by the part that holds them, W2's server error, off-region sources), the dispatcher's alarm kinds and the Pulse
  body with Acknowledge. 24 tests: after `r2a`'s schema and 2a's migration, `php r3a.php <root>/app`.
- `r3b.php`: release 3b: Overpass as one trip (raised once for every refusing server, a notice per server folded in,
  Acknowledge doesn't close it, cleared when the last refusal is resolved), a reading that repeats not counted as a new
  time, the seed's 5xx only when the latest call failed, the ▸ toggle (no "…", no More) and the check's other parts never
  repeated, old lines told to press Check, a long button list dropping whole buttons, an alarm's Pulse steps (Raise the
  cap as a form, Pause it, Mark resolved) and a step it doesn't carry refused. 17 tests: after `r2a`'s schema and 2a's migration, `php r3b.php <root>/app`.
- `r3c.php`: release 3c, the Trip rule: a crash a warning for its tries, a trip on the third (or after an hour);
  refusals at once; a trip stops only its own workflow, the master switch only when the crawler itself is down, a site job
  tied to no workflow only trips; its good reading noted, never cleared by itself; Resolve asks what fixed it, starts
  again what it stopped, a red check keeps it open with the reason, Undo; a back-off at its limit holds the server until a
  person resolves it; Overpass's trip not cleared by a 200; slow calls at the ledger; the steps' families; Pulse's
  Resolve and Undo; a red required line a warning while the system is stopped. 28 tests: after `r2a`'s schema and 2a's
  migration, `php r3c.php <root>/app`.
- `r3d.php`: release 3d: the group key (system, level, family), five engine feeds held as one group and a lone alarm on
  its own, Acknowledge all, Pulse's group item with its children (a done child leaves it), the board's group row with
  its alarms' own buttons and a fold the redraw keeps, the marks' red and amber. 17 tests: after `r2a`'s schema and 2a's
  migration, `php r3d.php <root>/app`.
- `r3e.php`: release 3e: a crawler run's page (the command strip, its records by workflow and kind, Peek links, a
  person's field said to be kept, a count narrowing it, a run no longer kept), "W1 today" and the last 7 days, a workflow
  with none. 12 tests: after `r2a`'s schema and 2a's migration, `php r3e.php <root>/app`.
- `w2-verify.php`: W2's second look: a listing whose check throws is set aside with its error; the board reads it.
