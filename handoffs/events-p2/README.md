# Events P2: businesses taking part, on the event page (decisions/0134's note #1594)

Built 2026-11-27 on P1 (`handoffs/events-p1/`; E1 and E2 before it). Sends after P1.

## What it shows
- **Event page → Taking part** (`website/event/index.php`): a heading over the cards (no outer card, as E2's "More at"), "Businesses <host> invited, in order of their names" (never ranked or paid, 0007). Each card to 0058 §11: the name the link to its listing, Link top right (`business:<id>`), round 30px icons with their names (Call, Directions, Website, Contact from ListingActions, counted with `data-out`/`data-e`; **Add to a trip**, 0174), its town, **Their offer:** its own line and/or code as written (a code past its last day isn't shown; a listing nobody runs shows no offer), and its **GO Code** (the listing page's short address, ShortCodes::forPage).
- **Send the evening to Maps** (0174 §4, `tv_maps_attrs`): every card with Directions, then the venue last; places without Directions left out, said so.
- None on a cancelled event or one that's over.
- **Listing → Coming up here** (`website/listing/view.php`): "Part of <event>", with its venue, beside its own events, soonest first.
- Words made true: the portal's lines ("shows on the event's page, with your offer, its GO Code and Directions"), Take part's notice, and the FAQ (two entries).

## Files
`app/lib/Events.php` (publicParticipants, partOf, html's Part of), `website/event/index.php`, `website/listing/view.php`, `app/business/events.php`, `app/ui/faq.md`; P1's files carried as built.

## Tests
- `tools/event-tests/event-parts-page.cjs <shots>`: 43/43 rendered at 1280 and 390 (order by name, card anatomy, icons' names and size, offer and code, an ended code hidden, an unclaimed listing's offer hidden, GO Code, Maps stops with the venue last and its chooser, Esc, the trip picker, no sideways scroll, edges at 390, none on a cancelled event, Part of on the listing, no script errors). Screens in `screens/`.
- The bench's `short_codes` lacked columns the live code writes (bench only; healed).
