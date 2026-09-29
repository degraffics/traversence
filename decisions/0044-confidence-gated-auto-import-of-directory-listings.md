# ADR 0044: Confidence-Gated Auto-Import of Directory Listings

**Status:** Accepted (2026-09-29). Amends `decisions/0005` for crawled **directory listings** only.

## Context

`decisions/0005` requires an admin sign-off before any automated output enters the permanent graph,
and `decisions/0021` made a review queue a prerequisite for turning the crawler on. That queue now
exists (Admin → Listing Intake: every crawled candidate is staged with a confidence score and an
existing-listing match, then approved, merged or rejected).

`decisions/0043` gives every place page category-driven sections, above all **Support & wellness**
(health care, mental health and recovery, food and basic needs, family and youth, seniors and veterans,
community and wellness). Those categories are the natural funnel into the Directory: building a guide
tells the crawler which listings a place should have, and the gaps become crawl targets. The pilot's
coverage is thin (Whiteriver: 9 support listings), so requiring a human sign-off on every find would
leave most places sparse.

The platform already has two public safety nets: **claiming** (owners correct their own listing,
`decisions/0015`) and **listing reports** (permanently closed, moved, hours changed, cluster mismatch,
other).

## Decision

**Crawled directory listings may be published automatically when confidence is high, as long as they
are neither inappropriate nor made up.** Owners' claims and public reports catch the rest. Guide stories
and other System-Created Content keep `decisions/0005`'s sign-off, unchanged.

**Guardrails: auto-import happens only when all of these hold.**

1. **Not made up.** The candidate is either from an **official registry** (a government list of health
   facilities, libraries, schools and similar) or **corroborated by at least two independent sources**
   (e.g. its own website plus a directory or registry). A single mention in text is never enough.
2. **Confidence at or above the threshold** (starting value 85 of 100), built from: source authority,
   corroboration, an address inside the place's ZIPs, completeness (phone, hours), and signs it is still
   operating.
3. **Not inappropriate.** It passes the content-safety check of the `decisions/0005` amendment, and its
   category is not on the manual-review list (adult content, weapons, and anything else that list names).
4. **Not a duplicate.** No existing listing matches it (composite hash, phone, or name at a nearby
   address). A match is always offered as a **merge for review**, never auto-applied, so claimed and
   locked fields stay protected (`decisions/0012`).

Anything below the threshold goes to Listing Intake as today, sorted by confidence. High-confidence items
that fail a guardrail get a one-click **quick approve** batch there.

**Confidential-address categories.** Domestic-violence shelters, some recovery residences, and
practitioners working from home keep their locations private for safety. Listings in those categories
show a **phone or hotline only**, never an address, map pin, or directions, whether auto-imported or
approved by hand. This applies the "never pinpoint sacred or restricted sites" rule (`decisions/0043`) to
people's safety.

**Auto-imported listings are marked.** Status is `unclaimed`, with a lower starting trust score than
reviewed listings (`TrustScore`). Each carries a visible "Is this yours? Claim it / Report a problem" and a
record of the sources that justified it. Admins get a list of recent auto-imports to spot-check.

## Consequences

Coverage can grow as fast as the crawler finds corroborated places, with Support & wellness first. The
cost of an occasional error moves from "blocked before publishing" to "fixed after publishing" through
claims, reports and admin spot-checks. That is acceptable for factual directory entries, and not for
narrative content, which is why guide stories stay under sign-off.

**To build:** a per-cluster **coverage report** (listings per guide category, gaps first); the
confidence components and the corroboration check; the auto-import path through the same
`CrawlerIngest::upsertEntity()` as every other ingest (no second path); the confidential-address flag on
categories; the "recent auto-imports" admin list; and the guide-driven crawl that fills the gaps.

**Open:** the exact threshold (85 is a starting point to tune against real results), and which official
registries count as trusted sources for each category.

## Progress (2026-09-29)

**Coverage report built** (Admin → Cluster tools → 4. Directory coverage): public listings per Support &
wellness group for every cluster in a geo-hub, ordered by how many residents live with each gap. The
groups are defined once (`api/lib/SupportGroups.php`) and shared with the public place pages. First
reading of the pilot data: Gallup (~22,000 residents) has no health-care listings despite being the
region's medical centre, and food & basic needs has only 10 listings across the whole pilot. Those are the
first crawl targets.

**Auto-import built (2026-09-29).** `api/lib/crawler/AutoImport.php` checks the four guardrails on every
candidate as Listing Intake stages it, and publishes the ones that pass through `IntakeStager::approve()`
with no admin id and a note recording why (score, checks, source websites). Auto-published listings start
2 trust points below person-approved ones (`TrustScore::ENTITY_AUTO_IMPORT_ADJUST`). Admin → Auto-imports
lists them for spot-checks, with a one-click pull (suppress) and put-back, and offers a quick-approve batch
for staged rows, best score first.

Two refinements found in testing:

- **The category must be a sure match.** The interim word-match classifier guessed "House Cleaning" for a
  "Safe House". Auto-import now requires every word of the category's name to appear in the listing's own
  name, offerings or description; otherwise a person picks the category. A plural-stemming bug in the
  classifier ("Pharmacies" failed to match "pharmacy") was fixed in both places.
- **Private locations are enforced on the public site.** `api/lib/ListingSafety.php` holds the rules:
  confidential categories (Group Homes, Residential Care Homes, Homes-Adult) plus shelter, safe-house,
  sober-living and recovery-residence names in any category. The listing page and search results show
  those with a phone only: no address, map pin or directions. Human-review-only categories: cannabis,
  tobacco and smoke shops, guns, pawnbrokers, bail bonds, massage. In this taxonomy "Adult" means elder
  care and is not restricted.

Settings: `AUTO_IMPORT=off` in `.env` disables auto-publishing; `AUTO_IMPORT_MIN_CONFIDENCE` sets the
threshold (default 85). The NPI Registry is the first trusted registry (`AutoImport::TRUSTED_REGISTRIES`,
see "Second look" below); otherwise a listing needs two independent websites.

## Running it in increments, off Bluehost (2026-09-29)

To keep the shared host light, the guide-driven crawl is split:

- **Bluehost** keeps the database, the guardrails and staging, and two small token-protected endpoints:
  `api/crawl/jobs.php` hands out the next jobs, and `api/crawl/results.php` stages what comes back.
  Jobs come from a queue (`crawl_jobs`) seeded from the coverage report: one job per gap, ordered by the
  residents living with it, each with a 20-minute lease and up to 3 attempts. `CRAWL_QUEUE=off` pauses
  it. The token is the existing `CRAWLER_API_TOKEN`, also accepted as an `X-Crawler-Token` header because
  shared hosting can strip `Authorization`.
- **Railway** runs the worker (`workers/crawler/` in this repo) every 5 minutes (raised from 15 the same day). Each run takes 5 jobs,
  makes one OpenStreetMap query per job, fetches at most 60 homepages (robots.txt respected, 1.5 s apart),
  and stops within 4 minutes. A place counts as corroborated when OpenStreetMap lists it **and** its own
  website names it.
- **Supabase** is not used: one database of record.
- The public place pages cache their profile and support sections for 24 hours (`page_cache`); a
  profile reload or new auto-imported listings clear the relevant entries.

Note: the older `api/ingest.php` endpoint writes crawled records straight to `entities` without staging
or these guardrails. The worker does not use it, and it should be retired or routed through staging
before anything else is pointed at it. (It has in fact never accepted a request: it reads
`CRAWLER_API_TOKEN` before the site has loaded `.env`, so it always fails closed. The crawl endpoints had
the same bug and were fixed on 2026-09-29; `ingest.php` was left as it is.)

## Second look: more references for listings in review (2026-09-29)

Most places found in OpenStreetMap have no website of their own there, so they arrive with one source and
wait for a person. The first live run (Health care in Gallup) published 1 of 12 and staged 11. To confirm
more of them without lowering the guardrails, the worker now gives staged, single-source listings a second
look by name, address and city (`api/crawl/verify.php`, `api/lib/crawler/Verify.php`):

- **NPI Registry** (federal list of health-care providers, free, no key). A record whose name matches and
  whose location shows the same street address or phone is a **trusted registry** match (+20 confidence).
- **Web search** (Brave Search API, only when `BRAVE_API_KEY` is set on Railway): one search for the place
  and one aimed at **chamber of commerce** listings. A result counts only if it names the place and shows its
  street address or phone. Each is labelled: chamber of commerce, well-known directory (BBB, Yelp, Yellow
  Pages, Healthgrades, findhelp…), government, the place's own website, or another website. Each website
  counts once (+10 each); NPI copy sites are ignored as not independent.
- The bonus is capped at +40. The four guardrails then run again, unchanged: a listing publishes only with a
  registry match or 2+ independent websites, a score of 85+, a sure category and no duplicate. Otherwise it
  stays in Quick approve with its references listed, so a person decides faster.
- Listings with no street address and no phone are not looked up: nothing could confirm them.
- Each listing gets one second look (a 20-minute lease, at most 3 attempts).
