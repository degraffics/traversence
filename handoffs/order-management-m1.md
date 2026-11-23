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
