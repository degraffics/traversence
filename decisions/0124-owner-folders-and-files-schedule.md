# 0124: Every owner's files in one folder; moving them on a schedule

Status: Built (2026-10-24). Jason: "Shouldn't we have a folder dedicated to user id for uploads that way it can be
easier to manage and export/delete if needed. Plus we will at some point have a DAM feature for file management
integrated." And, with no terminal on the host: "Use the cPanel API to open Cron."

## Owner folders (`app/lib/Store.php`)

Everything an owner keeps sits under one prefix in S3, and on the server while S3 is off:

| Prefix | What |
|---|---|
| `members/<folder>/public/` | profile and cover photos (and the kept original for re-framing), journey photos, top photos on their profile |
| `members/<folder>/private/` | GO Code logos, claim documents (still deleted once the claim is decided) |
| `businesses/<folder>/public/photos/` | a business's top photos (wherever they came from: its team, or a member adding a photo of the place) |
| `businesses/<folder>/private/quotes/q<id>/`, `quote-files/` | proofs and files on its quotes (straight from the browser, or through the server) |
| `orders/<folder>/private/proofs/`, `files/` | a client order's proofs, artwork and labels |
| `guests/<folder>/private/` | GO Code logos bought without an account |
| `internal/cards/` | scanned business cards (staff working files) |

- **`<folder>` is random** (16 hex), one per owner, in `storage_folders` (kind, ref, folder; made on first use by
  AdminTables, in the admin database). The account or listing number never shows in a photo's address, in keeping
  with "anything about a user is private" (decisions/0066).
- **Orders keep their own folder.** A client order's files stay with the order, our production and sales record
  (decisions/0095, 0099), even when the customer deletes their account.
- **Photo addresses** are `/uploads/<kind>/<folder>/<area>/<name>.jpg`. The website/uploads rule sends one that isn't
  on the server to `media.php`, which maps it to `<kind>/<folder>/public/…` (`Store::publicKey`). Public and private
  are separate under each owner, so a CloudFront or bucket rule can open `*/public/*` alone later.
- **Per owner:** `Store::files()` lists everything (key and size), `usage()` gives files and bytes (for the DAM and
  storage meters), and `wipe()` deletes it all. Deleting an account for good now wipes the member's folder
  (`AccountDeletion::run`). Export, a zip or a list of links per owner, is built on `files()` and comes with the DAM.

## Files from before

Records and files from before owner folders keep working where they are (`Store::either`) until they're moved.
`Store::moveOld` moves them a batch at a time:
1. Photos named in records (`hero_media`, `journey_media`, `member_profiles` avatar, cover and kept original) move
   into their owner's folder and S3, and the record gets the new address. A record naming a photo that no longer
   exists anywhere gets the new address too, and is counted as missing.
2. Files named by number move to where their library now looks: order proofs and files, GO Code logos (marked
   `has_logo`), scanned cards, business quote files, and claim documents (the claim's `doc_path` is updated once the
   file is there).
3. A file no record names goes to S3 as it was.

Three ways to run it, all safe to repeat:
- **Settings → AWS S3 → Move N files to S3** (by hand, a batch per request, with a running count);
- **System Configuration → Scheduled tasks → Files to S3** (`app/scripts/files_tick.php`, every 10 minutes, set up in
  cPanel's cron through the cPanel API like the other jobs): up to 300 files or 4 minutes a run. After that it says
  "Nothing left on the server" and can be removed;
- `php app/scripts/move_files_to_s3.php [--go]` from a terminal.

## Tested (local S3 stand-in)

- From the state before: 70 files (63 MB) and 23 photo records.
  - The move put photos in member and business folders and order files in order folders, with cards under `internal/`.
  - Every record was updated to its new address.
  - A second run, and the scheduled task, found nothing left.
- The Settings button did the same at 390 px wide.
- Pages showed the moved photos from S3: a listing's top photos and a profile.
- Order proofs opened from `orders/…`.
- New uploads went straight to their owner's folder: a top photo, a profile photo, a business's quote file and video, a journey photo.
- `usage()` and `files()` listed an owner's files. `wipe()` deleted them all, public and private.
- With S3 off, new photos are written to owner folders on the server and served from there.
