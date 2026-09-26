# ADR 0012: Unified `entities` Schema — the Hybrid Graph-Mirror Model

**Status:** Accepted, then substantially revised (2026-09-23) against the real, live codebase and the master platform spec (`traversence-platform-spec.md`, Sections 12, 15, 20, 27) uploaded directly from the working site export. The shared-primary-key decision below (originally accepted from document-only reconciliation of three conflicting drafts) did not survive contact with the real schema and is replaced in this revision. Everything else in this ADR that the real system confirms is left in place, marked as confirmed rather than decided.

## Context

`architecture.md` §9 originally carried three different, unreconciled descriptions of the same core table: SAOP's `entities` (semi-polymorphic, with user-specific and place-specific fields all on one table), the older Technical Spec's decoupled `users`/`business_listings`/`listing_ownership` tables, and a Phase 2 draft's `entities` variant (routing/provenance fields, no auth fields). The first version of this ADR resolved that three-way conflict by picking a hybrid model and, critically, a **shared primary key** between `entities` and `users`/`business_listings` — a reasonable-sounding synthesis at the time, but one made without access to any real code or schema, entirely from reconciling prose drafts against each other.

That gap has since closed. A full working export of the live site (PHP source, real migrations, and `traversence-platform-spec.md` — the actual, current, section-numbered master spec this document's earlier references to "Section 27/28" were pointing at without knowing it) is now available. Against that real source, the shared-primary-key decision is simply wrong: `entities` and `users` are built and run as two fully independent `AUTO_INCREMENT` ID spaces, and the real system has already moved past a simple ownership join into a working capability-grant model this ADR didn't anticipate.

## Decision

**The graph-in-SQL core is confirmed real, not just a design.** `entities` (nodes), `entity_metadata` (flexible key/value attributes), and `connections` (directed edges — `relationship_type` values in production use include `VERIFIED_BY`) are live, in production, with 4,376 real entity rows migrated from the legacy flat schema. This part of the original hybrid model needed no correction.

**No shared primary key. `entities` and `users` are independent ID spaces, linked by a nullable foreign key.** `entities.owner_user_id` (nullable — unclaimed/opted-out listings have no owner) is the real link, not a shared `id`. There is no `FOREIGN KEY (id) REFERENCES entities(id)` anywhere in the live schema, and there never should be — a real unclaimed merchant entity has no associated user at all, which a shared-PK design can't represent. `business_listings` as a distinct table does not exist; a listing's tier/verification state lives directly on `entities` (`status`, `verified_tier`, `trust_score`, `is_suppressed`) rather than a separate operational table.

**No Auth0, anywhere.** `auth0_sub` is removed from the schema entirely. The real identity system (`api/lib/Auth.php`) is plain custom PHP: native sessions (not JWT — a deliberate choice for BlueHost shared hosting, revisitable if a stateless mobile client is ever needed), `password_hash` via `PASSWORD_ARGON2ID` with a `PASSWORD_BCRYPT` fallback on PHP builds lacking the Argon2 extension, a `session_version` column that invalidates every other active session on password/email change, and a generic `email_confirmations` table (`action_type`: `registration` / `password_reset` / `ownership_change` / `claim` / `email_revert`) handling every account-security confirmation through one mechanism rather than per-purpose columns. Confirmed real `users` columns: `id` (`INT(10) UNSIGNED`), `email`, `password_hash`, `display_name`, `role` ENUM(`'user'`,`'business_owner'`,`'admin'`) DEFAULT `'user'` — `'user'` added 2026-09-23 (`decisions/0014`) as the real default for a new registration; `'business_owner'`/`'admin'` are legacy values, see below, `is_admin` — legacy, see below, `trust_score`, `session_version`, `is_suspended`, `email_verified_at`, `home_cluster_id` (secondary/derived), `area_zip`/`area_city`/`area_state`/`area_lat`/`area_lon` (primary location-resolution fields, migration 007), `created_at`/`updated_at`.

**Ownership and admin access are real, live capability grants — not a shared PK, not a `listing_ownership` table, and not an `OWNER_OF` edge.** This directly resolves the "still flagged" open item this ADR originally carried (whether `listing_ownership` or a graph `OWNER_OF` edge is authoritative) — and the real answer is neither. Two join tables, both live in production:

```sql
CREATE TABLE listing_access (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  access_level ENUM('owner', 'manager') NOT NULL DEFAULT 'manager',
  granted_by_user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE INDEX idx_entity_user (entity_id, user_id),
  CONSTRAINT fk_la_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE,
  CONSTRAINT fk_la_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE admin_access (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  hub_id INT UNSIGNED NULL COMMENT 'NULL = global platform-wide Super-Admin reach',
  tier ENUM('super_admin', 'regional_admin') NOT NULL DEFAULT 'regional_admin',
  granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE INDEX idx_user_hub_tier (user_id, hub_id, tier),
  CONSTRAINT fk_aa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_aa_hub FOREIGN KEY (hub_id) REFERENCES hubs(id) ON DELETE CASCADE
);
```

One `users` identity is the single front-end ID for everything (favorites, community participation, profile). Business-listing access (`owner`/`manager`, many-to-many — a listing can have more than one assigned user) and admin access (`regional_admin`, hub-scoped via `hub_id`; `super_admin`, platform-wide via `hub_id IS NULL`) are both stackable capability grants on top of that one identity, not role flags and not separate account types — a person can simultaneously be a resident, a `manager` on one listing, an `owner` on another, and a `regional_admin`. `Auth.php` exposes this via `canAdministerHub()`, `requireHubAdmin()`, `isSuperAdmin()`, `requireSuperAdmin()`, `getListingPermission()`, `requireListingAccess()`, `hasAnyAdminAccess()`.

**`entities.owner_user_id` and `users.is_admin` are legacy, mid-migration, not yet removed.** The real system is partway through an explicit add-then-migrate-then-drop sequence: `listing_access` was backfilled from `owner_user_id` the day it shipped, but `owner_user_id` itself stays in place (and is what the MVP claim flow — `api/claim.php` — still writes to directly) until every call site is confirmed migrated. Likewise `is_admin` remains live and load-bearing in `Auth.php`'s `requireAdmin()` today; it is not yet routed through `admin_access`. This legacy column should be treated as a current, real, in-use mechanism in this document — not dead weight to delete from any schema description — until the platform's own migration completes. **Correction (2026-09-23):** an earlier version of this note also named `role` as load-bearing in `requireAdmin()` — a direct grep of `api/` confirms that's not the case; `requireAdmin()` gates on `is_admin` alone. `role`'s only real change this round is its default value (see below and `decisions/0014`), not a live authorization role — it carries no gating weight anywhere in the codebase today.

**`entity_type` gains a `'topic'` value in the spec, not yet in live data.** Confirmed real usage today is `entity_type = 'merchant'` exclusively, across all 4,376 migrated rows. `'topic'`/`'content_asset'` nodes (for AI-extracted editorial content, `architecture.md` §26) are specified in the real platform spec (Section 12.1) as the intended design, consistent with this ADR's original reasoning, but nothing has created one yet.

**`entity_metadata` is confirmed as flexible/type-varying attributes, with real field-level locking.** Real columns include `source_type`, `trust_score` (default 10.00), and an `is_locked` flag — once a human (`owner`, `admin`, or high-trust `user`) edits a specific field, that field is locked against future automated overwrites. This is enforced per-field, on both `entities` and `entity_metadata`, not via a separate ownership-status check at write time as this ADR's first version assumed.

**`composite_hash` — confirmed exactly as designed, with the real formula.** `SHA256(lowercase(trim(name) + trim(address) + trim(zip)))`. A re-scrape hash-matches an existing entity (claimed and verified ones included) rather than creating a duplicate. Overwrite protection is real and live: a matched record only patches *unlocked* fields; anything a human has touched is `is_locked = 1` and immune to the patch regardless of how recent the scrape is. This is the field-level mechanism, not a record-level "route the whole update to HITL" gate — a claimed-and-verified listing can still legitimately receive new, unlocked-field data (e.g. hours a crawler picks up that the owner never set) without every re-scrape needing manual review. `decisions/0005-hitl-ai-guardrails.md` and `architecture.md` §25 should describe it this way, not as a blanket quarantine-on-match rule.

**The real, corrected core schema:**

```sql
CREATE TABLE entities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL DEFAULT 'merchant',  -- 'merchant' in all live data; 'topic'/'content_asset' spec'd, not yet used
    name VARCHAR(255) NOT NULL,
    status ENUM('unclaimed','claimed-unverified','pending-verification','verified',
                'disputed','pending-opt-out','opted-out') NOT NULL DEFAULT 'unclaimed',
    verified_tier TINYINT NULL,            -- 1-4, which route granted 'verified' (see decisions/0005, architecture.md §25)
    is_suppressed BOOLEAN NOT NULL DEFAULT FALSE,
    owner_user_id INT UNSIGNED NULL,       -- legacy MVP link; mid-migration to listing_access, see above
    primary_zip VARCHAR(10) NULL,
    micro_cluster_id INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,         -- FK to the real `categories` table (23 groups / 818 leaves)
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    composite_hash CHAR(64) NULL,          -- SHA256(lowercase(trim(name)+trim(address)+trim(zip)))
    trust_score DECIMAL(8,2) NOT NULL DEFAULT 10.00,
    created_by_type ENUM('ai_crawler','user','owner','admin') NOT NULL DEFAULT 'ai_crawler',
    updated_by_type ENUM('ai_crawler','user','owner','admin') NOT NULL DEFAULT 'ai_crawler',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE entity_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entity_id BIGINT UNSIGNED NOT NULL,
    meta_key VARCHAR(100) NOT NULL,
    meta_value TEXT NOT NULL,
    source_type ENUM('ai_crawler','user','owner','admin') NOT NULL DEFAULT 'ai_crawler',
    trust_score DECIMAL(8,2) NOT NULL DEFAULT 10.00,
    is_locked BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
);

CREATE TABLE connections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    relationship_type VARCHAR(100) NOT NULL,   -- e.g. 'VERIFIED_BY' (Peer Validation, live)
    created_by_type ENUM('ai_crawler','user','owner','admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (source_id) REFERENCES entities(id) ON DELETE CASCADE,
    FOREIGN KEY (target_id) REFERENCES entities(id) ON DELETE CASCADE
);

-- users: fully independent of entities, no shared PK
CREATE TABLE users (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NULL,
    role ENUM('user','business_owner','admin') NOT NULL DEFAULT 'user',  -- 'user' added 2026-09-23, decisions/0014; 'business_owner'/'admin' are legacy
    is_admin BOOLEAN NOT NULL DEFAULT FALSE,                                 -- legacy, mid-migration
    trust_score DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    session_version INT NOT NULL DEFAULT 1,
    is_suspended BOOLEAN NOT NULL DEFAULT FALSE,
    email_verified_at TIMESTAMP NULL,
    home_cluster_id INT UNSIGNED NULL,      -- secondary/derived, see architecture.md §23
    area_zip VARCHAR(10) NULL,              -- primary location-resolution fields
    area_city VARCHAR(100) NULL,
    area_state VARCHAR(2) NULL,
    area_lat DECIMAL(10,8) NULL,
    area_lon DECIMAL(11,8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL
);
```

## Consequences

`architecture.md` §7, §9, §11, and §13 all described the shared-PK/Auth0/`OWNER_OF`-edge model as current architecture. All four need correction to match this revision — done as part of this same pass, not deferred.

**This also reopens a question this ADR can't close by itself: ADR 0002 (Dual-Accounting Model).** ADR 0002's `actor_user_id`/`acting_as_listing_id` framing was built assuming `entities.id` was the shared value both resolved to. With no shared PK, `actor_user_id` is a `users.id` and the "acting as a listing" side would need to resolve through `listing_access` (which `entity_id` a user currently holds `owner`/`manager` on) rather than through a second `entities.id`-typed column. This document did not re-read or revise ADR 0002 itself in this pass — flagging it here as a direct, load-bearing consequence of this revision that needs its own follow-up rather than an assumption that dual-accounting "still just works" with `entities.id` swapped for `users.id`.

`decisions/0005-hitl-ai-guardrails.md` and `architecture.md` §25 should describe the claim-aware overwrite protection at the field level (`is_locked`), not as a record-level HITL quarantine trigger on every match against a claimed listing — corrected in this same pass.

The real system also has a working, four-tier verification model (self-confirm OTP, referrals, peer-vouch `VERIFIED_BY` edges, admin override) with its own trust-score economy, considerably more concrete than anything this ADR or `architecture.md` §25 previously described. See `architecture.md` §25's revised text for the full mechanism — recording it there rather than duplicating it in this ADR, since it's now confirmed as-built rather than a decision still being made.
