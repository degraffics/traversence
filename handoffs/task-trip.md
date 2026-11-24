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
