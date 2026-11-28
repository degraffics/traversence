# Platform atlas: marketplace-shop-checkout (reconciled)

*This is a draft for human review, merged from two independent passes (P1 and P2). Each citation is the cited pass's own file:line. Points without a tag are supported by both passes. "Single-pass" marks a claim only one pass makes. "Passes differ" marks a conflict.*

**Both pass texts are truncated inside section (c).** Neither pass reaches sections (d) or (e), so those sections cannot be reconciled. See the end of this entry.

**Coverage (both).**
- **Cut-off files:**
  - `MarketBiz.php` is cut off. Passes differ on where: P1 says "after line 525", P2 says "stops at line 526".
  - `MarketOffers.php` is cut off at line 492 (both).
  - `decisions/0135` is truncated (both).
- **Not attached, so their contents are not shown (both):** `Market.php`, `MarketCheckout.php`, `Billing.php`, `OneOffs.php`, `ItemKinds.php`, `Plan.php`, `csp.php`, and all page files under `/market/`, `/shop/`, `/qr/` and `/business/`.
- **Not attached, single-pass (P2):** `AccountDomains.php`, `Acceptance.php`, `Standing.php`, `Products.php`, `QuickBooksPay.php`, `Orders.php`, `/api/market-webhook.php`.
- **`Checkouts.php` is not part of this area (both).** It tracks the site files that Claude sessions have claimed during a release (P1 Checkouts.php:2–5, 12, decisions/0161; P2 Checkouts.php:2-5). P1 adds that it reads `releases/checkouts.json` (single-pass).

**The three parts of this area (single-pass, P1):**
1. The Marketplace (0135).
2. Business checkout, paid through each business's own Stripe or Square account (0135 step 4).
3. The Traversence Shop and its one cart (0154 §6, 0098).

---

## (a) What each person can do, page by page

**Residents and travelers.** Passes differ on structure:
- P1 gives travelers a separate section, but says no traveler-specific page is shown.
- P2 merges residents and travelers. It says the code never treats them differently and that place is the only lens.

**Place filter.** Both cite MarketBiz.php:474–477, but passes differ on the first key: P1 says "place key", P2 says "cluster". P2 also cites a "near" sort (MarketBiz.php:463, single-pass).

**How these items surface on `/discovery/`** is not shown (single-pass, P1).

### Resident / traveler (browsing)

| Route | What | Source / status |
|---|---|---|
| `/market/` | Grid of people's posts and businesses' items together, newest first. Search words, town/area, category, kind, price, free, and a **From** filter. | Live, placeholder (P1 pages.md §1). The step 1 live date of 2026-11-01 is single-pass (P1, 0135). P2 cites 0135 Steps 1 and 3 and MarketBiz.php:451-491. |
| `/market/view.php?id=` | A person's post: photos, price, "Listed … in <town>", Message, "Hi, is this available?", Save, Seller card. No comments or likes (0135 step 1). | Passes differ on the remaining items: P1 lists "More from", P2 lists Share, QR and Report. |
| `/market/item.php?id=` | A business's item: "Sold by" with the Link button, and buttons chosen by kind (see below). | P1 MarketBiz.php:120–131; P2 MarketBiz.php:120-152 |
| `/market/rules.php` | Public check of whether something can be sold, by its name (0142). | Single-pass (P2): rules are listed by tier and hold words are not listed. |
| `/market/ticket.php` | One code per seat, checked in once at the door. | P1 KindCheckout.php:223–228, 267–277; P2 KindCheckout.php:216-294 |
| `/market/download.php` | Digital download. | P1 KindCheckout.php:280–294. Single-pass (P2): the link is limited by uses and days. |
| Stays, rentals (single-pass, P1) | Stays: dates, guests, house rules, lodging tax. Rentals: dates, damage waiver, deposit. | P1 KindCheckout.php:151–166, 167–175 |

**Item buttons by kind:**
- **Main action:**
  - Service and made-to-order items get **Request a quote**.
  - Fixed-price goods get **Buy now**.
  - Anything else gets a quote (single-pass, P1, MarketBiz.php:137–143).
- **Kind wording:** "Get tickets", "Book your stay", "Rent it", "Subscribe", "Buy and download", "Request this service" (P1 MarketBiz.php:146–152; P2 lists the same family at 120-152).
- **Message** is added when the plan allows (P1).
- **Make an offer** is added only on physical, harvest and rental items, and only when the business has switched offers on (P1 MarketBiz.php:129, 134). P2 says offers are off by default (MarketBiz.php:32-38, 85-104).

### Cart, Shop and GO Codes

Passes differ on who these routes are for: P1 files them under signed-in Member, while P2 files them under resident/traveler browsing.

| Route | What | Source / status |
|---|---|---|
| `/market/cart.php`, `/market/order.php` | One cart per business; checkout; order page. | Live (P1). P2 adds: the gateway's own card fields on our page, or the business's direct payment instructions (0135 Step 4a). |
| `/shop/` | What Traversence itself sells, for a member, business or guest (0154 §6). | "Built (channel-shop-earned zip)" (both). P2 adds that a guest sees GO Codes and "what an account would add". |
| `/qr/`, `/qr/cart.php`, `/qr/manage.php` | Make, buy and manage GO Codes; guest checkout (0096). | P1 CartPlans.php:2–6 (the one cart holds GO Codes plus plans, packs and one-offs). P2 adds that a browser-held private key plus an order link keeps the codes (single-pass). |
| `/shop/domains.php` (P1 also `/user/domains/`) | Domains paid in the one cart. | Passes differ on status: P1 says "Built", P2 says "Built (domains-wallet)". P1 cites CartPlans.php:89–92. |

### Member (signed in)

| Route | What | Source |
|---|---|---|
| `/user/market/` | **Selling:** post status and buttons. **Buying:** saved posts and sellers messaged. **Post something:** 18+ only, 5 active posts. | 0135 step 1 (P1 also cites §7). Passes differ on the third Buying item: P1 says "offers", P2 says "orders". P2 adds Dashboard `#market` (single-pass). |
| Offers | Accept, counter, decline, withdraw. Seller taps **Sold**, buyer taps **Got it**. Either side can call it off or report a problem. Each side leaves one note afterwards. | Passes differ on the range: P1 MarketOffers.php:158–243, P2 133-243. Notes: 449-462 (both). P1 says problems can be reported up to 14 days after completion. |
| "How you take payment" (single-pass, P2) | Zelle, Venmo or cash details, shown only to the buyer whose offer was accepted. | P2 MarketOffers.php:319-320 |
| `/user/orders.php`, `/plans.php` | Your orders; plans (P1: "Start my free week", 0154). | P2 adds that plans and storage packs go in the one cart (CartPlans.php:21-38). |
| Linked businesses | Unsubscribe with an "ask again" choice, share contact fields per field, and a master "No marketing from anyone" switch. | P1 Marketing.php:6–16, 29–44, 68–88, 53–57, 115–133; P2 Marketing.php:20-23, 38-76, 115-133. P2 adds subscribe and set frequency (single-pass). |

### Business (listing team / portal)

| Route or tool | What | Source |
|---|---|---|
| `/business/` | Catalog, Publish/Unpublish, Marketplace settings, E-commerce setup, store policies. | P1 (router/api, Live). P2 cites the Unpublish line, MarketBiz.php:316-320 (single-pass). |
| `/business/pay-connect.php` | Connect Stripe or Square by OAuth. | MarketPay.php:182-207 (both). P2 adds manual payment ways, tax, shipping, pickup, Seller Agreement, a test purchase, then Go live (0135 Step 4a; 0142 zips 2–3). |
| Square catalog | Import and keep in sync. | MarketSquare.php:27-30. Passes differ on the gate: P1 says plan feature `pos_sync`, P2 says "Basic and up". |
| Offers and orders (single-pass, P2) | Answer offers from the Inbox, mark Sold, check tickets in at the door, download a dispute packet per order. | P2 MarketOffers.php:89-99, 401-426; KindCheckout.php:267-277; 0142 zip 3 |
| People who linked you | Names, plus only the contact fields each person shared, read live, with no export. | P1 Marketing.php:180–212; P2 181-212. P1 adds that insights are counts only (214–234, single-pass). |
| `/listing/view.php?id=` Shop section (single-pass, P1) | The listing's published items, Basic and up (0135 step 3). | Page not shown |
| `trvgo.us/<shop>`, custom domains (single-pass, P1) | Storefronts. | Not built (0135 step 5) |

### Admin (single-pass, P1; P2's text has no admin section)

- **Market 360°** at `/admin/market/`, reached via `/admin/router.php`. It lists Items, Business items, Offers & disputes, Gateways and Seller compliance (0135, 0142).
- **E-Commerce** for Traversence's own sales: `/admin/ecommerce.php` and `/admin/order.php` (0098).

### Routes missing from `pages.md` (single-pass, P1; reconcile under R09)

`/api/market-webhook.php`, `/api/csp-report.php`, `/api/go-codes.php`, `/api/qb-webhook.php`, `/order.php`, and `/admin/market/`.

---

## (b) How it works underneath

### Publishing through the walls (`MarketBiz.php`)

- **Draft and copy.** The catalog item is the walled draft. Publish copies out only the public parts, and cost, markup and supplier stay behind (P1 4–9, 165–175, 234–313; P2 165-175, 234-313).
  - Passes differ on the copied fields: P1 says title, about, kind, **price mode**, price and kind data; P2 says name, description, kind, price and **quantity**, and kind details.
- **Versions.** Each publish writes a numbered version (296–297, both).
- **Caps by plan.** Free 10, Basic 25, Core 100, Strategic 250, Cornerstone 1,000 (line 29). At the cap, the refusal names the plan that raises it (273–276). P1 adds that republishing an item already up is not blocked.
- **Micro-cluster.** Both cite 155–160, but passes differ on the framing: P1 says a listing without one can't publish, P2 says place is taken from it after the checks.
- **Single-pass (P1):** an unpublished-changes hash (186–189, 221–222), and photos limited to 10 from the business's own gallery (39, 346–362).
- **Album.** The public "Marketplace" album is synced at 387–412 (both). P1 adds that it shows only while the item is published.
- **Single-pass (P2):** `canSee()`. The team and staff see everything; others see only published items on listings that aren't suppressed (502-508).

**Publish gates, in order.** Passes differ on the count: P1 lists 10, P2 lists 9.

1. Account standing (236). P2 names it `Standing::guard`.
2. Item is Active and has a name (237–239).
3. Category chosen (241). **Single-pass (P1).**
4. Kind complete and fits the name (244–247).
5. Rulebook (248). P2 adds that prohibited items stop here (248-249).
6. Seller Agreement accepted (252–253).
7. Restricted or confirm answer needed (258–263). P2 says the code throws `MarketRuleAsk` otherwise. The answer is recorded through `Acceptance` (305–310). P1 treats that as part of this gate; P2 lists it after the gates.
8. Hold word. Passes differ on the outcome: P1 says it "goes to review", P2 says it "holds the item" (264).
9. CPSC recall check, which can hold the item (266–270).
10. Plan cap (273).

### Rulebook (`MarketRules.php`)

- **Tiers.** Five tiers: prohibited, review, restricted, confirm, notice (P1 5–13, 22–51, 53; P2 22-51, 53).
- **Head-noun reading.** "Wine rack" passes (62–94). P2 adds that "red wine" asks, using `HEADS`, `JOINS` and `isThing()`.
- **Description.** A prohibited word only in the description sends the item to review (P1 102, 108–113; P2 100-132).
- **Admin word lists.** Blocked and hold lists are read the same way (P1 124–150; P2 139-150).
- **Advance warning.** `forCatalog` gives early warning while the business shapes the item (157–171).
- **Recall check.** 4-second timeout, and a failure never blocks publishing (178–203). P1 names saferproducts.gov.
- **Card payment.** `cardOk` means restricted items can't be paid by card (223).

### Gateways (`MarketPay.php`)

- **Modes.** Live, sandbox, mock or off. Mock is refused anywhere but a local test (41–54, 58–65).
- **Test safety.** A test account on live pays in sandbox, and live keys are refused in test mode (105–114; P2 also 49-52).
- **Square tokens** are sealed with libsodium secretbox (118–137).
- **Stripe** uses an embedded session, card only, expiring at 31 minutes, with deposit, hold and subscription modes (240–275).
- **Square charge** (288–296).
- **Confirming payment.** `confirm()` checks with the gateway and checks the amount (302–319). P2 adds that the return page alone is never trusted.
- **Webhooks.** Stripe signatures must be within 5 minutes; Square uses HMAC (399–417).
- **Account standing** in plain words (336–360). P2 adds that it feeds the Trust score.
- **Single-pass (P1):** OAuth connect and finish (182–207), and Square requires an active location that takes cards (203–204).
- **Single-pass (P2):** capture, refund and subscription status (370-394).

### Checkout by kind (`KindCheckout.php`)

- **Bought alone.** Bookings, rentals and subscriptions are bought alone (P1 20, 153, 178; P2 20).
- **Availability** is counted from the orders themselves (69–91).
- **After payment.** Fulfilment issues ticket codes and download tokens (216–242).
- **Single-pass (P1):** date rules of up to 90 nights or days, plus minimums (94–110), and made-to-order spec questions with a deposit percentage (133–144).
- **Single-pass (P2):** `prepare()` adds kind lines: travel fees, made-to-order specs and deposits, harvest pickup windows, booking dates, guests, lodging tax and house rules, rental wa