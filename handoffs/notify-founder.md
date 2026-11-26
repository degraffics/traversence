# notify-founder: the site emails the Founder itself

Jason, 2026-11-25 (through the session manager): "If I have to allow emails in claude that's a problem. Can you use the
system for emailing?" Built and held, 2026-11-25, on Sync 18:38 UTC. A Tier 2 release (0179's note "Tier 2 publishes on
its own once Piece 3 is live"); until then it goes in the daily batch.

## What it does
The site's own dispatcher (`Notify::send`, decisions/0172 and 0176: Pulse first, the system's own email, free) emails the
Founder (`CommsSetup::founders`: active staff logins at the founder level; nobody else) three things, each once
(`founder_mail_sent`):
1. **The daily batch waiting**: `ControlGauges::latestBatch` (today's, or the latest until it's approved or replaced; the
   session manager, 2026-11-26), one email per date, thread `batch:<date>`. The Work queue's strip uses the same rule.
2. **A Tier 3 decision** reaching "Waiting for you" (`GaugeTasks::sendBrief`): its brief, thread `task:<id>`.
3. **The session manager's messages**: `releases/batch.json`'s `messages` (`{id, at, subject, body}`, id
   `^[A-Za-z0-9._:-]{1,80}$`, subject required, body ≤ 2000, at most 20), carried into gauges.json by `gauges.py`.

Never on staging (the job exits there, is in `NOT_ON_STAGING`, and `once()` checks `Staging::here()`). No Founder: nothing
is marked sent, so it goes once there is one. The job `founder_mail_tick` runs every 10 minutes (`Scheduler::AUTO`,
`QUIET`: it logs only when it sends).

## For the session manager
To email Jason, add to `releases/batch.json`'s `"messages"` and merge: it reaches the site with the next gauges run.
Use a new id each time (a used id is never sent again).

## The zip (`notify-founder/notify-founder.zip`, build in `notify-founder/build/`)
New: `app/lib/FounderMail.php`, `app/scripts/founder_mail_tick.php`, `app/migrations/2026-11-26_founder_mail.sql`
(CREATE TABLE only). Changed: `Notify.php` (three kinds), `ControlGauges.php` (`parse` keeps `messages`; `latestBatch`), `control-console.php` (the strip uses `latestBatch`),
`GaugeTasks.php` (`sendBrief` calls `FounderMail::decision`), `Scheduler.php` (the job), `staging-tables.php`
(`founder_mail_sent` in OWN, R14), `faq.md` (one entry). Repo side: `tools/page-audit/gauges.py` (`_messages`),
`releases/batch.json` (`messages: []`).

Claims to take at send time: `app/admin/control-console.php::tv_cb_wq_batch`, `app/lib/Scheduler.php::`, `app/lib/staging-tables.php::`, `app/lib/ControlGauges.php::parse`
(change), and the adds on Notify.php's KINDS, GaugeTasks::sendBrief and faq.md.

## Proof
- `tools/notify-founder-tests/founder.php <root>/app`: 17 checks on MariaDB; `tools/team-tests/batch.php` 21 and `batch.cjs` 18 for the strip; a copy with the once and staging guards
  removed fails 5.
- Roll back: `tools/team-tests/rollback.sh` on the bench, files match live byte for byte, every PHP file parses; left
  behind: `founder_mail_sent`.
- Zip check: clean but for the three change claims (taken at send).
