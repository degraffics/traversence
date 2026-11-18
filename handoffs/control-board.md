# The System Control Board (the command center): hand-off for its build session (2026-11-18)

## What Jason asked for

Jason, 2026-11-18: "I see START as PREFLIGHT, ACTIVATION, MONITORING, REPORTING for the entire system (each step
accessible and able to see what is doing and the data its generating)." Then: "In powerplant management, the site is
under operations management with a control room where all of the plants information is monitored and controlled. The
control board is where an operator can see every sensor and status of the entire power plant operation so I want our
system to have a very similar system." And: "neither of them, especially START, does not include endpoints or a way to
see what data is being captured and or stored."

## The outline (agreed in the session manager's chat, 2026-11-18)

| Control room | Ours |
|---|---|
| **Mimic board**: the whole plant as a flow, each unit lit by its state | One diagram: sources → workers (W0 indexer, the crawler, the video worker on Railway) → the site's endpoints (`/api/crawl/*`, 21 today) → tables → Review / published / held. Each box green, amber, red or grey with its live numbers |
| **Sensors and gauges** | Every pre-flight line, service, workflow, task, endpoint and table is a gauge: its value, its limit, when it last read; click for the detail and the data behind it |
| **Controls at the board** | Start, pause or resume each unit, run a check, mark an issue resolved, in place (never another page; drawers for other tools) |
| **Interlocks** | The pre-flight and Start's confirm step: a unit switches on only when its needs are green; one that trips stops alone |
| **Startup sequence** | Start: confirm, then switch on in dependency order; the board shows each unit coming on |
| **Annunciator** | One alarm list by priority (trip, warning, notice): what happened, what to do, its button; acknowledged; clears itself when fixed; to Jason's phone and the session manager's reports, never a Pulse case for approvals |
| **Historian** | Each gauge's readings kept over time (with a size limit); a trend per unit: runs, records in, stored or refused, spend, speed |
| **Logbook** | Every switch, alarm and acknowledgement, with who and why |
| **Shift report** | Daily and weekly, for the whole plant and per unit, downloadable and phone-ready; a short summary for the morning brief |

Pre-flight and Start become parts of the board (its interlocks and startup sequence), not separate destinations; their
current pages stay reachable until the board replaces them (archived, not deleted: `archive.md`).

## The order

1. **A decision** (new ADR, numbered next after `decisions/0170`), amending 0163 §10 and folding in the pre-flight and
   Start: the board, its units and gauges, the register, the ledger, the historian, alarms, logbook, reports, where it
   lives in the admin (decisions/0109: a section from the manifests, built from widgets), access, and what it replaces.
   **Ask Jason for his OK in your own chat with the decision pop-up before any build, then tell the session manager.**
2. **Release 1: the register + the three pre-flight fixes.**
   - The register: in code, each unit (workflow, task, worker, service) names its site jobs, crawler tasks, services,
     endpoints and tables; the board, Start and the pre-flight read it, so a new workflow appears by itself.
   - Start's confirm step re-tests the source-map lines (`Preflight::NEEDS`) its workflows depend on before confirming
     them. Today `Preflight::workflow()` reads the last stored `nd:` results, so a stale or never-run need doesn't hold
     a workflow.
   - Start's confirm step runs the four query timings (`Preflight::TIMINGS`).
   - `SystemStart.php`'s header comment still describes the old one-at-a-time pacing: correct it.
3. **Release 2: the sensors and the ledger, and the board's live view.** Each `/api/crawl/*` call and each run records
   what it received, stored and refused, and the ids of the records it touched (the worker and the endpoints report
   them; new tables name their class in `app/lib/staging-tables.php`, R14). This takes in queue item "each crawler run
   opens to the records it touched" (handoffs/session-manager.md, cleanup stage).
4. **Release 3: the historian and the annunciator.**
5. **Release 4: the logbook and the shift reports.**

## Where it stands (kept current by the build session)

- **2026-11-18:** the decision is written: `decisions/0171-the-control-board.md` (proposed). It was checked against the
  live mirror at "Sync: 2026-11-18 02:36 UTC" and sent to the session manager for Jason's OK. Nothing is built.
  Release 1 starts after his answer.
- **2026-11-18, later:** Jason answered "needs changes". Revision 2 of 0171 is in the same PR (#896). It adds:
  - organization by system, then process, with each gauge holding its whole process;
  - six stages, with shut-down and maintenance new;
  - real-time monitoring of slow, retry and crash;
  - alerts three ways (system notification, Pulse, push), and the health token explained;
  - Pulse fixed: one item per alarm, closed by itself;
  - complete routing per process (fast, efficient, accurate);
  - five releases instead of four.

  Sent back to the session manager for his OK.
- **2026-11-18:** Jason's example board is saved as `design/control-board-mockup.html` ("for your reference … you
  will be the decision makers"). Decision 0171 §12 lists what Release 2 keeps from it and what it changes. A summary for
  sharing, corrected against the code, is at https://claude.ai/artifact/699FnCRCPZBR5N3HxxyW1v.
- **2026-11-18:** Jason approved 0171 revision 2. Next: Release 1 (the register and routes, plus the pre-flight fixes).
- **2026-11-18, Release 1 built** on "Sync: 2026-11-18 02:36 UTC" and sent as `control-board-1.zip`. It holds:
  - `app/lib/ControlRegister.php` (new): systems, processes, workers, the 23 worker endpoints with their tasks and
    tables, `of()`, `dependsOn()`, `route()` and `unclaimed()`;
  - `Preflight.php`: the Routes group, with one line per workflow (W0–W12, W14), one per worker and "Every endpoint
    claimed". They are optional and hold nothing. `refreshFor()` is new;
  - `SystemStart.php`: the header rewritten; the confirm step calls `refreshFor()` before each line; the four timings run
    once a session (`$st['timings']`); a failed query becomes a step on the card;
  - `Thresholds.php`: `pf_fresh_mins` (60);
  - `faq.md`: two entries.

  Repo changes: zip-check rule R15 (a worker endpoint must be in `ControlRegister::ENDPOINTS`) and
  `tools/control-board-tests/r1.php` (33 pass).

  Checked at 390px: the Pre-flight console was rendered with the route lines open, with no sideways scroll. The Start
  card only gains words on its confirm line. Nothing new to press: a route line's Test is the existing button, and it
  was run through `Preflight::run`.

  Next, after it's live: Release 2 (the ledger and the live board).

- **2026-11-18: Release 1 is live** as #120, synced at "Sync: 2026-11-18 13:57 UTC". All 5 files in the mirror match
  the zip byte for byte. **Release 2 builds on that sync.**

### Where the folded-in cleanup fixes go (build session, 2026-11-18)

Release 1 closes none of them (it was built before they arrived). The plan:

- **Release 2: the ledger, the board, start-up and shut-down, the services' gauges**
  - each crawler run opens to the records it touched (the ledger's `control_records`);
  - Start's watch counts "nothing to do" as a good run;
  - a run whose only failure is a held service ends ok, not CRASHED;
  - `/admin/crawler.php`'s outdated "Cron Schedule */5" words;
  - the pre-flight's AZCC line tests the new host;
  - the "Added switched off" wording, by a data migration with `data_reason`;
  - the Stripe and Square cards read the keys `MarketPay` reads;
  - Name.com's outside requests that don't save (their own table, like the pre-flight's).
- **Release 3: the alarms and Pulse**
  - Pulse shows what each person has read;
  - the old Overpass and OSM cases resolved;
  - Pulse #22's Site health items;
  - the release pipeline as a process with alarms: a build that fails its staging checks is withdrawn by itself, a bad
    `release.json` can't strand a release at Planned, and a rebuilt zip under the same name isn't stuck at "Plan
    approved".
- **Release 4: maintenance and the upkeep calendar**
  - Pulse #31's 62 unused names (one list for Jason: safe, keep, unsure), and that case's blank tool view;
  - the leftovers `_moved_npi_orgs`, `_moved_irs_orgs` and `search_index_state.v`, removed under 0162's `"removes"`.
- **Rather leave out of the board's releases:**
  - **`Vectors::setupSql()`'s row level security:** a security fix, too small and too urgent to wait for Release 2.
    Its own small release.
  - **Coverage reading our Geofabrik copy before Overpass:** it changes how W1 gathers, which 0171 leaves alone. The
    source-map session's line of work.

## What exists to build on (live mirror, `sync-app/` = `app/`, `sync-storage/` = `website/`)

- **Pre-flight**: `app/lib/Preflight.php` (GROUPS: the site, outside services, W0 and the crawler, query timings, the
  source map `NEEDS`, the site's needs, workflows, tasks, phases `PHASES`, the switch-on check); page
  `app/admin/preflight-console.php`, https://traversence.com/admin/config/preflight.
- **Start the system**: `app/lib/SystemStart.php` (steps keys → watching → switch-on check → confirm operational →
  Railway's schedule → master → W0…W12 → tasks; `gate()` and `runs()` watch each unit alone; report download and
  "Operational: N on"); `app/lib/Automation.php` (WORKFLOWS, JOBS, switches); `app/admin/automation-console.php`,
  https://traversence.com/admin/config/automation#start.
- **Services and holds**: `ServiceHealth.php` (issues, kinds, probes, holds), `Operations.php` (the register of caps,
  `ADDED_OFF`, status), `OutsideServices.php` (COMPANIES, hosts), `Services.php`, `Thresholds.php`, `Clocks.php`
  (the 14-day ramp), `Scheduler.php` (site jobs, cron lines).
- **Crawler and W0**: `app/lib/crawler/CrawlService.php` (runs, `crawl_runs`), `Frontier.php`; consoles
  `app/admin/crawler-console.php` (Insights → Crawler), `app/admin/index-console.php` (Insights → The index (W0)),
  `app/admin/operations-console.php`, `app/admin/cleanup-console.php`.
- **Endpoints**: `website/api/crawl/` holds 21 endpoints plus `_auth.php`: commoncrawl, geocode, guides, heritage,
  identity, index, irs, jobs, landmarks, learn, loads, npi, places, rec, refresh, results, service, sources, targets,
  usage, verify.
- **Workers**: `workers/crawler/` (crawler and `indexer.py`), `workers/video/`, on Railway (project
  a445f0be-b6de-46bb-a422-51ff835ca6e4): `traversence` (cron */5, set by the site), `w0-indexer` (always on),
  `video-worker`. Never change a Railway cron or restart policy; the site sets them.
- **Pulse** carries notices, not approvals (Jason, 2026-11-17).

## What earlier build sessions did (summary, oldest first)

- **Nov 14.** These sessions laid the foundations:
  - **Crawler, migrations and switches:**
    - The crawler stopped retrying APIs that refuse us (406).
    - Migrations now run at publish, with safe rollback (0162; `handoffs/migration-safety.md`).
    - The automation switches arrived: master, workflow and service, shipped paused (#48; `handoffs/automation-switches.md`).
    - The data cleanup tool shipped (#50, #51; `handoffs/data-cleanup.md`).
  - **The Knowledge Engine's documents:** 0164 and its rule book; the rural research tests in Kayenta, St. Johns and Springerville; the gap review; and Jason's approvals applied to 0163 and 0164.
- **Nov 15.**
  - **Release A** (operations, stages 2–6) added the service register, the caps and the holds.
  - The **duplicates** cause was found and the self-correcting recheck planned (`handoffs/duplicates.md`).
  - **0165:** staging became live in safe mode, as one release (#56, #57; `handoffs/0165-staging.md`).
  - **Release B** (the Knowledge Engine, stages 7–11) shipped as #58–#67 with automation off (`handoffs/release-b.md`).
  - Smaller work: stock photos fixed (#68); Bluehost's bot check tested on every caller.
  - The first one-button **Start the system** was built.
- **Nov 17.**
  - **Domains:** domains and the wallet (#71); domain sales on their own switch (#89; `handoffs/domains.md`).
  - **W0 indexer:** every URL logged and always on, with its own Railway service (`handoffs/w0-indexer.md`).
  - **Admin:** the Releases page folds each release (#80); the favicon on every page.
  - **The pre-flight** (#88–#94, `handoffs/preflight.md`).
  - **Gates and switches:** the staging gate on all 36 outside calls (#102); scheduled tasks and their pages linked both ways (#105).
  - **Source map (b):** W1 reads our OpenStreetMap copy before Overpass (#101), and a single timeout is no longer a stop (#114).
  - Jason pressed Start at 18:44 UTC.
- **Nov 17–18.** The same source-map session rebuilt Start:
  - confirm operational within minutes, then switch on in order (#115);
  - the report download, a truthful notice and the phone top bar (#116);
  - fixes in place and in drawers (#117);
  - the card fits a phone, with "Switch W1 on" (#118);
  - service problems explained on the card, and the tables as cards on a phone (#119).
  - The system has read "Operational: 19 on" since.

## Lessons those sessions left (read `handoffs/builder-playbook.md` first)

Failed or stuck releases:
- MODIFY in a migration (#70);
- `"switch":"none"` (#108, #109);
- a re-sent zip name (#106).

`send.py` now refuses all three.

Deviations Jason had to correct:
- fixes that sent him to another page;
- an action with no way back;
- page reloads to the top;
- a card wider than a phone;
- stale wording;
- asking in Pulse or in plain lines instead of the pop-up;
- re-proposing what a decision already set;
- two PRs recording the same delivery.

The playbook covers each one.

## Cleanup fixes folded in (Jason, 2026-11-18)

Jason: "clean up fixes that touch the system should move into the system board session so they can be addressed as it
builds the board." Moved from the session manager's queue (cleanup stage). Fold each into the board release it
belongs to (most fit Release 1's register and routes, Release 2's ledger and Start/shut-down, Release 3's alarms and
Pulse, or Release 4's maintenance); say in each release which ones it closes, and list any you'd rather leave out.

- **Each crawler run opens to the records it touched** (Jason, 2026-11-18: "yes queue the crawler run records fix").
  Insights → Crawler (https://traversence.com/admin/insights/crawler, `app/admin/crawler-console.php`) counts what
  each run and task did, but nothing opens the records themselves, so "what did W1 bring in today" means searching
  Listings and Review by hand. Build: each run's task line and each count (added, changed, sent to Review, held,
  failed) opens a list of those records, each linked to its page (a listing, a Review item, a landmark, a guide),
  with what changed (fields before and after; under 0164 a run never overwrites what a person entered) and the
  source it came from; a filter for workflow and day ("W1 today").
  Find first whether runs already record the ids they touched (the run log, `crawl_*` tables, W0's audit log); if
  not, the worker and `api/crawl/` report them and a new table names its class in `staging-tables.php` (R14). A
  command-strip record page per run (0129), with Peek on each record.
- Pulse #31's 62 unused names (Jason, 2026-11-17: "just add it to the queue"): the monthly check found 62 tables
  and columns no live file names (0-row tables like `accuracy_reports`, `ai_credit_usage`,
  `gbp_addon_subscriptions`, `guide_mentions`; dated backups like `listing_actions_bak_20261014`). A cleanup
  session checks each against the live code, names built on the fly included, and gives Jason one list: safe to
  remove, keep (with the reason), unsure. On his OK, a release removes the safe ones (copied first, under 0162,
  named under "removes"), Keep it is pressed on the rest, and the case closes. With it, Jason's rule (2026-11-17):
  "we dont want a ton of non-used items in our dbase". Also: that case's "Open the tool" view is blank on the
  left (no tool drawn for it); give it its list, or open the case alone.
- Pulse #22's Site health items;
- `/admin/crawler.php`'s outdated "Cron Schedule */5" instruction (Start the system sets the cron now).
- Outside services' Stripe card checks `STRIPE_LIVE_SECRET_KEY`/`STRIPE_LIVE_CLIENT_ID` for live, but `MarketPay` reads
  live keys as `STRIPE_SECRET_KEY`… (no `LIVE_`), so a correct live setup shows "keys missing" (found 2026-11-16).
  Make the card read what `MarketPay::missingAll()` reads; same check for Square's card.
- `Vectors::setupSql()` (Supabase card) leaves `tv_vectors` without row level security and `tv_match` callable by
  Supabase's public roles. Add `enable row level security` and revoke `tv_match` from anon/authenticated/public with
  a grant to service_role (Jason ran these by hand on 2026-11-16).
- Outside requests don't save (Name.com ticket #3113488, after #84/#87/#89): likely the same cause the pre-flight
  found in `search_index_state.v`, a live column shorter than the code assumes, cutting the JSON silently; fix as
  the pre-flight did (their own table), confirmed by the card's "Can't keep requests" line.
- **A build merged while its zip name was already in the inbox stalls at "Plan approved"** (release #106,
  2026-11-17): `large-fallbacks.zip` was sent, then rebuilt under the same name; on merge the site recorded "taken
  from the inbox (it was already in)" and never put it on staging, and Check for builds said "nothing is waiting".
  Jason had to press Put it on staging, then Publish. Fix: a rebuilt zip (new bytes, same name) is taken as a new
  build and goes on to staging by itself; a release left at Plan approved with its zip present is picked up by the
  10-minute job and by Check for builds; and a Claude build that passes staging publishes itself as usual.
- **Unused database leftovers are removed, not kept** (Jason, 2026-11-17: "we dont want a ton of non-used items in
  our dbase"). First: `search_index_state.v`, the 200-character column left after #107 added `v_long` (add-only,
  since the release check refuses `MODIFY`). Once every reader uses `SearchIndex::state()` (grep for direct reads of
  `search_index_state`, e.g. health.php's listing_mark) and `v_long` holds every row, a later release copies any
  remaining `v` into `v_long`, moves reads and writes to `v_long` alone, and drops `v` as a recorded leftover under
  0162 (named under `"removes"` in release.json; Pulse #31's unused-schema list shows it). Same sweep for the other
  leftovers that list names.
- **The pre-flight's Arizona Corporation Commission line tests a dead address** (2026-11-17): `Preflight::test`
  case 'azcc' still calls `ecorp.azcc.gov`, which no longer resolves; the Commission moved to
  `arizonabusinesscenter.azcc.gov`, which answers our host 403 "Access Blocked". Test the new host and say it's
  blocked pending the records request (one line), not "No answer".
  When it's live, tell Jason that Resume on the AZCC service card is his call (it stays paused until then).
- **The "Added switched off" note is out of date** (2026-11-18, from Jason's phone): AZCC, NM SoS and the Arizona
  licence lists still read "Paused: Added switched off (Jason, 2026-11-15): nothing calls it until Release B and a
  person's switch". Release B has shipped. Reword `Operations::seed`'s reason (and the live rows' `paused_why`, by
  a data migration with `data_reason`) to "until a person switches it on" plus each one's real reason: AZCC and NM
  SoS, the address fix and the records request; the licence lists, Cloudflare (by hand, queue 3a).
- **OpenStreetMap coverage jobs wait forever on Overpass** (2026-11-18, the crawler's log): coverage jobs
  800000013 and 800000014 say "waits for OpenStreetMap, every OpenStreetMap server is held" (overpass-api.de's ban
  and the three mirrors held). W1 already reads our Geofabrik copy first (#101); coverage still asks only Overpass.
  Make coverage read the Geofabrik copy the same way, Overpass only as a fallback when one is open (never retried
  while held). Also: the crawler exits non-zero when its only task (Common Crawl, held by issue #13) fails, which
  Railway shows as CRASHED; a run whose only failure is a held service should end ok.
- **Start's watch counts "nothing to do" as a good run** (2026-11-18, the source-map session): the identity
  recheck and the vector index show "0 good runs" because their runs end "nothing to do", which
  `SystemStart::runs()` doesn't count (and `t:recheck` skips `crawl_runs` entirely), so their watch ends only at the
  6-hour mark. Count a task's own crawler outcomes, ok or nothing to do, as good. Small; Jason's OK as its own release.
- **A release that fails its staging checks is withdrawn by itself** (Jason, 2026-11-17: "withdrawing bad releases
  needs to be addressed so it can be automated upon fails"). Today a failed release sits on staging ("check failed:
  upload a fixed zip or put it on staging again") until someone presses Withdraw: #70, #78, #108 and #109 were all
  withdrawn by hand. Make it automatic: when a Claude build (from git) fails its staging checks, the site withdraws
  it ("Withdrawn: its checks failed: <which check and why>"), records that in its release record (so the building
  session sees it in git), and raises one Pulse item for staff; nothing on live changes. A build uploaded by a
  person isn't auto-withdrawn (they may fix it on staging), but gets the same Pulse item. A later build with the
  same name replaces it as usual.
- **A bad `release.json` can't strand a release at Planned** (#108, #109, 2026-11-17: `"switch": "none"` was stored
  as a switch key, `approve` found no such switch and threw, so both sat at Planned and never reached staging;
  Jason, on #109's card: "this is not how this is supposed to work"). `send.py`/zipcheck refuse a `switch` that
  isn't empty or a known `Switches::DEFS` key; `Releases::fromUpload` treats "none" as empty; a release that can't
  be approved says why on its card and in Pulse, never sits silently.
- **Pulse shows what each person has read** (Jason, 2026-11-17: "also need a way to know when Ive read something in
  the pulse"): Pulse items and cases have no read state per person today (only message threads and sources' rules
  do). Add it: an item is **unread** (bold, a dot) for each staff member until they open it; it goes unread again
  when something new happens on it (a step, a note, a change of status); a filter "Unread" in the list's one
  filter dropdown and a count on Pulse's tab; "Mark all read" and "Mark unread" per item. Read state is per person,
  so two people can see what the other hasn't opened; kept small (one row per person per item, last seen time).
- **Database leftovers:** the `_moved_npi_orgs` (38 MB) and `_moved_irs_orgs` (16 MB) tables in the health report:
  confirm they're leftovers of a finished move (decisions/0110, 0162's leftover check in Pulse) and nothing reads
  them, then drop them through a release's `"removes"` ⚑.
- **Pulse:** the old Overpass/OSM cases from before the pause (resolve, keeping the overpass-api.de one), and the
  62 unused names (above).
