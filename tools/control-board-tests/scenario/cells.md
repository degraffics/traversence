# The scenario test: its own table (handoffs/control-board-build.md §5)

Every one of step 2's **173 cells** (`step2/README.md`, "The totals": crawler side 35, sources and services 31, jobs, checks
and hosts 38, channels 69) mapped to 02 §4.9's **135 designed scenarios** (lift S-A1–15, faults S-B16–82, clock S-C83–90,
actions S-D91–120, escalation S-E121–123, correlation S-F124–127, portal S-G128–135), plus the lettered rows (S-A5b,
S-B37a, 38a, 38b, 41a, 70a), the words suite (W-…) and the named case (workflows-page.md §8, NAMED).

**Run:** `tools/control-board-tests/run-all.sh` runs it with the others (its entry: `scenario/run.php`), or by itself:
`mariadb < ../../release-b-tests/start-schema.sql && php run.php <root>/app [suite …]`. Each scenario stands up its
starting state on lv_main (every board table made by the runner: 02 §4.9's last line), causes its failure through the
engine's real callers, and asserts 02 §4.9's five things:
1. **state** (the alarm row, read through the engine);
2. **hold** (exactly Held / On a backup / Unaffected: `system_pauses` and a host's trip hold; no switch value changed,
   by a hash of every switch, task and workflow flag: CS-1);
3. **guard** (`ControlEngine::$checker` called with the component the row names; any other part's check fails, never a
   real outside call; the process's proxy points at a closed port);
4. **words** at the engine, Thread and Notify level (`ControlAlarms::facts`, `noticeTitle`, `Thread::push`, the stood-in
   Mailer's and WebPush's sends). The view surfaces (the board's rows, the card, Paused now, Scheduled tasks) are being
   rebuilt by other helpers: those words are **views: pending** below, for the session to add once the views land;
5. **messages** (`comms_messages`, Mailer, WebPush: counts, one thread per alarm, a close updating Pulse and the email).

Then recovery, and Recovered. A scenario prints `  ok  <id> …`, `FAIL  <id> … — expected …; got …` (its other failed
assertions on the lines under it, starting `        · `), or `  --  <id> … — <why>` when it can't be caused on the bench.

**The heartbeat cell** (row 53, "Bluehost is down: the outside heartbeat emails", S-B53; cells g3:C13, g3:D6) runs as
**not set up (ALARM_TO)**: not a pass, not a fail, until Jason sets `ALARM_TO` and the SES send-only key (close.md A1).
Its site-side import is covered by `tools/control-board-tests/heartbeat-import.php`, the heartbeat itself by
`tools/heartbeat/test_heartbeat.py`.

## What can't be caused on the bench, and why

- **Outside reads the bench doesn't make** (02 §4.9: stand-in hosts; this bench has none for these): Railway's deploy
  watch and its token (S-B51, S-B52), GitHub's token expiry, refusal and the sync's runs (S-B58–S-B61), a missing cron
  line read from cPanel and a dead watch seen from outside (S-B55), Preflight's live lines behind Check now, Resume,
  Activate, Start and Shut down (S-D92, S-D100, S-D102, S-D112, S-D113).
- **Raisers that don't exist yet** (build items, listed for the session): the AI share's warning (S-B41a), the test
  order's notice (S-B70a), the cost-per-record watch (S-B76), the channel fallback (S-B81), Assign (S-D118), the On a
  backup notice from `search_switches` (S-B44 depends on S-B43, which fails).
- **Words written by a caller outside the engine** (P9 moves them to state): the cap's band title (S-B39, W-12, Operations
  L605); the Railway key "not checked yet" line (S-B30).
- **View actions** (being rebuilt): Switch on (S-A9, S-D114, S-D115), Deactivate (S-D101), Add its key (S-D107), the card's
  "At <Provider>" rows (S-G131–S-G133).
- **Covered by an existing test re-run in run-all.sh** instead: S-D105 (`r4a.php`), S-D109 (`r2c.php`), S-D117 (`defs.php`).
- **Mapped approximately:** g1:6 (a request failing: the model says the same as today, "—") rides on S-B74 and S-B75,
  the nearest designed rows (a hop's numbers); g3:C14 (a realm refused mid-day: one database on the bench) is driven as
  the pre-flight's `site:db` raise (S-B54) and the master-off case (S-B72); g1:23 (the Census backup, matching) rides on
  S-B43 and `tools/place-tests/census-backup.php`.

## The scenarios (results of the bench run of 2026-11-22, on the overlay as it stood; `run.php` prints them live)

| Scenario | What it proves | Result (bench, last run) | Expected (02 §4) · got (the engine) |
|---|---|---|---|
| S-A1 | a 504 at Common Crawl's index: a warning, its first try at the next tick, nothing sent | ok |  |
| S-A2 | the index's three tries fail: a trip holding only calls to the index, W3 and every workflow unaffected | **FAIL** | expected hold: W3 and every workflow and task unaffected (02 row 2; workflows-page §8); got ["task:commoncrawl"] |
| S-A3 | W3's line is a roll-up: no alarm on W3, its row "Working" | ok |  |
| S-A4 | after the trip, the ladder's check fails: the trip's status updated in place, nothing re-sent | ok |  |
| S-A5 | Check and start again on the index: its check called, it closes, only the index's hold lifts, every channel updated | ok |  |
| S-A5b | Check and start again with the index still red: stays a trip, saying why, nothing changes | ok |  |
| S-A6 | a check closes only the component it checked: Common Crawl's data host is its own part | **FAIL** | expected the index and the data host are separate components (P2: the part that failed); got ["svc:commoncrawl","svc:commoncrawl"] |
| S-A7 | a switch a closed trip left off: CS-3's three cases (a person's setting, Start's, none) | ok |  |
| S-A8 | a person's close names the holds actually lifted, never "Started again: W3" while nothing of W3 was held | ok |  |
| S-A9 | Switch on a switch left off by a trip: the switch-on check first | not on the bench | Switch on (CS-4) is a view action on the task's row and Paused now (the views being rebuilt); its switch-on check is Preflight::run on live lines, which calls outside services. Re-run with the views: pending |
| S-A10 | any close updates the trip's thread on every channel; the bell's count drops | **FAIL** | expected the system's passing check closes it as Recovered; got Resolved by the system: Cleared by its check: Its check passed: the system fixed it Its check passed: answered 200 in 180 ms. |
| S-A11 | overture_bulk times out reading our reply: our endpoint is slow, a warning; W1 and its tasks unaffected | ok |  |
| S-A12 | foursquare_bulk the same, 11 min apart: the same alarm at the same component, count 2, no second message | ok |  |
| S-A13 | the engine's check of /api/crawl/loads passes: Cleared | ok |  |
| S-A14 | the next night's window: the bulk loads run (nothing holds them) | ok |  |
| S-A15 | Acknowledge a trip: Seen, the light stays red (one count of open trips, seen or not) | ok |  |
| S-B16 | a task fails in its own code: a warning "waiting for its next run", nothing held | ok |  |
| S-B17 | its next run fails again: a Trip that holds nothing, its next run still goes ahead (ruling 3, O2) | ok |  |
| S-B18 | its next run finishes well: Cleared | ok |  |
| S-B19 | an endpoint slow: 3× its usual with 20 calls of history, or over its speed gate with fewer | **FAIL** | expected fewer than 20 calls: judged against its declared speed gate (row 19; g1 row 9: missing); got no alarm |
| S-B20 | a call at its usual speed: Cleared | ok |  |
| S-B21 | an endpoint answers 5xx: a Warning (W-####), trying again, nothing held | ok |  |
| S-B22 | three failed tries of the endpoint's check: a Trip holding only the tasks that call it; its check runs while held (P7) | ok |  |
| S-B23 | Bluehost's bot check (409 humans_) or ModSecurity (406) refuses the worker: a refusal on the endpoint, held at once | **FAIL** | expected a refusal on /api/crawl/loads is raised (site_blocked is read; a 406 is a fault on the endpoint); got [] (and 2 more below) · also expected state: Paused by the system at once (a refusal needs a person); got null · also expected hold: the tasks that call that endpoint; got [] |
| S-B24 | timeouts in a burst inside one run: one fault, a Warning trying again (1 of 3) | **FAIL** | expected a DuckDB read failing at Overture's regional S3 host is Overture's, never Amazon Web Services' (g1 row 5; g2 row 31); got svc:aws (and 2 more below) · also expected a malformed 200 at a site caller is a back-off fault (g2 row 25: classify(200) is nothing); got · also expected the same cause in a task's report is the same fault: one alarm, not two (g1 row 4); got [{"akey":"svc:issue:13","component":"svc:tavil… |
| S-B25 | a success between failures: Cleared, its tries reset (any real 2xx from any caller) | ok |  |
| S-B26 | a server's 3 tries fail: a Trip and IR; each task reroutes (On a backup) or waits; never the workflow whole | ok |  |
| S-B27 | 429, S3 SlowDown, Google's per-minute limit: a rate wait, never a trip | **FAIL** | expected classify: Google's per-minute RESOURCE_EXHAUSTED is a rate wait, not a quota (g2 row 6); got quota |
| S-B28 | the wait ends and the engine's check passes, whether or not a task calls it: Cleared | **FAIL** | expected guard: the engine checked it after its wait (no task calling it); got [] (and 1 more below) · also expected state: Cleared after its wait (g2 row 12, L11: open 3 days); got {"id":33,"akey":"svc:issue:20","proc":"svc:wikimedia","level":"warning","kind":"retry","title":"query.wikidata.org: Asked us to slow down (the crawler, on Railw |
| S-B29 | a refused key (401): Paused by the system at once, calls with that key held, tasks reroute or wait | **FAIL** | expected the site's own caller (Claude): its calls are held, so its status says "Paused by the system", never "Down: nothing could be held"; got Down: nothing could be held since {t:2026-11-22T22:53:09Z}. |
| S-B30 | a Railway key the worker hasn't reported yet: Not checked yet, holds nothing | not on the bench | the key's state comes from Operations::status over the worker's stored report and the register (Operations::switchOnCheck, gateFor); its line is drawn on the Modules card being rebuilt (M). views: pending; covered today by tools/control-board-tests/keys.php |
| S-B31 | the worker reports a key missing or refused: as S-B29 | ok |  |
| S-B32 | a ban (403 challenge): Paused at once, never retried, its probe once a day for 3 days | **FAIL** | expected its probe is scheduled: once a day (g2 F7: PROBES is empty); got [null,null] |
| S-B33 | the ban's probe passes: stays Paused, a Notice "looks lifted" with Reactivate on the thread | **FAIL** | expected the trip's thread says its ban looks lifted (P10: one thread; g4 (b): the EngineCase step is off the board); got Paused by the system (source (W3)) since {t:2026-11-22T22:53:09Z}. |
| S-B34 | three daily probes fail: stays; the probes stop; it waits for a person | ok |  |
| S-B35 | a site blocks W0 or W3: that site held, one notice, never a trip; Fix it on W0's page | ok |  |
| S-B36 | a provider's per-day quota used (Gemini): Paused until its reset, its check at the reset | **FAIL** | expected its check is set for its reset (00:00 Pacific), not held for good (g2 row 6); got [null,null] |
| S-B37 | the reset check passes: Closed, Recovered; released | ok |  |
| S-B37a | a private input asked onto Gemini: a Notice on Gemini's card only; nothing sent, nothing held | **FAIL** | expected a Notice on Gemini's card (the session manager's ruling, 2026-11-21); got 0 |
| S-B38 | out of credit at Anthropic (400 "credit balance is too low"): a quota, Paused at once | **FAIL** | expected classified as a quota (a stop kind), not nothing (g2 row 7); got  |
| S-B38a | AWS pauses SES sending: a stop kind, Paused at once (email by SES); alarms go by Bluehost's SMTP | **FAIL** | expected classified as a stop kind (an account-level pause), never a try; got  |
| S-B38b | a source's file changed shape: Paused at once on that load only, nothing written | ok |  |
| S-B39 | near a cap: a Warning; its title's number is the reading (round(spend ÷ cap)), the band decides only the level | not on the bench | the cap look (Operations::warnings over the month's crawl_costs and the caps) is a caller outside the engine; its title is written by Operations (L605: "<name> at <band>%"), a raiser's words P9 moves to state. Covered on the bench once the words come from state; today tools/control-board-tests/r2c.php |
| S-B40 | our cap at 100%: Paused by the system, every call held | ok |  |
| S-B41 | the cap raised or the month turned: Closed, released | ok |  |
| S-B41a | a workflow's share of the AI cap used: a Warning on that workflow only | not on the bench | AiClient::shareLeft reads budgets and the month's AI spend and refuses the call to its caller; no raise exists to drive (the ruling, 2026-11-21, is a build item: "a Warning on that workflow only"). Listed for the session |
| S-B42 | a backup's own check fails while the primary works: its calls only; nothing waits on it | **FAIL** | expected hold: the backup's calls only; no task (Tavily, their first choice, works); got [["api.search.brave.com"],["task:howto","task:identity","task:learn","task:refresh","task:second_look","task:source","task:targets"]] |
| S-B43 | a first source paused: its task On a backup, said as a Notice naming both | **FAIL** | expected a Notice "<task> is on its backup (Brave) while Tavily is paused" (search_switches read: g2 row 27); got 0 |
| S-B44 | the first source's hold released: back on its first choice | not on the bench | needs S-B43's On a backup notice, which no raiser makes yet (search_switches are read by nothing): S-B43 fails first |
| S-B45 | every source of a need paused: every task that needs it waits, in every workflow the need names | ok |  |
| S-B46 | every task of a workflow held: the workflow waits whole, with no alarm of its own | ok |  |
| S-B47 | no run reported, or one abandoned: a Warning, the crawler's check its tries | **FAIL** | expected an abandoned run counts as a failure: it doesn't clear (g1 row 30: four killed runs never trip); got No run is overdue (22:53 UTC). |
| S-B48 | the crawler's 3 tries fail: a Trip holding all gathering; W0, the site and its jobs unaffected | ok |  |
| S-B49 | a run with tasks idle and one failing: no fault on the crawler; the task follows its own row | **FAIL** | expected no alarm on the crawler (g1 F4: idle + one failure is "failed", all gathering); got {"id":57,"akey":"run:failed","proc":"wk:crawler","level":"warning","kind":"crash","title":"A crawler run failed (#18)","means":"It couldn't do its work; what it was given goes back and the next scheduled run tries again.","todo":"Read run #18's why (beside thi (and 1 more below) · also expected the failing task has its own al… |
| S-B50 | a worker's heartbeat silent 15 min: W0 held after its tries; the video worker's queue waits | ok |  |
| S-B51 | Railway: a deploy failed or building over 30 min: a Warning, the old version running | not on the bench | Railway's deploy watch (Operations::watchTick) reads Railway's API, an outside call the bench doesn't make; its raise isn't in the engine yet (02 §5.2 "the ops:watch:* Pulse cases become rows 51 and 60"). Listed for the session |
| S-B52 | Railway refuses our token (401): Paused at once, deploys not watched | not on the bench | as S-B51: the watch catches the 401 as ok:null ("Couldn't look: HTTP 401") before any raise (g2 row 5); no engine path to drive |
| S-B53 | Bluehost is down: the outside heartbeat emails | not on the bench | not set up (ALARM_TO): the heartbeat sends from GitHub with ALARM_TO and the SES send-only key, which Jason sets (close.md A1). Its site-side import: tools/control-board-tests/heartbeat-import.php; the heartbeat: tools/heartbeat/test_heartbeat.py |
| S-B54 | a database realm refuses at the watch: a critical Trip at once (database) | ok |  |
| S-B55 | the watch hasn't ticked in 10 min, or a critical job's cron line missing: a critical Trip (procedures) | not on the bench | a dead watch is seen from outside the host (the heartbeat, not set up: ALARM_TO); a missing cron line is read from cPanel's API (Scheduler::sync), an outside call |
| S-B56 | cPanel's API doesn't answer (email and pages fine): a Warning on cPanel's API, never "Page load" | ok |  |
| S-B57 | GitHub's release look gets no answer: a critical Trip, releases and the sync held, still trying | ok |  |
| S-B58 | a GitHub token expiring within 14 days: a Warning | not on the bench | the token's expiry is read from GitHub's API (an outside call); no raiser on the site yet (manifest spec) |
| S-B59 | a GitHub token refused: Paused at once, what it does held | not on the bench | as S-B58: GitHub's answer is read by the release job and the sync watch, outside calls; no raiser yet |
| S-B60 | the sync's run failed or stuck: a Warning, then a Trip after 3 tries | not on the bench | the sync watch reads GitHub Actions' runs (an outside call); today a Pulse case ops:watch:* (02 §5.3 removes it) |
| S-B61 | no sync run an hour after its slot: a Warning; a Trip at 14 h | not on the bench | as S-B60 |
| S-B62 | a site job serving no workflow fails: a Warning, nothing held, its next run its check | ok |  |
| S-B63 | a job tied to workflows fails: a Warning on the job; the workflows' crawler tasks unaffected | ok |  |
| S-B64 | its next run skipped ("Paused: …"): nothing changes (a skip is never a good run) | ok |  |
| S-B65 | a job late: not started by its time plus 5 min: a Warning | **FAIL** | expected a lateness warning is raised (g3 J7: missing); got [] |
| S-B66 | a job stuck over 2 h: a fault (not slow): a Warning, then a Trip after 3 tries, holding its own work | **FAIL** | expected a fault on the diagnostic clock, not "slow" that can never trip (g3 J5); got slow |
| S-B67 | a job slow: over 3× its usual and over a minute: a Warning (Slow) that clears itself | ok |  |
| S-B68 | billing_tick fails or is stuck; releases_tick quiet: a critical Trip at once whatever the kind | **FAIL** | expected stuck billing (a critical job): a Trip at once (g3 J6, D2); got warning (and 1 more below) · also expected releases_tick quiet 30 min: a critical Trip (g3 J9); got warning |
| S-B69 | search's test fails: a critical Trip at once, each probe a try, nothing held | ok |  |
| S-B70 | QuickBooks refuses our token: Paused at once; closes as Fixed by the person who reconnected | **FAIL** | expected Fix it lands on the person's fix (a key: Connect QuickBooks again), never none (g4 (b)); got null |
| S-B70a | a test order reaching a charge: a Notice on QuickBooks' card; no real money moves | not on the bench | QuickBooksPay::guardReal() refuses to the caller only; the Notice on QuickBooks' card (the ruling, 2026-11-21) has no raiser yet. Listed for the session |
| S-B71 | a module's pre-flight line goes red: the component's own fault by its kind, never an alarm of the line's own | ok |  |
| S-B72 | site:db or site:sched red with the master OFF: a critical Trip at once (the master stops gathering, never duties) | **FAIL** | expected a Trip at once with the master off (g3 C3); got {"id":98,"akey":"pf:site:sched","proc":"site:sched","level":"warning","kind":"check","title":"The scheduler: its cron lines are missing","means":"","todo":"","reading":"","fix":"","n_times":1,"first_at":"2026-11-22 22:53:12","last_at":"2026-11-22 22:53:12","ac (and 1 more below) · also expected messages: the founder told; got [0,0,0] |
| S-B73 | Start's watch: a switched-on item's gate fails: a hold record, the switch untouched | ok |  |
| S-B74 | a task returns empty in 12 runs: a Warning, nothing held | ok |  |
| S-B75 | received ≠ stored + updated + Review + refused, either way: a Warning on that hop | **FAIL** | expected fewer accounted for than received is a warning too (g2 R55: only "more"); got no alarm |
| S-B76 | cost per record past control_cost_factor × its 30-day usual: a Warning | not on the bench | no raiser yet: control_cost_factor (Q19: 2.0×) is set; the cost watch is a build item listed for the session |
| S-B77 | Pulse slow to gather: a Warning, board only | ok |  |
| S-B78 | a query over its limit: a Warning (Slow) | ok |  |
| S-B79 | a fault on a part in maintenance: a Notice only; its holds stand | ok |  |
| S-B80 | a trip with nobody to receive it: shown, and a critical Trip on the Communications Dispatcher | **FAIL** | expected the dispatcher's own fault raised as a critical Trip (P11; g4 row 14); got [{"akey":"svc:issue:32","level":"trip"}] (and 1 more below) · also expected the trip says it reached no one; got Try 3 at 22:53 UTC: index.commoncrawl.org answered 504 Gateway Time-out. |
| S-B81 | email fails (SMTP and SES): a fault on that channel; the message falls back to Pulse and push | not on the bench | Notify's channel fallback (row 81) has no path yet; mail.failure is still a Pulse message (g4 (c)). Listed for the session |
| S-B82 | a Critical message with email paused by message control: sent anyway | ok |  |
| S-C83 | a try fails inside the hour: a Warning, n+1 of 3, the next at 15 then 35 min | ok |  |
| S-C84 | a try passes: Cleared, its proof named | ok |  |
| S-C85 | the third try fails, or the hour ends after a failed try: a Trip with its radius | ok |  |
| S-C86 | the hour ends with no try made (the check couldn't run): never a Trip on the clock alone | **FAIL** | expected …it says why and keeps a next check (it never stops trying); got [null,"Warning since {t:2026-11-22T21:43:12Z}, seen 1 time."] |
| S-C87 | a critical part's first failure: a Trip at once, still trying; nothing held | ok |  |
| S-C88 | its tries run out: Escalated, the Escalation Notice on the same thread, one push | **FAIL** | expected no "(N of these are waiting.)" in an in-place thread's email (g4 F8); got ["SYSTEM ALARM NOTICE: Amazon Web Services: Down: the system is still trying\n\nDown: the system is still trying, 0 of 3, ne","SYSTEM ALARM NOTICE: Amazon Web Services: Retries unsuccessful, now admin required\n\nRetries unsuccessful, now admin requ"] |
| S-C89 | its after-trip checks keep failing: still a Trip, its status updated in place | ok |  |
| S-C90 | an after-trip check passes: Closed as Recovered; dependants back on their first choice | **FAIL** | expected as Recovered, the system credited only where it fixed it (g2 row 20: "Resolved by the system"); got Resolved by the system: Cleared by its check: Its check passed: the system fixed it Its check passed: api.tavily.com answered a search (200). (and 1 more below) · also expected …its subject "…: Recovered"; got ["SYSTEM ALARM NOTICE: Tavily: Fixed by the system"] |
| S-D91 | Check now on a warning: pass Cleared; fail counts as a try | **FAIL** | expected a failing person's check counts as a try (row 91: "(2 of 3)"); got 0 |
| S-D92 | Check now on a working part: its reading updated; a fail opens a fault | not on the bench | Check now on a working module is the card's button (Preflight::run of its live line, an outside call on the bench); the card is being rebuilt (M). views: pending |
| S-D93 | Check and start again: check first; pass releases only this trip's hold | ok |  |
| S-D94 | Check and start again with its check red: a Trip, its status updated, the press refused saying why | ok |  |
| S-D95 | Resolve and Mark resolved (svcresolve) removed | **FAIL** | expected no svcresolve action left (02 §5.3: "Mark resolved and svcresolve" removed); got 4 mentions |
| S-D96 | Acknowledge a trip: Seen; the trip stays | ok |  |
| S-D97 | Acknowledge isn't offered on a warning | **FAIL** | expected no Acknowledge on a warning (R80); got {"html":"<style>.tv-th{min-width:0}.tv-th-title{margin:0 0 .2rem}.tv-th-parts{display:grid;gap:.15rem;font-size:13px}.tv-th-parts>span{min-width:0;overflow-wrap:anywhere}.tv-th-pill{display:inline-block;border-radius:999px;padding:0 .45rem;font-size:11.5px;bor |
| S-D98 | Undo a close within a day: the trip open again, its hold placed again, its thread raised | **FAIL** | expected its thread raised again: Pulse unread and one push (row 98); got [1,1] |
| S-D99 | Pause (a person) on a pausable part: Off by a person | ok |  |
| S-D100 | Resume with its check red: refused, saying why | not on the bench | Resume's check is the card's (Operations, Preflight::run of a live line): an outside call; the card is being rebuilt (M) |
| S-D101 | Deactivate: stage 7, dependants move to the next source | not on the bench | Deactivate (Q14) is a Modules card action (ServiceStages) being rebuilt (M); "Migrate traffic" has no library path yet |
| S-D102 | Activate: its line checked first | not on the bench | Activate runs Preflight::run on the service's live line (an outside call); covered by tools/control-board-tests/register.php |
| S-D103 | Take out (maintenance) for any module, host, job, worker or part | **FAIL** | expected every part can be taken out (g3 M5: workflows and tasks only); got ["svc:railway","svc:bluehost","job:billing_tick","wk:crawler","site:search"] |
| S-D104 | Put back: its check first; red stays out with the reason | ok |  |
| S-D105 | a window set ahead goes out and comes back by itself | not on the bench | built and covered by tools/control-board-tests/r4a.php (re-run in run-all.sh); its ticks read the scheduler's clock |
| S-D106 | Replace its key: pass Fixed; fail Paused with Put the old one back | ok |  |
| S-D107 | Add its key: Working, or Not checked yet until the report | not on the bench | Add its key writes .env through the one writer of secrets (Keys, 0173 r2): covered by tools/control-board-tests/keys.php; "not checked yet" is the card's (M) |
| S-D108 | Reactivate a ban: one polite request; pass Fixed, fail stays | ok |  |
| S-D109 | Raise the cap: as row 41 | not on the bench | Raise the cap is Operations::pulseDo(ops_cap:<gate>), which re-reads the month's costs; covered by tools/control-board-tests/r2c.php |
| S-D110 | It's fixed: try it again: a Warning "waiting for its next run" | ok |  |
| S-D111 | Pause everything: the master Off by a person; business duties run | ok |  |
| S-D112 | Start the system: each confirmed item on, in order, watched | not on the bench | Start tests each item's live needs first (SystemStart::refreshFor → Preflight::run): outside calls; covered by tools/control-board-tests/r1.php and r2b.php |
| S-D113 | Shut down: dependants named before it stops, leases handed back | not on the bench | shut-down waits on crawler runs and Railway's schedule (an outside call); its dependants' naming is a build item (g3 F10) listed for the session |
| S-D114 | Switch on (CS-4): as S-A9 | not on the bench | as S-A9: a view action; views: pending |
| S-D115 | Switch on: its check passed, or stays saying why | not on the bench | as S-A9 |
| S-D116 | Delete: Retired, its holds lift, history kept | **FAIL** | expected its trip closes: "Closed: <part> was deleted by Jason; its history is kept" (row 116); got still open |
| S-D117 | Bring it back: stage 1–2, its pre-flight needed | not on the bench | covered by tools/control-board-tests/defs.php (Retire and Bring it back) |
| S-D118 | Assign or Unassign to a need or task | not on the bench | no assign action exists (§4.8 row 118: "nothing"); a build item listed for the session |
| S-D119 | Log an entry | ok |  |
| S-D120 | Pause only this task during an incident: the task Off by the person | ok |  |
| S-E121 | Pulse's clock at a trip: the Founder at once, not the Director (level 0 until 2 h) | **FAIL** | expected the trip's Pulse item isn't escalated at the minute of the trip (g3 C15, R76: tripped() sets level 2 at once); got 2 |
| S-E122 | open 2 h: the Manager (level 1); open 4 h: the Director (level 2) | **FAIL** | expected at 2 h its level is the Manager's, not already the Director's; got 2 |
| S-E123 | its check passes after escalation: Closed as Recovered, released, the Recovered notice to the Founders | ok |  |
| S-F124 | Trip B on the same host and signature inside the window joins A's incident: one thread | **FAIL** | expected one thread: one Pulse row, one email, one push for the incident (g4 rows 10, 11: two of each); got [2,2,2] |
| S-F125 | one member's check passes: that member closed, the incident still open | ok |  |
| S-F126 | the last member's check passes: the incident closed, Recovered for the group | ok |  |
| S-F127 | alarms sharing a cause or incident: one group row, named by the cause | **FAIL** | expected …keyed by their incident, never the board-system grouping "engine feeds" (02 §5.3); got engine:trip:failing |
| S-G128 | a provider's quota: Fix it → "At <Provider>: billing", the email carrying its link line | **FAIL** | expected the email body's link line "Billing at Anthropic: <its Limits URL>" (row 128); got ["SYSTEM ALARM NOTICE: Anthropic (Claude): Down: the system is still trying\n\nDown: nothing could be held since Nov 22, 22:53 UTC.\nWhat's going on: api.anthropic.c"] |
| S-G129 | a refused key: Fix it → Its key with Replace; the email's "API keys at <Provider>" line | **FAIL** | expected the email body's keys link line (row 129); got ["SYSTEM ALARM NOTICE: Anthropic (Claude): Down: the system is still trying\n\nDown: nothing could be held since Nov 22, 22:53 UTC.\nWhat's going on: api.anthropic.c"] |
| S-G130 | their trouble (a 5xx): no Fix it (R118 wins); the console link to inspect | ok |  |
| S-G131 | a host's console link on the card (Railway's dashboard; Bluehost "No console page registered") | not on the bench | the card's "At <Host>" row is module-cards.php's (being built): views: pending |
| S-G132 | the sign-in link on the card | not on the bench | as S-G131: views: pending |
| S-G133 | a type with no registered page: "No <type> page registered for <Provider> yet", never a borrowed URL | not on the bench | as S-G131: views: pending (module-cards.php tv_mc_zone4 draws the note) |
| S-G134 | a portal link pressed: nothing on the board changes | ok |  |
| S-G135 | the verifying board action after a portal step: as S-D91, S-D93, S-D106 | ok |  |
| NAMED | Common Crawl's index down holds only W3's per-site copy (workflows-page §8, six steps) | **FAIL** | expected 2. W3's bulk reads (commoncrawl, at the data host) carry on: not held; got ["task:commoncrawl"] (and 1 more below) · also expected 5. closed as "Recovered: Common Crawl's index was down <n> min, the system fixed it."; got Resolved by the system: Cleared by its check: Its check passed: the system fixed it Its check passed: the index answered a real query (a lookup for https://exam |
| W-1 | §4.8 rows 1, 24: a warning: a plain title; "Trying again (1 of 3): <answer> at <time>. Next check <time>." | **FAIL** | expected the title is plain words (no host or caller: R102); got index.commoncrawl.org: Its server failed (the crawler, on Railway) (and 1 more below) · also expected the status "Trying again (1 of 3)…Next check…"; got Warning: the system is trying again by itself (0 of 3, next at {t:2026-11-22T22:58:14Z}). Nothing is held. |
| W-2 | §4.8 rows 2, 26, 85: a trip: "SYSTEM ALARM NOTICE: Common Crawl's index: Paused by the system"; Pu "SYSTEM ALARM: Common Crawl's index" | **FAIL** | expected E subject names the component plainly: "Common Crawl's index"; got SYSTEM ALARM NOTICE: Common Crawl: Paused by the system (and 1 more below) · also expected Pu title "SYSTEM ALARM: Common Crawl's index", its body starting with the action; got {"u":7,"title":"SYSTEM ALARM: Common Crawl","body":"Paused by the system (Calls to it, commoncrawl (W3)) since Nov 22, 22:53 UTC.","url":"https://traversence.co |
| W-11 | §4.8 rows 11, 16: our endpoint slow: "/api/crawl/loads is slow"; a task's own error: "<task> failed in its own code (run #N)" | **FAIL** | expected the endpoint's title, not the task's (P2); got W1: its task overture_bulk failed (run #22) (and 1 more below) · also expected a task's own error: "second_look failed in its own code (run #N)"; got W2: its task second_look failed (run #23) |
| W-21 | §4.8 rows 21, 22: "/api/crawl/loads keeps failing"; trip "Paused by the system (a trip): 3 tasks wait on it."; E "SYSTEM ALARM NOTICE: /api/crawl/loads: Paused by the system" | **FAIL** | expected E subject names the endpoint, never its first workflow (g1 F2: filed under W1); got SYSTEM ALARM NOTICE: W1 Listings: Paused by the system (and 1 more below) · also expected Pu body "Paused by the system: 3 tasks wait on it." (the count from the holds); got Paused by the system (overture_bulk (W1), foursquare_bulk (W1), geofabrik_bulk (W1)) since Nov 22, 22:53 UTC. |
| W-27 | §4.8 rows 27, 28 and g4 9b: a SlowDown reported by the worker as a server error reads "asked us to slow down" (P2: the worker's kind is a hint) | ok |  |
| W-29 | §4.8 rows 29, 31: "Claude (AI) refused our key" · "Paused by the system: calls with ANTHROPIC_API_KEY wait for a new key." | **FAIL** | expected the title plain: no host, no caller; got api.anthropic.com: Our key was refused (the site, on Bluehost) (and 2 more below) · also expected the status names the key whose calls wait; got Down: nothing could be held since {t:2026-11-22T22:53:14Z}. · also expected never "Nothing could be paused" on any surface; got Down: nothing could be held since Nov 22, 22:53 UTC. |
| W-47 | §4.8 rows 47, 48: "The crawler is down" · trip "Paused by the system: all gathering waits." | **FAIL** | expected E subject "SYSTEM ALARM NOTICE: The crawler is down: Paused by the system"; got SYSTEM ALARM NOTICE: The crawler: Paused by the system |
| W-62 | §4.8 rows 62, 63 and g4 row 13: a job's plain name, never "job:digest"; never "Paused" with nothing paused | **FAIL** | expected the notice names the job plainly; got SYSTEM ALARM NOTICE: job:digest_tick: Tripped: nothing is held |
| W-69 | §4.8 rows 69, 87: a critical part: "Down: the system is still trying" on B, P, E and Pu | ok |  |
| W-90 | §4.8 rows 90, 123: "Recovered: <part> was down <n> min, the system fixed it." on B, P and E; Pu replaced silently | ok |  |
| W-93 | §4.8 rows 5, 10, 93: a person's close: "…: Fixed by <name>" on P and E | ok |  |
| W-16 | g4 row 16: the logbook keeps the moment it tripped (Warning → Trip → Cleared) | **FAIL** | expected a Trip line between the Warning and the Clear; got ["Warning: W2: its task second_look failed (run #25)","Cleared: W2: its task second_look failed (run #26)"] |
| W-BL | BL: a number shown is computed from the same readings everywhere (the try count in the status and on the row) | ok |  |
| W-12 | g4 row 12 and §4.8 row 39: a cap's title says its reading, not the band | not on the bench | the cap title is written by Operations (L605) from crawl_costs and the caps, outside the engine; views: pending. Listed for the session (P9) |

## The 173 cells

Each cell: its failure, step 2's verdict, which column differs (g1 and g4 tagged here, as step 2 left them untagged; g2 and g3 by their findings), the scenario(s) that cover it, the result, and whether its words on the views are still to be added.

### Crawler side (g1-crawler.md §1, 35 rows): tagged by the column that differs

| Cell | Failure (step 2) | Step 2 | Differs in | Scenario(s) | Result | Words on the views |
|---|---|---|---|---|---|---|
| g1:1 | A 5-minute task fails on consecutive runs (e.g. refresh: our endpoint answered 500) | differs | holds, tries, words, Fix it, recovery | S-B16, S-B17, S-B18, S-C83, S-D110, S-D120 | ok | views: pending (the board's row and the part's card) |
| g1:2 | A nightly bulk task fails once (case #51: overture_bulk / foursquare_bulk timed out on our… | differs | holds, tries, words, Fix it, recovery | S-A11, S-A12, S-A13, S-A14, S-C85, S-C86 | FAIL | views: pending (the board's row and the part's card) |
| g1:3 | A night run where the run tasks are idle and the bulk task fails | differs | holds, tries, words, recovery | S-B49 | FAIL | views: pending (the board's row and the part's card) |
| g1:4 | An outside API times out once in a run (first strike) and the task's exception isn't caugh… | differs | holds, tries, words (two alarms for one cause) | S-B24 | FAIL | views: pending (the board's row and the part's card) |
| g1:5 | Overture's S3 bucket or Hugging Face (Foursquare) is down for the DuckDB reads | differs | holds, words (no service issue) | S-B24 | FAIL | views: pending (the board's row and the part's card) |
| g1:6 | A request (crawl_requests, find_name or read_page) fails | missing | words (nothing on any surface) | S-B74, S-B75 | FAIL | views: pending (the board's row and the part's card) |
| g1:7 | Our endpoint is slow (×3 its usual, with at least 20 earlier calls) | differs | holds (filed under the workflow), words | S-B19, S-B20 | FAIL | views: pending (the board's row and the part's card) |
| g1:8 | Our endpoint is slower than the worker's timeout (120 s per 500-row POST, 60 s GET) | differs | holds, tries, words | S-A11 | ok | views: pending (the board's row and the part's card) |
| g1:9 | Our endpoint is slow with under 20 calls of history (a nightly load's first nights) | missing | tries (no alarm under 20 calls) | S-B19 | FAIL | — |
| g1:10 | An outside call is slow but answers within 60 s | missing | tries (no durations measured) | S-B19 | FAIL | — |
| g1:11 | An outside call takes longer than 60 s (socket.setdefaulttimeout(60)) | differs | tries | S-B24 | FAIL | — |
| g1:12 | A task is slow (its share runs out), so the run is starved | matches | — (matches) | S-B47 | FAIL | — |
| g1:13 | An outside API at a back-off kind (timeout, 5xx), reported by the crawler | differs | tries, words, Fix it, recovery | S-A1, S-A2, S-A4, S-B26, S-C83, NAMED | FAIL | views: pending (the board's row and the part's card) |
| g1:14 | The same as #13, raised by the site's own call | matches | — (matches: recovery) | S-A5, S-C84 | ok | — |
| g1:15 | An outside API refuses our key, bans us, or is over quota | differs | words | S-A5b, S-B29, S-B31 | FAIL | views: pending (the board's row and the part's card) |
| g1:16 | An outside API answers 429 | matches | — (matches) | S-B27 | FAIL | — |
| g1:17 | A service's gate line is red (held, out of budget, key missing) | differs | holds | S-A7, S-B26, S-B46 | ok | — |
| g1:18 | A need has no working source (pf:nd:<need> trips) | differs | holds | S-B45 | ok | — |
| g1:19 | A backup's own pre-flight line is red while the primary works | differs | holds (minor) | S-B42 | FAIL | — |
| g1:20 | A workflow's own pre-flight line is red on a part that isn't a need | differs | holds, words | S-A3, S-A8, S-B46, S-B71, NAMED | FAIL | views: pending (the board's row and the part's card) |
| g1:21 | A Railway key the worker hasn't reported yet (Q4) | differs | holds, words | S-B30 | not on the bench | views: pending (the board's row and the part's card) |
| g1:22 | A site blocks W0 or W3 (a refusal) | matches | — (matches) | S-B32, S-B35 | FAIL | — |
| g1:23 | The Census geocoder fails on the site's connection | matches | — (matches) | S-B43 | FAIL | — |
| g1:24 | Our endpoint answers 5xx (three calls) | differs | holds, tries, Fix it, recovery | S-A13, S-B21, S-B22, S-D93, S-D94 | ok | — |
| g1:25 | Our endpoint answers 406 (ModSecurity) or 4xx | differs | holds, tries, words | S-B23 | FAIL | views: pending (the board's row and the part's card) |
| g1:26 | Bluehost's bot check (409 humans_) refuses the worker twice | missing | holds (nothing raised) | S-B23 | FAIL | — |
| g1:27 | Our site is unreachable at a run's start | differs | holds, words (blames the crawler) | S-B47 | FAIL | views: pending (the board's row and the part's card) |
| g1:28 | Our site times out on the run's report call | differs | tries, recovery (the run's data lost) | S-B47 | FAIL | — |
| g1:29 | Every task fails, so the run "failed outright" | differs | holds, tries, recovery | S-B48, S-B49 | FAIL | — |
| g1:30 | A run is killed before it reports (Railway's 30-min RUN_LIMIT, a crash) | differs | tries | S-B47, S-B48 | FAIL | — |
| g1:31 | No run at all (Railway down, schedule off, deploy failed) | differs | tries, words (no board alarm) | S-B47, S-B48 | FAIL | views: pending (the board's row and the part's card) |
| g1:32 | The crawler's own check (Check on wk:crawler, line rw:crawler) | differs | recovery (the check always passes) | S-B48 | ok | — |
| g1:33 | W0 Indexer stops checking in (over 15 min) | matches | — (matches) | S-B50 | ok | — |
| g1:34 | The video worker is down | missing | tries (no check-in) | S-B50 | ok | — |
| g1:35 | Any crawler-side failure against a critical duty | matches | — (matches) | S-B48 | ok | — |

### Sources and services (g2-services.md §1, 31 rows): by finding

| Cell | Failure (step 2) | Step 2 | Differs in | Scenario(s) | Result | Words on the views |
|---|---|---|---|---|---|---|
| g2:1 | Key refused (401) | differs | F1, F2 (recovery, Fix it) | S-A5, S-B29, S-D93, S-D106, S-G129 | FAIL | views: pending (the card) |
| g2:2 | Key refused (401) | differs | F2 (recovery) | S-B29, S-B31, S-D106, S-G129 | FAIL | views: pending (the card) |
| g2:3 | Key refused | missing | F5 (classify: a task failure) | S-B24, S-B38b | FAIL | — |
| g2:4 | Key missing | matches | — (matches) | S-B30, S-D107 | not on the bench | — |
| g2:5 | Token refused | missing | missing: no fault on the host part | S-B51, S-B52, S-B60 | not on the bench | — |
| g2:6 | Quota at the provider (429 RESOURCE_EXHAUSTED) | differs | F5 (classify, holds for good) | S-B36, S-B37, S-G128 | FAIL | — |
| g2:7 | Out of credit at the provider (400 "credit balance is too low") | missing | F5 (classify: nothing raised) | S-B38, S-G128 | FAIL | — |
| g2:8 | Our cap used up (100%) | matches | — (matches) | S-B40, S-B41, S-D109 | ok (some not on the bench) | — |
| g2:9 | Near a cap (50/75/95%) | differs | F10 (words: the band) | S-B39 | not on the bench | views: pending (the card) |
| g2:10 | Gemini's own day used (our count) | matches | — (matches) | S-B36, S-B37a, S-B41a | FAIL | — |
| g2:11 | Rate limit (429), with or without Retry-After | matches | — (matches) | S-B27 | FAIL | — |
| g2:12 | Rate limit (429) | differs | F6 (recovery: nothing re-checks) | S-B27, S-B28 | FAIL | — |
| g2:13 | S3 SlowDown (503 "reduce your request rate") | differs | — (matches; L12 words) | S-B27, S-B28, W-27 | FAIL | views: pending (the card) |
| g2:14 | A ban (403/406/418, Cloudflare's challenge) | differs | F1, F7 (no probe; recovery) | S-B32, S-B33, S-B34, S-D91, S-D108 | FAIL | — |
| g2:15 | A site that blocks us (W0's pages, sources' own sites) | matches | — (matches) | S-B35, S-D108 | ok | — |
| g2:16 | Server errors (5xx), repeated | differs | F1, F3, F4 (tries, recovery, words) | S-A1, S-A2, S-B24, S-B26, S-C83, S-G130 | FAIL | views: pending (the card) |
| g2:17 | Server errors (5xx) | differs | F4 (tries) | S-A1, S-A2, S-A6, S-B25, NAMED | FAIL | — |
| g2:18 | Timeouts, many in one run | differs | F4 (tries: a burst trips) | S-B24 | FAIL | — |
| g2:19 | A back-off, a success, then one more failure hours later | differs | F4 (tries: a success ignored) | S-B25 | ok | — |
| g2:20 | Timeout or 5xx at a critical part | differs | F3 (recovery: not Recovered) | S-B38a, S-C87, S-C90, S-E123 | FAIL | — |
| g2:21 | Any tripped back-off, closed by the system | differs | F3 (recovery: the thread not updated) | S-B25, S-C84, S-C90 | FAIL | — |
| g2:22 | A tripped back-off whose host is back, with nobody pressing | differs | F3 (recovery: never re-checked) | S-A2, S-A4, S-C89, S-C90, S-D91, NAMED | FAIL | — |
| g2:23 | A person presses Check and start again on a service's trip | differs | F1 (recovery: no check) | S-A5, S-A5b, S-D93, S-D94 | ok | — |
| g2:24 | Mark resolved | differs | F10 (Mark resolved) | S-D95 | FAIL | views: pending (the card) |
| g2:25 | A malformed answer (an empty or unreadable 200) | missing | F5 (classify: malformed unseen) | S-B24 | FAIL | — |
| g2:26 | A malformed answer | differs | F4 (tries) | S-B24 | FAIL | — |
| g2:27 | A source down, a backup in use | differs | F8 (backup not said) | S-B42, S-B43, S-B44 | FAIL | views: pending (the card) |
| g2:28 | A source down: what the gate holds | matches | — (matches) | S-B26 | ok | — |
| g2:29 | A site-only issue on a service the crawler also uses | differs | F9 (holds too wide) | S-A6, NAMED | FAIL | — |
| g2:30 | The Census geocoder down on the connection | differs | F8 (an alarm beside a working backup) | S-B43 | FAIL | views: pending (the card) |
| g2:31 | Overture's regional S3 host | differs | F9 (filed under AWS) | S-B24 | FAIL | — |

### Jobs, checks and hosts (g3-jobs-hosts.md §1, 38 rows): by finding F1–F11

| Cell | Failure (step 2) | Step 2 | Differs in | Scenario(s) | Result | Words on the views |
|---|---|---|---|---|---|---|
| g3:J1 | A site job that serves no workflow stops once (sales_tick, hourly) | differs | F3, F6 (tries, words) | S-B62, S-C83, S-C86 | FAIL | — |
| g3:J2 | A job tied to a workflow stops 3 runs in a row (listings_tick → W1) | differs | F1 (holds, recovery) | S-B63, S-B64 | ok | — |
| g3:J3 | A job tied to three workflows stops (guide_finds_tick → W4, W8, W10) | differs | F1 (holds) | S-B63 | ok | — |
| g3:J4 | A job stops, then no run comes for over 2 days (its cron line gone, or the host's cron dow… | differs | F1, F4 (recovery: a false clear) | S-B66 | FAIL | — |
| g3:J5 | A run never finishes (killed or hung). Live L7: identity_recheck_tick (W2), started 2026-1… | differs | F4 (tries: slow never trips) | S-B66 | FAIL | — |
| g3:J6 | A critical job is stuck (billing_tick, duty: payments) | differs | F2 (critical) | S-B68 | FAIL | — |
| g3:J7 | A late job: scheduled but it didn't start (sales_tick, last run 6 h ago; its late limit is… | missing | missing (lateness) | S-B65 | FAIL | views: pending (Scheduled tasks, Paused now) |
| g3:J8 | A silent job: one that never ran, or the watch itself (control_tick) stopped | missing | F2 (critical, silent) | S-B55 | not on the bench | — |
| g3:J9 | A quiet job's silence (releases_tick is in QUIET): no look in git for 30 min | differs | F2 (critical) | S-B57, S-B68 | FAIL | — |
| g3:J10 | A job is slow: its last run took over 3× its usual time (and over a minute) | matches | — (matches) | S-B67 | ok | — |
| g3:J11 | A job runs, but its own record of the run failed (the database refused the insert) | missing | missing (falls into J7/J8) | S-B55 | not on the bench | — |
| g3:J12 | A critical job stops once (billing_tick, daily) | differs | F3 (tries) | S-B68, S-C85, S-C86 | FAIL | — |
| g3:C1 | A required pre-flight line goes red on a component that isn't critical (svc:tavily, its us… | differs | F3 (tries, holds) | S-B71, S-C85, S-C86 | FAIL | — |
| g3:C2 | A workflow's own pre-flight line goes red on a part that isn't one of its needs (live L1, … | differs | holds (the workflow whole) | S-A3, S-A8, S-B46, NAMED | FAIL | — |
| g3:C3 | A critical site line goes red with the master switch OFF (site:db, site:sched) | differs | F2 (critical with the master off) | S-B54, S-B72 | FAIL | — |
| g3:C4 | A critical site line goes red with the master ON | differs | F3 (tries) | S-B54, S-B72, S-C85, S-C88 | FAIL | — |
| g3:C5 | Bluehost's line goes red because cPanel's API didn't answer (email fine, pages fine) | differs | F5 (Bluehost's line) | S-B56 | ok | — |
| g3:C6 | Diagnostics on an active module: the watch's re-test of a line not tested in 6 h | partial | partial (re-tests every 6 h) | S-B71 | ok | — |
| g3:C7 | Start testing: a dependency is stale or red at Start (refreshFor) | matches | — (matches) | S-B73, S-D112 | ok (some not on the bench) | — |
| g3:C8 | Start's watch: a switched-on item's gate fails (failures over 1 in 10, a new service issue… | differs | F7 (a person's switch written) | S-B73 | ok | views: pending (Scheduled tasks, Paused now) |
| g3:C9 | A red line in the switch-on check | matches | — (matches) | S-A9, S-D112 | not on the bench | — |
| g3:C10 | The search sensor's test fails (or ≥3 server errors in 5 min on search's paths) | matches | — (matches) | S-B69, S-C87 | ok | — |
| g3:C11 | The sign-in sensor's test fails (session store or accounts table), or ≥3 errors on its pat… | matches | — (matches) | S-B69 | ok | — |
| g3:C12 | QuickBooks needs reconnecting (refused, or its token ran out) | matches | words (credits the system) | S-B70 | FAIL | views: pending (Scheduled tasks, Paused now) |
| g3:C13 | Page load has no sensor | missing | F2 (missing: no page sensor) | S-B53 | not on the bench | — |
| g3:C14 | One database realm is refused mid-day (admin, listings or users) | missing | missing (no watch-level check) | S-B54 | ok | — |
| g3:C15 | Pulse's clock at a trip | differs | F9 (Pulse's clock) | S-E121, S-E122 | FAIL | — |
| g3:D1 | Early trip: a critical part's first failure, of kind retry, crash or silent | matches | words (Paused with nothing paused) | S-B68, S-B69, S-C87 | FAIL | views: pending (Scheduled tasks, Paused now) |
| g3:D2 | A critical part fails, but the alarm's kind isn't retry, crash or silent (slow for stuck, … | differs | F2 (critical by kind) | S-B57, S-B68, S-B72 | FAIL | — |
| g3:D3 | Still trying: the early trip's tries run out | differs | F3 (tries) | S-B69, S-C86 | FAIL | — |
| g3:D4 | The Escalation Notice | matches | — (matches) | S-B69, S-C88 | FAIL | — |
| g3:D5 | Recovered, after an early trip or after escalation | matches | — (matches; exceptions J2, C12) | S-B64, S-B70, S-C90, S-E123 | FAIL | — |
| g3:D6 | A duty is mapped to a part whose check tests something else | differs | F5 (duty on the wrong part) | S-B53, S-B56 | ok (some not on the bench) | — |
| g3:M1 | The master OFF | matches | — (matches) | S-A7, S-B64, S-D111 | ok | — |
| g3:M2 | Pause everything | matches | — (matches) | S-D111 | ok | — |
| g3:M3 | Shut down the whole system | partial | F10 (partial: dependants) | S-D113 | not on the bench | views: pending (Scheduled tasks, Paused now) |
| g3:M4 | Take out (maintenance): a workflow or a task | matches | — (matches) | S-B79, S-D103, S-D104 | FAIL | — |
| g3:M5 | Maintenance for a module (a service, a host, a job, a worker, the site) | missing | F8 (missing) | S-D103 | FAIL | views: pending (Scheduled tasks, Paused now) |

### Channels (g4-channels-views.md (a), 69 cells: 17 states × board, Pulse, email, push, and the logbook): tagged by what differs

| Cell | Failure (step 2) | Step 2 | Differs in | Scenario(s) | Result | Words on the views |
|---|---|---|---|---|---|---|
| g4:1B | Warning while trying: a non-critical task's 1st failure (A) — board | differs | words (status, raw id in What it affects) | S-A1, S-B16, S-B21, S-C83, W-1 | FAIL | views: pending (the board row is control-console.php's) |
| g4:1P | Warning while trying: a non-critical task's 1st failure (A) — Pulse | matches | — (matches) | S-A1 | ok | — |
| g4:1E | Warning while trying: a non-critical task's 1st failure (A) — email | matches | — (matches) | S-A1 | ok | — |
| g4:1Pu | Warning while trying: a non-critical task's 1st failure (A) — push | matches | — (matches) | S-A1 | ok | — |
| g4:2B | Trip: its tries ran out (B) — board | differs | words (the raiser's "carry on" against the hold) | S-A2, S-A14, S-B17, S-B22, W-11 | FAIL | views: pending (the board row is control-console.php's) |
| g4:2P | Trip: its tries ran out (B) — Pulse | differs | words (the raiser's "carry on" against the hold) | S-A2, S-B17, S-B22, S-E121, W-21 | FAIL | — |
| g4:2E | Trip: its tries ran out (B) — email | differs | words (the raiser's "carry on" against the hold) | S-A2, S-B17, S-B22, W-2, W-21 | FAIL | — |
| g4:2Pu | Trip: its tries ran out (B) — push | matches | — (matches) | S-A2, S-B17, S-B22, W-2, W-21 | FAIL | — |
| g4:3B | The trip acknowledged (B2) — board | differs | words, state (an acknowledged trip drops the count) | S-A15, S-D96 | ok | views: pending (the board row is control-console.php's) |
| g4:3P | The trip acknowledged (B2) — Pulse | matches | — (matches) | S-A15, S-D96 | ok | — |
| g4:3E | The trip acknowledged (B2) — email | matches | — (matches) | S-A15 | ok | — |
| g4:3Pu | The trip acknowledged (B2) — push | matches | — (matches) | S-A15 | ok | — |
| g4:4B | A critical part's first failure: trip at once, still trying (C) — board | matches | — (matches) | S-B69, S-C87, W-69 | ok | views: pending (the board row is control-console.php's) |
| g4:4P | A critical part's first failure: trip at once, still trying (C) — Pulse | differs | words ("Paused" with nothing paused) | S-B69, S-C87, W-69 | ok | — |
| g4:4E | A critical part's first failure: trip at once, still trying (C) — email | differs | words ("Paused" with nothing paused) | S-B69, S-C87, W-69 | ok | — |
| g4:4Pu | A critical part's first failure: trip at once, still trying (C) — push | differs | words ("Paused" with nothing paused) | S-B69, S-C87, W-69 | ok | — |
| g4:5B | Escalated: a critical part's tries out (D) — board | matches | — (matches) | S-C88 | FAIL | views: pending (the board row is control-console.php's) |
| g4:5P | Escalated: a critical part's tries out (D) — Pulse | matches | — (matches) | S-C88 | FAIL | — |
| g4:5E | Escalated: a critical part's tries out (D) — email | differs | words ("(2 of these are waiting.)") | S-C88 | FAIL | — |
| g4:5Pu | Escalated: a critical part's tries out (D) — push | matches | — (matches) | S-C88 | FAIL | — |
| g4:6B | Recovered by the system (E1, E2) — board | differs | words (What it affects after close) | S-A10, S-C90, W-90 | FAIL | views: pending (the board row is control-console.php's) |
| g4:6P | Recovered by the system (E1, E2) — Pulse | matches | — (matches) | S-A10, S-C90, W-90 | FAIL | — |
| g4:6E | Recovered by the system (E1, E2) — email | differs | words (What it affects after close) | S-A10, S-C90, W-90 | FAIL | — |
| g4:6Pu | Recovered by the system (E1, E2) — push | matches | — (matches) | S-A10, S-C90, W-90 | FAIL | — |
| g4:7B | Closed by a person: Check and start again (F), or a key fixed (ServiceHealth::resolve uid … | matches | — (matches) | S-A5, S-D93, W-93 | ok | views: pending (the board row is control-console.php's) |
| g4:7P | Closed by a person: Check and start again (F), or a key fixed (ServiceHealth::resolve uid … | differs | recovery (no check), words (the thread not closed) | S-A5, S-A10, S-D93, S-D98, W-93 | FAIL | — |
| g4:7E | Closed by a person: Check and start again (F), or a key fixed (ServiceHealth::resolve uid … | missing | recovery (no check), words (the thread not closed) | S-A5, S-A10, S-D93, S-D98, W-93 | FAIL | — |
| g4:7Pu | Closed by a person: Check and start again (F), or a key fixed (ServiceHealth::resolve uid … | matches | — (matches) | S-A5, S-D93, W-93 | ok | — |
| g4:8B | Notice: on a backup (G) — board | matches | — (matches) | S-B43 | FAIL | views: pending (the board row is control-console.php's) |
| g4:8P | Notice: on a backup (G) — Pulse | matches | — (matches) | S-B43 | FAIL | — |
| g4:8E | Notice: on a backup (G) — email | matches | — (matches) | S-B43 | FAIL | — |
| g4:8Pu | Notice: on a backup (G) — push | matches | — (matches) | S-B43 | FAIL | — |
| g4:9aB | A service's issue, key refused (trip at once, H1) — board | differs | words ("Nothing could be paused") | S-B29, W-29 | FAIL | views: pending (the board row is control-console.php's) |
| g4:9aP | A service's issue, key refused (trip at once, H1) — Pulse | differs | words ("Nothing could be paused") | S-B29, W-29 | FAIL | — |
| g4:9aE | A service's issue, key refused (trip at once, H1) — email | matches | — (matches) | S-B29, W-29 | FAIL | — |
| g4:9aPu | A service's issue, key refused (trip at once, H1) — push | matches | — (matches) | S-B29, W-29 | FAIL | — |
| g4:9bB | A service's issue, their 503 SlowDown reported as server (H2) — board | differs | words (a SlowDown as "Its server failed") | S-B27, W-27 | FAIL | views: pending (the board row is control-console.php's) |
| g4:9bP | A service's issue, their 503 SlowDown reported as server (H2) — Pulse | matches | — (matches) | S-B27, W-27 | FAIL | — |
| g4:9bE | A service's issue, their 503 SlowDown reported as server (H2) — email | matches | — (matches) | S-B27, W-27 | FAIL | — |
| g4:9bPu | A service's issue, their 503 SlowDown reported as server (H2) — push | matches | — (matches) | S-B27, W-27 | FAIL | — |
| g4:9cB | A service's back-off spent: 6 timeouts → trip (H4) — board | differs | words (two contradicting sentences) | S-A2, S-B26 | FAIL | views: pending (the board row is control-console.php's) |
| g4:9cP | A service's back-off spent: 6 timeouts → trip (H4) — Pulse | differs | words (two contradicting sentences) | S-A2 | FAIL | — |
| g4:9cE | A service's back-off spent: 6 timeouts → trip (H4) — email | differs | words (two contradicting sentences) | S-A2, W-2 | FAIL | — |
| g4:9cPu | A service's back-off spent: 6 timeouts → trip (H4) — push | matches | — (matches) | S-A2, W-2 | FAIL | — |
| g4:10B | Grouped: two out of budget at once (I) — board | matches | — (matches) | S-F124, S-F127 | FAIL | views: pending (the board row is control-console.php's) |
| g4:10P | Grouped: two out of budget at once (I) — Pulse | matches | — (matches) | S-F124, S-F127 | FAIL | — |
| g4:10E | Grouped: two out of budget at once (I) — email | differs | messages (one per member, not per group) | S-F124 | FAIL | — |
| g4:10Pu | Grouped: two out of budget at once (I) — push | differs | messages (one per member, not per group) | S-F124 | FAIL | — |
| g4:11B | Correlated: same host and signature in a short window (case #51's pair) — board | missing | correlation (missing) | S-A11, S-A12, S-F124, W-11 | FAIL | views: pending (the board row is control-console.php's) |
| g4:11P | Correlated: same host and signature in a short window (case #51's pair) — Pulse | missing | correlation (missing) | S-A12, S-F124 | FAIL | — |
| g4:11E | Correlated: same host and signature in a short window (case #51's pair) — email | missing | correlation (missing) | S-A12, S-F124 | FAIL | — |
| g4:11Pu | Correlated: same host and signature in a short window (case #51's pair) — push | missing | correlation (missing) | S-A12, S-F124 | FAIL | — |
| g4:12B | A cap warning whose reading moved (J; live L5/L6) — board | differs | words (the band, not the reading) | S-B39, W-12 | not on the bench | views: pending (the board row is control-console.php's) |
| g4:12P | A cap warning whose reading moved (J; live L5/L6) — Pulse | matches | — (matches) | S-B39 | not on the bench | — |
| g4:12E | A cap warning whose reading moved (J; live L5/L6) — email | matches | — (matches) | S-B39 | not on the bench | — |
| g4:12Pu | A cap warning whose reading moved (J; live L5/L6) — push | matches | — (matches) | S-B39 | not on the bench | — |
| g4:13B | A trip on a site job tied to no workflow (K) — board | matches | — (matches) | S-B62, W-62 | FAIL | views: pending (the board row is control-console.php's) |
| g4:13P | A trip on a site job tied to no workflow (K) — Pulse | differs | words (raw id; "Paused" with nothing paused) | S-B62, W-62 | FAIL | — |
| g4:13E | A trip on a site job tied to no workflow (K) — email | differs | words (raw id; "Paused" with nothing paused) | S-B62, W-62 | FAIL | — |
| g4:13Pu | A trip on a site job tied to no workflow (K) — push | differs | words (raw id; "Paused" with nothing paused) | S-B62, W-62 | FAIL | — |
| g4:14B | No active founder (extra N) — board | matches | — (matches) | S-B80 | FAIL | views: pending (the board row is control-console.php's) |
| g4:14P | No active founder (extra N) — Pulse | missing | messages (a trip to no one) | S-B80 | FAIL | — |
| g4:14E | No active founder (extra N) — email | missing | messages (a trip to no one) | S-B80 | FAIL | — |
| g4:14Pu | No active founder (extra N) — push | missing | messages (a trip to no one) | S-B80 | FAIL | — |
| g4:15B | Email channel paused by message control, then a trip (extra L) — board | matches | — (matches) | S-B82 | ok | views: pending (the board row is control-console.php's) |
| g4:15P | Email channel paused by message control, then a trip (extra L) — Pulse | matches | — (matches) | S-B82 | ok | — |
| g4:15E | Email channel paused by message control, then a trip (extra L) — email | differs | messages (Critical held by message control) | S-B82 | ok | — |
| g4:15Pu | Email channel paused by message control, then a trip (extra L) — push | matches | — (matches) | S-B82 | ok | — |
| g4:16 | The logbook: the moment it tripped isn't logged | missing | words (the logbook) | W-16 | FAIL | — |
