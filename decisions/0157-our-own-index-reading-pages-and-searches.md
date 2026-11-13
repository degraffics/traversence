# ADR 0157: Our own index: reading pages and reading searches

**Status:** Proposed (Jason, 2026-11-13: "yes write 0157"; part 7 added the same day at his ask), for his OK before building. It extends `decisions/0045` (our own
reference index, source-first crawling), `0044` (confidence-gated import), `0133` (the observations store and identity
record), `0060`/`0061`/`0063`/`0064`/`0067` (search, situations, the lexicon, words search learns, what words mean) and keeps
`0065` (OpenStreetMap is its own layer), `0066` (tribal nations' land), `0093` (what people open is a hint, never a
ranking) and `0007` (paid tiers never buy placement).

## Context

Jason, 2026-11-12/13, while testing Get Local:

- "Garbage" near St. Johns found nothing, though Blue Hills Environmental (garbage collection, 140 W Cleveland St) and the
  Apache County Regional Landfill are a few blocks away: "not sure how our crawler missed this?"
- "Confirm the crawler 1. Stores every url it reads. 2. That becomes an index of sources. 3. Those sources are then read
  into a dbase as organized data linking to that source. 4. We then can search the database for info related to a
  specific item or topic and have its sources and the data for reuse?"
- "Isn't the first step of the crawler what should be creating our index?"
- "Compare our index process to Google known process and see how we can do better." Then: "We need to Understanding a
  page and Query understanding, spelling, synonyms, intent better."

What we have (checked against the live code, 2026-11-12):

1. **The register of sources** (0045, `reference_sources`, Insights → Crawler sources): every website met, a person's call
   on each: Read regularly, Cite only (commercial directories whose terms forbid copying), Ignore, Waiting for you.
2. **The place store** (`source_facts`, `SourceIndex`): every place named on a "Read regularly" site, whatever job the
   worker was on, with name, address, house number, phone, ZIP and the page it came from; re-read every 30 days. Used
   only to *confirm* a listing we hold, never searched to *find* one.
3. **The raw index of sightings** (`crawler_raw_index`, `IntakeStager`): every business a crawl found, scored; two
   independent sources publish it (0044), the rest wait in Listing Review; identities from sightings (0133).
4. **The Topic index** (Topics, `topic_facts`): research facts by topic, each with its source (0133).
5. **Jobs**: gaps (cluster × kind), towns on demand (0149), situation gaps from the learning pass (0061), each answered from
   OpenStreetMap first and, for gaps, one web search keeping only .gov/.edu/local-government and "read" sources.
6. **Reading searches**: words matched in names, kinds and towns (SearchText), the hand synonyms, staff's words for kinds
   (category_terms), what words mean (Concepts, from WordNet, built offline), situations, the 5 W's, places in words,
   spelling offered never changed (0064), the learning pass (0061, 0093).

What's missing: no record of the pages read; the place store holds no kind of business, website or hours, and is never
searched; pages are read as text only (no structured data, no sense of what kind of page it is, no splitting a list page
into its items); and reading a search leans on a general dictionary (WordNet) instead of the words used where we are, so a
slip ("garabe") is offered the wrong fix and a word with two senses ("shoot") takes the wrong one.

How Google does it (its published account): discovery by links, sitemaps and submissions; a crawl budget per site and
revisits by how often pages change; rendering pages as a browser would; reading text, links and schema.org structured
data; one canonical page per duplicate set; facts merged into entities (the Knowledge Graph); query understanding
(spelling, synonyms, intent) before ranking. We can't match its reach and needn't: our index covers our regions, and there
it can be fuller, fresher and better sourced on places than Google's, with every fact's source shown and its licence
honoured, which Google doesn't do.

## Decision

Our own index is the crawler's first job: what it reads is kept, understood and searchable, and every fact keeps its
source. Six parts, built in this order, each its own delivery.

### 1. The page log

Every page the worker fetches is recorded: its address, the site, when, the answer (status, type, size), a fingerprint of
its content, the last-changed and entity tags its server sent, and when to read it next. Before downloading a page again
the worker asks whether it changed (If-Modified-Since / If-None-Match); an unchanged page costs nothing. The next visit
follows how often the page changes: an events calendar weekly, a county page monthly, a static site quarterly, never
sooner than the site's robots.txt allows. Pages that named nothing are logged too, so we know what we have read. Kept on
the site's database as small rows (no page bodies); what a page said is kept as facts (§2), not copies.

### 2. Reading a page

In this order, cheapest and surest first:

1. **Structured data.** schema.org JSON-LD and microdata (LocalBusiness and its kinds, Organization, Place, Event,
   OpeningHoursSpecification, GeoCoordinates, PostalAddress, telephone, url, sameAs). Taken as given, credited to the page.
2. **What kind of page it is**: one business's own site, a list of businesses (a county's services, a chamber's members,
   a directory), an events calendar, a government notice, an article. Each is read its own way.
3. **List pages split into items**: the repeated blocks (a name with a phone and/or an address) become one item each.
   A county "Waste services" page yields each company on it.
4. **What each item is**: the item's own words ("garbage collection, recycling, roll-off dumpsters") through the reader
   search uses (Concepts, staff's words) give it a kind of business; a page about one thing gives its kind to the thing.
5. **Where it is**: its address or ZIP, else the page's town, else the site's (the register knows a site's town).
6. **Linked to what we hold**: the identity record (0133) matches it to a listing, a landmark, a rec place or a town, so a
   sighting adds to a record instead of making a second one.
7. **Every fact keeps its page and date**; where sources disagree (a phone, hours, open or closed) the record says so and
   the disagreement goes on the call list.
8. **AI only last**, for a page the rules can't read and that matters (a "Read regularly" source, a job's target), within
   the monthly AI cap (0131); never for routine reading.

The place store (`source_facts`) gains the kind of business, the town, the website, hours, coordinates, the kind of page
and how it was read (structured data, list item, text, AI). Events read from pages go to the events store with their page.
The licence rules don't change: a "Cite only" site is never read in bulk; a source's licence decides the use (0133), noted
on the register for each site; tribal nations' land and sensitive sites stay as 0066 says.

### 3. Searching our own index

Get Local, the site's search, the admin search and "Send the crawler" look in the place store first. A place found there
that we don't list yet is staged (0044) with its sources, so it goes through the usual checks: two independent sources
publish it, the rest wait in Listing Review. Results show their sources one tap away. "Send the crawler" (Jason only, on
Find a business) then re-reads the town's registered sites, suggests new ones for his OK, and queues OpenStreetMap for the
kinds the words mean; a web search step comes only with his OK and a key (CLAUDE.md "No income, no expense").

### 4. Reading a search

1. **Our index is the dictionary.** Spelling and suggestions know the words used here: our places' names, towns, local
   names, and the words our sources use for kinds of business, weighted by how often they appear in our region. A slip is
   offered the nearest such word ("garabe": garbage; "motle": motel). Offered, never changed (0064).
2. **The sense from context.** Which words appear together in our index chooses a word's sense: "places to shoot" sits
   with range and rifle, not vaccine; "trash" with collection and landfill. Staff's words (category_terms) still come first.
3. **The intent first**, then the words: a name of something we hold ("Besh-Ba-Gowah", "Subway": that thing first), a kind
   of business, an activity, a fact about something (its hours, its phone), an event or a time ("this weekend"), a need
   (situations, 0061). Each goes its own way, and the results say how the search was read ("Showing gun ranges for
   'places to shoot'"), with a way to read it otherwise.
4. **Phrases are one unit** ("oil change", "trash collection", "places to stay"), learned from our index and from what
   people type, so a phrase's meaning isn't the average of its words.
5. **What people open** labels and prompts staff (the learning pass), never ranks (0093).

### 5. Trust and licence per source

The register keeps, per site, how often its facts were later confirmed (by a phone call, an owner, another source) or
contradicted, and its licence (what we may do with what it says). Well-confirmed sites weigh more in the two-source rule;
often-wrong ones less; a person's call on a site always stands.

### 6. "What we know and where from"

For any place, listing or topic: every fact we hold, each with its source page, date, how it was read and its licence, the
ones that disagree marked. Guides, listings, journeys and AI writing draw from it, and an editor can see why the site says
what it says.

### 7. When the free steps fail: Tavily, then Claude

Jason, 2026-11-13: "I really want the automation to eliminate as much human responsibility as possible"; "We need tavily
and Claude used better to fix issues where our free services fail." Today Tavily is used only for the crawler's second look
(one search per single-source listing; gap searches keep .gov/.edu pages only), and Claude searches the web only for names
in guides ("Find it", "Finds become listings by themselves"). One ladder now serves every find the free steps can't settle,
whatever raised it: a crawl find below the publishing score, a gap OpenStreetMap left empty, a search that found nothing
("garbage" near St. Johns), a guide's name, a closure report, two sources that disagree.

1. **Free first**: our own index (§3), OpenStreetMap, the open place datasets (Overture Maps places, CDLA-Permissive;
   Foursquare Open Source Places, Apache 2.0: loaded for our regions as sources of their own, licences to confirm before
   loading), the state's business registry (Arizona Corporation Commission: active or dissolved) and the USPS address
   check. Each confirmation adds to the score (0044); most finds should publish here.
2. **Tavily**, for what's still short: a search for the business by name and town (its own site, the county's and the
   chamber's pages, news), then Tavily Extract for the pages' text. Commercial directories found this way count as
   references only (Cite only, 0045), never copied. Its results go into the page log and the place store like any page we
   read, so a search is paid for once.
3. **Claude reads what Tavily found** (the light model): the facts as structured data (name, kind, address, phone, hours,
   website, open or closed), each with the exact words it came from on which page. A fact whose quoted words aren't on
   that page is thrown away: nothing Claude says counts unless a source says it. Same place or not, against what we hold.
4. **Claude researches what's still unsettled** (the stronger model, with its web search, as "Find it" does now): two
   sources that disagree, a closure, a possible duplicate. It decides with its reasons and its sources, and those decide:
   publish, turn down (contradicted: a dissolved registration, a disconnected phone, a page saying it closed), or merge
   (the same place, sure). Its reasons and sources stay on the record (§6).
5. **What's left for a person**: tribal nations' land and sensitive sites (0066: never decided by a machine), an owner's
   dispute, and what step 4 couldn't settle. Everything else is decided by the ladder and shown in the record, undoable.

Costs and order: demand leads (a find someone searched for, then a guide's name, then the rest). Per find, at most a set
spend (about two cents: one or two Tavily searches, one light read, a stronger research only when needed); a monthly cap for
Tavily and the AI cap (0131) for Claude, each shown in System Configuration → AI use; past a cap, finds wait for next
month, most asked-for first. Spending needs Jason's OK under CLAUDE.md "No income, no expense": this part is his ask, and the
caps are his to set.

### The test set (from the first delivery)

A list of real searches and what each should find, run daily by the search probe (`tools/page-audit/search-probe.mjs`)
and shown in Site health: today's ("garbage" and "trash collection" near St. Johns: Blue Hills Environmental and the
landfill once held; "garabe"; "places to shoot": ranges; "places to stay": lodging; "lunch": restaurants; "Besh-Ba-Gowah":
the park and museum), and each new slip as it's found. A change ships only when the set holds.

## Consequences

- The crawler stops throwing away what it reads: every page is logged and every place named on a read source is kept,
  understood and searchable, with its source.
- Search answers from our index first; a business named on a county page we read (Blue Hills) is found though nothing
  lists it yet, and goes through the usual checks.
- Reading pages and searches gets better without paid services: structured data and our own words do most of it; AI only
  for the pages that need it, within the cap.
- Costs: the worker runs longer (Railway, a few dollars a month at most, within the current plan); the site's database
  gains small rows (pages, facts), no page copies; no per-use cost unless Jason turns on a web-search key.
- Limits, said plainly: a business that no page we read names, and no listing or owner gives us, stays unfound until
  someone links it (or a paid search finds it); pages that need a browser to show anything are read only when they matter.

## Deliveries (each its own zip, in order)

1. Page log; structured data first; list pages split into items; kinds and towns on place facts; the test set.
2. Searching the place store (Get Local, site search, admin search) and "Send the crawler" (Jason only).
3. Our index as the dictionary; senses from context.
4. Intents and phrases; "how your search was read".
5. Trust and licence per source.
6. "What we know and where from".
7. The ladder for finds the free steps can't settle: the open datasets and checks, then Tavily, then Claude.
