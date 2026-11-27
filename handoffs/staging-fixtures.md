# Staging fixtures: a test item to buy and a test group to join, on staging only

*Data & Content, for the PM (8); the session manager's order OK 2026-11-27 20:10 UTC (gate 2's "Buy on the Marketplace"
and "Join a group", and gate 5's sandbox ticket test). Review: the IT Manager's (11) Tier 4 with an independent check
(staging's pipeline, decisions/0165). Slot: after the writeOrder fix and task-seed-4. Design read on Sync 2026-11-27 19:50 UTC.*

## Why the staging run is stuck (read-only, 20:04 UTC run)

- **Buy on the Marketplace:** `tools/page-audit/paths-staging.mjs:337` wants a visible Marketplace item whose link says
  "test". The only test business, "Traversence Test Listing" (`TestAccounts::LISTING`), is suppressed on purpose
  (`app/lib/TestAccounts.php:156`, "never public, never in search"), and the Marketplace hides every suppressed
  listing's items (`app/lib/MarketBiz.php:471`, `e.is_suppressed = 0`).
- **Join a group:** live has no public community groups, so staging's copy has none; `TestAccounts::makeAll` makes a
  Test Steward ("Stewarding a test community group") but no group.
- Staging is a copy of live, made again at every refresh (`StagingSite::start`, :793; steps copy → tidy → strip → base →
  finish), so a row written on staging by hand is gone at the next refresh, and a migration would also run on live.

## The design: one step in staging's own copy, after the strip

`StagingSite::finish()` (:1358) already writes staging-only settings into staging's databases after the copy (the
switches in `Staging::ON_AFTER_COPY`). The fixtures join it as `StagingSite::fixtures()`, called from `finish()`:

1. **Refuses unless it writes into staging:** every statement names staging's own database (`self::stg($setup, $realm)`)
   and the call throws when that name equals live's (`self::liveDbs()[$realm][0]`) or when `Staging::here()` says it's
   running on staging itself (the refresh runs from live's side only, :795).
2. **Marked as test, so Publish never carries it:** the connection runs `SET @stg_test = 1, @stg_actor = 'job:fixtures'`
   before the writes (after `base` put staging's triggers on), so staging's change record tags them as test data and
   Publish leaves them out (`StagingPublish.php:110`, "Test data (made by a test account)"). Reset after.
3. **The test listing, public on staging only:** `entities.is_suppressed = 0` for `TestAccounts::LISTING`'s id (from
   TestAccounts' own record), only in staging's main database.
4. **One "TEST · Community night seats" item:** kind `ticket` (`ItemKinds.php`, Event tickets: a date and time, a
   capacity), a sandbox price, published the way `MarketBiz::publish()` (:234) writes its public copy: the walled
   catalog row in staging's listings database, then its published row and version. Written by the same columns publish
   writes, checked against `ItemKinds::ready()` for a ticket's date. guardReal and testHere stay in force at checkout.
5. **One public "TEST · Site checks group":** `community_groups` (open join) with Test Steward as its steward in
   `community_group_members`, the rows `Community::create()` writes (:346, :353), minus its Verification check
   (`MemberVerification::require`, :315), which a fixture can't pass.
6. **Each fixture written once a refresh,** found by its name first (never a second copy), and said in the refresh log
   ("Fixtures: the test listing shown, 1 test item, 1 test group").

## Open, before building (from the code, not invented)

- **Buy now needs more than a visible item:** `MarketBiz::buttonsFor` (:120) gives Buy only with a set price, live
  checkout (`checkoutLive`, :107, `market_checkout.live`) and a plan that opens it ("Buy now (Core and up, through its
  own gateway)", :18–19), and publish asks for the Seller Agreement (:254). The test listing is on Free. So the item
  will show (the run moves past "no test business item") but stops at "no Buy now" until the test business has a
  sandbox gateway (the launch plan's F.4, paths-staging.mjs:332). Whether the fixture also sets the test listing to
  Core on staging is for the IT Manager (11) and the CFO (4) to say; the gateway's test keys stay F.4's.
- **The walled catalog's columns** (listings database, `Scope`) are read from staging's copy at build time; the zip
  check's R14 needs no new table (nothing new is made).

## Tests (to build)

`tools/staging-tests/fixtures.php` on MariaDB, two databases standing for live and staging:
- writes only into staging's database; refuses when the target is live's or when run on staging;
- the listing shown, one item, one group, each once; a second refresh re-applies them after a fresh copy;
- the rows carry `@stg_test = 1` in the change record, and a Publish preview leaves them out;
- the market browse query (`MarketBiz::browse`) finds the item on staging; live's copy has nothing new.
