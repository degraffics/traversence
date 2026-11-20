# The vault's row actions on §7 (2026-11-20): sent to the branch, waiting for Jason's OK

Design and trips session. Built on **Sync 17:24 UTC (740d308)**, with T5 live (#233), by `handoffs/vault-icons/build-vi.py`.
Source: Jason, 2026-11-20, T5's pop-up (reviewed by the session manager): "Follow the standard" (table-standard §7). It's
recorded as a dated note in `design/table-standard.md`.

## What it does
- Each login shows **Copy password**, its one visible button (§7).
- Its **⋯** (the admin's ⋯ menu from T1, `.akt-rm`) lists, in words, **Show the password for 10 seconds**, **Copy the user
  name**, **Change it** and **Delete it**. Copy the user name is greyed with "No user name saved." when there is none
  (CLAUDE.md: greyed with the reason). Delete is in Crimson Thread, the remove colour (design 3a, 2026-11-18).
- The menu closes with its **×**, **Esc** or a tap outside (CLAUDE.md: everything that pops open closes). Picking an item
  closes it. Delete keeps its confirm (`TvAsk.gate`) and the menu stays open until that's answered. Near the window's
  bottom it opens upward.
- Kept: every action, with the same handler and the same words as before (they were the icons' tooltips). The shown
  password still appears under the row for 10 seconds, with its × and the countdown. Nothing is dropped.
- FAQ: one sentence on the vault's entry says where each action is.

## Tests
`/test/vault-menu.php` on the harness runs the menu code taken from the built `tv-vault.js` with sample rows (the real
list needs a passkey): 26 of 26 at 1280 and 390. One visible button; ⋯'s four items in words; Esc, a tap outside and ×
close it; Change it runs and closes it; the greyed user-name item says why; Delete asks first with the menu still open,
then runs and closes; the last row's menu is wholly in the window; no sideways scroll; no script errors.

## Watch
The zip check warns that `tv-vault.js` calls `tv-ask.js::statuses`, which Operations is changing for 0176 R2. This
release doesn't touch that call; re-check it once 0176 R2 is live.
