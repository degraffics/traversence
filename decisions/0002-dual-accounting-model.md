# ADR 0002: Dual-Accounting Model for All Writes (UserId + ListingId)

**Status:** Accepted — mechanism revised 2026-09-23 against the real, live codebase (`architecture.md` §9/§11/§13, `decisions/0012`). The governing principle is unchanged; the concrete plumbing described below is not what the real system implements, and is corrected here rather than left standing as an inaccurate build target.

## Context

Legacy directory and review platforms rely on anonymous or single-identity writes, which enables sock-puppeting, unverified manipulation, and anonymous bias in reviews and listing edits. Traversence's Charter commits to rewarding authentic participation over ad spend or anonymous volume, which requires every action to be traceable to a real, accountable identity.

This ADR originally specified a shared `entities.id` between `users` and a `business_listings`-style table, a `listing_ownership` join table, and a persistent `x-acting-as-listing-id` session header validated by a session-context middleware. `decisions/0012`'s revision confirmed none of that plumbing exists in the real system — `entities` and `users` are independent ID spaces, there is no `listing_ownership` table, and there is no standing "acting as" session mode. The principle this ADR exists to protect (every write traceable to a real, accountable identity; no anonymous writes to the live graph) is unaffected and remains the governing rule — only the mechanism below is corrected.

## Decision

**Every write is stamped with an accountable identity — real and enforced today**, via `Auth::requireAuth()`/`requireListingAccess()` (§11, §13), not a header-driven role-toggle. Concretely:

- **Personal writes** (comments, reviews, saved places, community posts) are stamped with `actor_user_id` — a real `users.id`, resolved from the session, always required. Guests cannot write to the live graph at all (see `decisions/0009`).
- **Business-proxy writes** (editing a listing's own data) are authorized per-request, not via a standing session mode: `Auth::requireListingAccess($entityId, $minLevel)` checks the real, live `listing_access` table (`entity_id`, `user_id`, `access_level` ENUM `owner`/`manager`, `granted_by_user_id` — see `architecture.md` §9/§11) for a sufficient grant on the specific listing the request targets. A user can hold `owner`/`manager` grants on more than one listing simultaneously; which one a given write applies to is determined by the resource in the request (e.g. the `entity_id` in the URL/payload), not by a header or a toggled UI mode. `entities.owner_user_id` is the legacy MVP single-owner link this is migrating away from (add-then-migrate-then-drop, not yet dropped) — currently still what the claim flow (`/claim`) writes to directly.
- **Attribution on the resulting record**, wherever a write needs to record who acted and in what capacity, is `actor_user_id` (the real `users.id`, always) plus, for a business-proxy write, the `entity_id` the `listing_access` check authorized against — the same two facts the original design wanted, resolved through real tables instead of a shared-PK/header mechanism that never existed.
- **Concurrent-claim collisions** are handled by `api/claim.php`'s row-locked transaction on the claim itself, not a unique-index-enforced `listing_ownership` table — a second concurrent claim attempt on the same entity fails against that lock rather than against a separate ownership table's constraint.

## Consequences

Every write path must still run through `Auth::requireAuth()` (and, for listing-scoped writes, `requireListingAccess()`) — there is no legitimate way to write to the live database anonymously or without an accountable identity; guests remain read-only, full stop. The `content_contributions` audit-table language in the original version of this ADR should be read as "every write logs `actor_user_id`, and, where applicable, the `listing_access`-authorized `entity_id`" — whether a literal `content_contributions` table exists as its own audit log, separate from the tables already carrying this information (`entities.updated_by_type`, `verification_attempts`, etc.), is not yet confirmed against the real system and is flagged here as an open item for the next reconciliation pass rather than assumed either way.
