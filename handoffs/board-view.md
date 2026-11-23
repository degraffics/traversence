# The board's opening view (2026-11-23)

Jason, 2026-11-23: the board's opening view wasn't the one he approved in step 3 (`control-board-plan/step3/board/index.html`).
"it should have been built, so if it is not I want it and the other request for the panel built now!" His rail ruling is in
03 §27: **Development · Board · Modules · Workflows · Diagnostics**. With it, his note on the live panel fix: "back and title
conflict. also panel should have some margin".

Built on Sync 14:35 UTC (faq.md rebuilt on it; the other files unchanged since 13:42), with the panel fix (board-panels, #1289) live and checked byte for byte. Build:
`handoffs/board-view/`. Side-by-side images: `handoffs/board-view-proof/landing-1280.png`, `landing-390.png`.

## What changed (6 files)

- `app/admin/control-console.php`:
  - **`tv_cb_rail`** (new): the rail, sticky on the left at 1280 and a wrapped row on phones. Development and Board are the
    board's views; Modules (`/admin/config/register`) and Workflows (`/admin/config/workflows`) are their own pages;
    Diagnostics is the six stages and the tables. Counts: open alarms, modules, workflows, parts watched.
  - **`tv_cb_qbar`** (new): "What's wrong now", one row of chips: Trips, Warnings, Caps, Checks, Hosts (only when a core host
    is down) and Paused now. Each chip opens a small list. Each item opens its part's own page in the one side panel, titled by
    the part's name; a Paused now item opens where it's switched or fixed. With nothing open, one green "All clear". A "Paused
    now" link stays when nothing is paused, so `?tab=paused` always lands.
  - **`tv_admin_mod_control`**:
    - chips, then the shell (rail + main column);
    - the strip (beacon, Paused now, core hosts) and the three boxes moved onto the **Paused now** page;
    - the view tiles row removed (the rail replaces it);
    - the stage and table rows, with the stage's words, are Diagnostics' (`cb-diagnav`), with **Incidents** added to the tables;
    - **Board's landing**: "Is it delivering?" alone, full width. Development is its own page (Jason's ruling).
  - `tv_cb_deliver`: the three boxes are no longer at its top. `tv_cb_development`: unchanged but for where it sits.
- `website/js/admin-control.js`:
  - which parts each view shows: Board = delivering + Development; Paused now; Diagnostics' rows;
  - the rail's current item, with any stage or table keeping Diagnostics current;
  - the layout switch, kept on the device;
  - the chips' lists: one at a time, closed by ×, Esc, a click outside, or choosing an item.
- `website/js/admin-kit.js`, `app/admin/kit-css.php`: the drawer's Back is its own class (`ak-drback`), a small button in the
  header. `.ak-back` was already the page header's round back button, so it drew a circle over the title. A framed page now
  sits inset in the drawer.
- `app/ui/faq.md`: how the board opens.

## Added after the session manager's review (2026-11-23)

- **The gauge cards' Copy** cut their titles ("Communit", "Marketplac"; titles wrapped to 4 lines). The cause:
  - Copy sat in the card's corner, on the title line;
  - the title kept 2.6rem of padding each side for it.

  Each card's Copy is now a slot at its foot, under its buttons (`.cb-gcopy`, tv-copy's `data-copy-at`). The title is whole
  and centred, as in the mockup.
- **One part in the panel** (Jason: "when I am opening the google service registry from the way I routed into the panel
  shouldn't be the only thing in that panel vs having all services?"). In `tv-ask.js`, a page opened in a side panel or a
  drawer at a part's address (`#svc-…`, `#job-…`, `#mc-…`, `#wf-…`, `#pf-…`) shows that part's card alone. "Open the full
  page" has the list. It applies to every part opened that way: services, jobs, hosts, workflows' lines. Notices and the
  site's dialogs stay.
- **`gauges.py` works again.** `site-audit/results/rendered.json` (f0dc77d) has rows for staff sign-ins that timed out, with
  no `status` or `size`. `step3/mockups/src/board.py` now counts such a row as no answer. `gauges.json` and
  `gauges-items.json` are regenerated (`--check` passes). One draft number moved with its inputs: System Configuration's
  preview, 48 to 49. gauge-lifecycle.md §6 and gauges.php 5b now say 49, with the reason (the session manager: "draft
  numbers track the data").
- **Development is its own page** (Jason, 2026-11-23: "development is its own page, not side by side on control board").
  Board is "Is it delivering?" alone, full width, with no 2 columns / Stacked switch. The rail's Development opens the 16
  gauges, the lifecycle panel and Site health's list.

## Kept, moved, dropped

| Block (live #258) | Now |
|---|---|
| The top strip: beacon, Paused now (Needs attention, Waiting on it, Off on purpose), core hosts | Moved, whole, to the **Paused now** page (its chip; `?tab=paused`). Each trip also appears in the Trips chip; a core host down is a red Hosts chip. |
| Is it healthy / What's held and why / What needs me (`#cb-q3`) | Moved, whole, to the Paused now page. The chips carry its counts. |
| The views row (Is it delivering?, Development, Incidents) | Dropped as a row: Development and Board are on the rail, Incidents among Diagnostics' tables. |
| The six stages row, and the stage's words | Moved under **Diagnostics**, unchanged. |
| The tables row (Alarms, Logbook, Ledger, Upkeep, Reports, Scheduled tasks) | Moved under Diagnostics, unchanged, with Incidents first. |
| Is it delivering? (the gauge boxes) | Board's landing, alone, full width. |
| Development (the 16 gauges, the lifecycle panel, Site health) | Moved to its own page: the rail's Development (Jason's ruling). Not on Board. |
| Modules, Workflows | Their own pages, as before, now on the rail. |
| Old addresses (`?tab=alarms`, `?tab=observe`, `#cb-alarm-N`, `?tab=deliver`) | Unchanged: each opens its part, the rail showing where you are. |

Where the build differs from the mockup:
- Development is first on the rail, by Jason's ruling.
- The mockup's Sync chip is the board's Watching panel under Diagnostics. A chip comes with release 2's jobs section.

## Proof (live alarms snapshot, 1280 and 390)

- `tools/control-board-tests/board-opening.cjs`: **64/64** (Board alone, Development its own page) (with every gauge title whole: not cut, nothing over it, at most 3 lines, at 1280 in 2 columns and Stacked and at 390; each card's Copy at its foot), every rail item and every chip pressed.
  - The rail: order, Board current, left or above.
  - The chips: each list opens one at a time, inside the window; ×, Esc and a click outside close it; an item opens one panel
    titled by its part.
  - Paused now: its page keeps the strip and the three boxes.
  - The landing: delivering beside Development, Stacked kept on reload, stacked on a phone. No tile rows and no stage line on
    it; Development starts at the top of its column.
  - Development alone; Diagnostics with every stage and table, and a table keeps Diagnostics current; Board back;
    Modules and Workflows open their pages.
  - No sideways scroll.
- `board-panels.cjs`: **41/41**, on the real Service register: the register entry shows Google Cloud's card alone. Jason's path (the Alarms tab, an alarm, its part, its register entry), now also checking that
  the header's Back, title, "Open the full page" and × don't overlap, and that the framed page is inset. On the live code: the
  inset is 1px, and Back is the round `.ak-back`.
- `board-views.cjs` / `.php`, `board-tabs.cjs`, `lines.cjs`: their old-layout steps go through the rail now (Diagnostics, Paused
  now). The behaviour checked is the same.
- `rendered-all.sh` now reports a test that stops part way as a failure. Before, it counted it as a pass with fewer checks:
  board-views stopped at 16 of 90 and still read 16/0.
- The full sweeps (final build): `rendered-all` 450 passed. It flags dispatcher/r3 as stopped part way: it stops at the same place on the live code (Pulse's playbook drawer, `#pb-dr-play`), and the sweep's old counting hid it. `run-all`: 1522 passed, the same failures as release 1 (gauges.php passes).
