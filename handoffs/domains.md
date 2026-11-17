# Domains as a standalone product (decisions/0169 §1a)

Session: claude/domains-0169-1a. Started 2026-11-17, on the mirror at `3c54bf3` (latest sync then).

## Where it stands

- **Planned; scope and renewal card answered (relayed). Waiting on Jason's go in chat** before code is written.
- No release sent yet. Nothing in `releases/inbox/` from this work. No claims taken yet.

## What's live today (read 2026-11-17)

- `app/lib/Domains.php` (0139 5b): table `business_domains`, one row per storefront (`entity_id`), stages Added → DNS
  pointed → Certificate coming → Live / Failed / Removed. Buying charges the **plan's card** (`Billing::chargeExtra`,
  kind `domain`); renewals the same, 14 days ahead, in `domains_tick` (every 15 min). `DOMAINS_MODE` off|test|live|mock,
  `NAMECOM_ENV` sandbox|live; live + sandbox refuses to sell (`canSell`). `namecom()` and `fly()` have no
  `StagingGate::hold()` yet (R13).
- Callers: `app/business/storefront.php` (`tv_biz_domain_card`, `tv_biz_storefront_act` `domain_*`),
  `app/ui/storefront-view.php` (`byHost`), `app/admin/tools.php` (Market 360 → Custom domains: `all`, `get`, `check`,
  `disconnect`, `available`, `canSell`, `mode`), `app/lib/MemberPulse.php` (`forEntity`), `app/lib/Pulse.php`
  (`ncDown`, `ncCheck`), `app/scripts/domains_tick.php` (`tick`).
- The Shop: `website/shop/index.php`, cart lines in `cart_plans` (`app/lib/CartPlans.php`), one-offs paid as their own
  order in `app/lib/OneOffs.php` (orders + order_lines, `QuickBooksPay::chargeCard`, Sales Receipt), the cart page
  `/qr/cart.php`, actions in `website/api/go-codes.php`.
- Live `.env`: `DOMAINS_MODE=off` (session-manager.md: Fly and Name.com set up later), so nothing here is visible to
  the public until it's switched to `test`.

## The plan (one release), narrowed 2026-11-17

Scope change relayed by the session manager (Jason: "Shop for domains. Not the storefront app, that goes into the
queue"): only Shop → Domains and the account's Domains tab. The storefront's Connect your domain stays exactly as it is;
joining it to the account's domains is the Storefront app's job (0169 app 6).

1. **Data:** a new table `account_domains` for domains an account buys in the Shop or lists as brought (owner_type
   `user`|`listing`, owner_id, source bought|own, where it points: forward|dns|nameservers, the renewal card, expiry,
   auto-renew), `domain_events` (history) and `domain_cart` (a pending purchase). `business_domains` and every function
   on it are untouched; nothing is copied or updated, so no `data_reason`. Classes in `staging-tables.php`.
2. **`AccountDomains`** (`app/lib/AccountDomains.php`), a new library beside `Domains`: search and price (calls
   `Domains::search`/`price`/`canSell`, so the live + sandbox guard holds), the cart line, paying (its own order like
   Keep in 4K: card saved and charged, then Name.com registers it with WHOIS privacy, refunded if it can't be),
   renew now, auto-renew 14 days ahead in `domains_tick`, forwarding, DNS records, nameservers, the DKIM records when
   SES sends for it, unlock and transfer code, history. Its Name.com calls ask `StagingGate::hold()` first.
3. **Shop → Domains** (`/shop/domains.php`, a department link on the Shop), any account; the registrant 18+
   (`AgeGate`) with real contact details. Test accounts and `DOMAINS_MODE=test`: Name.com's and QuickBooks' sandboxes.
4. **Domains tab:** `/user/domains/` (a Dashboard section) and a **Domains** module in the business portal, one shared
   view (`app/ui/domains-view.php`). A business's storefront domain (`business_domains`) is listed there read-only, with
   a link to its storefront page, managed there as today.
5. **No admin list** (Jason, 2026-11-17: "Order processing should address situation like failed renewals and new
   purchases. And support tickets for issues."). Market 360 → Custom domains stays as it is (storefront domains only).
   Instead:
   - **Every purchase and renewal is an order** (0098) in Back-Office → Orders (`https://traversence.com/admin/backoffice/orders`):
     Domain Purchase paid → registering at Name.com → done; a renewal ordered 14 days ahead → charged → renewed. Each
     step is an order event, so the order is the record.
   - **What goes wrong is handled on the order:** a registration that fails after the charge (refunded, or the refund
     failed too), a renewal declined after its retries (3 and 7 days), a renewal charged but not renewed at Name.com:
     the order shows Needs attention and a Pulse case opens with the order and its buttons (Try again, Refund, Email the
     owner); the owner is emailed with a way forward (Replace card, Renew now).
   - **Support tickets:** the Domains tab's **Something isn't right** on a domain opens a support ticket on its latest
     order, the way orders' tickets work today (0101 follow-up: `order_feedback` kind `problem`, a Support case in the
     Pulse with its number, replies emailed, resolving it closes it on the order).
6. FAQ entries; 390px check; tests in mock mode.
7. **Two products** (Jason, 2026-11-17, relayed by the session manager: "Domain Purchase, domain renewal. Two
   products"): **Domain Purchase** (a first registration) and **Domain Renewal** (every yearly renewal, Renew now or
   auto-renew), added to `products` by the migration (INSERT … WHERE NOT EXISTS), both services priced per sale
   (Name.com's cost + `DOMAIN_MARKUP_CENTS`). Each links to its own QuickBooks item through the product's link
   (`Books::itemFor`), never a name picked at sale time. Every order line carries the product, **Name.com as its vendor**
   (`vendors`, `order_lines.vendor_id`) and **Name.com's actual cost** for that domain and year (`order_line_costs.vendor_cents`),
   so 0166's ledger can post the cost of goods; the margin is the markup. No income or cost account is chosen in code:
   that's 0166's QuickBooks setup step (two starter pairs: Domain purchases and Domain renewals, income + COGS). Until
   then the receipt follows today's `QuickBooksPay` path.

8. **A Wallet** (Jason, 2026-11-17, relayed: "Payments should have a payment wallet like system where multiple payment
   methods can be held … that card will be assigned to its future use for renewals. It also locks the card from being
   removed but it can be replaced with a new card." "Yes. Add to build."). `app/lib/Wallet.php`, tables `wallet_cards`
   and `wallet_assignments`, recorded as a note in decisions/0166:
   - one wallet per account (a member's; a business's, run by its owner, or its accountant role if the owner allows);
     never mixed;
   - many cards, one default (offered first at checkout); only the processor's card reference and a label
     ("Visa ··4242"), through `QuickBooksPay::saveCard` / `chargeSaved`; each card stays with the processor that saved it;
   - each purchase that renews is **assigned** a card (Domain Purchase now; plans and paid apps later); an assigned card
     is **locked**: never removed, only **replaced** (for that item, or everything on it); it unlocks when nothing
     renews on it (cancelled, auto-renew off, transferred away);
   - 30 days before a locked card expires: a reminder with Replace card; a declined renewal retries at 3 and 7 days, a
     new card at once;
   - test accounts in the sandbox; every charge has its key so it's made once;
   - **plans use the wallet too** (Jason, 2026-11-17: "this should not be different, it is a payment method from
     wallet"): the migration copies each subscription's card into its account's wallet (INSERT only; the QuickBooks
     wallet ids `TRV-U<id>` / `TRV-L<id>` are already per account, so the cards are already there at QuickBooks) and
     assigns the plan to it. `Billing::start` and `setCard` save through `Wallet` and assign; replacing the card in the
     wallet replaces it on the plan. `subscriptions.card_id` / `card_label` stay, kept equal to the assigned card, so
     every reader today (renewals, the cart, the Plans page, the admin, receipts) keeps working unchanged. A business's
     ownership moving (`Team.php`) unassigns and removes the old owner's card, as it clears it today;
   - `saveCard` and the cardOnFile charge are verified against QuickBooks' sandbox **on staging** once the release is
     there (QuickBooks is sandbox-only on staging; this session can't reach it).

Jason confirmed the scope (relayed 2026-11-17): Shop → Domains and the Domains tab; the storefront unchanged.

## Questions put to Jason (2026-11-17)

1. Renewals: keep the checkout card on file for that domain's renewals (shown, can be changed or removed; no card means
   reminders and Renew now). 0139 charged only a plan's card, which a member may not have.
2. Confirm the narrowed scope (relayed by the session manager).

## Files the release will carry (filled in as built)

-
