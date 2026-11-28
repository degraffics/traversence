# Platform atlas: knowledge-engine-crawler

*Draft for human review. Written only from the attached documents and code.*

**Path note.** The code is quoted from the mirror path `traverse-3way-sync/sync-app/lib/…`. The file headers call this `app/lib/…`. Citations below are written as `lib/<file>:<line>`.

**Partial view.** Every file was capped at 40,000 bytes:

- `CrawlService.php` is cut at line ~438 (inside `deliver()`).
- `GuideLinks.php` is cut at ~432 (inside `findTick()`).
- `Sources.php` is cut at ~481.
- ADRs 0163, 0164 and 0133 are truncated.

The following are named in the code but **not shown**:

- `AutoImport`, `IntakeStager`, `Guides`, `Landmarks`, `Identity`, `ListingIdentity`, `Automation`, `Operations`, `ServiceHealth`, `StockLibrary`, `Frontier`, `Clocks`
- the Railway worker (`workers/crawler/`)
- every page template and `architecture.md`

---

## (a) What each person can do here, page by page

The crawler has almost no pages of its own. It feeds pages owned by other areas. Routes and statuses below come from `site-audit/pages.md`.

### Resident and traveler (anyone, signed in or not)

| Route | What the engine gives them |
|---|---|
| `/guide/how.php?id=` (Live) | A how-to guide drafted from official .gov/.edu/.us pages. A person publishes it (`lib/HowGuides.php:11-19`). |
| `/guide/resources.php?id=` / `?area=` (Live) | "Who to call" lines. A line shows only while its number is found on the provider's own page (`lib/ResourceGuides.php:7-9`, `:165-183`). |
| `/listing/view.php?id=` (Live) | Listings the crawler created as `unclaimed` (`lib/CrawlerIngest.php:424-463`). Guide names link here (`lib/GuideLinks.php:242`). |
| `/place/landmark.php?id=`, `/place/recreation.php?id=` (Live) | Landmarks and Recreation.gov places, linked from guides (`lib/GuideLinks.php:243-244`). |
| `/hub/build.php?city=&state=&q=` ("Live once uploaded", 0147) | A guide built while you wait for a searched place that has none. |
| `/journeys/suggested.php?id=` ("Live once uploaded", 0146) | Rule-built journeys from a guide's places. A town crawl triggers them (`lib/crawler/TownCrawl.php:221`). |
| `/takedown.php` (Live) | Ask for something to be taken down. 0133 says it is protected-reason hidden at once, otherwise after 24h. |
| `/api/search.php`, `/api/suggestion.php` (Live) | Searches feed demand. A search with nothing near a town queues that town's build-out (`lib/crawler/TownCrawl.php:61-89`). How-to searches feed `HowGuides::refresh` (`:56-92`). |

Where place guides themselves render (sections, "Walking in the footsteps") is **not shown**. `/hub/?hub=` is "Live, placeholder".

### Member (signed in)

- **`/ai/` (Live): AI Assist.** 0164 R15 says it is for paid-plan members, writes from the Topic record only, and has allowances set in 0163 §7. The page and `AiCredits` code are **not shown**.
- **Suggestions.** A member's suggestion can bring a guide's check forward (0133 item 13).
- **Consent before AI.** A member's own words go to AI only with AI & Learning consent, and only to Claude (0164 R5; comment at `lib/GuideLinks.php:82`).

### Business

- **Free listing.** The engine can create a public, unclaimed listing without the business asking (`lib/CrawlerIngest.php:424-463`, status mapping `:596-602`).
- **Claim it.** `/claim.php?listing=` (Live). Once claimed, the crawler stops overwriting core fields (`lib/CrawlerIngest.php:468-470`) and never touches a locked field (`:544-547`).
- **Evidence offers.** Owners are offered evidence ("We found other information…", `listing/api/evidence.php` per 0062). That code is **not shown**.
- **"Check these" queue.** 0164 R4 / P3-5 describes an owner "Check these" queue in `/listing/businessportal.php` with email. The 0163 note lists P3-5 as **not built**.

### Staff (for context)

| Area | Routes |
|---|---|
| Crawler | `/admin/crawler.php`, `/admin/crawler-review.php`, `/admin/auto-imports.php`, `/admin/listing-intake.php`, `/admin/sources.php`, `/admin/identities.php` |
| Guides | `/admin/how-guides.php`, `/admin/resource-guides.php` |
| Search and clusters | `/admin/search-demand.php`, `/admin/cluster-tools.php` |
| Audit and config | Insights → Quarterly audit (`/admin/insights/audit`, `lib/GuideAudits.php:46`); `/admin/config/preflight` (0163 note, built #88) |

All listed Live in pages.md except the preflight, which comes from the 0163 note.

---

## (b) How it works underneath

### Two layers (0160 §1)

The Railway worker fetches. The site hands out work, stages results and decides what is published.

**Per-run plan.** `lib/crawler/CrawlService.php:91-175`:

- A run ends at once if paused (`:102-105`).
- It skips paused workflows and tasks, and tasks a red line holds (`:110-125`).
- Each workflow's share is split across its tasks (`:129-140`).
- A half-budget ramp applies after a return (`:139-140`).
- Tasks with no paid route make no paid calls (`:145-147`).
- Coverage (finding businesses) goes first (`:152`).
- Bulk loads run only in a 1–5am Arizona window, after other work (`:38`, `:153-158`).

**Run report.** `:183-309`:

- Records task outcomes. "Not reached" is never counted as served (`:207`).
- Writes the cost ledger, including failed paid calls (`:208-210`, `:366-374`).
- Sets an honest status: failed / starved / partial / idle / ok (`:236-243`).
- Raises control alarms (`:250-305`).
- Notes Overpass is retired. Missing OSM kinds are only reported (`:221-231`).

**Pause.** `lib/crawler/CrawlQueue.php:42-49` pauses for any of: staging, `CRAWL_QUEUE=off`, or the Automation master switch.

### Queues

- **Coverage gaps** (W1). `CrawlQueue::seed` (`:72-111`), priority by population. Lease order (`:114-145`):
  1. searched towns first (`:124-129`);
  2. situation gaps `sit:` next (`:134`).

  Failed jobs retry after 6h / 24h / 72h, then wait for a person (`:28`, `:231-244`). A candidate without a 5-digit ZIP is dropped (`:288`).
- **Town build-outs** (0149). `lib/crawler/TownCrawl.php`:
  - Ten OSM kinds (`:17-33`). Shelters are never included (`:24-26`).
  - At most 25 towns a day, each town once per 30 days, rebuilt on W1's clock (`:66-101`).
  - Auto-imported finds are search-indexed at once (`:208-211`).
- **Search targets** (0066). `lib/crawler/CrawlTargets.php`. Everything is held for a person and "never published on its own" (`:17-18`, `:95-96`).
- **Single requests.** `find_name` and `read_page` (`CrawlService.php:379-425`). The delivery code is cut off at `:438`.

### Ingest and the one record

`lib/CrawlerIngest.php`:

- **Identity hash.** Composite hash of name + address + ZIP (`:59-63`).
- **Identity check.** With no hash match, an identity check can match an existing business so no duplicate is made (`:415-422`).
- **Matched listings.** Only gaps are filled (`:505-508`).
- **Claimed listings.** Core fields are not patched (`:468-470`).
- **Person-set pins** are kept (`:471-478`).
- **Field writes.** Each written field is recorded to the identity (`:566-570`).
- **Clusters.** An unmatched ZIP joins the nearest cluster within 20 mi, 5 towns and 50k people, or else seeds a "(auto-seeded, needs review)" cluster (`:95-97`, `:118-174`).
- **Admin cluster fixes.** `lib/crawler/IntakeClusters.php`. One cluster per ZIP is enforced at `:147-151`.

### Source register

`lib/crawler/Sources.php`:

- **Standing rules** per host (`:53-69`). Examples:
  - OSM: pointer only.
  - Wikipedia: cite only.
  - Google, Tavily, Claude: evidence only.
  - IRS: confirms, never shown.
- **Store or not.** `mayStore` is true only for `facts` and `credit` (`:84-87`).
- **Terms-restricted sources** cannot be set to store or show (`:135`).
- **Licence tightening** holds that host's facts and opens an engine case (`:148-155`).
- **Tiers 1–5** (`:315-316`, `:450-459`).
- **Machine classification.** Most sites are classified by the machine (`:431-447`). Tribal nations' sites are always a person's call (`:343-344`, `:434-435`).

### Content builders

- **HowGuides.** Official hosts only (`:116-121`). The register must allow the source (`:231-240`). A step copied ≥80% from a non-open-licence source is refused (`:247-265`). Publishing needs ≥3 sourced steps plus a person's "own words" confirmation (`:214-221`).
- **ResourceGuides.** `check()` fetches the official page. If the number isn't found, the line is unverified and hidden (`:120-142`).
- **GuideLinks (Find it):**
  - Names are extracted, with Claude only when allowed (`:82-94`).
  - Landmarks are matched against held / story-only levels (`:199-219`). A held name can't be hand-linked (`:329`).
  - Confidential services are never looked up outside (`:389-394`).
  - Search order: our crawler → web step → Claude (`:382-411`). The Claude prompt says found=false for sacred sites (`:417`).
- **GuideAudits.** Each quarter draws a sample of ⅒ of live guides (5–40) with a pre-check for "way in" sentences (`:32-73`).
- **StockSource.** Every call goes through `StagingGate` and `ServiceHealth` (`:33-49`). Licence decisions sit in `StockLibrary`, which is **not shown** (`:8-9`).

---

## (c) Decisions

| ADR | R