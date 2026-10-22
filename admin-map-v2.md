# Admin map v2: one structure, one way in (proposal)

Status: **Superseded** by `admin-architecture.md` (2026-10-22). Kept for the inventory of today's pages. Replaces how the admin is put together, not what it does. It
builds on `workspace-map.md` (the 11 workspaces, AOS §8). Nothing gets built until this is approved.

Decided already (Jason, 2026-10-22): **start fresh**. Old admin addresses are retired, with no redirects.

---

## Why it feels random today

- **Two navigation systems on top of each other.**
  - The original area pages are E-Commerce, Sales, Safety, Search, People, Settings, Listings and Data & crawler.
    Each has its own tabs.
  - The 11 workspaces were added later, as menus that mostly link back into those tabs. You move between two
    systems without knowing which one you're in.
- **New tools land wherever is convenient.** Subscriptions became a tab in E-Commerce, while Back-Office still said
  "Plans & entitlements: coming". Promotions became a tab in Sales, while Sales & Growth still said "Regional
  promos: coming".
- **59 admin pages, 28 of them in no menu.** About half are detail pages (an order, a listing edit). The rest are
  reachable only by knowing the address: five search pages, the data crawler, intake, rebuild and others.
- **91 menu items, 36 of them partial or "coming"**, so many taps open a placeholder.
- **One thing, several names:** Subscriptions, Plans, Plans & entitlements.

---

## The rules

These go into `CLAUDE.md` as a standing rule for every build.

1. **Three levels, no more.** Workspace → Section → (Tabs inside a section, only when a section truly has parts).
   Everything else is a **detail**: one order, one listing, one staff member. A detail opens from its section, in
   the side panel on a desktop or full screen with ← on a phone, and its crumbs lead back.
2. **One address pattern:** `/admin/<workspace>/<section>` (for example `/admin/backoffice/orders`), and
   `/admin/backoffice/orders/123` for a detail. The old area pages (`ecommerce.php`, `sales.php`, `safety.php`,
   `search.php`, `people.php`, `settings.php`, `listings.php`, `data-crawler.php`, `adminportal.php`) are deleted.
   Their tabs become sections.
3. **One home per tool.** A tool exists in exactly one section. Where another workspace needs it, that workspace has
   a **shortcut** that opens the home section, already filtered (for example Sales → "Orders I started"). No copies.
   The Pulse drawer is the one exception: it opens any item's detail beside the stream.
4. **One name per tool**, the same in the menu, the page title, the crumbs and the docs.
5. **Only built things are in the menus.** Live and partial sections appear. Coming ones are listed together in one
   **"Coming here"** note at the foot of each workspace, so nothing you tap is a dead end.
6. **One guard.** The router checks your workspace access once, for every section, detail and endpoint. Pages don't
   carry their own checks.
7. **Endpoints follow the map.** Admin endpoints live at `/admin/api/<workspace>/<tool>.php`; the current
   `/api/admin/*` and `/admin/api/*` merge there. Public and member endpoints stay where they are.
8. **The check before every delivery:** the site map (`/admin/config/sitemap`) must show nothing "Not in the map"
   and nothing "file missing".

---

## The map

**Built from** lists today's files and tabs, so nothing is lost. **Details** are opened from the section, not from a
menu.

### 1. Dashboard
| Section | What it is | Built from | Status |
|---|---|---|---|
| Today | your day, the task queue, quick actions (each in a side panel), business numbers | `index.php`, `w.php` (board) | Live |
| Helper | what needs a person, one item at a time | `helper.php` | Live |

### 2. Pulse
| Section | What it is | Built from | Status |
|---|---|---|---|
| Stream | everything needing an answer or action; the drawer opens any item's detail | Pulse board | Live |
| Messages | business messages, guest questions, group posts | inbox | Live |
| Stream settings | routing, SLA timers | Pulse settings | Live |

### 3. Directory & Places
| Section | What it is | Built from | Status |
|---|---|---|---|
| Listings | find, edit, bulk actions. **Details:** a listing; Add a listing; Intake from a website | `listings.php`, `listing-edit.php`, `listing-add.php`, `listing-intake.php` | Live |
| Review | what the crawler and visitors sent. **Details:** a found item; a suggestion; create from a suggestion | `crawler-review.php`, `review-item.php`, `suggestion.php`, `listing-new.php` | Live |
| Claims | claims, holds, owner changes, disputes | `claims.php` | Live |
| Nexus | organizations with many locations. **Detail:** Rebuild from its website | `nexus.php`, `rebuild.php` | Live |
| Identities | each listing's facts by the 5 W's | `identities.php` | Live |
| Places | tabs: Landmarks, Recreation, Map points | `landmarks.php`, `recreation.php`, `geocode.php` | Live |
| Nations | nations, their rules, held places | `nations.php`, Safety → Hidden places | Live |
| Taxonomy & clusters | regions, clusters, coverage | `cluster-tools.php` | Partial |

### 4. Explore
| Section | What it is | Built from | Status |
|---|---|---|---|
| Journeys | journey moderation and features | `journeys.php` | Live |
| Experiences | experience review | `experiences.php` | Live |
| Stories | guide stories for place pages | `stories.php` | Live |
| Guides | tabs: Resource guides, How-to guides | Search → Resource guides, How-to guides | Live |

### 5. Market
| Section | What it is | Built from | Status |
|---|---|---|---|
| GO Codes & short links | every trvgo.us code, scans, switch off | Safety → Short codes (`short-codes.php`) | Live |

### 6. Social
| Section | What it is | Built from | Status |
|---|---|---|---|
| Reports | reports of posts, groups and listings | `reports.php`, Safety → Reports | Live |
| Standing | the enforcement ladder, trust flags, reviews | Safety → Standing | Live |
| Procedures | how we handle each kind of report | Safety → Procedures | Live |

### 7. Sales & Growth
| Section | What it is | Built from | Status |
|---|---|---|---|
| Calls today | follow-ups due, then the best to call | Sales → Calls today | Live |
| Prospects | every prospect, with filters (New this week becomes a filter). **Detail:** the lead panel | Sales → All prospects, New this week | Live |
| Scan cards | business card scanner | Sales → Scan cards | Live |
| Add from the field | a business seen in person | Sales → Add from the field | Live |
| Promotions | codes, GO Code offers, lead mail-outs | Sales → Promotions | Live |
| Territories | who covers where | Sales → Territories | Live |
| Funnel | the numbers from new to won | Sales → Funnel | Live |
| *Shortcuts* | Orders I started, New client order, Products (to quote from) | open Back-Office | — |

### 8. White Glove
Nothing built yet. The workspace shows its "Coming here" list (onboarding board, asset studio, verification vault)
until step 5 and the studio.

### 9. Back-Office
| Section | What it is | Built from | Status |
|---|---|---|---|
| Orders | every order, what needs a person first. **Details:** an order; a client order (builder); its shipping papers | E-Commerce → Orders, `order.php`, `client-order.php`, `manifest.php` | Live |
| Products | the catalog | `products.php` | Live |
| Vendor orders | jobs sent to printers and makers | `vendors.php` | Live |
| Plans & subscriptions | every plan, trials, declines, revenue | E-Commerce → Plans | Live |
| Payments | QuickBooks connection, sandbox/production, log | `quickbooks.php` | Live |
| Money reports | sales, tax, refunds, disputes by month | E-Commerce → Reports | Partial |
| People | tabs: Members, Staff & roles, Staff levels. **Detail:** a staff profile | `people.php`, `staff.php`, `staff-levels.php`, `staff-profile.php` | Live |

### 10. Insights
| Section | What it is | Built from | Status |
|---|---|---|---|
| Insights | how the platform is used | `insights.php` | Live |
| Search | tabs: Workbench & missed, Learning, Oversight, Demand, Index | `search.php` and the five `search-*.php` | Live |
| Crawler | tabs: Overview, Published (auto-imports), Sources | `crawler.php`, `auto-imports.php`, `sources.php`, `data-crawler.php` | Live |
| Data browser | find and edit any table | `data.php` | Live |

### 11. System Configuration
| Section | What it is | Built from | Status |
|---|---|---|---|
| Settings | how the site is set up; services and keys | `settings.php` | Live (read-only) |
| Logs | the action log and the others | `logs.php` | Live |
| Site map | every section, detail and endpoint, with the checks | `sitemap.php` | Live |

### Outside the map (not admin screens)
- `login.php` (staff sign-in) and `setup.php` (setting up a staff login from its link) stay standalone pages.
- `promotions.php` becomes an endpoint (`/admin/api/growth/promotions.php`).

### Deleted
- `adminportal.php` (an old redirect).
- The old area pages: `ecommerce.php`, `sales.php`, `safety.php`, `search.php`, `people.php`, `settings.php` (as a
  hub), `listings.php` (as a hub), `data-crawler.php`.
- `w.php?w=…`, replaced by `/admin/<workspace>`.

---

## Decisions for Jason

1. **Addresses:** `/admin/backoffice/orders` (clean, recommended) or `/admin/w.php?w=backoffice&s=orders`?
2. **Shortcuts, not copies** (rule 3): agreed?
3. **Coming items out of the menus** and into one "Coming here" note per workspace (rule 5): agreed?
4. **Placements to confirm:**
   - **Guides** (resource and how-to) move from Search to **Explore**.
   - **Hidden places** move from Safety to **Nations**.
   - **GO Codes & short links** move from Safety to **Market**.
   - **Intake from a website** becomes part of **Listings**.
   - **Logs** go to **System Configuration**.
5. **Phone menus:** the ☰ list shows the 11 workspaces. Inside a workspace, its sections are a **Shortcuts-style
   dropdown** (the inline-filter rule), not a row of tabs. Agreed?

## Build plan, after approval

1. **The frame:** the router at `/admin/<workspace>/<section>` with the one guard, the section dropdown, the detail
   panel, crumbs and the "Coming here" note.
2. **Move the tools in, one workspace at a time,** starting with Back-Office and Sales & Growth (the most tangled).
   Each tool's content moves as it is; only its frame changes.
3. **Delete the old area pages,** merge the admin endpoints under `/admin/api/<workspace>/`, and update the site map
   checks.
4. **Write the rule into `CLAUDE.md`**, then test every workspace at 390px and on a desktop.

Step 5 (Quotes → Won → onboarding) waits until this is done.
