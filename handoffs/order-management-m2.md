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
