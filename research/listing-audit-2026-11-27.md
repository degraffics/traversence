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

### Rendered (2026-11-28, read-only)

Rendered at 1280 and 390, as a guest and as staff, on a harness built from the live mirror (Sync 19:50 UTC; seeded:
a Show Low hospital, an urgent care, an antiques shop, one stock photo "antiques, storefront" tagged to Show Low's
cluster). Each count is the SQL to run in one live read (pending), on live table names.

**F1 confirmed.** The antiques storefront is the hospital's hero at both widths for both. Card-fixes' rules live only in
discovery's cards (`discovery/index.php:382`) and the list de-dupe; the listing hero goes `tv_explore_hero` →
`StockLibrary::forSlot`, which has neither. It also goes against `view.php:369`'s own comment ("a stock photo of its
kind of business") and 0143 ("representative images of the subject"). Fix as ruled, applied in `forSlot` so every slot
gets it: health, emergency, government, memorials, religious and cemetery kinds never get stock. The ranking change
needs a note on 0143.

**F2. The kind line hides under the photo credit** (390x664 and 360x640, staff; just clears at 390x844 and for guests).
- Cause: two layers sized apart. `view.php:267` caps the listing hero at `clamp(200px,32vh,340px)` (B5);
  `app/ui/explore-hero.php:205` pins the stock credit (`.xh-stkw`) at `bottom:3.4rem` on phones, while `.xh-head`
  (`:208`) grows down: a two-line name, then Claim it wrapping (`.xh-corner`, `:293`), then the kind line.
- Touches: F1's set on phones under about 750px tall; worse for names over about 24 characters, unclaimed (Claim
  it), or a manager viewing ("Add your own photo").
- Fix: one flow on phones: the hero a column (head at the top, credit and actions at the foot), `min-height` for the
  fixed height, so it grows to fit; or the credit in `.xh-acts`' row, as `.xh-cap` already is.

**F3. Edit (admin), ⋯ and the Link sit on the card's title** (1280 and 390).
- Causes: `view.php:289` places `.lv-admin` at `right:3.2rem` inside the corner that `js/link-button.js:20` places
  (two 30px buttons, about 4.1rem), so staff's Edit overlaps ⋯; and `view.php:276`'s `.lv-name.lv-loc{padding-right:0}`
  (B5's name over the address) dropped the 5.5rem clearance `.lv-name` (`:277`) and `h1` (`:251`) keep, so a name
  over about 22 characters runs under ⋯ and the Link at 390.
- Touches: every listing for staff; for everyone, names over about 22 characters
  (`SELECT COUNT(*) FROM entities WHERE entity_type='merchant' AND is_suppressed=0 AND CHAR_LENGTH(name)>22`).
- Fix: the clearance back on `.lv-loc`'s first line; Edit (admin) moves into the ⋯ toolkit as a named staff item.

**F4. Hours: no "open now" on the page; "24/7" unread; times in the server's zone.**
- The page prints the raw lines only (`view.php:500-503`). `Hours::parse` reads "24/7" as all days (`Hours.php:73`) but
  its 24-hour test (`:77`) leaves out `/7`, so "24/7" and "Emergency Department: 24/7" are unknown. Every caller
  takes the day and minute from `date()` in the server's zone: `UniversalSearch.php:1204`, `search_listings.php:482`,
  `SearchReading.php:112`, `hub/index.php:184` (tested: "Mon-Sun 8am-8pm" at 23:30 UTC reads Closed in UTC, Open in
  Phoenix).
- Touches: every listing with hours (`SELECT COUNT(*) FROM entity_metadata WHERE meta_key='hours'`); 24/7 ones
  (`… AND meta_value LIKE '%24%7%'`).
- Fix: in search release 2 (slot #9): `Hours::now` in the listing's own zone for search rows, situations and the page's
  line, and `|24\s*\/\s*7` at `Hours.php:77`. The four other callers move to it in the same release. Gap: Navajo
  Nation land keeps daylight saving while Arizona doesn't; `Hours::zone` reads by state today.

**F5. "Suggest an edit" missing on unclaimed listings, its words wrong on claimed ones.** An unclaimed listing's aside
offers only "Are you the owner? Claim it" (`view.php:564`); Suggest an edit (`?mode=contribute`) shows only for
claimed ones and drop boxes (`:562`, `:566`), whose page (`claim.php:199+`) is worded for unclaimed ("the owner
confirms them when they claim it"). Fix: Suggest an edit for every viewer who isn't its team, worded by claim state.

**F6. The claim path treats a hospital as a business, and offers staff a claim.** `Claims::KIND_GROUPS`
(`Claims.php:371`) maps only Government, Non-Profit and Religious, so Health & Medical is "business" (Owner, Co-owner,
Manager, Employee). `view.php:379-381` and `:564` show Claim to staff (CLAUDE.md: never offer what an account should
use elsewhere; staff have the editor). `:564`'s "Are you the owner?" ignores `KIND_WORDS`. Fix: Health & Medical in
`KIND_GROUPS` (roles for a hospital need a note on decisions/0070, for Jason); staff get "Open in the editor"; the
aside's words from `KIND_WORDS`.

**F7. Comment starters about food on every listing.** "Best thing to order · Parking · Good for kids · Accessible?"
on a hospital (`view.php:669`, hard-coded). Fix: starters by category group, beside `ListingActions`.

**F8. "Location and map pin" lands where it says** (staff: the pin and address form; a visitor: Suggest a fix). Edge:
staff see it on hidden listings (`view.php:424`) but `ListingLocation::get` (`ListingLocation.php:46`) filters
`is_suppressed = 0`, so they get "isn't there any more"; and a visitor can open the location page of a listing that
isn't public. Fix: `ListingLocation::get` takes view.php's own rule (public for visitors; any for staff and its team).

**F9. Directions opens a pin, not a route.** `view.php:172`, `:175` build `maps/search/?api=1&query=lat,lon`; the
same page's Nexus cards (`:584`) use `maps/dir/?api=1&destination=`. Fix: one helper in `ListingActions` for both.

**F10. Its place in search** (recorded; ranking is IT's release 1): "hospital show low" and "summit healthcare" find
it first ("Hospital · Show Low, AZ"); "urgent care show low" finds nothing ("urgent" read as a time word,
`UniversalSearch.php:355`); "emergency room show low" and "er show low" find no listings (no synonym to hospital; the
'Emergency room and hospitals' need, `Situations.php:140`, isn't reached by those words). Passed to IT.

**Worked:** Call, Website, Save contact (.vcf), QR (all in ⋯); the town link; Tell us (with ×); the hero's ⋯; no script
errors and no sideways scroll at 390.

### Housekeeping found on the way

- `app/ui/faq.md.orig` (403,551 bytes, an older copy) is on the live server, read by nothing (no `.php` or `.js` in the
  mirror names it). It goes under "removes" in search release 2.

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

### Counts per cause, from the public search (2026-11-27 ~21:30 UTC)

Read as a visitor through `/api/search_listings.php` (`category=health-and-medical`, 8 mi around 85901, 85929, 85935,
85936, 85938, 85925, 86025, 85937; de-duplicated by id). **852 health listings**: Show Low 440, Lakeside 137, Snowflake
74, Pinetop 44, Holbrook 42, Springerville 41, Taylor 28, St. Johns 29 (spelled four ways: "Saint Johns" 25, "St. Johns"
2, "St Johns" 1, "St John'S" 1), Eagar 17. None claimed. The search's own fields: a description on 65, a website on 28,
hours on 4. So C1 and C2 have to be counted from the listing page or a live read, not from search.

- **C3, kinds:** 17 records named as a hospital or medical center. **None is filed under Hospitals**: Doctors 9,
  Clinics 2, Medical Centers 2, Health Services 2, Physician Assistants 1. Both of 1A's hospitals are affected: Summit
  (#14779 Clinics; #6231, #7325, #7326, #7327, #7332 Doctors) and White Mountain Regional, Springerville (#7390 Doctors,
  #6232 Physician Assistants). The "8 times" from the first pass is 6 Summit-named records plus 2 hospitalist groups at
  its address (#8831, #9196). #7276 "Evans Army Church Hospital" at Summit's address is an NPI name that belongs to no
  place there; trace it in the live read.
- **C4, one address with many listings:** 72 addresses carry 3 or more health listings, 418 listings in all. The
  largest: 2200 E Show Low Lake Rd (Summit) **59**, 4951 S White Mountain Rd Bldg A (Summit's urgent care and clinics) 26,
  118 S Mountain Ave, Springerville (White Mountain Regional) 12, 2650 E Show Low Lake Rd 11, 2109 Navajo Blvd, Holbrook
  11. Most of the 59 are C5's people, not departments, so C4's fix comes after C5's.
- **C5, people as places:** **182** names in NPI's "Last, First" form with no business word, across all nine towns (Show
  Low 113, Holbrook 17, Lakeside 15, Springerville 9, Snowflake 7, St. Johns 6, Taylor 6, Eagar 5, Pinetop 4). By kind:
  Nurse Practitioners 68, Physician Assistants 23, Doctors 18, **Pharmacies 17**, Counselors 15, and others. There are
  about 60 more in the "Last First Credential" form ("Iverson Alison NP"), so the total is about 240. The first pass's
  123 covered three towns; this covers 1A. The exact figure, by NPI entity type (NPI-1), comes from the live read.
- **C1, a shared website:** the search shows only 2 domains used at more than one address (summithealthcare.net 3,
  communitybridgesinc.org 2). That is a floor, because only 28 listings show a website there.

Next: the live read per id for C1, C2 and C5's NPI type; where the individuals entered (the load and its file:line);
the top 50 by taps.

### Open: ADHS's licensed list (Summit Healthcare Urgent Care, Show Low)

Not in NPI, not in our listings. The read of ADHS's AZ Care Check (`azcarecheck.azdhs.gov`, `www.azdhs.gov`) is refused by
this cloud environment's network policy (2026-11-27 ~21:00 UTC); asked through the session manager to allow the two hosts.
Public pages found meanwhile (not a licence): the clinic's own page gives 4951 S. White Mountain Rd., Bldg A, Show Low
85901, (928) 537-6743; Building B is Summit's Outpatient Surgery Center (a separate place). Nothing is added from these
until the licence is read.

## The IT Manager's (11) code paths, behind each defect (2026-11-27 evening, read-only, Sync 19:50)

Read on the live mirror and `workers/crawler/crawler.py` (main). Each defect is given the one place its fix belongs.

**A correction to C5.** Neither NPI path can load a person. The file load keeps only entity type 2 (`crawler.py:2861`), and the API lookup asks only for `NPI-2` (`crawler.py:1007`). So the 123 people came in another way. The likeliest is the open-dataset loads (Overture and Foursquare, `crawler/OpenLoads.php:81-125`).
- `OpenLoads` drops only junk names (:93) and puts the dataset's own category into the find's offerings (:112).
- `IntakeStager::suggestCategories` then matches the offerings against `NPI_CATEGORY` (`IntakeStager.php:471-476`). An Overture category of exactly "pharmacy", "hospital" or "clinic" is stamped `from: registry`, score 99.
- So a dataset's label is treated as a federal record. That is also a second cause of C3.

**C5, the gate that lets people through.** `AutoImport::looksPerson` (:489-498) already recognises a person at a listed practice, as the relation `place` (:440, :455, :459). But:
- `ListingIdentity::resolve` drops `place` (:250), so the person resolves as new.
- `evaluate`'s publish test `$real` never checks for a person (`AutoImport.php:178`).
- The existing guard `NPI_NOT_PUBLIC` (:212-218, applied at :177) covers only finds from the NPI file and four kinds of service.
- Data cleanup's `person` rule (`DataCleanup.php:499`) catches only listings with no category.

**Fix:**
- A find whose name `looksPerson` is kept as a record under the practice it relates to, never a place. That is one condition in `evaluate`'s floor (:177-178), and it covers every path through `IntakeStager::stage`: NPI, the open loads and the crawler.
- `NPI_CATEGORY` applies only when the find's registry is NPI (`IntakeStager.php:471`).
- Data cleanup's `person` rule drops its no-category condition, to catch the people already live (with Undo).

**C3 and C6: the kind, and "How sure we are".**
- The score is `ListingConfidence::compute` (`ListingConfidence.php:85-182`). It reproduces #14779's 56: base 10, registry 22, own site 14.4, two key areas +10.
- The NPI match adds only the name or another name, the phone and the address (`Identity::fromReferences`, `Identity.php:971-980`). The rule is a name 50% alike plus the phone, or the ZIP and street number.
- The NPI taxonomy is never read. `category` is multi-valued (`Identity.php:24`), so it can never be a conflict (:204).
- So an NPI record saying hospital scores a listing filed as a clinic up, never down. The loose name rule also lets a department's NPI record vouch for its parent.
- **Fix:** in `Identity::fromReferences`, read `npi_orgs.taxonomy` and `label`. Add a `category` fact sourced from the registry. Make a mismatch between the registry's kind and the crawler's kind a conflict, which `ListingConfidence` already scores down (:151).
- The NPI code-to-category mapping (C3's fix) goes through the one category writer below.

**Who sets a listing's kind, and why a person's choice isn't safe.** The last writer wins:
- staging's suggestion (`IntakeStager.php:467-492`);
- the auto line (`AutoImport.php:120-140`);
- publish (`IntakeStager.php:570-572`);
- merge, which overwrites `category_id` on any unclaimed listing (:758-762);
- `CategoryClassifier::reclassifyTick` (:241-275, via `setCategory` :289-293);
- staff (`adminSave` :608-610);
- data cleanup (`DataCleanup.php:1056`).

`adminSave` never writes a locked `category` key (:611-618). So a staff re-file on an unclaimed listing can be undone by the next reclassify or merge.

**Fix:** one rule in `CategoryClassifier::setCategory`, used by `merge` and `approveNew` too: a category a person set (staff, the owner or a member) is never overwritten. `adminSave` writes a locked `category` key, source admin.

**C4: departments as places.** The one identity check is `ListingIdentity::resolve` (:237-262), with candidates by ZIP, phone or geohash (:180-198), related by `AutoImport::relationTo` (:405-464). "Summit Family Medicine" at the hospital's address has another phone and a name under 40% alike, so the relation is `other`, a new place (:458).

**Fix:** a branch in `relationTo` before the `other` fall-through (:450-461). The same address plus the same organisation family gives `covered`, or a `department` relation that `resolve` records as a sub-record. Every path gets it: resolve, fold, `recheckTick` and NpiBulk's `stageNew` (`NpiBulk.php:191-192`). There is no second identity path, and Undo is through `ListingIdentity::merge`/`undo` (:380, :453).

The family needs data the copy doesn't keep: `npi_orgs` has no parent or authorized-official columns (`NpiBulk.php:100`). The file loader (`crawler.py:2867`) would carry NPPES's "Parent Organization LBN" and the authorized official's name.

**The hero photo.** The order is the owner's photos (`listing/view.php:363`), then a photo of the place (`PlacePhotos::slides`, :366), then stock (:370, `ui/explore-hero.php:33-43`).
- `PlacePhotos` looks only for parks, monuments, visitor centres and historic sites (`PlacePhotos.php:20-21`), so a hospital always gets stock.
- The stock subject is the listing's category (`view.php:370` → `StockLibrary::pick`/`rank`, `StockLibrary.php:434-467`), so a wrong kind picks a wrong photo. A photo tagged for the town outranks any subject.
- **Fix:** the kind (above). Adding Hospitals and Medical Centers to `PlacePhotos::CATEGORIES` is optional.

**C1 and C2** are as Data & Content traced them (`crawler.py:805` `meta_description`; `listing/view.php:500-502`).
