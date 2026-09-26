# Traversence: Commercial & Partner Framework

*Version: 2026-09-22*
*Governance tier: Commercial. The Charter (`charter.md`) explicitly excludes pricing and business models from itself and designates this file as their home.*
*Primary sources: "Traversence Part 6: Scaling and Projections" (pricing tiers, revenue targets, operating costs) and "Part 2: The Business Overview, Ecosystem, Strategy, & Services" (what each tier includes, White-Glove services, the operating philosophy).*

**Industry classification, per direction, 2026-09-24 — corrected same day.** An initial "Media & Entertainment" label was wrong and has been replaced with Jason's own description, given directly: **Traversence operates primarily as a platform/directory and business network architecture — focused on local business clusters, listings, and service categorization — rather than a traditional media and entertainment company.** This is the description to use consistently wherever a form, registration, ad platform, or third-party integration asks for an industry type or category (e.g., a payment processor, an ad account, a business directory listing for Traversence itself), rather than reaching for "Media & Entertainment" just because the platform also carries editorial/Discovery content — that content is the Connection Layer's on-ramp into the directory (§0 above), not the business's primary category. Noted here rather than in `charter.md` since it's a business-facing classification fact, not mission/vision language, matching this file's role as "where pricing and business models live." Where a specific form only accepts a single short category term rather than a description, the fit is **NAICS 519130 — "Internet Publishing, Broadcasting, and Web Search Portals" — confirmed by Jason, 2026-09-24** as the code to use for that purpose, not just a suggested mapping anymore.

## 0. Business Model Philosophy: The Content-to-Commerce Engine

Three linked stages, replacing ad-driven networks and commission-taking OTAs:

- **Inbound Authority (the magnet) — the Authority Engine:** narrative content exploring a region's heritage, culture, and commerce captures organic search traffic from travelers seeking the deep story of a place, not just a transaction. Anchored specifically in **the Living Geography** (`brand.md` §2) — the platform's core narrative device — rather than generic regional-history content.
- **Contextual Discovery (the bridge):** story-driven guides lead readers from editorial content into the Regional Business Directory, rather than forcing product placement into the narrative.
- **Direct-to-Operator Commerce (the conversion) — the Conversion Engine:** traffic routes straight to the partner's own ecosystem — no centralized booking middleman, no commission taken, 100% of margin and customer ownership stays with the operator. Named to parallel the Authority Engine below; both names are established brand terminology (`brand.md` §2, the Nationwide Narrative framework).

**One duality, not two.** The three stages above are two equal-weight, symbiotic halves of a single mechanism, not three separate concepts. Editorial storytelling (Inbound Authority) *is* the Top-Down / "Let's Explore" **Discovery** side of the platform's Chameleon Filter routing (`decisions/0003-chameleon-filter-routing.md`, `architecture.md` §4); the Regional Business Directory (Direct-to-Operator Commerce) *is* the Bottom-Up / "Get Local" **Directory** side. An earlier draft of this document described a separate "Dual-Engine Architecture" (an Authority Engine plus a Partner Content Ecosystem) as though it were a distinct business-model concept alongside the Chameleon Filter's Directory/Discovery split. It wasn't — both were describing the same underlying duality from two different angles, one from the business side and one from the architecture side. "Dual-Engine Architecture" is retired as an independently named concept; the Directory/Discovery duality is the one duality, and this section describes how it generates the business model rather than sitting alongside a second architecture layer. **Where this duality actually operates geographically — the Editorial View (Authority Engine) and Directory & Utility View (Conversion Engine), and which of `architecture.md` §22's Continental Hub / Geo-Hub Zone / Micro-Cluster levels each one runs at — is pinned down in `architecture.md` §22, not repeated here.**

**Contextual Discovery (the bridge) has a name: the Connection Layer.** The mechanism that connects Discovery content to Directory listings — surfacing the right directory entries in the middle of an editorial narrative — is the platform's **Connection Layer**, named for the Charter's founding "Living Connection Points" language. It is not a third duality alongside Directory/Discovery; it's the bridge between the two, made concrete. Starting at the **Strategic** tier, partners gain direct ability to contribute, publish, and amplify their own stories, seasonal updates, events, and media within the platform — channeling Discovery's link equity into Directory categories and partner profiles via that same Connection Layer.

## 1. Pricing Tiers

| Tier | Price | Target Mix |
|---|---|---|
| Regional Business Directory | Free | — (every claimed business, by default) |
| Core Partner | $19/mo | 70% |
| Strategic Partner | $79/mo | 20% |
| Cornerstone Partner ("Complete Inclusion") | $249/mo | 10% |

**Resolved (2026-09-22):** paid tiers sell tools and support depth, never directory ranking. Visibility stays entirely earned via the Trust-Weighted model in `architecture.md` §7, for free and paid partners alike. See `decisions/0007-paid-tiers-sell-tools-not-placement.md` for the full decision record — this replaces the "priority placement / category leadership / priority routing" language the source business documents originally used.

**What each tier includes:**

- **Free — Regional Business Directory:** a fully operational, self-serve marketplace listing. See the Free Tier Specification immediately below — it's detailed enough to warrant its own breakdown rather than a one-line summary.
- **Core Partner ($19/mo):** enhanced profile, direct map integration, and standard AI-engine content assistance — this is the first tier with any AI-generation capability at all; Free has none (see below). Unlimited profile-level assistance (business-description drafting, basic photo/metadata optimization, not counted against the article quota below), plus **up to 5 AI-generated catalog articles/month** via the scraper-to-article pipeline (`architecture.md` §25) — one article per distinct product/listing variant, same mechanism as every tier, just capped lower. **Access level: self-service AI-assist with self-created publication access** — the partner (owner/manager, `listing_access`) generates and publishes their own content themselves, with no Traversence admin or White-Glove involvement in the workflow at this tier; that support starts at Strategic. Eligible to add the White-Glove support options below.
- **Strategic Partner ($79/mo):** everything in Core, plus expanded AI-engine access — **up to 25 AI-generated catalog articles/month**, plus **up to 4 custom partner content pieces/month** (seasonal updates, event posts, on top of the catalog-article quota) — the "Connection Feature" (a co-created Owner Spotlight Interview), rich media integration, White-Glove Setup, group-management support for operators with more than one listing, and ongoing quarterly feature updates. **Access level: adds admin/White-Glove-supported content creation and publication** on top of the same self-service tools Core already has — Traversence's own team now helps contribute, publish, and amplify the partner's stories and updates, rather than the partner working the AI-assist tools alone. Multi-location operators on this tier bill under the volume discount schedule in §10, not Core — Core has no multi-location path (§10.1).
- **Cornerstone Partner ($249/mo):** full AI-engine access across the partner's entire catalog — **no monthly article-count cap**; every distinct product/listing variant in the catalog gets an article, naturally bounded by the size of the partner's own catalog rather than an arbitrary number, regenerated automatically when the underlying catalog item changes — plus unlimited custom partner content pieces (highest content-generation ceiling), dedicated group-management support for multi-location operators, full content contribution rights, and complete White-Glove Support (§2 below). **Access level:** the same admin/White-Glove-supported publication as Strategic, at the platform's deepest support tier. No ranking, category-leadership, or routing advantage over a free or Core listing with a comparable trust score. Multi-location billing follows the same §10 schedule as Strategic.

**Tiered support level is a real, distinct axis from the article-volume quotas above, not a restatement of them, per direction 2026-09-24** — this matches the original source language (`Part_2_Business_Overview.docx`: "Starting at the Strategic Partner tier and above, members gain the direct ability to contribute, publish, and amplify their own stories... supported by our white-glove team"). Two different questions: *how much* AI-generated content a tier can produce (the 5/25/uncapped quotas), and *who does the work of creating and publishing it* (self-service alone at Core; admin/White-Glove-supported starting at Strategic).

**Which content this support-level axis actually governs is now settled by `decisions/0005`'s System-Created vs. Self-Generated Partner Content amendment (2026-09-24) — see that ADR for the full reasoning, not repeated here.** In short: a partner's profile fields, seasonal updates, event posts, and Owner Spotlight Interview (whether self-service at Core or White-Glove-assisted at Strategic/Cornerstone) are Self-Generated Partner Content — published on a fast, automated safety/legal/anti-spam check, never `decisions/0005`'s Administrator sign-off, regardless of tier or how much staff assistance went into drafting it. The AI-generated catalog-article quotas above (5/25/uncapped) are the one thing on this page that stays System-Created — every one of those articles still passes through the unchanged staging-and-sign-off gate (`architecture.md` §25 step 3) before publishing to Discovery, at every tier. "Self-created publication access" at Core and "admin/White-Glove-supported" at Strategic+ both describe the Self-Generated side of that line; neither one touches the catalog-article pipeline's gate.

**These figures (5 / 25 / uncapped) are first defaults, not tuned numbers — same status as every other unvalidated figure already in this document set** (`decisions/0024`'s 80%/50% classifier thresholds, §10.2's volume-discount bands). They're picked to scale roughly with the tier pricing ratio ($19 : $79 : $249) and to keep the mandatory per-draft Administrator sign-off (`decisions/0005`) realistically staffable at each tier, but no real admin-review-throughput data exists yet to size them precisely against — revise directly in this bullet list once usage patterns exist, matching `decisions/0017`'s "conservative, revisit once real data exists" posture for the same kind of first-draft number.

### Free Tier — Full Specification

The Free tier is a real, fully operational product, not a stripped-down teaser — it's meant to be enabling, not a wall businesses hit immediately.

**Marketplace, catalog & media base:**

- **Core inventory hub** — a fully operational, self-serve marketplace catalog where operators manually create, organize, and host their product items and variants.
- **Manual, user-generated publishing** — operators publish and manage all listing content entirely through manual data entry, retaining full control over their storefront presentation.
- **Media & asset hosting** — a baseline storage quota for product images, photos, and visual brand assets to support catalog display.
- **Organic discovery presence** — listings populate directly into the platform's standard directory and marketplace discovery feeds, giving baseline consumer visibility without any automated search acceleration.

**Operational & usage boundaries.** Treated as a **file-count limit first, storage-size limit second** — this matches how Bluehost (the current host, `decisions/0004`) actually enforces "unlimited" hosting in practice: real inode/file-count ceilings apply even on nominally unlimited plans. The working planning ceiling is **200,000 files platform-wide** (the more conservative of two figures found in Bluehost's own documentation), not a per-account number — catalog item counts and asset sizes stay sensible baseline minimums within that shared ceiling, enabling standard self-serve operation rather than forcing an artificial wall. As usage approaches that ceiling, a cleanup/retention process removes outdated files rather than raising the ceiling reactively — candidates include rejected or abandoned HITL-quarantine drafts (`decisions/0005-hitl-ai-guardrails.md`), orphaned scraper-corpus data left behind by a discarded topic, and superseded re-scraped snapshots once a fresher pass supersedes them.

**Consumer-to-operator communication:** a foundational messaging/inquiry framework lets consumers reach operators directly, with throughput capped at a baseline level suited to smaller or emerging operations.

**Strict exclusions:** **zero AI capabilities** — completely decoupled from the scraper-to-article pipeline (`architecture.md` §25–§26), dynamic content generation, and AI-driven enrichment of any kind; and **zero admin support** — strictly self-serve, with no administrative setup assistance or admin-managed content propagation.

**The upgrade path:** as an operator's inventory, media needs, communication traffic, or content ambitions grow, higher tiers are the natural next step — upgrading unlocks automated AI draft generation, deeper marketplace tools, expanded communication routing, and dedicated admin-assisted support, all excluded at Free by design.

## 2. White-Glove Support & Partnership Services

Positioned explicitly as partnership, not vendor/agency extraction: "we do not act as an outside vendor or an extractive marketing agency looking to lock operators into arbitrary retainers." The model is framed as mutual — the platform thrives when the region thrives — and every service is meant to protect operator sovereignty and margins while giving back time, not create dependency on a retainer.

**Cornerstone ("Complete Inclusion") core deliverables**, in order of deployment impact:

1. **Primary Digital Presence & Gateway Architecture** — Traversence serves as the partner's primary digital home and SEO funnel, capturing high-intent traffic from the Authority Engine and routing it into the partner's own ecosystem, replacing the need for a separate standalone website.
2. **Monthly Video Production & Distribution** — short-form video produced and distributed monthly across Traversence's own network channels.
3. **Local Search Dominance & Optimization** — hyper-local SEO and metadata work aimed at outranking distant corporate aggregators.
4. **Practical Creative & Display Assets** — collateral, custom QR codes, and print-ready signage bridging the physical location into the digital profile.

**Optional add-on — Google Maps & Local Place Management** (available to Core Partners and above):

- **Setup:** $125 one-time fee per location (profile audit, data verification, category engineering, coordinate locking).
- **Recurring:** $50/mo per location (ongoing optimization, Map Pack monitoring, photo gallery curation, review/Q&A management).
- **Multi-location:** additional physical locations or branches incur proportional setup and monthly fees.

This add-on is the one place billing is explicitly defined as per-location. The base Core/Strategic/Cornerstone subscriptions aren't stated as per-location or per-business anywhere in the source material — still an open question for a business with multiple sites.

### Sales & Partner Onboarding Framework, per direction, 2026-09-24

- **Writing Partner Pitches:** when pitching local businesses (lodges, outfitters, cafes, artisans), lead with the Nationwide Narrative (`brand.md` §2) to show why Traversence beats a traditional OTA or a generic search listing — not just a map pin, but embedding the enterprise into the Living Geography's unbroken, millennia-old commercial lineage. This is the sales-facing use of the same competitive-positioning claims in §9 above (Contextual Belonging, Escaping Commodity Pricing).
- **Tailoring Zone Messaging:** onboarding tone shifts by regional theme — in **Pre-Colonial Heavy Zones**, emphasize the business as part of an ancient archive of regional trade; in **Frontier & Industrial Transition Zones**, position the business as a modern keeper of the frontier highway, standing against corporate consolidation. This is a thematic/messaging overlay, distinct from the operational Continental Hub → Geo-Hub Zone → Micro-Cluster geography in `regions.md`. **Resolved, 2026-09-24:** `regions.md`'s "Thematic Messaging Overlay" section classifies all 15 of the Ancient America pilot's real Geo-Hub Zones, including a third mode below for the one that doesn't fit a binary read.
- **Dual-Layer Zones get a third messaging mode, not a forced pick, added 2026-09-24:** for a zone classified **Dual-Layer (True Hybrid)** in `regions.md` (currently just High Plateaus & Canyon Gateway), neither Pre-Colonial Heavy nor Frontier & Industrial Transition messaging alone is honest — the pitch instead names both threads explicitly and frames the partner as sitting at the point where they meet (e.g., an outfitter on a ranching road that itself reuses a centuries-older footpath), rather than picking whichever bucket sounds better for that specific business. Two zones (Albuquerque, Flagstaff & The Grand Canyon) carry an explicit secondary-layer qualifier in `regions.md` rather than a Dual-Layer classification — those still use their primary bucket's messaging, with the qualifier available as supporting color (e.g., "this ancient river-valley convergence point" for Albuquerque) rather than triggering the Dual-Layer mode itself.

## 3. Partner Scaling Targets

- **Per Strategic Sub-Hub:** target of 100 active paying partners. ("Strategic Sub-Hub" here is the same entity `architecture.md` §22 and `regions.md` call a Geo-Hub Zone — one term from the business/financial side, one from the architecture side, same thing.)
- **Per Continental Hub:** an average of 12 Strategic Sub-Hubs (ranging 15–20 in dense megalopolises, 10–15 in moderate regional cores, 5–10 in remote/frontier or island footprints) → roughly 1,200 active partners per hub at the 100-per-sub-hub target (840 Core / 240 Strategic / 120 Cornerstone at the target mix).
- **Nationwide, at full build-out across all 20 planned Continental Hubs:** roughly 24,000 active network partners.

## 4. Revenue Model (Per Regional Hub, at Target Scale)

**Recurring subscriptions** (1,200 partners: 840 Core, 240 Strategic, 120 Cornerstone):

| Tier | Partners | Monthly | MRR | ARR |
|---|---|---|---|---|
| Core | 840 | $19 | $15,960 | $191,520 |
| Strategic | 240 | $79 | $18,960 | $227,520 |
| Cornerstone | 120 | $249 | $29,880 | $358,560 |
| **Total** | **1,200** | — | **$64,800** | **$777,600** |

**Professional services add-on storefront:** white-glove digital setups and video services, ~$60,000/year per regional hub (scaled proportionately across its ~12 sub-hubs). This is a one-time/recurring service layer on top of subscriptions, not a subscription tier itself.

**Gross annual revenue potential, per regional hub:** ~$837,600.

*Note: Hub gross projections assume standard flat-rate single-listing distribution; accounts utilizing multi-location volume discounting (§10) will realize a lower blended yield per partner offset by higher lifetime value (LTV) and lower churn.*

## 5. Operating Cost Model (Per Regional Hub, at 12 Sub-Hubs)

| Expense Category | What It Covers | Monthly | Annual |
|---|---|---|---|
| Platform Infrastructure & Add-ons | Directory hosting, custom domain routing, wildcard SSL, transactional email, location-filtering plugins | $350 | $4,200 |
| Sub-Hub Editorial & Authority Engine | AI-assisted drafting, human editorial review, geographic fact-checking, localized landing page copy | $1,000 | $12,000 |
| Localized Field Operations & Career Leadership | Regional Directors and Field Executives leading partner acquisition and community anchoring | $6,000 | $72,000 |
| Creative & Fulfillment Services | Local graphic/video providers executing white-glove profile setups, QR displays, media packages | $2,000 | $24,000 |
| Hyper-Local Digital Acquisition | Micro-targeted geo-fenced social/search campaigns per sub-hub | $800 | $9,600 |
| Administrative & Legal Contingency | Licensing, regional software (CRM, scheduling, accounting), operational buffer | $1,000 | $12,000 |
| **Total** | | **$11,150/mo** | **$133,800/yr** |

## 6. Profitability Summary

- **Per regional hub:** ~$837,600 gross revenue − ~$133,800 OpEx = **~$703,800 net annual operating profit** (84.0% net margin).
- **Nationwide, across all 20 Continental Hubs (24,000 partners at full build-out):** ~$16,752,000 aggregate gross revenue − ~$2,676,000 aggregate OpEx = **~$14,076,000 net annual operating profit.**

These are target-scale projections for a fully built-out regional hub, not day-one numbers — `regions.md` shows the pilot hub (Ancient America) has 15 sub-hub-equivalent zones, above the 12-hub average used in this model, so its own numbers will differ somewhat from this per-hub template.

## 7. Content Production Behind the Revenue Model

The Editorial & Authority Engine line item above funds a specific content operation, structured to scale without linear cost growth:

- **Foundational Macro & Geo-Hub Guides** — long-form structural content per Strategic Sub-Hub (geography, history, transit corridors, gateway logistics), ~2,000–3,000 words each.
- **Dynamic Cross-Zone Itineraries & Editorial Stories** — the "Authority Engine" itself: weekly/monthly deep-dives into local history, geology, trade routes, and culture, ~1,200–1,500 words each.
- **Partner Profile & Asset Enrichment** — commercial content: copywriting polish, photo optimization, metadata tagging for the active partner base.

Scaling mechanics: the structural framework (sub-hub architecture, gateway mapping, taxonomy rules) stays identical across every new hub — only local metadata and historical detail change (template portability), which drops deployment overhead per hub over time (marginal cost reduction), while articles for border/adjacent zones naturally cross-link and compound organic search authority without added writing cost.

**Partner Profile & Asset Enrichment, mechanism:** the scraper-to-article pipeline that produces this content — ingesting a partner's own catalog, drafting one article per product/listing variant, routing traffic back through Discovery — is documented at the technical level in `architecture.md` §25–§26. The tiered "AI-engine content assistance" language in §1 above (standard/expanded/full) is the commercial framing of that same pipeline's generation quotas; exact numbers are still undefined (see "What's Still Missing" below).

**Editorial Content Blueprint — what the Foundational Macro/Geo-Hub Guides and Dynamic Cross-Zone Itineraries above should actually be anchored in, per direction, 2026-09-24:**

- **Writing Regional Guides:** anchor every Regional Hub's deep-dive content in real historical timelines and pre-colonial trade-network data, framed through **the Living Geography** (`brand.md` §2) — a region's indigenous economic roots and ancient trade arteries, not generic travel tips. This is the concrete editorial instruction behind the "historical/narrative content" the Authority Engine is defined by in §0 and §9.
- **Structuring Global Context — the Side-by-Side Historical Relevancy Timeline:** contextualize North American history against Asia and Europe in the same guides, showing that indigenous civilizations (Cahokia, Chaco Canyon) match the scale of Old World empires of the same era. Named here as a real editorial device to use, not yet built into any CMS/template tooling — that's a `phases.md`/`prd.md` implementation question, not a content-strategy one, and isn't resolved by this note.

## 8. Organizational Structure

**Founder, per direction, 2026-09-24:** Jason DeGraff founded Traversence.com.

Field-level roles tied directly to partner acquisition and community presence: **Regional Directors** and **Field Executives**, who also weave in vocational mentorship and community digital-literacy initiatives — framed as creating real local employment pathways, not just a sales function.

## 9. Competitive Positioning

- **Zero-Extraction Sovereignty:** no commission on partner transactions, versus the 15–30% commission typical of OTAs (Expedia, Booking.com). Traffic routes directly to the partner's own digital ecosystem.
- **Intrinsic Editorial Authority (the Authority Engine):** high-intent organic traffic captured through deep historical/narrative content anchored in **the Living Geography** (`brand.md` §2) rather than generic reviews or paid placement. **Resolved, 2026-09-24:** this note previously flagged that `brand.md` didn't name the Authority Engine or its underlying narrative device ("the Layered Frontier") anywhere — `brand.md` §2 now defines both, with the Living Geography superseding "the Layered Frontier" as the actual established term.
- **Contextual Belonging, not a transactional pin:** while corporate platforms sell a blank map marker, Traversence ties every independent lodge, outfitter, or cafe to the Living Geography — an unbroken, centuries-old commercial lineage, not just a listing. This is what the platform is actually selling to a partner: claiming a place in that continuity, not digital accommodations.
- **Escaping commodity pricing:** a business listed on a traditional OTA is reduced to a photo, a star rating, and a price point, pushed into a race to the bottom on price. Embedding a business in a rich historical/cultural narrative instead — a story-backed destination rather than a generic "accommodation option" — is the platform's structural answer to that dynamic: a customer who feels a genuine connection to the land and the people running a business is less inclined to haggle or price-shop it like a commodity.
- **Restoring economic and cultural lineage:** framing local enterprises as modern keepers of ancient trade corridors (the Living Geography again) validates their endurance against corporate consolidation directly — buying local is framed as continuing a centuries-old tradition of regional trade and community resilience, not just "supporting a small business" in the abstract.
- **Financial efficiency:** an 84% net operating margin claimed at target scale, positioned as proof that hyper-local depth doesn't require bloated overhead.

## 10. Multi-Location Volume Discount Schedule (Strategic & Cornerstone Enterprise Add-on)

Resolves the multi-location billing question this document previously left open (see "What's Still Missing" below) — for Strategic and Cornerstone accounts specifically. Locked in per direction, 2026-09-24.

**Per-listing Subscriptions, confirmed 2026-09-25 (`decisions/0035`):** each location under this schedule carries its own individual Subscription record — its own tier, source, and billing cadence — not one shared account-level grant. "Under one parent account" below describes the discount-banding math (how many per-listing Subscriptions get counted together for the volume band), not a single billing object covering every location. What actually links those per-listing Subscriptions together for banding, shared searchability, and admin visibility is an explicit **Location Group** (`decisions/0036`) — never inferred from one person simply holding `listing_access` across several listings, which may not even be the same business. A Location Group requires every member listing to independently hold its own Cornerstone (or, per this schedule, Strategic/Cornerstone) Subscription, and is created only by the group's owner or a single owner-designated Executive-level manager (`decisions/0037`).

### 10.1 Eligibility — Strategic & Cornerstone only; Core stays single-location

This schedule is a **Strategic/Cornerstone Enterprise Add-on**, not a new pricing tier and not something a Core account can trigger. Core Partner remains strictly single-location / single-listing, matching the tier boundaries already set in §1 — a Core account has no path into multi-location provisioning, group-dashboard management, or this discount schedule under any circumstance. Multi-location provisioning and group-dashboard management (already named in §1's Strategic and Cornerstone bullets) stay exclusive to those two tiers; this schedule is what governs billing once an account actually exercises that provisioning across more than one location.

### 10.2 Volume discount bands, by location count

| Locations under one parent account | Discount off the account's chosen baseline per-location rate |
|---|---|
| 1 (base) | — (100% of standard Strategic or Cornerstone rate) |
| 2–9 | 10% |
| 10–49 | 20% |
| 50–99 | 30% |
| 100–249 | 40% |
| 250+ | Automated banding stops — routes to enterprise custom agreement review, no automatic discount applied (§10.3) |

Confirmed, per direction, 2026-09-24 — these replace the earlier conservative first-draft defaults (5/10/15%).

### 10.3 The 250+ location boundary — automation stops, no ambiguous "or"

Automated volume-discount progression applies only up to and including 249 locations under one parent account. At exactly 250 locations, automated tiering pauses outright — there is no automatic 250+ discount band. The account instead routes to a mandatory enterprise custom agreement review before any further discount is applied. This is a hard stop at 250, not a soft "250 or more" threshold with implied automatic behavior, and it protects the platform from a programmatic discount cascading against an account large enough to materially affect per-hub revenue math without a human ever reviewing it.

### 10.4 Setup-effort language — no ghost features

Any custom onboarding or technical setup work a multi-location account needs beyond the standard Strategic/Cornerstone White-Glove Setup (§2) is described as "or similarly labor-intensive custom technical setup or white-glove onboarding" — existing, general language, not a named feature undocumented anywhere else in this file or in `architecture.md`.

### 10.5 Uniform tier application — no mixed-tier billing

A parent account managing multiple locations under this schedule must run one uniform tier — Strategic or Cornerstone, never a mix — across every location grouped under it. The discount bands in §10.2 apply against a single chosen baseline rate for the whole account ($79/mo Strategic, or $249/mo Cornerstone), applied per location before the volume discount is subtracted. Mixed-tier averaging is explicitly prohibited: an operator cannot group locations across different tiers under one account and have the discount computed against a blended baseline rate. An operator who genuinely wants different tiers at different locations is billed as separate, independent accounts — one per tier in use — each evaluated against this schedule independently by its own location count.

## 11. Internal Billing System — Subscriptions, Services & Non-User Counterparties

**Added 2026-09-25, per direction.** The recurring subscriptions in §1 and the White-Glove/add-on services in §2 and §10.4 are billed through Traversence's own back-office transaction system — distinct from `decisions/0031`'s Market (no in-platform payment processing, post-and-connect only) and from `decisions/0032`'s Quote (a mechanism between two platform users). **A Subscription is per listing, not per account, confirmed 2026-09-25** — a business operating multiple locations (e.g., a §10 multi-location Strategic/Cornerstone account) carries one independently-priced, independently-tiered Subscription per location, matching §10's revised framing above. Full decision record, including the Subscription and Service object shapes (source/description/terms/status/process each), the explicit boundary against ever feeding the public Trust-Weighted visibility model (`decisions/0007`), and the new Counterparty concept for non-user payees (vendors, contractors, field staff — §5 above): `decisions/0035-internal-billing-subscriptions-services-counterparties.md`. This is also what resolves `decisions/0034`'s flagged "tier model needs a second dimension" gap at the decision level — the schema/workflow build-out itself remains a separate, later pass. A promotional/trial Subscription (`decisions/0034`) is likewise per listing — SJ Portables' three-location promotional Cornerstone trial is three separate per-listing Subscription records, not one account-wide grant.

## What's Still Missing

**Per-listing vs. per-business billing for the base subscriptions — resolved 2026-09-25 (`decisions/0035`), for the multi-location case.** Every Subscription is per listing, full stop — §10's multi-location schedule bands per-listing Subscriptions linked into a Location Group (`decisions/0036`) together for discount purposes, it does not describe one account-wide billing object. What's still genuinely open: whether a *single physical location* that happens to host more than one distinct listing (two different businesses at one address, say) is billed as one Subscription or two — a narrower identity question (is that one listing or two?) rather than a billing-model question, and not addressed by `decisions/0035`. Core remains single-location/single-listing by design (§10.1), so this narrower question doesn't apply there.

**Concrete AI-engine content limits per tier — resolved with first-draft numbers, 2026-09-24.** §1 above now gives real figures (Core 5 catalog articles/month, Strategic 25 + 4 custom pieces/month, Cornerstone uncapped) instead of the bare "standard/expanded/full" directional language. These are explicitly flagged as first defaults, not tuned numbers — no real usage or admin-review-throughput data exists yet to validate them against, same caveat as `decisions/0024`'s classifier thresholds. Worth revisiting once real usage patterns exist, not before.

**Consent dependency — fully resolved 2026-09-24.** The AI content-generation pipeline above draws on a partner's own catalog data, which is uncomplicated. It becomes more sensitive if it ever draws on *user*-contributed content (reviews, posts) as grounding material — `decisions/0010-identity-exposure-and-ai-consent.md` (Accepted, no open parameters) covers this: such content is de-identified by default, and only needs an explicit control when identity would actually be attached. **Corrected 2026-09-24:** this used to flag the control's default state as the one remaining open piece — it isn't anymore; the control defaults to opt-in (off), per direction, matching `prd.md`'s consent-dependency note.
