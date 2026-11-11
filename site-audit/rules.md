# Rule catalogue

Decision 0145. Every rule the audit checks, where it comes from, and how it's checked.

**How each rule is checked:**
- **code:** `tools/page-audit/audit.py` checks it now.
- **rendered:** a real browser, Phase 2.
- **tools:** using each function, Phases 2–3.
- **data:** the health report and staging, Phases 2–3.
- **review:** a person or a review session, Phase 4.

**Block** means a new failure stops a zip (`zipcheck.py`).

A change to a rule is made in its source (`CLAUDE.md` or the decision), then here, then in the check, in the same
change.

## Checked in code now

| # | Rule | Source | Block |
|---|---|---|---|
| R01 | Every method a file calls exists (a zip can't drop one still in use) | `CLAUDE.md` One copy; the 2026-11-05 `Engage::liked()` outage | yes |
| R02 | No `alert()`, `confirm()`, `prompt()`; use `TvAsk` | `CLAUDE.md` No browser dialogs | yes |
| R03 | Uploads only through `Uploads.php` and `/api/upload.php` | `CLAUDE.md` One uploader; decisions/0127 | yes |
| R04 | No PHP the server can't run (`readonly`, `enum`, `never`, first-class callables, `array_is_list`, `json_validate`, typed constants, `new` in defaults) | `CLAUDE.md` The live server runs PHP 8.0 | yes |
| R05 | Pages load the session and draw the account bar | decisions/0055; the 2026-11-05 home page bug | no |
| R06 | FAQ links point at questions that exist | `CLAUDE.md` Keep the FAQ current; decisions/0057 | yes |
| R07 | Endpoints that change things check the security token (or a signature) | `app/lib/Response.php`; security | no |
| R08 | Migrations: add-only, safe twice, no `IF NOT EXISTS` in `ALTER`, no routines | `CLAUDE.md` Migrations; decisions/0145 §6 | yes |
| R09 | Every page is in `routes.md` or `archive.md` | `CLAUDE.md` Files used once, and the archive; decisions/0145 §1a | no |
| R10 | The brand's icons, not emoji | `CLAUDE.md` Phone layout and visual standard (Icons) | no |
| R11 | No breadcrumbs on content pages | `CLAUDE.md` Phone layout (No breadcrumbs); decisions/0058 | no |

## Rendered checks (Phase 2)

| # | Rule | Source |
|---|---|---|
| V01 | No sideways scroll at 390px; cards 3px from the edges, `.5rem` corners | `CLAUDE.md` Phone layout; decisions/0058 §11 |
| V02 | Card anatomy: name is the link (brown, underlined); Link button top right; action icons 30px, round, labelled | `CLAUDE.md` Card anatomy |
| V03 | Active icon state sage with a glow; rest cream with a brown ring | `CLAUDE.md` Icons |
| V04 | Signed in: the account bar is there, the right mode and colour | decisions/0055 |
| V05 | Signed out: the menu has Create account and Sign in; signed in, it doesn't | decisions/0055 |
| V06 | No dialog stacked over another; the search's top line stays in view | decisions/0058 §35 |
| V07 | Every click that waits shows a spinner, then the site's notice | `CLAUDE.md` Busy |
| V08 | Notices use `TvAsk.say`; status lines use `data-tv-status` | `CLAUDE.md` Notices |
| V09 | Password boxes have Show/Hide; new-password boxes a live checklist | `CLAUDE.md` Password boxes |
| V10 | Word fields have spell check; lists that need it have ⓘ | `CLAUDE.md` Words and lists; decisions/0144 |
| V11 | Content pages: hero, header (kind, title, place, Link), engage bar, comments | `CLAUDE.md` Content pages; decisions/0058 §20 |
| V12 | Record pages: the command strip (Back, name, Save/Close, ☰), notices box | `CLAUDE.md` Record pages; decisions/0129 |
| V13 | Filters are one dropdown button, not rows of pills | `CLAUDE.md` Filters |
| V14 | No console errors; no failed requests | decisions/0145 §1 |
| V15 | Accessibility basics: contrast, labels, tap size | decisions/0145 §1 |

## Tools and data (Phases 2–3)

| # | Rule | Source |
|---|---|---|
| T01 | Every tool in `tools.md` does what it says for each account allowed, with good, bad and empty input | decisions/0145 §1 |
| T02 | Every tool is refused, greyed out with the reason, for each account not allowed | `CLAUDE.md` The admin (Access) |
| T03 | Admin tools only from the manifests; other modules' tools in a drawer | `CLAUDE.md` The admin; decisions/0109 |
| T04 | Pulse steps a person can do from the case are buttons; outside services carry their link | `CLAUDE.md` Pulse |
| T05 | Links travel wrapped past ModSecurity | `CLAUDE.md` Links past Bluehost's ModSecurity |
| T06 | No one is offered what their account can't use, or should use elsewhere: each account type (guest, member, member on a paid plan, business owner, staff) sees the version for them, or a link straight to where theirs is. Checked page by page, signed in as each test account. Known: `/ai/` shows staff "Comes with a paid plan" though staff have AI Assist in the admin (Insights & Research → AI Assist) | `CLAUDE.md` Never a dead end (Jason, 2026-11-11: "the system should know better than to offer a staff member something they cant do or should do elsewhere. that goes for all users") |
| D01 | Every table and column used exists, in the right database | decisions/0110; decisions/0145 §1 |
| D02 | Query speed and plans (no full scans on pages, no query per row) | decisions/0145 §1 |
| D03 | Data health and retention rules running | decisions/0009, 0145 §1 |
| D04 | Endpoints answer only those allowed, with no more than the page needs | decisions/0145 §1 |
| D05 | Every paid call covered by revenue; caps, caching, backoff | `CLAUDE.md` No income, no expense; decisions/0133 |
| D06 | Tribal nations' land: sensitive names held, reads through `Landmarks::visibleSql()` | `CLAUDE.md` Tribal nations' land; decisions/0066 |

## Review (Phase 4)

| # | Rule | Source |
|---|---|---|
| J01 | The top journeys reach their goal within the agreed steps, with no dead ends | decisions/0145 §1 |
| J02 | What's shown is true and complete; what exists is reachable | decisions/0145 §1 |
| J03 | Plain wording, one clear next action, empty states that say what to do | decisions/0145 §1; `brand.md` |
| G01 | Crawler and guides: curators not controllers; tell the story, never the way in; licence decides the use | `CLAUDE.md`; decisions/0133 |
| G02 | Every page and tool is governed by a decision; no two decisions conflict | decisions/0145 §1a |
