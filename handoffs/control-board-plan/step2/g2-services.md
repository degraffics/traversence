# Step 2, g2: Source and Service modules (outside companies) against the model

Audit of the live code at the mirror's `37a212e` (Sync 2026-11-21 12:26 UTC; `sync-app/` = `app/`, `sync-storage/` =
`website/`) and the crawler 2.16.1 (`workers/crawler/crawler.py`). Graded against step 1 §1 (1.1 what a trip is, 1.2 a
service in its workflows, 1.3 the two clocks), §3 and §4, and the plug-in contract in Jason's approved report (role,
criticality, required keys, sensors, Incident Radius, Fix it routes; the four-zone card). No repo file or the mirror was
changed. Paths below are `lib/…` = `sync-app/lib/…`, `api.php` = `sync-storage/admin/api.php`, `crawler.py` =
`workers/crawler/crawler.py`.

**How it was proved.** On the g2 copy (`…/scratchpad/g2/y/live/app`, database `lv_g2` only), reset from
`release-b-tests/start-schema.sql`, with email and push stood in for (`Mailer`/`WebPush` replaced in a throwaway copy,
so nothing left). Probes, and their output, in `…/scratchpad/step2/probes/g2/`:
- `p1-services.php` → `p1.out`: the classifier on 12 answers; a site 401 (Claude), Check and start again on it; a 429
  with Retry-After eight times; S3 SlowDown; a 5xx ladder 1→6 and recovery; a back-off with a success between; Gemini's
  RESOURCE_EXHAUSTED; 409; a crawler 401 at Brave; the crawler's 503 ×6 and the site's check closing it; a crawler 429
  with Retry-After 3600.
- `p3-more.php` → `p3.out`: Claude out of credit (400); Gemini's empty 200; Gemini's quota 26 h later; a refused key's
  Check after Replace; the watch's recheck filter; a crawler 429 left 3 days; the classifier's edges; a crawler 401 at
  Tavily through the gates.
- `p5-close.php` → `p5.out`: Check and start again on a tripped back-off with the host still down; the system's own
  close of a tripped back-off and of a critical part's trip (is the thread told Recovered?); 6 timeouts in one run;
  Overture's regional host.
- `p4-lines.php`: every one of the 44 services has a `svc:` pre-flight line.
- Existing tests re-run on this copy (`probes/g2/tests/*.out`): `card-issue` 15/15, `r5a-rate` 18/18, `backoff-gate`
  12/12, `r4f` 7/7, `feeds-words` 9/9, `keys` 30/30, `holds` 24/24, `sensor` 30/30, `r1` 33/33, `itsdata` 22/22, `r4c`
  9/9, `lines` 33/33, `r2d` 9/9, `expire-tries` 9/9, `critical` 20/20, `r4e` 11/11, `links` 9/9;
  `tools/page-audit/service-defs.php --check`: "The outside services' lists match". `defs.php`, `register.php`,
  `modules.php` didn't run here (their tables come from migrations no longer in the mirror): not graded on them.
- Tables the probes made from their use in code: `control_ledger`, `control_records` (`probes/g2/ledger.sql`),
  `system_pauses` (as `head.php`), `service_stages` (`probes/stages.sql`).

**What the code does, in one paragraph.** One open issue per host and caller (`service_issues`; caller = the site on
Bluehost, or the crawler on Railway). Each failure is classified (`ServiceHealth::classify`, `lib/ServiceHealth.php:183-200`;
the worker's copy `crawler.py:267-288`) into a **stop** kind (ban, auth, quota: held at once, a trip, waits for a
person) or a **back** kind (rate, timeout, server, malformed: a back-off of 10 min doubling to 6 h, or the server's
Retry-After, `ServiceHealth.php:276-287`). A back-off is a warning until **6 failures** or **6 hours open**, then a trip
held "until an admin resolves it" (`ServiceHealth.php:171-173, 349-355`); a 429 never trips (`:349`). The site's own
callers ask `allowed()`/`held()` before and report `seen()` after (`:496-549`); the crawler's plan carries `holds()` and
its report carries `issues` (`:462-472, 573-589`; `crawl/CrawlService.php:218`). Closing: a back kind closes when the
host answers (`answered()`, `:290-304`); a stop kind only by a person. Caps are a separate path (`Operations::alarms`,
`lib/Operations.php:595-616`): warnings at 50/75/95%, a trip at 100% that pauses the service.

---

## 1. The failure map: services and sources

Clocks: **run** = the run schedule (a crawler run every 5 min, or the site's next call when a visitor or job makes one);
**diag** = the diagnostic clock (the system re-checking the component: `control_fix_tries` 3 / `control_fix_minutes` 60).
Words are given as board (alarm title / means / todo) · Pulse (the alarm's item and the `svc:` thread) · email (subject) ·
push (title). Push and email go to the Founder only for a trip (`ControlAlarms.php:633-652`; proved: 1 message per
trip, 0 per warning, `p1.out` A/C/E).

| # | Failure | Part (role) | Holds: code now | Holds: the model says | Tries & trip: code now (which clock) | Model says (which clock) | Words now (board · Pulse · email · push) | Fix it now | Recovery now | Verdict | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Key refused (401) | Anthropic, the site's caller (Service) | Every site call to the host (`allowed()` refuses); no `system_pauses` row; gates via the service's red line | The component's calls only; tasks reroute or wait (1.2) | Trip at once, kind `held` (AT_ONCE). No tries | At once, waits for the person's Replace, whose check closes it (1.1) | Board "api.anthropic.com: Our key was refused (the site, on Bluehost)" · "…then press Check and start again: it checks first, and closes it when the check passes" · email "SYSTEM ALARM NOTICE: Anthropic (Claude): Paused by the system" · push the same title | `register#svc-anthropic-key` (Its key, Replace) | **Replace's Check can't reach the host**: the pre-flight's `held()` refuses a stop kind even with Check it now on, so Check reads "Held by issue #n"; Check it now has no probe ("No check to send"); the only exit is Check and start again, which **closes it without any check** ("Resolved by Founder: Checked and started again", calls resume) | differs | `p1.out` A, B; `p3.out` Q4; `ServiceHealth.php:32, 350-355, 417-425`; `Preflight.php:687-692`; `ControlAlarms.php:307-313, 347`; `admin/operations-console.php:60-70` |
| 2 | Key refused (401) | Brave or Tavily, called by the crawler, key on Railway (Source) | The host for the crawler (`holds('crawler')`); the service's line red, so the gate holds the tasks that call it unless a need is covered | The same, and the task reroutes (Tavily ↔ Brave) | Trip at once (stop kind) | At once; closed by the worker's report after the key is replaced on Railway (1.1, Q4) | Board "api.search.brave.com: Our key was refused (the crawler, on Railway)" · thread todo "put it where the card says, then press Check it now: a passing check closes it" · email/push as row 1 | `register#svc-brave-key` (the card names "on Railway: the crawler service → Variables") | The worker reports key **presence** only (`worker:keys`), never validity; no probe; a later run's good calls don't close a stop kind ("state=open"); exit is "It's fixed: try it again" or Check and start again, neither checks | differs | `p1.out` I; `p3.out` Q10; `Operations.php:166-205`; `crawler.py:214`; `ServiceHealth.php:294` |
| 3 | Key refused | Hugging Face (`HF_TOKEN`) for Foursquare's bulk load, read by DuckDB (Source) | Nothing as a service: the DuckDB read raises inside the task, so it's a **task failure** (the case #51 pattern: the whole workflow tripped) | That task only, Foursquare's calls paused; W1's other tasks run | The task's crash rule (tries counted by runs) | The component's check on diag (1.3) | A task alarm `task:failed:foursquare_bulk`, never "key refused" | None for the key | Only the task's path | missing | `crawler.py:3557-3590` (hf:// via DuckDB, not `urlopen`); `crawler.py:253-258` (`API_HOSTS` lacks huggingface.co, overturemaps…, download.geofabrik.de); `crawler.py:293` returns for other hosts; live L2/L3 |
| 4 | Key missing | Any service whose key isn't set (Service/Source) | The service reads "off: No key …" (a status, not an issue); its line red; tasks that need a Railway key report held ("no Hugging Face token on Railway") | Holds nothing until checked; then only the tasks that need it (Q4) | None (a status) | None: "not checked yet" until the worker reports | Card "Off · No key: X isn't in .env"; Railway keys "not checked yet" until the first report | Card's Add its key | Its own line passes once added; an issue opened before is closed by the watch's check as "Not set up" | matches | `Operations.php:292-296`; `ControlAlarms.php:1047`; `api.php:236`; `crawler.py:3783` |
| 5 | Token refused | Railway API or GitHub sync token for the watch (Host) | Nothing | What the token does only (the manifest's radius) | `watchTick` catches the 401 as `ok: null`, "Couldn't look: HTTP 401" | A fault on the Host part (1.1) | No alarm, no issue; the switch-on line reads the stored words | None | — | missing | `Operations.php:525-552` (`get()` throws, `ok => null`, never `ServiceHealth`) |
| 6 | Quota at the provider (429 RESOURCE_EXHAUSTED) | Gemini, the site's caller (Service) | Every call to the host, **for good**: classified `quota` (stop) because Google's text says "check quota", which it also says for its **per-minute** limit | Held until the next day (R164: "when its day is used it is held until the next day"); a per-minute limit is a rate wait | Trip at once | A quota waits for the day's turn or a person (1.1); a per-minute 429 is a warning | Board "…: Over its quota or plan (the site, on Bluehost)" · "Nothing calls … until this is resolved" | `register#svc-gemini-caps`: **our** `gemini_daily` cap, which doesn't lift Google's hold | No probe, so nothing lifts it: 26 h later still "Over its quota or plan since …" | differs | `p1.out` G; `p3.out` Q3; `ServiceHealth.php:188, 449` (a quota's return needs a probe; `PROBES` is empty, `:45-46`); `Gemini.php:226-229` |
| 7 | Out of credit at the provider (400 "credit balance is too low") | Anthropic, the site (Service) | Nothing held; each call throws to its caller | The component paused at once, waiting for the person (a used-up quota, 1.1) | None: `classify(400)` is '' | At once | **Nothing**: no issue, no alarm, no Pulse, no email | None | — | missing | `p3.out` Q1; `AiClient.php:380-384`; `ServiceHealth.php:198, 539-543` |
| 8 | Our cap used up (100%) | A capped service: Tavily's month, Places, the AI monthly cap, W0's budget (Service/Source) | A system pause on the service's automatic calls ("out of budget"), with its alarm; a person's presses still reach it | The same (a used-up budget, 1.1) | Trip at once (`limit` is AT_ONCE) | At once | Board "<name> is out of budget" · "Raise the cap, or pause it…" · email/push "SYSTEM ALARM NOTICE: <name>: Paused by the system" | `register#svc-<k>-caps`; buttons Raise the cap (a form), Pause it, Its register entry; Set checks at once ("Checked now: Fixed") | Under 50% again (cap raised, or the month turns): the alarm clears as Recovered and the pause lifts | matches | `Operations.php:595-616`; `ControlAlarms.php:28, 277-281, 150-170`; `admin/operations-console.php:84-96` |
| 9 | Near a cap (50/75/95%) | The same (Service/Source) | Nothing | Nothing (a warning) | Warning; climbs a level | Warning on the board only | Title **"<name> at 50%"** while its reading says 67% (the level crossed, not the reading: live L5, L6) | Raise the cap, Pause it | Clears itself under 50% | differs (words only) | `Operations.php:605`; live L5/L6 |
| 10 | Gemini's own day used (our count) | Gemini, the site (Service) | The feature's call waits ("until tomorrow, no fallback to Claude"); background stops at 80% | The same (R164, Q13) | None: `room()` refuses quietly | No alarm; warns only on the unexpected | `Gemini::unexpected`: background stopped before 18:00 three days, the reserve under 5% | AI use | Next UTC day | matches | `Gemini.php:155-192`; `Operations.php:442-443` |
| 11 | Rate limit (429), with or without Retry-After | Any site caller (Service) | Calls to the host wait (Retry-After exactly, else 20 min doubling); no gate goes red for it | A warning that waits, never a trip (R70) | Warning for ever; `liftRateTrips` turns an old rate trip back into a warning | The same | Board "…: Asked us to slow down" · "wait twice as long as usual… never a stop" · no email or push · Check it now greyed "next try at …, by itself" | None (their trouble) | Clears when the host answers | matches | `p1.out` C; `ServiceHealth.php:280-286, 349, 363-367, 373-390`; `r5a-rate` 18/18, `backoff-gate` 12/12 |
| 12 | Rate limit (429) | Wikidata via the crawler (Source) | The host for the rest of the run, then the wait | A warning that clears itself | Warning | Clears when it answers | Status "Its wait ended …: the next call tries it again" · todo "nothing to press" | None | **Only a later crawler call to that host closes it.** If no task calls it again, it stays open as a warning for good: the watch rechecks site issues only | differs | `p3.out` Q6; `ControlAlarms.php:1042` (`caller !== 'site'` skipped); live L11 (open 3 days) |
| 13 | S3 SlowDown (503 "reduce your request rate") | Common Crawl's data host, crawler or site (Source) | As a 429 | As a 429 | Warning (`rate`) | The same | "Asked us to slow down" | None | As row 12 | matches (new); differs (an issue opened before 2.14.1 keeps "Its server failed" until a new failure: live L12) | `p1.out` D; `ServiceHealth.php:194-196`; `crawler.py:282-283, 1603-1609`; `feeds-words` 9/9 |
| 14 | A ban (403/406/418, Cloudflare's challenge) | Any API host, site or crawler (Source/Service) | The host for that caller, at once | At once; never retried by the system; waits for Reactivate (R94, Q6) | Trip at once | At once | "Refused us" · "Nothing calls … until this is resolved" | `register#issue-<id>` ("That source's line, with Reactivate": Check it now "asks once") | **No probe exists** (`PROBES = []`), so Check it now sends nothing and the 3-day daily probe of R94 never runs; Check and start again or "It's fixed: try it again" resume calls with no look, so a still-blocking host is asked again at full pace | differs | `p1.out` classify; `p2-source.php` P2f; `ServiceHealth.php:45-46, 428-459, 417-425`; `Thread.php:35` |
| 15 | A site that blocks us (W0's pages, sources' own sites) | A website (Source, not a service) | That site, held for a person's Reactivate (`EngineCase::heldSites` in the crawler's holds) | The same (R94, R53) | None (never retried) | None | One notice "N sites blocked us", a warning past `w0_block_share` (5%) of the day's reads; never a trip | `wf-W0#cb-held` with Reactivate in place | Reactivate: one polite try | matches | `ServiceHealth.php:469-470`; `Operations.php:646-656`; `itsdata` 22/22 |
| 16 | Server errors (5xx), repeated | National Weather Service, openrouteservice, AZ 511, Census…: the site (Service) | Calls wait on the ladder (10, 20, 40, 80, 160 min); at the trip, held with no end ("2099") | The component's calls; tasks reroute or wait | Warning "(1 of 6)"… then a trip at the **6th failure**, counted per **failed call** (run clock), or 6 h from the first | 3 tries or 60 min of the **component's check** (diag), then a trip | Board "…: Its server failed" · means at the trip "**held until an admin resolves it**" · the thread's status on the same alarm "It closes as Recovered when its check passes" · todo "Press Check and start again: **it asks once now**…" | None (their trouble), correctly | See rows 22 and 23: no system recheck while tripped; a person's press doesn't ask | differs | `p1.out` E; `ServiceHealth.php:171-173, 285, 349-355, 741, 747` |
| 17 | Server errors (5xx) | Common Crawl via the crawler (Source) | The host for the crawler (2 failures in a run hold it for the run); trip at 6 | As row 16 | Trip once the reports' `n` reach 6 (two runs of 3) | As row 16 | As row 16, "(the crawler, on Railway)" | None | The **site's** 2xx at that host closes it ("It answered the site's check again: the crawler's calls start again"), never a refusal, key or quota, never a 429 in its wait | matches (the close); differs (the tries) | `p1.out` J; `ServiceHealth.php:299-303`; `crawler.py:310-316` |
| 18 | Timeouts, many in one run | Tavily via the crawler (Source) | The host | The component's check decides, on diag | **A trip from one run**: the worker sends `n = 6` and `failed()` adds them as 6 hits | Never a trip from one burst; 3 checks / 60 min | "Its back-off ran out (6 failures in a row): … held until an admin resolves it" · email and push to the Founder | None | As row 17 | differs | `p5.out` R4; `ServiceHealth.php:245, 256`; `crawler.py:298-301` |
| 19 | A back-off, a success, then one more failure hours later | Any site caller using `seen()` (Service) | The host, with no end | Nothing: the success was a passing check | The success isn't recorded (`seen()` returns on any 2xx), so the open issue's first failure stays the start of its "6 hours open": one more failure **7 h later trips it** (hits = 2) and emails the Founder | Tries reset by a pass; a trip only for 3 failed checks / 60 min | "Its back-off ran out (2 failures in a row)" (false: there was a success between) | None | The watch's hourly recheck (2 a watch, site issues only, while not held) usually closes it first: a narrow window, but a false trip when it doesn't | differs | `p1.out` F; `ServiceHealth.php:539, 349`; `ControlAlarms.php:1036-1050` |
| 20 | Timeout or 5xx at a critical part | Amazon SES (`svc:aws`, duty "procedures"), QuickBooks (payments) (Service) | Nothing yet: a critical part trips at once while the system keeps trying, nothing stopped | The same (1.1, R72) | Trip at once, `retrying_at` set; escalates when its hour runs out | The same | Email and push at once (1 message) | None | When it answers, the close goes through `ControlAlarms::resolve` as "Resolved by the system", **not** `recovered()`: no "Recovered: … was down n min" notice, and the thread stays "Paused by the system" | differs (recovery) | `p5.out` R3; `ControlAlarms.php:98-99, 155, 593-606`; `ServiceHealth.php:320-324`; `ControlRegister.php:88-104` |
| 21 | Any tripped back-off, closed by the system | Row 16/17's parts (Service/Source) | Lifts | Lifts | — | Closes as Recovered: a notice to the Founders on the same thread, nothing to press (R73, R75, R132) | `cleared_how` "Resolved by the system: Cleared by its check: It answered again."; the Pulse item leaves; **the email/push thread is never updated** (still "SYSTEM ALARM NOTICE: AZ 511 (ADOT): Paused by the system"); 0 messages | — | — | differs | `p1.out` E2 ("messages on recovery: 0"); `p5.out` R2; `ServiceHealth.php:320-324`; `ControlAlarms.php:299-359` vs `593-606` |
| 22 | A tripped back-off whose host is back, with nobody pressing | Site callers (Service) | Held with no end | Lifts when its check passes (1.1) | **The system never checks it again**: the watch's recheck skips any issue still `holding()`, and a trip's hold is "2099"; the nightly pre-flight's call is refused by the same hold; the board drops Check it now on a trip | The system keeps checking on diag; a pass closes it | "held until an admin resolves it" | None | Only a person's Check and start again (row 23) or the card's key Check (which, for a back kind, is let past the wait) | differs | `p3.out` Q5 (holding: wait-ended false, tripped true); `ControlAlarms.php:1042, 821`; `ServiceHealth.php:230-235, 394`; `Preflight.php:687-692` |
| 23 | A person presses Check and start again on a service's trip | Every service issue (Service/Source) | Lifts at once | Lifts only when its check passes; a red check keeps it open, saying why (R82) | No check: only `pf:` alarms are checked first; a `svc:issue` resolves its issue straight away (5 ms, no outside call, host still down) | Check first | Its own words promise "it checks first" / "it asks once now" | — | "Resolved by Founder: Checked and started again"; calls resume; a still-failing host re-opens a new issue on its next call | differs | `p5.out` R1; `p1.out` B; `ControlAlarms.php:307-313, 325-347`; `ServiceHealth.php:354, 747` |
| 24 | Mark resolved | Every service issue | — | Never offered (R122) | `svcresolve` still an API action and a Pulse step (`alarm_svcresolve`), hidden only on a trip's row; a warning's buttons can carry it | No Mark resolved anywhere | "Mark it resolved? Calls to it…" | — | Closes with no check | differs | `api.php:207-211`; `ControlAlarms.php:821, 833, 877-881`; `control-console.php:601, 609`; `Pulse.php:896` |
| 25 | A malformed answer (an empty or unreadable 200) | Gemini and other site callers (Service) | Nothing: `classify(200)` is '', so the kind `malformed` is never used on the site | A back-off fault on the component (KINDS has it) | None | A warning on diag | Nothing; the caller gets "Gemini didn't answer (200)" | None | — | missing | `p3.out` Q2, Q8; `ServiceHealth.php:37, 198`; `Gemini.php:225-229` |
| 26 | A malformed answer | API hosts via the crawler (Source) | As a back-off | As a back-off | `malformed()` notes it (a "runtime error" remark as a timeout) | Warning on diag | "Answered with something we can't read" | None | As row 17 | matches (kind); differs (tries, as rows 16–18) | `crawler.py:362-366` |
| 27 | A source down, a backup in use | Tavily → Brave in the crawler's search (Source) | The task switches by itself ("search: brave instead (tavily held …)") | Reroute, "On a backup", the switch logged (R61, R90) | — | — | The worker counts `search_switches` and sends them; **the site never reads them**, so no "On a backup" from what happened, and no logged switch; the board's "On a backup" comes only from a stored `nd:` line | — | The task goes back to Tavily when it's no longer held | differs | `crawler.py:1023, 1042-1074, 3980`; no reader of `search_switches` in `sync-app`/`sync-storage` (grep) |
| 28 | A source down: what the gate holds | Any service on a workflow's needs (Source) | `gateFor`: covered (holds nothing), its tasks only, or the whole workflow; a need with no source left pauses the tasks that call its services | The same (1.2, R87–R88) | — | — | "Partly working", "On a backup" | Its workflow's page | The gate re-reads the stored line results | matches (with a lag: "covered" reads `Preflight::result` stored up to 6 h old, re-run 3 a watch) | `Operations.php:934-989`; `ControlAlarms.php:264-272`; `holds` 24/24; `ControlAlarms.php:1003-1007` |
| 29 | A site-only issue on a service the crawler also uses | Census, Common Crawl, Wikidata… (Source) | The site's issue makes the service "held" in `Operations::status`, so its switch-on line is red and the **crawler's** tasks can be held by the gate, though the crawler's own calls work (and the Census backup runs through the crawler) | Only the failing component (the site's calls) | — | — | "Issue #n: …" on the crawler's workflow | — | — | differs | `Operations.php:292-310` (all callers' issues), `:783-805` (the line), `:964-983` |
| 30 | The Census geocoder down on the connection | The site's Census call (Service), the worker as its backup | The batch goes to Railway; answers placed `via: railway`; other refusals wait | The same (R96) | The site's `timeout` issue still runs its own ladder and can trip (an email to the Founder) while the backup carries the work | A backup in use is a working state, never a trip (R90) | The backup isn't said on the issue (live L13 "warning since 11-18") | — | A backup batch never counts as the site's answer, so the site's issue doesn't close by itself | differs (the alarm beside a working backup); matches (the backup) | `Geocoder.php:175-222, 338-357`; `census-backup.php` (15, not re-run here); live L13 |
| 31 | Overture's regional S3 host | Overture via the crawler (Source) | Its calls are filed under **Amazon Web Services**, a critical part ("procedures": system email) | Overture's card | — | — | Calls counted on AWS's card; an issue there would trip AWS at once and email the Founder | — | — | differs | `p5.out` R5; `crawler.py:3546` (`overturemaps-us-west-2.s3.us-west-2.amazonaws.com`); `service-defs.php:241` (only the global host); `OutsideServices.php:381-388`; `ControlRegister.php:100` |

**Counts (31 rows):** matches 7 (rows 4, 8, 10, 11, 15, 28, and 13 for new issues); differs 20; missing 4 (rows 3, 5, 7,
25). Rows 13, 17, 26 and 30 match in part and differ in part; each is counted once, by its harm (13 as matches, 17, 26,
30 as differs).

---

## 2. The contract: which manifest fields exist today

| Manifest field | Where it lives now | Status |
|---|---|---|
| **id, name, purpose, pricing, links** | `service-defs.php` `company` (44 entries), generated into `OutsideServices::COMPANIES`; the zip check refuses a hand edit (`service-defs.php --check` passed) | exists |
| **role** (Host / Source / Service) | Nowhere. The board files a service as "engine" when a need names it, else "services" (`ControlAlarms::systemOf`, `ControlAlarms.php:367-377`); Railway and GitHub are services in the same list | missing |
| **criticality** | Only in `ControlRegister::CRITICAL` (`ControlRegister.php:88-104`: bluehost, cloudflare, quickbooks, github, aws), editable on Thresholds & gates; not in `service-defs.php` | partial (exists, in the wrong place, five services) |
| **required_keys** | `service-defs.php` `keys` (18 services) → `Operations::KEYS`, with where each lives (`.env` or Railway) | exists |
| **sensors** | Implied, not declared: each service's `svc:` pre-flight line (44 of 44, `p4-lines.php`); the calls the site and the crawler report; `PROBES` (empty, `ServiceHealth.php:45-46`); the worker's key report (presence only). No per-service list of what is watched and how often | partial |
| **caps and paces** | `service-defs.php` `caps` (14) → `Operations::CAPS`, `PACES` | exists |
| **feeds** (workflows and tasks served) | `OutsideServices::CONTROLS` (`tasks`, `jobs`, `switches`; 14 with tasks), `Operations::users`; the needs in `Preflight::NEEDS`. Not in `service-defs.php` | partial (exists, outside the manifest) |
| **fallback order** | `Preflight::NEEDS` (each need's sources in order, ours first; `Preflight.php:58-98`); the crawler's own Tavily → Brave | exists (outside the manifest) |
| **cadence** per assignment | Not per service: the task's `every_minutes` and the bulk window live on the task | missing (as a manifest field) |
| **incident_radius_rules** | Derived at run time by `ControlAlarms::stop` and `Operations::gateFor` from CONTROLS and NEEDS; no declared rule per fault | missing (as a field; the behaviour is partly there) |
| **fix_it_routes** | One generic map by cause for every service: `ServiceHealth::fixOf` (key → `#svc-<k>-key`, quota → `#svc-<k>-caps`, ban → `#issue-<id>`, else none) and `Thread::FIX` (`Thread.php:32-38`). Not per service (e.g. Gemini's quota lands on our cap, a Railway key on a card that can't check it) | partial |
| **stage** (the eight stages) | `service-defs.php` `stage` (retired, removed), `added_off`, `ServiceStages` (proposed, active, retired, removed) | partial |
| **staging answer** | `service-defs.php` `staging` (45) | exists |
| **contact, notices, ledger** | `service-defs.php` `contact` (20), `notices`, `ledger` (4) | exists |

**The four-zone card, today:** Identity and role (name, stage, status with reason; no role, no criticality shown);
Capabilities and feeds (what it serves, via CONTROLS); Sensors and key readiness (its keys by name, its pre-flight line,
its issue box `card-issue` 15/15); Actions and Fix it (Pause / Activate, key Add / Replace / Remove / Put back, Caps'
Set, the issue's buttons). Three of four zones exist in substance; zone 1 lacks role and criticality, zone 3 lacks a
declared sensor list and a key's validity for Railway keys.

---

## 3. Grades: R40–R64 (area 3) and R157–R166 (area 10)

| R# | Grade | What the code does | Evidence |
|---|---|---|---|
| R40 | needs staging | `ControlLedger` writes one row per endpoint call (received, stored, updated, Review, refused by reason, code, ms, run) and the ids touched; `r2a.php` needs the endpoints on a PHP server and wasn't run here | `lib/ControlLedger.php`; `probes/g2/ledger.sql` (its columns) |
| R41 | partial | The ledger's columns hold ids and counts only (no name, email, phone or text); trimming in small batches not proved here | `ledger.sql`; `ControlLedger.php` |
| R42 | partial | "Its data" per process, newest first, each record or its call; `?run=N`; W0's index by state (`itsdata` 22/22). The requirement's own note stands: values before and after, and each record's source, aren't kept | `tests/itsdata.l.out` |
| R43 | works | Raised at the event: `ServiceHealth::failed` raises before it returns; endpoints through `ControlLedger`; the crawler's report through `CrawlService::report` → `fromReport` | `ServiceHealth.php:241-269`; `CrawlService.php:183-218` |
| R44 | partial | The watch catches silence (`r3a`, `quiet-job`, owned by the jobs audit); a "never finished" job stays a warning for ever (live L7) | `ControlAlarms.php:922-1060`; live L7 |
| R45 | works | The kinds Slow, Retry, Crash, Held/out, Near a limit, Wrong count exist, each with its rule | `ControlAlarms.php:28`, `KINDS`; `Operations.php:595-616`; `ControlLedger.php:123-138` |
| R46 | partial | A service backing off is seen (its issue, "1 of 6"); the worker's own retries of a call aren't reported (held, as the requirement says) | `ServiceHealth.php:353`; `crawler.py` (no retry count in the report) |
| R47 | works | Start's confirm re-tests its workflows' `nd:` lines and stale `svc:` lines and the query timings (`r1` 33/33) | `tests/r1.start.out` |
| R48 | works | Four benchmark queries with time, limit and verdict, and Re-test (`r1`) | `tests/r1.start.out` |
| R49 | works | One "route complete" line per route from the register; an unclaimed hop or missing endpoint red (`r1`) | `tests/r1.start.out` |
| R50 | works | An open pre-flight alarm's words follow its line (`r4c` 9/9); a line untested 6 h is re-run by the watch, 3 a watch | `ControlAlarms.php:1003-1007`; `tests/r4c.start.out` |
| R51 | works | The hook and the 5-minute test for search, sign-in, payments; 2 errors a notice, 3 a critical alarm (`sensor` 30/30) | `tests/sensor.start.out` |
| R52 | works | Pulse's gathering timed, past `control_pulse_ms` a board-only warning | `ControlSensor.php:147-159`; `Thresholds.php:96` |
| R53 | works | "N sites blocked us" a notice, a warning past `w0_block_share` of at least 20 read, up to two Reactivate buttons and Every blocked site; never a trip | `Operations.php:646-656`; `itsdata` 22/22 |
| R54 | missing | `control_cost_factor` doesn't exist anywhere (Q19) | grep: no match in `sync-app` |
| R55 | partial | A call that counted **more** than it received is a warning on its endpoint (live L9); one that counted **fewer** (records unaccounted for) raises nothing | `ControlLedger.php:122-125` |
| R56 | works | A check's parts in order; the first down red, a later one amber "Partly working" naming its step (`r2d` 9/9, `lines` 33/33) | `Preflight.php:39-43`; tests |
| R57 | works | "Diagnostics" once active, never "pre-flight", including a system-paused card (`lines`) | `tests/lines.start.out` |
| R58 | works | The system pre-flight in the Start-up stage with Run it again, not the top line (`lines`) | `tests/lines.start.out` |
| R59 | needs staging | The list's grouping, marks and Run all that survives leaving the page need a rendered check and an interrupted run | `Preflight.php:575-600` (run state) |
| R60 | partial | The switch-on check's lines with their fixes exist (`Operations::switchOnCheck`); a refused watch token reads as `ok: null` (row 5); the hub lines' scope not proved here | `Operations.php:779-835`, `:525-552`, `:922-925` |
| R61 | partial | The map's order (ours, free, paid) in `Preflight::NEEDS`; judged by need; paid backups by a director's override (`PaidOverride`). Each switch to a backup isn't logged on the site: the worker's `search_switches` is dropped (row 27) | `Preflight.php:58-98`; `crawler.py:3980` |
| R62 | works | A frequent task with 12 empty runs in 24 h (a bulk load on its first) is a warning that clears itself, per task | `Operations.php:91, 471-503, 618-623` |
| R63 | partial | Railway's latest deploy of **one** service (`RAILWAY_SERVICE_ID`) and the sync's last run, hourly while Watching is on, as a **Pulse case** `ops:watch:*`, not an alarm under the trip model; a refused token raises nothing | `Operations.php:504-578, 685-690` |
| R64 | partial | The endpoint hop's time is in the ledger and judged slow against its usual × `control_slow_factor`, per record for a batch (`r4e` 11/11); the other hops (source → worker, worker → endpoint) aren't timed | `ControlLedger.php:126-138` |
| R157 | partial | The Service register (`/admin/config/register`) lists every service once with its stage and ⋯; `register.php` couldn't run here (its tables come from migrations no longer in the mirror) | `admin/operations-console.php`; `ServiceStages.php` |
| R158 | partial | The one writer (`EnvSettings`): owner only, never on staging, the value never shown, `# OLD` kept until a Check passes (`keys` 30/30). But a Check after Replace is refused by the open issue's own hold for a refused key, so "a pass forgets the old one" can't happen while the issue is open (row 1) | `admin/operations-console.php:55-79`; `Preflight.php:687-692`; `p3.out` Q4 |
| R159 | partial | Railway's keys stay on Railway; the card says where ("on Railway: the crawler service → Variables"); its Check confirms only that the worker **has** the key, never that it works | `Operations.php:166-205`; `crawler.py:214` |
| R160 | works | Each service defined once; the code's lists generated; the check refuses a list that differs | `service-defs.php --check`: "match" |
| R161 | partial | Caps are one setting (`Thresholds`); the site's own callers go through `allowed()`/`held()` and `seen()`. Bypasses: the crawler's DuckDB reads (Overture's S3, Hugging Face) never pass the counted `urlopen` (not counted, not held); the operations watch's Railway and GitHub calls never pass `ServiceHealth` | `crawler.py:3536-3590`; `Operations.php:540-552` |
| R162 | partial | This month's use and cost by **our** count per service and against the caps; the provider's own count side by side, and "our count only", weren't found | `Operations.php:222-260, 384-416` |
| R163 | partial | Warnings at 50/75/95%, a trip at 100% with Raise the cap, Pause it and the entry as buttons, and Set's check on the spot. The title names the level crossed ("at 50%") while the reading is 67% (live L5) | `Operations.php:595-616`; `admin/operations-console.php:84-96` |
| R164 | partial | Gemini paced (`room()`: per minute, the day, 20% reserve, background at 80%), failed calls counted (`AiClient::record` with `failed`), warns only on the unexpected. But Google's own 429 RESOURCE_EXHAUSTED becomes a permanent stop, never lifted at the day's turn (row 6) | `Gemini.php:155-229`; `p3.out` Q3 |
| R165 | works | The key field owner only, with its reason; never on staging (`keys` 30/30) | `tests/keys.start.out` |
| R166 | partial | A hub's opening budget is a stage on its readiness meter and a switch-on line (`HubOpening`); its line on the register isn't proved | `HubOpening.php:4-21`; `Operations.php:922-925` |

**Counts (35 rows):** works 15; partial 17; broken 0; missing 1 (R54); needs staging 2 (R40, R59).

---

## 4. Findings, ranked by harm, as card specs

### F1. Check and start again closes a service's trip without checking (rows 1, 14, 16, 23)

- **Module and role:** every Source and Service (the issue's alarm, `svc:issue:<id>`).
- **Field / zone:** zone 4, Actions and Fix it; the manifest's `fix_it_routes` and `sensors` (the check it runs).
- **What the card shows:** the trip's one button, **Check and start again**, with the words "it checks first, and closes
  it when the check passes" / "it asks once now".
- **What it means:** the person believes the service was asked and answered.
- **What happens now:** `ControlAlarms::resolve` checks only `pf:` alarms; a `svc:issue` is resolved at once
  (`ControlAlarms.php:307-313, 347`), its calls resume, with no outside call (proved: 5 ms, the host still down; a
  refused key "Resolved"). For a ban, the next calls go to a host that blocked us, which KINDS itself warns turns a name
  ban into an address ban.
- **What it should do:** run the service's own check (its `svc:` line, with the hold set aside for that one request);
  a pass closes it as Fixed by the person; a fail keeps it open, saying what came back, the button greyed with the reason.
- **Step-4 scenario:** for each kind (auth, quota, ban, a tripped 5xx), with the host still failing: press it → the trip
  stays open, one outside request logged, the words name the answer; then make the host answer → press again → closed,
  "Fixed by <name>", calls resume.

### F2. A refused key has no working way out (rows 1, 2; R158, R159)

- **Module and role:** keyed Services on `.env` (Anthropic, Gemini, SES…) and keyed Sources on Railway (Tavily, Brave,
  Places, RIDB, Hugging Face).
- **Field / zone:** `required_keys`, `sensors` (a key's validity); zone 3 (Key readiness) and zone 4 (Fix it).
- **What the card shows:** Fix it → Its key, Replace ready ("Replace runs its Check at once", `Thread.php:33`).
- **What it means:** replace the key and the check closes the trip.
- **What happens now:** the Check is refused by the very issue it's meant to close (`Preflight.php:687-692`: a stop kind
  holds even with Check it now on; "Held by issue #n"); Check it now has no probe (`PROBES = []`); a Railway key's Check
  reads only that the worker has a value. The only exit is F1's button.
- **What it should do:** a key's Check is the one request that may pass its own key's hold; a pass closes the issue
  ("Fixed by <name>: its key was replaced"). For a Railway key, the worker reports each key's own test (one free call),
  and that report closes it (Q4).
- **Step-4 scenario:** cause a 401 at Claude on staging (a stand-in host); Replace with a good key → Check passes, the
  issue and its alarm close, the thread says Fixed; Replace with a bad one → still held, Put the old one back offered.
  The same for a Railway key through the worker's report.

### F3. Service trips never close as Recovered, and nothing re-checks them (rows 16, 20, 21, 22)

- **Module and role:** every Source and Service; critical parts (SES, QuickBooks) included.
- **Field / zone:** `sensors` (the diagnostic check); zone 3 (its last check and the next) and the thread's Status.
- **What the card shows:** "Paused by the system (a trip) … It closes as Recovered when its check passes" beside, on the
  same alarm, "held until an admin resolves it".
- **What it means:** two opposite promises; neither is kept.
- **What happens now:** the watch never checks a tripped issue (it skips anything `holding()`, `ControlAlarms.php:1042`;
  crawler issues aren't rechecked at all); when a call does pass, the close runs through the person's resolve path
  (`ServiceHealth.php:323`), so no `recovered()`: no "Recovered: … was down n min", the email and push thread stays
  "Paused by the system" (proved: 0 messages), and a critical part's fix is filed as "Resolved by the system".
- **What it should do:** the watch checks a tripped component on the diagnostic clock (its `svc:` line, past its own
  hold); a pass lifts the pause and closes it through `clear()` → Recovered, updating the same thread (email reply, Pulse
  row; no push), escalated or not (R73, R75, R132).
- **Step-4 scenario:** trip a 5xx at a stand-in host; leave it; make it answer → within one watch the alarm closes as
  "Recovered: … was down n min, the system fixed it.", the thread's email reply and Pulse row updated, push count 0. The
  same at `svc:aws` (critical) → Recovered to the Founders.

### F4. Tries counted on the wrong clock: false trips that email the Founder (rows 16, 17, 18, 19)

- **Module and role:** every Source and Service with a back-off kind (timeouts, 5xx).
- **Field / zone:** `incident_radius_rules` and the tries' setting; zone 3's "Trying again (1 of 3)".
- **What the card shows:** "Calls … try again by themselves (k of 6)", then "Its back-off ran out (n failures in a row)".
- **What it means:** the system tried n times and gave up.
- **What happens now:** each failed call counts as a try (a crawler report's `n = 6` trips from one run); the 6-hour
  wall runs from the first failure even after successes, because the site's `seen()` ignores a 2xx (proved: 2 failures 7
  h apart, a success between → a trip). Neither is the model's 3 checks / 60 min of the component's check.
- **What it should do:** failures open the fault; tries are the system's checks of the component on the diagnostic
  clock (`control_fix_tries`, `control_fix_minutes`); any 2xx from any caller closes a back-off issue; a burst inside one
  run is one fault.
- **Step-4 scenario:** report 6 timeouts in one run → one warning "Trying again (1 of 3)", no email; fail 3 checks →
  a trip, one email; a failure, a success, a failure 7 h later → two separate warnings, no trip.

### F5. Provider quotas misread or unseen (rows 6, 7, 25, 3)

- **Module and role:** Gemini and Anthropic (Services); Foursquare through Hugging Face, Overture and Geofabrik through
  bulk reads (Sources).
- **Field / zone:** `sensors` and `fix_it_routes` (per service, not generic); zone 2 (spend and quota strip).
- **What the card shows:** Gemini: "Over its quota or plan", Fix it → our own `gemini_daily` cap. Anthropic out of
  credit: **nothing**. A refused Hugging Face token: a task's crash on W1.
- **What it means:** Gemini looks stopped until we raise a cap that isn't the limit; Claude looks fine while every call
  fails.
- **What happens now:** Google's 429 RESOURCE_EXHAUSTED (sent for its per-minute limit too) is a stop kind with no probe,
  held for good (proved 26 h later); Anthropic's 400 "credit balance is too low" is classified '' and recorded nowhere;
  an empty 200 is never `malformed`; the DuckDB reads aren't in the worker's `API_HOSTS`, so their key and server
  failures are task failures (the case #51 pattern).
- **What it should do:** each service's manifest names its quota answers (Google's per-minute = rate; per-day = quota
  until 00:00 Pacific, lifted then by its check; Anthropic's credit 400 = quota with Fix it → Anthropic's billing page);
  malformed answers back off; bulk reads report their host's issue like any API call.
- **Step-4 scenario:** stand-in answers for each: Gemini per-minute 429 → a warning that waits; per-day → held, lifted
  at the day's turn by its check; Anthropic credit 400 → a trip with Fix it on the billing link; an HF 401 in a bulk load
  → `svc:foursquare` key issue, only `foursquare_bulk` held, W1's other tasks run.

### F6. A crawler back-off nobody calls stays open for good (row 12; live L11, L12, L13)

- **Module and role:** Sources the crawler calls (Wikidata, Common Crawl, Census).
- **Field / zone:** `sensors` (who checks it when the crawler doesn't); zone 3.
- **What the card shows:** "Its wait ended …: the next call tries it again", for days.
- **What it means:** a live warning about a past event; the board's count never reaches zero.
- **What happens now:** only a crawler call or the site's 2xx at the same host closes it; the watch rechecks site
  issues only (`ControlAlarms.php:1042`); a task switched off never calls it again.
- **What it should do:** the watch runs the service's free check for a crawler issue whose wait has ended, the same as
  for the site's.
- **Step-4 scenario:** a crawler 429 at a stand-in host, its wait over, no run calling it → within one watch its check
  runs and the issue closes "Cleared by its check".

### F7. "Check it now" and the ban's daily probe don't exist (row 14; R94)

- **Module and role:** banned Sources and Services.
- **Field / zone:** `sensors` (the probe: its URL, pace, agent); zone 4's Check it now and Reactivate.
- **What the card shows:** Check it now / Reactivate "asks once" (`Thread.php:35`).
- **What happens now:** `PROBES` is empty (`ServiceHealth.php:45-46`; `probeFor` returns null since Overpass's
  retirement), so the button answers "No check to send", the 3-day daily probe and "looks lifted" never happen, and the
  way out is F1's unchecked resume.
- **What it should do:** each service's manifest declares its light probe; a ban gets one a day for 3 days, then a
  person's choice; a pass raises "looks lifted" with Reactivate.
- **Step-4 scenario:** a 403 at a stand-in host → no calls; the probe goes once a day; its 200 raises "looks lifted"
  with Reactivate; Reactivate's one request passes → closed.

### F8. A backup in use is never said (rows 27, 30; R61, R90)

- **Module and role:** Sources with a backup on the map (Tavily → Brave; the Census via Railway).
- **Field / zone:** `fallback order`; zone 2 ("On a backup: Brave").
- **What happens now:** the worker counts `search_switches` and sends them; the site drops them. The Census site
  issue keeps its own ladder and can trip and email while the backup carries the work.
- **What it should do:** the site keeps each switch (logged, on the need's line as "On a backup, since …"); a component
  whose work a backup carries is paused quietly and never trips the workflow.
- **Step-4 scenario:** hold Tavily → a run's searches go to Brave → the need reads "On a backup: Brave", a logbook line,
  no trip; release Tavily → back to first choice, logged.

### F9. Holds that reach past the failing caller or service (rows 29, 31)

- **Module and role:** Sources shared by the site and the crawler; Overture's regional S3 host.
- **Field / zone:** `incident_radius_rules`; `hosts`.
- **What happens now:** a site-only issue makes the whole service "held", so the crawler's tasks can wait though its
  own calls work; Overture's regional host is filed under AWS, a critical part (system email).
- **What it should do:** the radius is per component (caller and host); Overture declares the hosts it really calls.
- **Step-4 scenario:** a site-only Census trip → the crawler's W2 tasks run; an Overture S3 failure → Overture's card,
  never AWS's.

### F10. Smaller word and watch defects

- Cap titles name the level crossed ("Claude at 50%") while the reading says 67% (live L5, L6): words follow the reading.
- Mark resolved is still an API action and a Pulse step (row 24): remove it (R122).
- A refused Railway or GitHub watch token reads as "Couldn't look", raising nothing (row 5): a fault on the Host part.
- A critical part's trip that is still trying says "Paused by the system" in its notice title, though nothing is paused
  (row 20; `ControlAlarms::ACTIONS`).
- Each needs a scenario: the words checked against the state on all four surfaces.

### Manifest fields missing (for step 3's design)

`role`, a declared `incident_radius_rules`, a declared `sensors` list with each probe, per-service `fix_it_routes`
(quota and key answers by provider), `criticality` moved into the manifest, and `cadence` on each workflow assignment.
`feeds` and `fallback order` exist, but outside `service-defs.php` (`OutsideServices::CONTROLS`, `Preflight::NEEDS`).
