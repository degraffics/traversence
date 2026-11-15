# Hand-off: the service register (decisions/0163 §8 stage 2)

Session `session_01CXLi5h9itnngLeRubRrLfm`, from 2026-11-15. Jason: "start stage 2". Reads: 0163 §1, §6, §7, §8 item 2,
§9–§11; `handoffs/crawler-revamp.md` §7.1 open item 4 (the site's other outside callers onto `ServiceHealth`);
`handoffs/automation-switches.md`.

**State: plan shown to Jason, waiting for his OK.** Built on Sync 2026-11-15 12:03 UTC (mirror `ab8089f`). Nothing
claimed, nothing sent. The automation stays paused (master switch); `workers/crawler/` is not touched.

## What's there today (found in the live code, 2026-11-15)

Five places each hold part of a service's picture:
- **Outside services** (`app/lib/OutsideServices.php`, 0152): one card per company (36), its hosts, links, `CONTROLS`
  (sources, tasks, jobs, switches that use it), `SETTINGS` (the .env names on a card).
- **Services & usage** (`app/lib/Services.php`): use, cap, cost and 80/100% emails for web search, Google Places,
  Claude, SES only.
- **Thresholds & gates** (`app/lib/Thresholds.php`): `places_monthly`, the `osm_*` budget and pace, `paid_spread`,
  `ai_free_asks`, `ai_cover`.
- **ServiceHealth** (`app/lib/ServiceHealth.php`): issues, holds, probes, servers; only `OpenPlaces` (and through it
  DeepLook) calls it directly; Dossier, PlaceFinder, TravelLegs, SecondTier, Landmarks, AiClient check the pause
  switch through `Automation` but don't record failures.
- **Automation** (`app/lib/Automation.php`): the service switches.

Caps and paces, where each lives today:

| Service | Setting | Where today | Today's value |
|---|---|---|---|
| Claude | monthly cap | `.env` `AI_MONTHLY_CAP_USD` (code default 25; 0163 §7 says 40) | in `.env` (read at seed) |
| Claude | each feature's runs a day, budget, searches | `ai_features` (AI use) | stays there; the register links it |
| Claude | AI Assist free asks, cover | Thresholds `ai_free_asks` (3), `ai_cover` (0.5) | stored or default |
| Google Places | checks a month | Railway `GOOGLE_PLACES_MONTHLY` = 1000, overridden by Thresholds `places_monthly` (default 1000) | 1000 |
| Google Places | stop %, off | `.env` `PLACES_STOP_PCT` (90), `GOOGLE_PLACES` | |
| Google Places | per run | worker `PLACES_PER_RUN` (default 5; ceiling), plan's `per_run` | 5 |
| Tavily | searches a month | worker `SEARCH_MONTHLY` (default 1000, not set on Railway), sent with each ask | 1000 |
| Tavily | stop %, off | `.env` `WEBSEARCH_STOP_PCT` (90), `WEBSEARCH` | |
| Tavily | per run | worker `MAX_SEARCHES` (10; ceiling), plan's `paid` | 10 |
| Google + Tavily | spread through the day | Thresholds `paid_spread` (1) | |
| SES | monthly cap | `.env` `SES_MONTHLY_CAP_USD` (10) | |
| OpenStreetMap | queries a day crawler/site, MB a day, per minute, cache, ramp | Thresholds `osm_*` | 1500 / 1000 / 250 MB / 1 / 90 d / 14 d |
| OpenStreetMap | site per hour | `OpenPlaces::PER_HOUR` 60, `DeepLook::PER_HOUR` 40 (code) | |
| openrouteservice | matrix calls a day | `TravelLegs::MATRIX_A_DAY` 400 (code) | |
| Railway, Bluehost | flat plan | `.env` `RAILWAY_USD_MONTH` (5), `BLUEHOST_USD_MONTH` | |

Railway's variables (read 2026-11-15 through its API): only `GOOGLE_PLACES_MONTHLY=1000` among the limits; every other
limit is the worker's default.
