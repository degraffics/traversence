# C.5a: Get Local's list doesn't jump while its first search runs (0145 V06; the launch plan's C.5, gates 1 and 2)

One file, `website/directory/index.php`, built on **C.8's** copy (`handoffs/c8-open-now/`, which carries C.4's), so it
sends after C.8 and is rebuilt on the live copy then (same file).

## What moved, measured (tools/design-tests/c5a/shift.cjs; the browser's own layout-shift entries, worst of 3 loads)
Two causes, separated by loading the styles before the first paint (`DELAY=-1`, as a built stylesheet would):

| Page, width | Before (the CDN's styles late, as live) | Styles first, before | Styles first, after C.5a |
|---|---|---|---|
| /login.php 390 | 0.250 | 0.009 | (unchanged: not in this file) |
| /login.php 1280 | 0.653 | 0 | |
| /hub/ 390 / 1280 | | 0.001 / 0 | |
| /directory/?town=Show Low 390 | 0.653 | **0.676** | **0.003** |
| /directory/?town=Show Low 1280 | 0.136 | 0.002 | 0.020 |
| /directory/ (no place) 390 | | 0.032 | 0.032 |

- **Cause (1), shared: the Play CDN** paints the page unstyled, then restyles it (login's whole card, the bar). A few
  first-paint rules in the head don't reach it (tried: body margin, `.hidden`, flex, min-height; login stayed 0.54–0.62),
  because every page's own Tailwind classes change. With the styles in before the first paint, login and the hub are at
  0. So (1) is the built stylesheet's to fix (parked; recommended unparked, see below), not this release's.
- **Cause (2), Get Local's own (fixed here):** on a phone the sentence ("7 businesses within 10 mi of Show Low") fills
  after the search answers, 0 → 61px, and the list from "Loading…" (25px) to the cards, pushing everything down.
  Now, while the first search runs, the sentence's two lines are held on phones (`.dr-wait`), and the list shows five
  placeholder cards the cards' size (`.dr-skel`, "Loading…" kept for screen readers with `aria-busy`), also between
  pages. Only when a place is known (the link's, or this tab's): with none, an inline script clears them before the first
  paint, and the list asks for a place as before. 1280 rises 0.002 → 0.020 because the list is now on screen while it
  loads; under 0.1 ("good").

## Screens (`screens/`, 1280 and 390): before-/after- loading (the search held), loaded, and no place.

## Recommendation for the session manager
Unpark the built stylesheet (Jason's "Yes to the stylesheet", its plan in `handoffs/frontend-shop.md`): it's the one fix
for cause (1), measured here at 0.25–0.65 on the sign-in page every guest is sent to.

Design: approved (3769606). Its note done: the list is `aria-busy` while loading and "Loading…" a polite status (`tools/design-tests/c5a/list-busy.cjs`, 3/3); shift unchanged.
