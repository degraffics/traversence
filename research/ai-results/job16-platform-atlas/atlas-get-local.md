# Platform atlas: Get Local

*`research/platform-atlas/get-local.md`. **Draft for human review.***

**Sources and limits.** This entry uses only the attached material:

- **Documents:** charter.md, brand.md, prd.md, site-audit/pages.md, ADRs 0058, 0138, 0053, 0170 and 0157. ADRs 0058 and 0053 are truncated.
- **Code:** three library files, each capped:
  - `sync-app/lib/Hours.php`
  - `sync-app/lib/PlaceScope.php`
  - `sync-app/lib/LocalWords.php`

The code paths are shown as they appear in the sync mirror. The file headers name their homes as `api/lib/…` (Hours.php:6, PlaceScope.php:2) and `app/lib/…` (LocalWords.php:2). pages.md says the backend moved to `app/` under 0069.

Not shown: the `/directory/` page, `/api/search_listings.php`, `UniversalSearch`, `SearchIndex`, `Geocoder`, `ListingLocation`, `PlatformCounts`, `HeroMedia`, `Engage`, the JavaScript, and the database schema. Anything said about these comes from the documents only.

---

## (a) What each person can do, page by page

"Get Local" is the visitor-facing name for `/directory/` (pages.md, Notes; brand.md §3). The track's focus is "everyday utility, civic life, municipal data, local routines" (brand.md §3).

### Resident

| Page / route | What they can do | Source |
|---|---|---|
| `/` (home) | Use the **Get Local** door, one of four doors under the hero search | 0138 §3; pages.md §1 |
| `/directory/` | See businesses near them in **List \| Map**. The top row is the place ("📍 St. Johns ▾"), then the title and count, then removable filter chips. | 0058 §9; pages.md §1 |
| Search dropdown (Quick picks) | Use Near me, Open now, Has a phone, Has a website and Favorites. Filters live in the search, not on the page. A town or "near me" lists nearest first; otherwise results are by best match. | 0058 §9; 0053 |
| Directory cards | The name links to the listing. Call, Directions, Website and Peek sit under it, with Link in the corner. Each card shows "Open · Closes 5 PM" or "Closed · Opens tomorrow 8 AM". | 0058 §9; 0053; Hours.php:115-133 |
| "None here" box | When nothing matches, a resident can **Recommend one** or **Add a listing**, and can see "Nearby, with '…'" (other town areas within 200 mi). | 0058 §12 |
| "Named on pages we read" | Places the crawler has read about but that are not listed yet. Each links to its source page. | 0157 §3 (delivery 2 built) |
| `/listing/view.php?id=` | A business page: hero, Engage bar (views and comments; no Like on listings), and comments, which serve as reviews without stars | 0058 §20, §22; pages.md §1 |
| `/listing/location.php?id=` | Suggest a corrected pin (drag the pin or use "I'm here now"). No sign-in is needed, and the suggestion goes to Review. | 0058 §15 |
| "Tell us" (`/api/suggestion.php`) | Report something missing, a fix, closed or moved, or listed twice | 0053 "Tell us"; pages.md §6 |
| `/directory/?town=&state=` | Reached from town names on a place page; centred on the town, within 10 mi by default | 0053 |

### Traveler

- **Reaching Get Local:**
  - from place pages (`/hub/?cluster=`, which is a **Live, placeholder** page);
  - from the town links on those pages;
  - from "See all … in the directory" in universal search (0053; pages.md §1).
- **Distance and maps:** the distance menu offers 10, 25, 50 or 100 mi. The full-screen map opens with its panel (0053 step 4b; 0058 §5, §21).
- **Approximate pins are labelled.** An unplaced listing reads "Approximate pin: the middle of its ZIP area" (0058 §14).
- **Section behaviour:** on Get Local, businesses lead the results. On Let's Explore, journeys and stories lead and businesses follow (0058 §19).
- Nothing traveler-specific beyond this is shown for Get Local.

### Member (signed in)

- **Favorites filter:** businesses the member linked or saved to their Address Book (0053; 0058 §9).
- **Saved searches** (up to 20), **own quick-search phrases** with optional counts, and **saved places** via `/api/places.php` (0053 §6 and the 2026-11-22 note; pages.md §6).
- **Comment on listings** if 18 or older, up to 30 comments an hour; can also **Report** (0058 §4, §20).
- **Link** a business, or **Save** it privately to the Address Book (0058 §20, §22).
- **Dashboard:** `/user/dashboard.php` (pages.md §3).

### Business

| Page / route | What it does | Source |
|---|---|---|
| `/claim.php?listing=` | Claim by email code or text code. Also supports dispute, add a business (`?new=1`) and contribute details. | pages.md §1 (0070) |
| `/listing/businessportal.php`, `/business/` | Claims and their status, plan, the listing editor, and the scoped business portal | pages.md §4 |
| `/listing/location.php?id=` | Move the pin, effective for everyone at once. Can also edit street, town, state and ZIP; these fields are then locked against crawls. | 0058 §15 |
| Hero → **Photos & video** | Add up to 8 items. Photos are re-encoded and their metadata stripped. | 0058 §21 |
| About add-ons | Content by plan (`entities.plan_tier`): Core adds "What they offer"; Strategic adds "Owner spotlight" and a gallery | 0058 §22 |
| Business Insights | Calls, directions, website and contact taps, plus listing visits, for the last 30 days. These are counts only. | 0058 §9, §13 |
| Guest questions | `/api/guest-inquiry.php` | pages.md §6 (0052) |

Some listings cannot be claimed. Kinds in `NOT_A_BUSINESS` (today only `mail-package-drop-off`) show on search and maps but are kept out of business browse lists (PlaceScope.php:33-55).

---

## (b) How it works underneath

### Place scoping: `PlaceScope` (shown)

**Purpose.** One shared filter that turns hub, geo-hub or cluster into SQL over `entities`. The header comment names its callers as `search_listings.php`, `suggest.php`, `place.php` and `places.php` (PlaceScope.php:4-6). Those callers are not shown.

**Parameters and safety.**
- The most specific parameter wins: cluster, then geo, then hub (PlaceScope.php:63-73).
- Slugs are checked against a pattern. An invalid or unknown slug yields `'1 = 0'`, which matches nothing rather than being silently ignored (PlaceScope.php:14-15, 83-92).
- The SQL fragment contains only integers read back from the database (PlaceScope.php:11-13).

**Matching rules.**

| Level | Matches | Lines |
|---|---|---|
| Hub | Its clusters, or any ZIP in a county assigned to it (`hub_counties` via `zip_coordinates`) | PlaceScope.php:96-110 |
| Geo-hub | Its clusters, their primary ZIPs, or its counties | PlaceScope.php:113-127 |
| Cluster | `micro_cluster_id`, or the cluster's **primary** ZIP only | PlaceScope.php:156-157 |

**Display names.**
- Auto-seeded cluster names lose the "(auto-seeded…)" note (PlaceScope.php:57-61).
- Clusters named "ZIP 85901" display as the town and state instead (PlaceScope.php:142-154).

**Public statuses.** `PUBLIC_STATUSES` lists unclaimed, claimed-unverified, pending-verification and verified (PlaceScope.php:30-31).

### Hours and "Open now": `Hours` (shown)

- **Free-text parsing.** Reads lines such as "Mon–Fri 8am–5pm" and OpenStreetMap-style "Mo-Fr" (Hours.php:5-14, 62-93).
- **Bare closing times.** In "10-4", a bare close that is not after the opening time is read as afternoon (Hours.php:84-85).
- **Past midnight.** Closing times can run past midnight (Hours.php:87, 100-101, 123).
- **Unreadable hours are "unknown", never "open."** `openAt`/`status` return `null` or `''` (Hours.php:8-9, 96-103, 121).
- **Card text.** `status()` produces the card wording, including "Closed · Opens tomorrow …" (Hours.php:119-133).
- **"Open on a day."** `openOn()` supports this filter (Hours.php:135-140).

### Our own index as dictionary: `LocalWords` (shown)

- **Build.** Rebuilt once a day off the visitor's path. It is file-locked against concurrent builds and written to `app/storage`, "which never leaves the server" (LocalWords.php:15-16, 23, 140-162).
- **Inputs** (LocalWords.php:185-223):
  - public merchant listings' names, kinds and offerings;
  - the place store (`source_facts`), excluding sources marked ignore;
  - towns in served states;
  - visible landmarks only (`Landmarks::visibleSql()`, per 0066).
- **Business names are never kind clues.** This avoids the "Blue Rhino" problem, where "blue" was read as propane (LocalWords.php:169-171, 196).
- **Spelling safety.** `knows()` treats town and landmark names, or words used 3 or more times, as never needing a fix (LocalWords.php:61-72). `near()` offers candidate fixes (LocalWords.php:86-97).
- **Sense from context.** `kindsFor()` treats phrases as one unit and ignores roots that sit with every kind (LocalWords.php:106-134). Phrases need 3 or more uses with a kind (LocalWords.php:229-234).

### Described in documents, code not shown

- **Search.**
  - `UniversalSearch` / `/api/search.php`, including `elsewhere` for "none here" (0053; 0058 §12).
  - `search_listings.php` parameters: `category=a,b`, `has=`, `open=now&at=`, `ids=` (0053 step 4).
  - The `search_index` 5W table, kept current at most every 15 minutes with no cron (0053 §8, 5W-1).
  - `SearchPresets` and `search_counts` (0053 step 5).
- **Map points.**