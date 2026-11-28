# Platform atlas: Maps

*`research/platform-atlas/maps.md`. Draft for human review, 2026-11-27 context.*

**Sources.**
- **Code (three files, each capped).** All from the sync mirror `traverse-3way-sync/sync-app/lib/`:
  - `Geocoder.php` (complete, 1–366);
  - `ZipPopulation.php` (complete, 1–153);
  - `Landmarks.php` (truncated at line 531).
- **Documents.** Charter, brand.md, prd.md, site-audit/pages.md, and ADRs 0058, 0053, 0065, 0148 and 0164. ADRs 0058, 0053 and 0164 are truncated.
- **The map's front end is not shown.** This includes `js/map-view.js` (`TvMap`), `/api/pin.php`, `/api/locate.php`, `/api/place.php`, `/api/open-places.php`, `ListingLocation` and `OpenPlaces`. Everything about it below comes from the ADRs, not from code.
- **Path note.** Each file's own header says `api/lib/…` (for example `Geocoder.php:10`). pages.md says the library moved to `app/lib/` (decisions/0069). The live path is not shown.

---

## (a) What each person can do, page by page

### Resident (Get Local)

- **`/directory/`: List | Map.**
  - The Map opens the one shared full-screen map with every result as a pin (0058 §5), not only the current page of results.
  - Filters live in the search: Near me, Open now, Has a phone, Has a website, Favorites (0058 §9).
  - Favorites show as a heart in a circle and are never clustered (0058 §5).
- **Map controls** (0058 §5):
  - zoom;
  - **My location**: "moves the map on this device only, never saved or sent";
  - **Search this area**;
  - Map style.
- **Map styles:** Streets and Light (OpenStreetMap); Terrain and Satellite (USGS).
- **Approximate pins.**
  - A listing still at its ZIP's middle says "Approximate pin: the middle of its ZIP area" (0058 §14).
  - Three or more listings on one point show as one dashed spot ("157 approx."), not spread out (0053, 2026-10-04 progress).
- **`/listing/location.php?id=`: fix a wrong pin.**
  - A visitor can drag the pin, tap the map, or use "I'm here now", then Send.
  - This is a suggestion that a person approves in Review (0058 §15).

### Traveler (Let's Explore)

- **`/discovery/` Map tab** (0065, 2026-11-10 notes):
  - opens 5 mi around the site's place, or asks the device where it is;
  - opens on Everything;
  - filters for Hike, Walk, Drive, Exhibits and On the water (0148).
- **Outside our areas:**
  - OpenStreetMap places show as a separate, credited layer of lighter pins (0065);
  - Parks & Landmarks show nearest first.
- **`/place/landmark.php?id=` and `/place/recreation.php?id=`:**
  - **See on map** opens the full-screen map with the place chosen and the outdoors around it within 10 mi (0058 §10);
  - **Get directions** opens the visitor's own maps app (0148 §6).
- **Story-only landmarks** show no pin or map (`Landmarks.php:175-182`).
- **"None here" search box:** tapping a nearby place opens the map around it at 15 mi (0058 §12).
- **Highlighted guide names** get See on map and Directions (0148 §6).

### Member (signed in)

- **`/user/trips/trip.php?id=`:** a trip's days and the map.
  - It is the first map to draw routes (brand.md §4, 2026-11-22 note).
  - The chosen day's route is lime over its casing; the other days show faded casing only.
  - It is the owner's only view (pages.md §3).
- **`/trip/?t=`:** a shared, view-only trip with its map. Places kept private are left out (pages.md §3).
- Favorites (linked or saved businesses) show as hearts on every map (0058 §5).
- **Plan a trip here / Add to a trip** in the Engage bar's ⋯ menu (0058 §20 note). As of 2026-11-19, Add to a trip was greyed as "coming". Its current state is not shown.

### Business

- **`/listing/location.php?id=`, also reachable from Business portal → "Location and map pin":**
  - the owner or manager drags the pin and it is saved for everyone at once;
  - they can change the street, town, state and ZIP; the fields lock so crawls can't overwrite them (0058 §15).
- **Business Insights:** Directions taps from the map, the directory, the peek card and the listing page are counted per listing, action and day. The business sees the last 30 days (0058 §9).

### Staff (for completeness)

- `/admin/geocode.php`: Census map points.
- `/admin/landmarks.php`: Waiting / Shown / Approved / Hidden.
- `/admin/cluster-tools.php`: ZIP population import.
- Edit listing ▸ **Pick on the map** (0053).

---

## (b) How it works underneath

### Shared map component (not shown)

- `js/map-view.js` (`TvMap`) is "One map for the site". Leaflet loads on first use (0053 step 4b).
- `TvMap.open({panel: true})` starts with the info panel open (0058 §21).

### Listing points: `Geocoder.php`

**What counts as an approximate point.**
- The `APPROX` SQL treats a point as approximate when it is NULL or within 0.0002° of its ZIP centroid in `zip_coordinates` (`:37-38`).
- This one definition gates every write.

**How listings are queued.**
- `candidates()` picks non-suppressed merchants with an approximate point and a street address (`:66-91`).
- A listing is retried when:
  - it has never been tried;
  - its address changed (`:77`);
  - it found no match or a tie more than 90 days ago (`:35`, `:68`, `:78`).

**How addresses are sorted (`classify()`, `:97-115`).**
- Milepost: the highway and milepost are kept (`:102-105`).
- Mailing only: PO box, HC, RR, general delivery (`:107-109`).
- A house number first: sent to the Census (`:113`).
- Anything else: no number.

**Confidentiality checks.**
- Confidential listings are marked `private` and never sent, in batch (`:144`) and on demand (`:244`).

**Batch path.**
- `run()` → `prepare()` → `csv()` → `post()` → `parse()` → `apply()` (`:124-172`, `:300-361`).
- Batches are 500 addresses, sent to the Census `addressbatch` endpoint with benchmark `Public_AR_Current` (`:32-34`, `:348`).
- `apply()` re-checks `APPROX` before each write, so a point set in between is never overwritten (`:162-164`).

**Railway backup.**
- `runOrHandOff()`: if the site can't reach the Census on its connection, the batch goes to the Railway worker (`:180-193`).
- The worker returns answers through `takeAnswers()`, which applies them only to listings still waiting (`:200-210`).
- The backup is skipped when a person's pause or staging's gate is the reason the service is held (`:216-224`).

**On-demand path (0058 §14).**
- `locateOne()` uses the Census single-address service (`:240-265`).
- It is gated by `StagingGate` (`:251`) and `ServiceHealth` (`:252`).
- It rejects points outside latitude 17–72 / longitude −180 to −60 (`:261`).
- Doc mismatch: the docblock says it returns `'exact'` (`:238`), but the code returns `'matched'` (`:264`).

**Points from people (`setPoint()`, `:277-287`).**
- Staff and the owner replace any point (`:282`).
- The site and visitors fill only an empty or ZIP-middle point.

**Provenance.**
- Every write goes to the identity record with its source (`record()`, `:290-297`), in line with 0164 R11.
- Census is recorded as `government`; the site's own map as `own_site`; staff, owner and visitor as admin, owner and user.

**Counts** are cached for 10 minutes (`:43`).

**Schedule.**
- `POST /api/crawl/geocode.php` runs each worker cycle (0053, 2026-10-04 progress).
- The CLI equivalent is `app/scripts/geocode_listings.php` (pages.md §6).

### ZIP centroids and population: `ZipPopulation.php` (0042, not attached)

- Fetches the 2020 Census DHC `P1_001N` for every ZCTA (`:18-25`).
- The fetch goes through `StagingGate` and `ServiceHealth` (`:32-33`).
- It refuses anything under 30,000 ZCTAs (`:83-85`).
- It writes through a temporary table and one joined UPDATE, so it finishes within shared hosting's time limit (`:130-151`).
- It sets ZIPs with no ZCTA to 0 (`:144`).
- `zip_coordinates` is the same table `Geocoder::APPROX` and `Landmarks::nearestZip()` read.

### Landmarks: `Landmarks.php` (partly shown)

**Visibility rules.**
- `decide()` (`:185-194`):
  - a protected name → `story_only` if publicly known, else `waiting`;
  - a Spring on a nation's land → `waiting`;
  - else → `shown`.
- `isProtected()` (`:89-98`) treats a town's cemetery as civic, but a cemetery of a nation's peoples as protected (`:83-86`, `:103-120`).
- `publiclyKnown()` (`:165-171`) never applies on a nation's land or to sacred/burial/archaeological words (`:163`).

**What reaches the map.**
- `visibleSql()` returns only `shown` and `approved` (`:383-387`).
- So maps, search pins and nearby lists skip `story_only` and `waiting`. Only `story()` serves a story-only page (`:396-402`).

**Monthly load behaviour.**
- `batch()` keeps a person's or a nation's approved, hidden or closed decision across reloads (`:262`).
- `finish()` never deletes manual, wikidata, approved, hidden, closed or waiting rows (`:373`).
- `NATION_TOOLS = false` (`:70`).

**Nearest town and distance.**
- `nearestZip()` looks within 40 mi (`:73`, `:207-218`).
- `miles()` is an equirectangular approximation (`:220-225`).

**Photos.**
- `bestPhoto()` requires a free licence, at least 640 px width, name-word matches, and proximity (1.5 km on the spot, else within 5 mi) (`:282-331`).
- `findPhotos()` takes one landmark at a time with a 2 s pause, behind the W9 automation switch (`:337-360`).

**Activities** (Hike, Drive, Exhibits …) come from the source class and our category, never the name (`:449-478`). They feed the Discovery map filters (0148).

### OpenStreetMap layer (0065; code not shown)

- `open_places` / `open_place_asks` are OSM's own tables, credited and never copied into `entities`.
- The public Overpass service was retired on 2026-11-19. The layer is now filled only from Geofabrik's weekly state files: AZ, NM, UT, CO, NV.

---

## (c) Decisions

| ADR | What it rules for maps |
|---|---|
| 0053 | One site map (`TvMap`), distance and Map from search. Census geocoding rules: only empty or ZIP-middle points; confidential listings never geocoded; 90-day retry. Rural classification. Dashed "approx." spot; scheduled geocoding. |
| 0058 §5 | The map standard: nothing floats but Exit and the controls; the left panel or bottom sheet; styles (OSM, USGS); Favorites hearts. Tile-provider agreement needed before heavy traffic. |
| 0058 §8 | Auto-detect or a ZIP opens the town area, else the nearest within 40 mi (`place.cluster`). |
| 0058 §§10, 12, 21, 23 | See on map (10 mi); the "none here" map (15 mi); maps open with the panel; the panel folds on phones. |
| 0058 §14 | Locate ZIP-middle pins on demand (once, then after 90 days; at most 20 an hour per visit). |
| 0058 §15 | `/listing/location.php`: visitors suggest; business and staff set; confidential listings are never pinned; person