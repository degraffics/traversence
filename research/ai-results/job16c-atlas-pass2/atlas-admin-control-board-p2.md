# Platform atlas: admin-control-board

*Draft for human review. Path: `research/platform-atlas/admin-control-board.md`. This entry uses only the attached files. The code is a partial slice of the sync mirror (`traverse-3way-sync/…`), and each file is capped. Shared libraries such as `admin-hub.php`, `router.php`, `Auth.php`, `StaffLogin.php`, `Workspaces.php`, `Pulse.php` and `Standing.php` are named in the code but their source is **not shown**. Claims that rest on them are marked.*

---

## (a) What each kind of person can do here, page by page

The admin is a staff-only area. Residents, travelers, members and businesses do not use it directly. They meet it at the edges, where their actions arrive for a person to decide and where staff decisions come back to them.

**Resident or traveler (guest or member)**
- **A report they file** (posts, groups, comments, journeys, experiences, listing messages) lands in `/admin/reports.php`.
  - Staff can Remove, Restore or Dismiss it (`reports.php:59-65`).
  - A post reported by 3 people is hidden until a decision.
  - Reporters' names are never shown to the person reported (`reports.php:40`).
- **A "something missing" suggestion** becomes a live, unclaimed listing through `/admin/listing-new.php?suggestion=N`.
  - A signed-in sender is emailed when it goes live (`listing-new.php:2-5, 31`).
- **Their searches** feed `/admin/search-demand.php` as counts only, "never who" (`search-demand.php:63`).
  - These counts decide where the crawler looks next (`search-demand.php:27-31`).
- **What travelers see is shaped by staff gates:**
  - Hidden Recreation.gov places, such as sacred, restricted or closed sites (`recreation.php:2-3, 36`).
  - A tribal nation's visitor rules, shown only after a staff member ticks "Checked against the nation's own site" (`nations.php:5-6, 106`).
  - Place stories, published only after a checklist with at least one source (`stories.php:2-3, 63-68`).
  - Experiences, which post themselves only above a credibility score; otherwise they wait for an Editor (`experiences.php:2-8, 79`).

**Member**
- **Standing (notice of action):**
  - A warning gives the member 24 h to confirm or ask for review. Next comes a posting and messaging stop, then revocation with a review link.
  - Asking for a review stops the clock (`safety.php:62`). Staff Lift or Uphold the notice (`safety.php:57, 69-78`; `admin/api/people.php:71-75`).
- **Privacy protections when staff look at an account:**
  - Staff cannot browse accounts. A search needs ≥3 characters or a #number, or an exact email (`people.php:109-116`).
  - Opening a card requires a reason, and the opening is logged (`people.php:60-64, 101-102`).
  - Seeing an email or phone is a separate, logged "official contact" reveal (`people.php:96-99`).
- **Short codes:** a member's trvgo.us code can be switched off for misuse. The owner sees why, and the code is kept, never deleted (`short-codes.php:2-4, 51-52, 65`).
- **Staff who are also members:** an owner asks to link the staff login and the member confirms (decisions/0100, addendum 2026-10-23). The member can opt in to a "Traversence team" badge on their public profile (`staff-profile.php:104`).

**Business**
- **Claims** are decided in `/admin/claims.php`. That file is not shown; its behaviour comes from decisions/0016 and `pages.md` §5.
- **Listings staff add for a business:**
  - Staff can add a business that goes live unclaimed. The crawler is then queued to find its phone, website, email and hours (`listing-add.php:2-6, 29-33`).
  - Staff can link a business's locations into a Nexus (`admin/api/nexus.php:2-7`) or rebuild them from its website (`admin/api/rebuild.php:2-6`).
- **Sales and promotions:**
  - Staff log calls and visits and email the claim link. Sending that email needs `BUSINESS_POSTAL_ADDRESS` (`admin/api/sales.php:5-6`).
  - Staff issue promotion GO Codes, including one code per lead for a mail-out (`promotions.php:2-4, 43-48`).
- **Vendors** (print shops and the like) are emailed a link to their work orders. A new link stops the old one (`admin/api/vendors.php:4, 19-26`).

**Staff (the actual users)**

| Route | What it does |
|---|---|
| `/admin/login.php` | Staff ID or work email, password, then an emailed 6-digit code (or authenticator); "Can't get the email?" opens an owners' case (`login.php:2-7, 43-47`) |
| `/admin/setup.php?t=` | One-time setup: work-email code and password ≥12 characters (`setup.php:2-4, 83-87`) |
| `/admin/` | Dashboard module via `router.php` (`index.php:5-6`); `adminportal.php` now 301s here (`adminportal.php:4`) |
| `/admin/w.php?w=` | One of 11 workspaces; tools as tabs, filtered by role (`w.php:2-5, 19-31`) |
| `/admin/search.php` | Search area: 7 embedded tools (`search.php:15-23`) |
| `/admin/safety.php` | Reports, Standing, Procedures, Hidden places, Short codes (`safety.php:82-88`) |
| `/admin/data-crawler.php` | Crawler pages plus the Data browser (`data-crawler.php:14-20`) |
| `/admin/auto-imports.php` | Listings auto-published by the crawler; Pull or Put back (`auto-imports.php:2-6, 33-35`) |
| `/admin/listing-add.php`, `/admin/listing-new.php` | Add a listing; create one from a suggestion |
| `/admin/geocode.php` | Census geocoding, 500 at a time (`geocode.php:2-5`) |
| `/admin/search-index.php`, `/admin/search-demand.php` | Build the 5W index; demand funnel |
| `/admin/stories.php`, `/admin/experiences.php`, `/admin/nations.php`, `/admin/recreation.php` | Editorial and safety gates (above) |
| `/admin/reports.php`, `/admin/short-codes.php` | Moderation |
| `/admin/staff-levels.php`, `/admin/staff-profile.php` | Levels, departments, profiles (`staff-levels.php:2-5`) |
| `/admin/logs.php` | Action log (`audit_log`) and staff sign-ins (`logs.php:2-3, 15-21`) |
| `/admin/products.php`, `/admin/promotions.php`, `/admin/manifest.php` | Our own catalog, promotions, shipping papers |

---

## (b) How it works underneath

- **Shell and navigation.** Area pages call `tv_admin_require`, `tv_admin_start`, `tv_admin_hub` and `tv_admin_end` from `app/ui/admin-hub.php` (e.g. `search.php:7-24`). That file is not shown.
  - Each area's tools are embedded tool pages keyed by section, e.g. `'workbench' => […'/admin/search-misses.php']` (`search.php:16`).
  - Tab badges come from `tv_admin_counts()` (`search.php:12`; `data-crawler.php:12`).
- **Three generations of layout coexist in the code:**
  1. decisions/0078's eight areas (`search.php`, `safety.php`, `data-crawler.php`);
  2. decisions/0104's 11 workspaces (`w.php:2-5`);
  3. decisions/0109's module router (`index.php:2`; `promotions.php:14` redirects to `/admin/growth/promotions`).
- **Two page shells.** Older tools use `tv_crawler_start` and `tv_crawler_require_admin` from `app/ui/admin-crawler.php` (e.g. `recreation.php:8-12`). Some use the public `tv_page_start` (`reports.php:30`; `experiences.php:56`).
- **Access checks vary by file:**
  - `empty($me['is_admin'])`: `move-files.php:14`; `env-setting.php:17`.
  - `IntakeStager::canUseIntake`: `costs.php:12`; `vendors.php:14`; `pulse.php:34`; `rebuild.php:16`.
  - `canUseIntake` **or** any `admin_access` row with `tier = 'regional_admin'`: `nexus.php:18-19`; `category-add.php:14-15`; `find-listing.php:12-13`; `listings-bulk.php:17-18`; `sales.php:27-28`.
  - Role context `Roles::can($ctx,'people')`: `people.php:27-29`.
  - `Access::can('config.env', ['min'=>'executive'])`: `env-setting.php:18`.
  - `Access::can('config.communications…')`: `comms.php:19-20`.
  - Workspace and area gating: `Workspaces::opens` and `allowsArea` (`w.php:19, 31`).
  - The bootstrap staff gate described in decisions/0100 ("`app/bootstrap.php` sends every /admin page to `/admin/login.php`") is **not shown**.
- **Writes.** POSTs check a session CSRF token: either a form field (`recreation.php:19`) or an `X-CSRF-Token` header (`pulse.php:36`).
  - Exception: `vault-ext.php` has no cookie session. It instead checks `Origin` against a Chrome extension ID, with a hard-coded default if `VAULT_EXTENSION_ID` is unset (`vault-ext.php:13-16`), plus an `X-Vault-Token` header (`:23`).
- **Data separation (decisions/0110).**
  - Admin tables are named through `Database::t()` (e.g. `logs.php:16`; `people.php:34`).
  - `AdminTables::make` creates admin tables in the admin database when the main login can reach it, and records each one in `data_moves` (`AdminTables.php:19-31`).
- **Audit trails:**
  - `Audit::log` on `.env` changes (`env-setting.php:36-37`);
  - `Roles::log` on member actions (`people.php:73, 81, 85`);
  - `Standing::logAccess` on opening an account (`people.php:98, 102`);
  - `StaffLogin::log` on profile edits (`staff-profile.php:32`);
  - `CommsSetup::log` (`comms.php:70`).
- **Control Board (decisions/0171, 0176).**
  - `ControlLog` is a logbook. It writes alarms, acks, controls, stops and starts, maintenance and notes to `control_log` (`ControlLog.php:17-29`). It merges in automation switches, Start sessions, pre-flight runs, service issues, failed jobs and releases (`:66-91`).
  - "Who" is a staff name or "the system", never a member (`:8`).
  - `ControlConsistency` is a read-only daily check, read by `/api/health.php` (not shown). It confirms each alarm's title, status, plain names, Fix-it button and Incident Report agree across the board, Pulse and module cards (`ControlConsistency.php:2-13, 63-116`).
  - The board page itself (`app/admin/control-console.php`, required at `:31`) is **not shown**.
- **Staging publish (decisions/0165).**
  - `StagingMerge::row` is a three-way merge (base, staging, live) that yields keep, update, insert, delete or conflict (`StagingMerge.php:7-14, 33-73`).
  - `StagingIds` rewrites id references in columns, JSON, `kind:id` strings, links and store keys. It stops if it meets an unknown id-like column (`StagingIds.php:2-11`).
- **Pulse.** `/admin/api/pulse.php` handles cases, assignments, posts, vendor jobs, the 4OVER paste import, the daily report, tasks and mailbox actions (`pulse.php:2-24`). The `Pulse` library is not shown.

---

## (c) Decisions

| # | Rules |
|---|---|
| **0016** (amended by 0070) | `admin/claims.php`: entry via `hasAnyAdminAccess`; list scoped to the admin's hub; plain FIFO; three row types with different actions. A fraud flag opens a dispute rather than a one-click takedown. Manual "called listed number" verification; one decide endpoint that re-checks hub access server-side. |
| **0029** | Admin uses the shared member session; `admin/login.php` has three cases; `currentUser()` must also drop suspended users. **Superseded in practice by 0100** (separate staff logins). |
| **0078** (changed by 0079) | `/admin/` as a dashboard; flat menu of areas, each one page with tools as tabbed sections; People & roles, Settings (read-only at the time); `adminportal.php` 301s. 0079 (cited in `people.php:2-6`, text not shown): no browsing, a reason to open an account, notices instead of Suspend. |
| **0100** | Staff login separate from member accounts: TRV-#### ID, password, emailed code to `first.last@trvgo.com`, optional authenticator; owner or manager reset; recovery codes removed; roles open areas; owner-initiated member link confirmed by the member; "Can't get the email?" goes to an owners' case (Release 3f). |
| **0110** (amended by 0164) | Three realms and four databases; separate DB logins, tenant views and triggers, per-realm gateways, logged support access. Admin tables move to `trv_admin` through `Database::t()` with Undo. A 2026-11-27 note says the `MarketCheckout::writeOrder` scoped-tenant exception is **not met yet** in live code. |

Decisions cited in the code whose text is **not shown**: 0043, 0044, 0046, 0047, 0048, 0053, 0054, 0058, 0059, 0061, 0066, 0071, 0073, 0074, 0081, 0083, 0094, 0101, 0104, 0105, 0108, 0109, 0123, 0124, 0125, 0127, 0133, 0150, 0156, 0164, 0165, 0167, 0171, 0172, 0176.

---

## (d) Live vs coming

**Live per `pages.md` §5 and present in code:** reports, recreation, stories, experiences, nations, search-index, search-demand, auto-imports, geocode, plus the "working tool" rows (`listing-add`, `listing-new`, `manifest`, `products`, `promotions`, `safety`, `search`, `setup`, `short-codes`, `staff-levels`, `staff-profile`).

**Coming or partial, stated in code or docs:**
- `w.php` draws unbuilt sections as "Coming" or "In part" cards (`w.php:41-45`).
- `/admin/api/comms.php` is listed "Coming (release comms-all)" in `pages.md` §6, yet the file is in the sync mirror. Whether it is deployed cannot be determined here.
- decisions/0100 "Not yet": finer staff roles; staff notices in the member Pulse. Later addenda partly address both.
- Several tools show "Run migration … first" until set up (e.g. `stories.php:39-41`; `sales.php:30`).

**Doc–code mismatches to resolve:**
- **`adminportal.php`.** `pages.md` §5 calls it "Live, placeholder", but the code is a 301 redirect (`adminportal.php:4`).
- **`/admin/login.php`.** `pages.md` §8 lists it under Coming ("Admin pages use the normal sign-in"), but it exists as the staff sign-in (`login.php`; decisions/0100).
- **`/admin/listings.php`:**
  - `pages.md` §8 lists it as Coming, "covered by crawler-review".
  - decisions/0078 names it the Listings & places area page.
  - `safety.php:17` links to `/admin/listings.php#review|#claims|#published`.
  - The file is **not shown**, so whether those links work cannot be determined.
- **Settings.** decisions/0078 said changing settings was "coming". `env-setting.php` now writes whitelisted settings to `.env` (decisions/0125, text not shown).

---

## (e) Charter promises, economics, gaps

**Immersion and connection** (Charter, "Mission & Purpose": "connect communities").
- The admin is where the community's signals are acted on: reports, suggestions that become listings, and Search demand that drives the crawler toward towns people actually searched (`search-demand.php:2-5`).
- Stories carry the narrative layer under a checklist that requires sources (`stories.php:63-68`).
- Gap: no metric of connection outcomes is shown.

**True locality and context without noise** (Charter, "Why This Matters"; "Single Home Rule").
- Nations: only nation-listed places are shown, and rules appear only once checked against the nation's own site (`nations.php:82, 107`).
- Sacred or closed recreation sites can be hidden permanently (`recreation.php:36`).
-