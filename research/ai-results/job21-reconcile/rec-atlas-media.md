# Platform atlas: media (merged from two passes)

*research/platform-atlas/media.md · Draft for human review*

**Conventions.**
- **P1** and **P2** name the pass a statement comes from. Each citation is that pass's own file:line.
- **Both** means both passes support the statement.
- Code paths are written `app/lib/…`, using line numbers from the sync mirror `traverse-3way-sync/sync-app/lib/`. Both passes do this.

**Both passes are cut off.**
- P1 stops partway through (d), in "Register drift", at "`/gallery/`".
- P2 stops partway through (d), in "Coming", after its first item.
- Neither pass contains a section (e). Section (e) below is therefore empty.

**Sources.**
- **Code shown (both):** `Gallery.php`, `GalleryModes.php`, `GalleryCatalogs.php`, `GalleryUses.php`, `Uploads.php`, `HeroMedia.php`, `VideoJobs.php`, `UploadThreads.php`.
- **Docs used (P1 only):** `charter.md`, `brand.md`, `prd.md`, `site-audit/pages.md`, and ADRs 0126, 0127, 0143, 0153, 0154.
- **Scope (P2 only, single-pass):** the Gallery (albums, viewer, Portfolio, Brand & documents, Channel), the uploader, hero media, Stock library, the media side of Moments/Snaps/Keep, the video worker, and staff upload threads.

**Partial view.**
- `Gallery.php` is cut off at line 496 (both).
  - **Passes differ:** P1 says it ends after the album cover function. P2 says it ends partway through the album functions, and lists the unseen functions: `saveAlbum`, `addToAlbum`, `role`, `canContribute`, `room`, `jpeg`, `blur`, `when`.
- **Not shown, according to both:** `upload-targets.php`, `MemberPosts.php`, `StockLibrary.php`, `Store.php`, `S3.php`, `RecordFiles.php`, `EarnedStorage.php`, `Billing.php`, all JS files, and the gallery pages.
  - P1 adds the `moments_tick` job.
  - P2 adds every page under `website/`.

---

## (a) What each person can do, page by page

Statuses come from `site-audit/pages.md` (both).

### Resident / traveler

| Route | What they can do | Status | Support |
|---|---|---|---|
| `/listing/view.php?id=` | Hero photos and videos. Public **Albums** (0126 Phase 3). **Videos** section (0153). Labelled stock fallback when the listing has no photo (0143). | Live | Both. P2 adds "Representative" wording and a Posts section (single-pass). |
| `/hub/?hub=` | Place-guide hero with stock fallback (0143). | Live, placeholder | Both. P2 adds a Commons place-photo fallback (single-pass). |
| `/place/landmark.php`, `/place/recreation.php` | Wikimedia Commons photos with credit and licence (0143 notes 11-06 and 11-19). | Live | single-pass (P1) |
| `/user/profile.php?id=` | Gallery tab with public albums and photos (0126). | Live | Both. P2 adds a Posts tab (0153), single-pass. |
| `/user/profile.php?id=&tab=videos` | Channel and playlists. | Built | Both. P1 names the channel-shop-earned zip and adds a short link and QR code (single-pass). |
| `/user/post.php?id=` | A single post or Moment. | Built (moments-keep zip) | Both. **Passes differ** on audience: P1 lists it under Member, P2 under visitor. |
| `/gallery/album.php` | Public album, or unlisted album via its link. | Live | Both. P2 adds QR access and noindex for unlisted albums (0126), single-pass. |
| `/gallery/drop.php?g=` | Guest upload to an event album, no account needed (0126 Phase 2). | Live | Both. P1 adds "name optional". |
| `/gallery/m.php` | Serves files after a permission check. | Live | Both. P2 says the check runs on every request. |

### Member

| Route | What they can do | Status | Support |
|---|---|---|---|
| `/gallery/` | Timeline, Select, New album, Albums, Documents and Stock photos tabs, catalogs. | Live | Both. **Passes differ** on sources: P1 cites 0143 zips 1–3 and `GalleryCatalogs.php:14-21`; P2 cites 0143 zips 2–6. P1 adds upload and year/month grouping. P2 adds `?cat=` and an **Orders** catalog. |
| `/gallery/album.php` | Share sheet with QR, invite as View only or Can contribute, New link. | Live | Both. P1 adds: guest link, New link revokes the old one, Portfolio picks, downloads, watermark (0126). P2 adds: "Edit & who sees it", and modes Personal, Portfolio, Brand & documents and Channel. |
| `/gallery/join.php` | Accept an album invite (0126). | **Not in `pages.md`** | Both |
| `/user/dashboard.php` | "What's on your mind?" composer (0153 zip 1) and Gallery tab (0126 fix). | Live | Both. P2 adds: newest 12 items and up to 4 albums (single-pass). |
| Snap button on profile | Send a Moment directly (0153). | Route not listed | Both. P1 cites zip 2. |
| Moments, Keep, "Seen by" | 72-hour or 7-day posts, Keep into the Gallery, viewer list (0153). | n/a | single-pass (P2) |
| `/shop/`, `/plans.php` | Storage packs, Plus and Pro, Keep in 4K, single month of Pro (0154). | `/shop/` Built; `/plans.php` Live | Both. P1 adds the Pro free week. P2 adds prices ($0.99 for Keep in 4K, $9.95 for a month of Pro) and the triggers (Gallery full, or a recording reaching 2:00), single-pass. |
| Any file input | Gallery panel with Your gallery, Upload new, and Stock in illustrative slots only (0143 zips 2–3). | n/a | single-pass (P2). P1 mentions the rule only in (c). |

### Business

| Route | What they can do | Support |
|---|---|---|
| `/gallery/?b=<listing>` | The business gallery. Requires team catalog edit rights, with listing manage rights as a fallback (`Gallery.php:77-88`). | Both. P1 adds that Brand & documents albums keep versions (`GalleryModes.php:257-307`). |
| `/api/hero-media.php` | Up to 8 hero photos or YouTube/Vimeo links (`HeroMedia.php:10`, P1 also `:52-60`). | Both |
| `/business/` Overview | Post as the business and see Pulse (0153 biz-overview). | Both. P2 adds Messages and Gallery in the rail. |
| Storefront cover | Chosen from stock (0143). | Both. **Passes differ:** P1 says "Let us pick"; P2 says chosen from Gallery or Stock. |
| Orders catalog | Artwork from orders appears in a catalog (0143). | **Passes differ** on whose catalog. P1: the business's own orders, in its Gallery (zip 6). P2: an Orders catalog for clients/members (zips 2–6). |
| Brand & documents and Portfolio proofing | Albums with file versions and client proofing (0126 Phase 3). | single-pass (P2) |

### Staff

| Where | What | Support |
|---|---|---|
| Admin → Social → Galleries | Metrics and "take it off the site" (0126). | Both. P2 adds tables. |
| System Configuration → Stock library (`config/stock`) | Manage the stock library (0143). | Both. P1 details: search sources, tag, retire. |
| Pulse upload threads | Threads raised by uploads (`UploadThreads.php`). | Both. P1 gives the titles and `:97-147`. |
| Order pages | "This order's files" instead of a personal Gallery (0143 zip 5). | single-pass (P2) |

---

## (b) How it works underneath

### 1. The one uploader (0127, `Uploads.php`)

**Limits (single-pass, P2).** A file can be at most 1 GB (`:72`). The page asks before sending anything over 10 MB (`:73`).

**Start (both).** `start` runs the target's check and stages the file in the owner's `incoming/` folder. It then sends the file either as S3 multipart parts or as pieces through the server.
- P1 cites `:140-161`.
- P2 cites `plan :120-128`, `:146`, `:148-153` and `:155-160`.

**Signed state token (both).** The upload state travels in a signed token, not the session, and is valid for one day.
- P1 cites `:163-180`.
- P2 cites HMAC at `:165-170`, plus a same-user and age check at `:178`.

**Finish (both).** Four steps:
1. Check the file arrived at full size.
2. Sniff its content, except for `LOOSE` types (`:75`, `:251-255`).
3. Re-run the target's check.
4. Hand off to the target.

The result is cached, so a repeated request gets the same answer.
- P1 cites `:229-262`.
- P2 cites PageCache `upl:done:` at `:234-235`, plus `:238-246`, `:250` and `:256`.

**Gallery reuse (both).** A Gallery file is reused where it sits if the target allows (`pick`); otherwise it is copied S3-to-S3 (`:270-320`, `:365-374`). The use is recorded through `GalleryUses` (P2 names `fromGallery :270-287`, `gallery() :296-320` and `copyInto`).

**Record files (both).** `fromRecord` (`:327-344`) is guarded by `RecordFiles::mayUse`, which is not shown. P2 adds that it serves staff and the client the file was released to.

**Resume (both).** `status` (`:211-226`) lets another page continue an upload. P2 adds that it trims a cut-off piece.

**Cleanup (single-pass, P2).** `tidy` deletes stale staged files on about 1 in 20 requests (`:390-394`).

**Targets (both).** Defined in `upload-targets.php`, which is not shown. P2 adds that 0127 lists 15 targets.

### 2. Gallery as the hub

**Uses (both).** Each reuse is recorded in `GalleryUses.php:40-49` and summarised as "Used in …" at `:68-74`.
- P2 adds destination labels (`:12-38`).
- P2 adds an owner-only note in the header (`:6`); the page that enforces it is not shown.

**Catalogs (both).** Catalogs derive from each item's source (`GalleryCatalogs.php:54-69`). An auto-made private Documents album is created on first use (`:27-37`).
- P1 adds the Marketplace and Storefront albums.
- P2 says the Documents album is a Brand album.

### 3. Storage, visibility and serving (`Gallery.php`)

**Tables (both).** Created at `:41-68`. P2 lists them.

**Modes (single-pass, P2).** `social`, `portfolio`, `brand`, `channel` (`:34`).

**Manage (both).** Members manage their own gallery. For a business: team `catalog/edit`, falling back to `HeroMedia::canManage` (`:77-88`).
- **Passes differ** on `HeroMedia.php:21-28`. P1 calls it the fallback. P2 says any role other than visitor qualifies, via `ListingLocation::role`.

**See (both).** `:216-229`. Access goes to:
- the owner;
- anyone, for items public at their source;
- staff, for Marketplace photos under review (`:233-241`). P1 specifies "any user with an `admin_access` row";
- anyone, for items in a public album;
- holders of the unlisted-album token;
- people invited to the album.

**Audience labels (single-pass, P1).** `audience()` (`:284-303`).

**Sync (single-pass, P2).** Journey, hero and profile photos are joined in (`:178-189`, `:310-350`). Items deleted at the source are dropped (`:347-349`).

**Photos (both).** Re-encoded to a 2000 px copy, a 720 px card and a blur placeholder, which drops hidden data.
- **Line numbers differ:** P1 cites `:361-395`; P2 cites `addPhoto :363-395`.
- P2 adds: JPEG/PNG/WebP only, EXIF rotation, sizes at `:38-39`.

**Hero photos (both).** Re-encoded without metadata (`HeroMedia.php:62-88`).
- P1 adds sizes of 2000 and 640 px.
- P2 adds a 15 MB limit (`:11`) and that videos are YouTube/Vimeo embeds only (`:52-60`).

**Videos (both).** `videoFromUpload` (`:402-427`) checks magic bytes (P2: `:408`). The poster frame comes from the browser.

**Serving (single-pass, P1).** The `m.php` code is not shown; it is described in 0126.

### 4. Album modes (`GalleryModes.php`)

**Watermark (both).** Applied only when every way the viewer can see the photo is watermarked (`:89-96`). Rendering is at `:99-124`.
- P1 says the result is cached per photo, size and text.
- P2 says it is tiled at 38% strength.
- Each detail is single-pass.

**Picks (both).** Pick limit at `:147-164`. Send picks emails the owner(s) at `:167-185`.

**Downloads (both).** `:213-233`. P2 adds that Brand albums always allow download (`:218`), and that Portfolio uses a none/picks/all rule with a per-person allowance.

**Brand & documents (both).**
- **Passes differ** on the type list. P1 cites a whitelist "up to 1 GB" at `:20-26`. P2 cites types at `:20-25`, and places the 1 GB limit in the uploader (`Uploads.php:72`).
- **Passes differ** on versioning lines. P1 cites `:257-307` and says a new version moves the current file to an old version number at `:296-300`. P2 cites `fileFromUpload :282-307` and versions at `:318-325`.
- `forget` removes originals, versions and watermarks (`:334-352`): single-pass (P2).

**Channel (both).** One public album per owner, created on first use (`Gallery.php:147-155`). `videos()` builds the Videos tab (`:159-175`).

### 5