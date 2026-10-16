# 0098. One order system: orders, payments, refunds, deposits, chargebacks, subscriptions

- **Status:** Proposed (2026-10-16), for Jason to confirm before building
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

## To confirm with Jason

1. Subscriptions through QuickBooks invoices with AutoPay (above), or hold subscriptions for Stripe?
2. o2o products: offers or vouchers redeemed at a business, businesses' own products, or Traversence's printed goods?
   (The model takes all three; the first fulfiller built follows the answer.) Who receives the money for a business's
   o2o sale: Traversence or the business (see future-considerations, "Businesses selling through their own
   QuickBooks")?
3. A refunded or charged-back GO Code leaves the buyer's account (recommended), or stays?
4. Partial refunds allowed for all products, or only full?

## Build order, once confirmed

1. The order model and ledger, with GO Codes moved onto it; Admin → Orders; refunds (full / partial) and chargebacks.
2. Deposits read from QuickBooks; the numbers net of refunds, chargebacks and fees.
3. Subscriptions for the business tiers (per listing), with AutoPay.
4. The first o2o product.
