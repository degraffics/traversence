# ADR 0048: Continental Data Plan

**Status:** Proposed (2026-09-30), for Jason's decision. Builds on `decisions/0004` (phased infrastructure),
`decisions/0005` (people approve system-created content), `decisions/0043` (guides), `decisions/0044`
(confidence-gated auto-import) and `decisions/0045` (our own reference index, source-first crawling).

## Context

The pilot runs on one continental hub (Ancient America) with one live geo-hub, a few thousand listings and a
MySQL database on Bluehost shared hosting. The plan is 20 continental hubs. At that scale three things change:

- **Where listings come from.** Crawling site by site and confirming each place with a web search does not
  scale to tens of millions of places. Free bulk datasets do, and `decisions/0045` already makes the crawler
  source-first.
- **Where the data lives.** Shared MySQL is fine for the pilot and a few regions. It is not built to hold or
  search tens of millions of places and their references.
- **Who approves the stories.** The data parts of a guide (Census profile, Living here, Support & wellness,
  listings, groups) already fill in by themselves. The stories need a person (`decisions/0005`), and heritage
  and tribal content needs particular care (`decisions/0043`). People, not data, are the limit.

## Decision

### 1. Build on public and permissively licensed bulk data; cite everything

| Dataset | What it gives | Terms (confirm before loading) | Use |
|---|---|---|---|
| NPI Registry (NPPES monthly file) | Health-care providers and organisations, with addresses | US government, public | Load; a trusted registry under `decisions/0044` |
| HRSA health-center sites | Community health centers | US government, public | Load; trusted registry |
| IRS Exempt Organizations (Business Master File) | Nonprofits, churches, community groups | US government, public | Load; a reference, not proof a place is open |
| Census (ACS, TIGER, tribal-land geographies) | Place profiles, boundaries, tribal-land areas | US government, public | Already used for profiles; add tribal-land areas for the housing wording in `decisions/0043` |
| Recreation.gov / RIDB, NPS, USFS | Public recreation areas, campgrounds, visitor centres | US government, public | Load for guides and public places |
| Overture Maps places | Businesses and points of interest at national scale | Open (a permissive data licence for places at last check) | The main bulk base for businesses, if the terms hold |
| OpenStreetMap | Places, with local detail | ODbL: attribution, and databases built from it may have to be shared on the same terms | Reference and citation only ("found in OpenStreetMap"), not the base of our stored index |
| Commercial directories (Yelp, MapQuest, BBB…) | — | Terms forbid copying | Cite only, as today (`decisions/0045`) |

- **Terms are checked before each dataset is loaded**, from the publisher's own page, and recorded here with
  the date. The table above is a starting point from memory, not a legal reading. OpenStreetMap's share-alike
  and Overture's licence are the two that decide what Traversence can keep private and commercial.
- **Facts only, always cited:** name, address, phone, category, source and date. No copying of descriptions,
  reviews or photos.
- **Bulk rows go through the same door as the crawler:** into staging, where the `decisions/0044` guardrails
  decide what publishes. A bulk dataset is one reference, not an automatic publish.
- **The rules don't change at scale:** tribal lands as sovereign nations, sacred or restricted sites never
  pinpointed, public places and events only (`decisions/0043`).

### 2. Load region by region, as each hub opens

1. The Ancient America states: Arizona and New Mexico first (the pilot), then Utah and Colorado as their
   geo-hubs open (`regions.md` lists all 15 zones; 14 are "Coming soon" on the site).
2. Each further continental hub's states, when that hub is scheduled to launch. Nothing is loaded "just in
   case": data that no page shows is cost without value.
3. Loads run on the worker (Railway), not through the website's PHP, and send rows in batches the site
   already accepts.

### 3. Move to Postgres when a trigger is hit, not before

Stay on Bluehost MySQL (`decisions/0004`) until **any one** of these happens:

- the reference index and staging together pass about **2 million rows**;
- directory search or place pages regularly take more than **2 seconds**, after indexing;
- a bulk load can't finish within shared hosting's limits (import timeouts, disk);
- a **second continental hub** is scheduled to launch.

Then the listings, reference index and staging move to managed Postgres (Supabase or Railway Postgres), with
PostGIS for location queries, following `decisions/0004`'s phased approach: data first, the website keeps
running on Bluehost until it too has a reason to move. The code already reaches data through a few library
classes, which keeps the move contained.

### 4. Stories at scale: a batch review queue, never auto-publish

- **Place pages don't wait for stories.** A page without a story still has its profile, Living here,
  Support & wellness, listings and groups. The story is added when it's approved.
- **The crawler drafts; people approve** (`decisions/0005`, `decisions/0043`). Drafts join the Stories list
  (Admin → Crawler → Stories), marked as crawler-drafted, with the same checklist before publishing.
- **Review order:** continental hub first, then its geo-hubs (few, most-read), then town clusters by how many
  listings and visitors they have.
- **Heritage and tribal content** is reviewed by someone with that responsibility, and offered to the
  nation's own tourism or cultural office wherever one exists. Nothing about a nation is published on
  volume alone.
- **Capacity sets the pace:** if reviewers can approve 20 stories a week, 20 are drafted a week. Drafts are
  not stockpiled.
- Drafting needs an AI writing service, under the AI-consent and cost rules (`decisions/0010`). Choosing one
  is a separate decision.

### 5. Search stays the last resort

With bulk data and our own index checked first (`decisions/0045`), paid web search only covers what no source
covers. Its cost is watched per month in Admin → Crawler.

## Consequences

- Listings at national scale come mostly from public data, with the crawler filling gaps and people's own
  contributions and claims on top.
- The choice between Overture and OpenStreetMap as the base is made on licence terms first. It decides whether
  the listing database can stay Traversence's own.
- Postgres arrives on a measurable trigger, so the move happens when it pays for itself.
- Stories grow at the speed people can review them well. This is slower than the data, on purpose.

## Open

- Confirm current licence terms for Overture places and OpenStreetMap, and record them here.
- Who reviews stories touching tribal lands, and how nations' offices are approached.
- Which AI writing service drafts stories, and its monthly budget.
- Whether the 2-million-row and 2-second triggers are right once real load numbers are in.
