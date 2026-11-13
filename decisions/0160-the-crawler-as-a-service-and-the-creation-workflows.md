# ADR 0160: The crawler as one service, and each creation workflow start to finish

**Status:** Proposed (Jason, 2026-11-13: "There are processes for each one of the outlined creation tools. They all use
the crawler, so we need to separate the process from the crawler and how each workflow works from start to finish.").
It follows the full vetting (`research/crawler-review-2026-11-13.md`; "the review" below, with its fix numbers A1–F27).
It extends `decisions/0133` (the one rule book for the crawler and what it feeds) and keeps every rule in it.

## Context

Today one Railway job, every 5 minutes, does the work of every creation tool in one fixed order inside 240 seconds:
bulk loads, the second look, search targets, map points, identities, refresh, the Google check, learning, heritage,
how-to guides, Common Crawl, reading a source, and then the crawl jobs. The site's scheduled jobs do the rest of each
tool's steps. So:
- the tools compete for one run's time and fetches, and the later ones starve;
- a starved step is taken and abandoned, and the site reads the silence as a result;
- no tool can be seen on its own: where its work stands, what it made, what it's waiting for.

## Decision

### 1. Two layers

- **The crawler is one service.** It fetches and reads, politely and within budgets, for whoever asks. It makes nothing
  on its own and decides nothing about what's published.
- **Each creation tool is a workflow.** It owns its trigger, its steps, its gates, its output, where a person acts, and
  its own report. It asks the crawler for what it needs.

### 2. What the crawler does (the service)

| Service | What it does | Today |
|---|---|---|
| **Fetch** | one page, robots.txt and Crawl-delay honoured, 1.5 s apart, private addresses refused, 401/403/451 never tried another way | `fetch_page_meta` |
| **Read a page** | its structured data, then its text: places with name, address, phone, hours, website; the kind of page; its town | `read_page`, `pdf_page`, `lines_page` |
| **Read a site** | its sitemap, its own links, the page log (unchanged pages answer 304), links to other sites for the register | `read_source`, `sitemap_urls`, `own_site_links` |
| **Ask open data** | OpenStreetMap (Overpass), Wikidata, NPI, IRS, Recreation.gov, the Library of Congress, Census map points, Common Crawl | `overpass`, `wikidata`, bulk loads, `heritage`, `geocode`, `common_crawl` |
| **Search the web** (paid) | Tavily or Brave, only within the allowance | `search` |
| **Read the hard way** (paid) | Tavily Extract for a page it can't fetch; Textract for a scanned PDF | `tavily_extract`, `read_scan` |

It also keeps two shared memories, used by every workflow: the **source register** (which sites, read or cite only,
their terms and trust; 0045, 0157 §7) and the **place store** (every place named on a page we read, with its page;
0157 §2).

**One service, configured by the workflows** (Jason, 2026-11-13: "Crawler is one service where it can be configured by
workflows to achieve certain data capture tasks"). A workflow sets up its **capture tasks**:
- what to capture (places of a kind, a named place, a site's pages, records, events, terms);
- from where (open data, a site, the place store, the web);
- how often, and how fresh it must be;
- its share and its limits (time, fetches, paid calls).

The crawler runs them; the workflow decides what the results become.

**How the service runs:**
- Workflows put **requests** on one queue: "read this site", "find this kind of place in this town", "look up this
  name here", "read this page", "geocode these".
- Each workflow has its own share of every run's time and fetches. One workflow's backlog can't starve another.
- A request is taken only when there's time to do it. One that wasn't done goes back unchanged: never counted as a
  failure, never as a result.
- Each run reports, request by request, what was done, found, skipped and why.

### 3. The workflows, start to finish

Each workflow below: what starts it, its steps, what it asks the crawler for, its gate, what it makes, where a person
acts, and its gaps today (by the review's fix numbers).

#### W1. Listings (businesses and organizations)

- **Started by:**
  - a coverage gap (queued by hand, per geo-hub);
  - a town someone searched (0149);
  - a situation gap (0061);
  - a search target (0066);
  - Send the crawler;
  - a place named on a page we read;
  - the monthly NPI, IRS and Recreation.gov files.
- **Steps:**
  1. Find leads: open data by kind and town, or a place named on a page.
  2. Check each lead's own website.
  3. Stage the find (`IntakeStager::stage`).
  4. Gate it: real, complete, appropriate, unique (0076).
  5. If it isn't real yet, take the second look (NPI, our place store, a deep look, web search); then the second tier
     (Claude settles it), when that's on.
  6. Publish, or send to Review.
- **Crawler:** ask open data, fetch, read a page, read a site, search the web.
- **Gate:** the score (0044, 0076). Jason, 2026-11-13: one source can establish a listing, and a government page we
  read can establish one by itself; the listing is then vetted continuously.
- **Makes:** a public listing (`unclaimed`), with its sources on its identity record.
- **A person:** Review (finds that are out of options), Listing Intake, Send the crawler.
- **Gaps:** E19 (the run starves the jobs), E21 (towns built once, failed town jobs never retry), E22 (never a named
  business; Send the crawler ignores its words), E23 (ready finds stuck in no list; 40 deep; no kind means dropped),
  C11 (OpenStreetMap's details copied), C12 (the place store ignores use rules).

#### W2. Keeping listings current

- **Started by:** time (refresh every 30 or 90 days by score; Google every 90 days; map points while missing), a
  visitor's report, an owner's edit.
- **Steps:**
  1. Re-read the listing's own website: fill empty fields, look for closed, moved or dead.
  2. Check with Google (closure only).
  3. Fill missing phone or website (identity jobs).
  4. Place it on the map (Census).
  5. Merge new finds into it.
- **Crawler:** fetch, read a page, ask open data (Census), search the web (identity), Google Places.
- **Gate:** Jason, 2026-11-13: scoring decides. One site's "closed" is a signal on the score, marked "reported
  closed", not an instant close; the listing closes when the score says so, or a person or its owner confirms.
- **Makes:** current details, map points, "closed?" items for Review.
- **A person:** Review's Closed? and fix items; the owner in the business portal.
- **Gaps:**
  - B5: abandoned checks take websites off;
  - B10: one site can close a listing;
  - E24:
    - changed phones and hours missed;
    - no live-duplicate check;
    - listings without a point invisible in "near" searches;
    - queues that stall.

#### W3. The source register and the place store

- **Started by:** a site linked from a page we read, a site a second look accepted, Common Crawl's index of local
  public sites, a person adding one.
- **Steps:**
  1. Put the site on the register.
  2. Judge it: public bodies read by themselves; chambers, tourism sites and organizations by their terms (Claude, when
     that's on); commercial and social sites cite only; a nation's sites wait for a person.
  3. Read the sites marked read on their schedule (a week to 3 months, by change).
  4. Keep what each page names in the place store.
- **Crawler:** read a site, read a page, read the hard way, search the web (terms).
- **Gate:** the register's call and each site's use rule (licence; 0133, 0157 §7).
- **Makes:** the place store (shown as "Named on pages we read"; staged into W1) and facts on listings' identity records.
- **A person:** Crawler sources (the register), Identities.
- **Gaps:**
  - B6: a starved read wipes a site's places;
  - A1: the place store's results skip the protection checks;
  - C12: use rules ignored;
  - Part 2 of the review:
    - sites waiting forever after one failed describe;
    - "Visit website" links landing on the register;
    - two page readers.

#### W4. Place guides

- **Started by:** the guide job (busiest unwritten places first, `guide_draft_auto`), a person pressing Write a draft, a
  visitor's search for a place with no guide (W5).
- **Steps:**
  1. **The dossier:** what we hold, plus open data; refreshed every 180 days.
  2. **Gathering:** the Library of Congress, up to 24 hours.
  3. **The research brief:** Claude researching on the web, once a year.
  4. **The draft:** Claude writes the empty sections.
  5. **Names linked:** listings and landmarks; Find it for those missing (W8).
  6. **Score:** it publishes itself when its confidence clears the line, else it waits as a draft.
  7. **Upkeep:** checked every 30 days: passed dates, closed businesses, dead links; triage proposes fixes; renewal.
- **Crawler:** ask open data (Library of Congress, Wikidata), search the web (Claude's own searches are its own).
- **Gate:** the confidence score, and the protected-place levels below.
- **Protected places:** the three levels of decision 0133 (2026-10-30), as built:
  - **public:** told with directions like any place;
  - **known publicly, not open to visit:** named and told, never the way in, with the authorization line;
  - **held:** never named or located, only a general acknowledgement.
- **Makes:** a public guide; its suggested journeys follow (W7).
- **A person:** the guide editor (edit, take down, Write a draft, Check my facts), takedowns, the quarterly audit.
- **Gaps:**
  - A4: held landmarks can be named through the `dismissed` path, against 0133's levels;
  - B7: a failed draft loses "kept down";
  - B8: an edit can be overwritten;
  - E25: heritage starves, so drafts lean on the paid brief.

#### W5. Guides on demand (a first look)

- **Started by:** a visitor searching a place that has no guide (0147).
- **Steps:**
  1. A first look at once, from what we hold and the free sources, with at most one web search.
  2. The full guide follows (W4) within the allowance; the rest queued.
- **Crawler:** ask open data; queue the town for W1 (0149).
- **Gate:** half the daily AI allowance (0147).
- **Makes:** a first-look guide, labelled.
- **A person:** none needed; the visitor can ask to be told.
- **Gaps:**
  - E25: failed requests are never retried, though the visitor is told they will be; the first-look label is lost on
    any rewrite;
  - F27: the requests and their spend aren't shown anywhere (0147 says Insights → Guides).

#### W6. The heritage catalog and topics

- **Started by:** a place with a guide, every hour.
- **Steps:**
  1. Library of Congress catalog records and newspaper pages for the place.
  2. Claude (Haiku) reads the passages into facts, each tiered public, story only or held.
  3. Facts gather into topics; Claude writes each topic's summary from vetted facts.
  4. Thin, asked-for topics are researched on the web.
- **Crawler:** ask open data (Library of Congress).
- **Gate:** vetting (sources counted), and the tiers (0133: "tell the story, never the way in").
- **Makes:** the heritage catalog (Seeker), the Topic index, facts for guides.
- **A person:** Seeker, the Topic index.
- **Gaps:**
  - A2: a nation's cemetery tiered public;
  - E25:
    - starves in the worker;
    - an empty Library of Congress search not asked again for a year;
    - the extraction queue can stall;
    - each newspaper page counts as an independent source.

#### W7. Suggested journeys

- **Started by:** a live guide, hourly, 3 guides a run (0146).
- **Steps:** build journeys from the guide's own places by rules (no AI): legs, times, travel.
- **Crawler:** none (openrouteservice for travel times).
- **Gate:** rules.
- **Makes:** suggested journeys on the guide.
- **A person:** none today. Editing, pinning and hiding aren't built (the "a person's version stays" path is
  unreachable).
- **Gaps:** C11 (OpenStreetMap's hours and addresses copied into stops).

#### W8. Find it (places a guide names)

- **Started by:** a name in a guide or story that we don't hold, every 15 minutes.
- **Steps:**
  1. Our crawler first (OpenStreetMap, the place's own website).
  2. Then Claude on the web (`guide_find`).
  3. Confirmed places go to Review, or become listings by themselves when their score allows (`guide_find_add`).
- **Crawler:** ask open data, fetch, read a page, search the web.
- **Gate:** the listing score (W1's gate).
- **Makes:** listings, and linked names.
- **A person:** Review.
- **Gaps:** shares W1's.

#### W9. Landmarks and places on demand

- **Started by:** the monthly landmark file; a place someone opens or searches that we don't hold (0148).
- **Steps:**
  1. Load or look up (Wikidata, OpenStreetMap).
  2. Place it: on a nation's land?
  3. Decide: public, held or closed (0066).
  4. Write a short description when it has no Wikipedia summary (`place_about`, once).
- **Crawler:** ask open data.
- **Gate:** 0066: `Landmarks::visibleSql()`; pins on a nation's land move to a public place.
- **Makes:** landmarks and places, with their descriptions.
- **A person:** the landmarks admin; a nation's own tools when they exist (0066).
- **Gaps:**
  - A3: the OpenStreetMap layer has no nations check;
  - B9: the monthly load deletes held rows;
  - C13: Wikipedia "cite only" bypassed when a place is looked up.

#### W10. Live events

- **Started by:** a live guide, while the Live events template is running (0131 §5).
- **Steps:**
  1. The place's official event pages first.
  2. Then web search (Claude) only for what they don't list.
- **Crawler:** read a site (event pages), search the web.
- **Gate:** official sources first; the allowance.
- **Makes:** upcoming events on the guide.
- **A person:** Live events admin.
- **Gaps:** event pages' dates aren't kept by the crawler's page reading (Part 2 of the review).

#### W11. How-to guides

- **Started by:** a need with official guidance (0063 §8), 2 a run.
- **Steps:**
  1. Search for official pages (.gov, .edu, state).
  2. Read them.
  3. Write the steps from them.
- **Crawler:** search the web, fetch, read a page.
- **Gate:** official pages only.
- **Makes:** a how-to guide.
- **A person:** How-to guides admin.
- **Gaps:**
  - E25: when the run's searches are spent, marked "No official page found" for 30 days;
  - C14: steps copied word for word with no check of the source's terms.

#### W12. Search learning (demand)

- **Started by:** what people search, and what they open after.
- **Steps:**
  1. Score words against kinds; publish or retire what's learned.
  2. Find situation gaps: a need with nothing within 25 miles.
  3. Gaps open data covers become W1 jobs.
  4. The rest become web searches (Tier 2) for public sources.
- **Crawler:** search the web (Tier 2); queues W1.
- **Gate:** 3 or more searches in 30 days.
- **Makes:** learned words, situation gaps, new sources.
- **A person:** Search learning, Search demand.
- **Gaps:**
  - learning runs only when the worker calls it;
  - only cluster-scoped searches count;
  - gap jobs starve behind coverage jobs (E22).

#### W13. Stories

Person-written only (0043): no crawler. A story's named places go to Find it (W8).

### 4. Each workflow gets

1. **Its own queue and status:** what's waiting, running, done, failed and why; retry and requeue from the admin.
2. **Its own share of the crawler.**
3. **Its own report,** in the admin and in Pulse when it stalls.
4. **Its own costs,** counted honestly (a failed paid call counts).
5. **Its decision named,** so its rules are in one place.

### 5. The always-on Claude features (Jason asked, 2026-11-13)

These run by themselves on a schedule, switched on by default, under the $25 monthly AI cap:
- research briefs (W4);
- guide drafts on their own (W4);
- names in guides, journeys and stories (W4, W8);
- guide fixes before a rewrite (W4);
- heritage facts (W6);
- topic summaries and topic research (W6);
- live events (W10);
- Find it (W8).

**Decided (Jason, 2026-11-13):** "These are system dependent tools for research and development of content and UI/UX,
so yes they are exception based on us have a capped usage budget and we must accurately account for their costings so
the cap is enforced."
- They're within CLAUDE.md's AI exception.
- **On one condition:** every call's cost is counted, a failed call after a paid answer included (the review's D16), so
  the cap really holds. Fixing D16 is part of the crawler service's release.

## How it's built

In releases, after the review's release 1 (public exposure) and release 2 (stop the damage):
1. **The crawler service:** the request queue, a share per workflow, take only what can be done, report each request.
   The worker's fixed order goes. This replaces the review's E19 and F27's run report.
2. **Each workflow moves onto it, one release each, in this order:**
   1. W1 (listings);
   2. W3 (sources);
   3. W2 (upkeep);
   4. W4, W5 and W8 (guides);
   5. W6 (heritage);
   6. W9 (landmarks);
   7. W10 and W11;
   8. W12.

   Each one fixes its own gaps from the review and gets its admin view (§4).

## Open

