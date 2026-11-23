# Hand-off: order management M1 (state model and the board), 2026-11-23

From the Orders session (session_01CXLi5kEUy5gPJ4PG34SZhy), at a hold, for a fresh session to build M1. The session
grew too large (Jason, 2026-11-20: hand off "so we can reduce token use without logic loss"). The session manager
reviews this before the fresh session starts.

## What's live (built on Sync 2026-11-23 19:36 UTC, `8a94377`)

| Release | What | Where it's recorded |
|---|---|---|
| #270 orders-release-a | Ordering made ironclad: deposit cost + 15%, the tax choice, Ship to, QuickBooks pull exemption, cancel voids the invoice, the Orders list's Balance due and Next step, split by address | `research/ordering-ironclad-2026-11-23.md`, decisions/0177 |
| #272 collect-balance | Collect the balance on an order out the door; Complete the order waits for the money (`ClientOrders::completeHold`, `completeOwed`: an admin's override with a reason, event `completed_owed`); the drawer's Complete move refused (`app/admin/workflows.php` the `client_order` adapter's `stage` op); the payment hold said once; section links land below the strip (`app/ui/command-strip.php`) | PR #1307, `handoffs/orders-collect-balance/`, tests `tools/order-tests/collect-balance.cjs` (10), `complete-owed.cjs` (12) |

Also merged today: zipcheck now sees releases in flight on main, recent branches and claims (PR #1306,
`tools/page-audit/test_zipcheck_inflight.py`), after #270 overwrote #269.

## What's decided (read these first)

- **The plan:** `research/order-management-design-2026-11-23.md` (approved by Jason as the plan, 2026-11-23, pop-up;
  Design reviewer round 3 approved). §3 the three states, §3.4 every Next step is a task, §3.5 the order as one chain
  routed by its choices, §4 the pages, §6 releases M1–M7.
- **The mockups:** `design/order-management/mockups.html` (`?v=board|board-after|board-empty|order|order-after|…`), PNGs
  at 1280 and 390. Build to them.
- **Money policy:** `decisions/0178-order-money-policy.md` (Jason's six answers).
- **Rules from Jason today:** CLAUDE.md #1308 (see the task, do it, confirmed in place with Undo, next step) and #1309
  (single tasks end at their confirmation; an order is chained tasks routed by choices).

## M1: what it builds (one release)

From the design §6 and 0178 §6:

1. **One state function** in `app/lib/ClientOrders.php` (e.g. `boardState($pdo, $o, $lines = null)`) returning the
   three states and the one Next step by §3.4's rule: `order` (key, sentence), `money` (key, words, amount),
   `delivery` (key, words), `next` (state words, label, href or act). Everything below reads it; nothing else decides.
   Today `tv_order_next_step()` (`app/admin/tools.php` ~39) mixes them; replace it with a call to the function.
   - Order states §3.1 include **Link expired** (`ClientOrders::expired()`, already used by the archived
     `admin/ecommerce.php` line 176) and **Changes asked** (proofs with status `changes`).
   - Money states §3.2 read `Orders::money()` and `status`; **Released before payment** = `ClientOrders::overridden()`.
2. **The board** (`app/admin/tools.php` `'backoffice/orders'`):
   - Columns: Order, Client, Where it stands, Money, Delivery, Next step (outlined `action` cells, already rendered by
     `website/js/admin-kit.js`'s action cell from release A).
   - Tiles: replace `'metrics'` (tools.php ~98, today counts the workflow stage, so "Waiting for payment" reads 0 while
     #12 owes $809.10) with Needs us, Waiting on the client, In production, Out the door, and a separate **Money owed**
     tile (total + count, any stage). Each sets the list's `stage` filter (`widgets.php tv_w_metrics`,
     `admin-kit.js` `[data-ak-stage]`).
   - **Filtering gotcha:** `website/admin/api.php` ~450 filters rows by `row['stage']` after the tool's list returns.
     Either give each row `stage` = its board state key (and Money owed its own handling), or add a tool flag (e.g.
     `'stage_self' => true`) so the tool filters itself; api.php is shared admin code: claim per function, and the zip
     check will show if the board session has it in flight.
   - The `pipeline` widget on the section (`app/admin/manifests.php` `backoffice.orders`) draws the workflow's Stage menu
     in the filter; the Design reviewer wants the dropdown to hold only Everyone's/Mine and Open/All. Decide whether the
     pipeline stays (it's the workflow view) and how its menu is hidden for this list.
   - Count and paging under the table, the empty state per tile with its way forward (mockup `board-empty`).
   - After a row's task: the result in place with Undo, the row's new state and its next link ("✓ Paid … · Undo", then
     **Send the work order**); a row that moved says so until refresh; tiles recount (mockup `board-after`).
3. **The order page's three state lines** (`website/admin/client-order.php` ~559, the strip's `news`): today up to
   five strings stacked into one notice. Make it three lines, Order · Money · Delivery, each with its one button (the
   Money line's action the page's one filled button; Complete greyed with "$X still owed" while owed, filled once paid:
   mockups `order`, `order-after`). `app/ui/command-strip.php` renders `news` as strings (line ~47) plus one
   `news_act`; it needs per-line buttons, so the strip gains a structured form (keep the old string form working:
   other record pages use it).
   - Live read of Order #12 (2026-11-23) also showed: "Complete the order" twice, "Waiting on the client" beside
     "Still open: $809.10", Bill to's street and town empty, Shipping "One address" with empty fields on a pickup
     order, the due date empty, the email loose at the top, "Paid outside QuickBooks… we record it" (the **pay mode's**
     name) read as if paid. Show only what applies (pickup: our shop, no fields; outside: no card words).
4. **0178 §6: "Yes, I have it" with money owed** (`ClientOrders::confirmReceived` ~916): today it completes the order.
   With `completeHold() !== ''`: record `received_at` and the feedback and thank the client, but don't complete; the
   order's state becomes **Delivered, balance due**, its next task for an admin with three ways: **Record the payment**
   (received → mark paid, then complete), **Collect the balance** (not received), **Override with a reason**
   (`completeOwed`). A Pulse step for it (`app/lib/Pulse.php` `orderSteps` ~614). Once paid or overridden, it completes
   as today. The client's page says "Thank you" and nothing about money it doesn't owe us words on.
5. **The drawer's Complete greyed, not only refused**: `Workflow::moves()` greys moves by their `checks`, which live in
   the **stored** definition (`wf_versions`, seeded once from `app/admin/workflows.php`), so editing the seed changes
   nothing live. Options: a code-level guard the adapter exposes that `moves()` consults, or a migration that adds the
   check to the published version. `app/admin/Workflow.php` is shared: claim per function.
6. FAQ entries for what changed (`app/ui/faq.md`, check `grep -rn "faq.php#"` before rewording).

Not in M1: Money section, Place an order, catalog (M2–M6).

## Tests to write (end to end, 1280 and 390, on the design harness)

Every state on the board with its Next step; each tile's count and filter; Money owed overlapping other tiles; the
empty state; a row's task done in place with Undo and its next link; the order page's three lines on a draft, sent,
expired, changes asked, approved owed, deposit paid, in production, out the door owed, released, paid, delivered with a
balance (0178 §6's three ways), complete; the drawer's Complete greyed with the reason. Run `collect-balance.cjs` and
`complete-owed.cjs` again (they must still pass), and each against the live files to show what fails today.

## The harness (design harness, MariaDB)

- `$O=/tmp/claude-0/-home-user-traversence/<session>/scratchpad` is this session's; a fresh session builds its own (see
  `handoffs/design-and-trips-2026-11-22c.md` harness notes). MariaDB on 3307 (database `tvh`), `php -S` on 8790 with
  `harness/srv.php`; staff cookie `traversence_session=harness0000000000000000000000`.
- Seeded today: `wf_versions` needed `def MEDIUMTEXT` and the `note`/`created_at`/`created_by` columns before
  `Workflow::seed` could store `client_order`. Order 681 (shipped, owed, released), 680 (ready, paid, with a ledger
  row), 672 (approved, held).
- **Never run `tools/order-tests/rulings.php` on the harness database**: it drops and remakes orders and users. Run it
  in its own database.

## Pitfalls learned today

- Two zips carrying the same file overwrite each other whole, whatever functions each claimed; run the zip check just
  before merging (it now sees other branches' records and claims). The board session's gauge-panel release is in flight
  on `control-console.php`, `module-cards.php`, `tools.php` and `faq.md` (session manager, 19:43 UTC).
- Live reads (`live-read.yml`, test accounts) print to the run's log: read, then delete the log
  (`delete_workflow_run_logs`); nothing read goes into the repo, mockups or PRs.
- A `[data-tv-status]` line is redrawn by `tv-ask.js` as a `.tv-note` box; tests should look for `.tv-note`.
- The strip's `go` target is clicked (`tv-strip.js` ~170): point it at a field or button, never a heading (a heading
  click folds its section).

## Open, not M1

- Task: check Payment reminders catches up after the board's release 2 (`pay_wait_tick`).
- Release B (0177 §1, one invoice per destination) is M5.

## Built: M1 (the Orders session after the hand-off, session_01CXLi5jrE1f1iCSbBQssAAe), 2026-11-23

Release `order-m1.zip`, built on Sync 2026-11-23 19:36 UTC (`8a94377`), no release live since. Not sent yet: Design
reviewer, then the session manager, then Jason. Files (each differs from the live copy; claims under `order-m1.zip`):
`app/lib/ClientOrders.php` (boardState, releasedAt added; confirmReceived, received, gotItTick, a comment in
setItemStatus), `app/admin/tools.php` (tv_order_board added; tv_order_next_step now hands back boardState's Next step;
`backoffice/orders`), `app/admin/widgets.php` (a list's search words, first tile, empty states, range; a metric's sub-line
and money tile), `app/admin/manifests.php` (`orders`: the board is a table, not the stage pipeline),
`app/admin/kit-css.php`, `app/admin/Workflow.php` (moves: the adapter's guard greys a move), `app/admin/workflows.php`
(client_order's guard: Complete while owed), `app/lib/Pulse.php` (the `delbal` case), `app/ui/command-strip.php`
(`lines`: labeled lines each with a button; `news` still works), `app/ui/faq.md`, `app/scripts/got_it_tick.php` (a
comment), `website/admin/client-order.php`, `website/js/admin-kit.js`, `website/order.php`. No migration.
`website/admin/api.php` is NOT in it: the Control Board session holds it (gauge panel); the board re-reads its row instead.

Each behaviour and its source:
- One state function, three states and one Next step (§3, §3.4) → `ClientOrders::boardState()`; the board, the order
  page's lines and the drawer's fields read it. Order states §3.1 incl. Link expired and Changes asked (newest proof
  `changes`); Money §3.2 (Released before payment = an admin's `released` event; a deposit order's balance isn't owed
  until it's out the door, mockup board-after's totals); Delivery §3.3.
- "Past due" only with a due date or Net terms (a build reading of §3.4 rule 2: Due on receipt has no later date to be
  past). **Asked in the summary.**
- Tiles count by state and filter (§4.1, mockup board); Money owed apart; opens on Needs us (mockup); one filter button
  (Design reviewer, round 3); count under the table; each tile's empty state (mockup board-empty).
- Send it again / Send a reminder in the row: ✓ Sent with a Copy icon (CLAUDE.md #1316 icons), Moved to…, tiles recount
  (mockup board-after; CLAUDE.md #1308). The email that didn't go says so in red with the Copy icon (release A's rule).
- The order page's three lines (§4.3, mockups order, order-after); Complete greyed with "$X still owed" (#1307); the
  ▾ menu's Complete/Collect moved to the lines (said once); the email in Bill to; pickup: no address fields; Payment
  terms in words that never read as paid (the live read of Order #12).
- 0178 §6 as written: confirmReceived with money owed keeps the delivery and words, thanks the client (their page says
  "Thank you!", nothing about money), event `delivered_owed`, staff emailed, Pulse `delbal` with the three ways; paid in
  full then completes it (`received()`); an admin's override is #1307's `completeOwed`. The got-it reminder skips it.
- The drawer's Complete greyed (M1 part 5) by a code guard the adapter exposes, not the stored definition.

Kept, moved, dropped (playbook §3): Stage column → Where it stands; Total → the drawer and the order; Balance due →
Money; Updated → dropped (the mockup has no column; sorting by the other columns stays); Show and Whose filters → the one
button's four choices; tiles Drafts / With clients / Waiting for payment / To make → Needs us / Waiting on the client /
In production / Out the door + Money owed; the Stage pipeline menu → the tiles (the workflow's stages stay in System
Configuration → Workflows and the drawer); the strip's ▾ Complete the order / Collect the balance → the Order and Money
lines; the notice's stacked strings → three lines (sandbox and an open ticket still said under them); the #finish
"Waiting on the client" notice → the Order line. **Updated is the one drop: in the summary for Jason.**

Tests (design harness, MariaDB, `tools/order-tests/m1-seed.php` seeds 15 orders in every state; the harness's Mailer
"sends" while `<root>/harness-mail-ok` exists):
- `m1.php <root>`: 38 checks (each state, tiles, Money owed, Mine/All, the guard, 0178 §6 end to end, Pulse, got-it).
- `m1.cjs <base> <seed>`: 31 checks × 1280 and 390 (60 + the copy check), every new button pressed; screens in
  `handoffs/order-m1/`. Against the live files it fails at once (no tiles, no lines).
- `collect-balance.cjs` and `complete-owed.cjs`: updated to where those buttons live now (the Where it stands column;
  the Money line instead of ▾); all pass.

Open: task #4 (Payment reminders catching up after the board's release 2) needs a read of System Configuration →
Scheduled tasks, which `live-read.yml` doesn't offer (orders, order only): asked of the session manager.

Live read (`live-read.yml`, orders, 2026-11-23 20:24 UTC; its log deleted once read): 4 open orders. By the model they read
as: Order #12 Out the door, $809.10 owed, released before payment, Collect the balance (Needs us); two orders with the
printer, paid (In production, Follow the work order); one sent Oct 29, its 14-day link run out (Link expired, Send it
again: Needs us, the design's §1.2 case). Money owed: $809.10, 1 order. The live tiles today say 0 Waiting for payment.

### The Design reviewer's changes (2026-11-23 20:24 UTC), made
1. A row's result (✓ Sent, the Copy icon, its notice, "Moved to…") stacks at full width in a phone card; no "Next step"
   label there (`kit-css.php`).
2. Known values as text with a ✎ Edit icon (CLAUDE.md #1315, #1316): the due date "Nov 15, 2026 (from the terms)";
   Shipping "Pickup at our shop" or "Delivered Nov 22", its select and address fields behind ✎.
3. Aged items ask: the Delivery line says "Ready 21 days. Picked up?" (or "Shipped N days. Delivered?") with **Yes, picked
   up** (asked first; `ClientOrders::markReceived`: paid → complete, owed → Delivered, balance due with **Undo**,
   `unmarkReceived`) and **Remind the client** (`remindReceived`, the got-it email now; an email can't be undone, so the
   line says "Reminded Nov 23").
4. "Collect the balance" beside Record the payment is now **Ask the client to pay** (`askToPay`: an email with what's left
   and their link; "Asked Nov 23"); Override with a reason stays outlined (`data-danger`). The Pulse step is renamed.
5. The seed's line is a custom item (a catalog product with no run size read Qty 0); the page was right.
6. The Business line's "Change ▾" is a ✎ titled "Edit the business".
7. Filled buttons' umber: left to the Control Board session's kit release (the session manager's ruling).
Tests: `m1.php` 42 checks, `m1.cjs` 37 × 2 widths, `collect-balance.cjs` and `complete-owed.cjs` all pass.

### Waiting on the session manager
Its "intelligent system" messages (QuickBooks first, the vendor's open questions, the vendor invoice dropped and read
without AI, one "What's open" list) reached this session after M1 was built. Asked: ship M1 after these fixes and build
"What's open" as M1b (the design amendment, a live read of #12, the mockup first), or hold M1 for it.

### Round 2 (Design reviewer 20:33, session manager 20:33, Jason via the Design reviewer 20:37), made
- The three lines have no × (they carry the order's tasks): decisions/0129 note and CLAUDE.md's command-strip line.
- Each line: its one suggested action, ✎ (Order: edit the items, or reopen; Money: the payments on the money page, in a
  panel; Delivery: the delivery's date, `setReceivedOn`, the old date to History) and ⋯ Other moves (every move the order
  already has, each with what it does; greyed with why). Nothing new invented: Put on hold, Shipped instead and Returned or
  lost (the Design reviewer's examples) don't exist as moves yet and aren't listed.
- "Yes, picked up" ends in ✎ Edit the delivery, not Undo (a recorded fact); "Not delivered after all" is in Delivery ⋯.
- The due date's ✎ on its line; Terms as text with ✎; the pop-up's × kept inside it at 390.
- Jason asked (in this chat): "for money, would a undo be correct or would it be cancel unless processed then it would
  be refunded." Answered: read from QuickBooks → no Undo, ↗ open it there; recorded here and not processed → Cancel the
  record; processed → Refund (his OK, 0178 §1). Sent to the session manager for a note in decisions/0178. M1 has no
  money Undo; the M1b mockup's Money line now has the QuickBooks icon.
- Tests: `m1.cjs` 50 checks × 2 widths (every ⋯ opened and closed by ×, Esc, a click outside; ✎ Edit the delivery saved
  with History), `m1.php` 42, `collect-balance.cjs`, `complete-owed.cjs` (selectors scoped past the toolkit): all pass.
- Round 3 (Design reviewer 20:49): the Money line's one suggested action (Record the payment); Ask the client to pay
  and Override with a reason only in its ⋯. Delivered names who said so: "recorded by <first name>", or "the client told
  us on <date>". Tests pass (m1.cjs, m1.php, collect-balance.cjs, complete-owed.cjs).
- Held (the session manager, 20:43): the gauge panel goes first. My claims on tools.php, command-strip.php and
  kit-css.php are released and the pending record is off this branch; once it's live and synced, M1's shared files are
  rebuilt on the live copies, claimed again, zip-checked, then the session manager and Jason's pop-up.
