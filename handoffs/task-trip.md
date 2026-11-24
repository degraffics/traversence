# task-trip.zip: a task's trip that could never clear (2026-11-24)

**Found:** Jason pressed Check and start again on W1's overture_bulk trip (run #1290) once the crawler release (#277) was live.
The card then read "Acknowledged by Jason DeGraff" and still "Paused by the system (W1 (its tasks wait with it))".

**Cause (live, Sync 01:30 UTC):** `ControlAlarms::resolve()`, for a task's own code (`task:`/`job:`, no check before its next
run), returned "Noted … Its next run is its check" before the loop that lifts the trip's pauses. The trip (raised before the
one engine, so its `stopped` holds `['pause', 'wf:W1', …]`) kept W1 paused, and `CrawlService::plan()` skips a workflow the
system paused, so that next run never came. "Try it first on the next run" (`runNext`) only cleared `last_served_at`: the
same skip. Both W1 trips (overture_bulk #1290, foursquare_bulk #1288) each paused W1. A dead end.

**Fix (built on Sync 01:30 UTC):**
- `ControlAlarms::startForNextRun()`: the press lifts this trip's own pauses (`Automation::sysResume`), and says what
  happened: "Started again at … : W1 runs again, and its next run is this task's check: this closes as Fixed when that run does
  its work, or trips again if it fails"; or, with another trip still holding it: "… but W1 is still paused by "<its title>"
  (its own Check and start again lifts it). Press that one too; …". A trip that paused nothing reads as before.
- `CrawlService::runNext()` returns why the task won't run while the system holds it; `Operations` (Pulse's button) shows it as
  the notice's error, `crawler-console.php` as its words. Never "It runs first" when a pause will skip it.

- **The pressed state replaces the trip's** (Jason, 2026-11-24: "Thought every action was to replace old states with new state";
  his press left "Paused by the system", "Check and start again" and "Acknowledged" on the card). `ControlAlarms::startedAgain()`:
  its Status is the press's result, "Holds:" gone, What it's doing "Nothing is paused for this trip now: its next run is the
  check"; on the board the row is amber (`data-astate="started"`) with no Check and start again, Try it first or Acknowledge;
  in Pulse the same. If that run fails again, `raise()` puts its pauses back and its Status adds "Tried again at … it failed
  again (…). Fix its cause, then Check and start again.", the button back. Its next good run closes it as Fixed.

**Tests:** `tools/control-board-tests/task-trip.php` 21/21 (with the Design reviewer's words: "This trip's pause is lifted …", "W1 runs again …", the other trip's own button on the card) (W1's live state: two trips each holding wf:W1); run-all.sh only the
7 known live failures (r3c's and S-D110's words kept: "Its next run is its check").

**Once live:** Jason presses Check and start again on both W1 trips (the first names the second); W1 runs; each trip closes as
Fixed when its task's next run does its work.

**Noted, not changed:** under the one engine a task's own-code trip holds nothing (`ControlEngine::radius`: `task:`/`wf:` → []);
W1's two trips are older, from before it, which is why they hold all of W1.

## The Design reviewer's words and the rendered states (2026-11-24)

- Status: "This trip's pause is lifted (Jason, 02:10 UTC). W1 is still paused by one more trip: Foursquare failed (run
  #1288). Once both are cleared, W1's next run is this task's check: …" with that trip's own **Check and start again:
  Foursquare failed (run #1288)** on the card (wraps on a phone); What to do: "Press the other trip's Check and start again
  (below); …". Pressed there: both cards "W1 runs again (Jason, 02:11 UTC). Its next run is this task's check: it closes as
  Fixed if it works, or trips again if not."; What the system is doing "Nothing is paused for this trip. Its next run is the
  check."; What to do "Nothing to do now: it closes by itself when that run works."; no "Paused by the system", no "Holds:";
  amber. A group of trips all started again: "(nothing held: each waits for its next run)", amber.
- "cleared" became "pause is lifted": the trip stays open until its run proves the fix.
- Screens at 1280 and 390: `task-trip/task-trip-{one,waiting,failed}-{1280,390}.png` (`task-trip.cjs` 24/24, in rendered-all.sh).

## Folded in: the trip card's eight fixes (the session manager, from Jason's 390 screenshot, 2026-11-24)

Sent as **task-trip-2.zip** (the first, task-trip.zip, withdrawn unsent; the site never takes a name twice). For a task's
own-code trip that paused its whole workflow (`ControlAlarms::wholeWorkflowTrip`: W1's two, raised before the one engine;
engine-era trips pause only their task and keep their tested words):
1. Status in one sentence: "W1 is paused by the system since Nov 21, 9:35 AM UTC, after Overture Maps Places' read failed
   (run #1290). Seen by Jason, …." (no brackets in brackets; "<name>'s read failed", the task's own name before its colon).
2. What it affects once: Holds W1 Listings; no second "Paused by the system: W1 (…)".
3. The loose "by Jason… It stays open while it holds…" box gone for these (its words are in the Status).
4. One action, **Check and start again**; its run an evidence icon (eye, "Open Run #1290: what it did and why"); Try it first
   and Pause the task under ⋯ (`details.cb-pop.cb-more`: Esc and a click outside close it).
5. No Undo on its acknowledgement: Acknowledge until pressed, then "Seen by …" in the Status.
6. Its group in one line: "W1 Listings · 2 trips ▾" (a plain group's ": N parts" read as " · N trips" for trips).
7. What the system is doing "Only W1 waits; everything else carries on. It closes as Fixed when this task's next run works.";
   What to do "Read its why (its run, beside this). Then Check and start again: W1 runs, and its next run is this task's check."
8. A trip's mark is the stopped (power) icon everywhere, never an × that reads as close.

Also the Design reviewer's: "Once both are lifted". Tests: task-trip.php 26/26, task-trip.cjs 44/44 (before, one, waiting,
failed at 1280 and 390, screens in `task-trip/`), rendered board 283/283, run-all.sh only the 7 known; `links.php` updated.
**Not changed (noted):** other trips' acknowledgement still shows Undo and the "Seen:" line; #1314's no-Undo rule for every
alarm is a wider change for its own release.
