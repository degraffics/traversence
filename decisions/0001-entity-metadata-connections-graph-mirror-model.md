# ADR 0001: Entity-Metadata-Connections Graph-Mirror Model

**Status:** Accepted

## Context

Traversence needs to represent people, places, products, and topics associating freely with each other, the way people actually experience places in the real world — not through a rigid, over-engineered directory schema with a fixed table per entity type. A rigid schema fights the Charter's core paradigm shift away from static directories and artificial categories.

## Decision

Reject rigid per-type schemas in favor of a normalized Entity-Metadata-Connections topology:

- **Entities (nodes):** a single polymorphic table tracking people, places, products, and topics under a unified identity model, carrying spatial relevance (lat/long, micro-cluster mapping) and dual-accounting lineage.
- **Entity Metadata (attributes):** a flexible key/value attribute layer handling type-specific properties (business hours, historical summaries, custom attributes) without altering the structural schema.
- **Connections (edges):** directed, typed relationships (`OWNER_OF`, `VERIFIED_BY`, `INTERESTED_IN`, `TAGGED_AT`) that bind information into context through natural association.

## Consequences

New entity types (a new kind of place, a new kind of relationship) don't require schema migrations — they're new rows and new `meta_key`/`relationship_type` values. The tradeoff: query patterns that would be a simple join in a rigid schema (e.g. "all business hours for listings in this ZIP") require joining through `entity_metadata`, which is less immediately readable in raw SQL and needs careful indexing as data volume grows. **How this coexists with `users` and ownership/access is now resolved, not open** (`decisions/0012`, revised 2026-09-23 against the real live codebase): `users` is a fully independent table (no shared primary key with `entities`), there is no `business_listings` table and no `listing_ownership` table, and ownership/access run through the real, live `listing_access`/`admin_access` capability-grant tables — see `architecture.md` §9 for the confirmed schema.
