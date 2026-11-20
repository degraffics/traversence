# Trips you can save (decisions/0174 release 2)

Built on Sync 13:09 UTC (8095ddb), in `releases/inbox/trips.zip`, 13 files. Replayable: `handoffs/trips/build-tr.py`
(new files in `handoffs/trips/src/`, edits applied to the live mirror's copies).

## What it does
- **Add to a trip** in ⋯ on a business, a place (landmark), an outdoor place and a town or area (`tv_engage_bar` passes
  `add` to `tv_engage_tools`), and in Get Local's card ⋯.
  - **The picker** (`website/js/tv-trips.js`) offers your trips, the newest first, then **New trip**, named for the place
    (`Trips::nameFor`: "Trip to Show Low").
  - **Its confirmation** is in the picker, "✓ Added to …", with **Open it** and **Undo**. The same place twice says
    "Already in".
  - **From a ⋯ menu**, the menu closes and the picker sits by ⋯. On phones it's a bottom sheet. It closes with ×, Esc or a
    click outside.
- **Make it my trip** on a ready-made journey (`tv_sj_card`) copies its stops: `entity:` → listing, `landmark:` → lm,
  `osm:` → osm, `rec:` → rec. Stops the rules leave out are named, with why.
- **Your trips** (`/user/trips/`), reached from the account's rail and beside the Trip Planner. For each trip:
  - its stops in order; × takes one out (Undo puts it back in its place);
  - Rename, and Send to Maps (`tv_maps_attrs`, release 1);
  - Delete (kept 30 days under Deleted trips, Put back; `Trips::mine` clears older ones).
- **Guests:** one trip on the device (`localStorage['tv-trip-device']`), shown on Your trips through `resolve`, with
  Make an account (`TvJoin.open`). On the first page a signed-in person opens that loads `tv-trips.js`, the trip moves
  into the account (`claim`) and leaves the device.

## The rules (`Trips::resolve`, read fresh every time)
- **The same rules as Directions:** a place kept private (`ListingSafety`), only a mailbox address, marked closed (the
  `closed` mark's presence, decisions/0072), hidden, story-only (`Landmarks::story`), or with no point.
  - It's greyed in ⋯ with why, and refused by the API even on a direct call.
  - A stop that becomes one of these later keeps its place, greyed with why, and with no point, so Send to Maps leaves it
    out.
- **Nations' land:** points go through `Nations::pin` (0066).
- **Private:** only the owner reads a trip, nothing counts per person, and there's no AI and no outside call.

## The data
- `trips` and `trip_stops`, as in §6, plus:
  - `trips.deleted_at` (Delete's 30 days);
  - `trip_stops.id` and `added_at` (Undo and order).
- Both are named in `staging-tables.php` (records).
- `AccountDeletion` deletes them, and its summary counts them.
- The migration is safe to run twice.
- `tz` is the device's for now. Release 3 sets it from the first stop's place when it adds times.

## Not in this release (on purpose)
- **Add to a trip on the map's card and in the Trip Planner's results** (both named in §2): they come with release 3,
  beside the trip page's map and Save as a trip.
- **Days, reorder, times, the map:** release 3.
- **Sharing, Personal Guides and counts for businesses:** release 4.

## Tests
- `tools/trip-tests/trips.php <root>`: 28 checks on MariaDB (the rules, owners, Undo, Delete and its 30 days, claim, a
  journey's stops, account deletion, staging).
- `tools/trip-tests/trips.cjs` with `trips-seed.php`: 67 checks at 1280 and 390 (the picker from ⋯ and Get Local, New
  trip, Undo, Already in, greyed, Make it my trip, Your trips' ×/Undo/Rename/Delete/Put back, a guest's device trip, sign-in
  moving it in, no sideways scroll, edge to edge, no script errors).
