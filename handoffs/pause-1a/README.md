# pause-1a: nothing the system finds publishes by itself (Jason's call, 2026-11-28, "Pause it. Yes")

Built on Sync 2026-11-28 01:51 UTC (after #326 live). Zip: `pause-1a.zip`; build and `release.json` in `build/`.
Test: `php tools/pause-tests/pause.php handoffs/pause-1a/build <mirror>/sync-app/lib` (21 checks). Zip check clean.

- `AutoImport::enabled()` is false while `AutoImport::PAUSED` holds words. Every automatic publish asks it first.
- While paused, About text and hours are held for Review (`AutoImport::holdForReview`), never written by the system:
  in `fillGaps` with no admin, and in `Refresh::complete`.
- `api/ingest.php` stages each record for Review with a hold and answers 200 "held for review".
- Way back: Roll back, or a one-line release emptying `PAUSED`.
- **Tier 4:** the IT Manager (11), first on d32f440b (PASS for publishing; the About and hours gap, now fixed), then
  on 84d2b031 and this head (to be recorded here). Independent check: *(to be recorded)*.
- **After live:** the public 1A counts against 01:30 UTC (`before_counts`), an hour later.
