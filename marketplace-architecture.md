# Marketplace: how it's built, who uses it, who configures it (outline for discussion, 2026-10-22)

**The rules are in `decisions/0135`**, the Marketplace's one record. This file is the build plan and screens; where
it differs, 0135 wins. A business's items are its live catalog (0116), not a new record.

Builds on:
- the Master AOS §3 (payments), §5 (tiers), §7 (C2C) and §8 #5 (the workspace);
- decisions/0031 (Post-and-Connect, Facebook Marketplace as the UX base);
- decisions/0032 (quotes, dual confirmation, item pricing), now gathered in decisions/0135;
- decisions/0036 (location groups);
- decisions/0109 (the admin engine).

## 0. The name

Workspace 5 is **Marketplace & Storefronts**. The admin menu shows its old nickname, "Market 360°", and its address
is `/admin/market/`. That's where "Market" came from.

**Decided (0135):** the workspace stays **Market 360°** at `/admin/market/`, with Marketplace and Storefronts as two
sub-sections managed independently. The public page stays `/market/`, named "Marketplace".

## 1. What the Marketplace is: three surfaces, one engine

| Surface | Who sees it | What it is |
|---|---|---|
| **The Marketplace** (`/market/`) | everyone | Browse and search what people and businesses offer, near a place: a grid of item cards (photo, price, title, where), filters in the Quick Search, an item page, Message the seller / Make an offer. Facebook Marketplace is the base; no paid ranking (0007). |
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
- **Rating:** now a **comment** (0058 dropped stars; 0135 §4), anchored to a completed, dual-confirmed offer or a
  paid order where there is one (0032).

### Workflows (built-in, editable in System Configuration → Workflows)

| Workflow | Stages |
|---|---|
| **Item** | Draft → Active → Pending (an offer accepted) → Sold; Paused; Expired (not confirmed still on offer, 0135 §6); Removed (kept, never deleted) |
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

### A. You and the team: admin workspace 5, Market 360° (configure and keep it safe)

Two sub-sections, **Marketplace** and **Storefronts**, managed independently (0135 §1). Overview, Items, Offers &
disputes and Marketplace rules belong to Marketplace; Storefronts, Custom domains and Gateways to Storefronts.

| Section | What it's for | Widgets |
|---|---|---|
| Overview | items live, offers this week, completed, disputes, stores live | MetricCards |
| Items | every item; hide what breaks the rules (the seller sees why); reports land here | Table, DetailPanel, Item workflow |
| Offers & disputes | completed, stuck and disputed offers; settle a dispute | Pipeline, DetailPanel |
| Storefronts | every store, its tier, status, domain | Table, DetailPanel |
| Custom domains | each domain's DNS check; fix or help | Pipeline (domain workflow) |
| Gateways | which are connected, which are broken | Table |
| **Marketplace rules** (configuration) | **categories and their fields** (cars: year, mileage; clothes: size…); **what can't be sold**; **catalog caps per tier** (from `commercial.md` §1.1); **which gateways are offered**; **automatic hold rules** (keywords, prices, sacred-site tags, decisions/0066); trust points per completed trade; how long a post stays up before it asks to be renewed | SettingsForm |
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
| Free | the listing, and the catalog (live, 0116) with offers and manual payments |
| **Basic** | the Shop tab on the listing, quotes and invoices, discount codes; manual payments |
| **Core** | **card checkout through their own gateway (0% system processing fees; the processor's own fees apply)**; Storefront on trvgo.us and their own domain; dynamic QR to items |
| **Strategic** | subdomains, CRM, headless API, AI catalog drafts (HITL) |
| **Cornerstone** | multi-location inventory; Co-Op hub (catalog caps by plan: `commercial.md` §1.1) |

## 4. Payments, kept simple

- **Person to person:** offline (cash, their own app); dual confirmation on the site.
- **Business sales:** checkout goes **straight to the business's own gateway**. We record the order on the ledger
  (decisions/0098), so it shows in their Orders and our counts, with 0% system processing fees (we take no cut; their processor charges its own fees) and no money held by us.
- **Manual payments are the only mode for members (person to person) and for Free and Basic listings; gateway checkout starts at Core** (Jason,
  2026-10-22). A member's accepted offer shows their own payment instructions the same way.
- **Manual payments module** (store settings; each method switched on by the business):
  - **Methods:** Zelle, bank transfer, check, pay on pickup / delivery, or one the business names.
  - **At checkout:** the buyer picks one and sees the business's own instructions, filled in. For Zelle that's its
    email or phone, or its Zelle QR image, with the amount and **"put Order #1042 in the memo"**. The same
    instructions go in the order email.
  - **The order** is placed as **Awaiting payment**. Stock is held for a set time (the business chooses, e.g. 3 days),
    with a reminder to the buyer, then released and the order cancelled if nothing arrives.
  - **The business** checks its bank, matches the order number and taps **Mark paid** in Orders. That starts
    fulfillment and records the payment on the ledger (method, reference), the same as our client orders' "paid
    outside QuickBooks" (built).
  - **What it costs and risks:** no fee, but no automatic confirmation and no protection for the buyer. Suited to local
    businesses, services and large custom quotes, not high-volume retail.
- **Lower-cost automated options** (offered when the business's gateway supports them):
  - **Bank payment (ACH)**, where the buyer signs in to their bank to authorize it: usually about 0.8–1.5%, often capped.
  - **Wallet buttons** (Cash App, Venmo and others): fees usually below standard card rates.
  - **Card surcharge** (the buyer pays the card fee): a switch the business turns on only where it's allowed. Card
    network rules cap it, it's never allowed on debit cards, some states restrict or ban it, and it must be shown
    before payment.
- **Gateways:** one connector framework; the business picks from the gateways we support. **Square and Stripe first**
  (0135 §7). The options, all with a hosted checkout page and the business's own account connected by
  sign-in (no keys pasted, no card data on our server):

  | Gateway | Why | Watch out for |
  |---|---|---|
  | **Square** | What many small and rural shops already use at the counter; one catalog and stock for counter and online; Cash App Pay, Apple Pay, Google Pay; ACH | Online rates a bit above Stripe's |
  | **Stripe** (Connect, Standard accounts) | The broadest checkout: cards, wallets, ACH (about 0.8%, capped), Cash App Pay; tax; the business owns its account and we set no fee | Fewer counter (POS) users in our towns |
  | **PayPal** (with Venmo) | Buyers trust it; the Venmo button | Highest standard rates; holds on new accounts |
  | **QuickBooks Payments** | Businesses that keep their books in QuickBooks; we already use it for our own sales (0095) | No hosted checkout like Stripe's (0095), so more to build |
  | Later: Clover, Authorize.net, Helcim | Ones a business already has | Asked for one at a time |

  Rates change: check each processor's current pricing when checkout is built.

## 5. Phone and screen standards (as every page)

- The Marketplace grid and the item page work at 390px first.
- Item cards follow the card anatomy:
  - the name is the link;
  - Link button top right;
  - Message and Save as round icons.
- Filters live in the Quick Search (category, price, distance, condition, from a person or a business), not in rows.
- Approximate location shows as a circle, never a pin, for a person.

## 6. Build steps (each one or two zips)

1. **Person-to-person** (built 2026-11-01, decisions/0135 "Step 1 built"):
   - items and the Item workflow;
   - Sell an item;
   - the Marketplace grid and the item page;
   - Message the seller;
   - Buying / Selling in the dashboard;
   - admin: Items, Marketplace rules (categories and fields, prohibited list, hold rules).
2. **Offers and trust** (built 2026-11-01, decisions/0135 "Step 2 built"):
   - offers and counters;
   - dual confirmation and trust points;
   - ratings;
   - admin: Offers & disputes.
3. **Business catalog** (built 2026-11-01, decisions/0135 "Step 3 built"):
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

Answered 2026-11-01 in `decisions/0135`:
1. **Name and address:** Market 360° in the admin (`/admin/market/`), with Marketplace and Storefronts managed
   independently; the public page stays `/market/` (§1).
2. **Free businesses** sell, with manual payments (§6).
3. **Catalog caps:** an allowance by plan, active items: personal 5, Free 10, Basic 25, Core 100, Strategic 250,
   Cornerstone 1,000 per listing (§7).
4. **Gateways:** Square and Stripe first (§6).
5. **Custom domains:** in the Storefronts build (§9). One **Connect your domain** step sets up the storefront and the
   business's email together (0137 §8), with the same DomainCheck. Hosting: Bluehost shared hosting caps addon
   domains and SSL, so custom domains go through **Cloudflare's custom hostnames** (Cloudflare for SaaS), not addon
   domains.
6. **Reviews** are comments (§5).
7. **Post lifetime:** "Offer this for" (personal items 30 days, personal services 90, business Ongoing) and one
   30-day check that asks and, a check later, expires what wasn't confirmed (§8).
