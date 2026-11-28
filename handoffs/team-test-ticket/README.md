# Run a test ticket (the Team page). Built and tested, NOT sent.

Ticket: "Ready to be vetted: /admin/config/team shows each role's charter, lane, tier, AI access and queue, with Run a test ticket"
(task-seed-5). Built on mirror 5b39084 (Sync 2026-11-28 02:52 UTC) by the release line, as Build & QA (21).

## Files
- **New:** `app/lib/TeamVet.php`: `off()` (why a role can't run, with the way forward), `run()` (one test ticket through
  `TeamRunner::run`, then Undo or Dismiss and Withdraw), `last()`, `lastWords()`, `aiWords()`. The last result is one row a role in
  `team_settings` (`vet:<slug>`); no migration.
- **Changed:** `app/admin/tools.php` (`config/team` only): the fields AI access and Test ticket, the action Run a test ticket
  (greyed with `off`, as Run the audit now is, its title the same reason as its Test ticket row), and its act. One live line
  replaced: Status on an active role with no step reads "Active · waiting for its first step (its own release brings one)"
  (the Design look, 2026-11-28).
- **FAQ:** `faq-entry.md`, to merge into the live `faq.md` at send (faq.md is in flight in pause-1a-4).
- `TeamRunner.php` is not touched: no rule changes, the tree's approval stays, and nothing clashes with agent 01's held build.

## Tests (the live app plus this build, with the migrations)
- `php tools/team-tests/test-ticket.php <root>/app`: 46 of 46 on MariaDB.
- `node tools/team-tests/test-ticket.cjs <root> <shots>`: 26 of 26 at 1280 and 390 (screens in `screens/`).
- `runner.php` 85 of 85 (its steps check now holds before and after agent 01), `audit.php` 34 of 34.

## Before it is sent
1. pause-1a-4 live and synced, then `faq.md` rebuilt on the live copy; zip, zip check, send.
2. Design: approved to send by the session manager standing in as the Design look (2026-11-28), with two word fixes, both made
   (Status on a role with no step; the greyed button's title).

## The list card (the CEO's question, 2026-11-28)
- **Status:** an active role with no step reads "Waiting for its first step" in the list too (it read "Active" beside "No steps
  yet"); a role with a step reads "Active". Checked in `test-ticket.php`; the list at 390 wasn't looked at again in a browser (a
  word in the same pill).
- **Not tied to the test ticket passing yet:** a test's result is kept on staging (`team_settings`), and the live list can't read
  it. Suggest: the result travels with the agent's release record, then "Active" can wait for a passed test.
- **What fills by itself when an agent's release goes live** (tools.php, `config/team` list): Open tickets does (the Work queue's
  tickets its role holds). Works doesn't: it is the role's stored words (`stages`), so each agent's release sets them; only "No
  steps yet" goes by itself. Acts as doesn't: it is the role's level and department, which only the Founder gives (0180 §2).

## Gaps (labelled)
1. **A test run counts in the Team page's evidence.** It is a real `team_runs` row (dismissed or undone), so "Planning's
   suggestions … dismissed" and "build briefs … taken back" include test runs. Suggest: evidence leaves out runs on
   `area:team-test` (a change to TeamRunner.php, with agent 01's build).
2. **The ticket also names charter, lane and queue on the page.** Lane and queue need 0180's lanes per role; the charter comes
   with each agent's release (agent 01's `TeamRunner::CHARTERS`). Not in this release.
3. **"The step for Jason" for the paid AI path isn't decided anywhere** (which key, which budget), so AI access says it stays off
   until it has its own budget, as its own release. Suggest: a Decision Brief names the key and the cap.
4. **Only three roles have a step to test today:** the Project Manager, the System Architect and the Translator (the auditor
   has Run the audit now). Every other role's button is greyed until its own release brings a step.
