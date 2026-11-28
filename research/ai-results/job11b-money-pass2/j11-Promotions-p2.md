# Money-path review: `traverse-3way-sync/sync-app/lib/Promotions.php` against decisions 0177 and 0178

*Draft for human review.*

## Scope

`Promotions.php` never charges, refunds, invoices or calls a payment gateway. It:
- prices a line with a discount (`linePrice`, :213–222),
- records a code's use and the amount taken off (`redeem`, :225–231),
- removes that record (`release`, :234–241).

So most of 0177 and 0178 (invoices per destination, tax, deposits, vendor release, refund approval) has nothing to act on in this file. The defects below are where the discount and its record can lose or double money.

Callers are **not shown**. That includes the checkout and the order flow that call `check`, `linePrice`, `redeem` and `release`, so I cannot see:
- whether `redeem` runs before or after a charge succeeds,
- whether `release` is ever called on a paid order.

## Findings

**1. A retry or a race over-redeems a code: high**
- **Where:** :188, :197 (in `check`) and :227–229 (in `redeem`).
- **Problem:** `check` reads `uses` against `max_uses` and looks for an earlier use. `redeem` then inserts the use and increments `uses` with no condition, no uniqueness and no transaction. In the shown code, any of these grants the discount more than the limit allows:
  - two checkouts at once with the same code,
  - a retried checkout that calls `redeem` again for the same `order_id`.
- **Effect:** An assigned code is fixed at `max_uses = 1` (:88), so a race lets it be used twice. A retry also writes a second `off_cents` row for the same order, which doubles the recorded discount and pushes `uses` past `max_uses`.
- **Rule broken:** The file's own rules (:6–7, "a limit on how many times it's used… An account uses a code once"). This is the "retry that double-…" class from the brief. It is not a 0177/0178 section as such.
- **Smallest fix:**
  - Add a unique key on `promotion_uses (promo_id, order_id)` and use `INSERT IGNORE`, skipping the counter when no row was inserted.
  - Make the counter conditional: `UPDATE promotions SET uses = uses + 1 WHERE id = ? AND (max_uses IS NULL OR uses < max_uses)`.
  - If that update's `rowCount()` is 0, throw "used up" and roll back.
  - Wrap both statements in one transaction.

**2. Unknown cost lets a product line be discounted to zero: high**
- **Where:** :44 and :217.
- **Problem:** For `creative`, `additional` and `print`, `maxPercent` returns 100 (:44). When `$costCents` is 0 (unknown), :217 sets `$max = floor($unit * 100 / 100) = $unit`. A 100% code (allowed for these targets, :70) therefore makes the line free with `capped = false`.
- **Effect:** The header says a product line's discount "is capped at the floor" (:9–12). With unknown cost there is no cap at all. A sale can go below cost, and an order total can fall below its COGS. This interacts with 0177 §3: the deposit is "never above that invoice's total", so a zeroed invoice yields a zero deposit while the vendor still costs money.
- **Rule broken:** 0108's profit floor (header :9–12), with knock-on to 0177 §3. Note that 0177 §3's 65% fallback for unchecked COGS covers only the deposit, not the discount.
- **Smallest fix:** In `linePrice`, when `$costCents <= 0` and `$type` is not `go_code`, take nothing off (`$max = 0`, report as capped) until a cost is set. Alternatively apply a configured default cost percentage, as plans and GO Codes already do.

**3. A guest with no email can reuse a general code without limit: med**
- **Where:** :203–205.
- **Problem:** For a guest, `owner_id` is 0, so `owner_id <> 0` excludes the first test. With an empty email the second test (`? <> ''`) is also false. `usedBy` therefore always returns false, and only `max_uses` (often null, :81) stops repeat use.
- **Effect:** "An account uses a code once" (:7) is not enforced for guests.
- **Rule broken:** File rule :7 (0108). It is not 0177/0178.
- **Smallest fix:** In `check`, refuse the code for a guest whose email is empty ("Enter your email to use a code"). Alternatively require the email in `who` before calling `usedBy`.

**4. `release` deletes a discount record with no paid-state guard and no history: med (to confirm)**
- **Where:** :233–241.
- **Problem:** The docstring limits it to "an order that was cancelled before it was paid", but nothing in the function checks payment status. It hard-deletes the `promotion_uses` row, including `off_cents`, with no History entry.
- **Effect:** If any caller (not shown) reaches it for a paid or partly paid order, the record of the discount on a real payment vanishes. Totals and reports would then show a paid order with no explanation for its reduced price.
- **Rule broken:** 0178 note of 2026-11-23: money never has Undo, and only a recorded, unprocessed entry may be "Cancel the record", with History kept. The docstring's word is "Undoes". Whether a discount-use row counts as a money record under that note is for the reviewer to decide.
- **Smallest fix:** Pass the order's paid state, or query it, and refuse when anything has been paid. Log the removal (order, promo, `off_cents`) to the order's History instead of deleting silently.

**5. A failed payment may leave a discount record without a charge: not determinable**
- **Where:** :225.
- **Problem:** `redeem` takes `?int $orderId` and writes immediately. If a caller redeems before the charge and the charge then fails, a use with `off_cents` stays recorded against an unpaid order until `release` is called. The calling order is **not shown**.
- **Smallest fix (if confirmed):** Call `redeem` only after the payment succeeds, inside the same transaction that records the payment.

## Parts with nothing found

- **0177 §1 (several destinations):** `linePrice` is per line, so each line's discount can land on its own destination's invoice. Nothing here splits an order-level amount across invoices. `redeem` records `off_cents` per order, not per invoice. That is not a defect today: release B is not built.
- **0177 §2 (tax):** nothing touches `TaxCodeRef` or the Ship to.
- **0177 §4 (vendor release) and 0178 §1–§6 (refunds, cancel window, credit, terms, cards, delivery with a balance):** no code in this file.
- **Tests moving real money:** no payment calls here, so `guardReal()` is not involved. `leadNote` (:272) sends real email via `Mailer::send` whenever its conditions are met, which is not money.

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| Promotions.php:188, :197, :227–229 | Check-then-redeem not atomic; a retry or race inserts a duplicate use and over-counts past `max_uses` | File :6–7 (0108); retry/double class | high | Unique `(promo_id, order_id)`; conditional `uses < max_uses` update with `rowCount` check; one transaction |
| Promotions.php:44, :217 | Unknown cost on creative/additional/print allows up to 100% off, so a line goes below cost | 0108 floor (:9–12); knock-on 0177 §3 deposit cap | high | When cost is unknown on a product line, take nothing off (or apply a default cost %) |
| Promotions.php:203–205 | Guest with empty email bypasses "uses once" | File :7 (0108) | med | Require an email for guests before `usedBy` |
| Promotions.php:234–241 | Hard-deletes discount record with no paid check and no History; "Undo" on money | 0178 note 2026-11-23 (no Undo; Cancel the record, History kept) | med (confirm) | Refuse if any payment exists; log to History |
| Promotions.php:225 | Use may be recorded before a charge succeeds (caller not shown) | 0178 (record without a charge) | not determinable | Redeem only after payment success, in the same transaction |