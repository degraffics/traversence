# Listing audit, end to end (2026-11-27)

Jason, 2026-11-27, with a 390 screenshot of Summit Healthcare Regional Medical Center (Show Low): "End to end audit"
(decisions/0181's note, #1620; gates 1 and 3; live harm). Scope: every 1A health listing and 1A's top 50 by taps. Each
defect is traced to its root cause with file:line, how many listings it touches, and one fix per cause, never a one-off.
Read on the live mirror (`degraffics/traverse-3way-sync`, Sync 2026-11-27 19:50 UTC) and rendered on a harness built from
it. Data & Content's part (the data: categories, hours, duplicates, sources) goes in its own section below.

## Frontend & Shop's part: the page, its links, its photo, hours, claim, search

### F1. A stock storefront as a hospital's hero photo (found 2026-11-27)

- **Seen:** Summit Healthcare's hero is a stock "antiques storefront", labelled Representative.
- **Root cause:** `app/lib/StockLibrary.php:450-467` (`rank`). A library photo tagged to the listing's town or an area
  above it scores 20–100 on place alone (:456); the subject match (:458-461, the listing's category against the photo's
  subjects) only adds to it and isn't required. So any stock photo of Show Low outranks "nothing fits", and the
  `'fallback' => 'main street'` (`website/listing/view.php:370`) is never reached.
- **Why card-fixes didn't stop it:** its rules (handoffs/card-fixes.md: no stock photo twice in a list, none on a
  memorial, the credit on the photo) never checked the photo against the business's kind.
- **Touches:** every listing with no photo of its own (no hero media, no place photo) whose town or area has a live
  stock photo whose subjects don't match its category. The count comes from one live read (pending).
- **Fix (the session manager agreed the direction; a ranked ticket after the audit, not built yet):** a representative
  pick needs at least one subject hit, with place only breaking ties. With no subject hit, a neutral hero (no photo),
  never another kind's photo. Health and emergency kinds never get a stock storefront.

### Still to come

Rendered at 1280 and 390, as a guest and as staff: the kind line running under the photo credit; Edit and ⋯ over the
title; every link and button followed ("Location and map pin" → `/listing/location.php`); hours against `Hours::status`
in the listing's own zone; the claim path; its place in search ("hospital show low", "urgent care show low").

### Housekeeping found on the way

- `app/ui/faq.md.orig` is on the live server: a stray copy, read by nothing (no `.php` or `.js` in the mirror names it).
  It goes under "removes" in the next Frontend & Shop release.

## Data & Content's part

(to come)
