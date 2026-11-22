# Cards and photos (Design and trips, 2026-11-22)

Built on Sync 12:25 UTC (ca2f7ab) as `card-fixes.zip`. Source: `handoffs/screens-proposal-2026-11-22.md` A4–A6, A13,
B3, approved by Jason 2026-11-22. Build: `card-fixes/build-cf.py` (reads the mirror; rerun to rebuild).

| Behaviour | Source |
|---|---|
| A card's distance shows only when it says something: none when unknown, infinite, or under half a mile from the place's nearest point (a park placed only by its town read "0 mi"); `dv_mi()` on the park, listing-park and landmark cards. **Premise corrected:** the hand-off read "0 mi" as "no distance known"; the code shows it's a place geocoded to its town's own point | A4; principle 4 |
| Each card photo's credit is on the photo with an **i** (`.xc-cr`, its full credit and "Stock photo (representative)" on point or press-and-hold), not a line under every card; Commons credits too | A5; Jason 2026-11-17 (credit on the photo with an i) |
| The corner icon sits on a dark disc (`rgba(42,27,18,.62)`); the credit's backing `rgba(42,27,18,.8)`: 8.8:1 over a pale sky | A6; css-theme-brand §3 |
| The same stock photo never shows twice in one list: the pattern instead (stricter than "neighbouring": no grid row width to guess) | B3 (Jason, 2026-11-22) |
| A memorial or monument (by its name or kind) never gets a stock photo: its own, the Commons match, else the pattern; 0143's order otherwise kept | B3 (Jason, 2026-11-22); decisions/0143 |
| A public place, or one on a nation's land (`Claims::kind`), shows "Run by <agency>" from its Nexus, never "Unclaimed"; with no Nexus, no badge; a business keeps "Unclaimed" | A13; decisions/0070 lines 223–226 |
| FAQ "Where do I find parks and landmarks?" updated in place | CLAUDE.md "Keep the FAQ current" |

**Kept:** 0143's photo order (own, Commons by name, Commons found, stock, pattern); the stock library's per-slot choice;
every card's name, kicker, text, chips and link; the Claim button by kind (#215). **Moved:** photo credits from the
stats line onto the photo. **Dropped:** nothing. **Side effect:** a card that would have repeated a stock photo now shows
the brand's pattern.

## Tests
- `tools/design-tests/card-fixes/run.php <out>`: the release's own `dv_mi` and `dv_card_photos` with stand-ins for the
  photo libraries, and its card drawn: 10 checks.
- On the harness: three listing pages (a memorial with a Nexus, one without, a café): "Run by Arizona State Parks &
  Trails" with "Staff? Claim it"; no badge; "Unclaimed" with "Claim it".
- Rendered at 1280 and 390: the credit inside the photo, no sideways scroll, 8.83:1 contrast on a pale sky.
