# Traversence: Phased Development

*Version: 2026-09-22*
*Governance tier: Phased Development — the strict chronological build order and feature gatekeeper. Consolidated from `01-concept.md` through `06-deploy-and-complete.md`. These were previously six separate files governed by `GEMINI-PROTOCOLS.md` (not shared with me — if it defines rules beyond what's captured here, it should stay the authority and this file should point to it rather than duplicate it once you can share it).*

The purpose of this tier: prevent phase drift — building advanced features while foundational schemas remain unverified. A feature belonging to a later phase gets explicitly isolated or tagged "preview" until every preceding phase has 100% test and migration coverage. Every session should consult this document first, before writing code or altering specs, to confirm which phase is actually active.

## Phase 1: Concept & Foundations

**Mandatory phase-gating & anti-drift rules:**
1. **Database-First Verification** — no frontend directory, search index, or listing portal code is marked complete until its prerequisite database migrations (categories, geo-hierarchies, spatial columns) are verified as executed on the live server.
2. **Linear Milestone Enforcement** — features from later roadmap phases (map views, advanced discovery engines) must be explicitly isolated or tagged "preview" until preceding phases reach full test and migration coverage.
3. **Active State Synchronization** — every session consults this governance milestone first, before writing code or altering specs.

**Status:**
- [x] Core macro-requirements: dual-accounting foundation (UserId + ListingId) and economic tier definitions locked.
- [x] Pilot hub scope locked — see `decisions/0006-pilot-hub-scope.md`.
- [~] Search intelligence refinement: initial pass deployed (`api/lib/SearchText.php`), held pending full verification of underlying database migrations.

## Phase 2: Draft & Architecture

- **Graph-in-SQL schema topology** — `entities`, `entity_metadata`, `connections`. (See `architecture.md` §9 for the current reconciliation status across the different column-set descriptions of `entities` that exist across documents.)
- **Concentric Taxonomy Fallback** and **Dual-Method Navigation** (Bottom-Up Utility vs. Top-Down Experience via Linear Corridor Loop / Chameleon Pivot) — see `architecture.md` §18.

## Phase 3: Build & Core Engineering

- **Code delivery standard:** full-file iteration only — zero fragmented snippets or partial diffs; complete, production-ready files.
- **Backend stack:** plain PHP 8+, native PDO, MySQL/MariaDB. (Flagged in `architecture.md` §2 against the proposed Postgres migration in `decisions/0004`.)
- Session/security bootstrap, CSRF enforcement, module-relative routing, ZIP-centroid geospatial handling, and search intelligence internals are documented in full in `architecture.md` §2 and §4 rather than duplicated here.
- **Sub-phase tagging within a `prd.md` tier — standing method, added 2026-09-24 per `decisions/0020`.** This document's own "Linear Milestone Enforcement" rule (Phase 1, above) has always governed drift between the six macro-phases; it never governed the internal order of items *inside* a single `prd.md` tier once that tier grew past a handful of features — a real gap, since an undifferentiated tier creates the same three problems this rule exists to prevent one level up: unclear dependency sequencing, resource-allocation bottlenecks, and confusion about what a minimal build actually is. `decisions/0020` closed that gap for `prd.md`'s Tier 2 (dependency mapping, a value-vs-effort read, and firm `2.1`–`2.7` sub-phase tags) and establishes the same three-part method — map real technical dependencies, weigh value against effort, tag firm sub-phases — as the default whenever a future tier grows past a handful of undifferentiated items, rather than a one-time Tier 2 fix.

## Phase 4: Verify & Live Test (V&V)

- **V&V audit role:** review live-site assets, ZIP files, and deployment states against architectural specifications.
- **Real-world user journeys:** evaluate role-toggle header checks, avatar badge verification, and auth flows against live databases.
- **Failure tracking:** every production workaround, routing correction, and bug resolution is logged durably in `failure-fix-log.md`.

## Phase 5: Pivot & Adapt

- **Formal feedback gate:** live-testing results or real-world friction trigger clean, explicit updates to `01-concept.md`/`02-draft.md` (now this file and `architecture.md`) — never unmanaged code hacks.
- **Administrative isolation:** spec and documentation updates are batched to session reviews or close-out phases, never made mid-coding-turn.

## Phase 6: Deploy & Complete

- **Database migrations:** execute verified SQL migration scripts sequentially.
- **Pipeline execution:** synchronize verified file packages via the OneDrive connector sync path to the live host.
- **Task acceptance:** final verification of secure session states, active database connections, and successful endpoint responses.

## Orientation: How This Maps to a Generic Web Project Lifecycle

A separate, generic 7-phase reference (Initiation/R&D → Planning/Scope → Design/Prototyping → Development → Testing/QA → Deployment/Launch → Maintenance) was also provided. It isn't Traversence-specific and introduces no new rules, so it isn't tracked as its own governance file — but as an orientation aid, it maps loosely onto the six phases above like this: Initiation and Planning precede Phase 1; Design/Prototyping and Phase 2 overlap; Development matches Phase 3; Testing/QA matches Phase 4; Deployment/Launch matches Phase 6; Maintenance is the ongoing state after Phase 6. Phase 5 (Pivot & Adapt) has no equivalent in the generic model — it's a standing feedback loop across all phases, not a stage that happens once.
