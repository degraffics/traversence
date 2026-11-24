# Control Board build session: hand-off (2026-11-24, ~02:50 UTC)

From the Control Board build session (`session_01CXLi5hL7DpeN9WxT3oMXQK`) to a fresh session, at a hold, because this session grew
too large (CLAUDE.md, the session manager). Built on **Sync 02:46 UTC (973d10b)**. Read CLAUDE.md first, including
**"Never invent what's already set"** (grep `decisions/` and the live code; cite them; a gap is a suggestion, labelled).

## Live, by release (each byte-checked against the mirror)

| # | Release | What it did | Record |
|---|---|---|---|
| 273 | gauge-panel.zip | The gauge side panel as a drill-down: each item one cycle (view, request, cleared by its check) | `handoffs/gauge-panel.md` |
| 275 | kit-buttons.zip | Brand-brown filled buttons; Copy as an icon; **Request a fix** (never "Fix it" for what only queues), its line naming the session that has it (`releases/gauge-pickups.json` → `gauges.json`), Not picked up yet after a day; ⋯ per gauge item | `handoffs/kit-buttons/` |
| 277 | loads-reply.zip (+ crawler 2.17.0) | IR-0001: W1's Overture/Foursquare loads in numbered 100-row batches (500 took 101–130 s vs a 120 s read), a resent batch does nothing twice (`open_load_batches`, OWN), reply then recheck; W0's "received 250, accounted for 0" was the ledger not reading the nested receipt (fixed; "Its data" lists each batch); AuditClock asks for `gauge-requests.yml` hourly (confirmed: run #4, 02:17 UTC, repository_dispatch) | `handoffs/loads-reply.md` |
| 278 | task-trip-3.zip | A task's trip that paused its whole workflow could never clear (the press only noted it; the planner skips a paused workflow). The press now lifts its own pauses; the card shows the new state on every line (amber "Waiting for its run", nothing held), names another trip still holding it with that trip's button on the card; a failed retry goes back to the trip in words; Try it first says when a pause will skip it. The trip card cleaned up (one sentence, one filled action, run as an eye icon, rest under ⋯, Seen as a state line, no Undo, stopped (power) mark, one-line group header) | `handoffs/task-trip.md`, screens `handoffs/task-trip/` |

## Waiting on Jason right now

- **W1's two trips** (Overture #1290, Foursquare #1288). At ~02:46 UTC Jason pressed Overture's Check and start again: its card
  reads "This trip's pause is lifted … W1 is still paused by one more trip: Foursquare open places' read failed (run #1288)". He
  still has to press **"Check and start again: Foursquare open places' read failed (run #1288)"** (on that card, or Foursquare's
  own). Then both read "W1 runs again"; W1's next run retries both loads; each closes as Fixed when its run works.
- **Then confirm on live data** (live-read.yml, choice `ir0001`, test accounts; delete the run's log after): both trips
  cleared (or a new "Tried again … failed again"), and the ledger's `POST /api/crawl/loads` calls at about 20–30 s each.
  If a load still fails: read its run's log line (2.17.0 logs each slow/failed site call), fix at the cause.

## Next, in the session manager's order (each: Design reviewer → session manager → Jason's pop-up)

1. **The board's phone header** (Jason, 2026-11-24, 390px screenshot: "1 full width dropdown for alerts … instead of this visual
   chaos", then "Or a hamburger menu for pages like we've used before?"). Below 768px: the board's pages (Development, Board,
   Modules, Workflows, Diagnostics, with counts, current marked) in the site's ☰ pattern (CLAUDE.md, phone menus), either
   folded into the admin's ☰ as its first group or its own clearly separate ☰ (mock both; the Design reviewer picks, with why);
   **one Alerts dropdown**, full width, groups inside (Trips, Warnings, Caps, Checks, Paused now) each with its count; **one
   alarm count** everywhere (today the header says 13, the filter 35: find which is right, then make them agree, or label the
   difference, e.g. "13 need you · 35 open"). Desktop keeps the side rail; its five chips fold into Alerts unless the Design
   reviewer finds that worse at 1280. Every dropdown closes with ×, Esc and a click outside. Mock at 390 and 1280 first.
   **In the same release, first:**
   - the W1 side panel at 390 runs the card's parts together ("…UTC.What it affects: Holds: W1 ListingsWhat the system is
     doing…"): the side panel lacks the `.tv-th-parts` grid styles the board page has (Jason's 7:46 screenshot);
   - a tall green bar along the side panel's right edge beside the card (same screenshot);
   - the pills ("Trip", "Waiting for its run") are in a monospace font: the body font, as the board's other pills
     (design/table-standard.md, its Control Board note; the Design reviewer's nit on task-trip-3).
2. **No Undo on any alarm's acknowledgement, everywhere** (CLAUDE.md #1314/#1315: a record is edited, never undone; Undo only for
   actions). task-trip did it for W1's whole-workflow trips only (`ControlAlarms::wholeWorkflowTrip`); every other trip still
   shows Undo after Acknowledge and the "Seen:" box (`control-console.php` tv_cb_alarms; `ControlAlarms::pulseSteps`). Show
   "Seen by …, <time>" as the state line instead; check dispatcher tests (r1, r2-notice) and board-views D5 for words to update.
3. **Diagnose** (Jason, 2026-11-23: "where I can go step by step through checking what's wrong and what needs to be done to
   resolve impedance"): a step-by-step process in Diagnostics, scoped to one item, generated from the item's dependencies (the
   one engine's radius/needs: `ControlEngine`, `Preflight::NEEDS`, `ControlRegister`). Mockup first on overture_bulk's real chain
   (W1 → task → /api/crawl/loads → Overture's source); Design reviewer → session manager → Jason once.
4. **The workflow-shape audit** (`research/workflow-shapes-2026-11-23.md`, read-only; live read through the test accounts;
   report to the session manager).
5. **The Ledger tab in Modules** (the session manager's spec, 2026-11-23): one row per paid account: service, sign-in account,
   plan, cap, spent this month, % of cap, owed or credit, last and next bill, the source (the service's live API or our own
   count, with any gap shown, e.g. Google 608 vs 30), and the billing link in the right account. Totals at the top; each row
   opens to its entries; promo credits shown apart, never counted against the cap.
6. **The Find box in System Configuration** (same spec): a box at the top ("/" opens it) searching services, key names,
   accounts, workflows, alarms and settings, jumping to the exact card. And each card's top line: status, spend against cap,
   one next action; the rest folds.
7. **Task #4, the jobs** (Payment reminders and sales_tick catching up after #269): read the 10:17 UTC `site-audit/results/
   jobs.json`; one line to the session manager; a fix in the next release if a job is still late. **And the gauge-requests clock**:
   confirmed starting it (run #4, 02:17 UTC); confirm it keeps running hourly, and that the session manager dropped its hand start.

8. **Request a fix's follow-through (part d of kit-buttons, still to build):** when the fix's release goes live, its check
   runs at once; the request then clears ("Cleared: its check passed") or reads "Fix tried in #N, still failing", with one
   notice through the dispatcher (decisions/0176).

## Rules that bind every item (CLAUDE.md)

- #1308/#1309: the four steps (see, do, done and confirmed, next only within a process); a task ends at its confirmation.
- #1312: a list or a process.
- #1314/#1315: Edit for records (with History); Undo only for actions; money never has Undo.
- #1316: inline actions are 30px round icons with titles.
- #1317: the suggested action, ✎ to correct, ⋯ for every other valid move (greyed with the reason).
- #1334–#1336: never invent what's set (grep decisions/ and the live code, cite them); a gap is offered as suggestions.
- #1341: every pop-up to Jason opens with "Decision (nothing changes on the site yet): goes live in <release>, <place>" or
  "Release (live about 10 minutes after merge): <what you'll see>"; "live" only for work synced and byte-checked.
- A button's verb matches what it does (Request a fix where it queues; Fix it only where the system acts now).

## Open notes

- Request #1 (rt:o-gather, overture_bulk/IR-0001) is recorded in `releases/gauge-pickups.json` for loads-reply.zip: once its
  check passes, remove the line (the file's note says so).
- The "Cleared: our ledger misread W0's receipt (fixed in loads-reply)" close text also applies to any later close of that alarm
  on a receipt call: narrow, left as is (the session manager knows).
- Under the one engine, a task's own-code trip pauses only its task (`ControlEngine::radius`); W1's two were older, so they held
  all of W1. Nothing to migrate once they clear.
- `/api/crawl/places: received 1, accounted for 0` and `/api/crawl/identity: received 2, accounted for 1` (W2) are open warnings
  of the same family as W0's: check whether those endpoints nest their counts too (`ControlLedger::counts`).

## Bench and tools (this session's)

- MariaDB: `mysqld_safe --user=mysql &`, user tv/tv, db lv_main; a bench root = the mirror's `sync-app` as `app/` plus
  `sync-storage/{admin,api,js}` under `website/`, the build over it, a `.env` two folders up (copy from an earlier bench).
- `tools/control-board-tests/run-all.sh <root>` (7 known live failures: engine.php ×2, lines.php, r1.php's zz_test endpoint,
  r4b.php's unused names, register.php ×2); `rendered-all.sh <root> '<names>'`.
- This session's tests: `gauge-panel.cjs`, `gauge-requests.mjs`, `load-batches.php`, `w0-receipt.php`, `stale-why.php`,
  `task-trip.php` (26), `task-trip.cjs` (54) with `task-trip-seed.php`; worker `tests/test_load_batches.py`.
- `live-read.yml` choices added: `ir0001` (ledger loads calls, a crawler run, alarms) and `w0` (ledger index calls, W0's page).
- Zip names are never reused: a rebuilt, unsent zip gets `-2`, `-3` (remove the unsent one's inbox zip and delivery record first).
