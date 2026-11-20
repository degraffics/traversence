# 0175 release 2a, work in progress (held: Jason, 2026-11-20, "once release is live hold building anything more")

Unsent. Built on Sync 14:22–14:37 UTC; rebuild every file on the live copy before using it.
- `website/js/tv-ask.js`: one panel at a time. A link inside a panel replaces what it shows, with ← Back; ×, Esc and a
  click outside still close it. Test: `tools/control-board-tests/one-panel.cjs` (12/12; `peek-anchor.cjs` 10/10, as live).
- `app/lib/ControlRegister.php`: the route line names the board's Ledger in place of "next release records…" (2a item 9).
The rest of 2a's scope (sourced, cleared by the session manager 2026-11-20) is in the session manager's record: live
diagnostics top line on the board and Automation, Diagnostics vs Pre-flight words, a backup as a working state, alarm marks
by level, task switches on the workflow page, no Acknowledge while the system still tries, strip labels and the red-hour
question for Jason.
