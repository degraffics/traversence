# Hero tidy: HELD, approved but not sent (Jason, 2026-11-20)

Jason approved this in the pop-up on 2026-11-20 (reviewed by the session manager), then said "Once r4 is released and
live. Hold building anything more." So it's **not sent**: there's no zip in `releases/inbox/` and no PR. Send it only when
Jason or the session manager says.

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
`tools/design-tests/hero-tidy.cjs` with `hero-tidy-seed.php` and `stock-page.php` (copied to the harness's
`website/test/stock.php`): 30 checks at 1280 and 390. These went into `main` with R4 (#1153); they're tests only.
