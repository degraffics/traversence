# ADR 0149: Listings on Demand

**Status:** Accepted (Jason, 2026-11-09). Built: `listings-on-demand_20261109.zip`.

## Why

After the Dana Point guide was built, a search for restaurants in Dana Point still said "We have no listings in Dana
Point, CA yet." Jason: "The system should treat this as the pre-population request for building out the listings and
discovery. A request is driven by interest and interests are the queue to get busy and generate both research and
content. Even if it is outside our pilot zone, we don't want to miss the opportunity it presents."

## Decision

1. **Interest is the queue.** A guide built for a town outside our regions (decision 0147) also queues the crawler for
   that town: places to eat, places to stay, things to do, shops and health care (`town_crawl_jobs`, `TownCrawl`). The
   more people asked for the town, the higher it sits. Towns built before this are queued by the guide job.
2. **The same worker, first in line.** The off-site worker takes town jobs before the coverage jobs (`CrawlQueue::lease`),
   with no change to the worker: the jobs are shaped like coverage jobs, with the town's ZIPs and OpenStreetMap filters.
3. **The same checks.** What it finds goes through the same intake as every crawl (`IntakeStager`, decision 0044): a
   business seen on OpenStreetMap **and** its own website (or a trusted registry) becomes a listing; the rest wait in
   Review. Outside our communities a place scores lower (`AutoImport`), so more of them wait for a person.
4. **Businesses only.** Never a landmark or attraction from OpenStreetMap (decision 0065); places to visit come from
   places on demand (decision 0148).
5. **They show everywhere at once.** New listings are in the town's search results, its guide's sections and its
   journeys (rebuilt when a job adds listings). They are claimable listings and leads like any other.
6. **This replaces, for searched-for towns, decision 0065's "OpenStreetMap only outside our regions".** OpenStreetMap's
   places still show in search where we have no listings yet. Crawl targets (decision 0066, held for a person) stay
   for staff-queued places.
7. **Free.** The worker already runs; no AI.

## Fix (2026-11-10): the lookups were failing

The towns report (`site-audit/towns.md`, from the health report) showed it: of 25 town jobs, 16 had failed and 4 were
stuck. OpenStreetMap's main server answered 406, a mirror timed out, and Phoenix's query (a circle per ZIP for each kind
of place) was too large to send. A lookup that ran out of time answered "OK" with nothing and a remark, recorded as
"done, 0 found" (Dana Point's places to eat). The worker now sends one box around many ZIPs (circles for a few), always
in the request body, treats a timed-out remark as a failure and tries the next server (one more added).
`2027-02-20_town_crawl_retry.sql` queued every failed, stuck or empty town job again (`town-crawl-retry_20261110.zip`).
The one run that worked (Laguna Niguel, places to eat) found 25: 5 published, 20 in Review.

## Their own websites (Jason, 2026-11-10)

OpenStreetMap stays a lead only (decisions/0133; Jason: "OpenStreetMap requires shared data", ODbL share-alike), so a
place publishes only when its own website confirms it, and most leads have no website in OpenStreetMap (Laguna Niguel:
20 of 25 in Review). For a town built on demand, a lead with no website now gets one web search for its own site
(`find_own_site`: the name, street, town and state). A result counts only if its domain is the business's own (never a
directory: Yelp, TripAdvisor…) and the page names the place and shows its town, street or phone, so a namesake
elsewhere isn't taken. It's then confirmed like any website. The searches come from the free plan's monthly allowance
the site hands the worker (stopped at 90%; nothing billed), towns most asked for first; past it, leads wait in Review.

## Cut short, not done (2026-11-10)

Dana Point's places to stay came back "done, 0 found" twice. The worker's run has a 4-minute budget; the places to eat
before it (25, each with a website read and maybe a search) used it up, so the next job stopped before reading any of its
places and reported done with none. Now a job the run's time cuts short isn't reported when nothing was read (its lease
returns it), and is reported "partial" when some were; the site puts a partial town job back in the queue without
spending one of its three tries. The health report also shows, for each town, its listings (shown, hidden), what search
can find there and its landmarks by status, and `?find=` looks a place up by name (`towns-partial-diag_20261110.zip`).

## Search couldn't see them (2026-11-10)

Dana Point had 6 listings and Laguna Niguel 10, all published, and search found none. The search index's 15-minute
refresh takes listings changed since its mark, 300 at a time, and moved the mark only when fewer than 300 had changed;
with the pilot crawler busy it re-read the same oldest 300 every time, so newer listings never reached search. The mark
now moves to the last listing taken (a second back, so others changed that second are read again), 600 at a time, and a
town crawl indexes the listings it publishes at once. The towns report shows how far the index has caught up
(`search-index-catchup_20261110.zip`).

**From a search (2026-11-10, `places-and-search-crawl_20261110.zip`):** Jason, on "coffee near me" in Phoenix showing
Show Low and Dana Point: "Why doesn't our search use our crawler instantly for inquiries like this." A search for a
kind of business (`OpenPlaces::kindFor`) that finds nothing of ours within reach of the place, in our areas or not, now:
- shows what OpenStreetMap has around the place at once (decisions/0065; before, only for places outside our areas with
  nothing anywhere in reach), in place of loose "mentioning" matches far away;
- sends the town to the crawler (`TownCrawl::fromSearch`): it's made our town if it's new (`OnDemand::townFor`) and its
  five kinds are queued, as when a guide is asked for. Once a town every 30 days, at most 25 towns a day; never for the
  search run while typing. Real listings follow when the worker has been through it, usually within an hour or two.

**OpenStreetMap as the lead for everything it offers (Jason, 2026-11-10: "Crawler should use osm as lead source for
anything it offers not just listings"; `osm-leads_20261110.zip`).** The town kinds grow (the worker takes each job's own
filters, so it needs no change): groceries and gas, services (banks, post offices, salons, auto, fitness, vets, pets,
hardware, laundry, phones, opticians, bikes), public services (library, town hall, community centre, police, fire
station, courthouse), places of worship, campgrounds and RV parks; never shelters or safe houses
(`amenity=social_facility`), which a person links. The same checks apply: the business's own website, two sources to
publish, the rest to Review. Towns already built out get the new kinds once (`TownCrawl::addKinds`, `KINDS_VERSION`).
For places that aren't businesses, see decisions/0148 (outdoor places, confirmed on Wikidata).
