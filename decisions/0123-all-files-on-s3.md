# 0123: Every file the site keeps lives in S3

Status: Built (2026-10-24). Jason: "All files should. Journeys, listings, marketplace, isn't that why we set up S3, so
the gallery and order processing wouldn't kill Bluehost?" Yes (decisions/0099 "Storage: S3, not Bluehost"; master-aos
§5D). 0122 put business files there. This puts every other file there too, so Bluehost keeps none: not its disk, and
not its 200,000-file ceiling (commercial.md).

## How (one place: `app/lib/Store.php`)

Each library still makes a file where it always has (resizing a photo, taking an upload), then calls `Store::keep()`.
That moves the file to S3 under a key made from its path and removes the server copy. Reading, sending and removing
go through `Store` too: the server copy first (files from before, or S3 not set up), then S3. So no record or address
changes, and with S3 off everything works from the server as before.

| On the server | In S3 | What |
|---|---|---|
| `website/uploads/hero/` | `uploads/hero/` | photos at the top of listings and profiles (HeroMedia) |
| `website/uploads/journeys/` | `uploads/journeys/` | journey photos and their cards (Journeys) |
| `website/uploads/profile/` | `uploads/profile/` | profile and cover photos, and the kept original for re-framing (MemberProfile) |
| `app/storage/order-proofs/`, `order-files/` | `private/order-proofs/`, `private/order-files/` | client orders' proofs, artwork, labels (ClientOrders) |
| `app/storage/biz-files/` | `private/biz-files/` | business files that came in through the server (BizFiles' fallback; the page sends them to `biz/`, 0122) |
| `app/storage/qr-logos/` | `private/qr-logos/` | GO Code logos (QrIssue; the code's style records `has_logo` so lists don't look) |
| `app/storage/cards/` | `private/cards/` | scanned business cards (CardScan) |
| the claims folder (outside the site) | `private/claims/` | documents proving a claim, still deleted when the claim is decided (Claims) |

- **Public photos keep their `/uploads/…` addresses.** `website/uploads/.htaccess` sends a request for a file that
  isn't on the server to `website/media.php`. That page loads no session and no database, and points the browser at
  the file in S3. The signed link stays the same all day, so browsers keep both the photo and the move until midnight
  UTC. With `S3_PUBLIC_BASE` set (a CloudFront address, or the bucket's own address once `uploads/` is public) the move
  is permanent and cached for a year. That's the cheaper way once traffic grows (decisions/0099: a CDN in front, to be
  costed). Only `hero|journeys|profile` photo names are accepted; anything else is a 404.
- **Private files** open through a 15-minute signed link with their own name (`Store::redirect`), only after the same
  checks as before (the customer's order link, staff, the claim reviewer, the business's team).
- **Work that needs the file itself** reads it from S3 for that request: re-framing a profile photo, using an upload as
  a profile photo, a GO Code's logo in its download, reading a card with AI (`Store::local`, `Store::read`).
- **Removing** (a photo, a proof, a line, a code, an account) deletes from S3 too (`Store::remove`).
- **Not files the site keeps:** caches and logs in `app/storage/` (sign-in keys, QuickBooks discovery, logs,
  billing_tick.json) stay on the server. A census import is read and thrown away.
- **Marketplace** isn't built yet. When it is, its photos and files go through `Store` the same way.

## Moving the files already there

`php app/scripts/move_files_to_s3.php` says what it would move. Run it again with `--go` to move them. Running it twice
is safe, and a file that won't go stays and still works. Addresses don't change, so it can run any time. Run it once
the CORS rule is set.

## Also

- `S3::put` streams a file from disk (UNSIGNED-PAYLOAD), `S3::get` reads one, and `S3::presign` takes a fixed signing
  time (for media.php's all-day link). `Database::readEnv()` reads .env without connecting.
- Settings → AWS S3 now says it holds every file.
- Privacy: Amazon Web Services (S3) is named as file storage, and as a service that sees a visitor's address when a
  photo loads. FAQ: "Where are my photos and files kept?"

## Tested (local S3 stand-in, moto)

- The move script found and moved 70 files (63 MB), leaving no photos on the server; a second run found none.
- Pages showed the moved photos from S3: a listing's top photos, and a profile.
- New photos (top photo, profile photo, journey photo) went to S3 and none stayed on the server.
- Re-framing a profile photo from its original in S3 worked. Removing a photo deleted it from S3.
- An order proof opened by the customer's link through a signed S3 link. A scanned card was read back from S3.
- With S3 off, files stayed on the server and pages worked as before.
- A made-up address under `/uploads/` got a 404.
