# Order system map, part d3: decisions 0102–0142 (roles, admin engine, data walls, CRM, commerce settings, business portal sales, files, QuickBooks, catalog, terms)

Source: each file read in full from `/home/user/traversence/decisions/`; status lines from `decisions/index.md`
(generated 2026-11-19 07:17 UTC). "Business portal" items are **a business's own sales to its own customers**: a separate,
walled system (`degraff1_trv_listings`, `biz_*` / `b_*` tables), not Traversence's client orders (`ClientOrders`, `orders`).

---

## 0102: Roles and support access (RBAC master)

- **Date / status:** proposed 2026-10-21; file: "**Accepted and built** (Jason, 2026-11-18, through the session manager:
  'Approve')". index.md still lists it as `proposed` (Notes 1). Addendum 2026-10-23: Route 1 built for White Glove (0117).
- **Decides about orders:**
  - Principle: "**The role decides.** If a person's role allows an action, they do it, with no confirmation step and no
    one else's approval."
  - Three layers never mixed: platform staff (0100 logins), business team, community groups. "A platform staff member
    has **no access** to a business except through a standing grant or a ticket", and only with that grant's/ticket's role.
  - Business roles: Owner, Manager, Accountant ("money and records"), Sales ("customers, pipeline, orders"), Support
    ("customer help and orders"), Creator, Developer. One-person business: owner has everything, no roles shown.
  - Permission matrix rows touching orders (F full / E create-edit / V view / D draft / — none):
    - **Orders & fulfillment:** Owner F, Manager F, Accountant V, Sales E, Support E, Creator —, Developer —.
    - **Ledger, invoices, payments:** Owner V, Manager V, Accountant "E (invoices only)", Sales V, Support V, others —.
    - **Customers & pipeline:** Owner F, Manager F, Sales "E (their own)", Support V.
    - **Catalog & content:** Owner F, Manager F, Sales V, Support V, Creator D. **Publish:** Owner/Manager F, Creator
      "business setting". **Plan & billing:** Owner F, Manager V, Accountant V. **Exports:** Owner only.
    - **Inbox:** Owner/Manager all, Accountant billing, Sales "Sales view", Support "Support view".
  - "The ledger is append-only (0098): no role edits or deletes entries."
  - 4c: a member asking for a quote (or Contact, messaging the rep, Link) "puts a lead into the business's pipeline".
  - Support access (§5): Route 1 standing grant (named agent, until revoked; moves with account reassignment); Route 2
    ticket (timed access with a role by ticket type, e.g. "Theme fix" → Developer, 2 h). Banner "TRV-0012 is helping…
    · End now"; uneditable action log.
  - Data: `team_members`, `role_perms` (level none/view/draft/edit/full), `support_grants`, `support_tickets`,
    `audit_log`. One check: `Team::can($person, $business, $perm)` enforced in `app/bootstrap.php`.
  - Dropped: step-up confirmation, two-owner approval; "'Payment vault keys': QuickBooks holds card details, not us."
- **Changed by:**
  - 0110: a business's private data (customers, orders, cost and markup) "is opened only through support access (a
    standing grant or a ticket, decisions/0102), with the 'agent is in' banner, and every view logged".
  - 0115 §(build order 3): "Support access for our team (a standing grant or a ticket, 0102)".
  - 0117: builds Route 1 for White Glove ("the standing grant").
  - 0103: support grant follows reassignment; action log "kept 3 years".
  - 0137: changes 0102 §4b–4c (reports handled by group charter; "This replaces 0102 §4c's 'posts per day capped by
    plan'"); notes "the support-access ticket queue, 0102 §5 route 2, isn't built".
  - 0172 §1.5: who on a team gets which messages "is set in the RBAC configuration (0102 §4, its Inbox row…)".

## 0109: The admin rebuilt: modules, sections, widgets and workflows

- **Date / status:** Accepted 2026-10-22 (Jason). index: accepted. Zip 1 and Zip 2 built (Zip 2 "built 2026-10-22").
- **Decides about orders:**
  - Four layers: Modules (`app/admin/manifests.php`), Sections, Widgets (`app/admin/widgets.php`, `js/admin-kit.js`:
    Table/Pipeline, MetricCards, DetailPanel drawer, quick tasks, bridges), Workflows (stored in DB, edited at System
    Configuration → Workflows; each published version kept).
  - Addresses `/admin/<module>/<section>[/<id>]`, example "**`/admin/backoffice/orders/922`**"; one endpoint
    `/admin/api/<module>/<tool>`; every tool answers "list, get, move and create".
  - Access: module by department (AOS §9A); each action "a lowest level plus departments", changeable at System
    Configuration → Permissions; disallowed = greyed with reason; Founder and Executives do everything; an Assistant acts
    at their principal's level.
  - "**The built-in workflows are client orders, leads, subscriptions and promotions** (zip 1). Each one reads its stage
    from the site's own records, so there's no second copy of the state, and does its protected moves through the
    library that owns the record (`ClientOrders`, `Leads`, `Billing`, `Promotions`)."
  - Builder (Managers and up) may rename/time/assign owner to a money/data stage or move and add stages around it;
    "that stage or move can't be removed, and neither can its checks". "Moves that happen elsewhere (the client approves,
    a payment arrives) are marked as happening on their own." Publisher chooses whether in-progress items stay on their
    version; says where a removed stage's items go.
  - "Tables everywhere. Kanban only if a table proves not enough."
  - Zip 1 engine files: `app/admin/Access.php`, `Workflow.php`, `workflows.php`, `AdminModules.php`, `manifests.php`,
    `tools.php`, `widgets.php`, `sections.php`, `kit-css.php`; migration `2026-12-24_admin_engine.sql` (`wf_workflows`,
    `wf_versions`, `wf_items`, `wf_history`, `admin_permissions`); frame `admin/router.php`, `admin/api.php`.
  - Zip 1 rebuilt "**Back-Office: orders, with the client-order pipeline and site orders; products; vendor orders; plans
    and subscriptions**."
  - Zip 2 kept as own tools: "Payments; Money reports; …; Import & export". Fixed: Pulse case panel actions "resend,
    record a payment, due date, order note, void" (`Pulse::manage`).
  - Rule: no admin page outside the manifests; a list is also a tool in `app/admin/tools.php`.
- **Changed by:**
  - Own note 2026-11-12: 10 modules; Pulse merged into "1. Dashboard & Pulse"; renumbered "… 8 Back-Office …";
    `/admin/pulse/…` redirect (`Workspaces::MERGED`).
  - Own note 2026-11-18: design directive Releases 1–3 (`app/ui/theme-tokens.php`, Deep Umber command header, record strip).
  - 0127: upload targets' `done` handled by "the library that owns the record … as with any protected move (decisions/0109)".
  - 0140: "What a role or plan can't do is greyed with the reason (0109)."
  - 0159 keeps 0109; 0171 §12 places the control board in the admin; 0164 admin tasks assigned "as the admin's other
    work is assigned (decisions/0109)".

## 0110: Data separation: three realms, walls in the database

- **Date / status:** Accepted and set up 2026-10-22. index: accepted (Notes 1). Note at top: amended by 0164 R11.
- **Decides about orders:**
  - Realms Member / Business / Admin, fixed by router (`/user/…`, `/business/<listing>/…`, `/admin/…`); business
    request re-checks role on the listing (`listing_memberships`) every request then "forgets the address".
  - Tenant views: "`b_orders = SELECT … FROM orders WHERE tenant_id = tv_tenant()`; and the same for customers, items,
    quotes, promotions, files"; write triggers refuse wrong `tenant_id`. Gateways `MemberData`, `BusinessData`,
    `AdminData`; build check fails raw SQL outside its gateway.
  - `tv_member` can never touch "orders of other people"; `tv_business` never "our QuickBooks keys".
  - Admin and a business's data: "a business's private data (customers, orders, cost and markup) is opened only through
    support access … and every view logged".
  - Four databases (revised 2026-10-22):
    - `degraff1_traversence` (main): public/shared only.
    - `degraff1_trv_admin`: "**ours:** … settings and keys (QuickBooks, AWS…), workflows and permissions, and
      Traversence's own business (tenant 0: **our orders**, plans and billing, leads, promotions, GO Code sales)".
    - `degraff1_trv_listings`: "every business's private data: catalog, customers, quotes, **merchant orders**, gateway
      keys, storefront settings, business files index" (tenant_id, views, triggers).
    - `degraff1_trv_users`: members' private data.
  - Phase B move groups: group 2 Keys `qb_connection`, `qb_settings`; group 5 Our sales "`prospects`, …, `vendor_*`,
    **`products`, `order_inbox`**".
  - "**Stay in main until the members step: orders, subscriptions, promotions, GO Codes and short links.** Members and
    businesses touch them too, so they move with the members' gateway."
  - `Database::t('<table>')` names a movable table; moves recorded in main's `data_moves`. Groups 4–5 built: "sales, lead
    scores, the card scanner, vendor orders, products and the QuickBooks inbox" read through `Database::t()`.
  - Public pages later use narrow admin functions, e.g. "charge with our QuickBooks".
  - Since 2026-11-03 (0141 §2.6): "a business's customers (people who transacted) live in its walls and can be exported";
    tax-exemption certificates (0141 §4) stay in the walls.
- **Changed by:**
  - 0164 R11 / note 2026-11-14: identity tables stay in main (also via 0133 note 2026-11-01).
  - 0141 (see above, appended note).
  - 0162: migrations copied in "database a table has moved to (decisions/0110, `Database::schemaOf`)".
  - 0165: staging has "The four databases (0110), on each side"; outbox "in the database that owns the table, 0110";
    "a transaction can't cross the four databases' logins (0110)".
  - 0145: code check "every table and column used exists, in the right database (`Database::t`, decision 0110)".
  - 0115, 0118, 0135 build on its walls (0135: "the catalog item (in the business's walled data, 0110) is the draft").

## 0111: Customers (CRM), built in our admin first

- **Date / status:** Built 2026-10-22. index: accepted.
- **Decides about orders:**
  - Sales & Growth → Customers (`/admin/growth/customers`): one record per person/company that bought from Traversence.
  - "**Built from what they bought:** orders that went to them (not drafts or voided ones), with what was paid, refunded
    and disputed (`order_ledger`); plans … (`subscriptions`, `subscription_charges`); matched by email."
  - Auto-refresh on open; order details fill blanks, hand edits not overwritten.
  - Segments computed, never stored: New (first purchase ≤30 days), Returning (two orders+, or order and plan), VIP
    "$500 or more (`Customers::VIP_CENTS`…)", On a plan, Inactive, Lead. Tags start: Retail, Wholesale, **Client order**,
    GO Codes.
  - Drawer: activity "notes, orders, plans and charges, newest first".
  - Who: Sales, Customer Success, Finance; permissions `growth/customers`, `.edit`, `.create`.
  - Tables `crm_customers`, `crm_tags`, `crm_customer_tags`, `crm_notes`, `crm_sync` in the admin database, group 5;
    `owner_id` 0 = Traversence; a business's own customers carry its listing "in the businesses' own database".
  - Engine: filters can be functions; drawer `acts` post `action: act` to `/admin/api/<module>/<tool>`, checked by `.edit`.
  - Coming: "Price lists per segment (Wholesale)", "The business's own customers: the business portal step".
- **Changed by:**
  - 0115: business portal Customers is "our CRM (decisions/0111) for that business".
  - 0112 §2: price lists keyed to a CRM tag; form entries come into Customers as leads.
  - 0172: Pulse "Add to" can "link it to a customer or an order (0111)".

## 0112: Taxes and shipping, price lists, and Storefront & pages

- **Date / status:** Built 2026-10-22. index: accepted (Notes 1). Addendum 2026-10-23 (404 page).
- **Decides about orders:**
  - Back-Office → Taxes & shipping: `tax_rates`, `Commerce::taxFor` (state, city, ZIP/prefix; three decimals; option to
    tax shipping; rates "**added**: state, then city and district").
  - `commerce_settings.tax_source`: "'QuickBooks works it out' stays the setting for our invoices"; "'Our rates' uses the
    table, for orders paid outside QuickBooks and for the businesses' checkouts."
  - Shipping `ship_rules`, `Commerce::shipOptions`: flat, by weight, free over, local pickup, local delivery by ZIPs or
    distance (`zip_coordinates`), state limits; "every option that reaches them, cheapest first".
  - Check an address tool. Who: Finance sees; Finance managers change (Customer Success managers for shipping).
  - Back-Office → Price lists: `price_lists` (a CRM tag e.g. Wholesale, or everyone; % off catalog),
    `price_list_rules` (quantity breaks; set unit price to four decimals, "$0.045 a card"). `PriceLists::priceFor`:
    "every list the customer qualifies for is tried, and the lowest wins", worked on the whole line.
  - Storefront & pages: `site_pages`, quote form entries `site_form_entries` → Form entries and "into Customers as leads".
  - Data: admin DB `commerce_settings`, `tax_rates`, `ship_rules`, `price_lists`, `price_list_rules` (`AdminTables::make`,
    `data_moves`, group 5); main DB `site_pages`, `site_form_entries` ("public-side, like orders"). `owner_id` 0.
  - "**Not yet wired in:** Our own checkouts and client orders still take tax from QuickBooks, and prices from the
    catalog." Business checkout to use `Commerce::taxFor`, `shipOptions`, `PriceLists::priceFor`; "The client order
    builder can offer the customer's price-list price at the same time." Carrier rates/labels later.
- **Changed by:**
  - 0119: `Commerce` scoped to each business (`b_tax_rates`, `b_ship_rules`, `b_commerce_settings`).
  - 0128 step 1 revised: "**Tax authority is the existing tax source** in Taxes & shipping (`Commerce::taxSource`:
    quickbooks or rates), read and written through `Books`".
  - 0166 §3: "**Sales tax stays the bookkeeper's or processor's job.** We never calculate tax rules ourselves."
    (accepted, not built beyond §6a).
  - Own note 2026-11-13 (0159): `SitePages` becomes block-based. 0159 keeps price lists as the platform's commerce.
  - 0115 build order lists the business's Taxes & shipping, Price lists, Storefront & pages.

## 0113: Scheduled tasks, set from the admin through the cPanel API

- **Date / status:** Built 2026-10-23. index: accepted (Notes 1).
- **Decides about orders:** no order-specific rule beyond the job list. Jobs touching orders/billing: "Plans and
  renewals | `app/scripts/billing_tick.php` | every day at 6:13 am"; "Work order reminders |
  `app/scripts/work_orders_tick.php` | every hour (at :17)". Mechanism: `CPanel` (`app/lib/CPanel.php`), `Scheduler`
  (`app/lib/Scheduler.php`), each script calls `Scheduler::run('<job>')`, runs in `job_runs` (admin DB group 1, 14 days).
  Who: Directors in Engineering/Operations (`config.scheduler.edit`).
- **Changed by:** no later decision cites 0113. Own note 2026-11-17 (release #105): task ↔ page links
  (`app/admin/job-line.php`). 0124 adds the "Files to S3" job (`app/scripts/files_tick.php`). (CLAUDE.md: new cron jobs
  go in `Scheduler::JOBS` and `Scheduler::AUTO`.)

## 0116: The catalog in the business portal

- **Date / status:** Built 2026-10-23. index: accepted. Header: catalog's Marketplace display and caps in 0135.
- **Decides about orders:**
  - Business portal Catalog = "our Products tool (decisions/0101) scoped to the business through `Scope` (decisions/0115)";
    business's own `products` table in the listings DB (`owner_id`, item codes unique per business), view `b_products`.
  - Business keeps items/services/classes/events (`Products::BUSINESS_KINDS`), prices for quantities with own cost and
    markup, options that change price, taxable, supplier, "ready in", Active/Hidden; cost, markup, supplier private.
  - Drawer editing for us too (`Products::update()` changes only what's given); our full editor `/admin/products.php`.
  - Router: each tool is a line in `app/business/portal.php`; `business_hide` hides our-only columns.
  - Next (business's own sales): "The client-order engine takes its payments through our QuickBooks, so it can't run as
    is for a business. The business's version starts on **manual payments**": business marks paid (Zelle, transfer,
    check, cash, pay on pickup) with a reference; card checkout via its own gateway at Core.
- **Changed by:**
  - 0141 (appended note 2026-11-03): every catalog item has a **kind** (physical, service, made to order, ticket,
    digital, subscription, booking, perishable, rental) each configuring "checkout, after-purchase delivery, order
    handling, policies and gateway support".
  - 0135: "A business's items are its catalog (0116). An item that's Active shows in the Marketplace and in its
    Storefront"; "Single items only … Options that change the price (0116) cover sizes and add-ons"; listed among older
    records pointing to 0135.
  - 0130: "The same engine serves the business portal's catalogs (0116)." 0134: catalog kind "Class or event".

## 0118: The business portal: Quotes & invoices, Orders and the team (a business's own sales)

- **Date / status:** Built 2026-10-23. index: accepted.
- **Decides about orders (separate system: a business selling to its customers):**
  - Quotes & invoices (Basic Partner+; role needs Ledger): `app/lib/BizQuotes.php`; tables `biz_quotes`,
    `biz_quote_lines`, `biz_payments`, `biz_quote_log` (BusinessData VERSION 3, tenant views).
  - New quote from customer email (found in/added to Customers); Add from catalog (price for that many, cost private) or
    a line by hand; Terms & how to pay (due on receipt / Net 15 / Net 30, deposit %, tax rate, business's own pay link
    https only, other ways, notes, validity days); Send (private link), Record a payment, Make a copy, Void "only without
    payments".
  - "Statuses: Draft → Sent → (approved) Invoice → Paid; or Declined / Void." Numbers per business Q-0001 / INV-0001.
  - Customer page `/q.php?t=<business>.<token>` (noindex, no account): Approve by typing name, or Decline; invoice view,
    Pay now opens business's pay link; owner emailed; other business's number is 404.
  - "**Money never passes through Traversence.** Payments are made to the business and recorded by its team." Card
    checkout later = `card_checkout` feature (Core).
  - **Orders** (every plan; role needs Orders): "Every approved or paid quote is an order. Stages: New, In production,
    Ready for pickup, Shipped, Done." Ready and Shipped email the customer (tracking/pickup note); list defaults to not
    done, with payment state.
  - Team: `listing_invites` (main DB), `Team::claimInvites`; Owner/Managers manage team; Owner+Manager mirrored to
    `listing_access`; action log.
  - Terms `#business-quotes`: between business and customer; approving is agreeing.
  - Not yet: proofs/files on quotes; business-wide tax/pay link/terms; ownership transfer.
- **Changed by:**
  - 0119 "settles 0118's open question": tax rate, terms, pay link are business-wide settings.
  - 0120: proofs and files on quotes; handover. 0121: discount codes on quotes.
  - 0135 §2: "Request a quote (Basic and up) is where negotiation happens: the business writes the quote … (Quotes &
    invoices, 0118), and the buyer accepts it, which makes the order"; Make an offer retired; "Send a price" card.
  - 0134: tickets use the same manual payment "(0118, 0119)".

## 0119: The business portal: Sales tax, Shipping & pickup, usual quote terms

- **Date / status:** Built 2026-10-24. index: accepted.
- **Decides about orders (business's own sales):**
  - `Commerce` follows scope: in the portal reads/writes `b_tax_rates`, `b_ship_rules`, `b_commerce_settings`; one
    definition `Commerce::TABLES` for ours and each business (`BusinessData::TABLES`, VERSION 4: 13 tables walled).
  - Portal (Basic Partner+): Sales tax (`taxes`, role needs Ledger); Shipping & pickup (`shipping`, role needs Orders).
  - Plan & settings → Quotes & invoices usual terms saved as `quote_*` settings; "every new quote starts with them";
    view needs Quotes view, change Quotes edit. On a quote: "From your tax rates".
  - Not yet: auto tax from customer address on a quote (`Commerce::taxFor` can); shipping from rules on a quote; price
    lists (Strategic) and Storefront & pages (Core) for businesses.
- **Changed by:** 0134 cites it for event tickets' manual payment and "confirm the business's rates (0119)". No other
  later change found.

## 0120: Handing over a business; proofs and files on quotes, counting against storage

- **Date / status:** Built 2026-10-24. index: accepted, "Replaced by 0122" (index column).
- **Decides about orders (business's own quotes):**
  - Handover: `transfers` (kind `business`), `/user/transfer.php`, 30-day lapse; on accept previous owner's card off the
    subscription (`card_id` cleared, `cancel_at_end`).
  - Proofs & files: records `b_biz_files` (BusinessData VERSION 5: 14 tables), files under `app/storage/biz-files/<business>/`.
    Team (Quotes view/edit) at `/business/<listing>/quotes?files=<quote>`: **proof versions** ("an unanswered earlier
    version is marked replaced; an answered one keeps its answer as the record and can't be removed") or files, shared
    with customer or team-only.
  - Customer on `/q.php`: "**Approve the proof** or **Ask for changes**"; owner emailed; quote history records it;
    `q-file.php` serves only proofs and shared files; `nosniff`, sandboxing CSP.
  - Storage meter `BizFiles::used` vs `Billing::allowance`; over → refused (`BizStorageFull`, 413 `storage_full`), Add
    storage offered.
  - Addendum: 24 MB asks first, 1 GB max (`BizFiles::STANDARD`, `MAX`); chunked (`BizFiles::chunk`); video proofs.
  - Terms: "business quotes: approving a proof; files and storage".
- **Changed by:**
  - 0122: chunked path "superseded … once S3 is set up: files go straight to S3; this stays the fallback".
  - 0127: removed `BizFiles::add/chunk/s3Start/s3Done/s3Abort` and the upload half of `business/files.php` ("now 410");
    `biz_file` target.
  - 0103: "Section 2 'By hand' is built for businesses (decisions/0120)".

## 0121: A business's own discount codes

- **Date / status:** Built 2026-10-24. index: accepted.
- **Decides about orders (business's own quotes):**
  - Unlike our Promotions (65% floor, approvals, 0108): "no floor, no approval". Portal → Discount codes (Basic Partner+;
    role needs QR & short codes).
  - Code 3–40 chars, capitals, unique per business; % (≤100) or amount; dates, uses, smallest order, once per customer
    (default on).
  - On a quote (draft or sent only): team adds it, or customer types it on `/q.php`. "Checked when it goes on and again
    at approval"; switched-off code stops approval.
  - "The amount comes off the subtotal before tax (the taxable part in proportion), never more than the subtotal"; quote
    stores code as it was. "Counted as used when the quote is approved (`biz_code_uses`…)".
  - Data `b_biz_codes`, `b_biz_code_uses`; `biz_quotes` gains `code_id`, `code`, `code_kind`, `code_value`
    (BusinessData VERSION 6, 16 tables). `app/lib/BizCodes.php`; `BizQuotes::sums()`, `setCode()`, `approve()`.
- **Changed by:** 0134 cites its codes for events. No other later change found.

## 0122: Business files go straight to S3

- **Date / status:** Built 2026-10-24; "extended to every file the site keeps by decisions/0123". index: accepted (Notes 1).
- **Decides about orders:**
  - Business quote files: `BizFiles::s3Start` → multipart at `biz/<business>/q<quote>/<random>.<ext>`, signed part
    links (`S3::partUrls`, 8 MB, an hour); `BizFiles::s3Done` checks size, sniffed type, storage, records
    `biz_files.store_key` (BusinessData v7); abort path.
  - Seeing via `BizFiles::send` → 15-minute signed link (`S3::link`); removing deletes object.
  - Without S3 keys: 0120's chunk path. Bucket CORS/lifecycle/IAM set-up listed.
- **Changed by:**
  - 0123 (extends to all files), 0124 (owner folders), 0127 (these `BizFiles::s3*` functions removed; one uploader).
  - 0151 / own note 2026-11-11: "files move to Cloudflare R2"; "Amends decisions/0122 (where files are kept)";
    `S3_ENDPOINT`, `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY`, `S3_REGION=auto`.

## 0123: Every file the site keeps lives in S3

- **Date / status:** Built 2026-10-24. index: accepted (Notes 1).
- **Decides about orders:** Jason: "isn't that why we set up S3, so the gallery and **order processing** wouldn't kill
  Bluehost?" One place `app/lib/Store.php` (`Store::keep`, `redirect`, `local`, `read`, `remove`, `moveOld`).
  - "`app/storage/order-proofs/`, `order-files/` → `private/order-proofs/`, `private/order-files/` | **client orders'
    proofs, artwork, labels (ClientOrders)**"; `app/storage/biz-files/` → `private/biz-files/`.
  - "**Private files** open through a 15-minute signed link … only after the same checks as before (**the customer's
    order link**, staff, the claim reviewer, the business's team)." Removing a proof/line deletes from S3.
  - Tested: "An order proof opened by the customer's link through a signed S3 link."
  - "Marketplace isn't built yet. When it is, its photos and files go through `Store`."
- **Changed by:** 0124 (owner folders replace these prefixes); 0151 (R2). Own note 2026-11-15 (release #68):
  `/uploads/.htaccess` written from `Store::KINDS`.

## 0124: Every owner's files in one folder; moving them on a schedule

- **Date / status:** Built 2026-10-24. index: accepted.
- **Decides about orders:**
  - Prefixes: "`businesses/<folder>/private/quotes/q<id>/`, `quote-files/` | proofs and files on its quotes";
    "`orders/<folder>/private/proofs/`, `files/` | **a client order's proofs, artwork and labels**".
  - `<folder>` random 16 hex per owner in `storage_folders` (admin DB).
  - "**Orders keep their own folder.** A client order's files stay with the order, our production and sales record
    (decisions/0095, 0099), even when the customer deletes their account."
  - `Store::files()`, `usage()`, `wipe()`; `Store::moveOld` moves order proofs and files, business quote files.
  - Scheduled task "Files to S3" (`app/scripts/files_tick.php`, every 10 min).
- **Changed by:** 0126 (gallery in owner folders), 0151 (R2), 0153 §5 (where files live, 0124 and 0151).

## 0127: One uploader for every file

- **Date / status:** Built 2026-10-26. index: accepted. Addendum 2026-10-29 (uploads continue across pages).
- **Decides about orders:**
  - Jason: "Assigning **orders** or photos to the correct album or **order** should have already been established."
  - Before: order proofs and artwork were a form post capped by `upload_max_filesize` ("36 MB failed with no question asked").
  - One path: `js/tv-upload.js` (`TvUpload.send` / `bind`) → `/api/upload.php` (`start`, `piece`, `done`, `abort`,
    `do=status`) → `app/lib/Uploads.php`; targets in `app/lib/upload-targets.php` (`check`, `done`; `Upload::moveTo()`,
    `local()`, `asFile()`); "The library that owns the record does the work, as with any protected move (decisions/0109)."
  - Targets: "`order_proof`, `order_file` | an order's proofs (one version per batch), artwork, reference, shipping
    labels | 1 GB"; "`biz_file` | a business's quote proofs and files (counts toward its storage…) | 1 GB".
  - Forms with other fields send the file first then the form with its id: "a shipment's label".
  - Removed: "`ClientOrders::addProof/addProofs/addFile/uploads` and the proof and item-files posts in
    `admin/client-order.php`"; `BizFiles::add/chunk/s3Start/s3Done/s3Abort`.
  - Rule: `$_FILES` only in `Uploads.php` and `api/upload.php`.
  - Addendum: IndexedDB queue (`tv-uploads` → `jobs`), resume via `Uploads::status()`; "An order's file lists show each
    file's size (from the order's storage listing, here or in S3)."
- **Changed by:** 0134, 0143, 0153, 0158 keep it (0143: "No upload path was added"). No change to order targets found.

## 0128: QuickBooks invoices into Traversence orders, and one product list across both

- **Date / status:** accepted (Jason, 2026-10-28); header "step 1 built"; body records Step 2 and Step 3 "built
  (2026-10-28)". Step 4 (historical pass) not recorded as built. index: accepted.
- **Decides about orders:**
  - Problem: invoice 112906 (Sonoran Hospitality Group) typed by hand drifted ($0.0899 vs $0.1098, no shrink wrap,
    shipping, tax). Items found **by name** (`QuickBooksPay::namedItem`).
  - Three roles: **Processor** (QuickBooks Payments), **Bookkeeper** (QuickBooks), **Order / CRM management**
    (Traversence). Tax and history each **authority** or **follower**. "Each order records the setup it was made under,
    so changing a role later never rewrites old orders."
  - "**The rule:** Whoever started the order owns its numbers until Traversence takes it over. From then on Traversence
    processes it: production, work orders, shipping, history, reorders. QuickBooks stays the books."
  - Pull by invoice number (preview first): no site order → build from invoice; site order + unpaid → merge; site order +
    paid → rebuild to match exactly, payment recorded "as made in QuickBooks (nothing is posted back)". Order event
    logged. Client via `Clients`. Product lines become reorder items (Collateral Center); shipping/fees/tax don't.
  - Payment rules: "A paid amount is what was agreed … never reopens the price"; undercharge → order marked
    **underpriced**; overcharge → Money tools credit/refund.
  - Products tagged to QB items by id (`products.qb_item_id`), never by name; QB owns item name/income account/taxed;
    catalog owns specs, tiers, proofs, vendor, turnaround, files. QB "charge" items map to order charges, never reorder items.
  - Mapping page Products → QuickBooks items; history guarantees: old lines keep title/qty/price; nothing written to QB
    during mapping; links by mode (`qb_item_env`); Undo.
  - Step 1 built: `app/lib/SystemTags.php` (`system_tags`; kinds `qbo.item`, `qbo.customer`, `qbo.invoice`), `app/lib/Books.php`
    (`role_*` in `qb_settings`; `Books::snapshot()` "what an order will record"), Back-Office → QuickBooks items
    (`tv_admin_mod_qb_items`, `qbo_items`, `QuickBooksPay::items`), `QuickBooksPay::invoiceFor` (lines carry `product_id`),
    migration `2027-01-07_system_tags.sql`.
  - Step 1 revised: Bookkeeper Traversence or QuickBooks (never none); Processor QB Payments or no card gateway; tax
    authority = `Commerce::taxSource` via `Books`; `Books::requirements` status boxes.
  - Step 2 built: Back-Office → Orders → Pull an invoice (`tv_admin_mod_qb_pull`, `app/lib/QbPull.php`); unmapped items
    decided once (`Books::decide`); client match (`Clients::autoConnect`); "Make the order reads the invoice again from
    QuickBooks (never trusting the page)"; order approved on invoice date, `invoice_id`, tag `qbo.invoice`, `pay_mode`
    invoice; QB payments into ledger, never posted back; `Collateral::fromOrder(..., true)`; lines tagged `charge` never
    collateral; refused if already an order.
  - Step 3 built: order page QuickBooks invoice section (`app/ui/qb-merge.php`, `QbPull::mergePlan()` / `merge()`);
    line-by-line preview; paid → rebuild, dropped lines in history, marked paid; unpaid → keep order-only lines by tick;
    "Check it against the invoice" refresh; refused for invoice linked elsewhere, cancelled order, no pull permission
    (greyed with reason); split invoice lines update one order line.
- **Changed by:**
  - 0130 builds on it (catalog ↔ books mapping).
  - 0132: "Takes the tag/link rule out of `decisions/0128` … into its own record"; system tags "reference only, no
    notifications (`decisions/0128`)".
  - 0166 (accepted 2026-11-16; only §6a wallet built): "Our own ledger is the record … A bookkeeper connector **posts**
    to the connected bookkeeper from it"; "Sales tax stays the bookkeeper's or processor's job"; QuickBooks setup A
    (Traversence manages) / B (QuickBooks is master); "The catalog owns the product's link … **A product with no
    QuickBooks link can't go on sale.**"; processors' routing rules; refunds through the original provider.

## 0130: The catalog model, and how it maps to the books

- **Date / status:** "Agreed in discussion (Jason, 2026-10-28); to be built in steps." index: accepted. First step
  built 2026-10-29 (see below).
- **Decides about orders:**
  - Rule: "Traversence keeps its own catalog, departments, bundles and money accounts. QuickBooks (or any bookkeeper) is
    connected to them by tags, and is never needed for them to work."
  - Mapping table: department tree → QB categories; product → one item (general or specific); variant → parent's item;
    option-as-service → own item; bundle → QB bundle or pieces as lines; funding account → QB account; client → customer.
  - Model: departments (any depth); products (kind print/service/other; business item/service/class; service type);
    options made to order (option sets, no SKU per combination; option can be a service with own item, vendor, reorder);
    variants stocked (SKU `PARENT-COLOR-SIZE`, not EAV); bundles; later assemblies.
  - Production chains: "an order line can have **production steps**, each a service with its own vendor and work order,
    in order: print → finish → mail"; each billed as its own line/bundle piece; reorderable alone or as chain.
  - Funding accounts: every recorded payment goes to one; tagged to bookkeeper accounts.
  - "**Order lines keep a snapshot**: Product, variant, each option with its price effect and item, run size, steps and
    their vendors, and the bundle's pieces. A later catalog change never rewrites a past order."
  - Same engine serves business portal catalogs (0116).
  - Addendum 2026-10-29 COGS: Cost list (`cost_items`), recipes (`cost_recipes`), each item's Cost tab
    (`order_line_costs`), "Save and mark checked"; "**The work order asks first** when a ticked printed item's COGS isn't
    checked ('Save and send to vendor')."
  - Cost list = catalog's materials; materials have variants; option resolves to one exact material, else Cost tab says
    what's missing.
  - One system: "**The order pulls from the catalog**: adding an item copies its price and its cost lines"; "**The order
    can override** any price or cost … the catalog changes only when someone chooses 'Also update the catalog'"; new
    items/prices/costs typed on an order created in catalog "marked 'added from Order #N'".
  - Built 2026-10-29 first step: `order_lines.cost_cents` follows the Cost tab; draft refresh no longer overwrites it;
    "No hard cost"; "Keep as the product's recipe"; product drawer "Made of". Still to come: material variants, options
    linked to materials, hourly services.
- **Changed by:** 0137 §9 (flyers ordered through us use "catalog items with the vendor's prices and our markup
  (`Products`, 0101, 0130)"). 0166 §5 (catalog product type/income/cost/link, accepted not built). No other later change found.

## 0142: Terms, Store Policies and the Acceptance Log

- **Date / status:** Accepted (Jason, 2026-11-03), Phase B of 0141; wording a draft pending attorney. index lists it as
  `proposed` (Notes 1). Built: zip 2 (2026-11-03, 16:50 UTC sync), zip 3 (18:17 UTC), rules where work happens and four
  tiers (19:17 UTC), INFORM and recalls (2026-11-04, 23:03 UTC), word lists (2026-11-04, 13:48 UTC). Note at top: amended
  by 0164 R2, R7, R16.
- **Decides about orders (Marketplace orders: businesses' sales; plus sign-up):**
  - Templates: Platform Terms, Privacy, Seller Agreement, **Store policies** ("The buyer, at checkout"), **Checkout
    disclosures** ("Traversence's master, per item kind … The buyer, at checkout"). Locked clauses: "the seller is the
    business; Traversence isn't a party to the sale; disputes go to the business first".
  - Acceptance log `terms_acceptances` (append-only): kinds `account_terms`, `seller_agreement`, **`checkout`**,
    `house_rules`, `store_policy_published`; fields include **`order_id` ("The order, for a checkout")**, `text_hash`
    (SHA-256), `snapshot`, `ip`, `user_agent`; kept 7 years, name/email removed on deletion.
  - "**Each order also keeps its own snapshot** of the store policies, so the business's later changes never rewrite an
    old order." Chargeback packet: "an order, its receipt, and its acceptance record, as one file".
  - Terms Marketplace section: "Your order is a contract between you and that business"; card payments to the business's
    own Stripe/Square; direct payments to the business; "Traversence never holds your money and takes no fee from the
    sale"; multi-business cart "paid business by business", each sends a receipt, plus a summary; returns to business
    first, then Report on the order; each business charges its own sales tax.
  - Rulebook tiers: Prohibited / Restricted (card checkout only where gateway allows, else direct payment or quote) /
    Allowed with notice; later four tiers (restricted = card checkout off; confirm conditions = card checkout on).
  - Built zip 2: migration `2027-02-02_terms_acceptances.sql` adds `market_checkout.policies` and
    **`market_orders.policy_json`**; `Acceptance::record()` (no update/delete); `LegalTexts`; Store policies
    (`app/business/policies.php`, `StorePolicies`) shown on items, storefront, checkout, **order page and receipt email**;
    "placing an order, and the setup's test purchase, writes a `checkout` record (version
    `<line>|<terms>|s<store version>`) and keeps the policies on the order".
  - Zip 3: Seller Agreement before Go live (`MarketCheckout::seller()`, `acceptSeller()`); `MarketRules` at publish;
    restricted items can't be paid by card; `market_items.rule_flag` (`2027-02-03_market_rules.sql`); "**Dispute
    packet**" on each Marketplace order downloads one HTML file (order, receipt, policies, acceptance record, exact line);
    "Each order now keeps that line with its policies." Stripe checkout card only (35-minute hold mentioned).
  - INFORM (`Inform`, `inform_sellers`, `2027-02-04_inform_sellers.sql`): counts each business's paid Marketplace orders
    in 12 months (tests excluded) by the daily Marketplace job; thresholds 200 sales and $5,000 / $20,000; overdue → card
    checkout pauses. Recalls `MarketRules::recall` (CPSC). Catalog column `MarketRules::forCatalog` ("anything can be kept
    there for quotes and invoices"); `MarketRules::isThing`, `MarketRules::listed()`; `Market::trips()` removed.
  - Note: proofs approval terms are in 0120 (not in this record).
- **Changed by:**
  - 0164 (2026-11-14) amends it: R2 (curators, not controllers), R7, R16 (content templates; "every save is a version");
    "Where it and 0164 disagree, 0164 rules."
  - 0141: "Phase B is decisions/0142".
  - 0014: sign-up acceptance "recorded by `Acceptance::signup`, decisions/0142".
