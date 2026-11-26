# decisions/0180 R1: rename and roster (built, waiting its turn, 2026-11-26)

Order (the session manager, relaying Jason's "run it now"): #305 ba-proof-fixes, switch-readback, the staff-mail
release, then this. Built on Sync 11:50 UTC (f43fe5b) with those three laid over it; the patch `r1-tiers.patch` is against
that base. At send time: rebuild on the sync after the staff-mail release is live, re-apply where those files moved.

What it does (0180 §1, §5 and the appendix; the session manager's rulings 2026-11-26):
- **Tiers 1–5 everywhere at once, "Founder ruling" beside Tier 5:** TeamRunner (TIER3_AREAS → TERRITORIES, data
  unchanged; TIER3 → TIER5_WORDS; tier() returns 5; ruleData keys territories/tier5_words, so the fingerprint changes and
  the Team page asks for the tree's approval again), GaugeTasks, ReleaseKind (tier3() → territory()), Releases, the Team
  page's tree (five tiers; Tier 3 and 4 named as coming in R2 and R3; until R3 a territory still comes to the Founder),
  the Work queue (control-console), the admin API, FounderMail, Notify's founder.decision, Access, tools.php, the FAQ
  (eight entries reworded, one new: "What are the five tiers, and what is a Founder ruling?").
- **The stored tier:** `UPDATE control_gauge_tasks SET tier = 5 WHERE tier = 3` (one meaning per number; data_reason
  0180 §1). Roll back maps 5 → 3: `app/rollbacks/<same name>.sql`, run by Releases::rollBack (new in this release: a
  release's rollback SQL runs when it's rolled back, said in its record).
- **The roster:** the 32 roles and 12 advocates (A1–A12), the CEO (2) and today's working seats (1, 8, 20, 21, 23, 26, 28) active, every other role on standby, its work going up its reports-to line (0180's note "The CEO builds the team", #1454); the advocates active, views only; layers in the department column
  (founder, executive, managers, specialists, advocates); today's rows renamed in place only where the name is still the
  seed's; new rows inserted only where the slug isn't there; new roles have no permissions (only the Founder gives them,
  0180 §2: the card says "No permissions yet"). Retired, never shown, never routed to, still named for past runs: the six
  0180 names (4, 5, 9, 14, 18, 20) and two rows folded into one role each, Technical QA & Security Gatekeeper (into #21
  Build & QA Sessions) and Senior Designer (into #27 Public UI/UX Designers): **my call, reported**.
- **Standby routing (0180 §5):** a step whose role is on standby goes to the first active role up its reports-to line that
  Permissions allows the step, past retired ones; none: it waits for the Founder (TeamRoster::doer).
- **Staging's Team runner on** (CLAUDE.md's standing OK): the migration writes team_runner = on only on a database whose name
  ends stg; Staging::STAGING_ONLY keeps catch-up from bringing live's row and Publish from carrying staging's; a fresh
  copy starts with it on (Staging::ON_AFTER_COPY). The tree's approval and the ceiling stay Jason's own presses (CLAUDE.md):
  the session manager's #1443 pre-seed of them was declined.
- No ceiling, fence, step, STEP_AT, spend or reach data changed.

Tests (on the bench, R1 over the three before it): `tools/team-tests/r1.php` 34 (seed twice, who is active, names, layers, retired,
tiers, routing, Roll back byte for byte); roster.php 54, runner.php 85, audit.php 34, tickets.php 54, batch.php 25,
kind.php 35, rule2.php 24, pipeline.php 22, staging unit 65, staging db 49, closed 10, onstates 7, held 14, tickwait 6, old-suites 84/44/32; rendered at
1280 and 390: roster.cjs 44, team-head.cjs 40, runner.cjs 44, tickets.cjs 62, batch.cjs 18, fence.cjs 10.
- **A Work ticket** for the IT Manager's lane (the session manager, 2026-11-26): /admin/sources.php and /admin/crawler-review.php
  time out at 45 s on staging (#305's look). Added once by the migration; Roll back leaves it in the queue.
- **Not in R1:** the tree's approval and the ceiling. The session manager asked twice to record Jason's one-time setup in his
  words (#1443, #1444); this session's safety check refused both edits as undermining oversight. Jason presses them after R1.
