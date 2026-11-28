# Platform atlas: knowledge-engine-crawler (merged from pass1 and pass2)

*Draft for human review. This merges two independent passes. Both were built only from the attached files. Neither pass is complete. Pass1 ends partway through section (b), at "Commercial". Pass2 ends at the first row of section (c)'s table ("| ADR | R").*

**Citation convention**

- Pass1 (P1) cites `File.php:N`.
- Pass2 (P2) cites `lib/File.php:N`, or `lib/crawler/File.php:N` for `CrawlService`, `CrawlQueue`, `TownCrawl`, `CrawlTargets`, `IntakeClusters` and `Sources`.
- Where both passes give the same file and lines, the citation appears once, tagged **(P1, P2)**.
- Where the lines differ, each pass's citation is given separately.
- **single-pass** marks a point only one pass makes.
- When the single pass is P2 in section (b) or later, P1 may simply not have reached that point.

**Paths**

- Both passes read the code from a mirror at `sync-app/lib/`. P2 gives the full path, `traverse-3way-sync/sync-app/lib/…`.
- Both say the file headers name the code `app/lib/…`.
- *single-pass (P1):* some headers say `api/lib/…`, and pages.md:"The backend: app/" places the library in `app/lib/`.

**Truncation (both passes)**

Files were cut at about 40 KB:

- `CrawlService.php`: P1 says after line 437. P2 says at ~438, inside `deliver()`.
- `GuideLinks.php`: cut after/at ~432. *single-pass (P2):* inside `findTick()`.
- `Sources.php`: cut after/at ~481.
- ADRs 0163, 0164 and 0133 are truncated.

**Not shown**

- Both passes: `AutoImport`, `IntakeStager`, `ListingIdentity`.
- *single-pass (P2):* `Guides`, `Landmarks`, `Identity`, `Automation`, `Operations`, `ServiceHealth`, `StockLibrary`, `Frontier`, `Clocks`, the Railway worker (`workers/crawler/`), every page template, and `architecture.md`.

---

## (a) What each person can do here, page by page

Both passes agree on three points:

- The engine has almost no pages of its own.
- It feeds pages owned by other areas.
- Routes and statuses come from `site-audit/pages.md`.

*Passes differ on structure.* P1 has separate Resident ("Get Local") and Traveler ("Let's Explore") tables. P2 has one "Resident and traveler (anyone, signed in or not)" table. P2 also gives a Live status for most routes. P1 gives a status only for `/hub/build.php`.

### Resident / traveler

| Route | What the engine supplies |
|---|---|
| `/directory/`, `/api/search_listings.php` | *single-pass (P1):* listings the crawler found and published. The confidence score orders Best match (0133, note of 2026-11-19). |
| `/listing/view.php?id=` | **Both:** the crawler's listings. P1 says the listing shows its facts and their sources, and the card's rendering is not shown. *single-pass (P2):* the crawler creates listings as `unclaimed` (`lib/CrawlerIngest.php:424-463`), guide names link here (`lib/GuideLinks.php:242`), and the status is Live. |
| `/guide/resources.php?id=` / `?area=` | **Both:** "who to call" lines (P1: phone lines that aren't businesses). A line shows only while its number is found on the provider's official page. Line ranges differ: P1 `ResourceGuides.php:7-8, 120-142, 165-178`; P2 `lib/ResourceGuides.php:7-9, :165-183`, with `:120-142` cited in its (b). |
| `/guide/how.php?id=` | **Both:** how-to guides drawn from official pages, and nothing shows until a person publishes. **Passes differ on the official domains:** P1 says .gov/.edu/**.mil** (`HowGuides.php:116-121`). P2 says .gov/.edu/**.us** (`lib/HowGuides.php:11-19`). For publishing, P1 cites `HowGuides.php:19, 196-228`. P2 cites `:11-19`, and in its (b) `:214-221`. |
| `/api/suggestion.php` | **Both** list it. P1 says its handler is not shown. *single-pass (P2):* listed together with `/api/search.php` (Live). Searches feed demand. A search with nothing near a town queues that town's build-out (`lib/crawler/TownCrawl.php:61-89`). How-to searches feed `HowGuides::refresh` (`:56-92`). |
| `/takedown.php` | **Both:** ask for something to be taken down. P1 cites 0133 item 11 and says the handler is not shown. *single-pass (P2):* per 0133, items with a protected reason are hidden at once, and others after 24h. |
| `/discovery/`, `/hub/?...` | **Passes differ.** P1 says place guides (W4) have names linked to what we hold (`GuideLinks.php:240-249, 339-363`). P2 says where place guides render is **not shown**, and lists `/hub/?hub=` as "Live, placeholder". |
| `/hub/build.php?city=&state=` (P2 adds `&q=`) | **Both:** a guide built while you wait (0147), status "Live once uploaded". *single-pass (P1):* W5, and it queues the town's build-out (`TownCrawl.php:2-8`). P2 says it serves a searched place that has no guide. |
| `/place/landmark.php`, `/place/recreation.php` | **Both:** landmarks and Recreation.gov places. *single-pass (P1):* shown under the protected tiers (0164 R7). *single-pass (P2):* linked from guides (`lib/GuideLinks.php:243-244`), status Live. |
| `/journeys/suggested.php?id=` | **Both:** rule-built journeys, rebuilt or triggered after a town crawl (`TownCrawl.php:221`) (P1, P2). P1 labels this W7. P2 cites 0146 and gives the status "Live once uploaded". |

### Member (signed in)

**`/ai/` (AI Assist)**

- Both passes: answers are written from the Topic index/record only, and the feature needs a paid plan (0164 R15). The handler/page is not shown.
- *single-pass (P1):* consent is cited to 0164 R15 and 0133 "AI Assist for members".
- *single-pass (P2):* allowances are set in 0163 §7. The `AiCredits` code is not shown.

**Consent before AI.** The passes cite different sources.

- P1 links consent to R15.
- *single-pass (P2):* a member's own words go to AI only with AI & Learning consent, and only to Claude (0164 R5; comment at `lib/GuideLinks.php:82`).

**Other member points (each single-pass)**

- *(P1)* Takedown and suggestion are available as above. "Is this right? Yes/No" and "Still open?" are decided (0164 R12). Their UI is not shown.
- *(P2)* A member's suggestion can bring a guide's check forward (0133 item 13).

### Business

**Free listing.** *single-pass (P2):* the engine can create a public, unclaimed listing without the business asking (`lib/CrawlerIngest.php:424-463`, status mapping `:596-602`).

**Claim (`/claim.php`).** Both passes agree:

- Once a listing is claimed, the crawler does not patch its core fields (`CrawlerIngest.php:468-470`) (P1, P2).
- The crawler never overwrites a locked field (`CrawlerIngest.php:544-547`) (P1, P2).

*single-pass (P1):* a claim lifts a listing to the top confidence band (0164 R12).

**Evidence offers.** Both passes: evidence that differs from a claimed listing is offered to the owner, not applied (0062).

- P1 quotes the rule "evidence wins, unless claimed".
- P2 quotes "We found other information…" and cites `listing/api/evidence.php`, which is not shown.

**"Check these" in `/listing/businessportal.php`.** Both passes: it is decided in 0164 R4, but 0163 lists P3-5 as not built. *single-pass (P2):* it includes email.

### Admin / staff

**Listed by both passes**

- Crawler pages: `/admin/crawler.php`, `crawler-review.php`, `auto-imports.php`, `listing-intake.php`, `sources.php`, `identities.php`.
- Guide and search pages: `how-guides.php`, `resource-guides.php`, `search-demand.php`, `cluster-tools.php`.
- Insights → Quarterly audit. P1 cites `GuideAudits.php:45-46`. P2 cites `lib/GuideAudits.php:46` and gives the route `/admin/insights/audit`.
- `/admin/config/preflight`. P1 sources it to 0163 §9–§10. P2 sources it to the 0163 note, "built #88".

**Single-pass items**

- *(P1)* `landmarks.php` and `nations.php`.
- *(P1)* System Configuration → Automation, and Thresholds & gates (0163 §9–§10).
- *(P1)* Worker-only endpoints `/api/crawl/*` and `/api/ingest.php` (pages.md §6).
- *(P2)* All of these pages are listed Live in pages.md except the preflight.

---

## (b) How it works underneath

### Two layers (0160 §1; P1 also cites §2)

Both passes: the off-site Railway worker fetches pages. The site leases or hands out work, stages the results and decides what is published.

### Per-run plan: `CrawlService::plan` (`CrawlService.php:91-175`) (P1, P2)

**Points both passes make**

| Point | P1 lines | P2 lines |
|---|---|---|
| When paused, the run is recorded (P1) and ends at once, returning empty | `:102-105` | `:102-105` |
| Skips paused workflows and switched-off tasks, and tasks the system pauses or holds (P2: "a red line holds") | `:110-118` | `:110-125` |
| Bulk loads run only at night, Arizona time. P2 specifies 1–5am, after other work | `:38`, `:123` | `:38`, `:153-158` |
| Each workflow's share is split across its tasks (P1: by weight) | `:128-137` | `:129-140` |
| A returning workflow runs at half budget (the ramp) | `:139-140` | `:139-140` |
| A task with no paid route makes no paid calls | `:141-147` | `:145-147` |

**Single-pass points**

- *(P1)* The plan carries OpenStreetMap budget and holds (`:165-168`).
- *(P1)* Paid backups run only by override (`:171-172`).
- *(P2)* Coverage (finding businesses) goes first (`:152`).

### Run report: `CrawlService::report`

The passes give slightly different ranges: P1 says `:183-308`, P2 says `:183-309`.

**Points both passes make**

- Every paid call is written to the `crawl_costs` ledger, failed calls included (`:208-210`, `:366-374`) (P1, P2).
- Each run gets an honest status: failed, starved, partial, idle or ok (`:236-243`) (P1, P2).
- Alarms are raised (`:250-305`) (P1, P2). P1 adds that they are cleared. P2 calls them control alarms.

**Single-pass points**

- *(P1)* Railway seconds are logged (`:307`).
- *(P2)* "Not reached" is never counted as served (`:207`).
- *(P2)* Overpass is retired, and missing OSM kinds are only reported (`:221-231`).

### One-off requests

Both passes: `read_page` and `find_name` requests are handled here, and the code is cut off.

- P1 cites `:379-437