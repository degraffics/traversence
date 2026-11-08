# ADR 0147: Guides and Journeys on Demand

**Status:** Accepted (Jason, 2026-11-08), with the AI spending question in §5 to confirm.

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
3. **A guide is started:** if the place has no guide, the search says "We're building a guide to Dana Point. Come back
   soon, or follow it" (Follow emails or notifies once it's live; members only, their choice). The place goes to the
   front of the guide queue.
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
- A daily number of on-demand places (a tuned value in Thresholds & gates, `guides_on_demand_day`, default 10).
  Beyond it, places queue for the next day, most-searched first (distinct visitors, not raw searches).
- Searches from one connection count once per place per day (the same guard as likes, `LikeGuard`'s speed rules).
- Nothing outside the United States until our sources cover it (the gazetteer says where).

## 5. Cost (to confirm)

The crawler, our own research and the routes are free. Claude's writing is not. Preview (decision 0133, Content templates)
shows the cost per place before anything is spent, and Insights shows what each month really cost.
- **Suggested:** on-demand guides and journeys spend from the same **AI monthly cap** guides already use
  (`AI_MONTHLY_CAP_USD`, System Configuration → AI use, decision 0131), as two features: *Guides on demand* and
  *Journey writing*, each with its own monthly budget. When a budget is spent, places still get a guide gathered and
  journeys built by rules (no Claude), and Claude writes them when the next month's budget opens.
- This is a stated exception to "no income, no expense" (CLAUDE.md), the same one guides already have, and only up to
  the cap Jason sets. Setting the budgets to $0 keeps everything free (rules-only journeys, no new guides written).

## 6. Built as

- A **Suggested journey** content template (decision 0133, Content templates): what it gathers, what Claude writes,
  its checks and shelf life, with Preview and Try it, so its words and cost are shaped before anything is spent.
- `JourneyPlan` (0146 §7) and `travel_legs`.
- The search's "building a guide" answer and Follow, in Quick Search and the place page.
- Insights → Guides: places asked for, built, waiting, and the month's cost by feature.
- The audit (decision 0145): every on-demand guide and journey becomes a guide path once live.
