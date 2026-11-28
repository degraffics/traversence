# Money-path review: `QuickBooksPay.php` against 0177 and 0178 (draft for human review)

**Scope.** This review covers only `sync-app/lib/QuickBooksPay.php` and the two decisions. The callers (`admin/order.php`, checkout, the webhook, Pulse approvals) are **not shown**. Where a rule might be enforced by a caller, I say so and don't assume it either way.

---

## Defects

### 1. Refunds, credits and refund receipts don't check which QuickBooks company the order belongs to (high)

**Where:**
- `orderCustomer` 869–877
- `chargeFor` 969–987
- `refundCard` 993–1008
- `refundReceipt` 1015–1036
- `creditMemo` 1086–1100

**Problem.** `invoicePaid` refuses an order made in the other mode (line 819). None of the refund or credit functions do.

On an order made in the sandbox (`qb_env = 'sandbox'`), with the site now in production:
- `orderCustomer` returns the sandbox `qb_customer_id` (871).
- `chargeFor` sends that id to the live company (977). It then looks for a payment linked to the sandbox `invoice_id` (980).
- QuickBooks ids are company-local, so a live customer and live invoice with the same ids would be matched.
- `refundCard` would then refund that real customer's real card (1000).

`creditMemo` (1091) and `refundReceipt` (1033) would also write a live credit memo or refund receipt for a test order. The credit memo can auto-apply to a real invoice (1083–1084).

The comment at 112–113 allows refunds on "a test copy that once took real money". A sandbox-made order never took real money.

**Rule broken:** 0178 "Not changed": `guardReal()`, no test moves real money. Also 0178 §1: the money goes to the payment it comes from.

**Smallest fix.** Add one guard at the top of each of these five functions, the same as line 819:

```php
if (!empty($o['qb_env']) && self::env() !== 'mock' && $o['qb_env'] !== self::env())
    throw new RuntimeException('Order made in ' . $o['qb_env'] . ': refund or credit it there.');
```

### 2. `chargeCard` never checks what's still owed (med-high)

**Where:** 898–925, amount taken at 901.

**Problem.** The function charges `$cents ?? total_cents` without reading the invoice's balance. The same invoice can also be paid on QuickBooks' own pay link (`AllowOnlineCreditCardPayment` at 566 and 622). So a client who paid on the link and then pays on our page, or pays twice with two different `$key`s, is charged twice.

The `total_cents` used is also the local copy. 0177 §2 says the server confirms the amount shown before any charge; nothing here does that. Whether a caller does is **not shown**.

**Rule broken:** 0177 §2 (the server confirms the amount before any charge); no double charge.

**Smallest fix.** In the non-mock path, before calling `payCall`:
- read `invoicePaid($pdo, (int)$o['id'])`;
- refuse when the amount is more than `total − paid`, or when `paid >= total`.

### 3. The fallback in `recordCardPayment` can record one card charge twice (med)

**Where:** 941–949.

**Problem.** The `catch (Throwable)` at 945 also catches network failures and 5xx errors. In those cases QuickBooks may already have created the linked payment: `call()` → `httpRetry` throws only after both tries fail (371–374). The fallback then posts again with a different request id (`$key . 'p'`, 947). That makes a second payment on the invoice: the books show double, and the invoice shows overpaid or a credit.

If the fallback also throws, the card has been charged but nothing is recorded. The function returns nothing to help recover that; how the caller handles it is **not shown**.

**Rule broken:** a charge must have exactly one record (0178 money note: a card charged is a processed payment).

**Smallest fix.**
- Fall back only on a QuickBooks validation fault, i.e. when the message starts with `QuickBooks (400)`. Otherwise rethrow, so a retry uses the same `$key`.
- Or use `$key` for both attempts.

### 4. `refundCard` says "Nothing was refunded" when it can't actually tell (med)

**Where:** 1002–1004.

**Problem.** Any exception, including a timeout or 5xx where the refund may have gone through, is reported as "Nothing was refunded … Try again". If the retry goes through a new Pulse approval with a new `$key`, the client is refunded twice. `chargeCard` already handles the same situation correctly at 918.

**Rule broken:** 0178 §1 (refunds); no duplicate refund.

**Smallest fix.** Mirror 913–918:
- a 4xx error → "Nothing was refunded";
- anything else → "Can't tell yet whether it was refunded; don't refund again, check QuickBooks".

### 5. A refund can't name the payment it comes from, and nothing stops it exceeding what was paid (med)

**Where:** `chargeFor` 971 and 978–983; `refundCard` 993–1001.

**Problem.** 0178 §1 says a refund is prepared with "the payment it comes from". `refundCard` has no parameter for that payment:
- `chargeFor` returns the single `orders.charge_id` (971), or the first `CCTransId` linked to the invoice (982).
- An order paid by deposit and then balance has two charges (901 allows either), so a refund can be sent against the wrong charge.

There is also no cap against what was paid, or against refunds already made. Only Intuit's own per-charge limit applies. Whether the approved amount is checked upstream is **not shown**.

**Rule broken:** 0178 §1; 0177 §3–§4 (deposit and balance are separate payments).

**Smallest fix.**
- Add a `string $chargeId` argument to `refundCard`, taken from the payment the approval names.
- Refuse when `$cents` is more than that payment less what has already been refunded from it.

### 6. Every invoice for an order uses the same request id (med)

**Where:** 568 and 623 (`'inv-' . $oid`).

**Problem.** QuickBooks creates only once per request id (comment at 486–487). Two cases go wrong:
- **Re-invoicing an order after its lines change.** QuickBooks can hand back the earlier invoice, with the old lines and total. That invoice is then charged and paid.
- **0177 §1, one invoice per destination (release B).** Every destination's invoice would get the same id, so the second destination would never be invoiced. Its share would be lost or misrouted.

**Rule broken:** 0177 §1.

**Smallest fix.** Build the key from what makes the invoice unique: `'inv-' . $oid . '-' . $destKey . '-' . substr(hash('sha256', json_encode($ql)), 0, 12)`.

### 7. Credit memos and refund receipts can land on the wrong customer or product (low-med)

**Where:** `creditMemo` 1089–1091 and 1096; `refundReceipt` 1028.

**Problems.**
- `creditMemo` finds or creates the customer by display name or email (`customerId`). It doesn't use the order's own invoiced customer (`orderCustomer`). If the bill name or email changed, the credit goes onto a different (possibly new) customer than the one who was invoiced.
- Both functions post every line to the GO Code item (`itemId`), including refunds on client orders. That books the refund to the wrong product and income account.

**Rule broken:** 0178 §1 and its credit-memo note; 0177 §1 (an item keeps its own product).

**Smallest fix.**
- In `creditMemo`, use `orderCustomer($pdo, $o)` first and fall back to `customerId` only when it is empty.
- Pass the item (or the order's main item) into both functions, instead of always using `itemId`.

### 8. Possible sales tax on the billing address when nothing ships (low, needs a sandbox check)

**Where:** 538, together with 619–622.

**Problem.** When a new customer is made, its default `ShipAddr` is set to the billing address (538). An invoice with nothing shipped omits `ShipAddr` (622).

0177 §1 wants the fallback to be our address, named as such. Whether QuickBooks copies the customer's default ship-to onto such an invoice is **not shown**. If it does, tax follows billing, which is the problem 0177 set out to fix.

**Rule broken:** 0177 §1 and §2.

**Smallest fix.** Check this in the sandbox. Then either send our address as `ShipAddr` explicitly, or don't set the customer's `ShipAddr` at 538.

### 9. `guardReal` lets the invoice through if the order isn't found (low)

**Where:** 549 and 592.

**Problem.** The guard runs only when `Orders::get` returns a row. If the order isn't found, the invoice is made in production with no test check.

**Rule broken:** `guardReal` (0178 "Not changed").

**Smallest fix.** In production, throw when `Orders::get` returns nothing.

### 10. `recordOtherPayment` has no cap (low)

**