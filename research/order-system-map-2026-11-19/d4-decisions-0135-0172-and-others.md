# d4: The order system as decided (Marketplace, Storefronts, Market 360°, payments, Shop, communications)

Source: `/home/user/traversence/decisions/`. Status column from `decisions/index.md` (generated 2026-11-19 07:17 UTC).
Read in full: 0135, 0139, 0141, 0145, 0150, 0154, 0155, 0166, 0167, 0169; 0172 §2.4, §4 in full and every order mention.
Excluded from this map (covered elsewhere): 0035 0095 0096 0098 0099 0101 0102 0105 0106 0107 0108 0109 0110 0111 0112
0113 0116 0118 0119 0120 0121 0122 0123 0124 0127 0128 0129 0130 0142.

Two kinds of selling, kept apart throughout:
- **Traversence's own sales** (GO Codes, plans, packs, one-offs, domains, print, client orders): we are the merchant,
  charged through QuickBooks Pay, recorded on our order system (0098).
- **A business's Marketplace / storefront sales**: the business's own money through its own Stripe or Square (or its
  manual ways); Traversence takes 0%, never holds or routes it, and "only record[s] the order on our ledger (0098)".

---

## 0135 The Marketplace and Storefronts (the one record)

- **Date:** accepted 2026-11-01 (Jason); amendments and build notes through 2026-11-13.
- **Status (index):** accepted. Replaces 0031 and 0032 (index "Replaced by 0135").
- **Built:** steps 1, 2, 3, 4a, 4b built ("Step 1 built", "Step 2 built", "Step 3 built", "Step 4a built", "Step 4b
  built (2026-11-01)"); 4a "Built in a mock mode … real payments wait for Jason's one-time setup"; follow-ups
  2026-11-02 ("With this, 4b's follow-ups are all done"). Step 5 is 0139.

**Decides about orders**
- Master record: "the master record for the Marketplace, Storefronts and the business catalog they show. Where an older
  record says otherwise, this one rules."
- **No money through Traversence:** "No payment ever passes through Traversence (0031, Jason 2026-11-01). We never stand
  between a buyer and a seller's gateway, hold money or act as the merchant of record."
  - People: "pay each other offline; dual confirmation is on the site."
  - "Manual payments on every plan (Zelle, bank transfer, check, pay on pickup), marked paid by the business … Free
    businesses can sell."
  - "Card checkout from Core, through the business's own gateway, with the gateway's own card fields on our page …
    no card data on our page or server, no cut taken. We only record the order on our ledger (0098)."
  - "Square and Stripe first … PayPal / Venmo next if buyers ask; QuickBooks Payments and others when a business asks."
- **One catalog:** "A business's items are its catalog (0116) … No second item record is built for businesses." Cost,
  markup, supplier private. "Single items only (0032): no product variants yet."
- **Catalog caps** are a plan allowance in `Billing::PLANS`: Personal 5 / Free 10 / Basic 25 / Core 100 / Strategic 250 /
  Cornerstone 1,000 per listing; "Active items only"; at cap "greyed out with the reason … Nothing already showing is
  taken down." "No item packs."
- **Offers (0032, step 2):** own record with stages "Asked → Countered → Accepted → Both confirmed → Completed;
  Declined, Withdrawn, Disputed"; "Completed needs both: the seller taps 'Sold', the buyer taps 'Got it'." Records
  `market_offers`, `market_offer_events`, `market_notes` (`MarketOffers.php`); 7-day lapse; disputes to Market 360° →
  Offers & disputes and a Pulse Safety case with a Settle form.
- **Reviews:** "A comment from someone with a completed offer or a paid order is marked as such."
- **Post lifetime:** "Offer this for" defaults (personal 30 days, personal service 90, business Ongoing); one check
  every 30 days; "Expired: off the Marketplace and the Storefront, kept (never deleted)"; tracked stock sold out "comes
  down by itself".
- **Test posts:** "a team account … posts with Test post ticked"; "Only our team sees a test post"; "counts in no numbers".
  Notes "the site's sandbox is the QuickBooks switch for our own orders".
- **Step 3, publishing:** "the catalog item … is the draft. Publish copies only its public parts into `market_items`";
  "Cost, markup and supplier never leave the walls"; numbered versions `market_item_versions`. Buyer buttons: Message,
  Make an offer (off by default), Request a quote (Basic+; "the quote made in Quotes & invoices"), Buy now.
- **Step 4 decided (checkout):**
  - Connecting: "each business signs in to its own Stripe (Connect, Standard accounts) or Square (OAuth) … nothing is
    pasted"; tokens "sealed"; sandbox/mock mode.
  - "Checkout on our page, the gateway's card fields"; "The PCI questionnaire is the business's, as the merchant".
  - **One cart per business:** "Buy now and a cart, one per business: each business is paid through its own gateway, so
    a cart never mixes businesses ('Your order from …'). Both go to the same checkout."
  - E-commerce setup steps: Getting paid → Sales tax → Shipping & pickup → What buyers can do → "a test purchase ($1,
    refunded)" → Go live.
  - Webhooks: "the signature checked first … each event once … the payment confirmed with the gateway before anything is
    marked paid (never the return page alone) … a daily reconcile of orders still waiting. Only then does the server
    write the paid order and the customer into the business's own records".
  - Stock: "held for about 30 minutes while someone is checking out, taken off when paid (quotes and manual orders marked
    paid too)"; Square catalog "Square's, both ways".
  - Zelle: "stays a manual method … marked paid by the business"; for people, payment ways "shown only to the buyer whose
    offer they accepted, with the warning that these have no buyer protection."
- **Amendment "the seller sets the price; the buyer orders" (2026-11-01):** "This replaces Make an offer on businesses'
  items and closes the gap where an accepted offer never became an order."
  - Buy now "on every plan": pick quantity/options and pickup or delivery; "The buyer taps Place order; nothing is
    negotiated here"; "It becomes an order in the business's Orders (0098, the business's walled records), and the buyer
    gets it by email (sent for the business, 0137 §8)".
  - Paying: "from Core, Pay now through its own gateway … on every plan, the business's own ways … and the business marks
    it paid."
  - "Paid: the item counts as sold (or its stock goes down) … the buyer is added to the business's Customers, and a
    receipt goes out. Unpaid after the business's set time (default 3 days), the order is cancelled and the item (or
    stock) is released."
  - "Buyers can't propose a price"; "Request a quote (Basic and up) is where negotiation happens … the buyer accepts it,
    which makes the order"; **Send a price** from a conversation, "only that buyer can use it, once"; "Every override is
    logged … shows on the order as 'Price set by the business'."
  - Shared cards and tickets use the same buttons; people's posts: "Buy now at the post's price reserves it … No order or
    ledger: people pay each other directly"; reservation lapses after 3 days.
- **Step 4a built:** E-commerce setup module (roles: "Ledger to see and change it, Plan & billing to connect or
  disconnect, Publish to go live"); `/business/pay-connect.php` (state checked); `/market/cart.php` "one cart per
  business", `/market/order.php`; webhooks `/api/market-webhook.php?p=stripe|square`; "Then stock comes off and the
  business's own invoice is written (or its payment recorded) through the same gate as its portal. A direct payment is
  marked received by the business in Quotes & invoices, which marks the Marketplace order paid."; "The totals are the
  invoice's own arithmetic … so the order and the business's invoice agree to the cent"; card hold 35 minutes ("Stripe's
  checkout ends at 31 minutes"); Market 360° → Gateways ("the businesses' sales this week (to them, not us)").
- **Step 4b built:** Square catalog import/sync (`MarketSquare.php`, Basic+ catalog, Core+ payments); "a paid Marketplace
  order also goes to Square as an order (`/v2/orders` …) … no payment is attached"; "A Square call that fails never fails
  the order"; card-page CSP (`tv_card_page_csp`) on five card pages ("The Marketplace pages name Stripe and Square; our
  own checkout pages name Intuit (0098)"); stopped connection → Pulse case with Reconnect; "Mock mode is refused off a
  local test".
- 2026-11-02 notes: order page email checkbox fix (`/order.php`); Gateways card shows missing `.env` keys by name; Step 5
  test purchase ("a $1 stand-in … its team only"; `MarketCheckout::placeTest`); Stripe `ui_mode=embedded_page`, pin
  `2026-10-28.endive`; "Failed test purchases are left out of the Marketplace orders list".
- Bank transfer: "full account and routing numbers are better shared by phone … Only that order's buyer sees the
  instructions".
- Landing page (2026-11-03): rail includes "Cart & checkout"; promise "0% from any sale, paid to the seller".

**Changed by**
- 0141 (pointer at 0135 line 525): "one invoice per business with a combined orders summary (§2.2), going live opens the
  shop only (§2.3), tax-exempt buyers … (§4), deposit choices that work … and gateways that differ (§5), the chargeback
  packet (§6)."
- 0169: "Changes how … 0135's gateways … are built: each as an app"; "Today's Stripe and Square connections (0135) keep
  working as they are until their app is built"; app 3 "Stripe Payments (0135's connection moved in …)".
- 0031/0032/0036/0116 carry pointers to 0135 ("superseded by `decisions/0135`").
- 0136 applies 0135's buttons to shared cards; 0159 keeps "the cart and checkout on traversence.com through the
  business's own gateway (0139, 0135)".
- 0098 line 412: CSP for our card pages "Built with the Marketplace's step 4b (0135, which has the Marketplace's own list)."

---

## 0139 Storefronts on trvgo.us and Businesses' Own Domains

- **Date:** accepted 2026-11-01 (Jason); 5a built 2026-11-02, 5b built 2026-11-02; notes to 2026-11-17.
- **Status (index):** accepted.
- **Built:** "Step 5a built (2026-11-02)", "Step 5b built (2026-11-02)"; 5b "Not tested here … Fly's and Name.com's real
  APIs".

**Decides about orders**
- "Signing in, the cart and checkout stay on traversence.com: a storefront's Buy now and Add to cart lead there (its card
  pages and their security policy, 0098 and 0135)."
- Storefront page: "its published items (each links to its item page on traversence.com, where buying, offers and messages
  happen)"; "No script, no session, no cookie".
- **Domains bought through us are Traversence's own sale:** "We're the seller: billed through QuickBooks like GO Codes,
  renewing yearly on the card on file with a reminder before; domain fees aren't refundable once registered." Price =
  "Name.com's cost plus a markup".
  - Built: "charges the plan's card on file (`Billing::chargeExtra`: a QuickBooks receipt and email, like a pack; recorded
    as kind `domain` …)"; "If registering fails, the charge is refunded (`QuickBooksPay::refundCharge`); if that fails
    too, it's logged as SECURITY for our team to refund by hand."
  - Renewal: "two weeks before a bought domain runs out, the card is charged and Name.com renews it".
- **Test-money guards:** `DOMAINS_MODE=mock` "a local test only (refused on the server)"; test mode: "buying uses
  Name.com's sandbox … and no card is charged, nor on renewal"; "`DOMAINS_MODE=live` with the sandbox refuses to sell, so
  no real charge is ever made for a sandbox domain."

**Changed by**
- 0169 §1a (note at top): "domains become a standalone product (Shop → Domains …); a storefront uses one of the account's
  domains rather than owning it … this page's Connect your domain stays as it is until the Storefront app (0169 app 6)".
- Note 2026-11-17: "`DOMAINS_MODE` is again only the storefront's Connect your domain; domain sales have their own switch
  (0169 §1a, #72)"; Name.com Core API; "Fly and Name.com calls ask staging's gate first (0165 §1, #102)."
- 0141 §2.3, §7: "Publish opens or pauses the shop; it never adds or removes the listing or its map pin".
- 0158: "storefront changes become drafts until Publish, with versions and Roll back"; 0159: "storefront pages are built in
  Storefront Studio".

---

## 0141 Market 360° Architecture: Four Levels, Six Modules, Nine Item Kinds

- **Date:** accepted 2026-11-02/03 (Jason); builds 2026-11-03 to 2026-11-04.
- **Status (index):** accepted.
- **Built:** "Built: zip 1, Phase A (2026-11-03)"; "Built: the nine item kinds, Phase C in one build (2026-11-04)";
  delete, photos, publish-from-card, buttons by kind, tax by kind built 2026-11-04. Phase B is 0142. "Not yet: Square
  subscriptions …, a booking calendar view …, proofs sent from Made-to-order orders beyond Quotes & invoices as built."

**Decides about orders**
- §2.1 **Money never pools:** "Every sale is the business's own, charged by its own Stripe or Square; Traversence takes 0%
  and never holds, routes or settles a business's money. NEXUS routing only chooses which branch's gateway and fulfillment
  an order goes to".
- §2.2 **One invoice per business:** "A cart with several businesses is paid business by business, each step naming whose
  account it goes to ('1 of 2 paid'); each business sends its own receipt. The buyer also gets one combined 'your orders'
  summary (page and email) linking each receipt. It is a summary, never an invoice."
- §2.3 "Going live opens the shop only."
- §2.6 **Customers vs prospects:** "A customer transacted (bought, booked, ordered, asked for a quote). The business keeps
  what the transaction needs in its own walls (0110), and can export its customers. Marketing to a customer needs the
  checkout opt-in."
- §2.7 "No income, no expense".
- §4 Money & Processing: Stripe or Square; direct payments Zelle, bank transfer, check, pay on pickup. **Tax-exempt
  buyers:** "the business's responsibility, not Traversence's"; record of who/what (Form 5000A/5000)/how long/proof; "Tax
  comes off that buyer's orders with that business while it's valid." "Checkout disclosures: refund window, deposits,
  holds, chargeback notes".
- §5 **Nine item kinds**, each "its checkout steps and disclosures, what the buyer gets after paying, how Orders handles it
  … and which gateways support it". Made to order: "Spec fields, deposit, proof sign-off, balance invoice (quotes as
  built)". **Deposits:** "a hold on the card (short rentals …) or charge and refund on return".
- §6 E-commerce: "Orders (built), Customers (built), Bundles (new …), Routing (… the multi-business checkout of §2),
  Revenue (reports, payout status from the gateway), Coupons (built), and the chargeback packet (an order with its
  acceptance record, for the business to send its gateway)."
- Phase A built: payments verified via `MarketPay::accountStatus()` cached on `market_gateways`.
- Phase C built (`KindCheckout.php`, "one checkout path"): "Made to order … takes its deposit (`market_orders.pay_now_cents`;
  Stripe and Square charge only that; the rest is invoiced)"; tickets hold seats; bookings/rentals by dates ("overlaps
  read from orders"); "Subscriptions are card only, Stripe Checkout in subscription mode"; after payment: ticket codes
  (`market_tickets`), downloads (`market_downloads`), rental deposit "held on the card (Stripe, 6 days or fewer, captured
  on return) or charged and refunded, settled by the team's Mark returned"; order list shows dates, guests, deposits,
  tickets checked in.
- Delete item: "its Marketplace record stays, unpublished, for the orders that name it … quotes, invoices and orders keep
  their own copy of its lines."
- Buttons by kind: "`MarketBiz::buttonsFor()` decides, never the seller"; Request this service / Request a quote "are
  requested, not checked out; the quote carries any deposit and proof, Quotes & invoices"; "Make an offer on goods …
  when the store's one choice, Accept offers, is on".
- Sales tax by kind (`ItemKinds::TAXED`).

**Changed by**
- 0142 (excluded from sweep; cites 0141): "Chargeback packet (0141 §6): an order, its receipt, and its acceptance record";
  built as "The dispute packet … 'Dispute packet' on each Marketplace order in E-commerce setup"; "Each order also keeps
  its own snapshot of the store policies".
- 0158: "Catalog items (0141): their own draft and Publish stay; they join the version list and Roll back."
- 0166 §6: routing "by who's selling: Traversence, or a business on its own Stripe or Square account, as in 0141".
- Pointers in 0031, 0083 ("NEXUS routing chooses which branch's gateway and fulfillment an order goes to"), 0110, 0116,
  0134, 0135, 0139.
- 0144 `ItemKinds::fit()` limits which kinds an item name may take (see Other decisions).

---

## 0145 The Site Audit and Phased Releases

- **Date:** accepted 2026-11-06 (Jason); notes to 2026-11-17.
- **Status (index):** accepted.
- **Built:** Phase 2 site part built 2026-11-09; "No test makes a real transaction (Jason, 2026-11-10)" built
  (`customers-no-tests_20261110.zip`).

**Decides about orders**
- **Test-money guard** (2026-11-10): "Ensure no test can create actual transactions outside the sandbox."
  - "`QuickBooksPay::testHere()`: with a test account signed in … QuickBooks always runs in the sandbox, whatever the
    Payments switch says; Name.com runs in its sandbox with no card charged (`Domains::noRealMoney()`); a business checkout
    uses its gateway's test mode (`MarketPay::env()`)."
  - "`QuickBooksPay::guardReal()`: a test order (made in the sandbox, a 'TEST · …' copy even if made while payments were
    live, or a test account's) is refused before any live charge, invoice or payment is recorded; refunds and credits stay
    open, since they only send money back."
  - "'Test copy, billed to you' now always makes a sandbox order. A test account's plan receipt goes to the sandbox's
    books"; "Customers leaves test orders out of its numbers".
- §3 Test accounts: "tied to a test listing and sandbox payments only"; "left out of counts, emails and billing".
  Phase 2: "a test payer's charge runs in QuickBooks sandbox (`Billing::charge`)".
- §6 Staging: "payments in sandbox".
- §1 tools checked: "tools that change anything (save, post, pay, delete, send) on staging … and on live only through the
  test accounts".
- §1 Outside services audited include "QuickBooks, Stripe, Square … Name.com"; "sandbox and live keys not mixed".

**Changed by**
- 0165 (note at top): staging "catches up from live … The other staging rules here (email held, payments in sandbox, not
  indexed, behind a password) stay, widened by 0165 §1."
- 0166 §6: "'No test moves real money' (0145) applies to every connector: `guardReal()`/`testHere()` sit in the layer".
- 0169 §6: "test accounts never reach a live gateway (0145)".
- 0162 amends §6 (migrations); 0158/0159/0161 extend the release process.

---

## 0150 Vendor links (one page of a vendor's work orders, 30 days)

- **Date:** accepted 2026-11-09 (Jason).
- **Status (index):** accepted.
- **Built:** "Built: `vendor-links_20261109.zip`."

**Decides about orders**
- "One link per vendor, `/vendor.php?v=<token>`. It lists every work order we've sent them: open ones first (soonest
  due), then those finished in the last 90 days; never drafts … A tap opens the full work order (`/wo.php`) … No account or
  password; no client prices, billing or contacts (as 0101)."
- "Links work 30 days. The vendor's link and each work order's own link (was 60 days) … A work order's 30 days run from
  when it's sent".
- Expired (410): "Request a new link … emailed to the address on file … never shown on the page"; "At most 3 requests a
  day"; "Closed work orders (cancelled, declined, rejected) get no new link."
- Never a dead end: "Didn't get it, or need a hand?" with Send a new link / Ask our team / our email; guard "40 help asks a
  day".
- "Every work order email carries 'All your work orders from us, on one page'"; Back-Office → Vendors "Email their link /
  Email a new link".
- "The Vendor staff level lands on 'Your tickets'" instead of a 403; "Ticket-scoped access for that level is still to
  build."
- Cost: "system emails, which cost nothing".
- Built: `vendors.portal_token`, `portal_expires_at`, `vendor_link_requests`; `Vendors::LINK_DAYS = 30`;
  `WorkOrders::DAYS = 30`, `renewLink()`. "Work orders sent before this keep the 60 days they were given."

**Changed by**
- No other decision cites 0150. (0172 §1.7: "Vendor | work orders (exist, from orders@)"; §2.4 "Work order | vendor |
  email from orders@ | Alert".)

---

## 0154 Free, Creator and Business: Who Pays for What, and How Upgrades Are Offered

- **Date:** accepted 2026-11-11 (Jason).
- **Status (index):** accepted.
- **Built:** "Built 2026-11-11 (see Built, below), except the video worker and the coming items it lists"; then "one
  cart, the free week of Pro, and video quality by plan" built 2026-11-11; Keep in 4K and a single month of Pro built
  the same day.

**Decides about orders**
- §2 Creators: "paid guides and journeys sold through the marketplace"; "Creators keep 100% … no transaction or commission
  fee from Traversence … only the card processor's own fee applies."
- §3 Business add-ons: "Creator collaborations: a business … pays them through our checkout … The creator keeps the fee."
- §5 Upgrade sheet: "payment through the existing checkout (QuickBooks Pay)".
- §6 **The Traversence Shop, one cart:** "The cart icon now opens the GO Codes cart (`/qr/cart.php`). It becomes one cart
  for everything Traversence sells, and beside it a Shop page (`/shop/`)"; "Items come from the order system's products
  (0098), so a new tool or service is a product, not a page"; "Named Shop, not Market, so it isn't confused with the
  members' Marketplace or Market 360°."
- Built (one cart): "plans and storage packs go in the GO Codes cart (`cart_plans`, `app/lib/CartPlans.php` …)"; "One card
  entry pays everything: the card form makes one token per need"; "Plans are paid before GO Codes; a failure stops there
  and says what was done."
- Built (Keep in 4K $0.99): "paid … as an order of its own (`app/lib/OneOffs.php`: `orders` + `order_lines` product
  `keep_4k`, `QuickBooksPay::chargeCard` with its test guard, a Sales Receipt like a plan's; a test account pays in the
  sandbox), not a line on the GO Codes order"; "A decline voids its order and leaves it in the cart."
- Built (single month of Pro $9.95): "paid once like Keep in 4K (`OneOffs` kind `pro_month`)".
- §4 community budget: "3% of all revenue … never less than $25 a month".

**Changed by**
- 0152 note (in 0154): community budget set in Thresholds & gates.
- 0155 §1: add-ons "Sold in the Shop and its one cart (0154 §6; the Marketplace stays members' own buying and selling)".
- 0169 §1: "The Shop (0154 §6) is where Traversence sells its own things, with one cart and one checkout for every
  department."

---

## 0155 Add-ons (plug-in modules) and 1Password, resold and managed from our site

- **Date:** proposed 2026-11-11 (Jason).
- **Status (index):** proposed; Replaced by 0169.
- **Built:** "Note (2026-11-11, Jason: 'table 1password resale'): tabled. Nothing is built or requested from 1Password
  until Jason reopens it."

**Decides about orders**
- §1 (replaced): "An add-on is a product in the order system (0098) with four parts": "Sold in the Shop and its one cart …
  Charged first (QuickBooks Pay), so it never costs us before it's paid"; onboarding on payment; a dashboard module;
  "Upkeep: renew, pause, cancel and the hand-off (§4), driven by the order system's subscription".
- §5: "The Shop's price covers 1Password's rate, the card fee and our margin; nothing is sold at a loss."
- §7.4: "'Password manager setup' as a White Glove service …; billable work in the order system."

**Changed by**
- 0169 (and note at top of 0155): "§1's add-on pattern is replaced by 0169's app lifecycle in the Shop's App store;
  1Password becomes an app there. The rest of this record stands."
- 0156 amends "never built or hosted by us" to "never without explicit consent".

---

## 0166 Provider-neutral payments and bookkeeping

- **Date:** accepted 2026-11-16 (Jason); §6a 2026-11-17.
- **Status (index):** accepted.
- **Built:** "§6a, the wallet, built 2026-11-17 with Domains (release #71, `app/lib/Wallet.php`); the rest not built;
  queued in `handoffs/session-manager.md`".

**Decides about orders**
- Problem: QuickBooks account guessing ("`QB_INCOME_ACCOUNT_ID`, or the first active Income account"); "a product's cost
  isn't recorded, so Gross Profit is wrong."
- §1 "Bookkeeping belongs to the product … Payment processing is set up once, apart from products … They meet only at a
  sale or a refund. A refund reverses the product's income and cost (bookkeeping), and its money goes back through the
  processor that took it".
- §2 **Our own ledger:** "its own double-entry ledger of every sale, cost, tax collected, fee, refund and payout. Every
  entry balances"; provider ids kept so "a webhook delivered twice is recognised and never posted twice"; "The ledger is
  the true record. A bookkeeper connector posts to the connected bookkeeper from it"; with none, "an export goes to an
  accountant."
- §3 One connector shape, QuickBooks first; plan features detected; "Sales tax stays the bookkeeper's or processor's job.
  We never calculate tax rules ourselves."
- §4 QuickBooks setup step: A (Traversence manages) or B (QuickBooks is the master), "Traversence itself starts in B";
  starter chart (Income incl. "Domain purchases, Domain renewals", "Refunds and allowances as contra-income"; COGS incl.
  Printing, Shipping and fulfilment, Subcontractors, Domain registrations/renewals; Merchant fees; "one clearing account
  per processor"; "Funds held for businesses, only if money is ever held for them"); `QB_INCOME_ACCOUNT_ID` and
  `QB_REFUND_ACCOUNT_ID` "retired".
- §5 "The catalog owns the product's link": type, income account, cost and COGS account, vendor; "A product with no
  QuickBooks link can't go on sale."
- §6 **Payments layer:** "One connector per processor: QuickBooks Payments, Stripe and Square to start. Products never name
  a processor"; routing rules "by kind of payment; by who's selling …; with a fallback"; "Switching provider is a rules
  change, not a code or catalog change"; "its refund always goes back through the same one"; "Saved cards and
  subscriptions stay with the processor that stored them"; "A business's own sales (Stripe Connect, Square) are its money.
  Only our platform fee is our income."; "'No test moves real money' (0145) applies to every connector".
- §6a **Wallet:** per account (member's, business's), "never mix"; "Every purchase that renews is assigned a card";
  "An assigned card is locked"; declined renewal "retried 3 and 7 days later"; plans pay from the wallet (note 2026-11-17);
  "two wallets per account, never mixed: the live wallet and the test wallet … a live card is refused" whenever testing.
- §7 Running-cost bills optional.
- Build order: 1 ledger + QuickBooks connector side by side with today's path; 2 setup step; 3 payments layer; 4 more
  connectors.

**Changed by**
- 0169 (note at top of 0166): "built as apps in the Shop's App store, after 0169's first release"; 0169 app 1 "QuickBooks
  Bookkeeper (0166 steps 1–2 …)", app 2 "QuickBooks Payments (0166 step 3's payments layer …)"; "A release goes ahead of
  0166, so payments and bookkeeping are built as apps from the start". 0169 §1a: Domain products' cost "for 0166's ledger
  (income and cost accounts chosen in 0166's setup step, not in code)".

---

## 0167 Communications in one place

- **Date:** accepted 2026-11-16 (Jason).
- **Status (index):** accepted.
- **Built:** "Built and live 2026-11-18 as part of Release #135 (`comms-all.zip` …); SES bulk sending 'coming'."

**Decides about orders**
- §1 Senders include "orders (`orders@trvgo.us`)"; its settings "moved from Settings and Orders and payments".
- §1 Routing: "a table of each kind of email (sign-in codes, account notices, receipts, work orders, vendor email, digests,
  campaigns) and the sender it goes from … Code asks for a kind, never a hard-coded sender."
- §2 Sent view: "everything the site sends, Bluehost and SES alike"; "Bluehost sends start being logged (`Mailer::send`
  records each one …)"; failures "become a Pulse case with a button to fix it".
- §2 Compose "as any sender the staff member may use (support@, orders@)"; §2a overseers per mailbox ("support@, orders@
  …").
- Consequence: "A failed sign-in code or work order is visible the moment it happens".

**Changed by**
- 0172: "Amends … 0167 §2's Sent view (§4.3) and 0167 §2a's 'Outside addresses aren't connected' (§4.7)"; "0167's Sent
  view in Pulse reads `comms_route`"; build note: 0167's four parts shipped with 0172 steps 3–7 as one release.

---

## 0169 The Shop's App store, and one lifecycle for every app

- **Date:** accepted 2026-11-16 (Jason); §1a 2026-11-17.
- **Status (index):** accepted.
- **Built:** "§1a, Domains in the Shop and the Domains tab, built 2026-11-17 (releases #71–#77, #81–#83, #86 …); sales
  stay off until Domain sales is set. The App store and the lifecycle (§1–§6) not built".

**Decides about orders**
- §1 **Shop departments, one cart:** "The Shop (0154 §6) is where Traversence sells its own things, with one cart and one
  checkout for every department. Departments … such as: Plans, App store, Print and custom work, GO Codes, Services
  (Market 360, White Glove), Gift certificates." "Each department shows its items its own way (… print: proofs and
  options)." "never what that account can't use."
- §1a **Domains:** "add to the one cart, pay first, then it's registered through Name.com"; "A standalone product"; "Two
  products … Domain Purchase and Domain Renewal, each a catalog product with its own QuickBooks link, price set per sale;
  every order line carries Name.com's actual cost with Name.com as the vendor, for 0166's ledger"; "No test moves real
  money" and "no income, no expense" hold ("charged before Name.com is paid"); built: "Paid from the account's wallet
  (0166 §6a). What goes wrong is handled on the order, with a Pulse case (Try again, Refund, Email the owner), and
  Something isn't right opens a support ticket (Jason: 'Order processing should address situation like failed renewals
  and new purchases. And support tickets for issues.'); no separate admin list of accounts' domains." Own switch
  `DOMAIN_SALES_MODE`.
- §4 "Paid: Add to cart, charged first through the order system (monthly or yearly) … setup starts by itself once paid."
- §5.6 Remove: "refunds and payouts still owed keep working until they close; a paid app's subscription ends under the
  order system's rules."
- §6 "test accounts never reach a live gateway (0145)"; tokens sealed per account.
- Build order: Shop with departments and App store first; apps: QuickBooks Bookkeeper, QuickBooks Payments, Stripe
  Payments, Facebook Personal, Facebook Business, Storefront.

**Changed by**
- No later decision changes it. 0172 §4.7: business/member mailboxes offered via the App store (0169).

---

## 0172 Communications: who reaches whom (order parts)

- **Date:** accepted 2026-11-18 (Jason, draft 16); note 2026-11-19.
- **Status (index):** accepted.
- **Built:** "step 2 live as #130; steps 3–7 live as one release, #135 … #147 (`comms-gaps.zip` …). Still open: step 8,
  texting, needs its own decision; Journeys and the Marketplace feed in the member's chips are their own builds".

**Decides about orders**
- Gap 2 found: "A business isn't emailed about a new marketplace order or booking. `MarketCheckout::notify` (:644)
  writes an in-site message as if from the buyer; nothing else."
- "Pulse … knows nothing about orders, claims or checks"; "The message carries the process. What a message is for (ship an
  order …) is built into the message by the part of the system that sent it." Pattern = "0105's order case … its stepper
  and Next step buttons acting through `ClientOrders::setStage`"; "a message opened after its order shipped says
  'Shipped Nov 11 by Jason', with Undo".
- Order threads join the member's Pulse ("notices, order threads and mailboxes join it"); a case "an order" opens to "the
  order with its work orders"; ⊕ can "link it to a customer or an order (0111)".
- Levels: Critical "a payment failed … a domain failed to renew"; Urgent "a new order or booking for a business";
  Notice "an order's stage, a receipt … a domain renewed … low stock".
- §1.4 Business owner: reached by "the system (orders, bookings, low stock, domain)"; preferences "per kind × channel
  for the business (new message, new order, new booking …)".
- §1.5 who on a team gets order messages: "set in the RBAC configuration (0102 §4 …)".
- §1.6 **A business's customer:** "Anyone who bought, booked, got a quote or a work order from a business"; can reach "that
  business, about that order (a reply in the order's thread)"; reached "about that order only: receipt, changes, ready,
  reminders. Not marketing unless they also linked or opted in"; "per order: email (always), push, text".
- §1.7 Staff: "Accountant | payments, refunds, failed charges, payouts"; "Vendor | work orders (exist, from orders@)".
- §1.8 System to people: "codes, account notices, receipts, reminders: always system email".
- **§2.4 Orders and payments table:**
  - Client order (ours) placed, stage, done → buyer; staff | email | Notice | B (`ClientOrders::mail`)
  - Marketplace order placed → buyer | email | Notice | B (`mailBuyer`)
  - Marketplace order placed → business | Pulse, email, push | Urgent | "Pulse B; email/push G (gap 2)"
  - Order status by text → customer | Notice | P | "for the text decision (§5)"
  - Payment failed, refund, credit → buyer; Accountant | email + notice | Critical | B (`Orders::tell`)
  - Quote or invoice → customer; business on approval | email | Alert | B (`BizQuotes`, `q.php`)
  - Work order → vendor | email from orders@ | Alert | B
- §2.5 "Booking or rental placed | customer; business | as orders (§2.4) | Urgent … B for the customer; G for the business".
- §2.6 Marketplace: question on item (Pulse B, email/push G); offers (B, `MarketOffers`); "Low stock, item paused |
  business | notice | Notice | G".
- §2.7 Domains: "Bought, verified, renewal due, renewed, failed | owner; Developer role | email + notice | Notice; Critical
  when it fails | email B (`AccountDomains`); notice G".
- §3.3: claimed listings get "orders and bookings" in the communication system; §3.2 "Marketplace item … Message | fine
  (only managed businesses sell)".
- **§4 One way to deliver (in full):**
  - §4.1 `Notify::send($pdo, $kind, $to, $about, $words)` "the one entry point", row in `comms_messages`; kinds register
    `Notify::KINDS`; "Mail that's not a notice (receipts, work orders, codes) moves to `Notify` call site by call site,
    starting with the gaps in §2. `Mailer::send` stays as the email driver underneath."
  - §4.2 route stages Created → Addressed → Held → Sent → Delivered → Read → Answered or acted on → end point; "Pulse
    always: every message reaches stage 4 in the recipient's Pulse".
  - §4.3 message control: Creating, Tracking (`comms_route`), Monitoring, Verifying, Reporting ("per kind, channel,
    sender and business … each business's share in its Insights"), Controlling; "0167's Sent view in Pulse reads
    `comms_route` … one record, not two."
  - §4.4 "Things that can't be turned off (codes, receipts, security) say why, greyed with the reason."
  - §4.5 alarms via `Notify` (`alarm.trip` only since 2026-11-19); Pulse items assigned or escalated call `Notify`.
  - §4.6 engines: email (0167), push (own build), text (own decision; "customers' order texts" an open question).
  - §4.7 outside mailboxes: "Our own mailboxes (cPanel: support@, orders@, staff) | IMAP".
- §5 Texting: places a text could go include "customers' order texts".
- §6 Step 2 "Closes gaps 1–4 and 6 in Pulse and by system email: a business told of new messages, orders and comments".
- Consequence: "A business finally hears about its messages, orders and comments."

**Changed by**
- No later decision changes it (0167 is cited by it; nothing cites 0172 except 0167's build-order note).

---

## Other decisions with an order rule

- **0031 Market Post-and-Connect** (accepted; replaced by 0135): "Traversence does not process payment, hold funds, or act
  as a party to the transaction at launch"; pointer: "businesses' own card checkout, which still never passes through
  Traversence"; "0141 §2.1–2.2: … a multi-business cart is paid business by business, one invoice each, with a combined
  orders summary."
- **0032 Market Quote & Inventory Mechanics** (accepted; replaced by 0135): "a `quotes` table with a status enum and
  dual-confirmation timestamps"; catalog "Per-listing cap: 250 items" (now 1,000 per 0135); personal sellers "single-item
  flow".
- **0036 Location Group Linking:** "A listing's inventory, catalog cap …, and communications routing are scoped to that
  listing alone unless explicitly linked"; catalog-cap part moved to 0135.
- **0039 External Source plug-in and pricing models:** External Source contract with "an order hand-off mechanism back to
  the source system, fired when a Quote on one of its items reaches Accepted"; ShedSuite `POST /quotes/v1` "functions as
  the actual order-creation call"; status "scheduled polling of `GET /customer-orders/v1`"; `pricing_model` per catalog
  item (incl. `bundle_priced`, `build_to_order`).
- **0040 Delivery Method Contract:** "delivery should be treated as a line that's always present on an order"; buyer
  proposes route, "staff verifies or corrects it before the delivery line is priced and the Quote can proceed".
- **0041 SC&T native merge:** "every operational table — catalog, inventory, quotes, CRM/pipeline state … — is strictly
  scoped by `listing_id` … nothing is pooled or shared across listings."
- **0055 App Shell, roles:** staff Accountant "Revenue, refunds, commissions, costs"; a listing's Accountant "Invoices,
  payment methods, statements".
- **0073 Listing categories** (proposed): "Four conversion types …: book or reserve (a time), buy or order (a thing),
  request a quote (a job), and contact or visit … one shared building block … with the listing's catalog behind it".
- **0083 NEXUS** (pointer): "NEXUS routing chooses which branch's gateway and fulfillment an order goes to; each branch
  connects its own gateway, and money never pools at the brand."
- **0090 Sign-in providers:** "Amazon is left for checkout (Amazon Pay) and for sellers (Selling Partner API)".
- **0103 Ending, transfer, inactivity:** business closes: "open orders finish or refund first"; "Account with paid service
  or open orders | never treated as inactive while they're open"; "The ledger, orders, invoices and payments: kept for tax
  and legal periods (7 years)".
- **0115 Business portal foundation:** portal modules "Quotes & invoices; Catalog; Orders"; "Viewer … can see the listing,
  catalog, orders, customers, inbox, ledger and codes, and change nothing."
- **0117 White Glove:** "Customers, orders, inbox | See"; "Plan & billing, ledger … | No".
- **0132 Tags and links:** system tags "a client's QuickBooks customer; an order's invoice | reference only, no
  notifications"; "Client contact | person → client business | see and approve its orders, proofs and invoices"; "Vendor
  | vendor → work order or product | accept, quote and update the jobs sent to it".
- **0134 Events** (accepted; design): "a ticket is a catalog item sold through the order system, with nothing new needed
  for the money"; "The order system (0098): one order record whatever it sells, an append-only money ledger, refunds,
  credits, deposits and chargebacks"; "A cancelled event refunds or credits everyone"; payment by host: Traversence → "Our
  QuickBooks checkout (0095)"; Free/Basic business → "Manual payment … marked paid by the business"; Core+ → "Card
  checkout through the business's own gateway … no money held by us"; tickets a month by plan (Free 50, Basic 150, Core
  250, Strategic+ unlimited); outside ticketing options B/C; `event_ticket_codes` "its order line (0098)". Pointer: tickets
  become 0141's Event tickets kind.
- **0136 Topic groups:** shared item cards: "Buy now at the seller's price (every plan; Place order, then paid the
  business's way, or Pay now by card from Core), Request a quote (Basic and up …), Message. Buyers never propose a price";
  "The card never offers more than the item page"; flyers: "order it printed through our print catalog (`Products`,
  0101)".
- **0137 Group charters, notices:** Bluehost sends "Traversence's own sales: GO Code, print and plan receipts, invoices,
  payment failures"; SES sends "A business's own sales: order confirmations, tickets and their changes"; per-person SES
  account carries "order confirmations, tickets …", "A suspended bulk account can't stop a ticket or a receipt"; "Its order
  confirmations and tickets don't count" toward the email allowance. §9 "Flyers printed through us create orders": catalog
  items "with the vendor's prices and our markup (`Products`, 0101, 0130)"; "The flyer is the proof: they approve it at
  checkout"; "They pay through our QuickBooks checkout (0095) … the order lands on the ledger (0098)"; "It becomes a work
  order to the print vendor (0106) … in production → shipped or ready for pickup → complete, emailing at each step
  (`ClientOrders`)".
- **0140 Market 360 for a business:** Get selling segments incl. "4 Getting paid | Connect Stripe or Square, direct ways",
  "5 Shipping & tax", "7 Test & launch | Test purchase (0135) … | A test paid, then live"; "After launch … Orders, Discount
  codes, Marketing, Reports sit under the same module"; Customers and Quotes & invoices move under it. Widened by 0141.
- **0143 Stock library / Gallery:** order uploads ("proofs, print-ready files, artwork, labels") through the one uploader;
  "an order still keeps its own copy: its record outlives an account"; `gallery_uses` rows ("Order #9 · proof"); on admin
  order pages the first tab is "This order's files … then This client's earlier orders (up to 12 …), to reuse approved art
  on a reorder" under "the order-files rule (`backoffice/orders.edit`)"; staff have no personal Gallery ("all … file[s]
  will be assigned to an order"); **Orders catalog** in a client's Gallery follows 0101's Collateral Center ("only items
  released to them; the approved proof, print-ready files once that order is paid in full"; `Collateral::forClient`).
- **0144 Word suggestions:** `ItemKinds::fit()`: "the catalog's Add and Details refuse a kind that doesn't fit, with why;
  publishing refuses one too"; "Animals: Physical goods only".
- **0152 Outside services:** costs card says "card fees on our own orders are ours; on the Marketplace they're the
  business's, with 0% to us"; Settings group "orders and payments"; "In our admin: QuickBooks → Payments and QuickBooks
  items, Stripe and Square → Gateways, Name.com and Fly.io → Custom domains"; not yet: "card fees per payment recorded from
  QuickBooks".
- **0158 One way to publish:** "Catalog items (0141): their own draft and Publish stay; they join the version list and Roll
  back."
- **0159 The builder:** storefront blocks "Buy now and Add to cart"; "The commerce under it stays the platform's: the
  catalog and its variants, price lists (0112), stock, taxes and shipping, the cart and checkout on traversence.com through
  the business's own gateway (0139, 0135), and Square sync … Blocks show these; they never rebuild them."
- **0163 Operations:** master switch "never stops business operations: orders, billing, payments, email"; backups: "Our
  orders' payments | QuickBooks | no backup: wire or check"; "Marketplace checkout | Stripe or Square (the business's
  choice) | no backup: contact the seller"; "Selling domains | Name.com | sales pause"; planned: "Our payments | QuickBooks
  | Stripe as a second processor (our account, in test mode now), then wire or check; no internal card processing (PCI)";
  "Marketplace checkout | … the other, when the business has connected both; else pay at pickup, contact the seller".
- **0165 Staging in safe mode:** "payments are sandbox for everyone (`QuickBooksPay::testHere()`), live gateway rows are
  deleted after the copy (`qb_connection`, `market_gateways`)"; "Payments refuse to start on staging if a live key is
  present"; Payments "(QuickBooks, Stripe, Square, payouts, refunds, invoices) | Sandbox only (`testHere()` for everyone,
  `guardReal()` as on live)"; email incl. "work orders" held; data class **Money** "Orders, payments, invoices,
  subscriptions, payouts, gateway rows, promotions used | Live's come in (stripped) | Never carried"; never carried:
  "'TEST · …' orders and every Money row".
- **0171 Control Board:** watch every 5 minutes includes "QuickBooks' token read on the site, asked of QuickBooks at most
  once an hour (held on staging); refused or needing reconnecting is a trip at once".

Sweep files with keyword hits but no order-system rule (search/ranking "order", build order, crawler "catalog", proof as
evidence, `app/vendor/` paths): 0005 0007 0015 0016 0017 0019 0020 0021 0022 0023 0027 0029 0030 0042 0043 0044 0046 0048
0050 0053 0058 0060 0061 0062 0063 0064 0066 0067 0070 0075 0076 0086 0088 0092 0093 0094 0097 0100 0104 0126 0131 0133
0138 0146 0153 0156 0157 0160 0161 0164 0168 0170.
