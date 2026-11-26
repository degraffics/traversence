# Staging: Keep staging caught up says where it stands (2026-11-26)

Jason pressed **Keep staging caught up**; the Staging page kept saying "(off)", kept the button and the Now line said "not
kept caught up" while the fresh copy went in (live read 03:09 UTC). Session manager's brief: words follow the state, fix in
place, admin only, check every other button on Staging and Team, tell it before sending.

- `change.patch`: the change against Sync 02:36 UTC (8fb387d): `app/lib/StagingSite.php` (`mirrorState`, `copyHold`, the
  `base` step's guard after Don't turn it on, Start staging over named in messages), `app/lib/StagingApi.php` (summary's
  `mirror_state`, the Now line), `app/lib/StagingPublish.php` (preview and start refuse on half a copy),
  `app/admin/sections.php` (the notice, the box's four states, Set up / Preview / Publish / Schedule greyed with why, the
  preview line, state lines without ×), `app/ui/faq.md` (one new entry, one button name).
- Waits for #298 (batch-live-fix, also carries `faq.md`): after its sync, re-apply on the live copy, zip, send.
- Tests: `tools/staging-tests/mirror-on.php` (19), `mirror-words.cjs` with `page-router.php` and `mirror-seed.php` (42, at
  1280 and 390); `unit.php` 65 and `db.php` 49 still pass.
