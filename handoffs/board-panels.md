# The panel fix (2026-11-23)

Jason's first item from his screenshot of the live board ("I want to see the panel fixed first"), on its own, ahead of the
jobs and words release. Built on Sync 04:13 UTC. Zip: `releases/inbox/board-panels.zip`; build in `handoffs/board-panels/`.

## What he saw, and why

| Item | What he saw | Cause |
|---|---|---|
| 2 | An alarm opened from the Alarms tab, then "Its process's page", then "Its register entry": two headers, two ×, two "Open the full page" | The admin kit's drawers (`admin-kit.js`) kept their own stack, two deep. A link inside a drawer's frame didn't know it was in a panel (it isn't `peek=1`), so `TvAsk.peek` opened a third panel inside the frame. `one-panel.cjs` tested only the site's side panel, never the kit's drawers. |
| 3 | "Copy" twice, stacked, at the top of Identity and role | The whole card has its own Copy. With no heading of its own, its button went in the card's corner, right over zone 1's Copy. |
| 5 | Panels titled "Its process's page", "Its register entry"; the drawer's subtitle "Warning · svc:google" | The links' words became the panels' titles; the subtitle printed the raw id. |

## What changed (8 files)

- `website/js/admin-kit.js`: one drawer at a time. What opens from it (a page, another item) replaces what it shows, with
  **← Back** to each step; the browser's back steps back. ×, Esc and a click outside close it, Back and all. A drawer's own
  frame sends its links up to that drawer, never a drawer inside it; inside the site's side panel it uses that panel.
  `TvAdmin.isOpen()` (new).
- `website/js/tv-ask.js`: `TvAsk.peek` inside a drawer's frame, or with a drawer open, shows the link in that drawer.
- `website/js/tv-copy.js`: a whole whose first part has a heading puts its button there as **Copy all**, beside the part's
  own **Copy**; Copy all copies the whole card.
- `app/admin/tools.php` (`config/alarms`): the drawer's subtitle and its link name the part (`ControlAlarms::partName`).
- `app/lib/Operations.php` (`alarms`): the cap alarm's register entry is "<Service> in the register", its panel titled by the
  service's name (the fix's 4th element).
- `app/admin/control-console.php` (`tv_cb_alarms`), `operations-console.php` (`tv_ops_wf_page`): a peek fix's panel takes
  its 4th element as its title when it has one.
- `app/ui/faq.md`: the Copy entry (Copy all), the one-panel line (the admin's drawers).

Claims: admin-kit.js `detail, draw, frame, layer, paint` (change), `head, stepBack, shutAll` (add); tv-ask.js `peek`;
tv-copy.js `(top), place`; tools.php `config/alarms`; Operations.php `alarms`; control-console.php `tv_cb_alarms`;
operations-console.php `tv_ops_wf_page`. No migration, no switch, no outside call.

## Proof

- `tools/control-board-tests/board-panels.cjs`: **35/35 at 1280 and 390**. It uses `board-panels-seed.php` (the live alarms
  snapshot plus Google Cloud's cap alarm) and `board-panels-router.php` (the board, the Alarms tab's table and the alarm's
  drawer from `tools.php`'s own list and get, the part's own page, the register card). It follows Jason's path:
  - the Alarms tab;
  - a click on Google Cloud's row;
  - its drawer (its address `#open=config/alarms:<id>`);
  - "Google Cloud" (its own page): one panel, Back;
  - "Google Cloud in the register": still one panel, one header, one ×, one "Open the full page", titled "Google Cloud";
  - Identity and role's heading has one Copy and one Copy all, not overlapping, and Copy all copies the whole card;
  - Back twice returns to the alarm and its address;
  - Esc, × and a click outside close it; on a phone the drawer is the whole width, and × closes it.

  No sideways scroll.
- The same test on the live code: 16 failed. They reproduce what he saw: two drawers, the register entry inside the second,
  Copy overlapping Copy, "Its process's page" and "svc:google".
- `rendered-all.sh`: 376 passed, 0 failed (board-panels added). lines.php's seed note predates this release.
- `design-tests/table.mjs`: the same single failure before and after ("a table of groups only"), so it predates this release.
- `run-all.sh`: 1523 passed. The failures are the same 7 as on the live code, plus release 2's own tests (`board-words.php`, `old-titles.php`), which this release doesn't carry.

## Next

The jobs and words release (`handoffs/board-fixes/`):
- `Scheduler::sync`'s upkeep behind its hourly guard;
- the lateness check;
- the health report's jobs section;
- Google Cloud's title with its reading;
- old-titles;
- Google's "Signs in as" and account links.

It is rebuilt on this release once this one is live.
