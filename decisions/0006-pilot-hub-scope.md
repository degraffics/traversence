# ADR 0006: Pilot Hub Scope — Ancient America (Pilot Continental Hub)

**Status:** Accepted

## Context

Launching a regional discovery network everywhere at once means launching with thin, unverified data density everywhere — the platform's trust model depends on high-integrity local data, which can't be faked with broad, shallow coverage. A single, identifiable pilot region lets data density, moderation load, and the claim/verification pipeline get proven out before scaling to the other 19 planned Continental Hubs.

## Decision

Stage the pilot as one full Continental Hub — Ancient America, covering the Colorado Plateau and Mountain Gateway region across Arizona, New Mexico, Utah, and Colorado — rather than a single town or a loosely-bounded radius. The full geo-hub-zone and micro-cluster breakdown (15 zones, ~34 micro-clusters, an estimated 13,000+ local businesses at full build-out) is documented in `regions.md`. Rollout doesn't have to mean all 34 clusters at once — this ADR fixes the hub as the pilot boundary, not the launch sequence within it.

## Consequences

Early architecture and content decisions (taxonomy fallback behavior, micro-cluster density, HITL review throughput) get tuned against one real region's actual data characteristics rather than a synthetic or averaged case — which is a feature, but means some of those tunings may need revisiting once a second, differently-shaped region (denser urban area vs. rural corridor) comes online. Marketing and business-development effort should concentrate here first rather than spreading thin across multiple regions before the model is proven.
