# Tables and the Pulse page: the standard and the plan (draft for Jason, 2026-11-19)

Jason, 2026-11-19: "The table presentation needs a standard implementation plan." "Not sure about the hover tool bar?"
"Top half of page seems very cluttered." Screenshots: System Control Board's process table, Pulse's stream, Pulse's top.

## What's there now

28 files draw their own table (the admin consoles, `pulse-ui.php`, `vault.php`, `outside-services.php`, 15 admin pages,
`tv-cost.js`, `tv-domains.js`), each with its own class names (`ak-tbl`, `in-tbl`, `dm-tbl`…), header style, row height,
empty cell and date format. The kit's own table (`app/admin/widgets.php` `tv_w_table`, `js/admin-kit.js`) is used by the
manifest sections only. So the same idea looks different on every page.

What the screenshots show:
- **Control Board:** three columns (Last 24 hours, Speed, OK) hold only "–" on every row; the Stage pill is in a code font;
  "Working." repeats the pill; the row's one button sits far from its name.
- **Pulse stream:** the subject and its "What it means…" run together in one cut-off line; the date is cut ("Nov 19, …");
  "FROM ▲" plus a green ▾ is two controls for one column; the first column's "! ▾" is unclear.
- **The hover toolbar:** seven unlabeled icons appear over the subject, covering the text you were reading; one is
  greyed with no reason; "Pin in your own list" is the only way to learn what a button does.
- **Pulse's top half:** Quick Actions, Your day, a role picker, six queue pills, a bell, a gear, a +, the search, four
  folder pills, then the stream's own title (seven rows before the first message).

## The standard (one table, everywhere in the admin)

1. **One component:** `tv_table()` in `app/admin/kit-table.php` with `js/tv-table.js`; pages pass columns and rows,
   never their own `<table>` styles. The same component draws the manifests' tables (it replaces `tv_w_table`'s markup).
2. **Columns:** a status mark first (icon, colour and word, never colour alone), the name (the link that opens the row),
   then data, then the row's actions last. Numbers and dates right-aligned. A column that's empty on every row is hidden,
   with "3 empty columns hidden" under the table.
3. **Headers:** sentence case, small, the meta colour; a click sorts (▲▼ shown only on the sorted column). Filters are
   one dropdown button above the table (CLAUDE.md: icon, choice, count, ▾), not controls in the header.
4. **Rows:** one height (44px; two lines only when the row has a sub-line, the sub-line in the meta colour, never run
   into the title). Hairline between rows, a soft tint on hover, no zebra stripes. Long text ends in "…" with the whole
   text in the row's drawer, never cut dates: dates through `TvTime` ("Nov 19, 10:04 AM MST", or "10:04 AM" today).
5. **Actions:** at most one visible button per row (the row's main act: Check, Reply, Open), in a fixed actions column,
   plus **⋯** for the rest as a menu with icon and words ("Pin in your own list", "Archive"). A greyed item says why
   (CLAUDE.md). Nothing appears on hover over the text. The same ⋯ as the public cards (the card plan, 2026-11-19).
6. **Selecting rows:** a checkbox column only where there's a bulk action; the bulk bar appears above the table when
   one is ticked.
7. **Empty and loading:** "Nothing here yet" with the way forward (never a blank box); a skeleton row while loading.
8. **Phones (390px):** each row becomes a card (status, name, sub-line, the one button, ⋯), edge to edge (§11).
9. **Levels:** the table sits on a section (`--tv-sec`), rows on the card colour; the header row on the section colour.

## Pulse's top half (one header, then the list)

- **One line:** Pulse's mark and title, the role picker (only if you hold more than one role), the search (widest), the
  bell and the gear. The stream's own second title goes.
- **One filter button** under it, left: "Inbox · All queues (17) ▾" holding the folders (Inbox, Pinned, Follow up,
  Archived) and the queues (Operational Queues 17, Alerts 8, Order Mgmt 4, System Health 12, Mine 2, Resolved 1),
  with + New beside it. Alerts' count stays red on the button when there are any.
- **Quick Actions and Your day** become one folded "Today" strip at the top of the stream (closed by default, remembered),
  or a right column on wide screens: Jason's choice.
- Result: two rows before the first message instead of seven.

## Order (each its own release, each reviewed)

1. **T1, the component:** `tv_table()` and `tv-table.js`, its tests (`tools/design-tests/table.php`), the Control Board's
   process table moved onto it (the screenshot), and the ⋯ menu shared with the public cards.
2. **T2, Pulse:** the stream on the component (no hover toolbar; one visible action, ⋯), and the top half as above.
3. **T3–T5, the rest by module:** the consoles (control, automation, comms, crawler, operations, index), then the admin
   pages (insights, sources, products, vendors…), then the shared parts (vault, cost tab, outside services, domains).
   Each release lists the tables it moved; the zip check's earlier-work rule keeps every column and action.

## For Jason to decide

- Quick Actions and Your day: a folded "Today" strip, or a right column on wide screens?
- The row's one visible action on Pulse: Open, Reply, or none (⋯ only)?
