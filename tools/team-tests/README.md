# Piece 2's tests (decisions/0179; handoffs/piece2-team.md §12)

Run on MariaDB or MySQL, with a root that holds `app/` (the mirror's `sync-app` with the release's `app/` laid over it) and
`website/` (the mirror's `sync-storage/js` at least), and a `.env` two folders above the root (`Database::envPath`:
four folders up from `app/lib/`) naming `DB_DATABASE=lv_main`.

- `roster.php` (P2-2, the Team): the schema and its seed each run twice; 33 roles, 9 working, 11 advocates, 13 on standby;
  an edited role never written over; `Access::canAs` against the rules for every level and department; advocates and the
  Founder never act; Edit with History; the status and its way back; the tool `config/team` (groups, filters, drawer,
  actions). 51 checks: `mariadb -e 'DROP DATABASE IF EXISTS lv_main; CREATE DATABASE lv_main' && php roster.php <root>/app`.
- `roster.cjs` with `router.php` (P2-2 rendered at 1280 and 390): groups closed until opened (phones too), one page for five
  group rows, the drawer and its words, ×, Esc and a click outside, Put on standby and Make active through the drawer's form,
  History in place, no sideways scroll, no page error. 40 checks: `node roster.cjs <root> [shots dir]`.
- `tickets.php` (P2-1, ticket types and stages): over `before-schema.sql` (the Work queue as live before Piece 2), the two
  migrations run as the site runs them, twice; today's tasks staged; Add a task by type; each Work gate, one stage at a
  time, back with a reason; the system's own moves (release named, live, steps confirmed; no steps; no stage as #281); a
  Resolution's brief, Send, the answer, its precedent and its close; counts, the gauge and `report()`. 48 checks:
  `php tickets.php <root>/app` (it resets lv_main).
- `tickets.cjs` with `board-router.php` and `tickets-seed.php` (P2-1 rendered at 1280 and 390 on the board): rows' type and
  stage, the Tier 3 first, the Team icon, the filter, Add a task by type, a Work ticket's strip and gate, No decision
  applies, Move, ✎ back, the Decision Brief, Choose B and its precedent. 60 checks: `node tickets.cjs <root> [shots dir]`.
- `old-suites.sh` with `tickets-migrate.php`: the Work queue's own suites (work-queue 84, task-scope 44, task-adrs 32) over
  this build, their seeds running Piece 2's migrations too: `bash old-suites.sh <root>`.
- `runner.php` (P2-3, the runner): nothing and no log on live, off until On, the lock, one step a run and an hour's gap, never a
  person's ticket, the Project Manager's gate, the System Architect's build brief and `report()`, the Translator's proposal,
  Undo and a second Undo refused, Accept (a person's Edit) once, a role on standby, Permissions refusing, no outside call or
  paid AI, no SQL on landmarks or nations, the queue's tables records and their links, the job QUIET and staging only. 32
  checks: `php runner.php <root>/app` (it resets lv_main).
- `runner.cjs` with `runner-seed.php` (P2-3 rendered at 1280 and 390): a run's line with Undo, pressed; a proposal with Accept,
  pressed. 22 checks: `node runner.cjs <root> [shots dir]`.
- `audit.php` (P2-4, the daily audit): its sources (Site health from the cached report, a late job, an open alarm), one Audit
  ticket per finding, tiers by 0179, nothing doubled, the second look (confirmed opens Work or a Resolution; gone is cleared),
  the Audit closing with its ticket, Undo, Permissions, live and off doing nothing, the job, Run the audit now. 21 checks:
  `php audit.php <root>/app` (it resets lv_main).


## Index (moved from the root README, 2026-11-28)

Piece 2's tests (decisions/0179; `handoffs/piece2-team.md` §12; its README): `roster.php` + `roster.cjs` (the Team), `tickets.php` + `tickets.cjs` (ticket types and stages), `runner.php` + `runner.cjs` (the staging-only runner, the ceiling, the Tier 3 flag), `team-head.cjs` (the Team page's decision tree and how far agents work), `audit.php` (the daily audit, Tier 3 by what a finding touches), `batch.php` + `batch.cjs` + `batch_py.py` (P2-5: the daily batch and emailed answers), `fence.cjs` with `fence-seed.php` (P3-6: the fence's line on a ticket's page at 1280 and 390), `old-suites.sh` (the Work queue's own suites over Piece 2), `rollback.sh` (a release's Roll back proved on the bench)
- `test-ticket.php` + `test-ticket.cjs` (Run a test ticket, TeamVet; Jason, 2026-11-28): never on live, refused in words with the
  way forward where a role can't run yet, one test ticket through the real `TeamRunner::run`, the result and its cost shown and
  kept, nothing left in the queue; 45 checks on MariaDB and 26 at 1280 and 390: `php test-ticket.php <root>/app`,
  `node test-ticket.cjs <root> <shots>`.
- `team-pm.php` + `team-pm-db.php` (agent 01, the Project Manager's sequence and stall steps; held for a Founder ruling): 50 checks
  without a database, 9 on MariaDB in staging mode.
