# ADR 0163: Operations: one view of outside services, workflows and spend

**Status:** Proposed (written 2026-11-14 by the session manager from Jason's answers in chat; for his review). It builds on
`decisions/0160` (the crawler as one service, the workflows), `0133` (the six phases), `0152` (Outside services, one card
per company), `0161` (check-out), `0162` (migrations), and the service check built 2026-11-14 (`ServiceHealth`,
releases #43, #44, #46). It keeps the "no income, no expense" rule (CLAUDE.md) and Pulse's rules (one case per kind of
chore, a step per item, steps as buttons). The review that led here: `research/crawler-how-it-runs-2026-11-14.md`.

## Context

Jason, 2026-11-14: "this is what we need to determine how to manage to ensure things like usage expense, service caps,
active/inactive stats come together", and "I want to ensure we are not creating additional gaps or more human manual
work either."

What we have, each piece working on its own:
- **Caps in three places:** Railway settings (`GOOGLE_PLACES_MONTHLY`, `SEARCH_MONTHLY`, `PLACES_PER_RUN`…), the site's
  `.env` (`AI_MONTHLY_CAP_USD`, `SES_MONTHLY_CAP_USD`), and Thresholds & gates (OpenStreetMap's budget and cache). Each
  counts only our own use.
- **Costs in separate ledgers:** the crawler's paid calls (`crawl_costs`), Claude (`ai_jobs`), email (SES). No total per
  service, workflow or month; nothing sets spend against revenue or the community budget.
- **Silent services:** a service with no key reports "off"; one paced to nothing said "this run may make 0" with no reason
  (web search and Google Places, 2026-11-13/14, found to be the day's share spent early).
- **Runs "ok" with nothing found:** OpenStreetMap has found nothing since 2026-11-13 while every run reported ok.
- **The schedule and five workflows invisible:** each task's frequency, share and last outcome are only in the database
  (`crawl_capture_tasks`, `crawl_workflows`); W4, W5, W7, W8 and W10 run on the website outside the crawler's plan and
  report.
- **Warnings after the failure:** `ServiceHealth` opens a Pulse case when a service breaks, never before a cap.
- **Watching depends on a Claude session:** Railway deploys, stuck syncs and sessions are checked by the session manager's
  hourly check.

## Decision

One operations view on the three layers (phases, workflows, services), where **the system counts, checks and switches**
and a person decides only what the system can't.

### 1. The service register

Every outside service we call (OpenStreetMap, Google Places, Tavily, Claude, SES, the free registries, Common Crawl,
openrouteservice…) is one row, extending the Outside services cards (0152) and `ServiceHealth`:
- which workflows and tasks use it; where its key lives (named, never shown);
- **its cap and its pace, in one place**: the one setting the code reads. Railway's and `.env`'s copies are retired: the
  worker takes its limits from the site's plan, and the site's code reads the register. The register starts from today's
  values, found automatically; nothing is retyped;
- this month's use and cost by our count, and by the provider's where it has a usage feed (side by side, the difference
  flagged); otherwise labelled "our count only";
- its status, always with the reason: **active**, **paced** (to what), **held** (why: ban, key, quota, backing off),
  **off** (no key, switched off), **out of budget** (until when);
- what it produced (finds, listings, facts, pages) and the cost per result.

Every path goes through it: the website's own calls (OpenStreetMap, Claude, openrouteservice, deep look) pass the same
check and budgets as the crawler's.

### 2. Costs together

The crawler's ledger, Claude's (`ai_jobs`) and email's, per service, per workflow and per month, in one place; set against
revenue and the community budget (3% of revenue, $25 floor, 0154), so "no income, no expense" is checked by the system.

### 3. The workflow board and the task schedule

- **W1–W13**, the website's own included: queue, last run, outcome, output, cost, and the phase (0133) where work waits.
- **The task schedule:** each task's frequency, share of a run, limits, last run and outcome; the settings the planner
  (`CrawlService::plan`) already uses, shown and editable in the admin.

### 4. Warnings before limits, and "returns empty"

- **Levels (Jason, 2026-11-14): 50%, 75% and 95%** of any cap or budget (and a provider's balance where it's read): one
  Pulse case per service, updated as it climbs, with the step that fixes it (raise the cap, top up, pause the work) as a
  button.
- **"Returns empty"** (Jason's wording): a task that had work and got answers, but nothing came back. Not "nothing to do"
  (an empty queue) and not "held" (a service problem with its own case). Flagged when a frequent task has worked at least
  12 times over 24 hours and returned nothing; a monthly load on its first empty run. **One Pulse case, updated every
  hour**, listing every service and task that returned empty, a step each (its runs, what it asked, the last answers;
  Check it now, Pause the task); a step clears itself when the task finds something again; the case closes when no steps
  are left.

### 5. Watching built into the site

The site checks Railway's deploys (backboard.railway.com) and the sync's runs itself and raises a Pulse case when one
fails or stalls, so it no longer depends on a Claude session. The session manager's hourly check then covers sessions
only.

### 6. Less manual work, no new gaps

- **Replace, never add beside:** each cap, count and status moves into the register and the old place reads from it.
- **Built from what exists:** `ServiceHealth`, `crawl_costs`, `ai_jobs`, `CrawlService`, Outside services, Thresholds &
  gates. No new ledgers.
- **Silence is a status:** nothing can be quietly off.
- **No new spend:** usage feeds and status checks are free.
- **What a person still does:** approve releases; decide on a cap, a top-up or a pause when warned; contact a provider
  about a ban (Pulse gives the issue and evidence). Jason, 2026-11-14: no other chores kept by hand.

### 7. Anthropic (Claude) settings, as set 2026-11-14

- Auto-reload in the Console: back up to $20 when the balance reaches $5 (about $30 a month at today's pace).
- The site's AI cap (`AI_MONTHLY_CAP_USD`): **$40** a month, moving into the register.
- Anthropic's monthly spend limit: **$50**; Console email alerts at **$20 and $35**.
- No Admin API key: the Console is an individual account, which can't create one (future-considerations: "The Claude
  Console as an organization"). Claude's row shows "our count only" until then.

### 8. Build order

Each stage a release Jason approves; one session per stage, from its handoff.

1. **Already live (2026-11-14):** the service check, holds and automatic return (half budget for 14 days), server lists
   as settings, pacing per service (releases #43, #44, #46; worker 2.6).
2. **The service register:** one row per service; caps and paces moved into it from Railway and `.env` with today's
   values; status with reasons; the website's other outside callers onto `ServiceHealth` (handoffs/crawler-revamp.md
   §7.1 item 3).
3. **Costs together,** against revenue and the community budget.
4. **The workflow board and the task schedule.**
5. **Warnings (50/75/95%) and "returns empty".**
6. **Watching built into the site** (Railway, the sync).

The crawler revamp's own open items (the four approved releases W6, W9, W10/W11, W12; junk listing names; the map-tile
policy check) go first or alongside, from `handoffs/crawler-revamp.md` §7.1, in one crawler session, so nothing is built
twice.

### 9. The switches (Jason, 2026-11-14; built as release automation-switches_20261114, still Proposed with the rest)

Jason, 2026-11-14: "Please update the site settings to be off until switched back on, also give me a master switch to
turn on and off. Also I see the services as routed services connected to a task so we should be able to turn those on
and off as well." **System Configuration → Automation** (`app/lib/Automation.php`, `app/admin/automation-console.php`):

- **Four layers, one rule:** workflow → task → route → service. A call happens only when the master switch, its
  workflow, its task and its service are all on. A route is a task's use of a service (as `AiClient::ROUTES` maps an AI
  feature to its providers); pausing a service closes every route to it.
- **The master switch** (`automation_switches`, master/all) stops everything that gathers or creates content: the
  crawler's plan and every crawler endpoint answer paused (`CrawlQueue::pausedWhy()`), so a Railway run ends at once even
  if its cron returns; the site's workflow jobs skip (`Automation::JOBS`, checked in `Scheduler::run`); the AI features
  that serve workflows wait (`CrawlService::FEATURE_WF`, in `AiClient::allow`); and their outside calls aren't made
  (OpenStreetMap from the site, Dossier, PlaceFinder, openrouteservice, the second tier's page reads, landmark photos).
  **It never stops business operations:** orders, billing, payments, email, releases, staging, Moments, members' and
  businesses' own actions, a staff member's press for one thing (Write a draft, Check my facts:
  `Automation::PERSON_FEATURES`), guides' weather and roads, the admin.
- **Workflows W1–W13** are each a row of `crawl_workflows`, the website's own included; pausing one stops its crawler
  tasks, its site jobs (a job of several workflows skips only when all are paused), its AI features and its on-demand
  paths. W5 paused: a visitor's ask is kept (queued, most asked first when it's back), with Notify me and a link to what we
  list near the place; never a dead end.
- **Tasks:** `crawl_capture_tasks.enabled`, with who and why (`off_why`, `off_by`, `off_at`).
- **Services:** a person pauses an outside service; it's a status in `ServiceHealth` (`allowed()`), its hosts go to the
  crawler as holds (the task reports "held"), AI routes to it close (`providerFor`, and before each Claude request), and
  its Outside services card says so. Services that business operations depend on (`Automation::NOT_PAUSABLE`: billing,
  payments, email and files, sign-in, the site itself, guides' weather and roads…) aren't paused from here, greyed with
  the reason.
- **Each switch shows its reason, who set it and when;** pausing asks why; every change is logged (`automation_log`). A
  paused thing is "paused", never a failure, and raises no Pulse warning.
- **Shipped OFF:** the migration seeds the master paused, "Paused by Jason, 2026-11-14, until the crawler review", so
  everything stays off until he switches it on. Before the switches' database update has run, the master reads as off.
- **What it doesn't do yet:** Railway's cron itself is set on Railway (removed 2026-11-14, `handoffs/crawler-revamp.md`);
  the switch makes any run that starts end at once. The register (§1) will hold the switches' state with each service's
  caps and status.

## Consequences

- One place answers "what is each service costing, how close to its cap, is it working, and why not".
- Railway's limit settings and `.env`'s caps stop being the source; changing a limit is one setting in the admin.
- The session manager's hourly check shrinks to sessions.
- Providers without a usage feed (and Claude, without an admin key) are shown as "our count only".
- Everything that gathers or creates content can be paused in one place, by workflow, task or service, and was shipped
  paused (§9).
