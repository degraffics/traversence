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

## Built: M1b (the Orders session, session_01CXLi5kW2cGfR4Ya7fh2s3y), 2026-11-23

Release `order-m1b.zip`, first built on Sync 2026-11-23 21:07 UTC (`64c28ed`), rebuilt on 22:02 (below). Zip check (first build): clean but
for one hold, `app/ui/faq.md`, carried by the Control Board's kit-buttons zip (not live yet): per CLAUDE.md, wait for it
to go live, re-apply only M1b's two FAQ entries (and the one sentence added to "What does Next step mean") on the live
copy, and send. Claims under `order-m1b.zip`.

Files: `app/lib/ClientOrders.php` (readQuickBooks, applyQuickBooks, qbApplied, qbOrdersTick added; boardState: Paid in
QuickBooks, the vendor state and its Next step; recordPayment posts to a linked invoice), `app/lib/QuickBooksPay.php`
(invoiceByNumber's payments carry ref, method and ours; webUrl and createBill added), `app/lib/VendorBills.php` (new),
`app/lib/WorkOrders.php` (setInvoiceNo drops an old link), `app/lib/upload-targets.php` (target `order_vendor_bill`),
`app/lib/staging-tables.php` (`order_vendor_bills`: money), `app/lib/Scheduler.php` + `app/scripts/qb_orders_tick.php`
(the hourly pull), `app/admin/job-line.php` (its page), `app/migrations/2026-11-23_m1b_vendor_bills.sql` (add-only:
`orders.qb_link_id`, `qb_link_customer`, `qb_read_at`; table `order_vendor_bills`), `website/admin/client-order.php`,
`website/js/vendor-bill.js` (new), `app/ui/faq.md`.

Each behaviour and its source:
- A hand-made invoice is linked by its own column (`qb_link_id`), never `invoice_id`: `invoicePaid()`, the webhook, cancel's
  void and `WorkOrders` all read `invoice_id` and would rewrite totals or void it (brief: "decide the smallest way").
- Read when the order opens (5-minute cache) and hourly (§3.6.1; brief 1). **The hourly pull is a new job** ("QuickBooks
  invoices on orders"): none existed. One invoice links one order only.
- A payment QuickBooks shows is applied at once, as method QuickBooks with its date and reference (0178 note, decision
  (a)); no receipt to the client and nothing written to QuickBooks ("it writes nothing outside our records"); staff are
  told and the chain moves on as for any payment (paid in full completes a delivered order, 0178 §6). Payments the site
  posted carry our mark and are skipped. Ledger key `qbpay-<order>-<payment>`.
- Money line: "Paid in QuickBooks Oct 19 · check 2231 · $… on invoice …" with ↗ (CLAUDE.md #1316) and M1's ✎ (the
  payments, History kept), no Undo (0178's note).
- A payment recorded here is received on the linked invoice (0178 note (b), "Yes, post it"); guardReal holds.
- The Vendor line (§3.6.2–4, mockups open, open-after): shown only when a sent work order has an agreed cost; drop zone
  through the one uploader (0127), "Kept with this order's files; not sent to anyone", no team thread; read in the
  browser with pdf.js 3.11.174, no AI (§3.6.3, 0164); matched by PO = our work order number (or its PO), else vendor and
  amount; with two work orders and no PO, "Which work order is it for?"; ✓ beside matches, each mismatch asked once with
  its two answers (PO not ours, amount, tax and shipping added, ship via); Record waits for the answers (greyed, with why);
  "It shipped" asks for tracking (kept on the bill and on the work order when it has none); a scan asks the four fields.
- Record the vendor bill → also a QuickBooks Bill against the vendor (0178 note (c)), the vendor found or added, one line
  to the Cost of Goods Sold account (`QB_COGS_ACCOUNT_ID`, else the first COGS account: **a build reading, in the summary**),
  guardReal and StagingGate::hold first (R13). QuickBooks not answering: kept with why, **Send it to QuickBooks** retries
  (principle 1: the system fixes first).
- Money never has Undo (0178's note): **Cancel the record** only before QuickBooks; after, "void it there (↗)". ✎ edits a
  bill's number, date, total and due (History) only before QuickBooks; after, greyed "change it there".
- ⋯ (brief): Drop their invoice · Not billed by them (with why; **They billed after all** puts it back) · Dispute… (greyed
  until billed; records why on the bill; **Dispute settled**) · Cancel the record · Work orders. Dispute… doesn't email the
  vendor (nothing decided says it should).
- The board (brief 3): Next step **Record the vendor bill** (Needs us) once a work order is shipped, ready, complete or
  received and its bill isn't recorded, after money owed and before Complete.
- Stale blocks (brief 1): the client's checkout line hidden once released or handled outside (unless the client chose a
  way to pay); the payment form already showed only while something is owed.
- Words kept true: the payment step and the work orders' "invoice #" field no longer say "nothing is posted to it".

Admin's test: Paid in QuickBooks → nothing to do, ↗ to see it. Vendor not billed → drop their invoice or Record the bill
by hand. Read with a question → press the right answer, then Record. Billed → ↗ to see it in QuickBooks. Not in
QuickBooks yet → Send it to QuickBooks. Disputed → Dispute settled when it is. Not billed by them → They billed after all.

Kept, moved, dropped: nothing dropped. The client's checkout line hidden when it no longer applies (brief).

Not built: the mockup's ✉ beside the client's name (Client section): what it should do (mail from their own app, or
Send the client an update) isn't decided; asked in the review.

Tests (design harness): `m1b.php` 45 checks; `m1b.cjs` 37 × 1280 and 390 (74), every new button pressed, a real PDF
upload read in the browser (`m1b/make-invoice.py`, made up), pdf.js served from npm's pdfjs-dist 3.11.174 where cdnjs
isn't reachable (PDFJS_DIR); M1's `m1.php` (42), `m1.cjs`, `collect-balance.cjs`, `complete-owed.cjs` all pass. Screens
in `handoffs/order-m1b/`. Harness notes: `wf_*` tables made by hand; the Mailer stand-in returns while
`<root>/harness-mail-ok` exists (never shipped); the staff user is named "Staff".

### The Design reviewer's changes, round 1 (2026-11-23 21:48 UTC), made
1. Delivery's one action is **Yes, picked up** (filled only when no money is owed; else the Money line's button is the
   one filled); **Remind the client** is in its ⋯.
2. **Complete** is greyed "once it's picked up" (or "once it's delivered") while Delivery still asks; the Order ⋯'s
   Complete the order greyed with the same reason. Kept: the #finish section's own Complete the order stays pressable.
3. One fact asked once: the Vendor read's "It was picked up" also records the pickup on Delivery (`markReceived`: paid →
   the order completes, M1's rule; owed → it stays open); "Yes, picked up" first makes the read show "✓ Will Call,
   picked up Nov 23" and ask nothing.
4. A chosen answer shows ✓ outlined, the other dimmed; Record the vendor bill's reason in words while greyed ("Answer
   the shipping question first"), the QuickBooks sentence under it once it's ready; no focus ring by itself.
5. ↗ inline SVG: fine. 6. The ✉: left out; a behaviour question for the session manager.
M1's tests updated to the reviewed behaviour (m1.cjs: Delivery's one action, Complete greyed then Yes, delivered;
complete-owed.cjs: a paid order's #finish Complete pressable). All pass: m1b.php 45, m1b.cjs 41 × 2 widths (82), m1.php
42, m1.cjs, collect-balance.cjs, complete-owed.cjs. New screens: m1b-read-answered, m1b-delivery-first.

### The client's ✉ (the session manager's decision via the Design reviewer, 2026-11-23 21:49 UTC), made
A round ✉ beside the client's name in Bill to, titled "Send Ana an update": opens Send the client an update in place
(the note optional; Cancel and Esc close it); after it, "✓ Sent <day, time, zone>" (`data-tv-time`), no Undo, no ✎;
recorded in the History (`update_sent`). It uses the existing sender, `ClientOrders::sendUpdate` (the site's Mailer, a
system email), not the 0172 dispatcher: rerouting client order emails through it is its own change, **asked in the
summary**. `sendUpdate` now says Sent only when the email went (it ignored a failed send): a failure says so and records
nothing. FAQ: "How do I send a client an update on their order?".

Rebuilt on Sync 2026-11-23 22:02 UTC (`2caf2a1`): only `faq.md` had changed live; M1b's four entries re-applied on it.
Zip check: clean, no blocks. Tests: m1b.cjs 46 × 2 widths (92), m1b.php 45, m1.php 42, m1.cjs, collect-balance.cjs,
complete-owed.cjs all pass.

### The Design reviewer's round 2 (2026-11-23 22:02 UTC), made
1. An answer is a fact, recorded when given: choosing "It was picked up" asks Delivery's own question first, then records
   the pickup at once (do=picked_up) and redraws the Order and Delivery lines in place: "Delivered on Nov 23, recorded by
   Staff" with ✎; the answer reads "✓ It was picked up: recorded on the Delivery line", the other answer greyed with why
   (Delivery ⋯ Not delivered after all to change it); the state lines then have one filled button (Record the vendor bill).
2. The ✉: made (above). Also the Order line's words for a completed order follow who said it arrived ("Picked up on Nov
   23, recorded by our team", not "The client confirmed").
Tests: m1b.cjs 49 × 2 (98), m1b.php 45, M1's suite all pass.

### Design: approved (the Design reviewer, 2026-11-23 22:32 UTC, on 0043f67f), with two word fixes, made
- A pickup says so: Delivery "Picked up on Nov 23, recorded by <name>" (the `received` event's own "Picked up" note, so
  it stays true once the order completes) and the Order line "Complete. Picked up on Nov 23, recorded by <name>; …".
- One name for one fact: both lines name the person who recorded it.
Tests: m1b.cjs 50 × 2 (100), m1b.php 45, m1.php 42, m1.cjs, collect-balance.cjs, complete-owed.cjs all pass. Zip check
clean on Sync 22:02 UTC (`2caf2a1`), none of M1b's files changed live since. The session manager (22:19 UTC): the ✉ uses
the existing Mailer and History; moving client emails onto the 0172 dispatcher is its own item, not M1b.

### The session manager's review (2026-11-24 00:02 UTC): clear to ask, two checks, made
- qb_orders_tick is in `Scheduler::JOBS` and `AUTO` (Scheduled tasks), its page on `job-line.php`.
- The account a vendor bill went to is named on the Vendor line ("In QuickBooks: Cost of Goods Sold"; `qb_account`, in
  the new table's CREATE); Dispute…'s ⋯ says "not sent to the vendor (tell them yourself)". Re-recorded (send.py) on Sync
  22:02 UTC; m1b.php 45, m1b.cjs 102, m1.php 42, m1.cjs pass.

### Jason's "Review this again" on reading 2 (via the session manager, 2026-11-24 00:22 UTC), made
The vendor bill's account is a setting, never .env (CLAUDE.md: .env only for secrets):
- **Vendor bills post to** in System Configuration → Settings (`VendorBills::settingsCard`, hooked into `tv_site_settings`;
  `qb_settings` row `vendor_bill_account`): QuickBooks' Cost of Goods Sold accounts read live (`QuickBooksPay::cogsAccounts`),
  by number and name, the first preselected ("not chosen yet") until chosen; its History in `qb_settings_log` (new,
  add-only, money class); finance and operations managers change it, others see it greyed with why.
- The Vendor line says where a bill will post before Record ("Posts to 5000 · Cost of Goods Sold", ✎ to the setting in a
  panel) and, once billed, where it went ("In QuickBooks: …").
- `createBill` takes the account it's given; `QB_COGS_ACCOUNT_ID` is read once only, to seed the setting (History says so).
- **Not built, asked:** step 1, the vendor's own default expense category in QuickBooks. Whether the Accounting API's Vendor
  entity exposes it couldn't be confirmed from this session (Intuit's developer docs aren't reachable from here; the web
  search found only QuickBooks' screen for it). Building against an unconfirmed field would be a guess.
Tests: m1b.php 51, m1b.cjs 55 × 2 (110), M1's suite all pass. Screens: m1b-setting, m1b-setting-chosen, m1b-posts-to.

## Open items after M1b
- **The vendor's own default category first** (the session manager, 2026-11-24, option (a)): confirm the Vendor
  default-category field against a real QuickBooks Vendor read in the sandbox, then use it before the setting
  "Vendor bills post to" (`QuickBooksPay::createBill`, `VendorBills::account`).
- Moving client order emails onto the 0172 dispatcher (the session manager's separate item, not M1b).
- The Design reviewer (00:35 UTC): a finished step replaces its form. Once chosen, the setting is one line, "Vendor bills
  post to 5010 · Printing and production · chosen by <name>, <time>" with ✎ "Change the account" reopening the dropdown in
  place (Cancel, Esc close it); no repeated success notice; History under it. m1b.cjs 56 × 2 (112) pass.
