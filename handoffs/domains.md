# Domains as a standalone product (decisions/0169 §1a)

Session: claude/domains-0169-1a. Started 2026-11-17, on the mirror at `3c54bf3` (latest sync then).

## Where it stands

- **Planned, waiting on Jason's OK on three points** (below) before code is written.
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

## The plan (one release)

1. **Data:** a new table `account_domains` (owner_type `user`|`listing`, owner_id; `storefront_id` the storefront that
   uses it, or none; `points` storefront|forward|dns|nameservers; forward address; nameservers; the renewal card;
   everything `business_domains` has). `domain_events` (its history) and `domain_cart` (a pending purchase: name,
   price shown, registrant, for whom, the storefront it's for). The migration copies every `business_domains` row
   across with the same id (INSERT … SELECT … WHERE NOT EXISTS: safe twice, no UPDATE, so no `data_reason`); the old
   table stays untouched as the rollback copy. Classes in `staging-tables.php`.
2. **`Domains`** reads and writes the new table; every live function keeps its name and arguments (`forEntity` = the
   domain a storefront uses, `byHost`, `add`, `buy`, `check`, `disconnect`…), with new ones per account (`forOwner`,
   `use`, `release`, `pointTo`, `dnsRecords`/`setDnsRecord`, `forward`, `nameservers`, `renewNow`). `namecom()` and
   `fly()` ask `StagingGate::hold()` first.
3. **Shop → Domains** (`/shop/domains.php`, a department link on the Shop): search, price a year, **Add to cart** with
   the registrant's details and an 18+ tick (`AgeGate`); the cart pays it as its own order (like Keep in 4K): card
   charged, then Name.com registers it (WHOIS privacy), refunded if it can't be. Test accounts and `DOMAINS_MODE=test`:
   Name.com's sandbox and the QuickBooks sandbox.
4. **Domains tab:** `/user/domains/` (embedded in the member Dashboard as a section) and a **Domains** module in the
   business portal, both drawn by one shared view (`app/ui/domains-view.php`) so the Storefront app can reuse it.
5. **Storefront's Connect your domain:** "One of your domains", "Buy one" (the same search, into the cart, connected by
   itself once registered), "Bring one".
6. **Admin → Market 360 → Custom domains:** every account's domains, with the owner (member or business) and the
   storefront using it.
7. FAQ entries; phone layout checked at 390px; tests in mock mode.

## Questions put to Jason (2026-11-17)

1. Renewals for an account with no plan card: keep the checkout card on file for that domain's renewals (shown, can be
   changed or removed; no card means reminders and Renew now). Businesses' existing domains keep renewing on the plan
   card. (0139 charged only the plan's card.)
2. Buying from the storefront step goes through the cart too (0139 paid in place on the plan card).
3. One release rather than two.

## Files the release will carry (filled in as built)

-
