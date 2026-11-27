# 0170: Everything placed in the discovery funnel, by location, active or planned

Status: accepted (Jason, 2026-11-17, after his answers on the three choices and confirming that home is the existing
Single Home Rule, not a second one). Not built; queue item 3 in
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

Jason, 2026-11-17, settling where this lives: "The crawler should properly organize in phase 4. This is where it needs
to be bigger more than just listings." And: "Once organized, it will apply research data into the bigger picture for
placement."

### 1. Placement is Phase 4's job, for everything

**Its workflow: W15 Funnel** (Jason, 2026-11-17): the placement, the intent tags, the roll-up, the gaps by level and planned levels' readiness run as one workflow in the engine (0164's table), scheduled and run as things are catalogued, gated and budgeted like the rest.

Organizing happens in the Knowledge Engine's **Phase 4, the master catalog** (0164 Phase 4, step 6 "Catalogued"), not
in each content type's own code and not only for listings. When anything is catalogued (a listing, a landmark or
outdoor place, a place, an event, a topic, heritage, the Human Record and the Living Landscape, a footprint, a dossier
or research brief, a guide, a journey, a story, a Moment or a Marketplace post, a source), Phase 4 places it on the
place taxonomy (0042: Continental Hub → zone → town → anchor → sub-group) as `LOCATED_IN` edges (0164 §4.3) from its
point on the map (§4.2). Content a person makes goes through the same catalog step; nothing places itself.
`CrawlerIngest::resolveMicroClusterId` and every other path that picks a place today hand over to it.

### 1a. Intent tags, for the Chameleon Filter (Jason, 2026-11-17: "Intent tags will help")

Placement answers *where*; the Chameleon Filter (brand.md §5, architecture.md §22) answers *for whom and how* a level's
items show. So when Phase 4 catalogues an item it also tags **which tracks it serves**: **Get Local** (commerce and
utility: a café, a pharmacy), **Let's Explore** (narrative, corridors, history: a heritage site, a trail, a guide) and
**Community** (groups, posts, events people make); many serve more than one, each with a weight. Tags come from the
item's kind and category, its facts and its source, and a person can correct them. They're stored with the item, so
the Chameleon Filter (not yet built; Jason to sort out its design) has the data waiting for it.

### 2. By location, active or planned

- **Every level carries its geography:** each continental hub its states (and counties where a state is split); each
  zone the ZIPs of its towns (regions.md, through `zip_coordinates`) and the counties they sit in; each town its ZIPs.
- **A thing goes to its real level, open or not:** an existing town first; else a town seeded in its zone by 0042's
  clustering ("needs review"); else its zone; else its continental hub.
- **Shown right away, wherever it lands** (Jason, 2026-11-17: "Showing it right away is my initial thought. No need to
  say coming soon, because open hubs vs closed hubs is more about administration support over data available"): a
  zone or hub being open or planned is about our team's support there (staff, sales, the opening budget), not about
  what's shown. Content in a planned zone is public as soon as it passes the usual gates (the real-business floor,
  the publish thresholds, nations' land, privacy), with no "Coming soon" on it; it still counts toward that level's
  readiness.
- **Only what no hub covers is "no hub yet":** shown as usual, and counted as demand by place.
- **No default hub.** `DEFAULT_GEO_HUB_SLUG` and the `ancient-borderlands` fallbacks go; "no hub yet" is a valid
  answer everywhere (intake approvals never refuse for it).
- **Home and reach** (Jason, 2026-11-17, on the Single Home Rule: "If we are talking something physical then there is
  a specific geographic placement, but if we are talking context, then the scope of access and intent must be shaped
  as well. Example: The Grand Canyon…"). Two things, kept apart:
  - **Home is the Single Home Rule, unchanged** (architecture.md §22, decisions/0042), not a new rule. As written
    there: every listing has exactly one permanent home, bound 1:1 to its micro-cluster's resolved anchor from its
    physical address (`primary_zip`, secondary ZIPs mapped to the same profile), so it's never duplicated across
    clusters. 0170 only **applies the same rule to everything else**: a thing with a point (a landmark, a trailhead,
    an event's venue) takes the cluster its point falls in, the same way; a thing with no single point (a river, a
    trail, a state, a nation's land) takes the one level that spans it (a zone or a hub) as its one home. Still one
    home each; home is where it lives, is counted and is managed.
  - **Span, for things with no single point** (Jason, 2026-11-17): its one home is the level that spans it, and it
    keeps **touch points**: every physical place it really touches (a river's towns and access points, a trail's
    trailheads, a nation's land within each zone), each placed in its own cluster, so it shows as local in every one of
    them while existing once. (The Little Colorado River: one home, the zone that spans it; touch points St. Johns,
    Holbrook, Winslow, Cameron.) Touch points are never extra homes.
  - **Reach:** how widely it matters and to whom: town, zone, hub, state, national or international, and for which
    intent (Get Local, Let's Explore, Community). Reach decides which higher levels' guides, lists and searches link to
    it, never where it lives. **The Grand Canyon:** home is its point (Grand Canyon Village, in the Flagstaff, Grand
    Canyon & Colorado Rim zone); its reach is international and Let's Explore, so Ancient America's guide, Arizona's
    and the national pages all link to it, while a local café beside it has town reach and Get Local intent.
  - Reach comes from the evidence (how widely it's written about, visited, searched, protected or listed, e.g. a
    national park or a World Heritage Site), set at Phase 4 with the intent tags, and a person can adjust it.
  - A journey's home is where it starts; it reaches every level along its route.
  - **A listing's service area is its reach** (Jason, 2026-11-17): its home follows its address; the towns it serves
    link it into those towns' Get Local lists and searches, without moving or duplicating it; the owner sets it,
    within the valid rule (it plausibly serves them).
  - **Topics and experiences live in a second tree** (Jason, 2026-11-17): a topic or a kind of experience ("Route 66",
    "ranching heritage", "stargazing") has no point on the map; its home is the **Topic index** (0164 R15). It touches
    the map through its instances (each landmark, diner or museum with its own home), its touch points; its reach is
    its scope and intent (Route 66: national, Let's Explore). A specific experience offered (a business's guided tour)
    is content with a home: where it starts, or its business.
  - **Two trees joined by links:** the place tree (hub → zone → cluster: where things live, one home each) and the
    topic tree (themes and kinds of experience: what they mean), joined by home, touch points and reach. Jason: "two
    trees joined by links: this is the chameleon filter in my head." The Chameleon Filter is the lens over these two
    trees: for a visitor's intent, it chooses which links to follow and what to show ("stargazing near Springerville",
    "Route 66 across Arizona").

**Note (2026-11-17, release #110):** §2's rule already holds in the switch-on check: a hub's stage holds only W14 (its
authority sources), never the master switch or the other workflows (0163 §10's note).

### 3. Once organized, research builds the bigger picture

With everything placed, Phase 4 rolls what's under each level up into that level's picture, town → zone → hub:
- **Each level's knowledge:** the research, dossiers, heritage, topics, landscape and facts placed in or under it feed
  that level's dossier and its discovery guide (0043; written in Phase 5, §5.4, from the catalog), so a zone's guide
  is built from its towns' research, and a hub's from its zones'.
- **Each level's gaps:** what a level is missing (kinds of place, topics, thin coverage) becomes Phase 1 targets
  (§4.5) and ranks the indexer's reading (0164 §1.4's note) toward the levels that need it.
- **Each level's readiness:** what's gathered under a planned zone or hub (listings, places, research, demand) shows
  on HubOpening's meter, so the next level to open is the one the evidence says is ready.
- **Placement refined by research:** a thing whose place was unclear (a name with no point, a business with only a
  town) is placed again when research gives it a better location; regional facts move to the level they describe.

### 4. Rules that still hold

- Nations' land (0066): pins on a nation's land still move to a public place; held names stay held.
- "Places in search": a city named is always found and is demand, whatever level it falls in.
- **Claims and people's input win** (Jason, 2026-11-17: "Claims and user input should win over system content or
  listing generation 99%. As long as the user input is valid and follows our rules."): an owner's or a member's or a
  staff member's choice of place stands over the engine's; Phase 4 only suggests and fills in where none was given.
  **For a listing, its home follows its address** (the Single Home Rule): an owner corrects the address and the home
  follows; there's no choosing a home apart from where the business is. Where a person's choice sets the home is
  content with no address of its own (a guide, a story, a group, a post), and a business's **reach** (the towns it
  serves), which is theirs to set.
  **Valid** means it follows our rules: the chosen place is where the thing really is or plausibly serves (a home
  within reach of its address, not a town far away to look local), and it breaks no rule (nations' land, held names,
  privacy, honest representation). A choice that doesn't fit is never silently overridden: it's flagged with the
  reason, as a step in the person's own Pulse and, where needed, the team's.

### 5. The 20 continental hubs

The plan is 20 continental hubs; only Ancient America is defined. **Jason supplies the other 19 hubs' names and the
states each covers.** Each is added as planned with its states; its zones are drawn when that hub is scheduled
(0048). Until then, finds outside Ancient America are "no hub yet".

**Note (Jason, 2026-11-27: "we have hubs for those, just no sub hubs", of Abilene and New York under W15; recorded
as Operations & Policy (16)):** the other continental hubs exist; only their zones (sub-hubs) aren't drawn yet. A find
in a hub with no zones goes to that hub by §2's cascade; "no hub yet" is only for what no hub covers.

**Amendment (Jason, 2026-11-27: "The only reason a hub is not available is to stage pilot data and allocate support
to it before launching"; "shouldnt our team be able to determine this without human hand holding"; recorded as
Operations & Policy (16)):** this replaces "its zones are drawn when that hub is scheduled (0048). Until then, finds
outside Ancient America are 'no hub yet'". Code suggests zones (sub-hubs) for every hub now, from regional economic and
tourism patterns, and the team adopts them: the IT Manager's (11) lane adopts each, Data & Content builds (Build & QA,
21); the CTO's Tier 4 is recorded before live (zones are routes, territory 3; re-placing records, territory 2), with the
Privacy & 0066 Guard's step where a zone touches a nation's land (territory 4). Every zone change keeps a redirect from
each old address, checked before live; zones' pages stay under the per-page noindex until the search lock lifts (0181);
each zone keeps its reasons, has Undo and a daily-summary line Jason can overturn (0180 §3). A zone a person drew
(Ancient America's 15, or Jason's own) is never changed by the team; a suggested change to one goes to him. A hub's
opening is only the support decision (HubOpening's stages). The suggester is code, not AI (0164 R5 untouched). Designed
after gate 3's W15 and 1A guides; built in Q1.
The CMO's (7) independent check (2026-11-27, 0180's note "roles check one another"), naming conditions: (1) code
proposes boundaries, names stay curated (0066 §1; brand.md §1–2): a name is never taken from CBSAs, commuting zones or
state tourism regions, and every zone name gets an Editorial Director (12) and CMO step; (2) no zone is named after a
nation or uses a nation's name or symbols (launch plan §10.3; 0181's note on nation land), and a town on or across a
reservation names a zone spanning that land only with the Privacy & 0066 Guard's (25) step on the name; (3) where a zone
overlaps a nation's land, that land shows as the nation's own named land inside the zone, with its visitor rules (0066
§3, 0058 §26), never as a non-Native tourism region.

### 6. What's already there

Data cleanup (0163 §8 stage 1b) re-places existing content through Phase 4's placement: a preview of every move, a
ledger and Undo. Areas seeded under the Ancient Borderlands that lie outside it are the first proposals.

## Build order (each a release Jason reviews)

1. Zone geography for Ancient America's 15 zones; Phase 4's placement step (`LOCATED_IN` edges); listings and the
   crawler's intake onto it; the default removed; Settings' "Default place" row removed.
2. Every other kind of content through Phase 4's placement; things that span places.
2b. Research rolled up to each level (§3): level dossiers feeding the guides, gaps per level, readiness on the meter;
    intent tags (§1a) on everything catalogued.
3. Data cleanup's re-placing of what exists.
4. The other 19 continental hubs, when Jason has sent them.

## Consequences

- Every find and every piece of content lands where it is, and a planned zone or hub fills up visibly before it opens.
- One rule, in one place (Phase 4), to test and to explain; the indexer's reading priority uses the same levels.
- Each level's guide and readiness come from what's actually gathered under it.
- More geography to keep (each zone's ZIPs and counties); it comes from regions.md and our locations data, not by hand.

- **Hold released: build it now** (Jason, 2026-11-27: "Yes"; decisions/0181). Every find and listing goes to its real
  area, and nothing auto-seeds duplicate areas. The existing duplicates are merged into their real areas, with Undo,
  never deleted. It changes existing records, so its Tier 4 review is recorded before live.
