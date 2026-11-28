# Platform atlas: Let's Explore

`research/platform-atlas/lets-explore.md`. **Draft for human review.**

**Sources.** The attached documents and the 14 attached library files under `sync-app/lib/`. Each file is capped, and `PlaceFinder.php` and `Journeys.php` are cut off partway.

**Not shown.** No page templates (`website/discovery/`, `website/hub/`), `Guides.php`, `OnDemand.php`, `TravelLegs.php`, `Landmarks.php`, `UniversalSearch`, `Engage`, `ListingSafety`, `Weather`, `Roads511`, `commercial.md`, and decision 0164, which supersedes 0133. Claims about pages rest on `site-audit/pages.md` and the decisions, not on page code.

The charter calls this area the Traveler ("Let's Explore") Track: "top-down, regional narrative and exploration" (charter, Tri-Track). Visitors see `/discovery/` under that name (pages.md, Notes).

---

## (a) What each person can do, page by page

### Traveler (no sign-in needed)

| Route | What they do | Status (pages.md) |
|---|---|---|
| `/discovery/` | The Discovery Dashboard. Overview, Map, Regions, Experiences, National Parks, Things to do (Outdoors, Food & drink, Culture & heritage, Events, Stories & guides), Journeys, and the Trip Planner (`?view=plan`). The menu order is set in 0058 §6. | Live |
| `/hub/?hub=` `?geo=` `?cluster=` | The place guide for each funnel tier (0131 §1). Each has a story, Essentials (weather, roads, who to call), next stops, landmarks, eat & drink, things to do, suggested journeys and comments. | **Live, placeholder** |
| `/hub/build.php?city=&state=&q=` | Watch a "first look" guide build in about 2 minutes for a place Traversence doesn't cover yet (0147 §1a). | Live once uploaded |
| `/journeys/suggested.php?id=` | A rule-built route through a guide's places, timed against opening hours (0146). | Live once uploaded |
| `/journeys/view.php?id=` | Read a contributor's journey or a Traversence journey. | Live |
| `/experiences/view.php?id=` | One experience. | Live |
| `/place/landmark.php?id=` | A natural landmark from USGS names (0058 §24). | Live |
| `/place/recreation.php?id=` | A Recreation.gov place: See on map, Directions, Reserve (0058 §10). | Live |
| `/guide/how.php?id=` | A how-to guide drawn from official pages. | Live |
| `/guide/resources.php?id=` | Who to call in an area, for help that isn't a business. | Live |
| `/trip/?t=`, `/guide/personal.php?id=` | A shared trip; a member's published Personal Guide. | Live |

### Resident

Residents use the same guides through the **Visiting / Live here** dial. Live here leads with Essentials, Local and Support & wellness, and arriving from Get Local starts on Live here (0131 §3). They also get resource-guide lines in search (`ResourceGuides.php:165-183`) and how-to guides (`HowGuides.php:291-300`).

### Member (signed in)

- **Write journeys** at `/user/journeys/` and `compose.php` after becoming a contributor. A contributor must be 18+, have a public profile and pass verification (`Journeys.php:157-181`). Journeys can carry co-authors, pins and a mature-themes gate (0058 §7).
- **Experiences and trips** at `/user/experiences/`, `/user/trips/` and `/user/trips/trip.php`.
- **Engage:** comment (18+) and like on content pages (0058 §20).
- **Ask to be told** when an on-demand guide is ready, via "Notify me" (0147 §1b).
- **AI Assist** at `/ai/` (0133). It needs the member's consent, and is limited to paid plans with 3 free asks a day.

### Business

- **Hero photos and video:** owners and managers can set up to 8 (`HeroMedia.php:10, 21-28`).
- **Post journeys as the listing.** This needs a team role with posting rights (`Journeys.php:117-130, 241-244`). The journey shows a Business badge, and business journeys don't count toward Top Contributors (0058 §2).
- **Picked by fit, never by pay.** Businesses appear in guide picks and journeys by fit, never by payment (0131 §3; 0146 §3).
- **Opting out of suggested journeys:** the flag is read (`JourneyPlan.php:153`), but the portal switch is not built (0146, "Still to build").

### Staff (admin)

- **Content tools:** `/admin/journeys.php` (five views), `/admin/landmarks.php`, `/admin/stories.php`, `/admin/how-guides.php`, `/admin/resource-guides.php`, `/admin/guide-edit.php`.
- **Quarterly guide audit:** the URL `/admin/insights/audit` is emailed to admins (`GuideAudits.php:45-46`). That route isn't listed in pages.md; it may be served by `/admin/router.php` (not shown).

---

## (b) How it works underneath

**Place scope** (`sync-app/lib/PlaceScope.php`)
- Turns `?cluster`/`?geo`/`?hub` into a SQL filter, and the most specific one wins (`:63-73`).
- A bad slug matches nothing (`'1 = 0'`) rather than being ignored (`:83-92`).
- A hub covers its clusters plus the ZIPs in its assigned counties (`:106-109`).
- Clusters auto-seeded from a ZIP are shown to the public under their town name (`:142-154`).
- Drop boxes are listed but are not businesses (`:38-45`).
- **Gap:** only three levels are parsed. Decision 0131 §1 also defines `?town=` and `?sub=`, and `ResourceGuides.php:41-42` knows town and sub areas, but `PlaceScope::requested` doesn't.

**Journeys** (`Journeys.php`)
- A journey is scoped to a place and every place above it, `|hub|geo_hub|cluster|` (`:186-202`). Only those three levels are accepted (`:230`).
- The feed mixes journeys with published `place_stories` (`:476-510`).
- Magazine sections map onto journey categories (`:29-30`).
- An automatic content check holds a journey as a draft (`:247`). A Content Creator's piece goes to an editor (`:248`).
- Pins on a nation's land are moved to a public place or dropped (`:101-106, 263-267, 361`).
- Photos are re-encoded (`:335-366`).
- The reuse default is "Shown on Traversence only" (`:32, 271`).

**Suggested journeys** (`JourneyPlan.php`)
- **Themes and stay times:** six themes, each with its trigger words (`:19-26`), and stay times per kind of stop (`:28-29`). Journeys are timed for the next Saturday, with real sunrise and sunset (`:33, 204-212`).
- **Candidates** come from the guide's picks, its landmarks (protected ones excluded, `:110, 118`) and Recreation.gov places (`:63-143`). Outside covered areas, eating stops come from OpenStreetMap and are labelled as OSM's, never as listings (`:88-103`).
- **Dropped:** listings that opted out, are approximate (ZIP centre), are confidential, or are mailboxes (`:147-165`).
- **Routing:** greedy. The score is travel minutes, plus a bonus if the guide names the place, plus meal timing (`:228-275`, score at `:250`). **No payment input exists in the score.**
- **Rebuilds:** weekly. Staff-edited or pinned journeys are kept; themes left too thin are deleted (`:322-357, 360-375`).
- Regional (hub) guides get no journeys (`:65, 326`).

**Places on demand** (`PlaceFinder.php`, partly shown)
- Finds names in a guide that Traversence doesn't hold yet, via Wikidata, and links them as landmarks (`:32-70`).
- Sweeps by town (`:99-131`), by state tile (`:142-169`) and by searched names (`:180-199`). Searches containing a number or `@` are never sent out (`:205`).
- Asks Claude to write "about" text only for visible places with no summary. Protected places are skipped (`:340-373`).
- Ranks landmarks by interest (`:403-429`).

**OpenStreetMap layer** (`OpenPlaces.php`)
- Kept in its own table and never copied into listings (`:13-19`).
- The live Overpass service is retired. Only the Geofabrik copy is used, for AZ, NM, UT, CO and NV (`:22-24, 167-170, 209`).
- Never keeps historic or protected names (`:401-405`).
- On a nation's land, it shows businesses only, with the nation's note (`:126-133`).

**Photos**
- `PlacePhotos.php` finds Wikimedia Commons photos, credited (`:3-9, 129`). Private places are never looked up (`:68-70`). Places opened with no photo go first in the queue (`:92-102`).
- `HeroMedia.php` handles owner-chosen photos, which are re-encoded (`:62-88`).

**Supporting guides and data**
- **Resource guides** (`ResourceGuides.php`): a phone number shows only after it is found on the provider's own page (`:7-9, 120-142`). Only verified entries reach search (`:172`).
- **How-to guides** (`HowGuides.php`):
  - triggered by 3 or more searches, counts only (`:23, 56-63`);
  - drawn from `.gov`/`.edu`/`.mil` pages only (`:116-121`);
  - publishing needs at least 3 sourced steps and a check that nothing was copied (`:214-221, 247-265`).
- **Census profile** (`PlaceProfile.php`): ACS figures with a citation and margins of error (`:17-26`). Medians are approximate when several ZIPs are combined (`:274-282`).
- **Quarterly audit** (`GuideAudits.php`): samples 10% of live guides, between 5 and 40 (`:13-15, 32-48`). A pre-check flags any "way in" to a protected place and any open takedowns (`:51-73`). A person marks each passed, fixed or taken down (`:84-92`).

---

## (c) Decisions

| # | What it rules (as shown) |
|---|---|
| 0003 | Chameleon Filter: search follows the section. On Let's Explore, place content leads and businesses follow (0058 §19). Text not attached. |
| 0005 | A person approves what the system writes. Amended so guides publish themselves by score (0131 addenda). Text not attached. |
| 0007 | Money never buys placement (cited by 0131 §3, 0146 §3, 0147 §3). Text not attached. |
| 0013 | Clean hub/geo-hub/cluster addresses. Coming (pages.md §8). |
| 0043 | A guide at every level; Census place profiles (`PlaceProfile.php:8`). |
| 0056 | Journeys, contributors, the six-tier funnel (`Journeys.php:11-22`). |
| 0058 | Public profiles, journey management, 18+ accounts, the map, Explore navigation, the Explore hero, the content-page standard, landmarks, counting without "who". |
| 0059 | Destinations became Experiences (0058 §18 update). |
| 0061 | Resource guides: a number is shown only if found on an official page. |
| 0063 | How-to guides from official pages. |
| 0065 | OpenStreetMap kept as a separate, credited layer (`OpenPlaces.php`). |
| 0066 | Protected and sacred places are held, never given a "way in". |
| 0131 | One guide per funnel tier; one template; Visiting / Live here dial; names link to records; auto-publish at score 70. |
| 0133 | The craw