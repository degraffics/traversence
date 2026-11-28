# Independent review: listing intake code (IntakeStager.php, CrawlQueue.php) against ADR 0164

**Scope.** This review covers only the two files supplied. Several classes are called but not shown: `AutoImport`, `CrawlerIngest::upsertEntity/upsertMetadataKey`, `Identity`, `ListingIdentity`, `TownCrawl`, `Learning`, `CategoryClassifier` and `Suggestions`. Where a finding depends on them, I rely only on what the shown code passes them or says about them in comments.

**Two caveats on the rule list you gave:**
- 0164 does not name `Landmarks::visibleSql`. Its tribal-land and protected-place rules are R7, §0.6, §0.8 and the 2026-11-27 Q7 note. The only methods it names are `Landmarks::isProtected`, `Landmarks::NATION_SENSITIVE_CLASSES` and `ListingSafety::isConfidential`. I test against those.
- Neither file makes an AI call, so "nothing private to a free tier" (§0.8, R5) cannot be broken directly here. I check the nearest privacy duty instead: R6, R7's confidential listings, and §2.2/§3.4 on emails.

---

## Path 1: Worker crawl → auto-publish
`CrawlQueue::lease` → Railway worker → `CrawlQueue::complete` → `IntakeStager::createSource` + `IntakeStager::stage` → `AutoImport::tryImport`

1. **Trigger.** Machine. `seed()` or `requeueFailed()` queue jobs, `lease()` hands them out, and the worker POSTs candidates.
2. **Writes.**
   - A `crawler_sources` row with URL `https://worker.traversence/job/N`, `added_by_type 'admin'`.
   - A `crawler_raw_index` row with status `staged`.
   - `Identity::fromIntake`.
   - Then `AutoImport::tryImport`, which creates or updates a listing in `entities`/`entity_metadata` (reported as `auto_imported`).
   - `PageCache::forget('hub:support')`.
   - `Learning::tier1Done` for `sit:` jobs.
3. **