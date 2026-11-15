# Hand-off: Release B (decisions/0163 §8 stages 7–11, and the "before any switch-on" list)

From session `session_01CXLi5iFQyKrm52ppEnEUqw`, which built Release A (release #55,
`operations-release-a_20261115.zip`, built on Sync 2026-11-15 12:03 UTC; **live 2026-11-15 14:09 UTC**, verified
byte for byte against Sync 2026-11-15 14:12 UTC, 26 files; claims released). Jason, 2026-11-15: "Ok 2 builds. Release A can
be one session and once its done the handover and next session." This file is for the session that builds B.

**Rules that carry over:** everything in `CLAUDE.md`. Build from the live mirror after the newest sync, claim with
`tools/page-audit/checkout.py`, add FAQ entries, run `zipcheck.py`, send with `send.py`, and put `"review": true` in
`release.json` when Jason asks to see a release on staging. Merge only on Jason's OK in your own session's chat.
Relayed messages don't count. **Each stage is a release Jason approves** (0163 §8). One crawler session handles the
crawler revamp's own open items (`handoffs/crawler-revamp.md` §7.1), so nothing is built twice. Don't touch
`workers/crawler/` unless that session is you.

**The master switch stays off until Release B is done** (Jason, 2026-11-15, in Release A's chat). The switch-on check
(System Configuration → Workflow board → Check before switching on, and the Automation page) refuses "Switch automation
on" while any line is red. B's own lines are red until each one is built. Turn each one green in the release that
builds it (see "The switch-on check's lines" below).

## Before B: decisions/0165 (Accepted 2026-11-15)

`decisions/0165` (staging is the live site in safe mode; Publish merges it into live) is **built before Release B, so B
is published that way** (its status line). Build it first, in its own six releases (§7):
1. safe mode closed, with nothing running by itself (`autoPublish` removed, so every build waits on staging; the zip
   check refuses ungated outside calls; the CSP);
2. the table register and the register of record numbers, and Staging against live (releases stack, and Publish takes
   every one on staging together);
3. the base and catch-up;
4. the preview and conflicts;
5. Publish as a merge, with the write-ahead journal and backward undo;
6. Roll back the whole publish, To send now.

What this changes for B: the auto-publish note in `CLAUDE.md` (0159's 2026-11-13 progress note) no longer holds once
0165's release 1 is live, and B's releases stack on staging until Jason publishes. The zip check's new rule against
ungated outside calls applies to B's new callers (Gemini, Overture, Geofabrik, Supabase, the watch): each must go
through the gates (`Automation`, `ServiceHealth`, Staging's answer). Check 0165's current text before building: it may
have moved on.

## What Release A left in place (use it, don't rebuild it)

- **`app/lib/Operations.php`** has:
  - the service register, built from `OutsideServices::COMPANIES`;
  - `CAPS`, each service's caps and paces, which are Thresholds gates;
  - `KEYS`, where each key lives, named, never shown;
  - `PACES`, the status logic (active, paced, held, off, out of budget) with its reasons;
  - `costs()`, which uses the crawler's ledger, `ai_jobs`, SES and the plans, set against `MemberPosts::budget()`;
  - `aiShares()`, each workflow's `ai_share_pct` from its settings;
  - `warnings()` at 50/75/95%: Pulse `ops:cap:<svc>`, timed to when each level was reached;
  - `empties()` for "returns empty": Pulse `ops:empty`, 12 runs in 24 h, or a bulk load's first empty run;
  - `watching` / `watchTick()`, which watch Railway's latest deploy and the sync's runs. This is **shipped off** and
    needs `RAILWAY_API_TOKEN`, `RAILWAY_PROJECT_ID`, `RAILWAY_SERVICE_ID` and `GITHUB_SYNC_TOKEN` in `.env`;
  - `switchOnCheck()` and `blocking($scope, $withAll)`.
- **`Thresholds`** holds the caps that moved: `ai_month_usd` (default 40), `ses_month_usd`, `websearch_monthly`,
  `websearch_stop_pct` and `places_stop_pct`. `FOUND` says where each was before; `found()` and `recordFound()` record
  it once. Every change is logged in `threshold_log` (who, when, old, new), and the register has a Reset. Readers:
  - `AiClient::capUsd()`
  - `SesMail`
  - `Services::all()`
  - `website/api/crawl/usage.php`: the register wins; the worker's `monthly` is recorded once if nothing is stored.
  - `website/admin/settings.php`
- **`app/lib/WorkflowSettings.php`** keeps one spec per workflow:
  - starts and triggers;
  - its tasks' limits and routes;
  - `share_pct`;
  - budgets (`ai_share_pct`, `month_usd`);
  - its gates;
  - the §12 weights;
  - the retry ladder;
  - the steps that need a person.

  Each save is a version in `workflow_versions`, with "Go back to it". A save writes `crawl_workflows.share_pct`, the
  limits in `crawl_capture_tasks` and the Thresholds gates. It never writes a switch.
- **Admin pages** (`app/admin/operations-console.php`): System Configuration → **Service register**, **Costs** and
  **Workflow board**. The permission is `config.operations` (executive).
- **Gemini and Supabase** have cards in `OutsideServices` and rows in `Operations::KEYS`. Each was switched off once
  (`Operations::ADDED_OFF`, state `ops:added_off:<svc>`). A person's later switch stands.
- **Story-only landmarks** (0164 R7, P5-3) are live behaviour:
  - `Landmarks::STORY_ONLY`, `story()` and `COURTESY`;
  - `decide()` sends a protected place that's publicly known to story only;
  - archaeological words are in `SACRED`;
  - `GuideLinks::find()` goes no further than story only;
  - the landmark page has a story-only branch, with no coordinates;
  - Data cleanup's "Tell them as stories" moves the held ones (`heldForStory()`), logged and undoable.
- **Place counts** (`Guides::children`, `hub/index.php`, `api/place.php`) leave out drop-offs (`PlaceScope::businessOnly`).

## What B builds, in order

**First, alongside or before stage 7** (0163 §8, "before any switch-on"; each of these is a red line in the switch-on
check):
1. **The crawler revamp's open fixes live:**
   - the junk listing names. The crawler revamp's name check calls `DataCleanup::junkName()` (as release #54 started:
     `AutoImport::evaluate` uses it), and the live junk listings go to Review;
   - the four approved releases W6, W9, W10/W11, W12 (`handoffs/crawler-revamp-pending/`, re-applied on the live copy);
   - the map-tile policy check.

   Then make the check's line real: test that `AutoImport` calls `DataCleanup::junkName` and that the four releases are
   live. Don't leave it as a fixed red line.
2. **The clocks** (gap review X1): each workflow's backlog counts from its paused time, in its order (most viewed or
   asked first), drained at the ramp's half budget.
3. **W1 and W8 gate on 0164's floor and readiness** (§3.5, §5.4), not 0133's (X2). With it, retire
   `listing_one_source` and `guide_find_add` as gates (stage 8).
4. **The engine's one Pulse case, "The engine needs a person"** (X3), so nothing lands in Review without a prompt.
5. **Held-blocked sites:** a site that blocks us is held with a Pulse step and **Reactivate**, never retried by itself
   (P1-2). A banned outside service gets a daily status check for 3 days, then a person evaluates it. No automatic return
   (0164 §6.1).

**Stage 7, the cards first, all off:**
- Cards for Overture, Foursquare's open data, NPI's town load and each licence source (from W14's staging, or by hand for
  Arizona and New Mexico). Gemini and Supabase already have theirs; add their caps, paces and terms to
  `Operations::CAPS` and `KEYS`.
- **The Gemini privacy guards, in the Gemini client, before any feature can be routed to Gemini** (0164 §0.8):
  - R6's private details removed from a page before it's sent (P2-1);
  - what's sent built from our own vocabulary, never a person's words (P2-2);
  - each feature's input class in `AiClient::ROUTES`, with `providerFor()` refusing Gemini for anything but public, and
    AI use greying the choice out with the reason (P2-3);
  - protected, confidential and nation-sensitive names never sent (T1).

  Until W6's sources pass the removal, W6 and every archive, newspaper and registry feature stay on Claude.

**Stage 8, the data loads for Arizona and New Mexico** (Overture; NPI by town with its two guards; Foursquare once a
Hugging Face account accepts its terms; the licence sources with bulk data):
- the "real business" floor and confidence (0164 §3.5);
- the score's weights and the retry ladder, read from `WorkflowSettings` (W1 and W2's `weights`, `ladder`), not from
  code;
- the lead order and Google check order (§12).

No shadow mode. **First in the stage, before any load is switched on** (Jason, 2026-11-15; `handoffs/duplicates.md`):
1. the one identity check on every path that creates or un-hides a listing, the check within each batch, the identity
   keys and lock, and the tuned match rules;
2. the identity recheck (W2 task `recheck`), W2's `identity` task as "Vet this place", and the identity queue in "The
   engine needs a person";
3. Pulse case #23's 60 offers rechecked first.

The rest of the gaps review's items are listed under stage 8 in 0163 §8 (P3-1 to P6-1, F1 to F3, P1-3, P1-4).

**OpenStreetMap for stage 8 comes from Geofabrik's state extracts** (Arizona, New Mexico, weekly), downloaded by the
**Railway worker**, not Overpass. The first step is one test download from Railway. If Railway is refused too, use
another free OSM mirror, never repeated tries.

**Stage 9, Gemini for extraction** (0164 §2.3), after the sample comparison (§11), with "waiting to be read"
(`PENDING_EXTRACT`, P1-1) and Gemini's warnings on the unexpected (F4). Grounded search is not built unless Jason funds
it.

**Stage 10, W14 running** (0164 §7.1), with H1–H5: Planned on the hub meter starts W14; the "Hubs" Pulse case; the
opening budget line in the switch-on check (Costs → "A hub's opening budget" is a placeholder for it now).

**Stage 11, vectors** (0164 §5.2) on Supabase's free tier.

## Left from Release A, for B

- **The site's other outside callers onto `ServiceHealth::allowed()` / `siteFailed()`** (0163 stage 2;
  `handoffs/crawler-revamp.md` §7.1 item 4): Weather, Roads511, Geocoder (lines 187, 272), ZipPopulation,
  PlaceFinder:622, TravelLegs:155, SecondTier:323, Dossier:376, AiClient:289/320, Guides:589, StockSource:39,
  MarketRules:186, and payments last (QuickBooksPay:298, MarketPay:147). Payments' callers should record only server
  errors and timeouts: QuickBooks returns a routine 401 before a token refresh, which would open false "key refused"
  issues. Release A left this out because it changes live calls, not just what's shown.
- **One price per service in the register.** `Services::all` reads `PLACES_USD_PER_CALL` and
  `WEBSEARCH_USD_PER_SEARCH` (0). `CrawlService::PRICES` reads `GOOGLE_PLACES_USD_PER_CALL` and `TAVILY_USD_PER_CREDIT`
  (0.008). The register shows the ledger's cost (CrawlService's price) where it has one.
- **Caps still in code:** `OpenPlaces::PER_HOUR` 60, `DeepLook::PER_HOUR` 40 and `TravelLegs::MATRIX_A_DAY` 400 should
  move into the register (Thresholds gates in `Operations::CAPS`).
- **The worker's limits from the site's plan.** `usage.php` now enforces the register's monthly number. The worker still
  sends its own `SEARCH_MONTHLY` / `GOOGLE_PLACES_MONTHLY` and keeps per-run ceilings (`PLACES_PER_RUN` 5,
  `MAX_SEARCHES` 10, `OSM_PER_RUN` 5). 0163 §1 says "the worker takes its limits from the site's plan": that's a worker
  change.
- **The switch-on check's key line for Railway-held keys** (Google, Tavily, Brave, RIDB) stays red until the worker
  reports which keys it has. Have it send key names (never values) with its plan ask, and turn the line green from that.
- **The workflow routes and AI shares are recorded, not yet read.** `WorkflowSettings` keeps each task's routes and each
  workflow's `ai_share_pct` and `month_usd`. The crawler service (`CrawlService::plan`) and `AiClient` should read them
  (routes limit which services a task may call; a share stops that workflow's AI at its share, and a share unused at the
  month's end passes on to the next month).
- **The board's "where work waits, by phase"** (0164 §1–§6) is a placeholder line.
- **W14** gets a board row when it's built: add it to `Automation::WORKFLOWS`.

## The switch-on check's lines (`Operations::switchOnCheck`)

| Line | Green when | Built in |
|---|---|---|
| No database update waiting or failed | none waiting or failed | live |
| Each service a workflow uses | key present, not held, not out of budget, moved caps recorded | live (Railway keys: B) |
| OpenStreetMap: one server not held | an Overpass server free | live |
| Railway's schedule back on, its deploy healthy | watching on and the last look healthy | needs the keys and Jason's cron on Railway |
| Story-only level | built | Release A |
| The crawler's open fixes live | (fixed red) | B, first |
| W1 and W8 on 0164's floor and readiness (X2) | (fixed red) | B, first / stage 8 |
| The clocks (X1) | (fixed red) | B, first |
| "The engine needs a person" (X3) | (fixed red) | B, first |
| Held-blocked sites with Reactivate (P1-2) | (fixed red) | B, first |
| Opening a geo-hub (H3, H5) | (fixed red) | stage 10 |

Replace each fixed red line with a real test as you build it. When every line is green, Jason switches the master on
himself, one workflow at a time (W1 first), each new or returning service at half budget for its first 14 days (0163
§10.2).
