# ADR 0163: Operations: one view of outside services, workflows and spend

**Note (2026-11-19, decisions/0173):** §1's register becomes the one home for every outside service, with its stages (Proposed to Removed, 0173 §2) and each service defined once (`service-defs.php`, §4). A `.env` key is entered on its card, not in cPanel (§3); Railway's keys stay on Railway, with their link.

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
- **Jason can modify the workflows** (Jason, 2026-11-14: "confirm that the build regarding the knowledge engine will
  allow me to modify the workflows if I choose to"). Every workflow's settings are edited in the admin, never only in
  code: its switch; what starts it (schedule, triggers); its tasks, their order and share; the sources and services each
  task may use (routes); its budgets and paces; its gates' thresholds and the score's weights; its retry ladder; and
  which steps need a person. Each save is a version (who, when, what changed) with "go back to it", as content
  templates already are (0164 R16), and passes the switch-on check before it runs. A new kind of step or a rule in the
  rule book is a release; a new workflow made from existing steps is a setting. Built with the board (stage 4).

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
1b. **The data cleanup tool** (Jason, 2026-11-14: "in this build we also need a tool clean up all the bad data recently
   added"). Built first, since the bad data is live now and the tool works while the automation is paused. A section in
   the admin (Insights → Data cleanup) that brings what the old crawler made into line with 0164's rules, from
   `handoffs/crawler-revamp.md` Phase 4's assimilation passes:
   - **Find:** every listing, staged find, place-store fact and landmark the crawler made, newest first, checked against
     today's rules: the "real business" floor and the junk-name check ("Contact Us:", "PO Box 190", webinar and form
     titles), listings made from a page read alone, values copied from OpenStreetMap only, "Closed" set by one site,
     duplicates, protected names shown too openly (story-only), nation-sensitive names.
   - **Preview before acting:** counts by reason, with examples, so Jason sees what would change.
   - **Act, reversibly:** never delete. A record that fails is **quarantined** (off the public site, kept, with its
     reason), turned into a sighting or a "Help us find this business" card, re-sourced, marked caution instead of
     Closed, or offered for a merge, by rule.
   - **Never touches** what a person entered, a claimed listing's owner's fields, or anything a person already decided
     (0164 R4); those get a "Check these" step instead.
   - **A ledger and undo:** every change logged (what, why, which rule, when) with Undo per item and per run.
   - **A Pulse case** for what needs a person (one case, a step per kind), and a report when a pass ends: restored,
     re-sourced, quarantined, left.
   It runs by Jason's button (a pass per reason or all), and later on the schedule so the record stays clean.
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
   **And first in the stage, before any load is switched on** (Jason, 2026-11-15; 0164 §3.1, §3.2's note, §6.1): the
   one identity check on every path that creates or un-hides a listing, the check within each batch, the identity keys
   and lock (the unique key once the twins are cleared), the tuned match rules; then the identity recheck (W2 task
   `recheck`), W2's `identity` task as "Vet this place", and the identity queue in "The engine needs a person", with
   Pulse case #23's 60 offers rechecked first and only what's left moved to the queue. The cause and the build notes:
   `handoffs/duplicates.md`.
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

**Note (2026-11-15, releases #49–#67):** built, each shipped off with the master switch off. Stage 1b, Insights → Data
cleanup (#49 find and preview; #50 passes with a ledger, Undo and one Pulse case; #52 nothing taken off is a dead end;
#53 why records were left; #54 drop-offs as their own kind); its "Help us find this business" cards (#51). Stages 2–6 as
Release A (#55). The "before any switch-on" list and stages 7–11 as Release B, parts 1–7 (#58, #60, #63, #64, #66 with
part 6, #67; #65 was withdrawn for a migration that wasn't add-only). Not built: P1-4 (grounded search, unfunded), W14's
own finding of sources (H2, H3; Arizona's and New Mexico's staged by hand), P3-5 and P3-2's leftovers
(`handoffs/release-b.md`).

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
     **Note (Jason, 2026-11-17, after Start held on 20 hub lines; decisions/0170 §2):** the hub lines hold **W14 only**
     (authority sources, the one reader of `authority_sources`), never the master switch or W0–W13. A hub's stage is about
     our team's support (staff, sales, the opening budget), never about gathering or showing data: "we don't restrict data
     to fill where hubs exist, we just don't offer support in those hubs yet." Unread terms or an untested reader keep W14
     off and nothing else. A hub's sources are those of its own states (its micro-clusters' zips); a hub with no stage set
     takes it from `hubs.status`, and one with no state recorded has none (release hub-gate-preflight-2).
   Anything red keeps that part paused with the reason; the rest can start.
   **Built in Release A (release #55, 2026-11-15)**: `Operations::switchOnCheck()`, on the Workflow board and the
   Automation page. **Jason, 2026-11-15: the master switch stays off until Release B is built**; the check refuses
   Switch automation on while any line is red (a workflow's or a service's switch: while a line for it is red), and B's
   lines are red until each is built (`handoffs/release-b.md`).
2. **Resume in steps, not all at once:** one workflow at a time (W1 first), each new or returning service at half
   budget for its first 14 days (the ramp already live), with the "returns empty" and 50/75/95% warnings watching.
   **Note (Jason, 2026-11-17, after Start took hours over one workflow's gate):** "the start needs to confirm it's
   operational within minutes similar to the preflight. Once its operational the queue for firing each is clean and
   efficient, no need to rush. I just dont want the system acting incorrectly because something wont become active until
   step 999 or 6 hours later." So Start the system confirms every workflow and task first (the pre-flight's test of each,
   all of them, within minutes), then switches the confirmed ones on in order without waiting on runs (a task once its
   workflow is on), and watches each one by itself afterwards (`start_gate_runs`, `start_gate_hours`): one that misbehaves
   is stopped alone, with its fixes on the card. The half-budget ramp for 14 days stays. This replaces "one workflow at a
   time" (release start-confirm).
3. **New services are added switched off** (Gemini, Overture, Foursquare's open data, each licence source) with their
   cards complete, so switching them on is the only step left.
4. **The one step for Jason** at resume: any key the site needs in `.env` (File Manager), named by the check.

**Note (2026-11-15): Start the system replaces "Jason switches each on".** Jason: "It seems like you expect me to flip
each switch. Thats more work for me again. We need a ondemand system start which will fire each of the steps you expect
me to flip. And I will address issues that the system can't fix itself." So the switch-on is one button, **Start the
system** (System Configuration → Automation, and on the Workflow board), with **Pause everything** beside it
(`app/lib/SystemStart.php`, carried on by `system_start_tick` every 10 minutes, so the page can close; never on
staging). The order, the gates' rule (one at a time, W1 first) and the budgets (half for 14 days, §10.2; `Clocks::ramp`)
stay as above. What changes:
- **It fires each step itself:** the keys; watching (when its keys are there); the switch-on check, after fixing what it
  safely can (a waiting database update run, a failed one tried once more, the register's caps recorded, held
  services' probes brought forward, the junk count read fresh); Railway's schedule set again through its API with
  `RAILWAY_API_TOKEN` (`*/5 * * * *`, restarts Never, read back); the master on; then W1 to W12 in number order; then
  the new tasks (the identity recheck, Overture, Foursquare, OpenStreetMap from Geofabrik, the vector index with
  Supabase and Gemini's embeddings, W14). Gemini's other features and the licence sources aren't switched by it: they
  wait for the sample comparison (§11) and their hubs (0164 §7.1).
- **A red line holds only what it's for:** a line for everything holds the master; a workflow's or a service's line
  (a missing key included) holds that workflow and the tasks under it, and the rest goes ahead. The Railway schedule's
  line holds nothing (the site's parts run without it). Switch automation on by hand still waits for every line.
- **Automatic gates** (§12's settings `start_gate_runs`, 2, and `start_gate_hours`, 6, in the Thresholds register):
  the next starts once the one before has had 2 good runs or 6 hours, with no new service issue for its services,
  none of them out of budget, and failed runs no more than one in ten. A gate that fails pauses that one, stops the
  sequence and holds what's left.
- **One Pulse case, "Starting the system"**, with a step for each thing only Jason can fix (each key and where it goes,
  each red line with its fix, Railway's exact setting when there's no key, a failed gate's Try again or Leave it). When
  he fixes one, the next tick carries on. A workflow a person pauses meanwhile stays paused; pausing the master stops
  the start.

**Note (2026-11-18, releases #69, #96, #110, #112–#119): Start the system as built** (#69). The switch-on check is a
pre-flight group run on the server, and Start's card is a queue of steps with its own run log and reports (#112, #113,
#116). Every fix is on the card, never another page (#113, #114, #117, #119; Jason: "the corrections needs in page fix
option"; "Fix it can't take me to another page"); Pulse keeps only a notice. A service issue fails a gate only when it
holds the service (#114). It confirms all within minutes, then switches on in order, each watched alone (#115, §10.2's
note), and reads Operational once on (#116). The card works at 390px (#118).

**Note (2026-11-16): the pre-flight check, before Start the system.** Jason, after tracing every key: "what I also
want is the pre-flight checklist and system check page that goes through each item and ensures it is connected and
ready for use before we launch the updated engine." Today the cards say whether a key is *set*, and Start the system
finds what's missing as it goes. The pre-flight proves each one **works**, all on one page, before the press:
- **System Configuration → Pre-flight** (`/admin/config/preflight`), linked from Start the system and from Outside
  services. One list, grouped: the site itself (the four databases, `.env` writable, the scheduler running, database
  updates done, `HEALTH_TOKEN`, staging); the engine's services (Railway's API and both workers' shared keys matching,
  Google Places, Gemini, Tavily, RIDB, Overture, Foursquare through Hugging Face, Geofabrik, Supabase's index locked
  down, Census, OpenRouteService); email (Bluehost system and orders mailboxes, SES keys, identities and production
  access); storage (R2 bucket and its CORS rule); money (QuickBooks connected, Stripe and Square sandbox); sign-in
  (Turnstile's secret, Google, Facebook, Microsoft, Apple); GitHub's tokens and their expiry; cPanel's API.
- **The phases and workflows too** (Jason: "this also includes the phases and workflows too"): below the services,
  0164's phases (1–6) and each workflow (W1–W14) with its tasks, in the order Start the system runs them. Each shows
  ready only when everything under it is: the services it uses (above), its tasks registered with the scheduler, its
  settings and thresholds set, its cap and budget recorded, its gate (§10) able to pass, and a **dry run** of one small
  step where the task has a preview mode (what it would find or change, nothing written). A phase is ready when its
  workflows are. The same rule as the red lines: a not-ready workflow holds only itself and what's under it.
- **Each item is a real test, not a key check,** chosen to cost nothing and touch no one: a read-only call, a sign-in
  without sending, a known test value (Turnstile's test token, a free endpoint); never an email to a real person, a
  charge, or a write to someone's data. Each shows ready, not ready (with the exact reason the service gave), or not
  needed yet; when it last ran; and its fix (a button where the site can fix it, the URL where Jason must).
- **Its own process, apart from Start and Pause** (Jason: "it is a separate process and can be run independant from
  system start/stop in case we need to diagnos things while running"): the pre-flight runs any time, whether the
  system is stopped, starting or running, and never starts, pauses or changes anything itself. While the engine runs
  it's the diagnosis page: its tests stay read-only and free, take their small share of a service's pace (so they
  never crowd a running task), and its dry runs write nothing. Start the system *reads* its latest result; it doesn't
  run it.
- **Required or optional:** each item says whether the engine's launch needs it. Start the system shows the
  pre-flight's result first; with a required item red it says what that holds (as §10's red lines do) and offers to
  start the rest or wait.
- **It looks like a queue log** (Jason: "one line per check with a green check for confirmed and red x for failed.
  Each will be toggled to see status and actions to fix."): one line per check, in the order they run, each with its
  mark (the brand's icons, `tv_icon()`, never emoji: a green ✓ when confirmed, a red ✗ when it failed, a spinner while
  it runs, a grey dash when not needed yet), its name and when it last ran. Lines fill in one after another as a run
  goes, like a log. A line opens with a tap (▾) to show its status in full (what was tested, the service's own answer)
  and its actions to fix (the button or the URL); a red line can be opened straight from a count at the top
  ("41 ✓ · 3 ✗"). Groups (the site, services, phases, workflows) fold the same way, each with its own count.
- **Run all, or one at a time,** with a spinner on each and the results kept. It runs again by itself each night and
  after a `.env` change; an item that turns red becomes a step in Pulse.
- Calls go through `ServiceHealth::allowed()` and `StagingGate::hold()` (0165): on staging only what's safe there runs.
- It takes in 0152's "a Test button per part is a later step": each card's Test is that item's test.
- Queued in `handoffs/session-manager.md` **first**, before the App store, since the launch waits on it.

**Note (2026-11-17, releases #88–#99, #107, #111): the pre-flight is built** as written above, at
https://traversence.com/admin/config/preflight (`app/lib/Preflight.php`; #88). Since then: one row per line, the mark is
the answer (#90); results in their own table, slow checks marked, a text report (#91); tests tuned from Jason's reports
(#92, #96, #97: ".env names each set once"); Run all starts fresh and fills in like a log (#95); every workflow, task,
phase and the services they use are needed for launch (#98; Jason: "they are critical to our system"); a run log with
each run's report (#99); Run all runs on the server and carries on when the page is left, an interrupted run asks
Continue or Cancel (#107, #111).

**Note (2026-11-17): the source map, ours first, a backup for every need** (Jason: "we should have backups for
sources/apis if one fails that way it can still run in the event of failures. All workflows should be like this";
"for the workflows, do we have resources available for these because it seems like they only use outside services";
approved the direction the same day). Every need, the engine's and the site's, is answered in this order:
1. **Ours first** (0164's "what we hold first"): our catalog (`entities`, `landmarks`, `topics`, `heritage_facts`,
   `place_about`, `guide_finds`), our copies of open data (`open_places` from Geofabrik, Overture and Foursquare;
   `npi_orgs`, `irs_orgs`, `rec_places`, `zip_coordinates`, `tv_zip_population`, `nation_shapes`), our crawler's record
   (`crawl_frontier`, `crawl_pages`, `source_facts`, `crawler_raw_index`) and kept answers (`travel_legs`, `weather`,
   `stock_images`, the search index). Outside services fill a gap or refresh what we hold.
2. **Then outside, free before paid, in order**; the first that works wins and each switch to a backup is logged.
3. **A paid backup only by override** (Jason, 2026-11-17: "we can have an override for this if we need a source and no
   free ones are available"): off until a director switches it on for that one need, used only while no free source
   works, inside its own cap on the Service register, logged and shown on the pre-flight. Marked $ below.
4. **If none works, a planned fallback**: the work waits (nothing lost), goes to the person queue, or the page shows
   less. A need with no second source says **"no backup"** on the pre-flight, so the risk is seen.

The engine:

| Workflow | Need | Ours | Then outside | If none works |
|---|---|---|---|---|
| W0 | Read pages | the frontier, pages already read | Railway reads new ones | waits |
| W1 | Places by kind and town | `open_places`, `entities` | Overpass (4 servers) for a gap; the loads refresh | waits |
| W1 | Businesses in bulk | `open_places` | Overture → Foursquare (monthly) | last load kept |
| W1 | Special kinds | `npi_orgs`, `irs_orgs`, `rec_places` | NPI, IRS, Recreation.gov files | last load kept |
| W1 | Official places | `landmarks`, `entities` | Wikidata | waits |
| W1, W2 | Confirm a business | the catalog and local copies agreeing | NPI → AZ Corporation Commission, NM Secretary of State → Tavily → Brave → $ Google Places | person queue |
| W1–W3, W10, W11 | Page prose into facts | code's pre-scan; pages already read | Gemini (free tier) → $ Claude (AI cap) | "waiting to be read" |
| W2 | Re-read websites | our last copy | direct → Common Crawl | waits |
| W2 | Missing phone or website | local copies | Tavily → Brave | waits |
| W2 | Map points | `zip_coordinates`, `open_places` | Census geocoder | waits |
| W2 | Still open? | our copies' status | Google Places (within cap) | waits |
| W3 | Sources | `source_facts`, the frontier | direct → Common Crawl | waits |
| W3 | Semantic search | MySQL keyword search | Supabase with Gemini embeddings | keyword search |
| W4, W5 | Guides, first looks | catalog, research, heritage, topics | Claude (AI cap) | queued; W5's first look from what we hold |
| W6 | Heritage | `heritage_facts`, `topics` | Library of Congress → Wikimedia → National Park Service | waits |
| W7 | Drive times | `travel_legs` | openrouteservice | estimated times |
| W8 | Find it | catalog and local copies | Tavily → Brave, then Claude | waits |
| W9 | Landmarks | `landmarks`, our loads | Wikidata, USGS GNIS, Census TIGER | waits |
| W10–W12 | Events, how-tos, search learning | the search index, what it learned, pages read | Tavily → Brave | queued |
| W14 | Authority sources | what W14 staged | AZ Corporation Commission, NM Secretary of State (licence lists inactive until 3a) | waits |

The site:

| Need | Sources | If none works |
|---|---|---|
| Hosting, cPanel | Bluehost | **no backup**: the site is down |
| The site's own email | Bluehost SMTP → Amazon SES | waits |
| Bulk email, digests | Amazon SES | waits |
| File storage | Cloudflare R2 → Amazon S3 (until the old bucket is emptied) | uploads wait |
| Captcha | Cloudflare Turnstile | sign-in works, tighter rate limits |
| Our orders' payments | QuickBooks | **no backup**: wire or check |
| Marketplace checkout | Stripe or Square (the business's choice) | **no backup**: contact the seller |
| Selling domains | Name.com | sales pause |
| Own domains | Fly.io edge | the trvgo.us address still works |
| Sign-in with Google, Microsoft, Apple, Meta | each provider | email and password |
| The engine's host | Railway | **no backup**: the engine waits |
| Sync, checks, docs | GitHub | **no backup**: the sync waits, the site runs |
| Staff logins | 1Password | the admin's Vault |
| Maps | OpenStreetMap tiles → USGS National Map tiles; Leaflet via cdnjs | the list without the map |
| Weather, road work | National Weather Service; AZ 511 | hidden, with a link |
| Populations, tribal boundaries | our Census loads | last load kept |
| EU sign-up check | our DB-IP file | last file kept |
| Stock photos | `stock_images` → Pixabay → Library of Congress → Wikimedia Commons | placeholder |
| Videos | YouTube, Vimeo | a link out |
| Older pages' styles | Tailwind's CDN | unstyled |
| Recalls | CPSC | check skipped, logged |
| Menus, forms, cards | Google Document AI → Amazon Textract → Claude | waits |
| AI Assist | Claude (AI cap) | "try again later" |

**Planned backups for the "no backup" needs** (Jason, 2026-11-17; a future build, queued in `handoffs/session-manager.md`;
until each is built the pre-flight says "no backup yet, planned: …"):

| Need | Now | Planned backup | Cost |
|---|---|---|---|
| Hosting | Bluehost | a read-only copy of the public pages (guides, listings, places), refreshed nightly, served from Cloudflare or the Fly edge when Bluehost stops answering (visitors read; sign-in and posting wait); nightly database backups to R2 or Supabase (Supabase can't run the PHP site itself) | small |
| The engine's host | Railway | Bluehost's jobs for the light tasks (as today); the same crawler image on the `traversence` Fly organization for the heavy ones | only when used |
| The sync | GitHub Actions | Railway runs `sync.py` on its schedule; recorded in git once GitHub is back (OneDrive is already one of the three copies) | only when used |
| Our payments | QuickBooks | Stripe as a second processor (our account, in test mode now), then wire or check; no internal card processing (PCI) | fees only when used |
| Marketplace checkout | the business's Stripe or Square | the other, when the business has connected both; else pay at pickup, contact the seller | none |

**The pre-flight judges by need** (to build): a line per need, ✓ when its first source works, amber when it runs on a
backup, ✗ only when nothing works; our copies checked too (loaded, with rows, fresh), so an empty or stale copy is red
like a failed service. Then each workflow uses this order: W1 first (`open_places` before Overpass), then W2's fallbacks,
then the rest.

**Note (2026-11-17, releases #100, #101, #103, #104, #106):** built. The pre-flight judges by need, ours first, with
"No backup yet" and the planned backup shown (#100). W1 reads our OpenStreetMap copy (`open_places`, from Geofabrik)
before Overpass (#101). The two paid backups by override, each off until a director sets its monthly cap in Thresholds
& gates: Google Places confirms a business, Claude reads pages when Gemini can't; web search falls from Tavily to Brave
(#103). Each need's backups in the map's order (#104), and a hold on one server holds only that server, unless nothing
else answers (#106). Not built: stock photos' order (0143 interleaves on purpose), menus, W6's Wikimedia and National
Park Service steps, the AZ Corporation Commission and NM Secretary of State readers, W14 (0164 §7.1's note).

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

**Start the system's gates** (§10's note, 2026-11-15): `start_gate_runs` 2 good runs, or `start_gate_hours` 6 hours
clean, before the next workflow or task starts; settings in the Thresholds register, kept with who and when.

**The identity check and the recheck** (0164 §3.1, §3.2's note, §6.1; Jason, 2026-11-15: "the knowledge engine is the
master of this process"). Settings, tuned by a director (kept with who and when, and a Reset):

| Setting | To start |
|---|---|
| Merges by itself (exact twins, unclaimed, nothing a person's) | `same` at ≥ 0.95 (0164 §3.2's routing) with the same phone key, name key and ZIP, or the same address key |
| To "Vet this place" | `same` or `covered` with any detail differing across the two, or `maybe` (0.60–0.95) |
| A phone shared by more listings than this is a main line (never proof alone) | 4 |
| "Another location" needs points at least this far apart (or a different phone) | 150 m (`geohash_7`) |
| The identity lock's wait | 5 s, then the write retries once |
| The recheck after a load | the ids the load touched, at once |
| The recheck's daily slice | 2,000 listings, oldest checked first, in a quiet hour |
| Pairs merged in one run, at most | 200 (the rest next run); more than 5% of a town's listings in one run waits for a person's OK, as the sync's mass-delete guard does |
| "Vet this place" retries | the retry ladder (above) |
| Its costs | the recheck itself is free (our database); what it sends on is counted to `identity` and `places` on the board (§3), inside their allowances (§1) |

The recheck is a task of W2 on the switches (§9), shipped off; its runs, merges, sends and leftovers are on the board.

## Consequences

- One place answers "what is each service costing, how close to its cap, is it working, and why not".
- Railway's limit settings and `.env`'s caps stop being the source; changing a limit is one setting in the admin.
- The session manager's hourly check shrinks to sessions.
- Providers without a usage feed (and Claude, without an admin key) are shown as "our count only".
- Everything that gathers or creates content can be paused in one place, by workflow, task or service, and was shipped
  paused (§9).

**Note (Jason, 2026-11-20, decisions/0175): §9's task switches move to their workflow's own page on the board.** A task is a step of its workflow; Automation keeps the master switch and the workflows, with one line per workflow linking to its tasks. A fault never writes into a switch: it shows as Paused by the system.

**Note (2026-11-20, decisions/0164's note on Common Crawl's URL Index): the crawler's `CC_MAP_PER_RUN` (40).** The number
of the crawl's URL Index files whose key ranges the worker reads in one run while a new crawl's file map is built. It's
derived from today's numbers, not a new rule: the Common Crawl task's `CC_TIME` (60 seconds a run) at the worker's pace of
one request a second leaves room for about 40. A Railway variable like `CC_TIME`, `CC_PER_SITE` (50), `CC_PAGES` (40) and
`CC_COPY_MAX` (12). The map is whole after about 8 runs on a new crawl's 300 files, and is then read from the site.
