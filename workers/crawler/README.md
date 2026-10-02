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
`OVERPASS_URL` (an Overpass server to try first; the public one and two mirrors are always tried after it).

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
needed.

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
