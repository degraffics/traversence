# ADR 0008: Activity & Content Data Preserves Personal vs. Business-Proxy Attribution

**Status:** Accepted. **Note (2026-09-23):** ADR 0002 was revised against the real live codebase — `acting_as_listing_id` is not a shared-PK-typed column resolving to the same ID space as `actor_user_id`; a business-proxy write's listing identity is the `entity_id` a `listing_access` grant authorized the write against (see `architecture.md` §9/§11/§13, `decisions/0002`). The principle below — personal and business-proxy activity must stay independently attributable, never flattened into one identity — is unaffected by that correction and still holds; only the underlying field's resolution mechanism changed.

## Context

Users are expected to be a large source of content and activity on the platform — reviews, posts, comments, telemetry events, contributed edits — and that activity is meant to be used as data: feeding the Trust-Weighted ranking model (`TrustScore.php`), the DIKW telemetry pipeline, and the Authority Engine's content signals. ADR 0002 already stamps every write with `actor_user_id` (always) and a nullable `acting_as_listing_id` (only when acting as a verified business proxy), which correctly separates the two identities at the point of capture. What wasn't specified anywhere: whether that separation survives downstream, or gets collapsed into a single "who did this" signal once the data leaves the write path and enters feeds, aggregates, or analytics.

## Decision

The personal/business-proxy distinction is preserved end-to-end, not just at capture. Concretely:

- Every downstream consumer of activity or content data — profile feeds, telemetry aggregates, the DIKW pipeline, trust scoring — receives both `actor_user_id` and `acting_as_listing_id` as separate fields, never a single flattened identity.
- A user's personal activity (`acting_as_listing_id IS NULL`) and their business-proxy activity (both fields populated) must be independently queryable, so a personal activity feed can show only what someone did as themselves, distinct from what they did on behalf of a business they manage.
- Trust scoring and any future analytics must be able to weight or segment contributions by which capacity produced them, rather than crediting all of a person's activity to one undifferentiated identity.

## Consequences

The User Profile Feed (`architecture.md` §5/§6) needs an explicit personal/business split rather than one merged timeline — this wasn't called out as a requirement before this decision. The telemetry and DIKW pipeline, and `TrustScore.php` specifically, need to carry both identity fields through every aggregation step rather than resolving to a single actor early and discarding the rest. Any reporting or export built later that flattens `acting_as_listing_id` into `actor_user_id` (or drops it) for convenience violates this decision and should be caught in review.
