# ADR 0164: The Knowledge Engine: rule book, phases, workflows and sources

**Status:** Accepted (Jason, 2026-11-14: "all approved", with §14 settled the same day). **No shadow mode** (Jason,
2026-11-14): each part goes live when switched on (0163 §9, §10), never run alongside first. Written from his Decision 5 (2026-11-14, via the session
manager): "the rule book is essential to the new doc. so let's move it into the new adr and close and point old adrs to
this new one where ever its previous info aligns within the new doc." It **takes over `decisions/0133`** (the crawler's
one rule book, now marked superseded and kept as history) and builds on the draft
`research/knowledge-engine-architecture-2026-11-14.md` (Jason's reworked "PHASE 1 – 6: Knowledge Engine Crawler
Architecture", 2026-11-14) with every decision he made on it applied (§9). **`decisions/0163` (operations) stays a
separate record underneath it** (§10). Where an older record disagrees with this one, this one rules; the disagreements
found while writing it are listed in §12, settled by Jason (2026-11-14). **One home per fact** (Jason, 2026-11-14):
numbers and operating settings (caps, budgets, paces, warning levels, the AI cap, Anthropic's and Gemini's settings, the
switches, the switch-on check, the collaborative score's weights) live only in `decisions/0163`; this record holds the rules about data, sources, truth,
phases, workflows, establishing listings, privacy and protected places, and points to 0163 wherever a number is needed. **Amended the same day** by Jason's
decisions on the gaps review (`research/knowledge-engine-gap-review-2026-11-14.md`) and the rural test's Run 2
(`research/rural-test-2026-11-14.md` §8), applied in place and listed in §15.

**How to read it.** The rule book (R1–R19) holds every rule 0133 set, kept in substance. §0 to §8 follow the draft's
numbering: the foundations (§0), the six phases (§1 to §6, with the draft's subsections: lead sources are §1.5,
establishing a business without a website is §3.5), the workflows through the phases (§7) and the mapping onto what
exists (§8). How each 0133 rule was built (file names, jobs, migrations) stays in 0133's "What changes in the code" and
"Build order" sections; this record doesn't repeat it. "Built" below means built by 2026-11-14; the rest is decided and
to build, in stages Jason approves (§13).

---

## How the Knowledge Engine runs, start to finish

Read this first; the sections it points to hold the detail. Every step runs only when its switches are on (master,
workflow, task, service: 0163 §9), and every outside call passes the service check and its budget (0163 §1, §4). The
rules are here; the numbers are in 0163 (and the data gates in R10).

1. **Found** (Phase 1). A source enters the source register (R9, §1.4) from a seed, a link on a page we read, a lead
   (§1.5), a search (W12, §4.5) or W14's staging (§7.1); its address is normalized into the frontier (§1.1).
2. **Read** (§1.2, §1.3). Switches, the restricted registry, robots.txt and non-public paths are checked first; the page
   is fetched politely (R18); we keep its fingerprint and the facts with their quoted snippets, never the page.
3. **Classified** (§1.4). A tier (1–5) and a dossier; a person's call on the site and its licence override the machine.
4. **Extracted** (Phase 2). The pre-scan, code first (§2.2), AI only for prose still needed (§2.3: public pages, private
   details removed first, to Gemini's free tier to read, never to find; a person's words only to Claude, R5; §0.8); every fact stamped with its source, licence and exposure
   (§2.4).
5. **Resolved and vetted** (Phase 3). Matched against what we hold (§3.1, §3.2; the grey zone tries our free anchors,
   then Google Places); conflicts settled by the truth hierarchy, a person first (§3.3, R4); privacy, tier
   and disposition set (§3.4, R6–R8); a business **established** once it passes the "real business" floor, with the
   confidence its sources give it (§3.5, R12).
6. **Catalogued** (Phase 4). One identity record per thing (R11), on the map, linked into the Topic index (R15); gaps
   go back to step 1 (§4.5).
7. **Published** (Phase 5). Listings when **ready** (§5.4: real, complete, appropriate, unique), at whatever confidence
   they have; guides and Find it by
   their scores (R10); the scrub (R6, R7); sources and freshness shown (§5.3).
8. **Kept fresh** (Phase 6). Clocks and events send it back through steps 1–5 (§6.1): Refresh, Renew or Review.
   **Nothing waits in Review alone:** every item for a person is a step in a Pulse case, and the engine keeps retrying on
   the retry ladder meanwhile (§6.1); only a site that blocks us waits for a person's **Reactivate** (§1.2, 5).

The same path for six cases:
- **A business with a website:** W1 finds it (a lead or a link) → its site reads as Tier 2 → code takes the name,
  phone, address and hours → matched or new → established by its own website (§3.5, 1) → published when ready → W2 keeps
  it current on R12's cadence; a dead site lowers its confidence, never closes it (§1.3, R12).
- **A business with only a licence:** W14 has staged the state's registry, switched off (§7.1) → the hub opens and the
  registry is switched on after 0163 §10's switch-on check (R17) → the registry is Tier 1 → a match on name and address
  establishes it alone (§3.5; §12, C2–C4) → it shows the licence's facts with the source and "Not yet confirmed by the
  business" → a lapsed licence is recorded and lowers its confidence, and "Still open?" asks (R12); it is never labelled
  Closed on the licence's word.
- **A business found by open data and witnesses:** a lead from an open place dataset (Overture first), Google Places' check, a Facebook
  page, a chamber listing or OpenStreetMap (§1.5; Gemini is never a finder, §0.8) → it passes the floor when it
  has a real business name and an address or phone we may keep (§3.5) → it is public at once, at the confidence its
  independent sources give it through the collaborative score (weights: 0163 §12) → "Not yet confirmed by the
  business", with an invitation to claim; claiming and more agreeing sources raise it (§12, C1).
- **A heritage fact:** W6 from archives, newspapers and registers (Phase 1's pillar seeds) → the pillars' windows
  (§2.3) → catalogued on a primary record or two independent sources (§3.2, R10) → its tier set (R7, R8) → told in
  guides only from public-story-safe facts; its topic refreshed when its facts change (§6.1).
- **A guide:** W4 (or W5 on demand) → written by Claude from the catalog only (§5.4, R13) → the scrub → publishes itself
  at R10's guide score → renewed every 30 days (§6.1), inside the AI cap (0163 §7).
- **A new state's hub:** W14 finds, reads the terms of, tests and stages the state's authority sources, switched off
  (§7.1) → the state's jurisdiction record is reviewed (R8) → Planned → Seeding → Opening budget → Soft open → Open
  (R17; a director moves each stage) → its staged sources switch on only once 0163 §10's switch-on check, reading the
  hub meter's line for them, shows them still valid and their readers passing.

Three different checks: a **listing is ready** to publish (§5.4); a **hub's readiness meter** tracks its targets and
budget (R17), W14's staged sources being one of its lines; the **switch-on check** (0163 §10) says whether something can
be switched on safely, and reads the hub meter for a hub's sources.

---

## The rule book

### R1. The principle: we build our own record of the open web

The crawler exists so a small region's information doesn't depend on Google, Yelp or any gatekeeper that captures the
web and rents it back. We build our own index of the open sources (places' own websites, governments, public registries,
archives, museums, historical societies), our own verified facts with their sources, and our own catalog of the human
and natural record. We use outside services while we grow, but:
- **Every outside lookup is a bridge, not a foundation.** Paid search, Google and AI web search fill gaps only after our
  own sources are asked, and every useful page they turn up becomes a source we read ourselves next time.
- **We keep the facts and their sources, never the gatekeeper's content** (R9).
- **We measure independence:** the share of verifications and guide facts settled from our own index and the open
  sources, without a paid lookup. It should rise every quarter; Outside services reports it (0152, 0163).
- **The record is ours to keep:** sources, facts and catalog live in our database, survive any provider leaving, and are
  reused by every page, guide and story.

### R2. Curators, not controllers

Traversence curates the record; it doesn't control what people may know. **No topic is left out because it's
sensitive, uncomfortable or contested** (conflict and violence in a place's history, faith, politics, crime, health and
addiction, land and water disputes, a nation's history). What we manage is **exposure and representation**:
- **The topic is told; specific details are protected.** The protected tiers (R7) limit only particular details (a
  site's location, a private person's identity, a shelter's address). The topic and its story stay.
- **Fair representation:** credible sources from more than one side; where they disagree we say so and attribute each
  account; disputed facts are marked disputed; the editorial voice describes, it doesn't take sides.
- **Communities speak for themselves:** a nation's or community's own account is presented in its own terms and
  credited, beside the other sources, never replaced by ours.
- **Context and care:** a short note before distressing material; adult topics behind the site's 18+ settings; help
  close at hand where a topic touches a need (help lines with mental health and addiction topics).
- **Sources shown:** every published piece shows where its facts came from.
- **Corrections and a right of reply:** anyone can flag a misrepresentation; a community or nation can add its account.
  A takedown happens for law, safety or a private person's privacy, never because a topic is uncomfortable, and even
  then the topic stays with what can be said.
- **The Chameleon Filter delivers; it doesn't suppress:** sensitive topics are indexed and delivered by context like any
  other; nothing is ranked down or hidden for being sensitive (0132).
- **Our index answers any search** (0157 §7): our mission shapes the order and how it's shown, not what can be found,
  within three limits: the licence, nations' sacred and sensitive places (0066), and private details about people.

### R3. What the crawler gathers, and the two pillars

- **The crawler's scope is research identification, and it is broad:** every open source with research value in the
  areas we cover, whatever the subject (services and help lines, health, education, civic and government, public safety,
  businesses and organizations, events, transport and infrastructure, land, water, weather and environment, history,
  culture, faith and the arts), including sources useful later. Breadth is bounded only by the guardrails (public,
  licensed, polite, within budget), never by topic.
- **The two pillars are the content focus** (what Phase 2 extracts first for stories, how Phase 5 writes); they shape
  the narrative, never narrow the gathering:
  - **The Human Journey:** people moving, trading, building and keeping a life on this land across generations.
  - **The Living Landscape:** the land that shaped them: landforms, water, timber and stone, weather and seasons,
    wildlife, the old routes today's roads follow.
- Every guide shows how the two shaped each other.
- **Phase 1 is always-on automation** once switched on (0163 §9): fast, efficient and always working in small batches (W0, §1.4's note), gathering within the guardrails with no content settings. **Phase 2 and the later phases run on their own schedules or on demand** (Jason, 2026-11-17: "Phase 2 and the others can run on their own schedules or demand calls so we do[n't] bog down the crawler/railway/or host"), each within its budget;
  **configuration starts at Phase 3**, where research is shaped (coverage by area and theme, depth, the thresholds).

### R4. Provenance and ownership: never overwrite what a person entered

Every fact and field records its source and who set it (crawler, staff, owner, member). The crawler writes only to its
own fields, and only empty or crawler-owned ones; **a field a person entered or locked is never overwritten**; a claimed
listing is only offered a change (evidence wins, unless claimed: 0062). A crawl that disagrees with a person raises a
"check this?" (a tip or a Review item), never an edit. The item is a step in the engine's Pulse case (§6.1); on a
claimed listing it also goes **to the owner**: the business portal's **Check these** (each offered change with its
source: Accept / Keep mine) and a system email the day it's raised; the owner's answer settles the step (P3-5).

### R5. AI acts on a person's words only when called

AI writes freely only content the system owns (guides and stories it compiled, marked as compiled). On a person's own
words it acts **only when that person calls it** (Write, Rewrite, Assist, Check my facts): the result comes back as a
suggestion they accept or reject, never applied silently, and the call is logged. A member's words go to AI only with
their AI & Learning consent (0051), and **only to Claude** (never to Gemini's free tier, §0.8). Maintenance may flag a
person's content as possibly out of date; it never edits it. Fact tips on a person's guide sections compare their words
with the facts we hold and show a tip with its source; nothing is ever rewritten.

### R6. People

- **Public figures** (officials, authors, artists, founders, business owners in their business role, anyone known for
  public acts): named, with their public deeds.
- **Historical figures** (documented in published history): named and their story told, with respect; living
  descendants' private details are never published.
- **Private individuals** (anyone appearing only incidentally: census rows, deeds, court and cemetery records, notices,
  photos): not named, no profile, unless the family consents or a published history already tells their story. Anyone
  who may be living (born within about 100 years) is private unless a public figure. Living people's addresses, health
  and legal matters, and minors, are never published.
- No identities are built for people who merely appear in data (0062).
- People named in news or history are kept as contextual attribution for research, never in a listing's contact fields
  (§3.4).

### R7. Protected places: "tell the story, never the way in"

The standard protects details, never topics. **Three levels** (Jason, 2026-10-30):
- **Public:** the managing authority invites visitors: named, told, directions like any place (a town's pioneer cemetery
  included).
- **Known publicly, not open to visit:** named and told as public sources present it; never the way in; "visit only
  with the authorization of" the nation or the land's managers, and who grants it when that's public.
- **Held:** we know it exists and no public source names it: never named or located, even to AI prompts; a guide may
  say the area holds sacred places that aren't for visiting without permission, and whose.

Every catalog fact carries a tier set in Phase 3 and enforced in Phase 5:

| Tier | Published | Applies to |
| --- | --- | --- |
| **Public** | freely | public places, businesses, public history |
| **Story only** | the name and story; never location, pin, coordinates, directions or access, except an access point the managing authority offers publicly (a state park's rock-art tour) | sacred, burial and archaeological sites a public source already presents; private property (only public viewpoints or tours); sensitive wildlife (nests, dens, rare plants) |
| **Restricted** | never; kept for our records only | what a nation restricts; site locations protected by law in that place's jurisdiction (R8); domestic-violence shelters' addresses; private individuals' details |

- A sacred, burial or protected place is **held** by default; it moves to story only when a public source presents it
  to visitors and our crawler confirms that, and never when a nation has closed or restricted it.
- **Landmarks have the story-only level too** (Jason, 2026-11-14, P5-3): a landmark is shown, story only, waiting,
  closed or hidden. **A Wikipedia article alone moves a held place only to story only** (named and told; no pin, no
  coordinates in any public endpoint, the courtesy line); it is **fully public only when the managing authority's own
  page says it's open to visitors**. Archaeological words (ruin, rock art, petroglyph, pictograph, cliff dwelling,
  archaeological site) are held like the sacred and burials. A guide naming a held landmark (Find it) moves it no
  further than this.
- Story-only places carry a fixed courtesy line (look, don't touch, follow the nation's rules) and no pin.
- Each record's fields split into **public-story-safe** and the **restricted internal vault** (access coordinates,
  gates, easements); the vault is never sent to a public endpoint or a writing prompt.
- **Tribal nations' land (0066):** boundaries, public listings and publicly known places are public, with the nation's
  name and visitor rules; anything specific to a nation is held until the nation's own tools exist and it says
  otherwise; "closed by the nation" reopens only at its request; pins on a nation's land move to a public place; the
  data is never deleted. A nation's sites are always a person's call in the source register (0157 §7). Decisions about
  nations' land and sensitive sites are never made by a machine.
- **Confidential listings** are never sent to outside services in verification, and Find it never looks up a
  confidential name. The guard sits once in the Gemini client and every outside lookup, not in each workflow: a
  protected, confidential or nation-sensitive name never leaves in a prompt (T1, §0.8).
- **Limiting exposure:** every published fact keeps its source; anyone can ask for a takedown, and the item is hidden at
  once for a protected reason (a way in to a sacred, burial or archaeological place; a nation asking; someone's private
  information; a safety risk), otherwise when R10's takedown time passes without a decision; a quarterly sample audit of
  live guides (its size in R10); an attorney signs off on this standard once, and on each jurisdiction record as its region
  opens.
- **The Chameleon Filter obeys the tiers:** no tag, card or pin that locates a story-only or restricted item; tags never
  infer anything about a person.

### R8. The jurisdiction register: the law of the place

Traversence expands state by state, so the law that applies is the law of the place. Each place's rules come from a
jurisdiction record, layered:
1. **Federal baseline:** archaeological site locations kept confidential (ARPA; NHPA §304); Native American graves and
   cultural items (NAGPRA); federal privacy and children's rules.
2. **Each state and territory** (Puerto Rico, Guam, the U.S. Virgin Islands, the Northern Mariana Islands, American
   Samoa, D.C.): antiquities and burial-site statutes, unmarked graves, privacy and data laws, rules on scraping and
   public records, the state historic preservation office's practice.
3. **Each tribal nation** where it publishes them: cultural-resource and sacred-site codes, visitor rules, photography
   and research permits. A nation's rule outranks ours on its land, and a nation that hasn't published one is treated as
   restricting what it hasn't made public.
4. **Other countries** as the continental plan reaches them, added the same way.

Each record holds the jurisdiction, its laws (name, citation, what it protects, what it requires of us), its effect on
our tiers and sources, the reviewer and date. **A region opens only after its records are reviewed** (an attorney signs
each new state's or territory's record once); **where a record is missing or unclear, the strictest known rule applies**
(restricted until reviewed). In a closed region a guide doesn't go live and history isn't gathered. Where a record limits
reading websites, an official site is kept as a pointer only unless a person set that site's rules to keep its facts.

### R9. The licence decides the use: the source register and owning our data

Every source is a record; **its licence decides what we may do with its facts**, and the licence travels with the fact.

| Field | What it holds |
| --- | --- |
| Kind | website, official site, registry, bulk file, API, archive, museum, historical society, open place dataset |
| Licence | public domain, open (ODbL, CC BY-SA, CC BY, CC0), terms-restricted (Google, commercial directories), unknown |
| Use | store facts · keep a pointer only · cite only · evidence only (may confirm, never supply) · show with credit |
| Credit line | the attribution to show, when the licence asks |
| Status | read regularly · cite only · ignore · suggested |
| Trust | tier (§1.4), credibility, how often borne out or shown wrong (0157 §5) |
| Rate limit, cap, refresh cadence | per source |
| Terms checked | the date and the page read |

**We store only what we may own.** Anything whose terms would stop us owning our record is a reference: a lead that
tells the crawler where to look; the business's own site, a registry or a public record is what we store and cite.

| Source | Use |
|---|---|
| OpenStreetMap (ODbL, share-alike) | its own layer (0065): a lead and a map link, credited, never a stored value we show in our records; within its layer, show all it gives |
| Google | its place ID, check date and match only, as a link; its hours and status prompt a check; "Google verifies and links; it doesn't fill in listings" |
| Facebook, Instagram, Yelp, MapQuest, BBB, Yellow Pages, Healthgrades and other commercial directories | links and citations; nothing our crawler reads from them is stored as our value; a NAP item from a business's Facebook page may be used through Overture's Meta record or a person's entry citing the page (R14) |
| Wikipedia (CC BY-SA) | cite only: linked, never copied or read in bulk |
| Wikidata (CC0); Commons photos (CC0, public domain, CC BY, CC BY-SA, with credit) | owned facts; photos with credit |
| The business's own site; government registries (NPI, IRS, state and county); the Census | owned: stored with the source (the IRS file: see §12, C2) |
| ✚ Licence registries (state company, sales-tax, liquor, contractor, health-permit and licensing-board records) | owned public records: the facts they state, with the source, once each source's terms are checked (§1.5) |
| ✚ Open place datasets (Overture Maps Places, Foursquare's open places data) | corroboration, stored and shown with credit as their licences allow, **once their licences are confirmed** (Decision 9) |
| The owner, staff (a call, a visit), a postcard code, a visitor's confirmation | owned: our own evidence |
| Claude's and Gemini's results | ours to keep, but **never evidence**: what an AI finds leads the crawler to a page, and the page is the source |

- A link (`evidence` kind) is never a stored fact and never in the truth hierarchy; witnesses add weight only through
  §3.5's collaborative score, which feeds the listing's confidence (Decision 8; §12, C1).
- A bulk dataset publishes nothing by itself: each record must pass the "real business" floor and readiness (§3.5,
  §5.4; §12, C4).
- A cite-only source's facts can be cited, never published as ours. Terms-restricted sources can't be set to store or
  show.
- A fact is cataloged only when a source whose rule lets us keep and show it backs it: cite-only, evidence-only and
  pointer-only sources may confirm, never supply.
- **A licence change follows the facts already stored** (P4-2): when a source's terms change (or an upstream's licence,
  per record, at each load), its stored facts are re-sorted; those no longer keepable become evidence only; a listing
  field resting only on them is taken off (R11: kept as taken off), else kept from its other sources; one Pulse step per
  source shows the count.
- **The register judges a site itself** (0157 §7): government, schools and official registries read regularly at once;
  commercial directories and social media cite only; chambers, tourism sites and organizations by their terms (the
  second tier, quoting the terms); a person's call always stands.
- **Terms are checked in Pulse:** each source whose terms no person has read, or not in a year, is a System item with
  our rule and a step to open its terms; **I read them** records who and when.
- **Failed crawls clear themselves where no one is needed;** one that may be the site's own failure is a Pulse item
  (crawl it again, or remove it; what it found stays).

### R10. Thresholds and gates

One table, System Configuration → Thresholds & gates, each value named by what it gates. Tuned numbers a director may
change there (kept with who and when, and a Reset); standards change only by a decision. Today's values:

| Gate | Value | |
| --- | --- | --- |
| Listing publishes | readiness rules (no score; §5) | standard |
| Listing established | the "real business" floor (§3.5): a real business name and an address or phone we may keep; then public at its confidence (R12) | standard |
| Collaborative source score | feeds confidence, no threshold; its weights are settings in 0163 §12 | standard (weights tuned in 0163) |
| Closed shown | the owner says so; our team confirms it (a call to a disconnected line, a visit, a photo of the sign, a visitor's photo our team confirms); the public confirms it (visitors' "It's closed" on 3 devices on at least 2 different days within 60 days, none "Still open" since the first; where no machine closure signal came first, our team's call confirms it before it shows); or the business's own site says so (R12) | standard (the count tuned) |
| Guide publishes itself | score 70 | tuned |
| Find it adds a listing | the floor (§3.5) + readiness; its score only orders the work (Claude: high 75 / medium 65, +5 each phone, address, website, 2+ sources; 80 first) | tuned |
| Category set without a person / a person picks | 80% / under 50% (0024); "Other" never set by itself | tuned |
| Search: published / "may also help" | 0.80 / 0.60 relevance (0093) | tuned |
| Heritage fact cataloged | a primary record or two independent sources, one of which may supply it | standard |
| Entity match (§3.2) | merge ≥ 0.95, new < 0.60, grey zone between | tuned |
| Takedown hidden within | 24 hours (at once for a protected reason) | standard |
| Takedown requests a person a day | 5 | tuned |
| Quarterly audit sample | 0.1 of live guides (5 to 40) | tuned |

AI Assist's allowances (free asks a day, a member's AI cost) moved to 0163 §7 (2026-11-14, §14 point 10).

### R11. The one record: the identity record is the observations store

Listings and content are two readers of one record of what we've seen. Every check, free or paid, by anyone, writes
what it saw once, and every part of the system reads that record before it spends anything.
- **Three layers, one direction:** `entities` is the business; the identity (`identity_facts`,
  `identity_fact_sources`) is the evidence, every value any source gave with its source and date; the listing's fields
  are the shown copy, never a source.
- **Every write is recorded as it happens,** whoever makes it; what the listing no longer says is kept as **taken off
  the listing** (never shown, never written back by the evidence), so a dead website taken off stays off.
- **Merges join the evidence,** and Undo takes exactly those back off.
- **Identities from the first sighting;** approving, merging or closing a sighting as the same moves its evidence onto
  the listing; sightings turned down keep theirs, so nothing seen is lost.
- **Counting is honest:** one website counts once, however it was found; pages on one host, and directories copying each
  other, count once.
- **The evidence changes a listing carefully:** it may fill an empty phone, address, website or email with one
  independent source; replacing a shown value takes two; a name changes only to a fuller form of the same name, never to
  a registry's legal name over the one people know; a sighting under another name at the same phone or address brings
  only where it is and how to reach it.
- **Read before paying, everywhere;** a paid answer goes back into the record for the next reader; **both directions:**
  guides' research feeds listings, a business's own site feeds events and guides, an event at a venue is evidence the
  venue is active.
- The cost log notes what each paid call produced. It lives in the main database (0110 doesn't move the identity
  tables). Review shows a sighting's identity card.

### R12. Listing confidence, claims, closures and upkeep

- **Confidence, not restriction** (Jason, 2026-11-14: "scores are present that way the listing can be claimed and the
  score will naturally improve. so we dont restrict listings we add confidence to them"). **Confidence (0 to 100) only
  orders work and shows how sure we are;** a listing that passes the "real business" floor (§3.5) is public at whatever
  confidence it has; publishing hangs on readiness (§5), never on a number. Claiming and agreeing sources raise it.
- **Weights** (tuned): our team and the owner strongest; an official registry strong; the business's own live website
  with its phone and address strong; open data medium; independent pages medium; the **collaborative source score**
  (§3.5; weights in 0163 §12) for agreeing sources that aren't stored facts; visitors' "Is this right? Yes" small each,
  counted per device a day, never who. **After establishing, Google's open or closed counts only as a signal** (a Google
  "not found" is weak); Facebook and OpenStreetMap don't count at all (§12, C1). Disagreements lower it (a dead website,
  an address mismatch, a closure signal, a likely duplicate). **Evidence ages.**
- **Our own data and people count most:** many rural businesses have no website; **having no website or online record
  never counts against a listing** (Jason, 2026-11-01; §0.7).
- **State business and licence registries only add:** a record found raises the score; not found never lowers it (many
  rural sole proprietors are never registered, and a business on a nation's land is licensed by the nation, whose
  register comes first); lapsed, dissolved or revoked is recorded and lowers it (more for dissolved or revoked), with
  "Still open?" asked; never a Closed label on its own. A dissolved company is not a closed business (sole proprietors
  often carry on), which is why it asks rather than closes (F1).
- **Green means a person stands behind it:** machine evidence alone tops out at 75; our team's check or the owner's
  claim lifts it (80 to 89; a verified claim 90 to 99). **A claim keeps its band** (P3-4): evidence against it shows as
  a caution line and lowers it within the band; only a claim found false, or Closed, leaves the band. Each item against
  is labelled with its true source ("Its own website says it closed", "A visitor reported …").
- **A claim is a claim:** any claim puts a listing in the top band, Claimed by owner; the ✓ follows once the owner's link
  is proved; a claim found false drops it back to what its evidence earns.
- **On the card:** the ring and **How we know** (each piece of evidence and its date, Is this right? Yes / No per
  detail); the score never rests on colour alone.
- **Curators, not regulators: closed or moved** (Jason, 2026-11-14: "we document for accuracy and use vetted
  information to make good decisions… let the public or owner make that ultimate call before we label things like this.
  Safe and caution verse restriction"). A lapsed, dissolved or revoked licence, a dataset dropping a place, Google
  reporting it closed, or one site saying "closed" **never labels a listing Closed**: the fact is recorded and shown with
  its source where it helps ("Arizona's sales-tax licence lapsed, Oct 2026"), confidence drops (more for dissolved or
  revoked; 0163 §12), and a **"Still open?"** prompt asks visitors (and the owner, if claimed). **Closed** shows only when
  the owner says so, our team confirms it (a call, a visit, a photo of the sign), the public confirms it (R10), or the
  business's own site says so; it is still found by name,
  out of browse lists. **Moved** shows with the new address when known, never applied on its own. Listings with
  closure signals sort by their lower confidence, never hidden.
- **Three states on the card, no others** (F3): **Open**; **Caution**, "Still open?" with the signal's own line and
  source ("Arizona's sales-tax licence lapsed, Oct 2026"; "Its own website says it closed"); **Closed**, found by name,
  out of browse lists, not scored down (it's Closed). "Reported closed" and "Possibly closed" are retired as labels.
- **"Still open?"** (P5-2, built with W2): "Yes / It's closed" on a listing with a signal, counted per device a day,
  never who. Two closure signals from different origins, or 30 days with a signal and fewer answers than R10's count,
  put a step on the Call list (or in the engine's Pulse case when it has no phone), so a listing few people open never
  sits in caution for good. A claimed listing's owner is told of a Closed and can undo it.
- **Signals against** (F1; their values in 0163 §12) count once per origin, age like other evidence, and clear when
  their source reverses (a licence renewed, the record back in the dataset) or the owner or the public says still open.
- **Upkeep:** automatic checks monthly below 51, quarterly at 51 and above, **never more than 90 days apart**; anything
  that changes starts a check at once; the owner's "Still right?" email twice a year; full re-verification (registries,
  licences) yearly.
- **Less work for people:** machines decide when the evidence is overwhelming (the same Google place ID and the same
  phone is an automatic merge), every automatic decision reversible; Review ordered by value; paid checks where they move
  the score; Google's answers used in full (shared place IDs mark duplicates; a different street or town is a "Moved or
  wrong address?" item; repeated not-found goes on a low-priority list, never removed on Google's word).
- **The Call list:** every call to a business is also a check. **Listing check** is a Content template (its sources
  free first, cadence per band, budget). **Proving the return:** a monthly Listing quality card and a spot-checked sample
  per band to tune the weights.

### R13. AI's role: reader, researcher, writer, helper; never the source

- **Reader:** turns what the crawler fetches into structured lines for the record; a page is read once and stored.
  Public pages, with R6's private details removed first, go to Gemini's free tier first; anything from a person or
  private goes only to Claude (§0.8, §2.3, Decision 7).
- **Researcher (Claude with web search; Gemini never, unless Jason funds grounded search, §0.8):** fills gaps only, after the record and the
  free sources come up short; what it finds goes into the record.
- **Writer (Claude):** guides, summaries, AI Assist answers and event descriptions, **only from the record**, through the
  same scrub and gates as everything else.
- **Helper to people:** on request (Check my facts) and Review pre-sorting (a recommended answer a person agrees with or
  corrects).
- **An AI's word is never evidence.** What it finds counts only through the pages it cites, which our crawler reads and
  records under their own sources.
- Cheapest model first, prompts shaped in Content templates with a free Preview. **Gemini follows Claude's and the paid
  APIs' standards:** each feature's switch, model and provider, runs a day and budget on AI use; a director changes a
  feature's provider after the sample comparison (§2.3); Gemini's allowance is monitored and paced so it never becomes
  unexpectedly unusable (0163 §7, §11). **Each feature declares what it carries** (public, a person's words, private);
  Gemini is refused in code for anything but public, and AI use greys that choice out with the reason (P2-3).

### R14. Social pages: links, not sources we crawl

Facebook and Instagram pages are **never crawled or scraped** (Meta's terms and its login wall), by the crawler or by an
AI. A page is kept as the listing's social link and shown on the card; its existence is **weak evidence the business
exists**, never of its hours, and it never counts as a website (0086). A web search's title and snippet showing it count
as one ordinary web source. **A NAP item from its page** (name, address, phone; Jason, 2026-11-14) **may be used** when
it comes (a) through **Overture's Meta record** (licensed, CDLA-Permissive-2.0, credited), or (b) from **a person** (the
owner, our team or a visitor) who enters it with the page's public Facebook URL as its citation ("seen on its Facebook
page"); it fills or verifies that field at that source's confidence. Our crawler still never reads facebook.com (Meta's
terms forbid automated collection). Later, an owner may connect their own Page with Facebook Login, the one way a Page feeds the record directly.

### R15. The Topic index and AI Assist

- **Every topic the research touches has a record:** its names, definition, places and footprints, facts each with its
  source and licence, related topics, and a **state of knowledge** (coverage, freshness, agreement, open questions);
  topics nest and relate and build on 0067 and 0132. A summary is written by the system from its facts and kept current.
- **AI assist writes from the topic's record,** citing what it used; where the record is thin it says so and queues the
  topic for research. Every protected tier and people rule applies. **Gaps drive the crawler,** ordered by how often
  asked for. Public topic pages are a later decision.
- **Search answers from our own knowledge** where words name a topic or ask about a place's past; restricted facts never
  show; a topic whose facts are all held back gives no answer.
- **AI Assist for members:** for members with a paid plan (free asks a day, then plan credits; a member's asks never
  cost more in a month than a share of what their plans pay: both values in 0163 §7). An ask is the member's alone; Insights shows counts and money
  only, never what anyone asked; what members ask feeds guides as subjects and counts, never words or who. The answer is
  written only from our record; an ask we can't answer is researched and followed up at our cost.

### R16. Content templates

Each kind of content the crawler and AI make (a Place guide, Live events, Listing check, Suggested journey…) is a
template people shape before anything is spent: what it applies to and when it runs, what it gathers (free sources
first), what it writes, how it's checked and published, its shelf life, where it shows and its cost. **The exposure
standard is shared and locked** (shown, changed only by a decision). Preview spends nothing; Try it saves nothing to what
runs; every save is a version. A template being shaped or switched off writes only what someone asks for. Live events
are checked (a date ahead, the announcing page, a venue matched) and a name that may mark a protected place is held for
a person.

### R17. Where the crawler works, and opening a hub

- **Bulk loads run state by state as regions open** (Arizona and New Mexico now; nothing loaded "just in case", 0048);
  crawl targets can come from searches anywhere (0066), and every city named in a search is demand (CLAUDE.md).
- **Opening a hub, no cold start:** Planned (laws reviewed; free sources set) → Seeding (free bulk sources; leads checked
  at the business's own site, a registry, or established by the floor and its sources (§3.5) before values are kept;
  nothing publishes unless ready) → Opening budget (a one-time allowance, separate from upkeep, highest value first) →
  Soft open (readiness targets met; marked as a new area) → Open. A thin record starts gathering, never a failure; each
  hub makes the next cheaper; a hub readiness meter shows the targets, the budget and W14's staged sources. **A director
  moves each stage** on the meter; **a state's first hub stays Planned until that state's R8 record is signed.** Each step that needs a person in a
  hub's life (review its law, move it on when its targets are met, decide on a nation's sources) is a step, as a button,
  in one Pulse case, **Hubs** (H4). The
  targets are gates in R10; the opening budget is set in 0163 §2 and shown in the register.
- **Authority sources staged ahead of every hub** (Jason, 2026-11-14): W14 finds and stages each state's licence and
  registry sources in the source register before any hub there is planned, so **Planned** starts with them already
  known, their terms read and their readers tested. Opening a hub switches that state's staged sources on (after the
  switch-on check, 0163 §10); nothing is bulk-loaded before its hub opens (0048 stands). **For a hub already open**
  (Arizona's and New Mexico's), a newly staged source switches on by itself from its card once the switch-on check's hub
  line passes for it (H3).

### R18. Fetching guardrails (fixed, not configured)

Public, unauthenticated pages only; robots.txt obeyed, including `Crawl-delay`; a polite pace (1.5 s at least; a site
asking for more than 30 s left alone); every network hop checked against private addresses; terms and licence checked
and recorded before a source is read regularly; never a gated group or private forum; 401/403/451 never tried another
way. **Restricted sources** (cultural-resource reports marked restricted, anything a nation keeps to itself) are never
gathered.

### R19. Spending

Nothing spends Traversence money unless revenue covers it ("no income, no expense", CLAUDE.md), except the research
exception: Claude writing guides and journeys and the research and development tools that feed them, inside the AI
monthly cap (0131, 0147, 0160 §5; its value in 0163 §7). **Every call's cost is counted, failed calls included,** so the
cap holds. Gemini is used on its free tier only (Decision 7; how it's run: 0163 §11). Caps, paces, warnings, the cost ledgers and the
switches are the operations layer (0163, §10).

---

## 0. Foundations: what every phase stands on

1. **The rule book above** applies in every phase.
2. **Operations underneath every phase (0163):** every outside call passes the switches (master, workflow, task,
   service; live 2026-11-14), the service check (`ServiceHealth`: holds, back-off, automatic return), its budget and
   pace, and is counted in the cost ledger, failed calls included.
3. **Where each part runs:**
   - **The crawler worker** (Railway, Python): Phase 1 fetching, Phase 2 extraction, local NER.
   - **The website** (Bluehost, PHP 8.0, **MySQL 5.7.44**): the frontier and registry tables, Phase 3 resolution and
     vetting, Phase 4 catalog, Phase 5 serving, Phase 6 sweeps (scheduled jobs), the admin and Pulse.
4. **Build on what's there:** each phase maps onto existing tables and libraries (§8) and extends them; nothing is
   rebuilt beside them.
5. **Layers kept apart:** OpenStreetMap is its own layer (0065): it points us to a place, is credited, and is never
   copied into our records. Google's terms let us keep only its place ID. Neither is a source of facts for our catalog.
6. **Protected places and tribal nations:** R7's three levels and 0066 apply in every phase.
7. **Rural reality: a business doesn't need its own website to be real** (Jason, 2026-11-14). Many real businesses in
   our towns have only a licence, a Google or Facebook presence, a sign. A listing is established by **authority** (a
   government licence or registry), by **corroboration** (independent sources that agree), or by its owner, never only
   by having a website (§1.5, §3.5; Decision 8).
8. **Two AI providers, each for what it's best at** (Decision 7, as revised by the rural test's Run 2, §15): Gemini's
   free tier (Google AI Studio, no billing linked) **reads public pages only** (extraction); **it is never a finder**.
   The free tier has no Google grounding (Run 2: 429 on every current Flash model; 2.5-flash closed to new users), and
   ungrounded Gemini was unreliable (55% of the businesses it named confirmed, about 15% of its phones right, numbers
   made up), so its word is never a lead and weighs 0. **Grounded search only if Jason funds it**, as a paid feature
   under "no income, no expense" (R19). Claude writes in our voice and handles anything holding a person's own words or
   private data. The free tier lets Google use what we send, so **nothing private ever goes to it**, enforced once, in
   the Gemini client (not per workflow):
   - **"public" means a public page with R6's private details removed first** (§3.4's list, plus the people rule: names
     beside birth, death, court or address words), so that removal runs at the start of Phase 2 as well as in Phase 3
     (P2-1). Until W6's sources have that pass, W6 and every archive, newspaper and registry feature stay on Claude;
   - **what goes to it is built by the system from our own vocabulary** (a kind from `OpenPlaces::KINDS`, a town from
     `zip_coordinates`, a name from a guide the system wrote), never a person's words: a visitor's search words and the
     names in a member's story go only to Claude, with consent (P2-2, R5);
   - **a protected, confidential or nation-sensitive name** (`Landmarks::isProtected`, `ListingSafety::isConfidential`,
     `Landmarks::NATION_SENSITIVE_CLASSES`) never leaves in a prompt (T1, R7);
   - each AI feature declares what it carries, and Gemini is refused for anything but public (P2-3, R13).

   It is run to the same standards as Claude and the paid APIs (R13). When its daily allowance runs out, the work
   **waits** (Decision 7; run as 0163 §11, which keeps a reserve so it doesn't run out unexpectedly), as a page "waiting
   to be read" (§1.1), never lost.

---

## Phase 1: Wide discovery, restricted registry and source classification

Find and catalog every repository of real information in the areas we cover (R3), and keep them in the source register.
**Seeds, broad:** places' own websites; city, county, state, tribal and federal sites; school, health, transit and
utility sites; chambers, museums, libraries, newspapers and event calendars. **Seeds for the pillars, deep:** public
registries (NPI, IRS exempt organizations, Recreation.gov, GNIS, Census); newspaper archives (Arizona Memory Project,
the Library of Congress, Chronicling America); National Register nominations; BLM land surveys; state library and
university special collections; oral histories; county historical societies; museums; water and irrigation records.
**Order:** our own sources first, then the place's own site and official registries, then the owner, then outside
search only for what's still missing. Every useful page any step turns up is offered to the register.

### 1.1 URL frontier, normalization and re-verification queue

Every discovered link (`<a href>`, `<link rel="canonical">`, sitemap `<loc>`), and every re-crawl task sent back by
later phases, is normalized before queueing:
- **Canonicalization:** lowercase scheme and host; strip fragments; strip tracking parameters (`utm_*`, `fbclid`,
  `gclid`, `ref`); resolve relative paths; sort remaining query keys; strip trailing slashes except on the root.
- **Deduplication and priority:** `url_hash = SHA256(normalized_url)`, checked against the frontier and the restricted
  registry before insert. Inserts from a Phase 3 recheck or a parser-drift flag get high priority.
  *Note (Jason, 2026-11-17, W0):* the key is `sha1(normalized_url)`, the page log's own `url_hash`, so a frontier row and
  its page log row join; SHA-256 bought nothing here. Built in `app/lib/crawler/Frontier.php` (`crawl_frontier`, junk in
  `crawl_junk`); the restricted registry is the register's Ignore, junk records and skip rules (`crawl_skip_rules`).
- **States:** `PENDING → IN_FLIGHT → FETCHED | PENDING_EXTRACT | RESTRICTED | HELD_BLOCKED | FAILED_TRANSIENT | GONE`.
- **Waiting to be read** (`PENDING_EXTRACT`, P1-1): set when Phase 2 stops short (Gemini's allowance, or the second
  tier's budget, used). A page in it is re-fetched without `If-None-Match`/`If-Modified-Since`, and its fingerprint is
  saved only once its facts are, so a 304 never hides a page we haven't read. Decision 1 stands (no page bodies kept).
- **Per-host and per-workflow shares:** the frontier hands out work inside each workflow's share of a run
  (`CrawlService::plan`, 0160 §2), so one site or workflow can't starve the others; work not done goes back unchanged,
  never counted as a failure or a result.
- **Built on the page log** (0157 §1): the frontier extends it (address, site, answer, fingerprint, last-changed and
  entity tags, next visit by how often the page changes), and an unchanged page answers 304 and costs nothing.

### 1.2 Pre-fetch compliance, restricted gate and negative caching

In strict order, before any GET:
1. **Switches and service check:** the master, the workflow, the task and the service are on, and the host isn't held.
2. **Restricted registry lookup** by `url_hash` or domain/path pattern (joined to the register's Ignore): a hit logs
   `SKIP_RESTRICTED` and stops.
3. **robots.txt** (cached 24 hours): a disallowed path is recorded `ROBOTS_EXCLUSION` and stopped; `Crawl-delay` sets
   the host's pace (the larger of it and our 1.5 s default).
4. **Non-public paths** (`/wp-admin`, `/login`, `/signin`, `/account`, `/cart`, `/checkout`, `/auth`, `/dashboard`):
   recorded `ADMIN_PATH` and stopped.
5. **Negative caching and blocks** (Jason, 2026-11-14, P1-2): **a site that blocks us is never retried by itself.** A
   block (`AUTH_WALL`, a robots.txt exclusion, a Cloudflare challenge, a 403 or 406 ban) records its reason, **holds the
   site** (`HELD_BLOCKED`) and opens a step in a Pulse case for a person, with the evidence and a **Reactivate** button.
   It is re-read only after a person reactivates it (one polite try under R18; still blocked, it is held again with its
   step back). Records resting on it refresh from their other sources meanwhile (the identity record, open data, the
   Call list) and show "last checked" (Decision 3). Paywalls (`PAYWALL`) stay excluded, re-checked once a year;
   transient failures back off exponentially (§1.3).

### 1.3 HTTP fetch and post-fetch restriction capture

- **Request:** GET with our User-Agent (crawler name, site and support@traversence.com), `Accept:
  text/html,application/xhtml+xml,application/pdf`, a 15-second timeout, no cookies, no following of sign-in redirects.
- **Status routing:**
  - **200:** **keep the fingerprint, headers and the extracted facts with their quoted snippets; never the page body**
    (Decision 1; 0157 §1: facts, not copies, for copyright, cite-only terms and storage).
  - **301/302/307/308:** a redirect to a sign-in page records the original as `AUTH_WALL`; otherwise the target is
    queued (at most 3 hops, each hop checked against private addresses).
  - **401/403/451:** `AUTH_WALL` (451: refused); never retried another way (no new agent, no other route, no paid
    reader); held for a person's Reactivate (§1.2, 5).
  - **402, or a hard paywall signature** (`isAccessibleForFree: false` with a cut-off body): `PAYWALL`.
  - **404/410:** `GONE`. **Two clocks** (§14 point 11): the **page record** is re-checked once after 30 days, then
    archived; the **listing's website field** comes off the listing when the site fails twice, 3+ days apart, and the
    crawler gave it (kept as taken off), else goes to Review. For a listing's own website, GONE lowers its confidence,
    never closes it (Jason, 2026-11-13; R12); robots.txt blocks and spent budgets never count.
  - **429/5xx:** back off `2^attempt` minutes, at most 5 tries, following the server's `Retry-After` exactly.
  - **406/418 from an API** (a ban): the service check holds the service and opens one Pulse case; never retried until
    a person reactivates it (§1.2, 5; the hold and its case live 2026-11-14). Holds for quota and back-off (429, 5xx, a
    spent budget) still return by themselves (§6.1).
- **Second tier** (0157): a page that matters and that our fetch can't get may go to Tavily Extract, a scanned PDF to
  Textract, within their budgets; never a page robots.txt or the site refuses.

### 1.4 Automated source classifier and dossier engine

Each 200 gets a `source_tier` (1–5) and its dossier:
- **Tier 1, official and institutional:** `.gov`, `.mil`, `.edu`, or a domain on the state, county, municipal, tribal or
  registry seed lists (tribal nations' sites are often `.org` or `.com`, so the seed list names them; whether we read a
  nation's site regularly stays a person's call, R7). **Licence registries** are Tier 1 for what they state: legal name,
  address, licence type and status.
- **Tier 2, first-party:** a single organization's or place's own site (schema `Organization`/`LocalBusiness` matching
  the domain's main entity), or a single-topic site with no aggregator signs. WHOIS is not a signal (mostly redacted).
- **Open place datasets** (Overture, Foursquare's open data, §1.5) are their own class, **corroboration**: stored and
  shown with credit under their licences (once confirmed), ranked below a business's own site and below Tier 1 records.
- **Tier 3, regional context and aggregators:** local news, chambers, tourism boards, regional directories.
- **Tier 4, community and long-tail:** forums, personal blogs, crowdsourced reviews.
- **Tier 5, noise:** parked domains, link farms, thin pages (under 100 words with a high outbound-link ratio): dropped
  before Phase 2, but logged in the page log.
- **Dossier** (extending the source register, 0045, 0157 §7): domain, tier, coverage (place, years, subject), who made
  it, credibility (0.00–1.00), volatility, licence and terms flags, last audited. **The person's call on a site (Read
  regularly, Cite only, Ignore) and its licence override the classifier.**
- **Audit log** (`crawl_audit_log`, append-only): every decision with its reason code and the rule it applied.

**Note (2026-11-17): every URL found is logged; no "suggested" gate** (Jason: "'suggested' i dont recall approving
that part. It should log all urls it finds because it will be identified at a later step."). The live code still
works as 0045 did: links to other sites (at most 40 a site) enter the register as **suggested** and nothing more
happens until a person marks one Read regularly; links within a site are kept only 60 a page. Steps 1–3 above already
say otherwise, and this makes it plain:
- **Logged, all of them:** every link on every page read, on the same site or another, goes into the frontier (§1.1)
  normalized and de-duplicated, with the page it came from and its link text. No cap but the frontier's own size
  guard; nothing dropped for being off-site. (The old caps, 60 links a page and 40 off-site a source, go: batching,
  the classifier, the junk loop and the size guard now do their jobs.) A safety net only for a broken page with an
  absurd number of links: they're logged over several passes and the page is flagged; nothing is dropped.
- **Identified later, by the machine:** classification (§1.4: tier, kind, dossier, licence and terms) runs on each
  new site by itself; there's no waiting on a person.
- **Read only where the rules allow:** logging isn't reading. Before any fetch, §1.2 holds as written: the switches,
  the restricted registry, robots.txt, non-public paths; commercial directories cite only; Facebook and Instagram never
  read (R14); nations' sites a person's call (R7); blocked or banned hosts never retried.
- **A person's call is an override, never a gate:** Read regularly, Cite only or Ignore on the register still wins over
  the classifier, and a person sees only what the evidence can't settle (as §6.1's identity queue).
- **Today's "Waiting for you" sources** are classified by the same pass when this ships; nothing already decided changes.
  *Note (Jason, 2026-11-17, W0):* the machine's call (`Sources::machineCall`) sets a site that isn't a public body,
  a commercial directory or social media (a chamber, a tourism board, an organization, a business) to **Read regularly**
  at its tier: its pages are indexed, and its facts are kept only as its licence and terms allow (`Sources::rules`). A
  site its address can't settle is classified once its home page has been read (it may say it's a nation's). A tribal
  nation's site stays `suggested` with `judged_by = 'nation'`, a person's call (R7). "suggested" otherwise means only
  "being classified".
- **The indexer is Phase 1, and runs all the time, not on a schedule** (Jason: "That is the one thing I want to be a standard always
  running task, not on a schedule, because it will be the one thing that indexes our potential knowledge"). Logging
  the frontier, fetching what §1.2 allows, keeping each page's fingerprint and links, and classifying new sites run as
  one continuous worker: its own always-on service on Railway (restart on failure), not the cron. It only indexes:
  extracting, resolving, publishing and every other workflow stay scheduled (0163). It keeps every rule above (per-site
  pace, R18; robots; the restricted registry; blocked hosts never retried), sends its results to the site in small
  batches at the worker's pace (the Bluehost bot check, 2026-11-15), sleeps when the frontier is empty rather than
  spinning, and has its own switch and budget line in the register. Start the system starts it first; Pause
  everything stops it (the safety stop stays one button).
- **The reader loops back into the index** (Jason: "Maybe we loop back the reader to segment or promote urls in index
  so we restrict bad or junk while using them to keep from recapturing them"). Every later step that reads or judges a
  URL (the classifier, Phase 2's reading, Phase 3's vetting, a person's call) writes its verdict back onto the URL and
  its site in the index:
  - **Promoted:** it gave facts we kept, or a trusted source leads to it; read sooner, and its links ranked up.
  - **Normal:** waiting its turn by the priority below.
  - **Demoted:** thin or off-topic; read rarely, its links ranked down.
  - **Junk:** Tier 5 (parked, link farm, spam), a trap (endless calendars, session or search-result URLs), restricted,
    blocked or gone. **Never fetched again, but never forgotten:** it stays as a compact record (its hash, site and
    reason), so the indexer recognises it the moment it's found again and doesn't log or read it twice. A pattern
    seen often (a site's calendar pages, a tracking query) becomes a rule, so whole families are skipped at the door.
  - Verdicts can be undone (a person's call, or new evidence), and every one is in the audit log with its reason.
- **Reading priority:** every URL is logged, but what gets read first: sites in or about our hubs' places (with hubs by
  location); then by the classifier's tier and the URL's verdict; then how many of our known sources link to it; within
  a set depth from a trusted source. Low-value URLs wait; nothing is dropped except junk, which is remembered as junk.
- **A size guard:** junk kept compact; a URL never read after six months, and not linked from anything promoted, is
  compressed to its hash; the frontier's size and growth shown in the pre-flight. If it outgrows Bluehost's database,
  the frontier moves to the worker's side (Railway or Supabase).
- **Pace on the site:** the indexer on Railway gathers what it finds and writes it to the site in batches, so the site
  sees a few requests rather than a steady stream (Jason, 2026-11-17: "batch 250 records into dbase to stay away from
  bot check"; the numbers below agreed the same day):
  - **found URLs: 250 a batch; read pages with their links: 25 a batch; either capped at 100 KB**, whichever comes
    first (under ModSecurity's usual 128 KB limit on a request without files; a found URL is about 0.3–0.5 KB, a read
    page 5–10 KB);
  - **at most one batch every 10 seconds** (about 1,500 URLs a minute at most);
  - **self-adjusting:** a 409, 413 or slow answer halves the batch and doubles the gap; an hour of clean answers grows
    it back toward 250, never past 500;
  - **settings, not code:** batch size, size cap and gap in the Thresholds register;
  - links wrapped as usual (`tvSafe`), the worker's cookie and pacing (2.7.2+), back-off on 409 or 503, and each record
    keyed so a resent batch changes nothing; the indexer slows when the site is slow;
  - **tested before launch:** the pre-flight sends one full-size test batch to the intake (marked test, discarded).
  - **every batch gets a receipt** (Gemini's review, agreed by Jason 2026-11-17): the site answers with how many records
    it stored and a checksum of them; the indexer compares that to what it sent and resends what's missing. Three
    mismatches in a row slow the pace and open a Pulse case, so a batch a firewall swallows without an error is caught.

**Note (2026-11-17, release #79):** W0 Indexer's site side is built as written (#78 was withdrawn: its migration used a
column name MySQL 8 reserves). The worker (`workers/crawler/indexer.py`) runs as its own Railway service, `w0-indexer`,
and sleeps while the automation is paused; W0 comes on first at Start the system (`handoffs/w0-indexer.md`).

**Note (Jason, 2026-11-19, control-board-itsdata): outside our region is logged, never skipped silently.** His answer:
"W0 skips sites outside your region: skip or logged as outside the region?" → log it. Every address W0 is handed is
still kept, but one about another state or country goes to its own state, `outside` ("Outside our region", reason
`OUTSIDE_REGION`), and is never handed out to read. It's judged by `Sources::offWhyHost` from the address alone and what
we already hold: a country's ending (`offWhy`); a state's own `.gov` ("the state of Maryland's site"); `.xx.us` or
`.xx.gov` for a state not ours; a city or county site ending in another state's code (`lawrenceks.gov`). Federal sites
(nps.gov, recreation.gov…), ours (`tucsonaz.gov`), and a site any of whose places are in our hubs are never outside. A
welcome rule (W3's off-region rules, decisions/0171's Pause and Undo) brings it back to be read at the next hourly tick
(`Frontier::sweepOutside`). Sites that blocked W0 are now one quiet notice on the board (0171 §7 note), never a trip.

### 1.5 Lead sources for rural businesses

Finding businesses that have no website, and proving they're real, from four kinds of source. Each is a service under
0163 (switch, budget, service check) and a bulk load or task on the crawler, loaded state by state as regions open (R17).

| Kind | Sources | What we may do with it | Cost |
|---|---|---|---|
| **Authority: licences and registries** | Arizona Corporation Commission and New Mexico Secretary of State (companies); Arizona TPT (sales tax) licence lookup; liquor licences (Arizona DLLC, New Mexico ABD); Arizona Registrar of Contractors; county health permits and inspections; state licensing boards (cosmetology, auto, medical); NPI; the IRS nonprofit list; Recreation.gov | public records: store and show the facts they state, with the source; a match **establishes** a listing (§3.5; see §12, C2–C4 for NPI, IRS and bulk files); each source's **real use** recorded by W14 (§7.1, 3) | free (bulk files where offered, else paced lookups, 0163 §1); each source's terms checked first |
| **Open place datasets** | Overture Maps Places; Foursquare's open places data | **once their licences are confirmed** (Decision 9): name, address, phone, website, social pages, category, confidence, stored and shown with credit | free bulk downloads, refreshed with each release (monthly for Overture) |
| **Witnesses** (confirm, never copied) | Google Places (keep the place ID only), checked in the order set in 0163 §12; Gemini grounded search and Maps grounding **only if Jason funds them** (§0.8; results only point; Google's display and storage rules followed) | weight in the collaborative score, which feeds confidence ("confirmed open on Google") | Places paid per call, within its paced budget (0163 §1, §12); no grounding on Gemini's free tier (Run 2) |
| **Pointers** | OpenStreetMap (0065); a business's Facebook page (R14); chamber and tourism directories (cite only unless their terms allow) | a lead to read, and weight in the collaborative score when it agrees (§3.5) | free |

- **Every state's authority sources are found and staged by W14** (§7.1), off until that state's hub opens (R17).
- **OpenCorporates is not used** (Decision 6): the state registries and our free anchors instead.
- **Lead order** (Run 2; the order and its values are settings in 0163 §12): **Overture** as the base layer and **NPI**
  by town for health care first (free, no key), then **Google Places' check** for finds without a website that have a
  phone. Our own older listings are a lead source only (weight 0): 21 businesses in the two Apache County towns are only
  there.
- **NPI's two guards** (Run 2): departments fold into their parent (same address or building); non-public kinds (air
  ambulance, home care, residential treatment, VA registrations) are kept as records but not shown as places to visit,
  with a Review step to list one anyway if it serves visitors. Otherwise NPI alone establishes (§12, C3).
- **Gemini is never a finder** (§0.8): ungrounded, it named businesses from memory with made-up phones (Run 2). Grounded
  search, if Jason funds it, asks "what [kind] is in [town]?" built from our vocabulary only (§0.8); a lead is kept only
  with a cited link, and nothing it says becomes a fact until a licence, an open dataset or the business's own page
  confirms it.
- **A lead's states** (P1-4): **new → read** (its cited page read by our crawler) **→ witness only** (its cited pages are
  cite-only or social: counted in the collaborative score, then closed) **→ expired** (90 days with nothing readable).
  Each lead counts once in the register's "what it produced" (0163 §1).
- **Arizona's licence sources as found** (Run 2; each one's real use recorded by W14, §7.1): the liquor (DLLC),
  contractors (ROC) and county sites (Apache, Navajo) block automated reading behind Cloudflare: **blocked**, so a person
  or a public-records request for their lists as files; the TPT lookup takes only a licence number behind a CAPTCHA: a
  **claim-time check** of the number the owner gives; the Corporation Commission moved to
  `arizonabusinesscenter.azcc.gov` (its terms read before use). Kayenta: the Navajo Nation's own sources under 0066.
- **The rural research test first** (Decision 10, accepted to run now): Kayenta, St. Johns and Springerville, a few
  kinds of business, comparing what OpenStreetMap, Overture, Foursquare's open data, the state licence lookups, Gemini
  grounded search (only with a Gemini key, within the free allowance) and our current listings find; confirming each
  source's licence terms; reporting how many real businesses each mix would establish without a website. Free; nothing
  on the live site changes. **Run 2026-11-14:** `research/rural-test-2026-11-14.md`. Its proposed weights are the
  collaborative score's starting settings (0163 §12), tuned by its Run 2 (its §8; applied 2026-11-14, §15); its
  threshold is not used (§3.5).

---

## Phase 2: Contextual extraction and data segmentation (0157 §2)

Turn pages and files into facts, cheaply, whatever their subject; for stories, the pillars' passages go first. Facts
only; never whole pages kept. Listings and places are extracted as facts (name, address, phone, hours, kind), never page
text.

### 2.0 The pre-scan: "scanner before reader"

A structural pre-scan in milliseconds before any deep extraction or token spend:
- **Signal-to-noise:** text density against boilerplate; below the threshold flags `LOW_SIGNAL` (logged, never silently
  dropped).
- **Entity density:** addresses, phones, hours, schema; none on a directory page stops processing.
- **Traps:** endless calendars, pagination loops, malformed URLs.
- **Content map:** the main content blocks, so the reader parses only those.

### 2.1 Content-type dispatch and boilerplate stripping

- **PDF:** a text and table extractor keeping page numbers; a scanned PDF (no text) goes to the second tier (Textract)
  only inside its budget.
- **HTML:** strip scripts, styles, navigation, footers, headers, asides, cookie banners and hidden elements (trafilatura
  on the worker); isolate the main node.

### 2.2 Step 1: deterministic, code-first extraction (about 80%, free)

- **Structured data:** JSON-LD, microdata, OpenGraph (`ListingExtractor` on the site, the worker's parser).
- **Tables and registries:** `<table>`, `<dl>`, CSV and JSON endpoints into key-value rows.
- **Patterns:** US phones (E.164), street addresses (USPS form), ISO dates, opening hours.
- **Business emails (Decision 2, Jason's rule):** an email **with a business contact present** (a named contact person,
  a contact section, schema `ContactPoint`, beside the business's phone or address) is kept **and posted** as the
  listing's contact, including small businesses using personal providers. A **random email not tied to a contact** is
  kept for Review and restricted (never shown) until a person or the owner clears it.

### 2.3 Step 2: the narrative waterfall and long documents

Only when required fields are still empty after Step 1 and the body has prose (40+ words):
- **Tier A, keyword windows:** only the paragraphs near hours, address, admission or contact words; for the pillars,
  windows on people, trades, water, materials, land and hardship.
- **Tier B, local NER:** a small entity tagger on the worker (a bigger Railway worker; its cost goes in the register).
- **Tier C, the AI reader** (Decision 7):
  - **public pages, with R6's private details removed first (§0.8), go to Gemini's free tier** (Flash or Flash-Lite)
    to be read, within its allowance; when that is used up **the work waits** as a page waiting to be read (§1.1; its
    limits and how the service check holds it: 0163 §11);
  - **pages from a person** (a member's post, a scanned card, anything private) **go only to Claude** (Haiku), within
    the AI cap and the workflow's budget (0163 §7), counted per call;
  - a sample read by both is compared, and **a director** then changes the feature's provider on AI use (0163 §11);
  - under 4,000 tokens: the stripped text, a strict JSON schema; over 4,000: 3,000-token pieces with 500 overlapping,
    merged after;
  - the rule: "Extract only facts explicitly stated in the text. Return null for any unstated field. Do not infer."
    (No temperature setting: current Claude models don't take one; the schema and the rule do that job.)
  - for the pillars: turning points, people and their roles, trades and building materials, water and land use,
    hardships, local names and lore, sites you can walk today.

**Note (2026-11-15, releases #60, #67):** Compare Claude and Gemini is built for a director to press (#60), and the
reader of pages waiting to be read (`page_read_tick`, three an hour, under W3), shipped off (#67).

### 2.4 Splitting lists and stamping provenance

- **List unbundling:** a page with several items (a county directory, an events calendar) becomes one candidate per
  item; an item with its own page queues that page at high priority.
- **Provenance on every fact:** `fact_id`, `entity_candidate_id`, `field_name`, `field_value`, `source_url`,
  `url_hash`, `dossier_id`, `source_tier`, `extracted_at`, `extraction_method` (`DETERMINISTIC_PARSER |
  REGEX_HEURISTIC | LOCAL_NER | LLM_HAIKU | LLM_GEMINI`), `raw_snippet` (the quoted snippet, Decision 1), `licence`
  (from the register) and `exposure` (§3.4).

---

## Phase 3: Entity resolution, vetting and privacy

Check what was extracted, and shape the research. **Configuration lives here** (coverage: active regions, states,
priority by search demand and residents affected; how deep research goes; the thresholds, R10).

### 3.1 Normalizing candidates and the identity check

- Legal suffixes stripped for matching (kept separately); street abbreviations standardized; addresses geocoded (the
  U.S. Census geocoder, free) to `[lat, lng]` and a `geohash_7` (about 150 m).
- **The match rules** (same place, another location, provider, covered, another business, maybe), website brand and
  page levels (0083), other names (0084).
- **The Nexus** (0054, 0083): made automatically when locations share a website or main phone; **never on a similar
  name alone**; districts and `.gov`/`.us` parents never one brand. A Nexus is a parent with child locations, not one
  merged record. (The draft's "a name shared with records over 250 m apart becomes a Nexus" is held as §12, C5.)
- **One identity check, on every path** (amended 2026-11-15, Jason: "a process failure … at the fundamental level in
  which the data was captured and evaluated"; the cause: `handoffs/duplicates.md` §1). Today four different tests guard
  the ways a listing is made, and the crawler publishes on a match made when a find arrived, however long it waited.
  From Release B:
  - **One check, `ListingIdentity::resolve()`,** is this section's match rules in one place. It is called inside the
    transaction that writes, by **everything that creates or un-hides a listing**: the intake's publish
    (`IntakeStager::approve()`, whoever approves: arrival, the pipeline, the second look, quick approve, the helper,
    Review), the ingest API, every bulk load (W1), people's adds and claims (`AddBusiness`), staff adds, Pulse's Create,
    private client records, and every Show, Undo, Put back and "Help us find" answer. It reads candidates **fresh** from
    the identity keys (never a match stored earlier), hidden twins included, every candidate scored (no cap). `same`
    merges into the existing listing (gaps only, R4); a person who answers "a different business" is recorded on the
    pair for good (R11), read by every path.
  - **Within each batch:** a load's finds are grouped by identity key and folded into one find with all their sources
    before staging (one operator's two NPI records at one base arrive as one find with two sources); groups the check
    calls another location or a provider stay apart (0074).
  - **A database guard:** normalized phone, address and name keys on `entities`, indexed; a unique key on ZIP + phone +
    name added once the recheck (§6.1) has cleared today's exact twins; a lock on the identity while it's checked and
    written, so two workers can't both make it. The keys and the lock's numbers: 0163 §12.
  - **The match rules, tuned** (the gaps found 2026-11-15): extensions removed from phones; legal endings (LLC, PC,
    PLLC, LP, LLP, dba) and plurals folded; street types both ways; a PO Box never a street; one name measure everywhere
    (`AutoImport::sameName`, against the longer name, so a provider at a facility stays apart); "another location" needs
    two street numbers **and** a different phone or points over 150 m apart (`geohash_7`), so a typo'd or suite-first
    number isn't a branch. The kinds and 0074, 0081, 0083, 0084 stay as they are.

**Note (2026-11-15, releases #58, #60):** built in Release B, switched off: the one identity check on every path,
within each batch, the identity keys and lock, the tuned match rules, the recheck and the identity queue (#58); the open
data loads run through it (#60).

### 3.2 Similarity scoring and the grey-zone check

- **Match score (0.00–1.00):** name similarity (Jaro-Winkler) 0.35, address/geohash 0.35, phone or domain 0.20,
  category 0.10, against records in the same `geohash_6` (about 1.2 km) or the same domain.
- **Routing:** ≥ 0.95 merges into the existing record (reversible, R12); < 0.60 a new record; 0.60–0.95 (the grey zone)
  goes to the verification worker before any person:
  1. **Our own free anchors first:** the identity record, the place store, NPI, the IRS list, Recreation.gov, the Census
     geocoder, the licence registries and the open place datasets (§1.5).
  2. **Google Places** (paid per call, inside its paced budget and check order, 0163 §1, §12): to confirm the place
     exists and is open and to keep its place ID only (never Google's coordinates or address). (Gemini grounded search
     stood here; it isn't used, §0.8.)
  3. **Company registries:** the state registries where they can be read for free. **OpenCorporates is not used**
     (Decision 6).
  4. Re-score: ≥ 0.95 merges with the receipt attached; still grey goes to Review as a step in the engine's Pulse case
     (§6.1) and is **re-checked on the retry ladder** (§6.1; P3-3), never left; a person's answer ends it at once.
- **Cross-checks for heritage facts:** a primary record or two independent sources; **two secondary sources resting on
  the same primary count once, unless we read the primary** (as copies count once, R11); a research brief never counts
  as a source.

**Note, 2026-11-15: duplicates are settled by the engine, not sent to a person** (Jason, in his words: "I see this as a
process failure and we need to address it at the fundamental level in which the data was captured and evaluated." "I
see this a recheck not a pass to human for review. If we have data loads that cause issues the system must be able to
identify them and correct them. Listings are flexible, since we are building potential listings for claiming, once
claimed is a whole different conversation." "the knowledge engine is the master of this process, so make sure you don't
create something that doesn't fold into that." "The recheck: this also has tools specifically to check our data against
what's out there, so if one records has variations we need a go to process/workflow that precisely targets its details
and vets the single location data." "Only what the evidence can't settle, or anything claimed or entered by a person,
comes to you, with the reason. let's have a queue for these that allows them to be sales leads or admin taskings, but
the system will show them as listings until they are merged or vetted."). This **replaces "a match is always a merge
for review"** (`AutoImport`, 0044/0076) for unclaimed listings. Two listings the identity check (§3.1) matches:
1. **Exact twins merge by themselves:** `same` at ≥ 0.95 with the same normalized phone, name and ZIP (or address),
   both unclaimed and nothing a person entered or decided on either (R4). The routing above already says ≥ 0.95 merges;
   the recheck (§6.1) now applies it to listings already live, not only to finds.
2. **Variations go to the vetting first:** a pair (or one listing) whose sources differ on its name form, suite,
   phone, website, hours or open or closed goes to **"Vet this place"** (§6.1: W2's `identity` task, the one entry
   point), which checks each detail against our anchors and outside sources and settles each field from the evidence
   (R11); then the pair merges or stays apart on what it found.
3. **Only what the evidence can't settle, or anything claimed or entered by a person,** goes to the **identity queue**
   (§6.1), with the reason and each side's evidence, where it becomes a sales lead or an admin task. **Both stay public
   as listings meanwhile.**
Other locations and providers at one facility (0074) aren't duplicates and never reach the queue. A merge is undone
exactly (R11; §6.2's split for a wrong one).

### 3.3 The truth hierarchy (resolving conflicts)

**A person comes first** (R4): what an owner, a claimed business or our staff entered is never overwritten by a crawl; a
crawl that disagrees raises a "check this?" instead. Then, by field:
- **Legal name, parcel, coordinates, regulatory status:** Tier 1 > Tier 2 > Tier 3.
- **Hours, menu and services, prices, event dates, direct contact:** Tier 2 (first-party) > Tier 1 > Tier 3.
- **Open place datasets** sit below Tier 1 and a business's own site, above Tier 3 aggregators, for name, address, phone
  and category.
- **Same tier:** `DETERMINISTIC_PARSER > REGEX_HEURISTIC > LOCAL_NER > LLM_HAIKU = LLM_GEMINI`; then the most recent.
- OpenStreetMap, Google and Gemini's grounded answers never enter the hierarchy (§0.5): they point and witness only.
- The listing's shown name stays the one people know (R11).

### 3.4 Curation, disposition and visibility (0157 §3)

- **Privacy:**
  - **Removed before storage:** Social Security numbers, financial accounts, private non-business home addresses
    (`[REDACTED_PII]`).
  - **Business contacts:** kept as `PUBLIC_CONTACT`; an email without a business contact beside it is restricted until
    reviewed (Decision 2, §2.2).
  - **People named in news or history:** `CONTEXTUAL_ATTRIBUTION`, kept for research briefs, never in a listing's
    contact fields; the people rules (R6) decide what's published.
- **Protected places, three levels** (R7), each record's fields split into `PUBLIC_STORY_SAFE` and
  `RESTRICTED_INTERNAL_VAULT`; the tier comes from the jurisdiction register (R8).
- **Dispositions:**
  - **ACTIVE_PUBLIC:** through the existing listing gate: past the floor (§3.5) and ready (§5), at its confidence, then
    vetted continuously.
  - **ACTIVE_RESEARCH_ONLY:** context and background, held for research briefs.
  - **UNIVERSAL_RESEARCH_ARCHIVE:** every valid fact (never page bodies), kept; nothing valid is discarded.
  - **HOLD_NEEDS_RECHECK:** past its freshness or flagged for parser drift; queued back at high priority (a source that
    blocks us excepted: it waits for a person's Reactivate while the record refreshes from its other sources, §1.2, 5). **It stays
    public with "last checked" shown, out of browse lists only when Closed under R12** (Decision 3), so a paused crawler or a
    source that blocks us never empties the site. (The draft's 90-day Tier 1 / 30-day Tier 2 clocks: §12, C6.)

### 3.5 Establishing a listing: the "real business" floor, then confidence (Decision 8, as revised 2026-11-14)

**Confidence, not restriction** (Jason, 2026-11-14). A find becomes a public listing once it passes **the "real
business" floor**:
- **a real business name:** not page text (the junk names of 2026-11-13: "Contact Us:", "PO Box 190", headings, phrases
  from a page), and
- **a way to find it:** a street address or a phone we may keep (from the business's own site, a registry or licence, an
  open dataset whose licence allows it, a page we read whose terms allow it, or the owner).

It is then public, through readiness (§5.4), **at whatever confidence it has** (R12); nothing is held back for low
confidence. Without a keepable address or phone it **stays a sighting** until a keepable source supplies one.

**Verified NAP is the goal, not the start** (Jason, 2026-11-14: "NAP is what we ultimately want verified, but listings
starting as just one or even no NAP can be refined over time or by real people"). A verified Name, Address and Phone is
what every listing works toward; a listing may start with part of it and is refined by sources, the owner and people.
**Each NAP field shows how it's known** (its source and date: a licence, an open dataset, the owner, our team, a
visitor, "seen on its Facebook page", R14). The floor above stays the line for going public. **A sighting** (P3-2)
joins §4.5's gaps as a discovery target, retried on the retry ladder (§6.1); one with a Facebook link is a step in a
Pulse case, **Confirm from its page** (the link and the fields to type, R14); its town page asks visitors "Know its phone
or address?" as a suggestion.

**A find with only a name and a town gets a public "Help us find this business" card** (Jason, 2026-11-14: "b is a great
public request"): shown on its town page and in search as a request, never as a listing (no pin, no Call or Directions,
no confidence badge), asking visitors for its address or phone (and the owner to claim it). Answers come in as a
person's suggestion with its source (R4), and once a keepable address or phone arrives and passes the floor, it becomes a
listing. The card keeps to the rules for names: the "real business" check, the people rules (R6) and protected places
(R7, held names never shown); it retires on the retry ladder's long wait if nothing ever comes.

What raises its confidence, strongest first:
1. **The owner** claiming it (a claim, a GO Code at the counter, a staff visit): the strongest, always above the rest.
2. **Its own website** naming it.
3. **A government licence or registry match** on name and address, alone (as NPI and the IRS file: §12, C2–C4).
4. **The collaborative source score** (Jason, 2026-11-14): independent sources agreeing on the name plus the phone or the
   address each add their weight (an open place dataset, Google Places confirming it open, a Facebook page, a chamber or
   tourism listing, OpenStreetMap, a directory page our crawler read; never Gemini's own word, which weighs 0). **It is not a gate and has no
   threshold: it feeds the confidence shown** (R12). Sources count as independent only when one isn't copied from
   another (two directories fed by the same dataset count once). Its weights are settings in 0163 §12, starting from
   the rural test's proposal (§1.5), as tuned by its Run 2 (§15).

What it shows comes from what we may keep (the licence, the open datasets with credit, a readable page, the owner), never
copied from Google, OpenStreetMap or Facebook (R9). Until the owner confirms, it shows **"Not yet confirmed by the
business"** with an invitation to claim it. Machine evidence alone stays below green (R12).

**Closure in reverse, as curators, not regulators** (R12): a licence lapsing (or dissolved, revoked), Google reporting it
closed, an open dataset dropping it, or one site saying "closed" is recorded with its source and lowers confidence, and
"Still open?" asks visitors and the owner; it is labelled **Closed** only when the owner says so, our team confirms it, the public
confirms it, or its own site says so (R10, R12, W2).

**Note (2026-11-15, releases #51, #54):** "Help us find this business" is built (#51; Jason: "build the Help us find
this business card"): a name and a town is a request on its town's page and in search, never a listing (no pin, no
Call or Directions), asking "Know its phone or address?". Drop-offs that aren't businesses are their own kind, out of
business browse lists and never claimed (#54).

---

## Phase 4: The master catalog, knowledge graph and index

Every vetted fact, on any subject, is stored in our own record, each with its source and who set it, in one schema where
people, land and places meet on the map: **modern places** (listings, landmarks, outdoor places, with provenance); **the
Human Record** (turning points, builders and families, trades, industries, institutions, traditions); **the Living
Landscape** (landforms, water, trails and old routes, native materials, climate and seasons, environmental milestones);
**footprints** (the point where a past event, person or feature can be stood on today); **dossiers and research briefs**
(0131), kept with the place; **the Topic index** (R15); **the Seeker** (an admin search across the catalog by theme and
place). **On MySQL 5.7.44**, mapped onto what we have (§8), not new tables beside it.

### 4.1 Entities and identity

- **Canonical records:** listings, landmarks, outdoor places, topics and events keep their tables; one canonical
  identity per thing (the identity record, R11) links them, with its lifecycle state.
- **Provenance and actors:** every change, merge and verification receipt names its actor (crawler, a person, an
  operator override) in the identity records.
- **Attributes and history:** companion tables keyed by the canonical ID; history is never overwritten.

### 4.2 Spatial

Points in `POINT` columns with a `SPATIAL INDEX` (InnoDB, 5.7); radius searches with `ST_Distance_Sphere` (5.7.6+) and
a bounding box first; no SRID 4326 geography on 5.7 (spherical math on plain points is accurate enough at our scale).

### 4.3 Edges

`entity_edges` (directional, typed): `LOCATED_IN`, `MANAGED_BY`, `PART_OF_NEXUS`, `HISTORICALLY_LINKED_TO`, `NAMED_IN`
(a guide, story or journey naming a place) and `ON_LAND_OF` (a nation, 0066).

### 4.4 Text and summaries

JSON columns for narrative pieces and flexible properties; companion text tables with `FULLTEXT` indexes for keyword
search. 5.7 has no CTEs or window functions, so graph walks stay small, bounded queries.

### 4.5 Gap analysis and the feed-forward loop

Completeness queries flag records missing key fields, sightings included (§3.5); each becomes a discovery target in
Phase 1's frontier. A gap records **"looked, none found"** with the date and is retried on the retry ladder (§6.1), not
every run, and never dropped (P4-1); "no website" stops being a gap once two looks found none (R12: it never counts
against a listing). Gaps also come from searches (W12, "every search is demand") and from topics and guides with thin
coverage (R15), ordered by how often they're asked for. **A search that found nothing** records demand (P1-3): the
place, and the kind or topic from our own vocabulary, never the words (§0.8); a kind becomes a town crawl want
(`TownCrawl::want`), a topic a topic gap for W6 (R15), ordered by how often asked. Search targets inside an open hub
aren't held for a person.

---

## Phase 5: Publishing, serving and access

### 5.1 Access and visibility

- **Public directory and feeds:** `ACTIVE_PUBLIC` records and safe summaries.
- **Internal research:** `ACTIVE_RESEARCH_ONLY` and the archive, for research briefs, guides' writing and staff tools.
- **The vault:** sensitive coordinates, access details and personal identifiers stripped from anything bound for a
  public endpoint or a writing prompt.
- **Permissions:** `Access::can()` / `Modules::canTool()`, never a parallel system.

### 5.2 Queries and spatial filtering

- Spatial endpoints on bounding box plus `ST_Distance_Sphere`.
- **Semantic search** (Decision 4, **confirmed by Jason 2026-11-14**, §12 C10): a **sidecar vector index** beside MySQL,
  which stays the record: Postgres with `pgvector`, on **Supabase's free tier** to start (no cost; a small database, and
  free projects pause when idle); **Railway** (a few dollars a month) only once revenue covers it (§12, C10). Embeddings from Gemini's free
  embedding model (public text only) or a small model on the Railway worker. Built in a later stage under its own
  budget. Until then, search keeps improving what it does (our index as the dictionary, senses from context, phrases;
  0157 §4).

**Note (2026-11-17): everything works without the vector index** (Gemini's review, agreed by Jason). Supabase's
free tier can pause, and the index is a copy of public text, never the record. So every caller of the vector index
(semantic search, AI Assist, the Seeker) falls back to MySQL's own keyword search when it's paused, off or slow, with
a quiet "fewer matches right now" note, never an error. When it's back, the next upkeep run (`vectors_tick`) sends
anything missed, keyed by the text's hash, so a resend changes nothing and nothing is lost. The pre-flight checks that
Supabase is awake and the index is complete.

**Note (2026-11-15, release #60):** the vector index is built on Supabase with Gemini's embeddings, as a task added
switched off (`vectors_tick`); Supabase is a paused service until Start the system turns it on.

### 5.3 Provenance shown

Every served record carries its source tier, last checked time and confidence; **freshness is shown** (every guide and
listing shows when it was created and last refreshed); OpenStreetMap credited where it's shown (0065); open datasets
credited; a cite-only source shown as a citation, never as our words.

### 5.4 Writing from the catalog and the gates

- **Writing:** Claude (Sonnet, or the model set in AI use) writes from the catalog only: a place's "Walking in the
  Footsteps" chapter and the guide sections; **every historical mention is anchored to a place a visitor can stand
  today**, and people and land are told together. Guides, journeys, live events, how-tos and stories (W4, W5, W7, W10,
  W11, W13) are written only from `PUBLIC_STORY_SAFE` facts and vetted research.
- **Gates (rule-based, R10):**
  - **Listings** publish when **ready** (0076): **real** (past the "real business" floor, §3.5), **complete** (one rule, P3-1:
    the floor's name and keepable phone or street address, a website adding to it, and **a point in an area we cover**,
    from the address, the dataset's own point, or the town's middle marked approximate; a listing with only an address
    shows Directions and "No phone yet: know it?"), **appropriate** (a category fits, no spam or adult content), **unique**
    (no unresolved duplicate). No confidence threshold; everything after publishing is handled by rule in Phase 6.
  - **Guides** publish themselves at R10's guide score.
  - **Find it** results must pass the "real business" floor (§3.5) and readiness, and get confidence like any listing;
    R10's Find it score only orders the work (an AI's word is never evidence, R13). This replaces "a medium find with a
    phone, an address and two or more sources is enough" (2026-11-01).
  - **A person** only for exceptions: a pipeline exhausted, a protected item, a conflict with a person's own content,
    nations' land. Every exception is a step in a Pulse case (§6.1, X3); the engine keeps retrying in the background
    meanwhile (the retry ladder).
- **The check before saving** (the scrub) enforces R6 and R7 and removes any talk of our research ("according to our
  sources", dead websites).
- **Bridges to the real place:** GO Codes at markers, trailheads, theaters and storefronts (later).

---

## Phase 6: Lifecycle maintenance and self-healing

A loop back through Phases 1 to 3 that keeps what's published true.

### 6.1 Background workers and clocks

- **Clocks:** listings by band (R12); source pages and their facts by tier (§12, C6); guides every 30
  days; dossiers every 6 months; research briefs every year; events every month. **Every clock counts only time its
  workflow was switched on** (X1): paused time is stored per workflow (from `automation_log`), or due dates move on by it
  at switch-on, so a pause never makes finds "run out" (`AutoImport::exhausted`) or every listing fall due at once; the
  switch-on check shows each workflow's backlog and its order (0163 §10).
- **Events that start a check:** a source we read changes; a listing closes or moves; a name in a guide goes closed; a
  visitor reports something; a date in the text passes; search demand spikes; the season turns. Opening a guide raises
  its place in the queue; it never runs a check on the spot.
- **The background reader:** free checks first (links alive, linked names still open, dates not past, facts matching
  the catalog), the cheapest model only for what they flag; each piece gets a freshness score.
- **Outcomes:** **Refresh** (crawler-owned facts updated automatically); **Renew** (content the system wrote is
  re-researched, rewritten and re-scored under the same gates); **Review** (a person, only when their own content or a
  protected item is involved).
- **The engine needs a person: one Pulse case** (X3). Every item the crawler or the system raises for a person ("Possibly
  closed" on a claimed listing, R4's "check this?", "Moved or wrong address?", Listing Intake's out-of-options finds and
  search targets, the grey zone, a held email, the place store's sites waiting for a call, a nation's sites, a blocked
  site's Reactivate) is a step, each a button that does it (Confirm closed, Keep open, Merge, List anyway, Choose
  category, Clear email, Reactivate), grouped as `AutoImport::reason()` groups them and ordered by value; claimed
  listings' items first, and to the owner too (R4).
- **The retry ladder: never purgatory** (Jason, 2026-11-14: "waiting for review is purgatory, so we keep trying"; P3-3,
  P4-1). Whatever the engine retries for itself (the grey zone, gaps, sightings, out-of-options finds) is retried on its
  normal cadence, then waits longer after repeated fails, and keeps trying for good; **any new evidence resets it**. Its
  steps are settings in 0163 §12. Review still shows the item with its step; the system keeps trying in the background.
  The one exception is a site that blocks us: it waits for a person's Reactivate (§1.2, 5).
- **Topics:** a topic's summary and state of knowledge are refreshed when its facts change; topics asked for often
  first.
- **Freshness sweeper:** stale records to `HOLD_NEEDS_RECHECK` (still shown with "last checked", §3.4) and their URLs to
  the frontier at high priority (a blocked source excepted, §1.2, 5).
- **The identity recheck** (W2, task `recheck`; added 2026-11-15, Jason's words in the §3.2 note). After every data
  load (the ids it touched) and on a daily slice of all listings, it runs §3.1's check across live listings and acts on
  §3.2's note: exact twins it merges itself, variations it sends to "Vet this place", the rest to the identity queue.
  - **Which stays:** the most evidence (independent sources, then confidence), then the oldest, then the most views; the
    shown name the one people know (R11), the other kept as another name (0084).
  - **What folds in:** facts filling gaps only (R4), sources and the identity (R11), views and counts, comments and
    reactions, saves, guide and journey links, open suggestions, the Nexus, cleanup and "Help us find" notes.
  - **The old id** is hidden with `same_as`, so its link answers with a **301** to the kept one.
  - **Logged** in the data cleanup ledger (§6.1b) with Undo per pair and per run; an Undo records the pair as "not the
    same", never merged again.
  - It is a task on the switches (0163 §9: master, W2, the task), shipped off, its run and cost on the board (0163 §3);
    its slice and schedule are 0163 §12's. Data cleanup's `duplicate` rule and the live-pairs pass (`LiveDupes`) feed it
    instead of sending offers to Review.
- **"Vet this place": one entry point for checking one place's details** (2026-11-15). The tools exist, split across
  tasks: W1's `second_look` (a find with one source), W2's `identity` (who a listing is: its own site, NPI, Census, web
  search; `IdentityJobs`, a person's **Look now**), W2's `places` (Google, within its paced budget) and `geocode`.
  **W2's `identity` task becomes the one entry point** (confirmed by Jason, 2026-11-15: "W2's identity task is the entry point: this works") for a live listing, called by the recheck, the grey zone (§3.2)
  once a find is listed, and staff (Look now); `second_look` stays W1's look at a find before it's
  listed and settles fields the same way. A call names the place (or the pair) and the details in doubt; the task
  checks each one (name, address, phone, website, hours, open or closed): our anchors first (the identity record, the
  place store, NPI, the IRS list, Census, the open datasets), then Google's answers within allowance (0163 §12's check
  order), the collaborative score feeding confidence. Each field is settled from the evidence with its sources on the
  identity record (R11; `Identity::recompute()` on unclaimed listings, the owner offered it on claimed ones), each
  source used as its licence allows (keep or cite only, R9), nothing private to Gemini's free tier (§0.8). What it
  can't settle now waits on the retry ladder; what it can't settle at all goes to the identity queue. Toward verified
  NAP (§3.5).
- **The identity queue** (2026-11-15; a step kind in X3, not a new case, confirmed by Jason the same day): what the vetting can't settle, and any match where a listing is claimed or a
  person entered or decided something, as **steps of "The engine needs a person"** (X3, above; one kind, a step per
  pair), each with its reason and each side's evidence, and buttons that do it: **Merge** (choose which stays),
  **Keep apart**, **Make a sales lead**, **Make an admin task**.
  - **A sales lead** is the existing pipeline (decisions/0108): `Leads::route()` makes the stronger listing a prospect
    (source `crawler`) in its territory's rep's list with the question in the note, and puts it on the Call list
    (`Sales::toConfirm`); the call's outcome (`Sales::log`) answers the step, and a claim on the call ends it (the
    claimed listing decides, R4).
  - **An admin task** is the step assigned to a staff member, as the admin's other work is assigned (decisions/0109),
    done from the case.
  - **Public meanwhile:** both stay listings until merged or vetted; nothing is hidden while it waits. A visitor sees
    nothing new, except where the two disagree on a detail: that field shows how it's known (§3.5) with "being
    checked". Search shows both; the stronger first, the other under it when both answer the same search.
  - **Case #23's offers** move here (0163 §8, stage 8): each of the 60 is rechecked first; only what is left stays.
- **Quarantine worker:** re-checks grey-zone records on the retry ladder (free anchors first, then paid within budget);
  a person's answer ends it at once.
- **Canary for parser drift:** a site paused for drift is dry-run after 7 days and unpaused only if the new rules work;
  feeds the **"returns empty"** Pulse case (0163 §4). After two failed dry runs the case gets a step **Needs a reader
  fix**, naming the site and the empty fields, picked up by the crawler session's handoff; the site's facts keep showing
  with "last checked" (P6-1).
- **Credibility balancer:** a site's credibility falls on failures and contradictions and climbs back after clean batches
  (0157 §5's borne out and shown wrong).
- **Rural sources refreshed:** open place datasets reloaded with each release (monthly for Overture); licence registries
  re-read on their own cycle; a lapsed licence (or a dataset dropping a place) lowers confidence and asks "Still open?",
  never closes it (R12).
- **Service return:** a held service returns by itself, on 0163's ramp (§10, 2; live), **only for quota, back-off and
  budget holds**. **A ban or a block (a site or an outside service: 401/403/406/418, `AUTH_WALL`, robots, a challenge)
  is never retried by itself** (Jason, 2026-11-14: "blocked or banned should proceed with caution because we dont want to
  do more harm by trying again and again"; this replaces his earlier "go, automatic" for banned services). **A light
  status check is allowed, but never endlessly** (Jason: "ok with a status check on banned or blocked just not a retry
  endlessly"): only the provider's status page or one small request, never the real work, **once a day** (Jason,
  2026-11-14: "the daily status check is fine; if not lifted or active after 3 days a person is notified to evaluate").
  **After 3 days still blocked, a person is notified** (a Pulse step with the evidence) **to evaluate**: Reactivate, keep
  checking (daily or weekly), or stop checking. The daily checks pause until that person decides, so nothing repeats
  endlessly. When a check answers normally, the hold isn't lifted by itself either: the step says "looks lifted" with
  **Reactivate**, and a person decides (§1.2, 5); then the ramp. Values in 0163 §12.

### 6.1b Old data brought into line (Jason, 2026-11-14)

What the crawler made before these rules is checked against them by the **data cleanup tool** (0163 §8, stage 1b):
records that fail are quarantined, re-sourced or turned into sightings or "Help us find" cards by rule, never deleted;
what a person entered or decided is never changed by it (R4); every change is logged with an undo.

**Note (2026-11-15, releases #49–#54):** the data cleanup tool is built (Insights → Data cleanup): preview (#49), passes
by rule with a ledger and Undo (#50), nothing taken off left a dead end (#52; Jason: "Either they are reused or moved
into restricted"), why a pass left records as they are (#53), and the rest (#54). See 0163 §8's note.

### 6.2 Splitting and the audit trail

`SPLIT_CANONICAL`, triggered by an operator, separates a wrong merge into distinct records, keeping all history, and
undoes the edges and Nexus links the merge created.

### 6.3 Operations

How the engine is run, counted and switched is `decisions/0163` (§10 here).

---

## 7. The workflows through the phases

Each workflow (0160 §3) owns its trigger, steps, gates, output and report, and asks the crawler service for what it
needs; each has its own queue, share, report and costs (0160 §4) and its switch (0163 §9).

| Workflow | Phases | Under this record |
|---|---|---|
| **W0 Indexer** (new, Jason, 2026-11-17) | 1 (§1.1–1.4; §1.5's bulk loads stay with W1 and W14) | Phase 1's engine, always on in small batches, its own Railway service (not on a schedule): logs every URL found into the frontier, classifies new sites, keeps the verdict loop (promoted, normal, demoted, junk), reading priority, size guard, batches with receipts (§1.4's note); starts first in Start the system, stopped by Pause everything |
| W1 Listings | 1–4, 5.4 | leads in Run 2's order: Overture and NPI, then Google Places' check, with licences and OpenStreetMap (§1.5; Gemini never a finder, §0.8); public once past the "real business" floor, at its confidence (website, licence, collaborative score, owner: §3.5); published when ready (§5.4) |
| W2 Keeping listings current | 6 (via 1–3) | cadence by band (R12); closure signals (lapsed licences, dataset drops, Google, one site) lower confidence and ask "Still open?"; Closed only from the owner, our team, the public or the business's own site; "Still open?" built here (R12); the identity recheck, "Vet this place" (its `identity` task, the one entry point) and the identity queue (§6.1, 2026-11-15) |
| W3 Source register and place store | 1, 2 | reads the sources classified Read regularly (W0 finds and classifies them; a person's call overrides, never gates: §1.4's note); the dossier engine (§1.4); each licence registry and open dataset is a source with its licence (R9) |
| W4 Place guides | 4, 5.4 | written from public-safe facts and research; publishes at R10's guide score; the three protected levels (R7) |
| W5 Guides on demand | 4, 5.4 | a first look from what we hold; within its share of the daily AI allowance (0147; 0163 §7) |
| W6 Heritage and topics | 1–4, 6 | Library of Congress; vetting (§3.2); the Topic index (R15) |
| W7 Journeys | 5.4 | from a guide's places, by rules; openrouteservice; OpenStreetMap's details never copied into stops |
| W8 Find it | 3, 4 | names in guides and stories resolved against the catalog; Claude's web search for what's left (Gemini grounded search only if funded, from our vocabulary, never a member's names: §0.8); Find it's gate (§5.4) |
| W9 Landmarks and places on demand | 1–4 | the three protected levels; nations' land (0066); Wikipedia cite only |
| W10 Live events, W11 How-tos | 1–5 | official pages first; how-tos from official pages only, under their terms |
| W12 Search learning | 4.5 | searches feed the frontier; a search that found nothing records demand by place, kind or topic, never the words (§4.5); no grounded search unless funded (§0.8) |
| W13 Stories | 5.4 | person-written only (R5); named places to Find it, on Claude only (§0.8) |
| **W14 Authority sources** (new, Jason, 2026-11-14) | 1, 2, 6 | finds and stages each state's licence and registry sources in the source register ahead of its hubs (§7.1) |
| **W15 Funnel** (new, Jason, 2026-11-17; decisions/0170) | 4, 4.5 | places everything catalogued on the taxonomy (hub → zone → town → anchor → sub-group), active or planned, and tags its intent (Get Local, Let's Explore, Community); rolls each level's research up into that level's dossier for W4's guides; sends each level's gaps to W0 and W1; keeps each planned level's readiness (HubOpening); scheduled, and run as things are catalogued |

**The new parts and where they fit** (Jason, 2026-11-14; built in the order of 0163 §8, stages 7–11):

| Part | What it is | Phases | Used by | Managed by |
|---|---|---|---|---|
| Gemini | a service (Google AI Studio's free tier): reads public pages only, never a finder (§0.8) | 2, 4 | extraction (§2.3) wherever public pages are read (W1, W3, W14 among them); grounded search only if funded | AI use (per feature) and the register (0163 §1, §11) |
| Open data loads: Overture, Foursquare's open data | sources | 1, 2, 3, 6 | W1, W2 | W3 (the source register) |
| Licence sources | authority (Tier 1) | 1, 2, 3, 6 | W1, W2 | W14 (§7.1) |
| The collaborative score | a Phase 3 rule feeding confidence (§3.5, R12) | 3 | W1, W8, W2 | its weights in 0163 §12 |
| Vectors | an index (§5.2) | 4, 5, 6 | search, AI Assist, the Seeker | an upkeep task under Supabase's service card (0163 §1) |

### 7.1 W14: Authority sources, found and staged ahead of every hub (Jason, 2026-11-14)

**Why:** the rural test showed licences and registries are how real businesses without websites are proven (§3.5), and
each state publishes them differently. Researching them by hand when a hub opens is slow; W14 makes it part of the
phases, so opening a hub starts with its sources ready.

- **Started by:** a hub marked **Planned** on the readiness meter by a director (H1: `regions.md` is the plan, but the
  site can't read it); a schedule working through all states in waves
  (Arizona and New Mexico first; then Utah, Colorado, Nevada, California, Texas; then the rest); a person asking for a
  state; a source's yearly re-check.
- **Steps, through the phases:**
  1. **Find** (Phase 1): for each state and kind (company registry; sales tax or business licences; liquor; contractors;
     health permits, often by county; professional and trade boards; nonprofits federally), start from official
     directories of state agencies and national associations of those agencies (read by our crawler, Gemini reading
     the public pages; no grounded search unless funded, §0.8). Government domains (or a nation's own, below) are kept,
     and **a vendor-hosted page the agency's own government page links to** (the link kept as its provenance, Tier 1;
     H2); a person's call when no government page links to it.
  2. **Read its terms** (Phase 1): robots.txt, the site's terms of use and any data licence; whether automated reading
     is allowed; rate limits.
  3. **Classify** (§1.4): Tier 1 authority; how it can be read (bulk file, API, lookup page) and **its real use** (Run 2): **bulk data**;
     **lookup only** (paced); **blocked** (a Cloudflare challenge or a ban: a person, or a public-records request for the
     list as a file; held under §1.2, 5); or **claim-time check** (a lookup by a number the owner gives, as Arizona's
     TPT); what it proves (exists,
     open, kind of business, location); how often it changes; the fields it gives.
  4. **Test the reader** (Phase 2): read a handful of records (a few, politely) to confirm the fields come out; nothing is
     kept beyond the test.
  5. **Stage** (source register): a row per source, **status staged, switched off**, with everything above; terms that
     are unclear or forbid automated reading go to a person (one Pulse case per state, a step per source).
  6. **Keep fresh** (Phase 6): re-checked yearly, and again when the state's hub is planned; a source that moved or
     changed its terms is flagged.
- **Tribal nations:** each nation's own business, licensing or enterprise sources are found and staged the same way, but
  read only after a person's call and under the nation's rules (0066, R7, §12 C12). State records rarely reach nations'
  land (the rural test, Kayenta).

**Note (2026-11-17): when the official licence source is blocked or lacking** (Jason: "it seems like official sites for
license are lacking so we may need a strategy to solve the issues"; written for his OK, nothing built yet). Found while
building 0163's source map: Arizona's Corporation Commission moved to `arizonabusinesscenter.azcc.gov`, which answers our
reads with Azure's "Access Blocked" page; New Mexico's Secretary of State search couldn't be reached; azliquor.gov sits
behind a Cloudflare challenge; and neither publishes an open bulk file (research/rural-test-2026-11-14.md §8). R-rules
stand: a blocked site is never worked around, and the licence decides the use. So, for each state and kind, in this order,
each step recorded on the source's W14 row as its **real use**:

1. **Ask the agency for the official route.** A public-records request for the register as a file (Arizona's public
   records law, A.R.S. §39-121; New Mexico's Inspection of Public Records Act), and in the same letter, whether an API or
   an allowance for our host exists. Free or at cost, the terms in writing, and a file is bulk data (refreshed by asking
   again on a schedule the agency accepts). W14 drafts the letter per source; a director sends it; the answer is kept on
   the row. Arizona's Corporation Commission and New Mexico's Secretary of State first.
2. **The claim-time check** (§7.1, 3, as Arizona's TPT): when an owner claims or adds a business, they give its registry
   number and our team looks it up once by hand on the agency's own page. No automated read, so nothing is worked
   around; the fact is kept with the source and the date, and the listing gains the registry's weight.
3. **Other public authorities that overlap** (free, public domain, already ours or close): NPI and the IRS list (we hold
   both), Recreation.gov, the federal SAM.gov entity register (businesses registered for federal work; free key, its
   terms read first), county permit and inspection lists where a county publishes them, and licence boards that do
   publish files. W14 stages each with its fields; together they cover part of what the state register would.
4. **A licensed aggregator, only as a paid backup by override** (0163's source map, step 3; PaidOverride): off until a
   director sets its cap, used only when 1–3 gave nothing for a business being confirmed. Only one that (a) states its
   right to the data (an official feed or public-records requests), (b) lets us keep and show what we use, with credit,
   and (c) is priced per call. Candidates as of today, terms not read yet (their sites are outside this environment's
   network): Palm (getpalm.com: registry search across Secretaries of State, 100 free calls, then per call), Topograph
   (Arizona in beta, by request), Cobalt Intelligence. **Never** one that scrapes past an agency's block (a "catalog"
   of a site that blocks bots, such as anakin.io's page for the Arizona register): that is the same as working around
   it ourselves.
5. **Otherwise the listing stands on what else it has** (§3.5: its own website, two sources, a government page, local
   people's "Yes"), with its confidence shown, and the pre-flight and the hub readiness meter say **"no authority source
   yet"** for that state and kind, so the gap is seen.

To build once approved: W14's row gains the five steps' state (asked, answered, file received, claim-time, aggregator);
the letter template; the claim form's registry number field and its one-look task for our team; SAM.gov's reader
after its terms; the aggregator as a third `PaidOverride` need ("Confirm a business: licensed registry") after its
terms are read and a director chooses one.
- **Gate:** government (or a nation's own) sources, or a vendor page one of them links to (H2); terms read; a person's
  call where unclear.
- **Makes:** staged rows in the source register, ready for a hub; the state's readiness line in the hub meter (R17).
- **Cost:** free sources and Gemini's free tier only; its switch is off until Jason turns it on (0163 §9).

**Note (2026-11-18, releases #60, #117):** W14 is added paused (#60); its own finding of sources isn't built, and
Arizona's and New Mexico's were staged by hand. At Start the system, a hub's licence sources whose terms aren't read
are listed on the card, each with Read its terms and Terms read: allowed, done there (#117).

## 8. Mapping onto what exists

| The phases' name | What we have today |
|---|---|
| `crawl_frontier` | the page log (0157 §1) and the crawl queues (`crawl_jobs`, `town_crawl_jobs`, `crawl_requests`) |
| `restricted_registry` | new, joined to the source register's Ignore and the page log |
| `source_dossiers` | the source register (`reference_sources` with `source_rules`), extended with tier and credibility |
| `crawl_audit_log` | new, append-only |
| `extracted_items` with provenance | the place store (`source_facts`), the raw index (`crawler_raw_index`), the identity record (R11) |
| `entities` and the identity database | listings, landmarks, outdoor places, topics, linked by identity records (R11) |
| the manual review queue | Review and Listing Intake |
| the API verification worker | the second look (`second_look`, `identity`, `places` tasks) and the second tier (0157) |
| the thresholds | Thresholds & gates (`Thresholds`, R10) |
| open place datasets and licence registries | new bulk loads beside `npi_bulk`, `irs_bulk`, `rec_bulk` (an Overture load, a Foursquare load, a licence load per registry) |
| Gemini | a new provider in `AiClient` (beside Claude, Google Document AI and AWS) and a service in the register (0163 §1) |
| the vector index | a new sidecar service (Supabase's free tier first, 0163 §8 stage 11), its own row in the register |

## 9. Jason's decisions on the draft (2026-11-14), applied

1. **Page copies:** a fingerprint and the quoted snippet only; no whole pages (§1.3, §2.4). *Accepted.*
2. **Emails on business pages:** posted with a business contact present; a random email kept for Review and restricted
   (§2.2, §3.4). *Decided.*
3. **Stale records** stay public with "last checked", withheld only on a real sign of closure (§3.4). *Accepted.*
4. **Semantic search:** a sidecar `pgvector` index, Supabase's free tier first or Railway (§5.2). *Confirmed by Jason
   2026-11-14 (§12, C10).*
5. **Where this lives:** this record, with 0133's rule book brought in; 0133 closed and pointed here; older records that
   align pointed here (§11); 0163 stays as operations underneath. *Decided.*
6. **OpenCorporates:** not used (§1.5, §3.2). *Accepted.*
7. **Gemini:** free tier only, no billing linked; **reads public pages only, never a finder** (revised 2026-11-14 by
   the rural test's Run 2: the free tier has no grounding; grounded search only if Jason funds it, §15); Claude for
   writing and anything private; when the allowance runs out the work waits (§0.8, §2.3; run as 0163 §11). *Accepted;
   revised.*
8. **Businesses without a website or licence:** a find is public once it passes the "real business" floor; the
   collaborative source score feeds its confidence, with no threshold; "Not yet confirmed by the business" until the
   owner claims it (§3.5). *Decided; revised 2026-11-14 ("we dont restrict listings we add confidence to them").*
9. **Open place datasets:** Overture and Foursquare's open data as corroboration, once their licences are confirmed
   (§1.4, §1.5, R9). *Accepted.*
10. **The rural research test:** run now; Kayenta, St. Johns, Springerville (§1.5). *Accepted.*

## 10. Operations underneath: `decisions/0163`

0163 is the operations layer under every phase and workflow here, a separate record (its sections: §1 the service
register, §2 costs, §3 the workflow board, §4 warnings and "returns empty", §5 watching, §7 Anthropic's settings, §9 the
switches, §10 switching back on and the switch-on check, §11 Gemini, §12 the collaborative score's weights). This record says what the engine does and the
rules it keeps; 0163 says how it is run, counted and switched, and holds every number for it. New services named here (the licence registries,
the open datasets, Gemini, the vector index) each become a row in 0163's register before they're called.

## 11. What this replaces and amends

- **Supersedes `decisions/0133`** in full: its rule book is R1–R19, its six phases are §1–§6 here; its text stays as
  history (build notes included).
- **Amends** (a dated note at the top of each names the section here that now governs that part; their other content
  stands): 0001, 0005, 0012, 0024, 0043, 0044, 0045, 0048, 0054, 0061, 0062, 0065, 0066, 0067, 0070, 0071, 0072, 0073,
  0076, 0081, 0083, 0084, 0085, 0086, 0110, 0131, 0134, 0137, 0138, 0142, 0143, 0145, 0146, 0147, 0148, 0149, 0152,
  0157, 0160.
- **Works under it:** 0163 (§10).
- In the platform spec, the sections 0133 withdrew (§11, §15, §21, §30) stay withdrawn.

## 12. Conflicts with older records: settled (Jason, 2026-11-14, "accept your suggestions")

Found while writing this record and settled by Jason on the session manager's suggestions. Where an older record
disagrees, this section governs; the older text stays as history.

- **C1. Witnesses.** Google confirming open, a Facebook page and OpenStreetMap are **never stored facts and never in the
  truth hierarchy**; they add weight through §3.5's collaborative score, which feeds confidence. After establishing,
  only Google's open or closed counts, as a signal (R12); Facebook and OpenStreetMap don't (§14 point 1).
- **C2. The IRS file** is a government record: a match **establishes** a nonprofit's listing (rural churches and
  charities) and its stated facts (name, address) are stored and shown with the source. 0133's "confirms, never shown,
  never creates a listing" is replaced.
- **C3. NPI** is a government source: **alone it establishes** a listing (0160 W1, Jason 2026-11-13). 0133's "needs one
  more" is replaced. Run 2's two guards apply (§1.5): departments fold into their parent; non-public kinds aren't shown
  as places to visit.
- **C4. Bulk loads:** a licence or registry match **establishes a listing alone**, as NPI and the IRS file do (§14 point
  2); an open dataset's record is public once it passes the "real business" floor (§3.5); both publish through readiness
  (§5.4) at their confidence, shown "Not yet confirmed by the business" until the owner claims it. 0133's "a bulk dataset
  is never an automatic publish" is replaced: each record still passes the floor and readiness.
- **C5. The Nexus:** **0133's rule stands**: never on a similar name alone; a Nexus is made only when locations share a
  website, main phone or brand record. The draft's "name shared over 250 m" is not adopted.
- **C6. Freshness:** listings keep R12's cadence (monthly to quarterly by band, never over 90 days); the tier clocks (90
  days Tier 1, 30 days Tier 2) apply to **source pages and their facts**, beside it.
- **C7. Closure signals** (one site's "closed", a lapsed, dissolved or revoked licence, a dataset dropping a place,
  Google closed) are recorded and lower confidence, with "Still open?" asked; **Closed** is shown only from the owner,
  our team, the public or the business's own site (§15) (R12; Jason, 2026-11-14). 0072's and 0133's closing rules, and 0160 W2's
  "scoring decides closure", are replaced.
- **C8. The AI monthly cap** is the one set 2026-11-14 in 0163 §7 (`AI_MONTHLY_CAP_USD`); older records' figures are
  replaced.
- **C9. Warning levels** are the ones Jason set 2026-11-14 in 0163 §4; older records' levels are replaced.
- **C10. Semantic search:** a sidecar `pgvector` index on **Supabase's free tier first** (Decision 4 confirmed); Railway
  only if revenue covers it. Built in a later stage.
- **C11. Gemini's allowance used:** the work **waits** (Decision 7); no fallback to Claude (run as 0163 §11).
- **C12. Tribal nations' sites:** Tier 1 for what they state, **read only after a person's call** (0157 §7). Both kept.
- **C13. Business emails:** a sole proprietor's personal-provider email is posted **only when the business published it
  in its own contact section** beside a business contact, in their business role (Decision 2); the owner can remove it
  at any time. Otherwise kept for Review and restricted.

## 13. Next

Jason approved this record and 0163 on 2026-11-14 ("all approved"). The build follows 0163 §8 (the operations stages,
then stages 7–11 for this record's new parts), each a release he approves. **The crawler and the automation stay paused**
until those stages and the switch-on check (0163 §10) are done and Jason switches them on. The rural research test
(§1.5) ran 2026-11-14 (`research/rural-test-2026-11-14.md`); its Run 2 ran the same day and tuned the weights; Jason's decisions on it and on the gaps review are §15.

## 14. Found in the start-to-finish check: settled (Jason, 2026-11-14, "all approved")

Found by reading 0164 and 0163 as one story (Jason, 2026-11-14: "remove dups and ensure that we have not created
conflicts in understanding how the knowledge engine works start to finish"). **Settled 2026-11-14**, applied above:

1. **Google after establishing:** only Google's open or closed counts, as a signal (R12); Facebook and OpenStreetMap not
   (C1). *Accepted.*
2. **A licence match** establishes alone, like NPI and the IRS file (§3.5, C4). *Accepted.*
3. **Seeding** (R17): leads checked at the business's own site, a registry, or established by the floor and its sources.
   *Accepted.*
4. **A listing without a keepable address or phone** stays a sighting (§3.5). *Decided.* (Refined in §15: verified
   NAP is the goal; the name-and-town card is pending Jason's choice.)
5. **The collaborative score** is no longer a gate with a threshold: it feeds confidence; its weights are settings in
   0163 §12, from the rural test, tuned by its Run 2 (§3.5, R10, C4). *Decided (Jason: "we dont restrict listings we add
   confidence to them").*
6. **Find it** finds pass the floor and get confidence; the Find it score only orders the work (§5.4, R10). *Accepted.*
7. **The switch-on check:** 0163 §10's check renamed; W14's staged sources are a line of the hub meter, which it reads.
   *Accepted.*
8. **The hub's opening budget** lives in 0163 §2 and the register; the targets stay in R10. *Accepted.*
9. **A director moves a hub's stages;** a state's first hub stays Planned until its R8 record is signed (R17).
   *Accepted.*
10. **AI Assist's allowances** moved to 0163 §7. *Accepted.* (R18's politeness pace stays here as a fixed guardrail.)
11. **The two dead-website clocks** spelled out (§1.3). *Accepted.*
12. **Sources resting on the same primary** count once, unless we read the primary (§3.2). *Accepted.*
13. **Lapsed, dissolved, revoked:** curators, not regulators; recorded, confidence drops (more for dissolved or
    revoked), "Still open?" asked; Closed only from the owner, the public or the business's own site or sign (R12, §3.5,
    W2, C7). *Decided (Jason: "Safe and caution verse restriction").*
14. **One build order** in 0163 §8, this record's new parts as stages 7–11 (§7's table). *Accepted.*
15. **Gemini follows Claude's and the paid APIs' standards:** per-feature switch, model and provider, runs a day and
    budget on AI use; a director changes a feature's provider after the sample comparison; its allowance monitored,
    paced, warned (revised in §15, F4: on what's unexpected, not on use), with about 20% a day held for on-demand work (0163 §7, §11; R13). *Decided.*
16. **W6's phases** are 1–4 and 6 (§7). *Accepted.*

**No shadow mode** anywhere (Jason, 2026-11-14).

## 15. The gaps review and the rural test's Run 2: settled (Jason, 2026-11-14, via the session manager)

Jason decided on `research/knowledge-engine-gap-review-2026-11-14.md` (29 findings and §F's five items) and on the rural
test's Run 2 (`research/rural-test-2026-11-14.md` §8). **Every finding and §F item is accepted as the review suggests,
except the ones he changed** (marked ✎). Applied in place above; numbers and settings in 0163 (§7, §10, §11, §12); the
code fixes are in 0163 §8's build stages. Documentation only: no site code, no release.

| Item | Decision | Here | 0163 / stage |
|---|---|---|---|
| X1 clocks while paused | count only switched-on time; backlog shown at switch-on | §6.1 | §10; before switch-on |
| X2 W1 runs 0133's gate | switch-on check line; `evaluate()` and the old gates retired as gates | §5.4 | §10; stage 8 |
| X3 Review items with no prompt | one Pulse case, "The engine needs a person" | §6.1, R4, §5.4 | before switch-on |
| X4 AI cap shares | shares per workflow, unused share passes on | — | §7; stage 3 |
| X5 AI Assist's two homes | Thresholds rows read from AI use | — | §7; stage 2 |
| P1-1 page never read | `PENDING_EXTRACT` | §1.1, §0.8, §2.3 | stage 9 |
| ✎ P1-2 a site that blocks us | **no automatic retry**: held, a Pulse step with **Reactivate**; re-read only after a person reactivates | §1.2, §1.3, §3.4, §6.1 | before switch-on |
| P1-3 zero-result searches | demand by place, kind or topic, never the words | §4.5, W12 | stage 8 |
| P1-4 leads that never resolve | new → read → witness only → expired | §1.5 | stage 8 |
| P2-1, P2-2, P2-3, T1 Gemini and private data | one guard in the Gemini client: private details removed first, our vocabulary only, protected names never sent, input class per feature | §0.8, R5, R7, R13, §2.3 | stage 7, **before Gemini's stage 9** |
| P3-1 floor vs readiness | one rule: "complete" = the floor + a point (the town's middle, marked approximate) | §5.4 | stage 8 |
| ✎ P3-2 sightings / NAP | **verified NAP is the goal**; listings start with part of it and are refined by sources, the owner and people; each NAP field shows how it's known; the floor stays; sightings on the retry ladder; a name-and-town find gets a public **"Help us find this business"** card (Jason, 2026-11-14) | §3.5, R14 | stage 8 |
| ✎ P3-3, P4-1 no purgatory | **the retry ladder**, never stopping; new evidence resets it; Review still shows the step | §3.2, §4.5, §6.1 | §12 (ladder values); stage 8 |
| P3-4 claim band | a claim keeps its band; against lowers within it | R12 | stage 8 |
| P3-5 owner not told | portal "Check these" + system email | R4 | stage 8 |
| P4-2 licence changes | stored facts re-sorted | R9 | stage 8 |
| P5-1 our team may close | a call, a visit, a photo of the sign | R10, R12 | stage 8 |
| P5-2 "Still open?" | built with W2; Call-list step after 30 days or two signals | R12, W2 | stage 8 |
| P5-3 story-only landmarks | a Wikipedia article alone → story only; fully public only when the managing authority's page says open to visitors; archaeological words held; CLAUDE.md's 2026-11-12 line updated | R7 | before switch-on (live today) |
| P6-1 parser drift | "Needs a reader fix" step after two failed dry runs | §6.1 | stage 8 |
| H1–H5 hubs | Planned on the meter starts W14; vendor pages linked from a government page kept; open hubs' new sources switch on from their card; a "Hubs" Pulse case; the opening budget line | §7.1, R17 | §10; stage 10 |
| F1 −40 / −60 | moved to "Signals against" | R12 | §12; stage 8 |
| F2 the public's Closed | 3 devices, 2 days, 60 days; our team's call first when no machine signal came first; kept in R10 | R10 | header; stage 8 |
| F3 card labels | three states only | R12 | stage 8 |
| F4 Gemini warnings | warn on the unexpected, not on use | §14 point 15 | §11; stage 9 |
| F5 Google's check from 35 | a setting, with Run 2's order before it (below) | §1.5 | §12; stage 8 |
| ✎ Facebook as a NAP source | R14 stands (our crawler never reads facebook.com); a NAP item may come (a) through Overture's Meta record or (b) from a person citing the public Facebook URL; it fills or verifies at that source's confidence | R14, R9 | stage 8 |
| Run 2: Gemini | **reads public pages only, never a finder**; weight 0; grounded search only if Jason funds it | §0.8, §1.5, §2.3, §3.2, §7, Decision 7, W1/W8/W12/W14 | §11, §12; stage 9 |
| Run 2: lead order | Overture and NPI first, then Google Places' check (40 on a phone match, 30 on the address only); NPI's two guards | §1.5, C3 | §12; stage 8 |
| Run 2: Arizona's licence sources | each source's real use: bulk data, lookup only, blocked, claim-time check; ACC's new host | §1.5, §7.1 | stage 10 |
| Run 2: our coverage gap | Kayenta 0 listings; St. Johns 13 of 45; Springerville/Eagar 29 of 94 | — | stage 8's goals |

**F5 and Run 2 together:** Run 2's lead order sends Google's check to finds without a website that have a phone, which
mostly start at one witness (20–25, below 35). So the check order in 0163 §12 runs Run 2's group first, then F5's
"from 35", with F5's two exceptions; both are settings a director tunes.
