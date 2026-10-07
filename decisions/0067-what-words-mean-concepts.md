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
