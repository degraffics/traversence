# 0175: One state for a service, from selection to removal: switches belong to people, holds to the board

Status: **draft** (the Control Board session, 2026-11-20, at Jason's request relayed by the session manager). Nothing is
built until Jason approves it in the decision pop-up.

Builds on:
- 0173: one lifecycle for every outside service, with the Service register as its one home (§1) and its stages (§2).
- 0171: the System Control Board, its trips (§7 and its notes) and maintenance (release 4a).
- 0163 §9: the switches (master, workflows, tasks, services); §10: switching back on through the check.

Amends (each marked **Note for Jason** where it happens):
- 0163 §9: a crawler task's switch moves from Automation to the service it serves (§3).
- 0171 §7's note of 2026-11-18 ("a trip waits for an admin's Resolve"): a trip clears itself when its check passes,
  except the three kinds only a person can fix and the third trip of a cause in a day (§4), per his rule of 2026-11-19.
- 0171 §7: a trip no longer switches anything off; it holds (§4).

Checked against the live mirror at "Sync: 2026-11-20 04:00 UTC".

## What Jason asked for

Jason, 2026-11-20, with screenshots of Automation, the Service register and the System Control Board:

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

And on 2026-11-19: "If this was resolved by system or marked as resolved by admin it should be handled." That is the
rule §4 applies; it isn't asked again.

## What exists (checked in the live code)

His screenshots show Geofabrik in four states, in three places, at once:

| Where | What it said |
|---|---|
| Service register and Automation, the service | **On** |
| Automation, task `geofabrik_bulk` | **Off**: "Shut down from the Control Board by the Control Board (a trip)" |
| The board, its alarm | **Trip**: "its check passes now; it waits for an admin's Resolve" |
| The board, its reading | **Not ready**: "off until Start the system … W1: On a backup" |

The "read a source" and Common Crawl tasks were also switched off "by the Control Board (a trip)".

Why the pages disagree:
- **A trip uses a person's switch.** `ControlAlarms::stop()` calls `ControlBoard::stopOne()`, which calls
  `Automation::setTask(…, false, 'Shut down from the Control Board by the Control Board (a trip)')`. That's the same
  switch a person presses on Automation. So Automation shows "Off" as if someone chose it. A fault looks like a choice.
  When a person later switches the task on, the trip's record still says it holds it. The two disagree.
- **A passing check doesn't end a trip.** `ControlAlarms::clear()` on a trip only records `good_at` ("its check passes
  now") and waits for **Check and start again** (0171 §7's note, 2026-11-18). Jason's rule of 2026-11-19 says the
  system handles what it can fix.
- **Five places set a task's switch:**
  - the Automation page's Tasks table;
  - Insights → Crawler's on and off;
  - Start the system (`SystemStart`);
  - the board's Shut down (`ControlBoard::stopOne` and `shutDown`);
  - the trip (`ControlAlarms::stop`).
- **Each service's tasks are already listed in one place:** `OutsideServices::CONTROLS[svc]['tasks']`, for example
  Geofabrik → `geofabrik_bulk`. Since release #216, a service's card shows them, with **Switch on**.

## Decision

### 1. One state for a service: Jason's eight stages, on its card

Each service shows one stage. It's the same on its card, its row in the register, its row on the board, and wherever
else it's named. Jason's eight stages, with 0173 §2's stages folded into them:

| # | Jason's stage | What it means here | Where it's done | Its way back |
|---|---|---|---|---|
| 1 | **Selection and configuration** | Proposed (named, not built), Added (a release brought it in, off), Key in (0173 §2–3) | **Propose**, the release, **Add its key** on the card | **Drop it**, Withdraw, **Put the old one back** |
| 2 | **Integrated (pre-flight)** | Its own pre-flight line passes: key, address, terms, test call | **Check** on the card (the pre-flight's line for that service) | A red line says why, with its fix |
| 3 | **Activation: switched on** | A person activated it, through the check (0163 §10) | **Activate** on the card or the board | **Pause** (stage 7) |
| 4 | **Assigned to workflows** | The crawler tasks and site jobs it serves are switched on, each named with its workflow (§3) | **Switch on** per task, on the card | **Switch off** per task, with why |
| 5 | **Operational** | Running. The board shows real-time monitoring, performance, diagnostics and reports (0171) | The board, its own page, the logbook, reports | — |
| 6 | **Maintenance** | Taken out on purpose, in a window (0171 release 4a). Its alarms are notices and Start skips it | **Take out** on the board, or a window set ahead | **Put back**, after its check |
| 7 | **Deactivation** | Paused by a person: nothing calls it, its key stays (0173's Paused) | **Pause**, with why | **Activate**, through the check |
| 8 | **Removal** | Retired (record kept, code idle) or Removed (code and key gone). Its history, data and records are kept | **Retire it…**; Removed comes in a release | **Bring it back** (Retired) |

- **The card shows the eight as a strip.** Each stage reached gets a ✓. The stage it's in is lit. A step not reached says
  what's missing, with its button. For example: "2 Integrated: its key isn't in. Add its key".
- **A hold is shown with the stage, never in place of it** (§4): "5 Operational · held: download.geofabrik.de failed 6
  times (a trip). It clears when its check passes."
- **What's being said** stays in `service_stages`: who, when and why for stages 1, 3, 6, 7 and 8. 0173 §2's table and
  its buttons are kept; only the names and the order change to Jason's.

### 2. Two kinds of state, each with one owner

- **Switches are a person's** (or Start the system, pressed by a person): the master, each workflow, each service, and
  each task's assignment. Only a person's press, or what a person pressed on, changes a switch. The board never changes
  one on its own.
- **Holds are the board's:**
  - a trip;
  - a back-off;
  - not ready (Start the system hasn't reached it yet);
  - out for maintenance;
  - out of budget.

  A hold is kept in its own record, with its reason and the check that clears it. **Work runs only when its switches
  are on and nothing holds it.** That's the rule `Automation::gathering()` and `OpenLoads::off()` already apply. The
  difference is that holds are added beside the switches, never written into them.
- **Every page says both, the same way:** "On · held by its trip (download.geofabrik.de)", "Off: switched off by Jason,
  Nov 20, 9:10 AM MST, 'testing'", "On · not ready: Start the system reaches it after W1".
- **Shut down on the board is a person's press,** so it still sets switches, logged with who. **Shut down the whole
  system** does too. Only the trip changes: it holds instead.

### 3. Automation keeps the system's own switches; a task's switch goes with its service

- **Automation** (https://traversence.com/admin/config/automation) keeps:
  - the master switch;
  - Start the system;
  - Pause everything;
  - each workflow's switch;
  - the switch log.
- **A task's switch moves to the service it serves (Note for Jason: 0163 §9 amended).** The card's **Assigned to
  workflows** lists each task with its workflow, its state (§2's words), **Switch on** and **Switch off**, as Geofabrik's
  card has done since #216.
- **A task serving more than one service** shows on each of their cards. It's the same switch, so it reads the same
  everywhere.
- **Tasks with no outside service** don't call another company: a business's own website (W1's own reads), the site's
  own jobs. Their switch goes on their workflow's own page on the board
  (https://traversence.com/admin/config/control/wf-W1).
- **Nothing is lost** (playbook: "replacing a screen keeps everything it did"):
  - Automation's Tasks table becomes one line per workflow: "W1: 6 tasks, 5 on. Open W1". It links to the workflow's
    page, which lists every task with its switch.
  - The switch log keeps every task change.
  - Insights → Crawler keeps its view of runs. Its on and off become links to the same switch.

### 4. The board watches and holds, and never switches anything off

- **A trip holds.** It holds only what its cause touches, as narrowly as today (0171 §7):
  - a service's calls;
  - a task;
  - a workflow;
  - all gathering, when the crawler itself is down.

  What it held is kept in the trip's record, and Pulse gets its item. The Founder is emailed at once only for a trip that
  waits for a person (the three kinds below, and the third trip of a cause in 24 hours).
- **It clears by itself when its check passes.** Jason's rule (2026-11-19): "If this was resolved by system or marked
  as resolved by admin it should be handled." This replaces 0171 §7's note of 2026-11-18. It applies to every kind,
  "not ready" included. What it held runs again, and the logbook says so. Pulse's item closes with "The system fixed it:
  its check passed at 09:42 UTC", and a **Recovered** row at notice level is added to Pulse. There's no push and no email
  (Pulse stays trips only: only a trip that waits for a person reaches him at once).
- **Three kinds wait for a person,** because only a person can fix them:
  - a **refusal** (a block, like 406, or a site saying "don't");
  - a **refused key** (401 or 403 on our key);
  - a **used-up quota or budget**.

  For these, the card and the alarm say what a person does, with its button. Once it's done, the next passing check
  clears it. These are the same three kinds 0171's back-off already treats this way. This extends that rule to trips.
- **Check and start again stays,** for a person who doesn't want to wait for the next check. It runs the check at once.

### 5. Today's tasks switched off by a trip go back to what a person set

One database update. It's safe to run twice and needs no person:
- **What it finds:** each task whose switch was last set by "the Control Board (a trip)". Today that's
  `geofabrik_bulk`, the "read a source" tasks and Common Crawl's tasks, plus any workflow paused the same way.
- **What it does:** each one's switch goes back to the last value a person set in `automation_log`. Where no person ever
  set it, it goes back to on if Start the system had switched it on, else off. Its open trip becomes a hold (§4).
- **Kept:** the trip's history, the log lines and who did what. Each change is written to the log as "Put back to what
  Jason set on …".
- `release.json` carries `"data_reason"` (it's an `UPDATE`).

### 6. What doesn't change

- 0173's register, keys, single definition, Retire and Removed; 0171's board, alarms, logbook, ledger, reports and
  maintenance; Start the system's order and gates.
- Pulse is still for trips only. A hold that clears itself closes its Pulse item.
- No data is ever deleted. "Removal" keeps the history (Jason's point 8).

## Build order (each a release Jason reviews)

1. **Holds beside switches**:
   - a trip holds instead of switching off (§2, §4);
   - a trip clears itself when its check passes, except the three kinds;
   - today's tasks switched off by a trip are put back (§5);
   - each page's words say both ("On · held by …").

   This ends the disagreement Jason saw.
2. **The task's switch goes with its service** (§3):
   - the card's **Assigned to workflows**;
   - tasks with no outside service on their workflow's page;
   - Automation's Tasks table becomes a line per workflow;
   - Insights → Crawler links to the switch.
3. **The eight stages on the card** (§1):
   - the strip;
   - the register's Stage column and filter in Jason's words;
   - the board's row for a service shows the same stage.

## Consequences

- **One answer to "is it on?"** A switch is someone's choice. A hold is the board's, with its reason and the check that
  ends it.
- **Fewer presses.** A trip whose check passes needs no one. A person is called only for what only a person can fix.
- **One place per service.** Automation is the system's own switchboard. A service's card holds its whole life, from
  proposal to removal.
- **Risk:** a trip that clears itself and trips again, over and over. Mitigation: the third trip of the same cause in 24
  hours waits for a person. That's the "repeated" count the board already keeps (0171 release 3a), with the logbook
  showing each one.

## Questions for Jason

1. Approve this decision, with its three releases in that order?
   This includes one guard that's new: the third trip of the same cause in 24 hours waits for a person. (A trip whose
   check passes clears itself by Jason's rule of 2026-11-19, so that isn't asked again.)
2. A crawler task's switch:
   - **on the card of the service it serves** (recommended), and on its workflow's page when it serves none. Automation
     keeps the master and the workflows;
   - or it stays on Automation's Tasks table, with the card's button as a second way in.
