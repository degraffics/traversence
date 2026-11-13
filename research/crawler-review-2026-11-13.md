# The crawler, start to finish: the full vetting (2026-11-13)

Jason, 2026-11-13: "We've spent a ton of time on this crawler and it seems like we keep having misses. Let's review the
crawler start to finish and how it is deployed." Then: "I want the crawler's full scope vetted."

**How it was done:**
- Six read-throughs of the live code: the mirror at Sync 2026-11-13 13:34 UTC, and `workers/crawler/` on `main`.
  1. finding businesses;
  2. the source register and reading;
  3. keeping listings current, bulk loads and map points;
  4. learning, demand and the admin;
  5. guides and research;
  6. the rules across all of it.
- The serious findings were then checked by hand in the code, and are marked **(checked)**.
- Nothing was run. Railway's logs and the database weren't read, so counts and volumes are unknown. Where a finding
  depends on the data it says so.

## The short answer

The crawler's pieces are each reasonable. Together they fail in four ways:

1. **One 5-minute run is asked to do everything, in a fixed order, in 240 seconds.** The stages run in this order:
   1. bulk loads;
   2. the second look;
   3. targets;
   4. map points;
   5. identities;
   6. refresh;
   7. the Google check;
   8. learning;
   9. heritage;
   10. how-to guides;
   11. Common Crawl;
   12. reading a source;
   13. the crawl jobs.

   The later ones are starved most runs. Starving is not harmless: work is taken and then abandoned, and the site reads
   the silence as a result. It counts failed tries, finds "unreachable" websites and takes them off listings, and wipes
   a source's places.
2. **Finds rarely become listings by themselves.** One source isn't enough. Some ready finds can't publish and then sit
   in no list. A site's list is staged only 40 deep. Finds with no kind are dropped.
3. **Some public pages skip the rules.** The place store's results, the OpenStreetMap layer and the heritage tiering
   miss checks that the decisions on tribal nations' land, confidential addresses and licences require.
4. **Nothing reports any of it.** Railway shows every run as succeeded, errors go to logs no one reads, and the admin has
   no view of the town queue, starved runs, stuck finds or spend per feature.

## Fix order, by risk

Each is a release. Highest risk first: public exposure, then damage to good data, then money, then misses.

### A. Public exposure: before anything else

1. **"Named on pages we read" checks nothing** (checked: `SourceIndex::search`, 369–452). It shows name, address, phone
   and directions from any page we read, with no protected-place check, no nations check and no confidential-address
   check, so a shelter's address, or a sacred site named on a page, can show in search.
   - **Fix:** the same guards as listings and landmarks (`Landmarks::isProtected` with the peoples loaded,
     `Nations::at`, `ListingSafety::isConfidential`), plus a person-name filter.
2. **Heritage tiering doesn't know the nations' peoples** (checked: `Heritage::extract` calls `isProtected` without
   `Landmarks::loadPeoples`, and `NATIVE` has no peoples' names). A "Hopi cemetery" reads as a town cemetery and is
   marked public, with its map point.
   - **Fix:** load the peoples wherever `isProtected` is called, best inside `isProtected` itself.
   - **Then:** re-tier the heritage facts already made.
3. **The OpenStreetMap layer has no nations check** (`OpenPlaces.php` 205). Trailheads, reserves and viewpoints on a
   nation's land show with no check.
4. **Held landmarks in guides** (`GuideLinks.php` 205–207). Decided (Jason, 2026-11-13): "we can name a place, but we
   can not show data that would allow unauthorized access or direction to said place". So a guide may name a held place
   and tell its story; the fix is to make sure nothing about it shows the way in (map point or pin, address,
   directions, route, trailhead, access details, or a link to a page with them). The same rule applies to the place
   store's results (A1) and the OpenStreetMap layer (A3).

### B. Damage to good data

5. **Abandoned website checks take websites off listings** (checked: the worker breaks out of `refresh_listings` without
   reporting, and `Refresh::lease` counts a lease never reported as "unreachable"; on a second, `website_dead` and the
   website is taken off). The same pattern spends identity jobs' tries.
   - **Fix:** a lease never reported is "not tried", never "unreachable".
   - **Then:** review the websites taken off since this began.
6. **A starved source read wipes the source's places** (checked: `next()` marks it read when it's handed out; the worker
   sends nothing back when out of time; `store()` then deletes every place from that host).
   - **Fix:** an empty read changes nothing, and the source is handed back.
   - **Then:** read the wiped sources again.
7. **A failed guide draft wipes its own record** (`Guides.php` 1093): the "kept down" mark, so a guide the team took
   down can go live again; the hash, so Claude's text looks like a person's; and the passes already paid for.
8. **A person's guide edit can be overwritten** while a draft is being written (no version check, `Guides.php` 1015 to
   1123; triage, 676).
9. **The monthly landmark load deletes held rows** (checked: `Landmarks::finish` 339 deletes `waiting` rows missing
   from the month's file), against "Never delete the data".
10. **One website's word can close a listing** (`Refresh.php` 150). 0133 asks for three sources to agree.

### C. Licence and the rules on sources

11. **OpenStreetMap's phone, address and hours are copied into our listings** (the worker's `candidate()` and the
    intake), and OpenStreetMap counts as an independent source. 0065 says it's its own layer, never copied.
12. **The place store ignores each source's use rule.** Places from a site whose rule is cite only, pointer or evidence
    are stored, shown and made into listings (`insertFacts`, `search`, `stageNew`; only `link()` checks `mayStore`). A
    site changed to cite only keeps its places shown. Unknown licences default to "facts".
13. **Wikipedia "cite only" is bypassed** (`PlaceFinder::summary` stores extracts without `wikiAllowed`; shown in
    search and on tags).
14. **How-to guide steps are copied word for word** from .edu and state pages with no check against the source register.
15. **Fetching without robots.txt:** the site's own fetches for Claude's citations and scans (`SecondTier::fetch`), and
    Tavily reads of pages that answered 429. The worker fetches robots.txt with Python's default agent and unchecked
    redirects, and treats an unreachable robots.txt as allowed.

### D. Money (no income, no expense)

16. **Claude's failures after a paid answer are recorded at $0** (`AiClient` 241–244, `SecondTier` 74, 232), so every
    cap undercounts.
17. **Tavily:** Extract counts at most 10 a run while 20 are allowed, its credits aren't counted against the search plan,
    and there's no monthly Tavily cap. "Settle a find" goes straight to Claude with web search, skipping 0157's cheaper
    order (Tavily, then a quoted read).
18. **No guard on Brave or Railway minutes**, and several always-on Claude features (topic research, live events, guide
    finds, heritage) sit outside CLAUDE.md's stated exception. **Decide** whether they belong in it.

### E. The crawler's own working: the cause of most misses

19. **One run, one budget, a fixed order.** Give each stage its own share of time and fetches, put finding businesses
    first, and never take work you can't do (lease after checking the clock). A starved job keeps its tries. The run
    reports what it did, stage by stage.
20. **Bulk loads take whole runs.** Run them on their own schedule, not in place of the 5-minute run. NPI is off, but its
    old records still count as a trusted source.
21. **Town jobs:** a searched town is built out only once ever (checked: `fromSearch`, `COUNT(*) === 0`). Failed town
    jobs never retry, and town jobs filling the hand-out skip the queue's own retries and lease resets (checked). A
    partial town job can loop. The town kinds miss the kind searched (towing, plumber, garbage).
22. **Demand never reaches a named business.** Nothing looks for a business by name. Send the crawler ignores its words.
    Situation gaps starve behind coverage jobs and only count cluster-scoped searches.
23. **Intake:**
    - ready finds that can't publish are stuck in no list (checked);
    - the place store is staged 40 deep in the same order;
    - finds with no kind are dropped (checked; "garbage" isn't a kind word);
    - one-source finds wait for a person;
    - the source register leaves sites "waiting" forever when their home page failed once;
    - "Visit website" links land on the register instead of filling in the business's website.
24. **Keeping listings current:**
    - changed phones and hours are never caught;
    - listings without a website are only checked through Google;
    - live duplicates are never found;
    - listings without a map point are invisible in every "near" search;
    - two queues (geocoding, refresh) can stall on rows they skip without marking.
25. **Guides and research:**
    - heritage and how-to guides starve;
    - a Library of Congress search with nothing is not asked again for a year;
    - the extraction queue can stall;
    - failed on-demand guides are never retried, though the visitor is told they will be;
    - "first look" labels are lost on any rewrite.
26. **Search:**
    - name words are counted as kinds (my bug from 0157, checked);
    - a name isn't read first;
    - "includes" shows when the kind is the only result;
    - `listed()` counts hidden listings;
    - a debug switch is still live.

### F. Seeing it

27. **A crawler report and admin views:**
    - what each run did (each stage's time, counts, starved or not);
    - the town queue (each town, its kinds, found, failed, requeue);
    - finds by where they stand (published, second look, Review, stuck);
    - the source register's waiting reasons;
    - spend per feature and per service;
    - pause and resume from the admin, not only `.env`.

    Stalls go to Pulse, and a starved run is reported as a problem, not a success. The false "crawler has stopped"
    alert (it watches only the coverage queue) is fixed with it.

**Suggested order of releases:**
1. A (exposure);
2. B5, B6 and B9 (data damage);
3. F with E19 (seeing it, and the run's order and budgets: they're one change to the worker and its report);
4. the rest of B;
5. C;
6. D;
7. E20–E26.

Decide first: A4 (held landmarks in guides), B10 (one source closing a listing), D18 (which Claude features count as
the exception) and part 1's question (a government page as a source that's enough by itself).

## Part 1. Finding businesses (the first review, the "blue hills" case)

### 1.1 Deployment: sound

- **Railway:** project gracious-sparkle, service **traversence**; repo `degraffics/traversence`, **branch `main`**, root
  `workers/crawler`; auto-deploys on push; cron every 5 minutes; every run shows "succeeded" (Jason's screenshots,
  2026-11-13).
- The worker on `main` is the newest (the Common Crawl change of 2026-11-12). `workers/crawler/README.md` still says the
  branch is `claude/optimistic-cannon-9ifg1c`: out of date, corrected with this review.
- **Why "succeeded" means little:** the worker always exits 0 (`crawler.py` 2873–2882). A stage that fails or runs out of
  time is only a log line.

### 1.2 The worker: the jobs starve

**Order of a run** (`crawler.py` `run()`, 2642–2762):
1. the bulk loads (NPI, IRS, Recreation.gov, landmarks), when due;
2. the second look;
3. search targets;
4. map points;
5. identities;
6. refresh;
7. the Google check;
8. learning;
9. heritage;
10. guides;
11. Common Crawl;
12. reading a source;
13. **then** the crawl jobs (`jobs.php`).

**The run's limit is 240 seconds from its start** (`TIME_BUDGET`, 97). The stages before the jobs have budgets adding to
about 1,000 seconds. One geocode call alone can wait 250 seconds, and one Overpass call 125 seconds per mirror. When the
time is gone, the job loop stops at its first job: "out of time, left for its lease to return it".

**Each starved run still costs the jobs a try.** Leasing a job adds an attempt (`CrawlQueue.php` 120, `TownCrawl.php`
103). After 3 attempts:
- a coverage job is retried after 6 hours, 1 day and 3 days, then waits for a person;
- **a town job never retries by itself** (`TownCrawl.php` 100). Searching the town again won't requeue it: `fromSearch`
  queues a town only once in 30 days.

**Websites go unchecked.** `MAX_FETCHES` (60) is shared by every stage, and the earlier stages spend it. When a job gets
to a place's own website, the budget is often gone. The place then has one source, not two, and goes to review instead of
publishing.

**Other holes:**
- Coverage jobs that run out of time mid-way are marked **done** (`CrawlQueue::complete` ignores `partial`).
- At most 120 OpenStreetMap places and 25 candidates per job.
- A town whose places have no ZIP points finds nothing (`crawler.py` 511–514).
- `CRAWL_QUEUE=off` is checked only after every other stage has run.
- Missing keys turn stages off with only a log line.

**No stage looks for a business by name.** Town jobs ask OpenStreetMap for **kinds** (`TownCrawl::KINDS`), and the list
has no waste, garbage or utilities kind, so a trash hauler is never asked for. Lookups by name exist only for listings we
already have.

### 1.3 The site's intake: finds that never become listings

Every way in goes through `IntakeStager::stage()` and the gate `AutoImport::evaluate()`. The gate has four checks: real,
complete, appropriate and unique.

- **"Real" needs two independent sources, the place's own website, or the NPI registry.** Many finds have one:
  - a place named on one page we read is one host (`SourceIndex.php` 559–564);
  - an OpenStreetMap find whose website wasn't fetched is also one.

  These wait for the second look, which only runs when the worker calls `verify.php`, and only scans the top 500 staged
  rows. Then they go to Review ("Only one website lists it") for a person.
- **Ready but invisible.** The gate passes a find with a map point and no street, city or state (`AutoImport.php` 127).
  The publish step (`IntakeStager::approve`, 807–819) refuses it, and the refusal is swallowed (`AutoImport.php`
  602–604: "a person finishes it in Listing Intake"). But the row still counts as eligible, so it's never sent to Review
  or the second look. **It is retried and fails every 15 minutes, in no list.** OpenStreetMap point-only finds are the
  usual case.
- **The place store stages only the first 40 places of a site, in the same order every time** (`SourceIndex::stageNew`
  537–546). The cap counts places already staged or rejected, so places 41 and after on a big list are never staged.
- **No kind, no staging.** `stageNew` takes only places with a kind (`category_id IS NOT NULL`). The kind classifier's
  Utilities words have waste, trash and recycling, but not garbage, sanitation, refuse or environmental
  (`CategoryClassifier.php` 76).
- **Licence rules aren't applied** when a place store fact becomes a listing (they are when it adds to one).
- **Send the crawler ignores the words searched** (`$words` is unused). It says "new listings show within the hour",
  which single-source finds don't.

**Blue Hills Environmental, as an example:**
1. Its page has to be on a site we read.
2. Its kind has to be worked out, or it's never staged (likely stall 1).
3. Then it needs a second source with the same phone or house number, or it waits in Review (likely stall 2).

### 1.4 Search: names read as kinds

- **The cause** (introduced by decision 0157's third delivery): the site's word list counts every word of every business
  name as a clue to that business's kind (`LocalWords::build`, `isName` false for listings and the place store; 194,
  206). Many propane listings are named "Blue Rhino…", so "blue" became Propane Tank Kiosks.
  - The directory search then adds every listing of a kind the words seem to mean (`search_listings.php` 300–327),
    **without first checking whether the words are a name**. Decision 0157 §4.3 says a name comes first.
  - So "blue hills" showed a propane exchange as "includes Propane Tank Kiosks". "Nothing found near St. Johns" (with
    Tell us and Send the crawler) never appeared.
- **What it does to other searches:**
  - a found kind turns off the loose retry, so a misspelled name gets no second try;
  - "includes" is printed even when the added kind is the only result;
  - the read-page finds ("Named on pages we read") use the same kind guess;
  - `SourceIndex::listed()` counts hidden or suppressed listings as listed, so a place can be hidden in both lists;
  - a debug switch (`?debug=tv2f`) is still live.
- **A public "Blue Hills Environmental" would still rank first by name.** The bigger question is whether it's listed at
  all (§3).


## Part 2. The source register and reading sources

- **Self-judging works for addresses and descriptions.** Chambers, tourism sites and organizations decide only when
  their home page links terms and the source-terms feature is on at that moment. A failed describe, or one while the
  feature was off, leaves a site "waiting" for good (`describe_sites` sends empty, `about_at` set, never offered again).
- **One source a run, at the end of the run.** A starved read wipes its places (B6). Pages read before and not reached
  this time lose their places (the sitemap excludes known pages, against 0157 §8.2's "kept").
- **Pages that yield nothing:**
  - places with no 10-digit phone and no street number (PO boxes, "3 mi N of…", rural routes);
  - a name more than 3 lines above its phone or address;
  - script-drawn pages (only through Tavily Extract);
  - multi-page scans, which Textract fails and retries every 90 days;
  - events (their dates aren't kept);
  - facts from URLs over 500 characters, which are dropped.
- **Not built from 0157:**
  - Claude reading what the rules couldn't (§2 step 8: `read_how 'ai'` is dead);
  - the second tier's Tavily search when nothing is found (§3);
  - Claude reading a search it can't parse (§4.6).
- **Two page readers:** Listing Intake's `SiteFetcher` and `ListingExtractor` don't use the page log, the place store or
  the register's rules.
- **The admin:**
  - "Crawler sources" in Insights lists intake runs, not the register; the register is the bridged `/admin/sources.php`,
    with no paging;
  - not shown: why a site waits, re-describe or judge-now buttons, per-source pages and facts, wiped reads, spend per
    site, failed scans.

## Part 3. Keeping listings current, bulk loads, map points, the OpenStreetMap layer

- **Refresh** re-reads a listing's own website every 30 or 90 days. It's undercut by B5, and by bad website values
  without "https://", which can take every slot. It only fills empty fields, and listings without a website never
  refresh.
- **The Google check** (closure only; 3,000 a month, 90% cap): moved, not-found and duplicate checks by place ID (0133)
  aren't built. Listings it skips without marking can take every slot.
- **Map points (Census, free):** rows skipped without marking can stall the queue. Mileposts are kept for a lookup that
  doesn't exist. A listing with no point is invisible in every "near" search.
- **Merges:** new finds are merged into live listings; live duplicates aren't found (they rely on visitors' reports and
  a manual merge). 0133's "same place ID + same phone" merge isn't built. A row that throws is picked first on every
  pass.
- **Bulk loads (NPI off, IRS, Recreation.gov, landmarks):** each takes a whole run when due, and NPI and Recreation.gov
  allow 1,500 seconds. NPI has no guard against an empty load.
- **Invisible in the admin:** websites taken off and listings closed automatically, abandoned leases, overdue refresh
  and Google checks, live duplicates, listings missing from search for lack of a point, OpenStreetMap values in
  listings, stale NPI records still confirming.

## Part 4. Learning, demand and the admin

- **Learning** runs only when the worker calls it, so a paused or dead worker stops learning too. Situation gaps count
  only cluster-scoped searches, starve behind coverage jobs (priority = searches against priority = population), stay
  "tier 1" while their job waits, and the "none" status is never set.
- **Coverage gaps** are queued only by hand, a geo-hub at a time.
- **Search targets** (0066): the README's "new kinds" (`CrawlTargets::queueKind`) doesn't exist; targets never use web
  search; there's no retry or requeue.
- **Clusters:**
  - merging repoints everything except crawl jobs and situation gaps, which are then orphaned and take hand-out slots;
  - adding a ZIP to a cluster doesn't move the listings already there;
  - two ways of finding a ZIP's cluster can disagree.
- **The admin:**
  - **Pages:** Crawler overview, Review, Published, Sources, Cluster tools, Listing Intake, Search demand, Search
    learning, misses, oversight, the hub, Map points.
  - **Pulse:** failed crawl jobs (Retry), "stopped" (false alarms), source failures, terms due, suggestions.
  - **Missing:** the town queue, requeue for town jobs and targets, starved or stuck or orphaned jobs, per-town
    coverage, stuck situation gaps, pause from the admin. The overview's "done" counts only the coverage queue.
  - **Review for one person:** realistic by design (only finds that are out of options), but town build-outs send
    their many website-less finds to it, and it decodes every staged row on each admin page load.

## Part 5. Guides, stories, research, dossiers and heritage

- **Heritage (Library of Congress) and how-to guides** run 8th and 9th in each run and starve. They lease before
  checking the clock, so the work is locked for nothing, and the guide's gathering then goes ahead without them,
  writing from the paid brief instead of the free sources first.
- **How-to guides look sourceless when the run's searches are spent.** They're marked "No official page found" for 30
  days.
- **A Library of Congress search with no results** isn't asked again for 365 days. The heritage extraction queue can
  stall on places it skips without marking.
- **A2, B7 and B8 above.** Also:
  - the "story only" tier covers sacred, burial and archaeological sites, but not private property or sensitive
    wildlife (0133's table);
  - each Library of Congress page counts as an independent source, and one OCR'd newspaper page is enough to catalog a
    fact;
  - per-item rights aren't read;
  - old Wikipedia text can still reach Claude through the dossier.
- **Failed on-demand guides are never retried; "first look" labels are lost on any rewrite.** Suggested journeys can't
  be edited, pinned or hidden: the "a person's version stays" path is unreachable.
- **Invisible:** on-demand requests and their spend (0147 says Insights → Guides shows them; nothing does), stalled
  queues, errors, and anything but each scheduled job's last output line.

## Part 6. The rules across everything

- **Nations (0066):** A1–A4 above. Everything else public goes through `visibleSql()`/`visible()`, the pins move, and
  the second tier skips nations' land and their sites.
- **Licence:** C11–C15 above. Done well: `mayStore` on adding facts to a listing; Wikipedia gated in the landmark load,
  Identity and Dossier; Google keeps only the place ID; Common Crawl only for sources we read.
- **Politeness:** the worker honours robots.txt on every fetch and redirect, Crawl-delay, 1.5 s between fetches,
  private addresses blocked, and never retries 401/403/451 another way. Gaps are in C15.
- **Privacy:**
  - only organisations are asked of NPI, and Overpass gets a rounded point;
  - gaps: the place store can show a person's name and phone, and the worker takes the first `mailto:` on a site as the
    business's email, which may be a person's own.
- **Money:** D16–D18 above. Caps are checked before spending for search (Tavily/Brave, 90% and a daily pace), Google
  (90%), Claude (the $25 overall cap, each feature's switch and limits) and Textract (off by default).
