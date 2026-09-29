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
threshold (default 85). No official registries are wired as trusted sources yet
(`AutoImport::TRUSTED_REGISTRIES`), so today a listing needs two independent websites.
