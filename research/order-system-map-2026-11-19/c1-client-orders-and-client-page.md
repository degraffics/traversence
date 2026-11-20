# C1: Client orders as built (ClientOrders.php, order.php, the client APIs, Your orders)

Read in full: `sync-app/lib/ClientOrders.php` (2228 lines), `sync-storage/order.php` (557), `sync-storage/api/client-order.php` (25),
`sync-storage/user/orders.php` (97). Also read in full because the order page depends on them: `sync-storage/api/order-proof.php` (39),
`sync-storage/api/wire-card.php` (22), `sync-storage/js/tv-proof.js` (141). From `sync-storage/js/go-codes.js` I read only the card form
(`Go.cardForm`, lines 1-100). Paths below use the mirror's names: `app/` = `sync-app/`, `website/` = `sync-storage/`. "CO" = `app/lib/ClientOrders.php`.
Callers come from a grep of `sync-app`, `sync-storage` and `sync-trvgo` (no callers in trvgo).

Header comment (CO:2-13), quoted exactly:
```
// app/lib/ClientOrders.php — orders our team builds for a client to review, approve and pay (decisions/0101).
//   draft     our team builds it: the client (a member, or a name and email), lines (catalog products with options and
//             quantity, GO Codes, custom lines), a proof per line to approve, the terms (pay in full, a deposit, or
//             approve now and pay the invoice later), a note and how long it's good for
//   sent      the client has a private link (/order.php?t=…): they approve each proof or ask for changes (our team
//             uploads a new version and sends again), then approve the order with their name and billing address
//   pending   approved: the QuickBooks invoice is made then (tax from the address); they pay by card on the same page
//             (all of it, or the deposit and later the rest), or pay the invoice later
//   paid      GO Codes go live at once; printed products move through their stages: in production → ordered from the
//             printer → shipped / ready for pickup → complete; each step emails the client
// Everything after approval is the order system's as for any order (Orders: refunds, credits, the ledger, the history).
```

---

## 1. Function and constant inventory (file order)

Shorthand: "event" means `Orders::event()` writes a row to `order_events`. `mine($editing=true)` means the order must be `draft` or `sent`
(see `mine`, CO:2123).

### Constants (CO:23-45)
| Name (line) | Value / meaning | External users |
|---|---|---|
| `PAY_MODES` (23) | `full` "Pay in full on approval", `deposit` "A deposit on approval, the rest before delivery", `invoice` "Approve now, pay the invoice later", `outside` "Paid outside QuickBooks (cash, check, Zelle…): we record it" | app/admin/tools.php:66, website/admin/client-order.php:609, website/order.php:368 |
| `DELIVERY` (26) | `ship`, `pickup`, `none`. Comment: "How it reaches the client (2026-12-08_delivery_outside.sql). '' = shipped when the order has a shipping line." | (internal) |
| `ITEM_STATUS` (31) | Groups: Before approval (`incoming, review, pending, changes, response`), Approved (`approved, creative_queue, creative, preproduction, holding, production, finishing, post`), Out the door (`ready, shipped, in_route, delivered, complete`), Exceptions (`payment_hold, collections, cancelled`), Housekeeping (`archived, deactivated, deleted`). Comment (27-30): "Each item's status (Jason, 2026-10-16): the order is the carrier, each item goes through its own process. Set by hand; the client hears only when our team taps Send update. Grouped as they're offered." | app/lib/VendorOrders.php:354, app/lib/WorkOrders.php:666, website/admin/client-order.php:627, 889 |
| `FROM` (40) | `['Traversence.com', 'PO Box 3570', 'Saint Johns, AZ 85936', 'support@traversence.com']`. Comment: "Billed From on every order and invoice (Jason, 2026-10-16)." | website/admin/manifest.php:40, website/order.php:176 |
| `DUE_TERMS` (41) | `receipt` (0 days), `net15`, `net30` | website/admin/client-order.php:605 |
| `PAY_METHODS` (42) | wire, cash, check, zelle, venmo, bank, card ("Card (another terminal)"), other | website/admin/client-order.php:183, 1188, 1190, 1196 |
| `STAGES` (43) | `production` "In production", `ordered` "Work order issued", `shipped`, `ready` "Ready for pickup", `complete` | app/admin/workflows.php:59, app/lib/Pulse.php:654, website/admin/ecommerce.php:181, website/admin/client-order.php:538 |
| `PROOF_TYPES` (44) | pdf, png, jpg, webp | app/lib/upload-targets.php:27, app/lib/RecordFiles.php:52, 117 |
| `DAYS` (45) | 14 (link life) | app/admin/workflows.php:67, app/lib/Pulse.php:706, website/admin/client-order.php:94, 97, 1062 |

### Reading
| Function (line) | What it does | Tables | Callers |
|---|---|---|---|
| `isClient` (49) | True when `orders.created_by > 0` (an order our team built). | none | workflows.php:54, Collateral.php:54, QbPull.php:197, Shipments.php:32, VendorOrders.php:352, Pulse.php:631, admin/client-order.php:294, 515, 543, 1301, admin/manifest.php:16, api/order-proof.php:14 |
| `byToken` (51) | Order by 40-hex `access_token`, only where `created_by IS NOT NULL`. | R orders | website/order.php:25, api/wire-card.php:11, api/client-order.php:14, website/claim.php:47 |
| `url` (58) | `SITE_BASE` (default https://traversence.com) + `/order.php?t=<token>`. | none | tools.php:67, admin/client-order.php:530, 1065 |
| `editable` (63) | status in `draft`,`sent`. | none | Shipments.php:95, 118, admin/client-order.php:206, 497 |
| `expired` (65) | status `sent` and `expires_at` in the past. | none | admin/ecommerce.php:176, admin/client-order.php:503, website/order.php:99 |
| `lines` (68) | `Orders::lines` plus each line's proofs (`proofs`, `proof` = latest, `versions`, `proof_files`), `finals` (kind 'final'), bundle `parts`, and a GO Code's `code` row. Return filter (91): `fn ($l) => $l['status'] !== 'cancelled' \|\| in_array($l['status'], ['refunded'], true)`. | R order_lines, order_proofs, order_line_parts, qr_issued, short_codes | WorkOrders.php:273, 281, 305, 354, 525, 676, admin/client-order.php:496, website/order.php:87 |
| `dueNow` (95) | 0 unless `pending`; else what's left, or the deposit first when `pay_mode='deposit'`, nothing paid yet and `deposit_cents>0`. | R order_ledger (via Orders::money) | admin/client-order.php:1188, website/order.php:98, api/client-order.php:19 |
| `staffEmail` (105, private) | The creator's active `staff_accounts.email`, else their `users.email` (not `.invalid`), else `SMTP_FROM_EMAIL`/support@traversence.com. | R staff_accounts, users | `mailStaff` |

### Building it (our team)
| Function (line) | What it does | Tables | Callers |
|---|---|---|---|
| `create` (121) | New `draft`: new token, links a member by email (else `guest_key`), `pay_mode='full'`, sets `qb_env` from `QuickBooksPay::env()`, numbers it (`Orders::number`: "a sandbox order gets S-1, S-2…"), event `drafted`. | W orders | Collateral.php:191, QbPull.php:120, admin/client-order.php:53 |
| `duplicate` (141) | Copy as a new draft: keeps bill/ship/note/pay_mode/deposit_pct/due_terms/ship_mode/delivery/no_ship_reason (151), projects, lines (not GO Codes, not cancelled), costing (`Costing::carry`), bundle parts, proofs (latest proof version `waiting`, earlier `replaced`, finals `approved`; files copied). `$test`: name prefixed "TEST · ", email the staff member's own, built under `QuickBooksPay::withEnv('sandbox', …)`. Comment 148: "a test copy is always a sandbox order (Jason, 2026-11-10), whatever mode Payments is in: no real money, ever". Docblock 136-140 quoted: "A copy of an order as a new draft: its items (projects, options, bundled lines, rates, run sizes) and their proofs (each item's latest version waiting again; earlier ones kept as earlier). $test: billed to you, not the client, so nothing reaches them. It's made in the QuickBooks mode that's on now (switch to Sandbox first for a test run). GO Codes aren't copied (each is one code)." | R/W orders, order_projects, order_lines, order_line_parts, order_proofs, (Costing tables) | admin/client-order.php:244 |
| `client` (191, private) | Validates name (required) and email (required: "Add the client's email: the order goes there."), trims company and phone. | none | create, saveTerms |
| `memberFor` (200, private) | `users.id` for the email (not `@staff.traversence.invalid`). | R users | create, saveTerms |
| `saveTerms` (207) | `mine(editing)`. Client fields, `pay_mode` (unknown → `full`), `deposit_pct` (1-99 required for deposit), `note` (300 chars), `po_number` and `due_terms` (if `hasItems`), `delivery` (if `hasDelivery`), relinks user/guest, also on pending `qr_issued`. `outside` refused without the delivery migration (message tells to "Import app/migrations/2026-12-08_delivery_outside.sql in phpMyAdmin first."). | W orders, qr_issued | admin/client-order.php:57; via `setField` |
| `refreshPrices` (227) | Draft only: each catalog line re-priced from the catalog (see §5f). | R products; W order_lines | `send` (634); admin/client-order.php:488 (every load of the admin order page) |
| `addProduct` (250) | `mine(editing)`; active catalog product; options default (see §5c); price = catalog (`Products::priceFor`) or typed; inserts line `qty 1` titled "Name · N", `run_qty` = N; recalc; event `line_added`. | R products; W order_lines | admin/client-order.php:60, 211 |
| `addCustom` (275) | Custom or shipping line, price, cost, taxable, note, optional `service_type` (Promotions::SERVICE_TYPES). | W order_lines | admin/client-order.php:63, 66 |
| `addGoCode` (292) | Inserts `qr_issued` (`status 'pending'`, price default `QrIssue::PRICE_CENTS`), gets its trvgo.us short code now ("it reads \"not active\" until the order is paid"), inserts a `go_code` line (taxable 1), makes its GO proof. | W qr_issued, short_codes (via QrIssue), order_lines, order_proofs | admin/client-order.php:70 |
| `updateGoCode` (314) | Changes a GO Code's fields/title (`qr_issued`, `short_codes.title`, line title); event; then `goProof` again: "changed: a new proof for the client to approve". | W qr_issued, short_codes, order_lines, order_proofs | admin/client-order.php:78; `setLineField`, `editLine` |
| `goProof` (336, private) | Next version; marks `waiting`/`changes` rows `replaced`; inserts a `mime='go-code'` proof (no file). Docblock: "A GO Code's proof: no file, the code itself (its QR and what it holds), approved by the client like any proof (mime 'go-code'). A new version replaces one still waiting or with changes asked for." | W order_proofs | addGoCode, updateGoCode, send (635) |
| `updateLine` (343) | Qty/price/note. Catalog line: catalog price for the new run unless a price is typed; title follows the run if it still ends in a size; `run_qty`. Other lines: qty, unit, note (GO Code qty 1, also `qr_issued.price_cents`). | W order_lines, qr_issued | Collateral.php:205, admin/client-order.php:73, 79; `setLineField` |
| `removeLine` (371) | Deletes a pending `qr_issued`, the line's proof files and rows, parts, the line; recalc; event. | W qr_issued, order_proofs, order_line_parts, order_lines | admin/client-order.php:81 |
| `extTypes` (385) | Maps ext → mimes for the uploader. | none | upload-targets.php:27, 42, 426 |
| `proofFromUpload` (397) | `mine(editing)`. Files sent together are one version (session `tv_op_batch`). First file of a new version: marks earlier `waiting`/`changes`/`approved` rows `replaced` (408: "a new version needs its own approval"); inserts without a status (column default). Message adds "Send the order again so the client sees it." when `sent`. Docblock cites decisions/0127. | W order_proofs | upload-targets.php:31 |
| `replaceProofFile` (422) | Swaps one file in a version (keeps version, status `waiting` or `replaced`, client_note); deletes old; `reproof` if it was decided. | W order_proofs | upload-targets.php:30 |
| `removeProofFile` (443) | Deletes one file; if the version is empty, the previous max version goes `replaced`→`waiting` (454); else `reproof`. | W order_proofs | admin/client-order.php:245 |
| `reproof` (464, private) | Version back to `waiting`, clears `decided_at`, initials, IP; recomputes `orders.review`. Docblock: "A proof version whose files changed (one replaced or taken out) needs the client's approval again (Jason, 2026-10-28): back to waiting, its approval, initials and changes request cleared. True when it was decided before." | W order_proofs, orders | replaceProofFile, removeProofFile |
| `fileFromUpload` (475) | An item file (`order_files`: label, artwork, reference… per `FILE_KINDS`); needs `2026-12-18_item_panel.sql`. | W order_files | upload-targets.php:46 |
| `deleteTest` (496) | Sandbox orders only: deletes everything (proof/file storage, parts, packs, lots, addresses, vendor jobs, work orders, projects, promotion_uses, ledger, events, lines, qr_issued, orders row; `order_inbox.order_id` nulled). "Only a sandbox (test) order can be deleted. A real one is cancelled, and kept." | W ~15 tables | admin/client-order.php:248 |
| `removeFile` (514) | Deletes an `order_files` row and storage; unlinks pack labels. | W order_files, order_packs | admin/client-order.php:219 |
| `canRetire` (529) | `order_files.retired_at` exists. | R | admin/client-order.php:921, 1247 |
| `retireFile` (540) | Sets/clears `order_files.retired_at`; refuses when an open work order carries `f<id>`. Docblock: "A file retired (Jason, 2026-10-30: an old copy never sent to a vendor by mistake) or brought back: it stays on the order, and while retired no work order offers it. Not while a work order that's still open carries it." | R order_work_orders (WorkOrders::forOrder); W order_files | admin/client-order.php:165 |
| `canRetireProofs` (559) | `order_proofs.retired_at` exists. | R | WorkOrders.php:282, admin/client-order.php:901, 1247 |
| `retireProof` (572) | Same for proofs (one file or `$wholeVersion`). Docblock cites "(Jason, 2026-10-30: take away access to old concepts and versions)" and "Retired, our team still sees it; the client's page doesn't show it and its links don't open it, and no work order offers it." | W order_proofs | admin/client-order.php:161 |
| `canAddFiles` (600) | Uploader pre-check: `mine($proof)` (editable only for proofs), line exists, item-panel migration for files. | R | upload-targets.php:26, 40, 425 |
| `proofPath` (609) / `proofPlace` (615) | Storage path `orders/<oid>/proofs/<id>.<ext>`, or the old `storage/order-proofs/...` (decisions/0124). | none | Collateral.php:219, WorkOrders.php:294, RecordFiles.php:116, api/order-proof.php:29; Store.php:444 |
| `proof` (621) | One `order_proofs` row on the order. | R order_proofs | api/order-proof.php:25 |
| `send` (629) | See §3/§4. Two docblocks (627, 628): "Sends (or sends again) the order to the client: their private link by email, good for $days." / "Out to the client: their link, by email. $mail false: a sandbox order opened by our team as the client, no email." Requires at least one line; `refreshPrices`; GO proofs for codes without one; `status='sent'`, `sent_at=NOW()`, `expires_at` = end of day +days (1-90, default 14), `review=''`; emails the client (unless `$mail=false`); event `sent`/`resent`. | W orders, order_proofs, order_lines | workflows.php:67, Workflow.php:8, Pulse.php:706 (resend), admin/client-order.php:94 (test_open, `$mail=false`), 97 (send) |
| `cancel` (653) | Refused if anything paid. Deletes pending `qr_issued`; `Orders::cancelUnpaid` (status `void`, lines `cancelled`, promo released); emails the client unless it was a draft. | W orders, order_lines, qr_issued | workflows.php:69, Pulse.php:724 (void), admin/client-order.php:249 |
| `setStage` (664) | See §3. Refused while `draft`/`sent`/`void`. Writes `stage`, `tracking`; at shipped/ready/complete marks `open` lines `done`; event `stage`; email if `$mail && wantsProgress`. Comment on `$detail`: "for our history only (the vendor's name), never the client's". | W orders, order_lines | workflows.php:68, WorkOrders.php:431 (markOrdered → 'ordered'), 677 (shipped/ready), Pulse.php:612, 651, admin/api/pulse.php:15, admin/client-order.php:257; `complete` |
| `openBeforeComplete` (680) | Words for what's still open: money left, work orders not received, items not shipped/picked up. | R ledger, work orders, lines | admin/client-order.php:252, 1301 |
| `complete` (699) | `setStage('complete', mail=false)`, all non-shipping items `item_status='complete'` (except cancelled/archived/deactivated/deleted), thank-you email. Docblock quoted in §4. | W orders, order_lines | admin/client-order.php:252; `confirmReceived` |
| `GOT_IT_ASK` (712) | "When you have it, tell us here, and how it went:" | | |
| `hasReceived` (714) | `orders.received_at` and `order_feedback` exist. | R | admin/client-order.php:540, 1314, website/order.php:227, 242 |
| `deliverables` (717, private) | Lines other than shipping/GO Code, not cancelled/refunded, item status not cancelled/archived/deactivated/deleted. | none | outTheDoor, setItemStatus |
| `outTheDoor` (724) | Status pending/paid/part_refunded, stage not complete; without item statuses: stage shipped/ready; else every deliverable's item status in shipped/in_route/delivered/ready (or order stage shipped/ready). | R order_lines | admin/client-order.php:540, 1314, website/order.php:227 |
| `feedback` (735) | `order_feedback` rows, newest first. | R order_feedback | admin/client-order.php:534, 1304, website/order.php:217 |
| `confirmReceived` (746) | Client says they have it: `received_at=NOW()`, saves a review, event `received`, `complete()`, emails staff. Docblock: "The client has it (Jason, 2026-10-30: \"once confirmed delivered it is considered complete\"): when, what they thought of it (optional), and the order completes. Our team hears, with their stars and words." | W orders, order_feedback, order_lines | website/order.php:56 |
| `GOT_IT_REMIND_HOURS` (762) | 72. "(Jason, 2026-10-30)". | | |
| `gotItTick` (769) | Cron (`app/scripts/got_it_tick.php:15`, Scheduler 'got_it_tick' "hourly"): one "Did order #N arrive?" email per order out the door ≥72h and <30 days, no open problem ticket; event `got_it_reminder`. Docblock: "...gets one email asking them to (whatever their progress emails setting: it's how the order completes). After that the Pulse asks our team to complete it if they know it arrived." | R orders, order_events, order_feedback; W order_events | got_it_tick.php |
| `outSince` (790) | Newest `item_status`/`stage` event time. | R order_events | gotItTick |
| `review` (797) | Stars/words after completion, once; emails staff. | W order_feedback | website/order.php:57 |
| `problem` (813) | Saves a problem, opens a Pulse ticket (`Pulse::openTicket('fb:<id>')`), event, emails staff and client. Docblock: "...it opens a support ticket (a case in the Pulse, Jason 2026-10-30)... The order stays open until the ticket is resolved (in the Pulse, or Mark resolved on the order)." | W order_feedback, pulse tables | website/order.php:58 |
| `resolveProblem` (832) | Pulse mark done, `resolved_at`, event. | W order_feedback, pulse | admin/client-order.php:255 |
| `saveFeedback` (843, private) | Inserts `order_feedback` (rating 0-5, words ≤2000, `may_quote`). | W order_feedback | confirmReceived, review, problem |

### The client
| Function (line) | What it does | Tables | Callers |
|---|---|---|---|
| `PROOF_TERMS_V` (859), `PROOF_TERMS` (860), `PROOF_CHECKS` (865) | Terms version '2026-10-28'; three terms (100% Client Responsibility, All Sales Are Final, Error Liability) and two checkboxes. Docblock: "The final-approval terms a client accepts to approve a proof (Jason, 2026-10-28): shown in the approval pop-up, ticked and initialled there, checked again here. Changing the wording: change PROOF_TERMS_V too, so the record says which wording each approval was given under." | | website/order.php:447, 449 |
| `staffView` (876) | True for staff (`QuickBooksPay::staffHere`) on a real (non-sandbox) order that isn't their own (user id or email). Docblock: "...it shows exactly what the client sees, but nothing on it acts for them: approving, paying, changes and wire details are theirs alone. Staff act for a client from the order in Back-Office (Approve for the client, Confirm received). Sandbox orders stay usable, and a staff member's own order is theirs to act on (Jason, 2026-10-28)." | R (auth) | website/order.php:29, api/client-order.php:16 |
| `STAFF_VIEW_MSG` (886) | The read-only message. | | website/order.php:39, 530, api/client-order.php:16 |
| `hasSignoff` (888) | `order_proofs.client_initials` exists. | R | |
| `decideProof` (891) | Client approves (with terms checks + initials) or asks for changes on a proof; whole version updated; `orders.review`; event; staff email on changes. See §3. | W order_proofs, orders | website/order.php:41, 42 |
| `approve` (919) | The client (or staff, `$byStaff`) approves; see §3 and §5. | W orders, order_lines, order_proofs; QuickBooks invoice; promotions; collateral | WorkOrders.php:309 (processOutside), admin/client-order.php:103 (staff_approve), website/order.php:46 |
| `reopenBlock` (988) | Why an approved order can't go back to editing (paid, money recorded, invoice made here, a work order out). Docblock cites "(Jason, 2026-10-28: a wrong quantity after approving)". | R ledger, order_work_orders | admin/client-order.php:515 |
| `reopen` (1002) | `status` back to `sent` (if ever sent) or `draft`, clears approved_at/approved_name/stage and lines' approved_at; event `reopened`. "its proofs stay approved". | W orders, order_lines | admin/client-order.php:100 |
| `toCollateral` (1014, private) | `Collateral::fromOrder` (decisions/0101, addendum 2026-10-28); errors only logged. | collateral tables | approve |
| `hasPrint` (1020, private) | Any line other than go_code/shipping. | none | approve, received |
| `pay` (1023) | Card payment of `dueNow` with an Intuit one-time token; MySQL `GET_LOCK('tv-pay-<id>')`; `Orders::sameEnv`; idempotency key `pay-<oid>-<paidBefore>-<declines>`; `QuickBooksPay::chargeCard`; saves `charge_id`; `recordCardPayment` on the invoice; `received()` as `deposit` if it doesn't complete the total, else `charge`; `needs_books` event on bookkeeping trouble; `card_declined` event on decline. | W orders, order_ledger, order_events | api/client-order.php:18 |
| `paidInQuickBooks` (1054) | See §5d. | W ledger via received | QrIssue.php:485 (`markPaid`, for orders with `created_by > 0`) |
| `hasItems` (1064) | `order_lines.item_status` exists. (Its docblock at 1063 reads "Money in: the ledger, then (paid in full) GO Codes issued and printed products into production; the client is emailed." and appears to belong to `received` at 1774.) | R | VendorOrders.php:352, admin/ecommerce.php:99, admin/client-order.php:552, website/order.php:263, 276 |
| `statusLabel` (1066) | Label of an ITEM_STATUS key, else the key. | none | tools.php:109, ecommerce.php:103, vendors.php:145, admin/client-order.php:689, 878, 948, website/order.php:285 |
| `itemStatus` (1069) | Own `item_status`, unless it's a "Before approval" one and the order is past draft/sent; else derived: void→cancelled, draft→incoming, sent→changes/pending, otherwise approved. | none | tools.php:103, VendorOrders.php:361, WorkOrders.php:669, 676, ecommerce.php:103, admin/client-order.php:687, 870, website/order.php:230, 276 |
| `ref` (1081) | The client's invoice number: `Y-md-` + `S` for sandbox + 5-digit padded order number. (Docblock says "TRV-<year>-<order number>"; code builds `date('Y-md', created_at) . '-' . ...`.) | none | Pulse.php:86, vendors.php:109, 144, admin/client-order.php:604, manifest.php:27, 41, website/order.php:188 |
| `dueDate` (1084) | `due_date` if set, else approved_at (or today) + DUE_TERMS days. | none | admin/client-order.php:607, website/order.php:188 |
| `setItemStatus` (1091) | Sets `item_status`, `tracking`; out-the-door statuses mark line `done`; event `item_status`; when the last deliverable goes out the door (and `hasReceived`, `wantsProgress`), one email. Comment 1102-1103: "the last of it out the door (Jason, 2026-10-30): the client hears once, with each item's tracking, and is asked to tell us when they have it (that completes the order)". | W order_lines | VendorOrders.php:363, WorkOrders.php:671, admin/client-order.php:202 |
| `sendUpdate` (1113) | Staff "Send update": emails each item's status; refused for drafts and when the client turned off progress emails. | R order_lines | admin/client-order.php:204 |

### Drop ship per item, in-place editing (CO:1139-1463)
| Function (line) | What it does | Tables | Callers |
|---|---|---|---|
| `hasShip` (1141) | `order_lines.ship_cents` exists. | R | admin/client-order.php:552, website/order.php:101 |
| `addresses` (1143) | `order_addresses` by id. | R | admin/client-order.php:552, website/order.php:101 |
| `addressText` (1150) | "name, street, city, ST ZIP". | none | Shipments.php:80, admin/client-order.php:1091, 1092 |
| `saveAddress` (1156) | Insert/update an `order_addresses` row (street, town, state, 5/9-digit ZIP required); any status but void. | W order_addresses | admin/client-order.php:187 |
| `SHIP_STYLES`, `CARRIERS`, `FILE_TYPES`, `ART_TYPES` (1177, decisions/0107), `DOC_TYPES`, `ALL_TYPES`, `FILE_KINDS` (1186) | Lookup lists. FILE_KINDS comment (1184-1185): "what a file is (Jason, 2026-10-28): a production setup is imposed and output-configured for a vendor that skips preflight and prepress (Competition); a print file is 1-up, for a vendor that does its own prepress (4OVER)". | | see callers file list: SHIP_STYLES admin/client-order.php:971; CARRIERS pack-form.php:10, Shipments.php:188, admin/client-order.php:807, 972, manifest.php:24; FILE_TYPES upload-targets.php:42, admin/client-order.php:633, api/order-proof.php:28,31,36; ART_TYPES upload-targets.php:426; ALL_TYPES upload-targets.php:42, RecordFiles.php:59,117; FILE_KINDS Collateral.php:111, WorkOrders.php:187,191,194, RecordFiles.php:60, admin/client-order.php:699,786,789,919,925,1257 |
| `TAGS` (1190), `hasTags` (1191), `tags` (1192), `setTags` (1195) | Item tags (Rush, Reprint, Sample), staff only. | W order_lines.tags | TAGS admin/client-order.php:883; hasTags 628; tags Collateral.php:57, admin/client-order.php:690, 882; setTags 84 |
| `hasInline` (1206) | `order_lines.ship_style` exists. | R | Collateral.php:41, 109, admin/client-order.php:532, 553, 962 |
| `shipToText` (1209) | The order's Ship to as text. | none | admin/client-order.php:973, website/order.php:177 |
| `setField` (1215) | One order field in place. Client/payment fields (`name, email, company, phone, pay_mode, deposit_pct, note`) only while editable, through `saveTerms` (deposit defaults to 50 when switching to deposit, 1227). `bill_*`, `ship_*`, `po_number` any time until void. `due_terms`, `due_date`. Docblock: "Client and payment while it's open; Ship to, P.O. and dates until cancelled." | W orders | api/admin/client-order.php:26, 48, 49 |
| `setLineField` (1241) | One item field in place (title, note, qty, unit, service_type, taxable). `mine(editing)`. Catalog qty → `updateLine`. | W order_lines | api/admin/client-order.php:30 |
| `editLine` (1283) | The item Edit form all at once (title, note, qty/run, rate or catalog price, options, taxable, service_type). Option fallback identical to addProduct (1308). | W order_lines | admin/client-order.php:218 |
| `reorder` (1333) | Line positions. | W order_lines | api/admin/client-order.php:41 |
| `setShip` (1340) | Ship icon: style, carrier, address text, charge, cost, tracking; the charge only while editable. | W order_lines | admin/client-order.php:191 |
| `filePath` (1357), `filePlace` (1363) | Item file storage paths (decisions/0124). | none | filePath: Collateral.php:225, WorkOrders.php:294, RecordFiles.php:116, api/order-proof.php:23; filePlace: Collateral.php:225, Store.php:444 |
| `files` (1370) | `order_files` by line, with sizes from storage. | R order_files | WorkOrders.php:179, admin/client-order.php:553, 1207 |
| `shipLabel` (1382) | Where an item goes: ship icon setting, else older `ship_to` (`pickup`, `a<id>`), else the order's Ship to, else "The client's address". | none | admin/client-order.php:695, 932, website/order.php:284 |
| `setLineShip` (1397) | Older per-item ship-to (`''`, `pickup`, `a<id>`) + charge/cost; charge only while editable. | W order_lines | admin/client-order.php:189 |
| `shipping` (1412) | Shipping total and rows: from `Shipments::destinations` estimates when Shipments is ready and the order ships; else per-line `ship_cents`. | R | admin/client-order.php:755, website/order.php:101; approve |
| `hasDelivery` (1431), `delivery` (1434) | `orders.delivery` column; its value, else `ship` if a shipping line exists, else `''`. | none | hasDelivery admin/client-order.php:38, 597; delivery admin/client-order.php:1160, 1161, website/order.php:103 |
| `needsClientAddress` (1443) | Any non-shipping line with `ship_to === ''`. | none | website/order.php:104; shipFrom |
| `shipFrom` (1450, private) | Ship-to from the approval form; see §5a. | none | approve |
| `recordPayment` (1469) | Staff records an outside payment on a `pending` order; see §5d. | W ledger; QuickBooks payment | Pulse.php:711, admin/client-order.php:185 |

### Other ways to pay, wire details (CO:1495-1772)
Section comment 1495-1496: "other ways to pay (decisions/0101, addendum 2026-10-28): a wire, a check, cash, chosen by the client on their order page; the order waits on a payment hold until our team records the money (recordPayment), which releases it."

| Function (line) | What it does | Tables | Callers |
|---|---|---|---|
| `CLIENT_METHODS` (1499) | wire, check, cash. | | admin/client-order.php:1187, website/order.php:202 |
| `hasPayChoice` (1501) | `orders.pay_offer` and `orders.notify_progress` exist. | R | website/order.php:394, 395 |
| `offers` (1507) | From env `CLIENT_PAY_WAYS` (unset: `wire,check`); wire dropped under `WIRE_MIN_CENTS`. Docblock: "the client picks, not our team (Jason, 2026-10-28). Site-wide, from Settings (CLIENT_PAY_WAYS); unset, a wire or a check." Inline 1513: "a wire only for $2,500 and up". | env | website/order.php:394 |
| `placed` (1521) | paid/part_refunded; or pending with cash chosen, or wire/check with a `pay_report`. Docblock: "(Jason, 2026-10-28: \"If it complete move to thank you for your order page\")". | none | website/order.php:194, user/orders.php:69 |
| `forMember` (1530) | `QrIssue::claim` then the member's client orders, not drafts, newest 200. | R orders | user/orders.php:16 |
| `clientStatus` (1540) | Words: "Waiting for your review" / "Link expired: ask us to send it again" / "Cancelled" / "Refunded" / "Placed: waiting for your wire transfer/check to arrive" / "Approved: $X to pay" / the stage label / "Paid". | none | user/orders.php:69 |
| `WIRE_MIN_CENTS` (1557) 250000; `WIRE_MINUTES` (1558) 10 | Section comment 1552-1555: "wire details, shown only to the client, only on screen (Jason, 2026-10-28) / Never emailed, never on the page as text: a code goes to the order's email; once it's entered, the details show for 10 minutes as a picture (no text to copy) stamped with who, which order, when and from where. Each view is logged and our team is emailed. The details and the phone number to confirm them live only in .env, by hand." | | WIRE_MINUTES website/order.php:410, api/wire-card.php:4 |
| `wirePhone` (1561) | env `WIRE_VERIFY_PHONE`, default "(602) 708-3795". | env | website/order.php:425 |
| `wireReady` (1567, private) | pending, due > 0, pay_method wire, wire offered, `WIRE_INSTRUCTIONS` set. | | wireSendCode, wireCheckCode |
| `wireSendCode` (1574) | 6-digit code (hash in session), ≤5 per hour, emailed; event `wire_code`. | W order_events | website/order.php:51 |
| `wireCheckCode` (1592) | Checks the code (5 tries); session `tv_wire_ok` for 10 min; event `wire_viewed`; staff email. | W order_events | website/order.php:53 |
| `wireLeft` (1610) | Seconds left on this session. | | website/order.php:409, api/wire-card.php:12 |
| `wireCard` (1613) | PNG (GD) of the wire details, amount, reference and phone, watermarked with order, email, time, IP; doubled in size. | | api/wire-card.php:12 |
| `howToPay` (1636) | env `<METHOD>_INSTRUCTIONS`, " \| " → new lines. Inline: "set by hand in .env only, never from a page: a stolen admin login can't swap the bank details". | env | website/order.php:406 |
| `choosePay` (1645) | Client picks a way (pending, due > 0). `card` clears `pay_method`. Otherwise sets `pay_method`, optional `pay_report`; event `pay_method`; emails client and staff. | W orders, order_events | website/order.php:49 |
| `payWaitTick` (1674) | Cron (`app/scripts/pay_wait_tick.php:15`, Scheduler 'pay_wait_tick', in Scheduler::AUTO; docblock says every 15 minutes): three reminders after a wire/check is chosen with no `pay_report`. Docblock: "Reminders while a wire or check is chosen and no confirmation code has come (app/scripts/pay_wait_tick.php, every 15 minutes; Jason, 2026-10-28), each once, to the client and to support: 2 hours after they chose; 9:00 am the next business day; 4:00 pm that day, with the other ways to pay. A code, a payment or another choice stops them (choosing again starts over)." | R orders, order_events; W order_events (`pay_nudge1..3`) | pay_wait_tick.php |
| `seen` (1722) | Event `client_opened` at most every 30 min, with device kind. Docblock: "The client opened their order page (Jason, 2026-10-28): told by the page itself once it's showing in a browser, so a mail scanner that only fetches the link doesn't count, and our team viewing it doesn't either..." | W order_events | website/order.php:33 |
| `opens` (1733) | Last open and count. | R order_events | admin/client-order.php:528; payWaitTick |
| `reportPay` (1741) | Client gives the wire confirmation code / check number: `pay_report`, `pay_reported_at`; event `pay_reported`; staff email. | W orders | website/order.php:50 |
| `releaseHold` (1755) | Staff: pending order with `stage=''` → `stage='production'`; event `released`. Docblock: "Our team: start production before the payment is in (a client we trust). The hold is otherwise released by the payment." | W orders | WorkOrders.php:312, admin/client-order.php:104, 106 |
| `setNotify` (1765) | `notify_progress` 0/1 (if `hasPayChoice`); event `notify` when it changes. Docblock: "The client: emails as the order moves along (in production, shipped, our updates), or not. Receipts always go." | W orders | website/order.php:47, 54 |
| `wantsProgress` (1772) | `notify_progress` (default 1) === 1. | none | admin/client-order.php:1139, website/order.php:385, 395 |
| `received` (1774, private) | Money in: see §3/§4/§5d. | W order_ledger (Orders::record), orders, qr_issued, short_codes, order_lines | pay, paidInQuickBooks, recordPayment |

### Promotions (decisions/0108), CO:1802-1865
Section comment 1803-1804: "A code is attached while the order is a draft or out for review (our team or the client adds it) and comes off the prices when the client approves: then each line's price is the price after it, and what it took off is kept."

| Function (line) | What it does | Tables | Callers |
|---|---|---|---|
| `lineType` (1807) | Line's `service_type`, else `go_code`, else the catalog product's `service_type`. | R products | role, promoPreview |
| `promoPreview` (1821) | Code and per-line amount off (or already applied via `promo_off_cents`). | R promotions, order_lines | admin/client-order.php:758, website/order.php:344 |
| `setPromo` (1838) | Attach/remove a code while editable; refuses a code that takes nothing off. | W orders.promo_id | admin/client-order.php:58, website/order.php:43 |
| `applyPromo` (1854, private) | At approval: re-checks the code (dropped with an event if no longer valid), lowers each line's `unit_cents`, sets `promo_off_cents`, recalc, `Promotions::redeem`. | W order_lines, orders, promotion uses | approve (935) |

### Projects, bundles, released files (decisions/0101, addendum 2026-10-27), CO:1870-2121
Section comment 1871-1875: "An order holds projects; a project holds items, each with its own setup and its own proofs (creative concept rounds, or a print item's print-ready file). The client sees each item's price and the project's subtotal, never the lines bundled inside an item. Approval is the release for use, once the order is paid in full: in a project with print, the print items' approved files; a creative item's approved artwork stays view only. A project with no print releases the creative item's print-ready file (uploaded by our team after approval). Earlier rounds are ours."

| Function (line) | What it does | Tables | Callers |
|---|---|---|---|
| `hasProjects` (1877) | `order_lines.project_id` and `order_projects` exist. | R | admin/client-order.php:636, 779, website/order.php:275 |
| `projects` (1879) | Order's projects. | R order_projects | admin/client-order.php:636, website/order.php:95 |
| `groups` (1890) | Lines grouped by project with subtotals (incl. `ship_cents`). | none | admin/client-order.php:637, website/order.php:95 |
| `addProject` (1908), `renameProject` (1920), `removeProject` (1929), `setLineProject` (1938) | Project CRUD (editable only). | W order_projects, order_lines | admin/client-order.php:214, 215, 216, 217, 262 |
| `role` (1948) | `creative` / `print` / `other`; a catalog product with no type is `print`. | R products | WorkOrders.php:676, admin/client-order.php:639, 651, 1101, 1103, 1106, website/order.php:275, 335 |
| `partsOf` (1957) | Bundle lines. | R order_line_parts | lines, duplicate, editLine, addPart |
| `addPart` (1966), `setPart` (1984), `syncParts` (2001, private) | Bundle lines on a custom item; the item becomes qty 1 at their total. | W order_line_parts, order_lines | addPart admin/client-order.php:221; setPart 223, 225 |
| `setOptions` (2013) | Change a catalog item's options; option fallback same as addProduct (2022); re-priced unless typed. | W order_lines | admin/client-order.php:220 |
| `pickedOf` (2035) | Current options. | R products | admin/client-order.php:746 |
| `paidInFull` (2042) | paid/part_refunded, or approved with ledger paid ≥ total. | R ledger | Collateral.php:102, admin/client-order.php:704, website/order.php:335 |
| `clientProofs` (2049) | The latest version's non-retired files, unless `replaced`. "earlier rounds are ours alone". | none | clientMay |
| `approvedFiles` (2054, private) | Newest version whose first file is `approved`. | none | released, finalFromUpload |
| `released` (2064) | Files the client may download once paid in full (print items' approved files; creative items' finals in a project with no print). | R | website/order.php:96; clientMay |
| `clientMay` (2080) | Client file access: retired never; released yes; finals no; download only for a `waiting` proof (2087: "the proof waiting for their OK downloads (Jason, 2026-10-28); others once released"); view the current proof. | R | api/order-proof.php:28 |
| `finalFromUpload` (2093) | A creative item's print-ready file (`kind='final'`, `status='approved'`), only after its proof is approved. | W order_proofs | upload-targets.php:429 |
| `removeFinal` (2114) | Deletes a final. | W order_proofs | admin/client-order.php:246 |

### Helpers (CO:2123-2227)
| Function (line) | What it does | Callers |
|---|---|---|
| `mine` (2123, private) | Client order or "That order isn't here."; with `$editing`, refused unless editable: "The client has approved this order, so it can't be changed here. Use Fix, Credit or Refund on the order page." | many internal |
| `costed` (2132) | Line has `order_line_costs` (decisions/0130). | refreshPrices |
| `line` (2138) | One line of the order. | Costing.php:228, admin/client-order.php:119 |
| `recalcOrder` (2146) / `recalc` (2148, private) | `items` = count of non-cancelled lines; `subtotal_cents` = `total_cents` = Σ(qty×unit + ship_cents) + `Shipments::estimateCents`. | recalcOrder: Collateral.php:229, Shipments.php:60, 106, 121 |
| `runQty` (2159), `runInTitle` (2167), `titleWithRun` (2170), `countIn` (2178), `runKnown` (2185), `hasRunQty` (2187), `rate` (2190), `rateTotal` (2197), `cents` (2205) | Run size, rates, money parsing. | runQty: tools.php:108, cost-tab.php:26, Collateral.php:59, QbPull.php:186, Shipments.php:39, Costing.php:201, WorkOrders.php:210, admin/client-order.php:652, 712, 877, website/order.php:283, website/wo.php:179; titleWithRun QbPull.php:267, 281; hasRunQty QbPull.php:271, 284, 338; runKnown admin/client-order.php:652; rate admin/client-order.php:717, 740, 877, website/order.php:283; cents Shipments.php:90, 187, WorkOrders.php:373, Pulse.php:709, admin/client-order.php:147 |
| `mail` (2212, private) | `Mailer::send` to `bill_email` (if valid), appends "Questions? Reply to this email, or write to support@traversence.com.\n\nTraversence"; errors logged. | |
| `mailStaff` (2221, private) | `Mailer::send` to `staffEmail()`, appends "The order: <SITE_BASE>/admin/backoffice/orders/<id>". | |

---

## 2. The client's page (`website/order.php`)

### Loading (1-111)
- CSP: `tv_card_page_csp('order', ['quickbooks', 'pdfjs'])` (16). Headers no-store, no-referrer.
- Order by `?t=` / POST `t` via `byToken` (25), only if `Products::ready`. **A draft is treated as no order** (26: `if ($o && $o['status'] === 'draft') $o = null;   // not sent yet`).
- No order → "This link doesn't work" card (81-83).
- `$staffView` (29). Every POST is refused with `STAFF_VIEW_MSG` (39); the page greys every form out with that reason and blanks the card box (527-537); `api/client-order.php` returns 403 `staff_view` for `pay` (16).
- Retired proof files and finals are removed from what the page has (88-94).
- `$review = status === 'sent' && !expired` (100). `$waiting` = count of the order's proofs in `waiting`/`changes`, only while `$review` (105).
- `$delivery = delivery($o, $lines)`; if `ship` but `!needsClientAddress` it becomes `'items'` (104: "every item goes to a set address or is picked up: nothing to ask").
- `$outside = pay_mode === 'outside'` (102).

### POST actions (`do=`), lines 32-76
| `do` | Calls | Then |
|---|---|---|
| `seen` (32) | `seen()` unless staff here | 204 |
| `proof_ok` (41) | `decideProof(…, true, '', ['checks' => $_POST['check'], 'initials' => $_POST['initials']])` | "Proof approved." |
| `proof_change` (42) | `decideProof(…, false, $_POST['note'])` | "Sent to us. We'll make the changes and send you a new proof." |
| `promo` (43) | `setPromo($oid, code)` (empty code removes it) | |
| `approve` (44-48) | requires `agree`; `approve($pdo, $o, $_POST['name'], $_POST)` (the whole `$_POST` is passed as `$bill`); then `setNotify($o, !empty($_POST['notify']))` | "Approved. Thank you!", to `#pay` |
| `pay_method` (49) | `choosePay(method, ref)` | `#pay` |
| `pay_report` (50) | `reportPay(ref)` | 303 to `#thanks` |
| `wire_code` (51) | `wireSendCode` | `#pay` |
| `wire_hide` (52) | unsets session `tv_wire_ok` | `#pay` |
| `wire_show` (53) | `wireCheckCode(code)` | `#pay` |
| `notify` (54) | `setNotify` | `#pay` |
| `received` (56) | `confirmReceived(rating, words, quote)` | `#reorder`. Comment 55: "done (Jason, 2026-10-30): they have it (that completes the order), how it went, a problem, a reorder" |
| `review` (57) | `review(...)` | `#feedback` |
| `problem` (58) | `problem(words)` | `#got-it` |
| `reorder` (59-66) | stage must be `complete`; `Collateral::onOrder` + `Collateral::askReorder(want, changes, note, "name (email)", user id)` | `#reorder` |
Card payment is not a form post: `Go.cardForm` → `Go.call('/api/client-order.php', {t, action: 'pay', token})` (548) → `ClientOrders::pay`.

### What renders, top to bottom, and in which states
1. **Header** (176): "Invoice" when `approved_at` is set, else "Order"; FROM lines.
2. **Bill to** box (179): company or name, street/town/state/ZIP if `bill_street` set, phone, email.
3. **Ship / Delivery** box (180-187), by `Shipments::mode($o)` when Shipments is ready: `none` → Delivery reason (will_call adds "We'll let you know when it's ready to pick up."); `multi` → "Ships to N places" with each destination and its lots; otherwise "Ship to": the order's ship address if `ship_street` set, else "Your address, from the approval" (single) / "Each item below says where it ships", plus ", or give your address when you approve" while `$review`.
4. **Details** box (188): Number (`ref()`), Date (sent_at or created_at), Due (only after approval), P.O.
5. Flash messages (190-191). **Sandbox banner** if `qb_env === 'sandbox'` (192). **Staff read-only banner** (193).
6. **Thank-you** section `#thanks` when `placed()` (194-213): "Thank you, <first>: order #N is paid/placed." Paid: amount and date, "Your order is in production." if there are print lines. Cash: "held until we receive your payment". Wire/check: "We'll email you as soon as your … arrives (your reference: …)". Steps strip (203). Buttons: See all your orders / Sign in / Create your free account / "Put <company> on Traversence" (`/claim.php?new=1&order=<token>`).
7. **Do you have it?** `#got-it` (227-236) when `hasReceived` && not complete && `outTheDoor`: the stars form (`do=received`, "Yes, I have it"), open-ticket note, "Something isn't right?" (`do=problem`).
8. **Complete** `#feedback` (237-243) when `stage === 'complete'`: thanks; stars form (`do=review`) unless already reviewed. **Reorder** `#reorder` (244-257) when `Collateral::onOrder` returns pieces: tick boxes (pre-ticked only when there is one piece, 250), qty prefilled with the collateral qty, "I want changes to the art", note.
9. Banners: **cancelled** when `void` (259) "This order was cancelled. Nothing was charged."; **expired** (260) "This order's link expired on <date>. Reply to our email and we'll send it again."; the order **note** (261).
10. **Where it is** (263-270), only when `stage !== ''`, there are print lines and `hasItems` is false: steps In production / Sent to print / Shipped (or Ready for pickup) / Complete, tracking.
11. **What's in it** (272-360), all states:
    - Hint "Look over each proof: Approve it, or tell us what to change. Then approve the order below." when `$review && $waiting` (274).
    - Per project box with subtotal (275, 340). Per line: title, options, note, GO Code contents, "N × $rate" (catalog run) or "qty × unit", "Ships to …"/"Picked up by you" + shipping, the **item status pill only after approval** (285: `$ist !== '' && $o['approved_at'] && product !== 'shipping'`) with tracking; amount with struck-through list price when list > unit (287). Lines whose item status is `deleted` are skipped (276).
    - **Proof** (289-328) for the line's latest proof: status word (303): waiting → "Waiting for your OK" (reuse: "The same art as before: confirm the reuse"), approved → "Approved"/"Reuse confirmed", changes → "Changes asked for", replaced → "Replaced". Reuse note (304) cites "(decisions/0101, addendum 2026-10-28)" at 294. Action template only when `$review` and status `waiting` or `approved` (293): **Approve** / **Confirm reuse** (a `data-signoff` form, only for `waiting`, 307-308) and **Make changes** / "Change my mind: make changes" (for approved) with a required textarea (309-312). Preview buttons open `tv-proof.js` lightbox (one per file; "N files in this proof: approving it approves them all." 315). "You asked: <note> We'll send you a new proof." when `changes` (327).
    - GO Code downloads (SVG/PNG) when `QrIssue::owned` (329-332).
    - **Released files** download buttons (333-334); else, for an approved proof on an approved but not fully paid order (print, or creative without print with finals): "The approved files are yours to download once the order is paid in full." (335-336).
    - Totals (342-358): Items / Shipping rows when `shipping()` has cents; promo line ("taken off the prices when you approve" before approval); **Subtotal**; promo code form **only while `$review`** (348-352). After approval: Sales tax (not for outside), Total, Paid, Refunded. Before approval and not outside: "Plus sales tax, worked out from your billing address when you approve." (358). After approval with `ship_street`: "Shipping to …" (359).
12. **Approve the order** `#approve` (362-392), only while `$review`:
    - With proofs waiting/changes: "First approve each proof above" (+ "(we're working on the changes you asked for, …)" when `review === 'changes'`); **no form** (365).
    - Otherwise: terms text (outside: "You'll pay us directly, as we arranged (cash, check, Zelle…)…"; else `PAY_MODES[pay_mode]` + deposit % + "Approving makes your invoice; printing starts right away / once it's paid") and the form (see below).
13. **Pay** `#pay` (394-438) when `status === 'pending' && $due > 0`. Heading: "Payment on its way" (placed) / "Pay the deposit" (deposit, nothing paid) / "Pay the rest" (something paid) / "Pay", with the amount (398). When placed, the payment parts are folded under "Your payment details, or change how you'll pay" (399). See payment choices below. The progress-emails toggle (`$notifyBox`, auto-submits) at the end (437).
14. **Paid** section (439-440) when status `paid`/`part_refunded`/`refunded`: amount, date, invoice no., GO Codes note, the notify toggle.
15. **Final approval pop-up** `#co-sign` (443-454): the three PROOF_TERMS, download links for the proof's files, the two PROOF_CHECKS checkboxes, initials, "Cancel & Request Changes" (opens the line's Make changes) and "Submit Final Approval" (disabled until both boxes and ≥2 letters of initials, 502). Comment 499: "a proof's final approval: the terms, two boxes and initials, then Submit (decisions/0101, addendum 2026-10-28)".

### The approval form's fields and defaults (369-388), quoted
```
370  <label for="a-street">Billing street<?= $outside ? ' (optional)' : '' ?></label><input id="a-street" name="street" maxlength="160" autocomplete="street-address" value="<?= $e($o['bill_street']) ?>"<?= $outside ? '' : ' required' ?>>
371  … <input id="a-city" name="city" … value="<?= $e($o['bill_city']) ?>"<?= $outside ? '' : ' required' ?>>
372  … <input id="a-state" name="state" maxlength="2" … placeholder="AZ" value="<?= $e($o['bill_state']) ?>"…> … <input id="a-zip" name="zip" maxlength="10" … value="<?= $e($o['bill_zip']) ?>"…>
373  <?php if ($delivery === 'ship'): ?>
374  <fieldset class="co-ship"><legend>Where should we ship it?</legend>
375    <label …><input type="checkbox" name="ship_same" value="1" checked style="width:auto" data-ship-same> To the billing address above</label>
376    <div data-ship-to hidden>
377      <label for="s-name">Name on the package</label><input id="s-name" name="ship_name" maxlength="120" autocomplete="shipping name" value="<?= $e($o['bill_name']) ?>">
378      <label for="s-street">Street</label><input id="s-street" name="ship_street" maxlength="160" autocomplete="shipping street-address">
379      … <input id="s-city" name="ship_city" …>
380      … <input id="s-state" name="ship_state" maxlength="2" … placeholder="AZ"> … <input id="s-zip" name="ship_zip" …>
383  <?php elseif ($delivery === 'pickup'): ?><p class="go-note" style="margin-top:.5rem">You'll pick it up: we email you when it's ready.</p><?php endif; ?>
384  <label for="a-name">Your name (your approval)</label><input id="a-name" name="name" maxlength="120" autocomplete="name" value="<?= $e($o['bill_name']) ?>" required>
385  <label …><input type="checkbox" name="notify" value="1"<?= ClientOrders::wantsProgress($o) ? ' checked' : '' ?> …> Email me as my order moves along (in production, shipped). Receipts always come.</label>
386  <label …><input type="checkbox" name="agree" value="1" required …> I approve this order and its proofs as shown.</label>
387  <button class="go-btn main wide" …>Approve the order</button>
```
- Billing street/town/state/ZIP are prefilled from the order's `bill_*` (what staff typed); required unless `outside`.
- The ship fieldset appears only when `$delivery === 'ship'` after line 104. **`ship_same` is ticked by default** (375); the ship-to block is hidden while it's ticked (JS 525: `t.hidden = c.checked`). `ship_name` is prefilled with `bill_name`; the ship street/town/state/ZIP are **empty** (they are not prefilled from the order's existing `ship_*`).
- Name prefilled with `bill_name`, required. Notify ticked when `notify_progress` is 1 (default). Agree unticked, required.
- Under it: "By approving you agree to our Terms." (389).

### Payment choices shown, and when (394-436)
- `$offers` = `offers($o)` only when `hasPayChoice` (394); defaults wire + check from `CLIENT_PAY_WAYS`; wire only when total (or subtotal) ≥ $2,500.
- `$way` (394) quoted: `$way = isset($offers[$o['pay_method'] ?? '']) ? (string) $o['pay_method'] : ($outside && $offers ? '' : 'card');`
- Buttons (401): `(['card' => 'Card'] unless $outside) + $offers`, each posting `do=pay_method`. Choosing wire/check first asks (TvAsk.ask) for the confirmation code / check number, blank allowed (401, 468-478).
- `$way === 'card'` (403-405): for `pay_mode === 'invoice'` with nothing paid: "Your invoice (…) was emailed to you; pay it any time, by card here or on QuickBooks' page." Then `#co-paybox`, filled by `Go.cardForm` (539-551) with `amount: $due`, the name and bill ZIP, the QuickBooks pay link ("Pay on QuickBooks' page instead"), sandbox test banner when env isn't production. The card goes browser → Intuit tokens endpoint; only the token reaches `/api/client-order.php` (go-codes.js 24-27).
- `$way` wire (409-425): "Protected" note; with a code verified: the wire card image (`/api/wire-card.php`) with a countdown, covered when the window loses focus, copy/print blocked (481-498), "Hide now"; otherwise "Email me a code to see them" / code entry. Phone warning (425). Wire with no `WIRE_INSTRUCTIONS`: "We'll send you our wire transfer details ourselves…" (424).
- Check / cash: `howToPay` text box or "We'll send you our … details ourselves" (424).
- Wire/check: "Tell us it's sent" form (`do=pay_report`, 428-431); cash: "Your order is held until we receive it…" (432).
- `$way === ''` (outside with offers, none chosen): "Pay us directly, as we arranged (cash, check, Zelle…). We confirm each payment by email and it shows here." (434).
- As written, an `outside` order with no offers (`hasPayChoice` false, or `CLIENT_PAY_WAYS` empty) gets `$way = 'card'`, so the card box and the card form script (539) are used.

### `/api/client-order.php` (25 lines)
POST + CSRF. `{t, action: 'pay', token}` → `pay()`; `{t, action: 'status'}` (anything else) → `{status, due_cents}` with no charge. Staff view refused for `pay` (403). PDOException → 500 "If your card was charged, we'll see it and email you; don't pay again."; RuntimeException → 400 with its message.

### `/user/orders.php` (Your orders, 97 lines)
Sign-in required. `forMember()` (orders on the account, `QrIssue::claim` first so orders sent to a confirmed email join), excluding drafts. Each row: "Order #N" linking to `/order.php?t=<token>`, company, date, item count, "test (sandbox)", the `clientStatus()` pill (green when `placed()` or paid), and `total_cents` (or subtotal before approval). Unconfirmed-email note. "Your artwork": `Collateral::forClient` pieces with files and an "Ask to reorder" form (`do=reorder` → `Collateral::askReorder`), flash, back to `#art`.

### Supporting endpoints read
- `/api/order-proof.php`: staff (is_admin) see every file; the client with the link (order not `draft`) only what `clientMay` allows; `?f=` item files are staff only. Served from S3 via `Store::redirect`, else streamed; non-image types always as attachment.
- `/api/wire-card.php`: PNG only for a `pending` order with `pay_method='wire'` and `wireLeft > 0`; else 404.
- `js/tv-proof.js`: the lightbox ("PROOF: title", Width/Page/2-4 across view modes remembered in localStorage, pdf.js 3.11.174 from cdnjs without eval, red dashed cut line, the acts template copied into its top bar).

---

## 3. State machine

### `orders.status`
| Value | Set by (file:line) | Trigger |
|---|---|---|
| `draft` | CO `create` 126; CO `reopen` 1007 (when never sent) | staff (also Collateral.php:191 reorder, QbPull.php:120) |
| `sent` | CO `send` 639; CO `reopen` 1007 (when `sent_at` set) | staff (admin send/test_open, workflows op `send`, Pulse `resend`) |
| `pending` | CO `approve` 945 (outside) / 971 (QuickBooks) | client (order.php:46), staff (admin `staff_approve` 103; `WorkOrders::processOutside` 309) |
| `paid` | CO `received` 1783 (`IF(status = 'pending', 'paid', status)`) when ledger paid ≥ total; also `Orders::settle` (Orders.php:96-106) after any `Orders::record` | client card (`pay`), QuickBooks webhook/refresh (`paidInQuickBooks`), staff (`recordPayment`) |
| `part_refunded`, `refunded`, `disputed` | `Orders::settle` (Orders.php:104), outside this file | refunds/chargebacks in Orders |
| `void` | `Orders::cancelUnpaid` (Orders.php:464) via CO `cancel` 659 | staff (admin cancel, workflows op `cancel`, Pulse `void`) |
| (row deleted) | CO `deleteTest` 510 | staff, sandbox only |
Guards: `editable` = draft/sent; `approve` needs `sent`, or `draft` when `$byStaff` (924); `setStage` refuses draft/sent/void (669); `recordPayment`, `releaseHold`, `choosePay`, `reportPay`, `dueNow`, `wireReady` need `pending`; `reopen` needs `pending` with nothing paid, no `invoice_id`, no work order out (988-999).

### `orders.stage` ('' or a `STAGES` key)
| Value | Set by | Trigger |
|---|---|---|
| `''` | `approve` outside 945 (`stage = ''`, comment "on a payment hold: the payment recorded releases it to production (or Release now)"); `approve` QB 973 (unless invoice+print); `reopen` 1007 | client/staff approval; staff reopen |
| `production` | `approve` 973 when `pay_mode === 'invoice' && hasPrint`; `releaseHold` 1760; `received` 1790 when print and stage ''; WorkOrders.php:430, 677 | approval; staff release; any money in; work orders |
| `complete` | `received` 1791 when no print and paid in full; `complete()` via `setStage` 701; AccountDomains.php:465 | payment; client confirm / staff complete |
| any of production/ordered/shipped/ready/complete | `setStage` 671 | staff (admin `stage` 257, Pulse 612/651, admin/api/pulse.php, workflows op `stage`); `WorkOrders::markOrdered` 431 (`ordered`); WorkOrders 677 (`shipped`/`ready` when every print item is out the door) |

### `orders.review` ('' or 'changes')
- `send` 639 sets `''`; `decideProof` 910 sets `'changes'` when any of the order's proofs is in `changes`, else `''`; `reproof` 470 same recount; `approve` (outside) 945 sets `''`. (The QuickBooks branch's UPDATE at 971 doesn't list `review`.)

### `orders.pay_mode`
- `create` 126 `'full'`; `saveTerms` 211/218 (from staff input; unknown → `full`); `setField` 1222-1228 → `saveTerms` (only while editable); `duplicate` copies it (151); `WorkOrders::processOutside` (WorkOrders.php:307) sets `'outside'` before `approve`, restores the old value if approve throws.

### `orders.pay_method` / `pay_report`
- `choosePay` 1649 (`''` for card), 1653 (method), 1654 (`pay_report`, `pay_reported_at` when a ref is given); `reportPay` 1747. Client-triggered only.

### `orders.notify_progress`
- `setNotify` 1768; client (approve form 47, toggle 54).

### `order_lines.status` (`open`, `done`, `cancelled`, `refunded`)
- `done`: `setStage` 672 (all `open` lines at shipped/ready/complete); `setItemStatus` 1100 (that line, out-the-door statuses); `received` 1788 (GO Code lines when paid in full).
- `cancelled`: `Orders::cancelUnpaid` (Orders.php:465) via `cancel`.
- `refunded` and the initial value: set outside this file (not traced; see §6).
- `order_lines.approved_at`: set at `approve` 947/974, cleared by `reopen` 1008.

### `order_lines.item_status` (ITEM_STATUS keys; '' = derived)
- `setItemStatus` 1099: staff (admin 202), `VendorOrders` (VendorOrders.php:363, never backwards, holds stay), `WorkOrders` (WorkOrders.php:671, from a work order's status). `complete()` 702 sets `complete` on every non-shipping item not cancelled/archived/deactivated/deleted. `duplicate` doesn't copy it (157).
- When empty, `itemStatus()` (1069) derives: void→cancelled, draft→incoming, sent→changes/pending (by `review`), else approved. A "Before approval" status set by hand is ignored once the order is past draft/sent (1072-1073).

### `order_proofs.status` (`waiting`, `approved`, `changes`, `replaced`) and `kind` (`proof`, `final`)
| Move | Where | Trigger |
|---|---|---|
| new row, no status given (column default) | `proofFromUpload` 411; `goProof` 340 | staff upload; GO Code add/change; `send` 635 for codes without a proof |
| `waiting`/`changes` → `replaced` | `goProof` 339 | GO Code changed |
| `waiting`/`changes`/`approved` → `replaced` | `proofFromUpload` 408 (first file of a new version) | staff uploads a new version |
| `waiting` or `replaced` (explicit) | `replaceProofFile` 430 | staff swaps a file |
| decided → `waiting` (+ initials cleared) | `reproof` 468 | staff replaced/removed a file in a decided version |
| `replaced` → `waiting` (previous version) | `removeProofFile` 454 | staff removed a version's last file |
| → `approved` / `changes` (whole version) | `decideProof` 906 | client (order.php:41-42) |
| `waiting`/`changes` → `approved` | `approve` 939 when `$byStaff && $wait` | staff approving for the client |
| `approved`, kind `final` | `finalFromUpload` 2104 | staff |
| `waiting` (latest) / `replaced` (earlier) / `approved` (finals) | `duplicate` 177 | staff copy |
| `retired_at` set/cleared | `retireProof` 591 | staff |
| (rows deleted) | `removeLine` 377, `removeProofFile` 449, `replaceProofFile` 435, `removeFinal` 2119, `deleteTest` | staff |
Collateral.php:216 also inserts `order_proofs` rows (with `reuse_of`) for reorders.

### `qr_issued.status` (GO Codes on the order)
- `pending` at `addGoCode` 302; → `paid` + `issued_at` at `received` 1785 when paid in full (then `QrIssue::issueShort`); deleted at `removeLine` 375 and `cancel` 658; `cart` + `order_id NULL` in `Orders::cancelUnpaid` (Orders.php:466).

### Money (`order_ledger` via `Orders::record`)
- `received` 1776 records `deposit` or `charge` with a unique key (returns early if the key was recorded already: "recorded already").

### Crons
- `gotItTick` (hourly, `app/scripts/got_it_tick.php`), `payWaitTick` (`app/scripts/pay_wait_tick.php`; Scheduler.php:25 and AUTO; OutsideServices.php:344 lists it under quickbooks jobs).

### Webhook path to "paid"
- `website/api/qb-webhook.php:27-29` → for `pending` orders with that invoice, `QuickBooksPay::paid()` (balance ≤ 0.004; also rewrites `tax_cents`/`total_cents` from the invoice, QuickBooksPay.php:727-728) → `QrIssue::markPaid` → for `created_by > 0`, `ClientOrders::paidInQuickBooks` (QrIssue.php:481-485). Also `QrIssue::refresh` (QrIssue.php:469) and admin/order.php:73, 79.

---

## 4. Emails sent from ClientOrders.php

All client mails go through `mail()` (2212) to `bill_email`; staff mails through `mailStaff()` (2221) to the order's creator (`staffEmail`).

| Subject | When | To | Function:line | Switch |
|---|---|---|---|---|
| "Your order from Traversence, for your review" / "Your updated order from Traversence, for your review" | Send / send again. Body: review link, "approve the proofs," if any waiting, "The total is $X before tax.", pay mode (+deposit %), link expiry date | client | `send` 643 | none; skipped when `$mail=false` (sandbox test_open) |
| "Your Traversence order was cancelled" | cancel, unless it was a draft. "Nothing was charged." | client | `cancel` 660 | none |
| "Your Traversence order is in production / has been sent to print / has shipped / is ready for pickup / is complete" | `setStage` | client | `setStage` 675 | `$mail && wantsProgress($o)` |
| "Thank you for your order #N" | `complete()` | client | `complete` 706 | none (receives review link unless reviewed, and reorder link) |
| "Received: order #N ★★★…" | client confirmed receipt | staff | `confirmReceived` 757 | none |
| "Did order #N arrive?" | 72 h after out the door, once | client | `gotItTick` 781 | none ("whatever their progress emails setting") |
| "Feedback: order #N ★…" | client review | staff | `review` 805 | none |
| "Support ticket #T: order #N" | client problem | staff | `problem` 823 | none |
| "We have your note about order #N (ticket #T)" | client problem | client | `problem` 826 | none |
| "Changes requested: order #N" | client asks for changes on a proof | staff | `decideProof` 912 | none |
| "Approved: order #N" (outside) | approval of an outside order | staff | `approve` 950 | only when `!$byStaff` |
| "Approved: order #N" (QuickBooks) | approval | staff | `approve` 978 | none (also when staff approve) |
| (QuickBooks invoice email) | approval, `pay_mode === 'invoice'` | client, by QuickBooks | `approve` 964 passes `$o['pay_mode'] === 'invoice'` as `invoiceFor(..., $email)` | not traced inside QuickBooksPay |
| "Your Traversence order #N is on its way" / "… is ready for pickup" | the last deliverable goes out the door | client | `setItemStatus` 1107 | `!$was && hasReceived && outTheDoor && wantsProgress` |
| "An update on your order <ref>" | staff "Send update" | client | `sendUpdate` 1125 | throws if `!wantsProgress` |
| "Your code to see our wire details: NNNNNN" | client asks for the wire code | client | `wireSendCode` 1583 | none |
| "Wire details viewed: order #N" | code accepted | staff | `wireCheckCode` 1605 | none |
| "How to pay order #N by wire transfer/check/cash" | client chooses a way | client | `choosePay` 1658 | none (wire details never in it) |
| "Paying by …: order #N" | client chooses a way | staff | `choosePay` 1664 | none |
| "Reminder: your payment for order #N" / "Still waiting on your payment for order #N" | 2 h, next business 9 am, 4 pm | client | `payWaitTick` 1702 | none |
| "Payment reminder K of 3: order #N" | same | `SUPPORT_EMAIL` (default support@traversence.com) via `Mailer::send` directly | `payWaitTick` 1705 | none |
| "Wire sent: order #N" / "Check sent: order #N" | client reports it's sent | staff | `reportPay` 1750 | none |
| "Thank you: order #N is paid" / "Thank you: your deposit on order #N" | money in | client | `received` 1794 | `$receipt` (true except `recordPayment(..., $receipt=false)`) |
| "Paid: order #N" / "Deposit paid: order #N" | money in | staff | `received` 1799 | none |

`complete()` docblock (692-698), quoted: "The order is done (Jason, 2026-10-30): its stage is Complete, every item that isn't cancelled is Complete, and it moves to the archive (Order management → Completed). The client confirming they have it completes it (confirmReceived()); our team can too (\"Complete the order\" in the command strip). $what: what was still open, for the history. The client gets a thank-you with a link to reorder (priced at today's rates) and, if they haven't yet, to tell us how it went. Its art stays in the Collateral Center for the reorder."
Note: `complete()` calls `setStage(..., false)` (701), so no stage email goes with it. `approve` itself sends no client email.

---

## 5. Verbatim code and comments on specific behaviours

### (a) shipFrom / needsClientAddress / approve writing ship_*
```
1442    /** Whether any item ships to the client's own address (the one asked for when approving). */
1443    public static function needsClientAddress(array $lines): bool
1444    {
1445        foreach ($lines as $l) if ($l['product'] !== 'shipping' && ($l['ship_to'] ?? '') === '') return true;
1446        return false;
1447    }
1448
1449    /** The ship-to address from the approval form ('ship_same' = the billing address); [] when it isn't shipped. */
1450    private static function shipFrom(array $o, array $lines, array $in, array $bill): array
1451    {
1452        if (self::delivery($o, $lines) !== 'ship' || !self::needsClientAddress($lines)) return [];   // every item has its own address or is picked up
1453        if (!empty($in['_ship_on_wo']) && trim((string) ($in['ship_street'] ?? '')) === '') return [];   // handled outside: where it ships is on its work order (WorkOrders::processOutside)
1454        if (!empty($in['ship_same'])) {
1455            if ($bill['street'] === '') throw new RuntimeException('Add the billing address to ship there, or give the address to ship to.');
1456            return ['name' => (string) $o['bill_name']] + array_intersect_key($bill, array_flip(['street', 'city', 'state', 'zip']));
1457        }
1458        $a = [];
1459        foreach (['name' => 120, 'street' => 160, 'city' => 80, 'state' => 40, 'zip' => 12] as $k => $max) $a[$k] = mb_substr(trim((string) ($in['ship_' . $k] ?? '')), 0, $max);
1460        if ($a['name'] === '') $a['name'] = (string) $o['bill_name'];
1461        if ($a['street'] === '' || $a['city'] === '' || $a['state'] === '' || !preg_match('/^\d{5}(-\d{4})?$/', $a['zip'])) throw new RuntimeException('Add the address to ship to (street, town, state and ZIP).');
1462        return $a;
1463    }
```
`delivery()` (1433-1440): "How the order reaches the client: its setting, else shipped when it has a shipping line, else '' (not said)."
In `approve`:
```
930        $b = ['name' => (string) $o['bill_name'], 'email' => (string) $o['bill_email']];
931        if ($byStaff && trim((string) ($bill['street'] ?? '')) === '') foreach (['street', 'city', 'state', 'zip'] as $k) $bill[$k] = (string) ($o['bill_' . $k] ?? '');   // our team approving: the order's Bill to
932        foreach (['street' => 160, 'city' => 80, 'state' => 40, 'zip' => 12] as $k => $max) $b[$k] = mb_substr(trim((string) ($bill[$k] ?? '')), 0, $max);
…
936        $lines = Orders::lines($pdo, $oid);
937        $ship = self::shipFrom($o, $lines, $bill, $b);   // [] when it isn't shipped
…
940        $pdo->prepare('UPDATE orders SET bill_street = ?, bill_city = ?, bill_state = ?, bill_zip = ? WHERE id = ?')->execute([$b['street'], $b['city'], strtoupper($b['state']), $b['zip'], $oid]);
941        if ($ship) $pdo->prepare('UPDATE orders SET ship_name = ?, ship_street = ?, ship_city = ?, ship_state = ?, ship_zip = ? WHERE id = ?')->execute([$ship['name'], $ship['street'], $ship['city'], strtoupper($ship['state']), $ship['zip'], $oid]);
```
- On the client page, `$bill` is the entire `$_POST` (order.php:46), so `ship_same` and `ship_*` reach `shipFrom` as `$in`.
- The page's form default is `ship_same` ticked (order.php:375), with ship street/town/state/ZIP inputs empty and `ship_name` = `bill_name`.
- With `ship_same`, the ship address returned is `bill_name` + the billing street/city/state/zip just entered; it overwrites `orders.ship_*` at 941.
- `WorkOrders::processOutside` (WorkOrders.php:308) passes `['_ship_on_wo' => 1, 'ship_name'…'ship_zip' => the order's existing ship_*]` as `$bill`. With an existing `ship_street`, shipFrom continues: no `ship_same`, so it returns those fields (re-validated). Billing for that path comes from line 931 (the order's `bill_*`), since `$bill['street']` is empty.
- The order page's own pre-check (order.php:104): `if ($delivery === 'ship' && !ClientOrders::needsClientAddress($lines)) $delivery = 'items';`.
- `bill_*` (940) is written on every approval, including `outside` with blank billing (`$hasBill` false allows it, 933-934).

### (b) Do send / approve require a proof per printed item?
`send` (629-651) checks only that lines exist and adds GO proofs:
```
633        if (!$lines) throw new RuntimeException('Add something to the order first.');
634        self::refreshPrices($pdo, $oid);   // the catalog's latest, as it goes out
635        foreach ($lines as $l) if ($l['product'] === 'go_code' && !$pdo->query('SELECT 1 FROM order_proofs WHERE line_id = ' . (int) $l['id'])->fetchColumn()) self::goProof($pdo, $oid, (int) $l['id'], $staff);   // codes added before proofs
```
There is no check in `send` that a printed (non-GO) line has a proof. `$proofs` (641) only changes the email wording.
`approve` counts proof rows in `waiting`/`changes`; a line with no proof rows adds nothing to that count:
```
926        $wait = (int) $pdo->query("SELECT COUNT(*) FROM order_proofs WHERE order_id = $oid AND status IN ('waiting', 'changes')")->fetchColumn();
927        if ($wait && !$byStaff) throw new RuntimeException('Approve each proof first' . ($o['review'] === 'changes' ? ' (we\'re working on the changes you asked for)' : '') . '.');
…
939        if ($byStaff && $wait) $pdo->prepare("UPDATE order_proofs SET status = 'approved', decided_at = NOW() WHERE order_id = ? AND status IN ('waiting', 'changes')")->execute([$oid]);
```
The page's gate is the same count (order.php:105, 365). The pop-up text and the agree box say "I approve this order and its proofs as shown." (order.php:386). The header comment (CO:5) says the draft has "a proof per line to approve".

### (c) addProduct when an option choice is missing
```
249    /** A catalog product line: quantity, options picked, the catalog price (or ours: a change shows as a discount). */
…
256        $labels = []; $picked = [];
257        foreach ($p['options'] as $i => $o) {
258            $v = (string) ($opts[$i] ?? '');
259            if (!in_array($v, array_column($o['choices'], 'label'), true)) $v = $o['choices'][0]['label'];
260            $labels[] = $v; $picked[] = $o['name'] . ': ' . $v;
261        }
262        $list = Products::priceFor($p, $qty, $labels);
```
A missing or unknown choice becomes the option's first choice, with no error and no event noting it. The same fallback is in `editLine` (1307-1308) and `setOptions` (2021-2022).

### (d) Deposits, partial payments and paidInQuickBooks
```
94     /** What the client owes now: the deposit first (when the terms say so), then whatever is left. */
95     public static function dueNow(PDO $pdo, array $o): int
97         if ($o['status'] !== 'pending') return 0;
98         $paid = Orders::money($pdo, (int) $o['id'])['paid'];
99         $left = max(0, (int) $o['total_cents'] - $paid);
100        if ($o['pay_mode'] === 'deposit' && $paid === 0 && (int) $o['deposit_cents'] > 0) return min($left, (int) $o['deposit_cents']);
101        return $left;
```
- Deposit amount is set at approval (QuickBooks path only): `970  $dep = $o['pay_mode'] === 'deposit' ? (int) round($total * (int) $o['deposit_pct'] / 100) : 0;` written to `deposit_cents` at 971. The outside path sets `deposit_cents = 0` (945).
- `pay` (1046-1047): `$isDeposit = $paidBefore + $due < (int) $row['total_cents']; self::received($pdo, $oid, $due, $isDeposit ? 'deposit' : 'charge', 'card', 'card ' . $charge, $key);`
- `recordPayment` (1465-1468 docblock): "A payment received outside QuickBooks (cash, check, Zelle…), recorded by our team on an approved order: a deposit while some is left, the payment when it's all in (the GO Codes go live, printing starts, the client is emailed)." Code 1479: `self::received($pdo, $oid, $cents, $cents < $left ? 'deposit' : 'charge', $m, mb_substr(trim($ref), 0, 60), $key, $receipt);` More than what's left is refused (1475-1476, with a note about QuickBooks invoice mismatch when `invoice_id` is empty but `invoice_no` set). Idempotency key (1478): `'out-' . $oid . '-' . substr(hash('sha256', $cents . '|' . $method . '|' . $ref . '|' . date('YmdHi')), 0, 16);   // a double tap is recorded once`. When the order has `invoice_id`, it is also recorded in QuickBooks under the order's `qb_env` (1481-1489).
- `paidInQuickBooks`:
```
1053    /** The invoice was paid in QuickBooks (its page, or recorded there): the rest is recorded and the order goes on. */
1054    public static function paidInQuickBooks(PDO $pdo, int $oid): void
1056        $o = Orders::get($pdo, $oid);
1057        if (!$o || $o['status'] !== 'pending') return;
1058        $left = (int) $o['total_cents'] - Orders::money($pdo, $oid)['paid'];
1059        if ($left <= 0) return;
1060        self::received($pdo, $oid, $left, 'charge', 'quickbooks', (string) $o['invoice_no'], 'charge-' . $oid);
```
  Its caller path only fires when `QuickBooksPay::paid()` finds the invoice balance ≤ 0.004 (QuickBooksPay.php:729), and that call first rewrites `orders.tax_cents`/`total_cents` from the invoice (727-728). The ledger key is the fixed `'charge-' . $oid`.
- `received` (1774-1800): records the ledger row (returns if the key exists); `$full = paid >= total_cents`. Full: status → `paid`, `paid_at`, GO Codes `paid` + issued, GO lines `done`. Any payment (deposit included): `if ($print && $o['stage'] === '') … stage = 'production'` (1790). Not print and full: `stage = 'complete'` (1791). Event `deposit_paid`/`paid` with what's left. Client receipt says for a deposit: "…$X is left, due before delivery." (1795).
- Section comment 1495-1496 (payment hold): "the order waits on a payment hold until our team records the money (recordPayment), which releases it."

### (e) Tax before approval
- `recalc` (2148-2155) sets `subtotal_cents` and `total_cents` to the same sum; no tax: `$pdo->prepare('UPDATE orders SET items = ?, subtotal_cents = ?, total_cents = ? WHERE id = ?')->execute([(int) $r['n'], $s, $s, $oid]);`
- `send` email (647): `"\n\nThe total is " . Orders::usd((int) $o['subtotal_cents']) . ' before tax. '`
- Order page (358): `<?php elseif (!$outside): ?><p class="go-note">Plus sales tax, worked out from your billing address when you approve.</p><?php endif; ?>`; Sales tax and Total rows only `if ($o['approved_at'])` (353-355).
- Approve docblock (915-918): "The client approves the order: every proof approved, their name, and the billing address (sales tax depends on it). The QuickBooks invoice is made now. Returns the order." Validation message 934: `'Add your billing address: sales tax depends on it.'`; staff: `'Fill in Bill to (street, town, state, ZIP) first: the invoice\'s sales tax comes from it.'`
- QuickBooks path: `tax_cents = $inv['tax_cents']`, `total_cents = $inv['total_cents']` (969-972). Shipping is sent to QuickBooks as one non-taxable line (961-962).
- Outside path (943-946): `// no QuickBooks invoice: the total is the lines (add a tax line if it's owed); our team records each payment` → `tax_cents = 0, total_cents = subtotal`.
- `QuickBooksPay::invoiceFor` in `mock` env uses "a made-up 8.6% tax" (QuickBooksPay.php:595-596).

### (f) refreshPrices
```
223    /**
224     * While an order is a draft (not sent yet), its catalog lines follow the catalog: a changed price or cost updates
225     * them; a price our team typed stays, only its catalog price (shown struck through) moves. Once sent, never.
226     */
227    public static function refreshPrices(PDO $pdo, int $oid): void
228    {
229        $o = Orders::get($pdo, $oid);
230        if (!$o || $o['status'] !== 'draft') return;
231        $changed = false;
232        foreach (Orders::lines($pdo, $oid) as $l) {
233            if ($l['product'] !== 'product' || !$l['product_id']) continue;
234            $p = Products::get($pdo, (int) $l['product_id']);
235            if (!$p || !self::runKnown($l)) continue;   // its size lost to a rename: leave its price alone until it's set again
236            $qty = self::runQty($l) / max(1, (int) $l['qty']);
237            $list = Products::priceFor($p, $qty, Products::pickedFrom($p, (string) $l['options']));
238            $own = (int) $l['unit_cents'] !== (int) $l['list_cents'];   // a price our team typed
239            $unit = $own ? (int) $l['unit_cents'] : $list['total'];
240            if (self::costed($pdo, (int) $l['id'])) $list['cost'] = (int) $l['cost_cents'];   // its COGS (Cost tab) is its cost: the catalog's doesn't replace it
241            if ($unit !== (int) $l['unit_cents'] || $list['total'] !== (int) $l['list_cents'] || $list['cost'] !== (int) $l['cost_cents'] || (int) $p['taxable'] !== (int) $l['taxable']) {
242                $pdo->prepare('UPDATE order_lines SET unit_cents = ?, list_cents = ?, cost_cents = ?, taxable = ? WHERE id = ?')->execute([$unit, $list['total'], $list['cost'], (int) $p['taxable'], (int) $l['id']]);
243                $changed = true;
244            }
245        }
246        if ($changed) self::recalc($pdo, $oid);
247    }
```
Called by `send` (634, before the status changes to `sent`, so it runs on the first send only) and on every load of the admin order page (website/admin/client-order.php:488, comment "a draft follows the catalog"). It also overwrites `taxable` from the product. A line's own price is detected only as `unit_cents !== list_cents`.

---

## 6. Not traced / open
- The DB defaults for `order_proofs.status` (inserts at 340 and 411 give none) and `order_lines.status`: the migrations have already run and been deleted, so they aren't in the mirror. The page and code treat new proofs as `waiting`.
- Where `order_lines.status = 'refunded'` is set (Orders.php refund code, not read in full).
- `QuickBooksPay::invoiceFor` internals: how tax is computed from the address, and whether `$email = true` makes QuickBooks email the invoice; `chargeCard`, `recordCardPayment`, `recordOtherPayment`, `guardReal`.
- `Orders::lines`, `Orders::number`, `Orders::sameEnv`, `Orders::isTest` bodies (only `record`, `money`, `settle`, `cancelUnpaid` were read).
- `Shipments::mode/destinations/estimateCents/shipped` (used by the page's Ship-to box, `shipping()` and `recalc`), `Collateral::fromOrder/onOrder/askReorder/forClient`, `QrIssue::claim/owned/issueShort`, `Promotions::check/linePrice/redeem`, `Pulse::openTicket`.
- The admin order page (`website/admin/client-order.php`) staff approve form's own fields and defaults (caller of `approve` at :103).
- `js/go-codes.js` was read only for the card form (lines 1-100).
- The `hasItems` docblock at CO:1063 describes money-in; the code under it only checks a column. The `ref()` docblock says "TRV-<year>-<order number>"; the code builds `Y-md-[S]NNNNN`. Both are reported as they stand.
