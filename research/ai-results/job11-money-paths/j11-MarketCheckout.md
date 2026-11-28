# Money-path review: MarketCheckout.php against decisions 0177 and 0178

**Draft for human review.** Line numbers refer to `traverse-3way-sync/sync-app/lib/MarketCheckout.php`. `MarketPay`, `KindCheckout`, `BizQuotes`, `BusinessData`, the webhook endpoint and the pay-page callers are **not shown**, so findings that depend on them say so.

## Scope

Both decisions govern our **client orders** through QuickBooks. This file is the **Marketplace**, where a business is paid through its own Stripe or Square account. Only parts of the decisions map onto it:

- **0177 (destinations, tax by Ship to, COGS + 15% deposit):** nothing found that breaks it.
  - An order carries one `ship_name`/`ship_address` (lines 369, 382). Tax is the business's single rate (line 372), not a per-destination QuickBooks rate.
  - The deposit is KindCheckout's `balance` (line 386), not COGS.
  - No several-destination path exists, which matches 0177's status line ("isn't approved" until release B).
  - Open question for Jason: whether 0177 is meant to reach the Marketplace at all.
- **0178:** these parts apply:
  - "`guardReal()` (no test moves real money)"
  - "Money never has Undo" / "Cancel the record"
  - The CFO note on Marketplace refunds (`'refunded'` never set)
  - The principle that a charge and its record must agree.
- **0178 §1 ("Every refund … waits for his OK"):** `rentalReturned` refunds through the business's own gateway with only a team check (lines 724, 733). The CFO note treats Marketplace refunds as the business's own money, so this is listed as a **scope question**, not a defect.

## Defects

**1. Rental deposit refund can run twice (double refund).** Lines 726, 732–734.
- The "already returned" check reads the caller's in-memory `$o` (line 726).
- Money moves first (line 732 capture / line 733 refund).
- Only then is `pay_mode='returned'` written, with no condition (line 734).
- So two clicks, two tabs, or a retry after a gateway timeout each pass line 726 and refund or capture again.
- If line 734 fails after the gateway succeeded, the record says "not returned" and a retry refunds again.
- Breaks: 0178 money-record principle (a processed refund needs exactly one record); "a retry that double-charges".
- Severity: **high**.
- Fix: claim first with `UPDATE … SET pay_mode='returning' WHERE id=? AND pay_mode IN ('payment','hold')` and stop if `rowCount()=0`. Then call the gateway with an idempotency key derived from the order id. Set `'returned'` after it succeeds.

**2. A buyer's cancel of a card order being paid leaves a charge with no record.** Lines 518, 788.
- `cancel()` sets `status='cancelled'` for `pending` card orders (line 518). Nothing in this file closes the gateway's checkout session.
- If the buyer completes payment in the still-open session, `confirmAndMark` returns false because `cancelled` isn't accepted (line 788). `markPaid` would also refuse (line 574).
- Result: the business is charged on the buyer's card, our order says Cancelled, no invoice is written, and the buyer gets no receipt.
- Breaks: 0178 money-record principle ("a charge without a record").
- Severity: **high**.
- Fix, smallest of:
  - (a) refuse `cancel()` for `method='card'` while `status='pending'` and `provider_ref<>''`; or
  - (b) expire the gateway session before setting cancelled.
- Also: in `confirmAndMark`, when the gateway says paid on a cancelled order, write a note and a Work queue ticket instead of a silent `false`.

**3. An expired card order paid late is never reconciled if its webhook is missed.** Lines 804, 810, 788.
- `tick` expires pending card orders past their hold (line 810).
- The reconcile query asks the gateway only about `status='pending'` (line 804).
- `confirmAndMark` does accept `expired` (line 788), but nothing in this file asks about expired orders.
- A payment completed near the hold's end whose webhook fails or is dropped stays Expired forever.
- Breaks: 0178 money-record principle; file header line 13 ("or our question to it").
- Severity: **med**.
- Fix: change line 804 to `status IN ('pending','expired')`, bounded to, for example, `created_at > -2 days`.

**4. Payment is confirmed and refunded against the business's current gateway, not the order's.** Lines 789–791, 730–733.
- `confirmAndMark` and `rentalReturned` look up the gateway by `entity_id` at the time of the action (lines 789, 730). They ignore the order's stored `provider` (line 382) and its account.
- If the business reconnected to a different Stripe or Square account between placing and confirming or refunding (`payStatus` line 109 shows this case is expected), confirmation asks the wrong account.
  - The result is "not paid", which leads to defect 2/3's charge with no record.
  - A refund goes to the wrong account or fails.
- Breaks: "a destination that gets the wrong share / misroutes".
- Severity: **med**.
- Fix: store `account_id` on `market_orders` at placement. In both functions, refuse and ticket when it differs from `gateway()['account_id']`.

**5. Webhook events are marked handled before they're handled.** Lines 777–781.
- `firstTime` inserts the event id up front.
- If the caller's handling then throws, a redelivery is rejected as already seen.
- With defect 3, an expired order then has no second path to being recorded.
- The caller is not shown, so this depends on its order of operations.
- Severity: **med** (conditional).
- Fix: delete the `market_pay_events` row in the caller's catch, or insert it only after `confirmAndMark` succeeds.

**6. The business's invoice uses the tax rate at payment time, not at placement.** Lines 372, 642–645.
- The amount charged uses `$s['tax_milli']` at `place()` (line 372).
- For a card order, `writeOrder` runs inside `markPaid`, minutes to days later for an expired order. It writes the rate the business has *then* (lines 642, 645), while `total_cents` is forced to the old total (line 645).
- If the rate changed in between, the invoice's line tax and its total disagree. This breaks the file's own promise at lines 17–18 ("the order and its invoice always agree").
- Breaks: 0177 §2 by analogy (the client is charged the tax shown); line 18.
- Severity: **low–med**.
- Fix: store `tax_milli` on `market_orders` at placement and use it at line 645.

**7. Whether the business's copy is really "whole or not at all" can't be confirmed from this file.** Lines 625, 627, 631, 635–648.
- The transaction is opened on `$bp` (line 631).
- Every write goes through `$main = Database::connection()` (lines 635, 638, 641, 648); only the UPDATE at line 644 uses `$bp`.
- If `$bp` and `$main` are different connections, a throw after `BizQuotes::create` leaves a half invoice. Then `copyFailed` asks our team to write another, which would double the invoice and its payment in the business's books.
- `BusinessData::scoped` is not shown.
- Severity: **med** if they differ, none if they are one connection.
- Fix: pass `$bp` to `BizQuotes::*` and `Customers::ensure`, or assert `$bp === $main` before line 631.

**8. A rental "hold" is recorded as paid before any money is captured.** Lines 385–386, 574, 732.
- A `pay_mode='hold'` order goes through `markPaid` on the gateway's confirmation (line 574).
- `writeOrder` then records a card payment of `due()` in the business's books (line 648), and the business is told "paid by card" (line 593).
- Money is only captured at return (line 732). If the authorization has lapsed by then (card holds expire), the capture fails, but the order and invoice already say paid.
- What `MarketPay::confirm`/`capture` do for holds is not shown.
- Breaks: "a record without a charge".
- Severity: **med** (conditional).
- Fix: in `markPaid`, when `pay_mode='hold'`, skip the payment at line 648 (record it at capture), and say "authorized" in the notice.

**9. A deposit at or above the total silently becomes a full charge.** Lines 386, 549.
- `pay_now_cents = total − balance` (line 386).
- If KindCheckout's `balance` is ≥ total, `due()` falls back to charging the whole total (line 549).
- The deposit-only intent is lost without an error.
- KindCheckout is not shown.
- Severity: **low**.
- Fix: in `place()`, throw when `balance >= total`.

**10. A direct payment sent before a buyer's cancel has no path to a record.** Lines 518, 523, 608.
- After the buyer cancels an awaiting order, the business is only *told* to void the invoice (line 523); the invoice stays approved and owing.
- If the buyer had already sent Zelle or a check and the business marks the invoice paid, `quotePaid` ignores `cancelled` (line 608).
- The money is then in the business's books but not on our order.
- An expired awaiting order's invoice is likewise never voided (line 810).
- Severity: **low**.
- Fix: let `quotePaid` also match `cancelled` and write a note plus a ticket, rather than ignore it.

**11. Test purchases.** Lines 471, 485.
- `testAllowed` refuses outside `sandbox`/`mock` (line 471), which is consistent with 0178 "`guardReal()` (no test moves real money)". Whether the card session for a test re-checks `env` when it is created is not shown.
- Line 471's live-mode advice ("buy one of your own items … then refund it") tells the business to move real money as a test. Processor fees are usually not returned.
- Line 485 cancels an earlier *pending* test even if it was paid in the meantime, giving defect 2's charge with no record, with test money only.
- Severity: **low**.
- Fix: soften the line 471 wording. At line 485, also require `provider_ref=''` or ask the gateway first.

**12. 0178 CFO note: nothing in this file sets `'refunded'`.**
- `'refunded'` appears only in STATUSES (line 32); no setter is in this file.
- `rentalReturned` checks only `status === 'paid'` (line 725), so an order the business refunded in its own dashboard can still be refunded again here.
- Whether "Mark refunded" exists elsewhere is not shown.
- Severity: **low** (the gateway would likely refuse a refund beyond the charge).
- Fix: once Mark refunded exists, also refuse when a refund note or record exists.

**Nothing found in the following:**
- `totals` (line 321)
- The stock hold LIKE query (lines 258–259)
- `quotePaid` for awaiting/expired orders (line 608)
- `markPaid`'s guard against marking an order paid twice (line 576)
- Subscription cancel (lines 742–752)

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| MarketCheckout.php:726,732–734 | Rental refund/capture can run twice; record written after money moves, no condition | 0178 money note (one record per processed payment) | high | Conditional claim UPDATE before the gateway call; idempotency key |
| MarketCheckout.php:518,788 | Cancel of a pending card order leaves the gateway session open; a later payment has no record | 0178 money note | high | Refuse cancel of pending card orders (or expire the session); ticket when paid-on-cancelled |
| MarketCheckout.php:804 | Reconcile asks only about pending orders; late-paid expired orders are never recorded | 0178 money note; file header line 13 | med | `status IN ('pending','expired')` bounded by age |
| MarketCheckout.php:789–791,730–733 | Confirm and refund use the current gateway, not the order's account | Misrouted money | med | Store `account_id` on the order; refuse on mismatch |
| MarketCheckout.php:777–781 | Event marked seen before it's handled; a failed handling isn't retried | 0178 money note | med (caller not shown) | Remove the event row on failure, or insert after success |
| MarketCheckout.php:642–645 | Invoice tax rate read at payment time, not placement | 0177 §2 by analogy; line 18 | low–med | Store `tax_milli` on the order |
| MarketCheckout.php:631–648 | Transaction on `$bp`, writes via `$main`; rollback may leave a half invoice that gets duplicated | Line 631's own promise | med (if connections differ) | Use `$bp` for all writes, or assert one connection |
| MarketCheckout.php:574,648,732 | Rental hold recorded as paid before capture | "Record without a charge" | med (MarketPay not shown) | For holds, record the payment at capture |
| MarketCheckout.php:386,549 | Deposit ≥ total falls back to charging the full total | Deposit intent | low | Throw when `balance >= total` |
| MarketCheckout.php:523,608,810 | Cancelled or expired direct orders keep approved invoices; payments on them are ignored | 0178 money note | low | `quotePaid` notes and tickets cancelled-but-paid orders |
| MarketCheckout.php:471,485 | Live advice to test with real money; cancelling a possibly paid earlier test | 0178 "guardReal() (no test moves real money)" | low | Reword; check the gateway before cancelling |
| MarketCheckout.php:32,725 | `'refunded'` never set here;