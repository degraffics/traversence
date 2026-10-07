# 0067. What words mean: from a word to the kinds of place that serve it

Date: 2026-10-07
Status: Accepted

## Context

A review of four searches showed search was gated by a known vocabulary with nothing behind it when a word missed:

- **"cardio"** was flagged as unknown, and "Did you mean" offered cards, cargo and caruso (spelling only).
- **"xrays"** got "rats". Hyphens, spaces and run-together words weren't normalised, so x-ray, xray and xrays never met,
  and nothing linked them to Diagnostic Imaging Centers.
- **"mushroom"** was a known word (WordNet) with nothing behind it: no category or listing used it, so Enter found
  nothing and the dropdown showed only "Search for…".
- **"St. J"** worked: places had alias handling, search terms didn't.

"Recognized" meant: a word from our category names, the synonym table, situation phrases, taught senses or area names;
a whole word in a listing's text; or a town (`SearchText::known`). WordNet (ADR 0064) only said whether a word is
English, and its families served the situation reasoner, not categories.

## Decision

1. **Words are normalised like places.** Hyphens, spaces and run-together words are one word (x-ray = xray = x ray),
   plurals are their singular (`Concepts::canon`, `Concepts::squash`). Two or three words that are one thing ("x ray",
   "ct scan") read as one in the lexicon.
2. **A concept layer maps words to categories** (`api/lib/data/concepts.php`, read by `Concepts::kinds`), built by
   `workers/lexicon/build_concepts.py` from two sources:
   - **Seed words per category** (`workers/lexicon/category-terms.tsv`): the everyday words people search a kind of place
     by ("cardio", "x-ray", "haircut", "bread"), about 500 categories. Each is a direct way in. This is data, one line
     per category, not a fix for one word; edit it and rebuild.
   - **WordNet 3.1**, which carries each category further. A category reaches down through the kinds of its words and
     its seeds ("bread" → sourdough, bagel; "dog" → spaniel, poodle), its related forms (radiology → radiologist), and
     what the definitions of its trade's place name (baker → bakery: "breads and cakes and pastries"). A searched word
     reaches up through what it is a kind of and its definition. They meet as TF-IDF vectors (cosine), each sense of
     the word on its own, rarely used senses counting for less (WordNet's usage counts), and each category's words
     read in the sense its parent and listings point to (the medical "imaging", not imagination).
   - A word WordNet doesn't have reads through the words it starts ("cardio" → cardiology, cardiovascular), weaker.
   - People and places by name (WordNet's instances: George Washington, John Ford) are never a kind of thing, and a
     word that names a town or a state ("washington", "ford") gets no closest kinds: it's a place. A word with its own
     seed takes WordNet's other kinds only when they're close ("x ray" is the scan, not the physics).
   - Measured on 60 everyday words and on 50 words in no seed: the seeds answer their words directly; for the 50
     held-out words, WordNet alone puts a right kind first for half, a related-but-not-best kind for a further eighth
     (sedan → Automobile Renting), and nothing for the rest rather than a wrong guess. The floor (0.08) was chosen for
     that: an empty answer falls back to steps 4 and 5, a wrong one misleads.
3. **"Did you mean" offers only real slips.** A word the concepts understand is never "corrected" ("cardio", "xrays").
   A slip may be one letter off in a word of five letters or fewer, two up to eight letters, three beyond. Candidates
   are words that find something (category, synonym, situation and business-name words).
4. **No dead ends.** After Enter, when the words aren't a kind we have by name, **Closest kinds for "…"** shows the
   kinds they mean, each with how many are in the place (one tap searches it). When nothing is found by name or kind,
   **Mentioning "…"** lists listings and places whose description mentions the words. **Suggest it** still follows.
5. **Typing shows what's behind a word.** The kinds a word means appear as rows while typing, with how many are in the
   place ("Diagnostic Imaging Centers · St. Johns › 2 here · for "xrays"").

## Consequences

- Rebuild `concepts.php` when categories change (export SQL in `workers/lexicon/README.md`). A new category is still
  found by its own words until then.
- What people open after a search (Learning, ADR 0063) keeps adjusting on top: the concept layer is the starting point,
  not the last word.
- The data file is large (every English word that leads somewhere). PHP's opcache keeps it in memory; without opcache
  it's parsed per search request.
- `api/lib/synonyms.php` stays for now; the seeds supersede it and can absorb it.
- 2026-10-07, later: never a blank (Jason: "show nothing: not sure… try and modify your search, maybe"). Links between
  0.04 and 0.08 are kept as **maybes**: after Enter, under "Not sure what "…" means here. Maybe" (or "Maybe" after the
  sure kinds); while typing, their rows start "Maybe?". With nothing at all, search says "Not sure what "…" means. Try
  changing your search: fewer words, another word for it, or remove a chip above." (`Concepts::SURE`, `Concepts::MIN`).
- 2026-10-07, later: **word embeddings** join WordNet (Jason: "go for it"). spaCy's `en_core_web_lg` vectors (Explosion
  Vectors, CC0: 300 dimensions, trained on web text, news, Wikipedia and subtitles; about 500,000 words, each its own
  vector) place words by meaning: "burrito" near "tacos", "sedan" near "suv", "treadmill" near "gym". The build reads
  them (`CONCEPTS_VECTORS`); the site never does, and nothing about a search leaves the server. (The medium model,
  20,000 vectors shared among its words, was tried first: rare words borrowed a common word's vector, so "parka" read as
  "parks" and "burrito" as "pizza".)
  - Each category gets the vectors of its seed words and its name. A word scores by its nearest seed (three quarters)
    and the category's average (a quarter), blended 60/40 with WordNet's link (scaled so 0.25 is full).
  - Measured on 110 words: of the 50 no seed has, right first for 43 (WordNet alone: 27), right in the top three for 4
    more, wrong for 3 (close calls: whiskey → Beer & Ale before Liquor), and never empty (WordNet alone: 12). At 0.60 and
    above (**sure**) none of the 110 was wrong; 0.30 to 0.60 is a **maybe**. The data file carries these two thresholds
    (`sure`, `min`), so the site reads them from it.
  - Words WordNet doesn't have but people type (cardio, vape, airbnb, ebike) are in the vectors, so they're covered too.
- 2026-10-08: several words are read together, not word by word. A kind counts by how much of the search it answers
  (its score averaged over the words that lead anywhere), so one word can't carry it: in "cooking classes for outdoor
  cooking", "classes" alone no longer brings Dancing Instruction. When a situation answers the words ("Cooking and
  baking", "Flat tire"), its own list is the answer and no kinds read from single words are added under it. A kind
  shows once, whether it came from its name or from what the words mean.
- 2026-10-08: **missing kinds are found and seeded** (Jason: "We should be able to recognize missing categories and seed
  them"). The nightly drafts run (`SearchDrafts::missingKinds`) proposes a new kind of place two ways: a live situation's
  need that no category holds (found on the data: "Cooking and baking" › Cooking classes and Kitchen supplies), and
  searches that keep finding nothing and mean no kind we have (grouped by their words, plurals and order aside). Each
  draft names the kind, suggests where it belongs (where the nearest kinds we have sit: Cooking Classes under Education &
  Childcare) and lists the words people used, with the searches behind it. A person approves it in Admin › Search tools ›
  Drafts (name, parent and words editable): never automatic, since it changes the directory. Approving adds the
  category, its words, and the situation need it came from now shows it; logged with Undo (turns it off, removes the
  words, restores the need).
  - **Words for each kind are live** (`category_terms`, `api/migrations/2026-11-07_category_terms.sql`): staff add or
    remove the words people search a kind by in Admin › Search tools › Words for each kind, and search reads them on
    the next search, no rebuild. The next rebuild of `concepts.php` can take them in for WordNet and embeddings too.
  - A new kind starts with no listings, so search doesn't offer it as a place to go until listings arrive (owners,
    staff, or the crawler).
- 2026-10-08, later: **added on its own, then watched** (Jason: "Shouldn't need approval… automatically done against
  authority or confidence", and unused ones are monitored). A missing kind is created by the nightly run when
  **authority** backs it (a live situation already offers it) or **confidence** does (twice the usual searches, 6 in 30
  days, and a clear kind it belongs under); anything less waits and is rechecked each night. Its parent is where the
  nearest kinds and the situation's other needs sit, counted equally (Kitchen Supplies: Food & Dining). Logged as
  search's own change with Undo. **Monitoring:** a kind search added that, 60 days on, has no listings, fewer than 3
  searches and nothing opened from it is turned off again (kept, words removed, the need restored), and it isn't made
  again unless three times the searches ask for it; the same holds after a person's Undo. Kinds people added are never
  touched. A person can still add a waiting one early from Drafts.
- 2026-10-08, later: **the crawler fills new kinds** (Jason: "build the crawler for new kinds"). When search adds a kind,
  it queues a crawler target for it in every town we cover (43 towns: 86 targets for Cooking Classes and Kitchen
  Supplies; `api/migrations/2026-11-08_crawl_targets_kinds.sql`). The worker looks for it by the kind's words:
  OpenStreetMap names (`name~"cooking class|culinary|…"`) and, with a search key, a web search in the town, keeping only
  a business's own site that names the town, carries the words and shows a ZIP there. Facts come from that site, as
  for every crawl. These are our own towns, so nothing is held for a person: Listing Intake's usual checks and
  auto-import decide, and the kind shows in category suggestions first. The 60-day watch (no listings → retired) gives
  the crawler that long to find them. Targets show in Admin › Search › Search demand as "new kind, Cooking Classes".

