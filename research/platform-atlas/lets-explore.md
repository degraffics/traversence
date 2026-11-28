```markdown
# Platform atlas: Let's Explore

*Draft for human review. Built only from the attached documents and code. Anything not in them is marked **not shown**. Code is cited as `file:line`. Docs are cited by section.*

**Coverage caveat.** None of the page files for this area are attached: `website/discovery/index.php`, `website/hub/index.php`, `hub/build.php`, `place/*.php`, `journeys/*.php`, `includes/explore-hero.php`, `api/search.php`, the Engage files, and `app/lib/Guides.php`, `Landmarks.php`, `TravelLegs.php`, `OnDemand.php`, `UniversalSearch.php` are all **not shown**. Several attached sources are cut off:
- `PlaceFinder.php` (stops at line 495) and `Journeys.php` (stops at line 538);
- decisions 0058 (stops in §24) and 0133.

What follows is the library layer plus the documents.

---

## (a) What each kind of user can do, page by page

Routes and statuses come from `site-audit/pages.md`. "Get Local" and "Let's Explore" are the public names for `/directory/` and `/discovery/` (pages.md, Notes).

| Route | Status | Resident | Traveler | Member (signed in) | Business |
|---|---|---|---|---|---|
| `/discovery/` (overview, map, regions, outdoors, things to do, journeys, Trip Planner `?view=plan`) | Live | Browse the place they live in | Main entry. "Explore: \<place\>" hero, then a Regions / Experiences / National Parks strip (0058 §18) | Same | Shown in picks and in search as "connections" (0058 §19) |
| `/hub/?hub=` `?geo=` `?cluster=` | Live, placeholder | "Live here" dial: Essentials, Local, Support & wellness first (0131 §3) | "Visiting" order (0131 §3, addendum) | Comments | Named, linked and pinned as a pick when a guide names it (0131 §4) |
| `/hub/?town=` `?sub=` | In 0131 §1 table, **not in pages.md** | City and neighborhood guides | Same | — | — |
| `/hub/build.php?city=&state=&q=` | "Live once uploaded" | — | A first-look guide for an uncovered place in about 2 min (0147 §1a) | "Notify me when it's ready" (0147 §1b) | — |
| `/place/landmark.php?id=` | Live | — | USGS / Wikidata natural and historic places | Like, comment | — |
| `/place/recreation.php?id=` | Live | — | Recreation.gov places, "Reserve a campsite" (0058 §10) | Like, comment | — |
| `/experiences/view.php`, `/journeys/view.php` | Live | Read | Read | Like, comment (18+, 30 an hour; 0058 §20) | Journeys posted as a listing show a Business badge (0058 §2) |
| `/journeys/suggested.php?id=` | "Live once uploaded" | — | Rule-built route through a guide's places (0146) | Comment | May ask to be left out: flag read at `JourneyPlan.php:153`, portal switch not built (0146 Built) |
| `/guide/how.php`, `/guide/resources.php` | Live | How-to steps; who to call | Same | — | — |
| `/user/journeys/`, `compose.php` | Live | — | — | Write journeys as a contributor (18+, public profile; `Journeys.php:157-164`) | Post as the listing if their role allows it (`Journeys.php:118-130`) |
| `/user/trips/`, `/user/trips/trip.php`, `/trip/?t=`, `/guide/personal.php` | Live (0174, **not shown**) | — | — | Trips, shared trips, Personal Guides | — |
| `/user/experiences/` | Live | — | — | Their own experiences | — |

**Business side, on listing pages that link out from this area:**
- Hero photos and video, up to 8 (`HeroMedia.php:10, 21-28`).
- Business Insights counts for calls, directions and website taps (0058 §9).

---

## (b) How it works underneath

### Geography scope
- `PlaceScope::resolve` (`PlaceScope.php:75-159`) turns `hub` / `geo` / `cluster` into a SQL filter on `entities`.
  - The most specific level wins (`:63-73`).
  - An unknown slug matches nothing, `'1 = 0'` (`:85, 88-92`).
- Public statuses are listed at `:31`.
- Mailboxes are not businesses (`:38-55`).
- Auto-seeded "ZIP 85901" clusters are shown by their town name (`:142-154`).

### Journeys, written by people
`Journeys.php`:
- **Scope keys:** a journey stores its place and the places above it (`:186-202`). The feed is published-only, newest first, with no engagement ranking (`:476-510`, sort at `:508`).
- **Save rules** (`:217-284`):
  - the account-standing guard (`:219`);
  - an automatic content check holds the journey as a draft (`:247`);
  - a Content Creator's piece goes to an editor (`:248`);
  - content tags are refreshed for the Chameleon Filter (`:278`).
- **Photos:** re-encoded to JPEG, so all EXIF is dropped. Only the orientation is read (`:347-358`).
- **Pins:** a pin on a nation's land is moved to a public place or dropped (`:101-106, 263-267, 361`).
- **Co-authors:** up to 5. Blocked people are skipped. Accepting requires the 18+ gate (`:419-449`).

### Suggested journeys, built by rules
`JourneyPlan.php`. No AI and no cost (header `:2-7`).
- **Themes** are `day`, `outdoors`, `heritage`, `food`, `culture`, `shopping`, scored by guide words (`:19-26`).
- **Candidates** (`:63-143`) come from the guide's picks, landmarks and Recreation.gov places.
  - Protected landmarks are skipped (`:110, 118`).
  - Outside our areas, the OpenStreetMap copy is used for places to eat (`:90-103`).
- **Exclusions** (`dropListings`, `:147-165`): listings that opted out, pins only at a ZIP centre, confidential listings, and mailboxes.
- **Greedy build** (`:228-275`):
  - score = travel minutes, minus a bonus if the guide names the stop, plus a penalty for unknown hours (`:250`);
  - meals are placed in their slots (`:278-284`).
- **Storage** (`buildPlace`, `:322-357`): staff edits and pins are kept (`:346`), and themes that become too thin are deleted (`:353`).
- **Job:** `tick` runs 3 guides per run, weekly (`:360-375`).

### Guides and research (`Guides.php` not shown)
- **Census profile:** `PlaceProfile::combine` (`:260-315`), with its citation (`:24-26`).
- **Resource lines:** a number is published only if it appears on the provider's own page (`ResourceGuides.php:120-142`). Search matches by situation (`:165-183`).
- **How-to guides** (`HowGuides.php`):
  - only official `.gov`, `.edu` or state `.us` sources (`:116-121`);
  - publishing needs 3 or more sourced steps and confirmation that they are in our own words (`:214-221`);
  - a copy check runs against non-open licences (`:247-265`).
- **Places on demand from Wikidata** (`PlaceFinder.php`):
  - linking names in guides: `forGuide` `:32-70`;
  - sweeps by town, by state tile, and by searched name: `:99-237`;
  - AI "about" text, only when no data exists and inside the AI cap: `:340-373`;
  - an interest score from Wikipedia pageviews, sitelinks and guide mentions: `:403-429`.
- **Photos:** `PlacePhotos::find` uses only freely licensed Wikimedia Commons images, credited, and skips private places (`:29-87, 68, 125-130`).
- **OpenStreetMap layer** (`OpenPlaces.php`):
  - kept in its own table and never copied into listings (`:13-19`);
  - Overpass is retired, so data comes from our Geofabrik copy only (`:22-24, 115, 167-170`);
  - historic or protected names are never stored (`:404-405`).
- **Quarterly audit:** `GuideAudits` draws a sample (`:32-48`), runs a pre-check for "a way in" near protected names (`:51-73`), and records a person's mark (`:84-92`).

---

## (c) Decisions

| Decision | What it rules (for this area) |
|---|---|
| 0058 | §5 map; §6 Let's Explore menu; §13 counts, never who; §18 Explore hero; §19 Chameleon search: place content first, businesses as connections; §20 content-page standard (Engage bar, comments, no stars); §21 hero media; §22 search widens when a place has nothing; §24 landmarks, with sacred or nation land held for a person. *Truncated after §24.* |
| 0131 | One guide per funnel tier (continent to neighborhood); one template; Visiting / Live here dial; every name links; weather (NWS) and roads (AZ 511); Claude drafts; auto-publish at score 70 (reaffirmed 0181) |
| 0133 | Crawler rule book: curators not controllers, protected-information tiers, source register, Topic index. **Superseded by 0164 (not shown)** |
| 0146 | Suggested journeys from guides: themes, rules-only, openrouteservice and `travel_legs`, fairness (no pay), audit paths |
| 0147 | Guides and journeys on demand: first look in about 2 min; full guide in 24–48 h; up to 50% of the daily AI allowance |
| Referenced, **not shown** | 0003 / 0007 (Chameleon Filter, no pay-for-rank); 0043; 0056; 0059 (Experiences); 0061; 0063; 0065 (OSM layer); 0066 (protected places); 0148; 0164; 0174 (trips) |

---

## (d) Live vs coming

**Live** (pages.md): `/discovery/`, the place pages, landmark and recreation pages, journeys, experiences, guides, trips.
- `/hub/` is marked "Live, placeholder".
- `/hub/build.php` and `/journeys/suggested.php` are "Live once uploaded".

**Coming or not built (from the docs):**
- Clean hub addresses `/{hub-slug}/...` (pages.md §8, 0013).
- `/guide/{slug}` editorial articles with `[directory_feed]` (pages.md §8; PRD Tier 2.3).
- `#tag` theme pages (0058 §3: "Not built yet").
- Events under Things to do: "inactive until built" (0058 §6).
- From 0146 "Still to build":
  - the business portal switch to leave suggested journeys;
  - staff edit and pin in the admin;
  - saving to the Trip Planner;
  - Claude's lines.
- From 0147: Insights → Guides. Follow / notify is marked built in §1b, but still listed as "to build" in the Built note. **The docs disagree here.**
- Public topic pages: "a later decision" (0133).
- GO Codes at markers: "later" (0133).
- NPS closure alerts: once the key is added (0146 §7).
- A tile-provider agreement is needed before launch (0058 §5).

**Inconsistencies to resolve:**
- 0058 §20 (2026-11-19) says "Add to a trip is greyed, 'coming'", while pages.md lists `/user/trips/` as Live (0174).
- PRD Tier 2.2 (2026-09-25) still lists the "Let's Explore narrative entry point" and the "interactive corridor map" as active development. pages.md shows `/discovery/` with a map as Live.
  - The PRD looks stale.
  - A *corridor* map specifically is **not shown** in any code or decision attached.

---

## (e) Charter promises, economics and gaps

### Immersion and connection
Charter, Mission: "immersion and connection engine … heartbeat of every community"; Let's Explore is the "top-down, regional narrative" track (Tri-Track).
- **Delivered:**
  - guide tiers from region to neighborhood (0131 §1);
  - "Walking in the footsteps" history anchored to places you can stand on (0133 Phase 5);
  - rule-built day and theme journeys timed to hours and daylight (`JourneyPlan.php:204-219, 228-275`);
  - member journeys feeding the hero and the feeds (`Journeys.php:476-510`);
  - on-demand guides so there is "never an empty page" (0147 §1).
- **Gaps:**
  - The suggested-journey "why" falls back to stock sentences when the guide text is thin (`JourneyPlan.php:310-313`).
  - Every suggestion is timed for a Saturday only (`:33, 204-212`).

### True locality without noise
Charter, "Why This Matters" and the Single Home Rule / taxonomy.
- **Delivered:**
  - geographic scoping by hub, geo-hub and cluster rather than categories (`PlaceScope.php:96-158`);
  - "Near" capped at 40 miles (0058 §23);
  - a place's own content ranked ahead of businesses on Let's Explore (0058 §19).
- **Gap 1, resource lines in search.** 0131 §2 says Essentials shows "this place's lines first, then those of every place above it". `ResourceGuides::forSituations` looks up only `'all'` and the place's own key (`ResourceGuides.php:168`), not the ancestors. The guide page's own Essentials code is **not shown**.
- **Gap 2, on-demand places outside five states.** The OpenStreetMap copy covers only AZ, NM, UT, CO and NV (`OpenPlaces.php:209`), and Overpass is retired (`:22-24`). So for on-demand places like 0147's Dana Point, CA:
  - `JourneyPlan`'s OpenStreetMap eating stops (`JourneyPlan.php:90-103`) would come back empty;
  - 0147's "OpenStreetMap's places to eat" step cannot be fulfilled as written. *Infer