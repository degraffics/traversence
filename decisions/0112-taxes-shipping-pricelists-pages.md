# 0112: Taxes and shipping, price lists, and Storefront & pages, built in our admin first

Status: Built (2026-10-22). Jason, 2026-10-22: "Admin should have any and everything the business would", then
"Group all three into one zip." These close the last three gaps in business-portal-architecture.md's "Admin first"
list. Each tool is ours today (owner 0) and is scoped to a business by the business portal.

## 1. Taxes & shipping (Back-Office → Taxes & shipping)

- **Sales tax rates** (`tax_rates`, `Commerce::taxFor`):
  - a rate for a whole state, a city, or ZIP codes (whole ZIPs or their first digits);
  - rates to three decimals (5.125%);
  - an option to tax shipping too;
  - every rate that takes an address in is **added**: state, then city and district, as the place charges it.
- **Where our tax comes from** (`commerce_settings.tax_source`):
  - "QuickBooks works it out" stays the setting for our invoices, so nothing changes there;
  - "Our rates" uses the table, for orders paid outside QuickBooks and for the businesses' checkouts.
- **Shipping and pickup** (`ship_rules`, `Commerce::shipOptions`):
  - flat rate;
  - by weight (a base plus a price a pound);
  - free over an amount;
  - local pickup;
  - local delivery by ZIP codes, or by distance from a ZIP (miles from `zip_coordinates`);
  - each can be limited to some states;
  - a customer is offered every option that reaches them, cheapest first.
- **Check an address:** a state, city, ZIP, order total and weight show the options offered and the tax, line by line.
- **Who:** Finance sees it. Managers in Finance change it, and in Customer Success for shipping.

## 2. Price lists (Back-Office → Price lists)

- **A list** (`price_lists`) is for the customers carrying one CRM tag (Wholesale), or for everyone, and takes a
  percentage off the catalog.
- **Breaks and set prices** (`price_list_rules`):
  - from a quantity, a bigger percentage off, for every product or one;
  - or a set unit price for one product, to four decimals ($0.045 a card).
- **The price** (`PriceLists::priceFor`): every list the customer qualifies for is tried, and the lowest wins. It's
  worked out on the whole line, not the unit, so cheap items don't round wrong.
- **What would a customer pay?** An email, a product and a quantity show the catalog price, the price the customer
  gets and why.
- **Who:** Finance and Sales see it; their managers change it.

## 3. Storefront & pages (Marketplace & Storefronts → Storefront & pages, Form entries)

- **Our pages** (`site_pages`), at `/pages/view.php?s=<address>`:
  - Draft, Live or Hidden; drafts and hidden pages show only to our team, with a note saying so;
  - written in a little Markdown: headings, lists, bold, links and pictures (links and pictures only to our own paths
    or https).
- **Each page has:**
  - a search description;
  - a share picture (Open Graph and Twitter cards);
  - a canonical address;
  - JSON-LD (`WebPage`, or `ContactPage` with a contact form);
  - its views counted (`page:<id>`).
- **`/pages/sitemap.php`** lists the live pages for search engines.
- **Forms:** a page can carry a contact form or a quote form.
  - Entries (`site_form_entries`) come to **Form entries** (New, Handled, Spam), with Email them and Call them.
  - They come **into Customers as leads**, with their message in the activity.
  - Spam is kept out by a hidden box only robots fill in, five messages an hour from one connection (its address
    kept only as a hash), and the "verify you're human" check when it's set.
- **Who:** Content and Sales write pages; Content, Sales and Customer Success answer entries.
- **Storefront Studio** (Coming) is now each business's storefront: its theme, and these pages for its own site and
  domain.

## Where the data lives (decisions/0110)

- **Admin database:**
  - `commerce_settings`, `tax_rates`, `ship_rules`, `price_lists`, `price_list_rules`;
  - made there on first use (`AdminTables::make`) and recorded in `data_moves`;
  - part of group 5, so Move and Undo carry them.
- **Main database:** `site_pages` and `site_form_entries`. They're public-side, like orders: visitors read the pages
  and write the entries, as the main login.
- Every record has an `owner_id` (0 is Traversence) for the business step.

## Engine additions (for every tool)

- **Drawer actions:** text, notes, checkboxes and **selects**, with a current value; a long textarea for writing a
  page; "danger" actions (remove a price break).
- **Quick-create forms:**
  - optional fields;
  - options built from data, such as the price-list tag choice: a tool's `create.fields` can be a function.
- **Pages:** `tv_page_start()` takes `head` for a page's own share and canonical tags.

## Not yet wired in

- Our own checkouts and client orders still take tax from QuickBooks, and prices from the catalog.
- The business checkout step uses `Commerce::taxFor`, `Commerce::shipOptions` and `PriceLists::priceFor`. The client
  order builder can offer the customer's price-list price at the same time.
- Carrier rates and labels (USPS, UPS) are a later step (business-portal-architecture.md §3).

## Addendum (2026-10-23): the site's own "page not found"

- There was no custom 404 page. Now there's one: `app/ui/not-found.php` (`tv_not_found()`), shown by
  `website/_errors/404.php`.
- **What it shows:** the site's frame, "We couldn't find that page", a Search Traversence button, and Home, Get Local,
  Let's Explore and FAQ. It returns status 404, so search engines don't list the address.
- **Pages/view.php** uses it for an address with no page, and for a draft or hidden page opened by someone who isn't
  on our team.
- **Apache:** the domain's `.htaccess` (in `public_html/traversence/`, beside `website/`) has rule 7,
  `ErrorDocument 404 /website/_errors/404.php`, next to the 403 page's. Added 2026-10-23 to Jason's live file.
- **Other pages:** pages that say "not found" themselves (listings, journeys, guides) can switch to `tv_not_found()`
  as they're next changed.
