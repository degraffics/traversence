# Video worker (Railway)

Makes feed-sized copies of the videos members and businesses post (decisions/0153 §8, 0154 §2), off Bluehost, so the
shared host only does two small requests a run.

Every 5 minutes Railway starts the container; it runs for up to 4 minutes and exits:

1. `GET /api/video/jobs.php?limit=2`: the next videos, each with a short signed link to fetch the original from the file
   store (R2) and signed links to upload each copy to. The worker never holds the store's keys.
2. ffmpeg makes each copy: H.264/AAC MP4, web-ready, no taller than 720 (and 1080 when the poster's plan plays it:
   Personal Plus, Pro, a business), never upscaled.
3. `POST /api/video/results.php`: what it made. The site checks each copy is really in the store before using it. A
   failure is tried again, 3 times in all.

What the site does with them: feeds play the 720p copy; Personal Pro's and businesses' originals stay, and **Watch in
4K** (or HD) plays them; Keep copies the plan's size into the Gallery (720p Personal, 1080p Plus, the original on Pro).

## Setup (once)

1. **The token:** make a long random value (for example `openssl rand -hex 32`). Put it in the site's `.env` as
   `VIDEO_WORKER_TOKEN=…` (cPanel File Manager), and in Railway as the variable `VIDEO_WORKER_TOKEN`.
2. **Railway:** New service → from this repo, root directory `workers/video` (it builds from the Dockerfile; the cron
   schedule, every 5 minutes, and the watch path, so it rebuilds only when this folder changes, are in `railway.json`;
   if Settings doesn't show them, set **Config File** to `/workers/video/railway.json`). Variables: `VIDEO_WORKER_TOKEN`, `TRAVERSENCE_SITE=https://traversence.com`.
   Optional: `RUN_SECONDS` (240), `JOBS_PER_ASK` (2).
3. **R2 CORS isn't needed:** the worker's signed links are server-to-server.

To pause it: `VIDEO_WORKER_PAUSED=1` in the site's `.env` (jobs wait in the queue).

Cost: Railway bills the minutes it runs. The plans that make large videos (Plus, Pro, business plans) cover it; free
members' videos come from the community budget (decisions/0154 §4), and free accounts' video pauses when it's spent.
