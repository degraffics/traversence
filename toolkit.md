# Traversence toolkit

Every tool on the site, what it does and where it lives. Taken from the code on 2026-10-24: the admin manifests
(`app/admin/manifests.php`), the admin tools (`app/admin/tools.php`), the business portal (`app/business/portal.php`),
the member dashboard (`website/user/dashboard.php`) and the pages, endpoints and scripts under `website/` and `app/`.
Status: **live**, **partial** (works, not finished) or **coming** (planned, not built).

Paths: `website/` is the web root (what visitors reach), `app/` is never served (decisions/0069). An admin section's
address is `/admin/<module>/<section>`.

---

## 1. Visitors (no account needed)

| Tool | What it does | Where |
|---|---|---|
| Home | Search box, nearby places and listings | `website/index.php` |
| Search (Quick Search) | Places, listings, guides; filters at its foot; "near <place>" | `website/api/search.php`, `app/lib/UniversalSearch.php`, `website/js` (TvSearch) |
| Directory (Get Local) | Browse businesses by category and place | `website/directory/` |
| Listing page | A business: hero, details, map, call/directions/website (counted), albums, likes, comments | `website/listing/view.php` |
| Place pages | A town or area with its listings and guides | `website/place/` |
| Explore | Journeys, experiences, guides, how-tos | `website/journeys/`, `website/experiences/`, `website/guide/`, `website/discovery/` |
| Community / hubs | Regional hubs and community pages | `website/community/`, `website/hub/` |
| Nexus page | A brand or system and its locations | `website/nexus.php` |
| Market | The marketplace | `website/market/` |
| Storefront pages | A business's own pages and contact form (feeds its CRM) | `website/pages/` |
| GO Codes & QR | Make QR codes and short links (trvgo.us); free 5 a month, paid types; cart and guest checkout | `website/qr/`, `website/api/qr.php`, `website/api/go-codes.php`, `trvgo/index.php` |
| Plans | Business plans and prices | `website/plans.php` |
| Claim a listing | Claim a business, or add a new one, with verification | `website/claim.php`, `website/verify-listing.php` |
| Suggest a change | Tell us a listing is wrong or missing | `website/api/suggest.php`, `website/api/suggestion.php` |
| Client order review | A client approves proofs and pays (card) | `website/order.php`, `website/api/client-order.php`, `website/api/order-proof.php` |
| Business quote page | A business's customer approves a quote, uses a discount code, pays | `website/q.php`, `website/q-file.php` |
| Guest photo drop | Add photos to an event album with no account | `website/gallery/drop.php` |
| Shared albums | Public and unlisted albums (portfolio picks and downloads when signed in) | `website/gallery/album.php`, `website/gallery/m.php` |
| FAQ | The site's FAQ (source: `app/ui/faq.md`) | `website/faq.php` |
| Legal & about | Terms, privacy, our approach, data deletion | `website/terms.php`, `privacy.php`, `our-approach.php`, `data-deletion.php` |
| Promo landing | Promotion codes landing page | `website/promo.php` |
| Sign in / up | Email and password, Google, Apple, Microsoft; Turnstile captcha; forgot / reset password; email verify | `website/login.php`, `register.php`, `forgot-password.php`, `reset-password.php`, `verify-email.php`, `website/api/auth/` |

## 2. Members (signed in)

Rail in `website/user/dashboard.php`.

| Tool | What it does | Where |
|---|---|---|
| Dashboard + Pulse | Profile card with your Pulse (feed, notifications) | `website/user/dashboard.php`, `app/ui/pulse-ui.php` |
| Profile tabs | About, Gallery (newest 12 + albums), Contacts (private address book), Connections, Journeys; Check-ins, Events, Reviews **coming** | `website/user/dashboard.php`, `website/user/profile.php` (public view) |
| Gallery | All photos and videos: upload (videos up to 1 GB), timeline by date taken, albums (Public / Unlisted / Private), Personal / Portfolio / Brand & documents, People (invite, roles), guest uploads, QR, likes and comments, edit caption/alt/date, picks, watermarks, file versions | `website/gallery/`, `website/js/gallery.js`, `website/js/photo-viewer.js`, `app/lib/Gallery.php`, `app/lib/GalleryModes.php` |
| Journeys | Write and publish journeys with photos and pins | `website/user/journeys/`, `app/lib/Journeys.php` |
| Experiences | Your experiences | `website/user/experiences/` |
| Settings | Profile, default places, privacy, app settings, help | `website/user/dashboard.php` (sections) |
| Linked sign-ins | Link Google / Apple / Microsoft to your account | `website/user/accounts.php` |
| Delete account | Self-serve deletion with a 30-day restore | `website/user/delete-account.php`, `restore-account.php` |
| Notices | Standing notices, confirm, ask for a review | `website/user/notice.php` |
| Transfer | Take over a listing or group transferred to you | `website/user/transfer.php` |
| Context switcher | Act as yourself, a business (Owner/Manager…), a Steward or Admin | `app/ui/context-switcher.php`, `website/user/api/context.php` |
| Steward | Your communities; member reports and charter **coming** | `website/user/dashboard.php` (steward rail) |

## 3. Businesses (business portal)

`/business/<listing>/<tool>`: `website/business/router.php`, rail and modules in `app/business/portal.php`. Access by
team role (Team: Owner, Manager…) and plan features.

| Tool | Status | What it does |
|---|---|---|
| Overview | live | The business's home: messages, numbers |
| The listing / Edit listing | live | The public page and its editor (`website/listing/businessportal.php`) |
| Gallery | live | Its photos, videos, albums and brand files (`/gallery/?b=<listing>`) |
| Inbox | coming | Customer messages, questions, offers, form entries in one place |
| Customers | live | Its CRM (tool `growth/customers`) |
| Quotes & invoices | live | Quotes from the catalog with terms, deposit, tax and pay link; approved online → invoice (`business/quotes`, `app/lib/BizQuotes.php`) |
| Catalog | live | Items and services, price, private cost and markup, options (`backoffice/products`) |
| Orders | live | Approved quotes through their stages; emails at ready/shipped (`business/orders`) |
| Sales tax | live | Rates by state, city, ZIP (`backoffice/taxes`) |
| Shipping & pickup | live | Flat, by weight, free over, pickup, local delivery (`backoffice/shipping`) |
| Discount codes | live | Percent or amount off, dates, limits (`business/codes`) |
| Files | live | Proofs and shared files up to 1 GB each, counted against the plan's storage (sent through the site's uploader, target `biz_file`; served by `website/business/files.php`, `app/lib/BizFiles.php`) |
| Storefront & pages | coming | Its storefront on trvgo.us and its own domain |
| Marketing | coming | QR codes, short links, email campaigns |
| Reports | coming | Sales, margin, turnover, new and returning customers |
| Plan & settings | live | Plan, storage packs, team and roles, defaults (`website/plans.php`, `app/lib/Billing.php`, `app/lib/Team.php`) |

## 4. Admin (staff): 11 modules

Staff sign in with a TRV-#### ID, password and authenticator code (`website/admin/login.php`, `app/lib/StaffLogin.php`).
Router `website/admin/router.php`, one endpoint `website/admin/api.php`, widgets in `app/admin/widgets.php`, access by
`Access::can()` (`app/admin/Access.php`). Sections marked *bridge* show an older page inside the section until it's rebuilt.

### Dashboard (`/admin/dashboard/…`)
- **Today** (live): the daily steps, inbox, notifications, business numbers.
- **Quick actions** (live): everyday tools opened over the dashboard.
- **Helper** (live, bridge `/admin/helper.php`): obvious duplicates merged automatically; what needs a person.
- At hand: scan cards, client orders, listings, calls.

### Pulse (`/admin/pulse/…`)
- **Stream** (live): cases with severity, SLA, escalate, reply.
- **Stream settings** (live).

### Local (`/admin/local/…`)
- **Listings** (live): find, open and edit any listing; add one (`local/listings`).
- **Find, merge & bulk edit** (live, bridge `/admin/listings.php`).
- **Intake from a website** (live, bridge `/admin/listing-intake.php`): rebuild a business from its website (Nexus + locations).
- **Review** (live): visitor suggestions (`local/suggestions`) and the crawler's review queue (`/admin/crawler-review.php`).
- **Claims** (live, workflow `claim`).
- **Nexus** (live): brands and systems (`local/nexus`).
- **Identities** (live, bridge `/admin/identities.php`): website identity and same-site matching.
- **Landmarks** (live, workflow `landmark`, `local/places`).
- **Recreation sites** (live, bridge `/admin/recreation.php`).
- **Map pins** (live, bridge `/admin/geocode.php`).
- **Nations** (live): tribal nations' names, rules and land (`local/nations`).
- **Hidden places** (live, bridge `/admin/safety.php?sec=hidden`).
- **Taxonomy & clusters** (partial, bridge `/admin/cluster-tools.php`).
- **Single Home Rule** (coming).

### Explore (`/admin/explore/…`)
- **Journeys** (live, workflow `journey`), **Experiences** (live, workflow `experience`), **Stories** (live),
  **Resource guides** (live), **How-to guides** (live, workflow `how_guide`; ask the crawler for one).
- **Waypoints & routes** (coming), **Chameleon Filter** (coming).

### Market (`/admin/market/…`)
- **GO Codes & short links** (live, `market/codes`).
- **Storefront & pages** (live): our pages, drafts, SEO (`market/pages`, `app/lib/SitePages.php`).
- **Form entries** (live, `market/leads`): contact forms → CRM.
- **Post-and-Connect**, **Storefront Studio**, **Custom domains**, **Catalog rules**, **Gateways** (coming).

### Social (`/admin/social/…`)
- **Reports** (live, workflow `report`): reported content.
- **Standing** (live, workflow `standing`): the enforcement ladder, notices, limited mode.
- **Galleries** (live): shared albums and photos; take something off the site (never deletes) (`social/gallery_albums`, `social/gallery_items`).
- **Procedures** (live, bridge `/admin/safety.php?sec=procedures`).
- **Groups**, **Events** (coming).

### Growth (`/admin/growth/…`)
- **Calls today** (live), **Prospects** (live, pipeline for unclaimed and new listings, follow-ups, claim invites; workflow `lead`).
- **Customers** (live): the CRM: table, drawer, tags, notes, segments.
- **Scan cards** (live): business-card scanner (`app/lib/CardScan.php`, `app/ui/card-scanner.php`).
- **Add from the field** (live, bridge `/admin/sales.php?sec=field`).
- **Promotions** (live, workflow `promotion`): trials, % off, issue as GO Code, per-lead codes (`app/lib/Promotions.php`).
- **Territories** (live), **Funnel** (live).
- **Quotes & deals** (coming).

### Concierge (`/admin/concierge/…`)
- **Imports** (partial, bridge `/admin/auto-imports.php`).
- **Clients**, **Engagements**, **Service catalog**, **Asset studio** (coming): White Glove.

### Back-Office (`/admin/backoffice/…`)
- **Orders** (live): client orders (build, proofs, send, production; workflow `client_order`) and site orders (GO Codes).
  Order page: fix, credit, refund, chargeback (`/admin/client-order.php`, `/admin/order.php`).
- **Products** (live): the catalog. **Vendor orders** (live).
- **Price lists** (live): B2B lists by tag, volume breaks, price-for tester.
- **Taxes & shipping** (live): rates, shipping rules, tester.
- **Plans & subscriptions** (live, workflow `subscription`): trials, plan features, renewals.
- **Payments** (live, bridge `/admin/quickbooks.php`): QuickBooks connect, sandbox/production, card checkout, refunds, webhook.
- **Money reports** (partial, bridge `/admin/ecommerce.php?sec=reports`).
- **People** (live): members and staff. **Staff logins** (live). **Staff levels** (live): levels and departments.
- **Import & export** (partial).
- **Fulfillment**, **Inventory & COGS**, **Consent hub**, **DMCA queue**, **Verification vault** (coming).

### Insights (`/admin/insights/…`)
- **Insights** (live, `/admin/insights.php`), **Search workbench** (live), **Crawler** (live), **Sources** (live),
  **Data browser** (live, `/admin/data.php`).
- **5 W's lexicon**, **Emergency resources**, **Telemetry** (coming).

### System Configuration (`/admin/config/…`)
- **Workflows** (live): edit every workflow's stages and moves (`app/admin/Workflow.php`, `app/admin/workflows.php`).
- **Permissions** (live): who may see and do each tool.
- **Data separation** (live): checks that each business's data stays its own (`app/admin/DataCheck.php`).
- **Scheduled tasks** (live): cPanel cron jobs (`app/lib/Scheduler.php`).
- **Settings** (live, `/admin/settings.php`): setup cards (S3, AWS, mail…); "Change here" for plain `.env` settings
  (keys and passwords stay in `.env`, decisions/0125, `app/lib/EnvSettings.php`); **Move files** to S3.
- **Logs** (live): the action log. **Site map** (live, `/admin/sitemap.php`): every page, old ones too.
- **Templates**, **Branding & SEO**, **Notifications**, **Storage & backups** (coming).

## 5. Scheduled tasks (cPanel cron, `app/scripts/`)

| Script | What it does |
|---|---|
| `files_tick.php` | Moves files from before into owners' folders in S3, 300 at a time (every 10 min) |
| `move_files_to_s3.php` | The same move, by hand |
| `billing_tick.php` | Subscription renewals, trials ending, charges |
| `sales_tick.php` | Sales follow-ups and prospect timers |
| `standing_tick.php` | Standing notices' timers (enforcement ladder) |
| `ai_worker.php` | The queue of AI jobs (within the monthly AI cap) |
| `geocode_listings.php` | Map pins for listings without one |
| `recluster_auto_seeded.php` | Re-clusters auto-seeded places |
| `split_concepts.php`, `import_zip_population.php` | Data preparation (concepts, ZIP populations) |
| `staff-reset.php` | Resets a staff login from the command line |

## 6. Shared engines (`app/lib/`): what the tools are built on

- **Accounts & access:** `Auth`, `SocialAuth` (Google/Apple/Microsoft), `TwoStep`, `StaffLogin`, `Roles` (contexts),
  `Team` (business roles), `Scope` + `BusinessData` (each business's data walled off).
- **Uploads (decisions/0127):** one uploader for every file on the site: `js/tv-upload.js` (size checked first, over
  10 MB asks, progress bar, S3 in parts or pieces through the server, result beside the item), `website/api/upload.php`,
  `Uploads` (staging, sniffing, signed tokens, up to 1 GB). Each destination is a target in
  `app/lib/upload-targets.php`: order proofs and files, gallery photos/videos/files, guest drop, business files, hero,
  journey and profile photos, claim documents, card scans, GO Code logos, the census file.
- **Files:** `Store` (owner folders, S3 or the server), `S3`, `HeroMedia`, `BizFiles`, `Gallery`, `GalleryModes`;
  `website/media.php` (public photos from S3).
- **Money:** `Orders`, `ClientOrders`, `Products`, `Commerce` (tax, shipping), `PriceLists`, `Billing`, `Promotions`,
  `BizQuotes`, `BizCodes`, QuickBooks (`QuickBooksPay` and connector).
- **Content & data:** `Journeys`, `Experiences`, `MemberProfile`, `Nations` + `Landmarks` (public / private standard),
  `Reports`, `Standing`, `Customers`, `SitePages`, `HowGuides`, `UniversalSearch`, `Situations`, `Concepts`,
  the crawler (`app/lib/crawler/`).
- **Sharing:** short codes and QR (`Qr`, `QrIssue`, `trvgo/`), vCards (`website/api/vcard.php`).
- **Page parts** (`app/ui/`): app shell, header, page shell, engage bar (likes, comments, share, report),
  context switcher, Pulse, lead panel, card scanner, gallery parts, FAQ.
- **Browser helpers** (`website/js/`): `tv-ask.js` (site dialogs, no browser pop-ups; link wrapping past ModSecurity),
  `tv-password.js` (show/hide, checklist), `photo-viewer.js`, `gallery.js`, `link-button.js`, `admin-kit.js`.
