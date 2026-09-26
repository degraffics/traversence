# SJ Portables – Functional Requirements Document

Sep 25, 2026 · @Jason

## Document Overview

This FRD translates the SJ Portables BRD's requirements into buildable functional detail — exact fields, states, business rules, and acceptance criteria — for the multi-location inventory, Quote, delivery-distance, communications, and admin-panel build. It assumes the BRD's decisions as settled (3 locations at launch, Cornerstone promotional trial, 250-item per-listing catalog cap, no combined/group cap) and does not re-argue them; it exists to make them concrete enough to code against. Governing ADRs: `decisions/0031` (Market post-and-connect model), `decisions/0032` (Quote & inventory mechanics), `decisions/0034` (promotional trial grants), `decisions/0035` (internal billing). This is not itself the technical/schema design pass — see the Open Dependencies section at the end for what still needs that separate pass.

**Merged 2026-09-25, per direction.** This document now absorbs a separate, more concrete SJ Portables FRD drafted in a parallel thread (the real lot addresses, the ShedSuite technical detail, RTO financing, delivery calculator, and data model below) — the two were bounced off each other rather than kept apart, and any discrepancy found between them is called out and resolved inline rather than silently dropped. Platform-general mechanics (Location Groups, delegated roles, Subscriptions, the pricing-model taxonomy, the External Source contract) are covered in depth in the companion [Traversence FRD](https://claude.ai/artifact/Wb9ijULkbW74rrNPTiGRSY) and referenced here by ADR rather than re-explained; this document stays focused on how those mechanics apply to SJ Portables specifically.

## Business Context

SJ Portables (St. Johns Portable Sheds, Containers & Tanks) is an authorized American Barn Co. (ABCO, formerly Weather King) dealer with three lots, also selling containers, water tanks, septic systems, and culverts. In business since 2008; Google rating 4.7 from 52 reviews.

| Lot | Address | Phone | Hours |
| --- | --- | --- | --- |
| St. Johns, AZ (HQ) | 895 W Cleveland St, St. Johns, AZ 85936 | 928-321-1000 | Mon–Sat 9–5 |
| Sanders, AZ | 36788 US 191, Sanders, AZ 86512 | 928-245-5650 | Mon–Sat 9–4 |
| Quemado, NM | 3439 Hwy 60, Quemado, NM 87829 | 928-321-1000 (shared line) | To confirm |

| Product line | Pricing source | RTO | Delivery |
| --- | --- | --- | --- |
| ABCO portable buildings (sheds, cabins, barns, garages) | ABCO / ShedSuite | Yes, via ShedSuite | Free delivery & setup within 50 mi of any lot |
| Shipping containers (20/40 ft) | Dealer (cost to retail) | Yes, dedicated partner | Case-by-case freight quote |
| Water tanks, totes, barrels | Dealer | No | Case-by-case freight quote |
| Septic systems | Dealer | No | Case-by-case freight quote |
| Steel culverts + bands, aprons | Dealer | No | Case-by-case freight quote |

Corrections carried forward from an earlier third-party research pass, kept here so they aren't reintroduced later: ABCO RTO is 36 or 60 months with one month's rent at signing (the "$99 down" figure is unverified and may belong to the container program); ABCO is the supplier, not a competitor; horse barns are purchase-only (no RTO).

## Architecture & Systems of Record

**One Traversence-native build, not a separate offsite system.** Per the BRD, this is the "non-offsite" implementation of Traversence's Market/Inventory + Quote mechanics (`decisions/0031`, `decisions/0032`) — SJ Portables' listing and inventory are served directly through Traversence, not exported to separate hosting (`decisions/0033` covers that alternate path; not used here). **Correction, merging in a discrepancy from an earlier parallel draft of this document:** that draft described "a custom PHP/MySQL system" as a stack distinct from Traversence. That framing is corrected here — the tables and sync jobs below are the concrete schema Traversence's own Market implementation needs to satisfy this listing's real requirements (per-lot stock, RTO financing assignment, price imports, delivery-distance rules) and the External Source contract (`decisions/0039`), not a second system living outside the platform. The public site reads from Traversence's own data layer, so it stays fast, indexable, and unaffected if ShedSuite itself is briefly unreachable.

| Data / function | System of record | How the site gets it |
| --- | --- | --- |
| ABCO shed inventory, prices, specs, images | ShedSuite | Scheduled API sync into a local mirror (`shed_mirror`, `pricing_model: mirrored`) |
| ABCO options and custom builds | ShedSuite 3D builder | Link or embed; order created in ShedSuite |
| ABCO RTO contracts and order processing | ShedSuite | Hand-off at Quote-Accepted; status via scheduled polling — see ShedSuite Integration below (corrected from an earlier webhook assumption) |
| Container, tank, septic, culvert inventory and pricing | Traversence (native) | Native, `pricing_model: cost_markup` |
| Container RTO | Financing partner (per product line) | Partner apply link / embed |
| Delivery distance and free-delivery eligibility | Native | Routing API (road distance) |
| Leads and quotes (all lines) | Native, with shed leads also pushed to ShedSuite | Native + API write |
| Business info (lots, hours, phones, service area) | Native listing profile | Native |

**Stack:** cron (or equivalent scheduled job) for sync/polling, a routing/maps API for road distance. No separate public webhook endpoint is needed for ShedSuite — see the correction below.

## ShedSuite Integration

The site mirrors SJ's ABCO inventory from ShedSuite and hands orders back to ShedSuite once a Traversence Quote on a shed item is Accepted. This is the first real, built instance of the External Source plug-in contract (`decisions/0039`); the general contract lives in the Traversence FRD's Tier 2.8 section, not repeated here. ShedSuite's real Public API (`https://app.shedsuite.com/api/docs/index.html`) covers contacts, customer orders, inventory, quotes, and tokens — confirmed against its actual OpenAPI spec, not just its marketing description.

**FR-4.1 Inventory sync**

1. A scheduled job runs every 10–15 minutes and pages through `GET /inventory/v1`, filtered by `locatedAtDealerId` for SJ's three lots.
2. Each item is upserted into the local shed mirror, keyed on the ShedSuite inventory ID: lot, model, size, style, colors, status, cash price, images, ShedSuite listing URL. **Gap, confirmed against the real spec:** `Inventory` carries no RTO-monthly-payment field at all, only a cash price — RTO terms (`rto`, `rtoCompanyName`, `rtoMonthsOfTerm`) exist only on `CustomerOrder`, meaning they appear only after a sale, not on browsable inventory. The catalog's "from $X/mo" display cannot be built from this endpoint as originally assumed; it needs either a separate RTO-payment calculator run locally against ABCO's published terms, or omitting a monthly estimate until a quote is underway.
3. Items no longer returned (or flagged sold) are marked sold/removed and hidden from the site.
4. Each run is logged (started, items added/updated/removed, errors). A failed run keeps the last good data and alerts the admin.
5. Admin can trigger a manual sync.

**FR-4.2 Shed catalog on the site**

- Shed listing and detail pages read only from the local mirror — never a live ShedSuite call on page load.
- Filters: lot, building type, size, price range.
- Detail page shows lot, specs, gallery, cash price, a free-delivery check, and two actions: Buy / Rent-to-Own (hands off to ShedSuite's own listing/builder) and Request Info (creates a Traversence lead/Quote).

**FR-4.3 Custom builds**

- "Build Your Own" links to or embeds ShedSuite's own 3D builder, preset to SJ as dealer where supported. All ABCO options (paint, roof colors, windows, lofts, porches) come from the builder; the SJ catalog does not duplicate them. A configuration produced there can be referenced by its `design` object when the resulting order is created (FR-4.4).

**FR-4.4 Order hand-off, corrected against the real API spec (`decisions/0039`)**

- A shed Quote reaching Accepted in Traversence triggers the real ShedSuite call sequence: `POST /contacts/v1` once, to create the ShedSuite customer (or reuse an existing one), then `POST /quotes/v1`, referencing that customer, the dealer, and the building configuration (model variation, add-ons, colors, and optionally the `design` reference from FR-4.3). This call computes real pricing and returns an `orderNumber` immediately — it functions as the actual order-creation call despite being named "quote" in ShedSuite's API, not a separate pre-order stage.
- If the push fails, the Traversence-side Quote stays Accepted with a visible push-failure state and is retried; admin sees push status.
- **Still open, an account-structure question rather than a technical one:** whether SJ Portables — versus ABCO, as the umbrella ShedSuite account holder — can generate or hold its own API token scoped to just its three lots, since the `scopes` field on ShedSuite's token-creation endpoint is undocumented as to dealer-restriction capability.
- **Second gap, also confirmed against the real spec:** `CreateQuotePayload` only accepts a delivery/billing address by ID reference (`billingAddressId`/`shippingAddressId`), and no endpoint exists anywhere in ShedSuite's published API to create that address record, even though `CustomerOrder` carries full address text and coordinates once populated. It's open whether the delivery address Traversence already collects through its own drop-pin flow (see Delivery Distance Calculation) can be pushed in at quote-creation time some other way, or has to be entered separately inside ShedSuite after the fact.

**FR-4.5 Status sync, corrected from an earlier webhook assumption**

- ShedSuite's marketing material describes "real-time webhooks," but no webhook-registration endpoint exists anywhere in its actual published API. Status sync is designed as scheduled polling of `GET /customer-orders/v1` (filterable and sortable by `lastUpdated`), not inbound push, unless ShedSuite confirms a webhook-configuration channel exists outside this API.
- A poll that finds a status change (sale pending, sold, delivered, RTO terms populated) updates the mirrored item and any matching local lead/Quote immediately upon the next poll cycle, not in real time.

## Non-Shed Inventory & Pricing

Containers, water tanks, septic, and culverts are managed entirely as native Traversence Market items, priced under `pricing_model: cost_markup` (`decisions/0032`, `decisions/0039`) — the field-level shape is defined in the Data Model & Fields: Inventory Items section below; this section adds the SJ-specific business rules on top of it.

**FR-5.1 Catalog and stock** — products belong to a product line and carry SKU, name, description, specs (size, capacity, gauge, condition such as one-trip / cargo-worthy), images, and an active flag. Stock is tracked per lot: quantity on hand, or unit-level records for serialized items (containers). Unit statuses: available, on hold (quote pending), sold, delivered.

**FR-5.2 Pricing** — markup can be set per product line and overridden per product; cost is never shown on the public site (matching the platform-wide rule that cost stays private to `listing_access`). A product can display "Call for price" instead of a computed price.

**FR-5.3 Price list imports** — admin imports supplier price lists as Excel or CSV, with column mapping saved per supplier on first import; PDF imports go through a one-time parsing step that produces a CSV for review before import. Every import shows a preview of changes (new, changed, unchanged) before applying, and is stored with an effective date. Past Quotes keep the prices they were created with — an import never retroactively repriced an open or completed Quote. Price imports are for non-shed lines only, or a fallback for sheds solely if ShedSuite API access is ultimately not granted to SJ Portables directly.

## Rent-to-Own Financing Partners

SJ Portables enters RTO partners once and assigns one per product line; every new RTO Quote in that line uses it until the assignment changes. Assignment is at product-line level only, never per item.

**FR-6.1 Partner records** — name, logo, apply URL, embed code (optional), phone, email, down-payment text, term options (e.g. 36 / 60 months), no-credit-check flag, terms notes, active flag. Partners can be added, edited, and deactivated; never hard-deleted while referenced by an existing Quote.

**FR-6.2 Assignment** — each product line has an RTO-eligible flag and one assigned partner, or "Handled by ShedSuite" for the ABCO line. Individual products can opt out of RTO (e.g. horse barns) but cannot pick a different partner than their line's. Changing a line's partner affects new Quotes only.

**FR-6.3 Display rules** — RTO shows on a product only when its line is RTO-eligible, the product hasn't opted out, and an active partner (or ShedSuite) is assigned; otherwise the product shows "Purchase only." Product pages and the quote form pull terms text and the apply link from the assigned partner — no financing text is hard-coded.

**FR-6.4 Safeguards** — deactivating a partner still assigned to a line shows a warning naming the affected lines; the admin dashboard flags any RTO-eligible line with no active partner.

**FR-6.5 Quote snapshot** — when a Quote is created with RTO, the partner ID and a snapshot of its terms are stored on the Quote, so a later change to the partner's terms never changes what the customer was actually offered.

## Data Model & Fields: Inventory Items

| Field | Visibility | Notes |
| --- | --- | --- |
| item\_id | System | Unique per item |
| location\_id | System | One item belongs to exactly one SJ Portables location |
| category | Public | Shed / container / tank / etc. |
| title, description | Public | Free text |
| condition | Public | Structured field, per `decisions/0031`'s item detail page pattern |
| photos | Public | Gallery, per `decisions/0031` |
| cost | Private (`listing_access` owner/manager only) | Per `decisions/0032` |
| markup | Private (`listing_access` owner/manager only) | Flat **amount or percentage — both supported, per item (resolved, per direction, 2026-09-25).** |
| price | Public | Computed from cost + markup; cost/markup never exposed in the public page or API response |
| tax\_flag | Public | Taxable / non-taxable |
| status | Public | available / sold — non-destructive; sold items are flagged, never deleted (`decisions/0032`) |

**Business rule:** cost and markup are write-restricted to a `listing_access` owner/manager grant on that item's location, enforced at the API level, not just hidden in the UI — a direct API call from an unauthorized session must not return these fields.

**Also, per direction:** every listing is independent by default — its inventory, cap, and communications are scoped to that listing alone unless explicitly linked to others via a Location Group (`decisions/0036`). Holding `listing_access` across multiple listings never by itself pools them together. Each listing is further capped at 250 items regardless of tier or grouping (`decisions/0032`).

## Functional Requirement: Quote Workflow

**States:** Requested → Accepted / Rejected → (if Accepted) Completed — a real stateful record, not a message thread (`decisions/0032`).

1. **Requested** — a buyer submits a quote request against a specific item (via the primary Message button or the inline quick-message composer, per `decisions/0031`). Creates a Quote record: item\_id, buyer\_id, location's `listing_access`-authorized seller, requested\_at.
2. **Accepted / Rejected** — SJ Portables staff with `listing_access` on that item's location responds. Rejected Quotes stay on record, non-destructively.
3. **Completion requires dual confirmation** — the seller confirms "sold" and the buyer confirms "bought" independently; only when both confirmations exist does the Quote move to Completed. Neither party can unilaterally mark it done.
4. **On Completion:** the item's status flips to sold (non-destructive), and the Quote becomes the anchor point for SJ Portables' first review/rating record on the platform (`decisions/0032`).
5. **Disputes** on a completed Quote follow the same non-destructive, status-based pattern as `decisions/0015`'s claim/dispute flow — not a new mechanism.

**Lead intake and pre-Quote pipeline, reconciled from an earlier parallel technical draft.** Before a formal Quote record exists, a raw inquiry moves through lead-nurturing stages that sit inside, or immediately before, the Requested state above: new → contacted → freight pending (for freight-quoted items) → quoted. The stateful Quote record comes into existence at "quoted" — once staff has priced the item and sent it to the buyer, which is both the first point the buyer sees a firm number and the earliest point Requested formally applies. The remaining pipeline language maps directly onto the states above: "accepted" is Accepted, "lost" is Rejected, "fulfilled" is Completed. This reconciles that draft's operational language with the stateful Quote object here — they describe one lifecycle at two levels of resolution, not two different objects.

**FR-8.1 Lead forms.** Forms live on product pages, a general "Get a quote" page, and a bundle request form (FR-8.4 below). Fields: name, phone, email, preferred contact method, product(s), payment type (cash / RTO), delivery pin, site-access answers (freight items), notes. Spam protection and a confirmation email/SMS to the customer; instant notification to the assigned lot (Communications & Notification Routing).

**FR-8.2 Quote line structure**

| Line | Source | Editable by staff |
| --- | --- | --- |
| Base product price | ShedSuite (sheds) or native catalog | No (sheds) / Yes (others) |
| ABCO options | ShedSuite builder | No |
| Dealer add-ons, custom work, site prep | Staff entry | Yes |
| Delivery | Calculator (free ≤ 50 mi for buildings) or staff freight entry | Yes (freight) |
| Payment | Cash, or RTO with partner + terms snapshot | Partner fixed by product line |

**FR-8.3 Lot attribution.** Leads are assigned to the nearest lot by road distance (FR-7.5); staff can reassign. St. Johns and Quemado share one phone line, so phone leads can't be attributed by number; online leads are always tagged. Source tracking: page, product, UTM parameters, referrer.

**FR-8.4 Bundles.** "Homestead"-style packages (e.g. cabin + water tank + septic) are quoted as one request with multiple lines; shed lines hand off to ShedSuite (FR-4.4), other lines are quoted natively. This is the `bundle_priced` model (`decisions/0039`) applied across a mixed ShedSuite/native cart.

**FR-8.5 Communications on a Quote.** Every quote request and buyer message routes through the Communications Center (Communications & Notification Routing below); accepting a Quote for a non-shed unit puts that unit on hold, matching the item-status rule in Data Model & Fields above; staff can send a Quote to the customer as a link or PDF with a stated validity date.

## Functional Requirement: Delivery Distance Calculation

- The buyer sets a delivery point by dropping a pin on a map, or entering an address that geocodes to a pin.
- Distance is calculated as **real road-routing distance** from the item's location to that pin — not straight-line (haversine) distance. This matters specifically because SJ Portables' service area includes rural roads, where straight-line distance materially understates actual travel distance.
- The calculated distance displays to the buyer as part of or alongside the quote request, so both sides share the same delivery-distance expectation before a Quote is Accepted.
- **Open dependency:** no routing/mapping provider has been selected yet. **Resolved in shape, per `decisions/0040`:** the map/pin-drop UI (a library such as Leaflet) and the routing engine that supplies real road-distance geometry (OSRM, GraphHopper, Mapbox Directions, or Google Distance Matrix) are two separate concerns — for a rural service area like this one, a paid provider (Mapbox or Google) is recommended over self-hosted OSRM, since rural road-data coverage varies meaningfully and self-hosted OSRM depends entirely on OpenStreetMap's coverage of remote roads. **The specific provider choice itself is still open** and flagged in Open Dependencies below. **Also per `decisions/0040`:** the buyer can propose the delivery route themselves by dragging waypoints at intake (they often know the actual accessible path — a specific gate, a side road — better than a generic router), and staff verifies or directly corrects that proposed route (waypoint-dragging only, never freehand) before the delivery line is priced; see FR-7.2 below.

**FR-7.1 Location input.** Map with a draggable pin, plus address search and "use my location." Pin mode is required for rural parcels without a routable address; the pin's coordinates are stored with the lead/Quote.

**FR-7.2 Distance and route verification, extended per `decisions/0040`.** Road (driving) distance from the pin to all three lots via the routing engine chosen (still open — see Open Dependencies); nearest lot chosen by road distance, never straight-line. At intake, the buyer may drag the route (waypoint-dragging, not freehand) to show the actual accessible path if the default route looks wrong to them — useful specifically for the rural addresses this calculator exists to handle. That proposed route attaches to the lead as `buyer_proposed`. Before the delivery line is priced, staff (`listing_access` on that lot) reviews the route on the map and either approves it (`staff_verified`) or corrects it directly (`staff_corrected`) — no round-trip back to the buyer is required. The verified route and its resulting distance then lock to the Quote, the same way RTO terms and other quote lines already lock elsewhere in this document, so a later change to the per-mile rate or routing provider never silently reprices an existing Quote. Results are cached per pin to limit API cost.

**FR-7.3 Rules by product line**

| Product line | ≤ 50 road miles | > 50 road miles |
| --- | --- | --- |
| ABCO portable buildings | "Free delivery & setup" shown instantly | Per BRD (pricing rule pending) |
| Containers, water tanks, septic, culverts | Freight quote request | Freight quote request |

**FR-7.4 Site-access questions (freight items).** Road type to site, gate width, overhead clearance, slope/levelness at the drop spot, ground surface (dirt, gravel, pad), notes and photos — these travel with the Quote so staff can price freight without a follow-up call.

**FR-7.5 Outputs.** Nearest lot, road miles, free-delivery eligibility, and access answers save on the lead/Quote; the nearest lot sets the lead's lot attribution (Communications & Notification Routing, and FR-8.3 below).

## Functional Requirement: Communications & Notification Routing

- Every quote request and buyer message routes through Traversence's existing Communications Center (`prd.md` Tier 2.5) — no separate messaging system for SJ Portables.
- Each listing has its own assigned recipient — a specific email or an assigned listing manager — per `decisions/0036`; routing is per-listing by default, not pooled across locations.
- Because SJ Portables' three locations are explicitly linked into a Location Group (`decisions/0036`), the admin panel gives cross-location visibility into all three listings' communications — but each message still routes to its own listing's assigned recipient first; the group view is a read/manage surface on top of that, not a replacement for it.
- Sales response is tracked per Quote: requested\_at, first\_response\_at, resolved\_at (Accepted/Rejected/Completed). **Resolved, per direction 2026-09-25:** there is no platform-enforced SLA — SJ Portables defines its own sales process and target response time within its listing profile, and actual response times are shown against that self-defined target rather than a universal standard.
- **Resolved, per direction 2026-09-25:** notification channel is in-app (always on) plus opt-in email — a listing's assigned recipient can enter an email address to receive notifications via SMTP from the server, each one linking back to the specific item/thread in the Communications Center.

## Functional Requirement: Admin Panel

- **Inventory view:** all three linked locations' items in one place (enabled by their explicit Location Group, `decisions/0036`), filterable by location and status (available/sold); shows cost, markup, and computed price per item; shows each listing's running count against its own 250-item cap (`decisions/0032` — there is no separate group-wide cap; linking is for shared visibility only).
- **Quote view:** every Quote across all three locations, filterable by state (Requested/Accepted/Rejected/Completed) and by response time.
- **Location/staff assignment:** each listing has its own assigned recipient (a specific email or an assigned listing manager). **Resolved, per direction 2026-09-25:** this is `listing_access` itself — whoever holds an owner/manager grant on a listing is its assigned recipient by default, with no separate staff-login system to design (`decisions/0036`).
- The cross-location view exists only because SJ Portables explicitly linked its three listings into a Location Group — it is not automatic just because one account manages all three (`decisions/0036`).

**Module breakdown, merged from the concrete technical draft:**

| Module | Key functions |
| --- | --- |
| Dashboard | New leads by lot, quotes awaiting freight, sync health, RTO partner warnings |
| Leads & quotes | List/filter by lot, status, product line; build and send quotes; reassign lot; ShedSuite push status |
| Shed mirror | Read-only view of synced sheds, last sync time, manual sync, sync log |
| Inventory (non-shed) | Products, per-lot stock and units, cost, markup, retail, status |
| Price imports | Upload Excel/CSV/PDF, map columns, preview changes, apply with effective date |
| Financing partners | Add/edit partners, assign one per product line, deactivate with warnings |
| Delivery settings | Lot coordinates, free-delivery radius (default 50 mi), rules per product line, >50 mi pricing (per BRD) |
| Business settings | Lots, addresses, phones, hours, service-area towns, contact names |
| Users & roles | Owner (all), Lot manager (own lot's leads, quotes, stock), Staff (view, update lead status) — this maps onto `listing_access` and the Executive/delegated-role model in the Traversence FRD, not a separate staff/role system |

**FR-9.1 Audit.** Price changes, partner assignment changes, quote edits, and lot reassignments are logged with user and timestamp — the same dual-accounting requirement stated under Non-Functional Requirements below, applied specifically to these admin actions.

**FR-9.2 Notifications.** New lead → in-app plus opt-in email/SMS to the assigned lot; sync failure or push/poll errors → email to the owner.

## Functional Requirement: Catalog Cap Enforcement

- **One cap applies, per direction 2026-09-25:** every individual listing is capped at 250 items, whether or not it's linked into a Location Group. There is no separate combined/group cap — an earlier version of this requirement also capped a linked group's total at 500, which is removed.
- Linking SJ Portables' three locations into a Location Group (`decisions/0036`) has no effect on their catalog capacity — each location independently manages its own items against its own 250-item ceiling, so there's no interaction to account for between the three.
- At 249 items on a given listing, creation proceeds normally. At 250, creating a new item on that listing is blocked with a clear message, rather than a generic error — other linked listings are unaffected.
- The admin panel's inventory view (above) surfaces each listing's own count against its own 250-item cap.

## Functional Requirement: Promotional-Period SEO/Indexing Control

- While SJ Portables' account is in promotional status, its generated pages carry `noindex, nofollow` — not indexed, not followed by search engines (`decisions/0034`).
- This must be driven by the account's Subscription record (`decisions/0035`), specifically its `source` field (promotional vs. paid) — never a manual per-account toggle someone has to remember to flip.
- When the account converts to paid, or the review workflow confirms continuing promotional status under different terms, the directive is removed on the next page regeneration — automatically, not as a manual follow-up step.
- **Blocking dependency:** this cannot be implemented until the Subscription record and its `source` field exist (see Open Dependencies) — there is no interim manual workaround planned, so this requirement is sequenced behind that schema work.

## Non-Functional Requirements

- **Access control:** cost/markup visibility and item write access are enforced via `listing_access` at the API level (`decisions/0002`) — never a UI-only hide.
- **Dual accounting:** every write (item edit, Quote action, message) is stamped with a real `actor_user_id`, and, for location-scoped writes, the `listing_access`-authorized `entity_id` (`decisions/0002`).
- **Data integrity:** sold items and rejected Quotes are status-flagged, never deleted — preserving history for review/rating and dispute records.
- **No in-platform payment processing:** negotiation and payment happen off-platform once a Quote is Accepted (`decisions/0031`) — this build never becomes a payment-processing party.

## Data Model Summary

Core tables below, merged from the concrete technical draft; exact columns are finalized during technical design (see Open Dependencies & Questions). These are the schema this listing's Traversence-native build needs — not a separate system — per the Architecture & Systems of Record correction above.

| Table | Purpose | Key fields |
| --- | --- | --- |
| `lots` | The three yards | name, address, lat/lng, phone, hours, manager |
| `product_lines` | ABCO buildings, containers, tanks, septic, culverts | name, source (shedsuite / native), rto\_eligible, financing\_partner\_id, handled\_by\_shedsuite, default\_markup, delivery\_rule |
| `financing_partners` | RTO providers | name, logo, apply\_url, embed\_code, phone, email, down\_payment\_text, term\_options, no\_credit\_check, terms\_notes, is\_active |
| `products` | Non-shed catalog | product\_line\_id, sku, name, specs, cost, markup\_override, retail\_override, rto\_opt\_out, call\_for\_price |
| `inventory_units` | Stock per lot | product\_id, lot\_id, serial/qty, status |
| `shed_mirror` | Synced ShedSuite sheds | shedsuite\_id, lot\_id, model, size, specs, cash\_price, images, listing\_url, status, synced\_at |
| `leads` | Every inquiry | contact info, lot\_id, source, pin lat/lng, road\_miles, free\_delivery, access answers, shedsuite\_contact\_id, push\_status |
| `quotes` / `quote_lines` | Priced offers | lead\_id, status, payment\_type, financing\_partner\_id, financing\_terms\_snapshot, valid\_until; lines with source, price, locked flag |
| `price_imports` | Import history | supplier, file, effective\_date, change summary |
| `sync_log` | Integration health (polling, not webhooks) | run details, items added/updated/removed, errors |
| `audit_log` | Change history | user, entity, change, timestamp |

## Acceptance Criteria

| Scenario | Expected result |
| --- | --- |
| Item with cost $500, markup 20%, taxable | Public price shows $600 + tax noted; cost and markup appear in no public page or API response |
| A listing at 249 items, staff creates one more | Creation succeeds; count now shows 250/250 |
| A listing at 250 items, staff attempts to create one more | Blocked with a clear "catalog limit reached" message, not a generic error |
| Buyer requests a quote, seller accepts, buyer confirms "bought," seller confirms "sold" | Quote status becomes Completed; item flips to sold; review prompt appears for both parties |
| Buyer requests a quote, seller accepts, only the seller confirms "sold" | Quote stays Accepted, not Completed — completion requires both confirmations |
| Delivery pin dropped on a rural address 2 miles straight-line but 14 miles by road | Displayed distance is \~14 miles (road-routed), not 2 |
| Account's Subscription source = promotional | Generated pages carry `noindex, nofollow` |
| Account's Subscription source changes to paid | `noindex, nofollow` is removed on next page regeneration, with no manual step |
| A listing at 250 items is linked into a Location Group with other listings well under their own caps | Creation on that listing is still blocked at 250 — linking does not raise or pool the cap; the other listings are unaffected |
| Three linked listings each independently reach 250 items (750 total across the group) | All three succeed — there is no group-wide cap; each listing's 250-item cap is evaluated entirely on its own |
| A user holds `listing_access` on two unrelated businesses' listings | Their inventories and communications stay fully separate — no shared search view or combined management — unless a Strategic or Cornerstone account explicitly creates a Location Group linking them, which still never pools their catalog caps |

## Open Dependencies & Questions

These block a real technical build and need answers or a design pass before development starts, not assumptions:

- [ ] **Schema design itself** — `market_items`, `quotes`, `subscriptions`, `services`, `counterparties`, and `location_groups` tables are named as requirements across this FRD and the ADRs but not yet designed as real schema (`decisions/0032`, `decisions/0035`, `decisions/0036`).
- [ ] Routing/mapping provider for real road-distance calculation — not yet selected. **Shape resolved (`decisions/0040`):** map UI and routing engine are separate; recommendation is a paid provider (Mapbox or Google) over self-hosted OSRM given this service area's rural road coverage needs — but the actual provider and its cost budget remain SJ Portables' choice to make.
- [x] **Markup structure** — resolved: both flat dollar amount and percentage are supported, per item.
- [x] **Notification channel** — resolved: in-app (always on) plus opt-in email via SMTP, linking back to the specific item/thread.
- [x] **Staff login structure** — resolved: staff assignment is `listing_access` itself, no separate staff/role system.
- [x] **Response-time expectation** — resolved: no platform-enforced SLA; each business defines its own sales process and target response time within its listing profile.
- [x] **Catalog cap model** — resolved: per-listing 250-item cap only; the combined Location Group cap is removed, so linking listings together has no catalog-capacity interaction to resolve.
- [ ] **The `noindex, nofollow` hook** — sequenced behind the Subscription schema existing; not buildable before that.
- [ ] **Location Group creation — partially resolved, per direction 2026-09-25 (authority model corrected same day):** structural authority — creating a Location Group, or creating further delegated roles — belongs to the owner alone, with one narrow exception: the owner may designate exactly one manager as **Executive**, authorizing that one person to also link listings and create roles. No further delegation past that one level — an Executive can't designate another Executive or pass the authority on. Every other manager, and every role created below manager, has neither capability regardless of configuration (`decisions/0036`, `decisions/0037`). Each listing being linked must also hold its own individual Strategic or Cornerstone subscription — not one account-wide grant. Still open: the actual admin-panel UX for performing the link, designating an Executive, and configuring a delegated role's permissions — none of that schema or UI is designed yet.

**Additional open items, carried in from the concrete technical draft this document now merges (not yet resolved):**

- Can ShedSuite's 3D builder be embedded or deep-linked with SJ preset as dealer, or is it a plain hand-off link only?
- Delivery pricing past 50 miles for portable buildings — the BRD names this as a rule to define; not yet set.
- Confirm the 50-mile free-delivery radius is measured in road miles, not straight-line, consistent with the road-routing requirement elsewhere in this document.
- Container RTO partner name, terms, and apply method (link or embed) — and whether the "$99 down" figure found in early research actually belongs to that partner or to something else.
- Confirm service-area towns, given ABCO territory rules and a possible existing dealer in Pinetop–Lakeside.
- Quemado lot hours — still to confirm.
- Supplier price-list formats for tanks, septic, and culverts, needed to finalize the FR-5.3 import-mapping design.
- Google Business Profile: change SJ Portables' primary category from "Portable building manufacturer" to a dealer category, matching the BRD's NAICS/industry-classification note.

**Resolved by merging in ShedSuite's real API spec (`decisions/0039`), no longer open:** whether the ShedSuite API returns RTO monthly payments (it doesn't — see FR-4.1's gap and its downstream effect on catalog display); whether ShedSuite offers real-time webhooks (it doesn't — FR-4.5 is designed as polling instead); whether this build is a separate custom system from Traversence or a native one (resolved: native — see Architecture & Systems of Record); and how delivery gets priced when an item's cost is known but its delivery cost isn't (resolved: the `delivery_method` contract and buyer-proposed/staff-verified routing, `decisions/0040` — see FR-7.2 above).

**A platform-level gap this merge surfaced, not yet resolved anywhere — flagged for the Traversence FRD, not just here.** The Traversence FRD's Tier 2.8 section states that a visitor "requesting a quote on a specific item creates a stateful Quote record" immediately (`decisions/0032`). That assumes the item already has a fixed, computed public price, which holds for SJ's ShedSuite-mirrored and native cost-marked-up items — but not for anything routed to a freight quote (containers, tanks, septic, culverts beyond a simple case, per FR-7.3), where no price exists yet for a buyer to request against. This document resolves that locally by treating those as a pre-Quote `leads` record until staff prices them (see "Lead intake and pre-Quote pipeline" above), but the generic Market construct has no formal pre-Quote/inquiry object for this case today. Worth a small addition to `decisions/0032` or a follow-up ADR: a formal pre-Quote inquiry state (or an allowance for `landed_cost_plus`/case-by-case items to skip straight to a staff-priced Quote rather than assuming Requested always starts from a fixed price).
