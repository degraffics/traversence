# Events E2: the event's page, the listing's and the guide's "Coming up" (decisions/0134 §5's minimum, the note of 2026-11-27, option (a)). Built, not sent.

**Built on:** Sync 16:23 UTC and on E1 (handoffs/events-e1); it goes right after E1. Both are needed live by Dec 10–12 for the Hometown theater's community night.

## Files
- **New:** website/event/index.php, the page to 0058 §20–21:
  - the hero (the event's cover, else a labelled stock photo);
  - the header: its kind (a pill that searches), the title as an h1 (once), the Link button (place:ev:<id>);
  - facts: When (in the venue's zone, named), Where (the host listing and its town, "· the host"), Cost (Free or Tickets);
  - **Get tickets** to the host's own published Event tickets item;
  - the engage bar (views, likes, comments, Share, its QR: the event's GO Code);
  - About; Showtimes as information only (no link, no button); More at the host; comments.
  - A cancelled event says so and is kept; one that's over says so, with what's coming up at the host; one not there is a 404 with ways forward.
- **Changed:**
  - app/lib/Events.php: publicGet, over, forEntity, forPlace (cluster, geo hub, hub), ticketOf, html.
  - app/lib/Engage.php: the `event` kind (views, likes, comments; exists only when public).
  - app/lib/Links.php: place:ev:<id>.
  - app/lib/staging-tables.php: KINDS 'event'.
  - website/listing/view.php: Coming up here, host events first.
  - website/hub/index.php: Coming up, host events first.
  - app/business/events.php: the Coming line gone; a page icon (Peek) on published and cancelled rows; wording.
  - app/ui/faq.md: "What's on an event's page?" and the Events answer brought up to date.

## Tests
- events-public.php: **18 of 18** on MariaDB.
- event-page.cjs: **40 of 40** at 1280 and 390.
- E1's events.php: 42 of 42, still passing.

## Gaps
- A city or neighborhood guide (town:/sub: keys) shows no host events yet; only towns (clusters), areas and regions.
- The calendar /events/ is Q1 (the note).

## Before send
1. Rebuild listing/view.php and hub/index.php on the live copy after C.5b and C.8, which touch hub/index.php.
2. Rebuild faq.md the same way.
3. Re-run the paths: "See my listing" and the 1A guide paths.
4. The sandbox ticket purchase and refund goes on the payments checklist before the night.
