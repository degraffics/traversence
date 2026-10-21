# 0103: Ending, transfer and inactivity

Status: Proposed (Jason, 2026-10-21). Covers accounts, businesses, listings and community groups. Works with 0102
(roles), the account deletion with its 30-day restore, the enforcement ladder, and 0066 (nations' data). **Timings
marked ⏱ are proposals for Jason to set.**

## 1. Principle

Nothing ends abruptly. Every ending goes through the same stages, the owner is told at each one, and data is kept
until the last stage. Some records are never deleted (section 5).

## 2. Transfer of ownership

**By hand.** The owner names a successor; the successor accepts; the change is logged and everyone with a role is told.
Until accepted, nothing changes.

**When the owner is gone** (deleted account, ended staff login, removed for violations), ownership falls to the first in
line who accepts:

| Thing | Successor order |
|---|---|
| Business | the next Owner on its team → a Manager → a verified claimant (claim process) |
| Listing | its business → back to **unclaimed** (never deleted) |
| Group (person or business) | **what its charter says** (section 2a) |
| Support grant | the agent Traversence reassigns (0102 §5) |

No one accepts within ⏱30 days → the thing moves to **Hidden** (section 3).

### 2a. Groups: transfer terms are set in the charter

Whoever starts a group sets its **transfer terms** when writing the charter, and can change them later. The terms say
what happens when the owner (a person, or for a business group the business) is gone or steps down:

| Term | What happens |
|---|---|
| **End the group** (default) | the group goes into deactivation (section 3); no elections, no handover |
| Named successor | a named moderator or member is offered the group; if they decline or don't answer in ⏱14 days, the group ends |
| Longest-serving moderator | offered to them; if they decline, the next; with none left, the group ends |
| Moderators choose | the moderators pick one of themselves within ⏱14 days; no pick, the group ends |

- The default is **no elections: the group ends.** Anything else is the creator's choice in the charter.
- The terms are shown on the group page, so members know before they join.
- A business group's terms apply when the business itself closes or loses the group; staff changes inside the business
  never trigger them (0102 §4c).

## 3. Ending: the stages

| Stage | What happens | How long |
|---|---|---|
| 1. Notice | the owner (and team, members) is told what's coming, why, and how to stop it | ⏱14 days |
| 2. Hidden | gone from public view; data kept; the owner can still see it and restore it | ⏱30 days |
| 3. Archived | read-only, kept for records; restore only through support | ⏱1 year |
| 4. Removed / deleted | personal data deleted or anonymised; records in section 5 kept | — |

Routes into the stages:
- **The owner closes it:** starts at Hidden (they already know). Accounts keep the existing 30-day restore.
- **Removed for violations:** the enforcement ladder decides; its notice counts as stage 1. Severe cases go straight to
  Hidden.
- **The business closes:** its listings go back to unclaimed (kept as places on the map, marked "Closed" if known);
  its groups follow the transfer order above; its team roles end; open orders finish or refund first.
- **Legal request (erasure, DSAR, 0026):** follows that pipeline's timings instead.

## 4. Inactivity

| Thing | Inactive when | Then |
|---|---|---|
| Group | **no posts or comments for 30 days**, or **no owner or moderator activity for 90 days** | enters the deactivation policy: Notice → Hidden → Archived → Removed (section 3); posting or moderating during Notice stops it. No dormant groups are kept live |
| Claimed listing | owner hasn't signed in or updated for ⏱12 months | reminder to confirm the details; after ⏱18 months marked "unconfirmed" and offered back to claim |
| Business team member | not signed in for ⏱6 months | the owner is told and can end the role; nothing ends on its own |
| Support grant | unused for ⏱12 months | shown "not used since…"; stays on (0102) |
| Account | not signed in for ⏱2 years | a warning email; ⏱60 days later archived; never deleted without a further notice |
| Account with paid service or open orders | — | never treated as inactive while they're open |

## 5. Kept regardless

- The ledger, orders, invoices and payments: kept for tax and legal periods (⏱7 years), with personal details reduced
  to what the law needs.
- The action log (0102): kept ⏱3 years.
- A member's own posts in a group: theirs. When a group is removed, each member can still see and delete their own
  posts until stage 4; then the posts are deleted.
- A nation's data: never deleted (0066); hidden only.
- Places themselves (a listing's location, name, category): kept as an unclaimed place unless the place no longer
  exists.

## 6. Data

| Table | Key fields |
|---|---|
| `lifecycle` | kind (account / business / listing / group), ref, stage, reason, started, next step at, by |
| `transfers` | kind, ref, from, to, requested, accepted, declined |

A daily job advances stages when their time is up and sends the notices. Everything is logged.
