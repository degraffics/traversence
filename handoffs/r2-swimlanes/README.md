# decisions/0180 R2: swimlanes and locks (design for review, 2026-11-26)

0180 §5: "Every ticket routes to exactly one of the seven middle managers by its domain." §7: "One owner at a time.
Single-owner locks: one manager or specialist per ticket, and one claim per file or function (0161)" and "Sequential
hand-offs. Cross-lane work moves one lane at a time, with hand-off notes." Rollout item 2: "each ticket's owning middle
manager, single-owner locks and sequential hand-offs." #1460: who works what comes from the appendix's duties and
reporting lines; the CEO doesn't reassign work.

**The gap:** 0180 doesn't say how a ticket's domain is read, or which domain is which lane. Nothing in the code has a lane
(control_gauge_tasks has `owner`, a person or a session's name). Below are suggestions, each from what's already set.

## 1. A ticket's lane (suggested; first match wins)

| Read from the ticket | Lane (manager) | Source |
|---|---|---|
| Its files or pages touch the **money** territory (TeamRunner::TERRITORIES) | Accounting Manager (10) | appendix: "Accounting Manager, L3 → CFO"; its mandate "Verifies billing workflow fixes and reconciliations" |
| … the **data, address, nations or privacy** territory | IT Manager (11) | appendix: 22 Staging & Release, 24 Schema & Migration Guards and 25 Privacy & 0066 Guards report to the IT Manager |
| Its pages are in the admin's **backoffice** (orders, clients, support) or member support | Customer Service Manager (9) | its mandate: "Support, onboarding edge cases, orders"; 17 Order & Fulfillment and 18 Support report to it |
| … the **marketplace, storefronts, claims or partners** | Sales Director (14) | its mandate: "Leads, merchant onboarding, partnerships and retention"; 31, 32 |
| … **content pages, the FAQ, guides, design** (public pages, app/ui) | Editorial Director (12) | its mandate: "Content, copy, public design and briefs"; 26, 27, 28 |
| … **SEO, taxonomy, campaigns** (site-audit SEO findings) | Marketing Director (13) | its mandate: "Brand, campaigns, SEO, taxonomy and funnels"; 29, 30 |
| … **System Configuration, the control board, releases, staging, the crawler, jobs** | IT Manager (11) | its mandate: "Builds, QA, releases, schema and the guards"; 20–25 |
| An **audit** ticket (Ops & Reconciliation's) | IT Manager (11) | appendix: 23 reports to the IT Manager |
| Nothing above | Project Manager (8) | its mandate: "Swimlane: delivery. Moves each ticket through Planning, Building, Implementation and Confirmation" |

A **Resolution** ticket (Tier 5) keeps the lane of the work it came from. An **advocate's** experience ticket (R4) takes
the lane of the screen it names, by the same table.

**Stored as computed:** the lane is worked out by one function (`TeamRoster::lane($ticket)`) each time, so no existing
row is written; a hand-off (§3) stores the lane it moved to. The migration only adds columns (0162).

## 2. Single-owner locks

- A ticket has one owner at a time: a person (as today) or one role (`lock_by` = its slug, `lock_at`).
- A runner step takes the lock only when it's free, or its own, or its holder is a role whose hour (0180 §7 "at most once
  an hour") is over; a person's lock is never taken (§7 "Agents never take a ticket a person owns").
- The role is the lane manager or a specialist reporting to it (the appendix's lines; TeamRoster::doer for standby).
- File and function claims stay 0161's (checkout.py), unchanged.

## 3. Sequential hand-offs

- Moving a ticket to another lane is one action, **Hand off to <lane>…**, with a note (required), logged on the ticket and
  in the logbook; it releases the lock. Only the holding lane's manager (or the Founder) can do it.
- One lane at a time: a ticket is in exactly one lane; the next lane's work starts only after the hand-off.

## 4. The Work queue

- Each ticket shows its lane (the manager's name) and its owner; **Lane ▾** joins the one filter button (CLAUDE.md "Filters
  on a list … are one dropdown button").
- ⋯ carries Hand off to… (greyed with why when it isn't the holder's to give).

## 5. Data and seeds

- Migration (add-only, guarded): `control_gauge_tasks` + `lane VARCHAR(40) NULL`, `lock_by VARCHAR(80) NOT NULL DEFAULT ''`,
  `lock_at DATETIME NULL`, `handoff VARCHAR(400) NOT NULL DEFAULT ''`.
- Seeds, once each, routed by §1 (the CEO's list): the Scheduler bundle (:02, the carry job out of "slow", the 6:13 spread),
  no-undo, deadlock detection (R3). The before-and-after, 6:13 and slow-pages tickets are already in from R1.

## Questions for review

1. Is the table in §1 right, row by row? Especially money → Accounting Manager (vs Customer Service for orders) and
   audits → IT Manager.
2. Hand-off by the holding lane's manager only, or by any manager?
