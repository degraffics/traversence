# Money-path review: `traverse-3way-sync/sync-app/lib/Promotions.php` against 0177 and 0178

*Draft for human review.*

## Scope

`Promotions.php` never charges, refunds, credits or posts to QuickBooks. Its money effects are indirect:

- **Pricing:** `linePrice` sets what a line costs the client.
- **Records:** `redeem` and `release` write the discount record, `promotion_uses.off_cents`, and count uses.
- **Gating:** `check` and `usedBy` limit who gets a discount.

The callers that charge, and the orders and payments tables, are not shown. Where a defect depends on a caller, I say so.

## Defects

**1. A product line with unknown cost can be discounted to $0.** High.
- **Where:** 217, 44, 70–74.
- **What happens:** For creative, additional or print lines, `maxPercent` returns 100 (44). When `$costCents` is 0 ("unknown", per the docblock at 211), the cap becomes `floor($unit * 100 / 100) = $unit` (217). So a promotion of up to 100% applies in full. `create` permits that: 70 accepts 1–100, and 73–74 only check plans and GO Codes.
- **Result:** The line is sold at or near $0 while we still pay the vendor.
- **Rules broken:**
  - The file's own header (9–12, "no discount takes a sale below 65% profit… on a product line it's capped at the floor").
  - 0177 §3: the 65% fallback deposit for an item whose COGS isn't checked would then be 65% of nothing.
- **Smallest fix:** In `linePrice`, when `$costCents <= 0` and `maxPercent($type) === 100`, set `$max = 0`. That means no discount until the cost is known.

**2. A retry of `redeem` double-records the discount.** Medium.
- **Where:** 227–229.
- **What happens:** The insert into `promotion_uses` and the `uses + 1` have no guard against an existing row for the same `promo_id` and `order_id`. A retried checkout or submit writes a second use and a second `off_cents`, and burns a second use of the code.
- **Rules broken:** 0178's "money note": one record per money event, a correction made by Cancel the record, not by a duplicate.
- **Smallest fix:** Add a unique key on `(promo_id, order_id)`, and use `INSERT IGNORE`. Only increment `uses` when `rowCount() === 1`.

**3. `max_uses` can be exceeded by concurrent use (check-then-act).** Medium.
- **Where:** 188 and 197 read; 227–229 write without condition.
- **What happens:** Two checkouts can both pass `check` before either `redeem` runs. That includes an assigned code, whose `max_uses` is forced to 1 at 88. Both then get the discount.
- **Note:** Whether callers wrap check and redeem in a locking transaction is not shown.
- **Rules broken:** The promotion's own limit (header 6–7).
- **Smallest fix:** At 229, use `UPDATE promotions SET uses = uses + 1 WHERE id = ? AND (max_uses IS NULL OR uses < max_uses)`. If `rowCount() === 0`, throw "used up" before charging.

**4. `release` deletes the discount record without confirming the order is unpaid.** Medium.
- **Where:** 233–240.
- **What happens:** The docblock limits it to "an order that was cancelled before it was paid". The code deletes every use for the `order_id` unconditionally.
- **Result if called on a paid or processed order:**
  - The record of the discount given disappears.
  - `usedBy` (204) no longer finds it, so the same account can take the discount again.
- **Note:** Whether callers check paid state is not shown.
- **Rules broken:**
  - 0178 money note: "Cancel the record" only for "recorded here and not processed".
  - 0178 also says money never has Undo; the docblock at 233 calls this "Undoes".
- **Smallest fix:** Have `release` refuse, and throw, when the order has any processed payment, checked in the same transaction. The payments table is not shown. At minimum, rename the docblock to "Cancels the record" and require callers to pass proof of the unpaid state.

**5. A guest with no email can reuse a general code without limit.** Low.
- **Where:** 203–205.
- **What happens:** `owner_id <> 0` and `? <> ''` both fail for a guest without an email, so `usedBy` always returns false. When `max_uses` is NULL (81), the discount repeats without limit.
- **Rules broken:** Header line 7, "An account uses a code once". This applies only if guests are meant to be covered, which is not stated.
- **Smallest fix:** In `check`, require an email (or a signed-in id) before accepting a code from a guest.

**6. The floor's cost may omit our shipping cost.** Low, depending on the caller.
- **Where:** 211, 217.
- **What happens:** `$costCents` is "what one costs us". 0177 (Confirmed) says `ship_cost_cents` is COGS. Whether callers pass `cost_cents + ship_cost_cents` is not shown. If they pass only `cost_cents`, the floor is computed on too low a cost.
- **Rules broken:** 0177 §1 and §3 (COGS includes shipping).
- **Smallest fix:** State in the docblock that the cost includes shipping, and check the callers.

## Parts with nothing found

- **Charge without record / record without charge:** No charge happens in this file. Whether `redeem` runs only after a successful charge is not shown.
- **Refund exceeding payment, credit, terms, keyed cards (0178 §1–§5):** No such code here.
- **Test moving real money (`guardReal()`):** No payment or QuickBooks calls in this file.
- **Destination share (0177 §1, §3, §4):**
  - The discount is applied per line unit price (213–221), so it follows the item onto its destination's invoice.
  - `off_cents` is order-level only (227), so a per-invoice split isn't recorded. That is acceptable while several destinations are not built (0177 Status), but worth adding with M5.

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| Promotions.php:217 (with 44, 70–74) | Unknown-cost product line can be discounted up to 100% | 0108 floor (header 9–12); 0177 §3 | high | `$max = 0` when `$costCents <= 0` and `maxPercent($type) === 100` |
| Promotions.php:227–229 | Retried `redeem` inserts a second use and `off_cents` | 0178 money note (one record; Cancel the record) | med | Unique `(promo_id, order_id)` + `INSERT IGNORE`; increment only on insert |
| Promotions.php:188, 197 → 229 | Check-then-act lets `max_uses` (incl. assigned = 1) be exceeded | Promotion limit (header 6–7) | med | Conditional `UPDATE … WHERE uses < max_uses`, check `rowCount` |
| Promotions.php:234–240 | `release` deletes the discount record without checking the order is unpaid; code becomes reusable | 0178 money note (Cancel the record only if unprocessed; no Undo) | med | Refuse when the order has a processed payment; rename to "Cancels the record" |
| Promotions.php:203–205 | Guest with no email never "used" the code | Header 7 ("uses a code once") | low | Require an email or id for guests in `check` |
| Promotions.php:211, 217 | Floor cost may exclude `ship_cost_cents` (callers not shown) | 0177 Confirmed, §1, §3 | low | Document and verify the cost passed includes shipping |