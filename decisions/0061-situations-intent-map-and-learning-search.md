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

