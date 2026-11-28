# Money-path review: `MarketPay.php` against decisions 0177 and 0178

*Draft for human review.*

## Scope

Both decisions mostly govern Traversence's own client orders, which go through `QuickBooksPay`. `MarketPay` handles Marketplace orders paid to a business's own Stripe or Square account. Only two parts of the decisions reach this file:

- 0178 "Not changed": no test moves real money.
- 0178's 2026-11-27 note on Marketplace refunds.

Most defects below therefore cite the review criteria you listed (double charge, charge without a record, and so on) rather than a decision section. Callers are not shown: `app/business/checkout.php`, the webhook handler, and whatever sets `provider_ref`. Findings that depend on them say so.

## Defects

**1. Square retry can charge twice, and a timed-out charge has no record (high)**
- **Where:** `MarketPay.php:290`, `:150`
- **What happens:**
  - The idempotency key is `'tv-order-' . id . '-' . substr(sha1($sourceToken), 0, 12)`. Square's form makes a new token on each card entry, so a second submit gets a new key and is a new charge.
  - On a network timeout, `http()` throws "The payment service didn't answer … Try again." (`:150`), even though Square may already have charged the card. The buyer is invited to re-enter the card, which produces a new token, a new key and a second charge.
  - The first charge never gets a payment id back, so it has no record.
- **Rule:** criteria "a retry that double-charges" and "a charge without a record".
- **Smallest fix:** key on the order, not the card: `'tv-order-' . $order['id'] . '-' . substr((string) $order['token'], 0, 8) . '-' . self::due($order)`. Square refuses a reused key with different parameters, so a second card is rejected rather than charged. **Verify that behaviour in the sandbox.**

**2. A rental hold with a deposit is charged outright and never confirmed (high)**
- **Where:** `MarketPay.php:261-265` and `:310-312`
- **What happens:**
  - When `pay_mode` is `'hold'` and `pay_now_cents < total_cents`, the `elseif ($due < total)` deposit branch runs first. Manual capture (`:265`) is never set, so the deposit is charged immediately.
  - `confirm()` then treats the order as a hold and requires the PaymentIntent amount to equal `total_cents` (`:312`). A deposit-sized charge never matches.
  - Result: the card is charged, the order never reads as paid, and the money sits on the business's account with no record here.
- **Rule:** criterion "a charge without a record".
- **Smallest fix:** change `:261` to `} elseif ($mode !== 'hold' && $due < (int) $order['total_cents']) {`. Alternatively, refuse `pay_now_cents` on hold orders.

**3. Full payment: the amount charged and the amount confirmed are computed differently (med)**
- **Where:** `MarketPay.php:266-269`, `:278-285` vs `:314`
- **What happens:**
  - The full-payment session charges the sum of `stripeLines()`: each line's `unit_cents × qty`, plus `ship_cents`, plus `tax_cents`.
  - `confirm()` requires `amount_total === due()`, which here is `total_cents`.
  - If `total_cents` ever differs from that sum (a discount, a fee, rounding; how `total_cents` is built is not shown), the buyer is charged an amount the order never confirms. That is a charge without a record, and possibly a different amount from the total shown.
- **Rule:** criterion "a charge without a record".
- **Smallest fix:** before creating the session, sum `stripeLines()` and throw if it is not equal to `self::due($order)`. The same check applies to the hold branch.

**4. `confirm()` doesn't check that the gateway payment belongs to this order (med)**
- **Where:** `MarketPay.php:307`, `:314`, `:316-318`
- **What happens:**
  - Stripe: the session is read by `provider_ref`, and only status and amount are compared. The `client_reference_id` / `metadata[tv_order]` set at `:244` is never checked.
  - Square: `reference_id` (`'TV-' . id`, `:291`) and `location_id` are never checked.
  - A paid payment from another order at the same business, with the same amount, would confirm this order.
  - Whether `provider_ref` can come from anything the buyer controls (for example the return URL) is not shown.
- **Rule:** criteria "a record without a charge" and "misroute".
- **Smallest fix:**
  - Stripe: also require `(string) ($s['client_reference_id'] ?? '') === (string) $order['id']`.
  - Square: require `($p['reference_id'] ?? '') === 'TV-' . $order['id']` and `($p['location_id'] ?? '') === $g['location_id']`.

**5. Refund idempotency is keyed on payment + amount; no ceiling here (med)**
- **Where:** `MarketPay.php:381-382`
- **What happens:**
  - Keys are `'tv-refund-' . $ref . '-' . $cents` (Stripe) and `sha1($ref . $cents)` (Square).
  - Two legitimate partial refunds of the same amount on the same payment collapse into one at the gateway, while the caller may record two. That is a record without the money moving.
  - Stripe idempotency keys expire after 24 hours, so a retry made later refunds again.
  - Nothing in this file caps the refund at what was paid or captured, less earlier refunds. Whether the caller does is not shown.
- **Rule:** criteria "a refund that exceeds what was paid" and "a record without a charge".
- **Smallest fix:** add a refund-record id parameter and key on it (`'tv-refund-' . $refundId`). The ceiling check belongs to the caller; confirm it exists.
- **Open question:** 0178 §1 (every refund waits for Jason's OK) is written for client orders via QuickBooks. It doesn't say whether it covers a business refunding its own Marketplace money. A human should decide; I'm not counting it as a defect.

**6. Square refunds are always labelled "Deposit returned" (low)**
- **Where:** `MarketPay.php:382`
- **What happens:** every Square refund, whatever it is for, carries `reason => 'Deposit returned'`. The business's own records say the wrong thing.
- **Rule:** record accuracy.
- **Smallest fix:** add a `$reason` parameter.

**7. A non-COMPLETED Square status is reported as "Nothing was taken" (low/med)**
- **Where:** `MarketPay.php:294`
- **What happens:** any status other than `COMPLETED` throws "The card wasn't charged … Nothing was taken." For a status such as `APPROVED` or `PENDING`, money may still move, and no record is kept.
- **Rule:** criterion "a charge without a record".
- **Smallest fix:** say "nothing was taken" only for `FAILED` / `CANCELED`. For any other status, return the payment id and keep the order pending.

**8. A second checkout start within 24 hours fails (low; blocks a sale, no money lost)**
- **Where:** `MarketPay.php:271` with `:246`
- **What happens:** the idempotency key is fixed per order (`'tv-order-' . id . '-' . token[0..8]`), but `expires_at` is `time() + 31*60` and changes on every call. A second start (after an abandoned or expired session) reuses the key with different parameters, and Stripe rejects it. The buyer can't pay again until the key expires.
- **Rule:** criterion "retry".
- **Smallest fix:** add an attempt counter, stored on the order, to the key.

**9. Subscription confirmation checks no amount (low)**
- **Where:** `MarketPay.php:309`
- **What happens:** status alone confirms a subscription. Unlike `:312`, `:314` and `:318`, there is no comparison with what the order expected.
- **Rule:** criterion "a record without a matching charge".
- **Smallest fix:** compare `amount_total` with the first period's expected amount (or 0 for a trial).

**10. Test-mode webhooks are checked against the live secret (low; test money only)**
- **Where:** `MarketPay.php:401` with `:49-51`, `:99-101`
- **What happens:** `key()` picks the test secret only when the current request is a test account (`QuickBooksPay::testHere()`, not shown). A webhook request has no signed-in user, so it presumably reads `STRIPE_WEBHOOK_SECRET` (live). Test-mode events from test accounts on the live site then fail verification, and those test payments rest on `confirm()` alone. No real money moves.
- **Smallest fix:** in `stripeSigned`, when `MARKET_PAY_ENV` is `live`, also try `STRIPE_TEST_WEBHOOK_SECRET`. Apply the same to Square.

**11. Command-line runs count as a local test, so mock mode is accepted there (low)**
- **Where:** `MarketPay.php:60`, `:372`, `:380`
- **What happens:**
  - `localTest()` returns true for any command-line run, so `MARKET_PAY_ENV=mock` is honoured in server cron or command-line jobs even though web requests refuse it.
  - In mock, `capture()` and `refund()` return silently. A scheduled job would then record a capture or refund that never happened.
  - This only matters if `.env` on the server says `mock`.
- **Smallest fix:** on