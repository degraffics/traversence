# Money-path review: `ClientOrders.php` against decisions 0177 and 0178

Draft for human review. Several findings depend on helpers this file calls but doesn't contain (`Orders::*`, `QuickBooksPay::*`, `WorkOrders::*`, `Costing`). Where a finding depends on one of them, I say so.

## A. A test that could move real money

**A1. A test copy is invoiced and charged in Production when Payments is in Production (high).** Breaks 0178 "Not changed": `guardReal()`, "no test moves real money".

- `duplicate()` makes a test copy a sandbox order (`ClientOrders.php:196`, via `withEnv('sandbox', …)`).
- `approve()` then calls `QuickBooksPay::invoiceFor(...)` with no `withEnv` wrapper (`:1470`), so the invoice goes to whatever mode is on now.
- It then overwrites the order's mode with the current one: `qb_env = QuickBooksPay::env()` (`:1481`).
- It renumbers the order (`:1485`, "its mode is settled now").
- `cantInvoice()` refuses only Sandbox-on-a-real-order (`:1503`). It never refuses Production-on-a-test-order.
- After approval, `pay()` → `Orders::sameEnv($row)` (`:1731`) compares against the overwritten `qb_env`, so it passes. The card form charges a real card in Production.

The copy is billed to staff (`:192`). Even so, a test copy approved while Payments is in Production makes a real QuickBooks invoice and can take a real charge. Whether `invoiceFor`/`chargeCard` call `guardReal()` themselves is not shown.

- **Smallest fix:** in `cantInvoice()`, refuse when `Orders::isTest($o) && $env !== 'sandbox'`. In `approve()`, wrap `invoiceFor` in `withEnv($o['qb_env'] ?: env())`. Stop overwriting `qb_env` at `:1481`.

## B. A record without a charge

**B1. `paidInQuickBooks()` records the whole balance as paid without checking any money (high).** Breaks 0178 note (M1b): "QuickBooks is the record". Also the rule that a payment is recorded only when it happened.

- At `:1753`, if `fromQuickBooks()` returns `''`, the code falls through and records `left` as a full charge (`:1758`).
- `fromQuickBooks()` returns `''` in several cases where nothing new was paid:
  - the lock wasn't got (`:1775`);
  - QuickBooks didn't answer, or reports a total of zero or less (`:1784`);
  - QuickBooks' paid amount is already in the ledger (`:1788`).
- So a partial payment already recorded, or QuickBooks being down, marks the order paid in full. That releases the vendor and delivery, completes GO Codes and sends the client a "paid in full" receipt.
- The fallback also takes no `tv-pay-` lock, so it can run alongside `pay()`. The keys differ (`charge-<oid>` vs `pay-…`), so both get recorded.
- The caller is not shown.
- **Smallest fix:** delete the fall-through at `:1754–1758`. Rely on `fromQuickBooks()` only, or record only the amount `invoicePaid` actually reports, under the lock.

**B2. A staff payment can overrun the total in a race with a card payment (med).** Breaks 0178 §1 (the refund that follows is limited by what was paid).

- `recordPayment()` checks `left` (`:2188–2190`) and records (`:2194`) without taking the `tv-pay-<oid>` lock.
- `pay()` (`:1722`) and `applyQuickBooks()` (`:938`) do take it.
- A check recorded while the client's card is charging can push the paid amount above `total_cents`.
- **Smallest fix:** take `GET_LOCK('tv-pay-'.$oid)` around `:2186–2194`, and re-read `left` inside the lock.

**B3. `recordPayment()` keys duplicates by amount, method, reference and minute (med).** Breaks 0178 note: Cancel the record vs Refund; "never entered twice".

- The key is built at `:2193`.
- **Two real payments dropped:** two equal cash payments with no reference in the same minute become one ledger entry. `received()` silently returns (`:2510`).
- **One payment posted twice:** the same press either side of a minute boundary is recorded twice.
- **QuickBooks still posted on a deduped press:** when `received()` dedupes, `recordOtherPayment` is still called (`:2202`). Whether QuickBooks dedupes by `$key` is not shown.
- **Smallest fix:**
  - Have `received()` return whether it recorded.
  - Skip the QuickBooks post and the event when it didn't.
  - Key on a one-time form token instead of the clock.

## C. A charge without a record, and retries

**C1. A card can be charged with no ledger entry if `received()` fails (med).**

- In `pay()`, after `chargeCard` succeeds (`:1735`), `received()` (`:1744`) is not inside a try. If `Orders::record` throws, the card is charged and nothing is in the ledger.
- A retry reuses the same key (`paidBefore` is unchanged, `:1734`). It is safe only if `chargeCard` treats `$key` as an idempotency key, which is not shown.
- `recordCardPayment` (`:1740`) may have posted the payment to QuickBooks, so `fromQuickBooks` could later recover it.
- **Smallest fix:** wrap `:1744` in a try/catch that writes a `needs_books` event with the charge id and amount. Confirm `chargeCard` sends `$key` as the gateway's Request-Id.

**C2. The amount shown isn't always confirmed before the charge (low/med).** Breaks 0177 §2: "the server confirms the amount shown before any charge".

- `pay(..., ?int $expect = null)`: when `$expect` is null, the confirmation at `:1730` is skipped.
- Separately, the "send" email states the deposit on the pre-tax subtotal (`:695`), but `approve()` sets it on the taxed total (`:1479`).
- **Smallest fix:** make `$expect` required, and refuse the charge when it's missing.

**C3. A linked invoice's payments could be recorded twice, depending on code not shown (low).**

- `applyQuickBooks` skips payments marked `ours` (`:942`).
- If `recordOtherPayment` (`:2202`) doesn't leave that mark, a partial check recorded here is read back from the linked invoice as new money. It would be recorded a second time, capped at what's left (`:950`).
- **Smallest fix:** also skip QuickBooks payment ids already stored in `order_ledger.qb_payment_id`.

**C4. Money arriving in QuickBooks after the order is paid is silently ignored (low).**

- `applyQuickBooks` stops (`break`) once the status isn't pending (`:947`) or nothing is left (`:949`).
- An overpayment or second payment in QuickBooks after "paid" leaves no trace on the order. Contrast the `needs_books` event at `:954`.
- **Smallest fix:** write a `needs_books` event in those two cases instead of breaking silently.

## D. Deposit (0177 §3)

**D1. An unchecked COGS is treated as checked (med).** Breaks 0177 §3: "An item whose COGS isn't checked (Costing: unchecked, or zero) … uses 65%".

- `depositFor` treats any cost above zero as known (`:139–140`). It never asks `Costing` whether the cost is checked.
- Catalog list costs (`:316`, `:404`, `:2049`) and costs carried "at today's costs (unchecked)" by `duplicate()` (`:214`) are all counted as checked COGS.
- **Smallest fix:** at `:140`, count the cost only when `Costing` says the line is checked. Otherwise fall to the 65% path.

**D2. A shipping cost alone makes an item look costed (med).** Breaks 0177 §3. The vendor is released on an under-sized deposit (0177 §4).

- At `:139`, `ship_cost_cents` is added to the item's cost, so an item with no product cost but some shipping cost passes `$c > 0`.
- Example: an unknown-cost item with $10 of shipping gets a $11.50 deposit instead of 65% of its price.
- `vendorHold` / `releaseAt` (`:798`, `:1797`) then release the work order once that small deposit is in.
- **Smallest fix:** require `cost_cents > 0` (and checked) for the cost path. Add the shipping cost on top.

## E. Tax and destinations (0177 §1–§2)

**E1. A single destination taken from an item's own address isn't used as the invoice's Ship to (med).** Breaks 0177 Status: release A builds "one destination's Ship to"; §1: "now with its real Ship to".

- `destinationGroups` groups by `ship_address` (`:1533–1534`).
- `needsClientAddress` looks only at `ship_to` (`:2160`), so `shipFrom` still asks the approval form for an address.
- The invoice's `shipTo` therefore comes from the form or billing (`:1430`, `:1468`), not from the item's address. QuickBooks taxes the wrong place.
- **Smallest fix:** when there's exactly one group and its `own` is true, pass that group's address as `$shipTo`.

**E2. Order-address items and pickup items aren't counted as destinations (med).** Breaks the 0177 Status rule ("an order shipping to more than one address isn't approved") and §1 (pickup's destination is our address).

- Items addressed through `order_addresses` (`ship_to` = `aN`, shown by `shipLabel`, `:2122`) aren't counted at `:1531–1535`.
- Pickup items are skipped (`:1532`).
- So an order shipping to two `aN` addresses, or mixing pickup with a shipped address, passes the >1 check (`:1437`). It becomes one invoice taxed at a single Ship to.
- **Smallest fix:** resolve `aN` through `addresses()` in `destinationGroups`. Count pickup items as our own address.

**E3. Paid-outside orders skip the several-destinations refusal (low).** Breaks the 0177 Status rule.

- The `!$outside` guard at `:1437` lets an outside order with several addresses be approved as one order. No QuickBooks tax is made, but it isn't sent address by address.
- **Smallest fix:** drop `!$outside` from `:1437`, or note it as a deliberate exception in 0177.

## F. Vendor and delivery release (0177 §4; 0178 "Not changed")

**F1. A paid-outside order is never held for delivery (med).** Breaks 0177 §4 ("Shipping still waits for the balance") and 0178 "Not changed" (delivery waits, "or an admin's release with a reason").

- `shipHold` returns `''` for `pay_mode='outside'` (`:781`).
- Such orders can be marked shipped or ready with money owed, with no admin and no reason recorded. `boardState`'s `relMet` (`:1050`) agrees with this.
- **Smallest fix:** remove `:781`, and let `recordPayment` or an admin's `releaseHold` free the order.

**F2. The outside path writes the release record without an admin or a reason (med, depending on the caller, which is not shown).** Same rules as F1.

- `releaseHold(..., $outside=true)` skips the admin check and the reason (`:2485