# 0102: Roles and support access (RBAC master)

Status: Proposed (Jason, 2026-10-21). Builds on 0100 (staff logins and roles). Reviewed against an outside RBAC
compliance paper; adopted what fits a small-business platform, dropped the rest (end of this doc).

## 1. Principle

**The role decides.** If a person's role allows an action, they do it, with no confirmation step and no one else's
approval. If it doesn't, they can't. Keeping the platform hands-off means assigning the right role to each person.

## 2. Two layers, never mixed

1. **Platform staff:** Traversence's own team, on staff logins (0100), with roles that open admin areas.
2. **Business team:** a client's own people on their business. A platform staff member has **no access** to a business
   except through a standing grant or a ticket (section 5), and then only with the role that grant or ticket gives.

## 3. Business roles

| Role | For |
|---|---|
| Owner | the business; can do anything a lower role can |
| Manager | runs the business day to day |
| Accountant | money and records |
| Sales | customers, pipeline, orders |
| Support | customer help and orders |
| Creator | listing content and catalog |
| Developer | domain, API keys, webhooks, theme |

A business with one person sees no roles: the owner just has everything. Roles appear once they invite someone.
No lower role gets anything more easily than the Owner.

## 4. Roles × permissions

- **F** = full
- **E** = create and edit
- **V** = view only
- **D** = draft only, publishing goes to a Manager or the Owner
- **—** = none

| Permission | Owner | Manager | Accountant | Sales | Support | Creator | Developer |
|---|---|---|---|---|---|---|---|
| Plan & billing | F | V | V | — | — | — | — |
| Ledger, invoices, payments | V | V | E (invoices only) | V | V | — | — |
| Team & roles | F | E (roles below Manager only) | — | — | — | — | — |
| Listing details | F | E | — | — | V | E | — |
| Catalog & content | F | F | — | V | V | D | — |
| Publish | F | F | — | — | — | business setting | — |
| Orders & fulfillment | F | F | V | E | E | — | — |
| Customers & pipeline | F | F | — | E (their own) | V | — | — |
| Inbox | all | all | billing | Sales view | Support view | — | — |
| Domain, API keys, webhooks | F | — | — | — | — | — | E |
| Theme & styling | F | E | — | — | — | E | E |
| Exports | F | — | — | — | — | — | — |
| Delete the business | F | — | — | — | — | — | — |

Notes:
- The ledger is append-only (0098): no role edits or deletes entries.
- Creator publishing is a per-business setting: off by default for teams, on for a one-person shop.
- Deleting the business keeps the 30-day restore (as for account deletion) as a safety net, not a gate.

## 5. Support access: two routes

An agent working on a business always does so **with a role** in it (from section 3), given by one of these:

### Route 1: Standing grant (a named agent, until revoked)

- The owner grants a **specific agent** (e.g. their account rep) a role in the business. It lasts **until revoked**.
  No ticket is needed.
- On white-glove (TRVGO-managed) accounts we set it up at onboarding.
- **Changing the agent:** when Traversence reassigns the account, the grant moves to the new agent at once, with the
  same role. The old agent loses access immediately. The owner is told ("Your support agent is now Dana (TRV-0018),
  replacing TRV-0012 · Revoke"), in the app and by email. No confirmation is needed; the owner can revoke at any time.
- **Revoked** by the owner at any time, or by us when the agent leaves.

### Route 2: Ticket (rolling queue, timed access)

- The owner opens a ticket, or staff open one for them. A ticket staff open is shown to the owner at once
  ("Support opened a request for you · Not me / Cancel").
- Tickets go into the **support queue**. Any on-duty support agent picks one up.
- The agent gets **timed access** with the role set by the ticket type (e.g. "Theme fix" → Developer, 2 hours,
  extendable once).
- Access ends at the time limit, when the ticket closes, or when it's reassigned. The next agent gets their own clock.

### Both routes

- **Banner** while an agent is in: "TRV-0012 is helping (standing access / ticket #1043) · End now". End now ends
  that session at once.
- **Action log** that can't be edited: who acted, on which business, by which route (grant or ticket), what was done,
  when.
- **Ending a staff login** revokes that agent's standing grants and puts their tickets back in the queue.
- **Emergencies** (abuse, legal): a Traversence owner opens a ticket flagged as an emergency; the business is told.

## 6. Data

| Table | Key fields |
|---|---|
| `team_members` | business, user, role, status |
| `role_perms` | role, permission, level (none / view / draft / edit / full) |
| `support_grants` | business, agent, role, granted by and when, revoked when |
| `support_tickets` | business, opened by, type, subject, role, status, picked up by, access until |
| `audit_log` | actor, business, route (member / grant id / ticket id), action, when |

**One check** for every page and API, enforced in `app/bootstrap.php` like the staff areas:
`Team::can($person, $business, $perm)`. The person's role comes from team membership, an active standing grant, or a
picked-up ticket that hasn't run out.

## 7. From the outside paper: kept and dropped

Kept:
- Support access only by grant or ticket, logged, visible, revocable.
- Draft vs publish for Creators.
- Accountant read-only on the ledger.
- An action log that can't be edited.

Dropped:
- NIST / SOC 2 framing: not a requirement for us.
- Step-up re-confirmation and owner approval for risky actions: the role decides (section 1).
- Two owners approving deletion: the 30-day restore covers mistakes.
- A JSON policy engine: one table of role permissions and one check do the same job.
- "Payment vault keys": QuickBooks holds card details, not us.
- Record-level rules in the inbox: Sales and Support views cover it for now.

## 8. Build order (when business teams ship)

1. `team_members`, `role_perms`, `Team::can()`, invites.
2. `audit_log` and the agent banner.
3. Standing grants and reassignment notices.
4. Ticket queue with timed access.
