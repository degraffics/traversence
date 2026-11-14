# ADR 0163: Operations: one view of outside services, workflows and spend

**Note (2026-11-14, decisions/0164):** Works under `decisions/0164` §10 (the Knowledge Engine: rule book, phases, workflows and sources), which took in 0133's rule book. This record stays separate: this record is the operations layer under 0164. **One home per fact** (Jason, 2026-11-14): the numbers and
operating settings (caps, budgets, paces, warning levels, the AI cap, Anthropic's and Gemini's settings, the switches, the
switch-on check, the collaborative score's weights, a hub's opening budget, AI Assist's allowances, the AI cap's shares,
the retry ladder, the lead and Google check order, the signals against) live only here; the rules about data, sources,
truth, phases, workflows, establishing listings, privacy and protected places live only in 0164, which points here for
a number. **The data gates stay in 0164's R10** (the guide score, the entity match, the public's Closed count): they're
rules about the data, so both records say "the numbers are in 0163, and the data gates in R10" (gap review F2).
**Amended 2026-11-14** by Jason's decisions on the gaps review and the rural test's Run 2 (0164 §15): §7, §8, §10,
§11 and §12 below.

**Status:** Accepted (Jason, 2026-11-14: "all approved"; written that day by the session manager from his answers in
chat). **No shadow mode** (Jason, 2026-11-14): each part is built switched off and goes live when it's switched on, after
the switch-on check (§10); nothing runs alongside "to compare" before that. It builds on
`decisions/0160` (the crawler as one service, the workflows), `0164` (the Knowledge Engine's rules, six phases and
workflows; it took in 0133's), `0152` (Outside services, one card
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
- **known prices** (2026-11-14): Google Places about 3¢ a call; the free registries, open datasets and Gemini's free
  tier nothing (§11); Supabase's free tier nothing (the vector index, 0164 §5.2; its upkeep task sits under its card);
- its status, always with the reason: **active**, **paced** (to what), **held** (why: ban, key, quota, backing off),
  **off** (no key, switched off), **out of budget** (until when);
- what it produced (finds, listings, facts, pages) and the cost per result.

Every path goes through it: the website's own calls (OpenStreetMap, Claude, openrouteservice, deep look) pass the same
check and budgets as the crawler's.

### 2. Costs together

The crawler's ledger, Claude's (`ai_jobs`) and email's, per service, per workflow and per month, in one place; set against
revenue and the community budget (3% of revenue, $25 floor, 0154), so "no income, no expense" is checked by the system.

- **A hub's opening budget** (0164 R17; Jason, 2026-11-14, §14 point 8): a one-time allowance per hub, separate from
  upkeep, set here and shown as a line in the register for the services it draws on and on the hub's readiness meter;
  spent highest value first. Its amount is set when the hub is Planned; none is set yet. The hub's readiness targets stay
  gates in 0164 R10.

### 3. The workflow board and the task schedule

- **W1–W14**, the website's own included (W14 from when it's built, 0164 §7.1): queue, last run, outcome, output, cost,
  and the phase (0164 §1–§6) where work waits.
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
- The site's AI cap (`AI_MONTHLY_CAP_USD`): **$40** a month, moving into the register. On-demand guide builds (W5) use up
  to half of the daily allowance, the rest queued (0131, 0147).
- **AI Assist for members** (moved here from 0164 R10, Jason 2026-11-14, §14 point 10): free asks a day on paid plans
  **3**; a member's AI cost in a month at most **0.5** of what their plans pay. Tuned by a director in AI use (kept with
  who and when, and a Reset); the member sees them as their allowance (0164 R15).
- **Each AI feature** (Claude's and Gemini's alike, §11) has its switch, its model and provider, its runs a day and its
  budget, all on AI use (`AiClient::ROUTES`), and is counted per call, failed calls included.
- **The AI cap's shares** (gap review X4): the monthly cap is split into a share per workflow (W4's renewals, W5, W6's
  readings, W8's Find it, Phase 2's reads of a person's pages, AI Assist's follow-up research), set here like the
  crawler's shares in `CrawlService::plan`; a share unused at month's end passes on to the next month's pool. The
  50/75/95% warnings (§4) apply per share, so a busy workflow can't silently starve the others. The share sizes are
  set on AI use when stage 3 is built; until then W5's half of the daily allowance is the only share.
- **AI Assist's allowances have one home** (X5): `ai_free_asks` and `ai_cover` in `Thresholds` read from AI use until
  stage 2 moves them into the register.
- Anthropic's monthly spend limit: **$50**; Console email alerts at **$20 and $35**.
- No Admin API key: the Console is an individual account, which can't create one (future-considerations: "The Claude
  Console as an organization"). Claude's row shows "our count only" until then.

### 8. Build order

Each stage a release Jason approves; one session per stage, from its handoff.

1. **Already live (2026-11-14):** the service check, holds and automatic return (half budget for 14 days), server lists
   as settings, pacing per service (releases #43, #44, #46; worker 2.6). ✎ The live daily probe of a **banned** service
   (e.g. overpass-api.de's `/api/status`) stays **daily for at most 3 days**; still blocked after 3 days, a person is
   notified to evaluate (Reactivate, keep checking daily or weekly, or stop), and the checks pause until they decide.
   Its automatic return is **removed before any switch-on**: a passing check raises "looks lifted" with Reactivate for
   a person (Jason, 2026-11-14; 0164 §6.1).
2. **The service register:** one row per service; caps and paces moved into it from Railway and `.env` with today's
   values; status with reasons; the website's other outside callers onto `ServiceHealth` (handoffs/crawler-revamp.md
   §7.1 item 3); AI Assist's allowances moved in (X5).
3. **Costs together,** against revenue and the community budget; the AI cap's shares per workflow (§7, X4).
4. **The workflow board and the task schedule.**
5. **Warnings (50/75/95%) and "returns empty".**
6. **Watching built into the site** (Railway, the sync).

**Before any switch-on** (the gaps review, 0164 §15; each in the stage that touches it, at the latest stage 8, and
checked by the switch-on check, §10): clocks that count only switched-on time (X1); W1 and W8 gating on 0164's floor and
readiness (X2); the engine's one Pulse case, "The engine needs a person" (X3); a site that blocks us held with a Pulse
step and **Reactivate**, never retried by itself (P1-2), and the same for a banned outside service: a daily status check for 3 days,
then a person evaluates; no automatic return (0164 §6.1); landmarks' story-only level, with `Landmarks::publiclyKnown()`
moving a held place only to story only and `GuideLinks::find()` no further (P5-3; this one is live behaviour today, so it
goes first).

Then the Knowledge Engine's new parts (0164; Jason, 2026-11-14, §14 point 14: one build order, here):

7. **The cards first, all off:** Gemini, Overture, Foursquare's open data, NPI's town load, each licence source (from
   W14's staging, or by hand for Arizona and New Mexico), Supabase: each a row in the register (§1) with key, terms,
   cap, pace and status "off" (§10, 3). **And the Gemini privacy guards, in the Gemini client, before any feature can be
   routed to Gemini** (0164 §0.8): R6's private details removed from a page before it's sent (P2-1); what's sent built
   from our own vocabulary, never a person's words (P2-2); each feature's input class in `AiClient::ROUTES`, with
   `providerFor()` refusing Gemini for anything but public and AI use greying the choice out with the reason (P2-3);
   protected, confidential and nation-sensitive names never sent (T1). Until W6's sources pass the removal, W6 and every
   archive, newspaper and registry feature stay on Claude.
8. **The data loads for Arizona and New Mexico** (Overture, NPI by town with its two guards, Foursquare's open data
   once a Hugging Face account accepts its terms, the licence sources that offer bulk data), with the "real business"
   floor and confidence (0164 §3.5), the collaborative score's weights and the lead and Google check order (§12). No
   shadow mode: what passes the floor goes public at its confidence once the load is switched on. **Its goals start from
   our coverage gap** (Run 2, 2026-11-14): we list **0** businesses in Kayenta (Overture has 33, NPI 11, under 0066's
   rules), **13 of 45** of Overture's in St. Johns and **29 of 94** in Springerville/Eagar. **With it, from the gaps
   review:** one rule for "complete" in `AutoImport::evaluate()` (the floor plus a point, the town's middle marked
   approximate; P3-1) and `listing_one_source` and `guide_find_add` retired as gates (X2); NAP fields each showing how
   they're known, the Facebook citation entry (a person entering a NAP item with the page's URL) and Overture's Meta
   record as a NAP source (0164 R14); sightings as gaps, "Confirm from its page" and the town page's ask (P3-2); the
   retry ladder (§12; P3-3, P4-1); a claim keeping its band and true source labels (P3-4); the owner's "Check these" and
   its email (P3-5); licence changes re-sorting stored facts (P4-2); our team as a closer (P5-1); "Still open?" with W2
   (P5-2) and the public's Closed count (F2); the three card states (F3); the signals against (§12, F1); zero-result
   searches as demand (P1-3); a lead's states (P1-4); "Needs a reader fix" (P6-1).
   OpenStreetMap for stage 8 comes from **Geofabrik's state extracts** (Arizona, New Mexico; weekly), downloaded by the
   **Railway worker**, not Overpass (Overpass only for one place on demand). Geofabrik resets connections from Claude's
   cloud network (2026-11-14: TLS reset by peer; not our proxy, not a ban on us), so the stage's first step is one test
   download from Railway; if Railway is refused too, another free OSM mirror, never repeated tries.
9. **Gemini for extraction** (0164 §2.3), after the sample comparison (§11), with "waiting to be read"
   (`PENDING_EXTRACT`, P1-1) and Gemini's warnings on the unexpected (§11, F4). **Grounded search is not built** unless
   Jason funds it (§11): the free tier has none (Run 2).
10. **W14 running** (0164 §7.1): staging every state's authority sources, switched off until each hub, each with its
   real use (bulk data, lookup only, blocked, claim-time check; Arizona's as found in Run 2, with
   `arizonabusinesscenter.azcc.gov` allowed and its terms read). **With it** (H1–H5): Planned on the hub meter starts W14;
   vendor pages linked from a government page kept; a newly staged source for an open hub switches on from its card; the
   "Hubs" Pulse case; the opening budget line in the switch-on check.
11. **Vectors** (0164 §5.2) on Supabase's free tier.

Each stage is a release Jason approves. The crawler revamp's own open items (the four approved releases W6, W9, W10/W11,
W12; junk listing names; the map-tile policy check) go first or alongside, from `handoffs/crawler-revamp.md` §7.1, in one
crawler session, so nothing is built twice.

### 9. The switches (Jason, 2026-11-14; built as release #48, automation-switches_20261114, live 2026-11-14 17:57 UTC)

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
- **Workflows W1–W13** are each a row of `crawl_workflows`, the website's own included (W14 gets its row, paused, when
  it's built); pausing one stops its crawler
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

### 10. Switching back on without breaking (Jason, 2026-11-14: "make sure everything gets set up so when I flip the switch back on it doesn't break")

Every stage of the build leaves the automation **ready to resume**, and the master switch checks before it lets
anything run:
1. **The switch-on check on the Automation page** ("Check before switching on"; renamed from "the readiness check",
   Jason 2026-11-14, so it isn't confused with a listing's readiness or a hub's readiness meter), run when the master
   switch (or a workflow's, a service's or a load's) is turned on, when a hub's sources are switched on (0164 R17), and
   on demand. Each line is green or names exactly what's missing, with its fix as a button
   or link:
   - every service the workflow uses: its key present (`.env`, e.g. `GEMINI_API_KEY`; Railway's settings), reachable,
     not held, its terms checked, its budget and pace set;
   - the Railway crawler's schedule back on (its cron was removed 2026-11-14) and its latest deploy healthy;
   - no database update waiting or failed;
   - the crawler's open fixes that must come first live (the junk listing names; the four approved releases W6, W9,
     W10/W11, W12), so resuming doesn't restart old faults;
   - OpenStreetMap: at least one server not held (else its tasks resume "held", not failing).
   - **W1 and W8 gate on 0164's floor and readiness** (§3.5, §5.4), not 0133's (gap review X2);
   - **the clocks:** each workflow's backlog from its paused time and its order (most viewed or asked first), drained at
     the ramp's half budget (X1);
   - **the engine's Pulse case** is built, so nothing lands in Review without a prompt (X3); held-blocked sites listed
     with their Reactivate steps (P1-2);
   - **opening a geo-hub:** the hub's readiness meter line for its authority sources (staged by W14, 0164 §7.1): their
     terms still valid and readers passing, before they switch on with the hub; **"opening budget not set"** named with a
     button to set it (H5). For a hub already open (Arizona, New Mexico), the same line runs when a newly staged source's
     card is switched on (H3).
   Anything red keeps that part paused with the reason; the rest can start.
2. **Resume in steps, not all at once:** one workflow at a time (W1 first), each new or returning service at half
   budget for its first 14 days (the ramp already live), with the "returns empty" and 50/75/95% warnings watching.
3. **New services are added switched off** (Gemini, Overture, Foursquare's open data, each licence source) with their
   cards complete, so switching them on is the only step left.
4. **The one step for Jason** at resume: any key the site needs in `.env` (File Manager), named by the check.

### 11. Gemini (Google AI Studio) as run (Jason's Decision 7, 2026-11-14; the rules on what may go to it: 0164 §0.8, R5)

**Gemini follows Claude's and the paid APIs' standards** (Jason, 2026-11-14, 0164 §14 point 15): "we monitor it for usage
and ensure we dont cause it to become unexpectedly unusable."
- **Free tier only, no billing linked;** a row in the register (§1), added switched off (§10, 3).
- **Run like every AI feature (§7):** each feature using it has its switch, its model and provider, its runs a day and its
  budget on AI use, counted per call, failed calls included.
- **Changing a feature's provider** (Claude to Gemini or back) is a director's call on AI use, made after the sample
  comparison (0164 §2.3: a sample read by both, side by side); kept with who and when.
- **Its allowance:** about 15 requests a minute and 1,000 a day (Flash or Flash-Lite). **Monitored and paced through the
  day** (the day's allowance spread over the hours, not spent at the start), with **about 20% of each day held back for
  on-demand work** (a visitor waiting: W5, Find it, a search); background work stops at 80%.
- **What it's used for** (Run 2, Jason 2026-11-14; 0164 §0.8): **reading public pages only** (extraction). It is
  never a finder; its weight in the collaborative score is 0 (§12). Each feature's input class is declared in
  `AiClient::ROUTES` and Gemini is refused for anything but public (P2-3), greyed with the reason on AI use.
- **Warnings on what's unexpected, not on use** (gap review F4; the allowance resets daily and is paced to be used, so
  50/75/95% would warn every day with nothing to do): one Pulse case, updated, as §4, when background work reached its
  80% stop before 18:00 UTC three days running (more work than allowance: slow a feature, or move one back to Claude
  inside the AI cap, as buttons); when the 20% on-demand reserve fell below 5% on any day (a visitor may wait); and a
  refusal or ban, "held", as today. 50/75/95% stay for monthly caps.
- **When the day's allowance is used:** the service check holds Gemini until the next day and the work **waits**; no
  fallback to Claude (0164 §12, C11). A refused key or a ban is "held", with its case (§1).
- **Grounded search: none on the free tier** (Run 2, 2026-11-14): `google_search` answered 429 "quota exceeded" on
  every current Flash model, and `gemini-2.5-flash`, which had a free grounding allowance, is closed to new users.
  Ungrounded "from what you know" answers were unreliable (55% of the businesses named confirmed, about 15% of phones
  right, numbers made up), so they are never used. **Grounded search runs only if Jason funds it**: a paid feature
  under "no income, no expense", with its own card, cap and budget here; until then W1, W8, W12 and W14 don't use it.

### 12. The collaborative score's weights (0164 §3.5, R12; Jason, 2026-11-14)

The collaborative source score **feeds a listing's confidence; it is not a gate and has no threshold** (0164 §3.5). Its
weights are settings here, tuned by a director (kept with who and when, and a Reset), starting from the rural test's
proposal (`research/rural-test-2026-11-14.md` §7) and **retuned by its Run 2** (§8.9 there; Jason, 2026-11-14). Each
counts once per origin (independent sources only, 0164 R11), for a source agreeing on the name plus the phone or the
address:

| Source | Weight to start |
|---|---|
| Google Places confirms it open (place ID kept), **agreeing on the phone** | 40 |
| Google Places confirms it open, agreeing on the address only | 30 |
| Chamber of commerce or tourism office listing | 25 |
| Business-managed listing (Uberall, DAC) or filings-built (BrightQuery) | 25 |
| An open-dataset record from another origin (Microsoft, Foursquare) | 20 |
| Its Facebook page (or Overture's Meta record, once) | 20 |
| A directory page our crawler read (at most two count; found by grounded search only if that is funded) | 15 |
| OpenStreetMap | 15 |
| A news story naming it in that town (name and town only) | 10 |
| Gemini's own word, grounded or not | 0 (never a finder; a page it cites scores by its own class) |
| Our own older listings (bulk imports) | 0 (a lead source only) |
| An NPI organisation record | establishes alone (0164 §12, C3), with its two guards |
| A record under 0.5 Overture confidence | half its weight |
The sum adds to the listing's confidence under 0164 R12 (machine evidence alone tops out at 75). The rural test's
"threshold 60" is not used: nothing waits on a number; read 60 as the band where machine evidence counts as strong
(Facebook 20 + Google on the phone 40).

**Signals against (0164 R12)** (gap review F1: moved out of the score's table, since the score is sources agreeing).
Each counts once per origin (a monthly Overture reload doesn't count "dropped" again), ages like other evidence, and
clears when its source reverses (a licence renewed, the record back in the dataset) or the owner or the public says
still open. A claimed listing stays in its band (0164 R12).

| Signal | Weight to start |
|---|---|
| A closure signal (a lapsed licence, a dataset dropping it, Overture "permanently closed", Google closed, one site saying closed) | −40 |
| A dissolved or revoked licence | −60 |

**The lead order and Google's check** (Run 2; F5): free first, then Google where it lifts a find most.
1. Overture as the base layer; NPI by town for health care (free, no key).
2. **Google Places' check, first for finds without a website that have a phone** (Facebook-only, ours-only: about 100
   calls, ~$3, for the three test towns, inside Google's paced budget, §1).
3. Then **any find from a collaborative score of 35 before Google** (F5), with two exceptions inside the paced budget: a
   listing a visitor opened or searched for this month, and a Review item Google would settle (the grey zone).
Order only (0164 R10's spirit), director-tuned like the weights.

**The retry ladder** (Jason, 2026-11-14: "waiting for review is purgatory, so we keep trying"; 0164 §6.1). For whatever
the engine retries for itself (the grey zone, gaps, sightings, out-of-options finds); never for a site that blocks us
(that waits for a person's Reactivate, 0164 §1.2):

| Fails in a row | Next try |
|---|---|
| 1–2 | its normal cadence |
| after 3 | 7 days |
| after 6 | 30 days |
| after 10 | 180 days, then every 180 days, for good |
| any new evidence | back to the start |

Each step is a setting, tuned by a director (kept with who and when, and a Reset).

## Consequences

- One place answers "what is each service costing, how close to its cap, is it working, and why not".
- Railway's limit settings and `.env`'s caps stop being the source; changing a limit is one setting in the admin.
- The session manager's hourly check shrinks to sessions.
- Providers without a usage feed (and Claude, without an admin key) are shown as "our count only".
- Everything that gathers or creates content can be paused in one place, by workflow, task or service, and was shipped
  paused (§9).
