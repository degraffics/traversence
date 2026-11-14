# The crawler revamp: plan and hand-off

Jason, 2026-11-13: "We need to fix issues but we also need to fix the workflow management so we quit failing on Railway.
We need to initialize a revamp of the entire crawler management system. This revamp will fix the issue by deploying the
crawler by workflow and properly addressing each concern, conforming to our new standards, and assimilating old data into
current fresh and accurate data."

> **PAUSED (Jason, 2026-11-14 ~15:55 UTC): the Railway crawler is off until he has reviewed
> `research/crawler-how-it-runs-2026-11-14.md` and `decisions/0163` and says to resume.** The `traversence` service's
> cron was removed through Railway's API (instance `cronSchedule` null, was `*/5 * * * *`). `workers/crawler/railway.json`
> still says `*/5 * * * *`, so **any deploy of the worker restores the schedule**: don't merge a change under
> `workers/crawler/` until Jason says resume. To resume: set the cron back (API `serviceInstanceUpdate`, or Railway →
> traversence → Settings → Cron Schedule) or deploy the worker. The website's own workflows (W4, W5, W7, W8, W10) and
> its OpenStreetMap calls are not paused. **Since release #48 (2026-11-14 17:57 UTC) they are:** the automation switches
> shipped with the master paused (`decisions/0163` §9), and nothing resumes before 0163 §10's switch-on check.
>
> **ACCEPTED (Jason, 2026-11-14: "all approved"): `decisions/0163` and `decisions/0164`**, with 0164 §14 settled and no
> shadow mode. Listings are public once past the "real business" floor, at their confidence (0164 §3.5; the
> collaborative score feeds confidence, weights in 0163 §12); closure signals lower confidence and ask "Still open?",
> never label Closed (0164 R12). **The crawler and the automation stay paused** until the build stages (0163 §8, stages
> 2–11) and the switch-on check are done and Jason switches them on. Build from 0163 §8, one stage per release.

For the Claude session that carries the revamp. It was written by the session that vetted the crawler. Read this, then:
- `decisions/0160` (the design: one crawler service, configured by each workflow's capture tasks; the 13 workflows
  W1–W13 start to finish);
- `research/crawler-review-2026-11-13.md` (every defect, with file and line, and fix numbers A1–F27);
- `decisions/0164` (the Knowledge Engine: the rule book R1–R19 it took over from `decisions/0133`, the phases, the
  workflows W1–W14), with `decisions/0163` (operations: switches, budgets, caps, the switch-on check) underneath; both accepted 2026-11-14.

**Jason works mostly from his phone.** Keep his steps short, send each zip as a file, and check the decisions before
asking him anything (he has answered a lot already: see "Decided" below).

## 1. The goals

1. **Stop failing.** Nothing the crawler does may damage good data, show what it mustn't, or spend past its cap. Its real
   state is visible: a run that did nothing useful is reported as a problem, never as "succeeded".
2. **The crawler management system, rebuilt on 0160.** One crawler service. Each workflow configures it with capture
   tasks and is deployed onto it one at a time, meeting the current standards.
3. **Old data brought into line.** What the old system made is re-checked against the new rules:
   - restore what it damaged;
   - quarantine what doesn't meet the rules (never delete it);
   - refresh the rest, until every record is current, sourced and scored.

## 2. Decided (don't ask again)

- **0160, accepted:** one crawler service, configured by the workflows' capture tasks (what, where from, how often and
  how fresh, its share and limits); the crawler runs them, the workflow decides what results become.
- **Protected places:** decision 0133's three levels (2026-10-30), already built in the guides:
  - **public:** directions like any place;
  - **known publicly, not open to visit:** named and told, never the way in, with the authorization line;
  - **held:** never named or located.

  Data is kept and managed, never ignored or deleted; the nation gets control once its tools exist (0066).
- **Scoring decides, both ways** (0160 W1, W2):
  - one source can establish a listing, and a government page we read can by itself; it's then vetted continuously;
  - one site's "closed" is a signal on the score ("reported closed"), not an instant close.
- **The AI exception** (0160 §5, CLAUDE.md): the always-on research and development tools are within the capped budget,
  and every call's cost is counted, failed calls included, so the cap holds.
- **Every build is a release** (CLAUDE.md), tried on staging and looked at by Jason; FTP or Publish now (urgent) only for
  a system issue that can't wait.

## 3. The phases

Each phase is one or more releases. Each release carries its FAQ change, passes the zip check, and is recorded.

### Phase 0: stop the damage now (one urgent release, first)

Small guards in the current code, before any rebuild, so nothing more is lost while the revamp runs:
1. **A refresh or identity lease that was never reported counts as not tried**, never "unreachable" (review B5). Nothing
   more is taken off listings for a run's lack of time.
2. **An empty source read changes nothing:** the source's places stay, and it's handed back (B6).
3. **A failed guide draft keeps its record:** "kept down", hash, renew (B7). A draft never overwrites a section a person
   saved after it began (B8).
4. **The monthly landmark load stops deleting held rows** (B9).
5. **A data snapshot for the revamp:** counts for each store, reported with the daily health report so the plan has
   numbers. The stores:
   - staged finds by state, Review's size, ready finds stuck in no list;
   - the place store, and sources wiped;
   - websites taken off (`website_dead`) since this began;
   - listings without a map point;
   - town jobs by status, and coverage jobs failed;
   - the register's waiting sites;
   - OpenStreetMap values in listings;
   - heritage facts by tier.

   Read-only: nothing changes.

### Phase 1: close the exposure (review A1–A4)

1. **"Named on pages we read"** gets the listings' guards:
   - protected places by 0133's levels;
   - a nation's land by its rules (`Nations::at`);
   - confidential addresses never shown;
   - person-looking names withheld.

   Held places are kept and marked.
2. **The protected-place check always knows the nations' peoples** (inside `Landmarks::isProtected`), and heritage facts
   already tiered are re-tiered.
3. **The OpenStreetMap layer gets the nations check.**
4. **Guide links follow 0133's levels** (the `dismissed` path).

### Phase 2: the crawler service (0160 §2)

1. **On the site:**
   - **Workflows and capture tasks:** what to capture, from where, how often and how fresh, the workflow's share and
     limits.
   - **One queue of requests**, leased only when there's time to do them, done or handed back unchanged. Never counted
     as a failure for lack of time.
   - **Run reports**, request by request.
   - **A cost ledger:** every paid call counted, failed calls included (D16, D17).
2. **The worker on Railway:**
   - It works the queue by each workflow's share, with fixed budgets per request, and stops cleanly before its time.
   - **It no longer runs a fixed order of stages.** The bulk loads become capture tasks on their own schedule; they never
     take a whole run.
   - **Its exit and its report say what really happened.** A run that couldn't work (the site unreachable, no token,
     everything starved) is a failed run on Railway and a Pulse case.
   - Robots.txt with our own agent and checked redirects (C15).
3. **The admin, a Crawler console:**
   - each workflow: its queue, running, done, failed and why, its share, its spend, its last runs;
   - retry and requeue, and pause or resume each workflow (not only `.env`);
   - the false "crawler has stopped" alert replaced by real checks.
4. **Railway:** still one service (0160), its schedule and limits checked against the new runner. Its deploy is from
   `main`, `workers/crawler` (as set).

### Phase 3: deploy the crawler by workflow (0160 §3)

One workflow at a time moves onto the service. Each move does four things:
1. sets up its capture tasks;
2. fixes its own defects from the review;
3. gets its view in the console;
4. switches off its old stage in the worker.

**Order:**
1. W1 listings: E21–E23, C11 (OpenStreetMap kept as a lead and a link, not copied), C12, Send the crawler with its
   words, and the scoring decisions;
2. W3 the source register and place store: the waiting sites, "Visit website" links, use rules, the two page readers;
3. W2 keeping listings current: changes caught, live duplicates, map points;
4. W4, W5 and W8 guides: starvation gone, retries, labels, Find it;
5. W6 heritage and topics;
6. W9 landmarks and places on demand: C13 (Wikipedia cite only);
7. W10 live events and W11 how-to guides: C14;
8. W12 search learning: demand that reaches the kind searched, and named businesses.

Also in this phase, the search fixes (E26), with W1 or on their own:
- name words no longer counted as kinds;
- a name read first;
- an honest "nothing named … near …".

### Phase 4: bring the old data into line

Each store gets an **assimilation pass**: a capture task of its own, run through the new service, with a ledger of every
change and an undo. Nothing is deleted; what fails the rules is quarantined with its reason.

| Store | The pass |
|---|---|
| Websites taken off listings | Re-check each one taken off since this began; put back those that load, with their history |
| The place store | Read again every source wiped by an empty read; apply each source's use rule; mark protected and nation places by 0133 |
| Staged finds | Re-gate all of them under the new scoring; stuck "ready" finds published or sent to Review with what's missing; finds with no kind to a person |
| Review | Re-sorted by the new scoring; what the new rules settle settles itself, with a note |
| Listings | Re-scored. OpenStreetMap values that came from OpenStreetMap only are re-sourced from the business's own site or a source we read, or marked as unconfirmed. Missing map points placed. Live duplicates found and offered for a merge. "Closed" set by one website alone becomes "reported closed" under the score |
| Town jobs and coverage jobs | Failed and orphaned jobs reset under the new queue; towns built out once are re-offered by demand |
| The source register | Sites waiting after a failed describe described again; terms judged where the feature is on |
| Heritage facts | Re-tiered with the peoples loaded; each newspaper page counted as one source of its paper, not an independent one |
| Landmarks | Held rows the monthly load deleted restored from the load's files where they can be; the rest noted |
| Guides | The "kept down" marks and hashes a failed draft wiped, restored from the guides' history where it holds them; first-look labels |
| On-demand requests | Failed ones retried, and visitors who asked told |

When a pass ends it reports what it restored, refreshed, quarantined and left, and the record is then kept current by its
workflow.

### Phase 5: proof

- **Each workflow's test set:** St. Johns, AZ, with Blue Hills Environmental, a town searched, a guide, a held place.
- **A week of reports with no starved work and no damage**, and spend inside the cap.
- **The review's fix list closed item by item** in `research/crawler-review-2026-11-13.md`.

## 4. Standards every release meets

CLAUDE.md, in full. For this work above all:
- start from the live mirror;
- every build is a release;
- the zip check;
- the FAQ;
- 0133's rules;
- 0066 (nations);
- "no income, no expense" with honest costs;
- migrations safe to run twice;
- PHP 8.0 syntax;
- the admin standards (manifests, `Access::can`, greyed out with the reason, the command strip, notices, busy
  spinners);
- times shown where they happened;
- the phone layout.

## 5. Where things are

- **The worker:** `workers/crawler/` in this repo (`crawler.py`, `Dockerfile`, `railway.json`, `README.md`). Railway:
  project gracious-sparkle, service **traversence**, branch `main`, root `workers/crawler`, cron every 5 minutes,
  auto-deploy on push (so a merge to `main` that touches `workers/crawler/` redeploys it).
- **The site side** (the live mirror `degraffics/traverse-3way-sync`):
  - `sync-app/lib/crawler/*.php`;
  - `sync-storage/api/crawl/*.php`;
  - the ticks in `sync-app/scripts/`;
  - `sync-app/lib/Scheduler.php`;
  - the admin pages named in the review's Part 4.
- **Delivery:**
  - the release flow in `handoffs/0159-the-builder.md` §3;
  - for the worker: a merge to `main` deploys it, so its changes go out with the site release that needs them, never
    ahead of it;
  - the site and the worker keep one contract (the API), versioned, so either can go first safely.

## 6. Progress

- **Phase 0** (2026-11-13): `crawler-phase0_20261113.zip`, built on Sync 15:26 UTC, sent through git; Jason approved it as
  urgent (under release #9 it publishes itself once staging passes). Site guards only (no worker change, no migration): `Refresh::lease` (abandoned leases not
  tried, `crawler_refresh_abandoned` counted), `IdentityJobs::lease` (expired leases keep their tries),
  `SourceIndex::store` (each page read replaces only its own places; a read of nothing hands the source back),
  `Guides` (`draftFailed`, `keepNewer`), `Landmarks::finish` (never `waiting`, only states loaded). The snapshot is the
  health report's `crawler` section, written daily to `site-audit/crawler-snapshot.md` (history in
  `site-audit/results/crawler-snapshot.json`) by `tools/page-audit/towns.mjs`.

  Live as Release #13 (15:29 UTC); verified byte for byte on Sync 15:31 UTC.
- **Phase 1** (2026-11-13): `crawler-phase1_20261113.zip`, built on Sync 15:31 UTC, approved by Jason in chat.
  `SourceIndex::guard()` (the listings' guards on "Named on pages we read" and on staging finds; held rows marked
  `source_facts.held`, never deleted), `Landmarks::isProtected` loads the peoples itself, `Heritage::retier()` (run by
  the heritage tick, a batch each hour, from `heritage_retier_at`), `OpenPlaces::near` (nations' land), `GuideLinks`
  (0133's levels; a matcher's set-aside is matched again each scan).
  Live as Release #14 (15:38 UTC); verified on Sync 15:41 UTC.
- **Phase 2** (approved by Jason in chat): `crawler-phase2_20261113.zip`, built on Sync 15:41 UTC. The site's
  service (`app/lib/crawler/CrawlService.php`, `website/api/crawl/service.php`, migration
  `2026-11-13_crawler_service.sql`), D16 in `AiClient`, D17 in `SecondTier::recordExtracts`, `CrawlService::stalled()`
  in Pulse, the console **Insights → Crawler** (`app/admin/crawler-console.php`; Jason chose Insights). The worker change
  went to `main` (Railway redeploys from it) once the site part was live: Release #16 (15:57 UTC), verified on Sync
  15:59 UTC. Watch the first runs in Insights → Crawler.

- **Phase 3, W1** (approved by Jason in chat): `crawler-w1_20261113.zip`, built on Sync 16:18 UTC. The gate
  (`AutoImport::evaluate`: a government page alone; one source at `listing_one_source`, 50, in Thresholds & gates;
  OpenStreetMap never a source; a find publishing would refuse isn't "ready"), town jobs (`TownCrawl`: tries counted on
  failure, failed rounds, partial cap, rebuild after 30 days, `want:<kind>` jobs), the coverage queue's upkeep and partial
  jobs (`CrawlQueue`), the place store (`staged_at`, no-kind finds with phone and street, use rules), Send the crawler's
  words (`find_name` requests), search (`LocalWords`: names aren't kind clues; a name first in `search_listings.php`).
  The worker half (C11 in `candidate()`, the `find_name` handler) is on `wip/w1-worker`; merge it once W1 is live.
  Left on purpose: `SourceIndex::listed()` still counts hidden listings (showing a page's copy would undo a hide).
  Live as Release #20 (16:38 UTC); verified on Sync 16:41 UTC. Its worker half went to `main` after.
- **Phase 3, W2 and W3** (approved by Jason in chat): `crawler-w2w3_20261113.zip`, built on Sync 16:41 UTC. B10: one
  website's "closed" is `closed_signal` ("Possibly closed" on the listing, score -10, a Review item), never a closing
  (`Refresh::siteResult`, `ListingConfidence`, `listing/view.php`); refresh and map-point queues no longer stall on rows
  they skip (`Refresh::lease`, `Geocoder::candidates`); the register describes waiting sites again (`Sources::toDescribe`:
  14 days after an empty describe, 60 for any undecided). Worker half: a "Visit website" link fills in the place named
  before it (`own_site_links`); merged after the site part is live.
  Live as Release #22 (16:57 UTC); verified on Sync 16:59 UTC; its worker half merged then.

- **The first runs through the service, checked** (2026-11-13, 17:45 UTC, from Railway's logs; the console has the same
  runs): every run since 16:15 plans, works and reports through `service.php` (no more fixed order). Two faults:
  - **Coverage overran its share:** one OpenStreetMap job took 6–7 minutes (five mirrors at 125 s each; overpass-api.de
    answers 406, the others time out, openstreetmap.fr is for white-listed users only), so in 5 of 13 runs every other
    task was "not reached" and the next 5-minute slot was skipped.
  - **Common Crawl failed every run** on one malformed address in its index ("Invalid IPv6 URL" in `host_of`).

  Fixed in the worker (2.1, no site part): `overpass()` and `wikidata()` work inside the task's time left; a lookup that
  runs out of time hands the job back with its tries as they were (`OutOfTime`), only real refusals are failures;
  openstreetmap.fr dropped; `host_of` never throws.
  Also seen, for later: every release commit to `main` redeploys the worker (the service's watch paths are empty on
  Railway, though `railway.json` names `/workers/crawler/**`), and a redeploy stops a run mid-way (16:01, 17:00, 17:38);
  junk names reach listings from page reads ("Contact Us:", "An agency within the U.S. Department of Labor", "CDPF
  Informational Webinar Slideshow"): Phase 4's listings pass and W3's page readers.

- **Phase 3, W2 finished** (approved by Jason in chat): `crawler-w2b_20261113.zip`, built on Sync 18:59 UTC. Live as Release #28
  (19:19 UTC); verified byte for byte on Sync 19:20 UTC; its worker half (2.2) merged then. A changed
  phone or hours on a listing's own site goes to Review as a fix with the new values (`Refresh::changed`, `site_said`, asked
  once); live duplicates (`LiveDupes`, in `listings_tick`: the same phone plus the same Google place, or the same ZIP
  with the same name or street address; at most 4 listings to a number) offered in Review as Same business, a pair never
  asked twice; the crawler's own Review items no longer hit the visitors' 12-a-day cap (`Suggestions::create`); W2's
  Review waiting shown in the console. Worker half: 2.2, the refresh sends `seen` (one phone, the hours). Left: listings without a website are still checked only by Google
  (no key on Railway) and the identity jobs.
- **Worker 2.1 on Railway** (18:29 UTC): runs now keep their shares (18:36's run reached every task). Coverage still finds
  nothing: overpass-api.de answers 406 to the worker every time and the mirrors time out, so each job is handed back.
  Open: why the main Overpass server refuses Railway (its policy, or the request), and whether coverage needs its own
  longer share.

- **Phase 3, W3 finished** (approved by Jason in chat; live as Release #30, 19:57 UTC; verified on Sync 19:58 UTC): `crawler-w3b_20261113.zip`, built on Sync 19:39 UTC. Listing
  Intake's crawl (`api/admin/intake/crawl.php`) now hands what it read to `SourceIndex::keepRead`: its pages in the page
  log, the site on the register (`Sources::note`, judged like any), and its places in the place store where the use rule
  allows facts and the site isn't marked Ignore (`storeMore(..., 'intake', false)`: Listing Intake stages its own finds).
  Kept synchronous on purpose (the person sees the result at once), rather than `read_page` requests. Left: the other
  SiteFetcher callers (DeepLook, Dossier, GuideLinks, Rebuild) read businesses' own sites and don't log their pages yet.

- **Phase 3, W5** live as Release #32 (20:19 UTC), verified byte for byte on Sync 20:21 UTC; its database update
  (`2026-11-13_guide_requests_retry.sql`) was still waiting to run then (the site runs new ones within the hour: check
  `db_updates_log`, or that the file is gone from the next sync). W6, W9, W10/W11 and W12 are built, tested and
  approved (Jason, "merge all"), not sent: §8. W5 `crawler-w5`: a failed guide on demand is tried again by itself (30 min, 2 h, 8 h, from the step it
  stopped at; `OnDemand::failed`, `retryDue`; migration `guide_requests_retry`: `tries`, `failed_at`), then one Pulse
  case `guidefail:<time>` with Retry them all; the visitor's page gives our email; W5's requests in the console. W6
  `crawler-w6`: an empty Library of Congress search asked again in 30 days, a newspaper's pages one source (its LCCN,
  `Heritage::paper`), the extraction queue moves past places it skips. W9 `crawler-w9`: `PlaceFinder::summary` keeps
  Wikipedia's text only when `Landmarks::wikiAllowed`. W10/W11 `crawler-w11`: how-to steps never drafted from a
  cite-only or Ignore source; Publish refuses a step that reads as its source wrote it unless its licence is open
  (`HowGuides::copied`, the page's words kept as `sources[].said`). W12 `crawler-w12`: searched gaps leased before the
  sweep (`CrawlQueue::lease`), towns' searches count for their cluster (`Learning::gaps`). W4 and W8 need nothing more
  (starvation gone with Phase 2's shares; Find it shares W1's gate). Progress of each: the commits "Release crawler-w…".

## 7. Open (questions for Jason; his answers 2026-11-13 evening in each)

1. **Coverage finds nothing on Railway: why.** overpass-api.de answers 406 to every request, POST and GET. Its
   maintainers say a 406 there is a ban they set by hand on a user agent or referrer (an IP ban follows if the 406s are
   ignored), and only they can lift it: so our agent (`TraversenceCrawler/1.0 (+https://traversence.com; …)`) has
   been banned, most likely after the old fixed-order runs retried it run after run. Never get round it by changing the
   agent. Ask the Overpass maintainers (the OSM community forum, Overpass category) why and how to be unbanned, giving our
   contact, the agent, our rate (a few queries an hour now). Meanwhile Railway's `OVERPASS_URL` is
   overpass.private.coffee, which times out on our larger box queries (a town's every kind in one query, `[timeout:110]`):
   split queries per kind, or smaller boxes. openstreetmap.fr is for white-listed users only (dropped in 2.1). Since 2.1
   a slow lookup is handed back inside its share, so nothing else starves.
2. **Google Places is on Railway** (`GOOGLE_PLACES_KEY`, `GOOGLE_PLACES_MONTHLY` 1000; my earlier note was wrong). Every
   run's Google check says "nothing to do": the site paces it (`api/crawl/usage.php`: 90% of the month, at most the
   month's share of a day, about 30), and the worker logs nothing when it's allowed 0. Web search shows the same ("this
   run may make 0", 213 of 1000 used). To do: the worker logs the site's reason (`stop`, `pace`, `daily`, `day_used`);
   checked: Thresholds & gates `places_monthly` is 1000 (Jason's screenshot, 2026-11-13), so about 30 a day are allowed. Left to find: the site's `.env` `GOOGLE_PLACES=off` (or `WEBSEARCH=off`), the day's share already spent, or `places.php` handing out no listings; the worker's new log line will say which.
3. **Page reads shouldn't create listings** (Jason: "I thought reading should be indexing page content?"). Today they
   do: decisions/0157 §3 (delivery 2) stages up to 40 places a read through the intake (`SourceIndex::stageNew`), and
   W1's gate (Jason, 2026-11-13: a government page alone can establish a listing) publishes them: that's how "Contact
   Us:", "An agency within the U.S. Department of Labor" and "CDPF Informational Webinar Slideshow" became listings.
   Proposed: a page read only indexes (the place store, search's "Named on pages we read"); a listing comes only when the
   business's own site, a registry or a person confirms it. Needs his OK (it changes 0157 §3 and W1's gate; note both
   decisions). Phase 4: listings made from page reads alone go back to the store, quarantined with the reason.

**2026-11-14: outside services' issues (Jason, through the session manager: "1. if an api we use has an issue we need to
identify and address said issue. 2. the system must stop trying with an API [that] has an issue until it is resolved. 3.
The system must ask for help regarding the exact issue it is facing while still [performing] duties that have no issues").**
- Site: release `service-health_20261114` (built on Sync 13:21 UTC; Jason approved in chat). `app/lib/ServiceHealth.php`:
  each failure classified (ban 403/406/418, auth 401, quota 402/quota text, rate 429, timeout/no answer, 5xx, malformed)
  with its evidence, one open issue per host and caller (`crawler` on Railway, `site` on Bluehost) in `service_issues`.
  Stop kinds (ban, auth, quota) hold until a person marks resolved or the probe (`PROBES`: Overpass `/api/status`,
  overpass-api.de daily with exactly the refused agent) answers 200; back kinds back off 10 min doubling to 6 h and clear
  on the next answer. Pulse case `svc:<id>` (stop kinds, or a back-off 6 times / 6 h): evidence, the service's links
  (OSM: the statement, forum, FOSSGIS), Check it now (`svc_probe`), Mark resolved (`svc_resolve`); the card shows it.
  `CrawlService::plan` sends `holds`, `probes`, `osm_recent`; `report` files `issues`, `probes`, `osm_asked` and the
  `held` outcome (never a failure). `OpenPlaces::overpass` (DeepLook goes through it): overpass-api.de off, held servers
  skipped, one at a time (`GET_LOCK`), failures recorded, a spot and kind kept 30 days (was 7). Migration
  `2026-11-14_service_issues.sql` seeds the known overpass-api.de ban, so the first run probes its status once: that
  answer (Pulse case, or Railway's log line "probe overpass-api.de: …") says whether it's the agent or Railway's address.
  Contact in both agents: support@traversence.com (Jason, 2026-11-14; `ServiceHealth::CONTACT`, Railway `CRAWLER_CONTACT`).
- **Live as Release #43** (verified byte for byte on Sync 13:59 UTC). Worker 2.5 merged to `main` then (Railway redeploys; `CRAWLER_CONTACT` set on Railway). Worker 2.5: the same
  classification and holds for every API it calls (`API_HOSTS`; businesses' own websites aren't), the GET-after-406
  retry gone, overpass-api.de off (`OVERPASS_MAIN=on` only once its maintainers answer), one small query per kind
  (`[timeout:25]`), one at a time, 30-day cache per town and kind, `held` tasks. Set `CRAWLER_CONTACT` on Railway.
- Jason's later numbers (via the session manager, 13:20 UTC): a 3-month cache; a daily Overpass budget of a quarter of the
  public guidance (2,500 queries and 250 MB, crawler 1,500, site 1,000), halved while a server bans us and 14 days after.
  Built and tested as `service-health2` (Thresholds gates `osm_daily_crawler`, `osm_daily_site`, `osm_daily_mb`,
  `osm_cache_days`, `osm_ramp_days`; `ServiceHealth::osmBudget`/`osmUse`, migration `2026-11-14_osm_usage`); the worker
  already reads `osm_budget` (5 queries a run until the site sends one). **Live as Release #44** (verified on Sync 14:21
  UTC).
- **State at 14:25 UTC:** worker 2.5 on Railway since 14:01 (deployment `a18ef0bd`), runs ok (no crash). The mirrors
  still don't answer the small per-kind queries (private.coffee and kumi time out, maps.mail.ru 504): each is held for the
  rest of a run after two, and coverage stops at its budget as "held". Both database updates
  (`2026-11-14_service_issues`, `2026-11-14_osm_usage`) were still waiting to run at Sync 14:21; until they do, the site
  sends no holds, probes or budget (the worker keeps its own). Once they run, the next run's log has
  `probe overpass-api.de: …` (406: our agent is still refused; 200 with "Connected as": lifted for that address), and the
  Pulse case `svc:1` shows it. If the mirrors keep timing out, each opens a case after 6 back-offs or 6 hours: then
  consider another instance or our own (the statement's last line).
- **14:30 UTC: the database updates ran.** The plan now sends the holds (`held: overpass-api.de (Refused us (issue #1))`),
  and the first probe answered **406 Not Acceptable (nginx)** to `/api/status` with exactly the refused agent: that agent
  is still refused, even on the status page (the next probe is in a day; a 200 resolves issue #1 by itself, and the
  crawler still stays off it until `OVERPASS_MAIN=on`). To tell a name ban from an address ban for certain, one request
  with that agent from a home or office connection (research §1) is still the test.
- Seen 14:26: Common Crawl's index answered 504 once; the task reported failed and the run partial (not a crash). One
  server error marks that task failed; held only after a second. Fine as is; revisit if it repeats.
- **"Go, automatic"** (Jason, 14:53 UTC via the session manager): **live as Release #46** `service-health3_20261114`
  (verified Sync 15:21 UTC) and **worker 2.6** (merged then). Servers are settings (`service_servers`, OpenStreetMap's
  card in Outside services: add, pause, resume, each server's state); overpass-api.de is back on both lists, held by issue
  #1 until its daily probe answers 200, then it returns by itself (Pulse `svcback:<id>`, half budget for
  `osm_ramp_days`); pacing in Thresholds & gates: `osm_per_minute_crawler` / `_site` (1), the run's share spread over the
  day (`ServiceHealth::osmRun`), `paid_spread` (Google and web search even through the day, `api/crawl/usage.php`);
  Retry-After obeyed exactly, and on an Overpass 429 its status page's slot time. Worker 2.6: servers from the plan,
  the gap between queries, a job whose kinds don't all fit goes round as partial.
- **Open items, in order, for the next crawler session** (Jason, 2026-11-14 15:40 UTC: "fix the web search may make 0 next
  and other open items too"):
  1. **Web search and the Google check allowing 0: answered** (worker 2.6.1, main 3cd1082, first run 15:40 UTC):
     `web search: … today 30 of 30; at today's share` and `Google check: … used 420 of stop 900, pace 420, today 30 of
     30`. Both work: each spent its day's 30 early (the month went 213 → 243 since 2026-11-13), and every later run got 0.
     Release #44's `paid_spread` (live 14:21 UTC) spreads the day's share evenly from now on. Check tomorrow that the
     log shows a few allowed through the day; if it still reads "today 30 of 30" by morning, see what spends it
     (SecondTier's extract credits count against the same day).
  2. **Send the four approved releases W6, W9, W10/W11, W12** (§8; `handoffs/crawler-revamp-pending/`): they were never
     sent. Re-apply each `change.diff` on the live copy (the files may have moved since Sync 2026-11-13 19:58), claim,
     zip, send, one at a time.
  3. **Junk listing names** (§7.3, "first thing next" since 2026-11-13): widen `AutoImport::evaluate`'s name check, and
     send the live ones to Review (Phase 4).
  4. **The site's other outside callers onto `ServiceHealth::allowed()` / `siteFailed()`**: Weather, Roads511,
     PlaceFinder, Geocoder, SecondTier, AiClient, TravelLegs; payments last.
  5. **Maps against the tile usage policy**: attribution, a real Referer, no bulk download.
  6. Small: a single 5xx marks a task failed (held only after a second; Common Crawl 504 at 14:26); a coverage turn that
     only handed jobs back counts as "done"; "described 3 site(s) … 0 judged" (the terms-judging feature is off).
  State at 15:40 UTC: every Overpass server held (overpass-api.de by its ban, issue #1, probed daily; the mirrors backing
  off after timeouts and 504s), so coverage finds nothing until one answers; runs report ok.
- Next: the site's other outside callers onto `ServiceHealth::allowed()` / `siteFailed()` (Weather, Roads511,
  PlaceFinder, Geocoder, SecondTier, AiClient, TravelLegs; payments last), and post the OSM statement (research §2/§3).

**Jason's answers (21:20 UTC, through the session manager):**
- On 1: "Need a written statement and how to reach OpenStreetMap community so I can address, also need to verify this is
  actually blocked and why." Done: `research/osm-overpass-2026-11-13.md` (the evidence, the statement, the channel, the
  one test still to make). Until the maintainers answer, the crawler shouldn't send to overpass-api.de.
- On 3: "we need listings and our process should already address when a listing is created and how along with scoring."
  So **not** "indexing only": listings keep being made by the process as it is. Where it decides: decisions/0044 (the
  confidence-gated import), 0133 (scoring, the observations store), 0160 W1 (Jason: one source can establish a listing, a
  government page by itself, vetted continuously); code `app/lib/crawler/AutoImport.php` (`evaluate`: the gate, the
  name check) and `app/lib/ListingConfidence.php` (the score). The pending releases (W6, W9, W10/W11, W12) don't change it.
  The junk names come from a gap in that gate: its name check refuses only exact labels ("Contact Us", not "Contact Us:"),
  and nothing catches "PO Box 190", "Contact: Box 6022", "Contact Greg Trapp", "An agency within the U.S. Department of
  Labor", "CDPF Informational Webinar Slideshow", "USPS Collection Box". **First thing next:** widen `evaluate`'s name
  check (trailing punctuation; names starting "Contact", "PO Box", "Box", "An/A …"; words like webinar, slideshow,
  newsletter, form, application, PDF; drop and collection boxes) so such a find goes to Review, not live, and in Phase 4
  send the live ones already made to Review.

**Also from the 22:10 UTC logs:**
- **Common Crawl still failed** ("Invalid IPv6 URL"), now from `urljoin` on a page's link (`own_site_links`), and the run
  was "failed", so Railway showed the deploy CRASHED. Fixed in worker 2.3 on branch `claude/crawler-cc-fix` (one safe
  `join_url` for every page link; `public_host` refuses a malformed address); tested; **not merged: merging deploys it,
  so it waits for Jason's OK.**
- **Web search "this run may make 0"** every run (213 of 1000 used this month), the same pacing as Google's (§7.2): log
  the site's reason, check its daily share.

## 8. For the next session (W6 on)

The session that did Phases 0–2 and W1–W5 handed off here at about 435K context (the session manager, Jason's OK,
20:02 UTC). Everything below is in `main` and on the live site unless it says otherwise.

**Live and verified byte for byte** (each a release, approved by Jason in chat):
- Phases 0, 1, 2; W1 (#20); W2+W3 first parts (#22); W2 finished (#28, worker 2.2); W3 finished (#30);
  W5 (crawler-w5, see §6). Worker 2.1 and 2.2 on Railway; Railway's watch paths are `/workers/crawler/**`.

**In flight when this session stopped:** nothing. W5 is live and verified; the next release is W6 (below).

**Next, in order. W6, W9, W10/W11 and W12 are built and tested; Jason approved them in chat ("merge all", 2026-11-13),
to send one at a time, each after the one before is live and verified.** They're saved as patches against the live copy
of Sync 19:58 UTC (all four still apply to Sync 20:02) in `handoffs/crawler-revamp-pending/<w>/` (`change.diff`, `release.json`); each release's FAQ line is
in `faq-lines.py` (`python3 faq-lines.py <w> app/ui/faq.md`). For each: claim its files (below), start every file from
the live mirror, apply `change.diff` (`patch -p1`; re-apply by hand where a file moved), add its FAQ line, zip with its
`release.json`, `zipcheck.py`, `send.py`, PR, merge, wait for "Release #N: Live", run the sync, verify, next.
1. **W6** (`Heritage.php`): an empty Library of Congress search asked again in 30 days; a newspaper's pages are one
   source (its LCCN, `Heritage::paper`); the extraction queue moves past places it skips or fails.
2. **W9** (`PlaceFinder.php`): Wikipedia's summary kept only when `Landmarks::wikiAllowed`.
3. **W10/W11** (`HowGuides.php`): never drafted from a cite-only or Ignore source; Publish refuses a step that reads as
   its source wrote it unless the source's licence is open (`HowGuides::copied`; the page's words kept as
   `sources[].said`).
4. **W12** (`crawler/CrawlQueue.php`, `Learning.php`): searched gaps (`sit:…`) leased before the sweep; a town's
   searches count for its cluster's gaps.
5. **W4, W8:** nothing more (W4's starvation went with Phase 2's shares; Find it shares W1's gate). W13 (stories) has no
   crawler work yet.
6. Then **Phase 4** (the old data; notes below) and **Phase 5** (proof: §3).

**Check-out (decisions/0161, live in `main` since 20:02 UTC):** pull `main`, then claim each release's files when you
start writing them: `python3 tools/page-audit/checkout.py claim <path>[::<function>] --add|--change --why "…"` (add
claims are shared; change claims are exclusive per function; a claim lasts one release; `send.py` claims what you
missed). The zip check now also blocks another session's change claim and a broken live caller. See CLAUDE.md, "Several
sessions at once".

**Phase 4 (the old data), notes for its passes:**
- Listings closed by their own website alone before W2 (the `closed` meta with `by` 0 and a note starting "Its own
  website says") become `closed_signal` ("Possibly closed") unless a person confirmed them.
- `website_dead` since 2026-10 (B5's damage): re-check each and put back those that load.
- Sources wiped by an empty read (B6): `site-audit/crawler-snapshot.md` → place store → `sources_read_empty`.
- Heritage facts: `Heritage::retier()` re-tiers them, a batch an hour; facts vetted by two pages of one newspaper
  (before W6) are re-checked with `Heritage::paper`.
- Landmarks and places stored with a Wikipedia extract while Wikipedia's rule is cite only (before W9): clear the text,
  keep the title and link.
- Identity jobs set aside after starved runs: `CrawlService::retry($pdo, 'identity')`, or the console's Retry failed.
- Junk listing names (§7.3).
- The numbers for every pass are in `site-audit/crawler-snapshot.md` (daily, with change since the first day).

**Known issues:**
- Coverage (§7.1).
- "described 3 site(s) waiting for a call; 0 judged" every run: 406 sites wait after describe, and the terms judging
  feature is off, so few are decided.
- A coverage turn that only handed jobs back still counts as "done" in the run report (it got work).

**How the work went (useful):**
- A build is a zip sent with `tools/page-audit/send.py`, merged, and it publishes itself once staging's checks pass.
  The site takes zips about every 20 minutes (:17, :38, :57). Ask Jason in chat before merging each one.
- The delivery log (`deliveries/README.md`, sometimes a `deliveries/*.json`) conflicts on almost every PR: take
  `main`'s copy of each conflicted `deliveries/` file, run `deliveries.py verify --mirror …`, and check every
  `deliveries/*.json` still parses before pushing (a conflict marker got pushed once, 2026-11-13).
- Just before merging, fetch the mirror again: a release that went live meanwhile can have changed `faq.md`; rebuild it.
- Only the session whose release went live starts the sync; list the runs first.
- Railway logs: the proxy-injected credential reaches `backboard.railway.com` (GraphQL: `deployments`,
  `deploymentLogs`, project gracious-sparkle `a445f0be…`, service traversence `8d254c0d…`).
- The zip check's PHP 8.1 test reads ": never" in a string as the `never` type: word messages differently.
- The worker deploys on merge; its changes go after the site part they need is live.
- Testing: `apt-get install mariadb-server` (after `apt-get update`); start `mariadbd --user=mysql` in the background;
  drive the real classes with a stand-in `Database.php`; run migrations twice. The worker imports cleanly for unit
  tests (`python3 -I`).
