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

## The stopped copy (03:2x UTC): "strip admin admin_access: MySQL server has gone away"

`staging_carry_tick` carries a copy in one process for up to 55 minutes, and `StagingSite::conn()` kept each realm's
connection for the whole run. The admin realm's sat idle through main's strip, the server closed it, and the first admin
step after main's (alphabetically `admin_access`, a small table) met error 2006. It would happen again on every copy the job
carries. Fixed in the same release: `conn()` checks a kept connection idle 20 seconds or more (`SELECT 1`), makes it again
when the server has dropped it, and asks each new one for an hour idle (`wait_timeout`). Proof: `tools/staging-tests/conn-gone.php`
(10: the error reproduced word for word, then the next step, the same strip, runs on a new connection).

Live: nothing written to live's tables; the copy stopped before its `base` steps (which add live's outbox triggers), so none
went on. Left: the mirror setting `{starting: true}` in live's `staging_state`, read only by the next copy (it adds the base
steps) and by the page (after this release: "didn't turn on", Try again / Leave it off).
