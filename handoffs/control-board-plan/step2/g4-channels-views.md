# Step 2, g4: how a failure is SHOWN and ROUTED, and the views (2026-11-21)

Audit of the live code at `traverse-3way-sync` 37a212e (Sync 2026-11-21 12:26 UTC): `sync-app/` = `app/`,
`sync-storage/` = `website/`. Line cites are `sync-app/…` unless marked `website/`. Graded against step 1 §1 ("One model,
applied everywhere"), Jason's approved report (`mh.txt`: the plug-in manifest, the four-zone card, the Incident Radius,
correlated incidents, workflow assignment, the Communications Dispatcher as a Service module; no SMS, no webhooks) and
`manifests-railway-github.md`. No repo file or mirror file was changed.

**How it was proved.** On my copy (`g4/y/live`, database `lv_g4` only; MariaDB had died and was restarted):
- `probes/g4/channels.php` raises one alarm per state and captures each channel: the board row (`tv_cb_alarms`), the Pulse
  item (`Pulse.php` L97-106 and `ControlAlarms::pulseSteps`), the dispatcher's message (`comms_messages`, `comms_route`),
  the email (a `Mailer` test double) and the push (a `WebPush` test double). Output: `probes/g4/channels.json`.
- `probes/g4/extra.php` adds the dispatcher's edge cases (email channel paused, no device, no founder, quiet hours, a
  person's close). Output: `probes/g4/extra.json`.
- `probes/g4/modules-page.php` and `modules-shot.cjs` draw Modules (Service register) at 1280 and 390. Screenshots:
  `step2/shots/g4-modules-{1280,390}-{load,railway-card}.png`.
- The existing suites, all against my copy, all pass: dispatcher `r1` 43, `r2` 20 + `r2.cjs` 10, `r2-notice` 12, `r3` 44 +
  `r3.cjs` 48, `r4` 42 + `r4.cjs` 34; board `board-phone.cjs` 6, `board-tabs.cjs` 16, `board-menu.cjs` 6,
  `board-rendered.cjs` 20, `lines.php` 33 + `lines.cjs` 16, `one-panel.cjs` 12, `paused-now.php` 20, `routes.php` 76,
  `critical.php` 20, `card-issue.php` 15 (outputs `probes/g4/*.out`). **Passing tests prove what they test; they test the
  releases' own lines, not the model.** Every finding below is something the suites don't check.
- Missing tables made from their use in code: `control_ledger`, `control_records` (`probes/g4/ledger.sql`, from
  `ControlLedger.php` L176-183); `system_pauses` by `Automation::ensurePauses`; the comms tables in the probe's header.

---

## (a) Channels: what each one says now, per alarm state

Model (step 1 §1.1, R102-R104, R125-R133): a warning while the system tries goes to the board and the card only; a trip
reaches the Founder once and at once on Pulse, email and push; the Escalation Notice raises the same thread (one push);
Recovered is a Notice on the same thread (an email reply, Pulse updated, no push); a person's close is the thread's last
state; one thread per item **or group**; the title is plain words, never a raw id or check text.

| # | State (probe) | Board row | Pulse | Email | Push |
|---|---|---|---|---|---|
| 1 | Warning while trying: a non-critical task's 1st failure (A) | **differs**: "Status: Warning since 14:13 UTC, seen 1 time" (not "Trying again (1 of 3)", R104); "What it affects: **Holds: wf:W2**" though nothing is held, with the raw id (`Thread.php` L110: `holds` falls back to the part's name; `facts()` L728 sets it always) | matches: none ("Pulse lists open trips only", `Pulse.php` L98) | matches: none (`ControlAlarms::mail` L638) | matches: none |
| 2 | Trip: its tries ran out (B) | **differs**: "Paused by the system: W2 (its tasks wait with it)" beside "What the system is doing: … the workflow's other tasks carry on" (the raiser's words, `CrawlService.php` L261, vs the hold, `ControlAlarms::stop` L287-289) | **differs**: `SYSTEM ALARM NOTICE: W2 Keeping listings current: Paused by the system`, words carry the same contradiction | **differs**: subject right; body "What's going on: W2: its task second_look failed (run #9) / What it means: … the workflow's other tasks carry on" | matches: `SYSTEM ALARM: W2 Keeping listings current` / "Paused by the system (a trip) since Nov 21, 14:13 UTC. It closes as Recovered when its check passes." |
| 3 | The trip acknowledged (B2) | **differs**: the row says "Acknowledged by Jason", but the top-bar light and counts drop to **0 trips** while W2 stays paused (`ControlAlarms::counts` L545 `AND ack_at IS NULL`; light `website/admin/router.php` L132-135 then reads "No alarm open", green) | matches: the item stays | matches: nothing sent | matches: nothing sent |
| 4 | A critical part's first failure: trip at once, still trying (C) | matches: "Tripped at once … a critical part. The system is still trying; if that fixes it, this closes as Recovered." | **differs**: title "… QuickBooks (Intuit): **Paused by the system**", nothing paused (`stopped` null; `noticeTitle` L660-664 uses `ACTIONS['trip']` for every open trip) | **differs**: same subject; body "Paused by the system: Tripped at once …" | **differs**: body "Paused by the system: Tripped at once …" |
| 5 | Escalated: a critical part's tries out (D) | matches: "Escalation Notice …: retries unsuccessful, an admin is needed" | matches: title `…: Retries unsuccessful, now admin required`, updated in place, unread again | **differs**: a reply in the thread (`In-Reply-To` right), but the body adds "**(2 of these are waiting.)**" (`Notify.php` L567: `times > 1` on an in-place thread) | matches: one push, `renotify: true`, same tag |
| 6 | Recovered by the system (E1, E2) | **differs** (minor): "Closed …: Recovered: W2 … was down 1 min, the system fixed it." but still "What it affects: **Holds: wf:W2**" after it closed | matches: the item leaves; the message becomes `alarm.recovered` (Notice) in place | **differs**: reply in the thread, "(2 of these are waiting.)" / "(3 of these are waiting.)" in a Recovered notice | matches: none (Notice) |
| 7 | Closed by a person: Check and start again (F), or a key fixed (`ServiceHealth::resolve` uid 7, H1b) | matches: "Closed: Resolved by Jason: Checked and started again" (F: **nothing was checked**: no pf line, nothing stopped; `resolve` L306-345 closes it with "Checked") | **differs**: the item leaves Pulse, but the dispatcher's message stays `alarm.trip`, Critical, "Paused by the system", **unread** (bell 3 unread in `extra.json` O): `resolve()` L350-353 and `close()` L180-192 never call `notifyOne` | **missing**: no closing reply | matches: none (but the phone keeps the trip's notification) |
| 8 | Notice: on a backup (G) | matches: "Notice Tavily is down: W1 is on its backup (Brave)" with the needs and their backups | matches: none | matches: none | matches: none |
| 9a | A service's issue, key refused (trip at once, H1) | **differs**: title "api.anthropic.com: Our key was refused (the site, on Bluehost)" (host and caller, not plain words, R102); "**Nothing could be paused from here (it only trips)**" while the card says "Paused by the system (a trip) … no calls to api.anthropic.com" (`affects()` L771-772 vs `ServiceHealth::facts` L736-738) | **differs**: same contradiction in the words; Fix it step → `/admin/config/register#svc-anthropic-key` (matches) | matches: subject, `&fix=1` link | matches |
| 9b | A service's issue, their 503 SlowDown reported as `server` (H2) | **differs**: "data.commoncrawl.org: **Its server failed**" for "503 SlowDown Please reduce your request rate" (L12's cause, below) | matches: none | matches: none | matches: none |
| 9c | A service's back-off spent: 6 timeouts → trip (H4) | **differs**: "Paused by the system (a trip) … It closes as Recovered when its check passes" and, two lines on, "calls to api.tavily.com are **held until an admin resolves it**" (`ServiceHealth.php` L353) | **differs**: both sentences | **differs**: both sentences | matches |
| 10 | Grouped: two out of budget at once (I) | matches: one group row "2 engine feeds held" (`groups()` L393-405) | matches: one group item `alarms:engine:trip:held` with 2 children (`pulseGroups` L418-438) | **differs**: **two** emails, one per member (Q11: "one thread per item or group") | **differs**: **two** pushes |
| 11 | Correlated: same host and signature in a short window (case #51's pair) | **missing**: grouping is system:level:family only (`groupKey` L384-387); no signature, host or window; foursquare_bulk and overture_bulk trip as two rows of "engine feeds failing" at best | **missing** | **missing** | **missing** |
| 12 | A cap warning whose reading moved (J; live L5/L6) | **differs**: "Google at 50%", reading 67% (cause below) | matches: none | matches: none | matches: none |
| 13 | A trip on a site job tied to no workflow (K) | matches: "Tripped since …; Nothing could be paused from here (it only trips)" | **differs**: title `SYSTEM ALARM NOTICE: **job:digest**: Paused by the system` (raw id: `partName` L608-613 falls back to `$proc`; and "Paused" with nothing paused) | **differs**: same subject | **differs**: `SYSTEM ALARM: job:digest` |
| 14 | No active founder (extra N) | the row shows | **missing**: no message for anyone | **missing**: none, and nothing records that the trip reached nobody (`notifyOne` L618 and `mail` L642 return quietly; `mailed_at` stays null) | **missing** |
| 15 | Email channel paused by message control, then a trip (extra L) | matches | matches | **differs**: held, "Paused by message control: email", though Critical "can't be turned off" (`Notify.php` L31-32 `ALWAYS`; `pausedFor` L349-355 is checked first, L197/L227). Nothing on the board says alarm email is held | matches: pushed |
| 16 | The logbook (the finished story) | **missing**: the moment it **tripped** is not logged: `raise` logs only a new row (L139); `tripped()` L218-229 adds no line. W2's logbook reads Warning → Acknowledged → Cleared, with no Trip (screenshot `wf-W2-1280-load.png`) | – | – | – |

Quiet hours (extra P): a trip and its Escalation Notice go during quiet hours (matches R127); Recovered's email waits for
them to end (Notice level, `Notify.php` L29), with no push (matches R132).

**Verdict count** (69 cells: 17 rows × 4 channels, plus the logbook's one): matches 35, differs 25, missing 9.

### The live words that lag their state (L5, L6, L10-L12): their causes in code

- **L5, L6** ("Claude (AI) at 50%", reading 67%): the title is the **band**, not the reading. `Operations.php` L605
  builds `$name . ' at ' . $w['level'] . '%'` (the 50/75/95 band reached), and L614 passes `$top['pct'] . '%'` as the
  reading. Every watch tick rewrites the title (`ControlAlarms::raise` L120), so it's "fresh" but still says the band.
- **L10** (Common Crawl's pre-flight line says "the rest can start" while W3's trip holds W3 whole): two writers of the
  hold's words. The line's words come from `Preflight.php` L1635 ("It holds only what it's for (…); the rest can
  start."); the hold comes from `ControlAlarms::stop` L283-289, which pauses the whole workflow for any alarm whose
  process is `wf:W3` (`pf:wf:W3`), with its own words "W3 (its tasks wait with it)".
- **L11** (Wikidata 429 open three days): a 429 is never spent into a trip (`ServiceHealth.php` L348 `$kind !== 'rate'`)
  and closes only when the same caller gets an answer (`answered()` L290-294) or the site's own check answers outside
  the 429's wait (L299-302). Its board status reads "Warning since Nov 18, seen N times", with no last time and no next
  try, so the words can't say whether it cleared and came back or never cleared (R101, R104).
- **L12** ("data.commoncrawl.org: Its server failed" over a SlowDown reading): the worker's own kind is trusted.
  `ServiceHealth::fromReport` L577-579 passes `$i['kind']` straight to `failed()`; `classify()` L196, which maps
  `503 … slowdown|reduce your request rate` to `rate`, runs only for the site's own calls. The alarm's title is
  rebuilt from the kind only when a new failure is reported (L255-259 → `alarm()` L350), so an issue opened before
  crawler 2.14.1 keeps "Its server failed" until the next report. Probe H3 proves a later `rate` report does retitle it.
- **The same thread has two titles.** The alarm (board, Pulse, email) says "<host>: <What> (<caller>)"
  (`ServiceHealth.php` L350-351); the card and Outside services say "<Company>: <what> at <host>" (`ServiceHealth::facts`
  L758). R123 asks for the same words in all four places.
- **The live "13 of 21"** in the snapshot: the health report's alarms block lists only trips and warnings
  (`website/api/health.php` L184), while the summary line counts every open alarm. The other 8 are probably notices
  (needs live data to confirm).

---

## (b) The Fix it map: every cause → its landing

`Thread::FIX` (`Thread.php` L32-39) has six causes. Two set landings by alarm key: `ControlAlarms::fixOf` L734-746 and
`ServiceHealth::fixOf` L764-768. Anchors checked in the code that draws them; `r3.php` and `r3.cjs` (92 checks) prove
the panel opens on each.

| Alarm key | Cause | Landing | Anchor exists? |
|---|---|---|---|
| `svc:issue:<id>`, kind `auth` | key | `/admin/config/register#svc-<k>-key` | yes, `admin/operations-console.php` L203; Replace ready (`r3.cjs`) |
| `svc:issue:<id>`, kind `quota` | quota | `/admin/config/register#svc-<k>-caps` | yes, L205 (no caps) or L210 (Caps and paces) |
| `svc:issue:<id>`, kind `ban` | blocked | `/admin/config/register#issue-<id>` | yes, L328 (the card hides a second Fix it there, L323) |
| `svc:issue:<id>`, `rate`/`timeout`/`server`/`malformed` | theirs | none: "The system is handling it" | by design (0176 §5) |
| `ops:cap:<svc>` | quota | `#svc-<svc>-caps` | yes |
| `ops:cap:community` | quota | `/admin/config/register#costs` | yes, L169 |
| `ops:w0:held` | blocked | `/admin/config/control/wf-W0#cb-held` | yes, `admin/control-console.php` L952 |
| `ops:w0:held:<host>` | blocked | `/admin/config/control/wf-W0#held-<host>` | yes, L947 (`heldAnchor`) |
| (none raises it) | backup | "The workflow's Steps and backups" (greyed "to come") | the spot exists (`#cb-steps`, L540), **no raiser sets the cause** |
| (none raises it) | shutdown | "Maintenance: Take out" | **no raiser sets the cause** |

**Alarm kinds with no landing** (they keep only their component button, `tv_cb_component` L502-512, which opens a
page in the side panel): `task:failed:<t>` (CrawlService L261), `run:failed`, `run:starved` (L252-254), `silent:run`,
`silent:w0` (ControlAlarms L937-944), `job:<j>`, `job:<j>:stuck`, `jobslow:<j>` (L957-970), `rel:job:quiet` (L988),
`w3:offregion` (L1029), `ep:<ep>:5xx`, `ep:<ep>:count`, `ep:<ep>:slow` (ControlLedger L119-138; ControlAlarms L1078),
`ops:empty:<t>` (Operations L622), `ops:start:<hash>` (L637), `ops:w0:receipts` and `ops:w0:flag:*` (L662),
`ops:pf:stopped` (L673), every `pf:<line>` (Preflight L351: services, needs, workflows, sources, site lines),
`crit:<part>`, `crit:<part>:one` (ControlSensor L206-213), **`crit:site:payments:token`** (L195: "QuickBooks needs
reconnecting", a refused token, which is a person's key by the model), `slow:site:pulse` (L156), `watch:history:slow`
(ControlHistorian L105), `maint:*` (ControlMaintenance L136, L188), `osm:not_in_copy` (CrawlService L227),
`svc:osm:held`. **25 alarm kinds have no landing; landings exist for 3 key families** (a service issue of kind auth, quota or ban; `ops:cap:*`; W0's blocked sites). Three of them are a person's fix with no Fix it: the
QuickBooks token, a `pf:svc:*` line red for a missing key (its `todo` says "Its fix: …", L333), and `ops:w0:flag:*`.

Not alarms at all, so never on the board: `EngineCase` steps of kind `service` ("looks lifted: Reactivate it",
`ServiceHealth.php` L451-454; Geofabrik refused, `crawler/OpenLoads.php` L203), the Pulse case "The crawler has stopped"
(`Pulse.php` L371, linking to the old `/admin/crawler.php`), and `mail.failure` (`CommsSetup.php` L155-166). Each is a
failing component reported outside the one thread.

---

## (c) The Communications Dispatcher as a Service module (to the contract)

### Manifest, from the live code

| Field | Value |
|---|---|
| id · name | `dispatcher` · Communications Dispatcher (`Notify`, with `Thread`, `Mailer`, `WebPush`) |
| role | **Service**: delivers every thread (alarms, uploads, people's messages) |
| criticality | Critical by the model (a trip "reaches the Founder once and at once"). **Not a part today**: `ControlRegister::processes` (L160-164) has no dispatcher or Notify part; the duty "System-critical procedures" names `svc:aws` (SES) for system email (L101), but alarm email goes **Bluehost SMTP first**, SES only as its backup (`Mailer.php` L102-118) |
| plan · spend | Free: Bluehost's mailbox, our own VAPID keys (`WebPush.php` L5-19); SES at its price when it carries a send |
| stage · state | Operational. No stage record, no state reading |
| channels | **Pulse** (always; `comms_messages`), **email** (`Mailer`: Bluehost SMTP → Amazon SES backup), **push** (`WebPush`, RFC 8291/8292). `Notify::CHANNELS` L35. No SMS, no webhooks (matches Jason's correction) |
| required keys | `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL` (`Mailer.php` L18-19); SES's `personal` keys with `SES_MODE=live` (L155); `VAPID_PRIVATE_PEM_B64`, `VAPID_PUBLIC_B64U` (`WebPush.php` L18-19) |
| sensors today | Each message's route (`comms_route`: created, addressed, delivered, sent, held, skipped, failed; `Notify::route` L299); each sender's last sent and failed, 24 h counts (`CommsSetup::lastOf`, Communications page); a failed send as a `mail.failure` Pulse message (`CommsSetup.php` L155-166); dead push devices (`push_subs.failed_at`); quiet-hours holds released by `notify_release` (`Notify::releaseHeld` L656); message control's pauses (`comms_controls`) |
| sensors missing | Queue depth (held now, oldest held); failure and bounce rate per channel; "a trip reached nobody"; "a Critical message held by message control"; VAPID keys present; a founder with no device |
| feeds | Every alarm thread (`ControlAlarms::notifyOne` L615, `mail` L633), uploads' threads (`UploadThreads`), people's messages (`Notify::KINDS` L73-110) |
| fix_it routes | A sender failing → `/admin/config/communications#cs-<sender>` (Check, Send me a test, L42); a paused channel → `#cs-controls` (Resume); SMTP password → `.env` (Founder); push keys → none |
| actions | Check, Send me a test (per sender); Pause and Resume a kind, channel or sender (`#cs-controls`). Contract asks: Send Test Dispatch, Open Routing Matrix (`#cs-routing` exists, email only), Fix it |

### Four zones: what exists, and where

| Zone | Contract | Today |
|---|---|---|
| 1. Identity and role | Dispatcher · Service · Critical · stage · state | **missing**: no card on Modules, no part on the board |
| 2. Channel readiness | Email, Pulse, push, each ready or not, with its keys | **partial**: the Communications page's sender cards (Bluehost senders, last sent and failed, Check); the Channels list is static text (`comms-console.php` L141); push readiness isn't read |
| 3. Queue depth, failures | Held now, failed in 24 h, bounce rate | **partial**: the Sent view and the last 7 days by channel (`#cs-controls`); no held count; SES bounces stop an address (`SesMail.php` L9) but no rate is shown |
| 4. Fallback and actions | Automatic fallback status; Send Test Dispatch, Fix it | **partial**: email's SMTP → SES fallback exists and logs "Amazon SES, the backup" (`Mailer.php` L116); there is **no fallback across channels** (email failed → nothing else tries), and no status of it |

### Its Incident Radius rules (proposed from what the code does)

| Fault | Held | Unaffected | What the code does today |
|---|---|---|---|
| Bluehost SMTP refuses | Nothing while SES is live: SES carries it | Pulse, push | Falls back to SES and raises `mail.failure` (`Mailer.php` L108-118). No board alarm |
| SMTP and SES both fail | Email of every kind | Pulse, push | Route `failed`; `mail.failure` in Pulse, which reaches the overseers by the same failing email. No board alarm |
| VAPID keys missing or a device dead | Push for whoever has no working device | Pulse, email | `push` `skipped` / `failed` on the route; nothing tells anyone (extra M: the founder had no device and nothing noted it beyond the route) |
| Message control pauses a channel | That channel, **Critical included** | The rest | Held, "Paused by message control" (extra L): a trip's email waits silently. Under the model, Critical isn't held |
| No active founder | **Every alarm** reaches no one | – | Nothing sent, nothing logged (extra N) |
| The database is down | Pulse and the route | – | Notify never throws (L21): the send is lost with no trace |

---

## (d) The views against the contract

- **A four-zone card per module: missing.** Modules (Service register) has one card per outside service
  (`tv_ops_reg_block`, `admin/operations-console.php` L182-233, inside `ui/outside-services.php`). Its parts, in order:
  stage with Pause/Activate/Retire; status; Used by; Its key; Known price; This month; Caps and paces; Is it working;
  settings; In our admin; On <company>; Terms; Waiting on them. Every contract zone's material is there, scattered,
  with no zone order. There's **no role** (Host, Source, Service), **no criticality** (the duty lives only on the
  board, `ControlRegister::CRITICAL` L88-104), **no Incident Radius**. Workers (crawler, W0, video) and site parts aren't
  cards. The dispatcher has no card.
- **Stages are 0173's seven, not Jason's eight.** `ServiceStages::STAGES` L11-19: Proposed, Added, Key in, Active,
  Paused, Retired, Removed. "Assigned to workflows" and "Maintenance" aren't stages on the card (maintenance lives on
  the board, `ControlMaintenance`).
- **The module list (a card's closed row).** At 1280: mark, name, purpose, "stage · state", cost, test
  (`g4-modules-1280-railway-card.png`: "Railway … Added · In use · $5.00 · not tested", where "Added" and "In use"
  disagree). At 390: **only the mark, the name and the cost**. Stage, state and test are hidden
  (`g4-modules-390-railway-card.png`). No role. No sideways scroll at either width, closed or every card open
  (`probes/g4/modules-shot.out`).
- **The board's parts table** (`tv_cb_process_table` L460-470): Process, Status, Stage (the board's run stage), Where
  it stands, Last 24 hours, Speed, OK, Check, ⋯. No role, spend or criticality columns. `board-phone.cjs` (390, every tab
  and panel open) and `board-rendered.cjs` pass.
- **Workflow assignment** (a workflow's page, `tv_cb_steps` L521-557; `wf-W2-1280-load.png`): each task, its services
  with "its backup: A, then B" (a **fallback order: partial**, from `Preflight::NEEDS`), On or Off with why, its switch.
  **No cadence** (every 5 min or nightly lives on Insights → Crawler's "Every", `crawler-console.php` L116). **No queue
  counts** on the board. Insights → Crawler shows "Its queue" as **plain text**, not links that open the rows (L122,
  `$kv($q[$t])`). **Retry failed** exists only there, only for `coverage` and `identity` (L120), off the board. The
  Workflows menu page lists links, the spend fold, then the old Workflow board (`operations-console.php` L470-482).
- **Incident Radius: missing everywhere.** No code names it (`grep -i "incident radius|incident report|correlat"` finds
  nothing in `sync-app` or `sync-storage` outside word lists). The nearest piece is "What it affects" (`affects()`
  L768-793): what the trip paused, and for a service, each need with its other sources. It has no "Unaffected" side,
  and it contradicts the card for stop-kind issues (row 9a above).
- **Correlated incidents: missing** (row 11). Groups are by system, level and family only.
- **390, no sideways scroll: works** on the board (all tabs, panels, alarms), a part's page, a workflow's page
  (`board-phone.cjs` 6/6), Modules (my probe), Fix it panels (`r3.cjs`) and uploads' threads (`r4.cjs`).

---

## Grades: R1-R26, R100-R139, R176-R179

| R# | Grade | What the code does | Evidence |
|---|---|---|---|
| R1 | partial | Rows for workflows, tasks, workers, services, site parts and jobs; no row for the dispatcher (Notify), endpoints only through their workflow | `ControlRegister.php` L155-164 |
| R2 | partial | Start's stages are tabs, each with its part of the board; each stage's "data it's generating" is a link list, not its own readings | `board-tabs.cjs` 16/16; `control-console.php` L789 |
| R3 | partial | Its data (records touched) and the ledger on a process page; counts per endpoint read from `control_ledger`; live L9 says one endpoint's count is wrong | `control-console.php` L955-965; `ControlLedger.php` L117-183 |
| R4 | works | Systems → parts table → a part's own page (route, needs, controls, data, logbook, report) | `board-rendered.cjs` 20/20; `wf-W2-1280-load.png` |
| R5 | partial | Route checks exist (`routes.php` 76/76); uptime per process isn't shown on its page | `routes.php`; no `uptime` in `control-console.php` |
| R6 | partial | Six stages as tabs at system level; a process has Check, Shut down, Take out, but no per-process stage tabs | `control-console.php` L473-500 |
| R7 | works | The board draws Preflight's and SystemStart's own output | `control-console.php` requires `preflight-console.php`; `board-page.php` |
| R8 | works | Four systems from the register; jobs from `Scheduler::JOBS` | `ControlRegister.php` L160-164 |
| R9 | works | A system's line is its worst part, with counts | `board-rendered.cjs` |
| R10 | works | One table, group rows, ⋯ with reasons, name opens the part's page | `tv_cb_process_table` L460-470; `board-menu.cjs` 6/6 |
| R11 | works | Counts filter, open systems survive the redraw, Open every system, Holds: link, search by source | `board-rendered.cjs` 20/20 |
| R12 | works | The needs panel is read from `Preflight::NEEDS` | `control-console.php` L129-146 |
| R13 | partial | The route is one text line (`cb-route`), not hops with state and count | `control-console.php` L890 |
| R14 | partial | Data, logbook, report present; readings and stage via the row; no spend-against-cap or uptime block | `control-console.php` L955-972 |
| R15 | works | Tiles are tabs; deep links open the tab | `board-tabs.cjs` |
| R16 | works | `/admin/config/control`, first under System Configuration; `?proc=<kind>-<name>` pages | `admin/manifests.php` L162 |
| R17 | partial | Board, Modules and Workflows exist, but the menu still carries Pre-flight, Automation, Costs, Workflow board and the four board tables as sections | `manifests.php` L164-176 (`board-tabs.cjs` passes on the board itself) |
| R18 | works | Costs folded at the top of Modules; By workflow on Workflows | `operations-console.php` L169, L477 |
| R19 | partial | Old addresses redirect (`moved.php`, not rerun here); Modules still links out to the Pre-flight page | `operations-console.php` L177 |
| R20 | partial | The alarm queue, a workflow's steps and Sites that blocked us open on load | `control-console.php` L823, L540, L952 |
| R21 | works | Health line and Paused now at the top | `lines.php` 33/33, `paused-now.php` 20/20 |
| R22 | works | Needs attention, Waiting on those, Off on purpose | `paused-now.php` |
| R23 | missing | Dashboard → Today has no board line, and the top-bar light is hidden on Dashboard pages | `admin/sections.php` L20-24; `website/admin/router.php` L141 |
| R24 | works | `config/alarms`, `log`, `ledger`, `upkeep` tools; headings fold | `manifests.php` L164-167 |
| R25 | partial | Light in the top bar, stage strip, route line, query timings kept; 21 literal colours in the board's CSS | `control-console.php` L1000 (`#FFFBF2`, `#EFE6D6` …) |
| R26 | needs staging | Greyed with the reason by `Access::can`; not run per role | `control-console.php` `$btn(…, $why)` |
| R100 | partial | Every alarm has means and todo; a Fix it only for 3 key families, 25 kinds without one; some todo text is the raiser's placeholder | §(b) |
| R101 | broken | Band titles (L5/L6), stale kinds (L12), "Holds: wf:W2" after closing, "(N of these are waiting.)", "held until an admin resolves it" beside "closes as Recovered" | §(a) rows 1, 5, 6, 9b, 9c, 12 |
| R102 | partial | One shape on every surface (`Thread::html`), but titles carry hosts, callers and raw ids ("job:digest") | `ServiceHealth.php` L350; `ControlAlarms.php` L608-613 |
| R103 | partial | Push title+status, Pulse without details, board and card in full; board and card disagree on title and status for one issue | rows 9a; `ServiceHealth::facts` L758 |
| R104 | partial | "Paused by the system (a trip)" is right; a fault reads "Warning since …, seen N times", not "Trying again (1 of 3)"; no "Checked … Next check …" status line | `ControlAlarms::facts` L712 |
| R105 | works | Paused now names its trip; "then press Check and start again" | `paused-now.php` |
| R106 | works | Health line, critical duties first, red only for a critical duty | `paused-now.php`, `lines.php` |
| R107 | partial | Held light and tokens on the board; literal colours remain (board CSS, the top-bar light's fallbacks) | `router.php` L134; `control-console.php` L1000 |
| R108 | works | A system's hour red only for a critical part | `lines.php` |
| R109 | works | In maintenance, what a part raises is a Notice | `ControlAlarms.php` L91-95 |
| R110 | partial | A card's status carries its reason; at 390 the card row hides stage and state | `g4-modules-390-railway-card.png` |
| R111 | works | A trip's Pulse row is Critical | `Pulse.php` L104 |
| R112 | partial | The line says what the token is for, not the plain "a password … can't change anything, costs nothing" sentence | `Preflight.php` L784-785 |
| R113 | broken | Stale words: "An email goes to the admins at 80% and 100% of a cap"; Pulse's "Mark resolved" fallback; Notify's "with Acknowledge"; "The crawler has stopped" case | `ui/outside-services.php` L236; `Pulse.php` L1293, L371; `Notify.php` L82 |
| R114 | partial | Fix it and component buttons open in the side panel; Pulse's row link and the Modules card's Pre-flight link go to another page | `Pulse.php` L105; `operations-console.php` L177 |
| R115 | works | One panel, Back, ×, Esc, outside click | `one-panel.cjs` 12/12, `r3.cjs` |
| R116 | works | The component button by the part's kind | `tv_cb_component` L502-512 |
| R117 | partial | Key, quota, blocked (service and W0) land; backup and shutdown have no raiser; the QuickBooks token and key-missing pre-flight lines have no Fix it | §(b) |
| R118 | works | Their trouble has no Fix it; Check it now greyed on a 429; Use backup greyed "to come" (Q17 drops it: step 3) | `r3.php` 44/44; `ServiceHealth::rateFix` L363 |
| R119 | works | A task's switch has one home, with who and why | `lines.cjs` 16/16 |
| R120 | partial | Replace and Set check at once; Reactivate is one try at W0's next read, not an at-once check | `r3.php`; `control-console.php` L949 |
| R121 | works | Trip and Escalation link to `stream#case=alarm:<id>` (+`&fix=1`); Recovered to the board | probes H1, I, E1 |
| R122 | partial | No Mark resolved on the board, Pulse alarm steps or the card; `Pulse.php` L1293's fallback still says it | `Pulse.php` L1293 |
| R123 | partial | One issue box on the card, no ×; alarm title and status differ from the card's | `card-issue.php` 15/15, `r2.cjs`; row 9a |
| R124 | needs staging | Look, Work and Fix views exist in Pulse's kit; not pressed here | `PulseAct.php` |
| R125 | partial | Warnings reach no Pulse, email or push; but "The crawler has stopped", `mail.failure` and EngineCase `service` steps reach Pulse outside the model | `Pulse.php` L371; `CommsSetup.php` L164 |
| R126 | partial | A trip goes to Pulse, email and push in the same call; only to founders, and to nobody (silently) when there is none | probe B; extra N |
| R127 | partial | Critical goes through quiet hours; message control can still hold a Critical email | extra P, L; `Notify.php` L197 |
| R128 | broken | An acknowledged trip leaves the count: the light turns green "No alarm open" while its pause holds | `ControlAlarms::counts` L545; probe B2 |
| R129 | partial | One message per alarm, updated in place; a person's close leaves it "Paused by the system", unread | extra O; `resolve()` L350 |
| R130 | partial | Subjects, push titles and bodies as set; raw ids for jobs; "Paused by the system" where nothing paused; "(N of these are waiting.)" | rows 4, 5, 13 |
| R131 | partial | Email threading and push tags right; a group sends one message per member | row 10 |
| R132 | partial | Recovered: same thread, a reply, no push; its body says "(N of these are waiting.)" | row 6 |
| R133 | partial | Escalation: one louder push; the assignee's own message (Pulse's clock) not proved here | probe D; `Pulse::escalateTo` |
| R134 | works | Through Notify; staging holds mail and push; no SMS | `Mailer.php` L88; `WebPush.php` L196 |
| R135 | needs staging | The bell counts unread at Alert and up (`Notify::unreadForBell` L692); colour and tap not rendered here | – |
| R136 | needs staging | `read_at` per person on each message; Pulse marks per person | `Notify::markRead` L701 |
| R137 | works | No Approve in an alarm's steps | `pulseSteps` L811-845 |
| R138 | works | The alarms block, no personal data, 401/503 without the token; it omits notices | `website/api/health.php` L16-20, L180-185 |
| R139 | works | Uploads' threads | `r4.php` 42/42, `r4.cjs` 34/34 |
| R176 | works | Nothing wider than 390 with everything open | `board-phone.cjs` 6/6 |
| R177 | partial | Greyed Fix it shows its reason as text; the Modules row at 390 hides stage and state | `Thread.php` L62; screenshot |
| R178 | needs live data | The 300 ms first view and per-part loading need the live server's timings | – |
| R179 | partial | Board views have rendered tests at 1280 and 390; Modules and Workflows had none (my probe adds Modules) | `tools/control-board-tests/` |

**Grade count (70 rows):** works 28, partial 33, broken 3, missing 1, needs staging 4, needs live data 1.

---

## Findings, ranked by harm (card specs)

**1. An acknowledged trip turns the light green while its pause holds.**
- Module and role: Control Board (the board's own light) / Dispatcher (Service).
- Field or zone: zone 3, Sensors; the top-bar light (R128).
- What the card shows: "● 1 trip (acknowledged): W2 paused" in red, never "No alarm open" while any trip is open.
- What it means: a trip still holds work until its check passes. Acknowledging it only says someone has seen it.
- What happens: `counts()` counts open trips whether acknowledged or not. Acknowledged ones show as "seen", and the light stays red.
- Step-4 test: raise a trip, acknowledge it; the light and the board's Open filter still show 1 trip, red. Its check passes; the light goes green.

**2. A thread's words contradict its own hold, and the board contradicts the card.**
- Module and role: every module (the one words engine, step 3).
- Field or zone: zone 4's thread (Status / What it affects / What the system is doing).
- What the card shows: what is held and what is unaffected, written from the hold the engine placed. Examples: "Held: overture_bulk. Unaffected: W1's other tasks". "Held: calls to api.anthropic.com". One title per thread on every surface.
- What it means: a person reads one true account wherever they look.
- What happens: today's raisers write their own `means` text ("other tasks carry on", `CrawlService.php` L261; "held until an admin resolves it", `ServiceHealth.php` L353) and `stop()` writes the hold separately (L283-289). A stop-kind issue reads "Nothing could be paused" on the board (L771) and "Paused by the system" on the card (L736). Words are built from the hold and the issue only.
- Step-4 test: for every cell of the failure map, assert that each surface's Status and Affects equal the engine's hold, and that the board, the card, Pulse and the email share one title.

**3. A person's close never reaches the thread.**
- Module and role: Dispatcher (Service).
- Field or zone: zone 3, Failures and queue; the thread's last state.
- What the card shows: "Closed by Jason 14:20: its check passed" on Pulse's message and as an email reply. The bell count drops.
- What it means: a closed trip isn't still waiting for anyone.
- What happens: `ControlAlarms::resolve` (L350-353), `close()` (L180-192) and `closeKey` (L174) call `notifyOne(… 'alarm.recovered' …)` with "Closed by <who>", as `recovered()` does.
- Step-4 test: trip → Check and start again, and trip → Replace key. The message turns into a closed notice and an email reply is sent with no push; an unread Critical message isn't left behind.

**4. A trip can reach no one, silently.**
- Module and role: Communications Dispatcher (Service, critical).
- Field or zone: zone 2, Channel readiness; zone 4, Fallback.
- What the card shows: "Alarms reach: Founder (email ✓, push ✗ no device)", "Email held by message control: Critical goes anyway", "No founder: alarms reach no one" as a red line.
- What it means: whether a trip will reach a person right now.
- What happens: a Critical send skips message control's pause (`Notify.php` L197, L227). No founder, or no channel delivered, raises a board trip on the dispatcher part. A channel's failure falls back to the next channel (email failed → push; push skipped → email) and records "fell back".
- Step-4 test: extra.php's L, M and N scenarios. A trip with email paused is emailed. A founder with no device gets an email and the card says "push ✗". No founder raises the dispatcher's trip on the board.

**5. Stored titles lag the state (L5, L6, L12).**
- Module and role: Anthropic, Google (Source); Common Crawl (Source).
- Field or zone: zone 3, Sensors; zone 4's title.
- What the card shows: "Claude (AI): 67% of its cap (past the 50% warning)". "Common Crawl asked us to slow down (503 SlowDown)".
- What it means: the reading as it is now, with the band as context.
- What happens: the title is built from `$top['pct']` (`Operations.php` L605). `fromReport` runs `classify()` on the worker's status and body before trusting its kind (`ServiceHealth.php` L579). A thread's title is redrawn from its current kind on every read, not stored at raise.
- Step-4 test: raise a cap at 67%, so the title says 67%. Report a 503 SlowDown as `server`, so the title says "asked us to slow down" and the issue's kind is `rate`.

**6. No correlated incident and no Incident Radius; grouped alarms send one message per member.**
- Module and role: Railway (Host), and every module.
- Field or zone: the manifest's `incident_radius_rules`; zone 4.
- What the card shows: "IR · Correlated · W1 bulk loads timed out · 2 members", with Held, Unaffected, Pause only these tasks and Check and resume.
- What it means: one cause, one incident, one message.
- What happens: alarms on the same host with the same signature within a window (the manifest's) join one incident. The dispatcher sends one thread per incident or group (Q11), not per member (`groupKey` L384 today; two emails and two pushes in row 10).
- Step-4 test: two `TimeoutError` task failures 11 minutes apart give one incident row, one email and one push, with a radius listing both tasks held and W2-W12 unaffected.

**7. Fix it covers 3 key families; 25 alarm kinds have no landing.**
- Module and role: every module.
- Field or zone: the manifest's `fix_it_routes`; zone 4.
- What the card shows: Fix it on every alarm a person can fix. Examples: QuickBooks' token goes to its card's Its key (Reconnect). A key-missing pre-flight line goes to Its key. `task:failed` and `ep:*:5xx` go to the Site endpoint's page with its timings (case #51). Their trouble keeps none.
- What it means: no dead end; the person lands where the fix is.
- What happens: causes come from each module's manifest. `crit:site:payments:token` → key, `pf:svc:*` red for a missing key → key, `ops:w0:flag:*` → its line, `ep:*` → the Site part's page.
- Step-4 test: for each kind in §(b), press the first button; it lands on that anchor in the panel. Every kind is listed as Fix it, component or theirs, with none unlisted.

**8. "(N of these are waiting.)" in alarm emails.**
- Module and role: Dispatcher.
- Field or zone: the email body.
- What the card shows: nothing extra. The Escalation and Recovered replies carry only their state.
- What it means: there aren't N waiting; it's one thread updated.
- What happens: `Notify::deliverEmail` (L567) skips the line for `IN_PLACE` kinds.
- Step-4 test: trip → escalate → recover; no email body contains "of these are waiting".

**9. Raw ids and "Paused" where nothing is paused.**
- Module and role: Site jobs (Service), critical parts.
- Field or zone: zone 1, Identity (the part's name); the notice's action.
- What the card shows: "SYSTEM ALARM NOTICE: The daily digest job: Tripped (nothing paused)". For a critical early trip: "QuickBooks (Intuit): Tripped, still trying".
- What it means: the item has a name, and the action says what really happened.
- What happens: `partName` names jobs from `Scheduler::JOBS` (L608-613). `noticeTitle` picks "Tripped" when `stopped` is empty and the trip is early (L660-664). "Holds: <raw id>" leaves "What it affects" (`Thread.php` L110).
- Step-4 test: a trip on an untied job and a critical early trip; no subject, push title or row contains `job:`, `wf:` or `svc:`, or says Paused with nothing paused.

**10. The logbook misses the trip.**
- Module and role: Control Board (logbook).
- Field or zone: history (R103's logbook surface).
- What the card shows: "14:13 Trip: W2 … paused W2" between the warning and its close.
- What it means: the finished story has its turning point.
- What happens: `tripped()` and `trippedEarly()` add a logbook line (L218-229, L562-570).
- Step-4 test: warning → trip → recover gives three logbook lines, the middle one Trip.

**11. Module cards: no four zones, no role or criticality, seven stages, stage hidden on phones.**
- Module and role: every module (Railway, GitHub, the dispatcher, sources).
- Field or zone: all four zones.
- What the card shows: zone 1, "Railway · Host · Not critical · Hobby $5 · Operational · Working". Zone 2, what it feeds. Zone 3, sensors and keys. Zone 4, actions and Fix it. Closed at 390: name, stage badge, state.
- What it means: Jason's eight stages, the same card for every module.
- What happens: the card is drawn from the manifest (role, criticality, `required_keys`, `sensors`, `fix_it_routes`, `incident_radius_rules`). The existing parts are kept and placed in their zones (build on what's there). Stage words follow 0175 §1.
- Step-4 test: rendered at 1280 and 390, every module card has its four zones in order; the closed row shows stage and state at 390; Railway and GitHub match `manifests-railway-github.md`.

**12. Workflow assignment: no cadence, no clickable queue counts, Retry failed off the board.**
- Module and role: each Source assigned to W0-W12.
- Field or zone: zone 2, Feeds; workflow assignment.
- What the card shows: per task, "every 5 min / nightly 1-5 AM", "its backup: A, then B", queue counts (done 83 · queued 71 · leased 3 · failed 1), each opening its rows, and Retry failed.
- What it means: what each module is doing for each workflow, down to the rows.
- What happens: `tv_cb_steps` gains cadence (`every_minutes`), counts linked to the rows (the queue's table tool), and Retry failed for every task that has a failed state (today `crawler-console.php` L120, coverage and identity only).
- Step-4 test: on wf-W1 at 1280 and 390, a count opens its rows; Retry failed requeues only failed rows and says how many.

**13. Stale words in the admin.**
- Module and role: Modules, Pulse.
- Field or zone: page notes.
- What the card shows: true words.
- What happens: remove "An email goes to the admins at 80% and 100% of a cap" (`ui/outside-services.php` L236), Pulse's "Mark resolved" fallback (`Pulse.php` L1293), "with Acknowledge" (`Notify.php` L82). Fold "The crawler has stopped" (`Pulse.php` L371) into the `silent:run` alarm.
- Step-4 test: grep after the release finds none of these.

**14. Literal colours on the board.**
- Module and role: Control Board.
- What happens: replace the board CSS's 21 hex values (`control-console.php` L1000) and the light's fallbacks (`router.php` L134) with tokens.
- Step-4 test: grep for hex colours in the board's files returns none.
