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

- **Clocks:** listings every 30 days (replaced by the band cadence below: monthly to quarterly, never over 90 days); guides every 30 days; dossiers every 6 months; research briefs every year; events
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

One table, in System Configuration → Thresholds & gates (`Thresholds`), each value named by what it gates. Numbers a
director may tune there (kept with who changed them and when, and a Reset to the default); the AI switches are the same
values AI use holds; standards are shown there but change only by a decision. Built 2026-10-30. Today's values:

| Gate | Value | |
| --- | --- | --- |
| Listing publishes | readiness rules (no score) | standard |
| Guide publishes itself | score 70 | tuned |
| Find it adds a listing | score 80 + readiness | tuned |
| Category set without a person / a person picks | 80% / under 50% (decisions/0024, `CategoryClassifier`) | tuned; "Other" never set by itself |
| Search: published / "may also help" | 0.80 / 0.60 relevance (decisions/0093; the 0.90 / 0.75 of decisions/0061 was replaced) | tuned |
| Heritage fact cataloged | a primary record or two independent sources, one of which may supply it | standard |
| Free AI Assist asks a day (paid plans) | 3 | tuned |
| A member's AI cost a month | at most 0.5 of what their plans pay | tuned |
| Takedown hidden within | 24 hours (at once for a protected reason) | standard |
| Takedown requests a person a day | 5 | tuned |
| Quarterly audit sample | 0.1 of live guides (5 to 40) | tuned |

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
   sections is flagged in the editor. Events are kept monthly by the date check, not a monthly rewrite.
   **Built 2026-10-30 (moved):** the worker also sends the lines where a business's own site says it moved
   (`MOVED_HINT_RE`); `Refresh::movedLine` keeps one about the business itself (not a branch, not for a season, not its
   hours or its website) and the new street when it gives one. It's never changed on its own: Review gets a fix with the
   street filled in, once per sentence (`site_moved`), and not when the listing already has that address.
6. robots.txt `Crawl-delay` honoured; every hop checked against private addresses. **Built 2026-10-30** in the worker
   (`public_host`, `SafeRedirect`; a site asking for more than 30 s between pages is left alone).
7. The source register gains licence, use, credit, cadence and terms-checked fields; facts keep their source.
   **Built 2026-10-30:** `source_rules` (its own table in main) holds a person's rules per website; `Sources::STANDING`
   the engine's standing sources (OSM, Wikipedia, Wikidata, Commons, Google, Tavily, Claude, IRS, NPI, RIDB, Census,
   USGS, NWS, AZ 511, Library of Congress); `Sources::rules()` / `mayStore()`. Terms-restricted sources can't be set to
   store or show. Tier 2 learning reads only sources whose rules let us keep what they say. **Built 2026-10-30 (facts carry
   the rule):** each heritage fact's sources are stamped with their rule, licence and credit line when it's read
   (`Heritage::stamp`), and again when a person changes that site's rules (`Sources::setRules` → `Heritage::restamp`);
   older facts are stamped by the heritage job. A fact is cataloged only when a source whose rule lets us keep and show
   it backs it (`supplies`): cite-only, evidence-only and pointer-only sources may confirm, never supply. Credit lines
   show under the guide's "Walking in the footsteps", on "From our guides" results and on AI Assist's facts.
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
    territory's review needs the attorney's name and date; any change sends a record back to draft.
    **Built 2026-10-30 (regions):** `Jurisdictions::regionOpen`: a state or territory opens when its record and the federal
    baseline are both reviewed; Arizona and New Mexico (`OPENED`) were open before the register and stay open while theirs
    are reviewed; a place with no state yet is in a region we opened. In a closed region a guide doesn't go live, by itself
    or by a person (it waits as a draft and says why), and the heritage job doesn't gather its history (asked again in a
    week). The source register reads the effects: where a record lists a law limiting reading or copying websites
    (`scraping`, `Jurisdictions::limitsReading`), the dossier keeps an official website as a pointer only unless a person
    set that site's rules to keep its facts (`Dossier::mayRead`). The register shows each state open or closed.
11. Protected tiers in the catalog, enforced in publishing and in the Chameleon Filter; created and refreshed dates shown.
    **Built 2026-10-30:** tiers on every heritage fact (public, story, restricted) set by `Jurisdictions::tier`; a guide's
    check before saving (`Guides::scrub`) keeps a sensitive sentence only when a public source presents it, and then
    cuts any way in (`Guides::WAY_IN`: distances, directions, trailheads, parking, roads, coordinates) unless it names an
    access point the managing authority offers (`PUBLIC_ACCESS`: ranger-led and guided tours, visitor centers, state and
    national parks, museums); story-only places carry the courtesy line. The Seeker's "Tell a held place's story" turns a
    held landmark (not on a nation's land, with its public source, where the law of the place is reviewed, with no
    directions) into a story-only catalog fact; the landmark stays held, no page and no pin. Tags and preview cards
    already never link a held landmark. Guides show first published and checked dates; listings, listed since and
    checked. **Built 2026-10-30 (limiting exposure):** the takedown path (`Takedowns`, `/takedown.php`, "Ask us to take
    this down" under each written guide section, on landmark pages and under a topic in AI Assist): anyone, signed in or
    not (a captcha for guests, 5 a day each, counted by a one-way daily hash). A protected reason (a way in to a sacred,
    burial or archaeological place; a nation asking; someone's private information; a safety risk) hides it at once;
    anything else when 24 hours pass without a decision (`takedowns_tick`, every 10 minutes). A fact is held (`status
    held`; re-extraction keeps it held) and the guide's footsteps chapter stays out until the guide is written again; a
    landmark is hidden; a guide's section or a topic is left out. Social → Takedowns: Restore (brought back, every request
    on the item closed) or Keep it down (a fact becomes restricted); the asker hears by email if they left one. Members'
    posts, journeys and comments keep Report; listings their owners and Suggest a change. The quarterly sample audit
    (`GuideAudits`, Insights → Quarterly audit): the first run of each quarter draws a tenth of the live guides (5 to 40)
    with a pre-check (a way in near a protected-sounding name, names to read, freshness, open takedowns); a person marks
    each passed, fixed or taken down.
12. The Topic index: topic records with facts, sources and state of knowledge; system summaries; AI assist grounded in
    it (thin topics queued for research); topic tags and follows pointing to it.
    **Built 2026-10-30:** `topics`, `topic_names`, `topic_facts`, `topic_places`, `topic_relations` (`Topics`). Heritage
    extraction names each fact's topics; each guide place is a topic; shared facts relate topics. `Topics::record`: names,
    places, vetted facts (restricted never), sources, related topics and the state of knowledge (coverage, freshness, what
    isn't confirmed). The heritage job keeps summaries current from vetted facts (`topic_summary`, Haiku) and researches the
    most asked-for thin topic (`topic_research`, kept as unvetted research). `Topics::assist` (`topic_assist`) writes from
    the record only, cites facts, and queues a thin topic. Insights → Topic index. Topics with three or more public vetted
    facts are taggable (`Tags::topics`, keys `ti-<slug>`). AI Assist for members and search reading the index: built
    2026-10-30 (below).
13. Content maintenance: clocks, event triggers, queue priority on view, the background reader.
    **Built 2026-10-30:** `Guides::due` brings a guide's check forward on an event: a place it names closes
    (`SuggestionActions::markClosed`), a signed-in visitor suggests a change, new Library of Congress records arrive, the
    season turns (`guides_season`), or views spike (3× the prior four weeks' average and 20 or more in a week). The check
    runs on the guide job's next turn, never on the spot: events first, then opened since the last check, then oldest. The
    background reader adds the link check (`deadLinks`: no answer, an error or a parked page) and a closed check on the
    listings a guide names; each guide gets a freshness score (`Guides::freshness`), shown in the editor with what brought
    the check forward. **Built 2026-10-30 (the cheapest model first):** when everything the free checks find is a small
    fix in Claude's own sections (a passed date, a business that closed, a dead link) and there's nothing new to write
    from, Haiku proposes exact edits (`guide_triage`, `Guides::triage`); each is applied only when its text appears once,
    it adds no link and nothing the scrub would cut, and the finding is gone afterwards. What it doesn't fix still renews
    the guide in full. A closed name counts only while the text still names it.

**Search answers from our own knowledge (2026-10-30).** `Topics::answer`, called by `UniversalSearch::search` before
the place card: when the words name a topic in the index (as typed, then as search kept them, then a topic name that
holds them), or ask about a place's past (`Topics::ASKS_KNOWLEDGE`: history, founded, heritage, culture, legends...),
the top card is the topic's summary and up to three vetted facts, with related topics and a link to the guide's "Walking
in the footsteps". Restricted facts never show; a topic whose facts are all held back gives no answer; related topics
need a vetted fact we may show. A thin topic asked about is queued for research. Everyday searches (a business, a place,
a category) are unchanged.

**Search, step two, and AI Assist for members (2026-10-30).** As you type, topics with a vetted fact we may show are
offered after the tools the words ask for (`Topics::suggest`, kind `knowledge`, "From our guides"). Results gain a "From
our guides" group (`Heritage::searchPublic`): vetted public and story-only facts whose words match, story-only without
its place, linking to the guide's "Walking in the footsteps", drawn under the answer card. Once a day the heritage job
reads the Missed searches report (`Topics::fromMissedSearches`: zero, unread and left-with-nothing outcomes, counts
only) and credits the topics people looked for, queuing thin ones for research. `/ai/` is AI Assist for members: signed
in, with AI Assist & Learning on (their words go to Claude only then), 10 asks a day; it shows the topic's summary,
vetted facts and state of knowledge, and writes from the record only (`Topics::assist`), saying when a topic is thin.
AI Assist allowance (built, 2026-10-30; Jason: no one causes us an AI cost that a revenue stream doesn't cover): AI Assist is for members with a paid plan, their own (Personal Plus, Pro) or one on a business they own (`AiCredits`). Each gets 3 free asks a day (`AiCredits::FREE_PER_DAY`), then each ask uses one AI Assist credit from a plan they pay for: their own first, then their businesses' (the member picks when there's more than one), plan credits plus +50 packs. A trial has none until its first payment; a plan whose payment failed has none. Backstop: a member's asks never cost more in a month than half of what their plans pay a month (`AiCredits::COVER`). Each ask is booked in `ai_jobs.charged_to` (`free`, `user:<id>`, `listing:<id>`), so a business's owners share its credits; no new table. The Chameleon Filter reads a member's journey or story with Claude only when a paid plan of theirs covers it; otherwise it matches names from our own directory. Our own spending (guides, heritage, topic summaries and research, Find it) stays under the site's monthly AI cap and each feature's budget, as an investment in the catalog.

AI Assist asks (built, 2026-10-30; Jason: check live costs; cost and save asks; track where asks come from, for routing; members may ask about anything; free asks answer from what we have, queue the crawler and follow up). A member may ask about any subject, from `/ai/` (on its own or under a topic) or from the **✦ AI Assist** button in the journey and experience editors (`/api/ai-assist.php`, `js/tv-ai-assist.js`; Insert puts the text in the story). Each ask is kept in `ai_asks` (`AiAssist`): the member's words and answer (theirs alone, shown under Your asks with Copy, deleted with the account), its **source** (ai_page, journey, experience, topic, guide, search) for routing, what it cost (Claude's charge), the model and what paid (`free`, `user:<id>`, `listing:<id>`). The answer is written only from our record (the topic's vetted facts, cataloged heritage facts, the place guide's shown sections). When the record doesn't cover it, the answer says so and the subject goes to the research queue (`Topics::ensure`, queued); `AiAssist::followupTick` (heritage job) writes the answer again once research or new cataloged facts arrive, emails the member, and charges the follow-up to us (`followup`), not to their credits; an ask still waiting after 45 days closes. Insights → **AI Assist** shows the month's asks, members, cost, cost per ask against the 1 to 2 cents planned, free asks' cost, follow-ups waiting and sent, by source, and the subjects we didn't know yet: counts and money only, never what anyone asked. The free asks a day are a tuned value in Thresholds & gates (`ai_free_asks`).

**The AI Content Crawler's templates (Jason, 2026-10-30; all six steps built).** Each kind of content the crawler and Claude make is a template people create and shape before anything is spent: a Place guide, Live events, and more without a build. A template says what it applies to and when it runs, what it gathers (free sources first, paid web search only for gaps), what it writes (sections of prose, or records with fields, each with its instruction; the purpose, order of trust and voice; the AI feature that writes it), how it's checked and published, its shelf life, where it shows and its cost. The exposure standard is shared by every template and locked (`ContentTemplates::LOCKED`): shown, changed only by a decision. Protected places follow three levels (Jason, 2026-10-30, curators not controllers): **public** (the managing authority invites visitors: named, told, directions like any place, a town's pioneer cemetery included); **known publicly, not open to visit** (named and told as public sources present it, never the way in, with "visit only with the authorization of" the nation or the land's managers and who grants it when public); **held** (we know it exists, no public source names it: never named or located; the guide may say the area holds sacred places that aren't for visiting without permission, and whose). Step 1: `content_templates` and `content_template_versions` (every save a version: who, when, what changed; restore saves a new version), `ContentTemplates`, and the Place guide template set up from the code by the guide job; nothing reads it yet. Step 2: the guide writer and the guide job read the Place guide template (`Guides::template()`, the saved template over the code's, part by part; the code's when there's none): the purpose, order of trust, voice and formatting; each section's heading, instruction, pass and whether it's on; the length a pass may run and the searches allowed beside a research brief; which tiers write themselves, how busy a place must be and how many a run; how often a live guide is checked. A template being shaped or switched off writes only what someone asks for. The guide editor shows each section's instruction from the template. Checked: the prompt Claude receives is byte-for-byte the same as before for a new guide, a rewrite and a partly written one. Sections the guide page doesn't draw yet aren't written until step 4. Step 3, the three levels: `Landmarks::isProtected()` replaces the single word test everywhere it decides (the writer's scrub, Find it, landmark loads, heritage tiers, the audit's pre-check): a cemetery is civic unless it's on a nation's land or names its peoples (`Landmarks::loadPeoples()` reads them from the nations register, with Indian, native, tribal, ancestral), while ruins, rock art, kivas, shrines, burial grounds and the like stay protected whatever surrounds them. The scrub keeps a public-access sentence with its directions; keeps a known-not-open place's story, cuts its way in, and adds the authorization line (`Guides::AUTH_LINE`) when the section doesn't already say whose permission it needs; and keeps a general acknowledgement of held places (no name, no way in, says permission is needed: `Guides::acknowledges()`). The writer's rule says the three levels. Sentences no longer split after St., Mt. or Ft. (St. Johns was being cut in two). Migration `2027-01-28_landmarks_town_cemeteries.sql` shows the town cemeteries the site had held only for the word, leaving any a person reviewed, any on a nation's land, and any naming a nation's peoples or a protected kind. Step 3, free sources first: a place picked for its first guide starts `gathering` (`Guides::gather()`): the dossier at once, now with the National Register of Historic Places listings near it from Wikidata (CC0; never coordinates, and a site whose location the Register restricts has none there), and the worker's Library of Congress reading, led by gathering places, now also searching Chronicling America's newspaper pages in the place's state and sending their text (kind `news`, up to 3,000 characters each). `Guides::gatherTick()` waits up to 24 hours for that reading, then writes the research brief told what the public records hold (`Heritage::held()`), searching only for the gaps and with half the searches when the free material is rich (8 or more records, or a long dossier), reads the passages into heritage facts (`Heritage::extractPlace()`), and queues the guide; after 48 hours it's queued with whatever came in. A guide someone asks for is still written at once. Step 4: System Configuration → **Content templates** (`/admin/config/content`, each template at `/admin/config/content/<id>` with the command strip; `admin/content-template.php`). A director in engineering, operations or content shapes a template in sections: what it is (name, status, what it's for), when it runs (which tiers write themselves, listings a place needs, guides a run), what it gathers (the sources in order, free first; searches beside a brief), what it writes (purpose, order of trust, voice, formatting, the longest a pass may run, and each section's instruction, pass, order and whether Claude writes it), how it's checked, how long it lasts (days between checks), and the exposure standard (locked). **Preview** builds exactly what Claude would be sent for a place under the template as it is in the form, saved or not, and estimates the cost per pass (tokens in from its length, out from the sections asked, each search about 1¢, the upper figure if every search is used, a brief not yet held added), spending nothing: the writer was split into one core with three modes (`Guides::compose`: write, as before and byte-for-byte the same; preview, which reads a held brief only, `Dossier::research(..., $spend = false)`; try). **Try it** writes the place under the form's template, runs the same scrub and checks, saves nothing to the guide or the template, and keeps what came back (`content_template_tries`, migration `2027-01-29_content_template_tries.sql`) with its cost. Every save is a version with who and what changed; **Go back to it** saves an earlier version as the next. **Make a copy** shapes a variant without touching what runs; **Use it as the running template** saves the copy's spec as the running one's next version; a copy can be removed, a running template only switched off. Step 5, **Live events** (`LiveEvents`, migration `2027-01-30_live_events.sql`: `live_events`, `live_event_places`): the first template that makes records. Set up as being shaped, so it spends nothing until a director sets it running in Content templates (where its page shows the fields, how often and how far ahead it looks, the searches allowed, and Preview and Try it). While it runs, the guide job looks for one place's events a run (community and city guides that are live, each every 7 days, 120 days ahead): the official site's events and calendar pages first (`Dossier::eventPages`, free, under each state's reading rules), Claude for the list and web search only for what those pages don't list (`live_events` in AI use: Sonnet, 3 searches). Each event is checked (`LiveEvents::check`): a date ahead inside the window, the page that announces it, a venue matched to our listings where we have it; a name or venue that may mark a protected place is held for a person (Let's Explore → **Live events**, where any event can be shown or taken down; one taken down stays down). Events show in the guide's Events section under "Coming up" (a community's guide includes its towns', shown once) and on the venue's listing under "Coming up here", until the day after they end. Step 6: **what members ask feeds the guides**: the guide writer's ground truth now carries "what members asked about here" (AI Assist subjects asked in the place, its community's towns included, the last six months, with their counts; never a member's words or who), after visitors' intent; it's a source in the Place guide template ("What members asked here", added to the saved template as a version of its own). **Fact tips for a person's sections** (curator, not controller; never changing their words): `Guides::personSections` (sections a person wrote or changed since Claude did); `Guides::factTips`, free, compares their words with the facts the dossier holds (Wikidata's founding year, elevation, population) and shows a Tip under the section with the source; **Check my facts** (`Guides::checkFacts`, on request only, `fact_check` in AI use: Haiku, no searches) has Claude read their sections against the dossier, the held research brief and the heritage catalog and list what conflicts or isn't supported, each with the words in question (kept only when those words are really there), shown under the section while it reads as it did when checked. Nothing is ever rewritten.

## The one record: observations, listing confidence and opening a hub (Jason, 2026-10-31)

Listings and content are two readers of **one record of what we've seen**. Every check, free or paid, by the crawler,
Google, OpenStreetMap, a registry, Claude, a visitor, an owner or our team, writes what it saw once, and every part of
the system reads that record before it spends anything. Each paid answer is bought once and serves listings, guides,
events, topics and AI Assist alike. Over time the record grows into our own research source: deeper on the places we
cover than any general index, kept current by the people who live there, and cheaper to run with every hub we open.
That's our edge over Google: not more crawling, but knowing our places better, provably, and showing how sure we are.

### Why: what was being lost

- Pages found by paid web searches (Verify, Find it, guide research, Live events) stay with the feature that paid for
  them, often as a bare link, so another feature can pay to find the same page again.
- A business's own website is read monthly for its listing only; its events, history and offerings aren't passed on.
- An event announced at a venue is good evidence the venue is open, and isn't recorded as such.
- Guide research names businesses with details (when a store opened) that feed only the guide's text.
- Each feature has its own "check before paying" rule; there's no single one.
- A listing's evidence is weighed once, at intake. After it's published nothing adds up what we know, nothing ages it,
  and visitors can't tell a crawled listing from one its owner verified last week. So every problem lands on a person,
  and most of Google's answers go unused (decisions/0085 kept only the place ID and acted only on "permanently closed").

### The observations store is the identity record (Jason, 2026-11-01)

There is no new store. The **identity record** (decisions/0062: `identity_facts` and `identity_fact_sources`) is the one
primary record of every business, place and resource line, from the first sighting. A data review on 2026-11-01 found it
was a cache instead: rebuilt from the listing's own fields (`entity_metadata`), with 99.5% of its sources wiped on every
rebuild, and 17 places in the code that changed a listing without telling it. That is what this replaces.

- **Three layers, one direction.** `entities` is the business (its id, owner, status, category). The identity is the
  evidence: every value any source gave for each fact, with its source, date, and whether sources agree. The listing's
  fields (`entity_metadata`) are the shown copy of the identity's current values, kept for fast pages, never a source.
- **One line per thing seen:** the fact (name, other name, address, town, state, ZIP, point, phone, website, email,
  hours, about, offers, kind, social), its value, and each source: its kind and site, the page, who added it, a note,
  when it was checked. The status (confirmed, single, conflict, not right, taken off the listing) is worked out from
  the sources. What's kept follows the source register and *Owning our data* below.
- **Every write is recorded as it happens** (`Identity::listingWrote`, `Identity::syncFromListing`): the crawler and
  its refresh, the Census and site map points, staff in the editor, the owner, a visitor's accepted fix, add a business,
  address moves, aliases, rebuilds, approvals and merges. Each becomes a source of the new value that lasts through
  rebuilds. What the listing no longer says is kept as **taken off the listing** (history: never shown, never written
  back by the evidence) until the listing is given that value again. So a dead website the refresh took off stays off.
- **Merges join the evidence** (`Identity::mergeInto`): the hidden listing's sources join the kept one's, noted
  "Merged from #id", and Undo takes exactly those back off.
- **`crawler_raw_index` stays the raw archive**, the intake queue and the dedupe fingerprint; its facts are written
  into the identity once, when they're found, not re-read on every rebuild. `source_facts` stays the reference index
  (re-read per site); a match becomes an identity source when it's found. `reference_sources` with `source_rules` is the
  one source register; heritage's hosts join it.
- **It lives in the main database** with the listings, since public cards read it for the ring and How we know: Data
  separation (decisions/0110) no longer moves `identity_facts`, `identity_fact_sources` or `identity_applied`, and one an
  earlier move took to the admin database comes back on its own (`DataMove::home`, run by scheduled jobs and the Data
  separation page). The crawler's raw archive, its queues and the registry copies may still move.
- **Read before paying, everywhere:** listing confidence, the guide writer, Live events, Find it, AI Assist and the
  research brief ask the record first; a paid call is made only when nothing fresh enough is held, and its answer goes
  back into the record for the next reader.
- **Both directions:** a guide's research and Find it feed listings; a business's own site, read for its listing, also
  feeds events and guides; an event at a venue counts as evidence the venue is active; a guide's facts about a business
  become evidence on its listing.
- **The cost log notes what each paid call produced**, so Services & usage shows the return on each source and catches
  money spent twice.
- **Identities from first sighting** (built 2026-11-01): every crawler sighting gets an identity the moment it's
  staged (`record_kind = 'intake'`, keyed by its `crawler_raw_index` row; `Identity::fromIntake`): the page it came from,
  the sites that confirmed it, and the reference index and NPI/IRS matches, read once then. Verify's results and a
  re-crawl of the same location rebuild it. Approving it, merging it into a listing, or closing it as the same as one
  (nothing new, covered by) moves its evidence onto that listing, noted "From the crawler's sighting #id", and the
  sighting's own copy goes (`Identity::adopt`): one record. Sightings turned down for other reasons keep theirs, so
  what we saw is never lost. Those from before were given theirs by scheduled jobs, 50 a run
  (`Identity::backfillIntake`). OpenStreetMap is now a link (`evidence`), not a counted source; Wikidata (CC0) still
  counts.
- **The rebuild is a repair tool** (built 2026-11-01): `Identity::fromListing` no longer wipes and rebuilds. What it
  adds is kept and joins what's there, so a second run changes nothing; the sightings that became the listing, merged
  into it or were closed as the same as it join through `adopt()` (a sighting still waiting keeps its own). The old
  rebuildable rows were converted by scheduled jobs, 50 listings a run (`Identity::backfillListings`), and identities
  whose listing is gone are cleared.
- **Counting is honest** (2026-11-01): one website counts once, however it was found (the NPI Registry read on
  cms.hhs.gov is one source, not two). An official registry, the owner or staff still confirm on their own.
- **The evidence changes a listing carefully:** it may fill an empty phone, address, website or email with one
  independent source, but replacing a value the listing already shows takes two; anything less goes to a person. A
  name changes only to a fuller form of the same name (every word kept, short forms spelled out: "Zuni Recovery Ctr" to
  "Zuni Recovery Center"), never to a registry's legal name over the one people know. A sighting under another name at
  the same phone or address (a provider at a hospital) brings only where it is and how to reach it, never its name.
- **Review shows what we know:** a sighting's page in Review opens with its identity card (each fact, confirmed or one
  source, and every source with its link), so a person decides on the evidence.
- **Still to fold in** (later steps): a new match in the reference index (a site read again) joins the identities it
  matches when it's found; secondary categories become kind facts; the duplicate `category` name in the listing's fields
  goes once its one reader uses the category id.

### Owning our data: what we store, what we only reference (Jason, 2026-11-01)

We store only what we may own. Anything whose terms would stop us owning our record is a **reference**: a lead that
tells the crawler where to look. The crawler then reads the business's own site, a registry or a public record, and
that is what we store and cite.

| Source | Under this rule |
|---|---|
| OpenStreetMap (ODbL, share-alike) | A lead only: never a stored value we show. Its share-alike would reach our record. `open_places` (outside our regions, a week, display only) is a short-lived reference, never merged in. |
| Google | Its place ID only, as a link (`evidence`); its hours and status prompt a check. |
| Facebook, Instagram, Yelp and other commercial directories | Links; nothing read from them is stored as our value. |
| Wikipedia (CC BY-SA, share-alike) | Cite only (Jason, 2026-11-01): linked, never copied or read in bulk. Its article text is no longer read for town dossiers or kept on landmarks (a landmark shows Wikidata's description and links to the article); the rule in Manage sources decides, so switching it back needs no build. |
| The business's own site, government registries (NPI, IRS, state and county), the Census, Wikidata (CC0) | Owned: stored with the source. |
| The owner, staff (a call, a visit), a postcard code, a visitor's confirmation | Owned: our own evidence. |
| Claude's results | Ours (Anthropic's terms give the customer the outputs), but never evidence: what Claude found leads the crawler to the page, and the page is the source. |

A link (`evidence` kind) is never counted as a source agreeing, in the status or the score (built 2026-11-01).
**Checking the terms happens in Pulse** (Jason, 2026-11-01: "I don't want to dig through pages to find something
needing attention"): each source the engine runs on whose terms no person has read, or not in a year, is a System item
in Pulse with our rule in its summary, a step to open its terms in their own tab, and **I read them**, which records who
and when and resolves it (it comes back in a year). If they don't allow our rule, a step opens Manage sources to change it.
**Failed crawls clear themselves where no one is needed** (Jason, 2026-11-01): a failed source is removed when the same
address was crawled with success since (its sightings move to the run that worked), when it was never a website (a
phone number typed as an address), or when it's a site we never crawl (social media, Google, Yelp). Those two can't be
entered any more. A failure that may be the site's own (it was down) is a System item in Pulse: crawl it again, or
**Remove it** (what it found stays).
This replaces "OpenStreetMap under its licence with credit" as a stored source, and the OSM seeding in *Opening a hub*.

### A claim is a claim (Jason, 2026-11-01)

Any claim (the email code, a text from the listed phone, a domain email, a document) puts a listing in the top band,
**Claimed by owner**: the owner is a strong source on their own. The ✓ is the next step, once the owner's link to the
business is proved (the listed phone, a postcard code, a staff call, a domain email, a document, a field visit). A
claim later found false drops the listing back to what its evidence earns. This settles 0070's "Managed" question.

### Find it under the one record (Jason, 2026-11-01)

Find it (decisions/0131) links the names a guide mentions to listings. It keeps its search order (the crawler first:
our record, the business's own site; Claude with web search for what's left), but it no longer has a score of its own:
Claude's confidence (50 or 70) and OpenStreetMap's 40 are leads, not evidence. A find becomes a candidate identity like
any sighting, the crawler reads the pages Claude found, and the same listing confidence decides whether it's listed. One
score, one record. (To build; until then the 80 rule stands, with this as its replacement.)

### Listing confidence (0 to 100)

Built on the store, it **only orders work and shows how sure we are**, as Phase 5 already says: publishing still hangs on
the readiness rules (decisions/0076), never on a number.

- **Phase 3 for listings:** the same vetting test as heritage. Independent sources count; pages on one host, and
  directories copying each other, count once.
- **Weights (tuned in Thresholds & gates, first values to be adjusted after a month of sampled checks):** the owner's
  claim and confirmation strongest; an official registry strong; the business's own live website showing its phone and
  address strong; a Google match strong for existence and place (a Google "not found" is weak, since many rural
  businesses aren't on Google); OpenStreetMap medium; two or more independent pages medium; our team's confirmation
  strong; visitors' "Is this right? Yes" small each, counted per device a day, never who. Disagreements lower it: a dead
  website, an address mismatch, an open closure report, a likely duplicate. **Evidence ages**: each line counts for less
  as it gets older.
- **Green means a person stands behind it.** Machine evidence alone tops out at **75**; 76 and above take our team's
  confirmation or the owner's claim. A green listing whose evidence turns against it (a dead site, a closure report)
  drops out of green until someone looks again.
- **State business and licence registries** only ever add: a record found raises the score; a record not found never
  lowers it (many rural sole proprietors, ranches and family businesses are never registered, and a business on a
  nation's land is licensed by the nation, whose register comes first); a record saying dissolved or revoked goes to a
  person, never straight onto the card.
- **Provenance holds:** evidence never overwrites what a person or an owner entered; a disagreement is a tip or a
  Review item, and a claimed listing is only offered the change (as for a person's guide sections: fact tips, never
  rewrites).

### On the card: the ring and "How we know"

- A ring in the card's top-right column, under the Link button, at the actions' 30px size: the number inside, the arc
  filled to the score, its colour taken from the red-orange-yellow-green gradient at that score (solid band colours are
  a one-line switch if the olive between yellow and green reads badly). The ring uses a deeper gold than pure yellow so
  it shows on cream; the number stays dark brown; the label is spoken for screen readers, so the score never rests on
  colour alone.

| Score | Band | What it means | What "How we know" tells a visitor |
|---|---|---|---|
| 0–25 | Red | Raw crawled data, unconfirmed | **Unconfirmed:** found by our crawler and not yet checked |
| 26–50 | Orange | Vetted by sources; scores compounding | **Being checked:** some sources agree, and it's being confirmed |
| 51–75 | Yellow | Qualifies on confidence scoring | **Qualified:** enough independent sources agree |
| 76–100 | Green | Confirmed by Traversence or claimed by the owner | **Confirmed by Traversence**, or **Claimed by the owner ✓** |

- Tapping the ring opens **How we know**: each piece of evidence and its date ("Address matches Google Maps · Oct 2026",
  "Phone on its own website · Sep 2026", "Owner confirmed · Aug 2026") and the number. Each field has **Is this right?**
  Yes (strengthens it) or No (opens a pre-filled correction).

### Closed or moved: notified, never hidden

A listing stays findable at every step:
- **Possibly closed:** one source says so (an amber notice).
- **Reported closed:** two or more sources agree, or a visitor reports it (a red notice; it sorts below open places).
- **Closed:** confirmed by a person or the owner. Still found by name with a "Closed" tag; out of browse lists.
- **Moved:** a notice with the new address when we have it.
Each notice carries **Still open? Tell us**, so the owner or a visitor corrects it in one tap.

### Upkeep and touchpoints

- **Automatic checks** (its own website, Google, OpenStreetMap): **monthly** below 51, **quarterly** at 51 and above,
  **never more than 90 days apart**. Anything that changes (a closure report, a dead site) starts a check at once.
- **Owner "Still right?" email:** twice a year (one tap confirms; a change opens their editor).
- **Full re-verification** (registries, licences): yearly.
- This replaces Phase 6's single "listings every 30 days" clock.

### Less work for people

- **Machines decide when the evidence is overwhelming; people see the uncertain middle.** The same Google place ID and
  the same phone on two listings is an automatic merge (the Helper's rule); a closure agreed by three sources becomes a
  "Reported closed" notice without waiting. Every automatic decision is reversible.
- **Review is ordered by value:** low confidence on listings many people view comes first.
- **Paid checks go where they move the score:** weak and much-viewed listings first, settled ones on the slow clock.
- **Google's answers are used in full:** a shared place ID marks duplicates; a name that matches with a different
  street number or town is a "Moved or wrong address?" item (Google's address isn't kept); a listing Google can't find
  several times, with nothing else confirming it, goes on a low-priority list, never removed on Google's word.

### Listing check: the checking process as a template

The checking process is a Content template, **Listing check**: its sources in order, free first (its own website,
OpenStreetMap, registries, our reference index), then Google, then web search only for the gaps; the cadence per band;
its budget. It's shaped, previewed and tried like the Place guide and Live events, so guides, events and listings run on
one engine. The same store and score can later rate landmarks, outdoor places, event venues and Nexus brands; listings
come first, where the payoff and the labor are.

### Opening a hub (no cold start)

"Read the record before paying" fails where the record is empty, so a new hub opens in stages and never fails in public:

| Stage | What happens | Visitors see |
|---|---|---|
| **1. Planned** | The state's laws reviewed in the jurisdiction register (the region hold blocks publishing until then); the free sources set: statewide files, the official visitor bureau and chambers, the state's and nations' registries. | Nothing |
| **2. Seeding** | Free bulk sources fill the record first (OpenStreetMap as leads only, each checked at the business's own site or a registry before its values are kept): NPI, IRS, landmarks, the National Register, the Library of Congress, outdoor recreation, state registries, chamber and visitor-bureau directories. Nothing publishes unless it passes readiness. | Nothing |
| **3. Opening budget** | A one-time paid allowance for the hub, separate from monthly upkeep (opening never starves existing hubs, nor they it), spent highest value first: the busiest towns, then the essentials (fuel, food, lodging, medical, emergency), then the rest. | Nothing |
| **4. Soft open** | When the readiness targets are met (every town's essentials at 51+, most listings at 51+, the community and city guides written), it opens marked as a new area: honest rings, monthly checks, missed searches feeding gap-filling. | The hub |
| **5. Open** | Most listings at 51+: normal upkeep. | Normal |

- **"Read first; if it's thin, fill it from the hub's budget":** a thin record starts gathering, never a failure.
- **Each hub makes the next cheaper:** state registries, chains (Nexus), statewide files and businesses near borders are
  already in the store; the second hub in a state starts warmer than the first.
- **A hub readiness meter** in the admin shows each target, the opening budget spent and left, and what's still to fill.
  A hub that falls short stays in Seeding.
- The readiness targets and the opening budget are settings in Thresholds & gates; the budget is set per hub when it's
  opened, from a suggested amount the meter works out from its town and listing counts.

### Costing a hub: Payson (Rim Country), worked example (2026-10-31)

Planning estimates (the live counts come from the meter; Preview shows exact costs before anything runs), at the rates
the site is configured with: Sonnet $2/$10 and Haiku $0.10/$0.50 per million tokens in/out, web search about 1¢, Google
Text Search $0.032 at list price inside Google's free monthly allowance.

- **Scope:** 13 guides (the Rim Country area guide; communities: Payson and Star Valley, Pine and Strawberry, Christopher
  Creek with Kohl's Ranch and Tonto Village; about 9 towns), about 22,000 people, about 1,500 raw candidates and about
  900 listings published. The Tonto Apache Tribe's land borders Payson and follows the nation rules; Arizona's laws are
  reviewed, so there's no region hold.

| Opening work | How much | Cost |
|---|---|---|
| Seeding from free bulk sources | Everything | $0 |
| Verify for candidates lacking references | ~900 × ~2 searches | ~$18 |
| Google checks on published listings | ~900 | $0 inside the free allowance (~$29 at list) |
| Research briefs (gaps only) | 13 × ~$0.20 | ~$3 |
| Guide drafts (two passes) | 13 × ~$0.25 | ~$3 |
| Heritage facts, names, topic summaries (Haiku) | 13 places | under $1 |
| Find it (crawler first; Claude for ~40%) | ~130 names, ~50 paid | ~$4 |
| **Total** | | **≈ $30** (≈ $60 if Google charged list) |

- **Suggested opening budget: $50.**
- **Monthly upkeep after:** listing checks about 450 a month (~$1–2), guide checks free plus a renewal or two (~$0.50),
  Live events for 4 places weekly (~$0.50): **≈ $2–5 a month**. Members' AI Assist asks are paid for by plan credits.
- **People are the real cost:** about 150–200 Review items at opening (1–2 minutes each, **4–6 hours**, mostly in the
  first two weeks), then **about an hour a month**, falling as owners claim and visitors confirm. Green rings take our
  team or the owners: owner outreach (Sales) is the lever, and it's revenue.
- **Watch:** Payson's ~900 checks on top of today's ~1,600 a month pass the worker's cap (3,000, stopping at 2,700):
  raise `GOOGLE_PLACES_MONTHLY` to about 4,500 (inside Google's free 5,000, as Google's pricing stands) or let the
  opening checks spread over two months. Seeding takes about 1–2 weeks of paced crawler runs before soft open.

### Proving the return

A **Listing quality** card in Insights and in Services & usage, monthly: the share of listings in each band and the
average score; issues found by each source (Google, OpenStreetMap, the crawler, registries, visitors); issues resolved
automatically versus by a person; the time to resolve; what each paid source cost per issue found. A monthly sample of
listings in each band is spot-checked, like the quarterly guide audit, to tune the weights.

### Claude's role: reader, researcher, writer, helper; never the source

- **Reader (Haiku):** turns what the crawler fetches (a town's site, a newspaper page, a business's "About us") into
  structured lines for the record. A page is read once and stored, so every later reader uses that reading.
- **Researcher (Sonnet with web search):** fills gaps only, after the record and the free sources come up short
  (research briefs, Find it, Live events beyond official pages, topic research). What it finds goes into the record.
- **Writer (Sonnet):** guides, summaries, AI Assist answers and event descriptions, **only from the record**, through the
  same scrub and gates as everything else.
- **Helper to people:** on request (Check my facts), and next, **Review pre-sorting**: for each uncertain item (a possible
  duplicate, a closure, an unclear category) Haiku drafts a recommended answer with its reasons, and the person agrees or
  corrects (about a tenth of a cent each; seconds instead of minutes).
- **Claude's word is never evidence.** What it researches counts only through the pages it cites, which our crawler
  reads and records under their own sources (as the research brief never counts as a source for a heritage fact). In
  listing confidence a fact Claude found carries the weight of the page behind it, never "Claude said so".
- Cheapest model first, each feature's budget in AI use, prompts shaped in Content templates with a free Preview, every
  answer kept in the record; a member's words reach Claude only with their AI Assist & Learning consent.

### Social pages (Facebook, Instagram): links, not sources we crawl (Jason, 2026-10-31)

Many rural businesses have only a Facebook page (Diamond C Feed, St. Johns: facebook.com/diamondcfeedarizona).
- **Never crawled or scraped.** Meta's terms bar automated collection without its permission, and the pages sit behind
  its login wall; the crawler never fetches them, and neither does Claude.
- **Kept as the listing's social link**, shown on the card (a Facebook action button) and in How we know. Its existence
  (found by a search, given by the owner or a visitor) is **weak evidence the business exists**, never of its phone,
  address or hours (decisions/0086 already: a social page doesn't count as a website).
- **Search results that show it** (the title and snippet a web search returns, which we already pay for) count as one
  ordinary web source, with the snippet's details weighed like any other page.
- **People read it:** our team can open the page and confirm details (recorded as our confirmation, "seen on its
  Facebook page", and counted as Traversence's confirmation), and visitors' "Is this right?" works the same.
- **The owner can connect it (later):** on claiming, an owner may link their own Page with Facebook Login, letting us
  read their Page's details (hours, phone, about, events) with their permission through Meta's Graph API. That's the
  one way a Facebook page feeds the record directly, and it also brings their posted events into Live events.

### Build order

1. The observations store is the identity record. **Built 2026-11-01:** the identity tables stay in main; every
   listing write records into the identity with its source (the one writer); values taken off stay off; merges join
   the evidence; Google's place ID is a link, and links aren't counted. Identities from first sighting (also
   2026-11-01): every sighting has one, moved onto its listing when approved or matched. The rebuild is a repair tool;
   one site counts once; replacing a listing's value takes two sources; Review shows a sighting's identity card
   (2026-11-01).
2. Listing confidence. **Built 2026-11-01** (`ListingConfidence`, `listing_confidence`, `listing_confirms`): the score
   from the identity record (each independent source once, weighted by kind: our team 30, an official registry 22, a
   government page or its own website 18, a chamber 12, a community directory 10, another page 8, open data 6, a
   directory 4; a Google match 10 as our verdict that it's there; other links nothing), each counting a little less than
   the one before and less as it ages (to 40% over three years); +5 for each of who, where and how confirmed; visitors'
   Yes, up to 8 a year. Against it: a dead website −10, reported closed −20, its site says it moved −8, an open visitor
   report −5 each (to −10), sources disagreeing −5 each (to −15). Machine evidence stops at 75; our team's check lifts it
   to at least 80 (Confirmed by Traversence), a claim to 80 (Claimed by the owner), a verified claim to 90 (✓), unless
   something speaks against it. Refreshed whenever the identity changes, and weekly by scheduled jobs. The ring heads the
   listing card's actions column; How we know opens under the card with Is this right? Yes / No per detail. Next: the
   ring on listing cards in search and lists; cadence by band.
3. Google and OpenStreetMap used in full (duplicates, address mismatches, not-found); the Listing check template;
   cadence by band; automatic decisions; value-ordered Review; closure and moved notices.
4. Opening a hub: the stages, the readiness meter, the opening budget and its estimate.
5. The Listing quality report.
6. Review pre-sorting by Haiku; the owner's Facebook connection.

## Records this replaces (for the crawler)

Each keeps its history and carries a pointer here: 0005 (crawler and guide gates), 0024 §3, 0043 (crawler parts), 0044,
0045, 0048 (sources and loads), 0054 §6, 0061 (thresholds), 0062 (crawler and identity-job rules), 0065, 0066 (crawl
targets; the tribal standard is restated here), 0067, 0076, 0083 to 0086 (crawler parts), 0131 (dossier, research, Find
it, publishing). In the platform spec, §11, §15, §21 and §30 point here: the "unbounded" crawl, the old `api/ingest.php`
path, and Google Business Profile as a source are withdrawn.
