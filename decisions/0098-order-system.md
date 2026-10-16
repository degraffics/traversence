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
