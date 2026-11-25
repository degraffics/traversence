# board-phone: the Control Board on a phone (2026-11-24)

The Control Board build session (`session_01CXLi5gqPa5AQKoZXC7gZxx`), queue item 1 of `control-board-2026-11-24.md`.
Built on **Sync 03:54 UTC (5eef020)**. Zip: `board-phone-header.zip` (build in `board-phone/build/`, screens in `board-phone/`).
State: **built and tested on the bench; in review** (Design reviewer → session manager → Jason's pop-up when he's back).
Its send waits for order-m2b's `faq.md` (the only file it shares with a pending zip): once that's live, re-run
`board-phone/faq-edits.py` and `board-phone/tools-edits.py` on the live copies, rebuild the zip as `board-phone-header.zip` (never sent; `board-phone.zip` is #216's name), zip check, send.

## Files (each started from its live copy)

| File | Change |
|---|---|
| `app/admin/control-console.php` | `tv_cb_qbar` as one Alerts dropdown; `tv_cb_live_alarms` (the one count's alarms); `tv_cb_rail_items` shared by the rail and `tv_cb_pages_menu` (the phone's ☰); `Thread::css()` printed with the board's own styles; `.cb-pill` in the body font; the header's CSS |
| `website/js/admin-control.js` | the filter's Open = the one count (a pressed trip counts; notices under Notices); the ☰ names the page; the alarm filter shows only on the Alarms tab |
| `website/js/tv-ask.js` | `place()`: a notice beside something in a flex row takes a line of its own (under the row when it doesn't wrap) |
| `app/admin/tools.php` | `config/alarms`: the Alarms table's Open is the one count; notices under Notices (`board-phone/tools-edits.py`, re-applied on the live copy: order-m2b carries `tools.php` too) |
| `app/lib/ControlBoard.php` | `boardLight`: a part whose trips all started again is amber, "Waiting for its run", never "Tripped: stopped" (the board and the historian) |
| `app/lib/Scheduler.php` | `JOBS`: seven daily jobs' late limits (30–600 min) set to 1560, as `market_tick` and `billing_tick` |
| `app/lib/AuditClock.php` | `dispatch`: no answer at all is tried once more after 5 s, and the connection's own error kept |
| `website/api/health.php` | the jobs section's failure says why (`jobs_error`); refused starts and connection counts stand apart (and `tools/page-audit/towns.mjs` keeps `jobs.json` when the list is empty) |
| `app/ui/faq.md` | "How it opens" and "The light in the admin's top bar" entries (`board-phone/faq-edits.py`) |

## Each behaviour and its source

1. **On a phone the board's pages are a ☰ at the top left, naming the page you're on; no wrapped row.** Jason,
   2026-11-24: "Or a hamburger menu for pages like we've used before?"; CLAUDE.md, phone menus ("☰ tools drop-down left").
   Option A (the pages as the first group of the admin's own ☰) mocked too (`board-phone/option-a-admin-menu-390.png`):
   Modules and Workflows appear twice and "Board" sits above "System Control Board", so B, its own ☰, is built.
2. **One Alerts dropdown, full width on a phone, its groups inside each with its count** (Trips, Warnings, Caps, Checks,
   Sync, Hosts, Paused now). Jason, 2026-11-24: "1 full width dropdown for alerts … instead of this visual chaos". The
   groups and their items are the chips' own (Jason, 2026-11-23, 03 §27), moved inside. At 1280 the chips fold into it as
   well (the hand-off's default, for the Design reviewer to confirm).
3. **One alarm count everywhere** (light, Alerts, Board's count, the queue filter's Open, the Alarms table's Open): every open trip (seen or not, pressed
   and waiting for its run or not) and the warnings not yet seen. 02-state-model P9 ("A number shown … is computed from the
   same readings in every place it appears"); `ControlAlarms::counts` (Jason, 2026-11-19, "alarms everywhere"); D5 (an
   acknowledged trip still counts). Live at 04:11 UTC: the light said 12, the chips and rail 14 (they added 2 seen
   warnings), the queue's filter and the Alarms table 32 (they added 18 notices; the filter dropped pressed trips, the table seen trips).
4. **Paused now is a group in Alerts, counted on its own**, never added to the alarm count (it isn't an alarm; 0175's
   note: Paused now). Its page stays reachable from it, also when nothing is paused (the old "Paused now: nothing" link).
5. **The alarm queue's filter shows with its queue (Diagnostics → Observation and reporting) only.** CLAUDE.md: filters on a list are one
   dropdown button; on the Board page it was a second count with no list under it.
6. **The side panel draws each part of an alarm on its own line.** Cause: `Thread::html` prints its styles once per
   request, on its first call; `ControlBoard::settle` (every draw) can render a Pulse or email copy first and throw it
   away, so the page had none. The board's pages now print `Thread::css()` with their own.
7. **Pills in the body font.** design/table-standard.md (its Control Board note: "the Stage pill is in a code font");
   the Design reviewer's nit on task-trip-3.
8. **A pressed trip is listed and counted** (`data-astate="started"`, from task-trip-3): W1's page said "Nothing here for
   this filter." under its two trips once both were pressed.
9. **The tall green bar** (Jason's 7:46 PM screenshot): Check and start again's answer on a grouped trip was placed inside
   the alarm's row, a flex row, and squeezed to a column (88 px wide, 845 px tall at 390). `TvAsk`'s placement already
   gave a notice its own line in a grid ("never squeezed into a column"); it now does the same in a flex row.

10. **The summary names each group as the list does** ("2 trips · 17 warnings · 2 caps"), and **seen warnings are listed
    under Seen, said as not counted**; no "warning" pill on rows under Warnings. The Design reviewer, 2026-11-24 (its
    changes 1 and 2: "20 warnings" counted Caps and Checks with the 17 Warnings; a pill repeating its heading).
11. **A gauge section on a phone: its Copy on its heading's line, its grid to the card's edges.** The Design reviewer
    (change 4; 0058 §11): the Copy floated right inside the heading, and a grid avoids a float, so it stopped short.
12. **A part whose trips all started again reads "Waiting for its run" (amber) in the systems table, on its light and
    stage; its text "Started again: …"; Check and start again greyed "Already started again: its next run is the check".**
    The session manager, 2026-11-24 ("words follow the state"); live at 04:11 UTC W1's row said "Tripped: stopped until an
    admin resolves it" while its cards said nothing held (task-trip-3's state).
13. **Scheduled tasks: seven daily jobs no longer say Late hours before they're due.** Their late limits were 30–600
    minutes against a daily schedule (`mail_keep`, `notice_digest`, `guest_tick`, `supabase_keepalive`, `region_tick`,
    `identity_recheck_tick`, `data_cleanup_tick`); set to 1560, as `market_tick` and `billing_tick`. Live read at 05:24 UTC:
    all seven Late after running 16 hours earlier. The board's own "is late" alarms count from the schedule and were right.

14. **The audit clock, no answer from GitHub: tried again after 5 seconds, its own error kept.** The session manager,
    2026-11-24 (gauge-requests had no run 02:17 to 05:02 UTC). Live `job_runs` (read through the test Executive): the clock's
    hourly cron job (`audit_clock_tick`, cPanel, :17) ran every hour and needs no visitors; at 03:17 and 04:17 UTC its
    request got no HTTP status at all ("Couldn't ask for gauge-requests: GitHub said nothing", finished the same second),
    and the reason was thrown away; 02:17 and 05:17 worked. Principle 1 (the system tries first); the next hour still asks.

15. **A trip started again and waiting for its run is amber everywhere, as its card** (task-trip-3's state; the Design
    reviewer): its own Alerts group "Waiting for its run", "2 waiting" in the summary, Alerts amber, and the top bar's light
    red only for a trip still holding. `ControlAlarms::counts` names them (`waiting`, a part of `trip`), so every total
    stays the one count (D5: an open trip counts).
16. **Each Alerts item says its thread's title**, as its card and Pulse (decisions/0176 §2; the Design reviewer).

17. **The health report says why its jobs section is empty, and keeps refused starts.** The session manager, 2026-11-24
    (fold into this release). On live `/api/health.php`'s jobs section failed for days inside one try whose catch kept
    nothing (`jobs: []`), taking `jobs_missed` and the connection counts with it, so the daily fetch never wrote
    `site-audit/results/jobs.json`. Now each stands apart, and `jobs_error` carries the exception's message (no people).
    Test `health-jobs.php` (6; live: 3 fail).

## Still open (to the session manager)

- **Lead scores (`sales_tick`) has started only 4 times since Nov 18** (job_runs: each worked in about a second); this release's health report will show whether its starts are refused. Moving it to :02, a test of the :17 pile-up, goes to Jason (the session manager). Earlier note: it **hasn't started since 01:17 UTC** (Scheduled tasks on live, 05:24 UTC: "last ran 4 hours
  ago", while the other :17 jobs ran at 05:17). Its script records its start first and runs clean on the bench, so on
  live it isn't being started, or its start is refused (`Scheduler::missed`, shown in `/api/health.php`'s jobs section).
  Needs the server's side: its cron line's output, or the health report's misses.

## Kept, moved, dropped

- The rail's five entries, their counts, icons and order: **kept** at 768px and wider; **moved** into the ☰ on phones.
- Each chip's list, its items opening their part in the side panel, the badges (IR number, trying, n×, cap reading,
  pre-flight), All of Paused now: **moved** into Alerts' groups. The 30-item cap per group **kept**, with a link to the
  alarm list when a group has more.
- "All clear" when nothing is open: **kept** (Alerts 0, green, "All clear").
- ×, Esc, a click outside, one open at a time: **kept** for Alerts and the ☰.
- The queue filter's and the Alarms table's choices: **kept**; their Open no longer includes notices (under Notices)
  and keeps seen trips, so it gives the one count.
- Nothing dropped.

## The admin's test (each new state)

- Phone, the board: one line, ☰ Board and Alerts 12 · 2 trips · 10 warnings. Press Alerts: the groups; press an item: its
  part in the side panel. Press ☰: the five pages, the current one marked.
- Nothing open: Alerts 0, All clear, green; Paused now still inside it.
- Diagnostics → Observation and reporting: the ☰ says Diagnostics; the queue's filter appears, saying the same count as Alerts. Diagnostics → Alarms: the table's Open says it too.
- W1's side panel after a press: the card's parts on their own lines, the answer on a line under the card.

## Tests (bench: MariaDB, the mirror's app/ and website/ with the build over them)

- `tools/control-board-tests/board-phone-header.cjs` (38, rendered at 1280 and 390; on the live code it fails, as it
  should: no Alerts, and the green bar at 88 × 845 px), `board-phone.php` (6; on the live code 3 fail: the thread styles
  lost after a thrown-away first thread, the rail counting a seen warning), `board-view-go.cjs` (a page opened by the rail
  or the ☰, shared).
- Updated for the new header: `board-opening.cjs`, `board-tabs.cjs`, `board-views.cjs`, `gauge-panel.cjs`,
  `board-views.php` (D5's rule), `board-words.php` (the Sync group).
- `audit-clock-retry.php` (2; on the live code both fail: no retry, "GitHub said nothing").
- `rendered-all.sh`: 642 passed, 1 known (the `lines` seed note, as on live). `run-all.sh`: only the 7 known live failures.
- Rendered with sample state on the bench; the real run happens on live.

## Not in this release (noted to the session manager)

- W1's row in the systems table still says "Tripped: stopped until an admin resolves it" while its cards say nothing is
  held (live read, 04:11 UTC): words follow the state; a next release.
