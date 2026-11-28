# Platform atlas: plans-pricing-money

*Path: `research/platform-atlas/plans-pricing-money.md`. Draft for human review, dated from the attached files only.*

**What this entry is based on.** These sources were attached in full:

- `Plan.php`, `CartPlans.php`, `Wallet.php`, `VendorOrders.php`, `JourneyPlan.php` and `MarketPay.php`.
- Decisions 0098, 0128 and 0166.
- The charter, the brand guide, the PRD and `site-audit/pages.md`.

These were cut off partway, so anything past the cut is not shown:

- `Billing.php` stops at line 493.
- `Orders.php` stops at line 546.
- `WorkOrders.php` stops at line 476.
- Decisions 0101 and 0135 are truncated.

These are cited by the attached files but were not attached:

- `commercial.md`, which holds the tier prices according to the charter's Scope section and 0135.
- `QuickBooksPay.php`, `ClientOrders.php`, `AccountDomains.php`, `OneOffs.php`, `Market.php`, `MarketCheckout.php` and `Team.php`.
- Every page file, such as `plans.php` and `qr/cart.php`.

---

## (a) What each person can do here, page by page

Statuses below come from `pages.md`. Where a decision document says something different, that conflict is flagged.

### Resident and traveler (guest or member)

The attached files contain nothing that a traveler can do and a resident can't. Money flows are not split by track.

| Route | What they can do | Status |
|---|---|---|
| `/qr/`, `/qr/cart.php`, `/qr/manage.php` | Create, buy and manage GO Codes. A guest's cart is kept per browser (0098, "Where GO Codes live"). Codes are served through `trvgo.us`, and a refund switches a code off (0098 §4, §4a). | Live |
| `/market/`, `/market/view.php`, `/market/item.php` | Browse posts near a place and message a seller. Buy now / Place order on a business's item (0135 amendment). | Live; `/market/` itself is "Live, placeholder" |
| `/market/cart.php`, `/market/order.php` | One cart per business, then checkout through that business's own gateway or one of its manual payment methods (0135 Step 4a). | Live |
| `/market/download.php`, `/market/ticket.php` | A bought download; a bought ticket. | Live |
| `/order.php?t=…` | A client's private order page: approve proofs (initialled final approval), pay by card, wire, check or cash, then a thank-you page (0101). | **Not listed in `pages.md`**. Cited in 0101 and in 0098's CSP addendum. |
| `/shop/`, `/shop/domains.php` | The Traversence Shop and Domains. | Built, not shown as live |
| `/promo.php` | A promotion's page. | Live; code not shown |

### Member (signed in)

| Route | What they can do |
|---|---|
| `/plans.php` | Choose a plan. Personal is free, Personal Plus is $4.95 a month, Personal Pro is $9.95 a month (`Billing.php:25-27`). Yearly billing is 10 months for 12, and applies to the plan only (`Billing.php:47-52`). Storage and AI packs are added at full price (`Billing.php:30-38, 54-58`). The member can also take "Try Pro free for 7 days" once, with no card (`Billing.php:40, 243-258`), or buy a single month that doesn't renew (`Billing.php:260-281`). Page code not shown. |
| `/user/orders.php` | Every order prepared for them. Orders sent to their email join the account once the email is confirmed (0101). |
| `/user/domains/#wallet` | The wallet: saved cards and a default card. A card that something renews on is locked and can only be replaced (`Wallet.php:189-197, 233-248, 392-396`). Status: Built. |
| `/user/market/` | Selling, Buying, Post something. Up to 5 active posts (0135 §7). Sold / Got it confirmation, trust points, notes, and "How you take payment" for an accepted buyer (0135 Steps 2 and 4a). |

### Business (owner or team)

| Route | What they can do |
|---|---|
| `/plans.php`, `/listing/businessportal.php` | Choose a listing plan (`Billing.php:20-24`), shown in the table below. A 30-, 60- or 90-day trial given by staff or a promo ends on Free unless the owner keeps it (`Billing.php:39, 217-239`). |
| `/business/` (portal) | Tools are gated by plan. A tool the plan doesn't include shows greyed, with a note such as "Included in Core Partner and up" (`Plan.php:2-3, 75-78`). E-commerce setup, Catalog, Publish and Square sync are in 0135. The business wallet is at `/business/{id}/domains#wallet` (`Wallet.php:395`); that exact route is drawn by the router and is not listed in `pages.md`. |
| `/business/pay-connect.php` | Connect the business's own Stripe or Square account by OAuth (`MarketPay.php:181-207`). |

Listing plan prices (`Billing.php:20-24`):

| Plan | Price | Storage | AI credits a month |
|---|---|---|---|
| Free | $0 | 2 GB | 0 |
| Basic Partner | $29.95/mo | 10 GB | 10 |
| Core Partner | $79.95/mo | 25 GB | 50 |
| Strategic Partner | $229.95/mo | 100 GB | 250 |
| Cornerstone | $399.95/mo | 250 GB | 500 |

### Vendor (not one of the four audiences, but part of this area)

`/vendor.php` and `/wo.php` show work orders with their items, specs and files. They never show prices or client billing (`WorkOrders.php:2-6, 207-215`).

### Staff

- **E-Commerce:** `/admin/ecommerce.php` and `/admin/order.php`. Actions are Fix, Credit, Refund, Card dispute and Pause.
- **Client orders:** `/admin/client-order.php`, which forwards to `/admin/backoffice/orders/<id>` (0101).
- **Market 360°:** `/admin/market/` (0135). This route is not in `pages.md`'s admin table; it is reached through `/admin/router.php`.

---

## (b) How it works underneath

### Plans and feature gates

- **`Billing::PLANS` / `ADDONS`** hold price, storage and AI credits (`Billing.php:18-38`).
- **`Plan::FEATURES`** maps each feature to the plan it starts at (`Plan.php:15-34`). Staff can override this in the `plan_features` table (`Plan.php:38-60`). `allows()` compares the plan's rank with the feature's start (`Plan.php:66-72`).
- **Gap:** 0135 §7 says catalog caps become an "items allowance" in `Billing::PLANS`. The `PLANS` shown has no such column (`Billing.php:18-28`). Where the caps are actually enforced is not shown.

### Subscriptions

- **Starting a plan.** `Billing::start` takes a card from the wallet or a new token (`Billing.php:180, 309-316`) and charges the first period.
  - The charge has a key, `sub<id>-start-<day>` (`Billing.php:198`).
  - If the processor gives no answer, the plan is kept so a retry reuses the same key (`Billing.php:199-208`).
  - The card is then assigned to the plan and locked (`Billing.php:210`).
- **Charging.** `charge()` won't repeat a paid key (`Billing.php:423-424`). It charges the card on file through `QuickBooksPay`, writes a Sales Receipt, and emails a receipt (`Billing.php:420-452`). A test account's charge goes to the sandbox (`Billing.php:428-433`).
- **Plan changes.** An upgrade is prorated and takes effect now. A downgrade waits for the renewal (`Billing.php:341-358`).
- **Renewals.** A decline is retried after 3 and then 7 days (`Billing.php:41`). The account then drops to Free (`Billing.php:7-9, 483-488`). The rest of `renew()` is not shown.
- **Tier sync.** `syncTier` sets `entities.plan_tier` (`Billing.php:10`). Its body is not shown.

### One cart

`CartPlans` puts plans, packs, one-offs and domains in the same cart as GO Codes (`CartPlans.php:2-6`).

- Lines are grouped by account. Each group needs either a new card, the card on file, or is blocked with a reason (`CartPlans.php:47-94`).
- Groups are paid in order and stop at the first failure, saying what was done before it (`CartPlans.php:104-130`).

### Wallet (0166 §6a)

- It stores only the processor's card reference, a label and the expiry month (`Wallet.php:3-5`).
- A member's wallet and a business's wallet never mix (`Wallet.php:150`). The test and live wallets never mix either (`Wallet.php:14-20, 153, 292-306, 333-338`).
- Assigning a card to a renewing item locks it (`Wallet.php:211-219`). The card unlocks by itself once nothing renews on it (`Wallet.php:92-111`).
- On a change of owner, the previous owner's cards leave the business's wallet (`Wallet.php:272-283`).
- A daily reminder goes out 30 days before a locked card expires (`Wallet.php:368-389`).

### Orders and ledger (0098)

- **Tables:** `orders`, `order_lines`, `order_ledger` (add-only) and `order_events` (`Orders.php:5-12`).
- **Recording money.** `record()` uses `INSERT IGNORE` on `request_key`, so a repeat does nothing (`Orders.php:75-84`).
- **Status follows the money** (`Orders.php:113-123`). A cancelled payment record is subtracted from what's paid (`Orders.php:87-103`).
- **Store credit** lasts 90 days and the oldest credit is used first (`Orders.php:133-156`).
- **Refunds:**
  - A refund from the order page goes through QuickBooks Payments plus a Refund Receipt (`Orders.php:211-226`).
  - It requires a reason and is capped at the net paid less credits (`Orders.php:191-204`).
  - Webhook or checked refunds made in QuickBooks are recorded automatically, or sent to the inbox for a person (`Orders.php:259-306`).
  - Refunds wait for Jason's OK (`Orders.php:520-525`). The code after line 546 is not shown.
- **Chargebacks** pause what the order delivered. A won dispute restores it; a lost one takes it back (`Orders.php:410-434`).
- **Fix** corrects a GO Code in place (`Orders.php:440-452`).
- **Cancelling** an unpaid order also voids its QuickBooks invoice (`Orders.php:477-493`).

### Client orders and vendors (0101, 0106 not shown)

- `VendorOrders` keeps vendor jobs and costs. Costs and margin are shown to staff only (`VendorOrders.php:2-6, 155-163`). It parses pasted 4OVER pages (`VendorOrders.php:198-265`). Item status only moves forward and never out the door before payment (`VendorOrders.php:338-368`).
- `WorkOrders::create` requires the order to be paid in full before the vendor gets it (`WorkOrders.php:340-341`) and a total cost before sending (`WorkOrders.php:359`).

### Business gateways (0135 Step 4)

- `MarketPay` holds no money and takes 0% (`MarketPay.php:2-6`).
- Mock mode is refused on the server (`MarketPay.php:41-65`). A live key is refused in test mode (`MarketPay.php:104-114`).
- The Stripe Checkout session expires at 31 minutes, before the item hold ends (`MarketPay.php:246`).
- Payment is confirmed with the gateway, never from the return page alone (`MarketPay.php:298-319`).
- Webhook signatures are checked first (`MarketPay.php:398-417`).
- Square tokens are sealed (`MarketPay.php:116-137`).

### QuickBooks mapping (0128)

`SystemTags`, `Books`, `QbPull` and `qb-merge.php` are cited in 0128. Their code is not shown.

---

## (c) Decisions

| # | What it rules | Shown? |
|---|---|---|
| **0098** | One order system with an add-only ledger, QuickBooks for Traversence's own sales and books, and the "Fix it, credit, then refund" policy. All GO Codes route through `trvgo.us`, and a refund or lost chargeback switches a code off. Three kinds of commerce: Traversence (our ledger), business storefronts (their processor, no custody), and member-to-member (offline). Card form on our page (SAQ A-EP), CSP on card pages, and a cancel-the-record table. | Yes |
| **0101** | Staff-built client orders: catalog with run-size tiers and markup, proofs with signed final approval, deposits, wire/check/cash, payment hold, files released only on payment in full, projects and bundles, sandbox order numbering. | Yes (truncated) |
| **0128** | Three separate roles: processor, bookkeeper, order/CRM. Pull or merge QuickBooks invoices into orders. Products tagged to QuickBooks items by id, never by name. | Yes |
| **