# UI/UX experience check: start to finish (2026-11-01)

A check of the visitor's path through the live site, from first arrival to the admin, against the standards in
`CLAUDE.md` (decisions/0058 §11, §20–21, 0129, 0057, `tv-ask.js`, `tv-password.js`, 0127).

**What was checked:** the live mirror `degraffics/traverse-3way-sync` at `383ee83` (`sync-storage/` = `website/`,
`sync-app/` = `app/`). This was a read of the code, not a click-through: the site needs its database and server to run.
The 390px pass at the end has to be done on the live site.

**Short answer:** the middle of the path holds up. Content pages, search, the uploader and the FAQ meet the standard.
The **start of the path doesn't**: sign-up, sign-in, password reset and email verification are older pages with their own
password handling, plain red/green messages and no `tv-password.js`. Sign-up also tells people "10+ characters" while the
server wants 8 plus upper, lower, number and symbol, so people find out the real rule only when sign-up fails. Fix that
first.

## The path, stage by stage

| # | Stage | Pages | Verdict |
|---|---|---|---|
| 1 | Arrive | `index.php`, header, Quick Search (`js/search-panel.js`) | Mostly good. Emoji in Quick Search's filter chips |
| 2 | Explore | `discovery/`, `directory/`, map (`js/map-view.js`), `hub/` | Good layout. Emoji in chips; some Call links not counted |
| 3 | Read a page | `listing/view.php`, `place/landmark.php`, `place/recreation.php`, `experiences/view.php`, `journeys/view.php`, `market/view.php` | Listings and places meet §20 in full. Experiences and journeys lack the hero; journeys has its own like/comment code |
| 4 | Act on it | engage bar, Link button, Call / Directions / Website | Good on listing cards and the map. Some Call links skip `data-out` |
| 5 | Join | `register.php`, `verify-email.php`, `login.php`, `forgot-password.php`, `reset-password.php` | **Off standard** (below) |
| 6 | Own space | `user/dashboard.php`, `user/journeys/`, `user/market/` | Works; plain red/green messages in the dashboard; emoji counters |
| 7 | Business | `claim.php`, `join.php`, `business/`, `listing/businessportal.php` | `join.php` and the claim flow use the shell. Emoji on "Use where I am" |
| 8 | Community | `community/`, `community/group.php` | Breadcrumbs still on; no engage bar or Link button on a group |
| 9 | Admin | `/admin/<module>/…` via `admin/router.php` | Command strip on 3 record pages of ~20; `chamber-builder.php` open to anyone |

## Findings, most important first

### 1. Sign-up states the wrong password rule
- `register.php:39` says "Password (10+ characters)", `minlength="10"`.
- `app/lib/Auth.php:62–75` requires 8+ characters **and** an upper-case letter, a lower-case letter, a number and a
  symbol.
- `reset-password.php:19` uses `minlength="8"`.
- So a new visitor types a 12-letter lower-case password, passes the page's check, and is refused by the server. It's the
  first thing a new member does.

**Fix:** one rule, stated once. Give the new-password boxes `autocomplete="new-password"`, `minlength` and the rule the
server checks, and let `tv-password.js` show the live checklist so the form won't send until it's ticked.

### 2. The join pages skip the shared page parts
`login.php`, `register.php`, `forgot-password.php`, `reset-password.php`, `verify-email.php`, `email-revert.php` and
`listing/confirm-referral.php` build their own page from `app/ui/auth-nav.php`, which doesn't load `tv-ask.js` or
`tv-password.js`. The result:
- **No Show/Hide from `tv-password.js`:** `login.php:66` and `reset-password.php:61` have hand-written versions;
  `register.php` has none.
- **No live checklist:** `reset-password.php:33` sets `data-pw-match="#password"`, but nothing reads it on that page.
- **Plain colored messages** instead of `TvAsk.say()` / `data-tv-flash` / `data-tv-status`: `register.php` sets
  `text-green-700` / `text-red-700` on a status line (lines 31, 123–136). The same pattern is in `login.php` (5),
  `reset-password.php` (7), `verify-email.php` (4), `forgot-password.php` (3), `email-revert.php` (4).
- **Placeholders used as labels:** the boxes have no `<label>`, so the hint disappears once someone starts typing and
  screen readers get little.

**Fix:** move these pages onto `tv_page_start(…, ['no_crumbs' => true])` as `join.php` and `two-step.php` already do,
or have `tv_nav_head()` load `tv-ask.js` and `tv-password.js`. Then delete the hand-written Show/Hide code and the colored
text.

### 3. Emoji where the brand's icons belong (21 files)
The rule is `tv_icon()`, never emoji. The ones visitors see most:
- `js/search-panel.js:82`: Quick Search chips (🕒 Open now, 📞 Has a phone, …), 17 in all.
- `discovery/index.php:250`: theme chips (🧭 …), 9.
- `directory/index.php:190`: 🕒 Open now, 6.
- `journeys/view.php:95, 137–138`: 📍 place, ♥ likes, 💬 comments; the same 👁 / ♥ / 💬 counters in
  `user/journeys/index.php:115` and `admin/journeys.php:139`.
- 📍 buttons: `js/suggest.js:152` ("I'm here now"), `claim.php:146` ("Use where I am"), `user/journeys/compose.php:164`.
- `order.php:449`: "👉 Please enter your initials".
- Admin: `admin/listing-edit.php:213` 🗺, `admin/stories.php:88` 🌎, `admin/crawler-review.php:467` 📍,
  `js/admin-builder.js:55` 🔒.

★ ratings (`admin/client-order.php:1304`), ✦ AI Assist (`js/tv-ai-assist.js:26`) and ♥ on the hero
(`app/ui/explore-hero.php:47`) are type symbols rather than emoji; worth a decision on whether they stay.

### 4. Breadcrumbs on content pages
`community/index.php:9` and `community/group.php:11` still pass `'crumbs'`. (`listing/view.php:126` uses them only on the
"Listing not found" page; `user/journeys/` is inside the dashboard.) A group page also has no engage bar, Link button or
view count (`tv_count_item`).

### 5. Content pages that aren't complete to §20
| Page | Hero | Engage bar | Comments | Views | Link |
|---|---|---|---|---|---|
| `listing/view.php` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `place/landmark.php`, `place/recreation.php` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `experiences/view.php` | **no** | ✓ | ✓ | ✓ | ✓ |
| `journeys/view.php` | **no** | **own code** | **own code** | ✓ | ✓ |
| `market/view.php` | **no** | ✓ | none by design (messages) | ✓ | ✓ |

`journeys/view.php` keeps its own like button and comment list (`Journeys::liked`, `Journeys::comments`) instead of
`tv_engage_bar` / `tv_engage_comments`, so reporting, the 18+ check and comment look differ from every other page.

### 6. Outbound taps not counted
These Call links have no `data-out="call" data-e="…"`, so those calls never reach the counts:
- `directory/index.php:436`: the map pop-up's Call (the list's Call at line 376 is counted).
- `place/recreation.php:97` and `guide/resources.php:74`: round Call buttons (these aren't listings, so `data-e` may
  need a place id; decide whether they count).
- `directory/classic.php:1613`: the old directory, still linked from `directory/index.php` and `app/ui/header.php`
  but not in `archive.md`. Either archive it properly or bring it up to the standard.

### 7. Admin record pages without the command strip
Only `admin/client-order.php`, `admin/guide-edit.php` and `admin/content-template.php` call `tv_command_strip()`.
Record pages still on the old title and breadcrumbs include `admin/order.php`, `admin/listing-edit.php`,
`admin/staff-profile.php`, `admin/claims.php`, `admin/products.php`, `admin/promotions.php`, `admin/suggestion.php`
and `admin/landmarks.php`. (Some of these may be lists rather than records; check each.) `admin/listing-intake.php` has the
most plain colored messages of any page (16).

### 8. `admin/chamber-builder.php` is open to anyone
It's Jason's work in progress (`archive.md`), but it has no sign-in check, uses `alert()` and `confirm()`
(lines 432, 455, 491), and its "Run" button shows made-up log lines that say crawls ran and listings were published, with
internal file paths. Nothing is written, but a visitor who finds the URL sees a page that looks broken or misleading.
**Fix:** put it behind the staff check (or a section in the manifests) before anything else is done to it.

## What already meets the standard
- **No browser dialogs** anywhere except `chamber-builder.php`.
- **One uploader:** `$_FILES` appears only in `app/lib/Uploads.php` and `website/api/upload.php`.
- **Password boxes** get `tv-password.js` everywhere the shared header or shell is used (`app/ui/header.php:392`,
  `app/ui/page-shell.php:62`), including `user/dashboard.php`, `join.php` and the admin.
- **FAQ:** all 8 `faq.php#…` links resolve, and the live `app/ui/faq.md` matches this repo's `faq.md`.
- **No breadcrumbs** on listings, places, journeys or Let's Explore.
- **Phone layout:** no page-level negative margins used to fake edge-to-edge; wide-screen rules are behind
  `min-width` media queries.
- **Images** carry `alt` (decorative ones `alt=""`).
- **Listing cards and the map** count Call / Directions / Website taps.

## Order to fix
1. Sign-up password rule (finding 1): one line on the page plus the checklist. Small change, biggest effect.
2. Join pages onto the shared shell (2). Brings Show/Hide, the checklist and the one look for messages in one move.
3. Lock `chamber-builder.php` (8).
4. Emoji to `tv_icon()` in Quick Search, Let's Explore, the directory and journeys (3).
5. Journeys onto the shared engage bar and comments, and the hero for experiences and journeys (5).
6. Community breadcrumbs, group engage bar (4); uncounted Call links (6); `directory/classic.php` archived or updated.
7. Command strip on the remaining admin record pages (7), one module at a time.

## The 390px pass (do on the live site)
Each at 390px wide, signed out then signed in:
1. Home → Quick Search → type "coffee near" a pilot town → open a result.
2. The listing: hero flush under the tool bar, name not a link, Link top right, engage bar, round action buttons down the
   right, comments at the end, cards 3px from the edges, no sideways scroll.
3. Let's Explore and the directory: chips wrap, filters show only active ones with ×, map opens as its own view.
4. Sign up with a password that meets only the page's stated rule (shows finding 1), then verify the email, sign in,
   forget and reset the password.
5. Dashboard: ☰ tools left, profile and role right; journeys list and compose; market post.
6. Claim a listing, then the business portal.
7. Admin: open an order, a listing and a staff member; watch for the command strip hiding on scroll down and returning on
   scroll up.
