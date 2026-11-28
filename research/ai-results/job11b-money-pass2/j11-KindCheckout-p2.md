# Money-path review: `KindCheckout.php` against decisions 0177 and 0178 (draft for human review)

**Scope.** The file is the Marketplace checkout by kind (decisions/0141). It works out lines, extras, the deposit balance, holds and fulfilment, but never calls a payment gateway or QuickBooks. The charge, the order write and the refunds happen in `MarketCheckout` and pages that are not shown. So for most money paths I can only show what this file hands to them.

## Against 0177 (one order, several destinations)

0177 governs our client orders and their QuickBooks invoices: Ship to, tax per invoice, and a deposit of COGS + 15%. This file has none of that: no invoice, no Ship to, no COGS.

- The made-to-order deposit (`KindCheckout.php:141-142`) is the business's own `deposit_pct`, not 0177 §3's COGS × 1.15. That is not a defect as far as can be shown, because 0177 §3 is about our orders.
- **No defects against 0177 in this file.**

## Against 0178 (order money policy)

### 1. A refunded ticket order still checks in
- **Where:** `KindCheckout.php:273`
- **Problem:** `checkIn` refuses only when the order isn't `'paid'`. It never looks at a void on the ticket itself. 0178's CFO note (2026-11-27) names this exact gap: a refund made in the business's own dashboard leaves the order paid, and its tickets still get in.
- **What the fix needs:** 0178's fix says **Mark refunded** voids the tickets "so check-in refuses them". Nothing on lines 267-277 reads a void, and no void column is shown in `market_tickets`. So even after Mark refunded is built, check-in would not refuse a voided ticket unless that status change also takes the order out of `'paid'`, which is not shown.
- **Effect:** goods are given out against money that was returned.
- **Rule:** 0178, note of 2026-11-27.
- **Severity:** high.
- **Smallest fix:** after line 274 add `if (!empty($t['voided_at'])) return [false, 'This ticket was refunded.'];`, with the column added. Or confirm Mark refunded sets a status other than `paid`.

### 2. A refunded digital order may keep downloading
- **Where:** `KindCheckout.php:289`
- **Problem:** the same pattern as defect 1. Downloads check only `status !== 'paid'`. If the refund is recorded without changing the status, downloads continue.
- **Rule:** 0178, note of 2026-11-27, by the same reasoning.
- **Severity:** med.
- **Smallest fix:** the same void or status check as defect 1. Or confirm Mark refunded changes the status (not shown).

### 3. A rental "hold" can expire before the return date
- **Where:** `KindCheckout.php:171`
- **Problem:** the hold is allowed when the rental is 6 days or fewer (`$n > 6` turns it off). But a card authorization runs from checkout, not from pick-up. A 3-day rental booked 30 days ahead passes the check, and its authorization lapses (the comment says the card holds it "within the week") long before the return.
- **Effect:** the deposit can't be captured.
  - Line 8 says rentals on hold are "authorized, captured on return".
  - If the whole payment is authorized that way (not shown), the rental charge itself is lost: **a record without a charge**.
- **Rule:** not tied to a 0178 section. It is a money-loss path the request asks about.
- **Severity:** high.
- **Smallest fix:** base the check on the time from now to the return:
  `if ($hold && ($provider !== 'stripe' || strtotime($to) - time() > 6 * 86400)) $hold = false;`

### 4. The comments disagree on what is held
- **Where:** `KindCheckout.php:8` against `KindCheckout.php:172`
- **Problem:** line 8 suggests the rental payment is authorized and captured on return. Line 172 says only the deposit is "held on your card, released on return". Whether the deposit line is charged together with the rental, and how it is "refunded on return" when not held, is not shown.
- **Rule:** 0178 §1 governs refunds on our orders; whether it applies to Marketplace deposits is not shown.
- **Severity:** med, as a question.
- **Smallest fix:** document in the caller which amount is authorized and which is captured, and where the deposit refund is recorded.

### 5. Seats and dates can be oversold
- **Where:** `KindCheckout.php:62`, `KindCheckout.php:90`, `KindCheckout.php:156` (checks), with the order written elsewhere
- **Problem:** availability is read and then the order is written elsewhere. No lock is shown, so two buyers can each pay for the last seat or the same dates.
- **Effect:** one is charged for something that isn't there, and a refund follows.
- **Severity:** med.
- **Smallest fix:** in the caller, re-check `freeOn` / `seatsLeft` inside a transaction with `SELECT … FOR UPDATE`, as the order is written (not shown).

### 6. The availability match is fragile
- **Where:** `KindCheckout.php:72`
- **Problem:** `LIKE '%"item_id":N,%'` only matches if the JSON has no spaces, stores an integer, and has a key after `item_id`. Otherwise `taken()` undercounts and the item is oversold.
- **Severity:** low to med, depending on how the order lines are encoded (not shown).
- **Smallest fix:** drop the `LIKE` filter and rely on the PHP check at line 75. Or store `item_id` in a joinable column.

### 7. A payment after the hold expires can double-book
- **Where:** `KindCheckout.php:71`
- **Problem:** pending orders only hold their dates while `hold_until` is in the future. If a payment completes after its hold expired and the dates were resold, both orders are paid for the same dates. What happens on a late payment is not shown.
- **Severity:** med, as a question.
- **Smallest fix:** in the payment-completion path, re-check `freeOn` with `$exceptOrder` and refuse or flag the order (not shown).

### 8. Ticket codes can be issued twice
- **Where:** `KindCheckout.php:224-226`
- **Problem:** `fulfil` counts the tickets, then inserts. Two calls at once (for example a webhook and the return page) both count 0 and each issue `qty` codes.
- **Effect:** double the seats for one payment.
- **Severity:** med.
- **Smallest fix:** a unique key on `market_tickets (order_id, item_id, seat)` and `INSERT IGNORE`.

### 9. Download links can be doubled
- **Where:** `KindCheckout.php:231-236`
- **Problem:** the same race as defect 8. It can create two tokens, each with full `max_uses`.
- **Severity:** low.
- **Smallest fix:** a unique key on `market_downloads (order_id, item_id)` and `INSERT IGNORE`.

### 10. `fulfil` doesn't check the order is paid
- **Where:** `KindCheckout.php:216-220`
- **Problem:** the docblock says "Once paid", but the function never checks it. It relies entirely on the caller (not shown).
- **Severity:** low.
- **Smallest fix:** `if (($o['status'] ?? '') !== 'paid') return [];`

### 11. Any missing item is treated as the test purchase
- **Where:** `KindCheckout.php:124`
- **Problem:** any line whose item can't be found becomes a plain `physical` line "the setup's test purchase", at whatever `unit_cents` the line carries.
  - A deleted item passes through priced as given.
  - Whether the test purchase moves real money through the business's gateway is not shown. Nothing here plays the part `guardReal()` plays for QuickBooksPay (0178 "Not changed").
- **Severity:** low to med.
- **Smallest fix:** make the test purchase explicit, for example `!empty($l['setup_test'])`, and otherwise throw "This item is no longer for sale".

### Nothing found against these parts of 0178
- **§2 (cancel window), §3 (credit), §4 (terms):** nothing in this file touches them.
- **§5 (no keyed cards):** no card number is taken here.
- **The "money never has Undo" note:** there is no Undo here.

## Other parts
- **Deposit balance (`:142`, `:192`):** consistent per line.
- **Lodging tax (`:163-164`):** computed on the same `n × unit` as the line, since line 157 sets `qty = $n`.
- **`'pay_now'`:** the docblock promises it, but it is never set; only `'balance'` is. How the caller turns that into the amount charged is not shown.

## Summary

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| KindCheckout.php:273 | Refunded ticket order still checks in; no ticket void is read | 0178 note 2026-11-27 | high | Refuse voided tickets (`voided_at`), or confirm Mark refunded changes status |
| KindCheckout.php:289 | Refunded digital order still downloads if status stays paid | 0178 note 2026-11-27 (same reasoning) | med | Same void/status check |
| KindCheckout.php:171 | Hold allowed by rental length, not time to return; authorization lapses and the deposit (or whole charge) is lost | money-loss path (no § ) | high | Test `strtotime($to) - time() > 6*86400` |
| KindCheckout.php:8 / :172 | Unclear whether the deposit or the whole payment is held; deposit refund path not shown | 0178 §1 (if it applies) | med | Document authorize vs capture in the caller |
| KindCheckout.php:62, :90, :156 | Check then write elsewhere; oversold seats or dates get charged | money-loss path | med | Re-check under lock when writing the order |
| KindCheckout.php:72 | `LIKE` on JSON can miss lines, so `taken()` undercounts | money-loss path | low–med | Filter in PHP only, or use an indexed column |
| KindCheckout.php:71 | Payment after hold expiry can double-book (late-payment handling not shown) | money-loss path | med | Re-check availability on payment completion |
| KindCheckout.php:224-226 | Concurrent `fulfil` issues tickets twice | doubling | med | Unique (order_id, item_id, seat) and `INSERT IGNORE` |
| KindCheckout.php:231-236 | Concurrent `fulfil` creates two download tokens | doubling | low | Unique (order_id, item_id) |
| KindCheckout.php:216 | `fulfil` doesn't check paid status | record without charge | low | Return early unless `status === 'paid'` |
| KindCheckout.php:124 | Any missing item becomes an unpriced-check "test purchase"; real-money guard not shown | 0178 "Not changed" (`guardReal`, by analogy) | low–med | Explicit test flag; otherwise refuse |
| — | Nothing found against 0177 | 0177 §1–§4 | — | — |