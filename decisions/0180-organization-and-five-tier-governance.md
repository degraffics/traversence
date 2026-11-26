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

