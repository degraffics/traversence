# Platform atlas: search-situations

*Draft for human review. Sources are the attached documents and the attached code under `traverse-3way-sync/sync-app/lib/`. Each code file was capped at about 40,000 bytes. `SituationReasoner.php`, `SearchDrafts.php` and `SearchIndex.php` are cut off partway through, and decisions 0061, 0058, 0053 and 0133 are truncated. Anything outside what is shown is marked **not shown**.*

**Area in one line.** Search reads a typed phrase as a *situation* ("stuck on the highway", "bit by something") or by its 5 W's. It routes each need to a track (the Chameleon Filter) and answers with complete, local-first results. It learns from counted outcomes, never from who searched (decisions/0061, 0053 §8, 0063).

---

## (a) What each person can do, page by page

### Resident (Get Local)

- **Header search on every page except dashboards, admin and sign-in** (`js/search-panel.js`; not shown).
  - Calls `/api/search.php`, "Universal search, typing and Enter" (site-audit/pages.md §6).
  - While typing, it shows predictions only: "Search for …", completions, and "Go straight to" with a situation's preview answer (decisions/0061, "predictions only while typing").
- **Situation answers.**
  - Each need is listed with its track. Call comes first when urgent.
  - A danger situation leads with "If anyone is in danger, call 911."
  - A listing missing the phone, hours or place a need requires isn't offered for it (decisions/0061 principles 5–6).
- **Fact questions** ("blue hills hours"):
  - The answer comes straight from the listing, or from a page we read (`SearchReading.php:32-47`).
  - A confidential place gives its phone only (`SearchReading.php:91,97-104`).
- **Get Local directory** `/directory/` (Live): List | Map, with Quick picks in the search (decisions/0058 §9; decisions/0053).
  - `/api/search_listings.php` and `/api/suggest.php` share `SearchText` matching (`SearchText.php:4-15`).
- **Resource guides** `/guide/resources.php?id=` / `?area=` (Live): phone lines that aren't businesses, each checked against its official source (decisions/0061, Resource guides).
- **How-to guides** `/guide/how.php?id=` (Live): crawler-gathered steps from official sources, published only after review (decisions/0063 §8).
- **"Tell us"** via `/api/suggestion.php` and **"Suggest it"** for an unrecognised word (decisions/0053; decisions/0063 §2).

### Traveler (Let's Explore)

- On `/discovery/` (`track=explore`), search puts the place's own content first: journeys, stories, outdoors, groups. Businesses follow (decisions/0058 §19).
- Landscape words ("badlands", "hoodoo") find landmarks and outdoor places (decisions/0058 §22–24).
- Outdoors results come from `/place/recreation.php` and `/place/landmark.php`, indexed with an `explore` intent (`SearchIndex.php:452,480-482`).
- Held or hidden landmarks are never indexed (`SearchIndex.php:443,474`).
- "Nothing here? Say so" gives a "none here" box with "Nearby, with '…'" (decisions/0058 §12).

### Member (signed in)

- **Saved searches** are on the account. **Recent searches** stay in the browser only (decisions/0053 §6–7).
- **Own quick searches** are kept on the device (up to 12) and counted for the current place (`SearchPresets.php:86-109`).
- **AI Assist** `/ai/`: for members with a paid plan and AI Assist & Learning consent. It writes from the Topic index (decisions/0133, "Search, step two").
- **"Looking for a person?"** hands off to the Address Book. People are never in universal search (decisions/0053 §2).

### Business

- **No direct search controls are shown.** A business benefits indirectly:
  - **Completeness gates visibility for urgent needs.** Search oversight lists the "Incomplete listings" a situation would have shown, as "a reason for the business to claim and complete it" (decisions/0061 principle 5 and Oversight).
  - **Verification affects order.** Index weight is verified +10, claimed/pending +5, closed −40 (`SearchIndex.php:400`).
  - **Confidence affects order.** Get Local's Best match orders by listing confidence, and "once claimed that pushes them to the top" (decisions/0133, 2026-11-19 note).
  - **Counted actions** (Call, Directions, Website) appear in Business Insights (decisions/0058 §9).

### Staff (admin routes, all Live per site-audit/pages.md §5)

| Route | What it's for |
|---|---|
| `/admin/search-misses.php` | Teach search, Meanings, ✦ Analyze, Workbench, Drafts |
| `/admin/search-learning.php` | What search learned from what people open |
| `/admin/search-oversight.php` | What changed in how search understands people |
| `/admin/search-demand.php` | Where people look and what for |
| `/admin/resource-guides.php` | Phone lines and services that answer a situation |
| `/admin/how-guides.php` | How-to guides: asked for, found, published |
| `/admin/search-index.php` | Build the 5W search index |

Off-site worker endpoints: `/api/crawl/learn.php`, `/api/crawl/guides.php`, `/api/crawl/targets.php`.

---

## (b) How it works underneath

| Piece | File | What it does |
|---|---|---|
| 5W index | `SearchIndex.php:15-31` | One row per listing, outdoors place, story, group or topic, tagged What / Who / When / Where / Why. Nothing personal is indexed (`:25`). |
| Why intents | `SearchIndex.php:58-70` | Regex intents (eat, stay, heal, help, explore, connect, buy, fix, learn), read from category and offerings, never the name (`:376-379`). |
| Sections (Chameleon funnel) | `SearchIndex.php:39-53` | listing → Get Local; group / `ct:` topic → Connect; outdoors / story → Let's Explore. Market maps to `'1 = 0'`, i.e. nothing searched (`:51`). |
| Who / audience | `SearchIndex.php:80-88,189` | Only from the thing's own words. "-owned" only as the listing states it. |
| Situation meaning layer | `SituationReasoner.php:28-249` | Events, actions, tools, times, modifiers, wrappers, things, and `FRAMES` / `ACTION_FRAMES`. Taught rules are checked first (`:377`). Cues decide weak events (`:251,504`). "a bit" is an amount, not a bite (`:471`). |
| Phrase map, `Situations::read` | not shown | Referenced at `SearchDemand.php:88`, `SearchDrafts.php:91`, `SearchEdits.php:223`. |
| Query understanding, `UniversalSearch::understand/search` | not shown | Called at `SearchPresets.php:130,137`. |
| Word matching | `SearchText.php:209-241` | Strict word-start, loose fallback below 5 results (`:25`), short words matched whole (`:221-226`), multi-word things joined (`:115-131`). |
| Spelling | `SearchText.php:423-498` | "Did you mean" is offered, never applied (`:375-380`). Local words come first (`:452-455`). |
| Local dictionary | `LocalWords.php:2-16,164-236` | Built daily from our own listings, place store, towns and visible landmarks (`:222`). Business names are never treated as kind clues (`:169-171,196`). |
| Concepts (WordNet → kinds) | `Concepts.php:5-12,318-410` | Sense chosen by local context (`:419-449`). Staff terms are read live (`:150-160`). |
| Reading line / facts / pages | `SearchReading.php:174-201,32,144-167` | "Read as …" with alternatives. Pages served only from sites read regularly (`:142,160`). |
| Presets | `SearchPresets.php:22-84` | Per place, from counts of what's there, time of day, and what's asked (≥3 asks/30 days for a category, `:35,75`). No admin curation (`:9`). |
| Correction (Workbench) | `SearchEdits.php:9-28,38-119` | Guided values per W. Who is "never a person" (`:20`). Dry run in a rolled-back transaction (`:230-241`). Logged with Undo (`:160-195`). |
| Drafts and auto-learning | `SearchDrafts.php:35-153,475-495` | Word, rule and kind drafts from `search_outcomes`. Auto-applied when backed by ≥3 searches. Unused auto kinds retired after 60 days (`:351-376`). |
| Demand funnel | `SearchDemand.php:7-14,71-95,125-155` | state → county → town → anchor → destination, counts only. Feeds crawler targets. |
| Trace (✦ Analyze) | `SearchTrace.php:2-22` | Off unless switched on (`:11`). |

**Tables named in the shown code:**
- `search_index`, `search_index_state`, `search_counts`, `search_demand`
- `search_terms`, `search_outcomes`, `search_opens`
- `search_meanings`, `search_senses`, `search_frames`, `search_edits`, `search_drafts`
- `situations`, `situation_needs`, `situation_triggers`, `category_terms`

Their schemas and migrations are **not shown**.

---

## (c) Decisions

| Decision | What it rules |
|---|---|
| **0053** Universal search, in place | Panel over the page; peek before you go; chips; 5W model (§8); presets from aggregate signals, never admins (§5); recent searches browser-only, saved searches by choice, query text never stored against a person (§7); no people in universal search (§2). |
| **0058** (§9, §12, §13, §16, §19, §22–24) | Counts of listing actions; the "none here" box; Insights counts; broad words stay words; search follows the section; widening on Let's Explore; natural landmarks. |
| **0061** Situations | Day One deterministic with no AI model, Day Two learns; the situation map is data, not code; Chameleon routes by the reading; the incomplete-information rule; Admin-only danger list; sensitive searches are just searches; counts keep the words, never who (§8, changing 0053 §5); confidence formula and thresholds; crawler sources-first tiers. |
| **0063** Lexicon and syntax | Lexical category and job per word; "We don't recognize …"; suggestions removed, not edited; 5W correction with a dry run; reading kept separate from showing (§5); frame-first builder; apprentice drafts (§7, amended by 0064); crawler-built how-to guides (§8). |
| **0133** (superseded by 0164) | Search thresholds 0.80 / 0.60 (replacing 0061's); `Topics::answer` and "From our guides" in search; protected tiers apply to the Chameleon Filter. |
| **0157, 0067, 0093, 0064, 0068, 0066, 0164** | Cited in the code (e.g. `LocalWords.php:2`, `Concepts.php:6`, `SearchDrafts.php:18`, `SearchDemand.php:8`). The texts themselves are **not shown**. |

---

## (d) Live vs coming

**Live** (site-audit/pages.md):
- Search: `/api/search.php`, `/api/search_listings.php`, `/api/suggest.php`, `/api/suggestion.php`.
- Pages: `/directory/`, `/discovery/`, `/guide/how.php`, `/guide/resources.php`, `/ai/`.
- All the admin search tools listed in (a), and the crawl endpoints `learn`, `guides`, `targets`.
- `/hub/build.php` is listed as "Live once uploaded". Its tie to search isn't shown.

**Built per decision progress logs:**
- 0061 batches 1–3: map, editor, resource guides, oversight, learning, gaps.
- 0063 pieces 1–4 and how-to guides.
- Own-quick-search counts (0053 note 2026-11-22; `SearchPresets.php:86-143`).

**Coming or not built:**
- **Market search.** The funnel returns nothing (`SearchIndex.php:51`). Decisions/0053 shows Market as "soon", yet `/market/` pages are listed Live.
- **Events.** "when they exist" (0053 §2, 0061).
- **`#` theme pages.** "Not built yet" (0058 §3).
- **Public topic pages.** "a later decision" (0133).
- **"Add to a trip"** is greyed as "coming" (0058 §20 note).
- **"See all" carrying When to the directory.** Listed as a gap (0053, 5W-1).
- **Clean addresses** `/listing/{slug}` and `/{hub-slug}`. Coming (pages.md §8).

---

## (e) Charter promises, economics, and gaps

The charter names four guarantees, each to be "enforced elsewhere" (charter.md, "Why This Matters"). The mission adds immersion and connection.

### Immersion and connection
- Delivered by:
  - Let's Explore search leading with journeys, stories and outdoors (0058 §19).
  - Topic answers with vetted facts linked to "Walking in the footsteps" (0133).
  - Community-track routing for "meet people" (0061 principle 4; `SituationReasoner` frames).
- **Gap:** Connect results are groups and community topics only (`SearchIndex.php:49`). Events and person discovery are excluded by design (0053 §2).

### True locality without noise
- Delivered by:
  - A local dictionary built from our own data (`LocalWords.php:2-4`).
  - Local context choosing word senses (`Concepts.php:412-418`).
  - Results local first, never only local (0061 status line).
  - The Where reading via the gazetteer and landmarks (0061 addendum).
- **Gap:** `SearchDemand::inside` uses `associated_zips LIKE '%"zip"%'` (`SearchDemand.php:61-62`). It assumes JSON storage, which isn't shown.

### Discovery that can't be bought
- Delivered by:
  - Presets that "come from the system, not admins" (`SearchPresets.php:9`; 0053 §5).
  - Ranking weight from status and data only (`SearchIndex.php:400`).
  - Routes decided by meaning, never by opens alone (`SearchDrafts.php:18,85-86,383-400`; 0093 cited).
  - Brand §3b's "no sponsored carousels".
- **Gap:** claimed and verified listings rank higher (`SearchIndex.php:400`; 0133 note). Claiming isn't shown to cost money, but whether a paid plan affects verification or rank is **not shown**. `plan_tier` affects only About add-ons in 0058 §22.

### Frictionless agency
- Delivered by:
  - Suggestions-then-results.
  - The "Read as …" line with "read it otherwise" (`SearchReading.php:169-201`).
  - Removable chips (0053 §3) and removable suggestions (0063 §3).
  - "Did you mean" offered, never silently applied (`SearchText.php:375`).
- **Gap:** principle 2 was revised several times in one day (Enter only → 700ms pause → predictions only) (0061 progress). Which behaviour is current depends on code that is **not shown** (`search-panel.js`).

### Data dignity
- Delivered by:
  - Counts without identity: `SearchPresets.php:15-16,192-194`; `SearchDemand.php:9`; `SearchReading.php:9`.
  - Search learning keeps words by area and day, never account, device, IP or session (0061 §8).
  - Trace off by default (`SearchTrace.php:2-4`).
  - Own-phrase counts hashed and swept after an hour (`SearchPresets.php:111-125,134`).
- **Gaps:**
  - The dedupe signature sits in `$_SESSION` (`SearchPresets.php:213-217`). This is consistent with 0058 §13's "inside its own session", but the session lifetime is not shown.
  - `SearchDemand::fromSearch` falls back to the raw query text as the anchor (`SearchDemand.php:90`). Decisions 0061 §8 allows words, but there is no visible minimum-count rule here. 0058 §13 shows words only at ≥3 visits, and no such gate is visible in `SearchDemand::funnel` (`:125-155`).

### Economic results

**Businesses**
- Being complete (phone, hours, place) is what makes a listing