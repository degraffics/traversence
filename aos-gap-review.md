# Master AOS v44: what's built and what's left (review, 2026-10-22)

**How to read it:**
- Each AOS section is marked **Built**, **Partial** or **Missing**.
- The proposed build order is at the end.
- Status comes from the code and the decisions, not from memory.

## §1 Dual engine (traversence.com + trvgo.us)
- **Built:**
  - the hub: directory, search, journeys, experiences, guides, CMS, the admin;
  - trvgo.us short links and GO Codes (0094–0096).
- **Missing:**
  - merchant storefronts on trvgo.us;
  - custom domains (`clientdomain.com`);
  - edge rendering.

## §2 Users vs listings
- **Built:**
  - the split between people and listings, with `listing_memberships` and `Team::can` (0104);
  - claims, and adding a business.
- **Partial:** the listing's CRM and communication vaults; only the leads and messages pieces exist.

## §3 Payments
- **Built:** the order ledger with QuickBooks Payments for our own sales: GO Codes, client orders and plans (0098, 0101, 0108).
- **Missing:**
  - merchant gateways (Stripe, Square, NMI, Authorize.Net) on the same ledger;
  - the 0% merchant checkout.

## §4 Six-level funnel
- **Built:** hubs, geo-hubs, micro-clusters, anchors (listings) and discovery guides.
- **Partial:**
  - Taxonomy & clusters runs as its own tool;
  - 20-mile auto-clustering is in part.
- **Missing:**
  - the Single Home Rule conflicts queue;
  - Level 6, the Trip Planner (compile an itinerary, export a map).

## §5 Storage and tiers
- **Built:**
  - every tier and the personal plans, their prices, trials, renewals, card on file, annual billing;
  - storage and AI-credit packs as add-ons;
  - the 65% floor on promotions (0108).
- **Missing:**
  - **storage metering:** file sizes counted against each owner, the "near / at your allowance" messages, uploads stopped at the limit, the 30-day read-only grace;
  - **AI credits actually spent:** counting each use against the monthly allowance, with Free at zero;
  - WebP/resize on upload everywhere. Hero media and profiles do it today; check every upload path;
  - video only on paid plans.

## §6 Account models
- **Built:** Nexus as a grouping of locations (0054, 0075, 0083).
- **Missing:**
  - **Nexus billing:**
    - the parent's master subscription;
    - add-on locations at $59.95 / $99.95;
    - volume discounts;
    - the Starter Pack and pooled allowances;
    - local team seats per location;
  - **Co-Op Master Hubs:** memberships sold through the marketplace, and the dual-compliance check;
  - the master inventory ledger and fulfillment routing (shared with §8 #9).

## §7 C2C marketplace
- **Missing (all of it):**
  - posts and offers in chat;
  - offline payment, then dual confirmation;
  - marked sold;
  - trust points into the existing trust record.
- **Ready to build on:** Post-and-Connect is decided (0031, 0032), and messaging and the trust record exist.

## §8 The 11 workspaces
The frame, menus, workflows, permissions and archive are **built** (0109). What's still a landing page:

| # | Coming sections |
|---|---|
| 1 | Adaptive shortcuts (your most used) |
| 3 | Single Home Rule queue; auto-clustering settings |
| 4 | Waypoints & GPX/KML routes; Chameleon Filter picks |
| 5 | Post-and-Connect; Storefront Studio; custom domains; catalog rules (250-item cap); merchant gateways |
| 6 | Groups (charters, privacy levels, transfer terms, deactivation); events; business groups |
| 7 | Quotes & deals (Won → subscription, order, White Glove) |
| 8 | White Glove: clients, engagements, service catalog; the Asset studio (menus and brochures → catalog drafts) |
| 9 | Fulfillment (packs, lots, shipments); inventory & COGS; consent hub; DMCA queue; verification vault; reconciliation & tax (in part) |
| 10 | 5 W's lexicon; emergency resources map; telemetry (errors, jobs, AI spend, file count) |
| 11 | Templates; branding & SEO; notifications (SMTP, SMS, push); storage & backups; integrations & AI caps |

## §9 Roles
- **Built:**
  - staff levels and departments, and the Assistant rule (0100, 0104);
  - Access in the admin, with greyed-out actions (0109).
- **Partial:**
  - support tiers 1–3 and masking PII for Tier-1;
  - the seven business roles. The table exists, but the merchant side's own screens don't, so roles can't be given or used yet.

## §10 Delegation
- **Missing:**
  - standing grants to a named agent;
  - the ticket queue with timed access;
  - the "agent is in" banner with End now;
  - ending a staff login revokes its grants (0102 §5).

## §11 Data lifecycle
- **Built:** delete my account, with the 30-day restore (0091).
- **Partial:** imports (the auto-imports and crawler staging).
- **Missing:**
  - **the import engine** (§11.1): CSV, XLSX, GeoJSON, KML and Shopify/Woo maps, with the staging sandbox and a dry-run preview;
  - **export packages** (§11.2): self-serve for members; for businesses, Owner only;
  - webhooks for Strategic and up;
  - **ending, transfer and inactivity** (0103): Notice → Hidden → Archived → Removed, and the group deactivation timers;
  - **the 72-hour verification vault purge.** Claim documents are purged today; the White Glove documents aren't yet;
  - anonymized tax records;
  - **daily encrypted backups** off the server, 30 days kept, and the restore drill (§11.4).

## §12 Content and AI
- **Built:**
  - reference guides and first-person journeys;
  - HITL review for AI-drafted how-to guides and stories;
  - nations' sensitive places held (0066);
  - EXIF stripping on hero, profile, journey and card uploads.
- **Missing:**
  - the paid AI catalog and article quotas, run through HITL;
  - automated safety and anti-spam checks on partner posts;
  - quarantining uploads whose tags or metadata point to a sacred site. Places are held today, but uploads aren't checked.

## §13 Services and overages
- **Built:** client orders and the catalog sell design, print, signage and merchandise (0101).
- **Missing:**
  - email, SMS and push packs;
  - automatic top-ups;
  - the metering behind them (none of email, SMS or push volume is counted yet);
  - SMS and web push themselves (0108 step 7).

## §14 Server ceiling
- **Partial:** the file count is read (`Pulse::fileCount`).
- **Missing:**
  - the 200,000-file warning in telemetry;
  - automatic pruning of abandoned drafts, old scraper snapshots and orphaned uploads.

---

## Proposed build order

Each step is one or two zips. Earlier steps make money or remove risk; later ones depend on them.

1. **Safety net:** daily encrypted backups, plus telemetry (file count against the ceiling, errors, job runs, AI spend). Small, and it protects everything else.
2. **Close the sale (0108 steps 5–6):**
   - Quotes & deals: Won starts the subscription, the order and the engagement;
   - **White Glove**: engagements, services, opportunities, and the service catalog;
   - the Asset studio, with menus to catalog drafts through the OCR already built.
3. **Entitlements that hold the plans to their word:**
   - storage metering, with WebP everywhere and video on paid plans only;
   - AI credits spent and counted;
   - email and SMS metering, plus the packs.
4. **Merchant side v1 (§9B, the decided "after"), on the same engine:**
   - the business portal as modules;
   - the seven roles given and used;
   - standing grants and the ticket queue (§10).
5. **Nexus billing and Co-Ops (§6):** parent subscription, add-on locations, discounts, pooled allowances; Co-Op memberships.
6. **Market (§5, §7):**
   - merchant gateways on the ledger;
   - Storefront Studio;
   - custom domains on trvgo.us;
   - the 250-item catalog;
   - Post-and-Connect with dual confirmation.
7. **Governance (§9 #9, §11):**
   - ending, transfer and inactivity (0103);
   - consent hub, DMCA queue, verification vault;
   - export packages, then the import engine;
   - fulfillment, inventory & COGS.
8. **Community and exploration:**
   - groups with charters, events;
   - waypoints and GPX/KML, the Chameleon Filter;
   - the Trip Planner (Level 6);
   - the Single Home Rule queue;
   - 5 W's lexicon, the emergency map.
9. **Messaging (0108 step 7):**
   - SMS and push, each switched on by its key;
   - templates;
   - automatic claim invites.

## Decisions needed from Jason

1. **Order:** the list above. Should anything move up (for example Market ahead of the merchant side, or Messaging earlier)?
2. **Nexus seats:** a location's team members are business roles scoped to that location. Confirm.
3. **Co-Op:** does a Co-Op sell its memberships through our checkout (QuickBooks, as our own sale), or through its own gateway, which waits for step 6?
4. **Backups:** S3 as the off-server copy needs the AWS keys in `.env`. Or should it be another destination for now?
5. **Single Home Rule:** still waiting on your definition (it's a landing page today).
