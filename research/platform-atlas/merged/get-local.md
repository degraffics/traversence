# Platform atlas: Get Local (`/directory/`), merged from two passes

*Draft for human review. Merged from two independent passes (P1 and P2).*

**Marking convention**
- **Unmarked:** both passes support the statement.
- **[P1 only] / [P2 only]:** single-pass.
- **"passes differ: …":** the passes disagree or diverge.
- Where the passes cite different line ranges for the same point, both are given.

**Coverage**
- P1 stops partway through (b), at "Map points." It contains no (c), (d) or (e).
- Everything in (c), (d) and (e) is therefore P2 only. This reflects P1's truncation, not a disagreement.
- P2's (e) is itself cut off partway through "Data dignity."

---

## Sources and limits

**Documents (both passes)**
- charter.md, brand.md, prd.md and site-audit/pages.md.
- Decisions 0053, 0058, 0138, 0157 and 0170.
- Both passes say 0053 and 0058 were truncated.

**Code (both passes)**
- Three capped files: `Hours.php`, `PlaceScope.php` and `LocalWords.php`.
- They are shown in the sync mirror (`sync-app/lib/`; P2 gives `traverse-3way-sync/sync-app/lib/`).

**Path note**
- The file headers name other homes:
  - `api/lib/Hours.php` (Hours.php:6);
  - `api/lib/PlaceScope.php` (PlaceScope.php:2);
  - `app/lib/LocalWords.php` (LocalWords.php:2).
- pages.md says the backend moved to `app/` under decision 0069.
- [P2 only] The move date was 2026-10-09, 0069 is not shown, and the two `api/lib` headers are therefore stale.

**Not shown (union of both passes)**
- Pages and endpoints: the `/directory/` page and `/api/search_listings.php`.
- Search code: `UniversalSearch`, `SearchIndex` and `SearchPresets` [P2 only].
- Location and display code: `Geocoder`, `ListingLocation`, `PlatformCounts`, `HeroMedia` and `Engage`.
- JavaScript: P1 says "the JavaScript"; P2 names `search-panel.js` and `map-view.js`.
- [P2 only] `CrawlerIngest::resolveMicroClusterId`, `SourceIndex`, `Concepts`, `CategoryClassifier` and `SearchText`.
- The schema (P2 adds the migrations).

---

## (a) What each person can do, page by page

**Naming and track**
- "Get Local" is the visitor-facing name for `/directory/` (pages.md, Notes; P1 also cites brand.md §3).
- [P1 only] Its focus is "everyday utility, civic life, municipal data, local routines" (brand.md §3).
- [P2 only] It is the Resident track (charter, Tri-Track; brand.md §3). brand.md §5 says it serves "residents and explorers alike."

*Passes differ in structure:* P1 has separate Resident, Traveler, Member and Business sections. P2 uses one table with a combined "Resident / traveler (guest)" column. The merge follows P1's roles and folds P2's guest column into Resident and Traveler.

### Resident (guest)

**Home page (`/`)**
- The Get Local door is one of four doors under the hero search (0138 §3; P1 also cites pages.md §1).
- [P2 only] The home **Business** tab shows new, claimed or updated listings, newest first (0138 §4).

**`/directory/` (Live)**
- The top row shows the place, with **List \| Map** (0058 §9).
- [P1 only] Layout detail: "📍 St. Johns ▾" first, then the title and count, then removable filter chips.

**Filters and sorting**
- Filters live in the search's Quick picks, not on the page: Near me, Open now, Has a phone, Has a website (0058 §9). Favorites is for members only.
- A town or "near me" sorts nearest first; otherwise results are by best match (0058 §9; P1 also cites 0053).

**Cards**
- Each card has Call, Directions, Website, Peek and Link (0058 §9).
- [P1 only] The name links to the listing, and Link sits in the corner.
- [P1 only] Each card shows "Open · Closes 5 PM" or "Closed · Opens tomorrow 8 AM" (Hours.php:115-133).
- [P2 only] "Show more" (0053).

**Search panel** (`/api/search.php`, `/api/search_listings.php`)
- [P2 only] It opens in place, with chips, a peek card and 5W reading (0053 §1, §8).
- [P2 only] "Did you mean" suggestions (0058 §13).

**"None here" box**
- When nothing matches, it shows nearby alternatives (0058 §12).
- [P1 only] It offers **Recommend one** and **Add a listing**, plus "Nearby, with '…'" for other town areas within 200 mi.

**"Named on pages we read"**
- [P1 only, in (a)] Places the crawler has read about but that are not yet listed. Each links to its source page (0157 §3; delivery 2 built).
- P2 mentions 0157 §3 only in (c).

**Full-screen map (`TvMap`)**
- Approximate pins:
  - P1: an unplaced listing reads "Approximate pin: the middle of its ZIP area" (0058 §14).
  - P2: approximate pins show as one dashed spot (0053, 2026-10-04).
  - These describe different aspects (label vs rendering) and do not contradict.
- [P2 only] "My location" is used on this device only (0058 §5).

**`/listing/view.php?id=` (Live)**
- Hero, an engage bar (views and comments, no Like), and comments that serve as reviews without stars (0058 §20; P1 also cites §22 and pages.md §1).

**`/listing/location.php?id=`**
- A visitor can suggest a pin move without signing in. The suggestion goes to Review (0058 §15).
- [P1 only] By dragging the pin or using "I'm here now."

**"Tell us" (`/api/suggestion.php`)**
- Reports for something missing, a fix, closed or moved, or listed twice (0053; P1 also cites pages.md §6). P2 also cites 0058 §22 for the listing-page prompt.
- [P2 only] Limited to 12 a day per device (0053).

**Town links**
- Town names on place pages open `/directory/?town=…` (0053).
- [P1 only] The page is centred on the town, within 10 mi by default.

**`/directory/classic.php`**
- [P2 only] Archived: off the rail but still reachable (pages.md §1).

### Traveler

*P2 has no separate traveler column; it treats traveler and resident together as "guest."*

**Ways in**
- From `/hub/` place pages (Live, placeholder).
- From town links on those pages.
- [P1 only] From "See all … in the directory" in universal search (0053; pages.md §1).

**Other features**
- [P1 only] The distance menu offers 10, 25, 50 or 100 mi, and the full-screen map opens with its panel (P1 cites 0053 step 4b and 0058 §5, §21).
- On Get Local, businesses lead the results; on Let's Explore, journeys and stories lead (0058 §19). P1 places this in (a); P2 places it in (c).
- [P1 only] Nothing traveler-specific beyond this is shown.

### Member (signed in)

**Favorites filter**
- Covers businesses the member linked or saved to their Address Book (0053; 0058 §9).
- [P2 only] Favorites show on the map as hearts and are never clustered (0058 §5).

**Saved searches**
- Up to 20 (P1: 0053 §6; P2: 0053 step 6).
- [P2 only] Kept on the account.

**Own search phrases**
- P1: own quick-search phrases with optional counts (0053 2026-11-22 note).
- P2: "Your searches" phrases stored on the device (0053).
- These are compatible; only P2 says where the phrases are stored.

**Other member features**
- [P1 only] Saved places via `/api/places.php` (pages.md §6).
- Comment on listings if 18 or older, up to 30 comments an hour (0058 §20; P1 also cites §4).
- [P1 only] Can **Report** comments.
- Can Link a business or Save it to the Address Book (P1: 0058 §20, §22).
- [P1 only] Dashboard at `/user/dashboard.php` (pages.md §3).
- [P2 only] Unverified claimants' pin moves also go to Review. P2 cites 0058 §19 for this, but both passes elsewhere tie §19 to the Chameleon Filter, so this citation needs checking.

### Business

**Claiming and managing**
- `/claim.php?listing=` and `/listing/businessportal.php` (pages.md §1, §4).
- [P1 only] Claiming works by email code or text code. The page also supports disputes, adding a business (`?new=1`) and contributing details (0070). `/business/` is the scoped portal.
- [P2 only] Claim or Manage appears on the listing hero (0058 §22).

**Pin and address**
- Both passes say the business moves the pin directly (0058 §15).
- [P1 only] The move takes effect for everyone at once. Street, town, state and ZIP become editable and are then locked against crawls.
- [P2 only] This applies to the owner, manager or verified owner.

**Photos & video**
- Up to 8 items, added from the hero (0058 §21).
- [P1 only] Photos are re-encoded and their metadata stripped.