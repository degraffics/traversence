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

**Phase 3 remains:**
- portfolio mode (client picks, download limits, watermarks);
- brand & documents (the DAM);
- galleries for listings and businesses;
- the admin view;
- editing a caption or alt text from the viewer.
