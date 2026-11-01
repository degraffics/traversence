# ADR 0135: The Marketplace and Storefronts (the one record)

**Status:** Accepted (2026-11-01) by Jason.
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
- **The Marketplace, step 1 (people's posts):** live since 2026-11-01 (the 15:14 UTC sync): `/market/`, the item page,
  `/user/market/`, `Market.php` and the daily `market_tick`. What it does is in "Step 1 built" below.
- **Admin workspace 5, Market 360°** (`/admin/market/`), grouped under Marketplace, Storefronts and GO Codes & our pages:
  Items and Marketplace rules, GO Codes & short links, our pages and their form entries are live; Offers & disputes,
  Storefront Studio, Catalog rules, Gateways and Custom domains are placeholders, each naming its step. The Catalog
  rules placeholder reads "catalog caps by plan" (the old "250-item cap" text is gone, also from the Workspaces list).
- **The Marketplace, step 2 (offers, Sold / Got it, notes, disputes):** delivered 2026-11-01 in
  `marketplace-step2-plus_20261101.zip` (19 files, with the Workspaces text fix), built on the 15:29 UTC mirror; live
  once uploaded and `app/migrations/2027-01-13_market_offers.sql` has run. What it does is in "Step 2 built" below.
- **Not built:** businesses' catalogs on the Marketplace and their caps (step 3),
  checkout and gateways (step 4), the Shop tab, Storefronts, `trvgo.us/<shop>` and custom domains (step 5).

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
- **A person posts one thing at a time** (0032): no catalog screens. Personal posts are their own record
  (`market_posts`, step 1).
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

### 8. How long a post stays up (Jason, 2026-11-01)

Jason: posts are checked every 30 days and need a confirmation that they're still on offer; no confirmation takes them
down. A personal item and a business's product or service are offered for very different lengths of time, so the
seller sets how long.
- **"Offer this for"** on every post, with a default by kind:

  | Kind | Default | Choices |
  |---|---|---|
  | Personal item | 30 days | 7, 14, 30, 60 or 90 days; until a date |
  | Personal service (lawn mowing, lessons) | 90 days | the same, plus Ongoing |
  | Business item or service | Ongoing | 7–90 days, until a date, Ongoing |

- **One check, every 30 days.** It does two things at once:
  - **Asks:** "Still available?" for every post whose time is up (dated posts) or that's due its 30-day check
    (ongoing posts), in the Pulse and by email. For a business, one **Pulse case** "Still on offer?" carries a step
    per item (the Pulse rule). One tap confirms; a dated post renews for its length again.
  - **Expires:** any post asked at the last check and still not confirmed. So a post has 30 days to answer, and the
    answer window is the next check, not a separate grace period.
- While it waits for an answer, the post keeps showing.
- **Expired:** off the Marketplace and the Storefront, kept (never deleted), relisted in one tap. After that it follows
  0103's inactivity path.
- An item whose stock is tracked and sold out comes down by itself, without waiting for a check.
- The default lengths and the 30-day check are settings in Marketplace rules.

### 9. Custom domains

Belong to the Storefronts build (`marketplace-architecture.md` §6 step 5). How they're added on Bluehost is decided then.

### Still open

Nothing. Section 6's gateways after Square and Stripe are added when asked for.

## What changes

- **FAQ** (2026-11-01): the catalog answer gives businesses' caps as coming (step 3); "Can I sell things on
  Traversence?" and the Buying and selling answers describe step 1 as live.
- **Docs:** `future-considerations.md` (variants) and the SJ Portables BRD and FRD exports (a note at the top) carry
  the 1,000 cap. `commercial.md` §1.1 and the AOS, `admin-architecture.md`, `workspace-map.md`, `aos-gap-review.md`,
  `admin-rebuild-batch-plan.md` and `business-portal-architecture.md` now say "catalog caps by plan (0135)" in place of
  "the 250-item cap". `marketplace-architecture.md` follows this record.
- **When built** (site code):
  - `Billing::PLANS` gains an items allowance (personal plans 5) and `Plan` a `limit()` (as `business-portal-architecture.md` §7 planned);
    the Catalog greys out Add / Show at the cap.
  - Workspace 5's manifest splits into Marketplace and Storefronts (the Catalog rules placeholder's text already reads
    "catalog caps by plan").
  - The Item workflow gains **Expired**.

## Step 1 built (2026-11-01): people's posts

Delivered as a zip (24 files that differ from the live mirror of 2026-11-01 12:55 UTC); live once uploaded and the
database update `app/migrations/2027-01-12_marketplace.sql` has run (by itself, within the hour).
- **Records:** `market_posts` (status active, held, pending, sold, paused, expired, removed), `market_photos`,
  `market_saves`, `market_contacts`, `market_settings`. `app/lib/Market.php` owns them.
- **Public:** `/market/` is the grid: newest first, search words, a town or area, and one Filters button (category,
  items or services, price, free) with the ones in use shown with ×. `/market/view.php?id=` is the item page: photos
  with thumbnails, price, "Listed … in <town>", Message the seller (top button and the quick message "Hi, is this
  available?"), Save, Details, the Seller card (name, since when, how many sold, the Link button for the person), "More
  from" the seller. The engage bar gives views, Share, QR and Report; **no public comments or likes on a post**
  (buyers talk to the seller privately; Save is the bookmark).
- **Posting:** `/user/market/` (also the Dashboard's Marketplace section, `#market`): Selling (each post's status and
  buttons: Still available, Mark pending, Mark sold, Put it back up, Edit, Take off for now, Take down), Buying (saved
  posts, sellers messaged) and Post something. 18 or older. Photos go through the one uploader (target
  `market_photo`) into the member's gallery and show through a public "Marketplace" album only while the post is up.
- **Messages:** a buyer's message goes through the site's messages with the post's link. A post invites messages about
  it, so it reaches the seller whatever their message setting, unless either blocked the other (`People::send`,
  `$invited`). Up to 20 new sellers a day.
- **Rules (§7, §8):** 5 posts out per member; 30 days for an item, 90 for a service, Ongoing for services, or a date;
  the daily `market_tick` asks "Still available?" (Pulse and email) and expires what wasn't confirmed by the next
  check. Words that block a post and words that hold it for a person are settings.
- **Admin, Market 360°:** sections grouped under Marketplace, Storefronts and GO Codes & our pages. **Items** (metrics,
  table, drawer: Let it show, Take it down with a line the seller sees, Put it back up) and **Marketplace rules** are
  live; Offers & disputes, Storefront Studio, Catalog rules, Gateways and Custom domains are placeholders with their
  step. Held posts are one Pulse case (Safety) with a "Let it show" button per post. Reports on a post go to Social →
  Reports; taking one down there takes the post down with the reason. Staff can see a held or taken-down post's photos.
- **Not yet:** "Near me" on a map, businesses' catalogs (step 3); offers and dual confirmation came with step 2.

## Step 2 built (2026-11-01): offers, Sold / Got it, notes

Delivered as a zip (18 files that differ from the live mirror of 2026-11-01 15:14 UTC, which holds step 1); live once
uploaded and `app/migrations/2027-01-13_market_offers.sql` has run.
- **Records:** `market_offers` (asked, countered, accepted, completed, declined, withdrawn, fell_through, lapsed,
  disputed), `market_offer_events` (every move, by whom), `market_notes`. `app/lib/MarketOffers.php` owns them.
- **Offers:** on a post, the buyer sends an amount (a free post: "I'd like it"); the seller accepts, counters or
  declines; the buyer accepts a counter, counters again or withdraws. One open offer per buyer per post. Every move is
  also a line in the two people's conversation. No answer in 7 days: the offer closes (lapsed). Accepting marks the
  post Pending.
- **Dual confirmation (§4):** after accepting, the seller taps **Sold** and the buyer taps **Got it**; both: completed.
  The post is marked sold, other open offers on it close, and both earn trust points (`TrustScore::USER_MARKET_COMPLETED`,
  2 each, once a month for the same two people). One side confirmed and the other silent for 3 days: one email
  reminder. Before both confirm, either can call it off (the post shows again).
- **Notes (§5):** after a completed trade, each can leave one short public note about the other ("Say how it went").
  The latest three show on the seller card of their posts with "Bought from them" or "Sold to them". Reportable;
  Social → Reports hides one. No stars.
- **Problems:** "Report a problem" (once accepted, and up to 14 days after completing) goes to Market 360° → **Offers &
  disputes** (live: metrics, table, drawer with the full history) and to one Pulse case (Safety) with a Settle form per
  trade. Settling: it went through (completes it), it fell through, or the buyer or seller was in the wrong (falls
  through, and that person loses 5 trust points, `USER_MARKET_DISPUTE_LOST`). Both are emailed the note.
- **Where people see it:** the post page (Make an offer for buyers, Offers for the seller), Your Marketplace → Offers
  (`#market:offers`), and the Pulse ("Marketplace offer waiting on you"). A buyer with an offer on a post keeps seeing
  it after it sells.

## Older records that point here

0031 (Post-and-Connect; its "no payments through Traversence" stands), 0032 (quotes, pricing, catalog depth; its 250 is
now 1,000), 0036 (the catalog-cap part only), 0116 (the catalog in the
business portal). 0055's Marketplace naming and 0058's comments-not-stars stand as they are.
