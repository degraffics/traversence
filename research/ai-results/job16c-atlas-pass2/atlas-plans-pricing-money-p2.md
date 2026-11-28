```markdown
# Platform atlas: plans-pricing-money

*research/platform-atlas/plans-pricing-money.md. Draft for human review. Written only from the attached files. Anything not visible is marked **not shown**.*

**What was attached.** Docs: charter, brand, prd, site-audit/pages, and decisions 0098, 0101, 0128, 0135 and 0166.

Code, all under `sync-app/lib/`: `Plan.php`, `Billing.php`, `CartPlans.php`, `Wallet.php`, `Orders.php`, `MarketPay.php`, `VendorOrders.php`, `WorkOrders.php` and `JourneyPlan.php`.

**Truncated files.** These were cut off, so anything past the cut is not shown:
- `Billing.php`, from line 493 (inside `renew`).
- `Orders.php`, from line 546 (refund requests).
- `WorkOrders.php`, from line 476.
- 0101 and 0135, both near their end.

**Not attached at all:**
- No page code: `plans.php`, `/shop/`, `/qr/cart.php`, `/market/*`, `/order.php`, `/admin/ecommerce.php`.
- No `QuickBooksPay.php`, `ClientOrders.php`, `MarketCheckout.php`, `MarketSquare.php` or `OneOffs.php`.
- No `commercial.md`.

---

## (a) What each person can do, page by page

Routes and statuses come from `site-audit/pages.md` unless noted otherwise.

### Resident / traveler (as a buyer or visitor)

| Route | What they can do | Status |
|---|---|---|
| `/market/`, `/market/view.php`, `/market/item.php` | Browse people's posts and businesses' items together (newest first). See 0135 "Step 3 built". | Live |
| `/market/cart.php`, `/market/order.php` | Use one cart per business, then check out (see below). | Live |
| `/market/ticket.php`, `/market/download.php` | Tickets and bought downloads. | Live |
| `/qr/`, `/qr/cart.php`, `/qr/manage.php` | Buy GO Codes (a guest's cart is kept by browser, per 0098). | Live |
| `/promo.php` | A promotion's page. | Live |

Checkout options on `/market/cart.php` and `/market/order.php`:
- Pay by card through the business's own Stripe or Square, with the gateway's own card fields on our page.
- Or pay the business directly, following its instructions.

### Member (signed in)

**Plans and the cart**
- **`/plans.php`** (Live): plans and prices.
  - Personal plans are Personal (free), Personal Plus ($4.95) and Personal Pro ($9.95) (`Billing.php:25-27`).
  - Packs are listed at `Billing.php:30-38`.
  - Members can try Pro free for 7 days, once per account, with no card and no AI credits (`Billing.php:40,155,243-258`).
  - Members can buy a single month that does not renew (`Billing.php:263-281`).
  - The page code itself is not shown.
- **`/shop/`** (Built, "channel-shop-earned zip"): one cart for everything Traversence sells (`CartPlans.php:2-6`).
- **`/shop/domains.php`** and **`/user/domains/`** (Built): buy domains and manage the **wallet**.
  - The wallet link is `/user/domains/#wallet` (`Wallet.php:392-396`).

**Orders**
- **`/user/orders.php`** (Live): "Your orders" (0101 addendum 2026-10-28).
- **`/order.php?t=`**: a client's private order page for proofs, approval and payment (0101).
  - It is **not listed in pages.md**. It is named as a card page in 0098's CSP addendum.
- **`/user/market/`** (Live): Selling, Buying and offers.
  - "How you take payment" is shown only to the accepted buyer (0135, step 4a).
- **`/api/upgrade.php`** (Built): the upgrade sheet's choices. It only reads.

### Business

**Plans and features**
- Plans are Free, Basic $29.95, Core $79.95, Strategic $229.95 and Cornerstone $399.95 per listing per month (`Billing.php:20-24`).
- Paying yearly costs 10 months for 12, and the discount applies to the plan only (`Billing.php:48-58`).
- Each tool opens at a set plan tier (`Plan.php:15-34`).
- A tool the plan doesn't include shows greyed with "Included in <plan> and up" (`Plan.php:2-3,75-78`).

**The business portal**
- **`/business/`** (Live): the portal.
- **`/business/pay-connect.php`** (Live): connect Stripe or Square.
- **`/business/{id}/domains#wallet`**: the business's wallet (`Wallet.php:395`). This route is **not in pages.md**.
- The E-commerce setup module follows these steps (0135 step 4a):
  1. Getting paid.
  2. Sales tax.
  3. Shipping.
  4. Hold days.
  5. Test purchase.
  6. Go live.

**Payment rules for businesses**
- Manual payments are open from Free (`Plan.php:18`).
- Card checkout opens from Core, at "0% system processing fees" (`Plan.php:25`).
- Catalog publishing is capped by plan: 10 / 25 / 100 / 250 / 1,000 (0135 §7).

**Client orders, built by staff**
- A business can be a client of orders Traversence builds for it, with proofs, approval, deposits and wire, check or cash payment (0101).

### Vendor (outside party)

- **`/vendor.php?v=`** and **`/wo.php?w=`** (Live) show work orders.
  - Each link is private and lasts 30 days (`WorkOrders.php:18`).
  - "No prices or client billing ever go to a vendor" (`WorkOrders.php:6`).

### Staff and admin

**E-Commerce**
- Pages: `/admin/ecommerce.php`, `/admin/order.php` and `/admin/client-order.php` (all Live).
- 0101 says `/admin/client-order.php` now forwards to `/admin/backoffice/orders/<id>`. That route is **not in pages.md**.
- `/admin/sales.php` and `/admin/products.php` are Live and listed as "a working tool, archive.md".
- Order actions available: fix, credit, refund, record a chargeback, check payment, cancel unpaid (0098 §5).

**Marketplace administration**
- Market 360° lives at `/admin/market/` per 0135.
- It is **not in pages.md** as a route. pages.md lists `/admin/router.php` for modules.

---

## (b) How it works underneath

### Plans and features: `Plan.php`
- There are five tiers (`:12`).
- Each feature has a starting tier (`:15-34`).
- Staff can override a starting tier in the `plan_features` table, or switch a feature `off` (`:38-60`).
- `allows()` compares where the listing's tier sits against the feature's starting tier (`:66-72`).

### Billing: `Billing.php` (truncated after :493)

**Catalog of plans and packs**
- `PLANS` holds each plan's name, account type, price in cents, storage GB, AI credits and tier (`:19-28`).
- `ADDONS` holds the packs (`:30-38`).

**Starting a plan**
- `start()` puts the card on file through the wallet (`cardFrom`, `:309-316`), charges the first period, then locks the card to the plan (`:210`).
- If the processor gives no answer, the plan is kept so a retry reuses the same charge key (`:199-207`).

**Changing a plan or adding packs**
- An upgrade is charged now, prorated (`:353-354`).
- A downgrade, or a move to Free, takes effect at renewal (`:349-351`).
- A pack is charged for the rest of the current period (`:384-398`).

**Charging**
- `charge()` is idempotent: it checks `req_key` against charges already paid (`:423-424`).
- A test account's charge is forced into the QuickBooks sandbox (`:428-433`).
- It writes a QuickBooks Sales Receipt (`:441`) and emails a receipt (`:448-450`).

**Renewals**
- A declined renewal is retried after 3 and then 7 days (`:41,485-488`), then ends.
- A processor that doesn't answer (503) is retried the next day with the same key (`:484`).

### One cart: `CartPlans.php`
- A plan placed in the cart replaces any other plan already in the cart for that account. A pack adds one (`:21-38`).
- `groups()` sorts the cart by account and decides how each account pays (`:47-94`):
  - `new`: a new card starts a plan.
  - `file`: the card on file pays.
  - `none`: it can't be paid yet, with the reason.
- Domains and one-off purchases are folded into the same cart (`:53-61,89-92`).
- `pay()` stops at the first failure and reports what already went through (`:104-130`).

### Wallet: `Wallet.php` (0166 §6a)

**What is stored**
- Only the processor's card reference and a label. The card number is never stored (`:3-5`).
- Card refs are QuickBooks-only in the shown code (`:148,158`).

**Locking and replacing cards**
- A card with something renewing on it is locked and can't be removed (`:190-197`).
- A locked card can be replaced, and replacing it retries any declined renewal right away (`:233-263`).

**Test versus live**
- Live and test wallets never mix (`:62,153,185`).
- A test card is charged only in the sandbox, and a live card is refused while testing (`:292-306`).

**Change of owner and expiry**
- When a business changes owner, the previous owner's cards leave its wallet (`:273-283`).
- A locked card is reminded 30 days before it expires (`:369-389`).

### Orders and ledger: `Orders.php` (truncated after :546; 0098)

**The ledger**
- Ledger kinds: charge, deposit, refund, credit issued, credit used, chargeback, chargeback reversed (`:25-26`).
- The ledger is add-only and idempotent through `request_key` (`:75-84`).
- Order status follows the money (`settle`, `:113-123`).
- A part-paid deposit stays pending until the order is paid in full (`:119`).

**Fix, credit, then refund**
- Fix (GO Codes only) is at `:440-452`.
- A credit creates a QuickBooks credit memo (`:167-179`). Credits last 90 days, oldest used first (`:134,140-156`).
- A refund goes back to the card through QuickBooks Payments, then a Refund Receipt is made (`:211-226`).
- A refund requires a reason and is capped at what's left to refund (`:198-204`).
- From M2, refunds wait for "Jason's OK" before money moves (`:520-525`). The rest of that code is truncated.

**Disputes and QuickBooks sync**
- A chargeback pauses what the order delivered. Winning restores it; losing takes it back (`:411-434`).
- Refunds and credits made in QuickBooks are matched to orders automatically, or wait in an inbox for a person to place (`:259-306`).
- QuickBooks work is refused on an order made in the other mode, sandbox or production (`:182-189`).

### Business gateways: `MarketPay.php` (0135 step 4)

**Connecting**
- Stripe Connect (Standard accounts) or Square OAuth (`:2-6,182-207`).
- Square tokens are sealed with libsodium (`:118-137`).

**Safety guards**
- Mock mode is refused outside a local test (`:41-65`).
- Test mode never uses a live key (`:105-114`).

**Checkout**
- Stripe sessions are card-only (`:244`).
- Sessions expire at 31 minutes so they close before the stock hold ends (`:246`).
- Payment modes: deposit (`:261-263`), held funds (`:265`) and subscription (`:250-260`).
- No platform fee parameter appears in the Stripe session parameters (`:244-270`), which is consistent with "we take 0%" (`:5`).

**Confirming payment**
- Paid status is confirmed with the gateway, never from the return page alone (`:302-319`).
- Webhook signatures are checked: Stripe within a 5-minute window, Square by HMAC (`:399-417`).

### Vendors and production (staff only)

**`VendorOrders.php`**
- Clients never see vendor names, vendor statuses or costs (`:4-6`).
- Margin is shown to staff (`:156-163`).
- Vendor status moves our items forward only (`:344-368`).
- An item never goes out the door before the order is paid in full (`:353`).

**`WorkOrders.php`**
- A work order is refused while the order isn't paid in full ("model 1.9", `:341`).
- Orders handled outside Traversence are processed at `:301-315`.

### Not shown

Any of the following that the code above calls:
- `QuickBooksPay`
- `ClientOrders`
- `MarketCheckout`, `MarketSquare`, `OneOffs`, `AccountDomains`
- `Promotions`, `Costing`, `Team`, `TestAccounts`
- the webhooks `/api/qb-webhook.php` and `/api/market-webhook.php`

---

## (c) Decisions

| # | What it rules |
|---|---|
| **0098** | One order model for everything sold, with an add-only ledger.<br>QuickBooks keeps the books.<br>Three kinds of commerce:<br>• Traversence's own sales.<br>• Business storefronts: the business's processor, "never custody".<br>• Member-to-member (C2C): settled offline.<br>"Fix it, credit, then refund."<br>All GO Codes route through trvgo.us, and a refunded code returns 404.<br>Card form via Intuit tokens (SAQ A-EP).<br>Sandbox/production switch.<br>Pricing formula: COGS × markup.<br>Credits last 90 days (changed from 12 months).<br>PCI CSP list. |
| **0101** | Orders staff build for a client: catalog, proofs, approval, deposits, work-order flow.<br>Wire offered only on orders of $2,500 or more, with code-gated, stamped viewing.<br>Payment hold before production.<br>Files are released only once paid in full. |
| **0128** | Three roles, each set separately: processor, bookkeeper, order/CRM.<br>System tags link products to QuickBooks items by id.<br>Pull or merge a QuickBooks invoice into an order.<br>Steps 1–3 built. |
| **0135** | The master record for the Marketplace and Storefronts.<br>"No payment ever passes through Traversence."<br>No paid or engagement ranking.<br>Catalog caps by plan; offer/expiry rules.<br>Seller sets the price; the buyer orders (Buy now / Request a