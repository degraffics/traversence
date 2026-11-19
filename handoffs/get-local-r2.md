# Get Local R2 (2026-11-19)

One release, `get-local-r2.zip`, built on Sync 22:06 UTC. Jason approved every part (reviewed by the session manager):
Change 1 (the line over the list), Change 2 (card tools), the header search (a)+(b), the PO box rule, the comments box,
and the concept ("Build as shown"; layout "Lets build it each way for a A/B test"). Decision note: `decisions/0058`
(2026-11-19, "Get Local R2"). Tests: `tools/design-tests/get-local-r2.cjs` (42 checks, 1280 and 390).

## Parts and their state

| Part | Build script | State |
|---|---|---|
| a. The line over the list: Filters ▾ (ticks, chips in the sentence), Sort; "· showing" gone | `build-r2a.py` | built, in the zip |
| b. Card tools: numbered; Call, Directions, ⋯ (Email, Website, Peek in words), Link; score chip with ⓘ | `build-r2b.py` | built, in the zip |
| c. A mailbox has no Directions (card, map card, peek, directory pin; portal hint) | `build-r2c.py` | built, in the zip |
| d. The comments box: write in place, guest taps to sign in, starters | `build-r2d.py` | built, in the zip |
| e. Header search: split look, place chip; folds to ⌕ on Get Local; Ctrl K | `build-r2e.py` | built, in the zip |
| f. Arrange, Table, the list beside the map (A/B) | `build-r2f.py` | built, in the zip |
| g. FAQ | `build-r2g.py` | built, in the zip |

Next, after R2 is live: the place page (lightbox link to the place and See on the map; Add to a trip; counts always shown;
a ⋯ toolkit on the engage bar), diagnosed and proposed to the session manager first.

## Kept, moved, dropped

- **Kept:** every filter (Open now, phone, website, Favorites), Sort's three orders, the sentence, suggestions, Near me,
  the full-screen map and its Back to the list, Peek, Link, out-counts (`data-out`), the "Named on pages we read" cards,
  Tell us and Send the crawler, the pager (its per-page select stays; Arrange sets the same value).
- **Moved:** the four toggles into Filters ▾; Email, Website and Peek into ⋯; the card's status onto the category line;
  the score ring to a chip; the header search's place to a chip.
- **Dropped:** "· showing 1–10" (the pager says it); the old `.dr-more` CSS rule (nothing drew it since `tv-pager.js`).
  Not built: "Open now first" (Filters has Open now; hours can't order across pages); "Unclaimed listings" was dropped
  before approval.

## The A/B test

Computers only (≥1024px). Each device gets `beside` or `switch` at random (`localStorage tv_dir_ab`), kept; Arrange →
On a computer changes it. Counts through `/api/count.php` (`m=act`, never who): `gl-ab:<side>` once a visit, then
`gl-ab:<side>:open|out|pin|area|map|pick`, and `gl-view:list|table|map` when the view changes. Compare taps per visit on
each side after a few weeks; the side that loses is removed in a later release (Jason's call).

## Rebuilding

`R2_PARTS="r2a r2b r2c r2d r2e r2f r2g" handoffs/get-local-r2/mk-r2.sh` rebuilds every file from the mirror (edit its
paths first: `S`, `M`). Each script asserts its anchors, so a changed live file stops the build where it no longer fits.
The rendered test needs a local copy of the site with listings round Show Low (34.25, −110.03; one marked closed, one
with a PO Box) and Leaflet 1.9.4 from npm.
