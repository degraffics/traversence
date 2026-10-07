# 0065. OpenStreetMap businesses where we have no listings yet

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
