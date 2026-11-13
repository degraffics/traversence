# Proposals

Decision 0145 §2. Improvements drawn from the audit's findings, each with its evidence and verdict. Jason approves,
rejects or rewords each one; nothing changes until he does. P1–P6 decided 2026-11-08.

**Verdicts:**
- **fix:** the page breaks a rule.
- **amend:** the rule should change.
- **missing:** no rule covers it.
- **conflict:** two rules disagree.

## From the first run (2026-11-08, mirror `08b8643`)

### P1. The server's PHP version: amend `CLAUDE.md` (verdict: amend)

`CLAUDE.md` says the live server runs PHP 8.0. But code on the live site uses the `never` return type, which needs
PHP 8.1. It is in `app/lib/Response.php`, which every endpoint uses, and in four other files. The site works, so the
server must run 8.1 or newer.

**Proposal:** the health report (Phase 2) reads the real version. `CLAUDE.md`'s rule and check R04 then name that version.

**Decided (Jason, 2026-11-08): approved.** The health report reads the real version; `CLAUDE.md` and R04 then name it.

**Done (2026-11-13, `auditfix_20261113`):** the daily search-probe job saves the health report's versions to
`results/server.json`; R04 flags only features newer than that PHP. `CLAUDE.md` names the version once the first run records it.

### P2. Pages without the session or account bar (verdict: fix, or accept with a reason)

Seven pages draw without the session or the account bar (R05):
- `login.php`, `register.php`, `verify-email.php`, `email-revert.php`: sign-in pages, where no bar may be right. Signed
  in, they could send you on to your dashboard instead.
- `forgot-password.php`, `reset-password.php`: the same.
- `admin/chamber-builder.php`: an old admin page with no session at all.

**Proposal:** sign-in pages redirect signed-in visitors to the dashboard, and are listed as accepted exceptions. The
chamber builder is archived (it also has the only remaining browser dialogs, P3).

**Decided (Jason, 2026-11-08): approved.** Sign-in pages send signed-in visitors to their dashboard and are listed as
accepted exceptions; the chamber builder is archived.

**Done (2026-11-13):** `login.php` and `forgot-password.php` send someone signed in on (to `?next=` or the dashboard);
`reset-password.php` does when there's no reset link; `register.php` already forwards to `login.php`; `verify-email.php` and
`email-revert.php` act on emailed links that must work signed in, so they stay. All seven are accepted exceptions.

### P3. The last browser dialogs (verdict: fix)

Three `alert()`/`confirm()` calls remain, all in `admin/chamber-builder.php` (R02). Archive the page, or move them to
`TvAsk`.

**Decided (Jason, 2026-11-08): approved.** `admin/chamber-builder.php` is archived (with P2).

**Done (2026-11-13):** archived in `archive.md`, and its three dialogs replaced (a message on the page, a second press to confirm).

### P4. Emoji in what pages show (verdict: fix)

29 places use emoji where the brand's icons belong (R10). Examples: 📍 and 🧭 on journeys and Discovery, and 👉 on the
order sign-off.

**Proposal:** swap them for `tv_icon()` in one tidy-up release.

**Decided (Jason, 2026-11-08): approved.** All 29 swapped for `tv_icon()` in one tidy-up release.

**Done (2026-11-13):** the 25 left, swapped for the brand's icons (`tv_icon()` gained pin, clock, phone, comment, play, car
and heart; an icon inside a line of text is `tv-ei`, sized to its letters).

### P5. Endpoints without the security token (verdict: accept with reasons, or fix)

Six endpoints take a POST without the security token (R07). Five are public by design and should be listed as accepted
exceptions, each with its reason:
- `api/out.php`: tap counts;
- `api/short.php`: short links;
- `api/locate.php`: location;
- `api/qr.php`: QR images;
- `api/csp-report.php`: browser security reports.

`listing/api/referral/confirm.php` needs a look: it should have a token, or a reason it doesn't.

**Decided (Jason, 2026-11-08): approved.** The five are accepted exceptions with their reasons;
`listing/api/referral/confirm.php` gets the token unless the review finds a reason it can't.

**Done (2026-11-13):** the five are accepted exceptions. The review found the reason for `referral/confirm.php`: it signs no
one in, and the emailed confirmation token is the proof (as with email confirmation), so it is one too.

### P6. The page tree (verdict: missing)

72 pages are on the server but in neither `routes.md` nor `archive.md` (R09).

**Proposal:** approve the generated `page-tree.md` as the page tree, replacing the hand-kept `routes.md`. Then mark each
unlisted page live, archived, or to remove.

**Decided (Jason, 2026-11-08): the page tree becomes the source of truth, without losing a route.**
1. The audit checks every route in `routes.md` against `page-tree.md` and lists any that aren't there.
2. Each of those is settled: it's a real page the tree missed (the tree is fixed), or it's gone (noted in `archive.md`).
3. Only when every route in `routes.md` is confirmed in the tree is `routes.md` archived.
4. The 72 unlisted pages are then marked live, archived or to remove, in one list for Jason to approve.

**Started (2026-11-13):** `tools/page-audit/routes-check.py` writes [routes-check.md](routes-check.md): the 20 routes in
`routes.md` that aren't on the server (step 1; 4 already in `archive.md`), and the 70 unlisted pages with the evidence for
each and a suggested call (step 4), for Jason to approve.

**Done (2026-11-13):** Jason approved every call. `site-audit/pages.md` is the register (it replaced `routes.md`, kept in
`site-audit/archive/`); R09 70 → 0; routes-check: nothing to settle, nothing unlisted.

## From the review of 2026-11-13 (findings of 2026-11-13T15:28Z, mirror 67039f0)

12 open findings. Five were the path tool's own mistakes, fixed in the tool rather than proposed (2026-11-13,
`paths.mjs`, `rendered.mjs`):
- the four guides' "7 taps, the measure is 3" (f-74102ecaa2, f-829f5e5c4b, f-afad92fc99, f-c0a5f30a98): the walk along
  each stop was counted as taps; the steps now stop at the journey;
- `/user/post.php`'s "404" (f-2fa09a34a9): the check opened the page with no post id; the page answers that properly ("That
  post isn't available…", with a way forward). It's now among the pages that need an id.

The screenshots couldn't be opened from the review's session (their host isn't reachable from it): the verdicts below
rest on the results and the code.

### P7. Guide weather and roads fails nearly every run (verdict: fix, and missing)

The scheduled job "Guide weather and roads" (`guide_live_tick`, every 15 minutes) stopped with an error 94 times today:
almost every run. Guides still show their last good forecast, but it goes stale, and System Configuration's "Scheduled
tasks" check reads bad because of it. Why it stops isn't in the health report: it gives each job's last run and failures,
not the line the job left (`job_runs.note`, e.g. "Stopped: …").

- **Findings:** f-1c27e3f60f, f-be75f4f580
- **Decisions it touches:** 0131 §5 (live weather and roads); 0145 §1 (the health report)
- **Proposal:** (1) the health report adds each failing job's last line (the job's own message, never anything about a
  person), so the audit names the cause; (2) a build fixes the cause it shows. Until then, its last line is on System
  Configuration → Scheduled tasks → Guide weather and roads.

**Decided (Jason, 2026-11-13): fix it.** His screenshot of the job's last line gave the cause: `Uncaught TypeError: Unsupported
operand types: string * float` at `Roads511.php:168` (AZ 511 sends the reduced speed limit as text at times). Release
`roads511fix_20261113`: read only when it's a number, and a road feed that fails never stops the weather. Part (1), the
health report carrying a failing job's last line, is still to build.

### P8. R04 calls `never` a PHP 8.1 feature the server can't run (verdict: amend)

The health report says the server runs PHP 8.0, and R04 flags 11 places that declare a function's return type `never`
(e.g. `Response::error`, `website/listing/api/claim.php:56`). Yet the site runs them every day. On PHP 8.0, `never` is
read as the name of a class; a function that always exits or throws never returns a value, so the type is never checked
and nothing breaks. It only matters in a function that can return.

- **Findings:** f-6ee204bc89
- **Decisions it touches:** CLAUDE.md "The live server's PHP version"; P1 (decided)
- **Rule now:** "write to PHP 8.0: no `readonly`, `enum`, …" (CLAUDE.md), and R04 flags `never`.
- **Proposal:** R04 passes `: never` on a function whose every path ends in `exit`, `die` or `throw`, and flags it
  otherwise; CLAUDE.md's list is unchanged (`never` was never in it). With server.json recording 8.0 (the search probe's
  next run), CLAUDE.md's "until it's recorded" line names 8.0 as recorded.

**Decided (Jason, 2026-11-13): change the check, not the site.** Done the same day: R04 passes `: never` on a function whose
body exits, throws or calls another `never` function, and flags one that can return; a match in a trailing `//` comment
isn't flagged. R04 on the live mirror: 11 → 0.

### P9. Choosing one of our towns in Get Local loses "Within" (verdict: conflict)

On Get Local, a visitor who chooses "Show Low" in the place pop-up gets Show Low's area: the list covers the area and
"Within" (the distance) isn't offered. The pop-up lists our own towns only as their area (`location-scope.js`
placeOptions: "a town the search already placed in its area isn't listed twice"), and an area "replaces distance search"
(its own note). Typing a town we have no area for gives a point, with Within.

- **Findings:** f-c85353105f (the path now records it as unclear, not a dead end)
- **Decisions it touches:** CLAUDE.md "Places in search" (a city named becomes the place); 0058 §17 (our places first);
  0013 (hubs, geo-hubs, clusters)
- **The two rules:** a city named is the place, with or without a hub there; and our areas replace distance.
- **Choices:** (a) keep it: our towns are searched as their area (say so beside the place: "Show Low area"); (b) offer
  Within for an area too, measured from its town's center; (c) list the town both ways in the pop-up ("Show Low, AZ ·
  town" and "Show Low area").

**Decided (Jason, 2026-11-13): it's already set in the decisions.** decisions/0053 ("Town names on a place page open Get
Local"): a town opens centred on it, nearest first, within 10 miles by default, the distance changeable; only a local
name with no point of its own uses its area. So the verdict is **fix**: choosing one of our towns in Get Local sets the
town (Within 10 mi), and its area stays a separate choice. To build, for his OK.

### P10. "Open now" searches mark nothing open (verdict: fix, to check in the build)

"Places to eat open now near me" (Show Low) gave 5 results, none marked "Open now", as a guest and as a member, on every
run since Nov 12 (morning and afternoon, Arizona time). The search panel draws "Open now" when a result's `open` is true
(`search-panel.js:848`). Either the results aren't filtered to open places, or `open` isn't filled for them.

- **Findings:** f-fa035ddd5a
- **Decisions it touches:** 0060 (near me); 0058 §39 (filters in the search)
- **Proposal:** the build checks which, and fixes it: an "open now" search shows only places open now, each marked.

**Decided (Jason, 2026-11-13): wait for the crawler revamp.** He thinks the crawler has been wiping hours data
(handoffs/crawler-revamp.md). Looked at again once its fixes are live.

### P11. Things the staging paths will check, found in their code (verdict: fix)

Read while writing the staging paths (2026-11-13); each is confirmed or cleared by their first runs:
1. **Sign-up promises "two quick questions"; the page asks four** (where you're from, then three choices:
   `verify-email.php` #s1–#s4). The email, the answer and "Check your email" all say two. Fix the words (or the count).
2. **Confirming the email doesn't sign you in**: the last step is signing in again. Fine if intended; a line on the
   confirm page saying so.
3. **"See it as visitors do" on a hidden listing says "Listing not found" to its own owner** (the test listing is hidden,
   `is_suppressed`; `listing/view.php` shows hidden listings to staff only). CLAUDE.md "Never offer someone what their
   account can't use": show the owner their own listing with a line "Hidden from the public".
4. **Pulse: a form step's answer is lost** (pulse-ui.php writes it, then reloads at once), so "Decide" on a like check
   shows nothing. A form step's answer should show as the site's notice after the reload, as a `do` step's does.
5. **Pulse: "Let it show" on a post someone already decided** throws an error the endpoint doesn't catch
   (`Market::staffAct` throws `InvalidArgumentException`; `admin/api/pulse.php` catches only `RuntimeException`), so the
   person sees "That didn't reach the site" instead of the reason.
6. **The Reports page answers a decision in plain colored text** ("Done."), not the site's notice (`data-tv-flash`;
   CLAUDE.md "one look for notices").
7. **A like is taken for an item that doesn't exist** (`Engage::target` checks only the id's shape): a missing data rule;
   a like should check the item is there.

- **Decisions it touches:** CLAUDE.md "No browser dialogs; one look for notices", "Never a dead end", "Pulse"; 0145 §3
- **Proposal:** one small build for 1, 2, 4, 5 and 6 once the paths confirm them; 3 and 7 with their own decisions' notes.

**Decided (Jason, 2026-11-13): all seven,** with (2) signing the person in right after they confirm their email. One
build, checked against the staging paths, for his OK.

### P12. Sideways scroll on Market 360° → Gateways at phone size (verdict: fix)

The executive's check of `/admin/market/gateways` at 390px scrolls sideways. The page is the standard table and metrics
widgets (`market/gateways` in `app/admin/tools.php`); nothing in its columns is obviously wide, and the screenshot couldn't
be opened here.

- **Findings:** f-3ff207d9d1
- **Decisions it touches:** 0058 §11; 0109 (widgets)
- **Proposal:** the build looks at the screenshot (the run's `staff-executive-_admin_market_gateways.png`) and fixes what's wide.

**Decided (Jason, 2026-11-13): fix it** in the same build as P11.

### Not proposed

- **NPI Registry "warn"** (f-fc2b825837): an outside registry's own status check; nothing on our pages. Look at System
  Configuration's status line for what it warns about; set it aside there if it's the registry's side.

