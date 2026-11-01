# 0109: The admin rebuilt: modules, sections, widgets and workflows

Status: Accepted (2026-10-22) by Jason. Replaces the workspace pages of ADR 0104 (`admin/w.php`) and the area pages
of ADR 0078 as the admin's way around. The full outline is `admin-architecture.md`. This record says what was decided
and what zip 1 builds.

## Why

The admin grew page by page. Each tool had its own layout, its own lists and its own idea of a stage, and the menus
pointed at whatever page existed. Jason (2026-10-22): "This admin build feels random." The rebuild fixes the structure
once, so a new tool takes its place in it instead of adding another page.

## The four layers

1. **Modules:** the 11 workspaces of the Master AOS §8. They're defined in code (`app/admin/manifests.php`), in AOS order.
2. **Sections:** each module's screens, in menu order. A section is built from widgets, or it's a landing page saying
   what's coming.
3. **Widgets:** the parts every section is built from (`app/admin/widgets.php`, `js/admin-kit.js`):
   - a Table, or a Pipeline (a table with the stage filter);
   - MetricCards, which filter the list below them when tapped;
   - the DetailPanel drawer;
   - quick tasks;
   - bridges.
4. **Workflows:** stages and moves, stored in the database and edited in the admin (System Configuration →
   Workflows). Each published version is kept.

## Decided

- **Addresses:** `/admin/<module>/<section>[/<id>]`, such as `/admin/backoffice/orders/922`. There is one endpoint,
  `/admin/api/<module>/<tool>`, and every tool answers the same actions: list, get, move and create.
- **Placements:**
  - Guides → Explore
  - Hidden places → Nations (in Directory & Places)
  - GO Codes → Market
  - Intake → Listings
  - Logs → System Configuration
- **Old pages are archived, not deleted.** They're out of the menus but still open by direct link and from the site
  map. Opened on their own, they say where their work is now. Old addresses don't redirect; we start fresh.
- **Tools at hand never leave the page.** A tool from another module opens over the work in a drawer, two deep at
  most:
  - Back closes the top drawer;
  - what you were doing is kept (search, filters, page, the open item).
  - Each module lists its tools at hand as round icons beside the section menu.
- **Access follows the tool.** Who may do what is the same wherever the tool is opened:
  - a module opens by department (AOS §9A);
  - each action has its own rule, a lowest level plus departments, which can be changed in System Configuration →
    Permissions;
  - an action someone may not take is greyed out, with the reason ("greyed out is fine").
  - The Founder and Executives may do everything.
  - An Assistant acts with the level of the person they assist.
- **Workflows are built by Managers and up.** The publisher chooses whether items in progress stay on their version
  or move to the new one. When a stage is gone, the publisher says where its items go.
- **The built-in workflows** are client orders, leads, subscriptions and promotions (zip 1). Each one reads its stage
  from the site's own records, so there's no second copy of the state, and does its protected moves through the
  library that owns the record (`ClientOrders`, `Leads`, `Billing`, `Promotions`).
  - **What the builder can change:** a stage or move that does something with money or data can be renamed, timed and
    given an owner, and stages can be added around it.
  - **What it can't change:** that stage or move can't be removed, and neither can its checks.
  - **Moves that happen elsewhere** (the client approves, a payment arrives) are marked as happening on their own.
- **Tables everywhere.** Kanban only if a table proves not enough.
- **White Glove follows the subscription:**
  - one engagement per client;
  - the client and their rep choose the services together;
  - the services are top level and flexible, with suggested tasks (never required ones);
  - opportunities stay in view.
  - It's built after this rebuild, and its sections are landing pages until then.
- **Coming sections** are greyed out in the section menu, each with a landing page that says:
  - what it will do;
  - what it waits on;
  - what to use meanwhile.
- **The merchant side** (the business portal) comes after this, on the same engine.
- **No pilot:** the full scope, in two zips.

## Zip 1 (this build)

- **The engine:**
  - `app/admin/Access.php`, `Workflow.php`, `workflows.php`, `AdminModules.php`, `manifests.php`, `tools.php`, `widgets.php`,
    `sections.php` and `kit-css.php`;
  - the migration `2026-12-24_admin_engine.sql`, which adds the tables `wf_workflows`, `wf_versions`, `wf_items`,
    `wf_history` and `admin_permissions`.
- **The frame:**
  - `admin/router.php`, `admin/api.php` and `admin/.htaccess`;
  - `js/admin-kit.js` and `js/admin-builder.js`;
  - the menu of modules, the section dropdown, the tools at hand and the drawers.
- **Rebuilt from widgets:**
  - Back-Office: orders, with the client-order pipeline and site orders; products; vendor orders; plans and
    subscriptions.
  - Sales & Growth: calls today, prospects, scan cards, promotions, territories and the funnel.
  - System Configuration: the workflow builder and the permissions matrix.
- **Bridged:** every other live section shows today's tool inside the new frame until zip 2 rebuilds it.
- **A workflow made in the builder** gets its own section in the module it was made for, with its items and
  "New item".

## Zip 2 (built 2026-10-22)

- **Seven more built-in workflows**, each reading its stage from the site's own records and doing its moves through
  the library that owns them:
  - **Claim:** confirming their email → managing, to verify → verified, declined or withdrawn; on hold. Any time:
    log a call, ask for more. Uses `Claims`.
  - **Place review (landmarks):** waiting → shown → hidden, or closed by a nation. Held places stay held
    (decisions/0066). Uses `Landmarks::review`.
  - **Journey:** draft → published → hidden. Uses `Journeys`.
  - **Experience:** suggested → waiting for review → published, not approved, or hidden. Uses `Experiences::review`.
  - **How-to guide:** wanted → the crawler is looking → to review → published; nothing found; not a guide. Uses
    `HowGuides`.
  - **Report:** new → taken down or left up; put back. Uses `Reports::decide`, which also moves the author's
    standing.
  - **Standing notice:** open → review asked → put right, lifted or closed. Uses `Standing::decide`.
  - Moves that happen on the other person's side (the claimant confirms, the member asks for a review) are marked
    as happening on their own.
- **Rebuilt from widgets** (lists with search and filters, the detail drawer, quick tasks):
  - **Directory & Places:** Listings, Review (what visitors told us), Claims, Nexus, Landmarks, Nations.
  - **Journeys & Exploration:** Journeys, Experiences, Stories, Resource guides, How-to guides (with "Ask for a
    guide").
  - **Market:** GO Codes & short links.
  - **Community:** Reports, Standing.
  - **Back-Office:** People (members and staff).
  - **Insights:** Sources.
  - **System Configuration:** Logs (actions, workflow moves, staff sign-ins).
  - **Dashboard:** Quick actions, each opening over the Dashboard.
- **Kept as their own tools inside the frame** (module widgets: interactive tools rather than lists, opened in the
  section and in drawers):
  - the Helper; Find, merge & bulk edit; Intake from a website; the crawler's review cards; Identities; Recreation
    sites; Map pins; Hidden places; Taxonomy;
  - Procedures; Add from the field;
  - Payments; Money reports; Staff levels; Staff logins; Import & export;
  - the Insights numbers; the Search workbench; the Crawler; the Data browser;
  - Settings; the Site map.
- **Every old admin page is archived to a section:** out of the menus, still open by its address and in the site map,
  with a note saying where its work is now.
- **Fixed along the way:**
  - The member dashboard's Pulse feed is back. The staff Pulse had replaced the class it used, so the member feed is
    now `MemberPulse`.
  - The Pulse case panel's own actions work: resend, record a payment, due date, order note, void, and edit or
    delete a post or a note. They were wired in the panel but had nothing behind them (`Pulse::manage`).

## Rule from here

No admin page outside the manifests: a new admin tool is a section in `app/admin/manifests.php` (and, for a list, a
tool in `app/admin/tools.php`). It isn't a new page in the menus. See `CLAUDE.md`.
