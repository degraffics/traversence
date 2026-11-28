# Review of UniversalSearch.php against decisions 0060, 0061 and 0067: ranking and kinds

## What the decisions require

**Ranking**
- **Local first, never only local** (0060, Principle 2). What is beyond the place comes nearest first (0060, Phase A and Phase B: Tier 1 "in the person's place first, then beyond it… nearest first").
- **Exact kind first** (0060, Note 2026-11-27). "urgent care" ranks that exact kind first, then the broader health kinds, and never businesses outside the kind.
- **Order of a need's rows** (0061, Note 2026-11-27). Rows go by the need's position first, then distance, and "never by listing number". Pharmacies come before pharmacists. Individuals never appear as places.
- **The page only breaks ties** (0061, Principle 4).
- **Situations lead** (0061, 2026-10-02 progress). "The situation the words exactly name leads Go straight to." A reasoned situation "also leads Go straight to while typing" (meaning layer).
- **Ranked by meaning** (0067, 2026-10-07 item 3). When the words surely mean a kind, that kind comes first, then its places, then what Enter finds. Names that only share a word come after.

**Kinds**
- **The What** (0060 §3.3; 0061 Addendum). The What is the subject left once place words and describing words are gone. The head noun is the What.
- **Activities** (0060, Phase B):
  - A shop's kind typed without a going-word is not an activity.
  - Only places for doing things count for an activity.
  - Activity words are matched whole.
- **Near-me outdoor reach** (0060, near-me note). NEAR_MILES_OUTDOORS (30) applies to "hikes, camping, fishing, parks".
- **Intent taxonomy** (0061). "rent" is an action (Renting tools or equipment). "taxes" leads to the Taxes situation.
- **Urgent health words** (0061, Note 2026-11-27). "urgent care", "walk in clinic" and "need a doctor today" read as urgent. "urgent care" is the Urgent Medical Care kind.
- **Each need to its own track** (0061, Principle 4, Mixed).

---

## List 1: Ordering or scoring with no relevance to the words, the kind or the person's situation

**R1. `findCategory` picks among candidate categories by row id**
- Lines: 1087, 1106, 1114.
- What happens: `ORDER BY parent_id IS NULL, id LIMIT 1`. The root step (1104–1109) reduces "pharmacy", "pharmacies" and "pharmacists" to the same root "pharmac". Whichever of Pharmacies or Pharmacists has the lower id wins.
- Why it matters: THING_WAYS `meds` sends "pharmacy" through this path (2188). The same applies to therapy/therapists (comment at 1103).
- Decision: violates 0061, Note 2026-11-27 ("pharmacies before pharmacists; individuals never as places"; "never by listing number").
- Fix:
  - Rank candidates in this order: exact name, then plain name, then a place-kind over a person-kind, then listings in the place. The ordering at 1097 shows the pattern.
  - Never take a category of individuals when a place-kind matches.

**R2. `exactBusiness` returns any merchant with that exact name, with no place and no ORDER BY**
- Lines: 595–599. The result becomes `$top` at 1307 and leads the whole search.
- What happens: a chain name ("Walgreens", "Dollar General") can lead with a store in another state while one exists in the person's place.
- Decision: violates 0060, Principle 2 (local first).
- Fix: order by in-place first, then distance from the place's centre (`centerOf`). Drop the top slot if the match is beyond the reach.

**R3. `landmarkNamed` picks a nationwide match**
- Lines: 497–498, used at 447–448.
- What happens: it orders by `kind = 'outdoors' DESC, LENGTH(label)` across the whole country, with prefix LIKE. "plumber near Walmart" can centre the 15-mile circle on a Walmart in another state.
- Decision: 0061, Addendum (Where: "a landmark is a Where") combined with 0060, Principle 2.
- Fix: limit to the set place's reach, or order by distance from `self::$near` or the scope centre. Prefer an exact name