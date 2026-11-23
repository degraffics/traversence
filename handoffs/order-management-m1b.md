# Hand-off: order management M1b, "what the system knows, what it asks" (2026-11-23)

From the Orders session (session_01CXLi5jrE1f1iCSbBQssAAe), at a hold: M1 is live (release #274, Sync 21:07 UTC
`64c28ed`, verified through the test Executive: board tiles Needs us 2 · In production 2 · Money owed $809.10, Order #12's
three lines). M1's record: `handoffs/order-management-m1.md`. This file is M1b's brief, complete enough for whoever builds
it (this session or a fresh one, the session manager's call).

## What's decided (read first)

- **Design:** `research/order-management-design-2026-11-23.md` §3.6; mockups `design/order-management/mockups.html`
  `?v=open` and `?v=open-after` (PNGs at 1280 and 390), Design reviewer approved (round 3, f351d44), Jason approved
  (pop-up, 2026-11-23).
- **Money policy:** `decisions/0178`'s notes of 2026-11-23: money never has Undo (↗ for a QuickBooks payment, Cancel the
  record when not processed, Refund… when processed); (a) a payment QuickBooks shows on a linked invoice is applied at once
  (the session manager's decision); (b) a payment recorded here is also received on the linked hand-made invoice (Jason:
  yes); (c) a vendor bill recorded here is also created in QuickBooks against the vendor (Jason: yes). Paying vendors is a
  separate decision.
- **Rules from today:** CLAUDE.md #1308 (see → do → confirmed → next), #1309 (single tasks vs a process), #1314/#1315
  (Edit for recorded facts, Undo only for rollbacks, money never), #1316 (inline actions as tv_icon() round icons with
  titles), decisions/0129's note (lines carrying tasks have no ×), the verb rule (a button acts now and shows its result;
  anything queued says "Request…" and when).

## What it builds (one release, on M1's state function)

1. **QuickBooks first** (§3.6.1). An order naming a QuickBooks invoice reads it when the order opens (cache a few minutes)
   and on the hourly pull: balance and payments.
   - Reading exists: `QuickBooksPay::invoiceByNumber($pdo, $no)` returns `['invoice', 'customer', 'payments' => [id, date,
     cents]]` (mock mode: invoice 112906 paid Oct 19, 112907 open). `QuickBooksPay::invoicePaid()` reads by `invoice_id`
     but **also overwrites the order's total and tax from QuickBooks**: don't use it for hand-made invoices.
   - **Linking a hand-made invoice:** today such orders keep `invoice_no` with no `invoice_id` ("made by hand, nothing is
     posted to it"); `QbPull::merge()` links by setting `invoice_id`, but it also takes the invoice's lines, prices and tax
     (and sets pay_mode 'invoice'). (b) needs the link without the merge: decide the smallest way (a link column, e.g.
     `qb_link_id`, add-only migration, or `invoice_id` with a guard so `invoicePaid()` and friends don't rewrite totals).
     Check every reader of `invoice_id` first (`grep -rn invoice_id sync-app sync-storage`).
   - **Applying** (a): each QuickBooks payment not yet in the ledger is recorded (`Orders::record` with request key
     `qbpay-<payment id>`, method 'QuickBooks'), through `ClientOrders::received()` so paid-in-full moves the order on
     (and completes a delivered one, M1). The Money line: "Paid in QuickBooks Oct 19 · check 2231" with ↗ (open it in
     QuickBooks) and ✎ (correct it, History kept). No Undo.
   - **Posting** (b): `ClientOrders::recordPayment()` already posts via `QuickBooksPay::recordOtherPayment()` when
     `invoice_id` is set; extend to the linked hand-made invoice. `guardReal()` / `testHere()` hold.
   - Stale blocks hidden: the client's checkout line when released or outside; the payment form when nothing is owed
     (`client-order.php` ~1295, `#payhold`).
2. **The Vendor line** (§3.6.2–3), a fourth state line, built like M1's (command-strip `lines`: act, edit, moves).
   - Facts: the work order (`order_work_orders`: vendor, cost_cents, cost_state "agreed", delivery, due_on, status) and
     vendor bills (new: store them; add-only table, its class in `app/lib/staging-tables.php`, zip check R14).
   - "$256.83 to the print vendor (work order …), not billed yet" + drop zone + Record the bill by hand (opens a form).
   - **The drop zone:** the one uploader (decisions/0127): a new target in `app/lib/upload-targets.php` (the `order_file`
     target at ~line 37 is the model: `check` with `$tvOrderStaff`, `done` keeps it as the order's file, kind 'invoice'
     exists in FILE_KINDS), `TvUpload.bind()` in the page, the Gallery panel. Words: "Kept with this order's files; not
     sent to anyone".
   - **Reading without AI:** pdf.js in the browser (already loaded by `js/tv-proof.js` from cdnjs 3.11.174, no eval);
     pull invoice number, date, PO, amount, tax, shipping, total, due, ship-via; match by PO = our work order number
     (Jason's example: Customer PO 20261027-CP0001 = the work order), else vendor and amount. Show ✓/≠ beside what we
     expected; ask only the mismatches ("The vendor says it shipped by USPS Priority Mail; the work order says pickup.
     Which is right?" with its two answers as an outlined pair); then **Record the vendor bill** as the line's action
     (Undo is wrong here: a recorded bill is corrected with ✎ or cancelled as a record; once in QuickBooks, it's voided
     there). A scan with no text says so and offers the four key fields typed. No AI (private; never Gemini's free tier).
   - **(c)** Recording the bill also creates a QuickBooks Bill against the vendor: needs a new `QuickBooksPay` call
     (POST `bill`, VendorRef from the vendor's QuickBooks id; find or create the vendor) through `QuickBooksPay::call()`,
     guarded by `guardReal()`. Its result (Bill id) kept with the bill; the line reads "Billed $256.83 (inv 130929, Nov 17)
     · due". Outside services: `StagingGate::hold()` first (zip check R13).
   - Vendor ⋯: Drop their invoice · Not billed by them · Dispute… (greyed until billed). ✎ edits the recorded bill.
3. **The board** counts from the lines: the Next step includes the vendor's question when it's ours to answer.
4. Inline record actions as `tv_icon()` icons with titles (mail, pen, link/external).

## Tests to write (design harness; see M1's notes)

- `tools/order-tests/m1-seed.php` seeds 15 orders in every state (harness MariaDB `tvh`; the harness Mailer "sends"
  while `<root>/harness-mail-ok` exists). Add: a hand-made invoice linked by number (mock 112906 paid, 112907 open), a
  work order with an agreed cost, and a text PDF vendor invoice (made up, never Competition Printing's real file).
- QuickBooks in mock mode (`QB_ENV=mock`): applying a paid invoice once (twice is a no-op), a part payment, posting a
  recorded payment to the linked invoice (mock), a bill created (mock).
- Rendered at 1280 and 390: the four lines, the drop zone with a real upload, the read fields and the mismatch question,
  Record the vendor bill, every ⋯ open and closed (×, Esc, outside).
- Run `m1.php`, `m1.cjs`, `collect-balance.cjs`, `complete-owed.cjs` again.

## Coordination

- The Control Board's kit-buttons release is next on `app/admin/kit-css.php`: M1 kept its styles in
  `widgets.php::tv_w_board_css()`; keep M1b's there or on the order page.
- Claim per function; rebuild on the newest sync just before zipping; zip check just before merging.
- Task #4 (Payment reminders) moved to the Control Board session.
