# Platform atlas: marketplace-shop-checkout

*`research/platform-atlas/marketplace-shop-checkout.md`. This is a draft for human review. It was written only from the attached documents and code.*

**Coverage.**
- Some code files are cut off at 40,000 bytes: `MarketBiz.php` stops at line 526 and `MarketOffers.php` at line 492.
- These files are named in the code or docs but not attached, so their contents are **not shown**: `Market.php`, `MarketCheckout.php`, `Plan.php`, `Billing.php`, `ItemKinds.php`, `OneOffs.php`, `AccountDomains.php`, `Acceptance.php`, `Standing.php`, `Products.php`, `QuickBooksPay.php`, `Orders.php`, `app/ui/csp.php`, `/api/market-webhook.php`, and every page file under `/market/`, `/shop/`, `/qr/` and `/business/`.
- `decisions/0135` is truncated near its end.
- `sync-app/lib/Checkouts.php` is **not part of this area**, despite the name. It tracks which site files Claude sessions have claimed during a release (`Checkouts.php:2-5`).

---

## (a) What each person can do, page by page

The code does not treat residents and travelers differently anywhere in this area. Both are buyers. The only lens is place: browse filters by cluster, geo-hub or hub (`MarketBiz.php:474-477`) and there is a "near" sort (`MarketBiz.php:463`).

### Resident or traveler (signed out, or browsing)

| Route | What they can do | Source |
|---|---|---|
| `/market/` | A grid of people's posts and businesses' items together, newest first by default. Filters: search words, town or area, category, kind, price, free, and a **From** filter. | 0135 Step 1 and Step 3; `MarketBiz.php:451-491` |
| `/market/view.php?id=` | A person's post: photos, price, "Listed … in <town>", Message, the "Hi, is this available?" quick message, Save, the Seller card, and Share, QR and Report. There are no public comments or likes. | 0135 Step 1 |
| `/market/item.php?id=` | A business item: "Sold by" with the Link button. Buttons depend on the item's kind: Buy now / Get tickets / Book your stay / Rent it / Subscribe, Request a quote / Request this service, Message, and Make an offer. | `MarketBiz.php:120-152`, 0135 Step 3 |
| `/market/rules.php` | A public check of whether something can be sold, by its name. Rules are listed by tier. Hold words are not listed. | 0142 "The rules where the work happens" |
| `/market/cart.php`, `/market/order.php` | One cart per business. Checkout uses the gateway's own card fields on our page, or the business's direct payment instructions. Then the order page. | 0135 Step 4a; `KindCheckout.php:20` (bookings, rentals and subscriptions are bought alone) |
| `/market/ticket.php`, `/market/download.php` | A ticket with a code per seat, checked in once. A download link limited by uses and days. | `KindCheckout.php:216-294` |
| `/shop/` | What Traversence itself sells. A guest sees GO Codes and "what an account would add". Status: "Built (channel-shop-earned zip)". | pages.md §1; 0154 §6 |
| `/qr/`, `/qr/cart.php`, `/qr/manage.php` | Make and buy GO Codes with guest checkout. A private key in the browser plus an order link keeps them. | 0096 |
| `/shop/domains.php` | Search for and buy a domain in the one cart. Status: "Built (domains-wallet)". | pages.md §1 |

### Member (signed in)

| Route | What they can do | Source |
|---|---|---|
| `/user/market/` (also Dashboard `#market`) | **Selling**: each post's status and buttons. **Buying**: saved posts, sellers messaged, and orders. **Post something**: 18+ only, 5 posts out at once. | 0135 Step 1 |
| Offers (`#market:offers`) | Make, counter, accept, decline or withdraw an offer. The seller taps **Sold** and the buyer taps **Got it**. Either side can call it off or report a problem. Each side can leave one note afterwards. | `MarketOffers.php:133-243, 449-462` |
| "How you take payment" | Zelle, Venmo or cash details, shown only to the buyer whose offer was accepted. | `MarketOffers.php:319-320` |
| `/user/orders.php`, `/plans.php` | Your orders. Plans and storage packs in the one cart. | pages.md §3; `CartPlans.php:21-38` |
| Linked businesses (Dashboard) | Subscribe, set frequency, unsubscribe with an "ask again" choice, share contact fields, and a master "No marketing from anyone" switch. | `Marketing.php:20-23, 38-76, 115-133` |

### Business (team in the business portal)

| Route or tool | What they can do | Source |
|---|---|---|
| Catalog, then **Publish** | The walled catalog item is the draft. Publish copies out only its public parts and creates a numbered version. Unpublish takes it down. | `MarketBiz.php:165-175, 234-313, 316-320` |
| Marketplace settings | Turn Offers on (offers are off by default). The other buttons come from plan and setup. | `MarketBiz.php:32-38, 85-104` |
| E-commerce setup, `/business/pay-connect.php` | Connect Stripe or Square by OAuth. Set manual payment ways, tax, shipping and pickup, store policies and the Seller Agreement, run a test purchase, then Go live. | 0135 Step 4a; 0142 zips 2–3; `MarketPay.php:182-207` |
| Your Square catalog | Import from Square and keep in sync (Basic and up). | `MarketSquare.php:27-30, 113-194` |
| Offers and orders | Answer offers from the Inbox. Mark Sold. Check tickets in at the door. Download a dispute packet per order. | `MarketOffers.php:89-99, 401-426`; `KindCheckout.php:267-277`; 0142 zip 3 |
| People who linked you | Names, plus only the contact fields each person chose to share, read live. There is no export. | `Marketing.php:181-212` |

---

## (b) How it works underneath

### Publishing through the "walls" (`MarketBiz.php`)

- **Plan caps.** Free 10, Basic 25, Core 100, Strategic 250, Cornerstone 1,000 (line 29).
- **What publish copies.** `publicOf()` copies name, description, kind, price and quantity, and the kind's details. Cost, markup and supplier are not copied (165-175).
- **Order of checks in `publish()`:**
  1. The account's standing: `Standing::guard` (236).
  2. The item is Active and has a name (237-239).
  3. The kind is ready, and the name fits the kind (244-247).
  4. The rulebook. Prohibited items stop here (248-249).
  5. The Seller Agreement has been accepted (252-253).
  6. A restricted or confirm match needs the business's "is / not" answer, otherwise it throws `MarketRuleAsk` (258-263).
  7. A hold word holds the item (264).
  8. A CPSC recall lookup can hold the item (266-270).
  9. The cap is checked, and the next plan is named if they're at it (273-276).
- **After the checks.** Place comes from the listing's micro-cluster (155-160). A version snapshot is written (296-297). A restricted answer is recorded as an `Acceptance` (305-310). The public "Marketplace" album is synced (387-412).
- **Visibility.** `canSee()` lets the business's team and staff see everything. Everyone else sees only items that are published on a listing that isn't suppressed (502-508).

### The rulebook (`MarketRules.php`)

- There are five tiers: prohibited, review, restricted, confirm and notice. The rules themselves are at lines 22-51 and their strength order at line 53.
- **Head-noun reading.** "Wine rack" passes and "red wine" asks, using `HEADS` and `JOINS` with `isThing()` (62-94).
- **Name versus description.** The name decides. A prohibited word only in the description sends the item to review (100-132). The admin's own blocked and hold word lists are read the same way (139-150).
- **While shaping an item.** `forCatalog()` tells the business early what Publish will meet (157-171).
- **Recall check.** A 4-second timeout, and a failure never blocks publishing (178-203).
- **Card payment.** Restricted items can't be paid by card (`cardOk`, 223).

### Offers and the two-sided confirmation (`MarketOffers.php`)

- Statuses and timers: lapse after 7 days, one reminder at 3 days, problems reportable for 14 days, +2 points for a completed trade, −5 for a lost dispute (24-34).
- Every move is logged as an event (67-71) and also posted into the two people's conversation (74-87).
- `complete()` marks the post sold, closes other open offers on it, and gives trust points once per pair per 30 days. Test posts get none (254-282).
- Business items complete when the business taps Sold, with no trust points (211-216, 261).
- Staff settle disputes with `settle()` (480+, cut off).

### Gateways (`MarketPay.php`)

- **Mode.** `env()` refuses `mock` anywhere but a local test (41-54, 58-65). A test account on the live site is sent to sandbox mode, and live keys are refused in test mode (49-52, 105-114).
- **Tokens.** Square tokens are sealed with libsodium secretbox (118-137).
- **Stripe.** An embedded Checkout session that is card only and expires at 31 minutes. It handles deposits, holds and subscriptions (240-275).
- **Square.** Charges the token from Square's Web Payments form (288-296).
- **Marking paid.** `confirm()` asks the gateway and checks the amount; the return page alone is never trusted (302-319).
- **Webhooks.** Stripe signatures must be within 5 minutes; Square uses HMAC (399-417).
- **Other operations.** Capture, refund and subscription status (370-394). Account standing for the Trust score (336-360).

### Item kinds at checkout (`KindCheckout.php`)

- `prepare()` adds kind-specific lines: travel fees, made-to-order specs and deposits, harvest pickup windows, booking dates, guests, lodging tax and house rules, rental waivers and deposits, and subscription intervals (118-195).
- Availability is worked out from the orders themselves (69-91).
- After payment: ticket codes and download tokens (216-242).

### Tax and shipping (`Commerce.php`)

- Rates are per owner, kept in the business's walled tables (21-34).
- `taxFor()` adds up the state, city and ZIP rates that match (112-131).
- `shipOptions()` offers flat, weight, free-over, pickup, local by ZIP, and local by miles from ZIP centroids in `zip_coordinates`. Options are sorted cheapest first (200-237).

### Square sync (`MarketSquare.php`)

- Each Square variation becomes one catalog item (64-91).
- Imported items are not on the Marketplace until published (113-142).
- Prices and stock follow Square. A published item is republished automatically only if nothing else is waiting (150-194). Inventory webhooks update counts (197-207). A daily tick runs the sync (249-260).
- `sold()` takes sold items off Square's stock. On Core and up it also creates the order in Square (219-246).

### One cart for Traversence's own sales (`CartPlans.php`)

- Plans, packs, one-off purchases and domains are grouped by account (47-94).
- `pay()` pays group by group and stops at the first failure, saying what was already done (104-130).
- GO Codes, Traversence's own orders and the ledger run through QuickBooks (0098, 0096). That code is **not shown**.

---

## (c) The decisions and what each rules

| Decision | What it rules |
|---|---|
| **0135** (master record) | The Marketplace and Storefronts are two views managed in one workspace. A business's catalog is its single item record. Personal posts show an approximate area. Offers and the two-sided confirmation. Reviews are comments. **No payment ever passes through Traversence.** Caps by plan. The 30-day "still on offer?" check. Steps 1–4b. The amendment: "the seller sets the price; the buyer orders" (Buy now, Request a quote, Send a price; Make an offer retired). |
| **0142** | Legal texts kept as versioned templates. The add-only `terms_acceptances` log (stores user id, a SHA-256 of the exact text shown, IP and browser). Store policies and the Seller Agreement. The rulebook's four tiers. The dispute packet. INFORM Act data for high-volume sellers. Recalls. |
| **0098** | One order record for three kinds of commerce. Traversence's own sales go through a QuickBooks ledger. Business storefronts go through the business's own processor with no ledger of ours. Member-to-member trades are settled offline. |
| **0096** | GO Codes: guest checkout, the cart, Manage GO Codes, confirming payment by polling and webhook. |
| **0154** | The Shop and one cart for what Traversence sells. Creators keep 100%. Upgrades are offered only where someone reaches for more. |
| **0031 / 0032 / 0007** (cited) | Post-and-connect. Offers with stages. No paid or engagement ranking. **The text of these decisions is not shown.** |
| **0110 / 0116 / 0119 / 0141 / 0144 / 0047 / 0052** (cited in code) | Walled business data, the catalog, scope, item kinds, kind fit, linking. **The text of these decis