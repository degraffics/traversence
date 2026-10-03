# 0064: Search learns words on its own; people have access and control, not chores

**Status:** Accepted, 2026-10-04.
**Amends:** decisions/0063 §7 ("rules and senses are never auto-promoted"). Ranking was already learned without a
person (decisions/0061).
**Source:** Jason, building "Found a wallet" by hand: "By definition found is to find, so what did I find is the
question, and the builder should be able to see that… Not sure why I am having to build these situations." Then:
"Our system should be intelligent enough to not have a required review and approval for basic word association and
syntax… Adding words that are recognized, then phrases, should be system work that doesn't need human management.
Access and control yes, but having to add or structure this is not."

## Context

Search read each word as a bare label. It didn't know that "found" is a form of "find", that finding has an object, or
that a purse, keys and a passport are all someone's belongings. So every pairing had to be taught by hand ("found +
wallet", "found + purse"…), and drafts waited for a person to tap Approve. Predictions already learned from what
people do (they open one, or remove it with ×) without anyone approving them. Words and phrases should work the same
way.

## Decision

1. **Word knowledge is built in, from a dictionary.** WordNet 3.1 (Princeton, free to use with its notice) is loaded
   as data (`api/lib/data/word-families.php`, built by `workers/lexicon/build_families.py`). It has three parts:
   - about 14,000 common nouns, each in the family search acts on: someone's **belongings**, **documents** and cards,
     money, keys, a phone, computer or appliance, a pet, an animal, a vehicle, a tire, glasses, power and hand tools,
     plumbing, medicine, food;
   - the irregular forms of verbs (found → find, stolen → steal) and of plurals (knives → knife).

   Only a noun's most common sense counts. A short list of corrections fixes the senses people don't mean in a search
   (a "drone" is a device, not a bee).
2. **Verbs by their base form.** Any form of a verb whose every form means the same thing is that event or action:
   robbed, robbing and robs are all "lost"; leaked is "broken"; installing is "install". "Find" is deliberately not
   one of them: "find my keys" is lost, "found a wallet" is found.
3. **What a verb asks for.** New built-in event: **found** (someone else's thing). Its rules:
   - found + an animal → the lost-or-found pet situation;
   - found + belongings, documents, keys, a device, money, glasses or tools → **Found someone's belongings** (the
     police, non-emergency; on a trail or in a park, a visitor center or ranger station);
   - "found my …" is their own thing found again, so nothing to hand in.

   And lost, stolen or robbed + belongings, documents, money or a device → **Lost or stolen wallet, phone or ID**:
   report it, replace an ID at Motor Vehicles, cancel cards at the bank, replace a phone. Both situations are built in
   (seed 12).
4. **Applied at once, with nothing to approve.** A word the dictionary knows is read as its family as soon as anyone
   searches it ("I found someone's purse", "found a hamster", "someone robbed my backpack"). Analyze says so: "'purse'
   is someone's belongings, from the dictionary". A word taught in Admin always wins, and the built-in lists are read
   first.
5. **Learned from searches, applied by the system.**
   - **What's applied:** every 6 hours the missed searches give words search doesn't know, words that keep leading to
     one kind of place, and rules to situations that exist. Once at least 3 searches back each one, the system applies
     it (`SearchDrafts::autoApply`).
   - **The record:** each is logged as the system's (made_by 0) under Recent corrections, marked "learned on its own",
     with Undo.
   - **Reinforced by use, as predictions are:** removing a suggestion with ×, changing the search and leaving with
     nothing all count.
6. **What still needs a person:** nothing by design. *(Jason, the same day: "A situation that doesn't exist? Impossible,
   since search should be able to discern the content of the words or phrases and suggest a possible prediction of
   what they are asking for. We aren't making their decisions, just offering routes to information that may apply.")*
   - ~~**A situation that doesn't exist yet**~~ is made by the system (`SearchDrafts::buildSituation`):
     - **Name:** the words people used most ("Buy tires").
     - **Rule:** the draft's (buy + tire).
     - **What it offers, in order:** the kinds of places people opened after those searches; else the categories whose
       names carry the words at a word's start ("Tire Service", "Tire Dealers"; not "Retirement"); else listings that
       mention the words.
     - It goes live at once with source `auto`, logged with Undo, which removes the situation, its needs and its rule.
   - ~~**The 911 line.**~~ *Removed the same day (Jason): "911 and suggested solutions don't need constraints. They can
     be offered as a reasonable response to emergency or harmful situations it recognizes. Safety is in the searcher's
     hands, since we're asking for, or requiring, their action."* A reading from a dictionary word, and a rule learned
     on its own, may lead to a Danger situation like any other. Its answer, with the 911 line, is offered, and acting
     on it is the person's choice.
   - **Tribal nations' land and confidential addresses** (decisions/0058 §26). These rules hold however a word is read.
7. **Access and control.** In the Workbench:
   - every word shows where its reading came from (built in, taught, the dictionary, or learned on its own);
   - every change has Undo;
   - a person can override any reading by teaching the word differently.

### Progress, 2026-10-04: every English word is known

"Prepare for" showed "Not recognized: prepare" and offered "propane" and "preserve". The dictionary held only the
nouns in a family and a short list of verbs. Fixed:

- **The vocabulary:** all 77,519 single-word lemmas from WordNet (nouns, verbs, adjectives and adverbs), each with its
  parts of speech. They're in `api/lib/data/word-list.php` (1.3 MB, built by the same generator), read only when a
  word isn't known any other way.
- **Reading a word:** a word found there is recognized, with its part of speech chosen by its slot ("to prepare": the
  verb). Plurals and verb forms count ("keys", "prepared"). It has no job until it's part of something search acts on.
- **"Did you mean"** never corrects a real word. A slip still gets help: "pozza" → pizza, "frobnicat" is still flagged
  as unknown.

### Progress, 2026-10-04: words that ask for a tool

Search knew "planning" as a word but only offered offices and "planning open now". Words can also ask for one of
the site's own tools. `UniversalSearch::TOOLS` lists each tool with its *strong* words (trip, planner, itinerary,
vacation, getaway…), which always mean that tool, and its *plain* words (plan, planning, visit, travel), which mean
it when they make up most of the search. The tool leads the suggestions while typing, set to the person's place
(`/discovery/?view=plan&cluster=…`). After Enter it's asked above the results ("Are you looking for… Trip Planner").
When other words name something else ("planning and zoning"), the tool comes after the places. A tool that leads
drops "open now" completions. New tools are added to the list.

## Consequences

- Searches about everyday things work the first time, in words nobody listed: belongings, pets, tools, documents.
- Staff time moves from teaching words to the few things only people can judge: new situations and emergencies.
- **The dictionary has to be shipped.** It's a 450 KB generated PHP file, loaded only when a search needs it (about 5
  ms), and rebuilt by running the generator again.
- **Some mistakes will be read before anyone sees them.** The dictionary is broad; a wrong family shows up in Analyze
  and in what people remove, and is fixed by teaching the word or adding a correction to the generator.
- **A wrong reading can show the 911 line, or lead away from it.** Either way it's only an offer. People can see why
  in Analyze, staff can undo it, and the person searching always decides what to do.
