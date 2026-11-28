# Audit: UniversalSearch.php against decisions 0060, 0061 and 0067 (third pass)

**Scope.** Only `UniversalSearch.php` is attached. Several things this search relies on are not shown, and I have not guessed at them:

- `SearchIndex::find` and `SearchIndex::rows`: how the main result list, the `nearest` order, and the `explore_only` and `strict` options work.
- `Situations.php`: the need lists. The 0061 note cites them at `Situations.php:145` and `:152–156`.
- `OpenPlaces::KINDS` and `kindFor`.
- `Community::directory` and `Community::findPlaces`.
- `Experiences::search`, `Topics::suggest`.
- `SearchIndex::AUDIENCE` and `WHY_LABEL`.
- `concepts.php` and `category-terms.tsv`.
- `js/search-panel.js`, which holds the everyday "near me" kinds list.

The most important need lists (Clinics and urgent care, Pharmacies) live in `Situations.php`, so Part 2 cannot confirm the 0061 2026-11-27 ordering. That file needs its own pass.

---

## Part 1: ordering with no relevance signal, or where a non-relevance key overrides relevance

**1. `listings()` sorts the Listings group A–Z: lines 3218, 1339, 1342**
- **What it ranks:** the Listings group, up to 5 rows. It is still built and returned beside the index results.
- **How:**
  - The sort is `ORDER BY (business_name LIKE :pre) DESC, x.business_name`.
  - With a category and no words, `:pre` is `'%'`, so every row "matches" and the order is pure alphabetical.
  - `x.in_cat` (the listing really is in the category) is computed at 3190 but never used for ordering. A listing that only mentions the category word ranks equally with one in the category.
  - With `$area` set there is no distance order, so the list is A–Z within the radius.
  - The loose fallback at 3228 (any-word) is ranked alphabetically alongside strict matches. It also runs regardless of `$gated`.
- **Second effect:** line 1342 makes `rows[0]` the top answer when its name equals the text. When names repeat (chains), the tie is broken alphabetically, not by place.
- **Breaks:**
  - 0060, Principles 2 (local first, nearest first).
  - 0061, 2026-11-27 note ("never rank by listing number"; kind first, then distance).
  - 0061, 5 W's addendum, What row ("No loose any-word fallback once a What is read").
- **Fix:**
  - Order by `in_cat DESC`, then exact/prefix name match, then distance when `$area` is set, then index weight.
  - Pass `$gated`/`strict` into `listings()` and skip `$run(false)` when it is true.

**2. `exactBusiness()` has no ORDER BY: line 596**
- **What it ranks:** the single business that becomes `$top` (it leads the whole results) when the words exactly equal a name.
- **How:** `LIMIT 1` with no ORDER BY. For a chain name, the database decides, usually the lowest id. Nothing prefers the set place.
- **Breaks:** 0060 Principles 2 (local first); 0061 2026-11-27 note (never by listing number).
- **Fix:** pass `$place` and the centre. Order by in-place first, then distance, then id as a last resort.

**3. `landmarkNamed()` ignores where the person is: line 498**
- **What it ranks:** which landmark "near X" centres on.
- **How:** `ORDER BY kind='outdoors' DESC, LENGTH(label)` over a prefix LIKE. There is no proximity to the set place, so "plumber near Lyman Lake" can centre on a same-prefix lake or business in another state.
- **Breaks:** 0060 Principles 1–2 (start from the set place); 0061, 5 W's addendum, Where row.
- **Fix:**
  - Add the distance from `self::$near` or the scope centre as the second key.
  - Prefer an exact label over a prefix match before using length.

**4. `findCategory()` breaks ties by id: lines 1087, 1106, 1114**
- **What it ranks