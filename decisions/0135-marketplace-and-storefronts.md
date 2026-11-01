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
  (items and offers)" from Free). Publishing to the Marketplace is capped by plan (§7, step 3).
- **Back-Office → Plans & subscriptions:** the subscriptions pipeline and metrics, and **Plan features** (which tool
  each plan opens, `Plan::FEATURES`, changeable there). The plans' allowances are in `Billing::PLANS`: price, storage
  GB and AI credits a month. Packs (`Billing::ADDONS`) add storage or AI credits.
- **The Marketplace, step 1 (people's posts):** live since 2026-11-01 (the 15:14 UTC sync): `/market/`, the item page,
  `/user/market/`, `Market.php` and the daily `market_tick`. What it does is in "Step 1 built" below.
- **Admin workspace 5, Market 360°** (`/admin/market/`), grouped under Marketplace, Storefronts and GO Codes & our pages:
  Items, Business items, Offers & disputes, Marketplace rules, GO Codes & short links, our pages and their form
  entries are live; Catalog rules reads the caps by plan; Storefront Studio, Gateways and Custom domains are
  placeholders, each naming its step.
- **The Marketplace, steps 2 and 3:** live, their migrations run (`2027-01-13_market_offers.sql`,
  `2027-01-15_market_business.sql`). What they do is in "Step 2 built" and "Step 3 built" below.
- **Step 4a (checkout through the business's own Stripe or Square):** delivered, not yet uploaded; live once uploaded
  and `app/migrations/2027-01-16_market_checkout.sql` has run. See "Step 4a built" below.
- **Not built:** Square catalog sync (4b); Storefront Studio, `trvgo.us/<shop>` and custom domains (step 5).

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
- **Card checkout from Core**, through the business's own gateway, with the gateway's own card fields on our page
  (Step 4 decided): no card data on our page or server, no cut taken. We only record the order on our ledger (0098).
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

## Test posts: the Marketplace's sandbox (2026-11-01)

Jason asked to sandbox-test the Marketplace; it had no test mode (the site's sandbox is the QuickBooks switch for our
own orders). Now a team account (a row in `admin_access`) posts with **Test post** ticked (ticked by default for the
team; untick for a real post), `market_posts.is_test` (`2027-01-14_market_test_posts.sql`).
- Only our team sees a test post (marked Test); to anyone else it doesn't exist (404, never in the grid).
- Messages and offers on it are only between team accounts; the whole flow (counter, accept, Sold, Got it, note,
  problem, settling) works as usual.
- It uses up no one's 5 posts and counts in no numbers: metrics, the seller card, trades, notes or trust points.
- Market 360° → Items lists them under **Test posts**. Testing a trade needs two team accounts (seller and buyer).

## Step 3 built (2026-11-01): businesses' items

Jason's direction: "The catalog management is walled, but the post or publish to the marketplace should not be"; per
item draft and publish versioning; what a buyer can do depends on the listing's setup; offers off by default; photos in
this step. Delivered as a zip (21 files that differ from the live mirror of 2026-11-01 15:56 UTC, which holds step 2 and
test posts); live once uploaded and `app/migrations/2027-01-15_market_business.sql` has run.
- **Publishing, not reading through the walls:** the catalog item (in the business's walled data, 0110) is the draft.
  **Publish** copies only its public parts into `market_items` in the main database (name, description, kind, price
  shown, photos, the business's town). Cost, markup and supplier never leave the walls. The Marketplace, the Shop and
  search read only published records. Publishing is an action of the business portal after its role check: drafts and
  photos need **Catalog & content**; Publish, Unpublish and the buttons need **Publish** (Team).
- **Versions:** each Publish is a numbered version (`market_item_versions`, all kept). Edits to the draft show as
  **Published, with unpublished changes** until **Publish changes**. **Unpublish** takes it off; Publish brings it back.
- **Caps (§7):** published items per listing, Free 10, Basic 25, Core 100, Strategic 250, Cornerstone 1,000. At the cap
  Publish is greyed with the plan that raises it; republishing an item already up isn't blocked; nothing comes down.
- **What buyers can do:** Marketplace settings in the portal (`market_biz`), and per item: **Message** (its inbox),
  **Make an offer** (off by default; offers and counters as in step 2, answered by the team with Inbox; once accepted,
  the business marks it Sold, which completes it: no Got it, no trust points, no notes, as a business is reviewed in
  its listing's comments), **Request a quote** (Basic and up; a quote request in its inbox, the quote made in Quotes &
  invoices), **Buy now** (Core and up; greyed as coming until step 4).
- **Photos:** up to 10 per item, uploaded in the portal (target `catalog_photo`) into the business's gallery, counted
  against its plan's storage, shown through its public "Marketplace" album only while published.
- **Where buyers see them:** the Marketplace grid (people's posts and businesses' items together, newest first, with a
  **From** filter), the item page `/market/item.php` (Sold by, with the Link button), and the listing's **Shop** section
  (Basic and up).
- **The 30-day check:** "Still on offer?" in the business's Pulse; unconfirmed by the next check, the item expires.
- **Our team:** Market 360° → **Business items** (take down with a line the business sees, put back). Reports on an item
  go to Social → Reports. Offers & disputes includes offers on businesses' items.
- **Not yet:** the business's own inbox module in the portal (messages arrive in its listing's messages, as today),
  Buy now (step 4), storefronts on trvgo.us and custom domains (step 5), and test mode for business items.

## Step 4 decided (2026-11-01): card checkout through the business's own gateway

From Jason's gateway overview (Stripe and Square first; PayPal / Venmo next; Authorize.Net and Adyen later; 0% to us)
and his answers of 2026-11-01:
- **Connecting:** each business signs in to its own Stripe (Connect, Standard accounts) or Square (OAuth) account from
  the portal; nothing is pasted. We keep Stripe's account id, or Square's tokens sealed (as built in 4a: libsodium
  secretbox from the site's secret, as QuickBooks' are), checked daily and refreshed a week before they expire
  (Square's last 30 days). Traversence registers once with each as a
  platform (Jason: the Stripe platform account and the Square developer app, their keys in `.env`). A sandbox / mock
  mode, as QuickBooks has, so it's built and tested before they exist.
- **Checkout on our page, the gateway's card fields:** Stripe's embedded Checkout and Square's Web Payments form put
  the gateway's own fields inside our page: the buyer never leaves, and card data never touches our page or server
  (lighter PCI than our QuickBooks card form, 0098). The PCI questionnaire is the business's, as the merchant; fields
  in the gateway's own frame keep it at the lightest one (SAQ A). PCI DSS 4.0 still asks that the checkout page's
  scripts be listed and guarded against change (6.4.3, 11.6.1): only our own and the gateway's scripts load there,
  under a content security policy.
- **Buy now and a cart, one per business:** each business is paid through its own gateway, so a cart never mixes
  businesses ("Your order from …"). Both go to the same checkout.
- **E-commerce setup** in the portal, step by step within the plan: Getting paid (Stripe or Square from Core; manual
  methods on every plan) → Sales tax (the gateway's, or the business's own rates) → Shipping & pickup → What buyers can
  do → a test purchase ($1, refunded) → Go live (the checkout settings published, public parts only, as items are).
- **Webhooks, by industry practice:** the signature checked first (Stripe's with a 5-minute window; Square's HMAC),
  each event once (ids kept), the payment confirmed with the gateway before anything is marked paid (never the
  return page alone), a fast reply with the work after, a daily reconcile of orders still waiting. Only then does the
  server write the paid order and the customer into the business's own records, through the same gate as the portal.
- **Stock:** "Track stock" per item (off for services and made to order): held for about 30 minutes while someone is
  checking out, taken off when paid (quotes and manual orders marked paid too), Sold out at 0, low-stock alerts
  (Core). A Square-synced item's stock is Square's, both ways.
- **Square's catalog** (Jason: "yes, aligned with onboarding for subscription tiers"): Basic, catalog only (import as
  drafts, prices and stock kept in step); Core and up, catalog and payments (online orders sent back to Square).
  Imported items count toward the cap only when published.
- **Zelle and other direct payments** (Jason): Zelle has no platform API, so it stays a manual method, like wire and
  check: the business's Zelle details at checkout, marked paid by the business (already in §6). For people
  (step 2's offers), the seller can add how they take payment (Zelle, Venmo, Cash App, cash), shown only to the buyer
  whose offer they accepted, with the warning that these have no buyer protection.
- **Ticketing a business already sells elsewhere:** options B and C, recorded in 0134's amendment.
- **Later:** restaurant ordering systems, outside ticketing, accounting sync and more gateways
  (`future-considerations.md`, "Commerce integrations").

## Amendment (2026-11-01, Jason): the seller sets the price; the buyer orders

Jason: "This needs to be fast and clean. Request a quote is where negotiation comes in, but I still want a
straightforward way: pricing set by the seller, and the buyer accepts to proceed. If there are messages and
negotiations outside the transaction, let the seller address pricing overrides, not the buyer." This replaces Make an
offer on businesses' items and closes the gap where an accepted offer never became an order.

**1. Buy now: the buyer accepts the seller's price.** On a business's item with a set price (fixed, or "from" with
its options), the main button is **Buy now** (Jason, 2026-11-01: "Place order or Buy now"), on every plan:
1. The buyer picks the quantity and options (the catalog's own price rows and options, 0116), and pickup or delivery
   (the business's Shipping & pickup).
2. The order shows the seller's price, tax from the business's settings, and shipping, with the business's refund
   terms. The buyer taps **Place order**; nothing is negotiated here.
3. It becomes an **order** in the business's Orders (0098, the business's walled records), and the buyer gets it by
   email (sent for the business, 0137 §8) and in their account.
4. **Paying,** by the business's plan and setup (§6): from Core, **Pay now** through its own gateway, on our page
   (step 4); on every plan, the business's own ways (pay link, Zelle, check, pay on pickup), shown on the order, and
   the business marks it paid.
5. **Paid:** the item counts as sold (or its stock goes down, step 4), the buyer is added to the business's Customers,
   and a receipt goes out. Unpaid after the business's set time (default 3 days), the order is cancelled and the item
   (or stock) is released.

**2. Negotiation is the seller's, never the buyer's.**
- **Buyers can't propose a price** on a business's item: Make an offer is retired for businesses' items (it was off by
  default; any open offers are honored to the end).
- **Request a quote** (Basic and up) is where negotiation happens: the business writes the quote at the price it
  chooses (Quotes & invoices, 0118), and the buyer accepts it, which makes the order.
- **A price override from the conversation:** when a buyer and business talk in messages, the business (Inbox
  permission) can **Send a price** for that item, to that buyer: an amount, a quantity, an end date. It shows in the
  conversation as a card with **Buy at this price**; only that buyer can use it, once, before it ends. On Free (no
  quotes), this is how a business makes a special price. The buyer only ever accepts.
- Every override is logged (who set it, from what price, for whom), and shows on the order as "Price set by the
  business".

**3. Items with no price** ("Ask" items, services priced per job) show **Request a quote** (Basic and up) or
**Message** (Free), and the business answers with a quote or Send a price.

**4. Where else it applies.**
- **Shared cards** (0136, Selling in groups) carry the same buttons as the item page: Buy now, Request a quote,
  Message.
- **Tickets** (0134) use Buy now: on Free and Basic the order is paid the business's way and marked paid; from Core,
  Pay now.
- **People's posts** (one member selling to another): **the seller sets the price there too** (Jason, 2026-11-01).
  - **Buy now** at the post's price reserves it for that buyer (the post shows Pending) and shows the seller's
    payment ways (Zelle, Venmo, Cash App, cash; step 4, with the no-buyer-protection warning). No order or ledger:
    people pay each other directly (§6).
  - **Sold / Got it** (the dual confirmation, trust points and notes) completes it, as built in step 2.
  - **Buyers can't propose a price.** In messages, the seller can **Send a price** to that buyer, as businesses do. A
    free post's button is "I'd like it", and the seller picks who gets it.
  - Unanswered for 3 days (no Sold, no Got it), the reservation lapses and the post shows again.
  - Make an offer is retired for people's posts too; open offers are honored to the end.

**Changes from steps 2 and 3:** a business's buttons become Message, **Buy now** (every plan, creating an order),
Request a quote (Basic and up); the old Core-only card "Buy now" becomes the **Pay now** step of an order. Orders from
Buy now take stock down and add customers, as §6 and step 4 expect for "manual orders marked paid". People's posts
get Buy now and Send a price in place of offers.

## Step 4a built (2026-11-01): checkout through the business's own gateway

Delivered as a zip (25 files that differ from the live mirror of 2026-11-01 16:40 UTC); live once uploaded and
`app/migrations/2027-01-16_market_checkout.sql` has run. Built in a mock mode and tested without real Stripe or Square
accounts; real payments wait for Jason's one-time setup below.
- **E-commerce setup** (business portal, a new module): Getting paid (Connect Stripe / Connect Square from Core; Zelle,
  bank transfer, check, pay at pickup or another way, each with its instructions, on every plan) → sales tax → shipping
  and pickup → days to hold items for a direct payment → a test purchase → Go live / Pause. Then its Marketplace
  orders. Roles: Ledger to see and change it, Plan & billing to connect or disconnect, Publish to go live.
- **Connecting:** `/business/pay-connect.php`, a sign-in at Stripe or Square; the answer must carry the state we gave
  that person's session (a forged one is refused and logged). Stripe keeps the account id; Square's tokens are sealed
  (libsodium secretbox from the site's secret, as QuickBooks' are) and refreshed a week before they expire.
- **Buying:** Buy now and Add to cart on items whose buttons include them, on every plan (the order is placed on every
  plan; paying by card needs the business on Core or up and connected, otherwise the business's own ways are shown); one cart per business (`/market/cart.php`); checkout with the gateway's own card
  fields on our page (Stripe embedded Checkout, Square Web Payments) or a direct way with the business's
  instructions; the order page `/market/order.php`; orders in Your Marketplace → Buying; a Cart entry in the
  Marketplace menu.
- **Paid:** only when the gateway says so: webhooks at `/api/market-webhook.php?p=stripe|square` (signature first,
  each event once, a failed one forgotten so the retry runs, then the payment asked of the gateway), the order page
  asking on return, and the daily reconcile. Then stock comes off and the business's own invoice is written (or its
  payment recorded) through the same gate as its portal. A direct payment is marked received by the business in
  Quotes & invoices, which marks the Marketplace order paid.
- **The totals** are the invoice's own arithmetic (tax on taxable items at its rate, shipping untaxed), so the order and
  the business's invoice agree to the cent; each published item now carries its taxable flag.
- **Stock:** Track stock and how many, per published item, in the portal's Marketplace page, live at once; 35 minutes
  held for a card payment, the business's days for a direct one; Sold out at 0. The card window closes first (Stripe's
  checkout ends at 31 minutes; a Square payment after the hold is refused), so a released item is never paid for
  (review fix, 2026-11-01).
- **People's trades:** "How you take payment" in Your Marketplace → Offers, shown only to the buyer whose offer was
  accepted, with the no-buyer-protection warning.
- **Admin:** Market 360° → **Gateways** (live): the mode, connections (connected, stopped), live checkouts, paid orders
  and the businesses' sales this week (to them, not us).
- **Next, 4b:** Square catalog import and sync (Basic: catalog; Core: catalog and payments, orders sent back to Square).

**Jason's one-time setup (until then, Connect says "opens soon"):**
1. **Stripe:** in Traversence's Stripe account, turn on Connect (Standard accounts, OAuth). Add the redirect
   `https://traversence.com/business/pay-connect.php`. Add a Connect webhook to
   `https://traversence.com/api/market-webhook.php?p=stripe` for `checkout.session.completed`,
   `checkout.session.async_payment_succeeded` and `account.application.deauthorized`.
2. **Square:** in the Square Developer Dashboard, make an application; set its OAuth redirect to the same address, and a
   webhook subscription to `https://traversence.com/api/market-webhook.php?p=square` for `payment.created`,
   `payment.updated` and `oauth.authorization.revoked`.
3. **`.env`** (File Manager): `MARKET_PAY_ENV=sandbox` first (Stripe's test mode keys, Square's sandbox app), then
   `live`; `STRIPE_CLIENT_ID`, `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`; `SQUARE_APP_ID`,
   `SQUARE_APP_SECRET`, `SQUARE_WEBHOOK_KEY`.

## Older records that point here

0031 (Post-and-Connect; its "no payments through Traversence" stands), 0032 (quotes, pricing, catalog depth; its 250 is
now 1,000), 0036 (the catalog-cap part only), 0116 (the catalog in the
business portal). 0055's Marketplace naming and 0058's comments-not-stars stand as they are.
