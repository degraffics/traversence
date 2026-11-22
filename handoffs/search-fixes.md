# Search fixes (Design and trips, 2026-11-22)

Built on Sync 22:55 UTC 2026-11-21 (e229587) as `search-fixes.zip`. Source: `handoffs/screens-proposal-2026-11-22.md`
items A1–A3, B1, B2, approved by Jason 2026-11-22. Build: `search-fixes/build-sf.py` (reads the mirror; rerun to rebuild).

| Behaviour | Source |
|---|---|
| A section tab counts only what's listed under it, never the categories (they're in Suggestions), so the tabs add up to Results | A1; 0058 §38 "Categories leave the results" |
| The tabs take Search Directory's names (Discovery, Directory, Community), the same as each row's tag. **Note:** the proposal said the tag would take the tab's name; 0058 §38 names the tags as in Search Directory, so the tabs follow it instead (same result: tab and tag match) | A2; 0058 §38 |
| Every result has Peek: a listing (by its number) and an outdoor place (`rec:<id>`, the form `discovery/index.php` already sends) open the quick card; any other row with a page (a story, a journey) opens it in the page peek (`TvAsk.peek`) | A2; CLAUDE.md "Peek at a page without leaving" |
| "Our results … are lacking" shows above the rows | A3; decisions/0060 |
| The offer to build a place's guide sits under the results | B1 (Jason, 2026-11-22) |
| Quick Search: a category people ask about here gets its count (listings in it and its sub-categories, here); Open now keeps none; one that can't be counted shows no number rather than a wrong one | B2 (Jason, 2026-11-22) |
| Esc on a pop-up over the search panel (a Peek card, the page peek) closes only that pop-up; a second Esc closes the panel | 0058's note (Esc in an inner pop-up closes only it, as the place picker does); the session manager's review, 2026-11-22 |
| FAQ: five entries updated in place (Search Results' tabs, Peek, the lacking note, Build the guide, Quick Search) | CLAUDE.md "Keep the FAQ current" |

**Kept:** the tabs, Group by section, Open <section> links (they keep the section page's own name: Let's Explore, Get
Local), every Peek card's buttons, the Distance slider, the OpenStreetMap slot (still under the rows), Suggestions, your
own quick searches (still without a number: they're yours, not counted for the place).
**Moved:** the lacking note above the rows; the guide offer under the results. **Dropped:** nothing.
**Side effects:** search answers are cached briefly, so live may show the old tab numbers for a few minutes after the
release.

## Tests
`tools/design-tests/search-fixes.cjs`: 29 checks at 1280 and 390 on the harness with real search data (4 pool listings,
an outdoor place, a story, the Public Pools category): counts add up, names match, Peek on each kind pressed (card,
`rec:` id, page peek), × keeps the results, Esc closes only the card or the page peek and a second Esc the panel, the note above, the guide under, no sideways scroll, no script errors.
The live code gave Get Local 5 for 4 rows on the same data (the bug reproduced first). `SearchPresets::inCategory`
checked on the harness database: Public Pools 4, an empty category and an unknown one give no number.
