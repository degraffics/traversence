# 0166: Provider-neutral payments and bookkeeping: our own ledger, connectors, and QuickBooks set up once

**Note (2026-11-16, decisions/0169):** built as apps in the Shop's App store, after 0169's first release (see its build order).

Status: accepted (Jason, 2026-11-16: "set decision, and let's put this in a queue for future work"). §6a, the wallet, built 2026-11-17 with
Domains (release #71, `app/lib/Wallet.php`); the rest not built; queued
in `handoffs/session-manager.md` for after the usage reset.
Builds on: 0095 (GO Codes, QuickBooks checkout), 0096 (the QuickBooks connector), 0128 (QuickBooks invoices and
products), 0141 (Market 360, its gateways), 0145 ("no test moves real money": `QuickBooksPay::guardReal()`,
`testHere()`), 0154 (the community budget), 0163 (operations: the service register, costs).

## The problem

Today the site talks to one bookkeeper (QuickBooks Online) and records money through one code path. When it needs an
account, it guesses:
- a new QuickBooks product gets `QB_INCOME_ACCOUNT_ID`, or **the first active Income account** QuickBooks returns;
- a refund comes from `QB_REFUND_ACCOUNT_ID`, or **the first active Bank account**.

Nobody is asked, and a product's cost isn't recorded, so Gross Profit is wrong. Payments and bookkeeping are mixed in
one place, so changing a processor or a bookkeeper means changing code. Jason, 2026-11-16: "Our system should
function no matter what plan or processor it uses."

## Decision

### 1. Two separate jobs

- **Bookkeeping belongs to the product.** It says what a sale earned and what it cost.
- **Payment processing is set up once, apart from products** (Jason: "payment processing vs bookkeeping"). It says
  how money moves: which processor takes it, where it lands, the fees, the payouts.
- **They meet only at a sale or a refund.** A refund reverses the product's income and cost (bookkeeping), and its
  money goes back through the processor that took it (processing).

### 2. Our own ledger is the record

- The site keeps **its own double-entry ledger** of every sale, cost, tax collected, fee, refund and payout. Every
  entry balances.
- It keeps each one's provider and the provider's own id, so a webhook delivered twice is recognised and **never
  posted twice**.
- The ledger is the true record. A bookkeeper connector **posts** to the connected bookkeeper from it.
- **With no bookkeeper connected,** the site's own reports still work, and an export goes to an accountant.

### 3. Bookkeeper connectors

- There's **one connector shape**, and QuickBooks Online is the first connector. Others (Xero, Wave and so on) are added
  only when wanted.
- **The plan is detected, not asked.** The connector reads the company's settings on connecting and at each check:
  inventory tracking, classes, locations, sales tax. It adapts:
  - no inventory: Non-inventory products, still with income and cost;
  - no classes or locations: those tags stay in our ledger, so reports by hub still work on the site;
  - a plan change is noticed at the next check, and nothing breaks.
- **Sales tax stays the bookkeeper's or processor's job.** We never calculate tax rules ourselves.

### 4. The QuickBooks setup step (once, and changeable)

When QuickBooks is connected, the admin page walks through the setup once. The choices are saved in the site's
settings, not `.env`, and every change is logged.

1. **Who manages the chart of accounts and products?**
   - **A, Traversence manages:** the site suggests and creates the accounts and QuickBooks products for its revenue
     streams. It matches existing accounts by type and name first, and creates only what's missing, with the right
     account type and detail type, numbered only if QuickBooks numbering is on.
   - **B, QuickBooks is the master:** the site never creates. It reads the chart and products, and you match each line
     of "what we need" to your own.
   - Traversence itself starts in **B**.
2. **The starter chart** (what A creates, and what B asks you to match):
   - **Income (4000s):** Subscriptions and plans, GO Codes, Storefront and Market 360 services, Advertising and
     promotions, Custom work, Gift certificate sales, Platform fees, **Domain purchases**, **Domain renewals** (Jason, 2026-11-17: two products); plus **Refunds and allowances** as contra-income,
     so Net Sales is right.
   - **COGS (5000s):** Printing, Shipping and fulfilment, Subcontractors, **Domain registrations** and **Domain renewals**
     (Name.com's cost per domain, the vendor Name.com).
   - **Expenses (6000s):** Merchant fees, Software and hosting (a sub-account per service), AI services, Community
     (0154), Advertising.
   - **Assets:** one clearing account per processor, and Undeposited Funds.
   - **Liabilities:** Sales Tax Payable (the bookkeeper's own), and Funds held for businesses, only if money is ever
     held for them.
3. **Deposit and refund bank account,** and a check that sales tax is on.
4. `QB_INCOME_ACCOUNT_ID` and `QB_REFUND_ACCOUNT_ID` are **retired**. `.env` keeps only the keys: `QB_ENV`,
   `QB_CLIENT_ID`/`SECRET`, `QB_SANDBOX_…`, the webhook tokens.

### 5. The catalog owns the product's link

Jason: "this should be addressed by when a product is created". Each catalog product mirrors a QuickBooks product:

- **Type:** Service, Non-inventory, Inventory or Bundle, as the plan allows. The type decides the fields.
- **Sales:** name, SKU, category, price, **income account**, and taxable or a tax category.
- **Cost:** cost amount, **COGS or expense account**, vendor (Jason: "products have costs … 1. the income 2. the
  expense"). Inventory adds the asset account, quantity on hand and reorder point. A bundle lists its products.
- **On save:**
  - in **A**, the QuickBooks product is created;
  - in **B**, it's picked from QuickBooks' own list, and its type and accounts are read from there.
- **A product with no QuickBooks link can't go on sale.** The hold at sale time stays only as a safety net, as one
  step in "The engine needs a person"-style Pulse case.

### 6. The payments layer and routing

- **One connector per processor:** QuickBooks Payments, Stripe and Square to start. Products never name a processor.
- **Routing rules** on an admin page decide which processor takes each payment:
  - by kind of payment;
  - by who's selling: Traversence, or a business on its own Stripe or Square account, as in 0141;
  - with **a fallback** when one is down or refuses.

  **Switching provider is a rules change, not a code or catalog change.**
- **Each processor has its own clearing account,** so a payout matches its bank deposit.
- **Rules that make switching safe:**
  1. Every payment keeps its provider, and **its refund always goes back through the same one**, even after a switch.
  2. **Saved cards and subscriptions stay with the processor that stored them.** The site holds only the processor's
     tokens, never card numbers, so we stay outside full card-security (PCI) scope. On a switch, existing subscribers
     stay on the old processor until renewal or are asked once to re-enter their card. New customers use the new one.
  3. The old processor stays connected, receiving only, until its last refund window and payout close.
- **A business's own sales** (Stripe Connect, Square) are **its money**. Only our platform fee is our income.
- **"No test moves real money"** (0145) applies to every connector: `guardReal()`/`testHere()` sit in the layer, not in
  one processor's code.

### 6a. The wallet (Jason, 2026-11-17)

Jason: "Payments should have a payment wallet like system where multiple payment methods can be held. When a
subscription or product like domain renewals are purchased that card will be assigned to its future use for renewals.
It also locks the card from being removed but it can be replaced with a new card."
- **Each account has its own wallet:** a member's, and a business's (managed by its owner, or by its accountant role
  if the owner allows); they never mix. Many saved cards, one the **default**, offered first at checkout. Only the
  processor's card reference and a label ("Visa ··4242") are kept, never the number.
- **Every purchase that renews is assigned a card** (a plan, a domain's renewals, a paid app).
- **An assigned card is locked:** it can't be removed, only **replaced**, for one item or for everything on it. It
  unlocks when nothing renews on it any more (cancelled, auto-renew off, a domain transferred away).
- **Expiry:** a reminder 30 days before a locked card expires, with Replace card. A declined renewal is retried 3 and
  7 days later; a new card retries at once.
- **Each card stays with the processor that saved it** (§6, rule 2).
- **Built first by the Domains build** (0169 §1a, 2026-11-17) as a shared `Wallet` library; plans move onto it with
  this decision's payments layer (step 3), so nothing working today changes.
- **Note (Jason, 2026-11-17: "this should not be different, it is a payment method from wallet"):** plans pay from the
  wallet in the same build, not later. Each running plan's card is copied into its account's wallet and assigned to the
  plan (the migration adds rows only); starting a plan or changing its card goes through the wallet (a saved card or a
  new one), and `subscriptions.card_id` / `card_label` stay, kept equal to the assigned card, so renewals work as
  before. A business changing owner takes the previous owner's cards out of its wallet. Built as `app/lib/Wallet.php`
  (release `domains-wallet`, handoffs/domains.md).
- **Note (Jason, 2026-11-17: "this should have a test/sandbox version that is used by the system for testing to ensure
  no actual live payment is ever available"):** two wallets per account, never mixed: the live wallet and the test
  wallet (cards saved in QuickBooks' sandbox). Whenever the system is testing (a test account, domains' test mode, the
  staging copy, a test account signed in, or payments switched to Sandbox) only the test wallet is seen, saved to and
  charged; a live card is refused, and a test card is charged only with QuickBooks forced to its sandbox (refused if it
  still reads production). A test account has only a test wallet; each wallet has its own default.

### 7. Running costs (optional, later)

The Costs page (0163, Release A) knows each service's monthly spend. It may post those costs to the bookkeeper as bills
(Software and hosting, AI services), so the P&L's overhead fills in by itself. Optional, after steps 1–3.

## Build order (each a release Jason reviews)

1. **The ledger and a QuickBooks connector doing exactly what today's code does.** Run it beside today's path and
   compare the two before anything relies on it. Strict tests for balancing and for posting only once.
2. **The QuickBooks setup step:** A or B, the starter chart, the bank account, the plan's features detected, and the
   catalog's type, income, cost and link (§4, §5).
3. **The payments layer:** connectors, routing rules, the fallback, refunds through the original provider, clearing
   accounts (§6).
4. **Further connectors and running-cost bills,** only when wanted (§3, §7).

## Consequences

- No account is chosen by guesswork; a missing link is a Pulse step, never a silent default.
- A change of QuickBooks plan, bookkeeper or processor is a settings change, and history stays in our ledger.
- Gross Profit and Net Sales are correct by construction: income and cost per product, refunds as contra-income.
- More to build than today's single path. The ledger must be exactly right, so step 1 runs side by side with today's
  code before taking over.
- Customers re-enter a card when we change processor for them. That's the price of never holding card numbers.

## Note (Jason, 2026-11-20): two layers; QuickBooks stays primary for the books

Confirmed with the order rulings (`research/order-rulings-2026-11-20.md`, §3): "The payment processing layer (the app +
QuickBooks Payments) handles card charges, deposits, part payments, refunds and webhooks, updating `order_ledger` as the
real-time operational transaction log that drives order states and production release gates. The books (QuickBooks
Online) remain the authoritative accounting record for invoices, taxes, credits and financial reporting. Because 0166
§2's full double-entry ledger is not yet built (only the wallet is live) and QuickBooks holds years of accurate, verified
data, QuickBooks stays primary for the books unless and until an internal double-entry ledger is completely built,
reconciled in parallel against QuickBooks, and explicitly promoted." So §2's "the site's own double-entry ledger is the
true record" waits on that promotion; until then `order_ledger` is the operational log and QuickBooks the books.
