# The map release (2026-11-22)

Design and trips session (session_01CXLi5grMsUgrRMJv3a71F9). **Built on Sync 20:55 UTC (73d3782)** by
`handoffs/map-tokens/build.py` (`python3 build.py A|C` writes the other options to `variants/`). Zip `map-tokens.zip`.
Scope reviewed by the session manager (2026-11-22): build B; A and C shown as images at 390 in the release's own pop-up
("Approve B", "Use A", "Use C", "Needs changes"); A or C is rebuilt and sent without a second pop-up; his choice goes in
brand.md §4's note.

## What changes, each with its source
- **js/map-view.js onto the tokens, colours only** (css-theme-brand §4 Release 3; admin-design.md l.189-190 had given this
  file's colour work to Get Local's rebuild, which didn't take it: 0 uses of --tv-route, 127 literals). recolor.py (3f's):
  101 placed, status colours left and listed. The radius ring is a Leaflet option (an SVG attribute, where var() doesn't
  resolve): it reads its token's value at run time (`tvTok`, getComputedStyle, the hex as the fallback). Byte check: with
  the ring and its helper put back, undoing each token gives the live file exactly.
- **The trip page's day lines on --tv-route over --tv-route-casing** (brand.md §4's note, Jason 2026-11-19; admin-design.md
  l.112-114, the casing 2px wider: 6 under 4). **Option B:** the chosen day lime over its casing, the other days casing
  only at .35, the lime hidden. A **Route** row over the map (Day 1, Day 2…, filled by the script, shown only with two or
  more days that have lines) chooses a day; tapping a stop chooses its day too and still opens its card. Day 1 first. A
  one-day trip has no row. The lines stay dashed (straight lines, not roads, as the note under the map says). Pins keep
  their day colours (markers, not routes).
- **Words:** the note under the map adds "The chosen day's route is in lime; tap a day or one of its stops to see another";
  the FAQ's "What's on a trip's page?" says the same (decisions/0057; anchor unchanged).

## Kept / moved / dropped
- Kept: the numbered pins in their day colours, each stop's card on tap, the fit to every stop, straight dashed lines,
  the map's failure note, every control of the shared map (the byte check).
- Changed (Jason's pick): the five per-day line colours become lime over casing, one day at a time (B). A: every day lime.
  C: the day colours kept.
- Dropped: nothing.

## Admin's test
- A trip with two or more days: the map shows "Route: Day 1 Day 2", Day 1 pressed and lime; the others' lines a faint dark
  trace. Means: which day's route you're looking at. Press: another day, or tap one of its stops.
- One day: its route lime, no row.

## Proof (harness, rendered with sample state; `tools/design-tests/map-tokens/`, images in `map-tokens/proof/`)
- Trip map, option B: 22/22 at 1280 and 390. One-day trip: pass.
- Shared map (three pins, 5-mile ring): the panel's words under 4.5:1, 6 → 0 at 1280 and 390; pins and ring drawn; no
  sideways scroll; no script errors.
- Found, not this release's: location-scope.js's "We need a location to search" pop-up: "Log in" and "Not sure?" read
  2.9:1 (orange on cream). For the next design pass.

## State
Built and sent; waiting for the session manager's review, then Jason's pick in the release's pop-up.
