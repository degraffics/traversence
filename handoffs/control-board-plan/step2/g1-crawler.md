# Step 2, auditor g1: crawler-side failures

Part of step 2 of `handoffs/control-board-plan.md`. It covers five things: a task fails, a call is slow, a source is
down, our own endpoint is slow or failing when the worker calls it, and the worker is down. Each one is checked against
every part it can hit. Grading follows step 1's model (§1.1 what a trip is, §1.2 a service in its workflows, §1.3 the two
clocks). Where the model already answers a question, it isn't asked again.

**Built on:** the live mirror at `37a212e` (Sync 2026-11-21 12:26 UTC), `sync-app/` = app, `sync-storage/` = website,
and the crawler worker 2.16.1 (`workers/crawler/crawler.py`, `WORKER_V` at L215). The citations below are file:line.
`lib/` means `sync-app/lib/` and `crawler.py` means `workers/crawler/crawler.py`. Live findings already recorded in
`step2/live-snapshot.md` (L1–L13) and `step2/ir-0001.md` aren't repeated here; they're cited where they fit.

**Proved, not read.** Each verdict marked *(probe)* was caused on the copy against database `lv_g1`. The probes are in
`step2/probes/g1/`:

| Probe | What it causes | Result |
|---|---|---|
| `p1-task-fails.php` | a nightly bulk task failing once; a 5-minute task failing on 3 runs; Check and start again | 12 of 13 assertions held; the one that failed is finding F11 |
| `p2-site-endpoint.php` | `crawl/loads` slow, slow with little history, three 5xx, and the worker's side of the same moment | 13 of 13 |
| `p3-worker-down.php` | a run that failed outright, a run that never reported, no run at all, starved, the crawler's check | 14 of 14 |
| `p4-source-down.php` (+ `p4g.php`) | an outside timeout, a back-off reaching its limit, a refused key, a 429, S3 reads, each need's trip radius, a backup's own line | 16 of 16, plus the radius table |
| `p5-key-not-reported.php` | a Railway key not reported yet (Q4) | 3 of 3 |
| Existing tests run on the copy | `holds.php` 24/24, `backoff-gate.php` 12/12, `expire-tries.php` 9/9, `r5a-rate.php` 18/18, `paused-now.php` 20/20, `critical.php` 20/20, `census-backup.php` 15/15, `paused.php` 26/27 | `paused.php`'s one failure is its migration re-run; the copy has no `migrations/` |

`control_ledger`, `control_records`, `system_pauses`, `service_issues`, `crawl_run_items` and the other tables the copy
has no migration for are created in `probes/g1/common.php` from their use in code.

## 1. The failure map (crawler side)

Each row is one failure against one part, by role. *Diag* is the diagnostic clock and *sched* the run schedule (model
§1.3). "Trip" words: the email and Pulse title is `SYSTEM ALARM NOTICE: <part>: Paused by the system`
(`ControlAlarms.php:648`, `noticeTitle`). Warnings go to the board only (`ControlAlarms.php:79` in the `mail()` block).

| # | Failure | Part (role) | Holds: code now | Holds: the model says | Tries & trip: code now (which clock) | Model says (which clock) | Words now (board · Pulse · email · push) | Fix it now | Recovery now | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | A 5-minute task fails on consecutive runs (e.g. `refresh`: our endpoint answered 500) | Task in W2; the cause is a Site endpoint or a Source | **W2 whole** (`wf:W2`, identity too) *(probe p1 b)* | The failed component's calls; tasks that used it reroute or wait; W2 whole only if every task waits | Each failed **run** is a try (`n_times`); trip on the 3rd run, ~10 min apart. **Sched** clock | Tries are re-checks of the failed component, 3 or 60 min. **Diag** clock | Board: "W2: its task refresh failed (run #N)… the workflow's other tasks carry on" (false). Pulse/email/push at the trip: "W2 Keeping listings current: Paused by the system" | None (`fixOf` has no case for `task:`, `ControlAlarms.php:734`); Run #N peek, "Try it first on the next run", "Pause the task" | Only the same task reporting `done` clears it (`CrawlService.php:263`), and it can't run while W2 is paused. A person's Check and start again only | differs | `CrawlService.php:261-263`; `ControlAlarms.php:103-118` (tries = events), `283-285` (`wf:` pause); probe p1 b |
| 2 | A nightly bulk task fails once (case #51: `overture_bulk` / `foursquare_bulk` timed out on our `loads.php`) | Task in W1; the cause is the Site endpoint `crawl/loads` | **W1 whole**: coverage and second_look left out of the next plan *(probe p1 a)* | Nothing (our endpoint timing out is slow: a warning on the Site part); the next night's run goes ahead (model §1.3) | A warning "1 of 3 tries, until HH:MM"; with no second failure, `expireTries` makes it a trip at 60 min **with no check made**. Wall clock from the first failure | Diag clock: re-check the endpoint within the hour; it answers, so the warning clears | Board: the warning, then "Its tries ran out at HH:MM UTC with no fix: it's a trip for an admin". Thread status: "It closes as Recovered when its check passes" (there is no check). Founder email and push | None | None by itself (as in #1). Live: L2 and L3 | differs | `ControlAlarms.php:897-920`; `CrawlService.php:261`; probe p1 a; live L2, L3 |
| 3 | A night run where the run tasks are `idle` and the bulk task fails | Worker (the crawler blamed); the cause is the task's component | Run status `failed` → `run:failed`; after **3 runs (~15 min) the master is paused: all gathering** *(probe p3 a)* | Only what the component touches (as #2) | Each failed run is a try. Sched clock | Diag clock on the component | Board: "A crawler run failed (#N)". Email and push: "The crawler: Paused by the system". No task alarm at all (`if ($status !== 'failed')`) | None | The next run is paused at once, its empty report reads `idle`, which **clears `run:failed` as "Recovered: The crawler was down 1 min, the system fixed it"** with no check made. A flap: trip, email, Recovered | differs | `CrawlService.php:237-241` (`idle` isn't `done`), `252`, `256`; `ControlAlarms.php:256-258`; `CrawlQueue.php:47`; probe p3 a |
| 4 | An outside API times out once in a run (first strike) and the task's exception isn't caught | Source (e.g. Tavily); the task in its workflow | Issue warning on `svc:tavily` **and** a `task:failed:<task>` alarm on the workflow; the second runs path #1 to a whole-workflow pause | The component (Tavily) is checked; the task reroutes to Brave ("On a backup"), or waits | Issue: its hits (6) or 6 h; task: runs. Two clocks for one cause, both wrong | One fault, one component, diag clock | Two alarms: "api.tavily.com: Didn't answer (the crawler, on Railway)" and "W2: its task identity failed" | None on either | Issue: see #13. Task: as #1 | differs | `crawler.py:306` (held only on the 2nd strike), `350`, `3822`; probe p4 a |
| 5 | Overture's S3 bucket or Hugging Face (Foursquare) is down for the DuckDB reads | Source (Overture, Foursquare) | The task fails, then #2 or #3: W1 whole, or all gathering | The source is paused; its task reroutes (bulk: "the last load is kept"), not the workflow | As #2 or #3 | Diag clock on the source | "W1: its task overture_bulk failed". **No service issue**: the source's card stays green | None | As #2 | differs | `crawler.py:255` (API_HOSTS has neither host), `3552-3590` (reads go through DuckDB, not urllib); probe p4 f |
| 6 | A request (`crawl_requests`, find_name or read_page) fails | Task (request queue) | That request only, retried after 6 h, failed after 3 | Same | 3 tries, 6 h apart. Its own clock | — | **Nothing on any surface** | — | Retry on the crawler console | missing | `CrawlService.php:344-355` (`finishRequest`); R46 |
| 7 | Our endpoint is slow (×3 its usual, with at least 20 earlier calls) | Site endpoint, filed under **W1** (`procOfEndpoint`) | Nothing *(probe p2 a)* | Nothing: a warning on the **Site** part | Never a trip | Never a trip | Board only: "/api/crawl/loads is slow: It took 236 ms a record, against its usual 8.0 ms…" | "The call (#id)" peek on the Ledger | Clears at the next call at its usual speed | differs (filed under the workflow, not the Site) | `ControlLedger.php:129-142`; `ControlAlarms.php:45-55`; `ControlRegister.php:66`; probe p2 a |
| 8 | Our endpoint is slower than the worker's timeout (120 s per 500-row POST, 60 s GET) | Site endpoint | Worker: `TimeoutError` → task failed → #2 or #3 (W1 or all gathering). Site: a slow warning if it has history. **The two aren't tied** | Nothing: slow on the Site part (Q3) | As #2 | Slow is never a trip | Two unrelated alarms; the task one blames Overture's or Foursquare's task | None | As #2 | differs | `crawler.py:451-476` (the timeout isn't caught), `3588`; probe p2 a; `ir-0001.md` |
| 9 | Our endpoint is slow with under 20 calls of history (a nightly load's first nights) | Site endpoint | Nothing | Slow judged against a limit (a speed gate) | No alarm at all *(probe p2 a)* | A warning | — | — | — | missing | `ControlLedger.php:134` |
| 10 | An outside call is slow but answers within 60 s | Source | Nothing measured | A slow warning on that source | — | Warning, never a trip | — | — | — | missing | `crawler.py:316-355` (calls and failures counted, no durations); R46, R64 |
| 11 | An outside call takes longer than 60 s (`socket.setdefaulttimeout(60)`) | Source | Reported as a `timeout` issue, so #13 | Model: a fault on the component, tries on the diag clock | As #13 (6 hits or 6 h) | 3 tries or 60 min | As #13 | As #13 | As #13 | differs | `crawler.py:4038`, `350`; `ServiceHealth.php:16` |
| 12 | A task is slow (its share runs out), so the run is starved | Task / Worker | Nothing | Nothing | `run:starved` warning; never a trip | Warning | Board only: "A crawler run had work and did none of it (#N)" | Run #N peek | The next run that does its work clears it | matches | `CrawlService.php:254-256`; probe p3 d |
| 13 | An outside API at a back-off kind (timeout, 5xx), reported by the crawler | Source (e.g. Tavily) | Warning while it backs off; then a trip holds **calls to that host** (`hold_until = 2099-01-01`). Tasks: the gate (#17) | The component's calls; tasks reroute or wait | Trip at **6 failures in a row (`CASE_AFTER`) or 6 h open**. Count clock | 3 tries or 60 min, diag clock | Warning: "Calls to api.tavily.com wait… (3 of 6)". Trip: "Its back-off ran out (6 failures in a row): calls … are held until an admin resolves it". Founder email | None ("theirs") | **Never by the system**: the watch rechecks only `caller = 'site'` issues, and the worker never calls a held host. A site check of the same host closes it only where one exists (L4: open 30 h) | differs | `ServiceHealth.php:173`, `349-350`, `408`; `ControlAlarms.php:1042`; probe p4 b, c; live L4 |
| 14 | The same as #13, raised by the site's own call | Source | As #13 | As #13 | As #13 | As #13 | As #13 | None | The watch runs the service's free check hourly (two per watch); an answer closes the issue and its alarm as "Cleared by its check" | matches (recovery) | `ControlAlarms.php:1037-1050`; `ServiceHealth.php:290-300`; `backoff-gate.php` |
| 15 | An outside API refuses our key, bans us, or is over quota | Source | A trip at once; calls to that host held until a person acts | Paused at once; waits for the person's button, whose check closes it | At once (`AT_ONCE`) | At once | Board: "api.tavily.com: Our key was refused". Thread "What it affects": "**Nothing could be paused from here (it only trips)**" while its calls *are* held. Email, push | Its card's key / caps / issue (`#svc-tavily-key`) | Replace or Check it now; a quota's probe returning 200 resolves it | differs (words only) | `ControlAlarms.php:28`, `742-746` (`affects`); `ServiceHealth.php:764-767`; probe p4 d |
| 16 | An outside API answers 429 | Source | Waits twice as long | Same | Never a trip | Never a trip | Warning; Check greyed with the next try | — | By itself | matches | `ServiceHealth.php:349`, `363`; `r5a-rate.php` 18/18; probe p4 e |
| 17 | A service's gate line is red (held, out of budget, key missing) | Source in each workflow that uses it | The gate: `covered` (nothing), `tasks` (those tasks), or `whole`. **The crawler's plan reads only `tasks`**, so a `whole` line never stops a planned task; it holds only at Start and switch-on | Model §1.2: tasks reroute or wait; the workflow whole only when every task waits | Gate reads `nd:` results; with no stored result, `covered` is false, so the hold is wider | — | The pre-flight line "Waiting while their source is down"; Paused now | The register card | When the line passes | differs (partly) | `Operations.php:964-983`; `CrawlService.php:117`; `holds.php` 24/24; probe p4g |
| 18 | A need has no working source (`pf:nd:<need>` trips) | The need, in each workflow it names | Tasks that call its services; where no task is mapped, the **first** named workflow whole only. Radius as built: prose (Gemini: W1–W3, W10, W11) → **W1 whole only**; writing (W4–W6, W8, W10) → **W4 whole only**; reread → W2 whole; semantic → W3 whole; drive → W7 whole | The tasks needing it, in **every** workflow named | Pre-flight runs as tries, plus the hour (`expireTries`) | Diag clock | "<need>: …"; "Paused by the system: <task> (<wf>)" | The line's own fix | Recovered when the need's line passes | differs | `ControlAlarms.php:267-271`, `283-285`; `Preflight.php:69-70`; probe p4g |
| 19 | A backup's own pre-flight line is red while the primary works | Source (backup) | Its gate is `covered`: holds nothing. Its own `pf:svc:` trip pauses nothing, not even its own calls | Nothing in the workflows ("On a backup" stays working); the component's calls paused | Pre-flight tries and the hour | Diag | "Nothing could be paused from here (it only trips)" | Its line's fix | When its line passes | differs (minor: the component itself isn't paused) | `ControlAlarms.php:244-290` (no `svc:` branch); probe p4 h; `holds.php` |
| 20 | A workflow's own pre-flight line is red on a part that isn't a need | Workflow | **The workflow whole** (`pf:wf:W3` → W3; live L1) | Only the tasks that part holds | Pre-flight tries and the hour | Diag | "W3: …" (live L1, L10 disagree) | Its line's fix | When its line passes | differs | `Preflight.php:311-317`, `351`; `ControlAlarms.php:283-285`; live L1 |
| 21 | A Railway key the worker hasn't reported yet (Q4) | Source | The service's gate line is **red**, so its tasks are held (W1 `second_look`) or its workflows whole (W12: Tavily, Brave) | "Not checked yet": holds nothing | — | — | "TAVILY_API_KEY on Railway: not reported by the crawler yet" | The register card | At the worker's next report | differs | `Operations.php:797`; probe p5 |
| 22 | A site blocks W0 or W3 (a refusal) | Source (a website) | That host held; never retried | Same | At once | At once | One notice "N sites blocked us"; Reactivate | Its line on W0's page | A person's Reactivate | matches | `ServiceHealth.php:470`; `ControlAlarms.php:740-741` (`fixOf`: blocked) |
| 23 | The Census geocoder fails on the site's connection | Source with a backup (Railway) | The batch goes from Railway; nothing paused | "On a backup" | — | — | Board: on a backup | — | By itself | matches | `census-backup.php` 15/15 (live #244) |
| 24 | Our endpoint answers 5xx (three calls) | Site endpoint, filed under W1 | **W1 whole** *(probe p2 b)* | The tasks that call that endpoint (`overture_bulk`, `foursquare_bulk`, `geofabrik_bulk` for `loads`); a Site part fault | Each worker call is a try; a trip on the 3rd 5xx. Call clock | Diag clock: re-check the endpoint | Board: "/api/crawl/loads answered a server error (500)". Email: "W1 Listings: Paused by the system" | None; "The call (#id)" and Check (W1's pre-flight, not the endpoint) | Clears at the next call answering under 500, but **no call comes while W1 is paused**. Deadlock until a person acts | differs | `ControlLedger.php:119-121`; `ControlAlarms.php:45-55`, `283-285`; probe p2 b, c |
| 25 | Our endpoint answers 406 (ModSecurity) or 4xx | Site endpoint | Worker: `RuntimeError` → task failed → #1 or #2 | A Site part fault | As #1 | Diag | The ledger keeps the 406 row, but alarms start at 500; the task alarm says "site … said 406" in its reading | None | As #1 | differs | `crawler.py:471`; `ControlLedger.php:119` |
| 26 | Bluehost's bot check (409 `humans_`) refuses the worker twice | Host (Bluehost, our site) | The task is "held"; nothing raised | A fault on the Site host's part | — | Diag | `site_blocked` is sent in every report and **read by nothing** | — | — | missing | `crawler.py:469`, `3980`; `grep site_blocked` finds no reader in the mirror |
| 27 | Our site is unreachable at a run's start | Host (Bluehost) as the worker sees it | No run row; the worker exits 1 | A Site fault (the page duty's own sensor) | — | — | After 30 min with no run, a **Pulse-only** case "The crawler has stopped", which blames the crawler | "Check status" → the crawler console | When runs start | differs | `crawler.py:4073`; `CrawlService.php:490-499`; `Pulse.php:365` |
| 28 | Our site times out on the run's report call | Site endpoint `crawl/service` | The run stays `running`, then `abandoned`; **its task outcomes are never recorded** | A Site endpoint fault (slow) | `silent:run` warning at 35 min, cleared at abandon (#30) | Diag | "A crawler run never reported (#N)", then gone | Run peek, Railway | The run's data is lost | differs | `CrawlService.php:382`; `ControlAlarms.php:944-945`; probe p3 b |
| 29 | Every task fails, so the run "failed outright" | Worker (the crawler) | Master paused (all gathering) after 3 runs | All gathering, only when the crawler itself is down | 3 runs. Sched | Diag | As #3 | None | The flap in #3: the next paused run reports `idle` and closes it as Recovered | differs | As #3 |
| 30 | A run is killed before it reports (Railway's 30-min `RUN_LIMIT`, a crash) | Worker | Nothing; each run gives one warning that clears when the next run marks it `abandoned` | Model: the worker down holds all gathering once its tries run out | `silent:run` raised once or twice, then cleared. **Four runs killed in a row never trip** *(probe p3 b)* | Diag: the watch's tries | Board only, then nothing | Run peek, Railway | — | differs | `ControlAlarms.php:944-945`; `CrawlService.php:382`; `crawler.py:4044`; probe p3 b |
| 31 | No run at all (Railway down, schedule off, deploy failed) | Host (Railway) and Worker | Nothing | All gathering paused (Q5: Railway down is the crawler down) | **No board alarm** *(probe p3 c)*; Pulse's own critical case after 30 min. The Railway deploy watch records its state only | Diag | Pulse only: "The crawler has stopped" | → the crawler console | When runs start | differs | `Pulse.php:365`; `Operations.php:525-560` (no alarm raised); probe p3 c |
| 32 | The crawler's own check (`Check` on `wk:crawler`, line `rw:crawler`) | Worker | — | A real check: recent runs reaching the site and doing their work | **Always "ok" once any run ever reached the site**: 3 hours with no run, or a failed last run, still pass *(probe p3 e)* | — | "Its last run reached the site 3 hours ago: ok" | — | So no crawler trip can close "when its check passes" | differs | `Preflight.php:809-813`; probe p3 e |
| 33 | W0 Indexer stops checking in (over 15 min) | Worker (W0 Indexer) | W0, once the tries run out | W0 | Watch ticks (3, about 10 min), plus the hour | Diag (watch) | Board warning, then the trip's email and push | Railway link | Its check-in at request start clears it | matches | `ControlAlarms.php:937-939`; `crawl/index.php:25` |
| 34 | The video worker is down | Worker (video) | Nothing | Its feed queued | No check-in exists | Diag | — | — | — | missing | `ControlRegister.php:37` (no check line); manifests' *spec* |
| 35 | Any crawler-side failure against a critical duty | Critical duty | No crawler part carries a duty, so nothing trips at once | Same ("not critical by themselves") | — | — | — | — | — | matches | `ControlRegister.php:84-107`; `critical.php` 20/20 |

**Verdicts (35 rows):** matches 7 (rows 12, 14, 16, 22, 23, 33, 35); differs 23; missing 5 (rows 6, 9, 10, 26, 34).

## 2. Requirements R65–R99

| R# | Grade | What the code does | Evidence |
|---|---|---|---|
| R65 | partial | A crash raises an alarm (task, run, endpoint 5xx). Slow is raised only for our endpoints with 20+ calls of history and for site jobs. The worker's retries and outside call durations aren't reported. A failed request raises nothing | `ControlLedger.php:129-142`; `crawler.py:316-355`; map rows 6, 9, 10 |
| R66 | works | Three levels: trip, warning, notice | `ControlAlarms.php:22` |
| R67 | broken | Tries are raise events (runs, calls, failures), never re-checks of the failed component; there's no component check for a task. The at-once kinds (held, limit) work | `ControlAlarms.php:103-118`; probes p1, p2 b |
| R68 | broken | `expireTries` turns the warning into a trip when the hour passes, with no check made; the task's scheduled runs are counted as tries | `ControlAlarms.php:897-920`; probe p1 a; live L2, L3 |
| R69 | partial | `raise()` demotes slow trips, and the watch lifts old slow trips. But the worker turns a timeout on our endpoint into a task crash, which trips | `ControlAlarms.php:89`, `1013-1017`; map rows 2, 8 |
| R70 | works | A back-off trips at its limit; 429 never trips; a back-off in its wait isn't a hold | `backoff-gate.php` 12/12; `r5a-rate.php` 18/18 |
| R71 | partial | A red need pauses its tasks, a backup's line is covered, but a workflow's own line pauses it whole, and a key not reported yet is red and holds | map rows 18, 20, 21; probe p5 |
| R72 | works | A critical part trips at once while it keeps trying; no crawler part is critical | `ControlAlarms.php:100`, `562`; `critical.php` 20/20 |
| R73 | partial | Recovered closes with nothing to press, but `run:failed` "recovers" through a paused, idle run with no check | probe p3 a; `ControlAlarms.php:593-610` |
| R74 | partial | The Escalation Notice goes only for a critical part's early trip (`escalated`). A non-critical part whose tries run out gets an ordinary "Paused by the system" trip notice, not "Retries unsuccessful, now admin required" | `ControlAlarms.php:218-229`, `576-590`, `909-918` |
| R75 | partial | An escalated trip closes as Recovered when `clear()` is called, but crawler task, endpoint and run trips have no check that calls it | `ControlAlarms.php:150-170`; map rows 1, 2, 24 |
| R76 | broken | At the trip, `tripped()` sets the Pulse item's `esc_level` to 2 at once ("goes to the Director's level"), not the Manager at 2 h | `ControlAlarms.php:226`; `Pulse.php:1026-1032` |
| R77 | works | Pauses read "Paused by the system (a trip): … It resumes when its check passes." | `Automation.php:152-156` |
| R78 | works | One row per open key; a repeat updates its count and last seen | `ControlAlarms.php:126-131` |
| R79 | works | Warnings and notices clear when their reading is good; cleared ones go to the logbook | `ControlAlarms.php:150-176`, `close()` |
| R80 | needs staging | Rendering is another auditor's. `pulseBody` offers Acknowledge on any open, unacknowledged alarm; whether warnings ever reach Pulse is g4's | `ControlAlarms.php:795-806` (`pulseBody`) |
| R81 | works | Group rows by system, level and family (unit); rendering is g4's | `ControlAlarms.php:375-410` |
| R82 | partial | Check and start again checks first, but on a task trip it runs the **whole workflow's** pre-flight, so unrelated red lines (a Railway key not reported yet) block it | `ControlAlarms.php:308-335`; probe p1 c |
| R83 | partial | Old trips are settled. The "not set up" closure runs only for site-caller issues | `ControlAlarms.php:1037-1050` |
| R84 | works | A red check names its red part, read part by part | `Preflight.php:323-326` |
| R85 | works (review) | Levels, pauses and escalation are decided in `ControlAlarms`; `Notify::send` only routes | `ControlAlarms.php:615-626` |
| R86 | partial | Pauses are their own records (`system_pauses`) and never write a person's switch. A service's trip holds through `service_issues.hold_until = 2099-01-01`, not a pause record, so it isn't shown where a person's pause shows | `Automation.php:159-168`; `ServiceHealth.php:394`, `408` |
| R87 | broken | A task's failure, an endpoint's 5xx and a pre-flight workflow line pause the whole workflow; an idle-plus-one-failure run pauses all gathering; our endpoint's fault is filed under the workflow | probes p1, p2 b, p3 a; live L1–L3 |
| R88 | partial | The gate logic is right (`holds.php` 24/24), but the plan ignores `whole` lines, the gate depends on stored `nd:` results, and some needs pause only their first workflow | `Operations.php:964-983`; `CrawlService.php:117`; probe p4g |
| R89 | partial | Outside services are components (an issue, a card, a hold). Our endpoints and the crawler's own tasks aren't; one source timeout gives two alarms | probe p4 a; `ControlAlarms.php:45-55` |
| R90 | needs staging | `ControlBoard::onBackup` exists; the words on the board are g4's | `ControlBoard.php:484` |
| R91 | partial | Works for site-caller back-offs and pre-flight lines. Crawler-caller back-off trips never clear by themselves; task and endpoint trips have nothing to check | probe p4 c; map rows 1, 13, 24 |
| R92 | partial | A site 2xx closes the crawler's back-off at the same host, never a refusal or a 429 in its wait. It needs a site check of that host, and the watch doesn't run one for crawler issues | `ServiceHealth.php:290-300`; `ControlAlarms.php:1042`; live L4 |
| R93 | works | `ServiceHealth::resolve` closes the issue and its alarm together, saying how | `ServiceHealth.php:307-340` |
| R94 | works | Blocked sites are held for Reactivate; a ban's probe runs 3 days and then goes to a person, never back by itself | `ServiceHealth.php:453-457`, `470` |
| R95 | missing | No module declares "fix while running" or "needs shut down" (held as 0175 R4) | step 1 §7 |
| R96 | works | The Census backup, tested | `census-backup.php` 15/15 |
| R97 | partial | Overture is a backup in `NEEDS`; paid backups go through `PaidOverride` caps. But an Overture outage isn't seen as a source (map row 5) | `Preflight.php` `NEEDS`; `crawler.py:255` |
| R98 | works | Switches a past trip left off are listed; a person's own setting is put back | `paused-now.php` 20/20; `paused.php` 26/27 (the failure is the copy's missing migration) |
| R99 | partial | The interlock names the line, but a key not reported yet is red (Q4) and a `whole` gate line holds only at Start, not in the plan | probe p5; `Operations.php:934-944` |

**Grades (35 rows):** works 13 (R85 by review), partial 15, broken 4, missing 1, needs live data 0, needs staging 2.

## 3. Findings, by harm, as card specs

Each finding names the module and its role, where it belongs in the contract (manifest field or card zone), what the
card shows and means, what happens, and how step 4's scenario test proves it.

**F1. One task's failure pauses its whole workflow, timed by the run schedule, and can't recover by itself.** This is case
#51, live twice today (L2, L3), and gathered nothing for 24 hours ("Records: 0 in").
- **Module:** each Workflow plug-in (W1–W12) and its tasks. The component at fault is the Source or Site endpoint the task
  called.
- **Contract:**
  - `incident_radius_rules`: a task failure names its component. That component is paused, its tasks reroute or wait,
    and the workflow is paused only when every task waits.
  - `sensors`: a component check run on the diagnostic clock (3 tries or 60 min).
  - `fix_it_routes`: the component's own card.
- **Card (zone 3):**
  - The task shows "Trying again (1 of 3): `loads.php` didn't answer in 120 s", against the component, never "W1: its task
    failed".
  - Zone 4: Fix it opens the component; "Pause only this task" stays.
- **What happens:**
  - The worker sends which call failed: host or endpoint, its timing, and its class (site, source, worker).
  - The site files the fault under the component, never `wf:`.
  - The next scheduled run goes ahead unless the component is paused.
- **Scenario:**
  - Fail `overture_bulk` on a `loads.php` timeout at 02:00. The endpoint's check runs within the hour. W1's coverage and
    second_look stay planned.
  - The check passes, so the alarm clears, and the next night's run goes ahead.
  - Repeat with Overture's S3 down: only `overture_bulk` waits, "the last load is kept".

**F2. Our own crawler endpoints aren't a part.**
- **The fault today:** their 5xx, slow calls and timeouts are filed under the first task's workflow
  (`procOfEndpoint`). A 5xx trip pauses W1 whole and then deadlocks: only a call to the endpoint clears it, and none
  comes while W1 is paused.
- **Module:** the Site (Host: Bluehost) with each `/api/crawl/*` endpoint as a part.
- **Contract:**
  - `role: Host` part; `sensors`: each call's code and time (built, the ledger) plus a free self-check of the endpoint;
    `incident_radius_rules`: a 5xx holds only the tasks that call that endpoint; slow holds nothing.
  - `fix_it_routes`: the endpoint's own page with its timings (the slow call, the failing call).
  - Zone 3: "`loads.php`: 3 of the last 5 calls answered 500" or "slow: 118 s for 500 rows".
- **What happens:**
  - The endpoint's own check (an authenticated GET that does no work) re-tests it on the diagnostic clock, so recovery
    doesn't need the paused workflow to call.
  - The worker's timeout reading our reply is reported as the endpoint being slow, not as the task crashing.
- **Scenario:**
  - Make `loads.php` answer 500 three times: only the three bulk tasks wait.
  - Fix it, and the endpoint check passes: Recovered, with no person.
  - Make it sleep past 120 s: a slow warning on the Site part, nothing paused.

**F3. Crawler-held back-off trips never close by themselves, and they trip on the wrong clock** (6 failures or 6 hours;
the model says 3 or 60 min).
- **The fault today:**
  - The watch rechecks only site-caller issues (`ControlAlarms.php:1042`).
  - The worker never calls a held host.
  - So a Tavily or Common Crawl trip waits for a person. L4 has been open 30 hours.
- **Module:** each Source plug-in (Tavily, Brave, Common Crawl, NPI, Wikidata…).
- **Contract:**
  - `sensors`: the source's free check, run by the site or as a light probe in the worker's next plan.
  - `incident_radius_rules`: the host's calls; tasks reroute along the priority map.
  - Zone 3: "Paused by the system since 09:12. Next check 09:42 MST".
  - Zone 4: Check and resume.
- **What happens:** while a back-off trip is open, the watch (or the next run's probe) checks it on the diagnostic clock.
  A pass closes it as Recovered, with the same thread, never "until an admin resolves it".
- **Scenario:** trip Tavily from crawler reports. With no person, the check runs within the hour. Make it answer:
  Recovered, and its tasks go back to Tavily from Brave.

**F4. A run with one failed task and the rest idle pauses all gathering, then "recovers" with no check.** It's a flap
that emails the Founder a trip and then a Recovered.
- **Module:** the crawler (Worker, hosted on Railway).
- **Contract:**
  - `incident_radius_rules`: all gathering only when the crawler itself is down, i.e. it can't plan, report or run any
    task. Never when tasks were idle.
  - `sensors`: a real worker check (F5).
  - Recovery only by that check passing.
- **Card:** the Workers zone shows the crawler "Working" while one task's component is at fault (F1).
- **What happens:**
  - `idle` counts as "worked" for the run's status.
  - A paused run's empty report never closes `run:failed`.
- **Scenario:**
  - A night run with two idle tasks and a failed bulk: no `run:failed`, and the task follows F1.
  - Then fail every task for real: the master is paused after the diagnostic tries, and stays paused through the paused
    runs until the crawler's check passes.

**F5. The worker being down is close to invisible on the board.**
- **The fault today:**
  - `rw:crawler` always passes.
  - No run for hours is Pulse-only.
  - A run killed before it reports never trips, and its data is lost.
  - The video worker has no check-in.
- **Module:** Railway (Host), with the crawler, W0 Indexer and the video worker as its services. The manifest in
  `manifests-railway-github.md` already lists them.
- **Contract:**
  - `sensors`: runs (one 35 min late, or failing), a heartbeat per service (W0's is built; the crawler's from its run
    starts; the video worker's is *spec*), and deploys per service.
  - `incident_radius_rules`: "Railway down" or "one service down" as in the manifest.
- **Card (zone 3):** "Last run 09:20, did its work". "No run for 47 min: Railway's schedule or the deploy" (red at the
  trip).
- **What happens:**
  - The watch raises silence from the last run's start (not only from runs left `running`).
  - Abandoned runs count as failures toward the trip.
  - The crawler's check needs a recent run that reported.
- **Scenario:**
  - Stop the schedule on staging: a warning within 5 minutes and a trip at the diagnostic limit, holding all gathering,
    with Fix it pointing at Railway's deploy.
  - Restart it: Recovered by the check.
  - Kill four runs at `RUN_LIMIT`: a trip, not four cleared warnings.

**F6. A Railway key not reported yet holds work** (Q4 says "not checked yet, holds nothing").
- **Module:** each Source whose key lives on Railway (Tavily, Brave, HF_TOKEN, …).
- **Contract:**
  - `required_keys` with their state: present, missing, refused, or not checked yet.
  - Only missing or refused feed `incident_radius_rules`.
- **Card (zone 3):** "TAVILY_API_KEY: not checked yet (the worker reports at its next run)", grey, not red.
- **What happens:** the gate line stays green for "unread"; Start doesn't wait on it.
- **Scenario:** clear `worker:keys` on staging. No task is held and no line is red. Report it missing: only Tavily's
  tasks reroute to Brave.

**F7. One cause gives two alarms, and some sources are blind.**
- **The fault today:**
  - A first-strike outside timeout raises a service issue and also a task failure on the workflow.
  - Overture's S3 and Hugging Face (Foursquare) aren't classified at all.
- **Module:** the Source plug-ins Overture and Foursquare, and the crawler's reporting.
- **Contract:** `sensors` cover every host a task reads (the DuckDB reads included), and a task failure carries its
  component's id, so the board shows one alarm on the component and the task's "waiting on <component>".
- **Scenario:** block S3 for `overture_bulk`: one Overture issue, the task held, W1 working.

**F8. The incident radius for needs and pre-flight lines is uneven.**
- **The fault today:**
  - Gemini ("prose") pauses W1 whole only, leaving W2, W3, W10 and W11 running on it. The same pattern holds for "writing"
    (W4 only), reread (W2) and semantic (W3).
  - A workflow's own red line pauses it whole (L1).
  - The plan ignores `whole` gate lines that Start obeys.
- **Module:** Workflow plug-ins.
- **Contract:** `incident_radius_rules` per need: the tasks or site jobs that need it, in every workflow it names, from
  one map read by both the plan and Start.
- **Scenario:** take Gemini down. Each workflow's prose step waits, with the same words in the plan and at Start. Nothing
  else is held.

**F9. The words don't follow the state.**
- **The fault today:**
  - A refused-key trip says "Nothing could be paused from here" while its calls are held.
  - A task trip says "other tasks carry on" while the workflow is paused.
  - The task trip's notice names the workflow, not the task.
  - Non-critical trips never get the Escalation Notice.
  - Pulse goes to the Director at the trip.
- **Module:** the Communications Dispatcher (Service), reading the one engine's state.
- **Contract:** zone 3's status and "What it affects" read from the pause and hold records as they are.
- **Scenario:** each map row's four channels are compared to the map's words cell by cell.

**F10. Our host's refusals never reach the board.**
- **The fault today:**
  - Bluehost's bot check (`site_blocked`, sent every run) has no reader.
  - A 406 (ModSecurity) on a crawler endpoint becomes only a task failure.
  - Retries and call timings aren't reported (R46, R64).
- **Module:** Bluehost (Host).
- **Contract:** `sensors`: bot-check refusals per run and 4xx at our endpoints. A warning past a share, a trip only if
  work stops.
- **Scenario:** answer the worker 409 twice on staging: a warning on Bluehost's card.

**F11. Check and start again on a task trip checks the whole workflow.**
- **The fault today:** unrelated red lines (keys not reported, other services) block it ("Not started: its check is
  red"). Probe p1 c.
- **Contract:** zone 4's action checks only the component that tripped.
- **Scenario:** with an unrelated red line, Check and start again on a task trip restarts that task once its own
  component passes.
