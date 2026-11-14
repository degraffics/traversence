# The Knowledge Engine: crawler architecture, Phases 1–6 (draft, 2026-11-14)

**For Jason's review.** His reworked phases ("PHASE 1 – 6: Knowledge Engine Crawler Architecture", 2026-11-14), kept in his
structure, with the session manager's improvements and concerns written in. Nothing here is built or decided yet: once
Jason has settled the open questions (§9), this becomes an amendment to `decisions/0133` (which stays the rule book) with
`decisions/0163` (operations) underneath it.

How changes are marked:
- **✚ Added**: not in the draft.
- **✎ Changed**: the draft said something different; the reason follows.
- **? Decision**: Jason decides (listed again in §9).

---

## 0. Foundations (✚ added: what every phase stands on)

1. **The rule book is `decisions/0133`:** curators, not controllers (no topic left out for being sensitive; only exposure
   and representation managed); "tell the story, never the way in"; **never overwrite what a person entered**; AI acts on
   a person's words only when called; **the licence decides the use**.
2. **Operations underneath every phase (`decisions/0163`):** every outside call passes the switches (master, workflow,
   task, service; live 2026-11-14), the service check (`ServiceHealth`: holds, back-off, automatic return), its budget and
   pace, and is counted in the cost ledger, failed calls included. "No income, no expense" applies: the research exception
   covers Claude for guides, journeys and their research tools inside the AI cap.
3. **Where each part runs:**
   - **The crawler worker** (Railway, Python): Phase 1 fetching, Phase 2 extraction, local NER.
   - **The website** (Bluehost, PHP 8.0, **MySQL 5.7.44**): the frontier and registry tables, Phase 3 resolution and
     vetting, Phase 4 catalog, Phase 5 serving, Phase 6 sweeps (scheduled jobs), the admin and Pulse.
4. **Build on what's there:** each phase maps onto existing tables and libraries (§8) and extends them; nothing is
   rebuilt beside them.
5. **Layers kept apart:** OpenStreetMap is its own layer (decisions/0065): it points us to a place, is credited, and is
   never copied into our records. Google's terms let us keep only its place ID. Neither is a source of facts for our
   catalog.
6. **Protected places and tribal nations:** 0133's three levels (public; known publicly but not open to visit; held) and
   decisions/0066 (what's specific to a nation is held until the nation's own tools exist and it says so; pins on a
   nation's land move to a public place) apply in every phase.

---

## Phase 1: Wide discovery, restricted registry and source classification

### 1.1 URL frontier, normalization and re-verification queue

Every discovered link (`<a href>`, `<link rel="canonical">`, sitemap `<loc>`), and every re-crawl task sent back by later
phases, is normalized before queueing:
- **Canonicalization:** lowercase scheme and host; strip fragments; strip tracking parameters (`utm_*`, `fbclid`,
  `gclid`, `ref`); resolve relative paths; sort remaining query keys; strip trailing slashes except on the root.
- **Deduplication and priority:** `url_hash = SHA256(normalized_url)`, checked against `crawl_frontier` and
  `restricted_registry` before insert. Inserts from a Phase 3 recheck or a parser-drift flag get `priority = HIGH`.
- **States:** `PENDING → IN_FLIGHT → FETCHED | RESTRICTED | FAILED_TRANSIENT | GONE`.
- ✚ **Per-host and per-workflow shares:** the frontier hands out work inside each workflow's share of a run
  (`CrawlService::plan`), so one site or workflow can't starve the others.
- ✚ **Built on the page log** (decisions/0157 §1): the frontier extends it (address, site, answer, fingerprint,
  last-changed and entity tags, next visit), and an unchanged page answers 304 and costs nothing.

### 1.2 Pre-fetch compliance, restricted gate and negative caching

In strict order, before any GET:
1. **Switches and service check** (✚): is the master, the workflow, the task and the site (as a service) on, and is the
   host not held?
2. **Restricted registry lookup** by `url_hash` or domain/path pattern: a hit logs `SKIP_RESTRICTED` and stops.
3. **robots.txt** (cached 24 hours): a disallowed path is recorded `ROBOTS_EXCLUSION` and stopped; `Crawl-delay` sets the
   host's pace (the larger of it and our 1.5 s default).
4. **Non-public paths** (`/wp-admin`, `/login`, `/signin`, `/account`, `/cart`, `/checkout`, `/auth`, `/dashboard`):
   recorded `ADMIN_PATH` and stopped.
5. **Negative caching:**
   - ✎ **robots.txt exclusions are re-evaluated whenever the cached robots.txt changes**, not kept forever (sites change
     their rules; the draft made them permanent).
   - Paywalls (`PAYWALL`) and sign-in walls (`AUTH_WALL`) stay excluded; re-checked once a year.
   - Transient failures back off exponentially (as 1.3).

### 1.3 HTTP fetch and post-fetch restriction capture

- **Request:** GET with our User-Agent (crawler name, site and support@traversence.com), `Accept:
  text/html,application/xhtml+xml,application/pdf`, a 15-second timeout, no cookies, no following of sign-in redirects.
- **Status routing:**
  - **200:** ✎ **keep the fingerprint, headers and the extracted facts with their snippets, not the page body.** The
    draft stored every raw page (`raw_captures/`); 0157 keeps facts, not copies, for copyright, cite-only sources' terms and
    storage cost. ? Decision 1 (§9).
  - **301/302/307/308:** a redirect to a sign-in page records the original as `AUTH_WALL`; otherwise the target is
    queued (at most 3 hops).
  - **401/403:** `AUTH_WALL`. ✚ Never retried another way (no new agent, no other route).
  - **402, or a hard paywall signature** (`isAccessibleForFree: false` with a cut-off body): `PAYWALL`.
  - **404/410:** `GONE`, re-checked once after 30 days, then archived. ✎ **For a listing's own website, GONE is a signal
    on the listing's score, never a closure on its own** (Jason, 2026-11-13: scoring decides).
  - **429/5xx:** back off `2^attempt` minutes, at most 5 tries, and ✚ follow the server's `Retry-After` exactly.
  - ✚ **406/418 from an API** (a ban): the service check holds the service and opens one Pulse case; never retried until
    resolved (live since 2026-11-14).

### 1.4 Automated source classifier and dossier engine

Each 200 gets a `source_tier` (1–5) and its dossier:
- **Tier 1, official and institutional:** `.gov`, `.mil`, `.edu`, or a domain on the state, county, municipal, tribal or
  registry seed lists. ✚ Tribal nations' sites are often `.org` or `.com`, so the seed list names them.
- **Tier 2, first-party:** a single organization's or place's own site (schema `Organization`/`LocalBusiness` matching
  the domain's main entity), or a single-topic site with no aggregator signs. ✎ **WHOIS dropped as a signal:** most WHOIS
  records are redacted.
- **Tier 3, regional context and aggregators:** local news, chambers, tourism boards, regional directories.
- **Tier 4, community and long-tail:** forums, personal blogs, crowdsourced reviews.
- **Tier 5, noise:** parked domains, link farms, thin pages (under 100 words with a high outbound-link ratio): dropped
  before Phase 2, ✚ but logged in the page log.
- **Dossier** (`source_dossiers`, ✎ extending the **source register**, decisions/0045, 0157 §7): domain, tier, coverage,
  publishing entity, credibility (0.00–1.00), terms flags, last audited. ✚ **The person's call on a site (Read regularly,
  Cite only, Ignore) and its licence override the classifier**: a cite-only source's facts can be cited, never published
  as ours.
- **Audit log** (`crawl_audit_log`, append-only): every decision with its reason code and the rule it applied.

---

## Phase 2: Contextual extraction and data segmentation (decisions/0157 §2)

### 2.0 The pre-scan: "scanner before reader"

A structural pre-scan in milliseconds before any deep extraction or token spend:
- **Signal-to-noise:** text density against boilerplate; below the threshold flags `LOW_SIGNAL` (✚ logged, not silently
  dropped).
- **Entity density:** addresses, phones, hours, schema; none on a directory page stops processing.
- **Traps:** endless calendars, pagination loops, malformed URLs.
- **Content map:** the main content blocks, so the reader parses only those.

### 2.1 Content-type dispatch and boilerplate stripping

- **PDF:** a text and table extractor keeping page numbers; ✚ a scanned PDF (no text) goes to the second tier (Textract)
  only inside its budget.
- **HTML:** strip scripts, styles, navigation, footers, headers, asides, cookie banners and hidden elements; isolate the
  main node.

### 2.2 Step 1: deterministic, code-first extraction (~80%)

- **Structured data:** JSON-LD, microdata, OpenGraph.
- **Tables and registries:** `<table>`, `<dl>`, CSV and JSON endpoints into key-value rows.
- **Patterns:** US phones (E.164), street addresses (USPS form), ISO dates, opening hours.
- **Business emails:** valid addresses in business contexts (contact pages, footers, headers, schema), including small
  businesses using personal providers. ✎ **Kept as facts; whether one shows on a public listing is ? Decision 2** (our
  rule: anything about a person is private unless they say otherwise).

### 2.3 Step 2: the narrative waterfall and long documents

Only when required fields are still empty after Step 1 and the body has prose (40+ words):
- **Tier A, keyword windows:** only the paragraphs near hours, address, admission or contact words.
- **Tier B, local NER:** a small entity tagger on the worker (✚ a bigger Railway worker; its cost goes in the register).
- **Tier C, Claude Haiku:**
  - under 4,000 tokens: the stripped text, strict JSON schema;
  - over 4,000: 3,000-token pieces with 500 overlapping, merged after;
  - the rule: "Extract only facts explicitly stated in the text. Return null for any unstated field. Do not infer."
  - ✎ The draft's "temperature 0.0" is removed: current Claude models no longer take a temperature setting; the strict
    schema and the rule do that job.
  - ✚ Inside the AI cap and the workflow's budget, counted per call.

### 2.4 Splitting lists and stamping provenance

- **List unbundling:** a page with several items (a county directory, an events calendar) becomes one candidate per item;
  an item with its own page queues that page at high priority.
- **Provenance on every fact:** `fact_id`, `entity_candidate_id`, `field_name`, `field_value`, `source_url`, `url_hash`,
  `dossier_id`, `source_tier`, `extracted_at`, `extraction_method` (`DETERMINISTIC_PARSER | REGEX_HEURISTIC | LOCAL_NER |
  LLM_HAIKU`), `raw_snippet`. ✚ Plus `licence` (from the register) and `exposure` (§3.4).

---

## Phase 3: Entity resolution, vetting and privacy

### 3.1 Normalizing candidates and the identity check

- Legal suffixes stripped for matching (kept separately); street abbreviations standardized; addresses geocoded (✚ the
  U.S. Census geocoder, free) to `[lat, lng]` and a `geohash_7` (~150 m).
- **NEXUS or single:** a name shared with records over 250 m apart becomes a **NEXUS parent with child locations**, not
  one merged record.

### 3.2 Similarity scoring and the grey-zone check

- **Match score (0.00–1.00):** name similarity (Jaro-Winkler) 0.35, address/geohash 0.35, phone or domain 0.20, category
  0.10, against records in the same `geohash_6` (~1.2 km) or the same domain.
- **Routing:**
  - ≥ 0.95: merge into the existing record.
  - < 0.60: a new record.
  - 0.60–0.95 (grey zone): the verification worker, before any person:
    1. ✎ **Our own free anchors first:** the identity record, the place store, NPI, the IRS list, Recreation.gov, the
       Census geocoder.
    2. **Google Places** (paid, about 3¢ a call, inside its paced budget): ✎ **to confirm the place exists and is open
       and to keep its place ID only**. The draft took Google's rooftop coordinates into our record, which Google's terms
       don't allow.
    3. **Company registries:** ✎ **the state registries where they can be read for free; OpenCorporates only if we
       accept its terms** (its API is paid for commercial use, or free under share-alike). ? Decision 6.
    4. Re-score: ≥ 0.95 merges with the receipt attached; still grey goes to Review with a 30-day quarantine.

### 3.3 The truth hierarchy (resolving conflicts)

✚ **A person comes first:** what an owner, a claimed business or our staff entered is never overwritten by a crawl
(0133). A crawl that disagrees raises a "check this?" on the record instead.

Then, by field:
- **Legal name, parcel, coordinates, regulatory status:** Tier 1 > Tier 2 > Tier 3.
- **Hours, menu and services, prices, event dates, direct contact:** Tier 2 (first-party) > Tier 1 > Tier 3.
- **Same tier:** `DETERMINISTIC_PARSER > REGEX_HEURISTIC > LOCAL_NER > LLM_HAIKU`; then the most recent.
- ✚ OpenStreetMap and Google never enter the hierarchy (§0.5).

### 3.4 Curation, disposition and visibility (decisions/0157 §3)

- **Privacy:**
  - **Removed before storage:** Social Security numbers, financial accounts, private non-business home addresses
    (`[REDACTED_PII]`).
  - **Business contacts:** kept as `PUBLIC_CONTACT` (personal emails per ? Decision 2).
  - **People named in news or history:** `CONTEXTUAL_ATTRIBUTION`, kept for research briefs, never in a listing's contact
    fields.
- ✎ **Protected places, three levels, not two** (0133, 0066):
  - **Public:** the managing authority invites visitors. Named, told, directions like any place.
  - **Known publicly, not open to visit:** named and told as public sources tell it; never the way in; "visit only with
    the authorization of" the nation or the land's managers.
  - **Held:** we know it exists and no public source names it. Never named or located, even to AI prompts.
  - Each record's fields split into `PUBLIC_STORY_SAFE` and `RESTRICTED_INTERNAL_VAULT` (access coordinates, gates,
    easements), the vault never sent to public endpoints or writing prompts. Anything specific to a nation is held until
    its own tools exist and it says otherwise.
- **Dispositions:**
  - **ACTIVE_PUBLIC:** ✎ **through the existing listing gate** (the score; decisions/0044, 0076; W1: one source, or a
    government page alone, can establish a listing, then vetted continuously).
  - **ACTIVE_RESEARCH_ONLY:** context and background, held for research briefs.
  - **UNIVERSAL_RESEARCH_ARCHIVE:** everything valid, kept; nothing valid is discarded.
  - **HOLD_NEEDS_RECHECK:** past its freshness (over 90 days Tier 1, over 30 days Tier 2) or a parser-drift flag; queued
    back at high priority. ✎ **Stays public with "last checked" shown, withheld only on a real sign of closure** (? Decision
    3). The draft withheld every stale record, which would empty the site whenever the crawler is paused or a source
    blocks us.

---

## Phase 4: The master catalog, knowledge graph and index

✎ **On our database: MySQL 5.7.44** (the server's health report), not 8.0. ✎ **Mapped onto what we have** (§8), not new
tables beside it.

### 4.1 Entities and identity

- **Canonical records:** listings, landmarks, outdoor places, topics and events keep their tables; ✚ one canonical
  identity per thing (the identity record, 0133) links them, with its lifecycle state.
- **Provenance and actors:** every change, merge and verification receipt names its actor (crawler, a person, an operator
  override) in the identity records and the observations store.
- **Attributes and history:** companion tables keyed by the canonical ID; history is never overwritten.

### 4.2 Spatial

- Points in `POINT` columns with a `SPATIAL INDEX` (InnoDB, supported on 5.7).
- Radius searches with `ST_Distance_Sphere` (5.7.6+) and a bounding box first.
- ✎ **No SRID 4326 geography** on 5.7: distances stay spherical math on plain points, which is accurate enough at our
  scale.

### 4.3 Edges

`entity_edges` (directional, typed): `LOCATED_IN`, `MANAGED_BY`, `PART_OF_NEXUS`, `HISTORICALLY_LINKED_TO`; ✚
`NAMED_IN` (a guide, story or journey naming a place) and `ON_LAND_OF` (a nation, 0066).

### 4.4 Text and summaries

JSON columns (5.7 supports them) for narrative pieces and flexible properties; companion text tables with `FULLTEXT`
indexes for keyword search. ✚ 5.7 has no CTEs or window functions, so graph walks stay small, bounded queries.

### 4.5 Gap analysis and the feed-forward loop

Completeness queries flag records missing key fields; each becomes a discovery target in Phase 1's frontier at high
priority. ✚ Gaps also come from searches (W12, "every search is demand"), and from topics and guides with thin coverage
(0133).

---

## Phase 5: Publishing, serving and access

### 5.1 Access and visibility

- **Public directory and feeds:** `ACTIVE_PUBLIC` records and safe summaries.
- **Internal research:** `ACTIVE_RESEARCH_ONLY` and the archive, for research briefs, guides' writing and staff tools.
- **The vault:** sensitive coordinates, access details and personal identifiers stripped from anything bound for a public
  endpoint or a writing prompt.
- **Permissions:** `Access::can()` / `Modules::canTool()` (the admin's roles), never a parallel system.

### 5.2 Queries and spatial filtering

- Spatial endpoints on bounding box plus `ST_Distance_Sphere`.
- ✎ **Hybrid semantic search: ? Decision 4.** MySQL 5.7 has no vector search, so semantic search needs an outside
  service, at a cost and under its own budget. The alternative is to keep improving what search already does (our index as
  the dictionary, senses from context, phrases; 0157 §4).

### 5.3 Provenance shown

Every served record carries its source tier, last checked time and confidence; ✚ OpenStreetMap credited where it's shown
(0065); a cite-only source shown as a citation, never as our words.

### 5.4 Writing from the catalog (✚ added)

Guides, journeys, live events, how-tos and stories (W4, W5, W7, W10, W11, W13) are written only from `PUBLIC_STORY_SAFE`
facts and vetted research, behind the rule-based gates and the protected-information standard; freshness shown.

---

## Phase 6: Lifecycle maintenance and self-healing

### 6.1 Background workers

- **Freshness sweeper:** stale records to `HOLD_NEEDS_RECHECK` (shown with "last checked", §3.4) and their URLs to the
  frontier at high priority.
- **Quarantine worker:** re-checks grey-zone records when their 30 days end (free anchors first, then paid within budget).
- **Canary for parser drift:** a site paused for drift is dry-run after 7 days and unpaused only if the new rules work.
  ✚ Feeds the **"returns empty"** Pulse case (0163: one case updated hourly, a step per task or service).
- **Credibility balancer:** a site's credibility falls on failures and climbs back after clean batches.
- ✚ **Service return:** a held service returns by itself when its status check answers normally, at half budget for 14
  days (live).

### 6.2 Splitting and the audit trail

`SPLIT_CANONICAL`, triggered by an operator, separates a wrong merge into distinct records, keeping all history. ✚ It
also undoes the edges and NEXUS links the merge created.

### 6.3 Operations (✚ added; `decisions/0163`)

The switches; the service register (caps, paces, our count beside the provider's, status with reason); costs per service,
workflow and month against revenue; the workflow board and task schedule; warnings at 50%, 75% and 95%; the site watching
Railway and the sync.

---

## 7. The workflows through the phases (✚ added)

| Workflow | Phases | Notes |
|---|---|---|
| W1 Listings | 1–4, 5.1 | the frontier and registry finds; the listing gate |
| W2 Keeping listings current | 6 (via 1–3) | freshness sweeper; score decides closure |
| W3 Source register and place store | 1, 2 | the dossier engine; the person's call on each site |
| W4 Place guides, W5 Guides on demand | 4, 5.4 | written from public-safe facts and research |
| W6 Heritage and topics | 2–4 | Library of Congress; the topic index |
| W7 Journeys | 5.4 | from a guide's places; openrouteservice |
| W8 Find it | 3, 4 | names in guides resolved against the catalog |
| W9 Landmarks and places on demand | 1–4 | the three protected levels; nations' land |
| W10 Live events, W11 How-tos | 1–5 | official pages first |
| W12 Search learning | 4.5 | searches feed the frontier |
| W13 Stories | 5.4 | person-written; names to Find it |

## 8. Mapping onto what exists (✚ added)

| The draft's name | What we have today |
|---|---|
| `crawl_frontier` | the page log (0157 §1) and the crawl queues (`crawl_jobs`, `town_crawl_jobs`, `crawl_requests`) |
| `restricted_registry` | new, joined to the source register's Ignore and the page log |
| `source_dossiers` | the source register (`reference_sources`), extended with tier and credibility |
| `crawl_audit_log` | new, append-only |
| `extracted_items` with provenance | the place store (`source_facts`), the raw index (`crawler_raw_index`), the observations store (0133) |
| `entities` and the identity database | listings, landmarks, outdoor places, topics, linked by identity records (0133) |
| the manual review queue | Review and Listing Intake |
| the API verification worker | the second look (`second_look`, `identity`, `places` tasks) |

## 9. Decisions for Jason

1. **Page copies:** keep a fingerprint and the quoted snippet only (suggested), or store whole pages (where, at what
   cost, and how cite-only sources' terms are kept)?
2. **Personal emails** found on business contact pages: show them on listings, keep them internal, or show only once the
   owner confirms (suggested)?
3. **Stale records:** stay public with "last checked" (suggested), or are withheld?
4. **Semantic search:** later, under its own budget (suggested), or now?
5. **Where this lives:** an amendment replacing 0133's six phases (0133 stays the rule book), with 0163 as operations
   underneath (suggested)?
6. **OpenCorporates:** accept its terms (paid, or free with share-alike), or use only the state registries and our free
   anchors (suggested)?

## 10. Next

Once §9 is settled: this becomes the amendment to 0133; 0163 is updated to match; Jason approves both; then the build is
planned in stages (each a release he approves), starting with what the switches and the service check already give us.
The crawler stays paused until then.
