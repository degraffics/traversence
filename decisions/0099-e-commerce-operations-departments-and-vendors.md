# 0099. E-Commerce operations: six departments, the order lifecycle, and outsourced vendors

- **Status:** Accepted (Jason, 2026-10-16); extends 0098 (the order system, E-Commerce admin area)
- **From:** Jason's operations brief (2026-10-16): six departments as stages of the order lifecycle (customer service,
  pre-press, inventory and purchasing, production, shipping, accounting), with outsourced manufacturers as an
  automated tier of production and fulfillment. "I really want this part of the platform built correctly."

## Decision (proposed)

### 1. Departments are stages and staff capabilities, not separate apps

Each department is **a queue in E-Commerce** and **a capability a staff member can be granted** (the staff side of
`Roles`, decisions/0055: today `accountant` holds `money`). One person may hold all six (the solo case); as the team
grows, each queue gets its own people without changing the software.

| Department | E-Commerce queue | Capability | Does |
|---|---|---|---|
| 1. Customer service & intake | **Intake** | `orders` | verify details, address, instructions; quotes; changes and cancellations before approval; payment holds; **Fix / Credit** (0098) |
| 2. Pre-press & proofing | **Proofs** | `prepress` | check uploaded art (resolution, bleed, color mode, size); make the proof; send it; record the customer's approval (the line becomes final, 0098); prepare production files |
| 3. Inventory & purchasing | **Materials** | `inventory` | stock on hand / reserved / available for blanks and substrates; reorder points; purchase orders to vendors |
| 4. Production | **Production** | `production` | job tickets (each with a GO Code to scan at each step); in production → quality check (against the spec sheet) → done |
| 5. Shipping & fulfillment | **Shipping** | `shipping` | pack (box, tube, mailer by size), label, tracking, Shipped; or Ready for pickup |
| 6. Accounting | **Payments** + **Reports** | `money` (exists) | match payments, deposits and fees; tax (QuickBooks Automated Sales Tax); refunds and chargebacks; vendor bills; margins |

Admins (`owner`) see everything. Grants are given in Admin → People & roles, like other staff roles.

### 2. The order lifecycle (one record, with fulfillment units)

- **Order** (0098): placed → paid (or on hold: payment, fraud, address) → in fulfillment → completed; or cancelled
  (before approval), with the money ledger alongside (charges, credits, refunds, chargebacks).
- **Order lines** each carry their product, options and files (the customer's choices, stored with the line), and an
  **approved at** (after which they're final).
- **Fulfillment units**: the order splits into units by who makes and ships them (in-house, or each vendor). Each unit
  has its own status and tracking, so a customer gets separate tracking for in-house and outsourced items. Unit
  statuses: `intake → proof → materials → production → quality → packed → shipped | ready_for_pickup → delivered`,
  skipping what a product doesn't need (a GO Code or a design pack has no materials or shipping; an off-the-shelf
  item skips proof and production).
- **Routing rules per product**: `fulfillment_type` (`digital`, `in_house`, `outsourced`), `vendor_id`, `vendor_sku`,
  needs proof (yes/no), materials it uses (for made-to-order: decrement substrates by size × quantity), packaging
  class (mailer, box, tube) and weight.
- Every status change is logged (who, when, note) and the customer is emailed at the steps that matter to them: proof
  ready, approved, shipped (with tracking), ready for pickup, delivered.

### 3. Outsourced vendors

A **vendor** is a Counterparty (decisions/0035): company, contacts, the products they make (our SKU → their SKU, their
cost), their shipping cost, how we hand off work, and our return address for blind (white-label) shipping.

Hand-off, in the order we'd build it:
1. **Vendor portal + email work order** (method C, first): the vendor gets an email with the job, the print-ready
   files and our branded packing slip, and a private link to a restricted **vendor portal** listing only their jobs,
   where they confirm received → in production → shipped (carrier + tracking). No vendor needs an API to start.
2. **CSV batch** (method B), for vendors who want a daily or hourly file.
3. **API / webhook** (method A), per vendor, when one offers it (POD and large trade printers).

Whichever method, the vendor's updates land on the fulfillment unit: Shipped with tracking notifies the customer
automatically. Files go to vendors as private, expiring links, never public addresses.

### 4. Money for outsourced work

Each outsourced unit records its **cost**: vendor item cost + vendor shipping. A **vendor purchase order** is created
in QuickBooks when the unit is sent to the vendor (QuickBooks PurchaseOrder), and becomes a **bill** when the vendor
invoices (Bill), so vendor payables are in the books. **Margin per order** = what the customer paid − (vendor costs +
the payment fee). Reports show revenue, cost of goods, fees and margin by product and by vendor.

### 5. What's sized for now, and what waits

- **Now:** the queues and capabilities; fulfillment units and statuses; products with options and routing rules;
  proofs and approval; the vendor portal and email work orders; shipping as flat rates or local pickup, tracking typed
  in; vendor costs, purchase orders and bills in QuickBooks; margins.
- **Later, when volume asks for it:** live carrier rates and labels (EasyPost or ShipStation); CSV and API vendor
  hand-offs; full inventory with reorder points and purchase orders for stock (until then, materials are tracked only
  for what we hold); international shipping and customs.
- **Files** (uploads, proofs, production files) need object storage (S3, Cloudflare R2 or Bunny; the same need as
  member storage, commercial.md §1.2), not Bluehost's disk.

## Decided (Jason, 2026-10-16)

1. **Traversence makes no physical products in-house**, only labor and professional services (design, video, setup,
   management). So: **every physical O2O product is outsourced**; in-house work is services only. The **Materials**
   (inventory) and in-house **Production** queues aren't built: physical production is the vendors', tracked through
   their fulfillment units. Services get a **Service work** queue (design, video, setup) with the same proof and
   approval steps.
2. **Vendors are configurable**, not hard-wired: in E-Commerce → Vendors, staff add a vendor and define **its own
   order process**: how it receives work (email work order, the vendor portal, a CSV file, later an API), what it
   needs with each job (files and their format, the packing slip, mockups, a PO number), the steps it reports (e.g.
   received → proof → production → shipped), its terms (prepaid, card, Net-30), its products (our SKU → its SKU, cost)
   and its lead times. Each product then names its vendor; the routing follows that vendor's process.
3. **Shipping is configurable per vendor and per product**: **the vendor ships** (blind, with our return address and
   packing slip; it reports carrier and tracking), **we coordinate shipping** (we arrange the carrier, enter or buy the
   label, give the vendor the label or pickup), or **local pickup** / delivery. Rates per product: free, flat, by
   quantity, or quoted; carrier rates and labels (EasyPost or ShipStation) later.
4. **File storage: Amazon S3** (Jason: it also makes sense for video uploads instead of YouTube embeds). Uploads,
   proofs and production files go to a private bucket and are reached through short-lived signed links (to customers,
   staff and vendors). The code uses the S3 API, so it's not tied to one provider. For video served to the public, a
   CDN in front (CloudFront) keeps download costs down; to be costed when video upload is built.
5. **Pay after proof** (0098) is the default for custom products, configurable per product: pay after proof, a deposit
   at checkout with the balance on approval, or pay at checkout (ready-made items, GO Codes, services sold as fixed
   packs). Jason hasn't overridden the default.

## Build order (with 0098)

1. E-Commerce → Orders and Payments, the order model and ledger, GO Codes through trvgo.us (0098).
2. S3 for files (needed by proofs and uploads).
3. Products with options and routing rules (vendor, payment timing, shipping mode); fulfillment units and the queues
   (Intake, Proofs, Service work, Vendor jobs, Shipping); department capabilities; customer emails at each step.
4. Vendors, configurable: their order process, the vendor portal and email work orders, blind or coordinated
   shipping, costs, QuickBooks purchase orders and bills, margins.
