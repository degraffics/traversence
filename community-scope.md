# Community: what's live, what's proposed, and the decisions behind it

Scope of the Community ("Connect") build, checked on 2026-11-01 against the live mirror
(`degraffics/traverse-3way-sync`, `sync-storage/` = `website/`, `sync-app/` = `app/`). Update it as each part ships.

## Live

| What | Where | Decision |
|---|---|---|
| Public community groups: find, join, start (up to 5 per person); open or approval joining; about and member count for anyone, posts and member names for members only | `/community/`, `/community/group.php`, `app/lib/Community.php`, `user/api/community.php` | 0047 (progress notes) |
| Group topics (interest vs local groups) and groups tied to a place, shown on the place's page | `Community::TOPICS`, place picker, `hub/index.php` | 0047 |
| Group roles: owner, moderator, member (pick moderators, accept requests, remove posts or members, hide the group) | `Community::manage` | 0102 §4b |
| Starting a group needs Verification | `MemberVerification::require(…, 'steward')` | 0062 §4 |
| Reports on posts and groups (3 reports hide a post until an admin decides) → Admin → Social → Reports, Standing | `app/lib/Reports.php`, admin `social/reports`, `social/standing` | 0047, 0109 |
| Steward role in the dashboard: "Your communities" with members and join requests | `user/dashboard.php`, `app/lib/Roles.php` | 0055 |
| Private circles (a person's own lists of connections) | `app/lib/Groups.php` | 0047 §4 |
| Groups in Universal Search (posts never searched); the Community feed | `app/lib/SearchIndex.php` | 0053, 0055 |
| Live events from the crawler (a place's official pages), in guides and on venue listings | `app/lib/LiveEvents.php` | 0133 |

## Proposed, not built

| What | Status | Source |
|---|---|---|
| Per-group charter (purpose, house rules, transfer terms) | Steward dashboard shows "Charter — soon"; no charter field in the code | architecture §27, 0102 §4c, 0103 §2a |
| Transfer terms: end the group (default), named successor, longest-serving moderator, moderators choose | Not built | 0103 §2a |
| Inactivity: no posts for 30 days, or no owner/moderator activity for 90 days → Notice → Hidden → Archived → Removed | Not built; no scheduled job | 0103 §3–4 |
| Business-run groups: the business owns the group, team roles map to group roles, posting as the business, sales rep, contact only when the member starts it, post caps by plan | Not built; groups have no business owner | 0102 §4c |
| Steward member reports and Level 2 escalation (3-tier moderation) | Steward dashboard "soon"; admin (Level 3) is live | architecture §27, 0055, master-aos §6 |
| Admin → Social → Groups (table, charter drawer) | "Coming" card in `app/admin/manifests.php` | admin-architecture §6, 0109 |
| Admin → Social → Business groups | Not yet a section | admin-architecture §6 |
| Events made by people (group and business events, public calendar) | "Coming", waiting on "its definition (Jason)"; **no decision covers it** | admin-architecture §6, master-aos |
| Creation gated by trust score; signal-based visibility for new groups | Only Verification and the cap of 5 are live | architecture §27 |
| Community threads into the AI corpus (classified at intake, abstraction only) | Not built | 0011, 0010, architecture §27–28 |
| Rest of gap-review step 8: waypoints and GPX/KML, Trip Planner, Single Home Rule queue, emergency map | Not built | `aos-gap-review.md` §8 |

## Decisions that cover the build

- **Core:** `architecture.md` §27 (the governance model), 0047 (what was built), 0102 §4b–4c (group roles, business
  groups), 0103 (charter transfer terms, inactivity; Accepted, terms set).
- **Around it:** 0055 (Steward role and dashboard), 0062 (Verification), 0053 (search), 0132 (group membership as a
  link), 0052 (messaging between group members), 0058 (Steward badge), 0109 (admin module 6).
- **Safety and data:** 0009 (guest abuse in communities), 0010 (authorship), 0011 (AI corpus), 0066 (no sacred or
  restricted sites).
- **Sequencing:** 0020, 0030 (Connect after Directory and Discovery).

## Out of step

1. 0047's header still says "Design only; nothing built yet", though its progress notes record the build.
2. 0020 calls Communities "unschedulable" until a design pass; the groups are live.
3. §27 says "Steward" and trust-score gating; the code has owner/moderator (both count as Steward in `Roles.php`) and
   Verification.
4. 0103's header read "Proposed" though its terms were set and in use (0120); corrected 2026-11-01. Built so far:
   hand transfer for businesses only (`transfers` table, `/user/transfer.php`); the `lifecycle` table and the daily
   job are still to come.
5. Events has no decision.
6. `/community/index.php` still passes breadcrumbs (`crumbs`), which 0058 rules out on content pages.

## Next build

1. Write an **Events** decision (0103's terms are already set).
2. **Charter and transfer terms** on the group page, shown before joining.
3. **Inactivity and ending:** the `lifecycle` table and a daily `Scheduler::JOBS` entry for groups.
4. **Admin → Social → Groups** and **Business groups**; Steward member reports and escalation.
5. **Business-run groups** (0102 §4c).
6. **Events**, once defined.
