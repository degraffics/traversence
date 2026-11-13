# Pages: the page tree's register

*Site audit P6 (decisions/0145 §1a; Jason approved the calls 2026-11-13): this file replaces `routes.md`, kept as it was in
`site-audit/archive/routes-2026-11-13.md`. Every page on the server is here (live) or in `archive.md` (archived or removed);
what's designed and not built is under **Coming**. `site-audit/page-tree.md` is generated from the live mirror and checked
against this file on every audit run (rule R09); `site-audit/routes-check.md` lists what's out of step. A release that adds,
moves or removes a page updates this file in the same release.*

*Status words: **Live**; **Live, placeholder** (the page says it's a stand-in); **Moved** (forwards to the new home);
**Built (… zip)** (delivered, live once its release is published). Pages reached by a rewrite rather than a link (the admin's
and the portal's routers and endpoints, the sitemap) are live like any other.*

## 1. Public pages

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/` (`index.php`) | Home: the two ways in, Let's Explore and Get Local | 0058 | Live |
| `/discovery/` | Let's Explore: overview, map, regions, outdoors, things to do, journeys, the Trip Planner (`?view=plan`) | 0053, 0055, 0056, 0058, 0059 | Live |
| `/directory/` | Get Local: businesses near you, list and map | 0044, 0053, 0058 | Live |
| `/directory/classic.php` | The earlier directory page | 0053 | Live, superseded by `/directory/` |
| `/hub/?hub=` `?geo=` `?cluster=` | One landing page for a hub, geo-hub or cluster | 0043, 0047, 0048, 0058 | Live, placeholder |
| `/hub/build.php?city=&state=&q=` | A guide built while you wait, for a searched place with none (never indexed) | 0147 | Live once uploaded |
| `/listing/view.php?id=` | A business's page (hero, engage bar, comments, the Link button) | 0044, 0047, 0052, 0053, 0058, 0062 | Live |
| `/listing/location.php?id=` | Where a listing is: move the pin, "I'm here" | 0058 | Live |
| `/place/landmark.php?id=` | A natural landmark (USGS names) | 0058 | Live |
| `/place/recreation.php?id=` | A public recreation place (Recreation.gov) | 0048, 0058 | Live |
| `/experiences/view.php?id=` | One experience | 0058, 0059 | Live |
| `/journeys/view.php?id=` | One journey | 0056, 0058 | Live |
| `/journeys/suggested.php?id=` | One suggested journey, built by rules from a guide's places | 0146 | Live once uploaded |
| `/journeys/`, `/journeys/compose.php` | Old addresses for managing journeys | 0058 | Moved to `/user/journeys/` |
| `/community/` | Public community groups: find, join or start one | 0047 | Live |
| `/community/group.php?id=` | One community group | 0047 | Live |
| `/guide/how.php?id=` | A how-to guide the crawler gathered from official pages | 0058, 0063 | Live |
| `/guide/resources.php?id=` / `?area=` | A resource guide: who to call in an area for what isn't a business | 0058, 0061 | Live |
| `/market/` | Market, the fourth construct (post and connect) | 0007, 0030 | Live, placeholder |
| `/faq.php` | The FAQ (`includes/faq.md`, grown with every build) | 0057 | Live |
| `/our-approach.php` | Our approach to linking, in plain words | 0050, 0051, 0052 | Live |
| `/join.php?i=` | An invite link: remembers it and sends the person to sign up | 0047 | Live |
| `/claim.php?listing=` | Claim a listing: email code (Managed by owner), text code to the Verify number (✓ Verified), other ways, other locations, plan. `&dispute=1` questions a listing's owner; `?new=1` adds a business (to Review); `&mode=contribute` suggests details | 0070, 0062 | Live |
| `/vendor.php?v=` | A vendor's own page: all their work orders, each opening its full work order (`/wo.php?w=`); private links, 30 days, then Request a new link (emailed) | 0101, 0150 | Live |
| `/wo.php?w=` | One work order for its vendor: items, specs, ship-to, files, their status buttons; 30 days, then Request a new link | 0101, 0150 | Live |
| `/shop/` | The Traversence Shop: what we sell, shown for who's looking (a member, the business you're acting as, a guest); plans and packs pay on the Plans page, GO Codes in the cart | 0154 | Built (channel-shop-earned zip) |
| `/terms.php`, `/privacy.php` | Terms and privacy | — | Live, placeholder: not real legal text, to replace before launch |
| `/ai/` | The AI page (Ask Traversence) | — | Live (P6, 2026-11-13) |
| `/data-deletion.php` | How to delete your data | — | Live (P6, 2026-11-13) |
| `/gallery/` | The Gallery: your photos and files | — | Live (P6, 2026-11-13) |
| `/gallery/album.php` | One Gallery album | — | Live (P6, 2026-11-13) |
| `/gallery/api.php` | The Gallery's own actions | — | Live (P6, 2026-11-13) |
| `/gallery/drop.php` | Drop files into the Gallery | — | Live (P6, 2026-11-13) |
| `/gallery/m.php` | One Gallery item | — | Live (P6, 2026-11-13) |
| `/market/cart.php` | The Marketplace cart | — | Live (P6, 2026-11-13) |
| `/market/download.php` | A bought download | — | Live (P6, 2026-11-13) |
| `/market/item.php` | One Marketplace item | — | Live (P6, 2026-11-13) |
| `/market/order.php` | A Marketplace order | — | Live (P6, 2026-11-13) |
| `/market/rules.php` | The Marketplace rules | — | Live (P6, 2026-11-13) |
| `/market/ticket.php` | A ticket bought on the Marketplace | — | Live (P6, 2026-11-13) |
| `/market/view.php` | One Marketplace post | — | Live (P6, 2026-11-13) |
| `/pages/form.php` | A form on a built page | — | Live (P6, 2026-11-13) |
| `/pages/sitemap.php` | The sitemap for search engines (reached by its address, not by links) | — | Live (P6, 2026-11-13) |
| `/pages/view.php` | A page built with the page builder | — | Live (P6, 2026-11-13) |
| `/plans.php` | Plans and prices | — | Live (P6, 2026-11-13) |
| `/promo.php` | A promotion's page | — | Live (P6, 2026-11-13) |
| `/q-file.php` | A file opened from a QR code | — | Live (P6, 2026-11-13) |
| `/qr/` | GO Codes and QR codes | — | Live (P6, 2026-11-13) |
| `/qr/cart.php` | Buying GO Codes | — | Live (P6, 2026-11-13) |
| `/qr/manage.php` | Managing your GO Codes | — | Live (P6, 2026-11-13) |
| `/takedown.php` | Ask us to take something down (0133) | — | Live (P6, 2026-11-13) |
| `/two-step.php` | Two-step sign-in | — | Live (P6, 2026-11-13) |
| `/verify-listing.php` | Verify a listing (from its link) | — | Live (P6, 2026-11-13) |
| `/email/unsubscribe.php` | Unsubscribe (from the link in our emails) | — | Live (P6, 2026-11-13) |

## 2. Accounts and sign-in

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/login.php`, `/register.php` | Sign in; create an account (18+, consent) | 0014, 0047, 0058, 0062 | Live |
| `/forgot-password.php`, `/reset-password.php` | Password reset | — | Live |
| `/verify-email.php` | Email confirmation landing page | 0014 | Live |
| `/email-revert.php` | Undo an email change (type the original address) | — | Live |

## 3. Member dashboard (signed in)

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/user/dashboard.php` | The member Dashboard: Pulse, Address Book, links, saved searches, roles | 0047, 0049, 0052, 0054, 0055, 0056 | Live |
| `/user/profile.php?id=` | A person's public profile (only what anyone may see); opens on its Posts tab | 0047, 0058, 0153 | Live |
| `/user/post.php?id=` | One post on its own page, for whoever it's shared with (what the Pulse, notices and shared links open) | 0153 | Built (moments-keep zip) |
| `/user/profile.php?id=&tab=videos` | The profile's Videos tab: the Channel (kept videos from public posts) and playlists, with a short link and QR code | 0153 §7 | Built (channel-shop-earned zip) |
| `/user/journeys/`, `/user/journeys/compose.php` | Your journeys; write or edit one (contributors) | 0056, 0058 | Live |
| `/user/experiences/` | Your experiences | 0059 | Live |
| `/dashboard.php` | Old address of the business portal | — | Moved to `/listing/businessportal.php` |
| `/user/accounts.php` | Your accounts (switch between them) | — | Live (P6, 2026-11-13) |
| `/user/delete-account.php` | Delete your account | — | Live (P6, 2026-11-13) |
| `/user/market/` | Your Marketplace posts | — | Live (P6, 2026-11-13) |
| `/user/notice.php` | One notice | — | Live (P6, 2026-11-13) |
| `/user/orders.php` | Your orders | — | Live (P6, 2026-11-13) |
| `/user/restore-account.php` | Restore a deleted account | — | Live (P6, 2026-11-13) |
| `/user/staff-link.php` | Link your staff login (from its link) | — | Live (P6, 2026-11-13) |
| `/user/transfer.php` | Hand over an account or listing (from its link) | — | Live (P6, 2026-11-13) |

## 4. Business owners

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/listing/businessportal.php` | Your business: claims and where each stands, plan, a question about your listing, and the listing editor (`?id=`) | 0070 | Live |
| `/listing/confirm-referral.php` | Confirm a Tier 2 referral (token link) | — | Live |
| `/business/` | The business portal (the admin, scoped to a business) | 0109 | Live (P6, 2026-11-13) |
| `/business/router.php` | Draws the portal's sections (reached by rewrite) | 0109 | Live (P6, 2026-11-13) |
| `/business/api.php` | The portal's endpoint (reached by rewrite) | 0109 | Live (P6, 2026-11-13) |
| `/business/files.php` | A business's files | 0109 | Live (P6, 2026-11-13) |
| `/business/pay-connect.php` | Connect a business's payments | 0109 | Live (P6, 2026-11-13) |

## 5. Admin

All admin pages sit in the admin shell (left rail by job, no breadcrumbs, decisions/0055).

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/admin/adminportal.php` | Admin home: tiles for each admin area | — | Live, placeholder: its header says the page's own check "is not access control" (it holds no private data) |
| `/admin/helper.php` | The helper: what needs a person, one item at a time | 0062 | Live |
| `/admin/claims.php` | Claims: to confirm (overdue first), requests on hold, owner changes waiting, disputes. `?doc=` opens a claim's document (stored outside the site folders in `traversence-private/claims/` next to `.env`, never synced; folder locked to the site account (0700/0600) with a deny-all `.htaccess`; deleted as soon as the claim is decided; none are kept) | 0070 | Live |
| `/admin/insights.php` | How the platform is used: sections, routes between them, searches (counts only) | 0058 | Live |
| `/admin/crawler.php` | Crawler overview: worker, queue, what went live | 0046, 0048, 0061 | Live |
| `/admin/crawler-review.php` | Crawler review: listings to approve, possible duplicates | 0046, 0051, 0053 | Live |
| `/admin/auto-imports.php` | Listings the crawler published on its own (confidence-gated) | 0044, 0046 | Live |
| `/admin/listing-intake.php` | Point the crawler at a website; review what it proposes | 0022, 0029, 0046 | Live |
| `/admin/listing-edit.php?id=` | Edit a live listing | 0053 | Live |
| `/admin/sources.php` | Websites that confirm places for the directory | 0045, 0046 | Live |
| `/admin/identities.php` | Each listing's facts by the 5 W's, with their sources | 0062 | Live |
| `/admin/geocode.php` | Turn street addresses into map points (Census geocoder) | 0053 | Live |
| `/admin/cluster-tools.php` | ZIP population import, micro-cluster merge, cluster management | 0042, 0046 | Live |
| `/admin/search-index.php` | Build the 5 W search index | 0053 | Live |
| `/admin/search-misses.php` | Missed searches, drafts, words for each kind | 0061, 0063, 0064, 0067 | Live |
| `/admin/search-learning.php` | What search learned from what people open | 0061 | Live |
| `/admin/search-oversight.php` | What changed in how search understands people | 0061 | Live |
| `/admin/search-demand.php` | Where people look and what for (state → county → town) | 0066 | Live |
| `/admin/resource-guides.php` | Phone lines and services that answer a situation | 0061 | Live |
| `/admin/how-guides.php` | How-to guides: asked for, found, published | 0063 | Live |
| `/admin/stories.php` | Guide stories for place pages | 0043 | Live |
| `/admin/journeys.php` | Journey management (editor queue) | 0058 | Live |
| `/admin/experiences.php` | Experiences review | 0059 | Live |
| `/admin/reports.php` | Reports of community posts and groups | 0047 | Live |
| `/admin/landmarks.php` | Natural landmarks review (held, sensitive) | 0058, 0066 | Live |
| `/admin/nations.php` | Tribal nations and their visitor rules | 0058, 0066 | Live |
| `/admin/recreation.php` | Recreation.gov places: hide any that shouldn't show | 0048 | Live |
| `/admin/router.php` | Draws every module section (`/admin/<module>/<section>`, reached by rewrite) | 0109 | Live (P6, 2026-11-13) |
| `/admin/api.php` | The admin's one endpoint (`/admin/api/<module>/<tool>`, reached by rewrite) | 0109 | Live (P6, 2026-11-13) |
| `/admin/client-order.php` | A client's order | — | Live (P6, 2026-11-13) |
| `/admin/content-template.php` | Content templates | — | Live (P6, 2026-11-13) |
| `/admin/data.php` | Data tools | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/ecommerce.php` | E-commerce | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/guide-edit.php` | Edit a guide | — | Live (P6, 2026-11-13) |
| `/admin/listing-add.php` | Add a listing | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/listing-new.php` | A new listing | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/manifest.php` | A manifest | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/nexus.php` | Nexus | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/order.php` | An order | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/people.php` | People | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/products.php` | Products | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/promotions.php` | Promotions | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/quickbooks.php` | QuickBooks | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/rebuild.php` | Rebuild | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/review-item.php` | A review item | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/safety.php` | Safety | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/sales.php` | Sales | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/search.php` | Search tools | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/setup.php` | Setup | — | Live (P6, 2026-11-13) |
| `/admin/short-codes.php` | Short codes | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/sitemap.php` | The site map | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/staff-levels.php` | Staff levels | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/staff-profile.php` | A staff profile | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/staff.php` | Staff | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/suggestion.php` | A suggestion | — | Live (P6, 2026-11-13) (a working tool, archive.md) |
| `/admin/vendors.php` | Vendors | — | Live (P6, 2026-11-13) (a working tool, archive.md) |

## 6. API endpoints

### Public and signed-in (site pages call these)

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/api/video/jobs.php`, `/api/video/results.php` | The video worker's queue and reports (Bearer `VIDEO_WORKER_TOKEN`; `workers/video`) | 0153 §8 | Built (video-cart-trial zip) |
| `/api/upgrade.php?feature=` | The upgrade sheet's choices (`js/tv-upgrade.js`): storage, longer recording, plans; reads only | 0154 §5 | Built (channel-shop-earned zip) |
| `/api/guide-paths.php` | The site audit's guide paths: every live guide, its suggested journeys and each stop with what our data says about it now (the checks' token only, `HEALTH_TOKEN`; read only) | 0145, 0146 | Built (Phase 4, not delivered yet) |
| `/stg-enter.php` | Staging only: the way in from the live admin's Open on staging (a link the live site signs, good for 10 minutes, once): staging's pass, the staging bar, a test account | 0145 Phase 5 | Built (releases3 zip) |
| `/api/stg-bar.php` | Staging only: the staging bar's line to the live site (its signed cookie and own header) | 0145 Phase 5 | Built (releases3 zip) |
| `/api/release-bar.php` | Live only: answers the staging bar, signed with RELEASE_BAR_KEY; acts with the permissions the bar carries | 0145 Phase 5 | Built (releases3 zip) |
| `/api/staging-updates.php` | Staging only: runs staging's waiting migrations when a release goes there (staging's HEALTH_TOKEN) | 0145 Phase 5 | Built (releases3 zip) |
| `/api/search.php` | Universal search, typing and Enter | 0053, 0060, 0061, 0063, 0066, 0067 | Live |
| `/api/search_listings.php` | Directory search | 0044, 0053, 0058, 0068 | Live |
| `/api/suggest.php` | Older type-ahead for the directory box | — | Live |
| `/api/suggestion.php` | A visitor tells us something (missing place, fix, closed) | 0053 | Live |
| `/api/categories.php` | Category tree (plain names) | 0068 | Live |
| `/api/place.php` | "Where is this?" resolver; Auto-detect lookup | 0058 | Live |
| `/api/places.php` | Your saved places | — | Live |
| `/api/nearby_hubs.php` | Hubs near a point | — | Live |
| `/api/open-places.php` | OpenStreetMap businesses where we have no listings yet | 0065 | Live |
| `/api/tags.php` | The @ picker | 0051 | Live |
| `/api/engage.php` | Likes, comments, reports on content pages | 0058 | Live |
| `/api/hero-media.php` | A listing's or profile's hero photos and videos | 0058 | Live |
| `/api/journeys.php` | Journeys: read, write | 0056 | Live |
| `/api/guest-inquiry.php` | A guest asks a listing a question | 0052 | Live |
| `/api/pin.php` | Where a listing is: read, move the pin | 0058 | Live |
| `/api/locate.php` | Place an approximate listing at its street address | 0058 | Live |
| `/api/count.php`, `/api/out.php` | Counts only: page views and flow; calls, directions, website, contact | 0066 | Live |
| `/api/auth/*` | `session`, `login`, `logout`, `register`, `verify-email`, `change-password`, `password/request-reset`, `password/reset`, `email/request-change`, `email/confirm-change`, `email/revert` | 0047, 0058, 0062 (register) | Live |
| `/user/api/*` | `profile`, `people` (Address Book), `links`, `consent`, `context`, `pulse`, `marketing`, `business-messages`, `community`, `referrals`, `saved-searches` | 0047, 0049, 0052, 0053, 0055 | Live |
| `/listing/api/*` | `claim` (every claim action, disputes and the owner's answer), `evidence`, `update-metadata` (owner edits; name, phone, address and website wait for an admin until verified), `my-listings`, `vouch`, `referral/request`, `referral/confirm` (Tiers 2–3: future upgrades, not offered). `verify/tier1` answers 410 (retired by 0070) | 0070, 0062 | Live |
| `/api/admin/intake/*` | `candidates`, `review`, `existing`, `crawl`, `clusters` (admin only) | — | Live |

### Off-site worker (Railway crawler, bearer token)

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/api/crawl/jobs.php`, `results.php`, `verify.php` | Crawl jobs, findings, second look | 0044 | Live |
| `/api/crawl/refresh.php`, `sources.php` | Refresh live listings; read regular sources | 0045 | Live |
| `/api/crawl/npi.php`, `irs.php`, `rec.php` | Monthly NPI, IRS exempt-organization and Recreation.gov loads | 0048 | Live |
| `/api/crawl/landmarks.php` | Monthly natural landmarks load | 0058 | Live |
| `/api/crawl/geocode.php` | Map points on a schedule | 0053 | Live |
| `/api/crawl/learn.php` | Search learning pass | 0061 | Live |
| `/api/crawl/identity.php`, `usage.php` | Build a listing's identity; web search usage | 0062 | Live |
| `/api/crawl/guides.php` | How-to guides | 0063 | Live |
| `/api/crawl/targets.php` | Targets from search demand and new kinds | 0066, 0067 | Live |
| `/api/ingest.php` | Older ingestion endpoint | — | Live |
| *(no route)* `workers/crawler/` | The worker itself, on Railway | 0044 onward | Live |

### Command line only

| File | Purpose | ADR | Status |
|---|---|---|---|
| `app/scripts/geocode_listings.php` | Map points, command-line version | 0053 | Live |
| `app/scripts/import_zip_population.php` | ZIP population import | 0042 | Live |
| `app/scripts/recluster_auto_seeded.php` | Merge single-ZIP auto-seeded clusters | 0042 | Live |

## The backend: app/ (no routes)

Since 2026-10-09 (decisions/0069) the code library, data, migrations, scripts and shared page parts live in `app/`,
next to `website/` and never served: `app/lib/`, `app/data/`, `app/ui/`, `app/bootstrap.php`, `app/middleware/`,
`app/migrations/`, `app/scripts/`. The `.htaccess` refuses `app/` and the backend's old places inside `website/`.
Removed with the move, broken before it: `api/directory.php`, `market-placeholder/`, `image/includes/`,
`scripts/reconcile_clusters.php`.

## 7. Left over on the server: remove

None (P6, 2026-11-13): `/eng/directoryengine.php` is gone and `/user/api/bootstrap-super-admin.php` is in `archive.md`.

## 8. Coming: designed, not built

So the designs aren't lost. None of these files exist on the site. Those Jason archived on 2026-11-13 (P6) are in
`archive.md` → Removed with what replaced them.

| Route | Purpose | Designed in | Since |
|---|---|---|---|
| `/` split landing | Dual-gateway landing | — | `/` shows both ways in today, without a separate split page |
| `/{hub-slug}`, `/{hub-slug}/{geohub-slug}`, `/{hub-slug}/{geohub-slug}/{cluster-slug}` | Clean hub, geo-hub and cluster addresses | 0013 | `/hub/?hub=` etc. is the placeholder today |
| `/listing/{slug}` | Clean listing addresses | — | `/listing/view.php?id=` today |
| `/guide/{slug}` | Editorial articles with a `[directory_feed]` shortcode | — | `/guide/how.php` and `/guide/resources.php` are different (crawler-built guides) |
| `/privacy/dsar`, `/api/privacy/dsar-verify.php` | Data access requests (the export) | 0026 | Coming: deleting an account is built (`/data-deletion.php`, `/user/delete-account.php`) |
| *(Railway worker)* document OCR and Five-Ws classification | Reads uploaded documents | 0017 | |
| `/admin/login.php` | Admin sign-in | 0029 | Admin pages use the normal sign-in |
| `/dmca-notice.php`, `/api/dmca/counter-notice.php`, `/admin/dmca.php`, `/admin/dmca-settings.php`, `/admin/api/dmca/agent-info.php` | DMCA notices, counter-notices, review, agent registration | 0019 (Proposed) | Coming: a "later wave" in the manifests; takedowns today go through `/takedown.php` (0133) |
| `/admin/listings.php` | Pending-listing review | 0022 | Covered today by `/admin/crawler-review.php` and `/admin/listing-intake.php` |

## Editor zones

*Decisions/0161 §9: pages whose content the page builder writes to the database (`Blocks`, decisions/0159). Check-out
(`tools/page-audit/checkout.py`) maps a page to its files from the routes above (`claim --page /directory/`), and a claim
on a file here says so, so a code change is checked against what's been built in its zones. Add a row when a zone is added.*

| Zone | Files |
|---|---|
| `home.after-doors` | `website/index.php` |
| `page.<id>` | `website/pages/view.php` |

## Notes

**Reserved paths win over hub slugs** (confirmed 2026-09-23): a real top-level folder (`directory`, `discovery`,
`listing`, `admin`, `api` and the rest) is matched before any request is treated as a hub slug. Not re-checked
2026-10-08: `.htaccess` isn't in the sync mirror.

**"Get Local" and "Let's Explore"** are the names visitors see for `/directory/` and `/discovery/`.
