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

### Progress, 2026-10-04: Piece 1, the Workbench and recognized or not

- **Data** (`2026-10-28_lexicon.sql`):
  - `search_meanings.kind` widens to 10 characters (modifier, wrapper) and gains `pos`, the lexical category;
  - `search_suggestion_flags` holds removed suggestions.
- **`Lexicon`** reads every word: its lexical category, its job and W, and whether it's recognized.
  - **Known:** the reasoner's words (built-in and taught), plus places (towns, our areas) read as Where, categories
    (What) and words in names (Who).
  - **Little words** are tagged structure.
  - **An unknown word** gets a guessed category with the reason: after "to" a verb, after an article or preposition a
    noun, or from its ending (-ly, -ing, -ed, -tion…). With no clue, it's left unguessed.
- **New kinds:**
  - **modifier:** quality, condition, price, locality, access ("heavy duty", "used", "free", "local", "pet friendly");
  - **wrapper:** procedure ("how to"), place ("where can I"), need ("looking for"), definition ("what is").
- **Search:**
  - **What it returns:** `lexicon.unknown`, the words it doesn't recognize. The last word waits while a known word
    starts with it, since it may still be being typed. After Enter it also returns `lexicon.unread`, meaning the
    phrase wasn't read as a whole.
  - **Did you mean:** only words that fit the slot.
  - **"How to" + a word nobody knows:** reported as a procedure on an unknown word, not searched as a name.
- **The search panel:**
  - **Leads with "We don't recognize…":** visitors get **Suggest it**, which saves a "word" suggestion with the
    search to Review; staff get **Add it**, which opens the Workbench lightbox for that word.
  - **× on suggestions:** on Did you mean, the words that finish a search, and May also help. A visitor's × only
    counts; at 5, the suggestion drops out for those words. A staff member's × blocks it at once.
- **The Workbench:**
  - **The lead line** is Recognized, Not recognized (with Add it per word), or "Words recognized, the phrase isn't"
    (with New situation).
  - **Chips** show each word's lexical category and job → W. An unknown word is dashed red; a guessed category has
    "?".
  - **Tapping a chip opens a lightbox,** with no scrolling:
    - **Word:** category, job, meaning (a new word gets a new meaning named after it), "only with a partner"; saving
      replaces what was taught for it, and built-in meanings stay;
    - **Senses:** its senses, and add one;
    - **Forms:** other forms with the same meaning.
  - **× on every suggestion,** and a "Removed for these words" list with Undo.
- **FAQ:** "What happens if search doesn't recognize a word?" and "Can I remove a suggestion that doesn't fit?"
- **Checked in the sandbox (390px and desktop):**
  - "how to compost":
    1. Not recognized: compost (guessed verb, after "to"). Add it opened the lightbox.
    2. Saved as action / compost / verb, it read as "words recognized, the phrase isn't".
    3. Forms "composting, composted" were added.
  - A visitor's "how to frobnicate": We don't recognize, then Suggest it, which reached Review.
  - × on "Did you mean pizza": a visitor's counted; staff's blocked and was undone.
  - The earlier situation and time-word searches gave the same results.
- **Also checked on MariaDB:** the migration, the category column, and the removal counting, blocking and Undo.

### Progress, 2026-10-04: Piece 2, correcting the 5 W's in place

- **Selecting:**
  - tapping a word chip selects it, and its W card lights up;
  - the bar under the chips shows the selection, with **＋ word before / word after ＋** to grow it into a phrase
    ("first thing tomorrow"), and **Word, senses & forms** (the piece 1 lightbox).
- **✎ on each W card** opens a guided form inside the card. Only values search can act on are offered:
  - **Why:** "it names a situation by itself" (a sense, with optional cues), or a rule: an event or action plus
    things or tools → a situation. The word's own class is preselected.
  - **What:** "an everyday word for a category" (pick a real category; saved as a sense that reads it as the
    category), or "a thing, a tool or a modifier" with its meaning.
  - **Where:** "a local name for a town or area we hold" (checked against our places), or "never a place" (new
    `search_meanings` kind `notplace`). The card says that the reading is the true place, and whether it can be shown
    is decided when it's shown.
  - **When:** one of the ten time classes.
  - **Who:** one of the audiences search knows (new kind `audience`, read by the Who step). Never a person: anything
    else is refused.
- **Dry run before saving:**
  - Save stays off until a dry run has been done on the form as it stands; changing the form turns it off again.
  - **How it works:** the Workbench asks for two readings, each in its own request. One is plain. In the other, the
    change is written inside a transaction, the searches are read, and it's rolled back, so nothing is kept and the
    real code is used. Taught words are reloaded after the trial write.
  - **What it reads:** the search being worked on, plus recent searches with the word (`search_terms`, last 90 days,
    most searched first, up to 20).
  - **What it shows:** each search that would read differently, W by W, before → after ("cabins in greer — Where:
    Greer, AZ → nothing"), or "none would read differently; it applies to new ones".
- **Saving logs it** (`2026-10-29_search_edits.sql`, `search_edits`): the change, its summary, how many searches the
  dry run said it changes, who made it, and how to undo it (the rows it added, and the taught rows it replaced).
  **Recent corrections** lists them with **Undo**, which removes what was added and puts back what was replaced.
- **`SearchEdits`:** `check()`, `apply()`, `save()`, `undo()`, `samples()`, `reading()` and `readings()`.
  `SituationReasoner::reset()` was added for the trial.
- **Checked in the sandbox (desktop and 390px):**
  - What: "pub" → Bars. 2 of 2 searches change; saved, then undone.
  - Who: "grandparents" → For seniors. 2 change; saved.
  - Where:
    - "greer" never a place: 2 change, Where Greer, AZ → nothing;
    - "sj" a local name for Saint Johns: "plumber in sj" Where → Saint Johns, AZ.
  - When: the phrase "first thing tomorrow" → Open today. "plumber first thing tomorrow" changes; after saving it's
    one chip (time → When).
  - Why: "conked" → Stuck on the road.
  - A refused Who ("bob smith" as a person).
  - The trial leaves nothing behind.
- **Also checked on MariaDB:** the migration runs twice safely, a rolled-back trial leaves the tables unchanged, and a
  saved change and its undo restore the word's earlier reading.

### Progress, 2026-10-04: Piece 3, the frame-first situation builder

- **The situation editor has four steps:**
  1. **What it is:** the name, status, track, urgent or danger, and what would help (the needs).
  2. **What triggers it:** rules, each an event or action plus the things or tools it's about (or the action alone).
     They're added from pick lists of the meanings search knows, and removed with ×. The rules built into the code
     for this situation are listed read-only.
  3. **Example searches, the tests:** how people would say it, one per line. They prove the rules; they trigger
     nothing.
  4. **Fixed phrases:** only for idioms that can't be built from parts ("fender bender", "tow truck"). Existing
     situations keep theirs.
- **"Test them, and recent searches":**
  - **How it works:** two readings, each in its own request. One reads as things are now. In the other, the situation
    as edited is written inside a transaction (tried as Live), the searches are read, and it's rolled back.
  - **Each example** shows ✓ "reads as this, by a rule" or "by the phrase", or ✗ "reads as …", plus:
    - **Not recognized:** each word search doesn't know, with **Add "word"**, which opens the Workbench lightbox for
      it over the editor;
    - **a suggested rule** built from the example's own words (its first event or action, and its things and tools),
      with **Use this rule**.
  - **The total:** "N of M pass. Ready to set Live", or "Fix the ones that don't before setting it Live".
  - **The dry run:** the 60 most-searched recent searches, listing any that would read differently, before → after.
  - Testing works before the form is complete; only Save asks for a name, a need, and a rule or phrase.
- **Saving:**
  - a new situation starts as a draft ("Set it Live once its tests pass");
  - **New situation** from the Workbench puts the search being worked on into the examples, not the phrases;
  - the situation's taught rules are the ones in the editor (`search_frames` by slug; the built-in ones stay in code);
  - examples are kept on the situation (`2026-10-30_situation_examples.sql`).
- **Also:**
  - A rule with no things now reads "taught in Admin: 'compost'".
  - The Workbench shows the server's error messages, not "[object Object]".
  - A pending refresh of the built-in situations now runs before any trial transaction (piece 2's dry run included),
    since it opens its own. This was found on MariaDB.
- **Checked in the sandbox:**
  1. "how to compost" → **New situation**, with "how to compost" as an example.
  2. Named Composting, with four examples.
  3. **First test:** 0 of 4 pass. Each suggested "compost"; "bins" and "pile" were flagged with Add it.
  4. **Use this rule, then test again:** 4 of 4 pass; none of 29 recent searches change.
  5. **Saved as a draft:** a draft doesn't answer. Once Live, "how to compost" reads as Composting by the rule, and
     the Workbench leads "✓ Recognized: reads as Composting".
  - Reopening keeps the rule and the examples. 390px, no sideways scroll.
- **Also checked on MariaDB:** the migration, a rolled-back test (nothing left behind), and saving with a rule and
  examples.

### Progress, 2026-10-04: Piece 4, drafts from missed searches (the apprentice, §7)

- **Where drafts come from** (`api/lib/SearchDrafts.php`). Each learning pass (every 6 hours) ends by reading the last
  30 days of missed searches (found nothing, couldn't tell, nothing opened). Staff can also tap **Refresh drafts now**.
  Three kinds of draft:
  - **Word:** a word search doesn't know, used by 3 or more missed searches, or suggested by a visitor ("Suggest it").
    The job comes from where it sits: after "to", a verb, so an action; an adjective, a modifier; otherwise a thing.
    Approving teaches it as a new meaning named after the word.
  - **Category:** a single word after which people opened one kind of place at least 3 times and 60% of the time
    (last 90 days), and that isn't already read as a category.
  - **Rule:** an event or action with a thing or tool, in 3 or more missed searches that found no situation. Every
    thing in the search is counted ("rent a moving truck": move and vehicle).
    - **The situation it points to** comes from the places people opened after those searches, matched against live
      situations' needs.
    - **On a tie,** the situation that already has a rule led by the same event or action wins. A rental yard answers
      both renting and building, and "rent" picks renting.
    - **No situation fits:** the draft says "start one".
- **Evidence:** counts only, never who. "4 searches in 30 days said rent with vehicle and found no situation; people
  opened places Renting tools or equipment answers (4 times)". Up to five of the searches behind it are shown.
- **Deciding** (Workbench → **Drafts**; it opens by itself when drafts are waiting):
  - **Approve:** one tap. It goes through the same checks as a hand correction (`SearchEdits::check`), is made and
    logged, and Undo is under Recent corrections. A new type, `word/word`, teaches a word with any job (event, thing,
    action, tool, modifier, wrapper) and its part of speech.
  - **Start a situation** (a rule with no situation): opens the frame-first builder with the searches as examples and
    the rule already added, ready to test.
  - **Open in workbench:** puts the first search in the Workbench, where every W can be changed first.
  - **Dismiss:** set aside. It comes back only if three times as many searches miss.
  - **Approved, then undone:** the draft comes back on the next pass.
  - A draft that no longer misses (taught some other way) leaves the queue ("gone").
- Nothing is taught without a person. Zero-touch stays only for result ranking (decisions/0061).
- **Admin:** a Today line, "Words and rules to approve", opens `/admin/search-misses.php#drafts`. The count is on the
  Search group's badge and the Workbench's.
- **Migration:** `2026-10-31_search_drafts.sql`, the `search_drafts` table.
- **Checked in the sandbox:**
  - Seeded missed searches gave four drafts: "frobnicate" (an action, a verb, with a visitor's suggestion), "boozer"
    (a category), buy + tire (start one), and rent + vehicle → Renting tools or equipment.
  - **Approve rent + vehicle:** "rent a truck" now reads as Renting tools or equipment.
  - **Start a situation for buy + tire:** the builder opened with "buy tires / buy new tires" and the rule buy + tire.
  - Dismiss and Open in workbench work. A second approve of the same draft is refused.
  - Approving a rule with no situation says "needs a situation first".
  - 390px: card edges at 3px, no sideways scroll.
- **Also checked on MariaDB:**
  - the migration, run twice;
  - refresh (run twice: no duplicates);
  - approve, then Undo (the draft returns on the next pass);
  - dismiss.

### Progress, 2026-10-04: How-to guides built by the crawler (§8)

- **The trigger** (`api/lib/HowGuides.php`): a search that asks how to do something. That means a procedure wrapper
  ("how to", "how do I", "steps to", "learn how to") followed by an action search knows.
  - The guide is keyed by the action and its thing, if any ("compost", "repair|generator"). Its title is the words
    people used most ("How to compost leaves"), with a place cut off ("… in Show Low").
  - **Ignored:** "How to get to Show Low" has no action, so it isn't a guide. A word search doesn't know isn't a
    guide either; it goes to the Workbench's drafts first (piece 4).
  - Each learning pass (every 6 hours) reads the last 30 days of searches. Only counts are kept.
- **Wanted:** at least 3 searches in 30 days, or staff asked for it ("Ask for a guide").
- **The crawler** (`workers/crawler/crawler.py` `guides()`, `GUIDES_PER_RUN` 2, `GUIDES_TIME` 45):
  - two searches, "… extension", then "… site:.gov OR site:.edu";
  - **official pages only** (.gov, .edu, .mil, a state's .us), about the guide's words;
  - each page's best ordered list (navigation skipped), or its "Step 1 …" headings;
  - up to 4 pages.
- **On the site** (`api/crawl/guides.php`):
  - it accepts official pages only;
  - it drafts from the page with the most steps (at least 3), each step tagged with its source's number, and keeps
    the other pages as sources;
  - with nothing found it looks again in 30 days.
- **The review** (Admin → Search → **How-to guides**, `/admin/how-guides.php`):
  - tabs by state: wanted, the crawler is looking, to review, published, nothing found yet, not a guide;
  - **the editor:** the title, a line to start, and the steps one per line, each ending in its source's number "[1]".
    The sources are listed with the steps found on each.
  - **Publishing needs** at least 3 steps, a source on every step, and a tick: "The steps are in our own words, and I
    checked each one against its source." The crawler's lines are the source's own words and are never published as
    they are.
  - A refused Publish keeps the edit as the draft. A live guide is never saved into something that can't be published.
  - **Other actions:** take it down, have the crawler look again, not a guide, back to wanted.
- **Shown** (`/guide/how.php?id=`), following the content page standard:
  - **Header:** the kind ("How-to guide", searches for more), the title, the Link button (`place:how:N`).
  - **Body:** the Engage bar, numbered steps each linking to its source, the sources, "Rather have someone do it?"
    (searches the same words, so it finds who does it), "A step wrong or missing? Tell us", and comments.
  - **Counted** as `how:N`. Comment reports link back to it. (The report link for resource guides was missing too,
    and is fixed.)
- **In search:** a published guide for the words ("how to compost", "how do I compost leaves") leads the list, as
  "How-to guide · 4 steps, from official sources". The situation (who does it) still answers alongside it. A more
  specific guide ("repair|generator") wins over the action's own ("repair").
- **Admin:** a Today line "How-to guides to check", and a badge on the Search group and on the page.
- **Migration:** `2026-11-02_how_guides.sql`.
- **Checked in the sandbox:**
  1. Seeded searches gave three wanted guides: How to compost leaves (4 searches, 2 days → Composting), How to fix a
     generator (→ Small engine repair) and How to frobnicate. "How to get to Show Low" was ignored.
  2. The worker ran with fixture pages:
     - the compost guide drafted 5 steps from the extension page, with the EPA page as a second source (3 steps);
     - the blog page and the breadcrumb list were skipped;
     - the generator guide's official page had no steps, so it's nothing found yet.
  3. In review, Publish was refused without the tick, then for a step with no source (the edit kept), then published.
  4. The page showed the steps with their sources, the Link button, Engage and comments. Search for "how to compost"
     led with the guide, and Composting still answered. A guide that isn't published is "Guide not found".
  - 390px: 3px edges, no sideways scroll.
- **Also checked on MariaDB:** the migration (run twice), refresh, a staff request, lease, complete (a non-official
  page dropped), publish, and counts.

### Progress, 2026-10-04: The situation builder, from Jason's walk-through

- **Add a word without leaving** (step 2, "A word you need isn't in the lists? Add it here"):
  - Enter the word and what it is: what happened (event), what they want done (action), what it's about (thing) or
    a tool.
  - Then say what it means the same as. An existing meaning gives it that meaning's rules ("purse" → wallet).
    Otherwise it gets its own new meaning.
  - Once added, it's already picked in the rule's lists, ready for **Add this rule**.
- **Triggers explained in place:** a rule is what happened (or what they want done) plus what it's about. "Found" +
  "wallet" catches every way of saying it.
- **Save draft / Publish / Reset** replace Save:
  - **Save draft:** saves without answering searches.
  - **Publish:** runs the example searches first. If any don't read as this situation yet, it says which and asks
    before going live. With no examples, it asks too.
  - **Reset:** puts the form back as last saved (a new one is emptied), after a confirmation.
- **Situations work everywhere:**
  - The builder now says so. What would help is found near wherever the person searching is.
  - The place picker only sets where to **preview** it. It starts at "Where I am" (the place the page is set to).
- **Checked in the sandbox at 390px:**
  - "Found a wallet" built start to finish without leaving the builder. "found" was added as an event and "wallet" as
    a thing, and the rule became found + wallet.
  - The example "found someones wallet" passed (1 of 1).
  - Saved as a draft. Reopened, renamed, then Reset (the name came back). Published (the tests passed, so there was no
    question).
  - "I found a wallet" now reads as Found a wallet, with 5 police places near the search.
