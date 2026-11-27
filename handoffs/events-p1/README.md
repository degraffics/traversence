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
- `tools/event-tests/portal-parts.cjs <shots>`: 34/34 rendered at 1280 and 390, two owners (invite; take part with a line and a code, only live codes offered; the host sees both; the code switched off comes off with its notice, said once; remove/Undo; leave/Undo; no sideways scroll; no script errors). The harness needs a listings realm (`tvl`, DB_LISTINGS_*). Screens in `screens/`.

## Gaps (flagged, not built)
- **A code switched off in Discount codes** stays on the event page until the business next opens Events, or its last day passes. Suggest a change to 0121's Discount codes: on Save, take the code off its events at once (one line in the codes tool's save, `app/admin/tools.php`, a shared file, so its own release).
- **Editorial (12)'s check for a misleading offer** has no queue yet: the line is shown as written the moment it's saved. Suggest: new or changed offer lines go to Editorial's existing review list as information, with Hide in place.
