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

## Not done, on purpose

- `workers/crawler/` untouched (a worker deploy would restore the paused Railway schedule).
- `AutoImport::evaluate`'s own name check isn't widened here (handoffs/crawler-revamp.md §7.3): with automation paused no
  new junk arrives; when that work is done it should call `DataCleanup::junkName()` so the gate and the cleanup agree.
- Landmarks' story-only level (P5-3) is its own change; the cleanup holds those places with that target meanwhile.
