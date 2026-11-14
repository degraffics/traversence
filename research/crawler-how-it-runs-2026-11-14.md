# How the crawler runs (2026-11-14)

For Jason's review: what happens every 5 minutes, the tasks, the steps that turn a find into a listing, and which outside
services each part calls. Taken from the live code: `workers/crawler/crawler.py` (worker 2.5, on Railway) and
`app/lib/crawler/CrawlService.php` (the site's planner), and from decisions/0160. Each task's schedule (how often, its
share, its limits) lives in the site's database (`crawl_capture_tasks`, `crawl_workflows`), not in code, so it isn't
listed here.

## 1. One run, every 5 minutes

Railway starts the worker every 5 minutes. A run lasts at most about 4 minutes (`TIME_BUDGET` 240 s) and 60 page reads
(`MAX_FETCHES`).

1. **Ask the site for the plan** (`CrawlService::plan`). The site lists the tasks that are due, skipping any paused or
   switched off. Coverage (finding businesses) goes first, then whichever task has waited longest. Each task gets its
   share of the run's time, page reads and paid calls. The bulk loads are offered only at night (1–5 AM Arizona time),
   one per night, after the other work.
2. **Check outside services.** With the plan come the servers on hold (`ServiceHealth`: a ban, a refused key, a quota,
   or backing off), the status checks that are due, the day's OpenStreetMap budget, and the towns and kinds already
   asked recently (the cache). A held server is never called.
3. **Work each task inside its share.** A task starts only if it has time (at least 15 s). Work it doesn't finish goes
   back unchanged for next time: "not reached", never a failure.
4. **Report** each task: done, nothing to do, not reached, failed, off (no key or switch), or held (a service it needs
   has a problem). Paid calls are counted in the cost ledger, failed ones included.

## 2. The tasks

| # | Task | Workflow | What it does | Outside services it calls |
|---|---|---|---|---|
| 1 | `coverage` | W1 Listings | Finds businesses of one kind in one town cluster ("Gallup × Health care") | OpenStreetMap (1 query per job), Wikidata, then each place's own website |
| 2 | `second_look` | W1 Listings | Checks listings that have only one source | NPI registry; web search (Tavily), at most 2 per listing, inside the monthly pace |
| 3 | `targets` | W1 Listings | Searches queued by people or staff ("cafés in Kayenta"); finds held for a person | OpenStreetMap, then the places' websites |
| 4 | `refresh` | W2 Keeping listings current | Rechecks live listings' websites for changes | the businesses' websites |
| 5 | `identity` | W2 | Confirms who a listing is | NPI registry, Census, web search |
| 6 | `geocode` | W2 | Puts listings on the map from their street address (never confidential ones) | U.S. Census geocoder |
| 7 | `places` | W2 | Checks listings against Google: same place, closed or not; keeps only Google's ID | Google Places, paced by the site to about 30 a day |
| 8 | `source` | W3 Source register | Reads one "read regularly" source, only the pages that changed | that source's site |
| 9 | `commoncrawl` | W3 | Finds our states' local public sites (az.us, nm.us) for the register | Common Crawl index |
| 10 | `heritage` | W6 Heritage | Catalog records (never the items) for each guide's place | Library of Congress |
| 11 | `howto` | W11 How-to guides | Finds official pages for how-to guides | web search, then those pages |
| 12 | `learn` | W12 Search learning | Searches gaps open data couldn't fill; queues W1 | web search |
| 13 | `npi_bulk` | W1 | Monthly NPI file (health-care organizations in our states) | CMS download, at night |
| 14 | `irs_bulk` | W1 | Monthly IRS nonprofit list (confirms listings only) | IRS download, at night |
| 15 | `rec_bulk` | W9 Landmarks | Monthly Recreation.gov places | Recreation.gov API, at night |
| 16 | `landmarks_bulk` | W9 | Natural landmarks and nations' boundaries | USGS, Census, Wikidata, Wikipedia, Commons, at night |

The website (Bluehost) also calls some services itself, apart from the crawler: OpenStreetMap when a visitor's search
finds little nearby or for the deep look on one business, and Claude for guides and research (inside the monthly AI cap).

## 3. The six things the crawler can do (its services, decisions/0160 §2)

Every task above is built from these. The crawler fetches and reads for whichever workflow asks; it decides nothing about
what's published.

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

## 4. From a find to a listing: the six steps (W1)

1. **Find leads:** open data by kind and town (OpenStreetMap, Wikidata, NPI…), or a place named on a page we read.
2. **Check each lead's own website.**
3. **Stage the find** (`IntakeStager::stage`).
4. **Gate it:** real, complete, appropriate, unique (decisions/0076).
5. **If it isn't real yet, take the second look** (NPI, our place store, a deep look, web search), then the second tier
   (Claude settles it) when that's on.
6. **Publish, or send to Review.** One source can establish a listing, and a government page by itself; the listing is
   then vetted continuously (Jason, 2026-11-13).

## 5. How often each outside service is called

| Service | Who calls it | Limit |
|---|---|---|
| OpenStreetMap (Overpass) | `coverage`, `targets` (1 query per job); the website (visitor searches, deep look) | 2,500 queries / 250 MB a day together (crawler 1,500, site 1,000); half while a ban is open and two weeks after; each town and kind cached 3 months; one query at a time |
| Google Places | `places` | ~30 a day (the site paces the monthly allowance) |
| Web search (Tavily) | `second_look`, `identity`, `howto`, `learn` | the monthly allowance, paced over the days left; at most 10 a run |
| NPI registry | `second_look`, `identity`; monthly file | free; lookups per listing |
| Census geocoder | `geocode` | free; a batch a run when due |
| Library of Congress | `heritage` | free; a few places a run |
| Common Crawl | `commoncrawl` | free; when due |
| Businesses' and sources' own sites | `coverage`, `targets`, `refresh`, `source`, `howto` | 60 page reads a run shared; robots.txt; 1.5 s apart |

At 5 queries a run × 288 runs a day, OpenStreetMap stays near one query a minute, inside the crawler's 1,500. Every call
to every service passes the same check first: a server that has banned us, refused our key or used up its quota is held
until its status check answers normally or a person marks it resolved (then it returns by itself at half budget for two
weeks); a busy or slow one is skipped until its wait is over.

## 6. Coming (approved 2026-11-14, being built)

- `overpass-api.de` back on both lists, held by its open ban; the check before use is the only gate, and it returns by
  itself when its daily status check answers 200.
- Each service's servers as settings in its Outside services card (add, pause, see why it's held).
- A pace per service inside the check: OpenStreetMap at most one query a minute; Google Places and web search spread
  evenly through the day; a provider's own wait (Retry-After, 429, status-page slots) followed exactly.

## Questions for Jason

- Where this doesn't match what you expected: which task, step or service?
- Should each task's live schedule (how often, its share, its last run and outcome) be shown in the admin next to this?
