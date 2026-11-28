# The Design reviewer: hand-off (2026-11-23 to 2026-11-24, holiday wrap-up)

The standing Design reviewer session (Jason, 2026-11-23: "loop in the designer, and make it responsible for approving
builds based on design standards"). It didn't build anything. It approved or sent back every release and mockup that
changes what people see, before the session manager and Jason saw it (CLAUDE.md "Design review before Jason sees it", #1297).

## How a review runs

1. Read the screens at 1280 and 390, and render more where a state is missing. A bench was the live mirror plus the build
   over it, on MariaDB, using the build's own router or `handoffs/design-harness/` (its README has the steps).
2. Walk each changed path and press every new button once (the session manager, 2026-11-23: "your reply names what you
   walked and pressed").
3. Reply to the build session and the session manager in one of these forms:
   - **Mockup:** "Design: approved, not live" (#1341).
   - **Built release:** "Design: approved, release, live after merge".
   - **Changes:** "Design: changes needed", as numbered fixes, each with the rule it breaks and where on screen.

## The checklist, with each rule's source

Standards: brand.md §4 (tokens, the button standard: a filled button is brand brown `#92400E` with page-colour text,
5.18:1, one per view; umber only for bars), design/css-theme-brand.md (red only for trips and halts, amber for holds and
waits), design/table-standard.md (one table, group rows §8, paging §9, empty states §11), decisions/0058 §11 (phones
edge to edge at 3px, no sideways scroll), 0129 (the command strip).

Jason's rules from 2026-11-23 and 24, all in CLAUDE.md:

| Rule | CLAUDE.md |
|---|---|
| Design review before Jason sees a visible release | #1297 |
| Every action is a task: see it, do it, confirmed, next | #1308 |
| A task ends at its confirmation; a next step only within a process | #1309 |
| A list or a process: the three questions | #1312 |
| Edit, never Change or Undo, for entered information; Undo only for actions rolled back as if never done | #1314, #1315 |
| Icons with tooltips for inline actions; the primary action and state sentences stay words | #1316 |
| Suggested action, ✎ and ⋯ (every valid move, greyed with the reason) on each task or state line | #1317 |
| Money never has Undo (QuickBooks ↗ / Cancel the record / Refund…) | "Edit or Undo", decisions/0178 note |
| Never invent what's set; improvements as labelled suggestions; gaps flagged with suggested solutions | #1334, #1335, #1336 |
| Decision or release, said every time | #1341 |
| Outside links open in the browser, ↗ titled with the service and account; ours in Peek | #1344 |
| A button's verb says what happens (Fix it acts now; Request … when queued) | this hand-off's PR |
| What the system knows is applied, not asked; stale blocks hidden; aged items raise their question | this hand-off's PR |
| Lines that carry tasks have no × (0129 note) | this hand-off's PR |

The last three were given by Jason through the session manager on 2026-11-23 but weren't in CLAUDE.md yet. This PR adds them.

The other checks applied every time:
- one fact said once;
- counts agree on every surface;
- times are labelled in their stored zone (CLAUDE.md "Times"; I wrongly flagged a mixed MST/UTC list once and withdrew it);
- every pop-up closes by ×, Esc and a click outside;
- a failure is an error notice with its way forward (Send again, Copy, a mail link), never a green Success.

## What I approved (all now in main or live)

| Release | Session | Outcome |
|---|---|---|
| board-fixes (#269), its restore board-fixes-5 | Control Board | live |
| Orders release A, Collect the balance, Complete waits for the money | Orders | live |
| Gauge panel mockup rev 4 → release #273 | Control Board | live |
| kit-buttons (brand-brown filled buttons, Copy icons, Fix it → Request …, the gauge rows' ⋯) | Control Board | approved |
| loads-reply (the hand-off's words, W0's receipt words) | Control Board | approved |
| task-trip-3 (a trip's card replaced by its new state) | Control Board | approved |
| Order management mockups round 3; M1; M1b with the vendor-bill setting; M2; M2b "How it goes" (0101's methods) | Orders / Orders M1 / M2 | M2b paused by Jason (#1354) |
| board-phone-header (#280), work-queue (#281), task-scope (#282), task-adrs (#283) | Control Board | live |

## Open design notes (sent to the Control Board session for its Work queue)

Each note is given with its source:
1. Plain names for `/api/crawl/…` alarm titles, through `alarmTitle()` (board-phone review).
2. The Work queue's group rows need the ▾ (table-standard §8; work-queue review).
3. task-scope's build notes:
   - each step's See and Confirm lines come before its button;
   - an unset step says "Not set yet" with ✎;
   - the task's own next step sits on its page.
4. task-adrs: the detach notice names the decision.
5. M2b, when it resumes (build notes from its approval):
   - the unsent-email error's buttons go inside its box;
   - Ship mode reads "Not set" with ✎, its address fields opening only when needed.
6. Order page phone fixes still queued from Jason's Order #12 screenshots (my items 7–10):
   - Files & versions' add form stacks at 390, through the one uploader;
   - the explanations go behind ⓘ;
   - the per-item table becomes phone cards;
   - Print the master manifest becomes a heading icon.
7. Every order release's proof includes Order details, Files and Shipping at 390 (I missed these in M1 and M1b).

## 2026-11-27 (this reviewer's last day before the usage reset)

Approved, each with its notes:

| Release | Session | Outcome and notes |
|---|---|---|
| founder-link (b24dba4) | founder emails | Approved. The push has its title plus one sentence, with no address. There's one named button per email, and the phrase itself is the link. The footer is "You get this email because you're the Founder. Change what reaches you". "From the CEO" and the footer are #5E4F3C (7.65:1; #8A7355 failed at 4.36:1). Note: the text part's address shouldn't end in a full stop. |
| Events E1 (2196f08) | Frontend & Shop | Approved. Row moves are outlined, and New event is the one primary. ✎ and 🕒 sit together, with History below. History lines name each move "(was <State>)". |
| Events E2 (f80f090) | Frontend & Shop | Approved. A cancelled event shows "Tickets: sales stopped", plus "Have tickets?" with a way forward (no refund terms). "More at <host>" has no outer card. A stock hero carries its credit. |
| Events P1 + P2, pilot scope (2913dec, no participant codes) | Frontend & Shop | Approved. "Your offer" is labelled, with its hint, and is one line tall. Save offer and Leave sit below it at 390. P2's cards follow §11. Send the evening to Maps is in. |
| C.5a Get Local list hold (3769606) | Frontend & Shop | Approved. Note: aria-busy and a polite live region on "Loading…". |
| C.5b guide alerts and roads (7cb1da9) | Frontend & Shop | Approved. Note: drop stored items past their own end time when the page is drawn. |
| posts-pdo visitor error line | Frontend & Shop | Approved: "Something went wrong on our side. Try again in a minute; if it keeps happening, tell us at support@traversence.com." |
| writeOrder inbox words (1d20e63) | writeOrder | Approved on two conditions. (1) "Our team has been told" must raise the team's case in the same step, and a test must check it. (2) The retry release's message 2 must also cover invoices the team added by hand. |

Other rulings:
- Tailwind's `/40`, `/60`, `/70` on var() borders never drew. Rewrite them with color-mix in the stylesheet release (login.php:35 and the home and listing borders), with before and after screens at 1280 and 390.
- The P2 minimum-order line, "· code X on orders of $N or more until <date>", was approved and then dropped with codes.
