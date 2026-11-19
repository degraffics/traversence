# Hand-off: the session manager

For the Claude session that manages Jason's other sessions (CLAUDE.md, "The session manager"). Written 2026-11-14 by the
first one, moved to a fresh session at 600K context. Jason works mostly from his phone: short messages, one line per
item, decisions as "reply go".

## The job

Keep sessions fast and affordable, and keep Jason told only of what needs him.
- **Watch:** context size (report it; never move a build for it), restarts, cost, sessions waiting on Jason, release clashes, file claims
  (`tools/page-audit/checkout.py list`, decisions/0161), stuck syncs (traverse-3way-sync `sync.yml`), Railway deploys.
- **One session per build, start to finish** (Jason, 2026-11-18): a multi-phase build stays in its session until every
  phase is built and live, then it's closed; never a fresh session per phase. Propose, Jason approves: starting a session
  for a new build or a problem he reports. At most about three working sessions at once.
- **"Done" is checked, not taken on trust** (Jason, 2026-11-18, after Communications was closed with the admin Pulse
  Stream untouched): before archiving, compare what's live against the build's decision and its brief, page by page
  (every surface the decision names, e.g. *every* Pulse), and tell Jason what was checked. A gap reopens the session.
- **Decisions kept current** (Jason, 2026-11-18): after each release goes live, check that the decision it builds
  has a dated note of what was built, any change Jason approved along the way (e.g. 0172 §6's one release), and what's
  left; and that `decisions/index.md` and the README's range are current. Missing: ask the build session to add it in
  its next PR, or add it myself in the delivery PR.
- **Review every question before it reaches Jason** (Jason, 2026-11-18): a session sends me its question or release
  summary first. Answer fast (most take 1–3 minutes); it reminds me at 10 and asks him unreviewed only at 30. Check it against its decision, the brief, CLAUDE.md and the playbook: scope creep,
  drift, a conflict between sections, something he already decided, a hold wider than its cause, unclear words. Answer
  "clear to ask" or the fixes, with the sections quoted. Never approve for him; the OK stays his, in the session.
  Also check it against the playbook's principles (2026-11-19, builder-playbook §2–3): what waits on a person (could
  the system do it?), what stops (only for harm, as narrow as its cause), whether each alarm carries its fix, whether the
  words say what's true now, and the summary's admin's-test line for each new state (what he sees, means, presses). For a release that rebuilds or moves a screen, ask for its "what this replaces" list (playbook §3) and check each old control against the live page before clearing it (2026-11-19: #192 dropped the board's filtering counts and my review missed it).
- **No scope creep, no drift** (Jason, 2026-11-18: "consistency = scope creep or drift"): after each release, check the
  release and the build's `handoffs/<area>.md` against its decision and brief. **Scope creep:** something built or
  promised that the decision and brief don't call for. **Drift:** something the decision calls for changed, shrunk,
  renamed or silently dropped (e.g. 0172's admin Pulse left as it was), or a hand-off whose built/left lists, release
  numbers or sync don't match what's live. Either goes back to the session the same hour; anything that changes the
  decision needs Jason's OK and a note in the decision.
- **Without asking:** archive a session that has said it's done or handed off, work in `main`, nothing waiting on him
  (Jason, 2026-11-14), once the "done" check above passes. Never archive one waiting on him, the session manager itself, or "Community Build" (he keeps it).
  If the permission check refuses an archive, ask him for "archive it".
- **Relay:** when Jason answers here for another session ("merge it", a choice), send it to that session with
  `send_message` in his words. Never approve anything on his behalf.
- **Talking to Jason** (2026-11-18): read his answers literally and act on them; never ask the same question again just to re-confirm. Asking is right when something is unclear, or to offer a counter-suggestion with its reason (Jason, 2026-11-18).
  "Waiting for your OK" lists only what's new and unanswered, in plain words of what he does. **Approval requests are
  shared, approvals are made in the session** (Jason, 2026-11-18): a session's pending OK may be listed here with its
  name, but the session manager directs him to approve it in that session's own chat, never takes it here. Nothing waiting: one line saying so. Check a timing before blaming a
  session (cross-session messages can arrive minutes late). Relay what a session told me only when it needs his decision or I found a
  problem it hasn't; routine progress stays between me and the sessions; a release live and checked is one line.
  When I pass on a session's question or decision, check it against the decision it cites first.

## How the checks run

- **Hourly, while he's checked in:** a scheduled task named "Hourly session check" (cron `14 * * * *`) fires into the
  session manager. "Checking out" disables it; "checking in" (or "check in") enables it. Its prompt lists every check.
- **Notify** (PushNotification) only when something needs him: a session waiting, or a proposal. One line.
- **A website/ file missing from the first sync after a publish** (#137 and #152, 2026-11-18): the publish wrote it after
  the sync had listed the website side. Run the sync once more before calling anything missing; it arrived both times.
- **Never start a sync while a release is pending** (Jason, 2026-11-19): a zip in `releases/inbox/`, or a release Planned
  or On staging without its Live record. #158 went live mid-run, after the run had read the release records, and its 4
  app/ files were put back (kept in the sync repo's `conflicts/2026-11-19/`), so it was re-sent. sync.py now re-reads the
  records before the first put-back (traverse-3way-sync PR #11), but wait for the Live record anyway.
- **Claims check without stray files:** `git status -s` empty, then `git checkout -B <own branch> origin/main`, push it,
  `checkout.py list`. Never leave main's files as uncommitted changes (the stop hook complains).
- **Railway:** a token for `backboard.railway.com` is saved on the "cloud" environment; query
  `https://backboard.railway.com/graphql/v2` (deployments, `deploymentLogs`). Project "gracious-sparkle": services
  `traversence` (the crawler worker, cron every 5 minutes) and `video-worker`. "CRASHED" on a cron service means a run
  exited non-zero; read the logs before anyone presses Restart.
- **Reading another session cheaply:** `get_session` (size, restarts, cost, last summary) and `list_events` with
  `kinds: ["result"]`. Large results land in a file: parse with python, don't load them whole. Reading never wakes it.

## Rules Jason set (all in CLAUDE.md)

- Every build is a release through git; he approves each merge in chat; no zips for him to upload.
- **Sessions merge their own PRs** (Jason, 2026-11-18: "Sessions merge"): a build session asks him in its own chat
  with the pop-up, merges on his Approve and tells the session manager, which runs the sync, verifies the files,
  records the delivery and sends "verified live". The session manager merges only its own PRs (deliveries, queue).
- Both repos: sessions add `traversence` / `traverse-3way-sync` with add_repo without asking. The permission check may
  still refuse a session's own add or `create_session` with pre-approved tools: then he types "add the sync repo" there.
- FAQ clashes resolve themselves; check-out per 0161; "how it works" questions go to the documents, not to him.
- Cost: on Max 5x ($100/month); `cost_usd` on sessions is API-rate equivalent, not a bill. Watch the 5-hour window.
  The archived "Get" session is 903K context (~$2,700 equivalent): read its record, don't wake it.

## Paused (2026-11-14)

- **`decisions/0163` and `decisions/0164` are accepted** (Jason, 2026-11-14: "all approved"; no shadow mode; 0164 §14
  settled). **The crawler and the automation stay paused** until the build stages (0163 §8: operations stages 2–6, then
  7–11 for the Knowledge Engine's new parts) and the switch-on check (0163 §10) are done and Jason switches them on.
  Each stage is a release he approves, one session per stage from its handoff (`handoffs/crawler-revamp.md`).
- **The Railway crawler's cron** was removed through the API (~15:55 UTC); `railway.json` still says every 5 minutes, so
  a worker deploy restarts it (details at the top of `handoffs/crawler-revamp.md`); the automation master switch (release
  #48) makes any run that starts end at once. The hourly check expects no crawler runs meanwhile.

## The working queue now (2026-11-19, 16:15 UTC; the sections below are the history behind it)

**Building:**
- **Control Board (0171)**, session_01CXLi5ivMY6BdkfHK1xdLWS: live through #189. That covers every alarm landing on its
  fix (#183), trips-only to a person, critical parts by duty with Recovered and the Escalation Notice (#183, #185),
  search, sign-in and payments watched (#185), stage tabs, cleared alarms to the logbook, the logbook filter and the
  corrected OSM statement (#189). Asking now: control-board-table (PR #1064), the process table on tv_table() with Q2
  (blocked sites as one notice, a warning above 5% of the day's fetches), Q3 ("Its data" on each process's page, ids and
  field names only) and Q4 (W0 logs off-region addresses as "Outside our region", never fetched). Next: **Pulse's
  load-time warning** (below). The session stays open (Jason, 2026-11-19).
- **Design (`design/css-theme-brand.md`, `design/table-standard.md`)**, session_01CXLi5jkgtUikcdUQ4Wmv28: live through
  #190. That covers buttons (#181), Pulse dates, due clocks, tints and order (#182, #184, #188, #190; clocks only on work
  cases, open trips still escalate), T1 the table component (#186) and the home bar (#187). Asking now: T2, the admin's
  Pulse on the standard on its own engine (▾ and ⋯, two rows on top, paging of the gathered rows). Then T3–T5 (the rest
  by module; capped lists paged in SQL or shown as "400+"), 3e (Marketplace), the map release, the tokens release.
- **Approved, not yet built (design session, in order):** Get Local R2 (the approved concept with within ▾, Arrange and
  the list beside the map; the Filters line and card tools approved 2026-11-19; the PO-box Directions rule; the
  comments box and card tools Jason approved earlier on 2026-11-19 as "release (2)", never built until found from his
  "thought we fixed this too"; the header search after his one-line OK), then card photos (Commons + NPS, approved
  "Both"), then the place page (lightbox link, Add to a trip, counts, the ⋯ toolkit: proposal first).
- **Business listings**, session_01CXLi5kipJLJoRvo3jqLnQQ (opened 2026-11-19 at Jason's word): case #22 live as #191
  (owners edit their hidden listings). It reads the "Edit my listing" path after the 2026-11-20 run, then stays open for
  more listing work.

**Domains: Name.com reseller pricing and .ai** (Name.com support's answer, ticket 7Y5JM6-5L7GX, 2026-11-18):
- No fees or deposit. Enrolling in the API Reseller Program at https://www.name.com/nameapi/pricing unlocks tiered
  prices on **new registrations only** (not renewals or transfers); custom rates only well past the top tier. **Jason's
  step: enroll.** Nothing to change in code: `Domains::priceList()` and getPricing read our account's cost live (12-hour
  cache), and our price is that cost + `DOMAIN_MARKUP_CENTS`.
- Renewals stay at Name.com's renewal price (.com $21.99 today), so a member's renewal costs more than their first year.
  The search already shows the renewal price beside it (Domains.php:375); the cart and FAQ should say so plainly.
- **.ai needs 2 years minimum** (register and renew). `Domains.php` registers and renews with `'years' => 1`
  (:406, :476), and prices "a year": a .ai sale would fail at Name.com or be priced as one year. **Fix before Domain
  sales (`DOMAIN_SALES_MODE`) goes past off:** read each ending's minimum term (the requirements lookup it already
  allows) and register, renew and price .ai for 2 years, shown as "2 years". To a session when Jason chooses.

**Insights: people vs automated, new vs returning** (Jason, 2026-11-19: "how do I know if a visitor is unique or
returning vs bot or crawler?"; on the session manager's proposal, "good, just add it to the work queue"). Today
`PlatformCounts` (0058 §13, "never who") stores no IP, device or id, drops only bots that name themselves
(`isBot()`: bot, crawl, spider, curl…), and counts a visit per server session, so a crawler without cookies makes every
view a visit: Insights → Numbers shows Visits = Views on every page (/directory/ 615 = 615, /claim.php 393 = 393).
- **People vs automated:** a view counts as a person's only when the page's own script runs in a real browser and
  reports back (a small beacon after load); requests that never report are shown apart as "Automated", per page.
- **New vs returning:** a first-party cookie that only says "been here before" (no id, nothing that identifies a
  person or device), so 0058's "never who" holds; Insights shows new and returning visits. A 0058 §13 note.
- Free (no outside service). To a session when Jason chooses (the Control Board session, idle, knows Insights).

**Routes and trip plans on our own site, from OpenStreetMap** (Jason, 2026-11-19, with a screenshot of a guide's
"About 4 mi in all. Open the route in Maps", which sends people to Google Maps directions: "cant we offer a route or trip
plan using OSM and keep people on our own site?"). Decision first; nothing built. What it needs:
- **A routing engine over OSM roads**, choosing by "No income, no expense" and OSM's usage policies:
  - (a) self-hosted OSRM or Valhalla on Railway, from the Geofabrik Arizona and New Mexico extracts we already download.
    It has a monthly server cost, so it needs revenue or Jason's OK.
  - (b) openrouteservice's free tier (a key, daily cap), through StagingGate and counted on the board.
  - (c) FOSSGIS's public routing (routing.openstreetmap.de), light use only under its policy, with the same care as
    Overpass (decision 0065; research/osm-overpass-2026-11-13.md).
- **On our map** (map-view.js, the `--tv-route` lime line already in the design queue): the route drawn, its miles and
  time, the stops in order. A guide or journey's stops become a trip plan to save, share and print. "Open in Google
  Maps" stays as a choice for turn-by-turn on the road (never a dead end).
- OSM's credit on the route (0065); no visitor data kept (0058 §13).
- To a session when Jason chooses the engine.

**Pulse's load-time warning** (Jason, 2026-11-19: "add Pulse's load-time warning", to the Control Board session): time
how long `Pulse::items` takes to gather (its ~15 sources) on each open of the admin's Pulse. Keep the reading in the
board's timings (`ms:pulse`) with its history. Show it as a part on the board. A warning (board only) when the usual time
passes a limit in Thresholds & gates (default 2 seconds, the median of the last 20 opens). Its words: "Pulse is slow to
gather (2.4 s). The fix is the Pulse store: a communications build." Slow is never a trip (4e). This warning is the
signal to build the **Pulse store**, a communications build (0172: the dispatcher writes each item into one Pulse table
as it happens; Pulse reads one page at a time; a writer and a backfill per source, checked against today's view). Not
built until the warning shows or Jason asks (Jason, 2026-11-19, on the session manager's recommendation).

**Dispatch tool** (Jason, 2026-11-19, queued; decision first): one tool that controls (1) alerts, (2) button actions,
(3) form submissions, (4) the message gate and (5) role-based (RBAC) actions. The decision starts from what each already
has: alerts in ControlAlarms and 0172's one dispatcher (`Notify`) with `TvAsk.say`; buttons in `TvAsk.busy`/`gate` and
`Pulse::manage`; forms in `tv-ask.js`'s wrap and `Response::jsonBody`; the message gate in Notify's quiet hours, Stop
these and `StagingGate`; roles in `Access::can` / `Modules::canTool`. It extends them in place (one front door), never a
second copy. "Control" means **both** (Jason, 2026-11-19): rules he sets for each of the five, and a log of every
one that ran (who, what, when, where, the rule that allowed or stopped it).

**Queued builds, in order:** W15 Funnel (0170); state licence lists (3a: decision first); the App store (0169) and its
apps (QuickBooks Bookkeeper, QuickBooks Payments, Stripe Payments, Facebook Personal, Facebook Business, Storefront with
trvgo.us's hyphenated addresses, members' and businesses' outside mailboxes); Release B's small leftovers; backups for
the needs that have none; OpenStreetMap coverage from our Geofabrik copy.

**Communications' open items** (Jason, 2026-11-18: "move still open to the working queue"; the build itself is done,
#130, #135, #138, #141, #142, #147):
1. Texting (0172 step 8): its own decision first (0172 §5; research in `handoffs/communications.md`).
2. Journeys in Pulse's chips (2.10): its own build.
3. The Marketplace feed in Pulse's chips (2.6): its own build.
4. Pulse's new look: the design session's Release 3.
5. A member's or business's own outside mailbox (0172 §4.7): decided in the App store build (0169).
6. Gmail by sign-in: gated until a year's revenue reaches `GMAIL_ASSESSMENT_USD` (`Mailboxes::gmailGate`).
7. Live checks: push on a real device; mailboxes against real servers once Jason adds keys at
   https://traversence.com/admin/config/communications#cs-mailboxes; each new button pressed once on the live site.

**Jason's own:** enroll in Name.com's API Reseller Program (https://www.name.com/nameapi/pricing, the Traversence account; free, new registrations only; see Domains below, 2026-11-19); records requests (Arizona Corporation Commission, New Mexico's Secretary of State); Wikimedia Commons
for heritage (store with credit, or link); approve 0164 §7.1's licence-source strategy; renew `GITHUB_SYNC_TOKEN`
before Dec 16 (https://github.com/settings/tokens); the OpenStreetMap statement: posted 2026-11-19, about 16:10 UTC, waiting for a forum moderator (research/osm-overpass-2026-11-13.md §3); on their answer, Reactivate and Mark resolved.

**Visual fixes** (to the design session's Release 3): the squeezed footer edit zone (Discovery's hero, the Edit switch and stock
bylines are done: #153, #158, #159).

**To purge or remove:** the old S3 bucket, then narrow the AWS key to SES; `.env` leftovers (Jason); the "Default
place" settings row; merged branches on GitHub; the `_moved_npi_orgs` / `_moved_irs_orgs` drop (Jason's OK);
`user/api/notices.php` (nothing calls it: through the sync's delete path, checked first); the unused names once
Jason approves the Control Board's lists.

**Done 2026-11-18:** Supabase vector security (3f); the Control Board's cleanup fixes (3e, 3f); 0167 (#135); 0172
steps 2–7 (#130–#147).

## Where things stand (2026-11-16, 14:30 UTC)

- **Built and live:** decisions/0165 (#56, #57), Release B (#58–#67), the photo fix (#68), and Start the system plus the
  review link's login redirect (#69). **Jason pressed Start the system on 2026-11-17 at 18:44 UTC**, after a
  pre-flight with nothing needed for launch red (18:42 UTC: 114 ready, 11 on a backup, only OpenStreetMap's free
  servers red, optional). The site set the crawler's cron (*/5) itself; from now on the session manager never changes
  Railway's cron or restart policy, only reports failures.
  Railway's build settings (watch paths, root directory, builder) the session manager changes itself and tells Jason
  after (Jason, 2026-11-18: "This is something you can do"); the video worker's watch path is `/workers/video/**`
  since 01:40 UTC, so release commits no longer rebuild it.
- **Keys, traced with Jason by service on 2026-11-16:**
  - GitHub: `GITHUB_SYNC_TOKEN`, read-only on both repos.
  - Railway: limits set, compute $25 stop and $10 alert, agent $10 and $5; `RAILWAY_API_TOKEN` made; `HF_TOKEN` added.
  - Google and Gemini: done. m2servcore owns the projects and billing. The Places key is restricted to Places API (New),
    with SearchText at 200 a day and a $10 budget. `GOOGLE_PLACES_MONTHLY`=1000 on Railway. One Gemini key, …rCWw, on the
    free tier.
  - Finished 2026-11-16 (Jason put each block in `.env`): cPanel and `STAFF_EMAIL_DOMAIN=trvgo.us`; email (Bluehost
    `donotreply@traversence.com` on 465, the orders mailbox, SES in `mock` until production access, `EMAIL_LINK_KEY`);
    Cloudflare R2 (`S3_…`; the AWS bucket `traversence-media` to empty after Nov 18) and Turnstile; Meta sign-in; Stripe
    and Square in sandbox (test values in both the plain and `…_TEST_`/`…_SANDBOX_` names; no live keys yet; setup at
    Stripe and Square skipped for now); Fly and Name.com (`DOMAINS_MODE=off`, setup later); the legal identity (Jason
    to consider a shared `LEGAL_EMAIL` and a business `LEGAL_PHONE`); Pixabay, OpenRouteService, `HEALTH_TOKEN` (must
    match the GitHub secret); Hugging Face on Railway only; Supabase (setup SQL from its card still to run).
- **Queued after the usage reset (~Nov 17 23:00 UTC):**
  1. **W0 Indexer: every URL logged, always on** (**done** 2026-11-17 in session_01CXLi5jAPsvr7mrCV6roykv: site side live as release #79, the worker in `main` (#803), Railway service `w0-indexer` running and asleep while W0 is paused; handoffs/w0-indexer.md) (0164 §1.4's note, Jason 2026-11-17: "Put it first in the queue"): every link on every page read into the frontier (same site or not, with its page and link text);
     classification runs by itself on new sites; reading only where §1.2's rules allow; a person's call overrides,
     never gates; today's "Waiting for you" sources classified on the first pass. Worker (`own_site_links`, the
     40/60 caps, `read_source`) and site (`Sources.php`, `SourceIndex.php`, `/api/crawl/sources.php`).
     **And the indexer always on** (Jason, 2026-11-17): its own continuous Railway service (restart on failure), not
     the cron; indexing only; first in Start the system; Pause everything stops it; its own switch and budget line.
     With the reader's verdicts looped back into the index (promoted, normal, demoted, junk kept compact so it's never
     recaptured; patterns become skip rules), reading priority, a size guard, and pace on the site: writes to the site in
     batches: 250 found URLs or 25 read pages, 100 KB at most, one batch per 10 s, self-adjusting (halve on 409/413/slow,
     grow back to 250, cap 500), settings in Thresholds, keyed; the pre-flight sends one full-size test batch.
     **Every batch gets a receipt** (stored count and checksum; the indexer resends what's missing; three mismatches slow
     it and open a Pulse case).
     No per-page or per-source caps (the old 60/40 go); a safety net only for a broken page with an absurd number of
     links: logged over several passes and flagged, nothing dropped.
  2. **The pre-flight check** (**done** 2026-11-17: live as #88, #90, #91, #92, the Outside services table #94 and the Fly fix #93, synced 15:07 UTC; session_01CXLi5kGG8gG4QBmC4uzMzN stands by for Jason's first report after his keys. Earlier: release 1 `preflight.zip`
     sent (built on Sync 14:05 UTC), waiting for Jason's OK; worker 1.1.0 in the same PR; Outside services as a table is
     release 2, after release 1 is live; handoffs/preflight.md) (decisions/0163 §10's note of 2026-11-16; Jason asked for it before launching the
     engine): System Configuration → Pre-flight, a real, free, harmless test of every service and setting, and every
     phase, workflow and task (services, scheduler, settings, caps, gates, a dry run where there's a preview), ready or
     not with the service's reason and the fix, required or optional for launch; Start the system shows it first;
     nightly and after a `.env` change, red items to Pulse; each Outside services card's Test is its item. Laid out as a
     queue log: one line per check, green ✓ / red ✗, each line opens to its status and its fix.
     Domains in it: Fly's API token can read the edge's certificates; Name.com's sandbox answers a search with a price.
     **Query timings** (Gemini's review, 2026-11-17): the heaviest queries (a radius search, a zone roll-up, a graph walk,
     an index lookup) timed against set limits, a ✗ when one slows as the data grows. Supabase awake and the index complete.
     Its own process: runs any time (stopped, starting or running) to diagnose, never starts or pauses anything.
     **With it, Outside services becomes a table** (Jason, 2026-11-17: "maybe we treat it like the table view with search
     and filters sorting"): the admin's table widget, one row per company (name, what we use it for, status ✓/✗/—, this
     month's cost, last checked); search across names, uses and key names; one filter dropdown (status: needs a look, in
     use, not in use; kind: storage, email, payments, AI, sign-in, data sources, domains, workers, hosting); sort by
     status (default), name or cost; a row opens to today's full card in place (nothing dropped); filter and search
     remembered. One list and one status shared with the pre-flight; a ✗ there links to the company's row.
  3. **W15 Funnel: every find and every piece of content into the discovery funnel** (decisions/0170, accepted) (Jason, 2026-11-16, found in the `.env` trace):
     `CrawlerIngest::resolveMicroClusterId()` files any ZIP outside an existing area under `DEFAULT_GEO_HUB_SLUG`
     (ancient-borderlands), wherever it is (callers: `api/ingest.php`, `IntakeStager`, `IntakeClusters`,
     `AddBusiness`). Fix: an existing area first; else the hub whose region holds it, or the nearest in reach; else
     "no hub yet", held for 0164's hub opening and counted as demand. **No default hub** (Jason: "I dont want the
     ancient-borderlands as the default. It is the pilot."): remove the hard-coded `'ancient-borderlands'` fallbacks
     (`api/ingest.php`, `IntakeStager::defaultGeoHubId`, `IntakeClusters`) and retire `DEFAULT_GEO_HUB_SLUG` (Settings'
     "Default place" row goes; nothing else reads it). Intake approvals never refuse for a missing hub: "no hub yet" is
     a valid answer. Data cleanup proposes moving areas already seeded outside Ancient Borderlands (preview, Undo).
     **Use the hub framework we have** (Jason, 2026-11-17: "dont we have a way this can use our existing hub Framework to
     put a find into the right hub even if it isn't an active hub yet?"): Ancient America → its 15 zones (`geo_hubs`;
     the Ancient Borderlands active, 14 `planned`, regions.md) → towns (`micro_clusters`). Give every zone its geography
     (its towns' ZIPs from regions.md through `zip_coordinates`, then the counties they sit in), so a find goes to its
     zone **whether the zone is active or planned**; an existing town first, else a town seeded in that zone
     ("needs review"). A find in a planned zone is filed there and shown as usual (open or planned is about our team's
     support, not visibility; Jason 2026-11-17), and counts toward that zone's readiness (HubOpening's stages, Planned → Open). Only a find outside all 15 zones is "no hub
     yet" (demand, held for 0164's hub opening). No default hub.
     **And across continental hubs** (Jason, 2026-11-17: "if it is not in this hub, but another hub it could be aligned
     within the correct main hub"): the plan is 20 continental hubs (Ancient America is Hub 9 of 20, platform spec);
     each gets its states (and its zones when they're drawn) as **planned**, so a find outside Ancient America is filed
     under the continental hub that covers it, held there and counted as its demand, and placed in a zone once that hub's
     zones exist. Only Ancient America is defined in the repo: **the other 19 hubs' names and states are needed from
     Jason** before this part is built.
     **For all content, not just listings** (Jason, 2026-11-17: "this is not just about listings but how all content
     gets organized into the discovery funnel too"): one place resolver, "where does this belong", used by every kind
     of content as it's made or found: listings, landmarks and outdoor places, places, guides, journeys, events,
     stories, heritage and topics, research and dossiers, Moments and Marketplace posts, sources. Each is placed on the
     place taxonomy (0042, 0043: Continental Hub → Geo-Hub zone → town cluster → anchor → sub-group) from its
     location, active or planned; shown as usual (0170: open or planned is about support, not visibility); feeding that level's discovery guide and counts.
     Something about a whole region (a state, a river, a trail across zones) is placed at the level that spans it.
     Existing content is re-placed by Data cleanup (preview, Undo). Today each kind places itself its own way.
  3a. **State licence lists: a write-up (a decision) and a build plan, then its own build** (Jason, 2026-11-17: "this
     seems like it needs a build of its own. Let's put it into the queue for a write up and build plan"). After W15,
     since it feeds the funnel and uses the one identity check.
     - **The crawler's log line** (Jason, 2026-11-18): every run logs "held: apachecountyaz.gov (Paused by a person (Added
       switched off (Jason, 2026-11-15): nothing calls it until Release B…". It stays off (Cloudflare; its list comes by
       hand), but the wording is stale: this build rewords `Operations::seed`'s reason for `azlicences` to "never crawled:
       Cloudflare; its list comes by hand (licence lists)" and finds what still sends the crawler to it.
     - **Why:** licensing sites block automation (azliquor.gov and liquor.az.gov, roc.az.gov, apachecountyaz.gov: a
       Cloudflare challenge on every page, `robots.txt` too; never worked around, 0164), but a person can export a
       county's list. Jason's Apache County export (2026-11-17, `license_search_export.csv`, DLLC): 250 licences, 23
       columns; 62 active, 188 lapsed (closure signals); 60 active premises (bars, liquor and beer-and-wine stores,
       restaurants, lodges, wineries), 59 with a phone. The value is the rural places open data misses (Molly Butler
       Lodge, Mulloys Public House, Hawley Lake Lodge, RL Winery), proof of open or closed, a phone and "serves
       alcohol"; chains are already in Overture. Exactly 250 rows: check for an export cap (a statewide export).
     - **Workflow:** one source per licensing agency (export page, terms, column map), for Ancient America's states
       (Jason to confirm which); a monthly Pulse case "Licence lists due", a step per agency with its export link and an
       Upload button; a new target in `upload-targets.php` through the one uploader (0127); an agency's own file feed,
       where one exists, replaces its step. About 5 minutes an agency a month. Records requests for a statewide file
       (DLLC, ROC, Apache County) only if exports prove capped. Contractors (ROC) next, the same way.
     - **Rules:** personal columns dropped on load (licensee and agent names, owners' mailing addresses; "anything
       about an owner is private"); kept in its own licence tables, credited, never copied into listings (like OSM,
       0065); never overwrites what a person entered.
     - **Duplicates and names** (Jason, 2026-11-17: "dups with one active and one closed or the name of record is
       different than what the local store brand is publicly"): in Apache County 110 premises licences are only 90
       addresses; 15 have more than one licence, 10 an active beside a lapsed one. Group by premises (address, phone,
       type), not licence: the newest active decides; a lapsed licence beside an active one is history, not a closure
       signal; a closure signal only when nothing at the address is active, and to Review, never automatic; a new name
       at the same address goes to the identity queue ("same place, new owner?"), never a second listing. The licence
       name is never the public name: kept as an alias for search ("Mickey's Again Inc", "Short Stop Markets"); the
       listing shows the owner's or best-attested name. Names cleaned before matching (case, spaces, Inc/LLC, store
       numbers); one identity check (0164 §3.1, `handoffs/duplicates.md`), no second path that makes duplicates.
     - **Write-up first:** a decision for Jason with these rules and the build plan (releases in order), then the build.
  4. **decisions/0169, the Shop's App store and one lifecycle for every app** (accepted 2026-11-16). First: the Shop
     with departments, the App store, the app lifecycle (choose, install, configure, use, update, remove), the Apps page
     and the staff view. Today's Stripe and Square connections keep working until their app is built.
  5. **Then the apps, one release each, in Jason's order** (each built on 0169's lifecycle):
     1. **QuickBooks Bookkeeper:** decisions/0166 steps 1–2 (our ledger and a QuickBooks connector matching today's
        path, run side by side and compared; then the setup step: A or B, the starter chart, the catalog's type,
        income, cost and link, the plan detected);
     2. **QuickBooks Payments:** 0166 step 3's payments layer (routing, the fallback, refunds through the original
        provider, clearing accounts), QuickBooks Payments its first processor;
     3. **Stripe Payments:** 0135's connection moved in, with "Which should I use?" and "Don't have an account?";
     4. **Facebook Personal:** decisions/0168 Phase 1;
     5. **Facebook Business:** 0168 Phase 3 on (waits for Jason's Business Verification and Meta's App Review).
     6. **Storefront:** 0139 moved in; its setup includes the address (keep trvgo.us/<name>, bring a domain, or buy one).
        **With it, trvgo.us's hyphenated addresses** (found 2026-11-17 by the favicon build; Jason: "add to storefront
        app"): `https://trvgo.us/round-valley`, `/sj-portables` and pages like `/shop/about-us` get Apache's own 404
        ("…while trying to use an ErrorDocument") and never reach `trvgo/index.php`; `/abc12` and `/sjportables` do.
        Likely `trvgo/.htaccess`'s rewrite pattern allows only letters and digits: widen it to hyphens and slashes
        (real files and folders still excluded), give trvgo a working ErrorDocument, name the `.htaccess` separately in
        the release (dotfiles aren't synced), and test a hyphenated storefront end to end, including a business's own
        domain through the Fly edge (`X-TV-Host`), untested.
     7. **Domains** (0169 §1a): **live as release #71** (2026-11-17 03:30 UTC, synced and verified; session archived) from session_01CXLi5j7MKBy27a6L7AXRHN (started 2026-11-17 00:53 UTC at
        Jason's ask): Shop → Domains, the account's Domains tab, and the shared wallet (0166 §6a) only. Joining storefronts to the account's domains
        stays with app 6 (Storefront), queued; the storefront's Connect your domain is left as it is until then.

     8. **Outside mailboxes in Pulse** (Jason, 2026-11-18, from the Communications draft decisions/0172 §4.7, PR #903:
        "the app build should address these questions, so move it to the session manager to include into the app build
        queue"). Members get outside mailboxes as an App store add-on; for businesses the app build settles: how a
        business connects its own mailboxes (IMAP/POP, Microsoft sign-in, Gmail, SES receiving), whether it's an add-on,
        in plans, or from which plan up, its price, and set-up and standards. Connection details and rules are in 0172
        §4.7 (once 0172 is approved).
     Others (Square, Tickets, Appointments, Gallery storage, calendars, 1Password…) to be discussed with Jason next.
  6. **The cleanup stage** (its own area; can run beside the above). **Fixes in the code:**
     **Moved to the Control Board** (Jason, 2026-11-18: "clean up fixes that touch the system should move into the
     system board session so they can be addressed as it builds the board"): 18 fixes are now in
     `handoffs/control-board.md` ("Cleanup fixes folded in"), built with the board's releases.
     - Data cleanup's "From visitors" label;
     - Admin search on a phone (seen 2026-11-17, 390px): a result's where-it-lives line takes the row, so names shrink
       to "Na…", "Bus…", "Em…". On phones, put that line under the name, full width.
     - The switch-on check's `BRAVE_API_KEY` hold is correct (Jason, 2026-11-17: Brave and Tavily both present and
       ready; which one a workflow uses is the workflow's matter). (The 36 ungated calls: gated by staging-gate.zip.)
     - **Supabase vector security, its own small release** (handed back by the Control Board session, 2026-11-18):
       `Vectors::setupSql()` (Supabase card) leaves `tv_vectors` without row level security and `tv_match` callable by
       Supabase's public roles. Add `enable row level security` and revoke `tv_match` from anon/authenticated/public with
       a grant to service_role. Jason ran these by hand on 2026-11-16, so live is safe; this makes a fresh setup safe too.
     - **OpenStreetMap coverage reads our Geofabrik copy, its own release** (handed back by the Control Board session,
       2026-11-18: it changes how W1 gathers, which 0171 leaves alone). Coverage jobs (800000013, 800000014) wait on
       Overpass with every server held; read `open_places` (Geofabrik) first like W1 (#101), Overpass only when one is
       open. With it: a crawler run whose only failure is a held service ends ok (Railway showed CRASHED).
     **For Jason to address in the cleanup** (Jason, 2026-11-17: "Add these to the queue for me to address in the clean
     up"):
     - **Records requests to the Arizona Corporation Commission and New Mexico's Secretary of State** for their
       registers as files (or an API, or our host allowed); they unblock the AZCC and NM SoS readers and W14
       (`arizonabusinesscenter.azcc.gov` answers 403 from the build environment; the pre-flight reached both from the
       site). Each site's terms read first (0164).
     - **Wikimedia Commons for heritage (W6):** store its facts with credit, or only link to it. (Suggested: accept
       public domain, CC0, CC BY and CC BY-SA with credit and licence shown, files unaltered; skip no-derivatives and
       non-commercial; National Park Service photos checked per credit.)
     - **Approve the licence-source strategy, decisions/0164 §7.1** ("when the official licence source is blocked or
       lacking": records request, the claim-time registry number, other free authorities, a licensed aggregator only by
       paid override, else "no authority source yet"). It went into `main` with #851 but needs Jason's OK before anything
       is built from it (then queued: W14's five step states, the letter template, the registry-number field and a
       one-look task, a SAM.gov reader, the aggregator's PaidOverride need). Fold queue 3a's monthly manual exports in
       as a step if he agrees.
     - `GITHUB_SYNC_TOKEN` expires Dec 16, 2026: renew at https://github.com/settings/tokens. (`HF_TOKEN` and
       `BRAVE_API_KEY` are set on Railway.)
     **Visual fixes** (Jason, 2026-11-17: "add to visual fixes queue"):
     - **Discovery's hero doesn't follow the process already decided** (Jason, 2026-11-17: "when I click regions it
       doesnt really feel like the page changes visually … The hero is not changing like it should", then "I thought I
       already outlined this process"). The process: **real photos first** (journeys' photos for that place and the
       places inside it, 0058 §30 and the explore-hero section; then the page's own photos, e.g. landmarks' Commons
       photos), **stock only when there's no real image** and replaced by itself the moment one exists (0138, 0143 §4),
       and stock picked **this place first, then its region, then representative of the subject** (0143 §5,
       `StockLibrary::pick`). The code doesn't: `website/discovery/index.php` (lines ~497, 582, 599, 650) passes no place
       for Regions and Parks (Parks ignores `state=AZ`) and the same subject `landscape` everywhere, and
       `tv_explore_hero()` takes the one stock photo before the page's own landmark photos (`fallback_slides`,
       `dv_hero_photos`, 6 a view). Fix it to that order, each view passing its place (and a subject that fits the
       view), several real photos with arrows; and make switching views visibly change the hero (its photos, the tab
       in the heading, the view's own cards on it as the Overview has).
     - **An empty footer edit zone squeezed to one word per line** (Jason's screenshot, 2026-11-17, under the "Local
       businesses / Cities & towns" counts): the zone "…page, above the footer is empty: Edit to add a section" sits in
       a narrow column, so its tag and text wrap a word a line. The zone should take the full width of its row (or the
       page). Moot for visitors (they don't see empty zones), but goes with "editing switched on".
     - **Page-builder editing is switched on, not always on** (Jason, 2026-11-17: "i would prefer to have to activate
       these feature"): today a staff account with `pages.edit` sees every zone outlined with "Not published yet" and
       Edit on the live site (`Blocks::zone()`, `js/tv-editor.js`). Make it off by default: an **Edit page** switch in
       the profile menu (or the admin bar) turns on editing for that device until turned off; with it off, staff see the
       page exactly as visitors do.
     - **Stock photos show their byline** (Jason, 2026-11-17: "the by line should be shown, with maybe an i icon to show
       more"): replace the "Stock photo · Representative" pill with the photo's credit shown on the photo ("Photo:
       jplenio on Pixabay"), with an ⓘ that opens today's panel (representative photo, licence, link, About stock
       photos). Keep it `tv_icon()` and the brand look; check on phones.
     **To purge or remove** (Jason, 2026-11-17: "put anything that needs to be purged or removed into queue under
     cleanup stage"). Each is checked against the live site first and removed only with Jason's OK where marked ⚑:
     - **Amazon S3:** empty the old bucket `traversence-media` (from 2026-11-18, once a week on R2 shows nothing missing;
       0151 step 5) ⚑, then narrow the AWS key (`AWS_ACCESS_KEY_ID`) to SES sending only, then delete the `#OLD
       S3_BUCKET=traversence-media` line from `.env`.
     - **`.env` leftovers** (Jason removes them; nothing reads them): `MIGRATION_RUNNER_TOKEN` (its scripts are gone),
       `AUTO_IMPORT_MIN_CONFIDENCE` (moved to Thresholds), Cloudflare's stray `token_value=` (the R2 page's token value;
       not read; keep it in 1Password if wanted, don't revoke: it may be the R2 key's own), `DEFAULT_GEO_HUB_SLUG`
       (with the hubs-by-location fix), and `QB_INCOME_ACCOUNT_ID`/`QB_REFUND_ACCOUNT_ID` once 0166's setup step is live.
     - **Settings:** the "Default place" row (goes with the hubs-by-location fix).
     - **Claims:** the lapsed claim on `app/lib/Store.php` by the archived photos session: clear it with `checkout.py`.
     - **GitHub:** merged `claude/…` branches (Branches → trash), keeping `claude/session-manager-checks`.
  7. **Release B's small leftovers:** P3-5 (the owner's "Check these") and P3-2's Facebook-link case.
  8. **Communications, decisions/0172** (accepted 2026-11-18, draft 16; hand-off `handoffs/communications.md`). Builds in
     0172 §6's order (Jason: "Text, Email, Push engines... are separate builds"):
     1. 0171's alarms (the Control Board, building), then 0167, the email engine (its four steps below);
     2. one dispatcher into Pulse;
     3. levels, flags and mailboxes in Pulse;
     4. listing pages and cards (only what a listing can do);
     5. the push engine, its own build;
     6. outside mailboxes (app 8 in item 5);
     7. comments, bookings and groups;
     8. the text decision first (the open questions are in `handoffs/communications.md`), then its engine.
     **0167, the email engine** (accepted 2026-11-16), in its four steps, each a release
     for Jason's review:
     1. System Configuration → Communications: senders, mailboxes from cPanel on every domain, Check and Send me a
        test, domain health read-only; Bluehost sends logged;
     2. manage: make, suspend and reset mailboxes, passwords written to `.env` by the site, the routing table, DNS fixes;
     3. Pulse: incoming mail in the Stream, bounces and complaints as items, the Sent view, failures as cases;
     4. compose in Pulse, with threading; one inbox per person across the mailboxes they oversee, and a From choice (§2a).
  (decisions/0168's phases are apps 4 and 5 in item 2; its later phases follow those.)
  9. **Backups for the needs that have none** (Jason, 2026-11-17: "this needs to be a future build so pass it to the
     session manager for adding to the work queue"). Recorded in decisions/0163's note "the source map, ours first, a
     backup for every need", table "Planned backups" (in PR #840). Not needed for launch; until each is built the
     pre-flight shows it as "no backup yet, planned". Jason's order: database backups and the read-only copy first.
     1. Hosting (Bluehost): nightly database backups to R2 or Supabase; a nightly read-only copy of the public pages
        (guides, listings, places) served from Cloudflare or the Fly edge when Bluehost stops answering (Supabase can't
        run the PHP site).
     2. The engine's host (Railway): Bluehost jobs keep the light tasks; the same crawler image on the traversence Fly
        organization for the heavy ones.
     3. The sync (GitHub Actions): Railway runs `sync.py` on its schedule; the result recorded in git once GitHub is back.
     4. Our payments (QuickBooks): Stripe as a second processor, then wire or check; no card processing of our own (PCI).
     5. Marketplace checkout: the business's other processor if both are connected, else pay at pickup or contact the
        seller.
     (The source map's own builds stay with the pre-flight session after PR #840: (a) the pre-flight judging by need,
     our own data copies checked; (b) W1 reading `open_places` (the Geofabrik copy) before Overpass; (c) the other
     workflows' fallbacks.)
- **Jason, from the Domains build:** set **Own domains on storefronts** to **test** on the Name.com card
  (https://traversence.com/admin/config/services) to try domain sales; **withdraw release #70** (staged, never shipped:
  its migration wasn't add-only; #71 replaced it) on the site manager's Releases page.
- **For Jason to sort out:** the **Chameleon Filter** (brand.md §5, architecture.md §22, 0003, 0131 §3). **Already built
  in guides:** the Visiting / Live here dial and each section's picks (relevance, proximity, kind, season; never paid);
  **Jason's frame (2026-11-17):** the place tree and the topic tree joined by home, touch points and reach (0170 §2)
  "is the chameleon filter in my head": the filter is the lens that, for a visitor's intent, picks which links to follow.
  **still to decide:** the Community track, the filter beyond guides, and its use of the intent tags (0170 §1a): how each place's page shifts emphasis by the visitor's track (Get Local, Let's Explore, Community), which
  tags and items show for each, and the DIKW climb. Phase 4's intent tags (decisions/0170 §1a) are its data; its
  design is Jason's to decide (Jason, 2026-11-17: "put chameleon filter on the queue for me to sort out"), then a
  decision and a build. Two questions with it (2026-11-17): (1) where research finds a real community organization
  with no group, should the Community track invite it to claim its listing and start one? Groups are never
  auto-created; they stay person-made (Jason). (2) When an item is both Get Local and Community (a church, a library),
  which track leads on a place's page? Tabled by Jason for another day.
- **Jason's own, any time:** Meta Business portfolio and Business Verification (0168 Phase 2); the OSM forum statement (`research/osm-overpass-2026-11-13.md` §2), and the SES dashboard
  check for bounces lost while the bot check blocked them.
