# pause-1a: the independent check (2026-11-28)

A fresh agent, given only `handoffs/pause-1a/build` and `tools/pause-tests`, read against the live mirror at Sync 01:15 UTC
(d897347), on @84d2b031. Read-only. The IT Manager spot-checked each finding below on the live code.

**Verdict: FAIL.** No new listing is published by itself; every automatic creation stops at `enabled()`. But About text
and hours are still written into published listings, mostly through the identity evidence engine. That widens the IT
Manager's condition.

## Publishing: holds
- `tryImport` and `advance` ask `enabled()` first. The paths that reach them are `listings_tick`, `IntakeStager::stage:216`
  (from `results.php`, `loads.php`, `npi.php`, Find it, `SourceIndex::stageNew`), `Verify::complete:239`
  (`verify.php`, `SecondTier::settleTick`) and Refresh's other locations.
- The only direct `upsertEntity` is in `ingest.php`, which the build diverts to staging.

## What still writes About text or hours while paused
1. **The merge writes back what it just held.** `fillGaps` → `IntakeStager::merge` → `Identity::adopt` (`IntakeStager.php:834`;
   the "closed" branch calls `adopt` directly). `adopt` turns the find into facts, `description` and `hours` among them
   (`Identity.php:927-930`). Then `mergeInto` → `recompute` (`:705`, `:208`) → `applyEvidence` (`:279-304`) writes them onto
   an unclaimed listing's empty field from a single source. So what `holdForReview` just held lands anyway.
   - It runs on every automatic `fillGaps`: `listings_tick`, arrival at staging (`tryImport`'s obvious step, `AutoImport.php:692`),
     Helper's sweep (`Helper.php:66`), and `approve`'s "same" branch (`IntakeStager.php:531`).
   - `ingest.php`'s held records hit it too: `obvious` runs before `hold` is read (`AutoImport.php:692-697`).
2. **Hours from the identity worker.** `api/crawl/identity.php` → `IdentityJobs::complete` (`hours` is one of its finds,
   `IdentityJobs.php:30`) → `Identity::add` → `recompute` (`:238`) → `applyEvidence`.
3. **Hours from the place store.** `api/crawl/sources.php` and `commoncrawl.php` → `SourceIndex::store`, `storeMore`,
   `storePage` → `stageNew:781` → `link()` adds an hours fact (`SourceIndex.php:830-834`) → `recompute` → `applyEvidence`.
4. **Twin merges.** `ListingIdentity::merge:394-397` copies the hidden twin's missing metadata into the kept listing,
   `description` and `hours` included. It runs from `identity_recheck_tick.php:17` (`recheckTick:594`), `OpenLoads::recheck`
   after each `loads.php` batch, and `beforeShow:506`. That data is already ours, but it's still an automatic write.
5. **Not confirmed:** `DataCleanup::settle:1005` un-hides a listing whose sighting row is 'merged', without checking a person
   did it. It only matters if `data_cleanup_tick` is on.

**One fix covers 1-4:** in `Identity::applyEvidence`, and in `ListingIdentity::merge`'s metadata copy, skip `description` and
`hours` while `AutoImport::pausedWhy()` holds words, and hold them for Review.

## Other defects
- **The held About text is lost.** `Suggestions::create` keeps only `Suggestions::FIELDS` (`Suggestions.php:23-25`), which has
  no `description` (confirmed). Review would get "gives an About" with no text. The test's `Suggestions` stand-in stores
  every field, so it misses this.
- **Later finds are dropped.** `holdForReview` keeps one open suggestion per listing and device; a second find returns false
  and is held nowhere.
- **The test only partly exercises it.** `fillGaps`, Refresh, `tryImport` and `advance` are checked by searching the source,
  never run. Nothing covers the identity path. `ingest`'s stand-in `stage()` skips the obvious merge. The header comment still
  says `ingest` refuses.

---

## Second round (@5576476c, 2026-11-28 ~02:15 UTC)

A fresh agent checked the same paths again, on the new head. `pause.php` passes 21 of 21 and `identity.php` 11 of 11 on
MariaDB.

**Verdict: FAIL, on one open path.** All six earlier paths are closed, in code and in tests.

**Checked and clean:**
- all 60 scripts, all 22 crawler endpoints and every publish and un-hide route;
- people's own saves are never held.

**Still open: an automatic re-publish.** `HelpFind::promoteSome`/`promote` (HelpFind.php:128-161; it un-hides at :161,
confirmed by the IT Manager).
- **How it runs:** on page views, with no person: the hub's `HelpFind::block` → `inPlace` → `promoteSome`
  (`hub/index.php:466`, `HelpFind.php:40`), and search's `HelpFind::near` (`UniversalSearch.php:1642`).
- **What it does:** it un-hides any "Help us find" card that has a phone or a street address from any source, checking only
  `beforeShow`. While paused, phone and address still fill: `fillGaps` with no person, or the phone landing through
  `applyEvidence`.
- **Fix:** while paused, promote only a card whose phone or address a person set (source admin, owner or user, or locked),
  or gate `promoteSome` on `AutoImport::enabled()`.

**Smaller issues (not leaks):**
- A held About can't be applied from Review: `SuggestionActions::FIX` and `diff()` have no `description`.
- Joined hours (" | ") stay on one line when applied.
- A twin merge files the About twice: once as `crawler-identity-merge-paused`, then again through `applyEvidence`.
- A drop value whose `meta_value` isn't JSON is neither copied nor held (`ListingIdentity.php:401`).
- Appended values skip `strip_tags`.
- The row closes with "nothing new to add" when only the held fields were new.
