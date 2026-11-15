# Hand-off: decisions/0165, staging is the live site in safe mode; Publish merges it

Built 2026-11-15 in one session as **one release** covering all six steps of 0165 §7. The zip is
`releases/inbox/release-0165-staging-safe-mode.zip`, sent with `"review": true`. It was built on the sync of
**14:12 UTC, 2026-11-15**, after #55. This file says what's where, what ships off, how it was tested, and what's left.

## What's where (all new code is in `app/lib/`)

| File | What it does |
|---|---|
| `StagingGate.php` | Staging's answer at the site's gates (§1). `hold($url, $what, $ref, $bulk)` returns null on live at once. On staging it applies `ANSWERS`, one per service in `OutsideServices::COMPANIES`: hold, sandbox, read (paced 6 a minute per host), or allow. It also holds: the `staging_held` ledger (it takes in `staging_mail`); `staging_allow`, the per-service "allow for a test" with an expiry; `key()`, which lends live's key while a service is allowed; `liveKeys()` (payments refuse); `csp()`; and `scan()`/`scanText()` for ungated outside calls. |
| `staging-tables.php` | The table register (`StagingTables`): classes, `KINDS` (kind:id), `LINKS`, `REFS`, `NAMES`, `JSON` paths, `TEXT_COLS`, `STORE_KEYS`. An unlisted table follows live. |
| `StagingIds.php` | The register of record numbers. `build()` works from live's columns. It rewrites text, JSON, store keys and rows, with no database. |
| `StagingMerge.php` | §4's three-way merge as rules (`row()`, `settle()`), with no database. |
| `StagingSync.php` | Catch-up, run on live by each realm's live login (which reaches its staging copy). It covers:<ul><li>the `stg_base` fingerprints (MD5 of HEX of each value);</li><li>staging's `stgc_*` triggers into `stg_changes`, recording `@stg_actor` and `@stg_test` and skipping `@stg_sync`;</li><li>live's `stgo_*` outbox triggers into `stg_outbox`, which skip `@stg_pub`;</li><li>`push()`, `passTable()` and `followTables()`;</li><li>`renumber()` at a clash, with refs rewritten across realms and composite keys moving their change records;</li><li>`stopOnUnknown()`, `conflicts()`, `settle()` and `keepLiveForRest()`.</li></ul> |
| `StagingPublish.php` | The preview (it fills `stg_plan` in each staging database) and the publish steps: check, migrations, data, fixup, files, store, checks, instep. Also the write-ahead `pub_journal`, the backward `undoStep()`, `rollBack()` (14 days), `carryOn()`, `heldFor()`/`sendNow()` (To send now) and the email. |
| `StagingApi.php` | One action list for both doors: System Configuration → Staging (`website/admin/api.php`) and the site manager (`website/api/release-bar.php`, `stg:<act>`). |

These existing files changed:

- **`Releases.php`**
  - `autoPublish` is gone; `fromGit` leaves every build on staging.
  - New: `onStaging()`, `replacedIn()`, `publishPart()` (migrations, files, checks), `goLive()` (taken out of `publish()`), `movedOnLive()` and `asBar()`.
  - The single-release Publish and Schedule now refuse, pointing to Publish staging. The urgent path is unchanged.
- **`StagingSite.php`**
  - The weekly refresh is off: `due()` always returns false, and `refreshAll` is gone.
  - New: `ENV_DROP_0165` plus `cleanEnv()`, and the `stripRule()` refactor (checked byte-identical against live's strip).
  - New: `strippedCols()`, `pairs()`, `putState()`, `stripping()` (one salt for staging's life), `keepCaughtUp()` (the switch), `jobs()` (staging's own cron), `stagingRoot()` and `startOverLosses()`.
  - The refresh adds `base` steps when catch-up is on.
- **`Staging.php`**: the CSP header, the ledger in `holdMail()`/`heldMail()`, and `tag()`.
- **`Database.php`** tags staging's connections.
- **`Scheduler.php`**:
  - `lineFor()` tells live's cron lines from staging's;
  - `syncStaging()` and `NOT_ON_STAGING`;
  - three new jobs, `staging_push_tick` (every minute), `staging_night_tick` (nightly) and `staging_publish_tick` (every minute);
  - `staging_tick` is out of AUTO and does nothing.
- **The review email** (Jason, 2026-11-15, relayed): `Releases::changes()` reads release.json's `changes` (`[{what, where}]`); without it, `why` split into its numbered parts. `sendForReview` sends HTML in the daily report's look (`DailyReport::frame()`, `box()` and `button()`, shared) with a plain-text alternative. The Pulse case's "Read the release" step and the site manager's Changes list the same items. `send.py` warns when `changes` is missing. This release's own email is sent by live's current code, so the new look starts with the next release.
- **`QuickBooksPay::guardStaging()`**: `guardReal()` refuses while a live key is in staging's .env.
- **Gates in the outside helpers:** `AiClient` (Gemini), `TravelLegs` (openrouteservice), `stock/StockSource`, `Geocoder` (a bulk run is held) and `PlaceFinder`.
- **`ServiceHealth::allowed()`** asks the gate.
- **`Operations::register()`** has a `staging` column.
- **Pages:** `sections.php` (`tv_admin_staging_mirror`; Start staging over; the Releases buttons), `admin-staging.js` (`#stg2`), `stg-bar.js` (Staging ▾ and its panels), and `api/stg-csp.php` (CSP reports into Held).
- **The zip check, in the docs repo** (`tools/page-audit/audit.py`):
  - R13: a new outside call without a gate (blocking; the message names the function);
  - R14: a new table with no class (blocking);
  - `findings.py` CODE_BLOCKING was updated.

## What ships off (live behaves as before until Jason uses it)

- **Keep staging caught up** (System Configuration → Staging) is off. Switching it on starts staging over once, then adds the triggers and the base. Switching it off drops live's outbox triggers at once.
- **Staging runs its own jobs** is off.
- The three new jobs do nothing (and log nothing) until catch-up is on or a publish is running.
- No migrations are shipped. Staging's tables are made by code in staging's databases on first use. On live: `stg_outbox` and its triggers (only when catch-up is on) and `staging_state` keys (`mirror`, `jobs`, `preview`, `publish`, `last_publish`, `to_send`, `scheduled`, `pushed`, `night`, `ungated`, `salt`).

## Tested (no live database)

- `php tools/staging-tests/unit.php <app>`: 49 tests of the merge table, the register's rewriting, the table classes, the gate scan, live keys and the CSP.
- `php tools/staging-tests/db.php <app>` on MariaDB 10.11: 49 tests, with two realms each holding a live and a staging database:
  - the outbox and push, and the strip on the way in;
  - staging's triggers and actor;
  - a clash renumbered across a column, JSON, kind:id and a second database (composite key included);
  - conflicts and settling, and the preview (test data, money);
  - a forced failure part way, undone backward, so live ends as it was;
  - a late change on live;
  - Publish: new rows take live numbers and references follow, Publish's writes aren't pushed back, and the base is taken again;
  - Roll back, with a row changed since left and listed.
- The `<app>` for both: a mirror's `sync-app` with the release's `app/` laid over it, and a `.env` four folders up pointing `DB_DATABASE=lv_main`.
- R04 (PHP 8.0) clean; `php -l` on every file; the zip check passed (`--drops refreshAll,autoPublish`, both named in 0165 §7).

## Not done or worth knowing

- **Raw calls at runtime:** PHP on Bluehost can't intercept curl, so staging can't log a raw call as it happens. What it does instead:
  - the nightly scan of staging's own code lists any outside call that skips the gate (shown on the Staging page and in the site manager);
  - R13 refuses a new one;
  - the paid services' keys are out of staging's .env, so an ungated paid call fails there rather than leaving.
  At build time 37 such calls were in the code; the paid ones among them have no key on staging.
- **Who made a change:** `@stg_actor` is set when the connection is made, after the session starts. A change made in the same request as a sign-in is credited to the visitor before it. The preview lets Jason mark any row test or work.
- **Unknown references:** `stopOnUnknown()` stops a move only when an unknown column holds one of the numbers being moved, so it doesn't stop on every gap in the register. The preview lists the unknown ones.
- **Not yet carried:**
  - store files: all of `staging_s3` is copied, with a `published/<pub>/` copy of any live file it replaces;
  - texts and push: nothing sends them yet; any sender built later goes through the gate.
- **If triggers are refused** (no TRIGGER right): `canTrigger()` probes each side. On staging without triggers, a row both sides changed is a conflict on every differing field (the §3 fallback). On live without triggers there's no outbox, so the nightly pass carries catch-up alone.

## Next

Once Jason approves the release on staging and publishes, run the sync and check the files byte for byte. Then release this
session's claims, and update this file with the release number and "live".
