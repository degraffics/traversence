# 0060: Search & Suggestion Architecture

**Status:** Accepted, 2026-10-02. Phases A and B built; Phase C to come.
**Source:** Jason's "Traversence Search & Suggestion Architecture Specification (Full Final)", with the amendments
agreed in discussion below. Builds on decisions/0053 (universal search) and 0058 §12, §25, §35, §36.

## Context

Typing "Lyman" with the place set to the Greater St. Johns Area searched Lyman, Nebraska, and found nothing.
- The query reader took any town in the national ZIP list as the place when it was the whole search.
- The check meant to stop that ("is something on Traversence called this?") looked only at Recreation.gov places and
  landmarks, not listings, so Lyman Lake State Park didn't count.
- The typed town then replaced the person's place for the search, and the results were empty.

## Decision

### Principles

1. **The place a person sets is never changed by a search.** Search reads it and starts there. A typed place
   ("pizza in Show Low") applies to that one search only; the Location the person set stays as it is.
2. **Local first, never only local.** What matches in the place comes first. What matches beyond it follows, nearest
   first, labelled with how far it is. A search can always reach past the set place by what it matches, not by moving
   the place.
3. **The 5 W's are read in a fixed order before matching:**
   1. a 2-letter minimum;
   2. **Where**: the set place, unless "in/near/at/around" names another;
   3. **What**: the subject left once place words and describing words are gone;
   4. **When**: open now, tonight, today, this weekend, a day of the week (seasonal words when events exist);
   5. **Who**: who it's for;
   6. **Why**: what they want to do, read from the words around it (token window), never a blacklist.
4. **Two tiers with their own timing.**
   - **Tier 1, suggestions as you type:** about 120ms after a keystroke. Direct hits by name or tag, local first, each
     with its place and kind ("St. Johns › Parks"). One tap opens it.
   - **Tier 2, the full search:** on Enter, a tap, or a longer pause (about 700ms). Local, then beyond, then the
     controls for widening.

### Amendments to the spec, agreed in discussion

- **Never adjust the set location** (above). The spec's "explicit temporary location override" is per search only.
- **Local first doesn't block connection points outside it** (above): Tier 2 always includes what matches beyond.
- **The parser stays on the server.** Knowing that "Lyman" names a local park, or that "pizza" is a category, needs the
  data. It's one fast request; the step order is what matters.
- **Separate timing for the two tiers** (agreed).
- **A town outside our regions is never the place** unless the search says "in/near/at/around" it or names its state.
  We hold nothing there, so it could only ever return nothing.

## Phases

### Phase A (built 2026-10-02)

- **Where:** a town outside our regions counts only after "in/near/at/around" or with its state, even when it's the
  whole search (`UniversalSearch::findPlace`, `strict`). Towns in our regions read as before ("show low pizza").
- **"Something here is called that"** now includes listings, as well as outdoor places and landmarks
  (`namesSomething`).
- **Beyond:** with a place and something to match, Tier 2 adds what matches beyond the place within 250 miles of its
  middle, nearest first, up to 12 (`results.beyond`; `SearchIndex::find` options `not_place` and `strict`, items carry
  `miles`). Only real matches: no loose any-word try. Topics aren't included. When the "none here" box already lists
  nearby businesses by town, beyond leaves businesses out.
- **The panel** shows "Beyond <place> (N, nearest first)" under the local results: three rows, then "Show N more".
- **Not built yet:** a typed place shown as a suggestion rather than a chip.

### Phase B (built 2026-10-02)

- **Tier 1, `GET /api/search.php?suggest=1&q=`** (`UniversalSearch::suggest`): names that start with the words (or have
  a word starting with them), in the person's place first, then beyond it within 250 miles, nearest first. Each row
  has its place › kind line ("St. Johns › State Parks", or "Pizza · 38 mi" beyond). Also:
  - **categories** that start with the words ("All of this kind");
  - **kinds of place and things to do** (`TAGS`): "spr" offers Springs and Hot springs as well as Springerville;
    "boa" offers Boating. Petroglyphs is one: public sites have them, and results keep the visibility rules (§26);
  - **towns and town areas in our regions**, offered to tap, never switched to;
  - at most 8 rows: help question, the place's own, kinds and towns, then beyond.
- **It reads the whole context, not just places:** listings, outdoor places, stories, groups, categories, kinds and
  towns all match. Context-free prefixes ("spr") get several kinds of answer side by side.
- **"Help" alone asks back:** "Are you looking for… Help using Traversence (Help Center and FAQ) or Help in
  <place>". Both tiers ask (`askHelp`). From "hel" on, as you type.
- **The token window:** a word next to "help" names the help ("housekeeping help", "legal help"). That's a local
  service, not "Getting help": the why chip isn't set and "help" is dropped from the words.
- **A kind or activity typed in full** ("springs", "boating") puts outdoor places first (a why chip, "Natural features"
  or "Things to do"), never hiding businesses. Plurals also match the singular (springs → Malpais Spring).
- **Beyond** takes the 40 best matches, then the nearest 12, so a nearby real match isn't crowded out.
- **"Search the broader region for '<words>'"** when nothing in the place starts with them. One tap runs the full
  search with its Beyond list; the place stays as set.
- **Timing:** suggestions about 120ms after a key; the full search after about 700ms, on Enter or a tap. The full
  search replaces the quick list only when it finds something ("lym" keeps Lyman Lake on top). Enter runs the full
  search; Enter again opens the first result.

- **The place picker's Destination** (`js/location-scope.js`): a state being typed after a comma ("St. Johns, A")
  no longer loses the town. The words before the comma are searched, and the towns are kept whose state starts
  with what's after it (its code or name). Our areas and regions always stay.

- **A town chosen in its town area is the place itself.** Choosing "Saint Johns, AZ" set the place to The Greater
  St. Johns Area, so search counted Concho as local and the chip disagreed with the Location line. The panel now sends
  the chosen town (`town=`) with the area. When it's one of the area's own towns, its ZIPs are the place
  (`placeFromScope`, `town` flag; `SearchIndex::whereSql` uses only those ZIPs). The rest of the area comes under
  "Beyond <town>", nearest first. "See all … in the directory" names the area, since the directory counts the
  whole area.

- **Nothing has all the words** ("news new hat"): each word is counted on its own around the place (within 250 mi),
  and Suggestions offers **Search "news" · 8 found**, **Search "hat" · 1 found** (`split`). The "none here" box says
  "Nothing has all of these words. Try them one at a time under Suggestions." Describing words ("new") aren't offered.
- **Short words match their plural** ("hat" finds Hats Off): a 3-letter word that matches whole also tries its plural.
- **Category lines in Suggestions** wrap instead of cutting off the place, and drop trade codes like "(Whls)".

- **Why read as an action** ("Going fishing for apache trout", "where can we camp", "take the kids swimming"): an
  activity (`ACTIVITIES`: fishing, hiking, camping, boating, hunting, swimming, stargazing, skiing, horseback riding,
  biking, golf, picnics, birding, climbing, off-roading) said with a going-word ("go", "want to", "where can we",
  "take the kids"), or an "-ing" word, or the word alone. It becomes a "Going fishing" chip (× to search the words
  instead) and a line: "Looking for places to go fishing near <place>, with 'apache trout' first."
  - The activity is searched by the words places use (`ACT_WORDS`: a hike is at a trail or trailhead, fishing at a
    lake, pond or river), any of them, whole words only ("lake" isn't "Lakeside").
  - Only places for doing things count: recreation places, outdoor places, groups and stories, or a business named
    for the activity itself (a bait shop says "fishing"). A church named Silver Lakes isn't one.
  - The rest of the words ("apache trout") put the places that mention them first, never hiding the others.
  - Not an activity: "fish tacos" (dinner), "fish" alone, and a shop's kind typed without a going-word ("fishing bait"
    is the Fishing Bait category).
  - Going-words can chain ("where can I go stargazing"); question words never rank.
  - An activity skips the "none here" box and the per-word fallback: Beyond carries it, nearest first. With nothing in
    reach: "Nothing listed for stargazing near <place> yet. Know a spot? Tell us below."
  - In whole-word mode a long word (6+ letters, "stargazing") still counts in a place's description.
  - One thing spelled two ways reads as one: "star gazing", "Star gaz" (still typing), "stargaz" are stargazing.
  - "Near me", "around me": the person's place. With no place set, a line offers "Choose your place or use
    Auto-detect"; until then it searches everywhere. We never track where anyone is.
  - Words read as an activity are never offered as a person's name ("Looking for a person?").
- **The panel** hides "Search the broader region" when no place is set (there's nothing broader), and hides the
  Suggestions heading when there's nothing under it.
- **Beyond** never repeats something already listed in the place (a group with no place shows in both).
- **The panel's head row** stays on one line on wider screens: Location, Search Directory, then Distance at the top
  right. The slider's last step reads ∞ (no limit).

### Phase C

- **Graduated widening with counts:** the place, its area, its region, everywhere, each one tap.
- **A split view** for words that could mean either: platform support or local services.
- **Seasonal "When" words** once events exist.

## Consequences

- A search never leaves a person at a dead end because their place has nothing: what's nearest beyond it shows.
- Results cost one more indexed query when a place is set and there are words to match.
- "Lyman" finds Lyman Lake State Park whether it falls inside St. Johns or just outside it.
