# ADR 0043: A Discovery Guide at Every Level of the Place Taxonomy

**Status:** Accepted (2026-09-28)

## Context

`commercial.md` §7 funds "Foundational Macro & Geo-Hub Guides": long-form structural content per Strategic
Sub-Hub. `decisions/0042` then extended the place taxonomy to five levels (Continental Hub → Geo-Hub →
Cluster → Anchor → Sub-Group). The live placeholder landing page (`hub/index.php`) already walks the first
three levels with real names, town lists, accepted sub-groups, a listing count and a link into the
Directory, but it has no guide content. Nothing so far said which levels get a guide, what a guide holds,
or how guides relate to local listings.

## Decision

**Every level of the taxonomy gets its own Discovery Guide, sub-groups included.** A guide is that place's
"knowledge": what it is, why it matters, and the data that describes it. The crawler builds and maintains
guides.

**One skeleton at every level, scaled to the place:**

- **Story:** the narrative of the place, anchored in the Living Geography and real historical timelines
  (`commercial.md` §7 Editorial Content Blueprint).
- **Place Profile:** a data block in the same shape at every level: population and demographics, towns,
  land area, and at the levels where Census detail exists, age, income, jobs by industry and housing.
  Figures come from public sources (Census/ACS by ZIP area, county, and place), rolled up over the ZIPs
  that make up the level.
- **Local:** listing counts (by category where available) and a link into the Directory filtered to this
  place. Discovery keeps this route to local listings at every level where a list is useful.
- **Chameleon picks:** the Chameleon Filter (`decisions/0003`) weaves the listings that fit best into the
  story itself, chosen by relevance, proximity and type of experience. This is in addition to the Local
  link, not a replacement for it.
- **Next stops:** links to the level below.

**What each level emphasises:**

| Level | Example | Story | Profile | Local |
|---|---|---|---|---|
| Continental Hub | Ancient America | The region as a whole: plateau, cultures, why it's one region | Totals: population, states, counties, zones | Counts only, no list (too broad to be useful) |
| Geo-Hub | Ancient Borderlands | The narrative corridor (Authority Engine, `architecture.md` §22) | County-level detail (age, income, industries, housing) | Counts plus Directory link; a few zone-wide picks |
| Cluster | Show Low | How the towns work together: where people shop, eat, get services | Rolled up over its ZIPs; its towns | Main Directory entry point; densest, proximity-led picks |
| Anchor | Show Low (85901) | The town itself: history, civic identity | Town-level: incorporated status, county seat, place data | The town's own listings |
| Sub-Group | Pinetop Lakeside, Bourdon Ranch | What locals mean by the name; the lifestyle (ranch, lake, off-grid) | Usually no Census detail: listings using the name, parent cluster's figures for context | Listings that use the name |

**Review.** Guide stories are System-Created Content under `decisions/0005`: the crawler drafts, a person
approves before publication. Sub-group guides stay within what local usage supports (`decisions/0042`
amendment) and are never written about a sub-group that hasn't been accepted.

## Consequences

The landing page at each level becomes a guide page rather than a placeholder, and the anchor and
sub-group levels, which currently have no page, need one. The URL question `decisions/0042` left open
(nested listing URLs versus `/listing/{slug}`) now also covers how anchor and sub-group guides are
addressed, since `decisions/0013` makes only the first three levels route segments.

**Already live:** names, town lists, accepted sub-groups, population per ZIP, listing counts and the
Directory link.

**To build:** the guide stories and their review queue (a prerequisite per `decisions/0021`); the fuller
Census profile; anchor facts (county seat, incorporated status), which the anchor-resolution rule in
`decisions/0042` needs anyway; listing counts by category; and the Chameleon picks.

**Open, not decided here:**

- ~~Whether the Place Profile's figures refresh automatically or go through sign-off.~~ **Settled
  2026-09-28:** figures are published as the source gives them, including small places with wide margins
  of error, **as long as the source is cited**. Every profile names the survey and tables, a single area's
  figures show their 90% margin of error, and medians combined across ZIPs are marked approximate. No
  sign-off step for figures; stories still need one.
- Whether Chameleon picks need review. They choose among listings that are already public, which is
  closer to search ranking than to publishing.
- Target length per level. `commercial.md` §7 sets 2,000–3,000 words for Geo-Hub Guides; the smaller
  levels presumably need less.

## Implementation note (2026-09-28): the first Place Profile

Built from the American Community Survey 2019–2023 5-year estimates: population (B01003), median age
(B01002), median household income (B19013), poverty (B17001), bachelor's or higher (B15003),
unemployment (B23025), housing units (B25001), seasonal/second homes (B25004 line 6, vacant for seasonal,
recreational or occasional use), median home value (B25077) and top industries (C24050). Stored per ZIP
area, county and state in `place_profile`, loaded from Admin → Cluster tools with the server's Census API
key, and shown on the hub, geo-hub and cluster pages beside the county and state for comparison. Counts
and rates roll up exactly over a place's ZIPs; medians are population-weighted across ZIPs and marked ≈.
The Census's negative "not available" codes (e.g. medians for PO-box ZIPs) are treated as missing.

First reading for the pilot: the Show Low cluster is 29% seasonal homes against 5.5% statewide, and
Pinetop (85935) alone is 54% with a median age near 60, while Snowflake (85937) is 5% seasonal with a
median age of 32. That split, resort and second-home versus year-round family, is the kind of signal
the guides and the Chameleon Filter can use.
