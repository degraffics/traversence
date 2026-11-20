# The admin kit release (2026-11-20): sent to the branch, waiting for Jason's OK

Design and trips session (session_01CXLi5gxSyzVYGuXX3xuu2c). Built on **Sync 16:01 UTC (c8f9c3e)** by
`handoffs/admin-kit/build-kit.py`, which reads the live mirror, so re-running it rebuilds on the newest sync.
`releases/inbox/admin-kit.zip`, 4 files, zip check clean, claims `--release admin-kit`.

## The queue (`handoffs/admin-design.md` "Queue for the next admin release"), item by item
1. **One header per view at 390 (the module name cut beside the sections ▾): already done**, by #162 (live 2026-11-19
   02:15, "the admin command header on phones") and Jason's 2026-11-19 "Command needs help" (kit-css.php hides `.ak-mod`
   below 768px; the sections ▾ names the page). Rendered on the harness signed in as staff at 390: one row (Back,
   "System Control Board ▾", the board's light, Shortcuts), the filter on a second, nothing cut, no sideways scroll.
   Nothing to build; struck from the queue.
2. **Contrast under 4.5:1** (css-theme-brand §3). Every text use of `#8A7355` in `admin-hub.php`, `admin-dashboard.php`
   and `pulse-ui.php` moves to `var(--tv-meta,#5E4F3C)`, the token theme-tokens.php defines for metadata text at 4.5:1
   on every surface. Measured: 7.65:1 on #FFFBF2 (was 4.36), 6.89 on #F7EFDC (was 3.93), 5.77 on the page #E4DCC5 (was
   3.29). The rules changed:
   - admin-hub.php: `.hb-crumbs`, `.hb-row small`, `.hb-dl dt`, `.ab-g b` (Shortcuts' group labels).
   - admin-dashboard.php: `.ad-k`, `.ad-m small`, the "· show all" note.
   - pulse-ui.php: `.ps-hd` (the column headers, which `.ps-sort`/`.ps-flt` inherit), `.pq-n`, `.pq-t small`,
     `.ps-sub small`, `.pb-case-hd small`, `.pb-tl li.order b`, `.pb-tl small`, `.pb-next .lead`, `.pst small`,
     `.pb-play b::before` (a step's number), the step dots' labels, the uppercase small heads.
   - Kept as they were: marks that aren't text (▾ and › glyphs, the trend's =, severity icons, the clip icon, the
     `update` level's dot background). A non-text mark needs 3:1, which #8A7355 has.
   - Each file is byte for byte the live copy apart from the colour.
3. **The way-back bar 392px wide at 390** (found on live 2026-11-20, queued here by the session manager). Cause:
   `js/tv-trail.js` pulled `.tv-trl` out by `clamp(1rem,3vw,2.5rem)` (16px at 390); on phones the page's padding is
   `--tv-pad` (.85rem, 13.6px). It now uses `var(--tv-pad, <the same clamp>)`. On the harness, navigating Explore → a
   hub → a listing → a landmark, the bar spans 0–390 with no sideways scroll; 768 and 1280 are unchanged (64–768,
   64–1280).

## Tests
- Contrast measured in the rendered admin (harness signed in as staff): the dashboard, Shortcuts open, Pulse's stream.
- tools/notify-tests: member-pulse 4 of 4. pulse-act and admin-stream can't set up on this harness (they need migration
  files the live site deleted once run, as their headers say), with or without this change. Their files changed only
  in CSS colour values (checked: identical to live but for the colour).
- No FAQ entry: nothing anyone does changes (decisions/0057 is "what changed, from a visitor's view").

## The harness (for the next build)
`/home/user/x` in this container: MariaDB `tvh`, built by `tools/fix.py` from the error log (the `sql_errlog` plugin),
a router for the admin's addresses (`keep/srv.php`), and a harness-only staff sign-in (`website/test/login.php`, cookie
`traversence_session=harness0000000000000000000000`). Never shipped. It lives only in this container.
