# Search release 2: places and honest hours (held)

Frontend & Shop's half of the urgent-care plan (decisions/0181's note #1618; 0060's note #1619), slot #9. It goes after the
IT Manager's release 1 (#5) is live and synced. **Held:** nothing here is in `main`'s tools until then. The checker change
(`tools/page-audit/paths.mjs`) reads the listing page's new line, so it merges with the release, not before.

**What it does**
- `Hours::now($week, $tz)` and `Hours::zone()`: where a place stands now in its own zone ("Open · Closes 9 PM", "Closed ·
  Opens 8 AM", "Hours not known"). The state zone table moves to Hours, its one home (`JourneyPlan::timezone` reads it).
  "24/7" now reads as open 24 hours (the listing audit's F4).
- Search rows (`SearchIndex.php`) and situation rows (`Situations.php`) carry `hours_now`; Open now is judged in the
  listing's zone, never the visitor's clock; "Open 24 hours" only when its hours say so.
- `js/search-panel.js`: the row's line; an everyday local need with no place opens "Where are you?" (Auto-detect first),
  once for its words; Esc closes only the picker, the words and results stay (Chrome clears a search box on Esc).
- `listing/view.php`: the line over the hours (`data-hours-now`), kept (no ×).
- `faq.md`: "Where are you?" for everyday searches; "Does search say whether a place is open?".
- removes `app/ui/faq.md.orig` (read by nothing).

**Rebuild on the sync after release 1:** copy the live files into a folder laid out as the mirror, run
`python3 patch.py <folder>`; every anchor is asserted. Then, at IT's seam: swap `Situations::openState($row)`'s body to
`Hours::now` (agreed with the IT Manager) in place of this patch's Situations hunk; also move
`UniversalSearch.php:1204`, `api/search_listings.php:482`, `SearchReading.php:112` and `hub/index.php:184` to `Hours::now`
(F4's other callers).

**Tests** (built on Sync 2026-11-27 19:50 UTC, before release 1):
- `tools/search-tests/hours-now.php <app/lib>`: 15/15 (zones, past midnight, 24 hours, none known, a bad zone).
- `tools/search-tests/hours-and-place.cjs <base>`: 28/28 at 1280 and 390 (row lines, open now, Where are you?, Esc
  keeps the search, "near me" unchanged, a non-need asks nothing, the listing's line, no sideways scroll, no errors).

**Still to do before sending:** the rebuild above; Design reviewer at 1280 and 390 (the row line, the listing line, the
picker's words); the claim (`checkout.py`); send.py with `release.json` (removes: `app/ui/faq.md.orig`).
