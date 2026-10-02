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

### Phase C

- **Graduated widening with counts:** the place, its area, its region, everywhere, each one tap.
- **A split view** for words that could mean either: platform support or local services.
- **Seasonal "When" words** once events exist.

## Consequences

- A search never leaves a person at a dead end because their place has nothing: what's nearest beyond it shows.
- Results cost one more indexed query when a place is set and there are words to match.
- "Lyman" finds Lyman Lake State Park whether it falls inside St. Johns or just outside it.
