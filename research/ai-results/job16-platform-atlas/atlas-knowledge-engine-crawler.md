# Platform atlas: knowledge-engine-crawler

*Draft for human review. It is built only from the attached files. Code paths are cited as attached (`sync-app/lib/...`). File headers name them `app/lib/...` (some say `api/lib/...`), and pages.md:"The backend: app/" says the library lives in `app/lib/`. Treat `sync-app/lib/` as the sync mirror of `app/lib/`. Several attached files are cut off at about 40 KB: `CrawlService.php` (after line 437), `GuideLinks.php` (after line 432), `Sources.php` (after line 481), and ADRs 0163, 0164 and 0133. Anything past those cut-offs is "not shown".*

---

## (a) What each person can do here, page by page

The engine has almost no pages of its own. It feeds pages that belong to other areas. Routes and status come from `site-audit/pages.md`.

### Resident ("Get Local")

| Route | What the engine supplies |
|---|---|
| `/directory/`, `/api/search_listings.php` | Listings the crawler found and published. The confidence score orders Best match (0133, note of 2026-11-19). |
| `/listing/view.php?id=` | The listing itself, its facts and their sources. The card's rendering is not shown. |
| `/guide/resources.php?id=` / `?area=` | Phone lines that aren't businesses. A line shows only while its number is found on the provider's official page (`ResourceGuides.php:7-8`, `120-142`, `165-178`). |
| `/guide/how.php?id=` | How-to guides drawn from official .gov/.edu/.mil pages (`HowGuides.php:116-121`). Nothing shows until a person publishes it (`HowGuides.php:19`, `196-228`). |
| `/api/suggestion.php` | Tell us a place is missing, wrong or closed. Its handler is not shown. |
| `/takedown.php` | Ask for something to be taken down (0133 item 11; handler not shown). |

### Traveler ("Let's Explore")

| Route | What the engine supplies |
|---|---|
| `/discovery/`, `/hub/?...` | Place guides (W4) with names linked to what we hold (`GuideLinks.php:240-249`, `339-363`). |
| `/hub/build.php?city=&state=` | A guide built while you wait (0147/W5). It also queues the town's build-out (`TownCrawl.php:2-8`). Status: "Live once uploaded". |
| `/place/landmark.php`, `/place/recreation.php` | Landmarks and Recreation.gov places, shown under the protected tiers (0164 R7). |
| `/journeys/suggested.php?id=` | Rule-built journeys (W7), rebuilt after a town crawl (`TownCrawl.php:221`). |

### Member (signed in)

- **`/ai/` (AI Assist).** Answers are written from the Topic index only. It needs a paid plan and consent (0164 R15; 0133 "AI Assist for members"). The handler is not shown.
- **Takedown and suggestion, as above.** "Is this right? Yes/No" and "Still open?" are decided (0164 R12). Their UI is not shown.

### Business

- **`/claim.php`, `/listing/businessportal.php`.** A claim lifts a listing to the top confidence band (0164 R12).
  - The crawler never overwrites a locked field (`CrawlerIngest.php:544-547`).
  - It never patches a claimed listing's core fields (`CrawlerIngest.php:468-470`).
  - Evidence that differs from a claimed listing is offered to the owner, not applied (0062, "evidence wins, unless claimed").
- **The portal's "Check these."** It is decided (0164 R4), but 0163 lists P3-5 as not built.

### Admin / staff (where the engine is operated)

- **Crawler pages:** `/admin/crawler.php`, `crawler-review.php`, `auto-imports.php`, `listing-intake.php`, `sources.php`, `identities.php`.
- **Guide and search pages:** `how-guides.php`, `resource-guides.php`, `search-demand.php`, `cluster-tools.php`.
- **Protected places:** `landmarks.php`, `nations.php`.
- **Quarterly guide audit:** Insights → Quarterly audit (`GuideAudits.php:45-46`).
- **Operations pages:** System Configuration → Automation, Pre-flight (`/admin/config/preflight`) and Thresholds & gates (0163 §9–§10).
- **Worker-only endpoints:** `/api/crawl/*` and `/api/ingest.php` (pages.md §6).

---

## (b) How it works underneath

### The worker and its plan (0160 §1–§2)

The off-site Railway worker fetches pages. The site leases it work and decides what the results become.

- **Plan.** `CrawlService::plan` (`CrawlService.php:91-175`):
  - It records the run and returns empty when paused (`102-105`).
  - It skips paused workflows and switched-off tasks, and tasks the system paused or holds (`110-118`).
  - Bulk loads run only at night, Arizona time (`38`, `123`).
  - Each workflow's share is split across its tasks by weight (`128-137`).
  - A returning workflow runs at half budget (the ramp) (`139-140`).
  - A task whose routes name no paid service makes no paid calls (`141-147`).
  - The plan also carries OpenStreetMap budget and holds (`165-168`) and paid backups, which run only by override (`171-172`).
- **Report.** `CrawlService::report` (`183-308`):
  - Every paid call is written to the `crawl_costs` ledger, failed calls included (`208-210`, `366-374`).
  - Each run gets an honest status: failed, starved, partial, idle or ok (`236-243`).
  - Alarms are raised and cleared (`250-305`), and Railway seconds are logged (`307`).
- **Requests.** A one-off request queue handles `read_page` and `find_name` (`379-437`). A found name goes through the same intake as any other find (`431-437`). The rest of that function is not shown.

### The pause switch

`CrawlQueue::pausedWhy` checks three things: staging, `CRAWL_QUEUE=off`, and the automation master switch (`CrawlQueue.php:42-49`). Every lease path asks it (`CrawlQueue.php:116`, `CrawlTargets.php:62`).

### Queues

- **Coverage (W1).** `CrawlQueue::seed` turns cluster gaps into jobs, weighted by population (`CrawlQueue.php:72-111`).
- **Lease order.** `lease` serves towns people searched for first (`124-128`), then situation gaps ahead of the general sweep (`134`).
- **Retries.** Failed jobs come back after 6, 24 and 72 hours (`28`, `231-244`).
- **Candidates.** A candidate is dropped if it has no 5-digit ZIP (`288`).
- **Towns on demand (0149).** `TownCrawl.php`:
  - It covers ten kinds of place. It never asks for shelters (`17-33`).
  - It builds at most 25 towns a day, and the same town at most once every 30 days (`66-89`).
  - It rebuilds after 30 days on W1's clock (`91-101`).
  - It indexes auto-imported finds at once (`207-211`).
- **Search targets (0066).** Everything found is held for a person, because the place may be outside our regions (`CrawlTargets.php:95-99`).

### Intake and identity

`CrawlerIngest::upsertEntity` (`CrawlerIngest.php:383-519`):

- It looks up an exact composite hash first (`59-63`).
- With `$check` on, it runs the one identity check. A match fills only gaps (`415-422`, `505-508`).
- It keeps a pin a person placed (`471-478`).
- Every field it writes is recorded in the identity record (`566-570`).
- Category becomes Chameleon Filter intent vectors. An unknown category is flagged `intent_needs_review` (`26-75`, `491-493`).

`IntakeStager`, `AutoImport` and `ListingIdentity` are not shown.

### Geography

- **Auto-seeding.** A ZIP joins the nearest cluster within 20 miles, under a 5-town cap and a 50,000-population ceiling. Otherwise it seeds a new cluster named "(auto-seeded, needs review)" (`CrawlerIngest.php:95-97`, `118-174`).
- **One cluster per ZIP.** Admin fixes enforce it (`IntakeClusters.php:24`, `147-151`).

### Source register (0164 R9; 0157 §7)

- **Standing sources.** `Sources::STANDING` lists them with licence and use (`Sources.php:53-69`).
- **What may be stored.** `mayStore` allows only facts or credit (`84-87`). A terms-restricted source can never be set to store (`135`).
- **Licence tightening.** If a source's licence tightens, its facts are re-sorted and a case opens for a person (`146-155`).
- **Tribal nations' sites** are always a person's call (`343-344`, `431-435`).
- **Machine classification** sorts sites into tiers 1–5 (`315-316`, `431-458`). Commercial