# Hand-off: build decisions/0159, the builder

## 0. Where the build stands (2026-11-14 13:25 UTC; start here)

**5b is live and verified byte for byte: release #42 `themes5b_20261114` (Sync 2026-11-14 13:21 UTC).** Next: **5c**,
commerce blocks (they'll read the same `--tvb-*` variables, so they take the theme). Not yet tried live: a picture
upload into a business's Gallery from the builder; the themed builder in the real portal (tested locally only).
5b (built on Sync 00:58 UTC, PR #635): Files: `app/lib/Storefront.php`
(`THEME_STYLE`: each theme's heading font, button and section corners, two named panels; `style()`, `blockVars()`),
`app/lib/Blocks.php` (the kit reads `--tvb-*` CSS variables with the brand's look as defaults, so our pages don't change;
shop zones set them: `zone()` adds `tvb-themed` and the theme's page color in the portal, `shop()` on trvgo.us;
`bgsFor()` names a section's backgrounds by the theme: Sand/Clay, Sage/Stone, Linen/Honey, Ember/Dusk),
`website/api/blocks.php` (`bgs` from `bgsFor`), `app/ui/storefront-view.php` (headings and buttons follow the theme too;
5a's `.pgb .tvb-*` colour overrides replaced by the variables), `app/business/storefront.php` (a note under Theme),
`app/ui/faq.md` ("Do my storefront's pages follow my theme?" before "Can I put my storefront on my own domain?", and 5a's
"Coming next" now starts at blocks for selling). Tested: 33 DB checks (MariaDB; panels, accent, our pages untouched,
contrast of every panel), each theme's page rendered by the real `tv_storefront_serve` at 390px and 1100px (no sideways
scroll), the portal's themed zone. 

### Written 01:00 UTC

**All three below are live and verified byte for byte:** #38 `sitetopfix` (Sync 00:20), #40 `stgstaff` (Sync 00:40;
staging needs a refresh before Jason's staff entry and M2 ServCore show there), **#41 `studio5a` = step 5a** (Sync 00:58).
**Next: 5b** (the four themes as the blocks' starting styles), after Jason's look at 5a. Not yet tried live: a picture
upload into a business's Gallery from the builder.


**Step 5 started** (the session that took over at 21:10 UTC; built on Sync 2026-11-13 23:59 UTC). Three releases, sent one
at a time in this order (Jason, 2026-11-14 00:20 UTC: "go"):
1. **`sitetopfix_20261114`** (PR #628, merged): `app/ui/header.php`. The "Every page, under the header" zone (4c) was drawn
   inside the top bar's flex row (Jason's home-page screenshot on staging: the outline squeezed at the bar's right edge,
   the bar stretched). Now drawn hidden and moved at once to just under `#top-bar` (after `#tv-notice`), like the account
   notice; hidden where there's no bar or the bar is fixed (directory/classic.php).
2. **`stgstaff_20261114`** (branch `claude/stgstaff`): `app/lib/StagingSite.php`. Jason's account menu on staging showed
   only his personal account. Opening staging from the live admin signs in as the staff login's own user row, and the
   strip rewrote its marker address (`@staff.traversence.invalid`, `StaffLogin::DOMAIN`), so `Roles::staffLogin` and
   `StaffLogin::otherAccount` no longer saw a staff login (no staff entry, no linked member account, no M2 ServCore).
   The strip keeps those addresses now. **Takes effect at staging's next refresh** (tell Jason to refresh staging).
3. **`studio5a_20261113`** = **5a, a business's pages as blocks** (branch `claude/storefront-studio-5a`, PR #626 closed
   for now so only one PR is open; reopen or open a new one from that branch after merging main into it):
   - `Blocks`: zone `shop.<page id>` for a business's `site_pages` row (`isShop`, `shopOwner`); `can($perm, $zone)` asks
     `Team` for shop zones (`canShop`: on the team, the storefront set up, its plan; Storefront & theme edit = pages.edit,
     Publish edit = pages.publish); no live parts there (`liveFor` returns none, `blockHtml` never draws one);
     `state()['seed']` = the page's Markdown as one text block while nothing is saved; `shop()` draws it for trvgo.us;
     `hasLive()`; Publish later refused for shops (0158 §6); `afterShop()` syncs the storefront's public album.
   - `SitePages::hasBlocks` (a built page may go live with no Markdown); `Storefront::syncAlbum` + `blockPhotos` (the
     business's own photos in its pages' live and draft blocks join its public "Storefront" album, never another's);
     `block_image` upload target: shop zones go to the business's Gallery (`/gallery/m.php?i=…&s=full`, allowed by
     `Blocks::safeUrl`); `/api/blocks.php` asks per zone (`can.why`, `can.later`, `team`); `tv-editor.js` starts from
     the seed, greys Publish with the reason, hides Publish later where not offered.
   - Portal: `tv_biz_storefront_page()` (`/business/<id>/storefront?edit=<page id>`), Edit the page and Preview on each
     page; a built page's Markdown box becomes a note. `storefront-view.php`: draws `Blocks::shop()`, the preview link
     shows drafts and Draft pages on a live storefront too (noindex, no-store, no redirect to the domain).
   - FAQ: "How do I build my storefront's pages?" (`studio5a-faqedit.py` logic: insert before "Can I put my storefront on
     my own domain?"; re-apply on the live copy if faq.md changed).
   - Tested: 29 DB checks (MariaDB), step 4's 4c/4d DB tests still pass, browser at 390px (owner draft and publish,
     Creator's greyed Publish, Viewer look only, storefront script-free, draft preview). **Not tried live:** a picture
     upload into the business's Gallery.
- Next after 5a: **5b** themes as starting styles, **5c** commerce blocks, **5d** the studio bar (proposals below).
- Testing here: MariaDB on port 3399 (below); the portal runs under `php -S` with a router sending `/business/*` to
  `business/router.php`; Playwright needs a non-headless user agent (bootstrap gives "headless" visitors no session).

### Written 21:00 UTC (step 4 done)


**Step 4 is done and live**, each verified byte for byte against the mirror:
- bar fix = **#31** (`barfix_20261113`, Sync 20:02): the site manager shows what's on staging, or else the newest release.
- **4c** = **#33** (`editor4c_20261113`, Sync 20:40): Publish later; zones on every page (`site.top`, `site.bottom`).
- **4d** = **#34** (`editor4d_20261113`, Sync 20:59): Likes and comments and a Form as live parts, only in our pages'
  zones (`Blocks::LIVE` 'page.', `typesFor`/`liveFor`); Engage kind `page`; `SitePages::submit` takes a form block's kind
  while the page's live blocks hold it (`Blocks::hasForm`). Not yet tried live: the form send (captcha) and a like on a page.
- `handoffs/0159-prepared/` is gone (git history keeps the patches and their DB tests).

**Next: step 5, Storefront Studio** (Jason, 2026-11-13 21:10 UTC: "go ahead with step 5"; started in a fresh session per
CLAUDE.md, a phase per session). Proposed slices, one release each. Check each against the live code first (`SitePages`,
`Storefront`, `app/ui/storefront-view.php`, `trvgo/index.php`, the portal's Storefront & pages, `Team` perms), and adjust:
- **5a, a business's pages as blocks:** `page.<id>` zones work for a business's own `site_pages` rows (owner = its
  entity), edited in the portal's Storefront & pages on traversence.com with `Team` perms (`publish` publishes, no
  approval, 0158 §3) instead of `pages.edit`/`pages.publish`; its Markdown shown as one text block until edited (nothing
  rewritten, §2); `storefront-view.php` draws the live tree on trvgo.us **script-free** (no live JS parts there: the
  browser-only LIVE parts are not offered in shop zones). `Blocks::liveFor` already filters by zone start.
- **5b, the four themes** (Canyon, Sage, Paper, Night, 0139) as the blocks' starting styles on a storefront.
- **5c, commerce-first blocks** (§4): product grid with filters, collection, featured item, related items, Buy now / Add
  to cart (the platform's cart and checkout), menu, hours, map and directions, locations, booking or quote form (leads,
  0112), reviews. They show the catalog, never rebuild it.
- **5d, the studio bar** on the team's preview (the site manager's bar in its studio mode, §6).
Then step 6, AI writing. Claim files first (decisions/0161, `checkout.py claim`).

**Open, for Jason:** which coded pages get zones next; scheduling for shops (0158 §6). On 20:48 UTC he reported something
changed in the phone ☰ tools drop-down (/user/dashboard); the panel's place and width are right. 4c/4d add nothing on
that page; the last change to the menu's placement is context-switcher.php at Sync 2026-11-12 21:48 (list starts 34px down
under the account bar). Waiting on what exactly differs; change nothing until he says.

### Written 19:30 UTC by the session that built step 2's switch and steps 4a–4b

**Done and live** (each verified byte for byte against the mirror, recorded in `deliveries/`):
- **Steps 1–3** (releases #4–#18): the way into staging, the site manager, builds through git that publish themselves, the
  drift report, and the editor's core (`Blocks`, `/api/blocks.php`, `js/tv-editor.js`; zones `home.after-doors`, `page.<id>`).
- **Step 2's last part:** `SYNC_HOME=git` since 2026-11-13 (traverse-3way-sync PR #10). Server or OneDrive edits not
  from a release are put back (the edit kept in that repo's `conflicts/`); release files pass on ("from a release"). Undo:
  `SYNC_HOME: ftp` in its `sync.yml`. A OneDrive read timeout can fail one pair's half of a run: just run it again.
- **Step 4a** = release #23 (`editor4a_20261113`, verified Sync 17:22): widget panel, drag and drop, toolbar, design per
  breakpoint (container queries), live preview, divider and spacer.
- **Admin search** = release #24 (`adminfind_20261113`, verified Sync 17:39): Ctrl K finds scheduled tasks and switches.
- **Step 4b** = release #27 (`editor4b_20261113`, verified Sync 18:59): live components (`Blocks::LIVE`: search box,
  businesses, map button, guide, GO Code; `js/tv-blocks.js`) and the Page tab (title, search description, share picture).
  Not yet tried live: the guide card and GO Code (they need the live database) and the map's pins.

**Prepared, not sent** (built on Sync 18:59 UTC; in `handoffs/0159-prepared/`, both patches checked to apply cleanly to
that live copy and to rebuild exactly what was tested). Send them **in this order, one release at a time**:
1. **`barfix.patch`** (Jason, 19:15 UTC: the site manager on staging still said #18 when Releases ▾ listed #27). Cause: the
   bar stored the first release it showed in localStorage and asked for it forever. Fix: `Releases::barState` falls back to
   the newest release when none is on staging; `js/stg-bar.js` keeps a pick from Releases ▾ in sessionStorage only. Files:
   `app/lib/Releases.php`, `website/js/stg-bar.js`, plus `app/ui/faq.md` (run `barfix-faqedit.py` on the live copy).
   Release text: `barfix-release.json`.
2. **`editor4c.patch`** = step 4c, Jason said go: **Publish later** (▾ in the editor; `site_block_schedule`, made by
   `Blocks::ensure`; due schedules publish at the first look at that zone, once, inside the version's transaction, keeping
   a newer draft) and **zones on every page** (`Blocks::SITE`: `site.top` from `header.php`'s end, `site.bottom` in
   `tv_footer()`; browsing pages only, never admin/sign-in/payment; outlined for editors on the home page or where they
   have content). `Blocks::ensure` now does one cheap SELECT before any CREATE (every page reads these zones). Files:
   `app/lib/Blocks.php`, `app/ui/header.php`, `app/ui/footer.php`, `website/api/blocks.php`, `website/js/tv-editor.js`,
   plus `app/ui/faq.md` (`editor4c-faqedit.py`). Release text: `editor4c-release.json`. Tested: MariaDB
   (`editor4c-dbtest.php`, all ok: past time refused, one version when two look at once, newer draft kept, call off, where
   the zones show) and the browser (Publish later in the panel, the status line in the device's zone).
   **`header.php` and `footer.php` are shared by every page and often changed:** before zipping, fetch the mirror; if
   either changed since 18:59, re-apply only this patch's hunks to the live copy.
- How to send each: copy the live files from the mirror, `patch -p1 < …`, run its faqedit script on the live `faq.md`,
  add its `release.json`, zip (`app/…`, `website/…`), `tools/page-audit/send.py <zip> --mirror <checkout> --note "… built on
  Sync: <time>"`, PR and merge, wait for "Release #N: Live", run the sync, check byte for byte, `deliveries.py verify`.
- At 19:20 UTC `crawler-w2b_20261113.zip` (another session's) was pending in the inbox with `app/ui/faq.md`: the zip check
  blocks ours until it's live (then re-run the faqedit on the new live copy; no need to ask Jason).
- Delete `handoffs/0159-prepared/` once both are live (git history keeps them).

**Next, in order:**
1. Send the bar fix, then 4c (above).
2. **Step 4d** (Jason said go after 4c): the engage bar and a form as live parts, **only in our pages' zones**
   (`page.<id>`; LIVE's first field is "where it may go": use `'page.'`, and filter `types` by zone in the API's GET).
   - Engage bar: add a `page` kind: `Engage::KINDS`, `COUNT_KEY` (`'page' => 'page:'`, matching view.php's
     `tv_count_item('page:ID')`), `target()` (`ctype_digit`), `exists()` (`site_pages` live, owner 0), `LikeGuard.php`'s names
     (about line 260), `Reports.php`'s kind → link (about line 249; view.php takes `?s=slug`, so look up the slug). Draw it
     with `tv_engage_bar($pdo, 'page', (string) $id, ['title' => …])` (`app/ui/engage-ui.php`; needs bootstrap and
     page-shell, which view.php has); comments with `tv_engage_comments`.
   - Form: a block with `kind` contact|quote posting to `/pages/form.php`; let `SitePages::submit` accept a page whose
     live block tree has a form block of that kind, not only `site_pages.form`. Entries keep going to Marketplace &
     Storefronts → Form entries.
3. Step 5 Storefront Studio (SitePages to blocks: each Markdown page as one text block; themes; the portal; team
   Publish), step 6 AI writing.

**Open, for Jason:** which coded pages get zones next (he chose the home page first); scheduling for shops (0158 §6).
**Answered (Jason, 2026-11-13):** `page-shell.php`'s always-on `noindex` stays for every page, ours included, until he says the
site is complete and ready ("I want a complete ready site before I lift the indexing"). Don't lift it or ask again; the Page
tab's search settings are ready for that day.

**Known issues:**
- The 10-minute pickup is irregular (pickups seen at :28, :38, :57; some runs seem skipped). The site manager shows
  "The site last looked by itself …" under Check for builds; a ⚠ past 25 min. If it keeps skipping, look at the
  `releases_tick` cron line (`Scheduler::AUTO`, System Configuration → Scheduled tasks).
- After Check for builds, the first line still says "Look at it on staging, then Publish" (from `straightOn`) even when
  the next line says it published itself. Cosmetic.
- Testing here: MariaDB isn't preinstalled (`apt-get install -y mariadb-server`); its socket path is too long in the
  scratchpad, so run `mariadbd --skip-grant-tables --port=3399 --bind-address=127.0.0.1 --pid-file=… --socket=…`.

**Where the code is:** `Releases::fromGit / autoPublish / sendForReview / reviewPart / straightOn / signedByPublish /
pullFromBar / barState`; `DocsRepo::inbox / blob / takeFromInbox`; `website/api/release-bar.php` (state, upload, pull,
review and the release actions); `website/js/stg-bar.js`; `StagingSite::checkWayIn` and `website/api/stg-probe.php`.

The sections below are the original hand-off (history).

For the Claude session that builds `decisions/0159` (Traversence's platform builder and Storefront Studio). Written
2026-11-13 by the session that built Phase 5 (the Releases page and the staging bar). Read this, then 0159, then begin
at step 1. **Jason works mostly from his phone.** Keep his steps short, and give him each zip as a file.

## 1. Read first

- `CLAUDE.md` and `README.md`: the rules for every build (loaded by themselves). Above all:
  - every build is a release;
  - start from the live mirror;
  - the zip check;
  - the FAQ;
  - the phone standard;
  - no browser dialogs;
  - PHP 8.0 syntax.
- `decisions/0159-the-builder-platform-and-storefront-studio.md`: what to build and in what order.
- `decisions/0158-one-way-to-publish.md`: drafts, versions, Publish as a role's permission.
- `decisions/0145-site-audit-and-phased-releases.md`: §5 (the release process) and the notes at the end of §6.
- `site-audit/phase-5-releases.md`: the Built section.
- `site-audit/phase-6-deploy-from-git.md`: step 2 of 0159.
- `decisions/0139`, `0112` §3, `0135`, `0141`: storefronts, `SitePages`, the Marketplace, publishing from the catalog.
- `commercial.md` §1–§2: what each plan gets.

## 2. Where things are

- **The live site's code** is mirrored in `degraffics/traverse-3way-sync`:
  - `sync-app/` = `app/`, `sync-storage/` = `website/`, `sync-trvgo/` = `trvgo/`.
  - Add it with `add_repo` and clone it shallow. Start every file from its live copy there.
- **This repo** (`degraffics/traversence`) holds the docs, the decisions and the tools:
  - `tools/page-audit/zipcheck.py` (the zip check);
  - `tools/page-audit/deliveries.py` (`record`, `verify`);
  - `deliveries/` (the delivery log);
  - `deliveries/releases/` (each release's record, written by the site itself).

## 3. How a build reaches the site now

1. **Build the zip from the live files:**
   - files at their real paths (`app/…`, `website/…`, `trvgo/…`);
   - only files that differ from live;
   - `app/ui/faq.md` updated;
   - a `release.json` at its top: `{"title", "why", "proposals", "switch"}`. Leave `switch` empty for a fix that goes to
     everyone at once.
2. **Check and log it:**
   - `python3 tools/page-audit/zipcheck.py <zip> --mirror <sync checkout> --docs .` must pass;
   - then `python3 tools/page-audit/deliveries.py record <zip> --mirror <sync checkout> --note "…built on Sync: <time>"`;
   - commit, PR and merge into `main`.
3. **Send it:** send Jason the zip.
4. **Jason's part, on System Configuration → Releases:**
   1. upload the zip;
   2. Approve the plan;
   3. Put it on staging (its checks run there);
   4. Open on staging (his look);
   5. Sign it off;
   6. Publish, or Schedule.
   An urgent fix to the release system itself goes out with **Publish now (urgent)** and a reason.
5. **After he says it's published:**
   - run the sync (`sync.yml` on `main` in traverse-3way-sync; list the runs first, never two at once);
   - `cmp` each file against the mirror;
   - run `deliveries.py verify`, then PR and merge.
   The site writes the release's own record to `deliveries/releases/` (GITHUB_DOCS_TOKEN works).

0159 step 1 turns steps 2–5 of Jason's part into the site manager (upload, look, publish). Step 2 (Phase 6) ends the
uploads.

## 4. What exists that 0159 builds on (all live)

| What | Where |
|---|---|
| Releases: upload, plan, staging, checks, sign-off, publish or schedule, steps (switches), pause, Turn off, Roll back, rollback zip, urgent publish, Pulse case, git record | `app/lib/Releases.php`; the page `tv_admin_mod_releases()` in `app/admin/sections.php`; `website/js/admin-releases.js`; actions in `website/admin/api.php` (`config/releases`); upload target `release_zip` in `app/lib/upload-targets.php`; `app/scripts/releases_tick.php` (every 10 min) |
| Release files on the server | `app/storage/releases/<id>/` (`release.zip`, `new/`, `old/`), never served or synced |
| Staging (staging.trvgo.us): set up, refresh (carried on by `staging_carry_tick`), held email, test accounts, the strip of personal details | `app/lib/StagingSite.php`, `app/lib/Staging.php`, `tv_admin_mod_staging()`, `website/js/admin-staging.js` |
| The way in from the live admin and the staging bar | `Releases::enterUrl()`, `StagingSite::barKeys()`, `website/stg-enter.php`, `website/api/stg-bar.php` (staging), `website/api/release-bar.php` (live), `website/js/stg-bar.js`, `Staging::ribbon()` (injects the bar) |
| Running a release's migrations on staging | `website/api/staging-updates.php` (staging only, its HEALTH_TOKEN) |
| Switches (off, staff and test, a share, everyone; kill) | `app/lib/Switches.php` (`releases` switch on for staff) |
| Pages for us and for businesses | `app/lib/SitePages.php` (Markdown; Draft / Live / Hidden; `owner_id` 0 is Traversence) |
| Storefronts on trvgo.us | `app/lib/Storefront.php`, `trvgo/index.php`, `app/ui/storefront-view.php` (script-free), the portal's Storefront & pages |
| Team roles and the Publish permission | `app/lib/Team.php` (`publish`, `theme`, `catalog` perms) |
| Catalog item versions | `market_item_versions` (0141, publishing from the catalog card) |
| Admin permissions | `Access::can()`; the rows of System Configuration → Permissions in `tv_admin_mod_permissions()` |

Releases so far: #1 `stagingopen`, #2 `stagingguard`, #3 `stagingkey`, all live and published from the page (see
`deliveries/`).

## 5. Step 1 starts here: the way into staging still asks for the password

**What should happen:** Open staging (on System Configuration → Staging or → Releases) opens staging.trvgo.us signed in
as Jason himself (his own account and staff login there; the test accounts are the audit's), with the bar, and no
password prompt.

**What happens:** the browser's password prompt on staging's home page, after `/stg-enter.php` signs him in and
redirects there. The Staging page's Releases line last read: "Its pass rule: 2026-11-13 12:57 UTC not taken: the page
still asked with the pass (401)". That line comes from `STAGING_PASS_TEST` in staging's .env, written by
`StagingSite::barKeys()`.

**Found and fixed so far:**
1. **The cookie rule (form 1):** the web server refused `Require expr`.
2. **The cookie rule (form 2):** the web server didn't take `SetEnvIf` + `Require env`.
3. **Release #2:** PHP became the gate. A self-contained guard file (`staging-guard.php` in staging's own folder,
   written by `StagingSite::writeGuard()`) runs before every script through `.user.ini` `auto_prepend_file`. The web
   server's password rule is left out while the pass is on. `Staging::guard()` (from bootstrap) checks the pass too.
4. **Release #3:** the real reason it all failed. `StagingSite::password()` always adds "-" (for cPanel), and every
   check of the pass takes `[A-Za-z0-9]` only, so the pass was never set. The pass and key are now `bin2hex`. Since #3,
   nobody has confirmed whether Open staging works: **ask Jason first.**

**If it still prompts, find the layer, don't guess.** The session that wrote this can't reach the site, and the network
here can't either. The idea for that:
- **Mark each layer's 401:** add `X-Tv-Stg-Gate: guard-file` to the guard file's 401 and `X-Tv-Stg-Gate: page` to
  `Staging::guard()`'s 401. The web server's own 401 has neither.
- **Add a "Check the way in" button** on the Staging page (live, Founder). It asks staging for `/faq.php` three ways:
  - no credentials;
  - the pass cookie;
  - the checks' own sign-in, the `tv-releases` user, whose password is `site_release_settings.check_pw` on live.
- **Show for each:** the code, `WWW-Authenticate`, `X-Tv-Stg-Gate` and `Server`. Also show:
  - the top lines of staging's root `.htaccess` (the pass's value hidden);
  - whether `.user.ini` and `staging-guard.php` exist;
  - whether `STAGING_PASS` has the right shape (never its value).

**Things to rule out:**
- `.user.ini` being cached (PHP's `user_ini.cache_ttl`, 300 s);
- a parent `.htaccess` above staging's root;
- `Database::loadEnv()`, which only `putenv`s keys not already set;
- `stg-enter.php` only sets the pass cookie `if (Staging::pass() !== '')`.

## 6. 0159's build order (each step a release)

1. **The way in and the site manager** (§5 above; 0159 §5): Releases ▾, Upload (straight onto staging, checks run, no
   Approve or Put on staging), Changes and Note, Publish ▾ (Publish now, Schedule, next step, then Turn off and Roll
   back). Opening a release on staging is the look; Publish is the sign-off. The admin's Releases page stays as the
   record. Make the bar look like the admin command bar (0129), at the top.
2. **Phase 6, deploy from git** (`site-audit/phase-6-deploy-from-git.md`): a finished build becomes a release in git,
   which the sync puts on staging; it shows in the site manager as waiting. Publish deploys exactly its files, and Roll
   back the previous ones. `SYNC_HOME` moves to git, and drift is reported. Agree the switch date with Jason.
3. **The editor's core:** the block tree (sections, then columns, then blocks), rendered on the server, structured
   (grid and flex, never free positions); Edit on a page; Save draft (the draft is the preview); Publish; versions; Roll
   back. Start on block pages and the home page's first editable zone. New permissions `pages.edit` and `pages.publish`
   (Founder only until given to a role).
4. **The full editor:** the widget panel, drag and drop, the block toolbar (Manage, Design, move, duplicate, delete),
   the design panel (alignment, spacing, size, per breakpoint), the three breakpoints, global components (header,
   footer), the live components registry (search box, listings grid, map, guide, engage bar, form, GO Code), SEO fields.
5. **Storefront Studio:**
   - `SitePages` to blocks, each Markdown page kept as one text block;
   - the four themes;
   - commerce-first blocks (0159 §4);
   - the portal's editor;
   - the team role's Publish;
   - the bar on the team's preview;
   - storefront pages script-free on trvgo.us.
6. **AI writing:** a "write this for me" button on text blocks. For our pages, inside the AI monthly cap; for
   businesses, by plan (none at Free).

## 7. Open, for Jason

- Scheduling for shops (0158 §6): he decides once he sees the need.
- The first coded page to get editable zones: the home page is suggested.
- Phase 6's date: with step 2, unless he says otherwise.

## 8. Also waiting (not 0159, but known)

- **Phase 3:** the 11 staging paths that change something (sign up, like, claim, edit a listing, a market post, message
  a business, join a group, and the staff ones) have their steps written in `tools/page-audit/paths.mjs` but no runners.
- **Scheduled runs:** GitHub skipped the 2026-11-13 06:00 UTC sync and the 09:43 site checks. If they keep skipping, look
  at the workflows' schedules.
- **Path failures on live** (`site-audit/paths.md`):
  - the directory's place box gives no distance to set;
  - a suggested journey's FAQ link once didn't answer;
  - one guide journey takes 7 taps.

## 9. Testing locally

- **PHP and MariaDB** are in the container.
  - Start a database with `mariadbd --user=root --datadir=<dir> --socket=<dir>/s.sock --skip-grant-tables`, after
    `mariadb-install-db` the first time.
  - Copy the live `app/lib` into a test tree, and stub what you don't load.
- `Releases` was tested this way, end to end: upload, refusals, approve, urgent publish, steps by `tick()`, the
  settings, Turn off, Roll back, the rollback zip, signatures, the bar's permissions, the out-of-date check, and
  sign-off after a look.
- The staging guard file was tested with PHP's own server and `-d auto_prepend_file=`.
