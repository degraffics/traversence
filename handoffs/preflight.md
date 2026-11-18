# Pre-flight (decisions/0163 §10's note of 2026-11-16; queue item 2 in handoffs/session-manager.md)

Session: session_01CXLi5kGG8gG4QBmC4uzMzN, started 2026-11-17 on Jason's go ("yes start the preflight session now").
Check-out release names: `preflight` (release 1), `outside-table` (release 2, not built yet).

## State

- **Release 1, `preflight.zip`:** built on Sync: 2026-11-17 14:05 UTC, zip check clean, sent to `releases/inbox/`
  (PR open, merged once Jason approves). Files: new `app/lib/Preflight.php`, `app/admin/preflight-console.php`,
  `app/scripts/preflight_tick.php`, `website/js/admin-preflight.js`; changed `app/lib/Operations.php` (the Pulse case's
  three hooks), `app/lib/SystemStart.php` (panel: the pre-flight's result first), `app/lib/Scheduler.php`
  (`preflight_tick`, 15 min, in AUTO), `app/lib/Thresholds.php` (`pf_ms_radius`, `pf_ms_rollup`, `pf_ms_graph`,
  `pf_ms_index`), `app/admin/manifests.php` (config → `preflight`), `app/admin/sections.php` (requires the console;
  Outside services links to it), `app/ui/app-shell.php` (`tv_icon` 'x' and 'dash'), `website/admin/api.php`
  (`/admin/api/config/preflight`: run, fix, done), `website/api/crawl/index.php` (the check-in answers `"test": true`
  once when asked). No migration (results moved to `preflight_results` in preflight-slow).
- **The worker, in the same PR:** `workers/crawler/indexer.py` 1.1.0 sends the full-size test batch when its check-in
  says `test` (W0 stays asleep); tests in `workers/crawler/tests/test_indexer.py`. Merging redeploys `w0-indexer` on
  Railway (it watches `/workers/crawler/**`). Until it runs 1.1.0, the pre-flight's batch line says so (red).
- **Fix, `preflight-fix.zip`** (Jason, 2026-11-17: "keep lines compact"; "a green check mark or red x is enough"): one row per line, no notice after a test (only when a request fails, in the line's body). Built on Sync 14:22 UTC; js and the console only.
- **`preflight-slow.zip`** (Jason, 2026-11-17), built on Sync 14:28 UTC: results moved to their own table
  `preflight_results` (made on first use; staging's own), because live's `search_index_state.v` cut long values short
  (the header showed "78 not run yet"); slow checks (over `pf_slow_ms`, 5 s) in amber with "Show the slow ones"; each
  line's last 10 times; a full run's start, end and length; **Report (text)**: `GET /admin/api/config/preflight?action=report`.
  Lesson: `search_index_state.v` is short on live; keep anything longer than a few hundred characters out of it.
- **`preflight-tune.zip`** (2026-11-17, from Jason's first report: 58 ready, 24 not), built on Sync 14:36 UTC: Railway
  test reads the project (a team token can't ask `me`), OpenStreetMap asks each free server (6 s each), Fly sends a
  macaroon as FlyV1, Supabase's empty index is expected before switch-on, jobs shipped off get their line from Start the
  system, openrouteservice's 5xx is theirs. Real items found (for Jason): HF_TOKEN missing on Railway's `traversence`
  service; azliquor.gov's Cloudflare challenge; GITHUB_SYNC_TOKEN expires Dec 16, 2026; the switch-on check holds W1, W2,
  W3, W11, W12 for BRAVE_API_KEY though Tavily is the search (Operations::switchOnCheck; Jason's call); Domains::fly sends
  Bearer, so a macaroon FLY_API_TOKEN fails there too (domains session); 36 ungated outside calls (StagingGate::scan).
- **Release 2, `outside-table.zip`** (built on Sync 14:43 UTC, sent 2026-11-17): Outside services as a table: row per
  company (name, use, ✓/✗/— from its pre-flight line, this month's cost, last checked), search (names, uses, key names),
  one filter (status or kind, `OutsideServices::KINDS`/`KIND_NAMES`), sort (status default, name, cost), the card opens
  in place (`js/admin-outside.js`, kept in localStorage `os:table`), Test on each card (pre-flight `svc:<key>`); the
  pre-flight's Brave line checks BRAVE_API_KEY (Jason: Brave and Tavily both live and ready).
- **`preflight-refresh.zip`** (#95): Run all resets every line to queued, counts from 0, "Testing N of 87: …"; the Arizona
  licence lists aren't called (— "Inactive: not released yet (licence lists, queue 3a)", Jason via the session manager).
- **`preflight-fix2.zip`** (#96, from Jason's second report): Railway key names read from Railway's API (values never
  kept; falls back to the crawler's report); OpenStreetMap a one-node query to each free server; openrouteservice at
  `api.heigit.org/openrouteservice` (a two-point matrix, as TravelLegs); **a Start the system bug**: `turnOn('t:recheck')`
  refused "No such capture task" until the recheck had run once, now `ListingIdentity::ensureTask` first.
- **The Fly edge is set up** (Jason, 2026-11-17, with this session): `trvgo-edge` moved from his personal Fly account to
  the shared `traversence` organization (`fly apps move`), a fresh `STOREFRONT_EDGE_KEY` set on it, a deploy token for
  the site; `.env` has `DOMAINS_MODE=test`, `FLY_APP=trvgo-edge`, `FLY_IPV4=66.241.125.213` (shared),
  `FLY_IPV6=2a09:8280:1::1de:4a7c:0` (dedicated). The pre-flight's Fly line is ✓ (0 certificates). Lesson: `.env` keeps
  the first line for a name; **`preflight-envdup.zip`** adds a check for names set twice (needed for launch) and corrects
  Fly's hint (a deploy token for the app does read its certificates).
- **Left (Jason's, 2026-11-17):** OpenStreetMap's Overpass servers don't answer our host (one held by its ban issue, three
  time out or 504): ask again before Start, or mark Overpass optional for launch (his call). GITHUB_SYNC_TOKEN expires
  Dec 16, 2026. 36 ungated outside calls are in the session manager's cleanup stage.

- **`preflight-log.zip`** (#99): the page opens fresh; the run log at its foot (`preflight_runs`, the last 50, staging's
  own), each run with its report as it stood (`/admin/api/config/preflight?action=report&run=<id>`).
- **decisions/0163's note "the source map, ours first, a backup for every need"** (Jason approved, 2026-11-17; PR #840):
  every need answered ours first, then outside free before paid, a paid backup only by a director's override inside its
  own cap, then a planned fallback; "no backup" said plainly; the planned backups for those are queue item 9.
- **(a) `preflight-needs.zip`** (PR #843, sent, built on Sync 16:27 UTC): `Preflight::NEEDS` (21 engine needs, needed for
  launch; 22 site needs, shown): each need's sources in order (`['ours', table, words, stale days]`, `['svc', company]`,
  `['line', pre-flight line, words]`), `ours()` checks a copy's rows and freshness (its date column found in
  information_schema), `need()`: ✓ first source, amber `warn` "on a backup" (or a soft need whose fallback always works),
  ✗ none. Workflows are judged by their needs; services are needed for launch only for CORE (Bluehost, Railway, GitHub).
- **Next, (b) W1 reads `open_places` before Overpass** (for a fresh session if this one is full): the crawler's coverage
  task ("Find places by kind and town", `crawler.py` around `overpass(job, deadline) + wikidata(...)`, ~line 3231) and
  targets (`targets()`, ~line 2030) ask the site first for what `open_places` holds for the town box and kind (a new
  read-only endpoint, or `api/crawl/loads.php`), and call Overpass only for a kind or town our copy lacks; the switch-on
  check's line "OpenStreetMap: at least one server not held" (`Operations::switchOnCheck`, scope `osm`) then shouldn't
  hold W1 while `open_places` is loaded and fresh. Then (c): the paid-backup overrides (a director's switch per need, a
  cap in Thresholds, logged; shown on the need's line) and the other workflows' fallbacks in the map's order.

- **(b) `osm-ours.zip`** (session_01CXLi5kkqbbZZGMKEB1cFnC, built on Sync 16:33 UTC, sent 2026-11-17; Jason's OK pending):
  W1 reads our OpenStreetMap copy before Overpass. Files: `app/lib/OpenPlaces.php` (leadKinds: TownCrawl::KINDS' filters
  kept from each Geofabrik extract as their own kind `f:<hash>`; filterKind, copyKinds/copySignature, copyStates/ourCopy,
  forJob, attach), `app/lib/crawler/OpenLoads.php` (finish stores the kinds' signature per state,
  `open:geofabrik_sig:<state>`; geofabrikDue: a state loaded before today's kinds is due at once), `website/api/crawl/jobs.php`
  and `targets.php` (each leased job carries `ours` {filter: elements} and `ours_note`; no new endpoint, so no new calls
  through Bluehost's 409 check), `app/lib/Operations.php` (switch-on line "OpenStreetMap: our copy loaded, or a server not
  held": green on a fresh copy, a free server, or Geofabrik's test download passed, since t:osm runs only after W1 is on),
  `app/lib/Preflight.php` (source type `['copy', 'osm', …]`, first for the "places" need), `app/ui/faq.md`. Crawler 2.9.0
  (`workers/crawler/crawler.py`): `overpass()` reads `ours` first, Overpass only for the gaps; coverage and targets no
  longer stop at the door when every server is held: covered jobs are worked (partial when gaps wait), uncovered ones
  go back with their lease, the task reports held only when nothing could be worked; the run report adds `osm_ours`.
  Tests: `workers/crawler/tests/test_ours.py`; the site part was tested on SQLite (keepExtract, forJob, stale signature).
  **Not from our copy (Overpass only):** filters on `healthcare`, `office`, `social_facility` (not in the extract), the
  support groups' (worker's GROUP_QUERIES) and situation gaps' (Learning::OSM, private) filters, towns outside AZ and NM.
  Our copy is "fresh" for 45 days (OpenPlaces::COPY_DAYS). Places deleted in OpenStreetMap stay in open_places (keep()
  only replaces); a cleanup per load is a follow-up. The site's own nearby search (OpenPlaces::near) still asks Overpass
  for a stale spot; it could skip the ask in a loaded state (follow-up).
- **(c1) `paid-overrides.zip`** (Jason: "go on c", 2026-11-17; rebuilt on Sync 16:59 UTC after #102 changed Operations.php): `app/lib/PaidOverride.php` (new:
  NEEDS confirm → Google Places, prose → Claude; state, usable, runAllowance (at most 3 a run), recordRun → month count in
  `po:confirm:*` and the cost ledger as google_places W2 identity); Thresholds gates `po_places_confirm` (checks a month)
  and `po_claude_prose` (dollars a month), 0 = off, on the Service register (Operations::CAPS); CrawlService plan sends
  `paid_backups`, report reads `paid_used`; PageRead: on Gemini's failure, Claude by override, charged `po:prose`;
  IdentityJobs accepts checked `google`; Preflight source `['paid', need]` last on confirm and prose. Crawler 2.10.0:
  `search()` Tavily → Brave (held or failed), `SEARCH_SWITCHES` in the report; `google_confirm()` in identity_jobs only when
  nothing free found it (its Maps link as `social`/`evidence`, OPERATIONAL only). Tests: `workers/crawler/tests/test_paid.py`.
  The second look takes a Google match too (Jason, 2026-11-17: "google match is a good thing"): Verify::KINDS `google`, a
  reference that raises a new find's confidence (about +10 × its record); AutoImport::isGoogleHost keeps Google out of the
  keepable hosts, so it never passes the "real business" floor by itself.
- **(c2) `refresh-archive.zip`**, one release for all of (c2) (Jason, 2026-11-17: "do the full build so I only approve once";
  built on Sync 17:05 UTC; crawler 2.12.0). W2 re-read: Common Crawl's copy (`cc_home`) when a website doesn't load;
  `Refresh::siteResult` sends it to Review instead of taking it off when the copy is within 90 days; an empty description
  only, dated. W3 sources: Common Crawl's copy before Tavily Extract (`read_source`; CC_COPY_MAX 12 a run). W2 missing
  phone: `IdentityJobs::local` (npi_orgs by street number and ZIP, rec_places by name and ZIP) sent as the job's `local`,
  merged first by `identity_jobs`. W2 map points: `api/crawl/geocode.php` answers `waits` when the Census fails; the worker
  raises ServiceHeld. W2 still open: `api/crawl/places.php` orders closed_signal first. W1 official places: a Wikidata
  failure marks the job partial. W8: `DeepLook::fromOurCopy` (open_places) before Overpass. Site email: `Mailer::send` →
  `smtp()` then `viaSes()` (SesMail, personal account, only when SES is live); a failed QUIT after 250 isn't a failure.
  Weather: `website/hub/index.php` `noWx()` shows the NWS link. W10: `LiveEvents::tick` retries a failed check next day.
  Tests: `workers/crawler/tests/test_refresh_archive.py`, `test_fallbacks.py`; the site parts with stubs (scratch only).
  **Not built:** stock photos' order (decisions/0143 interleaves on purpose: Jason's call and a note on 0143), menus (no
  reader exists), W6 heritage's Wikimedia and NPS steps, Find it's Tavily → Brave step (the worker's `find_name` request
  could serve it), AZCC and NM SOS readers, W14, file storage's retry and old-bucket read.

- **(c2, large builds)** (Jason, 2026-11-17: "Large Builds: Go ahead and start this"; release `large-fallbacks`, claims held):
  live as **#106** (Sync 18:09 UTC, all four files verified); sent as `large-fallbacks.zip` after #851 went live as #104; rebuilt on Sync 17:57 UTC after #105 (job-links) changed
  faq.md: `app/lib/GuideLinks.php`, `app/lib/Scheduler.php`, `app/ui/faq.md`, and `app/lib/Operations.php` (W1's real
  blocker in Jason's 17:56 report: Issue #1, overpass-api.de's 406, held the whole OpenStreetMap service and so W1;
  `Operations::status` now asks `serverScoped()`: a hold on one server stays on it, and holds the service only when no
  other server is free and no other source (`osmOtherSource()`: our copy, Geofabrik, the places need's backup) answers.
  The switch-on OSM line uses the same helper).
  - **W8 Find it, Tavily → Brave** (`GuideLinks::findTick`, new `webStep`): after our crawler's map lookup finds nothing,
    one `find_name` request to the crawler (one web search, Tavily then Brave since 2.10.0, then its own website); found
    and staged → the find is `found`, its name links once listed; nothing, failed or a day waiting → Claude as before.
  - **File storage** (`Scheduler::AUTO` + `files_tick`): an upload the store didn't take waits on the server and
    `Store::moveOld` (step 2, files in their owner's folder not yet in the store) retries it every 10 minutes; `files_tick`
    is now set up by the scheduler itself. The old Amazon bucket as a read fallback isn't built: the files were copied to
    R2 (decisions/0151) and the old bucket's name is no longer in `.env`; worth it only if something turns up missing.
  - **Not started, blocked:** the AZ Corporation Commission reader (`arizonabusinesscenter.azcc.gov` answers 403 "Access
    Blocked" to us, Azure's firewall), the NM Secretary of State reader (refused at the session's proxy), and W14, which
    stages them. 0164 says each source's terms are read first and a blocked site is never worked around: Jason to ask AZCC
    for access (an API, a bulk file, or our host allowed) and read both sites' terms; then build.
  - **Not started, needs a decision:** W6 heritage's Wikimedia Commons and National Park Service steps. `completeLoc`
    accepts only loc.gov URLs and `heritage_sources.kind` is `loc`/`news` (its migration isn't in the sync's `deleted/`,
    so check the live column first); Commons' licences vary (CC BY-SA needs credit and share-alike on what we show), so
    each source's use needs Jason's call (decisions/0164: the licence decides the use) before code.
  - Passed to the session manager (Jason): stock photos' order (0143 interleaves on purpose) and a menu reader (none
    exists; Document AI → Textract → Claude).

- **Lesson (2026-11-17, #106):** a zip rebuilt and re-sent under the **same name** stalls at "Plan approved" ("taken from
  the inbox (it was already in)"); Jason had to stage and publish it by hand. Until the site's inbox handles it, give a
  rebuilt zip a new name (`large-fallbacks-2.zip`). Queued in the session manager's cleanup.
- **State at 18:10 UTC:** (b), (c1) and (c2) are live (#101, #103, #104, #106). Waiting on Jason: the Arizona and New
  Mexico registry requests (decisions/0164 §7.1's note), heritage's Commons licence call, his OK on that note, and his
  pre-flight run before Start the system.

- **Live as #107** (Sync 18:36 UTC, all files verified; the `-3` rename never merged and wasn't sent). **`preflight-server-run-2.zip`** (Jason via the session manager, 2026-11-17: "Preflight only works if I stay on that
  page"; built on Sync 18:09 UTC; replaces `preflight-server-run.zip`, never taken): Run all runs on the server. **Changed
  after review (Jason):** an interrupted run is never resumed by itself: it's marked Interrupted and waits for Continue or
  Cancel (the page asks on open; Run all asks Continue / Start a new run / Cancel via `TvAsk.choose`; Pulse steps
  `ops_pf_continue`, `ops_pf_cancel`; nightly and .env runs wait); every run is one log line from its start
  (`logStart`, `logSet`: Running, Interrupted at N of M, continued, Cancelled, Finished), its report as far as it got.
  The text below describing automatic pick-ups is superseded. `Preflight::startRun` / `work` / `live` / `liveLines` /
  `interrupted` / `stopRun`, the run's state in `preflight_results` row `_live` (kept out of `results()`), one at a time by
  `GET_LOCK('tv_preflight_run')`; the API's `start` answers, releases the session, then keeps working
  (`ignore_user_abort`, `fastcgi_finish_request`); `status` sends the lines finished since `from`. A run with no progress
  for 3 minutes is picked up from where it reached (the page, Run all, or `preflight_tick`), and after 3 pick-ups logged as
  "Stopped at N of M" (`finished()` takes a note, shown in the run log). `runAll` (nightly, .env) uses the same run. The
  page opens fresh except while a run is going. The old browser loop's `next` and `again` (js) moved to `work()` (named
  with `--drops`, Jason's ask). Tested on SQLite with faked lines (full run, cut off and picked up, stopped after 3, the
  lock); the page itself needs a look on staging.
  The same release keeps long settings whole, **add-only** (a `MODIFY` fails the Releases check's "Migrations only add",
  as #70 found; 0162 has no widening exception): `2026-11-17_search_index_state_wide.sql` adds `v_long MEDIUMTEXT NULL`
  (guarded), and `SearchIndex::state` reads `COALESCE(v_long, v)` / `setState` writes both (`v` its first 200 characters).
  `search_index_state.v` was VARCHAR(200), cut silently on live: Start the system's state, W0's flagged list,
  Geofabrik's probe, identity:last_run, worker:keys, search presets and Gemini's comparison were all longer than 200. New
  pre-flight line `site:state` (needed for launch): a ~4,800-character value written and read back.

- **Jason's pre-flight, 18:29 UTC (after #107):** 110 ready, 14 on a backup, 2 not ready (both optional), none needed for
  launch: clear for Start the system. "Long settings are kept whole" OK (4,827 characters back whole). W1 on a backup:
  Places on Overture until the Geofabrik copy loads (a W1 task), Health providers on the live NPI Registry (our copy 48
  days old, over 45). Optional reds: OpenStreetMap (the 3 free Overpass servers time out) and the AZ Corporation
  Commission line, which still tests `ecorp.azcc.gov` (gone; the register moved to `arizonabusinesscenter.azcc.gov`,
  which answers us 403): follow-up, point `Preflight::test` case `azcc` at the new host and say it's blocked.

## How it works

- `Preflight::lines()`: one list in run order: the site, every `OutsideServices::COMPANIES` key (`svc:<key>`: each
  card's Test, one status), Railway and W0 (`rw:*`), the query timings (`ms:*`), the workflows in `SystemStart::ORDER`
  plus W14 (`wf:*`), the new tasks (`t:*`), then 0164's phases (`ph:*`, last, since they roll up the workflows).
  Each: [group, name, needed for launch, company, workflow]. Needed for launch: the site's lines, Railway and W0, and
  companies W0 or W1 use (plus Bluehost, Railway, GitHub).
- Each test returns [ok|bad|na|wait, the service's own answer, what was tested, fixes]. Fixes: `['do', act, label]`
  (the site fixes it: `sched` = Scheduler::sync, `dbup` = DbUpdates::autoRun), `['url', label, url]`, `['tool', label,
  admin url]`. Answers pass through `clean()` (no key=value or JSON secret, no 32+ character token).
- Calls: `http()` asks `StagingGate::hold()` first and skips a host held by an open ServiceHealth issue; a service only
  paused on Automation is still tested (read-only). Nothing charges: Stripe only with an `sk_test_` key, Square and
  Name.com only their sandboxes, Places and Tavily never called (their keys are on Railway: the crawler's key report).
- W0's batch: the line stores `w0:test_ask`; the next check-in hands it out once (`Preflight::takeTestAsk`); the worker
  posts kind `test` (never stored); the line reads the receipt in `crawl_batches` after the ask.
- `preflight_tick` (15 min): a full run when `.env`'s time or size changed, and nightly after 2 am Arizona.
- Pulse: `ops:preflight` through `Operations::pulseItems/Steps/Do`; a step per red line ("Test again", or its fix),
  outside fixes carry their URL, and a "Run it all" step.


## preflight-follow (2026-11-17, built on Sync 18:36 UTC)

The page that pressed Run all now follows the run live. Before, it waited for `start`'s answer, which on LiteSpeed came only at the end of the run, so the button spun and the counts stayed at 0. Now:
- `begin()` polls `status` after 0.8 s whatever `start` does, and an ended state from an earlier run isn't taken as this run's end (`after`, 30 s).
- The button spins only until the run shows as running.
- `api.php` start calls `litespeed_finish_request()` where it exists.
- `tv_preflight_live` sends `start`.

Files: `website/js/admin-preflight.js`, `website/admin/api.php`, `app/admin/preflight-console.php`.

## hub-gate-preflight-2 (2026-11-17, built on Sync 18:36 UTC; replaces hub-gate-preflight, never sent)

After Start, the switch-on check held the master on 20 "Opening a geo-hub" lines. There were three causes:
- A hub with no `hub_stages` row defaulted to Open.
- A hub with no state got `sources($pdo, '')`, which returns every state's sources.
- The hub lines held everything (scope 'all').

The fix (Jason, 2026-11-17; 0163 §10's note; 0170 §2):
- A hub's stage now comes from `hubs.status` when no stage row is set.
- A hub's sources are those of its own states (`states`: every state of its micro-clusters' zips). It has none when no state is known.
- The hub lines' scope is **W14**, so they hold W14 only.
- The pre-flight's `site:switchon` is red only for lines that hold everything; a part's red lines are named but don't count.

After it ships, Ancient America's line stays red for W14 alone until AZ's and NM's terms are read in Pulse → Hubs. Start's master no longer waits on it.

## switchon-group (2026-11-17, built on Sync 19:23 UTC)

Jason asked for Start's "Check before switching on" to act like the pre-flight queue.
- The switch-on check is now a pre-flight group, `switchon`, with one line per check line.
  - Ids look like `so:<slug>-<sha8>`. They stay at 40 characters or fewer, the length of `preflight_results.id`.
  - The list of lines is kept in the search state `pf:so`.
  - `soTest` reads `Operations::switchOnCheck` and caches it for 20 seconds within a request.
- A line is needed for launch when its scope is 'all'.
- `recordSwitchOn` writes the check's answers into the group whenever the Workflow board opens or Start's check step runs.
- `startRun(..., $group)` runs the group alone. Its log line carries `counts.group`, and it doesn't count as a full run (lastFull).
- The board draws `tv_admin_mod_preflight($pdo, 'switchon')`: Run the check, the filters and the run log. The JS sends `group`.
- This replaces the single `site:switchon` line from #110.

switchon-group-2 replaces switchon-group, which was never sent.
- `SystemStart::panel` draws its steps as a queue like the pre-flight's (`.st-q`), with counts and the run log. While a run is going, it fetches itself again every 15 seconds.
- The check step is 'done' when no red line holds everything.
- `Preflight::workflow` returns 'warn' ("waits for its terms") when its only red lines are its own "Opening a geo-hub" lines. A phase shows that as amber.

## start-inline (2026-11-17, built on Sync 19:33 UTC)

Start the system is now self-contained (Jason).
- `SystemStart::panel` no longer shows `Preflight::startNote`; it links to the pre-flight as diagnostics instead.
- Asks carry `[4]` (the steps they belong to) and `[5]` (their scope). Each step shows its own asks inline with their fix: a 'do' becomes the button `au_do=start_do`, a 'tool' becomes a link, and an ext becomes its `Pulse::SERVICES` link.
- Held, stopped and running steps get **Check again** (`au_do=start_tick` → `SystemStart::tick`). Both handlers are in automation-console.php.
- Workflows and tasks show `lifeLines`: Started, Running (via `self::runs`), Complete (`done_at`, stored by `set()`) and Active status (wfOff/master and its last crawler run).
- The run log shows one line per session. `start()` archives the previous session to `start:sessions` (the last 20), and each line opens to that session's report.

## start-gate-fair (2026-11-17, built on Sync 20:04 UTC)

W1 stopped on a single timeout from overpass.kumi.systems (issue #12).
- `SystemStart::gate` now fails on a new issue only when `issueHolds()`: `ServiceHealth::holding()` and `Operations::status()` give 'held' (server-scoped).
- A stop keeps `items[id]['issue']`. In `advance()`, a step stopped by an issue that no longer holds its service carries on by itself.
- A stopped step on the card shows Try it again / Leave it paused (`start_do`) and Check issue now / Mark issue resolved (`start_issue_check` / `start_issue_resolve` in automation-console.php).
- The "Now" line points to the stopped step.

## start-confirm (2026-11-18, built on Sync 00:45 UTC)

Jason asked that Start confirm everything is operational within minutes, then fire the queue.
- **The verify step:** a new `verify` step tests each workflow and task with `Preflight::run` (the pre-flight's line for it). Each look gets `$budget` seconds, and the open card looks itself every 15 seconds while verifying.
- **Red lines:** a red one is held with its answer and fix, and retested at each look.
- **The master:** waits for `verify` to finish, then every confirmed item switches on in order in one look; a task once its workflow is on.
- **Watching:** each item that's on is watched alone (`gate`, `start_gate_runs` and `start_gate_hours` are now the watch). A failure stops that one only; the global 'stopped' state is gone.
- **Stopped steps:** each says "What to do".
- **Records:** decisions/0163 §10.2 has a note, and the Thresholds wording is updated.

## start-report (2026-11-18, built on Sync 01:19 UTC)

Jason's phone screenshot showed four problems, fixed here:
1. **Report downloads.** Each run log line has Download report and Open. Both go through `GET /admin/api/config/automation?action=report&run=<session start>[&view=1]`, which calls `SystemStart::report()` and returns plain text.
2. **The check's notice is accurate.** The Automation page's "Check before switching on" notice now names what each red line holds, and claims the master is held only when an 'all' line is red.
3. **"Starting" becomes "Operational".** `SystemStart::runLabel()` shows "Starting" until the master is on and nothing is pending, then "Operational: N on (k still watched), h held, s stopped".
4. **Solid top bar on phones.** The minimised admin top bar on phones (`app-shell.php`, `tv-brand-min.tv-nav-min body.tv-back`) is now solid #3E2B1F instead of transparent.

## start-inline-fix (2026-11-18, built on Sync 01:32 UTC)

Jason's rule: "Fix it can't take me to another page."
- **Hub lines:** `SystemStart::hubTermsInline` lists each hub source whose terms aren't read, skipping blocked ones. Each gets **Read its terms**, which opens the source's own page in a new tab, and **Terms read: allowed**: `au_do=start_hub_terms` → `HubOpening::pulseDo('hub_terms:<id>')`, checked against hubs.open.
- **Other 'tool' fixes:** open in a drawer (`data-peek-frame`).
- **Held steps:** a held step whose own fixes are listed doesn't repeat them in its status line.
