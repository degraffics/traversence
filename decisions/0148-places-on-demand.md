# ADR 0148: Places on Demand

**Status:** Accepted (Jason, 2026-11-08). Built: `places-on-demand_20261108.zip`.

## Why

Jason, reading the first look at Laguna Beach (decision 0147): "Could the chameleon filter actually make the
destination titles clickable and those locations have their landing pages?" And: "Shouldn't the directions have clicks
to the map and our links for directions via Google Maps?" A guide names Main Beach, Heisler Park, Crystal Cove State
Park and the Laguna Art Museum in bold, but we hold none of them outside our pilot states, so they stayed plain text.

## Decision

1. **Found free, kept as ours.** The names in a guide we don't hold (beaches, parks, museums, historic places; not
   roads, towns or businesses) are looked up in **Wikidata** (CC0) within 15 km of the guide's place: one question per
   place, kept 30 days (`PlaceFinder::near`). A match by name becomes a landmark of ours (`landmarks`, `file =
   'wikidata'`, its Q id kept), with Wikidata's description and, where there's an article, Wikipedia's summary (CC BY-SA,
   credited). The photo finder (`landmark_photos_tick`) looks for its free photo. Its page is the landmark page
   (`/place/landmark.php`), credited to Wikidata. The monthly GNIS reload keeps them.
2. **Linked.** The guide's name then links to that page (`content_tags`), and Find it doesn't spend on it.
3. **The rules hold.** Never a business (Find it and the listing checks, decision 0044), never a town, school or
   company, and never a place that may be sacred, burial or protected: `Landmarks::decide` holds it, and the guide keeps
   the plain name (decision 0066). Landmarks still never come from OpenStreetMap (decision 0065).
4. **When.** Right after a first look is written (decision 0147), and for every live guide by the job
   (`guide_ondemand_tick`, two guides a run, each once every 30 days). Free: no AI.
5. **Journeys use them.** Beaches and parks are outdoor stops, art museums and galleries arts and culture, other
   museums heritage (decision 0146).
6. **Where it is, from any name.** A highlighted name's card (`js/tv-tagcard.js`) shows **See on map** (our map) and
   **Directions** (the visitor's own maps app, Google Maps), as round icon buttons, counted as taps (`data-out`).
7. **Claude's own voice is kept out.** A sentence where Claude speaks of itself or its checking ("I haven't confirmed
   drive times…") is cut by the guide's scrub, like talk of our sources.

The Chameleon filter (decision 0003, the Visiting / Live here dial) decides what a guide puts first; names link the
same in both.

## Later

- GNIS (USGS) for states beyond the pilot, through the worker's monthly read, for the natural places Wikidata lacks.
- California's state parks and other state lists, where their data is open.
