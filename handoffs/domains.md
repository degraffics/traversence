# Domains as a standalone product (decisions/0169 §1a)

Session: claude/domains-0169-1a. Started 2026-11-17, on the mirror at `3c54bf3` (latest sync then).

## Where it stands

- **Planned (scope narrowed 2026-11-17), waiting on Jason's OK** (below) before code is written.
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
5. **Admin → Market 360 → Custom domains:** also lists the accounts' domains (owner, source, expiry, where it points).
6. FAQ entries; 390px check; tests in mock mode.

## Questions put to Jason (2026-11-17)

1. Renewals: keep the checkout card on file for that domain's renewals (shown, can be changed or removed; no card means
   reminders and Renew now). 0139 charged only a plan's card, which a member may not have.
2. Confirm the narrowed scope (relayed by the session manager).

## Files the release will carry (filled in as built)

-
