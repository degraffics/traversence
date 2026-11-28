# Money-path review: `QuickBooksPay.php` against 0177 and 0178

*Draft for human review. Every line reference is to the numbered file. When a caller decides the outcome (`admin/order.php`, checkout, cron, webhook), the caller is not shown and I say so.*

## Defects

### 1. A failed linked card payment can be recorded twice in the books (high)

**Where:** `QuickBooksPay.php:941-948`

- `recordCardPayment` catches *any* `Throwable` from the linked `POST payment`.
- It then posts a second, plain payment under a **different** request id (`$key . 'p'`, line 947).
- `httpRetry` (369-375) already retries on no answer and 5xx. So a first post that QuickBooks created, but whose answer was lost or returned 5xx, is followed by a second payment for the same charge.
- The QuickBooks invoice then shows double the money received. The excess becomes customer credit, which QuickBooks may auto-apply to the next invoice (comment at 1083-1084). That is money not collected.

**Breaks:** 0178 note (b), "so it's never entered twice". This is a record without a charge.

**Smallest fix:** fall back only on a validation refusal, for example `if (!str_starts_with($e->getMessage(), 'QuickBooks (400)')) throw $e;` before line 947. Otherwise rethrow.

### 2. Money calls don't check the order's QuickBooks mode (high)

**Where:** `QuickBooksPay.php:115-122` (guardReal), 898-950, 956-966, 590-592

- `guardReal` blocks only one direction: a test order while the mode is production (line 118 returns for any other mode).
- Nothing stops a **live** order (`qb_env = 'production'`) from being charged or paid while `env()` is sandbox. That happens when:
  - the switch is set to Sandbox, or
  - a staff test login is signed in, which forces sandbox (39, 104).
- In that case:
  - `chargeCard` makes a sandbox charge, so no real money moves.
  - `recordOtherPayment` / `recordCardPayment` post against the live invoice id in the sandbox company.
  - The caller may then mark the order paid. The live invoice is never paid.
- The file already knows this mismatch matters: `invoicePaid` returns null for it (819).

**Breaks:** 0177 §4 (vendor release waits on real payment) and 0178 note (b). This is a record without a charge, and a record lost from the live books.

**Smallest fix:** in `guardReal`, add a check after `guardStaging()`:

```php
if (self::env() !== 'mock' && !empty($o['qb_env']) && $o['qb_env'] !== self::env()) throw new RuntimeException(...);
```

### 3. A refund isn't tied to "the payment it comes from" and isn't capped (high)

**Where:** `QuickBooksPay.php:969-987`, 993-1001

- 0178 §1 has the admin choose "the payment it comes from". `refundCard` ignores that choice. It takes `$o['charge_id']` (a single column, 971) or the **first** card payment it finds linked to the invoice (978-983).
- A deposit and a balance are charged separately (comment at 901). So a refund can go to the wrong charge, or be larger than that charge.
- Nothing here checks `$cents` against:
  - what was paid,
  - what was already refunded, or
  - Jason's approved amount.
- Whether Intuit refuses an over-charge refund is not shown.

**Breaks:** 0178 §1. This is a refund that can exceed what was paid, or a misrouted refund.

**Smallest fix:**
- Add a `string $chargeId` parameter, so the prepared refund names its own charge.
- Before `payCall`, check `$cents <= ` that charge's amount minus prior refunds (from the ledger; schema not shown).

### 4. The invoice idempotency key is per order only (med)

**Where:** `QuickBooksPay.php:568`, 623, 486-488

- Both invoice paths send `requestid = 'inv-' . $oid`.
- The file's own comment (486-487) says QuickBooks creates only once per id. So any second `invoiceFor` for the same order returns the **first** invoice with its old lines and total. Examples of a second call: re-approval after an edit, or a re-invoice after a void.
- For 0177 Release B (one invoice per destination), every destination's invoice would collapse into one.

**Breaks:** 0177 §1 and §2 (tax and total per invoice). The destination or the order gets the wrong amount.

**Smallest fix:** use `'inv-' . $oid . '-' . $revisionOrDestinationId`.

### 5. Credit memos and refund receipts can land on the wrong customer and product (med)

**Where:** `QuickBooksPay.php:1091`, 1028, 1096

- `creditMemo` looks up or **creates** a customer from the order's current bill name and email (`customerId`, 533-540). It does not use `orderCustomer` (869).
- If the name has changed, the credit lands on a new or different QuickBooks customer and is never applied to the client's invoices.
- Both refund receipts and credit memos post to the GO Code item (`itemId`) even for client orders. This misposts income in the books.

**Breaks:** 0178 §3 (credit on the client's account) and 0098.

**Smallest fix:**
- Use `$cust = self::orderCustomer($pdo, $o) ?: self::customerId(