# The design session's hand-off (index)

Written 2026-11-20 by session_01CXLi5jkgtUikcdUQ4Wmv28, when Jason held every session ("every session is held… review
each session and hand off to new session so we can reduce token use without logic loss"). This is the one place to start;
each area's own hand-off (linked below) has the detail. **Building is held:** start nothing until Jason or the session
manager says.

## 1. What this session owns

| Area | Governing docs | Hand-offs |
|---|---|---|
| The design directive (tokens, admin, public pages one area at a time) | `design/css-theme-brand.md`, `brand.md` | `admin-design.md` |
| One table for the admin's lists (T1–T5) | `design/table-standard.md` | `admin-design.md`, the standard's "As built" notes |
| Get Local | decisions/0058 notes of 2026-11-19 (lines ~984–986) | `get-local-r2.md` (+ `get-local-r2/` build files) |
| The place page (photo corner, ⋯ toolkit, viewer) | 0058 notes 2026-11-19 (~468, ~761) | `place-page.md` (+ `place-page/`) |
| Card photos (parks, landmarks) | decisions/0143 notes 2026-11-19 | `card-photos-first/`, `wikidata-limits/` |
| Claim (who might claim, the form) | decisions/0070 note 2026-11-19 | `claim-kinds.md` (+ `claim-kinds/`) |
| Trips you can save | decisions/0174 | `send-to-maps.md`, `trips.md`, `trip-page.md`, `trip-share.md` (+ their build folders) |
| Held: the hero tidy | Jason's words of 2026-11-20 (below) | `hero-tidy.md` on branch `claude/hero-tidy` |

## 2. What's live, by release

From the site's release records (`deliveries/releases/`). Those don't name a session, so this list goes by subject; the
session manager's `session-manager.md` is the check if one is in doubt.
- **Design directive:** #146 (Release 1, tokens and shell), #149 (Release 2, the admin), #152 (3a, the site frame), #153
  (the Edit site icon), #155 (headings), #158 and #159 (3b-1, Home and Explore), #162 (the admin's command header on
  phones), #163 (3b-2, listings, places, hubs, journeys), #164 (3c, Get Local on the tokens), #165 (the classic
  directory archived), #170 (lighter controls), #172 (Inter, hinted), #176 (three levels: page, section, card), #181
  (one button standard).
- **Home and Explore pieces:** #171 (the hub's weather strip), #174 (Home's local group card), #187 (the home bar
  scrolls under the header), #202 (the map goes back to the list).
- **Pulse's look:** #182, #184, #188, #190, #195, #196, #199. **Tables:** #186 (T1), #193 (T2, the admin's Pulse), #194
  (T3, the consoles), #198 (T4, the older pages).
- **Get Local:** #167, #178 (the finder bar), #208 (R2: Filters, Arrange, Sort, Table, the list beside the map, the
  comments box, the card tools).
- **The place page:** #211. **Card photos:** #205, #213 (the opened page looked up first), #217 (a photo taken on the
  spot), #218 (Wikidata within its limits).
- **Claim:** #215.
- **Trips (0174):** #219 (R1, Send to Maps), #220 (R2, trips and Add to a trip), #221 (R3, the trip page), #227 (R4,
  sharing, Personal Guides, counts). Verified live on Sync 1ceeb44 (14:45 UTC, 2026-11-20).

## 3. What's held, and where

1. **Hero tidy: live as #230 (2026-11-20, verified in Sync 16:01 UTC).** Kept below as the record. Branch `claude/hero-tidy`, `handoffs/hero-tidy.md`, `hero-tidy/build-ht.py`, with
   the files as approved in `hero-tidy/built-on-8270202/`.
   - What it does:
     - ⋯ starts Link to, Claim, Add to a trip;
     - the page's Link icon hides while ⋯ is open;
     - the stock-photo details are see-through;
     - Explore's default-place offer is one slim line;
     - the hub's stray "// ··· (2026-11-19)" text is gone (#211's slip, still live);
     - same-named areas show once.
   - Its tests (`tools/design-tests/hero-tidy.cjs` and two helpers) are already in `main`.
   - **Send only on Jason's or the session manager's word.**
2. **0174 R5, routes on our own site: not started.** §5: openrouteservice's free tier (Jason's choice, 2026-11-19).
   - Its terms are to be checked from its own site first: the free plan's limits, commercial use, the credit it asks for,
     and whether routes may be stored.
   - openrouteservice.org wasn't reachable from the cloud session, so the check comes first, and the host may need
     allowing.
   - Registered in the Service register (0173), through `StagingGate::hold()`, counted on the Control Board.
3. **T5, the shared parts on the table standard:** the vault, the cost tab, outside services, domains
   (`table-standard.md` §Order 3). Not built.
4. **Release 3's remaining areas** (`admin-design.md` §Release 3):
   - 3d: Community, the member dashboard, Pulse's new look. Pulse's look is largely done through T2 and the Pulse
     releases; Community and the dashboard aren't.
   - 3e: the Marketplace, checkout and storefronts (business colours kept; the owner warned below 4.5:1).
   - 3f: sign-in, long text, standalone pages, leftovers.
5. **The map release** (brand.md §4 note, Jason 2026-11-18): trails and routes in a new nature/navigation colour
   (`--tv-route`), the ~50 colour literals in `js/map-view.js`. One release on that file; R5's route line goes on it.
6. **The next admin kit release** (`admin-design.md` "Queue for the next admin release"): **built and sent, 2026-11-20**
   (`handoffs/admin-kit.md`; the 390 header item was already done by #162):
   - at 390, the module name cut beside a near-duplicate sections ▾ (one header per view);
   - contrast under 4.5:1: Shortcuts' group labels (admin-hub.php), the dashboard's small labels (`.ad-*`), Pulse's
     column headers (`.ps-sort`, `.ps-flt`).
   - at 390 a hub is 392px wide on live: the `.tv-trl` bar (`js/tv-trail.js`) (found 2026-11-20 on hero tidy; queued here
     by the session manager).
7. **The footer's squeezed edit zone** (session-manager.md "Visual fixes", to Release 3).
8. **Park Service photos:** built into card photos, and skipped until `NPS_API_KEY` is set (0143's note).
   - Jason adds the key on its card: https://traversence.com/admin/config/register (the Park Service's optional key,
     0173 R2).
   - Once it's set, check a park's card shows an NPS photo with its credit. Nothing to build.

## 4. Open questions and items waiting on Jason

- **Sending hero-tidy:** approved 2026-11-20, then held ("Once r4 is released and live. Hold building anything more").
- **The area data** (ten micro clusters named "Albuquerque", "Abilene" under Ancient Borderlands): Jason sent it to the
  session manager "for us to discuss a plan" (2026-11-20). The session manager owns it. Data cleanup, 0163 §8, is the
  tool.
- **The ⋯ menu's "Link to"** (hero-tidy): it runs the page's own Link button. Journeys' Link buttons (`place:jr:N`) have
  no case in `Links::resolve` (a gap I found that predates this work; not fixed).
- **R5:** openrouteservice's terms (above). If storing isn't allowed, routes aren't kept and each trip asks again within
  the cap (0174 §5).
- **The control board's dashboard ideas, widening the OSM copy, and OSM's routing** sit with the session manager
  (`session-manager.md`). The cards side ("From OpenStreetMap, not checked by us") is to come to this session.

## 5. The next releases, in order (each its own OK; each line with its source)

1. **Hero tidy** (built). Sources:
   - Jason, 2026-11-20: "should start with Link to, then Claim, Add to Trip, then the rest of the list of options";
     "when active the link icon on the page should disappear"; "Stock photo pop-up should be transparent enough to see the
     image bg slightly while not losing readability"; "The location isnt handled well visually" (his pop-up answer:
     Explore's banner); "The rest is obvious" (the stray text, same-named areas).
   - Files: `app/ui/engage-ui.php`, `website/js/engage.js`, `website/listing/view.php`, `app/ui/stock-ui.php`,
     `website/discovery/index.php`, `website/hub/index.php`, `app/ui/faq.md`.
   - Tests: `hero-tidy.cjs` (30).
2. **The admin kit release** (§3.6). Sources: css-theme-brand §1 "one header per view"; the 3a probe's contrast findings.
   - Files: `app/admin/kit-css.php`, `website/admin/router.php`, `app/admin/admin-hub.php`, `app/ui/admin-dashboard.php`,
     `app/ui/pulse-ui.php` (look only; notify-tests must pass).
3. **T5** (§3.3). Source: `table-standard.md` §Order 3. Each tool lists the tables it moved; the zip check keeps every
   column and action; a capped list pages in SQL or says "400+".
4. **3d, then 3e, then 3f** (§3.4). Source: css-theme-brand §4 "Release 3: Public pages, rolled out one distinct area at
   a time"; the order approved by the session manager (`admin-design.md`).
5. **The map release** (§3.5). Source: brand.md §4's note (Jason, 2026-11-18). File: `js/map-view.js` (shared by every
   map: claim it whole). It goes before R5, so R5's line uses `--tv-route`.
6. **0174 R5** (§3.2). Source: 0174 §5 and §7.5. Files to expect:
   - `app/lib/TravelLegs.php`, which already holds `ors` legs (`source` 'ors'), so R5 fills those;
   - `app/lib/Trips.php::plan()`, where legs say "about" until then;
   - `website/user/trips/trip.php`'s map;
   - the Service register's definition (`app/lib/service-defs.php`, decisions/0173 §4);
   - FAQ.
   - Tests: extend `tools/trip-tests/trip-page.*`.

## 6. Rules and pitfalls learned (add to the builder playbook's spirit)

- **Shared files before every merge (2026-11-20, #235; the session manager):** the zip check reads only the inbox, so it
  doesn't see a release that's Planned or On staging. #234 (0176 R2 part 1) was on staging when the vault icons merged,
  and their faq.md then dropped #234's two FAQ changes. Before merging any release that carries faq.md or another shared
  file, ask the session manager whether another release with that file is Planned or On staging, and wait for "clear".

- **Rebuild on the newest sync, every time.** Before zipping, pull the mirror. If a release went live since, rebuild from
  the live copies: every build script here (`build-*.py`) reads the mirror, so re-running it is the rebuild.
- **faq.md order.** faq.md is add-only and shared. A zip blocked only by another pending zip's faq.md waits for that
  release to go live, then re-applies only its own entries on the live copy (CLAUDE.md). Releases queue on it; the
  session manager says who's next. Several times today R4 waited on three others.
- **Claims tied to the release:**
  - claim with `--release <zip name>` so the claim ends when that release is verified live;
  - a claim made without it outlives the release and blocks others (it blocked the Control Board on `staging-tables.php`);
  - release leftovers with `checkout.py release <path>`;
  - the zip check's "changes (top)" means claim `path::(top),…`.
- **A zip already in a branch's inbox can't be resent under the same name.** Remove `releases/inbox/<zip>` and
  `deliveries/<zip-name>.json` from the branch, rebuild, then run send.py. On a `deliveries/README.md` conflict,
  regenerate it (`deliveries.write_readme()`).
- **Merge `main` into the branch before the last zip check.** A stale local delivery log reports releases that are
  already live as still pending.
- **Status lines:** `data-tv-status` turns a `<p>` into the site's notice box on load (tv-ask.js). A test must look for
  `.tv-note`, and a button inside it survives.
- **A comment after `?>` prints as page text.** That's how #211 put "// ··· (2026-11-19)" on every hub (hero-tidy fixes
  it).
- **`entity_metadata` values are JSON** (`"Snowflake"` with its quotes). Decode them. The `closed` key's presence means
  marked closed; its value is when, who and a note.
- **The harness:**
  - The PHP server must run with `-t x/root/website`, or static files 404.
  - The worker restarting stops MariaDB and the server: start `mysqld_safe`, then `php -S`.
  - Its database lacks some live columns and tables; add them to the test database only, never in a release.
  - Playwright `waitForLoadState('load')` after a click that reloads returns at once; wait for the navigation instead.
- **Every behaviour in a summary has a source:** a decision and section, or Jason's quoted words and date (the session
  manager's rule, 2026-11-20). Anything else is a question.
- **The pop-up holds four questions at most.** More goes in two in a row; the session manager reviews first.
- **Never hide what can't be done:** grey it with why (CLAUDE.md). When restructuring a menu, keep items that used to
  render greyed.

## 7. Branches

- `claude/hero-tidy`: held, approved, unsent (see §3.1).
- Merged and done (can be deleted on GitHub): `claude/trip-share`, `claude/trip-page`, `claude/trips`,
  `claude/send-to-maps`, `claude/wikidata-limits`, `claude/park-photos-onspot`, and the earlier design branches.
- `claude/design-handoff`: this hand-off (its PR merges into main).
