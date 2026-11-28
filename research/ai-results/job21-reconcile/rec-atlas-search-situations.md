# Platform atlas: search-situations (merged from two passes)

*Draft for human review. **P1** = pass1 and **P2** = pass2. Unmarked items are supported by both passes. Where the two passes cite different lines for the same point, both citations are given. **Both passes are cut off before they end.** P1 stops partway through "Economic results" and has no section on gaps for businesses or members. P2 stops at the first item of "Gaps and tensions". Nothing after those points is reconstructed here.*

**Area in one line.** Search reads a typed phrase as a *situation* ("bit by something") or by its 5 W's. It routes each need to a track (the Chameleon Filter) and answers local-first. It learns from counted outcomes, never from who searched. (P1: 0061, 0053 §8, 0063. P2: 0061 principle 8, 0053.)

**Coverage**
- Both passes use code under `traverse-3way-sync/sync-app/lib/`.
- Both say `SituationReasoner.php`, `SearchDrafts.php` and `SearchIndex.php` are cut off.
  - P2 gives the cut points: after line 517, ~500 and ~483.
- **Passes differ** on which decision texts are truncated.
  - P1: 0061, 0058, 0053 and 0133 are truncated.
  - P2: 0061 is truncated, 0058 is seen "in part", and 0053 and 0133 are seen in full.
- *Single-pass (P2):*
  - 12 library files are shown.
  - Their headers still say `api/lib/`, while pages.md says the backend moved to `app/lib/` (0069).
  - These are not shown: `Situations.php`, `UniversalSearch.php`, `Lexicon.php`, `Learning.php`, `ResourceGuides.php`, `HowGuides.php`, `WordFamilies.php`, `/api/search.php`, `js/search-panel.js`, and every admin page's PHP.
- P1 also marks `js/search-panel.js` as not shown.

---

## (a) What each person can do, page by page

*Passes differ on structure. P1 has separate Resident and Traveler sections. P2 has one section for "Resident and traveler (anyone, signed in or not)".*

### Resident / anyone

- **Header search** appears on every page except dashboards, admin and sign-in.
  - Citations differ. P1 cites `js/search-panel.js` (not shown) and pages.md §6. P2 cites 0053 §1.
- **Predictions while typing.** Only predictions show while typing (0061 progress note).
  - *Single-pass (P2):* results come on Enter or a tap.
  - *Single-pass (P1):* predictions include "Search for …", completions, and "Go straight to" with a preview answer.
- **911 line.** A danger situation leads with "If anyone is in danger, call 911."
  - Citations differ. P1 cites 0061 principles 5–6. P2 cites principle 6.
- **Urgent needs** lead with Call.
  - *Single-pass (P2):* also open now and distance (0061 "Urgent display").
- **Incomplete-information rule.** A listing missing a phone, hours or place that a need requires is not offered for that need (0061 principle 5).
- **Fact questions** such as "blue hills hours" are answered from the listing or from a page we read (`SearchReading.php:32-47`). A confidential place gives its phone only.
  - This is P1 in (a) and P2 in (b).
  - **Passes differ** on the line range. P1 cites `:91,97-104`. P2 cites `:97-105, :91`.
- **Resource guides** at `/guide/resources.php?id=` / `?area=` cover phone lines that are not businesses.
  - P1: "each checked against its official source".
  - P2: "only verified entries show".
- **How-to guides** at `/guide/how.php?id=` are built by the crawler from official sources and published only after a person reviews them (0063 §8).
- **"Suggest it"** sends an unrecognised word to Review (0063 §2).
  - *Single-pass (P2):* a visitor's × on a suggestion is counted, and the suggestion drops at 5 (0063 piece 1).
- **"Tell us"** uses `/api/suggestion.php` (0053).
  - *Single-pass (P2):* it reports a missing place, a fix, or something closed.
- **Directory and search APIs:**
  - *Single-pass (P1):* `/directory/` offers List | Map with Quick picks (0058 §9; 0053).
  - *Single-pass (P1):* `/api/search_listings.php` and `/api/suggest.php` share `SearchText` matching (`SearchText.php:4-15`).
  - *Single-pass (P2):* `/api/suggest.php` is "the older directory type-ahead".
- *Single-pass (P2):* `/takedown.php` lets anyone ask for something to be taken down (0133).

### Traveler (Let's Explore)

- On `/discovery/`, the place's own content (journeys, stories and so on) comes first, and businesses follow (0058 §19).
- **"None here" box** (0058 §12). **Passes differ** on what it offers. These may be complementary.
  - P1: "Nearby, with '…'".
  - P2: "Recommend one, or Add a listing".
- *Single-pass (P1):*
  - Landscape words such as "badlands" find landmarks (0058 §22–24).
  - Outdoors results are indexed with an `explore` intent (`SearchIndex.php:452,480-482`).
- **Skipped landmarks.** **Passes differ** on what `SearchIndex.php:443,474` excludes.
  - P1: "held or hidden landmarks".
  - P2: "hidden landmarks and Recreation.gov places".

### Member (signed in)

- **Saved searches** are kept on the account. **Recent searches** stay in the browser only.
  - Citations differ. P1 cites 0053 §6–7. P2 cites §6 and §7.
  - *Single-pass (P2):* up to 20 saved searches, shown in `/user/dashboard.php` through `/user/api/saved-searches`.
- **Own quick searches** are kept on the device, up to 12, and counted for the current place.
  - Citations differ. P1 cites `SearchPresets.php:86-109`. P2 cites 0053 and `:96-109`.
- **AI Assist** at `/ai/` needs a paid plan and AI consent (0133, "Search, step two").
  - *Single-pass (P1):* it writes from the Topic index.
- *Single-pass (P1):* "Looking for a person?" hands off to the Address Book. People are never in universal search (0053 §2).

### Business

- **Passes differ** on whether businesses have search-related actions.
  - P1: "No direct search controls are shown".
  - P2: businesses can claim and complete a listing via `/claim.php?listing=` and `/listing/businessportal.php`.
- **Completeness gates urgent needs.** Search oversight lists incomplete listings as "a reason for the business to claim and complete it".
  - Citations differ. P1 cites 0061 principle 5 and Oversight. P2 cites principle 5 and the Resource guides progress note.
- **Counted actions.** Call, directions and website taps are counted in Business Insights (0058 §9).
  - *Single-pass (P2):* the Insights route inside the portal is not shown.
- **Ranking:**
  - Rank weight is verified +10, claimed or pending +5, closed −40 (`SearchIndex.php:400`). This is P1 in (a) and P2 in (b) and (e).
  - Once a listing is claimed, it is pushed to the top (0133 note).

### Staff (admin, Live per pages.md)

| Route | What it's for |
|---|---|
| `/admin/search-misses.php` | Teach search, Meanings, Workbench, Drafts. *P1 adds ✦ Analyze. P2 adds missed searches and "Predictions while typing".* |
| `/admin/search-learning.php` | What search learned from opens |
| `/admin/search-oversight.php` | What changed in how search reads people. *P2 adds resource numbers to check and incomplete listings.* |
| `/admin/search-demand.php` | Demand by place. *P2 cites 0066.* |
| `/admin/resource-guides.php`, `/admin/how-guides.php` | Guide editors |
| `/admin/search-index.php` | Build the 5W index |
| `/admin/insights.php` | Usage counts (0058 §13). *Single-pass (P2).* |

*Single-pass (P1):* the off-site crawl endpoints are `/api/crawl/learn.php`, `guides.php` and `targets.php`.

---

## (b) How it works underneath

| Piece | Finding | Citations |
|---|---|---|
| 5W index | One row per listing, outdoors place, story, group or topic. Nothing personal is indexed. | P1 `SearchIndex.php:15-31`, `:25`. P2 `:16-30`, `:25`. |
| Sections / Chameleon | listing → Get Local; group/topic → Connect; outdoors/story → Explore. Market matches nothing. | `SearchIndex.php:39-53`, `:51` (P1 in b, P2 in e) |
| Who / audience | Read only from the thing's own words. | P1 `:80-88,189`. P2 `:79-88`. |
| Why intents | Regex intents, never read from the name. | *Single-pass (P1)* `:58-70`, `:376-379` |
| Confidential listings | Carry `private_location`. | *Single-pass (P2)* `:385`, `:401` |
| Situation map | `Situations::read` / `Situations.php`, **not shown**. | P1 lists references at `SearchDemand.php:88`, `SearchDrafts.php:91`, `SearchEdits.php:223` |
| `UniversalSearch` | **Not shown**. | P1: called at `SearchPresets.php:130,137` |
| 5W parse order | Why → What → Where → When → Who. | *Single-pass (P2)*, 0061 addendum |
| Meaning layer | `SituationReasoner.php`: word classes and frames, taught rules first, cues for weak words. | P1 `:28-249`, `:377`, `:251,504`. P2 `:28-163`, `:168-249`, `:377`, `:504` |
| Meaning layer, P1 detail | "a bit" is read as an amount. | *Single-pass (P1)* `:471` |
| Meaning layer, P2 detail | harm + venomous → `someone-hurt`; reasoning given in words; no AI. | *Single-pass (P2)* `:174`, `:515`, `:2-3` |
| Word matching | Strict word-start match, with a loose fallback. | *Single-pass (P1)* `SearchText.php:209-241`, `:25`, `:221-226`, `:115-131` |
| Spelling | "Did you mean" is offered, never applied. Local words come first. | Both `:375-380`. Local-first: P1 `:452-455`, P2 `:450-455`. P1 range `:423-498`. |
| British spellings | Folded. | *Single-pass (P2)* `