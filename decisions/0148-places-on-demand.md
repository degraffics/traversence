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

**Fix (2026-11-08, `places-fix_20261108.zip`):** looked up by the guide's own names first (Wikidata's labels and other
names, within 25 km), then what's within 10 km for names written a little differently; the area search alone was cut
off in busy places. A town is never a landmark ("Dana Point", the city, was added and is hidden again by migration
`2027-02-14_places_on_demand_tidy.sql`); a business or school only when something about it is a place to visit (a
museum run by a nonprofit stays). Historic houses, cinemas and chapels count. A place's town is the guide's own when
it's within 4 miles, else the one Wikidata names, else the nearest by ZIP. Searched-for guides are looked up first,
four guides a run.

**Pilot towns and places by hand (2026-11-10, `places-pilot-towns_20261110.zip`):** Jason, on Four Corners Monument
not being found: "Yes, build it and run the place finder for pilot towns." The finder only kept places a guide's text
already named, so museums and harbors no guide mentioned yet (Laguna Art Museum, Dana Point Harbor) were never
landmarks. `PlaceFinder::sweepTick()` (run by `guide_ondemand_tick`, one town a run) now takes each pilot town (the
`anchors`) and keeps everything Wikidata holds within 6 miles that has an English Wikipedia article and passes the
same kinds (`KEEP`, never a town, a business only when it's a place to visit), at most 25 new a run, then looks again
after a year. Status still comes from `Landmarks::decide` (sacred or protected places are held). Places Wikidata
doesn't put near any town are added once by hand (`PlaceFinder::HAND`): **Four Corners Monument** (36.9990,
-109.0452, Apache County, AZ; Monument), on Navajo Nation land and approved as public because the nation runs it as a
Tribal Park open to visitors (decisions/0066). Each is indexed for search when it's added.
Fix (same day, `heritage-fix_20261110.zip`): the one-time hand-added places are marked done only once every one is in,
so a failed add is tried again on the next run.

**Places from the source, not by hand (2026-11-10, `places-from-source_20261110.zip`):** Jason, on the hand-added
Four Corners Monument: "Fixing four corners monument manual defeats our purpose: how do we fix our system to pull the
right data." It was missed because the landmark load keeps natural features only, the finder only looked near towns,
a search that found nothing went nowhere, and a place's town came from the nearest ZIP. Now, in `PlaceFinder`:
1. **Searched-for names** (`lookupTick`, six a run of `guide_ondemand_tick`): searches of two words or more already
   counted in `search_terms` (never who; ones that found nothing first) that read like a name (`nameIn`: not only
   generic words, never a number or an email) are asked of Wikidata by name (`wbsearchentities`), anywhere in the US.
   A match that's a place to visit with a Wikipedia article or on the National Register becomes a landmark through the
   same checks, searchable at once. Each name is asked once every 30 days.
2. **Each pilot state, square by square** (`stateTick`, one 0.5° square a run): everything Wikidata places there with
   an article or on the Register; a state rests 30 days once done, then starts again.
3. **The National Register of Historic Places** (Wikidata P649, CC0): a Register place is kept as a historic place
   even without an article, in the town sweep, the state squares and name lookups.
4. **Where it is, from the source:** the town Wikidata says it's in (P131, a settlement) and its state (one of ours
   first on a state line), then the nearest ZIP (one of our states when nearly as near), then the guide's place. A
   Wikidata landmark of ours is placed again from the source once a month; GNIS's and a person's are never overwritten.

The hand list (`PlaceFinder::HAND`) is gone. Migration `2027-02-21_landmarks_from_source.sql` makes the Four Corners
record a Wikidata place, so the finder ties it to its item and places it in Teec Nos Pos, AZ; its public status stays.

**Their kind from our categories, not guesses (2026-11-10, `near-me-everything_20261110.zip`):** places from Wikidata
showed as "Natural landmark" (Brophy College Chapel, Woodlea Historic District), and GNIS ones took their kind from the
last word of their name. Jason: "this should be from categories not guesses." Now each landmark is filed under one of
our categories (`landmarks.category_id`, migration `2027-02-22_landmark_categories.sql`) by its source's class: GNIS's
feature class, or Wikidata's "instance of" (`Landmarks::categorize`, run with the search index's refresh). A category
named that (or its plural), else one staff gave that word (`category_terms`); a class of several words tries the whole,
then its last word ("art museum": Museums). None fits: 0, shown as the source's own class, listed in the health report
(`landmark_kinds_unfiled`, in `site-audit/towns.md`) for staff to give a category that term; asked again daily. The
name is never read for it. Search indexes the category (`search_index.category_id`), so "museums near me" finds a museum
landmark and the kind pill searches its category; every landmark is re-indexed once (`landmark_kind_walk`).

**OpenStreetMap's outdoor places as leads (2026-11-10, `osm-leads_20261110.zip`):** each town's trailheads, parks and
preserves, and viewpoints on OpenStreetMap (its own layer, decisions/0065) give names; `PlaceFinder::osmLeadTick` (one
town a run, again after 30 days, while OpenStreetMap's hourly fair use has room) asks Wikidata for those names near the
spot, and a match with a Wikipedia article or on the National Register is kept through `keep()`, built from Wikidata.
Only the names go out; nothing of OpenStreetMap's is copied into the landmark.

**What a place is, without an article (2026-11-10, Jason on a map card: "Why do I want to visit this place… what is the
experience?"; `landmark-facts_20261110.zip`):** `PlaceFinder::factsTick` (fifty a run) reads what Wikidata records about
our places from it (heritage listing P1435, inception P571, style P149, architect P84; CC0) into `landmarks.wd_facts`
(migration `2027-02-23_landmark_facts.sql`); `Landmarks::about()` says it after Wikidata's description when there's no
Wikipedia article ("Listed on the National Register of Historic Places. Craftsman and bungalow architecture, from
1910."). Facts, not the experience: a written "what you'll find there" would need Claude, which only guides and journeys
may use today (CLAUDE.md, No income, no expense).
