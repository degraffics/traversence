# Traversence — Functional Requirements Document (FRD)

Sep 25, 2026 · @Jason

## 1. Purpose & How to Read This Document

This is Traversence's functional requirements document — the "what to build" layer, sitting one level below the [Traversence BRD](https://claude.ai/artifact/VKcDc5oL9hFYLqNBPJD3SL), which covers the mission, business model, and commercial structure this document exists to serve. Where the BRD explains why the platform exists and how it makes money, this document lists what the platform actually does, organized by build tier rather than by business construct, since tiers reflect real sequencing dependencies (documented in the `decisions/` ADR corpus) rather than an idealized feature grouping.

Traversence is built around four primary constructs — **Directory** (presence), **Discovery** (reach), **Connect** (relationship), and **Market** (transaction) — built deliberately in that order (`decisions/0030`). This document's tiers cut across those constructs chronologically: Tier 1 is live today; Tier 2 is active development, itself broken into dependency-ordered sub-phases 2.1 through 2.8; Tier 3 is future scope. Every feature below should pass the Charter's own test before it ships: does it streamline discovery or foster natural connection, or does it start to feel like a stiff corporate directory? If the latter, it doesn't belong here regardless of tier.

This document is a functional repackaging of `prd.md` and the `decisions/*.md` architectural decision record corpus that backs it — the ADRs remain the authoritative detail on any point where this summary and an ADR seem to disagree; this document should be corrected to match, not the other way around. SJ Portables is this platform's first real-world proving ground for the Market construct (Tier 2.8) specifically; its own separate BRD and FRD document how a real dealer inventory system (ShedSuite) plugs into the general mechanics defined here, and are meant to be read side by side with this pair to surface gaps in either direction.

## 2. Tier 1 — Live Today

- Resident directory search (bottom-up, radius-based) — functional on the current homepage.
- GBP audit/optimization landing page.
- Auth flows: login, register, forgot/reset password, email verification, email-revert.
- Personal account dashboard, including the Super-Admin bootstrap/panel-link block.
- Merchant management dashboard (`businessportal.php`).
- Tier 1 self-confirm OTP verification for listing claims.
- Core `/api/` infrastructure: bootstrap, database layer, crawler ingestion, auth endpoints, search/suggest/categories services.
- **Public claim entry point (`/claim`)**, live since 2026-09-23 (`decisions/0015`), wired to the real `listing/api/claim.php` OTP contract.
- **Admin Claims review queue (`admin/claims.php`)**, live since 2026-09-23 (`decisions/0016`); the rest of the admin sub-tools remain unbuilt — see Tier 2.6.
- **Registration compliance hardening:** home-location capture and the international registration gate (`decisions/0027`), and the DSAR export/erasure pipeline (`decisions/0026`) they depend on for the deletion half, with the jurisdictional reasoning behind both in `decisions/0025`.

Everything above is part of the MVP Launch Line (`decisions/0021`) — already shipped, not a launch dependency still pending.

## 3. Tier 2.1–2.3 — Foundational, Directory/Discovery Loop, Content Pipeline

**MVP Launch Line (`decisions/0021`, Accepted):** everything in Tier 1, plus Tier 2.1 below, is required before public launch. Tier 2.2 onward is deferrable to a post-launch roadmap, with one hard rule: if Tier 2.3's AI article pipeline ever ships before launch, its HITL review counterpart (Tier 2.6.1/2.6.2) has to ship in the same release — features are never split across the launch line if they form one intake-and-completion loop ("No Split Loops"). Launch itself is a readiness threshold ("as soon as the IN list is genuinely working and deployable"), not a calendar date.

**Tier 2.1 — Foundational, ship first (part of the MVP Launch Line):**

- Public business detail pages (`/listing/{slug}`) — the tier's true dependency root.
- Macro Hub, Geo-Hub, and Micro-Cluster landing pages — the tier's other dependency root; real data already exists (Micro-Cluster has 40 rows).

**Tier 2.2 — Completes the core Directory/Discovery loop (depends on 2.1):**

- Dual-Gateway landing page (Get Local / Let's Explore split).
- Chameleon Filter dual-behavioral content adaptation between `/directory/` and `/discovery/`.
- Top-down "Let's Explore" narrative entry point.
- Interactive corridor map.

**Tier 2.3 — Content pipeline (depends on 2.1's business detail pages):**

- Editorial article template with `[directory_feed]` shortcode.
- AI-generated, per-listing variant articles — the scraper-to-article pipeline: a partner's catalog is ingested, drafted into one article per product/listing variant, HITL-reviewed, then published into `/discovery/` with traffic routed back to the listing. This is the technical mechanism behind the Authority Engine described in the companion BRD's Business Model section, and behind the tiered AI-article quotas (5/25/uncapped) in its Market Structure section.

## 4. Tier 2.4–2.6 — Safety, Social/Engagement, Admin Sub-Tools

**Tier 2.4 — Safety infrastructure, sequenced ahead of the social features it protects, not after:**

- User-side safety controls: block, filter, and report/request-removal of unwanted engagement. Public content is attributed to a visible front-end profile, so this mitigation has to exist before Tier 2.5 ships the features that increase that exposure, not after.

**Tier 2.5 — Social/engagement features, depend on 2.4 being live first:**

- User profile feeds and the connection/watch mechanics.
- Person search — find a specific user by name/handle to send a connection request, matching against a public profile/display name only, never an internal user ID.
- The Centralized Communications Center (cross-entity messaging) — this is also the routing mechanism the Market construct (Tier 2.8) reuses for its "connect" action, rather than a separate messaging system.

**Tier 2.6 — Remaining admin sub-tools, internally sequenced, not a flat bucket:**

- Admin portal shell — already live as a real placeholder; every unbuilt sub-tool below shows a shared "Coming Soon" placeholder, and every route sits behind the real admin-guard from its first commit, stub or not.

1. `admin/listings.php` (pending-listing review) — the crawler pipeline cannot turn on for real new ingestion until this exists.
2. `admin/review-queue.php` (automated-seed cluster review) — the other half of the AI article pipeline's hard-coupling condition.
3. `admin/hubs.php` (hub/geo-hub management).
4. `admin/clusters.php` (cluster management) — can safely wait; structural, not content-gating.
5. `admin/users.php` (user management), last — real moderation levers already work through their own existing endpoints; this is a convenience console over mechanisms that already function, not a missing capability.

## 5. Tier 2.7 — Communities & Groups

Not yet schedulable — needs its own technical-design pass before it can even be sequenced, tracked here so it isn't silently dropped or silently assumed next.

User-created, charter-governed community spaces: creation gated by trust score, penalty history, and structural creation friction; three-tier guest/registered/member visibility; join-gated threads; a per-community, user-authored charter distinct from the platform's own Charter document. The Level 1/2/3 user-escalation ladder (peer self-serve → Group Charter Steward → System-Level Admin) is the associated conflict-resolution mechanism. No schema or API contract exists yet — that design pass is itself the prerequisite, not just "get to it eventually."

## 6. Tier 2.8 — Market (Fourth Construct)

Sequenced last, deliberately bare-bones, per `decisions/0030` (four-construct build order) and `decisions/0031` (post-and-connect launch model).

**Launch model:** post-and-connect, no transaction processing. A seller (personal or business) posts what they have; an interested party takes a "connect" action; negotiation, payment, and fulfillment all happen off-platform. Traversence never becomes a payment-processing party at this stage. Minimum real feature shape (Facebook Marketplace as the UX reference): a browsable/filterable post grid, location + radius filtering reusing the existing Directory search, category browsing, a "Create new listing" posting flow, personal Buying/Selling dashboard views, and a "connect" action routed through the existing Communications Center rather than a checkout flow. No engagement-ranked or paid featured placement — post visibility follows the same Trust-Weighted model as everywhere else. Launch one side first (B2C, businesses posting to consumers) rather than all of C2C/B2C/B2B/D2U at once.

**Item detail page:** title, price, "listed \[when\] in \[city, state\]," a photo gallery, structured fields plus free-text description, a separate "Seller details" view, and the connect action surfaced twice (a primary Message button and a pre-filled inline quick-message composer). No Buy/checkout/quantity control. A personal (C2C) post shows only an approximate area, never a pinpoint address; a business posting through its verified listing can still show its real address.

**Quote and inventory mechanics (`decisions/0032`):** Quote is a real stateful object (Requested → Accepted/Rejected), not a message; completion requires dual confirmation (both seller and buyer) before an item is marked sold, never a unilateral seller self-report; a completed Quote is the platform's first review/rating anchor point. Personal sellers get a streamlined single-item flow; business listings get a tiered multi-item catalog, capped at 250 items per listing, full stop — no account-level or combined/group cap. Multi-location inventory management is Strategic/Cornerstone-only, requires an explicit **Location Group** link (`decisions/0036`) gated to the listing's owner or a single owner-designated **Executive**-level manager (`decisions/0037`), and each linked listing individually holds its own Cornerstone subscription (`decisions/0035`) — linking has no effect on the 250-item cap, which stays per listing whether linked or not.

**Delegated roles and ownership (`decisions/0037`, `decisions/0038`):** below manager, a listing can have configurable delegated roles — the owner (or designated Executive) configures exactly which capabilities a role has, rather than picking from a fixed named-role menu; no role below manager ever gains linking or role-creation authority. Ownership can never be removed, only transferred to another user who must explicitly accept; the Subscription's financial responsibility transfers with it, and the incoming owner's choice about existing delegates wins on disagreement with the outgoing owner.

**Pricing model typing and the External Source plug-in contract (`decisions/0039`):** catalog items carry a `pricing_model` tag rather than one universal cost/markup shape — `cost_markup` (percentage/flat-dollar on cost), `cost_margin` (profit as a percentage of retail price, never a synonym for markup), `landed_cost_plus` (shipping and platform/referral fees folded in before a markup or margin target), `variant_priced` (cost and price computed per attribute combination, resolving the platform's earlier single-SKU-only gap), `bundle_priced` (a multi-item combination priced as its own object), `build_to_order` (no static price; resolved externally at configuration time), and `mirrored` (fully external, read-only price). Any listing whose inventory or pricing truth lives in a third-party system plugs in through a generic **External Source** contract — sync cadence, field mapping, `pricing_model` tag, order-hand-off mechanism, inbound webhook contract — rather than bespoke per-integration code. **SJ Portables/ShedSuite is this contract's first real, built instance**; a future OTA/channel-manager integration on the Discovery side of the platform is a named future application of the same contract, documented in the SJ Portables FRD rather than repeated here.

**Delivery Method contract and buyer-proposed/staff-verified routing (`decisions/0040`):** delivery is always a line on a Market order, priced through a `delivery_method` tag distinct from the item's own `pricing_model` — `carrier_rate` (real-time USPS/UPS/FedEx lookup), `flat_distance_rate` (a merchant-configured free radius, per-mile rate, and optional base fee, never a platform-fixed number), `freight_ltl` (oversized/palletized freight, staff-priced), and `manual` (general fallback). A Quote can now be created with one or more lines left unpriced — most often delivery — and the buyer can't reach Accepted until every line carries a real value, resolving a gap where the Quote model above assumed an item's total was already known at Requested. Real road-distance routing separates the map/pin-drop UI from the routing engine supplying actual route geometry; a buyer can propose the delivery route by dragging waypoints at intake, and staff verifies or directly corrects it (waypoint-dragging only, never freehand) before the delivery line is priced and locked to the Quote.

**Still needed before this is buildable:** real schema (an item/offer-listing table distinct from `entities`, a `quotes` table, a `location_groups` table, the Executive/delegated-role permission structure, the ownership-transfer mechanism, the `pricing_model` field and its per-model inputs, the External Source contract's own schema, and the `delivery_method`/route-verification schema per `decisions/0040`) and its own technical-design pass — the model itself is decided; the schema build-out is not yet scheduled. Sequencing follows Directory (presence), Discovery (reach), and Connect (relationship); full in-platform payment/merchant-of-record processing is explicit future expansion, not part of this tier.

## 7. Tier 3 — Future Scope

- Public informational pages: services, FAQ, careers.
- E-commerce & subscription infrastructure (`pricing.php`, Stripe subscription processing) — see the companion BRD's Market Structure section, currently the commercial skeleton this would implement.
- Advanced integrations: direct-to-operator webhooks, automated booking extensions. External consumers (partner APIs, webhooks) should receive app-scoped/contextual identifiers, never a raw internal user or entity ID — the same principle behind profile-based public authorship, extended to third-party integration surfaces.
- **Website Builder — offsite static export/redeploy (`decisions/0033`):** a client who wants their own hosting/domain gets a generated deployable site bundle instead of a live API — Traversence stays the sole admin/write path for inventory, content, and communications; the offsite site is a one-way generated artifact, redeployed manually or on an auto-sync schedule. Its inquiry/quote form writes back to the Communications Center and Quote workflow through one lightweight, app-scoped endpoint, not a full public API. The generated site carries a "Powered by Traversence" attribution/backlink by default, for Traversence's own aggregate SEO value — removable on request for Cornerstone tier only.
- **Personalized Insight & Productivity Engine** *(placeholder, not yet designed)*: per-user pattern recognition over a registered user's own activity, meant to surface customizable solutions across the Resident/Traveler/Community tracks. The technical substrate already exists (every user's activity already carries attribution through the telemetry pipeline without being flattened), but what an "insight" actually is, where it surfaces, and its interaction loop remain undefined pending a dedicated design pass. Its consent posture is already resolved: `users.ai_personalization_opt_in` defaults to off, captured at registration, well ahead of the feature itself existing.

## 8. Cross-Cutting Requirements & Open Items

**Sequencing rule ("No Split Loops"), `decisions/0020`/`0021`:** the launch line, and Tier 2's internal sub-phase order, are drawn by real dependencies, not preference — a feature that forms one intake-and-completion loop with another (the crawler pipeline and its admin review queue; the AI article pipeline and its HITL review) is never split across a launch or sequencing boundary. The same sub-phase-tagging method is the standing approach for any tier that grows past a handful of items.

**Privacy & compliance — resolved:** GDPR is out of scope on intent/targeting grounds, CCPA out of scope on a revisitable threshold basis, and cookie consent is likely not triggered at all, per a direct code audit (`decisions/0025`). The DSAR export/erasure pipeline (`decisions/0026`) and the registration-time international gate (`decisions/0027`) operationalize that position.

**Consent dependency — resolved:** user-contributed content is de-identified by default for AI grounding; the narrower case (identity actually attached to AI output) needs an explicit control that defaults to opt-in/off (`decisions/0010`). Any future feature drawing on identified user behavior (e.g. the Tier 3 Personalized Insight & Productivity Engine) must build that opt-in control as its foundational layer before the feature logic itself, not bolted on after.

**Data-minimization:** any pipeline drawing on raw user activity (the AI topic-extraction pipeline, the Tier 3 Insight Engine) follows a classify-at-intake, abstraction-out storage rule (`decisions/0011`) — raw activity detail is never what's stored or surfaced back to a user, only classified, frequency-gated abstractions.

**Open:** the actual commerce model for Market beyond post-and-connect (full merchant-of-record vs. the narrower payment-free framing) needs its own ADR and design pass before Tier 2.8 has more than a sequencing placeholder — see `future-considerations.md`. The External Source contract's own schema (source configuration per listing, field-mapping definitions, hand-off and webhook shapes) is named in `decisions/0039` but not yet designed. Cross-reference: full route-by-route build status lives in `routes.md`, which stays the single source of truth for routing rather than being duplicated here.

**Reading this alongside SJ Portables:** the SJ Portables BRD and FRD apply this document's Tier 2.8 mechanics against a real dealer's business (ShedSuite-sourced shed inventory alongside natively-priced non-shed inventory). Discrepancies between what this document assumes and what SJ Portables' concrete FRD actually needs are the intended signal to correct one document or the other — not a sign either was wrong to begin with.
