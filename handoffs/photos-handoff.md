# Stock photos 404 and the review link's dead end (2026-11-15)

**Release #68 `photos-handoff.zip`: live, synced byte for byte (Sync 22:13 UTC).**
- Cause: `website/uploads/.htaccess` (written by hand) handed missing photos to `media.php` for
  `members|businesses|guests|orders` and the old `hero|journeys|profile` names, but not `system` (the stock library,
  decisions/0143). Every stock photo, the home page's door cards among them, gave Apache's 404 once moved to R2.
- Fix: `Store::uploadsRule()` (run from `app/bootstrap.php`) writes the hand-off block from `Store::KINDS` at the
  top of that file and keeps the rest. The file as it was is kept as `.htaccess-before-rule`. A marker
  `app/storage/.uploads-rule-<hash>` means it rewrites itself when a kind is added. `Store::src()` gives a photo that's
  only in the store `S3_PUBLIC_BASE`'s address, or `/media.php?p=…` (used by `StockLibrary::shown()`).
- Verified live: both door photos load at `/uploads/…` and `/media.php?p=…`, and every kind reaches `media.php`.
- Daily: `tools/page-audit/photos-check.sh`, the last step of `site-checks.yml`. Set the repo variable `PHOTO_MEMBER`
  to a real member photo's `/uploads/…` address so a member photo is loaded too. No signed-out page showed one.

**Follow-up `review-link-login.zip`** (the session manager asked this session to build it): an admin API link opened
in a browser with no staff sign-in (the review email, the Pulse staging step, Open staging) now goes to
`/admin/login.php?next=…` instead of raw JSON. Scripts still get the JSON. ModSecurity lets `next` through both on
the GET and on the form post (checked live).
