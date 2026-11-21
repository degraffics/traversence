# Control board, step 1: your 22 questions (2026-11-21)

From `step1-requirements.md`: 189 requirements gathered from everything you've said about the board (0171, 0173, 0175,
0176 and their notes, the hand-offs and your mockup). Where two of your rules disagree, or nothing decides a point, it
became a question. Each has a recommendation.

Reviewed by the session manager. I checked the cited lines against the decisions, and each recommendation against your
dated notes. Answer with "all as recommended", or name the numbers you want changed.

## A. These shape the failure map (they decide what goes wrong today)

1. **A night-only task's tries (case #51).** You chose "keep the hour" on 2026-11-20. A task that only runs at night
   can't try again within the hour, so it trips after one try.
   *Recommended:* keep the hour for everything that can retry within it. A night-only task counts its tries by its own
   runs: "Trying again tonight, 1 of 3".
2. **What one task's failure holds.** Today it pauses the whole workflow (W1).
   *Recommended:* only that task. A workflow pauses only when all its tasks are paused, or the workflow itself fails.
3. **Our own site timing out or erroring when the crawler calls it.** Today it counts against the outside source.
   *Recommended:* our timeout is "slow" (a warning, nothing paused). Our 5xx error is a fault on our Site part, never
   blamed on the source.
4. **A Railway key the crawler hasn't reported yet.**
   *Recommended:* amber, "not checked yet", holding nothing. Red only when it's reported missing or refused.
5. **When a light is red.**
   *Recommended:* a row is red for an open trip. The summary lights are red only for a critical part down. Bluehost
   down is critical. Railway down pauses gathering. GitHub down holds releases only. No "red holds everything".
6. **A site that refused or banned us passes its check later.**
   *Recommended:* it waits for your Reactivate ("looks lifted"), never reopens by itself. A refused key or a used-up
   quota closes when your fix passes its check.

## B. Who is told, and how

7. **A non-critical trip.**
   *Recommended:* one message at the trip. "Escalated" means Pulse's clock: Manager at 2 hours, Director at 4. The
   "Retries unsuccessful, admin required" notice is only for critical parts.
8. **A critical part's first failure pauses nothing, so is it a "trip"?**
   *Recommended:* call it "Critical part down": red, told at once on all three channels, with its own Incident Report.
   It becomes a trip when its tries run out.
9. **Who hears of a trip at once.**
   *Recommended:* you, by email, push and Pulse. The system's Manager and Director see it in their Pulse queue at once,
   and are messaged only on escalation.
10. **Failed Railway deploys and stalled syncs.**
    *Recommended:* alarms on the board under the same rules, not separate Pulse cases.
11. **Several alarms from one cause.**
    *Recommended:* one message for the group ("5 engine feeds held"), as your format "(Item or Group)" says.
12. **Acknowledge.**
    *Recommended:* none on warnings. On a trip it means "seen by me": logged with who, doesn't close it, and doesn't
    stop escalation.

## C. Smaller rules

13. **Gemini's free day used up.** *Recommended:* the work waits until tomorrow. No paid Claude fallback for a used-up
    day.
14. **A person's quick stop on the board.** *Recommended:* Shut down is a Pause (Resume runs its pre-flight).
    Deactivate is the deliberate switch-off with a reason.
15. **0175 releases 3 and 4.** *Recommended:* this build takes release 4's `repair` and `fallback` fields, because the
    failure map needs them. Release 3 (the cards' stage strip) comes after.
16. **Review in the counts.** *Recommended:* its own count: received = stored + updated + Review + refused.
17. **"Use backup" button (greyed "to come").** *Recommended:* drop it. Show "On a backup: name" when the system has
    switched to one.
18. **The 2 AM run.** *Recommended:* keep it as a nightly self-test. A red line raises the usual alarm.
19. **How long things are kept.** *Recommended:* alarms, logbook and maintenance windows for 400 days; Incident Reports
    for good. The cost factor is 3.
20. **"Every press runs the Admin's test first."** *Recommended:* each stage button checks first, shows what it will do,
    and greys with the reason when it can't.
21. **Mockup layouts.** *Recommended:* step 3 shows all three (the one-line flow diagram, the stage strip with buttons,
    and your dashboard references) with real data, and you choose there.
22. **Fix, Disconnect and Add source on a need (from your example board).** *Recommended:* not in this build. Changing
    sources from a page needs its own decision.

## Also in step 1

- **22 broken pieces you found yourself, and where each stands.** 20 are fixed live. Two aren't:
  - case #51 (W1 paused whole by one task);
  - the scheduler's "Every minute" label and the Park Service card's glitches.

  Both go into the audit.
- **48 older lines** that your later notes replaced, so nothing is built from them.
- **Step 2 (the audit) has started.** It grades what's live, so it doesn't need these answers.
