# task-seed-6: one ticket per agent, in the CEO's order (for the PM (8); built by the release line as Build & QA (21))

Jason, 2026-11-28, through the CEO: "Bring each one online individually… just in case we run out of usage". It replaces the four team
group releases. Data only, Tier 2. Built on mirror 5b39084 (Sync 2026-11-28 02:52 UTC), on task-seed-4 and task-seed-5.

- **Migration:** `app/migrations/2026-11-28_task_seed_6.sql`, from `seed6.py` and `items.json` (`items.py` writes it).
- **42 tickets** under the transition goal, numbered 01 to 42 in wave order (role numbers from 0180's appendix):
  wave 1: 8, 22, 16, 11, 3 · wave 2: 21, 24, 25, 20, 23, 28 · wave 3: 12, 26, advocates A1 to A12, 15, 27, 9, 18, 17 ·
  wave 4: 5, 6, 7, 4, 10, 19, 13, 14, 29, 30, 31, 32. None for roles 1 and 2. Roles 8, 20, 21, 23, 26, 28: upgraded in place.
- **Live** for an agent means its "Run a test ticket" passed on /admin/config/team; each ticket says so.
- **task-seed-5's four group tickets** are withdrawn (kept, with why), only while still as seeded.
- **Test:** `php tools/task-seed-tests/seed6.php <mirror>/sync-app/lib <seed 4's> <seed 5's> <seed 6's migration>`: 17 of 17 on MariaDB.

## Gaps (labelled)
1. **Priority has three levels** (GaugeTasks::PRIORITY), so waves 3 and 4 are both Priority 3; the order is in each title's number.
2. **The advocates are named A1 to A12** as in 0180's appendix; their account types are in the draft (`team-advocates.md`).
3. **Each agent's release isn't built**: the drafts are job 18's, unreviewed. Each ticket is at Planning.
