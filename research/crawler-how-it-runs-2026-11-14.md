# The crawler: phases, workflows, services (2026-11-14)

For Jason's review. Three layers, from why to how:

- **Phases** (decisions/0133): the stages every piece of knowledge goes through, from finding a source to keeping what's
  published true. The rule book.
- **Workflows** (decisions/0160 §3, W1–W13): each thing we make (listings, guides, heritage, events…). A workflow is
  started by something, moves its work through the phases, asks the crawler for what it needs, and has its own gate and
  report.
- **Services** (decisions/0160 §2): what the crawler can do for any workflow, the tasks the Railway worker runs, and the
  outside services they call, with their limits.

Taken from the live code (`workers/crawler/crawler.py`, worker 2.5 on Railway; `app/lib/crawler/CrawlService.php`, the
site's planner) and the decisions. Each task's schedule (how often, its share, its limits) is a setting in the site's
database (`crawl_capture_tasks`, `crawl_workflows`), not in code, so it isn't listed here.

## 1. Phases (decisions/0133)

Phases 1 and 2 are always-on automation within fixed guardrails; configuration starts at Phase 3.

| Phase | What it does |
|---|---|
| 1. Source building (the index) | Find and catalog every repository of real information in our areas, on any subject: places' own sites; city, county, state, tribal and federal sites; school, health, transit; registries. Each source gets a dossier entry (what it covers, who made it, how credible, its terms). Public pages only; robots.txt and Crawl-delay obeyed; restricted sources never read. Every page read is logged (decisions/0157 §1) |
| 2. Extraction (reading pages) | Turn pages and files into facts, each keeping its page and date: code first (structured data, registries, tables: about 80%, free), boilerplate stripped, AI (Haiku) only for the narrative remainder. List pages split into their items; each item placed and linked to what we hold (0157 §2) |
| 3. Vetting and configuration | Cross-check against official registries and our own index; identity and duplicates; privacy and the protected-information tiers. Configuration lives here: which areas and themes are vetted and cataloged first |
| 4. The catalog (research) | Every vetted fact in our own record with its source: listings, landmarks, outdoor places, the Human Record, the Living Landscape, footprints, dossiers and research briefs, and the Topic index (our encyclopedia). Gaps in the catalog become research targets for the crawler |
| 5. Publishing | Writing from the catalog (guides, journeys, stories, events), behind rule-based gates and the protected-information standard ("tell the story, never the way in"); freshness shown |
| 6. Content maintenance | A loop back through Phases 1–3 that keeps what's published true: clocks (listings monthly to quarterly, never over 90 days; guides 30 days; dossiers 6 months; research briefs a year), events that start a check (a source changes, a listing closes); outcomes Refresh, Renew or Review |

## 2. Workflows (decisions/0160 §3)

Each workflow has its own queue and status, its own share of the crawler, its own report (in the admin, and Pulse when it
stalls), its own costs, and its decision named.

| Workflow | Started by | Phases | Crawler services it asks for | Worker tasks | Gate | Makes |
|---|---|---|---|---|---|---|
| W1 Listings | coverage gaps, towns searched (0149), situation gaps, search targets, Send the crawler, places named on pages, the monthly NPI/IRS/Recreation.gov files | 1–4 | ask open data, fetch, read a page, read a site, search the web | `coverage`, `second_look`, `targets`, `npi_bulk`, `irs_bulk` | the listing score (0044, 0076); one source can establish a listing, a government page by itself, vetted continuously | public listings (`unclaimed`) with their sources |
| W2 Keeping listings current | time (refresh by score, Google every 90 days, map points while missing), a source changing | 6 (through 1–3) | fetch, read a page, ask open data (Census), search the web, Google Places | `refresh`, `identity`, `geocode`, `places` | scoring decides; one site's "closed" is a signal, not a verdict | current details, map points, "closed?" items for Review |
| W3 Source register and place store | sites linked from pages we read, sites a second look accepted, Common Crawl's local public sites | 1–2 | read a site, read a page, read the hard way, search the web (terms) | `source`, `commoncrawl` | the register's call and each site's use rule (licence) | the source register; the place store ("Named on pages we read", staged into W1) |
| W4 Place guides | the guide job (busiest unwritten places first), Write a draft | 4–5 | ask open data (Library of Congress, Wikidata); Claude's own searches | (the site's guide job) | the confidence score; protected-place levels | public guides; journeys follow (W7) |
| W5 Guides on demand | a visitor searching a place with no guide (0147) | 4–5 | ask open data; queues the town for W1 | (the site) | half the daily AI allowance | a first-look guide, labelled |
| W6 Heritage catalog and topics | a place with a guide, hourly | 2–4 | ask open data (Library of Congress) | `heritage` | vetting (sources counted) and the protected tiers | the heritage catalog (Seeker), the Topic index, facts for guides |
| W7 Suggested journeys | a live guide, hourly, 3 a run (0146) | 5 | none (openrouteservice for travel times) | (the site) | rules | suggested journeys on the guide |
| W8 Find it | a name in a guide or story that we don't hold, every 15 minutes | 3–4 | ask open data, fetch, read a page, search the web | (the site, through the crawler's request queue) | the listing score (W1's gate) | listings, and linked names |
| W9 Landmarks and places on demand | the monthly landmark file; a place someone opens or searches that we don't hold (0148) | 1–4 | ask open data | `landmarks_bulk`, `rec_bulk` | 0066: `Landmarks::visibleSql()`; pins on a nation's land move to a public place | landmarks and places, with descriptions |
| W10 Live events | a live guide, while the Live events template runs | 1–5 | read a site (event pages), search the web | (the site's guide job) | official sources first; the allowance | upcoming events on the guide |
| W11 How-to guides | a need with official guidance (0063 §8), 2 a run | 1–5 | search the web, fetch, read a page | `howto` | official pages only | how-to guides |
| W12 Search learning | what people search and open after | 1, 4 | search the web (Tier 2); queues W1 | `learn` | 3 or more searches in 30 days | learned words, situation gaps, new sources |
| W13 Stories | a person writing (0043) | 5 | none; named places go to Find it (W8) | none | person-written only | stories |

## 3. Services

### 3.1 What the crawler can do (decisions/0160 §2)

The crawler is one service: it fetches and reads, politely and within budgets, for whichever workflow asks. It makes
nothing on its own and decides nothing about what's published.

| Service | What it does |
|---|---|
| 1. Fetch | One page; robots.txt and Crawl-delay honoured, 1.5 s apart; private addresses refused; 401/403/451 never tried another way |
| 2. Read a page | Its structured data, then its text: places with name, address, phone, hours, website; the kind of page; its town |
| 3. Read a site | Its sitemap and own links, the page log (an unchanged page answers 304), links to other sites for the register |
| 4. Ask open data | OpenStreetMap, Wikidata, NPI, IRS, Recreation.gov, Library of Congress, Census, Common Crawl |
| 5. Search the web (paid) | Tavily or Brave, only within the allowance |
| 6. Read the hard way (paid) | Tavily Extract for a page it can't fetch; Textract for a scanned PDF |

It keeps two shared memories every workflow uses: the **source register** (which sites, read or cite only, their terms and
trust) and the **place store** (every place named on a page we read, with its page).

### 3.2 One run, every 5 minutes

Railway starts the worker every 5 minutes. A run lasts at most about 4 minutes (`TIME_BUDGET` 240 s) and 60 page reads
(`MAX_FETCHES`).

1. **Ask the site for the plan** (`CrawlService::plan`): the tasks due (paused workflows and switched-off tasks left out),
   coverage first, then whichever task waited longest, each with its share of the run's time, page reads and paid calls.
   Bulk loads only at night (1–5 AM Arizona time), one a night, after the rest.
2. **Check outside services:** held servers (`ServiceHealth`: a ban, a refused key, a quota, or backing off), status
   checks due, the day's OpenStreetMap budget, and the towns and kinds asked recently (the cache). A held server is never
   called.
3. **Work each task inside its share.** A task starts only with at least 15 s left; unfinished work goes back unchanged:
   "not reached", never a failure.
4. **Report** each task: done, nothing to do, not reached, failed, off (no key or switch), or held. Paid calls go in the
   cost ledger, failed ones included.

### 3.3 The worker's tasks

| Task | Workflow | What it does | Outside services it calls |
|---|---|---|---|
| `coverage` | W1 | Businesses of one kind in one town cluster ("Gallup × Health care") | OpenStreetMap (1 query per job), Wikidata, then each place's website |
| `second_look` | W1 | Checks listings with only one source | NPI registry; web search, at most 2 per listing |
| `targets` | W1 | Searches people or staff queued ("cafés in Kayenta"); finds held for a person | OpenStreetMap, then the places' websites |
| `refresh` | W2 | Rechecks live listings' websites for changes | businesses' websites |
| `identity` | W2 | Confirms who a listing is | NPI registry, Census, web search |
| `geocode` | W2 | Map points from street addresses (never confidential ones) | U.S. Census geocoder |
| `places` | W2 | Checks listings against Google (same place, closed or not; keeps only Google's ID) | Google Places |
| `source` | W3 | Reads one "read regularly" source, only pages that changed | that source's site |
| `commoncrawl` | W3 | Finds our states' local public sites (az.us, nm.us) for the register | Common Crawl index |
| `heritage` | W6 | Catalog records (never the items) for each guide's place | Library of Congress |
| `howto` | W11 | Official pages for how-to guides | web search, then those pages |
| `learn` | W12 | Searches gaps open data couldn't fill; queues W1 | web search |
| `npi_bulk` | W1 | Monthly NPI file (health-care organizations in our states) | CMS download, at night |
| `irs_bulk` | W1 | Monthly IRS nonprofit list (confirms listings only) | IRS download, at night |
| `rec_bulk` | W9 | Monthly Recreation.gov places | Recreation.gov, at night |
| `landmarks_bulk` | W9 | Natural landmarks and nations' boundaries | USGS, Census, Wikidata, Wikipedia, Commons, at night |

The website (Bluehost) runs W4, W5, W7, W8 and W10 itself, and calls some services directly: OpenStreetMap when a visitor's
search finds little nearby and for the deep look on one business, openrouteservice for journeys, and Claude for guides
and research (inside the monthly AI cap).

### 3.4 Outside services and their limits

| Service | Who calls it | Limit |
|---|---|---|
| OpenStreetMap (Overpass) | `coverage`, `targets` (1 query per job); the website (visitor searches, deep look) | 2,500 queries / 250 MB a day together (crawler 1,500, site 1,000); half while a ban is open and two weeks after; each town and kind cached 3 months; one query at a time |
| Google Places | `places` | ~30 a day (the site paces the monthly allowance) |
| Web search (Tavily) | `second_look`, `identity`, `howto`, `learn` | the monthly allowance, paced over the days left; at most 10 a run |
| Claude | the website (guides, research, Find it, events) | the monthly AI cap; on-demand guides up to half the daily allowance |
| NPI registry | `second_look`, `identity`; monthly file | free |
| Census geocoder | `geocode` | free; a batch a run when due |
| Library of Congress | `heritage` | free; a few places a run |
| Common Crawl | `commoncrawl` | free; when due |
| Businesses' and sources' own sites | `coverage`, `targets`, `refresh`, `source`, `howto` | 60 page reads a run shared; robots.txt; 1.5 s apart |

At 5 queries a run × 288 runs a day, OpenStreetMap stays near one query a minute, inside the crawler's 1,500.

### 3.5 The check before every call (live 2026-11-14)

Every call to an outside service passes `ServiceHealth` first:
- **A ban, a refused key or a used-up quota** holds that server; nothing is sent to it. A Pulse case names the exact issue
  with its evidence, the service's link, "Check it now" and "Mark resolved". Work that doesn't need it carries on.
- **A busy or slow server** (429, timeouts, 5xx) is skipped until its wait is over; the provider's own wait is followed.
- **The hold lifts** when a person marks it resolved, or (being built) when its status check answers normally: it then
  returns by itself at half budget for two weeks.

## 4. Coming (approved 2026-11-14, being built)

- `overpass-api.de` back on both lists, held by its open ban; the check before use is the only gate, and it returns by
  itself when its daily status check answers 200.
- Each service's servers as settings in its Outside services card (add, pause, see why it's held).
- A pace per service inside the check: OpenStreetMap at most one query a minute; Google Places and web search spread
  evenly through the day.

## Questions for Jason

- Where this doesn't match what you expected: which phase, workflow or service?
- Should each task's live schedule (how often, its share, its last run and outcome) be shown in the admin next to this?
