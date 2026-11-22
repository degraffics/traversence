# Cards and photos (Design and trips, 2026-11-22)

Built on Sync 12:25 UTC (ca2f7ab) as `card-fixes.zip`. Source: `handoffs/screens-proposal-2026-11-22.md` A4–A6, A13,
B3, approved by Jason 2026-11-22; rebuilt 2026-11-22 by session_01CXLi5kMXdmwb4aE7hWkGeU with the session manager's three
fixes (A4 at its cause, the licence readable on the photo, proof on the real page). Build: `card-fixes/build-cf.py` (reads the mirror; rerun to rebuild).

| Behaviour | Source |
|---|---|
| A card's distance is hidden only when the place's point isn't known: none, or only its ZIP's middle (`Geocoder::APPROX`, the rule the geocoder already uses for "no point of its own"; a park placed only by its town read "0 mi"). A real point under half a mile says **under ½ mi**; others their miles (`dv_mi($m, $approx)`) on the park, listing-park and landmark cards. Recreation.gov and GNIS points are real points | A4; decisions/0060 ("near me" with miles); the session manager's review 2026-11-22 |
| Each card photo's credit is written on the photo (`.xc-cr`): who took it and its licence, both in words ("© Ken Lund · CC BY-SA 2.0"; no © for Public domain or CC0; "Wikimedia Commons" when no author), or "Stock photo · <who> on <source>" for stock; an **i** and the title carry the full credit. The name wraps to two lines before it's shortened, the licence is never cut. Not a link: nothing to tap inside the card's link. The licence was already stored (`landmarks.image_license`, `place_photos.image_license`): `PlacePhotos::for` now returns it and `Landmarks::top` selects it | A5; Jason 2026-11-17 (credit on the photo with an i); decisions/0164 (the licence decides the use); the session manager's review 2026-11-22 |
| The corner icon sits on a dark disc (`rgba(42,27,18,.62)`); the credit's backing `rgba(42,27,18,.8)`: 8.8:1 over a pale sky | A6; css-theme-brand §3 |
| The same stock photo never shows twice in one list: the pattern instead (stricter than "neighbouring": no grid row width to guess). `StockLibrary::pick` already avoids a photo used on the page and repeats only when every fitting one is used; this release stops that last repeat. The strip is one list; the landmark grid's pages are one list paged in the browser and carry Commons photos only | B3 (Jason, 2026-11-22) |
| A memorial or monument (by its name, or a listing's category) never gets a stock photo (a park's summary prose mentioning a monument doesn't count): its own, the Commons match, else the pattern; 0143's order otherwise kept | B3 (Jason, 2026-11-22); decisions/0143 |
| A public place, or one on a nation's land (`Claims::kind`), shows "Run by <agency>" from its Nexus, never "Unclaimed"; with no Nexus, no badge; a business keeps "Unclaimed" | A13; decisions/0070 lines 223–226 |
| FAQ "Where do I find parks and landmarks?" updated in place | CLAUDE.md "Keep the FAQ current" |

**Kept:** 0143's photo order (own, Commons by name, Commons found, stock, pattern); the stock library's per-slot choice;
every card's name, kicker, text, chips and link; the Claim button by kind (#215). **Moved:** photo credits from the
stats line onto the photo. **Dropped:** nothing. **Side effect:** a card that would have repeated a stock photo now shows
the brand's pattern.

## Admin's test (each new state a visitor or Jason sees)
- A card with no distance: the place has no point of its own yet (only its ZIP's middle); nothing to press. It gets its miles
  when the geocoder, the crawler or staff place it (Geocoder's existing paths: Edit listing → Pick on the map).
- "under ½ mi": the place is right there; nothing to press.
- "© Name · CC BY-SA 2.0" on a photo: whose photo and its licence; the i (point or press and hold) gives the full credit.
- "Stock photo · …": a representative photo of the kind of place, not this one; the place's own photo replaces it when added.
- The pattern on a memorial or a repeated stock slot: no photo of its own yet; adding one (the listing's Hero photos) shows it.
- "Run by <agency>" on a public place's page: who runs it; "Staff? Claim it" for the agency's staff.

Proof screenshots: `card-fixes/proof/` (the strip at 1280 and 390, a CC BY-SA card at 390 on a pale sky, landmarks at 390).

## Tests
- `tools/design-tests/card-fixes/run.php <out>`: the release's own `dv_mi`, `dv_credit` and `dv_card_photos` with stand-ins
  for the photo libraries, and its card drawn: 16 checks.
- `tools/design-tests/card-fixes/parks.cjs` on the harness seeded by `parks-seed.sql` (St. Johns; 14 park listings, 5
  Recreation.gov units, 30 landmarks; Commons with CC BY-SA, CC BY, Public domain and CC0, five stock photos, none; two
  memorials, one with its own photo; distances unknown, at a ZIP's middle, 0.3 mi, normal): the real
  `discovery/?view=parks&cluster=st-johns` at 1280 and 390, the landmark grid's three pages each read: no stock photo twice,
  no "0 mi", no distance at a ZIP's middle, "under ½ mi", normal miles, the memorials' photos and patterns, every credit
  whole (author and licence) even on the strip's narrow cards, a long name wrapped and its licence kept, no sideways
  scroll, 8.85:1 on a pale sky, no script errors: 34 checks. Rendered with sample state; the real run happens on live.
- From the first build: three listing pages (a memorial with a Nexus, one without, a café): "Run by Arizona State Parks &
  Trails" with "Staff? Claim it"; no badge; "Unclaimed" with "Claim it".
