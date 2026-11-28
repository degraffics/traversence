# Platform atlas: marketplace-shop-checkout

*Draft for human review. Sources: the attached docs (charter, brand, PRD, `site-audit/pages.md`, decisions 0096, 0098, 0135 (truncated), 0142, 0154) and ten `sync-app/lib/` files. Several files are cut off: `MarketBiz.php` after line 525 and `MarketOffers.php` after line 492. No page files (`website/market/*.php`, `/shop/`, `/qr/`, `/business/`) were attached, and neither were `Market.php`, `MarketCheckout.php`, `Billing.php`, `OneOffs.php`, `ItemKinds.php`, `Plan.php` or `csp.php`. Behaviour that depends on them is "not shown".*

**Scope note.** `Checkouts.php` is not part of this area, despite its name. It reads `releases/checkouts.json`, the site files Claude sessions have claimed during a release (Checkouts.php:2–5, 12; decisions/0161). It is listed here only so it isn't mistaken for shop checkout.

The area has three parts:

1. **The Marketplace:** members' posts and businesses' published items (0135).
2. **Business checkout:** paid through each business's own Stripe or Square account (0135 step 4).
3. **The Traversence Shop and its one cart:** what Traversence itself sells (0154 §6, 0098).

---

## (a) What each person can do, page by page

Statuses are from `pages.md`.

### Resident (Get Local)

| Route | What | Status |
|---|---|---|
| `/market/` | Grid of people's posts and businesses' items together, newest first, with a **From** filter, search words, town/area and Filters (category, kind, price, free) | Live, placeholder (§1 table); 0135 says step 1 live 2026-11-01 |
| `/market/view.php?id=` | A person's post: photos, price, "Listed … in <town>", Message, "Hi, is this available?", Save, Seller card, "More from"; no comments or likes (0135 step 1) | Live |
| `/market/item.php?id=` | A business's item: Sold by, Link button, and buttons chosen by kind (below) | Live |
| `/market/rules.php` | Public "what can be sold" page with a quick check by name (0142, rules where the work happens) | Live |

**Item buttons by kind** (`MarketBiz::buttonsFor`, MarketBiz.php:120–131):

- **Main action, chosen by kind** (MarketBiz.php:137–143):
  - service and made to order get **Request a quote**;
  - fixed-price goods get **Buy now**;
  - anything else gets a quote.
- **Message** is added when the plan allows it.
- **Make an offer** is added only on physical, harvest and rental items, and only when the business switched offers on (MarketBiz.php:129, 134).
- **Button wording by kind** (MarketBiz.php:146–152): "Get tickets", "Book your stay", "Rent it", "Subscribe", "Buy and download", "Request this service".

### Traveler (Let's Explore)

No traveler-specific Marketplace page is shown. What a traveler can use comes from the item kinds:

- **Tickets:** one code per seat, checked in once at the door (KindCheckout.php:223–228, 267–277; route `/market/ticket.php`).
- **Stays:** dates, guests, house rules and lodging tax (KindCheckout.php:151–166).
- **Rentals:** dates, damage waiver, deposit (KindCheckout.php:167–175).
- **Digital downloads:** `/market/download.php` (KindCheckout.php:280–294).

Tickets, stays and rentals are filtered by place key, geo-hub or hub (MarketBiz.php:474–477). How these surface on `/discovery/` is not shown.

### Member (signed in)

| Route | What | Status |
|---|---|---|
| `/user/market/` | Selling (post status and buttons), Buying (saved posts, sellers messaged, offers), Post something; 18+; 5 active posts (0135 step 1, §7) | Live |
| `/market/cart.php`, `/market/order.php` | One cart per business; checkout; order page | Live |
| `/user/orders.php` | Your orders | Live |
| `/shop/` | What Traversence sells, shown for a member, a business, or a guest (0154 §6) | Built (channel-shop-earned zip) |
| `/qr/cart.php` | The one Traversence cart: GO Codes plus plans, packs and one-offs (CartPlans.php:2–6) | Live |
| `/qr/`, `/qr/manage.php` | Make GO Codes; Manage GO Codes; guest checkout allowed (0096) | Live |
| `/plans.php` | Plans; Start my free week (0154 Built) | Live |
| `/shop/domains.php`, `/user/domains/` | Domains paid in the one cart (CartPlans.php:89–92) | Built |

**Offers on people's posts** (`MarketOffers::act`, MarketOffers.php:158–243):

- Stages are accept, counter, decline and withdraw.
- After accepting, the seller taps **Sold** and the buyer taps **Got it**.
- Either side can call it off before both confirm.
- A problem can be reported up to 14 days after completion.
- After a completed trade, each person can leave one public note (MarketOffers.php:449–462).

### Business (via its listing's team)

| Route | What | Status |
|---|---|---|
| `/business/` (router/api) | Portal: Catalog, Publish/Unpublish, Marketplace settings, E-commerce setup, store policies | Live |
| `/business/pay-connect.php` | Connect Stripe or Square by OAuth | Live |
| `/listing/view.php?id=` Shop section | The listing's published items (Basic and up, 0135 step 3) | Live (page not shown) |
| `trvgo.us/<shop>`, custom domains | Storefronts | Not built (0135, step 5) |

### Admin

- **Market 360°** at `/admin/market/`, reached through `/admin/router.php`. Items, Business items, Offers & disputes, Gateways and Seller compliance are listed in 0135 and 0142.
- **E-Commerce** for Traversence's own sales: `/admin/ecommerce.php` and `/admin/order.php` (0098).

### Routes named in the docs but missing from `pages.md`

These should be reconciled under rule R09:

- `/api/market-webhook.php` (0135 4a)
- `/api/csp-report.php`
- `/api/go-codes.php` (0096)
- `/api/qb-webhook.php`
- `/order.php`, the client order page (0098, 0135 4b note)
- `/admin/market/` itself

---

## (b) How it works underneath

### Business catalog to published item ("walls")

- The catalog item is the draft, kept in the business's walled data (0110, 0116).
- `MarketBiz::publish` copies only public parts into `market_items` (MarketBiz.php:4–9, 234–313). `publicOf` takes title, about, kind, price mode, price and kind data (MarketBiz.php:165–175). Cost, markup and supplier never leave the walls.
- Every publish writes a numbered version to `market_item_versions` (MarketBiz.php:296–297).
- "Published, with unpublished changes" is detected by a hash (MarketBiz.php:186–189, 221–222).
- Photos are limited to 10 per item, must be the business's own gallery photos, and appear through a public "Marketplace" album only while the item is published (MarketBiz.php:39, 346–362, 387–412).
- **Caps by plan:** Free 10, Basic 25, Core 100, Strategic 250, Cornerstone 1,000 (MarketBiz.php:29). At the cap, Publish is refused with a message naming the plan that raises it; republishing an item already up is not blocked (MarketBiz.php:273–276).
- A listing without a micro-cluster can't publish (MarketBiz.php:155–160).

### Gates checked at publish, in order

1. Account standing (MarketBiz.php:236).
2. Item is Active (237) and has a name (239).
3. Category chosen (241).
4. Kind is complete (244) and fits the name (246).
5. Rulebook check (248).
6. Seller Agreement accepted (252–253).
7. Restricted or confirm answer recorded through `Acceptance::record` (258–263, 305–310).
8. Hold word goes to review (264).
9. CPSC recall check, which can hold the item (266–270).
10. Plan cap (273).

### Rulebook (`MarketRules.php`)

- **Tiers:** prohibited, review, restricted, confirm, notice (MarketRules.php:5–13, 22–51, 53).
- **Name reading:** a word counts only as the head noun, so "wine rack" passes (MarketRules.php:62–94).
- **Description:** a prohibited word in the description sends the item to review rather than refusing it (MarketRules.php:102, 108–113).
- **Admin word lists:** blocked and hold words are read the same way (MarketRules.php:124–150).
- **Recall check:** saferproducts.gov, 4-second timeout; a failure never blocks publishing (MarketRules.php:178–203).
- **Card payment:** restricted items can't be paid by card (`cardOk`, MarketRules.php:223).
- **Advance warning:** `forCatalog` tells the business while it is still shaping the item (MarketRules.php:157–171).

### Gateways (`MarketPay.php`)

- **Modes** (MarketPay.php:41–54):
  - live, sandbox, mock, or off;
  - mock is refused off a local test (44–47, 58–65);
  - a test account on live pays in sandbox.
- **Key safety:** live keys are refused in test mode (MarketPay.php:105–114).
- **Connecting:** OAuth connect and finish (MarketPay.php:182–207). Square requires an active location that takes cards (203–204).
- **Square tokens** are sealed with libsodium secretbox (MarketPay.php:118–137).
- **Stripe checkout** (MarketPay.php:240–275):
  - embedded page, card only;
  - expires at 31 minutes (246);
  - supports subscription, deposit and hold modes (250–270).
- **Square charge:** `chargeSquare` (MarketPay.php:288–296).
- **Payment confirmed with the gateway, amount checked against what's due** (`confirm`, MarketPay.php:302–319).
- **Webhook signatures:** Stripe within 5 minutes; Square HMAC (MarketPay.php:399–417).
- **Account standing**, translated into plain words (MarketPay.php:336–360).

### Checkout by kind (`KindCheckout.php`)

- Bookings, rentals and subscriptions are bought alone (KindCheckout.php:20, 153, 178).
- Availability is counted from the orders themselves (KindCheckout.php:69–91).
- Date rules: up to 90 nights or days, plus minimums (KindCheckout.php:94–110).
- Made-to-order items ask their spec questions and take a deposit percentage (KindCheckout.php:133–144).
- Fulfilment issues ticket codes and download tokens (KindCheckout.php:216–242).

### Tax and shipping (`Commerce.php`)

- Per-owner rules; owner 0 is Traversence, and inside the portal the rules are the business's own walled tables via `Scope` (Commerce.php:2–6, 22).
- **Tax:** state, city and ZIP rates added together (Commerce.php:112–131).
- **Shipping kinds:** flat, weight, free-over, pickup, local ZIP, local miles (Commerce.php:15–18, 200–224).
- Local-miles distance is measured between ZIP centroids using `zip_coordinates` (Commerce.php:227–237).

### Square catalog (`MarketSquare.php`)

- Requires plan feature `pos_sync` (MarketSquare.php:17, 27–30).
- **Import:** each Square variation becomes one catalog draft (MarketSquare.php:113–142).
- **Daily sync:** price and stock follow Square; a price change auto-republishes an item that has no other changes waiting (MarketSquare.php:150–194, 249–260).
- **Webhooks:** inventory changes are applied at once; catalog changes mark the business for the next check (MarketSquare.php:197–213).
- **Sales:** each sale decrements Square's count; on plans with card checkout, the order is also posted to Square Orders (MarketSquare.php:219–246). The header says this never fails the order (215–218), but no try/catch is shown in `sold()`. Whether the caller catches errors is not shown.

### Offers and trust (`MarketOffers.php`)

- Offers lapse after 7 days; a reminder goes out after 3; a completed trade earns each person +2 trust points, and losing a dispute costs −5 (MarketOffers.php:30–34).
- Trust points are awarded once per pair per 30 days and never for test posts (MarketOffers.php:272–281).
- Staff settle disputes with `settle` (MarketOffers.php:480–492, truncated).

### Linking and marketing consent (`Marketing.php`)

- Linking a business opens two-way messages.
- Members can unsubscribe with an ask-again period, and there is a master "No marketing from anyone" switch (Marketing.php:6–16, 29–44, 68–88).
- Contact sharing is per field and is revoked on unlink (Marketing.php:53–57, 115–133).
- The business sees linkers' names under the consent rule, with contact details read live and no export (Marketing.php:180–212). Insights are counts only (Marketing.php:214–234).

### One cart (`CartPlans.php`)

- Plans and packs sit beside GO Codes in the cart. Only the owner or a billing delegate can add them (CartPlans.php:21–38).
- Lines are grouped by account. Each group is paid with a new card, the card on file, or not yet payable, with the reason given (CartPlans.php:47–94).
- Groups are paid in order; payment stops at the first failure and reports what was done (CartPlans.php:104–130).
- Traversence's own payments go through QuickBooks (0098 Decided §1; 0154 §5). That code (`QuickBooksPay`, `Billing`, `OneOffs`) is not shown.

---

## (c) Decisions

| # | What it rules (as attached) |
|---|---|
| 0135 | **Master record for the Marketplace and Storefronts; overrides older records.** Covers: two display models; one catalog for a business; approximate area for a person's post; offers and dual confirmation; reviews as comments; no payment through Traversence; catalog caps as a plan allowance; 30-day "Still available?" check; steps 1–4b; the amendment "seller sets the price; buyer orders" (Buy now on every plan; Make an offer retired). Doc is truncated. |
| 0142 | Legal texts as versioned templates; `terms_acceptances` log (add-only, SHA-256 of text shown, IP, 7 years); Marketplace terms; rulebook tiers; Seller Agreement before Go live; INFORM Act; recalls. |
| 0098 | One order model and add-only ledger. Three kinds of commerce (Traversence's own, business storefronts, member C2C), and money differs by kind. QuickBooks keeps the books. "Fix it, credit, then refund"; credits last 90 days. PCI script list for Traversence's card pages. |
| 0096 | GO Codes: guest checkout, cart, Manage GO Codes, QuickBooks polling plus webhook. |
| 0154 | Free / Creator / Business balance. One upgrade sheet; Shop and one cart; creators keep 100%; community budget of 3% of revenue (floor $25); earned storage. |
| 0007, 0031, 0032, 0036, 0055, 0058, 0066, 0079, 0101, 0110, 0116, 0119, 0133/0164, 0134, 0136, 0137, 0141, 0144, 0