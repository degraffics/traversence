# Platform atlas: Maps

*`research/platform-atlas/maps.md`. This is a draft for human review. Sources are the attached documents (charter, brand, PRD, `site-audit/pages.md`, decisions 0053, 0058, 0065, 0148 and 0164) and three code files: `Geocoder.php`, `Landmarks.php` and `ZipPopulation.php`. The code is shown under `traverse-3way-sync/sync-app/lib/`, while the docs place it at `app/lib/`. `Landmarks.php` is cut off after line 531, and 0058, 0053 and 0164 are also truncated. The map's own front end (`js/map-view.js`, `TvMap`), the endpoints `/api/pin.php`, `/api/locate.php` and `/api/open-places.php`, the `ListingLocation` class and every page file are **not shown**. Statements about them come from the docs only.*

---

## (a) What each person can do, page by page

The map is one shared full-screen component, used "everywhere a map opens: Let's Explore's Map, the directory's Map, and the map from search results" (0058 §5; 0053 §4b "One map for the site").

### Resident (Get Local)
- **`/directory/`** (Live, pages.md §1) offers a List | Map toggle. The Map shows every result as a pin, not just the current page of the list (0058 §5). The filters are Open now, Phone, Website and Favorites, and they float as one strip on the map (0053, "Dropdown and directory, round 3").
- **Map controls** (0058 §5):
  - zoom;
  - **My location**, which works "on this device only, never saved or sent";
  - **Search this area**;
  - Map style: Streets and Light from OSM; Terrain and Satellite from USGS.
- **The left panel** (a bottom sheet on phones) holds the chosen place, what's shown, the distance, "Search this area as I move the map", style and legend. On phones it folds rather than closes (0058 §23).
- **Approximate pins.** A pin still at its ZIP's middle says "Approximate pin: the middle of its ZIP area" (0058 §14). Three or more listings on one point show as a single dashed "N approx." spot (0053, 2026-10-04).
- **Wrong pin.** "Wrong spot? Fix the pin" opens `/listing/location.php?id=` (Live). A visitor can drag the pin, tap the map, or use "I'm here now", then Send. No sign-in is needed, and the result is a suggestion sent to Review (0058 §15).
- **Town names on a cluster page** open `/directory/?town=…&state=…` centred on the town, within 10 mi (0053).
- **OpenStreetMap leads.** Where Traversence has nothing, the map shows OSM places as lighter, credited pins (0065, "On the map").

### Traveler (Let's Explore)
- **`/discovery/`** (Live) has a Map tab in the ribbon (0065). It opens on Everything, and closing it returns to the previous page (0065). It has activity filters: Hike, Walk, Drive, Exhibits, On the water (0148, "What you do there").
- **Device location or a ZIP** opens the town area (cluster) it belongs to, or the nearest within 40 mi, via `/api/place.php` `place.cluster` (0058 §8).
- **"See on map"** from `/place/recreation.php?id=` (Live) opens the map with the place chosen and the outdoors within 10 mi (0058 §10). The same button appears on a highlighted guide name's card (0148 §6). Landmark pages `/place/landmark.php?id=` (Live) are on the content standard (0058 §24).
- **"None here."** Tapping a place in "Nearby, with '…'" opens the map around it at 15 mi (0058 §12).
- **Story-only landmarks** are named and told on their own page but never pinned or mapped (Landmarks.php:175-182).
- **Outside our areas**, Parks & Landmarks shows places near the site's point (0065).

### Member (signed in)
- **Favorites** (linked or saved businesses) show as a heart in a circle and are never clustered (0058 §5).
- **`/user/trips/trip.php?id=`** (Live, release 3) is a trip's page with a map. **`/trip/?t=`** (Live, release 4) is a shared trip with its map.
  - Brand §4 (Jason, 2026-11-22): the chosen day's route is drawn in lime over a casing, other days in casing only, faded; a "Route: Day 1 / Day 2" row picks the day. This is described as "the first map to draw routes."
  - The trip code itself is **not shown**.
- **Members 18+** can comment on place pages; the map card offers "Add a tip or review" (0148, "Immersion").

### Business
- **Moving its own pin.** From `/listing/location.php?id=`, reached through "Location and map pin" in the Dashboard's Business box and in `/listing/businessportal.php`, the business can drag the pin and it is "saved for everyone at once" (0058 §15). "The business" means a `listing_access` owner or manager, an org owner or manager, or a verified owner.
  - The business can change street, town, state and ZIP. Those fields are then locked against crawls.
  - Unverified claimants' pins go to Review (0058 §19).
- **Counted taps.** Call, Directions, Website and contact taps from the map are counted per listing, action and day, never who. The business sees 30 days of these in Business Insights (0058 §9).
- **Confidential listings are never pinned** (0058 §15; Geocoder.php:20, 144).

---

## (b) How it works underneath

### Map points for listings (`Geocoder.php`)
- **What counts as "approximate".** `APPROX` treats a point as approximate when it is null, or within 0.0002° of its ZIP's centroid in `zip_coordinates` (Geocoder.php:37-38). The listings API's `approx` flag and the map's dashed spot depend on this; the API side is not shown.
- **Batch run.** `candidates` selects merchants with an address that have not been tried, were not found 90+ days ago, or whose address changed since (66-91; `RETRY_DAYS` at 35).
- **Classifying addresses.** `classify` sorts addresses into four groups (97-115):
  - **mailonly**: PO box, HC/RR, general delivery;
  - **milepost**: highway and milepost kept;
  - **nonumber**;
  - a street line the Census can try.
- **Sending.** `prepare` marks confidential listings `private` (144). `post` sends batches of 500 (34) to the Census batch geocoder, passing through `StagingGate` and `ServiceHealth` first (332-361). `apply` only overwrites a point that is still approximate (162-164).
- **Railway backup.** If the site can't reach the Census on its connection, `runOrHandOff` returns the batch to the Railway worker (180-193). `takeAnswers` then applies the replies only for listings still waiting (200-210).
- **On-open lookup.** `locateOne` handles a single listing when it is opened (0058 §14), using the single-address service with an 8 s timeout (240-265).
- **US bounds.** Points outside lat 17-72 and lon -180 to -60 are rejected (261, 279, 323).
- **Manual points.** `setPoint` handles points from site, staff, owner or visitor. Only staff and owner may replace a real point; the others fill only empty or ZIP-middle points (277-287).
- **Provenance.** Every placed point is written into the listing identity with its source, e.g. "U.S. Census geocoder" or "Its own website's map" (`record`, 290-297; ties to 0164 R11).
- **Triggers.**
  - Admin: `/admin/geocode.php`.
  - Worker: `/api/crawl/geocode.php` on a schedule.
  - CLI: `app/scripts/geocode_listings.php` (pages.md §5-6; 0053, 2026-10-04).
  - These callers are **not shown**.

### ZIP centroids and population (`ZipPopulation.php`)
- Loads 2020 Census DHC P1_001N for every ZCTA into `zip_coordinates.population` (ZipPopulation.php:5-18), using `CENSUS_API_KEY` (21-25).
- Refuses anything under 30,000 ZCTAs (83-85).
- Bulk-updates through a temporary table. ZIPs with no ZCTA are set to 0 unless `$fillMissing` is false (130-151).
- Used by `/admin/cluster-tools.php` and `app/scripts/import_zip_population.php` (8). Cites decisions/0042, which is **not shown**.

### Landmarks on the map (`Landmarks.php`, partly shown)
- **Monthly load.** `batch` upserts up to 500 GNIS rows (228-270). It drops "(historical)" names (241), accepts only Wikimedia images with a free licence (243), keeps only `notable` features (197-204), and assigns the nearest town within 40 mi (`nearestZip`, 207-218; `NEAR_MILES` 73).
- **Status decision.** `decide` (185-194):
  - a protected name that is publicly known becomes `story_only`;
  - a protected name otherwise becomes `waiting`;
  - a nation's Spring class becomes `waiting`;
  - everything else is `shown`.
  - `isProtected` treats a town cemetery as public and a nation's or people's cemetery as protected (89-98). `publiclyKnown` never applies on a nation's land (165-171).
- **What can be mapped.** `visibleSql` lets only `shown`/`approved` rows appear (383-387). This is what keeps waiting and story-only rows off every map and pin list (comment 177-178).
- **Decisions persist.** A person's or nation's decision survives reloads (262). Held rows are never deleted by `finish` (373).
- **Photos.** `findPhotos` adds Commons photos, one at a time with a 2 s pause, and respects the Automation pause (337-360). `bestPhoto` requires ≥640 px, a free licence, and a location within 5 mi or 1.5 km "on the spot" (282-331).
- **Map card text.** `about` (38-63) and `activities` (449-478) supply the "what it is / what you do" lines described in 0148. Wikipedia text is used only if the source register allows (30-35); it is currently "Cite only" (27-28).

### Other parts, not shown
- **OSM layer:** `OpenPlaces`, the `open_places` tables and `/api/open-places.php`. It is now filled only from Geofabrik extracts for AZ, NM, UT, CO and NV; Overpass is retired (0065, 2026-11-19 notes).
- **Wikidata lookups:** `PlaceFinder`, through `Wdqs.php` (0148).
- **Map front end:** `js/map-view.js` and the panel, controls and spiral/dashed spots.
- **Counting:** `/api/count.php` and `/api/out.php` (0058 §13).

---

## (c) Decisions

| Decision | What it rules for maps |
|---|---|
| **0053** §4b + progress notes | One site map (`TvMap`), distance chips, Search this area, numbered pins, Census geocoding rules, rural address classes, website-map points, the dashed "approx." spot. |
| **0058** §5 | The map standard: nothing floats but Exit and controls; side panel; styles; Favorites never clustered; tile agreement needed before launch. |
| **0058** §8, §14, §15 | ZIP/device → cluster; on-open single geocode; who may move a pin (visitor → Review, business/staff at once, confidential never). |
| **0058** §10, §12, §21, §23 | "See on map" 10 mi; none-here map 15 mi; maps open with the panel; panel folds; one-count title. |
| **0058** §24 | Natural landmarks source and Admin → Landmarks. Its "nation land waits" rule appears superseded by 0066 in the code comment (Landmarks.php:15-22); 0066 is **not shown**. |
| **0065** (amended by 0164) | OSM is its own credited layer, never copied into our records. Map notes: Search this area in words, you-are-here dot, opens 5 mi near you, Discovery Map tab. Overpass retired 2026-11-19. |
| **0148** | Places on demand become landmarks; "See on map"/Directions on tag cards; activity filters on the map; OSM "todo" places on Discovery's map; Wikidata limits. |
| **0164** R7, R9, R11 | Story-only tier (no pin or coordinates); OSM and Google licence uses; points recorded in the identity record. |
| **Brand §4** | Route lime `#B6F22E` over Deep Umber casing; trip map "Approve B"; selected pin copper `#C85A17`. |
| 0042, 0044, 0066, 0174, 0175 | Cited by code or docs; **not shown**. |

---

## (d) Live vs coming

**Live**, per pages.md and the docs:
- the directory map and Discovery map;
- the pin-fix page;
- recreation and landmark pages;
- trip maps (`/user/trips/trip.php`, `/trip/`);
- `/api/pin.php` and `/api/locate.php`;
- `/admin/geocode.php`, `/admin/landmarks.php`, `/admin/cluster-tools.php`;
- the crawl geocode and landmarks endpoints.

The code shown backs the geocoding, ZIP population and landmark-status parts.

**Coming, or open:**
- **Tile provider agreement.** "Heavy traffic will need a tile provider agreement before launch" (0058 §5). Status is not shown.
- **Interactive corridor map.** PRD Tier 2.2 lists it as active development. No doc shown defines it as built.
- **Milepost placement.** Highway and milepost are kept "for a milepost lookup against ADOT's milepost data, once it's loaded" (0053; Geocoder.php:22-24, 104). There is no lookup in the code shown.
- **Nation tools.** `NATION_TOOLS = false` (Landmarks.php:70), so held places stay unshown with no approval queue.
- **OSM extension.** OSM pins beyond the five-state extract are lost since the Overpass retirement (0065).
- **"Add to a trip."** Greyed as "coming" in the 2026-11-19 ⋯ menu (0058 §20 note). pages.md lists trips as Live, release 2-4. Whether the menu item is now active is **not shown**.
- **Market location rules.** Approximate area for personal posts and buyer-proposed delivery routes are planned (PRD 2.8, decisions/0031, 0040, **not shown**). Their map work is not designed in the files shown.
- **Clean hub and listing addresses** (pages.md §8).

---

## (e) Charter promises, economic results, gaps

**Immersion and connection.** The charter calls Traversence "an immersion and connection engine" (charter, Mission).
- Landmarks give the map a story: a credited photo, an "about" line, and activities with how-to lines (Landmarks.php:38-63, 449-459).
- Discovery's map adds museums, parks and similar places (0148).
- Route drawing on trip maps follows the "Crimson Thread" lineage of brand §4.
- **Gap:** the Living Geography device ("a path worn down by centuries of trade", brand §2) has no map layer in anything shown.

**True locality.** "Understand a region… without wading through sponsored listings" (charter, Why This Matters).
- The map is honest about precision: ZIP-middle pins are labelled approximate (0058 §14) and stacked as one dashed spot rather than spread "as if they were real places" (0053).
- Points are only upgraded from Census, the business's own site, the owner or staff (Geocoder.php:17-19, 277-287). Each pin keeps its source (290-297).
- Rural addresses are classified rather than dropped (97-115).
- **Gaps:**
  - Census points "can be a few doors off" (0053).
  - Milepost businesses wait on unbuilt ADOT data.
  - OSM coverage is limited to five states.

**Discovery that can't be bought.** The charter forbids visibility that "can be bought" (charter, Why This Matters; brand §3b