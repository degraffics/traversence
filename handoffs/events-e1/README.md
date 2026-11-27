# Events E1: the store and the host form (decisions/0134's minimum, the note of 2026-11-27, option (a)). Built, not sent.

**Built on:** Sync 16:23 UTC. **Slot:** after C.4, place-list, C.8, C.5a/b and M-sections (about Dec 5). E2 follows (the /event/ page, listing and guide hook); both are needed live by Dec 10–12 for the Hometown theater's community night.

## Files
- **New:** app/lib/Events.php, app/business/events.php, app/migrations/2026-12-05_events.sql (events, event_history; creates only).
- **Changed:**
  - app/business/portal.php: the Events module, in the rail.
  - website/business/router.php: its page.
  - website/business/api.php: its actions.
  - app/lib/upload-targets.php: event_photo.
  - app/lib/staging-tables.php: RECORDS events and event_history; REFS ticket_item_id and event_id.
  - app/ui/faq.md: two entries.

## Rules, each with its source
- **Who:** a claimed listing; a role with Catalog & content to edit (Team::can, which holds an unclaimed public listing at view). Source: 0134 §2.
- **Kinds:** 0134 §3's list exactly. A film night is Arts. Adding "Film" would be a suggestion for the Founder, not done here.
- **Free:** no price fields. A ticket link only to the host's own published Event tickets item (ItemKinds 'ticket' or legacy 'class'). Source: the note.
- **No links, no "pay at the door" or box office notes;** showtimes are information only. Source: 0134 §3 and the note.
- **Times:** kept as the venue's, in its zone (JourneyPlan::timezone by state), shown with the zone named. Source: CLAUDE.md "Times where they happened".
- **Moves:** draft → publish; unpublish; cancel ⇄ put back on; delete kept 30 days with Put back. Every change keeps its old value in the History. Source: CLAUDE.md "Edit or Undo".
- **Held words:** MarketRules::check stops a publish (0134 §2's automated checks).

## Tests
`tools/event-tests/events.php <root>/app <migration>`: **42 of 42** on MariaDB.

- `tools/event-tests/portal.cjs`: the portal section rendered at 1280 and 390, signed in as the business's owner on the design harness: **28 of 28**. Screens are in `screens/`.
- **Harness note:** the harness's zip_coordinates lacks 85936, so Test Cafe's zone fell back to America/Denver. Live has the ZIP: Arizona reads America/Phoenix (the DB test checks AZ and NM).

## Still to do before send
- The Design reviewer.
- The zip check and claims, on the newest live copy.
