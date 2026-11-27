# 0180: The organization and five-tier governance: who decides what, and the hard money line

Status: **accepted** (Jason, 2026-11-26, in chat: "Accepted … Proceed with the rollout steps as sequenced"). Drafted by the session manager from his "Organizational Architecture, Role Mandates & 5-Tier Governance" specification and his final calls. His two clarifications are in §4 and §6, and his answers to the open questions are at the end.

Builds on:
- **0179** (publish authority and escalation) and its notes.
- **0165** (staging).
- **0066** (tribal nations' land).
- **0164** (the Knowledge Engine; nothing private to free external APIs).
- **0161** (file claims).
- **0178** (money: no Undo; refunds).

Amends 0179. Each place it amends carries a **Note for Jason**:
- 0179's three tiers become **five**: Tier 1–2 autonomous, Tier 3 middle management, Tier 4 executive suite, Tier 5 Founder ruling.
- 0179 rule 3's list, "always Tier 3 (Founder only)", is replaced by §3 and §4 below. The protected areas still get the most review, but touching one no longer sends the change to the Founder by itself. What reaches the Founder is a change to the rules, new policy, an irreversible production act, a deadlock, or real money moving.

## Why

Jason, 2026-11-26: the team should have "real operational authority inside their swimlanes and executive scopes", with the Founder seat "focused purely on strategic direction and vetted high-stakes rulings". Under 0179 every touch of a protected area came to him, even a billing bug fix that only carries out a settled decision. That keeps the platform from being autonomous.

## The decision (Jason's calls, 2026-11-26)

### 1. Five tiers

| Tier | What it covers | Who decides | How it goes live |
|---|---|---|---|
| **1: Autonomous polish** | Unit tests and minor bug fixes; copy, typos, the FAQ, formatting; layout polish, the 390px phone layout, contrast, spacing, clearer labels | Operational specialists, within their lane and the active fence | On staging, then publishes by itself once staging's checks and the before-and-after check pass. In the daily summary with Undo and Roll back. |
| **2: Standard operations and growth** | Safe functional updates; internal tools; additive database changes (new table, column or index); billing code and workflow bug fixes that change no price, charge, refund, payout or ledger rule (diagnosed, built and staged here; before live they also need the Tier 4 review in §4); crawler and ingestion reliability fixes within the $0 cap; regional guides, listing enrichment, campaign workflows, SEO and taxonomy; non-protected UI, onboarding steps, clutter reduction | Operational specialists, under their department manager. The Accounting Manager or IT Manager verifies billing and crawler batch fixes. | As Tier 1, within the reach fence (Backend only until the Founder widens it) |
| **3: Middle-management triage** | Hand-offs between lanes; multi-file changes; non-protected workflow redesigns; diagnosing billing workflow bugs or crawler problems; recurring audit anomalies; support and onboarding edge cases; proposing operational boundary adjustments | The seven middle managers (§5), under executive guidance | The manager resolves it, runs the Tier 2 work, or routes it to Tier 4 |
| **4: Executive suite** | Guides Tier 3; filters word-only false alarms; authorizes operational boundary adjustments (§2); reviews **every** change that touches one of the seven high-risk territories (§4) and runs its **Prior Ruling Check** | The executive suite: CEO, COO, CFO, CTO, CIO, CMO | It stays in Tiers 2–4 when the Prior Ruling Check passes (§3). Otherwise it goes to Tier 5. |
| **5: Founder ruling** | See §3's list | The Founder | A **4-part Decision Brief** delivered by the CEO: (1) the territory or rule triggered, (2) the options weighed, with trade-offs and platform-wide impact, (3) the executive recommendation, (4) the Founder's ruling options |

**Labels.** Every surface reads "**Founder ruling**" beside Tier 5: the Team page, the Work queue, the daily summary, Decision Briefs and the FAQ. The renumbering ships as **one release** that renames it everywhere at once (concern 5), so no screen means the old Tier 3 and the new Tier 3 at the same time.

### 2. Authority and responsibility (concern 2)

Roles have real operational authority inside their swimlane (Layer 3–4) and their executive scope (Layer 2). They adjust workflows, queues, layouts, screens and step order to do their jobs, without asking.

Only these need a **Founder ruling (Tier 5)**:
- a change to the **decision-tree rules**, the **tier definitions**, a **role's permissions**, or a **release fence** (stage ceilings, reach, the spend cap). These are the rules the tree's fingerprint covers (0179's note), so a change still clears the tree's approval.
- an **amendment to a settled ADR**, or **new policy** where no ADR exists.

An "operational boundary adjustment" (Tiers 3 and 4) is anything inside those rules. If one would change a rule above, it is Tier 5.

### 3. The Prior Ruling Check, and what reaches the Founder

Before anything is escalated to Tier 5, the executive reviewing it runs the **Prior Ruling Check**: does the action strictly **implement, enforce or repair** what an existing ratified ADR, 0066 or a governance rule already says?
- **Yes:** it stays in Tiers 2–4, through staging, the before-and-after check and Undo or Roll back. The check is recorded on the ticket, citing the ADR and section it relies on. It is listed in the daily summary as "Kept in Tier 4 by the Prior Ruling Check: <ADR §>", so the Founder can see and overturn it.
- **No:** it goes to Tier 5 when the action:
  - **amends, weakens or overrides** an ADR or a Founder decision;
  - **creates new policy** where no ADR exists;
  - is an **irreversible or destructive production action** that no ADR pre-authorizes;
  - is an **executive deadlock**;
  - or crosses the **hard money line** (§4.1).

The Prior Ruling Check is reasoning, so it runs in a Claude session (§6), never in a free external model. An item it can't settle with a cited ADR goes to Tier 5.

### 4. The seven high-risk territories (Tier 4 review, Tier 5 on the triggers in §3)

Detection stays as 0179's note set it: by **what a change touches** (libraries, tables, pages, jobs, services), using `TeamRunner::TIER3_AREAS` (renamed with the tiers). Words only raise a flag for a person to check.

**This check takes precedence over §1's tier** (Jason, 2026-11-26). A change that touches a territory never publishes as plain Tier 1 or Tier 2 without its Tier 4 review recorded. For example, a billing bug fix that touches Money and billing code:
- the team diagnoses, builds and stages it under Tiers 2–3;
- Tier 4 records its review and Prior Ruling Check before live release;
- it stays out of Tier 5 when it only fixes a bug within settled rules and moves no real money;
- it goes to Tier 5 if it changes a billing rule or would move real money.

The seven territories:
1. **Money and billing:** orders, carts, checkout, subscriptions, ledger entries, refunds, payouts, wallet cards, domain purchases, QuickBooks, Stripe, Square.
2. **Major data mutations:** any migration or operation that UPDATEs, DELETEs, DROPs, MODIFYs, RENAMEs, TRUNCATEs or CHANGEs existing structures or records. Additive tables, columns and indexes stay Tier 2. This is the same list as the code (concern 4); "ALTER" alone is not on it, since adding a column is an ALTER.
3. **Public addresses and routing:** short codes (trvgo.us), business domains, `.htaccess`, routers, page registers, Cloudflare, Name.com, Fly.
4. **Tribal nations' land (0066):** landmarks, nations, the held-names check, heritage and landmark jobs, any public surface that could expose protected locations.
5. **Privacy and identity:** user records, staff accounts, authentication and step-up, two-step devices, sessions, messages, contact cards, account deletion.
6. **Mission alignment:** the core purpose, the zero-commission commitment, community trust standards.
7. **Deadlocks:** rule conflicts or priority deadlocks across departments or executives.

#### 4.1 The hard money line (concern 1)

Code and accounting work are separated from live money moving.
- **Within the team's authority (Tiers 2–3, without asking):** fixing billing code, repairing billing workflows, reconciling ledger entries, resolving discrepancies, and preparing invoices and refund *records* per established policy and ADRs.
- **Never autonomous:** executing a real-money charge, payout, transfer or refund, and any external spend above $0. The system holds this at the payment and connector level, never by an agent's judgment:
  - `QuickBooksPay::guardReal()` and the connectors refuse a call with no person's press behind it;
  - agents and their sessions have no path to a live payment key;
  - staging refuses payments by design (0165).

  Executing a prepared refund still takes a person, as 0178 sets ("Refund…, with Jason's OK").
- A billing **rule** change (price, charge, refund, payout, ledger rule) is not a bug fix. It is new policy or an ADR amendment, so it is Tier 5.

### 5. The organization: 4 layers, 32 roles, plus the advocates

- **Layer 1:** the Founder.
- **Layer 2:** the executive suite: CEO, COO, CFO, CTO, CIO, CMO.
- **Layer 3:** seven middle managers, each with an **exclusive swimlane**: Project Manager, Customer Service Manager, Accounting Manager, IT Manager, Editorial Director, Marketing Director, Sales Director.
- **Layer 4:** operational and guard specialists (roles 15–32 in the mapping below).

Every ticket routes to exactly one of the seven middle managers by its domain.

The **test-account advocates** sit beside the layers. They do two jobs:
- **QA verification:** they are the before-and-after check's views (0179 rule 2, Piece 3).
- **Experience tickets:** a real persona's friction or UX finding opens a ticket routed to the middle manager whose lane owns that screen. That manager may propose a Planning initiative from it. An advocate never builds or moves a ticket itself.

The mapping from the 32 roles to today's roster is in the appendix. Roles stay **standby** until the Founder makes them active (the Team page's switch). A role on standby is skipped upward: its work goes to the next active role above it, and if none is active, to the Founder. Nothing is ever dropped.

### 6. How roles reason (concern 3)

Each role is grounded in four things, kept on its Team page record:
1. the ADRs and docs it must follow;
2. its job description (this decision);
3. its job knowledge (its SOPs, queues and reports);
4. its escalation support (who it hands to, and when).

**Routine execution** runs as fixed-form templates and code at $0, like today's steps (gate, brief, audit). **Deeper reasoning** runs in a Claude session within the existing allowance: Tier 4 reviews, the Prior Ruling Check, and Tier 5 Brief drafting.

Private material (0164) never goes to a free external API, Gemini's free tier included. The $0 agent spend cap stands.

**Keep the reasoning lean** (Jason, 2026-11-26).
- Routine checks and templates run in code or fixed form first.
- A Claude session is used only when code can't settle the question.
- A Prior Ruling Check is concise and targeted. It gets only the change, the areas it touches and the ADR sections it may rely on, never the whole history, and it answers with a citation and yes or no.
- Each one's session use is logged, so the daily summary shows what Tier 4 reasoning cost.

### 7. Guardrails that stay

- **Staging first.** All agent implementation happens on staging. The live site changes only through checked releases with Undo and Roll back.
- **Spend.** The $0 agent spend cap.
- **People's work stays theirs.** Agents never take a ticket a person owns, never write over a person's words, never act through a test account, and work any one ticket at most once an hour.
- **One owner at a time.** Single-owner locks: one manager or specialist per ticket, and one claim per file or function (0161).
- **Sequential hand-offs.** Cross-lane work moves one lane at a time, with hand-off notes.
- **No duplicates.** The audit checks open tickets and recent releases before opening a ticket.
- **Settled ideas are reused.** Before designing anything, the ADRs and 0066 are searched and cited. A gap is filled by extending or amending an ADR, never by a parallel concept.
- **Fences until the Founder moves them.** The stage ceilings, reach fence, spend limits and standby/active switches stay where the Founder set them.

### 8. Checks after release (concerns 6 and 7)

- **Automatic rollback is rule-based only.** A failed live check rolls a release back by itself: the before-and-after rules, server errors, a broken phone layout, a lost primary action. "Drift from the plan" judged by an agent opens a ticket, never a rollback.
- **Fidelity to the spec.** The staging page is checked against the Founder's approved mockup or design decision where one exists. Where none exists, it is checked against `brand.md`, `design/css-theme-brand.md`, `design/table-standard.md` and 0058 §11's 390px rules, and the check says which standard it used.
- **The simplicity veto.** The Cognitive Load & Simplicity Guardian and the Editorial Director may hold a screen before release for clutter or a missing "one clear next action". Holding it opens a ticket with the reason.

## How it rolls out (each a release, evidence-gated as 0179 sets)

1. **Rename and roster:**
   - Tiers 1–5 and "Founder ruling", everywhere at once.
   - The 32 roles seeded from the appendix. Today's active roles keep their status; every new role starts on standby.
   - The tree's fingerprint changes, so it is approved again.
2. **Swimlanes and locks:** each ticket's owning middle manager, single-owner locks and sequential hand-offs.
3. **Tier 4 and the Prior Ruling Check:** executives' reviews run in Claude sessions, with the ADR citation recorded and the daily-summary line.
4. **Advocates' experience tickets.**
5. **Rule-based auto-rollback and the fidelity fallback.**

Nothing above raises a stage ceiling or the reach fence. Those stay the Founder's presses.

## Jason's answers (2026-11-26)

1. **The roster is the 32 roles of Jason's specification of 2026-11-26, and only those** (Jason, 2026-11-26: "make sure you are using what I sent you this morning"). Six roles from the earlier Piece 2 roster are not among them: Legal Risk Checker (4), Mission & Brand Integrity Auditor (5), Historian (9), Regional Directory & Data Curator (14), Preflight, Print & Trade Vendor Specialist (18) and AI & Dynamic Guide Quality Auditor (20). They are **retired**: never run, and **not shown on the Team page at all** (Jason, 2026-11-26). Their rows stay in the database only so past tickets and runs still resolve their names; the mapping lives in this decision's appendix. Their duties are carried by the homes in the appendix (for example, directory accuracy by Front-End Content Writers, 26).
2. Keep all **12** advocates, A1–A12, including the Staff Views Advocate (#34).
3. Refunds: 0178 stands. A real refund executes only "with Jason's OK"; the team prepares refund records and fixes.

## The questions as asked

1. **Old roles not in the 32.** Six of today's roles have no seat of their own in the new list. The appendix suggests a home for each:
   - Legal Risk Checker
   - Mission & Brand Integrity Auditor
   - Historian
   - Regional Directory & Data Curator
   - Preflight, Print & Trade Vendor Specialist
   - AI & Dynamic Guide Quality Auditor

   Confirm, or keep any as its own role.
2. **Advocates: 12, not 11.** The Staff Views Advocate (#34, added in Piece 3 at your "Staff Views yes") checks admin pages as staff. It is kept as a 12th advocate.
3. **Refunds.** Under §4.1 the team prepares refund *records*. Executing one stays with a person, as 0178 sets. Confirm that 0178's "with Jason's OK" still stands, or name who may press Refund.

## Appendix: the 32 roles mapped to today's roster

"Today" is the roster seeded in Piece 2 (`handoffs/piece2-team.md` §7; Jason's spec numbers in `handoffs/piece2/jason-spec.md` §11).

| # | Role (0180) | Layer and reports to | Today's role (spec #) | Status at rollout |
|---|---|---|---|---|
| 1 | Founder / Chair | L1 | Executive Lead & Founder (1) | the real login |
| 2 | Chief Executive Officer | L2 → Founder | new | standby |
| 3 | Chief Operating Officer | L2 → CEO | new | standby |
| 4 | Chief Financial Officer | L2 → CEO | new | standby |
| 5 | Chief Technology Officer | L2 → CEO | new | standby |
| 6 | Chief Information Officer | L2 → CEO | new | standby |
| 7 | Chief Marketing Officer | L2 → CEO | new; takes in Mission & Brand Integrity Auditor (5)'s mission duty | standby |
| 8 | Project Manager | L3 → COO | Project Manager (6) | **active** |
| 9 | Customer Service Manager | L3 → COO | Customer Service & Member Support Agent (13), raised to manager | standby |
| 10 | Accounting Manager | L3 → CFO | new; takes the oversight half of Finance, Tax & Billing Controller (11) | standby |
| 11 | IT Manager | L3 → CTO and CIO | new | standby |
| 12 | Editorial Director | L3 → CMO | new | standby |
| 13 | Marketing Director | L3 → CMO | new | standby |
| 14 | Sales Director | L3 → CMO | new; takes the lead half of Growth, Merchant Onboarding & Partnerships Agent (12) | standby |
| 15 | Cognitive Load & Simplicity Guardian | L4 → Project Manager | Cognitive Load & Simplicity Guardian (3) | standby |
| 16 | Operations & Policy Director | L4 → Project Manager | Operations & Policy Director (7) | standby |
| 17 | Order & Fulfillment Lead | L4 → Customer Service Mgr | Order & Fulfillment Processing Agent (10); suggested home for Preflight, Print & Trade Vendor Specialist (18) | standby |
| 18 | Customer Support Specialists | L4 → Customer Service Mgr | the support half of (13) | standby |
| 19 | Finance, Tax & Billing Ops | L4 → Accounting Mgr | Finance, Tax & Billing Controller (11), read and prep only | standby |
| 20 | System Architect | L4 → IT Manager | System Architect (15) | **active** |
| 21 | Build & QA Sessions | L4 → IT Manager | Full-Stack Builder (21) and Technical QA & Security Gatekeeper (22) | **active** |
| 22 | Staging & Release Workers | L4 → IT Manager | new: the release pipeline and the before-and-after check, as a role | standby |
| 23 | Operations & Reconciliation Manager | L4 → IT Manager | Operations & Reconciliation Manager (8) | **active** |
| 24 | Schema & Migration Guards | L4 → IT Manager | new: the zip check's migration rules (ReleaseKind) | standby |
| 25 | Privacy & Decision 0066 Guards | L4 → IT Manager | new; suggested home for AI Legal Risk Checker & Auditor (4) and the 0066 half of (5) | standby |
| 26 | Front-End Content Writers | L4 → Editorial Director | Content & Copy Editor (19); carries the duties of retired 14 and 20 | **active** (as 19 is today) |
| 27 | Public UI/UX Designers | L4 → Editorial Director | UI/UX Prototyper & Flow Designer (16); Senior Designer (17) for visual assets | standby |
| 28 | Executive Translator & Briefing Lead | L4 → Editorial Director (CMO/CEO) | Executive Translator & Briefing Lead (2) | **active** |
| 29 | Brand & Campaign Strategists | L4 → Marketing Director | new | standby |
| 30 | SEO, Taxonomy & Funnel Analysts | L4 → Marketing Director | new | standby |
| 31 | Merchant Onboarding Specialists | L4 → Sales Director | the onboarding half of (12) | standby |
| 32 | Partnerships & Retention Leads | L4 → Sales Director | the partnership half of (12) | standby |
| — | Retired (earlier roster, not in the 32) | — | Legal Risk Checker (4) → duties to 25; Mission & Brand Integrity Auditor (5) → 7 and 25; Historian (9) → 12; Regional Directory & Data Curator (14) → 26; Preflight, Print & Trade Vendor Specialist (18) → 17; AI & Dynamic Guide Quality Auditor (20) → 26 | **retired** (kept for history) |
| A1–A12 | Test-account advocates | beside the layers; experience tickets go to the owning middle manager | Advocates (23–33) and Staff Views Advocate (34) | active, views only |

## Note (Jason, 2026-11-26): run it now

"Ship the pre-R1 fixes and roll out Decision 0180 now—don't hold roles on standby or stall on phased rollouts when I'm ready to run it. Activate the organization and execution loop so I can evaluate real actions and live-site changes in action. … I want all agents able to work on the backend, once proven we will grant access to entire platform."
- **Every role is active at R1.** All 32 seats, not only the seven carried over. Retired roles stay retired and unshown.
- **R1–R5 ship back to back.** Each ships as soon as its own tests and before-and-after check pass; none waits for a batch or a hold. The order stays R1 → R5 only where one needs the one before.
- **Reach stays Backend only.** Jason widens it to Everywhere once agents are proven on the backend.
- **The guardrails, unchanged:**
  - the hard money line (§4.1) and $0 spend;
  - Tier 5 only on its real triggers (§3), so routine fixes, workflow and layout adjustments, and work that carries out a settled ADR are never blocked;
  - reuse of the ADRs and standards (§7);
  - rendered checks, including 390px and the simplicity veto, with rule-based rollback (§8).
- **The Founder's own presses stay his**, by design: approving the decision tree, and raising the stage ceiling (Building, Implementation, Confirmation).

- **No buttons for Jason where the build can set the state** (Jason, 2026-11-26: "Don't ask me to press buttons for
  things you can set or wire directly in code/config"). R1 seeds, as data with a `data_reason` citing this note:
  - the decision tree approved, recorded as "Approved by Jason in chat, 2026-11-26 (decisions/0180)", tied to R1's own
    rule fingerprint. It is never shown as his press;
  - the ceiling at Implementation;
  - reach at Backend only;
  - staging's "Staging: the Team's runner" on for Everyone. It is set on staging only, and never turns the runner on
    live, which runs only on staging.

  The Founder-only guards stay in the code. They stop an agent or a job changing these. A release written on the
  Founder's written call is not an agent.
- **Correction, and the Founder's one-time setup** (Jason, 2026-11-26, in chat, in his own words: "I approve the decision
  tree, Raise to Building, and Raise to Implementation on https://traversence.com/admin/config/team, it's a one-time
  setup—not recurring permission").
  - The note above first said R1 would pre-seed the approval and the ceiling. The build session refused, rightly, while
    the Founder hadn't said it himself.
  - He has now said it. R1 records this as his approval of the R1 decision tree (its fingerprint), with the ceiling at
    Implementation. The record reads "Approved by Jason in chat, 2026-11-26: one-time setup (decisions/0180)".
  - **It is one time only.** Any later change to the tree's rules clears the approval and drops agents to Planning, as
    0179's note sets. A new approval is then his own press or his own written words again, never a carried-over one.
- **The Founder works directly with his executives** (Jason, 2026-11-26: "I expected to work directly with
  executives"; "Suite is good. I also expected to have these discussion in the system, moving away from using claude
  code chat").
  - **Now:** a standing "Executive Suite" Claude session, chaired by the CEO. It answers as the executive whose scope
    the topic belongs to, within §1–§4. It sends work to the build session, and the session manager keeps one release
    queue.
  - **In the platform, next:** a "Talk to your executives" panel on the Team page, with threads on tickets (Work queue
    phase 2's design, `handoffs/work-queue-phase2.md`), built after R2, with the Executive Suite as the voice that
    answers. It has no paid AI: replies travel through git to the session, so they take minutes. Every conversation is
    kept beside its tickets.
- **Where the Founder talks to the executives: the Work queue's "+ Add" (create new)** (Jason, 2026-11-26: "a part of the
  work que, create a new, should allow me to select executive suite and post my wants and needs there, for their review
  and response"). This replaces the Team page panel in the note above.
  - "+ Add" offers **To: the Executive Suite** beside giving work to the Team. His post becomes a Work queue item
    addressed to the suite.
  - The Executive Suite reviews it and answers on the item, as a thread. The answer names the executive speaking, cites
    its decisions, and gives the plan or the tickets it opens. Tickets the suite opens link back to the post.
  - Built after R2, with Work queue phase 2's thread (`handoffs/work-queue-phase2.md`). The answers travel through git
    to the Executive Suite session, with no paid AI, so they take minutes.
- **Two rule sets bind every agent, and the CEO comes first** (Jason, 2026-11-26).
  - "The executive suite is made up of system agents so what they do is bound by both, 1. Management 2. System policy
    and procedures": an agent needs both to allow an act. Management sets its role, lane, authority and direction. System
    policy sets how any agent works (spend, staging, checks, rollback, the money line, other people's data). The Founder
    sets and changes both.
  - "We can focus on the ceo first as my key management contact which I will use to build out the rest of the team and
    monitor performance": the Founder's channel is to the **CEO**. The CEO builds out the rest of the team with him and
    reports on its performance. The Work queue's "+ Add" offers **To: the CEO** (the note above, narrowed to the CEO).
  - "Only people can have paid for services right now": **no agent has a paid service**, the CEO included. The $0 agent
    spend cap stands. The CEO answers through the $0 route (a Claude session working within the Founder's own
    allowance, via git, in minutes). Free Gemini may be used for public material only (0164).
- **The Founder's and the CEO's AI budget** (Jason, 2026-11-26: "We have Google ai, so free gemini has been replaced";
  "A. Myself and ceo have a small budget for use of both llm and Google ai").
  - Google AI is a paid subscription (a person's service), so the free-tier limit is gone: internal business material
    may go to Gemini as it does to Claude.
  - Other people's data goes only under protecting terms, which a paid plan meets. Held 0066 names go nowhere.
  - **The CEO is the one agent with paid AI.** It shares a small budget with the Founder for Claude and Google AI,
    used when answering him and building out the team. Every other agent stays at $0.
  - The amount is the Founder's to set. Until he names it, the CEO keeps to the minutes route and spends nothing extra.
- **The CEO builds the team; the budget is $20 a month** (Jason, 2026-11-26: "CEO builds it. $20 a month").
  - **R1's roster:** active are the CEO (2) and today's working seats: Founder (1), Project Manager (8), System Architect
    (20), Build & QA Sessions (21), Operations & Reconciliation Manager (23), Front-End Content Writers (26) and
    Executive Translator & Briefing Lead (28). Every other role starts on standby.
  - The CEO brings each other role online with the Founder as the work needs it, and reports on how each performs.
    This replaces "every role is active at R1" in the "run it now" note. Retired roles stay retired and unshown.
  - **The Founder's and the CEO's shared AI budget is $20 a month,** for Claude and Google AI together, with a hard
    stop at the cap. At the cap the CEO falls back to the minutes route. Every other agent stays at $0.

- **Every role is available; the CEO staffs it without asking** (Jason, 2026-11-26: "Hiring proposal? Ive put all
  agents available...").
  - All 32 roles are the CEO's to use. Bringing a role online is the CEO's own management act inside 0180: no proposal
    to the Founder and no press by him. Each activation is logged on the Team page with who, why and the work it's for,
    and is undone by putting the role back on standby.
  - At R1 the CEO's first picks are active with the working seats: IT Manager (11), Staging & Release Workers (22),
    Schema & Migration Guards (24) and the Cognitive Load & Simplicity Guardian (15).
  - The CEO reports on staffing and performance in its updates. Founder rulings stay Tier 5 only.
- **All roles on at R1** (Jason, 2026-11-26: "All agents should be on and ready to deploy their duties and
  responsibilities"). It replaces the standby set in the note above: R1 seeds all 32 roles active, with the 12 advocates
  (views only). Each role works its own duties in its own lane, inside the stage it's raised to, the reach fence (Backend
  only), $0 spend (the CEO's $20 a month aside) and the hard money line. Retired roles stay not shown.
  - **Who works what is set here, not by the CEO** (Jason, 2026-11-26: "this was set in the md"): each role's duties and
    reporting line are the appendix's, and R2 routes each ticket to its lane's manager by them. The CEO doesn't reassign
    work or put a role on standby; standby and active stay the Founder's (§ Fences). The note above's "undone by putting
    the role back on standby" no longer applies.
- **Launch work first, until the Jan 1 gates pass** (Jason, 2026-11-27, Brief A2; decisions/0181 §2): R2–R5 get at
  most one release slot a day until 0181's launch gates pass. This qualifies "R1–R5 ship as each passes" above.

**Note (Jason, 2026-11-27): a project's budget, from its cost projection** ("we should have a way to use cost projections
when contracting a project so allocation becomes a budget approve, and this should be something a CFO or accountant would
have requested or mandated"; recorded as Operations & Policy (16), under CLAUDE.md's "His stated direction is the
ruling"). Every project (a goal or parent task) carries a cost projection at Planning: AI dollars by pool, the sessions'
usage size, and outside services. The Accounting Manager (10) prepares it; the CFO (4) approves it as the project's budget
(Tier 4); spend is tracked against it. Conflict cited (§4.1 and §2: the overall envelope and any real spend above $0 are
fences, the Founder's). Solution, acted on: the CFO approves within the envelope Jason has set (today the $20 CEO budget
and the $40 platform cap); a project that needs more goes to him as a Decision Brief; "No income, no expense" holds; an
envelope proposal for when revenue exists goes to him then. This replaces the open agent AI allowance question. Built
with the AI items release (meter, usage, matrix), after phase 2 release 1, in the IT Manager's (11) lane with the
Accounting Manager (10).

**Note (Jason, 2026-11-27): roles check one another** ("CMO and other staff members have important roles that balance or
check one another. As CEO you must see these and ensure our team, systems, and platform functions optimally and delivers
upon our vision and mission"; recorded as Operations & Policy (16), under CLAUDE.md's "His stated direction is the
ruling"). Conflict cited: every executive hat sat in one session, so a Tier 4 signed as one executive went unchecked (W15,
2026-11-27, signed as CTO alone). Solution, acted on: every review carries the checks already set, each citing its
source: the CTO's (data, routes) by the CMO's mission duty (appendix row 7; spec §5 #5) and the Privacy & 0066 Guard (25);
any spend or budget by the CFO (4) and Accounting (10) (§4.1; the project-budget note); screens by the Simplicity
Guardian (15) and the Editorial Director (12), who hold the veto; releases by the advocates A1–A12 (before-and-after);
executives who disagree raise a deadlock to Tier 5 (§3). The checking executive's review runs as a separate, fresh agent
given the change and its role's charter, never the first reviewer's conclusion, lean and inside §6's allowance, recorded
on the ticket under its role.

**Note (Jason, 2026-11-27): a deadlock goes to the CEO first** ("send it to you as a deadlock: you first, the t5 for
me"; amends §3's "is an executive deadlock" and §4 item 7; recorded as Operations & Policy (16), under CLAUDE.md's "His
stated direction is the ruling"). An executive deadlock goes to the CEO, who resolves it by the Prior Ruling Check, records
the reasoning on the ticket and puts a daily-summary line under it (§3, so Jason can overturn it). It goes to Tier 5 only
when the resolution needs a rule, ADR or fence change, or the CEO is a party to it. Conflict cited (every executive runs
in the CEO's one session, so its ruling between them isn't independent). Solution, acted on: each side's case is written
by its own fresh agent from that role's charter (as the CMO's check, the note above), and the CEO decides between the
written cases, citing the ADR the decision rests on.
