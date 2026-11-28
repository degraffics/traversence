# Review draft: `sync-storage/api/ingest.php` against 0164, 0163 and 0066

**Scope.** Only `ingest.php` is attached. The decisions that matter most happen inside code that is not attached:

- `CrawlerIngest::upsertEntity`, `CrawlerIngest::normalizeZip`, `resolveMicroClusterId`
- `ListingIdentity::locked`
- `Response::*` and `app/bootstrap.php`

Where a rule's compliance depends on those, I say "not shown" and treat it as a check to make, not a confirmed defect.

---

## 1. Creating and changing listings (lines 54–84)

**D1. No visible floor or readiness gate on a path that creates listings. Severity: high (verify).**

- Line 68 calls `CrawlerIngest::upsertEntity(..., true)`, and line 72 reports `created | updated | matched`.
- Nothing in this file applies the "real business" floor (§3.5, R10), readiness (§5.4) or `AutoImport::evaluate`.
- 0163 §8/§10 (X2) requires creating paths to gate on 0164's floor and readiness.
- The 0164 note of 2026-11-28 records that the September migration "through CrawlerIngest … never passed `AutoImport::evaluate`."
- Whether `upsertEntity` now applies the floor, or what `true` means, is not shown.
- **Smallest fix:** confirm that `upsertEntity` runs the floor and readiness before any public status. If it does not, call the floor and readiness check inside the closure at line 68, before the write, and return a `sighting` or `held` status instead of `created`.

**D2. The comment claims "the one identity check", but `ListingIdentity::resolve()` is not called here. Severity: med (verify).**

- Lines 64–68 wrap the write in `ListingIdentity::locked(...)`.
- 0164 §3.1 requires `ListingIdentity::resolve()` to be called "inside the transaction that writes" by "the ingest API", reading candidates fresh.
- Whether `upsertEntity` calls `resolve()` is not shown.
- **Smallest fix:** confirm it. If it does not, call `resolve()` inside the `Database::transaction` closure (line 67–68) before `upsertEntity`, and merge gaps only on `same`.

**D3. The "updated" path may overwrite fields a person entered. Severity: high (verify).**

- Line 72 shows the endpoint updates existing entities.
- R4 and §3.3 say a field a person entered or locked is never overwritten. A claimed listing is only offered a change ("Check these").
- The field-level guard is not shown.
- **Smallest fix:** confirm that `upsertEntity` writes only to empty or crawler-owned fields. On claimed or person-touched listings it should raise a "check this?" step and not edit.

**D4. Every record is assigned one fixed geo-hub. Severity: med.**

- Line 38 defaults to `'ancient-borderlands'`.
- Line 49 fixes `$defaultGeoHubId`, and line 68 passes it for every record, whatever its ZIP. The comment at lines 35–37 admits this.
- Rules this conflicts with:
  - R17: bulk loads run state by state as regions open.
  - §1.4 note (2026-11-19): out-of-region addresses are logged as `outside` / `OUTSIDE_REGION`, never handled silently.
  - R8: where a record is missing or unclear, the strictest known rule applies.
- How the hub ID is used downstream is not shown.
- **Smallest fix:** derive the hub per record from its ZIP (`zip_coordinates`). If none matches, return a per-record `outside_region` status instead of defaulting.

**D5. The identity lock is keyed on ZIP, with a fallback of `'00000'`. Severity: low.**

- At line 67, every record without a ZIP shares one lock key. A fake ZIP also flows into `normalizeZip`.
- §3.1 calls for a lock on the identity.
- **Smallest fix:** reject records with neither a ZIP nor a phone before the lock (the floor needs an address or phone anyway). Otherwise, key the lock on the normalized phone key.

## 2. Publishing and hiding

- This file has no explicit publish, hide or un-hide call.
- §3.1 says every un-hide goes through `resolve()`. Whether the "updated" path at line 72 can un-hide a hidden twin is not shown. It is covered by the check in D2.

## 3. Reading, and the master switch

**D6. No pause or switch check. Severity: high.**

- Lines 8–25 authenticate and go straight to writes.
- 0163 §9: the master switch makes "every crawler endpoint answer paused (`CrawlQueue::pausedWhy()`)".
- 0164, "How it runs": every step runs only when its switches are on (master, workflow, task).
- Whether `bootstrap.php` or `CrawlerIngest` checks this is not shown.
- **Smallest fix:** after line 23, check `CrawlQueue::pausedWhy()` and the owning workflow's switch. If paused, answer paused (not an error) without writing.

**Reads in this file:**

- Environment variables (lines 12, 38).
- `geo_hubs` (lines 41–43).

No licence-bound source is read or fetched here. **Nothing found** for R9 fetching or R18.

## 4. Licence-bound sources and provenance

**D7. No provenance or licence is required at the boundary. Severity: med.**

- Lines 55–68 pass `$record` whole, and only `zip` is read here.
- §2.4 requires provenance on every fact: `source_url`, `licence`, `exposure`.
- R9 says the licence decides the use. Cite-only, evidence-only and terms-restricted sources (OSM, Google, Facebook) may not supply stored values.
- Validation inside `upsertEntity` is not shown.
- **Smallest fix:** at line 55, reject a record lacking a source URL or source ID and its licence or use, with a per-record `validation_error`.

## 5. A person's words and AI

**Nothing found.** No AI call and no member content appear in this file (R5, R13, §0.8 not engaged).

## 6. Private data

**D8. Raw exception messages go to the error log. Severity: low.**

- At line 80, MySQL duplicate-key errors include the key value, which may hold a phone or address.
- §3.4 and R6 handle private details.
- **Smallest fix:** log the exception class, code and record index, not `getMessage()`.

**Not shown:** PII removal before storage (§3.4), confidential and protected names (R7), and pins on a nation's land (R7, 0066). Under 0066, business listings on a nation's land are public. No nation-specific handling appears in this file.

## 7. Request shape and batch handling

**D9. An empty array or a list of scalars is treated as one record. Severity: low.**

- At line 28, `[]` fails `$body !== []`, so line 29 yields `[[]]`.
- The check at line 31 can then never fire for `[]`.
- `[1,2]` becomes a single record `[1,2]`.
- **Smallest fix:** reject `$body === []` and non-array bodies explicitly before line 28.

**D10. No batch-size cap. Severity: low.**

- Lines 29 and 54 accept any count.
- 0164 §1.4's pacing note (250 or 25 a batch, at most 100 KB, settings in Thresholds) is written for W0's intake. Whether W0 posts here is not shown.
- **Smallest fix:** cap the count from a Thresholds setting and answer 413 above it.

**D11. The receipt counts attempts, not records stored, and has no checksum. Severity: low (conditional).**

- Line 87 returns `count($records)`, which includes errors.
- §1.4 says the receipt reports how many records were stored plus a checksum. This applies only if this is a batch intake under that rule, which is not shown.
- **Smallest fix:** add `stored` (non-error count) and a checksum over the returned `composite_hash` values.

## 8. Audit and the evidence record

- R11 says every write is recorded as it happens. §1.4 calls for `crawl_audit_log`.
- Neither is visible here; both are not shown.
- No defect is claimed; the human reviewer should check this.

## 9. What looks right

- **Fail closed when there is no token:** lines 14–19.
- **Constant-time token compare:** line 21.
- **One transaction per record:** lines 62–69.
- **Lock held around the transaction:** line 67.

---

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| ingest.php:8–25 | No master, workflow or task switch check before writing | 0163 §9; 0164 "How it runs" | high | Check `CrawlQueue::pausedWhy()` and the workflow switch after line 23; answer paused |
| ingest.php:67–68, 72 | Creates listings with no visible floor or readiness gate (CrawlerIngest's legacy path skipped `evaluate`) | 0164 §3.5, §5.4, R10; 0163 §8/§10 X2; 0164 note 2026-11-28 | high (verify, not shown) | Confirm in `upsertEntity`, or run floor and readiness in the closure; return sighting or held |
| ingest.php:68, 72 | "updated" may overwrite fields a person entered or claimed | 0164 R4, §3.3 | high (verify, not shown) | Write only empty or crawler-owned fields; raise "check this?" on claimed listings |
| ingest.php:64–68 | Claims the one identity check, but `ListingIdentity::resolve()` is not called here | 0164 §3.1 | med (verify, not shown) | Call `resolve()` inside the transaction before the write |
| ingest.php:38, 49, 68 | Every record forced into one default geo-hub | 0164 R17, R8, §1.4 note (OUTSIDE_REGION) | med | Derive the hub per record from its ZIP; return `outside_region` otherwise |
| ingest.php:55–68 | No source, licence or provenance required per record | 0164 §2.4, R9 | med | Reject records missing source URL and licence or use |
| ingest.php:67 | Lock keyed on ZIP, defaulting to `'00000'` | 0164 §3.1 (identity lock) | low | Reject records with no ZIP and no phone, or key the lock on the phone key |
| ingest.php:80 | Logs raw exception message (may carry a phone or address) | 0164 §3.4, R6 | low | Log class, code and index only |
| ingest.php:28–33 | `[]` or a scalar list treated as one record; the empty check is dead for `[]` | input validation | low | Reject `[]` and non-object bodies before line 28 |
| ingest.php:29, 54 | No batch-size cap | 0164 §1.4 pace note (if W0 uses it) | low | Cap the count from Thresholds; answer 413 |
| ingest.php:86–89 | Receipt counts attempts, not stored records; no checksum | 0164 §1.4 receipt (if applicable) | low | Add a `stored` count and a checksum of the hashes |

*Draft for human review. Severities marked "verify" depend on `CrawlerIngest`, `ListingIdentity` and `bootstrap.php`, which are not shown.*