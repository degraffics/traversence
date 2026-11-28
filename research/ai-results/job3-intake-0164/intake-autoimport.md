# Review of `sync-app/lib/crawler/AutoImport.php` against ADR 0164

**Scope.** This review covers only the file shown. `IntakeStager::approve()`, `merge()`, `reject()`, `freshMatch()`, `Identity::adopt()`, `SourceIndex::useRule()` and `ListingIdentity::beforeShow()` are not attached, so I cannot say what they do. Where a 0164 rule depends on them, I mark it "not visible."

**Overall.** AutoImport makes no AI calls, so the "nothing private to a free tier" rule (§0.8) does not apply directly here. Its privacy duties fall under R6, §2.2, §3.4 and C13 instead.

---

## Path 1: `advance()`, the pipeline pass

1. **Trigger.** Machine. A scheduled caller (not shown) passes up to 500 staged `crawler_raw_index` rows. The order is by `advanced_at`, then `confidence_score`.

2. **Writes.**
   - It refreshes `match` and `category_suggestions` from `IntakeStager::freshMatch` / `suggestFor`.
   - It then sends each row down one of three branches:
     - **1a, "covered":** `IntakeStager::reject()` with an `auto: covered by #…` note. It also sets `crawler_raw_index.entity_id` and calls `Identity::adopt()`.
     - **1b, "obvious":** `fillGaps()`, which is Path 3.
     - **1c, "eligible":** `tryImport()`, which is Path 2.
   - Otherwise it rewrites `raw_payload`.

3. **Person review.** None. 1a and 1b change or close records with no person.

4. **0164 issues.**
   - **§3.1 (one identity check).** Matching uses `IntakeStager::freshMatch` plus `obvious()` / `coveredBy()`. It does not use `ListingIdentity::resolve()` inside the writing transaction. Nothing here opens a transaction or takes the identity lock. `obvious()` is also called twice per row (lines `if (!self::obvious(...) && ...)` and `if ($m = self::obvious(...))`), so the match can change between the two calls. Whether `approve()` calls `resolve()` is not visible.
   - **§3.2 note, item 1 (who may auto-merge).** Only exact twins merge by themselves: same normalized phone, name and ZIP, **both unclaimed**, and nothing a person entered. `obvious()` breaks this in two ways:
     - It merges on `relation()`'s `same`, which includes weak cases with no phone match: `$sameNum && $nm >= 0.75`, and `$nm >= 0.75 && $noNum` (a route address in the same ZIP).
     - Its `usort` deliberately prefers the **claimed** listing as the merge target.
     - Variations should go to "Vet this place" (W2 `identity`). Claimed or person-touched matches should go to the identity queue.
   - **1a, "covered".** It closes a find as covered by a listed business on phone and address plus `nm >= 0.5`. It keeps the evidence, which is good (R11). However, the R11 rule that a sighting under another name "brings only where it is and how to reach it" is not applied. The find's name is simply dropped, with no alias. Compare 1b, which does add one.
   - **0164 header (switches) and 0163 §9.** `enabled()` reads `.env AUTO_IMPORT`, which **defaults to on**. No master, workflow or task switch is checked in this file.

5. **Smallest fix.**
   - Replace the `coveredBy` / `obvious` / `tryImport` triage with one `ListingIdentity::resolve()` call inside a transaction.
   - Restrict machine merges to `same` where phone, name and ZIP all match **and** neither side is claimed or person-touched.
   - Route the rest to W2 `identity`.
   - Gate `enabled()` on the 0163 §9 switches, and default to off.

---

## Path 2: `tryImport()`, machine publish

1. **Trigger.** Machine. It is called from `advance()`; other callers are not shown.

2. **Writes.**
   - If `obvious()` finds a match, it goes to `fillGaps()`.
   - Otherwise, if `evaluate()` is eligible, it calls `IntakeStager::approve($pdo, $rowId, self::input(...), null, $note)`, which creates a public listing.
   - It then runs `autoNexus()` (Path 4).
   - It also runs a bare `UPDATE entities SET trust_score = GREATEST(0, trust_score + ENTITY_AUTO_IMPORT_ADJUST)`.

3. **Person review.** None. This is intended: §3.5 and §5.4 say a listing past the floor and ready is public at its confidence. A spot-check list follows in `recent()`.

4. **0164 issues.**

   **The licence decides (R9; Decision 9; C2–C4).**
   - **`keepRule()` fails open.** On any exception it sets `$seen[$h] = true`, so a database error makes every host keepable. R9 says terms-restricted sources "can't be set to store or show."
   - **Facebook, Instagram and Yelp are not excluded in code.** `sourceHosts()` hard-excludes only OpenStreetMap, and `keepHosts` additionally only Google. Whether these hosts are blocked depends on whatever `SourceIndex::useRule` returns for them, which is not