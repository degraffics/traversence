# J1: jurisdictions reviewed by the team (built, not sent)

Data & Content session, 2026-11-27. Built on the live mirror at Sync 2026-11-27 02:50 UTC; `faq.md` and `sections.php` are
rebuilt on the sync after R2 (#317) and B.2 step 1 before zipping (the session manager's order OK, its slot). Sources:
Jason's R8 ruling (2026-11-27, "advisory, not a mandate"; 0164 R8/R17 note, PR #1492), Q8 "Show" (PR #1508), the session
manager's ruling (option b: OPENED kept, drafts that say "Found by search, not yet read against its official text").

In `build/`:
- `app/lib/Jurisdictions.php`: `review()` by a person or a team role (`reviewed_as`), attorney optional, refused while
  any law is unread (the CEO's Tier 4 fix); `readLaw()` (the official link, the unread words off, logged, back to draft);
  `undoReview()`; `reviewer()`; `tier()` per Q8.
- `app/lib/HubOpening.php`: `lawSigned()` reads the reviewed US and US-<state> records (it compared 'AZ' with 'US-AZ').
- `app/admin/sections.php`: the Jurisdictions page: Mark read beside each unread law, Mark reviewed greyed with why
  until all are read, the attorney's review optional, Undo the review.
- `app/migrations/2026-11-27_jurisdictions_team_review.sql`, `app/ui/faq.md`, `release.json`.

Tests: `tools/jurisdiction-tests/j1.php`, 31 checks on MariaDB (with the landmark gate downstream of `tier()`). Screens:
`screens/jurisdictions-1280.png`, `-390.png`. Tier 4 review: CEO, 2026-11-27, Prior Ruling Check yes (0164 R8 note, Q8;
R17 repair). Design reviewer: asked.

Reading the laws (the follow-up release, by role 25) waits for the statute hosts: uscode.house.gov, www.azleg.gov,
nmonesource.com, leg.colorado.gov, le.utah.gov, www.govinfo.gov. Until then the records stay drafts, which is safe.
