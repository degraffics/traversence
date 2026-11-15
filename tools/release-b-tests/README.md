# Release B's tests

- `identity.php`: the one identity check (decisions/0164 §3.1, §6.1; handoffs/duplicates.md §3): keys, resolve, merge and
  exact Undo, pairs, the recheck, case #23's offers, the batch fold. 36 tests.
- `gemini.php`: the Gemini client's guards (0164 §0.8): refusal by input class, the private-detail and held-name removal,
  prompts from our own vocabulary. 21 tests.

Run on MariaDB or MySQL with a `.env` four folders up from the app's `lib/` (DB_HOST, DB_PORT, DB_DATABASE=lv_main, …):
`mariadb < schema.sql && mariadb lv_main < <app>/migrations/2026-11-16_listing_identity.sql && php identity.php <app>`
(the app: a mirror's `sync-app` with the release's `app/` laid over it).
- `start.php` (with `start-schema.sql`): Start the system (decisions/0163 §10's note, 2026-11-15): never pressed does
  nothing; a missing key or red line holds only its part; the master waits for lines that hold everything; one workflow
  at a time, W1 first; the gates (good runs, hours, a rise in errors); a failed gate stops and pauses that one, with
  Try again / Leave it; a person's pause stands; Pause everything; staging refuses. 33 tests:
  `mariadb < start-schema.sql && php start.php <app>`.
