# ADR 0135: The Marketplace and Storefronts (the one record)

**Status:** Accepted (2026-11-01) by Jason, except section 8 (post lifetime), marked **(proposed)**.
**This is the master record for the Marketplace, Storefronts and the business catalog they show.** Where an older record
says otherwise, this one rules. Older records keep their history; each carries a line at its top pointing here (list at
the end). The build plan and screens stay in `marketplace-architecture.md`; tier prices and allowances in
`commercial.md` §1.1.

## Why one record

Marketplace rules had grown across 0031, 0032, 0036, 0055 and 0116, the AOS (§3, §5, §7), `commercial.md` and the
outline, and some no longer agreed:
- 0031's "no transaction processing" read as "no payments on the site", while `commercial.md` gives Core card
  checkout (Jason, 2026-11-01: it meant no payments pass **through Traversence**, which still holds);
- 0032 built ratings, and 0058 later dropped star ratings;
- 0032 set a catalog cap only for Cornerstone, while the AOS and the admin docs read "the 250-item cap" as every tier;
- the outline planned a new "Item" record, while the business catalog has been live since 0116.

## What's live (the `traverse-3way-sync` mirror, 2026-11-01)

- **The business catalog** (0116): the business portal's **Catalog**, our Products tool scoped to the business
  (`products` with `owner_id`). Items, services, classes or events; prices for one or more quantities; private cost,
  markup and supplier; options; Active or Hidden. Every plan has it (Back-Office → Plans → Plan features: "Catalog
  (items and offers)" from Free). **No item count is enforced**, and no plan carries one.
- **Back-Office → Plans & subscriptions:** the subscriptions pipeline and metrics, and **Plan features** (which tool
  each plan opens, `Plan::FEATURES`, changeable there). The plans' allowances are in `Billing::PLANS`: price, storage
  GB and AI credits a month. Packs (`Billing::ADDONS`) add storage or AI credits.
- **`/market/`:** the placeholder (0055). Sample cards marked "Sample", and "Post something" greyed out.
- **Admin workspace 5, Market 360°** (`/admin/market/`): GO Codes & short links, and our own site pages and their form
  entries, are built. Post-and-Connect, Catalog rules, Storefront Studio and Custom domains are placeholders.
- **Not built:** personal posts, offers, dual confirmation, the Shop tab, checkout, gateways, `trvgo.us/<shop>`.

## The rules

### 1. Two display models, one workspace

- **The Marketplace** (`/market/`, named "Marketplace" everywhere, 0055): one shared grid where everyone browses posts
  near a place. Facebook Marketplace is the UX base (0031): grid, filters in the Quick Search, item page with Message at
  the top and "Hi, is this available?" below, Seller details on their own view.
- **Storefronts:** one business's own shop, shown on its listing's Shop tab, at `trvgo.us/<shop>` and on its own
  domain.
- **Admin:** workspace 5 keeps the name **Market 360°** at `/admin/market/`. **Marketplace** and **Storefronts** are two
  sub-sections, managed independently: each has its own sections, rules, metrics and settings, and neither needs the
  other set up.
- **No paid or engagement ranking** anywhere in either (0007, 0031).

### 2. What's posted: one catalog for a business, simple posts for a person

- **A business's items are its catalog** (0116). An item that's Active shows in the Marketplace and in its Storefront;
  an edit shows in both. No second item record is built for businesses.
- **A person posts one thing at a time** (0032): no catalog screens. Personal posts are a new record when step 1 is
  built.
- **Private to the business:** cost, markup (flat or percent, 0032) and supplier.
- **Single items only** (0032): no product variants yet (`future-considerations.md`). Options that change the price
  (0116) cover sizes and add-ons.

### 3. Where it is

A person's post shows an **approximate area**, a circle, never a pin, unless they choose to show their address (0031).
A business shows its public address. Pins on a nation's land follow 0066.

### 4. Offers and dual confirmation (0032)

- An offer or quote is its own record with stages (Asked → Countered → Accepted → Both confirmed → Completed; Declined,
  Withdrawn, Disputed), not a message thread.
- **Completed needs both:** the seller taps "Sold", the buyer taps "Got it". Then the item is marked sold (kept, never
  deleted) and both earn trust points.
- **Response time:** each business sets its own target and sales steps; its actual times show against it (0032).

### 5. Reviews are comments

0058 dropped star ratings: on a listing, comments are how people review a business. A comment from someone with a
completed offer or a paid order is marked as such (0032's "verified interaction"). Businesses reply as anyone replies.

### 6. Payments

**No payment ever passes through Traversence (0031, Jason 2026-11-01).** We never stand between a buyer and a seller's
gateway, hold money or act as the merchant of record. That's what 0031 meant, and it holds:
- **People** pay each other offline; dual confirmation is on the site.
- **Manual payments on every plan** (Zelle, bank transfer, check, pay on pickup), marked paid by the business
  (`commercial.md` §1.1). Free businesses can sell.
- **Card checkout from Core**, straight to the business's own gateway's hosted page: no card data on our server, no
  cut taken. We only record the order on our ledger (0098).
- **Square and Stripe first (Jason, 2026-11-01).** Square fits the counter shops we sign; Stripe covers the rest, with
  low-cost bank payment (ACH). PayPal / Venmo next if buyers ask; QuickBooks Payments and others when a business asks.
  The options compared are in `marketplace-architecture.md` §4.

### 7. Catalog caps: an allowance on the plan (Jason, 2026-11-01)

The catalog cap is an **allowance**, like storage and AI credits. It sits beside them in `Billing::PLANS`, shows on
Back-Office → Plans and the business's Plans page, and is listed in `commercial.md` §1.1.
- **Active items only;** hidden, sold and expired ones don't count. A business's cap is per listing, and a Location
  Group adds no combined cap (0036).
- **At the cap:** adding or showing another item is greyed out with the reason and the plan that raises it, never a bare
  error. Nothing already showing is taken down.
- **Numbers:**

  | Personal (a member) | Free | Basic | Core | Strategic | Enterprise / Cornerstone |
  |---|---|---|---|---|---|
  | 5 active posts | 10 | 25 | 100 | 250 | 1,000 per listing |

  Cornerstone's 1,000 replaces 0032's 250.
- **No item packs.** Every active item uses storage (its photos), so storage is the natural bridge: a business that
  grows its catalog grows into the storage packs, and then a higher plan.

### 8. How long a post stays up (proposed)

Jason: posts are checked every 30 days and need a confirmation that they're still on offer; no confirmation takes them
down. A personal item and a business's product or service are offered for very different lengths of time, so the
seller sets how long.
- **"Offer this for"** on every post, with a default by kind:

  | Kind | Default | Choices |
  |---|---|---|
  | Personal item | 30 days | 7, 14, 30, 60 or 90 days; until a date |
  | Personal service (lawn mowing, lessons) | 90 days | the same, plus Ongoing |
  | Business item or service | Ongoing | 7–90 days, until a date, Ongoing |

- **Dated posts:** when the time is up, "Still available?" in the Pulse and by email; one tap renews it.
- **Ongoing posts:** checked every 30 days and kept showing while the business answers. One **Pulse case**
  "Still on offer?" carries a step per item (the Pulse rule). An item whose stock is tracked and sold out comes down by
  itself.
- **No answer in 7 days:** the post becomes **Expired**: off the Marketplace and the Storefront, kept, relisted in one
  tap. After that it follows 0103's inactivity path.
- Lengths, reminder and grace period are settings in Marketplace rules.

### 9. Custom domains

Belong to the Storefronts build (`marketplace-architecture.md` §6 step 5). How they're added on Bluehost is decided then.

### Still open

- The post-length details (section 8).

## What changes

- **Docs:** `commercial.md` §1.1 and the AOS, `admin-architecture.md`, `workspace-map.md`, `aos-gap-review.md`,
  `admin-rebuild-batch-plan.md` and `business-portal-architecture.md` now say "catalog caps by plan (0135)" in place of
  "the 250-item cap". `marketplace-architecture.md` follows this record.
- **When built** (site code):
  - `Billing::PLANS` gains an items allowance (personal plans 5) and `Plan` a `limit()` (as `business-portal-architecture.md` §7 planned);
    the Catalog greys out Add / Show at the cap.
  - Workspace 5's manifest splits into Marketplace and Storefronts; the Catalog rules placeholder's text ("the 250-item
    cap per catalog") becomes "catalog caps by plan".
  - The Item workflow gains **Expired**.

## Older records that point here

0031 (Post-and-Connect; its "no payments through Traversence" stands), 0032 (quotes, pricing, catalog depth; its 250 is
now 1,000), 0036 (the catalog-cap part only), 0116 (the catalog in the
business portal). 0055's Marketplace naming and 0058's comments-not-stars stand as they are.
