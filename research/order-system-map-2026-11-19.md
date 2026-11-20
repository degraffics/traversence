# The order system as it is (map, 2026-11-19)

Status: **map only, for the session manager's check and then Jason's.** No findings to fix, no proposals, no releases.
Order processing session. Live code: the mirror `degraffics/traverse-3way-sync` at "Sync: 2026-11-19 16:21 UTC"
(`sync-app/` = `app/`, `sync-storage/` = `website/`, `sync-trvgo/` = `trvgo/`); decisions on `main` at `afd0d26`.

Why: Jason (relayed by the session manager, 2026-11-19): "I feel like it is making decisions that arent in line with
what has been build already. The ordering system is massive and I dont want this new session half understanding it."
The earlier draft (`research/order-intake-2026-11-19.md`, PR #1074, a draft, not merged) is set aside until this map is
confirmed. §5 below corrects it.

**How it was read.** Every decision touching orders was read in full; every live file in the order system was read in
full, or where only part was read, the part is named. The full readings are in
[`order-system-map-2026-11-19/`](order-system-map-2026-11-19/), nine parts:

| Part | What it covers |
|---|---|
| [`d1-decisions-0035-0099-0108.md`](order-system-map-2026-11-19/d1-decisions-0035-0099-0108.md) | 0035, 0095, 0096, 0098, 0099, 0108 |
| [`d2-decisions-0101-0105-0107-0129.md`](order-system-map-2026-11-19/d2-decisions-0101-0105-0107-0129.md) | 0101 addendum by addendum (31 of them, 2026-10-16 to 10-30), 0105, 0106, 0107, 0129 |
| [`d3-decisions-0102-0142.md`](order-system-map-2026-11-19/d3-decisions-0102-0142.md) | 0102, 0109–0113, 0116, 0118–0124, 0127, 0128, 0130, 0142 |
| [`d4-decisions-0135-0172-and-others.md`](order-system-map-2026-11-19/d4-decisions-0135-0172-and-others.md) | 0135, 0139, 0141, 0145, 0150, 0154, 0155, 0166, 0167, 0169, 0172; then 27 other decisions with an order rule |
| [`c1-client-orders-and-client-page.md`](order-system-map-2026-11-19/c1-client-orders-and-client-page.md) | `ClientOrders.php` function by function; `order.php` (the client's page); `api/client-order.php`; `user/orders.php`; the state machine; every email |
| [`c2-admin-builder-and-workflows.md`](order-system-map-2026-11-19/c2-admin-builder-and-workflows.md) | the admin's order sections; `admin/client-order.php` (all 68 actions); `workflows.php`, `Workflow.php`; which paths go through the workflow |
| [`c3-money-ledger-quickbooks.md`](order-system-map-2026-11-19/c3-money-ledger-quickbooks.md) | `Orders.php`, `QuickBooksPay.php`, the webhook, `QbPull`, `Books`, GO Codes, one-offs, domains; every path where money moves |
| [`c4-fulfilment-catalog-pulse-jobs.md`](order-system-map-2026-11-19/c4-fulfilment-catalog-pulse-jobs.md) | `WorkOrders`, `VendorOrders` (4OVER), `Vendors`, `Shipments`, `Collateral`, `Products`, `PriceLists`, `wo.php`, `vendor.php`, order uploads, Pulse's order cases, the scheduled jobs |
| [`c5-business-sales-marketplace.md`](order-system-map-2026-11-19/c5-business-sales-marketplace.md) | a business's own sales: Marketplace, made to order, tickets, Quotes & invoices, storefronts; where they meet ours (they don't share tables) |

## 1. The shape of it

There are **two order systems**, kept apart on purpose (0098's three kinds of commerce; 0135; 0118):

1. **Traversence's own sales** (`orders`, `order_lines`, `order_ledger`, `order_events`; money through QuickBooks
   Payments; 0098, 0101, 0108). Six ways an order is made (§2): staff build a client order; the client asks for a
   reorder; QuickBooks pull; the GO Codes cart; one-offs; domains. Plans and packs are paid through the same cart but
   are subscriptions (`subscriptions`, `subscription_charges`), not `orders` (0108; c3 §5.5).
2. **A business's own sales** (`market_orders`, and the business's walled `b_biz_quotes`; money straight to the
   business's own Stripe or Square, or paid to it directly; "Money never passes through Traversence", 0118). Marketplace
   cart and Buy now, made to order, tickets, downloads, Quotes & invoices on `/q.php`. Storefronts on trvgo.us link into
   it (0139). It shares **no** order tables or order code with (1); the only shared code is the test-account switch
   (`QuickBooksPay::testHere()` inside `MarketPay::env()`, MarketPay.php:49-52) and the Notify kind `biz.order` (c5 §4).

The rest of this map is about (1), the system Jason's request is about; (2) is in c5.

**Client orders** (0101) are the heart of (1): an order our team builds for a client (most often printed work), with a
private page for the client to approve proofs and the order and to pay, then work orders to a vendor, shipping, and
completion. Its parts and their decisions:

| Part | Decision | Live code |
|---|---|---|
| Catalog: products, options with priced choices, run-size tiers with markup | 0101 (base, 10-16 "priced like the print vendor quotes", 10-26 "Rate is each item's"), 0130 | `Products.php` (`priceFor` 225, `parseOptions` 185); Back-Office → Products |
| Building the order | 0101; 0109 (it lives in Back-Office); 0129 (command strip); 0107 (item panel) | `admin/client-order.php`; `ClientOrders::create` 121, `addProduct` 250, `addCustom` 275, `addGoCode` 292, projects, parts |
| Proofs (versions, final approval signed with initials and two boxes) and files | 0101 (10-16, 10-26, 10-27, 10-28 "a proof's final approval, signed"); 0127 (one uploader); 0123/0124 (files in S3, owner folders) | `proofFromUpload` 397, `decideProof` 891, targets `order_proof`, `order_file`, `order_final` |
| Sending and the client's page | 0101 | `send` 629; `order.php` |
| Approval and the invoice | 0101 ("QuickBooks sees the order only when the client approves"), 0128 | `approve` 919 → `QuickBooksPay::invoiceFor` 590 |
| Paying: card, deposit, invoice later, wire/check/cash, outside, payment hold | 0098, 0101 (10-28: client chooses how to pay; wire details; payment hold; reminders) | `pay` 1023, `choosePay` 1645, `recordPayment` 1469, `releaseHold` 1755, `payWaitTick` 1674 |
| The ledger, refunds, credits, chargebacks | 0098 (fix, credit, then refund; credits 90 days) | `Orders.php` (`record`, `settle` 96, `credit` 150, `refundThroughQuickBooks` 194, `chargeback` 394), `admin/order.php`, webhook |
| Test money never real | 0145 | `QuickBooksPay::testHere` 92, `guardReal` 115, `guardStaging` 128 |
| Work orders to vendors; the vendor's page | 0101 (10-28), 0150 (30-day links) | `WorkOrders.php`, `wo.php`, `vendor.php` |
| Vendor orders (4OVER paste) and item statuses from them | 0106, 0107 | `VendorOrders.php` (`parse4over` 198, `match4over` 268, `import4over` 298, `syncItems` 344) |
| Shipping: delivery, per-item ship-to, manifests | 0101 (10-16 delivery and pass 2; 10-21 manifests) | `Shipments.php`, `setLineShip` 1397, `admin/manifest.php` |
| Statuses: stage, item statuses | 0101, 0105, 0107 | `STAGES` 43, `ITEM_STATUS` 31, `setStage` 664, `setItemStatus` 1091 |
| Collateral and reorder | 0101 (10-28 Collateral Center, 10-30 reorder) | `Collateral.php` |
| Clients are listings | 0101 (10-28) | `Clients`, `client_attach`, Back-Office → Clients |
| Completion | 0101 (10-30) | `confirmReceived` 746, `complete` 699, `gotItTick` 769 |
| Pulse: one case per order | 0105, 0101 (10-28), 0172 (amends the case drawer) | `Pulse.php` `orderSteps` 614, `stage` 645, `manage` |
| Workflow definition | 0109 Part C | `admin/workflows.php` 15-72 (`client_order`), `admin/Workflow.php` |
| Pull from QuickBooks | 0128 | `QbPull::pull` 97, `merge` 244 |

## 2. Every way an order is made (Traversence's own)

| # | Way | Who starts it | Record made | Money | Decision | Where (file:line) |
|---|---|---|---|---|---|---|
| 1 | **Client order built by our team** | staff, Back-Office → Orders → New client order (`/admin/backoffice/orders/new`) or Dashboard quick action | `orders` `draft`, `created_by` = staff, access token | after approval (§3) | 0101 | `client-order.php` `do=create` 52 → `ClientOrders::create` 121 |
| 2 | **Reorder** (same art, or with changes), from the Collateral Center, the order page, or **asked by the client** on Your orders or a completed order's page | staff; or client (`askReorder`, 5 an hour) making a draft for staff | new `draft` at today's prices, files copied; same art = the approved proof as a reuse to confirm | as 1 | 0101 (10-28, 10-30) | `Collateral::reorder` 183, `askReorder` 141; `user/orders.php`; `order.php` `do=reorder` 59-66 |
| 3 | **Duplicate / test copy** | staff | new `draft` (test: billed to staff, sandbox) | as 1 | 0101 (10-27), 0145 | `ClientOrders::duplicate` 141 |
| 4 | **Pull from QuickBooks** | manager, fin/ops (`backoffice.orders.pull`) | `pending`, `pay_mode='invoice'`, QuickBooks' totals; its payments recorded here, "never posted back" | from QuickBooks | 0128 | `QbPull::pull` 97, `merge` 244; sections.php 897-960 |
| 5 | **GO Codes cart** (member or guest) | buyer | `orders` + `order_lines` (`go_code`), `approved_at` now | invoice with tax → card here or QuickBooks' page | 0095, 0096, 0098 | `QrIssue::checkout` 311, `payCard` 371, `markPaid` 480 |
| 6 | **One-offs** (a 4K keep, a month of Pro) in the one cart | member | `pending` → `paid` | card on file, Sales Receipt, no invoice, no tax | 0154 | `OneOffs::pay` 50 via `CartPlans::pay` 104 |
| 7 | **Domains** in the one cart (purchase, renewal, auto-renew) | member / business | `pending` → `paid` → `complete` | wallet card; failed line refunded by itself | 0169 §1a | `AccountDomains::pay` 386 |
| — | Plans and packs (same cart) | member | `subscriptions`, `subscription_charges` (not `orders`) | card on file, Sales Receipt | 0108, 0154 | `Billing::start` 168, `charge` 416 |

**Print can't be bought by the client on the site.** The Shop's "Design, video, print and signs" card is "Ask for a
quote" by email (`shop/index.php:68`); a client's own way in is the reorder (2). Back-Office's "Quotes & deals" is
**coming** (`manifests.php:101`). (c3, c4 §1, c2 §1.)

## 3. The client order flow, end to end, as built

Each step: who acts, what the client sees, where money moves. Line numbers are in `ClientOrders.php` (CO) unless
named. Full detail: c1 §2-4, c2 §2-3, c3 §2-4, c4 §2-7.

| # | Step | Who | Order state | What the client sees | Money | Where |
|---|---|---|---|---|---|---|
| 1 | Start the order: name, email, company, phone; a member with that email linked; the client's business found (listing, or a private one made) | staff | `status=draft`, `pay_mode=full`, `qb_env` set, number (sandbox: S-n) | nothing (a draft link shows "This link doesn't work", order.php:26) | — | CO `create` 121; client-order.php `create` 52, `client_find`/`client_attach` 226-234 |
| 2 | Items: catalog product (options, run size; price from the catalog, or typed: the difference shows as a discount), custom line, GO Code, shipping line; projects; parts | staff | draft | — | — | CO `addProduct` 250 (an option not picked takes the product's first choice, 257-260; the builder's selects have no blank, c2 §2.5), `addCustom` 275, `addGoCode` 292, `addPart` 1966, `setOptions` 2013 |
| 3 | Terms: pay in full / deposit % / invoice later / outside; due terms; delivery (shipped, pickup, none); the order's Bill to and Ship to ("Same as Bill to"); per-item ship-to; manifests | staff | draft | — | — | `saveTerms`, `setField` 1215 (edit in place, `/api/admin/client-order.php`), `Shipments`, `setLineShip` 1397 |
| 4 | Proofs (a version per round, several files) and files (setup, print, artwork, native, reference, label…) | staff only | draft | — | — | targets `order_proof`, `order_file` (upload-targets.php:21, 35; staff-only gate 13-19) → `proofFromUpload` 397, `fileFromUpload` 475 |
| 5 | **Send**: prices refreshed from the catalog (typed prices kept); GO Codes get their proofs; email with the private link, "The total is $X before tax", the pay terms, the link's end date | staff (builder, Pulse "resend", or the workflow's `send`) | `sent`, `expires_at` | the email; the page: Bill to, Ship to (the order's, or "Your address, from the approval"), each item with its proof | — | CO `send` 629, `refreshPrices` 227 |
| 6 | The client opens it (`client_opened` pill for staff) | client | sent | "Look over each proof: Approve it, or tell us what to change. Then approve the order below." | — | CO `seen`; order.php 32, 274 |
| 7 | Each proof: **Approve** (the three terms, two boxes, initials: "Submit Final Approval") or **Make changes** (a note) | client | proof `approved` / `changes`; `review='changes'` | lightbox preview; "Changes asked for" | — | CO `decideProof` 891; order.php 41-42, 443-454 |
| 7a | Changes asked: staff emailed; a Pulse case "Changes asked on a proof"; a new version → `waiting` again; staff **Send again** | staff | sent | the new proof | — | `mailStaff` 912; Pulse.php:117; `proofFromUpload` 418 (tells staff to send again) |
| 8 | **Approve the order** (only once no proof is waiting or in changes): billing address (prefilled from the order, required unless outside), "Where should we ship it?" (only when some item ships to the client; "To the billing address above" ticked by default), name, "Email me as my order moves along", "I approve this order and its proofs as shown" | client; or staff "Approve for the client" (their form starts from the order's Ship to, box unticked) | `pending`, `approved_at`; lines `approved_at`; QuickBooks invoice with tax made (not for outside: payment hold) | "Approved. Thank you!"; the page becomes the Invoice; tax and total shown | invoice in QuickBooks (`requestid inv-<id>`) | CO `approve` 919 (ship-to from `shipFrom` 1450, written at 941; invoice 954-980); order.php 362-392; client-order.php 1155-1165 |
| 9 | **Pay**: card on the page (all, or the deposit then the rest); invoice later (QuickBooks emails it; pay by card here or on QuickBooks' page); wire (details behind an emailed code, ≥ $2,500), check, cash; "Tell us it's sent" | client; reminders by cron | `pending` until paid in full | "Pay the deposit" / "Pay the rest" / "Payment on its way" | card: QuickBooks Payments charge + payment on the invoice; ledger `deposit`/`charge` | CO `pay` 1023, `choosePay` 1645, `reportPay` 1741, `payWaitTick` 1674 (every 15 min) |
| 9a | Paid on QuickBooks' page | client | → `paid` when the invoice's balance is zero | — | ledger `charge` = what's left | qb-webhook.php 23-33 → `QuickBooksPay::paid` 718 (balance ≤ 0.004, 729) → `QrIssue::markPaid` 480 → CO `paidInQuickBooks` 1054 |
| 9b | Wire/check/cash/outside: staff record each payment (posted to the QuickBooks invoice), or **Release to production before payment** | staff | `pending` → `paid` | receipt email (unless "no receipt") | ledger; QuickBooks payment | CO `recordPayment` 1469, `releaseHold` 1755 |
| 10 | **Confirmation**: thank-you section on the page; receipt email "Thank you: order #N is paid" / "…your deposit…" with the amount, "in production" for print, the link; staff emailed; GO Codes issued when paid in full | system | `paid` (or `pending` with a deposit); `stage=production` when there's print (any money in) | thank-you, receipt | — | CO `received` 1774-1800; order.php 194-215 |
| 11 | Work orders: staff pick the vendor, items, files, ship-to, must-have date, cost (from COGS if blank); send by email from orders@ or hand the link | staff | `stage=ordered` ("Work order issued"; the client hears "sent to print") | stage email if progress emails are on | our cost on the work order | `WorkOrders::create` 331, `send` 481, `handLink` 455, `markOrdered` 427 |
| 11a | Or ordered on 4over by hand, then the 4over order page pasted back: vendor order and jobs, matched to the order and item; item statuses move forward from the vendor's words | staff | items move forward only | nothing (the sync sends no email, 0107) | vendor cost recorded | `VendorOrders::parse4over` 198 … `syncItems` 344; Pulse step and `admin/vendors.php` |
| 12 | The vendor accepts (name, job no.) or declines; marks production, shipped (tracking), ready | vendor on `/wo.php`; or staff for them (Pulse `wo_move`) | work order statuses; items forward; order → `shipped`/`ready` when every print item is out | stage emails if on; "on its way" when the last item goes out | — | `vendorMove` 587, `staffMove` 637, `moveItems` 661-679 |
| 13 | Item statuses by hand; **Send update** tells the client | staff | `item_status` | only when staff press Send update (and progress emails are on) | — | CO `setItemStatus` 1091, `sendUpdate` 1113 |
| 14 | Receive goods from the vendor (or reject on receipt); reprint | staff | work order `received`/`rejected` | — | — | `WorkOrders::receive` 682, `reprint` 702 |
| 15 | **Complete**: the client presses "Yes, I have it" (stars, words), or staff Complete the order (asked what's still open); reminder 72 h after out the door; "Something isn't right?" opens a ticket | client / staff / cron | `stage=complete`, items complete, archive | thank-you email with a reorder link | — | CO `confirmReceived` 746, `complete` 699, `gotItTick` 769, `problem` 813 |
| 16 | Money after: Fix, Credit (QuickBooks credit memo), Refund (card through QuickBooks Payments + refund receipt; or recorded), card dispute; refunds and credit memos made in QuickBooks arrive by webhook (one clear order: recorded; else the inbox) | admin/manager | `part_refunded`, `refunded`, `disputed` (from the ledger) | refund/credit email | QuickBooks | `admin/order.php`; `Orders::credit` 150, `refundThroughQuickBooks` 194, `fromQuickBooks` 242, `chargeback` 394; c3 §2.2 |
| — | Cancel (unpaid only), Reopen for changes (approved, nothing paid, no invoice, no work order out) | staff (manager for the workflow's cancel) | `void` / back to `draft`/`sent` | "This order was cancelled. Nothing was charged." | — | CO `cancel` 653, `reopen` 1002 |

**Three status layers** (c1 §3): `orders.status` (money: draft, sent, pending, paid, void, part_refunded, refunded,
disputed; `Orders::settle` 96 sets it from the ledger), `orders.stage` (production, ordered, shipped, ready, complete),
`order_lines.item_status` (about 25, in groups Before approval / Approved / Out the door / Exceptions / Housekeeping).
The workflow `client_order` (workflows.php:15-72) shows 11 stages read from those fields.

**Which moves go through the workflow** (c2 §3.3): only the Back-Office → Orders drawer's moves (`api.php:427-431`)
run `Workflow::move` with its checks (`proofs_approved` before production; `paid_in_full`, or pay mode invoice/outside,
before Shipped and Ready). The builder, the Pulse case, work orders and the vendor's page call `ClientOrders` directly;
the pipeline still shows them because the stage is read from the order.

**Emails** (c1 §4, c4 §2): 22 from `ClientOrders` (receipts, thank-you and "Did it arrive?" always; stage emails, the
"on its way" email and Send update only when the client keeps progress emails on); vendor emails from `WorkOrders`;
order mail goes through `Mailer`, not `Notify` (Notify's order kinds are the Marketplace's `biz.order`).

**Scheduled jobs** (c4 §8): `work_orders_tick` (hourly: not accepted by 9 am next business day, 24 h before due, at
due), `got_it_tick` (hourly, 72 h after out the door), `pay_wait_tick` (every 15 min, three reminders). All through
`Scheduler::JOBS`/`AUTO`.

## 3a. Artwork and files: the Gallery, the uploader, where files live, who sees them

(Added at Jason's question, 2026-11-19: "what about artwork/gallery file handling?") Decisions: 0099 (DAM), 0101
(10-26 uploads, 10-27 projects and file release, 10-28 Collateral Center), 0123/0124 (files in S3, owner folders), 0127
(one uploader), 0143 (the Gallery panel on every file input; 11-04 and 11-05 zips), 0151 (storage on Cloudflare R2).

| Part | Rule (decision) | As built (file:line) |
|---|---|---|
| **Who uploads an order's files** | Our team: proofs, artwork, reference, labels (0101, 0127) | Three targets, all staff-only behind `backoffice/orders.edit` (staff, fin/svc/sal): `order_proof` (upload-targets.php:21: PDF, PNG, JPG, WebP; a version per batch; `replace` swaps one file), `order_file` (35: art and document types; `kind` = setup, print 1-up, artwork, native, reference, document, estimate, invoice, label, manifest, `ClientOrders::FILE_KINDS` 1186), `order_final` (420: a creative item's print-ready file, after its proof is approved). Up to 1 GB, size checked first, the real type sniffed (`Uploads.php:120-131`). **The client has no upload on their order**: they send art outside the system (email), and our team adds it. |
| **The Gallery panel on an order** | Every file input opens the Gallery panel (`js/tv-art.js`); "Trv staff don't need individual gallery, all photos, videos, and file[s] will be assigned to an order" (0143, 11-05) | On an admin order page the panel's first tab is **This order's files**, then **This client's earlier orders** (up to 12, by account or email), to reuse approved art on a reorder; **Upload new** sends straight to the order, never to a staff member's Documents (`RecordFiles::forOrder` 26; `/api/stock.php?record=`; tv-art.js:40, 147). A chosen file travels as a reference (`_from_record` `order_proof:<id>` / `order_file:<id>`) and is copied server to server into its new place (`Uploads::gallery` → `fromRecord`), under the same `backoffice/orders.edit` rule (`RecordFiles::canOrders` 16). |
| **Where files live** | "Orders keep their own folder … our production and sales record", kept even if the client's account goes (0124); S3 (0123), then R2 (0151) | `orders/<order>/private/proofs/<id>.<ext>` and `orders/<order>/private/files/<id>.<ext>` (`ClientOrders::proofPlace` 618, file path 1366) through `Store`; served by `/api/order-proof.php` (redirect to storage, or streamed; non-images always as a download). |
| **What the client sees before paying** | 0099 DAM: watermarked proofs until paid (decided, 0099); 0101 (10-27): "Nothing is downloaded before the order is **paid in full**"; the proof waiting for their OK downloads (Jason, 10-28) | The proof is shown in the lightbox (`tv-proof.js`, pdf.js, a red dashed cut line); the proof **waiting** for their OK can be downloaded; other proofs and every final only once released (`clientMay` 2080). **No watermark** is applied anywhere in the order code (no match for "watermark" in `ClientOrders.php`, `order-proof.php`, `tv-proof.js`): 0099's watermark rule isn't built, and 0101's later rule (no download before paid, except the proof to approve) is what runs. |
| **Release after paying** | 0101 (10-27): files released by approval and payment; 0101 (10-28) Collateral Center: "only the approved, and allowed for USE released" (Jason) | `released` 2064: only when the order is paid in full; a print item's approved files; a creative item's finals when its project has no print. `Collateral::files(…, forClient)` (Collateral.php:93): the approved proof, and print-ready files once that order is paid in full; retired files never. |
| **The client's artwork afterwards** | 0101 (10-28) Collateral Center and Your artwork; 0143 (11-05, sixth zip): "add the Orders catalog to the client's gallery" | **Your artwork** on Your orders (`user/orders.php`, `Collateral::forClient` 117: only pieces released to them); the same pieces as the **Orders** catalog in the client's own Gallery (`RecordFiles::clientArt` 74; a business's Gallery shows its listing's pieces to its team). A client may use one elsewhere on the site (`RecordFiles::mayUse` 99); the files stay with their orders. |
| **Reorders reuse art** | 0101 (10-28, 10-30) | Same art: the approved proof goes along as a reuse (`order_proofs.reuse_of`), the client sees "The same art as before" and **Confirm reuse** with their initials; with changes: no proof or output files, a new proof round (`Collateral::reorder` 183). |
| **Files to the vendor** | 0101 (10-28): the work order carries "their files (print-ready files, else the current proof; artwork too)" | Each vendor has a file preference (`WorkOrders::FILE_PREF` 31: production setups, "we impose; they skip prepress", or print files 1-up, "they do prepress", or ask each time; `Vendors.php:64`); staff tick the files on the work order; the vendor downloads them on `/wo.php`; files changed after sending can tell the vendor (`setFiles` 519). Retiring a file a live work order uses is refused (ClientOrders.php:547-550). |
| **Checking artwork for print** | None in any decision | Nothing checks size, bleed, resolution, colour mode or fonts. The only "preflight" words: a production setup is for a vendor that "skip[s] preflight and prepress" (ClientOrders.php:1185, WorkOrders.php:30), i.e. our team prepares it. `Preflight.php` is the system's service check, not artwork. |
| **A business's own quotes** (separate system) | 0120 (proofs and files on quotes), 0122 | `BizFiles` on `/q.php`, in the business's own storage (c5). |

Open points for the map (not proposals): whether 0099's watermark rule still stands or was replaced by 0101's
download rule (no decision says so); and the client sending artwork (no rule either way; today only our team uploads).

## 4. Where decisions changed earlier decisions (order parts)

From the d-parts' "Changed by" lists; each line names the rule and the later one.

- **0098 → 0166**: "QuickBooks is the books … We don't keep a second double-entry ledger" → 0166 §2: the site's own
  double-entry ledger is the true record (accepted 2026-11-16; only the wallet, §6a, is built).
- **0098 → 0108**: subscriptions by QuickBooks invoices + AutoPay → "the card on file, charged by us"; a declined plan
  ends on Free.
- **0098 → 0101**: "Pay upon order approval, no holds" and deposits only on staff orders → 0101's per-order terms (in
  full, deposit, approve now and pay the invoice later), "Release to production before payment", and paid outside.
- **0098 §4a → 0096**: the 24-hour self-fix window for GO Codes dropped.
- **0099 → 0101**: the vendor portal / CSV / API hand-off → "Jason places the order with them himself", then work
  orders (`/wo.php`); in-house production and the Materials queue dropped (0099's own Decided).
- **0099 (S3) → 0123/0124 → 0151**: files in S3, owner folders; storage later moved to Cloudflare R2 (0151).
- **0101 work-order links 60 days → 0150**: 30 days (older ones keep 60).
- **0101 "Ordered from the printer" → 0101 (10-28)**: "Work order issued", the client told "sent to print".
- **0101 → 0128 / 0130**: pulled invoices never reopen the price; order lines keep a snapshot; COGS from the Cost tab.
- **0105 → 0172**: the case drawer's role and contents, the Operational Task Queue, severities (four become six).
- **0155 → 0169**: add-ons replaced by the App store (not built).
- Index drift: `decisions/index.md` lists 0102 and 0142 as "proposed"; both files say Accepted (0102 also built).

## 5. Intent before "gap": what the earlier draft called gaps

Checked against the decisions' words, the code's comments and the mirror's history (`git log -S` on the function).

| Behaviour | Evidence | Verdict |
|---|---|---|
| **The approval form ticks "To the billing address above"** with empty ship-to fields; ticked, `shipFrom` writes the billing address to `orders.ship_*` (order.php:375; CO 1454-1456, 941) | 0101 (10-16, Delivery): "Shipped: the client gives the ship-to address when approving ("to the billing address" by default)". Same day (pass 2 and the edit-in-place addendum): an order keeps its own **Ship to** ("with 'Same as Bill to'; the order's default destination"), shown to the client as "Ship to" (order.php:186, Sync 10-17 20:27 `a38d376`). `shipFrom` and the ticked box arrived together in Sync 10-17 10:23 (`66899d3`). The staff "Approve for the client" form starts from the order's Ship to with the box unticked (client-order.php:1160-1161); the client's form doesn't. | **Deliberate default (0101). Not reconciled with the later order Ship to. Unclear: ask Jason** whether an order with a Ship to should start the client's form from it. |
| **A printed item with no proof can be sent and approved** (`send` 629 makes proofs only for GO Codes; `approve` 926-927 blocks only proofs waiting or in changes) | 0101 base: building has "a proof per line"; "With every proof approved: billing address + name + 'I approve'". No line says Send must refuse an item without one; a reorder "With changes" starts "no proof … a new proof round". | **Unclear: ask Jason.** The decision describes a proof per line; nothing enforces it. |
| **An option not picked takes its first choice** (CO 257-260; the builder's selects have no blank) | 0101 (10-16, priced like the vendor): "The first choice is the standard one." | **Deliberate (0101).** Not a gap; the earlier draft was wrong. |
| **A part payment made on QuickBooks' own invoice page records nothing** until the balance is zero (`QuickBooksPay::paid` 729) | 0101 base: "Paying: the card form … for the amount due now (the deposit, then the rest; or the whole) … Paid on QuickBooks' page instead: the webhook or 'Check payment' records the rest." No comment in the code addresses a part payment there (c3 §6). | **Deliberate for "the rest". A part payment on QuickBooks' page isn't covered by any rule. Unclear: ask Jason.** |
| **Tax shown only at approval** (order.php:358) | 0101: "QuickBooks sees the order only when the client approves (an invoice then, as checkout does), not an Estimate before." | **Deliberate (0101).** |
| **Shipped / Ready set without the workflow's `paid_in_full` check** from the builder, Pulse, work orders and the vendor's page (c2 §3.3) | workflows.php:37-38 hold the check; 0101 (10-28): "Release to production before payment" for trusted clients; balance "the rest before delivery" (0101 Decision). No decision says which callers must use the workflow. | **Unclear: ask Jason.** |
| **`chargeFor` calls `guardReal` before looking the charge up** (QuickBooksPay.php:874), so a test order paid on QuickBooks' page has no refund from the site in production | Its own comment (109-114, Jason 2026-11-10): "refunds and credits only send money back, so a test copy that once took real money can still be refunded." | **Bug (evidence: the comment states the opposite of what the code does on that path).** |
| **A refund of printed work doesn't cancel the open work order** | 0098: refunds "pause what it paid for" for GO Codes (`applyRefund` revokes codes); nothing on work orders. | **Unclear: ask Jason.** |
| **Six builder actions have no form or caller** (`terms`, `line_save`, `addr_save`, `line_ship`, `options_save`, `stage`: client-order.php switch) | Replaced by edit in place (0101, 10-16 "the order edited in place"); no decision keeps them. | **Unclear: likely dead code** (CLAUDE.md: dead code is deleted, "checked against the live mirror"); not confirmed. |

## 6. What isn't known

- **The schema.** Migrations delete themselves once run (CLAUDE.md), so none are in the mirror: column defaults (e.g.
  `orders.status` on GO Codes checkout, new proof and line statuses), unique keys (`order_ledger.request_key`,
  `order_inbox (kind, qb_id)`) are inferred from the code, not seen.
- **Live permission overrides** (System Configuration → Permissions) are in the database; the access rules here are
  the code's defaults. Item pages (`/admin/backoffice/orders/<id>`) are reached before the router's section check is
  applied and rely on the page's own `tv_admin_require` (router.php:75-80, 132); its gating wasn't read.
- **Not read or only in part:** `QuickBooksPay::invoiceFor` internals (what the QuickBooks invoice email holds);
  `js/go-codes.js` (how the browser gets card tokens); `app/ui/command-strip.php`, `cost-tab.php`, `pack-form.php`,
  `qb-merge.php`; `/admin/api/vendors.php`, `/admin/api/costs.php`, `/admin/quickbooks.php`, `/admin/products.php`;
  `Costing.php`; `StagingGate::hold()`; `Clients`.
- **No caller found:** `Orders::byNo`, `QrIssue::voidOrder`, `QuickBooksPay::verify`, `Promotions::scanned`; Pulse's
  "Sent to the printer" alternative step is built but not used by the Pulse UI (c4 §9). `credit_used` is read but
  never written (credits aren't applied at checkout; 0098 listed that as not yet built).
- **Where a line becomes `refunded`**, and how `QuickBooksPay::paid` behaves for a voided invoice (QuickBooks'
  behaviour, not in code).
- **Marketplace** (c5 §5): nothing sets `market_orders.refunded`; subscription renewals have no webhook handler; a
  cancelled or expired order doesn't void the business's copy; a deposit paid directly leaves the order `awaiting`.
- **Live use.** The map is the code and the decisions; how many orders use each path (and whether a production
  purchase and refund were ever made: 0098's Record lists "a real $4.95 purchase and refund" as still to do) needs the
  live database.

## 7. Corrections to the earlier draft (`research/order-intake-2026-11-19.md`)

- "An option the builder misses is quietly set to its first choice" was presented as a bug: **0101 decided it** ("The
  first choice is the standard one").
- "The ship-to can be overwritten" was presented as a bug: the default is **0101's own rule**; what's open is how it
  meets the later order Ship to (§5).
- "Tax only at approval" was presented as a gap: **0101 decided it** (no Estimate before approval).
- R2's "tax estimate before approval", R1's "required option picks" and R1's ship-to change each went against a
  decision without saying so. The draft stays a draft (PR #1074) and is rewritten on top of this map only after
  Jason confirms the map.
