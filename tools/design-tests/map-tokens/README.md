# The map release's rendered checks (2026-11-22)

On the design harness (`handoffs/design-harness/README.md`; the release's `out/` over the mirror on :8790, the live mirror on
:8791), with Leaflet 1.9.4, leaflet.markercluster 1.5.3 and Tailwind's browser build served from npm in place of their CDNs.
- `map-route.cjs`: a trip's map, option B, at 1280 and 390: a lime line over a casing for each day, the casing 6 under the
  lime's 4, the Route row (Day 1, Day 2), Day 1 chosen first, only the chosen day lime and the other day's casing faded,
  pressing Day 2 moves it, tapping a day-1 stop chooses day 1 and still opens its card, no sideways scroll, no script
  errors. 22 checks. `TAG=A|C ONLY=1` with a variant's trip.php laid over takes the pop-up's comparison images.
- `one-day.cjs`: a one-day trip: no Route row, its line lime over the casing.
- `map-shared.cjs` with `map-test-page.php` (copy to `website/test/map-test.php` in both roots): the shared map opened on
  three pins with a 5-mile ring, before and after: the panel's words under 4.5:1, the ring's stroke, pins, sideways scroll.
Seed: trip 1 (user 2, two days, listings 10 and 11 on day 1, 12 and 13 on day 2, each with a point), trip 2 (one day).
