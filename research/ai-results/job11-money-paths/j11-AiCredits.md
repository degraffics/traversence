# Money-path review: `AiCredits.php` against 0177 and 0178 (draft for human review)

## Scope

0177 and 0178 govern **client orders**: invoices per destination, tax, the COGS + 15% deposit, payments, refunds, credit memos, terms, keyed cards and `guardReal()`. `AiCredits.php` does none of these. It reads plan and add-on prices to decide whether a member's AI ask is funded. It never charges a card, writes a payment, issues a refund or credit, or touches an order or invoice. The booking of `ai_jobs.charged_to` happens elsewhere (not shown).

So, part by part against the decisions:

| Decision part | Finding in this file |
|---|---|
| 0177 §1 (levels, one invoice per destination) | Nothing found; no invoices or destinations here. |
| 0177 §2 (tax) | Nothing found. |
| 0177 §3 (deposit COGS × 1.15) | Nothing found. |
| 0177 §4 (payments, vendor release) | Nothing found. |
| 0178 §1 refunds / credit-memo note | Nothing found; no refund or credit path. |
| 0178 §2 cancel window | Nothing found. |
| 0178 §3 credit on account | Nothing found. (AI Assist "credits" are a different thing from 0098 / 0178 §3 account credit.) |
| 0178 §4 terms, §5 keyed cards, §6 delivered with balance | Nothing found. |
| 0178 "Not changed": `guardReal()`, no test moves real money | No test code shown. Nothing in this file calls a payment gateway. |

The only money this file guards is **Traversence's own AI spend**, under the file's own rule. Lines 2–3 say no one causes Traversence an AI cost that a revenue stream doesn't cover. Line 7 says a backstop holds a member's month at half of what their plans pay. The defects below are measured against that header rule, not against 0177/0178.

## Defects against the file's own rule (lines 2–9)

**1. A failed count reads as "nothing used", so it fails open.** Line 88: `catch (Throwable $e) { return 0; }`.
- If the `ai_jobs` count query fails, `used` becomes 0 (line 36). Every pot then shows full credits (line 37), and free asks show the full daily allowance (line 60).
- Result: AI cost with no credit to cover it.
- Breaks: header lines 4–5 and 8–9.
- Severity: **med**.
- Smallest fix: on error, return `PHP_INT_MAX`, or rethrow so `state()` refuses the ask.

**2. A failed spend query disables the backstop.** Line 57: the `cost_micros` query sits in `try { … } catch (Throwable $e) {}`.
- `$spent` stays `0.0` (line 56), so `$spent >= $cover` (line 63) can never trip.
- Breaks: the line 7 backstop.
- Severity: **med**.
- Smallest fix: in the catch, set `$spent = INF`, or set `$why = 'cover'`.

**3. Only finished jobs are counted, so concurrent asks can overspend.** Line 87 filters `status = 'done'`.
- An ask in flight (queued or running, not yet done) isn't counted toward `used` or free asks.
- Several asks started together all see the same `left` or `free` (lines 60, 64–66) and all proceed. That can exceed a pot's credits or the daily free allowance.
- The booking step that would close this is not shown.
- Breaks: header lines 4–5.
- Severity: **med**.
- Smallest fix: count every status except failed or cancelled (e.g. `status <> 'failed'`), or reserve the credit atomically when the job row is inserted.

**4. Add-on prices may inflate the monthly cover (unverified).** Line 34 adds `Billing::ADDONS[$a['addon']][1] * qty` to the monthly `$cents`.
- The header (line 5) calls these "+50 packs". If a pack is a one-time purchase, counting its price as monthly revenue raises the backstop (line 59) every month after it is bought.
- `Billing::ADDONS` and `Billing::addons()` are not shown, so I can't confirm whether packs recur or how long they last.
- Breaks: line 7 ("what their plans pay a month").
- Severity: **low**, until `Billing` is checked.
- Smallest fix: add a pack's price only in the month it was bought, or only for add-ons that bill monthly.

**5. Co-owners each get the business plan's full revenue in their own backstop.** Line 42 adds a listing pot for every owner, and line 55 sums each pot's `cents` into that member's cover. Line 57, meanwhile, counts only the member's own spend (`created_by = ?`).
- Two owners of one business can each spend up to `ai_cover` × the business plan's monthly price. Together that is double the intended share of that revenue.
- The backstop holds "per member" as line 7 is worded, but not per revenue stream (line 2).
- Severity: **low**.
- Smallest fix: compute the backstop per pot, using spend `charged_to` that pot, or split a listing's `cents` among its owners.

**6. The `FREE_PER_DAY` and `COVER` constants are never used as fallbacks.** Lines 18 and 20 define them. Lines 59 and 76 read `Thresholds::get(...)` with no fallback, yet line 72 says "FREE_PER_DAY when not set".
- What `Thresholds::get` returns for an unset key is not shown.
- If it returns 0: cover becomes 0 and every funded member is blocked as 'cover' (fails closed, no money lost), and free asks become 0.
- Severity: **low** (no money loss shown).
- Smallest fix: `Thresholds::get(...) ?: self::FREE_PER_DAY` and `?: self::COVER`, or confirm that `Thresholds` defaults to them.

## Not a defect, noted

- Lines 39 and 43 swallow `Billing` and `Team` errors. A failure there means no pots, so the member is unfunded. That fails closed.
- Line 31 excludes trial, past-due and free plans, which matches lines 6–7.
- Line 57's spend includes failed jobs' cost. That is conservative.
- The path on line 2 (`app/lib/AiCredits.php`) differs from the file's location (`sync-app/lib/AiCredits.php`). This is a comment only.

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| AiCredits.php:88 | Count error returns 0, so credits and free asks look unused (fails open) | file header l.4–5, 8–9 (not 0177/0178) | med | Return `PHP_INT_MAX` or rethrow on error |
| AiCredits.php:57 | Spend query error leaves `$spent = 0`, so the backstop never trips | file header l.7 | med | In the catch, set `$spent = INF` / `why = 'cover'` |
| AiCredits.php:87 | `status = 'done'` ignores in-flight asks, so concurrent asks overspend credits and free asks | file header l.4–5 | med | Count every status except failed, or reserve the credit atomically at insert (booking not shown) |
| AiCredits.php:34 | Add-on pack price counted as monthly revenue in cover (if packs are one-time; `Billing` not shown) | file header l.5, 7 | low | Count a pack's price only in its purchase month or billing cycle |
| AiCredits.php:42, 55, 57 | Each co-owner's cover includes the whole business plan, so combined spend can exceed the intended share | file header l.2, 7 | low | Per-pot backstop using spend `charged_to` that pot |
| AiCredits.php:59, 76 | `COVER` / `FREE_PER_DAY` constants unused as fallback (`Thresholds` defaults not shown) | file header l.72 comment | low | `?: self::COVER` / `?: self::FREE_PER_DAY` |
| (whole file) | No order, invoice, deposit, payment, refund, credit memo, terms, card or test path | 0177 §1–§4; 0178 §1–§6, "Not changed" | n/a | none: nothing found against 0177/0178 |