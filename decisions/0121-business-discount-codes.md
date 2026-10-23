# 0121: A business's own discount codes

Status: Built (2026-10-24). Jason: "Discount codes: for listings we don't need to set rules for codes." Unlike ours
(Promotions, with its 65% floor and approvals, decisions/0108 §promos), a business's codes are its own offers: no
floor, no approval.

## What a business does (Business portal → Discount codes; Basic Partner and up; the role needs QR & short codes)

- **New code**: the code (3–40 letters, numbers or dashes, stored in capitals, unique per business), the offer (a
  percent off, at most 100, or an amount off), and if wanted: when it starts and ends, how many times in all, a
  smallest order, once per customer (on by default), a note for the team. On or off at any time.
- Its card lists where it was used (quote, customer, how much came off).

## On a quote

- **The team** adds it in the quote's card (Add a discount code; blank takes it off), or **the customer** types it on
  their quote page before approving (Have a discount code?). Only while the quote is a draft or sent.
- **Checked** when it goes on and again at approval: on, within its dates, not used up, the smallest order met, and
  (once per customer) not used by that email on another quote. A code switched off afterwards stops approval with a
  plain message until it's removed.
- **The amount** comes off the subtotal before tax (the taxable part in proportion), never more than the subtotal;
  the quote stores the code as it was (kind and value), so a later edit to the code doesn't change a sent quote's
  price. A percent follows the lines if they change.
- **Counted as used** when the quote is approved (`biz_code_uses`: code, quote, email, amount off).

## Data and files

- `b_biz_codes`, `b_biz_code_uses` in the business's walled data; `biz_quotes` gains `code_id`, `code`, `code_kind`,
  `code_value` (BusinessData VERSION 6: added once, checked against information_schema first, so MySQL takes it; 16
  tables, all walled).
- `app/lib/BizCodes.php`; `BizQuotes::sums()` (discount before tax), `setCode()`, `approve()`; the `business/codes`
  tool and the quote card in `app/admin/tools.php`; the `discounts` module (Marketing keeps QR codes, short links and
  campaigns, coming); the customer's page `q.php`.
- Terms (business quotes: discount codes), FAQ (making codes; using one).
