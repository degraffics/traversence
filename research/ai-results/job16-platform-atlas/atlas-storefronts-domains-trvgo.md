# Platform atlas: storefronts-domains-trvgo

`research/platform-atlas/storefronts-domains-trvgo.md` · *Draft for human review.*

**Coverage.** The code for this entry comes from five files:

- `shop/domains.php` (shown in full)
- `shop/index.php` (shown in full)
- `app/lib/Storefront.php` (shown in full, 361 lines)
- `app/lib/Domains.php` (cut off after line 520)
- `app/lib/AccountDomains.php` (cut off after about line 453)

The following are named in the docs but not shown:

- the trvgo.us router (`trvgo/index.php`)
- `app/ui/storefront-view.php` and `app/ui/domains-view.php`
- `app/business/storefront.php`
- the `/user/domains/` pages
- `workers/storefront-edge/`
- `trvgo/.htaccess`
- `Wallet`, `Billing`, `Orders` and `QuickBooksPay`
- the migrations
- the `domains_tick` job
- the Name.com helpers (`namecom()`, `api()`, `ncDown()`, `asSandbox()`, `lookupLive()`) beyond line 520 of `Domains.php`

Statements about those parts come from the ADRs, not from code.

---

## (a) What each person can do, page by page

### Resident or traveler (a visitor, signed in or not)

**`trvgo.us/<shop>`**: a business's storefront.
- It shows the listing's public details, its published items and its pages as tabs (ADR 0139 §5a).
- Each item links to its item page on traversence.com. Buying, offers and messages happen there.
- Brand locations are reached at `trvgo.us/<brand>/<location>` (`Storefront.php:3-4`, `:121-137`).
- Once the business connects a domain, the storefront answers at that domain, and the trvgo.us address redirects to it with a 301 (0139 §5b).
- This route is **not in `site-audit/pages.md`**, which is the routing register.

**A paused, unbuilt or hidden storefront**: the visitor sees a plain "isn't open right now" page. The view that draws it is not shown (0139 §5a).

**`trvgo.us/<code>`**: a GO Code scan. This shares the router. Shop names can never collide with codes (`Storefront.php:75-86`; 0139 §2).

**`/shop/` as a guest**:
- GO Codes, plus what a free account and a business claim add (`shop/index.php:96-103`).
- The Domains department card (`:51-54`).

**`/shop/domains.php` as a guest**: a prompt to sign in or join. A domain belongs to an account (`shop/domains.php:30-32`).

### Member

**`/shop/`**:
- Personal plans, Pro trial, storage packs, GO Codes, 4K keep and a single month of Pro, all into one cart (`shop/index.php:75-95`, `:107-115`).
- A "Your domains" link to `/user/domains/` (`:53`).

**`/shop/domains.php`**:
- Search a name (`AccountDomains.php:132-176`). Results include the typed name plus `Domains::TLDS`, which is com, net, shop, store, co, us, biz, app and ai (`Domains.php:33`).
- Choose a term of 1, 2, 3, 4, 5 or 10 years, limited to what Name.com sells for that ending (`AccountDomains.php:41`, `:190-210`).
- Add to cart. The member must confirm 18+ and give full registrant details (`:259-289`).
- Pay from the wallet (`:386+`).

**`/user/domains/`** (Domains tab). Per 0169 §1a and the `AccountDomains.php:12-16` header:
- renew now and auto-renew
- forwarding, DNS records or nameservers
- email records
- transfer away
- history
- "Something isn't right"

The tab's code is not shown.

### Business (an owner or a team member, acting as the listing)

**`/shop/`**: the plans for the listing, packs, services, White Glove and quotes. Billing changes are limited to those who may manage billing (`shop/index.php:55-74`, `:25`, `:58`).

**`/shop/domains.php?for=listing:<id>`**: buy a domain for the business.
- Acting as the business picks the listing automatically (`shop/domains.php:20-23`).
- It needs Domain (edit) plus Plan & billing (edit) on the team (`AccountDomains.php:105-117`, `:263`).

**`/business/<id>/domains`**: the portal's Domains tab (`shop/index.php:53`; code not shown).

**Portal → Storefront & pages** (Core and up; 0139 §5a):
- Set up the storefront and choose its name.
- Pick a theme and accent, and add a logo and cover.
- Write pages, then Preview, Go live and Pause.

The portal page itself is not shown. The rules behind it are `Storefront::start/save/setStatus/setPhoto` (`Storefront.php:140-197`, `:223-233`).

**Connect your domain** (0139 §3, §5b):
- **Bring your own:** the business is shown the records to add, the site checks them, and the domain goes live.
- **Buy one through us:** register at Name.com, with the records set automatically.
- Also available: renewal, auto-renew, the transfer code, and disconnect (`Domains.php:176-304`, `:386-484`).

### Staff (admin)

Market 360° → Storefronts and → Custom domains (0139). Pages not shown; the list query is `Storefront::all` (`Storefront.php:351-360`).

---

## (b) How it works underneath

### Storefront (`app/lib/Storefront.php`)

- **One storefront per listing.** The table `storefronts` is keyed by `entity_id`.
  - It is gated by Plan feature `storefront` through `Plan::allows` (`:16`, `:106-110`).
  - Statuses are Building, Live and Paused (`:17`).
  - A storefront that has been live can only pause; it never returns to Building (`:188-197`).
- **Names** (`:94-104`) must be:
  - 3–40 characters: letters, digits and single hyphens
  - not reserved (`:38-40`)
  - not the shape of a GO Code: 6 characters from the code alphabet (`:75-79`)
  - not an existing `short_codes` row
  - unique
- **Starting a storefront** (`:140-152`): `start()` builds the name from the listing's name and adds a number on a clash.
- **Path parsing** (`:82-86`, `:121-137`): `looksLikeShop`/`resolve` read up to three path parts. A location resolves only under its own brand (`:128`).
- **Parent brand** (`:171-182`): a storefront may name a parent brand only if the person runs both (the `$mayParent` callback). A location cannot be a parent.
- **Showing to the public** (`:200-206`) requires all three: status Live, the listing not suppressed (`entities.is_suppressed`), and a plan that still allows it.
- **Preview link** (`:209-218`): an HMAC over `entity|ISO-week`, accepted for the current week and the week of 7 days ago.
- **Look**:
  - Four themes (`:19-24`) and their block styles (`:31-36`).
  - An accent under 3:1 contrast against the theme's cards is refused (`:167`). Between 3:1 and 4.5:1 it is kept, with a warning (`:304-311`).
  - Contrast uses the WCAG formula (`:314-323`).
  - `style()`/`blockVars()` feed the block kit's CSS variables (`:329-348`).
- **Photos** (`:223-285`): the logo, the cover and any photos used in the page blocks must be the business's own `gallery_items`. They are kept in a public "Storefront" album (`syncAlbum`).
- **Address** (`:58-72`): `url()` uses the live own domain, then the parent's domain, then the trvgo.us address.

### Storefront domain (`app/lib/Domains.php`, table `business_domains`)

**Switches**:
- `DOMAINS_MODE` (off, test, live or mock) controls the storefront's Connect your domain only (`:45-53`).
- `DOMAIN_SALES_MODE` controls domain sales (`:56-70`). `forSales()` routes a sale's Name.com calls by the sales switch.
- `mock` is refused off a local test (`:48`, `:59`).

**Visibility** (`:81-103`): test mode shows the feature to the team only. `isTeam` includes the Founder through `TwoStep::isStaff`.

**Availability**:
- `available()` (`:106-113`) needs the Fly environment keys.
- `canSell()` (`:116-125`):
  - refuses live mode when Name.com is the sandbox
  - refuses while Name.com is down (`ncDown`, not shown)
  - refuses while Name.com credentials are missing

**Bring your own**:
- `add()` (`:176-202`) cleans the name (`:128-136`). It refuses Traversence's own domains, a second domain per storefront, and a domain another business has connected.
- It requires the plan and a storefront to exist (`:205-210`).
- It asks SES for DKIM tokens when SES is set up (`:213-228`).
- The records offered (`:164-171`) are A/AAAA to the Fly IPs, a `www` CNAME to `<app>.fly.dev`, and the DKIM CNAMEs.

**Checking**:
- `check()` (`:255-283`) uses `dns_get_record` (`:233-242`).
- When DNS points at the edge, it asks Fly's GraphQL API for a certificate (`:309-323`), going through `StagingGate::hold` (`:332-345`).
- When the certificate is ready, the status becomes `live` and `link()` sets `storefronts.domain` (`:286-289`).
- If a live domain stops pointing at the edge for 3 days, it becomes `failed` and is unlinked (`:264-268`).
- Disconnect (`:297-304`) unlinks the domain, deletes the certificates, sets the status to `removed` and turns auto-renew off.

**Buying**:
- The price is the cost rounded up to the cent, plus `DOMAIN_MARKUP_CENTS` (default 500) (`:350-353`).
- `buy()` (`:386-418`) runs in this order:
  1. Re-check the price, which may never exceed what was shown (`:397`), and refuse premium names.
  2. Charge `Billing::chargeExtra` with an idempotency key, unless test mode or a test account (`:399-401`).
  3. Register at Name.com Core API v1 with privacy on, Name.com's own auto-renew off, and an idempotency key (`:405-406`).
  4. If registration fails, refund through `QuickBooksPay::refundCharge`. If the refund also fails, log a SECURITY line (`:410-414`).
  5. Set the records (`:421-428`).
- Registrant details are **US only**: `country => 'US'` and a phone that must be a US number (`:431-443`).
- Transfer: `transferCode()` unlocks the domain and fetches the auth code (`:454-464`).
- Renewal: `renew()` charges the card first, then renews at Name.com (`:467-484`).
- Price comparison: `priceList()` compares the account's prices with retail, cached for 12 hours (`:492-515`).

### Account domains (`app/lib/AccountDomains.php`, tables `account_domains` and `domain_cart`, plus the shared `cart_plans`)

**Opening**: `open()` gates sales on the sales switch, the team in test mode, the Name.com environment and readiness (`:74-86`).

**Sandbox**: `sandboxed()` puts test mode, a test account or staging into the Name.com and QuickBooks sandboxes (`:89-95`). In test, searches read Name.com live while purchases stay in the sandbox (`:151-157`, `:274-278`).

**Terms**: `termsFor()` ca