# 0170: Everything placed in the discovery funnel, by location, active or planned

Status: proposed (drafted 2026-11-17 at Jason's ask; for his approval). Not built; queue item 3 in
`handoffs/session-manager.md`.
Builds on: 0006 (the pilot hub), 0013 (the nested route), 0042 (the five-tier taxonomy and clustering), 0043 (a
discovery guide at every level), 0048 (the continental data plan), 0060 and "Places in search" (a city named is always
found), 0066 (nations' land), 0148/0149 (places and listings on demand), 0164 (the Knowledge Engine; §1.4's indexer,
R17 hub opening), HubOpening (Planned → Open).
Replaces: `DEFAULT_GEO_HUB_SLUG` and every hard-coded `ancient-borderlands` fallback.

## The problem

Found while tracing `.env` (2026-11-16). When something new has a location outside the areas we've built, the code
files it under the pilot zone, the Ancient Borderlands, wherever it really is (`CrawlerIngest::resolveMicroClusterId`,
called by the crawler's intake, intake approvals, area seeding and Add a business). A Flagstaff café would be filed in
the Ancient Borderlands. And each kind of content places itself its own way: listings by ZIP into a cluster, guides
by the level they're written for, events and stories by whatever they're attached to.

Jason, 2026-11-16 and 17: "I dont want the ancient-borderlands as the default. It is the pilot." "dont we have a way
this can use our existing hub Framework to put a find into the right hub even if it isn't an active hub yet?" "if it
is not in this hub, but another hub it could be aligned within the correct main hub." "this is not just about listings
but how all content gets organized into the discovery funnel too."

## What exists

- **The taxonomy** (0042): Continental Hub → Geo-Hub (zone) → Cluster (town) → Anchor → Sub-group, the first three in
  the route (`/[hub]/[zone]/[town]/…`, 0013), a discovery guide at every level (0043).
- **Ancient America** (Hub 9 of 20, the platform spec) with its **15 zones** on the site since 2026-09-30
  (regions.md): the Ancient Borderlands **active**, the other 14 **planned** ("Coming soon").
- **Towns** (`micro_clusters`, a primary ZIP and associated ZIPs), and 0042's spatial clustering for new ones.
- **HubOpening's stages** (Planned → Seeding → Opening budget → Soft open → Open) and W14's authority sources per state.
- **The locations data** (`zip_coordinates`): every ZIP's point, town and state.
- Missing: a planned zone has a name and a town list but **no geography the code can match against**, and only one of
  the 20 continental hubs is defined.

## Decision

### 1. One place resolver for everything

A single resolver answers "where does this belong?" for **every kind of content**, as it's made or found:
listings; landmarks and outdoor places; places; guides; journeys; events; stories; heritage and topics; research and
dossiers; Moments and Marketplace posts; sources and the indexer's sites. It returns the deepest level that fits:
Continental Hub → zone → town → anchor → sub-group. Every path that creates or moves content calls it; nothing places
itself.

### 2. By location, active or planned

- **Every level carries its geography:** each continental hub its states (and counties where a state is split); each
  zone the ZIPs of its towns (regions.md, through `zip_coordinates`) and the counties they sit in; each town its ZIPs
  (as today).
- **A thing with a location goes to its real level, open or not:** an existing town first; else a town seeded in its
  zone by 0042's clustering ("needs review"); else its zone; else its continental hub.
- **Held where its level isn't open:** filed and kept, not public, until that level opens. It counts toward that
  level's readiness (HubOpening), so it's plain which zone or hub is filling up and worth opening next.
- **Only what no hub covers is "no hub yet":** held, and counted as demand by place.
- **No default hub.** `DEFAULT_GEO_HUB_SLUG` and the `ancient-borderlands` fallbacks go; "no hub yet" is a valid
  answer everywhere (intake approvals never refuse for it).

### 3. Things that span places

Something about a whole region (a state, a river, a trail or a route across zones, a nation's land) is placed at the
level that spans it, and linked to each level it touches, rather than forced into one town. A journey is placed where
it starts and linked along its route.

### 4. Rules that still hold

- Nations' land (0066): pins on a nation's land still move to a public place; held names stay held.
- "Places in search": a city named is always found and is demand, whatever level it falls in.
- A person's choice of place (an owner's listing, a staff member's guide) wins over the resolver; the resolver only
  suggests and fills in.

### 5. The 20 continental hubs

The plan is 20 continental hubs; only Ancient America is defined. **Jason supplies the other 19 hubs' names and the
states each covers.** Each is added as planned with its states; its zones are drawn when that hub is scheduled
(0048). Until then, finds outside Ancient America are "no hub yet".

### 6. What's already there

Data cleanup (0163 §8 stage 1b) re-places existing content with the resolver: a preview of every move, a ledger and
Undo. Areas seeded under the Ancient Borderlands that lie outside it are the first proposals.

## Build order (each a release Jason reviews)

1. Zone geography for Ancient America's 15 zones; the resolver; listings and the crawler's intake onto it; the
   default removed; Settings' "Default place" row removed.
2. Every other kind of content onto the resolver; things that span places.
3. Data cleanup's re-placing of what exists.
4. The other 19 continental hubs, when Jason has sent them.

## Consequences

- Every find and every piece of content lands where it is, and a planned zone or hub fills up visibly before it opens.
- One rule to test and to explain; the indexer's reading priority (0164 §1.4) uses the same levels.
- More geography to keep (each zone's ZIPs and counties); it comes from regions.md and our locations data, not by hand.
