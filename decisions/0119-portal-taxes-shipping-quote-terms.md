# 0119: The business portal: Sales tax, Shipping & pickup, and a business's usual quote terms

Status: Built (2026-10-24). Jason: "Next." The next tools across (business-portal-architecture.md §5 step 2: "then
the business's own Taxes & shipping…"), and it settles 0118's open question: a business's tax rate, terms and pay link
are business-wide settings, not copied from the last quote.

## One engine, scoped

- `Commerce` (tax rates, shipping rules, settings) follows the scope like Customers and the Catalog: inside the
  business portal it reads and writes the business's own `b_tax_rates`, `b_ship_rules` and `b_commerce_settings`;
  outside, ours, unchanged. Its table definitions are one constant (`Commerce::TABLES`) used for ours (admin data)
  and for each business's walled data (`BusinessData::TABLES`, VERSION 4: 13 tables, each with its view and guards).
- Distance-based delivery reads the shared ZIP places from main (public data), whichever side is asking.
- Checked: a business's rate and settings land only in its own rows; ours stay as they were; System Configuration →
  Data separation → Business walls passes with the new tables.

## In the portal (Basic Partner and up)

- **Sales tax** (`taxes`, the role needs Ledger): the same list and card as ours: rates by state, city or ZIP codes,
  whether shipping is taxed, on or off.
- **Shipping & pickup** (`shipping`, the role needs Orders): flat, by weight, free over an amount, local pickup, local
  delivery by ZIP codes or distance, with a note for customers.
- **Plan & settings → Quotes & invoices: your usual terms**: when it's due, deposit %, the usual tax rate (one of
  the business's rates, or a percent), pay link, other ways to pay, notes on every quote, how many days a quote is
  good. Saved in the business's settings (`quote_*`); every new quote starts with them (until saved, a new quote
  copies the last one's, as before). Seeing them needs the Quotes view; changing them, Quotes edit.
- **On a quote**, Terms & how to pay offers **From your tax rates** (the business's active rates) alongside the
  percent.

## Not yet

- Working tax out from the customer's address automatically on a quote (the engine can, `Commerce::taxFor`; quotes
  don't ask for an address yet), and adding shipping to a quote from the rules.
- Price lists (Strategic) and Storefront & pages (Core) for businesses.
