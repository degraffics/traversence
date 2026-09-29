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
- **Web search** (only with `BRAVE_API_KEY`): one search for the place and one aimed at **chamber of commerce**
  listings. A result counts only if it names the place **and** shows its street address or phone (in the
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

Optional variables: `BRAVE_API_KEY` (turns on the web search part of the second look; get one at
brave.com/search/api), `VERIFY_PER_RUN` (10; 0 turns the second look off), `VERIFY_TIME` (100),
`MAX_SEARCHES` (20 per run), `JOBS_PER_RUN` (5, at most 10), `MAX_FETCHES` (60), `TIME_BUDGET` (240),
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
