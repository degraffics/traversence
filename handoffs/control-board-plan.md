# The control board: one build, built right (plan, 2026-11-21)

Written by the session manager at Jason's request (2026-11-21: "I want a plan that ensures one build, built right, and
achieves what I want and need"), after case #51 (W1 paused whole for one task's timeout). Draft for Jason's review.

## Why this plan

Jason has been finding the board's broken pieces himself: Paused now unclear, a down backup holding W3, Fix it landing
on the wrong page, and W1 paused whole by one task. Each was fixed alone. Each fix was right for its own lines. None
asked whether the board as a whole does what Jason asked for:
- The board's rules (what a failure holds, how it retries, when it trips, what it says, where Fix it lands, how it
  recovers) are written separately in at least five places: `ControlAlarms`, `CrawlService`, `Preflight`,
  `Operations` and `ServiceHealth`.
- The work has been spread over four decisions (0171, 0173, 0175, 0176) and more than 30 releases (#192 to #247), by
  several sessions.
- I reviewed each release against the lines it cited, not against the whole.

## What Jason wants and needs (his words; the plan's test)

- A control room: "where an operator can see every sensor and status of the entire … operation" and control it
  (0171, 2026-11-18).
- "Start to finish system management and control. Organized by how an operator can see and understand each system and
  everything within its process" (0171, revision 1).
- "Any trip or issue (slow, retry, crash) produces an alert"; "down time is bad"; routing "fast, efficient, and
  accurate" (0171).
- The system fixes first. A trip stops only what it holds. Every alarm carries its fix (0171 §5, 0175 §3, the
  playbook §2–3).
- "Still struggling to get a clear picture of how well the system is performing" (2026-11-20): the board answers that
  at a glance.
- Not having to find the broken pieces himself (2026-11-21).

## The plan: five steps, one session, one release

### Step 1. One requirements list (no code)

Every requirement Jason has given for the board, in his words, from 0171, 0173, 0175 and 0176 with their dated notes,
the Pulse and Paused now notes, and his answers in the hand-offs. One line each: the requirement, its source, and how it
will be tested. Anything that conflicts or is unclear becomes a question, never a guess.

### Step 2. An audit of what's live against that list (no code)

Every requirement gets one of: works, partial, broken or missing. Each grade is proved on the live mirror and on
staging, not taken from a hand-off. It includes:
- **The failure map.** Every way anything can fail: a task fails, a call is slow, a source is down, a key is refused, a
  quota or budget runs out, our site errors, the worker is down, a job is late or silent, or a check fails. Each is
  checked against every kind of part: workflow, task, service, job, worker and critical duty. For each pair: what it
  holds, how it retries, when it trips, what it says (on the board, in Pulse, by email and by push), where Fix it lands,
  and how it recovers. The audit records what the code does now beside what the decisions say it should do.
- **Every open alarm and case on the live site**, read for wrong holds, wrong words and dead ends.
- **Every page of the board** at 1280px and 390px, with every button pressed once on staging.

This is where the session finds the broken pieces, so Jason doesn't have to.

### Step 3. One design, reviewed by Jason once

From steps 1 and 2:
- **One failure map:** the table above, every cell sourced.
- **One engine:** a single library that applies the map. Every caller (crawler reports, pre-flight, service checks,
  jobs and the worker) raises through it. The five separate versions are removed.
- **The board's views,** answering his questions in order: is the system healthy, what's held and why, what needs me,
  then each system and process down to its data.
- **What's kept, moved or dropped** from what's live, with the reason for each (the playbook: build on what's there).

- **Visual mockups** (Jason, 2026-11-21: "I want visual mockups too"):
  - every board view at 1280px and 390px, as rendered pages;
  - filled with real data from the live site, never invented, so each mockup shows today's actual state;
  - a page showing one case per kind of failure: the board, the Pulse row, the email and the push, then Fix it and
    recovery;
  - Jason's own `design/control-board-mockup.html` is the starting reference.

Jason gets one document, with the mockups: the requirements, the audit's findings and the design. He approves it, or asks for changes,
once. Nothing is built before that.

### Step 4. The build, as one release

- One session builds it, start to finish. No other session touches the board's files until it's live.
- **A scenario test** causes every cell of the failure map on staging, for every workflow, task, service and job. It
  checks the result against the map: what's held, the alarm, its words on all four channels, Fix it, and recovery.
  Every cell must pass.
- Rendered checks of every board view at 1280px and 390px.
- Sent with `"review": true`. Jason sees it on staging, with the scenario results, before Publish.

### Step 5. Live, then a week of watching

- The scenario test runs again on staging at every release that touches the board, so a later change can't quietly
  break it.
- A daily consistency check on live: each open alarm's hold, words and Fix it match the map. A mismatch is a case for
  the building session and me, never for Jason.
- Each trip gets its Incident Report (0175's note of 2026-11-21).
- After a week, the session manager reports to Jason what tripped, what recovered by itself, and any mismatch found.

## While the plan runs

- No one-off board patches. Case #51's causes go into the map, step 2.
- No live patches, not even for harm (Jason, 2026-11-21, after the live-harm lift went badly). A live-site action
  happens only on Jason's explicit call. The end-to-end standard in `CLAUDE.md` governs every step.
- Releases outside the board (Orders, Design and trips) carry on as before.
- The session manager reviews each step against the whole requirements list, not only the lines a step cites.

## Who

- The Operations session (session_01CXLi5jC5aZcEZtdMWtd4Gb) owns it, because it holds the board's recent context.
- If it nears its limit, it hands off at the end of a step, never mid-step.
- Its held items (W6/W8 "Follow 0163", Q4, the Incident Report plan) fold into steps 1–3.
