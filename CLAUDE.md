# Working notes for Claude sessions on this repo

## Pushing to GitHub from a cloud Cowork/Claude Code session

This repo (`degraffics/traversence` on GitHub) is version-controlled here as
the durable backup of Traversence's governance and technical documentation
(charter, PRD, commercial model, architecture, brand, phases, regions,
routes, workflow, future-considerations, `decisions/*.md`, the live
`traversence-platform-spec.md`, and Claude Docs exports under `claude-docs/`).

Cloud sessions reach GitHub through a local egress proxy. Two environment
variables (`GH_TOKEN`, `GITHUB_TOKEN`) show up as `proxy-injected`, and git is
configured to rewrite both SSH remote forms (`git@github.com:...` and
`ssh://git@github.com/...`) to HTTPS so the proxy can intercept the request
and inject a credential automatically — normal `git push`/`git pull` over
HTTPS, no manual token handling.

**The injection is gated per session, per repo.** A push can still fail with:

```
remote: access denied by the git proxy: <owner>/<repo> is not in this
session's authorized repository set, so the proxy will not inject a
credential for it. To fix, add the repository to the session's sources.
```

This means the proxy mechanism exists but this particular repo hasn't been
authorized for *this* session. It is not a token, config, or gitconfig
problem in this working tree — nothing in this repo or in `~/.gitconfig` can
fix it. If you hit this:

1. Don't retry the push as-is or try to route around it (no alternate auth,
   no disabling the proxy) — same guidance as any other 403 from the agent
   proxy (see `/root/.ccr/README.md`).
2. Tell Jason plainly that the push is blocked on repository authorization
   for this session, and give him the options:
   - Add `degraffics/traversence` to this session's authorized repository
     set (however that's surfaced in the Cowork/Claude session UI — this
     hasn't been pinned down from inside the session itself).
   - Supply a personal access token with push rights, and push with that
     directly instead of relying on the proxy.
   - Push from his own machine instead, if a folder/computer is linked via
     the remote-devices bridge and has its own git/GitHub access.
3. Local commits are safe either way — commit normally in the working copy
   even if the push has to wait. Don't let a blocked push stop you from
   getting the work committed.

Authorization is scoped to the session, so a prior successful push does not
guarantee the next session (or even a later point in the same one, if it
gets recreated) will have the same repo authorized — check by attempting the
push and reading the error, rather than assuming.

## Two folders: website/ and app/ (decisions/0069)

The site is two folders, side by side on the server and in OneDrive `Traversence/`:
- `website/`, the web root: pages, endpoints (`api/*.php`, `api/crawl/`, `api/auth/`, `user/api/`, `listing/api/`), `js/`,
  `image/`, `uploads/`.
- `app/`, never served:
  - `app/lib/` the classes;
  - `app/data/` the word data;
  - `app/ui/` the shared page parts (app shell, header, page shell, engage bar, `faq.md`);
  - `app/bootstrap.php`, `app/middleware/`;
  - `app/migrations/` the SQL;
  - `app/scripts/` command-line tools.

A third folder, `trvgo/`, is the document root of the trvgo.us addon domain (decisions/0094): only the short-link
router (`trvgo/index.php`, `trvgo/.htaccess`), reading `app/` by relative path. Nothing else goes in it.

Never put backend code, SQL, notes or dumps in `website/`. Delivery zips hold both folders at their real paths. Pages
reach the backend by relative path: `__DIR__ . '/../app/ui/header.php'` from a root page,
`__DIR__ . '/../../app/lib/Auth.php'` one folder down. Notes (`_notes`, `_SUPPORT`, `_concepts`, `_pending`) don't
belong in either folder.

## The admin: modules, sections, widgets, workflows (decisions/0109): every build

The admin is 11 modules (AOS §8) at `/admin/<module>/<section>[/<id>]`, drawn by `website/admin/router.php` from the
manifests in `app/admin/manifests.php`, with one endpoint `/admin/api/<module>/<tool>` (`website/admin/api.php`).

- **No admin page outside the manifests.** A new admin tool is a section in `app/admin/manifests.php`; a list is also a
  tool in `app/admin/tools.php`. Build it from widgets (table, pipeline, metrics, detail drawer, quick task), not as a
  new page in the menus.
- **Stages are workflows**, edited in System Configuration → Workflows, not hard-coded. The protected moves (money,
  data) go through the library that owns the record (an adapter in `app/admin/workflows.php`).
- **Access follows the tool:** `Access::can()` / `Modules::canTool()`. What someone may not do is greyed out with
  the reason, never hidden without saying.
- **Tools from another module open in a drawer** over the work (a module's `at_hand`), never by leaving the page.
- **Old pages are archived:** out of the menus, still there by direct link and in the site map. Don't delete them.

## Keep the FAQ current (decisions/0057)

`faq.md` is the site's FAQ and its only source: it ships as `app/ui/faq.md` (decisions/0069) and renders at `/faq.php`.
With every build, add or update its entries for whatever changed. Write them from a visitor's point of view, describe
what the site actually does, and say "coming" for anything not built yet. Include the updated `app/ui/faq.md` in
the delivery zip. Question anchors come from the wording, so rewording a question breaks links to it; check with
`grep -rn "faq.php#"` first.

## Phone layout and visual standard (decisions/0058 §11): apply to every page, every build

Space on a phone is at a premium. Every page, new or changed, follows this:

- **Edge to edge.** Below 768px, cards, lists of cards and panels run edge to edge with **3px** each side and **3px**
  between them, with small corners (`.5rem`). Text (headings, paragraphs, labels) keeps a small margin. The page
  frame (`app/ui/app-shell.php`) does this for the known card classes and Tailwind `rounded-xl/2xl border` cards;
  anything else opts in with `class="tv-bleed"`. Don't add per-page negative margins.
- **Card anatomy:**
  - the **name is the link**, top left, in brand brown (`#92400E`, underlined);
  - the **Link button** is in the top-right corner;
  - the **actions** (Call, Directions, See on map, Website, Contact, Peek) are a column of **30px round icon
    buttons down the right edge, under the Link button**, with `title`/`aria-label` and no text, icons in
    `#B45309`;
  - the **details** sit on the left.
- **Icons are the brand's flat SVGs** (`tv_icon()`), not emoji, for anything you tap. The active state is sage
  `#8FBCA8` with a soft glow, like the top bar; the rest state is flat cream with a brown ring.
- **No breadcrumbs on content pages.** The page says where it is. Its kind, category, agency, name and activity
  pills **search for more** (`TvSearch.open('<words> near <place>')`).
- **Filters inline with a list** (a queue, a table) are a **dropdown button like Shortcuts** (Jason, 2026-10-21): a rounded
  cream button with an icon, the current choice and its count, and ▾, opening a list of each option with its icon and count.
  Not rows of pills or bare icon buttons.
- **Filters live in the search** (the foot of its Quick Search, decisions/0058 §39), not in rows on the page. Show only active filters, each with ×.
- **Phone menus:** the ☰ tools button with a drop-down list (left), the person's profile and role (right).
- **Count outbound actions** with `data-out="call|directions|website|contact" data-e="<listing id>"`. This stores
  counts only, never who tapped.
- Check every page at 390px wide before delivering: card edges at 3px, no sideways scroll.

## No browser dialogs: every page, every build

Never use `alert()`, `confirm()` or `prompt()` (the browser's grey boxes). Use the site's own, from `js/tv-ask.js`
(loaded on every page by `app/ui/header.php`):
- a form or submit button that needs a yes: `data-confirm="Hide it from the site?" data-yes="Hide it"` (add `data-danger`
  for anything that removes or hides), shown inline under the button;
- in a click handler: `if (!TvAsk.gate(button, 'Remove this comment?', { yes: 'Remove', danger: true })) return;`;
- `TvAsk.confirm(msg, {anchor})` → Promise, `TvAsk.say(msg, {anchor})` for notices (a toast without an anchor),
  `TvAsk.ask(label, value)` → Promise for a one-field lightbox form.
- **notices** (Jason, 2026-10-28): `TvAsk.say(msg, {anchor, bad})` is the one look for good and bad news, a green ✓
  "Success:" or red ! "Error:" with ×, beside what was done (`anchor`), else a pop-up in the middle that the background or
  × closes. Pass `bad: true/false`. A message a page prints after a form posts carries `data-tv-flash="ok|error"`, and it
  shows beside the form that was sent. No other success/error boxes or toasts.
- **status lines** (Jason, 2026-10-28): a line a page shows about where things stand ("No listings need you", "3 were
  merged", "The crawler is still working on…") is never plain colored text: `<p data-tv-status="ok|info|bad"
  data-tv-label="All clear:">…</p>` draws it as the same notice box in its place (green ✓, amber i for info, red !).
- to show a page without leaving the one you're on (checking a listing from Review): `<a href="…" data-peek-frame>` or
  `TvAsk.peek(url, title)`, a pop-up card with the page in its embedded view and "Open the full page" in its corner.

## Password boxes: every page, every build (Jason, 2026-10-16)

Every password box gets a Show / Hide button, and every new-password box gets a live checklist under it. Both come from
`js/tv-password.js`, loaded on every page by `app/ui/header.php` and `app/ui/page-shell.php`:
- a box for a new password: `autocomplete="new-password"` and `minlength="N"` (add `data-pw-not="<text>"` for something
  it mustn't contain, e.g. the staff ID);
- its confirm box: `data-pw-match="#first-box-id"`;
- the form can't be sent until the list is ticked (the list turns red; no browser pop-ups).
Secrets shown once (authenticator keys, setup links) stay hidden until asked for, and the page offers a new one, never "open a
private window".

## One uploader for every file (decisions/0127): every build

Every file anyone sends the site goes through one path: `js/tv-upload.js` in the browser (loaded on every page by
`app/ui/header.php` and `app/ui/page-shell.php`), `/api/upload.php`, and `app/lib/Uploads.php`. Never a page or endpoint
of its own that takes `$_FILES`, `is_uploaded_file()` or `move_uploaded_file()`, and never a `multipart/form-data` form
that posts files anywhere else.
- **A new place to upload is a target** in `app/lib/upload-targets.php`: `check` (who may, what kinds, the most it takes,
  where it's staged) and `done` (what happens to the file that arrived: `$u->moveTo()`, `$u->local()`, `$u->asFile()`).
  The library that owns the record does the work (Gallery, ClientOrders, BizFiles…), as for any protected move.
- **In the page:** `TvUpload.send(files, {target, ctx, el})` or `TvUpload.bind(input, …)`; the result shows beside `el`
  (`TvUpload.reloadHere(message, el)` after a reload). Size checked first, over 10 MB asks, a progress bar, S3 in parts
  or pieces through the server when S3 is off. Up to 1 GB a file; a target may take less.
- A form that has other fields as well sends the file first, then the form with the id it got back (labels on an order's
  shipment, a GO Code's logo, a journey's photos after the journey is saved).
- Check with `grep -rn '\$_FILES\|is_uploaded_file\|move_uploaded_file' app website` before delivering: only
  `app/lib/Uploads.php` and `website/api/upload.php` may match.

## Links past Bluehost's firewall (ModSecurity): every form and request, every build

Bluehost's ModSecurity rejects a request ("Not Acceptable!") when a field holds a link like `…/profile.php?id=…`. Links
travel wrapped and are unwrapped on arrival, so code never sees the difference:
- ordinary POST forms: automatic (`js/tv-ask.js` wraps any field containing `://` or `www.`; `app/bootstrap.php` unwraps `$_POST`);
- `fetch` with JSON: send `JSON.stringify(window.tvSafe ? tvSafe(body) : body)`; `Response::jsonBody()` unwraps it;
- an endpoint that reads `php://input` itself must pass the decoded body through `Response::unwrap()`.

## Content page standard (decisions/0058 §20): every content page, every build

Listings, outdoor places, experiences, place pages, journeys, and any new kind of content page:
- **Hero** at the top for places, listings and profiles (`tv_explore_hero()`, decisions/0058 §21), flush under the tool bar.
  Listings and profiles pass `heading`, `slides` (HeroMedia) and `manage` for the owner.
- **Header:** kind or category (searches for more), the title (the page itself, not a search link), the place, and the
  **Link button** in the top-right corner (`data-link="…"` plus `js/link-button.js`). No breadcrumbs.
- **Engage bar** right under it: `echo tv_engage_bar($pdo, $kind, $ref, ['title' => …])` (`app/ui/engage-ui.php`).
  It shows views (platform counts), likes, comments, Share and an optional Report.
- **Comments** at the end: `echo tv_engage_comments($pdo, $kind, $ref)`. Public, 18+, reportable.
- **Counts:** `tv_count_item('<kind>:<id>')` so views are counted.
- **Maps** opened as their own view pass `panel: true` to `TvMap.open()` so the info panel starts open.


## Tribal nations' land: public / private standard (decisions/0066, replacing 0058 §26–27's stricter rule): every build

- **Public shows, a nation's land included:** established boundaries, key resources and infrastructure, public listings
  and businesses, and publicly known places, each with the nation's own name (`Nations::label()`) and its rules for
  visitors (`Nations::rules()`).
- **Sensitive is held:** a name that may mark a sacred, burial or protected site (`Landmarks::SENSITIVE`, anywhere), and
  what is specific to the nation (`Landmarks::NATION_SENSITIVE_CLASSES`, its springs). Held places are kept, never
  shown, until the nation has its own controls (the nation tools, `Landmarks::NATION_TOOLS`) and says.
- "Closed by the nation" is reopened only at the nation's request. Keep the data; never delete it.
- Anything about a user or an owner is private unless they say otherwise; same philosophy.
- Every reader of landmarks goes through `Landmarks::visibleSql()` / `visible()`. Pins on a nation's land
  (`Nations::pin()`) still move to a public place, so a pin never marks a sensitive spot.

## Migrations must run on Bluehost's MySQL (not just MariaDB): every build

The live database is MySQL. It rejects `ADD COLUMN IF NOT EXISTS`, `ADD INDEX IF NOT EXISTS` and `DROP … IF EXISTS`
inside `ALTER TABLE` (#1064), which a local MariaDB accepts. `CREATE TABLE IF NOT EXISTS` is fine. To add a column or
index safely (re-runnable, no `DELIMITER`, so phpMyAdmin's Import takes it), check `information_schema` first:

```sql
SET @s = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE t ADD COLUMN c INT DEFAULT NULL', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 't' AND COLUMN_NAME = 'c');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;
```

(Indexes: `information_schema.STATISTICS` … `INDEX_NAME = '…'`.) See `app/migrations/2026-11-29_go_codes_guest.sql`.

## The live server runs PHP 8.0: every build

Local tests run PHP 8.3, so 8.1+ features pass here and break on Bluehost (a parse error takes the whole file down).
Don't use: `readonly` properties or classes, `enum`, first-class callables (`strlen(...)`), `new` in parameter
defaults, `array_is_list()`, `json_validate()`, typed class constants, standalone `true`/`false`/`null` types. (`: never`
happens to parse on 8.0 as a class name, and only on functions that never return.) Before a delivery, grep the
changed files for these.
