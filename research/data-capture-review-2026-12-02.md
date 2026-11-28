# Data capture, end to end: every way a listing's data enters (draft, 2026-11-28)

*Data & Content, for the CEO, the session manager and the PM (8). Jason, 2026-11-28: "review the phases of the crawler
and workflows end to end to ensure the right data is being captured." Gate 3 and live harm (bad health data). This
widens the listing audit (`research/listing-audit-2026-11-27.md`, its counts and causes C1–C7 kept there) into one report.
Read-only. Sources: the live code at Sync 2026-11-28 01:15 UTC (`degraffics/traverse-3way-sync`; paths below are
`app/…` and `website/…` as served) and 1A's public pages and search. **Status: draft.** Sections marked *to do* are
filled before Dec 2. Then one fresh reviewer, given only 0164's rule book and the code, checks it independently.*

## 1. The shape: one funnel, two writers

Only two places in the code create a listing (`INSERT INTO entities`): `app/lib/CrawlerIngest.php:426`
(`upsertEntity`, for everything gathered) and `app/lib/AddBusiness.php` (a person adding a business). Every gathered
path reaches `upsertEntity` through the same three steps:

1. **Stage** `IntakeStager::stage()` (`app/lib/crawler/IntakeStager.php:169`): the candidate is placed (ZIP → point and
   cluster), matched against what's listed, given category suggestions, and parked in `crawler_raw_index` as staged.
   Nothing public changes.
2. **Gate** `AutoImport::evaluate()` (`app/lib/crawler/AutoImport.php:100–211`), four checks: *real* (the 0164 §3.5
   floor), *complete*, *appropriate* (a category, clean text), *unique*. All four pass → published with no person
   (`tryImport`, :690; the `listings_tick` job, which belongs to W1 per `app/lib/Automation.php:55`, and
   `guide_finds_tick` for Find it). Otherwise it waits in Review.
3. **Write** `IntakeStager::approve()` (:519) → `CrawlerIngest::upsertEntity()` (:383) and `upsertMetadataKey()`
   (:536). The one identity check (`ListingIdentity::resolve`) runs inside the lock.

So a defect in a gate shows up on every path that feeds it. The fixes below are made in the funnel, once, not per
source.

## 2. What protects a person's edits

- **Fields:** `upsertMetadataKey` refuses a locked field (`CrawlerIngest.php:543–546`, "a person entered it", logged
  to the board's ledger). Locks are set by an owner's claim (`Claims.php:739`), staff edits (`ListingBulk.php:192`,
  `Rebuild.php:406`) and a moved pin (`ListingLocation.php:112`). An unlocked field is overwritten by any later
  crawl. That is intended (the evidence wins, FAQ "Where does a business's information come from?").
- **The kind (category):** **a gap.** `CategoryClassifier::setCategory()` (`app/lib/CategoryClassifier.php:293`)
  updates `entities.category_id` whenever the listing has no owner (`owner_user_id IS NULL`). It checks the lock only
  on the `category` text field (:295). A staff member's category on an unclaimed listing is locked in metadata but
  **not** in `category_id`. So the daily reclassify (`reclassifyTick`, "applied" when sure, :258–270) can change the
  kind a person set, and the listing then shows the new kind with the old text locked. **Fix (F1):** `setCategory`
  skips a listing whose `category` field is locked, and records "kept (a person set it)" in `category_changes`.
  The same rule applies to ADHS A2 and IT's C3 fix. Lane: Data & Content (one function), Tier 4 IT Manager (11).
  *To do: count the listings where this happened, from `category_changes` (applied, with a locked category field).*

## 3. Each path

Columns: source and licence · fields · how the kind is decided · identity · writes and may overwrite · check before
showing · Undo · measured on 1A.

| Path | Source and licence | Kind decided by | Gate | Undo | 1A, measured |
|---|---|---|---|---|---|
| W1 `coverage`, `targets` (finding businesses: OSM copy, pages) | OSM a lead only (0065); its own site, keepable pages (R9) | the classifier on name and text (`CategoryClassifier`); fallback group → a person | §1's four | `AutoImport::suppress` (:774), spot-check list (:758) | *to do* |
| W1 `npi_bulk` (monthly NPI file) | CMS NPI, public domain | NPI taxonomy → `NpiBulk::label` (`NpiBulk.php:46–71`) → `IntakeStager::NPI_CATEGORY` (:437), `from: registry`, sure | §1, + `npiNotPublic` (:214) | as W1 | organisations only (`crawler.py:2860`) |
| W1 `npi_town`, verify (`crawler.py:1007`, :1039) | NPI-2 API | none: evidence only, kind not re-filed (C3) | §1 | as W1 | 0 of 17 hospital records filed as Hospitals |
| W1 `overture_bulk`, `foursquare_bulk` (`OpenLoads.php`) | Overture CDLA/ODbL parts, Foursquare OS Places (Apache 2.0), credited | their category → `suggestCategories` (`IntakeStager.php:471–476`); exact "pharmacy/hospital/clinic" stamped `from: registry`, score 99 (the IT Manager's trace, #1629) | §1; **no person check** (F2) | as W1 | ~240 people as places (C5) |
| W1 `irs_bulk`, `rec_bulk` | IRS EO list (public), Recreation.gov RIDB (its terms) | *to do* | *to do* | *to do* | *to do* |
| W2 refresh, identity, places, second look | the listing's own site, NPI, Google (confirms only, paid by override) | doesn't change kind | writes unlocked fields (`IdentityJobs.php:256–269`) | the field's History (`Identity::listingWrote`) | C1: 11 of 65 Abouts name another town |
| W3 sources, page reads (`page_read_tick`, shipped off) | the source register, its licence | n/a | *to do* | *to do* | *to do* |
| W4/W8 Find it (`guide_finds_tick`, `GuideLinks.php:504–650`) | our crawler, then web search with Claude | the classifier | §1 | as W1 | *to do* |
| W9 landmarks, places (`Landmarks.php`) | USGS GNIS, Wikidata, Commons | landmark classes; `Landmarks::visibleSql` (0066) | `publiclyKnown` | *to do* | *to do* |
| W14 authority sources (ADHS A1, held) | state records, R9 | ADHS licence type, in A2 | a person reads the terms | A1 adds only | n/a yet |
| People: `AddBusiness.php`, claims, staff edits | the person (0164: never overwritten) | the person's choice | *to do* | the field's History | *to do* |
| W0 indexer, W5–W7, W10–W13 | not listings (URLs, guides, journeys, events, stories) | n/a | n/a | n/a | out of scope for listings; *to do: confirm none writes `entities`* (the grep in §1 says none does) |

## 4. Failures so far, each with its fix, release and lane

| # | Failure (evidence) | Fix | Release | Lane |
|---|---|---|---|---|
| F1 | A person's category not protected in `category_id` (`CategoryClassifier.php:293`) | §2 | health corrections | Data & Content; Tier 4 IT (11) |
| F2 | The gate has no person check: `evaluate()` uses `looksPerson` (:489) only for duplicates (:142–147), never for *real* or *appropriate*, so an individual from an open dataset passes (C5, ~240 in 1A) | *real* fails for a person's name unless a practice is named; the record kept, attached to its practice (the CMO's ruling, 2026-11-27) | IT's search release 1 (#1629) | IT (11) |
| F3 | Registry evidence never re-files the kind (C3; `crawler.py:1039`) | one taxonomy-code mapping, applied where the crawler set the kind | IT's search release 1, with A2's licence rows in the same table | IT (11) and Data & Content |
| F4 | A site-wide meta description kept as one place's About (C1; `crawler.py:805`, :2443) | keep it only when the site is the place's own, or the text names its town | health corrections | Data & Content |
| F5 | Departments' hours kept as one place's hours (C2, 1 listing) | one set for the place's own address, else none; and the missing hours (99%) as a fill line | health corrections | Data & Content |
| F6 | "Qualified" vouches for more than it checks (C6; `ListingConfidence.php:24–31`) | the label says what it checks; a disagreement on kind holds it at "Being checked" | IT's search release 1 | IT (11) |
| F7 | Urgent care ranked under clinics (C7) | search order | Frontend & Shop | Frontend & Shop |

## 5. Checked against 0164, 0163 and 0066 (*to do*)

R9 (the licence decides) per source; R4 (fill gaps, never overwrite a person) against §2; §3.5's floor against §1's
gate; 0163 §9's switches per path (the pause on Jason's call, 2026-11-28, applies to W1's creating tasks and the two
publishing jobs); 0066 for every path that can place a pin on a nation's land (`Nations::pin()`, `Landmarks::visibleSql`).

## 6. The independent check (*to do, last*)

One fresh reviewer, given only `decisions/0164`'s rule book and the code paths in §1, never these conclusions, reads
the funnel and reports what breaks a rule. Its findings are added here unedited, each with a yes or no from this lane.
