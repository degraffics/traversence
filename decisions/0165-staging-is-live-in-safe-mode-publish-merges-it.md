# ADR 0165: Staging is the live site in safe mode; Publish merges it into live

**Status:** Accepted (Jason, 2026-11-15, through the session manager; his answers to the five choices are under "Choices for Jason"). **Built 2026-11-15 as one release** (release #56) covering all six steps of §7 (`handoffs/0165-staging.md`: what's where, what ships off, the tests in `tools/staging-tests/`, and what's left for later).
Once accepted it **replaces** `decisions/0159`'s 2026-11-13 progress note that builds sent through git go live by
themselves (`Releases::autoPublish`), and `decisions/0145`'s "refreshed from live" (the weekly copy over staging,
`staging_tick`). It keeps `0145`'s staging rules (held email, sandbox payments, not indexed, behind a password), `0159` §5
(the site manager), `0162` (migrations first, copied first) and `0110` (the walls between the databases).
Built next, before Release B, so B is published this way.
Checked the same day against an outside spec Jason shared ("Two-Tier Platform & Content Release System"): what it adds
is folded in, and §8 says where this decision differs from it and why.

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

**One gate, built from what's there.** The site already asks before every outside call: `ServiceHealth::allowed()` per
host (with `Automation::hostOff()`, the master and per-service switches of 0163 §9), `AiClient` for the AI providers,
`Staging::allow()` for the staging switches, `Staging::holdMail()` in Mailer and SES, `QuickBooksPay::testHere()` and
`guardReal()` for money. Staging's answer goes into those same places (our calls are curl, so there's no middleware to
add): on live they say "go"; on staging **hold**, **sandbox** or **allow** (a switch turned on for a test). What's held
goes in one ledger, `staging_held` (it takes in `staging_mail`). The service register of `decisions/0163` names every
service; each one's staging answer is a column there, so a new service can't be added without one. A service with no
answer is held. Held items can be **replayed** only to somewhere safe: an email with **Send to me** (to the staff
member's own address, marked as from staging), a webhook to the sandbox's address. Never to the real recipient.

**Payments refuse to start on staging if a live key is present** in its `.env` (a live QuickBooks, Stripe or Square
key): the site manager says which, and payments stay off until it's gone.

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

> **Note 2026-11-17 (Jason: "TEST MODE: ACTIVE (STAGE REPLIES ONLY) does this work?", yes):** a held call's notice now
> starts **TEST MODE: ACTIVE (STAGE REPLIES ONLY)**, then which service wasn't called and why (held, sandbox only,
> paced), then "Nothing was sent." (`StagingGate::said()`), e.g. "TEST MODE: ACTIVE (STAGE REPLIES ONLY) · Name.com wasn't
> called (held on staging). Nothing was sent." The ledger keeps its own reason text. The same release gated the 36 calls
> that skipped the gate, and AWS's staging answer ("allow") now covers the file store only: Textract, SES and SNS are held.

**Added 2026-11-15 after Gemini's review (Jason: "GO"):**
- **A raw outside call that skips the gates.** Our calls use curl directly, and Bluehost can't block the network for
  staging alone. So the zip check refuses any new outside call that doesn't go through the outside-services gate
  (extending rule R12). Staging also logs, and shows in the safe-mode bar, any call that reached the outside world
  without a gate, so a leak is seen at once.
- **Third-party scripts in pages.** Staging sends a Content Security Policy header that lets load only what pages need
  (map tiles, our fonts, our own files and the store) and blocks anything else. Nothing that tracks or reports out can
  run there.

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

> **Amended 2026-11-15 (Jason: "the live site should push updated data to staging at the time of an action"):** the
> 15-minute quick pass is replaced by a **push at the moment of each change on live**. Each live write is noted in a
> small outbox on live (in the database that owns the table, 0110), and a job sends it to staging within a minute. It
> is **not** sent by triggers that write across databases (Gemini's review: an outbox plus a job keeps live's speed
> unaffected on Bluehost). The outbox row is tiny, written in the same database as the change. Staging applies the
> change as below. The **nightly full pass stays as the safety net**: it compares fingerprints and
> catches a failed push, a cascaded delete, or a change made outside the code. Ids are still needed (choice 3): both
> sides create rows, so instant push makes a clash sooner, not impossible.


- **When, in two speeds** (Bluehost throttles CPU and IO per account, and live and staging share it):
  - **every 15 minutes, the quick pass:** tables that keep an `updated_at` (set by the database on every change) are
    read only for rows changed since the last pass, and small tables' keys are compared for rows removed;
  - **every night (02:00 Arizona time, outside the release window), the full pass:** every row's fingerprint, which
    catches removals in big tables, tables with no `updated_at`, and deletes the database cascaded;
  - and on **Catch up now**, and just before every Publish preview.
  The register (below) records per table whether it has a primary key (needed), an `updated_at` (for the quick pass)
  and its size. A table with no `updated_at` waits for the night.
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

**Work data and test data.** Jason wants "what the stage has become and the data it has generated" carried, so a row
made on staging isn't test data just because it was made there. Each change staging's triggers record also records
who made it (the site sets `@stg_actor` and `@stg_test` on staging's connection when someone signs in; a job sets its
name):
- **Work data** (carried and merged): Records changed by a staff login that isn't a test account, and by staging's jobs
  on Records tables.
- **Test data** (never carried): anything a test account made or owns (`users.is_test`, `check+…@traversence.com`,
  the staff logins Test accounts made), every Money row, the held ledger, the stripped values.
- In the preview, Jason can mark any carried row as test (left out) or a test row as work (carried), one at a time.

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
| changed | changed, the same field to the same value | nothing to do |
| changed | changed, the same field, different values | **a conflict**: Jason chooses |
| added | — | added to live, with a live id |
| removed | unchanged | removed on live |
| removed | changed | a conflict |
| changed | removed | a conflict |
| both added rows that clash on a unique key (the same slug, the same email) | | a conflict |

**New ids: live keeps the true numbers; staging gives way** (Jason, 2026-11-15: "the live site holds the true data and
the stage adjusts to it as needed"; reviewed with Gemini the same day). Live's numbering is never changed.
- **On staging, at a clash.** When live pushes a row whose id staging has already used for its own new row, staging
  **moves its own row** to the next free id, rewrites every reference to it (from the register below), and then
  takes live's row. This is logged in `stg_renumber` (realm, table, old id, new id, when).
- **At Publish.** Each row made on staging is inserted on live and **takes its id from live**. A map (`stg_id` →
  `live_id`) rewrites every reference in the carried rows, parents before children, and the preview lists every
  renumbering.
- **The register of record numbers** (`stg_id_refs`, built from the live code and checked before every catch-up apply
  and every publish) names every place an id is stored:
  - columns, including `*_id` and the ones not named that way;
  - ids inside **JSON and settings blobs** (page and widget layouts, settings, saved filters), by path;
  - **`kind:id` strings** (`listing:123`, `user:45`) in notes, logs, activity and Pulse keys;
  - **links in text** (`/listing/123`, `?id=123`) in pages, descriptions and messages;
  - file and store keys that carry an id.

  **Caches are cleared** after any renumber or publish. **Sessions are never carried.**
- **An unknown reference stops it.** A field that holds what looks like a record number but isn't in the register
  stops the publish (and the catch-up for that row) with a clear message naming the table and field. It's added to the
  register before going on, so a link is never left pointing at the wrong record.
- Either way, codes people see (GO codes, short codes, slugs) keep their text, and a clash on a unique key is a conflict.

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
   journal (`pub_journal`: the row before, and the ids it adds). Each entry is written **before** its write (write-ahead), so the journal always
   knows more than live has done.
3. **Migrations,** oldest first (0162: add-only, copied first; a failure stops here with nothing else changed).
4. **Data,** a batch at a time (250 rows), per database, each batch in one transaction, each row written only if
   live's row still matches what the preview saw. A row that changed in between becomes a late conflict, left as live
   has it, for Jason after. Live stays open the whole time. (One transaction for the whole merge isn't possible: each
   database has its own login, 0110, and a transaction can't cross logins. The journal makes the whole of it undoable
   instead.)
5. **Files,** as Publish does today: each file is already waiting in the release's folder
   (`app/storage/releases/<id>/new/`), its previous copy kept, and it goes live by a rename, which is all-at-once for
   that file. A few hundred renames take seconds. Then staging's files in the store (100 a step).
6. **Checks on live,** then switches step 1 (staff and test accounts) where a release has one.
7. **Staging matches live again:** the base taken again (staging's renumber map applied there first).

**If a step fails while writing** (migrations aside, which 0162 keeps), what this publish wrote is undone at once from
the journal, by itself, **read backward** (last write first) with compensating writes from each row's before-image; an
undo that's interrupted resumes where it stopped. The site manager says where it stopped and why. **If a check on live fails afterwards,**
it's Jason's call, as today: Turn off, or Roll back.

**Roll back** is for the whole publish, for 14 days (as 0162's copies): files back, added rows removed, changed rows put
back, removed rows put back, each only if live hasn't changed that row since (else it's listed for Jason). Migrations
stay (add-only; what they made is tracked as leftovers, 0162).

**A publish that stops halfway for any other reason** (the server restarted, a time limit) stops at the step it was
on. The site manager offers **Carry on** (from that step) or **Roll back** (what was done), and ask our team. Each step
is in the journal, so nothing is half-known.

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

**Note (2026-11-15, releases #40, #59, #61, #62, #67):** the site manager bar sits fixed along the bottom of the
screen above everything, and reaches the live site past Bluehost's bot check (#59); it draws again after a name clash
in its script (#61). Staff stay staff on staging after the privacy strip (#40). **Check for builds** on the live
Releases page takes Claude's builds from git at once, and the docs repo's ping (`release-ping.yml` →
`/api/releases-pull.php`) has the site take a merged build within a minute, one taker at a time; an approved review
part folds to one line with Edit (#62; Jason: "need a way to push immediate fixes like this into production"). Withdraw
is in the bar's Publish menu for any release not yet live (#67).

### 6. Risks and limits

- **Bluehost's MySQL 5.7:** no `WITH`, `OVER()` or `JSON_TABLE` (the code check, R04, flags them); `JSON_OBJECT`
  is there (the before-copies). Session `SET` is allowed; nothing needs SUPER. Triggers need
  the TRIGGER right on staging's databases (cPanel normally gives it; Release 3 checks first, with the fallback in §3). A
  single-statement trigger needs no `DELIMITER`. No binlog or replication on shared hosting, which is why fingerprints.
- **PHP 8.0:** none of the newer forms (CLAUDE.md).
- **The four databases (0110), on each side:** main, admin, listings and users on live, and staging's copy of each.
  Every pass and every merge runs per pair, each by that realm's own login, as the refresh copies today: no login
  crosses a wall. The new tables (`stg_base`, `stg_changes`, `pub_journal`) go inside the databases staging already
  has, so the plan's database count doesn't change (setup checked there was room for staging's four through cPanel,
  `StagingSite::room()`).
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

1. **Safe mode closed, nothing by itself.** The gate and the Held ledger (with Send to me); the remaining live keys out
   of staging's `.env`, and payments refusing if one is there; sandbox webhooks to staging; staging's scheduled jobs;
   `autoPublish` removed (every build waits on staging); the weekly refresh stopped (Start staging over stays as the
   button); the zip check's rule against ungated outside calls and staging's log of them; the Content Security Policy.
2. **The table register** (`staging-tables.php`) with its zip check rule, **the register of record numbers**
   (`stg_id_refs`: columns, JSON paths, `kind:id` strings, links in text, store keys) and its check, and **Staging against live**
   for code: releases stack, and Publish takes every release on staging together (files and migrations).
3. **The base and catch-up:** fingerprints, staging's triggers, live's outbox and the push job, staging's renumbering
   at a clash, nightly and Catch up now, the strip
   on the way in, the conflicts list.
4. **The preview and conflicts:** the side-by-side choice, Keep live for the rest.
5. **Publish as a merge:** the write-ahead journal and backward undo, the id map (staging's new rows take live ids), batches with the still-matches check, staging's files, the steps run
   by the server, Carry on.
6. **Roll back the whole publish, To send now,** and the old refresh code removed.

### 8. Content edits stay live (0159), and where this differs from the outside spec

**The spec's Tier 1 is already decided:** `decisions/0159` §3 puts content and layout in the editor on live, with a
draft only the team sees, Publish now or on a schedule, a version per publish and Roll back to any of them, and its
rule between the layers (a part that needs new platform code is a code release, through staging). This decision
doesn't add a second content path. The editor's pages and versions are Records: an edit made on live comes to staging
by catch-up; one made on staging is merged at Publish like any other. Content belongs on live; staging is for work tied
to code being tried.

**Where this differs from the spec, and why:**
- **No new `APP_ENV` / `SAFE_MODE`:** staging is already marked by `STAGING=1` in its `.env` (`Staging::here()`).
- **No new interceptor layer:** the gates exist (§1); staging's answer goes into them.
- **No `is_test_data=1` on every row made on staging:** that would leave behind the work Jason wants carried. Work data
  and test data are told apart by who made the change (§3).
- **Ids:** the spec's odd/even is **not** taken. Live keeps its numbers, and staging renumbers through the register
  (Jason's choice, §4).
- **Catch-up:** every 15 minutes only for what's cheap to find (tables with `updated_at`, small tables' keys); the full
  comparison nightly, because the server's CPU is shared with live.
- **No maintenance lock and no single transaction:** live stays open, each row is written only if it still matches the
  preview, and a transaction can't cross the four databases' logins (0110). The journal gives the whole-publish undo
  instead. (A short lock is choice 2 below.)
- **No webroot symlink or folder swap:** releases write into `public_html/traversence/` file by file, the 3-way sync reads
  that folder, and `app/storage` (sessions, the releases' copies) and the uploads live inside it, so swapping the folder
  would mean copying the whole site each time. Each file is staged first and renamed into place, which is all-at-once
  per file and seconds for a release.
- **Rollback on a failed step is automatic** (as the spec says) for what Publish wrote; a failed check after Publish stays
  Jason's call, as today. Migrations are never undone (0162: add-only, copied first).
- Taken from the spec: Replay of held items to safe places, payments refusing with a live key present, no-op for the
  same change on both sides, the held count in the site manager, the dry run, 250 rows and 100 files a step.

## Choices for Jason

1. **All of staging at once, or pick releases?** Recommended: all at once (staging is one state; picking breaks data
   that depends on a release), with the urgent single-release path kept.
2. **Live stays open during Publish** (late conflicts listed after), or a short lock (visitors read, writes wait a few
   minutes, staff allowed in)? Recommended: open.
3. **New ids: live odd and staging even** (no remap; live's ids step by two from then on), or **a high block for
   staging and a remap at Publish** (live untouched, more that can go wrong)? Recommended: odd and even.
4. **Catch-up:** the quick pass every 15 minutes and the full pass nightly (recommended), or nightly only (lighter on
   Bluehost, staging up to a day behind).
5. **Staging's own views, taps and counts are never carried** (test traffic). Confirm.

**Jason's answers (2026-11-15):**
1. All of staging at once, with the urgent single-release path kept.
2. Live stays open during Publish; late conflicts are listed after.
3. **Live keeps the true numbers, and staging renumbers its own rows at a clash and at Publish** (his choice over odd
   and even), with the register of record numbers as a required part of the build (§4).
4. Changed: push at the time of the action, plus the nightly full pass (the amendment in §3).
5. Confirmed: staging's own views, taps and counts are never carried.

**Jason, 2026-11-15, at the build ("Go"):** the release goes to staging for his review; and live's outbox is filled by
one-statement triggers on live's tables, in the database that owns each table (they only note the table, key and kind of
change), added only when Keep staging caught up is switched on and taken off when it's switched off. That is the one
exception to §3's "triggers on staging only".

**Correction (Jason, 2026-11-15, after the build):** "I expected claude built and approved here builds to still go live.
The publish feature is for me to push the changes I make from within staging." So §2's "nothing goes live by itself" is
narrowed: Claude's builds from git, approved in chat, still go live by themselves once their checks on staging pass
(`Releases::autoPublish`, 0159's 2026-11-13 note, kept); one sent for review waits for the review and its own Publish.
**Publish staging** (§4, §5) carries what's made on staging: its data, merged, and any zip uploaded there (the site
manager's Upload). Everything else here stands. The first release (#702's zip) shipped the narrower §2; the next
release puts this right (`handoffs/0165-staging.md`): release #57, live 2026-11-15.

## Consequences

- Staging becomes where the work happens; Publish is the one door into live, for code and data together.
- On acceptance: `decisions/0159`'s auto-publish note and `0145`'s weekly refresh are replaced (both carry a note), and
  CLAUDE.md's 3-way sync lines ("publishes it by itself when they pass") change in the same pass.
- Every new table needs a class, and every new outside service a staging answer.
- More moving parts on a shared host: the build order puts safety and "nothing by itself" first, so they hold even if
  the merge takes longer.

## Note (Jason, 2026-11-26): conflicts are the team's, not the Founder's

Jason, on the suggestion that a conflict nobody made should settle itself: "this is the project manager
responsibility isn't it?" Under decisions/0180, settling staging conflicts belongs to the team.
- **A conflict with no person's change behind it** settles itself as **Keep live**. That's a row staging's own jobs changed, with nothing recorded. The record's history says "Kept live by itself: staging's change wasn't recorded, so no person made it". This turns on the switch the conflict fix ships with.
- **A conflict where a person changed the row on staging** becomes a Work queue ticket in the Operations &
  Reconciliation Manager's lane (0180 role 23, under the IT Manager; the Project Manager sequences it). It never comes
  to the Founder as a question. It reaches him only if it meets a Tier 5 trigger (0180 §3).
