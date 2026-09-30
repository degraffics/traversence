# ADR 0055: App Shell, Dashboards and Roles

**Status:** Accepted (2026-09-30) by Jason. Builds on
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

## Progress (2026-09-30): the page template, Discover, the Pulse, roles, and the collection component

**Checked against the live site first** (the SQL dump and the website zip Jason uploaded, 30 Sept). The search, map
and preset files match. Eight crawler and Review files on the site were older than the working copy (the name-from-the-
web-address, same-location re-crawl, found-listings lightbox, unread Locations pages, and the field checks while
editing), so a catch-up zip restored them. One migration had not been run (`2026-09-30_people_find_and_invite.sql`:
find-by-email or phone settings and invites; the code works without it, those features stay off until it is run).
The error logs held only old, already-fixed errors.

**1. One layout (step 1, §1).** `includes/app-shell.php`:
- The toolbar runs full height along the left edge, just under the header. It shows icons; the tab at its foot turns
  labels on, and the choice is remembered. Items can be grouped under small headings and marked "Soon".
- The workspace fills the right. On phones the toolbar becomes one swipeable row across the top.
- No transitions.
- `tv_app_start()` / `tv_app_end()` give a whole page; `tv_app_open()` / `tv_app_close()` fit a page with its own
  head (the dashboard).

**Discover (step 5) is the first page on it.** Toolbar: Overview, Map, Regions; Outdoors, Food & drink, Culture &
heritage, Stories & guides; Events and Plan a trip (soon); Your places (signed in).
- It follows the visitor's place: the address (`?cluster=`, `?geo=`, `?hub=`) or the place chosen on the site.
- **Overview:** "Ask about {place}" presets (decisions/0053 §5), "What's here" tiles with counts, and regions.
- **Map:** full screen, choosing Things to do, Places to eat, Places to stay or Everything, with a distance. Outdoors
  places get their own peek card.
- **Outdoors: collections instead of repeats.** A place with several parts reads as one ("Luna Lake: 4 places").
  The rest sit under what they are: Lakes & water, Trails, Campgrounds, Day use, Scenic & overlooks, Wilderness &
  forests, Visitor centers. This answers the Outdoors concern in future-considerations; place pages use the same
  collections.
- **Food & drink:** grouped by what places serve (read from descriptions and menus, decisions/0053 §8).
- **Culture & heritage:** Museums & history; Tribal nations & cultural centers, shown as sovereign nations whose
  guidance visitors follow; Arts & galleries; Libraries.

**2. Roles and the Context Switcher (step 4, §4, §8).** `role_grants` and `role_log` tables; `api/lib/Roles.php`.
- **Grants are stacked on one account.** Existing access counts as grants with no data changes:
  - super_admin = Admin Owner;
  - regional_admin = Admin Manager of its region;
  - a listing's owner or manager = its Owner or Manager;
  - owners and moderators of community groups = Steward.
- **What each role can do** follows the tables in §8. No capability reads people's messages or contact cards.
- **Who can give roles:** an Owner can give any role except Owner, by the exact email of a Traversence account. A
  Manager can add General members, and anyone can step down. Every grant and removal is logged.
- **The Context Switcher:** "Acting as" at the top of the workspace, at every size. It shows only the roles the
  person holds, and the choice lasts the visit.
- **Toolbars per role:**
  - Member: Pulse, Address Book, Linked, Groups; Marketplace and Travel plans (soon); Default places, Privacy,
    App settings, Help.
  - A listing: Pulse, Messages & insights (that listing only), The listing, Edit listing, Team & roles.
  - Steward: Pulse, Your communities (with requests to join); Member reports and Charter (soon).
  - Admin: Needs you, Admin work (grouped Content, People, Listings, Money, System; each role sees its own groups),
    Procedures, Review, Reports, Staff & roles.
- **Procedures:** Morning review, Community care and Weekly as step lists linking to each tool. Ticks are kept for
  the day.

**3. The Pulse (step 3, §5).** `api/lib/Pulse.php`, `/user/api/pulse.php`.
- **"Needs you" leads.** It is the old Notification Center, now per role:
  - a listing's unread customer messages and guest questions;
  - a steward's requests to join;
  - staff queues and reports.
- **Then the stream,** newest first: Messages, Community, Discovery, Listings, Marketplace for a member; Messages and
  Guest questions for a listing; group posts for a steward.
- **Filter chips** with counts. A setting chooses one combined stream or a tab per source, and switches each source on
  or off (kept in the browser for now).
- **Concept cleanup (§6):** "Linked Connections" is now **Linked**, "Community Connections" is now **Groups**, and
  "Saved Locations" is now **Default places** under Settings. The bell opens the Pulse.

**4. The collection component (step 2, §3).** `js/collection.js` (`TvCollection.mount`).
- Search within the list; sort; group by; filters, with active ones as removable chips.
- List, Blocks or Lines layout.
- Collapse or expand each group and item, or all at once.
- Select with bulk actions, plus each item's own quick actions.
- The view is remembered per list (in the browser for now).
- The toolbar opens collapsed (search, layout, View, Select). **Linked is the first list on it:** grouped by kind,
  filtered by kind or muted, with bulk Mute, Unmute and Unlink.

**Next:**
- The collection component on Review, the Address Book's contacts and directory results.
- Views and Pulse settings saved to the account instead of the browser.
- Region-scoped staff grants.
- Discover's Trending Connections, which must follow `decisions/0050`: aggregate counts only.
- Events and Plan a trip.
- The cluster and anchor-town naming (future-considerations).

### Follow-up (2026-09-30): the layout on every page; the role switch at your name

From Jason's review of the live site:
- **Every page uses the one layout now,** not just Discover. The shared page frame (`includes/page-shell.php`) opens
  every page inside the app shell, with the toolbar for its part of the site (`includes/section-rails.php`, chosen by
  path):
  - **Let's Explore:** Discover, place pages (with "This place"), recreation places.
  - **Get Local:** listings, claiming a business.
  - **Social:** groups, profiles.
  - **Admin:** Admin work, Review, Reports, Crawler, the portal.
  - **Traversence:** home, Our approach, Privacy, Terms.
- **The crawler pages** (Review, Sources, Stories, Places…) keep their own toolbar, now full height at the left edge
  with the waiting count on Review. The workspace always takes the full width.
- **The role switch is at your name,** at the top of the toolbar, on every page (`includes/context-switcher.php`).
  Tapping your name (your initial when the toolbar is narrow; the first icon on phones, pinned while the row scrolls)
  opens the roles you hold. Choosing one switches the toolbar and workspace. The bar across the dashboard's workspace
  is gone.
- **Every page knows who is signed in:** the page frame starts the same session as the API.
- **Review's counts are honest.** It shows the 300 highest-confidence listings (500 with "show up to 500"), and now
  says how many are waiting in all, so its count matches the badge and "Needs you". Before, it said "430 of 430
  shown" while 972 items were waiting.
- **Two fixes:** Review's "All" chip was dark text on dark (an old crawler-shell style), and the collection component's
  classes are now `cl-*`, so they can't collide with the crawler's `tc-*`.

Not yet on the layout: the home page, the directory (its own full-screen search layout), the business portal, and
sign-in pages.

