# Search audit: UniversalSearch.php against decisions 0060, 0061 and 0067

**Scope.** Only `app/lib/UniversalSearch.php` was attached. Several files decide the ranking and the need lists, and none of them were attached:

- **`SearchIndex.php`**: `find`, `rows`, `whereSql`, the weights, and the `nearest` mode.
- **`Situations.php`**: the seed situations and needs, including the "Clinics and urgent care" need the 0061 note cites at `Situations.php:145, :152–156`.
- **Other ranking and kind sources:** `Concepts.php`, `OpenPlaces.php` (`kindFor`, `KINDS`), `Community.php` (`findPlaces`, `directory`), `Experiences.php`, `SearchText.php` (`suggestions`, `where`), `Topics.php`, `Heritage.php`, `SourceIndex.php`, `HelpFind.php`, `Learning.php`.
- **The panel:** `js/search-panel.js`, which holds the "near me alone" everyday list.

Wherever a finding depends on one of these, it says "not shown".

---

## Part 1: Ordering without a relevance signal

### 1.1 `listings()`: category searches come back A–Z (line 3218)

- **What happens.** The query orders by `(x.business_name LIKE :pre) DESC, x.business_name LIMIT 5`, with `:pre = esc($text).'%'`.
  - When the words were read as a category, `understand()` sets `$text = ''` (line 472). `:pre` then becomes `'%'` and matches every row, so the order is purely alphabetical.
  - `in_cat` is computed (line 3190) but never used to order.
  - Distance isn't used at all.
- **Why it matters.** Line 1340's comment says "not an A–Z list", but that guard covers only "a place alone". A search like "urgent care" in Lakeside still gets its Listings group as the first 5 alphabetically.
- **Ranks:** the Listings group rows (line 1352).
- **Breaks:**
  - 0061, Note 2026-11-27: exact kind first, then by need position and distance, never by listing number. Alphabetical is the same failure.
  - 0060 Principles 2: local first, nearest first.
- **Fix:**
  - Order by `in_cat DESC`.
  - Add the name-prefix term only when `$text !== ''`.
  - Then order by distance from `centerOf($place)` (or `$area`), then by index weight.
  - With a category and no words, drop the `:pre` term entirely.

### 1.2 `exactBusiness()`: same-named listings picked arbitrarily (line 596)

- **What happens.** `... e.name = ? LIMIT 1` has no `ORDER BY` and no place filter. A chain or a common name ("Subway", "Dollar General") picks an arbitrary row.
- **Why it matters.** That row becomes `$top`, the lead answer (line 1307), and can be hundreds of miles from the set place.
- **Ranks:** the top answer.
- **Breaks:** 0060 Principles 1–2: the set place is where search starts, local first.
- **Fix:** pass `$place`/`$ctx` in. Order by "in the place" first, then distance from the centre, then `id` only as the final tie-break.

### 1.3 Let's Explore: journeys and experiences always placed ahead (lines 1403–1409, 1416–1417, 3258)

- **What happens.**
  - Journeys and experiences are put in front of every index result with `array_merge($jr/$xp, $items)`, whatever their match strength.
  - The "wider" fallback (lines 1416–1417) sorts only on "is it a topic".
  - Journeys themselves are ordered `ORDER BY (j.likes * 3 + j.comments * 2) DESC, j.published_at DESC` (line 3258). That is popularity and recency; the word match is only a filter.
  - Experiences' own order is not shown.
- **Ranks:** the main result list on the explore track.
- **Breaks:** 0061 Principles 4: "The page someone searched from only breaks ties." Here the page decides the order outright.
- **Fix:**
  - Score journeys and experiences on the same scale as the index items: words matched, title versus body, then distance.
  - Merge them by that score, with track fit added only as a tie-break.
  - In `journeysFor`, order by token-match count first, then engagement.

### 1.4 `meant()`: shared-word names can outrank kinds found by meaning (lines 2558, 2927, with `matchScore` at 2143–2156)

- **What happens.** `byMatch()` re-sorts every suggestion by name-prefix score alone.
  - Category rows found by meaning ("Diagnostic Imaging Centers" for "xrays") and "agree" listings found by kind ("Exit Mobile DJ" for "live mu") don't share their name with the words, so they score 0.
  - They therefore fall below any town or name that starts with the typed letters.
  - This undoes the "kind first" order built at line 2886.
- **Breaks:** 0067, 2026-10-07 "essence" §3: "When the words surely mean a kind of place: that kind first, then its places … names that only share a word come after."
- **Fix:** add a component to `matchScore` for `sure` kind rows and `agree` rows (for example +2.5, matching a whole-word start), or run `byMatch` only within each band built at line 2886.

### 1.5 `matchScore`: the track bonus outweighs real match differences (lines 2154–2156)

- **What happens.** The +0.5 "fits the track" bonus is bigger than many match-quality gaps between partial-prefix matches. Example: 1.2 + 0.5 = 1.7 beats 1.44. A weaker name match can lead because of the page.
- **Breaks:** 0061 Principles 4: the page only breaks ties.
- **Fix:** make the track bonus smaller than the smallest match step (for example 0.01), so it only breaks equ