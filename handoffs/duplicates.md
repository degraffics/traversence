# Hand-off: duplicate listings, fixed at the source and rechecked by the system

Session `session_01CXLi5i1v7RKZQtpJWBxva6`, 2026-11-15, started by the session manager at Jason's ask. **Docs and
analysis only:** no site files changed, no release sent, no files claimed (Release A, 0163 stages 2–6, is building).
Built on the live mirror at **Sync 2026-11-15 12:03 UTC** (`degraffics/traverse-3way-sync` `main`, `ab8089f`). The build
goes in **Release B** (0163 §8 stages 7–11), as part of the stage 8 data loads, before any load is switched on.

**Why:** Data cleanup's `duplicate` rule found **60 pairs** of live listings that look like one business twice (Pulse
case #23, sent to Review as merge offers). Jason, 2026-11-15, in his words:

> "I see this as a process failure and we need to address it at the fundamental level in which the data was captured
> and evaluated."
>
> "I see this a recheck not a pass to human for review. If we have data loads that cause issues the system must be able
> to identify them and correct them. Listings are flexible, since we are building potential listings for claiming, once
> claimed is a whole different conversation."

Jason's further direction the same day (relayed by the session manager) is quoted in `decisions/0164` §3.2's note of
2026-11-15, which records it.

---

## 1. The cause

### 1.1 Every path that creates or un-hides a listing (live code, 12:03 UTC)

Only **two statements** in the live code insert an `entities` row: `CrawlerIngest::upsertEntity()`
(`app/lib/CrawlerIngest.php:415`) and `AddBusiness::create()` (`app/lib/AddBusiness.php:186`). Every path goes through
one of them. Neither runs a duplicate check itself; the only guard inside them is `upsertEntity()`'s **exact
`composite_hash`** (below). Whatever check there is lives in each caller, and the callers use **four different tests**.

**Creating (crawler side: `IntakeStager::approve()` → `CrawlerIngest::upsertEntity()`)**

| Path | Where | Duplicate check | Matches fresh? |
|---|---|---|---|
| Staging, auto-publish on arrival | `IntakeStager::stage()` → `AutoImport::tryImport()` | `obvious()` (merge if `relationTo` = same), `evaluate()` unique check | **Yes**: `enrich()` matches each candidate inside the loop, after the one before it was published |
| The pipeline's pass | `AutoImport::advance()` (`scripts/listings_tick.php`, 150 a run) | `freshMatch()`, then `coveredBy()`, `obvious()`, `evaluate()` | **Yes** (the only path that rematches on purpose) |
| **The second look (Verify)** | `crawler/Verify.php:218` → `AutoImport::tryImport($pdo, $rowId, $c)` | `obvious()` and `evaluate()`, but on **`$c['match']` as it was at staging** | **No** |
| Quick approve (a person's batch) | `AutoImport::quickApprove()` | `evaluate()`'s unique check on the **stored** match | **No** |
| The helper's Approve | `Helper::act('l_approve')` (`Helper.php:309`) | **none** (evaluate for the category only) | **No** |
| Review's Approve | `api/admin/intake/review.php:18` → `approve()` | **none** in the endpoint (the page shows the stored match) | No |
| Review item: "a different business, added as its own" | `admin/review-item.php:61` | a person's decision (the page runs `freshMatch` to show it, l.137) | — but the "not the same" answer isn't stored on the **pair**, so a later check can offer it again |
| Bulk loads through the crawler | `NpiBulk`, `SourceIndex`, `CrawlTargets`, `CrawlQueue`, `TownCrawl`, `CrawlService`, `GuideLinks:585`, `Refresh:160`, `api/admin/intake/crawl.php`, `api/crawl/results.php` | all through `stage()` (above), then wait for Verify / advance / a person | as above |
| **Ingest API** (Railway workers, the old prepopulated load) | `api/ingest.php` → `upsertEntity()` directly | **exact `composite_hash` only** (lower-cased name + address + ZIP, otherwise raw) | — |
| Cluster seeding | `CrawlerIngest::resolveMicroClusterId()` | makes **clusters**, never listings | — |
| OpenStreetMap | its own tables (0065); **no path writes `entities`** | n/a (correct: OSM is never copied in) | — |

**Creating (people's side: `AddBusiness::create()`)**

| Path | Where | Duplicate check |
|---|---|---|
| Visitor "Add a business" and claim-by-adding | `listing/api/claim.php` → `AddBusiness::start()` / `confirm()` | `AddBusiness::duplicates()` offered at `start()`; **skipped with `not_mine`**; `confirm()` creates without checking again (the draft can wait up to an hour) |
| Staff "Add a listing" | `admin/listing-add.php:27` | `duplicates()`, bypassed by **Add anyway** |
| Staff from a suggestion (page) | `admin/listing-new.php:29` | `duplicates()`, bypassed by **Add anyway** |
| **Staff from a suggestion (Pulse case button)** | `Pulse.php:642` → `AddBusiness::fromSuggestion()` | **none** |
| Card scan, Sales | `CardScan.php:267`, `Sales.php:317` | `duplicates()`, bypassed by "anyway" |
| Rebuild (a business's locations) | `Rebuild.php:274` | its own pairing (`sameNamed`, the plan's merge list), not the shared test |
| Private client records | `Clients::makePrivate()` | **none** (created hidden; can shadow a live listing) |
| Test accounts | `TestAccounts.php:151` | n/a (a test listing) |

**Un-hiding (`is_suppressed` 1 → 0)**: none of these runs a duplicate check.

| Path | Where |
|---|---|
| Staff Show in the editor | `admin/listing-edit.php:37` → `IntakeStager::setSuppressed()` |
| Auto-import spot-check, pulled and put back | `AutoImport::suppress($id, false)` |
| Undo of a merge (Find & edit), Rebuild's undo | `ListingBulk.php:158`, `Rebuild.php:327` |
| Data cleanup: Undo per record / run / reason, **Put back** | `DataCleanup::undo()`, `undoGroup()`, `putBack()` (`:1168`) |
| Data cleanup: a sighting approved in Review settles its hidden listing | `DataCleanup.php:953` |
| **"Help us find this business" answered** | `HelpFind::promoteSome()` (`HelpFind.php:155`): a phone or street address arrives, the listing is un-hidden, **even when that phone is another live listing's** |

**Suggestions accepted in Review:** "Same business" merges through `SuggestionActions::merge()` (keeps one, fills gaps,
hides the other with `same_as`, moves the suggestions and the Identity; `listing/view.php:128` sends the old id on with a
**301**). "Missing place" creates through `fromSuggestion()` (above).

### 1.2 The four tests, and how loose or strict each is

| Test | Used by | Name | Phone | Address | Gaps |
|---|---|---|---|---|---|
| `composite_hash` | `upsertEntity()`, `enrich()`/`freshMatch()` "exact" | `strtolower(trim(name))` | — | `strtolower(trim(address))` | No normalizing at all: "Rd"/"Road", "LLC"/none, a comma, a suite, a double space each make a new hash. Not unique in the database (to confirm, §1.5 Q4). |
| `findLookalikes()` | intake matching | `similar_text` ≥ 70 % on a key that drops the/and/llc/inc/co/corp/company/ltd (**not** pc, pllc, lp, llp) | last 10 digits | not compared (returned for `relationTo`) | **At most 5 matches** (`array_slice(…, 0, 5)`), ZIP rows read **without ORDER, LIMIT 2000**, phone rows LIMIT 200: in a busy ZIP the real twin can fall off. Includes hidden listings. |
| `AutoImport::relationTo()` | `obvious()`, `evaluate()`, `coveredBy()`, Data cleanup | `sameName()`: shared words ÷ the **longer** name, with short forms (`ABBREV`) and `Identity::nameKey` (drops LLC, PC, Inc…); ≥ 0.75 | `Identity::digits`: last 10 digits | `Identity::addr` (street words shortened, suite dropped) + ZIP; street numbers compared | **No plurals** ("Guardian Flights" vs "Guardian Flight" = 0.5). **"branch" is tested first**: a different leading number at the same phone and name is another location, so a typo'd or suite-first number makes a false branch. |
| `LiveDupes::same()` (+ `relationTo` in cleanup) | the live-pairs pass, Data cleanup | `Identity::nameMatch`: shared words ÷ the **shorter** name, ≥ 0.8 | last 10 digits, groups of 2–4 | first number + first word | A different measure from the intake's, so the cleanup finds pairs the intake's own rule never looked at. |
| `AddBusiness::duplicates()` | people's add forms | `similar_text` ≥ 70 % within the same **town text** (city meta), narrowed by the **first word** | phone `LIKE` the **last 4 digits**, LIMIT 300 | not compared | Live listings only (a hidden twin is missed); the town must be spelled the same; the 300 cap misses in a large table. |

Phones everywhere: `substr(digits, -10)` reads **"928-555-1234 ext 22"** as `5551234 22`… (the extension's digits shift
the number), and a stored "+1" is fine. Nothing compares websites at the intake except `relationTo`'s `samePlace` for the
"parent name in front" case.

### 1.3 The two holes that make most pairs

1. **Publishing on a stale match.** A find is matched once, when staged. A find that waits (most do: the NPI file alone,
   one source under the line, a missing ZIP) is published later by **Verify** or a person's **quick approve / helper /
   Review approve**, using the match it was given when it arrived. A twin published in the meantime, often its
   **sibling from the same batch**, isn't in that match, so nothing stops the second one. Only `advance()` rematches
   (`freshMatch`), and only for the rows it reaches first.
2. **The last guard is an exact string.** `upsertEntity()` would have merged the twin if the hash matched, but the hash is
   the raw name + address, so "1234 Airport Rd" and "1234 Airport Road" (or a suite, or a comma) are two businesses.

Also: **two finds in one batch** are safe only when the first publishes on arrival (the second is matched after it);
when both wait, hole 1 applies. **Two workers at once** (Verify's tick, `advance()`, a crawl's `stage()`, a person) have
no lock on the identity, so both can pass the check before either inserts. **Records older than the check** (the
prepopulated load through `api/ingest.php`, and listings made before 0074/0076 gave us `relationTo`) were never compared
by today's rule; the live-pairs pass (`LiveDupes`, 5 pairs a run, 40 open at most) and Data cleanup are the only things
that look at them.

### 1.4 The likely route for "Guardian Flight LLC" #8935 / #8922

Same name, same phone, same ZIP, ids 13 apart (made in one window). Data cleanup offered it, so today's `relationTo`
calls it `same`, `covered` or `maybe`, **not** `branch`: the street numbers don't differ (or one has none). Had either
row's match held the other, `obvious()` would have merged it. So:
- Both were **crawler finds** (cleanup reads only `created_by_type = 'ai_crawler'`), staged close together. "LLC" and an
  air-ambulance operator point to the **NPI file** (organization names, one NPI per base or subpart, one main phone),
  which can't publish on arrival (`$fromFile` needs its own site or one more reference).
- Both waited; each was later published by **Verify's `tryImport` on its staging-time match** (neither existed yet when
  they were staged), or by a person's quick approve on the same stale match.
- `upsertEntity()`'s hash didn't catch it because the two address strings differ in writing (a suite, an abbreviation,
  a different second line from the NPI record).

**To confirm** (crawler tables may be in the admin database, `Database::t()`; the database name is in System
Configuration → Database):

```sql
-- Q1: how each of the two was published, and on which match
SELECT r.id, r.entity_id, r.source_id, r.status, r.reviewed_by_user_id, r.reviewed_at, LEFT(r.review_note, 120) AS note,
       JSON_UNQUOTE(JSON_EXTRACT(r.raw_payload, '$.source.kind'))    AS kind,
       JSON_UNQUOTE(JSON_EXTRACT(r.raw_payload, '$.verify.done_at')) AS verified,
       JSON_EXTRACT(r.raw_payload, '$.match')                         AS match_at_publish,
       JSON_UNQUOTE(JSON_EXTRACT(r.raw_payload, '$.where.address'))   AS address
  FROM crawler_raw_index r WHERE r.entity_id IN (8922, 8935) ORDER BY r.id;
-- Verify's route: note starts "auto-import:", reviewed_by_user_id NULL, verified within seconds of reviewed_at, and
-- match_at_publish lacks the other id. A person's: "quick approve" / "approved in the helper" with a user id.
-- No row at all: made by api/ingest.php (or before the intake kept rows).

-- Q2: the two listings as stored
SELECT e.id, e.name, e.primary_zip, e.composite_hash, e.submission_source, e.created_by_type, e.created_at,
       (SELECT meta_value FROM entity_metadata WHERE entity_id = e.id AND meta_key = 'address') AS address,
       (SELECT meta_value FROM entity_metadata WHERE entity_id = e.id AND meta_key = 'phone')   AS phone
  FROM entities e WHERE e.id IN (8922, 8935);
```

### 1.5 For all 60 (the same questions, Jason can run them)

```sql
-- Q3: each offered pair, and how each side came to be published
SELECT s.id AS suggestion, s.entity_id AS a, s.same_as_id AS b, s.status,
       ra.review_note AS a_note, ra.reviewed_by_user_id AS a_by, ra.reviewed_at AS a_at, JSON_UNQUOTE(JSON_EXTRACT(ra.raw_payload,'$.source.kind')) AS a_kind,
       rb.review_note AS b_note, rb.reviewed_by_user_id AS b_by, rb.reviewed_at AS b_at, JSON_UNQUOTE(JSON_EXTRACT(rb.raw_payload,'$.source.kind')) AS b_kind
  FROM suggestions s
  LEFT JOIN crawler_raw_index ra ON ra.entity_id = s.entity_id  AND ra.status = 'published'
  LEFT JOIN crawler_raw_index rb ON rb.entity_id = s.same_as_id AND rb.status = 'published'
 WHERE s.kind = 'same' AND s.device_hash = 'crawler-cleanup' ORDER BY s.id;
-- Count by route: LEFT(note, 11) = 'auto-import' (machine: Verify or arrival), 'quick appro', 'approved in', NULL (ingest/old).

-- Q4: is composite_hash guarded at all?
SHOW INDEX FROM entities WHERE Column_name = 'composite_hash';
```

Admin pages to look at: **Insights → Data cleanup** (the `duplicate` card's examples), **Review** (the "Crawler: live
duplicates" and cleanup offers), each listing's **Sources** panel (`/admin/listings/…`, "Where the crawler found it").

---

## 2. Where the fix lives: the Knowledge Engine (not a separate system)

Jason, 2026-11-15: "the knowledge engine is the master of this process, so make sure you don't create something that
doesn't fold into that." The design is written into the engine's own sections; this file doesn't repeat it.

| What | The spec | Its numbers |
|---|---|---|
| One identity check on every path, the batch check, the database guard, the tuned match rules | 0164 §3.1 ("One identity check, on every path") | 0163 §12, "The identity check and the recheck" |
| Exact twins merge; variations to the vetting; the rest to the queue | 0164 §3.2, note of 2026-11-15 (Jason's words) | 0163 §12 |
| The identity recheck (W2 task `recheck`, on the switches, on the board) | 0164 §6.1 | 0163 §12, §9, §3 |
| "Vet this place": W2's `identity` task as the one entry point | 0164 §6.1 | the retry ladder, the check order (0163 §12) |
| The identity queue: sales lead (the existing pipeline, `Leads::route`, the Call list) or admin task, both still public | 0164 §6.1 ("The engine needs a person", X3) | — |
| W2's row | 0164 §7 | — |
| When it's built | 0163 §8, stage 8, first, before any load is switched on | — |

**Nothing found without a home in 0164.** Two choices made, for Jason to overrule:
- The entry point for vetting a live listing is W2's `identity` task (it already "confirms who a listing is", with
  staff's Look now); W1's `second_look` stays the look at a find before it's listed.
- The identity queue is a kind of step in the engine's one Pulse case (X3), not a new case or a new lead store.

## 3. Build notes for Release B

- **Claims** (decisions/0161), `--change`: `app/lib/CrawlerIngest.php::upsertEntity`, `app/lib/AddBusiness.php::create`,
  `::duplicates`, `app/lib/crawler/IntakeStager.php::approve`, `::stage`, `app/lib/crawler/AutoImport.php::tryImport`,
  `::quickApprove`, `app/lib/crawler/Verify.php`, `app/lib/crawler/LiveDupes.php::tick`, `app/lib/IdentityJobs.php`
  (a reason and the details in doubt on a look), `app/lib/DataCleanup.php` (findDupes, undo, putBack, settle),
  `app/lib/HelpFind.php::promoteSome`, `app/lib/Helper.php::act`, `app/lib/Pulse.php` (the queue's step kind),
  `website/api/ingest.php`, `website/api/admin/intake/review.php`; `--add`: `app/lib/ListingIdentity.php`, the
  migration (identity keys, indexes; the unique key on a later run once twins are gone), the recheck's script (in
  `Scheduler::JOBS` and `Automation::JOBS` under W2), `app/ui/faq.md` entries.
- **What moves on a merge:** list every table holding an entity id before writing (`grep -rn "entity_id\|'entity:'\|listing:" app`)
  and move each with its row ids in the ledger, so Undo is exact. `SuggestionActions::merge()` and `ListingBulk::merge()`
  move only facts, suggestions, the identity and the Nexus today.
- **Order:** keys + migration → `resolve()` and every caller → the batch fold → the recheck, case #23's 60 first → the
  unique key.
- **Tests:** the 60 pairs' shapes as fixtures (same and different numbers, suites, phones with extensions, plurals,
  LLC/PC, a provider at a clinic, two bases of one operator, a claimed side, a person's lock); two workers at once; an
  NPI batch with two records of one operator; a recheck merge and its Undo; the 301; a "Help us find" answer carrying a
  live listing's phone; a sales lead made from the queue and its call closing the step.
- **FAQ:** "Why did a listing I saw move to another link?" (merged; its link follows) and, for owners, "Will you merge my
  claimed listing?" (never without asking you).
