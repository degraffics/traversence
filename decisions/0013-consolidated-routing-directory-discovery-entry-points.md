# ADR 0013: Consolidated Routing — One Shared Hub/Geo-Hub/Cluster Tree Under Two Funnel Entry Points

**Status:** Accepted

## Context

Three inconsistent descriptions of the same routes existed across the document set: `architecture.md` §4 already named `/directory/` and `/discovery/` as the two funnel prefixes (per ADR 0003's Chameleon Filter routing); §19 separately described two different *slug patterns* underneath those funnels — a nested path for top-down (`/[continental-hub]/[geo-hub]/cluster-[id]`) and a colon-delimited composite string for bottom-up (`hub_[identifier]:geo_[zone]:cluster-[id]`); and `routes.md`, meant to be the single source of truth for actual URLs, used neither — flat, un-prefixed routes (`/get-local`, `/explore`, `/hub/{hub-slug}`, `/geo-hub/{geohub-slug}`, `/cluster/{cluster-slug}`) with no relationship to either pattern above.

Two things resolved this once surfaced: confirmation that `/get-local` and `/explore` were always meant to be consolidated into `/directory` and `/discovery` (the consolidation simply never made it into `routes.md`), and that only one entry route per funnel is needed — not a parallel deep hierarchy duplicated under each funnel prefix.

## Decision

**`/directory` and `/discovery` are the only two funnel-specific routes.** They are the sole entry points into their respective funnels, replacing `/get-local` and `/explore` as route names. This matches `architecture.md` §4's Chameleon Filter naming exactly, closing the gap between that section and the actual route table.

**Beneath them, both funnels converge on one shared, canonical hierarchy — not two parallel trees.** A single nested, slash-delimited path structure serves both funnels for the geographic pages themselves:

- `/[hub-slug]` — Continental Hub landing
- `/[hub-slug]/[geohub-slug]` — Geo-Hub landing
- `/[hub-slug]/[geohub-slug]/[cluster-slug]` — Micro-Cluster landing

A visitor reaches the same Micro-Cluster page regardless of which funnel they entered through. What changes is content emphasis at that page, per `architecture.md` §22's already-established Dual-Behavioral Routing (Layer 1 Macro-Editorial emphasis for Discovery-origin traffic, Layer 2 Micro-Cluster Directory emphasis for Directory-origin traffic) — this decision doesn't invent that adaptation, it confirms the URL structure it implies, and avoids the duplicate-content problem two separate parallel hierarchies for the same real-world places would create.

This also directly satisfies a stated navigation requirement: Hub, Geo-Hub, and Cluster are each independently addressable as their own page, so a visitor — or a breadcrumb trail — can move between the three as separate stops, truncating the path to jump up a level.

**The colon-delimited composite format is retired as a URL.** `hub_[identifier]:geo_[zone]:cluster-[id]` doesn't support independently addressable levels or breadcrumb navigation the way a nested path does — it reads as one opaque unit, not three navigable stops. If a composite key is still useful for anything, it exists only as an internal lookup/dedup mechanism, analogous to `composite_hash` on `entities` (`architecture.md` §9, `decisions/0012`) — never rendered in the address bar.

**Migration note — corrected 2026-09-23 against the real live code.** There is no live `/get-local` route and no migration/redirect needed: `/directory/index.php` and `/discovery/index.php` are already the real, live route names, confirmed directly from a working export of the site. The original version of this note assumed `/get-local` was live purely from document-only reconciliation, before any real code had been seen — that assumption didn't survive contact with the actual codebase. `/directory/` is already substantially built (category filter against the real `categories` table, search/sort/map); `/discovery/` is live with real hub data behind placeholder editorial content. This ADR's routing *structure* (funnel prefixes + shared nested hierarchy) turned out to be independently confirmed by the platform's own real spec draft for the geographic hierarchy, arrived at separately — a strong validation of the decision, not a contradiction of it.

**Reserved-slug collision priority, confirmed real:** `directory`, `discovery`, `listing`, `admin`, `api`, and other real top-level folder names always win over a hub slug — checked before a request is ever treated as `/[hub-slug]`.

**Naming note:** "Get Local" and "Let's Explore" remain the real, live UI/marketing names for the two gateways (used in the account dashboard's copy today) — distinct from, and not required to match, the `/directory`/`/discovery` technical route names decided above.

## Consequences

`routes.md` is rewritten to reflect this structure and its own header claim ("needs no restructuring, only continued upkeep") is corrected. `architecture.md` §19 is rewritten to state this single resolved pattern in place of the two-pattern description and the previously-flagged gap. No other document needs to change — `architecture.md` §4 and §22 already described the pieces this ADR assembles; nothing here contradicts either.
