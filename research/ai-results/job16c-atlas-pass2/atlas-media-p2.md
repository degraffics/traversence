# Platform atlas: Media

`research/platform-atlas/media.md` · **Draft for human review**

**Scope.** The Gallery (albums, the viewer, Portfolio, Brand & documents, the Channel), the one uploader, hero media, the Stock library, Moments, Snaps and Keep (their media side), the video worker, and the staff threads raised by uploads.

**What was visible.**
- Code is shown from the sync mirror `traverse-3way-sync/sync-app/lib/`. The decisions place this code at `app/lib/`, so paths below are written `app/lib/…` with mirror line numbers.
- `Gallery.php` is cut off at line 496, partway through the album functions. Everything after that is **not shown**: `saveAlbum`, `addToAlbum`, `role`, `canContribute`, `room`, `jpeg`, `blur` and `when`.
- These files are referenced but **not shown**: `MemberPosts.php`, `StockLibrary.php`, `upload-targets.php`, `Store.php`, `S3.php`, `RecordFiles.php`, `EarnedStorage.php`, `Billing.php`, every page under `website/`, and every JS file.
- These decisions are cited by the attached documents but are **not attached**: 0124, 0151, 0164, 0176, 0058, 0050, 0007, 0133.

---

## (a) What each person can do, page by page

Routes come from `site-audit/pages.md` unless a decision is named.

### Resident or traveler (visitor, signed out or in)

- **Listing page** (`/listing/view.php?id=`):
  - shows the hero photos and videos;
  - shows the business's public albums under **Albums** (0126 phase 3);
  - shows a Posts section and a **Videos** section (0153 zip 3, channel-shop-earned note);
  - when the listing has no photo of its own, shows a labelled stock photo marked "Representative" (0143).
- **Place guides** (`/hub/?hub=…`, a live placeholder): the hero falls back to a stock photo or a Commons place photo (0143, including the 2026-11-19 notes).
- **A person's profile** (`/user/profile.php?id=`):
  - the Gallery tab shows public albums and public photos (0126);
  - the Posts tab (0153);
  - the Videos tab, `&tab=videos`, shows the Channel and playlists. Its status is *Built*.
- **One post** (`/user/post.php?id=`): status *Built (moments-keep zip)*.
- **An album** (`/gallery/album.php`): opens a public album, or an unlisted one through its link or QR code. Unlisted albums are noindex (0126).
- **Guest upload to an event album** (`/gallery/drop.php?g=…`): no account needed (0126 phase 2).
- **Photo files** are served through `/gallery/m.php` after a permission check on every request.

### Member (signed in)

- **Gallery** (`/gallery/`):
  - timeline, albums, Select, New album;
  - catalogs (`?cat=`), a Documents tab, a Stock photos tab, and an **Orders** catalog (0143 zips 2–6).
- **Album** (`/gallery/album.php`):
  - Share sheet with a QR code, Edit & who sees it, New link, invite people as View only or Can contribute;
  - the album's mode can be Personal, Portfolio, Brand & documents or Channel.
- **Album invite** (`gallery/join.php`): named in 0126 but **not listed in `pages.md`**.
- **Dashboard** (`/user/dashboard.php`):
  - "What's on your mind?" composer (0153 zip 1);
  - the Gallery tab shows the newest 12 items and up to 4 albums (0126 fix).
- **Moments, Snaps and Keep** (0153): 72-hour or 7-day posts, Keep into the Gallery, Snaps sent from a profile's Snap button, and a "Seen by" viewer list.
- **Upgrades** (`/plans.php`, `/shop/`): the upgrade sheet appears when the Gallery is full or a recording reaches 2:00. Keep in 4K costs $0.99 and a single month of Pro costs $9.95 (0154).
- **Any file input on the site** opens the Gallery panel with **Your gallery**, **Upload new** and, in illustrative slots only, **Stock** (0143 zips 2–3).

### Business (team member)

- **Business Gallery** (`/gallery/?b=<listing>`): requires Team `catalog` edit rights or the listing's manage rights (`app/lib/Gallery.php:77-88`).
- **Business portal** (`/business/`, router):
  - Overview with posting as the business, the Pulse and Messages (0153 biz-overview note);
  - Gallery in the rail; the Storefront cover chosen from Gallery or Stock (0143).
- **Hero photos and videos** (`/api/hero-media.php`): up to 8 per listing (`app/lib/HeroMedia.php:10`).
- **Brand & documents** albums with file versions, and Portfolio client proofing (0126 phase 3).

### Staff (supporting role)

- **Admin → Social → Galleries:** metrics, tables, and "Take it off the site" (0126).
- **System Configuration → Stock library** (`config/stock`) (0143).
- **Upload threads in Pulse** (`app/lib/UploadThreads.php`).
- **Order pages:** "This order's files" in place of a personal Gallery (0143 zip 5).

---

## (b) How it works underneath

### The one uploader (0127): `app/lib/Uploads.php`

- **Limits.** Any one file is at most 1 GB (`:72`); the page asks before sending files over 10 MB (`:73`).
- **Start (`start`).**
  - Asks the target's `check` (via `plan`, `:120-128`).
  - Stages the file in the owner's `incoming/` folder (`:146`).
  - Then hands out either S3 multipart part URLs (`:148-153`) or a piece size for sending through the server (`:155-160`).
- **State token.** The upload's state travels in an HMAC-signed token, not the session (`:165-170`). It is checked for the same user id and for an age of one day or less (`:178`).
- **Finish (`finish`).**
  - Answers once even if asked again, through the PageCache key `upl:done:` (`:234-235`).
  - Checks that the file arrived whole (`:238-246`).
  - Re-runs the target's check (`:250`).
  - Sniffs the content against the declared type, except for the `LOOSE` extensions (`:75`, `:251-255`).
  - Calls the target's `done` (`:256`).
- **Files chosen from the Gallery.**
  - `fromGallery` (`:270-287`) and `gallery()` (`:296-320`) either reuse a Gallery photo where it sits (the target's `pick`) or copy it S3-to-S3 (`copyInto`, `:365-374`).
  - Both record the use through `GalleryUses::record`.
- **Files a record keeps.** `fromRecord` (`:327-344`) handles order files for staff and for the client they were released to. The permission check is `RecordFiles::mayUse`, which is **not shown**.
- **Resuming.** `status` (`:211-226`) lets another page or a reload carry on, trimming a cut-off piece back to the last whole one.
- **Cleanup.** `tidy` deletes stale staged files on about 1 in 20 requests (`:390-394`).
- **Where targets are defined.** Targets live in `app/lib/upload-targets.php`, which is **not shown**. 0127 lists 15 targets with their size limits.

### The Gallery: `app/lib/Gallery.php` (partly shown)

- **Tables** are created on first use (`:41-68`): items, albums, album items, album people, guest keys and guest items.
- **Album modes** are `social`, `portfolio`, `brand` and `channel` (`:34`).
- **Who may manage** (`canManage`, `:77-88`): a member manages their own; for a business, Team `catalog` edit rights, else `HeroMedia::canManage`.
- **Who may see** (`canSee`, `:216-229`):
  - the owner;
  - anyone, for a public item synced from elsewhere;
  - staff looking at Marketplace photos under review (`:233-241`);
  - anyone, for an item in a public album;
  - holders of the token for an unlisted album;
  - people invited to the album.
- **Storage limits** (`roomFor`, `:110-120`):
  - Members: plan, plus packs, plus earned storage (`memberCap`, `:124-134`).
  - Businesses: `BizFiles::allowance`.
- **Channel** (`:147-155`): one public album per owner, created on first use. `videos()` (`:159-175`) builds the Videos tab.
- **Syncing photos kept elsewhere.** `sync`/`syncBusiness` (`:178-189`, `:310-350`) join in journey, hero and profile photos where they live. A photo deleted at its source is dropped here too (`:347-349`).
- **Photo uploads** (`addPhoto`, `:363-395`): JPEG, PNG or WebP only. Each photo is rotated from EXIF and re-encoded to a 2000 px copy, a 720 px card (`:38-39`) and a blur placeholder.
- **Video uploads** (`videoFromUpload`, `:402-427`): the file's magic bytes are checked (`:408`), and the poster comes from a frame the browser captured.

### Album modes: `app/lib/GalleryModes.php`

- **Watermarking is applied only when it can't be stepped around.** No watermark is added if any way the person can see the photo has none (`watermarkFor`, `:89-96`). The stamp is tiled at 38% strength (`:99-124`).
- **Picks:** a pick limit (`:147-164`), and Send picks emails the owners (`:167-185`).
- **Downloads** (`canDownload`, `:213-233`): Brand albums always allow downloads (`:218`); Portfolio albums apply the none / picks / all rule and a per-person allowance.
- **Files and versions:** brand-file types are listed at `:20-25`; `fileFromUpload` (`:282-307`); versions at `:318-325`; `forget` removes originals, versions and watermarked copies (`:334-352`).

### Catalogs and uses

- `app/lib/GalleryCatalogs.php`:
  - catalogs are derived from where each item came from (`:54-69`);
  - `documentsAlbum` makes a private "Documents" Brand album on first use (`:27-37`).
- `app/lib/GalleryUses.php`: labels each destination (`:12-38`), records a row (`:40-49`), and builds the "Used in … and N more" summary (`:68-74`). The header says only the owner sees these (`:6`); the enforcing page is **not shown**.

### Hero media: `app/lib/HeroMedia.php`

- Up to 8 items (`:10`), 15 MB each (`:11`).
- A listing's hero is managed by any role other than visitor, through `ListingLocation::role` (`:21-28`).
- Photos are re-encoded with no original metadata kept (`:62-88`). Videos are YouTube or Vimeo embeds only (`:52-60`).

### Video worker queue: `app/lib/VideoJobs.php`

- Queues a 720p copy, and a 1080p copy when `$hd`, only when the file store is on (`:24-34`).
- `lease` hands out jobs with 1-hour presigned links and a 20-minute lease (`:37-59`), so the worker holds no storage keys.
- `result` checks each copy's real size in storage (`:82`) and retries up to 3 times (`:11`, `:71-75`).

### Staff threads: `app/lib/UploadThreads.php`

- **Place-photo threads** go to the safety team (`:97-113`). They offer Hide, Show again, Take it down and Leave it up, each through `Workflow::move` (`:247-271`).
- **Order-file threads** go to the orders team (`:136-147`).

### Stock library (0143)

`StockLibrary.php`, the source adapters in `app/lib/stock/`, `/api/stock.php` and `js/tv-stock.js` are described in 0143 but are **not shown**.

---

## (c) Decisions

| ADR | What it rules |
|---|---|
| **0126** | The Gallery is one module for every owner. It rules: <ul><li>the viewer and layout standard;</li><li>Public, Unlisted and Private albums, with new tokens revoking old links;</li><li>files served only through a checked `m.php`;</li><li>phase 2: likes and comments, invites, guest uploads, QR codes;</li><li>phase 3: editing, business galleries, Portfolio, Brand & documents, admin moderation.</li></ul> |
| **0127** | One uploader. Every file goes through `tv-upload.js`, `/api/upload.php` and `Uploads.php`. A new place to upload is a target, never a page. Uploads carry on across pages (2026-10-29). Task documents become a target (2026-11-27 note). |
| **0143** | Stock library. Stock is allowed only in *illustrative* slots, never *authentic* ones, and is always labelled. Licences must allow commercial use and changes. Sources: Wikimedia, Library of Congress, Pixabay. Every file input routes through the Gallery panel. Staff have no personal Gallery. Clients get an Orders catalog. Commons place photos follow the location and name rules. |
| **0153** | Moments (72 h or 7 days), Keep, Snaps, the 120 s cap, Temporary bins, held copies for reports, the viewer list (amends 0050), the Channel, the 7 reactions, the video worker, the fair-use caps and the community budget. |
| **0154** | Free, Plus and Pro tiers and their video quality. Creators keep 100%. Business add-ons (collaborations, licensing). A 3% community budget. One upgrade sheet. The Shop and one cart. Earned storage of +1 GB per qualifying follower or friend, up to 25 GB. |
| 0124, 0151, 0164, 0176, 0050, 0058, 0007 | Cited by the code or the decisions above; **not shown**. |

---

## (d) Live vs coming

### Live (per `pages.md`)

- `/gallery/`, `album.php`, `api.php`, `drop.php`, `m.php`
- `/api/hero-media.php`
- `/listing/view.php`, `/user/profile.php`, `/user/dashboard.php`

### Built, waiting on release (per `pages.md` and the decisions)

- `/user/post.php`
- the profile's Videos tab
- `/shop/`
- `/api/video/jobs.php` and `/api/video/results.php`
- `/api/upgrade.php`
- the 0143 stock zips (built, "waiting for Jason's upload")

### Routes named in decisions or code but missing from `pages.md`

These should be added to the register or confirmed:
- `/api/upload.php`, `/api/stock.php`, `/api/art-file.php`
- `/user/api/posts.php`, `/user/api/post-media.php`
- `/gallery/join.php`, `/api/upload-act.php`

### Coming (from the decisions and code)

- **Uploads and video:**
  - single-request uploads for small files (0153);
  -