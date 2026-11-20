# Send to Maps (decisions/0174 release 1)

Built on Sync 04:00 UTC (6792988), in `releases/inbox/send-to-maps.zip`, 7 files. Reviewed by the session manager.
Replayable: `handoffs/send-to-maps/build-sm.py` (reads the live mirror, writes `out/`).

## What it does
- **Where it appears:** a suggested journey's route icon (on a guide and on `/journeys/suggested.php`), and a place's
  **Make a day of it** (`/place/landmark.php`). Both open Send to Maps (`website/js/tv-maps.js`).
- **The choices:** Google Maps or Apple Maps, and starting from **My location** or the first stop. Both are remembered on
  the device (localStorage, wrapped).
- **Every stop goes, in order.** A link holds a start, 8 stops between and an end. A longer route is split into
  "Leg 1 of N" links, each starting where the last ended. Nothing is cut silently: before this release, every stop
  after the tenth was dropped.
- **Without script** the link is what it was: Google, the first ten stops (`tv_maps_fallback`).
- **Closing:** ×, Esc, a click outside, or the icon again. On phones it's a bottom sheet.
- **Counting:** `data-out="directions"` (counts only).
- **No cost:** no key, no call from our server, no AI, no tables.

## The links' limits
- **Apple:** `maps.apple.com/directions?source=&destination=&waypoint=…&mode=driving`, with `waypoint` repeated.
  - Leaving out `source` starts from the current location.
  - Needs iOS 18.4, macOS 15.4 or watchOS 11.4. The chooser says so.
  - Source: Apple's "Adopting unified Maps URLs" (developer.apple.com), read on 2026-11-20 from its JSON. It states no
    maximum.
- **Google:** its docs host (developers.google.com) is blocked from the cloud session. So the count stays at the site's
  proven one, an origin, 8 waypoints and a destination, the same as the journeys' link since they began, rather than a
  guessed limit.
- **Leg size:** the same for both apps, `MID = 8` in `tv-maps.js`. Raise it only after checking Google's and Apple's
  current pages.

## The same rules as Directions (the session manager's check)
- `JourneyPlan::dropListings` now also drops a listing whose location is kept private (`ListingSafety::isConfidential`)
  and a mailbox address (`ListingActions::isMailbox`). Before, either could be a journey stop with a Directions arrow.
- `Landmarks::bite` reads `search_index` with `private_location = 0`.
- `tv_maps_stops()` is the last guard. It leaves out stops flagged private, private_location, story or approx, a mailbox
  address, and a stop with no point. Fewer than 2 stops left means no Send to Maps.

## Tests
- `tools/design-tests/send-to-maps/send-to-maps.cjs` with `page.php` (copied to the harness's `website/test/maps.php`):
  48 checks at 1280 and 390.
- `rules.php <root>` on MariaDB: 8 checks. Two of them, the shelter and the mailbox, fail on the code before this release.

## Next
Release 2 (decisions/0174 §7): trips and **Add to a trip**. Its picker sends a saved trip through the same
`tv_maps_attrs()`.
