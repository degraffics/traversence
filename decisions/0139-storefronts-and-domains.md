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
