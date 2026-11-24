# Hand-off: order management M2, money on the order page (2026-11-24)

From the Orders session (session_01CXLi5kW2cGfR4Ya7fh2s3y) after M1b went live (Release #276, Sync 00:46 UTC `3d42052`).
This is M2's brief: complete enough for a fresh session. M1b's record: `handoffs/order-management-m1b.md`; M1's:
`handoffs/order-management-m1.md`.

## What's live (order management)

| Release | What |
|---|---|
| #274 order-m1 | One state function (`ClientOrders::boardState`), the board and its tiles, the order page's Order · Money · Delivery lines with ✎ and ⋯, Delivered, balance due (0178 §6) |
| #276 order-m1b | QuickBooks first (`ClientOrders::readQuickBooks`, the hourly `qb_orders_tick`, `orders.qb_link_id`), the Vendor line and vendor bills (`app/lib/VendorBills.php`, `order_vendor_bills`, the bill made in QuickBooks), "Vendor bills post to" in System Configuration → Settings (`qb_settings` + `qb_settings_log`), Delivery's one action, the pickup answered once, the client's ✉ |

## What's decided (read first)

- **The plan:** `research/order-management-design-2026-11-23.md` §6, row M2: "Money on the order page: `admin/order.php`
  folded in (payments, credit memo, refund with Jason's OK (0178 §1), dispute, cancel until the vendor accepts (0178 §2));
  Take a payment". §4.3 (the order page) and the mockups `design/order-management/mockups.html` `?v=money-pay`,
  `?v=order-after` (PNGs at 1280 and 390).
- **decisions/0178:**
  - §1: every refund waits for Jason. An admin prepares it on the order (amount, reason, the payment it comes from); it
    goes to him as a Pulse case with Approve and Decline; only on Approve is it sent through QuickBooks. Today any admin
    refunds any amount (`website/admin/order.php` `case 'refund'`): that narrows.
  - §2: Cancel is offered until the vendor accepts the work order (with their job number); after that it's greyed:
    "The vendor accepted the work order on <date>: it can't be cancelled; a credit or a refund instead".
  - §5: no keyed cards; staff send or copy the client's pay link.
  - Its note: money never has Undo. A payment read from QuickBooks shows ↗; one recorded here and not processed has
    **Cancel the record**; one processed (charged, or posted to QuickBooks) has **Refund…** with Jason's OK.
- **CLAUDE.md** (2026-11-23): see → do → confirmed → next; Edit for recorded facts, Undo only for rollbacks, never money;
  inline actions as round icons with titles; a finished step replaces its form (the Design reviewer's reading).

## What it builds (one release)

1. **The Money line's ⋯ does the money moves in place**, not on `/admin/order.php` (today: "Refund, credit memo or
   dispute… On the order's money page for now (it moves here in M2)", `website/admin/client-order.php` ~678).
   Fold `admin/order.php`'s handlers into the order page through their owning library (`app/lib/Orders.php`):
   `credit()` ~150, `refund()` ~212 / `refundThroughQuickBooks()` ~194 / `refundable()` ~175, `chargeback()` ~394 /
   `chargebackResolved()` ~404, `inboxApply()` ~358, `cancelUnpaid()` ~460, plus "Check QuickBooks" (`fromQuickBooks`).
   `admin/order.php` stays for GO Code orders (its `fix`, `pause`/`resume` are theirs); client orders open the order page.
2. **Refund with Jason's OK (0178 §1):** Refund… prepares it (amount ≤ `refundable()`, reason, which payment, work orders
   to stop); a Pulse case for Jason with Approve / Decline (`app/lib/Pulse.php`, as a `['do', …]` step, CLAUDE.md's Pulse
   rule); the Money line says "Refund of $X waiting for Jason's OK" with its case link; Approve sends it
   (`refundThroughQuickBooks`), Decline closes it with his reason. Nothing moves before Approve.
3. **Cancel until the vendor accepts (0178 §2):** the Order ⋯'s Cancel the order… today greys on "something is paid" and
   "the vendor has it"; make the rule the vendor's acceptance (`order_work_orders.status` accepted or beyond / `job_no`),
   with its date in the reason. A paid order cancelled before acceptance needs its refund (the §1 path).
4. **Payments' corrections, by the money rule:** each ledger row on the Money line's History: one read from QuickBooks
   (method QuickBooks, `request_key` `qbpay-…`) shows ↗; one recorded here and not posted (no QuickBooks payment id in
   its event) has **Cancel the record**; one processed has **Refund…**. Find where the posted payment id is kept
   (`recordPayment`'s event text today: "(payment N)"): a column may be needed (add-only).
5. **Take a payment** from the Money line: the existing payment step (`#payhold`) stays the form; the pay link to send or
   copy (0178 §5). M3 builds Take a payment across orders.
6. FAQ entries for what changed.

## Tests (design harness)

- Stand it up from `handoffs/design-harness/README.md` (MariaDB `tvh`, `php -S` on 8790 with `h/srv.php`, `fix.py`),
  then `tools/order-tests/m1b-seed.php` (it runs `m1-seed.php`); `qb_settings`, `wf_*` and a users row 1 (display name
  "Staff", role super_admin) were made by hand this time; the Mailer stand-in returns while `<root>/harness-mail-ok`
  exists (never shipped; build the zip from an explicit file list). pdf.js from npm's `pdfjs-dist@3.11.174` (PDFJS_DIR)
  where cdnjs isn't reachable.
- Rerun `m1.php`, `m1b.php`, `m1.cjs`, `m1b.cjs`, `collect-balance.cjs` (10 11 5), `complete-owed.cjs` (10 11).
- **Never run `rulings.php` on `tvh`** (it drops and remakes tables).

## Pitfalls learned in M1b

- The ledger's `request_key` is unique across all orders: key anything per order (`qbpay-<order>-<payment>`).
- `send.py` refuses a zip name it has recorded; a record only on your unmerged branch can be taken off the branch and
  sent again under the same name (never one in `main`).
- `deliveries/README.md` is generated: on a merge conflict take main's and regenerate (`deliveries.py`'s `write_readme`).
- A whole-file `(top)` change claim is needed when adding functions to a class (the zip check says which).
- The worker restarts stop the harness (MariaDB and `php -S`): start both again and sign in again.
- `.env` is only for secrets (CLAUDE.md); a choice like an account goes in an admin setting with History (Jason's
  "Review this again", 2026-11-24).

## First task for the M2 session (the session manager, 2026-11-24)

M1b's last live check: once the hourly `qb_orders_tick` has run in production, run `live-read.yml` (read: order,
order_id: 12, to_log on), confirm Order details say "Invoice 112906, linked by its number" and, if QuickBooks shows a
payment on it, the Money line says "Paid in QuickBooks <date> · <ref>"; delete the run's log once read; tell the session
manager. A test account reads only QuickBooks' sandbox (`QuickBooksPay::testHere`), so the link shows only after the job
(production) has linked it; the Vendor line's "Posts to …" likewise shows only to staff in production.

## Open, not M2

- The vendor's own default expense category before "Vendor bills post to": confirm the field against a real QuickBooks
  Vendor read in the sandbox first (the session manager, 2026-11-24).
- Client order emails onto the 0172 dispatcher (the session manager's separate item).

## Built (the M2 session, session_01CXLi5j2wAgTrKdTwX6kQNY, 2026-11-24): built on Sync 00:46 UTC (3d42052)

The zip `order-m2.zip` (not sent yet): `app/lib/Orders.php`, `app/lib/ClientOrders.php`, `app/lib/Pulse.php`,
`app/lib/Customers.php`, `app/lib/staging-tables.php`, `app/ui/faq.md`, `app/migrations/2026-11-24_order_refunds.sql`,
`website/admin/client-order.php`, `website/admin/order.php`. Claimed per function (order-m2.zip).
Rebuilt on **Sync 01:30 UTC (7bece80)**: `staging-tables.php` on its live copy (with #1328's `open_load_batches`), claimed
again; the zip check passes clean. Design reviewer's round 1 (01:28) applied: each move only in ⋯ (the forms are panes
opened from ⋯ or a row's ↩/✕, closed by ×, Esc or opening another), Withdraw inside its notice, "Refund waiting $X" in
amber in the figures.
The session manager's calls (01:22): (a) the reversing kind, noted on decisions/0098; (b) a refund of money that wasn't a
card: on Approve its Refund Receipt is made in QuickBooks and recorded, then Payments' case `refundsend:<id>` (and the
order's notice) carries **✓ Sent** with how it went (`payout`: to_send → sent); (c) credit memos: Jason answered "Need my OK" (02:16 UTC, decisions/0178's
note): `Orders::requestCredit` (kind `credit` in the same table), the Founder's case "Credit memo to approve", Approve
runs `Orders::credit`; Approve and Decline need the Founder's own staff login (an admin login that isn't a staff
login is refused, greyed with why).

Each behaviour and its source:
- **Refunds wait for Jason** (0178 §1): `Orders::requestRefund` (amount ≤ `refundable()` and ≤ what's left of the chosen
  payment, a reason, GO Codes and work orders to stop) writes `order_refund_requests` (waiting) and a History line; one
  waits per order; **Withdraw the refund** until decided. Pulse case `refundok:<id>` (the key `refund:` was taken by the
  Refunded/Part refunded update rows) shows only when `Access::who()` is the Founder, with **Approve: refund $X**
  (`['do', 'refund_approve']`) and **Decline** (`['form', 'refund_decline']`, why required); `Orders::approveRefund` /
  `declineRefund` check the Founder again. Approve runs `refundThroughQuickBooks`; if QuickBooks refuses, nothing moves,
  it stays waiting with QuickBooks' words, and Approve can be pressed again. GO Code orders' refunds on `admin/order.php`
  take the same path ("Already refunded in QuickBooks: record it here" stays, as before).
- **Cancel until the vendor accepts** (0178 §2): `ClientOrders::cancelBlock` (a work order accepted or beyond, or a job
  number: "The vendor accepted the work order on Nov 3 (job …): it can't be cancelled; a credit or a refund instead"; the
  date from the `work_order_accepted` event, else the work order's log). Unpaid: cancels, and work orders not accepted
  stop (`stopUnaccepted`, the vendor told). Paid: its refund of everything paid goes to Jason with `cancel_order`; on
  Approve it's refunded and cancelled. A payment only recorded here must have its record cancelled first.
- **The money rule** (0178's note): `Orders::payments` classes each payment: `qb` read from QuickBooks (↗ to the payment
  or its invoice), `card`, `posted` (recorded here and received on its QuickBooks invoice: `order_ledger.qb_payment_id`,
  new, or older rows' "(payment N)" event) → **Refund…**; `here` → **Cancel the record** (`Orders::cancelRecord`: a
  reversing `payment_cancelled` row keyed `cancel-<row>`, the ledger still only added to; paid, status and stage go back;
  greyed once a work order went out after it, it was delivered, its GO Codes went live, or a dispute is open).
- **Money on the order page** (design §4.3a): the **Money** section (`#money`): Total · Paid · Owed (Credits, Refunded,
  Disputed only when not $0.00), each ledger row with its round icon, Refund…, Cancel the record, Issue a credit memo,
  Record a card dispute / Dispute won or lost, QuickBooks refunds and credits to place (the inbox), Cancel the order….
  The Money line's ⋯ goes to each, plus **Copy the pay link** (0178 §5) and **Check QuickBooks**; ✎ goes to #money; the
  Money line says "Refund of $X waiting for Jason's OK". `admin/order.php?id=<client order>` forwards to `#money` (a POST
  there changes nothing and says where to do it); it stays for GO Code orders.
- Replaced screen, kept/moved: old page's Fix it, pause/resume → GO Code orders only (client orders have no GO Code fix
  there); Credit, Refund (with work orders and where each stands), inbox, chargeback, won/lost, Check QuickBooks → moved
  to #money; cancel unpaid → Order ⋯; the policy line → kept; ledger and history → the Money section's rows (History
  section unchanged); mock "Mark paid" (QB_ENV=mock only) → not moved (test only).
- FAQ: four entries (team: where to refund/credit/correct; why a refund waits for Jason; until when an order can be
  cancelled; client: can I cancel).

Tests (design harness): `tools/order-tests/m2.php` 58/58 (`m2-seed.php` for the rendered one), `m2.cjs` all passing at 1280 and
390 (each new button pressed; screens in `handoffs/order-m2/`), `m1.php` passes; `m1b.php`'s 4 vendor-bill failures are the
same on the live code (the harness's `order_vendor_bills` is short of columns), not M2's.

Open for the session manager (sent 01:05, reminded 01:22): (a) the reversing kind needs live's `order_ledger.kind` to be a
VARCHAR (the harness's is); (b) a refund of a payment that wasn't a card: today Approve stays waiting with "no card to
refund from here"; (c) credit memos stay an admin action without Jason's OK. An admin who isn't a staff login reads as
the Founder (`Access::who`, the site's rule; EnvSettings uses it too).

**Settled (02:15 UTC, the session manager's (A)):** live `order_ledger.kind` is an ENUM without a new kind (read through
PR #1331's meta read, run 45309955001, log deleted), so Cancel the record writes a new add-only table,
`order_payment_cancels` (no ledger kind, no `MODIFY`); `Orders::money()`, `invoiceLaterOpen`, Customers' net and
`ecommerce.php`'s money reports take it off; noted on decisions/0098. The Design reviewer approved M2 (02:15, every
round), with the tidy made: a refund's facts on its Refunded row ("why · approved by, when · ✓ Sent <time> · ref ✎").
Order #12 (run 45309920979, log deleted): "Invoice 112906, linked by its number", "Paid in QuickBooks Oct 26 · $809.10 on
invoice 112906" confirmed; History's "Needs books" (QuickBooks $1,111.14 paid against the order's $809.10 lines) is with
Jason through the session manager; no money on #12 changed.
Zip: 10 site files (adds `website/admin/ecommerce.php`), built on Sync 01:30 (7bece80); the zip check passes clean.
Tests: m2.php 59/59, m2.cjs all passing at 1280 and 390.

## Next, in order (the session manager, 2026-11-24 02:22–02:27)

1. **M2 (#1333)**: clear to ask Jason once the Design reviewer has looked at the 390 fixes (sent 02:28). Merge only after the
   Control Board's task-trip release is live and synced; then rebuild `Pulse.php` on that live copy, take the inbox record
   off the branch and send again (`send.py`), rerun the zip check.
2. **M2b "How it goes"** (Jason: "I need to be able to modify. If I schedule a courier or ship vs will call. With
   competition printing I ask for will call, then schedule courier for local delivery or assign freight, or drop ship";
   then "check adrs for previous established methods"). Never new names (CLAUDE.md #1334): an Edit (✎, never Undo) of the
   existing fields at the right level, the established names, the existing work-order email to tell the vendor, History.
   The levels to map first, so Delivery reads one and they never disagree:
   - the work order's `delivery` (`WorkOrders::DELIVERY`, decisions/0101 work orders step 1): Will Call, Internal Transfer,
     Blind Drop Ship, Local Delivery, Next-day Courier, Ship USPS / UPS / FedEx, Freight; "Courier booked: add tracking";
   - the order's `ship_mode` (`Shipments::MODES`, 0101 addendum 2026-10-21): No shipping (with `no_ship_reason`), One
     address, Several destinations (`order_addresses`, a method per destination);
   - the order's `delivery` (`ClientOrders::delivery()`: pickup / ship) and an item's ship style (0101 addendum
     2026-10-16: Drop Ship, Courier, Pick Up; carrier and tracking);
   - also read 0040 (delivery_method, freight_ltl), 0099 (fulfilment statuses), 0112 (ship_rules, local pickup).
   "Will call, then a courier for local delivery" is two steps: show how the existing values cover it before adding any.
   No outside booking (courier API, freight quote): named as later. The note goes on decisions/0101.
3. The Design reviewer's 7–10 (Files & versions and Shipping at 390: stacked add-a-document form through TvUpload, ⓘ for
   the explanations, phone cards for the per-item table, the manifest's print icon).

## M2 live (2026-11-24 02:52 UTC)

Release #279 (order-m2), Sync 02:52 UTC (2eab529), recorded live in #1340. PR #1333 merged (f9f00d39).

## M2b "How it goes": the mapping (first draft, the M2 session, 2026-11-24)

Jason: "I need to be able to modify. If I schedule a courier or ship vs will call. With competition printing I ask for
will call, then schedule courier for local delivery or assign freight, or drop ship." Then: "check adrs for previous
established methods" (CLAUDE.md #1334). Every value below already exists; nothing is named new.

An order goes in **two legs**, and each already has its own field:
1. **Vendor → us, or vendor → the client** (the work order): `order_work_orders.delivery`, `WorkOrders::DELIVERY`
   (decisions/0101 work orders step 1): Will Call (we pick it up), Internal Transfer, Blind Drop Ship (the vendor ships
   straight to the client, unbranded), Local Delivery, Next-day Courier, Ship USPS / UPS / FedEx, Freight; and
   "Courier booked: add tracking" (courier, number; 0101, 2026-10-28), shown on the vendor's page and emailed to them.
2. **Us → the client** (the order and its items):
   - the order: `orders.ship_mode` (`Shipments::MODES`, 0101 addendum 2026-10-21): No shipping, with
     `no_ship_reason` (Electronic delivery, Customer will call, None), One address, Several destinations;
     `order_addresses` carries a method per destination; and `orders.delivery` (`ClientOrders::delivery()`: pickup,
     ship), which the Delivery line reads today;
   - an item: its ship style (0101 addendum 2026-10-16): Drop Ship, Courier, Pick Up, with carrier (USPS, UPS, FedEx,
     Other) and tracking.

Jason's cases, in existing values:
- "Will call, then a courier for local delivery": work order **Will Call** (leg 1), then the item or destination
  **Courier**, with the courier and number (leg 2).
- "Assign freight": work order **Freight** (leg 1), or the destination's method for leg 2.
- "Drop ship": work order **Blind Drop Ship** (leg 1 is the whole trip; there's no leg 2).
- "Ship vs will call": leg 2's mode or style (ship versus Customer will call / Pick Up).

Still to read before the design: 0040 (delivery_method, freight_ltl), 0099 (fulfilment statuses) and 0112 (ship_rules,
local pickup). The question for the session manager is which field the Delivery line reads so the levels never disagree.
Proposed: the line reads leg 2 (`orders.delivery` / `ship_mode`, the items' styles); it shows leg 1 as its first step
while the vendor has it. "Change how it goes…" is ✎ on each leg's existing field, never a new list. A leg-1 change goes
to the vendor through the existing work-order email ("Tell the vendor").

**Read (02:58):** 0040's `delivery_method` / `freight_ltl` is the Marketplace's quote model (a business's listings), not
our client orders: not used. 0099's fulfilment statuses (intake … packed → shipped | ready_for_pickup → delivered) are
already M1's Delivery model. 0112's `ship_rules` (local pickup) are the Shop's checkout, not client orders: not used.

**What the Delivery line reads today** (`ClientOrders::boardState`, live): `orders.delivery` = pickup, or the order's stage
`ready`, or any item `ready` → **"Ready for pickup at our shop."** The items' status is moved by the vendor's work order
(`WorkOrders::ITEM_FOR`: the vendor's `ready` → the item's `ready`). So on Order #12 (work order 20261027-CP0001, **Will
Call**, the vendor marked it Ready for pickup on Nov 2), the line says "Ready for pickup at our shop. Ready 21 days.
Picked up?". But what's ready is leg 1 at Competition Printing, for us to collect; leg 2 (the client's pickup at our shop,
or a courier from there) hasn't started. That's the gap Jason felt. M2b's fix in existing values: on a Will Call (or
Internal Transfer) work order, the vendor's ready is "Ready at <vendor> for us to collect" (leg 1, its next action
"Collected", the work order's existing Receive); leg 2 starts at Receive, from the order's own mode or the item's style
(Pick Up → "Ready for pickup at our shop"; Courier → "Book the courier" with the existing courier and number; Ship →
tracking). Blind Drop Ship has no leg 2: the vendor's shipped is the client's shipped.

## M2b built (the M2 session, 2026-11-24): built on Sync 02:52 UTC (2eab529), not live

Decided: decisions/0101's note (2026-11-24, Jason's Approve and Yes); mockup `handoffs/order-m2b/mockup.html`. The zip is
`handoffs/order-m2b/order-m2b.zip` (zip check clean): `app/lib/ClientOrders.php` (`legs()` new; `boardState` reads it;
`deliverables` made public), `app/lib/WorkOrders.php` (`goesTo`, `DELIVERY_MEANS`, `legWords`, `setDelivery` new;
`setTracking` says `_told` only when the email went), `app/admin/tools.php` (`backoffice/work_orders`: Courier booked on a
Will Call too; "emailed" only when it went), `website/admin/client-order.php` (the Delivery line's leg words, Receive /
Add the courier's number (the Design reviewer's naming), ⋯ Courier booked: add tracking and Change how it goes…, their panes, handlers `wo_courier` and
`how_goes`; the Order line's button left to Delivery while ours to receive or send; the Shipping box no longer says
"Pickup at our shop" for a vendor's Will Call), `app/ui/faq.md` (three entries). No migration.
- **The leg rule:** leg 1 is the first work order out and not received: Will Call with a courier's number → Out by courier
  from the vendor; to the client (`goesTo`) → At the vendor / Shipping from the vendor to the client; to us → At the vendor,
  Ready at <vendor> for us to collect (since, days; Receive), On its way to us (Receive). Leg 2 once received (not for a
  straight-to-client one): the item's ship style: Pick Up → Ready for pickup at our shop; Courier / Drop Ship → Received,
  waiting for its number (Add the courier's number, at the item's Tracking); shipped → Out by courier from our shop / Shipped. No work order: as before.
- **My reading, flagged:** the note names Ship, Freight and Local Delivery; Next-day Courier is read the same way (its
  ship-to, or us with none). `WorkOrders::moveItems` still treats Local Delivery's complete as "came to us" (unchanged).
- **The Design reviewer's two changes (03:27):** a vendor email that didn't go is its own error notice at the top of the
  order (no ×; Send again, Copy the message, Email it from your mail; `WorkOrders::tellVendor` keeps what we'd have sent,
  the work order's History says it didn't go; the drawer's tracking points to it); the Shipping box says How it goes in words
  with ✎ (the pane), and the order's own ship mode is labelled Ship mode.
- **Tests:** `tools/order-tests/m2b.php` (31) and `m2b.cjs` (86, at 1280 and 390) with `m2b-seed.php`; `m1b-seed.php`'s
  pickup orders now received at our shop with the item Pick Up (a Will Call ready at the vendor now reads as ours to collect).
  M2's suites pass. m1.php/m1.cjs, collect-balance.cjs, complete-owed.cjs and m1b's vendor-bill steps fail the same checks
  on the live files as on M2b's (harness drift, checked side by side on a second server), so not M2b's.
