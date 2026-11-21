# Step 2, g3: the site's jobs, checks, critical duties, switches, and the Bluehost host

Audit of the live code against `step1-requirements.md` §1 (the model: 1.1 a trip, 1.2 a service in its workflows, 1.3 the
two clocks). No code or mirror was changed.

**Live code:** `degraffics/traverse-3way-sync` at `37a212e` (Sync 2026-11-21 12:26 UTC). `sync-app/` = `app/`. Citations
are `file:line` under `sync-app/lib/` unless a path is given.

**Proved by running.** Everything was run on the g3 copy, database `lv_g3` only, after a reset from
`tools/release-b-tests/start-schema.sql` each time. The files are in `step2/probes/g3/`:
- `jobs.php` → `jobs.out`: scenes S1–S12. It is the earlier g3 probe, run again fresh at 14:17 UTC, and its output matches
  the 13:01 run except for the times.
- `extra.php` → `extra.out`: scenes E1–E8, new for this audit.
- `ledger.sql` and `stages.sql`: `control_ledger`, `control_records` and `service_stages`, made from how the code uses
  them.

**Re-run after the MariaDB restart (14:27 UTC):** lv_g3 was still there. I reset it and re-ran both probes; their output is identical to the earlier runs, times and ids aside. I re-ran every test below on a freshly recreated lv_g3: the results stand, except `expire-tries` (now 9/9, which confirms the earlier miss was leftover rows) and `r4a` (now 39/39; its earlier stop came from the database's state, not the code).

Both probes copy the app to a temporary folder and stub `Mailer` and `WebPush`, so a "mail" or "push" in the output is a
call that the dispatcher really made. I also ran the existing tests on the copy: `critical` 20/20, `quiet-job` 4/4,
`sensor` 30/30, `r1` 33/33, `r2b` 14/14, `r4b` 35/35, `r4c` 9/9, `r5a` 17/17, `r5b` 17/17, `paused-now` 20/20,
`proctable` 27/27, `overpass-off` 15/15, `self-withdraw` 17/17, `workflows` 7/7, `modules` 14/14 and `keys` 30/30. Four
fail for reasons outside the code:
- `expire-tries` 8/9: the earlier test's rows were left in `control_log`.
- `paused` 26/27: its migration file isn't in the copy.
- `release-b-tests/start.php` 25/33: it predates Start's "confirm operational" step, so its fixtures now meet the real
  pre-flight.
- `register` and `defs`: they need their migrations.

---

## 1. The failure map: jobs, checks, critical duties, switches

Column notes:
- **Holds** means what is paused.
- **Tries and trip** names the clock each one runs on. *Schedule* is the run schedule; *diagnostic* is the system
  re-checking the component (model 1.3).
- **Words** gives the board, Pulse, email and push wording, in that order.

### 1a. The site's jobs (`Scheduler::JOBS`, the watch `ControlAlarms::tick` L922)

| # | Failure | Part (role) | Holds: code now | Holds: the model says | Tries & trip: code now (which clock) | Model says (which clock) | Words now (board · Pulse · email · push) | Fix it now | Recovery now | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| J1 | A site job that serves no workflow stops once (`sales_tick`, hourly) | `job:sales_tick` (a component under the Site) | Nothing. "Nothing could be paused from here (it only trips)" | That job's own work; nothing else | Warning "1 of 3 tries, until HH:MM". Its own **next scheduled runs** are counted as tries (*schedule*), but `event=false` means `n_times` never moves, so the "1 of 3" never advances. At 60 min `expireTries` trips it with **no try made**, its words saying "Its tries ran out" (*schedule mistaken for diagnostic*) | A warning while the system re-checks the component within the hour (*diagnostic*). A job has no check to re-run, so nothing tries. The next run on its *schedule* isn't a try | Board: warning, then trip. Pulse, email and push at the trip: **"SYSTEM ALARM NOTICE: Lead scores: Paused by the system"**, though nothing is paused. After a 2nd failure the words say "(2 in a row)" next to "1 of 3 tries" | None (`fixOf` has no job cause). The button "Its job" peeks `/admin/config/scheduler#job-sales_tick` | Its next good run: "Recovered: Lead scores was down 61 min, the system fixed it" | **differs** | ControlAlarms.php:957 (`event=false`, `tryFirst = fails<max`), :107, :897-919; jobs.out S1, S12 |
| J2 | A job tied to a workflow stops 3 runs in a row (`listings_tick` → W1) | `job:listings_tick` | **All of W1** ("W1 (its tasks wait with it)"), so every W1 crawler task waits | Only that job's work (matching and publishing waiting listings). W1's crawler tasks don't depend on it | A trip at once on the 3rd failure (`fails ≥ 3` turns tryFirst off), counting scheduled runs (*schedule*) | Component warning on the diagnostic clock, then a pause on the job alone | Email, push and Pulse: "SYSTEM ALARM NOTICE: **job:listings_tick**: Paused by the system". The part's name is the raw id, because a workflow's jobs aren't parts (ControlRegister.php:164) | None. The button "Its job" | **A false recovery.** With W1 paused, the job's next run skips and is recorded `ok=1 "Paused: …"` (Scheduler.php:119). The watch reads that as a good run: "Recovered: job:listings_tick was down 1 min, the system fixed it". W1 resumes, the job fails again, and the trip comes back: a loop that emails the Founder each round | **differs (broken)** | ControlAlarms.php:279-281, :961; Scheduler.php:113, :119; Automation.php:372-381; jobs.out S2 |
| J3 | A job tied to three workflows stops (`guide_finds_tick` → W4, W8, W10) | `job:guide_finds_tick` | **W4 only** (`$wfs[0]`). W8 and W10 run on, and so does the job itself (`Automation::job` lets it run while any of its workflows is on) | The job's work only | As J2 (*schedule*) | As J2 | As J2 | None | As J2. The job keeps running and failing while W4 sits paused for no reason | **differs** | ControlAlarms.php:279-281; Automation.php:51; jobs.out S3 |
| J4 | A job stops, then no run comes for over 2 days (its cron line gone, or the host's cron down) | `job:<job>` | — | — | — | Still a fault: the job has neither run well nor been retired | Closes as **"Its last run ended well (HH:MM UTC)"**. The watch only reads runs from the last 2 days, so the job drops out of the list and `clearOthers` closes its alarm | — | **A false clear** | **differs** | ControlAlarms.php:950 (`INTERVAL 2 DAY`), :961; jobs.out S4 |
| J5 | **A run never finishes** (killed or hung). Live L7: `identity_recheck_tick` (W2), started 2026-11-20 13:15 UTC | `job:identity_recheck_tick` | Nothing | The recheck's work (W2's `recheck` task, its job) is Paused by the system once the hour's re-checks fail | A warning of kind **`slow`**, so it **can never trip** (`raise()` turns slow trips into warnings, L89). `event=false`, so n=1 forever. No escalation, and no clock of any kind | A fault, not slowness: a warning on the *diagnostic* clock, then Paused by the system and the Founder told once (1.1) | Board only: "The job "identity_recheck_tick" never finished · …it was killed, or it hangs". The part's name is the raw id, "What it affects" is empty, and nothing says whether it is running now. Nobody is told | None. "Its job" | **A false clear** 2 days after it started: "Its last run ended well" while `finished_at` is still NULL (E1). The logbook keeps that wrong story | **differs** | ControlAlarms.php:89, :959, :950, :961; extra.out E1 |
| J6 | **A critical job is stuck** (`billing_tick`, duty: payments) | `job:billing_tick` (critical: payments) | Nothing | A critical part's first failure trips at once, while the system tries | A warning. The critical rule only raises a warning of kind retry, crash or silent (L100), and a stuck job is kind `slow` | A trip at once, the Founder told (1.1) | Board only, nobody told. Renewals could stop with no one knowing | None | As J5 (false clear after 2 days) | **differs** | ControlAlarms.php:100, :959; ControlRegister.php:95; extra.out E7; jobs.out S6 |
| J7 | **A late job**: scheduled but it didn't start (`sales_tick`, last run 6 h ago; its late limit is 180 min) | `job:sales_tick` | Nothing | A fault on the component (the cron line or the host's cron): warning, then a pause on its work | **Nothing raised.** The watch's job section only reads failed and unfinished runs; it has no lateness check. Lateness exists only as red text on Scheduled tasks (admin/sections.php:596) and in the audit's D03 in Pulse Site health (GitHub, daily) | A warning on the *diagnostic* clock (R44: "a scheduled job that didn't start") | — | — | — | **missing** | ControlAlarms.php:947-962; admin/sections.php:596; jobs.out S5 |
| J8 | **A silent job**: one that never ran, or **the watch itself** (`control_tick`) stopped | `job:control_tick` (critical: procedures) | Nothing | The watch down is a critical duty down: a trip at once | **Nothing.** The watch is the only thing that raises silence, so a dead watch raises nothing, anywhere on the site. The `site:jobs` line reads "None stopped in the last day" (it counts failures only). `billing_tick` isn't in `Scheduler::AUTO`, so `site:sched` doesn't check its cron line either | A trip at once, through something outside the watch (the health report's alarms block, or a GitHub check) | — | — | — | **missing** | Scheduler.php:69, :103; Preflight.php:770, :773-776; extra.out E8; jobs.out S5 |
| J9 | **A quiet job's silence** (`releases_tick` is in `QUIET`): no look in git for 30 min | `job:releases_tick` (critical: procedures) | Nothing ("nothing is lost") | Releases (builds wait in the inbox). Critical: a trip at once | A warning `rel:job:quiet` of kind `check`. No critical bump (`check` isn't retry, crash or silent), so it stays a warning. A look that GitHub didn't answer (`found: -1`) raises nothing at all (S11; the GitHub manifest covers that case) | A trip at once, held = releases (Railway/GitHub manifest) | Board only. Nobody told | "Releases (Check for builds)" and "Its job", as peeks | When it looks again, it clears | **differs** | ControlAlarms.php:988-992, :100; Scheduler.php:168; jobs.out S11 |
| J10 | A job is slow: its last run took over 3× its usual time (and over a minute) | `jobslow:<job>` | Nothing | Nothing (slow is never a trip) | A warning that never trips | A warning that clears itself | Board only: "The job … is slow · Its last run took N s against its usual M s" | "Its job" | It clears when the job takes its usual time again | **matches** | ControlAlarms.php:963-971 |
| J11 | A job runs, but its own record of the run failed (the database refused the insert) | — | Nothing | A fault on the database part | The job runs with no record (`return` at L103), so it shows up later as J7 or J8 | The database's own critical check catches it | — | — | — | **missing** (falls into J7/J8) | Scheduler.php:97-103 |
| J12 | **A critical job stops once** (`billing_tick`, daily) | `job:billing_tick` (critical: payments) | Nothing at first. After escalation, still nothing (not tied to a workflow) | A trip at once while the system tries; Paused by the system when the tries run out | A trip at once with "retrying" (good): Pulse, email and push. **At 60 min it escalates** ("Retries unsuccessful, now admin required"), though **no retry was made**: the next run is 24 h away (*schedule*) | Trip at once ✓. Tries are re-checks within the hour (*diagnostic*). With no check to run, the Escalation Notice can't truthfully say retries failed | Email: "SYSTEM ALARM NOTICE: Plans and renewals: Paused by the system", then "…: Retries unsuccessful, now admin required". Push: 2. Nothing is paused, though the words say "Paused" | None | Its next good run → "Recovered … the system fixed it" | **differs** (escalation clock and words; the at-once trip matches) | ControlAlarms.php:100-114, :897-911; jobs.out S7 |

### 1b. Checks failing (pre-flight, Diagnostics, Start's testing, the switch-on check, the duty sensors)

| # | Failure | Part (role) | Holds: code now | Holds: the model says | Tries & trip: code now (which clock) | Model says (which clock) | Words now (board · Pulse · email · push) | Fix it now | Recovery now | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| C1 | A required pre-flight line goes red on a component that isn't critical (`svc:tavily`, its use on) | `svc:tavily` (Source) | **Nothing** (`stop()` has no branch for a service; only issues and caps place a pause) | The component's calls. Its tasks reroute, or wait if nothing else covers them (1.2) | Warning "1 of 3". **Nothing re-tests it inside the hour**: the watch re-runs only lines untested for 6 h, and then for their words only. At 60 min it trips "Its tries ran out", with **zero tries** (*diagnostic* clock, but no checks made) | Re-check the component within the hour (*diagnostic*), then pause what it touches | At the trip, email, push and Pulse: "SYSTEM ALARM NOTICE: Tavily: Paused by the system", **with nothing paused**. The button reads "Its line on the **pre-flight**" for an active module (R57 says that's Diagnostics) | The line's own fix, "Its line on the pre-flight", and Check | Its line passes → "Recovered: Tavily was down 61 min, the system fixed it", even when a person replaced the key (the words credit the system) | **differs** | Preflight.php:332, :337, :351; ControlAlarms.php:244-290, :1017-1024; jobs.out S9 |
| C2 | A workflow's own pre-flight line goes red on a part that isn't one of its needs (live L1, `pf:wf:W3`) | `wf:W3` | **The whole workflow** (`stop()`'s `wf:` branch) | Only the tasks that can't reroute | As C1 | As C1 | "W3 won't start until this is fixed; the rest of the system runs." (Live: its words and its hold disagree, L1/L10) | As C1 | As C1 | **differs** (the need path was narrowed by #247; a `wf:` line's own red part still pauses the workflow whole) | ControlAlarms.php:283-285; Preflight.php:303-311 |
| C3 | **A critical site line goes red with the master switch OFF** (`site:db`, `site:sched`) | `site:db` (critical: database), `site:sched` (critical: procedures) | Nothing | A critical part trips at once, whatever the master says: the master stops gathering, never business duties | **A warning.** `$on` needs the master on, so the level is "warning", and kind `check` gets no critical bump | A trip at once (1.1) | Board only: "Part of the site itself isn't ready … Nothing switched on needs it yet: Start holds it until it passes." **Nobody told**, even with a database down | The line's fix | The line passes | **differs (harm)** | Preflight.php:337, :351; ControlAlarms.php:100; jobs.out S8; extra.out E5 |
| C4 | A critical site line goes red with the master ON | `site:db` | Nothing | A trip at once while the system tries | A trip at once with "retrying": Pulse, email and push (good). It escalates at 60 min, though nothing re-tested the line (only the 2 AM run, a .env change, Start, or the 6-hour words-only refresh) (*schedule* stands in for *diagnostic*) | Re-checks within the hour (*diagnostic*) | "SYSTEM ALARM NOTICE: The databases: Paused by the system", then "Retries unsuccessful" | The line's fix | The line passes → Recovered | **differs** (tries clock) | Preflight.php:351; ControlAlarms.php:897-911, :1004-1015; jobs.out S8 |
| C5 | **Bluehost's line goes red** because cPanel's API didn't answer (email fine, pages fine) | `svc:bluehost` (Host; **its duty is "page"**) | Nothing | cPanel's API only: setting up jobs and mailboxes. A warning, never a page-load trip | **A critical trip at once, labelled "Page load"**. Escalated at 60 min with n=1 and no re-check | A warning on the scheduler-API component (*diagnostic*); pages aren't involved | The title is **the part that passed**: "Bluehost: The site's email: signed in." Means: "What needs this company waits; everything else runs. A critical part (Page load): you're told at once…". Email and push: "SYSTEM ALARM: Bluehost · Paused by the system". The board light says "Tripped: stopped until an admin resolves it." (false: it closes as Recovered) | None mapped. The line's fix | The line passes → Recovered | **differs (harm: false page-down alarm, wrong title)** | ControlRegister.php:89; Preflight.php:975-987, :325; ControlBoard.php:186; extra.out E2 |
| C6 | Diagnostics on an active module: the watch's re-test of a line not tested in 6 h | any `pf:` line | Never changes the level (`event=false`) | — | Words only | A check that passes lifts its pause; a failing one counts as a try | Words updated in place (good) | — | It closes if the line passes | **partial** (re-tests every 6 h, not within the hour, and never counts as a try) | ControlAlarms.php:1004-1015; Preflight.php:342-345 |
| C7 | Start testing: a dependency is stale or red at Start (`refreshFor`) | Start-up stage | That item is held, with its answer and fix; the rest go on | The same | Tested fresh before each item, inside Start's time budget | The same | Board warning "Starting the system: …" (`ops:start:*`), no Pulse (it waits for a person) | "Open it", plus its console | Re-tested at each look until green | **matches** | SystemStart.php:299-318; Operations.php:627-640; `r1` 33/33 |
| C8 | **Start's watch: a switched-on item's gate fails** (failures over 1 in 10, a new service issue, out of budget) | `wf:Wn` / `t:x` | **Writes the person's switch**: `crawl_workflows.paused = 1` by "Start the system", shown as "**set by Founder**" (it joins on the presser's uid). No `system_pauses` row | Paused by the system (a pause of its own), never a write into a switch (1.1) | Judged at each 10-minute look (*schedule*) | A trip: a pause placed, the Founder told once | Board warning `ops:start:*` only. No trip, no email, no push. Paused now reads it as a person's switch | "Look again" | "Carried on" only when the cause was a service issue that no longer holds; otherwise a person switches it on | **differs** | SystemStart.php:365-372, :515-521; Automation.php:296-298; extra.out E3 |
| C9 | A red line in the switch-on check | Start-up stage | Its scope only (`all` holds the master; a hub line holds W14 only) | The same (R60) | At each look (*schedule*) | The same | Board warning per line, with its fix | The line's fix, as a tool or a link | It clears when the line goes green | **matches** | SystemStart.php:263-283 |
| C10 | **The search sensor's test fails** (or ≥3 server errors in 5 min on search's paths) | `site:search` (critical: search) | Nothing | A trip at once while the system tries | A trip at once, retrying; each 5-minute probe is a try, and it **escalates on the 3rd failed watch** (*diagnostic* ✓) | The same | Pulse, email and push at once; the Escalation Notice once; Recovered as a notice | The failing path (peek) and Bluehost's error log (link) | A good probe → "Recovered: Search was down N min, the system fixed it" | **matches** | ControlSensor.php:192-212; jobs.out S10; `sensor` 30/30 |
| C11 | The sign-in sensor's test fails (session store or accounts table), or ≥3 errors on its paths | `site:signin` (critical: procedures) | Nothing | As C10 | As C10 | As C10 | As C10 | The sign-in page (peek) and the error log | As C10 | **matches** | ControlSensor.php:100-115, :192 |
| C12 | **QuickBooks needs reconnecting** (refused, or its token ran out) | `site:payments` (critical: payments) | Nothing (checkout can't take a card) | Waits for the person's Reconnect; its check then closes it | A trip at once (`held`, nothing tried) | The same | "SYSTEM ALARM NOTICE: Checkout and payments: Paused by the system". It closes as **"Recovered: … the system fixed it"**, though a person reconnected | "Connect QuickBooks again" (peek), Intuit (link), Check it now | Its next probe passes | **matches** (hold, clock) / words differ | ControlSensor.php:178-184; jobs.out S10 |
| C13 | **Page load has no sensor** | `svc:bluehost`, `site:env` (critical: page) | — | A trip at once | **Nothing watches whether pages load.** If the site is down, the watch can't run, so nothing raises, emails or pushes. Only GitHub's daily probe (`search-probe.yml`, 12:47 UTC fallback) writes `alarms.json` | A heartbeat from outside the host (the contract's Host sensor) | — | — | — | **missing** | ControlSensor.php:23-31 (search, sign-in and payments only); traversence `.github/workflows/search-probe.yml:11` |
| C14 | One database realm is refused mid-day (admin, listings or users) | `site:db` (critical: database) | Nothing | A trip at once | Seen only by `site:db` in the pre-flight (nightly, a .env change, Start, the 6-hour words refresh). Visitors' 5xx errors trip search, sign-in or payments instead, under the wrong part | A check on the diagnostic clock, every watch | — | — | — | **missing** (no watch-level DB check) | Preflight.php:735-743; ControlAlarms.php:1004-1015 |
| C15 | **Pulse's clock at a trip** | any trip | — | — | `tripped()` and `trippedEarly()` set the Pulse item's escalation to **level 2 (the Director) at once** | The Founder at once, then Pulse's clock: the Manager at 2 h, the Director at 4 h (R76) | Pulse: "Escalated to the Director" at the minute of the trip | — | — | **differs** | ControlAlarms.php:226, :566; Pulse.php:1026-1033 |

### 1c. The critical duties (`ControlRegister::DUTIES` L76, `CRITICAL` L88)

| # | Failure | Part (role) | Holds: code now | Holds: the model says | Tries & trip: code now (which clock) | Model says (which clock) | Words now (board · Pulse · email · push) | Fix it now | Recovery now | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| D1 | **Early trip**: a critical part's first failure, of kind retry, crash or silent | any duty part | Nothing yet | Nothing yet; the system keeps trying | `trippedEarly`: a trip at once, `retrying_at` set | The same (1.1) | Status: "Tripped at once … a critical part. The system is still trying". Subject: "…: **Paused by the system**", while nothing is paused (it should say "Tripped at once" or "Down: retrying") | Per part | — | **matches** (level and clock) / words differ | ControlAlarms.php:100-114, :562-570 |
| D2 | **A critical part fails, but the alarm's kind isn't retry, crash or silent** (`slow` for stuck, `check` for pre-flight lines and quiet jobs) | `job:billing_tick`, `job:releases_tick`, `site:*` with the master off | Nothing | A trip at once | A warning: the critical rule depends on the alarm's kind, not on the fault | Any failure of a critical part (1.1) | Board only | — | — | **differs** | ControlAlarms.php:100; rows J6, J9, C3 |
| D3 | **Still trying**: the early trip's tries run out | any duty part | `escalated()` calls `stop()` (which pauses nothing for a site, job or service part) | Paused by the system on what its cause touches | Tries are `n_times` (when an event repeats: the sensors) or the 60-minute expiry (jobs, pre-flight lines) whether or not any try happened | Real re-checks (*diagnostic*) | — | — | — | **differs** for jobs and lines, **matches** for the sensors | ControlAlarms.php:117, :897-911 |
| D4 | **The Escalation Notice** | any part whose tries ran out | — | — | Sent once, on the same thread (one push for the level rise), and logged | The same | "SYSTEM ALARM NOTICE: <part>: Retries unsuccessful, now admin required"; status "Escalation Notice …: retries unsuccessful, an admin is needed" | — | — | **matches** (shape and once-only) | ControlAlarms.php:576-591; jobs.out S7 (msgs `alarm.escalated` times=2), S10 |
| D5 | **Recovered**, after an early trip or after escalation | any | Lifts what it paused (`sysResume`) | The same | When its check passes | The same | "Recovered: <part> was down N min, the system fixed it." A notice, no push (push n stays 2 in S7) | — | Closes the trip, its pause and its issue | **matches**. Two exceptions: J2/J4/J5 (the "check" that passed wasn't real), and C1/C12 (it credits the system for a person's fix) | ControlAlarms.php:150-172, :593-606; jobs.out S7, S10 |
| D6 | **A duty is mapped to a part whose check tests something else** | `svc:bluehost` → page (its test is email and cPanel's API); `site:env` → page (its test is "readable **and writable**": unwritable fails it, yet pages still load) | — | — | A trip at once on a page-load duty that isn't down | Duty = the part whose failure stops the duty | False "Page load" critical trips | — | — | **differs** | ControlRegister.php:89-90; Preflight.php:744-747, :975-987; extra.out E2 |

### 1d. The master switch, start-up, shut-down, maintenance

| # | Failure / act | Part (role) | Holds: code now | Holds: the model says | Tries & trip: code now (which clock) | Model says (which clock) | Words now (board · Pulse · email · push) | Fix it now | Recovery now | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| M1 | The master OFF | master | Gathering jobs skip (listings, identity recheck, guide finds). Business jobs run (billing, releases, mail, the watch) | The same (R141) | — | — | A skip is recorded as a good run, "Paused: …" | — | — | **matches** (but see C3: the master off also silences critical site lines) | Automation.php:372-381; extra.out E5 |
| M2 | Pause everything | master | The master off, plus Railway's schedule when its keys are there | The same (R148) | — | — | Logged with who | — | Start the system | **matches** | SystemStart.php:189-200 |
| M3 | **Shut down the whole system** | master, workflows, tasks | Writes every switch off in reverse order, then the master, then Railway; "stopping" → "stopped, safe" once no crawler run is going | Reverse order ✓; **names what depends on each before it stops**; leases handed back; W0's batches and site jobs waited for | — | — | "stopped, safe … every switch off, with who and why" | — | Start the system | **partial**: nothing names dependants; leases aren't returned; `settle()` waits on crawler runs only | ControlBoard.php:625-663; extra.out E6; `r2b` 14/14 |
| M4 | Take out (maintenance): a workflow or a task | `wf:`, `t:` | Stops it in order (writes its switches as the person, which is right for a person's act). Blue; its alarms become notices; Start skips it | The same | A window set ahead goes out and comes back by itself. A late start is logged with how late; a missed window is logged | The same | Logbook lines, e.g. "It went out 40 minutes late…" | — | Put back runs its check first. A red check keeps it out, with a warning `maint:back:*` | **matches** | ControlMaintenance.php:79-169; extra.out E4 |
| M5 | **Maintenance for a module** (a service, a host, a job, a worker, the site) | `svc:railway`, `svc:bluehost`, `job:*`, `wk:*`, `site:*` | Can't be taken out: `canTake()` allows only workflows and tasks with a switch | "Needs shut down" repair (the battery) is Maintenance for any module (1.2, R95) | — | — | — | — | — | **missing** | ControlMaintenance.php:29-34; extra.out E4 |

**Counts (§1, 38 rows).** matches 12 · differs 18 · partial 2 · missing 6; plus the Incident Report, missing (§4). By section: jobs 1 match, 8 differ, 3 missing; checks 5 match, 7 differ, 1 partial, 2 missing; duties 3 match, 3 differ; switches 3 match, 1 partial, 1 missing. C12, D1 and D5 match on hold and clock but carry word faults.

---

## 2. Requirements graded

### Area 2 (R27–R39)

| R# | Grade | What the code does | Evidence |
|---|---|---|---|
| R27 | partial | The register reads the declared lists: workflows, jobs, Start's order, needs, services, crawler tasks. But a workflow's site jobs aren't parts, so their alarms carry raw ids ("job:identity_recheck_tick") with no row, light or name | ControlRegister.php:152-168 (L164); `r1` 33/33; extra.out E1 (`in processes(): false`) |
| R28 | works | `rt:claimed` lists unclaimed endpoints, and the zip check's blocking rule R15 refuses a worker endpoint no process names | ControlRegister.php:224; tools/page-audit/audit.py:34, :556; zipcheck.py:312 |
| R29 | partial | Duties and their defaults exist; changes are logged (`critical` 20/20). Not critical: crawler, W0, video worker, Railway. GitHub is critical through releases. But `svc:bluehost` and `site:env` carry "page" while their checks test other things (D6), and page load has no sensor (C13) | ControlRegister.php:76-121; Preflight.php:744-747, :975-987 |
| R30 | partial | `site:search`, `site:signin`, `site:payments` and `site:pulse` exist with their duties. Communications isn't a system: there are four systems and no process per channel | ControlRegister.php:25-30, :166 |
| R31 | partial | 0173's seven service stages (proposed … removed), not Jason's eight. Jobs, hosts and workers have no stage (0175 R3 held) | ServiceStages.php:11-18 |
| R32 | missing | There is no eight-stage strip and no single stage text shown everywhere (R3 held) | ServiceStages.php; step1 §7 |
| R33 | partial | Stage 2 (pre-flight), 3 (the switch-on check), 5 (the board), 6 (workflows and tasks only) and 7–8 (as Retired/Removed for services) exist. Deactivation as a stage doesn't | ControlMaintenance.php:29-34; ServiceStages.php:17-18 |
| R34 | partial | Pause by the system (`sysPause`) and by a person, Activate (`startOne`), Delete = Retire with Bring it back; who is recorded. There is no Deactivate action. **The system also writes into a person's switch** at Start's watch (C8), so "system code can call only Pause" fails | SystemStart.php:515-521; ControlBoard.php:545-600; extra.out E3 |
| R35 | needs staging | `modules` 14/14 passes. `register` and `defs` need their migrations (`service_stages`, `service_proposals`) | ServiceStages.php:143-151 |
| R36 | needs staging | `Operations::ADDED_OFF` and `decide()` exist; a release can't be proved on the copy | ServiceStages.php:59 |
| R37 | works | Overpass is the first Retired record: nothing calls it, its alarms are settled, and its record is kept (`overpass-off` 15/15) | ServiceStages.php:17-18, :134 |
| R38 | missing | No standalone/integration split or touch points (R4 held) | step1 §7 |
| R39 | missing | No `kind`, `repair`, `fallback` or `touch_points` in `service-defs.php`, and no zip-check refusal for them (R4 held) | service-defs.php:83-89 |

### Area 9 (R140–R156)

| R# | Grade | What the code does | Evidence |
|---|---|---|---|
| R140 | works | Four layers plus the system's pause: `gathering()` asks master → workflow → service → sysAny; tasks go through the crawler's plan | Automation.php:354-362 |
| R141 | works | The master off skips only `Automation::JOBS` and gathering; billing, releases, mail and the watch run (E5). (The side effect on critical lines is graded at R72 / C3) | Automation.php:372-381; extra.out E5 |
| R142 | works | A shared job skips only when all its workflows are paused; W5's visitor note; Pause is greyed on business services (`NOT_PAUSABLE`) | Automation.php:51-60, :75-99, :401 |
| R143 | partial | A pause needs a why, and it's logged with who. But Start's watch writes switches attributed to whoever pressed Start ("set by Founder"), and a trip's job pauses read as "Paused by the system" when nothing was paused (J1, C1) | Automation.php:406-427; extra.out E3 |
| R144 | works | A task's switch is on its workflow's page; the card's Switch on uses the same switch (`workflows` 7/7, `task-on` exists) | tools/control-board-tests/workflows.php |
| R145 | works | The board's Check, Check and start again, and readings are kept; `startOne` and `stopOne` call `Automation` | ControlBoard.php:545-600 |
| R146 | partial | One button, a 10-minute tick, refused on staging, confirm before switching on, order, gates. **A misbehaving item is "stopped alone" by writing its switch, with a board warning only** (C8). `start.php` 25/33 (stale fixtures) | SystemStart.php:157-187, :213-224, :365-372; extra.out E3 |
| R147 | partial | Reverse order, "stopping" → "stopped, safe" with what it left (`r2b` 14/14, E6). It doesn't name dependants before stopping, doesn't return leases, and doesn't wait for W0 or site jobs | ControlBoard.php:625-663 |
| R148 | works | Pause everything is kept as the emergency stop | SystemStart.php:189-200 |
| R149 | partial | A row's Shut down is logged as "stop … shut down" with who, not as Deactivate | ControlBoard.php:574-600 |
| R150 | partial | Workflows and tasks: blue, notices, Start skips, Put back checks first, windows ahead, late and missed logged (E4). **Modules can't be taken out** (M5) | ControlMaintenance.php:29-34, :98-169; extra.out E4 |
| R151 | works | `upkeep()` reads the night window, the jobs and the nightly pre-flight; `r4a` 39/39 on a clean lv_g3 (maintenance, windows ahead, the upkeep calendar) | ControlMaintenance.php:193-266; tools/control-board-tests/r4a.php |
| R152 | works | Start and Shut down are refused on staging | SystemStart.php:157-161; ControlBoard.php:629 |
| R153 | works | `Clocks::ramp`: half budget for 14 days, applied on resume | Clocks.php:101; Automation.php:429-436 |
| R154 | needs staging | `WorkflowSettings.php` versions; not exercised here | WorkflowSettings.php |
| R155 | partial | The recheck is W2's task, shipped off, with its job tied to W2. But its job has no part on the board: the live L7 alarm reads "job:identity_recheck_tick", affects nothing, and can't escalate | Automation.php:58; ControlRegister.php:164; extra.out E1 |
| R156 | works | A failing build withdraws itself (`self-withdraw` 17/17); the Releases page refreshes | tools/control-board-tests/self-withdraw.php |

### Area 11 (R167–R175)

| R# | Grade | What the code does | Evidence |
|---|---|---|---|
| R167 | works | Readings (`control_readings`), alarms, stage changes (`service_stages`, `automation_log`) and controls (`control_log`) are all kept | ControlHistorian.php:24-28; ControlLog.php:15-28; `r4b` 35/35 |
| R168 | works | Written on a change and hourly; 30 days raw, then daily rows kept 400 days | ControlHistorian.php:1-12; `r4b` |
| R169 | works | Trends for 24 h, 7 d and 30 d as inline SVGs, with "No history yet: readings start now". Note: `hold` counts as up | ControlHistorian.php:19-22, :219-302 |
| R170 | works | The logbook reads `control_log` and the existing logs, with who as "the system" or staff and the time zone kept (`r5a` 17/17) | ControlLog.php:1-40 |
| R171 | partial | A closed thread writes its story to the logbook (`Thread::text(..., 'logbook')`), but the story holds only the last status: **no record of each check or try** (none is kept), and a false clear writes a false story (E1) | ControlAlarms.php:180-191; extra.out E1 |
| R172 | works | Reports by window, in sections, with CSV and download, nothing private (`r5b` 17/17). The day's report daily and the week's on Monday, inside the daily report | ControlReport.php:6, :74-165; DailyReport.php:164-169 |
| R173 | missing | **No Incident Report.** `grep "Incident Report\|incident_report\|'IR-"` over `sync-app/` and `sync-storage/` finds nothing (the only "incident" hits are Roads511 and the legal text) | §4 below |
| R174 | missing | There's nothing to link | — |
| R175 | partial | Uptime per process in the historian and in every report. The board's own page is outside this audit | ControlReport.php:74-87 |

**Counts (39 rows).** works 17 · partial 14 · missing 5 · needs staging 3 · broken 0 · needs live data 0. Area 2: works 2, partial 6, missing 3, needs staging 2. Area 9: works 10, partial 6, needs staging 1. Area 11: works 5, partial 2, missing 2.

---

## 3. The Bluehost host, as a module (to the contract, as Railway and GitHub were done)

### Manifest

| Field | Value (from the live code) |
|---|---|
| id · name | `bluehost` · Bluehost |
| role | **Host**: runs our site: pages, endpoints, the database, `.env`, the scheduler, the site's own email |
| criticality | **Critical**, carrying three duties: **page** (the web server, PHP, the router, `.env`), **database** (its MySQL: the main database plus the admin, listings and users realms), **procedures** (cPanel's cron, which runs every site job and the board's watch, and the site's own email for sign-in links). Today only `svc:bluehost` → page and `site:env` → page are mapped, and both map to checks that test other things (D6) |
| plan · spend | A flat hosting plan. `BLUEHOST_USD_MONTH` (the setting "Bluehost plan, a month") goes into the monthly total. No usage to read: a fixed price, so "flat plan, $x a month" |
| stage · state | Operational · Working (the live pre-flight's state needs live data) |
| what it hosts | traversence.com (`website/`, `app/` above the web root); staging.trvgo.us (the same account; Scheduler.php:161); the trvgo.us router (`trvgo/`); four databases (Database.php:142, Preflight.php:735-743); `.env` above `public_html` (Database.php:64-81); **50 cron jobs** in `Scheduler::JOBS`, 40 of them in `AUTO` (the ones that set themselves up); mailboxes (CPanelMail); SMTP |
| deploys from | Releases: git inbox → staging → publish (Releases, `releases_tick`), mirrored by the 3-way sync over FTP (GitHub-hosted) |
| required keys | `.env`: `DB_*` for each realm, `CPANEL_HOST`, `CPANEL_USER`, `CPANEL_TOKEN`, `STAFF_EMAIL_DOMAIN`, `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `CRAWLER_API_TOKEN` and `VIDEO_WORKER_TOKEN` (the workers' way in), `HEALTH_TOKEN` (must match GitHub's). Names only |
| caps | None metered. The server's limits (process kill, max execution) show only as stuck jobs (J5) |
| sensors (built) | **Pre-flight:** `site:db`, `site:env`, `site:envdup`, `site:dbup`, `site:sched` (cPanel's cron list against `AUTO`), `site:jobs` (failures in the last day), `site:token`, `site:health`, `site:state`, `site:php`, `svc:bluehost` (SMTP sign-in plus cPanel's API). These run nightly, on a `.env` change and at Start, plus the watch's 6-hour words refresh. **The watch (every 5 min):** job runs that failed, stuck or slow; the release job's look; the duty sensors for search, sign-in and payments (a test plus the server-error hook in Database.php:284-296); Pulse's gather time; the query timings (`ms:*`, at Start and in the pre-flight). **Outside:** GitHub's daily health report (`api/health.php` with `HEALTH_TOKEN` → `alarms.json`, `server.json`), the site audit's checks |
| sensors (*spec*) | **Heartbeat from outside**: page load, and "the watch ran in the last 10 min", read by GitHub (free minutes). **Job lateness** for every job in `JOBS` except `QUIET` and `RETIRED`. **The cron line of each critical job** (`billing_tick` isn't in `AUTO`, so nothing checks its line). **A database check each watch.** The **bot check** (Bluehost's `humans_` 409 on the worker endpoints; research/bluehost-bot-check-2026-11-15.md) as a refusal on the Site endpoint part |
| feeds | Every visitor duty; the workers' endpoints (`/api/crawl/*`, `/api/video/*`), which every Railway service needs to store anything; Pulse and the board themselves; releases' publish step; the sync's FTP side |
| fix_it routes | `.env` → cPanel → File Manager (`https://my.bluehost.com/hosting/app#/cpanel`, Preflight.php:124). A job → Scheduled tasks `#job-<job>`. A failed migration → Database updates. The cron line → Scheduled tasks' "Set them up now". Email → cPanel's Email accounts. The error log → Bluehost's account (today only `https://my.bluehost.com/`; *spec*: the account's cPanel Errors page). The bot check → a request to Bluehost (a person's step, with the paths from the research) |
| actions | Check now (re-run the `site:*` lines); Set them up now (cron); Run database updates now; Take out (*spec*: it can't be today, M5) |

### Four zones

1. **Identity and role:** Bluehost · Host · Critical (page, database, procedures) · flat plan, $x a month · Operational ·
   its state.
2. **What it hosts and feeds:** the site (pages, endpoints), staging, the trvgo.us router, the four databases, `.env`,
   the scheduler (50 jobs: on time / late / stopped / stuck, each a line), email, and what each feeds.
3. **Sensors and keys:** the heartbeat (*spec*), each `site:*` line with when it was last tested, the duty sensors, the
   watch's last run, each critical job's last run and its cron line, the bot check, and the keys by name.
4. **Actions and Fix it:** the actions above, and one Fix it for the problem that's open.

### Incident Radius rules

| Fault | Held | Unaffected | Today |
|---|---|---|---|
| **Bluehost down** (pages don't answer) | Everything it hosts: pages, search, sign-in, payments, the admin, Pulse, the board, every site job, staging, trvgo.us. Workers' calls fail, so nothing is stored; Railway runs back off | Railway services keep running on their current version (their writes wait); GitHub (releases wait in the inbox; the mirror keeps its last copy); OneDrive; everything already stored | **Nothing raised or sent.** The board lives on the host. Only the daily GitHub probe (*spec*: a heartbeat outside the host, the IR opened on its side and finished when the site is back, from the gap in `control_readings` and `job_runs`) |
| One database realm refused | What reads that realm (listings → search and listing pages; users → sign-in; admin → orders and the admin) | The other realms' pages; the workers' calls to other tables | Seen only at the next pre-flight run (C14); its 5xx errors trip search, sign-in or payments instead |
| `.env` unreadable | Every page (the page duty) | — | `site:env` check (nightly) |
| `.env` unwritable (readable) | Only Settings and Its key writes (R158); pages load | Everything else | **Trips "Page load"** (D6) |
| cPanel's API down | Only setting up or changing cron lines and mailboxes. Jobs keep running on their existing lines | Everything else; a warning | **Two critical trips** (`svc:bluehost` page, `site:sched` procedures) (C5) |
| Cron not running (no job starts) | Every site job, including the watch: no alarms, releases, renewals, mail, held email, Pulse follow-ups | Pages, search, sign-in, checkout (but not renewals) | **Nothing** (J8) |
| One job fails | That job's own work (its outputs wait) | Its workflow's crawler tasks; every other job | Pauses its first workflow whole, then recovers falsely (J2, J3) |
| A critical job fails (`billing_tick`, `releases_tick`) | Its duty's work: renewals, or releases | Everything else | Trips at once ✓ / quiet is a warning only (J9) |
| Site email (SMTP) refused | Nothing while SES answers (the `siteemail` need: Bluehost, then AWS; Preflight.php:86): "On a backup" | Everything | `svc:bluehost` red, combined with cPanel's API into one line |
| Bluehost's bot check refuses a worker endpoint | What that endpoint carries; it's a refusal (a person asks Bluehost to exempt it), never retried by the system | Pages and the other endpoints | Not a sensor; found by the research of 2026-11-15 |
| Our endpoint slow (IR-0001's `loads.php`) | Nothing: a warning on the Site endpoint part | Railway, the sources, the tasks' next runs | Belongs to the crawler-task audit (another reader) |

---

## 4. The Incident Report

**Is it built?** No. Nothing writes, numbers or links one: `grep -rn "Incident Report\|incident_report\|'IR-"` over
`sync-app/` and `sync-storage/` at `37a212e` finds only Roads511's "incidents" and the legal text. There's no table, no
`IR-####` counter, and no link on any surface.

**What would feed its Incident Radius and timeline today:**
- `control_alarms` (one row per alarm): when it was raised (`first_at`), the repeats (`n_times`), `tripped_at`,
  `retrying_at`, what was paused (`stopped`), `ack_*`, how it closed (`cleared_how`), `resolved_*`. The warning and the
  trip are **the same row**: a warning that becomes a trip has no ID of its own. The report needs "Warning ID and trip ID"
  (R173), so it needs either the logbook row of each level change or a split.
- `control_log`: the alarm raised, the trip ("Its tries ran out at …"), the Escalation Notice, the clear with its story,
  and Resolve with who and the note.
- `system_pauses`: Held. What was paused, from when to when, and how it lifted.
- `ControlAlarms::affects()`: what it touches. The **Unaffected** side isn't computed anywhere: for a host's IR it would
  be the register minus Held (`ControlRegister::processes`).
- `comms_messages` / `comms_route`: who was told, when, and on which channel.
- `control_ledger` / `control_records`: the calls and the records touched (evidence). `crawl_runs` and
  `crawl_run_items`: the run. `job_runs.note`: a job's own words. `preflight_results.hist`: a line's last 10 results with
  their time and ms (the nearest thing to a try timeline). `service_issues.evidence`: the reading.

**What's missing for it:**
- **A record of each try:** with `event=false`, `n_times` doesn't move, and jobs and lines are never re-checked (J1, C1).
- A root-cause field.
- The correlation of members (the same host and signature within a short window: IR-0001's two timeouts).
- The plug-in's version (the worker's version is in the crawler's report; the site's release number is in
  `site_releases`).

---

## 5. Findings, ranked by harm (card specs)

### F1. A job's trip pauses its whole workflow, then "recovers" on a run that never ran

- **Module and role:** Bluehost (Host) → Scheduler → `job:<job>` (a component under the Site); here `listings_tick` →
  W1.
- **Manifest field / zone:** `incident_radius_rules`, row "One job fails"; zone 2, the job's line.
- **What the card shows:**
  - "Listings pipeline (job): stopped 3 runs in a row. Holds: its own step (matching and publishing waiting listings).
    W1's crawler tasks carry on."
  - Recovery: "Recovered when it next runs well". A skipped run ("Paused: …") is never a good run.
- **What it means:** W1 keeps gathering, and the waiting listings queue up until the job works.
- **What happens today:**
  - W1 is paused whole (ControlAlarms.php:279-285).
  - The job's next run skips because W1 is paused, and is recorded `ok=1` (Scheduler.php:119).
  - The watch closes the trip as "Recovered … the system fixed it" and W1 resumes. The job fails again: a loop that emails
    and pushes the Founder each round.
  - A job tied to three workflows pauses only the first (W4).
- **Step-4 scenario:**
  1. Make `listings_tick` fail 3 times. Expect no `system_pauses` row for `wf:W1` (only a pause on the job's own work),
     and W1's tasks still planned.
  2. Run the job while paused. The alarm stays open.
  3. Give it a real good run. It closes as Recovered.
  4. Repeat with `guide_finds_tick`: W4, W8 and W10 are all untouched.

### F2. The site's critical duties can fail with nobody told

- **Module and role:** Bluehost (Host, critical: page, database, procedures).
- **Manifest field / zone:** `sensors`; zone 3, with the heartbeat, the watch's last run, each critical job's cron line
  and its lateness.
- **What the card shows:** "The watch last ran 14:05 (5 min ago) ✓ · Billing renewals: on time ✓ / late 3 h ✗ · Page
  load (outside check): answered 14:09 ✓ · Databases: 4 of 4 answered at 14:05 ✓".
- **What it means:** a critical duty down is a trip at once, whatever the master switch says.
- **What happens today:**
  - With the master off, a red `site:db` or `site:sched` line is a warning (Preflight.php:337, :351).
  - A stuck critical job (billing) is a warning (ControlAlarms.php:89, :100).
  - A quiet `releases_tick` is a warning (L988).
  - A late or silent job raises nothing (no lateness check in `tick`), and that includes the watch itself.
  - `billing_tick` isn't in `Scheduler::AUTO`, so `site:sched` never checks its cron line (Scheduler.php:69,
    Preflight.php:770).
  - Page load has no sensor at all (ControlSensor.php:23-31).
- **Step-4 scenario:** each of these causes one trip at once (Pulse, email, push) and a Recovered when it's fixed:
  1. Master off, then `site:db` refused.
  2. `billing_tick` started 3 h ago and never finished.
  3. No `control_tick` row for 15 min (caught by the outside heartbeat).
  4. `sales_tick` past its late limit (a warning, not critical).
  5. `billing_tick`'s cron line removed.

### F3. Jobs and pre-flight lines "run out of tries" without a single try (the case #51 clock, outside the crawler)

- **Module and role:** every site job, and every pre-flight line's component (Source, Service, Host).
- **Manifest field / zone:** `sensors` (each with its check and its tries); zone 3, "Trying again (n of 3): last checked
  HH:MM".
- **What the card shows:**
  - The diagnostic tries, each with its time and result.
  - For a job that has no check: "No check can run until its next run (Monday 6:13): a warning until then."
  - The Escalation Notice only after real tries.
- **What it means:** a scheduled run is never a try (1.3).
- **What happens today:**
  - `event=false` freezes `n_times`, so the words read "1 of 3 tries" after a 2nd failure (S12).
  - `expireTries` trips or escalates at 60 min with nothing tried (ControlAlarms.php:897-911): a daily billing job
    escalates after one failure (S7), a red Tavily line trips with no re-check (S9), and Bluehost's line escalates with
    n=1 (E2).
- **Step-4 scenario:**
  1. Fail a line's check once. Expect 3 diagnostic re-checks within 60 min, each logged with its result.
  2. A fourth fail is the trip. A pass in between closes it.
  3. For a daily job: one failure leaves a warning until its next run, never an Escalation Notice that claims retries.

### F4. A stuck job never escalates, and clears itself as "ended well" two days later

- **Module and role:** Bluehost → Scheduler → `job:identity_recheck_tick` (W2's job; live L7).
- **Manifest field / zone:** zone 2, the job's line with "running now since 13:15 (25 h): not finished"; zone 4, Fix it
  for its job.
- **What the card shows:** "Identity recheck (job): its run of Nov 20, 13:15 UTC never finished (killed or hung).
  Holds: the recheck's work. What the system is doing: the next run starts as usual." With the part's real name, never
  `job:…`.
- **What it means:** a stuck run is a fault, not slow: a warning on the diagnostic clock, then Paused by the system on
  the recheck and the Founder told once. It closes only when a run finishes, or a person retires the job.
- **What happens today:**
  - It's kind `slow`, so it can't trip.
  - It's board-only.
  - "What it affects" is empty.
  - After 2 days it leaves the watch's window and closes as "Its last run ended well" with `finished_at` NULL (E1,
    ControlAlarms.php:950, :961).
- **Step-4 scenario:**
  1. A run started 3 h ago with no end: a warning naming "Identity recheck", with what it holds.
  2. After the tries: a trip and its pause, sent once.
  3. Aged 49 h with no run since: still open.
  4. A finished run → Recovered.

### F5. Bluehost's line is wired to "Page load", and its title shows the part that passed

- **Module and role:** Bluehost (Host, critical).
- **Manifest field / zone:** `criticality` per duty, with one sensor per duty; zone 3, one line per hosted part.
- **What the card shows:**
  - "cPanel's API: didn't answer 14:18 (setting up jobs waits; the jobs themselves run). A warning."
  - Separately: "Site email: signed in ✓".
  - "Page load" is its own heartbeat line.
- **What it means:** a cPanel or email blip is not a page outage.
- **What happens today:**
  - `svc:bluehost` → page (ControlRegister.php:89), so a cPanel API blip is a critical page trip, sent at once and
    escalated at 60 min.
  - Its title is the first sentence of the result, "Bluehost: The site's email: signed in." (Preflight.php:325).
  - `site:env` unwritable also trips page (Preflight.php:744-747).
  - A cPanel blip trips `site:sched` (procedures) too: two critical trips for one cause.
- **Step-4 scenario:**
  1. Fail cPanel's API with email fine: one warning on "cPanel's API", no trip, no push, the title naming cPanel.
  2. Make `.env` read-only: a warning on "Settings can't be saved", not Page load.
  3. Stop the page heartbeat: a Page load trip.

### F6. "Paused by the system" when nothing is paused

- **Module and role:** any part whose trip places no pause (site jobs, services' pre-flight lines, site parts).
- **Manifest field / zone:** zone 4. The thread's action word follows what `system_pauses` actually holds.
- **What the card shows:**
  - "Down: the system is trying" / "Tripped: nothing paused (it has no work to hold)" / "Paused by the system: <what>".
- **What it means:** words follow the state (R101).
- **What happens today:** the email, push and Pulse subject is always "…: Paused by the system" (ACTIONS, L657),
  including Lead scores, Tavily and Bluehost with `stopped = null`. The board light says "Tripped: stopped until an admin
  resolves it." although it closes as Recovered (ControlBoard.php:186).
- **Step-4 scenario:** trip a site job tied to no workflow. The subject and push never say "Paused"; the board never says
  "until an admin resolves it".

### F7. Start's watch writes into a person's switch, credited to the Founder, and tells no one

- **Module and role:** Start-up stage → a workflow or task.
- **Manifest field / zone:** zone 4 of the workflow (its stage and its pause, with who).
- **What the card shows:** "W2: Paused by the system (Start's watch): 3 of its 10 runs failed since it came on. It
  resumes when its check passes." Its switch still reads On.
- **What it means:** a fault pauses, never switches off (1.1).
- **What happens today:** `pauseItem` sets `crawl_workflows.paused = 1` (SystemStart.php:515-521). Paused now reads it as
  "set by Founder" (E3). There's no `system_pauses` row and only a board warning.
- **Step-4 scenario:** fail a switched-on item's gate. Expect one `system_pauses` row, the switch untouched, one trip
  told once, and a Recovered when its gate passes.

### F8. Maintenance can't take out a module

- **Module and role:** Railway and Bluehost (Hosts), every service, the workers, the site jobs.
- **Manifest field / zone:** lifecycle stage 6, Maintenance / Draining; zone 4, Take out / Put back / Extend.
- **What the card shows:** "Take out Bluehost's cron for upgrade, back by 16:00": its dependants named and paused with
  why, their alarms as notices, Put back checking first.
- **What happens today:** `canTake` allows only workflows and tasks (ControlMaintenance.php:29-34).
- **Step-4 scenario:** take out `svc:tavily`. Its tasks reroute or wait with "In maintenance", no trips are raised, and
  Put back with its check red keeps it out.

### F9. A trip jumps Pulse straight to the Director

- **Module and role:** the Communications Dispatcher (Service, critical).
- **Manifest field / zone:** routing matrix; zone 2 of the dispatcher.
- **What the card shows:** "Trip: the Founder at once; the Manager at 2 h; the Director at 4 h."
- **What happens today:** `escalateTo(..., 2)` at every trip (ControlAlarms.php:226, :566).
- **Step-4 scenario:** open a trip. Pulse's escalation level is 0 at once, 1 at 2 h and 2 at 4 h.

### F10. Shut-down doesn't name dependants or hand back leases

- **Module and role:** the whole system (Shut-down stage).
- **What the card shows:** "Stopping W1 will hold: … (named). In flight: 3 leased towns (handed back), 1 W0 batch
  (finishing), listings_tick (skips its next run)." Then "stopped, safe".
- **What happens today:** switches are written and only crawler runs are waited for (ControlBoard.php:625-663).
- **Step-4 scenario:** shut down with a crawler run, a W0 batch and leased requests going. Expect dependants listed
  first, the leases back to queued, and "safe" only after all three.

### F11. No Incident Report

- **Module and role:** every plug-in. Feeds: §4.
- **What the card shows:** IR-#### on every trip, linked on all seven surfaces, with Held and Unaffected from the
  register.
- **Step-4 scenario:** each trip in the scenario test opens exactly one IR with its sections in order. A warning that
  clears itself opens none.
