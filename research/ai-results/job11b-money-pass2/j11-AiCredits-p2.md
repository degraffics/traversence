# Money-path review: `AiCredits.php` against decisions 0177 and 0178

*Draft for human review.*

## Scope

**Decisions 0177 and 0178 do not govern this file.** `AiCredits.php` handles AI Assist credits:

- free asks per day
- plan and add-on credit pots
- a monthly cost backstop

It has no orders, invoices, Ship to, tax, deposits, payments, refunds, credit memos, vendor release or QuickBooks calls. Against each decision section:

- **0177 §1 (levels / several destinations):** nothing in this file. No finding.
- **0177 §2 (tax):** nothing. No finding.
- **0177 §3 (deposit COGS + 15%):** nothing. No finding.
- **0177 §4 (payments, vendor release):** nothing. No finding.
- **0178 §1–§6 and the notes (refunds, cancel window, credit, terms, cards, delivered with a balance, money never has Undo):** nothing. No finding.
- **0178 "Not changed", `guardReal()` (no test moves real money):** this file charges no card and calls no gateway. It only reads `Billing` and `ai_jobs` (lines 30, 34, 57, 87). No test is shown. No finding.

**What this file does not show:**

- No code here writes to `ai_jobs` or charges anything. The booking of `charged_to` (header, line 8) happens elsewhere, so "a charge without a record" and "a retry that double-charges" can't be checked here.
- `Billing`, `Team`, `Thresholds` and the `/ai/` caller are not shown.

**Rule used for the findings below.** The only rule this file can break is its own stated policy (header, lines 2–9, Jason 2026-10-30): "no one causes Traversence an AI cost that a revenue stream doesn't cover." Each finding cites that header line as its rule.

## Findings

**1. Gates fail open on any database error (med).**
- `count()` returns 0 on any exception (line 88). The caller then sees:
  - `used` = 0, so `left` = full credits (lines 36–37);
  - free asks used today = 0, so the full free allowance (line 60).
- `spent` stays 0.0 if its query throws (line 57), so the cover check at line 63 never trips.
- Result: a failing read approves asks that no plan covers.
- **Rule:** header line 2, and lines 6–7 ("has none until… paid"; "A backstop holds a member's month").
- **Smallest fix:** in `count()` and the `spent` query, fail closed. Rethrow, or return `PHP_INT_MAX` / `INF`, so that `state()` returns `next = null`.

**2. Checked, then booked, with no hold for jobs in flight (med).**
- `count()` counts only `status = 'done'` (line 87).
- A job that is pending or running uses no free ask and no credit until it finishes. Two quick or parallel asks can both see `free_left = 1` or `left = 1` (lines 60, 65–66) and both run.
- Over-use is bounded by concurrency, but uncovered.
- **Rule:** header lines 4–5 ("each ask uses one AI Assist credit").
- **Smallest fix:** count every status except failed or cancelled jobs (for example `status <> 'failed'`), so a booked ask holds its slot at once.
- Whether the caller books atomically is not shown.

**3. The backstop can count one business plan once per owner (low; check intent).**
- `cover` sums the `cents` of every pot, including each business the member owns (lines 42, 55, 59).
- `spent` is per member (`created_by = ?`, line 57).
- With N owners, one business plan's price backs N separate half-price allowances. The shared business credits (line 36) still cap credit use, but the cover backstop, the last guard, is multiplied.
- **Rule:** header line 7 ("half of what their plans pay a month").
- **Smallest fix:** decide whether that is intended. If not, divide a listing pot's `cents` by its owner count, or check the cover per pot over `charged_to`.

**4. The default fallback isn't visible, and a zero cover blocks everyone (low; unverifiable).**
- The comment says `FREE_PER_DAY` applies "when not set" (line 72), but `freePerDay()` returns `Thresholds::get(...)` with no fallback (line 76). `FREE_PER_DAY` (line 18) and `COVER` (line 20) are not referenced in the code shown.
- If `Thresholds::get` returns null or 0 for an unset key:
  - free asks become 0;
  - `cover` becomes 0, so `spent >= cover` (line 63) blocks every funded member with `why = 'cover'`.
- This fails closed, so no money is lost, but paid members are refused.
- **Smallest fix:** use `?: self::FREE_PER_DAY` at line 76 and `?: self::COVER` at line 59. Whether `Thresholds::get` already defaults is not shown.

**5. Add-on packs may refill every month (low; unverifiable).**
- Add-on credits are added to each month's allowance (line 34), and use is counted from `date('Y-m-01')` (line 36).
- If a "+50 pack" is a one-time purchase rather than a recurring add-on, it refills every calendar month without being paid for again.
- Line 34 also adds the add-on price into `cents` as monthly, regardless of cycle.
- `Billing::addons` / `ADDONS` are not shown.
- **Rule:** header lines 4–5.
- **Smallest fix:** confirm add-ons are recurring monthly. If they are one-time, count their use against their purchase, not the calendar month.

**6. Credits reset by calendar month, not billing period (low).**
- `used` counts from the 1st of the calendar month (line 36).
- A plan bought or renewed mid-month gets a fresh allowance on the 1st and again in its next period, so more credits than paid can be drawn around the boundary.
- **Rule:** header line 5.
- **Smallest fix:** count from the subscription's current period start, if `Billing::current` exposes one (not shown).

**Not defects, noted:**
- Line 37 reads `PLANS[$s['plan']][0]` without a guard. An unknown plan key raises a warning, not a money error.
- Line 31 relies on a failed-payment plan not being `'active'`. The statuses are not shown.

## Summary

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| AiCredits.php:88, :57 | DB error yields used=0 / spent=0, so asks are approved unfunded (fail open) | header :2, :6–7 (0177/0178 n/a) | med | fail closed: rethrow or return max so `next=null` |
| AiCredits.php:87 (via :36, :60) | only `done` jobs counted; parallel asks overrun free asks and credits | header :4–5 | med | count non-failed jobs, so a booked ask holds its slot |
| AiCredits.php:42, :55, :59 vs :57 | business plan's cents back each owner's cover separately | header :7 | low (confirm intent) | split listing cents by owner count, or check cover per pot |
| AiCredits.php:76, :59 (:18, :20 unused) | no visible default; unset threshold gives 0 free asks and cover 0, blocking all | header :17–20, :72 | low | `?: self::FREE_PER_DAY` / `?: self::COVER` |
| AiCredits.php:34, :36 | add-on credits and price treated as monthly; one-time packs may refill | header :5 | low (unverifiable; Billing not shown) | confirm recurring, else count against the purchase |
| AiCredits.php:36 | calendar-month reset, not billing period | header :5 | low | count from the period start |
| — | 0177 §1–§4, 0178 §1–§6 and notes, `guardReal` | 0177, 0178 | none | n/a: this file moves no order money |