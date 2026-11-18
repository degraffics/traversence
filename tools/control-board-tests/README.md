# The System Control Board's tests (decisions/0171)

- `r1.php`: release 1. It covers the unit register (systems, processes, a workflow's parts, what a line depends on), the
  Pre-flight's Routes lines (each hop checked, an unclaimed endpoint named), Start testing what each workflow depends on
  fresh before confirming it, and the four query timings once a Start. 33 tests.

Run on MariaDB or MySQL, with a root that holds `app/` (the mirror's `sync-app` with the release's `app/` laid over it)
and `website/api/` (the mirror's `sync-storage/api/crawl` and `api/video`). Put a `.env` four folders up from `lib/`:
`mariadb < ../release-b-tests/start-schema.sql && php r1.php <root>/app`.
