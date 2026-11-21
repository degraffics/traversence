# What the control board is

Written by the Operations session at Jason's request (2026-11-21: "explain what the control board is and what
components it uses to manage the system"). The session manager reviewed it against 0171, 0175's "What Jason asked
for" and `design/control-board-mockup.html`.

## 1. A dashboard over a plant

Jason gave two pictures, and they fit together.

**The power plant's control room:** "an operator can see every sensor and status of the entire … operation", with
"start to finish system management and control. Organized by how an operator can see and understand each system and
everything within its process. Each gauge holds the entire process."

**The car's dashboard:** "it shows your actual driving data and can be used to monitor and adjust performance based on
data readings, the actual component like gas tank is where the gas lives … You don't fix the dashboard to fix the gas
tank."

So the board reads and adjusts. It doesn't hold the parts. Each part lives in its own place: a service's key lives on its
card, a workflow's tasks live with the workflow, a job lives in the scheduler. When something needs Jason, the board
brings that part to him in a drawer, a pop-up or a side panel, right where he is. In his words: "I dont want multiple
pages where one thing affects and another and I have to go hunting through places to find how to solve the one start to
end process. The board should provide me direct action capabilities to fix issue, by using, drawers, pop-ups,
side-panel to bring me the component or tool to fix the issue at hand."

**Day to day,** he glances at it and sees green: each system's light, its numbers (records in, stored, refused), its
spend against its limit, and its stage. The board does the watching.

**When something goes wrong,** the system tries first. If it can't fix it, it pauses only the broken part, tells him
once, and hands him the part to fix.

## 2. What it oversees: the four sections of his mockup

1. **The Knowledge Engine:** the crawler's core workflows, which find and keep what Traversence knows. They run in six
   phases: wide discovery and classification; contextual extraction; entity resolution, vetting and privacy; the
   master catalog, graph and index; publishing, serving and access; then lifecycle and verification.
   - W0 Indexer logs every web address found.
   - W1 finds businesses and organizations. W2 keeps them current.
   - W3 keeps the sources we read and the place store.
2. **Workers and pipelines:**
   - W4–W14: place guides, guides on demand, heritage, journeys, Find it, landmarks, events, how-to guides, search
     learning, stories and authority sources;
   - the machines on Railway that run them: the crawler, W0 Indexer and the video worker;
   - the release jobs.
3. **Outside services:** every company we call, such as Common Crawl, the Census, Wikidata, Gemini and Claude, Google,
   QuickBooks and Amazon's email. Each has its keys, its limits and its spend. The source priority map shows, for each
   need, our own copy first, then outside services in order, and what happens if none works.
4. **The site itself:** the databases, scheduled jobs, staging, the sync, and what people feel at once: pages, search,
   sign-in and payments.

Every process has a route. A task runs on a worker, which calls a source and sends what it found to our site. The site
then stores it, updates it, sends it to Review or refuses it. The board knows each route, so it shows where something
stopped, and checks that what came in adds up to what went out.

## 3. Every service is a module, with eight stages

A service is like a module or plug-in. Each one passes through Jason's eight stages:

1. **Selection and configuration:** chosen, with its settings and keys.
2. **Integration (pre-flight):** checked against the system before it's trusted: its keys, its reach and its limits.
3. **Activation:** switched on.
4. **Assigned to workflows:** the workflows and tasks it supports are named.
5. **Operational:** real-time monitoring, performance, diagnostics and reporting.
6. **Maintenance:** taken out on purpose, with a reason and a "back by" time.
7. **Deactivation:** switched off by a person.
8. **Removal:** gone from use. Its history is kept.

**The actions, and who presses them:**
- **Activate:** a person.
- **Pause:** the system or a person.
- **Deactivate:** a person.
- **Delete:** a person.

A fault doesn't switch anything off; it pauses. "Like a faulty headlight or dead battery, one can be changed while the
system is running another might need the system shut down to replace or repair." A pause lifts by itself when its check
passes, and the system never changes a person's switch.

**The same model carries the platform.** The App store and its apps are modules too. A module is either standalone (a
self-contained solution) or integrated into the core components, and integration follows strict procedures.

## 4. What the board uses to oversee them

- **Six stages on every system and every process,** as on his mockup: **Pre-flight, Start-up, Observation, Shut-down,
  Maintenance, History.** The same controls work on the whole system or on one process. Start-up brings things on in
  order. Shut-down stops them in the reverse order, safely, with nothing left half-done.
- **The register:** the board's map of every system, process and module, and what each one uses.
- **Sensors, and the query timings and speed gates:** every call and run is measured (how long it took, what came in,
  what was stored or refused), and each key query is timed against its limit.
- **Alarms:**
  - a **warning** means the system is handling it: something is slow, retrying, near a limit or on a backup;
  - a **trip** means the system couldn't fix it, so it paused only what the fault touches, and a person is needed.
- **The Unified Pulse Alarm Queue:** one item per problem, acted on in place, closed by itself when fixed. A trip reaches
  him once and at once: the board's light, the Pulse item, one email and one push.
- **Fix it:** one button on each alarm, which brings the part to him in a drawer, a pop-up or a side panel.
- **The maintenance calendar:** windows set ahead, each with a reason and a "back by" time.
- **History:**
  - every reading kept over time, so each part has a trend and an uptime;
  - the logbook: who did what, and why;
  - reports for the whole system or one part;
  - an Incident Report for every trip.

## 5. One example: a source goes down at 2 AM

Take Common Crawl: an outside module, operational (stage 5), and assigned to W3's tasks (stage 4).

1. **2:00 AM.** A W3 task asks it for data and gets no answer. The gauge's needle moves: the failed call is recorded.
2. **The system tries first.** It waits and asks again. The module shows amber, a warning. Where the source priority map
   names a backup, the work uses it and says "On a backup". Nobody is woken.
3. **Its tries run out, so it's a trip.** The system **pauses** the module; it isn't switched off. Only the tasks that
   need it wait. The rest of W3, and every other workflow, keep running, like a car still driving on one faulty
   headlight.

   *Today's code does the opposite here: case #51 paused all of W1 for one task. This build fixes that.*
4. **Jason is told once:** one item in the Pulse queue, one email and one push: "SYSTEM ALARM: W3 Sources and the place
   store". It says "Paused by the system", with what's held and what to do.
5. **In the morning,** the board's top line answers three questions: is it healthy, what's held and why, and what needs
   me. He presses Fix it, and Common Crawl's own card opens in a side panel on the board. He doesn't go to another page.
6. **Recovery.**
   - If it came back by itself, its check passes, the pause lifts, and the item closes as Recovered.
   - If it needs a part changed, he does it right there. That might be a new key, or switching to the backup. If the
     repair needs it shut down, he takes it into maintenance (stage 6) with a "back by" on the calendar, then puts it
     back.
7. **History:** the logbook shows each step, the historian counts the downtime, and the Incident Report records what
   happened, what it held, how long it lasted, and how it was fixed.

The board is the dashboard; the modules are the parts. A fault is held only as widely as its cause. The system fixes it
where it can, and brings it to Jason, with its fix, where it can't.
