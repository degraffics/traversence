# Platform atlas: storefronts-domains-trvgo

*Draft for human review. Sources are the attached decisions (0139, 0169, 0137, 0098, 0096), the charter, the brand guide, the PRD, `site-audit/pages.md`, and five code files. Every code file is capped, so `Domains.php` (shown to line 520) and `AccountDomains.php` (shown to line 453) are only partly shown. These are not shown: the trvgo.us router `trvgo/index.php`, `app/ui/storefront-view.php`, `app/ui/domains-view.php`, `app/business/storefront.php`, `workers/storefront-edge/`, `/user/domains/`, the `domains_tick` job and every migration.*

---

## (a) What each person can do, page by page

**Resident or traveler (a visitor who isn't signed in)**
- **`trvgo.us/<shop>`** shows one business's storefront: the listing's public details, its published items, the brand's live locations, and its pages as tabs.
  - **Address forms:** `trvgo.us/<shop>/<page>` is one page. `trvgo.us/<brand>/<location>` is a location (`Storefront.php:3-4`, `:121-137`).
  - **What the page carries (0139 §5a):** no script, no session and no cookie.
  - **Buying happens elsewhere:** "Buy now" and "Add to cart" lead to the item page on traversence.com.
  - **A storefront that isn't showing** gives a plain "isn't open right now" page (0139 §5a). Not showing means it is still building, paused, on a plan below Core, or its listing is hidden.
- **A business's own domain** (for example `pinecafe.com`) serves the same storefront through the Fly.io edge. Once it is live, `trvgo.us/<shop>` answers with a 301 redirect to it (0139 §5b; `Storefront.php:58-64`).
- **No resident- or traveler-specific surface exists in this area.** There is no Directory or Discovery integration in the code shown. The storefront routes are not listed in `pages.md`, which only covers traversence.com.

**Member (signed in)**
- **`/shop/`** shows a Domains department to every account (`shop/index.php:51-54`). For a member it also shows plans, storage packs, GO Codes, "Keep one video in 4K" and a month of Pro (`:75-95`). A guest sees GO Codes and a Join prompt (`:96-103`).
- **`/shop/domains.php`** lets a member search a name and add it to the one cart with its registrant details (`shop/domains.php:2-6`, `:34`). A guest is asked to sign in or join (`:30-32`).
- **`/user/domains/`** is the Domains tab: renew, auto-renew, where the domain points, DNS and email records, transfer away, history and tickets (`pages.md` §3; `AccountDomains.php:12-17`). The page itself is not shown.

**Business (acting as a listing)**
- **`/shop/`** shows the business's plans, packs, Services, and a Coming list: creator collaborations, licensing, and email/text/push packs (`shop/index.php:55-74`).
- **`/shop/domains.php?for=listing:<id>`** buys a domain for the business (`shop/domains.php:20-23`). This needs Domain (edit) and Plan & billing (edit) on the team (`AccountDomains.php:105-117`, `:263`).
- **`/business/<id>/domains`** is the business's Domains tab (`shop/index.php:53`). Its storefront domain shows there read only (0169 §1a).
- **Portal → Storefront & pages** (Core and up; 0139 §5a). The route is not shown, and `app/business/storefront.php` is not shown. It covers:
  - set-up, the shop name and theme;
  - logo and cover;
  - pages (Draft, Live or Hidden);
  - Preview, Go live and Pause;
  - Connect your domain: bring your own, or buy one through Name.com (0139 §5b).

**Staff**
- **Market 360° → Storefronts** and **Market 360° → Custom domains** (0139). Admin routes are not shown. `Storefront::all` (`:351-360`) is the data source for the first.

---

## (b) How it works underneath

**Storefront (`app/lib/Storefront.php`)**
- **Feature and stages:** the plan feature is `storefront` (`:16`). Stages are Building, Live and Paused (`:17`).
  - `start` creates a storefront as Building with the Canyon theme (`:140-152`).
  - `setStatus` allows only Live or Paused, and re-checks the plan before going Live (`:189-197`).
- **Showing to the public:** `showing` requires status Live, a listing that isn't suppressed, and a plan that allows it (`:200-206`).
- **Plan gate:** `Plan::allows(MarketBiz::tier(...))` (`:106-110`).
- **Shop names versus GO Codes:**
  - A name is 3–40 letters, digits and single hyphens. It may not be reserved (`:38-40`), may not look like a code (6 characters from the code alphabet, `:41`, `:75-79`), may not match an existing `short_codes` row, and must be unique (`:94-104`).
  - `looksLikeShop` is the router's test (`:82-86`).
- **Routing:**
  - `resolve` reaches a location only under its brand (`:128`, `:132`).
  - `url` prefers the storefront's own domain, then the parent brand's domain, then trvgo.us (`:58-72`).
- **Locations:** a storefront can be made a location of a brand only if the editor also runs the brand. This is checked through the `$mayParent` callback (`:171-182`).
- **Look:**
  - There are four themes plus block styles (`:19-36`).
  - An accent below 3:1 contrast against the theme's cards is refused (`:167`). Between 3:1 and 4.5:1 it is kept with a warning (`:304-311`). The WCAG contrast calculation is at `:314-323`.
  - `style` and `blockVars` feed the block kit (`:329-348`).
- **Photos:**
  - Only the business's own ready photos can be used (`:223-233`).
  - These are mirrored into a public "Storefront" gallery album, together with photos found in its page blocks (`:240-285`).
- **Preview:** an HMAC link keyed on the ISO week, accepting this week's key and the one from 7 days earlier (`:209-218`).

**Storefront domains (`app/lib/Domains.php`, table `business_domains`)**
- **Switches:**
  - `DOMAINS_MODE` controls the storefront's Connect your domain. `DOMAIN_SALES_MODE` controls domain sales (`:14-20`, `:45-61`).
  - `mock` is refused outside a local test (`:48`, `:59`).
  - Test mode is visible to the team only (`:81-103`).
- **Stages:** Added → DNS pointed → Certificate coming → Live; Failed; Removed (`:31`).
- **Bring your own:**
  - `add` refuses Traversence's own domains and a domain another business has connected (`:176-202`).
  - It requires a storefront on Core or above (`:205-210`).
  - The records a business must add are an A record (plus AAAA if set), `www` as a CNAME to `<app>.fly.dev`, and SES DKIM CNAMEs (`:164-171`, `:213-228`).
- **Checking:**
  - `check` looks up DNS with `dns_get_record` (`:233-249`). If the domain points at the edge, it asks Fly for a certificate; when the certificate is ready, the domain goes Live and `link` sets `storefronts.domain` (`:255-289`).
  - A live domain whose DNS has been wrong for 3 days becomes Failed and is unlinked (`:264-267`).
  - Fly GraphQL calls pass `StagingGate::hold` (`:335`).
- **Buying:**
  - `buy` re-runs the search and refuses a premium name or a higher price than was shown (`:386-397`).
  - It charges with `Billing::chargeExtra`, unless the call counts as no real money (`:400-401`).
  - It registers through Name.com's Core API with `privacyEnabled: true`, `autorenewEnabled: false` and an idempotency key (`:405-406`).
  - If registration fails in live mode, it refunds through `QuickBooksPay::refundCharge`. If the refund also fails, it logs a SECURITY line (`:410-413`).
  - It then sets the records (`:421-428`).
- **Pricing:** the price is Name.com's cost plus `DOMAIN_MARKUP_CENTS`, which defaults to 500 (`:350-353`). The TLDs offered include `.app` and `.ai` (`:33`).
- **Registrant:** US-only contact details and phone number (`:431-443`).
- **Auto-renew, transfer and renewal:**
  - Auto-renew can be switched on or off (`:446-451`).
  - Transfer unlocks the domain and returns the authorisation code (`:454-464`).
  - Renewal charges first, then renews at Name.com (`:467-484`).
- **Name.com price list:** cached for 12 hours (`:492-515`).

**Standalone domains (`app/lib/AccountDomains.php`, tables `account_domains` and `domain_cart`, plus the Wallet)**
- **Model:** a domain belongs to an account, either a member or a listing. `business_domains` is left untouched (`:2-25`).
- **Open or closed:** `open` checks database readiness, the sales mode, team-only visibility in test mode, and that live mode is not paired with Name.com's sandbox. It also checks whether Name.com is down (`:74-86`).
- **Search and quote:**
  - Search covers the typed name and the TLD list (`:132-176`).
  - Terms are 1, 2, 3, 4, 5 or 10 years, limited to what Name.com allows for that ending (`:41`, `:190-210`).
  - `quote` gives Name.com's real total for the chosen term, plus the markup once a year (`:228-244`).
- **Cart:**
  - The buyer must tick 18+, and `AgeGate` records it (`:265`, `:283`).
  - The price is re-checked (`:273`).
  - A sandbox check runs for test buys (`:274-278`).
  - A domain already registered through Traversence is refused (`:281-282`).
  - The registrant's contact details are stored as JSON in `domain_cart.who` (`:285`).
- **Renewal price:** never just the markup alone (`:305-318`).
- **Paying (`pay`):**
  - It re-validates the items, picks a wallet card, creates the order, and writes `order_lines` with Name.com as vendor and its cost in `order_line_costs` (`:386-432`).
  - It charges the card. A decline voids the order; no answer leaves `card_unknown` for staff (`:436-448`).
  - After that, the order is paid (`:449-453`).
  - The rest of the flow is not shown.

**Shop pages**
- `shop/index.php` runs Add to cart through `Go.go(... plan_add ...)` into `/qr/cart.php` (`:107-115`).
- `shop/domains.php` delegates to `tv_domains_view` (`:34`), which is not shown.

---

## (c) Decisions

| ADR | What it rules here |
|---|---|
| **0139** | Storefronts load at `trvgo.us/<shop>`, and traversence.com stays the source for editing, cart and checkout. The router serves shops alongside codes (amends 0094). Shop names can never look like codes. Brand/location paths are allowed. One canonical address: a connected domain, with trvgo.us redirecting to it. No Cloudflare; Fly.io holds certificates. Domains can be bought through Name.com or brought. Domain fees aren't refundable once registered. Steps 5a and 5b are recorded as built, with later notes: the switch split, the Core API move, Fly's token format, and the staging gate. |
| **0169 §1a** | Domains become a standalone product: Shop → Domains plus a Domains tab on every account. A storefront *uses* a domain rather than owning it. There are two products, Domain Purchase and Domain Renewal, each with Name.com as vendor. Its own switch is `DOMAIN_SALES_MODE`. Built as release `domains-wallet` on 2026-11-17. **App 6, the Storefront app,** will join account domains to storefronts. The App store lifecycle (§1–§6) is not built. |
| **0137 §8** | Email: "Connect your domain" is one step covering both storefront and email DKIM records, from Core up. Its plan for Cloudflare custom hostnames is superseded by 0139 §3. |
| **0098** | One order system. Storefront sales are "kind 2": the business's own processor at 0% commission, and "Traversence never holds it". Domain sales are kind 1, Traversence's own sales. |
| **0096** | The GO Code context, which shares trvgo.us with shops. |

These are referenced but not attached, so their content is not shown: 0094, 0135, 0141, 0154, 0158, 0159, 0165, 0166, 0033 (cited in the PRD, Tier 3).

---

## (d) Live vs coming

**Built (per the docs and the code shown)**
- **Storefront 5a and domain connection 5b:** recorded as built on 2026-11-02 (0139).
  - They are gated at run time by `DOMAINS_MODE`, which defaults to off. With it off, the card says "opens soon" (`Domains.php:47`, `:110`).
  - 0139 states that Fly's API, Name.com's API and the Caddy config were "not tested here".
- **Shop → Domains and the Domains tab:** `pages.md` marks them "Built (domains-wallet, 2026-11-17)", not "Live".