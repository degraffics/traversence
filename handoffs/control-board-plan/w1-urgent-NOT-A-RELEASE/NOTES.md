# NOT A RELEASE: the urgent W1 fix, stopped (2026-11-21)

**Not a release.** Never sent, merged or deployed. It's kept only as evidence for step 3. Its claims were released.

**History:**
- Built 14:34–14:43 UTC, after Jason chose "Lift now + urgent fix".
- Stopped by the session manager at 14:43 UTC, after Jason's lift of the trips went badly.
- From then on his end-to-end engineering standard governs all work: contract and boundaries first, complete
  state-transition coverage, actions where the problem is reported, proof before hand-off, and live actions only on his
  explicit call.

**Why it stopped:** it is exactly the piecemeal patch the standard rules out. It changes how trips pause and clear in
two files, without the one state model, the full transition table or the verified recovery that step 3 must establish
first.

## What it did

| Change | Source cited | What step 3 must still decide |
|---|---|---|
| A `task:failed:<t>` trip pauses only that task | Model 1.2; 0175 §3 | Only within the one engine's hold rules |
| Our endpoint's timeout or 5xx (worker 2.16.2's `SiteSlow`) is recorded as `ep:<endpoint>:slow` / `:5xx`, and an `ep:` trip holds nothing | 0171's note of 2026-11-19 (slow is never a trip) | Our endpoints as parts with their own manifest and Incident Radius |
| A run stopped only by our endpoint doesn't make the run "failed" | Same | Run status within the state model |
| `settleNarrow`, on each watch: open old trips narrowed; the two bulk loads' read timeouts re-filed and closed | IR-0001 | **A recovery that closes trips without a real check.** That's what the standard forbids ("No recovery action may claim success or close a trip without real verification"). The re-file rests on IR-0001, whose handler-or-response question is still open |
| Crawler 2.16.2: 250 rows a batch, one resend after 30 s | Case #51's draft | A resend adds to a town's tallies (`open_loads` seen/staged), though not its records, which go through the identity check |
| FAQ entry | — | — |

**Tests on the copy (Sync 14:26 UTC):**
- `task-failures.php`: 30 of 30.
- The neighbouring suites pass, those needing the ledger tables included (r3c 27, r4e 11, itsdata 22).
- The crawler's tests pass.
- The only failure is `paused`' migration check, which also fails on the live code.

**Found while building, for step 3's state model:**
- "Check and start again" on a task trip checks the whole workflow's pre-flight. With any red line in W1, a person's
  lift of one task is refused.
- A paused task's trip clears only when that task reports "done", which it can't do while paused.

## Files

- `w1-site.patch`: `ControlAlarms.php`, `CrawlService.php` and `faq.md`, against the mirror at Sync 14:26 UTC.
- `w1-crawler-tests.patch`: crawler 2.16.2 and the tests, against traversence `main` (d6b4427e).
- `w1-urgent-NOT-A-RELEASE.zip`: the zip as built. **Never to be sent.**
