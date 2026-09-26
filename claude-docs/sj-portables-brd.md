# SJ Portables – Business Requirements Document

Sep 25, 2026 · @Jason

## Document Overview

This BRD defines the business requirements for SJ Portables (St. Johns Portable Sheds, Containers & Tanks) — a new website and inventory-management build replacing the lead-generation gaps in its current site. Prepared as the build brief for the SJ Portables project (DEGRAFFICS.COM), kept separate from the core Traversence.com platform build. Status: draft, first pass.

## Business Background & Objectives

SJ Portables sells portable sheds, containers, and tanks across multiple physical locations. Its existing site (shedscontainersandtanks.com), built by Jason, lacks operational support, inventory management, and quote/delivery optimization. The objective is a real inventory-driven website that captures leads from actual per-location stock and pricing, and routes interested buyers into a structured quote process instead of a static contact form.

This build also serves as the first real-world implementation of Traversence's Market/Inventory + Quote mechanics (`decisions/0031`, `decisions/0032`) — the "non-offsite" version, meaning SJ Portables' listing and inventory are managed and served directly through Traversence rather than exported to separate hosting (`decisions/0033` covers that alternate path; not used here).

## Scope

**In scope:** multi-location inventory management with cost-to-retail pricing; a public catalog/browse experience per location; a quote/lead-capture workflow; delivery-distance calculation from a drop-pinned address using real road distance; an admin panel spanning quotes and inventory across all locations.

**Out of scope for this build:** in-platform payment processing (Market carries none at launch, per `decisions/0031`); offsite static-export deployment (`decisions/0033` — a separate, later option if SJ Portables ever wants its own standalone hosting); public reviews/ratings beyond what a completed Quote itself anchors (`decisions/0032`).

## Stakeholders

| Stakeholder | Role |
| --- | --- |
| Jason DeGraff (DEGRAFFICS.COM) | Developer/owner of this build; also founder of Traversence |
| SJ Portables | Client business — multi-location, multi-inventory |
| Traversence platform | Provides the underlying listing, inventory, Quote, and account infrastructure this build runs on |

## Functional Requirements: Inventory & Pricing

- Each location manages its own inventory independently by default; SJ Portables' three locations are explicitly linked into a shared Location Group for combined visibility, never inferred automatically just because one account holds access to all three (`decisions/0032`.
- Each item carries cost, markup, and a tax/no-tax flag. Cost and markup stay private to the owner/manager (`listing_access`); only the computed price and tax status are public.
- Multi-location inventory management requires Strategic/Cornerstone tier and an explicit Location Group link (`decisions/0032`'s multi-location gate, `decisions/0036`'s linking mechanism) — see Platform Integration & Tier Model below for how SJ Portables meets that gate.
- An item tied to a completed Quote is marked sold as a status flag, not deleted — preserving it for history and for the review/rating record.

## Functional Requirements: Quote, Lead Capture & Delivery Distance

- A visitor requesting a quote on a specific item creates a stateful Quote record (Requested → Accepted/Rejected), not a plain message (`decisions/0032`).
- Quote completion requires dual confirmation — both SJ Portables and the buyer confirm — before an item is marked sold; no unilateral self-report.
- Delivery distance is calculated from a drop-pinned buyer address using real road-routing distance, not straight-line distance — rural roads make straight-line estimates unreliable for this business.
- A completed Quote is the anchor point for SJ Portables' first review/rating record on the platform.

## Functional Requirements: Communications, Sales Response & Notification Queue

- Every quote request and buyer message routes through Traversence's existing Communications Center (`prd.md` Tier 2.5), not a separate messaging system built for SJ Portables.
- Incoming quote requests and buyer messages queue as notifications into SJ Portables' admin panel, so a lead is never missed between arrival and a human response.
- Sales response is tracked per Quote (Requested → first response → Accepted/Rejected), giving SJ Portables visibility into how quickly its team follows up on a lead.
- Multi-location routing: a quote request tied to a specific location's inventory notifies that location's assigned staff, not one undifferentiated inbox.

## Functional Requirements: Admin Panel

- A single admin panel gives SJ Portables visibility across all three locations' inventory and quotes, enabled by their explicit Location Group link — not automatic just because one account manages all three, and not a separate panel per location.
- Tracks quote status (Requested/Accepted/Rejected/Completed) and inventory status (available/sold) in one place.
- Surfaces the same cost/markup/tax fields at the item level, for internal pricing management.

## Platform Integration & Tier Model

SJ Portables needs Strategic/Cornerstone-tier access to use multi-location inventory management (`decisions/0032`). Per direction, SJ Portables receives Cornerstone tier as a promotional trial — free for a period, not a permanent bypass of tier logic (`decisions/0034`). The account's tier is real, recorded state (a genuine Cornerstone grant, not a hardcoded exception in the gate logic), tracked the same way any Subscription is (`decisions/0035`), just with its source marked promotional rather than paid. Continuation past the trial — convert to paid, extend, or step down — is a deliberate review decision, not automatic in either direction. **Per direction:** while the account is in promotional status, its site pages carry `noindex, nofollow` — the site is not indexed or followed by search engines during the trial, so no SEO/backlink value accrues for free before SJ Portables converts to paid (`decisions/0034`). Normal indexing turns on once the trial ends in conversion or confirmed continuation.

## Non-Functional Requirements, Assumptions & Constraints

- No in-platform payment processing at this stage — negotiation and payment happen off-platform once a Quote is accepted (`decisions/0031`).
- SJ Portables, as a verified business, can show real per-location addresses — unlike a personal Market post, which defaults to an approximate area (`decisions/0031`).
- Assumes SJ Portables' locations are already claimed/verified Traversence listings, or will be claimed as part of this build, so `listing_access` can scope inventory correctly per location.
- Cornerstone's per-listing catalog cap is resolved at 250 items per listing, with no combined cap across linked locations (`decisions/0032`); Core/Strategic per-listing caps remain undecided.

## Success Criteria & Open Questions

Success looks like: SJ Portables' locations each showing accurate live inventory and pricing, quotes flowing through the platform instead of an external contact form, and delivery-distance estimates buyers can trust before requesting a quote.

**Open questions:**

- [ ] How many of SJ Portables' physical locations are onboarded at launch vs. phased in? **Answer, per direction:** 3 locations at launch.
- [ ] Is shedscontainersandtanks.com retired, redirected, or kept running alongside this build? **Answer, per direction:** kept running alongside this build.
- [ ] What is the length of the promotional Cornerstone trial, and who reviews it at the end? **Answer, per direction:** a 2-month testing phase comes first, to verify the features and functionality work; a 1-month promotional Cornerstone trial follows, reviewed at its end.
- [ ] What is the per-tier Market catalog item-count cap once decided (`decisions/0032`)? **Answer, per direction:** resolved to 250 items per listing, no combined cap across locations (corrected 2026-09-25 from an earlier 500-total figure).
