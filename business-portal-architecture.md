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
| Tax by jurisdiction | **New:** a tax rate per place the business collects in; Stripe Tax as an option where their gateway is Stripe. |
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
| Single items (offers, no checkout) | ? (to decide) | ✓ | ✓ | ✓ | ✓ |
| Catalog and checkout (their gateway, 0%) | | ✓ (cap to set) | ✓ | ✓ | ✓ (250) |
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
| Webhooks, API, QuickBooks sync | | | | ✓ | ✓ |
| Multi-location inventory, Nexus and Co-Op hub, master communication vault | | | | | ✓ |
| Team | Owner | + 1 | up to 5 | up to 15 | unlimited (+ seats per location) |
| AI catalog drafts (HITL) | 0 | 5 / mo | ? | 25 articles + 4 posts / mo | uncapped |

Seat numbers, the Basic/Core caps and Core's AI are proposals, not AOS. The rest follows AOS §5B.

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
3. **Checkout through their gateway** (Stripe first), with taxes and shipping rules.
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
