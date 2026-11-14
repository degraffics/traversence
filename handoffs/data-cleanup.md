# Hand-off: the data cleanup tool (decisions/0163 §8 stage 1b, 0164 §6.1b)

Session `session_01CXLi5ikaAYTSrHS6AnPpWB`, 2026-11-14, from Jason's "start" through the session manager: stage 1b first,
"a tool clean up all the bad data recently added". Built as releases Jason approves in chat, one at a time:

1. **Find and preview** (`data-cleanup_20261114.zip`, built on Sync 2026-11-14 22:08 UTC). Nothing is written.
2. **Act, ledger and Undo** (next): the actions by rule, the ledger, Undo per item and per run, the Pulse case, the pass
   report, the schedule hook (off).

**State:** step 1 sent to the inbox and in a PR into `main`, **waiting for Jason's OK to merge**. Claims held by this
session until it's verified live: `app/lib/DataCleanup.php`, `app/admin/cleanup-console.php`, `app/admin/manifests.php`,
`app/admin/sections.php` (all add). `faq.md` is shared add-only (not claimed).

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

## Step 2: act, ledger, Undo (the design to build next)

- **Migration** `app/migrations/2026-11-1x_data_cleanup.sql` (CREATE TABLE IF NOT EXISTS, safe twice; tables in the main
  database beside `entities`): `cleanup_runs` (id, rule or 'all', by, by_name, started_at, finished_at, tz, counts_json,
  report_json, undone_at, undone_by) and `cleanup_ledger` (id, run_id, store, ref, field, old_value, new_value, rule,
  action, target, by, at, tz, undone_at, undone_by). No `UPDATE`/`DELETE` in the migration (no `data_reason` needed).
- **Actions, never delete**, each writing a ledger row with the old value first:
  - listing quarantine / sighting / help_find: `IntakeStager::setSuppressed` + `removed` meta `{at, by, reason, cleanup:
    run, target}` (the editor's Put back already reads it) + `SearchIndex::indexListings`. Undo: unsuppress, restore the
    old `removed` meta (or delete it), reindex. When the "Help us find" card is built, those with target `help_find`
    move to it.
  - unconfirm (OSM only): a metadata key `unconfirmed` `{field: why}` and the listing's confidence refreshed; the listing
    page's "How we know" shows "Not yet confirmed" for those fields (a small change to the card; claim it then).
    Re-source first: if another keepable source holds the same value, add it and nothing is marked.
  - caution: `closed` → `closed_signal` `{line, url, at, from: 'cleanup'}` (Refresh already scores it −10 and shows
    "Possibly closed"); the `closed` meta's old JSON in the ledger; `SuggestionActions::reindex`. "Still open?" itself is
    W2's (P5-2); until then the signal shows.
  - merge: `Suggestions::create(['kind' => 'same', …], null, 'cleanup-dupes')` as LiveDupes does; Undo withdraws a
    suggestion still `new`.
  - staged: status `quarantined`, `review_note` prefixed "Data cleanup: …"; Undo back to `staged`.
  - place store: `held` set; Undo back to null.
  - landmarks: status `hidden`, `review_note` "Data cleanup: story only planned (R7)" (hidden survives the monthly
    reload; `waiting` doesn't), `reviewed_by` the person who ran it; nation's land written to the nations' log as
    `Landmarks::review` does. `wiki_text`: `extract` cleared, old text in the ledger.
- **Undo** per ledger row and per run (`insights.cleanup_undo`): refused, with the reason, when the record changed since
  (its current value isn't the ledger's new value): a person's later edit wins.
- **Pulse:** one case, "Data cleanup needs a person", a step per kind (Check these by rule; duplicates to Review), each
  a `['do', …]` button (open the record in a drawer, Mark looked at, Undo); the pass report as the case's note:
  restored, re-sourced, quarantined, left. Read `Pulse.php` (`manage`, how a case's steps are built) before writing.
- **Runs:** a pass per rule or all, by button (`TvAsk.busy` while it works), batched (500 a request, the page continues
  the run until done). The schedule hook: a `Scheduler::JOBS` entry `data_cleanup_tick` listed in `Automation::JOBS`
  under the master switch, and shipped off (a `cleanup_auto` switch, default off, on the section).
- **Before step 2:** read `Pulse.php`, `Scheduler.php` (`JOBS`, `AUTO`), `Automation.php` (`JOBS`), `SuggestionActions`
  (`put`, `reindex`), `Suggestions::create`; start every file from the live mirror again.

## Not done, on purpose

- `workers/crawler/` untouched (a worker deploy would restore the paused Railway schedule).
- `AutoImport::evaluate`'s own name check isn't widened here (handoffs/crawler-revamp.md §7.3): with automation paused no
  new junk arrives; when that work is done it should call `DataCleanup::junkName()` so the gate and the cleanup agree.
- Landmarks' story-only level (P5-3) is its own change; the cleanup holds those places with that target meanwhile.
