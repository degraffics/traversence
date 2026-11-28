# Platform atlas: Let's Explore (merged from pass 1 and pass 2)

*Draft for human review. Each claim is tagged with the pass that supports it: **[both]**, **[P1]** or **[P2]**. A claim tagged with one pass is single-pass. Citations are each pass's own. Where both passes cite the same thing with different line numbers, both are given.*

**Coverage and truncation.**
- **Both passes are cut off.**
  - Pass 1 stops partway through (e), mid-sentence ("*Infer").
  - Pass 2 stops partway through the (c) decisions table, at the 0133 row. It has no (d) or (e).
- **Sections (d) and (e) below are therefore single-pass (P1).** Some of their points are backed by material elsewhere in pass 2, and this is noted where it applies.
- **Source files.**
  - [both] `PlaceFinder.php` and `Journeys.php` are attached but cut off.
  - [P1] `PlaceFinder.php` stops at line 495 and `Journeys.php` at line 538. Decisions 0058 (within §24) and 0133 are also cut off.
  - [P2] There are 14 attached library files.
- **Library path.** Passes differ: P1 gives `app/lib/`, P2 gives `sync-app/lib/`.
- **Not shown.**
  - [both] Page templates (`website/discovery/`, `website/hub/`), `Guides.php`, `Landmarks.php`, `TravelLegs.php`, `OnDemand.php`, `UniversalSearch`, Engage.
  - [P1] `hub/build.php`, `place/*.php`, `journeys/*.php`, `includes/explore-hero.php`, `api/search.php`.
  - [P2] `ListingSafety`, `Weather`, `Roads511`, `commercial.md`, decision 0164.
- **How page claims are sourced** [both]: they rest on `site-audit/pages.md` and the decisions, not on page code. "Let's Explore" is the public name for `/discovery/` (pages.md, Notes). The charter's Tri-Track calls this the "top-down, regional narrative" track.

---

## (a) What each kind of user can do, page by page

| Route | Status (pages.md) | Traveler | Resident | Member | Business |
|---|---|---|---|---|---|
| `/discovery/` | Live [both] | Main entry [both]. Menu: overview, map, regions, experiences, national parks, things to do, journeys, Trip Planner `?view=plan` (0058 §6) [both]. Sub-items under Things to do: Outdoors, Food & drink, Culture & heritage, Events, Stories & guides [P2]. Hero "Explore: \<place\>" then a Regions / Experiences / National Parks strip (0058 §18) [P1]. | Browse their own place [P1] | Same [P1] | Shown in picks and in search as "connections" (0058 §19) [P1] |
| `/hub/?hub=` `?geo=` `?cluster=` | **Live, placeholder** [both] | Guide per funnel tier: story, Essentials (weather, roads, who to call), next stops, landmarks, eat & drink, things to do, suggested journeys, comments (0131 §1) [P2]. "Visiting" order (0131 §3) [P1]. | "Live here" dial leads with Essentials, Local, Support & wellness (0131 §3) [both]. Arriving from Get Local starts on Live here [P2]. | Comments [P1] | Named, linked and pinned as a pick when a guide names it (0131 §4) [P1] |
| `/hub/?town=` `?sub=` | In 0131 §1, **not in pages.md** [P1] | Town and neighborhood guides [P1] | | | |
| `/hub/build.php?city=&state=&q=` | Live once uploaded [both] | First-look guide for an uncovered place in about 2 min (0147 §1a) [both] | | "Notify me" (0147 §1b) [both]. P1 flags this as disputed, see (d). | |
| `/place/landmark.php?id=` | Live [both] | Passes differ: P1 says USGS / Wikidata natural and historic places; P2 says a natural landmark from USGS names (0058 §24) | | Like, comment [P1] | |
| `/place/recreation.php?id=` | Live [both] | Recreation.gov place with Reserve (0058 §10) [both]. "Reserve a campsite" [P1]. See on map, Directions [P2]. | | Like, comment [P1] | |
| `/experiences/view.php`, `/journeys/view.php` | Live [both] | Read [both] | Read [P1] | Like and comment, 18+ (0058 §20) [both]. Limit of 30 an hour [P1]. | Listing journeys show a Business badge (0058 §2) [both]. They don't count toward Top Contributors [P2]. |
| `/journeys/suggested.php?id=` | Live once uploaded [both] | Rule-built route through a guide's places (0146) [both], timed against opening hours [P2] | | Comment [P1] | Opt-out flag read at `JourneyPlan.php:153`, but the portal switch is not built (0146) [both] |
| `/guide/how.php`, `/guide/resources.php` | Live [both] | How-to steps; who to call [both] | Same [both]. P2 cites `ResourceGuides.php:165-183` and `HowGuides.php:291-300`. | | |
| `/user/journeys/`, `compose.php` | Live [both] | | | Write journeys as a contributor. Passes differ on the requirements: P1 says 18+ and a public profile (`Journeys.php:157-164`); P2 adds verification (`:157-181`). Co-authors, pins and a mature-themes gate (0058 §7) [P2]. | Post as the listing if their role allows. Passes differ on cites: P1 `:118-130`, P2 `:117-130, 241-244`. |
| `/user/trips/`, `trip.php`, `/trip/?t=`, `/guide/personal.php` | Live [both]. P1 attributes this to 0174, not shown. | Shared trip; Personal Guide [P2] | | Trips, shared trips, Personal Guides [both] | |
| `/user/experiences/` | Live [both] | | | Their own experiences [both] | |

**Business**
- Owners and managers can set up to 8 hero photos and videos (`HeroMedia.php:10, 21-28`) [both]. P2 says "owners and managers".
- Picked by fit, never by pay (0131 §3; 0146 §3) [P2].
- Business Insights counts for calls, directions and website taps (0058 §9) [P1].

**Member AI Assist** [P2]
- At `/ai/` (0133).
- Needs the member's consent.
- Paid plans only, with 3 free asks a day.

**Staff** [P2]
- Admin content tools: `/admin/journeys.php`, `landmarks.php`, `stories.php`, `how-guides.php`, `resource-guides.php`, `guide-edit.php`.
- The audit URL `/admin/insights/audit` is emailed to admins (`GuideAudits.php:45-46`). It is not in pages.md and may be served by `/admin/router.php`, which is not shown.

---

## (b) How it works underneath

### Place scope (`PlaceScope.php`)
- **Filter** [both]: turns `hub` / `geo` / `cluster` into a SQL filter, and the most specific level wins (`:63-73`).
  - The resolver is `resolve` at `:75-159` [P1].
- **Unknown slug** [both]: matches nothing, `'1 = 0'`. Passes differ on cites: P1 `:85, 88-92`, P2 `:83-92`.
- **Auto-seeded ZIP clusters** [both]: shown under their town name (`:142-154`).
- **Mailboxes / drop boxes** [both]: not treated as businesses. Passes differ on cites: P1 `:38-55`, P2 `:38-45`.
- **Public statuses** [P1]: listed at `:31`.
- **Hub coverage** [P2]: a hub covers its clusters plus the ZIPs in its assigned counties (`:106-109`).
- **Gap** [P2]: only three levels are parsed. 0131 §1 defines `?town=` and `?sub=`, and `ResourceGuides.php:41-42` knows about them, but `PlaceScope::requested` does not. P1 is consistent with this: it notes that `?town=` and `?sub=` are missing from pages.md.

### Journeys written by people (`Journeys.php`)
- **Scope keys** [both]: a journey is stored against its place and the places above it (`:186-202`).
  - Only three levels are accepted (`:230`) [P2].
- **Feed** (`:476-510`): passes differ on what it does.
  - P1: published only, newest first, no engagement ranking (sort at `:508`).
  - P2: it mixes journeys with published `place_stories`.
- **Save rules** [both]: an automatic content check holds the journey as a draft (`:247`), and a Content Creator's piece goes to an editor (`:248`).
  - Account-standing guard at `:219`; Chameleon Filter tag refresh at `:278` [P1].
- **Nation-land pins** [both]: moved to a public place or dropped (`:101-106, 263-267, 361`).
- **Photos** [both]: re-encoded.
  - P1 adds: converted to JPEG, EXIF dropped, only orientation read (`:347-358`).
  - P2 cites `:335-366`.
- **Co-authors** [P1]: up to 5, blocked people skipped, 18+ gate to accept (`:419-449`).
- **Other details** [P2]: magazine sections map onto journey categories (`:29-30`); the reuse default is "Shown on Traversence only" (`:32, 271`).

### Suggested journeys (`JourneyPlan.php`)
- **No AI, no cost** [P1]: per the header (`:2-7`).
- **Themes** [both]: six themes with trigger words (`:19-26`). P1 names them: day, outdoors, heritage, food, culture, shopping.
  - Stay times per kind of stop (`:28-29`) [P2].
- **Candidates** [both]: from the guide's picks, its landmarks (protected ones excluded, `:110, 118`) and Recreation.gov places (`:63-143`).
  - Outside covered areas, eating stops come from OSM. Passes differ on cites: P1 `:90-103`, P2 `:88-103`.
  - These stops are labelled as OSM's, never as listings [P2].
- **Dropped** [both]: opted-out listings, pins only at a ZIP centre, confidential listings and mailboxes (`:147-165`).
- **Routing** [both]: greedy (`:228-275`), with the score at `:250`.
  - Passes differ on the score's parts. P1: travel minutes, minus a bonus if the guide names the stop, plus a penalty for unknown hours, with meals placed in their slots (`:278-284`). P2: travel minutes, plus a bonus for a named stop, plus meal timing.
  - "No payment input exists in the score" [P2].
- **Timing** [both]: journeys are timed for a Saturday (`:33, 204-212`).
  - The next Saturday, with real sunrise and sunset [P2].
- **Rebuilds** [both]: weekly. Staff edits and pins are kept, and themes that become too thin are deleted (`:322-357, 360-375`).
  - Edits and pins kept at `:346`, thin themes deleted at `:353`, 3 guides per run [P1].
- **Hub guides** [P2]: regional (hub) guides get no journeys (`:65, 326`).

### Places on demand (`PlaceFinder.php`, cut off)
- **Guide names** [both]: names in guides are found via Wikidata and linked (`:32-70`).
- **Sweeps** [both]: by town, by state tile and by searched name (P1 `:99-237`).
  - P2 splits these as `:99-131`, `:142-169` and `:180-199`.
  - Searches containing a number or `@` are never sent out (`:205`) [P2].
- **AI "about" text** [both] (`:340-373`):
  - P1: only when no data exists and within the AI cap.
  - P2: only for visible places with no summary, skipping protected places.
- **Ranking** [both]: landmarks are ranked by interest (`:403-429`).
  - Wikipedia pageviews, sitelinks and guide mentions [P1].

### OpenStreetMap layer (`OpenPlaces.php`)
- **Separate table** [both]: never copied into listings (`:13-19`).
- **Source** [both]: Overpass is retired, so only the Geofabrik copy is used (`:22-24, 167-170`).
  - Cite `:115` [P1].
  - Covers AZ, NM, UT, CO and NV (`:209`) [P2]. P1 gives the same states in (e).
- **Protected names** [both]: historic or protected names are never stored. Passes differ on cites: P1 `:404-405`, P2 `:401-405`.
- **Nation land** [P2]: shows businesses only, with the nation's note (`:126-133`).

### Photos
- **`PlacePhotos`** [both]: Wikimedia Commons photos, credited, and private places are never looked up.
  - P1 cites `:29-