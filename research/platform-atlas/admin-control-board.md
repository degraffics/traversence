# research/platform-atlas/admin-control-board.md

*Draft for human review. Sources: the attached charter, brand, PRD, `site-audit/pages.md`, ADRs 0016, 0029, 0078, 0100, 0110, and the code excerpts. Code paths are written as they appear in the sync mirror: `admin/…` stands for `traverse-3way-sync/sync-storage/admin/…` and `lib/…` stands for `sync-app/lib/…`. A file being in the mirror does not show it is deployed.*

**Partial view.** This entry covers the admin side: its dashboard, its tools, the staff sign-in, and the System Control Board's log and consistency check. The following files are referenced but **not shown**:

- `admin/router.php`
- `app/ui/admin-hub.php` and `admin-dashboard.php`
- `app/ui/admin-crawler.php`
- `app/admin/Access.php` and `control-console.php`
- `lib/Workspaces.php`, `StaffLogin.php`, `Pulse.php`, `Standing.php` and `Auth.php`
- `app/bootstrap.php`
- `admin/claims.php`, `listings.php`, `insights.php` and `people.php`

The board console itself is also not shown. Its page and route are not in `pages.md`, so this entry does not name one.

---

## (a) What each audience can do here

The admin side is staff-only. Residents, travelers, members and businesses never open these pages. What they get is the result of staff work. The tables below list the staff page and the effect each audience sees.

### Staff entry and shell

| Route | What it does |
|---|---|
| `/admin/login.php` | Staff sign-in. A staff ID or work email plus a password, then a 6-digit code sent to the work email (an authenticator app code also works). Members are sent to `/login.php` (`admin/login.php:2-4, 87-92`). **Can't get the email?** files an owners' case (`:43-47, 108-119`). |
| `/admin/setup.php?t=` | One-time setup link, valid 72 hours. The person sets a password and confirms the emailed code (`admin/setup.php:2-4, 79-89`). |
| `/admin/` | The dashboard module, handed to the router (`admin/index.php:5-6`). |
| `/admin/w.php?w=` | One of 11 workspaces. Its tools appear as tabs, filtered by the person's role (`admin/w.php:2-5, 19-23, 31`). |
| `/admin/adminportal.php` | A 301 redirect to `/admin/` (`admin/adminportal.php:4`). |

**Discrepancies to resolve:**

- `pages.md` §5 still lists `adminportal.php` as "Live, placeholder", but the code is a redirect.
- `pages.md` §8 lists `/admin/login.php` as "Coming… Admin pages use the normal sign-in", but the file exists and implements the separate staff login from 0100.

### Effects for residents (Get Local)

| Staff page | Effect for residents |
|---|---|
| `/admin/listing-add.php` | A listing goes live unclaimed. The crawler then looks up its phone, website, email and hours (`admin/listing-add.php:2-6, 29-31`). |
| `/admin/listing-new.php?suggestion=` | A visitor's "something missing" suggestion becomes a live listing. A signed-in sender is emailed (`admin/listing-new.php:2-5, 30-31`). |
| `/admin/geocode.php` | Replaces ZIP-centroid map points with Census geocoder points (`admin/geocode.php:27`). |
| `/admin/search-index.php` | Builds the 5W search index (`admin/search-index.php:34, 42-43`). |
| `/admin/auto-imports.php` | **Pull from site** or **Put back** for listings the crawler published on its own (`admin/auto-imports.php:33-35`). |

### Effects for travelers (Let's Explore)

| Staff page | Effect for travelers |
|---|---|
| `/admin/stories.php?place=` | Place stories. Publishing requires the checklist and at least one source (`admin/stories.php:2-3, 63-68`). |
| `/admin/experiences.php` | Approves, rejects or hides experiences. Approving needs an Editor (`admin/experiences.php:2-8, 96-104`). |
| `/admin/nations.php` | A nation's visitor rules show only after someone ticks "Checked against the nation's own site" (`admin/nations.php:2-6, 106`). |
| `/admin/recreation.php` | Hides sacred, restricted or closed Recreation.gov places. Hidden places stay hidden across monthly reloads (`admin/recreation.php:2-3, 36`). |

### Effects for members

| Staff page or endpoint | Effect for members |
|---|---|
| `/admin/reports.php` | Remove, restore or dismiss reported posts, groups, comments and messages. A post reported by 3 people is hidden until a person decides. Reporters' names are never shown to the person reported (`admin/reports.php:40, 61-63`). |
| Safety → Standing (`admin/safety.php:47-80`) | The notice ladder: a warning, then 24 hours, then a stop on posting and messaging, then revoked. Asking for a review stops the clock (`:62`). Staff decide with **Lift** or **Uphold** (`:57, 75`). |
| `/admin/api/people.php` | A member is found only by search and opened only with a recorded reason. Contact details are a separate recorded reveal (`admin/api/people.php:2-6, 92-103`). |
| `/admin/short-codes.php` | Switches off a misused trvgo.us code. The owner sees the reason (`admin/short-codes.php:2-4, 65`). |

### Effects for businesses

| Staff page or endpoint | Effect for businesses |
|---|---|
| Claims (`/admin/claims.php`) | **Not shown.** Per `pages.md` §5 and ADR 0016, it handles claims to confirm, holds, owner changes and disputes. |
| `/admin/api/nexus.php`, `/admin/api/listings-bulk.php` | Link listings into a Nexus ("it stays a listing"), merge listings, with Undo (`admin/api/nexus.php:2-7`; `listings-bulk.php:2-8, 41-46`). |
| `/admin/api/rebuild.php` | "Rebuild from its website": reads the site, plans changes, applies what staff accept, with Undo (`admin/api/rebuild.php:2-7`). |
| `/admin/api/sales.php`, `/admin/api/cards.php` | Lead workspace, a call/visit log, and an emailed claim-link invite (`admin/api/sales.php:2-14, 42-43`). A business-card scanner (`admin/api/cards.php`). |
| `/admin/promotions.php` | Promotions issued as GO Codes, one lead code per listing for mail-outs, CSV for mail merge (`admin/promotions.php:2-4, 16-29`). |
| `/admin/products.php`, `/admin/manifest.php` | Traversence's own print and service catalog, client orders and packing slips (`admin/products.php:2-4`; `admin/manifest.php:2-5`). |

### Control and oversight (staff only)

| Staff page or module | What it does |
|---|---|
| `/admin/logs.php` | The action log (`audit_log`, rows only added) and the staff sign-in log (`admin/logs.php:2-3`). |
| `/admin/staff-levels.php`, `/admin/staff-profile.php?id=` | Levels and departments, which decide workspaces. Staff profiles (`admin/staff-levels.php:2-5`; `admin/staff-profile.php:2-6`). |
| Pulse (`/admin/api/pulse.php`) | Cases, assignment, escalation, tasks, vendor jobs, the daily report (`admin/api/pulse.php:2-24`). |
| System Control Board logbook (`lib/ControlLog.php`) | Records alarms, acknowledgements, shut-downs, controls and notes. Also reads switches, start sessions, pre-flight runs, outside-service issues, failed jobs and releases (`lib/ControlLog.php:2-9, 57-99`). |
| Daily consistency check (`lib/ControlConsistency.php`) | Each alarm must read the same on the board, in Pulse and on its module card (`lib/ControlConsistency.php:2-13`). It is read by `/api/health.php`, which is **not in `pages.md`'s register and not shown**. |

---

## (b) How it works underneath

**Area pages and tabs.** Each area page calls the same four helpers: `tv_admin_require` → `tv_admin_start` → `tv_admin_hub` (sections as tabs) → `tv_admin_end` (ADR 0078 §2). Examples: `admin/search.php:11-24`, `admin/safety.php:81-89`, `admin/data-crawler.php:11-21`.

- A section either embeds a tool's own page (`url`) or renders inline HTML (`html`).
- Badges come from `tv_admin_counts` (`admin/search.php:12-13`).

**Workspaces.** `admin/w.php` adds the workspace model.

- `Workspaces::opens($staff)` decides which workspaces a person enters (`:19`).
- `allowsArea(... StaffLogin::areaFor(path))` filters each tool (`:31`).
- An unbuilt section renders a "Coming" or "In part" card (`:41-45`).

**Router.** `admin/index.php` sets `$tvRoute` and requires `router.php` (not shown). Module-style addresses such as `/admin/growth/promotions` and `/admin/backoffice/people` appear in `admin/promotions.php:14` and `admin/staff-profile.php:37`.

**Access checks.** These are not uniform across the code shown:

| Check | Where it is used |
|---|---|
| `is_admin` on the session user | `admin/api/move-files.php:14`, `env-setting.php:17` (plus `Access::can('config.env', min executive)` at `:18`) |
| `IntakeStager::canUseIntake` | `admin/api/costs.php:12`, `vendors.php:14`, `pulse.php:34`, `rebuild.php:16` |
| `canUseIntake` OR any `regional_admin` row | `admin/api/nexus.php:18-19`, `find-listing.php:12-13`, `listings-bulk.php:17-18`, `sales.php:27-28` |
| `Roles::can($ctx,'people')` in the admin context | `admin/api/people.php:27-29` |
| `Access::can('config.communications[.manage]')` | `admin/api/comms.php:19-20` |
| Extension Origin + `X-Vault-Token`, no cookies | `admin/api/vault-ext.php:15-16, 23` |
| Content roles (Editor / Content Creator) | `admin/experiences.php:20`, `nations.php:21` |

**Staff identity (0100).** Each staff login has a hidden user record, so existing admin checks keep working. Bootstrap sends `/admin` pages to `/admin/login.php` unless a staff login is active. The bootstrap code itself is not shown.

- Sign-in steps: `StaffLogin::checkPassword` → `checkCode` (`admin/login.php:31-36`).
- The step expires after 600 s (`:54`).

**CSRF.** State-changing POSTs check the session token. Examples: `admin/api/people.php:68`, `admin/promotions.php:33`, `admin/recreation.php:19`.

**Data separation (0110).**

- `lib/AdminTables.php:19-31` creates admin library tables in the admin database when the main login can reach it, and records them in `data_moves` so `Database::t()` resolves them.
- Admin tables are named through `Database::t('admin_access' | 'staff_accounts' | 'audit_log' | 'pulse_marks')`. Examples: `admin/logs.php:16-20`, `admin/staff-profile.php:56`.

**Auditing.**

- `Audit::log` on `.env` changes, recorded as before → after (`admin/api/env-setting.php:35-37`).
- `Roles::log` on member actions (`admin/api/people.php:73, 81, 85`).
- `Standing::logAccess` on opening a member card (`:98, 102`).
- `StaffLogin::log` on profile edits (`admin/staff-profile.php:32`).
- `ControlLog::add` never throws, so a logging failure never stops the action it records (`lib/ControlLog.php:31-40`).

**Staging (0165, ADR not shown).** These are pure-function libraries:

- `lib/StagingMerge.php`: the three-way row merge, with conflicts for Jason to choose (`:7-14, 33-73`).
- `lib/StagingIds.php`: rewrites ids across columns, JSON, `kind:id` text, links and store keys (`:2-12`).

**Possible fragility (needs a check).** `admin/api/listings-bulk.php:17` and `rebuild.php:16` call `IntakeStager` without a `require_once` in the file shown. It may arrive via bootstrap or `ListingBulk.php`/`Rebuild.php`, which are not shown.

---

## (c) Decisions

Only the ADRs marked "attached" were read. The rest are cited from code headers or the PRD, and their full text is **not shown**.

| ADR | What it rules |
|---|---|
| **0016** (attached; amended by 0070) | Claims review queue. Hub-scoped. FIFO. Three row types with their own actions (approve/reject, looks legitimate/flag→dispute, escalate/dismiss). Admin phone verification. The endpoint re-checks the hub on the server. |
| **0022** (via PRD) | Every admin route is behind an auth guard from its first commit. |
| **0029** (attached) | Admin uses the shared member session. Separately, `currentUser()` must drop suspended users (