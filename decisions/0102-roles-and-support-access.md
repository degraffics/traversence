# 0102: Roles and support access (RBAC master)

Status: Proposed (Jason, 2026-10-21). Builds on 0100 (staff logins and roles). Reviewed against an outside RBAC
compliance paper; adopted what fits a small-business platform, dropped the rest (end of this doc).

## 1. Principle

**The role decides.** If a person's role allows an action, they do it, with no confirmation step and no one else's
approval. If it doesn't, they can't. Keeping the platform hands-off means assigning the right role to each person.

## 2. Three layers, never mixed

1. **Platform staff:** Traversence's own team, on staff logins (0100), with roles that open admin areas.
2. **Business team:** a client's own people on their business. A platform staff member has **no access** to a business
   except through a standing grant or a ticket (section 5), and then only with the role that grant or ticket gives.
3. **Community groups:** members running their own groups (0047). Roles inside a group (section 4b) give nothing
   outside it, and a business or staff role gives nothing inside a group.

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
| Community groups (4c) | F | F (not delete) | — | Sales rep | Moderator | Moderator, posts as the business | — |
| Exports | F | — | — | — | — | — | — |
| Delete the business | F | — | — | — | — | — | — |

Notes:
- The ledger is append-only (0098): no role edits or deletes entries.
- Creator publishing is a per-business setting: off by default for teams, on for a one-person shop.
- Deleting the business keeps the 30-day restore (as for account deletion) as a safety net, not a gate.

## 4b. Community group roles

Already built (0047, `api/lib/Community.php`); listed here so every role lives in one doc.

| Permission | Group owner | Moderator | Member | Signed-in visitor | Anyone |
|---|---|---|---|---|---|
| See about, place, topic, member count | F | F | F | V | V |
| See posts and member names | F | F | F | — | — |
| Join | — | — | — | E (open, or by request) | — |
| Post, comment, leave | F | F | E | — | — |
| Accept join requests | F | E | — | — | — |
| Remove posts or members | F | E | — | — | — |
| Pick moderators | F | — | — | — | — |
| Edit about, topic, place; open or approval joining | F | E (topic, place) | — | — | — |
| Hide or delete the group | F | — | — | — | — |
| Report a post or the group | E | E | E (posts) | E (the group) | — |

- A person runs up to 5 groups. Blocked people don't appear to each other.
- **Platform side:** Safety staff (Reports, 0047) can remove or hide posts and hide a group, and restore them. They
  can't post, change the group's settings, or see member names beyond what reports show.
- **Handing over and ending:** follows the transfer terms in the group's charter (0103 §2a). The default is no
  elections: the group ends. Groups with no posts for 30 days, or no owner or moderator activity for 90 days, enter
  deactivation (0103 §4).
- Support access (section 5) doesn't reach a person's own groups: their owner gets help through an ordinary ticket,
  without an agent acting inside the group. A business's groups (4c) are reached only through the business role a
  grant or ticket gives.
- **The 5:** one person can start and run up to 5 groups (0047), to stop group spam. A business's groups have their own
  allowance by plan and don't use up any team member's 5.

## 4c. Groups run by a business

For a business, its community exists to **educate and win leads**. We support that agenda and keep it from turning
into unhealthy marketing or system abuse.

**Ownership.** The owner of the group is the **business**, not a person. The group shows "Run by <listing name>" and
links to the listing. Staff changes don't affect it.

**Who runs it** comes from the business roles (section 4, Community groups row):

| Business role | In the business's groups |
|---|---|
| Owner | group owner: everything |
| Manager | group owner, except deleting |
| Sales | **sales representative** (below) |
| Support | moderator: accepts requests, removes posts |
| Creator | moderator, and posts as the business |
| Accountant, Developer | nothing, unless they join as an ordinary member |

The business can also appoint ordinary members as moderators (4b limits).

**The charter.** Each business group has a charter: its stated purpose and house rules, shown on the group page. Within
it, the business publishes what it likes: education, offers, events, product news, calls to get in touch. Joining is
consent to receive that inside the group.

**Two voices.** A team member posts **as the business** (the listing's name and logo) or **as themselves** if they're
also a member. The group shows which.

**The sales representative.** A Sales team member acts for the company in the group: posts offers and answers questions
as "Name · Sales, <business>", and replies to members who ask.

**Contact beyond the group: only when the member starts it.** By any of:
1. **Contact the business**;
2. **asking for a quote**;
3. **messaging the rep**;
4. **Link** with the business (0047): the standing opt-in. The business and its reps can then contact the member
   directly and send updates, outside the group too. Unlinking stops it.

Each of these puts a lead into the business's pipeline. Without one of them, the business reaches a member only inside
the group.

**Guardrails** (against abuse, not content):

| Stops | How |
|---|---|
| Outreach nobody asked for | no messages outside the group unless the member started contact or linked; no exporting member lists |
| Flooding | posts per day capped per group by plan; repeated identical posts held |
| Bleeding beyond the group | business posts show in the group and its members' Community feeds only; never pushed to non-members or made to look like platform recommendations |
| Bait and switch | a group can't misrepresent its charter; "not what this group says it is" reports go to Safety |
| Harmful content | the site's rules, reports and enforcement ladder apply as everywhere |
| Fake engagement | no paid or rewarded joins, likes or reviews; sock-puppet patterns flagged |
| Misuse of member data | the team sees member names in the group only; no tracking off it; no selling or sharing |

Members can always leave, mute the business's posts, or report them.

**Allowance:** groups per business set by plan (e.g. 1 on free), outside any person's 5.

**Ending:** a business group follows the lifecycle in 0103 (transfer, hiding, removal, inactivity).

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
| `community_groups` (add) | `business_id` (set when a business runs it), `charter` |
| `community_group_members` (exists) | group, user, role (owner / moderator / member), status |
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

## Addendum (2026-10-23)

Route 1 is built for White Glove accounts: decisions/0117. The grant is automatic with a plan that includes White
Glove and the account's rep, moves with the rep, and is scoped by the Terms (`#white-glove`); the owner can turn it off.
