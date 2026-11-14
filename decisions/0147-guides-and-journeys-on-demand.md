# ADR 0147: Guides and Journeys on Demand

**Note (2026-11-14, decisions/0164):** Amended by `decisions/0164` R1, R9, R19, §7 (W5) (reuse of what's gathered, the research exception and guides on demand). 0133, named below, is closed and its rules are in 0164. The rest of this record stands; where it and 0164 disagree, 0164 rules.

**Status:** Accepted (Jason, 2026-11-08), spending decided in §5.

## Why

Jason, in Dana Point, California: "as a user I only have the Google resources to find things around me, but if I search
traversence.com I would like the system to generate both city guides and journeys prompted by my search and location
target." The crawler and Claude build the place's guide and its suggested journeys (decision 0146), and the place's
research stays ours, so the next visitor gets it from our own record.

## 1. What happens when someone searches a place we don't cover yet

1. **At once, from what we have:** the search answers with whatever the record already holds near the place: listings,
   landmarks, outdoor places, experiences, events, the weather (`Weather`), and the nearest guides. Never an empty page.
2. **The place is named:** the search or the visitor's location is matched to a real place in our gazetteer
   (`app/data/US.txt`): a town, a city or a park. A guess never starts a build.
3. **A guide is started at once** (§1a): the visitor watches a first look build in about two minutes, and can follow
   the place to hear when the full guide is ready (members only, their choice).
4. **The crawler gathers, free sources first** (decision 0131 step 3, `Guides::gather()`): the dossier, the place's
   official sites, OpenStreetMap, Wikidata, the National Register, the Library of Congress, and the businesses' own
   sites into listings.
5. **Claude writes the guide** from that record (decision 0131: the research brief, the guide, the scrub and its
   checks), and it publishes itself by the score, as guides do now.
6. **Then its journeys:** the journey job reads the guide's themes (0146 §1), builds the routes by rules with our own
   travel times (0146 §7), and Claude writes each journey's title, why to take it and a line per stop, from the guide and
   the brief only (no new web searches). The journey is checked like a guide (§3) and labelled "Suggested by Traversence".
7. **What the visitor asked for counts:** the words of the search (counts only, never who) are part of the guide's
   "visitors' intent" (decision 0131) and the themes its journeys favour. A search for "tide pools near Dana Point" makes
   tide pools a theme to look for.

## 1a. How fast: a first look in about two minutes (Jason, 2026-11-08)

"Can we create this instantly or within a reasonable please wait time frame." Yes, in two stages.

**Stage 1, the first look (target: about two minutes, to be measured on the live site and shown in Insights):**

| Time | What the visitor sees | What runs |
|---|---|---|
| At once | What we already hold near the place, the map, the weather, and "Building your guide to Dana Point", with its steps ticking off | The search starts the build in the background on our own server (as **Write a draft with Claude** does now, `Guides::compose`: up to 4 minutes, carries on if the visitor leaves) |
| ~10–30 s | "Finding places" → "Reading its history" | The fast free sources, all at once: our listings and landmarks, OpenStreetMap's places, Wikidata and the encyclopedia summary, the town's official home page, the weather service |
| ~30–90 s | Each section of the guide appears as Claude writes it | Claude writes from that record: the shorter first-look pass, with no more than one web search |
| ~90–120 s | "Suggested journeys" appear | Journeys built by rules from the guide's themes, with travel times (one matrix call, then our own `travel_legs`); Claude writes their titles and lines |

- The page checks for progress every few seconds; it never holds a request open. If the visitor leaves, the build
  finishes anyway and is there next time.
- The first look passes the same checks as any guide: the scrub, protected places (decision 0066), and every named
  place matched to our record. A name we can't confirm isn't shown as a stop or link.
- It's labelled **"First look: we're still researching Dana Point"**, and isn't offered to search engines until the full
  guide passes its score (decision 0131). That's the St. Johns lesson: no thin guide presented as finished.

**Stage 2, the full guide (within 24–48 hours, in the background):** the slow, deeper sources (the Library of Congress
and its newspapers, the National Register, the crawler's visits to businesses' own sites, the research brief) are
gathered as now (`Guides::gather()`, `gatherTick()`). Claude then rewrites only its own first-look sections from the
richer record, the journeys are rebuilt, the "First look" label comes off when the guide passes its score, and
followers are told.

**When today's on-demand allowance is spent (§5):** the visitor still gets the instant part (what we hold, the map,
the weather, places found, journeys built by rules) at once, with "Claude's write-up is queued for tomorrow". Nothing
waits empty.

## 1b. Notify me when it's ready (Jason, 2026-11-10)

The build page has **Notify me when it's ready**. Signed in: a notice in the person's Pulse (kind `guide_ready`, which
they can switch off in App Settings) and an email to their account, or only the Pulse's email when that's how their
notices already come. Signed out: an email to the address they type, once, and the address is cleared when it's used;
five a day from one device, then the page asks them to sign in. If the page is still open in another tab and they allow
it, the browser shows its own notice too. Whoever asked hears once, when the first look goes live, however it got there
(the build steps and the 5-minute job both check). System emails, so no cost. Built: `guide_watchers`,
`OnDemand::watch()` / `notifyReady()`, `{action: notify}` on `/api/guide-build.php` (`guide-notify_20261110.zip`).

## 2. Built once, kept, reused

Everything gathered is ours to reuse (decision 0133: the licence decides each fact's use): the dossier and research
brief (renewed yearly), listings (the band cadence), landmarks, travel legs (0146 §7), events, and the guide and
journeys themselves (checked every 30 days). A second search for the same place builds nothing; it reads what's there and
raises the place in the queue for its next check.

## 3. The rules it follows

- **Decision 0133 and 0066:** curators, not controllers; "tell the story, never the way in"; protected places held by
  `Landmarks::isProtected()` / `visibleSql()`.
- **Accuracy:** every stop in a journey is a place in our record, confirmed open by the crawler; a name Claude gives
  that we can't match to a confirmed place is dropped, never invented into a stop.
- **Fairness:** a business is in a guide or journey because it fits, never because it pays (decision 0007).
- **Privacy:** the visitor's location is used only to name the place; it isn't kept with who they are.
- **Labelling:** "Compiled by Traversence" on the guide and "Suggested by Traversence" on journeys, as now.

## 4. Limits, so a search can't run up work

- One build per place at a time; a place searched again while building just waits.
- The daily money allowance in §5 is the limit. Beyond it, places queue for the next day, most-searched first
  (distinct visitors, not raw searches). An optional count cap (`guides_on_demand_day` in Thresholds & gates) is off.
- Searches from one connection count once per place per day (the same guard as likes, `LikeGuard`'s speed rules).
- Nothing outside the United States until our sources cover it (the gazetteer says where).

## 5. Cost: half the daily AI allowance (Jason, 2026-11-08)

The crawler, our own research and the routes are free. Claude's writing is not.
- **The daily allowance** is the AI monthly cap (`AI_MONTHLY_CAP_USD`, System Configuration → AI use) divided by the
  days in the month.
- **On-demand guides and journeys may spend up to 50% of it each day** ("so we can create guides fast and within budget
  while traffic is low"), as two features in AI use: *Guides on demand* and *Journey writing*, sharing that half. The
  share is a setting there (default 50%).
- **Before a build starts**, its cost is estimated the way Preview does (decision 0133, Content templates). If it would
  take today's on-demand spend past the half, it **queues** for the next day instead, most-searched first. A build
  never stops part way.
- Unspent allowance doesn't carry over. Everything else Claude does (guide renewals, AI Assist, events) keeps the other
  half and its own budgets.
- **While queued**, the place still gets its free work at once: the crawler gathers, and journeys are built by rules
  from what's there, shown without Claude's words until they're written.
- This is a stated exception to "no income, no expense" (CLAUDE.md), the same one guides already have, and never past
  the cap Jason sets. Insights → Guides shows each day's on-demand spend against its half, and what's waiting.

## 6. Built as

- A **Suggested journey** content template (decision 0133, Content templates): what it gathers, what Claude writes,
  its checks and shelf life, with Preview and Try it, so its words and cost are shaped before anything is spent.
- `JourneyPlan` (0146 §7) and `travel_legs`.
- The search's "building a guide" answer and Follow, in Quick Search and the place page.
- Insights → Guides: places asked for, built, waiting, and the month's cost by feature.
- The audit (decision 0145): every on-demand guide and journey becomes a guide path once live.

## Built (2026-11-08): `guides-on-demand_20261108.zip`

- **Search:** a place outside our areas with no guide gets a card, "Build the guide to Dana Point, CA" (or "See how
  it's going", or "Open the first look"), from `OnDemand::offer` (`UniversalSearch`, `js/search-panel.js`).
- **`/hub/build.php`:** the place becomes one of our towns (`anchors`, community 0: outside our areas), real places only
  (`zip_coordinates`). The page's own script starts the build (`POST /api/guide-build.php`, with the security token)
  and checks in every 1.5 seconds; each check runs the next step under a lease (`OnDemand::step`), never twice at once.
  A step that outlasts the connection runs on (`ignore_user_abort`).
- **Steps:** places (the dossier, OpenStreetMap's places to eat and stay, outdoor places, landmarks) → journeys (by rules;
  outside our areas, OpenStreetMap's places to eat are stops, credited, never listings, decision 0065) → write (the
  first look: `Guides::$firstLook`, no research brief bought, shorter, feature *Guides on demand*, at most one search)
  → journeys again → deep (the guide job's gathering: Library of Congress, newspapers, research brief).
- **Live as a first look** when it has sections and no name holds it back (decision 0066); labelled "First look" on the
  guide. The job `guide_ondemand_tick` (every 5 minutes) carries on builds a visitor left, writes queued ones, and,
  once the research is in (or 48 hours pass), has Claude rewrite its own sections; the label comes off when the score
  reaches the publishing line.
- **Allowance:** Thresholds & gates → *Guides on demand: share of the day's AI allowance* (`guide_ondemand_share`,
  default 50%); today's spend is *Guides on demand*'s in `ai_jobs`; a first look's cost is estimated from the last ten
  (40¢ until there are some). The full guide's research and rewrite spend from their own features (*Research briefs*,
  *Guide drafts on their own*).
- **Still to build:** Follow and "your guide is ready" notices, Claude's journey lines, and Insights → Guides (asked
  for, built, waiting, cost).

**Fix (2026-11-08, `ondemand-fix_20261108.zip`):** the live `anchors.cluster_id` has a foreign key to `micro_clusters`,
so a town outside our areas couldn't be made with community 0. Migration `2027-02-13_anchors_outside.sql` lets the
column be empty (NULL; the key stays for every town that has a community; nothing removed), and such towns are made
with NULL. Search also offers the guide when the words name a real town with no place set ("dana point", "things to do
in dana point"): the most populous town of that name (`OnDemand::townInWords`). A database error is never shown to a
visitor.
