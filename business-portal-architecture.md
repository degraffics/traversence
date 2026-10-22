# The business portal: our admin, scoped to a business and gated by plan (outline for discussion, 2026-10-22)

**Jason (2026-10-22):** "the business[es] are built on the same functionality we have as the platform admins, just
segmented into plans with tools that make the revenue model work." The plans stay ours: AOS §5B and commercial.md.

This revises the "Shop module" in `marketplace-architecture.md` §3C. The business side isn't a separate shop builder.
It is **the same engine** (decisions/0109: modules → sections → widgets → workflows), with three changes.

## 1. Three changes to the engine

1. **Scope:**
   - every tool works for Traversence (tenant 0, all of our own data) or for one business (its own data only);
   - our records get an owner: orders, products, customers, leads, promotions, GO Codes, workflows;
   - a row with no business on it is ours.
   - So our client-order builder, our leads pipeline and our promotions *are* the business's tools, with nothing
     written twice.
2. **Who:**
   - in the business portal, the AOS §9B business roles take the place of staff levels;
   - the roles are Owner, Manager, Accountant, Sales, Support, Creator and Developer (`Team::can`);
   - the greyed-out rule stays: what a role can't do shows greyed with the reason.
3. **Plan (entitlements):**
   - each tool and limit is opened by the listing's plan: `Plan::allows(listing, feature)` and `Plan::limit(listing, items|seats|storage|ai|email|sms)`;
   - a tool the plan doesn't include **shows greyed, with "Included in Core: see plans"**;
   - the same greyed-out pattern doubles as the upgrade path.

**Address:** `/business/<listing>/<module>/<section>`, drawn by the same router, widgets and kit. A Nexus parent sees
its locations as a filter (AOS §6).

**Admin first (Jason, 2026-10-22):** "Admin should have any and everything the business would." Traversence is
business zero: every business tool is built in our admin first, for our own data, then opened to a business by scope
and plan. Nothing is built only for businesses.

| Business module | In our admin (2026-10-22) |
|---|---|
| Dashboard, Inbox | ✓ Dashboard, Pulse |
| Quotes & invoices | ✓ client orders (Net terms, pay links) |
| Catalog | ✓ Products (no stock or import yet) |
| Orders & fulfillment | ✓ Orders, Vendor orders, labels |
| Marketing | ✓ Promotions, GO Codes; ✗ campaigns, automations |
| Reports | partial: Insights; ✗ margin, new and returning customers |
| Settings & team | ✓ Configuration, workflows, people |
| Customers (CRM) | ✓ Growth → Customers (decisions/0111): profiles from orders and plans, segments, tags, notes |
| Storefront & pages | ✗ |
| Taxes, shipping and pickup rules | ✗ |
| B2B price lists, volume breaks | ✗ |

So the build order in §5 starts by closing these gaps in our admin, CRM first, then scopes them per business.

## 2. The business portal's modules (mirroring ours)

| Business module | Ours it comes from | What the business does there |
|---|---|---|
| **Dashboard** | 1. Dashboard | today's orders, quotes, messages, low stock, numbers |
| **Inbox** | 2. Pulse | customer messages, guest questions, offers, form submissions, order events; reply, assign, note |
| **Customers (CRM)** | 7. Sales: leads, prospects | customer profiles (contact, orders, lifetime value, messages, notes); tags and segments (Wholesale, Retail, VIP, Inactive); leads pipeline (inquiries, quote requests, form leads) |
| **Quotes & invoices** | 9. Client orders | the client-order builder (lines, proofs, terms, deposit, **Net 15 / Net 30** already built, a pay link, approve and pay) |
| **Catalog** | 9. Products | items with private cost and markup, run sizes, options, stock, categories, import |
| **Orders & fulfillment** | 9. Orders, Vendor orders | orders through stages (paid → in production → ready / shipped → done); packing and shipping labels (built for ours); vendor jobs |
| **Storefront & pages** | (new; listing profile sections) | theme, pages (About, Services, Gallery, FAQ, Contact), forms that feed the leads pipeline, SEO title and description, share image, sitemap.xml and JSON-LD made automatically; trvgo.us name, own domain |
| **Marketing** | 7. Promotions; 5. GO Codes | discount codes (with the business's own floor), QR codes and short links, email campaigns and automations |
| **Reports** | 10. Insights | sales, margin (from their private cost), items turning over, customers new and returning, where they came from |
| **Settings & team** | 11. Configuration, 9. People | team and roles, payments (their gateway), taxes, shipping and pickup rules, domain, notifications, **their workflows** (the builder), integrations (QuickBooks, webhooks) |

## 3. The review's points: what changes, what was already covered

| Point | Effect on the plan |
|---|---|
| CRM as its own pillar | **Adopted:** it's the Customers module, from our leads pipeline. Customer profiles are built from orders, offers, messages and form leads. Tags and segments are new. |
| B2B price lists per customer group | **New:** a price list per segment (Wholesale) and volume breaks. Net terms already exist in our client orders. |
| Lead pipeline from the microsite | **Adopted:** site forms create leads in Customers and show in the Inbox. |
| Automated email / SMS (confirmations, shipping, follow-ups, abandoned cart) | **Split:** order, quote and shipping emails are part of operating, on every paid plan. Marketing automations and SMS draw on the plan's email/SMS volume (AOS §5B: Strategic and up), or a pack (§13B). |
| Quotes and invoices with pay links | **Already built for us** (client orders); it becomes the business's. |
| Order management states | **Already built** (the client-order workflow); the business can edit its stages on the plans that open the builder. |
| Carrier labels and rates | **Later:** labels as files and tracking numbers exist; carrier APIs (rates, printed labels) are a later step. |
| Tax by jurisdiction | **New:** a tax rate per place the business collects in; automatic tax from the business's payment gateway where it offers one. |
| Shipping matrix | **New:** flat, by weight, free over an amount, local pickup, local delivery by ZIP or distance. |
| CMS pages, SEO, sitemap | **Adopted:** Storefront & pages. JSON-LD is already AOS Core ("automated local SEO"). |
| Analytics and margin | **Adopted:** Reports, from the same widgets as Insights. |
| QuickBooks / Xero, webhooks, API | **QuickBooks first:** our connector, run per business. Webhooks and the API on Strategic and up (AOS §5B, §11.2). |
| RBAC presets | **Already decided:** the seven roles (§9B, 0102). A **Viewer** (read-only) role is the one the review adds. |
| Their tier table | **Not used** (Jason): ours stays. |

## 4. Where the new pieces sit in our plans (proposal, AOS §5B order)

| | Free | Basic $29.95 | Core $79.95 | Strategic $229.95 | Cornerstone $399.95+ |
|---|---|---|---|---|---|
| Listing, inbox, reply | ✓ | ✓ | ✓ | ✓ | ✓ |
| Catalog (items, offers, no checkout): commercial.md Free spec | ✓ | ✓ | ✓ | ✓ | ✓ |
| Card checkout through their own payment gateway (0% system processing fees; the processor's own fees apply) | | | ✓ | ✓ | ✓ |
| Manual payments (Zelle, bank transfer, check, pay on pickup), marked paid by the business: **members, Free and Basic sell this way only; gateway checkout starts at Core** (Jason, 2026-10-22) | ✓ standard | ✓ standard | ✓ | ✓ | ✓ |
| Quotes and invoices with pay links | | ✓ | ✓ | ✓ | ✓ |
| Customers: profiles and order history | | ✓ | ✓ | ✓ | ✓ |
| Order emails (confirmations, shipping) | | ✓ | ✓ | ✓ | ✓ |
| Taxes, shipping and pickup rules | | ✓ | ✓ | ✓ | ✓ |
| Discount codes | | ✓ | ✓ | ✓ | ✓ |
| Storefront, pages, forms, SEO, own domain | | Shop tab on the listing | ✓ | ✓ (+ subdomains) | ✓ (+ one per location) |
| Dynamic QR and short links | | | ✓ | ✓ | ✓ |
| Tags, segments, low-stock alerts | | | ✓ | ✓ | ✓ |
| Leads pipeline, automations, campaigns (email/SMS/push volume) | | | | ✓ (10k / 500 / 10k) | ✓ (50k / 2,500 / 50k) |
| B2B price lists, volume breaks | | | | ✓ | ✓ |
| Their own workflows (the builder) | | | | ✓ | ✓ |
| Webhooks, headless API, **embeddable widgets / buy buttons** (commercial.md Strategic), QuickBooks sync | | | | ✓ | ✓ |
| Multi-location inventory, Nexus and Co-Op hub, master communication vault | | | | | ✓ |
| Team roles (the 8 business roles) | Owner | ✓ | ✓ | ✓ | ✓ + custom roles and one Executive (0037) |
| AI Assist credits a month (0108, built) | 0 | 10 | 50 | 250 | 500 |
| AI catalog articles (HITL) | 0 | 5 / mo (AOS §5B) | 5 (cumulative) | 25 + 4 posts / mo | uncapped |

Catalog item caps for Basic, Core and Strategic are still open (commercial.md leaves item counts to "sensible baseline
minimums"; 0032 sets Cornerstone at 250 per listing). Everything else follows commercial.md and AOS §5B.

## 5. Effect on the build order

1. **Make the engine multi-business**, before any business tool:
   - an owner (listing) on the records;
   - `Plan::allows` / `Plan::limit`;
   - business roles in Access;
   - the business portal frame at `/business/<listing>/…`;
   - the greyed upgrade notes.
   - This is the merchant side's step 1.
2. **Move our own tools across**, one at a time:
   - Quotes & invoices, Catalog and Orders first (they exist today);
   - then Customers, from leads plus orders;
   - then Discount codes and GO Codes.
3. **Checkout through the business's own payment gateway** (any gateway on the ledger), with taxes and shipping rules.
4. **Storefront & pages** on trvgo.us and their own domain: pages, forms, SEO.
5. **CRM depth:** segments, B2B price lists, automations and campaigns (with the email and SMS metering from the AOS gaps list).
6. **The Marketplace's public side** (person-to-person posts, offers, dual confirmation) can run alongside from step 1. It uses the same item and offer records.

## 6. To decide

1. Is this the shape: one engine, our tools scoped to a business and opened by plan, with greyed tools as the
   upgrade path?
2. The tier placement in §4 (especially Free and single items, Basic's catalog cap, team seats, Core's AI).
3. A Viewer (read-only) role added to the seven?
4. B2C and B2B both from the start (price lists at Strategic), or B2C first?
5. Basic: the Shop tab on their listing plus pay links. Also **buy buttons to embed on their existing website**?

## 7. Decided (Jason, 2026-10-22)

1. **The shape:** one engine; our tools scoped to a business and opened by plan; greyed tools are the upgrade path.
2. **Ownership:** every record has an owner, with **no way to reach data that isn't ours or the business's** (§8).
3. **Business roles:**
   - listings keep their own role structure (AOS §9B, 0102);
   - **Viewer (read-only)** is added, making eight roles: Owner, Manager, Accountant, Sales, Support, Creator, Developer, Viewer.
4. **Plans are configured in our admin:**
   - Back-Office → Plans gets the **plan configurator** (AOS Part B #9's PlanEditor): prices, allowances (storage, AI credits, email/SMS/push, catalog cap), and which tools each tier opens;
   - **add-on pack prices too** (storage, AI credits, email, SMS, push), editable, starting from AOS §5D (storage) and §13B (email, SMS, push);
   - versioned like workflows; a change applies at each account's next renewal;
   - checked against the 65% floor.
   - Today the plans are fixed in code (`Billing::PLANS`); the configurator moves them into the database.
5. **Selling to consumers and to businesses are activations, not defaults.** A business switches on Retail and/or
   Wholesale (the tool-activation pattern, decisions/0049). Wholesale price lists open at Strategic.
6. **What commercial.md already settles:**
   - Free businesses have the catalog (its Free spec: "a fully operational, self-serve marketplace catalog");
   - **manual payments only on Free and Basic; card checkout through the business's own gateway starts at Core** (Jason, 2026-10-22, changing commercial.md's Basic line);
   - AI is by AI Assist credits (0108: 10 / 50 / 250 / 500), plus the article quotas;
   - embeddable widgets (buy buttons) are Strategic ("headless API and widgets").

## 8. Keeping each business's data its own (how it's enforced)

> **Superseded by decisions/0110** (three realms, separate database users, tenant views): the points below are its walls 4 and 5.

- **One gate:**
  - every business tool runs through one scoped layer that adds `owner = this business` to every read and write;
  - the business comes from the signed-in person's membership (`listing_memberships`), **never from the address or the request**;
  - a tool can't run a query without it.
- **Checked twice:**
  - before any change, the record is reloaded and its owner compared with the business;
  - a mismatch is refused and logged as a security event.
- **Files** (photos, documents, labels) are served through the same check, never by a public path.
- **Our team** sees a business's data only through support access (a standing grant or a ticket, 0102). It shows the
  "agent is in" banner and is written to the action log.
- **Tested on every build:** a test signs in as business A and tries every tool, item and file of business B; every
  attempt must fail.
- **Nexus parents** see their own locations' data through the Nexus link, nothing wider (AOS §6).
