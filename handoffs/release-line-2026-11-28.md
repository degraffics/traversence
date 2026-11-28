# The release line, 2026-11-28: where it stands (kept current so it resumes cleanly)

Session manager: session_01CXLi5mGX2sg5QS11gg3Wrh. Queue order (the CEO): the pause → task-seed-4 → task-seed-5 → the rules release →
task-seed-6-2 → Run a test ticket → agent 01. One at a time; each is sent only after the one before has its before look done.

| Item | Ticket | State at 14:27 UTC | Next step |
|---|---|---|---|
| The pause, `pause-1a-5.zip` (#335) | The pause… (pause-1a-4) | Before look done 14:11; after look next | Report its verdict. #331 (the same pause) is withdrawn by ruling1-holds-3's "replaces" |
| Ruling 1's holds, `ruling1-holds-3.zip` | Data releases that only add rows… / An incomplete look says… | Sent (#1690); replaces -2, #336, #331. Not live | Report when #331 and #336 show Withdrawn, and its verdict |
| task-seed-4, `task-seed-4-2.zip` | task-seed-4: the goals… | Sent (#1691), "replaces": ["#332"]. Not live | Wait for its before look, then send task-seed-5-2 |
| task-seed-5 | task-seed-5: every current item… | #333 withdrawn | Send `task-seed-5-2.zip`, unchanged files, its ticket; "replaces" only if #333 isn't Withdrawn; run `seed5.php`; mark task-seed-5's record replaced |
| task-seed-6 | (the agents' tickets) | #334 went Live at 13:45, before the goals: it made 0 tickets | After 4-2 and 5-2 are live: `task-seed-6-2.zip` with the migration renamed `2026-11-28_task_seed_6b.sql` (same statements) plus the tickets in `handoffs/task-seed-7/items.json`; run `seed6.php` |
| Run a test ticket | Ready to be vetted… | Built, tested, Design approved (`handoffs/team-test-ticket/`). Not sent | After the pause is live and synced: rebuild `tools.php` and `faq.md` on the live copy, zip, zip check, send |
| The rules release | Agent online 01… | Built, tested, Design approved (`handoffs/team-rules/`). Not sent | After Run a test ticket: rebuild its four files and `faq.md` on the live copy; send. When live, confirm where Approve the decision tree is pressed and tell the session manager (Jason: Approve, then raise to Building and Implementation again) |
| Agent 01, the Project Manager | Agent online 01… | Built and verified (`handoffs/team-pm-8/`). Held | After the Founder's one approval: rebuild on the rules release (add `sequence`, `stall` to `BUILT`, its Works words), send |

Rules since 14:10 UTC: one send at a time, each after the one before has its before look done; every release.json names its `ticket`
and, for a resend, what it `replaces`; no batch OK is asked for the seeds.

**Bench (this container):** MariaDB with users `t`/`t` and `tv`/`tv`; a test root is the mirror's `sync-app` plus a build plus the
migrations from the build zips in `handoffs/` (`2026-11-25_*`, `2026-11-26_0180_tiers_roster.sql`). `service mariadb start` after a restart.

**Open with the session manager:** the 22 planned steps are not designed (a change from the list needs a new press, told before that
agent's release); tying a role's "Active" to its passed test ticket (the result is kept on staging); the evidence counts include test runs.
