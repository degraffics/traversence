# 0078. The admin dashboard: one menu of areas, each area one page of tools

- **Status:** Accepted
- **Date:** 2026-10-11
- **Builds on:** 0047 (account hub), 0055 §1 and §4 (one layout; roles and contexts), 0062 (Helper), 0074 and 0076
  (Manage listings)
- **Changed by:** 0079 (§3 Members: no browsing, a reason to open an account, notices instead of Suspend)

## Context

Jason: "The admin dashboard needs to be like the user profile dashboard, just focused on the admin management tools
instead of user-focused tools." The admin side was split across three places:

- the member Dashboard in its Admin role (Today, Procedures, Staff & roles);
- a placeholder `admin/adminportal.php` with tiles, most of them "not built yet";
- a grouped admin menu whose groups opened into 25 or so pages.

Nothing gave staff one front door, and accounts and settings had no tools.

## Decision

1. **`/admin/` is the admin dashboard.** It has the member Dashboard's layout: the menu on the left (on a phone, the ☰
   tools button), the page on the right. The menu is flat, eight entries:

   | Entry | Page | Sections |
   |---|---|---|
   | **Today** | `admin/index.php` | Today (what needs a person, notifications, inbox, system status, key numbers: `tv_admin_dashboard()`); Helper |
   | **Listings & places** | `admin/listings.php` | Overview, Review, Find & edit, Add, Nexus, Claims, Identities, Published, Crawl a site, Experiences, Journeys, Landmarks, Nations |
   | **Search** | `admin/search.php` | Workbench & missed searches, Learning, Oversight, Resource guides, How-to guides, Search index, Search demand |
   | **Safety** | `admin/safety.php` | Reports, Procedures, Hidden places |
   | **Data & crawler** | `admin/data-crawler.php` | Crawler, Sources, Stories, Places, Coverage, Map points, Data loads, Data browser |
   | **Insights** | `admin/insights.php` | (one page) |
   | **People & roles** | `admin/people.php` | Members, Staff & roles |
   | **Settings** | `admin/settings.php` | How it's set up (read-only) |

2. **Each area is one page; its tools are its sections.** They sit in a row of tabs at the top and switch in place.
   This is the pattern Manage listings started, now shared as `app/ui/admin-hub.php`:
   - `tv_admin_require()` checks access;
   - `tv_admin_start()` opens the page with its area lit in the menu;
   - `tv_admin_hub()` draws the tabs and sections;
   - `tv_admin_end()` closes the page.

   A tool section shows the tool's own page in its embedded view, loaded when its tab first opens. The section is kept
   in the address (`/admin/search.php#oversight`). A tool opened on its own still works, and the menu lights its area
   (`tv_admin_area()`, from the page's address).
3. **People & roles (new).**
   - **Members:** find anyone by name, email or #number; the newest are shown until you type. A member's card shows:
     - their account (email, phone, joined, email and age confirmed, area, comments, reports sent);
     - the roles they hold;
     - Verified, with each check;
     - their privacy choices;
     - their listings.

     Staff can **Suspend** a member (signed out everywhere, can't sign in) or **Restore** them. The People role is
     required (staff Owners and Managers). You can't suspend yourself or anyone with access to all of Traversence.
     Each change is logged in `role_log`. Messages, Address Books and contact cards are never shown.
     The endpoint is `admin/api/people.php`.
   - **Staff & roles:** staff and regional managers, and what each can do. Owners add and remove roles. This moved
     here from the member Dashboard and uses the same `/user/api/context.php`.
4. **Settings (new, read-only).** Shows how the site is set up, from `.env` and the server:
   - auto-publish and its confidence;
   - the crawler queue;
   - analytics;
   - videos;
   - staying signed in;
   - email sending;
   - the site address;
   - the default place;
   - the claims phone line;
   - service keys;
   - PHP and MySQL.

   Passwords and keys are shown only as set or not set. Changing settings here, and a maintenance notice, are coming.
5. **The member Dashboard stays the person's own.** In the Admin role it shows the member menu with **Admin
   dashboard** first. Its front page and the old admin sections go to `/admin/`: `#admin`, `#team` →
   `/admin/people.php#staff`, `#procedures` → `/admin/safety.php#procedures`. Switching to Admin from anywhere else
   opens `/admin/`, and any admin page puts an admin in their Admin role.
6. **Old addresses keep working.**
   - `admin/adminportal.php` redirects (301) to `/admin/`.
   - The top menu's admin link, the admin back link, and links in the tools and the Today page point to `/admin/`
     or the right area.
   - The root `dashboard.php` redirect to the business portal is unchanged.

## Consequences

- A new admin tool is added as a section of its area (one line in that page's list), not as a menu entry.
- Pages a tool links to by their own address still open on their own, with the menu lit on their area.
- The FAQ says what staff can see about an account and that accounts can be suspended. "Who can see my email?" now
  says that staff who look after accounts can see it.
- Next: change settings from the Settings page (stored in the database, `.env` as the default); a maintenance notice;
  a member's history (suspensions, roles given) on their card.
