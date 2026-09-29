# Guide-crawl worker (Railway)

Finds local Support & wellness resources for places where the directory has gaps, and sends them to
traversence.com, where they are published or queued for review under the ADR 0044 guardrails
(`decisions/0044-confidence-gated-auto-import-of-directory-listings.md`).

It runs **off Bluehost** so the shared host only does two small requests per run: hand out jobs, and
check results. All fetching happens here.

## How it works

Every 15 minutes Railway starts the container; it does one small, budgeted run and exits:

1. `GET /api/crawl/jobs.php`: the next 2 jobs from the coverage queue (Admin → Cluster tools →
   *Queue these gaps for the crawler*). A job is one gap, e.g. *Gallup × Health care*.
2. One OpenStreetMap (Overpass) query per job for that kind of place around the cluster's ZIPs.
3. For each place with a website, one polite homepage fetch (robots.txt respected, 1.5 s apart). The
   website counts as a second, independent source only if the page names the place.
4. `POST /api/crawl/results.php`: the findings. Places seen in OpenStreetMap **and** on their own
   website can auto-publish; the rest wait in Listing Intake / Auto-imports → Quick approve.

Budgets per run: 2 jobs, 20 website fetches, 240 seconds, whichever comes first. A job that isn't
finished returns to the queue by itself when its 20-minute lease runs out.

## Railway setup

1. **New Project → Deploy from GitHub repo → `degraffics/traversence`.**
2. In the service's **Settings**:
   - **Root Directory:** `workers/crawler` (Railway then finds the `Dockerfile` and `railway.json`
     here, which set the 15-minute cron schedule and "never restart").
   - **Branch:** the branch this folder is on (`claude/magical-clarke-cn5pqi` until it is merged to
     `main`).
3. In **Variables**, add:
   - `TRAVERSENCE_URL` = `https://traversence.com`
   - `CRAWLER_API_TOKEN` = the same value as `CRAWLER_API_TOKEN` in the site's `.env`
4. Deploy. Each run's log lines look like
   `job 12 Health care in Gallup: 9 OSM, 6 sent -> 2 published, 4 to review`.

Optional variables: `JOBS_PER_RUN` (2), `MAX_FETCHES` (20), `TIME_BUDGET` (240),
`OVERPASS_URL` (the public Overpass API).

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
