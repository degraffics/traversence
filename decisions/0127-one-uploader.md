# 0127: One uploader for every file

Status: Built (2026-10-26). Jason: "I see file uploads the same no matter where they are being uploaded from. The
gallery was built to handle any kind of file and size up to 1 gb, this is the system that must be used for file
uploading, having two different versions is a recipe for disaster. Assigning orders or photos to the correct album or
order should have already been established." Then: "do all 3 please" (the three waves below).

## Why

Before this, the site had about a dozen ways to take a file: the gallery's own S3 multipart flow (videos and brand
files, up to 1 GB), a business's quote files (their own chunked flow and their own S3 flow), order proofs and artwork
(an ordinary form post, capped by PHP's `upload_max_filesize`, so 36 MB failed with no question asked), and plain
`$_FILES` posts for gallery photos, the guest drop, hero photos, journey photos, profile photos, claim documents,
business card scans, GO Code logos and the census file. Each had its own size rules, its own errors (some at the top
of the page, some as a browser error page), and its own bugs. Fixing one didn't fix the others.

## The decision

One path for every file anyone sends the site:

- **The browser:** `js/tv-upload.js` (`TvUpload.send` / `bind`), loaded on every page by `app/ui/header.php` and
  `app/ui/page-shell.php`.
  - the size is checked before anything is sent: too big is said at once, with the limit; over 10 MB asks first;
  - photos are made a sensible size on the phone (unless the album keeps originals);
  - the file goes straight to S3 in parts (presigned), or in pieces through the server when S3 isn't set up; each
    part is retried three times;
  - a progress bar, and the result beside what you were working on (`TvUpload.reloadHere` keeps your place after a
    reload and opens the `<details>` it was in), never a jump to the top.
- **The server:** `/api/upload.php` (`start`, `piece`, `done`, `abort`) and `app/lib/Uploads.php`.
  - `start` asks the target whether this person may send this file here (`check`) and stages it in the owner's folder
    (`<owner>/incoming/`);
  - `done` checks the size that arrived, sniffs the content (the first bytes must match the name; loose kinds like
    Office files, fonts and design files are allowed by name), asks the target again, then hands the file over;
  - the state travels in a signed token (HMAC, one day, the uploader's id), not the session, so a phone that loses
    its session mid-way still finishes; leftovers are removed whatever happens.
- **The destinations ("targets")** live in `app/lib/upload-targets.php`. Each has `check` (who may, what kinds, the
  most it takes, whose folder) and `done` (what happens to the file: `Upload::moveTo()`, `local()`, `asFile()`). The
  library that owns the record does the work, as with any protected move (decisions/0109).

| Target | Where | Most |
|---|---|---|
| `order_proof`, `order_file` | an order's proofs (one version per batch), artwork, reference, shipping labels | 1 GB |
| `gallery_photo`, `gallery_video`, `gallery_file` | albums (originals kept in portfolio and brand albums, new versions of a file) | 100 MB photo, 1 GB video and file |
| `gallery_guest` | a guest adding to an album by its link or QR code (no account; the album's key) | 25 MB |
| `biz_file` | a business's quote proofs and files (counts toward its storage; full says where to add more) | 1 GB |
| `hero_photo`, `journey_photo`, `profile_photo` | the photos at the top of a listing or profile, journeys, avatars and covers | 15 MB |
| `claim_doc` | a document proving a claim | 25 MB |
| `card_scan` | a business card's front, then its back (read once both are in) | 10 MB |
| `go_logo` | a GO Code's logo (members and guests) | 2 MB |
| `census_file` | the Census population file, when the server can't reach the Census | 50 MB |

A form with other fields as well sends the file first and then the form with the id it got back: a shipment's label,
a GO Code's logo, a journey's photos once the journey is saved (its errors are shown, and the photos stay picked).

## What went

- The gallery's `videoStart/videoDone`, `fileStart/fileDone/fileAbort` and the photo post in `gallery/api.php`.
- `BizFiles::add/chunk/s3Start/s3Done/s3Abort` and the upload half of `business/files.php` (now 410).
- `ClientOrders::addProof/addProofs/addFile/uploads` and the proof and item-files posts in `admin/client-order.php`.
- The POSTs in `gallery/drop.php`, `api/hero-media.php` (photo), `user/api/member-profile.php` (photo),
  `listing/api/claim.php` (document), `admin/api/cards.php` (a new scan), `api/go-codes.php` (the logo field) and
  `admin/cluster-tools.php` (pop_file).

`grep -rn '\$_FILES\|is_uploaded_file\|move_uploaded_file' app website` now matches only `Uploads.php` and
`api/upload.php`. The rule is in CLAUDE.md: a new place to upload is a target, never a page of its own.

## Tested

Every target, through its own page where it has one, on a site with S3 and one without (pieces through the
server): order proofs (two files as one version), artwork, a shipping label; a photo and a video into an album, brand
files, a portfolio original; a guest's photo; a business proof; a hero photo; a journey draft with its photo; an
avatar; a guest's GO Code with a logo; a claim document (a fake PDF refused, someone else's claim refused); a card's
front and back (both kept as clean JPEGs, a back for a card already read refused); the census file. A 30 MB proof
asked first and went in pieces. Nothing left in `incoming/` or in S3's unfinished uploads. Checked at 390 px.

## Addendum, 2026-10-29: uploads carry on across pages (Jason: "I upload a very large file or video and I can't stay in one spot")

- **The browser keeps the file, not the page.** `TvUpload.send()` puts each file in an IndexedDB queue (`tv-uploads` →
  `jobs`: the file, its target, ctx and extra settled when it was chosen, where it was started, how far it got). Any page
  of the site sends the queue, one page at a time (`navigator.locks`, else a renewed lease in `localStorage`), oldest
  file first. Another page takes over when the sending one closes.
- **Resume:** `/api/upload.php` `do=status` (`Uploads::status()`) says how far a file got: pieces received (a cut-off
  piece is trimmed back to the last whole one), or fresh S3 part addresses and whether the file is already whole.
  `finish()` accepts an S3 file that's already whole (a page left while saving asks again). The server's upload token is
  good for a day; a job older than 23 hours is let go with a message.
- **Connection lost:** the job waits and tries every minute, and on the browser's `online` event.
- **What shows:** the page that started it shows its bar beside the form as before; every page shows a card in the
  bottom-left (each file with its size and how much has gone, one bar, Hide/Show) with what to do before leaving: on a
  computer keep one Traversence page open until 100%; on a phone keep the browser in front (phones suspend background
  tabs; it carries on when the page is in front again). Finished on another page: a notice with "Open <page>". With
  **Tell me when it's done** (the browser's permission, asked from that button only), a finish while the page is hidden
  shows a browser notification.
- **Leaving the last page** while files are still going: the browser's own leave question (`beforeunload`), the only
  one a closing tab can ask; links within the site don't ask, since the next page carries on. Other open Traversence
  pages are counted over a `BroadcastChannel`.
- **Sizes with names:** `finish()` returns `bytes`; the summary reads "big.pdf (1.2 GB) added…" or one line per file
  with its size. An order's file lists show each file's size (from the order's storage listing, here or in S3).
- **Fallback:** when the browser can't keep a copy (a private window, storage blocked), the page sends the files itself,
  as before, and links, the command strip's Back/Close and closing the tab ask first, with how far it got.
- **Not possible:** sending while no Traversence page is open at all (a closed browser, or a phone with the browser in
  the background). That needs Background Fetch, which only Chromium has and which can't send our pieces; not taken.
