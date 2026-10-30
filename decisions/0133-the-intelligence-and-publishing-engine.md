# ADR 0133: The Intelligence & Publishing Engine (the crawler's one rule book)

**Status:** Accepted (2026-10-30) by Jason. **This is the master record for the crawler and everything it feeds**
(listings, landmarks, outdoor places, dossiers, research, guides and stories). Where an older record says otherwise, this
one rules. Older records keep their history; each carries a line at its top pointing here (list at the end).

## Why one record

The crawler's rules had grown across eighteen records (0005, 0024, 0043, 0044, 0045, 0048, 0054, 0061, 0062, 0065,
0066, 0067, 0076, 0083, 0084, 0085, 0086, 0131) and four sections of the platform spec, and some disagreed with each
other and with the code (the review of 2026-10-30, "Crawler scope: what's been decided and what's built"). This record
puts the rules in one place, in the order the work happens.

## The principle: we build our own record of the open web

Traversence's crawler exists so that a small region's information doesn't depend on Google, Yelp or any gatekeeper that
captures the web and rents it back. **We build our own resources for information and research**: our own index of the
open sources (the places' own websites, governments, public registries, archives, museums, historical societies), our own
verified facts with their sources, and our own catalog of the human and natural record. We don't have to compete on day
one, and we use outside services while we grow, but:

- **Every outside lookup is a bridge, not a foundation.** Paid search, Google and AI web search fill gaps only after our own
  sources are asked, and every useful page they turn up becomes a source we read ourselves next time (the source register).
- **We keep the facts and their sources, never the gatekeeper's content** (licences below).
- **We measure independence**: the share of verifications and guide facts settled from our own index and the open sources,
  without a paid lookup. It should rise every quarter; Services & usage reports it.
- **The record is ours to keep:** sources, facts and catalog live in our database, survive any provider leaving, and are
  reused by every page, guide and story.

## Curators, not controllers

Traversence curates the record; it doesn't control what people may know. **No topic is left out because it's sensitive,
uncomfortable or contested**: conflict and violence in a place's history, faith, politics, crime, health and addiction,
land and water disputes, a nation's history. What we manage is **exposure and representation**, so neither we nor the
people who use the site are put at risk and nothing is misrepresented:

- **The topic is told; specific details are protected.** The protected tiers (below) limit only particular details: a
  site's location, a private person's identity, a shelter's address. The topic and its story stay.
- **Fair representation:** credible sources from more than one side; where they disagree, we say so and attribute each
  account ("according to the county history… the Navajo Nation's account…"); disputed facts are marked as disputed; the
  editorial voice describes, it doesn't take sides.
- **Communities speak for themselves:** a nation's or community's own account of its history is presented in its own
  terms and credited, beside the other sources, never replaced by ours.
- **Context and care:** a short note before distressing material (violence, death, abuse); adult topics behind the site's
  18+ settings; help close at hand where a topic touches a need (help lines with mental health and addiction topics).
- **Sources shown:** every published piece shows where its facts came from.
- **Corrections and a right of reply:** anyone can flag a misrepresentation; a community or nation can add its account.
  A takedown happens for law, safety or a private person's privacy, never because a topic is uncomfortable, and even then
  the topic stays with what can be said.
- **The Chameleon Filter delivers; it doesn't suppress.** Sensitive topics are indexed and delivered by context like any
  other; nothing is ranked down or hidden for being sensitive (decisions/0132).

## What the crawler gathers, and what Traversence writes about

**The crawler's scope is research identification, and it is broad.** It finds and indexes every open source with research
value in the areas we cover, whatever the subject: services and help lines, health, education, civic and government, public
safety, businesses and organizations, events, transport and infrastructure, land, water, weather and environment, history,
culture, faith and the arts. It isn't limited to what we publish today: a source that's useful later (a utility's outage page,
a school calendar, a county's records) is indexed now. Breadth is bounded only by the guardrails (public, licensed, polite,
within budget), never by topic.

**The two pillars are Traversence's content focus**: the lens for what Phase 2 extracts first for stories and how Phase 5
writes. They shape the narrative; they don't narrow the gathering.

### The two pillars

- **The Human Journey:** people moving, trading, building and keeping a life on this land across generations: who came,
  why, what they built and with what, how they got through hard years, what they celebrate.
- **The Living Landscape:** the land that shaped them: plateaus and valleys, rivers and water tables, timber and stone,
  weather and seasons, wildlife, the old routes that today's roads follow.

Every data point, archive and listing exists to honor the lived experience of the people and the enduring character of the
land, and every guide shows how the two shaped each other.

## The six phases

Phases 1 and 2 are **always-on automation**: they gather everything within the guardrails, with no content settings.
**Configuration starts at Phase 3**, where research is shaped.

### Phase 1: Source building (identifying the research)

Find and catalog every repository of real information in the areas we cover, on any subject (the scope above), and keep
them in the **source register** (below).

- **Seeds, broad:** the places' own websites; city, county, state, tribal and federal sites; school, health, transit and
  utility sites; chambers, museums, libraries, newspapers and event calendars. **Seeds for the pillars, deep:** the places' own websites and official sites; public registries (NPI, IRS exempt organizations, Recreation.gov,
  GNIS, Census); and for the record of people and land: newspaper archives (Arizona Memory Project, the Library of Congress),
  National Register of Historic Places nominations, BLM land surveys, state library and university special collections, oral
  histories, county historical societies, museums, water and irrigation records. Every useful page any step turns up is
  offered to the register.
- **Order:** our own sources first, then the place's own site and official registries, then the owner, then outside search
  only for what's still missing.
- **Guardrails (fixed, not configured):** public, unauthenticated pages only; robots.txt obeyed, including `Crawl-delay`;
  a polite pace; every network hop checked against private addresses; terms and licence checked and recorded before a
  source is read regularly; never a gated group or private forum.
- **Restricted sources:** cultural-resource reports marked restricted, and anything a nation keeps to itself, are never
  gathered. What counts as restricted follows the jurisdiction register (below).
- **Each source gets a dossier entry:** what it covers (place, years, subject), who made it, how credible it is, how
  often it changes (volatility), and its licence.

### Phase 2: Extraction (facts from every source; the pillars first for stories)

Turn pages and files into facts, cheaply, whatever their subject. For stories, the pillars' passages go first.

- **Code first (about 80%, free):** structured data (schema.org, registries, tables) is parsed by code: the site's
  `ListingExtractor` and the worker's parser (Python: BeautifulSoup, MIT licence).
- **Strip and compress:** boilerplate, menus and ads are stripped (the worker: trafilatura, open licence, confirmed per
  version), and long historical or geographic texts are cut to the passages that matter (keyword windows on people,
  trades, water, materials, land and hardship).
- **AI only for the narrative remainder:** those passages go to the cheapest model (Haiku) with a fixed schema: turning
  points, people and their roles, trades and building materials, water and land use, hardships, local names and lore,
  sites you can walk today. Facts only; never whole pages kept.
- Listings and places are extracted as facts (name, address, phone, hours, kind), never page text.

### Phase 3: Vetting (protecting the record) and configuration

Check what was extracted, and shape the research. **Configuration lives here.**

- **Cross-checks:** against official registries and our own index; a heritage fact needs a primary record or two
  independent sources; overlapping citations count as corroboration, not duplicates.
- **Identity and duplicates:** the match rules (same place, another location, provider, covered, another business,
  maybe), website brand and page levels, other names, the Nexus rules (made automatically when locations share a
  website or main phone; never on a similar name alone; districts and `.gov`/`.us` parents never one brand).
- **Privacy and dignity** (people, below) and the **protected-information tiers** (below) are assigned here.
- **Configuration (System Configuration):** which areas and themes are vetted and cataloged first (coverage: active
  regions, states, priority by search demand and residents affected), how deep research goes, and the thresholds
  table. Phases 1 and 2 aren't configured by area; they gather within their guardrails and budgets.

### Phase 4: The catalog (the tapestry of place and terrain)

Every vetted fact, on any subject, is stored in our own record (services, civic, events, infrastructure as much as
history). For the pillars, vetted facts are stored each with its source and who set it, in one schema where people, land and places meet on the map.

- **Modern places:** listings, landmarks, outdoor places, with provenance.
- **The Human Record:** turning points, builders and families, trades, industries, institutions, traditions.
- **The Living Landscape:** landforms, water, trails and old routes, native materials, climate and seasons, environmental
  milestones.
- **Footprints:** the map point or address where a past event, person or landscape feature can be stood on today.
- **Dossiers and research briefs** (decisions/0131) feed it and are kept with the place.
- **The Topic index (our encyclopedia):** see below.
- **The Seeker:** an admin search across the catalog by theme and place ("water management and stone masonry tied to sites
  in Apache County").

### The Topic index: our encyclopedia of the state of knowledge

The catalog grows into an encyclopedia: **every topic the research touches has a record of what we know about it**, so
anyone (a writer, an owner, a member, staff or Claude) who calls on AI assist to write about a topic writes from our own
researched knowledge, with its sources, not from a model's memory or a fresh web search.

- **A topic** is any subject: a place, a person of public or historical note, an event, a landform or river, a species, an
  industry or trade, a practice or tradition, an institution, a dish, a route, a service. Topics nest (Apache County >
  St. Johns > Pioneer Days) and relate (the Little Colorado River ↔ ranching ↔ irrigation). They build on the concepts of
  decisions/0067 (what words mean) and are what topic tags and topic follows point to (decisions/0132).
- **Each topic's record holds:** its name and other names; a definition; its places and footprints; its facts, each with
  its source and licence (the source register); related topics; and a **state of knowledge**: how well covered it is,
  how fresh, which sources agree or conflict, and the open questions (what we don't know yet).
- **A summary** of each topic, written by the system from its facts (system-owned content), is kept current by Phase 6.
- **AI assist uses it:** called on a topic, it's given the topic's record (summary, facts, sources, protected tiers) and
  writes from it, citing what it used. Where the record is thin, the call says so, works from what there is, and queues
  the topic for research (Phases 1 to 3) so the next call knows more. Every protected tier and people rule applies.
- **Gaps drive the crawler:** a topic with low coverage, stale facts or open questions becomes a research target, ordered
  by how often it's asked for (AI assist calls, searches, guides that need it).
- **Public topic pages** (an encyclopedia people can read) are a later decision; the index serves our own writing first.

### Phase 5: Publishing (walking in their footsteps, with the land)

- **Writing:** Sonnet (or the model set in AI use) writes from the catalog: the place's "Walking in the Footsteps" chapter
  (its story) and the guide sections. **Every historical mention is anchored to a place a visitor can stand today**, and
  people and land are told together.
- **Gates (rule-based, the thresholds table):**
  - **Listings:** publish when **ready** (decisions/0076): real (own site, a trusted registry, or two independent sources;
    the NPI file alone needs one more), complete (name, phone or website, a street number or point in an area we cover),
    appropriate (a category fits, no spam or adult content), unique (no unresolved duplicate). **No confidence threshold**:
    the score only orders work and shows how sure we are. Everything after publishing is handled by rule in Phase 6.
  - **Guides:** publish themselves at score 70 or more; **Find it** results become listings at 80 or more **and** pass the
    same readiness checks as any listing.
  - **A person** only for exceptions: a pipeline exhausted, a protected item, a conflict with a person's own content.
- **The protected-information standard** and the **people rules** (below) are enforced here, and the check before saving
  removes anything that breaks them, plus any talk of our research ("according to our sources", dead websites).
- **The Chameleon Filter follows the same process.** Its tags, highlighted names, preview cards, map pins and feed delivery
  (decisions/0132) obey the protected tiers: no tag, card or pin that locates a story-only or restricted item; tags never
  infer anything about a person; delivery by context follows the same gates as publishing.
- **Freshness is shown:** every guide and listing shows when it was created and when it was last refreshed.
- **Bridges to the real place:** GO Codes at markers, trailheads, theaters and storefronts (later).

### Phase 6: Content maintenance (review, renew, refresh)

A loop back through Phases 1 to 3 that keeps what's published true.

- **Clocks:** listings every 30 days; guides every 30 days; dossiers every 6 months; research briefs every year; events
  every month.
- **Events that start a check:** a source we read changes; a listing closes or moves; a name in a guide goes closed; a
  visitor reports something; a date in the text passes; search demand spikes; the season turns.
- **Opening a guide** raises its place in the queue; it never runs a check on the spot.
- **Topics:** a topic's summary and state of knowledge are refreshed when its facts change; topics asked for often are
  checked first.
- **The background reader:** free checks first (links alive, linked names still open, dates not past, facts matching the
  catalog), the cheapest model only for what they flag; each piece gets a freshness score.
- **Outcomes:** **Refresh** (crawler-owned facts updated automatically); **Renew** (content the system wrote is re-researched,
  rewritten and re-scored under the same gates); **Review** (a person, only when their own content or a protected item is
  involved).
- **Closed and moved:** checked on live listings at each refresh, not only from visitors and Google; a closure from Google
  alone is a Review item, never automatic.

## Rules across every phase

### Provenance and ownership

Every fact and field records its source and who set it (crawler, staff, owner, member). The crawler writes only to its own
fields, and only empty or crawler-owned ones; a field a person entered or locked is never overwritten; a claimed listing is
only offered a change (evidence wins, unless claimed: decisions/0062).

### AI acts on a person's words only when called

AI writes freely only content the system owns (guides and stories it compiled, marked as compiled). On a person's own words
it acts **only when that person calls it** (Write, Rewrite, Assist): the result comes back as a suggestion they accept or
reject, never applied silently, and the call is logged. A member's words go to AI only with their AI & Learning consent
(decisions/0051). Maintenance may flag a person's content as possibly out of date; it never edits it.

### People

- **Public figures** (officials, authors, artists, founders, business owners in their business role, anyone known for
  public acts): named, with their public deeds.
- **Historical figures** (documented in published history: books, National Register filings, historical societies, period
  newspapers): named and their story told, with respect; living descendants' private details are never published.
- **Private individuals** (anyone appearing only incidentally: census rows, deeds, court and cemetery records, notices,
  photos): not named, no profile, unless the family consents or a published history already tells their story. Anyone who
  may be living (born within about 100 years) is private unless a public figure. Living people's addresses, health and legal
  matters, and minors, are never published.
- No identities are built for people who merely appear in data (decisions/0062).

### The protected-information standard

"Tell the story, never the way in." The standard protects details, never topics (curators, not controllers). Every
catalog fact carries a tier set in Phase 3 and enforced in Phase 5:

| Tier | Published | Applies to |
| --- | --- | --- |
| **Public** | freely | public places, businesses, public history |
| **Story only** | the name and story; never location, pin, coordinates, directions or access, except an access point the managing authority offers publicly (a state park's rock-art tour) | sacred, burial and archaeological sites a public source already presents; private property (only public viewpoints or tours); sensitive wildlife (nests, dens, rare plants) |
| **Restricted** | never; kept for our records only | what a nation restricts; site locations protected by law in that place's jurisdiction (the jurisdiction register); domestic-violence shelters' addresses; private individuals' details |

- A sacred, burial or protected place is **held** by default; it moves to Story only when a public source presents it to
  visitors and our crawler confirms that, and never when a nation has closed or restricted it (decisions/0066).
- Story-only places carry a fixed courtesy line (look, don't touch, follow the nation's rules) and no pin; a pin on a
  nation's land moves to a public place.
- **Limiting exposure:** every published fact keeps its source; anyone can ask for a takedown and the item is hidden
  within 24 hours while we look; a quarterly sample audit of published guides; an attorney signs off on this standard once,
  and on each jurisdiction record as its region opens.

## The jurisdiction register (laws by place)

Traversence expands state by state, so **the law that applies is the law of the place**, not one state's. Each place's
rules come from a jurisdiction record, layered:

1. **Federal baseline, everywhere in the U.S.:** archaeological site locations kept confidential (ARPA; NHPA §304, also
   protecting the locations of historic properties when disclosure risks harm); Native American graves and cultural items
   (NAGPRA); federal privacy and children's rules.
2. **Each state and territory** (Puerto Rico, Guam, the U.S. Virgin Islands, the Northern Mariana Islands, American Samoa,
   and D.C.): its antiquities and burial-site statutes, unmarked-grave rules, state privacy and data laws, rules on
   scraping and public records, and its state historic preservation office's confidentiality practice.
3. **Each tribal nation** where it publishes them: its cultural-resource and sacred-site codes, visitor rules, photography
   and research permits. A nation's rule outranks ours on its land, and a nation that hasn't published one is treated as
   restricting what it hasn't made public.
4. **Other countries** as the continental plan reaches them (Canada's provinces and First Nations, Mexico's states and
   Indigenous communities): their heritage and privacy laws, added the same way.

Each record holds: the jurisdiction, its laws (name, citation, what it protects, what it requires of us), its effect on our
tiers and sources, the reviewer and date. **A region opens only after its records are reviewed** (an attorney signs off on
each new state's or territory's record once); **where a record is missing or unclear, the strictest known rule applies**
(Restricted until reviewed). The protected tiers, the source register's uses and the takedown path all read from it.

## The three registers

### The source register

Every source is a record; **its licence decides what we may do with its facts**, and every fact carries its source, so the
licence travels with the fact.

| Field | What it holds |
| --- | --- |
| Kind | website, official site, registry, bulk file, API, archive, museum, historical society |
| Licence | public domain, open (ODbL, CC BY-SA, CC BY, CC0), terms-restricted (Google, commercial directories), unknown |
| Use | store facts · keep a pointer only · cite only · evidence only (may confirm, never supply) · show with credit |
| Credit line | the attribution to show, when the licence asks |
| Status | read regularly · cite only · ignore · suggested |
| Rate limit, cap, refresh cadence | per source |
| Terms checked | the date and the page read |

Standing uses: OpenStreetMap (ODbL): existence, a map link, the website as a pointer, never its phone, hours or address;
data outside our regions kept in its own tables. Wikipedia (CC BY-SA): facts only, never copied. Wikidata: CC0 facts.
Commons photos: CC0, public domain, CC BY or CC BY-SA, with credit. Google: the place ID, check date and match only;
"Google verifies and links; it doesn't fill in listings". Yelp, MapQuest, BBB, Yellow Pages, Healthgrades: cite only.
Google, Yelp, Facebook and Instagram results: evidence only. The IRS exempt-organizations file: confirms, never shown,
never creates a listing. A bulk dataset is never an automatic publish.

### Thresholds and gates

One table, in System Configuration, each value named by what it gates. Today's values:

| Gate | Value |
| --- | --- |
| Listing publishes | readiness rules (no score) |
| Guide publishes itself | score 70 |
| Find it adds a listing | score 80 + readiness |
| Category auto-fill | above 80%; below 50% to a person |
| Situation published / "may help" | 0.90 / 0.75 |
| Heritage fact cataloged | a primary record or two independent sources |

### Services & usage

One page for every paid or limited service (web search, Google Places, Claude, and any added later): its cap, use today and
this month, the month's projected cost, **alerts at 80% and 100%**, a **monthly report**, and an automatic stop at the cap.
Today: web search 1,000 a month with a stop at 90% and a daily share; Google Places 3,000 a month, stop at 90%, a $10 alert;
Claude under `AI_MONTHLY_CAP_USD` and each feature's own budget. The page also reports **independence** (the principle).

## Where the crawler works

Bulk loads run state by state as regions open: Arizona and New Mexico now; Utah and Colorado as their hubs open; nothing
loaded "just in case" (decisions/0048). Crawl targets can come from searches anywhere (decisions/0066). Research is shaped
in Phase 3's coverage setting.

## What changes in the code (to build)

1. The dead 85 setting is removed; the score orders work only. **Built 2026-10-30.**
2. Find it's listings pass the readiness checks. **Built 2026-10-30:** a find approved by its score is staged with the
   crawler's listings (`GuideLinks::stageFind` → `IntakeStager::stage`) and published only through `AutoImport`; the
   old direct add is gone. A find that fails a check waits there with the reason, and its guide name links once listed.
3. Waiting listings are re-matched and published by a scheduled job, not only when Review opens. **Built 2026-10-30:**
   `listings_tick` (every 15 minutes, `Scheduler::AUTO`) runs `AutoImport::advance`.
4. Confidential listings are never sent to outside services in verification. **Built 2026-10-30:** `Verify` checks them
   in our own records only (no DeepLook, never leased to the worker); Find it never looks up a confidential name.
5. Refresh marks a listing when the work is done; live listings get a closed/moved check; clocks per this record.
   **Built 2026-10-30 (listings):** a refresh counts when the worker reports (a lease holds it two hours); every 30
   days. A website that fails twice, 3+ days apart, comes off the listing when the crawler gave it (kept as
   `website_dead`), else goes to Review; robots.txt blocks and spent budgets never count. A business's own site saying
   it closed for good marks it Closed (decisions/0072) when unclaimed and not sharing its site (a chain); otherwise
   Review. **Built 2026-10-30 (guides):** `Guides::maintainTick` (in the guide job) checks each live guide every 30
   days, opened-since-last-check first: the dossier renewed when due (6 months), dates passed, linked names closed,
   research a year old. A finding in Claude's own sections renews the guide (rewritten, re-scored); one in a person's
   sections is flagged in the editor. Events are kept monthly by the date check, not a monthly rewrite. Still to
   build: "moved" from a business's own site.
6. robots.txt `Crawl-delay` honoured; every hop checked against private addresses. **Built 2026-10-30** in the worker
   (`public_host`, `SafeRedirect`; a site asking for more than 30 s between pages is left alone).
7. The source register gains licence, use, credit, cadence and terms-checked fields; facts keep their source.
   **Built 2026-10-30:** `source_rules` (its own table in main) holds a person's rules per website; `Sources::STANDING`
   the engine's standing sources (OSM, Wikipedia, Wikidata, Commons, Google, Tavily, Claude, IRS, NPI, RIDB, Census,
   USGS, NWS, AZ 511, Library of Congress); `Sources::rules()` / `mayStore()`. Terms-restricted sources can't be set to
   store or show. Tier 2 learning reads only sources whose rules let us keep what they say. Still to build: each stored
   fact carrying its source's rule.
8. Services & usage page, alerts and monthly report; independence measured. **Built 2026-10-30:** System Configuration
   → Services & usage (`Services`), the `services_tick` job (hourly): emails at 80% and 100% once a month each (retried
   if no email went), the report in the first days of the month. Independence: the Find it share our crawler answered,
   and paid lookups per new crawler listing.
9. The heritage pipeline: worker job, Haiku extraction, catalog tables, the Seeker, the Footsteps chapter.
   **Built 2026-10-30:** the worker's `heritage()` searches the Library of Congress per guide place (catalog records and
   links only) into `heritage_sources`; `heritage_tick` (hourly) runs `Heritage::extractTick`: keyword windows from the
   LOC records, the dossier and the research brief, read by `heritage_extract` (Haiku) into `heritage_facts`. Vetting: a
   LOC primary record or two independent groups (the dossier counts as one; the research brief never counts). Tiers by
   `Jurisdictions::tier`; protected facts are never pinned. Insights → The Seeker. Guides gain "Walking in the
   footsteps" (`DRAFT_V` 4), written from vetted public and story-only facts; the monthly check renews a guide when the
   catalog gains vetted facts.
10. The jurisdiction register (federal baseline, Arizona and New Mexico first, then each state, territory and nation as it
    opens), read by the protected tiers and the source register.
    **Built 2026-10-30:** `jurisdictions` / `jurisdiction_laws`, System Configuration → Jurisdictions. Seeded as drafts:
    the federal baseline (ARPA, NHPA §304, NAGPRA, COPPA), Arizona (A.R.S. 41-841 to 847, 41-865, 39-125) and New
    Mexico (NMSA 18-6-1 to 17, 18-6-11.2); the citations are as understood and are confirmed in the attorney review.
    `Jurisdictions::forPlace` layers federal, state or territory and nation; `tier()` gives a sensitive item story-only
    once every layer is reviewed, restricted until then, and always restricted on a nation's land. A state's or
    territory's review needs the attorney's name and date; any change sends a record back to draft. Still to build:
    holding a region closed until its record is reviewed, and the source register reading the effects.
11. Protected tiers in the catalog, enforced in publishing and in the Chameleon Filter; created and refreshed dates shown.
    **Built 2026-10-30:** tiers on every heritage fact (public, story, restricted) set by `Jurisdictions::tier`; a guide's
    check before saving (`Guides::scrub`) keeps a sensitive sentence only when a public source presents it, and then
    cuts any way in (`Guides::WAY_IN`: distances, directions, trailheads, parking, roads, coordinates) unless it names an
    access point the managing authority offers (`PUBLIC_ACCESS`: ranger-led and guided tours, visitor centers, state and
    national parks, museums); story-only places carry the courtesy line. The Seeker's "Tell a held place's story" turns a
    held landmark (not on a nation's land, with its public source, where the law of the place is reviewed, with no
    directions) into a story-only catalog fact; the landmark stays held, no page and no pin. Tags and preview cards
    already never link a held landmark. Guides show first published and checked dates; listings, listed since and
    checked. Still to build: the 24-hour takedown path for any published item and the quarterly sample audit.
12. The Topic index: topic records with facts, sources and state of knowledge; system summaries; AI assist grounded in
    it (thin topics queued for research); topic tags and follows pointing to it.
    **Built 2026-10-30:** `topics`, `topic_names`, `topic_facts`, `topic_places`, `topic_relations` (`Topics`). Heritage
    extraction names each fact's topics; each guide place is a topic; shared facts relate topics. `Topics::record`: names,
    places, vetted facts (restricted never), sources, related topics and the state of knowledge (coverage, freshness, what
    isn't confirmed). The heritage job keeps summaries current from vetted facts (`topic_summary`, Haiku) and researches the
    most asked-for thin topic (`topic_research`, kept as unvetted research). `Topics::assist` (`topic_assist`) writes from
    the record only, cites facts, and queues a thin topic. Insights → Topic index. Topics with three or more public vetted
    facts are taggable (`Tags::topics`, keys `ti-<slug>`). Still to build: AI Assist for members (the /ai/ page), and search
    reading the index.
13. Content maintenance: clocks, event triggers, queue priority on view, the background reader.

## Records this replaces (for the crawler)

Each keeps its history and carries a pointer here: 0005 (crawler and guide gates), 0024 §3, 0043 (crawler parts), 0044,
0045, 0048 (sources and loads), 0054 §6, 0061 (thresholds), 0062 (crawler and identity-job rules), 0065, 0066 (crawl
targets; the tribal standard is restated here), 0067, 0076, 0083 to 0086 (crawler parts), 0131 (dossier, research, Find
it, publishing). In the platform spec, §11, §15, §21 and §30 point here: the "unbounded" crawl, the old `api/ingest.php`
path, and Google Business Profile as a source are withdrawn.
