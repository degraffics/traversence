# 0063: Lexicon and syntax: how search reads each word, and how a person corrects it

**Status:** Accepted, 2026-10-04. To be built in four pieces (below).
**Source:** Jason's "NLP and linguistics" note and Workbench screenshots ("how to compost"), 2026-10-04. Builds on
decisions/0061 (situations, the meaning layer, the five kinds of word) and 0060 (search and suggestions).

## Context

Search reads words with rules, never a model (decisions/0061):
- the five kinds of word (event, thing, action, tool, time);
- senses chosen by the words around them;
- grammar cues ("by", "my", "out of");
- "Did you mean" without silent changes;
- the What gate.

Three things were missing:
- **Lexical categories.** Search never knew, or showed, whether a word was working as a noun, a verb or a modifier.
- **Position and word shape for unknown words.** "To ___" is a verb; "-ing" is a process.
- **A fast way for a person to correct a reading.** "How to compost" showed the gap: "how" unused, "to" a little
  word, "compost" read as a name, and "Did you mean how to compass" offered.

## Decision

### 1. Every word has a lexical category and a job

- **Lexical category** (part of speech): noun, verb, adjective, adverb, preposition, pronoun, determiner, particle.
- **Job** (kind), feeding one W:
  - **event** → Why;
  - **thing** → What / Who;
  - **action** → Why (who does it, and a guide when there is one);
  - **tool** → What;
  - **time** → When;
  - **modifier** (heavy-duty, emergency, local) → narrows What;
  - **wrapper** ("how to", "where can I") → a structural hinge. "How to" marks a procedure.
- **Known words** carry their category and job, as taught or built in.
- **Unknown words get a guessed category, with the reason:**
  - by position: after "to" or a wrapper is a verb; after an article or preposition is a noun;
  - by shape: -ing is a process, -ed a past event, -ly an adverb, -tion, -ment and -ness a noun.
  - A guess is shown and fills in the Add form. It never changes results until a person confirms it.

### 2. Recognized or not, said plainly

- Search leads with whether it recognized the words: "We don't recognize 'compost'", or "…the phrase 'how to
  compost'".
- **Visitors** get **Suggest it**, which goes to Review as a suggestion. **Staff** get **Add it**, which opens the word
  form, filled in.
- "Did you mean" offers only near spellings that fit the slot. A noun isn't offered where a verb is needed.

### 3. Suggestions are removed, not edited

- **Removing teaches:**
  - **staff ×:** blocks that suggestion for that search at once, logged, with Undo;
  - **visitor ×:** counted as "not relevant", counts only, never who. Enough of them and it drops out on its own.
- **No editing:** the fix for a wrong suggestion is teaching the word, sense or rule behind it, one click away.

### 4. The 5 W's are corrected through guided paths

- **The trigger:** selecting a word lights up its W.
- **Edit offers only values search can act on:**
  - **Why:** a situation, or a rule (event or action + thing or tool). Danger stays admin-only.
  - **What:** a real category, a name, or a modifier.
  - **Where:** a place we hold, or a local name for one.
  - **When:** one of the ten time classes.
  - **Who:** who it's for, who runs it, or a public office. Never a person.
- **A dry run comes first:** every edit shows which recent searches it would change, before and after. Then it's
  saved, logged, with Undo.

### 5. Where: the reading is accurate; showing it is a separate check

- **The reading:** the parser records the true Where, even for a sacred site, a confidential address or a closed
  place.
- **Showing it:** whether the front end may present it is decided separately, at presentation, by the rules that
  already govern display:
  - a nation's own rules (decisions/0058 §26), through `Landmarks::visible()` and `Nations`;
  - phone only for a confidential address;
  - closed or hidden places.
- **Why:** a correct reading never leaks something that mustn't be shown, and a hidden thing is never misread as
  something else.

### 6. Situations are built as frames

A situation is a frame: a scenario with roles. The builder becomes frame-first:
1. **Name and needs:** the answers.
2. **The frame:** event or action meanings, plus thing or tool meanings. This is what makes every wording work.
3. **Example searches as tests,** not triggers. Each shows live whether it reads to the situation, with **Add it**
   for any word it doesn't know.
4. **Fixed phrases** only for idioms that can't be built from parts ("fender bender", "tow truck").
5. **A dry run.** It starts as a draft; a person sets it live.

### 7. Learning: apprentice first

- **Drafts:** missed searches and unrecognized words are grouped, and drafts of words, senses and rules are proposed,
  each with its evidence.
- **Approval:** one tap by a person. Rules and senses are never promoted automatically.
- **Zero-touch** stays only for result ranking (May also help → Most opened, decisions/0061).
- **Auditable:** everything learned shows in ✦ Analyze.

### 8. Guides are built by the crawler

- **The trigger:** an action whose steps need defining ("how to compost") is a guide to build.
- **The source:** the crawler gathers the steps from official how-to sources (university extension services,
  government pages) and records each step's source.
- **Publishing:** a person reviews it before it's shown.
- **Until then:** an action leads to who does it, and the guide says "coming".

## Build, in pieces

1. **The Workbench:**
   - word chips with category and job;
   - a lightbox to edit a word, its senses and its forms;
   - recognized or not, with Suggest it and Add it;
   - removable suggestions;
   - "Did you mean" fitted to the slot.
2. **Inline W editing** with the dry run.
3. **The frame-first situation builder.**
4. **Drafted words, senses and rules** from missed searches.

Then guides (§8), as their own step after ADR 0062's remaining steps.

## Consequences

- People can see why a word was read as it was, and correct it in one place without scrolling.
- More is stored per word (category, job, forms), and every change is logged.
- Unknown words are named, not guessed past. That's honest with visitors and gives staff a queue of exactly what to
  teach.
- Reading and presentation are separate, so the rules for nations' land and confidential addresses hold however a
  word is read.
