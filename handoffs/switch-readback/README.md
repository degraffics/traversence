# Switches: Save reads the row back (held, 2026-11-26)

Jason saved "Staging: the Team's runner" on staging twice (13:14 and 13:16 UTC). Each save said "Success: Saved: it takes
effect at once" and updated Last changed, but On for still read Off. Built on Sync 11:50 UTC (f43fe5b). Patch:
`switch-readback.patch` (app/admin/sections.php, app/lib/Switches.php).

- Save reads the switch again (`Switches::fresh`) before it reports anything. If the state or kill switch isn't what was
  chosen: "Not saved: you chose X, but it reads Y now, so nothing changed…" (an error, never Success). If it matches,
  it names the new state: "Saved: Staging: the Team's runner is on for everyone, from now."
- The runner's card says "A background job, so only Everyone runs it": `TeamRunner` asks `Switches::on()` with no
  person, so Staff and test accounts reads as off for the job.
- Cause on staging still open (read-only so far): the form, `Switches::set`, the column (live varchar(8)), the other
  writers and the page's redraw are ruled out. The remaining one is catch-up's no-base path (`StagingSync::catchUpKey`:
  no base row, live has a row, staging's change unrecorded → `applyLive` overwrites). Waiting on the card's note.
- Test: `tools/switch-tests/readback.cjs` with `router.php` (live code through the real widget, Chromium at 1280 and 390):
  choose Everyone, save, reload, still Everyone; a row forced back says Not saved. 12/12.
