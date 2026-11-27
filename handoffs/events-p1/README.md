# Events P1: other businesses take part in an event (decisions/0134's note #1589)

Built 2026-11-27 on E1 (`handoffs/events-e1/`) and E2 (`handoffs/events-e2/`); sends after them. **The offer follows the CMO's ruling** (0134's note #1594): one of the business's own discount codes (BizCodes, decisions/0121, Discount codes), a short line in its own words (≤140 characters, no links), or both. P2 (`handoffs/events-p2/`) shows them on the event page.

## What it does
- **Host, in Business portal → Events:** under each event, "Taking part" lists the businesses and their state; **Invite a business** is a type-ahead (name and town). Remove asks first and offers Undo (back to its earlier state).
- **Invited business, in its own portal → Events:** an Invitations block: Take part (with an optional line and/or one of its codes that's switched on and not ended; none yet links to Discount codes), Decline; then Save offer, Leave (asks first) and "Undo: take part again".
- **Codes are the business's walled data** (0110): Events opens the business realm for them (`router.php`, `api.php`); the code's words and last day are kept with the participation, and checked again each time its team opens Events. A code switched off, ended or used up comes off, said once ("Code taken off:", with Discount codes' link); a changed one takes its new words.
- **An unclaimed listing** is recorded as waiting and isn't told (no one to tell; Team caps unclaimed public listings at view).
- Notice `biz.event_invite` (Notify kind, business channel, catalog perm). Every move goes to `event_history` (`part:<move>`) in words.
- Words kept true: until P2 is live, the business shows on the event's page "with the next update"; P2 changes them.

## Files
`app/lib/Events.php`, `app/business/events.php`, `app/lib/Notify.php`, `app/lib/staging-tables.php` (RECORDS event_participants, REFS → events), `app/ui/faq.md` (1 entry), `website/business/api.php` (array answers for find; opens the realm for a chosen code), `website/business/router.php` (opens the realm on Events), migration `app/migrations/2026-12-09_event_participants.sql`.

## Tests
- `tools/event-tests/events-parts.php <app> <e1 events.sql> <p1 sql>`: 22/22 on MariaDB.
- `tools/event-tests/portal-parts.cjs <shots>`: 51/51 rendered at 1280 and 390, two owners (invite; take part with a line and a code, only live codes offered; the host sees both; the code switched off comes off with its notice, said once; remove/Undo; leave/Undo; no sideways scroll; no script errors). The harness needs a listings realm (`tvl`, DB_LISTINGS_*). Screens in `screens/`.


## Design fixes (the Design reviewer, 5c391af and 94c92b7) and the live code (the session manager, 2026-11-27)
- One filled button per view (brand.md §4): row moves (Publish, Put back on, Take part) are outlined; New event is the primary.
- Visible labels: "Your offer (Optional, in your own words)" with the example as a hint, and "Discount code (Optional, one of yours)".
- Taking part rows aligned with their heading, the offer and code under the name, Remove and Undo sized like row buttons.
- The code reads **live** when drawn (`Events::liveCode`): from the business's walled data through the same gate MarketCheckout::writeOrder
  uses (0110: the listings login on its own connection, `@tv_tenant` set to that business, read only through `b_biz_codes`). Shown only while on,
  in its dates and not used up, as "code SHOW10 until Dec 31"; otherwise the owner's line alone. Unreachable: nothing shown.
  The stored snapshot and the "Code taken off:" notice in the owner's Events stay (approved). The earlier gap note is replaced by this.
- Editorial (12) reads the pilot's offer lines by hand before Dec 15 (the session manager); its queue is logged not-before-Jan-1.

## Before live: the CTO's Tier 4 on `Events::liveCode` (the session manager, 2026-11-27: a data wall, 0110)
Four things, each tested in `tools/event-tests/live-code.php` (17/17 on MariaDB, a realm built like BusinessData's):
the read is limited to the one code that business attached (`WHERE code = ? LIMIT 1` through `b_biz_codes` only; same-named
codes of two businesses each read only their own); it's read only (forty reads, the server's write counters unchanged; no
write in its code); `@tv_tenant` is reset in `finally` after the read, also when it fails, and the next query sees no
business's rows; only `[code, ends]` comes back (no counts, notes or other codes; the P2 page test checks the HTML too).
Also: the guest's offer form at 390 (one-line field, the code select full width, Save offer and Leave below; tested).
The sticky bars drawn part way down an earlier full-page screenshot were the screenshot's stitching, not the page.

**The code behind the `event_codes` switch** (off until the Founder's ruling on 0110's note); the Tier 4 review and QA in `handoffs/events-p2/tier4-review.md`. Files added: `app/lib/BusinessData.php` (publicCode), `app/lib/Switches.php` (event_codes, the Founder's).
