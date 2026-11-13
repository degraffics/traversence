# The crawler revamp: plan and hand-off

Jason, 2026-11-13: "We need to fix issues but we also need to fix the workflow management so we quit failing on Railway.
We need to initialize a revamp of the entire crawler management system. This revamp will fix the issue by deploying the
crawler by workflow and properly addressing each concern, conforming to our new standards, and assimilating old data into
current fresh and accurate data."

For the Claude session that carries the revamp. It was written by the session that vetted the crawler. Read this, then:
- `decisions/0160` (the design: one crawler service, configured by each workflow's capture tasks; the 13 workflows
  W1–W13 start to finish);
- `research/crawler-review-2026-11-13.md` (every defect, with file and line, and fix numbers A1–F27);
- `decisions/0133` (the rule book: curators, not controllers; protected places' three levels; licence decides the use).

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

- **Phase 3, W3 finished** (sent, waiting for Jason's OK): `crawler-w3b_20261113.zip`, built on Sync 19:39 UTC. Listing
  Intake's crawl (`api/admin/intake/crawl.php`) now hands what it read to `SourceIndex::keepRead`: its pages in the page
  log, the site on the register (`Sources::note`, judged like any), and its places in the place store where the use rule
  allows facts and the site isn't marked Ignore (`storeMore(..., 'intake', false)`: Listing Intake stages its own finds).
  Kept synchronous on purpose (the person sees the result at once), rather than `read_page` requests. Left: the other
  SiteFetcher callers (DeepLook, Dossier, GuideLinks, Rebuild) read businesses' own sites and don't log their pages yet.

## 7. Open

- Nothing waiting on Jason. Worker 2.1 merged with his OK (2026-11-13), and Railway's watch paths set to
  `/workers/crawler/**` (he asked), so a release commit no longer redeploys the worker.

## 8. For the next session (from W4 on)

The session that did Phases 0 to 2, W1, W2 and W3 handed off here (CLAUDE.md: a long build moves to a fresh session per
phase). Start from this file, `decisions/0160` and the review; everything below is in `main` and on the live site unless
it says otherwise.

**Left in Phase 3, in order:**
1. **W2, the rest:** a changed phone or hours on a listing's own website is never caught (the refresh only fills empty
   fields: compare and send a change to Review, or to the identity record as a newer source); live duplicates (0133's
   "same place ID + same phone" merge, offered in Review); listings without a website are only checked through Google.
2. **W3, the rest:** Listing Intake's two page readers (`SiteFetcher`, `ListingExtractor`) don't use the page log, the
   place store or the register's rules: move them onto `read_page` requests (`CrawlService::request`, the worker's
   `do_requests`).
3. **W4, W5, W8 (guides):** heritage and how-to now get their share of each run (the plan), so the starvation is gone;
   still to do: failed on-demand guides are never retried though the visitor is told they will be (`OnDemand`); Find it.
   "First look" labels now survive a rewrite (Phase 0, `Guides::compose`).
4. **W6:** a Library of Congress search with nothing isn't asked again for 365 days; one newspaper page counts as an
   independent source (count each paper once); the heritage extraction queue can stall on rows it skips.
5. **W9:** C13, `PlaceFinder::summary` stores Wikipedia extracts without `wikiAllowed` (cite only).
6. **W10, W11:** C14, how-to steps copied word for word from .edu and state pages with no check against the register.
7. **W12:** demand that reaches the kind searched (TownCrawl `want:` jobs exist now: wire situation gaps to them), and
   situation gaps starving behind coverage jobs.
Each move also: its capture tasks in `crawl_capture_tasks` (a migration, INSERT IGNORE), its view in the console
(`app/admin/crawler-console.php` draws every workflow with tasks, spend or requests), and its old stage switched off.

**Phase 4 (the old data), notes for its passes:**
- Listings closed by their own website alone before W2 (the `closed` meta with `by` 0 and a note starting "Its own
  website says") become `closed_signal` ("Possibly closed") unless a person confirmed them.
- `website_dead` since 2026-10 (B5's damage): re-check each and put back those that load.
- Sources wiped by an empty read (B6): `site-audit/crawler-snapshot.md` → place store → `sources_read_empty`.
- Heritage facts: `Heritage::retier()` is already re-tiering them, a batch an hour.
- Identity jobs set aside after starved runs: `CrawlService::retry($pdo, 'identity')`, or the console's Retry failed.
- The numbers for every pass are in `site-audit/crawler-snapshot.md` (daily, with change since the first day).

**How the work went (useful):**
- A build is a zip sent with `tools/page-audit/send.py`, merged, and it publishes itself once staging's checks pass
  (Release #9). Ask Jason in chat before merging each one.
- `deliveries/README.md` conflicts on almost every PR: take `main`'s copy and run `deliveries.py verify --mirror …`.
- The zip check blocks a file another pending zip carries (`faq.md` mostly): wait for it to go live and its sync.
- The zip check's PHP 8.1 test reads ": never" in a string as the `never` type: word messages differently.
- The worker deploys on merge; its changes go after the site part they need is live. Contract 2 falls back to the
  fixed order when `service.php` is missing or not ready.
- Testing: `apt-get install mariadb-server` works here; run the migration twice, and drive the real classes with a
  stand-in `Database.php` (see the PRs for Phases 1, 2 and W1). The worker imports cleanly for unit tests
  (`python3 -I`, `WEB_FIXTURE`; stub `public_host`, the sandbox has no DNS).
