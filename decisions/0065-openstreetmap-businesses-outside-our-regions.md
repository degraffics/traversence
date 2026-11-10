# 0065. OpenStreetMap businesses where we have no listings yet

> **Crawler rules: superseded by `decisions/0133` (2026-10-30), the crawler's one rule book.** This record stays as history; where it and 0133 disagree, 0133 rules.

Date: 2026-10-04
Status: Accepted

## Context

People search places outside our regions: they're moving to Seattle, or planning a trip somewhere we don't cover yet.
Search now keeps their place (ADR 0060, 2026-10-04 progress notes), says "We have no listings in Seattle, WA yet", and
refers them to Google Maps to carry on. That's honest, but it sends people away for things as ordinary as a pizza place
or a storage unit.

OpenStreetMap (OSM) is free, open map data with good coverage of everyday businesses (places to eat, lodging, fuel,
groceries, pharmacies, hospitals) and thin coverage of services (movers, utilities, apartments). Its data is under the
Open Database License (ODbL): showing it needs a credit line, and a database built from it that we share publicly has to
be offered under the same licence.

Two of our rules shape this:
- **Tribal nations' land** (decisions/0058 §26): on a nation's land only its public places and **public businesses**
  show; never a sacred or restricted site. Public businesses are open to search and display (Jason, 2026-10-04).
- **Google is a link out, never our data** (ADR 0062).

## Decision

1. **Only where we have no listings.** A place outside our regions (`UniversalSearch::loosePlace`, `place.outside`).
   Inside our regions nothing changes.
2. **Only businesses.** Our kinds (`OpenPlaces::KINDS`): places to eat, pizza, coffee, lodging, fuel, groceries,
   pharmacies, hospitals, clinics, self storage, auto repair, hardware, laundry. **Never** landmarks, attractions,
   viewpoints, historic or natural places. Anything OSM also marks historic, natural or an attraction is dropped. That's
   where sacred or restricted sites could be, and we have no nation boundaries outside our regions to check against.
   "Things to do" stays a Google Maps link.
3. **Only when asked, and once a week.** A need ("Storage") or a search ("pizza") that names one of those kinds asks
   OSM's Overpass service for it around the town: about 8 miles, then 25 when that finds fewer than 3. The answer is
   kept for 7 days (`open_places`, `open_place_asks`). All our searches together ask at most 40 times an hour (OSM's
   fair use). A failed ask is tried again the next day. The panel loads the businesses after the answer shows
   (`/api/open-places.php`), so search is never slowed down by OSM.
4. **Shown as OSM's, not ours.** Every block says "From © OpenStreetMap contributors, not Traversence listings". There's
   no Peek, no claiming, no listing page, and nothing goes into `entities` or `search_index`. Each row opens directions
   (Google Maps, to the point) in a new tab and has Call when OSM has a phone. The Google Maps link stays under it ("More
   storage", "More: pizza near Seattle, WA").
5. **Within the Distance slider.** At most 25 miles, and no further than the slider (10 mi by default).
6. **Kept apart.** OSM data lives only in its own two tables, so the ODbL share-alike applies to them alone. If anyone
   asks, we can share those two tables as they are.
7. **Privacy.** Only the kind and a point rounded to about 7 miles go to OSM, from our server. The panel sends the
   town's middle rounded to about a mile. Nothing about who searched is stored. Taps are counted as `act` (`osm:<place>`,
   `osm-call`), never who.

## Consequences

- People moving or travelling outside our regions get real nearby businesses for the everyday kinds. Movers, utilities
  and apartments still go to Google Maps.
- OSM entries can be out of date or lack a phone or hours. The credit line says where they're from, and the rows
  promise nothing more.
- If out-of-zone use grows past OSM's fair use, we run our own Overpass copy (Railway) or stop. The 40-an-hour cap
  means we degrade to Google links, never to errors.
- Taps on OSM and Google rows by town show where people want listings next, which is the signal for expanding regions.
- Run `api/migrations/2026-11-03_open_places.sql` once.
- 2026-10-07: rows with the same name read apart. Distances under 10 miles show one decimal ("0.4 mi"), and the street
  shows when OSM has it. The same business mapped twice (a point and its building, under 0.1 mi apart) shows once.

**A lead only (2026-11-10):** OpenStreetMap's data is share-alike (ODbL), so the panel shows only a business's name,
what it is, how far, and directions; `/api/open-places.php` no longer sends its phone, hours, street or website (the
**Call** button goes). It's offered wherever we have none of that kind within reach, not only outside our areas
(decisions/0149, "From a search"). Still to bring in line: suggested journeys use its hours and address
(`JourneyPlan`).

**On the map (Jason, 2026-11-10: "Why nothing and no search here options", the directory map over Tempe with "Nothing
here"):** `js/map-view.js` shows **Search this area** in words at the top whenever the map has moved (it was only the ↻
icon). With nothing of ours there, the panel offers Search this area, Widen to 25 mi, and OpenStreetMap's places (places
to eat, coffee, groceries, gas, pharmacy, places to stay) as lighter pins: name, kind, distance, directions, credited, a
lead only; when the words name a kind ("coffee shops"), its places show at once (`/api/open-places.php?words=`). Asked
from the map (`crawl=1`), the nearest town goes to the crawler (`TownCrawl::fromSearch`, decisions/0149).

**Everything we can, as its own layer (Jason, 2026-11-10: "Use openstreetmap for everything we can get for sources and
data as long as we dont have to share back data"; `osm-leads_20261110.zip`).** Replaces "a lead only" above.
- **The layer rule (what keeps share-alike away from our data):** OpenStreetMap's data stays in its own tables
  (`open_places`), always shown as OpenStreetMap's with "© OpenStreetMap contributors", and is never copied into our
  listings, landmarks or other records. ODbL asks for sharing back only when a database mixing its data with ours is
  used publicly; separate layers shown side by side don't (the OpenStreetMap Foundation's Collective Database and
  Horizontal Map Layers guidelines). The crawler still uses it only as a lead to a business's own website; our
  listing's facts come from there. (Our reading of the licence and guidance, not legal advice.)
- **Within the layer, all it gives:** name, kind, address, phone, hours, website (`/api/open-places.php` sends them
  again; the panel's Call and the map pin's Call, Website and hours show).
- **The outdoors too:** trailheads and nature preserves (`trail`: "hiking near me"), campgrounds, parks, viewpoints.
  Never anything tagged historic or an attraction, and never a name that may mark a sacred or protected place
  (`Landmarks::isProtected`, decisions/0066).
- **Everything at once (`all`):** the everyday kinds in one ask (places to eat, coffee, gas, pharmacies, groceries,
  hotels), 1.5 mi then 5 mi out: the map with no words (2026-11-10: "Used search here button but results are
  nonexistent") and "near me" alone with fewer than 3 of ours.
- Still to bring in line with the layer rule: suggested journeys copy OpenStreetMap's hours and address into a journey's
  stops (`JourneyPlan`); they should point to the layer instead.
- **Where you are, on the map (Jason, 2026-11-10: "A pin for where I am currently located physically would be helpful
  too"; `map-you-are-here_20261110.zip`):** the blue dot shows when the map opens, if the place came from this device
  (Auto-detect) or the browser already allows location for the site; it never prompts by itself (the My location button
  does) and doesn't move the map. The position stays in the browser.
- **Why the map showed nothing (Jason, 2026-11-10: "why am I not seeing any listings from osm?"; `discovery-map_20261110.zip`):**
  one Overpass server, a 12-second wait and the shared hourly limit, with a failed ask remembered for a day. Now
  `OpenPlaces` tries a second server, waits up to 18 seconds, asks a failed spot again after 10 minutes, allows 60 asks
  an hour with at most 15 for our own background jobs (`PlaceFinder::osmLeadTick` asks as one), and asks "everything"
  around a spot about a mile and a half across. The health report shows the hour's asks and the last ten with what each
  found (`site-audit/towns.md`). The map's locate button is the location arrow, "Go to my location".
- **Discovery's Map tab** (Jason, 2026-11-10: "Discovery should also have the map as an option"): Map sits in the ribbon
  with Regions, Experiences and Parks & Landmarks; outside our areas (no cluster), it opens 10 mi around the place set on
  the site (its point, or its ZIP's), or asks where first and opens there.
- **Parks & Landmarks outside our areas** (Jason, 2026-11-10: "Or at least parks and landmarks?"; delivered in `map-near-cards_20261110.zip`): with no area of ours
  for the site's place, the page sends its point (`?near=lat,lon&near_label=`) and shows the landmarks near it and the
  National Park Service places nearest first, each with its miles. The rest of Discovery still follows our areas only.
- **The map opens near you, and its cards say what a place is** (Jason, 2026-11-10: "Map should open zoomed to current
  location + 5 mile radius so it doesn't see entire western states. Also… a name only isn't enough to make a qualified
  decision to visit"; `map-near-cards_20261110.zip`): opened without a pin to show, `js/map-view.js` starts 5 mi around
  the site's place when it has a point (Auto-detect, a ZIP's middle), or around the device when the browser already
  shares it and no place is set; else as before. A pin's card shows its photo (credited), a few lines on what it is
  (Wikipedia's summary, else what we hold about it, else Wikidata's description; a listing's own description), then
  where, hours and the ways to reach it.
  With no place set, opening the map asks the device where it is (the browser's one-time question), rather than only
  when the browser had already said yes (2026-11-10: the directory's map sat on its default view of eastern Arizona;
  `map-ask-where_20261110.zip`). Refused: the map says to allow location or choose a place.
