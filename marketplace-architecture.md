# Marketplace: how it's built, who uses it, who configures it (outline for discussion, 2026-10-22)

Builds on:
- the Master AOS §3 (payments), §5 (tiers), §7 (C2C) and §8 #5 (the workspace);
- decisions/0031 (Post-and-Connect, Facebook Marketplace as the UX base);
- decisions/0032 (quotes, dual confirmation, item pricing, the 250-item cap);
- decisions/0036 (location groups);
- decisions/0109 (the admin engine).

## 0. The name

Workspace 5 is **Marketplace & Storefronts**. The admin menu shows its old nickname, "Market 360°", and its address
is `/admin/market/`. That's where "Market" came from.

**Proposal:**
- The name in the menu becomes **5. Marketplace & Storefronts**.
- Its address becomes `/admin/marketplace/` (nothing links to the old one yet).
- The public page is `traversence.com/marketplace`.

## 1. What the Marketplace is: three surfaces, one engine

| Surface | Who sees it | What it is |
|---|---|---|
| **The Marketplace** (`/marketplace`) | everyone | Browse and search what people and businesses offer, near a place: a grid of item cards (photo, price, title, where), filters in the Quick Search, an item page, Message the seller / Make an offer. Facebook Marketplace is the base; no paid ranking (0007). |
| **Storefronts** | everyone | A business's own shop, from the same catalog, shown three ways: the **Shop** tab on its listing page; `trvgo.us/<shop>`; and its own domain (`clientdomain.com`, Core and up). Ad-free; its own theme. |
| **Back of house** | sellers; our team | Members: **Buying / Selling** in their dashboard. Businesses: the **Shop** module in the business portal. Our team: workspace 5 in the admin. |

Everything is one set of records (items, offers, orders), so a business edits an item once and it changes in the
Marketplace, on its listing, on trvgo.us and on its own domain.

## 2. The building blocks (same engine as the admin, decisions/0109)

### Records
- **Item:** belongs to a person or a listing; title, photos, category, its category's fields (condition, size…), price,
  tax flag, stock, where (approximate for a person, the business address for a listing).
  - **Private to the business:** cost, and markup as a flat amount or a percent (0032).
- **Offer / quote:** someone asks or offers; the seller accepts, counters or declines; both confirm the hand-off.
- **Order:** a business sale paid at checkout, through **the business's own gateway**. It's recorded on our order
  ledger (decisions/0098), but we never hold the money (AOS §3).
- **Storefront:** a listing's shop settings: theme, sections, pages, its trvgo.us name, its domain.
- **Gateway connection:** a listing's own payment gateway account (keys or connect).
- **Rating:** left after a completed, dual-confirmed offer or a paid order (0032).

### Workflows (built-in, editable in System Configuration → Workflows)

| Workflow | Stages |
|---|---|
| **Item** | Draft → Active → Pending (an offer accepted) → Sold; Paused; Removed (kept, never deleted) |
| **Offer (C2C and B2C quotes)** | Asked → Countered → Accepted → **Both confirmed** → Completed; Declined; Withdrawn; Disputed |
| **Merchant order** | Placed → Paid (by their gateway) → Ready / Shipped → Delivered; Refunded; Cancelled |
| **Storefront setup** | Not started → Building → Live → Paused |
| **Custom domain** | Added → DNS pointed → Checked → Live; Failed |
| **Gateway connection** | Added → Tested → Live; Broken (alert) |

**Dual confirmation (AOS §7):**
- the seller taps "Sold" and the buyer taps "Got it";
- only when both have, it's Completed;
- the item is marked sold;
- both earn trust points in the existing trust record.

### Widgets

**Public:**

| Widget | What it is |
|---|---|
| ItemCard | photo, price, title, where; the name is the link (decisions/0058 §11) |
| ItemGrid | |
| ItemPage | gallery and thumbnails, fields, "Listed 3 days ago in Show Low", **Message** at the top and a quick message below ("Hi, is this available?"), Seller details |
| OfferPanel | ask, offer, counter, accept |
| ConfirmBar | "Sold" / "Got it" |
| Cart and Checkout | business items only, paid through the seller's gateway |
| StorefrontShell | header, theme, sections, pages |
| RatingCard | |

**Back of house** (the admin's widgets reused):

| Widget | What it is |
|---|---|
| CatalogTable | |
| ItemEditor | photos, fields, price, private cost and markup, stock |
| Offers pipeline | |
| Orders pipeline | |
| ThemeEditor | colors, logo, sections |
| DomainCheck | the DNS record to add, then a check with a plain result |
| GatewayConnect | |
| SettingsForm | response-time target, sales steps (0032), pickup / shipping / local delivery |

## 3. Who does what

### A. You and the team: admin workspace 5, Marketplace & Storefronts (configure and keep it safe)

| Section | What it's for | Widgets |
|---|---|---|
| Overview | items live, offers this week, completed, disputes, stores live | MetricCards |
| Items | every item; hide what breaks the rules (the seller sees why); reports land here | Table, DetailPanel, Item workflow |
| Offers & disputes | completed, stuck and disputed offers; settle a dispute | Pipeline, DetailPanel |
| Storefronts | every store, its tier, status, domain | Table, DetailPanel |
| Custom domains | each domain's DNS check; fix or help | Pipeline (domain workflow) |
| Gateways | which are connected, which are broken | Table |
| **Marketplace rules** (configuration) | **categories and their fields** (cars: year, mileage; clothes: size…); **what can't be sold**; **catalog caps per tier** (Cornerstone 250; Basic, Core and Strategic to set); **which gateways are offered**; **automatic hold rules** (keywords, prices, sacred-site tags, decisions/0066); trust points per completed trade; how long a post stays up before it asks to be renewed | SettingsForm |
| GO Codes & short links | (built) | Table |

**At hand:** Edit listing, Member, Report, Order.

### B. A member (a person)

- **Sell:** one item at a time, free.
  - Steps: photos, title, price, category and its fields, approximate area (or their real address if they choose,
    0031), Post.
  - No catalog, no checkout; the Marketplace is the C2C model.
- **Buy:** browse, Message, make an offer; pay in person, off the site.
- **Dashboard → Buying / Selling:** my posts (renew, mark sold, pause), offers made and received, confirmations
  waiting, ratings.
- **Rules that apply:**
  - Free accounts get no AI.
  - Photos are resized and their location data removed (AOS §12).
  - Personal storage counts toward their plan.

### C. A business (by role, AOS §9B): the business portal's **Shop** module

> **Revised (2026-10-22):** the business side is our admin's own tools, scoped to the business and opened by plan. See
> `business-portal-architecture.md`. The Shop sections below become its Catalog, Quotes & invoices, Orders, Storefront & pages
> and Settings modules.

| Section | What it's for | Roles |
|---|---|---|
| Catalog | items with private cost and markup, stock, categories; import a CSV or a Shopify/Woo export (AOS §11.1); the tier's cap shown | Owner, Manager, Creator (drafts; publishing is a business setting) |
| Offers & quotes | requests, counters, accepted, confirm | Owner, Manager, Sales (their own) |
| Orders | paid orders, fulfillment, refunds through their gateway | Owner, Manager, Support, Accountant (view) |
| Storefront | theme, sections, pages, trvgo.us name | Owner, Manager, Creator, Developer |
| Domain | connect `clientdomain.com` (Core and up) | Owner, Developer |
| Payments | connect their gateway (Basic and up) | Owner, Accountant |
| Settings | response-time target, sales steps, pickup, shipping and delivery choices | Owner, Manager |

**What each tier opens:**

| Tier | What it adds |
|---|---|
| Free | the listing, and posting single items (C2C-style, offers only) |
| **Basic** | catalog and checkout through their own gateway (0% fee); the Shop tab on the listing |
| **Core** | Storefront on trvgo.us and their own domain; dynamic QR to items |
| **Strategic** | subdomains, CRM, headless API, AI catalog drafts (HITL) |
| **Cornerstone** | 250 items per listing; multi-location inventory; Co-Op hub |

## 4. Payments, kept simple

- **Person to person:** offline (cash, their own app); dual confirmation on the site.
- **Business sales:** checkout goes **straight to the business's own gateway**. We record the order on the ledger
  (decisions/0098), so it shows in their Orders and our counts, with 0% fee and no money held by us.
- **Direct payment methods** (Zelle, bank transfer, check, cash): a business can list them at checkout. The order waits
  as "awaiting payment", showing the business's Zelle details and the order number to put in the memo; the business marks
  it received (the same as our client orders' "paid outside QuickBooks", built). No fee, no automatic confirmation, no
  card protection for the buyer.
- **Gateways:** one connector framework; the business picks from the gateways we support. Which gateways come first is decided when checkout is built.

## 5. Phone and screen standards (as every page)

- The Marketplace grid and the item page work at 390px first.
- Item cards follow the card anatomy:
  - the name is the link;
  - Link button top right;
  - Message and Save as round icons.
- Filters live in the Quick Search (category, price, distance, condition, from a person or a business), not in rows.
- Approximate location shows as a circle, never a pin, for a person.

## 6. Build steps (each one or two zips)

1. **Person-to-person:**
   - items and the Item workflow;
   - Sell an item;
   - the Marketplace grid and the item page;
   - Message the seller;
   - Buying / Selling in the dashboard;
   - admin: Items, Marketplace rules (categories and fields, prohibited list, hold rules).
2. **Offers and trust:**
   - offers and counters;
   - dual confirmation and trust points;
   - ratings;
   - admin: Offers & disputes.
3. **Business catalog:**
   - the Shop module's Catalog (with private cost and markup), Offers and Settings;
   - the Shop tab on the listing;
   - tier caps enforced.
4. **Checkout:**
   - the payment gateway connection per business;
   - cart and checkout to their gateway;
   - merchant orders on the ledger;
   - admin: Gateways.
5. **Storefronts:**
   - `trvgo.us/<shop>` with themes;
   - custom domains with the DNS check;
   - admin: Storefronts and Custom domains.
6. **Import / export:** for catalogs (shared with AOS §11).

## 7. To decide

1. **Name and address:** "Marketplace & Storefronts", `/admin/marketplace/`, `/marketplace`. Agree?
2. **Free businesses:** can they post items (offers only, no checkout), or is selling a business item Basic and up?
3. **Catalog caps for Basic, Core and Strategic:** Cornerstone is 250 per listing. Proposed: Basic 25, Core 100,
   Strategic 250.
4. **Gateways:** which ones we support first (decided when checkout is built).
5. **Custom domains on Bluehost shared:** each domain is added by hand as an addon domain with its SSL in cPanel. Is
   that fine for now (we do it when a Core client asks), with automation once the hosting moves?
6. **Reviews:** only after a completed offer or a paid order (0032). Do businesses reply publicly to ratings?
7. **Posts expire:** after 30 days, with a "still available?" email to renew. OK?
