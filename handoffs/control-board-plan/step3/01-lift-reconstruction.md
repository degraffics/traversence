# The lift of 2026-11-21: what "Check and start again" did (step 3, worked case 1)

Read-only reconstruction. Nothing on the live site, the repos or the mirror was touched.

- **Live code:** the mirror at Sync 2026-11-21 14:26 UTC (`aeb5f31`), copied whole to
  `step3/lift/x/y/live/` (sync-app = app/, sync-storage = website/). The only changes in the copy: `Mailer.php` and
  `WebPush.php` replaced with recorders (as `tools/dispatcher-tests/r1.php` does).
- **Database:** `lv_g1`, reset from `release-b-tests/start-schema.sql`. Missing tables were created from their use in
  code (`step3/lift/boot.php`).
- **Probes:**
  - `step3/lift/boot.php` raises the starting state through the real code paths.
  - `cap.php` captures the state after each press.
  - `w3-press.php` is what Jason did. Its outputs are `w3-press-index200.out` (Common Crawl's index answered) and
    `w3-press-indexreset.out` (it didn't).
  - `w1-press.php` (CASE=A|B, ORDER=of|fo) is what would have happened had he also pressed W1's trips. Outputs:
    `w1-{A,B}-{of,fo}.out`.
  - `hypo-35.php` is a press on issue #35's own row.
- **Marks:** **[obs]** = observed (Jason's account, or the 10:19 snapshot). **[sim]** = simulated (seen in a probe on the
  copy). **[inf]** = inferred from the code, not seen.
- **One real outside call.** The W3 probe made the same two public read-only GETs the press makes:
  `data.commoncrawl.org/crawl-data/index.html` and `index.commoncrawl.org/collinfo.json`. From the sandbox the index
  host answered 200 on some tries and reset the connection on others. That is why there are two outputs.

---

## Reconciliation (a)–(f) with Jason's account

Jason pressed **Check and start again on W3's trip only** (`pf:wf:W3`). The two W1 trips stayed tripped. **[obs]**

### (a) Why the trip closed with no real check, and whether Common Crawl's index answered

**What the press ran** (one call: `ControlAlarms::resolve`). The route is `website/admin/api.php:219-224` →
`app/lib/ControlAlarms.php:299-359`.

1. **The pre-flight branch** (`ControlAlarms.php:306-313`). It re-runs W3's own pre-flight line,
   `Preflight::run('wf:W3')`.
   - That line is computed from stored results: `Preflight::workflow`, `Preflight.php:1403-1466`. It makes no outside call.
   - Since release #247 (live at 12:26), a red service line that a backup covers counts as **"covered"**
     (`Operations.php:975`), or holds only the tasks that use it (`Operations.php:977`).
   - So Common Crawl's red line no longer holds W3 whole. W3's line is amber or "wait", never red, whether #35 is open
     or not. **[sim]** In both outputs `wf:W3` reads "Its gate: the switch-on check has no red line for it".
   - The trip had outlived its cause. A line that turns amber doesn't close a trip: only ok or na does
     (`Preflight.php:306`). So since 12:26 the trip held W3 whole on a check that no longer held it. **[inf]**
2. **The pause branch** (`ControlAlarms.php:318-334`).
   - `Preflight::refreshFor('wf:W3')` (`ControlAlarms.php:328`, `Preflight.php:389-405`) re-tests every service line
     W3's needs name if it is older than `pf_fresh_mins` (60 min, `Thresholds.php:89`). Common Crawl's line is one of
     them.
   - That test **is an outside call**: two GETs, `data.commoncrawl.org/crawl-data/index.html` and
     `index.commoncrawl.org/collinfo.json` (`Preflight.php:1112-1113`).
   - `Preflight::run('wf:W3')` then runs again. It isn't red, so `Automation::sysResume` lifts W3's pause
     (`ControlAlarms.php:333`), and the alarm is closed by its own UPDATE (`ControlAlarms.php:353-355`).

**Was it a real check?** Partly.
- W3's own check was real, but it read stored results. It passed because #247 had changed what holds W3, not because
  anything was fixed.
- The outside call fetched `collinfo.json`: a static list of crawls. It is not the index query that answered 504 for 30
  hours (the snapshot's reading "504 Gateway Time-out", **[obs]**).
- So "it answered" proves the host serves a file. It does not prove the queries the crawler makes work. **[inf]**

**Did the index answer?** It must have, for trips to go from 4 to 2 (see (b)): a 2xx from `collinfo.json` is the only
path inside this press that closes #35. **[inf, from obs + sim]**

If the outside call had found Common Crawl's line fresh (tested within the hour), no call was made, and the trip still
closed on stored results. **[sim]** `w3-press-indexreset.out` shows the same: W3 closed while #35 stayed open.

**The words Jason saw.** The notice he saw flash:

> Success: Resolved by Jason: Checked and started again Started again: W3 (its tasks wait with it). It's in the logbook.

**[sim]** These are the exact words of `ControlAlarms.php:351`, plus `admin-control.js:335`.
- The note runs into "Started again" with no full stop (the empty note is replaced at `ControlAlarms.php:304`).
- "Started again: W3" is false in practice; see (d).

### (b) Why trips went from 4 to 2: one press, and a side effect

The trips before the press **[obs]**:
- `pf:wf:W3`
- `svc:issue:35`
- `task:failed:overture_bulk`
- `task:failed:foursquare_bulk`

One press closed two of them **[sim, `w3-press-index200.out`]**:
1. `refreshFor` → `Preflight::http` gets 2xx from `index.commoncrawl.org` → `ServiceHealth::answered($host, 'site')`
   (`Preflight.php:682`).
2. That rule was added with #247: "the site's own check answering closes the crawler's back-off at that host too"
   (`ServiceHealth.php:299-304`, its comment naming "Common Crawl's #35").
3. → `ServiceHealth::resolve(35, uid 0)` → `$close` → `ControlAlarms::resolve(<#35's alarm>, 0, 'the system', 'Cleared
   by its check: …')` (`ServiceHealth.php:323`).
4. #35's row reads "Resolved by the system: Cleared by its check: It answered the site's check again (… UTC): the
   crawler's calls start again", acknowledged by "the system". Its hold of 2099-01-01 is cleared, and the crawler's
   plan stops sending `index.commoncrawl.org` as a hold. **[sim]**

The same 200 from `data.commoncrawl.org` closed **issue #22** (a warning) too. **[sim]**

The Common Crawl switch-on warning (`pf:so:commoncrawl-…`) stayed open, still reading "Held: Issue #35", until the next
5-minute watch closed it ("Its check passed"). **[sim]**

So trips go 4 → 2. **[obs + sim]** If the index had not answered, they go 4 → 3: W3 closes and #35 stays open.
**[sim, `w3-press-indexreset.out`]**

Neither close is tied to the other in the code:
- W3's resolve does not close #35. `ControlAlarms.php:346-348` closes only an `svc:issue:` alarm's own issue.
- The closing of #35 is a hidden side effect of a refresh made for W3.

### (c) Why Read a source (W3) and Common Crawl (W3) stayed off ("nobody had set this switch before")

These two switches are `crawl_capture_tasks.enabled = 0` for `source` and `commoncrawl`. **[obs]**
- They were written by a trip before decisions/0175: `ControlBoard::stopOne` → `Automation::setTask(..., false, $why,
  …, who = "the Control Board (a trip)")` (`ControlBoard.php:574-588`).
- They are the system's switch-offs, not a person's.

What the rules say:
- **0175 §7, as written** (`decisions/0175-one-lifecycle-for-every-module.md:270-280`): each goes back to the last
  value a person set; where no person ever set it, to what Start the system set.
- **The code** (`Automation::putBackTripSwitches`, `Automation.php:195-236`, run each watch by `settle0175`): it puts
  back only where `automation_log` has a line by someone other than the board, and that line was "on"
  (`Automation.php:206-209`). Start the system counts as a person.
- **0175's note of 2026-11-20** (`0175…md:494-497`): Jason chose **"Keep for a person"** for switches "a past trip
  left off with no person's setting to go back to". They stay off, listed as left off by a closed trip; nothing turns
  them on by itself.

**Which is true now:** the note governs. These two have no person's line and no Start the system line, so they stay off
until a person switches each one on. **[inf; the exact `automation_log` rows need a live read]**

The words follow from that:
- While the W3 trip was open, the board folded the two switches under it as "switched off for it by the board"
  (`ControlBoard.php:211-215`, `pausedHtml`'s `$sub`).
- When it closed, they moved to "held by the system with no open trip" with the words Jason quoted
  (`ControlBoard.php:357`). **[sim]** The words are verbatim the same.

Two faults remain:
1. "Nobody had set this switch before" is written for every such switch (`ControlBoard.php:214, 357`). It is never
   read from `automation_log`. A switch whose person last set it **off** also stays off (`Automation.php:208`), and
   would carry the same false words. **[inf]**
2. The trip that left them off closed with "Started again: W3", and nothing on its row, in its words or in the press
   mentions them. The decision keeps them off, but the press claims a start that they prevent. **[sim]**

On "Outside services: 3 off on purpose": these two task switches are not in that count. They are engine items in the
"sys" group (`ControlBoard.php:215`; health count `ControlBoard.php:318-331`). That line most likely changed because
Common Crawl's own part stopped being "down" once #35 closed. **[inf]**

### (d) What W3 is doing now: held through those switches

- `system_pauses` for W3 is lifted ("Checked and started again by Jason"). **[sim]**
- W3's crawler tasks are both off. `CrawlService::plan` skips any task with `enabled = 0`
  (`crawler/CrawlService.php:112`).
- **[sim]** After the press the plan holds `targets, identity, refresh`. No `source`, no `commoncrawl`.
- W3's two site jobs are shipped off: `page_read_tick` and `vectors_tick` (`Automation.php:62-63`).
- **W3 does nothing.** It is "started" in name, and held by the old switches in fact. **[sim + inf]**
- Before the press the same was true. Pausing W3 again with the trip held nothing more. Those switches have held W3's
  crawler work since the pre-0175 trip that wrote them. **[inf]**

### (e) What the Pulse case, the email and the push say now

None of `resolve`, `close` or `closeKey` sends anything. Only `recovered()` (`ControlAlarms.php:593-606`) and
`escalated()` update the thread.

| Thread | In Pulse | Email and push | The message row |
|---|---|---|---|
| **pf:wf:W3** | The alarm's item is gone (`Pulse.php:98` skips cleared alarms). Pulse's own list leaves out messages that carry a `pulse_ref` (`Notify.php:684`). So the message isn't shown anywhere in Pulse **[inf]**. | Nothing new sent. **[sim]** Recorders: mail `[]`, push `[]`. | Still `alarm.trip`, title "SYSTEM ALARM NOTICE: W3 Sources and the place store: Paused by the system", body "Paused by the system (a trip) since …", **unread**. **[sim]** |
| **svc:issue:35** | Gone, the same way. | Nothing sent. Closed by the system, but not as Recovered, so there is no "Recovered" notice either. **[sim]** | Still "SYSTEM ALARM NOTICE: Common Crawl: Paused by the system", unread. **[sim]** |
| **W1 ×2** | Still open trips. | Nothing new. | Unchanged. **[sim]** |

So Jason's inbox and phone end both threads on "Paused by the system". The board's logbook ends one on "Resolved by
Jason" and the other on "Resolved by the system".

The model (§1.1) asks for one message, closed in place as **Recovered**, a notice with nothing to press. **[inf, from
code + sim]**

### (f) The UI path: banner first, actions below, W1 inside "3 engine feeds failing"

- **The order of the page** (`admin/control-console.php`):
  - The top strip comes first: the live diagnostics `ControlBoard::pausedHtml` (`control-console.php:757-760`), its
    health line and "Paused now". Then the pre-flight result and core hosts (`:761-771`).
  - Then the six stage tabs (`:784-791`) and the table tabs.
  - The alarms list comes last, `tv_cb_alarms` (`:830`, panel at `:588`).
  - The trips are named in the strip, but its links open "where it's switched or fixed". The strip has no
    Check and start again. That button exists only on the alarm rows (`:617`) and on a part's row (`:488`). **[obs +
    inf]**
- **The grouping:**
  - `ControlAlarms::groups` (`ControlAlarms.php:393-405`) groups open alarms by `system:level:family`
    (`groupKey`, `:384-387`).
  - `systemOf` (`:363-371`) puts every `wf:`/`t:` part and every service a workflow's need reads into **engine**.
  - `familyOf` (`:375-378`) puts `crash` and `retry` into **failing**.
  - So the two W1 task trips (kind `crash`) and #35 (kind `retry`) became one row, "3 engine feeds failing"
    (`GROUP_WORDS`, `:380`). **[sim]** Groups: `engine:trip:failing [#35, foursquare, overture]`.
  - W3's trip (kind `check`) stayed its own row, which is why it was the one in reach.
  - The group is a closed `<details>` (`control-console.php:657`, no `open`). Its only button is "Acknowledge all 3"
    (`:659`). Each member's Check and start again is inside it.
- **What it means:**
  - "Feeds" is a label for the board's engine system. It is not what the alarms are.
  - Two of our own tasks failing, and an outside service's back-off, read as one kind of thing.
  - The group hides the actions for the trips that hold the most (all of W1).
  - Its one action, Acknowledge, is the one that turns the top light green while their pauses hold (g4 F1).
  - This fails the standard's point 3: "actions sit where the problem is reported". **[inf]**

---

## Starting state (2026-11-21, before the press)

| Trip | State [obs = snapshot 10:19 / Jason] | Hold scope | Thread |
|---|---|---|---|
| `pf:wf:W3` | Trip since 2026-11-20 15:05. Title "Its gate: 1 red line(s): Common Crawl (W3) (Held: Issue #35…)" [obs]. Kind "Its check", seen 3 times, Nov 20 09:00 → Nov 21 09:00 [obs, Jason]. | `system_pauses wf:W3` ("W3 (its tasks wait with it)") [obs, as stopped]. Under live code since 12:26 its line is amber, not red [inf/sim]. | One `alarm.trip` message: email and push "Paused by the system" [sim; that it was sent is inferred]. |
| `svc:issue:35` | index.commoncrawl.org, crawler caller, server kind, 504, back-off spent [obs]. | `service_issues.hold_until = 2099-01-01` (the trip's hold). The crawler's plan sends it as a hold [sim]. Not in `system_pauses`. | One message, as above. |
| `task:failed:overture_bulk` | Trip (tries ran out 09:31, run #1290) [obs]. | Its own `system_pauses wf:W1`: **all of W1** [obs "W1 (its tasks wait with it)"]. | One message. |
| `task:failed:foursquare_bulk` | Trip (09:20, run #1288) [obs]. | A second `system_pauses wf:W1` [obs/sim]. | One message. |
| (switches) | `source` and `commoncrawl` (W3) off by a pre-0175 trip [obs, Jason]. Master on [inf: W3 tripped, which needs the master on]. | The plan skips them [sim]. | none |
| (warnings) | `pf:so:commoncrawl-…` and `svc:issue:22` (data.commoncrawl.org 503) [obs]. | — | Board only. |

Board counts before the press: trip 4. **[sim; obs: "4 trips"]**

## Press by press: what Jason did (one press)

| # | Entry state | Action and code path | What it checked (really / claimed) | State after | Side effects | What Jason saw | What the model and the standard require |
|---|---|---|---|---|---|---|---|
| 1 | The 4 trips above. W3's line amber under #247. Common Crawl's service line older than 60 min. | **Check and start again on W3's row** → `admin-control.js:313-335` → `api.php:219-224` → `ControlAlarms::resolve`. Pre-flight branch `:306-313`; pause branch `:318-334` with `Preflight::refreshFor` `:328`. | **Really:** W3's line, on stored results: not red, because #247 counts Common Crawl as covered. The refresh made one outside call to Common Crawl (two GETs, `Preflight.php:1112-1113`). `collinfo.json` answered 2xx **[inf from 4→2; sim]**. **Not checked:** the index query that failed (504); W3's tasks; W1. **Claimed:** "Checked and started again… Started again: W3". | **pf:wf:W3:** closed "Resolved by Jason: Checked and started again Started again: W3 (its tasks wait with it).", acknowledged by Jason, Undo kept a day **[sim]**. **svc:issue:35:** closed "Resolved by the system: Cleared by its check: It answered the site's check again…"; issue resolved, hold cleared **[sim]**. **svc:issue:22:** closed **[sim]**. `pf:so:commoncrawl` stays open "Held: Issue #35" until the next watch **[sim]**. **Pauses:** `wf:W3` lifted; `wf:W1` ×2 open **[sim]**. **Switches:** `source` and `commoncrawl` still off **[sim/obs]**. | The crawler may call `index.commoncrawl.org` again, but no W3 task is enabled to call it **[sim/inf]**. Two threads left "Paused by the system", unread; no email or push **[sim]**. The two switch-offs move to "held by the system with no open trip" **[sim/obs]**. Top counts: trip 2 **[sim; obs "4 to 2"]**. The W1 group now reads "2 engine feeds failing" **[sim]**. | A "Success" notice with the line above **[sim; obs "a notice flashed"]**. The row left the list. "Paused now": the two left-off switches with the closed-trip words **[obs + sim, verbatim]**. "Data gathering: 1 down (W1 Listings)" **[obs]**. | One state model. The check is the failed component's own: the index query that 504'd, not `collinfo.json`. #35 closes as Recovered through its own thread, said once. W3 resumes only what its trip paused, **and** the press says plainly that W3's two tasks stay off by a decision, with Switch on beside it. No "Started again" while nothing starts. Recovered notices replace "Paused by the system" in Pulse, email and push. |

If the index had not answered (or the line was fresh), the same press:
- closes W3 with the same words;
- leaves #35 open, and `index.commoncrawl.org` held;
- leaves 3 trips. **[sim, `w3-press-indexreset.out`]**

## What would have happened had he pressed W1's (he didn't): cases A and B

He pressed W3's first in every run. Order `of` is overture first; `fo` is foursquare first. The results are the same
either way, mirrored.

### Case A: W1's pre-flight not red

**First W1 press (overture):**
- **Check:** the whole of W1's pre-flight (`ControlAlarms.php:322-331`). It never tests the failed task's own read
  (Overture's S3/DuckDB), nor our endpoint, which IR-0001 names.
- **Result:** its own `wf:W1` pause lifts. The other trip's `wf:W1` pause still holds W1 **[sim]**. The plan doesn't
  change: `targets, identity, refresh` **[sim]**.
- **Jason would see:** "Success: Resolved by Jason: Checked and started again Started again: W1 (its tasks wait with
  it). It's in the logbook." That is **false**: W1 is still paused.
- **Notifications:** none.
- **Model:** two trips with one cause should be one hold on the one component, and the words should say what still
  holds. W1 should never have been held whole for one task.

**Second W1 press (foursquare):**
- **Check:** the same whole-W1 check.
- **Result:** both pauses lifted. The plan now has `coverage, second_look, targets, identity, refresh` **[sim]**. The
  bulk tasks wait for their nightly window, so neither failed task is tested by the lift **[inf]**.
- **Jason would see:** the same Success line.
- **Notifications:** both W1 threads stay "Paused by the system", unread **[sim]**.
- **Undo** works: "Undone: the trip is open again, and W1 … stopped again." **[sim]**
- **Model:** Recovered only once the task's next run, or a check of its component, passes. Until then the threads say
  waiting, not "started".

### Case B: a W1 need with no source

The need is "Places by kind and town": our copy missing, and Overture's line red.

**Each W1 press:**
- **Check:** the whole-W1 pre-flight, red.
- **Result:** `RuntimeException` → "Not resolved" (`ControlAlarms.php:331, 339-343`). The trip stays open with
  `resolve_note`; both pauses stay **[sim]**.
- **Side effect:** the refresh raises a new `pf:nd:places` warning alarm. Pressing one button opens another alarm
  **[sim]**.
- **Jason would see:** "Error: Not resolved at HH:MM UTC by Jason: W1 (its tasks wait with it): Not started: its check
  is red. What holds it: Needs: Places by kind and town ✗, Businesses in bulk (on a backup), …" **[sim]**
- **Undo:** "It wasn't resolved, so there's nothing to undo." **[sim]**
- **Model:** a task whose only source is down holds that task, not W1, and its fix is on the need's line. The red need
  names W1 as held, though only `coverage` uses Places.

### A press on #35's own row (he didn't)

- **Result:** closed in **3 ms**, with no outside call. `ServiceHealth::resolve(uid > 0)` → `closeKey`
  (`ServiceHealth.php:321`, via `ControlAlarms.php:347`). **[sim]**
- **The record breaks:**
  - `ControlAlarms::resolve`'s own UPDATE then matches no row (`:353-355`, `WHERE cleared_at IS NULL`), so
    `resolved_at` and `ack_at` stay NULL.
  - The board counts it as an **unseen** trip (`counts`, `:538-549`): unseen 1.
  - Undo is refused: "It wasn't resolved…". **[sim]**
- **Jason would see:** "Success: Resolved by Jason: Checked and started again It's in the logbook."
- **Model:** Check and start again must run the component's check, and close only when it passes.

## Where it broke, against the end-to-end standard (CLAUDE.md, 2026-11-21, commit 17d76af2)

### 1. Contract and boundaries

- **Recovery rules are split across five files, with hidden sub-state changes.**
  - The resolve logic lives in `ControlAlarms::resolve`, `ServiceHealth::resolve`/`answered`, `Preflight::alarm`,
    `Automation::putBackTripSwitches` and `ControlBoard::pausedNow`.
  - The press on W3 changed #35, #22 and the crawler's holds as a side effect of a refresh (`Preflight.php:682` →
    `ServiceHealth.php:299-304` → `:323`). **[sim]**
- **A hold's scope isn't one model.**
  - W1 is held whole twice over for two task failures: two `system_pauses wf:W1` rows (`ControlAlarms.php:285`).
  - W3 was held whole by a trip whose own line had become amber (`Preflight.php:306`).
  - W3's real hold, two switches, sits outside both, in `crawl_capture_tasks`. **[sim/obs]**
- **"Feeds" groups by board system, not by cause or hold** (`ControlAlarms.php:363-405`). **[sim]**

### 2. Every state transition

| Transition | What happens today | Evidence |
|---|---|---|
| Trip → closed by a person | Sends nothing: no Recovered; Pulse, email and push stay "Paused by the system" | `ControlAlarms.php:299-359`; **[sim]** mail and push `[]` |
| Trip → closed by the system inside a person's press (#35) | Closed as "Resolved by the system", not Recovered, with no thread update | **[sim]** |
| A trip whose line turns amber | Stays a trip, holding its workflow whole | `Preflight.php:306`; **[inf + sim]** |
| Trip closed while its leftover switch-offs stay | "Started again: W3", while the plan runs no W3 task | **[sim]** |
| Two trips on one workflow | Lifting one claims "Started again: W1" while the other still holds it | **[sim]**, case A |
| Pressing on a red need | Raises a new alarm (`pf:nd:places`) | **[sim]**, case B |
| Issue trip closed by a person | No `resolved_at` or `ack`: an unseen trip, and Undo refused | **[sim]**, `hypo-35` |

### 3. UI matches the action

- **The actions aren't where the problem is reported.**
  - The banner names the trips but has no button. The W1 trips' buttons are inside a closed group whose one action is
    Acknowledge (`control-console.php:647-662, 757-760, 830`). **[obs + inf]**
- **The words are not a true status.**
  - "Checked and started again Started again: W3" for a workflow with no task on.
  - "Paused by the system" left in email and push after both closed.
  - "nobody had set this switch before" stated without reading the log (`ControlBoard.php:214, 357`). **[sim/inf]**
- **The thing the operator thinks he's fixing (Common Crawl, #35) isn't the thing the button checks.**
  - Its check is `collinfo.json`, a static file, not the index query that 504'd (`Preflight.php:1112-1113`). **[inf]**

### 4. Proof

- No test covers any of these paths:
  - a person's resolve updating the thread (`tools/dispatcher-tests/r1.php` covers only Recovered and the Escalation
    Notice);
  - two pauses on one workflow;
  - a trip outliving an amber line;
  - a trip's leftover switches after it closes;
  - `answered()` closing a crawler trip from inside another trip's press.
- The press is not idempotent in its effects. Its outcome depended on whether a third party's static file answered
  in that second (**[sim]**: 200 on some tries, reset on others).
- R189's scenario test doesn't exist yet (step 2 README).

## What we can't know without live data

These live reads (2026-11-21, 13:00–14:45 UTC unless stated) would confirm or correct the above:

1. **`control_alarms`:**
   - The rows for `pf:wf:W3`, `svc:issue:35`, `svc:issue:22`, `task:failed:overture_bulk`,
     `task:failed:foursquare_bulk` and `pf:so:commoncrawl-common-crawl-a5bbfe76`.
   - Their `level`, `cleared_at`, `cleared_how`, `resolved_name`, `resolve_note`, `ack_name`, `stopped` and `last_at`.
   - Expected: W3 "Resolved by Jason: Checked and started again Started again: W3…"; #35 "Resolved by the system:
     Cleared by its check: It answered the site's check again (Nov 21, HH:MM UTC)…", within a second of W3's
     `resolved_at`.
2. **`system_pauses`:** every row with `kind = 'wf'` and `ref IN ('W1', 'W3')`, open and cleared. Expected: W3 cleared
   "Checked and started again by Jason"; two W1 rows open.
3. **`crawl_workflows`** (W1, W3) and **`crawl_capture_tasks`** (`source`, `commoncrawl`, and W1's tasks): `paused`,
   `paused_why`, `enabled`, `off_why`, `off_by`, `off_at`.
4. **`automation_log`** for `task/source`, `task/commoncrawl` and `workflow/W3`, all time. This confirms that no
   person's or Start the system's line exists, which is why `putBackTripSwitches` left them off.
5. **`service_issues`** #22 and #35: `state`, `resolved_at`, `resolved_by`, `resolved_how`, `hold_until`. Also any new
   issue at `index.commoncrawl.org` opened after the press: did the crawler hit 504 again?
6. **`preflight_results`:**
   - `svc:commoncrawl`: its `at`, `s` and `said` just before and after the press. Was it older than 60 min, and did
     `collinfo.json` answer?
   - `wf:W3` and `nd:sources`.
7. **`comms_messages` and `comms_route`** for `thing IN ('alarm:<W3 id>', 'alarm:<#35 id>', 'alarm:<W1 ids>')`:
   `kind`, `title`, `body`, `read_at`, `updated_at`. Expected: unchanged since the trip, no route rows after the press.
8. **`control_log`** for procs `wf:W3`, `svc:commoncrawl`, `wf:W1`, `so:commoncrawl-…`: the `resolve` and `clear` lines
   and their order.
9. **The crawler's next plans** (crawl_runs and run items after the press): did any W3 task run, and was
   `index.commoncrawl.org` still sent as a hold?
10. **Bluehost's access log or the ledger** for `POST /admin/api/config/control` with `action=resolve`: the time and
    the response. This confirms there was one press and no other.

### The sandbox's limits

- Run numbers, alarm ids and times differ from live.
- cPanel isn't connected, so the scheduled-jobs part reads "wait".
- The other services' lines were seeded as passing, so that only Common Crawl's line was re-tested.
- Two sandbox-only trips (Search, Sign-in, from `ControlSensor` on the next watch) are noise and are not part of the
  reconstruction.
