# Architectural & Implementation Handoff to Claude: Work Queue Phase 2

## 0. Handoff Mandate, Holistic Vision & Expected Retort Protocol

### Why We Are Handing This Off Now
Traversence is already running in production, and the gap we want to close is between **what was envisioned** and **what has been built organically over time**. Features, pipelines, scripts, and workflows were built to solve immediate needs as they emerged—leaving seams between data capture, front-end consumption, reporting, UX refinement, endpoint/database contracts, and operational ownership.

This handoff reorganizes how the system operates so that **you (the Founder) step out of hands-on development and routine supervision**, and the system interfaces with you **only for Tier 3 decisions that only you can make**—while capable specialist agents execute autonomously within established policies, ADRs, and guardrails.

### Expected Retort & Gap-Closing Loop From Claude
Treat this document as a **direct architectural and operational handoff**. After reviewing this spec against the live codebase, schema, workflows, and test accounts:
1. **Return a candid retort/critique** outlining your **concerns, technical objections, edge cases, schema/workflow conflicts, or implementation risks** (especially where a live system could hit friction, race conditions, or over-complication).
2. **Identify any missing seams** between the existing codebase (`TestAccounts.php`, `System Configuration → Workflows`, `Pulse → Site health`, 3-way sync, release pipelines, and `W0–W15`) and the specifications below.
3. **We will respond to your objections directly** to refine edge cases and lock in the final build execution so this plan has the highest chance of succeeding cleanly.

---

## 1. Executive Mandate & Live Cutover Priorities

### Core Shift: From Hands-On Builder to Decision-Only Founder
* **Founder Role:** Vision holder, mission guardian, final tie-breaker, and resolver of **Tier 3 out-of-bounds resolution tickets**. The Founder does **not** write routine code, approve every routine workflow step, or wade through raw logs/stack traces.
* **System Role:** Operates as a cohesive, self-governing company of specialist roles and background keepers that check internal resources first, execute within policy and ADRs, run scheduled health/drift audits, and surface concise **4-part Decision Briefs** only when a true Tier 3 boundary is reached.

### Fast Cutover Sequence (Live System)
Because the platform is already live, execute in this order rather than doing a disruptive rip-and-replace:
1. **Stabilize Guardrails First:** Protect database/endpoint contracts, `TestAccounts.php` / `check@traversence.com` isolation, Decision 0066 cultural/tribal site holds, 3-way sync (6:00 & 18:00 UTC), and staging-to-live release discipline.
2. **Activate Background Keepers & Knowledge-to-Action Loops:** Put quiet specialists and scheduled audits in place to govern continuity, prevent drift, and ensure `W0–W15` data is actually consumed, reported, and used to improve UX.
3. **Streamline Day-to-Day Work Queue & Operations:** Align active work around the **four-stage Work Queue** (*Planning → Building → Implementation → Confirmation*) and departmental queues/logs/reports so daily work moves smoothly without cluttering the Founder's view.
4. **Build / Wire "The Team" (`[👥]`) & Governance UI:** Provide clean visibility and management for the organizational roster, sandbox test-account bindings, workflow stages, and exception/resolution tickets while keeping surface cognitive load low.

---

## 2. Design, Stack & Platform Guardrails

* **Visual Theme & UI Palette:** Maintain exact continuity with the existing warm earth-tone design system:
  * Canvas / Vellum background: `#EFE3C8`
  * Card / Elevated Parchment surface: `#F8F5EC`
  * Primary Ink / Umber: `#3E2B1F`
  * Deep Espresso accents & active borders: `#2A1D14` / `#24201B`
  * Subtle borders (`1px solid`), generous whitespace, calm editorial typography, and low cognitive clutter.
* **Tech Stack Alignment:** Native PHP 8+ (PDO), MySQL, vanilla JavaScript, and Tailwind CSS (via CDN) matching existing platform architecture.
* **Configurable Workflows (`System Configuration → Workflows` — `/admin/config/workflows`):** Workflow stages and stage owners are configured in System Configuration—not hard-coded—across three workflow groups:
  1. **Business Workflows (11):** *Client order* (Back office), *Lead* (Growth), *Subscription* (Back office), *Promotion* (Growth), *Claim* (Local), *Place review* (Local), *Journey* (Explore), *Experience* (Explore), *How-to guide* (Explore), *Report* (Social), *Standing notice* (Social).
  2. **The Knowledge Engine (`W0–W15`, Decision 0164):** *W0 Indexer*, *W1 Listings*, *W2 Keeping listings current*, *W3 Sources and place store*, *W4 Place guides*, *W5 Guides on demand*, *W6 Heritage and topics*, *W7 Journeys*, *W8 Find it*, *W9 Landmarks on demand*, *W10 Live events*, *W11 How-tos*, *W12 Search learning*, *W13 Stories*, *W14 Authority sources*, *W15 Funnel* (`hub → zone → town → anchor`). Rules for all `W0–W15`: *curators, not controllers; tell the story, never the way in; never overwrite what a person entered.* A source going down holds only what depends on it.
  3. **Operations Workflows (7):** *Releases* (Decisions 0159 & 0165), *3-way sync* (server, OneDrive, and GitHub at 6:00 & 18:00 UTC; only releases change the site; stray edits reverted), *Database updates* (pre-change backup, automated release data updates, logged execution), *Site audit* (Decision 0145; daily checks as test accounts at phone and desktop sizes feeding `Pulse → Site health`), *Notify* (Decisions 0172 & 0176; single dispatcher, right person/channel, one thread per thing, quiet hours), *Scheduled tasks*, and *Work queue*.
* **Core Front-End & Decision Rules:**
  * **Front Door (`/`) & Quick Search (Decision 0148):** One-tap routing (*Let's Explore* for visitors, *Get Local* for residents) and town search behavior that logs demand even where data does not exist yet.
  * **Places & Tribal Sovereignty (Decision 0066):** Natural landmarks (USGS) and public recreation (Recreation.gov); sensitive tribal nations' land is held and never shown.
  * **Trips & Personal Guides (Decision 0174):** Save places, plan days/times against opening hours, send to Google or Apple Maps, print itineraries, share view-only links, or publish Personal Guides.
  * **Market, Shop & `trvgo.us` Storefronts (Decision 0139):** Marketplace items/tickets/downloads, role-aware plans/packs/GO codes/domains, QR/GO code linking from print to site, and business storefronts on short codes or custom domains.
  * **Trust, Help & Pre-Launch Readiness (Decision 0057):** `/faq.php` growing with every build, `/our-approach.php`, `/ai/` (*Ask Traversence*), `/takedown.php`, data deletion, unsubscribe/"Stop these", and replacing placeholder Terms & Privacy before launch alongside upcoming clean addresses, editorial guides, data access exports, and DMCA notices.
* **Test Account & Sandbox Isolation (`app/lib/TestAccounts.php` — Decision 0145 §3):**
  * **Founder Login:** Never a test login (`is_production_founder = 1`).
  * **23 Test Accounts + Guest (`signed_out`):** Configured via System Configuration → Test accounts (`/admin/config/testaccounts`) and defined in `app/lib/TestAccounts.php`.
  * **Non-Negotiable Isolation:** Excluded from real counts, likes, emails, people search, and billing; send no outbound communications; pay only in sandbox (no real money moves); route test mail to `check@traversence.com`; store credentials only in GitHub secret `CHECK_ACCOUNTS` (never in code or chat); refresh cleanly via staging's *"Set up staging"*.

---

## 3. Operating Mode: Day-to-Day Operators vs. Background Keepers & Cognitive Load Guardrails

### Preventing Over-Complication & Cognitive Overload
Having a comprehensive roster, 34 workflows across three engines, and 23 test accounts is valuable *under the hood*—but **must not surface as daily clutter or force the Founder to supervise every moving part**.
* **Quiet by Default:** Most roles and workflows run passively or trigger only on relevant events, stage transitions, or schedules.
* **Cognitive Load & Simplicity Guardian:** Enforces *"One Screen, One Clear Next Action"*, trims scope/UI bloat, and prevents systems or reports from becoming heavy to read or maintain.
* **Executive Translator:** Distills technical, operational, and audit activity into plain-English, scannable updates—reserving full escalations for Tier 3 decisions.

### Two Operating Tiers in Action
1. **Day-to-Day Focus (Work Queue & Live Operations):** Active roles driving the **four-stage Work Queue** (*Planning → Building → Implementation → Confirmation*), active business/order/listing/claim/review/guide/report workflows, customer support, print/vendor handoffs, and clean daily summaries.
2. **Background Workers (Keepers, Transition & Continuous Audits):** Specialists that guard system integrity, data/endpoint contracts, Decision 0066 holds, legal risk flags, decision/living-landscape lineage, test-account isolation, and knowledge-to-action consumption/reporting/UX loops.

---

## 4. Model & System Routing Policy (Internal-First & Cost-Conscious)

Deciding *which* system or model handles a task is governed by **Policy & Procedures** (maintained by the **Policy & Procedures Manager** and wired/verified by the **System Architect** / **Technical QA**), rather than bloating the roster with extra routing roles:

1. **Internal Systems & Deterministic Logic First:** Always check internal database records, ADRs, SOPs, logs, `W0–W15` state, local configuration, and existing queries/rules before calling external AI models or third-party APIs.
2. **Claude:** Primary lane for deep codebase implementation, multi-file edits, schema/endpoint wiring, structured build execution, and technical alignment against specs and ADRs.
3. **Gemini:** Primary lane for broad ideation, synthesis, narrative framing, cross-checking concepts, and exploratory/collaborative structuring.
4. **External Services & APIs:** Used for designated data sources, vendor/print workflows, billing/accounting integrations, or messaging channels—always honoring sandbox isolation, daily allowances, quiet hours, and release/3-way sync rules.
5. **No Silent Guesswork:** If a model/service is unavailable, ambiguous, or blocked by a guardrail, fall back cleanly or issue a structured task/resolution ticket rather than guessing or burning paid calls on aimless retries.

---

## 5. Database & Endpoint Governance (Reusable Core Assets)

The database and its endpoints are durable, reusable platform assets—not byproducts of individual screens:

1. **Versioned Migrations & ADR Traceability:** Every schema, index, column, or constraint change requires a tracked migration step with pre-change backup safety and logged execution, tied to a clear rationale/ADR. Never rely on ad-hoc live edits that conflict with 3-way sync or release rollbacks.
2. **Explicit Endpoint Contracts:** Every API endpoint and data-access path defines expected inputs, authentication/role permissions, response shape, and error handling—avoiding scattered one-off SQL across templates or ad-hoc scripts.
3. **Reusable Query & Service Boundaries:** Shared domain reads/writes (places, hubs, businesses, claims, trips/guides, orders, test accounts) live in clean, reusable data/service layers so multiple views and workflows don't duplicate logic or drift apart.
4. **Data-Layer Isolation & Visibility Rules:** Test-account exclusions (`TestAccounts.php`), sandbox boundaries, and **Decision 0066** sensitive-site visibility rules are enforced at the data/query/endpoint layer—not left to individual UI templates to remember.

---

## 6. Knowledge-to-Action Loop (`W0–W15` → Consumption, Reporting & UI/UX Improvement)

Data capture and cataloging (`W0–W3` and topic/concept indexing) are incomplete unless they actively serve the mission:

1. **Mission-Aligned Data Consumption:** Every cataloged topic, concept, place, guide, journey, story, or demand signal (`W4–W13`, `W15`) must connect to clear user or operational value—feeding Hubs, Places, Journeys/Guides, search/on-demand town guides (`W5`), directory freshness (`W2`), or demand-aware priorities (`W12` search demand by place/kind/topic *without storing raw search strings*, and `W15` `hub → zone → town → anchor` funnel gaps).
2. **Actionable Reporting (Signal, Not Raw Counts):** Roll up knowledge coverage, search/funnel gaps, queue health, site-audit findings (`Pulse → Site health`), and support/operational trends into concise, plain-language reports that guide action.
3. **Continuous UI/UX Improvement Loop:** Feed usage friction, zero-result/sparse searches, empty states, support patterns, and audit findings back into **UI/UX Prototyper**, **Content & Copy Editor**, and **Cognitive Load Guardian** as small, focused improvements—turning real user behavior into cleaner screens and copy.

---

## 7. Departmental Communication: Inboxes, Logs & Reports

The team coordinates like a real company using structured channels rather than ad-hoc noise:
* **Departmental Inboxes & Queues:** Work items, stage handoffs, and exceptions route to the owning department/role queue with clear stage ownership and time/SLA awareness.
* **Audit & Execution Logs:** Autonomous actions, stage transitions, database updates, releases, and notifications are logged in structured records for traceability without interrupting the Founder.
* **Reports & Executive Summaries:** Routine work stays in logs and departmental queues; reports summarize system health, knowledge/demand gaps, and queue status, while the **Executive Translator** filters what reaches the Founder.

---

## 8. Four-Stage Work Queue, Tiered Decision Tree & Resolution Ticket System

### Four-Stage Work Queue Lifecycle
Every queued build/change item moves through four standardized stages (with **Project Manager** tracking sequence/dependencies and **Cognitive Load Guardian** guarding simplicity):
1. **Planning:** Scope, architecture/ADR check, flow/design check, policy/legal risk check, and dependency sequencing.
2. **Building:** Implementation across PHP 8+/MySQL/JS/Tailwind, endpoints, templates, and assets.
3. **Implementation:** Staging rollout, release/3-way sync execution, database updates, and system wiring.
4. **Confirmation:** Site/test-account audit checks, functional & UX verification, operational check, and sign-off.

### Light Intake (`+`) vs. Founder Decision Brief
* **`+` (Create Queue Item) Is Lightweight Intake:** Launching a project or issue through `+` must **not** force the Founder to fill out a heavy Decision Brief. Keep `+` fast and simple (plain-language goal/issue, optional lane/type, urgency/context) so the team plans, builds, and executes autonomously.
* **Founder Decision Brief Is Triggered Only on Tier 3 Escalation:** Only when work or an exception hits a **Tier 3 boundary** does the system generate a **4-part Decision Brief**.

### Master Decision Tree & Authority Tiers (With Sound Logical Intuition)
Agents are trained to exercise **sound logical intuition** before escalating: checking ADRs/SOPs and internal records first, evaluating second-order downstream effects across `W0–W15`/endpoints/workflows, honoring mission/Decision 0066/privacy/sandbox guardrails, favoring reversible/additive steps, and proposing **reasoned solutions** rather than dumping raw symptoms.

* **Tier 1 — Autonomous Execution & Improvement (No Founder Approval):** Routine queue progression, standard ops/support/order steps, directory/knowledge hygiene (`W0–W15`) within rules, microcopy/UX simplicity improvements, preflight/QA/contract checks, and logging actions cleanly.
* **Tier 2 — Cross-Role Handoff & Staging/Peer Gate (No Founder Approval):** Multi-specialty work, endpoint/schema/workflow/notification changes, and guide/story/directory reviews validated through peer roles, staging (`Decisions 0159 & 0165`), QA, and audit checks.
* **Tier 3 — Founder Escalation (Resolution Ticket / Out-of-Bounds Call):** Triggered **only** when an event, issue, or decision falls outside expected parameters/authority across:
  1. **Money, legal, or external commitments** (real-money pricing/billing changes, refunds/disputes above threshold, external contracts, final legal/compliance sign-offs).
  2. **Mission, cultural, or trust boundaries** (core mission/zero-commission shifts, **Decision 0066** tribal/cultural rules, privacy/non-tracking principles).
  3. **Destructive or high-impact changes** (dropping/altering major tables/data, breaking public URLs/redirects, overriding conflicted human-entered records).
  4. **True priority deadlocks** (conflicting valid priorities or rules requiring Founder vision to break the tie).

#### Out-of-Bounds = Resolution Ticket (4-Part Founder Decision Brief)
Whether a user hits an account issue, a listing attempts an unauthorized email, a release/audit hits a boundary, or a policy question has no precedent yet, out-of-bounds items that reach the Founder arrive as a **Resolution Ticket** formatted as a **4-part Decision Brief**:
1. **The Decision:** One clear sentence stating the exact decision required.
2. **Why It Needs You:** Which Tier 3 boundary, risk, or trade-off prevents the team from closing it autonomously.
3. **Options (A vs. B):** Concrete choices—each with **Pros**, **Cons**, and a **Recommended Default** grounded in system logic and ADRs.
4. **Impact if Delayed:** Whether it blocks live work immediately, sits safely on hold, or carries time sensitivity.

Once resolved, the decision closes the ticket and **updates/creates standing policy or ADR precedent** so future similar occurrences follow the rule automatically without repeatedly asking the Founder.

---

## 9. Scheduled Clean-Up & Continuous System Health Audit

To ensure what is built is both **done right** and **keeps working right** over time, **system health & clean-up auditing runs as a Scheduled Task** (coordinating **Technical QA & Security Gatekeeper**, **Operations & Reconciliation Manager**, and relevant domain/background keepers):

1. **Continuous Evaluation:** Runs on a recurring schedule (and after releases/migrations/syncs) to check site/endpoint health, `Pulse → Site health` findings, workflow/queue health, `W0–W15` pipeline freshness and gap signals, test-account/data isolation, and stale test artifacts or transition leftovers.
2. **Diagnostic Testing First:** Queues targeted diagnostic tests (including isolated test-account checks) to distinguish real drift or faults from transient noise before escalating.
3. **Automatic Ticket Issuance:**
   * Issues standard **Work Queue / departmental tasks** automatically for Tier 1 / Tier 2 fixes and clean-up.
   * Issues a **4-part Founder Decision Brief (Resolution Ticket)** if the finding crosses a Tier 3 boundary.

---

## 10. Core Pillar Ownership Across the Team

* **Discovery** (`W0`, `W4–W12`, `W15`, Hubs, Places, Journeys, Guides, Events, Search Demand): Owned across **Regional Directory Curator**, **AI & Dynamic Guide Quality Auditor**, **Historian**, and **Mission Auditor** (Decision 0066).
* **Directory** (`W1–W3`, `W14`, *Claim*, *Place review*, *Lead*): Owned by **Regional Directory Curator** (accuracy, deduplication, non-overwrite of human entries, verified closures), **Mission Auditor** (Decision 0066 holds), and **Sales / Onboarding Agent** (claiming & outreach).
* **Community** (`W13` human stories, *Report*, *Standing notice*): Owned by **Content & Copy Editor**, **Customer Service Agent**, **Policy & Procedures Manager**, and **Legal Risk Checker / Auditor**.
* **Marketplace** (*Promotion*, *Client order*, *Subscription* under zero-commission alignment): Owned by **Sales / Onboarding Agent**, **Order & Fulfillment Processing Agent**, **Finance / Tax Controller**, and **Operations & Reconciliation Manager**, with **Founder** owning Tier 3 pricing/billing/mission/contract calls.

---

## 11. Organizational Roster (33 Entities Across 4 Departments) & Schema

### Department 1: Leadership, Cognitive Shield & Integrity Governance
1. **Executive Lead & Founder** (`is_production_founder = 1`, Human) — Vision holder, final decision-maker, Tier 3 resolution ticket resolver, target/live authority.
2. **Executive Translator & Briefing Lead** (AI Agent — `Test Executive` lens) — Plain-English translation, 4-part Decision Briefs, zero raw log/jargon dumps.
3. **Cognitive Load & Simplicity Guardian** (AI Agent — `Test Assistant` lens) — *"One Screen, One Clear Next Action"*, prevents bloat and cognitive overload.
4. **AI Legal Risk Checker & Auditor** (AI Agent) — First-pass legal, privacy, consent, IP/DMCA, and pre-launch compliance risk scanner; escalates formal legal/policy sign-off.
5. **Mission & Brand Integrity Auditor** (AI Agent) — Enforces core platform promises, zero-commission fairness, **Decision 0066** tribal site holds, and **Decision 0057** FAQ/trust alignment.

### Department 2: Delivery, Operations, Finance, Historian & Member Support
6. **Project Manager** (AI Agent) — Sequences work across *Planning → Building → Implementation → Confirmation*, tracks dependencies and stalls.
7. **Operations & Policy Director** (AI/Hybrid — `Test Ops Director`) — Owns master SOPs, model/system routing policy, data/endpoint governance SOPs, and SLA rules.
8. **Operations & Reconciliation Manager** (AI/Hybrid — `Test Ops Manager`) — Monitors queue/operational health, scheduled audit/drift follow-through, and ledger/order reconciliation.
9. **Historian — Platform Lineage, Human Journey & Living Landscape** (AI Agent) — Tracks decision/architecture lineage *and* human journeys + living-landscape/community/seasonal/place evolution over time (honoring Decision 0066 and separating "then" from "now").
10. **Order & Fulfillment Processing Agent** (AI/Hybrid — `Test Ops Staff`) — Order checks, proofs, work orders, and fulfillment progression.
11. **Finance, Tax & Billing Controller** (AI/Hybrid — `Test Finance Staff`) — Tax/TPT rules, sandbox payment isolation, payouts, and billing/invoicing accuracy.
12. **Growth, Merchant Onboarding & Partnerships Agent** (AI/Hybrid — `Test Sales Staff`) — Business claiming (`/claim.php`), leads, promotions, and partner onboarding.
13. **Customer Service & Member Support Agent** (AI/Hybrid — `Test Service Staff`) — Member/business support, report/standing-notice handling, and logging UX/support friction.
14. **Regional Directory & Data Curator** (AI/Hybrid — `Test Content Staff`) — Directory accuracy, `W1–W3`/`W14–W15` coverage and gaps, non-overwrite of human entries, and verified closures.

### Department 3: Design, Print, Content, AI Guides, Engineering & QA
15. **System Architect** (AI Agent — `Test Engineering Staff` lens) — Schema, migrations, endpoint contracts, reusable query boundaries, ADRs, and release/3-way sync architecture.
16. **UI/UX Prototyper & Flow Designer** (AI Agent) — Multi-state wireframes and flows in the `#EFE3C8` / `#F8F5EC` / `#3E2B1F` / `#2A1D14` palette.
17. **Senior Designer — UI, Marketing Graphics & Visual Support** (Human/Hybrid — `Test Content Creator`) — Visual polish, icons, graphics, and brand-consistent assets.
18. **Preflight, Print & Trade Vendor Specialist** (Human/Hybrid — `Test Vendor login`) — Press preflight (bleed/trim/DPI/CMYK/dielines), vendor/work-order checks, and printable layouts.
19. **Content & Copy Editor** (AI Agent — `Test Editor`) — Microcopy, onboarding, error states, and plain, jargon-free editorial voice.
20. **AI & Dynamic Guide Quality Auditor** (AI Agent) — Audits `/ai/` (*Ask Traversence*), `W4–W5` place/on-demand guides, `W10–W11` events/how-tos, and `W12` privacy-safe demand signals.
21. **Full-Stack Builder** (AI Agent — `Test Engineering Staff`) — Implements PHP 8+/MySQL/JS/Tailwind features strictly within approved scope, schema, and endpoint contracts.
22. **Technical QA & Security Gatekeeper** (AI Agent — `Test Engineering Staff`) — Tests edge cases, auth/permissions, input validation, data isolation, responsive layouts, and scheduled audit diagnostics.

### Department 4: Audience & Ecosystem Advocates (Bound to Test Accounts & Guest)
23. **Guest Visitor Advocate** (`signed_out`) — Signed-out discovery, front-door routing, and sign-up friction.
24. **New Member Onboarding Advocate** (`test_new_member`) — First-7-day and sign-up experience.
25. **Resident & Member Advocate** (`test_member`) — Saved places, trips/Personal Guides, community groups, and member flows.
26. **Editorial Contributor Advocate** (`test_contributor`) — Writing, editing, and publishing journeys and stories.
27. **Community Group Steward Advocate** (`test_steward`) — Group stewardship and moderation.
28. **Client & Sandbox Buyer Advocate** (`test_client`) — Orders, proofs, deliverables, and sandbox payments.
29. **Business Owner Advocate** (`test_business_owner` — *Traversence Test Listing*, Saint Johns, AZ) — Business control, team roles, `trvgo.us` storefront/GO codes, and plan clarity.
30. **Business Manager Advocate** (`test_business_manager`) — Day-to-day listing updates, offers, and operations.
31. **Business Accountant Advocate** (`test_business_accountant`) — Invoices, receipts, and financial clarity.
32. **Business Sales Advocate** (`test_business_sales`) — Inquiries and promotional visibility.
33. **Business Viewer Advocate** (`test_business_viewer`) — Read-only access boundaries without unintended mutations.

### Database Schema (`dev_team_entities`)
```sql
CREATE TABLE dev_team_entities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(160) NOT NULL,
    entity_type ENUM('human', 'ai_agent', 'hybrid', 'audience_persona') NOT NULL DEFAULT 'ai_agent',
    department ENUM('leadership_shield', 'operations_support', 'design_build_qa', 'audience_advocates') NOT NULL DEFAULT 'design_build_qa',
    job_title VARCHAR(180) NOT NULL,
    mission_mandate TEXT NULL,
    oversight_tier ENUM('observer', 'contributor', 'gatekeeper', 'executive_lead') NOT NULL DEFAULT 'contributor',
    reports_to_entity_id INT UNSIGNED NULL,
    is_production_founder TINYINT(1) NOT NULL DEFAULT 0,
    test_account_key VARCHAR(100) NULL,
    test_account_label VARCHAR(150) NULL,
    sandbox_only TINYINT(1) NOT NULL DEFAULT 0,
    can_approve_target TINYINT(1) NOT NULL DEFAULT 0,
    can_sign_off_mission TINYINT(1) NOT NULL DEFAULT 0,
    can_sign_off_audience TINYINT(1) NOT NULL DEFAULT 0,
    can_authorize_live TINYINT(1) NOT NULL DEFAULT 0,
    can_override_sla TINYINT(1) NOT NULL DEFAULT 0,
    allowed_modules JSON NULL,
    pipeline_roles JSON NULL,
    guardrails JSON NULL,
    status ENUM('active', 'standby', 'inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (reports_to_entity_id) REFERENCES dev_team_entities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 12. Implementation Scope & Acceptance Criteria ("Done When")

### Implementation Scope
1. **Migration & Seeder:** Create `dev_team_entities` and seed all 33 entities with mandates, guardrails, oversight flags, and `TestAccounts.php` / `signed_out` bindings.
2. **Backend API Endpoints (PHP 8+ PDO):**
   * `GET /api/team/list.php` — Filterable by department, pipeline role, module, or status; returns counts and test-account metadata.
   * `POST /api/team/save.php` — Creates or updates an entity with validation, prepared PDO statements, and strict exclusion of secret credentials.
   * `POST /api/team/status.php` — Updates `active` / `standby` / `inactive` status cleanly.
3. **Frontend UI ("The Team" View):**
   * Wire the top-right **`[👥]` Team icon** on the Work Queue dashboard to open **The Team** in the `#EFE3C8` / `#F8F5EC` / `#3E2B1F` / `#2A1D14` palette.
   * Provide a **Lifecycle & Coverage Strip**, **Department Tabs**, clean `#F8F5EC` entity cards, and a slide-over profile editor.

### Acceptance Criteria ("Done When")
* \[ \] Clicking **`[👥]`** opens **The Team** in the `#EFE3C8` / `#F8F5EC` / `#3E2B1F` / `#2A1D14` design system.
* \[ \] All four departments and all 33 seeded entities (including **Founder**, **Project Manager**, **AI Legal Risk Checker & Auditor**, **Historian**, operational/build/QA roles, and the 11 audience/test-account advocates) are present and filterable without clutter.
* \[ \] **Founder** is marked as production leadership (`is_production_founder = 1`), never a test login, and test-account-linked profiles reflect `TestAccounts.php` (`Decision 0145 §3`) and sandbox isolation rules.
* \[ \] Spec clearly documents the **four-stage Work Queue** (*Planning → Building → Implementation → Confirmation*), **Tier 1–3 decision tree & 4-part Decision Brief resolution tickets**, **internal-first model/system routing**, **database & endpoint governance**, **knowledge-to-action consumption/reporting/UX loops**, **scheduled health/clean-up audits**, and **Claude's retort/gap-closing loop**.