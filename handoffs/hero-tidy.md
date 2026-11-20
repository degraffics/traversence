# Hero tidy: rebuilt after Jason's pop-up answers, waiting for his second OK (2026-11-20)

Jason approved this in the pop-up on 2026-11-20 (reviewed by the session manager), then held it ("Once r4 is released and
live. Hold building anything more"). R4 went live as #227. At the restart Jason said resume (the session manager,
2026-11-20: "now release the session to build"). The Design and trips session (session_01CXLi5gxSyzVYGuXX3xuu2c)
rebuilt it on **Sync 15:06 UTC (eb49e51)**, which includes #228. The files are those of the approved build, plus the
live lines put in since 8270202 (R4's trip count in `engage-ui.php`; R4's and the dispatcher's FAQ entries). It was sent
with `send.py` (`releases/inbox/hero-tidy.zip`, `deliveries/hero-tidy.json`), and the claims are tagged
`--release hero-tidy`. Merge only after Jason's Approve in the pop-up.

**Asked in the pop-up (the session manager, 2026-11-20):** Link to on a journey would press the page's Link button
(`place:jr:N`), which has no case in `Links::resolve`. Should it be fixed here, or should Link to be left out on
journeys? No source covers it.

**Also asked (the session manager's review, 2026-11-20):** which area a merged same-named row opens. As built, the one with
the most listings opens; the other choice is a short list of every area of that name, with none chosen for him. This is
display only: the ten Albuquerque clusters are his open area data plan, and this release doesn't change or hide that data.

## What it does (each with its source)
- **⋯ starts Link to, Claim, Add to a trip, then the rest.** Source: Jason, 2026-11-20, "should start with Link to, then
  Claim, Add to Trip, then the rest of the list of options".
  - Link to runs the page's own Link button.
  - Claim on a listing goes to `/claim.php?listing=`; elsewhere it's greyed with why.
  - Files: `engage-ui.php`, `engage.js`, `listing/view.php`.
- **The page's Link icon hides while ⋯ is open.** Source: Jason, "when active the link icon on the page should
  disappear".
- **The stock photo's details pop-up is see-through:** 80% opacity, with the photo blurred behind it. Source: Jason, "transparent enough to see
  the image bg slightly while not losing readability". Its words no longer repeat "Representative photo" (`stock-ui.php`).
- **Explore's default-place offer is one slim line.** Source: Jason, "The location isnt handled well visually", and his
  pop-up answer naming Explore's banner (`discovery/index.php`).
- **The hub's stray "// ··· (2026-11-19)" text is gone.** It came from release #211's slip (`hub/index.php:572`).
- **Same-named areas show once in an area list** ("Albuquerque · 10 areas"), the biggest opening (`hub/index.php`). The
  data itself (the ten Albuquerque clusters, Abilene under Ancient Borderlands) is the session manager's to plan with
  Jason; it isn't part of this.
- **FAQ:** "What's under ⋯" is updated for the new order.

## To send it
1. Run `python3 handoffs/hero-tidy/build-ht.py`. It reads the live mirror at the time, so it rebuilds on the newest sync.
   `built-on-8270202/` is the copy as approved, for comparison.
2. Add `release.json`, zip it, and run zipcheck.
3. Claim the files, then run send.py.
4. Open a PR, and merge once it's cleared.

## Tests
Run on the rebuild (2026-11-20, a harness on MariaDB built from the live mirror): **30 passed, 0 failed** at 1280 and 390.
`stock-page.php` loaded the bootstrap itself, so `tv_page_start()` never set HTML and the page went out as JSON. It is
fixed (test-only). At 390, the hub and the test page scroll 2px sideways with the live files too, so that isn't from
this release. On live, a hub at 390 is 392px wide too: the `.tv-trl` bar (`js/tv-trail.js`) is 392px. It was
already live, and the session manager has queued it for the admin kit release.

`tools/design-tests/hero-tidy.cjs` with `hero-tidy-seed.php` and `stock-page.php` (copied to the harness's
`website/test/stock.php`): 30 checks at 1280 and 390. These went into `main` with R4 (#1153); they're tests only.

## Jason's answers (pop-up, 2026-11-20, reviewed by the session manager) and the rebuild
- **Approve.**
- **Journey Link: "Fix it here".** This release adds the `jr:` case to `Links::resolve` (published only) and the feed's
  words "A journey you linked", plus an FAQ entry ("Can I link a journey?"). Recorded as a note in decisions/0047. The
  question assumed ⋯ appears on journeys; it doesn't (⋯ with Link to is on listings, landmarks, outdoor places and hubs).
  The real gap was the Link button journey pages and cards already drew.
- **Same-named areas: "This seems to be a crawler, workflow issue, because the page should show what is properly
  assigned to that area based on its funnel level."** The merge was taken out, so the hub lists its areas as the data
  has them. His words went to the session manager's area data plan. hero-tidy.cjs now checks that nothing is merged.
- **Tests on the rebuild:** hero-tidy.cjs passed 30 of 30. Links on MariaDB: a published journey links, its state says
  linked, a draft is refused, the feed says "A journey you linked", and unlink works. The journey page draws its Link
  button. Pressing it while signed in was not done on the harness: the real press happens on staging and live.
- Re-sent as `hero-tidy.zip` (the earlier inbox copy was removed from the branch first). 8 files, zip check clean.
  Claims: Links.php::resolve,feed and hub/index.php::(top) are added; hub_card is released.
