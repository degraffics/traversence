# Admin workspace map (draft)

Status: Draft for Jason's corrections (2026-10-21). Follows Master AOS v43 §8 (the 11 workspaces) and §9A (levels and
departments). Every existing admin tool is placed below; nothing is dropped.

**How to read it:**
- **H = home:** the tool is configured here (its settings, rules, templates). Each tool has exactly one home.
- **U = use:** the same tool embedded here for the work, filtered to this place. No settings.
- **Status:**
  - **Live:** built; moved as it is;
  - **Partial:** built in part; the gap is named;
  - **Coming:** not built; shown as a placeholder until its wave.
- **❓** marks something for Jason to define or confirm.
- **Departments:**
  - **Ops:** Operations;
  - **Fin:** Finance;
  - **Sal:** Sales;
  - **Svc:** Service;
  - **Con:** Content;
  - **Eng:** Engineering.

  Founder and Executives see every workspace.

---

## 1. Dashboard & Quick Actions

> Built as one screen with workspace 2: the Pulse board (decisions/0105 addendum). The Alerts and Quick actions rows below are now the board's Operational Task Queue and Quick Actions.

Opened by: everyone (each person sees their own).

| Section | Tools | Status | From |
|---|---|---|---|
| Your day | daily steps, task queue for your role | Live | `admin/index.php` (Your day) |
| Helper | one item at a time across queues | Live | `admin/helper.php` |
| Alerts | U: Pulse's critical items (disputed payment, order past due, a report about safety, a sacred place or private details, crawler stopped, files near the ceiling) | Live (0105) | Pulse |
| Quick actions | shortcuts for what your role reaches: new client order, add a listing, find a listing, new prospect, Pulse, reports, GO Codes; new ticket coming | Live (0105) | — |
| Order terminal | U: Orders (new order, take a payment) | Partial: the client order builder exists | `admin/client-order.php` |
| Business numbers | U: Insights tiles (MRR, orders, partners) | Live | dashboard |

## 2. Unified Communications Hub (Pulse Stream)
Opened by: everyone; each department sees its own filter.

| Section | Tools | Status | From |
|---|---|---|---|
| Pulse | H: the stream; chips for Inquiries, Sales, Fulfillment, Payments, Safety, System, plus Mine, Needs action, Done; critical items pinned | Live (0105) | — |
| Split drawer | opens the item's own tool beside the stream; full screen with a back arrow on a phone; Done marks it done | Live (0105) | — |
| Messages | business messages, guest questions, group posts | Live (inbox) | inbox |
| Stream settings | H: routing rules (which department gets what), SLA timers | Live (0105) | — |

## 3. Directory & Places (Get Local 360°)
Opened by: Ops (H), Con, Sal (view), Svc (view).

| Section | Tools | Status | From |
|---|---|---|---|
| Six-tier taxonomy | H: Continental Hub → Regional / State → Geo-Hub Zone → Micro-Cluster → Anchor → Sub-Group; Level-1 master taxonomy | Partial: hubs, geo-hubs and clusters exist; ❓ which existing table holds each level | `cluster-tools.php`, regions |
| Auto-clustering | H: 20-mile spatial clustering | Partial: cluster tools exist; ❓ confirm 20 miles is the rule | `cluster-tools.php` |
| Single Home Rule | H: each listing has one home anchor; conflicts queue | Coming; ❓ define the rule exactly | — |
| Listings | find and edit, bulk actions, add, intake, new | Live | `listings.php`, `find-listing.php`, `listings-bulk.php`, `listing-add/edit/intake/new.php` |
| Review | crawler review, review items, suggestions, claims | Live | `crawler-review.php`, `review-item.php`, `suggestion.php`, `claims.php` |
| Nexus | H: brand networks (Location Groups, AOS §6) | Live (grouping); Coming (billing link) | `nexus.php`, `rebuild.php` |
| Places | landmarks, recreation, geocoding | Live | `landmarks.php`, `recreation.php`, `geocode.php` |
| Nations | H: nations, their rules, held places (0066) | Live | `nations.php`, Safety → Hidden places |
| Categories | H: categories, secondary categories | Live | `category-add.php` |

## 4. Journeys & Exploration (Let's Explore 360°)
Opened by: Con (H), Ops.

| Section | Tools | Status | From |
|---|---|---|---|
| Reference guides | H: editorial guides | Partial: resource guides and how-to guides exist under Search | `resource-guides.php`, `how-guides.php` |
| Journeys | first-person journeys: moderation and features | Partial: admin page exists | `journeys.php` |
| Experiences | experiences | Live | `experiences.php` |
| Stories | stories | Live | `stories.php` |
| Waypoints & routes | interactive waypoints; GPX/KML files | Coming | — |
| Chameleon Filter | editorial picks | Coming; ❓ define it | — |

## 5. Marketplace & Storefronts (Market 360°)
Opened by: Ops (H), Svc, Sal (view).

| Section | Tools | Status | From |
|---|---|---|---|
| Post-and-Connect | C2C/B2C exchange: posts, offers, dual confirmation, trust points (to the trust system) | Coming; ❓ define B2C posts | — |
| Storefront Studio | merchant storefronts, themes | Coming | — |
| Custom domains | H: `clientdomain.com` mapping, checks | Coming | — |
| Catalog rules | H: pricing rules, the 250-item cap | Coming; ❓ is 250 per listing on every tier? | — |
| Gateways | H: merchant payment gateways on the ledger (direct delivery routing) | Coming | — |
| Short codes & QR | GO Codes, short links, scans | Live | `short-codes.php` (now under Safety) |

## 6. Community & Events (Social 360°)
Opened by: Ops (H), Con, Svc.

| Section | Tools | Status | From |
|---|---|---|---|
| Groups | topic spaces, privacy levels, charters with transfer terms (0102, 0103) | Partial: groups exist; charters and transfer terms Coming | community |
| Business groups | groups run by a business (0102 §4c): guardrails, post caps | Coming | — |
| Moderation | H: reports, 3-tier escalation | Partial: reports exist; ❓ the three tiers | `reports.php`, Safety |
| Standing | enforcement ladder, trust flags, reviews | Live | Safety → Standing, Procedures |
| Deactivation | H: inactivity (30/90 days) and lifecycle stages (0103) | Coming | — |
| Events | ❓ define: group events, business events, public calendar? | Coming | — |

## 7. Sales, Marketing & Customer Growth
Opened by: Sal (H), Svc (order processing), Fin (orders, view).

| Section | Tools | Status | From |
|---|---|---|---|
| Prospects | calls today, all prospects, add from the field, new this week, funnel | Live | `sales.php` |
| Unclaimed pipeline | unclaimed listings, claim invites | Live | Sales |
| Quotes & deals | quotes, proposals, close; closing opens White Glove (8) | Coming | — |
| Orders | U: Orders (Sales starts them; Service processes them here) | Live | `ecommerce.php`, `client-order.php`, `order.php` |
| Catalog | U: Products (to quote from) | Live | `products.php` |
| Regional promos | promo tools by region | Coming; ❓ define | — |

## 8. White Glove Concierge
Opened by: Svc (H), Sal (to schedule at close).

| Section | Tools | Status | From |
|---|---|---|---|
| Onboarding board | H: stages per client; opened by a closed sale | Coming; ❓ the stages | — |
| Asset studio | OCR digitizing; menu and brochure parsing into the catalog | Coming; ❓ what goes in, what comes out | — |
| Imports | U: the import engine (11.1) for the client's data | Coming | — |
| Verification vault | U: documents (72-hour purge) | Coming | — |

## 9. Back-Office Operations, Logistics & Governance
Opened by: Fin (finance), Svc (fulfillment, vendors), Ops (governance).

| Section | Tools | Status | From |
|---|---|---|---|
| Orders | H: orders, order page (fix, credit, refund, chargeback), statuses | Live | `ecommerce.php` → Orders, `order.php`, `client-order.php` |
| Products | H: the catalog | Live | `products.php` |
| Fulfillment & shipping | manifests, lots, packs, slips; multi-channel splitting | Partial: manifests step 1 built | `manifest.php`, client orders |
| Vendors | H: vendors and production partners | Partial (decisions/0099) | E-Commerce |
| Payments & ledger | H: the payment ledger, gateways, sandbox/production | Live (QuickBooks) | `ecommerce.php` → Payments, `quickbooks.php` |
| Reconciliation & tax | payment batches, tax documents, fees | Partial: Reports exist | `ecommerce.php` → Reports |
| Plans & entitlements | H: tiers, storage, communication volume, AI quotas, overage packs, annual plans, Nexus fees and pools | Coming | — |
| Inventory & COGS | master inventory ledger, cost of goods | Coming | — |
| Import & export | H: the import engine (staging, preview), export packages, data streaming (AOS §11) | Partial: auto-imports exist | `auto-imports.php` |
| Consent hub | members' consents | Coming; ❓ define | — |
| DMCA queue | take-down requests | Coming | — |
| Verification vault | H: documents, 72-hour purge | Coming | — |
| People | H: members, staff and roles, staff logins | Live | `people.php`, `staff.php`, `identities.php` |

## 10. Insights & Analytical Research
Opened by: Executives, Ops, Con, Eng (telemetry).

| Section | Tools | Status | From |
|---|---|---|---|
| Insights | business numbers, reports | Live | `insights.php` |
| Search workbench | workbench, missed searches, learning, oversight, index, demand | Live | `search.php` and its sections |
| 5 W's lexicon parser | Who, What, Where, When, Why (lexicon 0063) | Partial: the lexicon and workbench exist; ❓ the parser's output | search tools |
| Data & crawler | sources, crawler, data crawler | Live | `data.php`, `crawler.php`, `data-crawler.php`, `sources.php` |
| Emergency resource mapping | ❓ define: shelters, hotlines, closures? | Partial: shelters and hotlines exist on listings | — |
| Telemetry | system health: errors, jobs, file count against the 200,000 ceiling | Coming | — |

## 11. Global System Configuration
Opened by: Founder, Eng (Director), Executives (view).

| Section | Tools | Status | From |
|---|---|---|---|
| Settings | how it works, migrations list, setup | Live (read-only) | `settings.php`, `setup.php` |
| Branding | logo, colors, icons | Coming | — |
| SEO | permalink rules, schema builders | Coming | — |
| AI guardrails | quotas per tier, HITL rules, blocked uses | Coming | — |
| Notifications | SMTP, Twilio (SMS), push | Partial: email works | — |
| Integrations | Google Maps, Google Places, QuickBooks, Turnstile, sign-in providers | Partial: in `.env`, no panel | — |
| Storage | S3 settings, dormant until credentials are added; file count | Coming | — |
| Backups | daily encrypted dumps, 30 days; restore drill | Coming | — |
| Infrastructure | DB replicas, Redis, vector search: future (AOS §11.4) | Coming (shown, not active) | — |

---

## Tools used in more than one place

| Tool | Home | Used in |
|---|---|---|
| Orders | 9 | 1 (terminal), 2 (drawer), 7 (Sales and Service) |
| Products | 9 | 7 |
| Listings (find and edit) | 3 | 2 (drawer), 7, 8 |
| Imports | 9 | 8 |
| Verification vault | 9 | 8 |
| White Glove onboarding | 8 | 7 (opened at close) |
| Reports and enforcement | 6 | 2 (drawer), 3 (listings) |
| Insights tiles | 10 | 1 |
| Short codes & QR | 5 | 3 (listing), 7 (sales) |

## Left for Jason (❓)

1. ~~Alerts~~ and 2. ~~Pulse~~: settled and built (decisions/0105).
3. Six tiers: which existing table holds each level; the 20-mile rule; the Single Home Rule (3).
4. Chameleon Filter (4).
5. B2C posts in Post-and-Connect; the 250-item cap per tier (5).
6. The three moderation tiers; what Events are (6).
7. Regional promos (7).
8. White Glove stages; OCR input and output (8).
9. The consent hub (9).
10. The 5 W's parser output; emergency resource mapping (10).
