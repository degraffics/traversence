# Review: `NpiBulk.php` against 0164, 0163 and 0066

This is a draft for human review. Everything here comes from the attached file and the three decision records. Callers and callees (`IntakeStager`, `ListingIdentity`, `CrawlQueue`, `AutoImport`, `Verify`, the worker and the intake endpoint) are **not shown**, so wherever a finding depends on them I say so.

## What the file does

- **Stores a reference copy.** `batch()` keeps a copy of NPI organisations (`npi_orgs`) at 99–120.
- **Stages new listings.** `stageNew()` stages finds in our cluster ZIPs as new listings and counts `auto_imported`, i.e. listings it created (149–199).
- **Deletes dropped records.** At the end of a load it deletes records missing from the newer file (`finish()`, 202–210).
- **Confirms listings.** It supplies "trusted registry" confirmations to listings in review (`matches()`, 216–235).

## Defects

### Creating and publishing listings

**1. NPI's two guards are missing (high).**
- **Where:** 153–185. Every organisation in a cluster ZIP with a numbered address is staged under a source flagged `create_listings` (196). Line 198 counts auto-imported listings.
- **What goes wrong:** Nothing holds back the non-public kinds the code itself labels: Home health care, In-home supportive care, Hospice, Ambulance service, Medical transportation, Residential mental health treatment, Substance use treatment (lines 48–56).
- **What it breaks:** 0164 §1.5 says non-public kinds (air ambulance, home care, residential treatment, VA registrations) "are kept as records but not shown as places to visit, with a Review step"; also §12 C3 and 0163 §8 stage 8.
- **Also relevant:** residential treatment addresses touch the privacy line (people tied to a vulnerable service).
- **Departments:** I can't tell whether departments fold into their parent (same address or building), the other guard. `ListingIdentity::fold` at 190 is not shown.
- **Smallest fix:** keep a list of non-public taxonomy codes. For matching rows, store in `npi_orgs` only (skip staging) or stage with a hold flag plus a Review step "List anyway".

**2. Possible publishing while switched off (medium, depends on code not shown).**
- **Where:** `batch()` (99) and `stageNew()` never check `self::off()`, the master switch or W1. `off()` is only read in `status()` (79).
- **What it breaks:** 0163 §9 and 0164 §1.2 step 1 say a call happens only when master, workflow, task and service are all on.
- **Why it's conditional:** if the intake endpoint already refuses while paused (`CrawlQueue::pausedWhy()`), this is covered. That endpoint is not shown.
- **Smallest fix:** at the top of `batch()`, return `[0,0]` when `self::off($pdo) !== null` or `CrawlQueue::pausedWhy()` is set.

**3. A PO Box passes as an address (medium).**
- **Where:** 154 accepts any address containing a digit, so "PO Box 190" qualifies.
- **What it breaks:** 0164 §3.1 says "a PO Box never a street"; §3.5's floor needs a street address or phone; "PO Box 190" is named as junk.
- **Smallest fix:** also skip when `preg_match('/^\s*(p\.?\s*o\.?\s*box|box)\b/i', $address)`. If a phone is present, stage it with the phone as the "way to find it" instead.

**4. Doc comment contradicts the rule book (low).**
- **Where:** 17–18 says an NPI-only place "needs one more reference … before it can publish".
- **What it breaks:** 0164 §12 C3 says "NPI alone establishes", replacing 0133's "needs one more". Whether `AutoImport` still enforces the old rule is not shown.
- **Smallest fix:** rewrite the comment to C3 and the two guards. Check `AutoImport` separately.

**5. Confidence is hard-coded (low).**
- **Where:** 179 (`45 + 10`).
- **What it breaks:** 0164's header ("one home per fact": numbers live in 0163) and 0163 §12.
- **Smallest fix:** read the value from the Thresholds/0163 §12 settings.

**6. The phone has no recorded source (low).**
- **Where:** 176 records sources for `name` and `address` only, though a phone is supplied at 175.
- **What it breaks:** 0164 §3.5 ("each NAP field shows how it's known") and §2.4 (provenance on every fact).
- **Smallest fix:** add `'phone' => [$url]` when the phone is not empty.

**7. The source register row lacks licence and terms (low, depends on code not shown).**
- **Where:** 196 creates the register row with no licence, use, or terms-checked date visible. `createSource`'s signature is not shown.
- **What it breaks:** R9's register fields ("Licence", "Use", "Terms checked") and R18 (terms recorded before regular reading).
- **Smallest fix:** pass licence = public domain, use = store facts, and the terms date. If `createSource` can't take them, set them after creation.

### Matching against what we hold

**8. A second, divergent identity test runs before the one check (medium).**
- **Where:** 156–163 runs its own match, using the local `sameName` (141–146). That measure divides by the **shorter** word list, so "Smith" vs "Smith Family Dental" scores 1.0.
- **What goes wrong:** a provider at a facility can be silently skipped as "already listed" before `ListingIdentity::resolve()` ever sees it.
- **What it breaks:** 0164 §3.1 calls for "one check … one name measure everywhere (`AutoImport::sameName`, against the longer name, so a provider at a facility stays apart)".
- **Smallest fix:** delete the pre-filter at 156–163 and rely on 190–191. Alternatively, swap in `AutoImport::sameName`.

**9. Matches are dropped instead of merged (medium).**
- **Where:** a `same` or `covered` match is thrown away (163, 191), as is a prior raw-index hit (166).
- **What goes wrong:** the existing listing never gains the NPI record as evidence, at least in this file.
- **What it breaks:** 0164 §3.1 ("`same` merges into the existing listing (gaps only, R4)") and R11 ("every check … writes what it saw once"; "nothing seen is lost").
- **Smallest fix:** for `same`/`covered`, add the NPI source to the matched listing's identity record (gaps only) instead of `continue`.

**10. The check runs outside the writing transaction (low, depends on code not shown).**
- **Where:** `resolve()` at 191 is a pre-filter run outside the write and lock.
- **What it breaks:** 0164 §3.1 says the check is "called inside the transaction that writes … a lock on the identity".
- **Why it's conditional:** if `IntakeStager::stage/approve` re-runs the check under the lock, this is fine. Not shown.
- **Smallest fix:** confirm that `stage()` resolves under the lock. If it does, keep 191 as an optimisation only.

**11. `matches()` can confirm the wrong business (medium).**
- **Where:** 226–229. It accepts the loose shorter-list name match plus only the **house number** within the ZIP. The street is never compared.
- **What goes wrong:** a different business at "120 W Main" vs "120 E Cleveland" with one shared word becomes a trusted-registry confirmation. It also returns the first hit only (230).
- **What it breaks:** 0164 §3.1's tuned match rules ("one name measure everywhere") and R11 ("counting is honest").
- **Smallest fix:** use `AutoImport::sameName` and require the street name token to match too when matching by address.

**12. Phone extensions corrupt the number (low).**
- **Where:** 110 and 220 take the last 10 digits after stripping non-digits. A number with an extension ("928-555-1234 x12") becomes a wrong phone. Whether the worker's file ever includes extensions is not shown.
- **What it breaks:** 0164 §3.1 ("extensions removed from phones").
- **Smallest fix:** cut at `/x|ext/i` before stripping, or take the **first** 10 digits after a leading 1.

### Changing or removing data

**13. Dropped records are deleted, not recorded (medium).**
- **Where:** `finish()` hard-deletes every `npi_orgs` row not in the newest file (204–206).
- **What goes wrong:** a dataset dropping a place is a closure signal. It should be recorded, lower confidence and ask "Still open?". Here it simply vanishes and nothing reaches the listing.
- **What it breaks:** 0164 R12 ("a dataset dropping a place … the fact is recorded"), its "Signals against", 0163 §12 (F1), and R11 ("nothing seen is lost").
- **Smallest fix:** mark rows `dropped_at` instead of `DELETE`, and raise one signal against any listing that relies on them.

**14. Rows over 1,000 are silently dropped, then deleted (medium).**
- **Where:** 103 silently drops any rows beyond 1,000 in a batch.
- **What goes wrong:** combined with #13, organisations that were sent but never saved are then removed as "not in this file". The worker sees only the `saved` count; whether it compares counts is not shown.
- **What it breaks:** R11 (nothing seen lost), and the same spirit as §1.4's batch receipts.
- **Smallest fix:** return the number skipped for size, so the worker resends. Do not run `finish()` while any batch is short.

### Parts with nothing found

- **AI, Gemini, a person's words (R5, R13, §0.8):** no AI calls and no person's words in this file. Nothing found.
- **OpenStreetMap, Google, Facebook (R9, R14, §0.5):** none used. Nothing found.
- **Fetching (R18, §1.2–1.3):** the download happens on the worker, which is not shown. Nothing to judge here.
- **Hiding and takedowns (R7):** no hide or unhide paths in this file.
- **Nations' land (0066, R7):** nothing in the file decides on nation sites or protected places. Two things to check elsewhere:
  - NPI records on a nation's land (Kayenta's 11, 0163 §8) are staged like any other. Public listings there are public under 0066 §3.
  - Whether the resulting listings carry the nation's name and visitor rules (R7) is decided downstream, and that code is not shown.
- **Region (R17):** the states are limited to AZ and NM (24, 107), and staging is limited to cluster ZIPs (154). Consistent.

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| NpiBulk.php:153–185, 196–198 | non-public NPI kinds (home care, residential treatment, ambulance, hospice…) staged and auto-imported; department fold not shown | 0164 §1.5 NPI guards, §12 C3; 0163 §8 st. 8 | high | skip/hold non-public taxonomy codes, add Review step "List anyway" |
| NpiBulk.php:99, 149 | batch/stage ignore task, master and W1 switches (unless the endpoint checks; not shown) | 0163 §9; 0164 §1.2(1) | med | return early when `off()` or `pausedWhy()` is set |
| NpiBulk.php:154 | "PO Box 190" passes as an address | 0164 §3.1, §3.5 floor | med | reject PO Box patterns; rely on the phone if present |
| NpiBulk.php:17–18 | comment says NPI needs one more reference | 0164 §12 C3 | low | rewrite comment; check AutoImport |
| NpiBulk.php:179 | confidence number hard-coded | 0164 header (one home per fact); 0163 §12 | low | read from settings |
| NpiBulk.php:176 | phone has no recorded source | 0164 §3.5, §2.4 | low | add `'phone' => [$url]` |
| NpiBulk.php:196 | register row without licence, use or terms date (createSource not shown) | 0164 R9, R18 | low | record public domain / store facts / terms date |
| NpiBulk.php:141–146, 156–163 | second identity test with shorter-list name measure skips providers at facilities | 0164 §3.1 (one check, one name measure) | med | remove pre-filter or use `AutoImport::sameName` |
| NpiBulk.php:163, 166, 191 | same/covered matches discarded, NPI evidence not merged | 0164 §3.1, R11 | med | attach NPI source to the matched listing (gaps only) |
| NpiBulk.php:191 | resolve outside the writing transaction and lock (stage not shown) | 0164 §3.1 | low |