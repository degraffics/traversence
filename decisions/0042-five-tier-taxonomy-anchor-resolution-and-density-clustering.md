# ADR 0042: Five-Tier Geographic Taxonomy, Anchor Resolution, and Density-Based Auto-Seeding

**Status:** Accepted (2026-09-28)

## Context

The taxonomy locked by `decisions/0013-consolidated-routing-directory-discovery-entry-points.md` and
`architecture.md` §19 has three addressable tiers — Continental Hub, Geo-Hub, and Micro-Cluster — with
one shared nested route (`/[hub-slug]/[geohub-slug]/[cluster-slug]`). Micro-clusters are meant to bundle
3–5 neighboring towns into one local pool (`traversence-platform-spec.md` §2), but the live auto-seeding
logic (`api/lib/CrawlerIngest.php`'s `resolveMicroClusterId`) does not do that: it creates one new
micro-cluster per unmatched incoming ZIP code, with no radius or density logic at all. A live phpMyAdmin
check found 38–43 of these single-ZIP stubs, each named a placeholder like "ZIP 85901 (auto-seeded, needs
review)" (`traversence-platform-spec.md` §15, §19). The prior decision on these rows
(`traversence-platform-spec.md` §19) was to leave them as-is under Ancient Borderlands indefinitely, on
anti-fragmentation grounds — a decision made before any real multi-town clustering algorithm existed to
re-run them through.

Two other gaps existed alongside this: nothing in the taxonomy resolves *which* of several towns in a
cluster is its primary/anchor town when more than one candidate exists, and nothing handles unincorporated
subdivisions, ranch communities, or colloquial place names (e.g. "Red Sky Ranch") that share a ZIP with an
incorporated town but have no municipal identity of their own.

Jason supplied a full design ("Universal Hub & Cluster System") addressing all three gaps, confirmed against
two follow-up clarifications: the exact slug-generation formula and canonical listing URL, and that the
existing single-ZIP auto-seeded stubs are to be re-clustered/merged once the new algorithm exists, not
grandfathered in.

## Decision

**The taxonomy gains two tiers beneath Micro-Cluster, for five in total: Hub → Geo-Hub → Cluster → Anchor →
Sub-group.** Only the first three remain independently addressable in the URL; Anchor and Sub-group are
resolved data, not new route segments. The canonical URL stays exactly what ADR 0013 already locked, now
with the listing itself nested under its cluster:

```
domain.com/[continental-hub]/[geo-hub]/[cluster-slug]/[listing-id-or-slug]
```

**Micro-Cluster auto-seeding becomes real spatial clustering, replacing the one-ZIP-per-stub behavior.**
`resolveMicroClusterId` is rewritten to group incoming coordinates using a 100-mile proximity radius and a
target 150,000 population ceiling, bounding a pool of neighboring towns rather than stubbing a cluster per
ZIP. **The existing 38–43 single-ZIP auto-seeded clusters are re-clustered/merged under the new algorithm
once it ships** — this supersedes the prior "leave them as-is" call in `traversence-platform-spec.md` §19;
that call was reasonable only in the absence of a real clustering algorithm to run them through, and one now
exists.

**City/Municipal Anchor is a resolved field on the micro-cluster, not a new route segment.** When a
cluster's town pool has more than one candidate, an automated resolution sequence picks the anchor in this
order: county seat status (highest precedence), then incorporated-municipality ranking (population/status),
then a centroid or lowest-numeric-ZIP fallback. The Single Home Rule is enforced at this tier: every listing
binds 1:1 to its cluster's resolved anchor, locking `primary_zip` and mapping secondary ZIPs to the same
profile.

**The anchor directly produces the cluster's slug.** The formula is:

```
slug = "cluster-" + tier_id + "-" + lowercase(anchor_name)
```

e.g. `cluster-1a-st-johns` — consistent with the pilot's existing real slug for Cluster 1A. The
colon-delimited composite tag (`hub_[id]:geo_[zone]:cluster_[id]`) remains internal-only, unchanged from ADR
0013 — it is a lookup/dedup key, never rendered in the address bar or the new slug formula.

**Extension Layer A — algorithmic overrides for high-value, low-density outliers.** Remote tourist hubs,
high-altitude enclaves, and isolated hamlets that fall below the standard density threshold but carry real
search/discovery volume are force-bound into a target micro-cluster via a hardcoded ruleset (state-park
boundaries, tourist corridors, historical registers), independent of the radius/density algorithm. These
rows are tagged `source = 'system_override'`, `confidence_score = 1.00` — an additive value on the existing
`source` enum (alongside `manual` and `automated_seed`), not a schema change.

**Extension Layer B — sub-group aliasing for subdivisions with no municipal identity.** Unincorporated
rural subdivisions, off-grid ranch communities, and historic outposts that share a ZIP with an incorporated
anchor town are captured as sub-groups: the parser looks for structural boundary keywords (Association,
Ranchos, Estates, Unit, Development, Grant) in address text, validates the match against land records,
postal metadata, or historical registries (never inferring an association from context alone), binds the
validated entity to its parent anchor's `primary_zip`, and indexes the colloquial name into the cluster's
metadata alias array. A sub-group is discoverable through its alias and never gets its own URL slug or
changes the cluster's primary slug.

## Consequences

Nothing about ADR 0013's locked route structure changes — three addressable levels, one shared nested tree.
What changes is what feeds the third segment (a resolved anchor, not a raw town name) and what sits beneath
it in the data model (anchor, then sub-group aliases) without ever surfacing as its own path segment.

**Open item, flagged rather than resolved here:** the URL pattern above nests the listing directly under its
cluster (`.../[cluster-slug]/[listing-id-or-slug]`), while `routes.md` and `decisions/0020` currently
document a flat `/listing/{slug}` as the public business detail page and Tier 2's dependency root. This ADR
does not resolve whether the flat route is retired, kept as a canonical/redirect target, or coexists
alongside the nested form — that needs an explicit call before `routes.md` is updated to match, since
`/listing/{slug}` is load-bearing for other already-built dependencies (ADR 0020).

`resolveMicroClusterId` needs a real rewrite (radius/density grouping instead of a ZIP-keyed stub), and the
38–43 existing auto-seeded clusters need a one-time re-clustering/merge pass once that ships — not a
silent, indefinite hold as previously decided. The anchor-resolution sequence (county seat → incorporated
ranking → centroid/numeric fallback) and the sub-group parser both need building; neither exists today.

The single-geo-hub limitation on auto-seeding (`DEFAULT_GEO_HUB_SLUG`, flagged in
`traversence-platform-spec.md` §15) is **not** resolved by this decision — it's an orthogonal problem (which
geo-hub an incoming record belongs to) from the one this ADR solves (how records within a geo-hub cluster
together and resolve an anchor). It remains open, to be addressed when a second hub or geo-hub goes live.

`source = 'system_override'` needs the same admin-review-queue visibility already planned for
`automated_seed` rows, so a hardcoded override isn't invisible to admins auditing cluster data.

## Amendment (2026-09-28): grouping limits, as built

Building `resolveMicroClusterId` against the 43 real auto-seeded stubs changed three of the numbers above.
The Decision section's "100-mile proximity radius and a target 150,000 population ceiling" is superseded by:

- **Join radius: 20 miles, not 100.** An unmatched ZIP joins the nearest cluster in its geo-hub whose
  center is within 20 miles and that still has room; otherwise it anchors a new cluster. Simulated on the
  real stubs, a 100-mile radius put every stub within reach of every other, so clusters filled up in
  whatever order ZIPs arrived — Show Low folded into St. Johns (~45 mi), Gallup landed with Grants.
  At 20 miles the groups follow real geography (Show Low/Lakeside/McNary, Snowflake/Taylor,
  Holbrook/Woodruff/Sun Valley, Gallup/Vanderwagen/Mentmore, Zuni/Ramah/Pine Hill).
- **Population ceiling: 50,000, not 150,000,** applied only when every ZIP involved has a population.
  Populations come from the 2020 Census (DHC P1_001N per ZCTA), loaded nationwide into a new
  `zip_coordinates.population` column. ZIPs with no ZCTA (PO boxes, single-business ZIPs) are stored as 0,
  since the Census counts those residents under the surrounding ZIP.
- **Town cap: at most 5 distinct towns per cluster, always** — the spec's "3–5 neighboring towns". In
  population mode alone, rural areas fit 10+ small towns under 50,000 (the Show Low area did in
  simulation), so the cap applies whether or not population is known. It counts distinct town names,
  so a city's many ZIPs count once and the cap never splits a metro.

Clusters can absorb ZIPs regardless of source, so curated clusters (St. Johns, Round Valley) grow too.
Commerce ties that span farther than 20 miles — St. Johns to Show Low or Sanders — are served by the
Concentric Taxonomy Fallback (Micro-Cluster → Adjacent Cluster → Geo-Hub, `architecture.md`), not by
cluster membership.

**Anchor, as built:** a runtime-seeded cluster is anchored on whichever ZIP arrived first. The one-time
merge of the existing stubs places ZIPs largest-population first, so the biggest town in an area becomes
its anchor — the population stand-in for this ADR's incorporated-municipality ranking. County-seat
precedence isn't applied yet: there is no county-seat data. Auto-seeded clusters are named
`"<Town> (auto-seeded, needs review)"` with slug `cluster-auto-<town>` (the slug formula above, with
`auto` standing in for a tier id).

**New open item:** the 50,000 ceiling is sized for the rural pilot. A single metro ZIP can pass it on its
own, so a metro hub (e.g. Phoenix/Mesa/Gilbert) would be split into one-ZIP clusters. The ceiling needs to
scale with density, or be set per hub, before a metro hub goes live.

**Applied 2026-09-28.** The one-time merge ran on the live database: 43 single-ZIP stubs in Ancient
Borderlands became 18 clusters (plus Concho into St. Johns and Greer into Round Valley). Hand corrections
after it:

- Chambers (86502), Petrified Forest (86028) and Red Valley (86544) shared one placeholder coordinate in
  `zip_coordinates`; all three were corrected, and Red Valley (near Shiprock) was split out of the Sanders
  cluster into its own.
- **Petrified Forest → Holbrook** — the first Extension Layer A override, bound by hand. It sits ~23 mi from
  Holbrook, just outside the 20-mile join radius. There is no override ruleset or `system_override` source
  value yet (`micro_clusters.source` is an ENUM, so adding one is an `ALTER TABLE`, not a data-only change).
- **McNary → the Show Low cluster** (with Pinetop), out of Vernon. This puts Show Low at 6 towns: the
  5-town cap governs automatic grouping, not admin decisions.

The merge now only regroups legacy stubs (slug `cluster-auto-` + a ZIP), so re-running it can't undo these.
Grants and the Catron County clusters (Datil, Pie Town, Quemado, Reserve) remain under Ancient Borderlands
pending a geo-hub review.
