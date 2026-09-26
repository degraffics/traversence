# Traversence Platform Architecture — Working Spec
*Reconciling "Part 8: Platform & Directory Architecture" with product decisions made in planning discussion. This is a living document — update it as decisions change.*

---

## Development Status — Quick Reference Checklist

*Update this section whenever build status changes, so re-opening this file gives an accurate picture without re-reading everything below.*

### Built and confirmed live in production
- [x] Full geographic taxonomy (hubs → geo-hubs → micro-clusters), pilot data locked (St. Johns / Round Valley, Ancient Borderlands geo-hub)
- [x] Graph-in-SQL schema (`entities` / `entity_metadata` / `connections`) and all supporting tables (Sections 12, 15–16, 20)
- [x] Full auth system: register, login, logout, session check, email verification, password reset, CSRF protection on all state-changing endpoints
- [x] Crawler ingestion pipeline (`api/ingest.php`, `CrawlerIngest.php`) with dedup hashing, field-locking, trust-score baselining
- [x] **Legacy data migration executed live**: 4,376 entities created, 102 deduped/merged, 0 errors
- [x] Trust score system (`TrustScore.php`) with full tiered points economy
- [x] **Outgoing transactional email confirmed sending in production** — authenticated SMTP via the real BlueHost mailbox credentials, first live send confirmed. This closes the last open item from Phase 4 (see Milestone note below).
- [x] **Quick local profile removal, sign-out storage bug fix, and `set-home-location.php` cleanup — shipped and confirmed working.** See Section 20.21.
- [x] **Section 20.22 (Dashboard HTTP 500) — resolved.** Root cause was a BlueHost shared-hosting resource limit (connection-count class issue causing stalled load → HTTP 500), not an application bug. No code change required.
- [x] **`switchRegion()` no-op bug — resolved (closes Section 20.23's deferred item).** Resolution was architectural, not a data fix: hub-pill clicks are the Bottom-Up "expand from here" taxonomy fallback, never a home-location declaration. The `persistAccountHomeLocation()` call was removed from `switchRegion()` entirely — pills stay session-only; only the Location Modal's auto-detect/manual-entry flows persist to the account.
- [x] **Section 25 — Micro-Cluster / Zip-Coordinates Dual-Layer Reconciliation, decided and confirmed against the original Part 8 blueprint.** `micro_clusters` is the permanent, deliberately curated Hub→Geo-Hub→Cluster taxonomy tier (small-N by design — "3 to 5 neighboring towns," a fixed namespace for ~20 continental hubs); `zip_coordinates`/`area_*` (Section 20.24) remains the comprehensive resolution layer for radius search and area-of-interest. Not competing systems requiring a merge. Unblocks Section 23 (Town Hall) to build against `micro_clusters` as its cluster definition, and unblocks the hub-pill taxonomy rewrite (see Section 25 body / Open Gaps below).
- [x] **Section 27 — Admin & Business-Access Capability Model, schema and helpers shipped.** New join tables `listing_access` (user↔entity, `owner`/`manager` tiers) and `admin_access` (user↔hub, `regional_admin`/`super_admin` tiers, nullable `hub_id` for platform-wide reach) — live in production, migrated with `UNIQUE` indexes (corrected from an initial draft that used non-unique indexes) and `UNSIGNED`/`BIGINT UNSIGNED` FK types (corrected after a `#1215` FK-mismatch error on first run). `Auth.php` capability helpers added: `canAdministerHub()`, `requireHubAdmin()`, `isSuperAdmin()`, `requireSuperAdmin()`, `getListingPermission()`, `requireListingAccess()`, `hasAnyAdminAccess()` — all independent of the legacy `is_admin`/`role` columns, which are retained temporarily per the add-then-migrate-then-drop sequence. This resolves the resident/business/admin identity-model open question: one unified `users` identity, with business-listing access and admin access both modeled as stackable capability grants rather than exclusive account types or role flags — a user can simultaneously be a resident, hold `listing_access` on one or more businesses, and hold `admin_access`.
- [x] **Self-service Super-Admin bootstrap — shipped and confirmed working (first real Super-Admin grant made via this flow).** `user/dashboard.php`'s first rendered block checks `admin_access` row count: zero rows → one-time "Become Super-Admin" card; current user already admin → "Admin Panel" link (to the not-yet-built `/admin/` shell); otherwise → nothing renders. New endpoint `user/api/bootstrap-super-admin.php` grants `tier='super_admin', hub_id=NULL` via an atomic `INSERT...WHERE NOT EXISTS` (not a separate count-then-insert, which would have left a TOCTOU race window).

### Built, not yet deployed/tested live
- [ ] **Verify Phase 4 works end-to-end in production** (this is the agreed immediate next step) — run migrations 005/006, confirm claim → OTP → dashboard flow actually works against the live DB. **In progress**: 002/003 confirmed already applied from an earlier session; 004 had a real gap (`verified_tier`/`entity_metadata.trust_score` missing, added to the migration after an earlier partial run) — closed via `004b_catchup.sql`. All migrations 003/004/005/006 hardened for a real compatibility issue found along the way: this account's MySQL/MariaDB doesn't support `ADD COLUMN IF NOT EXISTS`. **Also found: `Database.php` was never actually deployed to the live server** — this broke `dashboard.php` and effectively everything else; re-delivered with explicit upload-path instructions. **Also found: `index.php`'s login was still the old localStorage fake auth, never updated to call the real backend** — no real login existed anywhere on the live site. Built `login.php`, `register.php`, and `forgot-password.php` as a real, working testing bridge (temporary — `index.php`'s own auth UI still needs a proper rebuild as its own task).
- [x] **`index.php` aligned to real auth (scoped patch, not the full homepage rebuild)** — separated the old password-less "quick local profile" (location memory only) from real account status, which is now checked via a genuine `api/auth/session.php` call. Old "Owner Dashboard" link (misleadingly gated by the fake profile) removed; new "My Account"/"Sign In"/"Register" nav added, pointing at `/user/dashboard.php`, `/login.php`, `/register.php`. The full homepage/magazine rebuild (Section 3) remains separate, larger, unscoped work.
- [x] **Personal user account dashboard (`user/dashboard.php`)** — built: profile summary, business-portal nav tile, referral inbox (`api/user/referrals.php`, new), working change-password/change-email UI (backend existed, no page ever built for either before now). Honest "Coming Soon" for favorites/community/messages, not fake UI.
- [x] **`dashboard.php` moved to `listing/businessportal.php`** (real folder-per-domain structure, mirroring the API layer) — old path kept as a thin redirect stub for `index.php`'s not-yet-updated links.
- [x] **Full domain reorganization completed**: `listing/api/*` and `user/api/*` now hold domain-specific endpoints (claim, vouch, verify, referral, my-listings, update-metadata, referrals), mirroring the page-level `listing/`/`user/` folders. Global `api/` retained for platform-level concerns only (`bootstrap.php`, `lib/*`, `middleware/*`, `auth/*`, and the crawler `ingest.php`/`CrawlerIngest.php` — confirmed as platform infrastructure, not listing-domain UX, since nothing user-facing ever triggers it). `login.php`'s redirect finally moved to `/user/dashboard.php`, completing the personal/business split's intended flow.
- [x] **Real bug found and fixed via live testing: `api/auth/session.php` withheld `csrf_token` from unauthenticated responses**, breaking login/register/forgot-password identically (all three fetch a token before any session exists). Diagnosed via a standalone session-persistence check that first ruled out infrastructure as the cause.
- [x] **Real bug found and fixed via live testing: `Database.php`'s `.env` path was wrong at its actual deployment location** — used `dirname(__DIR__)` (one directory above the file), correct only if nested in a subfolder, but `Database.php` was deployed at the project root itself at the time. **Superseded immediately after: `Database.php` moved into `api/` instead of fixing the path at the root** — `dirname(__DIR__)` is now the correct logic again, since `api/` is one directory below the project root where `.env` lives. `api/bootstrap.php`'s require path updated accordingly (`Database.php`, same directory, not `../Database.php`). Final state: `api/Database.php`, `dirname(__DIR__)` env lookup.
- [x] **Real bug found and fixed via live testing: `user/dashboard.php` and `listing/businessportal.php` rendered as raw text instead of HTML** — both require `api/bootstrap.php` directly (for the server-side login check), which unconditionally sets `Content-Type: application/json`, correct for real API endpoints but wrong for these two HTML pages. Fixed by explicitly overriding the header back to `text/html` immediately after the require, in both files.
- [x] **Change-password UX improved after first successful live test**: show/hide toggles on every password field in `user/dashboard.php`, and a "Confirm New Password" field with client-side match validation before the API call. Underlying functionality (session-preserving-on-current-device, invalidating-elsewhere) confirmed working correctly on the first real test.
- [x] **Real gap found and fixed: password change had no security notification email at all** — `api/auth/change-password.php` never had a `Mailer::send()` call, unlike email-change which already notifies. Added, pointing to the existing forgot-password flow as the recovery path (no revert-link mechanism applies here — there's no prior password value to roll back to).
- [x] **Standard password complexity policy added platform-wide**: 8+ chars, upper/lower/digit/special character, enforced server-side via a single shared `Auth::assertPasswordMeetsRequirements()` (replacing three duplicated length-only checks) and mirrored client-side as a live checklist across `register.php`, `reset-password.php`, and `user/dashboard.php` — plus confirm-password fields and show/hide toggles on every password input across all three.
- [x] **Real bug found and fixed: `reset-password.php` never sent a CSRF token**, despite `api/auth/password/reset.php` requiring one since Section 18 — existed since CSRF enforcement was added, never exercised until this test. Fixed.
- [x] **Change Email section given proper field labels and a password visibility toggle** — was missing both.
- [x] **Email-revert flow hardened: now requires typing the correct original email address (server-verified), not just a bare click.** Genuine second factor — possession of the link *and* knowledge of the correct account email, not just one or the other. Doesn't reveal the correct address on mismatch, and the page itself never displays it either, to avoid unnecessary exposure if the link is ever forwarded.
- [x] **`login.php` given the same password show/hide toggle** used everywhere else — last remaining password field on any page without one.
- [x] **Area-of-interest schema + write-side + read-side rework (Section 20.24) — delivered, not yet live-tested.** `zip_coordinates` is now the primary resolution source (`users.area_zip`/`area_city`/`area_state`/`area_lat`/`area_lon`, migration 007); `micro_clusters`/`home_cluster_id` retained as secondary/derived. `Auth.php`, `session.php`, and `user/api/set-home-location.php` all updated to match; response contract to `index.php` unchanged.
- [x] **Home-location auto-scope for authenticated search — read side, schema-verified against live source/data (superseded by Section 20.24's area_* fields as the primary path; this entry describes the original cluster-join design, now the fallback).** Real gap identified: `users.home_cluster_id` has existed since migration 003, but no feature read it — Section 23 (Town Hall) was the only documented consumer, and it isn't built. `index.php`'s "Set Precise Location" modal was firing for logged-in users because it's driven by the separate, pre-existing quick-profile system (Section 20.12), which was never wired to real account data. **Fix delivered**: `api/auth/session.php` resolves `users.home_cluster_id` → `micro_clusters.primary_zip` → `zip_coordinates` (city/state/lat/lon) and returns it as `home_location` in the authenticated response, wrapped defensively so a missing cluster/zip or query failure degrades to `null` rather than breaking session-check. `index.php`'s `checkRealAuthState()` — the only place this fetch happens — applies `home_location` to `currentLocation` once the (necessarily async) response resolves, but only if nothing already occupies `currentLocation` (an active quick-profile/session location always wins); if a search was already parked waiting on a location when the fetch resolves, the modal is closed and the search resumed instead of left stuck open. Setting the location alone never auto-fires a search (Intentional Query-Driven Results). Manual "Set Location" override and the quick-profile system are both untouched. **First live test surfaced the real remaining gap: nothing ever wrote `home_cluster_id` in the first place** — see Section 20.20 for the write-side fix (`user/api/set-home-location.php`). **Live-tested and confirmed working** — see Section 20.21.
- [x] **`set-home-location.php` cleaned up to use `Response::jsonBody()`** instead of manual `file_get_contents`/`json_decode` parsing, matching the established pattern confirmed via `api/auth/login.php`. See Section 20.21.

### Open bugs — not yet resolved
*(Section 20.22 dashboard 500 and the `switchRegion()` no-op bug, formerly logged here, are resolved — see Development Status "Built and confirmed" above.)*

### Newly discovered — unresolved architecture question (read this before touching location/geo code again)
- [x] **Two parallel geography systems — resolved, see Section 25.** `micro_clusters` is the permanent curated taxonomy tier; `zip_coordinates`/`area_*` is the resolution layer for search. Not merged, not competing — confirmed against the original Part 8 blueprint's own language ("3 to 5 neighboring towns," fixed small-N namespace).
- [ ] **"Area of interest" reframe — direction set, not yet built.** See Section 20.23. Explicit product direction: stop framing saved location as "where the user currently is" and instead frame it as "the place the visitor wants to search/connect with" — supports a local resident searching nearby *and* someone (local or remote) researching a destination elsewhere. Should extend to registration/signup, not just post-login settings.
- [ ] **International / out-of-region visitors — explicitly clarified as a data-scope problem, not a location-widget problem.** No UI/location-picker fix can produce results outside the current dataset — `zip_coordinates`/`micro_clusters`/`geo_hubs` have zero rows outside the US, let alone outside AZ/NM/UT today. Serving international or other-US-region visitors requires actual content/data expansion (new geo coverage, crawler sourcing outside the current trade area) — a separate, unaddressed product/content-scope decision, not part of any location-persistence bugfix.
- [ ] **Registration-time area-of-interest capture — blocked on a real constraint, not yet designed.** Confirmed via real `api/auth/register.php` source: registration does **not** create a session (no `session_regenerate_id()`, no `$_SESSION['user_id']`). So a signup-time "area of interest" step cannot simply call the existing authenticated `user/api/set-home-location.php` — it needs new server-side handling inside `Auth::register()`/`api/auth/register.php` itself, in the same request/transaction, before any session exists. Not designed, not built.
- [ ] **"Viable at any scale" non-functional requirement, explicitly stated by the user**: whatever mechanism gets built for location/area-of-interest targeting must hold up once the (not-yet-built) crawler ingest pipeline adds much broader geographic coverage. Rules out fragile approaches like plain city-name string matching (collision risk — e.g. "Springfield" exists in multiple states/regions). This is *why* the architecture question above needs resolving deliberately rather than patched around.

### Not yet started — real gaps, not just future nice-to-haves
- [ ] **Section 26 — `set-home-location.php` / Section 20.24 discrepancy (flagged, unconfirmed, not blocking).** The live/uploaded `user/api/set-home-location.php` does not match Section 20.24's description: it still requires a strict 5-digit zip, resolves only against `micro_clusters`, and writes only `home_cluster_id` — no `area_*`/`zip_coordinates` path exists in it at all, despite 20.24 stating this file was rewritten to treat `area_*` as primary. Unresolved whether 20.24's file-level work never actually shipped, or the uploaded copy was stale. Not currently blocking anything (the `switchRegion()` fix bypasses this endpoint entirely) but should be confirmed before any future task builds against this file's assumed contract.
- [ ] **Hub-pill / `nearby_hubs.php` taxonomy rewrite (Section 25 follow-through) — scoped, not yet built.** Current `nearby_hubs.php` couples pill membership to `currentLocation.radius` (Haversine distance over `zip_coordinates`), which is architecturally backwards per Section 25's decision — pills should be a taxonomy walk (`micro_clusters` sibling/adjacent-geo-hub query), never radius-dependent. No gating on `source='manual'` vs. `automated_seed'` — all 40 current rows are pill-eligible; naming cleanup on the 38 auto-seeded rows happens pre-launch, not via a query filter. `switchRegion()` also needs to become cluster-aware (set `currentLocation` from a chosen cluster's `primary_zip`, not city/state strings) as part of the same rewrite.
- [ ] **Section 28 — Full site page inventory (Dual-Gateway landing, taxonomy page tiers, editorial template, admin shell) — scoped, not yet built.** See Section 28 body for the full route list. Public/user-facing: Dual-Gateway landing (`/`, replacing the current single "Let's Get Local" hero with a Get Local / Let's Explore split), `/explore`, `/hub/{slug}`, `/geo-hub/{slug}`, `/cluster/{slug}`, `/map`, and an editorial article template — all as FPO/rough-content stubs, real routes and nav. Admin shell (structure only, no functions wired): `/admin/` dashboard, `/admin/login.php` (auth-reuse decision pending), `/admin/hubs.php`, `/admin/clusters.php`, `/admin/review-queue.php` (table shell over the real 38 `automated_seed` rows), `/admin/listings.php`, `/admin/users.php`.
- [ ] **Phase 7 — Admin panel.** No interface exists yet. Tier 4 (admin) verification has nothing to trigger it, and Tier 2's auto-flagged "needs retroactive review" entities are accumulating in `verification_attempts` with no way to view them. **Confirmed sub-scopes (this session):** (1) Section 27's RBAC tiers now have live schema/helpers to build against; (2) a cluster review queue UI over existing `micro_clusters.source = 'automated_seed'` rows (38 live, unreviewed) — rename/reassign-geo_hub/promote-to-manual actions, Regional-Admin-scoped. No new crawler/staging logic needed — UI layer over existing columns only.
- [ ] **Auth.php / businessportal.php / TrustScore.php / api/ingest.php — legacy column call-site audit, partially complete.** Confirmed via direct grep of the real files: `Auth.php` genuinely uses `is_admin`/`role` (in `attemptLogin()`, `currentUser()`, `requireAdmin()`) — not yet migrated to the new `admin_access`-based helpers, left functional and untouched. `businessportal.php`, `TrustScore.php`, and `api/ingest.php` do **not** actually reference `owner_user_id` anywhere (a prior secondhand report claiming otherwise was checked against the real files and found incorrect) — no migration work needed in those three files for `owner_user_id`. `entities.owner_user_id` itself is retained temporarily per the add-then-migrate-then-drop sequence regardless.
- [ ] **Small admin verification tool (Section 20.10)** — designed, not built: search/create unclaimed listings, pending-review queue, one-click admin verify. Narrower and more urgent than the full Phase 7 panel.
- [ ] **White-glove delegate access (Section 20.10)** — designed, not built: `business_delegates` table, request/grant/revoke UI, delegate-submittable referrals/document-upload (never vouchers, never verification itself)
- [ ] **Document upload as a subsystem (Section 20.10)** — designed, not built: private off-web-root storage, file type/size limits, admin review
- [ ] **Voucher-request/invitation system (Section 20.10)** — designed, not built: lets a claimant invite a specific verified business to consider vouching, without compelling the outcome
- [ ] **Seed the initial vouching pool.** Tier 3 (vouchers) needs at least a few already-verified businesses to exist before it can work for anyone else — now naturally covered by the small admin verification tool above once built, rather than a one-off raw DB update.
- [ ] Public directory frontend — dual map/list interface (Section 12.5)
- [ ] Claim button / "claim this listing" UI on the public directory (explicitly deferred earlier as its own frontend task)
- [ ] Phase 5 — graph connection management UI, micro-publishing (`posts`) endpoints
- [ ] CMS / content engine, shortcode/graph-traversal content-to-map integration (Section 6)
- [ ] Full Phase 9 security hardening pass (rate limiting / brute-force protection on login, broader CORS/security audit — CSRF itself is done, Section 18)

### Explicitly deferred by decision (not forgotten, not currently planned)
- [ ] Booking/payment (Phase 6) — deferred until post-launch, tied to the broader communications system
- [x] ~~Delegate / multi-user business access — single-owner model only for now~~ **Superseded by Section 27's `listing_access` table** — multi-user business access is now schema-live (`owner`/`manager` tiers). `businessportal.php`'s UI/permission-check rewrite to actually use it is still pending (see call-site audit above), and ownership-transfer UI is not yet designed, but the "single-owner only" constraint itself no longer holds architecturally.
- [ ] Document-upload and domain-OTP as additional Tier 1 self-verification methods (domain-email match only, for now)
- [ ] Push notifications, native or Web Push (Section 20.8) — noted as a viable future direction for both security and general communication, no infrastructure built
- [ ] Cross-border (AZ/NM) + Navajo Nation micro-cluster cleanup — confirmed as real, queued work; explicitly waiting on the admin verification tool (Section 20.10/20.11), which will include cluster rename/merge/reassign as a built-in feature
- [ ] Some `TrustScore.php` constants remain unwired pending the features that would trigger them: community upvotes, dispute penalties, accuracy-report confirm/reject (all depend on Phase 7 admin tooling or features not yet built)

---

## 0. Platform Decision
Original plan assumed Brilliant Directories (BD) as the base platform. **Decided: BD is not being used.** Everything in the original blueprint framed as "BD overrides" (Step 8) will instead be built natively in the existing custom PHP/JS stack. No BD dependency, add-ons, or widget constraints apply going forward.

---

## 1. Search Model: Radius Slider + Taxonomy-Tier Fallback + Hierarchy Browse

Three related but distinct mechanisms, not one:

1. **Radius search (existing, unchanged):** lat/lon based, Haversine distance, the current user-adjustable slider/proximity UI. This is how "where I am right now, how far am I willing to travel" search works, dialed in by the user directly.
2. **Concentric taxonomy-tier fallback (new, automatic — confirmed mechanism, see worked example below):** when a query in a resident's home cluster returns zero or insufficient results, the system automatically escalates through the taxonomy tree in order — home cluster → adjacent cluster(s) → geo-hub → continental hub — rather than by mile-distance math. This is not user-triggered; it fires only when the tightest scope fails to satisfy the query. Behavior differs by Chameleon Filter mode:
   - **Utility mode:** escalation prioritizes distance-to-dispatch / service coverage (closest available provider, including those with matching Service Area Zip Codes).
   - **Experience mode:** escalation prioritizes corridor/narrative continuity (the next relevant stop along the historical/travel corridor) over raw distance.
3. **Hub hierarchy browse (new, manual/opt-in):** a separate, deliberate way to browse beyond one's immediate area on purpose — evolving the existing "Regional Hub Proximity" panel into a Hub → Geo-Hub → Cluster drill-down.

**Resolved — how #1 and #2 relate:** confirmed to coexist as two separate mechanisms. The mile-radius slider remains the resident's manual distance dial (Utility mode, "Bottom-Up" entry). The concentric tier-based fallback is a separate, automatic, system-triggered escalation that fires specifically when a query returns zero/insufficient local results — it does not replace the slider, and the user never manually triggers it.

Every business needs **both** a lat/lon (for radius) and a `cluster_id` (for hierarchy/tier fallback). Neither replaces the other.

**Worked example (from the concentric fallback doc):** a St. Johns resident searching for "24-hour hydraulic hose repair" with no local match automatically gets a Springerville/Eagar (Round Valley) provider surfaced without manually changing their location — Tier 2 of the escalation. If nothing exists there either, it expands to the Ancient Borderlands Geo-Hub (Show Low, Pinetop-Lakeside, Holbrook), pulling in mobile providers who registered that geo-hub as a Service Area.

**Two-method framing (Bottom-Up vs. Top-Down):** the same taxonomy data (cluster/geo-hub/hub) powers two inverse navigation flows depending on entry vector, not two different data models:
- **Bottom-Up (Get Local / Utility):** "Inside-Out" — starts at the micro-cluster, expands outward only via the slider or automatic tier fallback described above.
- **Top-Down (Let's Explore / Experience):** "Outside-In" — starts at the continental/macro-hub level with broad thematic selection, narrows through a **Linear Corridor Loop** (adjacent micro-clusters grouped into a natural travel corridor, e.g. US-180/191 through St. Johns and Round Valley), then drills into a specific micro-cluster stop. As the traveler zooms into a specific stop, the UI performs a **"Chameleon Pivot"** — switching from high-level storytelling to ground-level operational utility (fuel, dining, trading posts, cell service alerts) for that stop specifically. This refines the Contextual Handoff mechanic already noted in Section 7.3 of the original blueprint.

**Monetization tie-in:** this fallback mechanism gives Core Partners in Round Valley/Show Low visible evidence of spillover search demand from St. Johns — a concrete reason to upgrade to Strategic ($79/mo) to capture leads across the whole geo-hub via Service Area Zip Codes. Worth building into partner-facing upgrade messaging, not just the backend logic.

---

## 2. Geographic Taxonomy

Retained from the original blueprint, built natively (no BD):

- **Macro Hub** (`hub:`) — continental/theater level (~20 total, e.g. "Ancient America")
- **Geo-Hub** (`geo-hub:`) — regional corridor level (e.g. "Ancient Borderlands")
- **Micro-Cluster** (`cluster:`) — 3–5 neighboring towns bound into one local pool (e.g. "Cluster 1A: The Little Colorado & Petrified Basin")

**Naming convention (retained as-is from blueprint):**
- DB tag format: `hub_[id]:geo_[zone]:cluster_[id]` — e.g. `hub_aa:geo_borderlands:cluster_1a`
- URL format: `/[continental-hub]/[geo-hub]/cluster-[id]` — e.g. `/ancient-america/borderlands/cluster-1a`
- UI must always show the short-code paired with its descriptive name.

**Data needed (net-new, doesn't exist yet):** a zip-code → cluster mapping table. This is largely config/data-entry work, not logic — clusters have to be manually defined and zip codes assigned to them.

**Canonical pilot example (locked):**
- **Continental Hub:** Ancient America — Hub 9 of 20 (Four Corners & Colorado Plateau)
- **Geo-Hub:** Ancient Borderlands — the regional corridor connecting St. Johns to neighboring High-Country/Little Colorado Basin towns
- **Micro-Clusters (locked, manual/heuristic seeding for pilot):**
  - **Cluster 1A — St. Johns** (ZIP 85936)
  - **Cluster 1B — Round Valley / Springerville & Eagar** (ZIPs 85938, 85925)

**Single Home Rule (retained):** every business is pinned to exactly one Micro-Cluster. Secondary Service Area zip codes (for mobile/field-service businesses) may be added from a pre-mapped list within the same Geo-Hub only.

---

## 3. Homepage: Wide-to-Narrow Funnel

**Decided:** entry state is wide, not scoped. The homepage is a magazine-style front door with preconfigured **Topics**, **Regions**, and **Broad Experience content** that the visitor browses to narrow their own intent — not a hard-scoped view from the first click. "Tight" (cluster-scoped content/listings) is the *destination* of navigation, not the starting state.

- **"Let's Get Local"** and **"Let's Explore / Connect"** are the two entry doors, but both lead into the content authority engine — not directly into a pre-filtered directory. Listings surface *inside* content via shortcode injection, not as a parallel path.
- Once a visitor has narrowed to a specific cluster/region (via search, selection, or explicit location-setting), that becomes the active scope: **nothing outside the visitor's chosen cluster/region shows up in content or listings by default.** Widening scope (larger radius, or drilling up into geo-hub/hub) is always an opt-in action from that point on.
- Practical implication: the homepage cannot be "complete" as a static build — it depends on the CMS/content engine (Section 6) existing in at least a basic form. A placeholder/static version can launch first, but the real magazine experience is downstream of content tooling.

---

## 4. User Classes (Simplified from Blueprint)

Blueprint called for a strict wall between personal and business accounts. **Decided: deliberately diverge from that** — business listings require and attach to a registered user account (closer to how Google Business Profile itself works, and consistent with the GBP management service angle). Three tiers:

1. **Unregistered Visitor** — read-only browsing of content and listings, cluster-scoped once they've set a location. Any interaction (comment, save, claim) triggers registration.
2. **Registered User** — can set/follow hubs or clusters, link to and follow content, comment on content (comment system requires registration — see Section 6). This is the identity layer everything social/tracked hangs off of.
3. **Business Listing** — a directory profile with services, requires an owning Registered User account to create, claim, or manage it.

**Business listing lifecycle (refined — full state machine):**

```
unclaimed ──> claimed-unverified ──> pending-verification ──> verified
     │                │                       │                 │
     │                └──────────┬────────────┴─────────────────┘
     │                           ▼
     │                       disputed  (profile frozen during ownership investigation)
     │                           │
     └───────────────────────────┴──> pending-opt-out ──> opted-out (soft-suppressed)
```

- **Prepopulated / Unclaimed (default state):** Traversence pre-populates most listings itself (see Section 11, Phase A). Visible in the directory but owned by no user account — an initial seed state, not a pending-review state.
- **Claimed (Unverified):** a Registered User claims an existing unclaimed listing via a low-friction claim form. This opens the **Free Tier** business listing and grants immediate access to update basic details (hours, bio, phone, address). Profile displays a "Claimed – Unverified" indicator while awaiting verification.
- **Verification path (decided):** three independent routes to "verified" status, any one of which is sufficient on its own:
  1. **Self-verification** — domain match, document upload, or OTP.
  2. **Peer Validation** — 3 vouches from other verified local businesses, or a passing Community Confidence Score.
  3. **Admin vetting** — Traversence staff manually reviewing and accepting a listing through the admin pending-listings queue.
  
  Email confirmation is always required first regardless of which path follows, as the standard mechanism for all account security actions platform-wide (not a one-off claim feature).
  
  **Admin's role is oversight, not a mandatory gate:** admin review is not required for every claim to reach verified status — self-verification and Peer Validation can grant it independently. Admins retain the ability to flag, investigate, or reverse a verification after the fact if concerns arise (ties into the dispute system below), rather than sitting in the critical path of every claim. This meaningfully reduces admin workload at scale, at the cost of some fraud-vouching exposure the Community Reporting/dispute system (Section 4b) is designed to catch after the fact.
- **User-submitted new listing (business doesn't yet exist in the directory):** requires a capture/submission form, and goes through the same verification pipeline as claimed listings — one unified pipeline and one unified admin queue for both paths.
- **Self-serve opt-out (soft-suppression):** business owners can request removal via a public form, no account required. Requests queue for admin review; approval sets `is_suppressed: true`, which removes the listing from public search/map layers while retaining the underlying record and dedup hash — future crawler runs still match and patch internal metadata without making the listing public again. If an owner later decides to claim the business, completing the standard claim flow un-suppresses it instantly.
- **Disputed:** filing an ownership dispute freezes critical profile edits (payment links, website URL) and issues the existing claimant a 72-hour verification challenge (domain, license, or utility bill proof). Failure to verify revokes claim rights and resets or transfers ownership.

**Practical implication for the schema:** listings need the full status enum above, an `owner_user_id` that's nullable (unclaimed/opted-out listings have no owner), an `is_suppressed` flag distinct from status (so suppression survives future crawler matches), and a `submission_source` (prepopulated vs. user-submitted) for internal tracking. Simple `is_claimed`/`is_verified` booleans can be derived from status for anywhere the UI only needs a quick yes/no. The admin tool needs its own view/role — this is now a concrete build item, not a deferred question.

## 4b. Community Reporting & Dispute Resolution (new)

- **Information Accuracy Reports:** any user can submit data corrections (e.g. permanently closed, moved, changed hours). Submissions either adjust the listing's Community Confidence Score or notify the owner for confirmation, depending on submitter trust level.
- **Fraud prevention:** submissions from authenticated local user profiles or verified local businesses carry more weight than anonymous ones. Malicious or repeatedly-wrong reporting degrades the submitter's own trust score and risks account suspension — this is the same trust-scoring concept that feeds Peer Validation (Section 4), and is the primary safeguard against vouch-fraud now that Peer Validation can independently grant verified status without admin review.
- **Ownership disputes:** see the `disputed` state above — this is the formal escalation path when accuracy reports or a rival claim call a listing's current ownership into question.

**Current state gap:** auth today (`index.php` / `dashboard.php`) is a `traversence_user` record in `localStorage` only — no real backend accounts, sessions, or DB-linked identity. This has to become real before claim/follow/comment features mean anything.

**Cross-cutting security principle (decided):** email confirmation is the standard verification mechanism across the platform, not just for business claims — it applies to any account security action (registration, ownership changes, password resets, etc.). Worth building this as a shared, reusable service/flow rather than a one-off for the claim process, since it will be needed in multiple places from the start.

---

## 5. Chameleon Filter / Intent Tags

Retained from blueprint: every business and every piece of content gets tagged `[Utility]`, `[Experience]`, or both (hybrid bridge — e.g. general stores, cafes serving both audiences).

Combined with Section 3's funnel principle: the filter's job is to make sure a Resident-intent browse never surfaces Experience-only content/listings and vice versa, *within whatever geographic scope is currently active.*

---

## 6. CMS / Content Engine

Net-new system, doesn't exist yet. Requirements gathered so far:

- Editorial content organized by the Dual-Track model: **Utility Track** (practical/resident-focused: infrastructure, property, automotive, seasonal readiness) and **Experience Track** (heritage/traveler-focused: history, trade routes, itineraries, culture).
- **Shortcode injection**: articles dynamically pull live directory listings scoped by cluster + tag (e.g. `[directory_feed cluster="cluster_1a" tag="Utility" category="Core Infrastructure"]`).
- **Author permission layer, tied to subscription tier**: paid-tier businesses can author content themselves (not just appear as a linked resource inside staff-written content). This is a deliberate differentiator — businesses earn placement inside the editorial content itself, not just directory-rank prominence.
- **Comment system**: requires a registered user account to participate (ties to Section 4). Needs content tracking/engagement data tied to real user IDs — not compatible with the current localStorage-only auth.
- Feeds into the Endorsement Tag mechanism from the original blueprint (moderator-applied tags linking community recommendations to verified business profiles) — not yet scoped in detail.

**Resolved:** see Section 7 — authoring rights are gated to Strategic Partner tier and above, not a separately priced unlock.

---

## 7. Monetization Layer — Product & Pricing Architecture

Confirmed tier structure (from Part 2: Business Overview), replacing the earlier placeholder — this also resolves the open question on authoring rights:

| Tier | Price | Includes |
|---|---|---|
| **Regional Business Directory** (Free) | $0 | Baseline profile, map pin, core operational data. This is the tier a listing enters at once claimed. No content authoring rights. |
| **Core Partner** | $19/mo | Enhanced profile, direct map integration, priority placement within directory categories, eligibility for white-glove add-ons (including the GBP management add-on, see below). Still **no** content authoring rights — placement only. |
| **Strategic Partner** | $79/mo | **Unlocks content authoring** — ability to contribute/publish partner content, plus the "Connection Feature" (Owner Spotlight Interview), rich media, White-Glove Setup, quarterly feature updates. |
| **Cornerstone Partner** (Complete Inclusion) | $249/mo | Category leadership/maximum visibility within its Regional Hub, priority routing, **full content contribution rights**, complete White-Glove Support. |

**Resolves earlier open question:** authoring rights are **not** a separate unlock from placement tiers — they're bundled in, and specifically gated to **Strategic Partner and above**. Core Partner and Free tiers affect listing prominence/placement only; they do not grant CMS authoring access. The permission layer in Section 6 should key directly off `plan_tier >= strategic_partner`.

**White-Glove GBP Management Add-on** (ties directly to the existing GBP landing page initiative — see prior context): available exclusively to Core Partner and above. $125 one-time setup fee per location, $50/mo per location recurring. Multi-location businesses incur proportional fees per additional location. This is a natural implementation link between the GBP landing page/lead-gen work already underway and the business-tier backend being designed here — a Core Partner signup is a qualified lead for the GBP add-on.

**Practical implication for schema:** `plan_tier` needs at least 4 states (free / core / strategic / cornerstone), and a separate boolean/flag for the GBP add-on per location, since it's cross-cutting (available at Core+ but is its own billable line item, not a tier itself).

---

## 8. Build Sequence (current thinking, subject to revision)

1. Taxonomy data model — hubs/geo-hubs/clusters table + zip mapping, add `cluster_id` to businesses (radius search continues running unchanged in parallel)
2. Real backend user accounts/auth/sessions (replacing localStorage `traversence_user`) — prerequisite for nearly everything below
3. Business claim/manage flow tied to taxonomy + verification decision (Section 4 open question)
4. Hub/Geo-Hub/Cluster browse UI layered onto the existing "Regional Hub Proximity" panel
5. Intent tags (`[Utility]` / `[Experience]` / hybrid) on businesses, then content once it exists
6. CMS/content engine: authoring, shortcode injection, comment system, tier-based permissions
7. Homepage magazine rebuild (Topics/Regions/Broad Experience funnel), once content engine (step 6) has enough substance to power it

---

## 9. Business Model & Phased Rollout Context

Sourced from the full business blueprint (Parts 1–8, financial/scaling projections). This section is business context informing technical priority, not itself a build item.

- **Pilot scope (confirmed):** the full vision is 20 Continental Hubs at scale, but the actual near-term build target is **one pilot hub — Ancient America (Colorado Plateau), anchored on St. Johns, AZ and Apache County** — which matches what's already built in `index.php`. Taxonomy tables should be designed to hold multiple hubs eventually, but only this one hub needs real data populated now. Treat this spec as pilot-hub architecture that happens to scale, not a mandate to build out all 20 hubs immediately.
- **Tier revenue mix (informational, not a build requirement):** business model assumes a 70% Core / 20% Strategic / 10% Cornerstone partner distribution per hub, used for financial projections. Doesn't change the schema beyond the `plan_tier` field already noted in Section 7.
- **Bootstrap funding mechanism:** the platform's own zero-capital launch strategy is direct GBP (Google Business Profile) audits/fixes sold to local businesses for an upfront flat fee — this is precisely what the existing `gbp-landing-traversence.html` page (logo, testimonial, and form backend still outstanding) is for. That page isn't a side project; it's the funding mechanism for the rest of this build, worth prioritizing accordingly.
- **Staff roles (Regional Directors / Field Executives):** the financial model assumes hub-scoped human staff handling partner acquisition and verification. For the pilot phase (one hub, one operator), the admin/verification tool can be built as a single global-admin role — but shouldn't be hardcoded so tightly that adding hub-scoped staff roles later requires a rewrite.

## 10. Net-New Feature Areas Identified (Not Yet Scoped)

- **Direct-to-operator booking/payment routing** — two paths described: Native PMS integration (webhook/API token handshake, funds settle directly to operator's merchant account) and an "Independent Alternative" (direct-link reservation routing via email/SMS/dashboard messaging, operator handles billing themselves). Zero commission on both. **Resolved — deferred:** confirmed as a later-phase feature, to be built alongside the broader communications/connection system between users and businesses (messaging, lead handoff, etc.) rather than as part of the initial directory/CMS/taxonomy build.
- **Content typology system:** CMS needs to support at least three distinct content types with different behavior, not one generic "article": Foundational Macro/Geo-Hub Guides (~2,000–3,000 words, structural), Dynamic Itinerary/Editorial Stories (~1,200–1,500 words, the Authority Engine content), and Partner Profile Enrichment (commercial copywriting/photo/metadata, tied to a business listing). Refines Section 6.
- **Endorsement Tags:** moderator action linking a community-thread recommendation to a verified business profile as permanent social proof. Requires the comment/thread system (Section 6) plus a moderation action and a data link from thread → business profile. Confirmed as a real feature, not just a passing mention.

## 11. Dynamic Taxonomy & Discovery Engine (Phased)

Two distinct capabilities, deliberately kept separate due to very different risk/readiness profiles:

**Phase A — Automated Business Discovery/Seeding & Boundary Generation (near-term, low-risk, high-value):** two parts, both geography/registry-driven rather than usage-driven (avoids the cold-start problem — see below):
- **Cluster generation:** an automated migration script groups existing/harvested business records by ZIP code, evaluates geographic centroid/contiguity within a distance threshold (e.g. ~5–8 miles), and auto-generates initial micro-clusters (`source: "automated_seed"`, `status: "approved"`). This is purely geography-based, not demand-signal-based — it needs no usage/traffic history to run, which resolves the cold-start concern originally raised about dynamic clustering. Confirmed to reproduce the same pilot result already locked manually: Eagar/Springerville merge into Cluster 1B, St. Johns remains its own Cluster 1A.
- **Business discovery:** crawl state business registries, the Google Business Profile API (a sanctioned API, not scraping), and chamber-of-commerce directories to auto-populate baseline `unclaimed` listings — directly automates the manual business-cataloging work already called for in the blueprint's rollout plan, and feeds straight into the listing lifecycle (Section 4).
- **De-duplication & enrichment ("Match & Enrich"):** scraped records are matched via a composite hash (`SHA256(lowercase(trim(name) + trim(address) + trim(zip)))`). A hash match triggers a **non-destructive patch** — only missing fields (hours, phone, website) get populated; verified or manually-entered data is never overwritten by a later crawl.

**Phase B — Adaptive, LLM-Assisted Cluster Boundary Engine (later phase, deferred):** a backend pipeline (calling the Claude API as a service — distinct from this chat) that scores ZIP-code interdependency from demand/supply/corridor/topographical signals and proposes micro-cluster and geo-hub boundary adjustments as traffic and claim data accumulate. **Deferred, not rejected**, for three reasons:
- **Cold-start problem:** there's no usage/search data yet for the pilot hub to cluster on — something has to seed the first boundaries before any adaptive system has data to learn from.
- **Scraping risk:** business registries and the Google Business API are safe sources; scraping social media groups, forums, and municipal boards for demand signals carries real ToS/legal exposure worth a compliance review before building, separate from the engineering work.
- **Trust cost of errors:** the Single Home Rule's core value proposition is that residents always see their community grouped correctly — an algorithmic mis-clustering isn't a minor bug here, it undermines the product's central trust claim. Any system-proposed boundary change should require human approval before publishing, not auto-publish.

**Schema implication (build now, use later):** cluster records should include a `source` field (`manual` vs. `system-proposed`) and a `status` field (`draft` vs. `approved`) from the start, so Phase B can slot in later as a proposal-review workflow without a schema rewrite — even though only manual/heuristic clusters exist at launch. Example record shape: `{cluster_id, source: "manual"|"system_proposed", status: "approved"|"draft", confidence_score}`.

**Phase 1 — Legal & Indexing Compliance Standards (applies to data harvesting now, not deferred):** crawling is limited to unauthenticated public web data and official APIs only — gated social groups, private forums, and password-protected areas are explicitly excluded (this also resolves the earlier ToS/legal caution: the "scrape forums and social groups" idea from the original crawler concept is dropped in favor of open-web/API sources only). Extracted data is limited to facts (names, addresses, categories) and short transformative snippets; AI-generated editorial content must cite source URLs. Technical compliance includes `robots.txt`, user-agent policies, and rate limiting. Legal grounding referenced: hiQ v. LinkedIn (public-data scraping), Authors Guild v. Google (transformative indexing as fair use), Feist v. Rural (limited copyright protection on facts/compilations) — reasonable framing, but **a real attorney review is warranted before Phase 1 harvesting goes live**, not just reliance on case citations in this spec.

**Phase 2 — Deterministic Clustering Logic (the Adaptive Isolation Index):** once adaptive clustering resumes, boundary/fallback-radius decisions use a weighted Cluster Interdependency Score (service gap + proximity + density ratio) and an inverse travel-boundary formula — low-density/frontier nodes (e.g. St. Johns) get a wider acceptable travel boundary (~45–60 min / 35–50 mi) since local resources are sparse, while high-density/metro nodes (e.g. Phoenix) get a tighter boundary (~10–15 min / 3–5 mi) since local resources are saturated. Low-resource nodes treat nearby high-resource hubs as primary fallback targets while keeping their own distinct local core under the Single Home Rule. **This is Phase 2 tooling — the pilot itself launches with the fixed manual clusters (1A/1B) above, no formula involved at launch.** The specific weights and thresholds are reasonable starting heuristics, not yet empirically calibrated — expect tuning once real usage data exists.

**Practical resolution for the St. Johns/Round Valley boundary question — resolved:** locked as Cluster 1A (St. Johns) and Cluster 1B (Round Valley/Springerville & Eagar), both within the Ancient Borderlands geo-hub. See Section 2 for full detail.

## 12. Technical Implementation Architecture

Confirmed with the Master Handoff Briefing. This is the concrete schema/stack translation of Sections 1–11 above — treat this as authoritative for DDL and API work going forward.

### 12.1 Database: "Fake Graph in Relational SQL"

Three-table normalized structure mirroring a graph model, chosen to launch cheaply on shared hosting (BlueHost MySQL/MariaDB) with a clean migration path later to a managed graph/geo stack (Supabase+PostGIS or Neo4j):

- **`entities`** (nodes) — single polymorphic table for every entity type (merchant, place, topic/content, consumer). Key columns: `id`, `entity_type`, `name`, `composite_hash` (SHA256(name+address+zip), O(1) dedup — matches the Section 4 dedup pattern), `primary_zip`, `micro_cluster_id`, `latitude`, `longitude`, `status`, `is_suppressed`, `created_by_type`, `updated_by_type`, `created_at`.
- **`entity_metadata`** (flexible attributes) — key-value/JSON store for type-specific polymorphic data (hours, price range, social URLs, historical era details), avoiding sparse columns/NULL bloat. Includes `source_type`.
- **`connections`** (edges) — directed relationships between entities. Key columns: `id`, `source_id`, `target_id`, `relationship_type` (e.g. `OWNER_OF`, `LOCATED_AT`, `OFFERS_PRODUCT`, `VERIFIED_BY`, `INTERESTED_IN`, `FEATURED_IN`), `created_by_type`, `created_at`.

**Data provenance & origin tracking (new):** `entities`, `entity_metadata`, and `connections` all track origin via a `created_by_type` (and `updated_by_type` on `entities`) enum: `ai_crawler` / `user` / `owner` / `admin`, defaulting to `ai_crawler`. **Override logic:** once a human (`owner`, `admin`, or high-trust `user`) edits a specific field, that field is locked against future automated crawler overwrites — this is the mechanism that makes the "non-destructive patch" behavior in Section 12.3 actually enforceable at the field level, not just at the record level.

**Content lives in the same graph, not a separate CMS table** — for editorial guides, topic maps, and itineraries specifically. Articles/guides are `entity_type: "topic"` or `"content_asset"` nodes; the shortcode-injection concept from the original blueprint (Section 6) is implemented as graph traversal — querying `connections` between a content node and nearby place/business nodes — rather than a special-purpose parser bridging two separate systems.

**Micro-publishing (`posts` table) is a deliberate exception, not a contradiction:** owner announcements, seasonal updates, and quick promotions are lightweight and high-volume, and sit in their own dedicated `posts` table (`entity_id`, `user_id`, `status`: draft/published/archived, soft-delete) rather than the graph. They don't need graph-edge traversal semantics, and routing them through `connections` would add query volume/noise to the graph for no benefit. Editorial content (guides, itineraries, topic maps) remains fully graph-based as above — `posts` sits *alongside* that model, not in place of it.

### 12.1b Exception-Based Cluster Governance (revised — reverses earlier Phase B gating decision)

**This intentionally reverses the "human approval required before a system-proposed boundary change publishes" position from Section 11 Phase B.** Confirmed as a deliberate choice, consistent with the same ship-first/self-correct pattern already applied to Peer Validation (Section 4) and Phase 1 seeding (Section 11):

- **Zero-friction ingestion:** automated/crawled clusters (`source: "automated_seed"` or `"system_proposed"`) go live immediately on generation — no pending/draft review gate.
- **No `status` column on `micro_clusters`:** all cluster records are active by default.
- **Correction happens after the fact, not before publish:** inaccuracies are reported via the standard public data-correction form (`report_type = 'cluster_mismatch'`, see Section 4b). High-trust reports or owner edits write directly to a `cluster_overrides` table (`entity_id`, `forced_cluster_id`, `is_excluded`). Directory queries perform a `LEFT JOIN` against `cluster_overrides` so corrections apply seamlessly without touching the underlying auto-generated cluster assignment.

### 12.2 Listing State Machine (schema mapping)

Maps directly onto Section 4's state machine: `unclaimed → claimed-unverified → pending-verification → verified → disputed → pending-opt-out → opted-out`, with derived booleans (`is_claimed`, `is_verified`, `is_suppressed`) for fast UI conditionals. Claiming is frictionless/un-gated — opens editing immediately under `claimed-unverified`.

**Verification routes (three independent paths, per Section 4's resolved decision):**
- **Self-Verification** — domain email match or OTP.
- **Peer Validation** — 3 vouches from existing verified entities, implemented as `VERIFIED_BY` edges (auditable: always traceable who vouched for whom, feeding the fraud-prevention/trust-score system in Section 4b).
- **Admin Override** — manual approval through a **lightweight custom-built `/admin` panel** within the application (not BlueHost's cPanel hosting interface). Confirmed scope: reviewing pending claims, verifying listings, approving soft-suppression opt-outs, and reviewing dispute tickets.

**Frictionless opt-out:** unauthenticated public opt-out requests soft-suppress (`is_suppressed = true`, `status = opted-out`). `composite_hash` remains stored so future crawler runs auto-suppress re-imports without going public again — matches Section 4's soft-suppression/reactivation design.

### 12.3 Offsite Crawler Execution

**Zero resource choking on BlueHost:** multi-threaded scrapers and bulk data processing never run on the shared production server. Offsite workers (lightweight VPS or serverless) parse public data, compute the composite hash externally, and POST lightweight REST payloads (INSERT/UPDATE) to the BlueHost API endpoint.

- **Phase 1 (automated baseline seeding):** ingest existing/harvested business records, group by ZIP, evaluate 5–8 mile contiguity, auto-generate micro-clusters (`source: "automated_seed"`, `status: "approved"`) — matches Section 11 Phase A, purely geography-driven, no cold-start dependency on usage data.
- **Phase 2 readiness:** schema fields (`source: "system_proposed"`, `status: "draft"`, `confidence_score`) exist from day one so the adaptive LLM boundary-proposal engine (Section 11 Phase B) can slot in later without a migration.

### 12.4 Stack

- **Database:** MySQL/MariaDB on BlueHost, strict indexes on `composite_hash`, `primary_zip`, and connection foreign keys.
- **Backend/API:** PHP/PDO, for continuity with the existing live codebase (`index.php`, `dashboard.php`, `search_listings.php`, `nearby_hubs.php`) — staying in PHP avoids re-platforming everything already built. (Next.js API routes was floated as an alternative in the handoff doc but would mean a full re-platform; not adopted.)
- **Frontend mapping:** lightweight JS mapping library (Leaflet.js or MapLibre GL) plus responsive slide-over drawer panels.
- **Billing Sync:** dedicated PHP Stripe webhook listener (`api/stripe-webhook.php`) syncing subscription tier updates automatically — resolves the earlier gap where Payment Links alone wouldn't keep `plan_tier` in sync with Stripe's subscription state.

### 12.5 UI Architecture: Spatial-First with Dual-Mode Directory

Resolves the earlier map-vs-homepage question: **the interactive map is not the site's landing page.** The magazine-style, wide-to-narrow content homepage (Section 3) remains the actual front door at traversence.com. The map is specifically the interface for the **Get Local / Directory / Cluster exploration view**, reached either directly (Get Local entry) or via content ("Explore on Map" traversal from an article).

- **Dual Interface (Map + List), confirmed:**
  - **Desktop:** split-screen — Map View and scrollable List/Grid View synchronized; hovering/clicking a listing card highlights its map pin.
  - **Mobile:** responsive layout with a floating Map/List toggle.
  - **Unified filters:** mile-radius slider, categories, and search queries stay synchronized across both views simultaneously.
  - **Contextual Drawers:** selecting a pin opens a non-disruptive sliding drawer/bottom sheet (name, photo, category, verification state) rather than a full page navigation — this is the "Infinite Hop Navigation" mechanic (Place → Product → Topic → Person) referenced in the executive summary, implemented as graph traversal via the `connections` table.

- **Content-to-Map Spatial Integration:**
  - Standard articles/guides remain clean text/media by default — spatial treatment is opt-in, not automatic for all content.
  - **Itinerary/Story Maps:** content tagged `entity_type: "content_asset"` (itineraries, regional loops) get connected, numbered route pins showing stop sequence, distance, and travel time.
  - **Article-to-Map Traversal:** any guide/story mentioning local businesses/landmarks (via `FEATURED_IN`/`LOCATED_AT` edges) gets an "Explore on Map" button, jumping into the Directory Dual View pre-filtered to just that content's featured places.
  - **Route Exporting:** users can export saved itinerary stops/node lists to external navigation tools (Google Maps, Apple Maps).

## 13. MVP Build Roadmap & Scope Boundary

Nine-phase implementation task list, confirmed as the full platform lifecycle — **not all nine phases are in scope for initial launch.**

**MVP scope (build now): Phases 1, 2, 3, 4, 5, 7, 8, 9.**
- Phase 1: environment/schema deployment
- Phase 2: user identity & auth
- Phase 3: crawler ingestion (`/api/ingest.php`, dedup hashing, field-locking, Phase A automated seeding)
- Phase 4: claims, Peer Validation (auto-elevate to `verified` at ≥3 distinct `VERIFIED_BY` edges), merchant dashboard
- Phase 5: graph connection management + micro-publishing (`posts`)
- Phase 7: admin governance dashboard, soft-suppression/opt-out workflow
- Phase 8: dual map/list directory interface, content-to-map traversal
- Phase 9: security audit, BlueHost deployment hardening

**Explicitly deferred to post-launch: Phase 6 (E-Commerce & Monetization).** Confirms and reaffirms the earlier decision that booking/payment routing is a later-phase feature tied to the broader user-business communications/connection system — payment gateway integration, `orders`/`transactions`/`payment_methods` tables, subscription billing automation, and the customer billing dashboard all wait until post-MVP user/business engagement flows are live.

**Delegate/multi-user business access — deferred.** Initial release uses the single-owner model strictly (`entities.owner_user_id`). No `business_delegates` join table in the MVP schema. Multi-user access control is bundled with the deferred e-commerce/advanced-messaging rollout, not built separately before then.

**Naming standardization (confirmed):** `Database::connection()` / `Database::transaction()`, and `.env` keys `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` (matching the already-built `Database.php`) — not `getInstance()` or `DB_NAME`/`DB_USER`/`DB_PASS` as an earlier draft of the task list used. `CRAWLER_API_TOKEN` added to `.env.example` for the Phase 3 ingestion endpoint.

## 14. Phase 2 Deliverable: Authentication API

Built as concrete code, matching Section 13's MVP scope. Files under `api/`:

- **`bootstrap.php`** — shared entrypoint: secure session cookie config (httponly, samesite=Lax, secure when HTTPS detected), starts the PHP native session, issues a per-session CSRF token, sets JSON response headers.
- **`lib/Response.php`** — consistent JSON response helper (success/error/method-guard/body-parsing).
- **`lib/Auth.php`** — core identity logic: `register()`, `attemptLogin()`, `logout()`, `currentUser()`/`currentUserId()`, `requireAuth()`/`requireAdmin()`, `issueEmailConfirmation()`/`confirmEmailToken()`, `requestPasswordReset()`/`resetPassword()`.
- **`auth/register.php`, `auth/login.php`, `auth/logout.php`, `auth/session.php`, `auth/verify-email.php`, `auth/password/request-reset.php`, `auth/password/reset.php`** — the actual endpoints.
- **`middleware/require_auth.php`, `middleware/require_admin.php`** — reusable guards for Phase 3+ endpoints (claim, connections, posts, admin governance) to include at the top of the file.

**Key decisions made while building this, not previously specified:**

- **Session strategy: native PHP sessions, not JWT.** Chosen deliberately for the BlueHost shared-hosting target — no extra infrastructure (Redis, token blacklist, etc.) needed. Flagged as swappable later if a separate stateless mobile client needs JWT, without touching the core `Auth` login logic.
- **Password hashing: ARGON2ID with automatic fallback to BCRYPT** if the PHP build lacks the Argon2 extension (some shared-hosting builds omit it) — satisfies the roadmap's "PASSWORD_ARGON2ID or PASSWORD_BCRYPT" requirement without hardcoding one.
- **Email verification/password reset both route exclusively through `email_confirmations`**, not per-purpose token columns on `users`. This surfaced a real inconsistency in the schema as originally written (`users` had its own redundant `email_verification_token`/`email_verification_expires` columns) — **removed those columns from the schema** in favor of the one generic mechanism, consistent with the spec's "email confirmation is the standard mechanism for all account security actions platform-wide" principle (Section 4).
- **Anti-enumeration by design:** login and password-reset-request both return identical generic responses whether or not the email exists, to avoid leaking which addresses have accounts.
- **CSRF token issued on every session**, ready for the frontend to wire up, though full enforcement middleware is explicitly deferred to Phase 9 per the roadmap — the token exists now so nothing has to be retrofitted later.
- **Rate limiting/brute-force protection on login is not yet implemented** — flagged in the code as a Phase 9 item, not silently skipped.
- **Email delivery itself is not wired up.** `register.php` and `password/request-reset.php` both generate real tokens and leave an explicit integration point (`// Mailer::sendVerificationEmail(...)`) rather than guessing at a mail provider — BlueHost supports PHP's native `mail()` if no transactional email service is configured yet.

## 15. Phase 3 Deliverable: Legacy Migration & Crawler Ingestion

**Critical context discovered mid-build:** the platform already has a live production database (`listings`, `users`, `zip_coordinates` — a flat, non-graph schema), not a blank slate. Confirmed as **Option 2**: single controlled migration into the graph architecture (Sections 12–14), with `listings` as the one-time seed source rather than an ongoing parallel schema.

**Files built:**
- **`schema/migrations/002_fix_zip_coordinates.sql`** — fixes the legacy `INT(5)` ZIP truncation bug (drops leading zeroes on Northeast/East Coast ZIPs) by converting `zip_coordinates.zip` to `VARCHAR(5)` and zero-padding. Must run before the migration script, since coordinate fallback lookups depend on it. `listings.zip` itself is left un-altered since that table is being retired, not kept in service — the migration script zero-pads it at read-time instead.
- **`api/lib/CrawlerIngest.php`** — shared logic between the migration script and the live endpoint (hashing, ZIP normalization, cluster resolution/auto-seeding, locked-field-aware upsert), so the two paths can't drift out of sync over time.
- **`api/ingest.php`** — live crawler endpoint. Bearer-token auth against `CRAWLER_API_TOKEN` (fails closed if unconfigured), accepts a single record or a batch array, each record processed in its own transaction so one bad record doesn't roll back an entire batch, returns HTTP 207 on partial batch failures.
- **`scripts/migrate_listings.php`** — one-time CLI migration, `--dry-run` and `--geo-hub=<slug>` flags, single transaction (appropriate at pilot scale — flagged in-code to revisit chunked commits if `listings` ever grows into the thousands before this needs to run again), prints a per-row and summary report to stderr.

**Design decisions made while building this:**

- **Core entity fields (`name`, `latitude`, `longitude`, `primary_zip`) are only crawler-patchable while a listing is still `unclaimed`.** Once claimed, only `entity_metadata` is touched (and only unlocked keys), never the core entity row — this extends the existing `is_locked` field-locking concept from Section 12.1 to the entities table itself, not just metadata, since it wasn't originally specified but is a direct consequence of the same principle.
- **Category → Chameleon Filter intent mapping is a starting lookup table, not exhaustive.** Unmapped categories default to Utility=true/Experience=false (the safer default for a rural-infrastructure-first platform) and get flagged with an `intent_needs_review` metadata key rather than silently guessing wrong forever — this makes unmapped categories queryable/fixable later.
- **Auto-seeded clusters (`resolveMicroClusterId`) only target a single configured geo-hub (`DEFAULT_GEO_HUB_SLUG`).** Correct for the pilot, since the crawler only operates within Ancient Borderlands right now. **Explicitly flagged as a limitation, not silently extended:** once a second hub/geo-hub goes live, auto-seeding needs a real way to pick the correct geo-hub per incoming record (e.g. a region parameter the crawler config supplies), not a single default.

**Two items not resolved — flagged rather than guessed:**

1. **Legacy `status` mapping is genuinely ambiguous and handled conservatively, not decided.** The old `pending`/`active`/`rejected` enum doesn't map cleanly onto the new lifecycle. Current behavior: `active` → `unclaimed`/visible; `rejected` → `unclaimed`/suppressed (never silently resurrected); **`pending` → `unclaimed`/suppressed as a conservative default** — since "pending" under the old admin-approval workflow may have meant "deliberately withheld for a reason," and the new model has no pre-publish gate to fall back on. The migration script prints a count of suppressed `pending` rows and flags them for manual review rather than assuming they're safe to auto-publish. **Needs an explicit decision on how to handle the `pending` backlog**, not just the conservative default standing in permanently.
2. **`users` table structure — confirmed, and the migration reconciled through two rounds.** Real columns: `id` (INT(10) UNSIGNED), `email`, `password_hash`, `role` ENUM(`'admin'`,`'business_owner'`) default `business_owner`, `created_at`. **Separately fixed (schema bug, no judgment call):** every FK across the schema referencing `users.id` had been typed `BIGINT UNSIGNED` before this table was seen, but the real column is `INT(10) UNSIGNED` — corrected across `entities.owner_user_id`, `email_confirmations.user_id`, `posts.user_id`, `disputes.filed_by_user_id`, `accuracy_reports.reported_by_user_id`, `opt_out_requests.reviewed_by_user_id`, `verification_attempts.reviewed_by_user_id`, `cluster_overrides.created_by_user_id`; the conflicting `CREATE TABLE users` block was removed from `traversence_schema.sql` in favor of the additive migration below. Final reconciled migration, **`schema/migrations/003_alter_users.sql`**, adds `display_name`, `email_verified_at`, `is_admin`, `trust_score`, `is_suspended`, `home_cluster_id`, `updated_at` via `ALTER TABLE ... ADD COLUMN`; purely additive, no existing rows touched beyond an `is_admin` backfill. Must run *after* `traversence_schema.sql` (needs `micro_clusters` to exist for its FK) — full order: `002_fix_zip_coordinates.sql` → `traversence_schema.sql` → `003_alter_users.sql` → `migrate_listings.php`.
   - **`is_admin` design settled: a stored, independently-set column, not derived from `role`.** An earlier draft of this migration derived admin status from `role === 'admin'` to avoid two fields drifting out of sync; a later draft proposed a stored, backfilled column instead. Adopted the stored version — it **decouples admin permission from the `role` enum entirely**, meaning `role` stays free to be extended later (e.g. adding a `resident` value) without that change ever affecting who has admin rights. This is a genuine improvement over the earlier design, not just a style choice.
   - **Fixed real gaps in an intermediate draft of this migration before adopting it:** a proposed patch omitted `display_name`, `is_suspended`, and `updated_at` — all three are already referenced by the delivered `Auth.php` (registration writes `display_name`/`updated_at`; login checks `is_suspended`), so omitting them would have broken registration/login with SQL errors on the next deploy. Added back. Also fixed `home_cluster_id` to `INT UNSIGNED` (matching `micro_clusters.id`'s real type, an intermediate draft had it as signed `INT`) and added the actual `FOREIGN KEY` constraint (an intermediate draft only added an index, not the constraint — meaning an invalid cluster ID could have been stored with nothing to catch it).
   - **`trust_score DEFAULT 0.00` — implemented; the earning mechanism gap is resolved, see Section 16.** No longer an incomplete design — Section 16 defines the full points economy that grows it from this baseline.
   - **`role` enum semantics — resolved.** The lean two-value enum stays as-is for organizational context; `is_admin` (already implemented as the sole authorization signal in `Auth.php`) is what actually gates access. Decoupling these means `role` remains free to later gain values like `resident`/`moderator`/`contributor` without ever affecting access control. `Auth::register()` still leaves `role` unset on insert, falling through to the table's default — low-stakes now that `role` carries no authorization weight.

## 16. System-Wide Trust Score Architecture

Extends trust scoring to `entities` and `entity_metadata`, alongside `users.trust_score` (Section 15). Full points economy and enforcement logic now centralized in a new shared service, **`api/lib/TrustScore.php`**, rather than scattered across endpoints as magic numbers.

**Schema:** `schema/migrations/004_add_trust_scores.sql` — adds `entities.trust_score` (DEFAULT 10.00, indexed) and `entity_metadata.trust_score` (DEFAULT 10.00). Run order is now `002_fix_zip_coordinates.sql` → `traversence_schema.sql` → `003_alter_users.sql` → `004_add_trust_scores.sql` → `migrate_listings.php`.

**Design decision — enforcement lives in application code, not DB triggers.** MySQL triggers would fire on every write to these tables regardless of which code path caused it, making behavior harder to trace, test, or disable on shared BlueHost hosting. Every future caller that adjusts a trust score must go through `TrustScore::adjustUserTrust()` / `adjustEntityTrust()` rather than writing to the columns directly, or the threshold effects below won't fire.

**Reconciled against an alternate implementation proposal — two real conflicts resolved, one improvement adopted:**
- **Rejected: dropping the metadata-level constants.** An alternate draft of this class omitted `entity_metadata` scoring entirely (no AI-summary baseline, no admin/owner-edit value) and two of the user/entity deltas. Adopting it as-is would have broken already-wired code — `CrawlerIngest.php` references `METADATA_AI_SUMMARY_BASELINE` directly. Restored the full constant set.
- **Rejected: automatic un-suppression.** The alternate draft recomputed `is_suppressed` fresh on every adjustment (`newScore < threshold ? 1 : 0`), meaning a suppressed listing would automatically reappear the moment positive adjustments pushed its score back up — with no admin ever reviewing why it was suppressed in the first place. Kept the one-directional policy: suppression only ever gets *set* automatically, never *cleared* automatically; reinstating a suppressed listing is a deliberate admin action, consistent with opt-out reactivation elsewhere in the spec.
- **Adopted: `SELECT ... FOR UPDATE` row locking** for the entity adjustment's read-compute-write sequence — more defensive concurrency practice than the original read-after-write approach, genuinely better for this kind of code even though the original wasn't actually broken.
- **Fixed a bug found while reconciling, not present in either external draft:** the original `adjustEntityTrust()` hardcoded `updated_by_type = 'admin'` on every call, which is simply wrong for e.g. a crawler-triggered geocode bonus. Now takes an explicit `$actorType` parameter so provenance tracking (Section 12.1) stays accurate for score-driven updates, not just content edits.

**Interpretation, stated explicitly since the source instructions could be read either way:** the existing three-path verification model (Section 4) remains authoritative for setting `status = 'verified'` — trust_score is a parallel signal for ranking/moderation, not a second independent gate to that status. "Claiming a Verified Entity: +50 → immediately elevates to verified" is read as *the same verification event doing two things at once* (setting status directly, and separately adding points), not as "crossing a point threshold independently triggers verification."

**What's actually wired up now vs. specified for later** (most of the points economy has no calling endpoint yet, since the features that would trigger it — claim, dispute resolution, accuracy report resolution, community edits — aren't built until later phases):

| Points event | Status |
|---|---|
| User: email verified (+10) | **Wired** — `Auth::confirmEmailToken()` |
| Entity: AI crawler baseline (10.00) | **Wired** — `CrawlerIngest::upsertEntity()`, creation only |
| Entity: geocode/address match (+5) | **Wired** — only when Traversence's own `zip_coordinates` fallback resolves coordinates the payload didn't already supply, and only at creation (never re-applied on re-crawl — see anti-gaming note below) |
| Metadata: AI summary baseline (20.00) | **Wired** — applies only to the `ai_summary` key specifically; every other crawler-sourced field uses the column default (10.00) |
| Entity: suppression below 0 → `is_suppressed = 1` | **Wired** — enforced inside `adjustEntityTrust()`, maps directly onto the existing suppression flag from Section 4. Does not auto-*un*suppress if score later recovers — re-surfacing is a deliberate admin action, consistent with opt-out reactivation elsewhere in the spec |
| User: claim verified entity (+25) | Not yet wired — no claim endpoint exists (Phase 4) |
| Entity: human creation, inherits submitter's score capped at 25 | Not yet wired — no "submit new listing" form exists yet |
| Entity: verified claim (+50, sets status directly) | Not yet wired — no claim endpoint exists (Phase 4) |
| Entity: community upvote (+1) | Not yet wired — no upvote feature exists yet |
| Entity: dispute penalty (-10) | Not yet wired — dispute resolution UI doesn't exist yet (Phase 7) |
| User: confirmed accuracy report (+2) / malicious report (-15) | Not yet wired — report resolution UI doesn't exist yet (Phase 7) |
| Metadata: admin/owner edit (100.00, sets `is_locked=1`) | Not yet wired — business dashboard field-edit endpoint doesn't exist yet (Phase 4) |
| Metadata: community edit, weighted by submitter's trust score | Not yet wired — no community-edit endpoint exists yet |
| User: score below 0 → "flagged for review" | Implemented as a query predicate (`trust_score < 0`), not a stored flag or auto-suspension — no Phase 7 admin dashboard exists yet to consume a richer flag |

**Anti-gaming safeguard, not explicitly requested but necessary:** point awards at entity-creation time (baseline + geocode bonus) apply only once, at INSERT — never re-applied when the crawler re-ingests/patches the same record on a later run. Without this, repeatedly re-crawling the same listing would be a free way to inflate its score.

## 17. Web-Based Migration Runner: Bug Fixes & Consolidation Gaps

A parallel set of files (`api/db_config.php`, a stripped-down `migrate_listings.php`, `scripts/run_migration_web.php`) appeared outside the original build, created to run the migration over HTTP since CLI/SSH access wasn't confirmed available. Three real issues found and fixed, one flagged as unresolved:

1. **Security — real DB credentials were hardcoded in plaintext in `db_config.php` and shared in an uploaded file.** Treated as compromised; password rotation in BlueHost's MySQL panel recommended independent of any code fix. Moved credentials to `.env`, bridging both this file's original constant names (`DB_NAME`/`DB_USER`/`DB_PASS`) and the project's existing `.env` key names (`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`) so no immediate `.env` rewrite is forced.
2. **Root cause of the "headers already sent" warning — fixed.** `db_config.php` unconditionally called `header()`/`http_response_code()`/`exit` on connection failure, assuming it always ran first in a fresh AJAX/JSON response. Called from `run_migration_web.php` (which has already echoed a text banner by the time this runs), that's a guaranteed "headers already sent" warning. Fixed by removing those calls — a failed connection now just throws `PDOException`, same as PDO's default; the caller decides how to report it.
3. **A real, more serious bug caught, not just the header warning: the dry-run flag likely never worked over HTTP.** `run_migration_web.php` tried to make `--dry-run` reach `migrate_listings.php` by manually assigning `$_SERVER['argv']` before including the script, expecting `getopt()` to read it. `getopt()` reads the actual process-level command-line arguments the SAPI was invoked with — under a web server request there is no such thing, so this likely silently failed, meaning `?mode=live` and the default dry-run mode may have behaved identically. Fixed by having the web runner pass mode through an explicit `MIGRATION_DRY_RUN` constant defined before `include`, which `migrate_listings.php` checks first, falling back to real `getopt()`/argv parsing only for genuine CLI invocations (where it works correctly).
4. **`SQLSTATE[08004][1040] "Too many connections"` — infrastructure, not a code bug, and not fully resolved from this session.** This is BlueHost's MySQL account hitting its connection cap, not something fixable in application code. Diagnostic queries (`SHOW VARIABLES LIKE 'max_connections'`, `SHOW STATUS LIKE 'Threads_connected'`, `SHOW PROCESSLIST`) were provided to distinguish "stale connections need clearing" from "the plan's cap is genuinely too low for normal use," but the actual state of this hasn't been confirmed as of this entry.
5. **Added a basic access gate to `run_migration_web.php`** (shared-secret token via `.env`'s `MIGRATION_RUNNER_TOKEN`, compared with `hash_equals()`) — running a data migration off a public, unauthenticated URL was a real exposure that existed before this fix, independent of the bugs above. Explicitly recommended as a stopgap, not a permanent pattern — the runner script should be deleted from the server once the migration is complete rather than left reachable indefinitely.

**Not resolved — flagged, not silently accepted:** this project now has two separate DB connection mechanisms (`Database.php` and `db_config.php`), which will drift out of sync over time if left as-is. Not consolidated in this session since the exact deployed path of `Database.php` on the live server couldn't be verified from this environment.

**Follow-up, several sessions later: this exact gap caused a real production failure.** `Database.php` had never actually been uploaded to the live server at all — only `db_config.php` existed there. Since `api/bootstrap.php` (and by extension nearly every real endpoint — `Auth.php`, `dashboard.php`, `claim.php`, `vouch.php`, etc.) requires `Database.php` at the project root, this caused `dashboard.php` to fail outright. Re-delivered `Database.php` with explicit upload-path instructions. The standing recommendation to delete the migration-runner scripts (`db_config.php`, `run_migration_web.php`, `migrate_listings.php`, `generate_token.php`) once the migration is complete — which it is — now doubles as the actual fix for the two-connection-mechanism problem: once those are removed, `Database.php` is the only connection mechanism left in the project.

**Also built `forgot-password.php`** — a second real gap found alongside this: `reset-password.php` (the page after clicking the email link) already existed, but nothing actually triggered `api/auth/password/request-reset.php` in the first place. Built the missing "enter your email" landing page, using the same session.php-for-CSRF-token pattern as the other landing pages.

**Follow-up: a bigger structural gap surfaced right after this fix — there was no way to actually log in for real anywhere on the live site.** `index.php` is the original pre-Phase-2 file and still uses the old client-side `localStorage`-based fake login (the same mechanism `dashboard.php` used before it was rebuilt) — it was never updated to call the real `api/auth/login.php` endpoint. So a person "logging in" through `index.php` created a fake browser-only record the real backend never saw, no real session/cookie ever got set, and `dashboard.php` correctly redirected away every time (its auth check was working exactly as designed — the login it was checking for had simply never been real). Rather than rebuild `index.php`'s entire auth UI as an unblock-testing afterthought, built minimal, real `login.php` and `register.php` pages — same pattern as the other landing pages — so live testing can proceed immediately. **`index.php`'s login/registration UI still needs a proper rebuild against the real backend as its own piece of work** — these two pages are a testing bridge, not the final intended UI.

**Also caught from a live phpMyAdmin screenshot during this session:** `micro_clusters` showed 0 rows despite `hubs`/`geo_hubs` correctly showing their seeded row each — meaning the pilot cluster seed (Cluster 1A/1B) either failed silently or never ran. Re-running that specific `INSERT` directly was recommended before any migration proceeds, since without it, ZIPs from the legacy `listings` table would auto-seed placeholder clusters instead of correctly landing in the intended pilot clusters. **Follow-up confirmed this was resolved** — the pilot clusters were present on a later check.

**Migration compatibility issue discovered during the live production test run:** this account's MySQL/MariaDB version does not support the `ADD COLUMN IF NOT EXISTS` syntax at all — attempting it throws a `#1064` syntax error. This surfaced twice: once when hardening migrations 003/004 for idempotency after they'd already partially run, and it briefly produced a broken catch-up script before being caught. **Fixed across migrations 003, 004, and 006** by replacing `ADD COLUMN IF NOT EXISTS` with a stored-procedure-based existence check (`information_schema.columns` lookup, then a plain conditional `ALTER TABLE`) — this pattern works on effectively every MySQL/MariaDB version, unlike the newer syntax. `005`'s `CREATE TABLE` statements were also given `IF NOT EXISTS` (safe, standard, universally-supported syntax, unlike the column-level version). Added `004b_catchup.sql` as a standalone script for the specific gap this caused: `entities.verified_tier` and `entity_metadata.trust_score` had not yet been added in production when this was discovered, since those columns were added to migration 004 after an earlier version of that file had already been run.

**Added `scripts/generate_token.php`** — a standalone utility generating cryptographically secure random values for `MIGRATION_RUNNER_TOKEN` and `CRAWLER_API_TOKEN`. No DB connection or dependency on any other project file, so it can't fail for the same reasons the migration runner has been failing for. Same disposability pattern as the other one-off scripts here: delete it from the server once the values are copied into `.env`.

**A `.env` review during this same follow-up surfaced further real issues, not yet confirmed resolved:**
- An explicit instruction to set `DEFAULT_GEO_HUB_SLUG=cluster-1a-st-johns` was identified and rejected — that's a micro-cluster slug, not a geo-hub slug, and setting it would make the `SELECT id FROM geo_hubs WHERE slug = :slug` lookup in both `migrate_listings.php` and `api/ingest.php` fail immediately. The correct value (`ancient-borderlands`) was confirmed still present in the uploaded `.env` and should not be changed.
- `DB_DATABASE=traversence` / `DB_USERNAME=traversence_app` in the uploaded `.env` look like unreplaced placeholder values rather than the real BlueHost-provisioned names — all prior evidence from this account (phpMyAdmin's `degraff1_traversence` breadcrumb, the original `db_config.php`'s hardcoded `degraff1_TRAV` username) points to BlueHost's standard `degraff1_`-prefixed naming convention. Flagged for the user to confirm against BlueHost's MySQL Databases panel directly rather than assumed/corrected unilaterally.
- `MIGRATION_RUNNER_TOKEN` was entirely absent from the uploaded `.env`, which would make `run_migration_web.php` refuse to run at all.
- `.env`'s actual file path wasn't confirmed — `db_config.php` expects it at the project root (`.../public_html/traversence/.env`, one level above `api/`), not at `.../public_html/.env` as one instruction's wording implied.

**Reworked the authorization flow into a session-bound, click-through UX, replacing the static `.env`-token-in-URL approach.** `generate_token.php` now issues a per-session random value (stored server-side), and links directly into a dry run with that value embedded in the URL — no manual copy-paste into `.env` required for the migration flow specifically (`CRAWLER_API_TOKEN` still requires manual `.env` placement, since it's a persistent secret for a different, ongoing endpoint, not a one-off session flow). **Explicitly not "session presence alone" as the check** — that would be forgeable via CSRF (an external page linking to `?mode=live` would still carry an active session cookie on a top-level GET navigation even under `SameSite=Lax`, which permits exactly that). The random value doubles as a CSRF token: it must appear in both the session and the URL to authorize a request, so an attacker who can't see the actual token value can't construct a working link. Token expires after 1 hour. The static `.env` `MIGRATION_RUNNER_TOKEN` mechanism from the previous round is retained as a fallback authorization path, not removed.

Also added: a "Run Live Migration Now" button that only appears after a dry run completes successfully (detected from the script's own output), with a JS confirmation prompt before proceeding; one-time-use enforcement (the session authorization is consumed after a successful live run, requiring a fresh visit to `generate_token.php` for another run — the migration itself is idempotent via `composite_hash` matching, so this is hygiene, not a correctness requirement); and HTML-escaping of the migration script's captured output before display, since legacy business/address data could otherwise contain characters that break the page.

**Bug caught and fixed while wiring up the output capture:** `migrate_listings.php`'s error paths called `exit`/`exit(1)` directly. Since the web runner now captures its output via `ob_start()`/`ob_get_clean()` to safely HTML-escape it, a hard `exit()` would have bypassed that capture — PHP auto-flushes an open output buffer at shutdown, printing raw unescaped content outside the intended page structure and skipping the live-mode button logic entirely. Changed those paths to `return` instead, which works correctly whether the file is run via CLI or included from the web runner. Trade-off: a true CLI run now always exits with status 0 even on error — acceptable for a one-off migration tool, noted in-code for anyone who later wires this into an automated pipeline expecting a nonzero exit code.

## Resolution Log — Formerly Open Items (Sections 15–16)

1. **Legacy `pending` listings backlog — resolved.** No suppression: `pending` migrates identically to `active` — a normal, visible `unclaimed` entity with baseline trust_score. Unclaimed is already a normal, publicly-browsable state in this schema (Section 4), not a hidden one — the unclaimed/verification lifecycle itself is the moderation gate, not visibility-suppression. Only `rejected` remains suppressed, since it represents an actual past admin decision rather than "hadn't been reviewed yet." `CrawlerIngest::mapLegacyStatus()` and `migrate_listings.php`'s summary reporting both updated to match.
2. **`role` enum semantics — resolved.** The lean two-value enum stays as-is for organizational context; `is_admin` is what actually gates access, fully decoupled from `role`.
3. **`trust_score` earning mechanism — resolved.** See Section 16.

*(Section 17 above introduces two new, separate open items from a later session — the too-many-connections diagnosis and the `Database.php`/`db_config.php` consolidation — distinct from and unrelated to the items resolved here.)*

## 18. CSRF Enforcement — Pulled Forward from Phase 9

The migration-runner session-token pattern (Section 17) is fundamentally CSRF protection: a value that must match between the session and the request, so a request can't be forged by a page the user didn't actually intend to act on. `api/bootstrap.php` had already been issuing a `$_SESSION['csrf_token']` to every session since Phase 2, but nothing ever checked it — a gap originally flagged and deferred to Phase 9. Rather than wait, the same enforcement pattern used for the migration runner has been wired into the real auth endpoints now.

**Added `Response::requireCsrf()`** — compares the `X-CSRF-Token` request header against `$_SESSION['csrf_token']` via `hash_equals()`, returning a 403 on mismatch or absence. Called immediately after `Response::requireMethod('POST')` in every state-changing `api/auth/*` endpoint: `register.php`, `login.php`, `logout.php`, `verify-email.php`, `password/request-reset.php`, `password/reset.php`. `session.php` is unchanged (GET-only, read-only, no CSRF risk).

**Frontend integration requirement, not yet built anywhere:** whatever client consumes this API must call `GET /api/auth/session.php` first to read the current `csrf_token` (returned in its response body even for an unauthenticated/anonymous session), then send it back as the `X-CSRF-Token` header on every subsequent POST to these endpoints. No frontend exists yet that does this — noting it now so it isn't missed when one is built.

**Explicitly scoped — not a general "block bad actors" mechanism.** This protects specifically against forged cross-site requests riding on an active session; it does not provide bot detection, rate limiting/brute-force protection on login (still an open item, flagged in `login.php`'s own comments), or identity verification beyond what `email_confirmations` already does. Those remain separate, unimplemented concerns.

## 19. Cross-Border Cluster Data — Resolved via Part 3 (Continental Network) Blueprint

Following the migration (Section 17), a live phpMyAdmin check surfaced 43 auto-seeded `micro_clusters` beyond the two pilot clusters — one flagged as needing a decision: 14 of them fall in New Mexico ZIP-code ranges (roughly 870xx, the Gallup/Zuni corridor), all filed under the Ancient Borderlands geo-hub, which was scoped to Apache County/White Mountains, Arizona.

**Resolved by the source Part 3 blueprint document, not guessed:** Hub 9 (Ancient America)'s own stated jurisdiction is *"the Four Corners region spanning eastern Arizona, **western New Mexico**, southern Utah, and western Colorado."* This settles the real question — these New Mexico businesses are not out-of-scope data that leaked in; they're squarely within Hub 9's defined territory. Excluding them would have been wrong.

**How to handle them — also informed by the source document, not invented:** Part 3's "Border Community & Overlap Protocols" section addresses this exact scenario directly. Two explicit principles apply:
- *"Rather than fracturing the macro network with endless hub additions, overlapping geo-hub corridors act as tactical multi-directional bridges."* — the blueprint explicitly discourages creating a new dedicated geo-hub reactively for a small number of border-area businesses.
- *"Border and gateway outposts utilize automated metadata tags to pull into adjacent zone feeds... without duplicating accounts."* — the intended treatment is cross-referencing into the nearest existing geo-hub's feed, not a rigid single-home reassignment.

**Decision:** leave the 43 auto-seeded clusters (both the Arizona sub-region ones and the New Mexico border ones) as-is under Ancient Borderlands for now, rather than doing speculative reorganization at the current low row-count. This is consistent with the source document's explicit anti-fragmentation guidance. Revisit proper sub-grouping (and potentially a real geo-hub split for the Navajo Nation cluster noted separately, or a lightweight border-outpost tagging mechanism for the New Mexico cluster) once real business density in these areas justifies the effort — not preemptively.



1. **Hub count:** confirmed as 20 Continental Hubs (Ancient America explicitly referenced as "Hub 9 of 20" in the St. Johns worked example). The "14 Regional Hubs" reference in Part 4 is treated as a stale draft figure, not a second grouping.
2. **"Strategic Sub-Hub" taxonomy mapping:** confirmed to mean the **geo-hub** tier, not cluster.
3. **Map vs. homepage relationship (Section 12.5):** resolved — the map is the Directory/Cluster exploration interface, not the site's landing page.
4. **Admin Override mechanism (Section 12.2):** confirmed as a custom-built `/admin` panel within the application, not BlueHost's cPanel hosting interface.

## 20. Phase 4 Design: Claim, Verification Tiers & Account Security (Pre-Build)

Extensive design discussion resolved before any Phase 4 code was written. Captured here in full since it spans account security (applies platform-wide) as well as the claim/verification flow itself.

### 20.1 Account Security: Step-Up Authentication

A generalized pattern, not claim-specific — applies to claiming a listing, changing password, and changing email:

- **Claiming a listing:** requires a fresh OTP sent to the **claimant's own account email** (not any business email — there usually isn't one on file). This OTP **only reconfirms the account holder is real and reachable; it does not grant Verified status by itself.** Required **every time**, not just on a user's first-ever claim.
- **Password change:** requires re-entering the current password before setting a new one (standard practice, defends against a stolen/unattended session).
- **Email change:** three-step flow —
  1. Re-enter current password (defends against session/device theft without password knowledge).
  2. OTP sent to the **new** address (proves genuine ownership of the destination, not the origin — catches typos as a side benefit).
  3. On success, email updates immediately. **Old email gets a notification (not a blocking gate)**: plain-language "if this was you, no action needed," plus a 72-hour single-use revert link.
  4. **Clicking the revert link does three things together, not just the rollback:** (a) reverts `users.email`, (b) invalidates all active sessions for the account, (c) forces a password reset before the account can be used again. A revert-only response would let an attacker who still knows the password just repeat the change — the point of offering recovery at all is to actually recover the account, not just the field.
- **Schema:** new `user_otp_challenges` table (`user_id`, `purpose` enum `claim`/`password_change`/`email_change`, hashed code, expiry, attempt count, optional `context` e.g. entity_id being claimed) — generic across all three uses so the OTP logic exists in exactly one shared service, not duplicated per endpoint. The email-change revert token uses a new `email_confirmations.action_type` value, `email_revert` (kept distinct from the existing `ownership_change` value, which means something different — business-entity ownership disputes, Section 4b).

### 20.2 Verification Routes — Four Independent Paths, Tiered by Strength

All four **independently and directly** grant Verified status on their own — none of them are sequential gates or prerequisites for each other:

1. **Self-domain match** (Tier 1, weakest) — the only fully-automated, zero-human-judgment path. Deliberately scoped to domain-email matching only for this phase; domain-based OTP and document-upload self-verification are explicitly deferred (OTP needs a real SMS/telephony provider integration; document upload needs an admin reviewer, which doesn't exist until Phase 7).
2. **Referrals** (Tier 2) — claimant names up to 3 registered-user email addresses. Each named user must be a real, existing account (not the claimant's own) and must **actively click a confirmation link sent to their own on-file email** — a referral only counts once confirmed, not merely because a valid email was entered. Once 3 are confirmed, the entity is granted Verified **and simultaneously auto-flagged for retroactive admin review** (non-blocking — this doesn't delay verification, it's a standing audit signal). This is consistent with the source document's existing principle that admin oversight is "a post-verification governance layer rather than a system bottleneck," applied automatically for this specific, comparatively weaker-evidence route.
3. **Vouchers / Peer Validation** (Tier 3) — unchanged from earlier design: 3 confirmed `VERIFIED_BY` edges from entities that are themselves already Verified. No human review triggered — this remains the strongest fully-automated path since it requires real accountability from already-verified businesses.
4. **Admin override** (Tier 4, strongest) — direct manual decision, the apex authority for anything escalated, disputed, or ambiguous.

**Explicitly rejected reading, stated for the record:** an earlier draft of this design considered a single aggregate point total across all evidence types as the actual verification gate (e.g., partial referral + partial documentation stacking to cross one threshold). Rejected — it would have reversed an explicit principle from the same source blueprint: *"Crossing platform trust_score thresholds provides an independent signal for search ranking algorithms and automated moderation, operating without acting as a secondary gate to profile verification."* Each of the four routes above independently satisfies its own threshold; trust score reflects *which* route was used, it doesn't determine *whether* verification happened.

### 20.3 Tiered Trust-Score Bonuses

Base bonus scales with route strength (Tier 1 lowest, Tier 4 highest), plus separate stackable supplemental bonuses for additional evidence that don't change verified/unverified status by themselves:

| Tier | Route | Entity bonus | User bonus |
|---|---|---|---|
| 1 | Self-domain match | +20 | +10 |
| 2 | Referrals (3 confirmed) — auto-flags for admin retroactive review | +30 | +12 |
| 3 | Vouchers (3 confirmed) | +35 | +15 |
| 4 | Admin override (direct) | +50 | +25 |

**Supplemental, stacks on top of whichever base tier applied:**
- Business document on file: +10 entity / +5 user
- Each additional referral beyond the minimum 3 (capped at 3 extra): +2 entity each
- Each additional voucher beyond the minimum 3 (same cap): +2 entity each

This replaces the earlier flat +50 entity / +25 user bonus (Section 16's table, "Entity: verified claim (+50, sets status directly)") — that flat figure is now specifically the Tier 4 (admin) value, not a uniform constant across all routes. `TrustScore.php`'s constants need updating accordingly when Phase 4 is actually built (not yet implemented as of this entry — this section is pre-build design).

### 20.4 Referral Mechanism — Schema

New `entity_referrals` table: `entity_id`, `referrer_user_id`, `confirmation_token`, `confirmation_expires_at`, `confirmed_at`, `created_at`. Kept separate from `email_confirmations` since that table's shape (tied to a user, no entity context) doesn't fit this flow. Acknowledged residual risk, accepted as tolerable: requiring referrers to be real registered accounts with an active confirm click raises the bar over arbitrary free-text names, but doesn't fully prevent someone using multiple sockpuppet accounts to confirm each other — mitigated by the fact that referrals only ever escalate to a human decision, never grant Verified without also triggering the automatic admin review flag.

### 20.5 Foundation Built

- **`schema/migrations/005_phase4_claims.sql`** — `user_otp_challenges` (generic across claim/password/email-change), `entity_referrals`, and an `email_confirmations.action_type` enum extension adding `email_revert`.
- **`api/lib/TrustScore.php` updated** — the flat +50/+25 verification bonus replaced with four tiered constants each (`ENTITY_VERIFIED_TIER1_SELF_DOMAIN` through `TIER4_ADMIN`, plus matching `USER_VERIFIED_TIER*` constants) and two supplemental constants (document-on-file, extra referral/voucher beyond the minimum 3). Added `TrustScore::awardVerificationTier()` — a single entry point the eventual claim/verification endpoint calls with a tier number, applying the correct entity + user bonus plus any supplements in one place. Deliberately does not set `entities.status` itself — that stays the calling verification logic's responsibility, keeping trust_score strictly a signal, never the mechanism that determines verified/unverified.
- **`api/lib/StepUpAuth.php`** — new shared OTP service: `issue()`/`verify()`, single-use challenges, 10-minute expiry, capped incorrect-attempt count. One class handles all three purposes (claim, password_change, email_change) rather than three separate implementations.

**Not yet built:** `api/claim.php`, the referral submission/confirmation endpoints, `api/auth/change-password.php`, `api/auth/change-email.php` (+ its revert-link handler), the actual `Verification.php` orchestration logic tying the four tiers together, and the merchant dashboard page (confirmed to be a real page, replacing `dashboard.php`'s localStorage fake auth, not API-only).

### 20.6 Full Build Completed

- **`schema/migrations/006_session_security.sql`** — adds `users.session_version` (the "security stamp" pattern: bumping it invalidates every other active session for that user without needing to track individual session IDs — required to make the email-revert flow's "invalidate all sessions" claim actually true, not just described) and `email_confirmations.metadata` (generic JSON payload, used by the revert flow to store which address to revert to).
- **`api/lib/Auth.php` updated** — `attemptLogin()`/`currentUser()` now check `session_version` on every request; new `Auth::bumpSessionVersion()` and `Auth::changeCurrentPassword()` (re-verifies current password, bumps session_version, refreshes the *current* session so the user isn't logged out of the device they just used). `resetPassword()`'s forgot-password flow also now genuinely bumps session_version — closing a gap its own code comment had flagged since Phase 2.
- **`api/lib/Verification.php`** — orchestrates all four tiers. `trySelfDomainMatch()` (Tier 1), `checkReferralThreshold()`/`checkVoucherThreshold()` (Tiers 2/3, called after a referral confirms or a vouch is recorded), `applyAdminVerification()` (Tier 4, ready for Phase 7's admin panel to call). Each independently sets `status = 'verified'` and calls `TrustScore::awardVerificationTier()` — never a shared point-threshold, per Section 20.2's explicit rejection of that reading.
- **`api/claim.php`** — two-step (`action: start` issues OTP to the claimant's account email; `action: confirm` verifies the code, then sets `owner_user_id`/`status` inside a row-locked transaction to prevent a race with a concurrent claim attempt). Immediately attempts Tier 1 self-domain match on success — free to check, fires automatically wherever the data already supports it.
- **`api/referral/request.php` / `api/referral/confirm.php`** — claimant names up to 3 registered-user emails; each referral only counts once the named user actively confirms via their own token link. Hitting 3 confirmed referrals calls `Verification::checkReferralThreshold()`, which grants Verified **and** logs an `admin_override`/`pending` row in `verification_attempts` as the auto-flag for retroactive review — reuses the existing admin-queue mechanism rather than adding a new column.
- **`api/vouch.php`** — Tier 3. Requires the voucher to be owned by the requesting user and already `verified` before it can vouch for another entity; creates the `VERIFIED_BY` connection edge, then checks the 3-voucher threshold.
- **`api/auth/change-password.php`** — re-verify current password, set new one, session_version bump preserves the current session while invalidating others.
- **`api/auth/email/request-change.php`, `confirm-change.php`, `revert.php`** — the full three-step flow from Section 20.1: password re-confirmation → OTP to the *new* address → update + dual notification. The revert handler does all three recovery actions together (roll back the email, invalidate every session via `bumpSessionVersion`, and direct the user to the existing forgot-password flow) rather than just the field rollback — a revert-only response would let an attacker who still knows the password simply repeat the change.
- **`api/business/my-listings.php`** — new endpoint (didn't exist before, needed by the dashboard): lists entities owned by the current user, with live voucher/referral progress counts attached for anything still `claimed-unverified`.
- **`api/business/update-metadata.php`** — owner edits to non-critical fields only (hours, phone, address, city, state, description — explicitly not `website`, since that field is load-bearing for Tier 1 self-domain matching, and not payment-related fields). Every owner edit sets `is_locked = 1`, which is what makes the field-locking design from Section 12.1 actually enforceable against future crawler patches.
- **`dashboard.php` rebuilt** — the real merchant dashboard, replacing the old localStorage-based fake auth entirely. Server-side redirect via `Auth::currentUser()` if not authenticated (no client-side flash of dashboard content before discovering the user isn't logged in); lists owned listings with status badges and live vouch/referral progress; matches Traversence's established design system (dark brown header, parchment background, card browns, amber accents).

**Still not built, flagged rather than silently assumed complete:** email delivery for any of the new OTP/notification/revert-link flows — **resolved, see the milestone note above: outgoing email delivery is now confirmed live**; Phase 7's actual admin panel, so the `pending` rows Tier 2 logs into `verification_attempts` have nowhere to be reviewed yet; document-upload and domain-OTP as additional self-verification methods (Tier 1 currently supports domain-email match only, per the earlier build-sequencing decision).

### 20.7 Email Delivery — Authenticated SMTP via BlueHost Mailbox

**Decision: BlueHost's own mail hosting, explicitly as a temporary bridge, not a permanent choice.** BlueHost's own documentation (provided by the user) explicitly warns against using its built-in email for transactional system mail — which is exactly what every flow in this build needs (OTP codes, verification links, password resets). Accepted anyway as a deliberate, pragmatic near-term bridge given zero setup cost, consistent with this build's established pattern of shipping the minimal viable version first (domain-match-only Tier 1, deferred OTP/document-upload, etc.) — with an explicit intent to migrate to a real transactional provider (Postmark, SendGrid, Mailgun) as a near-term follow-up, not "only if it becomes a problem."

**Chose authenticated SMTP via a real mailbox over PHP's `mail()`.** `mail()` is simpler but doesn't reliably pass SPF/DKIM authentication tied to the actual domain — a meaningful gap specifically because several flows here carry 10-minute-expiry OTP codes, where landing in spam and being seen late is equivalent to the feature being broken, not just an inconvenience.

**Built `api/lib/Mailer.php`** — a minimal hand-rolled SMTP client (no PHPMailer/Composer dependency, matching the project's established "no external libraries" pattern from `Database.php`'s hand-rolled `.env` parser). Talks raw SMTP over a socket: EHLO → STARTTLS → EHLO again → AUTH LOGIN → MAIL FROM/RCPT TO/DATA → QUIT. Handles multi-line SMTP responses and dot-stanza escaping correctly. Configuration via `.env`: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`, plus a new `APP_URL` key used to build absolute links in outgoing emails.

**Wired into every previously-marked integration point:** `register.php` (verification link), `password/request-reset.php` (reset link), `claim.php` (OTP code — a failed send here surfaces as an error to the user, since the flow genuinely cannot proceed without it, unlike registration/reset where the account/request already exists regardless), `email/request-change.php` (OTP to the new address), `email/confirm-change.php` (dual notification — plain confirmation to the new address, FYI-plus-revert-link to the old one), `referral/request.php` (referral invite link).

**New scope this surfaced, not part of the original request but necessary to avoid shipping a broken feature:** three of these emails needed to contain a clickable link, but the corresponding endpoints only existed as JSON POST APIs with no page for a browser click to land on. Built four minimal landing pages — `verify-email.php`, `reset-password.php`, `confirm-referral.php`, `email-revert.php` — each reading a token from the URL and calling the existing API via `fetch()`, styled consistently with the established brand. Distinguished from the claim-button/public-directory UI, which remains explicitly deferred: these four pages are the minimum needed to make already-built backend flows actually completable by a real user, not new user-facing product surface.

**Bug found and fixed against the real BlueHost mail panel settings:** the initial `Mailer.php` connected via a plain `tcp://` socket for every port, only skipping the `STARTTLS` *command* for port 465. That's wrong — port 465 is implicit SSL/TLS, meaning the encrypted handshake must happen the instant the socket opens, before any SMTP commands are exchanged at all; a real port-465 server never sends a plaintext greeting to negotiate with. Fixed by connecting via `ssl://` specifically when the configured port is 465, leaving the plaintext-connect-then-`STARTTLS`-upgrade path (port 587 and similar) unchanged. `.env.example`'s SMTP defaults updated to match this account's actual BlueHost panel values (`mail.traversence.com`, port 465, `donotreply@traversence.com`) rather than the generic port-587 guess used initially.

**Outgoing send confirmed live in production** — the first real email sent via this SMTP configuration was confirmed delivered, closing out the last open item from the Phase 4 milestone (see the Development Status checklist and the Milestone note above).

### 20.8 Push Notifications — Considered, Deferred (Not Built)

Discussed as a future alternative/complement to email OTP, not committed scope. Key distinctions worth preserving for when this is revisited:

- **Push requires a prior device-registration step that has no email-OTP equivalent** — a native app install or granted browser notification permission must exist before any challenge can be sent. Email OTP works the instant an address is on file; push doesn't have an equivalent zero-setup fallback.
- **Genuine security upside if built well:** a device-bound "approve/deny this login" push challenge is meaningfully more phishing-resistant than a typed code, since there's no code to intercept and no phishing page that can trick someone into entering it in the wrong place.
- **Cost reality, given the stated shoestring budget:** a native iOS/Android app is real ongoing cost (Apple developer program alone is $99/year, plus two separate codebases to build and maintain) — not comparable in scope to anything else built in this project so far. **Web Push (browser-based, via a service worker) is essentially free** by contrast — no app store fees, no native app — and would be the more realistic middle-ground option if this is revisited before a native app ever makes sense. Its limitation is reach (only works for users with notification permission granted and an active browser), not cost.
- **Decision: email OTP remains the sole mechanism for now.** No push infrastructure — native or Web Push — is in scope until budget/priorities change.
- **Flagged as a genuinely viable future direction, not just security.** Beyond step-up authentication, Web Push is a real candidate channel for general platform communication once budget allows — verification status changes, referral/vouch confirmations, claim-related updates, and similar notifications that currently only exist as emails could reasonably move to (or be duplicated via) Web Push, given it's the lower-cost path of the two push options and doesn't require anything beyond the existing web app.

### 20.9 Tier 1 Redesigned — Self-Confirm OTP Replaces Domain Matching

**Domain-based self-verification (both the original email-domain-match design and a subsequent domain-ownership-challenge redesign) has been fully dropped.** Both were explicitly identified as unnecessary complexity ("chasing our tail") given the platform's actual user base — many businesses will use Traversence's own hosted profile as their web presence rather than maintaining a separate site (per the subscription tier framing), and requiring domain literacy or restricting `website` to admin-only editing conflicted with the platform's actual posture of giving businesses free access to their own data.

**Tier 1 is now: a dedicated, owner-triggered "click here to verify" OTP challenge, separate from the claim OTP.** This is a real, deliberate distinction, not a workaround of the earlier rule that claim-OTP reconfirmation doesn't verify by itself (Section 20.1) — claiming and this action are two different, separately-invoked events, even though both ultimately reconfirm the same account identity. That's exactly why Tier 1 remains the lowest-confidence, lowest-bonus tier: it proves the account holder is real and reachable, not that they're connected to the actual business. Implemented as `api/verify/tier1.php` (two-step, same start/confirm pattern as `claim.php`), a new `tier1_verify` OTP purpose in `StepUpAuth`, and `Verification::applyTier1SelfConfirm()` (replacing the deleted `trySelfDomainMatch()`). `website` reverts to ordinary owner-editable data with no special restriction, since nothing depends on trusting its accuracy for Tier 1 anymore.

**New guardrail this made necessary: voucher eligibility now requires Tier 2+.** Since Tier 1 is now trivially cheap for any real account to obtain on any unclaimed listing, allowing a Tier-1-only entity to also *vouch* for others would let that cheap tier launder itself into helping other businesses reach Tier 3, undermining what Peer Validation is supposed to mean. Added `entities.verified_tier` (new column, migration 004) — recorded whenever any tier grants verified status — and `api/vouch.php` now explicitly rejects vouches from an entity verified at Tier 1 only. Constants renamed accordingly (`TIER1_SELF_DOMAIN` → `TIER1_SELF_CONFIRM`) to stay accurate to what the tier actually checks.

### 20.10 White-Glove Delegate Access & Related Verification Tooling — Designed, Not Yet Built

Extensive design discussion, captured here since none of it is implemented yet:

- **Delegate access model confirmed as "Option A, properly scoped"**: white-glove staff (including the admin/regional-rep role) act as an **authorized agent** with real, grantable permissions — not by sharing credentials or operating the owner's session directly. This is genuinely Option B's architecture (a real authorization layer between ownership and action), just with actions attributed to the delegate's own identity rather than impersonating the owner — consistent with the existing `created_by_type`/`source_type` provenance pattern already used everywhere else in this build.
- **Hard line: verification itself is never a delegable permission.** `is_admin` status remains the sole gate for Tier 4, independent of any delegate grant — this also avoids a structural conflict of interest where the same person could be both the commercial white-glove helper and the sole judge of that business's trustworthiness.
- **Refined further: delegation covers *gathering evidence*, not *bypassing it*.** A delegate can submit referral requests and initiate a document upload on a business's behalf — but a referral still requires the actual named person to click their own confirmation, and a document still requires a genuine admin decision to accept. Vouchers are explicitly **not** delegate-submittable under any circumstance — a vouch has to be a real third-party business's own choice; there's no proxy version of that action.
- **Not yet built:** `business_delegates` table (entity, delegate user, granting user, status, permission set), the request/grant/revoke UI, a `delegate` provenance value, document upload as an actual subsystem (private off-web-root storage, file type/size limits, admin review queue), and admin-facing tooling to input/review this evidence.
- **Voucher-request (invitation) system — designed, not built.** Lets a claimant (or delegate) invite a *specific*, named already-verified business to consider vouching, without compelling the outcome — an invitation, not a mechanism that creates the vouch itself. Proposed `vouch_requests` table (`entity_id`, `requested_by_user_id`, `target_entity_id`, `status: pending/vouched/declined/expired`), structurally similar to `entity_referrals`.
- **A small admin verification tool — designed, not built.** Scoped deliberately narrower than the full Phase 7 admin panel: search/create unclaimed listings, a pending-review queue (which would also surface Tier 2's auto-flagged retroactive-review entities for free, since they already log into `verification_attempts`), and a "Verify" action calling the already-built `Verification::applyAdminVerification()`. Listing creation reuses `CrawlerIngest::upsertEntity()` with `created_by_type = 'admin'` instead of a new code path.

### 20.11 Master Punch List Adopted; Dashboard Split Confirmed; Cluster Cleanup Queued

A comprehensive, well-organized full-platform punch list was provided and adopted as the master reference for tracking build status across every area (public frontend, auth, user dashboard, business portal, admin, CMS, API layer, deferred items) — more complete than the scattered per-section tracking used previously. One inaccuracy in it was caught and corrected before adoption: it marked `api/auth/login.php` as already routing to a `user/dashboard.php` split-dashboard destination — that page doesn't exist, and the split itself was only a proposal embedded in the list, not yet a decision. It has now been decided (see below).

**Decided: split the personal user dashboard from the business listing portal.** A future personal account dashboard (default post-login landing page — profile, saved favorites, personal messages, referral tracker) is distinct from business/listing management, which is reached *from* the personal dashboard via a nav tile rather than landed on directly. The personal dashboard is **not yet built**.

**Renamed in preparation:** ~~`dashboard.php` → `business-portal.php` (flat file at the project root...)~~ — **superseded, see below.**

**Final decision: real folder structure, mirroring the `api/` tree exactly.** `business-portal.php` was a flat-file intermediate step, immediately reversed — the platform now uses **`business/dashboard.php`**, sitting alongside `api/business/*`, with a future personal dashboard becoming `user/dashboard.php` alongside the planned `api/user/*`. Same filename, disambiguated by folder, matching the punch list's own naming and giving genuine domain separation at the page layer too, not just the API layer.

**Two things had to change for this move, and now apply platform-wide, not just to this one file:**
1. **PHP `require_once` paths** — `business/dashboard.php`'s reference to `api/bootstrap.php` became `../api/bootstrap.php`.
2. **Every JavaScript `fetch()` call, across every page, converted to absolute paths** (`/api/auth/session.php`, leading slash) instead of relative ones (`api/auth/session.php`). A relative path resolves against the *page's own URL* — from inside `/business/dashboard.php`, `fetch('api/...')` would have silently requested `/business/api/...`, which doesn't exist. Converted proactively across **every** page (`login.php`, `register.php`, `forgot-password.php`, `reset-password.php`, `verify-email.php`, `confirm-referral.php`, `email-revert.php`), not just the one that moved — this makes every page's folder depth irrelevant to its own API calls going forward, so a future move can't silently break this again. Caught one real bug while doing this: an initial pass used `sed` on `fetch('api/` and missed `business/dashboard.php`'s actual call sites, which are wrapped in a local `apiFetch()` helper (`apiFetch('api/...')`, capital F) — fixed once the mismatch was caught by checking the actual call sites rather than trusting the first pass.

The old `dashboard.php` path is kept as a **thin redirect stub** to `/business/dashboard.php` (absolute path), not deleted outright, since the not-yet-rebuilt `index.php` still has hardcoded links to `/dashboard.php` — remove the stub once those links are updated directly. `login.php`'s post-login redirect now points to `/business/dashboard.php`; **this will need to move again** once the actual personal dashboard exists, since `business/dashboard.php` should then be reached via a nav tile from that page, not landed on directly after login.

**Zip-code-to-cluster cleanup (Section 19's original finding) — explicitly queued, not addressed now.** Confirmed as real, worthwhile cleanup work (19 clusters genuinely White Mountains/Apache County, 10 Navajo Nation communities warranting their own sub-region, 14 New Mexico border-outposts in-scope per Hub 9's own jurisdiction but not accurately labeled) — but deliberately deferred until the admin verification tool (Section 20.10) is built, since cluster rename/merge/reassign is confirmed as a feature that belongs inside that same tool rather than a one-off raw-SQL pass. No action needed on this until that tool exists.

### 20.12 Personal Dashboard Built; `index.php` Aligned to Real Auth (Scoped Patch, Not a Rebuild)

**Note: file paths referenced in this section (`api/user/referrals.php`, `business/dashboard.php`, `/business/dashboard.php` links) were superseded by the full domain reorganization in Section 20.13 immediately below — kept as an accurate historical record of what was built at the time, not the current file layout.**

**Explicitly scoped:** this is a targeted alignment patch to `index.php`, not the full magazine-homepage rebuild (Section 3) — that remains separate, larger, CMS-dependent work.

**Built `user/dashboard.php`** — the real personal account dashboard, matching the confirmed personal/business split. Includes: profile summary (email, display name, member-since date), a nav tile into `/business/dashboard.php`, a referral inbox (see new endpoint below), and — genuinely new, not previously built anywhere — working UI for two endpoints that had backend logic but no page: **change-password** and the two-step **change-email** flow. Honest "Coming Soon" placeholders for favorites/community/messages rather than fake functional UI, consistent with the rest of this build's approach to unbuilt features.

**Built `api/user/referrals.php`** — new endpoint; nothing previously let a user see referral activity without digging through email. Returns three views: referrals naming this user as referrer awaiting confirmation (with the token, linking straight into the existing `confirm-referral.php` flow rather than duplicating confirmation logic), referrals already confirmed, and referrals this user has sent for their own listings.

**Real bug caught and fixed while building `user/dashboard.php`: the same relative-path issue from the `business/dashboard.php` move also applies to plain HTML `href`/`src` attributes, not just `fetch()` calls.** `business/dashboard.php`'s header still had `href="index.php"` and `src="image/Logo-Gtext.png"` — relative to the page's own URL, which from inside `/business/` would resolve to `/business/index.php` and `/business/image/...`, neither of which exist. Fixed to absolute paths. Worth remembering for any future page move: check both JS fetch calls *and* HTML attributes, not just one or the other.

**`index.php` patch — separated two previously-conflated auth concepts rather than just fixing a broken link.** The existing "Owner Dashboard" nav link was gated by the old client-side, password-less "quick local profile" (`isSignedIn()`/`localStorage`) — a system that exists purely to remember search-location preferences, entirely disconnected from real accounts. Showing a dashboard link there was actively misleading: a visitor could set a quick profile (email only, no password) purely for location memory, see what looks like an account menu, and get redirected away on click since no real session ever existed. **Resolution:** stripped the dashboard link from that block entirely (it now just shows the quick-profile email and a sign-out control, an honest representation of what it actually is), and added a **new, genuinely separate nav element** (`#real-auth-guest` / `#real-auth-user`) driven by an actual `api/auth/session.php` check — showing Sign In/Register when there's no real session, or a "My Account" link to `/user/dashboard.php` when there is. Both default to the signed-out state on any fetch failure, so a broken API call can never silently imply a real login that was never verified. The old quick-profile system itself was left completely untouched — not ripped out, not renamed in its own logic — since it's working, unrelated functionality outside this patch's scope.

### 20.13 Full Domain Reorganization — `listing/` and `user/` Folders, Including the API Layer

**Follow-up: full domain-based reorganization, one pass, not piecemeal.** Given two prior moves had each surfaced a broken-link bug, the naming was locked down explicitly before touching anything a third time:

- **`listing/businessportal.php`** (not `business/`) — final naming confirmed.
- **Real folder-per-domain structure adopted for the API layer too**, mirroring the page structure: `listing/api/*` and `user/api/*` for domain-specific endpoints, with `api/` (global) reserved for anything platform-level regardless of domain — `bootstrap.php`, `lib/*`, `middleware/*`, and `api/auth/*` (confirmed to stay global: authentication applies to every account regardless of whether they ever touch a listing or personal dashboard).
- **`api/ingest.php` and `CrawlerIngest.php` stay in global `api/`, reversing an earlier proposal to move them to `listing/api/`.** The organizing principle clarified during this pass — domain `api/` folders support *UX interactions*, and nothing under any `api/` tree ever renders a page (`Response::success()`/`error()` always return JSON) — makes clear the crawler ingestion endpoint doesn't belong there: it's a machine-to-machine, bearer-token pipeline with no page or logged-in user ever triggering it, which reads as platform infrastructure, not listing-domain UX.

**Full moves executed in this pass:**
- `api/claim.php`, `api/vouch.php`, `api/verify/tier1.php`, `api/referral/request.php`, `api/referral/confirm.php` → `listing/api/*` (same filenames, folder depth-corrected require paths)
- `api/business/my-listings.php`, `api/business/update-metadata.php` → `listing/api/my-listings.php`, `listing/api/update-metadata.php` (flattened — the `business/` subfolder inside `api/` was redundant once everything lives under `listing/api/` already)
- `api/user/referrals.php` → `user/api/referrals.php`
- `business/dashboard.php` → `listing/businessportal.php`
- `confirm-referral.php` → `listing/confirm-referral.php`

**Cross-references fixed across every affected file:** the referral-invite email link (`listing/api/referral/request.php`), `user/dashboard.php`'s business-portal tile/referral-fetch/confirm-referral links, and the root `dashboard.php` stub (now pointing to `/listing/businessportal.php`).

**`login.php`'s redirect finally moved to `/user/dashboard.php`**, fulfilling a TODO left open since the personal/business split was first decided ("this will need to move again once the actual personal dashboard exists") — the business listing portal is now reached via a nav tile from the personal dashboard, not landed on directly after login, matching the original intended flow.

### 20.14 Real Bug Found via Live Testing: `api/auth/session.php` Withheld the CSRF Token from Unauthenticated Requests

Live testing produced "Invalid or missing CSRF token" on login. Diagnosed methodically rather than guessed at: a standalone `session-debug.php` diagnostic confirmed PHP session persistence itself was completely healthy (stable session ID across reloads, cookie round-tripping correctly, writable session storage) — ruling out the initial hypothesis (session/infrastructure failure) and pointing at the application code instead.

**Actual bug: `api/auth/session.php` only included `csrf_token` in its response when the caller was already authenticated.** `login.php` (and identically, `register.php` and `forgot-password.php`) call this exact endpoint specifically to fetch a token *before* being authenticated — that's the entire point, since a CSRF token is needed to submit the very first login/register/reset-request action. The unauthenticated branch returned `{authenticated: false, user: null}` with no `csrf_token` field at all, so the client's `csrfToken` variable was `undefined`, sent as the literal string `"undefined"` in the `X-CSRF-Token` header, which obviously never matched the real session value.

**Fixed:** both branches of `session.php` now return `csrf_token`, since `bootstrap.php` already guarantees it exists for every session, authenticated or not — the endpoint was simply withholding data it already had. One fix in one file resolves the identical failure across all three pre-auth pages (`login.php`, `register.php`, `forgot-password.php`) that share this pattern, not just login specifically.

### 20.15 Real Bug Found via Live Testing: `Database.php`'s `.env` Path Was Wrong at Its Actual Deployment Location

Next live-test failure after the CSRF fix: "Database configuration missing," despite `.env` being completely correctly filled in (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, all present and correct).

**Actual bug:** `Database.php`'s `.env` loader used `dirname(__DIR__) . '/.env'` — "look one directory above wherever this file lives." That logic was written under the assumption `Database.php` would be nested inside a subfolder, mirroring `api/db_config.php`'s placement. It was later explicitly confirmed and repeatedly deployed at **the project root itself**, alongside `.env` directly — meaning `dirname(__DIR__)` was silently looking *one directory above the actual project root*, somewhere `.env` could never exist, and giving up without any indication why. The `.env` file itself was never the problem.

**Fixed:** changed to `__DIR__ . '/.env'` — this file's own directory, matching its actual, confirmed deployment location. Updated the class's doc-comment to match reality rather than describing an assumed layout that was superseded turns ago.

**Follow-up, immediately after: reversed to the opposite placement instead of fixing the path in place.** Rather than keep `Database.php` at the project root (the fix above), the decision was made to move it *into* `api/`, alongside `bootstrap.php`, `db_config.php`, and everything else backend-related — keeping the project root reserved for pages only. This makes `dirname(__DIR__)` the *correct* logic again (one directory above `api/` is the project root, where `.env` lives) — reverted to that, updated `api/bootstrap.php`'s require path from `../Database.php` to `Database.php` (same directory now), and confirmed no other file referenced the old path (`scripts/migrate_listings.php` uses the separate `db_config.php` mechanism entirely, unaffected either way). Net effect: `Database.php` now lives at `api/Database.php`, and the `.env`-lookup logic correctly matches wherever it actually lives, rather than chasing the file's location with contradictory fixes.

### 20.16 Real Bug Found via Live Testing: HTML Pages Requiring `api/bootstrap.php` Rendered as Plain Text

Next live-test failure: `user/dashboard.php` displayed its own raw HTML source as visible text in the browser instead of rendering. PHP itself was confirmed working correctly (the logged-in user's real email was interpolated into the output) — this was a response-header problem, not a logic or session bug.

**Actual bug:** `api/bootstrap.php` unconditionally sends `Content-Type: application/json` — correct for every real API endpoint, but `user/dashboard.php` and `listing/businessportal.php` also `require` it directly (to call `Auth::currentUser()` for their server-side login check), and both output full HTML pages afterward. The browser received genuine HTML content labeled as JSON and displayed it as plain text rather than rendering it, exactly matching the symptom.

**Fixed:** both files now explicitly override the header back to `Content-Type: text/html; charset=utf-8` immediately after requiring `bootstrap.php`, before any output is sent. Found and fixed in both files at once — `listing/businessportal.php` had the identical latent bug and would have failed the same way the moment it was tested next.

### 20.17 Password Change UX Improvements — Show/Hide Toggles, Confirm Field

Real change-password functionality confirmed working on first live test (correct success message, session preserved on the current device, other sessions invalidated — matching the design from Section 20.1 exactly). Two UX gaps identified from that same test and fixed:

- **Show/hide visibility toggle** added to every password input on `user/dashboard.php` — current password and new password on the change-password form, current password on the change-email form. Simple type-toggle between `password`/`text`, no new backend involved.
- **"Confirm New Password" field added** to the change-password form, with client-side match validation *before* the API is ever called — catches the most common real mistake (a typo in the new password) without a round trip, and without the server needing to know about or store a "confirm" value it was never asked for.

**Real gap found immediately after: password change had no email notification at all.** Reported as "no email received" — checked and confirmed this wasn't a delivery failure, `api/auth/change-password.php` simply never had a `Mailer::send()` call built into it. A real, notable inconsistency: email-change already sends a security notice on change, but password-change — arguably the *more* security-sensitive of the two — silently didn't. Fixed by adding a notification email, following the same pattern used elsewhere: "if this was you, no action needed" reassurance, plus a direct pointer to the existing forgot-password flow if it wasn't. No revert-link mechanism here, unlike email-change — there's no prior password value to roll back to, so the correct recovery path is simply resetting the password again via the flow already built.

### 20.18 Standard Password Complexity Policy; CSRF Bug Found in `reset-password.php`

**Password policy upgraded platform-wide, server-side first.** Added `Auth::assertPasswordMeetsRequirements()` — a single shared validator replacing three separate, duplicated `strlen() < 10` checks (registration, password-change, password-reset) — enforcing: minimum 8 characters, at least one uppercase letter, one lowercase letter, one digit, and one special character. "Special character" is deliberately *any* non-alphanumeric character via regex, not a fixed whitelist — the examples given (`@ $ ! % * ? &`) are illustrative, not exhaustive, so a valid but unlisted symbol still passes. Explicitly framed as a server-side policy first: client-side checklists are UX feedback only, never a security boundary, so the server independently re-validates everything regardless of what a client claims to have already checked.

**Same live checklist UI (5 requirements, real-time checkmarks) added consistently across every page that sets a password** — `register.php`, `reset-password.php`, and `user/dashboard.php`'s change-password form — plus a "Confirm Password" field with client-side match validation on all three, and show/hide visibility toggles on every password input across all three pages. Deliberately duplicated identically in each file rather than shared via a JS asset (no build/bundling pipeline exists in this project) — kept byte-for-byte consistent so behavior can't silently drift between pages.

**Real bug found while testing this end-to-end (via the actual "this wasn't me" → forgot-password → reset-password flow): `reset-password.php` never fetched or sent a CSRF token at all.** `api/auth/password/reset.php` has required one since Section 18's CSRF enforcement pass — but `reset-password.php`'s own submit handler was never updated to actually fetch and attach one, unlike every other page built after that point. This bug existed from the moment CSRF enforcement was added and simply never got exercised until this test. Fixed using the same `session.php`-for-token pattern as every other page.

**Also fixed: the Change Email section had no field labels and no password visibility toggle**, reported alongside the password-policy request. Added `<label>` elements to every field in both the request and confirm forms (current password, new email, verification code), and the same show/hide toggle pattern for its password field, matching the treatment now applied consistently across the whole account-security section.

### 20.19 Email-Revert Hardened: Confirm-Email Required, Not Just a Bare Click

Raised after a fully successful end-to-end test of the revert flow: a single click, with no visible confirmation of what was about to happen, felt thin for an action this consequential (email rollback + force-logout-everywhere + mandatory password reset). Worth being precise about what changed and what didn't — the link itself was always the primary secret (only reachable via the old inbox, same pattern as every other email confirmation in this build); this isn't a new cryptographic weakness being patched, but a real gap in requiring *deliberate, verified* action beyond mere possession of the link.

**Added a required second factor: the caller must now correctly type the account's original email address, verified server-side, before the revert executes.** `api/auth/email/revert.php` now requires `confirm_email` alongside `token`, compares it (case-insensitive) against the stored `revert_to_email` via `hash_equals()`, and rejects on mismatch **without revealing the correct address** in the error — so this can't be trivially defeated by trial-and-error against someone who has the link but doesn't actually know the account's real email. `email-revert.php` updated to collect this input; deliberately does *not* pre-fill or display the actual old email on the page itself, since that would be unnecessary additional exposure if the link were ever forwarded or leaked to a third party — the legitimate account holder already knows their own email and doesn't need it shown back to them.

### 20.20 Home-Location Write Side — `user/api/set-home-location.php`

**Trigger:** first live test of the Section 20.19-adjacent home-location read-side fix showed the modal still firing after login. Root cause traced to real data, not a logic bug: `degraff1_traversence.sql` confirmed the only real `users` row has `home_cluster_id = NULL` — nothing in the platform has ever written that column. Section 23 (Town Hall) was the only documented future consumer/setter, and it isn't built either.

**Fix:** rather than build new UI, reused the existing "Set Precise Location" modal — it already captures a zip on every path (manual entry, auto-detect's reverse-geocode, hub-pill `switchRegion`). New endpoint **`user/api/set-home-location.php`** (real-auth-only, POST, CSRF-protected via the existing `Response::requireCsrf()`) resolves a submitted zip against `micro_clusters` (`primary_zip` OR `JSON_CONTAINS(associated_zips, ...)` — `primary_zip` alone misses zips like Round Valley's `85925`, which only appears in `associated_zips`) and updates `users.home_cluster_id`. A zip that doesn't resolve to any cluster is a no-op, not a clear — a one-off search outside known territory shouldn't erase a previously-good home value.

**`index.php` changes:** `checkRealAuthState()` now also captures `csrf_token` and a new `realAuthActive` flag (nothing previously tracked real-auth status or held the CSRF token outside that function's own scope). New `persistAccountHomeLocation(loc)` — fire-and-forget POST, no-ops if not real-auth or no zip — called from `handleLocationSubmit()`, `autoDetectLocation()`, and `switchRegion()`, immediately after each one's existing `saveCurrentLocation()` call. Deliberately **not** hooked to `bindLocationToProfile()`'s call sites as originally drafted in the Phase 1 design — rereading the real code, that function is only called directly from `switchRegion()`, and only indirectly (via `createOrUpdateProfile()`) from the other two, gated on the quick-profile email field, which doesn't apply to real-auth identity at all. Excluded on purpose: `resetLocationScope()`/`clearGeolocation()` (not a new "set"), and the radius-slider functions (zip unchanged, would spam the endpoint on every drag tick).

**Live-tested and confirmed working — see Section 20.21.**

### 20.21 Quick-Profile Removal, Sign-Out Storage Fix, `set-home-location.php` Cleanup — Shipped, Confirmed Working

**Trigger:** follow-up session, after the user confirmed the Section 20.20 home-location write-side fix works in production ("ok this seems to be working"). Three pieces of work landed in this pass.

**1. Quick local profile system fully removed from `index.php`, replaced with a real account nav.** The old password-less "quick local profile" (`homeLocation`, `isSignedIn()`, `checkAuthState()`, the `#user-nav` markup, and the entire fake `#auth-modal` — `openAuthModal`/`closeAuthModal`/`handleAuthSubmit`/`openReturningVisitorPrompt`/`skipReturningVisitorPrompt`/`restoringLocationOnLogin`) is gone, along with `bindLocationToProfile()`, `createOrUpdateProfile()`, and `#loc-email-block`. This was the system Section 20.12 deliberately left untouched as out-of-scope; removing it is a new, explicit decision, not a reversal of that earlier restraint — it had become fully redundant once real auth (`#real-auth-user`/`#real-auth-guest`) existed alongside it. Replaced with an accessible dropdown (`#account-menu-btn`/`#account-menu-panel`, `toggleAccountMenu()`) containing Dashboard / Business Portal / Sign Out links, driven entirely by real-auth state.

- **Storage-tier logic changed to gate on `realAuthActive` instead of the removed `isSignedIn()`.** `saveCurrentLocation()`, `saveSearchState()`, `loadSavedSearchState()`, and the initial-load restore all switched over. Reads changed to unconditionally check `localStorage` first, then fall back to `sessionStorage` — necessary because `realAuthActive` isn't known synchronously at `DOMContentLoaded` (the `checkRealAuthState()` fetch is async), so a read can't gate on it directly the way a write can.
- **Decision, made via AskUserQuestion — anonymous/signed-out visitors now get session-only persistence.** `sessionStorage`, cleared on tab/browser close — no durable local-account-like convenience layer for anonymous users anymore, since that convenience was what the removed quick-profile system provided and nothing replaces it for anonymous visitors by design.

**2. Sign-out storage bug fixed via a new shared `js/shared-auth.js`.** Real bug found: `index.php` and `listing/businessportal.php`/`user/dashboard.php` each had their own `logout()`, and they'd drifted — `index.php`'s cleared `localStorage`/`sessionStorage` copies of `traversence_location`/`traversence_search_state`, the dashboard's (pre-fix) did not. Signing out from the dashboard left the previous session's location/search visible to the next visitor in that browser tab. Fixed by consolidating to one canonical `logout()` in `js/shared-auth.js` (POSTs to `/api/auth/logout.php` with `X-CSRF-Token: window.appCsrfToken`, clears both storage tiers unconditionally regardless of which was in use, redirects to `/index.php`), included via `<script src="/js/shared-auth.js">` on both `index.php` and `user/dashboard.php`. New convention: both pages now set `window.appCsrfToken` (`index.php`'s `checkRealAuthState()`, `user/dashboard.php`'s `init()`) so the shared `logout()` can read the right token from either page without its own session-check logic.

**3. `user/api/set-home-location.php` cleaned up: manual JSON body parsing swapped for `Response::jsonBody()`.** Confirmed as the real, established pattern via `api/auth/login.php`'s source (`$body = Response::jsonBody();`). The endpoint's actual resolution logic (zip → `micro_clusters` via `primary_zip`/`associated_zips` → `users.home_cluster_id`) is unchanged. `php -l` clean.

**Status: all three pieces confirmed working in production per the user's own testing ("ok this seems to be working").** This is what the Development Status checklist above now reflects.

### 20.22 Dashboard HTTP 500 — Diagnosis In Progress, Root Cause Still Unconfirmed

**Trigger:** user reported `/user/dashboard.php` returning HTTP 500 in production (Chrome's generic error page, two screenshots), alongside a report that the "My Account" nav link fails to reach the dashboard after login.

**Diagnostic steps taken:**
- Read the real `dashboard.php` source (user-uploaded) — no obviously broken `require`/include path, no syntax issue visible by inspection.
- Formed a specific hypothesis: `Auth::currentUser()`'s returned array might be missing `created_at`, and a `strtotime(null)` (or similar) on the dashboard's "member since" display could be throwing a fatal.
- **Hypothesis explicitly ruled out** once the user uploaded the real `Auth.php` source: `currentUser()`'s SELECT list is `'SELECT id, email, display_name, home_cluster_id, role, is_admin, session_version, email_verified_at, created_at FROM users WHERE id = :id LIMIT 1'` — `created_at` is present and returned. This also incidentally confirmed `home_cluster_id` is present (relevant to the home-location feature, Section 20.20/20.21), closing a risk that had been flagged but not yet checked.
- No other candidate cause found by inspection of `dashboard.php` or `Auth.php` alone.

**Blocked on:** the actual PHP error log. Repeatedly requested (BlueHost cPanel → Metrics → Errors, or a temporary `display_errors`/`error_reporting(E_ALL)` toggle in the file) — never provided as of this entry. This is the only remaining path to a real diagnosis; further guessing from the two files alone is not productive.

**Status — explicitly still open.** The user's later message ("ok this seems to be working, but I want to address how we set the location...") arrived without explicitly confirming this bug was fixed — it's ambiguous whether "this" referred to the dashboard 500 specifically or to the broader session/login flow generally. **Treat as unresolved until the error log is seen and a real fix is confirmed**, not as silently closed.

### 20.23 "Area of Interest" / Location-Persistence Architecture — Open Question, Not Yet Designed

**Trigger:** user, unprompted by a specific bug, raised: "I want to address how we set the location within the user account so that I dont have to keep setting it when I sign in." Scoped and re-scoped twice via follow-up:

1. First re-scope: not about which of the three location-setting UI routes (hub-pill click, auto-detect, manual zip entry) to fix — **all three** need to reliably persist. Auto-detect is the user's own most-used route, but manual entry is explicitly fine as a fallback wherever auto-detect isn't reliable. Also raised, in the same message: what about visitors who want to browse locations/listings outside the current AZ/NM/UT trade area, including international visitors?
2. Second re-scope, via AskUserQuestion: on the out-of-region question, the user redirected past the offered options to a broader framing — this is about *exploration*, top-down: "if someone is local and looking for resources or international looking for destinations... how?" On registration-time location capture, the user picked "add a location step to registration" but qualified it: **"this also needs to be for the area of interest not just specific where I am right now location."**
3. Non-functional requirement, stated explicitly and unprompted: whatever gets built must be **"viable at any scale"** once the not-yet-built crawler ingest pipeline adds much broader geographic coverage.

**Concept reframe (direction set, not yet built anywhere):** stop modeling the saved value as "the user's current/GPS location." Model it as **"area of interest"** — the place a visitor wants to search/connect with, which может be their own home area (a resident) or a destination elsewhere (a local researching a trip, or someone remote researching the region). This reframe should extend to registration/signup, not just post-login account settings — captured as an area of interest at signup, not a GPS capture.

**International / other-region scope — explicitly clarified as a data problem, not a UI problem.** No location-picker or persistence fix can produce results outside the platform's actual dataset. `zip_coordinates` (the comprehensive geography table) and `micro_clusters`/`geo_hubs` (the curated taxonomy) both currently have zero rows outside the US, let alone outside the AZ/NM/UT pilot trade area. Serving international or other-region interest requires real content/data expansion — new geo coverage, crawler sourcing outside the current area — which is a separate, unaddressed product/content-scope decision. This is out of scope for any location-persistence bugfix or UI change.

**Critical discovery made while scoping a fix, not yet reconciled: the codebase has two entirely separate, non-intersecting geography systems.**

- **System 1 — legacy/comprehensive, powers actual search today.** `zip_coordinates` table (zip/city/state/lat/lon; the table's own code comments in `nearby_hubs.php` describe it as covering "all of North America" and growing independent of business data), accessed via `require_once 'db_config.php'; getDbConnection();`. Used exclusively by `search_listings.php` (real listing search + distance filtering, confirmed via full source read) and `nearby_hubs.php` (the "Regional Hub Proximity" pill list — groups `zip_coordinates` by `city, state` via Haversine distance with a bounding-box pre-filter, confirmed via full source read). **This is the system that actually powers everything a real visitor sees and clicks on the live site.**
- **System 2 — newer/curated, powers only the home-location feature.** `micro_clusters`/`geo_hubs`/`hubs` tables (small, curated "trade area" taxonomy — 2 real rows today: Cluster 1 = St. Johns AZ, `primary_zip` `85936`; Cluster 2 = Round Valley, `primary_zip` `85938`, `associated_zips` `["85938","85925"]`), accessed via `Database::connection()`/`Database.php`. Used only by `api/auth/session.php`'s `home_location` read (Section 20.20) and `user/api/set-home-location.php`'s write (Section 20.20/20.21).
- **These two systems never intersect.** Confirmed directly from `nearby_hubs.php`'s real source: hub pills shown to users are built purely from `zip_coordinates`, grouped by city/state — they carry **no `micro_cluster` id or any other cluster reference at all.** This invalidates a fix design floated mid-session (resolve `switchRegion()`'s persistence gap by passing the hub pill's already-resolved cluster id straight through) — there is no cluster id anywhere in that data path to pass through.
- This is a fresh, concrete instance of the "two DB connection mechanisms will drift" risk already flagged as an open item in Section 17 — not a new category of problem, but a specific case of it now blocking real feature work.

**Confirmed, code-certain bug sitting on top of this (not yet fixed, and shouldn't be fixed until the architecture question below is resolved, so the fix targets the right storage mechanism):** `switchRegion(cityName, stateAbbr)` in `index.php` unconditionally sets `currentLocation.zip = '';` before calling `persistAccountHomeLocation(currentLocation)` (added in Section 20.20). `persistAccountHomeLocation()`'s own guard is `if (!realAuthActive || !loc.zip) return;`. Since `switchRegion()` always clears `zip` first, this is a **guaranteed no-op, every single time**, not an intermittent failure — every hub-pill click by a signed-in user silently fails to persist, confirmed directly from the shipped source rather than inferred. `autoDetectLocation()` is a *sometimes*-persists case by contrast (depends on whether reverse-geocoding resolves a postcode, documented in that function's own comments as unreliable in this app's rural coverage area) — not guaranteed-broken like `switchRegion()`. `handleLocationSubmit()` (manual zip entry) should already persist correctly, since a user-typed zip is always present.

**Open, undecided architectural question — this is the actual blocker, not the bug above:** should the account's persisted "area of interest" keep targeting `users.home_cluster_id`/`micro_clusters` (today's small, curated, 2-row table, disconnected from real search/hub UI), or should it instead store a zip/lat/lon directly — mirroring `zip_coordinates`, the table that's already comprehensive and is the one actually used by real search and hub pills, and that's the natural target for the "viable at any scale" crawler-ingest requirement? Storing zip/lat/lon directly would also finally give `switchRegion()`'s hub-pill flow something real to persist (a resolved city's zip/lat/lon from `zip_coordinates`, which it does have access to via the hub data), rather than needing a cluster id that doesn't exist in that flow. **Not decided.** Deciding this is a prerequisite for: fixing the `switchRegion()` no-op, designing the "area of interest" reframe's actual storage/read paths, and designing registration-time capture.

**Registration-time capture — blocked on a separate, confirmed constraint.** Read the real `api/auth/register.php` source: it does not create a session (`Auth::register()` sends a verification email and returns 201; no `session_regenerate_id()`, no `$_SESSION['user_id']` set — email verification gates full account activation). So a signup-time "area of interest" step cannot reuse the existing authenticated `user/api/set-home-location.php` endpoint — there's no session yet to authenticate with. It would need new handling built directly into `Auth::register()`/`api/auth/register.php`, resolving and writing the area-of-interest value in the same request/transaction that creates the account row. Not designed, not built.

**Status: fully scoped as an open question, explicitly not decided, and correctly not started.** This is the state at hand-off — see the Development Status checklist above for the itemized open threads.

### 20.24 Area-of-Interest Schema & Write/Read Rework — Shipped, Not Yet Live-Tested

**Trigger:** Direct follow-up discussion of 20.23's open architecture question.

**Resolved:** `zip_coordinates` — the comprehensive, crawler-ingest-scale table already used by real search (`search_listings.php`) and hub pills (`nearby_hubs.php`) — is now the primary resolution source for a user's area of interest. `micro_clusters`/`home_cluster_id` is retained as a secondary, best-effort derived field for cluster-scoped features (Town Hall, Section 23), not replaced.

**Clarified in the same discussion:** `db_config.php`'s `getDbConnection()` and `Database.php`'s `Database::connection()` are two connection *mechanisms* to the same physical database (bridged credentials, per Section 17) — `zip_coordinates` and `users` were never actually cross-database, only cross-code-path. This made the fix a data-modeling decision, not a plumbing one.

**Shipped:**
- `schema/migrations/007_add_area_of_interest.sql` — adds `users.area_zip`/`area_city`/`area_state`/`area_lat`/`area_lon`/`area_updated_at`. Idempotent via the established information_schema-existence-check pattern (Section 17).
- `user/api/set-home-location.php` rewritten — resolves a submitted zip against `zip_coordinates` (primary, writes `area_*`) and `micro_clusters` (secondary, writes `home_cluster_id`) independently, in one transaction. No-match-on-either is a no-op, not a clear, matching prior behavior.
- **`api/lib/Auth.php` patched** — `currentUser()`'s SELECT extended to include `area_zip`, `area_city`, `area_state`, `area_lat`, `area_lon`, following the existing precedent that `home_cluster_id` is already exposed on the returned user array rather than stripped.
- **`api/auth/session.php` rewritten** — `home_location` resolution now prefers the direct `area_*` fields on `$user` (no query needed for the common case), falling back to the original `home_cluster_id` → `micro_clusters` → `zip_coordinates` join only when the direct fields are unset (pre-migration accounts, or a cluster-only match with no `zip_coordinates` hit). Response contract (`home_location: {city, state, zip, lat, lon}`) preserved exactly, so `index.php`'s consuming code needs no change.

**Real gap caught and fixed before it shipped, not after:** the write-side and read-side were designed a turn apart. The first draft of the migration/write-endpoint stored a single computed `area_label` ("City, State") string; reconciling it against `session.php`'s actual response contract (separate `city`/`state` keys, consumed by already-shipped `index.php` code) surfaced that a combined label doesn't cleanly decompose back into that shape. Fixed by storing `area_city`/`area_state` as separate columns instead of a combined label — caught during this same turn, before any of it was deployed, not discovered later via a live bug.

**Not yet shipped, explicitly deferred:**
- `switchRegion()`'s guaranteed-no-op bug (Section 20.23) — now unblocked in principle (hub pills' city/state can resolve against `zip_coordinates` the same way), but the actual fix (returning a representative zip/lat/lon from `nearby_hubs.php` per hub) is not built.
- Registration-time area-of-interest capture — still blocked on `Auth::register()` creating no session; unaffected by this schema change beyond now having a clear resolution target to write to.

**Status:** Schema, write-side, and read-side all delivered. **Not yet live-tested end-to-end.** If a previous draft of migration 007 (with `area_label` instead of `area_city`/`area_state`) was already run against production, drop the orphaned column (`ALTER TABLE users DROP COLUMN area_label;`) before or after running the current version — nothing reads it.

---

## 21. Crawler Ingestion & Experiential Content Pipeline (Expanded Scope)

### 21.0 Scope & Relationship to Existing Systems (Clarification)

A separately-drafted architecture document proposed expanding the crawler beyond structured listing ingestion into narrative/experiential content extraction, a 4-tier safety engine, and synthesis with source citations. Confirmed: this is a **separate system from Trust/Verification (Section 4, 20)**, not an extension of it.

- **Trust/Verification (existing)** — user-initiated, human-driven. Governs *who owns/controls* a listing (claim, vouch, referral, admin approval).
- **Crawler Ingestion (this section)** — machine-initiated, automated. Governs *what data exists* before a human ever touches it.

These do not share scoring. Crawler confidence is tracked as a distinct `ingestion_confidence` (0–100, Tier 3 below) — kept namespace-separate from `entities.trust_score`/`users.trust_score` (Section 16) to prevent future conflation in schema or code. `field_locks`/`is_locked` (Section 12.1, 15) remains a one-way gate the crawler checks before writing — it does not read from `TrustScore.php` or `Verification.php`.

### 21.1 Layout, Auth Wrapping, Onboarding (UI Layer)

Modular template partials (`templates/global-header.php`, `templates/global-footer.php`, `templates/user-nav.php`, `templates/listing-nav.php`), branded auth page wrapping for `login.php`/`register.php`/`forgot-password.php`/`reset-password.php`, and dashboard empty-state onboarding (claim vs. create paths). Low-risk UI work — sequenced independently of the crawler subsystem and can proceed alongside current frontend/GBP work.

### 21.2 Structured Listing Ingestion (Extends Section 15)

**Reuses `CrawlerIngest.php` directly — no parallel/duplicate ingestion logic.** Confirmed:

- **NAP Standardization & Matching**: reuses the existing normalizer and canonical composite hash (name, address, phone/zip) already implemented in `CrawlerIngest.php` per Section 12.1/15 — not reimplemented for this expanded scope.
- **Field-Locking Preservation**: honors the existing `field_locks`/`is_locked` mechanism (Section 12.1, 15) so admin- or merchant-verified values are never overwritten by incoming raw crawler data.

This preserves a single-source-of-truth ingestion pipeline rather than introducing parallel/conflicting logic alongside the existing one.

### 21.3 Staging Table (`crawler_raw_index`)

New table, buffers raw crawler output prior to graph integration — distinct from the direct-upsert path `CrawlerIngest.php` currently uses for structured listings. Fields: `id`, `source_url`, `payload_type` (listing | content), `raw_payload` (JSON), `content_hash` (SHA-256), `confidence_score` (0–100), `status` (staged | quarantined | published), `created_at`. Routes by payload type to identity-matching (listing) or narrative-extraction (content) pipelines.

### 21.4 Experiential & Narrative Content Pipeline

Extracts landmarks/routes/spots from non-commercial travel text, converts route descriptions into spatial micro-cluster nodes, attaches temporal tags. Content safety gateways filter trespassing/hazard recommendations and scrub PII/harassment. An `entity_content_bridge` connection type (or reuse of the existing `connections` table per Section 12.1's graph model — worth confirming at build time rather than assuming a new table) attaches approved notes to parent geo-hubs, micro-clusters, and business entities.

### 21.5 4-Tier Safety Engine — Split Execution Model

Per Section 12.3 (Offsite Crawler Execution), the safety tiers split across environments:

- **Tiers 1–3 (offsite)**: Network gatekeeping (SSRF/private-IP blocking, robots.txt, timeout/size caps), payload filtering (script stripping, PII redaction, adult-content filtering), and confidence scoring — execute on the offsite VPS/serverless worker, never on BlueHost.
- **Tier 4 (on BlueHost)**: Quarantine vault (`crawler_quarantine` table) and audit log live on BlueHost, since that's where the Phase 7 admin panel and review UI reside.

**Score-based routing (Tier 3 output), confirmed:** the `ingestion_confidence` score produced by the dedup/matching step (21.2) determines routing, consistent with `crawler_raw_index.confidence_score` and `crawler_raw_index.status` (Section 21.3):

| Confidence | Routing |
|---|---|
| ≥ 80 (High) | Merges directly into production `entities` records (respecting `field_locks`) |
| 50–79 (Medium) | Routes to `crawler_raw_index`, `status = staged`, surfaced in the unified admin review queue |
| < 50 (Low) | Routes directly to `crawler_quarantine` |

This is the same threshold model as `crawler_raw_index.status` in Section 21.3 — stated explicitly here since it governs the actual routing decision, not just the storage schema.

### 21.6 Context Synthesis, Source Provenance & Shortcode Caching

Fact aggregation into original editorial-tone summaries. Outbound citations stored in a `content_sources` JSON column (source_name, source_url, accessed_at). UI citation badges on synthesized guide pages. Originality guardrails: N-gram overlap checks against raw source text; minimum 3 distinct verified sources required before automated guide generation triggers.

**Shortcode caching — resolved.** The `directory_feed` shortcode (Section 6) on guide/`/guide/{slug}` pages is **cached/pre-rendered, not live-queried**: rendered to cached HTML blocks on a periodic cron cycle, with targeted cache invalidation triggered whenever a business in the referenced micro-cluster updates its operational status or tier — not a blanket time-based expiry alone. Rationale: keeps editorial guide pages fast and SEO-clean, and avoids per-pageview query load scaling linearly with traffic — consistent with the same shared-hosting resource discipline behind the offsite crawler decision (Section 12.3).

### 21.7 Ingestion Endpoint Authentication (Gap Identified & Closed)

Section 12.3 defines the offsite worker POSTing lightweight REST payloads to a BlueHost API endpoint, but did not define endpoint authentication for this expanded pipeline (the existing `api/ingest.php` already has bearer-token auth against `CRAWLER_API_TOKEN` per Section 15 — this closes the equivalent gap for the new staging/content endpoint). Closed as follows:

- **HMAC-SHA256 signed payloads**: offsite worker signs each POST (raw JSON body + timestamp) using a shared secret stored in env config on both sides. Endpoint verifies signature and checks timestamp freshness (±5 min window) to block replay attacks.
- **Independent from browser session auth**: server-to-server, not a browser flow — does not reuse `session_version` or CSRF tokens (Section 18).
- **IP allowlist** as a second layer, if the offsite worker runs on a static-IP VPS.
- **Endpoint-side rate/size limits**, not solely reliant on the offsite worker's own caps.

Implementation: one new config value (shared secret) + one verification function (`verifyCrawlerSignature()`) applied at the top of the ingestion endpoint before any write to `crawler_raw_index`.

### 21.8 Route & Taxonomy Gaps Identified in the Dual-Behavioral Audit

A follow-up architectural audit comparing this expanded scope against the Resident/Traveler dual-behavioral model (Sections 1–5) surfaced route and schema gaps:

- **Missing route tier**: the public site needs all three taxonomy levels represented, not just geo-hub — `/hub/{hub-slug}` (Macro/Continental Hub), `/geo-hub/{geohub-slug}` (Strategic Geo-Hub), `/cluster/{cluster-slug}` (Micro-Cluster, the destination for "Get Local" zip lookup — houses hyper-local weather, municipal info, and everyday utility listings, e.g. `/ancient-america/borderlands/cluster-1a`).
- **Missing route**: `/map` or `/explore/map` — dedicated interactive corridor map / scrollable milepost timeline route, distinct from the general Dual Interface described in Section 12.5.
- **`intent_vector` on crawler staging**: the Chameleon Filter's `[Utility]`/`[Experience]`/hybrid tagging (Section 5) needs to be assigned during crawler extraction, not just on manually-managed entities — add `intent_vector` to `crawler_raw_index` alongside the existing per-entity intent tagging, so staged records carry a proposed classification into review rather than arriving untagged.
- **Missing UI**: no toggle for the Chameleon Filter exists yet on public pages or the user dashboard to let a resident explicitly suppress tourism-oriented content. Flagged as a UI gap, not yet built.

### 21.9 Sequencing Decision

- **Section 21.1** (UI/layout) may proceed now, alongside current frontend and GBP landing page work.
- **Sections 21.2–21.8** (crawler subsystem expansion) are treated as a distinct future phase, sequenced **after** the Phase 7 admin panel ships. Rationale: the admin panel now needs to serve multiple queues — verification actions (existing, Section 12.2), crawler staging/quarantine review (new) — so it should be designed as a general-purpose review-queue framework rather than hardcoded to one workflow.

---

## 22. Development Phase Logging Standard (Process Decision)

**Adopted going forward**: whenever a discussion surfaces scope that wasn't already defined in the spec — a new feature, an integration, a subsystem — it gets logged as its own numbered phase using the template below, rather than folded loosely into whatever section prompted it. This keeps the spec able to absorb unexpected downstream adjustments without losing traceability of *why* a phase exists or *what* it depends on.

**Phase Entry Template:**

- **Trigger** — what discussion/decision surfaced this, and when.
- **Scope Summary** — plain-language description of what the phase covers.
- **Dependencies** — what must exist first (schema, other phases, third-party access).
- **Schema/DB Impact** — new tables/columns, or none.
- **Sequencing Position** — where it sits relative to other phases already in the roadmap.
- **Open Risks/Flags** — anything ambiguous, external, or budget-sensitive.
- **Status** — Proposed / Approved / In Progress / Shipped.

Sections 23 and 24 below are the first two phases logged under this standard.

---

## 23. Town Hall — Community Discussion Subsystem

- **Trigger**: Surfaced during the Section 21 route-architecture audit as `/cluster/{slug}/townhall` and `/user/townhall`. Confirmed as a fully-scoped, explicitly logged phase (not deferred, not simplified) per explicit instruction.
- **Scope Summary**: Per-Micro-Cluster community discussion space for Resident Members — local group discussions, contractor recommendation requests, and generation of verified social proof tied to a resident's home cluster. Surfaced in two places: the cluster-level public portal (`/cluster/{slug}/townhall`) and the resident's own portal (`/user/townhall`, a personalized feed of their home cluster's activity plus any clusters they follow).
- **Dependencies**:
  - Micro-Cluster taxonomy must be live (Section 2, 19) since every thread is scoped to a cluster.
  - `user_home_cluster`/`home_cluster_id` on registration — already exists on `users` per Section 15's migration 003 (`home_cluster_id`); Town Hall is the first feature to actually consume it. **Note: as of Section 20.20/20.21, the home-location feature also now consumes this column** — Town Hall is no longer the only planned consumer, though it remains the only one actually built as of this entry.
  - Existing auth/session system (Section 14, 18) — posting requires a logged-in Registered User at minimum; no anonymous posting.
- **Schema/DB Impact** (new, not yet built):
  - `townhall_threads` — id, cluster_id, author_user_id, title, body, status (active | locked | removed), created_at.
  - `townhall_replies` — id, thread_id, author_user_id, body, status, created_at.
  - `townhall_flags` — id, target_type (thread | reply), target_id, reporter_user_id, reason, created_at — user-driven reporting, separate from admin-driven moderation.
  - Moderation status fields follow the same enum pattern already used elsewhere (e.g. the listing state machine, Section 12.2) rather than introducing a new pattern.
- **Sequencing Position**: A full UGC subsystem, comparable in complexity to the crawler subsystem (Section 21) — not a simple route addition. Sequenced **after** the Phase 7 admin panel ships, for the same reason as the crawler: Town Hall moderation needs a queue in that same admin panel (flagged threads/replies), so the panel should be built as the general-purpose review-queue framework first.
- **Open Risks/Flags**:
  - **Moderation load**: needs a moderation policy before launch — who reviews flags, what the removal/ban escalation path looks like, whether posting requires a minimum trust score to reduce spam.
  - **Abuse surface**: contractor recommendation threads are a natural spot for fake reviews or competitor sabotage — may warrant a minimum account age or verified-resident status to post, decided alongside the moderation policy above.
  - **Hosting load**: threaded discussion is straightforward read/write load for BlueHost/MySQL — no offsite requirement like the crawler, but confirm query patterns (thread listing, reply counts) stay indexed and cheap as cluster count scales toward the full 20-hub taxonomy.
- **Status**: Proposed — logged in full per explicit instruction, not yet built.

---

## 24. Direct-to-Operator Financial & Routing Architecture (PMS/Booking Integration)

- **Trigger**: Surfaced as "PMS/webhook settings" on `/listing/manage/{id}` during the Section 21 audit; fully specified in a later session. Reconciles and supersedes the brief mention in Section 10 ("Direct-to-operator booking/payment routing... deferred") with full technical detail — the deferral decision itself (see Sequencing Position below) is reaffirmed, not reversed.
- **Scope Summary**: Traversence's booking model rejects the OTA (Expedia/Booking.com-style) escrow-and-commission model. Bookings and payments settle directly into the merchant's own software and bank account at 0% platform commission. Two paths depending on merchant sophistication:
  - **Path A — Native System Integration**: For operators with existing PMS software (e.g. Cloudbeds, Guesty, RMS). Merchant connects via API token or OAuth handshake in `/listing/manage/{id}`. Bidirectional webhook sync:
    - *Inbound*: PMS pushes live availability, rates, and calendar blockouts to Traversence, rendered on `/listing/{slug}` without manual double-entry.
    - *Outbound*: When a traveler books, Traversence transmits reservation parameters directly to the merchant's PMS API.
    - *Settlement*: Payment is processed entirely by the operator's own attached gateway (Stripe, Square, Authorize.Net, etc.) linked to their PMS. Traversence never touches payment flow or holds guest funds.
  - **Path B — Independent Alternative Framework**: For off-grid operators, small B&Bs, or independent guides without PMS software. No webhook sync — booking requests route via secure email, SMS, or dashboard messaging directly to the operator. Billing is handled offline via the merchant's own invoicing. 0% commission guarantee holds across both paths.
- **Dependencies**:
  - Merchant Portal (`listing/businessportal.php`, Section 20.13) must exist first, since credential entry and Path A/B selection happen there — it already does.
  - Listing detail page (`/listing/{slug}`) needs a live-availability display slot, fed by Path A's inbound webhook data. Not yet built (public directory frontend itself is an open item — see Development Status checklist).
- **Schema/DB Impact** (new, not yet built):
  - `merchant_pms_credentials` — merchant_id, provider (cloudbeds | guesty | rms | other), encrypted token/OAuth refresh token, connected_at, status.
  - `pms_availability_cache` — merchant_id, room/unit identifier, rate, availability window, last_synced_at — populated by inbound webhooks, read by `/listing/{slug}`.
  - `booking_requests` — for Path B: merchant_id, traveler contact info, requested dates, status (sent | acknowledged | confirmed | declined), routing_method (email | sms | dashboard).
  - `webhook_event_log` — audit trail of inbound/outbound webhook events per merchant, for debugging sync issues.
- **Sequencing Position**: **Locked as last phase in the build order, until told otherwise.** Requires most other development to be completed first, so it stays at the tail of the roadmap — after the crawler subsystem (Section 21), the Phase 7 admin panel, and Town Hall (Section 23). This is a deliberate decision, not inferred from other phases shifting around it, and is consistent with the existing Phase 6/e-commerce deferral already established in Sections 9, 10, and 13.
- **Open Risks/Flags**:
  - **Third-party API access**: Cloudbeds, Guesty, and RMS typically require a developer/partner agreement and sometimes a certification process before granting webhook/API access — external lead time, not build time, worth starting early once this phase is greenlit.
  - **Credential security**: `merchant_pms_credentials` stores tokens for third-party financial-adjacent systems — needs encryption at rest and a rotation/revocation path, consistent with the security bar already set for user auth (Section 17, 20.18).
  - **Path B is a manual process at scale**: as merchant count grows, email/SMS-routed booking requests have no automatic confirmation loop — worth deciding whether `/admin/` needs visibility into stalled Path B requests, or whether that's purely between merchant and traveler.
- **Status**: Proposed — fully specified, sequencing locked, not yet built.

---

## Change Log
- Initial draft compiled from planning conversation, reconciling Part 8 blueprint PDF with product decisions.
- Added business/financial context, net-new feature areas, and open documentation questions from the full Master Blueprint (Parts 1–8 + scaling/financial docs).
- Resolved hub count (20, canonical) and Strategic Sub-Hub = geo-hub tier mapping via the St. Johns worked example.
- Resolved search model: mile-radius slider and concentric taxonomy-tier fallback confirmed to coexist as separate mechanisms; added Bottom-Up/Top-Down framing, Linear Corridor Loops, and the Chameleon Pivot.
- Added phased Dynamic Taxonomy & Discovery Engine (Phase A: automated geography-based seeding; Phase B: adaptive LLM-assisted reclustering, deferred) plus legal/compliance framing.
- Locked pilot taxonomy: Cluster 1A (St. Johns, 85936) and Cluster 1B (Round Valley/Springerville & Eagar, 85938/85925) under the Ancient Borderlands geo-hub.
- Expanded listing lifecycle to full state machine (unclaimed → claimed-unverified → pending-verification → verified, plus disputed and opt-out/soft-suppression branches), added dedup/enrichment pattern and Community Reporting & Dispute Resolution system.
- Resolved verification model: three independent paths to verified status (self-verification, Peer Validation, admin), admin repositioned as an oversight/dispute layer rather than a mandatory gate.
- Resolved booking/payment routing: deferred to a later phase, to be built alongside the broader user-business communications/connection system.
- Added Section 12 (Technical Implementation Architecture): entities/entity_metadata/connections graph-in-SQL schema, offsite crawler execution rules, PHP/PDO+MySQL stack, dual map/list directory interface, and content-to-map spatial integration.
- Resolved Admin Override mechanism: confirmed as a custom-built `/admin` application panel, not BlueHost's cPanel hosting interface.
- Generated `traversence_schema.sql` (16 tables) and `Database.php` (PDO connection class) as concrete deliverables implementing Section 12.
- Added Section 13 (MVP Build Roadmap): confirmed MVP scope is Phases 1–5, 7–9 of the full task list; Phase 6 (e-commerce) and delegate/multi-user business access both explicitly deferred post-launch. Added `posts` table (micro-publishing, sits alongside the graph content model rather than replacing it) to the schema. Standardized on `Database::connection()`/`transaction()` and `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` env keys; added `CRAWLER_API_TOKEN`.
- Added Section 14 (Phase 2 Deliverable): built the full authentication API (`bootstrap.php`, `lib/Response.php`, `lib/Auth.php`, register/login/logout/session/verify-email/password-reset endpoints, auth/admin middleware). Removed redundant `email_verification_token`/`email_verification_expires` columns from `users` in favor of the existing generic `email_confirmations` table.
- **Discovered a live production database already exists** (`listings`, `users`, `zip_coordinates` — flat, non-graph schema). Confirmed migration strategy: Option 2, single controlled migration into the graph architecture.
- Added Section 15 (Phase 3 Deliverable): built `002_fix_zip_coordinates.sql`, `CrawlerIngest.php` (shared hashing/cluster-resolution/upsert logic), `api/ingest.php` (live endpoint), and `scripts/migrate_listings.php` (one-time CLI migration). Two items flagged as unresolved: legacy `pending` status backlog handling, and unconfirmed `users` table structure.
- **Confirmed real `users` table structure** (id INT(10) UNSIGNED, email, password_hash, role ENUM('admin','business_owner'), created_at). Fixed a real bug: every FK referencing `users.id` across the schema was mistyped BIGINT UNSIGNED instead of INT UNSIGNED. Removed the conflicting `CREATE TABLE users` from the main schema; added additive `schema/migrations/003_alter_users.sql`. Updated `Auth.php` to derive `is_admin` from `role` instead of a nonexistent column. Left the `role` enum's `business_owner`-vs-`resident` semantics as an open, unresolved product question rather than guessing on live data.
- **Reconciled migration 003 through a second round:** adopted a stored `is_admin` column (backfilled from `role`) instead of deriving it at read-time — decouples admin permission from the `role` enum entirely, resolving part of the earlier ambiguity. Fixed gaps in an intermediate draft (missing `display_name`/`is_suspended`/`updated_at`, which `Auth.php` already depends on; `home_cluster_id` typed as signed `INT` instead of `UNSIGNED`; missing FK constraint, index-only). `trust_score` implemented at `DEFAULT 0.00` per explicit instruction, but flagged as an incomplete design — no mechanism yet defined for how it increases, only how it degrades.
- Added Section 16 (System-Wide Trust Score Architecture): resolved the trust_score earning-mechanism gap with a full points economy across users/entities/entity_metadata. Added `schema/migrations/004_add_trust_scores.sql` and `api/lib/TrustScore.php` (centralized points constants + threshold-enforcement, application-level not DB triggers). Wired up email-verification scoring (`Auth.php`) and entity-creation-time scoring including the geocode-match bonus and `ai_summary` baseline (`CrawlerIngest.php`), with an anti-gaming safeguard (one-time, not re-applied on re-crawl). Most of the points economy (claim, disputes, accuracy reports, community edits) has no calling endpoint yet and is documented as specified-but-not-wired, pending those later-phase features. Clarified that the existing three-path verification model remains authoritative for `status = 'verified'`; trust_score is a parallel ranking/moderation signal, not a second independent gate.
- Reconciled `TrustScore.php` against an alternate implementation draft: rejected two regressions (dropped metadata constants that would have broken already-wired `CrawlerIngest.php` code; automatic un-suppression, which would let a suppressed listing silently reappear without admin review), adopted one improvement (`SELECT ... FOR UPDATE` row locking for entity adjustments), and fixed a provenance-stamping bug found in the original (`updated_by_type` was hardcoded to `'admin'` regardless of actual actor — now an explicit parameter).
- **Resolved the two remaining open items.** Legacy `pending` listings now migrate identically to `active` (visible, unclaimed, baseline trust_score, no suppression) — updated `CrawlerIngest::mapLegacyStatus()` and `migrate_listings.php` accordingly. `role` enum confirmed to stay lean (organizational only); `is_admin` — already the sole authorization signal in `Auth.php` — is the permanent answer for access control, decoupled from `role` so it can gain values later without risk.
- Added Section 17: fixed three real bugs in a separately-created web-based migration runner (`db_config.php`, `run_migration_web.php`) — plaintext credentials now in `.env` (rotation recommended, treated as compromised since shared in an uploaded file), a "headers already sent" root cause (unconditional `header()` calls on connection failure), and a more serious latent bug where `--dry-run` likely never reached the migration script over HTTP (fixed by passing mode via an explicit constant instead of a faked `$_SERVER['argv']`, which `getopt()` doesn't reliably read under a web SAPI). Added a shared-secret access gate to the runner. Flagged two unresolved items: BlueHost's MySQL connection-limit issue (infrastructure, not code), and the project now having two parallel DB connection mechanisms needing eventual consolidation. Also caught `micro_clusters` showing 0 rows in production via a live phpMyAdmin screenshot — pilot cluster seed data likely didn't take.
- **Migration executed successfully in production**: 4,376 created, 102 updated, 0 errors, matching the dry run exactly.
- Added Section 18 (CSRF Enforcement): pulled Phase 9's deferred CSRF check forward into the current build. Added `Response::requireCsrf()`, wired into all six state-changing `api/auth/*` endpoints.
- Added Section 19: resolved the cross-border (AZ/NM) micro-cluster question raised after the live migration, using the Part 3 (Continental Network) blueprint's own stated Hub 9 jurisdiction and Border/Overlap Protocols — New Mexico ZIPs confirmed in-scope, and reorganization deferred per the source document's explicit anti-fragmentation guidance rather than reactively splitting geo-hubs at low data density.
- Added Section 20 (Phase 4 Design, pre-build): consolidated an extensive planning discussion covering step-up authentication (OTP for claims/password/email changes, with a three-part email-revert recovery flow), the four independent verification routes (self-domain, referrals, vouchers, admin) now tiered by strength with per-tier trust bonuses replacing the earlier flat +50/+25, and the new `entity_referrals` mechanism. Explicitly rejected an aggregate-point-threshold reading of verification in favor of keeping each route independently sufficient, per the source blueprint's own stated principle that trust_score never acts as a secondary gate to verification. Not yet implemented — this is design captured ahead of the actual Phase 4 build.
- **Full Phase 4 build completed** (Section 20.6): `migration 006` (session invalidation + confirmation metadata), `Verification.php` (all four tiers), `claim.php`, referral request/confirm endpoints, `vouch.php`, `change-password.php`, the three-endpoint email-change/revert flow, `my-listings.php`, `update-metadata.php`, and a fully rebuilt `dashboard.php` replacing the old localStorage fake auth with real session-backed API calls. Fixed a standing gap flagged since Phase 2: `Auth::resetPassword()`'s forgot-password flow now genuinely invalidates other sessions via the new `session_version` mechanism, rather than only describing that intent in a comment.
- Added Section 20.7: built `api/lib/Mailer.php` (hand-rolled authenticated SMTP client, no external dependency) and wired it into every previously-marked email integration point. Chose BlueHost's own mailbox hosting as an explicit temporary bridge despite BlueHost's own documentation cautioning against it for transactional mail, with authenticated SMTP over PHP's `mail()` specifically because of the OTP flows' 10-minute expiry windows. Built four minimal landing pages (`verify-email.php`, `reset-password.php`, `confirm-referral.php`, `email-revert.php`) so the now-functional emails have somewhere real to link to — necessary scope surfaced by finishing email delivery, distinguished from the still-deferred claim-button/directory UI.
- **Fixed a real bug in `Mailer.php`** against the account's actual BlueHost SSL/TLS settings: implicit-TLS port 465 was being connected to with a plain `tcp://` socket instead of `ssl://`, which would have failed against a real port-465 server (it expects encryption from the first byte, never sends a plaintext greeting). Fixed, and `.env.example` updated to the account's real mail server values.
- Added Section 20.8: push notifications discussed and deliberately deferred (email OTP remains sole mechanism), Web Push flagged as the lower-cost future option over native apps.
- Added Section 20.9: **redesigned Tier 1 verification** — dropped domain-matching entirely (both the original email-domain-match check and a subsequent domain-ownership-challenge redesign) in favor of a dedicated, owner-triggered post-claim OTP, distinct from the claim OTP itself. `website` is ordinary owner-editable data again. Added `entities.verified_tier` and a new guardrail: vouching requires Tier 2+ verification, preventing the now-cheaper Tier 1 from laundering into Peer Validation trust. Renamed `TrustScore` constants accordingly. Built `api/verify/tier1.php`, updated `StepUpAuth`/`Verification.php`/`vouch.php`/`claim.php` to match.
- Added Section 20.10: extensive white-glove delegate access design captured (Option A refined into "agent with real grantable permissions, own identity attributed" — genuinely Option B's architecture with better attribution), plus voucher-request/invitation system and a small scoped admin verification tool (narrower than full Phase 7). Hard line established: verification itself is never delegable, and vouchers are never delegate-submittable — delegation covers gathering evidence, not bypassing it. None of Section 20.10 is built yet; captured as confirmed design for the next build session.
- Added Section 20.11: adopted a comprehensive master punch list as the primary status-tracking reference (correcting one inaccuracy in it — a claimed `user/dashboard.php` redirect that didn't exist). Confirmed the personal/business dashboard split as a real decision. **Moved `dashboard.php` to `business/dashboard.php`** (real folder structure, mirroring `api/business/*` — superseding an initial flat `business-portal.php` rename), converted every page's `fetch()` calls platform-wide to absolute `/api/...` paths, updated `login.php`'s redirect target, kept `dashboard.php` as a redirect stub for `index.php`'s unmigrated links. Queued the zip-to-cluster cleanup (Section 19) as a feature to build inside the future admin verification tool rather than addressing it now.
- Added Section 20.12: built `user/dashboard.php` (personal account dashboard, including working UI for the previously-page-less change-password/change-email backend) and `api/user/referrals.php`. Applied a scoped alignment patch to `index.php` — separated the old password-less quick-location-profile system from real account status (now checked via a genuine session API call), removing the misleading "Owner Dashboard" link that was gated by the fake profile and adding honest, separately-driven Sign In/Register/My Account nav elements. Explicitly not the full homepage/magazine rebuild. Caught and fixed a second instance of the relative-path bug class from the `business/dashboard.php` move — this time in plain HTML `href`/`src` attributes, not just `fetch()` calls.
- Added Section 20.13: full domain reorganization in one pass — `listing/api/*` and `user/api/*` now mirror the page-level `listing/`/`user/` folders; global `api/` reserved for platform-level concerns (`bootstrap.php`, `lib/*`, `middleware/*`, `auth/*`) plus the crawler ingestion pipeline, explicitly reconsidered and kept global rather than moved to `listing/api/` since it's machine infrastructure, not UX-triggered. `business/dashboard.php` → `listing/businessportal.php`, `confirm-referral.php` → `listing/confirm-referral.php`, all business-domain `api/business/*` and `api/user/*` endpoints relocated with depth-corrected require paths, every cross-reference (email links, dashboard tiles, redirect stub) updated to match. `login.php`'s redirect finally moved to `/user/dashboard.php`, completing the personal/business dashboard split's intended flow.
- Added Section 20.14: first real bug found via live testing on the newly-deployed reorg — `api/auth/session.php` only returned `csrf_token` when already authenticated, breaking the very first action on `login.php`/`register.php`/`forgot-password.php` (all three need the token specifically *before* any session exists). Diagnosed via a standalone session-persistence check (`session-debug.php`) that ruled out infrastructure first, then found the real cause in the endpoint's own logic. Fixed by returning `csrf_token` in both branches.
- Added Section 20.15: second real bug found via live testing, next in the sequence — `Database.php`'s `.env` path used `dirname(__DIR__)` (one directory above the file), which was correct only under an assumption (nested inside a subfolder like `api/db_config.php`) that was explicitly superseded when `Database.php` was confirmed deployed at the project root. Fixed to `__DIR__ . '/.env'`. The user's actual `.env` file was correctly configured throughout — the misleading "Database configuration missing" error pointed at the wrong culprit.
- **Immediate follow-up: `Database.php` moved into `api/` instead**, reversing the fix above by changing the file's location rather than its path logic. `dirname(__DIR__)` is correct again in this location; `api/bootstrap.php`'s require path updated to match (`Database.php`, not `../Database.php`). Final, confirmed location: `api/Database.php`.
- Added Section 20.16: fourth real bug found via live testing — `user/dashboard.php` and `listing/businessportal.php` rendered their own HTML source as visible plain text instead of a rendered page. Root cause: both require `api/bootstrap.php` directly for their server-side login check, and `bootstrap.php` unconditionally sets `Content-Type: application/json` (correct for API endpoints, wrong for these two HTML pages). Fixed by explicitly resetting the header to `text/html` right after the require, in both files at once — the second file had the identical latent bug and would have failed identically the moment it was tested.
- Added Section 20.17: confirmed the change-password flow works correctly end-to-end on first real test (correct success message, session preserved on current device, other sessions invalidated). Added show/hide password visibility toggles across all password fields on `user/dashboard.php`, and a client-side "Confirm New Password" match check before the API call. **Found and fixed a real gap immediately after**: password change never had a security-notification email built at all (`api/auth/change-password.php` had no `Mailer::send()` call), unlike email-change which already sends one — a real inconsistency given password change is arguably the more security-sensitive action. Added, pointing to the existing forgot-password flow as the recovery path.
- Added Section 20.18: standard password complexity policy (8+ chars, upper/lower/digit/special) added platform-wide via one shared `Auth::assertPasswordMeetsRequirements()`, replacing three duplicated length-only checks, mirrored as a live client-side checklist + confirm field + show/hide toggle across `register.php`, `reset-password.php`, and `user/dashboard.php`. **Found and fixed a real bug while testing this end-to-end**: `reset-password.php` never fetched or sent a CSRF token at all, despite the endpoint requiring one since Section 18 — existed since CSRF enforcement was added, never exercised until this specific test walked the real "this wasn't me" → forgot-password → reset flow. Also added missing field labels and a password toggle to the Change Email section.
- Added Section 20.19: hardened the email-revert flow after a fully successful end-to-end test raised a real concern — a bare single click, with no visible confirmation, felt thin for a consequential action. Added a required, server-verified "confirm the original email address" step (`hash_equals()` comparison, no reveal on mismatch) as a genuine second factor alongside link possession, without displaying the actual old email on the page itself to avoid unnecessary exposure if the link were ever forwarded.
- Added Section 21 (Crawler Ingestion & Experiential Content Pipeline, Expanded Scope): a separately-drafted architecture document proposed expanding the crawler beyond structured listing ingestion (Section 15) into narrative/experiential content extraction, a 4-tier safety engine, and synthesized guide content with source citations. Confirmed as a system distinct from Trust/Verification — `ingestion_confidence` kept namespace-separate from `trust_score`. Reconciled the offsite/onsite split (Section 12.3) across the new safety tiers, closed a previously-undefined authentication gap on the new staging ingestion endpoint (HMAC-SHA256 signed payloads, independent of session/CSRF auth), and resolved the guide-page shortcode engine to cached/pre-rendered rather than live-queried, for the same shared-hosting resource discipline as the offsite crawler decision. A follow-up dual-behavioral route audit surfaced missing route tiers (`/hub/`, `/geo-hub/`, `/cluster/`), a missing interactive map route, a missing `intent_vector` field on crawler staging records, and a missing Chameleon Filter UI toggle — all logged as gaps to close before this phase builds. Sequenced after the Phase 7 admin panel, alongside Town Hall (Section 23).
- Added Section 22 (Development Phase Logging Standard): adopted a standing process — any newly-surfaced scope gets logged as its own numbered phase using a fixed template (Trigger / Scope / Dependencies / Schema Impact / Sequencing / Risks / Status), rather than folded loosely into whichever section prompted it. Applied retroactively to Sections 23 and 24.
- Added Section 23 (Town Hall — Community Discussion Subsystem): fully specified per explicit instruction as its own logged phase, not deferred or simplified. Per-cluster community discussion (contractor recommendations, local threads), schema sketched (`townhall_threads`/`townhall_replies`/`townhall_flags`), sequenced after the Phase 7 admin panel since its moderation queue depends on that same review-queue framework. Moderation policy and abuse-prevention approach flagged as undecided.
- Added Section 24 (Direct-to-Operator Financial & Routing Architecture): full technical specification of the PMS/booking integration first mentioned at a high level in Section 10 — Path A (native PMS webhook sync via Cloudbeds/Guesty/RMS, 0% commission, direct merchant settlement) and Path B (manual email/SMS/dashboard routing for operators without PMS software). Schema sketched (`merchant_pms_credentials`, `pms_availability_cache`, `booking_requests`, `webhook_event_log`). Sequencing explicitly locked as the last phase in the build order until told otherwise, reaffirming (not reversing) the existing Phase 6 deferral. Flagged: third-party PMS API access typically requires a partner/certification agreement (external lead time), and Path B has no automatic confirmation loop at scale.
- Confirmed Section 21's reconciliation against `CrawlerIngest.php` (Section 15): the expanded pipeline reuses the existing NAP normalizer and canonical composite hash rather than reimplementing matching logic, and honors existing `field_locks`. Made the confidence-score routing thresholds explicit in 21.5 (≥80 direct merge, 50–79 staged to `crawler_raw_index` for admin review, <50 to `crawler_quarantine`) — same thresholds already implied by `crawler_raw_index.status` in 21.3, now stated against the routing decision itself.
- **Confirmed outgoing transactional email delivery live in production** (Section 20.7): the first real SMTP send via the authenticated BlueHost mailbox configuration was confirmed delivered. This closes the last open item from the Phase 4 milestone — Phase 4 (claim, all four verification tiers, referrals, vouching, account security, merchant/personal dashboards, and email delivery) is now fully confirmed working end-to-end in production. Updated the Development Status checklist accordingly.
- **Delivered and schema-verified the home-location auto-scope fix, correcting an earlier speculative entry.** An initial version of this fix was written and logged before the real `index.php`/`session.php`/`bootstrap.php` source and the live `degraff1_traversence.sql` schema were available, and incorrectly assumed a direct `micro_clusters` join could supply city/state/lat/lon on its own. Against the real files and schema: `api/auth/session.php` now resolves `users.home_cluster_id` → `micro_clusters.primary_zip` → `zip_coordinates` (city/state/lat/lon), returned as `home_location`, defensively wrapped so a bad/missing cluster or query failure degrades to `null` rather than breaking session-check. Because `index.php`'s `checkRealAuthState()` fetch is genuinely async, it resolves *after* `DOMContentLoaded`'s synchronous location-restore block has already run — so the fix applies `home_location` to `currentLocation` from inside `checkRealAuthState()` itself, only when nothing already occupies it (an active quick-profile/session location always wins), and closes+resumes a search that was already parked waiting on a location (`pendingSearchQuery`) rather than leaving the modal stuck open — this is the exact scenario from the original bug report (logged-in user, modal fired anyway). Setting the location alone never auto-fires a search. Quick-profile system and manual "Set Location" override untouched. Delivered, schema-verified, not yet live-tested — logged under Development Status.
- **First live test of the above surfaced the real remaining gap, and it was resolved (Section 20.20):** the modal still fired after login because `users.home_cluster_id` had never been written by anything — confirmed via `degraff1_traversence.sql` (the one real `users` row has `home_cluster_id = NULL`). Built the write side, `user/api/set-home-location.php`, reusing the existing "Set Precise Location" modal rather than new UI: resolves a submitted zip against `micro_clusters` (checking both `primary_zip` and `associated_zips`) and updates `users.home_cluster_id`, no-op (not a clear) on no match. Wired into `index.php` via a new `persistAccountHomeLocation()` call from `handleLocationSubmit()`, `autoDetectLocation()`, and `switchRegion()` — corrected mid-design from the original plan to piggyback on `bindLocationToProfile()`'s call sites, which turned out to be gated on the quick-profile email field and don't apply to real-auth identity. Not yet live-tested end-to-end.
- **Added Section 20.21 (Quick-Profile Removal, Sign-Out Storage Fix, `set-home-location.php` Cleanup — shipped, confirmed working):** removed the old password-less quick-local-profile system from `index.php` entirely (superseding, not reversing, Section 20.12's deliberate decision to leave it untouched — it had become fully redundant once real auth existed), replaced with an accessible real-auth account dropdown; switched storage-tier gating from the removed `isSignedIn()` to `realAuthActive`; decided (via AskUserQuestion) that anonymous visitors now get session-only location/search persistence, no durable local layer. Fixed a real sign-out bug: `index.php` and the dashboard each had their own drifted `logout()`, and the dashboard's never cleared client storage — consolidated into one shared `js/shared-auth.js` `logout()`, included on both pages, using a new shared `window.appCsrfToken` convention. Cleaned up `user/api/set-home-location.php` to use `Response::jsonBody()` instead of manual body parsing, matching the confirmed-real pattern from `api/auth/login.php`. All three pieces confirmed working in production per the user's own testing.
- **Added Section 20.22 (Dashboard HTTP 500 — diagnosis in progress, root cause still unconfirmed):** user reported `/user/dashboard.php` returning HTTP 500 in production. Diagnosed via inspection of the real `dashboard.php` and `Auth.php` sources; formed and then explicitly ruled out a specific hypothesis (`created_at` missing from `Auth::currentUser()`'s return — confirmed present in the real SELECT). No other candidate found by inspection alone. Blocked on the actual PHP error log, which has not been provided. **Logged explicitly as still open** — a later, ambiguous user message did not clearly confirm this was resolved, and it should not be treated as closed without direct confirmation.
- **Added Section 20.24 (Area-of-Interest Schema & Write/Read Rework — shipped, not yet live-tested):** resolved Section 20.23's open architecture question — `zip_coordinates` (comprehensive, matches real search/hub pills) becomes the primary resolution source for a user's area of interest; `micro_clusters`/`home_cluster_id` retained as a secondary, best-effort derived field. Clarified that the "two DB connection mechanisms" were always the same physical database, not a cross-database problem. Shipped: migration 007 (`area_zip`/`area_city`/`area_state`/`area_lat`/`area_lon`/`area_updated_at` on `users`), a rewritten `user/api/set-home-location.php`, a one-line `Auth::currentUser()` SELECT extension, and a rewritten `api/auth/session.php` read side that prefers the direct `area_*` fields over the old cluster-join, with the response contract preserved so `index.php` needs no change. **Caught and fixed within the same turn, before deployment:** an initial draft stored a combined `area_label` string that didn't cleanly decompose into the `{city, state}` shape `session.php`'s real response contract requires — switched to separate `area_city`/`area_state` columns before anything shipped. `switchRegion()`'s no-op bug and registration-time capture remain explicitly deferred, now unblocked in principle but not built.
- **Added Section 20.23 ("Area of Interest" / Location-Persistence Architecture — open question, not yet designed):** user explicitly reframed the location-persistence discussion twice — first to "all three location-setting routes need to reliably persist, and what about international/other-region visitors," then via AskUserQuestion answers to "this is about top-down exploration (local-for-resident vs. destination-for-traveler)" and "registration should capture area of interest, not just current location." Also stated an explicit non-functional requirement: whatever gets built must be "viable at any scale" once the future crawler ingest pipeline expands geographic coverage. **Major architectural discovery, not yet reconciled:** reading the real `nearby_hubs.php` and `search_listings.php` sources revealed the platform has two entirely separate, non-intersecting geography systems — the legacy `zip_coordinates`/`db_config.php` system that actually powers real search and hub pills (comprehensive, no cluster IDs), and the newer `micro_clusters`/`Database.php` system that only the home-location feature uses (curated, 2 real rows, never referenced by hub pills at all). This invalidated an in-session fix proposal (passing a hub pill's cluster ID through to the persistence endpoint) since hub pills carry no cluster ID at all. Confirmed via the real `api/auth/register.php` source that registration creates no session, meaning any registration-time area-of-interest capture needs new server-side design, not reuse of the existing authenticated endpoint. Logged the confirmed, code-certain `switchRegion()` guaranteed-no-op bug (unconditional `zip = ''` before a zip-gated persistence call) as diagnosed but intentionally not yet fixed, since fixing it before the architecture question is resolved risks fixing it against the wrong storage target. International/other-region scope explicitly clarified as a data-coverage problem, not a location-widget problem — out of scope for any persistence fix. **Status: fully scoped, explicitly undecided, correctly not started** — captured in full per the user's explicit request to preserve continuity before an extended absence, rather than guessed at or partially built.

---

## 25. Micro-Cluster / Zip-Coordinates Dual-Layer Reconciliation

**Trigger:** Building cluster-aware hub pills (see Open Gaps below) required resolving Section 20.23's open architecture question — whether `micro_clusters` and `zip_coordinates` needed to be merged, or one deprecated in favor of the other.

**Decision:** Confirmed against the original "Part 8: Platform & Directory Architecture" blueprint (re-uploaded and cross-checked mid-session). The blueprint's own language settles this — Micro-Cluster is explicitly defined as binding **"3 to 5 neighboring towns into a strict local business and municipal pool"** with a fixed `hub:geo-hub:cluster` namespace sized for ~20 continental hubs. This is a deliberately small-N, hand-curated taxonomy tier by original design — it was never intended to scale to zip-level granularity. `zip_coordinates` is a separate, later-built comprehensive resolution layer for radius search and area-of-interest (Section 20.24), never reconciled back into the taxonomy.

**Resolution:** `micro_clusters` remains a permanent taxonomy tier (Hub→Geo-Hub→Cluster) for browsing, hub-pill navigation, and Town Hall's cluster definition. `zip_coordinates`/`area_*` remains the resolution layer for search and area-of-interest. Neither is merged, deprecated, or required to scale to match the other's coverage.

**Schema check performed this session** (against `degraff1_traversence.sql`): the taxonomy already exists relationally — `hubs` (id, slug, name, hub_number), `geo_hubs` (id, hub_id FK, slug, name), `micro_clusters` (id, geo_hub_id FK, slug, name, primary_zip, associated_zips, source, confidence_score). Real data: 1 hub (`ancient-america`), 1 geo_hub (`ancient-borderlands`), and **40 micro_clusters** rows — corrected from Section 20.23's belief of "2 real rows." Of the 40: 2 are `source = 'manual'` (St. Johns, Round Valley, confidence 1.00), and **38 are `source = 'automated_seed'`**, each a single-zip stub named `"ZIP XXXXX (auto-seeded, needs review)"`, created in a batch crawler run on 2026-09-11.

**Explicit decision on gating:** no query-level gating on `source` — all 40 rows are pill-eligible now. Naming/quality cleanup on the 38 auto-seeded rows happens as a pre-launch content pass, not a code filter.

**Open Gaps (not yet built):**
- `nearby_hubs.php` currently couples pill membership to `currentLocation.radius` (Haversine distance over `zip_coordinates`) — backwards per this decision. Rewrite target: sibling-cluster query (`WHERE geo_hub_id = :current_geo_hub_id AND id != :current_cluster_id`), with a second tier querying other geo-hubs in the same continental hub if "+ More Hubs" is expanded. No radius/distance math anywhere in the rewritten path.
- Response shape changes from `{ city, state, distance }` to `{ id, slug, name, primary_zip }` — `index.php`'s `renderProximityHubs()` and the `fetch()` call building the `nearby_hubs.php` URL both need updating to match (no more `radius`/`lat`/`lon` params sent at all).
- `switchRegion()` needs to become cluster-aware: set `currentLocation` from the chosen cluster's `primary_zip`, not raw city/state strings — a materially different function body than the "remove the persist call" fix already shipped (see Development Status), worth doing as one combined pass rather than two.

**Status:** Decided and confirmed. Rewrite implementation not yet started.

---

## 26. `set-home-location.php` / Section 20.24 Discrepancy

**Trigger:** Investigating whether `switchRegion()`'s hub-pill fix could pass a resolved zip to `persistAccountHomeLocation()` required reading the live `user/api/set-home-location.php` source.

**Finding:** The uploaded/live file does not match what Section 20.24 states was shipped. Section 20.24 claims `set-home-location.php` was rewritten to treat `zip_coordinates`/`area_*` fields as the primary resolution/storage path, with `micro_clusters`/`home_cluster_id` as secondary. The actual file:
- Accepts only a strict 5-digit `zip` — no `city`/`state`/`lat`/`lon` input path exists
- Resolves exclusively against `micro_clusters` (`primary_zip`/`associated_zips`)
- Writes only `users.home_cluster_id` — never touches `area_zip`/`area_city`/`area_state`/`area_lat`/`area_lon` or `zip_coordinates` anywhere

**Two possible explanations, neither confirmed:** (1) Section 20.24's described rewrite of this specific file was drafted but never actually deployed, while the other three pieces (migration 007, the `Auth::currentUser()` SELECT extension, `session.php`'s read side) may genuinely be live; or (2) a stale/pre-rewrite copy was uploaded and the real current file matches 20.24's description.

**Why unresolved rather than guessed:** building anything against an assumed-rewritten contract that isn't actually live risks the exact failure mode Section 20.23 already hit once (a fix proposal invalidated once real files were read). This does not block current work — the `switchRegion()` fix (Section 27/Development Status) bypasses this endpoint entirely by removing the persist call rather than feeding it a zip — but should be confirmed before any future task builds against this file's contract.

**Status:** Flagged, unconfirmed, not blocking.

---

## 27. Admin & Business-Access Capability Model

**Trigger:** Building cluster-aware hub pills surfaced that `hubs`/`geo_hubs` have no creation/management path or privilege-tiering at all — any authenticated admin could theoretically create/edit any hub. Resolving that, plus an explicit product question ("can admin also be a user and business listing manager"), required deciding the platform's whole identity/permission model.

**Decision — unified identity, stackable capabilities, not exclusive account types:** One `users` identity remains the single front-end ID for community engagement (favorites, Town Hall, profile). Business-listing ownership and admin access are both modeled as **capability grants** on top of that identity, via join tables — not role flags on `users`, and not separate account types. A single person can simultaneously be a resident, hold `listing_access` on one or more businesses (and be a business's assigned team, not just its sole owner), and hold `admin_access` — all at once, with no exclusivity constraint.

This directly obsoletes `users.role` (`enum('admin','business_owner')`, no "resident" value ever existed) and `users.is_admin` as sources of truth going forward — both retained temporarily per the add-then-migrate-then-drop sequence, not dropped yet.

**Schema shipped (live in production this session):**

```sql
CREATE TABLE listing_access (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entity_id BIGINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  access_level ENUM('owner', 'manager') NOT NULL DEFAULT 'manager',
  granted_by_user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE INDEX idx_entity_user (entity_id, user_id),
  INDEX idx_user_access (user_id),
  CONSTRAINT fk_la_entity FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE,
  CONSTRAINT fk_la_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_la_granted_by FOREIGN KEY (granted_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE admin_access (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  hub_id INT UNSIGNED NULL COMMENT 'NULL = global platform-wide Super-Admin reach',
  tier ENUM('super_admin', 'regional_admin') NOT NULL DEFAULT 'regional_admin',
  granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE INDEX idx_user_hub_tier (user_id, hub_id, tier),
  INDEX idx_hub_user (hub_id, user_id),
  CONSTRAINT fk_aa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_aa_hub FOREIGN KEY (hub_id) REFERENCES hubs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Backfilled `listing_access` from existing `entities.owner_user_id` (`ON DUPLICATE KEY UPDATE` upsert, safe to re-run due to the `UNIQUE` index).

**Two real bugs caught before/during deployment, not after:**
1. Initial draft used non-`UNIQUE` indexes on both tables' natural keys — would have silently broken the backfill's `ON DUPLICATE KEY UPDATE` idempotency on any re-run. Fixed before running.
2. First live migration attempt hit MySQL `#1215 - Cannot add foreign key constraint` — FK columns were plain `INT` while `entities.id` is `bigint(20) UNSIGNED` and `users.id`/`hubs.id` are `int(10) UNSIGNED`. Fixed: `entity_id` → `BIGINT UNSIGNED`, all `user_id`/`hub_id`/`granted_by_user_id` → `INT UNSIGNED`.

**`owner`/`manager` permission split (business listings):**
- **owner:** full authority — edit profile, service-area zips, intent vectors, Town Hall responses as the business, transfer ownership, invite/remove `manager` users, archive/decommission the listing
- **manager:** day-to-day authority — basic info, photos/media, hours, Town Hall engagement as the business; cannot remove the owner, assign new users, or delete/transfer the entity

**`Auth.php` helpers added** (alongside the existing `is_admin`-based `requireAdmin()`, left untouched): `canAdministerHub($userId, $hubId)`, `requireHubAdmin($hubId)`, `isSuperAdmin($userId)`, `requireSuperAdmin()`, `getListingPermission($userId, $entityId)`, `requireListingAccess($entityId, $minLevel = 'manager')`, `hasAnyAdminAccess($userId)`.

**Self-service Super-Admin bootstrap** (see Development Status): `user/dashboard.php` first-item check + `user/api/bootstrap-super-admin.php`, atomic `INSERT...WHERE NOT EXISTS` to close a TOCTOU race a naive count-then-insert would have left open. First real Super-Admin grant made via this flow this session.

**Migration sequencing constraint (explicit, not yet executed):** add-then-migrate-then-drop. `entities.owner_user_id` and `users.role`/`is_admin` stay in place until their real call sites are migrated to `listing_access`/`admin_access` queries. Call-site audit performed this session (see Development Status "Not yet started" — legacy column audit) found `Auth.php` genuinely depends on `is_admin`/`role` (not yet migrated); `businessportal.php`/`TrustScore.php`/`api/ingest.php` do **not** actually reference `owner_user_id` (a prior secondhand claim to the contrary was checked and found wrong) — no migration work needed there specifically for that column.

**Status:** Schema, helpers, and bootstrap flow shipped and confirmed live. `businessportal.php` UI/permission-check rewrite to actually consume `listing_access` (ownership transfer, invite/remove manager UI) not yet designed or built. `Auth.php`'s legacy `requireAdmin()` call sites not yet migrated to the new helpers.

---

## 28. Full Site Page Inventory (Dual-Gateway, Taxonomy Pages, Admin Shell)

**Trigger:** Live inspection of `index.php` confirmed the Dual-Gateway Landing Hero (Part 8, Step 1 — "Get Local" / "Let's Explore" as co-equal entry points) was never built — only a single "Let's Get Local" hero exists, hard-coded as the default landing state. This connects to a broader gap: the platform's entire Top-Down/global-traveler track (Track 2 / Authority Engine, Part 8 Step 4) has zero front-end surface today. Follow-up request: build FPO/rough stubs for every real page the platform needs, including an admin shell "devoid of any anticipated functions."

**Scope — Public/user-facing (7 items, real routes + nav, rough/placeholder content, existing design system):**

| Route | Purpose | Notes |
|---|---|---|
| `/` — Dual-Gateway landing | Replaces single "Let's Get Local" hero with Get Local / Let's Explore split | Get Local branch keeps existing functional search below the fold |
| `/explore` | Top-Down entry stub | Placeholder continental-hub selector; no real editorial content yet |
| `/hub/{hub-slug}` | Macro Hub page | Real data exists (Ancient America); placeholder geo-hub list |
| `/geo-hub/{geohub-slug}` | Geo-Hub page | Real data exists (Ancient Borderlands); placeholder editorial list |
| `/cluster/{cluster-slug}` | Micro-Cluster page — actual Get Local destination | Real data exists (40 rows); placeholder weather/municipal blocks; live directory listings for real |
| `/map` | Interactive corridor map | Static placeholder; no real interactivity yet |
| Editorial article template | Long-form Utility/Experience content w/ `[directory_feed]` shortcode | One generic template; one hardcoded example rendering real listings |

**Scope — Admin shell (structure only, per explicit instruction: no functions wired):**

| Route | Content |
|---|---|
| `/admin/` | Dashboard shell — nav sidebar, placeholder summary cards |
| `/admin/login.php` | **Open decision, not yet resolved:** reuse existing session auth (gated by Section 27's `admin_role` check) vs. a fully separate login surface. Working assumption is shared auth, role-gated routing — not confirmed. |
| `/admin/hubs.php` | Super-Admin tier — table shell over real `hubs`/`geo_hubs` data, no create/edit forms wired |
| `/admin/clusters.php` | Regional-Admin tier — table shell over `micro_clusters` (name, slug, source, confidence_score columns visible), no inline actions functional |
| `/admin/review-queue.php` | Table shell filtered to `source = 'automated_seed'` (real data, 38 rows) — visually present, no approve/rename/reassign buttons wired |
| `/admin/listings.php` | Placeholder pending-listing table, no approve/reject logic |
| `/admin/users.php` | Super-Admin tier — placeholder user table shell |

**Explicitly excluded from this FPO pass — locked/deferred per standing decisions, not overlooked:**
- `/cluster/{slug}/townhall`, `/user/townhall` (Section 23, locked after Phase 7)
- Booking/payment routing pages (Section 24, explicitly locked last)
- Admin panel *functional* logic (review/approve/CRUD) — this phase is shell/structure only; functional build is Phase 7 proper

**Dependency:** Track 2/Authority Engine editorial content system (Part 8 Step 4) doesn't exist yet — `/explore` and the editorial template land on placeholder/stub content, not real content, until that system is separately scoped.

**Status:** Scoped, not yet built. Sequencing between the Dual-Gateway/`/explore` pair and the `/hub/`/`/geo-hub/`/`/cluster/`/`/map` taxonomy-page tier not yet split into separate phase numbers if desired — currently one combined scope.
- **Resolved Section 20.22 (dashboard HTTP 500).** Confirmed via user report that root cause was a BlueHost shared-hosting resource limit (connection-count class issue causing stalled load, manifesting as HTTP 500), not an application defect. No code change required. Both previously-formed hypotheses (including the ruled-out `created_at` theory) are moot.
- **Resolved Section 20.23's `switchRegion()` no-op bug via architecture discussion, not a data fix.** User reframed the underlying question: hub pills serve Bottom-Up "expand from here" exploration (finding resources not otherwise available in a thin local market), while Top-Down/Let's Explore is a separate interest-driven flow that never touches `switchRegion()`. This confirmed hub-pill clicks should stay session-only, never a home-location declaration — removed the `persistAccountHomeLocation()` call from `switchRegion()` entirely.
- **Logged Section 25 (Micro-Cluster/Zip-Coordinates Dual-Layer Reconciliation), decided and confirmed against a re-uploaded copy of the original Part 8 blueprint.** `micro_clusters` is the permanent curated taxonomy tier (small-N by design, per the blueprint's own "3 to 5 neighboring towns" language); `zip_coordinates`/`area_*` remains the resolution layer. Schema check against the real `degraff1_traversence.sql` corrected a prior belief (Section 20.23: "2 real rows") — actual count is 40 (2 manual, 38 automated_seed/needs-review from a 2026-09-11 crawler run). Decided, on explicit instruction: no gating on `source` for pill eligibility; naming cleanup on auto-seeded rows is a pre-launch content pass, not a query filter.
- **Surfaced and flagged a new architecture gap: no privilege-tiering exists for hub/geo-hub creation.** Discussion converged (with an external proposal document reviewed and largely adopted) on a two-part resolution: (1) Super-Admin (platform-wide, 20 hubs) vs. Regional Admin (single hub scope) tiers — logged as Section 27; (2) rejected a proposed full stack migration (Node/Postgres/PostGIS) as contradicting its own single-developer-MVP simplification goal, since the existing PHP/MySQL/BlueHost platform is already substantially further built than the proposal assumed (crawler safety pipeline, HMAC ingestion, and the `micro_clusters.source='automated_seed'` staging mechanism all already exist).
- **User clarified the identity/access model directly**, prompting Section 27's final shape: the `users` table is the single front-end identity; business-listing access is a separate, many-to-many capability (a listing can have multiple assigned users, not just one owner); admin access follows the same capability-grant pattern for consistency. Reviewed and adopted an external design document's `listing_access`/`admin_access` schema and `owner`/`manager` permission split.
- **Shipped Section 27's schema and `Auth.php` helpers, live in production.** Caught and fixed two real bugs before/during deployment: a non-unique-index idempotency flaw in the original backfill design (fixed pre-deployment), and a MySQL `#1215` FK type-mismatch error on the first live migration attempt (`INT` vs. the real `BIGINT UNSIGNED`/`INT UNSIGNED` parent columns — fixed and re-run successfully). Verified via direct grep of the real uploaded files that a secondhand claim about `businessportal.php`/`TrustScore.php`/`api/ingest.php` needing `owner_user_id` migration work was incorrect — none of the three reference that column.
- **Shipped self-service Super-Admin bootstrap**, per user's request to work from their existing account rather than raw SQL inserts. First dashboard-load item now checks `admin_access` row count (zero → bootstrap card; already-admin → panel link; otherwise → nothing). New atomic bootstrap endpoint closes a TOCTOU race identified while writing it (a naive separate count-then-insert would have allowed two simultaneous requests to both succeed). User confirmed their own Super-Admin grant is now live.
- **Confirmed via live `index.php` inspection that the Dual-Gateway Landing Hero (Part 8 Step 1) was never built** — only "Get Local" exists; the entire Top-Down/Authority Engine track has zero front-end surface. Logged as part of Section 28 (Full Site Page Inventory), scoped on explicit instruction to build FPO/rough stubs for every real page the platform needs, including an admin shell with structure only and no functions wired. Full route inventory captured (public/user-facing + admin shell), with Town Hall, booking/payment, and admin *functional* logic explicitly excluded as already-locked/deferred scope, not overlooked.
- **Logged Section 26 (`set-home-location.php`/Section 20.24 discrepancy), flagged and unconfirmed.** The live file doesn't match what 20.24 describes as shipped (still zip-only/`micro_clusters`-only, no `area_*` path at all) — cause unresolved (never-shipped work vs. stale upload), not currently blocking since the `switchRegion()` fix bypasses this endpoint entirely.
- **Logged Section 29 (User-Submitted Places — AI-Vetted Intake), scoped per the fixed phase template.** Surfaced from the Competitive Positioning review's open checklist item (Atlas Obscura model); decided that user-submitted places enter the existing crawler intake path rather than a new one. Registered users submit a place; the submission goes through the same HMAC-signed, dedup-checked ingestion path as crawler data, gets an offsite Tiers 1–3 confidence score, and Tier 4 routes it (high → auto-accept with a "Community-submitted" label, middle → Phase 7 admin review, low → admin review at launch). Submitting creates data only, never ownership — the Crawler/Trust separation (Section 12/16) holds. Schema and exact score thresholds left open pending real submission data; sequenced after the Phase 7 admin panel, no new ingestion infrastructure needed.

---

## 29. User-Submitted Places — AI-Vetted Intake

- **Trigger**: Surfaced during the Competitive Positioning review (Atlas Obscura model) as an open checklist item; decided that user-submitted places enter the existing intake queue and are vetted by the AI crawler, with a confidence score determining routing.
- **Scope Summary**: Registered users can submit a place (name, location pin, category, description, photos, optional URL). Submissions enter the existing ingestion path as a signed payload (HMAC-SHA256, same as crawler ingest) and pass the composite SHA256 dedup check before vetting. Offsite Tiers 1–3 cross-check external sources and return a confidence score (0–100). Tier 4 (BlueHost) routes by score:
  - High (≥ [TBD]) → auto-accepted, published with a "Community-submitted" label until verified.
  - Middle ([TBD]–[TBD]) → Phase 7 admin review queue.
  - Low (< [TBD]) → admin review during the launch period; auto-reject reserved for clear junk (spam, duplicates, outside all hubs) once thresholds are calibrated.

  The submitter's trust score is an input to confidence scoring. The submitter is notified of the outcome (accepted / in review / declined + reason) via Messenger. Submitting a place creates data only — it grants no ownership; listing ownership continues to follow the verification tiers (Crawler/Trust separation holds).
- **Dependencies**:
  - Section 12 (Crawler Infrastructure & Ingestion) — Tiers 1–3 offsite, Tier 4 quarantine/audit.
  - Phase 7 admin panel (review queue UI).
  - Trust score (submitter weighting).
  - `api/lib/Messenger.php` (outcome notifications).
  - Single Home Rule / `micro_clusters` (auto-assigning a primary cluster).
- **Schema/DB Impact** (proposed, not confirmed):
  - Decision needed: store submissions as `entities` rows with a quarantine status, or in a separate submissions table promoted to `entities` on acceptance.
  - New fields either way: `submitted_by_user_id`, `confidence_score`, `routing_outcome`, `review_status`, `reviewed_by_admin_id`, `decline_reason`, timestamps.
  - Watch signed/unsigned `BIGINT` consistency on new FKs (Section 27 lesson).
- **Sequencing Position**: After the Phase 7 admin panel (review queue depends on it); runs through the existing Section 12 ingest path, so no new ingestion infrastructure.
- **Open Risks/Flags**:
  - Thresholds unknown until real submissions are scored — start conservative.
  - Rural places have thin external data → false lows; admin review covers this at launch.
  - Abuse vector: fake places to game rankings or plant competitor listings; rate-limit per user, weight by trust score.
  - Auto-accepted places need a visible "Community-submitted" label to protect trust claims.
  - Nominatim is still called client-side (open item, see "Open and unresolved") — pin-drop geocoding for submissions should use the pending server-side endpoint.
- **Status**: Scoped — not started.

---

## 30. Pilot Market Penetration — Configurable Crawler Sources & Role-Based Context Building

- **Trigger**: Surfaced while designing the St. Johns pilot's go-to-market flow (Competitive Positioning doc's cold-start risk resolution) — deciding operationally how the AI scraper/crawler is actually used to penetrate a target market at deployment, rather than just that it would be used.
- **Scope Summary**: Three related decisions:
  - **(a) Crawl flow for a pilot cluster**: a broad, unfiltered discovery scrape runs first; topics/entities get prioritized from repetitive identification across that broad scrape (an entity or theme surfacing repeatedly across sources is the signal of local significance, not a hand-picked category list); those identified topics then get a narrower, targeted re-scrape/content-generation pass scoped specifically to build full context on each. This supersedes hand-picking cornerstones by category as the default mechanism — frequency does the prioritization instead, with manual admin selection as a fallback (see Open Risks/Flags).
  - **(b) Community-influence identification without named-person entities**: the crawler does not attempt to resolve specific individuals — there is no ownership/verification model in the current schema for a person, only for a business (`entities`/`listing_access`). Instead it follows the "5 Ws and actions" embedded in event calendars, community/group pages, and org directories to build role-based context (City Mayor, Deacon, Local Business Owner, etc.) tied to a function or connection point, not a named individual. Content generated from this context is written around the role/connection point itself; per direction, the real person holding that role is expected to recognize themselves in it, making the claim/join flow intuitive without the platform ever having guessed at or published an unverified name.
  - **(c) Crawler source configurability, new requirement**: admins and `listing_access` holders need the ability to directly add crawl sources — a URL, a scope, and a rationale (why this source; matching the existing audit-trail pattern, `decisions/0002`) — rather than the crawler only working from whatever it's pre-configured or scheduled to reach. Applies at the admin level (seeding a pilot cluster with region-specific sources — county government sites, Census QuickFacts, chamber of commerce pages) and at the listing level (a business owner pointing the crawler at their own additional web presence for verification/enrichment).
  - **Scope granularity, per direction**: a source's natural scope tracks its data granularity against the existing Continental Hub → Geo-Hub → Micro-Cluster taxonomy, not a free-text description. County-level statistical sources (e.g. Census QuickFacts, which describes Apache County as a whole) are a natural fit for Hub-level context, not Micro-Cluster-level — Apache County is Hub 9's anchor, a materially larger footprint than a single Micro-Cluster like St. Johns. A source's `scope` should therefore reference the taxonomy tier it's meant to inform (hub / geo-hub / micro-cluster) plus the specific id at that tier, so an admin adding a source picks the level it actually describes. This also gives Section 28's currently-placeholder Hub page (`/hub/{hub-slug}`) a concrete first real content source it didn't have before — county-level demographic/economic context descending into geo-hub- and cluster-level content as the taxonomy narrows, mirroring the platform's own architecture rather than treating every source as equally local.
  - **(d) Non-geographic scope maps to the existing dual-engine architecture, corrected per direction.** An earlier draft of this section proposed a standalone `topical` scope_tier sitting alongside hub/geo_hub/micro_cluster — the wrong frame. The platform already has the real answer: the Directory ("Get Local")/Discovery ("Let's Explore") dual-engine model. Discovery-engine sources are inherently topic/corridor-driven by the platform's own existing design, and are meant to cross or exceed single-hub boundaries — a source about "international travel" is Discovery-engine content, not a fourth geographic tier bolted onto Directory's taxonomy. A crawl source's scope is therefore first which engine it feeds, then (for `directory`) which taxonomy tier, or (for `discovery`) what topic/corridor context.
  - **Directory's own boundary-breaking, per direction — corrects an overstatement above ("no exception").** Directory isn't rigidly sealed to the taxonomy either; it already has the pattern for this, just not yet named as boundary-breaking. `/directory/`'s existing radius search widens outward in concentric tiers when nearby results run thin (Competitive Positioning doc; the Bottom-Up/"Get Local" model) — an organized, tiered flow (Micro-Cluster → Geo-Hub → Hub), not a fixed wall at the cluster line. The crawler extends the same idea on the ingestion side: it can identify associated relevance — a business functionally tied to a cluster's real community even when it sits just outside that cluster's formal zip/`associated_zips` boundary — and treat that as a new, relevance-driven conceptual boundary rather than the strict administrative one, consistent with Section 25's finding that `micro_clusters` (curated, hand-drawn) and `zip_coordinates` (raw resolution) were never fully reconciled to begin with. The distinction from Discovery's boundary-breaking is in kind, not degree, and corrects an overstatement two sentences prior: Discovery isn't actually unanchored. Discovery roams by topic or corridor, but the Chameleon Filter — the platform's existing intent-vector router, upstream of both engines — is what links that roaming back to a real place, and a real place back to a topic, depending on which route the connection point was entered from (utility/Directory-first or experience/Discovery-first). The real difference isn't anchored-vs-unanchored — both engines ultimately resolve to a real place — it's that Directory's anchor is fixed (the resident's own location, widening outward from there by tier or relevance), while Discovery's anchor is established dynamically by the Chameleon Filter based on entry route and topic.
  - **(e) Borrowed infrastructure as a deliberate bootstrap, not a permanent dependency — refined per direction.** An earlier draft of this section framed self-built-vs-third-party as a binary (reject the search-API approach outright). Per direction, the real intent is a bootstrap: it's fine to run on borrowed infrastructure (a search API, an external index, any third-party source) while the platform's own foundation is still being built — that's how the platform grows and eventually surpasses those original sources, not a compromise to avoid. This matches a pattern already used elsewhere in this spec (Section 20.7 used BlueHost's own mailbox hosting as an explicit temporary bridge for transactional email, against the host's own documented caution, specifically because it unblocked real functionality now while a better solution could come later). The organically-grown, self-owned crawl/index capability is the target end state, not a same-day requirement. This still doesn't mean undirected open-web crawling: per direction, the scraper stays targeted at real content context — an actual Discovery corridor's topic, an actual pilot's targets — rather than being "left to seek and seek," whether it's currently running on borrowed infrastructure or the platform's own. The earlier "unbounded" decision for a broad, non-geographic scrape means no hard result/cost cap is required, not that the crawl is aimless.
- **Dependencies**:
  - Section 12/21 (Crawler Infrastructure & Ingestion, Expanded Scope) — this is new configuration surface on top of that existing pipeline, not a separate crawler.
  - Section 29 (User-Submitted Places — AI-Vetted Intake) — shares the same confidence-scoring/dedup path; a role-based page's claim flow is analogous to Section 29's submission-to-ownership handoff.
  - Phase 7 admin panel — admin-side source configuration UI.
  - `listing_access` (Section 27) — gates which listings a manager/owner can add sources for.
- **Schema/DB Impact** (proposed, not confirmed):
  - New table, e.g. `crawler_sources`: id, url, engine (directory | discovery), scope_tier (hub | geo_hub | micro_cluster — required and only meaningful when engine = directory), scope_id (the hub/geo_hub/micro_cluster row that tier resolves to), discovery_context (the topic/corridor driving the source — required when engine = discovery, in place of a geographic scope_tier), discovery_boundary (nullable — an optional anchoring hub/region id, result-count cap, or cost/time cap on a discovery-engine source; null = intentionally unbounded, per direction not a requirement), rationale, added_by_user_id, added_by_type (admin | listing_access), target_entity_id (nullable, for a listing-scoped source), status, created_at.
  - Topic/repetition tracking for (a): some mechanism to count or score how often an entity/theme recurs across a broad scrape before a targeted re-scrape triggers — not yet designed, flagged here rather than assumed.
  - No new schema needed for (b) — role-based context lives in generated content (Section 21's narrative pipeline), not as a new entity type; explicitly rejected creating a "person" entity to avoid an unbuilt ownership/verification model for individuals.
- **Sequencing Position**: Needed before the St. Johns pilot's crawl-and-content phase can run for real. Sits alongside Section 29; depends on Section 21's narrative pipeline, which already exists. Admin-side source-configuration UI naturally sequences with the Phase 7 admin panel; a scoped-down manual (CLI or direct DB) version could unblock the single pilot cluster sooner if the full UI isn't ready in time.
- **Open Risks/Flags**:
  - Real Apache County data (census.gov QuickFacts, pulled 2026-09-26) shows only 60.0% of households have a broadband subscription and a 29.3% poverty rate — both bear directly on this plan: a meaningful share of St. Johns residents may not reliably reach online content at all, which tempers how much weight "visitors reaching content" (stage 1 of the pilot's success ladder) can carry alone; median household income of $41,438 is a real headwind against paid-tier conversion, reinforcing why heavier and longer reliance on the promotional/trial tier mechanism (`decisions/0034`) is realistic for this market, not just a nice-to-have.
  - The same data shows only 442 employer establishments in the entire county, not just St. Johns — hard confirmation, not just directional belief, that trade/service inventory really is scarce here, supporting the cornerstones-first decision already logged in the Competitive Positioning doc.
  - Repetition-based topic identification needs a minimum scrape volume to produce a meaningful signal — a very small town could show flat, non-repetitive results simply because there isn't much to find, not because nothing matters; worth a fallback (admin manually seeds known cornerstones) if the repetition signal comes back too thin.
  - Role-based content risks looking wrong or stale if the person actually holding a named role (Mayor, etc.) turns over before anyone claims it — needs a lightweight way to flag/refresh role-based content rather than treating it as permanent once generated.
  - Open source-authorization question: does adding a crawl source (especially an arbitrary URL from a `listing_access` holder) need admin approval/review before the crawler acts on it, or is it trusted at intake and only reviewed if something goes wrong — not yet decided.
  - An intentionally unbounded Discovery-engine scrape still runs through the same paid offsite compute (Tiers 1–3) — with no cap set, cost is bounded only by whoever is watching the run, not by the system itself. Real-time cost/progress visibility (a running total, a manual stop) is worth having even though a cap isn't required — not yet designed.
  - The bootstrap-then-surpass approach in (e) still needs an actual transition point decided eventually — at what point (index size, confidence in the platform's own crawl coverage, or a specific milestone) does reliance on borrowed infrastructure start winding down in favor of the platform's own — not yet decided, and not urgent before the pilot, but worth not leaving indefinite by default.
- **Status**: Scoped — not started.
