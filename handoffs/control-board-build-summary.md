# The control board as one release: the release summary (step 4)

2026-11-23. The build session (session_01CXLi5iMkDf1xiJ4V9rScRe, taking over at the 23:25 UTC hold) for the session
manager's review, then Jason's pop-up. The plan is `handoffs/control-board-build.md`; the log, every step with its proof,
is `handoffs/control-board-build-log.md`. **Not yet in `main`, not sent, not live.**

**Built on:** the mirror at **Sync 2026-11-22 23:05 UTC (`8d3afbd`)**. Fetched again before zipping (CLAUDE.md).
**Sent as:** `control-board.zip`, `"review": true`: Jason sees it on staging and approves UI, UX and Functional before
Publish. `"data_reason"` for the one `UPDATE` (NPI's switch, below).

## 1. What it is, in a few lines

- **One engine** applies the state model Jason approved on 2026-11-22 (`step3/02-state-model.md`, 196 rows) and replaces
  the separate copies of the map in nine libraries: what a fault holds (only what its cause touches), the system's three
  tries on its own clock, a trip when they run out, a check before any close, words from the state as it is now.
- **The board he approved:** three views first (Is it delivering?, Development, Incidents), the Arc gauges, the 16
  development gauges, the runtime lifecycle, the check register, the Workflows page, the four-zone module cards, the
  Incident Reports, the folded report, Copy by section.
- **23 defects fixed** (plan §4, D1–D23), and six more the rendered and views checks found this session (§3).

## 2. Proof (on the bench: the live mirror with the build over it, MariaDB 10.11, PHP 8.3; never live)

| Check | Result |
|---|---|
| Every PHP test (`tools/control-board-tests/run-all.sh`: the board's and the dispatcher's, 80 files) | **1,549 pass**, 3 known failures (below) |
| Every rendered test at 1280 and 390 (`rendered-all.sh`, 14 tests: board-menu, board-phone, board-rendered, board-tabs, board-views, lines, module-cards, one-panel, one-place, peek-anchor, workflows-page, dispatcher r2–r4) | **310 pass, 0 fail** |
| The zip check (`zipcheck.py`) | **clean: no new failures**; one approved drop named (`--drops OFF`, §2b) |
| The scenario test (`scenario/run.php`) | **135 of 135 designed scenarios run, 0 fail** (30 can't be caused on the bench, each saying why); **173 of 173 cells covered, every one passing** |
| The views (new: `views()` after every scenario) | **159 of 173 cells drawn** on the board's row, the alarm's Pulse item and its module card, the words checked on each; the other 14 say why in `cells.md` |
| Buttons pressed in a rendered test | Write the root cause (and Change the root cause) on an Incident Report, at both widths, kept after a reload and in the logbook; Check now, Check and start again, Acknowledge, Check it now on the cards (module-cards); the board's ⋯, counts, Open every system, Holds: links, search (board-rendered, board-menu); the gauges' snapshots and three paths with Back, ×, Esc and a click outside (board-views) |
| Phone at 390 | every rendered test checks no sideways scroll at 390; `layout-probe.mjs` measures public pages signed out on the live site, so it doesn't reach admin pages |

### 2a. Known failures

Known PHP failures, the same three as before this build (listed in `run-all.sh`'s KNOWN): r1 sees the bench's own
`zz_test` endpoint; two `register.php` checks read live files outside this build.

### 2b. One drop, named

`NpiBulk::OFF` (the constant that kept NPI's load off) is removed: D12 replaces it with the npi_bulk task's own switch and
its why (03 §22 NPI 1, in step 3, approved by Jason 2026-11-22). Its one other reader, `website/admin/crawler.php`, is in
the zip and reads the switch. The zip check names it with `--drops OFF` on that approval; **the session manager to
confirm the approval covers it**, else it goes to Jason in the pop-up.

### 2c. The zip

`releases/inbox/control-board.zip`: 43 site files (34 changed from their live copies, 9 new, including 3 migrations:
`2026-12-01_control_engine.sql` and `2026-12-01_control_incidents.sql` add only; `2026-12-01_npi_switch.sql` one guarded
`UPDATE`, hence `data_reason`), each differing from live byte for byte; `release.json` with `"review": true`,
`"switch": ""`. Built on Sync 2026-11-22 23:26 UTC (`6b67f7e`; no site file changed since 23:05). Recorded in
`deliveries/control-board.json` (pending).

## 3. Found and fixed this session

| What the check found | Fix | Source |
|---|---|---|
| A trip joining an incident sent nothing (a timeline note only) | It updates the incident's Pulse item and replies in its email thread, the first trip's subject, no push (`ControlAlarms::mail`, `Notify::send` `no_push`); S-F124's 7 checks | 02 row 124; the session manager's ruling (2) 2026-11-22; 0176 §1; note added to decisions/0176 |
| The dispatcher's own "reached no one" trip sent you to Communications to name a founder | Founders are staff logins' Founder level: it opens **Staff levels** (`/admin/backoffice/levels`) | ruling (3): `manifests.php` `backoffice` → `levels`; `CommsSetup::founders` reads `Workspaces::levelOf`. The dispatcher card's own link, Communications, is right and kept |
| **Write the root cause did nothing** on an Incident Report: the page didn't load its script | The IR page loads `admin-control.js`, which on that page only answers the button (no redraw) | 02 §2.6, P12 |
| After writing it, the page still read Unconfirmed until reloaded | The line is drawn again in place, Confirmed with its words; its line has no × (a fact, `data-tv-keep`) | playbook §2 "a press comes back to where it was pressed"; principle 4 |
| A module card printed a raw time token ("since {t:2026-…}") | The card's light draws it as a labelled time | CLAUDE.md "Times where they happened" |
| A job's alarm read "Holds: job:digest_tick" (board and Pulse); Start's watch titled one "wf:W2: held by…" | Plain names everywhere: "Lead scores (job)", "W2 Re-read websites" | 02 §5.4: "never job:, wf: or svc:" |
| The card's issue and the board's alarm told two versions ("Since … Backing off until …" under another title) | The issue's thread takes its alarm's title and status (one engine); What to do, Details and buttons stay the card's | 0176 §3 "the thread is the alarm"; 02 §4.8 row 26; note added to decisions/0176 |

## 4. Every behaviour and its source

(The 23 defects, plan §4, each with the step 2 or live-read that found it and the row of 02 it follows.)

| # | Behaviour now | Source |
|---|---|---|
| D1 | A task's trip holds only that task's work: W1's bulk loads wait on `/api/crawl/loads` as its own part, W1 runs | 02 rows 11–14; 0175 §3; live-reads C2 |
| D2 | The system's tries are its own checks of what failed at about 5, 15 and 35 minutes; a trip when they run out or the hour ends | 02 §0a ruling 2, P3; 0171 §5 |
| D3 | Check and start again checks first, calling what failed; a red check keeps the trip and names the part | 02 rows 93–94; 0171 §7 |
| D4 | Words from the state: "Paused by the system (…) since …", "Trying again (n of 3)…", "Recovered: … was down n min", "Fixed by <name>"; W13 a list, W14 Not built | 02 §4.8; 0175 §2; 0176 §7 |
| D5 | One count of open trips everywhere; an acknowledged trip stays open and red ("1 trip (seen by Jason)") | 02 row 96; 03 §9 |
| D6 | The ledger records every worker call | live-reads C3 2 (read on staging after it lands, §7) |
| D7 | The Data browser lists every realm, labelled | live-reads, `data.php` |
| D8 | `identity_recheck_tick`'s hang shows on W2's page, holding only `recheck` | live-reads C2 #5 |
| D9 | Failed crawl jobs are a count on their task's workflow, with Try again there | live-reads, Insights |
| D10 | Tavily's 432 is a used-up quota | 03 §17 |
| D11 | The IRS load checks its file's shape and keeps a 90% floor before replacing anything | 02 row 38b; for-jason-review 5 |
| D12 | NPI's monthly load is off on its own switch with its why ("CMS took its NPI file page down…", Jason 2026-11-02), not a constant in code; the migration sets it once (`data_reason`) | 03 §22 NPI 1 |
| D13 | Turnstile refusing our secret lets people sign in and trips sign-in as critical | 03 §24 3 |
| D14 | The video worker restarts on failure, up to 10 times (`workers/video/railway.json`, reaches `main` only in the merge) | 03 §23; Jason's restart rule 2026-11-21 |
| D15 | Copy on every section and card | Jason, live-reads C6 |
| D16 | The report folded: summary and spend first | Jason, live-reads C3 |
| D17 | The Arc gauges, the 16 development gauges, the runtime lifecycle, the check register, the Workflows page | 03 §27 (Arc); gauge-lifecycle.md; check-register.md; workflows-page.md (all approved 2026-11-22) |
| D18 | Hosts and endpoints are parts with a role and an Incident Radius; the video worker has a heartbeat | 02 §6; 03 §1–§9 |
| D19 | One dispatcher path: a close updates Pulse and email; a trip to nobody raises the dispatcher's own trip | 0176 §1–§4; 02 P10, P11 |
| D20 | Critical duties from the manifest trip at once | 0171's critical note; 02 §5 |
| D21 | Start's watch never writes a person's switch | 02 CS-1, P5 |
| D22 | Incident Reports (IR-####): members, radius, timeline, evidence, root cause Unconfirmed until a person writes it | 02 §2.6, P12; 03 §7 |
| D23 | The outside heartbeat: every 20 minutes from GitHub, emails at once while the site is down, Recovered in the same thread; imported to the board when it's back | 02 §0a ruling 6; `.github/workflows/heartbeat.yml` reaches `main` only in the merge |

## 5. The admin's test, one line per new state

- **Is it delivering?** (where the board opens): Jason sees three questions (healthy, held, needs me) and a gauge per
  promise; it tells him at a glance whether the site does its job; he presses a gauge to see why.
- **A gauge's snapshot:** its measure, target, reading and missing setup, and Workflows ▸, Core Services ▸, Registered
  Services ▸; he presses a path to see what it depends on, ← Back to return; ×, Esc or outside closes it.
- **Development:** 16 gauges in Planning with "not signed off"; nothing to press yet (sign-off is question 1, §7).
- **Site health on Development:** each finding with its page and who it blocks; Mark fixed or Run the checks now.
- **Incidents → IR-0001:** "Unconfirmed: No one has written its root cause yet"; he presses **Write the root cause**,
  types it, and sees "Confirmed: <his words>" at once, with the answer beside the button.
- **Paused by the system (a trip):** what's held and since when; he presses **Check and start again** (it checks first)
  or waits: it closes as **Recovered** by itself when its check passes.
- **Recovered / Fixed by <name>:** the same message, closed; nothing to press.
- **A second trip joining an incident:** his Pulse item for the first trip updates ("IR-0001 · Correlated · 2 parts:
  …") and a reply lands in the same email thread; no second push; nothing new to press.
- **The dispatcher reached no one:** a trip on the board saying so, with **Staff levels (who receives trips)**: he gives
  a staff login the Founder level.
- **A module card:** its light in words (Working, Warning (the system is trying), Paused by the system, In maintenance
  with Back by, Off with who and why, Not checked yet, Retired); Fix it only where a person can fix it, else "The system
  is handling it" with Check it now.
- **Workflows page:** a gauge card per workflow; W13 "a list", W14 "Not built"; he presses a card for its own page.
- **NPI's task:** "Off on purpose: CMS took its NPI file page down until further notice (Jason, Nov 2, 2026)", with its
  switch beside it.
- **The heartbeat, not set up:** a warning "not set up (ALARM_TO)" until its secrets are added (A1, below).

## 6. Kept, moved and dropped (playbook §3)

**The board (03 §9):** moved: the strip first, alarms last → three questions first, each trip and switch a row with its
buttons. Replaced: "3 engine feeds failing" closed group → grouped by cause, each member keeping its buttons; Acknowledge
turning the light green → the light stays red, "seen by"; the card's parts in no order → the four zones; 0173's seven
stages → Jason's eight; "On <company>" links → four typed slots with source tags; the module list at 390 → the whole card
stacked. Added: role and criticality; workers as cards. Dropped (Jason's OK in 02 rows 95, Q17): Mark resolved, Use
backup; and the old words ("Nothing could be paused from here", "Couldn't look: HTTP 401", "nobody had set this switch
before").

**The Workflows page (workflows-page.md §7, approved 2026-11-22): 8 kept, 2 moved, 3 dropped.** Kept: the stage
workflows' builder; each workflow's own page; By workflow, this month; each workflow's switch line; last run; Returns
empty; the settings form with versions; the task table (as "Its steps and their switches"). Moved: Start the system →
the board's Start-up only; Check before switching on → each red line onto what it holds, the whole check in Start-up's
pre-flight. Dropped: the interlocks note; the list of W1–W14 buttons (the cards replace it); the old intro line.

**Site health:** moved from its Pulse case to the Development view's list, each finding once as its own Pulse item (no
push); the case's buttons (Mark fixed, Check again, Run the checks now) kept on the list (check-register.md, Jason
2026-11-22).

## 7. Questions for Jason, as one (principle 7)

1. **The gauges' sign-off has no recorder yet:** all 16 development gauges stay in Planning until he signs each off
   (B10–B19). Should the next release add a Sign off button on each gauge (his name and date, Undo), or does he sign in
   chat and a session records it?
2. **BRAND's three standards a browser can't read** (one header per view, filters as one dropdown, card anatomy): judged
   by the page review (0145) as a reviewer's line, marked "not read by the check"?
3. **The format of `site-audit/tools.md`** the tools check reads, and **the light probe's key pages** (default `/`,
   `/discovery/`, `/directory/`, `/login.php`).
4. **"Half its budget in 14 days" (R153)** has no data source yet: left out until one is registered.
5. **A server's own check** runs its module's whole pre-flight line; a check per server needs a `probe` in its manifest.
6. **The heartbeat's secrets (close.md A1):** `ALARM_TO`, `HEARTBEAT_SES_KEY_ID`, `HEARTBEAT_SES_SECRET` at
   https://github.com/degraffics/traversence/settings/secrets/actions. Until then it sends nothing and says so.

None blocks the release.

## 8. Still to do around it

- **Staging, read-only, after it lands:** the Ledger tab (https://staging.trvgo.us/admin/config/control?tab=ledger) and
  C15 (`irs_loads`). Live's https://traversence.com/admin/config/control?tab=ledger only on Jason's call.
- **Repo files that act by themselves reach `main` only in this release's merge, after his Approve:**
  `.github/workflows/heartbeat.yml` (starts a schedule that emails), `.github/workflows/site-checks.yml` (starts the
  hourly live probes), `workers/video/railway.json` (can redeploy).
- **Outside this zip:** the crawler worker's 432 fix (`workers/crawler/crawler.py` `note_issue`), a worker release after
  the board is live.
