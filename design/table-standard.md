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

## The standard (one table for the admin's lists)

It applies to the admin's tables and the admin's Pulse. The profile page's Pulse (members and listings) keeps the row
icons Jason set in his own mockup (`design/pulse-profile-mockup.png`: read/unread, forward, pin, shield, file, +, ▾) unless
he says otherwise.

1. **One component:** `tv_table()` in `app/admin/kit-table.php`, filled by `js/admin-kit.js`'s List, extended in place (no second file); pages pass columns and rows, never
   their own `<table>` styles. `tv_w_table()` stays as a wrapper that calls `tv_table()` (the zip check keeps live functions).
2. **Columns:** a status mark first (icon, colour and word, never colour alone), the name, then data, then the row's
   actions last; numbers and dates right-aligned. **Pulse keeps 0172's columns and order (From, Severity, Subject,
   Received)**: "Pulse keeps the look Jason has set" (0172).
3. **No silent hiding:** a column with no data yet stays, saying so once ("No history yet", principle 4); where every row is
   empty, the column folds to a narrow one with a note under the table naming why ("Last 24 hours, Speed: no history
   yet"). Nothing that will fill is hidden.
4. **Headers:** sentence case, small, the meta colour; a click sorts (▲▼ only on the sorted column). Filters are one
   dropdown button above the table (CLAUDE.md), not controls in the header.
5. **Rows:** one height (44px; two lines only for a sub-line, kept apart from the title in the meta colour). Hairlines,
   a soft tint on hover, no stripes. Dates through `TvTime` ("Nov 19, 10:04 AM MST"), never cut. Long text ends in "…".
6. **Opening a row:** in Pulse, **in place, under itself (▾), one at a time** (0172). Other tables open the record's detail
   drawer (0109). Either way the whole text is there.
7. **Actions:** no toolbar appearing over the text. Pulse: **▾** (open in place) and **⋯**, a menu with icon and words for
   the same actions the hover bar has today (mark read, pass it on, pin in your own list, protect, add to, file); a greyed
   item says why. Other tables: at most one visible button (Check, Approve…) and ⋯.
8. **Group rows:** alarms and other system feeds group under one row that opens to its members, on the Board and in Pulse
   (0172, 0171 §8's note; the directive's "rollups for system feeds only"). Part of the component from T1, with its tests.
9. **Paging and counts, for large sets** (Jason, 2026-11-19: "pagination and count controls too. This is something that
   large data sets need for presentation control"). The component carries the admin's list standard of 2026-11-12
   (`js/admin-kit.js`, `.ak-pgb`): the count always shown ("1–50 of 1,234"), **Show 10 / 25 / 50 / 100** a page (kept in
   this browser per table), a page bar (first, previous, the pages around this one, next, last; current page in the
   standard button), **Show more** under the list, and **Columns** to choose which show. Paging, sorting, filtering and
   the count come from the server (a page at a time, `LIMIT`/`OFFSET` with a `COUNT(*)`), so a table of 100,000 rows
   loads as fast as one of 50. A filter or sort goes back to page 1 and the count follows it ("12 of 1,234 match").
   In Pulse the default order stays 0172's ("never reorders anything for you"); a sort the person clicks is theirs, and
   paging never changes the order. The List's existing `.ak-pgb` page bar is kept and extended, never copied.
   Group rows count as one row for paging; their members show when opened. On phones the bar keeps previous, "Page 3 of
   25" and next.
10. **Selecting rows:** a checkbox column only where there's a bulk action; the bulk bar appears when one is ticked.
11. **Empty and loading:** "Nothing here yet" with the way forward; a skeleton row while loading.
12. **Phones (390px):** each row becomes a card (status, name, sub-line, ▾ or its button, ⋯), edge to edge (§11).
13. **Levels:** the table sits on a section (`--tv-sec`); rows on the card colour; the header row on the section colour.

## Pulse's top half (one header, then the list)

- **One line:** Pulse's mark and title, the role picker (only if you hold more than one role), the **Filter this list** box
  (the directive's labeled local filter, distinct from the platform search), the bell, a **⋯** for Quick Actions and an
  icon for **Your day** that opens it as a pop-up: 0172's own option ("Quick Actions may become a ⋯ in the Pulse header
  and Your day an icon that opens it on screen as a pop-up"), which fits the directive's one header per view. The
  stream's second title goes.
- **One filter button** under it: "Inbox · All (17) ▾" holding the folders (Inbox, Pinned, Follow up, Archived) and the
  stream's views (Operational Queues, Alerts, Order Mgmt, System Health, Mine, Resolved), with + New beside it. These views
  are filters of the same stream (`tv_pulse_board()`'s `$chips`), not 0105's Operational Task Queue, which 0172 removed
  (the bell's list replaced it); none comes back. Alerts' count stays red on the button.
- Result: two rows before the first message instead of seven.

## Order (each its own release, each reviewed)

1. **T1, the component:** `tv_table()`, admin-kit.js's List extended, `tv_w_table()` as its wrapper, group rows, the ⋯ menu (shared with
   the public cards), a test page and `tools/design-tests/table.php`. **The Control Board's table stays with its own
   session:** once T1 is live I tell it, and it moves its process table onto the component in its own release.
2. **T2, Pulse (admin):** the stream on the component (▾ and ⋯, no hover bar; 0172's columns; alarm groups), and the top
   half as above.
   **As built (2026-11-19, the session manager's review):** Pulse keeps its own engine (rows open in place, live marks,
   0172's columns and order) and takes the standard's look, ⋯ with words, groups, paging and the count, and phone cards;
   only server-listed tables use `tv_table()`. Its paging pages the rows Pulse gathers (from many sources in one pass),
   not a database query. The hover bar goes from the admin's Pulse only; the profile Pulse keeps Jason's mockup's icons.
3. **T3–T5, the rest by module:** the consoles other than the Board (automation, comms, crawler, operations, index), the
   admin pages (insights, sources, products, vendors…), then the shared parts (vault, cost tab, outside services, domains).
   Each lists the tables it moved; the zip check keeps every column and action.
   A tool whose own query is capped (orders at 400) says "of 400" when more exist: as each moves, it pages in SQL
   (`LIMIT`/`OFFSET` with a `COUNT`) or says "400+". T1 sends one page at a time to the browser; three tools page in
   the database so far.

## The admin's test (principle 5)

| State | What Jason sees | What he presses |
|---|---|---|
| Empty | "Nothing here yet" and the way forward (e.g. "No alarms. The Board checks every 5 minutes.") | the way forward's button |
| Loading | a grey skeleton row where rows will be | nothing; rows replace it |
| A group row | "Overpass: 4 alarms ▸" as one row | the row: it opens to the four, each with its own ▾ and ⋯ |
| A greyed ⋯ item | "Protect" greyed, with "Only an owner can protect this" under it | nothing; the reason is shown |
| A phone card | each row as a card: severity, from, subject, the sub-line, ▾ and ⋯ | ▾ opens it under itself |
| A large table | "1–50 of 1,234", Show 50 ▾, the page bar | a page number, or Show 100 |
| No history yet | "No history yet" once in Last 24 hours | nothing; it fills as readings come |
