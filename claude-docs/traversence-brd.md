# Traversence — Business Requirements Document (BRD)

Sep 25, 2026 · @Jason

## 1. Executive Summary

The mission of Traversence is to act as an immersion and connection engine that bridges daily life and deep exploration by connecting users to the heartbeat of every community — putting the right tools into the hands of residents and travelers right where they need them. Its purpose is to bridge the gap between people and places by creating seamless pathways for exploration, local connection, and discovery: connecting communities, streamlining discovery, and simplifying the journey.

The platform's paradigm shift is to re-map North America by replacing political borders with living connection points, where information is organized by the natural, recognizable flow of geographic and cultural association rather than artificial corporate categories. This moves the architecture away from static directories and toward natural clusters of connection points that reflect how people actually experience places, rather than flat category trees.

**Why this matters commercially, not just philosophically:** Traversence is built as a direct counter-reaction to algorithmic bloat. Where major platforms rely on attention-sucking feeds, ad-driven clutter, and opaque ranking manipulation, users and business owners increasingly want true locality without noise, transparent and non-manipulative discovery, frictionless agency between macro and hyper-local views, and data dignity and control. Every one of those guarantees is enforced by a specific platform mechanism, not left as aspiration — visibility is earned through a Trust-Weighted model rather than sold, and paid tiers sell tools and support depth, never placement.

The platform is organized around a **Tri-Track Architecture** (Resident, Traveler, Community) layered on top of a **Content-to-Commerce Engine** business model, monetized through a four-tier partner ladder (Free through Cornerstone) rather than commissions or advertising. This document is the business case for that structure: what Traversence is, who it serves, how it makes money, and why that model is durable. The companion [Traversence FRD](https://claude.ai/artifact/Wb9ijULkbW74rrNPTiGRSY) covers what gets built and in what order; the `decisions/*.md` architectural decision record corpus is the authoritative detail underneath both.

## 2. Business Model: the Content-to-Commerce Engine

Traversence replaces ad-driven networks and commission-taking OTAs (Expedia, Booking.com) with a single mechanism built from three linked stages:

- **Inbound Authority (the Authority Engine)** — narrative content exploring a region's heritage, culture, and commerce, anchored in the Living Geography, captures organic search traffic from travelers seeking the deep story of a place, not just a transaction.
- **Contextual Discovery (the Connection Layer)** — story-driven guides lead readers from editorial content into the Regional Business Directory, rather than forcing product placement into the narrative.
- **Direct-to-Operator Commerce (the Conversion Engine)** — traffic routes straight to the partner's own ecosystem. There is no centralized booking middleman and no commission taken; 100% of margin and customer ownership stays with the operator.

These are two equal-weight, symbiotic halves of one mechanism, not three separate ideas: Inbound Authority *is* the top-down "Let's Explore" Discovery side of the platform's routing model, and Direct-to-Operator Commerce *is* the bottom-up "Get Local" Directory side. Contextual Discovery is the bridge between them, made concrete — the mechanism that surfaces the right directory listing in the middle of an editorial narrative.

This is what makes the model structurally different from a traditional OTA: a business listed on a traditional platform is reduced to a photo, a star rating, and a price point, pushed into a race to the bottom on price. Embedding a business in a rich historical and cultural narrative instead — tying it to an unbroken, centuries-old commercial lineage — is the platform's structural answer to commodity pricing, and its actual sales pitch to a prospective partner.

**Industry classification, for any external form or integration:** Traversence operates as a platform/directory and business network architecture — focused on local business clusters, listings, and service categorization — not a media and entertainment company, even though it also carries editorial content. Where a short category code is required rather than a description, the fit is NAICS 519130 ("Internet Publishing, Broadcasting, and Web Search Portals").

## 3. Market Structure: the Tri-Track Architecture & Partner Tiers

Three convergent tracks organize every user-facing surface: the **Resident** ("Get Local") track — bottom-up, everyday utility and local routines; the **Traveler** ("Let's Explore") track — top-down, regional narrative and exploration; and the **Community** ("Connect") track — the living, streaming layer binding dialogue and gatherings to place. A fourth construct, **Market**, is a transaction layer built last, on top of the other three (`decisions/0030`).

Partners monetize through a four-tier ladder. Paid tiers sell tools and support depth — never directory ranking; visibility stays entirely earned through the platform's Trust-Weighted model for free and paid partners alike (`decisions/0007`).

| Tier | Price | Target Mix | What it adds |
| --- | --- | --- | --- |
| Regional Business Directory | Free | — (every claimed business, by default) | A fully operational, self-serve marketplace listing; zero AI capabilities, zero admin support. |
| Core Partner | $19/mo | 70% | Enhanced profile, direct map integration, self-service AI-assist (up to 5 AI-generated catalog articles/month), self-created publication access. Single-location only. |
| Strategic Partner | $79/mo | 20% | Everything in Core, plus up to 25 catalog articles/month and 4 custom content pieces/month, the Owner Spotlight Interview, White-Glove Setup, admin/White-Glove-supported publication, multi-location eligibility. |
| Cornerstone Partner ("Complete Inclusion") | $249/mo | 10% | Full, uncapped AI-engine access across the entire catalog, unlimited custom content, dedicated group-management support, full White-Glove Support. |

Strategic and Cornerstone accounts running more than one location bill under a volume discount schedule (10% at 2–9 locations, up to 40% at 100–249; 250+ routes to a manual enterprise agreement) — each location still carries its own independently-priced, independently-tiered Subscription; the discount only bands them together for pricing, never merges them into one billing object. Core remains single-location by design. The full functional shape of these mechanics — Subscriptions, Location Groups, delegated roles — lives in the companion FRD's Tier 2.8 section, not here.

An optional Google Maps & Local Place Management add-on ($125 one-time + $50/mo per location) is available to Core Partners and above, billed explicitly per physical location regardless of tier.

## 4. Revenue Model, Operating Costs & Partner Scaling Targets

**Scaling targets:** 100 active paying partners per Strategic Sub-Hub (Geo-Hub Zone); an average of 12 Sub-Hubs per Continental Hub (ranging 5–10 in remote/frontier footprints to 15–20 in dense megalopolises), yielding roughly 1,200 active partners per hub at target mix (840 Core / 240 Strategic / 120 Cornerstone); roughly 24,000 active network partners nationwide at full build-out across all 20 planned Continental Hubs. These are target-scale projections for a fully built-out hub, not day-one numbers.

**Revenue, per regional hub at target scale (1,200 partners):**

| Tier | Partners | Monthly | ARR |
| --- | --- | --- | --- |
| Core | 840 | $19 | $191,520 |
| Strategic | 240 | $79 | $227,520 |
| Cornerstone | 120 | $249 | $358,560 |
| **Subscription total** | **1,200** | — | **$777,600** |

Plus a professional-services add-on storefront (white-glove setups, video services) of roughly $60,000/year per hub, for gross annual revenue of roughly **$837,600 per hub**.

**Operating costs, per hub (12 sub-hubs):** roughly $133,800/year, spanning platform infrastructure, sub-hub editorial and Authority Engine production, localized field operations (Regional Directors and Field Executives), creative and fulfillment services, hyper-local digital acquisition, and administrative/legal contingency.

**Profitability:** roughly $703,800 net annual operating profit per hub (84.0% net margin); roughly $14,076,000 aggregate net annual operating profit nationwide at full 20-hub build-out. Financial efficiency at this margin is itself part of the competitive story — proof that hyper-local depth doesn't require bloated overhead.

The editorial cost line funds a specific, deliberately non-linear content operation: long-form Foundational Macro & Geo-Hub Guides (structural, per Sub-Hub), shorter Dynamic Cross-Zone Itineraries (the Authority Engine itself, recurring), and Partner Profile & Asset Enrichment (the commercial application of the same scraper-to-article pipeline, detailed in the companion FRD). The structural framework repeats across every new hub with only local detail changing, so deployment overhead drops per hub over time, while cross-linking articles compound organic search authority without added writing cost.

## 5. Competitive Positioning

- **Zero-Extraction Sovereignty** — no commission on partner transactions, versus the 15–30% commission typical of OTAs. Traffic routes directly to the partner's own digital ecosystem.
- **Intrinsic Editorial Authority (the Authority Engine)** — high-intent organic traffic captured through deep historical and narrative content anchored in the Living Geography, rather than generic reviews or paid placement.
- **Contextual Belonging, not a transactional pin** — while corporate platforms sell a blank map marker, Traversence ties every independent lodge, outfitter, or cafe to an unbroken, centuries-old commercial lineage. This is what the platform actually sells to a partner: a place in that continuity, not digital accommodations.
- **Escaping commodity pricing** — a business on a traditional OTA is reduced to a photo, a star rating, and a price point, pushed toward a race to the bottom. A customer who feels a genuine connection to the land and the people running a business is less inclined to haggle or price-shop it like a commodity.
- **Restoring economic and cultural lineage** — framing local enterprises as modern keepers of ancient trade corridors validates their endurance against corporate consolidation directly; buying local becomes continuing a centuries-old tradition, not just "supporting a small business" in the abstract.
- **Financial efficiency** — an 84% net operating margin at target scale, positioned as proof that hyper-local depth doesn't require bloated overhead.

## 6. Organizational Structure & Go-to-Market

Traversence.com was founded by Jason DeGraff. Field-level roles are tied directly to partner acquisition and community presence: **Regional Directors** and **Field Executives**, who also weave in vocational mentorship and community digital-literacy initiatives — framed as creating real local employment pathways, not just a sales function.

**Partner pitch:** when pitching local businesses (lodges, outfitters, cafes, artisans), lead with the Nationwide Narrative to show why Traversence beats a traditional OTA or a generic search listing — not just a map pin, but embedding the enterprise into the Living Geography's unbroken, millennia-old commercial lineage.

**Zone messaging is tailored to regional theme:** in Pre-Colonial Heavy Zones, the business is positioned as part of an ancient archive of regional trade; in Frontier & Industrial Transition Zones, as a modern keeper of the frontier highway standing against corporate consolidation. A third mode applies to Dual-Layer ("True Hybrid") zones, where neither framing alone is honest — the pitch names both threads explicitly and positions the partner at the point where they meet.

**Internal billing, briefly:** partner subscriptions and White-Glove/add-on services are billed through Traversence's own back-office transaction system, distinct from the Market construct's post-and-connect model (no in-platform payment processing there) and from the Quote mechanism between two platform users. Each location carries its own independently-priced Subscription; the full schema (Subscriptions, Services, and a Counterparty concept for non-user payees like vendors and field staff) is functional detail that lives in the companion FRD and `decisions/0035`, not here.

## 7. Scope, Governance & Related Documents

This document deliberately excludes technical implementation detail (database schemas, code, API routing), granular UI specifics (component breakdowns, color tokens), and functional/feature-level requirements. Those live in the companion [Traversence FRD](https://claude.ai/artifact/Wb9ijULkbW74rrNPTiGRSY), backed by the `decisions/*.md` architectural decision record corpus, which is the authoritative detail underneath both documents. This split mirrors the platform's own governance tiers: `charter.md` (mission and vision, changes rarely) and `commercial.md` (pricing and business model, this document's real sources) versus `prd.md` and `decisions/*.md` (the "what to build" layer, changes per release).

**SJ Portables is this platform's first real-world proving ground**, specifically for the Market construct and its External Source plug-in contract (`decisions/0039`). SJ Portables has its own separate BRD and FRD, which document a real dealer's business context and a real third-party integration (ShedSuite) against the general mechanics defined in this pair. Those four documents are meant to be read together, bouncing off one another to surface gaps: where the SJ Portables documents assume a Traversence capability not yet defined here, or where this pair assumes a real-world integration shape that SJ Portables' concrete FRD shows doesn't quite hold.
