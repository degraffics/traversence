# Decided, not live yet

Kept by the session manager (CLAUDE.md, "Decision or release: said every time"; Jason, 2026-11-24). Each approved
decision, the release that will put it live, and its place in the queue. A line moves to "Live" only when its release
is synced and byte-checked. Updated with every approval and every release.

## Building now

| Decision (when) | Release | Session | Next step |
|---|---|---|---|
| Change how an order goes: two legs on 0101's existing fields (approved 2026-11-24) | M2b, PR #1346, built and reviewed | Orders | **Paused by Jason 2026-11-24 ("Pause for now. add to work queue.")**: goes into the Work queue. Out of flight: no inbox zip, claims released; PR #1346 merged as the record only (2026-11-25); its build in `handoffs/order-m2b/`, how to resume in `handoffs/order-management-m2.md` (Final hand-off) |
| No Undo on an alarm's acknowledgement (CLAUDE.md #1314) | no-undo, built and tested, **not sent** | Control Board (archived) | in the Work queue (task: No Undo…), its build in `handoffs/no-undo/`, branch `claude/no-undo`; needs a Design look and Jason's pop-up |
| Work queue phase 2: a task's thread (Jason's spec, 2026-11-24) | design only (`handoffs/work-queue-phase2.md`) | — | in the Work queue for Jason after his four-stage redesign |
| 0179: publish authority, interim daily batch, target safe kinds alone (2026-11-25) | **Decision, in effect now for sessions** (daily batch); rule 2 waits for piece 3 | session manager | the daily summary written in chat until piece 2 builds its page |
| Piece 2: Team roster, three ticket types, agent runner, staging only (2026-11-25) | not started | to be assigned | build brief, then one build session |
| Piece 3: before-and-after check, Undo per agent action, spend limit per agent (2026-11-25) | not started | — | after piece 2; switches on 0179 rule 2 |

## Queued, in order

**From 2026-11-25 the Work queue on the System Control Board's Development page is the queue** (Jason: "We will resume our work from the work que"): every item below is a task there, with its scope, priority, decisions and docs (#284, #285, task-seed-3). The lists below are kept as history.

### Control board (handoffs/control-board-2026-11-24.md)
1. ~~Phone header~~ live as #280
2. No Undo on any alarm's acknowledgement (CLAUDE.md #1314)
3. Diagnose: step by step, from the outside in (2026-11-23)
4. Workflow-shape audit: a report to Jason first (2026-11-23)
5. Ledger tab in Modules (2026-11-23)
6. Find box in System Configuration (2026-11-23)
7. Request a fix's follow-through: the check runs when the fix goes live; one notice (2026-11-23)
8. Outside links open in the browser: ↗ with the service and account; the first-tap hint on phones (2026-11-24; CLAUDE.md)

### Orders (research/order-management-design-2026-11-23.md §6; handoffs/order-management-m2.md, Final hand-off)
0. M3's first fix: the order tests green again (the harness schema to the live tables, seeds that clear what they count)
1. M2b: how an order goes (above), when Jason resumes it
2. M3: the Money section: Outstanding with aging, Take a payment across orders, Credits on account
3. M4: Place an order, guided; the archived E-Commerce orders page retired
4. M5: several destinations (decisions/0177)
5. M6: the catalog: Services, Categories
6. M7: credit terms, Net 15 / Net 30, granted by Jason (0178)

### Later
- Client order emails through the one dispatcher (0172)
- A vendor's default category from QuickBooks, once confirmed in the sandbox

## Live today (2026-11-23/24), each synced and byte-checked

#271 restore · #272 Collect the balance · #273 gauge panel · #274 Orders M1 · #275 kit buttons and Request a fix ·
#276 M1b What's open · #277 crawler batches · #278 W1 fix and alarm card · #279 M2 refunds and credit memos ·
#280 the board on a phone · #281 the Work queue · #282 a task's page (scope, priority, steps) · #283 decisions on a task ·
#284 the scope doc's seed (its migration cleared unrun, see task-seed-2) · #285 the scope doc's tasks, run · task-seed-3
(everything outstanding before the holiday, merged #1368, live once synced)
