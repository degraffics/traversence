# Admin architecture: modules, widgets, workflows (outline)

Status: **Outline, decisions recorded** (2026-10-22); ready to become the build's decision record once approved. Nothing gets built until it's approved. Replaces
`admin-map-v2.md` (a page-by-page map) with a structure: **modules → sections → widgets**, and **workflows**
configured in the admin.

Follows: Master AOS §8 (the 11 workspaces), §9A (staff levels and departments), §9B and §10 (merchant roles and
delegation), §11 (data). Decided by Jason (2026-10-22):
- **Start fresh:** no redirects from old admin addresses.
- **Full scope, no pilot.**
- **Tables everywhere.** Kanban is looked at later, only if a table proves not enough.
- **Workflows are created, configured and edited in the admin** from the start, by **Managers and up**.
- **Publishing a changed workflow:** whoever publishes chooses whether items in progress move to the new version.
- **What you may not do shows greyed out, with the reason**, never hidden. Jason, as Founder, has everything.
- **Coming sections show greyed out in the menus** and open a landing page explaining what's coming.
- **White Glove follows the subscription:** services are configured per client, not a fixed checklist (B8, C).
- **The merchant side comes after the platform admin**, on the same engine.

---

## Part A. The architecture

### A1. Four layers

| Layer | What it is | Where it's defined | Example |
|---|---|---|---|
| **Module** | one of the 11 workspaces (AOS §8): its sections, who opens it, its endpoints | code: one manifest per module | Back-Office |
| **Section** | one screen in a module, with its own address; a layout of widgets | the module's manifest | Back-Office → Orders |
| **Widget** | a reusable building block (table, panel, stepper…); takes a config and a data source, knows nothing about where it sits; can open in a section, a drawer or a quick task (A3a) | code: the widget library | Table, DetailPanel |
| **Workflow** | the stages something moves through, the moves allowed, who may make them, what's checked, what happens | **the database, built and edited in the admin** | Client order: Draft → … → Shipped |

Underneath all four:
- **Domain libraries** keep the business logic that already works (Billing, ClientOrders, Sales, Claims,
  QuickBooksPay, Promotions…). Workflows call them through hooks; they aren't rewritten.
- **Endpoints**: one per tool, with the same actions everywhere (A5).
- **Access**: AOS §9A levels × departments, checked once by the router (A6).
- **History**: every move and every action recorded, never edited.

### A2. Modules (code)

- **One manifest per module**, `app/modules/<module>.php`, declaring:
  - the module's key, number, name and icon (AOS §8);
  - the departments that open it (§9A);
  - its sections: key, name, icon, status, the layout of widgets, who may open it, and the **actions by level**
    (Director configures, Manager assigns and approves, Staff does the work);
  - the module's endpoints.
- **Status per section:** live, partial or coming. **Coming sections appear greyed out** in the menus. Opening one
  shows its **landing page**: what it will do, how it fits the module, what it waits on, and the tools you can use
  meanwhile.
- **Addresses:** `/admin/<module>/<section>`, and `/admin/<module>/<section>/<id>` for one item (opened in the detail
  panel). No other admin page addresses exist.
- **The shell** (one for the whole admin):
  - the ☰ module list on the left of a phone, with the person's profile and role on the right;
  - inside a module, its sections as a **Shortcuts-style dropdown** (the inline-filter rule);
  - crumbs "Module › Section › Item";
  - the detail panel;
  - the landing page for coming sections.

### A3. Widgets (code): the catalog

Every widget gets:
- **a config:** columns, fields, labels, the workflow to follow;
- **a data source:** an endpoint and its filters;
- **the person's permissions,** so it shows only the actions they may take.

Widgets talk to their endpoint and to each other through events (selected, changed, advanced), never directly. Each
widget is a PHP renderer plus one JS file, with no build step.

**Lists and views**

| Widget | Does | Options |
|---|---|---|
| **Table** | rows from a source; search, sort, paging, bulk actions, export; tap a row → DetailPanel | columns, row actions, bulk actions, density |
| **Pipeline** | a Table bucketed by workflow stage: a stage dropdown (FilterMenu) with counts; each row shows its stage and SLA; moves from the row or the detail panel (a kanban view can be added later if a table isn't enough) | workflow |
| **FilterMenu** | the Shortcuts-style dropdown: icon, current choice and its count ▾, each option with its icon and count | options from the source |
| **SearchBox** | search inside a source; feeds Table or Pipeline | placeholder, fields |
| **Queue** | one item at a time, with Skip and Done (Helper, Review) | source, actions |
| **Stream** | the Pulse stream: chips, pinned critical items, Done | channels |
| **MetricCards** | a row of numbers, each one tappable to filter | metrics |
| **Chart** | a line, bar or funnel from a source | type, series |
| **Map** | points, areas and clusters | layers |
| **Calendar** | dated items (events, follow-ups) | source |

**One item**

| Widget | Does | Options |
|---|---|---|
| **DetailPanel** | opens one item beside the list (a desktop) or full screen with ← (a phone); holds other widgets | sections |
| **Stepper** | the workflow header: stages, where the item is, how long it's been there against its SLA | workflow |
| **StageActions** | the buttons for the moves allowed from this stage, for this person; asks for what a move needs (a note, a field) | — |
| **ActionBar** | actions allowed at any stage (refund, credit, chargeback, note, reassign, cancel) | actions |
| **Fields** | view and edit an item's fields in place, with field-level masking for support tiers (PII) | fields, editable by level |
| **Form** | make a new item | fields, validation |
| **Timeline** | the item's history: moves, actions, notes, emails, who and when | — |
| **Notes** | team notes and mentions | — |
| **Files** | files and proofs, with versions and approval | kinds |
| **Assignee** | the owner, department and assistant | who can assign |
| **Related** | linked items (an order's vendor jobs, a lead's cards, a listing's claims) | relations |

**Settings and builders**

| Widget | Does |
|---|---|
| **SettingsForm** | a module's settings (Directors only), with what each setting does |
| **WorkflowBuilder** | create and edit workflows (A4) |
| **PermissionMatrix** | levels × departments × actions for a module |
| **TemplateEditor** | email and notice templates with fields |

**Module widgets:** a few tools are too specific to be generic. They stay as their own widgets, registered in the
catalog, with a config and a data source like the rest:
- CardScanner;
- ClientOrderBuilder (the line editor);
- ShippingManifest;
- PlanEditor;
- PromoMaker;
- LeadPanel's call log;
- SearchWorkbench;
- QRDesigner.

### A3a. Tools at hand: use any tool without leaving your work

Rule: **one home per tool, reachable from anywhere, without leaving where you are** (Jason, 2026-10-22). A tool's code
lives only in its home module. Other modules don't link to it: they **open it over the current work**. You do the
task, close it, and you're exactly where you were.

**How a tool can open** (each tool declares which of these it supports):

| Opens as | For | Desktop | Phone |
|---|---|---|---|
| **Drawer** | look at or change one thing: edit a listing, adjust a territory, check a product, see an order | slides over the right side, the work behind it dimmed and kept as it was | a full-screen sheet; ← or the phone's Back closes it |
| **Quick task** | make something in a few steps: scan a card, new client order, start a trial, add from the field | a centred panel, stepped where needed | a full-screen sheet with steps |
| **Full page** | a long, deep job: build a large order, rebuild a Nexus | the tool's own section | the same |

**What makes it work**
- **Your place is kept.** The section underneath stays loaded: its filters, search, page, scroll and open item. A
  drawer or quick task never reloads it.
- **The result comes back.** When the tool finishes, it tells the section you came from. For example:
  - a scanned card adds or updates the lead in your prospect list;
  - an edited listing updates the lead's row;
  - a new order appears in the lead's Related list.
- **One over another, two deep at most.** From a lead (drawer) you can open its listing (a second drawer). Closing it
  returns you to the lead, then to the list.
- **The Back button closes the top layer**, never leaving the page, and a refresh reopens the same layers (they're
  kept in the address, like `…/prospects#lead=4378&listing=4378`).
- **Going to the full page keeps your place.** Your view (filters, search, page, scroll) is saved for the module, and
  **← Back to Prospects** returns you to exactly that view.
- **Access follows the tool, not the module.** Each action in a tool has its own permission, the same wherever the
  tool is opened (a Sales rep may edit a listing's details; merging needs an Operations Manager, as an example). An
  action you may not take **shows greyed out with the reason** ("Merging needs an Operations Manager"); the endpoint
  refuses it too. The rules are set in System Configuration → Permissions. The tool's own checks and its endpoint (`/admin/api/<its home module>/…`) apply wherever it's opened.
- **"At hand" in every module:** each module's manifest lists the tools from other modules its people use most. They
  appear as icons in the module's tool bar and as actions in its detail panels (for example "Edit listing" on a lead).
- **Built once:** the shell holds one drawer stack and one quick-task panel for the whole admin. Tools render into it
  as widgets (no frames, no separate pages), using the same endpoint contract.

### A4. Workflows (database, edited in the admin)

**What a workflow holds**
- **Identity:** its key, name, module, what kind of item it moves (order, lead, claim…), a version, and its state
  (draft or published).
- **Stages:**
  - key, name, colour and icon, and order;
  - **start** or **end**, plus whether it counts as done, lost or cancelled;
  - the **SLA** (hours allowed in the stage);
  - the **owner** (department, and optionally a role);
  - the **widgets shown in the detail panel at this stage**.
- **Moves** (from stage → to stage):
  - a label (for example "Send to client");
  - **who may make it** (levels and departments; or the client or owner, for client-side moves);
  - **checks** that must pass;
  - **what it asks for** (a note, a field);
  - **what happens** after it.
- **Any-stage actions:** moves allowed from anywhere: refund, credit, chargeback, cancel, hold, reassign.

**Checks and what-happens are picked from a list, not written.**
- They're built in code and offered in the builder by name, with a plain description and the settings each takes.
- **Checks**, for example:
  - every proof approved;
  - billing address present;
  - paid in full;
  - has a card on file;
  - claim verified;
  - floor price kept.
- **What happens**, for example:
  - make the QuickBooks invoice;
  - charge the card on file;
  - email the client (with a template);
  - create a Pulse item;
  - issue the GO Codes;
  - start White Glove onboarding;
  - send to the vendor;
  - move the lead.
- So a workflow can be built and changed in the admin without breaking the money or the data.

**The builder** (System Configuration → Workflows, **Managers and up**)
- List the workflows, with how many items are in each stage.
- **Edit as a draft:**
  - add, rename, reorder or remove stages (drag);
  - draw the moves in a from × to grid;
  - for each move, pick who may make it, its checks, what it asks for, and what happens;
  - set the SLAs and owners;
  - pick each stage's widgets.
- **Check before publishing:**
  - every stage can be reached;
  - every stage except the end ones has a way out;
  - money stages keep their protections (B below).
- **Preview:** click through a test item.
- **Publish as a new version.** Whoever publishes chooses for the items in progress: **keep them on their version**,
  or **move them to the new one**, mapping each old stage to a new one.
- History of versions: who changed what, when.

**Protections**
- A stage or move that charges money, refunds or issues something can only be removed when no item is in it. Its
  checks can't be removed at all (for example "paid in full" before Shipped on a paid order).
- **Built-in workflows** (C below) start from defaults matching how the site works today. They can be edited but not
  deleted. New workflows can be created freely.
- Every move goes through one engine: it checks the move is allowed for this item, stage, person and version, runs
  the checks, makes the move, records it, then runs what-happens. A failed what-happens (QuickBooks down) is retried
  and shows on the item; it never leaves a half-made move.

**Data** (tables): workflows, workflow_versions, workflow_stages, workflow_moves, workflow_items (an item's workflow,
version, stage, entered at, owner), workflow_history (every move and action: who, when, from, to, note), plus the
registry of checks and what-happens (in code).

### A5. Endpoints (one contract)

- `/admin/api/<module>/<tool>.php`, one per tool, with the same actions everywhere:
  - `list`: filters, search, sort and page → rows, counts per stage and per filter option;
  - `get`: one item, with its stage, the moves allowed for this person, its history;
  - `create`, `update`: fields;
  - `move`: a move by its key, with what it asks for → the item's new state;
  - `act`: an any-stage action;
  - `export`.
- **Workflow endpoints:** `/admin/api/config/workflows.php` for the builder.
- One JSON shape for rows, items and errors. Links in fields are wrapped past ModSecurity as now.
- The current `/admin/api/*` and `/api/admin/*` endpoints are folded into these. Public and member endpoints
  (`/api/*`, `/user/api/*`, `/listing/api/*`) stay as they are.

### A6. Access (AOS §9A)

- **Opening a module:** by department (the table in §9A). Founders and Executives open all 11. An Assistant opens
  what their person opens. A Vendor opens nothing except a ticket.
- **Doing things, by level** (each action's rule set in System Configuration → Permissions; an action you may not take
  shows greyed out with the reason):
  - Director configures (settings, workflows, templates);
  - Manager assigns and approves;
  - Staff does the work;
  - Executive sees everything but doesn't configure or run queues;
  - Founder can do everything.
- **Support tiers (Service Staff):** fields marked PII are masked for Tier-1. Tiers 2 and 3 add queues, logs and
  telemetry as §9A says.
- **Checked once,** by the router: the section, then each action and move. Widgets only show what's allowed.
- **Delegation (§10):** an agent acting for a business works through the same widgets, with the business role, a
  banner, and every action in the log.

### A7. Standards every section follows

- **Phone first:**
  - 390px with no sideways scroll;
  - cards edge to edge with 3px each side;
  - the name is the link, with the Link button top right;
  - actions as 30px icon buttons down the right edge.
- **No browser dialogs** (TvAsk), password rules, ModSecurity wrapping, MySQL-safe migrations: as in `CLAUDE.md`.
- **The site map** (System Configuration → Site map) checks the build: every route is in a manifest, every widget is
  in the catalog, every workflow is valid, no file is missing.

### A8. The merchant side (AOS §9B)

- A business's own dashboard (owner, manager, accountant, sales, support, creator, developer) runs on **the same
  engine**: its own modules (Orders, Customers, Catalog, Team, Billing), the same widgets, and **its own workflows**
  (a business's order or lead process), configured by its Owner or Manager.
- In scope for the structure now; built after the platform admin.

---

## Part B. The structure (AOS §8, everything placed)

**How to read it:**
- **Built from** lists today's tools, so nothing is lost.
- Status: **L** live, **P** partial, **C** coming.
- Sections marked C appear greyed out in the menu and open a landing page explaining what's coming.

### 1. Dashboard & Quick Actions
- **Opened by:** everyone (each person sees their own).
- **AOS §8:** task queues, urgent alerts, adaptive shortcuts, manual order processing.
- **At hand (opened over the work, A3a):** Every Quick action: each opens as a drawer or quick task over the Dashboard.

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| Today | MetricCards (your numbers), Queue (your task queue across workflows: items in stages you own, SLA first), Stream (critical alerts, pinned) | all (reads stages you own) | Dashboard board | L |
| Quick actions | a grid; each opens its tool's Form or section in the DetailPanel: Scan a card, New client order, Find a listing, Add a listing, Calls today, Review, GO Codes, Settings | — | Quick actions | L |
| Helper | Queue (one item at a time across every queue) | — | `helper.php` | L |
| Shortcuts | adaptive: your most used sections | — | Shortcuts menu | P |

### 2. Pulse Stream (Unified Communications)
- **Opened by:** everyone; each department its own channels.
- **At hand (opened over the work, A3a):** Any item's own tool, opened beside the stream (the split drawer): an order, a lead, a listing, a report, a claim.

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| Stream | Stream, FilterMenu (channels: Inquiries, Sales, Fulfillment, Payments, Safety, System; Mine, Needs action, Done), DetailPanel (the item's own detail: the "split drawer") | Pulse case | Pulse | L |
| Messages | Table (business messages, guest questions, group posts), DetailPanel + Notes | Pulse case | inbox | L |
| Stream settings | SettingsForm (routing by department, SLA timers) | — | Pulse settings | L |

### 3. Directory & Places (Get Local 360°)
- **Opened by:** Operations (home), Content, Sales and Service (view).
- **At hand (opened over the work, A3a):** Scan a card (7), Lead (7), Claim invite (7), Promotions (7), GO Codes (5), Report (6).

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| Listings | Table (find, filters, bulk: merge, Nexus, hide), DetailPanel (Fields, Related, Timeline, Files), Form (Add a listing; Intake from a website) | Listing lifecycle | listings, listing-edit, listing-add, listing-intake | L |
| Review | Queue + Pipeline (what the crawler and visitors found), DetailPanel (side by side, Fields) | Listing review | crawler-review, review-item, suggestion, listing-new | L |
| Claims | Pipeline, DetailPanel (evidence Files, Timeline) | Claim | claims | L |
| Nexus | Table, DetailPanel (Related: locations), module widget: Rebuild from website | — | nexus, rebuild | L |
| Identities | Table, DetailPanel (the 5 W's facts) | — | identities | L |
| Places | FilterMenu (Landmarks, Recreation, Map points), Table, Map | Place review | landmarks, recreation, geocode | L |
| Nations | Table, DetailPanel (rules, held places) | — | nations, Safety → Hidden places | L |
| Taxonomy | Table (the six tiers: Continental Hub → Regional/State → Geo-Hub Zone → Micro-Cluster → Anchor → Sub-Group; Level-1 master taxonomy), Map | — | cluster-tools | P |
| Auto-clustering | SettingsForm (20-mile rule), Map, Table (proposed clusters) | Cluster proposal | cluster-tools | P |
| Single Home Rule | Queue (conflicts), DetailPanel | Home conflict | — | C |
| Categories | Table (categories and secondary), Form | — | category tools | L |

### 4. Journeys & Exploration (Let's Explore 360°)
- **Opened by:** Content (home), Operations.
- **At hand (opened over the work, A3a):** Edit listing (3), Place (3), Report (6).

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| Journeys | Pipeline, DetailPanel | Content publishing | journeys | L |
| Experiences | Pipeline, DetailPanel | Content publishing | experiences | L |
| Stories | Pipeline, DetailPanel, Form | Content publishing | stories | L |
| Guides | FilterMenu (Resource guides, How-to guides), Pipeline, DetailPanel | Content publishing | resource-guides, how-guides | L |
| Waypoints & routes | Map, Table, Files (GPX/KML) | Content publishing | — | C |
| Chameleon Filter | Table (editorial picks), SettingsForm | — | — | C |

### 5. Marketplace & Storefronts (Market 360°)
- **Opened by:** Operations (home), Service, Sales (view).
- **At hand (opened over the work, A3a):** Edit listing (3), Order (9), Report (6).

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| GO Codes & short links | Table (every trvgo.us code, scans), DetailPanel, module widget QRDesigner | — | short-codes | L |
| Post-and-Connect | Pipeline (posts, offers), DetailPanel (dual confirmation, trust points) | C2C exchange (AOS §7) | — | C |
| Storefront Studio | Table (storefronts), DetailPanel (theme) | Storefront setup | — | C |
| Custom domains | Table, DetailPanel (DNS checks) | Domain connection | — | C |
| Catalog rules | SettingsForm (pricing rules, the 250-item cap) | — | — | C |
| Gateways | Table (merchant gateways), DetailPanel | Gateway connection | — | C |

### 6. Community & Events (Social 360°)
- **Opened by:** Operations (home), Content, Service.
- **At hand (opened over the work, A3a):** Edit listing (3), Member (9 People), Standing (6).

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| Reports | Pipeline, DetailPanel (the post, Timeline) | Report (3-tier escalation) | reports, Safety → Reports | L |
| Standing | Pipeline (the enforcement ladder), DetailPanel | Standing ladder | Safety → Standing | L |
| Procedures | Table, TemplateEditor | — | Safety → Procedures | L |
| Groups | Table (topic spaces, privacy levels), DetailPanel (charter, members) | Group lifecycle (deactivation 30/90 days) | community | P |
| Business groups | Table, DetailPanel (guardrails, post caps) | — | — | C |
| Events | Calendar, Table, DetailPanel | Event | — | C |

### 7. Sales, Marketing & Customer Growth
- **Opened by:** Sales (home), Service (order processing), Finance (orders, view).
- **At hand (opened over the work, A3a):** Edit listing (3), Nexus (3), Claim (3), New client order and Order (9), Products (9), Plans & subscriptions: start a trial (9), Territory (7), GO Codes (5).

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| Calls today | Queue (follow-ups due, then best to call), DetailPanel (module widget LeadPanel: call log, outcomes) | Lead | Sales → Calls today | L |
| Prospects | Pipeline (the lead stages), FilterMenu (Whose: Everyone's, Mine, Shared queue; territory; web presence), DetailPanel | Lead | Sales → All prospects, New this week | L |
| Scan cards | module widget CardScanner | Lead (creates or joins) | Sales → Scan cards | L |
| Add from the field | Form | Lead | Sales → Add from the field | L |
| Quotes & deals | Pipeline, DetailPanel (lines, terms, Files), StageActions (send, accept → Won) | Quote / deal → opens the White Glove engagement | — | C (step 5) |
| Promotions | Table, DetailPanel, module widget PromoMaker (offers, GO Code, lead mail-out CSV) | Promotion | Sales → Promotions | L |
| Territories | Table, Map, Form | — | Sales → Territories | L |
| Funnel | Chart (funnel), MetricCards | Lead (counts) | Sales → Funnel | L |
| Orders | Back-Office → Orders opened in place, filtered to "started by me" | Client order | — | L |

### 8. White Glove Concierge
- **Opened by:** Service (home; delivers), Sales (sells it and sets it up with the client at close).
- **How it works (Jason, 2026-10-22):** White Glove follows the subscription. When a subscription is created (or a deal
  is Won), an **engagement** opens for the client. The client and their rep decide together which services to do,
  starting from what the plan includes, adding any others. Services are **top-level and flexible**: no fixed must-do
  list. Each service has a light stage (Planned → In progress → Delivered, or Not now), an owner and an optional
  suggested task list that can be ignored. The engagement keeps **opportunities in view**: included benefits not yet
  used, services that fit this business, and upgrades, so nothing is overlooked.
- **At hand (opened over the work, A3a):** Edit listing (3), Order (9), Products (9), Import (9), Lead and Deal (7).

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| Clients | Table (each client with a White Glove engagement: their plan, rep, services, progress, opportunities), DetailPanel (the engagement) | White Glove engagement | — | C |
| Engagement | DetailPanel: the plan's included services (from the subscription's benefits), the services chosen with the client, each with its own stage, owner, due date and notes; **Opportunities** (benefits not yet used, services that fit the business, upgrades); Timeline | White Glove engagement; White Glove service | — | C |
| Service catalog | Table, Form: the top-level services (setup, photography, menu digitizing, content, GO Codes, storefront…), which plans include them, their price outside a plan, and an optional **suggested** task list (never required) | — | — | C |
| Asset studio | Queue, module widget OCR reader (menus, brochures → catalog drafts), DetailPanel (side by side) | Asset digitizing | AI jobs (cards) | P |
| Imports | Back-Office → Import & export opened in place | Import | — | C |

### 9. Back-Office Operations, Logistics & Governance
- **Opened by:** Finance (finance), Service (fulfillment, vendors), Operations (governance).
- **At hand (opened over the work, A3a):** Edit listing (3), Lead (7), Promotions (7), Member (9 People), Report (6).

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| Orders | MetricCards, Pipeline (client orders and site orders), DetailPanel (Stepper, StageActions, ActionBar: Fix, Credit, Refund, Chargeback; Files: proofs; Related: vendor jobs; Timeline), module widgets ClientOrderBuilder, ShippingManifest | Client order; Site order (GO Codes); Order item | E-Commerce → Orders, order, client-order, manifest | L |
| Products | Table, DetailPanel, Form (run sizes, options, kind for promos) | — | products | L |
| Vendor orders | Pipeline, DetailPanel | Vendor job | vendors | L |
| Fulfillment | Pipeline (packs, lots, shipments; multi-channel splitting) | Shipment | client orders' shipping | P |
| Plans & subscriptions | MetricCards (monthly revenue), Pipeline, DetailPanel (charges Timeline, ActionBar: start trial, stop at renewal, end), module widget PlanEditor (tiers, prices, storage, AI credits, packs) | Subscription | E-Commerce → Plans | L |
| Payments | SettingsForm (QuickBooks connection, sandbox/production), Table (the payment log) | — | quickbooks | L |
| Reconciliation & tax | MetricCards, Table (by month: sales, tax, refunds, disputes), export | — | E-Commerce → Reports | P |
| Inventory & COGS | Table (inventory ledger), MetricCards | — | — | C |
| Import & export | Pipeline (staging, preview, publish), Table (export packages) | Import (AOS §11.1) | auto-imports | P |
| Consent hub | Table (members' consents), DetailPanel | — | consent records | C |
| DMCA queue | Pipeline, DetailPanel | DMCA take-down | — | C |
| Verification vault | Table (documents, 72-hour purge), DetailPanel | — | claim documents | P |
| People | FilterMenu (Members, Staff, Levels), Table, DetailPanel (staff profile; level, department, assistant; logins) | Staff onboarding | people, staff, staff-levels, staff-profile | L |

### 10. Insights & Analytical Research
- **Opened by:** Executives, Operations, Content, Engineering (telemetry).
- **At hand (opened over the work, A3a):** Edit listing (3), Review item (3), Search workbench item (10).

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| Insights | MetricCards, Chart | — | insights | L |
| Search workbench | module widget SearchWorkbench, FilterMenu (Missed, Learning, Oversight, Demand, Index) | — | search and the five search pages | L |
| 5 W's lexicon | Table, DetailPanel | — | lexicon tools | P |
| Crawler & data | FilterMenu (Overview, Published, Sources, Data browser), Table, DetailPanel | — | crawler, auto-imports, sources, data-crawler, data | L |
| Emergency resources | Map, Table (shelters, hotlines, closures) | — | shelters and hotlines on listings | P |
| Telemetry | MetricCards, Chart (errors, jobs, AI spend, file count against the ceiling) | — | parts in Settings | P |

### 11. Global System Configuration
- **Opened by:** Founder, Engineering (Director), Executives (view).
- **At hand (opened over the work, A3a):** Any section's settings, opened as a drawer from that section (Directors).

| Section | Widgets | Workflows | Built from | St |
|---|---|---|---|---|
| **Workflows** | Table (every workflow, items per stage), **WorkflowBuilder** | — | — | new |
| Permissions | PermissionMatrix (levels × departments × actions, per module) | — | staff levels | P |
| Templates | TemplateEditor (emails and notices) | — | in code today | C |
| Settings | SettingsForm (how it's set up) | — | settings | L |
| Integrations & AI | SettingsForm (Google, QuickBooks, Claude, AWS, Turnstile, sign-in providers; AI spend and caps) | — | Settings rows | P |
| Notifications | SettingsForm (SMTP, Twilio SMS, push) | — | email only | P |
| Branding & SEO | SettingsForm (logo, colours; permalink rules, schema builders) | — | — | C |
| Storage & backups | SettingsForm (S3, backups, restore drill) | — | S3 check | P |
| Logs | Table (the action log, workflow history, errors) | — | logs | L |
| Site map | the build's checks | — | sitemap | L |

---

## Part C. The built-in workflows (defaults, editable in the builder)

Each starts from how the site works today.

| Workflow | Module | Stages (default) | Notable checks and what-happens |
|---|---|---|---|
| Listing lifecycle | 3 | Draft → Live → Hidden → Removed | evidence needed to go live |
| Listing review | 3 | Found → Reviewing → Created / Merged / Rejected | duplicates check; creates the listing |
| Claim | 3 | Requested → Email confirmed → Verified → Active; On hold; Disputed | verification evidence; starts the 30-day trial; tells Sales |
| Place review | 3 | Found → Reviewed → Shown / Held | Nations rules (held never shown) |
| Content publishing | 4 | Draft → In review → Published → Retired | Editor approves; Creator drafts |
| C2C exchange | 5 | Posted → Offer → Both confirmed → Done | dual confirmation; trust points |
| Report | 6 | New → Tier 1 → Tier 2 → Tier 3 → Resolved | removes or restores the post; standing step |
| Standing ladder | 6 | Good → Notice → Limited → Suspended → Ended | notices, review requests |
| Lead | 7 | New → Contacted → Met → Interested → Meeting → Invited → Quote → Won / Lost / Not now / Closed | next follow-up; tells the rep; a promo scan moves to Interested |
| Quote / deal | 7 | Draft → Sent → Accepted → Won / Lost | **Won opens the White Glove engagement**, the subscription and the order |
| Promotion | 7 | Draft → Active → Paused → Ended | profit floor; issues the GO Code |
| White Glove engagement | 8 | Opened → Planning with client → In service → Review → Ongoing / Closed | opened by a new subscription or a Won deal; reads the plan's benefits; lists opportunities |
| White Glove service | 8 | Planned → In progress → Delivered; Not now | per service; optional suggested tasks, never required; tells the client when delivered |
| Asset digitizing | 8 | Uploaded → Read → Checked → In the catalog | AI read; a person checks |
| Client order | 9 | Draft → With client → Approved → Paid → In production → Shipped / Ready → Done; Cancelled | proofs approved; billing address; makes the invoice; charges; issues GO Codes; sends to vendors. Any stage: Fix, Credit, Refund, Chargeback |
| Site order (GO Codes) | 9 | In cart → Pending payment → Paid → Issued; Void | releases the promo on void |
| Order item | 9 | Waiting → Printing → Sent → Delivered | tracking number |
| Vendor job | 9 | Draft → Sent → In production → Shipped → Received | — |
| Shipment | 9 | Packing → Shipped → Delivered | manifest, label |
| Subscription | 9 | Trial → Active → Past due → Ended | renewals, retries (+3, +7 days), reminders, plan changes |
| Import | 9 | Staged → Previewed → Published / Discarded | confidence gate |
| DMCA take-down | 9 | Received → Reviewing → Removed / Rejected → Counter-notice | timers |
| Staff onboarding | 9 | Invited → Set up → Active → Ended | revokes grants and tickets on End |
| Pulse case | 2 | Open → Assigned → Escalated → Done | SLA timers, routing |

---

## Part D. Building it (full scope)

1. **Engine and shell:**
   - the router at `/admin/<module>/<section>[/<id>]` and the one access check;
   - the module manifests for all 11 modules;
   - the shell (☰, section dropdown, crumbs, detail panel, drawer stack and quick-task panel, landing pages for coming sections);
   - the endpoint contract.
2. **The widget library:** Table, Pipeline, FilterMenu, SearchBox, DetailPanel, Stepper,
   StageActions, ActionBar, Fields, Form, Timeline, Notes, Files, Assignee, Related, MetricCards, Chart, Queue, Stream,
   Map, SettingsForm, PermissionMatrix, TemplateEditor.
3. **The workflow engine and builder:**
   - the tables;
   - the registry of checks and what-happens, wrapping today's libraries;
   - the builder (System Configuration → Workflows);
   - the built-in workflows seeded from Part C, with today's items placed in their current stages.
4. **Every live section rebuilt from widgets** (Part B), module by module, with all 11 in this build. Module widgets
   are wrapped from today's code. Partial sections are completed where the gap is small; Coming sections are listed.
5. **Old area pages and endpoints deleted;** admin endpoints folded into `/admin/api/<module>/`.
6. **`CLAUDE.md` rule, site map checks, tests:** every section at 390px and on a desktop; every workflow moved
   end to end.

Step 5 of Sales & Growth (Quotes → Won → onboarding) is built as part of this, as the Quote/deal and White Glove
workflows.

## Part E. Decided (Jason, 2026-10-22)

1. **White Glove** follows the subscription: an engagement per client, services chosen by the client and the rep from
   the plan's benefits and beyond, light stages, suggested (never required) tasks, opportunities kept in view.
2. **Workflows are built by Managers and up.**
3. **Publishing a changed workflow:** the publisher chooses whether items in progress move to the new version.
4. **Tables everywhere;** kanban only if a table proves not enough.
5. **The merchant side after the platform admin,** on the same engine.
6. **Coming sections greyed out** in the menus, each with a landing page explaining what's coming. Actions you may
   not take are **greyed out with the reason**.
