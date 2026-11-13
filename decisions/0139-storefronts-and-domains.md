# ADR 0139: Storefronts on trvgo.us and Businesses' Own Domains

**Status:** Accepted (Jason, 2026-11-01). Settles the open points of `marketplace-architecture.md` §8 (the Community
session's hand-off), 0135 §9 and 0137 §8 "DNS and hosting". Builds as the Marketplace's step 5: 5a the storefront,
5b Connect your domain.

## 1. Where a storefront loads

- **On trvgo.us itself:** `trvgo.us/<shop>` shows the business's storefront, and the address bar stays there (Jason).
  This amends 0094's "serves only the router, never the site's pages": the trvgo.us router now serves storefronts as
  well as short codes and GO Codes, reading the same database through `app/` and keeping no copy.
- **traversence.com stays the source:** the business edits its listing, catalog, items and pages there, and the
  storefront shows them at once (0033). Signing in, the cart and checkout stay on traversence.com: a storefront's
  Buy now and Add to cart lead there (its card pages and their security policy, 0098 and 0135).
- **The trvgo/ folder joins the 3-way sync** as a third pair (mirrored to `sync-trvgo/`), so the live router is visible
  to every build and its changes go through zips and the sync like the rest. Its `.htaccess` stays unmirrored.

## 2. Shop names and GO Codes share trvgo.us

- A shop name can't look like a code: exactly 6 characters from the code alphabet (`ShortCodes`) isn't allowed, and
  no new code is issued that matches a shop name.
- **Locations under a parent brand:** the router allows `trvgo.us/<brand>/<location>` from the start (and
  `brand.com/<location>` once the brand connects a domain), as the AOS gives locations a section on the parent's site.
- **One address for search engines:** once a domain is connected, it's the storefront's canonical address and
  `trvgo.us/<shop>` redirects to it.

## 3. Businesses' own domains (Connect your domain, Core and up)

- **No Cloudflare** (Jason, 2026-11-01): trvgo.us stays on Bluehost's nameservers. This replaces the hand-off's
  "only trvgo.us moves to Cloudflare" and 0137 §8's plan for Cloudflare custom hostnames.
- **The certificates and routing run on Fly.io:** a small proxy app holds a certificate for each connected domain
  (added through Fly's API when the domain checks out) and passes each visit to `trvgo.us` with the domain asked for
  in a header the router trusts only from the proxy (a shared secret). Railway was looked at first: it caps custom
  domains per service (2 on Hobby, 20 on Pro) and won't raise it for one domain per customer, so it doesn't fit. Fly
  has no cap on domains per app; its price per domain (first 10 free) is checked on Fly's pricing page when set up.
- **Two ways in, both built in 5b** (Jason, 2026-11-01: "Build both domain sales and bring your own option"):
  buy a new domain through us, or bring one the business already owns.
- **Bring your own:** in Connect your domain, the business types its domain; we show the records (the domain's A record
  and `www` CNAME, pointing at the Fly app; and the three DKIM records for email, 0137 §8); the same DomainCheck checks
  them and turns each on as it passes. Stages: Added → DNS pointed → Checked → Live; Failed.
- **Buy a domain, through Name.com** (Jason, 2026-11-01): Connect your domain searches for a name and buys it through
  Name.com's reseller API. It's registered in the business's name with WHOIS privacy (the business owns it and can
  transfer it away); its DNS records for the storefront and email are set through the same API, so it's live with
  nothing typed. We're the seller: billed through QuickBooks like GO Codes, renewing yearly on the card on file with a
  reminder before; domain fees aren't refundable once registered. The price is Name.com's cost plus a markup set in
  Plan features.

## Build order

- **5a:** the trvgo/ folder into the sync; shop names; `trvgo.us/<shop>` (the listing, its Shop items, its pages from
  `SitePages`) with the brand's ready themes plus the business's logo, cover and one accent color; the portal's
  Storefront & pages for the business; the admin's Storefronts section.
- **5b:** Connect your domain, both ways: buying through Name.com and bringing your own; the Fly proxy, DomainCheck,
  the domain workflow, the admin's Custom domains; email DKIM beside the storefront's records.

## Step 5a built (2026-11-02): the storefront on trvgo.us

Built on the **23:57 UTC sync** (2026-11-01), the first with `trvgo/` mirrored (`sync-trvgo/`; the sync's PR #6).
- **The router** (`trvgo/index.php`): a path that isn't a code and looks like a shop (`Storefront::looksLikeShop`)
  goes to `app/ui/storefront-view.php`; anything else is as before, including "This code isn't active". Codes are
  always made 6 characters from the code alphabet (`ShortCodes::fresh`), so a shop name can't collide; a name matching
  any existing code is refused as well.
- **`Storefront`** (`app/lib/Storefront.php`, table `storefronts`, migration `2027-01-22_storefronts.sql`): one per
  listing; its name (3 to 40 letters, numbers and hyphens; not a code's shape; not reserved; unique); Building → Live →
  Paused; four ready themes (Canyon, Sage, Paper, Night) with an accent color checked for contrast, a tagline, a few
  lines about it, a logo and a cover; a parent brand for a location (only when the person runs both).
- **The page:** the listing's public details (`VCard::listingCard`, so a confidential listing's address stays off),
  its published items (each links to its item page on traversence.com, where buying, offers and messages happen), the
  brand's live locations, and its live pages as tabs (`SitePages`, owner = the listing; pictures and links in them
  point at traversence.com). No script, no session, no cookie: its policy forbids every script. Canonical address and
  LocalBusiness data for search engines. Not showing (building, paused, its plan below Core, its listing hidden): a
  plain "isn't open right now" page; its team sees it through a signed preview link (7 days, from the portal).
- **The portal's Storefront & pages** (live, Core and up): set up, name and look, logo and cover (uploaded through the
  one uploader, target `storefront_photo`, into the business's gallery and its storage; or chosen from its photos; kept
  in a public "Storefront" album), its pages (add, write, Draft / Live / Hidden), Preview, Go live and Pause (Publish).
  Its own domain shows as "coming next".
- **The admin:** Market 360° → Storefronts (live): every storefront, its status, plan and pages; our team can pause one.
- **The listing's Shop** links to the storefront when it's showing.
- **Tested** on a local copy (MariaDB, PHP's own server for trvgo.us and the site): 40 storefront checks (names and
  the code rule, plan, building, preview, live, pages, paused, plan ending, a hidden listing, locations, existing codes,
  capitals, an unknown code, trvgo.us alone), 10 portal actions, the upload target's refusals; phone and desktop
  widths with no sideways scroll and no console errors.
- **`trvgo/.htaccess` changes too** (a dotfile, not mirrored; uploaded by hand with the zip). Read from OneDrive's copy:
  it sent only letters and digits (`^([A-Za-z0-9]{1,16})/?$`, the codes) to the router and 404'd the rest, so
  `trvgo.us/round-valley` would never have reached it. One rule is added after the codes' rule: shop paths,
  `^([A-Za-z0-9-]{3,40}(?:/[A-Za-z0-9-]{1,80}){0,2})/?$` → `index.php?c=$1`. Everything else in it is as it was
  (https, www, the 404 for anything else, dotfiles denied).

## Step 5b built (2026-11-02): Connect your domain, bought or brought

Built on the **00:13 UTC sync** (2026-11-02, the one with 5a live).
- **`Domains`** (`app/lib/Domains.php`, table `business_domains`, migration `2027-01-24_business_domains.sql`): one domain
  per storefront; Added → DNS pointed → Certificate coming → Live; Failed; Removed. Our own domains, malformed ones and
  one another business has connected are refused.
- **Bring your own:** the portal shows the records (A for the domain to the edge's IPv4, AAAA if there is one, CNAME
  `www` to `trvgo-edge.fly.dev`; the three DKIM CNAMEs for email when SES is set up). DNS is checked by the site
  (`dns_get_record`); once it answers with the edge, the edge is asked for a certificate (Fly's GraphQL API:
  `addCertificate`, then its status); when it's ready, the domain is Live and becomes the storefront's address.
- **Buy one, through Name.com's reseller API** (v4; `NAMECOM_ENV` sandbox first): a search offers the typed name and
  .com, .net, .shop, .store, .co, .us, .biz with our price a year (Name.com's cost + `DOMAIN_MARKUP_CENTS`, default
  $5.00); premium names are referred to us. Buying re-checks the price (never more than shown), charges the plan's card
  on file (`Billing::chargeExtra`: a QuickBooks receipt and email, like a pack; recorded as kind `domain`, which the
  migration adds to `subscription_charges.kind`), then registers it with the business as registrant, admin, tech and
  billing contact (WHOIS privacy on), then sets its records at Name.com. If registering fails, the charge is refunded
  (`QuickBooksPay::refundCharge`); if that fails too, it's logged as SECURITY for our team to refund by hand.
- **Renewal:** two weeks before a bought domain runs out, the card is charged and Name.com renews it for a year (once;
  a failure is noted and the business's Pulse says so). Renewal can be switched off; **Move it to another registrar**
  unlocks it and shows its transfer code once.
- **The edge** (`workers/storefront-edge/`, Caddy on Fly.io): passes each visit to trvgo.us with `X-TV-Host` (the
  domain) and `X-TV-Edge` (the shared key), drops any copies a visitor sends, and sends `www.` to the bare domain. The
  router serves a business's domain only with the key, and only its storefront (codes aren't served there).
- **One address:** a live domain is the storefront's canonical address; `trvgo.us/<shop>` and its pages redirect to it
  (301), a brand's locations sit under its domain (`brand.com/location`). DNS gone for 3 days: Failed, the storefront
  goes back to trvgo.us, the business's Pulse says what to fix.
- **The job** `domains_tick` (every 15 minutes, `Scheduler::AUTO`): checks waiting domains, live ones once a day,
  renews due ones.
- **The admin:** Market 360° → Custom domains (live): every domain, its stage, how it came, its expiry; Check now and
  Disconnect.
- **Test mode:** `DOMAINS_MODE=mock`, a local test only (refused on the server, like `MARKET_PAY_ENV`): no Fly, no
  Name.com, no charge.
- **Tested** locally in mock mode: 39 checks (cleaning, plan, own domains refused, bring your own through DNS waiting,
  the wrong answer, the certificate and Live; the edge with and without its key, an unknown domain, codes refused there;
  the 301s from trvgo.us, a location under the brand's domain; DNS gone for 3 days; search, taken names, the price
  guard, the registrant's details, buying, transfer code, renewal once, auto-renew); 9 portal actions; the storefront's
  40 and the earlier Marketplace suites again; the card at phone width. **Not tested here** (not reachable from this
  session): Fly's and Name.com's real APIs, and the Caddy config itself: they're checked in their sandboxes first.

### Jason's one-time setup for 5b
1. **Fly.io:** follow `workers/storefront-edge/README.md` (deploy the edge, set its key, note its IPs, make a deploy token).
2. **Name.com:** the reseller account's API tokens (sandbox and live).
3. **`.env`** (File Manager): `DOMAINS_MODE=live`, `FLY_API_TOKEN`, `FLY_APP=trvgo-edge`, `FLY_IPV4`, `FLY_IPV6`,
   `STOREFRONT_EDGE_KEY` (the same as the edge's), `NAMECOM_USER`, `NAMECOM_TOKEN`, `NAMECOM_ENV=sandbox` (then
   `live`), optionally `DOMAIN_MARKUP_CENTS`. Until `DOMAINS_MODE` is set, the card says "opens soon".

**Test mode and the switch in Settings (2026-11-02, Jason: "need a way to toggle this for testing").** `DOMAINS_MODE`
and `NAMECOM_ENV` are now among the plain settings changeable in Admin → Settings (`EnvSettings`, decisions/0125; the
tokens and the edge's key stay in `.env` by hand). `DOMAINS_MODE` takes **off** (the card says "opens soon"), **test**
(only our team sees Connect your domain; the real edge and real DNS, so a domain we own connects for real; buying uses
Name.com's sandbox whatever `NAMECOM_ENV` says, and no card is charged, nor on renewal) or **live** (businesses on Core
and up, real charges). `mock` stays a local test only. Built on the 01:13 UTC sync: `app/lib/Domains.php`,
`app/lib/EnvSettings.php`, `app/business/storefront.php`, `app/admin/tools.php`.

**Since 2026-11-03 (decisions/0141):** 0141 §2.3, §7: Publish opens or pauses the shop; it never adds or removes the listing or its map pin from discovery. Layout themes (Retail, Venue, Lodging, Service) come in Phase E, suggested by the business's main item kinds.

## Name.com refusing our sign-in (2026-11-03)

Seen live: `NAMECOM_ENV=sandbox` with the live username gave "Permission Denied" in a business's domain search. Now:
the sandbox has its own pair, `NAMECOM_TEST_USER` (the `-test` username) and `NAMECOM_TEST_TOKEN` (the live pair is tried
when they're missing); a refusal is remembered, businesses see "Buying a domain through us is paused for a moment…"
(and can still connect one they own), and our team gets one Pulse case (`namecom:down`) with the error, the Name.com
link and **Check again**; the 15-minute domains job re-checks, so buying reopens by itself. `DOMAINS_MODE=live` with
the sandbox refuses to sell, so no real charge is ever made for a sandbox domain.

**Note (2026-11-13, decisions/0158):** storefront changes become drafts until Publish, with versions and Roll back, and the staging bar on the team's preview. Publish is the team role's `publish` permission. Scheduling waits for Jason.

**Note (2026-11-13, decisions/0159):** storefront pages are built in Storefront Studio (blocks, themes, the team's Publish) and stay script-free on trvgo.us.
