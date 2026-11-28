# The release line, 2026-11-28: where it stands (kept current so it resumes cleanly)

Session manager: session_01CXLi5mGX2sg5QS11gg3Wrh. Queue order (the CEO): the pause → task-seed-4 → task-seed-5 → the rules release →
task-seed-6-2 → Run a test ticket → agent 01. One at a time; each is sent only after the one before has its before look done.

| Item | Ticket | State at 14:05 UTC | Next step |
|---|---|---|---|
| The pause, `pause-1a-5.zip` (#335) | The pause… (pause-1a-4) | Sent (#1680). #335 "planned" since 13:46, before look not done | Wait; report its verdict |
| task-seed-4 | task-seed-4: the goals… | #332 staged, "couldn't judge it; waits for a person" (the check's sign-in, fixed #1679) | Resend as `task-seed-4-2.zip`: same migration, release.json gains its `ticket`; run `seed4.php`; mark task-seed-4's record replaced |
| task-seed-5 | task-seed-5: every current item… | #333 withdrawn (same cause) | Resend as `task-seed-5-2.zip`, unchanged; run `seed5.php`; mark replaced |
| The rules release | Agent online 01… | Built and tested, in main (`handoffs/team-rules/`, #1685). Not sent | After the pause is live and synced: rebuild `faq.md` on the live copy with `faq-entry.md`, zip, zip check, send. When live, confirm where Approve the decision tree can be pressed and tell the session manager |
| task-seed-6 | (the agents' tickets) | #334 went Live at 13:45, before the goals: it made 0 tickets | After 4 and 5 are live: send `task-seed-6-2.zip` with the migration renamed `2026-11-28_task_seed_6b.sql` (same statements); run `seed6.php` |
| Run a test ticket | Ready to be vetted… | Built, tested, Design approved, in main (`handoffs/team-test-ticket/`, #1677, #1678, #1684). Not sent | Rebuild `tools.php` and `faq.md` on the live copy after the rules release is live (it reads `TeamRoster::steps()`), then send |
| Agent 01, the Project Manager | Agent online 01… | Built and verified (`handoffs/team-pm-8/`, #1676). Held | After the Founder's one approval: rebuild on the rules release (add `sequence`, `stall` to `BUILT`, its Works words), send |

**Bench (this container):** MariaDB with users `t`/`t` and `tv`/`tv`; a test root is the mirror's `sync-app` plus a build plus the
migrations from the build zips in `handoffs/` (`2026-11-25_*`, `2026-11-26_0180_tiers_roster.sql`). `service mariadb start` after a restart.

**Open with the session manager:** the 22 planned steps are not designed (a change from the list needs a new press, told before that
agent's release); tying a role's "Active" to its passed test ticket (the result is kept on staging); the evidence counts include test runs.
