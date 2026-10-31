# Traversence & TRVGO: Master Administrative & Operational Specification (Master AOS)
*Version: 44.0 (Updated October 22, 2026: lower personal storage, storage add-on packs, annual discount on plans only, 65% profit floor)*  
*Governance Tier: Core Specification & Master Operations*  
*Industry Classification: NAICS 519130 — Internet Publishing, Broadcasting, and Web Search Portals*

---

## 1. Executive Summary & Dual-Engine Architecture
Traversence operates as a unified, dual-engine ecosystem designed to bridge editorial cultural heritage with direct-to-operator local commerce. The platform explicitly separates central narrative intelligence from high-performance edge execution and delivery.

```
       +-----------------------------------------------------------------------+
       |                        TRAVERSENCE ECOSYSTEM                          |
       +-----------------------------------------------------------------------+
                                           |
                   +-----------------------+-----------------------+
                   |                                               |
                   v                                               v
    +------------------------------+                +------------------------------+
    |   TRAVERSENCE (traversence.com)  |                |       TRVGO (trvgo.us)       |
    |   Central Hub & Narrative Engine |                |  Edge Infrastructure & Routing|
    +------------------------------+                +------------------------------+
    | * Regional Directory & CMS   |                | * Custom Domain Host         |
    | * AI Storytelling & Guides   |                | * Merchant Standalone Shops  |
    | * Multi-Vendor Marketplace   |                | * O2O Dynamic QR System      |
    | * Living Geography Engine    |                | * Edge Short-Code Delivery   |
    +------------------------------+                +------------------------------+
                   |                                               |
                   +-----------------------+-----------------------+
                                           |
                                           v
                       +---------------------------------------+
                       |     THE CONNECTION LAYER & CONVERSION |
                       |    (Direct-to-Operator via Payment Ledger)|
                       +---------------------------------------+
```

### A. Traversence (traversence.com) — Central Hub & Intelligence Engine
* **Role:** Central administrative hub, foundational directory, multi-vendor marketplace, administrative command center, editorial publishing platform, and narrative intelligence engine.
* **Key Functions:**
  * Curates regional itineraries, historical commerce overlays, and human "Journeys."
  * Houses the central database, administrative CMS, CRM vaults, and AI-driven content generation pipelines.
  * Powers the top-down narrative discovery funnel across regional hubs and micro-clusters.

### B. TRVGO (trvgo.us) — Owned Edge Infrastructure & Routing Engine
* **Role:** High-performance edge delivery network, custom domain gateway (`clientdomain.com`), standalone storefront engine, and offline-to-online (O2O) routing utility.
* **Key Functions:**
  * Powers standalone, ad-free merchant storefronts mapped directly to custom operator domains.
  * Serves dynamic short-codes and O2O QR routing (`trvgo.us/{code}`) linking physical touchpoints (menus, signs, packaging) directly to digital assets.
  * Delivers high-speed edge rendering and zero-latency redirection.

---

## 2. Decoupled Entity Model & Security Access
To maintain data integrity, security, and multi-tenant isolation, the system strictly separates individual human identities from commercial listing entities.

```
        +----------------------------+
        |     HUMAN USER IDENTITY    |
        |          (users)           |
        +----------------------------+
        | * Personal Cloud Storage   |
        | * C2C Peer Trades & Chat   |
        | * Saved Routes & Favorites |
        +----------------------------+
                       |
                       | (via listing_memberships / RBAC)
                       v
        +----------------------------+
        |     COMMERCIAL LISTING     |
        |         (listings)         |
        +----------------------------+
        | * Custom Domains & SEO     |
        | * CRM & Email/SMS Vaults   |
        | * Payment Gateways         |
        | * Storefront & Products    |
        +----------------------------+
```

### A. Structural Entity Separation
* **User Identity (`users`):** Represents an individual human. Holds personal cloud storage, personal media galleries, review uploads, saved itineraries, and offline C2C trading histories.
* **Commercial Listing (`listings`):** Represents a commercial enterprise or directory listing tenant. Holds commercial domains, product catalogs, customer transaction ledgers, payment gateway accounts, and CRM vaults.

---

## 3. Financial Model & Payment Routing
* **Zero-Escrow P2P / Direct Settlement:** The platform uses a **payment processor ledger** to facilitate integration with independent vendors (Stripe, Square, NMI, Authorize.Net, etc.) without acting as a financial intermediary.
* **0% System Processing Fees:** Marketplace e-commerce runs via direct merchant gateway settlement. Traversence takes no cut of any sale; the merchant's payment processor charges its own fees, which Traversence does not set or control.
* **Traversence's own sales** (subscriptions, services, GO Codes, client orders) run through the same ledger; QuickBooks Payments is its first gateway (built).

---

## 4. Discovery-to-Action Funnel Architecture & Regional Taxonomy
The platform guides consumers from high-level cultural exploration down to hyper-local merchant conversion through a structured 6-level geographic and narrative funnel:

```
LEVEL 1: CONTINENTAL HUB             [ Macro Discovery ]
  └── LEVEL 2: REGIONAL / STATE      [ Regional Stories ]
        └── LEVEL 3: GEOGRAPHIC HUB  [ Cultural / Historical Overlays ]
              └── LEVEL 4: MICRO-COMMERCE  [ Micro-Clusters & Trade Routes ]
                    └── LEVEL 5: SINGLE-HOME-RULE ANCHOR  [ Individual Listings ]
                          └── LEVEL 6: LOCALIZED SUB-GROUP   [ Trip Planner & Action ]
```

* **Level 1 (Continental Hub):** High-level regional gateway (e.g., Ancient America) setting the overarching cultural and historical tone.
* **Level 2 (Regional / State):** Broad state and geographical boundaries organizing major travel corridors and historical zones.
* **Level 3 (Geographic Hub / Geo-Hub Zone):** Distinct economic and cultural regions mapped to historical trade networks and geographic features.
* **Level 4 (Micro-Commerce Connections):** Local clusters, trade routes, river valleys, and merchant associations bridging stories into local business ecosystems.
* **Level 5 (Single-Home-Rule Anchors):** Individual merchant listings, physical premises, and historical points of interest.
* **Level 6 (Localized Sub-Groups & Itinerary Action):** The conversion node where travelers compile custom itineraries using the Trip Planner, export dynamic maps, and engage with local operators.

---

## 5. Storage Schedule & Commercial Subscription Tiers

### A. Personal Cloud Storage (Human User Identity)
Personal storage attached to individual user accounts operates on a dedicated tiered schedule. Strictly zero AI or LLM capabilities are permitted on free personal storage accounts.

| Plan | Price | Storage & Capabilities |
| :--- | :--- | :--- |
| **Base Personal** | $0 / mo | 2 GB included free. Staging assets, review uploads, receipts, C2C trade photos. **Strictly zero AI access**. |
| **Personal Plus** | $4.95 / mo | 25 GB secure cloud storage + full access to AI photo/text editing utilities. |
| **Personal Pro** | $9.95 / mo | 50 GB secure cloud storage + full access to AI photo/text editing utilities. |

*Lowered 2026-10-22 (Jason) from 10 GB / 100 GB / 250 GB: once files are on S3, space costs every month, and at full
use the old 100 GB and 250 GB plans fell below the 65% profit floor. More space comes from the storage packs (§5D).*

### B. Cumulative Commercial Subscription Tiers (Listings)
Commercial features scale cumulatively and additively. Paid commercial tiers sell tools, communication volume, storage depth, and White-Glove support—never directory search ranking or algorithmic placement.

| Tier | Price | Included Storage & Comms | Core Capabilities & AI Limits |
| :--- | :--- | :--- | :--- |
| **Free Directory** | $0 / mo | 2 GB storage | Standard NAP directory listing, manual publishing, baseline C2C messaging. **Zero AI/LLM access**. |
| **Basic Partner** | $29.95 / mo | 10 GB storage (Auto WebP/AVIF) | Central marketplace listing with manual payments (Zelle, bank transfer, check, pay on pickup), profile sectioning, taxonomy routing, AI text assistance (5 catalog drafts/mo). |
| **Core Partner** | $79.95 / mo | 25 GB storage (`srcset` asset delivery) | Card checkout via the partner's own payment gateway (0% system processing fees; the processor's own fees apply), owned custom domain (`clientdomain.com`), standalone storefront, dynamic QR suite (`trvgo.us/{code}`), automated Local SEO (JSON-LD). |
| **Strategic Partner** | $229.95 / mo | 100 GB storage + 10k Email / 500 SMS / 10k Push | High-output AI content (up to 25 articles/mo + 4 custom posts), subdomain routing, White-Glove setup, native internal CRM suite, Headless API access. |
| **Enterprise / Cornerstone** | $399.95+ / mo | 250 GB storage + 50k Email / 2,500 SMS / 50k Push | Multi-Unit/Co-Op Master-Hub architecture, Master Communication Vault, joint regional campaigns, uncapped AI catalog generation, advanced team RBAC, dedicated support. |

*Payments (Jason, 2026-10-22): Free and Basic sell with manual payments only; card checkout through the partner's own gateway starts at Core.*

*Storage lowered 2026-10-22 (Jason) from 10 GB / 50 GB / 100 GB / 1 TB / 2 TB; more from the storage packs (§5D).*

### C. Annual Plans
* **Pay for 10 months, get 12** (16.7% off), on **subscriptions of service only**: every tier, personal plans, and Nexus
  add-on locations (each location is a subscription).
* **Add-ons are never discounted** (storage packs, AI credit packs). They're billed at full price **on the plan's
  schedule**, so there's one renewal date and one charge:
  * monthly plan: add-ons on the same monthly charge;
  * annual plan: add-ons at 12× the monthly price on the same annual charge;
  * added mid-term: prorated to the plan's next renewal, then renewing with it.
* Removing an add-on takes effect at the next renewal (no refund mid-term). If the plan lapses, its add-ons stop with
  it; storage over the new allowance stays readable for 30 days (nothing is deleted, new uploads pause).
* **Profit floor:** no discount, promo or combination takes a sale below **65% profit** (decisions/0108). This
  replaces the earlier 45%-off cap.
* 250+ locations: custom agreement, with the 65% floor as a guide.

### D. Storage Add-on Packs (personal accounts and listings alike)
One set of packs for everyone; they stack. Priced at the 65% profit floor on a cost basis of **$0.04 per GB-month**
(S3 storage $0.023 plus download traffic, allowing about 20% of the pack viewed each month).

| Pack | Price / mo | Our cost / mo | Margin |
| :--- | :--- | :--- | :--- |
| +25 GB | $2.95 | $1.00 | 66% |
| +50 GB | $5.95 | $2.00 | 66% |
| +100 GB | $11.95 | $4.00 | 67% |
| +250 GB | $28.95 | $10.00 | 65% |
| +1 TB | $114.95 | $40.00 | 65% |
| +5 TB | $574.95 | $200.00 | 65% |

* Space is metered per owner (a person or a listing): every stored file's size is recorded against it.
* Near the allowance the owner sees what's used; at it, the upload stops with a plain message offering an upgrade or a
  pack. Nothing stored is ever deleted for being over.
* Photos are converted to WebP and resized on upload (a 4–8 MB phone photo is kept at about 300 KB); size caps per
  file; video only on paid plans.
* If S3 prices or traffic change, the cost basis is reviewed and the packs repriced to hold the floor.

---

## 6. Account Models, Nexus Location Fees & Volume Discounts

Every commercial account runs on one of three models.

### A. NEXUS (centralized corporate and franchise networks)
* **What it is:** a multi-location enterprise, corporate chain or franchise network running several rooftops under one
  administration. On the site it is the brand's Nexus (decisions/0075), which links its locations.
* **Uniform tier:** every location in a Nexus runs on the same tier (Strategic or Cornerstone/Enterprise). Mixed tiers
  are prohibited.
* **Parent billing and pooling:** the parent holds the master subscription and pays one consolidated bill. Locations
  don't carry their own matching subscriptions: they draw storage, communication volume, catalog caps and AI quotas
  from the parent's pooled ledger. The parent upgrades its master tier as demand grows.
* **Nexus Starter Pack:** when the first add-on location joins, the parent's pool gets a one-time addition, included
  in the price: Strategic +100 GB, +5,000 email, +250 SMS, +5,000 push; Cornerstone double that.
* **Add-on location fee:** each location beyond the parent pays a monthly fee, with volume discounts applied to it:
  **$59.95** under a Strategic parent and **$99.95** under Cornerstone.
* **What an add-on location gets:**
  - its own listing: page, map pin, hours, contact, photos, reviews, and a place in its local cluster;
  - a storefront section on the parent's domain (`brand.com/location`); its own domain is an extra;
  - its own QR codes and short links, with scans counted per location;
  - local team seats (e.g. a General Manager and staff), with roles scoped to that location;
  - a local inventory and pickup point, connected to the master ledger;
  - every parent-tier feature, drawn from the pool;
  - an addition to the pool: Strategic +50 GB, +1,000 email, +50 SMS, +1,000 push; Cornerstone +100 GB, +2,500
    email, +125 SMS, +2,500 push.
* **Inventory and fulfillment:** one Master Inventory Ledger (raw materials, work in progress, finished goods), with
  fulfillment routed across central hubs or branch locations.

| Locations in the Nexus | Discount on the add-on location fee |
| :--- | :--- |
| **1 (the parent only)** | none (no add-on fee) |
| **2 – 9** | 10% |
| **10 – 49** | 20% |
| **50 – 99** | 30% |
| **100 – 249** | 40% (combined with annual, capped at 45%) |
| **250+** | Automated discount pauses; a custom enterprise agreement is required. |

**Examples:**

| | 3-location Strategic | 15-location Cornerstone |
|---|---|---|
| Parent | $229.95 | $399.95 |
| Add-on locations | 2 × $59.95 at −10% = $107.91 | 14 × $99.95 at −20% = $1,119.44 |
| **Monthly** | **$337.86** | **$1,519.39** |
| **Annual** (10 months) | **$3,378.60** | **$15,193.90** |
| Per location, monthly | $112.62 | $101.29 |
| Saving against separate subscriptions | 51% | 75% |
| Pool: storage | 1.2 TB | 3.6 TB |
| Pool: email | 17,000 | 95,000 |
| Pool: SMS | 850 | 4,750 |
| Pool: push | 17,000 | 95,000 |

### B. CO-OP (chambers, associations and guilds)
* **What it is:** a merchant association, chamber of commerce or maker guild uniting independent businesses under a
  regional banner, run from a Co-Op Master Hub.
* **Marketplace:** the Master Hub configures, bundles and sells Co-Op memberships and shared resource packages (e.g. a
  Gold Merchant Tier at $500 a year) through the platform marketplace, under its own account tier, settled through its
  own payment ledger.
* **Dual compliance:** every member holds both an active Traversence/TRVGO subscription and an active Co-Op
  membership. If the membership lapses, the Co-Op's shared resources are withdrawn; the member's own listing,
  storefront and purchases are untouched.

### C. DECENTRALIZED (independent tenants)
* **What it is:** a standalone merchant or single-location business on its own terms.
* **Self-contained:** its own subscription tier, its own payment gateway, its own stock ledger; nothing shared.

---

## 7. Peer-to-Peer (C2C) Marketplace & Dual Confirmation Protocol
To eliminate financial platform liability and processing fee overhead on local secondary trades, Consumer-to-Consumer (C2C) item trades operate on an offline-payment, on-platform confirmation model.

```
[ BUYER ]                                                    [ SELLER ]
    |                                                            |
    | ------------ 1. Initiates Offer in In-App Chat ----------> |
    |                                                            |
    | <----------- 2. Accepts Offer & Coordinates Pickup -------> |
    |                                                            |
    | * Offline Payment (Cash / Direct Peer App at Pickup) *     |
    |                                                            |
    | ------------ 3. Submits "Mark Completed" ----------------> |
    |                                                            |
    | <----------- 4. Confirms Completion ---------------------- |
    |                                                            |
    v                                                            v
  +------------------------------------------------------------------+
  |               DUAL MUTUAL CONFIRMATION EXECUTED                   |
  |  * Trust Scores Updated for both Buyer and Seller                |
  |  * Listing Marked as Sold (0% Platform Fee Charged)              |
  +------------------------------------------------------------------+
```

1. **Offline Financial Settlement:** Buyer and seller arrange cash, local pickup, or direct peer transfer independently.
2. **In-App Dual Mutual Confirmation Protocol:** Once physical exchange occurs, both parties execute a mutual completion prompt in the chat.
3. **Trust Score Accrual:** Upon mutual confirmation, the listing status updates to SOLD and both users gain verified local trust score points without processing platform financial transactions. Trust points are kept in the platform's existing trust system (the enforcement ladder's trust record), not a separate score.

---

## 8. Comprehensive Workspaces & Operational Engines (1 to 11)
* **1. Dashboard & Quick Actions:** Real-time operational task queues, urgent platform alerts, adaptive navigation shortcuts, and manual order processing terminals.
* **2. Unified Communications Hub (Pulse Stream):** Filtered, critical-action communications stream (inquiries, sales events, fulfillment drops, and critical system emergencies) equipped with contextual split-drawers.
* **3. Directory & Places (Get Local 360° Workspace):** Six-Tier Geographic Taxonomy Engine (the six funnel levels of §4: Continental Hub → Regional / State → Geo-Hub Zone → Micro-Cluster → Single-Home-Rule Anchor → Localized Sub-Group), 20-mile spatial auto-clustering, Single Home Rule enforcement, and Level-1 Master Taxonomy governance.
* **4. Journeys & Exploration (Let's Explore 360° Workspace):** Curated travel guides, interactive map waypoints, GPX/KML route files, and Chameleon Filter editorial picks.
* **5. Marketplace & Storefronts (Market 360° Workspace):** Post-and-Connect C2C/B2C exchange model, Custom Domain & Storefront Studio (`clientdomain.com`), catalog pricing rules (capped at 250 items), and direct gateway delivery routing.
* **6. Community & Events (Social 360° Workspace):** User-created topic spaces, group privacy levels, user-authored charters, and 3-Tier escalation moderation.
* **7. Sales, Marketing & Customer Growth:** B2B lead prospecting, unclaimed listing pipelines, order processing workspaces, and regional promo tools.
* **8. White Glove Concierge:** High-touch client onboarding task boards, OCR asset digitizing studio, and menu/brochure parsing pipelines.
* **9. Back-Office Operations, Logistics & Governance:** Inventory ledgers, COGS accounting, multi-channel fulfillment splitting, QuickBooks sync, user consent hubs, DMCA take-down queues, and secure private verification vaults.
* **10. Insights & Analytical Research:** On-demand search engine workbench, 5 W's lexicon parser (Who, What, Where, When, Why), emergency resource mapping, and infrastructure telemetry.
* **11. Global System Configuration:** Unified technical administration panels covering branding, permalink SEO rules, schema builders, AI assist guardrails, vector search embeddings, SMTP/Twilio notifications, API integrations (Google Maps, QuickBooks), S3 storage configuration (drop in credentials to switch uploads from the server to S3; local storage until then), and backup settings. DB replica routing and Redis are future (§11.4); OPcache settings as Bluehost allows.

---

## 9. Two-Sided Role-Based Access Control (RBAC) Architecture

### A. Platform Staff & Ops Roles (Internal Infrastructure & Ecosystem Governance)
Staff roles run the **whole admin**, all 11 workspaces of §8, not only listings. A staff role has two parts:
- a **level**, which says how much they can do;
- a **department**, which says which workspaces they work in.

A tool is configured only in its home workspace; people who use it elsewhere work it there (§8).

**Levels:** each level does one thing, so they don't overlap. One person may hold several levels while the team is
small.

| Level | Its one job | Scope | Reports to |
|---|---|---|---|
| 👑 Founder / Owner | everything; staff logins and roles; final policy overrides | all | — |
| Executive (CEO, COO, CIO/CTO, CFO) | **decides:** targets, policy approval, emergency platform freeze; sees everything; doesn't configure tools or run queues | the whole company | Founder |
| Director | **configures:** owns a department's tools, rules and exceptions | one department | Executive |
| Manager / Editor | **assigns and approves:** runs daily work and people; an Editor approves and publishes content | a team in a department | Director |
| Staff / Content Creator | **does the work:** works queues; Creators draft content for an Editor | their team | Manager (Creators: an Editor approves their work) |
| Assistant | **supports one named Executive, Director or Manager** with that person's access, read, write and update, except delete and managing staff | the person assisted | that person |
| Third-Party Vendor / Integrator | nothing by default; ticket-scoped, time-limited, revocable at once | one ticket | Service Manager |

**Departments:** each has its own Director, Managers and Staff.

| Department | Covers | Workspaces |
|---|---|---|
| Operations | onboarding and KYC approvals, suspensions, governance (consent, DMCA, verification vault), the directory | 1, 2, 3, 5, 6; 9 governance |
| Finance | ledger, reconciliation, QuickBooks, tax, fees | 1, 2; 9 finance; 7 orders (view) |
| Sales | leads, unclaimed listings, quotes, promotions; **sells White Glove** as part of the deal | 1, 2, 7; 8 (to schedule onboarding at close) |
| Service | the support queue, SLAs and Tier-1/2/3 support; tickets and standing grants (0102); order processing and fulfillment; vendor management; **delivers White Glove** | 1, 2, 7 (order processing), 8 (home), 9 (fulfillment, vendors) |
| Content | 3, 4, 6 editorial; HITL staging | Editors approve; Creators draft |
| Engineering | 10 telemetry; 11 (staging); Tier-3 routing and domain fixes | developers get no production data |

**White Glove** is shared: Sales sells it and Service delivers it. Closing a sale opens the onboarding automatically.
**Orders** follow the home-and-uses rule: the home is Back-Office (9); Sales starts orders, Service processes them,
Finance reconciles them.

**Support tiers** (Staff in Service):
- **Tier-1:** triage; sees 3, 5 and 6 with PII masked.
- **Tier-2:** adds the 9 queues and application and webhook logs; works tickets with timed access (0102).
- **Tier-3:** adds 10 telemetry and 11 (view); fixes TRVGO routing and domains.

**From the built roles (0100):**

| Built role | Becomes |
|---|---|
| Owner | Founder |
| Manager | Operations Manager |
| Editor | Content Editor |
| Content Creator | Content Creator |
| Accountant | Finance Staff (or Finance Manager) |
| Executive Assistant | Assistant, assigned to a named person |
| General | Service Staff, Tier-1 |

### B. Client Listing Management Roles (External Merchant Environment)
As decisions/0102 §3–4 (seven roles; the role decides, with no confirmation steps):

1. **👑 Owner:** everything; billing, team and roles, domain and API keys, exports, deleting the business.
2. **Manager:** runs the business day to day; team roles below Manager; catalog, orders, customers, theme; QR codes and short links (`trvgo.us/{code}`).
3. **Accountant:** ledger, invoices, payments (edits invoices only).
4. **Sales:** customers and pipeline (their own), orders; the sales representative in the business's groups.
5. **Support:** orders and fulfillment, the Support view of the inbox; moderator in the business's groups.
6. **Creator:** listing details, catalog drafts (publishing is a business setting), theme; posts as the business.
7. **Developer:** domain, API keys, webhooks, theme.

A one-person business sees no roles. Permission table: decisions/0102 §4.

---

## 10. Secure Delegation & System Access Workflow
As decisions/0102 §5. An agent always acts with a business role (§9B), given by one of two routes:
1. **Standing grant:** the owner grants a **named agent** a role until revoked. If Traversence reassigns the account, the
   grant moves to the new agent at once; the owner is told and can revoke. No confirmation needed.
2. **Ticket queue:** tickets go into a rolling queue; the agent who picks one up gets **timed access** with the role set
   by the ticket type (e.g. 2 hours, extendable once); it ends at the time limit, on closing, or on reassignment.

Both routes: a banner while an agent is in ("End now"), PII masked where the role doesn't need it, every action in an
action log that can't be edited, and ending a staff login revokes its grants and returns its tickets to the queue.

---

## 11. Data Retention, Deletion, Recovery & Import/Export Services

### 11.1 Data Ingestion & Import Engine
* **Supported Formats:** Bulk CSV, TSV, JSON, XLSX, GeoJSON, KML, ESRI Shapefiles, and WooCommerce/Shopify catalog migration maps.
* **Schema Validation & Staging Sandbox:** Incoming imports pass through schema validation workers. Records with missing required fields or invalid geo-coordinates route to an isolated staging sandbox.
* **Human-In-The-Loop (HITL) Queue:** Importers receive an interactive batch preview dashboard detailing valid vs. error rows for dry-run verification prior to live database execution.

### 11.2 Data Portability & Export Services
* **Portable Package Generator:** One-click merchant/user data package creation emitting a structured `.zip` container housing CSV/JSON data files, media upload manifests, and transaction ledgers.
* **Role-Restricted Export Permissions:** Consumer exports are self-service (`User Settings -> Privacy`). Commercial listing exports containing customer/financial logs are strictly limited to **C-Suite Personnel** roles.
* **Real-time ETL & Webhook Streaming:** Strategic and Cornerstone tiers can configure REST/GraphQL webhooks to continuously stream transactional, catalog, and CRM event data to external enterprise data warehouses.

### 11.3 Data Deletion Lifecycle & Retention Rules
* **Ending, transfer and inactivity follow decisions/0103:** Notice → Hidden → Archived → Removed, with the owner told
  at each stage; groups follow the transfer terms in their charter; groups with no posts for 30 days or no owner or
  moderator activity for 90 days enter deactivation.
* **30-Day Soft-Delete Grace Period, only for what an owner deletes themselves:** hidden at once (`deleted_at`,
  `is_active = FALSE`), restorable for **30 calendar days**, then the daily purge removes the rows and their files.
* **Never purged:** a nation's data (decisions/0066); places themselves (a removed listing returns to an unclaimed place).
* **72-Hour OCR Verification Vault Auto-Purge:** verification documents (utility bills, business licenses) from
  white-glove onboarding are purged 72 hours after verification.
* **Anonymized Tax Records:** financial and tax records keep their figures with personal details removed, for the
  legally required period.

### 11.4 Backup & Recovery (Bluehost shared, now)
* **Daily backups:** an encrypted database dump every 24 hours, copied off the server (to S3 once configured), kept 30
  rolling days; Bluehost's own backups as a second copy.
* **Files:** uploads included in a weekly archive, or held in S3 once configured (§8 #11).
* **Restore drill:** a restore is tested on the local copy each quarter, with the steps written down.
* **Future (needs a VPS, dedicated server, or cloud):** RPO < 5 minutes (MySQL replication, frequent snapshots),
  RTO < 2 hours (automated failover), and SLAs. Recorded in `future-considerations.md`.

---

## 12. Content Engine, AI Guardrails & Privacy Protocols

### A. Dual Content Model
* **Reference Guides:** Vetted, highly structured historical, geographic, and regional editorial content managed directly by platform editorial staff.
* **First-Person Journeys:** User-contributed community narratives, photo logs, and real-world travel experiences tied to geographic anchors.

### B. AI Generation Limits & Guardrails
* **Free Tier:** Strictly 0 AI capabilities.
* **Paid Tier Content Quotas:** System-generated catalog articles undergo Human-In-The-Loop (HITL) staging and sign-off before publishing to Discovery feeds.
* **Self-Generated Partner Content:** Profile fields, event posts, and news updates publish immediately following automated safety, anti-spam, and legal compliance checks.

### C. Privacy & Cultural Moderation Protocols
* **EXIF / GPS Stripping:** Photo uploads automatically strip embedded EXIF and precise GPS metadata by default unless explicitly opted in for route mapping.
* **Sacred Site & Tribal Protection:** Pre-publication tag reviews automatically quarantine uploads containing metadata or keywords associated with sensitive or protected indigenous and tribal heritage locations.

---

## 13. Professional Services, Print O2O & Infrastructure Overages

### A. Professional Agency Services Add-Ons
To bridge digital platform profiles with physical main street touchpoints, Traversence provides structured high-margin services:
* **Graphic Design & Creative:** Identity refreshes, menus, digital ad banners, packaging inserts.
* **Video Production:** Production of ephemeral Temporary Display Mode reels ($< 2 \text{ min}$) and evergreen Permanent Cloud Storage Mode "Owner Spotlights."
* **Signage & Collateral:** Weatherproof outdoor signage, window decals, and trade displays integrated with dynamic `trvgo.us/{code}` O2O QR routing.
* **Branded Merchandise:** Custom staff apparel, promotional swag, and retail collections.

### B. Communication & Infrastructure Overages
When commercial accounts exceed bundled monthly limits, automated top-ups apply:
* **Email Packs:** +10k ($19.95) | +25k ($44.95) | +50k ($74.95) | +100k ($129.95)
* **SMS Packs:** +500 ($19.95) | +2,500 ($89.95) | +5,000 ($169.95) | +10,000 ($299.95)
* **Web Push Packs:** +5,000 ($9.95) | +15,000 ($24.95) | +50,000 ($69.95)
* **Storage Packs:** the one set in §5D, for personal accounts and listings alike (+25 GB $2.95 up to +5 TB $574.95).
  *The older commercial packs here (+500 GB $24.95, +1 TB $49.95) are replaced by §5D (v44). All pack prices are
  editable in the plan configurator.*

---

## 14. Infrastructure Safety Boundaries & Server Ceilings
* **Shared Storage Ceiling:** The platform enforces a working planning ceiling of **200,000 files platform-wide** (governed as a file-count/inode limit first, storage size second) to align with Bluehost shared hosting limits.
* **Automated Pruning:** As total files approach the ceiling, automated cleanup processes remove rejected or abandoned HITL-quarantine drafts, superseded scraper snapshots, and orphaned temporary asset uploads.

---

## 15. Summary Governance Matrix

| Parameter | Platform Standard |
| :--- | :--- |
| **Primary Industry Code** | NAICS 519130 (Internet Publishing / Portals) |
| **Primary Domain** | `traversence.com` (Central Hub & Narrative Engine) |
| **Edge Routing Domain** | `trvgo.us` (Storefront Host & O2O QR Router) |
| **Entity Security Architecture** | Decoupled users vs listings (via `listing_memberships` / two-sided RBAC) |
| **Payment Processing** | 0% system processing fees on merchant direct sales via the payment ledger (the processor's own fees apply) |
| **C2C Marketplace** | Offline Settlement + In-App Dual Mutual Confirmation Protocol |
| **Server Safety Ceiling** | 200,000 Files / Inodes Platform-Wide |