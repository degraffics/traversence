# Page Structure & Routing Architecture

*Version: 2026-10-09 (claiming built, decisions/0070; the backend moved to app/, decisions/0069)*
*Governance tier: Routing — the single source of truth for URLs and page build status.*

*Regenerated 2026-10-08 from the site files themselves (the TRAVERSE-3WAY-SYNC mirror as of its last run, 2026-10-07, plus
the files uploaded since), replacing the 2026-09-17 version, which had drifted both ways: it listed 14 routes as built
that were never on the site, and missed most of what was built from `decisions/0043` on. Every route below is a real
file on the site unless it's in **Designed, not built**. The ADR column is the decision records the file names in its
own header. Status words: **Live**; **Live, placeholder** (the page says it's a stand-in); **Moved** (forwards to the
new home); **Remove** (left over, should come off the server).*

## 1. Public pages

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/` (`index.php`) | Home: the two ways in, Let's Explore and Get Local | 0058 | Live |
| `/discovery/` | Let's Explore: overview, map, regions, outdoors, things to do, journeys, the Trip Planner (`?view=plan`) | 0053, 0055, 0056, 0058, 0059 | Live |
| `/directory/` | Get Local: businesses near you, list and map | 0044, 0053, 0058 | Live |
| `/directory/classic.php` | The earlier directory page | 0053 | Live, superseded by `/directory/` |
| `/hub/?hub=` `?geo=` `?cluster=` | One landing page for a hub, geo-hub or cluster | 0043, 0047, 0048, 0058 | Live, placeholder |
| `/listing/view.php?id=` | A business's page (hero, engage bar, comments, the Link button) | 0044, 0047, 0052, 0053, 0058, 0062 | Live |
| `/listing/location.php?id=` | Where a listing is: move the pin, "I'm here" | 0058 | Live |
| `/place/landmark.php?id=` | A natural landmark (USGS names) | 0058 | Live |
| `/place/recreation.php?id=` | A public recreation place (Recreation.gov) | 0048, 0058 | Live |
| `/experiences/view.php?id=` | One experience | 0058, 0059 | Live |
| `/journeys/view.php?id=` | One journey | 0056, 0058 | Live |
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
| `/gbp-landing-traversence.html` | Google Business Profile audit landing page | — | Live |
| `/terms.php`, `/privacy.php` | Terms and privacy | — | Live, placeholder: not real legal text, to replace before launch |

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
| `/user/profile.php?id=` | A person's public profile (only what anyone may see) | 0047, 0058 | Live |
| `/user/journeys/`, `/user/journeys/compose.php` | Your journeys; write or edit one (contributors) | 0056, 0058 | Live |
| `/user/experiences/` | Your experiences | 0059 | Live |
| `/dashboard.php` | Old address of the business portal | — | Moved to `/listing/businessportal.php` |

## 4. Business owners

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/listing/businessportal.php` | Your business: claims and where each stands, plan, a question about your listing, and the listing editor (`?id=`) | 0070 | Live |
| `/listing/confirm-referral.php` | Confirm a Tier 2 referral (token link) | — | Live |

## 5. Admin

All admin pages sit in the admin shell (left rail by job, no breadcrumbs, decisions/0055).

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/admin/adminportal.php` | Admin home: tiles for each admin area | — | Live, placeholder: its header says the page's own check "is not access control" (it holds no private data) |
| `/admin/helper.php` | The helper: what needs a person, one item at a time | 0062 | Live |
| `/admin/claims.php` | Claims: to confirm (overdue first), requests on hold, owner changes waiting, disputes. `?doc=` opens a claim's document (stored outside the site folders in `traversence-private/claims/` next to `.env`, never synced; folder locked to the site account (0700/0600) with a deny-all `.htaccess`; deleted 72 h after a decision) | 0070 | Live |
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

## 6. API endpoints

### Public and signed-in (site pages call these)

| Route | Purpose | ADR | Status |
|---|---|---|---|
| `/api/search.php` | Universal search, typing and Enter | 0053, 0060, 0061, 0063, 0066, 0067 | Live |
| `/api/search_listings.php` | Directory search | 0044, 0053, 0058, 0068 | Live |
| `/api/suggest.php` | Older type-ahead for the directory box | — | Live |
| `/api/suggestion.php` | A visitor tells us something (missing place, fix, closed) | 0053 | Live |
| `/api/categories.php` | Category tree (plain names) | 0068 | Live |
| `/api/place.php` | "Where is this?" resolver; Auto-detect lookup | 0058 | Live |
| `/api/places.php` | Your saved places | — | Live |
| `/api/set-home-location.php` | Set your home place | — | Live |
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

| Route | What it is | Why remove |
|---|---|---|
| `/eng/directoryengine.php` | An early directory engine | Not used by any page |
| `/user/api/bootstrap-super-admin.php` | One-time Super-Admin setup | Excluded from the sync on purpose; delete from the server once used |

*Checked 2026-10-08 against OneDrive `website/`: `tv-version.php`, the nested `website/` copy and `index-gamer.php` were
already deleted. The sync's GitHub mirror (`sync-storage/`) never removes a file once copied there, so it can list files
the site no longer has; check OneDrive before treating a row as live.*

Delete from both the server and OneDrive `website/` before a sync run: the sync copies a file that exists on only one
side back to the other, and keeps no record of deletions.

## 8. Designed, not built

Kept from the earlier version of this file so the designs aren't lost. None of these files exist on the site.

| Route | Purpose | Designed in | Since |
|---|---|---|---|
| `/` split landing | Dual-gateway landing | — | `/` shows both ways in today, without a separate split page |
| `/{hub-slug}`, `/{hub-slug}/{geohub-slug}`, `/{hub-slug}/{geohub-slug}/{cluster-slug}` | Clean hub, geo-hub and cluster addresses | 0013 | `/hub/?hub=` etc. is the placeholder today |
| `/map` | Interactive corridor map | — | The map is now a full-screen view inside pages (decisions/0053 §4b), not a route |
| `/listing/{slug}` | Clean listing addresses | — | `/listing/view.php?id=` today |
| `/guide/{slug}` | Editorial articles with a `[directory_feed]` shortcode | — | `/guide/how.php` and `/guide/resources.php` are different (crawler-built guides) |
| `/privacy/dsar`, `/api/privacy/dsar-verify.php` | Data access and deletion requests | 0026 | |
| `/api/documents/upload.php`, `/admin/api/documents/view.php` | Supporting documents | 0017 | Built differently: claim documents go through `listing/api/claim.php` and `/admin/claims.php?doc=` (0070) |
| *(Railway worker)* document OCR and Five-Ws classification | Reads uploaded documents | 0017 | |
| `/listing/api/managers/invite.php`, `remove.php` | Co-managers for a listing | 0018 | |
| `/listing/api/referral/create.php` | Create a referral | — | `referral/request.php` exists instead |
| `/admin/login.php` | Admin sign-in | 0029 | Admin pages use the normal sign-in |
| `/dmca-notice.php`, `/api/dmca/counter-notice.php`, `/admin/dmca.php`, `/admin/dmca-settings.php`, `/admin/api/dmca/agent-info.php` | DMCA notices, counter-notices, review, agent registration | 0019 (Proposed) | |
| `/admin/listings.php` | Pending-listing review | 0022 | Covered today by `/admin/crawler-review.php` and `/admin/listing-intake.php` |
| `/admin/review-queue.php` | Auto-seeded cluster review | 0022 | Partly covered by `/admin/cluster-tools.php` |
| `/admin/hubs.php`, `/admin/clusters.php` | Hub and cluster management | 0022 | Partly covered by `/admin/cluster-tools.php` |
| `/admin/users.php` | User management | 0022 | |

## Notes

**Reserved paths win over hub slugs** (confirmed 2026-09-23): a real top-level folder (`directory`, `discovery`,
`listing`, `admin`, `api` and the rest) is matched before any request is treated as a hub slug. Not re-checked
2026-10-08: `.htaccess` isn't in the sync mirror.

**"Get Local" and "Let's Explore"** are the names visitors see for `/directory/` and `/discovery/`.
