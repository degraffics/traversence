# Review: `CrawlerIngest.php` against decisions 0164, 0163 and 0066

This is a draft for human review. All line numbers refer to `traverse-3way-sync/sync-app/lib/CrawlerIngest.php`. The file has two consumers (lines 10–11): `scripts/migrate_listings.php` and `api/ingest.php`. Neither consumer's source is shown, so any defect that depends on how they call this file is marked as such.

## 1. Creating a listing: `upsertEntity` (383–519)

**D1. No "real business" floor or readiness check before a public listing is made. Severity: high.**
- The only gate is a non-empty name (391–393).
- The insert at 425–461 then creates the listing with `is_suppressed = 0`. That comes from `mapLegacyStatus`, whose default branch is "unknown/new crawler record" (600).
- The docblock says unclaimed is "publicly-browsable" (583–588).
- Result: every new crawler record goes public with no check for:
  - a junk name (§3.5's "Contact Us:", "PO Box 190"),
  - a keepable address or phone,
  - a point in a covered area,
  - a category, or
  - uniqueness.
- This breaks 0164 §3.5, §5.4 (readiness), R10 ("Listing publishes / established") and §12 C4. 0164's 2026-11-28 note confirms the legacy run "through CrawlerIngest" "never passed `AutoImport::evaluate`".
- Smallest fix: before the insert, run the floor and readiness checks. If they fail, insert with `is_suppressed = 1` as a sighting, or hand the record to the intake/`AutoImport::evaluate()` path instead of publishing. That function's signature is not shown.

**D2. The identity check is off by default. Severity: high.**
- `$check` defaults to `false` (383). With it false, a record with no exact hash match is always inserted as new (415, 424).
- 0164 §3.1 requires `ListingIdentity::resolve()` on "everything that creates or un-hides a listing … the ingest API, every bulk load". Running a migration through this file counts.
- Whether `api/ingest.php` passes `true` is not shown.
- Smallest fix: remove the parameter, or default it to `true`, so every path runs `resolve()`.

**D3. The check is not visibly inside a transaction or under the identity lock. Severity: medium.**
- `resolve()` (417) and the `INSERT` (425) are separate statements. Nothing in this file begins a transaction or takes the identity lock.
- This breaks 0164 §3.1 ("called inside the transaction that writes … a lock on the identity while it's checked and written") and 0163 §12 (the 5 s lock wait).
- The caller may wrap it; that is not shown.
- Smallest fix: wrap lines 411–517 in a transaction with the identity lock, or assert that the caller holds it.

**D4. Grey-zone and "differs" matches create a new listing. Severity: medium-high.**
- Only `same` and `covered` count as a match (418). A `maybe` result (0.60–0.95) falls through to a new insert.
- This breaks 0164 §3.2 routing (the grey zone goes to the verification worker or "Vet this place", not to a new record) and 0163 §12 (`maybe`, and `same`/`covered` with differing details, go to "Vet this place").
- Smallest fix: when `kind === 'maybe'`, stage the record or send it to W2's `identity` task instead of inserting.

**D5. People and protected names are inserted as merchants. Severity: medium.**
- `entity_type` is always `'merchant'` (431).
- There is no people check (R6), no `ListingSafety::isConfidential` check, and no protected-name check (R7). That means a shelter's address and pin would be published.
- 0164's 2026-11-28 note found "about 310 people shown as places" from this path.
- Smallest fix: before the insert, run the people rule and the confidential/protected checks. Route hits to `person_place` or a suppressed/held state.

**D6. ZIP-centroid points are published as exact and earn a trust bonus. Severity: medium.**
- When the payload has no coordinates, the ZIP's coordinates become the listing's point (400–405, 454) with nothing marking them as approximate. 0164 §5.4 says the town's middle must be "marked approximate".
- They also add `ENTITY_GEOCODE_MATCH_BONUS` (442–447). A ZIP centroid is not evidence about the business itself (R12 weights).
- Smallest fix:
  - write a `geocode` metadata key with an approximate source (e.g. `s: 'zip'`), and
  - drop the bonus, or award it only for a real address geocode.

**D7. A missing ZIP becomes `00000`. Severity: low-medium.**
- Line 389 defaults to `'00000'`. `resolveMicroClusterId` then returns 0 (121), and the listing is still inserted, public and with no cluster (453).
- This breaks 0164 §5.4 (complete requires "a point in an area we cover").
- Smallest fix: fold this into D1's readiness check (no ZIP or point means not public).

## 2. Changing a listing

**D8. Name and ZIP are overwritten on unclaimed listings without checking who set them. Severity: high.**
- Lines 470 and 479–487 rewrite `name`, `primary_zip`, `micro_cluster_id` and the point whenever the status is `unclaimed`.
- Only the point is protected for person-set geocodes (471–478). Name and ZIP are not protected.
- R4 applies to "a field a person entered", claimed or not, and 0164 §3.2's note says the same for "anything … entered by a person". R11 also says "a name changes only to a fuller form".
- Further risk: if `composite_hash` is not recomputed when staff rename a listing (not shown), a re-crawl with the old name matches at 411 and reverts their edit.
- Smallest fix: add `AND updated_by_type = 'ai_crawler'` (or an equivalent check per field) to the update at 479–483.

**D9. Metadata is overwritten with a single source, and only a lock stops it. Severity: high.**
- `upsertMetadataKey` refuses only when `is_locked = 1` (544–547). Otherwise `ON DUPLICATE KEY UPDATE meta_value` replaces whatever is there (555, 562), including a person-entered value that is not locked.
- The docblock says locks come only from the dashboard (523–524), so edits made in Review, by staff in the admin, or by visitors are not covered (not shown whether they lock).
- This breaks:
  - R4 ("only empty or crawler-owned" fields),
  - R11 ("replacing a shown value takes two" independent sources), and
  - R4 again: a disagreement must raise a "check this?" step. Line 545 only writes to the ledger, and only if `ControlLedger` is already loaded.
- Smallest fix:
  - on an existing row, skip the write unless `source_type = 'ai_crawler'`;
  - send a replacement of a shown value through the identity record (`Identity::recompute()`) rather than writing it directly;
  - on a refusal, raise the R4 step, not just a ledger line.

**D10. An exact-hash match can hit a merged-away id. Severity: medium.**
- Line 411 matches on `composite_hash` alone, without regard to `same_as` or hidden state. Its result is used directly (465–487).
- If a merged-away id keeps its hash (not shown), a re-crawl updates the hidden old id, not the kept one (0164 §6.1, "The old id is hidden with `same_as`").
- Smallest fix: follow `same_as` to the kept id after line 413.

**D11. A field "taken off" a listing may be written back. Severity: medium (depends on code not shown).**
- On non-matched paths, every non-empty key is written (506–515).
- R11 says a value taken off "stays off" and is "never written back". Whether `Identity::listingWrote` (569) enforces this is not shown.
- Smallest fix: skip any key recorded as taken off.

## 3. Facts, provenance and licence

**D12. No source or licence is carried, so licence-bound data can be stored. Severity: high.**
- The record carries no source URL or licence field (378–381).
- `upsertEntity` calls `upsertMetadataKey` with no `$src` (514). `Identity::listingWrote` therefore receives `[]` (569), and `source_type` is just `'ai_crawler'`.
- Nothing refuses values from cite-only, evidence-only or terms-restricted origins (OpenStreetMap, Google, directories).
- This breaks R9 ("the licence travels with the fact"; "Terms-restricted sources can't be set to store"), §2.4 (provenance on every fact), R11, and §0.5.
- Smallest fix:
  - require `record['source']` and `record['licence']`;
  - pass them as `$src`;
  - refuse to store when the register's use isn't "store facts".

**D13. Private details are not removed before storage. Severity: medium.**
- Address and phone are stored as given (496–499), with no §3.4 check (e.g. a "private non-business home address" becomes `[REDACTED_PII]`).
- Smallest fix: apply §3.4's removal before line 506.

**D14. Description and AI summary are stored with no scrub and no "compiled" mark. Severity: medium.**
- `description` and `ai_summary` (500, 502) are stored without the scrub (§5.4: "enforces R6 and R7").
- `ai_summary` is not marked as compiled (R5).
- A crawler `description` may be page text rather than a fact (§1.3 Decision 1; §5.3 "a cite-only source … never as our words").
- Smallest fix:
  - run the scrub on both before writing;
  - store `ai_summary` with a compiled flag;
  - drop `description` unless its source's licence allows showing it.

**D15. Category is stored raw. Severity: low.**
- The free-text category is written straight to metadata (501). Separately, a fallback intent vector is set (74).
- The category is not set through R10's category gate (80%/50%, 0024). The intent fallback is flagged for review (492), which is good.
- Smallest fix: store the raw category as evidence only and let the category step set the shown one.

## 4. Areas: `resolveMicroClusterId` (118–174)

**D16. Every ZIP, from any state, lands in one geo-hub. Severity: medium.**
- Clusters are created and joined in `$defaultGeoHubId` only (140, 157, 164). The code says so itself (108–116).
- A ZIP outside the region seeds a cluster in the wrong hub. 0164's 2026-11-19 §1.4 note logs outside-region addresses as `outside` (for W0), and R17 loads state by state.
- No decision section squarely governs cluster assignment; this is the closest rule.
- Smallest fix: take the geo-hub from the ZIP's state or hub, or skip auto-seeding when the ZIP isn't in a hub's states.

**D17. Auto-seeded clusters are marked fully confident and created before any decision. Severity: low.**
- Line 161 sets `confidence_score` to 1.00 while line 156 names the cluster "needs review".
- The cluster is created (and other clusters' ZIP lists changed, 143–147) at line 408, before the match or decision. So a record that is later matched still alters the taxonomy.
- Smallest fix: compute the cluster after the decision at 424, and seed with a low confidence.

## 5. Parts where nothing was found

- **Fetching, robots and blocks (R18, §1.2–1.3):** nothing in this file. It does no network calls.
- **AI calls / Gemini (§0.8, R5, R13):** none in this file. `ai_summary` arrives already written (see D14).
- **Spending / budgets (R19, 0163 §1–§4, §7):** no paid calls here.
- **Closing a listing:** nothing sets Closed. `mapLegacyStatus` (596–601) never closes, consistent with R12 and C7.
- **Hiding:** only legacy `rejected` hides a listing (599), and an existing record is never un-hidden here. No defect found.
- **The switches (0163 §9):** this file has no pause check. Whether `api/ingest.php` checks `CrawlQueue::pausedWhy()` is not shown. Confirm it in the caller.
- **0066, nations' land:** no nation-land handling exists in this file. Public business listings on a nation's land are allowed under 0066 §3 and R7. The protected and confidential gap is D5. Nothing shown here pins a held site.

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| CrawlerIngest.php:391, 425–461, 600 | New record public with only a non-empty name; no floor, junk-name or readiness check | 0164 §3.5, §5.4, R10, C4 | high | Run the floor and readiness checks (`AutoImport::evaluate`) before insert; otherwise suppress as a