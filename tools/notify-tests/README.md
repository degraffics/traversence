# tools/notify-tests/

The index of this folder, moved here from the root README to keep it a short map (CLAUDE.md "Keep this file lean"). Keep it current when files are added, moved or removed.

## Index (moved from the root README, 2026-11-28)

decisions/0172's tests: `notify.php` (step 2, the one dispatcher on a real MySQL or MariaDB: recipients by role, once, threads, choices, quiet hours and the release job, Stop these, notices through it, the callers); `comms-engine.php` (0167's setup, the seal and .env secrets, push and pauses in Notify, the six levels, flags and tags, mailboxes in Pulse, SES receiving, the Sent view); `mail-transport.php` (IMAP, POP, SMTP, MIME, safe HTML, Microsoft Graph, no network); `push.php` (VAPID and RFC 8291 against its test vector); `listing-messages.php` (+ `.sql`: cards, Leave a message, its pace and protections); `step7.php` (comment replies and the notice's buttons, booking reminders, groups); `pulse-act.php` (a row's Pass it on, Protect and Add to, conversations and Reply, by context); `admin-stream.php` (staff messages and mail as rows, mail's own actions, grouped alarms, A task); `member-pulse.php` (needs as rows); `push-pace.php` (push-polish: one push per thing, renotify only on a rise or Critical, the pace and its summary, the badge, the app manifest)
