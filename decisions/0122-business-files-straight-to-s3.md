# 0122: Business files go straight to S3

Status: Built (2026-10-24). Jason: "Why are we worried about Bluehost limits if uploads are going to S3?" He's right:
master-aos §5D and commercial.md put business and member files in object storage, not on the web host. 0120's
addendum sent big files to Bluehost in pieces; this replaces that path whenever S3 is set up.

## How an upload goes

1. **Start** (`business/files.php`, JSON `{s3: "start"}` → `BizFiles::s3Start`): the site checks the business's
   role and plan, the quote, the file's kind and type (by extension), its size (1 GB at most), and the storage left. It
   then starts a multipart upload at `biz/<business>/q<quote>/<random>.<ext>` and returns one short-lived signed
   link per 8 MB part (`S3::partUrls`, an hour). The upload is remembered in the session (who, which business, which
   quote, size).
2. **Parts:** the browser PUTs each part to its own link, directly to S3. Each part is retried twice, and the bar shows
   progress. The file never passes through Bluehost, so its upload limit doesn't matter.
3. **Done** (`{s3: "done"}` → `BizFiles::s3Done`): only an upload this person started on this business is accepted.
   The site joins the parts, then checks the stored size and the file's real type from its first bytes (finfo; Office
   files by extension, since they're zips). It checks storage again and records the file with its `store_key`
   (`biz_files.store_key`, BusinessData v7). Anything wrong (a part missing, the wrong size, a program dressed as a
   PDF, storage full) is deleted from S3 and the person is told why.
4. **Abort** (`{s3: "abort"}`): when a part can't get through, the page cancels the upload.

The 24 MB question stays (above it the page asks first, with what's used), because big files still take a while and use
storage. Bluehost's limit was never the reason for it.

## Seeing and removing

- **Seeing** (the team's `business/files.php?f=`, the customer's `q-file.php`): `BizFiles::send` redirects to a
  15-minute signed link (`S3::link`) with the file's own name and type. Pictures, PDFs and video show in the browser,
  and other files download. S3 answers byte ranges, so video seeks.
- **Removing** deletes the object from S3 and frees the storage.
- Files stored on the server before this (no `store_key`) keep working from there.

## When S3 isn't set up

Without the keys, the site keeps 0120's path: pieces under the host's limit, joined on the server. Nothing breaks
before the bucket exists.

## What has to be set (Jason)

`.env` (secrets only there):

```
AWS_ACCESS_KEY_ID=…
AWS_SECRET_ACCESS_KEY=…
AWS_REGION=us-east-1          # the bucket's region
S3_BUCKET=traversence-files   # the bucket's name
# S3_ENDPOINT=…               # only for an S3-compatible store (Cloudflare R2 and the like); leave out for AWS
```

The key's IAM policy needs `s3:PutObject`, `s3:GetObject`, `s3:DeleteObject`, `s3:AbortMultipartUpload` and
`s3:ListMultipartUploadParts` on `arn:aws:s3:::<bucket>/*`.

**Bucket CORS** (S3 → the bucket → Permissions → Cross-origin resource sharing), so the browser may PUT parts and read
the ETag:

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

**Lifecycle rule** (Management → Create lifecycle rule, whole bucket): "Delete expired object delete markers or
incomplete multipart uploads" → incomplete multipart uploads after **1 day**. This clears uploads someone walked away from.
Keep the bucket private (Block all public access on), because every link is signed.

## Tested (local S3 stand-in, moto)

- A 30 MB PDF went up in 4 parts straight to the store.
- A WebM proof played on the customer's page through the redirect, with byte ranges (206).
- Removing a file deleted the object.
- Refused on Done, with the object thrown away: a program named `.pdf` (by its first bytes) and a wrong size.
- An upload key the page didn't start was refused.
- Over 1 GB and past the storage were refused at Start, with Add storage.
