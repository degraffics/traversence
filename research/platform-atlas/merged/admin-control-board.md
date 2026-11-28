# research/platform-atlas/admin-control-board.md (reconciled)

*Draft for human review. This merges two independent passes, P1 and P2.*

**Both passes are cut off.** P1 stops partway through section (c), in the middle of the ADR 0029 row. P2 stops partway through section (e). When a point from the later sections appears in only one pass, that is often because the other pass ends early, not because it disagrees. Those points are still marked single-pass.

**How to read the tags**

- **[both]**: both passes support the point. Citations from both are given when they differ.
- **single-pass (P1)** or **single-pass (P2)**: only one pass says it.
- **passes differ: …**: the passes describe the same thing differently.

**Path conventions.** P1 writes `admin/…` for `traverse-3way-sync/sync-storage/admin/…` and `lib/…` for `sync-app/lib/…`. P2 mostly cites bare file names.

**Sources.** Both passes used the attached charter, brand, PRD, `site-audit/pages.md`, the ADRs and the code excerpts. The code is a partial slice of the sync mirror.

- Being present in the mirror does not show a file is deployed. P1 says this in its header; P2 says it about `comms.php`.
- P2 adds that each file is capped. This is single-pass (P2).

**Files referenced but not shown:**

- **[both]:** `router.php`, `app/ui/admin-hub.php`, `Auth.php`, `StaffLogin.php`, `Workspaces.php`, `Pulse.php`, `Standing.php`, `app/bootstrap.php`, `admin/claims.php`, `/api/health.php`, and the board console page.
- **single-pass (P1):**
  - `admin-dashboard.php`, `app/ui/admin-crawler.php` and `app/admin/Access.php`.
  - `admin/listings.php`, `insights.php` and `people.php`. P2 also treats `listings.php` as not shown, in (d).
- **passes differ: the board console.**
  - P1 says the console's page and route are not in `pages.md`, so it does not name one. P1 lists `control-console.php` as not shown.
  - P2 names it `app/admin/control-console.php`, "required at `:31`", and says it is not shown.

---

## (a) What each audience can do here

**[both]** The admin side is staff-only. Residents, travelers, members and businesses never use it directly. They see the effects of staff work. P2 adds a framing, single-pass (P2): these audiences meet the admin "at the edges", where what they submit arrives for a person to decide and where staff decisions come back to them.

### Staff entry and shell

| Route | What it does | Status |
|---|---|---|
| `/admin/login.php` | Staff ID or work email, plus a password, then an emailed 6-digit code. An authenticator code also works. **Can't get the email?** opens an owners' case. | [both] (P1 `admin/login.php:2-4, 87-92`, `:43-47, 108-119`; P2 `login.php:2-7, 43-47`). The redirect of members to `/login.php` is single-pass (P1, `:87-92`). |
| `/admin/setup.php?t=` | One-time setup link: the person confirms an emailed code and sets a password. | [both] (P1 `admin/setup.php:2-4, 79-89`; P2 `setup.php:2-4, 83-87`). The link lasting 72 hours is single-pass (P1). The password minimum of 12 characters is single-pass (P2). |
| `/admin/` | The dashboard module, handed to `router.php`. | [both] (P1 `admin/index.php:5-6`; P2 `index.php:5-6`) |
| `/admin/w.php?w=` | One of 11 workspaces. Its tools show as tabs, filtered by the person's role. | [both] (P1 `admin/w.php:2-5, 19-23, 31`; P2 `w.php:2-5, 19-31`) |
| `/admin/adminportal.php` | A 301 redirect to `/admin/`. | [both] (P1 `admin/adminportal.php:4`; P2 `adminportal.php:4`) |
| `/admin/search.php` | The Search area, with 7 embedded tools. | single-pass (P2, `search.php:15-23`) |
| `/admin/safety.php` | Tabs: Reports, Standing, Procedures, Hidden places, Short codes. | single-pass (P2, `safety.php:82-88`). P1 cites `admin/safety.php:81-89` only as an example of the four hub helpers. |
| `/admin/data-crawler.php` | Crawler pages plus the Data browser. | single-pass (P2, `data-crawler.php:14-20`). P1 cites `:11-21` only as a hub example. |

### Effects for residents and travelers

P1 splits these into separate resident and traveler tables. P2 combines them as "Resident or traveler".

- **Reports.** Staff Remove, Restore or Dismiss reported items. A post reported by 3 people is hidden until a person decides. Reporters' names are never shown to the person reported.
  - [both] (P1 `admin/reports.php:40, 61-63`; P2 `reports.php:40, 59-65`).
  - **passes differ: what can be reported.**
    - P1, which files this under members, lists posts, groups, comments and messages.
    - P2 lists posts, groups, comments, journeys, experiences and listing messages.
- **"Something missing" suggestions.** `/admin/listing-new.php?suggestion=` turns a suggestion into a live listing. A signed-in sender is emailed.
  - [both] (P1 `admin/listing-new.php:2-5, 30-31`; P2 `listing-new.php:2-5, 31`).
  - P2 adds that the listing is unclaimed and the email is sent when it goes live.
- **Adding a listing.** `/admin/listing-add.php` puts a listing live unclaimed. The crawler then looks for its phone, website, email and hours.
  - [both] (P1 `admin/listing-add.php:2-6, 29-31`; P2 `listing-add.php:2-6, 29-33`).
  - P2 files this under businesses.
- **Geocoding.** `/admin/geocode.php` runs Census geocoding.
  - [both]
  - Replacing ZIP-centroid points is single-pass (P1, `admin/geocode.php:27`).
  - Running 500 at a time is single-pass (P2, `geocode.php:2-5`).
- **Search index.** `/admin/search-index.php` builds the 5W index.
  - [both]
  - Line citation `admin/search-index.php:34, 42-43` is from P1 only.
- **Search demand.** `/admin/search-demand.php` collects counts of searches only, "never who" (`search-demand.php:63`). Those counts steer where the crawler looks next (`:27-31`).
  - single-pass (P2)
- **Auto-imports.** `/admin/auto-imports.php` offers **Pull from site** or **Put back** for listings the crawler published on its own.
  - [both] (P1 `admin/auto-imports.php:33-35`; P2 `auto-imports.php:2-6, 33-35`)
- **Place stories.** `/admin/stories.php?place=` publishes only after a checklist with at least one source.
  - [both] (P1 `admin/stories.php:2-3, 63-68`; P2 `stories.php:2-3, 63-68`)
- **Experiences.** `/admin/experiences.php`.
  - [both]
  - **passes differ: how experiences get approved.**
    - P1 says staff approve, reject or hide them, and approving needs an Editor (`admin/experiences.php:2-8, 96-104`).
    - P2 says experiences post themselves above a credibility score, and otherwise wait for an Editor (`experiences.php:2-8, 79`).
- **Nations.** `/admin/nations.php` shows a nation's visitor rules only after someone ticks "Checked against the nation's own site".
  - [both] (P1 `admin/nations.php:2-6, 106`; P2 `nations.php:5-6, 106`)
- **Recreation.** `/admin/recreation.php` hides sacred, restricted or closed Recreation.gov places.
  - [both] (P1 `admin/recreation.php:2-3, 36`; P2 `recreation.php:2-3, 36`)
  - That hidden places stay hidden across the monthly reloads is single-pass (P1).

### Effects for members

- **Standing (Safety → Standing).** Asking for a review stops the clock. Staff decide with **Lift** or **Uphold**.
  - [both] (P1 `admin/safety.php:47-80`, `:62`, `:57, 75`; P2 `safety.php:62`, `:57, 69-78`, `admin/api/people.php:71-75`)
  - **passes differ: the ladder.**
    - P1: a warning, then 24 hours, then a stop on posting and messaging, then revoked.
    - P2: a warning gives the member 24 h to confirm or ask for review, then a posting and messaging stop, then revocation with a review link.
    - P1 reads "24 hours" as its own step. P2 reads it as the time allowed to respond to the warning.
- **Looking up a member (`/admin/api/people.php`).** Staff cannot browse members. Opening a card needs a recorded reason. Seeing contact details is a separate recorded reveal.
  - [both] (P1 `admin/api/people.php:2-6, 92-103`; P2 `people.php:60-64, 101-102`, `:96-99`)
  - The search minimum is single-pass (P2, `people.php:109-116`): at least 3 characters, a #number, or an exact email.
- **Short codes (`/admin/short-codes.php`).** Staff can switch off a misused trvgo.us code, and the owner sees the reason.
  - [both] (P1 `admin/short-codes.php:2-4, 65`; P2 `short-codes.php:2-4, 51-52, 65`)
  - That the code is kept and never deleted is single-pass (P2, `:51-52`).
- **Staff who are also members.** An owner starts a link between the staff login and the member account, and the member confirms (decisions/0100, addendum 2026-10-23). The member can opt in to a "Traversence team" badge (`staff-profile.php:104`).
  - single-pass (P2)

### Effects for businesses

- **Claims (`/admin/claims.php`).** The file is not shown. Its behaviour comes from `pages.md` §5 and ADR 0016.
  - [both]
- **Nexus (`/admin/api/nexus.php`).** Staff link listings into a Nexus.
  - [both] (P1 and P2 `admin/api/nexus.php:2-7`)
  - The quote "it stays a listing" is single-pass (P1).
- **Merging listings (`/admin/api/listings-bulk.php`).** Merge listings, with Undo.
  - single-pass (P1, `listings-bulk.php:2-8, 41-46`)
- **Rebuild from its website (`/admin/api/rebuild.php`).**
  - [both] (P1 `admin/api/rebuild.php:2-7`; P2 `admin/api/rebuild.php:2-6`)
  - The steps, from P1 only: read the site, plan changes, apply what staff accept, with Undo.
- **Sales (`/admin/api/sales.php`).** A call and visit log, and an emailed claim link.
  - [both] (P1 `admin/api/sales.php:2-14, 42-43`; P2 `admin/api/sales.php:5-6`)
  - That sending needs `BUSINESS_POSTAL_ADDRESS` is single-pass (P2).
  - The business-card scanner `/admin/api/cards.php` is single-pass (P1).
- **Promotions (`/admin/promotions.php`).** GO Codes, including one per lead for mail-outs.
  - [both] (P1 `admin/promotions.php:2-4, 16-29`; P2 `promotions.php:2-4, 43-48`)
  - The CSV export for mail merge is single-pass (P1).
- **Catalog (`/admin/products.php`, `/admin/manifest.php`).** Traversence's own catalog, orders and packing slips.
  - [both]
  - Line citations `products.php:2-4` and `manifest.php:2-5` are from P1 only.
- **Vendors.** Print shops and similar are emailed a link to their work orders. Issuing a new link stops the old one.
  - single-pass (P2, `admin/api/vendors.php:4, 19-26`)

### Control and oversight (staff)

- **Logs (`/admin/logs.php`).** The action log (`audit_log`) and the staff sign-in log.
  - [both] (P1 `admin/logs.php:2-3`; P2 `logs.php:2-3, 15-21`)
  - That `audit_log` rows are only ever added, never changed, is single-pass (P1).
- **Staff levels and profiles (`/admin/staff-levels.php`, `/admin/staff-profile.php`).**
  - [both] (`staff-levels.php:2-5` in both; P1 also `admin/staff-profile.php:2-6`)
- **Pulse (`/admin/api/pulse.php:2-24`).** Cases, assignment, vendor jobs, the daily report and tasks.
  - [both]
  - Escalation is single-pass (P1).
  - Posts, the 4OVER paste import and mailbox actions are single-pass (P2).
- **System Control Board log and consistency check.** [both]. Details are in (b).

---

## (b) How it works underneath