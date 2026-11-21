# The control board: step 2, the audit of what's live (2026-11-21)

Step 2 of `handoffs/control-board-plan.md`. It grades what's live against step 1's list (`../step1-requirements.md`,
whose section 1 is the model: what a trip is, a service in its workflows, the two clocks), under the plug-in contract
Jason approved as step 3's frame (`../module-harmony-report.pdf`; worked in `../manifests-railway-github.md`).

**How it was checked:**
- The live mirror at Sync 2026-11-21 12:26 UTC (`37a212e`) and the crawler worker 2.16.1.
- Four auditors, each on its own copy of the site and its own test database, **proving behaviour by running probes**
  (raise the failure, read what was paused and said), not by reading hand-offs.
- Live data from `site-audit/results/alarms.json` (10:19 UTC) and Railway's logs, read-only.
- Nothing on the site, the mirror or Railway was changed.

| File | What it covers |
|---|---|
| `g1-crawler.md` | Crawler-side failures: a task fails, a slow call, a source down, our own endpoint slow or failing, the worker down |
| `g2-services.md` | Sources and services: keys, quotas, rate limits, bans and blocks, server errors, backups; which manifest fields exist today |
| `g3-jobs-hosts.md` | The site's jobs, checks, critical duties, switches and maintenance; Bluehost to the contract; the Incident Report |
| `g4-channels-views.md` | How each alarm is shown on the board, in Pulse, by email and by push; the Fix it map; the Communications Dispatcher to the contract; the views |
| `live-snapshot.md` | The open alarms on live at 10:19 UTC (13 of 21), and what each shows wrong |
| `ir-0001.md` | IR-0001's root cause from runs #1288 and #1290: our load endpoint didn't reply in time; handler or response path still open |

## The totals

**Failure map:** 173 cells.

| Area | Cells | Matches | Differs | Missing | Other |
|---|---|---|---|---|---|
| Crawler side | 35 | 7 | 23 | 5 | — |
| Sources and services | 31 | 7 | 20 | 4 | — |
| Jobs, checks and hosts | 38 | 12 | 18 | 6 | 2 partial |
| Channels | 69 | 35 | 25 | 9 | — |

**Requirements graded:** 179 of 189. Area 13, the build's own process rules, is graded in the last section.

| Grade | Rows |
|---|---|
| Works | 72 |
| Partial | 79 |
| Broken | 7 (R67, R68, R76, R87, R101, R113, R128) |
| Missing | 8 |
| Needs staging | 12 |
| Needs live data | 1 |

## What the findings have in common

Seven root causes explain most of the 74 cells that differ and the 24 that are missing. Each is today written
separately in several libraries (ControlAlarms, CrawlService, ServiceHealth, Preflight, Operations, Scheduler). That is
the plan's case for **one engine that applies the map** (step 3).

1. **Tries are counted on the wrong clock** (model 1.3).
   - A scheduled run, or each failed call, counts as a try.
   - The count freezes at "1 of 3", and the hour then trips or escalates with no retry made.
   - It covers tasks (case #51), jobs (a daily billing failure reads "Retries unsuccessful"), pre-flight lines and
     service issues (one run's six timeouts trip at once, and the 6-hour window ignores successes in between).
   - *g1, g2 F4, g3 F3.*
2. **Holds are too wide, and some can't lift** (model 1.1–1.2).
   - A task's, a job's or a need's fault pauses its whole workflow, or only the first workflow it names.
   - Its recovery waits on the paused thing running again. So W1 stays held until a person acts: the live
     "0 records in, 0 stored" in 24 hours.
   - A job's skipped run is recorded as a good run, so it "recovers" falsely and trips again.
   - *g1 F1–F2, g3 F1.*
3. **Recovery isn't a check.**
   - "Check and start again" closes a service trip at once with no outside call: 5 ms, with the host still down.
   - The watch never re-checks crawler-reported or held issues, so issue #35 has been open 30 hours.
   - A refused key's Replace is blocked by its own issue.
   - No service has a probe.
   - An idle or skipped run reads as Recovered.
   - *g2 F1–F3, g1 F3–F4, g3 F1.*
4. **Hosts and our own endpoints aren't parts with a role.**
   - `loads.php` and the other crawler endpoints aren't on the board, so their faults are pinned on a workflow.
   - A crawler that's down barely shows.
   - The video worker has no heartbeat.
   - Bluehost's line is wired to the "Page load" duty but tests email and cPanel.
   - *g1 F2, F5; g3 F5.*
5. **Words are written by each raiser, not read from the state.**
   - A task trip says "the other tasks carry on" while the workflow is held whole.
   - A refused key reads "Nothing could be paused" on the board and "Paused by the system" on its card.
   - Cap titles show the band, not the reading.
   - A worker's report of the kind of failure is trusted without checking it.
   - *g4 F2, F5; live L5, L6, L10, L12.*
6. **The channels lose the thread.**
   - An acknowledged trip turns the top light green while its pause holds.
   - A person closing a trip never updates its Pulse message or email.
   - A trip with nobody to receive it is sent nowhere, with no record.
   - There's no fallback from one channel to another.
   - *g4 F1, F3, F4.*
7. **Critical duties can fail unseen.**
   - With the master switch off, a red database or scheduler line is only a warning.
   - A stuck or quiet billing or release job is only a warning.
   - Nothing checks billing's cron line, or whether pages load.
   - *g3 F2.*

**And the contract isn't there yet:**
- No module declares its role, Incident Radius, cadence or probes.
- There's no four-zone card.
- Cards show 0173's seven stages, not Jason's eight.
- Workflow pages show no cadence and no clickable queue counts; Retry failed is only on Insights → Crawler.
- There are no correlated incidents, and no Incident Report.
- Fix it has a landing for 3 families of alarm, and 25 alarm kinds have none.
- *g2 §2, g3 §4, g4 (b) and (d).*

## Live harm (raised to the session manager at once)

**W1 can't recover by itself.** The two task trips (`overture_bulk`, `foursquare_bulk`) pause all of W1, and the only
things that clear them need W1 to run. And the button a person would press, Check and start again, closes without
checking (root cause 3). The session manager decides whether a fix or a person's lift goes to Jason now.

## What needs live data or staging

**Live data,** for the session manager's one ask to Jason with step 3:
- IR-0001: our load endpoint's own timings for 08:15–08:21 and 08:26–08:32 UTC (the board's ledger rows for
  `/api/crawl/loads.php`, or Bluehost's access log).
- The 8 open alarms the snapshot leaves out, the open system pauses, and the open Pulse cases.
- Whether `OVERTURE_RELEASE` is set on Railway (its name only).

**Staging:** 12 rows (pressing buttons, rendered states with live-shaped data). They're checked in step 4's rendered
checks.

## Area 13: the build's own rules (R180–R189)

These are rules for how the build is run, so they're graded on what's been done so far.

| Rows | Grade |
|---|---|
| R182, R185, R186: sourced, no drift, no one-off patches | **Followed** (the case-51 release was stopped; conflicts became questions) |
| R180, R181, R183, R184, R187: one build, mockups, kept/moved/dropped, the Admin's test, staging | **Apply at steps 3–4** |
| R188: no change to how workflows gather, no spend | **Followed** (nothing built) |
| R189: the scenario test, then a daily consistency check | **Missing:** no scenario test exists yet. Step 4 also needs a test schema holding every board table (the live migrations were deleted after running) |
