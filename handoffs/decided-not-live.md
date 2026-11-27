# Decided, not live yet

Kept by the session manager (CLAUDE.md, "Decision or release: said every time"; Jason, 2026-11-24). Each approved
decision, the release that will put it live, and its place in the queue. A line moves to "Live" only when its release
is synced and byte-checked. Updated with every approval and every release.

**Company goal (Jason, 2026-11-26, to the CEO):** "Work with your team to analyze the entire platform and let me see
your best plan for development based on both the frontend and back end being ready by Jan 1 for market roll out." The
front end and the back end ready for the market roll-out on **January 1, 2027**. The CEO's plan comes to Jason after the
session manager's review.

**Company priority (Jason, 2026-11-26, to the CEO):** "No real numbers yet, I've kept the system locked for SEO until
its built right. I want the frontend and the shop ready from end to end so we can strategies how to penetrate 1a properly."
1A is regions.md's St. Johns, Springerville, Eagar, Show Low, Holbrook, Winslow and the Petrified Forest gateway.
"Ready" is measured by 0145's paths (`tools/page-audit/paths.mjs`).

**Open with the Founder:** Order #12's books ($1,111.14 invoiced against $809.10 of lines): "I will address this after
holiday" (Jason, 2026-11-27). Nothing changes until he answers. Brief D (the CEO's) comes by Dec 14–21.

## Building now

| Decision (when) | Release | Session | Next step |
|---|---|---|---|
| Change how an order goes: two legs on 0101's existing fields (approved 2026-11-24) | M2b, PR #1346, built and reviewed | Orders | **Paused by Jason 2026-11-24 ("Pause for now. add to work queue.")**: goes into the Work queue. Out of flight: no inbox zip, claims released; PR #1346 merged as the record only (2026-11-25); its build in `handoffs/order-m2b/`, how to resume in `handoffs/order-management-m2.md` (Final hand-off) |
| No Undo on an alarm's acknowledgement (CLAUDE.md #1314) | no-undo, built and tested, **not sent** | Control Board (archived) | in the Work queue (task: No Undo…), its build in `handoffs/no-undo/`, branch `claude/no-undo`; needs a Design look and Jason's pop-up |
| Work queue phase 2: a task's thread (Jason's spec, 2026-11-24) | phase 2 release 1 (`handoffs/work-queue-phase2.md`) | Piece 2/3 | the four-stage redesign is the team's, inside the transition plan (Jason, 2026-11-27): Piece 2 drafts, the Design reviewer approves, the CEO's Tier 4; Jason sees it once as the release |
| The transition plan: our business and conversations move onto the platform (Jason, 2026-11-27: "Yes, the transition plan") | task-seed-4, then phase 2 release 1 (with `releases/task-notes.json` and `releases/task-rulings.json`), then the Executive channel; cutover about Dec 15 after a parallel week | Piece 2/3 | after the pipeline release and R2, alongside A3; launch work wins against 0181's gates; $0 |
| 0179: publish authority, interim daily batch, target safe kinds alone (2026-11-25) | **Decision, in effect now for sessions** (daily batch); rule 2 waits for piece 3 | session manager | the daily summary written in chat until piece 2 builds its page |
| Piece 2: Team roster, three ticket types, agent runner, staging only (approved and pre-approved to publish, 2026-11-25) | P2-2 team-roster, P2-1 team-tickets, P2-3 team-runner, P2-4 audit, P2-5 daily batch, in that order | Piece 2 build (session_01CXLi5kEx1NbamxWYoRxUDh) | built and reviewed; each sent once the one before is live and synced |
| Gaps for Jason's return (session manager's holiday rulings) | — | — | Q-A: tickets reach the Team by a person's "Give it to the Team", or by themselves only when the audit raised them; Q-B: no stall limit per stage (none decided), so no stall step |
| The site's stylesheet built once with the tailwindcss CLI, in place of the Play CDN (Jason, 2026-11-27: "Yes to the stylesheet"; note on `design/css-theme-brand.md`) | its own release, after C.5a and C.5b | Frontend & Shop | a plan to the session manager first, then the Design reviewer at 1280 and 390 on every page kind |
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

- **2026-11-26 batch approved** (Jason, ~00:50 UTC: "No need to wait for approval. I want to see the agent work a bit."): P3-3, P3-4, P3-5 (Tier 3, 0066), notify-founder (counts as "Approve notify"), then P3-6 the backend fence with no batch wait. Sent one at a time in that order. Staging's three switches go on via the standing OK. Agents stay at Planning until Jason approves the decision tree.
- **decisions/0180 accepted** (Jason, 2026-11-26): the five tiers, the hard money line, the 32 roles plus 12 advocates. Rollout in order: R1 rename and roster, R2 swimlanes and locks, R3 Tier 4 and the Prior Ruling Check, R4 advocates' experience tickets, R5 rule-based auto-rollback and the fidelity fallback. Nothing in it raises a ceiling or a fence.
- **Executive channel in the platform** (Jason, 2026-11-26; 0180's notes): the Work queue's "+ Add" gets To: the CEO (narrowed from the Executive Suite, 2026-11-26); his post becomes an item the suite reviews and answers in its thread; built after R2 with Work queue phase 2's thread. The Executive Suite session itself is running now.
