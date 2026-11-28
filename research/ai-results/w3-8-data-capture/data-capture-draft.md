Status: batch draft, for Data & Content to verify

# Data capture, end to end: every way a listing's data enters (batch draft, 2026-12-02; first draft 2026-11-28)

*Data & Content, for the CEO, the session manager and the PM (8). Jason, 2026-11-28: "review the phases of the crawler and workflows end to end to ensure the right data is being captured." Gate 3 and live harm (bad health data).*

*This report widens the listing audit (`research/listing-audit-2026-11-27.md`, whose counts and causes C1–C7 stay there) into one report. It is read-only.*

*Sources:*
- *The live code at Sync 2026-11-28 01:15 UTC (`degraffics/traverse-3way-sync`; paths below are `app/…` and `website/…` as served).*
- *1A's public pages and search.*
- *For this batch draft, the review answers of jobs 3, 3b and 9 (listed in §0).*

***Status: batch draft.** The* to do *sections are filled where an attached answer covers them. Where no answer does, they are left* to do. *Every claim taken from an answer names that answer. Line numbers are cited only where an answer gives them. A finding supported by one answer is marked **single-pass**. Disagreements between answers, or between an answer and the earlier draft, are marked **Conflict**.*

## 0. The review answers used (added in this batch)

Answers are cited by the short keys below, from the most general job to the most specific.

| Key | Answer file (`research/ai-results/…`) | Reviews | State of the attached copy |
|---|---|---|---|
| 3·AI | `job3-intake-0164/intake-autoimport.md` | `AutoImport.php` vs 0164 | truncated in Path 2 (mid R9) |
| 3·CL | `job3-intake-0164/intake-cleanup.md` | `DataCleanup.php` | truncated after scope |
| 3·IS | `job3-intake-0164/intake-intake-stager.md` | `IntakeStager.php`, `CrawlQueue.php` | truncated in P1 |
| 3·RF | `job3-intake-0164/intake-refresh.md` | refresh | title only |
| 3·SH | `job3-intake-0164/intake-suggest-helpfind-api.md` | `Suggestions.php`, `HelpFind.php`, `api/ingest.php` | truncated in Path A |
| 3b·AI | `job3b-intake-0164-pass2/intake-autoimport-pass2.md` | `AutoImport.php` | truncated in Path 2 |
| 3b·IS | `…-pass2/intake-intake-stager-pass2.md` | `IntakeStager.php`, `CrawlQueue.php` | truncated in Path 1 |
| 3b·LI | `…-pass2/intake-listing-identity-pass2.md` | `ListingIdentity.php` | truncated in Path 1 |
| 3b·RF | `…-pass2/intake-refresh-pass2.md` | `Refresh.php` | truncated in scope |
| 3b·SH | `…-pass2/intake-suggest-helpfind-api-pass2.md` | `Suggestions.php`, `HelpFind.php`, `api/ingest.php` | truncated in Path D |
| 9·CI | `job9-intake-in-depth/j9-CrawlerIngest-php.md` | `CrawlerIngest.php` vs 0164/0163/0066 | summary table truncated after row 1; D1–D17 complete |
| 9·NB | `…/j9-NpiBulk-php.md` | `NpiBulk.php` | last table row truncated; defects 1–14 complete |
| 9·OL | `…/j9-OpenLoads-php.md` | `OpenLoads.php` | truncated in defect 4 |
| 9·IX | `…/j9-indexer-py.md` | `workers/crawler/indexer.py` (W0) | truncated in M2 |
| 9·IN | `…/j9-ingest-php.md` | `sync-storage/api/ingest.php` | complete |

**Empty in the attached copies, so contributing nothing:**
- Job 3: `intake-identity`, `intake-ingest-add`, `intake-listing-identity`.
- Job 3b: `intake-cleanup-pass2`, `intake-identity-pass2`, `intake-ingest-add-pass2`.
- Job 9: `j9-AutoImport-php`, `j9-DataCleanup-php`, `j9-Identity-php`, `j9-IntakeStager-php`, `j9-ListingIdentity-php`, `j9-crawler-py`.

**Not covered by any answer, so their rows below remain the earlier draft's own reading:**
- `CategoryClassifier`, `AddBusiness`, `Claims`, `DataCleanup` (beyond scope), `Landmarks`, `GuideLinks`, `crawler.py`, `IdentityJobs`, `ListingConfidence`, and `AutoImport::evaluate()`.

**Path naming.** The answers name files as `sync-app/lib/…` and `sync-storage/api/ingest.php`. This report names them `app/lib/…` and `website/api/ingest.php`. The mapping is assumed, not verified (see §8).

## 1. The shape: one funnel, two writers

Only two places in the code create a listing (`INSERT INTO entities`):
- `app/lib/CrawlerIngest.php:426` (`upsertEntity`, for everything gathered);
- `app/lib/AddBusiness.php` (a person adding a business).

Every gathered path reaches `upsertEntity` through the same three steps:

1. **Stage.** `IntakeStager::stage()` (`app/lib/crawler/IntakeStager.php:169`) places the candidate (ZIP → point and cluster), matches it against what's listed, gives it category suggestions, and parks it in `crawler_raw_index` as staged. Nothing public changes.
2. **Gate.** `AutoImport::evaluate()` (`app/lib/crawler/AutoImport.php:100–211`) runs four checks:
   - *real* (the 0164 §3.5 floor);
   - *complete*;
   - *appropriate* (a category, clean text);
   - *unique*.

   If all four pass, the listing is published with no person (`tryImport`, :690). This runs from the `listings_tick` job, which belongs to W1 per `app/lib/Automation.php:55`, and from `guide_finds_tick` for Find it. Otherwise the listing waits in Review.
3. **Write.** `IntakeStager::approve()` (:519) → `CrawlerIngest::upsertEntity()` (:383) and `upsertMetadataKey()` (:536). The one identity check (`ListingIdentity::resolve`) runs inside the lock.

So a defect in a gate shows up on every path that feeds it. The fixes below are made in the funnel, once, not per source.

**What the answers add (batch draft):**

- **The insert line.** 9·CI places the insert at `CrawlerIngest.php:425–461` and `upsertEntity` at 383–519, consistent with :383 and :426 above.
- **Two direct consumers of `CrawlerIngest`.** 9·CI names `scripts/migrate_listings.php` and `api/ingest.php` (lines 10–11). Neither goes through steps 1–2. That matches F8 and F9.
- **Where publishing happens.** 3·IS and 3b·IS both trace worker crawl → `CrawlQueue::complete` → `IntakeStager::createSource` + `stage` → `AutoImport::tryImport`. Both say publishing goes through `approve(..., adminId null)` with no person. 9·NB and 9·OL say the NPI and open-dataset loads publish only through `IntakeStager::stage`'s `auto_imported`. Whether the floor, readiness, protected-name and nation checks run there is "not shown" in both.
- **Conflict: "the one identity check runs inside the lock."**
  - 3·AI and 3b·AI both say `advance()` decides covered/obvious/eligible with its own `obvious()`/`coveredBy()`/`relation()`, not `ListingIdentity::resolve()`. 3·AI adds that "nothing here opens a transaction or takes the identity lock".
  - 9·CI says `upsertEntity`'s `$check` defaults to `false` (383). It also says `resolve()` (417) and the `INSERT` (425) are not visibly in one transaction or under the lock (D2, D3).
  - 9·IN reports that `ingest.php:68` calls `upsertEntity(..., true)` inside `ListingIdentity::locked(...)` and a transaction (64–68). Yet 9·IN states that `resolve()` "is not called here".
  - Read together: on the ingest path the check is probably on, and the lock is probably held by the caller. That is an inference across two answers, not a finding (§8).
  - Whether `approve()` calls `resolve()` under the lock is "not visible" in 3·AI, 3b·AI and 3b·IS.
  - The draft's sentence stands unconfirmed (F11, F12).

## 2. What protects a person's edits

- **Fields.**
  - `upsertMetadataKey` refuses a locked field (`CrawlerIngest.php:543–546`, "a person entered it", logged to the board's ledger).
  - Locks are set by an owner's claim (`Claims.php:739`), staff edits (`ListingBulk.php:192`, `Rebuild.php:406`) and a moved pin (`ListingLocation.php:112`).
  - An unlocked field is overwritten by any later crawl. That is intended (the evidence wins, FAQ "Where does a business's information come from?").
- **The kind (category): a gap.**
  - `CategoryClassifier::setCategory()` (`app/lib/CategoryClassifier.php:293`) updates `entities.category_id` whenever the listing has no owner (`owner_user_id IS NULL`).
  - It checks the lock only on the `category` text field (:295).
  - So a staff member's category on an unclaimed listing is locked in metadata but **not** in `category_id`.
  - The daily reclassify (`reclassifyTick`, "applied" when sure, :258–270) can therefore change the kind a person set. The listing then shows the new kind with the old text locked.
  - **Fix (F1), in IT's search release 1b:** `setCategory` never overwrites a category a person set. ADHS A2 and the C3 fix re-file through it, through one mapping (`IntakeStager::REGISTRY_KIND`, `registryCategory()`; the IT Manager, 2026-11-28).
  - *To do: count the listings where this happened, from `category_changes` (applied, with a locked category field).* **No answer covers `CategoryClassifier` or `category_changes`; still to do.**

**What the answers add (batch draft):**

- **Lock lines.** 9·CI gives the lock refusal as `CrawlerIngest.php:544–547`; this draft says 543–546. That is a one-line difference to check, not a substantive conflict.
- **Conflict: "an unlocked field is overwritten by any later crawl. That is intended."** 9·CI says this breaks the rules:
  - D9: `upsertMetadataKey` "refuses only when `is_locked = 1`"; otherwise it replaces the value (555, 562), "including a person-entered value that is not locked".
  - D9 also says "locks come only from the dashboard (523–524)", so edits made in Review, by staff in the admin, or by visitors may be unlocked.
  - 9·CI cites R4 ("only empty or crawler-owned" fields) and R11 ("replacing a shown value takes two" independent sources).
  - Of this draft's lock sources (claims, `ListingBulk`, `Rebuild`, `ListingLocation`), none is reviewed by any answer, so the coverage of locks is unverified.
  - D8 adds that `name`, `primary_zip`, `micro_cluster_id` and the point are rewritten on unclaimed listings (470, 479–487), and that only the point is protected for person-set geocodes (471–478).
  - See F18. This lane has to decide whether the "intended" sentence stands under R4 and R11.
- **Ingest path.** 9·IN D3 raises the same risk on the ingest path (`ingest.php:68, 72`, "high (verify)").

## 3. Each path

Columns: source and licence · how the kind is decided · gate (check before showing) · Undo · measured on 1A. Answer-based additions are in *italics* with their keys.

| Path | Source and licence | Kind decided by | Gate | Undo | 1A, measured |
|---|---|---|---|---|---|
| W1 `coverage`, `targets` (finding businesses: OSM copy, pages) | OSM a lead only (0065); its own site, keepable pages (R9). *Answers: the worker's source is recorded only as a synthetic `https://worker.traversence/job/N` row with no kind, licence, Use or terms date; real page URLs survive only in the payload JSON (3·IS, 3b·IS).* | the classifier on name and text (`CategoryClassifier`); fallback group → a person | §1's four | `AutoImport::suppress` (:774), spot-check list (:758) | *to do: no answer measures this* |
| W1 `npi_bulk` (monthly NPI file) | CMS NPI, public domain. *The register row is created with no licence, use or terms date visible (`NpiBulk.php:196`, 9·NB #7).* | NPI taxonomy → `NpiBulk::label` (`NpiBulk.php:46–71`) → `IntakeStager::NPI_CATEGORY` (:437), `from: registry`, sure | §1, + `npiNotPublic` (:214). *Conflict: 9·NB #1 finds "nothing holds back the non-public kinds" in `NpiBulk.php` (153–185), and AutoImport is not shown to it. This draft's `npiNotPublic` may be that hold; verify (F20).* | as W1. *9·NB #13: dropped records are hard-deleted (`NpiBulk.php:204–206`).* | organisations only (`crawler.py:2860`). *9·NB agrees `batch()` keeps NPI organisations (99–120).* |
| W1 `npi_town`, verify (`crawler.py:1007`, :1039) | NPI-2 API | none: evidence only, kind not re-filed (C3) | §1 | as W1 | 0 of 17 hospital records filed as Hospitals |
| W1 `overture_bulk`, `foursquare_bulk` (`OpenLoads.php`) | Overture CDLA/ODbL parts, Foursquare OS Places (Apache 2.0), credited. *9·OL #1: per-record licences are collected (104) and attached (121) but never compared against an allowed list. #2: the source is auto-created with `create_listings` and no licence or terms record (225).* | their category → `suggestCategories` (`IntakeStager.php:471–476`); exact "pharmacy/hospital/clinic" stamped `from: registry`, score 99 (the IT Manager's trace, #1629) | §1; **no person check** (F2). *9·OL #3: only `DataCleanup::junkName` is applied before staging (93); no protected, confidential, nation-land or person check (93–124).* | as W1 | ~240 people as places (C5). *Conflict on the count: see F2.* |
| W1 `irs_bulk`, `rec_bulk` | IRS EO list (public), Recreation.gov RIDB (its terms) | none: neither makes a listing. IRS only confirms one waiting in Review (`IrsBulk.php:8–14`, "never shown and never creates a listing"); RIDB fills its own table, shown as "Outdoors & public lands" (`RecBulk.php:8–15`) | n/a | RIDB: Admin → Crawler → Recreation hides a place, kept across reloads | none found. *No answer reviews these files.* |
| W2 refresh, identity, places, second look | the listing's own site, NPI, Google (confirms only, paid by override) | doesn't change kind | writes unlocked fields (`IdentityJobs.php:256–269`). *See F18 on unlocked person-entered fields.* | the field's History (`Identity::listingWrote`) | C1: 11 of 65 Abouts name another town. *Both refresh answers (3·RF, 3b·RF) are truncated before any finding.* |
| W3 sources, page reads (`page_read_tick`, shipped off) | the source register, its licence | n/a | *to do: no answer* | *to do: no answer* | *to do: no answer* |
| W4/W8 Find it (`guide_finds_tick`, `GuideLinks.php:504–650`) | our crawler, then web search with Claude | the classifier | §1 | as W1 | *to do: no answer* |
| W9 landmarks, places (`Landmarks.php`) | USGS GNIS, Wikidata, Commons | landmark classes; `Landmarks::visibleSql` (0066). *Conflict on naming: 3b·IS says 0164 "does not name `Landmarks::visibleSql`"; it names `Landmarks::isProtected`, `NATION_SENSITIVE_CLASSES` and `ListingSafety::isConfidential`. This row cites 0066, which no answer quotes.* | `publiclyKnown` | *to do: no answer* | *to do: no answer* |
| W14 authority sources (ADHS A1, held) | state records, R9 | ADHS licence type, in A2 | a person reads the terms | A1 adds only | n/a yet |
| People: `AddBusiness.php`, claims, staff edits | the person (0164: never overwritten) | the person's choice from `AddBusiness::KINDS`, a top-level group; staff refine it | a confirmed email and phone, an emailed code, a check for one already theirs; live as Managed by owner, noindex until verified (`AddBusiness.php:8–17`) | the field's History; claims' disputes | not sampled: 0 of 852 1A health listings are claimed. *The `ingest-add` answers are empty in jobs 3 and 3b.* |
| People: suggestions, "Help us find" (`Suggestions.php`, `HelpFind.php`) *(row added from 3·SH, 3b·SH)* | a visitor, member, staff member or machine (`crawler-`/`system:` devices) | n/a | *a person reviews every suggestion in Review; `create` writes no listing field (3·SH, 3b·SH). Exception: `Guides::due()` moves a guide check forward before review (both).* | *`decide()` can set status back to `new` with no history row (3b·SH)* | *not measured* |
| W0 indexer, W5–W7, W10–W13 | not listings (URLs, guides, journeys, events, stories) | n/a | n/a | n/a | out of scope for listings. *To do: confirm none writes `entities`* (the grep in §1 says none does). *W0 partly confirmed: "Nothing in this file creates, changes, publishes or hides a listing" (`indexer.py`, 9·IX, single-pass). W5–W7 and W10–W13: no answer, still to do.* |

### Measured on a 1A sample (2026-11-28 ~01:45 UTC)

Method:
- 150 of the 852 public 1A health listings, chosen at random (seed 1128).
- Each page was read as a visitor.
- The table records what the page's "How sure we are" names as its source, and the error found.

| Source the page names | Listings | People shown as places | Hospital-named, not filed under Hospitals |
|---|---|---|---|
| The NPI Registry (address, phone) | 62 | 8 (13%) | 2 |
| Our crawler only, nothing else confirming | 84 | 49 (58%) | 1 |
| Our team | 4 | 2 | 0 |

**Labels:** Unconfirmed 84, Qualified 34, Being checked 20, Confirmed by Traversence 12. "Sources disagree" appears on 31.

**How people are counted:** by name form (NPI's "Last, First", or a name ending in a credential, with no business word). The figures are a floor.

*To do: the same sample against each path's own record (the live read per id), and Show Low's non-health listings for comparison.* **No answer measures listings; still to do.**

## 4. Failures so far, each with its fix, release and lane

How to read this section:
- F1–F10 are the earlier draft's. Their file:line cells repeat the draft's own references. Where an answer gives lines, the answer's lines are added with its key.
- F11 onward are new, from the answers. They have no release or lane yet; those are for this lane to assign.
- Severity is the answer's own rating. "Unrated" means no attached answer rates it.

| # | Failure (evidence) | Rule | file:line | Severity | Fix | Release · Lane | Supported by |
|---|---|---|---|---|---|---|---|
| F1 | A person's category not protected in `category_id` | 0164 R4 | `CategoryClassifier.php:293` (draft only) | unrated | §2: `setCategory` never overwrites a category a person set (the locked text key, or set by admin, owner or user) | **IT's search release 1b** (the session manager, 2026-11-28: not a second fix in the same writer) · IT (11) | none: draft only |
| F2 | The gate has no person check: `evaluate()` uses `looksPerson` (:489) only for duplicates (:142–147), never for *real* or *appropriate*, so an individual from an open dataset passes (C5, ~240 in 1A) | 0164 §3.5, R6 | draft: `AutoImport.php:489`, :142–147. Answers: `OpenLoads.php:93–124`; `CrawlerIngest.php:431` | 9·OL #3: high if IntakeStager doesn't apply it; 9·CI D5: medium | *real* fails for a person's name unless a practice is named; the record is kept, attached to its practice (the CMO's ruling, 2026-11-27). 9·OL: "flag person-like names for the same people guard". 9·CI: run the people rule before insert and route hits to `person_place` | **IT's search release 1b** (evaluate keeps a looksPerson find as a record) · IT (11) | 9·OL, 9·CI (supporting the gap upstream and in `upsertEntity`). `evaluate()` itself: no answer (9·AutoImport empty, 3·AI and 3b·AI truncated before it). **Conflict on the count:** draft ~240 (C5, open datasets); 9·CI and 9·OL quote 0164's 2026-11-28 note, "about 310 people shown as places", which 9·CI attributes to the CrawlerIngest path |
| F3 | Registry evidence never re-files the kind (C3) | 0164 §12 C3 | `crawler.py:1039` (draft only) | unrated | one taxonomy-code mapping, applied where the crawler set the kind | IT's search release 1, with A2's licence rows in the same table · IT (11) and Data & Content | none: `j9-crawler-py` empty |
| F4 | A site-wide meta description kept as one place's About (C1) | 0164 §5.3 (draft) | `crawler.py:805`, :2443 (draft only) | unrated | keep it only when the site is the place's own, or the text names its town | health corrections · Data & Content | none. Related: 9·CI D14 (a crawler `description` may be page text, stored unscrubbed, `CrawlerIngest.php:500`), single-pass |
| F5 | Departments' hours kept as one place's hours (C2, 1 listing) | n/a | none | unrated | one set for the place's own address, else none; and the missing hours (99%) as a fill line | health corrections · Data & Content | none |
| F6 | "Qualified" vouches for more than it checks (C6) | n/a | `ListingConfidence.php:24–31` (draft only) | unrated | the label says what it checks; a disagreement on kind holds it at "Being checked" | IT's search release 1 · IT (11) | none |
| F7 | Urgent care ranked under clinics (C7) | n/a | none | unrated | search order | Frontend & Shop · Frontend & Shop | none |
| F8 | `website/api/ingest.php:68` creates listings through `upsertEntity` with no gate: no floor, no category check, no person check, no auto-import switch. Nothing of ours calls it today, but any holder of the worker token can | 0164 §3.5, §5.4, R10; 0163 §8/§10 X2; 0163 §9 (9·IN) | `ingest.php:67–68, 72` (gate); `ingest.php:8–25` (switch); `CrawlerIngest.php:391, 425–461, 600` (only a non-empty name checked; inserted `is_suppressed = 0`) | 9·IN D1: high (verify); D6: high. 9·CI D1: high | refused while the pause holds (pause-1a); then retired, or routed through `IntakeStager::stage`. Answers: check `CrawlQueue::pausedWhy()` after line 23 and answer paused (9·IN); run floor and readiness before insert, else insert suppressed as a sighting or hand to `AutoImport::evaluate()` (9·CI, 9·IN) | pause-1a now; retirement in 1b or later · IT (11) | 9·IN, 9·CI (two answers, one job). 3·SH and 3b·SH cover `api/ingest.php` but are truncated before it. 9·IN notes "fail closed when there is no token" and a constant-time compare (14–21) |
| F9 | Listings public with no second source: 84 of 150 sampled 1A health listings (56%) read "Found by our crawler; no other source has confirmed it yet", Unconfirmed at 5 of 100. 49 of those 84 (58%) are people. 0164 §3.5's floor asks for a source we may keep; the page names none | 0164 §3.5 | draft: `CrawlerIngest.php:9–14`. 9·CI: consumers at lines 10–11 | 9·CI D1: high (the path) | Traced: all 83 of the sampled ones are listings #4931–#8722, each "Listed since September 2026". That is the one-time legacy migration (`scripts/migrate_listings.php`, through `CrawlerIngest`), before 0164's floor (2026-11-14). It never passed `evaluate()`, and W2 rechecks them ("checked Nov 18–20") but never re-applies the floor. Their truncated category names come from that legacy data. **Fix:** two Data cleanup rules on `app/lib/DataCleanup.php` RULES (ledger, Undo per record or per pass, "Check these" for anything a person touched, R4): `person_place` and `legacy_unsourced`, run once by a person's button and previewed first | health corrections (the two rules) · Data & Content (IT's 1b covers new finds; these are the old ones) | 9·CI quotes 0164's 2026-11-28 note that the legacy run "through CrawlerIngest" "never passed `AutoImport::evaluate`", **single-pass** (and see §6 on why it is not independent). `DataCleanup`: 3·CL truncated, job 3b and job 9 copies empty. **Note:** "84" vs "all 83 of the sampled ones" in the draft's own text; reconcile |
| F10 | Sources disagree, shown but unresolved: 31 of 150 (21%) carry "⚠ Sources disagree" | 0164 R11 (evidence rule) | none | unrated | *to do: which fields, and why the evidence rule (most independent sources wins) didn't settle them.* **No answer; still to do.** Related: 9·CI D9 says a single source replaces a shown value (`CrawlerIngest.php:555, 562`), against R11's "takes two" | health corrections · Data & Content | none directly. 9·CI D9 related, single-pass |

### 4b. New findings from the review answers (F11 onward, release and lane unassigned)

**F11. Intake matching decided outside the one identity check (AutoImport).**
- **Path:** W1 and every staged find → `AutoImport::advance()`.
- **Rule:** 0164 §3.1 ("one identity check, on every path").
- **file:line:** none given. Both answers cite functions only: `obvious()`, `coveredBy()`, `relation()`.
- **Severity:** unrated.
- **What the answers report:**
  - The covered/obvious/eligible decision is made in AutoImport, not by `ListingIdentity::resolve()` (3·AI, 3b·AI).
  - 3·AI only: no transaction or identity lock is opened, and `obvious()` is called twice per row, so the match can change between calls.
- **Fix:** route the decision through one `ListingIdentity::resolve()` inside a transaction (3·AI). Treat `obvious()`/`coveredBy()` as adapters to it (3b·AI).
- **Supported by:** 3·AI, 3b·AI (two passes).
- **Conflict:** with §1's "runs inside the lock".
- **Small conflict:** 3·AI says `advance()` takes up to 500 rows ordered by `advanced_at` then `confidence_score`. 3b·AI says "oldest `advanced_at` first, up to 150 (maximum 500)".

**F12. `upsertEntity`'s identity check is optional, unlocked, and lets the grey zone through.**
- **Path:** legacy migration and `api/ingest.php` → `CrawlerIngest::upsertEntity`.
- **Rules:** 0164 §3.1, §3.2; 0163 §12.
- **file:line:**
  - `CrawlerIngest.php:383, 415, 424`: `$check` defaults to `false`.
  - `CrawlerIngest.php:417, 425`: no transaction or lock visible.
  - `CrawlerIngest.php:418`: `maybe` falls through to a new insert.
  - `ingest.php:64–68`: `resolve()` not called in the endpoint.
- **Severity:** 9·CI D2 high, D3 medium, D4 medium-high. 9·IN D2 medium (verify).
- **Fix:**
  - remove the parameter or default it to `true`;
  - wrap lines 411–517 in a transaction with the identity lock, or assert that the caller holds it;
  - send `maybe` to W2's `identity` task (9·CI);
  - or call `resolve()` inside the closure at line 68 (9·IN).
- **Supported by:** 9·CI, 9·IN.
- **Conflict between them:** 9·CI says "whether `api/ingest.php` passes `true` is not shown". 9·IN reports that line 68 passes `true` and holds the lock (67). Neither sees the other file, so the combined reading is unverified (§8).

**F13. NPI bulk runs its own, looser identity test and drops matches.**
- **Path:** W1 `npi_bulk`.
- **Rules:** 0164 §3.1 (one check, one name measure, `AutoImport::sameName` against the longer name), R11.
- **file:line and severity:**
  - `NpiBulk.php:141–146, 156–163` (medium): `sameName` divides by the shorter word list, so "Smith" vs "Smith Family Dental" scores 1.0.
  - `NpiBulk.php:163, 166, 191` (medium): same/covered matches are discarded, not merged as evidence.
  - `NpiBulk.php:191` (low, depends on code not shown): `resolve()` runs outside the writing lock.
- **Fix:** delete the pre-filter or use `AutoImport::sameName`; attach the NPI source to the matched listing (gaps only); confirm `stage()` resolves under the lock.
- **Supported by:** 9·NB, single-pass.

**F14. Machine merges on weak matches, and into claimed listings.**
- **Path:** `AutoImport` "obvious" → `fillGaps()`.
- **Rule:** 0164 §3.2 note, item 1 (only exact twins with phone, name and ZIP, both unclaimed, nothing person-entered).
- **file:line:** none given. The answer cites `$sameNum && $nm >= 0.75` and `$nm >= 0.75 && $noNum`, and the `usort` that prefers the claimed listing.
- **Severity:** unrated.
- **Fix:** restrict machine merges to full phone + name + ZIP with neither side claimed or person-touched. Route the rest to W2 `identity` / "Vet this place".
- **Supported by:** 3·AI, **single-pass**. 3b·AI's Path 1 does not raise it. That is not a contradiction, but it is unconfirmed.

**F15. A "covered" find's name is dropped with no alias.**
- **Path:** `AutoImport` "covered" branch.
- **Rule:** 0164 R11 ("brings only where it is and how to reach it").
- **file:line:** none given.
- **Severity:** unrated.
- **Fix:** add the find's name as an alias, as the "obvious" branch already does.
- **Supported by:** 3·AI, **single-pass**.

**F16. Switches: creating paths don't check master, workflow or task.**
- **Paths and evidence:**
  - AutoImport: `enabled()` reads `.env AUTO_IMPORT`, which **defaults to on**, and no master, workflow or task switch is checked (3·AI). 3b·AI agrees that publishing runs "while `AUTO_IMPORT` is not `off`".
  - `NpiBulk.php:99, 149`: `batch()`/`stageNew()` never check `off()` (9·NB #2, medium, conditional on the intake endpoint).
  - `ingest.php:8–25`: no pause check (9·IN D6, high). 9·CI also notes `CrawlerIngest` has no pause check.
  - `indexer.py:393` (and 50): a round runs about 300 s without check-in, so Pause takes up to about 5 minutes to take effect (9·IX M1, medium).
- **Rules:** 0163 §9; 0164 header ("How it runs"), §1.2 step 1.
- **Fix:**
  - gate `enabled()` on the 0163 §9 switches and default it off (3·AI);
  - return early on `off()`/`pausedWhy()` (9·NB, 9·IN);
  - `time.monotonic() > round_end` (9·IX).
- **Supported by:** 3·AI, 3b·AI, 9·NB, 9·IN, 9·IX, 9·CI. This answers part of §5's 0163 §9 to-do. It bears on whether Jason's pause (2026-11-28) actually holds on every path; see §8.

**F17. The licence does not decide: sources and facts carry no checked licence.**
- **Rules:** 0164 R9, §0.5, §1.4, §2.4, R18; 0163 §8 stage 8.
- **Evidence by path:**
  - **AutoImport:** `keepRule()` fails open. On any exception it marks the host keepable. Facebook, Instagram and Yelp are not excluded in code; `sourceHosts()` excludes only OSM, and `keepHosts` additionally only Google. No line given; unrated (3·AI, **single-pass**; the answer is truncated there).
  - **Worker intake:** the only source recorded is the synthetic job URL, with no licence or Use, and no register lookup. A cite-only or directory page "can be published as our value". No line given; unrated (3·IS, 3b·IS, two passes).
  - **`OpenLoads.php:104, 121, 132`:** per-record licences are never checked. High (9·OL #1).
  - **`OpenLoads.php:225`:** source auto-created with `create_listings`, no licence or terms. Medium (9·OL #2).
  - **`NpiBulk.php:196`:** register row without licence, use or terms date. Low (9·NB #7).
  - **`CrawlerIngest.php:378–381, 514, 569`:** no source or licence carried. `upsertMetadataKey` is called with no `$src`; `Identity::listingWrote` receives `[]`. High (9·CI D12).
  - **`ingest.php:55–68`:** none required per record. Medium (9·IN D7).
- **Fix:**
  - refuse to store unless the register's use is "store facts" (9·CI);
  - skip or mark evidence-only any record whose licence isn't on the allowed list (9·OL);
  - don't auto-create sources; require a register row with licence and terms date (9·OL);
  - make `keepRule` fail closed (implied by 3·AI's finding; the answer is truncated before its fix);
  - reject ingest records lacking source and licence (9·IN).
- **Supported by:** 3·AI, 3·IS, 3b·IS, 9·OL, 9·NB, 9·CI, 9·IN.

**F18. Person-entered fields overwritten when not locked.**
- **Path:** `upsertEntity` / `upsertMetadataKey`, and every path through them.
- **Rules:** 0164 R4, R11, §3.2 note, §3.3.
- **file:line and severity:**
  - `CrawlerIngest.php:470, 479–487`: name, ZIP and cluster rewritten on unclaimed listings. High (9·CI D8).
  - `CrawlerIngest.php:544–547, 555, 562`: only `is_locked` stops a write; a refusal only writes a ledger line (545). High (9·CI D9).
  - `ingest.php:68, 72`: high (verify) (9·IN D3).
- **Fix:**
  - `AND updated_by_type = 'ai_crawler'` on the update at 479–483;
  - skip existing rows unless `source_type = 'ai_crawler'`;
  - send shown-value replacements through `Identity::recompute()`;
  - raise the R4 "check this?" step on refusal (9·CI).
- **Supported by:** 9·CI, 9·IN.
- **Conflict:** with §2's "that is intended".

**F19. No protected, confidential or nation-land check before a gathered record is staged or inserted.**
- **Paths and evidence:**
  - `OpenLoads.php:93–124`: high if IntakeStager doesn't apply it (9·OL #3). The answer notes that line 123 attaches the dataset's own point on the Navajo Nation, and `due()` puts Kayenta first (60–62).
  - `CrawlerIngest.php:431`: medium; a shelter's address and pin would be published (9·CI D5).
  - Suggestions: a visitor's `point` is stored with no protected-place check. Unrated (3b·SH; "not public here").
- **Rules:** 0164 R7, R6, §3.4; 0066.
- **Fix:**
  - run `Landmarks::isProtected` / `ListingSafety::isConfidential` and hold on a hit;
  - drop `where.point` inside `nation_shapes` (9·OL);
  - route hits to a held state (9·CI);
  - store a computed protected flag on `point` (3b·SH).
- **Supported by:** 9·OL, 9·CI, 3b·SH.
- **Open question (9·OL):** whether machine-publishing Overture businesses on the Navajo Nation needs a person's call "is not settled in the attached text".
- **Conflict:** with §5's "the funnel places their pins as given… That's consistent". 9·OL's fix would move such pins.

**F20. NPI's non-public kinds are staged and auto-imported.**
- **Path:** W1 `npi_bulk`.
- **Rules:** 0164 §1.5 (NPI guards), §12 C3; 0163 §8 stage 8.
- **file:line:** `NpiBulk.php:153–185, 196–198`; the kinds are listed at 48–56 (home health, in-home care, hospice, ambulance, medical transportation, residential mental health, substance use).
- **Severity:** high.
- **Fix:** keep a list of non-public taxonomy codes; store them in `npi_orgs` only, or stage them with a hold and a Review step "List anyway".
- **Supported by:** 9·NB, **single-pass**.
- **Conflict:** with the §3 row's `npiNotPublic (:214)`. The answer could not see AutoImport.
- **Department fold:** 9·NB could not see it (`ListingIdentity::fold`, 190).

**F21. Junk addresses pass the floor.**
- **Evidence:**
  - `NpiBulk.php:154`: any address containing a digit passes, so "PO Box 190" qualifies. Medium (9·NB #3).
  - `CrawlerIngest.php:391`: no junk-name check at all. High (9·CI D1, part of F8).
- **Rule:** 0164 §3.1, §3.5.
- **Fix:** reject PO Box patterns and use the phone if present (9·NB).
- **Supported by:** 9·NB, 9·CI.

**F22. Dropped evidence is deleted.**
- **Path:** NPI bulk `finish()`.
- **Rules:** 0164 R12, R11; 0163 §12.
- **file:line and severity:**
  - `NpiBulk.php:204–206`: hard delete, not a closure signal. Medium (#13).
  - `NpiBulk.php:103`: rows over 1,000 silently dropped, then deleted as "not in this file". Medium (#14).
- **Fix:** mark `dropped_at` and raise a signal; return the skipped count; don't run `finish()` while any batch is short.
- **Supported by:** 9·NB, **single-pass**.

**F23. Points published without being marked approximate.**
- **Evidence:**
  - `CrawlerIngest.php:400–405, 454, 442–447`: a ZIP centroid is stored as the point and earns `ENTITY_GEOCODE_MATCH_BONUS`. Medium (D6).
  - `CrawlerIngest.php:389, 121, 453`: a missing ZIP becomes `00000` and the listing stays public with no cluster. Low-medium (D7).
  - `ingest.php:67`: lock keyed on ZIP with a `'00000'` fallback. Low (9·IN D5).
- **Rules:** 0164 §5.4, R12; §3.1 (identity lock).
- **Fix:** write an approximate `geocode` key and drop the bonus; fold D7 into the readiness check; reject records with no ZIP and no phone.
- **Supported by:** 9·CI, 9·IN.

**F24. Every record lands in one geo-hub.**
- **Evidence:**
  - `CrawlerIngest.php:140, 157, 164` (comment 108–116). Medium (D16).
  - `CrawlerIngest.php:161, 156, 408`: auto-seeded clusters are given confidence 1.00 and created before the match decision. Low (D17).
  - `ingest.php:38, 49, 68`. Medium (9·IN D4).
- **Rules:** 0164 R17, R8, §1.4 note (OUTSIDE_REGION). 9·CI: "no decision section squarely governs cluster assignment".
- **Fix:** derive the hub from the ZIP, return `outside_region` otherwise, and seed clusters after the decision with low confidence.
- **Supported by:** 9·CI, 9·IN.

**F25. W0 indexer reads past refusals and keeps hitting blocked hosts.**
- **Path:** W0 (not a listing writer, but a data-capture step).
- **Rules:** 0164 R18, §1.2 step 5, §1.3, §1.4.
- **file:line and severity:**
  - `indexer.py:269–284`: 401/403/451/406/418 and "thin" pages are still fingerprinted, their links are queued, and the sitemap is fetched. High (H1).
  - `indexer.py:254, 392–400`: no local hold after a block. High (H2).
  - `indexer.py:288–311`: `describe()` skips the hold, `ADMIN_PATH` and status guards. Medium (M2).
- **Fix:** `if page and not why and 200 <= row["s"] < 300:` at 269; set a local hold after 264 and merge holds at 343; early return in `describe()`.
- **Supported by:** 9·IX, **single-pass**. The answer is truncated in M2's fix.

**F26. A scheduled tick changes the live listings schema.**
- **Path:** `ListingIdentity::uniqueKey` and `recheckTick`.
- **Rule:** 0164 §3.1 database guard ("added once the recheck has cleared today's exact twins").
- **file:line:** none given.
- **Severity:** unrated.
- **What the answer reports:** `uniqueKey` rewrites `ident_live` on every row and runs `ALTER TABLE entities ADD UNIQUE KEY uq_ident` with no person and no release. `ensureKeys` runs inside `candidateIds`, so a read writes up to 3,000 rows per ZIP.
- **Fix:** none given; the answer is truncated.
- **Supported by:** 3b·LI, **single-pass**.

**F27. Suggestions: provenance and owner routing.**
- **Path:** `Suggestions::create`, `decide`.
- **Rules:** 0164 R4, R11, §3.5.
- **file:line:** none given (cites `FIELDS['source']`).
- **Severity:** unrated. 3b·SH rates the related §0.8 point "low" and Path B "low here".
- **What the answers report:**
  - The `source` field is optional, so a phone or address can arrive with no source (3·SH, 3b·SH).
  - A crawler "check this?" on a claimed listing is not routed to the owner (3b·SH).
  - `Guides::due()` runs before review (3·SH, 3b·SH).
  - `decide()` overwrites status with no history (3b·SH).
- **Fix:** route to the owner's Check-these when claimed; keep an append-only status history; stamp `carries = person_words` (3b·SH).
- **Supported by:** 3·SH and 3b·SH (source, `Guides::due`); 3b·SH only (owner routing, history).

**F28. Failed intake rows are never surfaced.**
- **Path:** `AutoImport::advance()`.
- **Rule:** 0164 R9 / §1.1 ("failed crawls … a Pulse item").
- **file:line:** none given.
- **Severity:** unrated.
- **What the answer reports:** any throwable is only `error_log`'d.
- **Fix:** count exceptions per row and raise a Pulse step after N (X3).
- **Supported by:** 3b·AI, **single-pass**.

**F29. Private details and page text stored unscrubbed.**
- **Evidence:**
  - `CrawlerIngest.php:496–499`: no §3.4 removal. Medium (D13).
  - `CrawlerIngest.php:500, 502`: `description` and `ai_summary` are stored without the scrub, and `ai_summary` is not marked compiled. Medium (D14).
  - `ingest.php:80`: raw exception messages, which may contain a phone or address, go to the error log. Low (9·IN D8).
- **Rules:** 0164 §3.4, R6, R5, §5.3, §5.4.
- **Fix:** as given in each answer (scrub before write, a compiled flag, log only class, code and index).
- **Supported by:** 9·CI, 9·IN. Relates to F4.

**F30. Numbers and matching in the bulk loads.**
- **Evidence:**
  - `NpiBulk.php:179`: hard-coded confidence. Low (#5).
  - `OpenLoads.php:106, 116`: a linear formula that doesn't halve under 0.5 as its own comment claims; the 0.5 default; Overture's scale used for Foursquare. Medium (9·OL #4; the answer is truncated in its fix).
  - `NpiBulk.php:226–229, 230`: `matches()` confirms on name plus house number only. Medium (#11).
  - `NpiBulk.php:110, 220`: phone extensions corrupt the number. Low (#12).
  - `NpiBulk.php:176`: the phone has no recorded source. Low (#6).
  - `NpiBulk.php:17–18`: a comment says NPI needs one more reference, against C3. Low (#4).
- **Rules:** 0164 header (one home per fact), §3.1, §3.5, §2.4, R11, §12 C3; 0163 §12.
- **Supported by:** 9·NB, 9·OL (each item single-pass).

**F31. Smaller `upsertEntity` defects.**
- **Evidence:**
  - `CrawlerIngest.php:411, 465–487`: an exact-hash match can update a merged-away id. Medium (D10).
  - `CrawlerIngest.php:506–515, 569`: a field taken off may be written back. Medium, depends on code not shown (D11).
  - `CrawlerIngest.php:501, 74, 492`: category stored raw, outside R10's gate. Low (D15).
- **Supported by:** 9·CI, **single-pass**.

**F32. Ingest request handling.**
- **Evidence:**
  - `ingest.php:28–33`: `[]` or a list of scalars is treated as one record. Low (D9).
  - `ingest.php:29, 54`: no batch cap. Low (D10).
  - `ingest.php:86–89`: the receipt counts attempts, not stored records, and has no checksum. Low, conditional (D11).
- **Rule:** input validation; 0164 §1.4 (if W0 posts here).
- **Supported by:** 9·IN, **single-pass**.

**Truncated, not usable as a finding:** 3·IS raises "Old gate still drives publishing (§5.4; §3.5; §12 C4; §15 X2). The stage() docblock says…". The copy ends there (see §8).

## 5. Checked against 0164, 0163 and 0066 (in progress)

- **0066 (nations' land).**
  - Business listings are public under 0066 ("public listings and businesses"), and the funnel places their pins as given.
  - `Nations::pin()` moves or drops a pin only for places, stories, journeys and trips (`SourceIndex.php:635`, `OpenPlaces.php:131`, `Journeys.php:105`, `Trips.php:59`). That's consistent.
  - Nothing in the listings funnel names a held sacred or burial site: those are landmarks, read through `Landmarks::visibleSql()`.
  - *Answers:*
    - 9·NB and 9·CI agree that public business listings on a nation's land are allowed under 0066 §3. 9·CI also cites R7.
    - 9·OL proposes moving open-dataset pins inside `nation_shapes` to the town's public point, and leaves open whether machine-publishing there needs a person (F19). **Conflict** with "places their pins as given".
    - 9·NB notes Kayenta's 11 NPI records are staged like any other, and that whether listings carry the nation's name and visitor rules (R7) is decided downstream (not shown).
    - 9·CI: "Nothing shown here pins a held site."
  - *To do: the place store's people-guard (`DataCleanup` `store_guard`) against F2.* **Still to do:** 3·CL is truncated after its scope (it begins "A general note on the tribal-"), and `intake-cleanup-pass2` and `j9-DataCleanup-php` are empty.
- **R9 (the licence decides) per source.** Filled from the answers; see F17. No path the answers reviewed checks a record's licence against the register before storing:
  - worker intake (3·IS, 3b·IS);
  - Overture and Foursquare (9·OL);
  - NPI (9·NB, register row only);
  - `upsertEntity` (9·CI);
  - ingest (9·IN).

  AutoImport's `keepRule` fails open (3·AI). 9·IX says W0's restrictions are enforced only by the site's hand-out (not shown). The IRS, RIDB, W3, W4/W8 and W9 paths are not covered.
- **R4 (fill gaps, never overwrite a person) against §2.** Filled; see F18, F14 and F27. The answers dispute §2's "intended". Locks cover only what locks; person-entered unlocked values are overwritten (9·CI D8, D9); machine merges can target claimed listings (3·AI).
- **§3.5's floor against §1's gate.** Partly filled:
  - Paths that bypass the gate have no floor: `upsertEntity` (9·CI D1) and ingest (9·IN D1). See F8 and F9.
  - NPI's own pre-check lets a PO Box through (9·NB #3, F21).
  - Whether `evaluate()` applies the floor correctly is unreviewed: the job 9 AutoImport copy is empty, and 3·AI and 3b·AI are truncated before it.
- **0163 §9's switches per path.** The pause on Jason's call, 2026-11-28, applies to W1's creating tasks and the two publishing jobs. Filled; see F16. AutoImport defaults on with no master, workflow or task check (3·AI). NPI bulk, ingest and `CrawlerIngest` have no pause check (9·NB, 9·IN, 9·CI), and W0's check-in lags about 5 minutes (9·IX). Whether the pause actually holds today depends on code the answers could not see (the intake endpoint, `bootstrap.php`) (§8).
- **0066 for every path that can place a pin on a nation's land** (`Nations::pin()`, `Landmarks::visibleSql`). Partly filled: 9·OL (pins as given, F19), 9·NB (staged like any other), 9·CI (no nation handling in `CrawlerIngest`), 9·IN (none in ingest). `Landmarks.php`, `Nations::pin()` and W9 are not covered. See also the naming conflict in the §3 W9 row (3b·IS).

## 6. The independent check

The plan was for one fresh reviewer, given only `decisions/0164`'s rule book and the code paths in §1, never these conclusions, to read the funnel and report what breaks a rule. Its findings were to be added here unedited, each with a yes or no from this lane.

**What this batch has instead.** Three batches of AI review answers (§0), summarised in §4b with their keys. The unedited text is the answer files themselves, which this report points to rather than pastes. Three limits apply before they count as the independent check:

1. **The rule book was not only 0164.** Job 9 reviewed against 0164, 0163 and 0066 ("Everything here comes from the attached file and the three decision records", 9·NB).
2. **The reviewers were not fully blind to this lane's conclusions.** 9·CI and 9·OL quote 0164's 2026-11-28 note, which already contains this lane's legacy-migration finding (F9) and the ~310 people count. Their agreement on F9 is therefore not independent.
3. **Coverage is incomplete.** Twelve answer copies are empty and most others are truncated (§0). `AutoImport::evaluate()`, `IntakeStager::approve()`, `ListingIdentity::resolve()` and `DataCleanup` are not reviewed by any usable answer.

**The lane's yes or no, per finding (for Data & Content to fill):**

| Finding | From | Lane: yes / no |
|---|---|---|
| F2 (support), F8, F9 (support), F11–F32 | §4b keys | ___ each |

A truly fresh reviewer on `evaluate()`, `approve()`, `resolve()` and `DataCleanup` remains *to do, last*.

## 7. Ranked fix list (batch draft)

**Ranking basis (this lane's editorial ordering, for the lane to confirm):**
1. live harm to health data;
2. the answers' severity;
3. the number of answers in agreement;
4. whether the fix is in the funnel (once) rather than per path.

| Rank | Fix | Findings | Strongest support |
|---|---|---|---|
| 1 | Confirm the pause holds on every creating path, and make creating paths check master, workflow and task (default AUTO_IMPORT off) | F16, F8 | 9·IN D6 high; 3·AI; 3b·AI; 9·NB; 9·CI; 9·IX |
| 2 | Close the ungated writer: `upsertEntity`/ingest run floor and readiness or insert as a sighting; retire or route `api/ingest.php` through `stage` | F8, F21 | 9·CI D1 high; 9·IN D1 high |
| 3 | Hold NPI's non-public kinds (home care, residential treatment, hospice, ambulance) out of public listings | F20 | 9·NB #1 high (single-pass; check `npiNotPublic` first) |
| 4 | Never overwrite a person's field, locked or not; replacing a shown value goes through the identity record | F18, F1 | 9·CI D8, D9 high; 9·IN D3 high (verify) |
| 5 | Person and protected checks before staging or insert; IT's 1b `evaluate` change for new finds | F2, F19 | 9·OL #3 high-if; 9·CI D5 medium |
| 6 | Run the two cleanup rules on the September legacy listings (`person_place`, `legacy_unsourced`) | F9 | draft trace; 9·CI D1 (not independent) |
| 7 | The licence decides: fail-closed `keepRule`, a register row with licence and terms before `create_listings`, per-record licence checks, source on every fact | F17 | 9·OL #1 high; 9·CI D12 high; 3·IS + 3b·IS; 3·AI |
| 8 | One identity check under the lock on every path; NPI drops its own measure; `maybe` goes to "Vet this place" | F11, F12, F13 | 3·AI + 3b·AI; 9·CI D2 high; 9·NB |
| 9 | Restrict machine merges to exact, unclaimed, untouched twins | F14 | 3·AI (single-pass) |
| 10 | W0: stop reading refused pages; hold blocked hosts locally | F25 | 9·IX H1, H2 high (single-pass) |
| 11 | Keep dropped NPI records as closure signals; don't delete short batches | F22 | 9·NB medium |
| 12 | Approximate points and ZIP `00000`; geo-hub per ZIP | F23, F24 | 9·CI; 9·IN |
| 13 | Scrub and mark compiled text; keep private details out of storage and logs | F29, F4 | 9·CI D13, D14 medium |
| 14 | Earlier-draft items with no answer support: F3, F5, F6, F7, F10 | n/a | draft only |
| 15 | No schema change from a tick; Pulse for failing rows; suggestion routing and history; numbers from settings; ingest validation; alias on "covered" | F26, F28, F27, F30, F31, F32, F15 | single-pass or low |

## 8. Unverified

**Not checked by any usable answer:**
- `AutoImport::evaluate()` (F2's `looksPerson` at :489 and :142–147; `npiNotPublic` at :214; the floor).
- `IntakeStager::approve()`, `stage()`, `merge()`, `reject()`.
- `ListingIdentity::resolve()`, `locked()`, `fold`.
- `Identity::*`.
- `DataCleanup` (`store_guard`, RULES).
- `CategoryClassifier` (F1).
- `crawler.py` (F3, F4, the `:2860` organisations-only reference).
- `ListingConfidence` (F6).
- `AddBusiness`, `Claims`, `ListingBulk`, `Rebuild`, `ListingLocation` (the lock sources in §2).
- `IdentityJobs`, `GuideLinks`, `Landmarks`, `Nations::pin()`, `IrsBulk`, `RecBulk`, refresh (W2).

**Combined readings across answers, not confirmed by either:**
- Whether `ingest.php:68`'s `true` means `upsertEntity` runs `resolve()` (F12).
- Whether the lock at `ingest.php:67` satisfies 9·CI D3 (F12).

**Line-level differences to reconcile:**
- `CrawlerIngest` lock refusal: draft 543–546 vs 9·CI 544–547.
- Insert: draft :426 vs 9·CI 425–461.
- AutoImport batch: 3·AI "up to 500" vs 3b·AI "up to 150 (maximum 500)", and the ordering.

**Counts to reconcile:**
- People shown as places: draft ~240 (C5) and 49 of 84 sampled, vs 0164's note "about 310" (9·CI, 9·OL).
- The draft's own "84 of 150" vs "all 83 of the sampled ones" in F9.

**Path mapping:** that the answers' `sync-app/lib/…` and `sync-storage/api/ingest.php` are the same files as this report's `app/lib/…` and `website/api/ingest.php`.

**Whether Jason's pause (2026-11-28) holds today on:**
- NPI bulk;
- open loads;
- ingest;
- AutoImport (its `.env` default);
- W0.

These depend on the intake endpoint and `bootstrap.php`, which no answer saw (F16).

**Rule citations the answers disagree on or could not see:**
- `Landmarks::visibleSql` named in 0164 (3b·IS says not) vs 0066 (this draft).
- 0066 on machine-publishing on the Navajo Nation (9·OL's open question).

**Truncated answers whose remaining findings are unknown:**
- 3·AI and 3b·AI (Path 2 onward);
- 3·IS ("Old gate still drives publishing…") and 3b·IS;
- 3b·LI (F26's fix);
- 3·SH and 3b·SH (paths for `HelpFind` and `api/ingest.php`);
- 9·OL (defect 4's fix onward);
- 9·IX (M2 onward);
- 9·CI and 9·NB summary tables.

**Earlier-draft to-dos still open:**
- §2 count from `category_changes`;
- §3 the 1A measures for W1 `coverage`/`targets`, W3, W4/W8, W9;
- §3 the per-id live read and the Show Low comparison;
- §3 W5–W7 and W10–W13 not writing `entities`;
- F10's fields and cause;
- §6 a fresh, blind reviewer.