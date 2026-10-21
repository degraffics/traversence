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
| Group run by a person | its longest-serving moderator → a member who asks (Safety approves) |
| Group run by a business | the business → its longest-serving member moderator who asks → archived |
| Support grant | the agent Traversence reassigns (0102 §5) |

No one accepts within ⏱30 days → the thing moves to **Hidden** (section 3).

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
| Group | no posts or moderator activity for ⏱6 months | moderators are asked to keep it going; offered to members who'd run it |
| Group | ⏱12 months | archived read-only; members can still see past posts; restorable by a moderator |
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
