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
- **Ruled:** the CMO (7) approved the fix as proposed (2026-11-27, through the session manager): gate 1, one root-cause
  fix, tested at 1280 and 390, its slot with the audit's ranked tickets.
- **Fix:** a representative
  pick needs at least one subject hit, with place only breaking ties. With no subject hit, a neutral hero (the brand pattern, no photo),
  never another kind's photo. Health and emergency kinds never get a stock storefront.

### Still to come

Rendered at 1280 and 390, as a guest and as staff: the kind line running under the photo credit; Edit and ⋯ over the
title; every link and button followed ("Location and map pin" → `/listing/location.php`); hours against `Hours::status`
in the listing's own zone; the claim path; its place in search ("hospital show low", "urgent care show low").

### Housekeeping found on the way

- `app/ui/faq.md.orig` is on the live server: a stray copy, read by nothing (no `.php` or `.js` in the mirror names it).
  It goes under "removes" in the next Frontend & Shop release.

## Data & Content's part

*Data & Content, for the PM (8), shared with Frontend & Shop and the IT Manager (11). Live harm, gates 1 and 3 (0181's
note, #1620). Jason: "End to end audit", on Summit Healthcare Regional Medical Center, Show Low. Read-only: the site's
public pages and search, live-read.yml (logs deleted), the CMS NPI Registry's public API, and the live code at Sync
2026-11-27 19:50 UTC. **First findings, 2026-11-27 evening; the full audit (every 1A health listing and the top 50 by
taps, with counts per cause) by about Dec 2.** Companion counts: `handoffs/health-1a/counts-2026-11-27.md`.*

### The listing Jason opened: #14779, Summit Healthcare Regional Medical Center (public page, 20:45 UTC)

What a visitor sees: kind **Clinics**; **"How sure we are: Qualified, 56 of 100"** ("Address and phone in the NPI
Registry · Nov 2026", "Website on its own website · Nov 2026"); **About:** "Comprehensive healthcare in Show Low, AZ.
Board certified specialists offering quality preventive and diagnostic services in Lakeside, AZ."; **Hours:** four
different lines ("Monday – Thursday 8 AM – 5 PM", "Friday 8 AM – 12 PM", "Monday – Friday 7:30 AM – 5 PM", "Monday –
Friday 7 AM – 5 PM"); a stock photo; unclaimed. NPI: General Acute Care Hospital, Rural (282NR1301X), 2200 E Show Low
Lake Rd. And the same hospital is **listed 8 times** (counts file).

### Each defect to its root cause (first pass)

**C1. The About is the whole health system's website description, copied as this place's.** Not AI-written. The
crawler fills a missing description with the website's `<meta name="description">` (`workers/crawler/crawler.py:805`
`meta_description()`, used at :877, :2213, :2443 and :2465 "found['description'] = meta_description(home)"). A health
system's (or chain's) homepage describes all its locations, so a Show Low hospital gets Lakeside's line. Nothing checks
that the text is about this place (its town, its kind). 0164 allows vetted public facts only: a site-wide meta line is
neither vetted nor about the listing. **Touches:** every listing whose description came from a shared website (count
in the full audit: listings sharing one website domain with different addresses).
**Fix:** a website's description is kept only when the site is the place's own (one address on it), or when the text
names the listing's own town and nothing contradicts its kind; otherwise it goes to the record, not the About, and the
About says nothing rather than something wrong. Never over a person's words (0164).

**C2. Hours from several departments shown as one place's hours.** The four lines are a website's departments, all
kept (`listing/view.php:500–502` shows every stored line). **Touches:** listings whose hours came from a multi-location
website. **Fix:** hours kept only when one set is found for the place's own address; else none, said as "Hours not
known" (0058 §20's page already has that line).

**C3. A hospital filed as "Clinics": the NPI's own label isn't used when the NPI only verified the listing.** Two paths:
- A listing **made from the NPI file** gets its kind from the NPI code: `NpiBulk::label()` (`crawler/NpiBulk.php:46–71`,
  '282N' → "Hospital") then `IntakeStager::NPI_CATEGORY` (`crawler/IntakeStager.php:437`, 'hospital' → Hospitals). That
  path is right.
- A listing **made elsewhere and later matched in the NPI** (the crawler's verify, `crawler.py:1039`) carries the NPI's
  taxonomy *description* ("General Acute Care Hospital, Rural") only as evidence; nothing re-files its kind from it,
  and `NPI_CATEGORY`'s keys are NpiBulk's short labels, not those descriptions. Its kind stays the name classifier's guess
  ("…Medical Center" → Clinics). **Touches:** every listing with NPI evidence whose kind differs from its NPI code's
  (full audit). **Fix:** one mapping from the NPI taxonomy *code* (both paths carry or can carry it) to our category,
  applied when NPI evidence arrives, only where the kind came from the crawler (never a person's choice), with Undo;
  the verify step records the code, not only the description.

**C4. One hospital, eight listings: NPI departments loaded as separate places, and the identity check can't fold them.**
Each NPI organisation number (the hospital, its ER group, hospitalists, departments) is its own record; their names
differ ("Summit Healthcare Regl Med Center", "Regional Medical Center", "Summit Family Medicine") and so do their phones,
so the one identity check (name, phone, street; 0163 §12, 0164 §3.1) sees different businesses at one address.
**Touches:** Summit 10 rows at 2200 E Show Low Lake Rd and 7 at 4951 S White Mountain Rd; Statclinix 3; Banner 2 (full
audit: all addresses with 3+ health listings). **Fix:** at one street address, NPI records of one organisation family
(same parent name, or the same authorized official) fold into the place, departments kept as its sub-records, not
places; through the identity queue with Undo.

**C5. People listed as places.** 123 individual providers (NPI-1) appear as places to visit in three towns (7 under
Pharmacies, which is why "urgent care" shows two pharmacists first). The rural test's guard (research/rural-test-
2026-11-14.md §8.9) says non-public kinds are kept as records, not shown as places; the individuals came in anyway (the
path is in the full audit). **Fix:** individuals kept as records under their practice, never a place; Undo.

**C6. "Qualified" says "Enough independent sources agree", but it only counts sources for the address, phone and
website.** `ListingConfidence` (`app/lib/ListingConfidence.php:24–31`): registry 22 + own site 18 + … ≥ 51 is
"Qualified". Nothing in the score checks the kind, the About or the hours, so a listing with a wrong kind, a wrong
About and four sets of hours reads as "Qualified". **Fix:** the label says what it vouches for ("Address and phone
confirmed by 2 sources"), and a disagreement in kind (C3) or a shared-site About (C1) lowers it or holds it at "Being
checked" ("words follow the state").

**C7. Search's urgent-care answer lists clinics first.** The "Feeling sick" need lists "Clinics and urgent care" by
listing number, so five Lakeside clinics come before Banner's urgent care (#8938). Search's lane (Frontend & Shop):
urgent care before clinics in that need.

### Ranked by harm (first pass)

1. **C3 + C4** (a hospital shown as a clinic, eight times): someone needing a hospital can't tell which is real.
2. **C5** (people as places; pharmacists ahead of pharmacies): wrong answers to a health need.
3. **C1 + C2** (wrong town in the About, contradictory hours): a visitor drives to the wrong place or time.
4. **C6** (a label that vouches for more than it checks): trust.
5. **C7** (urgent care ranked under clinics).

### Next, for the full audit (by about Dec 2)

- Each of 1A's health listings and the top 50 by taps (from the outbound-tap counts) traced: source, identity, kind and
  why, About and its source, hours and their source, confidence lines; one row each.
- Counts per cause from live (live-read.yml): listings sharing a website domain at different addresses (C1, C2), NPI
  evidence vs kind (C3), addresses with 3+ health listings (C4), "Last, First" names in health (C5).
- Where the individuals (C5) and the departments (C4) entered: which load or crawler step, with file:line.
- 0066: Pinetop–Lakeside borders the White Mountain Apache Tribe's land; every listing traced is a public facility or
  business off the nation's land; nothing held is named.

### Open: ADHS's licensed list (Summit Healthcare Urgent Care, Show Low)

Not in NPI, not in our listings. The read of ADHS's AZ Care Check (`azcarecheck.azdhs.gov`, `www.azdhs.gov`) is refused by
this cloud environment's network policy (2026-11-27 ~21:00 UTC); asked through the session manager to allow the two hosts.
Public pages found meanwhile (not a licence): the clinic's own page gives 4951 S. White Mountain Rd., Bldg A, Show Low
85901, (928) 537-6743; Building B is Summit's Outpatient Surgery Center (a separate place). Nothing is added from these
until the licence is read.
