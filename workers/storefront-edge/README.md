# The storefront edge (Fly.io)

Businesses' own domains (decisions/0139 §3, the Marketplace's step 5b) point at this small app on Fly.io. Fly holds a
certificate for each one (the site adds them through Fly's API when a domain's DNS checks out) and hands each visit to
Caddy, which passes it to `trvgo.us` with the domain asked for (`X-TV-Host`) and a shared key (`X-TV-Edge`). The trvgo.us
router (`trvgo/index.php` → `app/ui/storefront-view.php`) trusts the domain only with that key, and shows the storefront.

## One-time setup (Jason)

1. **Fly.io account** (fly.io), with a card. Install `flyctl` on your computer and `fly auth login`.
2. In this folder: `fly launch --copy-config --name trvgo-edge --no-deploy` (keep the name, or put yours in `.env` below).
3. A shared key, any long random string: `fly secrets set STOREFRONT_EDGE_KEY=<the key>`.
4. `fly deploy`. Then `fly ips list`: note its IPv4 (and IPv6). If it shows only a shared IPv4, that works for most
   domains; `fly ips allocate-v4` gives a dedicated one (a small monthly cost) if a domain's certificate won't issue.
5. A deploy token for the site: `fly tokens create deploy -a trvgo-edge` (it can manage this app's certificates only).
6. The site's `.env` (File Manager):
   ```
   DOMAINS_MODE=live
   FLY_API_TOKEN=<the deploy token>
   FLY_APP=trvgo-edge
   FLY_IPV4=<its IPv4>
   FLY_IPV6=<its IPv6>
   STOREFRONT_EDGE_KEY=<the same key as step 3>
   ```
7. Check: `curl https://trvgo-edge.fly.dev/__edge` answers `ok`.

Selling domains also needs Name.com's reseller API in `.env`: `NAMECOM_USER`, `NAMECOM_TOKEN`, `NAMECOM_ENV=sandbox`
(then `live`), and optionally `DOMAIN_MARKUP_CENTS` (our margin a year, default 500).

## How a visit goes

`pinecafe.com/hours` → DNS (A to the edge, or `www` CNAME to `trvgo-edge.fly.dev`) → Fly (its certificate) → Caddy →
`https://trvgo.us/hours` with `X-TV-Host: pinecafe.com` → the router finds the live domain's storefront and shows its
Hours page. Nothing is kept on the edge; it has no database and no copy of anything.
