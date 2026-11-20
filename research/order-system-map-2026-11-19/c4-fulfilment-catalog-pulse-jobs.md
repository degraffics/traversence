# C4 — Order system map: work orders, vendors, 4OVER, catalog, collateral, shipping, Pulse, jobs

Source: live mirror `/home/user/traverse-3way-sync` (`sync-app/` = `app/`, `sync-storage/` = `website/`). Paths below
are given as `app/…` / `website/…`. Every assigned file was read in full; ClientOrders.php, Costing.php, admin/tools.php,
admin/client-order.php were read only where they call into these files (marked "partial").

---

## 1. Function inventory

Callers come from `grep -rn "Class::method("` across app/, website/, trvgo/ (own-file calls noted as "self").

### app/lib/WorkOrders.php (776 lines) — tables `order_work_orders`, `order_work_order_log`, `product_vendor_parts`, reads `order_proofs`, `order_files`, `order_lines`, `vendors`
Header (2-11): "work orders to vendors (decisions/0101, addendum 2026-10-28)… emailed from orders@trvgo.us (ORDERS_* in .env) with a private link… (/wo.php?w=…, 30 days from when it's sent; after that the page offers Request a new link, emailed to the vendor: decisions/0150)… No prices or client billing ever go to a vendor." processOutside: "approved for the client with no invoice made here (the QuickBooks invoice number is kept as a reference only…)… Staff only."

Constants: `DAYS=30` (18) "decisions/0150; 60 before 2026-11-09"; `DELIVERY` (21) "Jason's list, 2026-10-28": will_call, internal, blind_drop, local, courier, usps, ups, fedex, freight; `STATUS` (24); `VENDOR_MOVES` (27); `ITEM_FOR` (29); `FILE_PREF` (31); `NO_NEW_LINK` (121) = draft, cancelled, declined, rejected; `RANK` (622).

- `statusReady (33)` — probes `status, cost_cents, parts` + `order_work_order_log`. Callers: admin/tools.php:98,135; Pulse.php:142; scripts/work_orders_tick.php:13; website/admin/client-order.php:147,1074; website/vendor.php:59.
- `nextNo (44)` — `YYYYMMDD-<code>####`, running count across all WOs; `WO_START_NO` env. Comment: "Jason: YYYYMMDD-WO####, then the vendor's code where WO was: 20261027-CP0001". Callers: self 401, 706.
- `recode (54)` — swaps vendor code, keeps date+count. Self 397.
- `get (59)` — Callers: tools.php:154,194; Pulse.php:854; client-order.php:136,147,150,154,157,168,172,1272; website/api/admin/client-order.php:35.
- `log (65)` — rows of `order_work_order_log`. tools.php:158; client-order.php:1227.
- `dueAt (73)` — due_on + due_time, "5 pm when no time is given". tools.php:110,145,156; Pulse.php:148,585; client-order.php:1220; vendor.php:110; wo.php:82.
- `partFor (80)` — [product SKU, vendor part # from `product_vendor_parts`]. client-order.php:1110; self 383.
- `ready (91)` — `order_work_orders` exists. client-order.php:941,1074.
- `forOrder (98)` — ClientOrders.php:547,581,685; Pulse.php:585; client-order.php:1216.
- `byToken (105)` — token match AND `expires_at > NOW()`. wo.php:16.
- `byTokenAny (113)` — expired too. wo.php:18.
- `renewLink (124)` — new 40-hex token, +30 days; optional email first; throws for NO_NEW_LINK statuses. wo.php:34; self 145.
- `urlForVendor (143)` — renews (no email) if expired. vendor.php:32.
- `url (149)` — `SITE_BASE/wo.php?w=<token>`.
- `vendors (155)` — Vendors list, else distinct past WO vendors + VendorOrders::vendors.
- `printLines (167)` — excludes product `shipping`, `go_code`; status `cancelled`, `refunded`. client-order.php:1074.
- `choices (177)` — per-line files for a WO: finals ("Print-ready", ticked), proofs (ticked only if no print-ready/setup/print), order_files (setup/print ticked; artwork ticked if not ready); line 0 = order-wide docs; key -1 = labels and manifests (always ticked). Excludes retired, `label/manifest/invoice/estimate` from line lists. client-order.php:1076,1239.
- `shipText (200)` — order Ship-to as label text. client-order.php:1091.
- `itemText (208)` — vendor's line text, "never a price". tools.php:173.
- `parts (218)` — decodes `parts` JSON `[line id => [code, vendor pn, specs]]`. tools.php:156; client-order.php:1079; wo.php:81.
- `lastSpecs (225)` — newest specs for a line on this order; else for reorders: "Jason, 2026-10-30: 'use previous specs'" via `order_lines.collateral_id`. client-order.php:1115.
- `specs (243)` — written specs, else `options` + "Note: …". client-order.php:1115; wo.php:180.
- `specGaps (254)` — compares Costing parts/binding names' sizes against specs. Comment: "Jason, 2026-10-29: the order said a 14mm coil, the costing a 10mm one". client-order.php:1115.
- `items (270)` — lines whose id is in `line_ids`. tools.php:157; wo.php:78.
- `files (277)` — WO's chosen keys resolved; finals ride along even if replaced. tools.php:157; wo.php:79.
- `file (288)` — key `p<id>`/`f<id>` → [path, name, mime]. wo.php:45.
- `processOutside (301)` — sets `pay_mode='outside'`, `ClientOrders::approve`, `setInvoiceNo`, `releaseHold` if stage '', event `outside`. client-order.php:111.
- `setInvoiceNo (318)` — `orders.invoice_no`, refuses if `invoice_id` set. client-order.php:175.
- `create (331)` — see §2. client-order.php:114,131.
- `markOrdered (427)` — stage '' → production, then `ClientOrders::setStage(...,'ordered',..., 'to vendor (X)')`. tools.php:196; client-order.php:148; self 421, 462.
- `setTracking (435)` — courier booked "(Jason, 2026-10-28)"; emails vendor if sent. tools.php:197.
- `handLink (455)` — "Jason, 2026-10-28: 'a send link, in case I just want to email the rep'"; draft→sent. tools.php:195; client-order.php:137; website/api/admin/client-order.php:37.
- `linkFor (470)` — [url, mailto]. client-order.php:1231.
- `send (481)` — email; draft→sent. tools.php:196; client-order.php:148; self 420.
- `setFiles (519)` — "Jason, 2026-10-30: new files for the vendor after it went out". client-order.php:169.
- `opened (551)` — `opened_at`, `opens+1`. wo.php:77.
- `note (558, private)`, `set (564, private)`, `tellUs (572, private)` — log row; update + `status_at`; email to `ORDERS_REPLY_TO` / `SMTP_REPLY_TO` / support@ with link `/admin/backoffice/orders/<id>#work`.
- `hasAcceptedBy (585)` — column probe.
- `vendorMove (587)` — wo.php:66.
- `staffMoves (625)` — Pulse.php:586.
- `staffMove (637)` — "Jason, 2026-11-01: the vendor is working on it but never clicked Accept". Pulse.php:857.
- `moveItems (661, private)` — §2.
- `receive (682)` — client-order.php:151.
- `reprint (702)` — client-order.php:173.
- `deleteDraft (718)` — client-order.php:155.
- `cancel (726)` — tools.php:198; client-order.php:158.
- `nextBusinessNine (740)` — ClientOrders.php:1686; Pulse.php:152.
- `tick (751)` — scripts/work_orders_tick.php:14.

### app/lib/VendorOrders.php (388) — tables `vendor_orders`, `vendor_jobs` (via `Database::t`), reads `orders`, `order_lines`
Header: "vendor orders and their jobs (decisions/0106), the way 4OVER publishes them… Staff only: a client never sees a vendor, a vendor status or a cost… Margin… shows to staff." `KNOWN = ['4OVER','Signs365'] (11)`.
- `ready (13)` — Pulse.php:582,1528,1538; api/pulse.php:65; client-order.php:768,871,936; admin/vendors.php:18.
- `cents (20)` — strips non-digits, ×100. admin/vendors.php:32,96,98.
- `vendors (27)` — top 20 used + KNOWN. Pulse.php:1539; WorkOrders.php:162; admin/vendors.php:80,123.
- `recent (35)` — Pulse.php:1540.
- `findOrCreate (41)` — by (vendor, vendor_order_no); new row purchased_on defaults today.
- `date (54, private)`.
- `addJob (57)` — insert job, event `vendor` "Sent to …", `syncItems`. Pulse.php:1529; admin/vendors.php:44.
- `updateJob (73)` — only given fields among po_name, set_name, job_no, job_status, tracking, completion_on, facility, note + money; recomputes total; event; `syncItems`. api/pulse.php:74; admin/vendors.php:26.
- `setLine (93)` — then re-syncs every job on the order. api/pulse.php:75; client-order.php:88.
- `fields (103, private)`, `total (116, private)`.
- `job (123)` — api/pulse.php:72; client-order.php:86.
- `label (130)` — "4OVER #017633948 · … · $22.50". Pulse.php:1530; client-order.php:947.
- `items (140)` — Pulse.php:1541.
- `forOrder (147)` — Pulse.php:588,1539; client-order.php:628.
- `margin (156)` — client `subtotal_cents` vs sum of job `total_cents`. Pulse.php:1541; client-order.php:768.
- `tracking (166)` — newest job tracking. Pulse.php:582.
- `listing (173)` — tools.php:641; admin/vendors.php:51.
- `parse4over (198)`, `match4over (268)`, `import4over (298)` — §3. Callers: api/pulse.php:61-66; admin/vendors.php:36,39.
- `itemStatusFor (326)` — "(decisions/0107)". client-order.php:946.
- `syncItems (344)` — self 68, 89, 100.
- `lineFor (370)` — self 317.

### app/lib/Vendors.php (297) — tables `vendors`, `order_lines.vendor_id`, `vendor_link_requests`, reads `order_work_orders`
Header: "Back-Office → Vendors; decisions/0101, addendum 2026-10-28… Staff only."
- `ready (9)` — sections.php:706; AccountDomains.php:604; WorkOrders.php:158,346,412; client-order.php:938,1082.
- `hasCode (16)` — sections.php:711. `codeFor (19)` — set code, else initials (1 word: first 2 letters; else first letters up to 4), else "WO". sections.php:711; WorkOrders 397,401,706.
- `hasPref (29)` — sections.php:714. `all (32)` — sections.php:733,736; WorkOrders 158; client-order.php:940,1076. `get (38)` — tools.php:232; WorkOrders 341,346,491,706; api/vendors.php:20. `byName (45)` — AccountDomains.php:605; WorkOrders 345.
- `save (53)` — name/email/contact/phone/website/account_no/notes/active, file_pref, code. AccountDomains.php:606; WorkOrders 346; api/vendors.php:29. `setCode (81, private)`.
- `setForLine (91)` — `order_lines.vendor_id`, event `line_vendor`. client-order.php:127.
- `forLine (105)` — line vendor, else catalog product's `vendor` text matched by name. tools.php:106; client-order.php:938,1077.
- `LINK_DAYS=30 (119)`, `REQUESTS_A_DAY=3 (121)`, `HELP_A_DAY=40 (200)`.
- `linkReady (123)` — sections.php:722. `linkUrl (130)`. `byLink (136)` — vendor.php:18. `link (147)` — WorkOrders 491. `linkEnds (160)` — sections.php:725.
- `emailLink (168)` — token switched "only once it has gone". api/vendors.php:25; vendor.php:46.
- `mayRequest (184)` — vendor.php:44; wo.php:33. `logRequest (191)` — vendor.php:44; wo.php:33,35.
- Section comment (197): "never a dead end (Jason, 2026-11-09)".
- `helpTo (203)`, `helpRoom (208, private)`, `askByEmail (219)`, `askTeam (241)`, `tellTeam (250, private)` (mail to helpTo with link `/admin/backoffice/vendor_list`), `helpBox (260)` — vendor.php:64; wo.php:148,151,154,157. `HELP_CSS (272)`. `handleHelp (276)` — vendor.php:24; wo.php:24.
- `workOrders (289)` — "open ones first (due soonest), then those finished in the last 90 days. Never drafts." vendor.php:59.

### app/lib/Shipments.php (221) — tables `order_addresses`, `order_lots`, `order_packs`, `order_pack_items`, `orders.ship_mode`, `orders.no_ship_reason`, `order_files` (label)
Header: "shipping as manifests (decisions/0101, Jason 2026-10-17; 2026-12-12_shipping_manifests.sql)… An order is the master manifest… GO Codes aren't shipped."
- `MODES (15)`, `REASONS (16)`; `ready (18)` — ClientOrders.php:1416; client-order.php:588; manifest.php:16; order.php:180.
- `mode (25)` — client-order.php:588,842,863; order.php:180. `shipped (27)` — ClientOrders.php:1416; client-order.php:804. `order (29, private)`.
- `itemQty (37)` — client-order.php:864. `shippable (44)` — client-order.php:864.
- `setMode (49)` — client-order.php:193. `main (65)`. `destinations (74)` — ClientOrders.php:1417; client-order.php:596,806; manifest.php:18; order.php:182.
- `saveDest (86)` — client-order.php:194. `deleteDest (112)` — client-order.php:195. `ensureLots (126)` — client-order.php:805. `lots (135)` — client-order.php:806; manifest.php:18; order.php:182. `saveLots (145)` — client-order.php:198. `packs (171)` — client-order.php:806; manifest.php:18. `savePack (180)` — client-order.php:199. `deletePack (207)` — client-order.php:200. `estimateCents (216)` — ClientOrders.php:2153.

### app/lib/Collateral.php (234) — tables `collateral`, `order_lines.collateral_id`, `order_proofs.reuse_of`, copies into `order_proofs`, `order_files`, `order_lines`
Header: "the Collateral Center (decisions/0101, addendum 2026-10-28)… Clients see only items released to them (Jason: 'only the approved, and allowed for USE released'), on their Your orders page."
- `FILE_KINDS (17)` = setup, print, artwork, native, reference. `ready (19)` — tools.php:211; RecordFiles.php:77; client-order.php:521. `get (26)` — tools.php:231,250; user/orders.php:29.
- `nameOf (34, private)`, `hasArt (37, private)`.
- `fromOrder (50)` — ClientOrders.php:1017; QbPull.php:174,326. Comment: "$artLater: an order pulled from QuickBooks (decisions/0128)".
- `backfill (81)` — tools.php:212. `files (93)` — tools.php:222,234; RecordFiles.php:70,90; user/orders.php:81. `forClient (117)` — RecordFiles.php:86; user/orders.php:21. `edit (126)` — tools.php:256-258. `askReorder (141)` — order.php:65; user/orders.php:30. `onOrder (158)` — order.php:62,244. `copyFile (167, private)`. `reorder (183)` — tools.php:253; client-order.php:242.

### app/lib/Products.php (243) — table `products` via `Scope::t` (business scope switches table/PDO), `orders.pay_mode` probe
Header (2-8): "the catalog (decisions/0101)… Priced the way a print vendor quotes… Stored: tiers = {"markup": 100, "rows": [{qty, cost, price}]} (older: a plain list); options = [{name, choices: [{label, add, pct}]}] (older: choices as plain strings)."
- `KINDS (15)` print/service/other. `kinds (20)` — business: ItemKinds (decisions/0141 §5). tools.php:482,519,539,625.
- `ready (27)` — sections.php:266; client-order.php:37; ecommerce.php:82,169; products.php:16; api/wire-card.php:11; order.php:25; user/orders.php:16.
- `all (35)` — sections.php:266,865,929; tools.php:478,730,875; business/marketplace.php:36; Costing.php:264; QbPull.php:48; ui/qb-merge.php:29; client-order.php:499; products.php:32.
- `delete (44)` — tools.php:597. `get (49)` — ~30 callers (ClientOrders.php:234,253,349,956,1260,1302,2017,2037; WorkOrders 85; Vendors 110; Costing 109; BizQuotes 161; Books 196; MarketSquare 172,178; upload-targets 189; tools.php many; client-order.php:712,733,1293; sections.php:272; business/marketplace.php:26).
- `shape (57, private)` — decode tiers/options; tiers sorted by qty.
- `save (73)` — tools.php:629; Books.php:210; MarketSquare.php:132; client-order.php:210; products.php:21. `saveType` closure (91): service_type in '', creative, additional, print "(decisions/0108)".
- `update (112)` — tools.php:620; Costing.php:111; MarketSquare.php:177. `setKind (133)` — tools.php:603. `setKindValue (147)` — upload-targets.php:195.
- `runsText (156)` — tools.php:543. `money (161, private)`. `parseRows (169)`. `parseOptions (185)`. `choiceText (202)`. `optionsText (207)` — tools.php:525,545; products.php:57.
- `pickedFrom (213)` — ClientOrders.php:237,351,1305,2038; client-order.php:712.
- `priceFor (225)` — sections.php:274; BizQuotes.php:163; ClientOrders.php:237,262,351,1313,2026; client-order.php:712.

### app/lib/PriceLists.php (131) — tables `price_lists`, `price_list_rules` (created by `AdminTables::make`), reads `crm_tags`, `crm_customer_tags`, `crm_customers`
Header: "price lists for groups of customers (B2B; business-portal-architecture.md, 'Admin first')… A customer gets the lowest price any of their lists gives. owner_id 0 is Traversence."
- `ensure (13)` — tools.php:752. `all (25)` — tools.php:724. `get (34)` — tools.php:729. `save (46)` — tools.php:746,754. `addRule (66)` — tools.php:747. `removeRule (79)` — tools.php:748. `priceFor (90)` — sections.php:275 only (the "What would a customer pay?" test, `tv_admin_mod_pricetest`). `money (122)` — sections.php:277; tools.php:733. `pct (125, private)` 0–90.

### website/wo.php (210) — vendor's work-order page
Header: "/wo.php?w=… … works for 30 days from when it's sent; an expired one offers Request a new link, emailed to the vendor's address on the work order, never shown here (decisions/0150). Never a dead end… No prices, no client billing. Printable." (Note: the page does show "Total cost" = `cost_cents`, 173.)
- 16-19 token lookup / expired lookup; 23-26 help-box POST; 29-42 Request a new link (CSRF, `mayRequest('wo')`, `renewLink(..., true)`, `logRequest`); 44-54 file download (`Store::redirect` for S3, else readfile); 58 `$preview = QuickBooksPay::staffHere()` (staff preview: not counted as opened, buttons disabled, 209); 62-72 vendor POST → `vendorMove`; 76 HTTP 410 for expired, 404 unknown; 77 `opened`; 126 print CSS "(Jason, 2026-10-28)" one sheet; 190 draft preview shows the `sent` buttons; 193-204 forms: Accept (accepted_by, job_no), Reject (why), In production, Shipped (carrier select USPS/UPS/FedEx/Courier/Freight/Other preselected from delivery, tracking), Ready, Complete.

### website/vendor.php (121) — vendor's own page of all work orders (decisions/0150)
- 18 `byLink`; 23-26 help POST; 29-37 `&wo=<id>` redirect to `urlForVendor` (non-draft, own vendor); 40-53 expired → Request new link (`mayRequest('vendor')`, inactive vendor refused, `emailLink(..., null)`); 58 404/410; 59 `Vendors::workOrders`; 106-117 groups "Open" (sent, accepted, production, shipped, ready) and "Finished in the last 90 days".

### website/admin/vendors.php (162) — Back-Office → Vendor orders (decisions/0106)
- POST `do`: `job` (26, updateJob with job_status, tracking, completion_on, job_no, subtotal, shipping, tax), `order` (28-33, direct UPDATE of vendor_orders no/purchased_on/payment/total/note), `parse4` (34-37), `import4` (38-40), `new` (41-45 addJob). Filters vendor / q / "Not complete" (78-84). Paste-from-4OVER block (86-105). "+ Add a vendor job" form defaults vendor "4OVER" (110). Table 4OVER-layout (127-159) with links to `/admin/backoffice/orders/<id>`, `/admin/pulse/stream#case=order%3A<id>`, item `#item=<line>:vendor`.

### website/admin/manifest.php (76) — printable shipping papers (§6).

### Order upload targets (app/lib/upload-targets.php) and routing (app/lib/Uploads.php)
- `$tvOrderStaff` (15-19): `Access::can('backoffice/orders.edit', ['min' => 'staff', 'depts' => ['fin','svc','sal']])`.
- `order_proof` (21-33): check `ClientOrders::canAddFiles($pdo, $oid, true, line)`, types `PROOF_TYPES`, sniff, owner `orders/<oid>`; done → `replaceProofFile` when ctx.replace, else `proofFromUpload(order, line, u, uid, batch)`.
- `order_file` (35-48): `canAddFiles(..., false, line)`; ctx.kind `label` → `FILE_TYPES` "a shipping label", else `ALL_TYPES`; done → `fileFromUpload(order, line, u, uid, kind)` (default kind `artwork`), returns `['file' => id]` (that id is what `Shipments::savePack` takes as `label_id`, Shipments.php:201-202 comment "a label sent first through the uploader (decisions/0127)").
- `order_final` (418-431): comment "A creative item's print-ready file on a client order (no print in its project; decisions/0101, addendum 2026-10-27): added after the client approves the artwork, released to them once the order is paid in full." types `ART_TYPES`; done → `finalFromUpload`.
- (Also present, business-side, not client orders: `catalog_photo` 147, catalog digital file 180-196 → `Products::setKindValue`, business quotes 201-225 → BizFiles.)
- Uploads.php routing: `target()` (89) registers; `def()` (91) loads upload-targets.php; `start()` (140) runs `plan()` (check → size/type), stages at `Store::path(kind, ref, 'incoming', …)`; S3 multipart if `Store::on()`, else pieces; `finish()` (229) idempotent via PageCache key, verifies size, re-runs `plan()`, sniffs mime when `sniff`, calls `done`, removes stage if not moved.

### Pulse (app/lib/Pulse.php 1588, app/ui/pulse-ui.php 1422, website/admin/api/pulse.php 131) — order parts: §7.

### app/lib/Scheduler.php (283) and scripts — §8.

### website/shop/index.php (116) — the Traversence Shop (decisions/0154 §6)
Not part of the client-order (print) flow: plans, storage/AI packs, GO Codes (`/qr/`), domains (`/shop/domains.php`, decisions/0169 §1a), White Glove, and "Design, video, print and signs" = `mailto:support@…?subject=A quote for <biz>` ("Quoted", 68). Cart actions via `Go.go({action:'plan_add'…})` / `pro_month_add` then `/qr/cart.php` (108-114). No Products/ClientOrders calls.

---

## 2. Work orders

### Statuses (WorkOrders.php:24)
draft "Not sent", sent "Sent to vendor", accepted, declined "Rejected by vendor", production "In production", shipped, ready "Ready for pickup", complete, received, rejected "Rejected on receipt", cancelled.
Columns used: wo_no, vendor, vendor_email, vendor_id, line_ids (CSV), files (JSON keys), ship_to, due_on, due_time, note, token, expires_at, created_by, status, status_at, delivery, po_no, project, cost_cents, cost_state ('' / ours / accepted), parts (JSON), sent_at, opened_at, opens, job_no, accepted_by, promised_on, vendor_note, carrier, tracking, received_by, received_on, receive_note, reminders (CSV), reprint_of.

### Every move
| Move | Who / where | Code |
|---|---|---|
| create → draft (or save a draft again) | staff: client-order.php `wo_create` (127-133), `outside_wo` (107-117) | create 331-424. Requires order status pending/paid/part_refunded (335). Vendor by id, or typed name → `Vendors::byName` or new `Vendors::save` (342-348); typed email back-filled to vendor if blank (350). Sending needs email (352) and, once statusReady, a cost (353). No cost typed → sum of `Costing::forOrder` `vendor_cents` for ticked lines (374-378, "one system: decisions/0130"). Part # typed is saved to `product_vendor_parts` (385-386). Specs kept only if different from `specs($l)` (388). Edits only while draft (366); vendor change recodes number (397-398). Log "Made"/"Saved" (417); order event `work_order` (418). |
| draft → sent (email) | staff: `wo_create` save=send, `wo_send` (140-148), tools.php act `send` (196); create() with $send | send 481-512: requires email + cost (483-484); extends expires to ≥ now+30d (488); email (492-504) incl. vendor's all-orders link (`Vendors::link`, 490-491); sets `sent_at`; draft→sent + log (509); order event `work_order_sent` (510). Callers then `markOrdered`. |
| draft → sent (link handed by staff) | client-order.php `wo_link` (134-139); tools.php act `link` (195) | handLink 455-467: cost required; status sent, sent_at, expires ≥ +30d; log "Its link given to the vendor by our team"; `markOrdered`. |
| sent → accepted / declined | vendor via wo.php | vendorMove 587-619: accepted needs `accepted_by` (596), sets job_no, promised_on = due_on, cost_state=accepted ("Jason, 2026-10-28: accept or decline, no counter-offers"; "no ship date asked"); declined needs `why` → vendor_note. |
| accepted → production/shipped/ready; production → shipped/ready; shipped/ready → complete | vendor via wo.php (VENDOR_MOVES 27) | shipped needs tracking unless delivery in will_call/internal/local/courier (608). Each: set, log (by_vendor=1), order event `work_order_<to>`, `moveItems`, `tellUs` email. |
| forward any steps (sent→…complete) | staff via Pulse step form `wo_move:<id>` (Pulse.php:852-860, 1165-1167) | staffMove 637-658: forward only (`staffMoves` 625: ranks > current, from rank ≥1); if never accepted, sets accepted_by "For the vendor, by <name>", promised_on, cost_state=accepted; tracking optional; note "(marked for the vendor by X)"; `moveItems`. No `tellUs`. |
| → received / rejected | staff: client-order.php `wo_receive` (149-152) | receive 682-699: from shipped/ready/complete/accepted/production; needs by, date, note if rejected; emails vendor on rejection. |
| rejected/declined/cancelled → new draft | staff `wo_reprint` (170-173) | reprint 702-714: copies row, new number, `reprint_of`. |
| any (not received/cancelled) → cancelled | staff `wo_cancel` (156-158), tools.php act `cancel` (198) | cancel 726-737: emails vendor if it was sent. |
| delete draft | staff `wo_delete` (153-155) | deleteDraft 718-724: only never-sent; deletes log + row; event `work_order_deleted`. |
| files changed after send | staff `wo_files` (166-169) | setFiles 519-548: refused on cancelled/declined/rejected/received; at least one file; optional vendor email "New files for work order …". |
| courier tracking | staff tools.php act `tracking` (197) | setTracking 435-448; emails vendor if sent. |
| reminders | cron hourly | tick 751-775 (§8). |

### Emails (all `Mailer::send(..., 'ORDERS')`)
- To vendor: work order (506, subject "Work order <no> from Traversence"; body ends "Bill Traversence; never contact our client directly."), new link (133), courier booked (444), files changed (544), rejected on receipt (695), cancelled (734), reminders (766-767).
- To vendor (from Vendors): all-work-orders link "Your Traversence work orders" (Vendors.php:177).
- To us: `tellUs` on every vendor move (572-579) → `ORDERS_REPLY_TO` else `SMTP_REPLY_TO` else support@ ("orders@ only sends: our team reads support@").
- Vendor help/ask-team → `Vendors::helpTo()` (Vendors.php:250-257).

### Links and expiry
- Per WO: token 40 hex, `expires_at` = created + 30 days; send/handLink push to ≥ now+30 (461, 488). `byToken` refuses expired. Expired page: Request a new link, max `REQUESTS_A_DAY=3` per WO per day (non-staff requests), logged in `vendor_link_requests` kind `wo`. Closed statuses (NO_NEW_LINK) get no new link (126; wo.php:150). Opening a WO from vendor.php renews an expired token silently (143-146).
- Per vendor: `vendors.portal_token`/`portal_expires_at`, 30 days (`LINK_DAYS`); `link()` reuses a live one or makes a new one (147-157); `emailLink` makes a fresh one after the email goes. Request new link: 3/day kind `vendor`. Help box: "Send a new link to my email" (`askByEmail`, same answer whether or not the address is known, 222) and "Ask our team"; overall 40/day (`HELP_A_DAY`) across kinds `ask`,`team`.

### How work orders move the order stage and item statuses
- `markOrdered` (427-432): only if order stage is '' or production: '' → `production`, then `ClientOrders::setStage(oid,'ordered', tracking, staff, 'to vendor (X)')`. Comment: "'Work order issued to vendor (Competition Printing)'; the client hears only 'sent to print'". Called on send / handLink / tools act send.
- `moveItems` (661-679): `ITEM_FOR` production→production, shipped→shipped, ready→ready, complete→delivered. Skips `delivered` when delivery is will_call/internal/local ("it came to us: we take it from here", 665). Rank = ITEM_STATUS 'Approved' + 'Out the door' in order (666); item moves only if current is in that rank set and lower ("forward only, never out of a hold or past what we set by hand", 660). Uses `ClientOrders::setItemStatus(..., staff 0)` with carrier+tracking when shipped.
- Order stage follow (674-678): when WO status is shipped/ready and order stage in '', production, ordered: if every printed line with `ClientOrders::role == 'print'` is in shipped/ready/in_route/delivered/complete → stage set to `ready` (WO ready) or `shipped` via `ClientOrders::setStage(..., 0)`.
- Order completion is not set by work orders; `ClientOrders::openBeforeComplete` (ClientOrders.php:680-686, partial) lists "N work order(s) not received yet" (counts statuses not in received/cancelled/complete/declined/rejected).
- Retiring a proof/file that is on a live WO is refused (ClientOrders.php:547-550, 581-585).

---

## 3. Vendor orders / 4OVER paste

### Model (decisions/0106)
`vendor_orders` (vendor, vendor_order_no, purchased_on, payment, subtotal/shipping/total_cents, note, created_by) holds `vendor_jobs` (vendor_order_id, order_id, line_id nullable, po_name, set_name, job_no, job_status, tracking, sku, specs, run_size, turnaround, facility, completion_on, ship_to, ship_method, artwork_url (http only), subtotal/shipping/tax/total_cents, note, created_by). `vendor_order_no` prefixed `old-` is hidden as "—" (VendorOrders.php:134; admin/vendors.php:134; Pulse.php:1540).

### Parse (`parse4over`, 198-265)
- Lines trimmed, blanks dropped. Must contain `Order # <5+ digits>` else error "That doesn't look like a 4OVER order page…".
- `Purchased: m/d/yy(yy)` → Y-m-d. Payment: "Credit Card Type X" + last 4 of "Credit Card Number" → "MasterCard ••1852".
- Order totals: from the line "Order Totals" + 8 lines: Subtotal, "Shipping & Processing", "Grand Total" (`$` amount on the label's line or the next).
- Projects: each starts at a line beginning "Project/P.O. #:"; ends at next or at Order Totals. Spec lines `SKU|Size|Shape|Stock|Colorspec|Coating|SPOT UV SIDES|Run Size|Turnaround Time:`; specs joined " · " (coating gets " coating"); SKU spaces removed.
- Project Cost block (9 lines): Subtotal, Shipping & Processing, Tax, Grand Total.
- Ship to: lines between "Ship to" and "Shipping method", minus "United States" and "T:" lines; ship_name = first; ship_company = second unless it starts with a digit.
- "Shipping method", "Production Facility", "Turnaround Completion Date" = next line. Completion date: weekday stripped, purchase year added; if > 30 days before purchase, +1 year ("ordered in December, done in January", 242).
- Sets: each line matching `^J\d{6,}-\d+$` is a Job ID; set_name = previous line; following lines until next Job ID: "Track Shipment" → next line is tracking; "View" skipped; a `$x.xx` line = set cost (stops); other lines joined as job_status. No sets → one empty set with cost = project total.
- No projects → error "Copy the order's own page… not the order list."

### Match (`match4over`, 268-291)
- Candidate orders: up to 200 newest `orders` with `created_by > 0` and status not void/draft.
- Per project: if any set's Job ID already exists in `vendor_jobs` → that order (`known`). Else score each order: ship_name equals normalized ship_name or bill_name +3; ship_company = client_company +2; client_company contained in po_name +1. Picks best; requires score ≥ 2, else 0. Returns order labels "YYYY-MMDD-00042 · company".

### Import (`import4over`, 298-321)
- `findOrCreate('4OVER', no)`, then UPDATE purchased_on (COALESCE), payment if non-empty, subtotal/shipping/total.
- For each set: fields from project + set. When the project has more than one set (`$multi`), per-set subtotal/shipping/tax are blank and total = set cost; otherwise project subtotal/shipping/tax and total = project total (or set cost).
- Existing Job ID → `updateJob` (counts updated). Else `orderFor[project index]`; 0 → skipped; else `addJob` with `vendor_order_id` and `line_id = lineFor(...)`.
- Server re-parses the pasted text on import; the browser's parse is not trusted (api/pulse.php:63 "the page is read again here, never trusted from the browser"; admin/vendors.php:39 same).
- `lineFor` (370-387): per non-shipping/GO/cancelled line, haystack = title+note+ship_address+ship_to; +2 first name of ship_name as a word, +2 full ship_name contained, +2 street number (3rd comma part's first token) as a word; needs ≥2 and no tie.

### Item sync rules (`syncItems`, 344-367, decisions/0107)
Quote (338-342): "Moves our items on from a vendor job: a job tied to an item moves that item; a job on the order (no item) moves every printed item that hasn't a job of its own (Jason: the invoice's vendor unless the item says otherwise). Only forward, never past what our team set by hand, never out of a hold or housekeeping status. The client isn't emailed; our team sends updates."
- Status words (`itemStatusFor`, 326-336): contains "deliver" → delivered; "pickup"/"pick up"/"ready for" → ready; "ship" or any tracking → shipped; cut|finish|pack|bindery|trim → finishing; print|press|imposition|prepress|processing|production|plate|queue → production; else nothing.
- Rank = ITEM_STATUS groups 'Before approval', 'Approved', 'Out the door' in order (354); only clients' orders with items; tracking passed for shipped/delivered.
- Run on addJob, updateJob, and for all jobs on setLine (oldest first via array_reverse).
- Vendor jobs never move the order stage (only items). The Pulse "Sent to the printer" stage (`ordered`) can add a job (Pulse::stage → vendorJob, 645-656).

---

## 4. Products / catalog

### Record (Products.php)
Columns written by `save` (86-101): sku (uppercased, non-alnum→'-', from name if blank, ≤40, unique), name ≤120, kind (print/service/other, or business ItemKinds), description ≤600, options JSON, tiers JSON `{"markup": N, "rows": [...]}`, taxable 0/1, vendor (free text ≤120), turnaround (free text ≤80), status active/hidden; `service_type` ('', creative, additional, print) set separately "(decisions/0108)" when the column exists. Business scope adds `kind_data` (ItemKinds).
`shape` (57-67) adds `markup` (0 for old plain-list tiers), sorts tiers by qty, normalizes options to `{label, add (cents), pct}`.

### Options format
Text, one option per line: `Name: Choice, Choice +10%, Choice +5.00` (`parseOptions` 185-200; error text "Write each option like "Paper: Matte, Glossy"…"). `+N%` → `pct` (share of the run price); `+N.NN` → `add` cents (a vendor cost, marked up). Name ≤40, label ≤60. Shown back by `optionsText`/`choiceText`.
On an order line the picked choices are stored as text `"Paper: Matte · Turnaround: 2 Business Days"` in `order_lines.options` (ClientOrders.php:2035-2036 etc.). `pickedFrom` (213-218) reads them back; a missing option falls to `choices[0]`.

### Tiers / markup
`parseRows` (169-183): per row qty>0 required; cost or price required; empty price = `round(cost × (100+markup)/100)`; keyed by qty (duplicates collapse), sorted. Markup clamped 0–1000 (82). Drawer `update` takes runs as lines "250 18.04 36.08" (122-128).
Costing recipes push tiers into the product: `Costing::saveRecipe` (Costing.php:100-111, partial) — "one system: the product's run sizes in the catalog take the recipe's tiers (each run's COGS as its cost, the tier price as its price)" via `Products::update(... 'runs' ...)`.

### priceFor rules (225-242)
- qty = max(1, qty). Tier = the largest tier with `qty ≤ requested`; none → first tier (or zeros).
- Factor = 1 if exact tier, else `qty / tier.qty` (pro-rated linearly from the tier below; for qty below the smallest tier, pro-rated down from the smallest).
- For each option, the picked label (or `choices[0]` when not given): `pct` adds `basePrice × pct/100` to price and `baseCost × pct/100` to cost; else `add` adds `add` to cost and `add × (100+markup)/100` to price.
- Returns `unit` and `total` both = rounded price (same value), `cost` rounded.
- Admin JS preview (client-order.php:1615-1622) mirrors this for the hint line and shows `turnaround`.

### Turnaround
`products.turnaround` is free text; shown in admin catalog (products.php:60,86), product drawer (tools.php:541), and the add-item hint on the order (client-order.php:1601,1622). Turnaround as a priced choice is only possible as an Option ("Turnaround: 4 Business Days, 2 Business Days +10%", header line 5). 4OVER jobs carry their own `turnaround` text (VendorOrders).

### How options are offered for selection (default choice)
- Add item to an order (client-order.php:992-1000, JS 1607): one `<select name="opt[i]">` per option, no option marked selected → browser shows the first choice; labels show "(+N%)" or "(+$x.xx)" (marked-up add). Run size defaults to the first tier's qty (1612). Server `ClientOrders::addProduct` (ClientOrders.php:248-262, partial): a value not in the choices becomes `choices[0]`.
- New product from the order (`new_product`, client-order.php:205-212): `addProduct(..., [], ...)` → every option = first choice.
- Edit item (client-order.php:746): selects pre-selected from `pickedOf` (current text), and `setOptions` / line edit (ClientOrders.php:2017-2036, 1302-1310) again fall back to `choices[0]`.
- Repricing a draft (ClientOrders.php:228-245): uses `pickedFrom` of the stored text; a typed price (unit ≠ list) is kept; a checked costing's COGS replaces catalog cost.
- No client-facing option picker was found in the assigned files (shop/index.php sells no catalog products).

### Price lists
Per-list: name, CRM tag (or everyone), percent_off (0–90), note, active, owner_id (0 = Traversence). Rules: product (or all), min_qty, percent_off OR unit_price (unit price requires a product). `priceFor` (90-119): customer's tags by email; for each active list the customer qualifies for, candidates = list % and each matching rule; take the lowest total ("Catalog price" otherwise). Prices are on the whole line ("cheap items cost fractions of a cent each"). Only caller is the admin test (sections.php:262-280).

### Recipes / costs (Costing.php, partial)
Header: "cost of goods, seen and checked before a work order goes out (Jason, 2026-10-29)": cost list (paper, binding, other), a recipe per product (`cost_recipes`), each order item's costing (`order_line_costs`), checked by a person; lines marked "vendor" are vendor charges. Used here by: `WorkOrders::create` (vendor_cents default cost), `WorkOrders::specGaps`, `Collateral::reorder` → `Costing::carry` ("re-run at today's costs for the new run (unchecked)"), client-order.php `line_cost` (118-125).

---

## 5. Collateral and reorders

- Made: `fromOrder` (Collateral.php:50-79) when an order is approved (ClientOrders.php:1017) or pulled from QuickBooks (QbPull.php:174,326; `$artLater` lets catalog products in before files). Skips: non-client, unapproved, test (`Orders::isTest`), `qb_env='mock'`, void; lines shipping/go_code, cancelled/refunded, and tag `charge` ("charges (shrink wrap, fees) are never reorder items", 57); needs art (`hasArt`: approved proof or `final`, or order_files setup/print/artwork/native).
- Follows the newest order: existing item (by `collateral_id` or `source_line_id`) updated with new source order/line, product, options, qty, note, vendor, `times_ordered+1`; same line again → only options/qty/note. New: client_email (lowercased), name, company, user_id, name (title minus run size), product, options, qty (runQty), vendor, source/first order.
- `backfill` (81-89): all approved non-void, non-sandbox/mock client orders, oldest first; run the first time the Collateral list opens with an empty table (tools.php:212).
- Files (`files` 93-115): approved proof of the highest approved version, and finals; for clients only non-retired, finals only when the source order is paid in full; staff also see order_files of FILE_KINDS. URLs `/api/order-proof.php?o=…&p|f=…`.
- Client sees: `forClient` (117-124) `status='active' AND released=1` by email or user_id → website/user/orders.php ("Your artwork") and RecordFiles.php.
- Staff (tools.php `backoffice/collateral` 205-262): list with Show filter (In use / Released / Not released / Archived), detail with Reorder (qty, art same/changes), Release/Stop showing, Rename, Archive/Bring back → `Collateral::edit` (126-139, event `collateral` on the source order).
- Staff reorder: tools.php act `reorder` → `Collateral::reorder([id], [id=>n], sameArt, uid)`; also client-order.php:242 (partial).
- Client reorder: `askReorder` (141-156) from website/order.php `reorder` (only when the order stage is `complete`, 58-66) and website/user/orders.php (25-33). Session limit 5 per hour ("That's a lot of reorders at once…"). Creates the draft via `reorder(..., staff 0, requested true)`, client note as order event `note`, email to `ORDERS_FROM_EMAIL` (else support@) "Reorder asked for: …" with link `/admin/backoffice/orders/<nid>`.
- `reorder` (183-233): one client per reorder; new order via `ClientOrders::create` as staff or source `created_by`; copies bill/ship/pay fields (pay_mode, deposit_pct, due_terms, ship_mode, delivery, no_ship_reason); copies each source line except id/order/status/approved/created/item_status/tracking/project/ref/promo/collateral, sets `collateral_id`; numeric `ship_to` cleared; catalog line → `ClientOrders::updateLine` (today's catalog price); else qty; `Costing::carry`. Files: same art → proof copied as `waiting` with `reuse_of` (client "Confirm reuse"), finals as approved, all order_files; changes → no proof, no finals, no setup/print files (artwork/native/reference copied). `recalcOrder`; event `drafted` "Reorder asked for by the client: …" or "Reorder: …".

---

## 6. Shipping

- Modes (Shipments.php:15-16): none (reasons electronic / will_call / none), single, multi. `setMode` (49-62): creates main destination and lots when not none; single forces every lot to main; `recalcOrder`; event `shipping`.
- Destinations = `order_addresses` (label, name, street, city, state, zip, method, est_cents, is_main). Main = order's Ship-to fields (address shown from `orders.ship_*`, 79). `saveDest` (86-109): non-main needs street, city, state, 5/9-digit ZIP; estimate (charged to client) can't change after approval (`ClientOrders::editable`, 95). `deleteDest`: not main, not with packs, not with an approved estimate; its lots move to main.
- Lots = `order_lots` (line_id, dest_id, qty, label). `ensureLots`: every shippable line (not go_code/shipping) gets one lot = full qty to main (`itemQty`: catalog runQty, else qty). `saveLots` (145-169): rows with qty>0; dest forced to main in single mode; removed lots delete their pack items; event `lots` notes total vs item qty.
- Packs = `order_packs` (dest_id, box, weight_lb, carrier ∈ `ClientOrders::CARRIERS` usps/ups/fedex/other, service, cost_cents, tracking, label_id) + `order_pack_items` (pack_id, lot_id, qty). `savePack` (180-205) replaces pack items with given lots of that destination; label_id must be an `order_files` id on the order. `deletePack`. Estimate the client pays = sum of destinations' `est_cents` when shipped (`estimateCents` 216-220, used ClientOrders.php:2153).
- Printing (website/admin/manifest.php, staff `tv_crawler_require_admin`): `?id=` master manifest (all destinations: lots, qty, packed (warn if short), which packs; pack boxes), `&dest=` one destination, `&pack=` packing slip ("Pack n of N", checkboxes, "Packed by / Checked by / Date"). Header uses `ClientOrders::FROM`, `ref`, `po_number`. Linked from client-order.php:808,817,834.
- Client view: website/order.php:180-182 reads mode, destinations, lots (partial).
- Tracking elsewhere: order `tracking` via `ClientOrders::setStage`; WO carrier/tracking; vendor jobs tracking (`VendorOrders::tracking` fills the Pulse "Mark shipped" box, Pulse.php:582).
- Work orders: labels and manifests on the order go with every WO (choices key -1, WorkOrders.php:193-195); WO `delivery` list (21-22); "Use an address" picker incl. "All of them (per the labels / manifest)" (client-order.php:1092-1093).
- No carrier API / label purchase was found: labels are uploaded files (`order_file` kind `label`).

---

## 7. Pulse — order-related cases

### Item kinds raised (Pulse::items) — key prefix, source, severity, title, action
| Key | Source / sev | Condition | Line |
|---|---|---|---|
| `dispute:<oid>` | payments / critical | orders.status='disputed' | 105-106 |
| `paid:<oid>` | payments / event | paid_at in last 30 days | 107-108 |
| `refund:<oid>:<status>` | payments / update | refunded / part_refunded, updated ≤30d | 109-110 |
| `qbinbox:<id>` | payments / alert | `Orders::inbox` "A QuickBooks refund to place" → `/admin/ecommerce.php?sec=orders` | 111-112 (not in ORDER_KEYS: stays its own item) |
| `overdue:<oid>` | fulfillment / critical | due_date < today, status sent/pending | 115-116 |
| `proof:<pid>` | fulfillment / alert | order_proofs.status='changes' | 117-120 |
| `ship:<oid>` | fulfillment / alert, or update when a WO is with a vendor | approved, pending/paid, stage not shipped/ready/complete, ship_mode ≠ none, client order | 121-126 |
| `gotit:<oid>` | fulfillment / update | `got_it_reminder` event, not complete — "reminded and still not confirmed (Jason, 2026-10-30: 'Client or myself should complete this')" | 127-129 (own item, not folded) |
| `fb:<id>` | support critical (problem) / fulfillment event (review) | order_feedback | 130-137 |
| `wodecl:<woid>` | fulfillment / critical "Send elsewhere" | WO declined | 150 |
| `worej:<woid>` | fulfillment / alert "Reprint" | WO rejected on receipt | 151 |
| `wolate:<woid>` | fulfillment / critical "Chase it" | sent/accepted/production past dueAt | 152 |
| `woacc:<woid>` | fulfillment / alert "Chase it" | sent, now ≥ nextBusinessNine(sent_at) | 153 |
| `worcv:<woid>` | fulfillment / update "Receive" | shipped/ready with delivery will_call/internal/local/courier | 154 |
| `draft:<oid>` | sales / update | draft client orders ≤30d — "(Jason, 2026-10-28: 'why don't I see incoming orders')" | 173-174 |
| `sent:<oid>` | sales / update | status sent ≤30d | 175-176 |
| `won:<oid>` | sales / event | approved ≤30d | 177-178 |
| `domorder/domrenew/domverify:*` | fulfillment | `AccountDomains::attention` (Shop domains) | 290-298 |
| `domtk:<id>` | support / critical | domain ticket | 300-303 |
WO items exclude orders with `qb_env` sandbox/mock (147).

### One case per order (`byOrder`, 562-608)
`ORDER_KEYS` (559) = dispute, paid, refund, overdue, proof, ship, sent, won, draft, wodecl, worej, wolate, woacc, worcv → folded into `order:<oid>`; sandbox/mock skipped ("test orders stay out of the Pulse (Jason, 2026-10-28)"). Top item by severity then newest decides source/severity/issue. Adds `orderSteps`, tracking (falls back to vendor job tracking), work orders summary (id, staffMoves, no, vendor, status, label, due, tracking, opened, sent; cancelled skipped), vendor jobs labels, money left, outside/invoice. Stage `ordered` with WOs relabels "In production" step to "With the vendor" (589). `explain` text per issue (593-597). Action = flow next label unless issue is overdue/dispute/proof/sent/qbinbox. A completed order closes its case (531).

### orderSteps (614-642)
- Site (created_by 0, GO Codes): Ordered → Paid → Codes issued; closed when paid/refunded/void.
- Client: Sent → Approved → Paid → In production → (Shipped | Ready for pickup when `delivery='pickup'`; omitted when ship_mode or delivery is none) → Complete. `next`: not made → ['production','Start production']; production/ordered → shipped/ready/complete; else complete. `alt` = ['ordered','Sent to the printer'] at production (returned; no UI use of `.alt` found in pulse-ui.php).

### Playbook steps per issue (playbook 1104-1176, `$s(title, help, act, auto, ext)`)
- overdue (1125-1130): Check the payment [tool `/admin/order.php?id=`] auto=paid; Remind the client [reply text]; Settle it [tool order page]; Resolve [resolve] auto=paid.
- proof (1131-1136): Read what the client asked / Make the changes and upload a new proof / Send it to the client [tool `#lines`]; Resolve when they approve.
- dispute (1137-1142): Gather the evidence [tool money]; Answer the bank in QuickBooks [tool `/admin/quickbooks.php`, ext quickbooks]; Record how it ended; Resolve auto when status ≠ disputed.
- sent (1143-1147): Check they got it; Follow up [reply]; Resolve auto when status ≠ sent.
- default (ship, paid, won, draft, WO kinds…) (1148-1175): if pending → "Record the payment (X left)" [tool `#payhold`] auto=paid (outside-paid wording); client orders: no WO and vendor jobs → "With the printer" auto; no WO → "Send it to the printer" [tool `#work`]; each WO → title "Work order N · Vendor: Label", act = form `wo_move:<id>` "Update for the vendor" (fields `to` select of staffMoves defaulting to production if allowed, `carrier`, `tracking`) unless draft or no moves (then tool `#work`), auto when shipped/ready/complete/received; next stage button [stage]; for shipped/ready next also "Or: the client has it already" [stage complete]; Resolve auto when closed.

### Case actions and buttons
- pulse-ui.php Manage buttons (1083-1092): Edit the order / Open the order (toolUrl), Send to the client again (`resend`, draft/sent), Record a payment (`pay`, pending unpaid), Change the due date (`due`), Refund or credit (toolUrl money page, when paid), Add an order note (`note`), Void the order (`void`, unpaid non-void).
- Their forms (1252-1256) → `api {do:'manage', what, args}` → `Pulse::manage` (700-725): resend → `ClientOrders::send(oid, DAYS)`; pay → `ClientOrders::recordPayment(oid, cents, method, ref)` methods cash/check/zelle/venmo/bank/card/other; due → UPDATE orders.due_date + event; note → `Orders::note`; void → `ClientOrders::cancel`.
- Stage buttons (1184-1202) → `{do:'stage'}` → `Pulse::stage` (645-656) → `ClientOrders::setStage` ("the client hears only 'sent to print'"), optional vendor job when stage `ordered` (vendor or vendor_order_id required), case note, complete marks the case done. Shipped asks tracking inline; complete uses `TvAsk.gate`.
- Work orders box (1052-1058) lists WOs; vendor jobs box (1059-1078) "Vendor jobs (our team only)" with Update per job (`{do:'vjob'}` → `updateJob` + `setLine` when line changes, api 69-76), "+ Add a vendor job" (`{do:'vendor'}` → `Pulse::vendorJob` → `VendorOrders::addJob`, api 53-58), "Paste from 4OVER" (`{do:'parse4'}` then `{do:'import4'}`, api 59-68), margin line "Client price · vendor cost · margin (pct%)".
- `wo_move` form → `{do:'manage', what:'wo_move:<id>'}` → `WorkOrders::staffMove` (Pulse.php:852-860).
- `caseOf` (1545-1572) adds `vendor` = `vendorJobs` (jobs, vendors, recent vendor orders, margin, items) and merges the order's `order_events` (40 newest with notes, excluding `step*`) into history.
- `fb:` done/undo sets `order_feedback.resolved_at` (1058-1059).
- Chip "Order Mgmt" counts open fulfillment + payments items (pulse-ui.php:143, filter 611); quick link "Order management" `/admin/backoffice/production`, "New client order" `/admin/client-order.php?new=1` (48-50).

---

## 8. Scheduled jobs for orders

All in `Scheduler::JOBS` and `Scheduler::AUTO` (set up in cPanel by `Scheduler::sync`); each script calls `Scheduler::run(<job>)` (records `job_runs`; skips when `Automation::job` says paused).
| Job | Preset | What it does |
|---|---|---|
| `work_orders_tick` | hourly (:17), late after 180 min | `WorkOrders::tick` (751-775): WOs in sent/accepted/production with email and unexpired link; once each (`reminders` CSV): `accept` if sent and now ≥ 9:00 next business day after sent_at; `due` at/after due time; else `24h` within 24 h of due. One email per run combining reasons, logged "Reminder sent: …". |
| `got_it_tick` | hourly, 180 | `ClientOrders::gotItTick` (ClientOrders.php:769-791, partial): approved client orders pending/paid/part_refunded, not complete, never reminded, all out the door (`outTheDoor`) for ≥72 h (`GOT_IT_REMIND_HOURS`) and ≤30 days, no open problem ticket → one email "Did order #N arrive?" with `#got-it` link; event `got_it_reminder` (then Pulse `gotit:`). |
| `pay_wait_tick` | every 15 min, 60 | `ClientOrders::payWaitTick` (1674-1715, partial): pending orders with pay_method wire/check and empty pay_report, amount due > 0; stages from the `pay_method` event: 1 = +2 h, 2 = 9:00 next business day (`WorkOrders::nextBusinessNine`), 3 = 4 pm that day with other ways to pay; one email per stage to client and to `SUPPORT_EMAIL` (link `#payhold`); events `pay_nudge1..3`. |
| `billing_tick` | daily 6:13 | Plans (not client orders): renewals, retries, trials (`Billing::tick`). |
| `market_tick` | daily | Marketplace offers and `MarketCheckout::tick` (card orders for businesses), not client print orders. |
| `daily_report` | hourly (:02) | Emails every open Pulse case (includes order cases) at 5 am Arizona. |

---

## 9. What couldn't be traced (or is outside these files)

- `ClientOrders.php` internals (approve, setStage, setItemStatus, itemStatus, role, runQty, recalc/recalcOrder, recordPayment, cancel, send, proof/final upload handlers, canAddFiles, outTheDoor, confirmReceived, payment holds) were read only at the call sites listed; their full behavior (client emails on stage changes, item-status side effects) is not mapped here.
- `Costing.php` read only for its header, function list and `saveRecipe`; `forLine`, `forOrder` (`vendor_cents`), `carry`, `sheet` not traced.
- `admin/client-order.php` (the order page) read only for the work-order/collateral/option/shipping handlers quoted; its full UI and remaining POST actions are not mapped. `admin/products.php`, `admin/ecommerce.php`, `website/order.php`, `website/user/orders.php`, `admin/order.php`, `api/order-proof.php`, `QbPull.php`, `RecordFiles.php`, `BizQuotes.php`, `Books.php`, `MarketSquare.php` not read beyond call lines.
- `ClientOrders::role($pdo,$l) === 'print'` (used by moveItems to decide whether the order ships) not read.
- `Orders::inbox` (QuickBooks refunds to place) not read.
- `orderSteps()['alt']` ("Sent to the printer") is computed but no consumer was found in pulse-ui.php; the `ordered` stage button handler exists (pulse-ui.php:1192).
- `PriceLists::priceFor` has no caller in order pricing (only the admin price test); whether any other path applies price lists to client orders was not found.
- Migrations defining the tables (`2027-01-01_work_orders.sql`, `2027-01-02_work_order_status.sql`, `2027-01-03_collateral.sql`, `2026-12-12_shipping_manifests.sql`, `2026-12-17_vendor_orders.sql`, `2027-02-17_vendor_links.sql`) were not opened; column lists above come from the PHP.
- No carrier rate/label API, and no automated vendor-status fetch (4OVER data enters only by paste or typed fields).
