# board-phone: the Control Board on a phone (2026-11-24)

The Control Board build session (`session_01CXLi5gqPa5AQKoZXC7gZxx`), queue item 1 of `control-board-2026-11-24.md`.
Built on **Sync 03:54 UTC (5eef020)**. Zip: `board-phone.zip` (build in `board-phone/build/`, screens in `board-phone/`).
State: **built and tested on the bench; in review** (Design reviewer → session manager → Jason's pop-up when he's back).
Its send waits for order-m2b's `faq.md` (the only file it shares with a pending zip): once that's live, re-run
`board-phone/faq-edits.py` on the live `faq.md`, rebuild the zip as `board-phone.zip` (never sent yet), zip check, send.

## Files (each started from its live copy)

| File | Change |
|---|---|
| `app/admin/control-console.php` | `tv_cb_qbar` as one Alerts dropdown; `tv_cb_live_alarms` (the one count's alarms); `tv_cb_rail_items` shared by the rail and `tv_cb_pages_menu` (the phone's ☰); `Thread::css()` printed with the board's own styles; `.cb-pill` in the body font; the header's CSS |
| `website/js/admin-control.js` | the filter's Open = the one count (a pressed trip counts; notices under Notices); the ☰ names the page; the alarm filter shows only on the Alarms tab |
| `website/js/tv-ask.js` | `place()`: a notice beside something in a flex row takes a line of its own (under the row when it doesn't wrap) |
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
3. **One alarm count everywhere** (light, Alerts, Board's count, the filter's Open): every open trip (seen or not, pressed
   and waiting for its run or not) and the warnings not yet seen. 02-state-model P9 ("A number shown … is computed from the
   same readings in every place it appears"); `ControlAlarms::counts` (Jason, 2026-11-19, "alarms everywhere"); D5 (an
   acknowledged trip still counts). Live at 04:11 UTC: the light said 12, the chips and rail 14 (they added 2 seen
   warnings), the filter 32 (it added 18 notices, and dropped pressed trips).
4. **Paused now is a group in Alerts, counted on its own**, never added to the alarm count (it isn't an alarm; 0175's
   note: Paused now). Its page stays reachable from it, also when nothing is paused (the old "Paused now: nothing" link).
5. **The alarm list's filter shows with its list (Diagnostics → Alarms) only.** CLAUDE.md: filters on a list are one
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

## Kept, moved, dropped

- The rail's five entries, their counts, icons and order: **kept** at 768px and wider; **moved** into the ☰ on phones.
- Each chip's list, its items opening their part in the side panel, the badges (IR number, trying, n×, cap reading,
  pre-flight), All of Paused now: **moved** into Alerts' groups. The 30-item cap per group **kept**, with a link to the
  alarm list when a group has more.
- "All clear" when nothing is open: **kept** (Alerts 0, green, "All clear").
- ×, Esc, a click outside, one open at a time: **kept** for Alerts and the ☰.
- The filter's choices (Open alarms, Acknowledged, Trips, Warnings, Notices): **kept**; Open no longer includes notices
  (they're under Notices), so it gives the one count.
- Nothing dropped.

## The admin's test (each new state)

- Phone, the board: one line, ☰ Board and Alerts 12 · 2 trips · 10 warnings. Press Alerts: the groups; press an item: its
  part in the side panel. Press ☰: the five pages, the current one marked.
- Nothing open: Alerts 0, All clear, green; Paused now still inside it.
- Diagnostics → Alarms: the ☰ says Diagnostics; the filter appears, saying the same count as Alerts.
- W1's side panel after a press: the card's parts on their own lines, the answer on a line under the card.

## Tests (bench: MariaDB, the mirror's app/ and website/ with the build over them)

- `tools/control-board-tests/board-phone-header.cjs` (34, rendered at 1280 and 390; on the live code it fails, as it
  should: no Alerts, and the green bar at 88 × 845 px), `board-phone.php` (6; on the live code 3 fail: the thread styles
  lost after a thrown-away first thread, the rail counting a seen warning), `board-view-go.cjs` (a page opened by the rail
  or the ☰, shared).
- Updated for the new header: `board-opening.cjs`, `board-tabs.cjs`, `board-views.cjs`, `gauge-panel.cjs`,
  `board-views.php` (D5's rule), `board-words.php` (the Sync group).
- `rendered-all.sh`: 642 passed, 1 known (the `lines` seed note, as on live). `run-all.sh`: only the 7 known live failures.
- Rendered with sample state on the bench; the real run happens on live.

## Not in this release (noted to the session manager)

- W1's row in the systems table still says "Tripped: stopped until an admin resolves it" while its cards say nothing is
  held (live read, 04:11 UTC): words follow the state; a next release.
