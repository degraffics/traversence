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
