# Events P2: businesses taking part, on the event page (decisions/0134's note #1594)

Built 2026-11-27 on P1 (`handoffs/events-p1/`; E1 and E2 before it). Sends after P1.

## What it shows
- **Event page → Taking part** (`website/event/index.php`): a heading over the cards (no outer card, as E2's "More at"), "Businesses <host> invited, in order of their names" (never ranked or paid, 0007). Each card to 0058 §11: the name the link to its listing, Link top right (`business:<id>`), round 30px icons with their names (Call, Directions, Website, Contact from ListingActions, counted with `data-out`/`data-e`; **Add to a trip**, 0174), its town, **Their offer:** its own line and/or code as written (the code read live, `Events::liveCode`: shown only while on and in its dates, as "code SHOW10 until Dec 31"; a listing nobody runs shows no offer), and its **GO Code** (the listing page's short address, ShortCodes::forPage).
- **Send the evening to Maps** (0174 §4, `tv_maps_attrs`): every card with Directions, then the venue last; places without Directions left out, said so.
- None on a cancelled event or one that's over.
- **Listing → Coming up here** (`website/listing/view.php`): "Part of <event>", with its venue, beside its own events, soonest first.
- Words made true: the portal's lines ("shows on the event's page, with your offer, its GO Code and Directions"), Take part's notice, and the FAQ (two entries).

## Files
`app/lib/Events.php` (publicParticipants, partOf, html's Part of), `website/event/index.php`, `website/listing/view.php`, `app/business/events.php`, `app/ui/faq.md`; P1's files carried as built.

## Tests
- `tools/event-tests/event-parts-page.cjs <shots>`: 51/51 rendered at 1280 and 390 (order by name, card anatomy, icons' names and size, offer and code, a switched-off and an ended code hidden (read live), an unclaimed listing's offer hidden, GO Code, Maps stops with the venue last and its chooser, Esc, the trip picker, no sideways scroll, edges at 390, none on a cancelled event, Part of on the listing, no script errors). Screens in `screens/`.
- The bench's `short_codes` lacked columns the live code writes (bench only; healed).

Design: approved but for the repeated discount (fixed: the code and its last day only, here and on the host's row).

## Before live: the CTO's Tier 4 on `Events::liveCode` (the session manager, 2026-11-27: a data wall, 0110)
Four things, each tested in `tools/event-tests/live-code.php` (17/17 on MariaDB, a realm built like BusinessData's):
the read is limited to the one code that business attached (`WHERE code = ? LIMIT 1` through `b_biz_codes` only; same-named
codes of two businesses each read only their own); it's read only (forty reads, the server's write counters unchanged; no
write in its code); `@tv_tenant` is reset in `finally` after the read, also when it fails, and the next query sees no
business's rows; only `[code, ends]` comes back (no counts, notes or other codes; the P2 page test checks the HTML too).
Also: the guest's offer form at 390 (one-line field, the code select full width, Save offer and Leave below; tested).
The sticky bars drawn part way down an earlier full-page screenshot were the screenshot's stitching, not the page.

**The code behind the `event_codes` switch** (off until the Founder's ruling on 0110's note); the Tier 4 review and QA in `handoffs/events-p2/tier4-review.md`. Files added: `app/lib/BusinessData.php` (publicCode), `app/lib/Switches.php` (event_codes, the Founder's).

## The code, published by its owner (Jason's ruling, decisions/0134's note #1605; replaces the live read and the `event_codes` switch)
Attaching a code (Take part, Save offer) writes its public terms, read on the owner's own walled connection, into the
participation: `offer_code`, `offer_code_starts`, `offer_code_ends`, `offer_code_min`. The event page and the host's row read
only that copy (`Events::publishedCode`), shown between its first and last day. `BizCodes::save` and `BizCodes::redeem`
(claimed, decisions/0161) update or withdraw the copies when the owner changes the code, switches it off or its last use
comes (`Events::codeChanged`, History `part:code_changed` / `part:code_off`); each visit to Events checks again
(`recheckCodes`, "Code taken off:"). `BusinessData::publicCode` and the `event_codes` switch are gone; files: `app/lib/BizCodes.php`
added to the build. Tests: `tools/event-tests/publish-code.php` 21/21 (P2's build), P1 rendered 51/51, P2 rendered 53/53, P1's
database test 22/22. Note found on the bench: Discount codes are Basic Partner and up (Plan), so a Free business has no codes to offer.

## The pilot's scope (Jason, 2026-11-27, decisions/0134's note #1606): no codes. **This replaces the code sections above.**
Only the host's event tickets sell. A participant's card shows its name, its owner's own offer line (the deal given in
person at its own place) and its GO Code to its location (the listing's Share code, Directions, Send to Maps through the
trail). Every discount-code piece is out: the code select and its notices, `offer_code*` columns, `codeChoices`,
`codeChanged`, `publishedCode`, `recheckCodes`, `BusinessData::publicCode`, the `event_codes` switch and the BizCodes hook
(its claims released). Nothing reads a participant's business data; P1 no longer changes `website/business/router.php`.
Tests: P2 rendered 45/45. The CTO's Tier 4 condition is moot (no walled read); a fresh QA check is recorded in
`handoffs/events-p2/tier4-review.md`.
