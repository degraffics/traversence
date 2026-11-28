# Platform atlas: media

*research/platform-atlas/media.md · Draft for human review*

**Sources.** The docs used are `charter.md`, `brand.md`, `prd.md`, `site-audit/pages.md` and decisions 0126, 0127, 0143, 0153 and 0154. The code is eight files from the sync mirror (`traverse-3way-sync/sync-app/lib/`, cited below as `app/lib/…`): `Gallery.php`, `GalleryModes.php`, `GalleryCatalogs.php`, `GalleryUses.php`, `Uploads.php`, `HeroMedia.php`, `VideoJobs.php` and `UploadThreads.php`.

**Partial view.** `Gallery.php` is cut off after line 496, so everything past the album cover function is not shown. Several files the docs name are not attached, and are marked "not shown" where they matter: `upload-targets.php`, `MemberPosts.php`, `StockLibrary.php`, `Store.php`, `S3.php`, the `js/` files, and the `/gallery/*.php` pages.

---

## (a) What each person can do, page by page

Statuses come from `site-audit/pages.md`. "Built (… zip)" means delivered but live only once its release is published.

### Resident and traveler (no account needed)

| Route | What they can do | Status |
|---|---|---|
| `/listing/view.php?id=` | See the hero photos or videos at the top of a business page. Where it has none, a labelled stock photo shows instead (0143 Built). The page also shows the business's public **Albums** (0126 Phase 3) and its **Videos** section (0153 build note). | Live |
| `/hub/?hub=` etc. | Place-guide hero, using the stock fallback (0143 Built). | Live, placeholder |
| `/place/landmark.php`, `/place/recreation.php` | Landmark and park photos found on Wikimedia Commons, with credit and licence (0143 notes, 2026-11-06 and 11-19). | Live |
| `/user/profile.php?id=` | A member's Gallery tab, holding their public albums and photos (0126). | Live |
| `/user/profile.php?id=&tab=videos` | The member's Channel and playlists, with a short link and QR code. | Built (channel-shop-earned zip) |
| `/gallery/album.php` | A public album, or an unlisted one opened by its link. | Live |
| `/gallery/drop.php?g=` | Add photos to an event album as a guest: name optional, no account (0126 Phase 2). | Live |
| `/gallery/m.php` | Serves each file after a permission check. | Live |

### Member (signed in)

| Route | What they can do | Status |
|---|---|---|
| `/gallery/` | Timeline grouped by year and month. Upload, Select, New album. Tabs for Albums, Stock photos and Documents. A Catalog filter (0143 zips 1–3; `GalleryCatalogs.php:14-21`). | Live |
| `/gallery/album.php` | Share sheet with QR code, invite people as View only or Can contribute, guest link, New link (which revokes the old one), Portfolio picks, downloads and watermark (0126). | Live |
| `/gallery/join.php` | Accept an album invitation (0126 Phase 2). | **Not in `pages.md`** |
| `/user/dashboard.php` | "What's on your mind?" composer and the Gallery tab (0153 zip 1; 0126 fix). | Live |
| `/user/post.php?id=` | One Moment on its own page. | Built (moments-keep zip) |
| `/shop/`, `/plans.php` | Storage packs, Plus and Pro, a free week of Pro, a single month of Pro, Keep in 4K (0154 Built). | `/shop/` Built; `/plans.php` Live |
| Profile **Snap** button | Send a Moment directly to someone (0153 zip 2). | Per ADR; route not listed |

### Business (team members with Catalog & content edit)

| Route | What they can do | Status |
|---|---|---|
| `/gallery/?b=<listing>` | The business's own gallery. Its Brand & documents albums keep file versions (0126 Phase 3; `GalleryModes.php:257-307`). | Live |
| `/api/hero-media.php` | Manage up to 8 hero photos or YouTube/Vimeo links (`HeroMedia.php:10, 52-60`). | Live |
| `/business/<id>` Overview | Post as the business and see its Pulse (0153 build note, `biz-overview`). | Live (`/business/` router) |
| Storefront cover | Pick a stock photo, or use "Let us pick" (0143). | Per ADR |
| Orders catalog | Artwork released from the business's own orders appears in its Gallery (0143 zip 6). | Per ADR |

### Staff

| Where | What they can do |
|---|---|
| Admin → Social → Galleries | Metrics, plus a "take it off the site" moderation action (0126 Phase 3). |
| System Configuration → Stock library (`config/stock`) | Search sources, tag, retire photos (0143). |
| Pulse upload threads | "New photo(s) of {place}" and "New artwork on Order #N" (`UploadThreads.php:97-147`). |

---

## (b) How it works underneath

### 1. One uploader (0127)

- **Start.** `Uploads::start` looks up the destination's own rules, checks the file against them, and stages it in the owner's `incoming/` folder (`Uploads.php:140-161`). The file then goes to S3 in parts, or in pieces through the server when S3 isn't set up.
- **Signed state.** The upload's state travels as a signed token valid for 24 hours, not in the session (`Uploads.php:163-180`).
- **Finish.** `finish` does four things (`Uploads.php:229-262`):
  - checks the file arrived at its full size;
  - checks the file's first bytes, except for a "loose" list of types where the name is believed (`:75, :251-255`);
  - re-runs the destination's rules;
  - hands the file to the destination and caches the result, so a repeated request gets the same answer.
- **Resume.** `status` lets another page carry an upload on (`:211-226`).
- **Destinations.** The per-destination definitions live in `upload-targets.php` (not shown).

### 2. Gallery as the hub for every file

- **Reusing a Gallery file.** A file already in the Gallery is sent as a reference (`do=gallery`), not re-uploaded. It is used where it is if the destination allows that (`pick`), otherwise copied server to server (`Uploads.php:270-320, 365-374`).
- **Files owned by records.** Order files reach other destinations through `fromRecord`, guarded by `RecordFiles::mayUse` (`Uploads.php:327-344`; `RecordFiles.php` not shown).
- **"Used in".** Each reuse is recorded in `gallery_uses` (`GalleryUses.php:40-49`) and summarised as "Used in …" (`:68-74`).
- **Catalogs.** These are derived from each photo's source and from the Marketplace and Storefront albums (`GalleryCatalogs.php:54-69`). Documents land in an auto-made private "Documents" album (`:27-37`).

### 3. Storage, visibility and serving

- **Tables.** Created by `Gallery::ensure` (`Gallery.php:41-68`).
- **Who may manage.** Members manage their own gallery. For a business, the team permission `catalog/edit` qualifies, with `HeroMedia::canManage` as a fallback (`Gallery.php:77-88`; `HeroMedia.php:21-28`).
- **Who may see** (`Gallery.php:216-229`): the owner; anyone, if the photo came from elsewhere and is public there; any user with an `admin_access` row, for Marketplace photos under review (`:233-241`); then by album (public, unlisted plus token, or invited).
- **Audience labels** for the viewer come from `audience()` (`:284-303`).
- **Photo processing.** Uploaded photos are re-encoded to a 2000 px copy, a 720 px card and a blur placeholder, which drops hidden data (`:361-395`). Hero photos get the same treatment at 2000 and 640 px (`HeroMedia.php:62-88`).
- **Videos.** `videoFromUpload` checks the file's first bytes and takes the poster frame from the browser (`Gallery.php:402-427`).
- **Serving.** `/gallery/m.php` and the signed-link logic are described in 0126; the code is not shown.

### 4. Album modes (`GalleryModes.php`)

| Mode | What the code does |
|---|---|
| Portfolio | Picks with a limit (`:147-164`), sending picks emails the owner(s) (`:167-185`). Download rules (`:213-233`). A watermark that can't be stepped round: it applies only when every way the viewer can see the photo is watermarked (`:89-96`). The watermark is drawn and cached per photo, size and text (`:99-124`). |
| Brand & documents | Whitelisted file types up to 1 GB (`:20-26`). A new version moves the current file to an old version number (`:296-300`). |
| Channel | Public, made on first use (`Gallery.php:147-155`). The Videos tab reads it (`:159-175`). |

### 5. Storage caps (0154)

- **Members.** `memberCap` adds the plan's allowance and earned storage (`Gallery.php:124-134`). Going over throws `MemberStorageFull`, which opens the upgrade sheet (`:137-143`).
- **Businesses.** Gallery bytes plus BizFiles usage are checked against the plan's allowance (`:110-120`).

### 6. Video worker (0153 §8)

- `VideoJobs::queue` makes a 720p copy, plus 1080p for plans that play HD (`VideoJobs.php:24-34`).
- `lease` hands out signed GET and PUT links valid for an hour, leases jobs for 20 minutes, and allows 3 tries (`:11-12, 37-59`).
- `result` records sizes from what is actually in storage, not what the worker reports (`:82`).

### 7. Staff threads (0176, text not shown)

- **Journey photos.** They go live at once ("post first") and raise one notice-level thread for the safety team (`UploadThreads.php:97-112, 128`). Reports on the journey join the same thread, with Take it down, Leave it up and Hide (`:173-191, 247-271`).
- **Order artwork.** Raises a thread for the orders team. The file check is greyed as "to come" (`:24-25, 165, 197-198`).

### 8. Not shown

`MemberPosts.php`, the `moments_tick` job, `StockLibrary.php`, `EarnedStorage.php`, `Billing.php`, `RecordFiles.php`, `Store.php`, `S3.php`, the browser scripts (`tv-upload.js`, `tv-art.js`, `tv-moments.js`, `tv-upgrade.js`), and the `/gallery/*.php` pages.

---

## (c) Decisions

| ADR | What it rules |
|---|---|
| **0126** | The Gallery is one module for every photo and video.<br>• Viewer and layout standards; public, unlisted and private albums with instant revocation.<br>• Files are never public addresses.<br>• Phase 2: likes, comments, invites, guest uploads, QR codes.<br>• Phase 3: viewer editing, business galleries, Portfolio, Brand & documents, admin moderation. |
| **0127** | One uploader for every file; a new upload place is a target, never a page. Amended 2026-10-29 so uploads carry on across pages. 2026-11-27 note: documents live on the platform, not as GitHub links. |
| **0143** | Stock library. Stock is allowed only in illustrative slots, never authentic ones, and is always labelled, with "Representative" where it isn't the actual place. Sources are Wikimedia, Library of Congress and Pixabay, commercial licences only, with no sensitive sites. Every file input goes through the Gallery, and staff have no personal Gallery. Commons place-photo finder. Amended by **0164 R9** (text not shown); where they disagree, 0164 rules. |
| **0153** | Every post is a Moment (72 hours or 7 days) unless kept. Snaps are Moments sent in chat. 120-second cap. Temporary bins in R2. Viewer lists name linked people, which amends 0050. Channel; seven reactions; feeds newest first with no ads. Video worker. Retention by law (§9). Posting limits (§10). |
| **0154** | Free covers the full social experience with 2 GB plus earned storage (+1 GB per 500 followers or per friend who joins, up to 25 GB). Plus and Pro are sold as tools; creators keep 100%. Business add-ons: creator collaborations and licensing. A 3% community budget. One upgrade sheet at the point of need. The Shop and one cart. |
| Referenced only | 0007 (plans sell tools, never placement), 0050 (counts only), 0058 §21 (hero media, per `HeroMedia.php:2`), 0094 (trvgo.us QR), 0101 (Collateral Center), 0109 (admin sections), 0124 (owner folders), 0135 (market review), 0151 (R2), 0164, 0176. Texts not shown. |

---

## (d) Live vs coming

### Live (`pages.md`)

- `/gallery/` and its `album`, `api`, `drop` and `m` pages
- `/api/hero-media.php`, `/api/engage.php`, `/listing/view.php`, `/user/profile.php`, `/user/dashboard.php`, `/plans.php`

### Built, waiting on release publication (`pages.md`)

- `/user/post.php`, the profile's Videos tab, `/shop/`
- `/api/upgrade.php`, `/api/video/jobs.php` and `/api/video/results.php`

### Coming or not built, per the ADRs and code

- **Order files:** the file check (resolution, bleed, size) and "Ask for a new file" (`UploadThreads.php:24-25`).
- **Shop add-ons:** creator collaborations, licensing, and email/text/push packs are listed in the Shop as coming (0154 Built).
- **Earned storage:** taking back space earned from accounts later removed as fake, and checking Turnstile per person (0154 Built).
- **Video:** copies for videos uploaded straight to the Gallery, server-side poster frames, a pinned video, and per-video short links (0153 notes).
- **Videos are not watermarked;** their posters are (0126).
- **Stock:** no Stock tab yet for guide illustrations, page banners or category tiles, and not used on the directory's own result cards (0143 "Not now").
- **NPS photos** wait for `NPS_API_KEY` (0143 note, 11-19). Unsplash and Pexels are "possible later".
- **Moments:** the ring on the profile photo, Highlights, and single-request uploads for small files (0153 zip 1 "Not yet").

### Register drift (worth fixing under rule R09)

These routes are named in ADRs but absent from `pages.md`:

- `/gallery/