# Hand-off: build decisions/0159, the builder

## 0. Where the build stands (2026-11-13, written by the session that built steps 1–3's core; start here)

**Done and live** (each verified byte for byte against the mirror, recorded in `deliveries/`):
- **Step 1:** the way into staging (releases #4 Check the way in, #5 the fix: staging's private folder rule had shut its
  pages) and the **site manager** at the top of staging (#6, #8, #11, #15): Releases ▾ (Check for builds first, and when
  the site last looked by itself), Upload, Review, Changes, Note, Publish ▾. It refreshes itself every minute.
- **Step 2, builds through git** (#7, #10, #11): `tools/page-audit/send.py` → `releases/inbox/` on main → the live site
  takes it (`releases_tick` every 10 min, or Check for builds) → staging, checks → **publishes itself** when they pass
  (Jason: no second Publish). `"review": true` in release.json → email + Pulse case → UI/UX/Functional on staging → Publish.
  CLAUDE.md's delivery rules say all this. Other sessions use it (#9, #12–#14, #16, #17 were theirs).
- **Drift report** (this repo, no site code): `tools/page-audit/drift.py` in `site-audit.yml` after each sync; base
  recorded at Sync 15:41 UTC; open drift → Pulse Site health, rule D06 (`findings.py`). Reports only.

**Just landed:** `editor_20261113` = **release #18** (step 3, the editor's core), built on Sync 15:59 UTC, taken from git
and published itself at 16:16 UTC; all 8 files verified live at Sync 16:18 UTC (no drift). Nothing of this build is in flight.
Its files: `app/lib/Blocks.php` (new), `website/api/blocks.php` (new), `website/js/tv-editor.js` (new),
`app/lib/upload-targets.php` (target `block_image`), `app/admin/sections.php` (Permissions rows `pages.edit`,
`pages.publish`), `website/index.php` (zone `home.after-doors` under the four doors, empty until published),
`website/pages/view.php` (zone `page.<id>` under each page's text), `app/ui/faq.md` ("How do I change what a page says?",
drift line). Not yet tried by Jason: he (Founder, signed in with his staff login) should see the outlined zone and **Edit** on the home
page under the four doors (live site, or staging); ask him first to add a heading, Save draft, Publish, then Versions → Roll back once.

**Next, in order:**
1. Step 2's last part: the sync's direction (`SYNC_HOME` to git, in traverse-3way-sync's `sync.py`), so OneDrive or
   server edits stop going live without a release. **Agree the date with Jason first**; the drift report shows them now.
2. Step 4, the full editor (0159 §3–§6): widget panel and drag and drop, the block toolbar, the design panel (alignment,
   spacing, size per breakpoint), three breakpoints, global components (header, footer), the live components registry
   (search box, listings grid, map, guide, engage bar, form, GO Code), SEO fields. Build on `Blocks` (tree:
   sections → cols → blocks; `clean()` is the one gate for what's kept; `render()` server-side).
3. Step 5 Storefront Studio (SitePages to blocks: each Markdown page as one text block; themes; the portal; team
   Publish), step 6 AI writing.

**Open, for Jason:** the `SYNC_HOME` date; which coded pages get zones next (he chose the home page first); scheduling
for shops (0158 §6).

**Known issues:**
- The 10-minute pickup is irregular (pickups seen at :28, :38, :57; some runs seem skipped). The site manager shows
  "The site last looked by itself …" under Check for builds; a ⚠ past 25 min. If it keeps skipping, look at the
  `releases_tick` cron line (`Scheduler::AUTO`, System Configuration → Scheduled tasks).
- After Check for builds, the first line still says "Look at it on staging, then Publish" (from `straightOn`) even when
  the next line says it published itself. Cosmetic.
- The editor panel's header wraps on a computer (Jason's screenshot, 2026-11-13, first live use): at 27rem, Save draft,
  Publish and Versions fill the row and ✕ drops to a second line over the status text. Give ✕ its own corner (or put
  Versions in a ▾) when step 4 reworks the panel. The zone's state pill also sits under the panel while it's open.
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
