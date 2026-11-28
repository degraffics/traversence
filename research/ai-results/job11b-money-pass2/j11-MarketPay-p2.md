# Money-path review: `MarketPay.php` against 0177 and 0178

Draft for human review. Callers (checkout pages, webhook handlers, the order record writes) are not shown, so where a defect depends on what a caller does, I say so.

## How far 0177 and 0178 reach into this file

- **0177** covers client orders invoiced through QuickBooks: one invoice per ship-to, deposit = COGS × 1.15, release on the whole order. `MarketPay.php` is the Marketplace path. Money goes to one business's own Stripe or Square account (lines 2–6), and the deposit comes from `pay_now_cents` set elsewhere (lines 362–367).
  - Nothing here computes a deposit, a per-destination share or tax per ship-to, so I found no 0177 defect in this file.
  - Routing to the right payee depends on the caller passing the right gateway `$g`. `start()` (line 240) does not check that the order belongs to `$g`'s business. That check is not shown elsewhere either, so it needs confirming.
- **0178 §1** (every refund waits for Jason) is written for client orders (`admin/order.php`). `refund()` (lines 378–383) refunds on the business's own account with no approval step.
  - The 2026-11-27 note treats Marketplace refunds as the business's own.
  - Whether §1 applies to `refund()` is a scope question for a human, not a defect I can assert.
- **0178 "Not changed"** (no test moves real money): the guard here is:
  - `env()` switches to sandbox for a test account (lines 49–52);
  - `key()` refuses live keys in sandbox (lines 105–114);
  - Square uses the sandbox host (line 167);
  - mock is refused off the local machine (lines 44–48).
  
  I found no path in this file where a test account reaches a live key or host.
  - One unverifiable point: a CLI run (`localTest()` is true, line 60) with `MARKET_PAY_ENV=live` uses live keys unless `QuickBooksPay::testHere()` or `StagingGate::hold` stop it. Both are not shown.

## Defects

**1. Square retry can double-charge (high).**
- **Where:** line 290. The idempotency key is `tv-order-<id>-` plus `sha1($sourceToken)`, so it is per card token, not per order. Square's Web Payments form makes a new token on each tokenize.
- **How it happens:** if `/v2/payments` times out after Square charged (lines 146 and 150: 25 s timeout, "Try again"), the buyer retries with a fresh token. That gives a new key and a second charge, and the first charge was never recorded.
- **Breaks:** "a retry that double-charges"; 0178's principle that every charge has a record.
- **Smallest fix:**
  - Write a pending-charge marker on the order before calling Square.
  - When a marker exists, refuse a new charge until the earlier attempt is reconciled (look up that payment, or have staff check).
  - Alternatively, key on order id + `due()` and treat a replay as the existing payment.

**2. Square PENDING/APPROVED is reported as "Nothing was taken" (med).**
- **Where:** line 294. Any 200 response whose status isn't `COMPLETED` throws "The card wasn't charged … Nothing was taken." Hard declines come back as HTTP ≥400 (line 172), so this branch is mostly in-flight statuses.
- **Result:** money may move with no record, and the buyer is told it didn't.
- **Smallest fix:** on `PENDING`/`APPROVED`, return or keep the payment id as pending for `confirm()`. Only `FAILED`/`CANCELED` should say nothing was taken.

**3. Stripe charges the sum of the lines, but `confirm()` checks the order total (med).**
- **Where:** `stripeLines()` builds the session (lines 266–269 and 278–285), so the amount is Σ `unit_cents`×`qty` + ship + tax.
  - `confirm()` requires `amount_total === due($order)` (line 314).
  - Hold mode requires `amount === total_cents` (line 312).
- **Result:** if `total_cents` differs from that sum (a discount, credit or rounding; whether orders can carry these is not shown), the buyer pays the line sum and the order never reads paid. That is a charge without a record, possibly an overcharge.
- **Smallest fix:** in `start()`, before creating the session, check that the line sum equals `due($order)`. If it doesn't, refuse, or charge one line of `due()`.

**4. A hold order with a deposit is captured at once and never confirms (med).**
- **Where:** in `start()`, the deposit branch (line 261) is tested before the hold branch (line 265). A `pay_mode='hold'` order with `pay_now_cents` < total therefore gets an immediate deposit charge with no `capture_method=manual`.
- **Then:** `confirm()` for hold compares the PaymentIntent amount with `total_cents` (line 312). They never match, so a captured charge has no paid record.
- **Smallest fix:** test `$mode === 'hold'` before the deposit branch, or refuse a deposit on a hold. In `confirm()`, compare against `due($order)`.

**5. A second, equal partial refund is silently dropped (med).**
- **Where:** refund idempotency keys are `tv-refund-<ref>-<cents>` (Stripe, line 381) and `sha1($ref.$cents)` (Square, line 382).
- **Result:** two genuine refunds of the same amount on one payment (Stripe within 24 h; Square for its key lifetime) replay the first. No money moves, while the caller presumably records a second refund: a record without a refund.
- **Also:** nothing here checks the refund against what was captured minus what was already refunded. The gateways cap at the payment amount, but not against our own records.
- **Smallest fix:** key on the refund record's own id. Pass the remaining refundable amount in and refuse anything above it.

**6. Stripe session key reused with different parameters (low).**
- **Where:** line 271. The key `tv-order-<id>-<token8>` is fixed per order, but `expires_at` (line 246) changes on every call.
- **Result:** a second `start()` for the same order within 24 h (page reload, retry after the 31-minute expiry) gets a Stripe 400 for reused idempotency keys. This fails closed (no money moves), but the buyer can't pay.
- **Smallest fix:** reuse the stored open session if `provider_ref` is set and not expired, or add an attempt number to the key.

**7. Subscription confirm has no amount check (low).**
- **Where:** line 309 checks status and `payment_status` only, unlike lines 312, 314 and 318.
- **Also:** shipping and tax become recurring line items (lines 256–259), so they are charged every period. Whether that is intended is not covered by either decision.
- **Smallest fix:** compare `amount_total` with the expected first charge, or with 0 for a trial.

## Parts with nothing found

- Webhook signatures (lines 399–417).
- Token sealing (lines 118–137).
- OAuth (lines 182–229).
- `accountStatus` (lines 336–360).
- `capture()` (line 374): the key is stable per PaymentIntent, and the gateway caps the amount.
- `due()` (lines 363–367).

## Summary

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| MarketPay.php:290 (with 150) | Square idempotency is per token, so a retry after a timeout double-charges, and the first charge is unrecorded | no double charge; 0178 (every charge recorded) | high | Pending-charge marker before the call; block a new charge until reconciled, or key on order + amount |
| MarketPay.php:294 | Non-COMPLETED (PENDING/APPROVED) told "Nothing was taken"; money may move with no record | charge without record | med | Treat PENDING/APPROVED as pending and keep the id; only FAILED/CANCELED say nothing taken |
| MarketPay.php:266–269, 281–283 vs 312, 314 | Session charges the line sum; confirm checks total/`due()`; a mismatch leaves a charge unrecorded and possibly an overcharge | charge without record | med | Check line sum == `due()` before creating the session, or charge one line of `due()` |
| MarketPay.php:261 vs 265, 312 | Hold + deposit is captured immediately; confirm wants `total_cents`, so it never reads paid | charge without record | med | Branch on hold before deposit; confirm against `due()` |
| MarketPay.php:381, 382 | Refund key = ref + cents, so a second equal refund replays as a no-op while being recorded; no check against the refundable remainder | record without refund; refund ≤ paid | med | Key on the refund record id; refuse above captured − refunded |
| MarketPay.php:271 with 246 | Same idempotency key with a changing `expires_at`; second `start()` within 24 h fails | (availability; fails closed) | low | Reuse the open session or add an attempt number to the key |
| MarketPay.php:309, 256–259 | Subscription confirm has no amount check; ship/tax recur each period | charge vs record mismatch | low | Check `amount_total` against the expected first charge |
| MarketPay.php:378–383 | Refund has no approval step | 0178 §1 (scope unclear for Marketplace) | — (question) | Human to confirm whether §1 covers business-account refunds |
| MarketPay.php:60, 49–53 | A CLI run with `env=live` uses live keys unless `testHere`/`StagingGate` (not shown) stop it | 0178 "Not changed" (`guardReal`) | — (verify) | Confirm the test harness forces sandbox or mock |
| MarketPay.php (all) | No per-destination split, deposit or tax in this file | 0177 §1–§4 | none found | — |