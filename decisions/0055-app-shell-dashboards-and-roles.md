# ADR 0055: App Shell, Dashboards and Roles

**Status:** Proposed (2026-09-30), from Jason's UI and roles direction; accepted before build. Builds on
`decisions/0046` (crawler shell), `decisions/0052` (one messaging system), `decisions/0054` (Nexus) and the
Steward role in `architecture.md` §11/§27. Messaging step 7c (Discovery page and navigation) is delivered through
this ADR.

## Context

Features have grown page by page: the account hub, Business Insights, the crawler, community groups, the Address
Book. Each has its own layout and list style, and some concepts overlap (Saved Locations, Linked Connections,
Community Connections, Favorites). People who hold several roles (a member who manages a listing and stewards a
group; an admin) have no clean way to move between them.

## Decision

### 1. One layout everywhere: toolbar left, workspace right

- **Desktop:** the left is always the **toolbar**, quick access that routes back to anywhere the person needs to be
  in their current role. The right is the **workspace**: the content or task at hand.
- **Phone:** the toolbar becomes a row of icons across the top of the workspace (as the account hub does now).
- **No animated transitions on navigation.** It opens and closes instantly.

### 2. The header: primary funnels, collapsible

- **Expanded:** the logo lockup (compass + "Traversence, The Immersion and Connection Engine"), the notification
  bell, and the five **primary funnels**: AI Assist, Discover, Directory, Community, Marketplace. After them come the
  profile and the hamburger.
- **Minimized:** only the logo mark, the bell and the hamburger. A small tab collapses or expands each side, and
  the choice is remembered. Phones use the minimized header, with the funnels in the hamburger menu.
- The global search sits beside the logo (search rework, `decisions/0053`).

### 3. The workspace: every collection works the same way

One collection component for every list (Review, directory results, contacts, links, groups, inbox, listings):
- **search, sort, filter, group by**, with active filters shown as removable chips;
- **layout toggle:** List, Blocks (cards), Lines (compact rows);
- **collapse / expand** on every item and group;
- **select and act** (bulk actions), with each item's own quick actions on the item;
- **the view is remembered** per person and per collection.

**Toolbars open collapsed**: view toggle, settings and hamburger. Expanding keeps the main tool header in place and
gives quick access to each item's settings.

### 4. Dashboards per role, switched from the top

- **Global Context Switcher:** a toggle next to the person's name switches the whole toolbar and workspace to the
  role chosen: Member, a listing or Nexus they manage, Steward (for their communities), Admin. It shows only the
  roles the person holds.
- **Member and Listing dashboards open on the Pulse** (§5).
- **Steward dashboard:** their communities, the charter, member reports, escalations (Level 2).
- **Admin dashboard:** a cleaner sidebar with sections, sub-tools and quick actions (§7).

### 5. The Pulse: one place for everything incoming

For members and listings, the dashboard opens on the **Pulse**: notifications, inbox and feeds in one stream.
It covers messages, requests, business messages, guest questions, group posts, and Discovery, Listings and
Marketplace updates.
- Filter chips: All, Messages, Community, Discovery, Listings, Marketplace.
- A setting: one combined stream, or each source as its own tab. Each source can be switched on or off.
- The Notification Center becomes the top of the Pulse (what needs you), not a separate place.

### 6. Fewer, clearer concepts for what you keep

The words people see are narrowed to three:
- **Linked:** open connections to businesses, people, places and topics. They fill your feeds. "Saved
  Locations", "Favorites" and "Linked Connections" become this one list, with tabs for People, Businesses, Places and
  Topics. Links to places and topics have no other side, so they are already private.
- **Address Book:** private contacts (`decisions/0052` §2).
- **Groups:** communities you belong to. They are run on the community pages themselves; the dashboard shows
  shortcuts, and their posts appear in the Pulse.

### 7. Admin work: grouped tasks, routine procedures, automation

- Admin tools are grouped by job: **Content** (Review, crawler, sources, places), **People** (reports, accounts,
  Stewards), **Listings** (claims, Nexus), **Money** (when billing exists), **System** (settings, integrations,
  logs). Each section has quick actions and a count of what needs attention.
- **Procedures:** routine processing is written down as step lists ("Morning review: tag leads, then duplicates,
  then ready listings…") that walk the admin through each step. Every step that can be automated is (auto-import,
  auto-retry, second look), and people handle only exceptions.

### 8. Roles: two separate sides

**Admin side (Traversence staff):**

| Role | Scope | Can | Cannot |
|---|---|---|---|
| Owner | Everything | Staff accounts, integrations, settings, final escalation | – |
| Accountant | Finance | Revenue, refunds, commissions, costs | Support tickets, content, people's messages |
| Executive Assistant | Delegated by an executive | Reports, scheduling, notes on their behalf | Anything not delegated; every action logged as "on behalf of" |
| Manager | A region or a queue | Assign work, watch response times, approve exceptions | Global settings |
| General | Sales or support | Leads in their territory; claim and answer help tickets | Other territories, billing, settings |

**User side (a listing's or Nexus's own team):**

| Role | Scope | Can | Cannot |
|---|---|---|---|
| Owner | The organization | Plan, billing, profile, add and remove team members, close it | – |
| Accountant | Its money | Invoices, payment methods, statements | Listing content, messages, support tickets |
| Executive Assistant | Delegated by the Owner | Reports, training, basic profile edits | Anything not delegated |
| Manager | A location or department | Team metrics, add General members, escalate issues | Billing, closing the organization |
| General | Day-to-day | The listing's tools: answer messages, update hours and posts | Billing, team, other people's data |

**Members** (people using Traversence for themselves) are a third kind of account with no team roles. **Stewards**
are a per-community grant stacked on a member account (`architecture.md` §11/§27), not a staff role.

**Rules for every role:**
- Roles are grants stacked on one account, never a single `users.role` column (`architecture.md` §11).
- Least privilege: each role sees only its own scope.
- **No role can read people's private messages or contact cards.** Staff see a conversation only through a report
  the person chose to file (`decisions/0052`).
- Delegated and elevated actions are logged: who, on whose behalf, when.
- Money roles exist in the model now; their screens come with billing (`commercial.md`).

## Consequences

- Build order, each shipped and tested before the next:
  1. **Shell:** the header per the mockups (logo lockup, bell, funnels, collapsible, no transitions), the left
     toolbar and right workspace on every page.
  2. **Collection component:** search, sort, filter, group, layout, collapse, select, remembered views. First
     applied to Review, then contacts, links and directory results.
  3. **Pulse and concept cleanup:** one stream with filters and a tabs setting; Linked replaces Saved Locations and
     Favorites; Groups shortcuts.
  4. **Roles and the Context Switcher:** a grants table for both sides, the switcher, per-role toolbars, the
     Admin sidebar regrouped, procedures.
  5. **Discovery page** per the mockup: category rail (Explore Regions, Culture & Heritage, Adventure & Immersion,
     Culinary Exploration, Travel Planning & Events), the main story, Trending Connections.
- Existing role checks (`is_admin`, `admin_access`, `listing_access`) are mapped to grants in step 4, without
  breaking current access.
- Open: whether Stewards get any Traversence-wide view (default: only their communities); what "Trending
  Connections" ranks by, which must follow `decisions/0050` (aggregate counts only, no personal targeting).
