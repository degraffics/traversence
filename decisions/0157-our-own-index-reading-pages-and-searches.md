# ADR 0157: Our own index: reading pages and reading searches

**Status:** Accepted (Jason, 2026-11-13: "yes approved, start delivery 1"; the second tier added the same day at his ask). Deliveries 1 to 4 built 2026-11-13 (3b folded into 4, Jason: "build 3b and 4 as one"). It extends `decisions/0045` (our own
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

**Two tiers in every part** (Jason, 2026-11-13: "I really want the automation to eliminate as much human responsibility
as possible"; "These should be second tier services when first tier fall short"). The first tier is free: our own index,
the rules below, OpenStreetMap, open data. The second tier is paid, Tavily (search and page reading) and Claude, and runs
only where the first tier fell short, for that one page, search or find; its rules and costs are under "The second tier"
below. Today Tavily runs only for the second look at a single-source listing (gap searches keep .gov/.edu pages), and Claude
searches only for guides' names ("Find it"); both move into the parts below.

### 1. The page log

Every page the worker fetches is recorded: its address, the site, when, the answer (status, type, size), a fingerprint of
its content, the last-changed and entity tags its server sent, and when to read it next. Before downloading a page again
the worker asks whether it changed (If-Modified-Since / If-None-Match); an unchanged page costs nothing. The next visit
follows how often the page changes: an events calendar weekly, a county page monthly, a static site quarterly, never
sooner than the site's robots.txt allows. Pages that named nothing are logged too, so we know what we have read. Kept on
the site's database as small rows (no page bodies); what a page said is kept as facts (§2), not copies.

*Second tier:* a page that matters (a read source, a job's target) and that our fetch can't get or shows nothing without a
browser goes to Tavily Extract; it's logged like any page, so it isn't fetched that way twice.

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
8. *Second tier:* **Claude reads what the rules couldn't** (the light model), for a page that matters (a "Read regularly"
   source, a job's target, a page the second tier fetched) and that steps 1–7 left short: no kind, no address, an item
   they couldn't split. It gives the facts as structured data, each with the exact words it came from; a fact whose words
   aren't on the page is thrown away, so nothing counts unless the page says it. Never for routine reading.

The place store (`source_facts`) gains the kind of business, the town, the website, hours, coordinates, the kind of page
and how it was read (structured data, list item, text, AI). Events read from pages go to the events store with their page.
The licence rules don't change: a "Cite only" site is never read in bulk; a source's licence decides the use (0133), noted
on the register for each site; tribal nations' land and sensitive sites stay as 0066 says.

### 3. Searching our own index

Get Local, the site's search, the admin search and "Send the crawler" look in the place store first. A place found there
that we don't list yet is staged (0044) with its sources, so it goes through the usual checks: two independent sources
publish it, the rest wait in Listing Review. Results show their sources one tap away. "Send the crawler" (Jason only, on
Find a business) then re-reads the town's registered sites, suggests new ones for his OK, and queues OpenStreetMap for the
kinds the words mean.

The first tier also holds the open place data, loaded for our regions as sources of their own: Overture Maps places
(CDLA-Permissive) and Foursquare Open Source Places (Apache 2.0), licences confirmed before loading; and the checks that
confirm a find: the state's business registry (Arizona Corporation Commission: active or dissolved) and the USPS address
check. Each counts toward the two sources (0044).

*Second tier:* a search, a gap or a town where all of that found nothing ("garbage" near St. Johns) gets a Tavily search
for the kind and town, with no .gov-only filter (a business's own site counts; commercial directories it turns up are Cite
only, 0045, never copied). The pages go through §1 and §2 like any other, and a search is paid for once.

**Built (delivery 2, 2026-11-13):** the site's search, Get Local and the admin's search show "Named on pages we read"
when ours are few: places in the store we don't list (same phone, or most of the name within about a mile, means we do),
by the kinds the words mean or their own words, within reach of the place, each opening the page it's named on. Every
read stages what it names that we don't list (up to 40 a read, those with a kind and a ZIP) through the intake (0044).
"Send the crawler" is for the Founder signed in to the admin; it marks the town's read sources due, re-runs the town's
OpenStreetMap jobs (all its kinds) and lists the sources waiting there by name.

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
6. *Second tier:* a search the rules can't read (no intent found, nothing found) is read by Claude (the light model): the
   intent, the kind and the place, offered the same way ("Showing … for …", never changed, 0064). Kept, so the same words
   aren't paid for twice, and shown to staff in the learning pass as a hint for our own words.

**Built (delivery 3, 2026-11-13):** `LocalWords` (app/lib/LocalWords.php), rebuilt daily from the crawler's report: every word
of our public listings' names, kinds and offerings, the place store's names and words, our states' towns and the landmarks
we show, with how often each is used, and for each word's root the kinds of business it sits with. Spelling: a town's or
landmark's name word, or a word used three times or more, is never offered a fix (nor a real word's plural, "hills"); the
words used here are offered before a general dictionary's, lifted by how often; two swapped letters are one slip. Sense:
`Concepts::kinds` lifts the kinds the words sit with here and lowers the rest; a kind they plainly mean here is added only
when none of the dictionary's sits with them. Staff's words are never moved. Known gap: the WordNet data lists
"resturants" as a word form, so it isn't offered a fix (to clear in the lexicon builder).

### 5. Trust and licence per source

The register keeps, per site, how often its facts were later confirmed (by a phone call, an owner, another source) or
contradicted, and its licence (what we may do with what it says). Well-confirmed sites weigh more in the two-source rule;
often-wrong ones less; a person's call on a site always stands.

*Second tier, settling a find:* what the first tier leaves unsettled (a find below the publishing score, two sources that
disagree, a closure report, a possible duplicate, a guide's name) gets, in order: a Tavily search for that business by name
and town, Claude's quoted reading of what it finds (§2), then, only if still unsettled, Claude's research (the stronger
model with its web search, as "Find it" does now). That decides: publish, turn down (contradicted: a dissolved
registration, a disconnected phone, a page saying it closed) or merge (the same place, sure), with its reasons and sources
on the record (§6), undoable. Left for a person: tribal nations' land and sensitive sites (0066, never decided by a
machine), an owner's dispute, and what the research couldn't settle.

### 6. "What we know and where from"

For any place, listing or topic: every fact we hold, each with its source page, date, how it was read and its licence, the
ones that disagree marked. Guides, listings, journeys and AI writing draw from it, and an editor can see why the site says
what it says. Each fact also says which tier found it (our reading, Tavily, Claude).

### 7. The register decides, and nothing found is wasted (Jason, 2026-11-13)

Jason: "I really want the automation to eliminate as much human responsibility as possible"; "Most sites have a subject or
main description so it could be used as what shapes its reason for use"; "We also have the aws text scanner too"; "When
second tier look for content or sources they follow the steps 1,2,3,4,5 as well right. I dont want paid services to leave
out any info that could be reused"; "Similar to Google search the information we find can be re-served as data found by a
search query; not just results shaped by our platform mission". This amends 0045, where a person made every call.

1. **The register judges a site itself**, from its address and what it says it is (its title, its description line, its
   schema.org Organization record): government (.gov, .mil, state and local .us), schools (.edu) and official registries
   are read regularly at once; commercial directories and social media are cited only, as before; chambers, tourism sites,
   community directories and organizations wait for the second tier (Claude reads the site's terms and a sample page and
   decides read or cite only, quoting the terms), and until the caps are set they wait for a person. Each call shows
   "set by the crawler" and why; a person's call always stands and is never changed by the crawler. Tribal nations' sites
   (by their address or what they say they are) are always a person's call (0066).
2. **PDFs** are read like pages: their text, free (first tier). A scanned PDF (pictures of pages, no text) is logged as one
   and goes to the second tier: AWS Textract (already connected, per page read), then Claude only if Textract can't read it.
3. **What a paid step fetches goes through every step a free read does**: the page log (§1), reading the whole page (§2,
   every place on a list, not only the one sought), the store and its search (§3), the dictionary (§4), the source's
   record (§5) and "what we know" (§6). Paid once, kept for reuse. One exception, the licence: a site whose terms forbid
   copying (a commercial directory) is kept as a link and as confirmation, never as copied facts.
4. **Our index answers any search**, as a search engine's does, not only with listings: the places and pages we hold that
   match, each with its source ("From pages we read"). Our mission shapes the order and how it's shown, not what can be
   found (0133: curators, not controllers). Three limits stay: the licence (a cite-only page is a title and a link), tribal
   nations' sacred and sensitive places (0066), and private details about people.

### The second tier: when it runs and what it costs

Only where the first tier fell short, for that one page, search or find; never as a routine. Demand leads: what someone
searched for first, then guides' names, then the rest. Per find, at most a set spend (about two cents: one or two Tavily
calls, one light read, the stronger research only when needed); a monthly cap for Tavily and the AI cap (0131) for Claude,
both shown in System Configuration → AI use. Past a cap, the rest wait for next month, most asked-for first, and the first
tier carries on. Spending needs Jason's OK under CLAUDE.md "No income, no expense": the second tier is his ask, the caps
are his to set, and each part's second tier is switched on only with them.

### The test set (from the first delivery)

A list of real searches and what each should find, run daily with the search probe (the "Our index" cases in
`tools/page-audit/search-scorecard.mjs`) and shown in Site health: today's ("garbage" and "trash collection" near St. Johns: Blue Hills Environmental and the
landfill once held; "garabe"; "places to shoot": ranges; "places to stay": lodging; "lunch": restaurants; "Besh-Ba-Gowah":
the park and museum), and each new slip as it's found. A change ships only when the set holds.

## Consequences

- The crawler stops throwing away what it reads: every page is logged and every place named on a read source is kept,
  understood and searchable, with its source.
- Search answers from our index first; a business named on a county page we read (Blue Hills) is found though nothing
  lists it yet, and goes through the usual checks.
- Reading pages and searches gets better without paid services: structured data and our own words do most of it. The
  second tier (Tavily, Claude) takes only what the first leaves short, within Jason's caps, so most finds settle without a
  person.
- Costs: the worker runs longer (Railway, a few dollars a month at most, within the current plan); the site's database
  gains small rows (pages, facts), no page copies; no per-use cost beyond the second tier's caps.
- Limits, said plainly: a business that no page we read names, and no listing or owner gives us, stays unfound until
  someone links it or the second tier finds it; pages that need a browser to show anything are read only when they matter.

## Deliveries (each its own zip, in order)

1. Page log; structured data first; list pages split into items; kinds and towns on place facts; the test set.
2. Searching the place store (Get Local, site search, admin search) and "Send the crawler" (Jason only).
3. Our index as the dictionary; senses from context.
4. Intents and phrases; "how your search was read"; facts answered directly; "From pages we read" for any search; the
   register judging sites itself; PDFs read (with 3b, as one delivery).
5. Trust and licence per source (the second tier's reading of a site's terms comes with the caps).
6. "What we know and where from".

Each delivery brings its part's second tier, switched on once Jason sets the caps.
