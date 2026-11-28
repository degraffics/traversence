# Platform atlas: Get Local (`/directory/`)

*Draft for human review. Sources: the charter, brand.md, prd.md, site-audit/pages.md, decisions 0053, 0058, 0138, 0157 and 0170, and three code files: `Hours.php`, `PlaceScope.php` and `LocalWords.php`. Decisions 0053 and 0058 were truncated in what I received, and each file is capped, so this entry covers only part of the area. Anything I could not see is marked "not shown."*

**Path note.** The files sit under `traverse-3way-sync/sync-app/lib/`, but their headers name other locations: `api/lib/Hours.php` (Hours.php:6), `api/lib/PlaceScope.php` (PlaceScope.php:2) and `app/lib/LocalWords.php` (LocalWords.php:2). pages.md says the backend moved to `app/` on 2026-10-09 (decisions/0069, not shown). The two `api/lib` headers are stale against that move.

---

## (a) What each person can do, page by page

"Get Local" is the name visitors see for `/directory/` (pages.md, Notes). It is the Resident track (charter, Tri-Track; brand.md §3). brand.md §5 also says it serves "residents and explorers alike."

| Page / route | Resident / traveler (guest) | Member (signed in) | Business |
|---|---|---|---|
| `/` (Get Local door) | One of four doors under the hero search. Opens `/directory/` (0138 §3). The home feed's **Business** tab shows new, claimed or updated listings, newest first (0138 §4). | Same. | The tab invites "Add your business" when empty (0138 §5). |
| `/directory/` (Live) | Top row has the place and **List \| Map** (0058 §9). Filters live in the search's Quick picks: Near me, Open now, Has a phone, Has a website (0058 §9). Cards carry Call, Directions, Website and Peek, plus a Link icon. "Show more" (0053). A town or "near me" sorts nearest first, otherwise best match (0058 §9). | Adds the **Favorites** filter (linked or Address-Book businesses; 0053 "Directory filters"; 0058 §9). | Not shown. |
| Search panel (every page; `/api/search.php`, `/api/search_listings.php`) | Opens in place, with chips, a peek card and 5W reading (0053 §1, §8). Shows a "none here" box with nearby towns that have the thing (0058 §12). "Did you mean" (0058 §13). | Saved searches, up to 20, kept on the account (0053 step 6). "Your searches" phrases are stored on the device (0053). | Not shown. |
| Full-screen map (`TvMap`) | Pins. Approximate pins are shown as one dashed spot (0053, 2026-10-04). "My location" is used on this device only (0058 §5). | Favorites show as hearts and are never clustered (0058 §5). | Not shown. |
| `/listing/view.php?id=` (Live) | Hero, engage bar (views and comments, no Like), comments as reviews without stars (0058 §20). Guest questions (`/api/guest-inquiry.php`). "Closed, moved or listed twice? Tell us" (0058 §22). | Comment if 18+, up to 30 an hour (0058 §20). Save to Address Book, Link. | Claim or Manage on the hero (0058 §22). Photos & video, up to 8 (0058 §21). About add-ons by plan (0058 §22). |
| `/listing/location.php?id=` (Live) | Suggest a pin move; no sign-in needed. It goes to Review (0058 §15). | Same. Unverified claimants also go to Review (0058 §19). | Owner, manager or verified owner moves the pin and edits the address directly (0058 §15). |
| `/api/suggestion.php` ("Tell us") | Missing, fix, closed or duplicate reports. Up to 12 a day per device (0053). | Same. | Not shown. |
| `/hub/?hub=` `?geo=` `?cluster=` (Live, placeholder) | Town names on a cluster page open Get Local (`/directory/?town=…`; 0053). | Same. | Not shown. |
| `/claim.php?listing=`, `/listing/businessportal.php` (Live) | Not applicable. | Not applicable. | Claim, then manage the listing (pages.md §1, §4). |
| Business Insights | Not applicable. | Not applicable. | Last 30 days of Call, Directions, Website and contact counts, plus visits (0058 §9, §13). Route not shown. |
| `/directory/classic.php` | Archived: off the rail but still reachable (pages.md §1). | Not applicable. | Not applicable. |

---

## (b) How it works underneath

**Place scope: `PlaceScope.php`**

- It turns `?hub=`, `?geo=` or `?cluster=` into one SQL filter over `entities`. That filter is shared by `search_listings.php`, `suggest.php`, `place.php` and `places.php` (PlaceScope.php:4–6).
  - The most specific level wins (PlaceScope.php:64–73).
- **Injection safety:**
  - The fragment holds only integers read back from the database (PlaceScope.php:11–14).
  - Slugs must match `^[a-z0-9][a-z0-9-]{0,99}$` (PlaceScope.php:88).
  - An unknown slug yields `'1 = 0'` rather than being ignored (PlaceScope.php:85, 90–92, 100–101).
- **Matching:**
  - A hub matches its clusters, or its counties through `hub_counties` and `zip_coordinates` (PlaceScope.php:106–109).
  - A geo-hub matches its clusters, its clusters' primary ZIPs, or its counties (PlaceScope.php:122–126).
  - A cluster matches `micro_cluster_id` or the cluster's primary ZIP (PlaceScope.php:156–157).
- **Public statuses:** `unclaimed`, `claimed-unverified`, `pending-verification`, `verified` (PlaceScope.php:31).
- **Non-businesses:** mail and package drop-offs are listed and pinned but kept out of browse lists and can't be claimed (PlaceScope.php:33–55).
- **Display names:** the auto-seeded note is stripped (PlaceScope.php:57–61). ZIP-named clusters show as "City, ST" (PlaceScope.php:142–154).
- **Duplicate slugs** are resolved by `ORDER BY mc.id LIMIT 1` (PlaceScope.php:134). This is consistent with 0170's note that duplicate areas still exist and are to be merged.

**Opening hours: `Hours.php`**

- It parses free-text hours, including ranges, OSM-style `Mo-Fr`, "Weekends 10-4", 24-hour and past-midnight times (Hours.php:5–9, 62–93).
- A bare close time is read as afternoon, so "10-4" closes at 4 PM (Hours.php:84–85).
- Unreadable hours give `null`, never "open" (Hours.php:8, 96–103, 121). This backs 0053 §4's rule: "Unreadable hours never count as open."
- `status()` writes "Open · Closes 9 PM", "Closed · Opens tomorrow 8 AM" and "Closed today" (Hours.php:115–133).
- Day and minute are passed in by the caller. Whether the caller uses the visitor's local time (0053 §8) is not shown.

**Local dictionary: `LocalWords.php` (0157 §4)**

- What it builds from (LocalWords.php:185–223):
  - public listings' names, kinds and offerings;
  - the place store (`source_facts`), skipping ignored hosts;
  - town names in served states;
  - visible landmarks only, via `Landmarks::visibleSql()` (LocalWords.php:212, 222, which honours 0066 held names).
- **Business names are not kind clues.** Example: "Blue Rhino" made "blue" read as propane (LocalWords.php:169–171, 196).
- **Thresholds:**
  - A word is "known," so never offered a fix, if it is a name word or is used 3 or more times (LocalWords.php:72).
  - Phrases need 3 or more uses together, with a kind (LocalWords.php:230–233).
  - A word that sits with every kind is ignored (LocalWords.php:126).
- **Rebuild and storage:**
  - Rebuilt at most daily, off the visitor's path, under a file lock (LocalWords.php:15–16, 23, 140–162).
  - Stored in `app/storage`, which "never leaves the server" (LocalWords.php:15–16, 30).

**Not shown:**

- `/directory/` page code, `search_listings.php`, `UniversalSearch`, `SearchIndex`, `SearchPresets` and `search-panel.js`;
- `map-view.js`, `Geocoder`, `ListingLocation`, `PlatformCounts`, `Engage` and `HeroMedia`;
- `CrawlerIngest::resolveMicroClusterId`, `SourceIndex`, `Concepts`, `CategoryClassifier` and `SearchText`;
- the schema and migrations.

**Possible drift:** `LocalWords::PUBLIC` (LocalWords.php:24) is a separate copy of `PlaceScope::PUBLIC_STATUSES`. The comment at PlaceScope.php:30 asks only that `search_listings.php` be kept in step with it. LocalWords is not mentioned.

---

## (c) Decisions

| Decision | What it rules for Get Local |
|---|---|
| **0053** Universal Search, In Place | Search opens over the page, reads queries into chips, and sends "See all" to the directory. Filters: open now or on a day, phone, website, categories. Presets come from aggregate counts only, never personal targeting and never admin-curated (§5). Query text is never stored against a person (§7). The 5W model and one `search_index` (§8). Directory rebuilt as List \| Map. Census geocoding rules: never replace a point a person set; confidential listings never geocoded. "Tell us". (Truncated.) |
| **0058** Public profiles… | Map standard (§5). Directory layout, card anatomy, and outbound actions counted per listing/action/day, never who (§9). "None here" with nearby (§12). Insights: counts only, words shown at 3 or more visits, rare words deleted after 90 days (§13). Approximate ZIP pins and single-address geocoding (§14). Pin moves: visitor suggests, business saves (§15). Broad words, "near you" (§16). The Chameleon Filter in search: on Get Local businesses lead (§19). Content standard: no Like on listings, comments as reviews, no star ratings (§1, §20). Hero media (§21). About add-ons by `plan_tier` (§22). (Truncated after §24.) |
| **0138** Home page | Get Local is one of four doors (§3). Business tab: newest and nearest, no paid or engagement ranking (§4). Never invented activity (§5). |
| **0157** Our own index | Crawler page log. Pages read for structured data first. The place store is searched ("Named on pages we read," §3). `LocalWords` as dictionary and sense (§4). Per-source trust (§5). A paid second tier (Tavily, Claude) only where the free first tier falls short, under caps Jason sets, off by default. |
| **0170** Everything placed in the funnel | No default hub: removes `DEFAULT_GEO_HUB_SLUG` and the `ancient-borderlands` fallbacks. Placement happens in Phase 4 for all content. The Single Home Rule applies to everything. A listing's service area is its reach, not a second home. Claims and people's input win when valid (§4). Intent tags are stored for the Chameleon Filter (§1a). |

Referenced in these documents but not shown: 0007 (no pay for rank), 0042, 0044, 0045, 0050, 0052, 0055, 0064, 0066, 0069, 0093, 0133, 0164 and 0181.

---

## (d) Live vs coming

**Live (pages.md):**

- `/directory/`, `/listing/view.php`, `/listing/location.php`, `/claim.php` and `/listing/businessportal.php`;
- `/api/search.php`, `/api/search_listings.php`, `/api/suggestion.php`, `/api/pin.php`, `/api/locate.php` and `/api/count.php`.

**Live, placeholder:** `/hub/`.

**Built per decisions:**

- 0157 deliveries 1–6 (2026-11-13). Common Crawl partly: the town-naming query via Athena was not done.
- The "own quick counts" feature (0053 note, 2026-11-22).

**Coming or not built:**

- **Routes:** `/listing/{slug}` and clean hub addresses (pages.md §8). `/listing/confirm.php` and `/listing/requests.php` (release comms-all).
- **Placement:** 0170 is accepted but "Not built." On 2026-11-27 the hold was released to build. The other continental hubs' zones are still to be suggested.
- **Smaller gaps:**
  - "Add to a trip" is greyed until its saved-trips decision exists (0058 §20 note).
  - "See all" does not yet carry When (0053).
  - No "New" count yet on saved searches (0053).
  - The home Business tab is not yet "nearest first" (0138, full layout).
  - The Stock library (0138 §6).
  - Multi-page scans (0157 §7).

**Doc conflicts to resolve:**

- The PRD (2026-09-25) lists `/listing/{slug}` as Tier 2.1 and the Dual-Gateway landing as Tier 2.2. pages.md shows `/listing/view.php` live and `/` already showing both ways in. The PRD is older.
- 0058 §19 says search already follows the section ("the Chameleon Filter"). 0170 §1a calls the Chameleon Filter "not yet built." Its scope needs one definition.

---

## (e) Charter promises and economic results

**Immersion and connection.** The charter's mission is an engine "connecting users to the heartbeat of every community."

- *What delivers it:*
  - The Link button on cards and listings, and Save to Address Book (0058 §9, §20).
  - Comments as reviews (0058 §20).
  - Guest questions to unclaimed listings (0058 §22).
  - Town names on place pages open Get Local (0053).
- *Gap:* messaging to businesses is a later tier (PRD Tier 2.5). Its build state in the current code is not shown.

**True locality.** Charter: "True Locality & Context Without Noise"; the Single Home Rule.

- *What delivers it:*
  - One shared place filter across endpoints (PlaceScope.php:4–6).
  - Unknown places match nothing rather than everything (PlaceScope.php:85).
  - Drop boxes are kept out of business lists (PlaceScope.php:38–45).
  - The dictionary is built from local words and towns (LocalWords.php:185–219).
  - "Near" means within 40 miles (0058 §23).
- *Gap:* 0170 records that new finds outside built areas are filed under the pilot zone wherever they really are. That fallback code is not shown here. 0170 is the fix and is not yet built.

**Discovery that can't be bought.** Charter: "Transparent, Non-Manipulative Discovery"; "visibility … bought."

- *What delivers it:*
  - Presets come from aggregate signals, never admins (0053 §5).
  - What people open labels results but never ranks them (0157 §4.5, citing 0093).
  - Home tabs carry no paid or engagement ranking (0138 §4).
  - No stars (0058 §1).
- *Gap:* About add-ons grow with `plan_tier` (0058 §22). That is paid content depth, not placement, but the ranking code that would prove plan tier never affects order (`search_listings.php`, `UniversalSearch`) is not shown. 0007 is not shown either.

**Frictionless agency.** Charter: move "between macro-views and hyper-local details seamlessly."

- *What delivers it:*
  - Search opens in place, and Back closes the panel (0053 §1).
  - Filters live in the search (0058 §9).
  - List \| Map in one place.
  - Plain open/closed text (Hours.php:119–133).
  - Unknown hours are never claimed open (Hours.php:121).
  - "None here" with nearby alternatives (0058 §12).
- *Gap:* the many search-panel revisions in 0053 suggest the layout is still moving. Phone checks are documented elsewhere (0058 §11) and not shown in code.

**Data dignity.** Charter: "data minimization, clear boundaries."

- *What delivers it:*
  - Counts carry no user, device or IP (0058 §9, §13).
  - Query text is never stored against a person (0053 §7).
  