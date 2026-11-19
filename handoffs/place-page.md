# The place page (2026-11-19)

One release, `place-page.zip`, built on Sync 22:49 UTC. Jason approved it in the pop-up, after a review by the session manager. Decision notes: decisions/0058 §20 and §28 (2026-11-19). Tests: `tools/design-tests/place-page.cjs` (48 checks at 1280 and 390).

## What it does

1. **The photo viewer names what's in the photo.** The top line has the place's name (a link to its page), **See on the map** and **Copy link**.
   - On Let's Explore, each landmark photo names its own landmark.
   - On a page's own photos, the line names the page.
2. **Plan a trip here** (in ⋯) shows the place's ready-made journeys first (decisions/0146), then the Trip Planner set to the place. **Add to a trip** is greyed as coming.
3. **Views always show, 0 included.** The viewer shows the counts of whatever place is in the photo.
4. **⋯ on the engage bar:** See on the map, Directions, Plan a trip here, Add to a trip, Save contact, Suggest an edit, Report.
   - Each item runs the page's own button where there is one.
   - Anything the page can't offer is greyed, with its reason.
5. **A business with only a mailbox gets no Directions on its own page.** This closes a gap left by R2's rule.

## Kept, moved, dropped

- **Kept:** the bar's views, Like, Comments, Share and QR; the page's own action buttons; the viewer's arrows, swipe, keys, credit and close.
- **Moved:** Report goes into ⋯ on landmarks, outdoor places, listings and place pages. Elsewhere it stays on the bar.
- **Dropped:** nothing.

## Next

The saved-trips decision (Jason said "Yes"). It builds on Favorites and Personal Guides (0058 §1) and on suggested journeys (0146), and takes in the queued routing and "Send to Google or Apple Maps" items.

## Rebuilding

`PP_PARTS="pp ppfaq" handoffs/place-page/mk-pp.sh` rebuilds every file from the mirror. Edit its `S` and `M` paths first.
