# The daily goal check (goal_check_tick): built, not sent. Built on mirror eb848e8 (Sync 12:50 UTC).

**For:** the PM (8), as Build & QA (21), IT lane, Tier 2. **Source:** CLAUDE.md "Goals and records". **Plan OK'd by:** the session manager, 2026-11-27.

**Order:** after the pipeline release, R2 and task-seed-4. It needs R2's `TeamRoster::lane()` and task-seed-4's `due_on`.

## Files
- **app/lib/GoalCheck.php (new):** `STALL_DAYS` 3; `AT_RISK` (the PM's ruling, with its note); `CROSS_LANE` 'ceo'; tick(); atRisk().
- **app/scripts/goal_check_tick.php (new).**
- **app/lib/Scheduler.php:** `goal_check_tick` added to JOBS (daily, late after 1560 min), AUTO and NOT_ON_STAGING. No other job is touched.
- **app/ui/faq.md:** one team entry.

## Tests
`tools/team-tests/goal-check.php <root>/app`, on MariaDB with R2's TeamRoster: **25 of 25** (stall, at risk, cross-lane to the CEO, no due date, no duplicates, cleared, re-opened, the rule's edges, the job's lists, before task-seed-4). With the live TeamRoster (before R2), every ticket falls to the Project Manager's lane: 23 of 25 pass, and the 2 lane checks fail as expected.

## Zip check (at build)
These three blocks are all expected and clear at send time:
1. **faq.md** is in flight in pipeline-tick: rebuild it on the live copy.
2. **R01:** `TeamRoster::lane()` isn't live until R2 is.
3. **Scheduler.php (top)** asks for a change claim. Only `--add` was taken, as asked; the session manager decides.
