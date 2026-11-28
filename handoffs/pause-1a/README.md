# pause-1a: nothing the system finds publishes by itself (Jason's call, 2026-11-28, "Pause it. Yes")

Built on Sync 2026-11-28 02:52 UTC (after #327 live; faq.md re-applied on it). Sent again as `pause-1a-2.zip` after #328 was withdrawn: Review's Done: line 24px off the right edge at 390 (crawler-review.php, one CSS line). Zip: `pause-1a.zip`; build and `release.json` in `build/`.
Tests: `php tools/pause-tests/window.php handoffs/pause-1a/build <mirror>/sync-app/lib` (18 checks: Review's window and the once-while-paused pass, #330) and `php tools/pause-tests/pause.php handoffs/pause-1a/build <mirror>/sync-app/lib` (21 checks) and
`php tools/pause-tests/identity.php handoffs/pause-1a/build <mirror>/sync-app/lib` (11 checks, the real `Identity::applyEvidence`
on MariaDB; the owner's and staff's saves). Zip check clean.

- `AutoImport::enabled()` is false while `AutoImport::PAUSED` holds words. Every automatic publish asks it first.
- While paused, About text and hours are held for Review (`AutoImport::holdForReview`), never written by the system:
  in `fillGaps` with no admin, `Refresh::complete`, `Identity::applyEvidence` (a merge's adopt, the identity worker, the
  place store) and `ListingIdentity::merge`'s copy (unless a person merges). One suggestion per listing and source; a
  second find is added to it; the About kept in full (`Suggestions::HELD_FIELDS`, machine devices only).
- A person's own save (the owner's `Claims::writeField`, the staff editor's `IntakeStager::adminSave`) writes at once,
  locked, as today: neither asks the pause.
- Not covered, by design: `DataCleanup::settle` un-hiding a merged sighting (path 5 of the independent check). It runs
  only from `data_cleanup_tick`, which is off.
- `HelpFind::promoteSome` (a "Help us find" card back on page views): while paused, only on a phone or address a person
  entered (`tools/pause-tests/helpfind.php`, MariaDB).
- Known, not leaks (the IT Manager, 2026-11-28): a twin merge can file the About twice (merge, then identity).
- `api/ingest.php` stages each record for Review with a hold and answers 200 "held for review".
- Way back: Roll back, or a one-line release emptying `PAUSED`.
- **Tier 4:** the IT Manager (11), first on d32f440b (PASS for publishing; the About and hours gap, now fixed), then
  on 84d2b031 and this head (to be recorded here). Independent check: *(to be recorded)*.
- **After live:** the public 1A counts against 01:30 UTC (`before_counts`), an hour later.

## pause-1a-6 (2026-11-28, the release line)
#335 (pause-1a-5) was withdrawn by the before-and-after check: sideways scroll at 390 on Review, for executive and ops-staff.
Cause: while paused Review shows the newest 200, and finds whose name, address or category has no break point ran wider than their
card; under the old order they sat below the page's cap and were never drawn. Fix: one CSS line in `crawler-review.php` (`.rv-card`
and its text wrap anywhere). Nothing else changed from pause-1a-5.
- Bench: the live mirror plus the build, served by `php -S` with `TV_BENCH=1` (a one-line stand-in for the admin check in the bench's
  copy of `admin-crawler.php`); `php tools/pause-tests/review-seed.php <db>` (1,000 staged finds, every eleventh unbreakable), then
  `node tools/pause-tests/review-390.cjs <bench url> <shots>`: 24 checks at 390 and 1280. Before the fix: page 1,127px wide at 390.
  After: no sideways scroll, cards 3px from each edge, Show 200 more loads the next 200. Screens in `screens/`.
- Pause tests (window 18, pause, identity, helpfind): all passed. Zip check clean on mirror da2708f.
- release.json: its ticket, `"replaces": ["pause-1a-5.zip"]`.
