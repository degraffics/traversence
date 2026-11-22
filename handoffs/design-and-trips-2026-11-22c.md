# Design and trips: hand-off at the 2026-11-22 evening hold (written by session_01CXLi5grMsUgrRMJv3a71F9)

This session took over from session_01CXLi5jNQCN3zFZ2Fh3qUNf (`design-and-trips-2026-11-22b.md`). It stops at a clean hold
(the session manager, 2026-11-22). **Newest sync at the hold: 8d3afbd (Sync 23:05 UTC).** Nothing is held, sent or
waiting on Jason; no claims are held by this session (each ended when its release went live).

## 1. Live from this session (each reviewed by the session manager, approved by Jason in a pop-up, verified on the mirror)
| # | Release | Record |
|---|---|---|
| 254 | 3e: the Marketplace, checkout, storefronts' own pages and domains on the tokens, layouts kept; the owner's accent note under 4.5:1 | `r3e.md` |
| 255 | 3f's leftovers: sign-in, long text, standalone pages, member pages and the portal on the tokens; three contrast fixes | `r3f-leftovers.md` |
| 256 | The map release: map-view.js on the tokens; a trip's route lime over the umber casing, the chosen day (Jason: "Approve B", brand.md §4's note) | `map-tokens.md` |
| 257 | The location pop-up's Log in and Not sure? readable | `location-popup.md` |

## 2. Next
- **0174 R5, routes on our own site** (§5; Jason chose openrouteservice's free tier). Waiting: §5 says its terms (free plan
  limits, commercial use, the credit, whether routes may be stored) are read from its own site first, and this
  environment refuses `openrouteservice.org` and `account.heigit.org` (proxy 403). The session manager is asking Jason to
  allow both and will start R5 fresh when they're open. Nothing is claimed or built for it. A Control Board build
  session may claim faq.md, manifests.php, tools.php, Pulse.php and Notify.php: check `releases/checkouts.json` first.
- Release 3 (css-theme-brand §4) is done for public pages. The order pages (order.php, wo.php, user/orders.php, vendor.php)
  wait for the orders hold (Jason, 2026-11-21) for their colour pass.

## 3. Found, not done
- `app/ui/page-shell.php`'s "|" divider in taupe (3.83:1; `aria-hidden`; taupe is kept for rules): left as it is.

## 4. Harness notes (add to `design-harness/README.md`'s)
- After a worker restart, start the database again (`mysqld_safe --user=mysql &`) and both servers; sign in again.
- The listings realm needs `DB_LISTINGS_*` in `/home/user/.env` (pointed at `tvh`); its update triggers ("another
  business") block a direct UPDATE of a tenant table: drop the `tvb_upd_*` trigger in the harness to seed.
- Routes: `/business/api/…` → `business/api.php`, `/business/…` → `business/router.php` (added to `h/srv.php`).
- Leaflet 1.9.4 and leaflet.markercluster 1.5.3 from npm, served in place of cdnjs; the shared map opens at once with
  `select: <id>` (else it waits for a place and the location pop-up covers it).
- recolor.py (in `r3f-leftovers/`, `map-tokens/`) now leaves a declaration untouched when no colour changes; keep
  Leaflet's SVG options out of its reach (they take no var()).
