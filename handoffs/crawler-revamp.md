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

- **Phase 0** (2026-11-13): built as `crawler-phase0_20261113.zip` on Sync 14:41 UTC; Jason confirmed it ships as
  **Publish now (urgent)**. Site guards only (no worker change, no migration): `Refresh::lease` (abandoned leases not
  tried, `crawler_refresh_abandoned` counted), `IdentityJobs::lease` (expired leases keep their tries),
  `SourceIndex::store` (each page read replaces only its own places; a read of nothing hands the source back),
  `Guides` (`draftFailed`, `keepNewer`), `Landmarks::finish` (never `waiting`, only states loaded). The snapshot is the
  health report's `crawler` section, written daily to `site-audit/crawler-snapshot.md` (history in
  `site-audit/results/crawler-snapshot.json`) by `tools/page-audit/towns.mjs`.

## 7. Open

- The Crawler console's place in the admin (Insights, or System Configuration).
