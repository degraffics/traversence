# The "rules" release: the 42 agents' listed steps in the decision tree, once. Built and tested, NOT sent.

Ticket: "Agent online 01 (wave 1): Project Manager (8), upgraded in place". Jason's ruling B (decisions/0180 §2, #1681). The list
the Founder sees is `handoffs/team-agents-rules.md`; `steps.json` is that list as built. Built on mirror 5b39084.

## Files (on the live copy)
- `app/lib/TeamRunner.php`: the 27 entries in STEPS, STEP_AT and STEP_COST_MICROS; STEP_AI, AGENTS_API_LIVE (false), STALL_DAYS (3)
  in `ruleData()`; `BUILT` (the four live steps) and `built()`, outside `ruleData()`; `pick()` and `applies()` skip a step without code.
- `app/lib/TeamRoster.php`: `steps()` returns only built steps; `planned()` the rest.
- `app/admin/team-console.php`: an unbuilt step's row reads "Coming with its release … It does nothing until then".
- `app/admin/control-console.php`: one line: Give it to the Team lists only roles with a built step.
- FAQ: `faq-entry.md`, merged into the live `faq.md` at send.

## Tests (the live app plus this build, with the migrations)
- `php tools/team-tests/rules.php <root>/app <live TeamRunner.php>`: 24 of 24 on MariaDB.
- `runner.php` 85 of 85, `audit.php` 34 of 34. The Team page at 1280 and 390: 27 Coming rows, no sideways scroll, no page error
  (`screens/`; the bench's roster shows slugs for roles it doesn't seed).
- Fingerprint: live `7b521b97…`; this release `c96dc6ae…`; with steps added to BUILT, still `c96dc6ae…`.

## Order and what follows
1. After the pause and task-seed-4 and -5 are live and synced: rebuild `faq.md`, zip, zip check, send. Then task-seed-6-2.
2. When it is live, confirm where the Approve press is available (the tree's approval is kept in `team_settings`) and tell the
   session manager; only then is the Founder told.
3. Each agent's release: add its step to `BUILT` with its code, and its Works words. Agent 01's build (`handoffs/team-pm-8`) and Run a
   test ticket (`handoffs/team-test-ticket`) are rebuilt on this release's files first.
4. If designing a planned step changes its rule from the list, the session manager is told before that agent's release: it needs a new press.

## Gaps (labelled)
- The Design reviewer hasn't looked at the Coming rows.
- Roles 11, 12 and 26's drafts are not verified; role 26's draft names a slug that isn't on the roster (the release uses `content-editor`).
