# ADR 0165: Staging is the live site in safe mode; Publish merges it into live

**Status:** Proposed (written 2026-11-15 for Jason's approval, through the session manager). Nothing here is built.
Once accepted it **replaces** `decisions/0159`'s 2026-11-13 progress note that builds sent through git go live by
themselves (`Releases::autoPublish`), and `decisions/0145`'s "refreshed from live" (the weekly copy over staging,
`staging_tick`). It keeps `0145`'s staging rules (held email, sandbox payments, not indexed, behind a password), `0159` §5
(the site manager), `0162` (migrations first, copied first) and `0110` (the walls between the databases).
Release B waits for Jason's answer on this.

## Context

Jason's words (2026-11-15):

> "the process of vetting a release is actual use of the system with changes applied. The reason why Ive asked for zips
> and then upload them to the server is so I can do the work, while I go through the site and attempt to work I see
> issues and areas of improvement, so I come back here and make those requests, implement, and work some more. the
> staging is where we can run the system without affecting the real front end, so we need a way to have the live site
> and the staging act the same while controlling the releases and migrations into the live site."

> "staging, it should be a mirror of the live site in safe mode. it acts like the live site, but it does not affect the
> live site until published. To publish the stage is to move what the stage has become and the data it has generated
> into the live site, which means balancing stage against live so we merge without losses or conflicts."

What the live code does today (read from the mirror on 2026-11-15, `sync-app/lib/Staging.php`, `StagingSite.php`,
`Releases.php`, `scripts/staging_carry_tick.php`, `QuickBooksPay.php`):

- **Staging is a full copy** at staging.trvgo.us: its own folder, its own `.env` (`STAGING=1`), its own four databases
  (main, admin, listings, users), a viewing password, `noindex`, a "Staging" mark on every page.
- **Safe already:** email is held in `staging_mail` (Mailer and SES); payments are sandbox for everyone
  (`QuickBooksPay::testHere()`), live gateway rows are deleted after the copy (`qb_connection`, `market_gateways`); paid
  AI (Anthropic, Google Document AI, AWS keys) and the crawler are off unless `staging_ai` / `staging_crawler` is on; files
  go under `staging/` in the store and a live file is never deleted from staging; cPanel, GitHub, SES, worker, edge,
  Name.com, social sign-in and analytics keys are left out of staging's `.env`; Turnstile uses its test keys.
- **Not safe yet:** other keys still reach staging's `.env` with no staging gate in the code read: Google Places,
  openrouteservice, Pixabay (and any key not in `ENV_DROP`). Sandbox gateways' webhooks point wherever their app was set
  up. Staging shares the server's IP address with live, so its calls to free public services (OpenStreetMap, Wikipedia,
  the Weather Service) count against the same limits and can get live blocked (as Overpass did, research/osm-overpass).
- **Staging runs no scheduled jobs:** cron jobs are added through cPanel, and staging has no cPanel key. So staging
  doesn't "act like live": nothing ticks there.
- **The weekly refresh wipes staging:** each table is dropped and copied again from live (`DROP TABLE`, `CREATE … LIKE`,
  `INSERT … SELECT`), then people's details are replaced. Anything Jason did on staging is lost, and every release on
  staging goes stale (`refreshedSince`).
- **Publish carries only a release's files** (and its migrations first, 0162). Nothing Jason made on staging (a page, a
  listing fixed, a setting, a workflow) reaches live. And builds from git publish themselves once their checks pass.

So today staging is a place to look at code, not a place to work. This decision makes it the place to work.

## Decision

### 1. Safe mode: staging acts like live and can't touch the real world

**One gate.** Every outside effect goes through one question, `Staging::outside($what, $detail)`: on live it says
"go"; on staging it says **hold**, **sandbox** or **allow** (for a switch turned on for a test), and records what was
held in one ledger, `staging_held` (it takes in `staging_mail`). The service register of `decisions/0163` names every
service; each one's staging answer is a column there, so a new service can't be added without one. A service with no
answer is held.

| Outside effect | On staging |
|---|---|
| **Email** (system emails, notices, sign-in links, work orders, Pulse, the release emails) | Held in the ledger, never sent (today's rule). Staff on staging read them in **Held**, so a sign-in link or a confirm link can still be followed there. |
| **Texts and push** (coming; nothing sends them yet) | Held, the same way. Any sender built later goes through the gate. |
| **Payments** (QuickBooks, Stripe, Square, payouts, refunds, invoices) | Sandbox only (`testHere()` for everyone, `guardReal()` as on live), live gateway rows removed after every copy, and live payment keys left out of staging's `.env` (only the `QB_SANDBOX_…` and sandbox keys stay). |
| **Paid outside APIs and AI** (Anthropic, Gemini, Google Places and Document AI, Tavily, Brave, openrouteservice, Pixabay, Textract, every per-use key) | Held. A switch per service (`staging_ai`, `staging_crawler` today; one per service in the register) allows it for a test, counted against live's caps (0163), because it is the same money. Where live already has the answer cached, staging may use that copy. |
| **Free public services** (OpenStreetMap, Wikipedia and Wikidata, NWS, NPS, RIDB, USGS, Census, NPI, IRS) | Allowed for reading, but paced inside live's own limits (the same IP address), never a second crawler's worth. Bulk pulls are held. |
| **The crawler and automation** (0163 §9's master switch, workflows, tasks; the Railway workers; the video worker) | The crawler is off unless its switch is on. Workflows run on staging's data, so they act like live, but their outside calls go through the gate. Worker keys stay out, so jobs for Railway are held. |
| **Scheduled jobs** | Staging gets its own: live's Scheduler adds staging's copy of each job to cron (live has the cPanel key), pointing at staging's folder. They run on staging's data under the gate. Jobs that only talk to the outside world (`ses_setup_tick`, `domains_tick`, `audit_clock_tick`, the sync) do nothing there. |
| **Webhooks** | In: the sandbox apps' webhooks point at staging's own addresses; live's webhooks never reach staging. Out: none (GitHub, Fly, Name.com keys stay out). |
| **Domains and storefront edge** (Name.com, Fly.io, DNS) | Held. A business's domain is never connected from staging. |
| **Sign-in by Google, Apple, Microsoft, Facebook** | Off (email only, Jason 2026-11-08). |
| **The 3-way sync and git** | Staging is never synced to OneDrive or the mirror, and never writes git. Live writes the release records. |
| **Search engines and analytics** | `noindex` header, robots `Disallow`, the viewing password; no analytics key; sitemaps never pinged. |
| **The file store** | Staging writes under `staging/` and never deletes a live file (today's rule). |

**What a person on staging sees when something is held:** the action works as on live, and the site's notice says what
didn't leave: "Held on staging: the email to the business (Order #9) is in Held. Nothing was sent." (`TvAsk.say`, beside
the button, never a browser dialog). Where the answer had to come from outside (an AI description, a Places lookup), the
notice offers the way on: **Use live's answer** when there is one, **Allow it for this test** for someone who may turn
the switch on, else "Held on staging: ask our team (support@traversence.com)". Never a dead end. The site manager's
**Held** list shows everything held, newest first, with the time in the zone it happened (`data-tv-time`).

### 2. Releases collect on staging; nothing goes live by itself

- A build (from git, or Upload) goes onto staging, its migrations run there and its checks run. **It stays there.**
  `autoPublish` goes: there is no "approved in chat, so it goes live". Publish is Jason's, on the site manager.
- **Releases stack.** Release #57 can follow #56 onto staging before #56 is published. A file a later release changes
  again shows "replaced by #57". Staging is always "live plus everything waiting".
- **Staging against live** (a new panel in the site manager): what staging has that live doesn't: releases, files,
  migrations, data changes (counts by kind), settings, and held effects. Plus what live has that staging hasn't caught up
  to yet, and the conflicts (§3).
- **Needs changes:** Jason's note on a release (UI, UX, Functional, or a Note pinned to a page) goes back in the release's
  record (`deliveries/releases/`) and its Pulse case, where the building session picks it up. The fix comes as the next
  release onto staging, on top. Nothing is taken off staging to fix it.
- **Urgent fixes keep their own path:** one release straight to live with a reason (as today). Catch-up (§3) then brings
  it to staging.

### 3. Keeping staging a mirror: catch-up from live, staging's work kept

Live keeps changing (visitors, owners, orders). Instead of the weekly copy over staging, staging **catches up**:

- **When:** every night (02:00 Arizona time, outside the release window), on **Catch up now**, and just before every
  Publish preview.
- **The common base.** For every row, staging keeps two fingerprints taken when the two sides last matched: live's row
  and staging's row (`stg_base`: realm, table, key, the two hashes). Two, because staging's copy of a person's row is
  stripped and never equals live's. A row has changed on a side when its fingerprint there no longer matches the base;
  it was added when the base has no key for it; removed when the base has it and the side doesn't.
- **What staging changed, field by field:** triggers on staging's tables (staging only, never on live) keep the row as
  it was the first time staging changed it since the base (`stg_changes`). That copy is the base for a three-way merge.
  Catch-up's own writes don't count (they run with a flag the triggers skip). If Bluehost doesn't allow triggers, the
  fallback asks about every differing field on a row both sides changed: more questions, never a silent choice.
- **Catch-up applies live's changes to staging** for rows staging didn't touch: added, changed, removed. People's details
  are replaced on the way in, the same as the strip today, with one salt for staging's life, so a person keeps the same
  stand-in. Test accounts keep theirs. Then those rows' base moves on.
- **Rows both sides changed** are not touched. They go on the conflicts list at once (not only at Publish), with base,
  staging and live side by side.
- **Files:** live's files change only by releases. A file an urgent release changed on live, and staging didn't, is
  copied to staging. If staging also changed it, that is a code conflict: Claude builds one release that has both.
- **Starting over** stays, only as a button (**Start staging over**), never on a schedule. It lists what staging would
  lose and offers Publish first.

**Every table has a class** (`app/lib/staging-tables.php`, one register; a migration that makes a table names its class
in the same release, and the zip check blocks one that doesn't; an unlisted table is treated as *follows live*):

| Class | What | Catch-up | Publish |
|---|---|---|---|
| **Records** | What people and staff make: listings, places, pages, storefronts, guides, journeys, posts, comments, workflows, categories, settings and switches | Live's changes come in | Staging's changes are merged in (§4) |
| **Money** | Orders, payments, invoices, subscriptions, payouts, gateway rows, promotions used | Live's come in (stripped) | Never carried: everything on staging is sandbox |
| **Follows live** | Bulk reference data (`zip_coordinates`, the OpenStreetMap layer, NPI, IRS) and anything a job recomputes (search index, scores, confidence, counts, caches) | Copied from live | Never carried; live's own jobs redo it |
| **Staging's own** | The held ledger, `staging_s3`, its job logs, its sessions, views and taps from staging visits | Kept | Never carried |
| **Never copied** | Sign-in secrets, devices, the vault, one-time codes, page cache, live's release record | Empty on staging | Never carried |

Links between rows are in the same register (`listing_id` → listings, `kind:id` references like `listing:123`), so a row
can be carried with what it points to and its new id (§4). A table with no primary key can't be merged row by row: it is
*follows live* until it gets one.

### 4. Publish is a merge: live and staging balanced, nothing lost

**What is carried:** every Records row staging added, changed or removed since the base, the settings among them,
staging's uploaded files (`staging/<key>` to `<key>`), and the code and migrations of every release on staging.

**The three-way merge, per row and field** (base, staging, live):

| Staging | Live | Result |
|---|---|---|
| changed | unchanged | staging's row |
| unchanged | changed | live's row stays |
| changed | changed, different fields | both: each side's fields |
| changed | changed, the same field, different values | **a conflict**: Jason chooses |
| added | — | added to live, with a live id |
| removed | unchanged | removed on live |
| removed | changed | a conflict |
| changed | removed | a conflict |
| both added rows that clash on a unique key (the same slug, the same email) | | a conflict |

**New ids.** Staging gives its new rows ids from a high block (each table's next id set to 2,000,000,000 at every
catch-up; live never gets near it). At Publish each new row is inserted on live with a new live id, and a remap
(`stg_id` → `live_id`) rewrites every link to it in the other carried rows and in `kind:id` references, parents before
children. Codes people see (GO codes, short codes, slugs) keep their text; a clash is a conflict. After Publish, staging
renumbers its own copies the same way, so the two match again.

**Conflicts:** shown side by side, base · staging · live, with who changed each and when. Jason picks **Keep live**,
**Use staging**, or per field, or types the value. Nothing is overwritten silently, and Publish doesn't open while one
is open. **Keep live for the rest** settles what's left in one go.

**Never carried:**
- people's stripped details (staging's stand-ins: a change staging made to a stripped field is shown as not carried);
- test accounts and everything they own (`users.is_test`, `check+…@traversence.com`, the staff logins Test accounts
  made), "TEST · …" orders and every Money row ("No test moves real money");
- sessions, secrets, devices, `.env` (staging keeps its own), staging's switches (`staging_ai`, `staging_crawler`, …);
- staging's logs, counts and views (test traffic);
- held outside effects. **An email held on staging is never sent by Publish.** Records that held something are flagged
  in the preview, and after Publish a **To send now** list offers each on live, one at a time, for Jason to send or drop.

**Code, migrations and data, one ordered step, run on the server** (the CLI job, a step at a time like the refresh, so
the page can close; an email when it's done):

1. **Check:** the preview is fresh (under 15 minutes), no conflict is open, staging's checks passed, there's room on disk.
2. **Copies:** every migration's tables (0162) and every live row the merge will change or remove, into the publish's
   journal (`pub_journal`: the row before, and the ids it adds).
3. **Migrations,** oldest first (0162: add-only, copied first; a failure stops here with nothing else changed).
4. **Data,** a batch at a time, per database, each batch in one transaction, each row written only if live's row still
   matches what the preview saw. A row that changed in between becomes a late conflict, left as live has it, for Jason
   after. Live stays open the whole time.
5. **Files,** as Publish does today (the previous copy of each kept). Then staging's files in the store.
6. **Checks on live,** then switches step 1 (staff and test accounts) where a release has one.
7. **Staging matches live again:** the remap applied there, the base taken again.

**Roll back** is for the whole publish, for 14 days (as 0162's copies): files back, added rows removed, changed rows put
back, removed rows put back, each only if live hasn't changed that row since (else it's listed for Jason). Migrations
stay (add-only; what they made is tracked as leftovers, 0162).

**A failed publish halfway** stops at the step it was on, with the reason. The site manager offers **Carry on** (from
that step) or **Roll back** (what was done), and ask our team. Each step is in the journal, so nothing is half-known.

**The preview** (before every Publish, and any time): what changes on live, by kind (pages, listings, settings,
workflows, files, migrations), counts for added, changed, removed; each row on a click; the conflicts; what isn't carried
and why; held effects; how long it should take; and when it was worked out.

### 5. What Jason does (the site manager, decisions/0159 §5)

1. **Open on staging** from the live admin. The site manager says: "Staging · 3 releases waiting · 41 changes · 2 held ·
   1 conflict · caught up 2:10 AM MST".
2. **Work.** Use the site as on live. What's held shows its notice and lands in **Held**.
3. **Note what needs changing** (Note on the page, or Review → Needs changes). It goes to the building session.
4. **The fix arrives** as the next release on staging (Releases ▾ shows it, its checks, what it replaced). Keep working.
5. **Conflicts** (the badge): side by side, choose.
6. **Publish ▾ → Preview.** A spinner on the button while it's worked out, then the preview in the site manager's
   drawer.
7. **Publish** (asks once: "Publish staging to live?", yes **Publish**), or **Schedule** (inside the release window; the
   preview is done again at the time and stops if a new conflict appears). Progress shows step by step; the page can
   close; an email says when it's done or where it stopped.
8. **After:** "Published: 3 releases, 41 changes, 0 conflicts. Checks on live passed." Then **To send now**, and
   **Roll back** for 14 days.

The admin's rules hold: no browser dialogs (`TvAsk`), a spinner on whatever is working, notices beside the button, every
stop with a way on (Carry on, Roll back, ask our team), times labeled in the zone they happened.

### 6. Risks and limits

- **Bluehost's MySQL 5.7:** no window functions or `WITH`; JSON functions are there (the before-copies). Triggers need
  the TRIGGER right on staging's databases (cPanel normally gives it; Release 3 checks first, with the fallback in §3). A
  single-statement trigger needs no `DELIMITER`. No binlog or replication on shared hosting, which is why fingerprints.
- **PHP 8.0:** none of the newer forms (CLAUDE.md).
- **The four databases (0110):** each realm is merged by its own login, as the refresh copies today: no login crosses a
  wall. A transaction can't span two databases, so each batch is per database and the journal makes the whole publish
  undoable.
- **Size and time:** staging already doubles the databases; the base adds a key and two hashes per row. Fingerprints run
  in chunks of a few thousand rows by key, at night, on the command line (no web time limit). Bulk tables follow live, so
  they're copied when they change, not compared row by row.
- **Bluehost's limits:** CPU and IO are throttled per account (live and staging share them), connections are capped,
  web requests time out. So: nightly work, small batches, a pause between them, the CLI job for everything long.
- **Jobs on both sides** make the same derived data twice: that's why derived tables follow live and are never carried
  (no false conflicts).
- **Cascades:** a delete the database cascades fires no trigger; the fingerprints catch it.
- **Shared IP address:** staging's calls to free services are paced inside live's.
- **Until Release 5,** work done on staging (data) isn't carried: the site manager says so on every page, and only
  releases' code and migrations publish.

### 7. Build order (releases)

1. **Safe mode closed, nothing by itself.** The gate and the Held ledger; the remaining live keys out of staging's
   `.env`; sandbox webhooks to staging; staging's scheduled jobs; `autoPublish` removed (every build waits on staging);
   the weekly refresh stopped (Start staging over stays as the button).
2. **The table register** (`staging-tables.php`) with its zip check rule, the link register, and **Staging against live**
   for code: releases stack, and Publish takes every release on staging together (files and migrations).
3. **The base and catch-up:** fingerprints, staging's triggers, the high id block, nightly and Catch up now, the strip
   on the way in, the conflicts list.
4. **The preview and conflicts:** the side-by-side choice, Keep live for the rest.
5. **Publish as a merge:** the journal, the remap, batches with the still-matches check, staging's files, the steps run
   by the server, Carry on.
6. **Roll back the whole publish, To send now,** and the old refresh code removed.

## Choices for Jason

1. **All of staging at once, or pick releases?** Recommended: all at once (staging is one state; picking breaks data
   that depends on a release), with the urgent single-release path kept.
2. **Live stays open during Publish** (late conflicts listed after), or a few minutes read-only? Recommended: open.
3. **Catch-up nightly, on demand and before every preview,** or every 15 minutes? Recommended: nightly and on demand
   (Bluehost's throttling).
4. **Staging's own views, taps and counts are never carried** (test traffic). Confirm.

## Consequences

- Staging becomes where the work happens; Publish is the one door into live, for code and data together.
- On acceptance: `decisions/0159`'s auto-publish note and `0145`'s weekly refresh are replaced (both carry a note), and
  CLAUDE.md's 3-way sync lines ("publishes it by itself when they pass") change in the same pass.
- Every new table needs a class, and every new outside service a staging answer.
- More moving parts on a shared host: the build order puts safety and "nothing by itself" first, so they hold even if
  the merge takes longer.
