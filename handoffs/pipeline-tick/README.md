# Release pipeline: tick and verdict (2026-11-27)

The session manager's ruling (2026-11-27): its own release, after #318 Live and B.2 step 1, ahead of J1, C.4, place-list
and C.8. It needs the CEO's Tier 4 check first (the release pipeline, StagingPublish's territory). Backend only.

## What it fixes

#317 and #318 each waited an hour between their before look and staging.

- **releases_tick ran every 24 minutes, not 10.** The live read of Scheduled tasks (2026-11-27 05:50 UTC) shows its line
  as `*/24 * * * *`. `Scheduler::JOBS` says `'10min'`, and `handoffs/piece3-check.md` §3 counts on "releases_tick
  (every 10 minutes)". Most of live's 10-minute jobs read `*/15` to `*/24` (`site-audit/results/jobs.json`), and no
  record says who spread them or why: a gap, flagged for the CEO. Only releases_tick moves here.
- **The verdict read came back empty.** #318's before verdict was on main at 05:07:26 (run 45942037912). The 05:24 run
  didn't move it; the 05:48 run did, exactly 60:00 after its upload, which is `BeforeAfter::LATE_MINUTES`. #317 was the
  same (03:24:02 to 04:24:02). The read was `DocsRepo::raw($path, true)` from raw.githubusercontent.com. What it got
  isn't proven, because the job is quiet by design and logged nothing.

## The release

| File | Change |
|---|---|
| `app/lib/DocsRepo.php` | `now($path)`: the file on main through the contents API (with the key), never kept; 404 is null; the API away: `raw($path, true)` |
| `app/lib/BeforeAfter.php` | `verdict()` reads through `now()` and keeps its far timeouts (`far`, at most three), written on the card at the after verdict; `NOTE_MINUTES = 30`: no verdict past it is said once on the release's card, naming the file; `advance()` holds the lock `tv_rel_ba`; `tickLine()`: releases_tick's line to the `'10min'` preset once, remembered (`site_release_settings` `tick_line_10min`), a later Change is a person's |
| `app/scripts/releases_tick.php` | calls `BeforeAfter::tickLine()` |
| `website/api/releases-pull.php` | also runs `BeforeAfter::advance()` |
| `app/ui/faq.md` | the before-and-after entry: it moves on once the look is done; the 30-minute note |
| `.github/workflows/beforeafter.yml` (this repo, with the PR) | "Tell the site the verdict is in": pings `/api/releases-pull.php` after "Commit the verdict", the same manners as `release-ping.yml` (409 cookie, 429 wait 65 s) |

No migration and no new table: `site_release_settings` (live's) holds the one new key.

## Proof

`php tools/piece3-tests/tickverdict.php <root>` (root holds `app/` and `website/`), 27 checks on MariaDB. It runs the real
endpoint in its own process with `tickverdict-pull.php`. Kept: pipeline 22, tickwait 6, closed 10, onstates 7, held 14,
release-tests/record 15. The bench root is the mirror at Sync 02:50 UTC with this build over it.

## Way back

Roll back the release on System Configuration → Releases. The cron line stays at every 10 minutes after a roll back;
**Change** on https://traversence.com/admin/config/scheduler puts it at any preset. Remove the workflow step by a PR.

## Before sending

- Rebuild `faq.md` on the live copy then (R2 and B.2 carry it), re-applying only this entry's two sentences.
- The CEO's Tier 4 check. The Design reviewer's OK on the card's one new line of words.
