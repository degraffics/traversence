# Business listings: the build session's record (2026-11-19)

The business listings session (opened by Jason 2026-11-19). Kept current as the record of what's built and what's left.

## 1. Site-audit case #22: owners open their hidden listing (listing-portal-hidden.zip)

Built on Sync 2026-11-19 15:56 UTC; reviewed by the session manager (merged listings made read only).
- `app/lib/Claims.php`: `ownListing()` (the team's own listing, hidden or not; only listings `Team::listingsOf` gives
  them), `hiddenReason()` (why, in the owner's words: the test listing, a private client record, merged into another
  listing, not public yet; anything held by staff, including 0066 and 0164 R7 holds, only "under review"),
  `askAboutHidden()` (Ask our team: our inbox, Reply-To the account email, 3 a session a day). `listing()` is unchanged.
- `website/listing/businessportal.php`: the editor opens for a hidden listing with "Hidden from the public site" at the
  top; a merged listing is read only (Edit the kept listing if on its team, else See it and Ask our team to manage it);
  the list's names open the editor, a held request opens the request, never the same page; a Hidden pill.
- `app/ui/faq.md`: "My business's listing is hidden. Can I still see it?" reworded in place.
- Tests: `tools/listing-tests/portal-hidden.php` (20 on MariaDB). Rendered at 390px with the live code on a test database.
- Left: the daily path check "Edit my listing" (owner) passing on staging once it's live; Ask our team's real send on live.
