# Money-path review: `ClientOrders.php` against decisions 0177 and 0178

*Draft for human review.* Only `ClientOrders.php` and the two decisions are shown. `Orders`, `QuickBooksPay`, `Shipments`, `Costing`, `WorkOrders` and `admin/order.php` are **not shown**, so wherever a finding depends on their behaviour, I say so.

## Defects

### 1. Paying on QuickBooks' page can record money that never came in (HIGH)

`paidInQuickBooks` (2751–2759) calls `fromQuickBooks`. When that returns `''`, it records the whole remaining balance as a `charge` (2758). It never asks QuickBooks how much was actually paid. `fromQuickBooks` returns `''` in three cases:

- **The card lock times out (1775).** `pay()` is mid-charge. `paidInQuickBooks` then records the full balance with no lock. When `pay()` finishes, it records the same money again (1744), so the ledger shows more than was paid.
- **QuickBooks shows nothing paid (1784).**
- **QuickBooks shows a part payment that is already recorded (1788).** For example, a deposit is in and QuickBooks shows nothing new. The rest of the balance is then marked paid anyway.

Each case is a record without a charge. Through `received()` it then marks the order paid, issues GO Codes and lifts `shipHold`.

- **Breaks:** 0177 §4 (paid in full before release; "single invoices are tied to the whole order status").
- **Fix:** delete the fallback at 1754–1758. A `''` from `fromQuickBooks` should mean "nothing new". If a fallback is kept, require `invoicePaid()['paid'] >= total` under the `tv-pay-` lock.

### 2. A test copy can be invoiced and charged in Production (HIGH)

- A test copy is created as a sandbox order (194–196). The comment says "no real money, ever".
- At approval, `cantInvoice` (1497–1507) only blocks the opposite case: a real order while Payments is in Sandbox (1503). It does not block a test order while Payments is in Production.
- `invoiceFor` (1470) then runs in the current global mode, not under `withEnv($o['qb_env'])`. Compare `recordPayment` (2202) and `readQuickBooks` (921), which do use the order's own mode.
- 1481 then overwrites `qb_env` with `QuickBooksPay::env()`. The sandbox order becomes a production one, so `Orders::sameEnv` (1731) no longer stands between it and `chargeCard`.

Whether `guardReal()` inside `invoiceFor`/`chargeCard` stops this is **not shown**.

- **Breaks:** 0178 "Not changed" (`guardReal()`: no test moves real money).
- **Fix:**
  - In `cantInvoice`, add a refusal when `Orders::isTest($o) && $env === 'production'`.
  - At 1470, wrap the call in `QuickBooksPay::withEnv((string) ($o['qb_env'] ?: QuickBooksPay::env()), …)`.
  - At 1481, write that same mode rather than `env()`.

### 3. A payment recorded here can be read back from the linked invoice and counted twice (MED)

- `recordPayment` writes the ledger row (2194) under key `out-…`, posts it to the linked invoice (2202) and stores the QuickBooks `qb_payment_id` (2204).
- `applyQuickBooks` skips a QuickBooks payment only if it is flagged `ours` (942) or its own key `qbpay-<oid>-<pid>` is already in the ledger (943–945). It never checks `qb_payment_id`.
- If the `ours` mark is missing (its source is **not shown**), a deposit recorded here is recorded a second time. The cap at 948–950 limits this only once the order is full.
- **Breaks:** 0178 note (b), "so it's never entered twice".
- **Fix:** at 944, also skip when `SELECT 1 FROM order_ledger WHERE order_id=? AND qb_payment_id=?` matches `$p['id']`.

### 4. `recordPayment` takes no lock, and its duplicate check is fragile (MED)

- **No lock.** It reads `$left` (2188) and records (2194) without the `tv-pay-<oid>` lock that `pay()`, `fromQuickBooks` and `applyQuickBooks` share. A check recorded at the same moment as a card charge can push "paid" past the total.
- **The duplicate key includes the minute.** The key hashes `date('YmdHi')` (2193):
  - A double tap that crosses a minute boundary is recorded twice and posted to QuickBooks twice.
  - Two genuine, identical payments within one minute become one record (money lost from the books).
- **The QuickBooks post runs even when nothing was recorded.** `received()` returns `void` (2508), so `recordPayment` cannot tell that the record already existed (2510). It still posts to QuickBooks (2202) and logs a second event (2210). Whether `recordOtherPayment` ignores a repeat of the same `$key` is **not shown**.
- **Breaks:** 0178 note (b), never entered twice.
- **Fix:**
  - Take `GET_LOCK('tv-pay-'.$oid)` around 2188–2209.
  - Make `received()` return a bool and skip the QuickBooks post and the event when it is `false`.
  - Use a key supplied by the form (a nonce), not the minute.

### 5. A card can be charged with no ledger record (MED)

In `pay()`, the card is charged at 1735 and the ledger is written last, at 1744. If `received()` or `Orders::record` throws, the card stays charged with nothing in the ledger. The `charge_id` write (1738) is also only best-effort.

A retry reuses the same key, because `paidBefore` and the decline count are unchanged (1734). Whether `chargeCard` then returns the earlier charge instead of charging again is **not shown**.

- **Breaks:** 0177 §4 / 0178 money record ("a charge without a record").
- **Fix:** write the ledger row right after 1735, before the QuickBooks post. Wrap 1737–1745 so that any failure logs a `needs_books` event that names `$charge`.

### 6. The deposit counts an unchecked cost as known (MED)

- 0177 §3 says an item whose COGS "isn't checked (Costing: unchecked, or zero)" is figured at 65%.
- Line 140 treats any non-zero cost as checked, including catalog costs never confirmed on the Cost tab (`costed()` at 2851 is not consulted). Copies always carry "unchecked" costs (214).
- Line 139 adds `ship_cost_cents` to the product cost. So a line with product cost 0 and a shipping cost counts as fully costed and the 65% fallback never applies. This understates the deposit.
- **Breaks:** 0177 §3, third bullet.
- **Fix:** in the `foreach` at 137, treat a line as costed only when `cost_cents > 0 && self::costed($pdo, $l['id'])` (or Costing's checked flag); otherwise use the 65% branch.

### 7. "Paid outside" orders can be released for delivery with nothing paid (MED)

- `shipHold` returns `''` for every `outside` order (781). Such an order can be marked Shipped or Ready with nothing recorded and no admin reason. This runs through `setStage` (760) and `setItemStatus` (1834).
- `vendorHold` still holds the same order for payment (798–799). So the two holds disagree, and approval itself says outside orders sit "on a payment hold" (1449, 1453).
- 0101 B.4, which the comment cites, is **not shown**. Against 0177 §4 / 0178 "Not changed" (delivery waits for payment, or an admin's release with a reason), this is a gap.
- **Fix:** remove the `outside` shortcut at 781. An outside order handled elsewhere already gets a `released` event via `releaseHold($outside=true)` (2493), which `overridden()` honours.

### 8. Credit terms can be set by any staff (LOW)

`saveTerms` (264) and `setField('due_terms')` (1961–1963) let any staff member set Net 15 or Net 30.

- Today this moves no money: holds still wait for payment, and only past-due display changes (1039).
- But 0178 §4 says Jason alone grants terms. Once M7 makes "the vendor and delivery don't wait", this becomes a release without payment.
- **Fix:** gate `net15`/`net30` on a Jason-only grant on the client account before M7 ships.

### 9. Overpayment in QuickBooks is cut without a trace (LOW)

`fromQuickBooksLocked` caps at `min($s['paid'], total)` (1787) and drops any excess silently. `applyQuickBooks` logs a `needs_books` event in the same situation (954).

- **Fix:** log `needs_books` when `$s['paid'] > total`.

### 10. A comment contradicts the code (LOW)

The comment at 2134 says item shipping is "left off the invoice", but 1464 puts it on the invoice. The money agrees with `recalc` (2874), so the amounts are consistent.

- **Fix:** correct the comment.

## Parts with nothing found

- **Refunds and credit memos (0178 §1, notes):** there is no refund or credit-memo code in this file. `cancel()` with money paid defers to that flow (746), and `cancelBlock` (711–730) matches §2. Whether a refund can exceed what was paid lives in `Orders`/`admin/order.php`, which are **not shown**.
- **0177 §1 several destinations:** release A refuses an order shipping to more than one address (1437–1441). `split`/`unsplit` (1547–1613) act only on draft or sent orders with nothing paid (1549, 1581), so no money is misrouted. Each split order inherits `deposit_pct` (1553) and recalculates.
- **0177 §2 tax:** the tax choice is applied per line (1466) and can't be changed after approval (1957). No defect.
- **Credit on account (0178 §3) and keyed cards (§5):** not present in this file.

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| ClientOrders.php:1753–1758 | Records the whole balance as paid when `fromQuickBooks` returns '' (lock timeout, nothing new, part paid); can double with `pay()` | 0177 §4 | high | Drop the fallback, or require QB paid ≥ total under the `tv-pay` lock |
| ClientOrders.php:1503, 1470, 1481 | Test (sand