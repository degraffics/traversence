# Review of `OpenLoads.php` against decisions 0164, 0163 and 0066

**Draft for human review.** Line numbers refer to the attached `OpenLoads.php`. "Not shown" means the behaviour lives in a file that wasn't attached (IntakeStager, ListingIdentity, ListingConfidence, Automation, OpenPlaces, EngineCase, the `loads.php` caller).

## The paths in this file

**Creates**
- Load tasks: lines 38–39, through `ListingIdentity::ensureTask`. The header (line 14) says they're added off, but that function is not shown.
- A crawler source record with `create_listings`: line 225.
- Staged finds and auto-imported listings: lines 132–134.
- Load and batch ledger rows: lines 141–144, 167, 203.
- Kept OpenStreetMap rows: line 205, through `OpenPlaces::keepExtract` (not shown).

**Changes**
- Closure signal metadata and a confidence refresh: lines 214–217.
- The identity recheck, which may merge: lines 140 and 153 (`recheckTick` is not shown).
- Probe state and an engine case: lines 234–235.

**Publishes**
- Only indirectly, through `IntakeStager::stage`'s `auto_imported` (lines 132–134). Whether the floor, readiness, protected-name and nation checks run there is not shown.

**Hides**
- Nothing in this file. Recheck merges that hide an id with `same_as` happen inside `recheckTick` (not shown).

**Reads**
- `due` (lines 59–68), `batchDone` (159–160), `geofabrikDue` (188–193), `lastProbe` (240).

**Parts with no findings**
- **A person's words:** none are handled in this file.
- **AI, Gemini or Claude calls:** none.
- **Emails:** none are kept. Line 7 says so, and no email field is read. This is consistent with §2.2 and R9.

---

## Defects

### 1. No per-record licence check before storing (lines 104, 121, 132). High.
**Rule:** 0164 R9 ("its licence decides what we may do… the licence travels with the fact"; "a licence change follows… an upstream's licence, per record, at each load"; "Terms-restricted sources can't be set to store or show"), §0.5, and R9's open-datasets row ("once their licences are confirmed").

**What the code does:** each record's licences are collected (line 104) and attached (line 121). They are never compared against an allowed list. A record whose upstream licence is share-alike (ODbL) or unknown is staged as a value we keep and show, the same as a CDLA or Apache record.

**Smallest fix:** before line 107, skip the record (or mark it evidence-only) unless every licence in `$lic` is on an allowed list held in the register (for example CDLA-Permissive-2.0, Apache-2.0, CC0). Count those records as `skipped`.

### 2. Source auto-created with `create_listings` but no licence or terms record (line 225). Medium.
**Rule:** 0164 R9 (register fields: Licence, Use, Terms checked), R18 ("terms and licence checked and recorded before a source is read regularly"), and 0163 §8 stage 8 (Foursquare only "once a Hugging Face account accepts its terms").

**What the code does:** `createSource` grants `create_listings` from inside the load. Nothing records the licence, its use or a terms-checked date, and nothing refuses when the terms aren't confirmed.

**Smallest fix:** don't auto-create. Return 0 and refuse the batch (count the rows as skipped) unless a register row exists with a licence and a terms-checked date.

### 3. No protected, confidential, nation-land or person check before staging (lines 93–124). High if IntakeStager doesn't apply these; that part is not shown.
**Rules:**
- 0164 R7: "A sacred, burial or protected place is held by default"; archaeological words are held; "Confidential listings…"; shelters' addresses are Restricted.
- R7 / 0066: "pins on a nation's land move to a public place"; "Decisions about nations' land… never made by a machine".
- R6 and §3.4: private non-business home addresses are removed before storage.
- The 2026-11-28 note: about 310 people were shown as places.

**What the code does:** only `DataCleanup::junkName` is applied (line 93). Line 123 attaches the dataset's own point for every record, including towns on the Navajo Nation. `due()` puts Kayenta first (lines 60–62, "the fewest listings… Kayenta has 0").

**Smallest fix:** in the loop, before `$cands[] = $c` (line 124):
- run `Landmarks::isProtected` / `ListingSafety::isConfidential` on the name and category, and hold for a person on a hit;
- drop `where.point` when it falls inside `nation_shapes`, so the pin moves to the town's public point;
- flag person-like names for the same people guard.

**Open question for the reviewer:** 0066 §3 says public listings on a nation's land are presented. Whether machine-publishing Overture businesses on the Navajo Nation needs a person's call is not settled in the attached text.

### 4. Confidence hard-coded and not matching the rule it cites (lines 106 and 116). Medium.
**Rules:** 0163 §12 ("A record under 0.5 Overture confidence | half its weight"; weights are "settings… tuned by a director") and 0164's header ("One home per fact: numbers… live only in 0163").

**What the code does:**
- `30 + 30 * min(1, $conf)` is linear. It doesn't halve under 0.5, although line 116's comment claims it does.
- The 30 and 30 are constants in code, not read from settings.
- A missing confidence defaults to 0.5 (line 106).
- Overture's confidence scale is also applied to Foursquare.

**Smallest fix:** read the dataset's weight from the settings, multiply it by 0.5 when `$ds === 'overture' && $conf < 0.5`, and use no default when