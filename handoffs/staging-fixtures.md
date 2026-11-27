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
- Staging is a copy of live, made again at every refresh (`StagingSite::start`, :794; steps copy → tidy → strip → base →
  finish), so a row written on staging by hand is gone at the next refresh, and a migration would also run on live.

## The design: one step in staging's own copy, after the strip

`StagingSite::finish()` (:1358) already writes staging-only settings into staging's databases after the copy (the
switches in `Staging::ON_AFTER_COPY`). The fixtures join it as `StagingSite::fixtures()`, called from `finish()`:

1. **Refuses unless it writes into staging:** every statement names staging's own database (`self::stg($setup, $realm)`)
   and the call throws when that name equals live's (`self::liveDbs()[$realm][0]`) or when `Staging::here()` says it's
   running on staging itself (the refresh runs from live's side only, :796).
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

## Both ways the theater might sell (the session manager, 2026-11-27 20:12: (a), with the direct-pay check)

**Read-only, Sync 19:50:** a Free or Basic listing **can** show Buy now with direct pay today. Buy is
`'buy' => ['Buy now and Add to cart', 'manual_pay', …]` ("on every plan once its checkout is live (card from Core)",
`MarketBiz.php:36`), and `manual_pay` opens on Free (`Plan.php:18`, "Manual payments (Zelle, bank transfer, check, pay
on pickup)"). `allowed()` (MarketBiz.php:85–93) then needs only a live checkout (`checkoutLive`, :107); `setLive`
(`MarketCheckout.php:200–210`) takes a direct way alone ("card (Core and up…) or a direct way like Zelle"), after the
Seller Agreement and the INFORM Act details (:202–203). A direct-pay order goes to the business waiting for payment, the
buyer told how to pay (:391–394). So the pilot theater on Free can sell its seats "paid the theater's way" (0134's note):
**no pilot gap.**

So the fixtures make **two test businesses**, both on staging only, each with one "TEST · Community night seats" item:
- **The test listing on Core** (the session manager's (a)): card checkout through a sandbox gateway; its
  `market_checkout` row live; the card step waits for F.4's sandbox keys.
- **A second test listing on Free with direct pay:** "TEST · Direct-pay theater", its checkout live with one direct way
  (pay at pickup), its Seller Agreement and INFORM details recorded as test; Buy now then places an order awaiting
  payment, no money moving at all.
Both rows carry `@stg_test`; guardReal and testHere stay in force; Tier 4: the IT Manager (11) and the CFO (4) for the
checkout rows. The second listing is made by the fixture on staging only (never by TestAccounts on live).

## Tests (to build)

`tools/staging-tests/fixtures.php` on MariaDB, two databases standing for live and staging:
- writes only into staging's database; refuses when the target is live's or when run on staging;
- the listing shown, one item, one group, each once; a second refresh re-applies them after a fresh copy;
- the rows carry `@stg_test = 1` in the change record, and a Publish preview leaves them out;
- the market browse query (`MarketBiz::browse`) finds both items on staging; live's copy has nothing new;
- `MarketBiz::buttonsFor` gives Buy on both: card-ready on Core, direct pay on Free; a direct-pay order is awaiting payment.
