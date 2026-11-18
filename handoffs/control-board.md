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
   **Send it to the session manager for Jason's OK (decision pop-up) before any build.**
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
