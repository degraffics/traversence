# 0126: The gallery, one module for every photo and video

Status: Phase 1 built (2026-10-24). Jason: "The gallery is how uploaded photos and videos are viewed, like an album. The
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
