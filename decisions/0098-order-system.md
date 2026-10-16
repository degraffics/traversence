# 0098. One order system: orders, payments, refunds, deposits, chargebacks, subscriptions

- **Status:** Accepted (Jason, 2026-10-16); the build order is at the end
- **Builds on:** 0035 (internal billing: Subscriptions, Services, Counterparties), 0095/0096 (GO Codes through QuickBooks)
- **Jason, 2026-10-16:** "Since this is our transaction system we should have the ability to process orders correctly.
  So refunds, deposits, chargebacks are a part of that system. Also we have the o2o products and subscriptions that will
  be piped through this system, so we need to ensure it is not just GO Codes but order processing."

## Context

GO Codes are sold through QuickBooks (an invoice per order, paid on QuickBooks' page, confirmed by polling and webhook).
The tables are GO Code-specific (`qr_orders`, `qr_issued`), and nothing handles money going back: a refund in
QuickBooks leaves the order "paid" and the codes issued. Subscriptions (decisions/0035), services, top-ups, o2o products
and anything else Traversence sells need the same path, with the same records.

## Decision (proposed)

**1. One order model for everything sold.**
- `products`: what can be sold: key, name, kind (`one_time`, `subscription`, `service`, `o2o`), price, cadence for
  subscriptions, taxable, the QuickBooks item it maps to, and a **fulfiller**: the code that delivers it (GO Codes
  issues codes; a plan turns on a tier for a listing; an o2o product issues a redeemable voucher; a service opens a work
  item). New products plug in a fulfiller; the order flow doesn't change.
- `orders`: who (member, guest by private key, or a listing), status, totals, tax, billing details, the QuickBooks
  invoice, the access link. `order_lines`: product, quantity, price, what was delivered (e.g. the GO Code ids).
- `qr_orders` becomes a view or is migrated into `orders`; GO Codes' pages keep working.

**2. A payment ledger, never edited, only added to.** `payments` rows, each with amount (+ or −), method, QuickBooks
reference, date, who recorded it, and a kind:
- `charge`: the invoice paid (detected by polling or webhook, as now);
- `refund`: full or partial; recorded when we refund (button, below) or when QuickBooks shows a RefundReceipt / credit
  memo against the order;
- `chargeback` and `chargeback_reversed`: a card dispute. QuickBooks' accounting connection doesn't announce disputes,
  so these are recorded by staff from QuickBooks Payments' notice (button), with the dispute reference;
- `deposit`: when QuickBooks moves the money into the bank (read from QuickBooks Deposits), so the books show what was
  actually received, net of processing fees.
An order's state comes from its ledger: paid, partly refunded, refunded, disputed, chargeback lost / won.

**3. What a refund or chargeback does.** Staff choose full or partial, and a reason. The refund is made in QuickBooks
(a RefundReceipt against the customer, through the API, so it's in the books with its tax reversed), the ledger records
it, the buyer gets an email, and the product's fulfiller undoes what it can: GO Codes leave the buyer's account
(printed codes keep scanning: a static code can't be switched off), a plan's tier ends, an o2o voucher is voided. A
chargeback does the same when it's lost.

**4. Subscriptions** (decisions/0035: per listing, monthly or annual): each period, the system creates that period's
invoice in QuickBooks and emails it. **QuickBooks AutoPay**: a customer who ticks AutoPay on QuickBooks' payment page is
charged automatically after that; the rest pay the emailed invoice. Card data stays with Intuit; no Payments API and no
regulated-payments review. Past due after N days → reminders → the tier pauses (status per 0035: active, past-due,
paused, canceled). If automatic card renewals or dunning become a must, Stripe (on hold) is the upgrade path; the
ledger stays the same.

**5. Admin → Sales → Orders** replaces "GO Code orders": every order of every kind; filters by kind, status and date;
each order's ledger; buttons: Check payment, Refund (full / partial), Record chargeback, Record chargeback outcome,
Resend receipt, Cancel unpaid. Numbers: sales, refunds, chargebacks, net, tax collected, deposits.

**6. One QuickBooks link.** QuickBooksPay stays the only code that talks to QuickBooks; it gains refund receipts and
reading deposits and recurring invoices. Every call and webhook stays logged with its intuit_tid (decisions/0096).

## Update (2026-10-16): O2O, and where it lives in the admin

**O2O is Traversence's own print-on-demand and agency line** (Jason's FRD §6, now in `commercial.md`): starter kits,
banners, curb signs, cards, mailers, apparel, swag, design and video packs. These are products **Traversence sells**,
so they go through this order system, and they need what Jason's e-commerce concept describes:
- **Products with options** (size, material, finish, quantity runs, text, artwork upload, GO Codes to print) and price
  changes per option, so a new product is added in the admin without new code;
- **production routing**: each order line goes to a queue (proof / pre-press check, in production, at a vendor,
  shipped or ready for pickup), with **vendors** for outsourced work (decisions/0035's Counterparty) who receive the
  job and report it shipped, while the order keeps its status from start to finish;
- **shipping** for physical goods (rates and labels later through EasyPost or ShipStation; flat rates or local pickup
  first). Tax stays with QuickBooks Automated Sales Tax.

**Not in Sales: its own admin area, "E-Commerce"** (Jason: "I don't like it stuffed into the sales process"). Sales stays the
prospect pipeline. E-Commerce: Orders · Customers · Products · Production & fulfillment · Subscriptions · Promotions ·
Payments (the ledger, deposits, chargebacks, the QuickBooks connection and its health) · Reports. The GO Code orders
page becomes E-Commerce → Orders and E-Commerce → Payments.

**Three kinds of commerce, one order record** (Jason, 2026-10-16: businesses sell "from their storefronts at 0%
commission: their own payment processor and their own money. Traversence never holds it"; "the user to user
marketplace will have order processing" too). Every order has the same record (who, what, lines, status, history), so
orders look and behave alike; the **money** is what differs:

| Kind | Seller | Money | Managed in |
|---|---|---|---|
| 1. Traversence's sales | Traversence: GO Codes, plans, storage, top-ups, O2O print, agency | Traversence's merchant account (QuickBooks now); our ledger: charges, refunds, chargebacks, deposits | Admin → E-Commerce |
| 2. Business storefronts | A business, to visitors | **The business's own processor**, paid to them directly, 0% commission. We record the order and the payment status their processor reports; **no money ledger of ours, never custody** | The business's dashboard; admin read-only for support |
| 3. Member marketplace (C2C, FRD §3.2) | A member, to a member | **None through us**: settled offline (cash, pickup, their own transfer) | The two members in their chat: agreed → handed over → both mark complete (trust scores); disputes and reports in Admin → Safety |

Refunds, chargebacks and deposits exist only for kind 1 (for kind 2 they happen in the business's own processor; kind
3 has no payment to reverse, only both people agreeing to cancel). Which processors a business can connect (the FRD
names Stripe Connect; a business's own QuickBooks was discussed in future-considerations) is decided with the
storefront build; either way the money is theirs.

## Decided (Jason, 2026-10-16)

1. **Payments: QuickBooks.** All of Traversence's own sales (kind 1), subscriptions included, go through QuickBooks:
   invoices, QuickBooks' payment page, AutoPay for recurring. Other payment methods for the cart come with the listings
   e-commerce module (kind 2).
2. **The admin area is "E-Commerce"**: Orders · Customers · Products · Production & fulfillment · Subscriptions ·
   Promotions · Payments · Reports. Out of Sales.
3. **"Fix it, credit, then refund."** Refunds are avoided: first we **fix** it (redo, reprint, replace); if it can't be
   fixed, a **full or partial credit** on the buyer's account (store credit, used toward anything we sell); a
   **refund** only when neither works. **Made-to-order and custom products are final once the order (proof) is
   approved**: we always fix our own errors, production defects and shipping damage, never a change of mind or a
   mistake in what was approved. Things outside our control after delivery (weather, wear, mounting, a code printed
   too small) aren't ours. Written into the Terms of Use ("Fix it, credit, then refund"; the bracketed days and
   whether credits expire are for Jason and the attorney) and the FAQ.
   - In the ledger this adds `credit_issued` and `credit_used` (an account balance per customer, applied at
     checkout), and every order line gets **approved at** (the proof approval; for a GO Code, its issue) after which
     the line is final.
   - The E-Commerce order page offers, in this order: **Fix** (opens a remake or reprint linked to the order, at no
     charge), **Credit** (full or partial), **Refund** (full or partial; the reason is required).
4. **A refunded or charged-back GO Code becomes inactive, and its link shows a 404.** A QR code that holds its content
   itself can't be switched off, so: **paid link GO Codes now point through `trvgo.us/{code}`** (a fixed, non-editable
   short code; the buyer sees no difference) and a refund or a chargeback switches it off: trvgo.us answers 404 "This
   code isn't active". Wi-Fi, contact card, phone, email, text and event codes hold their content and can't be
   switched off once printed: they leave the buyer's account (no downloads, no fixes). Codes issued before this keep
   working as they are. The Terms and commercial.md say so.
4a. **Every GO Code routes through TRVGO** (Jason, 2026-10-16, superseding the "link codes only" in 4): "All codes
   should route through TRVGO for scan capture. They will all have the ability to be dynamic, but only if the code is
   set to offer access to editing", so a one-time static code can later get a subscription and go dynamic, with the
   same printed QR.
   - Every new code is a trvgo.us short code; every scan is counted (day and phone kind, as `decisions/0094`).
     **Static** = the buyer can't edit it; **Dynamic** = editable, through the QR plan or Core and up. Upgrading adds
     the subscription to that code; nothing is reprinted.
   - What a scan does per type: **link** → straight there; **contact card** → the .vcf (the phone offers Add contact);
     **event** → the .ics (Add to calendar); **phone, text message, email** → a small trvgo.us page that opens the
     dialer / messages / mail app at once, with a button as a fallback; **text** → a page showing the text with Copy.
   - **Wi-Fi stays direct** (one-tap joining only works when the camera reads the Wi-Fi code itself; no web page can
     join a network): not counted, not editable. (Jason didn't choose between the options; the recommended one stands
     until he says otherwise.)
   - Refunded or charged-back: the code is switched off and trvgo.us shows the 404 "not active" page. Only a Wi-Fi code
     can't be (it leaves the buyer's account).
   - **No more 24-hour self-fix window** (Jason: "there is no need to say edit it within 24 hours, because it can be
     edited by customer service in the event a static code is not working or pointing at something incorrectly"):
     **customer service can correct any static code at any time** (E-Commerce → the order → Fix), and the printed QR
     never changes. Buyers still can't edit a static code themselves.
   - **No old-style codes kept** (Jason: only 2 exist, both his sandbox tests): the build converts every issued code
     (Wi-Fi aside) into a trvgo.us code with the same content and look; their QR images change, nothing printed is
     affected. From then on there is one kind of GO Code.
   - Codes now depend on trvgo.us being up ("works while trvgo.us is up", replacing "never depends on us"): the router
     stays tiny (no session, one count write) so it's the most reliable thing we run.
   - The Terms, FAQ and commercial.md change with the build, so they describe what the site does.
5. Chargebacks: a disputed payment pauses what it paid for (codes, plans, credits) until the dispute is settled; lost
   means the same as a refund, won restores it.

## To confirm with Jason

1. ~~Subscriptions~~: QuickBooks (above).
2. ~~What o2o is~~: answered, Traversence's print and agency line (above). ~~Who holds a business's money~~: answered,
   the business, through its own processor; Traversence never does. (The FRD cites "ADR 0038" for Stripe Connect, but
   0038 is ownership transfer: the processor choice gets its own decision with the storefront build.)
3. ~~Refunded GO Codes~~: inactive, 404 (above).
4. ~~Partial refunds~~: fix it, credit, then refund (above).

5. ~~Name~~: E-Commerce.

## Build order, once confirmed

1. Admin → E-Commerce with Orders and Payments; the order model and ledger (charges, credits, refunds, chargebacks),
   GO Codes moved onto it; fix / credit / refund on each order; link GO Codes through trvgo.us and switched off on a
   refund or lost chargeback.
2. Products with options, and production routing with vendors; the first O2O products (Starter Kit, banner, cards).
3. Deposits read from QuickBooks; Reports net of refunds, chargebacks and fees.
4. Subscriptions (business tiers per listing, member storage, monthly design and video packs), with AutoPay.
5. Customers and Promotions.

## Money and banking (Jason's brief, 2026-10-16), mapped to QuickBooks

The brief's four layers (collection, settlement, accounts payable, tax) and its double-entry mapping, decided for a
QuickBooks-based setup:

**QuickBooks is the books; our ledger is the operations record.** We don't keep a second double-entry ledger. Every
money event in our ledger (0098 §2) creates or reads the matching QuickBooks record, and QuickBooks posts the
accounting entries:

| Event | Our ledger | QuickBooks record (it posts the entries) |
|---|---|---|
| Order checked out | order, lines, `approved_at` | **Invoice** (revenue + sales tax payable, by Automated Sales Tax) |
| Customer pays | `charge` | **Payment** against the invoice (QuickBooks Payments, on its page) |
| Payout to the bank | `deposit` (read) | **Deposit** (gross, less the processing fee as an expense): read to match orders to bank deposits |
| Credit on account | `credit_issued` / `credit_used` | **Credit memo**, applied to a later invoice |
| Refund | `refund` | **Refund receipt** (reverses revenue and tax) |
| Chargeback | `chargeback` / `…_reversed` | recorded by staff from QuickBooks Payments' dispute notice (the accounting connection doesn't announce disputes) |
| Work sent to a vendor | unit cost | **Purchase order** |
| Vendor invoices | vendor bill | **Bill** (COGS + accounts payable); paid by QuickBooks bill pay (ACH/check), weekly batch |

**Collection.**
- Card data never touches us (tokenization and vaulting are QuickBooks'). ✓ already.
- **Capture timing → "pay after proof" for custom products.** QuickBooks' hosted invoice page charges in full when paid;
  card pre-authorization (auth now, capture later) needs Intuit's Payments API, which brings the regulated-payments
  review we've avoided. The same goal (no charge, so no refund, if a custom order is cancelled before it's made) is
  met by **invoicing custom lines when the proof is approved**: order → proof → approval → invoice → paid → production.
  Ready-made products and GO Codes are paid at checkout as now. (Optional per product: a deposit at order, the
  balance on approval.)
- **Split payment:** store credit (credit memo) applied first, the rest on the invoice. BNPL (Klarna, Affirm) and gift
  cards: not now (with the listings e-commerce module, if ever).

**Settlement.** Gross and fees are separate lines (QuickBooks records the Payments fee on the deposit). Reports show
gross, fees, refunds, chargebacks and net, matched to deposits.

**Accounts payable.** Purchase order → bill → QuickBooks bill pay, weekly. Virtual cards (Ramp, Brex) and split payouts
at checkout (Stripe Connect) aren't used: vendors are paid on their terms from the operating account.

**Sales tax** is a liability, never revenue: QuickBooks Automated Sales Tax calculates it per address, tracks what's
owed per state, and files or reminds from its Sales Tax centre. A separate **tax holding account** is a banking choice
for Jason (a second checking account the collected tax is moved to); Reports show the tax collected per period so the
transfer is one number.

**Safety.**
- **Idempotency:** every create call to QuickBooks sends a `requestid` (QuickBooks ignores a repeat with the same id),
  derived from our order and event, so a retry or a double click never makes a second invoice, refund or bill. Our own
  checkout uses one order token per cart, so a second click finds the same order.
- **Refunds and chargebacks** hold unshipped fulfillment units automatically, and update the order.

**Net realized margin per order** = order total − (sales tax + shipping cost + payment fee + vendor COGS), in Reports
by order, product and vendor.

## Decided (Jason, 2026-10-16, on the money brief)

- **QuickBooks keeps the books; the site keeps the operations record.** Agreed.
- **Bills are the accountant's.** The site creates the vendor **purchase order** in QuickBooks when work goes to a
  vendor; entering the vendor's bill and paying it is the accountant's work in QuickBooks (no bill-pay automation).
- **Pay upon order approval, no holds.** The customer pays when they approve the order (for custom work: the order and
  its proof). No card holds or deposits taken by the customer's own choice, unless demand for that appears.
- **No double charges** (QuickBooks `requestid` on every create; one order token per cart). Confirmed.
- **Pricing (Jason's formula):** price = **COGS × markup**, where COGS is materials + the vendor's charge; **shipping and
  handling is a direct pass-through** at cost, added as its own line; **sales tax is separate**. So each product (and
  each option that changes cost) stores its **cost** and the **markup**; the price is computed, and changes when a
  vendor's cost does. Per order: **sales subtotal** = COGS × markup (+ S&H at cost); **gross profit** = subtotal − COGS
  − S&H; **total** = subtotal + tax; **net profit** = gross profit − payment fees. Tax is never revenue. Reports use
  these, by order, product and vendor.
- **Deposits are set by staff, never chosen by the requester.** Staff can **start an order for a customer** (a draft
  order, or quote): build the cart, set prices or adjustments, **require a deposit** (an amount or a percentage) or
  full payment, and **send it to the customer** to review, approve and pay. The deposit is the first invoice; the
  balance is invoiced on proof approval or delivery, as the order says. Orders customers place themselves are paid in
  full on approval.

## Built (2026-10-16): step 1

- **Tables** (`app/migrations/2026-12-02_orders.sql`, MySQL-safe, safe to run twice): `orders` (kind: traversence,
  business, c2c; status incl. draft, sent, part_refunded, refunded, disputed; deposit, created_by and staff_note ready
  for staff draft orders), `order_lines` (product + what it delivers, unit price and cost, approved_at), `order_ledger`
  (charge, deposit, refund, credit_issued, credit_used, chargeback, chargeback_reversed; each with a unique
  `request_key`, so a repeat is ignored; only ever added to) and `order_events` (the history, with who did each).
  `qr_orders` rows move in with the same numbers; paid ones get their charge in the ledger. `qr_orders` stays in the
  database, unused, until we're sure.
- **Status follows the money** (`Orders::settle`): paid, part_refunded, refunded, or disputed while a dispute is open.
- **Every GO Code goes through trvgo.us** except Wi-Fi: a short code of kind `go` (no owner, so it isn't in the
  member's Dynamic list) pointing at the `qr_issued` row. The router serves by type: link → 302; contact card → .vcf;
  event → .ics (wrapped in VCALENDAR with a UID); phone, text message, email → starts it at once (a page that opens
  the tel:/sms:/mailto: link as it loads, so no gate; its one button is there only for a browser that won't open the
  phone's app without a tap; on Android the scan is counted and then answered with a 302 to an `intent:` link, which
  the browser lets open the dialer, Messages or email app, with `S.browser_fallback_url` back to the button page
  `?b=1`, not counted twice); text → a page. **If Traversence closes** (Terms): `GO_DIRECT=on` in .env makes every
  download hold what the code does directly (no trvgo.us), for the download window the Terms promise. A code not free, paid or granted (refunded = `revoked`) or switched off → "This code isn't active" (404).
  The two codes issued before this are converted the first time their owner's list or file is opened.
- **No self-fix after issue.** The customer fixes a code in the cart; once issued, customer service fixes it on the
  order page (`Orders::fix` → `QrIssue::staffFix`); the printed code stays the same.
- **Admin → E-Commerce** (`admin/ecommerce.php`): Orders (disputes and waiting first; find by #, email, name or
  invoice), Carts (Grant), Payments (the QuickBooks page, out of Sales), Reports (this month and last, from the
  ledger). **An order** (`admin/order.php`): its lines with each GO Code's trvgo.us address, scans and picture; 1 Fix
  it, 2 Credit (a QuickBooks credit memo, non-taxable, with `requestid`; the customer is emailed), 3 Refund (made in
  QuickBooks by staff, recorded here with the refund receipt number and a required reason; capped at what was paid less
  refunds and credits; a full refund switches every line off, a partial one the lines picked), Card dispute (pauses
  the codes; Won turns them back on, Lost switches them off), Check payment, Cancel unpaid, a team note, the history
  and the money. Admins only, behind two-step sign-in. A card dispute shows on the admin Today list.
- **Not yet:** credits taken off at checkout on the site (QuickBooks' "Automatically apply credits" covers the
  invoice), staff draft orders and deposits, products with options and vendors (step 2).

## Built (2026-10-16): refunds both ways with QuickBooks

Jason: most refunds and disputes start in QuickBooks, so the site listens; and a refund started on an order shouldn't be
entered twice.

- **Refund from the order page, one step** (`Orders::refundThroughQuickBooks`): the card is refunded through
  **QuickBooks Payments** (`POST /quickbooks/v4/payments/charges/{CCTransId}/refunds`, the charge found on the invoice's
  Payment, `Request-Id` = the refund's key), then a **Refund Receipt** goes in the books (tax in the order's proportion,
  set with `TxnTaxDetail.TotalTax`, from `QB_REFUND_ACCOUNT_ID` or the first bank account, `requestid` = the same key,
  tagged `[TRV:key]` in its private note), then the order is put right and the customer emailed. A double click is one
  refund. If QuickBooks Payments refuses (paid by bank transfer, a charge it won't refund from another app), nothing is
  recorded and the page says to refund it in QuickBooks; if the card is refunded but the receipt fails, the refund is
  recorded and the order's history says to make the receipt in QuickBooks.
- **Needs the payments permission:** the connection now asks for `com.intuit.quickbooks.accounting
  com.intuit.quickbooks.payment` and remembers it (`qb_connection.scopes`). A connection made before shows "Card refunds
  from an order are off: reconnect" on Payments, and the order page offers only "Already refunded in QuickBooks: record
  it". **Unverified until a sandbox test:** whether Intuit lets an app refund a charge paid on QuickBooks' own invoice
  page; the fallback above covers it if not.
- **The listener** (`api/qb-webhook.php`): RefundReceipt and CreditMemo events (tick them in the Intuit app's
  Webhooks). Ours (tagged) are skipped. Otherwise matched by QuickBooks customer to our paid orders
  (`orders.qb_customer_id`, else the customer's invoices): **recorded by itself** when exactly one order can be the one
  and a refund is the whole amount left (every item switched off) or it's a credit memo; **anything else waits** in
  `order_inbox`, shown at the top of E-Commerce → Orders, on the admin Today list and in the menu badge, with the
  possible orders and why. A person opens the order (`order.php?id=…&inbox=…`), ticks which items stop, and records it;
  or marks it "Not ours". **Check QuickBooks for refunds** asks for the last 45 days, for a missed webhook. Void/Delete
  of a receipt is logged for a person to check.
- **Card disputes stay by hand:** QuickBooks has no webhook for them (Intuit emails the merchant); record the dispute,
  then Won or Lost, on the order.
- Migration `2026-12-03_qb_refunds.sql` (order_inbox, orders.qb_customer_id, qb_connection.scopes).

## Built (2026-10-16): the card on our checkout page, and the Sandbox / Production switch

Jason: the gap between cart and payment (a new tab on Intuit's invoice page, no way back, a wait) had to go; and a
switch to move between the QuickBooks sandbox and production for testing.

- **Card on our page.** Checkout makes the invoice as before (Automated Sales Tax), then shows subtotal, tax, total
  and a card form on the cart page. The card number, expiry, security code and billing ZIP go from the browser
  straight to Intuit's tokens endpoint (`/quickbooks/v4/payments/tokens`, no credentials, as Intuit's own PHP Payments
  SDK calls it); only the one-time token reaches our server (`go-codes.php` action `pay`), which charges it
  (`POST /quickbooks/v4/payments/charges`, `capture: true`, `context.isEcommerce`, `Request-Id` per order and attempt),
  saves `orders.charge_id`, records a QuickBooks Payment on the invoice (`TxnSource: IntuitPayment`,
  `CreditChargeResponse.CCTransId` = the charge, so it matches the deposit; a plain payment with a note for the team if
  QuickBooks won't take that form), and issues the codes. One charge per order: a lock while paying, the same request
  key until a decline (a double tap or retry is never charged twice). A decline shows the bank's reason and "nothing
  was charged". If the browser can't reach Intuit, the page offers QuickBooks' invoice page instead, which also stays
  as "Pay on QuickBooks' page instead (card or bank transfer)". Refunds from an order go straight to `charge_id`. A
  pending order's "Pay now" on Manage GO Codes opens the same form; "Back to the cart" cancels the unpaid order.
- **PCI:** the card never touches our servers, but the form is on our page, so this is SAQ A-EP (not SAQ A). The Intuit
  app's security answers about card data need updating to match.
- **The switch** (E-Commerce → Payments → Mode): Production or Sandbox (testing), kept in `qb_settings` (QB_ENV in
  .env is only the starting point; QB_ENV=mock still wins for local tests). Each mode has its own keys
  (`QB_CLIENT_ID`/`QB_CLIENT_SECRET`/`QB_WEBHOOK_TOKEN` for production, `QB_SANDBOX_…` for the sandbox; an older .env
  with QB_ENV=sandbox and only plain keys reads them as the sandbox's) and its own connection (row 1 production, row 2
  sandbox; the connection made before is moved to its mode). Tokens are now sealed with the site's own secret, so
  moving Intuit's keys around in .env doesn't lose a connection. Every order records `qb_env`; QuickBooks work on an
  order (payment check, card refund, credit) only happens in its mode. While Sandbox is on, checkout is open to our
  team only, with a "Test mode" note and Intuit's test card. Sandbox orders carry a Test tag and stay out of Reports.
  Webhooks are taken from either mode, by whichever token signed them. Orders made before this are marked sandbox
  (production wasn't on yet).
- Migration `2026-12-04_qb_checkout.sql` (qb_settings, qb_connection.env, orders.qb_env, orders.charge_id).
- **Checkout footer** (Jason): the cart and checkout drop the site footer (the claim call-out and site links) for a
  short customer-service one: how to reach us about an order, how card payment is handled, and links to order help,
  fixes/credits/refunds, terms of purchase and privacy (`tv_page_end(['footer' => 'checkout'])`, `tv_footer_checkout()`).
- **Fixes after the first sandbox run (2026-10-16):** card payment refuses before charging if
  `2026-12-04_qb_checkout.sql` hasn't run (it had charged, then failed saving `charge_id`); once a card is charged,
  nothing can stop the order being marked paid (a failure is noted for the team instead); database errors are never
  shown to customers; refunds or credit memos in QuickBooks for customers with no paid orders from the site are
  skipped, not put in the inbox (the sandbox's sample customers filled it); a contact card's website may be trvgo.us
  (only a link or Dynamic code can't point to another trvgo.us code).
