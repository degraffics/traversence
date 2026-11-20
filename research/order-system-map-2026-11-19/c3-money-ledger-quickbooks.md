# C3: The order system's money layer, as built (QuickBooks, the ledger, GO Codes, one-offs, domains, QuickBooks pull)

Source: live mirror `degraffics/traverse-3way-sync` as checked out at `/home/user/traverse-3way-sync`.
Paths below: `app/` = `sync-app/`, `web/` = `sync-storage/`. Read-only map, no opinions.

Files read in full: `app/lib/Orders.php` (515), `app/lib/QuickBooksPay.php` (1140), `web/api/qb-webhook.php` (41),
`app/lib/QbPull.php` (345), `app/lib/Books.php` (261), `app/lib/Promotions.php` (292), `app/lib/OneOffs.php` (105),
`app/lib/QrIssue.php` (668), `app/lib/CartPlans.php` (131), `web/admin/order.php` (317), `web/api/go-codes.php` (120),
`web/qr/cart.php` (169).
Read in part (order/payment parts only): `app/lib/AccountDomains.php` (1–120, 255–856, 1059–1248), `app/lib/Billing.php`
(1–42, 160–557), `app/lib/Wallet.php` (1–30, 130–184, 286–365), `app/lib/ClientOrders.php` (1023–1063, 1469–1493,
1774–1800, only where it calls into these files), `app/lib/Domains.php` (385–418, 468–482).
Payment-named libs (`ls app/lib | grep -iE "pay|refund|ledger|book|credit"`): `AiCredits.php`, `Bookings.php`, `Books.php`,
`ControlLedger.php`, `MarketPay.php`, `QuickBooksPay.php`. Headers and greps only for `AiCredits` (AI credit counting,
no money moves), `ControlLedger` (System Control Board worker-call ledger, not money), `Bookings` (Marketplace
`market_orders` reminders; "it never changes an order's money"), `MarketPay` (Stripe/Square for a business's own
Marketplace sales, `market_orders`, not the `orders` table). No `Payments*`, `Refund*` or `Ledger*` library for orders exists
beyond these.

---

## 1. Function inventory

### 1.1 `app/lib/Orders.php`

Header (lines 2–16), verbatim:
> "the order system (decisions/0098, 0099): one record for every order, whatever it sells, and a money ledger that is only ever added to."
> "order_ledger  money: charge, refund, credit_issued, credit_used, chargeback, chargeback_reversed, deposit. Each event has a request_key, so recording the same event twice does nothing."
> "QuickBooks keeps the books (decisions/0098): money moves there (the customer pays on QuickBooks' page; a refund to the card is made in QuickBooks); this records what happened, creates the QuickBooks records it can (a credit memo), and puts the order and what it delivered right (a refunded GO Code is switched off)."
> "The policy (Terms "Fix it, credit, then refund"): fix first, then a full or partial credit, a refund only when neither works; made-to-order lines are final once approved."

Constants: `KINDS` (22) ledger kinds → labels; `STATUS` (24) `draft, sent, pending, paid, void, part_refunded, refunded, disputed`; `CREDIT_DAYS = 90` (117, comment: "Terms, "Fix it, credit, then refund"; Jason, 2026-10-28: 90 days").

| Function (line) | What it does | Tables | QuickBooks calls | Callers |
|---|---|---|---|---|
| `ready` (27) | true if `order_ledger` exists | R order_ledger | – | web/admin/order.php:34, qb-webhook.php:35, ecommerce.php, section-rails.php:194 |
| `get` (34) | one order row | R orders | – | everywhere |
| `lines` (40) | lines ordered by `position` (if column exists) else id | R order_lines | – | many |
| `ledger` (48) | ledger rows with actor name | R order_ledger, users | – | web/admin/order.php:302, AccountDomains.php:1149 |
| `events` (55) | history rows | R order_events, users | – | web/admin/order.php:293 |
| `event` (62) | append history row (event ≤40 chars, note ≤600) | W order_events | – | many |
| `record` (72) | `INSERT IGNORE` a ledger row keyed by `request_key`; returns false on repeat; calls `settle` when new. Throws on unknown kind or cents ≤ 0 | W order_ledger → settle | – | QrIssue.php:497, OneOffs.php:92, AccountDomains.php:447/544/1151, QbPull.php:151/311, ClientOrders.php:1776, self (credit, applyRefund, fromQuickBooks, inboxApply, chargeback, chargebackResolved) |
| `money` (84) | sums by kind: `paid = charge + deposit`; `net = paid − refund − chargeback + chargeback_reversed`; `disputed = max(0, chargeback − chargeback_reversed)` | R order_ledger | – | order.php:103, ecommerce.php:173, client-order.php, order.php (public):97, ClientOrders (many), QbPull, Pulse.php:590, admin/Workflow.php:30, admin/tools.php:61 |
| `settle` (96, private) | status from money (see §4) | R/W orders | – | record, chargebackResolved |
| `disputeOpen` (109) | latest of events `chargeback/chargeback_won/chargeback_lost` is `chargeback` | R order_events | – | settle, chargeback, chargebackResolved, pauseLine, order.php:104 |
| `creditBalance` (123) | store credit: `credit_issued` minus `credit_used` across a member's (or guest email's) orders, oldest first, each lot expiring after 90 days | R order_ledger, orders | – | web/admin/order.php:107 only |
| `creditUntil` (142) | date 90 days on, for the email | – | – | credit |
| `credit` (150) | staff credit: paid order, same env, ≤ `net − credited`; key `credit-<oid>-<sha(cents|note|YmdHi)>`; `QuickBooksPay::creditMemo`; record `credit_issued`; event `credited`; emails customer | W order_ledger, order_events | creditmemo POST (via creditMemo) | web/admin/order.php:47 |
| `sameEnv` (165) | throws if order's `qb_env` ≠ current env (mock always passes; empty `qb_env` passes) | – | – | credit, refundThroughQuickBooks, QrIssue::payCard:387, ClientOrders::pay:1034 |
| `refundable` (175) | `max(0, net − credited)` | R | – | checkRefund, applyRefund, fromQuickBooks, inboxApply, order.php:60/253, ecommerce.php:118/122 |
| `checkRefund` (181, private) | reason required ("the policy is fix first, then credit"); cents ≤ refundable | – | – | refundThroughQuickBooks, refund |
| `refundThroughQuickBooks` (194) | card refund via QB Payments then Refund Receipt, then applyRefund; returns a warning if the receipt failed (event `needs_books`) | W via applyRefund | Payments `charges/{id}/refunds`; `refundreceipt` POST | web/admin/order.php:56 |
| `refund` (212) | records a refund already made by hand in QuickBooks (Refund Receipt no.) | W via applyRefund | – | web/admin/order.php:53 |
| `applyRefund` (221, private) | record `refund`; if nothing left refundable → all lines taken back, else `$revoke` lines; event `refunded`; email | W order_ledger, order_lines, qr_issued, short_codes, order_events | – | refundThroughQuickBooks, refund, fromQuickBooks, inboxApply |
| `fromQuickBooks` (242) | a RefundReceipt/CreditMemo made in QuickBooks → `ours`/`done`/`queued`/`recorded`/`inbox`/`skip` (see §3) | R order_ledger, order_inbox; W order_ledger, order_inbox | via candidates → `customerInvoices` query | qb-webhook.php:38, checkQuickBooks |
| `candidates` (292, private) | paid/part_refunded/disputed orders of a QB customer (by `qb_customer_id` or invoice ids), same env, ≤50 | R orders | `select Id from Invoice where CustomerRef` | fromQuickBooks |
| `isTest` (303) | `qb_env === 'sandbox'` | – | – | no, number, ClientOrders::ref |
| `no` (306) | display number: sandbox `S-<test_no>`, else `order_no`, else id | – | – | many |
| `number` (313) | assigns next `order_no` or `test_no` once the mode is known | R/W orders | – | QrIssue.php:358, OneOffs.php:66, AccountDomains.php:432, ClientOrders.php:130/948/976 |
| `byNo` (324) | find by "12", "#12", "S-3" | R orders | – | no caller found by grep |
| `hasCol` (333) | column probe on `orders` | – | – | number, byNo, candidates, QrIssue::payCard:377 |
| `inbox` (340) | open inbox rows for current env (fallback without env) | R order_inbox | – | ecommerce.php:93, Pulse.php:111, admin-dashboard.php:223 |
| `inboxItem` (351) | one inbox row | R order_inbox | – | inboxApply, order.php:152 |
| `inboxApply` (358) | staff places an inbox refund/credit on an order; keys `qb-rr-<id>` / `qb-cm-<id>`; marks inbox `done` | W order_ledger, order_inbox | – | web/admin/order.php:63 |
| `inboxDismiss` (376) | inbox row → `dismissed` | W order_inbox | – | web/admin/ecommerce.php:59 |
| `checkQuickBooks` (382) | last 45 days of RefundReceipt and CreditMemo through `fromQuickBooks` (missed webhooks) | – | `select * from RefundReceipt/CreditMemo where TxnDate >= …` | web/admin/ecommerce.php:41 |
| `chargeback` (394) | event `chargeback` first, then record `chargeback` (key `chargeback-<oid>-<sha(ref|cents|YmdHis)>`), pause GO Codes | W order_events, order_ledger, short_codes | – | web/admin/order.php:67 |
| `chargebackResolved` (404) | won: event `chargeback_won`, record `chargeback_reversed` (key `chargeback-won-<oid>-<YmdHis>`), unpause. Lost: event `chargeback_lost`, settle, take back every line | W | – | web/admin/order.php:70/71 |
| `fix` (423) | staff correct a GO Code line (`QrIssue::staffFix`), optional title; event `fixed` | W order_lines, qr_issued, order_events | – | web/admin/order.php:43 |
| `pauseLine` (438) | pause/resume one GO Code line (refused to resume while a dispute is open) | W short_codes, order_events | – | web/admin/order.php:86 |
| `note` (451) | staff note event | W order_events | – | order.php:89, Pulse.php:722 |
| `cancelUnpaid` (460) | `pending/draft/sent` → `void`; lines `cancelled`; pending GO Codes back to cart; promo use released; event says "void invoice … in QuickBooks too" (no QB call) | W orders, order_lines, qr_issued, promotion_uses, promotions, order_events | none | order.php:82, QrIssue::cancelMine:415, ClientOrders.php:659 |
| `paidOrder` (473, private) | throws unless `money.paid > 0` | – | – | credit, refund*, inboxApply, chargeback |
| `takeBack` (482, private) | lines → `refunded`; GO Code → `QrIssue::revoke` | W order_lines, qr_issued, short_codes | – | applyRefund, chargebackResolved |
| `pause` (498, private) | pause/restore every non-refunded GO Code line | W short_codes | – | chargeback, chargebackResolved |
| `tell` (505, private) | email the customer (never fails the action) | – | – | credit, applyRefund |
| `usd` (514) | money format | – | – | many |

### 1.2 `app/lib/QuickBooksPay.php`

Header (2–11), verbatim:
> "A checkout becomes a QuickBooks invoice: QuickBooks' Automated Sales Tax adds the tax from the buyer's address, the buyer pays on QuickBooks' own secure payment page (its invoice link: card, and bank transfer or Apple Pay when the account offers them), and QuickBooks tells us it's paid (api/qb-webhook.php), or we ask it (paid()). No card number ever touches our server, and every sale is in the books with its tax."
> ".env: QB_CLIENT_ID, QB_CLIENT_SECRET (the Intuit developer app), QB_ENV (production | sandbox | mock, a local test with no network), QB_WEBHOOK_TOKEN (the app's webhook verifier token), optionally QB_INCOME_ACCOUNT_ID."

Mode/environment:

| Function (line) | What it does | Callers |
|---|---|---|
| `env` (36) | order of precedence: `QB_ENV=mock` → `mock`; `testHere()` → `sandbox` (comment: "a test account signed in: never live QuickBooks, whatever the switch says (Jason, 2026-11-10)"); `useEnv` forced value; `qb_settings.mode`; `QB_ENV=sandbox` → sandbox; else `production` | ~30 sites (grep list in work notes: ClientOrders 129/972/1484, Books 59/61/108, Orders 168/287/296/344, QrIssue 357, Wallet 159/300/337/360, AccountDomains 417, OneOffs 63, admin/quickbooks.php, order.php, ecommerce.php, client-order.php, order.php, api/plans.php, api/domains.php, api/go-codes.php, ControlSensor, Preflight) |
| `useEnv` (47) / `forcedEnv` (50) / `withEnv` (135) | force a mode for one request / read it / run a closure in a mode | useEnv: Billing 427/429/439, Wallet 298/302/362/363, OneOffs 58/95, admin/quickbooks.php:43, qb-webhook.php:20; withEnv: ClientOrders 150/1484, modeStatus |
| `mode` (53) / `setMode` (64) | read/write `qb_settings.mode` | setMode: web/admin/quickbooks.php:86 |
| `key` (76) | env key for a mode (`QB_*` / `QB_SANDBOX_*`, legacy fallback) | internal |
| `testHere` (92) | true on staging (`Staging::here()`), or when the signed-in user or staff login is a test account; false in CLI or with no session. Comment: "On the staging copy it's true for everyone (MarketPay and Domains ask this too)." | env; Staging.php:12, StagingGate.php:9, Wallet 336/347, AccountDomains 93, Domains 77, MarketPay 51 |
| `guardReal` (115) | calls `guardStaging()`; if `env() !== 'production'` returns; else throws if the order is `qb_env = sandbox`, `bill_name` starts `TEST · `, or its `user_id` is a test account | internal (invoice 549, invoiceFor 592, chargeCard 802, recordCardPayment 836, recordOtherPayment 860, chargeFor 874); external: AccountDomains.php:436 only |
| `guardStaging` (128) | throws if `StagingGate::liveKeys()` finds a live payment key in staging's .env | only from guardReal |
| `modeStatus` (143) | keys/connection/company/cards/lapsed per mode | web/admin/quickbooks.php:165 |
| `envRaw` (155), `configured` (165), `ready` (167), `connection` (172), `sortLegacy` (181) | connection row (id 1 production, id 2 sandbox) | internal, Books::requirements |
| `open` (194) | buying open: mock yes; sandbox only for staff (`staffHere`); else configured + refresh token not expired | QrIssue::checkout:315, api/go-codes.php:49 |
| `staffHere` (203) | `TwoStep::isStaff` on current user | open; ClientOrders 880, web/order.php:33, hub/index.php:253, api/content-tags.php:20, wo.php:58 |

Connection, tokens, HTTP (no money): `sealKeys` (218), `seal` (227), `realm` (237, comment quotes Intuit's review: "customer IDs … must be stored securely"), `realmSealable` (244), `unseal` (254), `redirectUri` (266), `endpoint` (272, discovery doc cached a day), `authUrl` (295; caller admin/quickbooks.php:37), `http` (303: every call passes `StagingGate::hold()` first, logs, `ServiceHealth::seen`), `faultText` (327), `logPath` (341), `logWebhook` (344), `logNote` (351), `log` (356: `app/storage/logs/quickbooks.log`, company id masked, ~2 MB rotation), `httpRetry` (369: once more after 2 s on 429/5xx/no answer), `tokenCall` (377), `store` (388), `connect` (400; admin/quickbooks.php:47; GET `companyinfo/{realm}`, then `itemId`), `disconnect` (413; admin/quickbooks.php:92; POST revocation), `stillConnected` (429; admin/quickbooks.php:57; deletes the connection on 400/401/403), `ping` (443; ControlSensor.php:117), `access` (451; refresh on expiry; `invalid_grant` → `markReconnect`), `needsReconnect` (471), `markReconnect` (476).

| Function (line) | What it does | QuickBooks API calls | Callers |
|---|---|---|---|
| `call` (483) | one Accounting API call; `requestid` query param when given (comment: "QuickBooks does a create only once per id, so a retry or a double click never makes a second invoice or credit memo (decisions/0098)"); 401 → fresh token, retry once | `/v3/company/{realm}/…?minorversion=75` | internal |
| `query` (505) | `GET query` | query | internal |
| `itemId` (512) | the "GO Code" Service item (Taxable, UnitPrice 4.95), found or created; cached in `qb_connection.item_id` | `select Id from Item where Name='GO Code'`; maybe `select Id from Account where AccountType='Income'`; POST `item` | connect, invoice, invoiceFor (when item name is 'GO Code'), refundReceipt, creditMemo |
| `customerId` (533, private) | find/create Customer by DisplayName `"<name> · TRVGO <uid>"` or `"<name> · <email>"` | `select Id from Customer where DisplayName=…`; POST `customer` | invoice, invoiceFor, creditMemo, salesReceiptFor, salesReceipt |
| `invoice` (547) | GO Codes invoice: guardReal; mock: 8.6% tax, `/qr/?mockpay=`; one taxable line per code (`TaxCodeRef TAX`), `AllowOnlineCreditCardPayment` + `AllowOnlineACHPayment`, requestid `inv-<oid>`; gets `InvoiceLink`; if empty, POST `invoice/{id}/send` then link again; throws if still no link; GET invoice for tax/total | POST invoice; GET invoice?include=invoiceLink; POST invoice/{id}/send; GET invoice/{id} | QrIssue.php:346 |
| `invoiceFor` (590) | client-order invoice (decisions/0101): guardReal; per-line item = 'GO Code' item, or the product's tagged item (`Books::itemFor`), or `namedItem` (then `SystemTags::set('qbo.item', …)`); `TAX`/`NON` per line; requestid `inv-<oid>`; `$email` → POST send | POST invoice; POST send; GET link; GET invoice | ClientOrders.php:964 |
| `invoiceByNumber` (636) | read-only invoice by DocNumber + Customer + up to 10 Payments (amount applied to this invoice); mock returns invoices 112906 (paid) / 112907 (open) | query Invoice; GET customer/{id}; GET payment/{id} | QbPull.php:25 |
| `items` (675) | read-only Item list (paged 500) | query Item | Books.php:117 |
| `namedItem` (696, private) | find/create a Service item by name | query Item; maybe query Account; POST item | invoiceFor, salesReceiptFor, salesReceipt |
| `link` (710, private) | `InvoiceLink` if https | GET invoice/{id}?include=invoiceLink | invoice, invoiceFor |
| `paid` (718) | the "is it paid" check (see §3): skips other mode; mock = `note === 'mock-paid'`; GET invoice; writes `tax_cents`, `total_cents`; true if `TotalAmt > 0 && Balance <= 0.004` | GET invoice/{id} | qb-webhook.php:29, QrIssue::refresh:469, web/admin/order.php:73 |
| `mark` (735) | `[TRV:<key>]` tag | – | recordCardPayment, recordOtherPayment, refundReceipt, creditMemo, salesReceiptFor, salesReceipt |
| `isOurs` (737) | PrivateNote contains `[TRV:` | – | Orders::fromQuickBooks:248 |
| `canRefundCards` (740) | connection scopes include `com.intuit.quickbooks.payment` (mock true) | – | chargeCard, refundCard, saveCard; admin/quickbooks.php:206; admin/order.php:250 |
| `paymentsApi` (747) / `payCall` (753) | Payments API `/quickbooks/v4/payments/…` with `Request-Id` header (comment: "Intuit does it once per id (no double refund)") | Payments API | chargeCard, refundCard, refundCharge, chargeSaved |
| `orderCustomer` (771) | `orders.qb_customer_id`, else from the invoice (and saved) | GET invoice/{id} | recordCardPayment, recordOtherPayment, chargeFor, refundReceipt |
| `tokenUrl` (788) | where the browser posts the card for a one-time token | – | web/order.php:545, api/plans.php:77, api/domains.php:59, api/go-codes.php:50 |
| `chargeCard` (800) | guardReal; amount = `$cents` or `total_cents` (comment: "$cents: a deposit or the balance (client orders)"); mock declines on token containing "decline"; needs payment scope; `access()` first; POST `charges` capture=true, Request-Id = `$key`; 4xx → "The card wasn't accepted … Nothing was charged."; 5xx/no answer → "Don't pay again: refresh this page in a minute."; status must be CAPTURED/SETTLED | POST payments/charges | QrIssue.php:390, OneOffs.php:68, ClientOrders.php:1038 |
| `recordCardPayment` (834) | guardReal; QB Payment on the invoice, `TxnSource IntuitPayment`, `CCTransId = chargeId`, requestid `$key`; on refusal a plain Payment with requestid `$key.'p'`; returns `linked` flag | POST payment (×2 on fallback) | QrIssue.php:400, ClientOrders.php:1043 |
| `recordOtherPayment` (858) | guardReal; QB Payment for a wire/check/cash with the client's reference (comment: "so the invoice shows paid (Jason, 2026-10-28)") | POST payment | ClientOrders.php:1484 (inside `withEnv(order's qb_env)`) |
| `chargeFor` (871) | the card charge that paid the invoice: `orders.charge_id`, else (guardReal first) scan up to 100 Payments for the customer for a CCTransId linked to the invoice | query Payment | refundCard only (no external caller) |
| `refundCard` (894) | mock id; needs payment scope; `chargeFor`; POST `charges/{id}/refunds` with Request-Id `$key`; DECLINED/CANCELLED throws | POST payments/charges/{id}/refunds | Orders.php:201 |
| `refundReceipt` (916) | RefundReceipt for the customer from `QB_REFUND_ACCOUNT_ID` or the first Bank account; tax split pro rata from the order (`tax = round(cents * tax_cents / total_cents)`); line on the GO Code item; `PaymentRefNum` = card refund id; tagged `[TRV:key]`; requestid `$key` | maybe query Account; POST refundreceipt | Orders.php:203 |
| `fetch` (940) | GET one entity | GET {entity}/{id} | qb-webhook.php:38, invoiceByNumber |
| `recent` (947) | last N days of an entity (≤200) | query | Orders::checkQuickBooks:386 |
| `customerInvoices` (955) | invoice ids for a customer (≤200) | query Invoice | Orders::candidates:295 |
| `verify` (965) / `verifyEnv` (971) | HMAC-SHA256(body, verifier token) base64 compared with `intuit-signature`, tried for production then sandbox | – | verifyEnv: qb-webhook.php:18; verify: no caller found |
| `creditMemo` (987) | CreditMemo, non-taxable, on the GO Code item, for the customer found/created from the order's bill fields; tagged; requestid. Comment: "QuickBooks applies open credits to the customer's next invoice when "Automatically apply credits" is on (Settings → Advanced → Automation)." | customer lookup/create; POST creditmemo | Orders.php:158 |
| `refundsIn` (1004) | `[entity, id, operation]` for RefundReceipt/CreditMemo in a webhook | – | qb-webhook.php:35 |
| `invoicesIn` (1016) | invoice ids from Invoice entities and from Payments' LinkedTxn | GET payment/{id} per Payment entity | qb-webhook.php:24 |
| `saveCard` (1043) | token → card on file `customers/{wallet}/cards/createFromToken`, Request-Id; returns [id, "Visa ••1111", "YYYY-MM"] | POST customers/{wallet}/cards/createFromToken | Billing.php:310, Wallet.php:140 |
| `refundCharge` (1065) | refund any charge id (comment: "a domain we couldn't register after charging for it, decisions/0139") | POST payments/charges/{id}/refunds | AccountDomains.php:543/1150, Domains.php:411 |
| `chargeSaved` (1074) | charge a card on file, `recurring: true`; 4xx → code 402; else 503 | POST payments/charges (cardOnFile) | Billing.php:428, Wallet.php:301/305 |
| `salesReceiptFor` (1098) | SalesReceipt, each line on its own item or a fallback "Traversence plan" item, all `NON` | customer; maybe namedItem; POST salesreceipt | AccountDomains.php:558 |
| `salesReceipt` (1122) | SalesReceipt on "Traversence plan" item; taxable only if `QB_PLAN_TAXABLE` | customer; namedItem; POST salesreceipt | Billing.php:437, OneOffs.php:90 |

### 1.3 `web/api/qb-webhook.php` – see §3.

### 1.4 `app/lib/QbPull.php`

Header (2–6): "a QuickBooks invoice into a Traversence order (decisions/0128, step 2): an order that started in QuickBooks. The invoice is the source of every number (lines, prices, tax, total, payments); … Read only in QuickBooks: a payment made there is recorded here, never posted back. preview() shows it first; pull() reads the invoice again (never trusting the page) and builds the order."

| Function (line) | What it does | Tables | QB calls | Callers |
|---|---|---|---|---|
| `preview` (21) | reads invoice; computes `paid = total − Balance`; lines (Sales and Discount lines; Discount negative); each item staged (`Books::stageItem`) and classified product/charge/open; client by tag `qbo.customer` or `Clients::find`; `existing` order by tag `qbo.invoice` or `invoice_id + qb_env`; `refers` = orders with that `invoice_no` and no `invoice_id` | R products, orders, system tags, clients; W qbo_items | invoiceByNumber | app/admin/sections.php:922, pull, mergePlan |
| `pull` (97) | refuses if existing or refers; email required; decides open items (`Books::decide`), preview again; in a transaction: `ClientOrders::create`; inserts lines (`product` with `run_qty`, or `shipping`/`custom` tagged `charge`); sets order `status='pending'`, approved/sent dates, invoice ids, `qb_env`, `pay_mode='invoice'`, totals from QB; event `pulled_note` if total ≠ lines+tax; records each QB payment (`charge` if ≥ left else `deposit`, key `qbpull-<env>-<invId>-<payId>`, method `quickbooks`); if `Balance <= 0` sets `status='paid'`, `paid_at`; tag `qbo.invoice`; commit. Then client attach, `qbo.customer` tag, `client_listings.qb_customer_id`; event `pulled`; `Collateral::fromOrder` | W orders, order_lines, order_ledger, order_events, system tags, client_listings | via preview | app/admin/sections.php:911 (gate `Access::can('backoffice.orders.pull', min manager, depts fin/ops)`) |
| `runOf` (186) | `ClientOrders::runQty` | – | – | mergePlan |
| `mergePlan` (193) | invoice lines paired to an order's lines by score (product +50, charge +10, similar_text ×0.4, run +15; ≥30); group rows (one order line split into several invoice lines); `only` = order lines not on the invoice; `paid_here`; `gap` | R | via preview | app/ui/qb-merge.php:25, merge |
| `merge` (244) | decides items, plan again; transaction: update grouped and paired lines, insert new, drop `only` lines (kept only if unpaid and `keep[id]`), recompute subtotal; order `pending` if draft/sent, invoice ids, `qb_env`, `pay_mode='invoice'`, totals; records `owed = max(0, inv.paid − paid_here)` as charge/deposit (key `qbmerge-<env>-<invId>-<oid>-<inv.paid>`); if paid and nothing kept: `pending`→`paid`; tag; events `merged`, `price_gap`; Collateral | W orders, order_lines, order_ledger, order_events | via preview | app/ui/qb-merge.php:5, web/admin/client-order.php:181 |
| `insertLine` (331, private) | add a product run or a charge line | W order_lines | – | merge |

Comment block (178–183), verbatim: "Unpaid: one order, linked to the invoice; lines that match take the invoice's run and price (their proofs, files and work orders stay), lines only on the invoice are added, lines only on the order are kept or dropped (your choice: a kept line isn't on the invoice yet: add it in QuickBooks and pull again). Paid: the payment rules, so the order is rebuilt to match the invoice exactly (lines only on the order are dropped), the QuickBooks payment is recorded, and the site carries on from there. Pulling again refreshes it the same way."

### 1.5 `app/lib/Books.php`

Header (2–7): "who does what with the money (decisions/0128), and mapping QuickBooks' items to our products. Three roles … the processor (the payment gateway at checkout, only that), the bookkeeper … and order/CRM management. Tax and history each have one authority … Reading QuickBooks' items writes nothing there; mapping never changes a past order's lines."

| Function (line) | What it does | Tables | QB | Callers |
|---|---|---|---|---|
| `ROLES` (16) | processor `qbo`/`offline` (default qbo); books `trv`/`qbo` (default qbo); orders `trv`/`other` (default trv); tax `books`/`trv` (default books); history `books`/`trv` (default trv) | – | – | – |
| `role` (26) | tax role follows `Commerce::taxSource` (quickbooks → books) unless books = trv; history = trv when books = trv | R qb_settings | – | admin/sections.php:853, requirements, snapshot |
| `setRoles` (38) | saves roles; tax written to `Commerce` setting `tax_source` (`quickbooks` or `rates`) | W qb_settings, commerce settings | – | admin/sections.php:836 |
| `requirements` (56) | per-role readiness lines (comment: "A role isn't functional until its line is ok (Jason, 2026-10-28: e-commerce requirements must be set and managed).") | R | – | admin/sections.php:859 |
| `snapshot` (77) | roles at the moment (written into QbPull's `pulled` event text) | – | – | QbPull.php:171 |
| `setting` (83) / `put` (88) | qb_settings read/write | qb_settings | – | internal |
| `ensure` (95) | `CREATE TABLE IF NOT EXISTS qbo_items` at runtime | DDL | – | internal |
| `env` (108) | `QuickBooksPay::env()` | – | – | QbPull |
| `readItems` (111) | reads all QB items into `qbo_items`; carries `products.qb_item_id` into tags | W qbo_items, system tags | items() | admin/sections.php:837 |
| `items` (133) | staged items with state and suggestion (charge words, ≥75% name match) | R | – | admin/sections.php:864, progress, acceptSuggestions |
| `progress` (162) | counts | R | – | requirements, admin/sections.php:864 |
| `item` (171), `setRole` (180) | private | qbo_items | – | – |
| `decide` (190) | link / make (product `QB-<ref>` sku) / charge / leave / clear | W system tags, qbo_items, products | – | admin/sections.php:839, QbPull 110–112, 252–254 |
| `acceptSuggestions` (228) | takes product/charge suggestions | W | – | admin/sections.php:838 |
| `stageItem` (240) | `INSERT IGNORE` an item seen on an invoice | W qbo_items | – | QbPull.php:63 |
| `itemState` (248) | role + tagged products | R | – | QbPull.php:64 |
| `itemFor` (256) | our product → QB item ref in this mode | R system tags | – | QuickBooksPay.php:608, AccountDomains.php:557 |

### 1.6 `app/lib/Promotions.php` (order parts; whole file read)

Header (4–16) includes: "The profit floor (0108): no discount takes a sale below 65% profit. … A percentage beyond the floor can't be made for plans or GO Codes, and on a product line it's capped at the floor (the line says so)."

| Function (line) | Order-relevant behaviour | Tables | Callers |
|---|---|---|---|
| `check` (181) | validates a code for `plans` / `cart` (GO Codes) / `quote` (other kinds); one use per account or email | R promotions, promotion_uses | api/go-codes.php:40/92, api/plans.php:85/112, ClientOrders.php:1844/1859 |
| `linePrice` (213) | `[unit after, off, capped]`; off capped so profit ≥ 65% (cost-based, else `maxPercent`) | – | QrIssue::withPromo:296, ClientOrders.php:1830 |
| `redeem` (225) | inserts `promotion_uses` (with `order_id`/`sub_id`, `off_cents`), `uses + 1`; lead note | W promotion_uses, promotions | QrIssue.php:340, ClientOrders.php:1864, api/plans.php:105/118 |
| `release` (234) | deletes uses for an order, `uses − 1` | W | Orders::cancelUnpaid:467, QrIssue::checkout:351 (invoice failed), QrIssue::voidOrder:666 |
| others | `ready` 28, `canMake` 31, `pct` 38, `maxPercent` 41, `clean` 48, `create` 53 (admin/promotions.php:38), `date` 97, `freshCode` 99, `forLeads` 110 (admin/promotions.php:47), `issueGoCode` 128, `pageUrl` 138, `setStatus` 140, `get` 148, `byCode` 156, `targets` 164, `offer` 167, `usedBy` 201, `scanned` 244 (no caller found by grep for `Promotions::scanned(`), `leadNote` 255, `leadCodes` 278, `all` 288 | | |

### 1.7 `app/lib/OneOffs.php`

Header (2–6) verbatim: "one-off purchases (decisions/0154 §5): keep a single video in 4K ($0.99), without a plan. In the one cart (cart_plans, owner_type 'media'), paid with the cart's card entry (one more one-time token): an order of its own (orders + order_lines), the card charged on it (QuickBooksPay::chargeCard, which refuses real money for a test), a Sales Receipt in the books like a plan's (no invoice: nothing to ship, nothing to tax by address), and then what was bought happens. A test account pays in the sandbox only (decisions/0145). GO Codes' checkout is untouched."

| Function (line) | What it does | Callers |
|---|---|---|
| `KINDS` (12) | `keep_4k` 99¢; `pro_month` 995¢ (comment: "Billing::oneMonth, nothing renews") | – |
| `keep4kWhyNot` (15), `whyNot` (30) | eligibility | addKeep4k, addProMonth, pay |
| `addProMonth` (37), `addKeep4k` (43) | `INSERT IGNORE cart_plans` (`oneoff`/`media`) | api/go-codes.php:67 / :62 |
| `pay` (50) | see §5.2 | CartPlans.php:111 |

### 1.8 `app/lib/AccountDomains.php` (order parts)

Header (2–25) includes: "Paying is an order (0098): the card from the account's wallet (0166 §6a) is charged first, then Name.com registers it in the buyer's name with WHOIS privacy; one that can't be registered is refunded." and "Its own switch (Jason, 2026-11-17: "finding and buying was to be its own process"): DOMAIN_SALES_MODE … No real money in a test: DOMAIN_SALES_MODE=test, a test account, or the staging copy go to Name.com's sandbox and pay in QuickBooks' sandbox; DOMAIN_SALES_MODE=live with NAMECOM_ENV=sandbox refuses to sell."

| Function (line) | What it does | Callers |
|---|---|---|
| `open` (74) | sales open/why not; live + Name.com sandbox refuses | addToCart, pay |
| `sandboxed` (89) | test mode, `QuickBooksPay::testHere()`, or a test owner | pay, inPlace |
| `inPlace` (98, private) | runs in `Domains::asSandbox` + `Wallet::asSandbox` | many |
| `canPay` (114) | Domain (edit) + wallet manage | addToCart, pay, mine |
| `addToCart` (259) | price re-quoted (never above shown); 18+; registrant contact; inserts `domain_cart` + `cart_plans` (`oneoff`, `domain_purchase`) | api/domains.php:82 |
| `renewToCart` (292) | renewal into cart | api/domains.php:92 |
| `renewPrice` (305, private) | Name.com renewal cost + `DOMAIN_MARKUP_CENTS` (default 500); 503 if no cost ("never a renewal at our markup alone") | renewToCart, renewNow, autoRenew |
| `setCartCard` (327) | `wallet:<id>` or `new` for the cart group | api/domains.php:85 |
| `cartGroups` (339) | cart domain groups for CartPlans | CartPlans.php:91 |
| `payGroup` (367) | cart group → `pay` | CartPlans.php:112 |
| `pay` (386) | see §5.3 | payGroup, renewNow:716, autoRenew:833 |
| `hasOnly` (470), `anySandbox` (472) | helpers | pay |
| `register` (479) | Name.com POST `/core/v1/domains`, X-Idempotency-Key `tv-dom-<oid>-<domain>[-sb]`; writes `account_domains`; assigns wallet card; DKIM | pay, pulseDo |
| `renewAt` (519) | Name.com `:renew`, X-Idempotency-Key `tv-dom-renew-<oid>-<domain>` | pay, pulseDo |
| `lineFailed` (532, private) | purchase: refund that line's cents; renewal: line `approved`, Needs attention | pay |
| `receipt` (553, private) | `salesReceiptFor` in sandbox when forced; failure → event `needs_books` | pay |
| `mailBuyer` (562, private) | receipt email | pay |
| `productId` (578) / `vendorId` (598) | catalog products `DOMAIN-PURCHASE`/`DOMAIN-RENEWAL` (hidden, taxable 0); vendor Name.com | pay, attention, pulseDo |
| `setAutoRenew` (688) | assigns/releases wallet card | (api/domains.php) |
| `renewNow` (710) | pay a renewal now | api/domains.php:88 |
| `retryRenewal` (722) | autoRenew after a new card | Wallet.php:260 |
| `autoRenew` (823) / `renewDeclined` (841) | job renewal; 503 → `retry`; decline → `renew_fails`, retry days [3, 7], email | tick |
| `attention` (1062) / `pulseSteps` (1084) / `pulseDo` (1112) | Pulse cases `domorder:`, `domrenew:`, `domverify:`, `domtk:`; Try again / Refund / Email / Clear | Pulse.php:293 / Pulse playbook / Pulse.php:769 |
| `tick` (1208) | every 15 min (`app/scripts/domains_tick.php:18`): verify, auto-renewals due 14 days ahead, mark expired, moved away, wallet reminders, DKIM checks, delete cart registrant data older than 30 days | domains_tick.php |

### 1.9 `app/lib/QrIssue.php` (GO Codes)

Header (2–18) includes: "Paid: $4.95 once, plus tax, per code … Paid through QuickBooks." and "A refunded or charged-back code is switched off (trvgo.us shows "not active"). Orders are the order system's (Orders: orders, order_lines, order_ledger)."

| Function (line) | What it does | Tables | Callers |
|---|---|---|---|
| `guestKey` (57), `owner` (71), `whose` (78), `allowOrder` (87), `orderAllowed` (92), `allowedOrders` (94) | guest cookie `tv_go` (SHA-256 stored); order access by session list | – | api/go-codes.php, manage.php |
| `blocked` (97) | suspended/limited/deleted accounts | R users | checkout, create |
| `freeLeft` (109) | 5 free basic codes for a verified-email member | R | create |
| `create` (186) | free → issued at once; else `cart` at 495¢ (cart max 25) | W qr_issued | api/go-codes.php:82 |
| `get` (218), `owned` (229), `listFor` (232), `fix` (263), `remove` (272), `cart` (280) | cart/codes | qr_issued | api/go-codes.php |
| `withPromo` (290) | per-line `Promotions::linePrice('go_code')` | – | checkout, api/go-codes.php:50 |
| `checkout` (311) | §5.1 | W orders, qr_issued, order_lines, promotion_uses | api/go-codes.php:100 |
| `payCard` (371) | §5.1 | – | api/go-codes.php:105 |
| `cancelMine` (410) | buyer cancels a pending order with no `charge_id` → `Orders::cancelUnpaid` | – | api/go-codes.php:108 |
| `order` (418), `byToken` (429), `orders` (439) | buyer's orders (`pending, paid, part_refunded, refunded, disputed`) | R | manage.php:20, api |
| `refresh` (460) | for each pending order (≤ every 5 s via `checked_at`): `QuickBooksPay::paid` → `markPaid` | W orders.checked_at | api/go-codes.php:111 |
| `manageUrl` (474) | `/qr/manage.php?t=<access_token>` | – | markPaid, admin/order.php:149 |
| `markPaid` (480) | §5.1 step; delegates client orders (`created_by > 0`) to `ClientOrders::paidInQuickBooks` | W orders, qr_issued, short_codes, order_lines, order_ledger, order_events | qb-webhook.php:29, web/admin/order.php:73/79, payCard:403, refresh:469 |
| `claim` (518) | guest → member: carts, codes, orders by guest key, and guest orders by confirmed email | W qr_issued, orders | api/go-codes.php:32, qr/manage.php:21, ClientOrders.php:1533 |
| `grant` (541) | staff grants a cart/pending code free (`order_id = NULL`) | W qr_issued | web/admin/ecommerce.php:38 |
| `issueShort` (605) | trvgo.us short code (not Wi-Fi) | W short_codes, qr_issued | create, markPaid, grant, listFor, admin/order.php:115, ClientOrders.php:1787 |
| `staffFix` (632), `revoke` (648), `pause` (655) | correct / switch off (`status='revoked'`, `short_codes.active=0`) / pause | qr_issued, short_codes | Orders.php:430 / 490 / 445, 501 |
| `voidOrder` (661) | void pending order, codes to cart, release promo | W | no caller found by grep |
| others | `basic` 121, logo functions 129–174, `cleanStyle` 176, `shortUrl` 551, `detailsHtml` 566, `holds` 585, `qrData` 619 (comment: "GO_DIRECT=on in .env (Terms "If Traversence closes")") | | |

### 1.10 `app/lib/CartPlans.php`

Header (2–6): "one cart for everything Traversence sells … a plan that starts takes one card token (Billing::start keeps the card on file for renewals); a change of plan or packs added to a plan already paid go on the card on file (Billing::changePlan, addAddon). GO Codes in the same checkout take a second token from the same card entry (js/go-codes.js `tokens`)."

| Function (line) | What it does | Callers |
|---|---|---|
| `ready` (13) | cart_plans + Billing ready | api/go-codes.php |
| `add` (21) | plan (replaces account's plan line) or pack (+1, ≤20) | api/go-codes.php:75 |
| `remove` (40) | delete line | api/go-codes.php:76 |
| `groups` (47) | groups by account; one-offs one group (`card: new`); domain groups from AccountDomains; plan group `card: new` (plan starting), `file` (paid plan with card on file: `previewChange` + `previewAddon`), or `none` (why) | api/go-codes.php:52, section-rails.php:241, shop/index.php:28 |
| `tokensNeeded` (97) | count of groups with `card === 'new'` | api/go-codes.php:52 |
| `pay` (104) | each group in order; one-off → `OneOffs::pay`; domain → `AccountDomains::payGroup` (token only if `card === 'new'`); new plan → `Billing::start`; else `changePlan` + `addAddon`; deletes the group's cart lines after each; stops at first failure with "Done: …" prefix | api/go-codes.php:77 |

### 1.11 `app/lib/Billing.php` (where it touches QuickBooks; it does not write `orders`)

Header (2–11) includes: "The card is kept on file in QuickBooks Payments; each renewal charges it (option B) and writes a Sales Receipt into QuickBooks. … Charges are never made twice: each has a key, and QuickBooks Payments does a key once."

| Function (line) | QB / money | Callers |
|---|---|---|
| `start` (168) | card via `cardFrom` (wallet or `saveCard`); `charge(..., 'start', …, 'sub<sid>-start-<today>')`; on failure the subscription ends/reverts | CartPlans.php:116, api/plans.php:104 |
| `oneMonth` (259) | no charge itself (OneOffs charges) | OneOffs.php:81 |
| `setCard` (289), `cardFrom` (305) | `QuickBooksPay::saveCard` fallback when the wallet isn't ready | – |
| `changePlan` (337) | upgrade difference charged now, key `sub<sid>-up-<plan>-<today>` | CartPlans.php:119, api/plans.php:128 |
| `addAddon` (380) | prorated pack, key `sub<sid>-add-<aid>` | CartPlans.php:120, api/plans.php:140 |
| `chargeExtra` (400) | one-off on the plan's card (storefront domains, decisions/0139 §3) | Domains.php:401, 475 |
| `charge` (416, private) | dedupe on `subscription_charges.req_key` with status `paid`; test payer (`TestAccounts::isTestUser`) → `useEnv('sandbox')`; `chargeSaved`; failure → `subscription_charges` row `failed`; `salesReceipt` (failure only logged); row `paid`; email. No `guardReal` call. | start, changePlan, addAddon, chargeExtra, renew |
| `renew` (454) | key `sub<sid>-renew-<period_end>-<fails>`; 503 → `retry` | tick, Wallet.php:257 |
| `tick` (519) | daily (`app/scripts/billing_tick.php:16`) | – |

### 1.12 `app/lib/Wallet.php` (payment-layer parts)

Header comments verbatim: "Plans pay from the wallet (Jason, 2026-11-17: "it is a payment method from wallet")" and "Two wallets per account, never mixed (Jason, 2026-11-17: "a test/sandbox version that is used by the system for testing to ensure no actual live payment is ever available")".

| Function (line) | What it does | Callers |
|---|---|---|
| `add` (134) | `saveCard` (in sandbox for a test owner) then `adopt` | resolve, api/domains.php:126 |
| `adopt` (145) | `wallet_cards` row, `env` sandbox for the test side | add |
| `resolve` (170) | `wallet:<id>` (must be `usable`) or token → add | Billing::cardFrom, AccountDomains::pay, setCartCard, setAutoRenew |
| `charge` (292) | test card: force `useEnv('sandbox')`, refuse if env still reads production; live card while side is `test`: refused ("This is a test: it never charges a real card"); else `chargeSaved` | AccountDomains.php:437 |
| `testOwner` (309), `asSandbox` (323), `side` (333), `inSandbox` (341), `liveAllowed` (344), `usable` (351), `sandboxFor` (357) | test/live wallet selection | AccountDomains, Billing |

### 1.13 `web/admin/order.php` (one order, admin)

Header (2–8): the three-step policy "1. Fix it … 2. Credit … 3. Refund only when neither works … Also: a card dispute (chargeback) and how it ended, checking a payment with QuickBooks, cancelling an unpaid order, a note for our team. The history and the money ledger below are only ever added to. Admins only."
Access: `tv_crawler_require_admin` (18) + `tv_admin_require` (19). Client orders still `draft`/`sent` redirect to `/admin/client-order.php` (26).
POST `do` (40–90): `fix` → Orders::fix; `credit` → Orders::credit; `refund` with `how=record` → Orders::refund, else Orders::refundThroughQuickBooks; `inbox` → Orders::inboxApply; `chargeback`; `won`/`lost`; `check` → `QuickBooksPay::paid` then `QrIssue::markPaid`; `mockpaid` (mock only; sets `note='mock-paid'` then markPaid); `cancel` → Orders::cancelUnpaid; `pause`/`resume` → Orders::pauseLine; `note`.
Display: summary tiles from `Orders::money` (174–183); "Check payment with QuickBooks" button when unpaid (185); credit form default `net − credited` (241); refund radio "Refund the card through QuickBooks" enabled only if `canRefundCards` (251) else "Already refunded in QuickBooks: record it here" (252); refund amount default `refundable` (253); dispute form default `max(0, net)` (273); history (293); ledger with refund/chargeback/credit_used shown negative (304).

### 1.14 `web/api/go-codes.php`

Actions (56–113): `status`, `keep4k_add`, `pro_month_add`, `plan_add`/`plan_remove`/`plans_pay` (members only), `create`, `fix`, `remove`, `promo` (stores code in `$_SESSION['tv_go_promo']`), `unpromo`, `checkout`, `pay`, `cancel`, `refresh`. Requires POST + CSRF (23–24). Member signed in → `QrIssue::claim` each request (32). Status carries `pay.token_url`, `pay.test` (`env !== 'production'`), `pay.mock` (50). PDOException message (117): "Something went wrong on our side. If you were paying, nothing was charged twice: refresh the page, or write to support@traversence.com."

### 1.15 `web/qr/cart.php`

Header (2–6) verbatim: "Checkout makes the QuickBooks invoice (sales tax from the address), then the card is taken right here: the card goes from the browser straight to Intuit (QuickBooks Payments), and the codes are ready the moment it's paid (decisions/0098). QuickBooks' own payment page stays as the other way to pay (card or bank transfer)."
Flow in the page script: "Continue to payment" (`#go-pay`) → button text "Working out the tax…" → `checkout` → `Go.cardForm(paybox, order, …)` with `tokens: withPlans ? need + 1 : 1`; with plans: `plans_pay` with the first `need` tokens, then `pay` with the last (156–157); `onPaid` → `o.manage`; `onCancel` → `cancel`. Plans-only cart pays in its own box (96–109). Domain note (60): "Domain names aren't refundable once registered."

---

## 2. Where money moves

Legend: G = `guardReal` (which calls `guardStaging`), T = `testHere` (via `env()`), E = `Orders::sameEnv`, W = Wallet test/live side checks, S = `StagingGate::hold()` (on every QuickBooks HTTP call, `QuickBooksPay::http` 306).

### 2.1 Charges and invoices

| # | Path | Steps (file:line) | Environment checks | Idempotency |
|---|---|---|---|---|
| C1 | GO Codes invoice (checkout) | QrIssue::checkout 311 → order row 326 → lines 332 → promo redeem 340 → `QuickBooksPay::invoice` 346 → order gets `invoice_id/no/pay_url/tax/total` 355, `qb_env` 357, number 358, `qb_customer_id` 359, event `placed` 361 | `QuickBooksPay::open` 315 (sandbox: staff only); G at 549 (env() includes T); S | QB `requestid=inv-<oid>` (568) |
| C2 | GO Codes card on our page | QrIssue::payCard 371 → `GET_LOCK('tv-pay-<oid>')` 382 → status must be `pending` 386 → E 387 → key `pay-<oid>-<#card_declined events>` 389 → `chargeCard` 390 → `charge_id` saved 397 → `recordCardPayment` 400 → `markPaid(…, 'card', 'card <id>')` 403 → `needs_books` event on warning 404 | G in chargeCard 802 and recordCardPayment 836; E; payment scope check 808 | Payments `Request-Id = pay-<oid>-<n>`; QB Payment `requestid` same key (fallback `<key>p`); ledger `charge-<oid>` (497); DB lock |
| C3 | GO Codes paid on QuickBooks' page | qb-webhook.php 24–29, or QrIssue::refresh 460–469, or admin/order.php `check` 73 → `QuickBooksPay::paid` 718 → `QrIssue::markPaid` 480 → status `pending`→`paid` 488 (exits if no row changed) → codes `paid` 491 → short codes 493 → lines `done` 494 → `Orders::record('charge', total_cents, 'charge-<oid>', 'quickbooks', …)` 497 → event `paid` 498 → email 504 | webhook signature env forced (qb-webhook 20); `paid()` returns false when `qb_env` ≠ env (722) | `UPDATE … WHERE status='pending'` rowCount; ledger `charge-<oid>` |
| C4 | Client order invoice | `QuickBooksPay::invoiceFor` 590 (called ClientOrders.php:964) | G 592 | `requestid=inv-<oid>` |
| C5 | Client order card (deposit or balance) | ClientOrders::pay 1023: lock, `dueNow`, E, key `pay-<oid>-<paidBefore>-<declines>`, `chargeCard(…, $due)` 1038, `recordCardPayment(…, $due)` 1043, `received(deposit|charge)` 1047 → `Orders::record` 1776 | G, E | Request-Id/requestid = key; ledger key = key |
| C6 | Client order paid in QuickBooks | webhook/markPaid 483–486 → `ClientOrders::paidInQuickBooks` 1054: if pending, records `left = total − paid` as `charge`, key `charge-<oid>` | as C3 | ledger `charge-<oid>` |
| C7 | Client order wire/check/cash | ClientOrders::recordPayment 1469 → `received` → `QuickBooksPay::withEnv(order qb_env, recordOtherPayment)` 1484 | G 860 | key `out-<oid>-<sha(cents|method|ref|YmdHi)>`; QB `requestid` same |
| C8 | One-off (4K keep, a month of Pro) | §5.2 | test user → `useEnv('sandbox')` (OneOffs 58); G in chargeCard | Request-Id `oneoff-<oid>`; receipt `oneoff-<oid>`; ledger `charge-<oid>` |
| C9 | Domains (purchase, renewal, Renew now, auto-renew) | §5.3 | `sandboxed()` (test mode, T, test owner); G only when not sandboxed (436); W in Wallet::charge (296–305) | Request-Id `domorder-<oid>` or `domrenew-<did>-<expiry date>-<fails>`; ledger `charge-<oid>`; Name.com `X-Idempotency-Key` |
| C10 | Plans/packs (subscriptions, not `orders`) | Billing::charge 416 → chargeSaved 428 → salesReceipt 437 → `subscription_charges` | test payer → sandbox (425–427); no G | `subscription_charges.req_key` + Request-Id |
| C11 | Storefront domain (Domains.php, not `orders`) | Domains::buy 386 → `Billing::chargeExtra` 400 (`noRealMoney()` → `'TEST-NOT-CHARGED'`) | Domains' own `mode()`/`noRealMoney()` | key `domain-<domain>-<Ymd>` |

### 2.2 Refunds, credits, chargebacks

| # | Path | Steps | Environment checks | Idempotency |
|---|---|---|---|---|
| R1 | Staff card refund | admin/order.php:56 → Orders::refundThroughQuickBooks 194: paidOrder, E 197, checkRefund 198, key `rf<oid>-<sha(cents|reason|YmdH)>` 199 → `refundCard` 201 (`chargeFor` 898 → `charge_id` or Payment scan; Payments refund 901) → `refundReceipt` 203 (failure → warning, event `needs_books` 207) → `applyRefund(…, 'card')` 206 | E; `chargeFor` calls G at 874 when `charge_id` is empty; `refundCard` and `refundReceipt` themselves have no G | Payments Request-Id and QB requestid = key; ledger key = key (repeat → "That refund is recorded already.") |
| R2 | Staff records a refund made in QuickBooks | admin/order.php:53 → Orders::refund 212 → applyRefund(…, 'quickbooks') | none (no QB call) | key `refund-<oid>-<sha(cents|qbRef|reason)>` |
| R3 | Refund/credit made in QuickBooks, auto-matched | webhook → Orders::fromQuickBooks 242 (§3) | webhook env | `qb-rr-<id>` / `qb-cm-<id>`; inbox unique by (kind, qb_id) assumed from `INSERT IGNORE` |
| R4 | Same, placed by a person | admin/order.php:63 → Orders::inboxApply 358 | none | `qb-rr-<id>` / `qb-cm-<id>` |
| R5 | Staff credit | admin/order.php:47 → Orders::credit 150 → `creditMemo` 158 → record `credit_issued` 159 | E 153; no G | `credit-<oid>-<sha(cents|note|YmdHi)>` (QB requestid + ledger) |
| R6 | Domain line not registered (automatic) | AccountDomains::lineFailed 532 → `Wallet::sandboxFor('', 0, refundCharge(charge, line cents, …, 'domrefund-<lid>'))` 543 → record `refund`, key `refund-<lid>` 544 | runs inside `pay`'s `inPlace` (sandbox when sandboxed) | Request-Id `domrefund-<lid>`; ledger `refund-<lid>` |
| R7 | Domain refund from Pulse | AccountDomains::pulseDo `dom_refund` 1145: skips lines whose `refund-<lid>` is in the ledger 1149; `refundCharge(orders.charge_id, unit_cents, …, 'domrefund-<lid>')` inside `asSandbox(qb_env==sandbox)` 1150; record 1151; line `cancelled` | sandbox from the order's `qb_env` | same keys |
| R8 | Storefront domain not registered | Domains.php:411 `refundCharge(…, '<key>-refund')` when `mode()==='live'` | – | `domain-<domain>-<Ymd>-refund` |
| K1 | Chargeback opened | admin/order.php:67 → Orders::chargeback 394 (no QB call) | none | `chargeback-<oid>-<sha(ref|cents|YmdHis)>`; `disputeOpen` refuses a second |
| K2 | Dispute won / lost | Orders::chargebackResolved 404 | none | won: `chargeback-won-<oid>-<YmdHis>`; lost: no ledger row |

---

## 3. The QuickBooks webhook (`web/api/qb-webhook.php`)

1. Non-POST → 405 (15). Body read raw (16). DB connect failure → 503 (17).
2. `verifyEnv(body, HTTP_INTUIT_SIGNATURE)` (18): HMAC-SHA256 with the production verifier token, then the sandbox's (`QuickBooksPay.php` 971–978). Neither → `logWebhook(false)` and 401 (19).
3. `useEnv($env)` (20): comment "signed with the sandbox's token: it's about the sandbox company, whatever the switch says".
4. `http_response_code(200)` before any work (22): "QuickBooks wants a quick answer; the work below is short".
5. Invoices and payments (23–33): `invoicesIn` (1016) collects ids from entities named `Invoice` (any operation) and, for `Payment` entities, GETs each payment and collects its `LinkedTxn` of type `Invoice`. Logged (25). Then `SELECT id FROM orders WHERE status = 'pending' AND invoice_id IN (…)` (27); for each, `QuickBooksPay::paid()` → `QrIssue::markPaid()` (29). Errors only logged (32).
6. Refunds and credit memos (35–41): only when `Orders::ready`. `refundsIn` (1004) takes entities `RefundReceipt` and `CreditMemo`. Operation `Delete` or `Void` → `logNote("$entity $id $op: check its order by hand")`, nothing else (36). Otherwise `fetch` the entity and `Orders::fromQuickBooks` (38); result logged (39).

The "paid" condition, `QuickBooksPay::paid` (718–730), verbatim:
```php
if (!empty($o['qb_env']) && self::env() !== 'mock' && $o['qb_env'] !== self::env()) return false;   // made in the other mode
if (self::env() === 'mock') return $o['note'] === 'mock-paid';
...
return $total > 0 && (float) ($inv['Balance'] ?? $total) <= 0.004;
```
Before returning, it overwrites `orders.tax_cents` and `orders.total_cents` from the invoice (727–728).

`Orders::fromQuickBooks` (242–289) outcomes:
- `skip`: no id or entity not RefundReceipt/CreditMemo (247); `TotalAmt` ≤ 0 (255); customer has no candidate orders (260, comment: "a customer with no paid orders from the site (something else the company sells): not ours").
- `ours`: PrivateNote has `[TRV:` (248).
- `done`: ledger already has `qb-rr-<id>` / `qb-cm-<id>` (249–251).
- `queued`: already in `order_inbox` (252–253).
- `recorded`: exactly one candidate and it fits: a credit → `credit_issued` (265–268); a refund equal to everything refundable → `applyRefund(…, 'Refunded in QuickBooks', label, [], null, key, 'quickbooks')`, which takes back every line (270–272).
- `inbox`: otherwise, with a `why`: partial refund on the one order (274), "more than any of this customer's orders has left to refund" (275), several possible orders (277–281, exact-amount ones first). Inserted with `qb_env` (287).

Candidates (292–299): orders with status `paid`, `part_refunded`, `disputed`, same `qb_env`, matched by `qb_customer_id` or `invoice_id` among the customer's invoices (≤200), newest 50.

Not handled by the webhook (by code): any entity other than Invoice, Payment, RefundReceipt, CreditMemo (e.g. SalesReceipt, Deposit, Customer, Item); Payment/Invoice events for orders not in `pending`; invoices whose order is not found by `invoice_id`; RefundReceipt/CreditMemo `Delete`/`Void` (log only); a partially paid invoice (Balance > 0.004) produces nothing (no deposit row).

---

## 4. The ledger

`order_ledger` columns written (Orders.php:76): `order_id, kind, amount_cents, method, qb_ref, note, actor_user_id, request_key`, by `INSERT IGNORE` (a duplicate `request_key` is a no-op; uniqueness of the column is inferred from this, the migration isn't in the mirror).

Kinds (22–23) and who writes them:

| kind | label | written by |
|---|---|---|
| `charge` | Paid | QrIssue::markPaid 497; OneOffs::pay 92; AccountDomains::pay 447; ClientOrders::received 1776 (via pay, paidInQuickBooks, recordPayment); QbPull pull 151 / merge 311 |
| `deposit` | Deposit paid | ClientOrders::received (part payments); QbPull 151/311 (a QB payment smaller than what's left) |
| `refund` | Refunded | Orders::applyRefund 224; AccountDomains 544, 1151 |
| `credit_issued` | Credit given | Orders::credit 159, fromQuickBooks 266, inboxApply 369 |
| `credit_used` | Credit used | no writer found anywhere (`grep credit_used`): read only by `creditBalance` and shown negative in admin/order.php:304 |
| `chargeback` | Card dispute | Orders::chargeback 399 |
| `chargeback_reversed` | Dispute won | Orders::chargebackResolved 411 |

`Orders::money` (84–93): `paid = charge + deposit`; `net = paid − refund − chargeback + chargeback_reversed`; `credited = credit_issued`; `refundable = max(0, net − credited)` (175–179). Credits reduce what can be refunded/credited further but not `net`.

`Orders::settle` (96–106), run after every new ledger row (and from `chargebackResolved` when lost):
1. No order, or status `draft`, `sent`, `void` → unchanged.
2. `paid ≤ 0` → unchanged.
3. Status `pending` and `paid < total_cents` → unchanged (comment: "a deposit: waiting for the rest (decisions/0101)").
4. Otherwise: `disputed` if a dispute is open; else `refunded` if `net ≤ 0`; else `part_refunded` if `refunded > 0`; else `paid`. Written only if different.

So `pending → paid` happens in settle when `paid ≥ total_cents`; most paths also set `paid` directly before recording (QrIssue::markPaid 488, OneOffs 75, AccountDomains 445, ClientOrders::received 1783, QbPull 153/313). Credits do not change status (net unchanged). A lost dispute leaves `chargeback` without a reversal, so `net` drops by the disputed amount.

Status changes outside settle: `void` (Orders::cancelUnpaid 464, QrIssue checkout failure 349, OneOffs card failure 70, AccountDomains card failure 439, QrIssue::voidOrder 663); `pending` (QbPull pull 140, merge 302 from draft/sent; OneOffs/AccountDomains insert as `pending`).

`order_events` names seen in these files: placed, paid, deposit_paid, card_declined, declined, needs_books, needs_attention, attention_cleared, credited, refunded, refund, chargeback, chargeback_won, chargeback_lost, fixed, paused, resumed, note, cancelled, done, problem, pulled, pulled_note, merged, price_gap, line_dropped, payment_recorded.

---

## 5. Other order-making paths

### 5.1 GO Codes cart checkout (member or guest)

| Step | file:line | Who acts | What the buyer sees | Money |
|---|---|---|---|---|
| Code made (free or into cart at $4.95) | QrIssue::create 186; api/go-codes.php:79 | buyer | code issued, or in cart | none |
| Promo applied | api/go-codes.php:91–94; QrIssue::withPromo 290 | buyer | "Code applied: …", line prices reduced | none |
| Checkout: billing details, order row, codes `pending`, lines, promo redeemed | QrIssue::checkout 311–343 | buyer | "Working out the tax…" (cart.php:146) | none |
| QB invoice (tax from address) | QuickBooksPay::invoice 547 via checkout 346 | system | card form with subtotal/tax/total | QB invoice created (requestid `inv-<oid>`) |
| Invoice failure | checkout 347–353 | system | "Checkout couldn't start just now. Nothing was charged; your codes are still in your cart." | order `void`, promo released |
| Pay by card here | QrIssue::payCard 371 via api/go-codes.php:105 | buyer | redirected to `/qr/manage.php?t=…` (cart.php:160) | Payments charge + QB Payment on invoice; ledger `charge` |
| Plans/one-offs/domains in the same cart | cart.php:155–157 → `plans_pay` then `pay` | buyer | one card entry, "Pay $X in all" | per §5.2/§5.3/Billing |
| Or pay on QuickBooks' page (`pay_url`) | webhook / refresh / admin "Check payment" | buyer, then system | email "Your GO Codes are ready" | ledger `charge` = `total_cents` (after `paid()` refreshed totals) |
| Cancel before paying | QrIssue::cancelMine 410 → Orders::cancelUnpaid | buyer | codes back in cart | none; event text asks to void invoice in QB (no API call) |
| Decline | payCard 391–392 | system | "The card wasn't accepted: … Nothing was charged." | event `card_declined` (changes next key) |

### 5.2 One-offs (OneOffs::pay 50, via CartPlans::pay 111)

| Step | file:line | Who | Buyer sees | Money |
|---|---|---|---|---|
| Token format check; eligibility re-checked | 53–54 | system | errors | none |
| Test account → `useEnv('sandbox')` | 57–58 | system | – | – |
| Order `pending`, `approved_at` NOW, `qb_env`, lines `approved_at`, number | 60–66 | system | – | – |
| `chargeCard(row, token, 'oneoff-<oid>', total)` | 68 | system | decline message | Payments charge (G) |
| Failure → order `void`, lines `cancelled` | 69–72 | system | error | none |
| Order `paid`, `charge_id`, lines `done` | 75–77 | system | – | – |
| Deliver: `Billing::oneMonth` / `keep_hd = 1` + `MemberPosts::keep` (failures → events `needs_attention` / `note`) | 79–88 | system | – | – |
| `salesReceipt` (failure → `needs_books`) | 90–91, 94 | system | – | QB SalesReceipt (key `oneoff-<oid>`) |
| Ledger `charge`, event `paid` | 92–93 | system | – | ledger |
| Receipt email | 96–102 | system | "Your Traversence receipt: $X" | – |
No invoice, no tax lines (SalesReceipt lines `NON` unless `QB_PLAN_TAXABLE`).

### 5.3 Domains (AccountDomains::pay 386)

| Step | file:line | Who | Buyer sees | Money |
|---|---|---|---|---|
| Add to cart (price re-quoted, 18+, registrant) | addToCart 259 | buyer (member, or Domain + Plan & billing edit on a business) | domain line in cart; "Domain names aren't refundable once registered." (cart.php:60) | none |
| Re-check each spec before charging | 395–405 | system | "isn't available any more" / "price changed" | none |
| Card: `wallet:<id>`, new token (saved to wallet), or the assigned card (auto) | 407 | system | – | `saveCard` for a new token |
| Order `pending` with `qb_env`, lines (product, vendor Name.com, `cost_cents`), `order_line_costs` | 414–431 | system | – | – |
| `guardReal` unless sandboxed; `Wallet::charge` | 436–437 | system | decline → order `void`, event `declined`, error (402) | Payments charge on card on file |
| Order `paid`, `charge_id`, ledger `charge`, event `paid` | 445–448 | system | – | ledger |
| Register / renew at Name.com per line; failure → `lineFailed` | 450–461 | system | – | purchase failure: refund of that line (R6) |
| SalesReceipt for done lines | 463, 553 | system | – | QB SalesReceipt |
| Email receipt (includes failed lines' words) | 464, 562 | system | "Your Traversence receipt: domains, order #…" | – |
| `stage = 'complete'` if no failures | 465 | system | – | – |
| Renew now | renewNow 710 → pay | buyer | – | as above |
| Auto-renew (job) | tick 1208 → autoRenew 823 → pay(uid 0, auto) | system | decline email with retry date | key `domrenew-…` |
| Pulse: Try again / Refund / Clear / Email | pulseDo 1112 | staff | email from staff | R7 |

### 5.4 QuickBooks pull (QbPull::pull 97 / merge 244)

| Step | file:line | Who | Buyer sees | Money |
|---|---|---|---|---|
| Preview by invoice number | preview 21 via admin/sections.php:922 | staff (`backoffice.orders.pull`: manager+, fin/ops) | nothing | none (QB read only) |
| Decide unmapped items | pull 105–116 / Books::decide | staff | – | – |
| Build client order from invoice (status `pending`, `pay_mode='invoice'`, QB totals) | 118–145 | staff | – | – |
| Record QB payments as `charge`/`deposit`; `paid` if Balance ≤ 0 | 148–153 | system | – | ledger only, "never posted back" |
| Client attach, tags, events, Collateral | 158–174 | system | – | – |
| Merge into an existing client order | merge 244–327 via admin/client-order.php:181, ui/qb-merge.php | staff | – | ledger `charge`/`deposit` for `inv.paid − paid_here` |

### 5.5 Plans and packs (for completeness; `subscriptions`, not `orders`)

CartPlans::pay 104 → Billing::start / changePlan / addAddon; Billing::charge 416 writes `subscription_charges` and a QB SalesReceipt; no `orders` row and no `order_ledger` row.

---

## 6. Verbatim comments requested

### Partial payments / deposits / QuickBooks' own invoice page
- QuickBooksPay.php:4–6: "the buyer pays on QuickBooks' own secure payment page (its invoice link: card, and bank transfer or Apple Pay when the account offers them), and QuickBooks tells us it's paid (api/qb-webhook.php), or we ask it (paid())."
- QuickBooksPay.php:717: "/** Has the order's invoice been paid in full? Keeps its tax and total up to date. */" and 729: `return $total > 0 && (float) ($inv['Balance'] ?? $total) <= 0.004;`
- QuickBooksPay.php:803: "// $cents: a deposit or the balance (client orders)"
- QuickBooksPay.php:830–832: "The payment in the books: a QuickBooks payment on the order's invoice, marked as made through QuickBooks Payments (TxnSource IntuitPayment, the charge as its CCTransId) so it matches the deposit."
- Orders.php:11: "money moves there (the customer pays on QuickBooks' page; a refund to the card is made in QuickBooks)"
- Orders.php:102: "// a deposit: waiting for the rest (decisions/0101)"
- QrIssue.php:379: "Card payment isn't switched on yet. Nothing was charged: use "Pay on QuickBooks' page instead" below."
- qr/cart.php:6: "QuickBooks' own payment page stays as the other way to pay (card or bank transfer)."
- qb-webhook.php:4–5: "A paid order's codes are issued at once; the buyer's own page also asks QuickBooks (QrIssue::refresh), so a missed webhook only delays, never loses, an order."
- ClientOrders.php:1053: "/** The invoice was paid in QuickBooks (its page, or recorded there): the rest is recorded and the order goes on. */"
- QbPull.php:147: "// payments made in QuickBooks: recorded here, never posted back"; 307: "// QuickBooks' payments: what it has that isn't recorded here yet (a payment already recorded here isn't counted twice)"
- No comment was found that addresses a partial payment made on QuickBooks' invoice page reaching the webhook.

### Test refunds (chargeFor / guardReal)
- QuickBooksPay.php:109–114: "Never real money for a test (Jason, 2026-11-10: "ensure no test can create actual transactions outside the sandbox"): a test order (made in the sandbox, or a "TEST · …" copy, even one made while payments were live) never goes through live QuickBooks. Called before every charge, invoice and payment on an order; refunds and credits only send money back, so a test copy that once took real money can still be refunded."
- QuickBooksPay.php:870: "/** The card charge that paid an order's invoice: its QuickBooks Payments id (CCTransId), or '' (paid another way). */" with line 873 `if (!empty($o['charge_id'])) return (string) $o['charge_id'];   // taken on our checkout page` and line 874 `self::guardReal($pdo, $o);   // never real money for a test`.
- QuickBooksPay.php:890–892: "Refunds the card that paid the order, through QuickBooks Payments. Returns the refund's id. Throws with a plain reason when it can't (paid by bank transfer, connected before card refunds, or QuickBooks refused)." `refundCard` calls `chargeFor` at 898.
- QuickBooksPay.php:87–91: "A test account is signed in, a member's or a staff test login's (users.is_test, decisions/0145 §3): everything it does with QuickBooks runs in the sandbox, so no test makes a live charge, invoice, payment or receipt. On the staging copy it's true for everyone (MarketPay and Domains ask this too)."
- QuickBooksPay.php:124–126: "Payments refuse to start on staging while a live payment key is in its .env (decisions/0165 §1): the site manager says which, and payments stay off until it's gone. Nothing on live."
- Billing.php:439: "// a test account's receipt went to the sandbox's books, never the live ones (2026-11-10)"
- AccountDomains.php:436: "// never real money for a test (decisions/0145)"; 558: "// a test's receipt goes to the sandbox's books"

### Tax
- QuickBooksPay.php:4: "QuickBooks' Automated Sales Tax adds the tax from the buyer's address"
- QuickBooksPay.php:511: "The "Designed QR code" product in QuickBooks (taxable service at $4.95), found or made once." (the item name constant is `'GO Code'`, line 26)
- QuickBooksPay.php:544: "one line per code, taxable, Automated Sales Tax works out the tax from the address"
- QuickBooksPay.php:586–587: "tax worked out by QuickBooks from the address on taxable lines"
- QuickBooksPay.php:551 / 595: "local test only: a made-up 8.6% tax …"
- QuickBooksPay.php:912–913: "a Refund Receipt for the order's customer, with the tax in the same proportion as the order's (so sales tax owed comes down)"; 931: "// the tax refunded, as charged (not worked out again)"
- QuickBooksPay.php:983–984: "a QuickBooks credit memo for the order's customer, not taxed (it's money back on account)"
- QuickBooksPay.php:1118–1120: "Plan lines are recorded as not taxable until sales tax on subscriptions is settled with the accountant (set QB_PLAN_TAXABLE=1 to change it)."
- OneOffs.php:5: "(no invoice: nothing to ship, nothing to tax by address)"
- QrIssue.php:8: "Paid: $4.95 once, plus tax, per code"; 319: "Add your billing address: sales tax depends on it."
- Books.php:28: "// the same setting as Taxes & shipping's tax source, so the two never disagree"; 47: "// our own books: we're the tax and history authority"; requirement text 71: "QuickBooks works out the tax on each invoice; Traversence takes it as given."
- AccountDomains lines are inserted `taxable 0` (424); `salesReceiptFor` lines are `NON` (1108).

---

## 7. What I couldn't trace

- Schema: the migrations (`2026-12-02_orders.sql`, `2026-12-03_qb_refunds.sql`, `2026-12-04_qb_checkout.sql`, `2026-12-07_inbox_mode.sql`, `2026-12-29_order_numbers.sql`, `2026-12-11_inline_order.sql`) are named in comments but not in the mirror (they delete themselves once run). Unverified: the unique index on `order_ledger.request_key`, the unique key on `order_inbox (kind, qb_id)`, the default of `orders.status` (QrIssue::checkout inserts no status; payCard requires `pending`), and the unique key used by `order_lines … ON DUPLICATE KEY UPDATE` in checkout (332–333).
- `js/go-codes.js` (`Go.cardForm`, `Go.go`, token fetch from Intuit) not read: how the browser obtains one or several tokens.
- `ClientOrders.php` was read only at the call sites listed; `dueNow`, `create`, `isClient`, line 150 (`withEnv`) and the 964 invoice path were not followed.
- `web/admin/ecommerce.php`, `web/admin/client-order.php`, `web/admin/quickbooks.php`, `web/order.php`, `web/api/plans.php`, `web/api/domains.php`, `app/admin/sections.php` were read only at the lines quoted.
- What QuickBooks sends for an invoice Void/Delete, and what `TotalAmt`/`Balance` a voided invoice returns, so whether `paid()` could ever be true for one, is QuickBooks behaviour not visible in code.
- Whether QuickBooks Payments honours `Request-Id` across the fallback key (`<key>p`) in `recordCardPayment`; whether Intuit dedupes `requestid` across sandbox and production.
- `StagingGate::liveKeys()`, `StagingGate::hold()`, `Staging::here()`, `TestAccounts::isTestUser`, `Domains::noRealMoney()`/`asSandbox`, `Pulse` playbook wiring: not read.
- No caller found for `Orders::byNo`, `QrIssue::voidOrder`, `QuickBooksPay::verify`, `Promotions::scanned` (grep on `Class::fn(` across `sync-app`, `sync-storage`, `sync-trvgo`; dynamic calls would not show).
- Where `order_inbox`'s table name resolves (`Database::t('order_inbox')`; listed in `DataMove` group 5 "Our sales", so it may live in the admin database).
