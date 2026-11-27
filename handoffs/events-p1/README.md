# Events P1: other businesses take part in an event (decisions/0134's note #1589)

Built 2026-11-27 on E1 (`handoffs/events-e1/`) and E2 (`handoffs/events-e2/`); sends after them. **The offer field is left open** for the CMO's ruling on what an "offer" is (0108 Promotions are staff-made; suggested: the business's own Discount codes, or a 140-character offer line as built here). P2 (offers and GO Codes on the event page, "Part of <event>" on listings, the trail per 0174) waits for that ruling.

## What it does
- **Host, in Business portal → Events:** under each event, "Taking part" lists the businesses and their state; **Invite a business** is a type-ahead (name and town). Remove asks first and offers Undo (back to its earlier state).
- **Invited business, in its own portal → Events:** an Invitations block: Take part (with an optional offer, ≤140 characters, no links), Decline; then Save offer, Leave (asks first) and "Undo: take part again".
- **An unclaimed listing** is recorded as waiting and isn't told (no one to tell; Team caps unclaimed public listings at view).
- Notice `biz.event_invite` (Notify kind, business channel, catalog perm). Every move goes to `event_history` (`part:<move>`) in words.
- Words kept true: the business shows on the event's page **with the next update** (P2).

## Files
`app/lib/Events.php`, `app/business/events.php`, `app/lib/Notify.php`, `app/lib/staging-tables.php` (RECORDS event_participants, REFS → events), `app/ui/faq.md` (1 entry), `website/business/api.php` (array answers for find), migration `app/migrations/2026-12-09_event_participants.sql`.

## Tests
- `tools/event-tests/events-parts.php <app> <e1 events.sql> <p1 sql>`: 22/22 on MariaDB.
- `tools/event-tests/portal-parts.cjs <shots>`: 26/26 rendered at 1280 and 390, two owners (invite, take part with offer, remove/Undo, leave/Undo, no sideways scroll, no script errors). Screens in `screens/`.
