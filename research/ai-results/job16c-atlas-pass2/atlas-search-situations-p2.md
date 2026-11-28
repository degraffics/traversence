# Platform atlas: search-situations

*research/platform-atlas/search-situations.md. Draft for human review. Built only from the attached documents and code.*

**Scope and coverage.** This area covers situation search and the 5W reading around it: how a search is read (Why, What, Where, When, Who), how situations answer it, how people correct and teach search, and how search learns.

The code shown is 12 library files from `traverse-3way-sync/sync-app/lib/`. Their header comments still say `api/lib/`, while `site-audit/pages.md` says the backend moved to `app/lib/` (decisions/0069). Four of those files are **cut off** partway: `SituationReasoner.php` (after line 517), `SearchDrafts.php` (after ~500), `SearchIndex.php` (after ~483) and the decision texts.

The following core files are **not shown**:
- `Situations.php`, `UniversalSearch.php`, `Lexicon.php`, `Learning.php`
- `ResourceGuides.php`, `HowGuides.php`, `WordFamilies.php`
- `/api/search.php`, `js/search-panel.js`
- every admin page's PHP

---

## (a) What each person can do here, page by page

Routes come from `site-audit/pages.md` unless noted.

**Resident and traveler (anyone, signed in or not)**

| Where | What they can do |
|---|---|
| The header search panel, on every page except dashboards, admin and sign-in (decisions/0053 §1) | Type a situation ("power went out", "bit by something"). Predictions show while typing, and results come on Enter or a tap (0061, progress "predictions only while typing"). A danger situation puts "If anyone is in danger, call 911." on top (0061 principle 6). Urgent needs lead with Call, open now and distance (0061 "Urgent display"). |
| `/api/search.php`, `/api/suggest.php` | Universal search and the older directory type-ahead. Both are Live. |
| `/directory/` (Get Local), `/discovery/` (Let's Explore) | The same search, routed by section. Businesses lead on Get Local; the place's own content leads on Let's Explore (0058 §19). |
| Panel: "We don't recognize…" → **Suggest it** | Sends an unknown word to Review (0063 §2). A visitor's × on a suggestion is counted; at 5, the suggestion drops (0063 piece 1). |
| `/api/suggestion.php` ("Tell us") | Report a missing place, a fix, or something closed (0053). |
| `/guide/resources.php?id=` / `?area=` | Resource guides: phone lines for needs that aren't businesses. Only verified entries show (0061, Resource guides). |
| `/guide/how.php?id=` | How-to guides built by the crawler from official pages and reviewed by a person (0063 §8). |
| Panel "none here" box | Recommend one, or Add a listing (0058 §12). |
| `/takedown.php` | Ask for something to be taken down (0133). |

**Member (signed in)**
- Saved searches: on the account, up to 20, shown in `/user/dashboard.php` through `/user/api/saved-searches` (0053 §6).
- Own quick searches: kept on the device, up to 12 (0053). They are counted for where the member is (`SearchPresets.php:96-109`).
- Recent searches: kept in the browser only (0053 §7).
- `/ai/`, AI Assist on topics: needs a paid plan and AI consent (0133, "Search, step two").

**Business**
- Rule: a listing missing what a need requires (a phone, hours, a place) isn't offered for that need, and the gap is counted. That gap is "a reason for the business to claim and complete it" (0061 principle 5).
- Admin → Search oversight lists these incomplete listings with a link to complete each (0061, Resource guides progress).
- Claim and complete a listing through `/claim.php?listing=` and `/listing/businessportal.php`.
- See counted calls, directions and website taps in Business Insights (0058 §9). Its route inside the portal is not shown.

**Staff (admin)**

| Route | Job |
|---|---|
| `/admin/search-misses.php` | Missed searches; Teach search; Workbench (word chips, lightbox, inline W editing with a dry run); Meanings; Drafts; Predictions while typing (0061, 0063) |
| `/admin/search-learning.php` | What search learned from opens (0061 batch 3) |
| `/admin/search-oversight.php` | Resource numbers to check, incomplete listings, what changed |
| `/admin/search-demand.php` | Demand by state → county → town (0066) |
| `/admin/resource-guides.php`, `/admin/how-guides.php` | Guide editors |
| `/admin/search-index.php` | Build the 5W index |
| `/admin/insights.php` | Usage counts (0058 §13) |

---

## (b) How it works underneath

**Reading a search**
- The 5W parser order (Why first, then What, Where, When, Who) is set out in the 0061 addendum.
- **The situation map** (`situations`, `situation_triggers`, `situation_needs`) is matched by phrase in `Situations.php`, which is not shown.
- **The meaning layer**, used when no phrase matches, is `SituationReasoner.php`:
  - It has word classes for events, actions, tools, time, modifiers, wrappers and things (`:28-163`).
  - Frames turn event + thing into a situation; the first match wins (`:168-249`). Example: harm + venomous → `someone-hurt` (`:174`).
  - Taught frames are checked before built-in ones (`:377`).
  - A weak word needs a cue such as "got" or "my" (`:504`).
  - Each answer carries its reasoning in words (`:515`). This is rule-based, with no AI (`:2-3`).
- **The kinds a word means**, from WordNet data plus staff's words, come from `Concepts.php`:
  - `kinds()` (`:318-329`) and `dictKinds()` (`:332-410`).
  - Data is split by first two letters for speed (`:25-99`).
- **Local sense** comes from `LocalWords.php`:
  - Words, names, kinds and phrases are drawn from public listings, the pages we read, towns and visible landmarks (`:164-236`).
  - Held landmarks are excluded (`:222`).
  - It is built once a day into server storage (`:15-16`, `:140-162`) and lifts kinds by context (`Concepts.php:419-449`).
- **Spelling and "Did you mean"** live in `SearchText.php`:
  - It never changes a word on its own (`:375-380`).
  - Local words come before the general dictionary (`:450-455`).
  - British spellings are folded (`:75-84`).
- **The plain reading line and factual answers** come from `SearchReading.php`:
  - `line()` writes "Read as a need: …" (`:174-201`).
  - `fact()` answers "hours of X" (`:32-47`).
  - A confidential place shows its phone only (`:97-105`, `:91`).
  - `pages()` serves only pages from sources marked `read` (`:160`).
- **Trace for ✦ Analyze**: `SearchTrace.php:11-21` is off unless switched on.

**The index and presets**
- `SearchIndex.php` holds one row per listing, outdoors place, story, group or topic, tagged by the 5 W's (`:16-30`). Rules:
  - Nothing personal is indexed (`:25`).
  - Audience is read from the thing's own words, "never guessed about a person" (`:79-88`).
  - Confidential listings carry `private_location` (`:385`, `:401`).
  - Hidden landmarks and Recreation.gov places are skipped (`:443`, `:474`).
  - The rank weight is `verified` +10, claimed or pending +5, closed −40 (`:400`).
  - Sections: the `market` section matches nothing ("nothing to search yet", `:51`).
- `SearchPresets.php` builds one-tap searches from what's in the place, the time of day and aggregate asks (`:50-84`):
  - `record()` keeps only the reading, never the words or the person (`:192-229`).
  - Repeat counts are suppressed per session for 10 minutes (`:213-217`).
  - Rows are deleted after 90 days (`:228`).

**Correcting and learning**
- `SearchEdits.php` handles one W at a time (`:27`):
  - Who accepts only the fixed audience list, never a person (`:20`, `:111-116`).
  - Dry run: the change is applied inside a transaction, searches are re-read, then rolled back (`:230-241`).
  - Saving logs the change with its undo (`:189-195`, `:160-186`).
- `SearchDrafts.php` drafts word and rule changes from missed searches over 30 days (`:35-153`):
  - "missing kinds" (`:162-269`);
  - `autoApply()`, which applies changes without a person (`:475-495`);
  - retiring unused auto-added kinds after 60 days (`:351-376`);
  - undoing routes that came from usage rather than meaning (`:383-400`, decisions/0093).
- `SearchDemand.php` counts place → anchor → destination by day, with no "who" (`:7-14`, `:36-52`). It feeds crawler targets.

**Tables named in the code or docs:** `search_terms`, `search_outcomes`, `search_opens`, `search_learned`, `search_counts`, `search_demand`, `search_meanings`, `search_frames`, `search_senses`, `search_edits`, `search_drafts`, `category_terms`, `search_index`, `search_index_state`, `situation_sources`, `situation_gaps`.

---

## (c) Decisions

| ADR | What it rules | Text seen? |
|---|---|---|
| 0053 | Universal search opens in place; the 5W model; presets come from the system, never admins; saved searches; query text never stored against a person (§7) | Yes |
| 0058 §12, §13, §19 | The "none here" box; counting usage, never who; search follows the section (the Chameleon Filter) | Yes (in part) |
| 0061 | Situations, the intent map, the Chameleon router, the 911 line, the incomplete-information rule, words counted without who (principle 8), confidence scoring, learning | Yes, truncated |
| 0063 | Lexical categories, the Workbench, guided 5W correction with a dry run, the frame-first builder, drafts, how-to guides | Yes |
| 0064 | System applies learned words and rules; system-made situations | **Not shown**; known only from code comments and the 0063 §7 amendment |
| 0066 | Search demand and its funnel | Not shown |
| 0067 | Concepts (WordNet), missing kinds | Not shown |
| 0068 | Plain category names | Not shown |
| 0093 | Routes come from meaning, not usage; search thresholds 0.80 / 0.60 | Not shown; cited by 0133 and code |
| 0133 | Crawler rule book; topic answers in search; protected tiers | Yes; **closed, superseded by 0164** |
| 0157 | Local words, the reading line, pages | Not shown |
| 0164 | Amends 0061 (thresholds, learning); supersedes 0133 | **Not shown** |
| 0044, 0072, 0144 | Confidential listings show phone only; closed listings; spelling | Cited only |

---

## (d) Live vs coming

**Live** (per `pages.md`):
- `/api/search.php`
- every `/admin/search-*.php` page, `/admin/resource-guides.php`, `/admin/how-guides.php`
- `/guide/resources.php`, `/guide/how.php`

Batches 1–3 of 0061 and pieces 1–4 of 0063 are recorded as built.

**Coming, or not yet:**
- **Market in search:** the section matches nothing (`SearchIndex.php:51`), even though `/market/*` pages are listed Live.
- **Events, and "new"/"upcoming":** wait for dates on listings and events (0061 addendum).
- **Saved searches' "New" count** (0053).
- **"See all" carrying When:** 0053 lists it as a known gap; whether it's fixed is not shown.
- **Public topic pages:** "a later decision" (0133).
- **# tag pages:** not built (0058 §3).
- **Personalized Insight engine:** Tier 3 (PRD).
- **Clean addresses** (`/listing/{slug}`): Coming (`pages.md` §8).

---

## (e) The charter's promises, economic results and gaps

The charter's guarantees are in "Why This Matters". Its enforcement note ties Transparent Discovery to the Chameleon Filter and the Trust-Weighted model, Data Dignity to 0010 and 0011, and True Locality to the Single Home Rule and the clustering taxonomy.

**Immersion and connection.** "Uniting those who shape the community with those who journey across it" (charter, Paradigm Shift).
- Each need is routed to its track: Resident, Traveler or Community (0061 principle 4; `SearchIndex.php:39-53`).
- On Let's Explore, the place's own journeys and stories come first (0058 §19).
- Topic answers draw on our own catalog (0133).

**True locality, without noise.** "Understand a region… without wading through sponsored listings."
- Results are local first, never only local (0061, from 0060).
- Local words act as the dictionary (`LocalWords.php`; `SearchText.php:450-455`).
- Demand is counted by state → county → town (`SearchDemand.php:125-155`).
- A "near" place must be within 40 miles (0058 §23).

**Discovery that can't be bought.** "Visibility can be bought…; they want a system where the architecture supports clean discovery tools."
- No code shown takes payment into account.
- Presets come "never from admins" (`SearchPresets.php:9`).
- Learned routes come from meaning, not usage (`SearchDrafts.php:18`, `:383-400`).

**Frictionless agency.** "Move between macro-views and hyper-local details seamlessly."
- Search opens in place and closes back to where you were (0053 §1).
- Answers show while typing, with phones folding the tools into one line (0061).
- Reading chips can be removed, and every reading offers another way to read it (`SearchReading.php:174-201`).

**Data dignity.** "Respect data minimization."
- Search counts never store who, only words, area and day (0061 principle 8; `SearchDemand.php:8-9`; `SearchPresets.php:192-195`).
- Own-phrase counts are stored under a hash of phrase and place for one hour (`SearchPresets.php:127-143`).
- Sensitive searches are treated as ordinary searches (0061 principle 7).

**Economic results**

| For | Result |
|---|---|
| **Businesses** | Completing a listing makes it eligible for urgent needs (0061 principle 5). Verified and claimed listings rank higher (`SearchIndex.php:400`; 0133 note, "once claimed that pushes them to the top"). Call, directions and website taps are counted in Business Insights (0058 §9). Demand gaps feed the crawler (`SearchDemand.php:8`). |
| **Members** | Saved and own quick searches; no search profile kept on them. A paid plan opens AI Assist (0133). No member revenue from this area is shown. |

**Gaps and tensions for human review**

1. **Who makes new situ