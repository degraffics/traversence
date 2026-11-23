# 0177: One order, several destinations: invoices, tax and the deposit

**Status:** accepted (Jason, 2026-11-23). Built in the full ordering release.
**Builds on:** 0101 (client orders), the order model (`research/order-model-2026-11-21.md` §1.8, §1.9), QuickBooks Online's
automated sales tax.

## Why

Jason, 2026-11-23: "what if we have an order that has multiple ship tos?" … "My recommendation for several destinations is
one invoice per destination under the same order, so each is taxed for where it goes."

QuickBooks Online works out sales tax from the invoice's one **Ship to** (the company address if none is given). A line
carries only **taxable / not taxable**, never its own address. So lines on one invoice can differ in *whether* they're
taxed, never in *where*. Sources:
[how QBO calculates sales tax](https://quickbooks.intuit.com/learn-support/en-us/help-article/sales-taxes/learn-quickbooks-online-calculates-sales-tax/L8VWCLobK_US_en_US),
[automated sales tax on an invoice](https://quickbooks.intuit.com/learn-support/en-us/help-article/sales-taxes/use-automated-sales-tax-invoice-sales-receipt/L9LdFWhoj_US_en_US),
[several ship-tos on one invoice](https://quickbooks.intuit.com/learn-support/en-us/other-questions/invoice-with-multiple-shipping-addresses-and-sales-tax-amount/00/1321561).

Today the site sends the billing address as both Bill to and Ship to (`QuickBooksPay::invoiceFor`: `'BillAddr' => $addr,
'ShipAddr' => $addr`), so tax follows billing, not where the goods go.

## Decision

### 1. Three levels

| Level | Holds on its own | Joins the others |
|---|---|---|
| **Item** (a line) | Product, quantity, price, its COGS (`cost_cents`, `ship_cost_cents`), proof, its destination, taxable or not | On exactly one invoice: its destination's |
| **Invoice** (one per destination) | The Ship to, QuickBooks' tax for that address, its total, balance and payment link | Holds the items going there; belongs to one order |
| **Order** (the whole) | The client, the Bill to, every item, the total across its invoices, the deposit and what's owed | The sum of its invoices. The client sees one order page, one balance, each invoice listed |

- **Bill to** is the client's billing address on every invoice; only the **Ship to** changes.
- An order with one destination has one invoice, as today, now with its real Ship to.
- Pickup, delivery by us, and digital items: their destination is our address (QuickBooks' own fallback), named as such.

### 2. Tax

- The order taker picks the order's tax: **taxable**, **reseller**, **exempt**, **out of state**; an item can differ.
  It is sent per line as taxable / not taxable (`TaxCodeRef` TAX / NON); QuickBooks applies the rate for that invoice's
  Ship to. A reseller or exempt choice keeps its certificate note on the order.
- What the client sees before approving is the tax QuickBooks gives back, per invoice; the server confirms the amount
  shown before any charge.

### 3. The deposit: COGS + 15%

Jason, 2026-11-23: "in most cases deposits are figured by covering cogs + 15% so if we are breaking down the deposit on
multiple shipment orders than this should be factored."

- **Each invoice's deposit = its items' COGS (product and shipping cost) × 1.15**, never above that invoice's total.
  The order's deposit is the sum of its invoices' deposits. This replaces 1.8's "65% default" as the default.
- **Staff can still set it per order** (1.8: "Percentage set per order by staff"); a set % applies to each invoice's total.
- **An item whose COGS isn't checked** (`Costing`: unchecked, or zero) can't be figured: the order says so beside the
  deposit and uses 65% for that item until its COGS is checked. Approval shows which items those are.
- A deposit taken on an estimate counts toward the order (1.8), applied to its invoices in proportion to their deposits.

### 4. Payments and the vendor

- A payment pays the order's balance and is recorded against its invoices: deposits first, then each invoice's balance,
  oldest destination first.
- **The vendor release waits on the whole order** (1.9: "Paid in full before order is released to vendor", a deposit
  the exception): the order is paid in full, or its whole deposit is in, across all its invoices. The destination
  invoices exist for tax; they're parts of one order. Jason confirmed it, 2026-11-23: "Paid in full or overrides. single
  invoices are tied to the whole order status." No invoice releases its destination on its own.
- Shipping still waits for the balance (1.9), per the whole order; the admin's override (Release before payment, with a
  reason) is unchanged.

## Confirmed

- §3, Jason, 2026-11-23 (pop-up, reviewed by the session manager): Approve. "Deposit default becomes items' cost + 15%
  (65% where an item's cost isn't recorded); staff can still set a % per order." It replaces 1.8's 65% default.
- §4, Jason, 2026-11-23: "Paid in full or overrides. single invoices are tied to the whole order status."
- The shipping we pay (`ship_cost_cents`) is a cost of goods, so it's in the COGS (session manager, 2026-11-23).
