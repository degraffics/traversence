# Frontend & Shop build session (Jan 1 launch): its record

The session building the launch plan's streams C (visitor front end), F (Shop and money, code) and G (search)
(`research/launch-plan-jan1-2026-11-26.md` on `claude/launch-plan-jan1`; decisions/0181). Kept current as the record,
not as a hand-over (builder playbook §5).

## Releases, in order

| # | Release | Source | State |
|---|---|---|---|
| 1 | **G.1 the search lock's leaks** (`search-lock.zip`) | 0181 §4 | **Live as Release #315** (02:24 UTC), synced 02:27 UTC; headers and meta checked on live |
| 2 | **C.1 the device's place read as "outside"** (`device-place.zip`) | plan C.1; CLAUDE.md "Places in search", 0060 | **Live as Release #316** (02:48 UTC) |
| 3 | **C.2 the path checker** | plan C.2; 0145 | In main (PR #1487) |
| 4 | **C.3 the launch paths** (same branch): each 1A town's eat, stay, see, guide; the three buying paths defined | 0181 §1 gate 2; 0145 §2 | In main (PRs #1487, #1488: the gateway is Holbrook's, 0042); buying paths' runners written: plan-buy and shop-buy pass on the harness (QuickBooks mock), market-buy goes as far as the business's checkout (its card step waits for F.4's test keys) |
| 5+ | The rest of C and F in the plan's order | plan §4 C, F | Queued |

## G.1: the search lock (search-lock.zip)

Built on Sync 2026-11-27 01:35 UTC (`7a839cb`). Files: `app/ui/search-lock.php` (new), `app/ui/security-headers.php`,
`app/ui/storefront-view.php`, `app/ui/faq.md`, `website/index.php`, `login.php`, `register.php`, `forgot-password.php`,
`directory/classic.php`, `robots.txt`, `sitemap.php`.

Each behaviour and its source:
- One switch, `TV_SEARCH_LOCK` (true) in `app/ui/search-lock.php`, side-effect free so the trvgo.us router can load it.
  Lifting is one release setting it false (0181 §4: "It's one release").
- Every page and endpoint sends `X-Robots-Tag: noindex` (security-headers.php, loaded by bootstrap and by the pages that
  don't load it) (0181 §4: "the site isn't indexed").
- The home page, login, register, forgot-password and the classic directory print `<meta name="robots" content="noindex">`
  via `tv_robots_meta()` (0181 §4's named pages).
- Storefronts: `noindex` instead of `all`; a team's preview stays `noindex, nofollow`; a closed shop was already noindex
  (0181 §4: "storefronts (today X-Robots-Tag: all)").
- Sitemap: an empty `sitemapindex` with a comment while locked (0181 §4: "the sitemap … say the same").
- robots.txt: a comment saying the site isn't open yet; no `Disallow: /`, since a crawler barred from a page never sees
  its noindex and can still list its address (build choice, flagged to the session manager).
- FAQ "Can search engines find Traversence?": Not yet, and what stays out after the lift (0181 §4 "Pages held after
  the lift"). The anchor is unchanged (`grep faq.php#can-search-engines` finds no links).
- Not changed: `page-shell.php:51`'s fixed noindex (G.2 makes it per page; G.4 lifts).

Tested on the harness (MariaDB, the mirror with the zip laid over): each named page answers `X-Robots-Tag: noindex` and
carries the meta; register.php redirects to `login.php?tab=create`, also noindex; a seeded storefront answers 200 with
`noindex` (the plan check stubbed in the test copy only); the sitemap is empty; with the switch false, the storefront
answers `all`, no header or meta is sent, and the sitemap lists its parts as before. Screens at 1280 and 390 (Tailwind built locally from the site's files, as its CDN is refused in the build sandbox) in
`search-lock/screens/`: no sideways scroll, no script errors, pages unchanged but for the FAQ answer.

Admin's test: nothing new to press. Jason sees the FAQ's answer say "Not yet"; a search engine sees noindex everywhere.

## C.1: the device's place (device-place.zip)

`UniversalSearch::loosePlace` (the place from Auto-detect, or a town or ZIP set with no area named) returned
`'outside' => true` always. It now checks the place's ZIPs, or for a bare point the nearest town's
(`Landmarks::nearestZip`), with `clusterForZips` (the check `placeNamed` and `countyNamed` already use) and, inside an
area, returns the shape `placeFromScope` gives a town chosen in its area (`'sub' => 'Your place'`, the cluster's key,
level, slug and url, `town`, `area`), with `'outside' => false`. Outside every area: unchanged. FAQ: one sentence added
to "Does Auto-detect share where I am?".

Tests: `tools/search-tests/device-place.php` 7/7 (Show Low by city, ZIP, an area ZIP (Pinetop), a bare point; Seattle
and a point at sea stay outside; an unknown slug falls through). The whole `UniversalSearch::search` on the harness:
before, "pizza" with Show Low set gave the place chip `out=true`; after, `out=false`; Seattle `out=true` both. The
effect on the live notes ("did you mean", "We have no listings in …") is checked on live after it publishes, with the
search probe.

## C.2 and C.3: the path checker (repo only)

- **Sign in** (`paths.mjs`): since #253 the phone menu's Sign in is `a.tvpm-sin` and `.tvpm-in` is the menu's column, so
  after opening the menu at 390 the old list found no visible Sign in (`a[href="/login.php"]`'s first match is the
  hidden panel copy). Reproduced on the harness with `.hidden` applied; the new list finds it.
- **Staff two-step** (`check-kit.mjs`): the site takes each 30-second step once (`StaffLogin::totpMatch`,
  `totp_last_step`); the run signs the same logins in several times, so later sign-ins got "That code doesn't match".
  `staffIn` keeps the last step per login (in the process and a temp file the run's steps share), waits for a fresh step,
  and retries once on the next. Old code fails the stand-in with the live error; new passes 4/4.
- **Launch paths** (`paths.mjs`): `1a:<town>:eat|stay|see|guide` for regions.md's 1A towns (six; "Petrified Forest
  gateway" names no town: gap sent to the session manager), with their own 300 s budget, and a Launch paths table in
  `paths.md`. `shop-buy`, `plan-buy`, `market-buy` defined (staging, sandbox, a runner stops unless the page says Test
  mode); runners next.

Harness note: Tailwind's CDN is refused in this sandbox, so `.hidden` and the utilities don't apply unless stood in for
(a stylesheet built with `tailwindcss@3.4.17` from the site's files, served in place of the CDN by Playwright's route).

Design reviewer's notes for a later search release (seen in C.1's screens, there before it): "See all 1 listings" should
read "See the listing" when there's one; at 390 the location line says "ZIP: 85901" while the chip says "Show Low, AZ".

## Buying paths' runners (`paths-staging.mjs`)

- Staging only (`onStaging`: the run's site must be `CHECK_ACCOUNTS`' staging site), and the card is typed only after
  the card form says Test mode; otherwise stuck before any card (CLAUDE.md "No test moves real money").
- `plan-buy` (runs first): Shop → Your plan → the first plan to add → the cart → Continue to payment → 4111… → the Plans
  page names it → Stop at the end of this period.
- `shop-buy`: A month of Personal Pro; when a plan renews (the Shop then offers no month), the first storage pack, paid
  on the card on file. When the cart holds payment back, the runner reports the cart's own reason.
- `market-buy`: Marketplace → the test item → Buy now → the business's checkout; stuck there with the reason, as its
  Stripe or Square test step waits for the launch plan's F.4.
- Harness proof: `tools/path-checker-tests/buy-runners.mjs`, plan-buy then shop-buy both reach their goals; the guard
  refuses off staging with 0 taps. Their first real run is on staging (Mondays and Thursdays, or PATHS_ONLY).

Queue (the session manager, 03:58 UTC): C.4 (summary first), then C.8 open now (P10, reopened by Jason 2026-11-27, the
CEO's brief 03:42 UTC).

## C.4: a town as a point (town-point.zip)

Built on Sync 2026-11-27 02:50 UTC (`122090e`). Files: `website/directory/index.php`, `website/js/location-scope.js`.
- Found: the place pop-up already set our towns as a point (place.php row 0, P9), but Get Local started every point at
  radius 250 ("any distance"), so a town picked showed "within any distance", against 0053's "within 10 miles by default".
  The path checker read the old `#dr-dist` list, hidden since Get Local R2 put the distance in the sentence, so it
  reported "no distance to set" instead.
- Fix: a town picked, or opened by `?town=`, starts at 10 mi; `?radius=` wins; near me, a ZIP and a map area keep no cap
  (0060). In the pop-up the area search reads for a town is listed once, by the area's name, as "Town area" (it was
  listed with the town's name), and a city suggestion naming the same town isn't listed again.
- The FAQ's "When I choose a town, why can I set how far to look, and what is its 'Town area'?" already says this.
- `paths.mjs` set-place reads the sentence's distance, flags a town not opening at 10 mi (J02), and changes it.
- Tests: `tools/search-tests/town-point.cjs` 20/20 at 390 and 1280. The set-place path on the harness: live code
  "issues: Show Low opened within any distance, not 10 mi (decisions/0053)"; with C.4, passed.
- Seen, not this release's: the place pop-up's list runs past the right edge at 390 on the harness, the same with the live
  code (maybe the Tailwind stand-in); sent to the Design reviewer.

## Place box lists on phones (place-list.zip): next after C.4, ahead of C.8 (the session manager, 04:39 UTC)

Found by the Design reviewer in C.4's screens. `app/ui/location-modal.php`'s phone rule (≤640px) `.lm-sug{position:static}`
was meant for the location window's own list (0058 §25) but also hit every place box's list (`.tv-pbox-list lm-sug`), so
on a phone the list landed inside its bar (bar 48 → 216px, list to 497px on a 390px screen), cutting off the town, GO
and the choices. Scoped to `.lm-box .lm-sug`. Built on Sync 02:50 UTC; zipcheck clean. Tests:
`tools/search-tests/place-list.cjs` 14/14 (live code fails 2 at 390); screens before and after in `place-list/screens/`.
No FAQ change (a layout fix: nothing for a visitor to learn).

## C.8 open now (P10, reopened by Jason 2026-11-27): plan sent to the session manager 04:50 UTC, three gaps open

Live today: hours only in entity_metadata 'hours' (source_type owner / admin / ai_crawler; OSM-only values known through
identity_fact_sources, DataCleanup osm_only). Search keeps unknown-hours listings on an open-now search ("Hours not
listed", search-panel.js:955); Get Local drops them (search_listings.php:262). No known-hours count. "Now" is the
visitor's clock, never the listing's zone (JourneyPlan::timezone exists).
Plan: known = owner or OpenStreetMap; open in the listing's zone; "Hours not known" wherever status shows; "N of M have
known hours"; FAQ; eat-near-me checks it. Gaps asked: (a) staff hours known? (b) unknown kept labelled or dropped on an
open-now search? (c) Navajo land's daylight time.

## Queue at 04:57 UTC (each rebuilt on the newest sync before it's sent)

1. **C.4 town-point.zip**: Design: approved; order OK. After r2-lanes-2, B.2 step 1 and J1 are live and synced.
2. **place-list.zip**: Design: approved; order OK. Rebuild location-modal.php on the sync after C.4.
3. **C.8 open-now.zip**: Design: approved (after two fixes: the row tag's "·" and meta italic; one count sentence);
   order OK. Waits also for r2-lanes-2's faq.md; directory/index.php is built on C.4's. Its why names the .tvs-open clash
   as the likely cause of P10's "marks nothing open".

## C.5 layout shift (0145 V06): investigation done 2026-11-27, proposal with the session manager

The "0.212 on 8 pages" is /login.php on a phone (the 8 send a guest there). /directory/?town=Show Low phone 0.694; hub
phone 0.366; desktop small. Causes: (1) shared: before Tailwind's CDN script runs, header.php:405 #site-menu-panel shows in
the flow, the bar's wrapper (page-shell.php:85, auth-nav.php:37) isn't a row, body keeps 8px: the bar 227–273px drops to
~55px; (2) directory/index.php:309 #dr-sent filled after the search answers, pushing the list 62px on a phone; (3)
hub/index.php:308 #wx-alerts/#roads revealed above #g-essentials ~4.5 s late, #gd-wx widening. Proposed: C.5a (1)+(2) after
C.8 (same files), C.5b (3) via the Design reviewer; suggested change: a built Tailwind stylesheet instead of the Play CDN.
Measuring scripts were in the session scratchpad (measure.cjs etc.), not kept.

## The built stylesheet (Jason, 2026-11-27: "Yes to the stylesheet"; design/css-theme-brand.md note, PR #1522)
Comes after C.5a and C.5b, as its own release. Its plan went to the session manager on 2026-11-27 and is waiting for "clear":
- **Build:** tools/stylesheet/ runs the tailwindcss 3.4.17 CLI over the mirror and writes website/css/tv.css.
- **Switch:** one switch, tv_tailwind_tags() in app/ui/tailwind.php, used by all six places that load the CDN today: page-shell.php:56, auth-nav.php:27, and index.php, directory/classic.php, listing/confirm-referral.php, user/dashboard.php.
- **Zip check:** a new rule rebuilds and compares tv.css.
- **Gap:** about 380 places build class names in code, which the CLI can't see. Each needs a safelist line or a whole literal class.
- **Claims:** tv.css is claimed with --change.
- **Retiring the CDN:** the CSP and service entries keep the CDN until the stylesheet has run a week clean.
- **Before-and-after look:** Piece 2 runs it at full reach.
- **Session manager, 2026-11-27: "clear, with four fixes":**
  1. TV_TAILWIND becomes a switch under System Configuration → Switches, with Undo and History.
  2. The zip-check rule for tv.css ships first, as its own check change with three tests, after the CEO's Tier 4. Piece 2 reviews the diff.
  3. Before building, list the Play CDN-only blocks (`type="text/tailwindcss"`, `@apply` in pages, per-page `tailwind.config`) and send the list to the Design reviewer.
  4. Dynamic class names become whole literal classes first. Any safelist lives in tools/stylesheet/ with file:line for each entry.
- **Fix 3 done, 2026-11-27 (Sync 16:23 UTC):** Play CDN-only blocks in the live mirror: `type="text/tailwindcss"`: none;
  `@apply` in pages, CSS or JS: none; per-page `tailwind.config`: one, page-shell.php:57 (the font families: serif, display,
  sans), which moves into tools/stylesheet/tailwind.config.js unchanged. The CDN loads in six places (page-shell.php:56,
  auth-nav.php:27, index.php:83, directory/classic.php:55, listing/confirm-referral.php:8, user/dashboard.php:106) and is
  named in csp.php:63, StagingGate.php:313, OutsideServices.php:184/283, service-defs.php:499, Preflight.php:1129 (kept a week).
  Class names built in code: about 140 sites by a rough grep (`class="…' . $x`, `className +=`, `$cls`, `$tv_item`), to
  be listed one by one with file:line before the build (fix 4).
- **Fix 4 done, 2026-11-27 (the class names; Sync 16:23 UTC):** `tools/stylesheet/dynamic-classes.py` lists every place a
  Tailwind name is glued from pieces (`'bg-' . $x`, `"text-" + c`, `` `w-${n}` ``, `"bg-$tone"`, `'sm:' . $x`): **10 places in
  8 files, none of them Tailwind**. All are the site's own classes: Blocks.php:555 (`.tvb-$bp-mt-$k`, its own CSS),
  tv-proof.js:109 (`m-page`, `m-c2`…), admin-outside.js:66 (`os-m-`), control-console.php:189 and :2056 and
  ControlConsistency.php:71 (`cb-p-`), MarketSquare.php:239, MarketPay.php:271 and :290 (`tv-order-`, not a class),
  client-order.php:828 (`order-`, a key). So **no safelist line is needed**; the "about 140" were whole names in variables,
  which the CLI reads. The earlier "about 380" was the same over-count.
  **Proof:** the CLI built tv.css from the live mirror in 5 s (33 KB; `app/data/` excluded: 11 MB of word lists, no markup,
  which stalled it). `tools/stylesheet/harvest.cjs` opened 17 pages at 390 and 1280 (home, sign-in and Create account, Get Local
  with and without a place, a guide, a listing, an event, the Marketplace, FAQ, Explore, Journeys, Community, a member's
  dashboard, the business portal) and matched each of the **762 class names in use** against tv.css's rules in the browser.
  Every Tailwind name matched but three, and those three are **already broken today**: `border-[color:var(--tv-umber)]/40`
  (login.php:35), `border-[color:var(--tv-line-strong)]/60` and `/70` (the home page, sign-in, a listing). Tailwind 3.4
  can't put an opacity on a `var()` colour, so the CLI skips them, and so does the Play CDN (the same v3.4 engine; its exact
  version couldn't be fetched from here, the proxy blocks the host), leaving the default border colour. Flagged for the Design
  reviewer: written as `border-[color:color-mix(in_srgb,var(--tv-umber)_40%,transparent)]` they'd show as meant. The others the
  harvest named (`pl-h`, `pl-r`… on the dashboard; bare `pl`, `mt`, `m`) are the pages' own classes, not Tailwind.
  **The Design reviewer, 2026-11-27:** the intended look is wanted; in the stylesheet release write the three as
  `color-mix(in_srgb,var(--tv-umber)_40%,transparent)` (and line-strong at 60/70%), with before/after screens of the sign-in
  card, home and a listing at 1280 and 390.
    **Content for the build:** `website/**/*.{php,js,html}`, `app/**/*.php`, `trvgo/**/*.php`, excluding `app/data/**`.
- **Measured for it (C.5a):** login 390 0.250 / 1280 0.653 with the CDN late; 0.009 / 0 with the styles in first; the hub 0.001 / 0.
- **The before-and-after look:** the check can't cover every page (BA_MAX_PAGES 40; about 213 pages). It takes about 20, one per family of how a page loads Tailwind, plus each distinct layout in the page shell. Piece 2 is adding `"look_pages"` to the check, a check change through the CEO and the session manager. **Ping Piece 2 when we're two releases out.**

## The Marketplace's four sections (0135 note, Jason 2026-11-27): sized only
Sent to the session manager on 2026-11-27, waiting for its place in the queue and the gaps' rulings.
- **Release M-sections, about 6 files, no migration:**
  - market/index.php: `?section=` maps onto the existing `from` (people = Aftermarket, business = Shops), plus `regional`; Trade shows a "coming" panel;
  - MarketBiz::browseWhere/count: `regional` = kind × geo_hub_id; a new madeIn() for the label;
  - section-rails.php: the market case;
  - item.php and view.php: the label;
  - faq.md;
  - tests.
- **Gaps, with my suggestions:**
  1. Guided services: none in ItemKinds. Suggest leaving services out for now.
  2. "In the area": suggest any geo hub.
  3. "<region>" in the label: suggest the geo hub's name.
  4. The Native-made field: suggest none for Jan 1.
  5. All physical goods, or only made-here?
- **The CEO's rulings (Tier 4, 2026-11-27):**
  1. No services in Regional for Jan 1. A "guided" flag comes in Q1.
  2. "In the area" = any geo hub, by geo_hub_id.
  3. The label is "made in <geo hub name>".
  4. Native-made on Jan 1 is the FAQ only.
  5. Regional and its label cover tickets, bookings, harvest and made to order only. Physical goods join in Q1 with a "made here" tick.
- **Build notes:** ship before the stylesheet; keep /market/'s default landing, or update the market-buy runner in the same release; re-run plan-buy, shop-buy, market-buy and the post path before sending.
- **Don't build until the session manager places it.** It goes after C.8 (they share faq.md).

## goal_check_tick (for the PM (8); session manager, 2026-11-27; CLAUDE.md "Goals and records")
- **Status:** plan sent to the session manager, waiting for its OK and three rulings: the due_on/measure columns' owner, the at-risk rule, and the `ceo` slug.
- **Build:** GoalCheck.php, scripts/goal_check_tick.php, Scheduler (--add only), faq.md, tools/team-tests/goal-check.php.
- **Tickets:** Work tickets keyed by audit_key `goal-stall:`/`goal-risk:`, not Audit tickets (TeamRunner.php:514 clears unknown audit keys).
- **Dependency:** needs R2's TeamRoster::lane().
- **Order:** after the pipeline release, R2 and task-seed-4; then C.4.

## Gates (CLAUDE.md #1553: no gate, no build). Reported to the session manager 13:27 UTC, 2026-11-27
**Items → gate:**
- C.4: 1. Set my place, F×7, J01.
- place-list: 1.
- C.8: 1. J03 "none says open now".
- M-sections: none. Jason's Jan 1 ruling; the session manager decides.
- C.5a: 1 and 2 (V06).
- C.5b: 2. The 1A guide shifts on /hub/.
- The zip-check rule: none on its own.
- The stylesheet: 1 and 2. Suggested parked until C.5a/b are re-measured.

**Status:**
- Gate 1: fail.
- Gate 2: fail. Winslow; buying paths unknown (no staging run).
- Gate 6: noindex holds live; the lift date is Jason's.

## 0134 events: a minimum build sized for a Founder decision (sent 2026-11-27, about 17:10 UTC; nothing built)
- **Tickets:** mostly live (ItemKinds, KindCheckout, market/ticket.php). §6b's Ticket Sales caps aren't built.
- **Event pages and store:** new, 2 releases. Keeping live_events separate departs from §1's "one store".
- **GO Codes:** live via ShortCodes::forPage.
- **The pilot:** content, needing a real host.
- **Total:** 3–4 releases.
- **Suggested option (a):** free events, with a listing's ticket item linked; 2 releases.

## State at 2026-11-27 ~18:10 UTC (for a fresh session if this one hands off)
**Live from this session:**
- G.1 search lock (#315);
- C.1 device place (#316);
- C.2 and C.3 (repo: path checker, launch paths, buy runners).

**Built and waiting for the session manager's go** (2026-11-27 evening), in this order: goal_check_tick, then C.4, place-list, C.8, posts-pdo, C.5a, C.5b, M-sections, E1, E2, P1, P2. Each has its build in handoffs/ and a README.
- **goal_check_tick:** handoffs/goal-check. Sends after task-seed-4. Claims taken; rebuild Scheduler.php and faq.md on the live copy.
- **C.4:** handoffs/c4-town-point.
- **place-list:** handoffs/place-list.
- **C.8:** handoffs/c8-open-now.
- **posts-pdo:** handoffs/posts-pdo (one file; 8/8; no visible change but the error words).
- **C.5a:** handoffs/c5a-list-hold (Get Local on C.8's copy; 390 0.676 → 0.003). With the Design reviewer.
- **C.5b:** handoffs/c5b-guide-live (hub on C.8's copy + Roads511::stored; 390 0.233 → 0.001; 18/18). With the Design reviewer.
- **Measuring:** tools/design-tests/c5a/shift.cjs (DELAY, LIVE_DELAY), seed-wx.php, hub-stored.cjs.
- **M-sections:** handoffs/m-sections, Design approved.

**Events:** decisions/0134's minimum, the note of 2026-11-27, option (a) plus participants (#1589).
- **E1:** handoffs/events-e1, Design: approved (2196f08a).
- **E2:** handoffs/events-e2, Design: approved (f80f0900).
- **P1:** handoffs/events-p1, offer to the CMO's ruling #1594 (own codes and/or a line); 22/22 and 34/34. With the Design reviewer.
- **P2:** handoffs/events-p2, Taking part, Send the evening to Maps, Part of <event>; 43/43. With the Design reviewer.
- **Gaps flagged:** a code switched off shows until the business opens Events (suggest a hook in Discount codes' save); Editorial's check of offers has no queue.
- **Touch points:** F1–F9 in handoffs/hometown-theater/touch-points.md (due Dec 3); waiting on the session manager for PR timing.
- **Plan:** all four to about Dec 13; the pilot is the Hometown theater's community night. A hand-set showcase is the fallback, kept ready.

**Parked:** the built stylesheet. Re-measured 2026-11-27: it's the fix for cause (1) (login 0.25/0.65 → 0 with styles first); unparking suggested to the session manager.

**Pending work:**
- **The gate 2 staging paths run:** the Shop, plan-buy, Marketplace-buy, the skipped member and staff paths, plus the sandbox ticket purchase and refund for the CFO's Tier 4. Start it on the session manager's word after pipeline-tick-3 is Live (#322 withdrew) (site-checks.yml, staging_paths). R2 waits for it.
- **The latent posts error:** built as posts-pdo (above).

**Bench:**
- the PHP server on 8790, rooted in scratchpad g1test, with srv-ev.php (business portal routes and /__login?uid=);
- MariaDB tvh (tv/tv); the heal scripts heal3.py;
- the Tailwind stand-in tw/tw-inject.js;
- a headless browser needs a normal Chrome user agent, or it gets no session.
