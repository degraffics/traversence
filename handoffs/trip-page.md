# The trip page (decisions/0174 release 3)

Built on Sync 13:32 UTC (e4602d8), in `releases/inbox/trip-page.zip`, 10 files. Replayable: `handoffs/trip-page/build-t3.py`.

## What it does
- **`/user/trips/trip.php?id=`** is the owner's only. Another person's trip, or a missing one, gets "Trip not found" with
  the way back. A guest goes to Your trips, where their one trip lives on the device.
- **Settings:** Starts (a date), Days (1–14), and Each day starts (5 AM–1 PM).
  - Fewer days moves the stops of the days taken away to the end of the last day. Nothing is dropped.
  - Moving a stop past the last day adds the day.
- **Order:**
  - drag, on desktop, within a day or onto another;
  - ↑ ↓ (across days at the ends);
  - a Day select beside each stop (phones and keyboards);
  - × with Undo, which keeps the stop's day and stay.
- **Times** (`Trips::plan`):
  - the first stop is at the day's start; each next one comes after the stay (by kind, or the person's choice) and the
    leg's estimate;
  - with a date, each stop is checked against its hours (`Hours::openAt`), and outdoor places against sunrise and sunset;
  - "Closed when you'd arrive: Opens 11 AM" carries a **Move to day N** button.
- **Time zones:** times are in the trip's zone, which `touch()` sets from the first stop's place
  (`JourneyPlan::timezone`). They're always labelled ("9:00 AM MST"); a stop in another zone shows its own ("… there").
- **The map:** Leaflet with OpenStreetMap tiles, credited. Numbered pins, a colour a day, and dashed straight lines.
  - The legs say "about" (`TravelLegs::estimate`, never a call out).
  - Each day shows its travel, its miles and when it's done.
- **Print** prints the stops, not the buttons. **Send to Maps** and **Delete** work as in releases 1 and 2.
- **Save as a trip** (the Trip Planner): where, when and how long become a trip.
  - Its place is mapped from the planner's level and slug to a key, and stored in `trips.place_key`.
  - "This weekend" dates it; a weekend is 2 days.
  - An empty trip's page offers that place's ready-made journeys (**Use these stops**, `Trips::addMany`).
- **Add to a trip** on the map's card (`map-view.js`) and in search's Peek (`search-panel.js`), where the planner's
  results open.
  - Both use `tripRef()`: an outdoors `rec:g123` is a landmark, `rec:ABC` is a recreation place.
  - Both lazy-load `tv-trips.js`, and leave out a place kept private.

## The data
`app/migrations/2026-11-21_trips-page.sql` adds `trips.start_min`, `trips.place_key` and `trip_stops.stay`. Each is
guarded through `information_schema`, so it's safe to run twice. There are no new tables.

## Tests
- `tools/trip-tests/trip-page.php <root>`: 21 checks on MariaDB (timing, hours, daylight, the zone, moves, fewer days,
  stays, Use these stops, Undo).
- `tools/trip-tests/trip-page.cjs`: 56 checks at 1280 and 390, plus one source check of the map card. Set `LEAFLET` to a
  local copy of `leaflet-1.9.4/`.
- Release 2's `trips.php` (28) and `trips.cjs` (67) still pass. "Open it" now goes to the trip's page.

## Next
Release 4: sharing a trip by link, publishing as a Personal Guide, and counts for businesses and places.
