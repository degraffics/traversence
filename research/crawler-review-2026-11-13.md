# The crawler, start to finish: a review (2026-11-13)

Jason, 2026-11-13: "this concerns me. We've spent a ton of time on this crawler and it seems like we keep having misses.
Let's review the crawler start to finish and how it is deployed." The case: "blue hills" near St. Johns showed a propane
exchange, not Blue Hills Environmental (garbage collection, 140 W Cleveland St, St. Johns).

This review was done by reading the live code (the mirror at Sync 2026-11-13 13:34 UTC, and `workers/crawler/` on
`main`). It ran nothing, and it couldn't read Railway's logs or the database. Each finding names its file and line, and
the main ones were checked by hand.

## The short answer

The crawler isn't broken in one place. It leaks at every stage, and **nothing reports the leaks**, so each miss looks like
a one-off:

1. **The worker rarely gets to its main job.** Each run does 11 other stages first, and the jobs that find businesses
   run last, usually after the run's time is gone.
2. **Most finds can't become listings by themselves.** A place seen on one site fails the "real" check and waits for a
   second look or a person. Some finds that pass the check can't publish, and then appear in no list at all.
3. **Search hides what we do have.** It reads business-name words as kinds of business, so a name search can show an
   unrelated kind and never say "nothing found".
4. **It's all silent.** Railway shows every run as succeeded. Starved jobs, stuck finds and wrong readings are written
   nowhere a person looks.

## 1. Deployment: sound

- **Railway:** project gracious-sparkle, service **traversence**; repo `degraffics/traversence`, **branch `main`**, root
  `workers/crawler`; auto-deploys on push; cron every 5 minutes; every run shows "succeeded" (Jason's screenshots,
  2026-11-13).
- The worker on `main` is the newest (the Common Crawl change of 2026-11-12). `workers/crawler/README.md` still says the
  branch is `claude/optimistic-cannon-9ifg1c`: out of date, corrected with this review.
- **Why "succeeded" means little:** the worker always exits 0 (`crawler.py` 2873–2882). A stage that fails or runs out of
  time is only a log line.

## 2. The worker: the jobs starve

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

## 3. The site's intake: finds that never become listings

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

## 4. Search: names read as kinds

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

## 5. What to fix, in order

Each is a release.

1. **See the leaks first.** A crawler report on the site, from what each run sends back:
   - each stage's time and count;
   - jobs reached or starved;
   - finds by where they stand (published, waiting for a second look, in Review, stuck);
   - towns and their kinds, with what each found.

   It goes in Pulse when something stalls. The worker reports a run that starved its jobs as a problem, not a success.
2. **The worker's order and budgets:**
   - the crawl jobs first, with their own share of the run;
   - a fixed share each for the other stages, so one slow call can't eat the run;
   - a starved job doesn't spend a try;
   - partial coverage jobs go back to the queue;
   - failed town jobs retry like coverage jobs;
   - the websites of a job's places get their own fetch budget;
   - add waste, garbage and utilities to the town kinds.
3. **Intake:**
   - a find that can't publish goes to Review with what's missing, or the town and ZIP are filled in from its map point;
   - the place store skips what's already staged or rejected before its cap, and works down the list;
   - a find with no kind is staged for a person, not dropped;
   - the classifier learns garbage, sanitation and refuse;
   - licence rules apply when the place store creates a listing;
   - Send the crawler uses the words, keeps a record and reports back.
4. **Search:**
   - name words no longer count toward kinds;
   - a name is read first: when it matches nothing, the page says "Nothing named … near …", with an unrelated kind
     offered at most as a separate "did you mean";
   - "includes" only when the added kind added results;
   - `listed()` counts only public listings;
   - the debug switch goes.
5. **A decision for Jason:** should a place listed by a government source we read (a town, county or state page, with
   its address or phone) count as real by itself? Today it needs a second source. Public bodies' own lists are usually
   the best source a small town has.
