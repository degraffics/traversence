# Search audit against decisions 0060, 0061 and 0067

**Scope.** I reviewed the four attached files: `Situations.php`, `SituationReasoner.php`, `SearchIndex.php` and `SearchReading.php`. Line numbers below are the ones printed in those files.

**Line numbers in the 0061 note.** The 0061 note of 2026-11-27 cites `app/lib/Situations.php:145, :152–156`. In the attached numbering, those needs are at **:143–156**: the first needs are at :147 and :154. The file's own header says `api/lib/Situations.php` (:3).

**Not attached, so not assessed:**
- `UniversalSearch.php`: `findPlace`, `nearMe`, `NEAR_ENOUGH`, and the callers that set `nearest`, `strict`, `whole` and `track`.
- `SearchText.php`, `Concepts.php`, `WordSenses.php`, `WordFamilies.php`, `Lexicon.php`.
- `OpenPlaces.php`, `ResourceGuides.php`, `Community::directory`, `Landmarks.php`, `crawler/SourceIndex.php`, `Hours.php`.
- `js/search-panel.js`.
- Decisions 0157, 0164 and 0181. Where a finding depends on 0164's "individuals never as places" guard, it is marked *0164 not shown*.

---

## Part 1: Ordering without a relevance signal

### 1.1 `Situations.php:1142` and `:1188`: a need's answers ignore which kind they are (high)

**What it ranks.** Every situation need's answers, including "Clinics and urgent care", "Emergency room" and "Pharmacies".

**How it works now.**
- The SQL orders by `here DESC, si.weight DESC`.
- The PHP sort is `[!here, open (urgent only), miles]`.
- `catIds` (:1054–1062) merges every pattern's ids into one flat set. The order the need lists its categories in is thrown away.
- When there is no `center`, every item has `miles = 99999` (:1188). The order then falls back to `weight`, then the database's unspecified row order. In practice that is storage or id order, i.e. by listing number. The schema is not shown.

**What it breaks.** 0061's note of 2026-11-27: "Rows are ordered by the need's position first, then distance, never by listing number… pharmacies before pharmacists". Also 0060's note of the same date: "ranks that exact kind first, then the broader health kinds".

**Fix.**
1. Make `catIds` return `[id => patternIndex]`.
2. In `solutions()`, carry `catPos` on each item. Items found by words only get `PHP_INT_MAX`.
3. Sort by `[!here, urgent ? open !== true : 0, catPos, miles ?? INF, label]`.
4. Remove any reliance on SQL order for ties.

0061 says "position first, then distance". Whether "in the place" (0060 local first) outranks position is ambiguous; I've kept `here` first. The owner should confirm this.

### 1.2 `Situations.php:1142`: candidates are cut at 400 before distance is known (high)

**What it ranks.** Which candidates a need gets to choose from.

**How it works now.** `LIMIT 400` is applied after `here DESC, weight DESC`. With no place match, or `reach = 'all'` (no box, :1134), the 400 are picked by weight and then arbitrary order. Only after that does :1179 compute miles and :1188 sort by them. The nearest real answer can be dropped before distance is ever measured.

**What it breaks.** 0060 principle 2: "what matches beyond it follows, nearest first".

**Fix.** When `center` is set, add a distance expression to the `ORDER BY`. A bounding-box squared-degrees term is enough, e.g. `ORDER BY here DESC, ((si.lat-:c0)*(si.lat-:c0)+(si.lon-:c1)*(si.lon-:c1)) ASC`. Alternatively, always apply the box at :1134–1137 using the reach being used.

### 1.3 `Situations.php:575` and `:578`: shared trigger phrases pick a situation by row order (med)

**How it works now.** `phrases()` has no `ORDER BY` (:575), and the `usort` (:578) only sorts by phrase length. Equal-length phrases keep database order. In `read()` (:594–600), the first identical phrase uses up the tokens, so the second situation can never match. The situation that answers is chosen by row and id order, not by meaning.

**Phrases seeded in two situations:**

| Phrase | First situation | Second situation |
|---|---|---|
| "car wont start" | :26 stuck-on-road (danger) | :45 fix-my-car |
| "remodel" | :115 | :400 |
| "drivers license", "renew my license", "register my car" | :70 | :411 |

**What it breaks.** 0061, Matching: "several situations in one search combine".

**Fix.** Either remove the duplicates (see Part 2, row 2.21), or let an identical phrase in several situations match all of them. Track `$used` per phrase string rather than per token.

### 1.4 `Situations.php:651` and `:654`, feeding `:1083–1084`: ties between situations fall to id order (low)

**How it works now.** `read()` loads its situations with `WHERE id IN (…)`, which returns no particular order. It then sorts only by `[danger, urgent]`. Among equals, the order is id order, not the longest or most specific phrase matched. `answer()` uses this order to decide whose needs lead.

**Fix.** Carry the matched phrase's token count, plus whether it came from a phrase or was reasoned. Add it as the third sort key.

### 1.5 `Situations.php:808` and `:820`: Tier 1 situation suggestions (low)

**How it works now.** `sugg