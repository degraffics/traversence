# 0061: Situations: an intent map, the Chameleon Filter as router, and search that learns

**Status:** Accepted, 2026-10-02. Built in three batches (below). Replaces Phases B (in part) and C of decisions/0060;
0060's principles stand: the place someone sets is never changed, and results are local first, never only local.
**Source:** discussion with Jason on 2026-10-02 (the "Search & Suggestion Architecture" spec, the Day One / Day Two
lifecycle, and the Relational Intent Map).

## Context

Search was built from word rules: describing words, going-words, activity lists, plurals, prefixes and fallbacks.
Each fix worked for the phrase in front of us and missed the next one ("best place to star", "job interview coming
up", "I can't pay my bills"). People search the way they think: a situation ("the power went out", "stuck on the
highway", "lost my keys"), not a directory category. Search has to meet that thought, then find what we hold.

## Decision

### Principles

1. **Day One is pre-coded and deterministic; Day Two learns from use.** No AI model is needed or used. (AI could be
   added later as one more source of suggestions; nothing depends on it.)
2. **Suggestions follow the person's thinking, not our data model.** While typing, show only suggestions: situations
   and their needs, direct hits, and learned completions. **Results run only on Enter or a tap,** never on a pause, so
   half-typed words ("best place to star") never produce results.
3. **The Relational Intent Map** (the *situation map*) bridges a human situation and the directory's assets. It's
   data, not code: anyone with the right role edits it in Admin, and learning proposes additions.
4. **The Chameleon Filter routes by the reading** (brand.md §5): each need goes to its track. The page someone searched
   from only breaks ties.
   - **Resident (Get Local):** "stuck on the highway", "fix my car": Call, open now, nearest.
   - **Traveler (Let's Explore):** "stargazing", "history of St. Johns": places, landmarks, stories.
   - **Community:** "meet people", "groups for new parents": groups (events when they exist).
   - **Mixed:** "job interview": each need to its own track.
5. **Information that can't complete the task isn't a solution.** Each need says what a solution must have (a phone,
   hours or "24 hours", an address or pin). A listing missing it isn't offered for that need; it stays in the
   directory, and the gap is counted (an Admin report, and a reason for the business to claim and complete it).
6. **Danger.** A fixed, Admin-only list of danger situations ("smell gas", "car accident", "stuck on the road") puts
   "If anyone is in danger, call 911." on top. Learning may add solutions to a danger situation, never declare a new
   one.
7. **Sensitive searches are searches.** Shown plainly, counted like any other, kept as a recent search in the person's
   browser, never tied to who typed them. Search shows what's available; it doesn't decide what someone does with it.
8. **Counts keep the words typed** (by area and day), never who: no account, device, IP or session. This changes
   decisions/0053 §5, which counted the reading only.

### The situation map

| Part | What it holds |
|---|---|
| **Situation** | label shown ("Stuck on the road"), urgency (normal, urgent), danger (Admin only), track, status (live, may help, learning, retired, draft), confidence, source (seed, admin, crawler) |
| **Triggers** | many phrases per situation ("stuck on the highway", "broke down on the road", "car died", "need a tow"), stored normalised |
| **Needs** | ordered; each has a label ("Towing"), its track, the categories and words that find it, what a solution requires (phone, hours, address), and how far to reach |
| **Resources** | for needs that aren't businesses (an outage line, highway patrol non-emergency, 211): in **resource guides**, one per area, written in the Traversence voice, each entry structured (name, phone, hours, what it's for, area, official source, last checked) |

**Matching (Day One):** normalise (case, contractions, punctuation, plurals), then match whole phrases, longest
first ("power went out" beats "power" and "out"). Several situations in one search combine ("flat tire on the
highway at night"): their needs merge, without repeats, and "night" puts open-now and 24-hour first. As someone
types, partial phrases match ("the power w…" offers Power out).

**Urgent display:** Call, open now or 24 hours, distance and address first; no stories or long descriptions.

**Tribal land (decisions/0058 §26):** on a nation's land, needs map to the nation's own services only where the
nation lists them publicly, under its own name.

### Learning (Day Two)

1. **What people open:** search words, area and what was opened, as counts. A thing opened often after a phrase rises
   for that phrase; phrases people complete become suggestions as others type.
2. **New words and local names:** frequent searches that find little appear for review ("the rez", "upper lake").
3. **What's missing:** zero-result searches counted by area ("firewood in St. Johns, 40 times this month").
4. **Token windows:** new "<service> help" pairs counted, so service words grow.

### The crawler and ingestion pipeline: our sources first (builds on decisions/0045)

The crawler is already source-first (decisions/0045): every website that confirms places goes into our own source
list (`reference_sources`, Admin → Sources) and is read directly from then on; a paid search covers only what no source
does. Situations follow the same rule, in this order:

1. **Our own index and sources first.** For a situation's needs, the crawler looks in our listings, places and stories,
   and in the sources we already read regularly (county and municipal pages, chambers, tourism sites, tribal
   governments' public service pages).
2. **Tier 1, the free baseline:** official open data: Wikidata, state open-data portals, county GIS, municipal
   websites. Core assets, the geographic hierarchy, official service providers. About 90% of the structure.
3. **Tier 2, Tavily (already in the worker, `TAVILY_API_KEY`):** only for a gap that steps 1 and 2 can't fill; only
   during admin indexing or content work, never on a keystroke; target domains limited to government (.gov), education
   (.edu) and verified local news.
4. **Every useful page Tavily finds becomes a source.** A page that lists several places or resources joins the source
   list ("Read regularly", per decisions/0045), so the next time it's read directly with no paid search. Paid searches
   shrink over time instead of repeating.

### Confidence for situations found by the crawler (0 to 1)

| Signal | Weight | Measured as |
|---|---|---|
| Behaviour | 0.45 | share of searches for the phrase that opened that kind of solution, as a cautious lower bound (Wilson) |
| Outside sources | 0.25 | independent Tier 1 / Tier 2 sources naming that solution for the situation (3 or more is full) |
| Text match | 0.20 | overlap between the phrase and the solution's category and description words |
| Stability | 0.10 | seen over 14+ days or in 3+ areas |

- **Gates, whatever the score:** at least 10 searches; solutions complete (principle 5); tribal land rules; never a
  sacred or restricted site.
- **Thresholds, cautious at first:** **1.00** publishes automatically; **0.85** shows as "may also help", below the
  main results; under 0.85 keeps learning. They can be lowered once there's a track record.
- **Self-correcting:** if people search again or leave without opening anything, the score falls and the mapping
  retires on its own.
- **Phone numbers and resources:** published only when the number appears on the provider's own official site;
  re-checked monthly and unpublished if it disappears.
- **Oversight, not approval:** an Admin view shows what was added and why (score and sources). Nobody has to approve
  everyday additions.

## Build

1. **Batch 1 (search core):** results on Enter or a tap only; the situation map tables and matching; urgent mode with
   the 911 line; the incomplete-information rule; Chameleon routing per need; seed situations; counting the words.
2. **Batch 2 (Admin):** the situation editor (danger Admin-only), resource guides, the review and oversight view.
3. **Batch 3 (learning and the crawler):** opens and completions, the missing report, Tier 1 and Tier 2 ingestion,
   confidence scoring and retirement.

## Consequences

- Search stops growing by special cases: new understanding is a row in the situation map, not code.
- Results are slower to appear while typing (by design): suggestions first, results on intent.
- The counts change (words kept, never who); the FAQ and Our approach say so.
- Some listings stop appearing for urgent needs until they're complete; the Admin report shows which.

## Progress

- **2026-10-02, batch 1:**
  - Tables `situations`, `situation_triggers`, `situation_needs`, `search_terms` (`2026-10-18_situations.sql`).
  - `api/lib/Situations.php`: seed (73 situations, 478 phrases, 175 needs), seeded the first time search reads it
    and never over Admin edits; normalising and phrase matching (longest first, up to two words between, several
    situations combine); Tier 1 situation suggestions ("star" offers Starting a business and Stargazing; leading "the",
    "my", "I" are skipped); answers per need, local first then nearest, open-now first when urgent, with the
    incomplete-information rule (a listing missing its need's phone, hours or place isn't offered for it).
  - A need that names categories takes businesses from those categories only; its words find outdoor places,
    stories, groups and experiences.
  - Search returns `situation`; the panel shows the 911 line for danger situations, then each need marked with its
    track, Call first when urgent, and folds everything else under "Everything else for …".
  - Results run only on Enter or a tap; the counts beacon says whether anything was found; `search_terms` keeps the
    words by area and day.
  - Not yet: the Admin editor, resource guides and the oversight view (batch 2); learning and the crawler (batch 3).
- **2026-10-02, batch 1 fixes (after the first live test):**
  - Typing no longer leaves the last search's results on screen: they clear as soon as the words change, and the
    first line while typing is always "Search for '…' ↵" (Enter or a tap). The "Search the broader region" line went:
    the full search already shows Beyond.
  - Clicking into the box selects its words (typing replaces them); a second click places the cursor. Esc clears the
    words first, then closes.
  - Spelling slips are forgiven when reading a situation: a word one letter off one of our phrases' words (two for 7+
    letters) is read as it ("broke my ancle" is ankle). Hedges at the start of a phrase being typed are skipped ("I
    think I may have broken my…").
  - A new situation, **An injury** (urgent, not danger): urgent care, ER, bone and joint doctors, braces and
    crutches, getting better. "Someone is hurt" keeps the life-threatening phrases and the 911 line.
  - Seed versions (`SEED_VERSION`, kept in `search_index_state`): seed situations are brought up to date on the live
    site; ones edited in Admin never are.
- **2026-10-02, answers while typing (Jason: "having to enter is bugging me"):**
  - Principle 2 changes: results no longer wait only for Enter. When the words name a situation, the suggestions show
    a **preview of its answer** (each need that has an answer, its nearest one or two, with Call), from
    `?suggest=1` (`preview`). After a pause of about a second the full results follow below; Enter and the
    "Search for" line still run them at once.
  - What the words might mean stays on top when the full results land ("best place to star" keeps Starting a
    business and Stargazing above its results).
  - Situation suggestions try the words as typed, then without leading filler ("best place to star…" is "star…";
    "place to st…" still offers Somewhere to stay).
  - A new situation, **Can't find my car** (towed or impounded, report it stolen, a ride, a rental); "can't find my
    keys" and "can't find my dog" join their situations. Seed version 3.

- **2026-10-02, reading fragments by the job each word does** (Jason's note on part-of-speech anchors). A search is
  rarely a sentence; each word is read for its role, in this order, and what's left is the thing searched:
  | Role | Words | What it does | Where |
  |---|---|---|---|
  | **Situation** (trigger) | "power went out", "stuck", "broke down" | the situation map answers; checked first, by whole phrase | `Situations::read` |
  | **Modifier** (filter) | "emergency", "urgent", "24 hour", "24/7", "after hours"; "open now"; "cheap", "good", "new" | the first five: open now and 24-hour first, known-closed left out, "emergency" in a name ranks up. "Open now" filters. Describing words ("new", "good", "store", "shop") are dropped | `understand()` When; `DESCRIBE` |
  | **Where** (geometry) | "in", "near", "around", "at"; or a town at the end with no little word ("hardware store St Johns") | the place for this search only; "St", "Mt", "Ft" read as Saint, Mount, Fort | `findPlace()` |
  | **What** (anchor) | "plumber", "hardware", "pizza" | a category when it names one (everyday words via synonyms), otherwise the words | `findCategory()` |
  - "Emergency room", "emergency medical services" and similar stay words (a place, not a filter).
  - Only describing words left after something else was read means nothing to match: "where to buy new" is Shopping,
    not the word "new" (which found New England and every "-New" category).
- **2026-10-02, search fixes from the live test ("where to buy new", "broken down"):**
  - **Trade codes** come off category names wherever they show (`SearchText::catLabel`, the panel's `catName`):
    "-Retail", "-Wholesale", "-New", "(Whls)", "(Mfrs)". "-Used" stays. The plain name is searchable: "automobile parts
    & supplies" finds the "…-Retail-New" category.
  - **Category lines in Suggestions** only when a word typed is one of the category's words (a long word may be its
    root: "plumber" is Plumbing). "Down" isn't Downspouts. None at all when a situation answers.
  - **With a situation**, the rest of the results ("Everything else") match whole words only.
  - **"My place only" with nothing there** says how far the nearest is, with 25 mi, 50 mi and No limit buttons and how
    many each finds (`results.widen`); a tap moves the slider. The first step of decisions/0060 Phase C.
  - **"Looking for a person?"** no longer shows for a category, a situation, an activity, or words that were all read
    as something else.
  - **No place set:** the Distance slider is greyed at ∞ ("Choose a place to limit distance") and the server treats
    the reach as no limit.
  - **Two old slips fixed:** a comment had swallowed the "Did you mean" check and Beyond's Why and Who filters; and an
    empty "none here" box (no towns) was hiding businesses from Beyond.
- **2026-10-02, search panel text:** all text in the panel is small (headings .85rem, rows .82rem, second lines
  .72rem; the phone's box stays 16px so iOS doesn't zoom), and the head row's labels never wrap ("Search Directory:"
  on one line). Search logic is on hold: Jason is writing the exact logic he wants, to implement as specified.

## Addendum, 2026-10-02: the 5 W's deterministic parser (Jason's spec)

Jason's "Traversence Search Architecture: 5 W's Deterministic Parser" is adopted as the search logic: syntax over
grammar, no AI. Each W and how it's read:

| W | Read as | Built |
|---|---|---|
| **Why** (read first) | phrases and Why words against the situation map | Phrases match longest first; the situation's own words are used up, so they're never searched as a What. A Why word with no phrase ("stuck in the elevator", "my pipes are leak") offers the situations that use it, never answers: `Situations::ANCHORS`, `Situations::near()`, ranked by the other words they share. Beside other results, only a situation sharing another word is offered ("lost lake" offers nothing). |
| **What** | the core noun: a category, an everyday word for one, a kind of place or thing to do | The **What gate**: the words left must be ones the directory knows (`whatVocab`: category words, synonyms, kinds, activities, features) or something's name. **The head noun is the What** ("commercial plumber" is a plumber); words before it are **attributes** that put the best first, never required. Known words that together are a kind of thing stay together ("auto parts", "mexican food"). No loose any-word fallback once a What is read. |
| **Where** | a preposition boundary, the gazetteer, or the session's place | "in/near/at/around", a town at the end, "St/Mt/Ft". New: **a landmark is a Where** ("plumber near Lyman Lake": 15 mi around it, `landmarkNamed`). The place someone set is never changed. |
| **When** | time and urgency | "now", "tonight", "today", "weekend", a day; "emergency"/"urgent" ("Emergency: open now first") and "24 hour"/"after hours" ("Open now first"). "New" and "upcoming" wait for dates on listings and events. |
| **Who** | who it's for, or an entity's name | "for kids" and now plain "kids activities" (unless it's a category's own name). A business named outright leads, with **what it offers** and "More like it nearby" (the relational lookup). "Beginners" reads as Learning. |

- **Nothing read:** no list of stray matches ("standing alone on the corner" no longer finds a pregnancy center and
  portable toilets). "Maybe you mean" offers the situations its Why words point to, with "Name the kind of place or
  service, or say what's going on".
- **No Enter gate:** a pause of 700ms does exactly what Enter does. Half-typed words show "Maybe you mean" (where the
  words are heading: "stuck on the hig" is Stuck on the road), never a dead end.
- **Seed version 4:** "stuck in the mud / snow / a ditch / sand", "slid off the road"; "get away", "getaway", "need a
  break", "vacation" (A getaway or day trip); "feel alone", "all alone" (Meeting people).
- The response carries `parse` (why, what, attrs, where, when, who) so the reading can be checked.
- Fixed in passing: the panel failed ("Search is unavailable") whenever nothing matched in the place but something did
  beyond it.

### Progress, 2026-10-02: completions, verbs and kin (from Jason's Google comparisons)

- **Completions while typing, Google-style.** Under "Search for …", what was typed in plain text and what it may
  carry on to in bold; a tap searches it. They stay above the results until one is picked or the words change.
  Deterministic sources, in order (`Situations::complete`, `UniversalSearch::completions`):
  1. situation phrases carrying on from the last words typed, the longest overlap first ("where can i park" → "…and
     sleep in my car", "…overnight for free", "…my car"; "i lost my" → "…keys", "…dog"; "stuck in the" → "…mud");
  2. the Why phrases ("landmarks i should" → "…check out"; "places to" → "…eat", "…stay", "…see");
  3. the 5 W's around a thing named: a place to see takes Who and Why ("parks" → "…for kids", "…worth the drive",
     "…near St. Johns"); anything else takes When and Where ("pizza" → "…open now", "…open this weekend", "…near St.
     Johns"). Never a word already said ("parks near me" isn't offered "near" again).
  - A little word ("me", "the") is never finished into another ("parks near me" isn't "meteor shower").
  - Not yet: completions learned from what people search. `search_terms` keeps the words in their normal form
    ("automobile part and supplie"), not as typed, so they'd read badly; batch 3 keeps a display form, shows only
    phrases searched several times that found something, and only with words we know (never a name).
- **A verb isn't its noun.** "Where can I park" is a new situation, **Somewhere to park** (overnight with an RV or
  camper, rest areas and truck stops, parking lots, parking rules), not businesses named "Park". When a situation's
  phrase uses the word a category was read from, the situation takes it ("where can I park overnight" isn't Parks).
  Seed version 6 (also "stuck in the mud / snow / sand", "park and sleep in my car", "park overnight for free").
- **A category's kin, and shared words.** "Parks" takes in State Parks and National Parks/Preserves (a qualifier
  before the same name). A word other kinds of business also use ("park": RV Parks, Mobile Home Parks, Parking) is
  never read from a business's name, so "parks near me" no longer lists RV and mobile home parks. "Pizza" is in no
  other category, so Dittys Pizza & Pie still counts (`catKin`).
- **Places to see.** "Landmarks", "canyons", "springs" (the kinds of place, `TAGS`) find only places for going (outdoor
  places, stories, explore listings), never a business only named for it ("Landmark Homes"), and skip the "none here"
  business box. "Check out", "worth the drive", "worth seeing", "must see", "should visit" read as Why: explore.
- **A landmark needs a name:** "pizza near the park" isn't "near Park Service".

### Progress, 2026-10-02: predictions only while typing; tools fold on phones (Jason)

- **While typing, only the predictions show:** the "Search for …" line, the completions, and Go straight to (with a
  situation's preview answer). Results, Suggestions, Quick Search, Recent Searches, "Did you mean" and the person row
  wait until a prediction is tapped or Enter is pressed. The search no longer runs on a pause: less load, and nothing
  half-read on screen ("need so" no longer says "Did you mean feed so"). This replaces the 700ms pause above.
- **On a phone, the search tools fold into one line** that says what they're set to ("The Greater St. Johns Area · All
  · 50 mi ▾"). A tap opens Location, Search Directory and Distance; the choice is kept in this browser.
- **Completions keep to the situation the words already name:** "need gas" is a new situation, **Gas for the car**
  (gas stations, propane), and is offered "…station", never "…leak"; "where can i park" still carries on to
  "…overnight for free". The situation the words exactly name leads Go straight to. Seed version 7.
- **Later the same day (Jason):** the built-in clear × inside the search boxes is gone (the close ✕ sits past the pin;
  Esc clears). On a phone the **Location line is the toggle**: "Location: <place> · <directory> · <distance> ▾", with
  ✎ on the same line to change the place; Search Directory and Distance open under it. New situation **A restroom**
  ("need a bathroom", "public restroom", "toilet"): rest areas, gas stations and travel centers, parks and visitor
  centers. A long last word that none of our words start with is read as a slip while typing ("need a bathroon").
  Words that already name a situation get no "open now / near" completions ("bathroom"). Seed version 8.

### Progress, 2026-10-02: Missed searches, and catching two misses (first piece of batch 3, brought forward)

- **Missed searches** (`/admin/search-misses.php`, under Insights; `search_outcomes`, migration
  `2026-10-19_search_outcomes.sql`): how each search someone chose (Enter, a tap, Try it) turned out, as counts of
  the words as typed, area, day and outcome. Never who. Outcomes: **couldn't tell**, **nothing found**, **no place
  set**, **searched again** (nothing opened, another search within a minute), **nothing opened**, **offer ignored**
  ("Maybe you mean" not taken), **feedback** tapped. Opening, calling, peeking, widening or taking a suggestion from
  the results counts as found. The page lists the most-missed words (3 or more searches, so a name typed once never
  shows), by period and area, each with **Try it**. Kept 180 days. The panel reopening on the same words isn't a new
  search.
- **The directory page answers situations itself.** Enter there handed the words to the directory list, which matches
  them word for word ("need a bathroon"). Now, when the words name a situation, the panel answers it and stays open;
  other words still go to the list.
- **No place, no guess.** With no place set, a situation's answer was "nearest" from nowhere (A restroom led with
  Vaseys Paradise near Marble Canyon). Now it shows its needs and asks: "Where are you? Then we'll show the nearest …"
  with **Choose your place or use Auto-detect**, both in the full results and in the preview while typing.
