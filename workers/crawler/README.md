# Guide-crawl worker (Railway)

Finds local Support & wellness resources for places where the directory has gaps, and sends them to
traversence.com, where they are published or queued for review under the ADR 0044 guardrails
(`decisions/0044-confidence-gated-auto-import-of-directory-listings.md`).

It runs **off Bluehost** so the shared host only does two small requests per run: hand out jobs, and
check results. All fetching happens here.

## How it works

Every 5 minutes Railway starts the container; it does one small, budgeted run and exits:

1. `GET /api/crawl/jobs.php`: the next 5 jobs from the coverage queue (Admin → Cluster tools →
   *Queue these gaps for the crawler*). A job is one gap, e.g. *Gallup × Health care*.
2. One OpenStreetMap (Overpass) query per job for that kind of place around the cluster's ZIPs.
3. For each place with a website, one polite homepage fetch (robots.txt respected, 1.5 s apart). The
   website counts as a second, independent source only if the page names the place.
4. `POST /api/crawl/results.php`: the findings. Places seen in OpenStreetMap **and** on their own
   website can auto-publish; the rest wait in Listing Intake / Auto-imports → Quick approve.

**Second look.** Before the jobs, each run takes up to 10 listings waiting for review that have only one
source and looks each up by name, address and city (up to 100 seconds of the run):

- **NPI Registry** (the federal list of health-care providers; free, no key). A record whose name matches and
  whose location shows the same street address or phone counts as a trusted registry, which the guardrails
  accept on its own.
- **Web search** (only with a search key, `TAVILY_API_KEY` or `BRAVE_API_KEY`, and only when the NPI Registry
  didn't already confirm it): one search for the place and, if that found no chamber listing, one aimed at
  **chamber of commerce** listings. A result counts only if it names the place **and** shows its street address or phone (in the
  search snippet, or on the page itself). Each is labelled: chamber of commerce, well-known directory (BBB,
  Yelp, Yellow Pages, Healthgrades, findhelp...), government, the place's own website, or another website.
  Each website counts once; NPI copy sites are ignored.

The site adds these references to the listing and runs the guardrails again (ADR 0044): a registry match adds
20 to the score, each other website 10 (at most 40). Listings that now pass are published; the rest stay in
Auto-imports → Quick approve with their references listed. Listings with no street address or phone are
skipped, because nothing could confirm them.

**Search that learns (decisions/0061, batch 3).** Each run also calls `GET /api/crawl/learn.php`. That runs the
site's learning pass when it's due (every 6 hours: scoring what people open after their words, and finding *situation
gaps*, a need like Towing with nothing within 25 miles of an area where people searched it). Gaps covered by open
data become crawl jobs like the others (group `sit:<key>`), each carrying its own OpenStreetMap filters and, for
official places (hospitals, police, post offices, libraries...), Wikidata classes: **Tier 1**, free. Gaps Tier 1 can't
fill come back as **Tier 2** tasks: one web search each (Tavily, at most once a month per gap), keeping only pages on
.gov, .edu, state and local .us sites, or sources marked "Read regularly", that name the need and one of the area's
towns ("Saint Johns" and "St. Johns" both count). They go back with `POST /api/crawl/learn.php`: each page becomes a
source on the site, and one that lists 3 or more phone numbers is read regularly from then on.

Budgets per run: 5 jobs, 60 website fetches, 240 seconds (under the 5-minute interval, so runs never overlap), whichever comes first. A job that isn't
finished returns to the queue by itself when its 20-minute lease runs out.

## Railway setup

1. **New Project → Deploy from GitHub repo → `degraffics/traversence`.**
2. In the service's **Settings**:
   - **Root Directory:** `workers/crawler` (Railway then finds the `Dockerfile` here).
   - **Branch:** the branch this folder is on (`claude/magical-clarke-cn5pqi` until it is merged to
     `main`).
   - **Deploy → Cron Schedule:** `*/5 * * * *`, and **Restart Policy:** Never. Set these in the page:
     Railway is retiring config files (`railway.json` is kept only as a record of these settings) and
     new services can't opt in to them.
3. In **Variables**, add (check each shows its value, not `<empty string>`):
   - `TRAVERSENCE_URL` = `https://traversence.com`
   - `CRAWLER_API_TOKEN` = the same value as `CRAWLER_API_TOKEN` in the site's `.env`
4. Deploy. Each run's log lines look like
   `job 12 Health care in Gallup: 9 OSM, 6 sent -> 2 published, 4 to review`.

Optional variables: `TAVILY_API_KEY` (turns on the web search part of the second look; tavily.com has a free
plan of 1,000 searches a month, about 500 listings at two searches each), `BRAVE_API_KEY` (paid alternative,
used only when there is no Tavily key), `VERIFY_PER_RUN` (10; 0 turns the second look off), `VERIFY_TIME` (100),
`MAX_SEARCHES` (10 per run), `JOBS_PER_RUN` (5, at most 10), `MAX_FETCHES` (60), `TIME_BUDGET` (240),
`OVERPASS_URL` (an Overpass server to try first; the public one and two mirrors are always tried after it), `LEARN_PER_RUN` (3 Tier 2 searches per run; 0 turns
them off), `LEARN_TIME` (45 seconds). Tier 2 shares `MAX_SEARCHES` with the second look. `SEARCH_MONTHLY` (1000): the search plan's monthly allowance. After each run the worker tells the site which search it has and how many searches it made (`POST /api/crawl/usage.php`), and the admin dashboard's System status shows "Tavily: 340 of 1,000 searches this month", amber at 80%, red when it's used up.

## Search targets (decisions/0066)

Each run the worker also takes up to `TARGETS_PER_RUN` search targets (2; 0 turns it off), within `TARGETS_TIME`
seconds (90). A target is a town (or county) and a kind of business, queued from Admin → Search → Search demand
(`GET /api/crawl/targets.php`). It runs the same OpenStreetMap query and `candidate()` checks as a crawl job, around
the place's ZIPs, and posts what it found to `POST /api/crawl/targets.php`. The site stages them in Listing Intake,
held for a person and never auto-published, because the place may be outside our areas.

**New kinds of place (decisions/0067).** When search adds a kind of place on its own (Cooking Classes), the site
queues a target for it in every town we cover (`CrawlTargets::queueKind`, kind `cat:<category id>`). The worker looks
for it by the kind's own words: OpenStreetMap places whose name carries them, and, when there's a search key, one web
search per target ("Cooking Classes in Show Low, AZ"). A web result counts only when it's the business's own site (not
a directory, review site, social media, agency or school page), names the town, carries the kind's
words and shows a ZIP in the place; its name, phone, address and hours come from that site. These are our own towns,
so the candidates aren't held: Listing Intake's usual checks (and auto-import, when it's on) decide. With a search key,
a new kind costs about one search per town we cover (43 now), spread over runs by `TARGETS_PER_RUN` and
`MAX_SEARCHES`.

**Facts from the business's own website.** For every candidate (crawl jobs and targets alike): when its website
names the place, the name, address, phone and hours are sourced to the website. The phone comes from the site when it
shows one (a `tel:` link or a number), the address when the site shows the house number and street, and the hours
from its schema data. OpenStreetMap is the lead: what the site doesn't show stays sourced to OpenStreetMap, for a
person to check.

## Map points (decisions/0053, 2026-10-04)

First thing each run, the worker asks the site to place up to `GEOCODE_PER_RUN` batches of street addresses on the map
(2; 0 turns it off), within `GEOCODE_TIME` seconds (420). It does this with `POST /api/crawl/geocode.php`, and
`GET` returns how many are left. The site sends each batch of up to 500 to the U.S. Census geocoder itself
(`api/lib/Geocoder.php`), so the worker never sees addresses. Only points that are missing or exactly the ZIP's
middle change, and confidential listings are never sent. A batch can take a few minutes, so this call waits up to
250 seconds. A Census outage is logged and the run carries on. It is the same as Admin → Crawler → Map points →
Find points, without anyone pressing it.

## How-to guides (decisions/0063 §8)

Each run the worker takes up to `GUIDES_PER_RUN` guides (2; 0 turns it off) that people asked for, by searching "how
to …" 3 or more times in 30 days or by staff asking in Admin (`GET /api/crawl/guides.php`). It spends up to
`GUIDES_TIME` seconds (45) on them:

1. Two web searches (sharing `MAX_SEARCHES`): `how to … extension`, then `how to … site:.gov OR site:.edu`.
2. Only official pages are read: `.gov`, `.edu`, `.mil`, or a state's `.us`. Each result must be about the guide's
   words ("compost" matches "composting").
3. Up to 4 pages. From each: its best ordered list (3 to 20 items that read as sentences; navigation and breadcrumbs
   are skipped), or else its "Step 1 …" headings. At most 15 steps of 300 characters each.

It sends the pages back (`POST /api/crawl/guides.php`). The site drafts from the page with the most steps and keeps
the others as sources. A person rewrites the steps in our words, checks them against the sources and publishes;
nothing shows before that. If no official page lists steps, the site marks it "nothing found yet" and asks again in
30 days. With no search key the job reports that and does nothing.

## Building identities (decisions/0062, step 4)

Each run, after the second look, the worker takes up to `IDENTITY_PER_RUN` listings (5; 0 turns it off) that have no
phone or website (`GET /api/crawl/identity.php`) and spends up to `IDENTITY_TIME` seconds (60) looking them up, in
this order:

1. **Its own website**, when the listing has one: the home page and up to 3 contact/about pages. A `tel:` link or the
   number printed most, a `mailto:` address, links to its social pages, and its hours.
2. **The NPI Registry**, for a phone at the address we hold (health care).
3. **One web search** (`"Name" Town ST`, shares `MAX_SEARCHES`), only for what's still missing:
   - a result whose domain carries the name and that shows the address or town is its own website, and is read as
     in step 1;
   - another page counts only when it names the place **and** shows its street address; the phone printed nearest
     after the name is taken.
   - **Google, Yelp and Facebook** are never fetched or copied. Their search snippet only confirms a phone found
     above (or our address), and their page is kept as a link.

It sends back each value with the page it's on and what that page matched (`POST /api/crawl/identity.php`). The site
decides what goes on the listing:
- straight on: a value from its own website, a registry or a government page, or one two separate websites agree on;
- to the admin helper ("Found by the crawler"): anything else, for a person's one tap.

Nothing a person entered is overwritten, and a value a person marked "Not right" is never sent again. A confidential
address is never sent to the worker: it searches by name and town. A listing where nothing is found is looked at
again in 30 days. The site tops up the queue on its own; Admin → Identities shows it and has **Look now** per
listing.

## Pausing

- On the site: `CRAWL_QUEUE=off` in `.env`. The worker sees "paused" and exits without doing anything.
- Auto-publishing only: `AUTO_IMPORT=off` in `.env`. Findings still arrive, but all wait for review.
- On Railway: remove the cron schedule or pause the service.

## Testing locally

```
TRAVERSENCE_URL=http://localhost:8000 CRAWLER_API_TOKEN=... \
OVERPASS_FIXTURE=osm-sample.json WEB_FIXTURE=web-sample.json python3 crawler.py
```

`OVERPASS_FIXTURE` is an Overpass JSON reply and `WEB_FIXTURE` maps URLs to HTML, so no network is
needed. `WIKIDATA_FIXTURE` is a SPARQL JSON reply and `SEARCH_FIXTURE` maps a query (or `*`) to
`{"results": [{"url", "title", "description"}]}`.

## Workload

About 60 jobs an hour: one OpenStreetMap query per job (well inside the public Overpass fair-use
limits), at most 60 homepage fetches per run, 1.5 s apart. Bluehost sees one small request to hand out
jobs and one per finished job. To go faster, raise `JOBS_PER_RUN` (the site caps it at 10) before
shortening the schedule; keep `TIME_BUDGET` below the schedule interval so runs don't overlap.


## Monthly NPI Registry load (decisions/0048)

| Setting | Default | What it does |
|---|---|---|
| `NPI_BULK` | on | Monthly NPI Registry load (decisions/0048). When the site says it's due, that run downloads CMS's full NPI file (~1 GB), keeps health-care organizations in AZ and NM, and sends them in batches. `off` stops it. |
| `NPI_TIME` | 1500 | Seconds allowed for one monthly load. |
| `IRS_BULK` | on | Monthly IRS exempt-organization list (decisions/0048): `eo_az.csv`, `eo_nm.csv` from irs.gov, used by the site only to confirm listings. `off` stops it. |
| `RIDB_API_KEY` | (none) | Recreation.gov RIDB key. With it, once a month the worker loads public campgrounds, recreation areas, trailheads and visitor centers in AZ and NM for place pages (decisions/0048). |

## Natural landmarks (monthly)

When the site says they're due (`GET /api/crawl/landmarks.php`), one run loads natural landmarks for the site's states
and does nothing else (decisions/0058 §24):

1. **USGS Geographic Names** (`DomesticNames_<state>_Text.zip`, a few MB each): the named natural features.
2. **Census AIANNH** (tribal nations' boundaries, about 9 MB): each feature inside a nation's lands is marked with the
   nation's name. The site never lists those without a person.
3. **Wikidata** (one query per quarter of each state): the features' Wikipedia articles and photos, by GNIS id.
4. **Wikimedia Commons:** each photo's license and author. Only CC0, public domain, CC BY and CC BY-SA photos are kept.
5. **Wikipedia:** the first three sentences of each article.

It keeps the notable ones (an article, a photo, or a landscape word in the name) and posts them in batches of 500.
It also sends the nations' boundaries, simplified to about 50 m, one area per request. The site keeps journey and photo
pins off a nation's land except at its public places (decisions/0058 §26).
`LANDMARKS=off` turns it off. No key is needed. Test it with `GNIS_FIXTURE_DIR`, `AIANNH_FIXTURE` and `WIKI_FIXTURE`
(see the top of `crawler.py`).
