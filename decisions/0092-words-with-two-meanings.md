# 0092. Words with two meanings: activities first, the thing after, the words around them decide

- **Status:** Accepted
- **Date:** 2026-10-15
- **Builds on:** 0060 (activities read as a Why), 0061 (situations, word senses), 0067 (concepts from WordNet)

## Context

Jason searched "tubing". The search offered Pipe (wholesale), Plumbers and Mechanical Contractors: "Tubing is also an
activity, so why is our search still struggling with things that have multiple meanings?"

**Why it happened:** the search understands a word through three layers.
1. **What it has been taught:** activities (fishing, hiking, skiing…), activity tags ("Things to do"), situations
   (Snow and skiing, Boating and water…) and word senses (only "fall" so far).
2. **What staff taught it in Admin:** category terms.
3. **The dictionary** (WordNet, decisions/0067), which maps words to kinds of business.

"Tubing" was in none of the first two. The dictionary's "tubing" is a conduit, and the dictionary only ever leads to
businesses, so an activity meaning that isn't a business is lost. Also:
- "snow tubing" found nothing;
- "river tubing" matched places named "River";
- "copper tubing", once tubing was an activity, found a ski resort whose description mentions snow tubing.

## Decision

**Tubing, rafting and sledding are taught as activities** (`UniversalSearch::ACTIVITIES`, `ACT_WORDS`, `TAGS`):
- **tubing:** river tubing, tube or float the river. It finds rivers and river recreation sites.
- **rafting:** white water and river rafting.
- **sledding:** snow tubing, tubing hill, sledding and sled hill. It finds ski areas and sledding hills.

**Situations** (`Situations::SEED`, version 13):
- **A new situation, "On the river: tubing and rafting":** rivers and put-ins, and outfitters, guides and water-sports
  rentals.
- **Snow and skiing** also answers to "snow tubing", "tubing hill" and "sledding hill", and lists ski resorts.
- **The phrases start with "tubing"** ("tubing on the river", "tubing hill"), so typing "tubing" offers both, while
  "copper tubing" contains neither.

**The thing meaning stays** (`2026-11-26_tubing_meanings.sql`): "tubing" is taught to Pipe-Wholesale, Plumbing
Fixtures & Supplies-Wholesale and Hardware-Retail, as a word added in Admin would be. Suggestions for "tubing" are, in
order:
1. Snow and skiing;
2. On the river;
3. hardware, pipe and plumbing suppliers;
4. Tubing (things to do).

**The words around it decide** (`ACT_NOT`):
- "Copper", "pvc", "pex", "steel", "hose", "pipe", "plumbing", "fitting", "hydraulic", "medical", a size or a number
  next to "tubing" make it a thing.
- It's then not read as an activity or as a place to visit, and it isn't matched in descriptions, where it means the
  activity (`SearchIndex::find` `no_about`).
- "copper tubing" and "pvc tubing" offer only pipe and plumbing suppliers.

## Checked on a copy of the site

| Typed | Shows |
|---|---|
| "tubing" | both activities, then pipe |
| "snow tubing" | ski areas (Red River, Santa Fe, Sipapu, Taos) |
| "river tubing", and Enter on "tubing" | rivers |
| "copper tubing", "pvc tubing" | pipe and plumbing suppliers only |
| "rafting" | the river situation, Outfitters, the Rafting tag |
| "sledding" | Snow and skiing |

The 24 standard searches are unchanged.

## Not done: the general fix

The same gap shows elsewhere: "shooting" offers Veterinarians, "zip line" offers linen services, "hot air balloon"
offers party balloons and air-conditioning contractors, and "paintball" offers Paint Stores. Each can be taught the way
tubing was. The general fix is in the dictionary build: WordNet already marks which senses are sports and recreation
(the "act → diversion, recreation, sport" family). `workers/lexicon/build_concepts.py` could map those senses to "Things
to do" instead of dropping them, and mark a word with both a recreation sense and a business sense as two meanings, so
search offers both as it now does for tubing. That would cover most of these words at once.
