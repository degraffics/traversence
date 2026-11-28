# Money-path review: KindCheckout.php against 0177 and 0178 (draft for human review)

**File reviewed:** `traverse-3way-sync/sync-app/lib/KindCheckout.php`. Its header (line 2) calls itself `app/lib/KindCheckout.php`. Someone should confirm this sync copy matches the live file.

## Scope

**This file never moves money.** It decides:
- what each item kind adds to the order (fees, lodging tax, deposit, waiver)
- what's left as a balance (line 142)
- whether a rental deposit is a card hold (lines 169–173)
- what the buyer gets after paying (lines 216–242)

The calls that charge, capture, record, refund or retry live in `MarketCheckout`, which is not shown. So "charge without a record", "retry that double-charges" and "refund above what was paid" can't be checked end to end here.

**0177 and 0178 mostly govern a different system.** They cover client orders: QuickBooks invoices, Ship to and tax, the COGS + 15% deposit, Jason-approved refunds. This file is the Marketplace, paid through the business's own Stripe or Square (0141 §5). Only these parts of 0178 reach it directly:
- the CFO note (2026-11-27) on Marketplace refunds and `KindCheckout::checkIn`
- "Not changed": `guardReal()`, no test moves real money

I have not applied 0177 §3 (COGS + 15%) to the made-to-order `deposit_pct` at line 141. Whether it should apply is a scope question for a human, not a defect I can show.

## Defects

### 1. A rental's card hold can expire before the rental is returned (high)
- **Where:** line 171, with line 8 ("authorized, captured on return").
- **Problem:** The hold is allowed when the rental lasts 6 days or fewer (`$n > 6`). The check measures the rental's length, not the time from checkout to return. A 3-day rental starting 30 days out gets a hold that, per line 171's own comment, a card keeps for only about a week. By return there may be nothing left to capture.
- **Impact:** At least the deposit is lost. If line 8 means the whole rental is authorized, the rental fee is lost too. What is held is decided in MarketCheckout (not shown).
- **Rule:** General money path. No 0177/0178 section covers it.
- **Smallest fix:** Allow a hold only if `strtotime($to) <= strtotime('+6 days')`, measured from now. Otherwise fall back to `charge`.

### 2. A reversed rental date range is charged as one day and holds no dates (medium)
- **Where:** lines 101–103, together with line 78.
- **Problem:** For a rental, `$n = max(1, $n)` turns a negative count (return before pickup) into 1, so the check at line 103 passes. Line 108 only fixes `to === from`.
- **Impact:**
  - The buyer pays for 1 day on an inverted range.
  - At line 78, `$lf < $to && $lt > $from` is never true for that order, so it holds no dates.
  - Its own availability check at line 156 also always passes.
  - The same unit can then be sold to someone else, and one of the two buyers needs a refund.
- **Rule:** General money path.
- **Smallest fix:** Before line 102, add `if ($t < $f) throw new InvalidArgumentException('The return day is after the pickup day.');`.

### 3. Availability and capacity are checked, not reserved (medium)
- **Where:** line 156 (`freeOn`), and the ticket case at lines 145–147.
- **Problem:**
  - `freeOn` reads, then returns. Nothing in this file locks or re-checks when the order is written, so two buyers at once can both pass and both pay for the same dates.
  - For tickets, `prepare` never calls `seatsLeft` (lines 58–63). Whether MarketCheckout checks capacity, and whether it re-checks inside a transaction, is not shown.
- **Rule:** General money path (double sale, then a refund).
- **Smallest fix:**
  - In MarketCheckout, re-run `freeOn` / `seatsLeft(..., $exceptOrder = new id)` inside the write transaction, with a row lock on the item.
  - Add a `seatsLeft` check to the `ticket` case here.

### 4. Taken counts depend on how the JSON happens to be encoded (medium)
- **Where:** lines 71–72.
- **Problem:** The pattern `LIKE '%"item_id":' . $itemId . ',%'` only matches when `item_id` is encoded as a bare number followed by a comma. It misses an order whose lines were written with `"item_id":"12"` or with `item_id` as the last key.
- **Impact:** That order holds no seats or dates, so they can be oversold.
- **Rule:** General money path.
- **Smallest fix:** Drop the LIKE pre-filter, or relax it to `'%"item_id":%' . $itemId . '%'`, and rely on the exact check already at line 75.

### 5. Check-in and download trust only the order's status (0178 CFO note; medium)
- **Where:** lines 273 and 289.
- **What's shown:** Check-in refuses anything not `paid`. That is the gap the 0178 CFO note names: a refunded order stays `paid`, so its tickets still check in.
- **What's not shown:** The note's fix ("Mark refunded" in the portal, voiding the order's tickets) is not in this file.
  - If "Mark refunded" sets the order's status, line 273 will refuse correctly.
  - If it voids the tickets instead, as the note says, nothing here reads a void, and voided tickets still check in.
  - The same applies to downloads at line 289.
- **Rule:** 0178, CFO note (2026-11-27).
- **Smallest fix:** When the Mark refunded ticket lands, have `checkIn` (and `download`) also refuse a voided ticket, e.g. `if (!empty($t['voided_at'])) return [false, 'This ticket was refunded.'];`. Make sure "Cancel the record" clears it.

### 6. A missing item is charged as a plain physical line (low/medium)
- **Where:** line 124.
- **Problem:** Any line whose item isn't found becomes `physical` and passes through at the caller's `unit_cents`. That includes the setup's test purchase, but also an item deleted after it was added to a cart.
- **Impact:** A deleted item can still be charged. Whether the test purchase is kept off real money (0178 "Not changed": no test moves real money) is decided outside this file (not shown).
- **Rule:** 0178 "Not changed" (`guardReal`), as the analogous rule for the Marketplace. Also general money path.
- **Smallest fix:** Accept a missing item only when the caller marks the line as the setup test (e.g. `!empty($l['test'])`). Otherwise throw "This item is no longer for sale."

### 7. A refundable rental deposit has no return path here (low; note, not shown)
- **Where:** line 172.
- **Problem:** Line 172 promises "refunded on return", and no code here returns it. Under the CFO note, a business's own refund is recorded by the business. Where the deposit refund is done is not shown.
- **Rule:** 0178 CFO note.
- **Smallest fix:** None in this file. Confirm the return path exists in MarketCheckout or the portal.

## Parts with nothing found

- **0177 §1 (destinations, one invoice each) and §2 (tax):** Nothing in this file contradicts them. There is one `ships` flag (line 127) and no per-line destination, which fits release A's "one destination". Lodging tax (lines 163–164) is a flat percentage on nights × rate, as the business sets it, not QuickBooks tax.
- **0177 §4 (release waits on payment):** `fulfil` (lines 216–242) only issues tickets and downloads. Whether it is called only once the order is paid is the caller's job (not shown).
- **Retries in `fulfil`:** They are idempotent. Ticket seats continue from the existing count (lines 224–225), and an existing download token is reused (lines 231–233).
- **Made-to-order balance (line 142):** Rounded per line, and it can't exceed the line's total because 0 < pct < 100. Nothing found.
- **0178 §1–§6 (Jason's approval of refunds and credit memos, cancel window, credit, terms, keyed cards, "Yes, I have it"):** Nothing in this file touches them.

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| KindCheckout.php:171 (with :8) | Hold allowed by rental length, not time to return; the authorization can lapse before capture | general money path | high | Hold only if the return date is within 6 days of now; else charge |
| KindCheckout.php:101–103 (with :78) | Rental return before pickup becomes 1 day, is charged, and holds no dates (oversell) | general money path | med | Throw if `$t < $f` before `max(1, $n)` |
| KindCheckout.php:156, :145–147 | Availability and seats checked without a reservation; tickets not capacity-checked in `prepare` | general money path | med | Re-check under lock at order write; call `seatsLeft` in the ticket case |
| KindCheckout.php:71–72 | LIKE pattern misses orders whose `item_id` is quoted or last, so they don't count as taken | general money path | med | Loosen or drop the LIKE; rely on line 75's exact match |
| KindCheckout.php:273, :289 | Check-in and download read only `status = 'paid'`; a refunded order or voided ticket still admits | 0178 CFO note (2026-11-27) | med | Also refuse voided tickets; Cancel the record clears the void |
| KindCheckout.php:124 | Missing item passes as physical at the caller's price; test vs real not decided here | 0178 "Not changed" (no test moves real money) | low/med | Accept only an explicitly marked test line; else refuse |
| KindCheckout.php:172 | "Refunded on return" deposit has no return path in this file | 0178 CFO note | low | Confirm the path exists in MarketCheckout or the portal (not shown) |

Open question for the human reviewer: should 0177 §3 (deposit = COGS × 1.15) govern Marketplace made-to-order deposits (line 141), or is that left to each business under 0141? The decisions as shown don't say.