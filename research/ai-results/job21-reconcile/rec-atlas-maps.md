# Platform atlas: Maps (merged from pass1 and pass2)

*`research/platform-atlas/maps.md`. Draft for human review.*

**Marking**
- **Unmarked** means both passes support the line.
- **(single-pass: pass1)** or **(single-pass: pass2)** means only that pass says it.
- **Passes differ:** means the passes disagree.
- Citations are the passes' own. Where they differ, both are given.

**Coverage**
- **pass1** stops partway through section (c), in the 0058 §15 row (it ends at "…confidential listings are never pinned; person"). Sections (c) from there on, (d) and (e) therefore come from pass2 only, except where pass1 said the same thing in another section.
- **pass2** stops partway through (e), at "(charter, Why This Matters; brand §3b".

**Sources**
- **Code.** Three files from the sync mirror `traverse-3way-sync/sync-app/lib/`:
  - `Geocoder.php`;
  - `ZipPopulation.php`;
  - `Landmarks.php`, cut off after line 531.
  - pass1 adds that `Geocoder.php` (1–366) and `ZipPopulation.php` (1–153) are complete (single-pass: pass1).
- **Documents.** Charter, brand.md, prd.md, `site-audit/pages.md`, and decisions 0053, 0058, 0065, 0148 and 0164. Of these, 0053, 0058 and 0164 are truncated.
- **Not shown.** The map front end (`js/map-view.js`, `TvMap`), `/api/pin.php`, `/api/locate.php`, `/api/open-places.php`, `ListingLocation` and `OpenPlaces`. Anything about them comes from the docs only.
  - pass1 also lists `/api/place.php` as not shown (single-pass: pass1).
  - pass2 also says every page file is not shown (single-pass: pass2).
- **Path.** The code sits in the mirror, but the docs place the library at `app/lib/`. The live path is not shown.
  - pass1 adds that each file's own header says `api/lib/…` (`Geocoder.php:10`), and that pages.md records the move to `app/lib/` in decisions/0069 (single-pass: pass1).

---

## (a) What each person can do, page by page

There is one shared full-screen map component (0058 §5; 0053 §4b / step 4b, "One map for the site").
- pass2 quotes where it is used: "everywhere a map opens: Let's Explore's Map, the directory's Map, and the map from search results" (single-pass: pass2).

### Resident (Get Local)

- **`/directory/`: List | Map.** The Map shows every result as a pin, not just the current page of results (0058 §5).
  - pass2 marks the page Live (pages.md §1) (single-pass: pass2).
- **Filters.** **Passes differ:**
  - pass1: the filters live in the search and are Near me, Open now, Has a phone, Has a website and Favorites (0058 §9).
  - pass2: the filters are Open now, Phone, Website and Favorites, floating as one strip on the map (0053, "Dropdown and directory, round 3").
  - Near me appears only in pass1. The passes also differ on where the filters sit and on the source cited.
- **Favorites** (linked or saved businesses) show as a heart in a circle on every map and are never clustered (0058 §5).
  - pass1 lists this under both Resident and Member. pass2 lists it under Member.
- **Map controls** (0058 §5):
  - zoom;
  - **My location**, "on this device only, never saved or sent." pass1's quote begins "moves the map…" (single-pass: pass1 wording);
  - **Search this area**;
  - **Map style**: Streets and Light (OpenStreetMap), Terrain and Satellite (USGS).
- **Left panel / bottom sheet.**
  - It folds on phones rather than closing (0058 §23). pass1 has this in its (c) table.
  - Its contents are the chosen place, what's shown, the distance, "Search this area as I move the map", style and legend (single-pass: pass2).
- **Approximate pins.**
  - A pin still at its ZIP's middle says "Approximate pin: the middle of its ZIP area" (0058 §14).
  - Three or more listings on one point show as one dashed "N approx." spot, not spread out (0053, 2026-10-04 progress). pass1 gives the example "157 approx."
- **Fixing a wrong pin: `/listing/location.php?id=`.**
  - A visitor drags the pin, taps the map, or uses "I'm here now", then taps Send.
  - The result is a suggestion that a person approves in Review (0058 §15).
  - The link is labelled "Wrong spot? Fix the pin", the page is Live, and no sign-in is needed (single-pass: pass2).
- **Town names on a cluster page** open `/directory/?town=…&state=…`, centred on the town within 10 mi (0053) (single-pass: pass2).
- **OpenStreetMap places** appear as a separate, credited layer of lighter pins (0065).
  - **Passes differ** on framing. pass1 places this under Traveler as "Outside our areas". pass2 places it under Resident as "where Traversence has nothing".

### Traveler (Let's Explore)

- **`/discovery/` Map tab** (0065):
  - It opens on Everything.
  - It opens 5 mi around the site's place, or asks the device where it is. pass1 cites the 0065 2026-11-10 notes. pass2 has "opens 5 mi near you" in its (c) table.
  - Its activity filters are Hike, Walk, Drive, Exhibits and On the water (0148; pass2: "What you do there").
  - pass2 adds that the tab sits in the ribbon, that closing it returns to the previous page, and that the page is Live (single-pass: pass2).
- **Device location or a ZIP** opens the town area (cluster) it belongs to, or else the nearest within 40 mi, via `place.cluster` (0058 §8).
  - pass1 has this in its (c) table.
  - pass2 names the endpoint `/api/place.php`.
- **See on map** opens the full-screen map with the place chosen and the outdoors within 10 mi (0058 §10). It also appears on highlighted guide-name cards (0148 §6).
  - **Passes differ** on the source pages:
    - pass1 puts the button on both `/place/landmark.php?id=` and `/place/recreation.php?id=`.
    - pass2 puts it on `/place/recreation.php?id=` only, and says landmark pages are "on the content standard" (0058 §24).
- **Get directions.**
  - Highlighted guide names get Directions as well as See on map (0148 §6). pass2 has this as "See on map/Directions on tag cards" in its (c) table.
  - Get directions opens the visitor's own maps app (0148 §6) (single-pass: pass1).
- **Story-only landmarks** are told on their own page but never pinned or mapped