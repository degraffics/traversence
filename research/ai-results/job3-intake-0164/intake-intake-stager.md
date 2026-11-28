# Review: listing write paths in `IntakeStager.php` and `CrawlQueue.php` against ADR 0164

**Scope.** These two files call `AutoImport::tryImport`/`fillGaps`, `CrawlerIngest::upsertEntity`/`upsertMetadataKey`, `Identity::*`, `ListingIdentity::*`, `Learning::tier1Done` and `Suggestions::hints`. None of those bodies is attached. Where a finding depends on one of them, it says so and judges only what these files do or fail to do.

**Two rules these files never touch:**
- Neither file references `Landmarks::visibleSql`, `Landmarks::isProtected`, `ListingSafety::isConfidential` or the source register's licence/Use fields.
- Neither file makes an AI call. So "nothing private to a free tier" (§0.8, R5) is not engaged here. The candidates' own extraction happens on the worker and is not shown.

---

## P1. Worker results to staging to auto-publish

`CrawlQueue::lease/describe` → `CrawlQueue::complete` → `IntakeStager::createSource` → `IntakeStager::stage` → `AutoImport::tryImport`

1. **Trigger.** Machine: the Railway worker posts candidates for a leased job. Jobs come from `seed()` (an admin's click, by population gap), `TownCrawl` or `sit:` gaps.
2. **Writes.**
   - A synthetic `crawler_sources` row: url `https://worker.traversence/job/N`, engine `directory`, `added_by_user_id` set to whoever queued the job.
   - One `crawler_raw_index` row per candidate (`status='staged'`).
   - `Identity::fromIntake`.
   - If `tryImport` passes: a public listing (via `approve(..., adminId null)`, per the class comment). Then `PageCache::forget('hub:support')`, so it shows at once.
3. **Person before public?** No for auto-imported rows. Yes for rows left staged.
4. **Breaks or silences:**
   - **The licence decides (R9; §1.4; §2.4).** The only source recorded is the synthetic job URL, with no kind, licence, Use or terms-checked date. The real page URLs survive only inside the JSON payload (`source.start_url`, `sources[field]`, `corroboration`). No register lookup decides whether a fact may be stored. A candidate read from a cite-only chamber page or a commercial directory can be published as our value.
   - **Old gate still drives publishing (§5.4; §3.5; §12 C4; §15 X2).** The stage() docblock says