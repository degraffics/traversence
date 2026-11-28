# Agent 01: the Project Manager (8), upgraded in place. Built and verified, NOT sent.

Ticket: "Agent online 01 (wave 1): Project Manager (8), upgraded in place" (task-seed-6). From the batch's draft
`research/ai-results/ready/team-pm-8/`, verified by the release line against mirror 5b39084 (Sync 2026-11-28 02:52 UTC).

- **File:** `build/app/lib/TeamRunner.php` (the live copy plus the draft: 6 live lines replaced, the rest added). `php -l` clean.
- **Tests, all on the live lib plus this file:**
  - `php tools/team-tests/team-pm.php <root>`: 50 of 50 (no database).
  - `STAGING=1 php tools/team-tests/team-pm-db.php <root>`: 9 of 9 on MariaDB (the draft's `Database::pdo()` corrected to `Database::connection()`).
  - `php tools/team-tests/runner.php <root>/app`: 85 of 85 (it was 84: "the Team page lists each working role's real steps" expected
    the PM's steps to be gate alone; the check now expects gate, sequence, stall).
- **FAQ:** `faq-entries.md`, to merge into the live `faq.md` at send (faq.md is in flight in pause-1a-4).

## Before it is sent
1. **A Founder ruling (Tier 5).** The draft's own gate: the tree's rules change (`TeamRunner::ruleData`: two steps, STEP_AI,
   AGENTS_API_LIVE, STALL_DAYS), so the fingerprint changes; on staging the tree's approval is cleared and every role works at the
   Planning ceiling until the Founder approves the tree again (decisions/0180 §2). That is not "stage ceiling unchanged". Every later
   agent's release that adds a step does the same. Suggest: one Decision Brief covering the 42 agents' rule changes, or approving
   the tree once per wave.
2. **pause-1a-4 live and synced**, then `faq.md` rebuilt on the live copy; zip, zip check, send.

## Not verified here (labelled)
- `Access::canAs` for the new actions `team.sequence` and `team.stall` (the draft asks to confirm on staging that the PM's row passes).
- The charter isn't shown anywhere yet: the Team page ("Ready to be vetted") is its own ticket.
- "Run a test ticket" doesn't exist yet, so this agent can't be reported Live by that rule until the Team page's release.
