# Platform atlas: plans-pricing-money (merged)

*`research/platform-atlas/plans-pricing-money.md`. This is a reconciled draft for human review, merged from two independent passes. Both passes worked only from the attached files.*

**How to read the tags**
- Untagged lines are supported by both passes.
- **single-pass (p1)** or **single-pass (p2)** means only one pass says it.
- **passes differ** means the two passes conflict.
- Where the passes cite different line ranges for the same claim, both are given as p1 / p2.

**Both passes stop partway through section (c).** Pass 1 cuts off at the start of the 0135 row. Pass 2 cuts off inside its 0135 row. Neither pass contains any text for sections (d) or (e), so those sections are left empty below rather than invented.

---

**What this entry is based on**

Docs attached:
- The charter, the brand guide, the PRD and `site-audit/pages.md`.
- Decisions 0098, 0128 and 0166 in full, plus 0101 and 0135 truncated near their end.

Code attached:
- `Plan.php`, `CartPlans.php`, `Wallet.php`, `VendorOrders.php`, `JourneyPlan.php` and `MarketPay.php` in full.
- `Billing.php`, `Orders.php` and `WorkOrders.php`, each truncated.
- Pass 2 places all of this code under `sync-app/lib/` (single-pass (p2)).

Truncated files (anything past the cut is not shown):
- `Billing.php` stops at line 493. Pass 2 notes the cut falls inside `renew` (single-pass (p2)).
- `Orders.php` stops at line 546. Pass 2 notes the cut falls inside refund requests (single-pass (p2)).
- `WorkOrders.php` stops at line 476.

Not attached:
- **`commercial.md`.** Pass 1 adds that it holds the tier prices, according to the charter's Scope section and 0135 (single-pass (p1)).
- **Page code.** No page files were attached, such as `plans.php`, `/qr/cart.php`, `/shop/`, `/market/*`, `/order.php` and `/admin/ecommerce.php`.
- **Supporting classes:**
  - Both passes list `QuickBooksPay.php`, `ClientOrders.php`, `MarketCheckout.php` and `OneOffs.php`.
  - Both also name `AccountDomains` and `Team`. Pass 1 lists them as not attached. Pass 2 lists them as "not shown" (called by the code but absent).
  - `Market.php` appears only in pass 1 (single-pass (p1)).
  - `MarketSquare.php`, `Promotions`, `Costing`, `TestAccounts`, `/api/qb-webhook.php` and `/api/market-webhook.php` appear only in pass 2 (single-pass (p2)).

---

## (a) What each person can do here, page by page

Statuses come from `pages.md` unless noted otherwise.

### Resident and traveler (buyer or visitor)

Pass 1 adds that the attached files show nothing a traveler can do that a resident can't, and that money flows are not split by track (single-pass (p1)).

| Route | What they can do | Status |
|---|---|---|
| `/qr/`, `/qr/cart.php`, `/qr/manage.php` | Buy GO Codes, with a guest's cart kept per browser (0098). Pass 1 adds creating and managing codes, the "Where GO Codes live" section, and that codes are served through `trvgo.us` and switched off by a refund (0098 §4, §4a) (single-pass (p1) in this section; pass 2 covers `trvgo.us` in (c)). | Live |
| `/market/`, `/market/view.php`, `/market/item.php` | **Passes differ on emphasis.** Pass 1: browse posts near a place, message a seller, and Buy now / Place order on a business's item (0135 amendment). Pass 2: people's posts and businesses' items are shown together, newest first (0135 "Step 3 built"). | **Passes differ:** p1 marks `/market/` itself "Live, placeholder"; p2 marks all three Live. |
| `/market/cart.php`, `/market/order.php` | One cart per business, then checkout. Pass 1: through the business's own gateway or one of its manual payment methods (0135 Step 4a). Pass 2: by card through the business's own Stripe or Square, using the gateway's own card fields on our page, or by paying the business directly following its instructions. The two descriptions are compatible. | Live |
| `/market/download.php`, `/market/ticket.php` | A bought download; a bought ticket. | Live |
| `/promo.php` | A promotion's page. Pass 1 adds that the code is not shown (single-pass (p1)). | Live |

### Pages filed differently by the two passes

**`/order.php?t=…`**
- **Passes differ on audience:** p1 lists it under resident/traveler; p2 lists it under member.
- Both agree it is a client's private order page for proofs, approval and payment (0101).
- Both agree it is **not listed in `pages.md`** and is named in 0098's CSP addendum.
- Pass 1 adds an initialled final approval, payment by card, wire, check or cash, and a thank-you page (single-pass (p1)).

**`/shop/` and `/shop/domains.php`**
- **Passes differ on audience:** p1 lists them under resident/traveler; p2 lists them under member.
- Both agree the status is Built, not Live.
- Pass 1 describes them as "the Traversence Shop and Domains", "not shown as live".
- Pass 2 adds the following (single-pass (p2)):
  - `/shop/` is one cart for everything Traversence sells (`CartPlans.php:2-6`), with status note "channel-shop-earned zip".
  - `/shop/domains.php` and `/user/domains/` are where members buy domains and manage the wallet.

### Member (signed in)

**`/plans.php`**
- **Personal plans:** Personal is free, Personal Plus is $4.95 and Personal Pro is $9.95 (`Billing.php:25-27`). Pass 1 says "a month".
- **Yearly billing:** 10 months for 12, applied to the plan only.
  - **Passes differ on cite and placement:** p1 cites `Billing.php:47-52` under member; p2 cites `:48-58` under business.
  - Pass 1 adds that packs are charged at full price (`Billing.php:30-38, 54-58`). Pass 2 lists packs at `:30-38`.
- **Free Pro trial:** "Try Pro free for 7 days", once, with no card.
  - p1 cites `Billing.php:40, 243-258`; p2 cites `:40,155,243-258`.
  - Pass 2 adds that it is once per account and gives no AI credits (single-pass (p2)).
- **Single month:** a month that does not renew. p1 cites `:260-281`; p2 cites `:263-281`.
- **Status:** pass 2 says Live. Pass 1 gives no status.
- Both agree the page code is not shown.

**`/user/orders.php`**
- Shows the member's orders (0101).
- Pass 1 adds that orders sent to the member's email join the account once the email is confirmed (single-pass (p1)).
- Pass 2 adds the status Live, the label "Your orders", and that this comes from the 0101 addendum dated 2026-10-28 (single-pass (p2)).

**`/user/domains/#wallet`**
- The member's wallet. Status: Built.
- A locked card can only be replaced.
- p1 cites `Wallet.php:189-197, 233-248, 392-396`; p2 cites `Wallet.php:392-396` for the link.

**`/user/market/`**
- Selling and Buying.
- "How you take payment" is shown to the accepted buyer (0135 Step 4a).
- Pass 1 adds "Post something", a cap of 5 active posts (0135 §7), Sold /