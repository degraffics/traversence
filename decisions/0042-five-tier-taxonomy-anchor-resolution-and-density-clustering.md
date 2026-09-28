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
