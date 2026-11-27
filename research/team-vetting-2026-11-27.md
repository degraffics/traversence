# The team, vetted: who actually does each role (2026-11-27)

Jason, 2026-11-27: "I can tell that my team roles and responsibilities have not been fully adopted. The fact that your
acting like the project/session manager and CEO is the tell-tale sign. We need to address this because my team concept
needs to be vetted and accurate before we truly know its working."

A read-only check of decisions/0180's 32 roles and the 12 advocates against the live code (Sync 12:50 UTC) and the
hand-offs. The CEO's Decision Brief (Tier 5: roles and how the organization runs) follows the map.

## What's true today
- **The roster is real; the roles mostly aren't.** R1 (#313) seeded all 32 roles and the advocates as active. R2 (lanes) is
  held on `claude/r2-design`, so no lane exists in live code. R3 (recorded Tier 4, the Prior Ruling Check), R4
  (advocates' experience tickets) and R5 (rule-based auto-rollback, the simplicity veto) aren't built.
- **Four roles have code**, each a fixed $0 step in `TeamRunner` on staging (`app/lib/TeamRunner.php` STEPS): the Project
  Manager's `gate`, the System Architect's `handoff` (off at the Planning ceiling), the Executive Translator's `brief` (one
  proposed field) and Operations & Reconciliation's daily `audit`. There's no reasoning and no AI. Whether `team_runner` is on, on staging,
  is unknown.
- **The guards are code, not roles:** the zip check and `ReleaseKind` (24), `Landmarks::visibleSql` and `HeldNames` (25),
  the release pipeline and before-and-after check (22), the advocates' views in `beforeafter.mjs` (A1–A12).
- **The judgement is all in Claude sessions organized by area, not by role:**
  - the session manager acts as COO and Project Manager;
  - the CEO session acts as CEO, all five other executives, the Executive Translator and, until today, the Project Manager;
  - the build sessions (Piece 2/3, Frontend & Shop, Data & Content) are the "Build & QA Sessions" (21) and also write the copy (26) and design (27);
  - the Design reviewer stands in for the Simplicity Guardian (15).
- **Nothing performs** the Customer Service, Accounting, IT, Editorial, Marketing and Sales managers (9–14) or roles 16–19
  and 29–32. Lane assignments are written by the session manager or the CEO in hand-offs.

## The map

| # | Role | Performs it today | Gap |
|---|---|---|---|
| 1 | Founder | Jason | — |
| 2 | CEO | the CEO session | the "To: the CEO" channel unbuilt; budget only a session rule |
| 3–7 | COO, CFO, CTO, CIO, CMO | the CEO session wears each hat | label only; R3 unbuilt |
| 8 | Project Manager | `TeamRunner` gate (mechanical) + the session manager and the CEO by hand | no judgement on the platform; no lanes (R2) |
| 9–14 | Customer Service, Accounting, IT, Editorial, Marketing, Sales managers | nothing (roster rows) | no steps, no lanes |
| 15 | Simplicity Guardian | the Design reviewer session | no veto on the platform |
| 16 | Operations & Policy | nearest: the session manager's drift review | none |
| 17 | Order & Fulfillment | the Orders session (paused) | none |
| 18, 19 | Support Specialists; Finance, Tax & Billing Ops | nothing (QuickBooks jobs are platform jobs) | none |
| 20 | System Architect | `TeamRunner` handoff (template; off at Planning) | template only |
| 21 | Build & QA Sessions | the build sessions | works; not tied to the roster |
| 22 | Staging & Release Workers | `Releases.php`, `beforeafter.mjs` | code, not a role |
| 23 | Operations & Reconciliation | `TeamRunner::audit()` daily on staging | rules only, staging only |
| 24, 25 | Schema & Migration Guards; Privacy & 0066 Guards | the zip check, `ReleaseKind`; `Landmarks`, `HeldNames` | code, not a role |
| 26, 27 | Content Writers; UI/UX Designers | the build sessions; the Design reviewer approves | no role step |
| 28 | Executive Translator | `TeamRunner` brief (one field) + the CEO session | template only |
| 29–32 | Brand, SEO, Onboarding, Partnerships | nothing | none |
| A1–A12 | Advocates | `beforeafter.mjs` views (no AI) | experience tickets (R4) unbuilt |

## Decision Brief (Tier 5: roles and how the organization runs; 0180 §1's 4 parts)

**1. What's triggered.** How 0180's roles are carried out is a rule of the organization (§3), and today it doesn't match
0180 §5. Every ticket should route to one of seven middle managers, but none exists outside a label.

**2. The options.**
- **A. Sessions hold lanes, not areas.** It changes only *who performs* each role. The roster, reporting lines and
  active/standby stay exactly as 0180's appendix sets them (#1460), and standby stays the Founder's.
  - **Lanes by R2's routes** (`TeamRoster::LANE_ROUTES`, held on `claude/r2-design`; 0180 §5 names the seven lanes but not
    their scope). The ~three sessions (CLAUDE.md) each own a lane group as its manager:
    - **The Piece 2/3 session owns the IT Manager's lane (11):** releases, staging, alarms, config, the crawler and sources,
      migrations, and the data, address, nations and privacy territories.
    - **Data & Content owns the Editorial Director (12) and Marketing Director (13) lanes:** places, listings, guides,
      journeys, the FAQ and copy, design; SEO, taxonomy and campaigns. Its crawler, W-workflow and jurisdiction work is the
      IT Manager's lane under R2's routes, so the IT Manager owns those tickets. Data & Content builds them as Build & QA (21).
    - **Frontend & Shop owns the Customer Service (9), Accounting (10) and Sales (14) lanes:** orders, the cart and support;
      billing and QuickBooks; the Marketplace, storefronts and claims. Its page builds are Build & QA (21).
  - **Every build session is also Build & QA (21)**, reporting to the IT Manager, whichever lane owns the ticket.
  - **The session manager is the COO (3) and Project Manager (8).**
  - **The CEO session is the executive suite (2–7),** each Tier 4 recorded under the named executive (CFO for money, CTO for
    data, and so on).
  - **The Design reviewer stays a standing session** doing roles 15 and 27's approvals (fidelity, the simplicity veto)
    across all lanes.
  - **Specialists (16–20, 22–32)** are checklist steps the owning lane's session runs and records on the ticket under the role's
    name ("Privacy & 0066 Guard: clear, no held names"); the code that already does some (22–25, A1–A12) stays.
  - **What A amends** (named so a yes covers it, nothing changed by default):
    - CLAUDE.md's "one session per area" becomes "one session per lane group";
    - `handoffs/session-manager.md` names the session manager as COO (3) and PM (8);
    - Tier 4 reviews are recorded under the named executive.
  - $0 new spend, inside today's usage; accountability becomes real now.
- **B. Roles as platform agents.** Each role a prompted Claude call in `TeamRunner`. Real judgement on the platform, but
  it spends Claude money. The $40 cap is at 84% this month and "No income, no expense" holds, so not before revenue.
- **C. A now, B later.** A until revenue covers it. Then the specialists that are mostly rules (audit, gate, guards,
  translator) move to the platform first, the managers last.

**3. Recommendation: C.** A makes every role answerable today at no cost, and B grows from what A proves.

To prove it works, one real ticket goes from intake to live with each role's part recorded on it. Suggested: the stray "Recovered" email fix, which is in the IT Manager's lane. R2 (lanes) carries the lane names into the code. R3 records Tier 4 and the Prior Ruling Check.

**4. Your options.** Yes to C. Yes to A only. B now (it needs a money ruling). Or change the lane groups.
