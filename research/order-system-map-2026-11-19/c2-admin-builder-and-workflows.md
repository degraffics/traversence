# C2: The order system in the admin, as built

Source: the live mirror `degraffics/traverse-3way-sync` at `/home/user/traverse-3way-sync` (`sync-app/` = `app/`, `sync-storage/` = `website/`). Read-only map. Paths below drop the `sync-*` prefix only where it's obvious; file:line references are to the mirror.

Files read in full: `sync-storage/admin/client-order.php` (1721 lines), `sync-app/admin/workflows.php` (422), `sync-app/admin/Workflow.php` (329), `sync-app/admin/manifests.php` (199), `sync-storage/admin/router.php` (137), `sync-storage/admin/ecommerce.php` (297). Read in part: `sync-app/admin/tools.php` (all order, product, vendor, work-order, collateral, client, tax, shipping, price-list, plan, customer, promotion and business-quote/order blocks: lines 1–1073), `sync-app/admin/sections.php` (lines 1–323 and 703–968: quick actions, workflows, permissions, tax/ship, price test, sandbox, vendors, cost list, QuickBooks items, QuickBooks pull). Spot-checked for callers: `sync-storage/admin/api.php`, `sync-storage/admin/api/pulse.php`, `sync-storage/api/admin/client-order.php`, `sync-app/lib/ClientOrders.php`, `sync-app/lib/WorkOrders.php`, `sync-app/lib/Pulse.php`, `sync-app/lib/Products.php`, `sync-app/admin/AdminModules.php`.

---

## 1. The admin's order modules

### How the access rules work
- A section opens if the person's department works in the module (`Access::canOpen($mod)`), or else if their role reaches the section's first table/pipeline tool (`Modules::canSection`, `sync-app/admin/AdminModules.php:91-96`). A tool's rule is `Access::can('<tool>.view', $tool['view'])` (`AdminModules.php:82-88`).
- Back-Office is worked by departments `ops`, `fin`, `svc` (`sync-app/lib/Workspaces.php:35-38`).
- Every rule below is the code default. System Configuration → Permissions can change it (`sections.php:121-161`, `Access::rule()`). The live overrides are in the database and weren't read.
- Drawer actions (`act`) on any tool check `Access::can('<tool>.edit', $tool['edit'] ?? ['min'=>'staff','depts'=>[]])` (`sync-storage/admin/api.php`, the `act` branch after line 432).
- Workflow moves check `Access::can('<wf>.move.<key>', move.who)` (`Workflow.php:149, 172`).

### Back-Office module (`manifests.php:112-138`), at hand: `local/listings`, `growth/prospects`, `growth/promotions`, `growth/customers`

| URL | Section label | Widgets (manifests.php line) | Tool in tools.php, rule (`view`) | Notes |
|---|---|---|---|---|
| `/admin/backoffice/orders` | Orders | `metrics` + `pipeline` `backoffice/orders`; `table` `backoffice/site_orders`; `html` `tv_admin_mod_qb_pull`; `html` `tv_admin_mod_sandbox` (113) | `backoffice/orders` (tools.php:40-75): staff, depts fin/svc/sal; `workflow => 'client_order'`; `open => /admin/backoffice/orders/{id}` | Replaces `/admin/ecommerce.php`, `/admin/order.php`, `/admin/manifest.php`. `item => ['/admin/client-order.php?id={id}', 'Order #{id}']` (114) |
| `/admin/backoffice/orders/<id>` and `/new` | (item page) | `router.php:65-80` requires `admin/client-order.php` | none | See "Routing" below |
| `/admin/backoffice/products` | Products | `table` `backoffice/products`; `html` `tv_admin_mod_cost_list` (115) | `backoffice/products` (470-632): view staff fin/svc/sal; edit staff fin/svc; create rule staff fin/svc | Replaces `/admin/products.php`. Comment: "one catalog: what we sell, and the materials and services it's made of (decisions/0130)" |
| `/admin/backoffice/production` | Order management | `table` `backoffice/production` (116) | `backoffice/production` (80-121): staff, svc/fin/sal; `open` the order page `#l<line>`; `count_by => status` | One row per printed item on approved client orders (excludes shipping and GO Code lines) |
| `/admin/backoffice/work_orders` | Work orders | `table` `backoffice/work_orders` (117) | `backoffice/work_orders` (124-201): staff, svc/fin; `open => detail` | Drawer actions: link, send, tracking, cancel |
| `/admin/backoffice/clients` | Clients | `table` `backoffice/clients`; `table` `backoffice/client_match` (118) | `backoffice/clients` (265-331) and `backoffice/client_match` (333-406): staff, svc/sal/fin | |
| `/admin/backoffice/qb_items` | QuickBooks items | `html` `tv_admin_mod_qb_items` (119) | none; `sections.php:826-895`, rule `backoffice.qb_items.edit` manager fin/ops (829) | |
| `/admin/backoffice/collateral` | Collateral | `table` `backoffice/collateral` (120) | `backoffice/collateral` (205-261): staff, svc/sal/fin | |
| `/admin/backoffice/vendor_list` | Vendors | `html` `tv_admin_mod_vendor_list` (121) | none; `sections.php:703-764`; saves go to `/admin/api/vendors.php` (not read) | No Access::can in the section function |
| `/admin/backoffice/cost_list` | Materials & services | `html` `tv_admin_mod_cost_list` (122) | none; `sections.php:771-818`; saves go to `/admin/api/costs.php` (not read) | Also shown under Products |
| `/admin/backoffice/vendors` | Vendor orders | `table` `backoffice/vendors` (123) | `backoffice/vendors` (634-649): staff, svc/fin; `open => /admin/vendors.php?q={ref}` | Replaces `/admin/vendors.php` |
| `/admin/backoffice/pricelists` | Price lists | `table` `backoffice/pricelists`; `html` `tv_admin_mod_pricetest` (124) | `backoffice/pricelists` (718-755): view staff fin/sal/svc; edit manager fin/sal | |
| `/admin/backoffice/taxes` | Taxes & shipping | `table` `backoffice/taxes`; `table` `backoffice/shipping`; `html` `tv_admin_mod_taxship` (125) | `backoffice/taxes` (651-683): view staff fin, edit manager fin; `backoffice/shipping` (685-716): view staff fin/svc, edit manager fin/svc; tax source rule `backoffice/taxes.edit` manager fin (sections.php:229) | |
| `/admin/backoffice/plans` | Plans & subscriptions | `metrics` + `pipeline` `backoffice/plans`; `html` `tv_admin_mod_planfeatures` (126) | `backoffice/plans` (422-468): staff fin/sal; `workflow => 'subscription'`; create rule manager sal/fin | |
| `/admin/backoffice/payments` | Payments | bridge `/admin/quickbooks.php` (127) | none | Not read |
| `/admin/backoffice/money` | Money reports | bridge `/admin/ecommerce.php?sec=reports`, status `partial` (128) | none | |
| `/admin/backoffice/fulfillment` | Fulfillment | coming (132): "Packs, lots and shipments across orders, and multi-channel splitting. Shipping papers are on each order today." | none | |
| `/admin/backoffice/inventory` | Inventory & COGS | coming (134) | none | |

Other modules:
- `growth/quotes`: "Quotes & deals", **coming** (manifests.php:101), meanwhile `[['New client order', '/admin/client-order.php?new=1']]`.
- `growth/promotions`: `html` `tv_admin_mod_promos` (98; sections.php:63-71), tool `growth/promotions` (tools.php:1048-1072): staff sal, `workflow => 'promotion'`. The builder's "Make a promo code" links here (client-order.php:761).
- `growth/customers` (tools.php:780-851): staff sal/svc/fin; lists customers from orders and plans.
- `market/codes` "GO Codes & short links" (manifests.php:77): not read (tools.php:1391).
- `concierge/*`: White Glove is all **coming** (104-110).
- `config/workflows` (168): `tv_admin_mod_workflows` (sections.php:98-118), rule `config.workflows.edit` manager (101).
- `config/permissions` (169): `tv_admin_mod_permissions` (sections.php:121-161), rule `config.permissions.edit` director (123).
- Dashboard → Quick actions (sections.php:28-39) has "New client order" → `/admin/client-order.php?new=1` (31).
- At-hand lists name `backoffice/orders` in dashboard (27), pulse (33), market (80), growth (102), concierge (110); `backoffice/production` in dashboard and pulse; `backoffice/products` in growth and concierge.

### Business-portal order tools (scoped to a business, `Scope::isBusiness()`)
- `business/quotes` "Quotes & invoices" (tools.php:854-937): view `owner`. BizQuotes: add_item, add_line, remove_line, terms, send, pay, void, code, copy.
- `business/codes` (939-979): view `owner`.
- `business/orders` (981-1022): view `owner`. Stage moves through `BizQuotes::stage` (1018); pay through `BizQuotes::pay` (1019). This is a separate system from ClientOrders.

### Tool details (Back-Office)

**`backoffice/orders`** (tools.php:40-75)
- `list` (44-57): client orders only (`o.created_by IS NOT NULL`), sandbox left out (`$tvNoTest`, line 34). Filter `mine`. Search by number, company, name or email. Each row's stage comes from `Workflow::item($pdo,'client_order',id)` (52). Limit 400.
- `get` (58-68): the drawer's fields; links "Open the order" and "The client's page".
- `metrics` (69-73): Drafts, With clients, Waiting for payment, To make (`paid,production,ordered`).
- `create` (74): "New client order" → `/admin/backoffice/orders/new`.
- Because it has `workflow`, the drawer shows the stage bar, moves and history from `ak_detail()` (api.php:36-60), and moves post `act=move` (api.php:427-431).

**`backoffice/production`** (80-121)
- Approved client orders in status pending/paid/part_refunded (94). Buckets: queue, production, shipped, delivered, complete (86-92).
- Hides item statuses cancelled, archived, deactivated, deleted, payment_hold, collections (104).
- Vendor comes from the item's latest work order, else `Vendors::forLine`. Due date comes from the work order (`WorkOrders::dueAt`), else the order's `due_date` (110). Sorted soonest due first.
- "All open" leaves out the archive unless you search (116).

**`backoffice/work_orders`** (124-201)
- Leaves out sandbox and mock orders (136). Filters: status (includes "Not sent" for drafts) and vendor.
- Drawer fields (165-179); links "See it as the vendor" `/wo.php?w=<token>`, "Edit it (not sent yet)" → `/admin/backoffice/orders/<oid>?wo_edit=<id>#wo-edit`, "Its order".
- `acts` (185-190) and `act` (192-200):
  - `link` → `WorkOrders::handLink`
  - `send` → `WorkOrders::send` then `WorkOrders::markOrdered`
  - `tracking` (courier only) → `WorkOrders::setTracking`
  - `cancel` → `WorkOrders::cancel`

**`backoffice/collateral`** (205-261)
- Backfills on first use (212).
- `act`: `reorder` → `Collateral::reorder(..., same art?)`; `release`, `rename`, `archive` → `Collateral::edit`.

**`backoffice/clients`** (265-331)
- Acts: contact, invite, uninvite, note.
- `create`: tag a listing #, a clear match, or a private listing (`Clients::makePrivate`).

**`backoffice/client_match`** (333-406)
- Order groups not connected to a business, plus connected orders "to check".
- Acts: ok, undo, connect, number, private, person.

**`backoffice/site_orders`** (408-420)
- GO Codes checkout orders (`created_by IS NULL`). Opens `/admin/order.php?id={id}`.

**`backoffice/products`** (470-632)
- Our catalog (staff) and a business's catalog (`Scope::isBusiness()`: publish, unpublish, write, photos, delete, kinddetails).
- Staff acts: `details`, `prices` (runs text "how many, cost, price"), `options` (one per line, `"Size: Small, Large +2.00"`). The detail's links include "The full catalog editor" `/admin/products.php#p<id>`.

**Sections in sections.php**
- **Sandbox orders** (`tv_admin_mod_sandbox`, sections.php:284-314): orders with `qb_env='sandbox'`. Per row:
  - "Open as the client" posts `do=test_open` to `/admin/backoffice/orders/<id>` (308).
  - "Delete" posts `do=test_delete` (309-310).
  - No Access::can in the function.
- **Pull an invoice from QuickBooks** (`tv_admin_mod_qb_pull`, 897-960): rule `backoffice.orders.pull`, manager, fin/ops (905).
  - GET `?qbpull=<no>` shows a preview (`QbPull::preview`).
  - POST `qbp=pull` → `QbPull::pull` (911), then redirects to the new order.
  - If an order already refers to the invoice, it links to merge instead: `?qbmerge=<no>#qbmerge` (925).
  - Unmapped QuickBooks items have a required select: Choose… / make / charge / tag to a product (943-945).
- **QuickBooks items** (826-895): roles, read, accept, decide, undo. Rule `backoffice.qb_items.edit`.

### `/admin/ecommerce.php` (older E-Commerce hub, still present, 297 lines)
- Listed as replaced by `backoffice/orders` (manifests.php:113). `money` bridges to `?sec=reports` (128).
- Its title row is `tv_admin_start('ecommerce','Orders',…)` (67). Access: `tv_crawler_require_admin` + `tv_admin_require` (26-27).
- POST actions (33-64): `grant` (a GO Code in a cart, free: `QrIssue::grant`), `check_qb` (`Orders::checkQuickBooks`), `sub_trial`/`sub_end`/`sub_stop`/`sub_resume` (Billing), `dismiss` (`Orders::inboxDismiss`).
- Tabs (290-296):
  - Orders: QuickBooks refunds inbox, search, list; client orders open `/admin/backoffice/orders/<id>`, others `/admin/order.php?id=`; "Being built or with the client"; "New order" → `/admin/client-order.php?new=1`.
  - Products (url `/admin/products.php`), Plans, Payments (url `/admin/quickbooks.php`), Reports.
- The "New order" page in client-order.php still shows breadcrumbs E-Commerce → Orders → New order (client-order.php:450).

### Routing to the builder
- The section declares `item => ['/admin/client-order.php?id={id}', 'Order #{id}']` (manifests.php:114).
- `router.php:68-72`: for a numeric id or `new`, `/new` becomes `?new=1`.
- `router.php:75-80`: the item page is required directly (`$tvItemRoute = true`, query merged into `$_GET`), then `exit`. This runs **before** the router's `[$ok,$why] = Modules::canSection(...)` result is used (that's only checked at 132). Access on the item page is the page's own `tv_crawler_require_admin($pdo); tv_admin_require($pdo)` (client-order.php:27-28).
- client-order.php:36: a direct GET to `/admin/client-order.php` (no `$tvItemRoute`, no `embed`) gets a 301 to `/admin/backoffice/orders/<id>` (or `new`).
- The router seeds the workflows on every admin request: `if (Workflow::ready($pdo)) Workflow::seed($pdo);` (router.php:15).

---

## 2. The staff builder: `sync-storage/admin/client-order.php`

Header comment (2-7): `?new=1` starts one; `?id=` builds it (catalog products with options and quantity, GO Codes, custom lines, a proof per line), sets terms, sends the link, sees answers, and moves printed items through production. "Money after approval (refunds, credits, the ledger) is on the order page (admin/order.php)."

Libraries (11-24): ClientOrders, Shipments, QrIssue, Qr, VendorOrders, Promotions, QuickBooksPay, WorkOrders, Vendors, Uploads, cost-tab, Collateral, Clients.

Page state:
- `$ready = Products::ready` (37); `$hasDel = ClientOrders::hasDelivery` (38).
- `$editable = ClientOrders::editable($o)` means status draft or sent (ClientOrders.php:63; used at 497).
- `$anyEd = status !== 'void'` (553).
- Page guards:
  - Library too old: 290.
  - Migration not run: 291.
  - Not a client order: "Open it as an order" → `/admin/order.php` (294).
  - A shutdown handler prints fatal errors on the page (282-288).
- `ClientOrders::refreshPrices($pdo,$oid)` on every load: "a draft follows the catalog" (488).

### 2.1 Every POST `do=` action (switch at 51-259)
All posts check CSRF (48). Errors flash back. An Add-item error reopens its tab with what was typed (`$dlg` map, 268). Work-order errors land on `#work` or `#outside` (276). After any `add_*` or `new_product`, a `project` field moves the newest line into that project (260-263).

| do | line | Calls | Form in this file |
|---|---|---|---|
| `create` | 52-56 | `ClientOrders::create($pdo,$staff,$_POST)` | New-order form 466-471 |
| `terms` | 57 | `ClientOrders::saveTerms` | **none found** (terms/pay mode save in place, see 2.4) |
| `promo` | 58 | `ClientOrders::setPromo($code)` | Only the take-off form, `code=''` (762) |
| `add_product` | 59-61 | `ClientOrders::addProduct(product, qty, opt[], price, note)` | Add item → Catalog (991-1000) |
| `add_custom` | 62-64 | `ClientOrders::addCustom(title, qty, price, taxable, note, cost, 'custom', type)` | Add item → Custom (1044-1053) |
| `add_ship` | 65-67 | `ClientOrders::addCustom('Shipping: '.method, 1, price, taxable, note, cost, 'shipping')` | Add item → Shipping (1017-1024) |
| `add_go` | 68-71 | `ClientOrders::addGoCode(type, f[type], title, price)` | Add item → GO Code (1004-1014) |
| `line_save` | 72-74 | `ClientOrders::updateLine` | **none found** |
| `go_save` | 75-80 | `ClientOrders::updateGoCode` (+ `updateLine` if price) | "Edit what the code holds" (683-685) |
| `line_remove` | 81 | `ClientOrders::removeLine` | Row delete icon (729) |
| `tags` | 83-84 | `ClientOrders::setTags` | Item panel → Details (881-884) |
| `vjob_line` | 85-89 | `VendorOrders::setLine` | Item panel → Vendor, "This job is for" (949-950) |
| `test_open` | 90-95 | `ClientOrders::send(...,false)` if draft or expired, then 303 to `/order.php?t=<token>` | Strip menu "Open as the client" (516); Sandbox list (sections.php:308) |
| `send` | 96-98 | `ClientOrders::send($oid,days,$staff)` | "Send it" (1061-1064) |
| `reopen` | 99-101 | `ClientOrders::reopen(order, why)` | Strip menu "Reopen for changes" (515) |
| `staff_approve` | 102-105 | `ClientOrders::approve(order, name, $_POST)`; `releaseHold` if `release` | "Approve for the client" (1158-1164) |
| `release_hold` | 106 | `ClientOrders::releaseHold` | "Release to production before payment" (1201) |
| `outside_wo` | 107-117 | Checks vendor and lines (109-110), then `WorkOrders::processOutside(order, name, invoice_ref)`, then `WorkOrders::create(..., $go)` | "Handled outside: create the work order" (1169-1179) |
| `line_cost` | 118-125 | `Costing::startFrom` (act=load), `Costing::saveLine`, `Costing::saveRecipe` (act=recipe) | From `app/ui/cost-tab.php:45,49` via `tv_cost_pane` (958) |
| `line_vendor` | 126-128 | `Vendors::setForLine` | Item panel → Vendor "Who makes it" (939-942) |
| `wo_create` | 129-134 | `WorkOrders::create(order, $_POST, $staff, save==='send')` | Edit draft (1276-1279), New work order (1282-1285) |
| `wo_link` | 135-139 | `WorkOrders::handLink` (marks a draft sent) | Draft row "Copy link" / "Email the link" (1232) |
| `wo_send` | 140-148 | Optional email/cost update (raw SQL 145, 147), `WorkOrders::send`, `WorkOrders::markOrdered` | Row "Send"/"Send again" (1234-1235) |
| `wo_receive` | 149-152 | `WorkOrders::receive(ok, by, on, note)` | Row "Receive ▾": Accept it / Reject (1260-1264) |
| `wo_delete` | 153-155 | `WorkOrders::deleteDraft` | "Delete draft" (1266) |
| `wo_cancel` | 156-158 | `WorkOrders::cancel` | "Cancel" (1267) |
| `proof_retire`/`proof_restore`/`version_retire`/`version_restore` | 159-163 | `ClientOrders::retireProof` | Proofs tab (902, 904); WO files panel (1247, 1253, 1258) |
| `file_retire`/`file_restore` | 164-166 | `ClientOrders::retireFile` | Files tab (921); WO files panel |
| `wo_files` | 167-170 | `WorkOrders::setFiles(files[], tell)` | Row "Files (n) ▾" (1237-1251) |
| `wo_reprint` | 171-173 | `WorkOrders::reprint` | "Reprint"/"Make it again" (1265) |
| `wo_invoice` | 174-176 | `WorkOrders::setInvoiceNo` | "QuickBooks invoice # … Keep it" (1270) |
| `qb_merge` | 177-181 | `Access::can('backoffice.orders.pull', manager fin/ops)` then `QbPull::merge` | `tv_qb_merge_box` from `app/ui/qb-merge.php` (1183), not read |
| `record_pay` | 182-185 | Validates `PAY_METHODS`, then `ClientOrders::recordPayment(amount, method, ref, receipt)` | Quick confirm (1189-1190); full form (1194-1200) |
| `addr_save` | 186-187 | `ClientOrders::saveAddress` | **none found** |
| `line_ship` | 188-189 | `ClientOrders::setLineShip` | **none found** |
| `ship_save` | 190-192 | `ClientOrders::setShip($line,$_POST)` | Ship pop-up (970-978); the label goes first through the uploader (1668-1679) |
| `ship_mode` | 193 | `Shipments::setMode(mode, reason)` | Shipping box select (591-594) |
| `dest_save` | 194 | `Shipments::saveDest` | Edit destination (819-823); Add a destination (844-848) |
| `dest_delete` | 195 | `Shipments::deleteDest` | 824 |
| `lots_save` | 196-198 | `Shipments::saveLots` | Lots pop-up (854-860) |
| `pack_save` | 199 | `Shipments::savePack` | `app/ui/pack-form.php:7` (included at 835, 839) |
| `pack_delete` | 200 | `Shipments::deletePack` | 836 |
| `line_status` | 201-202 | `ClientOrders::setItemStatus(status, tracking)` | Item panel → Status (888-892) |
| `send_update` | 203-204 | `ClientOrders::sendUpdate(note)` | "Tell the client" (1294-1297) |
| `new_product` | 205-212 | Editable check, then `Products::save($np + status active)`, then `ClientOrders::addProduct` | Add item → New product (1027-1041) |
| `project_add` | 214 | `ClientOrders::addProject` (+ `setLineProject` if `line`) | Hidden form `#co-pjf` (780), via JS 1634-1638 |
| `project_rename` | 215 | `ClientOrders::renameProject` | Project header input (643) |
| `project_remove` | 216 | `ClientOrders::removeProject` | "Take off project" (647) |
| `line_project` | 217 | `ClientOrders::setLineProject` | Row project menu (726) |
| `line_edit` | 218 | `ClientOrders::editLine($line,$_POST)` | Edit pop-up template (734-749) |
| `file_remove` | 219 | `ClientOrders::removeFile` | Order docs (787), Files tab (922) |
| `options_save` | 220 | `ClientOrders::setOptions` | **none found** (options are edited in `line_edit`) |
| `part_add` | 221 | `ClientOrders::addPart` | Bundle "+ Add" (669) |
| `part_save` | 222-224 | `ClientOrders::setPart` per field | Bundle row Save (664-668) |
| `part_remove` | 225 | `ClientOrders::setPart(...,'remove')` | Bundle × (668) |
| `client_attach` | 226-228 | `Clients::attachOrder(listing)` | Business: Connect (573), The person alone (578) |
| `client_private` | 229-233 | `Clients::makePrivate` + `attachOrder` | "Make a private listing" (577) |
| `client_find` | 234-238 | `Clients::find` (into the session) | Find form (575) |
| `reorder_art` | 239-243 | `Collateral::reorder(ids, [], true)`; redirects to the new order | Duplicate ▾ (522) |
| `duplicate` | 244 | `ClientOrders::duplicate($oid,$staff,test)` | Duplicate ▾: "Test copy, billed to you" (519), "Copy for the same client" (523) |
| `proof_remove` | 245 | `ClientOrders::removeProofFile` | Proofs tab Remove (906) |
| `final_remove` | 246 | `ClientOrders::removeFinal` | Print-ready × (705) |
| `test_delete` | 247-248 | `ClientOrders::deleteTest` | Sandbox list only (sections.php:309) |
| `cancel` | 249 | `ClientOrders::cancel(why)`; `why` is a hidden empty value (1067) | "Cancel the order" (1067) |
| `complete` | 250-253 | `ClientOrders::complete($oid,$staff, openBeforeComplete(...))` | "Complete the order" (1317) |
| `problem_ok` | 254-255 | `ClientOrders::resolveProblem(fb)` | "Mark resolved" (1308) |
| `stage` | 256-258 | `ClientOrders::setStage(stage, tracking)` | **none found** in the mirror's PHP/JS (the Pulse's `do: stage` goes to `admin/api/pulse.php`, not here) |

Form `do` values with **no case in the switch**: `proof` (700, 910), `item_files` (924), `order_files` (788). The JS at 1658-1687 intercepts every `multipart/form-data` form and sends the files through `TvUpload.send` to `/api/upload.php`:
- target `order_proof` for `proof`;
- target `order_file` for `item_files`, `order_files` and labels.

A `ship_save` or `pack_save` label is uploaded first, then the form is submitted with `label_id` (1676-1678). Also through the uploader:
- "Replace" on a proof file: target `order_proof` with ctx `replace` (1640-1647).
- "+ Add print-ready file": target `order_final` (1649-1655).

In-place edits go to a separate endpoint, `POST /api/admin/client-order.php` (JS 1458-1529), with these actions:
- `field` → `ClientOrders::setField`
- `line` → `ClientOrders::setLineField`
- `reorder` (drag ⋮⋮) → `ClientOrders::reorder`
- `customer` → `setField` for name, company, phone, email and the bill address
- `wo_link`

(`sync-storage/api/admin/client-order.php:25-49`, read only for its cases.)

### 2.2 The "New order" page (no `id`, 449-485)
- Title "New order", with breadcrumbs E-Commerce → Orders → New order (450).
- "A past client (type to find them)" datalist from every distinct `bill_email` (up to 500). Picking one fills the form (476-481).
- Form `do=create`, `novalidate`:
  - Client's name (`required`), Email (`required`, type email).
  - Business (optional), Phone (optional).
  - Note: "If they have a Traversence account with this email, the order is linked to it."
  - Button: "Start the order".
- Success flash: "Started. Fill in Bill to and Ship to, then + Add item." (55).

### 2.3 The order page, top to bottom

**Status line** (`$state`, 501-509): Draft / Sent: the link has expired / Changes requested / Sent: waiting on the client / Deposit paid: $X left / Approved: waiting for payment / else `Orders::STATUS`.

**Command strip** (`tv_command_strip`, 526-547; decisions/0129)
- `title` "Order #N"; `news_key` `order-<id>`. `news_tone` is bad if there's an open problem or the state is red.
- `news` lines (537-541):
  - The state, or for pending: deposit/payment wording.
  - The stage: "With the vendor: …" for ordered/production.
  - "Test order (sandbox)".
  - An open support ticket, or "All out the door: waiting on the client…".
  - For sent orders: when the client last opened it (`ClientOrders::opens`).
- `send` (542):
  - draft or sent: "Send to the client" / "Send again" → `#co-send-btn`;
  - pending or paid: "Send the client an update" → `#co-update-btn`;
  - otherwise none.
- `finish` (543): "Complete the order" → `#co-complete` when pending/paid, a client order, and stage ≠ complete.
- `back` and `close`: `/admin/backoffice/orders` (544).
- `more` (☰) (529-533):
  - "What the client sees" (`ClientOrders::url`, new tab) when not draft.
  - "Money: refund, credit, history" → `/admin/order.php?id=` when not editable.
  - "Documents and files" → `#docs` when `hasInline`.
  - "Cancel the order" → `#co-cancel-btn` (danger) when draft or sent.
- `menu_html` (`$coMenu`, 514-525):
  - "Reopen for changes" (pending only). **Disabled** with the `ClientOrders::reopenBlock` reason shown under it.
  - "Open as the client" (sandbox, not void).
  - "Duplicate ▾": "Test copy, billed to you" (always the QuickBooks sandbox); "Reorder with the same art" (only if lines have `collateral_id`); "Copy for the same client".
- `new` (546): forms that wait for their own button: `wo_create` without `wo`, `add_*`, `project_add`, `new_product`, `duplicate`, `reorder_art`, `reopen`, `test_open`.
- `skip`: `client_find` (547).

**Customer** (560-564)
- When editable: a "Customer" box with a datalist of past client emails (up to 300). A match posts the in-place `customer` action (JS 1488-1489).
- "Email:" is editable in place only when editable.

**Business** (`<details class="co-biz">`, 565-582; decisions/0101 addendum 2026-10-28)
- On load, if `listing_id IS NULL`, `Clients::autoConnect` runs (491-494). It connects a clear match or makes a private listing.
- Shows the connected business with a pill (Private listing / On Traversence / not claimed), "The person alone", or "Not connected yet".
- Candidate listings each have "Connect" (**disabled** if already connected).
- "Find a listing" form; "Make a private listing" (with a confirm); "The person alone (no listing)" (**disabled** when already set to 0); "Its contacts" → `/admin/backoffice/clients/<id>`.

**Bill to / Shipping / Order details** (583-613): three boxes, values edited in place (`contenteditable` spans, `$ed`, 555-557).
- **Bill to**: name, company, phone are editable only when `$editable` (draft or sent). bill_street, city, state, zip are editable when `$anyEd` (not void).
- **Shipping**:
  - With `Shipments::ready`: a "How it ships" select. It has a disabled, preselected placeholder `Choose how it ships…` when the mode is empty, choices from `Shipments::MODES`, and auto-submits `ship_mode` on change. It's **disabled** when void (592).
  - Mode `none`: a reason select from `Shipments::REASONS`, auto-submit (593).
  - Mode `multi`: "N destinations: Shipping & manifests" (596).
  - Ship to name, street, city, state, zip are editable in place when not void (598-599). "Same as Bill to" copies company or name plus the bill address, each saved (600; JS 1482-1487).
- **Order details**:
  - Invoice no. (`ClientOrders::ref`).
  - **Terms**: a select from `DUE_TERMS` (`receipt` "Due on receipt", `net15`, `net30`; ClientOrders.php:41), preselected to the current value (default `receipt`). Saved in place as field `due_terms`. Shown only when `hasItems && anyEd`, else as text (605).
  - Invoice date: `sent_at` or `created_at`.
  - **Due date**: `<input type=date data-f="due_date">`. Its title says "Empty: from the terms (…)" (607).
  - Order no.: `po_number`, in place.
  - **Payment**: a select of `PAY_MODES` labelled Pay in full / Deposit / Invoice later / Outside QuickBooks. "Outside" only when `$hasDel`. Preselected to `pay_mode`. Editable only when `$editable`, else text (609).
  - Deposit %: in place, only when `pay_mode === 'deposit'` (611).
  - Small text: "Link good until …" and "Approved by … at …" (612).
- **Message to the client**: `note`, in place, editable only when editable (614).

**Items table** (`#lines`, 616-776)
- Columns: drag, #, Product / service & description, Qty, Rate, Amount, Tax, icons (617).
- Projects (decisions/0101 addendum 2026-10-27): each group has a header with a rename input (editable), "Project · N items · $subtotal", "+ Add item here", and "Take off project" (640-650).
- Each line (651-752):
  - Title and description (`note`) editable in place when editable. The options text shows under the title.
  - **Bundles** for custom lines in a project (661-672): "Bundle lines into this item (only you see them)". Each row has line, qty, each, our cost, amount, Save, ×. Inputs are **disabled** when not editable. Note: "The item's price now becomes its first line…".
  - **GO Code** lines (673-686):
    - The short code is issued on view if still pending and not a direct type (674).
    - QR image; SVG (print), PNG and "Try it" buttons.
    - Note: "live" / "a proof until the order is paid (works, not counted)".
    - "Edit what the code holds" form (`go_save`) when editable.
  - **Status pill** (689): opens the item panel's Status tab. Then tags, then the vendor's own status button (`co-vst`) when a vendor job applies (691).
  - **Ship line** (694-696): shown when the line has ship data. Opens the ship pop-up.
  - **Files row** (697-701): "Proof vN (n files)" links that open in the proof lightbox, the proof's status pill, "N earlier versions", file kinds. "+ Upload proof" (`do=proof`, multiple, PDF/PNG/JPG/WebP) when editable, not shipping, not GO Code.
  - **Print-ready file** for creative items in a project with no print item (702-708): files with pill "Released to the client" / "Released once paid in full", × to remove, and "+ Add print-ready file" once a proof is approved and the order isn't void.
  - The latest client change request: "Client asked (vN): …" (709).
  - **Meta line** (710-714): Catalog / GO Code / Shipping / Custom; cost or catalog cost; catalog price if higher; "COGS $X ✓ / (not checked)" or "Work out its COGS". These open the Cost tab and are staff only.
  - Qty: GO Code is fixed at 1; otherwise editable in place (`data-lf=qty`, the run quantity for catalog products) (716).
  - Rate: editable in place (`data-lf=unit`) (717).
  - Amount: `unit_cents × qty` (718).
  - Tax checkbox `data-lf=taxable`, saved on change. **Disabled** when not editable (719).
  - Icons (720-730):
    - Edit (editable, not shipping).
    - Status and Files (not shipping).
    - Project menu (projects on, editable, not shipping). The current project is a **disabled** "✓" item; "+ New project…".
    - Ship icon (`hasInline`, not void, not shipping).
    - Delete, with a confirm (editable).
- **Summary** (755-775):
  - Items and shipping split, with each shipping row "→ to".
  - Promo preview: "−$X code … (comes off at approval)" and the note "some lines capped at the 65% profit floor".
  - Subtotal.
  - When editable: "Make a promo code" (peek `/admin/growth/promotions`) and "Take <code> off" (`do=promo`, code '').
  - After approval: tax, total, paid, left. Before approval: "at approval: sales tax (from their address)".
  - Staff only: vendor margin (`VendorOrders::margin`; decisions/0106) and COGS profit (Jason, 2026-10-29).

**+ Add item / + New project** (778-781): editable only.

**Files & versions** (`#docs`, 782-803; when `hasInline`)
- The order's own documents (our team only), with Remove.
- An upload form (`order_files`) with a Kind select from `FILE_KINDS` minus `label`. The first option is preselected; there's no placeholder.
- Then a per-item table: proofs (the client sees) / other files (ours), opening the item panel.

**Shipping & manifests** (`#ship`, 804-865; when `Shipments::shipped($o)`)
- `ensureLots` runs on view (805).
- Per destination:
  - Estimate against actual (sum of packs).
  - Manifest link `/admin/manifest.php?id=&dest=`.
  - "Edit this destination" (`dest_save`); the estimate is **readonly** when not editable.
  - Remove a non-main destination.
  - Lots table, packed or not.
  - Packs with a packing slip, a label, and Edit / Remove via `app/ui/pack-form.php`.
  - "+ Add a pack".
- "+ Add a destination" when the mode is `multi`.
- "Print the master manifest ↗" (808).
- Lots pop-up (851-862): rows of qty, lot label, "Goes to" (hidden when the mode is single); "+ Lot"; "Split into N equal lots"; a running sum "n of qty ✓". JS 1531-1556 replaces the row ship icon's action with this editor when the order ships.

**Item panel** (`<aside id="co-item">`, 866-961; decisions/0107)
- A side panel with ☰ for tabs and ×. Tabs are built per line (871):
  - always `details`, `status`, `proofs`;
  - `files` and `shipping` when `hasInline`;
  - `vendor` when `VendorOrders::ready`;
  - `cost` unless the line is a GO Code.
  - Shipping lines get no panel (870).
- **Details** (874-886): item, options, description, "Qty × rate = amount", a status button. Note: "Change the name, description, quantity and rate on the order itself." Tags (staff only): text plus quick tags from `ClientOrders::TAGS`, "Save tags".
- **Status** (887-896):
  - A select of `ITEM_STATUS` grouped in optgroups ("Before approval", "Approved", …; ClientOrders.php:31-…), preselected to the current item status.
  - Tracking.
  - "Save the status".
  - Note: "The client isn't emailed: use Send an update… The vendor's status moves this item forward on its own (never back)."
  - History for the line.
- **Proofs** (897-915):
  - Each version, newest first, with its pill, dates and "final approval initialled XX from IP".
  - Retire or restore a version or a file (`canRetireProofs`, not void).
  - Replace a file (editable).
  - Remove a file (editable).
  - "Client asked: …".
  - New version upload (`proof`, multiple): "PDF, PNG, JPG or WebP, up to 12 files. The client approves or asks for changes on the version as a whole."
  - When not editable: "The client has approved the order, so its proofs are final."
- **Files** (917-930): by kind, with Retire/Restore (`canRetire`) and Remove. Upload (`item_files`) with a Kind select from all `FILE_KINDS` (first preselected). Note: "Artwork, reference and documents: … up to 1 GB each. Labels: PDF or an image."
- **Shipping** (931-934): the ship label text; "Edit the shipping" opens the pop-up.
- **Vendor** (936-956):
  - "Who makes it": a select with `Not chosen yet` (value 0) plus active vendors. It auto-submits `line_vendor`.
  - "From the product (X); choose to set it for this item."
  - Links "Set up vendors" (peek `/admin/backoffice/vendor_list`), plus "Send a work order" (`#wo-new`, pending/paid/part_refunded) or "Handled outside: create the work order" (`#outside`, draft/sent).
  - "No vendor job tracked yet. Once the vendor has it, add their job from the order's case in the Pulse (Add a vendor job, or Paste from 4OVER)." (944).
  - Jobs here: their status mapped to the item status (`VendorOrders::itemStatusFor`), completion date, and "This job is for" (whole order or one item, auto-submit `vjob_line`).
  - Margin on the item's own jobs.
  - "All vendor orders" → `/admin/vendors.php`.
- **Cost** (958): `tv_cost_pane($pdo,$l,$hidden)` from `app/ui/cost-tab.php` (not read in full). It posts `do=line_cost` with `act` load, save, check, none or recipe.

**Edit pop-up** (`#co-edit`, 962-966; template per line 732-749)
- Name (`required`), Description.
- Non-GO Code lines: "How many (the run)" or "Quantity" (`required`), "Rate (each)". Both are **readonly** for a bundle, with title "A bundle is one item…" / "Set by the bundled lines".
- Catalog product: checkbox "Use the catalog's price for this run (Product)", checked unless the rate was typed. Typing a rate unticks it (1702).
- Warning if the run size was lost: "Its run size was lost when it was renamed: enter it, and check the rate." (743).
- Catalog options: one `<select name="opt[i]">` per option (see 2.5).
- Custom lines: "Kind (promo codes, and how its files go to the client)" select. It starts with `Not set` (value ''), then `Promotions::SERVICE_TYPES` except `go_code`, preselected to `service_type` (747).
- "Taxable" checkbox.
- Button: "Save the item" (`line_edit`).
- The form is `novalidate`, so the browser doesn't enforce `required`.

**Ship pop-up** (`#co-ship`, 967-980; shown when `hasInline` and not void)
- Style radios from `SHIP_STYLES` (`drop` Drop Ship, `courier` Courier, `pickup` Pick Up; ClientOrders.php:1173). **None is checked in the HTML.** On open, the JS checks the line's saved style, or `drop` (1499).
- Carrier select with `—` (empty) plus USPS/UPS/FedEx/Other.
- "Ship to (empty: the order's Ship to)" textarea; its placeholder is the order's ship-to text.
- Shipping charge (to the client) and Our cost. Both are **readonly** when not editable; the charge's title is "Approved: the charge stays".
- Tracking.
- "Add a shipping label (PDF or image)".
- "Save". Pickup hides the carrier, address and money fields (1494).

**Add an item pop-up** (`#co-add`, 982-1057; editable only). Tabs: Catalog, New product, GO Code, Custom, Shipping.
- **Catalog** (989-1002): if the catalog is empty, it links to `/admin/products.php#new`.
  - Product select (`required`, but the form is `novalidate`). There's no placeholder, so the **first product is preselected**.
  - Run size (number, `min=1`, a datalist of tier quantities; the first tier fills in when empty, 1610).
  - "Price for the line (empty: the catalog's)".
  - Option selects are drawn by JS (see 2.5).
  - Hint: "Catalog price for N: $X · turnaround" (1613-1621).
  - Note on the line (shown to the client).
  - "Add it"; link "The catalog".
- **GO Code** (1003-1015): type buttons from `QrIssue::FIELDS` (vcard is pressed by default; a hidden `type=vcard`). Fields per type; the hidden types' inputs are disabled (1626). Name on the code. Price, prefilled `QrIssue::PRICE_CENTS`. Note: "Its trvgo.us address and QR are made now… starts working once the order is paid."
- **Shipping** (1016-1025): Method (prefilled `Ground`), Vendor's charge, Charge the client, Note, "Sales tax applies to shipping" (unchecked).
- **New product** (1026-1042): Product name; Kind (`Products::KINDS`, first preselected); Vendor (text); Run size; Vendor cost for the run; Markup %; "Your price for the run (empty: cost + markup)"; Note; Options (one per line); Description; "Sales tax applies" (checked). Button "Create and add it". Server: a price or a cost is required (209); a service defaults to qty 1 (208).
- **Custom** (1043-1054): What it is; How many (default 1); Price each; Your cost each (optional); Kind select: Creative / Print / **Additional service (preselected)**; Note; "Sales tax applies" (checked).

**Send it** (1059-1068; editable only, that is draft or sent)
- "The link works for (days)": `min 1`, `max 90`, default `ClientOrders::DAYS` = 14 (ClientOrders.php:45).
- Button "Send to the client" / "Send again" with confirm "Email <bill_email> the order to review?".
- When sent: the link text and "Copy the link".
- "Cancel the order" (danger confirm; "The client is told." when not draft).

**Approve for the client (skip their checkout)** (`#bypass`, 1153-1165; draft or sent with lines; open by default when `pay_mode=outside`)
- Text when outside: "Paid outside QuickBooks: no invoice and no card form. Sales tax isn't added for you; if it's owed, add it as a line (no tax on it)."
- Otherwise: "Makes the QuickBooks invoice now (with sales tax, from Bill to above)…"
- Fields: Who approved it (prefilled `bill_name`). If `ClientOrders::delivery === 'ship'`: "Ship to the billing address" checkbox, plus ship street, town, state (`maxlength 2`, placeholder AZ) and ZIP. Then "Release to production now (don't wait for the payment)" (unchecked).
- Button "Approve for the client" (confirm).

**Handled outside: create the work order** (`#outside`, 1166-1181; draft or sent, `WorkOrders::ready`, with print lines)
- Text: "For an order invoiced and paid outside Traversence… approved for the client with no invoice made here and nothing posted to QuickBooks, put into production, and its work order goes to the vendor from orders@trvgo.us… The client isn't emailed (unless they asked for progress emails)."
- Fields: Who approved it; "QuickBooks invoice # you made (optional, for reference)".
- A previous draft, cancelled, declined or rejected work order is filled back in (1172-1175).
- `$woFields` (see Work orders), then the split save button `$woBtns(true)`.
- "Every way processes the order. Not ready to send? Save it: it waits under Work orders…"
- Server (`WorkOrders::processOutside`, WorkOrders.php:301-314):
  - refuses unless draft or sent;
  - sets `pay_mode='outside'`;
  - `ClientOrders::approve` (put back if it fails);
  - `setInvoiceNo`;
  - `releaseHold` if the stage is empty;
  - logs the event `outside`.

**Work-order fields** (`$woFields`, 1072-1141; decisions/0101 addendum 2026-10-28)
- **Vendor select** (when `Vendors::ready`): `Choose…` (value ''), active vendors with `data-email` and `data-pref`, then `+ A new vendor…` (value `new`, which reveals a name input). **Preselected** to the vendor of the first print line that has one (`Vendors::forLine`), or the draft's `vendor_id` (1077-1078, 1084). Choosing one fills the email, the part numbers, and ticks files by the vendor's `file_pref` (1437-1447).
- "Their orders email (it goes from orders@trvgo.us)".
- Without `Vendors::ready`: plain vendor and email inputs.
- **Items and files for the vendor** (fieldset 1100-1124): one checkbox per `WorkOrders::printLines` line.
  - Print lines are ticked by default. Creative work and services are ticked only when there is no print item (1105-1106).
  - Options text.
  - COGS: "COGS $X, the vendor's share $Y · ✓ checked / not checked" or "No COGS yet", with "Check/See its cost" (1108).
  - With `statusReady`: "their part #" input, remembered per product and vendor in `product_vendor_parts` (1109-1113).
  - Print lines: "Specs for the vendor" textarea, prefilled from the options or the last specs. "Costed with: …" and a red status line "The specs don't match the costing: X isn't in these specs…" (1114-1119).
  - File checkboxes per item (`WorkOrders::choices`), then whole-order files.
- With `statusReady`:
  - Project name (from the line's project, or the first title).
  - "Total cost (our cost of goods; they accept or decline at it)". Auto-filled with the ticked items' vendor share, and it follows the ticks until typed (`data-wo-auto`; JS 1384-1386). A "Reset to COGS ($X)" button appears when the cost differs (1387-1392).
  - "Must have date" (date), "Must have by" (time).
  - Delivery method select: `Choose…` plus `WorkOrders::DELIVERY`.
  - Ship-to box: a textarea prefilled with `WorkOrders::shipText($o)`, or the one address. With several addresses: a "Use an address" select (`Choose…`, each, "All of them (per the labels / manifest)").
  - Shipping labels and manifests checkboxes (ticked by default).
  - "Purchase order no. (optional)".
  - "Production notes (optional)".
- Without `statusReady`: ship-to, "Needed by", "Notes for the vendor".
- Always: checkbox "Mark the order "Work order issued" when it's sent", checked (1139).
- **Split save button** (`$woBtns`, 1142-1152), "one save button, four ways (like QuickBooks)":
  - `stay` "Save draft, keep editing"
  - `new` "Save and start another"
  - `close` "Save work order" (the default main button)
  - `send` "Save and send to vendor"
  - ▾ lists all four. The last one used is remembered in localStorage `tvWoSave` and becomes the main button (1364-1405).
  - Before `send`, the confirm adds "COGS isn't checked for …" for ticked print items whose COGS isn't checked, and "No must-have date: the vendor sees "Not set"" (1371-1375).
  - On the outside form it also asks first "Process this order against QuickBooks invoice #N…" or "…outside QuickBooks (no invoice made here or there)…" (1376).

**QuickBooks merge box** (1183): `tv_qb_merge_box` when the order has `invoice_no` or `?qbmerge=`, and isn't void.

**Payment hold / Waiting for payment** (`#payhold`, 1184-1203; status `pending` and money left)
- The heading is "Payment hold" when `stage === ''`, else "Waiting for payment".
- "The client's checkout:" shows the method they chose (`CLIENT_METHODS`) and any reported reference ("they say it's sent").
- **Quick confirm**: when `PAY_METHODS[pay_method]` exists and (cash or reported), a one-click "✓ Confirm $X received" posts `record_pay` with `receipt=1`, amount `ClientOrders::dueNow`, method and ref (1188-1191).
- When held: "On a payment hold: it goes to production when the payment is in…".
- "When the money arrives: $left left…" plus QuickBooks wording. With `invoice_id`: "Recording it also receives it on QuickBooks invoice N". With a hand-made `invoice_no`: "nothing is posted to it: receive the payment there too…" (1193).
- **Record payment form**: Amount (prefilled with what's left); "How it came in" select: `Choose…` (preselected unless `pay_method` is set) plus `PAY_METHODS` (wire, cash, check, zelle, venmo, bank, card "Card (another terminal)", other; ClientOrders.php:42); Reference; "Email the client a receipt" (checked unless outside). Button "Payment received: release to production" (when held) or "Record the payment". The confirm text changes with the receipt box (1408-1415).
- When held: "Release to production before payment" (`release_hold`).

**Work orders** (`#work`, 1216-1287; status pending, paid or part_refunded, `woReady`, print lines)
- Table: work order (peek `/wo.php?w=<token>`; project, items, files, must-have, delivery, cost "(agreed)"), vendor and email, status pill plus job no., received by, tracking, sent/opened, vendor note, received or rejected; history.
- Row actions (1228-1267):
  - Edit (draft, `statusReady`) → `?wo_edit=<id>#wo-edit`.
  - "See it as the vendor".
  - Copy link / Email the link: a draft does it via the `wo_link` form with a confirm ("giving its link marks it sent"); otherwise a client-side copy and a `mailto`.
  - Send / Send again (draft or sent). The form adds a `required` email input if there's no vendor email, and a `required` "Total cost" input if `statusReady` and the cost is null.
  - Files (n) ▾ (sent and not closed): tick files with newer/older copy labels, Retire per file, "Email <vendor> what changed" (checked), "Save files". Retired files fold separately with Restore.
  - Receive ▾ (`statusReady`, status accepted, production, shipped, ready or complete): Received by (prefilled with your name), Date (today), Note, "Accept it" / "Reject" (danger).
  - Reprint / Make it again (rejected, declined or cancelled).
  - Delete draft (draft never sent) or Cancel (not received, cancelled, rejected or declined).
- No work orders: "None yet. Send the vendor what to make, with the files… (no client prices or billing)."
- When `invoice_id` is empty: "QuickBooks invoice # (made by hand; for reference only, nothing is posted to it)" with "Keep it" (`wo_invoice`) (1270).
- Edit draft block `#wo-edit` (`?wo_edit=` with a draft) (1272-1280); "+ New work order" `#wo-new` (1281-1285).

**Tell the client** (1289-1299; not draft or void)
- "Set each item's status in the list above; the client isn't told until you send an update."
- "Place it with: <vendors>, using the approved proof(s) above."
- Optional note; "Send update" (`send_update`, confirm).

**Complete the order** (`#finish`, 1301-1320; pending or paid, client order)
- Open problems: "Support ticket #N:" (`Pulse::caseNo`), "Reply from the ticket" → `/admin/pulse/stream#case=fb:<id>`, "Mark resolved".
- Reviews: stars and words.
- If `stage === 'complete'`: "This order is done… in the archive (Order management → Completed)… a reorder re-runs each item's costing at today's costs…".
- Otherwise "Waiting on the client" text (`outTheDoor`), "Still open: …" (`openBeforeComplete`: money due, work orders not received, items not shipped), and "Complete the order" with a confirm that repeats what's still open.

**History** (1322-1323): every `Orders::events`, newest first, with the actor or "the client or the system".

### 2.4 What's greyed or read-only, and why (as coded)
- **Disabled with a reason**: "Reopen for changes", with the `reopenBlock` text (ClientOrders.php:988-996):
  - "It's paid: change it with Money (credit or refund), or duplicate it."
  - "Only an approved order waiting for payment reopens."
  - "A payment is recorded on it…"
  - "Its QuickBooks invoice N was made here…"
  - "Work order X is with the vendor: cancel it first…"
- **Disabled without a stated reason**:
  - Business "Connect" on the one already connected (573).
  - "The person alone" when already set (578).
  - Bundle inputs when not editable (665-666).
  - Tax checkbox when not editable (719).
  - Ship-mode select when void (592).
  - The current project in the project menu (726).
- **Readonly**:
  - Ship charge and cost when not editable; the charge's title is "Approved: the charge stays" (974).
  - Destination estimate when not editable (820).
  - Edit qty and rate for bundles (739-740).
- **Not rendered at all** (rather than greyed) when not editable:
  - + Add item, Edit icon, Delete icon, Upload proof, the Send section and "Cancel the order" button (the `if ($editable)` block spans 982-1069), "Edit what the code holds", promo controls, project rename and remove, the Customer finder.
  - Name, company, phone, email and note become plain text.
  - Payment mode becomes text.
  - Terms and Due date become text when void or without `hasItems`/`hasInline`.
- **Server-side checks**:
  - `qb_merge`: `Access::can('backoffice.orders.pull', manager fin/ops)` (179).
  - `new_product`: `ClientOrders::editable` (206).
  - `test_open`: sandbox only (92).
  - `outside_wo`: vendor and lines (109-110).
  - No other action in the switch calls `Access::can`. The page as a whole requires `tv_crawler_require_admin` + `tv_admin_require` (27-28).

### 2.5 How lines and options are entered
- **Add → Catalog** (JS 1600-1622): on product change, `#co-opts` is rebuilt as
  `'<label>' + o.name + '<select class="hb-in" name="opt[' + i + ']">' + o.choices.map(c => '<option value="' + c.label + '">' + c.label + (c.pct ? ' (+pct%)' : (c.add ? ' (+$x)' : '')) + '</option>') + '</select></label>'` (1607).
  - **No placeholder option, no `selected`, no `required`.** The browser therefore preselects each option's **first choice**.
  - Server `ClientOrders::addProduct` (ClientOrders.php:257-260) also falls back: `if (!in_array($v, array_column($o['choices'], 'label'), true)) $v = $o['choices'][0]['label'];`.
  - The line's `options` text is saved as "Name: choice · Name: choice".
- **Edit pop-up** (746): `<select class="hb-in" name="opt[<?= $oi ?>]">` with `<option<?= ($cur[$oi] ?? '') === $ch['label'] ? ' selected' : '' ?>>label</option>`. No value attribute, no placeholder, no `required`. `$cur = ClientOrders::pickedOf` → `Products::pickedFrom`, which itself defaults to the first choice when a name isn't found (Products.php:213-218).
- **Qty and rate**: edited in place on the row (blur saves; Enter blurs; Escape reverts; 1474-1478), or in the Edit pop-up. For a catalog product the qty shown is the run (`ClientOrders::runQty`) and the rate is `ClientOrders::rate`.
- **Drag reorder** with ⋮⋮ saves the order through the in-place endpoint (1508-1528).
- **Price**: catalog price from tiers, or a typed "Price for the line". A draft re-prices from the catalog on load (`refreshPrices`, 488).

### 2.6 Ship-to and bill-to
- Bill to: in place in the Bill to box; filled from a past client via the Customer datalist (`do: customer`) or at creation (name, email, company, phone only).
- Ship to: in place in the Shipping box, plus "Same as Bill to". "Approve for the client" has its own ship fields and a "Ship to the billing address" checkbox when delivery is ship (1160-1161).
- Per item: the ship pop-up's address textarea (empty means the order's Ship to). With `Shipments::shipped`: destinations, lots and packs.
- On a work order: its own `ship_to` textarea and the "Use an address" select.
- `addr_save` and `line_ship` exist in the switch with messages ("Address added. Choose it under any item: Ships to.") but no form in the mirror posts them.

### 2.7 Terms and delivery
- Terms are the `due_terms` select plus an optional `due_date` override, saved in place. Pay mode and deposit % are saved in place. `do=terms` (`ClientOrders::saveTerms`) has no form here.
- Delivery: "How it ships" (`ship_mode`) with reasons for `none`. `ClientOrders::delivery($o,$lines)` decides whether the approve form asks for ship fields. Work-order delivery method is `WorkOrders::DELIVERY` (values not read).

### 2.8 Proofs and files
- Proofs go up as versions (one or several files) through `/api/upload.php` target `order_proof`. Replace one file in a version (same version), Remove, Retire or Restore a version or file. The client's notes show per version. A GO Code's proof is its QR (`mime 'go-code'`).
- Item files (artwork, reference, documents, labels) use target `order_file` with a kind. Order documents are `order_files` (kind ≠ label).
- Print-ready files for creative items use target `order_final`; they're released to the client once paid in full (704).
- Comment 1658-1660: "Files on this order (proofs, artwork, reference, labels) go through the site's one uploader (js/tv-upload.js, decisions/0127): straight to S3, up to 1 GB, with the size checked first, a progress bar and the result beside the item."

### 2.9 Send, staff approve, record payment, work orders, 4OVER, cancel, complete, reopen (summary)
- **Send**: `do=send` → `ClientOrders::send(days)`; also the strip's Send → `#co-send-btn`. A sandbox order can be opened as the client without email (`test_open`).
- **Staff approve**: `do=staff_approve` → `ClientOrders::approve`, plus optional `releaseHold`.
- **Handled outside**: `do=outside_wo` → `processOutside` + `WorkOrders::create`.
- **Record payment**: `do=record_pay` → `ClientOrders::recordPayment` (the message appends `$qbm` from the call); the quick "Confirm received" uses the same action.
- **Release before payment**: `do=release_hold`.
- **Work orders**: `wo_create` / `wo_send` / `wo_link` / `wo_files` / `wo_receive` / `wo_reprint` / `wo_cancel` / `wo_delete` / `wo_invoice`.
- **4OVER**: no link or form in client-order.php. The Vendor tab's text sends staff to "the order's case in the Pulse (Add a vendor job, or Paste from 4OVER)" (944). That is `admin/api/pulse.php` `{do: parse4, text}` / `{do: import4, key, text, order_for}` (pulse.php:11), not read further.
- **Cancel**: `do=cancel` → `ClientOrders::cancel(why='')`; offered only for draft or sent (button at 1067 inside the editable block; strip ☰ 533).
- **Complete**: `do=complete` → `ClientOrders::complete` → `setStage('complete', …, mail=false)` + all items `complete` + thank-you (ClientOrders.php:692-704).
- **Reopen**: `do=reopen` → `ClientOrders::reopen(order, why)` (the `why` field isn't in the form; it's sent empty).

---

## 3. Workflows

### 3.1 The engine (`sync-app/admin/Workflow.php`)
- Built-ins come from `workflows.php` (`builtins()`, 58-62). They're seeded once into `wf_workflows` and `wf_versions` (v1, "Built in") (67-78). The seed runs on every admin router request (router.php:15).
- `item()` (116-136): for a built-in, the stage is the **adapter's** stage, read from the site's own records (orders.status and stage). A `wf_items` row is used only to keep a builder-added stage that maps to the same system stage (`'system'` key, 128-131).
- `moves()` (139-157):
  - moves from the current stage;
  - `auto` moves are shown as not allowed, "Happens on its own: …";
  - others check `Access::can('<wf>.move.<key>', who)`;
  - any-stage actions check `.act.`.
- `move()` (162-208):
  1. finds the move;
  2. checks `from`;
  3. refuses `auto`;
  4. checks Access;
  5. checks each `asks` field is present;
  6. runs each named check;
  7. runs the adapter `op` (the owning library);
  8. upserts `wf_items` (stage, `entered_at`);
  9. writes `wf_history`;
  10. runs effects (email, assign; failures recorded);
  11. writes `audit_log`.
- `checks()` (20-34):
  - `note`;
  - `proofs_approved`: no `order_proofs` in waiting or changes;
  - `paid_in_full`: paid ≥ total, **or** `pay_mode` is invoice or outside;
  - `has_owner`.
- `clock()` (218-224): hours in the stage against the SLA.
- `validate()` (229-265): a built-in's `op`/`locked` stages and its `op` moves (with their checks and op) can't be removed or changed. `publish()` (277-299) can carry items to the new version.

### 3.2 `client_order` (workflows.php:15-72)
"Back-Office: an order our team prepares for a client (decisions/0101)". `module backoffice`, `entity order`.

| Stage key | Label | Kind | Owner dept | SLA (h) | Protection |
|---|---|---|---|---|---|
| draft | Draft | start | sal | 72 | locked |
| sent | With the client | | sal | 120 | locked |
| approved | Approved, waiting for payment | | fin | 72 | locked |
| paid | Paid | | svc | 24 | locked |
| production | In production | | svc | 120 | op stage |
| ordered | Work order issued | | svc | 168 | op stage |
| shipped | Shipped | | svc | 168 | op stage |
| ready | Ready for pickup | | svc | 168 | op stage |
| complete | Complete | end (done) | | | op stage |
| cancelled | Cancelled | end (cancelled) | | | locked |
| refunded | Refunded | end (cancelled) | | | locked |

| Move | From → to | op | Checks / asks | Who |
|---|---|---|---|---|
| send "Send to the client" | draft → sent | send | | staff, sal/svc |
| resend "Send again" | sent → sent | send | | staff, sal/svc |
| to_production "Start production" | approved, paid → production | stage | checks `proofs_approved` | staff, svc |
| to_ordered "Work order issued" | paid, production → ordered | stage | | staff, svc |
| to_shipped "Shipped" | production, ordered → shipped | stage | asks `tracking`; checks `paid_in_full` | staff, svc |
| to_ready "Ready for pickup" | production, ordered → ready | stage | checks `paid_in_full` | staff, svc |
| to_complete "Complete" | shipped, ready → complete | stage | | staff, svc |
| approved "Client approves" | sent → approved | none | auto "on the client's order page" | |
| paid "Paid" | approved → paid | none | auto "when the payment comes in" | |
| refunded | paid…complete → refunded | none | auto "when a full refund is made on the order page" | |
| cancel "Cancel the order" | draft, sent, approved → cancelled | cancel | asks `note` | **manager**, sal/svc/fin |

- Any stage: "Open the order" → `/admin/backoffice/orders/{id}` (staff); "Refund, credit or chargeback" → `/admin/order.php?id={id}` (manager, fin/svc) (45-48).
- Adapter `info` (51-65): stage =
  - `void` → cancelled;
  - `refunded` → refunded;
  - draft or sent → itself;
  - a non-empty `orders.stage` in `ClientOrders::STAGES` → that stage;
  - paid or part_refunded → paid;
  - else → approved.
  `since` = `updated_at`; `owner_id` = `created_by`.
- Adapter `ops` (66-70):
  - `send` → `ClientOrders::send($id, DAYS, $uid)`
  - `stage` → `ClientOrders::setStage($id, $to, tracking, $uid)`
  - `cancel` → `ClientOrders::cancel($id, note, $uid)`
- `ClientOrders::STAGES` = production "In production", ordered "Work order issued", shipped "Shipped", ready "Ready for pickup", complete "Complete" (ClientOrders.php:43).
- `ClientOrders::setStage` (ClientOrders.php:664-677) itself checks only:
  - a client order;
  - a known stage;
  - status not draft, sent or void ("The client hasn't approved this order yet.").
  It then:
  - updates stage and tracking;
  - marks open lines done for shipped, ready or complete;
  - logs the event;
  - emails the client if `$mail && wantsProgress`.

Other order-adjacent workflows in the same file: `subscription` (111-144, tool `backoffice/plans`), `promotion` (147-172). No other order workflow; business-portal orders (BizQuotes) have no workflow.

### 3.3 Which paths go through Workflow, and which don't

**Through `Workflow::move`** (moves, Access, checks, `wf_items` and `wf_history`):
- `POST /admin/api/<module>/<tool>` with `act=move`, when the tool has a `workflow` (`sync-storage/admin/api.php:427-431`). For orders that's the `backoffice/orders` tool (the Back-Office → Orders pipeline / detail drawer).

**Reading only** (stage display): `Workflow::item` in tools.php:52 and :71; `ak_detail` api.php:43-58 (stage bar, moves, history, clock).

**Direct calls, bypassing Workflow** (no `wf_history`, no `who`/`checks`/`from` from the workflow):

| Caller | file:line | Call |
|---|---|---|
| Builder `do=stage` (no form posts it) | client-order.php:257 | `ClientOrders::setStage` |
| Builder `do=send` | client-order.php:97 | `ClientOrders::send` |
| Builder `do=test_open` | client-order.php:94 | `ClientOrders::send(...,false)` |
| Builder `do=cancel` | client-order.php:249 | `ClientOrders::cancel` |
| Builder `do=staff_approve` | client-order.php:103-104 | `ClientOrders::approve`, `releaseHold` |
| Builder `do=release_hold` | client-order.php:106 | `ClientOrders::releaseHold` (raw `UPDATE orders SET stage='production'`, ClientOrders.php:1760) |
| Builder `do=reopen` | client-order.php:100 | `ClientOrders::reopen` |
| Builder `do=record_pay` | client-order.php:185 | `ClientOrders::recordPayment` |
| Builder `do=complete` | client-order.php:252 | `ClientOrders::complete` → `setStage('complete')` (ClientOrders.php:701) |
| Builder `do=outside_wo` | client-order.php:111 | `WorkOrders::processOutside` → `ClientOrders::approve` (WorkOrders.php:309), `releaseHold` (312) |
| Builder `do=wo_send` | client-order.php:148 | `WorkOrders::markOrdered` → raw `UPDATE stage='production'` if empty, then `ClientOrders::setStage('ordered')` (WorkOrders.php:427-431) |
| `WorkOrders::create` with send + `ordered` ticked (builder `wo_create`, `outside_wo`) | WorkOrders.php:~420-421 | `markOrdered` |
| Work orders drawer act `send` | tools.php:196 | `WorkOrders::send`, `markOrdered` |
| Vendor's status on a work order | WorkOrders.php:677 | raw `UPDATE stage='production'` if empty, then `ClientOrders::setStage('ready'/'shipped')` |
| Pulse case `{do: stage}` | admin/api/pulse.php:86-87 → Pulse.php:645-651 | `ClientOrders::setStage` (needs a vendor for `ordered`), plus an optional vendor job |
| Pulse `manage` resend / pay / void | Pulse.php:706, 711, 724 | `ClientOrders::send`, `recordPayment`, `cancel` |
| The client's page | sync-storage/order.php:46 | `ClientOrders::approve` |
| Pulse step model | Pulse.php:614-643 (`orderSteps`) | Its own next-step logic: production → shipped/ready/complete, alt "Sent to the printer" (ordered). Not the workflow def. |

Because a built-in's stage is read from the order itself, direct changes still show in the pipeline. They don't write `wf_items` or `wf_history`, and they don't run the workflow's `proofs_approved` / `paid_in_full` checks or `who` rules.

---

## 4. Verbatim comments citing decisions or Jason

**sync-storage/admin/client-order.php**
- 2: `// admin/client-order.php — an order our team builds for a client (E-Commerce → Orders → New order, decisions/0101):`
- 34: `// its address: an order in Back-Office (decisions/0109); /admin/client-order.php forwards here`
- 82: `// proofs and item files come in through the uploader (api/upload.php, targets order_proof and order_file: decisions/0127)`
- 177: `// decisions/0128, step 3: the order takes its QuickBooks invoice's lines, tax and payments`
- 213: `// projects (decisions/0101, addendum 2026-10-27): a heading for an order's items, each with its own setup`
- 226: `// the order's business (decisions/0101, addendum 2026-10-28)`
- 239: `// decisions/0101, addendum 2026-10-28: this order's collateral, reordered with the same art`
- 490: `// its business (decisions/0101, addendum 2026-10-28): connected from the start when the listing is clear, a private one made when there's none`
- 526: `// the command strip (decisions/0129): what the order is and where it stands; one button for Save/Send/Close; ☰ for the rest`
- 626: `// the item panel (decisions/0107): status groups for the pill's colour, vendor jobs by item, each item's history`
- 638: `// a project's items: the client sees each one's price and the project's subtotal (decisions/0101, addendum 2026-10-27)`
- 760: `/* promo codes are made in Growth → Promotions and entered by the client at checkout; here we make one, not apply one (Jason, 2026-10-28) */`
- 767: `/* staff only (decisions/0106): our vendor cost against the client's price; never on the client's page */`
- 770: `/* profit from each item's COGS (its Cost tab), else its catalog cost; staff only (Jason, 2026-10-29) */`
- 866: `// the item panel (decisions/0107): one item at a time beside the order, its tabs from the row's buttons`
- 1073: `// a work order's fields (decisions/0101, addendum 2026-10-28): who makes it, their email, which items and files, where it ships, when, notes`
- 1105: `// the cost to start from: the ticked items' vendor share   // its COGS: checked before the work order goes (Jason, 2026-10-29)`
- 1559: `// the item panel (decisions/0107): opens beside the order (#item=<line>:<tab> after a save brings it back)`
- 1659-1660: `// Files on this order (proofs, artwork, reference, labels) go through the site's one uploader (js/tv-upload.js,` / `// decisions/0127): straight to S3, up to 1 GB, with the size checked first, a progress bar and the result beside the item.`

**sync-app/admin/workflows.php**
- 2-4: `// app/admin/workflows.php — the built-in workflows (decisions/0109 Part C): each one's default stages and moves (its` / `// first version, editable in System Configuration → Workflows) and its adapter, which reads where an item is from the` / `// site's own records and does the protected moves through the library that owns them.`
- 15: `// ---- Back-Office: an order our team prepares for a client (decisions/0101) ----`
- 74: `// ---- Sales & Growth: a lead (a listing worth a call; decisions/0081, 0108) ----`
- 110: `// ---- Back-Office: a plan (decisions/0108 step 3) ----`
- 146: `// ---- Sales & Growth: a promotion (decisions/0108 step 4) ----`

**sync-app/admin/Workflow.php**
- 2: `// app/admin/Workflow.php — the workflow engine (decisions/0109 A4). …`

**sync-app/admin/manifests.php** (order parts)
- 2: `// app/admin/manifests.php — the 11 modules' manifests (decisions/0109 A2; Master AOS §8; admin-architecture.md Part B).`
- 114: `// /admin/backoffice/orders/9: the order itself, inside Back-Office`
- 115: `// one catalog: what we sell, and the materials and services it's made of (decisions/0130)`
- 116: `// every printed item: in the queue, in production, shipped, delivered (decisions/0101)`
- 118: `// client businesses are listings: tagged or private, their contacts and orders (decisions/0101)`
- 119: `// who does what with the money; QuickBooks' items mapped to our products (decisions/0128)`
- 120: `// each client's pieces: see, release, reorder (decisions/0101)`
- 121: `// who makes our printed work; work orders go to them (decisions/0101)`

**sync-app/admin/tools.php** (order parts)
- 77-79: `// Order management (Jason, 2026-10-28, like his old dashboard): every printed item on an approved order, where it` / `// stands, who makes it and when it's due; In the queue / In production / Shipped / Delivered with their counts, and the` / `// completed ones in the archive (Completed, or found by searching).`
- 87: `// done: the archive (Jason, 2026-10-30), shown when asked for or searched`
- 123: `// Work orders (decisions/0101, addendum 2026-10-28): every one we've made, where it stands, for which order and client.`
- 125: `// each opens the work order itself (Jason, 2026-10-28)`
- 203-204: `// The Collateral Center (decisions/0101, addendum 2026-10-28): each client's configured pieces, from their approved` / `// orders, to see, release to the client, and reorder (the same art for the client to confirm, or with changes).`
- 263-264: `// Clients are listings (decisions/0101, addendum 2026-10-28): …`
- 323: `// it checks for listings first (Jason): a clear match is connected; possible ones are named; none: a private listing`
- 333: `// our orders not connected to a business yet, grouped to settle at once (decisions/0101, addendum 2026-10-28)`
- 473: `// ours only (decisions/0108); 'market': what the Marketplace's rules say, while the item's still being shaped (decisions/0142 §4)`
- 493: `// its recipe: what it's made of (decisions/0130)`
- 496-497: `// putting it online, here in its card (Jason, 2026-11-04: "another place to finish my work for putting items online. Make` / `// this seamless"): …`
- 527: `// its photos, here in its card (Jason, 2026-11-04: "what about photos?"): …`
- 853: `// ---- A business's quotes, invoices and orders (business portal only: BizQuotes, decisions/0118) ----`

**sync-app/admin/sections.php** (order parts)
- 27: `/** Dashboard → Quick actions: the everyday tools, each opened over the Dashboard (decisions/0109 A3a). */`
- 97: `/** System Configuration → Workflows: every workflow, and the builder (Managers and up; decisions/0109 A4). */`
- 285-286: `* Back-Office → Orders: sandbox (test) orders, folded away until wanted. They're numbered on their own (S-1, S-2…),` / `* stay out of the lists and money above, and can be deleted (decisions/0101, addendum 2026-10-28).`
- 721: `// their own link to all their work orders (decisions/0150): 30 days; sending a new one ends the old`
- 767: `* Back-Office → Cost list (Jason, 2026-10-29): paper at the best price on the market, …`
- 821: `* Back-Office → QuickBooks items (decisions/0128): who does what with the money …`
- 898: `* Back-Office → Orders: Pull an invoice from QuickBooks (decisions/0128, step 2). …`

**sync-storage/admin/router.php**
- 2: `// admin/router.php — the admin (decisions/0109): /admin/<module>/<section>[/<id>]. …`

**sync-storage/admin/ecommerce.php**
- 2: `// admin/ecommerce.php — E-Commerce, an area of the admin dashboard (decisions/0098, 0099): everything Traversence sells.`
- 6-7: `//             One list for both kinds (Jason, 2026-10-16): the site's checkout and the orders our team builds for a client` / `//             (admin/client-order.php, decisions/0101); those still being built or out for review are listed first.`
- 66: `// the title is the section that's open (Orders, Products…), with where it sits top right (Jason, 2026-10-16)`
- 168: `// ---- client orders (decisions/0101): built by our team, reviewed and approved by the client ----`

**sync-app/lib/ClientOrders.php** (seen in passing)
- 693: `* The order is done (Jason, 2026-10-30): its stage is Complete, every item that isn't cancelled is Complete, and it`

---

## 5. What I couldn't trace (not read, or no caller found)
- **ClientOrders internals**: `create`, `send`, `approve`, `reopen`, `cancel`, `recordPayment`, `addProduct` beyond the option fallback, `editLine`, `setShip`, `setItemStatus`, `refreshPrices`, `duplicate`, `deleteTest`, `retireProof`, `itemStatus`, `delivery`, `opens`, `promoPreview`. So what each does to status, QuickBooks, email and ledger isn't mapped here. The same goes for **WorkOrders** (`create`, `send`, `receive`, `choices`, `specs`, `specGaps`, `DELIVERY`, `STATUS`), **Shipments**, **Costing**, **Clients**, **Collateral**, **QbPull**, **VendorOrders**.
- **UI includes not read**: `app/ui/command-strip.php` (how `news`, `send`, `finish`, `more`, `new`, `skip` render and behave), `app/ui/cost-tab.php` beyond its two `line_cost` forms, `app/ui/pack-form.php` beyond line 7, `app/ui/qb-merge.php`, `js/tv-cost.js`, `js/tv-proof.js`, `js/admin-kit.js`, `app/admin/widgets.php` (`tv_w_item`, pipeline rendering).
- **Endpoints not read**: `/api/upload.php` targets `order_proof`, `order_file`, `order_final` (`app/lib/upload-targets.php`); `/admin/api/vendors.php`; `/admin/api/costs.php`; `/admin/order.php` (the money page); `/admin/manifest.php`; `/admin/products.php`; `/admin/vendors.php`; `/admin/quickbooks.php`; `/wo.php` (the vendor page); `/order.php` (the client page) beyond its `approve` call; `admin/api/pulse.php` `parse4` / `import4` (4OVER paste).
- **No caller found** in the mirror for these `do=` cases in client-order.php: `terms`, `line_save`, `addr_save`, `line_ship`, `options_save`, `stage`.
- **Live permission overrides**: Access rules can be changed in Permissions and are stored in the database, so the effective live rules are unknown.
- **The router's section check isn't applied to item pages**: router.php runs the item page before using `canSection`'s result. Whether `tv_admin_require` adds module- or department-level gating wasn't read.
- **Archive and redirect**: whether `/admin/ecommerce.php` still opens as a page or shows an archive banner (it's listed in `replaces`) wasn't checked (`Modules::archive`, `admin-hub.php`).
- **Business-portal internals**: `BizQuotes` (business/quotes, business/orders) wasn't read beyond the tool definitions.
- **Constants not read**: `Shipments::MODES` and `REASONS`, `ClientOrders::CLIENT_METHODS`, `FILE_KINDS`, `TAGS` and the rest of `ITEM_STATUS`, `Products::KINDS`, `Promotions::SERVICE_TYPES`, `QrIssue::FIELDS` and `PRICE_CENTS`.
