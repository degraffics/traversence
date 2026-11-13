# Working notes for Claude sessions on this repo

This repo (`degraffics/traversence`) is the durable record of Traversence's governance and technical docs: charter, PRD,
commercial, architecture, brand, phases, regions, routes, workflow, future-considerations, `decisions/*.md`,
`traversence-platform-spec.md`, and Claude Docs exports in `claude-docs/`. The site's code lives on the server and in
OneDrive, mirrored to `degraffics/traverse-3way-sync` (below). Rules marked "every build" apply to every delivery.

## Pushing from a cloud session

Git goes through the session's proxy, which adds the credential itself: plain `git push`/`git pull`, no tokens. If a push
fails with "access denied by the git proxy: … not in this session's authorized repository set", the repo isn't
authorized for *this* session (nothing in the repo or gitconfig fixes it). Don't retry or route around it. Commit
locally anyway, and tell Jason his options: add `degraffics/traversence` to the session's repositories, give a personal
access token with push rights, or push from his own computer. Authorization is per session, so try the push rather than
assume.

## One copy: all work ends up in `main` (Jason, 2026-11-01): every session

`main` is the single source of truth; split branches leave information out of step and behavior inconsistent.
- **Start from the latest `main`.** Fetch `origin/main` and base the session's branch on it. Work on the given branch
  that isn't in `main` gets merged or rebased first, never thrown away.
- **Finish in `main`.** When done (or Jason says "push", "wrap up", "merge it"): commit, push the `claude/…` branch, open
  a PR into `main` (or update the open one) and merge it. Only one open PR at a time; merge an earlier one first.
- **Check:** `git merge-base --is-ancestor origin/<branch> origin/main`, then tell Jason plainly the work is in `main`.
  Merged branches can be deleted on GitHub (Branches → trash); if that's blocked, leave it to Jason.
- If the merge can't happen (blocked push, conflict, failed check), say what's still outside `main`. Never call work
  saved when it's only on a branch.

## Build on what's there (Jason, 2026-11-10): every build

Before changing an area, read its decisions and the live code. Earlier work is kept: a change extends or replaces it in
place, never drops it. Going against an earlier decision needs Jason's OK first, and the decision gets a note. The zip
check blocks a zip that removes a live function, constant or FAQ question; `--drops` names one only after he agrees.

## Keep this file lean

It loads in full every session, and so does `README.md` (imported below). One short rule per point; the detail lives in
the decision it cites. Remove rules a later decision replaces. Keep the README a short map of the repo, updated when
files are added, moved or removed.

@README.md

## The 3-way sync: server ⇄ OneDrive, recorded in GitHub (Jason, 2026-10-28): every build

`degraffics/traverse-3way-sync` keeps the server (FTP) and OneDrive `Traversence/website` + `Traversence/app` the same,
at 6:00 and 18:00 UTC or on demand (its workflow's Run button), committing the result to `sync-storage/` (website),
`sync-app/` (app) and `sync-trvgo/` (trvgo.us, since 2026-11-01).
- **What's live** is those folders on that repo's `main`, not a copy from an earlier session. Compare before building.
- **Delivering:** Jason uploads to the server; the sync updates OneDrive. The server wins a two-sided change
  (`SYNC_HOME=ftp`). A zip holds **only files that differ byte for byte from the live mirror**, never a blanket upload.
  Dotfiles (`.htaccess`) aren't mirrored, so name them separately when they change.
- **Several sessions at once** (Jason, 2026-11-01): current first, so nothing is rebuilt.
  - **Before writing any file for a zip**, fetch the mirror and start every file from its live copy, never from a copy
    held earlier in the session. If Jason uploaded anything since the newest sync, run the sync first.
  - **Just before zipping**, fetch once more. A file changed there since gets only this build's change re-applied
    (most often `faq.md`, `manifests.php`, `tools.php`, `Pulse.php`, `section-rails.php`, `portal.php`, the router).
  - The hand-off names the sync it's built on ("built on 17:44 UTC") and lists the zip's files. Never build on another
    session's zip that isn't live yet. One upload at a time, the sync run after each.
  - **Before a zip goes out** (decisions/0145): `tools/page-audit/zipcheck.py <zip> --mirror <checkout>` must pass (no
    new blocking failures), and `tools/page-audit/deliveries.py record` logs it, so a missed file shows as pending. It
    also blocks a mirror behind its `main` and a file another session's pending zip carries (Jason, 2026-11-11).
  - **Claude runs the sync** (Jason, 2026-11-01): when Jason says he's uploaded, run the workflow (`sync.yml` on `main`),
    wait for its "Sync: …" commit, and check the mirror matches the zip byte for byte.
  - **One sync at a time, never a pile-up** (Jason, 2026-11-11, after a 35-minute stall): GitHub keeps one waiting run
    per group, and each new request cancels the one already waiting, so sessions requesting at once cancel each other.
    Only the session whose zip was uploaded starts the sync. First list the runs: one queued or running already covers
    the upload (wait for its commit; never start another). One running over 15 minutes with its job finished is stuck:
    cancel it; if it still shows running, rename `concurrency.group` in `sync.yml` (PR, merge) and start one run. No
    commit after 10 minutes: list the runs again before anything else.
- **Deleting** on one side deletes on the other (OneDrive recycle bin; server copy in the sync repo's `deleted/`).
  Claude can delete in OneDrive via the Microsoft 365 connector. Never re-send a deleted file. More than max(10, 5%)
  deletions in a run wait for `allow_mass_delete`.
- **Never in a zip:** `bootstrap-super-admin.php` (retired) and anything under the archive register's "Removed".
- **Never synced:** dotfiles (`.env`, `.htaccess`), `app/storage/`, `_notes`, `_SUPPORT`, `_pending`, `_concepts`,
  `error_log`, `bootstrap-super-admin.php`.

## Files used once, and the archive (Jason, 2026-10-28): every build

- **Migrations run themselves** (2026-10-30): upload the file; the site runs new ones oldest first within the hour, or
  when System Configuration → Database updates opens, logs them (`db_updates_log`) and deletes them. A failed one holds
  the rest until replaced. So each must be safe to run twice and need no person; zips carry only new ones; no phpMyAdmin
  steps in delivery notes.
- Test files, dumps and one-off scripts never go in `website/` or `app/`; delete them once used.
- **Archived pages** (out of menus, still reachable) are listed in `archive.md` with what replaced them. Remove one only
  when Jason clears it, then move it to the register's "Removed" in the same build.
- **Dead code** (nothing calls it, checked against the live mirror) is deleted; the sync history keeps old versions.

## The crawler, research and guides: decisions/0133 rules: every build

`decisions/0133` is the one rule book for the crawler and what it feeds (listings, landmarks, dossiers, research, guides,
stories): curators, not controllers (no topic left out for being sensitive; only exposure and representation managed);
"tell the story, never the way in"; never overwrite what a person entered; AI acts on a person's words only when called;
the licence decides the use. Where older crawler records disagree, 0133 wins.

## Folders: website/, app/, trvgo/ (decisions/0069, 0094)

- `website/` is the web root: pages, endpoints (`api/`, `api/crawl/`, `api/auth/`, `user/api/`, `listing/api/`), `js/`,
  `image/`, `uploads/`. Never backend code, SQL, notes or dumps.
- `app/` is never served: `lib/` classes, `data/` word data, `ui/` shared page parts (shell, header, engage bar,
  `faq.md`), `bootstrap.php`, `middleware/`, `migrations/`, `scripts/`.
- `trvgo/` is the trvgo.us document root: the router (`index.php`, `.htaccess`) for short codes, GO Codes and businesses'
  storefronts (decisions/0139); storefront pages are drawn by `app/ui/storefront-view.php`. Mirrored to `sync-trvgo/`.
- Pages reach `app/` by relative path (`__DIR__ . '/../app/ui/header.php'` from the root). Zips hold the folders at real
  paths. Notes folders (`_notes`, `_SUPPORT`, `_concepts`, `_pending`) go in neither.

## The admin (decisions/0109): every build

10 modules (Pulse is part of the Dashboard since 2026-11-12) at `/admin/<module>/<section>[/<id>]`, drawn by `website/admin/router.php` from `app/admin/manifests.php`, one
endpoint `/admin/api/<module>/<tool>`.
- No admin page outside the manifests: a new tool is a section there (a list also a tool in `app/admin/tools.php`),
  built from widgets (table, pipeline, metrics, detail drawer, quick task).
- Stages are workflows (System Configuration → Workflows), not hard-coded; money and data moves go through the owning
  library (`app/admin/workflows.php`).
- Access: `Access::can()` / `Modules::canTool()`. Disallowed actions are greyed out with the reason, never silently hidden.
- Another module's tools open in a drawer (`at_hand`), never by leaving the page. Old pages are archived, not deleted.

## Record pages: the command strip (decisions/0129): every admin record page

`tv_command_strip()` (`app/ui/command-strip.php`, `js/tv-strip.js`) replaces the title and breadcrumbs: one slim line
in the admin command bar's look that stays at the top as you scroll (Jason, 2026-11-12).
- Left: Back and the short name ("Order #9"), no pills. Right: **Save** (with a count) when changed, else **Close**, ▾
  for Save and close, Send, Close; **☰** for other actions and Shortcuts (they trigger the page's own buttons).
- Where the record stands is a notices box under the strip (`news`), closed with ×, back only when it changes. Nothing
  shown there is repeated below.
- Each `h2.hb-h` section folds with a ▾ on its heading (the heading toggles it; remembered), and shows an unsaved dot. Section buttons name the section ("Save work order"); never a
  bare "Save and close" inside one. `'new'` forms and `data-confirm` buttons wait for their own button; searches `'skip'`.

## Keep the FAQ current (decisions/0057): every build

`faq.md` ships as `app/ui/faq.md` and renders at `/faq.php`. Every build adds or updates entries for what changed, from a
visitor's view, saying "coming" for what isn't built; include it in the zip. Anchors come from the question wording, so
check `grep -rn "faq.php#"` before rewording one.

## Phone layout and visual standard (decisions/0058 §11): every page

- **Edge to edge below 768px:** cards and panels 3px from each side and each other, `.5rem` corners; text keeps a small
  margin. `app/ui/app-shell.php` handles known card classes; others add `class="tv-bleed"`. No per-page negative margins.
- **Card anatomy:** the name is the link, top left, brand brown `#92400E` underlined; Link button top right; actions
  (Call, Directions, See on map, Website, Contact, Peek) as 30px round icon buttons in a row on the name's line, left of
  Link (under the name on phones), with `title`/`aria-label`, no text, icons `#B45309`; details across the card below.
  Card grids page by whole rows (`js/tv-pager.js`).
- **Icons** are the brand's flat SVGs (`tv_icon()`), never emoji. Active: sage `#8FBCA8` with a soft glow; rest: cream
  with a brown ring.
- **No breadcrumbs** on content pages (the way back up is `js/tv-trail.js`'s route, 2026-11-12); kind, category, agency, name and activity pills search for more
  (`TvSearch.open('<words> near <place>')`).
- **Filters** live in the search (foot of Quick Search, §39), showing only active ones with ×. Get Local's directory is the
  exception: its own **Find a business** box and Refine row (2026-11-12, decisions/0058 note). Filters on a list or queue
  are one dropdown button like Shortcuts (icon, current choice, count, ▾), not rows of pills.
- **Phone menus:** ☰ tools drop-down left, profile and role right.
- **Count outbound taps:** `data-out="call|directions|website|contact" data-e="<listing id>"` (counts only, never who).
- Check every page at 390px: 3px card edges, no sideways scroll.

## Content pages (decisions/0058 §20, §21): every build

Listings, outdoor places, experiences, places, journeys and any new content kind:
- **Hero** (`tv_explore_hero()`) flush under the tool bar for places, listings and profiles (pass `heading`, `slides`,
  `manage` for the owner).
- **Header:** kind or category (searches), the title (not a link), the place, Link button top right (`data-link` +
  `js/link-button.js`). No breadcrumbs.
- **Engage bar** under it: `tv_engage_bar($pdo, $kind, $ref, ['title' => …])` (`app/ui/engage-ui.php`).
- **Comments** at the end: `tv_engage_comments($pdo, $kind, $ref)` (public, 18+, reportable).
- **Views:** `tv_count_item('<kind>:<id>')`. Maps opened as their own view pass `panel: true` to `TvMap.open()`.

## Pulse (Jason, 2026-11-01): every build

A step doable from the case is a button that does it (`['do', …]` / `['form', …]` → `Pulse::manage`), not a link. A step
needing an outside service (Railway, QuickBooks, AWS, Cloudflare…) carries its link (`Pulse::SERVICES`, the step's
`ext`); never "go to Railway" without it. One case per kind of chore, a step per item.

## No browser dialogs; one look for notices (`js/tv-ask.js`): every page

Never `alert()`, `confirm()` or `prompt()`.
- Needs a yes: `data-confirm="Hide it from the site?" data-yes="Hide it"` (+ `data-danger` for removing or hiding); in
  code `if (!TvAsk.gate(btn, 'Remove this comment?', {yes: 'Remove', danger: true})) return;`. Also
  `TvAsk.confirm(msg, {anchor})`, `TvAsk.ask(label, value)` (one-field form).
- **Notices:** `TvAsk.say(msg, {anchor, bad})`, green ✓ "Success:" / red ! "Error:" beside `anchor`, else a centered
  pop-up. After a form post, the page's message carries `data-tv-flash="ok|error"`. No other success/error boxes.
- **Status lines** are `<p data-tv-status="ok|info|bad" data-tv-label="All clear:">…</p>`, never plain colored text.
- **Peek** at a page without leaving: `<a href="…" data-peek-frame>` or `TvAsk.peek(url, title)`.
- **Busy** (Jason, 2026-11-02): any click that doesn't finish at once shows a spinner on what was clicked,
  `TvAsk.busy(btn, true)` before the request and `false` when it answers, then the answer as the site's notice
  (`TvAsk.say`: beside it, or the centered pop-up when there's no room, as in a Pulse case). Never a button that greys out
  and looks dead.

## Words and lists (decisions/0144, Jason, 2026-11-04): every form

Fields of words (names, descriptions, notes) get spell check and the site's suggestions: `data-spell` on an input (a
textarea gets them unless `spellcheck="false"`); codes, amounts and dates don't. A list whose choices need explaining
carries each choice's meaning (`[key, name, what it is]`), shown by the kit's ⓘ.

## Never a dead end (Jason, 2026-11-09): every page

Wherever a link, code or step stops working (expired, replaced, over a limit, an email that failed), the page offers a
way forward: send it again, and ask our team (a form to our inbox), with our email address. Never a lock-out.
Never offer someone what their account can't use, or should use elsewhere (Jason, 2026-11-11): show the version for
who they are (staff, a business, a member, a guest), or point straight to where theirs is.

## Times where they happened (Jason, 2026-11-10): every page that shows a time

Store the moment and the device's time zone (`Intl…timeZone`, sent with the action). Show it in that zone, always labeled
("Nov 9, 10:00 AM PST"), with `<time datetime="ISO" data-tv-time data-tz="…">` or `TvTime.fmt()` (`js/tv-time.js`);
with no zone stored, in the viewer's. Rolling out page by page from Scan cards.

## Places in search (Jason, 2026-11-10): every build

A city named in a search is always found in the locations data (`zip_coordinates`) and becomes the place, with or
without our data or a hub there: every search is demand. Older rules that hid out-of-area towns ("strict", "names
something here") no longer apply to cities; an unknown word is never dropped before the place is read. One exception: the
whole search is the exact name of something we have ("Four Corners Monument"). While typing, a name of two words or more that
starts a name we have is suggested wherever it is, even when the words also read as a town.
Places come from sources, never hand lists in code: a name searched and not found is looked up (decisions/0148).
OpenStreetMap is its own layer (decisions/0065): its own tables, credited, never copied into our records, so nothing is
shared back; within it, show all it gives. Nothing of ours in reach: its places at once, and the town to the crawler (0149).
"Near me" is the nearest first, no cap, with miles; fewer than 3 within 5 mi (30 for the outdoors) says results are lacking
and tells the team (decisions/0060).

## No test moves real money (Jason, 2026-11-10): every build

Test accounts, test orders ("TEST · …" copies, sandbox orders) and test posts never reach a live gateway: anything that
charges, invoices or records a payment goes through `QuickBooksPay::guardReal()` / `testHere()` (decisions/0145).

## Password boxes (`js/tv-password.js`, Jason, 2026-10-16): every page

Every password box gets Show/Hide; new-password boxes (`autocomplete="new-password"`, `minlength`, optional
`data-pw-not`) get a live checklist, and the confirm box `data-pw-match="#id"`. The form won't send until it's ticked.
One-time secrets stay hidden until asked for, with an offer of a new one.

## One uploader (decisions/0127): every build

Every upload goes `js/tv-upload.js` → `/api/upload.php` → `app/lib/Uploads.php`. A new place to upload is a target in
`app/lib/upload-targets.php` (`check`: who, kinds, size, staging; `done`: the owning library handles the file). In pages:
`TvUpload.send(files, {target, ctx, el})` or `TvUpload.bind(input, …)`. Forms with other fields send the file first, then
the form with its id. Before delivering, `grep -rn '\$_FILES\|is_uploaded_file\|move_uploaded_file' app website` may
match only `app/lib/Uploads.php` and `website/api/upload.php`.
Every file input opens the Gallery panel (`js/tv-art.js`, decisions/0143). Staff have no personal Gallery (Jason,
2026-11-05): on admin pages files belong to the record (an order, a business), never to the staff member.

## Links past Bluehost's ModSecurity: every request

A field holding a link (`…?id=…`) gets "Not Acceptable!", so links travel wrapped. POST forms: automatic (`tv-ask.js`
wraps, `app/bootstrap.php` unwraps). JSON `fetch`: `JSON.stringify(window.tvSafe ? tvSafe(body) : body)`, read with
`Response::jsonBody()`. Endpoints reading `php://input` themselves pass it through `Response::unwrap()`.

## Tribal nations' land (decisions/0066): every build

- **Public:** boundaries, key resources and infrastructure, public listings and businesses, publicly known places, with
  the nation's name (`Nations::label()`) and visitor rules (`Nations::rules()`).
- **Held, never shown:** names that may mark sacred, burial or protected sites (`Landmarks::SENSITIVE`) and what's
  specific to the nation (`Landmarks::NATION_SENSITIVE_CLASSES`), until the nation's own tools exist
  (`Landmarks::NATION_TOOLS`) and it says. A place held only by a word, off a nation's land, that is plainly public (its own
  article, open to visitors) shows, unless it speaks of the sacred or burials (`Landmarks::publiclyKnown()`, 2026-11-12). "Closed by the nation" reopens only at its request. Never delete the data.
- Anything about a user or owner is private unless they say otherwise.
- All landmark reads go through `Landmarks::visibleSql()` / `visible()`; pins on nation land (`Nations::pin()`) move to a
  public place.

## Migrations for Bluehost's MySQL: every build

Run by `DbUpdates::autoRun` (split on `;`): no `DELIMITER`, no stored procedures. MySQL rejects `ADD COLUMN/INDEX IF NOT
EXISTS` and `DROP … IF EXISTS` inside `ALTER TABLE`; `CREATE TABLE IF NOT EXISTS` is fine. Guard with `information_schema`:

```sql
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE t ADD COLUMN c INT DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't' AND COLUMN_NAME = 'c');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
```

(Indexes: `information_schema.STATISTICS`.) Tables may have moved to the admin database (decisions/0110,
`DataMove::GROUPS`, e.g. `products`): find the table first and name its database (see
`app/migrations/2026-12-23_promotions.sql`).

## cPanel through its API (`app/lib/CPanel.php`, Jason, 2026-10-28): every build

New cron jobs go in `Scheduler::JOBS` and `Scheduler::AUTO`; `Scheduler::sync()` adds them within the hour. Mailboxes via
`CPanelMail`. Never send Jason into cPanel, except for `.env` secrets (File Manager) or what the API can't do (say why).

## No income, no expense (Jason, 2026-11-03): every build

Nothing we build spends Traversence money (a per-use API, email, text, push, ID check, AI call) unless revenue covers
it: inside a paying plan's allowance, a paid top-up, or a fee charged first. Free tiers get only what costs us nothing,
except free members' Moments, Snaps and Keeps, paid from the community budget (3% of revenue, $25 floor; decisions/0154).
System emails the site sends itself (links, notices, work orders) cost nothing and aren't limited by this (Jason, 2026-11-09).
The one exception: Claude writing guides and journeys, inside the AI monthly cap (decisions/0131, 0147: on-demand
builds up to 50% of the daily allowance, the rest queued). Also a place's description, written once when someone opens a place with no
Wikipedia summary, kept as research (decisions/0148, `place_about`).

## The live server's PHP version: every build

The real version is in `site-audit/results/server.json` (from the health report, daily; site audit P1); the code check (R04)
flags anything newer. Until it's recorded, write to PHP 8.0: no `readonly`, `enum`, first-class callables (`strlen(...)`),
`new` in parameter defaults, `array_is_list()`, `json_validate()`, typed class constants, or standalone `true`/`false`/`null`
types. A helper from `app/ui/app-shell.php` (`tv_icon`, `tv_count_item`…) is called only after `tv_page_start()`.
