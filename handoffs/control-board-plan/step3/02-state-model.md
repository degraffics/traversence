# The control board: step 3, the core specification (2026-11-21)

**What this is:** the one state model, the full state-transition table and the one engine that applies them. It's a
design document. No code is written or changed. It is step 3 of `handoffs/control-board-plan.md`. Jason approved the
frame (`module-harmony-report.pdf`) with three corrections: his eight stages, no SMS, and no webhooks.

**Built from:**
- Jason's end-to-end standard (CLAUDE.md, "End to end, verified, before anything ships", 2026-11-21: points 1–4);
- `step1-requirements.md`, whose §1 is the model (1.1 what a trip is, 1.2 a service in its workflows, 1.3 the two
  clocks) with R1–R189 and Q1–Q22;
- `manifests-railway-github.md`;
- step 2's audit (`step2/README.md`, `g1`–`g4`, `live-snapshot.md`, `ir-0001.md`), with its seven root causes;
- `step3/01-lift-reconstruction.md`, Jason's lift of W3;
- decisions 0171, 0173, 0175 and 0176 with their notes.

Live code was checked on the mirror at `37a212e` and `aeb5f31`.

**How to read it:**
- "today:" marks what the live code does, from the audit.
- "R#" is step 1's requirement and "Q#" its question.
- "g1 F2" is a finding of audit file g1, and "g3 J5" a row of an audit file's failure map. "L4" is a live snapshot row.
- "Lift (a)–(f)" is a section of 01-lift-reconstruction.

Code names appear only in the source and test columns.

---

## 0. Closed trips and switches: the rule, stated once

Jason (2026-11-21): "Make sure the transition table defines what happens when a trip is lifted/closed: specifically
whether dependent switches stay off, revert to prior operator intent ('Keep for a person'), or require an explicit
toggle, so 'off on purpose' never shows up for a system-initiated switch-off." Every table row that touches a switch
refers to this section as **CS**.

**CS-1. The system never writes a switch again.**
- A fault places a hold, its own record. A switch is a person's choice (P5).
- Sources: 0175 L63-64, §3 "It writes nothing into anyone's switch".
- Today, two code paths still write switches:
  - the pre-0175 trip's `ControlBoard::stopOne` (`Automation::setTask … "the Control Board (a trip)"`);
  - Start's watch, `SystemStart::pauseItem`, which sets `crawl_workflows.paused = 1` credited to the person who
    pressed Start (g3 C8, E3).
- Both become holds (transitions 7 and 73). So after this build, the only switches the system turned off are the ones
  left over from before.

**CS-2. The state's name is exactly "Off by the system (left by a closed trip)".**
- The engine reads `automation_log`. A switch whose last writer was the board's trip, or Start's watch, is in this
  state once its trip has closed.
- While its trip is still open, the same switch reads "Off by the system (for its open trip T-####)".
- **"Off on purpose" is never used for a system action.** The state is never a person's Off and never "paused by a
  person" (R105; 0175's note "Paused now gives a clear picture").
- Its light is amber with the pause mark. It is listed **open, never folded,** under Paused now → Needs attention.

**CS-3. The one exit rule when its trip closes: Keep for a person.** This is the model's one choice, from two sources:
- 0175 §7 ("each switch goes back to the last value a person set in `automation_log`");
- the note of 2026-11-20, Jason's standing answer for switches no person set: **"Keep for a person": they stay off …
  each with its switch; nothing switches them back on by itself.**

Applied, there are three cases, decided in this one place:

| The last value a person set | What happens | Guard | Words (the switch's row, Paused now) |
|---|---|---|---|
| **On** | It goes back on. | **Only once that part's switch-on check passes** (P4, rule NG below). Until then it stays "Off by the system (left by a closed trip)", with Switch on greyed and the reason. Logged: "Put back to what <name> set on <date>". | Pass: "On · Working: put back to what <name> set on <date>; its check passed: <what was checked>." Check red: "Off by the system (left by a closed trip): it goes back on when its check passes (<name> set it on, <date>). Its check is red: <reason>." |
| **Off** | It becomes that person's Off. A person did choose it, so it moves to their Off, with their words. | none: nothing becomes active | "Off: <name> switched it off on <date> (<their why>)." |
| **None** (no person ever set it) | **It stays off until a person's explicit toggle.** Nothing switches it on by itself. | the toggle runs the switch-on check (transition 9) | "Off by the system (left by a closed trip): left off by <trip title> (T-####, closed <date>). No one has switched it on or off by hand, so it waits for you. [Switch on]" |

Whether **Start the system's** setting counts as a person's is open item O4: 0175 §7 named it separately, the note
doesn't say, and today's code counts it as a person's.

**CS-4. The toggle sits on the row where the issue is reported.** It is never in another section or inside a
collapsed group (standard 3; Jason, 2026-11-21). One switch (R119) is offered on three open rows:
1. the switch's own row: the task's line on its workflow page, https://traversence.com/admin/config/control/wf-W3#cb-steps;
2. the closed trip's row and thread, as a **Still off** line;
3. its own line in Paused now → Needs attention.

All three press the same switch. A group row that contains a trip shows each member's Still off line, with its toggle,
when the group is opened. The group's header carries the count: "2 switches still off".

**CS-5. The closing message names them, the same on every channel.** When a trip closes and a switch it left stays off,
every closing state carries the **Still off** line:
- **Board:** "Still off: Read a source (W3), Common Crawl (W3): left off by this trip before trips paused instead. No one
  has switched them by hand, so they wait for you. [Switch on] [Switch on]".
- **Pulse:** the row's last note, with the same words and buttons.
- **Email:** the closing reply, with the same words and a link to https://traversence.com/admin/config/control/wf-W3#cb-steps.
- **Push:** the push already shown is replaced silently under its tag: "Recovered. 2 switches still off: open to
  switch on." No new alert (R132).
- **A press's own notice** never says "Started again: <workflow>" while a switch left off keeps its tasks from running
  (Lift (c), (d)).

**CS-6. What it replaces (today's words, observed):**
- **"nobody had set this switch before, so it stays off"**: a fixed text for every such switch (`ControlBoard.php:214,
  357`), never read from the log. It's also shown for a switch a person last set *off* (Lift (c), fault 1).
- **"Started again: W3"** in the press's notice (Lift (a), (d)).
- The threads left reading **"Paused by the system"** after the close (Lift (e)).

The designed words are CS-3's and CS-5's, read from the log each time they're drawn (P9). The rows are 7·now and 7,
8·now and 8, and 10·now and 10 in §4. The paired wording is in §4.8.

**NG: no unguarded trip-close (Jason, 2026-11-21).** Closing a trip never moves a switch, a service or any part to
active, Working or Recovered unless that transition's guard passes. Trips may close without a check in two cases only:
**Retired** (the part was deleted) and a person's **Undo**, which reopens. Neither makes anything active.

**BL: billing, quota and credit limits (Jason, 2026-11-21: "include a direct Open billing link to the provider's
billing URL or login (stored on the service/register record) right on the card alongside any board-level cap/pause
action, keep internal board actions separate from external billing changes, and ensure the displayed percentage
matches the actual spend/cap numbers").**

The percentage follows one rule:
- It is computed from the same numbers shown, in the title and the body alike: round(spend ÷ cap × 100). For example,
  "Claude (AI): $27.06 of $40.00 (68%)".
- The band (50, 75, 95) decides the level, never the number shown.
- Today, observed: the title "Anthropic (Claude) at 50%" uses the band reached, while the body says 67% (L5, L6;
  transition 39·now).

**PL: the provider's page that matches the fix (Jason, 2026-11-21: "give each service/issue type the URL that actually
matches what you're fixing").** It replaces a single billing link.

- **Two kinds of action, always apart:**
  - **Board actions** act on our system, each with its guard: Check now, Check and start again, Retry, Pause or Resume
    our calls, Acknowledge, and raising or lowering our cap. A board action never claims to change anything at the
    provider ("raised our cap for Tavily", never "raised at Tavily").
  - **Portal actions** open the provider's page and change nothing on the board: "Open <Provider> billing ↗",
    "Open <Provider> API keys ↗", "Open <Provider> console ↗", "Open <Provider> sign-in ↗".
- **Each portal action is followed by the board action that verifies it,** shown next to it in order:
  - after Open billing: Check now (a quota or credit trip), or Check and start again (a trip);
  - after Open API keys: Replace key, then its Check;
  - after Open console: Check now.
  Only the board action's passing check changes state (P4, NG).
- **Routing by fault kind,** from the manifest's `portal_links` (§8):

  | Fault kind | Portal link type |
  |---|---|
  | quota, budget, credit | `billing` (billing, spend limits, top-up) |
  | key, auth | `keys` (the API keys page) |
  | ban, rate, server, timeout, malformed, an endpoint, a host | `console` (the dashboard or status) |
  | anything else | `login` (the sign-in) |

- **When that type has no URL,** the card says so in words: "No <billing / keys / console / sign-in> page registered
  for <Provider> yet." It **never falls back to another type's page**, and **never silently omits the link**.
- **On the card,** where Fix it lands for that fault, both kinds sit on the one card, in two labelled rows: "On our
  system" (board actions) and "At <Provider>" (portal actions).
- **Today, observed and simulated:**
  - one generic map by cause (`ServiceHealth::fixOf`, `Thread::FIX`, g2 §2 `fix_it_routes`) with no provider pages;
  - Fix it for a provider's quota lands on **our own** cap (Gemini's 429 → `#svc-gemini-caps`, which "doesn't lift
    Google's hold", g2 row 6);
  - a refused Railway key lands on a card that can't check it (g2 §2).
- Transitions 128–135 (group G) route each kind. Coverage is in §8.

---

## 0a. Jason's rulings and answers (2026-11-21): these govern wherever a row below differs

**Rulings:**
1. **Toggles in folded groups.**
   - A folded group's header keeps the count ("2 switches still off").
   - Any group holding a switch still off opens by itself.
   - **Switch on** stays visible on the task's own row, on the closed trip's row, and in Paused now → Needs attention
     (CS-4). A toggle is never hidden inside a closed group.
2. **Retry spacing.** The tries at about 5, 15 and 35 minutes are confirmed, inside the 60-minute "Keep the hour". They
   are marked **des**: they adopt the existing back-off spacing, not an earlier rule of Jason's.
3. **A part with no separate check (its next run is the check), and O2.**
   - The engine never places a hold that blocks a part's own next run (P7).
   - If that next run fails again, the engine raises a **Trip** that says plainly it can't be held without blocking its
     next run, and the next scheduled run still goes ahead.
4. **Start the system and "Keep for a person" (O4).** Start the system's setting does **not** count as a person's. Only an
   explicit action by a person on the switch counts. Otherwise the "None" rule applies: the switch stays **Off by the
   system (left by a closed trip)** until a person switches it on.

5. **Words while something is held** (Jason, 2026-11-21, on GitHub and Bluehost). Words follow the state (P9). Once a
   hold is placed, the status is "Paused by the system (<what's held>): still trying, n of 3, next at <time>". "Down" is used
   only when a host itself is gone and nothing could place a hold. Rows 53–55 and 57 read this way.
6. **When Bluehost is down, the board can't load** (Jason, 2026-11-21).
   - The outside heartbeat (GitHub Actions, every 15–30 min, O3) sends **email only**, through the site's existing
     Amazon email (SES), with a GitHub secret limited to sending. Jason confirmed SES's fraction-of-a-cent cost as the
     system-email exception (2026-11-09).
   - The push keys stay on Bluehost, so there's **no push** while the host is gone.
   - The heartbeat writes the incident as an unnumbered record in the repo: times, what failed, its checks.
   - When the site is back, it imports that record and assigns the T- and IR- numbers then. Pulse shows it as "Raised
     while the site was down, by the outside heartbeat", with the real times.
   - Recovered then closes it on every channel.
7. **Bluehost's cPanel** (Jason, 2026-11-21). Console keeps `CPANEL_HOST` (Site settings). The documented my.bluehost.com
   cPanel link folds into Sign in · Account, never shown twice.

**Answers:**
- **Q19:**
  - Alarm and logbook history are kept **90 days**; trip and Incident Report records **365 days** where practical.
  - `control_cost_factor` is **2.0×** the rolling 30-day average.
- **O1:** a **15-minute** correlation window, matching host, signature and time.
- **O3:** the outside GitHub probe runs every **15–30 minutes**, a light check, comfortably inside GitHub's free minutes.
- **O5:** provider pages come from what is already documented, and from Jason's Vault for the priority modules.
  - They are matched strictly by type (billing, keys, console, login). A URL is never invented, and never borrowed from
    another type.
  - Where a type has no registered URL, the card keeps "No <billing / keys / console / sign-in> page registered for
    <Provider> yet".
  - **Note:** the Vault's items are sealed in Jason's browser (decisions/0156; `vault_items` holds only a sealed blob and
    a hash of the site). Neither the site nor a session can read a Vault URL. The priority modules' URLs come from Jason,
    copied from his unlocked Vault into the list below. Until then the "not registered" wording stands.

## 1. Principles

Each is a rule the engine enforces. A rule the engine can't enforce isn't a principle here.

| # | The rule | Source |
|---|---|---|
| P1 | **One state model, one engine.** Every caller reports evidence. Only the engine decides a part's state, its hold, its alarm, its words and who is told. | Standard 1 ("no … recovery rules split across files"); PLAN step 3 ("One engine … The five separate versions are removed"); audit root causes 1–7 |
| P2 | **A fault belongs to the component that failed, and the engine classifies it.** A raiser's own label is never trusted: a timeout reading *our* reply is our endpoint's fault, never the task's or the outside source's. | Model 1.2 ("1. component 2. workflow are checked"); Q3; g1 F2, F7; g4 L12 (the worker's kind trusted) |
| P3 | **Tries are the engine's checks of the failed component, on the diagnostic clock.** A scheduled run is never a try, and a burst inside one run is one fault. | Model 1.3; "Keep the hour" (0171 L712-717); root cause 1 |
| P4 | **No try counts, no trip closes, and no switch goes back on without a real check.** A real check calls the failed component as the failure did and names what it called ("Common Crawl's index answered a real query"). Stored results and a static file never count. | Standard 2; R82, R93; Lift (a); g2 F1 |
| P5 | **A fault pauses, never switches off.** A hold is its own record; the system never writes a person's switch (§0, CS-1). | 0175 L63-64 ("Holds vs OFF … hence PAUSED"), §3; R86; g3 C8 |
| P6 | **A trip holds only what its cause touches.** The Incident Radius is computed from the manifest: each task reroutes to its next working source, or waits. A workflow waits whole only when every task waits; all gathering only when its host or worker is down. | 0175 §3 and the note of 2026-11-21 ("reroute to a back up service or trip"); R87, R88; root cause 2 |
| P7 | **A hold never blocks its own way out.** The check that lifts a hold must be able to run while the hold stands. | g1 F2 (endpoint deadlock), g1 F1 / g3 J2 (a paused job's skipped run read as good), g2 row 22 (a trip's hold refused its own check) |
| P8 | **Critical is decided by the part's duty, never by the alarm's kind or the master switch.** A critical part trips at once while the system keeps trying. | 0171 L311-320 ("Critical is that our site can perform its duties…"); R72; g3 D2, C3; root cause 7 |
| P9 | **Words are generated from state, never written by a raiser.** One title per thread on every surface, read again each time it's drawn. A number shown (a percentage, a count, a time) is computed from the same readings in every place it appears (BL). | 0176 §2 (the thread's one shape); "Words follow the state" (0171 L305); standard 3; root cause 5 |
| P10 | **One thread per alarm or incident, and every state change updates it on every channel.** That includes a person's close and Recovered. | 0176 §1, §4; R129, R131; g4 F3; Lift (e); root cause 6 |
| P11 | **Only a trip reaches a person, and a trip always reaches someone.** If it can't, the dispatcher's own fault is raised and shown. | 0171 routes note L427-435 ("Everything else should be cleared by the system so why send and pulse?"); 0176 §4; g4 F4 |
| P12 | **Every trip has an Incident Report with its warning ids and trip id.** It opens at the trip and is linked wherever the trip is named. | 0175 note 2026-11-21 ("Warning id and trip id with explanations that I can reference"; "Trips only"); R173, R174 |

---

## 2. The objects

### 2.1 Module

A plug-in registered by one manifest (§8).

| Field | Values | Source |
|---|---|---|
| **Role** | **Host** (runs our code: Bluehost, Railway, GitHub) · **Source** (supplies data: Overture, Foursquare, Geofabrik, NPI, IRS, Tavily, Brave, Common Crawl, Census, Wikidata…) · **Service** (does a platform job: Payments, the video worker, the Communications Dispatcher, Gemini, Claude, SES) | Report §3 ("Role determines how faults behave: a Host fault holds what runs on it, while a Source fault reroutes along the source priority map") |
| **Criticality** | A duty, or none: **page**, **search**, **payments**, **database**, **procedures** (system-critical procedures: sign-in, system email, releases, the watch, billing jobs) | 0171 L311-318; R29 |
| **Stage** | Jason's eight (§3a) | 0175 §1 L33-54 |
| **Level** | standalone or integration | 0175 L57-59, §4 |
| **Repair** | **fix while running** (the headlight) or **needs shut down** (the battery) | 0175 §3 L173-178; R95 |

Today there is no role anywhere, criticality lives only in `ControlRegister::CRITICAL`, and the stages are 0173's seven
(g2 §2; g4 (d)).

### 2.2 Part

The unit the board shows: one row, one light, one state. Each part has a parent, which gives the tree the board draws
(R4, R8).

| Kind | Example | Its parent | Its check (§2.7) |
|---|---|---|---|
| Module | Common Crawl, Railway, Bluehost | its system | its servers' and keys' checks, rolled up |
| Server of a module | Common Crawl's index (`index.commoncrawl.org`), its data host (`data.commoncrawl.org`) | its module | its declared probe |
| Key | `TAVILY_API_KEY` on Railway | its module | one free authenticated call, or the worker's key test |
| Workflow | W3 Sources and the place store | the Knowledge Engine | roll-up only: never a fault of its own |
| Task | `commoncrawl` (W3), `overture_bulk` (W1) | its workflow | its needs' checks; its own next run for a fault in its own code |
| Endpoint of ours | `/api/crawl/loads` | Bluehost (Site) | an authenticated no-work request, timed against its speed gate |
| Job | `billing_tick`, `listings_tick` | Bluehost (Site → Scheduler) | its declared check, else its next run |
| Worker | the crawler, W0 Indexer, the video worker | Railway | a recent run or heartbeat that reported |
| Critical duty | page load, search, sign-in, payments, the databases | the Site | its sensor (the outside heartbeat, the 5-minute tests, each realm's query) |
| Channel | Pulse, email, push | the Communications Dispatcher | each channel's readiness (§7) |

**Rules:**
- A workflow's own pre-flight line is a **roll-up**, never a fault. Today, `pf:wf:W3` tripped W3 whole on a line that
  had become amber (Lift (a); g1 row 20).
- A job tied to a workflow is a part with its own row, not a raw id. Today its alarm reads `job:identity_recheck_tick`,
  with no row (g3 R27, J5).

### 2.3 Need

What a task needs ("places by kind and town", "sources", "prose"), with its sources in priority order: our own copy
first, then outside services, free before paid. The first one that works wins (0163's source map; R61; R12).
- A need is answered while any of its sources' checks pass. It is "On a backup" when the first one doesn't.
- A need with no working source holds **the tasks that need it, in every workflow it names**. Today it holds only the
  first workflow named, whole (g1 row 18, F8).
- A paid backup is used only under a director's override with its own cap (R61, R97).

### 2.4 Hold

A pause the system places. It is its own record and **never a switch** (P5).

| Field | What it holds |
|---|---|
| scope | the parts held: a server's calls (by caller: the site, the crawler, or both), a key's calls, tasks, a workflow, all gathering, all a host runs, releases, the sync |
| cause | the fault that placed it, and its trip id T-#### |
| why | words generated from the cause (P9) |
| lifted by | the check that lifts it: the cause's own check (§2.7), or a person's act on the cause followed by that check |
| placed · lifted | when, and by which check, with its result |

Holds of one cause never stack. Today W1 carried two `system_pauses wf:W1` rows for two task failures, and lifting one
claimed "Started again: W1" (Lift, case A). In the new model one cause gives one hold, and a part is held while any
hold's scope covers it. Today's other holds become hold records too: `service_issues.hold_until = 2099-01-01` (g2 row 22)
and `crawl_workflows.paused` written by Start's watch (g3 C8).

### 2.5 Switch

A person's choice: **On**, **Off** (a person's pause or Deactivate, with who and why), or **In maintenance** (taken out,
with a reason and a Back by). The four layers stay as they are: the master, then workflow → task → service (R140), each
with who, when and why (R143). Off by the system (left by a closed trip) (§0) is read from the log for switches written before this build, and
is never written again.

### 2.6 Alarm, Incident, Thread

- **Alarm:** one per open fault on one part (R78), with its level (warning, trip or notice), its ids (**W-####** from the
  warning and **T-####** at the trip), its reading, its count and its first and last seen.
- **Incident:** opened at a trip as **IR-####**. Its members are the trip and any correlated trips on the same host with
  the same signature in the window (§4 group F). It holds the timeline of warnings, checks, the trip, notices, actions
  and the close; its **Incident Radius** (Held / On a backup / Unaffected); its evidence; and its root cause, which a
  person writes or the engine sets from a correlation (R173; report §5).
- **Thread:** one per alarm or incident, on every channel, in the six-part shape: Title, Status, What it affects, What
  the system is doing, Buttons, Details ▾ (0176 §2; R102). A group of alarms that share a cause has one thread (Q11).

Today a warning and its trip are the same row with no id of its own, the moment of the trip isn't logged, and there is
no Incident Report (g3 §4; g4 row 16).

### 2.7 Check

Each part declares what it calls, what proves a pass, and when it may run.
- **It calls the component as the failure did**, or the cheapest request of the same kind: free, touching no one,
  costing nothing (R59).
- **It never reads stored results only.** Today, `Preflight::workflow` passed W3 on stored results, and the test of
  `collinfo.json` (a static file) stood in for the index query that answered 504 for 30 hours (Lift (a)).
- **It runs past its own part's hold for that one request** (P7). Today a refused key's Check was refused by its own
  issue's hold (g2 F2).
- **A check result** records what was called, the answer (code, size, ms) and pass or fail, with the proof in words:
  "Common Crawl's index answered a real query: a lookup for <fixed URL> found 1 record in 412 ms."

The checks, by kind:

| Kind | What it calls | What proves a pass |
|---|---|---|
| A source or service server | its declared probe, a request of the failed kind | a 2xx with the expected shape, inside its speed gate |
| A key on `.env` | one free authenticated call with that key | a 2xx that isn't an error body |
| A key on Railway | the worker's key test at its next run (one free call per key) | the worker reports "answered" for that key; presence alone is "not checked yet" (Q4). Today only presence is reported (g2 row 2) |
| Our endpoint | an authenticated no-work request (`?check=1`) | a 2xx within the endpoint's speed gate |
| The crawler | its last run | a run started within its schedule plus 5 min that reported back. An `idle` run while held never counts. Today `rw:crawler` passes once any run ever reached the site (g1 row 32), and a paused run's `idle` closed `run:failed` (g1 F4) |
| W0 Indexer, the video worker | the heartbeat | a heartbeat within 15 minutes (the video worker's is *spec*) |
| Railway (host) | its API: each service's latest deploy, and the crawler's or W0's check | a successful deploy, and a worker check that passes |
| Bluehost: page | an outside heartbeat (GitHub, §10 O3) | the home page and `/api/health.php` answer 200 |
| Bluehost: the databases | one query per realm (main, admin, listings, users), at every watch | each realm answers. Today only the nightly pre-flight checks them (g3 C14) |
| Bluehost: cron | the watch's own last tick, and each critical job's cron line | a tick within 10 minutes; every critical job's line present. Today `billing_tick` isn't checked (g3 J8) |
| GitHub | a read with each token, and the release look | each token reads its repository; the look returns found ≥ 0 |
| A job | its declared check (its dependencies, its cron line), else **its next run** | a run that finished well. A skipped run ("Paused: …") never passes. Today it counted as good (g3 J2) |
| Search, sign-in, payments | the 5-minute tests, and the server-error hook | as 0171 L338-346 (built, `sensor` 30/30) |
| A need | its sources' checks | any one source passes |
| A cap | the use against the cap | under 50% of it (g2 row 8: built) |
| A channel | readiness: SMTP sign-in, SES live keys, VAPID keys, and at least one active receiver with that channel | all present; a test dispatch on demand |

**A part with no check of its own** (a job or task failing in its own code) has only its next run as a check. Its fault
is a warning that says so: "No check can run before its next run at <time>". The person's button is "It's fixed: try it
again" (R122). What happens if that next run fails too is open item O2.

---

## 3. The canonical states

One model, written once, used by every part.

### 3a. A module's lifecycle: Jason's eight stages on the report's five states

Refined from `manifests-railway-github.md` §3. A stage is a module's place in its life. Its run-state (3b) is what
it's doing now.

| Report's state | Jason's stage | Allowed here | Who presses | Its way back |
|---|---|---|---|---|
| **Registered / Draft** | 1. Selection and configuration | Edit configuration · Add its key · Propose · Discard · **Complete setup capture** | a person | Drop it (a proposal) |
| | 2. Integration (pre-flight) | Run pre-flight (its check, with its keys, reach and limits) · Edit | a person; the nightly 2 AM run | — |
| **Active (Operational)** | 3. Activation | **Activate** (offered only once its pre-flight passes, R57) | **a person** | Deactivate |
| | 4. Assigned to workflows | Assign to a need or task (in the map's order) · Unassign (Q22) | a person | Unassign |
| | 5. Operational | Check now · **Pause** · Resume · Check and start again (on a trip) · Fix it | Pause: **the system or a person**; Resume: the system's passing check, or a person | Resume |
| **Maintenance / Draining** | 6. Maintenance | Take out (a reason and a Back by) · Put back (its check first) · Extend the window | a person; a window set ahead goes out and back by itself | Put back |
| **Deprecated** | 7. Deactivation | **Deactivate**, with why. Its assignments first move to the next source on the map ("Migrate traffic"), and no new ones are taken | **a person** | Activate through stage 2's check |
| **Removed (archived)** | 8. Removal | **Delete** (while code remains it reads Retired; once a release drops the code, Removed) · Bring it back · View history | **a person** | Bring it back (while code remains) |

**Stage 1's exit guard: Setup & Access Capture** (Jason, 2026-11-21; `04-vault-portal-links.md` §10). A module that uses an
outside service or an off-site access point can't leave stage 1, so it can't be integrated or activated, until its
record says three things:
1. **Where to sign in:** the Sign in slot, or `Not registered` with its reason.
2. **Where its keys live:** each key's **name** and place (`.env`, Railway, a GitHub secret, or the Vault), and the Keys
   slot. Credentials themselves live only in the Vault. No value ever appears in a record, spec, log or message.
3. **Which slots are intentionally unregistered,** each with a one-line why. A `Not registered` slot without a why blocks
   the exit.

Every slot carries exactly one approved source tag. A module that went live before this rule (all of today's) isn't
held: its gaps show as a notice on its card ("Setup capture: Sign in not recorded"), never an alarm or a hold, until its
next setup change.
- Transition: stage 1 → stage 2 is guarded by the capture being complete.
- Test: S-A0, "a new module can't leave stage 1 while any of the three is missing; an unregistered slot without a why is
  refused; no credential value in any record".


**The rules:**
- System code may call only **Pause**, as a hold (R34). Today Start's watch writes switches (g3 C8), so it fails this.
- The stage is the same text on the card, the register, the board and an account's Apps page (R32).
- The card shows the eight stages as a strip: ✓ for each stage reached, the current one lit, and an unreached one naming
  what's missing with its button ("2 Integrated: its key isn't in. Add its key").
- Diagnostics, not pre-flight, for any module past stage 3, including one the system paused (R57).

### 3b. A part's run-state: one value at a time

| Run-state | What it means | Entered by | Left by | Light |
|---|---|---|---|---|
| **Working** | Its last check, or its last real call, passed | a pass | a fault, a switch, maintenance | green |
| **On a backup** | Its first source is held, and the next one works. A working state, never "Holding" (R90) | its first source paused while a later one passes | the first source's check passes ("Back on its first choice: <name>", logged) | amber, naming the backup |
| **Warning (the system is trying)** | A fault is open and the system is checking it, or it's slow, rate-limited, near a limit or waiting for its next run. Nothing is held | `fault()` | a pass (cleared), or tries out (a trip) | amber "!" |
| **Paused by the system** | A hold covers it, either **for its own trip**, or **waiting on** another part's trip | `hold()` | `release()` by the cause's passing check | red for its own trip; amber "Held" with the pause mark while waiting on another (R107) |
| **Off** | A person's switch, or Deactivated | a person | a person's Switch on, with the switch-on check | grey |
| **Off by the system (left by a closed trip)** | A switch written by the board's trip or Start's watch before this build, whose trip has closed (§0). While its trip is open: "Off by the system (for its open trip T-####)" | read from the log | CS-3's one exit rule | amber with the pause mark, open under Needs attention |
| **In maintenance** | Taken out on purpose | Take out, or a window | Put back after its check | blue |
| **Not checked yet** | No check has ever run: a key the worker hasn't reported, a new part | registration | its first check | grey, labelled "not checked yet" (Q4) |
| **Removed** | Deleted. Kept for history, off the board | Delete | Bring it back | none |

**Combining with the switch.** The board writes `<switch> · <run-state>`. Examples:
- "On · Working"
- "On · Paused by the system: waiting on Common Crawl's index (IR-0007)"
- "Off · Paused by the system: its trip stays open until its check passes"
- "Off by the system (left by a closed trip): left off by …" (§0)
- "In maintenance until 16:00 MST"

**Precedence for the light and the first words** (the first that applies wins):
1. Removed
2. In maintenance
3. Off
4. Off by the system (left by a closed trip)
5. Paused by the system for its own trip
6. Paused by the system, waiting on another part
7. Warning
8. On a backup
9. Not checked yet
10. Working

The rest of what applies is still said after the first words.

**How the states combine:**
- A person's Off is never shown as a failure, and raises nothing (R109).
- A part that is Off with a trip still open keeps that trip. Its check still runs, since a check costs nothing (R59). It
  can close as Recovered while the part stays Off.
- A part is never shown "Working" while it is held.

**Roll-up (a system, a module, a workflow):**
- Its light is its worst part's (R9).
- A workflow with some of its tasks held reads **"Partly working: <tasks> wait on <part>"** (R56).
- A system's hour turns red only when a critical part is down (R108).
- A roll-up never raises an alarm of its own (§2.2).

### 3c. An alarm's states

```
                ┌────────── pass ───────────┐
Notice (open) ──┴─► Closed                  │
Warning (open) ──── tries out / at once ──► Trip (open) ── tries out (critical) or Pulse's clock ──► Escalated (open)
   │  pass                                  │  pass / person's fix + pass             │  pass / person's fix + pass
   ▼                                        ▼                                         ▼
Cleared (logbook)                Closed: Recovered · Fixed by <name> · Retired (any open state, on Delete)
```

| State | Its mark | Board | Pulse | Email | Push |
|---|---|---|---|---|---|
| **Notice** (on a backup, came on, in maintenance, "N sites blocked us") | — | its row, "Notice" | none | none | none |
| **Warning** | W-#### | "!" with "Trying again (n of 3)", or "Slow", "Asked us to slow down", "Near its cap"; no Acknowledge (R80) | none | none | none |
| **Trip** | T-####, IR-#### | red, "Paused by the system (a trip)" or, when nothing is held, "Down: the system is still trying". Seen by <name> when acknowledged; the light stays red (g4 F1) | one row, Critical, unread | one email to the Founder, in the same minute | one push |
| **Escalated** | the same ids | "Retries unsuccessful, now admin required" | the same row, unread again; Pulse's clock: the Manager at 2 h, the Director at 4 h (R76) | a reply in the thread | one push for the level rise |
| **Closed: Recovered** | | "Recovered: <part> was down <n> min, the system fixed it." | the row's last note; it leaves the open list | a reply in the thread | none (R132) |
| **Closed: Fixed by a person, with a passing check** | | "Fixed by <name>: <what they changed>. Its check passed: <what was checked>." When the person changed nothing: "Recovered: <part> was down <n> min; <name>'s check found it working: <what was checked>." | the same | a reply | none |
| **Closed: Retired** | | "Closed: <part> was deleted by <name>; its history is kept." | the same | a reply | none |

**Where a closed alarm goes:**
- It leaves the alarm list for the logbook and the Alarms table (R79).
- Its Incident Report stays open to read (R173).
- A person's close can be undone within a day (0171 L397), which reopens it.

---

## 4. The full state-transition table

**Column keys:**
- **Basis:**
  - **obs**: observed live, in the snapshot or in Jason's account;
  - **sim**: simulated by a probe on the live code;
  - **inf**: inferred from code or decisions, not yet run;
  - **des**: designed, the new model, proved by the step-4 scenario named in the Test column.
- **Clock:**
  - **Diag** is the diagnostic clock: the engine's tries at the next watch tick (≤5 min), then after the back-off wait
    (10 min, then 20), so at about 5, 15 and 35 minutes. Three tries or 60 minutes, whichever comes first
    (`control_fix_tries` 3, `control_fix_minutes` 60). After a trip, checks follow the back-off ladder (10 min doubling
    to 6 h, today's `ServiceHealth` values; R104).
  - **Sched** is the run schedule.
  - **Pulse** is Pulse's escalation clock.
- **Words:**
  - **B** is the board and the card (the full thread);
  - **P** is the Pulse row;
  - **E** is the email subject (the body is the thread's shape, §7);
  - **Pu** is the push title and body;
  - "—" means nothing is sent.
  - `<part>` is always the part's plain name (P9).
  - Notice titles are `SYSTEM ALARM NOTICE: <item or group>: <action>` and push titles `SYSTEM ALARM: <item or group>`
    (R130).
- **Hold:** what is held (Held), what runs on a backup, and what is unaffected. "Unaffected: the rest" means every part
  not listed as Held or On a backup.

Rows marked "·now" are today's behaviour, given beside the designed row with the same number.

### Group A: The W3 lift of 2026-11-21, today and designed (transition #1 and what follows)

The starting state, observed at 10:19 UTC and from Jason's account:
- 4 trips: `pf:wf:W3`, `svc:issue:35` (index.commoncrawl.org, 504), `task:failed:overture_bulk` and
  `task:failed:foursquare_bulk`;
- two W3 task switches off from a pre-0175 trip.

Under the designed model the same causes give a different starting state:
- one trip on **Common Crawl's index**;
- one warning on **our endpoint `/api/crawl/loads`**;
- two switches **Off by the system (left by a closed trip)**.

| # | Basis | From | Event or action (who) | Guard: the real check | To | Hold: Held · On a backup · Unaffected | Side effects | Words: B · P · E · Pu | Clock | Test |
|---|---|---|---|---|---|---|---|---|---|---|
| 1·now | obs, sim | index.commoncrawl.org Working | The crawler's calls get 504 (issue #35) | none: each failed call counts | issue warning "(k of 6)", then a trip at 6 failures or 6 h; `hold_until` 2099 | the host, for the crawler | none | B "index.commoncrawl.org: Its server failed (the crawler, on Railway)"; at the trip "held until an admin resolves it" | count of calls | L4; g2 rows 17-18 |
| 1 | des | Common Crawl's index: Working | `fault(index server, evidence: 504 Gateway Time-out)` from the crawler's report or the site's call | the engine classifies it (504 is a back kind) and opens W-#### | Warning | none | the first try is due at the next tick | B "Common Crawl's index isn't answering · Trying again (1 of 3): it answered 504 Gateway Time-out at 07:06 MST. Next check 07:11 MST." · P — · E — · Pu — | Diag | S-A1 |
| 2·now | obs | warning | 6 failures / 6 h | none | trip, held "until an admin resolves it"; the watch never checks it again (crawler issues aren't rechecked) | the host's calls | open 30 h | as 1·now | count | L4; g1 row 13; g2 row 22 |
| 2 | des | Warning | the engine's 3 tries of the index query fail (≈5, 15, 35 min) | each try is the index query itself: a CDX lookup for a fixed known URL | Trip: T-####, IR-#### | **Held:** calls to Common Crawl's index (no crawler task uses it since 2.15.0, L4). **On a backup:** none. **Unaffected:** W3 and every workflow (W3's need "sources" doesn't wait on this backup, K3) | the incident opens with the warning's id | B "Common Crawl's index isn't answering · Paused by the system (a trip) since 07:41 MST. Next check 07:51 MST." · P/E "SYSTEM ALARM NOTICE: Common Crawl's index: Paused by the system" · Pu "SYSTEM ALARM: Common Crawl's index" / "Paused by the system (a trip) since 07:41 MST. Nothing else waits on it." | Diag | S-A2 |
| 3·now | obs, sim | W3 Working | W3's own pre-flight line turns red over Common Crawl's line | stored results | `pf:wf:W3` trip, **W3 paused whole**; its words disagree with its line (L1, L10) | all of W3 | "its tasks wait with it" | B "W3 won't start until this is fixed" | Diag (no try made) | L1, L10; g1 row 20 |
| 3 | des | W3 Working | W3's roll-up recomputed | none needed: a roll-up isn't a fault | W3 "On · Working" (or "Partly working" if a task waits) | as row 2 | **no alarm on W3**; its row links "Waiting on: none" | B W3's row: "Working. Its backup source Common Crawl's index is paused (IR-0007); nothing in W3 uses it." | — | S-A3 |
| 4·now | sim | trip | the watch's tick | skipped: a holding issue is never rechecked | trip, unchanged | — | — | — | — | g2 row 22, F3 |
| 4 | des | Trip | the after-trip check on the back-off ladder fails | the index query | Trip, its status updated in place | unchanged | — | B status "Checked 09:42 MST: still 504. Next check 10:12 MST." · P the same, in place · E — · Pu — | Diag (ladder) | S-A4 |
| 5·now | obs, sim | `pf:wf:W3` trip | **Check and start again** on W3's row (Jason) | W3's line on stored results, plus a refresh that fetched `collinfo.json` (a static file) | closed "Resolved by Jason: Checked and started again Started again: W3 (its tasks wait with it)." | W3's pause lifted | **#35 and #22 closed as a hidden side effect** (`answered()`); the threads are left "Paused by the system", unread | B the Success line above · P the item gone · E, Pu: nothing (still the trip's) | — | Lift (a), (b), (e) |
| 5 | des | Trip | Check and start again, pressed on Common Crawl's index row, or on W3's row (where "Waiting on" carries the same button, CS-4 / standard 3) (Jason) | **the index query**, past the hold for this one request | **pass:** Closed. **fail:** row 5b | **pass:** the index's hold lifts; nothing else changes | the incident gets its close and the check's evidence. **No other fault closes unless its own component was the one checked** (row 6) | **pass:** B "Recovered: Common Crawl's index was down 7 h 21 min; Jason's check found it working: the index answered a real query (a lookup for <URL>, 1 record, 412 ms)." · P the row's last note, the same words · E a reply in the thread, "SYSTEM ALARM NOTICE: Common Crawl's index: Recovered" · Pu — | — | S-A5 |
| 5b | des | Trip | as 5, the check fails | the index query | Trip, with Check and start again greyed | unchanged | — | B "Checked 14:02 MST by Jason: still 504 Gateway Time-out. The system checks again at 14:32 MST." Button greyed "Its check is red (504 at 14:02 MST)" · P the status in place · E — · Pu — | — | S-A5b |
| 6·now | sim | #35 trip, #22 warning | W3's refresh made the site's call to the index and data hosts | a 2xx from any site call at the host | both closed "Resolved by the system", not Recovered; no thread update | holds cleared | the switch-on warning stays until the next watch | — | — | Lift (b) |
| 6 | des | the data host's warning (#22's component) | none: a check closes only the component it checked | — | stays a Warning until its own check (a small read from the data host) passes | — | each component's close goes through its own `release` and `notify` | when its own check passes: B "Cleared: Common Crawl's data host answered its check (a read of <object>, 2xx, 230 ms)." (a warning, so nothing is sent) | Diag | S-A6 |
| 7·now | obs, sim | `source`, `commoncrawl` (W3) off by a pre-0175 trip | that trip closes | none | off, and listed: "left off by a trip that has closed (…). The board switched it off before trips paused instead, and nobody had set this switch before, so it stays off." | the tasks don't run | the same fixed words for every such switch, **never read from the log** | B the words quoted (Jason quoted them verbatim) | — | Lift (c); `ControlBoard.php:214, 357` |
| 7 | des | Off by the system (for its open trip, the pre-0175 W3 trip) | its trip has closed | CS-3 reads `automation_log`; no person's row exists | CS-3 case **None**: Off by the system (left by a closed trip); **nothing is switched on** (NG) | the two tasks don't run, and are named | listed under Needs attention; the closing thread carries **Still off** (CS-5) | B "Off by the system (left by a closed trip): left off by W3's trip of Nov 20 (closed Nov 21). No one has switched it on or off by hand, so it waits for you. [Switch on]", on the task's row on wf-W3 and on the closed trip's row, open (CS-4) · P the trip's closing note carries the same "Still off" line · E the closing reply carries it · Pu — | — | S-A7 (the three CS-3 branches) |
| 8·now | sim | — | the press's notice | — | "Started again: W3" | W3 runs no task | — | as 5·now | — | Lift (d) |
| 8 | des | — | any person's close | — | — | — | the press's notice is built from the holds actually lifted and the switches still off | "Closed. Resumed: <parts the hold held>. Still off: Read a source (W3), Common Crawl (W3): [Switch on]." It never says "Started again: W3" while no W3 task can run | — | S-A8 |
| 9 | des | Off by the system (left by a closed trip) | **Switch on** on the task's row, on the closed trip's row or on its Needs attention line (Jason) | the switch-on check for that task: its needs' checks, run now | **pass:** On · Working. **fail:** stays Off by the system (left by a closed trip) | **pass:** the task is planned | logged "Switched on by Jason (was left off by a trip)" | **pass:** B "On · Working: switched on by Jason, its check passed: <what>." **fail:** "Not switched on: <need> has no working source (<reason>)", with its fix | — | S-A9 |
| 10·now | sim | the trip's thread | a person's close | — | the message stays `alarm.trip` "Paused by the system", unread | — | the bell keeps counting it | — | — | Lift (e); g4 row 7 |
| 10 | des | the trip's thread | any close (Recovered, Fixed, Retired) | — | the thread is closed on every channel | — | the bell's count drops; it's in the logbook | §7 "Closed" rows | — | S-A10 |
| 11·now | obs, sim | W1 Working | `overture_bulk` times out reading our `loads.php` reply (run #1290) | none | `task:failed` warning, then a trip at 60 min with no try made; **W1 paused whole** | all of W1 | 0 records in or stored in 24 h | E/P "SYSTEM ALARM NOTICE: W1 Listings: Paused by the system"; B "…the workflow's other tasks carry on" (false) | the wall clock | L2; g1 row 2; IR-0001 |
| 11 | des | `/api/crawl/loads`: Working | `fault(loads endpoint, evidence: TimeoutError reading the reply, 120 s, 500 rows)` from the worker's report | the engine classifies it: a timeout reading **our** reply is **our endpoint slow** (Q3) | the endpoint: Warning (Slow) | **Held:** nothing. **Unaffected:** W1 and every task | the endpoint's check is due at the next tick | B "/api/crawl/loads is slow · It didn't reply to the crawler within 120 s for 500 rows at 01:31 MST. The system is checking it." · P — · E — · Pu — | Diag | S-A11 |
| 12·now | obs | W1 paused by #1290 | `foursquare_bulk`, the same, 11 min earlier (run #1288) | none | a second W1 trip and a second W1 pause | all of W1, twice | — | a second trip email and push | the wall clock | L3 |
| 12 | des | the endpoint: Warning | the same signature at the same component | the engine matches the component and signature | the same alarm: count 2, last seen updated | nothing | no second alarm and no message | B "…seen 2 times (01:20, 01:31 MST)" | — | S-A12 |
| 13 | des | the endpoint: Warning | the engine's check of the endpoint passes | an authenticated no-work request answered 2xx within its speed gate | Cleared | — | — | B "Cleared: /api/crawl/loads answered its check in 0.4 s (01:36 MST)." | Diag | S-A13 |
| 14·now | obs | W1 paused | the next night's window | — | the bulk tasks don't run; "Records: 0 in, 0 stored" | — | — | — | Sched | L2, L3 |
| 14 | des | W1 Working | the next night's window | — | `overture_bulk` and `foursquare_bulk` run | — | — | — | Sched | S-A14 |
| 15·now | sim | 3 trips in the group "3 engine feeds failing" | **Acknowledge all 3** (the group's one button) | none | acknowledged; **the top light turns green, "No alarm open"**, while the pauses hold | unchanged | — | B light green | — | g4 F1; Lift (f) |
| 15 | des | Trip(s) | Acknowledge on a trip or a group of trips | none (it's a mark, not a close) | Trip, "Seen by <name>" | unchanged | the escalation continues (Q12); Check and start again stays on the same row (standard 3) | B "● 1 trip (seen by Jason): …"; **the light stays red** · P the row's note "Seen by Jason 08:05 MST" · E — · Pu — | Pulse | S-A15 |

### Group B: Faults by kind (entry points)

| # | Basis | From | Event or action (who) | Guard: the real check | To | Hold: Held · On a backup · Unaffected | Side effects | Words: B · P · E · Pu | Clock | Test |
|---|---|---|---|---|---|---|---|---|---|---|
| 16 | des | task Working | the task errors **in its own code** (no failed call in the evidence) | classified as the task's own; it has no check of its own | Warning "waiting for its next run" | nothing (P7: holding the task would block its check) | — | B "<task> failed in its own code (run #N): <error>. No check can run before its next run at 02:00 MST." · [It's fixed: try it again] | Sched (its next run is its check) | S-B16 |
| 17 | des | as 16 | its next run fails again | that run | **O2, open** | **O2** | — | — | Sched | S-B17 (after O2) |
| 18 | des | as 16 | its next run finishes well | a run that did its work, or found nothing to do | Cleared | — | — | B "Cleared: <task>'s run #N finished well." | Sched | S-B18 |
| 19 | des | endpoint Working | a call 3× slower than its usual (≥20 calls of history), or **over its declared speed gate** (fewer than 20) | the ledger's timing | Warning (Slow) | nothing | — | B "<endpoint> is slow · It took <s> for <n> rows against its usual <s>." | — | S-B19 (today g1 rows 7, 9: missing under 20) |
| 20 | des | endpoint: Warning (Slow) | a call at its usual speed, or its check inside the gate | the timing | Cleared | — | — | B "Cleared: <endpoint> answered at its usual speed (<s>)." | Diag | S-B20 |
| 21 | des | endpoint Working | it answers 5xx | — | Warning, W-#### | nothing yet | the endpoint's check is due | B "<endpoint> answered a server error (500) · Trying again (1 of 3)." | Diag | S-B21 |
| 22 | des | endpoint: Warning (5xx) | 3 failed tries of the endpoint's check | an authenticated no-work request | Trip | **Held:** the tasks that call it (for `loads`: `overture_bulk`, `foursquare_bulk`, `geofabrik_bulk`). **Unaffected:** their workflows' other tasks, and every other endpoint | the check keeps running on the ladder and **runs while the tasks are held** (P7; today a deadlock, g1 row 24) | B "<endpoint> keeps failing · Paused by the system (a trip): 3 tasks wait on it." · P/E "SYSTEM ALARM NOTICE: <endpoint>: Paused by the system" · Pu "SYSTEM ALARM: <endpoint>" / "Paused by the system: 3 tasks wait on it." | Diag | S-B22 |
| 23 | des | endpoint Working | the worker gets 406 (ModSecurity) or 409 `humans_` (Bluehost's bot check), per run | classified as a **refusal** on the endpoint | Paused by the system at once (it needs a person: ask Bluehost to exempt the path) | Held: the tasks that call that endpoint | `site_blocked` is read (today nothing reads it, g1 row 26) | B "Bluehost's bot check refused the crawler at <endpoint> · Paused by the system. Ask Bluehost to exempt <path>." Fix it → Bluehost's card, its bot-check line · P/E/Pu: the trip | at once | S-B23 |
| 24 | des | server Working | timeout, 5xx or a malformed answer (back kinds), from any caller; **a burst inside one run is one fault** | the engine classifies it; the worker's kind isn't trusted (P2) | Warning "Trying again (1 of 3)" | nothing | — | B "<module> isn't answering · Trying again (1 of 3): <answer> at <time>. Next check <time>." | Diag | S-B24 (today g2 row 18: 6 timeouts in a run trip it) |
| 25 | des | as 24 | any try passes, **or any real call from any caller gets a 2xx** | that answer | Cleared, with the tries reset | — | — | B "Cleared: <module> answered (<what>)." | Diag | S-B25 (today g2 row 19: a success between failures is ignored) |
| 26 | des | as 24 | 3 tries fail, or the hour ends after a failed try | its declared probe | Trip, IR-#### | the radius (§6): its calls; each task reroutes or waits; a workflow waits whole only when every task does | each task that reroutes says "On a backup" (row 44) | B "<module> isn't answering · Paused by the system (a trip) since <time>." · P/E "…: Paused by the system" · Pu "SYSTEM ALARM: <module>" / "Paused by the system. <n> tasks on a backup, <m> waiting." | Diag | S-B26 |
| 27 | des | server Working | 429, or `503 SlowDown … reduce your request rate`, or Google's **per-minute** RESOURCE_EXHAUSTED | the engine classifies the body (g4 L12; g2 F5) | Warning (a rate wait): its Retry-After, else twice as long each time; **never a trip** | nothing ("a back-off in its wait isn't a hold", R70) | Check now greyed with the next try as text | B "<module> asked us to slow down · Calls wait until <time>, then start again by themselves." | Diag | S-B27; `r5a-rate` |
| 28 | des | as 27 | the wait ends and the engine's check passes, **whether or not any task calls it** | its probe | Cleared | — | — | B "Cleared: <module> answered its check after its wait (<time>)." | Diag | S-B28 (today g2 row 12, L11: open 3 days) |
| 29 | des | key Working | 401, or 403 with an auth body, at a key | classified as a refused key: a stop kind | Paused by the system **at once**: no tries, it waits for a person | Held: calls using that key. Each task reroutes (Tavily ↔ Brave) or waits | Fix it → the card's Its key with Replace ready | B "<module> refused our key · Paused by the system: calls with <KEY> wait for a new key." (never "Nothing could be paused", g1 row 15) · P/E "…: Paused by the system" · Pu the same title / "Paused by the system: its key was refused." | at once | S-B29 |
| 30 | des | key Not checked yet | the worker hasn't reported that key yet | — | Not checked yet | nothing; Start doesn't wait on it (Q4) | — | B "<KEY> on Railway: not checked yet (the worker reports at its next run)." | — | S-B30 (today g1 row 21: red, holds tasks) |
| 31 | des | Not checked yet / Working | the worker reports the key missing, or its test refused | the worker's key test | as 29 | as 29 | — | as 29, "isn't on Railway" or "was refused" | at once | S-B31 |
| 32 | des | server Working | a ban (403/406/418, a challenge page) | classified as a ban | Paused by the system at once; **never retried** | Held: that caller's calls to it; tasks reroute or wait | its probe goes once a day, for at most 3 days | B "<module> refused us · Paused by the system. The system checks once a day for 3 days; after that it's your call." · P/E/Pu: the trip | probe daily | S-B32 (today g2 row 14: no probe exists) |
| 33 | des | as 32 | the ban's probe passes | one polite request | **stays Paused**; a Notice "looks lifted" with Reactivate | unchanged (no automatic return, R94) | — | B "<module>: its ban looks lifted (probe 200 at <time>). [Reactivate]" · P the trip's row updated · E — · Pu — | probe | S-B33 |
| 34 | des | as 32 | 3 daily probes fail | the probe | stays; the probes stop | unchanged | — | B "<module>: still refusing after 3 days. It waits for you: [Reactivate] tries once." | — | S-B34 |
| 35 | des | a website (not a module) Working | a site blocks W0's or W3's read | classified as a refusal | that site held; never retried | Held: reads of that site | rolled into one Notice; **a warning only past `w0_block_share`**; never a trip | B "<n> sites blocked us" with up to two [Reactivate] in place | — | S-B35 (`itsdata` (built, matches g1 row 22) re-run against the model) |
| 36 | des | server Working | the provider's **per-day** quota used (Gemini RESOURCE_EXHAUSTED per day) | classified as a quota with a known reset (its manifest) | Paused by the system **until its reset** | Held: its calls; the work waits, with no fallback to Claude (Q13) | the check runs at the reset time; the card's two rows (PL): On our system [Check now]; At Google: [Open Google AI Studio (Gemini) billing ↗], or the note "No billing page registered for Google AI Studio (Gemini) yet" (its only Limits link is a docs page, O5) | B "<module> used its day at the provider · Paused by the system until 00:00 Pacific; its check runs then." | at its reset | S-B36 (today g2 row 6: held for good) |
| 37 | des | as 36 | the reset check passes | its probe | Closed: Recovered | released | — | B/P/E "Recovered: Gemini was held 9 h until its day turned; its check passed." · Pu — | at its reset | S-B37 |
| 37a | des | Gemini (a Service on its free tier) Working | a feature whose input isn't public (research, archives, a listing, a person's own words, private material) is asked onto Gemini | `Gemini::refused()`: the feature's input class (`AiClient::INPUT`) | **a Notice on Gemini's card only**; the feature stays on Claude (the session manager's ruling, 2026-11-21) | nothing held; nothing sent to Google | no message (a Notice); the refusal is counted on the card | B "Kept on Claude: this carries a person's own words (nothing private to Gemini's free tier)" (the class named by its input) · P, E, Pu — | — | S-B37a |
| 38 | des | server Working | out of credit at the provider (Anthropic 400 "credit balance is too low") | classified as a quota with no reset: a stop kind | Paused by the system at once; it waits for a person | Held: its calls; paid backups stay off | Fix it → its card's Caps and paces, On our system: [Pause our calls] [Check now]; At Anthropic: **[Open Anthropic (Claude) billing ↗]** (its record's Limits link, https://console.anthropic.com/settings/limits), then Check and start again (PL) | B "Claude (AI) is out of credit at Anthropic · Paused by the system. Add credit, then press Check and start again." · P/E/Pu: the trip | at once | S-B38 (today g2 row 7: nothing raised) |
| 39·now | obs | Claude (AI) capped, $27.06 of $40.00 | the watch's cap look | the reading | Warning; **the title is the band reached, the body the reading** (`Operations.php` L605 builds the title from `$w['level']`, L614 the reading from `$top['pct']`) | nothing | the buttons Raise the cap and Pause it, with no link to the provider's billing | B title "Anthropic (Claude) at 50%", body "67%" · P, E, Pu — (a warning) | — | L5, L6; g4 row 12 |
| 39 | des | a capped part Working | our use crosses 50 / 75 / 95% (**the band decides the level, never the number shown**) | the reading: spend and cap, read together | Warning (Near its cap) | nothing | two kinds of action, apart (PL). On our system: [Raise the cap] [Lower the cap] [Pause our calls] [Check now]. At Anthropic: [Open Anthropic (Claude) billing ↗], then Check now | B title **"Claude (AI): $27.06 of $40.00 (68%)"**, and the body says the same numbers and the same 68%, "past its 50% warning". The percentage is round(spend ÷ cap × 100) in both · P, E, Pu — | — | S-B39: title % = round(spend/cap×100) = body %, at 51%, 67.65% and 96%; the billing-type portal link present, or its "not registered yet" note; no board action changes the provider |
| 40 | des | Warning (cap) | our use reaches 100% | the reading | Paused by the system (a trip) | Held: every call, a person's press included (Jason, 2026-11-21: at the cap nothing passes) | Fix it → Caps and paces: On our system: [Raise the cap] (the owner's, logged: the one way past it) [Pause our calls] [Check now]. At Tavily: "No billing page registered for Tavily yet" (PL; O5) | B "Tavily: $20.00 of $20.00 (100%) · Paused by the system until our cap is raised or the month turns." · P/E/Pu: the trip | at once | S-B40 (built: g2 row 8 matches; re-run against the model) |
| 41 | des | as 40 | the cap is raised, or the month turns | use under the cap | Closed (Fixed by <name>, or Recovered) | released | — | "Fixed by Jason: raised our cap for Tavily to $25.00. Its check passed: $20.00 of $25.00 (80%)." (never "raised at Tavily") / "Recovered: … the month turned." | — | S-B41 (built, re-run against the model) |
| 41a | des | a workflow's Claude features Working | the workflow's share of the AI cap (`budgets.ai_share_pct`, with last month's unused part) is used (`AiClient::shareLeft`) | the reading: the workflow's spend this month against its share | **a Warning on that workflow only**, never on Claude or its other workflows (the session manager's ruling, 2026-11-21) | Held: that workflow's Claude features, until the 1st or its share is raised. Unaffected: every other workflow's features and Claude itself | the board's Workflow page offers Raise its share | B "<workflow> has used its <n>% share of the AI cap ($<x> this month, with last month's unused part): its Claude features wait until the 1st." · P, E, Pu — (a warning) | — | S-B41a |
| 42 | des | a backup server Working | the backup's own check fails while the primary works | its probe | Warning, then a Trip on the backup's calls | **Held:** the backup's calls only. **Unaffected:** every workflow (they don't use it now) | the need's line reads "backup down" | the same shape as 24 and 26, plus "Nothing waits on it: its need's first choice is working." | Diag | S-B42 (today g1 row 19: its own calls weren't paused) |
| 43 | des | a task using its first source | its first source becomes Paused by the system (26, 29, 32, 36) | the next source on the map passes its check | the task On a backup | On a backup: that task | the switch is **logged** (today the worker's `search_switches` is dropped, g2 row 27); the need's line reads "On a backup since <time>" | B a Notice "<task> is on its backup (Brave) while Tavily is paused (IR-…)" | — | S-B43 |
| 44 | des | On a backup | the first source's hold is released | the first source's check | Working (its first choice) | — | logged "Back on its first choice: Tavily" | B the Notice closes, "Back on its first choice: Tavily." | — | S-B44 |
| 45 | des | task Working or On a backup | every source of a need it uses is paused | — | the task is Paused by the system (waiting on <need>) | Held: **every task that needs it, in every workflow the need names** (g1 F8) | each held task names the need and its first fix | B on each task's row: "Waiting on <need>: no source works (<A> paused, <B> paused)." | — | S-B45 |
| 46 | des | workflow Working | every one of its tasks is held | the roll-up | the workflow Paused by the system (waiting on <part>) | the workflow; nothing more | — | B "W<n>: every task waits on <part> (IR-…)." No alarm of its own | — | S-B46 |
| 47 | des | crawler Working | no run started within its schedule plus 5 min, or a run was abandoned (killed at `RUN_LIMIT`, crashed) | the crawler's check (a recent run that reported) | Warning; **an abandoned run counts as a failure** | nothing yet | the run's lost task results are noted on the run | B "The crawler hasn't reported since <time> · Trying again (1 of 3)." | Diag | S-B47 (today g1 rows 30-31: 4 kills never trip; no run is Pulse-only) |
| 48 | des | as 47 | 3 tries fail | the crawler's check | Trip | **Held:** all gathering the crawler runs (W1–W12's crawler tasks). **Unaffected:** W0, the site and its jobs, pages, search, sign-in, payments | Fix it → Railway's card, the crawler's deploy and schedule | B "The crawler is down · Paused by the system: all gathering waits." · P/E "SYSTEM ALARM NOTICE: The crawler: Paused by the system" · Pu the same | Diag | S-B48 |
| 49 | des | crawler Working | a run has some tasks idle and one failing | — | **no fault on the crawler**: the failing task follows row 16 or its component's row | — | — | — | — | S-B49 (today g1 F4: all gathering paused, then "Recovered") |
| 50 | des | W0 Indexer / the video worker Working | no heartbeat for 15 minutes | its heartbeat | Warning, then a Trip after 3 tries | W0: W0 held. The video worker: new video copies queue | the video worker's heartbeat is *spec* | the same shape as 47 and 48, with its own name | Diag | built for W0 (g1 row 33); S-B50 for the video worker |
| 51 | des | Railway Working | a deploy failed, or has been building over 30 min | Railway's API | Warning (the old version keeps running) | nothing | it becomes a trip only if runs then fail (rows 47-48) | B "Railway: the crawler's deploy <id> failed at <time>; the old version is running." | — | S-B51 |
| 52 | des | Railway Working | Railway's API refuses our token (401) | its token check | Paused by the system at once (the watch can't read deploys) | Held: watching Railway's deploys only | — | B "Railway refused our token · Paused by the system: deploys aren't watched." Fix it → the card's Its key. P/E/Pu: the trip | at once | S-B52 (today g2 row 5: "Couldn't look", nothing raised) |
| 53 | des | Bluehost: page Working | the outside heartbeat gets no 200 (raised on GitHub's side, since the board lives on the host) | the heartbeat | a critical Trip at once (page) | **Held:** everything Bluehost hosts. **Unaffected:** Railway's services (their writes wait), GitHub, OneDrive | the incident is opened outside the host and finished when the site is back, from the gap in its readings | E/Pu from the heartbeat's own sender: "SYSTEM ALARM NOTICE: Page load: Down: the system is still trying" | Diag (heartbeat) | S-B53; O3 |
| 54 | des | the databases Working | a realm refuses, at the watch | each realm's query | a critical Trip at once (database) | Held: what reads that realm (listings: search and listing pages; users: sign-in; admin: orders and the admin) | its visitors' 5xx are filed under it, not under search | B/P/E "SYSTEM ALARM NOTICE: The listings database: Down: the system is still trying" · Pu the same | Diag | S-B54 (today g3 C14) |
| 55 | des | cron Working | the watch hasn't ticked in 10 minutes (seen from outside), or a critical job's cron line is missing | the outside heartbeat; `site:sched` | a critical Trip at once (procedures) | Held: every site job (a dead watch); or that job | — | E/Pu "SYSTEM ALARM NOTICE: The site's scheduled jobs: Down: the system is still trying" | Diag | S-B55 (today g3 J8: nothing raised anywhere) |
| 56 | des | cPanel's API Working | cPanel's API doesn't answer (email and pages fine) | its probe | Warning on **cPanel's API** (Bluehost's part), never "Page load" | nothing: jobs run on their existing lines | — | B "cPanel's API isn't answering: setting up jobs and mailboxes waits; the jobs themselves run." | Diag | S-B56 (today g3 C5: two false critical page trips, with the passing part as the title) |
| 57 | des | GitHub Working | the release look gets no answer (`found: -1`) | the look | a critical Trip at once (procedures, through releases) | **Held:** releases (builds wait in the inbox), the sync, new Railway deploys. **Unaffected:** pages, search, sign-in, payments, gathering | the system keeps trying at each 10-minute look | B/P/E "SYSTEM ALARM NOTICE: GitHub: Paused by the system (releases and the sync): still trying, 1 of 3, next at <time>" · Pu "SYSTEM ALARM: GitHub" / "Paused by the system (releases and the sync): still trying." (Jason, 2026-11-21: words follow the state, P9; the trying is a second clause, never the status) | Diag (the 10-min look) | S-B57 (manifest spec; today nothing is raised) |
| 58 | des | a token Working | it expires within 14 days | its expiry | Warning | nothing | — | B "GITHUB_SYNC_TOKEN expires Dec 16, 2026 (in 13 days). Replace it: https://github.com/settings/tokens" | — | S-B58 |
| 59 | des | a token Working | the token is refused | its read | Paused by the system at once | Held: what that token does (releases, watching the sync, or the health report) | Fix it → Its key, plus GitHub's token page | B "GitHub refused GITHUB_DOCS_TOKEN · Paused by the system: releases wait." · P/E/Pu: the trip | at once | S-B59 |
| 60 | des | the sync Working | a run failed, or is stuck over 15 min with its job finished | GitHub's runs | Warning, then a Trip after 3 tries | Held: the sync, and the mirror and OneDrive it keeps. Unaffected: releases, the site | Fix it → the run's page with Cancel | B "The sync's run #<n> failed at <time> · Trying again (1 of 3)." Trip: "…: Paused by the system" | Diag | S-B60 (today a Pulse case `ops:watch:*`, g2 R63) |
| 61 | des | the sync Working | no run an hour after a slot | GitHub's runs | Warning; **a Trip at 14 h with no run** | nothing (the next run catches up), until the trip | — | B "The 06:00 UTC sync didn't run; the mirror is behind since 03:19 UTC." | — | S-B61 (manifest W-sync-0621) |
| 62 | des | job Working | a site job serving no workflow fails | its declared check, else row 16's rule | Warning | **nothing**: only that job's work waits, and its next run still runs (P7) | — | B "Lead scores (job) failed at <time>: <error>. Its next run at <time> is its check." | Diag, else Sched | S-B62 (today g3 J1: trips with no try, "Paused by the system" with nothing paused) |
| 63 | des | job Working | a job tied to workflows fails | as 62 | Warning on the **job** | Held: nothing; **the workflows' tasks are unaffected** | — | B "Listings pipeline (job) failed: matching and publishing waiting listings waits. W1's crawler tasks carry on." | as 62 | S-B63 (today g3 J2, J3: W1 whole; only the first of three workflows) |
| 64 | des | job: Warning | its next run is **skipped** ("Paused: …") | a skipped run never passes | unchanged | — | — | — | — | S-B64 (today g3 J2's false recovery loop) |
| 65 | des | job Working | **late:** not started by its time plus 5 min | its cron line and the watch's own tick | Warning | nothing | — | B "<job> is late: it was due at <time> and hasn't started." | Diag | S-B65 (today g3 J7: missing) |
| 66 | des | job Working | **stuck:** started over 2 h ago and not finished | is it still running? did it finish? | **a fault, not slow**: Warning, then a Trip after 3 tries | Held: its own work only | **no automatic clear by age** (today a 2-day window closed it as "ended well", g3 J4, J5, L7) | B "Identity recheck (job): its run of Nov 20, 13:15 UTC never finished (killed or hung)." Trip: "…: Paused by the system: the recheck's work waits." | Diag | S-B66 |
| 67 | des | job Working | slow: over 3× its usual and over a minute | the run's time | Warning (Slow) | nothing | — | B "<job> is slow: its last run took <s> against its usual <s>." | — | S-B67 (built: g3 J10 matches; re-run against the model) |
| 68 | des | a critical job Working | `billing_tick` fails, or is stuck; `releases_tick` is quiet for 30 min | its check | a critical Trip at once **whatever the alarm's kind** (P8) | Held: its duty's work (renewals; releases) | the system keeps trying | B/P/E "SYSTEM ALARM NOTICE: Plans and renewals (job): Down: the system is still trying" · Pu the same | Diag | S-B68 (today g3 J6, J9, D2: warnings) |
| 69 | des | a critical duty Working | the search or sign-in test fails, or 3 server errors come in 5 minutes | the 5-minute test | a critical Trip at once; each probe is a try | nothing is held (it's what visitors use) | — | B/P/E "SYSTEM ALARM NOTICE: Search: Down: the system is still trying" · Pu the same / "Down since 14:05 MST: the system is still trying." | Diag | S-B69 (built (g3 C10, C11 match); the words are new (g4 row 4) re-run against the model) |
| 70 | des | payments Working | QuickBooks refuses our token, or it runs out | its token read | Paused by the system at once; waits for the person's Reconnect | Held: checkout's card payments | **Fix it → the card's Its key (Reconnect)** (today: none, g4 (b)) | B "Checkout can't take cards: QuickBooks needs reconnecting · Paused by the system." · P/E/Pu: the trip | at once | S-B70 |
| 71 | des | a component Working | **its pre-flight line goes red** (the nightly 2 AM run, at Start, or a `.env` change) | the line's check is the component's check | **the component's own fault** (rows 24-70 by its kind), never an alarm of the line's own | as that kind | — | as that kind | Diag | S-B71 (today g3 C1: a trip with zero tries and nothing paused) |
| 72 | des | a critical site line Working, **the master switch OFF** | `site:db` or `site:sched` goes red | its check | a critical Trip at once | as rows 54 and 55 | the master stops gathering only (R141) | as rows 54 and 55 | Diag | S-B72 (today g3 C3: a warning, nobody told) |
| 73 | des | an item switched on by Start | its gate fails at Start's watch (failures over 1 in 10, a new hold, out of budget) | the gate's reading | Paused by the system: **a hold record, the switch untouched** (CS-1) | Held: that item | — | B "W2: Paused by the system (Start's watch): 3 of its 10 runs failed since it came on. It resumes when its check passes." · P/E/Pu: the trip | Sched (Start's 10-min look) | S-B73 (today g3 C8: the switch written, "set by Founder", a board warning only) |
| 74 | des | task Working | it returns empty (12 or more runs in 24 h; a monthly load on its first) | the runs | Warning | nothing | — | B "<task> found nothing in 12 runs since <time>." | — | S-B74 (built: g2 R62; re-run against the model) |
| 75 | des | endpoint hop Working | received ≠ stored + updated + Review + refused, either way | the ledger | Warning on that hop | nothing | — | B "<endpoint>: received 2, accounted for 1. Its numbers can't be trusted until this is fixed." | — | S-B75 (today g2 R55: only "more"; L9) |
| 76 | des | process Working | cost per record goes past `control_cost_factor` × its 30-day usual | the ledger | Warning | nothing | — | B "<process>: <¢> a record, against its usual <¢>." | — | S-B76, once Q19 sets the factor |
| 77 | des | `site:pulse` Working | Pulse's gather time passes `control_pulse_ms` | its timing | Warning, board only | nothing | — | B "Pulse is slow to gather (2.4 s). The fix is the Pulse store: a communications build." | — | S-B77 (built re-run against the model) |
| 78 | des | a query timing Working | over its limit | Re-test | Warning (Slow) | nothing | — | B "<query>: <ms> against its limit <ms>." | — | S-B78 (built re-run against the model) |
| 79 | des | a part In maintenance | any fault on it | — | a Notice only | its maintenance holds stand | — | B "Notice: <part> (in maintenance until <time>): <what came back>." | — | S-B79 (built: R150; re-run against the model) |
| 80 | des | a trip opens | **no active person can receive it** (no founder, or every channel off) | the dispatcher's receiver check | the trip is shown on the board **and a critical Trip on the Communications Dispatcher** | Held: nothing | logged "reached nobody" on the trip (§7) | B "The Communications Dispatcher: a trip reached no one (no active founder) · Down." | at once | S-B80 (today g4 row 14: silent) |
| 81 | des | email Working | SMTP and SES both fail a send, or push keys are missing or a device is dead | the route's answer | a fault on that channel (the dispatcher's part): Warning, then a Trip; **the message falls back** to the next channel | Held: nothing (the other channels carry it) | logged "fell back to push" on the message | B "Email isn't sending (SMTP 535, SES refused) · Alarms go by Pulse and push." | Diag | S-B81 (today g4 (c): no fallback across channels) |
| 82 | des | email paused by message control | a Critical message | none | **sent anyway** (Critical can't be held) | — | the board's dispatcher row says "email paused for everything but Critical" | — | — | S-B82 (today g4 row 15: held) |

### Group C: The system's tries and the hour

| # | Basis | From | Event or action (who) | Guard: the real check | To | Hold: Held · On a backup · Unaffected | Side effects | Words: B · P · E · Pu | Clock | Test |
|---|---|---|---|---|---|---|---|---|---|---|
| 83 | des | Warning | a try fails (try n < 3, inside 60 min) | the component's check | Warning (n+1 of 3) | none | the try is logged with its call and answer, for the IR | B "Trying again (2 of 3): <answer> at <time>. Next check <time>." | Diag | S-C83 |
| 84 | des | Warning | a try passes | the component's check | Cleared | — | logged, with the proof | B "Cleared: <proof>." | Diag | S-C84 |
| 85 | des | Warning | the 3rd try fails, or the hour ends after a failed try | the component's check | Trip | the radius (§6) | the IR opens with the W id and the T id | §3c Trip | Diag | S-C85 |
| 86 | des | Warning | the hour ends **with no try made** (the check couldn't run: its own wait, or its host down) | — | **never a Trip on the clock alone** (P4); it stays a Warning that says why | none | — | B "No check could run in the hour: <reason>. Next check <time>." | Diag | S-C86 (today g1 row 2, g3 J1, C1: `expireTries`) |
| 87 | des | a critical part Working | its first failure | its check | **Trip at once**, still trying (P8) | nothing held unless its radius names work | — | B/P/E "…: Down: the system is still trying" · Pu the same (g4 row 4: never "Paused" with nothing paused) | Diag | built + S-C87 (the words) |
| 88 | des | Trip, critical, still trying | its tries run out | real tries, logged | Escalated | the radius, if any | **the Escalation Notice once** (R74) | B "Retries unsuccessful, now admin required: <part>" · P the same row, unread again · E a reply "SYSTEM ALARM NOTICE: <part>: Retries unsuccessful, now admin required" · Pu one push | Diag | built (g3 D4); S-C88 (with real tries only) |
| 89 | des | Trip, non-critical | its after-trip checks keep failing | the ladder | Trip, its status updated | unchanged | **the non-critical part gets the same Escalation Notice once its tries are out** (R74; today only critical ones do, g1 R74) | as 88 | Diag | S-C89 |
| 90 | des | Trip or Escalated | an after-trip check passes | its check | Closed: Recovered | released; dependants back on their first choice | the IR closes; CS-5 applies to anything still off | §3c Recovered; Escalated: "the Recovered notice goes to the Founders" (R75) | Diag | S-C90 (today g2 F3: never) |

### Group D: Every person's action

**Rule for all of them** (standard 2):
- Each action's guard is a real check. The table says what it may close and what it may not.
- A greyed button shows its reason as text beside it (R177).
- Every press is logged with who.
- Access is per 0171 §13 (R26).

| # | Basis | From | Event or action (who) | Guard: the real check | To | Hold: Held · On a backup · Unaffected | Side effects (what it may and may not close) | Words: B · P · E · Pu | Clock | Test |
|---|---|---|---|---|---|---|---|---|---|---|
| 91 | des | Warning | **Check now** (a person) | the component's check, at once | pass: Cleared. fail: it counts as a try | — | it may close **only this warning**, never a trip | pass: "Cleared: Jason's check passed: <proof>." fail: "Checked by Jason <time>: <answer> (2 of 3)." | Diag | S-D91 |
| 92 | des | Working | Check now | its check | pass: Working, its reading updated. fail: opens a fault (the first try) | — | — | "Checked <time>: <proof>." | — | S-D92 |
| 93 | des | Trip | **Check and start again** | **the faulted component's own check**, never the workflow's whole pre-flight (g1 F11), past its hold for that one request | pass: Closed (Fixed / Recovered, §3c). fail: row 94 | pass: releases **only this trip's hold**; what it held runs again | it may close only this trip and its correlated members if the same component; never a switch Off or Off by the system (left by a closed trip) (CS-3, CS-5, NG); it acts as the acknowledgement (R82) | rows 5 and 8's words | — | S-D93 (today g2 F1: 5 ms, no call) |
| 94 | des | Trip | as 93, the check fails | as 93 | Trip, its status updated; the button greyed until the system's next check | unchanged | — | "Checked <time> by Jason: still <answer>. The system checks again at <time>." | — | S-D94 |
| 95 | des | any | **Resolve** (today's word and API action), **Mark resolved** (`svcresolve`) | — | **removed**; an old Resolve address runs row 93 | — | Mark resolved is gone everywhere (R122), including Pulse's fallback step (`Pulse.php` L1293) | — | — | S-D95: grep finds neither |
| 96 | des | Trip | **Acknowledge** | none | Trip, Seen | unchanged | the light stays red; the escalation clock continues (Q12) | row 15 | Pulse | S-D96 |
| 97 | des | Warning | Acknowledge | — | **not offered** (R80) | — | — | — | — | S-D97 (built: `lines`; re-run against the model) |
| 98 | des | Closed by a person | **Undo**, within one day | none (it puts back what was) | Trip, with its hold placed again | as before the close | the thread reopens | B "Reopened by Jason: the trip is open again; <scope> waits again." · P unread again · E a reply "…: Paused by the system" · Pu one push (Critical) | — | S-D98 |
| 99 | des | On | **Pause** (a person) on a pausable part | none (stopping is never refused for a state, R147) | Off (a person's pause): "Paused by <name>: <why>" | its dependants reroute or wait, "Waiting on <part>: paused by <name>" | asks why (R143); raises nothing (R109); it doesn't close the part's open alarms | B "Off · Paused by Jason (testing a new key)." | — | built + S-D99 |
| 100 | des | Off (a person's pause) | **Resume** | its pre-flight (it isn't active now, R57) | pass: On · Working. fail: refused, saying why, with the fix in place | released | — | fail: "Not resumed: its check is red: <reason>. <fix button>" | — | S-D100 |
| 101 | des | Active | **Deactivate** (a person; a row's Shut down is this, Q14) | none | stage 7, Off | dependants move to the next source on the map ("Migrate traffic"), or wait, saying why | open **warnings** close "Stopped watching: deactivated by <name>" (no claim of a fix). **Trips stay open** until their check passes (P4) | "Deactivated by Jason (why). W8 now uses Brave." | — | S-D101 |
| 102 | des | Deactivated / Registered | **Activate** | stage 2's pre-flight passes | stage 3 → 5, On · Working | — | — | fail: "Not activated: <line> is red: <fix>" | — | S-D102 (built: R57; re-run against the model) |
| 103 | des | On | **Take out** (maintenance), a reason and Back by: **for any module, host, job, worker or part** (today workflows and tasks only, g3 M5) | none | In maintenance | Held: its dependants, saying "In maintenance: <part>, back by <time>" | its faults become Notices; Start skips it | B "In maintenance until 16:00 MST: <reason> (Jason)." | — | S-D103 |
| 104 | des | In maintenance | **Put back** | the part's own check | pass: On · Working. fail: stays out, with the reason | pass: dependants released | — | fail: "Not put back: its check is red: <reason>." | — | built for workflows; S-D104 for modules |
| 105 | des | In maintenance | Extend the window; a window set ahead goes out or comes back by itself; a Back by passes | Put back's check, when it comes back | as 103 and 104 | as 103 and 104 | a late start is logged with how late; a missed window is logged | logbook lines (built) | — | S-D105 (built: `r4a`; re-run against the model) |
| 106 | des | Paused (refused key) | **Replace** its key (the owner) | the key's own check, **past its own hold** (P7) | pass: Closed, Fixed. fail: Paused, with **Put the old one back** | pass: released | the old value is kept as `# OLD` until a pass forgets it | pass: "Fixed by Jason: replaced TAVILY_API_KEY. Its check passed: Tavily answered a search." fail: "Not fixed: Tavily answered 401 with the new key. [Put the old one back]" | — | S-D106 (today g2 F2: blocked by its own issue) |
| 107 | des | Off (no key) / Not checked yet | **Add its key** | the key's check (`.env`), or the worker's next report (Railway) | Working, or Not checked yet until the report | — | — | "Added by Jason. Its check passed: <proof>." or "…not checked yet: the worker reports at its next run." | — | S-D107 |
| 108 | des | a blocked site, or a ban | **Reactivate** | one polite request, **at once** (today it waits for W0's next read, g4 R120) | pass: Closed, Fixed. fail: stays | pass: released | — | "Fixed by Jason: <site> answered one polite request (200)." / "Still refusing: <answer>." | — | S-D108 |
| 109 | des | Trip (out of budget) | **Raise the cap** | use against the new cap | as row 41 | as row 41 | — | as row 41 | — | S-D109 (built, re-run against the model) |
| 110 | des | a part with no check of its own (rows 16, 62) | **It's fixed: try it again** | none now: its next run or call is the check | Warning "waiting for its next run" | unchanged | the next pass closes it as "Fixed by <name>: its next run worked"; a fail returns it to where it was | "Waiting for its next run at <time> (Jason said it's fixed)." | Sched | S-D110 |
| 111 | des | On | **Pause everything** (the emergency stop) | none | the master Off by a person; Railway's schedule stopped | Held: all gathering. **Unaffected:** business duties (R141): orders, billing, email, releases, the watch | open alarms are unchanged; **critical duties are still watched** (P8) | "Everything is paused by Jason (why). Business duties run." | — | S-D111 (built: `SystemStart`; re-run against the model) |
| 112 | des | Off | **Start the system** | the confirm phase: every workflow and task's dependencies re-tested fresh (never stored results), inside its time budget | each confirmed item On, in order, and watched (row 73) | an item that fails its check stays off, with its fix | refused on staging | the Start-up stage's lines (built) | Sched (its 10-min tick) | built + S-D112 |
| 113 | des | On | **Shut down** one process, or the whole system | none (never refused for a state) | Off, in Start's order reversed | dependants **named before it stops**; leases handed back; W0's batches and site jobs waited for | "stopping" until nothing is in flight, then "stopped, safe" with what it left | "Stopping W1 will hold: … In flight: 3 leased towns (handed back) …" then "stopped, safe" | — | S-D113 (today g3 M3 is partial) |
| 114 | des | Off by the system (left by a closed trip) | **Switch on** (CS-4) | the switch-on check for that part | On · Working, or stays, saying why | as row 9 | logged | row 9 | — | S-A9 |
| 115 | des | Off (a person's) | Switch on | the switch-on check | On · Working, or stays, saying why | — | — | "Switched on by Jason; its check passed." | — | S-D115 (built re-run against the model) |
| 116 | des | any stage | **Delete** | none | Retired (code remains) → Removed (code dropped) | its holds lift; dependants move to the next source | open alarms close as **Retired** (§3c); history, data and IRs are kept | "Closed: <part> was deleted by Jason; its history is kept." | — | built (Overpass) + S-D116 |
| 117 | des | Retired | **Bring it back** | — | stage 1–2 (it needs its pre-flight) | — | — | — | — | S-D117 (built re-run against the model) |
| 118 | des | Active | **Assign** or **Unassign** to a need or task | assigning runs that part's check for the need | stage 4 updated | the need's order changes, logged | — | "Brave now backs up Tavily for W8's search (Jason)." | — | S-D118 |
| 119 | des | any | **Log an entry** | — | — | — | a logbook row by the person | — | — | S-D119 (built re-run against the model) |
| 120 | des | Trip, a correlated member | **Pause only this task** | none | the task Off by the person | — | the incident stays open | "Off · Paused by Jason (only this task) during IR-0001." | — | S-D120 |

### Group E: Escalation on Pulse's clock

| # | Basis | From | Event | Guard | To | Hold | Side effects | Words: B · P · E · Pu | Clock | Test |
|---|---|---|---|---|---|---|---|---|---|---|
| 121·now | sim | Trip | the trip itself | — | the Pulse item's escalation at level 2 (the Director) at once | — | — | P "Escalated to the Director" at the minute of the trip | — | g1 R76; g3 C15 |
| 121 | des | Trip, Critical, open 2 h (seen or not) | Pulse's clock | the trip is still open | Pulse level 1: **the Manager** | unchanged | the Manager's own channels get a notice (R133) | P "Escalated to the Manager (open 2 h)" · E a reply to the Manager · Pu one push (a level rise) | Pulse | S-E121 |
| 122 | des | level 1, open 4 h | Pulse's clock | still open | level 2: **the Director** | unchanged | the same, to the Director | P "Escalated to the Director (open 4 h)" | Pulse | S-E122 |
| 123 | des | escalated at any level | its check passes | its check | Closed: Recovered | released | the clock stops; **the Recovered notice goes to the Founders** and to each person it was escalated to | §3c Recovered | — | S-E123 |

### Group F: Correlation

| # | Basis | From | Event | Guard | To | Hold | Side effects | Words: B · P · E · Pu | Clock | Test |
|---|---|---|---|---|---|---|---|---|---|---|
| 124·now | obs | two W1 task trips, 11 min apart, the same `TimeoutError` | — | — | two trips, two W1 pauses, two emails and two pushes; grouped as "engine feeds failing" with an outside back-off | W1 ×2 | — | two threads | — | L2, L3; g4 row 11; Lift (f) |
| 124 | des | Trip A open | a Trip B on the **same host** with the **same signature** inside the correlation window | the engine compares host, signature and time | B joins A's incident IR-#### as a member: one thread | each member's own radius, unioned | **one** email and **one** push for the incident | B "IR-0001 · Correlated · 2 parts: <A>, <B> · Paused by the system" · P one row · E a reply "…: <group>: Paused by the system" · Pu — (the level is the same) | — | S-F124; O1 (the window) |
| 125 | des | an incident with members | one member's check passes | its check | that member Closed; the incident stays open | its hold released | — | B "<B> recovered; <A> is still paused." · P updated · E — | — | S-F125 |
| 126 | des | the last member open | its check passes | its check | the incident Closed | released | the IR is complete | §3c Recovered, for the group | — | S-F126 |
| 127 | des | open alarms of one **cause** (not one board system) | more than one alarm shares a cause or incident | — | one group row on the board and one Pulse item; each member keeps its own buttons, shown in the group's opened row (standard 3) | — | grouping by board system and family ("feeds") is removed (Lift (f)) | B "<group words naming the cause>: 3 parts" | — | S-F127 |

### Group G: Routing to the provider's page, by fault kind (PL)

| # | Basis | From | Event or action (who) | Guard: the real check | To | Hold | Side effects | Words: B · P · E · Pu | Clock | Test |
|---|---|---|---|---|---|---|---|---|---|---|
| 128·now | obs, sim | a provider quota trip (Gemini's 429 RESOURCE_EXHAUSTED) | Fix it | — | Fix it lands on **our own** cap, `#svc-gemini-caps`, which "doesn't lift Google's hold"; no provider page | unchanged | — | B "…: Over its quota or plan (the site, on Bluehost)"; Fix it → Caps and paces | — | g2 row 6; `ServiceHealth::fixOf` |
| 128 | des | a quota, budget or credit warning or trip | Fix it, or the card opened | — | the card shows "On our system" (cap and pause actions) and "At <Provider>": the **billing** link | unchanged | — | B "At Anthropic: [Open Anthropic (Claude) billing ↗] then [Check now]" · P the same two rows · E the body's link line "Billing at Anthropic: <url>" · Pu — | — | S-G128: for each of rows 36, 38, 39, 40 the link is of type billing, or the note |
| 129·now | sim | a refused key | Fix it | — | Its key on our card, with Replace ready; no link to the provider's keys page. A Railway key lands on a card that can't check it | unchanged | — | B "…: Our key was refused…"; Fix it → `#svc-<k>-key` | — | g2 rows 1-2, §2 |
| 129 | des | key or auth (rows 29, 31, 52, 59, 70) | Fix it | — | Its key on the card, with "At <Provider>": the **keys** link, then **Replace key**, then its Check. A key kept on Railway routes to Railway's **Keys** slot, never its Console (Jason, 2026-11-21): while no Railway keys page is registered, the slot reads "No API keys page registered for Railway yet" with "Publish from your Vault", and Fix it says so rather than borrowing another link | unchanged | — | B "At Anthropic: [Open Anthropic (Claude) API keys ↗] → [Replace key] → its Check" · P the same · E the link line "API keys at Anthropic: <url>" | — | S-G129 |
| 130·now | sim | ban, rate, server, timeout or malformed | the card | — | "their trouble": no Fix it, no provider link | — | — | B "The system is handling it" | — | g4 (b); `Thread::FIX` |
| 130 | des | ban, rate, server, timeout or malformed (rows 24-28, 32-34, 42) | the card | — | no Fix it (still their trouble, R118); "At <Provider>": the **console** link, to inspect its health; the board's [Check now] beside it (not on a 429 in its wait) | unchanged | — | B "At Tavily: [Open Tavily console ↗]" · P the same | — | S-G130 |
| 131 | des | an endpoint or host fault (rows 19-23, 47-61) | the card | — | "At <Host>": the host's **console** link (Railway's Dashboard, GitHub's run page from the alarm's evidence). Bluehost has none by the label rule: its note | unchanged | — | B "At Railway: [Open Railway console ↗]"; Bluehost: "No console page registered for Bluehost yet" (O5) | — | S-G131 |
| 132 | des | any other kind | the card | — | the **login** link | unchanged | — | B "At <Provider>: [Open <Provider> sign-in ↗]" | — | S-G132 |
| 133 | des | the routed type has no URL | the card | — | the note in words: "No <billing / keys / console / sign-in> page registered for <Provider> yet". **Never another type's page, and never silent** | unchanged | the module's coverage line (§8) reads "not registered yet" | B and P show the note where the link would be | — | S-G133: remove each type in turn; the note appears and no other type's URL is used |
| 134 | des | any | a portal action pressed (a person) | none | **no change on the board**: the same state, holds and alarm | unchanged | logged "Opened Anthropic billing (Jason)"; the verifying board action is shown next | — | — | S-G134: every state and hold row is unchanged after the press |
| 135 | des | after a portal action | the verifying board action (Check now, Check and start again, Replace key and its Check) | that action's own guard | as that action's row (91, 93, 106) | as that row | — | as that row | — | S-G135 (= S-D91, S-D93, S-D106) |

**Counts:** 135 numbered transitions, written as 155 rows: 136 designed rows (135, plus 5b) and 19 rows of today's
behaviour (·now) beside their designed rows.

### 4.8 Copy pairing: today's words beside the new, on every surface

Jason (2026-11-21): "pair today's live phrasing with the new wording, the same on the board, Pulse, email and push."

**How to read it:**
- **Today** is quoted from the snapshot (obs), the probes (sim) or the code (inf), with its audit source.
- "nothing" means no words exist today: the path is missing.
- "not recorded" means step 2 didn't capture the words. Step 4 reads them on staging before replacing them.
- **New** gives the one title that every surface shares, then what each surface adds: **B** the board and the card,
  **P** Pulse, **E** the email (its subject is `SYSTEM ALARM NOTICE: <title>: <action>`) and **Pu** the push (its title
  is `SYSTEM ALARM: <title>`, its body starting with the action).
- A warning has no P, E or Pu.

| # | Today: board · Pulse · email · push | New: one title; B · P · E · Pu |
|---|---|---|
| 1, 24 | B "index.commoncrawl.org: Its server failed (the crawler, on Railway)"; status "Calls to index.commoncrawl.org wait a little and try again by themselves, longer each time (1 of 6)." (sim, g2 row 16) | "Common Crawl's index isn't answering". B "Trying again (1 of 3): it answered 504 Gateway Time-out at 07:06 MST. Next check 07:11 MST." |
| 2, 26, 85 | B "Its back-off ran out (6 failures in a row): calls to index.commoncrawl.org are held until an admin resolves it.", beside "It closes as Recovered when its check passes" · P/E "SYSTEM ALARM NOTICE: Common Crawl: Paused by the system" · Pu "SYSTEM ALARM: Common Crawl" / "Paused by the system (a trip) since …" (obs L4; sim g4 row 9c) | "Common Crawl's index isn't answering". B "Paused by the system (a trip) since 07:41 MST. Held: calls to Common Crawl's index. Unaffected: W3 and every workflow. Next check 07:51 MST." · P the same, unread · E "SYSTEM ALARM NOTICE: Common Crawl's index: Paused by the system" · Pu "SYSTEM ALARM: Common Crawl's index" / "Paused by the system since 07:41 MST. Nothing else waits on it." |
| 3, 46 | B title "Its gate: 1 red line(s): Common Crawl (W3) (Held: Issue #35…)"; "W3 won't start until this is fixed; the rest of the system runs." · P/E "SYSTEM ALARM NOTICE: W3 Sources and the place store: Paused by the system" · Pu "SYSTEM ALARM: W3 Sources and the place store" (obs L1) | No alarm and no message for a workflow. B on W3's row: "Working. Its backup source Common Crawl's index is paused (IR-0007); nothing in W3 uses it." When every task waits: "W3: every task waits on <part> (IR-…)." |
| 4, 89, 91 (on a trip) | nothing (no check runs after a trip, sim g2 row 22). Check it now: "No check to send" (sim g2 row 14) | B and P, in place: "Checked 09:42 MST: still 504. Next check 10:12 MST." · E, Pu — |
| 5, 93 | Notice "Success: Resolved by Jason: Checked and started again Started again: W3 (its tasks wait with it). It's in the logbook." · P the item gone · E, Pu nothing; the message stays "SYSTEM ALARM NOTICE: W3 Sources and the place store: Paused by the system", unread (obs + sim, Lift (a), (e)) | B "Recovered: Common Crawl's index was down 7 h 21 min; Jason's check found it working: the index answered a real query (a lookup for <URL>, 1 record, 412 ms)." · P the same, as its last note · E a reply "SYSTEM ALARM NOTICE: Common Crawl's index: Recovered" · Pu the shown push replaced silently: "Recovered." Plus CS-5's Still off line wherever it applies |
| 5b, 94 | "Error: Not resolved at HH:MM UTC by Jason: W1 (its tasks wait with it): Not started: its check is red. What holds it: …" (sim, Lift case B) | B and P "Checked 14:02 MST by Jason: still 504 Gateway Time-out. The system checks again at 14:32 MST." Button greyed "Its check is red (504 at 14:02 MST)" · E, Pu — |
| 6 | B "Resolved by the system: Cleared by its check: It answered the site's check again (… UTC): the crawler's calls start again", closed inside another press; no thread update (sim, Lift (b)) | Only its own check closes it: B "Cleared: Common Crawl's data host answered its check (a read of <object>, 2xx, 230 ms)." (a warning, so nothing is sent) |
| 7, 114 | "left off by a trip that has closed (…). The board switched it off before trips paused instead, and **nobody had set this switch before**, so it stays off. Switch it on there when it's ready." Shown for every such switch, never read from the log (obs: Jason quoted it; `ControlBoard.php:357`) | "Off by the system (left by a closed trip): left off by W3's trip of Nov 20 (closed Nov 21). No one has switched it on or off by hand, so it waits for you. [Switch on]". The same words on B (the task's row and the closed trip's row), P (the closing note) and E (the closing reply); Pu "Recovered. 2 switches still off: open to switch on." (CS-5) |
| 8 | "**Started again: W3**" while no W3 task can run (sim, Lift (d)) | "Closed. Resumed: <what the hold held>. Still off: Read a source (W3), Common Crawl (W3): [Switch on]." |
| 9 | not recorded | pass: "On · Working: switched on by Jason, its check passed: <what>." fail: "Not switched on: <need> has no working source (<reason>)." (board only) |
| 10 | the thread stays "SYSTEM ALARM NOTICE: <part>: **Paused by the system**", unread, after any person's close (sim, Lift (e); g4 row 7) | Every close updates every channel: P the last note · E a reply `…: Recovered` / `…: Fixed by <name>` / `…: Closed` · Pu the shown push replaced silently |
| 11, 16, 17, 18 | B "W1: its task overture_bulk failed (run #1290)… the workflow's other tasks carry on", then "Its tries ran out at 09:31 UTC with no fix: it's a trip for an admin" · P/E "SYSTEM ALARM NOTICE: W1 Listings: Paused by the system" · Pu "SYSTEM ALARM: W1 Listings" (obs L2) | Our endpoint: "/api/crawl/loads is slow". B "It didn't reply to the crawler within 120 s for 500 rows at 01:31 MST. The system is checking it." A task's own error: "<task> failed in its own code (run #N): <error>. No check can run before its next run at 02:00 MST." (board only) |
| 12 | a second "SYSTEM ALARM NOTICE: W1 Listings: Paused by the system" email and push (obs L3) | B "…seen 2 times (01:20, 01:31 MST)." No message |
| 13, 20 | nothing (our endpoints have no check, g1 F2) | B "Cleared: /api/crawl/loads answered its check in 0.4 s (01:36 MST)." |
| 14 | summary "Records: 0 in, 0 stored, 0 refused." (obs) | the summary counts the night's real runs |
| 15, 96 | the light turns green, "**No alarm open**", while the pauses hold (sim, g4 row 3) | B "● 1 trip (seen by Jason): …", red · P note "Seen by Jason 08:05 MST" |
| 19 | B "/api/crawl/loads is slow: It took 236 ms a record, against its usual 8.0 ms…"; under 20 calls of history, nothing (sim, g1 rows 7, 9) | "/api/crawl/loads is slow". B "It took <s> for <n> rows against its usual <s>" (or "against its limit <s>") |
| 21, 22 | B "/api/crawl/loads answered a server error (500)" · E "W1 Listings: Paused by the system" (sim, g1 row 24) | "/api/crawl/loads keeps failing". B warning "Trying again (1 of 3)"; trip "Paused by the system (a trip): 3 tasks wait on it." · P/E "SYSTEM ALARM NOTICE: /api/crawl/loads: Paused by the system" · Pu "…" / "Paused by the system: 3 tasks wait on it." |
| 23 | nothing (`site_blocked` is read by nothing, g1 row 26) | "Bluehost's bot check refused the crawler". B "Paused by the system at <endpoint>. Ask Bluehost to exempt <path>." · P/E/Pu the trip's |
| 25 | "Its back-off ran out (2 failures in a row)" after a success in between (sim, g2 row 19) | B "Cleared: <module> answered (<what>)." |
| 27, 28 | B "…: Asked us to slow down"; a SlowDown reported as `server` reads "Its server failed" (obs L12); a wait that ended stays "Its wait ended …: the next call tries it again" for days (obs L11) | "<module> asked us to slow down". B "Calls wait until <time>, then start again by themselves." Then "Cleared: <module> answered its check after its wait (<time>)." |
| 29, 31 | B "api.anthropic.com: Our key was refused (the site, on Bluehost)"; "**Nothing could be paused from here (it only trips)**", while the card says "Paused by the system" · E "SYSTEM ALARM NOTICE: Anthropic (Claude): Paused by the system" (sim, g4 row 9a) | "Claude (AI) refused our key". B "Paused by the system: calls with ANTHROPIC_API_KEY wait for a new key." · P/E "SYSTEM ALARM NOTICE: Claude (AI) refused our key: Paused by the system" · Pu "SYSTEM ALARM: Claude (AI) refused our key" / "Paused by the system: its key was refused." |
| 30 | "TAVILY_API_KEY on Railway: not reported by the crawler yet", red (sim, g1 row 21) | "TAVILY_API_KEY on Railway: not checked yet (the worker reports at its next run).", grey |
| 32, 33, 34 | "Refused us" · "Nothing calls … until this is resolved"; Check it now "No check to send"; no probe, so no "looks lifted" (sim, g2 row 14) | "<module> refused us". B "Paused by the system. The system checks once a day for 3 days; after that it's your call." Then "its ban looks lifted (probe 200 at <time>). [Reactivate]", or "still refusing after 3 days." · P/E/Pu the trip's, with P's row updated |
| 35 | "N sites blocked us" (built, matches) | unchanged |
| 36, 37 | "…: Over its quota or plan (the site, on Bluehost)" · "Nothing calls … until this is resolved", still showing 26 h later (sim, g2 row 6) | "Gemini used its day at the provider". B "Paused by the system until 00:00 Pacific; its check runs then. On our system [Check now] · At Google: [Open Google AI Studio (Gemini) billing ↗] or "No billing page registered…"" Then "Recovered: Gemini was held 9 h until its day turned; its check passed." |
| 37a | "Gemini's free tier lets Google use what it's sent, so only public pages go to it (decisions/0164 §0.8); this feature carries a person's own words, which go only to Claude, with their consent (R5), so it stays on Claude." (obs, `Gemini::refused()`), returned to the caller only | "Kept on Claude: this carries a person's own words (nothing private to Gemini's free tier)" (a Notice on Gemini's card; no message) |
| 38 | nothing: the credit 400 is recorded nowhere (sim, g2 row 7) | "Claude (AI) is out of credit at Anthropic". B "Paused by the system. Add credit at Anthropic, then press Check and start again. On our system [Pause our calls] [Check now] · At Anthropic [Open Anthropic (Claude) billing ↗]" · P/E/Pu the trip's |
| 39 | title "**Anthropic (Claude) at 50%**", body "67%" (obs L5; `Operations.php` L605) | "Claude (AI): $27.06 of $40.00 (68%)", with the body saying the same numbers, "past its 50% warning". On our system [Raise the cap] [Lower the cap] [Pause our calls] [Check now] · At Anthropic [Open Anthropic (Claude) billing ↗] |
| 40, 41, 109 | "<name> is out of budget" · "Raise the cap, or pause it…" · E "SYSTEM ALARM NOTICE: <name>: Paused by the system"; Recovered as built (inf, g2 row 8) | "Tavily: $20.00 of $20.00 (100%)". B "Paused by the system until our cap is raised or the month turns." Then "Fixed by Jason: raised our cap for Tavily to $25.00. Its check passed: $20.00 of $25.00 (80%)." Never "raised at Tavily" |
| 41a | "<workflow> has used its <n>% share of the AI cap (…): more on the 1st, or raise its share on the Workflow board." (obs, `AiClient::shareLeft`), shown only where a feature is refused | "<workflow> has used its <n>% share of the AI cap ($<x> this month, with last month's unused part): its Claude features wait until the 1st." (board only, on that workflow) |
| 42 | "Nothing could be paused from here (it only trips)" (sim, g1 row 19) | "<backup> isn't answering". B "…Nothing waits on it: its need's first choice is working." |
| 43, 44 | "Notice Tavily is down: W1 is on its backup (Brave)", only from a stored line; the real switch isn't logged (sim, g4 row 8; g2 row 27) | B Notice "<task> is on its backup (Brave) while Tavily is paused (IR-…)", then "Back on its first choice: Tavily." |
| 45 | "Paused by the system: <task> (<first workflow only>)" (sim, g1 row 18) | B on each held task's row, in every workflow: "Waiting on <need>: no source works (<A> paused, <B> paused)." |
| 47, 48 | B "A crawler run never reported (#N)", then gone · P only "The crawler has stopped" (`Pulse.php` L371) (sim, g1 rows 30-31) | "The crawler is down". B warning "hasn't reported since <time> · Trying again (1 of 3)", then trip "Paused by the system: all gathering waits." · P/E "SYSTEM ALARM NOTICE: The crawler is down: Paused by the system" · Pu the same title |
| 49 | B "A crawler run failed (#N)" · E/Pu "The crawler: Paused by the system", then "Recovered: The crawler was down 1 min, the system fixed it" (sim, g1 F4) | no crawler words; the task's own row (16 or its component's) |
| 50 | W0: built (matches). The video worker: nothing | "<worker> hasn't checked in since <time>", in rows 47-48's shape |
| 51, 52 | nothing; a refused token reads "Couldn't look: HTTP 401" (sim, g2 row 5) | "Railway: the crawler's deploy <id> failed at <time>; the old version is running." (board only) · "Railway refused our token": "Paused by the system: deploys aren't watched." (the trip's words on every surface) |
| 53, 54, 55 | nothing raised anywhere; `site:jobs` reads "None stopped in the last day" (sim, g3 J8, C13, C14) | "Page load" / "The listings database" / "The site's scheduled jobs": "Down: the system is still trying" on P, E and Pu, sent from outside the host for 53 and 55 |
| 56 | B title "**Bluehost: The site's email: signed in.**"; light "Tripped: stopped until an admin resolves it." · Pu "SYSTEM ALARM: Bluehost · Paused by the system" (sim, g3 C5) | "cPanel's API isn't answering". B "setting up jobs and mailboxes waits; the jobs themselves run." (board only) |
| 57 | nothing (`found: -1`, obs) | "GitHub: Paused by the system (releases and the sync): still trying, 1 of 3, next at <time>". Pu "Paused by the system (releases and the sync): still trying." (Jason, 2026-11-21) |
| 58, 59 | not recorded | "GITHUB_SYNC_TOKEN expires Dec 16, 2026 (in 13 days)…" / "GitHub refused GITHUB_DOCS_TOKEN": "Paused by the system: releases wait." |
| 60, 61 | a Pulse case `ops:watch:*` (inf, g2 R63); a missed slot raises nothing | "The sync's run #<n> failed at <time>", then "…: Paused by the system" · "The 06:00 UTC sync didn't run; the mirror is behind since 03:19 UTC." |
| 62 | "SYSTEM ALARM NOTICE: **Lead scores: Paused by the system**" (nothing paused); "1 of 3 tries" beside "(2 in a row)"; then "Recovered: Lead scores was down 61 min, the system fixed it" (sim, g3 J1) | "Lead scores (job) failed". B "at <time>: <error>. Its next run at <time> is its check." (board only) |
| 63, 64 | "SYSTEM ALARM NOTICE: **job:listings_tick**: Paused by the system"; "W1 (its tasks wait with it)"; then "Recovered: job:listings_tick was down 1 min, the system fixed it" on a skipped run (sim, g3 J2) | "Listings pipeline (job) failed". B "matching and publishing waiting listings waits. W1's crawler tasks carry on." A skipped run changes nothing |
| 65 | nothing (red text on Scheduled tasks only, inf g3 J7) | "<job> is late: it was due at <time> and hasn't started." |
| 66 | B "The job "identity_recheck_tick" never finished · …it was killed, or it hangs"; 2 days later "**Its last run ended well**" (obs L7; sim E1) | "Identity recheck (job): its run of Nov 20, 13:15 UTC never finished (killed or hung)"; trip "…: Paused by the system: the recheck's work waits." |
| 67 | "The job … is slow · Its last run took N s against its usual M s" | unchanged, with the job's plain name |
| 68 | billing stuck: board only. Billing failed: "SYSTEM ALARM NOTICE: Plans and renewals: **Paused by the system**", then "Retries unsuccessful, now admin required" after no retry (sim, g3 J6, J12) | "Plans and renewals (job)": "Down: the system is still trying"; the Escalation Notice only after real tries |
| 69, 87 | B "Tripped at once … a critical part. The system is still trying…" · P/E/Pu "<part>: **Paused by the system**", with nothing paused (sim, g4 row 4) | "Search": "Down: the system is still trying" on B, P, E and Pu; Pu "Down since 14:05 MST: the system is still trying." |
| 70 | "SYSTEM ALARM NOTICE: Checkout and payments: Paused by the system", closing as "Recovered: … the system fixed it" after a person reconnected (sim, g3 C12) | "Checkout can't take cards: QuickBooks needs reconnecting": "Paused by the system". It closes as "Fixed by <name>: reconnected QuickBooks. Its check passed: <proof>." |
| 71 | "SYSTEM ALARM NOTICE: Tavily: Paused by the system" with nothing paused; the button "Its line on the pre-flight" for an active module (sim, g3 C1) | the component's own words by its kind; the button "Its line in Diagnostics" |
| 72 | B "Part of the site itself isn't ready … Nothing switched on needs it yet: Start holds it until it passes." Nobody told (sim, g3 C3) | as 54 and 55: "Down: the system is still trying" |
| 73 | Paused now: "set by **Founder**"; board warning "Starting the system: …" (sim, g3 E3) | "W2: Paused by the system (Start's watch): 3 of its 10 runs failed since it came on. It resumes when its check passes." |
| 74, 77, 78, 79 | built; the words are kept | unchanged, with plain names |
| 75 | "its numbers on the board can't be trusted" for "more" only (obs L9) | "<endpoint>: received 2, accounted for 1. Its numbers can't be trusted until this is fixed." |
| 76 | nothing (missing) | "<process>: <¢> a record, against its usual <¢>." (once Q19 is set) |
| 80 | nothing, and nothing logged (sim, g4 row 14) | "The Communications Dispatcher: a trip reached no one (no active founder)": "Down" |
| 81 | a `mail.failure` message in Pulse, reaching people by the same failing email (inf, g4 (c)) | "Email isn't sending (SMTP 535, SES refused) · Alarms go by Pulse and push." |
| 82 | "Paused by message control: email" for a Critical message (sim, g4 row 15) | sent anyway; B "email paused for everything but Critical" |
| 83, 84 | "Status: Warning since 14:13 UTC, seen 1 time"; "1 of 3 tries" frozen (sim, g4 row 1; g3 J1) | "Trying again (2 of 3): <answer> at <time>. Next check <time>." / "Cleared: <proof>." |
| 86 | "Its tries ran out at HH:MM UTC with no fix: it's a trip for an admin", with no try made (sim, g1 row 2) | "No check could run in the hour: <reason>. Next check <time>." |
| 88 | the Escalation Notice as built; the email body adds "(2 of these are waiting.)" (sim, g4 row 5) | "Retries unsuccessful, now admin required: <part>"; no "of these are waiting" |
| 90, 123 | services: never; the close goes through as "Resolved by the system" with no Recovered (sim, g2 row 20) | "Recovered: <part> was down <n> min, the system fixed it." on B, P and E; Pu replaced silently |
| 92 | not recorded | "Checked <time>: <proof>." |
| 95 | "Mark it resolved? Calls to it…" (`svcresolve`; `Pulse.php` L1293's fallback) | removed everywhere |
| 97 | built (no Acknowledge on a warning) | unchanged |
| 98 | "Undone: the trip is open again, and W1 … stopped again." (sim, Lift case A) | "Reopened by Jason: the trip is open again; <scope> waits again." P unread again; E a reply; Pu one push |
| 99, 100 | not recorded | "Off · Paused by Jason (testing a new key)." / "Not resumed: its check is red: <reason>." |
| 101 | a row's Shut down logs "stop … shut down" (inf, g3 R149) | "Deactivated by Jason (<why>). W8 now uses Brave." |
| 102, 104, 105, 111, 112, 115, 117, 119 | built; the words are kept | unchanged |
| 103 | modules can't be taken out (sim, g3 M5) | "In maintenance until 16:00 MST: <reason> (Jason)." |
| 106 | Check after Replace: "**Held by issue #n**" (sim, g2 F2) | "Fixed by Jason: replaced TAVILY_API_KEY. Its check passed: Tavily answered a search." / "Not fixed: Tavily answered 401 with the new key. [Put the old one back]" |
| 107 | "Off · No key: X isn't in .env" (inf, g2 row 4) | "Added by Jason. Its check passed: <proof>." / "…not checked yet: the worker reports at its next run." |
| 108 | Reactivate: one try at W0's next read, with no answer at once (inf, g4 R120) | "Fixed by Jason: <site> answered one polite request (200)." / "Still refusing: <answer>." |
| 110 | "It's fixed: try it again" (built) | "Waiting for its next run at <time> (Jason said it's fixed)." |
| 113 | "stopped, safe … every switch off, with who and why" (sim, g3 M3) | "Stopping W1 will hold: … In flight: 3 leased towns (handed back) …", then "stopped, safe" |
| 116 | Retire's words, as built | "Closed: <part> was deleted by Jason; its history is kept." |
| 118 | nothing (no assign action) | "Brave now backs up Tavily for W8's search (Jason)." |
| 120 | the task trip's "Pause the task" (inf, g1 row 1) | "Off · Paused by Jason (only this task) during IR-0001." |
| 121, 122 | P "**Escalated to the Director**" at the minute of the trip (sim, g3 C15) | P "Escalated to the Manager (open 2 h)", then "Escalated to the Director (open 4 h)" |
| 124, 125, 126 | two threads: two emails and two pushes (obs L2, L3) | "IR-0001 · Correlated · 2 parts: <A>, <B>": one thread, "Paused by the system"; "<B> recovered; <A> is still paused." |
| 127 | "**3 engine feeds failing**", with only "Acknowledge all 3" (sim, Lift (f)) | "<the cause in words>: 3 parts", each member's buttons in the opened row |
| 128–135 | Fix it for a provider's quota → our own cap; for a key → our card only; their trouble → no link (sim, g2 row 6, §2) | "At <Provider>: [Open <Provider> billing / API keys / console / sign-in ↗]", then the verifying board action; or "No <type> page registered for <Provider> yet" |

### 4.9 The step-4 test mapping

Each designed row names its scenario (S-A1 … S-F127). Step 4's scenario test (PLAN step 4; R180, R189) causes each one
on staging and asserts the same five things:
1. **State:** the part's run-state and the alarm's state after the event, read through `state()` and the alarm row.
2. **Hold:** exactly the Held, On a backup and Unaffected sets in the row's Hold column, and nothing wider; no switch
   value changed (CS-1).
3. **Guard:** the check ran and called what the row names (an outside request is logged, or a stand-in host records
   it). A close without that call fails the test (NG).
4. **Words:** each surface's words equal §4.8's New column (title, status, action), and the numbers shown agree with
   each other (BL).
5. **Messages:** the counts of Pulse rows, emails and pushes are as §7 says for that state; a close updates all three.

The scenarios group into suites, one per group:

| Suite | Scenarios | What it stands up | Built on (existing tests, re-run against the model) |
|---|---|---|---|
| **lift** | S-A1–S-A15 | Jason's starting state of 2026-11-21 (`step3/lift/boot.php`), then each press of §4 group A | `step3/lift/*.php` probes |
| **faults** | S-B16–S-B82 | each kind of failure, by stand-in host and stand-in endpoint answers; the worker's reports replayed | `holds`, `backoff-gate`, `r5a-rate`, `critical`, `sensor`, `itsdata`, `census-backup`, `card-issue`; the g1–g4 probes |
| **clock** | S-C83–S-C90 | a controllable clock: tries at about 5, 15 and 35 min, the hour, the ladder | `expire-tries` (rewritten: no trip without a try) |
| **actions** | S-D91–S-D120 | each button pressed with its check red and then green; each switch read through CS-3's three cases | `paused`, `paused-now`, `keys`, `r4a`, `r2b`, `lines`, `one-panel` |
| **escalation** | S-E121–S-E123 | Pulse's clock moved forward 2 h and 4 h | `notify` |
| **portal** | S-G128–S-G135 | every paid or keyed module × the fault kinds; each link type removed in turn | `dispatcher-tests/r3` (Fix it routing) |
| **correlation** | S-F124–S-F127 | two failures with one signature, 11 minutes apart | none (new) |
| **words** | every row's §4.8 New column | for every scenario, the board, the card, Pulse, the email and the push compared cell by cell | `dispatcher-tests/r1`, `r2`, `r2-notice`, `r3` |

Step 4 also needs the test schema step 2 found missing: every board table, since their migrations ran on live and were
deleted (step 1 §7).


---

## 5. The unified engine

### 5.1 One library, its API (names only)

The library is `ControlEngine`, with the dispatcher (`Notify`) as its only way out.

| Call | What it does |
|---|---|
| `fault(part, evidence)` | Any caller reporting a failure. The engine runs `classify`, finds or opens the component's alarm, and schedules its tries. It is the only way an alarm is raised |
| `ok(part, evidence)` | Any caller reporting a real success. It closes a back-kind warning and resets its tries (row 25) |
| `classify(evidence) → (component, class, kind)` | Moves classifying out of the raisers: our reply's timeout is the endpoint's slowness; SlowDown is a rate wait; a credit 400 is a quota; a DuckDB host is its Source. The worker's own kind is a hint only |
| `check(part, by) → result` | Runs the part's declared check past its own hold for that one request, and records what was called, the answer and the proof |
| `tries(alarm)` | The diagnostic clock: when the next try is due, and the count |
| `radius(part, fault) → {held, on_backup, unaffected}` | §6, from the manifests |
| `hold(scope, cause)` / `release(hold, by: check)` | Places and lifts hold records. `release` needs a passing check result |
| `state(part)` | The run-state with §3b's precedence, read from the switches (read-only), holds, maintenance, faults and the log (§0) |
| `trip(alarm)` / `escalate(alarm)` / `close(alarm, how, check)` | The alarm's transitions (§3c). `close` refuses without a passing check, except for Retired and the "stopped watching" of row 101 |
| `act(person, action, target)` | Every person's action, with its guard (group D) |
| `correlate(alarm)` | Joins a trip to an open incident (group F) |
| `incident(alarm)` | Opens IR-#### and appends to its timeline |
| `words(subject, surface)` | Generates the thread's six parts for a surface (5.3) |
| `notify(thread, state)` | Hands each state change to `Notify` (§7) |
| `tick()` | The watch: tries due, after-trip checks, silence, lateness, Pulse's clock, the CS-3 settle, the dispatcher's readiness |

### 5.2 Every caller raises only through it

| Caller | It calls | Instead of today's |
|---|---|---|
| `CrawlService::report` (the crawler's report: tasks, runs, issues, `site_blocked`, `search_switches`, keys) | `fault` / `ok` per call outcome, each with its component | `task:failed:<task>` and `run:failed` raises; `ServiceHealth::fromReport` trusting the worker's kind |
| `ControlLedger::finish` (each endpoint call) | `fault` / `ok` on the endpoint part | `ControlLedger::alarms` (5xx, slow, count), filed under the first workflow by `procOfEndpoint` |
| `ServiceHealth` (the site's own callers: `seen`, `siteFailed`) | `fault` / `ok`; `allowed()` / `held()` read `state` | `failed`, `answered`, `resolve`, `alarm`, `spentHold`, `holdForTrip`, `tripHeld`, `liftRateTrips`, `holding`, `CASE_AFTER` |
| `Preflight` (each line) | `check` | `Preflight::alarm` (its own level and words, L337, L351); `refreshFor`'s side-effect closes |
| `Operations` (caps, empties, W0's blocked sites, the Railway and sync watch) | `fault` / `ok` | `Operations::alarms`, `watchTick`'s `ops:watch:*` Pulse cases; `gateFor` becomes `radius` |
| `ControlSensor` (search, sign-in, payments, Pulse's time) | `fault` / `ok` | `checkOne` / `watch` raising directly |
| `Scheduler` (each job's end), and the watch's job section | `fault` / `ok` per job | `ControlAlarms::tick`'s job rows (`event=false`, the 2-day window, `slow` for stuck) |
| `SystemStart` (Start's watch) | `hold` through `fault` | `pauseItem` writing `crawl_workflows.paused` |
| The workers (heartbeats) | `ok` per heartbeat; silence through `tick` | `silent:*` raises in `tick` |
| The outside heartbeat (GitHub) | its own sender for page, database and cron down, then `fault` when the site answers again | nothing (missing) |

### 5.3 What it replaces, and what's removed

**Replaced, in place:**
- **`ControlAlarms`:**
  - `raise`, `tripped`, `trippedEarly`, `escalated` and `recovered` become `trip`, `escalate` and `close` with a check;
  - `resolve`, `close`, `closeKey`, `clear` and `clearOthers` become `close(alarm, how, check)`;
  - `stop` and `affects` become `radius` and `hold`;
  - `expireTries` and `tick` become `tries` and `tick`;
  - `facts`, `noticeTitle`, `noticeBody`, `statusLine`, `partName`, `pulseBody`, `stoppedWords` and `fixOf` become
    `words`, with Fix it routes from the manifest;
  - `notifyOne` and `mail` become `notify`;
  - `counts` counts open trips, seen or not (g4 F1);
  - `groups` and `groupKey` group by cause or incident (row 127);
  - `settle0175` becomes CS-3.
- **`ServiceHealth`:** the parts listed in 5.2. `classify` moves to the engine. `facts`, `fixOf` and `waitWords` become
  `words`. `PROBES` comes from each manifest's `check`.
- **`Automation`:** `sysPause` and `sysResume` become hold records written only by the engine. `putBackTripSwitches`
  becomes CS-3. `setTask` is never called with "the Control Board (a trip)".
- **`ControlBoard`:** `stopOne` no longer writes a switch for a trip. `pausedNow` and `pausedHtml` read `state` and
  `words`; their fixed "nobody had set this switch before" goes (CS-6).
- **`ControlMaintenance::canTake`:** extended to every part (row 103).
- **`Thread::FIX`:** becomes the manifest's `fix_it_routes`.
- **`Notify`:** Critical skips message control (row 82); the "(N of these are waiting.)" line goes for `IN_PLACE`
  threads (g4 F8); a channel can fall back to another (row 81).

**Removed** (the split rules the audit found):
- each raiser's own `means` and `todo` text (`CrawlService.php` L261 "other tasks carry on"; `ServiceHealth.php` L353
  "held until an admin resolves it");
- `hold_until = 2099-01-01` as a trip;
- `event=false` tries and the "1 of 3" count that freezes;
- `expireTries` tripping with no try made;
- the 2-day job window's false clear;
- `escalateTo(…, 2)` at a trip;
- Mark resolved and `svcresolve`;
- the Use backup button (Q17);
- `run:failed` from idle runs;
- the Pulse case "The crawler has stopped" (`Pulse.php` L371), which becomes row 47;
- the `ops:watch:*` Pulse cases, which become rows 51 and 60;
- `mail.failure` as a Pulse message, which becomes row 81;
- the board-system grouping "engine feeds".

### 5.4 Words come from state

`words(subject, surface)` reads only:
- the part's plain name (manifest `name`; a job's from `Scheduler::JOBS`; never `job:`, `wf:` or `svc:`);
- the alarm's state, ids and readings;
- the hold records (for "What it affects", Held / On a backup / Unaffected);
- the clock (Status: since when, the next check, with its zone);
- the switches, read through the log (§0);
- the last check's proof.

**A raiser passes evidence, never sentences.** The notice's action word follows what the holds hold:
- "Paused by the system" only when a hold exists;
- "Down: the system is still trying" for a critical part with nothing held;
- "Retries unsuccessful, now admin required" after real tries;
- "Recovered", "Fixed by <name>", or "Closed".

Every surface asks for the same subject and gets its part of the thread (R103): the push gets the title and status;
Pulse all but Details; the board and the card all six parts; the logbook the finished story. So the board, the card,
Pulse and the email can't disagree (g4 F2; L10). Each time it's drawn, the title is redrawn from the current kind and
reading (L5, L6, L12).

---

## 6. The Incident Radius

**How it's computed** from the manifests (§8), for a fault on component C:
1. **C's own scope.**
   - A Source or Service server: its calls, for the caller that failed (the site, the crawler, or both when the probe
     fails for both).
   - A key: the calls that use that key.
   - An endpoint: the tasks whose `endpoints` include it.
   - A job: nothing beyond its own work (P7).
   - A worker or host: everything whose `hosted_by` is C.
2. **Each task T** whose needs name C, or whose `calls` include it:
   - if the need has a later source whose state is Working → T is **On a backup**;
   - else T is **Held: waiting on C**.
   - A task that doesn't use C is Unaffected, even in the same workflow.
3. **Each workflow:** Held whole only if every task is held. "Partly working" if some are. Otherwise Working or On a
   backup.
4. **Duty:** if C carries a duty, it's critical (P8), and the duty's dependants (from `feeds`) are Held.
5. **Unaffected** = every registered part minus Held minus On a backup, listed by system. Today there's no Unaffected
   side anywhere (g4 (d)).

**Worked cases:**

| Fault | Held | On a backup | Unaffected | Today |
|---|---|---|---|---|
| **One task error** (`second_look` fails in its own code) | nothing (its next run is its check; row 16) | — | W2 whole and everything else | W2 whole (g1 row 1) |
| **Our endpoint slow** (`loads.php`, IR-0001) | nothing: a warning on the Site part (Q3) | — | Railway, every source, the bulk tasks' next runs | W1 whole twice (L2, L3) |
| **Our endpoint 5xx** (3 tries fail) | `overture_bulk`, `foursquare_bulk`, `geofabrik_bulk` | — | W1's other tasks, every other endpoint, the site | W1 whole, then a deadlock (g1 row 24) |
| **Common Crawl down** (the data host; the index too) | W3's `commoncrawl` task's reads, if its need has no working later source | the need "sources" on its next source, where the map has one | W1, W2, W4–W14, W0, the site | W3 whole (L1); today the index host alone holds nothing the crawler calls (row 2) |
| **Railway down** (no service runs or reports) | everything it hosts: the crawler (all gathering in W1–W12), W0, new video copies (they queue) | — | pages, search, sign-in, payments (Bluehost); releases (GitHub); everything stored | barely shown (g1 F5) |
| **GitHub down** (its API doesn't answer) | releases (builds wait in the inbox), the sync and the mirror, new Railway deploys | — | pages, search, sign-in, payments, gathering, the workers on their current versions | nothing raised (manifest spec) |
| **A critical duty down** (search's test fails) | nothing more (search *is* the duty; no hold can help it) | — | everything else; told at once, still trying | matches (`sensor`), apart from the words |
| **The listings database realm refused** | search and listing pages; the endpoints writing listings (their tasks wait) | — | sign-in (users realm), the admin, orders | seen only nightly (g3 C14) |

---

## 7. Notification states (email, Pulse and push only)

**The defaults:**
- Who: the Founder for a trip; then Pulse's clock (R76).
- Channels: Pulse, email and push (0176 §4).
- No SMS (0171: "no text messages"; no income, no expense) and no webhooks, per Jason's correction.
- The body on every channel says what's going on, what it means and what to do, with the link (R130).
- Alarm emails drop " · Traversence".
- One email thread per alarm or incident (a stable Message-ID, In-Reply-To, References).
- One push tag per alarm or incident, alerting again only when the level rises or for Critical (R131).

| Alarm state | Who | Pulse | Email | Push | Updates in place? |
|---|---|---|---|---|---|
| Warning, Notice | nobody | — | — | — | the board and card only |
| Trip opens | the Founder, at once, in quiet hours too (Critical) | a new row, Critical: `SYSTEM ALARM NOTICE: <item or group>: Paused by the system` (or `Down: the system is still trying`) | a new thread, the same subject, in the same minute, with `/admin/dashboard/stream#case=alarm:<id>&fix=1` when it has a Fix it | `SYSTEM ALARM: <item or group>`, body "Paused by the system (a trip) since <time>. <n> wait, <m> on a backup." | — |
| A check after the trip | — | the status line in place ("Checked 09:42 MST: still refused. Next check 10:12 MST.") | — | — | yes |
| Seen (Acknowledge) | — | a note "Seen by <name>" | — | — | yes |
| A correlated member joins | the same people | the row's title becomes the group | a reply, its subject with the group | — (the same level) | yes |
| Escalated (tries out) | the same | `…: Retries unsuccessful, now admin required`, unread again | a reply, the same subject | one push, `renotify` | yes |
| Pulse's clock: Manager, Director | that person | the same row, escalated | a message to that person by their channels | one push to that person | yes |
| Closed: Recovered | the Founder; the Founders and the escalated people if it was escalated | the last note: "Recovered: …", plus CS-5's Still off | a reply, `…: Recovered` | — | yes |
| Closed: Fixed by a person | the same | the last note: "Fixed by <name>: …" | a reply, `…: Fixed by <name>` | — | yes (today never, g4 row 7) |
| Closed: Retired | the same | the last note: "Closed: deleted by <name>" | a reply | — | yes |
| Reopened (Undo) | the same | unread again: `…: Paused by the system` | a reply | one push | yes |
| **Nobody can receive it** | — | — | — | — | the trip's row says "Reached no one: <why>", logged; the dispatcher trips (row 80) |
| **A channel's own failure** | — | the message carries "Fell back to push (email failed: <answer>)" | — | the fallback | the dispatcher's channel part faults (row 81) |
| Staging | nobody | held and logged | held and logged | held and logged | (R134) |

**Recovered updates all three.** The Pulse row and the email reply change at once. The push already shown on the phone
is replaced in place under its tag with the Recovered words, silently.

**The Communications Dispatcher is a Service module** (critical, procedures):
- its channels: Pulse, email (Bluehost SMTP, then SES) and push (VAPID);
- its sensors: queue depth, failures per channel, "reached no one", Critical held, keys present, founders without a
  device;
- its Fix it routes to `/admin/config/communications#cs-<sender>` and `#cs-controls`.

Everything above comes from g4 (c).

---

## 8. The manifest contract (short: the cards come later)

One manifest per module, listed once here and the same for every module. It extends `service-defs.php` (0173 §4: one
definition, the code's lists generated from it, and the zip check refuses a difference). The lists that live elsewhere
today (`OutsideServices::CONTROLS`, `Preflight::NEEDS`, `ControlRegister::CRITICAL`) move into it.

| Field | What the state rules read it for | Today |
|---|---|---|
| `id`, `name`, `company`, `purpose`, `contact`, `notices`, `ledger`, `staging` | plain names in `words`; the staging gate | exists |
| `role` (Host · Source · Service) | `radius` | missing |
| `criticality` (a duty, or none) | P8 | partial |
| `stage` (the eight, with history) | §3a | partial (seven) |
| `level`, `touch_points`, `repair` | the zip check; which repair Fix it offers | missing (0175 R4) |
| `plan`, `caps`, `paces` | rows 39-41 | exists |
| `hosted_by`, `hosts`, `feeds` (with the cadence per assignment), a task's `needs` and `endpoints` | `radius` (§6) | partial |
| `required_keys` (name, where it lives, its check) | rows 29-31 | partial |
| `sensors`, `check`, `probe`, `answers` (how its answers classify, and its reset time) | `classify`, P4, P7, rows 27, 32, 36, 38 | missing (`PROBES` is empty) |
| `fix_it_routes` | Fix it (R117) | partial (one generic map) |
| **`portal_links`**: per type `billing`, `keys`, `console`, `login`, each `{url, label, type}` | PL's routing (group G) | missing as a field |
| **`setup_capture`**: `sign_in` (a slot or why), `keys` (each `{name, place}`: `.env` · Railway · GitHub secret · Vault; never a value), `unregistered` (each slot type with its one-line why), `captured_at`, `by` | stage 1's exit guard (§3a); the card's setup notice | missing as a field (Jason, 2026-11-21) |
| `correlation` (signature fields), `incident_radius_rules` (rare overrides) | group F; `radius` | missing |

**`portal_links` values come only from `service-defs.php`'s company link lists**, mapped by their labels:
- a label with Billing, Usage or Limits → `billing`;
- Keys, Key, Credentials or API settings → `keys`;
- Dashboard or Console → `console`;
- Account → `login`.

Nothing is invented. Coverage on the live record (`37a212e`), for every paid or keyed module:

| Module | billing | keys | console | login |
|---|---|---|---|---|
| Cloudflare | Billing | not registered yet | Dashboard | not registered yet |
| Amazon Web Services | Billing | The site's key (IAM) | Console | not registered yet |
| Anthropic (Claude) | Usage; Limits | Keys | Console | not registered yet |
| Google Cloud | Billing | Credentials | Console | not registered yet |
| Google AI Studio (Gemini) | Rate limits (a docs page: see O5) | Keys | not registered yet | not registered yet |
| Tavily | not registered yet | not registered yet | Dashboard | not registered yet |
| Brave Search | not registered yet | not registered yet | API dashboard | not registered yet |
| Railway | Usage | not registered yet | Dashboard | not registered yet |
| Bluehost | not registered yet | not registered yet | not registered yet ("cPanel" isn't a mapped label) | Account |
| QuickBooks (Intuit) | not registered yet | not registered yet | not registered yet | not registered yet ("QuickBooks", "Developer app" aren't mapped labels) |
| Stripe | not registered yet | not registered yet | Dashboard | not registered yet |
| Square | not registered yet | not registered yet | not registered yet | not registered yet ("Developer apps") |
| Name.com | not registered yet | API settings | not registered yet | not registered yet |
| Fly.io | Billing | not registered yet | Dashboard | not registered yet |
| GitHub | Billing | not registered yet | not registered yet ("The sync") | not registered yet |
| Supabase | Usage | not registered yet | Dashboard | not registered yet |
| openrouteservice (HeiGIT) | Plans and limits | not registered yet | Dashboard | not registered yet |
| U.S. Census Bureau | not registered yet | Key sign-up | not registered yet | not registered yet |
| AZ 511 (ADOT) | not registered yet | Developers (key) | not registered yet | not registered yet |
| Foursquare (via Hugging Face) | not registered yet | not registered yet | not registered yet | not registered yet |
| Recreation.gov (RIDB) | not registered yet | not registered yet | not registered yet | not registered yet |
| National Park Service | not registered yet | not registered yet | not registered yet | not registered yet |
| Pixabay | not registered yet | not registered yet | not registered yet | not registered yet |
| Microsoft | not registered yet | not registered yet | not registered yet | not registered yet |
| Apple | not registered yet | not registered yet | not registered yet | Developer account |
| Meta (Facebook) | not registered yet | not registered yet | not registered yet | not registered yet |
| 1Password | not registered yet | not registered yet | not registered yet | not registered yet |

**The four zones of the card** (report §1) read these fields:
1. Identity and role;
2. Capabilities and feeds;
3. Sensors and key readiness;
4. Actions and Fix it, with PL's two rows, "On our system" and "At <Provider>".

The card's existing parts are kept and placed in their zones. The cards and mockups come in the next step-3 document.

---

## 9. Requirements coverage

| Requirements or root cause | Satisfied by |
|---|---|
| R1–R26 (views, layout) | §2.2 parts and roll-up; §3b lights and precedence; Paused now's groups (§0 CS-2, rows 7 and 15). **The views and mockups themselves are the next step 3 document** |
| R27–R39 (register, lifecycle) | §2.1, §3a, §8; rows 101-102, 116-118 |
| R40–R64 (sensors, checks, pre-flight) | §2.7; rows 19-21, 47-61, 65-78; P4 |
| R65–R85 (alarms, tries, trip, escalation) | §3c; groups C and E; rows 1-2, 83-90, 121-123; P3, P8 |
| R86–R99 (holds, backups, recovery) | §2.4, §6; rows 22, 26, 42-46, 73, 90; §0 for R98 |
| R100–R113 (words, lights) | §5.4; §3b; every row's Words column; P9 |
| R114–R124 (Fix it, routing) | §8 `fix_it_routes`; rows 23, 29, 38, 52, 70, 106; CS-4 |
| R125–R139 (who is told) | §7; P10, P11; rows 80-82 |
| R140–R156 (switches, start-up, shut-down, maintenance) | §0; §2.5; rows 73, 99-115 |
| R157–R166 (services, keys, caps) | §8; rows 29-41, 106-109 |
| R167–R175 (history, Incident Reports) | §2.6; P12; rows 2, 85, 124-126 |
| R176–R189 (phones, build rules) | not state rules: step 3's views and step 4's tests; every designed row names its scenario (R180, R189) |
| Root cause 1: tries on the wrong clock | P3; group C; rows 2, 11, 24, 62, 86 |
| Root cause 2: holds too wide, holds that can't lift | P6, P7; §6; rows 3, 11, 22, 45-46, 49, 63-64 |
| Root cause 3: recovery isn't a check | P4; §2.7; rows 5, 6, 13, 28, 37, 93, 106 |
| Root cause 4: hosts and endpoints aren't parts with a role | §2.1-2.2; §8 `role`; rows 11, 19-23, 47-57 |
| Root cause 5: words written by raisers | P9; §5.4; rows 3, 7, 8, 29, 39 |
| Root cause 6: the channels lose the thread | P10, P11; §7; rows 10, 15, 80-82, 124 |
| Root cause 7: critical duties fail unseen | P8; rows 53-55, 68, 72 |
| Jason's checks (2026-11-21) | closed trips and switches: §0 CS-1–CS-6, NG; rows 7·now, 7, 8, 9, 10, 114. The Basis column on every row; a named step-4 test on every designed row (§4.9). Copy pairing: §4.8. The percentage and provider pages: BL, PL, rows 39·now, 39, group G, §8's coverage, O5 |

**Deliberately left out, and why:**
- SMS and webhooks: Jason's corrections to the report.
- The **Use backup** button: the system reroutes by itself (Q17).
- Mark resolved, and Resolve's "What fixed it?": "this should not be an option, it should be the result of an issue
  being fixed" (R122).
- `control_slow_trips` and the "third trip in 24 hours" guard: replaced (step 1 §5).
- The board's views, mockups and kept/moved/dropped list: the next step-3 document (the plan), not the state model.
- Account apps' own lifecycle beyond "one model, two levels": 0175 §1; their hooks are built when the first integration
  needs them (R38).
- Uploads' threads (R139): already on the thread shape and outside the board's states.

---

## 10. Open items

All answered by Jason on 2026-11-21 (§0a). What's left to supply, not to decide:
- **Provider pages for the priority modules**, from Jason's Vault: billing, keys, console and login for Railway, GitHub,
  Anthropic, Google, Tavily, Brave Search, Bluehost and QuickBooks first. The full gap list is in §8's coverage table.
- **IR-0001's root cause** (handler or response path) still needs live data (`../step2/ir-0001.md`). It doesn't change
  the model: row 11 holds either way.
