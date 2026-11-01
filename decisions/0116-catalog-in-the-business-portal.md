# 0116: The catalog in the business portal (step 2, first tool)

Status: Built (2026-10-23). Jason, 2026-10-23: "Go on 2", meaning business-portal-architecture.md §5 step 2: move our
own tools across one at a time.

## What changed

- **Catalog opens in the business portal** (every plan: `catalog` starts at Free). It's our Products tool (decisions/0101)
  scoped to the business through `Scope` (decisions/0115):
  - the same list, drawer and actions;
  - the business's own table in the listings database (`products`, with `owner_id`, item codes unique per business);
  - its view `b_products` and the write checks.
- **What a business keeps:**
  - its items, services, classes or events (`Products::BUSINESS_KINDS`);
  - prices for one or more quantities, with its own cost and markup;
  - options that change the price;
  - taxable or not, a supplier, a "ready in", and Active or Hidden.
  - Cost, markup and supplier are private to its team.
- **Editing moved into the drawer, for us as well:**
  - **Details**, **Prices** (one line each: how many, cost, price; price left off = cost plus markup) and **Options**
    (`Products::update()` changes only what's given);
  - **Add a product** is a quick form: name, kind, price, how many;
  - our full catalog editor (`/admin/products.php`) is still linked from our drawer.
- **Fixed:** our product drawer's **Run sizes** line was always empty. It read the prices from a field that no longer
  existed.
- **The business router** now draws any module that names one of our tools, so each next tool is a line in
  `app/business/portal.php`. Columns that are ours only (promo-code type) are hidden for a business
  (`business_hide`).

## Checked

- **As a business:** adding, prices for 1 and 12, options, a description; the list. A Free plan has the catalog.
- **Between businesses:** business A can't open or change business B's product through the portal's API ("That isn't
  here"). The Business walls check still passes.
- **Ours:** the same drawer editing in Back-Office → Products; all 89 admin sections.

## Next: Quotes & invoices and Orders

The client-order engine takes its payments through our QuickBooks, so it can't run as is for a business. The
business's version starts on **manual payments** (business-portal-architecture.md §7.6: Free and Basic sell this way
only):

- the business marks an order paid (Zelle, bank transfer, check, cash, pay on pickup) with a reference;
- card checkout through its own gateway comes at Core, with the gateway step.
