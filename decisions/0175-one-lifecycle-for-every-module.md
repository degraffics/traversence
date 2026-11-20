# 0175: One lifecycle for every module: our services and account apps, from selection to removal, acted on from the board

Status: **accepted** (Jason, 2026-11-20, draft 2, approved with the decision pop-up; reviewed by the session manager).
The Control Board session wrote it at Jason's request, relayed by the session manager. Draft 2 rewrote draft 1 (one
state for a service) with Jason's five messages of the morning. His answer on where a task's switch goes is quoted in
§6 and applied there, and in §3. **Built (2026-11-20):** release 1, Paused by the system (#222), with feeds-words (#223) and the watch keeping the hour (#228). **Held** (Jason, 2026-11-20: "once release is live hold building anything more"): release 2a (the board as the one place to act), 2b (one control board in the menu), the Census backup, releases 3–4. Their scope and sources: `handoffs/control-board-2026-11-20.md`.

Builds on:
- 0169: the Shop's App store and one lifecycle for every app (accepted 2026-11-16, not built). Its §5 lifecycle is
  folded into §1 here.
- 0173: one lifecycle for every outside service: the Service register as its one home, its stages, and each service
  defined once.
- 0171: the System Control Board:
  - its trips (§7 and its notes) and the escalation for critical parts (§5's note of 2026-11-19);
  - maintenance (release 4a);
  - the drawer (`at_hand`).
- 0163 §9: the switches; §10: switching back on through the check.
- 0065, 0066, 0058 §13 (outside data, nations' land, counts never who); 0145 (no test moves real money); 0165 (staging).

Amends (each marked **Note for Jason** where it happens):
- 0169 §5–6: the App store builds on this decision's stages (§1) and its one definition (§4). 0169's details are kept
  inside the stages.
- 0173 §2: its stages are named and ordered as Jason's eight (§1). Its buttons and history are kept.
- 0171 §7: a fault no longer switches anything off. The system pauses the module, with its reason (§3).
- 0163 §9: a crawler task's switch moves to its workflow's own page on the board (§6).

Checked against the live mirror at "Sync: 2026-11-20 04:00 UTC".

## What Jason asked for

His words, 2026-11-20, in the order he gave them.

On Automation and the board:
> automation and control board seem like they repeat each other, but in different ways. I see a service like a module or
> plug-in:
> 1. Service selection and its configuration
> 2. Integrated into our system (preflight)
> 3. Activation: switched on
> 4. Assigned to workflows to render support
> 5. Operational: Real Time Monitoring, Performance, Diagnostics, and Reporting
> 6. Maintenance
> 7. Deactivation
> 8. Removal (System keeps historical data even after removed)

The guiding picture:
> The board is like the dashboard of the automobile, it show your actual driving data and can be used to monitor and
> adjust performance based on data readings, the actual component like gas tank is where the gas lives the it only works
> if the car is turned on? You don't fix the dashboard to fix the gas tank.

Its reach:
> the model I am seeing is the same model we will build upon for the app store and its apps. Our system is modular and
> adding/removing apps to for abilities/tasks/services will be essential for our platforms success.

On the stages and 0169's lifecycle: "together they cover the entire lifecycle".

On how deep a module goes:
> there are two things I see: 1. an app can be a standalone self contained solution or 2. it can be an integration into
> our platform core components, so we need to ensure we understand how both are to be used and have strict procedures
> for integration.

His corrections to draft 1:
> Activate (person), Pause (System/person), Deactivate (person), Delete (person)
> Holds vs OFF: the app service is not turned off when its performance is not functional unless it requires it off for
> maintenance, hence PAUSED. Like a faulty headlight or dead battery, one can be changed while the system is running
> another might need the system shut down to replace or repair.
> TRIPS: third trip of the same cause within 24 hours waits for a person. who set this? we have an escalation process
> already set/// the action and timeline is set!
> I dont want multiple pages where one thing affects and another and I have to go hunting through places to find how to
> solve the one start to end process. The board should provide me direct action capabilities to fix issue, by using,
> drawers, pop-ups, side-panel to bring me the component or tool to fix the issue at hand.

And on 2026-11-19: "If this was resolved by system or marked as resolved by admin it should be handled."

## What exists (checked in the live code)

His screenshots show Geofabrik in four states, in three places, at once:

| Where | What it said |
|---|---|
| Service register and Automation, the service | **On** |
| Automation, task `geofabrik_bulk` | **Off**: "Shut down from the Control Board by the Control Board (a trip)" |
| The board, its alarm | **Trip**: "its check passes now; it waits for an admin's Resolve" |
| The board, its reading | **Not ready**: "off until Start the system … W1: On a backup" |

Why the pages disagree:
- **A fault uses a person's switch.** `ControlAlarms::stop()` calls `ControlBoard::stopOne()`, which calls
  `Automation::setTask(…, false, 'Shut down from the Control Board by the Control Board (a trip)')`. That's the switch a
  person presses on Automation, so a fault reads as Off, as if someone chose it. The "read a source" and Common Crawl
  tasks were switched off the same way.
- **A passing check doesn't end a trip.** `ControlAlarms::clear()` on a trip only records `good_at` and waits for
  **Check and start again**.
- **Five places set a task's switch:**
  - the Automation page's Tasks table;
  - Insights → Crawler;
  - Start the system (`SystemStart`);
  - the board's Shut down (`ControlBoard::stopOne`, `shutDown`);
  - the trip (`ControlAlarms::stop`).
- **A module's parts live on different pages:** its card (register), its tasks (Automation), its pre-flight line
  (Pre-flight), its alarm (the board) and its maintenance (the board).

## Decision

### 1. One lifecycle: Jason's eight stages, with 0169's details within them

Every module, our platform services and account apps alike, goes through the same eight stages. Each module shows the
one stage it's in, the same everywhere it's named: its card, the register, the board, an account's Apps page.

| # | Jason's stage | What it holds (0169's details within it) | Who does it |
|---|---|---|---|
| 1 | **Selection and configuration** | What it does, plans and cost, what it can see and do, **"Which should I use?"**, **"Here's what you'll need"**. Install with every permission listed. The module's guided setup: its key, or signing in and approving (0169 §5.1–3; 0173's Proposed, Added, Key in). | Us (platform) or the account (app) |
| 2 | **Integrated** | Its pre-flight test passes before it can be switched on. An integration also meets the procedure in §5. | The system tests; a person fixes |
| 3 | **Activation** | **Activate**: switched on, by us for a platform module, by the account for its app, through the check (0163 §10). | A person |
| 4 | **Assigned** | The workflows, tasks and features it supports, each named with its switch (§6). | A person |
| 5 | **Operational** | Real-time monitoring, performance, diagnostics and reporting (0171). A status card. A problem is an alarm (a Pulse case for an app) with its fix (0169 §5.4). | The system watches |
| 6 | **Maintenance** | **Take out** and **Put back** after its check (0171 release 4a). Updates with new permissions or terms are asked for again; nothing changes silently (0169 §5.5). | A person, or a window set ahead |
| 7 | **Deactivation** | **Deactivate**: switched off, not removed. It says why, with who and when, and comes back the same way, through stage 2's check. | A person |
| 8 | **Removal** | **Delete**: access revoked at the service's end; a plain statement of what happens to the data and what replaces it; money owed keeps working until it closes; **history kept** (0169 §5.6; 0173's Retired and Removed). | A person |

- **The card shows the eight as a strip.** Each stage reached gets a ✓. The stage it's in is lit. A stage not reached
  says what's missing, with its button: "2 Integrated: its key isn't in. Add its key".
- **0173's buttons are kept under these names:** Propose, Add its key, Activate, Pause, Retire it… (now Delete, with
  Retired kept as Delete's record while code remains), Bring it back. So is its history in `service_stages`.
- **Two levels, one model:**
  - **Platform modules** are switched by us. Their card is in the Service register, their dashboard is the board.
  - **Account apps** are switched by the account. Their card is in the account's **Apps** page, which is that account's
    own dashboard (0169 §5).

### 2. The actions, and who presses them

| Action | Who | What it does | Its way back |
|---|---|---|---|
| **Activate** | A person | Switches it on, through stage 2's check | **Deactivate** |
| **Pause** | The system or a person | Stops its work for now. It stays active, keeps its key and settings, and says why | It resumes when the system's check passes, or a person presses **Resume** |
| **Deactivate** | A person | Switches it off on purpose (stage 7), with why | **Activate**, through the check |
| **Delete** | A person | Removal (stage 8), history kept | **Bring it back**, while its code remains |

- **A fault never means Off.** When a module isn't working, the system **pauses** it. It's shown as **Paused by the
  system**, with its reason, in the same place a person's pause shows ("Paused by the system: download.geofabrik.de
  failed 6 times, 09:12 MST. It resumes when its check passes."). Its Activate switch stays as the person left it.
- **Every press records who pressed it**, as switches do today.

### 3. Faults: paused by the system, two kinds of repair, and the escalation that already exists

**Paused by the system** replaces a trip that switches things off (Note for Jason: 0171 §7 amended):
- It pauses only what its cause touches, as narrowly as 0171 §7 already does:
  - a service's calls;
  - a task;
  - a workflow;
  - all gathering, when the crawler itself is down.
- It writes nothing into anyone's switch. Today's `ControlAlarms::stop()` writes into the switches; it moves to a pause
  record of its own.
- **It clears itself when its check passes** (Jason, 2026-11-19: "If this was resolved by system or marked as resolved
  by admin it should be handled"). What it paused runs again, the logbook says so, and Pulse gets a **Recovered** notice
  with nothing to press.
- **It waits for a person only where 0171's escalation already says so.** The escalation stays exactly as 0171 set it
  (§5's note of 2026-11-19, "critical parts trip at once", and 3c's note of 2026-11-18):
  - **A critical part** (its duty: page, search, payments, database, procedures; Thresholds & gates → Critical parts):
    a trip at once, to Pulse, the Founder's email and the push, while the system keeps trying.
  - **The system fixes it:** "Recovered: <part> was down <n> min, the system fixed it.", a notice with nothing to press.
  - **Its tries run out** (`control_fix_tries`, default 3, or `control_fix_minutes`, default 60): the Escalation Notice,
    "Retries unsuccessful, now admin required: <part>, <duty>". Pulse's clock and its escalation to the Manager, then
    the Director, run as set. Only then does it wait for a person.
  - **Any other part:** a warning while the system tries, Paused by the system when it can't. It's escalated the same
    way.
  - Draft 1's "third trip in 24 hours" guard is dropped: it wasn't Jason's and isn't in 0171.

**Two things are checked at a failure: the component and the workflow** (Jason, 2026-11-20, quoted in §6):
- **The component** (the module): its own check, its own pause, its card.
- **The workflow** that uses it: it **reroutes to a backup service** where one is named, as today's "On a backup" does
  (Tavily to Brave, our OpenStreetMap copy before any server). It's **paused by the system** only when it can't do its
  work without that component. The alarm names which of the two is the source, and the board opens that one.

**Two kinds of repair, declared in each module's definition (§4):**
- **Fix while running** (the faulty headlight): a key, a retry, a setting, a server added. Nothing else stops. The module
  is Paused by the system until its check passes; its dependants carry on without it (§5's fallback).
- **Needs shut down** (the dead battery): the repair is done in **Maintenance** (stage 6, 0171 release 4a). **Take out**
  tells its dependants and pauses them, saying why, until **Put back**. Put back runs the module's check first, then
  resumes them.

### 4. One definition for every module

`app/lib/service-defs.php` (0173 §4) and 0169 §6's app definition become one shape. One entry per module:

| Field | What it holds |
|---|---|
| `level` | `platform` (switched by us) or `account` (switched by the account) |
| `kind` | `standalone` or `integration` (§5) |
| `maker` | `ours` or `connected` (0169 §3: who makes it, a separate axis) |
| `touch_points` | For an integration: the core components and hooks it uses, the data it reads and writes, its permissions |
| `repair` | `running` or `shutdown` (§3), and per fault kind where they differ |
| `fallback` | What the core does without it |
| 0173's fields | Company, hosts, keys, caps, notices, contact, ledger, staging answers, the retired record |
| 0169's fields | Accounts it's for, category, plans, price, permissions, setup steps, checks, webhooks, removal statement |

The generated lists (0173 §4's `--write` / `--check`, and the zip check) cover the new fields. A module missing `kind`,
`repair` or (for an integration) `touch_points` and `fallback` fails the check.

### 5. Two kinds of module, and the integration procedure

This is a separate axis from 0169 §3's ours or connected (who makes it): **how deep it goes**.
- **Standalone:** its own data and screens. The core gives it only the account, billing and Pulse, and removing it is
  clean. For example: 1Password, Gallery storage, Tickets, outdoor tracks.
- **Integration:** plugs into a core component. For example:
  - Stripe and Square into orders;
  - QuickBooks into the books;
  - Facebook into posting;
  - Geofabrik and Wikimedia into the crawler and search.

**The integration procedure: stage 2's requirements.** A standalone module needs steps 1, 4 and 6.
1. **It declares its touch points** in its one definition: the core components, the data read and written, its
   permissions.
2. **It goes only through the owning library's published hooks,** never core tables directly. Each core component lists
   the hooks it allows (the appendix).
3. **Data rules:**
   - never overwrite what a person entered;
   - outside data is its own credited layer (the 0065 pattern);
   - nations' and privacy rules apply (0066, 0058 §13).
4. **Safety:**
   - every outside call asks `StagingGate::hold()` first;
   - no test reaches a live gateway (0145);
   - faults pause, never switch off (§3).
5. **A fallback named before activation:** what the core does without it. Removing it leaves the core working.
6. **Proven before Activation:**
   - its pre-flight test passes;
   - a staging test runs end to end;
   - the fallback is tested by pausing it.

### 6. The board is the one place to act

The car picture:
- **The ignition** is Automation's master switch and the workflows, with Start the system and Pause everything.
- **The components** are the modules' cards, where they're filled and fixed.
- **The dashboard** is the board: readings, performance, diagnostics and reports.
- **A warning light** points to its component.
- **Paused by the system** is the safety cutoff that lifts itself.

Jason's rule: no hunting across pages. **Every alarm opens the component or tool that fixes it in a drawer, a side panel
or a pop-up (`at_hand`), never by leaving the board:**
- the module's card;
- its key (Add, Replace, Put the old one back);
- its pre-flight line, with Run it again;
- its task assignment (Switch on or Switch off);
- **Take out** / **Put back** for maintenance;
- **Activate**, **Pause**, **Resume**, **Deactivate**.

What this means:
- **The card's controls come to the board in place.** Each switch keeps one home (the card, or Automation for the master
  and workflows). The board opens that home over itself, so a press there is the same switch, recorded with who pressed
  it.
- **The board's own adjustments stay:** Check it now, Check and start again, pace or slow down, and the readings,
  logbook, ledger and reports.
- **Start the system, Pause everything and Shut the system down** stay where they are on the board's Start-up and
  Shut-down stages, and on Automation. They're one function, now named as the ignition. **Shut down** one workflow or
  module stays on its row, as Deactivate, recorded with who.
- **A task's switch lives with its workflow** (Jason, 2026-11-20, answering where it goes): "Workflows are multi-step
  tasks or procedures that use services to render a specific action/result/outcome. So if a workflow has a component
  that is failing it needs to either reroute to a back up service or trip if it is not able to function properly. So two
  things are happening at the time of a failure 1. component 2. workflow are checked. The system board will access both
  depending on the source of the issue."
  - A task is a step of its workflow, so its switch is on the workflow's own page on the board
    (https://traversence.com/admin/config/control/wf-W1), with the services each step uses and its backup.
  - A module's card lists the workflows and steps it serves (stage 4), each linking to that switch. It's the same
    switch, never a second one.
  - Automation's Tasks table becomes one line per workflow, "W1: 6 tasks, 5 on. Open W1", linked to that page (Note
    for Jason: 0163 §9 amended). Insights → Crawler's on and off become links to the same switch.
  - The board opens the component or the workflow in a drawer, whichever the failure's source is.
- **Nothing is lost** (playbook: "replacing a screen keeps everything it did"): every page that changes keeps its
  functions, moved, with the old address leading to the new one.

### 7. Today's tasks switched off by a fault go back to what a person set

One database update. It's safe to run twice and needs no person:
- **What it finds:** each task or workflow whose switch was last set by "the Control Board (a trip)". Today that's
  `geofabrik_bulk`, the "read a source" tasks and Common Crawl's tasks.
- **What it does:**
  - each switch goes back to the last value a person set in `automation_log`;
  - where no person ever set it, it goes back to what Start the system set;
  - its open trip becomes Paused by the system (§3), with its check.
- **Kept:** the trip's history and the log. Each change is logged as "Put back to what <name> set on <date>".
- `release.json` carries `"data_reason"`.

### 8. What doesn't change

- 0173's register, keys and Retire record; 0171's board, alarms, logbook, ledger, reports, maintenance and escalation;
  Start the system's order and gates.
- Pulse is still trips only.
- No data is ever deleted at Removal (Jason's stage 8).
- 0169's Shop, plans, carts and the order system's rules for paid apps.

## Build order (each a release Jason reviews)

1. **Paused by the system:**
   - faults pause instead of switching off;
   - the pause clears itself when its check passes, with the escalation as 0171 has it;
   - today's switches are put back (§7);
   - every page's words say both ("On · Paused by the system: …").

   This ends the disagreement Jason saw.
2. **The board as the one place to act** (§6):
   - every alarm opens its card, key, pre-flight line, assignment, maintenance or switch in a drawer;
   - the task's switch goes to its workflow's page, with each step's services and backup; the card lists what it serves;
   - Automation's Tasks table becomes a line per workflow.
3. **The eight stages and the actions on the card** (§1–2): the strip, Activate, Pause, Resume, Deactivate and Delete;
   the register's stage filter in Jason's words.
4. **One definition** (§4–5):
   - `level`, `kind`, `touch_points`, `repair` and `fallback` on every platform module;
   - the zip check refuses a module without them;
   - the appendix's hooks marked "to come" are built as each first integration needs them.

Then **0169's release 1 (the App store)** builds on these: its account-level cards, Apps page and app definitions use
this lifecycle and definition. Nothing in the App store is built on a model of its own (Note for Jason: 0169 §5–6).

## Consequences

- **One answer to "is it on?"** A person's switch, and a pause by the system with its reason and the check that lifts
  it, shown together everywhere.
- **One place to act.** The board brings each fix to the alarm. Nothing needs hunting for.
- **One model for the platform and the App store.** A new service or app is one definition and its connector. An
  integration can't reach core tables, and must name its fallback.
- **The escalation stays as set.** Nothing new decides when a person is called.

## Appendix: core components and their hooks

Integrations use only these. "Exists" means the library and the call are live today (checked at Sync 04:00 UTC); "to
come" is built when the first integration needs it.

| Core component | Owning library | Hooks an integration may use | State |
|---|---|---|---|
| Orders and money | `Orders.php` (0098) | record an order, add to its money ledger (add only), its events | exists |
| Payments at checkout | `MarketPay.php` (0135), `QuickBooksPay.php` (0095) | a business's gateway connect and refresh; `guardReal()`, `testHere()` | exists |
| The books | `Books.php` (0128) | roles, item mapping, requirements | exists; 0166's ledger and connector to come |
| Business listings | `ListingIdentity.php` (0164 §3.1) | the one identity check on every path; never a direct insert | exists |
| The crawler | `crawler/CrawlService.php`, `crawler/OpenLoads.php` | a task, a service call through the ledger, a bulk load by place | exists |
| Outside service health | `ServiceHealth.php` | report a failure, its kind and evidence; the check | exists |
| Places and landmarks | `Landmarks.php`, `Nations.php`, `OpenPlaces.php` | `Landmarks::visibleSql()`, `Nations::pin()`, our OpenStreetMap copy (its own layer) | exists |
| Search | `SearchIndex.php` | index a listing or landmark, refresh one | exists |
| Files | `Uploads.php`, `upload-targets.php` (0127), `Gallery.php` | an upload target; a gallery item | exists |
| Messages to people | `Notify.php` (0172) | `Notify::send` (one dispatcher) | exists |
| Pulse | `Pulse.php` | a case with its steps and buttons (`Pulse::manage`) | exists |
| Secrets | `EnvSettings.php` (0173 §3) | `setKey`, `removeKey`, `putBack` | exists |
| Staging | `StagingGate.php`, `staging-tables.php` (0165) | `StagingGate::hold()`; a table's class | exists |
| Posting to social accounts | (0168) | publish a post, read its result | to come |
| Calendars and bookings | `Bookings.php` (0172 §2.5) | a booking's times and reminders | exists in part; calendar sync to come |
| Storefronts | `Storefront.php` (0139) | a shop's pages and items | exists |

## Questions for Jason (answered 2026-11-20: Approve; a task's switch lives with its workflow, §6)

1. Approve this decision, with its four releases in that order, and 0169's App store built on it afterwards?
2. A crawler task's switch:
   - **on the card of the module it serves** (recommended), or its workflow's page when it serves none, both opened from
     the board in a drawer. Automation keeps the master and the workflows;
   - or it stays on Automation's Tasks table, with the card's button as a second way in.

**Note (Jason, 2026-11-20, relayed by the session manager): pre-flight before a module is active, diagnostics once it is.**
His words: "Pre-flight shows where it belongs: on each module's card as its stage-2 result, and in the Start-up stage
when you turn the system on. 'Run it again' stays there. There's a difference between an app preflight and a system
preflight. App: 1. new apps needs to know if can run prior to integration. 2. when something is paused or deactivated, it
must preflight before it can go active again." And his correction: "once something is active it is not longer
preflight, it is diagnostics."
- **A module's pre-flight** is its own test, and it applies only while the module isn't active:
  - before its first Activation (stage 2): Activate is offered only once it passes;
  - before Activate or Resume after a person paused or deactivated it: only a pass switches it on. A fail says why, with
    its fix in place.
  - On the card it shows as "Pre-flight", with **Run it again**, until the module is active.
- **Diagnostics** apply once a module is active (stage 5). That includes when the system pauses it: the module is still
  switched on, so its diagnostics check it and lift the pause when they pass (§3). The card shows "Diagnostics" once the
  module is active, and the word "pre-flight" never appears for an active module.
- **The system pre-flight** runs at Start the system (the ignition): core hosts, the database, every active module's
  check, and each workflow's dependencies and backups. It's shown in the Start-up stage, with **Run it again**. The 2 AM
  run may stay as a nightly self-test that feeds the cards. It's never the board's top line.
- **The board's top line becomes live diagnostics:** what's paused right now (by the system or a person) and why, each
  item opening its fix in a drawer (`at_hand`). No stale snapshot ("over an hour old") on the dashboard.
- Built in release 2 (the board as the one place to act).

**Note (Jason, 2026-11-20): Trip, and fault.** Asked "Trip or Fault", he agreed with this reading: "I am good with this
definition." A **fault** is the part not working. A **trip** is the system placing its pause because of it ("A trip is
when the PAUSE has been placed"). The board's word stays **Trip**, and what it pauses reads "Paused by the system (a
trip): …".

**Note (Jason, 2026-11-20, in the decision pop-up on release 1): an escalated trip closes as Recovered.** Asked whether a
trip that has escalated (its tries ran out) and whose check later passes should close itself, he chose "Close as
Recovered". It closes itself as Recovered, its pause lifts, and the Recovered notice goes to the Founders. Nothing is
left to press. This settles §3's "it waits for a person only where 0171's escalation already says so": once its check
passes, nothing waits (`ControlAlarms::ESCALATED_RECOVERS`).

**Note (Jason, 2026-11-20, in the decision pop-up): the Census geocoder's backup.** Asked "The Census geocoder resets the
site's connections (Bluehost's network). Fall back to the crawler's own geocode on Railway when that happens?", he
answered "Yes". So the Census geocoder (U.S. Census Bureau) names its backup, per §3 ("The workflow … reroutes to a
backup service where one is named"):
- When the site's call to geocoding.geo.census.gov fails on the connection (reset or no answer), the batch is sent from
  Railway instead.
- **Correction (the Control Board session, 2026-11-20, checked in the code after his answer):** the crawler's geocode
  task doesn't call the Census itself today. It asks the site to (`/api/crawl/geocode.php` → `Geocoder.php`), so it
  shares the site's connection. A backup on Railway means the worker sends the batch to the Census itself, then hands
  the answers to the site to place.
- A map point is never lost or invented in between: a batch that neither side could send waits, as it does now.
- **Re-asked with the corrected picture (Jason, 2026-11-20, in the decision pop-up):** "There's no Railway path today. A
  backup means the crawler calls the Census itself and hands the answers to the site: a new worker call plus a site
  endpoint." He answered "Yes, build it".

**Note (Jason, 2026-11-20, relayed by the session manager): one control board in the menu, folded into release 2.** On
a screenshot of the System Configuration menu: "arent we combining things into one control boards, if so why does the
drop down look like we are segmenting everything?" On folding it into release 2: "yes. fold into 2". The operations part
of the menu becomes three entries:
- **System Control Board:**
  - its tabs Alarms, Logbook, Ledger, Upkeep and Reports (separate menu items today);
  - Scheduled tasks as a tab (jobs are parts it watches);
  - Automation's master switch, Start the system and Pause everything as its Start-up and Shut-down stages (the ignition,
    §6).
- **Modules** (the Service register): each card holds its stage-2 pre-flight and its caps, budget and cost (the
  Pre-flight and Costs pages today). The system pre-flight goes into the board's Start-up stage.
- **Workflows:** the Workflows and Workflow board pages merge. Each workflow's page has its steps, services, backups and
  task switches.
- **Permissions and Data separation stay** as configuration.

The rules hold:
- every old address redirects to its new tab, card or anchor, landing on the same spot;
- old manifest sections are archived in `archive.md`, not deleted;
- every behaviour each page had is listed in the hand-off with where it moved, and a drop needs `--drops` and his OK;
- the zip check and the drawer and anchor tests cover the redirects.

**Note (Jason, 2026-11-20, in the decision pop-up): when an hour on a system's strip turns red.** No decision set it
(release 2a's item 11, `handoffs/control-board-2026-11-20.md`). Asked "When should one of those hours turn red?", he
chose "Critical part down": an hour turns **red only when a critical part is down** (its duty: search, sign-in,
payments, page load, the database; 0171 §5's note, Thresholds & gates → Critical parts). Any other part held or red
that hour makes it **amber, with the cause named** ("1 of 20 held: Common Crawl"). Built in release 2a.

**Note (the Operations session, 2026-11-20, reviewed by the session manager): §6's homes after "one control board in the
menu".** §6 says each switch keeps one home, "the card, or Automation for the master and workflows", and that Start the
system, Pause everything and Shut the system down stay "on Automation". The note "one control board in the menu" (Jason:
"yes. fold into 2") moves Automation's master switch, Start and Pause everything into the board's Start-up and Shut-down
stages, and the menu keeps three entries. So from release 2b, the master's and those three presses' home is the board's
Start-up and Shut-down stages, and Automation's address leads there. Until 2b, release 2a puts the live "paused now"
line on both the board and Automation; 2b's list of moved behaviours carries Automation's line into Start-up. Nothing new:
this records what the two notes already say together.

**Note (2026-11-20): release 2a is sent in two parts**, as its scope allows (`handoffs/control-board-2026-11-20.md`):
part 1, the side panel (an alarm's first button opens its component, routed by the component: a service's card, a
pre-flight line, a workflow's or task's own page, a site job on Scheduled tasks; one panel at a time with Back), the
Routes line's words, and a trip's words ("closes as Recovered", ESCALATED_RECOVERS); part 2, the lines and words (items
3–8, 10–11). 0176 release 3 routes a cause to its exact spot inside the component (the session manager, 2026-11-20).

**Note (2026-11-20): release 2a built.** Part 1 live as #229 (`0175-r2a-panel.zip`). Part 2 (`0175-r2a-lines.zip`,
PR #1186, built on Sync 16:01 UTC):
- "Paused now" is the top line of the board and of Automation, and the pre-flight's result sits in the Start-up stage.
- An active module reads "Diagnostics".
- A workflow whose failing task a backup covers reads "On a backup".
- A warning shows "!" and has no Acknowledge.
- A system's hour turns red only for a critical part. A process's own hour is amber while it is held: a words fix,
  since green while held was untrue (playbook principle 4).
- Each task's switch is on its workflow's page.
- The card's in-place Switch on (#216) stays beside the one home of §6. Jason, 2026-11-20, in the decision pop-up: "Keep
  both". It presses the same switch, and the card also links to it.

**Note (Jason, 2026-11-20, in the decision pop-up): where Costs lives.** Asked where Costs' figures that aren't one
service's go (the month against revenue, the community budget, the AI cap's shares, a hub's opening budget), he chose
"Top of Modules": the whole Costs page is a fold at the top of Modules (Service register), and in release 2b part C By
workflow also shows on Workflows. Automation's Services table stays in Start-up (asked in the same pop-up, not removed).

## Note (Jason, 2026-11-20): "Paused now" gives a clear picture

On a screenshot of the board's "Paused now" (12 paused or out: 2 trips, 7 marked "paused by a person (Shut down from the
Control Board by the Control Board (a trip))", 3 "paused by Release A"): "this makes me feel like the system is not
operating correctly. Still struggling to get a clear picture of how well the system is performing." He chose all three of
the session manager's proposals:
1. **Words true:** a pause the board's shutdown made after a trip is never "paused by a person"; it names its trip.
2. **Three groups:** **Needs attention** (each trip, with its fix); **Waiting on those** (what a trip's shutdown paused,
   folded under its trip); **Off on purpose** (switched off by a release or a person, with what each waits for, folded
   away).
3. **One health line above it, in plain words**, e.g. "Site, search, sign-in and payments: working. Data gathering: 2
   sources down (Overture bulk, Common Crawl), 9 parts waiting on them. 3 off on purpose." The critical duties first
   (0171's), then each system's count.
Built by the Operations session, before part C.

**Built (Operations session, 2026-11-20, release 0175-paused-now, built on Sync 18:34 UTC):** the cause of "paused by a
person": before 0175 a trip shut its part down through the board (`ControlBoard::stopOne` with who = "the Control Board (a
trip)"), writing into the switches; `putBackTripSwitches` (§7) puts back only switches a person had set before, so the rest
stayed off, and "Paused now" read any switched-off task or workflow as a person's. Now a switch carrying that mark is the
trip's: under its open trip, or, when its trip has closed, listed under Needs attention as left off, naming the trip. Paused
now is in the three groups; the health line names the duties of 0171 §5's register (sign-in and email named out of
"System-critical procedures", its other parts as "Site jobs"), then Data gathering, Workers, Outside services and the
site's own parts, each part said once; red only for a critical duty down (Jason, 2026-11-20: "Critical part down"). Tests:
`tools/control-board-tests/paused-now.php` (20), `lines.php` updated.

**Answered (Jason, 2026-11-20, in the decision pop-up, reviewed by the session manager; PR #1219):** Approve. On switches a
past trip left off with no person's setting to go back to: **"Keep for a person"**. They stay off, listed under Needs
attention as left off by a trip that has closed, each with its switch; nothing switches them back on by itself.
