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

Recorded in `decisions/0164` §3.2 (note of 2026-11-15).

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

## 2. The fix at the source (Release B, with stage 8, before any data load is switched on)

### 2.1 One identity check: `ListingIdentity::resolve()`

A new library, `app/lib/ListingIdentity.php`, the **only** place that decides whether a find or a new listing is one we
already have. Every creating path calls it **inside the transaction that inserts**, and nothing inserts an `entities`
row except `ListingIdentity::create()`, which calls it.

```
resolve(PDO $pdo, array $cand, array $opts = []): array
  // $cand: name, other names, phone, website, address, city, state, zip, point, category, person-or-business
  // returns ['verdict' => same|covered|branch|place|other|maybe, 'id' => ?int, 'score' => float, 'why' => [..], 'checked' => [ids]]
```

- **Candidates read fresh, every time**, from the indexed key columns (§2.3), never from a match stored earlier: same
  phone key anywhere; same ZIP with the same address key or the same name key; same website page (`WebIdentity`); same
  Google place id or Overture/NPI id where held. Hidden listings count (a hidden twin is still a twin); merged ones
  (`same_as`) resolve to the kept one. No cap of 5: every candidate is scored, the best kept.
- **Verdicts are `relationTo`'s** (0074, 0076, 0081, 0084 kept as they are: `branch`, `place`, `covered` aren't
  duplicates) with the tuning in §2.4, and 0164 §3.2's routing on the score: ≥ 0.95 same → merge (gaps only, R4);
  0.60–0.95 → the grey-zone worker, then the recheck (§3); < 0.60 a new listing.
- **Callers:**
  - `IntakeStager::approve()` calls it before `upsertEntity()`: a `same` verdict merges (`AutoImport::fillGaps`) instead
    of creating, **whoever approved it** (Verify, quick approve, the helper, Review). A person who pressed "a different
    business" passes `['not_same' => [ids]]`, stored on the pair (§3.4), so it's honoured for good.
  - `AutoImport::tryImport()` and `quickApprove()` get the match from `resolve()`, not `$c['match']` (Verify's hole
    closes).
  - `api/ingest.php` goes through `approve()`'s path (or `resolve()` then `upsertEntity()`), never straight to the hash.
  - `AddBusiness::create()` calls it: `same` with an unclaimed listing → the person is offered that listing to claim
    (the add becomes a claim); "Add anyway" / `not_mine` / Pulse's button pass the person's `not_same`, recorded. The
    form's own `duplicates()` becomes a call to `resolve()` (one test, four places fewer).
  - `Clients::makePrivate()`: a `same` verdict links the private record to the live listing instead of making a shadow.
  - **Every un-hide** (`setSuppressed(false)`, `AutoImport::suppress(false)`, `DataCleanup` undo/Put back/settle,
    `HelpFind::promoteSome`, merge undo): `resolve()` first; a `same` with another live listing merges instead (or, when
    a person pressed it, shows the twin and asks).
- **A lock on the identity:** `resolve()` + insert run under `GET_LOCK('tv_ident_<phone key or zip+name key>', 5)`, so
  two workers can't both create the same business.

### 2.2 A check within each batch

`IntakeStager::stage()` and every bulk load (NPI by town, Overture, licence files, `api/ingest.php` batches) first
**group the batch by identity key** (phone key; ZIP + address key; ZIP + name key) and fold each group into one find
(sources and facts unioned, the strongest value per field by 0164 §3.3) before anything is staged. So the NPI's two
records of one operator at one base arrive as **one** find with two sources, not two finds. Groups that `resolve()`
calls `branch` or `place` stay separate.

### 2.3 A database-level guard

A migration (Bluehost-safe, guarded with `information_schema`, safe to run twice):

- `entities.ident_phone` CHAR(10) NULL, `ident_addr` VARCHAR(120) NULL, `ident_name` VARCHAR(160) NULL, written by
  `ListingIdentity::keys()` on every insert and every edit of name, phone, address or ZIP (and back-filled in batches by
  the recheck's first run).
- Indexes: `(ident_phone)`, `(primary_zip, ident_addr)`, `(primary_zip, ident_name)`.
- **A unique key** on a generated column `ident_unique` = `CONCAT(primary_zip, '|', ident_phone, '|', ident_name)` when
  all three are present, else NULL (MySQL allows many NULLs). It is the last guard: the insert of an exact twin fails,
  and `create()` turns the failure into a merge. **Added only after the recheck has cleared today's exact twins**
  (otherwise the `ALTER` fails); until then the index is plain, and the migration adds the unique key on a later run
  when `SELECT … GROUP BY ident_unique HAVING COUNT(*) > 1` is empty. Rows a person kept apart (`not_same`) get a
  suffix in `ident_unique` so the key doesn't force a merge a person refused.
- `composite_hash` stays for history but stops being the identity.

### 2.4 Match rules, tuned to what was found

`ListingIdentity::keys()`, shared by the intake, the cleanup and the recheck:
- **Phone:** digits with any extension removed first (`x`, `ext`, `#` and what follows), a leading 1 dropped, 10 digits
  or none. Toll-free and numbers shared by more than 4 listings count as a main line (as `LiveDupes::MAX_GROUP`): never
  proof alone.
- **Name:** `Identity::nameKey` plus `AutoImport::ABBREV`, legal endings dropped (inc, llc, pllc, pc, plc, ltd, lp, llp,
  corp, co, company, dba and the words after "dba" kept as another name), "&" = "and", **plurals folded** (a final s
  after a non-s: flights → flight; not "services"/"ss" words, which `ABBREV` already folds), small words dropped. One
  similarity measure, `sameName()` against the longer name (so a provider at a facility stays apart), used everywhere;
  `nameMatch()` and `similar_text()` retire from identity.
- **Address:** `Identity::addr` plus street types both ways (rd/road, hwy/highway, ste/suite dropped), ordinal words (1st
  / first), PO Box kept apart as a mailing address (never the street key, as `HelpFind` already does), "US 191"/"Hwy 191"
  /"Highway 191" folded.
- **Street numbers:** `branch` needs two different numbers **and** either a different phone or a distance over 150 m
  (`geohash_7`, 0164 §3.1) when both have points; the same phone, same name and points within 150 m is `same` whatever
  the numbers say (a typo or a suite-first address).
- **Kept as is:** a person's name at a facility is a provider (`looksPerson`), never merged into the facility (0074);
  another business sharing a phone or a building is `place`/`other`; a parent or legal name in front is `same`/`covered`
  (0081); a chain's shared website is "same brand", a Nexus, never a merge (0083).

---

## 3. The self-correcting recheck (replaces sending duplicates to a person)

### 3.1 What it may merge, by itself

A pair qualifies only when **both** listings are:
- **unclaimed** (`owner_user_id` null, status `unclaimed`, no `access`: `SuggestionActions::managed()` false), and
- carry **nothing a person entered or decided** (Data cleanup's R4 test: no metadata locked or from admin/owner/user,
  `updated_by_type` not a person, not approved by a person in the intake, no person's `not_same` for the pair), and
- `resolve()` calls them **`same` at ≥ 0.95** (or `covered` with the same kind of place, as `obvious()` does).

Comments, views, saves and links people made **on** a listing don't stop a merge: they are the visitors', not facts about
the business, and they move with it (§3.2).

### 3.2 How it merges

- **Which stays:** the one with the most evidence (independent sources, then confidence), then the oldest (lowest id),
  then the most views. Its name stays unless the other's is the one people know (R11: the better-sourced shown name);
  the other's name becomes another name (0084).
- **Folded in:** facts filling gaps only (`ListingBulk::merge`'s fill, never overwriting); sources, Identity and evidence
  (`Identity::mergeInto`); **views and counts** (`tv_count_item` totals for `listing:<id>`), **comments and reactions**
  (engage tables by `kind`/`ref`), saves/keeps, guide and journey links (`content_tags.ref = 'entity:<id>'`), open
  suggestions, Nexus membership, Help-find and cleanup notes. Release B lists every table that holds an entity id (`grep
  -rn "entity_id\|'entity:'\|listing:" app`) and moves each, recording the moved row ids for Undo.
- **The old id:** hidden with `same_as` {id, at, by: 0, how: 'recheck', run}, so `listing/view.php`'s **301** sends its
  link on; search and the sitemap drop it at reindex.
- **Logged** in the cleanup ledger (`cleanup_runs` / `cleanup_ledger`, rule `recheck_same`), one entry per pair with
  every value and row moved, **Undo per pair and per run** (Undo un-hides, moves the rows back, removes `same_as`, and
  records the pair as `not_same` so the recheck never repeats it).

### 3.3 When it runs

- **After every data load** (each `stage()` batch, each bulk load, each `api/ingest.php` batch): the ids it touched,
  against everything.
- **On a schedule:** a daily slice across all listings (keyed on the new indexes, so it's cheap), in `Scheduler::JOBS`
  and `Automation::JOBS`, behind the master switch, **shipped off** like the other tasks (0163 §9).
- Its report joins Data cleanup's pass report: merged, sent to a person (by reason), left.

### 3.4 What it never merges: a person decides, with the reason

| Case | Why it goes to a person | The step says |
|---|---|---|
| Either listing **claimed** or managed | "once claimed is a whole different conversation" | "Claimed by its owner: ask them before merging" |
| A person's entry or decision on either | R4 | "Our team / the owner entered … on #…" |
| A person said "not the same" before | their answer stands | never offered again |
| **Another location** (`branch`) | 0074, 0083: a Nexus, not a merge | joins the Nexus by rule (`autoNexus`), no step |
| **Providers or offices at one facility** (`place`, a person's name) | 0074 | no step (not duplicates) |
| Grey zone (0.60–0.95, `maybe`) after the grey-zone worker | 0164 §3.2 | on the retry ladder, a step in "The engine needs a person" |

Pairs a person decides are stored as a pair (`listing_pairs`: a, b, verdict same/not_same, by, at, why) so every path
(`resolve()`, the recheck, `LiveDupes`, cleanup) reads one answer.

### 3.5 The 60 already offered

The recheck's first run takes Pulse case #23's 60 offers (suggestions `kind = 'same'`, devices `crawler-cleanup` and
`crawler-dupes`, status `new`) **before** anything else:
1. Each pair is resolved again under §2.4's rules and checked against §3.1.
2. **Qualifies:** merged as in §3.2; its offer closed as "merged by the recheck" (with the ledger's Undo); the Pulse step
   for it goes.
3. **Doesn't:** stays in Review **with its reason** from §3.4 (claimed, a person's entry, grey zone); a pair that is now
   `branch` or `place` is closed as "not duplicates (another location / at one facility)" with no person needed.
4. The run's report says how many of the 60 were merged, closed as not duplicates, and left for a person, and why.

`LiveDupes::tick()` and Data cleanup's `duplicate` rule stop sending offers: they feed the recheck instead, and only what
§3.4 sends reaches Review.

---

## 4. For the session that builds it

- **Claims** (decisions/0161), `--change`: `app/lib/CrawlerIngest.php::upsertEntity`, `app/lib/AddBusiness.php::create`,
  `::duplicates`, `app/lib/crawler/IntakeStager.php::approve`, `::stage`, `app/lib/crawler/AutoImport.php::tryImport`,
  `::quickApprove`, `app/lib/crawler/Verify.php`, `app/lib/crawler/LiveDupes.php::tick`, `app/lib/DataCleanup.php`
  (findDupes, undo, putBack, settle), `app/lib/HelpFind.php::promoteSome`, `app/lib/Helper.php::act`,
  `website/api/ingest.php`, `website/api/admin/intake/review.php`; `--add`: `app/lib/ListingIdentity.php`, the
  migration, `app/scripts/listing_recheck_tick.php`, `app/ui/faq.md` entries.
- **Order inside the release:** keys + migration (plain indexes) → `resolve()` and every caller → the batch fold → the
  recheck with the 60 → the unique key on the next run once twins are gone.
- **Tests to run:** the 60 pairs' shapes as fixtures (same/different numbers, suites, ext phones, plurals, LLC/PC, a
  provider at a clinic, two bases of one operator, a claimed side, a person's lock); two workers at once; an NPI batch
  with two records of one operator; Undo of a recheck merge; the 301; a Help-find answer carrying a live listing's phone.
- **FAQ:** "Why did a listing I saw move to another link?" (merged; its link follows) and, for owners, "Will you merge my
  claimed listing?" (never without asking you).
