# The Control Board build session's hand-off at its hold (2026-11-23, about 15:15 UTC)

Session `session_01CXLi5iMkDf1xiJ4V9rScRe`. It took over step 4 of the board from `session_01CXLi5mF3yZ2ZE7oz8RpDsq`
(`handoffs/control-board-build.md`, the step 4 brief). Session manager: `session_01CXLi5mGX2sg5QS11gg3Wrh`. Design
reviewer: `session_01CXLi5mAZ747xQzHNPrjM9G` (new, 2026-11-23). Every release that changes what people see goes to the
reviewer with screenshots at 1280 and 390. "Design: approved" comes before the session manager's "clear to ask", and both
come before Jason's pop-up.

## Live (each byte-checked against the mirror)

| # | Release | Build | What it did |
|---|---|---|---|
| #258 | control-board.zip | `handoffs/control-board-build/` | The board as one release: the one engine, threads, IRs, cards |
| #260 | control-board-health.zip | `handoffs/control-board-health/` | ControlConsistency, health.php's consistency/ledger/irs_loads, `consistency.json` daily |
| #261 | control-board-fix.zip | `handoffs/control-board-fix/` | The false /api/0 trip, plain endpoint names, cents |
| #262 | crawler-phone.zip | `handoffs/crawler-phone/` | Insights → Crawler at 390 |
| #263 | board-phone-fit.zip | `handoffs/board-phone-fit/` | No sideways scroll with live content |
| #264 | spend-units.zip | `handoffs/spend-units/` | Each service's count in its own unit |
| #266 | board-panels.zip | `handoffs/board-panels/` (+ `.md`) | One panel at a time in the admin's drawers, Copy all, panels titled by the part |
| #268 | board-view-3.zip | `handoffs/board-view/` (+ `.md`, `board-view-proof/`) | The opening view: rail (Development · Board · Modules · Workflows · Diagnostics), What's wrong now chips, Board alone, Development its own page, one part in the panel, the drawer's Back and margin, gauge titles whole |

## Held: release 2, "jobs and words" (PR #1299, draft; `releases/inbox/board-fixes-2.zip`)

Build: `handoffs/board-fixes/` and `handoffs/board-fixes.md`, built on Sync 14:53 UTC. The session manager says its content
is right; it waits for "Design: approved", sent 15:1x UTC with `handoffs/board-fixes-proof/`. Then:
1. Ask Jason with the pop-up, marked "reviewed by the session manager and the Design reviewer", with a push notification.
2. Mark #1299 ready, merge it (resolve `deliveries/README.md` keeping both rows), and tell the session manager.
3. Byte-check the release when it's live.

Before sending, if a sync has landed since 14:53, fetch the mirror and re-zip. If faq.md changed live, re-apply only this
build's two FAQ edits.

What it carries:
- `Scheduler::sync`'s upkeep behind its hourly guard: fix 1, the likely cause of the morning's cron gap.
- Refused starts recorded.
- The lateness check fixed.
- health.php's jobs section, saved daily as `site-audit/results/jobs.json` by `towns.mjs` and `search-probe.yml`. Those
  two repo files act by themselves, so they reach main only with this merge.
- old-titles' redraw.
- Google Cloud's cap title with its reading.
- Google's "Signs in as" (`SignIns.php`).
- The Sync chip.
- The Alarms table's plain names.
- Drawer titles on two lines.

Tests: `old-titles.php`, `board-words.php`, `google-signin.cjs`.

## Next, in order

1. **The gauge side panel redesign, mockup first**, after release 2, sent to the Design reviewer and then the session
   manager. The asks, relayed by the session manager on 2026-11-23:
   - Jason: "The gauge opened side panel and presents a ton of overwhelming text. I want a visual drill down into things
     I can act on."
   - Jason: "every single listed item is a dead end."
   - The shape, refined by the session manager:
     - top: the gauge, its stage, and "What moves it: N things";
     - one stacked bar of its items by status (Live and checked · Needs a check · Not built · Awaiting your sign-off),
       each segment filtering the list;
     - "What you can do": 3–5 action cards, one sentence and one button each (Sign off the scope; a failing page opens
       its finding; checks not built say what they need and who builds them);
     - items as compact rows, each a name plus 6 dots (decided · built · live · checked · reviewed · proved), opening in
       place with pass/fail chips and Workflows / Core Services / Registered Services;
     - sources, hashes and releases behind a Details fold, as links.
   - Hard requirements: every item and every reading leads somewhere you can act.
     - The item's name opens the page itself (Peek) and its row in Site health.
     - A failing check opens its finding with a screenshot, plus "Send to a build", which queues the fix for the owning
       session, names the owner, and keeps a way back.
     - "no check yet: X" says what building it needs and where it is in the queue.
     - An open finding opens with its evidence and its fix route.
     - "Awaiting sign-off" has Sign off in place.
   - Add a rendered test that no item or chip in a gauge panel is inert text.
   - Apply the pattern to every gauge panel, runtime gauges included.
   - Today's panel is drawn by `tv_cb_gauge_card` and `tv_cb_gauge_detail` in `app/admin/control-console.php`, with the
     gauges from `ControlGauges` (`gauges.json`, `gauges-items.json`).
2. **The provider usage spec**, specs only, to the session manager first: "the provider's own usage and spend, within the
   service's budget".
   - Google first: Cloud Monitoring for request counts; the billing export to BigQuery, or the budget feed, for actual
     cost after the free allowance.
   - Then Railway (Jason adds RAILWAY_API_TOKEN via Add key at
     https://traversence.com/admin/config/register#svc-railway-key) and Anthropic.
   - Reading the figure must cost nothing or fit inside that service's budget; AWS Cost Explorer stays an estimate.
   - Reconcile our 608 Places checks against Google's 30 requests.
   - List what Jason sets up once.
3. **(b), Railway's real cost:** a daily usage read beside the estimate, once the key is in. The backboard GraphQL `projects`
   and `usage` queries work; `me` isn't authorized.
4. **Step 5, a week of watching:**
   - `site-audit/results/consistency.json` daily;
   - after release 2, `jobs.json`: Lead scores' cron line (`missing` → Set them up now), `jobs_missed`, `db_connections`.

   Report mismatches to the session manager, not Jason.

## Open questions and known failures (not this build's)

- `sales_tick`'s cron line: the session manager asked Jason. `jobs.json` will show it once release 2 is live.
- These fail on the live code too, so they're not from this build:
  - `run-all.sh`: engine.php migrations A–B (their files ran), lines.php (seed), r1.php (`/api/crawl/zz_test.php`, a
    stray endpoint), r4b.php (health.php's unused names), register.php ×2.
  - `rendered-all.sh`: dispatcher/r3 stops at Pulse's playbook drawer (`#pb-dr-play … Open`). It's recorded in
    `control-board-build-log.md`.
- `rendered-all.sh` now fails a test that stops part way. It also runs a subset by name: `rendered-all.sh <root> 'name|name'`.

## The bench (how this session tested)

- MariaDB: `mysqld_safe --user=mysql &`, user tv/tv. It stops when the worker restarts; start it again.
- Roots: `<scratchpad>/bench/w/root` (the `.env` two folders up, DB `lv_main`) and `bench2/w/root` (`lv_b`). A root is the
  mirror's `sync-app` → `app` and `sync-storage` → `website`, with the build copied over.
- `tools/control-board-tests/board-panels-router.php` is PHP's server router. It serves the board, the Alarms table and an
  alarm's drawer (from `tools.php`'s list and get), a part's page, and the register: the card alone, or the whole
  register with `REGISTER=full`.
- Seeds: `board-panels-seed.php` (the live alarms snapshot plus Google Cloud's cap alarm).
- Rendered tests: `board-panels.cjs`, `board-opening.cjs` (writes screenshots with a shots dir), `google-signin.cjs`.
- Never run two suites at once: they share the database.

## Pitfalls learned

- A class name may already exist in the admin's CSS. `.ak-back` was the page header's round button and drew a circle
  over the drawer's title.
- A `<form>` inside a `<p>` closes the paragraph, so use a `<div>`.
- tv-ask redraws `data-tv-status` lines as new boxes without their classes. To hide one by class, wrap it.
- `send.py` won't reuse a zip's name. A zip that never reached main is removed with its record, and the build is sent
  under a new name.
- Live snapshots move. A test that needs old-worded alarms seeds its own.
- Draft gauge numbers track their inputs (System Configuration 48 → 49, 2026-11-23). Regenerate `gauges.json` and update
  §6 and gauges.php 5b with the reason.
