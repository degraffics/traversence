# Ruling 1's holds: releases wait for the Founder only when they must. Built on mirror 5b39084.

Tickets (the CEO's final shape, 2026-11-28; in `handoffs/task-seed-7/items.json`, seeded with task-seed-6-2): "Data releases that only
add rows go live on passing checks, only data_reason changes stay in the daily batch" and "An incomplete look says 'couldn't be
judged', never 'its checks passed'". Sent as `ruling1-holds.zip` (#336), then as `ruling1-holds-2.zip`, which replaces it and #331.

- **BeforeAfter.php:** an incomplete after look is asked again, `INCOMPLETE_LOOKS` (3) looks in all, each known by its run so the last
  verdict on main isn't read as the new one. A look with no run link can't be told apart: it goes straight to the ticket.
- **Releases.php:** `checkedByAdvocates`: after the last incomplete look the release stays on staging, unpublished, with an Audit
  ticket (`ba-incomplete:<id>`) in the IT Manager's lane, and only then reaches the Founder: his email says it couldn't be judged. `rule2`: only `ReleaseKind::changesRows` (UPDATE or
  DELETE) holds as data. `reviewLead()`: "its checks passed" only when the before-and-after check passed, and why it waits.
- **ReleaseKind.php:** `changesRows()`; `insertsRows()` stays for TeamRunner's fence.
- **Tests:** `php tools/piece3-tests/ruling1.php <root>/app` 33 of 33 on MariaDB; `pipeline.php` 22, `rule2.php` 25, `kind.php` 35,
  `tickwait.php` 6, `closed.php` 10, `onstates.php` 7. `tickverdict.php` and `held.php` weren't run here (they need their own bench).

## Gaps (labelled)
1. **DB4 unchanged:** once the day's minutes are spent, a passed release is still sent to the Founder for review. Suggest: it waits
   for the next day's minutes instead, by ruling 1. Not changed here: it wasn't in the session manager's list.
2. **No FAQ entry in this zip** (faq.md is in flight in pause-1a-5); the next release that carries faq.md adds it.
3. **#331 and #332 can't be asked again from the site** (their waiting records are gone); the resends cover them.
4. **The before look** has no incomplete verdict of its own in the code: an unusable before look shows as an incomplete after look.
