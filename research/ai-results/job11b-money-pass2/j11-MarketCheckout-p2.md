# Money-path review: `MarketCheckout.php` against decisions 0177 and 0178

*Draft for human review.*

## How the decisions apply

- **0177 (several destinations, invoices, tax, COGS + 15% deposit)** is written for client orders invoiced through QuickBooks (`QuickBooksPay::invoiceFor`). `MarketCheckout` never touches QuickBooks.
  - It holds one ship-to per order (lines 369 and 382).
  - It uses one tax rate, the business's own `tax_milli` (lines 171 and 372).
  - Its deposit comes from `KindCheckout`, not from COGS (lines 385–386).
  - **No 0177 defect found.** No order splits across destinations, so no destination can get the wrong share.
- **0178** applies in three places:
  - "`guardReal()`: no test moves real money" (Not changed).
  - The money rules: Cancel the record, no Undo.
  - The 2026-11-27 note on Marketplace refunds.
- **§1 (every refund waits for Jason)** is written for our orders refunded through QuickBooks. `rentalReturned` (line 733) refunds from the business's own gateway, and the 11-27 note treats such refunds as the business's own. I have **not** listed it as a breach. **Open question for a human:** does §1 reach Marketplace deposit refunds?
- **The 11-27 "Mark refunded" fix** is not in this file. Nothing here sets `status = 'refunded'` (it appears only in `STATUSES`, line 32). Whether it lives elsewhere is not shown.

## Defects

**1. A rental deposit refund can be paid twice** (lines 726, 732–734). **High.**
- The "already returned" check at line 726 reads the `$o` the caller passed in.
- The gateway capture or refund then runs (lines 732–733).
- Only afterwards does line 734 set `pay_mode = 'returned'`, and that update has no condition.
- So a double-click, two staff, or a retry after line 734 fails each pass line 726, and each sends a refund or capture.
- Rule: 0178 (no record without a charge, no charge without a record; a processed refund happens once). The method's own docblock says "Once." (line 720).
- **Fix:** claim the order before moving money:
  - `UPDATE … SET pay_mode='returned' WHERE id=? AND pay_mode <> 'returned'`;
  - continue only if `rowCount()===1`;
  - put the old `pay_mode` back if the gateway call throws.

**2. A buyer can cancel a card order that is still being paid, and the later payment is never recorded** (lines 518, 574, 788). **High.**
- `cancel()` cancels a `pending` card order.
- The gateway's pay window is still open: `CARD_HOLD_MIN` is 35 minutes and Stripe's session is 31 minutes (line 35). Whether the gateway session is closed is not shown.
- If the buyer pays in another tab, `confirmAndMark` returns false for `cancelled` (line 788), and `markPaid` excludes it (line 574).
- Result: the card is charged and the order says Cancelled.
- The same gap exists for the sandbox test orders that line 485 cancels (test money only).
- Rule: 0178 money note (a charge must have its record).
- **Fix (smallest):** in `cancel()`, refuse `pending` card orders; allow only `awaiting`. Alternatively, let `confirmAndMark`/`markPaid` accept `cancelled` card orders the gateway confirms as paid, and notify the business.

**3. A card order expired by the daily pass is never reconciled again** (lines 804, 810). **Medium.**
- Reconcile selects only `status = 'pending'` (line 804).
- If the gateway call fails at that tick (the error is swallowed at line 805), line 810 expires the order.
- Later ticks never ask the gateway about it again. A paid order whose webhook was missed stays Expired with no record.
- Rule: file header line 13 ("our question to it"); 0178 money note.
- **Fix:** line 804: `status IN ('pending','expired') AND method='card'`, limited to recent orders (for example, created within the last 3 days).

**4. A rental "hold" is recorded as paid in the business's books before anything is captured** (lines 648, 732). **Medium.**
- `markPaid` → `writeOrder` records `BizQuotes::pay` for `due($o)`, which is the full total, on confirmation.
- Line 732 shows that a hold is captured only at return ("the rest captured").
- Whether `MarketPay::confirm` treats an uncaptured authorisation as paid is not shown.
- If it does, and the authorisation lapses or is never captured, the invoice shows paid with no charge behind it.
- Rule: 0178 (record without a charge).
- **Fix:** for `pay_mode='hold'`, do not record the payment at line 648. Record the captured amount when line 732 succeeds.

**5. A refund or capture at return is not recorded as money** (lines 733–736). **Medium.**
- The refund is written only as free text in `note`.
- `status` stays `paid`.
- The business's invoice copy still shows the full payment. No amount is booked against `biz_quote_id`.
- Rule: 0178 11-27 note (a refund the business made is recorded on the order, with History).
- **Fix:** after the gateway call, record the refunded amount on the business's invoice (a negative or refund entry via `BizQuotes`) and in a `refunded_cents` column.

**6. When only the link to the copy fails, a direct payment never reaches the order** (lines 657–664, 807–810, 608). **Medium.**
- If the `biz_quote_id` update fails, no `COPY_HELD` note is written, so the hold is not protected (line 807) and the order expires.
- When the business marks the invoice paid, `quotePaid` finds nothing by `biz_quote_id` (line 608). Nothing calls it again later.
- Result: the buyer paid, and the Marketplace order says Expired with its stock released.
- A ticket is filed (line 662), but nothing holds the order meanwhile.
- **Fix:** in the catch at line 658, also call `self::note($pdo, $o['id'], self::COPY_HELD)` so line 807 keeps the hold.

**7. Test-purchase selectors match any order whose note starts with the test text** (lines 485, 502, 509 vs line 383). **Low.**
- A buyer's free-text note can begin with `TEST_NOTE`.
- Line 485 has no `env`, `buyer_id`, `method` or amount filter.
- If live and sandbox orders share the table (not shown), a sandbox test run could cancel a live pending order, which feeds into defect 2.
- Rule: 0178 Not changed (no test moves, or loses, real money).
- **Fix:** add `AND env = ? AND total_cents = 100 AND item_id-less lines` to line 485 (or use a dedicated `is_test` column), and the same at lines 502 and 509.

**8. A deposit refund is not capped at what was actually charged** (lines 729–733). **Low.**
- `$back` is capped at the deposit lines, not at `due($o)`.
- If a rental can also carry a `balance` (`pay_now_cents < total`; whether `KindCheckout` allows this is not shown), the refund could exceed the amount charged.
- **Fix:** `$back = min($back, self::due($o))` for card refunds.

**9. Square capture has no access token** (line 732 vs line 733). **Low.**
- Refund passes `self::access($pdo, $g)`; capture does not.
- The `MarketPay::capture` signature is not shown. If Square needs the token, the capture fails after the order is already treated as returned (see defect 1's ordering).
- **Fix:** pass `self::access($pdo, $g)` to `capture`, as the refund at line 733 does.

## Parts with nothing found

- Webhook dedupe: `firstTime` (lines 777–782).
- The conditional paid update in `markPaid` (lines 574–576).
- Totals math (lines 321–327).
- The sandbox gate on the test purchase (line 471).
- Settings and cart (lines 148–316).
- 0177 as a whole (see above).

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| MarketCheckout.php:726,732–734 | Deposit refund/capture not idempotent; can be sent twice | 0178 money note (charge ↔ record, once) | high | Conditional `UPDATE … WHERE pay_mode<>'returned'` before the gateway call; restore on throw |
| :518, :574, :788 | Pending card order cancelled while still payable; later charge unrecorded | 0178 money note | high | Refuse cancelling `pending` card orders, or let confirm/markPaid accept gateway-paid `cancelled` |
| :804, :810 | Expired card orders never reconciled again | 0178 money note; header line 13 | med | Reconcile recent `expired` card orders too |
| :648, :732 | Hold recorded as paid on the invoice before capture | 0178 (record without charge) | med | Skip line 648's pay for `hold`; record the captured amount at line 732 |
| :733–736 | Refund/capture kept only as note text; invoice and status unchanged | 0178 11-27 note | med | Book the refund on the invoice and in a `refunded_cents` column |
| :657–664, :807, :608 | Link failure leaves no COPY_HELD; the direct payment never reaches the order | 0178 money note | med | Add the `COPY_HELD` note in the link-failure catch |
| :485, :502, :509 (vs :383) | Test selectors match on buyer-writable note text, unscoped by env | 0178 Not changed (`guardReal`) | low | Scope by env, amount and test flag |
| :729–733 | Refund capped at the deposit, not at the amount charged | 0178 (refund ≤ paid) | low | `min($back, self::due($o))` |
| :732 | Capture called without the Square access token (signature not shown) | 0178 money note | low | Pass `self::access($pdo,$g)` |

**Open question for a human:** does 0178 §1 (Jason's OK on every refund) apply to Marketplace deposit refunds at line 733?