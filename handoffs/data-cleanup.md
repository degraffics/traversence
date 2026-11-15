# Hand-off: the data cleanup tool (decisions/0163 §8 stage 1b, 0164 §6.1b)

Session `session_01CXLi5ikaAYTSrHS6AnPpWB`, 2026-11-14, from Jason's "start" through the session manager: stage 1b first,
"a tool clean up all the bad data recently added". Built as releases Jason approves in chat, one at a time:

1. **Find and preview** (`data-cleanup_20261114.zip`, built on Sync 2026-11-14 22:08 UTC). Nothing is written.
2. **Act, ledger and Undo** (`data-cleanup-act_20261114.zip`): below.

**State:** step 1 live as release #49 (2026-11-14), merged on Jason's "merge it" (PR #679); Sync 23:28 UTC: all 5 files match the live mirror byte for byte, delivery verified, claims released. First live preview (Jason, 16:24 his time): 4,395 records fail today's rules (listings 4,730 read, staged 970, place store 2,376, landmarks 5,312); "Not a business name" 48. Next: step 2 once Jason sends the per-rule counts; make the result table fit a phone (it runs off the edge at 390px).

## Step 1: find and preview (what's in the zip)

| File | What |
|---|---|
| `app/lib/DataCleanup.php` (new) | the rules (`RULES`, `ACTIONS`, `STORES`), the junk-name check (`junkName`), `preview($pdo, $only)`, the finders per store |
| `app/admin/cleanup-console.php` (new) | `tv_admin_mod_cleanup()`: Insights → Data cleanup; Preview (one rule or all), counts by reason, examples |
| `app/admin/manifests.php` | `insights/cleanup` section (html widget), after Sources |
| `app/admin/sections.php` | requires the console; the two permission rows (`DataCleanup::PERMS`) |
| `app/ui/faq.md` | two staff entries (how to clean up; will it change a person's work) |

Every item is `act` (the rule's action applies), `check` (a person entered or decided something: never changed, a "Check
these" step) or `left` (looked at, left as it is). A listing counts under one reason, the first it fails: name, floor,
sources; then OSM values, one-site Closed, duplicates.

| Rule | Store | Finds | Action (step 2) |
|---|---|---|---|
| `junk_name` | listings | `junkName()` true | quarantine |
| `no_floor` | listings | no street address (a number first) and no phone | `help_find` when it has a town (city or ZIP), else `sighting` |
| `page_only` | listings | phone and address rest only on cite-only sources (`SourceIndex::useRule` = cite) | sighting |
| `osm_only` | listings | a phone, address, website, hours or point whose only sources are openstreetmap.org (identity facts) | unconfirm |
| `closed_one_site` | listings | `closed` meta with `by` 0 (a machine); its own website saying so is `left` (R12 allows Closed) | caution + Still open? |
| `duplicate` | listings | same phone (groups of 2–4) + `LiveDupes::same()`; a pair asked about before is `left` | merge offer |
| `staged_junk` | staged | `crawler_raw_index` status staged, junk name | status `quarantined` |
| `store_junk` | place store | `source_facts.held` null, junk name | `held = 'junk'` |
| `store_guard` | place store | held null and `SourceIndex::guard` would hold it (protected / nation / person) | `held` = that |
| `store_cite` | place store | held null, host's rule cite only | `held = 'cite'` |
| `protected_open` | landmarks | shown or approved, `Landmarks::isProtected(name + wd_desc, nation)` | story_only (hold until the level exists) |
| `nation_sensitive` | landmarks | on a nation's land, `fclass` in `NATION_SENSITIVE_CLASSES`, shown | hold |
| `wiki_text` | landmarks | `extract` kept while `Landmarks::wikiAllowed()` is false | clear the text, keep title and link |

**Who decided it (R4):** a listing is `check` when claimed (`owner_user_id` or status not unclaimed), approved by a
person in the intake (`crawler_raw_index.reviewed_by_user_id`), `updated_by_type` a person, or any of its metadata locked
or set by admin/owner/user. Field rules (`osm_only`) are `check` only when that field is a person's, or claimed. A
landmark `approved` or with `reviewed_by` is `check`. Only crawler-made (`created_by_type = 'ai_crawler'`), public,
unsuppressed listings are read; `closed` set by a person (`by` > 0) is never a find.

**Tested here** (MariaDB 10.11, the live libraries from the mirror, a fixture of every case): each rule finds its case,
person-decided ones land in Check these, the section renders (allowed, after a preview, denied: greyed with the reason),
no sideways scroll at 390px. The junk-name check against the 2026-11-13 junk names (all caught) and real names (A&W,
Contact Lens Center, Box Canyon Outfitters, Call Ahead Pizza, Drop Zone…: none caught). Not tested: the live data's
size and time (reads up to 6,000 per store; Preview one rule at a time if every rule is slow).

## Step 2: act, ledger, Undo (`data-cleanup-act_20261114.zip`, built on Sync 2026-11-14 23:28 UTC)

**State:** live as release #50 (2026-11-14), merged on Jason's "merge it" (PR #681); its migration ran at Publish. Sync
23:59 UTC: all 10 files match the live mirror byte for byte, the migration deleted; delivery verified, claims released.
Next: Jason runs passes one rule at a time (junk names first), then the rest; the daily pass stays off until he switches it
on (and automation is on). Later: the "Help us find this business" card (0164 §3.5) takes the `help_find` targets, and
landmarks' story-only level (P5-3) takes the `protected_open` ones.

| File | What |
|---|---|
| `app/lib/DataCleanup.php` | `collect()` (full lists), `run()` (a press: up to `BATCH` 600, Continue resumes the open run), `act()` per rule, `get()`/`put()` (the one place that reads and writes each stored value), `undo()` (per record or run; a value changed since is left, "Changed since"), `wikiCounts()`/`canWrite()`/`writeNow()`, `pulseItems()`/`pulseSteps()`/`pulseDo()`, the `auto` setting |
| `app/admin/cleanup-console.php` | a card per rule; Preview / Run a pass / Run this rule / Continue; Check these (`?checks=<rule>`); Wikipedia replacements with Write 10 now; Passes with Undo; the daily pass switch |
| `app/migrations/2026-11-14_data_cleanup.sql` | `cleanup_runs`, `cleanup_ledger`, `cleanup_checks`, `cleanup_settings` (create only) |
| `app/scripts/data_cleanup_tick.php` | the daily pass: in `Scheduler::JOBS` (not `AUTO`) and `Automation::JOBS` (W1), and the page's switch, shipped off |
| `app/lib/Pulse.php` | the `cleanup:checks` case: items, steps (`case 'cleanup'`), manage (`cleanup_seen:<rule>`, needs `insights.cleanup`) |
| `app/lib/ListingConfidence.php` | `compute`: the `unconfirmed` note lowers it and says which values; a cleanup `closed_signal` reads "Still open? One site said it closed (…)" |
| `UniversalSearch::peekOutdoors`, `ContentTags::card` | Wikipedia's words never shown while its rule is cite only (`Landmarks::about`) |

What each action writes (all logged with the old value): listings `meta:removed` {at, by, reason, cleanup, target} then
`is_suppressed` 1; `meta:unconfirmed` {fields}; `meta:closed_signal` {line, at, from: cleanup} and `meta:closed` removed;
duplicates a `same` suggestion (device `crawler-cleanup`, the crawler's own checks), Undo dismisses it while still new;
staged `review_note` then `status` quarantined; place store `held` (junk, cite, protected, nation, person); landmarks
`review_note`, `reviewed_by`, `status` hidden (+ the nations' log on a nation's land); Wikipedia `about_queued_at` (first
pass) then `extract` '' (a later pass, once `research` is written).

Duplicates changed from step 1: only pairs `AutoImport::relationTo` calls `same`, `covered` or `maybe` (the 420 of the
first preview were mostly providers at one clinic, decisions/0074).

Tested here (MariaDB 10.11, the live libraries): the migration twice; a pass for every rule on a fixture of each case,
then the second preview finds only what's left; Undo per record and per run, a field a person changed after the pass
left as it is; the merge offer made and withdrawn; the Pulse case's item, steps and Looked at them; the page in every
state (preview, pass, write gated by AI use, daily pass on, denied with the reason), no sideways scroll at 390px. Not
tested: the live data's size (600 a press), `SearchIndex` and `ListingConfidence` refresh against the live tables (both
wrapped so a failure only logs).

## "Help us find this business" (`help-find_20261115.zip`, built on Sync 2026-11-14 23:59 UTC)

**State:** live as release #51 (2026-11-15), merged on Jason's "merge it" (PR #683). Sync 00:21 UTC: all 9 files match
the live mirror byte for byte; delivery verified, claims released. Jason to press "Undo all" for the drop boxes' reason
under release #50's pass.

- `app/lib/HelpFind.php`: a card is a listing hidden by Data cleanup with target `help_find` (and still unclaimed).
  `inPlace()` (the town page), `near()` (search), `shown()` (the name rules: junk, `looksPerson` R6, `isProtected` R7,
  confidential; retired after `RETIRE_DAYS` 180 with no suggestion about it), `promoteSome()` (a phone of 10 digits or an
  address starting with a number: un-suppressed, `removed` deleted as the editor's Put back does, a `found` note, reindex,
  confidence; called on every card list), `counts()`, `block()` / `card()`.
- Town page: `HelpFind::block()` at the end of the Local block. Search: `results['help']`, drawn by `helpHtml()` after
  "Named on pages we read". Answers: `TvSuggest.open({kind: 'fix', entity_id, help_find: 1})`, a form with phone, street
  address, website and How do you know? (`fields.source`). Claim: `Claims::listing` accepts a card.
- Drop and collection boxes are no longer junk (Jason, 2026-11-15: "drop boxes are good to know"); a PO Box as a name is; a PO Box address is kept as a mailing address (not a street address for the floor). Undo by reason: `DataCleanup::groups()` / `undoGroup()`, "Undo all N" under each pass.
  The ones release #50's passes took off are put back with Undo per record. Open question to Jason: drop boxes as their
  own kind of place (Mail & package drop-off, no Claim, out of business browse lists) or ordinary listings.

Tested here (MariaDB, the live libraries): a card shown on its town and found by its kind in search, not for other words;
a person's name, a protected name and a 180-day-old card held back; a phone accepted makes it a listing again and it
leaves the cards; the card at 390px. Not tested: the town page and search panel live (the zip check's code checks only).

## Reused or restricted, never a dead end (`data-cleanup-reuse_20261115.zip`, built on Sync 2026-11-15 00:21 UTC)

Jason: "They still exist, just hidden: this is a dead end. Either they are reused or moved into restricted"; the table
below approved 2026-11-15 ("table is right"); drop boxes: "Yes their own listing and map pin".

**State:** live as release #52 (2026-11-15), merged on Jason's "merge it" (PR #685); its migration ran. Sync 01:20 UTC:
all 4 files match byte for byte; delivery verified, claims released. Next for Jason: run "Taken off by an earlier pass"
(release #50's records) and "Mail & package drop-offs".

**The "left" breakdown** (`data-cleanup-left_20261115.zip`, built on Sync 2026-11-15 01:20 UTC; Jason: "yes, add the
left breakdown" after a pass said "left 3,272"): `run()` keeps `left_by` (reason => n, `DataCleanup::leftWhy()`), and
Passes shows "Why N were left as they are". Old passes have the total only. Live as release #53 (PR #686); Sync 01:39
UTC: all 3 files match; delivery verified, claims released. The 3,087 of his passes are landmarks waiting for their own
description (the writer is paused with automation).

## The rest, in one release for Jason's review (`data-cleanup-dropoffs_20261115.zip`, built on Sync 2026-11-15 01:39 UTC)

Jason, 2026-11-15: "for the rest of the build make one release so i can check it all at once"; he chose all three
follow-ups. `"review": true`: staging, UI, UX and Functional each need Approve before Publish. **State:** merged on
Jason's "Merge it" (PR #687); **release #54, on staging since 2026-11-15 02:00 UTC, waiting for Jason's UI, UX and
Functional approvals** (the email and Pulse case went out). Nothing else is waiting. To finish, once a "Release #54: Live"
commit lands on `main`: list `sync.yml` runs on traverse-3way-sync (start one only if none is queued or running), wait for
its "Sync: …" commit, `cmp` the 11 files in the table below against the mirror, run
`tools/page-audit/deliveries.py verify --mirror <checkout>` (it releases the claims), and set this line to live. His
"needs changes" notes, if any, come back in `deliveries/releases/2026-11-14-54-…json`: fix them as a new release.

| Part | Files |
|---|---|
| Drop-offs not businesses: out of business browse lists, no Claim (Suggest an edit instead) | `PlaceScope::NOT_A_BUSINESS`, `businessOnly()`, `notABusiness()`; `Claims::start`; `website/listing/view.php`; `website/claim.php`; `website/api/search_listings.php` (only when browsing: no words, not the map, no kind chosen, no ids); `Guides.php` (essentials, a town's sections) |
| The crawler's gate on cleanup's check | `AutoImport::evaluate`: `DataCleanup::junkName()` also fails `complete`, its reason in the label |
| Pins | `DataCleanup::dropPins()`/`placeDrops()` + the page's "Drop-offs without a map pin" (Census by street address, 25 a press); `OpenPlaces` kind `dropoff` (amenity post_box, parcel_locker; unnamed named from operator; words "drop box", "drop off", "package locker", "mail a package"; not rentals, Dropbox, kids, donations) |

Changed from what Jason was offered: pins aren't copied from OpenStreetMap (decisions/0065 keeps OSM its own layer);
our point comes from the Census by street address, and OSM's boxes show in search as their own credited layer. Not
changed: a town's and the place funnel's counts (`Guides::children`, `api/place.php`) still count drop-offs in their
group. Tested here (MariaDB, the live libraries): browse exclusion, the claim reason, the gate's verdicts ("Contact Us:",
a webinar title held; "Kayenta Market", "FedEx Drop Box" not held for their names), the kinds' words, the pin counts and
press, the page at 390px. Not tested: the listing and claim pages rendered (lint and the zip check only); the Census
call (no network to it here).

| Taken off by a pass | Goes to (marker: the listing's `removed` note `target`) |
|---|---|
| no address or phone, with a town | `help_find`: the "Help us find" card |
| a name only, no town; resting only on cite-only pages | `sighting` + `row`: a staged `crawler_raw_index` row |
| page-text name WITH a phone or street address | `name_unknown` + `row`: a sighting with name '' (the page name kept in `cleanup.page_name`) |
| page-text name with nothing else | `restricted` |
| staged junk; place-store junk; cite-only facts; protected and nation-sensitive landmarks | restricted (by rule, as before: quarantined, `held`, Hidden by us) |
| drop and collection boxes (`DataCleanup::dropName()`) | put back, category `mail-package-drop-off` (rule `drop_box`; pin = the listing's point) |

| File | What |
|---|---|
| `app/lib/DataCleanup.php` | `route()`, `dropName()`/`dropCategory()`, rules `drop_box` and `taken_off` (store `off`, `findOff()`), `toSighting()` (row + ledger field `sighting`; Undo turns a still-staged row down), `nextTry()` (`LADDER`), `retryTick()`/`lookFor()` (the place store, keepable hosts only, no fetch), `settle()` (Review's merge/approve/turn-down), `restricted()`/`restrictedCounts()`/`canRequest()`/`putBack()`/`request()`, `sightingCounts()`; staged junk skips cleanup's own sightings |
| `app/admin/cleanup-console.php` | Back to sightings (counts, Look for answers now, Open Review); Restricted by reason with Put back / Make it a request (greyed with the reason for landmarks and guard holds); `settle()` on view |
| `app/scripts/data_cleanup_tick.php` | settle + retry before the daily pass (still off, under the master switch) |
| `app/migrations/2026-11-15_drop_off_category.sql` | the leaf under the Post Office's group (INSERT … WHERE NOT EXISTS; no UPDATE, no `data_reason`) |
| `app/ui/faq.md` | new: "What happens to the records Data cleanup takes off?", "What is a Mail & package drop-off?"; two answers updated |

Sighting payload: `cleanup` {entity_id, fact_id, run, kind, rule, page_name, reread, at}, `retry` {fails, next_at, last_at,
answer}, a `cleanup` flag for Review, `match.exact` = the hidden listing. Settled from Review: merged/approved into it →
put back (`found` note) only with a real name and a phone or street address, else Restricted saying why; approved as
another listing → target `found`, `as`; turned down by a person → Restricted ("Turned down in Review"), listed with Put back.

Tested here (MariaDB 10.11, the live libraries, a fixture of each case): the migration twice (one leaf, right group, not
under "Email marketing"); every route; a second preview finds nothing; the ladder steps; answers found by phone (a real
name) and by a single-phone name; the three Review outcomes; Restricted, Put back, Make it a request for a listing, a
staged find and a place-store fact; Undo of a pass (a record requested since is left); the page allowed and denied
(30 buttons greyed with the reason), no sideways scroll at 390px. Not tested: the live data's size; Review's own screens
with an empty-name row (Approve needs a name, which is the point).

**Follow-ups, not in release #52 (all three are in #54, below the next heading's table):** drop-offs without Claim and out of business browse lists (Jason's "own kind" in
the open question: needs `Claims` and the directory; the category alone is here); a drop box with no point gets its pin
only when it has one (no geocoding spent); the crawler re-reading `cleanup.reread` pages is the crawler revamp's to wire
(automation paused); `AutoImport::evaluate` should use `junkName()` and `dropName()`.

## Not done, on purpose

- `workers/crawler/` untouched (a worker deploy would restore the paused Railway schedule).
- `AutoImport::evaluate` calls `DataCleanup::junkName()` since #54; `dropName()` there (taking a box as a drop-off at
  intake) is left to the crawler revamp (handoffs/crawler-revamp.md §7.3).
- Landmarks' story-only level (P5-3) is its own change; the cleanup holds those places (Restricted) with that target meanwhile.
