# ADR 0151: Files on Cloudflare R2 (no charge when people view or play them)

**Status:** Accepted (Jason, 2026-11-11: "ok"). Built: `files-on-r2_20261111.zip` (the code to use R2's own key). The move
itself is the steps below, done once in the Cloudflare and Amazon dashboards. Amends decisions/0122 (where files are
kept) and master-aos §5D (the storage packs' cost basis).

## Why

Every file the site keeps is in Amazon S3 (decisions/0122, 0123, 0124). A photo or video is shown by sending the viewer's
browser to a short-lived signed S3 link (`gallery/m.php`, `media.php`), so the file travels from Amazon to the viewer.
Amazon charges for data leaving it for the internet: about **$0.09 a GB** after the first 100 GB a month, **on every
view or play**, not once. A 300 MB video played 10,000 times in a month is about 3 TB, about $260. Jason: "whoa. I had
not considered the costs of serving video for watchers."

master-aos §5D priced the storage packs on $0.04 per GB-month, allowing for about 20% of a pack to be viewed each month.
That holds for photos, not for video watched over and over, and free accounts (2 GB, no revenue) had no cover at all,
against "No income, no expense" (CLAUDE.md).

Asked whether our own server could pull the video and serve it: it doesn't avoid the charge (Bluehost is on the internet,
so every pull from Amazon is still billed), and caching video on Bluehost breaks its terms for shared hosting, holds a PHP
process per viewer for the whole play, and brings back the storage limits files left Bluehost for. Not done.

## Decision

1. **Files move to Cloudflare R2**, an S3-compatible store that charges **nothing for downloads** (egress), however many
   times a file is viewed or played. Storage is about $0.015 a GB-month (Amazon: $0.023); reads are about $0.36 per
   million after 10 million free a month. Viewers still fetch files directly from the store with short-lived signed
   links, so private files stay private and Bluehost serves only pages.
2. **R2 has its own key.** The storage code signs with `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY` and `S3_REGION`
   (default `auto`) whenever `S3_ENDPOINT` is set; the AWS key stays for Amazon's email (SES, decisions/0137) and
   Textract. Without `S3_ENDPOINT`, nothing changes. (`app/lib/S3.php` `S3::creds()`; the Settings card checks R2.)
3. **Next, smaller video:** each uploaded video converted to a phone-sized version (about 720p). Not built yet; a build
   of its own. Pages already load only a video's first bytes until someone presses play (`preload="metadata"`, no
   autoplay), so nothing is fetched for videos nobody watches.
4. Not chosen: YouTube embeds (ads and tracking), Cloudflare Stream or Vimeo (charged per minute watched), CloudFront in
   front of S3 (still charged per GB after its free allowance).

## Setup (once; secrets only in `.env`)

**1. Cloudflare: the bucket.** Sign in at dash.cloudflare.com (a free account) → **R2 Object Storage** → **Create
bucket**, name it `traversence-files`, location automatic. In the bucket's **Settings**:
- **CORS policy** → Add, the same rule as on S3 (decisions/0122):
  ```json
  [
    {
      "AllowedOrigins": ["https://traversence.com", "https://www.traversence.com"],
      "AllowedMethods": ["PUT", "GET", "HEAD"],
      "AllowedHeaders": ["*"],
      "ExposeHeaders": ["ETag"],
      "MaxAgeSeconds": 3600
    }
  ]
  ```
- **Object lifecycle rules** → abort incomplete multipart uploads after **1 day** (the rule S3 has).
- Leave **Public access** off: every link is signed.

**2. Cloudflare: the key.** R2 → **Manage API tokens** → **Create API token**: permission **Object Read & Write**,
applied to the `traversence-files` bucket only. It shows an **Access Key ID**, a **Secret Access Key** and the
**S3 endpoint** (`https://<account id>.r2.cloudflarestorage.com`). The secret is shown once.

**3. Copy the files over.** R2 → **Data Migration** → **Migrate files** (Cloudflare's own copier, "Super Slurper"; if the dashboard has moved it, a Claude session can script the copy instead): source Amazon S3,
the Amazon bucket's name (`S3_BUCKET` in `.env` before the switch) and its region (`AWS_REGION`), with a read-only AWS key made for this (IAM → the traversence-site user is
fine if it can list and read; or a new key with `s3:ListBucket` and `s3:GetObject`); destination the new R2 bucket.
Amazon charges its $0.09 a GB once for this copy: small at today's sizes, which is why the move is now.

**4. Switch the site.** In `.env` (cPanel File Manager), add:
```
S3_ENDPOINT=https://<account id>.r2.cloudflarestorage.com
S3_ACCESS_KEY_ID=…
S3_SECRET_ACCESS_KEY=…
S3_REGION=auto
```
Set `S3_BUCKET` to the **R2** bucket's name (`traversence-files`): the site uses that one setting for whichever store it's on, and the Amazon bucket's name differs (found during the move, 2026-11-11). Note the old value to switch back. Keep the `AWS_…` lines (email uses them). **Admin → System Configuration → Settings**
then says "File storage (S3-compatible): Connected: traversence-files (Cloudflare R2) · uploads ready".

**5. Check, then clean up.** Open a few photos and a video on the site; upload one photo. Run the migration once more to
catch anything added in between. After a week with nothing missing, empty the Amazon bucket (Amazon still charges for
storing it) and narrow the AWS key to email only.

To go back: remove the four lines from `.env` and put `S3_BUCKET` back to the Amazon bucket's name; the site uses Amazon again.

## Fix, 2026-11-11 (`r2-put-key_20261111.zip`)

`S3::put()` (the server storing a file it made itself: re-encoded photos, video posters, profile and Gallery photos,
scanned cards) still signed with `AWS_ACCESS_KEY_ID` and `AWS_REGION` after the switch, so R2 refused it with "The file
store didn't take the file" (found by Jason posting a photo Moment). It now signs with `S3::creds()`, the store's own key
and region, like every other call. Uploads straight from the browser (in parts) were never affected. Files the site
tried to keep in that window stayed on the server, where they still work; `Store::moveOld()` (Settings, or the `files_tick` job if it's still scheduled) moves them to R2.
