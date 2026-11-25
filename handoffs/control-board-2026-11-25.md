# Control Board build session: final hand-off (2026-11-25, before the holiday)

The Control Board build session (`session_01CXLi5gqPa5AQKoZXC7gZxx`), which took over from `control-board-2026-11-24.md`.
Jason, 2026-11-24: "please ensure each session is summarized and that all adr's and governance docs are updated then archive
them. Push anything outstanding into the task que so I can review it when I return. We will resume our work from the work que."
**From here, the Work queue is the queue:** System Configuration → System Control Board → Development
(https://traversence.com/admin/config/control?tab=development). Every open item is a task there with its scope, priority,
decisions and its docs in its source.

## Live (each synced and byte-checked, or checked on live data)

| # | Release | What it did | Record |
|---|---|---|---|
| 280 | board-phone-header.zip | the board on a phone: ☰ for its pages, one Alerts dropdown, one alarm count everywhere; W1's side panel drawn right; daily jobs' late limits; the audit clock's retry; the health report's jobs section | `board-phone.md` |
| 281 | work-queue.zip | the Work queue on Development: its gauge, Add a task, Take / Move / Name its release / Withdraw, ✎ with History, 20 tasks seeded | `work-queue.md` |
| 282 | task-scope.zip | a task's page: scope (goal, in, out, done when), priority 1–3, steps (see → do → confirm) | `task-scope.md` |
| 283 | task-adrs.zip | the decisions a task rests on: Attach a decision from the Decisions page's own list, ✎ to detach | `task-adrs.md` |
| 284 | task-seed.zip | Jason's scope doc: **its migration was cleared unrun** (0162's 2026-11-25 note) | `task-seed.md` |
| 285 | task-seed-2.zip | the scope doc's tasks, run (checked on live: 18 Backlog rows) | `task-seed.md` |
| — | task-seed-3.zip (PR #1368) | everything outstanding before the holiday, each task with its docs; **merged, live once synced: check its rows on live** (CLAUDE.md's new line) | `task-seed.md` |

## Built, not sent (both in the Work queue)
- **no-undo** (`no-undo.md`, branch `claude/no-undo`): no Undo after an alarm's acknowledgement on the board, the card and Pulse;
  "Seen by …, <time>" as the state line. Tests pass (7; 4 fail on live); the zip check was clean on Sync 2026-11-24 13:50 UTC.
  Rebuild on the live copies, a Design look, then Jason's pop-up. Its updated tests are in `no-undo/tests/` (they expect no
  Undo, so they go into `tools/` with the release).
- **Work queue phase 2** (`work-queue-phase2.md`): a task's thread with Jason, the system, Claude (`task_assist`, inside the $40
  cap) and Gemini (public pages only); design only, its mockups; for Jason after his four-stage redesign.

## Open, for whoever resumes
- **Jason's Work queue redesign** around his four stages (planning, building, implementation, confirmation), waiting for his
  outline; mockup first, his words only.
- **The migration runner's false "already there"** (P1 in the queue; 0162's note; CLAUDE.md's line until it's fixed).
- **Gemini on the paid tier** (queued); whether internal text may go to the paid key is Jason's call (0164 §0.8).
- **W1's trips and the 503 retry**, **the loads counting warning**, **plain alarm titles**, **the :17 crowd** and the :02 test:
  in the queue with their docs.
- **Bench notes:** MariaDB stops when the container restarts (`mysqld_safe --user=mysql &`); `rendered-all.sh` takes `|`
  between names; `dispatcher/r3` stops at a click on this bench and on live code alike; `gauges.php` 11a/11b and
  `old-titles.php` fail on live code too.

## Rules learned here
- A data migration with a CREATE of an existing table is cleared unrun (#284): check rows on live after publish.
- A dialog (`TvAsk.ask`/`choose`) opens behind the board's side panel: inside a panel, edit in place.
- The zip check's add-only rule matches words in data ("Change how …"): reword the data, the site's check is the same.
- A task's `source` is 255 characters.
