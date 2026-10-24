# 0126: The gallery, one module for every photo and video

Status: Phases 1 and 2 built (2026-10-24). Jason: "The gallery is how uploaded photos and videos are viewed, like an album. The
user profile has gallery, but the gallery is the module that plugs in and holds all, for user, admin, listing…" His
brief sets the standard below: immersive viewing, frictionless navigation, clarity. The interface frames the photos
and stays out of the way. It must suit a photographer's portfolio (client proofing, download limits), a personal or
social album (quick sharing, timeline), and a business (brand assets and documents). Creating needs an account;
guests interact as an album's settings allow.

## The standard (what every gallery view does)

**Viewing** (`js/photo-viewer.js`, used site-wide):
- Full-screen view with several ways out: ×, Esc, a tap on the dark background, or pulling the photo down on a phone.
- Previous and next with no autoplay; the arrows grey out at the first and last photo instead of looping.
- "3 of 24" on every screen.
- Swipe on phones, with the neighbours waiting just off screen.
- Pinch to zoom on phones; wheel, buttons and keys on wide screens.
- A row of thumbnails under the photo on wide screens; edge to edge on phones.
- Videos play in the same viewer, with their poster.
- Alt text is read for every photo.

**Layout** (`app/ui/gallery-ui.php`):
- Brick rows on wide screens (one row height, photos at their own shapes, generous gaps).
- Two columns on phones, edge to edge at 3px (decisions/0058 §11).
- Lazy loading: a tiny blurred copy (24 px, sent inside the page) shows at once and the photo fades in as it nears the
  screen.
- Text over photos (album titles, dates) sits on a dark gradient so it reads on any photo.

**Grouping:** a gallery is grouped by year and then month, by when each photo was taken (from the photo itself),
else when it was added. Each month shows six, then "View all".

## Privacy and sharing

Each album is one of three:
- **Public:** anyone can see it, and search engines may list it once the site is open to them. Today every page is
  noindex while the site is pre-launch.
- **Unlisted:** anyone with its link: a 32-character random token, never a number. Pages send noindex, nofollow, both
  as a header and in the page.
- **Private:** the owner only.

Behind that:
- **Instant revocation:** making an album private, or **New link**, gives it a new token at once. Every old link and
  QR code stops working, and the files behind them too.
- **Files are never public addresses.** Gallery uploads sit in the owner's private folder (decisions/0124) and are
  served by `gallery/m.php` after a check on every request:
  - for a public album, a signed S3 link that stays the same all day, so browsers can keep it;
  - otherwise a 15-minute link that isn't kept.
- **Photos from elsewhere** (journey photos, top photos, profile and cover photos) join the gallery where they are
  (`Gallery::sync`). Their own page still decides who sees them, and deleting one there removes it here.

## Built in phase 1

- `app/lib/Gallery.php`:
  - items (`gallery_items`) and albums (`gallery_albums`, `gallery_album_items`), made on first use;
  - photo uploads: made a sensible size on the phone (2400 px, turned the right way up), then re-encoded on the server
    (hidden data dropped) as a 2000 px copy, a card and the blur;
  - video uploads: straight to S3 in parts, up to 1 GB, with a poster frame taken by the browser.
- `gallery/` (Your gallery):
  - the timeline and the albums;
  - Upload; Select (to add to an album, or delete);
  - New album (name, date, about, who sees it).
- `gallery/album.php`:
  - the cover with title, date and count;
  - Share (copy or the phone's share sheet); Edit & who sees it; Add from gallery; Upload here;
  - Select, then Make cover or Take out of album;
  - New link; Delete album (its photos stay in the gallery).
- The profile's Gallery tab: public albums, then public photos and videos, opening in the viewer.
- Gallery is in the Dashboard menu.
- Deleting an account deletes its gallery records, and its folder goes with it (decisions/0124).

## Next phases

- **Phase 2, social:**
  - likes, double-tap to like, and comments pinned to each photo (a drawer on wide screens, a sheet on phones);
  - invites for private albums (accounts or email), each person View only or Can contribute; contributors can't delete
    others' photos or change the album;
  - removing or blocking someone wipes their photos, likes and comments from the album;
  - QR codes for album links (via trvgo.us);
  - guest uploads for events by a temporary key or QR code, without an account.
- **Phase 3, modes:**
  - **Portfolio:** client proofing (favourites and picks), download limits, watermarks.
  - **Brand & documents (the DAM):** a business's brand assets and documents, with folders, file types and versions.
  - Galleries for listings and businesses (their team manages them) and the admin view across owners.
- Not yet: editing a photo's caption or alt text from the viewer (its menu still says "coming"). Storage metering for
  members joins the plans' allowances (decisions/0108).

## Tested

At 390 px and 1280 px, against a local S3 stand-in:
- **Uploads:** 4 photos and a WebM video. The video got a poster and its length, and plays full screen.
- **Viewer:**
  - "1 of 6", with the first arrow greyed;
  - Esc, and a tap on the background, close it;
  - the thumbnail row jumps to a photo;
  - the last photo greys the next arrow.
- **Albums:** made from three selected photos, with a cover, date and count.
- **Unlisted:**
  - a stranger with the link sees it (noindex);
  - by number it's a 404, and its photos without the link are refused (403);
  - after New link, the old link is a 404 and its photos are refused.
- **Public:** on the profile's Gallery tab; photos cacheable for the day.
- **Private again:** everything refused.
- **Layout:** edges at 3px on phones, no sideways scroll, no script errors.
- The older profile viewer still works.

## Phase 2 built (2026-10-24): social, people, guests

**Likes and comments are pinned to each photo.** They use the site's tables (`content_likes` / `content_comments`, kind
`photo`, ref = the gallery item).
- They go only through `gallery/api.php`, which checks that the person may see that photo. `api/engage.php` refuses
  kind `photo`.
- The viewer's side panel is now real:
  - the like count and your like;
  - the comments, oldest first, each with when;
  - **Remove** on your own comments, and on any comment on your own photo;
  - **Report** (Admin → Reports, like other comments);
  - **double-tap to like** on phones, with a heart.
- Comments need an account that's 18 or older. The panel asks once, the way content pages do.

**People** (`gallery_album_people`): the owner invites by email as **View only** or **Can contribute**.
- The email carries a one-time link, `gallery/join.php`. The invitee signs in or makes an account, and the link is
  then used up.
- Invited people see the album whatever its setting, under **Shared with you** in their gallery.
- Contributors add their own photos (they stay theirs, in their own folder) and can take out only those. They can't
  remove anyone else's photos or change the album; the API enforces this, not just the page.
- The owner can change a role, take back an invitation, or **Remove** someone. Removing them takes their photos out
  of the album and deletes their likes and comments on its photos, though they keep their own photos.
- Anyone can **Leave**.

**Guest uploads** (`gallery_guest_keys`, `gallery_guest_items`): for an event, **Let guests add photos** gives a
guest link (`gallery/drop.php?g=…`) and a QR code.
- Guests need no account: an optional name, then photos only, made a sensible size on the phone.
- Limits: 60 a day per guest per album (a hash of their address and browser; nothing about them is stored), and
  2,000 per album.
- The photos are the owner's to keep or delete, shown as "Added by {name} (guest)".
- **New guest link** or **Turn off** stops the old link and QR code at once.
- The page uses its own form key, made from the guest link, so it works without a session or cookies.

**Share** is now a sheet with the link, the phone's share sheet, and a **QR code** (`tvQr`, on a trvgo.us short link,
decisions/0094). A new album link leaves the old short link pointing at a dead link, so revocation holds.

The viewer shows who can see each photo: Public, Anyone with the link, Invited people, or Only you.

**Tested** at 390 px, with three accounts and a guest:
- **Invitations:** two people invited (emails sent); the contributor joined from the link and landed in the album.
- **The contributor:**
  - uploaded into the album;
  - liked a photo;
  - commented after confirming they're 18 or older;
  - saw "Invited people" in the viewer.
- **The viewer-only person:** saw the album with only Leave; adding photos was refused by the server; double-tap
  liked.
- **The guest:** added 2 photos as "Pat", without an account.
- **The owner:**
  - saw the guest credit;
  - removed the contributor's comment;
  - removed the contributor: their photo left the album and their likes there were deleted, while the photo stayed
    theirs; the album is now a 404 for them;
  - made a new guest link, after which the old one was a 404.
- **Share:** the QR code was made on a trvgo.us link.
- No sideways scroll and no script errors.

**Phase 3 remained** (built below).

## Phase 3 built (2026-10-24): editing, businesses, portfolio, brand & documents, admin

**Editing from the viewer.** On your own photo (`mine`), the viewer's ⋯ menu has Edit caption, Edit alt text, When it
was taken, and Delete (gallery uploads only). The edits use the site's own prompts (`TvAsk`), shown above the viewer,
and the page updates in place.

**Business galleries.** A gallery's owner can be a business (`owner_kind` `businesses`, ref = the listing).
- `gallery/?b=<listing>` and every action with `b` check `Gallery::canManage`: the business's team with Team
  `catalog` edit, or `HeroMedia::canManage('listing')`. Anyone else gets a 403.
- The photos at the top of the listing (hero media) join it, like a member's journey photos (`syncBusiness`).
- The business portal's rail has **Gallery**. The listing page shows the business's public albums under **Albums**,
  plus **Manage the gallery** for its team.
- A business's gallery counts toward its plan's storage (`BizFiles::used` adds `Gallery::bytes`; `Gallery::roomFor`
  refuses uploads past the allowance).

**Album modes** (`gallery_albums.mode`): Personal (`social`), Portfolio, Brand & documents. They're chosen in the
album sheet. The rules live in `app/lib/GalleryModes.php`.

**Portfolio** (`gallery_album_opts`, `gallery_picks`, `gallery_pick_sends`, `gallery_downloads`):
- **Rules** (owner): picks on or off, a pick limit, downloads `none` / `picks` / `all`, a per-person download
  allowance, and a watermark (on by default; text defaults to "© owner name").
- **Picks:** clients, signed in (invited, or holding the unlisted link), tap **Choose my picks**, then **Send my
  picks** with a note. The owners get an email; **Client picks** lists each client's picks, whether they were sent,
  and the note, and can highlight them on the album.
- **Watermark:** `gallery/m.php` serves a watermarked copy whenever someone other than the owner can see the photo
  *only* through watermarked portfolio albums. If any of their ways in has no watermark (a public personal album,
  say), there's nothing to step around, so none is added.
  - The stamp is GD's own font, enlarged, tilted and tiled at 38%. It's made once per photo, size and text
    (`<file>-wm[c]-<sha8>.jpg`) and kept beside the photo.
  - Watermarked copies are never sent with public caching. Videos aren't watermarked (their posters are).
- **Originals:** photos uploaded into a Portfolio or Brand album also send their full-size original, in parts,
  straight to S3 (`gallery_files`, `<file>-orig.<ext>`).
- **Downloads:** `m.php?s=orig` gives the original (or the photo as shown, if no original was kept) as an attachment.
  `GalleryModes::canDownload` checks the rules; the reason it gives when it says no ("Pick it first…", "You've used
  all N downloads…", "Sign in to download.") shows in the viewer's menu. Each download is counted per album and
  person; downloading the same photo again doesn't use up the allowance.

**Brand & documents** (the start of the DAM; `gallery_files`, `gallery_versions`):
- **Upload files:** any of PDF, SVG, AI, EPS, PSD, INDD, ZIP, Office, fonts, TIFF, images, audio or video, up to
  1 GB each, in parts straight to S3. A file is an item of kind `file`. It lives in its album and isn't in the photo
  timeline.
- **The list:** each file is a card with its type, name (the download), size and version. Its actions sit down the
  right edge (Download, Versions, New version, Describe, Delete).
- **New version** uploads beside the current file. Once the upload is whole and the size checks out, the current
  file becomes `-vN` and is recorded in `gallery_versions`. Every version stays downloadable (`m.php?s=orig&v=N`),
  and old versions count toward storage.
- Deleting a file deletes every version. Deleting a Brand album deletes the files that are in no other album.
- Files need S3: without it, the page says so.
- Contributors can add files too (they stay theirs).

**Admin** (Social → **Galleries**, decisions/0109: a section built from widgets, with no new page):
- **Metrics:** public, unlisted and private album counts, and gallery storage.
- **Tables:** `social/gallery_albums` (filters: who sees it, what it's for, whose) and `social/gallery_items`
  (filters: kind, guest uploads).
- **Privacy:** only what's already shared (public and unlisted) is listed or opened. Private albums are only counted,
  and their drawer says nothing about them.
- **Moderation** never deletes the owner's files:
  - **Take it off the site** on an album makes it private, which gives it a new token, so its links and QR codes
    stop;
  - on a photo, it takes it out of every shared album.
  - Both ask why, log it (`role_log`, context `gallery`), and email the owner, or a business's owners and managers.
- Access is `view` for ops/svc/con staff and `edit` (the actions) for ops/svc, through `Access::can`.

**Tested** at 390 px, with the owner, a client, a signed-out visitor, someone outside the business, and staff, on
local S3 (moto) and with no S3:
- **Portfolio:**
  - the sheet's options scroll on a phone;
  - 3 photos uploaded with their originals;
  - the client's card was watermarked while the owner's wasn't;
  - a third pick was refused at the limit of 2;
  - the picks were sent with a note and the owner was emailed;
  - downloads: the first picked photo gave 200, the second gave "used all 1", an unpicked one gave "Pick it first",
    and downloading the first again was allowed;
  - signed out: "Sign in to pick", and downloads refused;
  - **Client picks** showed the client, the note and the time, and highlighted their 2 picks.
- **Editing:** the caption and date were edited from the viewer's menu.
- **Brand:**
  - an SVG and a PDF were uploaded;
  - **New version** gave version 2, with version 1 still downloadable byte for byte;
  - a visitor downloaded from the public album but had no edit actions;
  - files stayed out of the timeline;
  - deleting a file worked;
  - the card edges were 3 px.
- **Business:**
  - the owner got 200; someone outside the team got 403 on the page and an error from the API;
  - a business album was made and a photo uploaded to it;
  - the listing page showed the album.
- **Admin** (390 and 1280):
  - the metrics and both tables showed;
  - the album drawer took the album off the site: it became private and the owner was emailed;
  - the photo drawer took the photo out of its album and the owner was emailed.
- No sideways scroll and no script errors.

**Limits / next:**
- Watermarks are on photos and posters only, not inside videos.
- Searching and tagging files across albums, and sharing a single file, are for the DAM's next step.
- Originals and files need S3.
- A client's picks are per album; they aren't carried across albums.


## Fix (2026-10-24, afternoon): photos missing on the live site; the dashboard's Gallery tab

- **Photos were blank** on traversence.com (profile, cover and journey photos). `website/uploads/.htaccess` sent missing
  photos to `media.php` (S3) only for the old folders (`hero`, `journeys`, `profile`). The owner folders
  (decisions/0124: `members/`, `businesses/`, `guests/`, `orders/`) were left out. That's where new photos go and where
  **Move files** puts the old ones, and once a photo is kept in S3 its server copy is gone, so those addresses gave
  a 404. The rule now covers every folder. The local test router already did, which is why testing didn't catch it.
- **The dashboard's Gallery tab** was still the old list ("Folders and albums are coming"). It's now the gallery
  module: the newest 12 items and up to 4 albums, opening in the same viewer (with editing), and **Open your
  gallery** for the rest.
- **Upload failed with "Unexpected end of JSON input"** in a business's gallery on the live site: PHP stopped part way
  and sent nothing back. It didn't happen here, even acting as the business. The fix covers both likely causes:
  - **Memory:** a photo is now given room to be worked on (`Gallery::room`, raising `memory_limit` up to 1 GB as
    needed, like `PlaceProfile`). The EXIF turn no longer keeps two copies in memory. A 12-megapixel photo sent at
    full size went through under a 128M limit.
  - **Silence:** `gallery/api.php` and the guest upload (`drop.php`) now answer in JSON even when PHP stops (out of
    memory, out of time, a fatal error). The page then says why, and the cause goes to the error log
    (`website/gallery/error_log` on Bluehost).
- **Photos still blank, "403 Forbidden: Server unable to read htaccess file".** The real cause: `Store::relocate`
  (**Move files**) and the gallery made folders 0750. Under `website/uploads/` that shuts out Apache's own user, so
  every photo in an owner folder was refused before any rewrite rule ran. Public photo folders are now made 0755
  (`Store::mode`; private storage stays 0750). `Store::openPublic()` opens the folders already made shut (folders
  0755, files 0644) once, from `app/bootstrap.php`, then writes the marker `app/storage/.uploads-open-v1`, so
  later requests cost a single `is_file()`.
- **Business upload: "The server stopped part way (BizFiles.php, line 336)".** The live server runs **PHP 8.0**, and
  `BizStorageFull`'s constructor used `readonly` properties (8.1+), a syntax error there. It was first loaded on a live
  business request by the gallery's storage check. Now plain `public`. The same scan found `array_is_list()` (8.1+)
  in `Concepts.php` and `api/ingest.php`; both now check keys themselves.

## Fix (2026-10-24): the business's Plan & settings page

- **No way to buy a plan** from a business's Plan & settings. A **Your plan** card now opens the page: the plan, the
  storage used, and **Choose / Change plan** and **Add storage** (`/plans.php?listing=<id>`, `#packs`). The Overview's
  plan and storage tiles already linked there.
- **The page stopped after the team list on the live site**, with no hand-over section and no footer. Most likely
  `Team::pendingTransfer()`'s query failing against an older `transfers` table. It's now caught and logged, and so is
  the White Glove block. The portal router now shows "This page stopped part way (file, line)" if it ever stops again,
  and logs why.
