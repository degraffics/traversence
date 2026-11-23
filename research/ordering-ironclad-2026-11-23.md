# Ordering, ironclad: the map and the tests (2026-11-23)

Jason, 2026-11-23: "1 simple requirement before all else, placing orders and configuring them or purchase is what must be
ironclad first." Also: "from the screen I sent how to do I place an order should be visually dominant", "Make this page
open with job board and above it a place orders button", then "Table view like it currently is is fine", and on data:
"for building we must use live data".

**Status: map and tests, before any fix** (the session manager's order: "Send me the map and the test results first").
- Built on the live mirror at Sync 13:42 UTC (8b3ea37).
- Tested on the design harness with mock QuickBooks: no money moved, no client reached.
- Not yet tested on staging or live; that's Jason's call (one question in the pop-up).
- Every finding below was checked in the code by this session. Where a case ran, the evidence is what it did; where it
  can't run on the mock, it says "code".

## 1. The map: every path, with file and line

`CO` = `app/lib/ClientOrders.php`, `QBP` = `app/lib/QuickBooksPay.php`, `QR` = `app/lib/QrIssue.php`, `WO` =
`app/lib/WorkOrders.php`; pages are `website/…`.

### Entry 1: Back-Office → Orders (`/admin/backoffice/orders`)
- **Layout:** the section is set in `app/admin/manifests.php:113`: metrics, then pipeline (the table), then the site's
  orders, then QuickBooks pull, then Sandbox. The tool is `app/admin/tools.php:40-75`: columns Order, Client, Stage,
  Total, Updated, and `create` → `/admin/backoffice/orders/new`.
- **Placing an order today:** a 38×38 "+" icon, its name only in aria-label ("New client order"). It sits 333px down at
  1280 and 671px down at 390, under two "Runs" lines and four count tiles. (Rendered: `entry-orders-*.png`.)
- **The Runs lines** come from `app/admin/widgets.php:99-100`, Jason 2026-11-17: "a page a scheduled task serves says
  when it runs, with Change to its card".

### Staff build the order (`website/admin/client-order.php`; in-place edits through `website/api/admin/client-order.php`)
1. **Create:** `do=create` (page 52) → `CO::create` 137, `CO::client` 207-214 (name; a valid email). A member is linked
   by email (`memberFor` 216).
2. **Add items:**
   - catalog product: `do=add_product` → `CO::addProduct` 267-289. The price is `Products::priceFor` 225-242: the tier
     at or below, pro rata.
   - custom line: `add_custom` → `addCustom` 292.
   - shipping line: `add_ship`.
   - GO Code: `add_go` → `addGoCode` 309.
   - bundles: `part_*` 2136-2180.
   - projects: 2078-2115.
   - edit in place: `setLineField` 1406.
   - the edit dialog: `editLine` 1448.
   - remove: `removeLine` 388.
3. **Configure:**
   - options: by position, an unpicked one takes the first choice (274-277; 0101).
   - files: through `js/tv-upload.js` → `/api/upload.php` → `app/lib/Uploads.php`, targets `order_proof`, `order_file`
     and `order_final` in `app/lib/upload-targets.php` 15-50, 430.
   - proofs: `proofFromUpload` 414.
4. **Shipping:**
   - per item: the ship icon → `setShip` 1505.
   - per order: `Shipments::setMode` and destinations (`app/lib/Shipments.php` 49-124).
   - addresses: in place through `setField` 1380. There's no control for `orders.delivery`.
5. **Prices:**
   - a draft follows the catalog on every view (`refreshPrices` 244, run by the page and by Send).
   - a price staff typed is kept.
   - promotions come off at approval (`applyPromo` 2024).
   - the total is `recalc` 2296.
   - tax is made only at approval, by QuickBooks (`QBP::invoiceFor` 590).
6. **Draft:** there's no single Save. Fields save in place; the command strip's Save posts the "only save" forms.
7. **Send:**
   - `do=send` → `CO::send` 646-669.
   - It checks for at least one line and a proof for every printed item (`proofGaps` 1138).
   - The link lasts 14 days. The email goes through `mail()` 2360.
   - **Send cart isn't built** (model 1.1). There's one route, Send.
8. **Copies:**
   - Duplicate and test copy: `CO::duplicate` 157.
   - Reorder with the same art: `Collateral::reorder` 183.
   - The client's Ask to reorder: `website/order.php` 58-65 and `website/user/orders.php` 23-36 → `Collateral::askReorder`
     141. It makes a draft for staff; no money.

### The client (`website/order.php?t=…`; payments through `website/api/client-order.php`)
1. **Open:**
   - lookup: `CO::byToken` 67.
   - a wrong link or a draft: "This link doesn't work…".
   - expired: `expired` 81; only "sent" orders expire.
   - void: "This order was cancelled. Nothing was charged."
2. **Proofs:** `decideProof` 1001-1023: approve with both boxes and initials, or ask for changes.
3. **Approve:**
   - `do=approve` → `CO::approve` 1029-1092.
   - checks: link not expired; a proof for every printed item; none waiting; a name; the billing address.
   - then the promo, ship-to (`shipFrom` 1600), and the QuickBooks invoice (`invoiceFor` 590, requestid `inv-<id>`).
   - status becomes pending, with the deposit at `deposit_pct`.
4. **Pay:**
   - card form: `js/go-codes.js` 43-93. The card goes straight to Intuit; only a token comes to us.
   - `CO::pay` 1174-1202 takes a lock and charges `dueNow` (111) with request key
     `pay-<id>-<paid>-<declines>` (`QBP::chargeCard` 813, `guardReal` 815).
   - then `recordCardPayment` 847 and `received` 1939.
   - QuickBooks' own page: the webhook (`website/api/qb-webhook.php`) → `QR::checkPaid` 484 → `CO::fromQuickBooks` 1221.
   - wire, check or cash: `choosePay` 1795 and `reportPay` 1891; staff record it (`recordPayment` 1619).
5. **Paid:** `received` 1939. It writes the ledger (`Orders::record`, the request key is unique), the status, GO Codes,
   the stage and the receipts, then `WorkOrders::catchUp`.
6. **The vendor:** `vendorHold` 722 gates `WorkOrders::create`, `send` and `handLink`, and `setStage('ordered')` (#265).

### The client's own purchases (Shop and the one cart; our own sales, not the Marketplace)
- **Shop** `website/shop/index.php`; **cart** `website/qr/cart.php`; script `js/go-codes.js`; API `website/api/go-codes.php`.
- **GO Codes:**
  - add: `QR::create` 186.
  - checkout: `QR::checkout` 311, which makes a QuickBooks invoice with QuickBooks' tax.
  - card: `QR::payCard` 371, with a lock and a request key.
  - paid: `markPaid` 495.
  - Back to the cart: `cancelMine` 410.
- **One-offs** (Keep a video in 4K, a month of Pro): `app/lib/OneOffs.php` 37-104.
- **Domains:** `app/lib/AccountDomains.php` 259-575 (Name.com).
- **Plans and packs:** `app/lib/CartPlans.php` and `app/lib/Billing.php`. These are subscriptions, not orders.
- **Print can't be bought on the site:** the Shop's print card is a mailto (`shop/index.php:68`). "Place order" and the
  ordering queue aren't built (model 1.3).

## 2. The tests

- `tools/order-tests/ironclad.php` runs each path's library calls on the harness's database: 32 cases.
- `ironclad.cjs` with `ironclad-seed.php` runs the client's page and entry point 1 in the browser at 1280 and 390:
  18 cases.
- A result:
  - **WORKS** means it does what the decisions say.
  - **PARTIAL** means it works, with a gap.
  - **BROKEN** means wrong money, a wrong record, a crash or a false message.
- Totals: 33 works, 2 partial, 15 broken. Three of the broken are repeats: entry point 1 fails at both widths, and the
  outside order's card form fails at both widths and in the library run. That leaves **12 distinct broken cases**: the
  library run's 11, plus entry point 1. With what reading the code turned up, they are §3 a, b and c. Pictures are in
  `handoffs/ordering-ironclad/`.

| Path | Case | Result | Evidence |
|---|---|---|---|
| Build | Create: a bad client email | **WORKS** | Refused: "RuntimeException: Add the client's email: the order goes there." |
| Build | Create: name and email | **WORKS** | Order 361 draft, token 40 chars |
| Build | Add a catalog product (500, Gloss) | **WORKS** | unit 10000¢, options "Paper: Gloss", taxable 1 |
| Build | Add a product with quantity "abc" | **BROKEN** | Accepted with no message: title "Ironclad business cards · 1", unit 20¢ (quantity became 1, priced pro rata from the 500 tier); the page casts (int) before the library sees it |
| Build | An option not picked | **WORKS** | Took the first choice ("Paper: Matte"), as decided in 0101 ("The first choice is the standard one") |
| Build | A negative price | **WORKS** | Refused: "RuntimeException: Enter the price in dollars, like 29.00." |
| Build | Untick Taxable on a catalog line, then the page reloads | **BROKEN** | taxable was 0 after the save, 1 after the reload (refreshPrices puts the catalog's back: ClientOrders.php:258) |
| Build | The catalog price changes while it's a draft | **WORKS** | The line follows: 11000¢ |
| Build | Send with no items | **WORKS** | Refused: "RuntimeException: Add something to the order first." |
| Build | Send a printed item with no proof | **WORKS** | Refused: "RuntimeException: Every printed item needs a proof first (Ironclad business cards · 500: no proof yet). Add a proof unde" |
| Build | Send when the email can't go (no mail server) | **BROKEN** | Status sent; the event says "To ironclad-client@example.com, good until Dec 7" and the page says "Sent. The client has the link by email" though Mailer failed (logged only: ClientOrders.php mail()) |
| Build | A double click on Add it | **BROKEN** | Two identical lines made (2): no busy state on the builder and no request key |
| Client | Approve on an expired link | **WORKS** | Refused: "RuntimeException: This order's link has expired. Ask us to send it again." |
| Client | Approve with no billing address | **WORKS** | Refused: "RuntimeException: Add your billing address: sales tax depends on it." |
| Client | Approve (mock QuickBooks: 8.6% tax) | **WORKS** | pending, subtotal 10000¢ + tax 860¢ = 10860¢, invoice M-361 |
| Client | Approve again (a second click after the first finished) | **WORKS** | Refused: "RuntimeException: This order is already approved." |
| Client | Approve an order whose items were all removed after sending | **BROKEN** | Approved with no items: total 0¢, invoice M-364 |
| Pay | A refused card | **WORKS** | Said "RuntimeException: Your card was declined (test). Nothing was charged."; paid 0¢; card_declined events 1 |
| Pay | Pay in full by card | **WORKS** | status paid, paid 10860¢ of 10860¢, stage "production" |
| Pay | The same card form sent twice (back button, resubmit) | **WORKS** | paid stays 10860¢ (nothing due: returns without charging) |
| Pay | Deposit terms: the card form submitted twice (a double click) | **PARTIAL** | First charge 7059¢ (the deposit, 7059¢); the second, seconds later, charged the balance too: paid 10860¢ of 10860¢. The button disables itself; the server can't tell a double click from "Pay the rest" |
| Pay | A part payment below the deposit, then the page asks for… | **BROKEN** | Asks 8860¢; the rest of the deposit is 5059¢ (dueNow switches to the whole balance once anything is paid: ClientOrders.php:116) |
| Pay | An order paid outside QuickBooks, paid by card on its page | **BROKEN** | Charged 10000¢ by card although it has no invoice (order.php:395 shows the card form; pay() has no outside check); needs_books: 0 |
| Pay | Shipping set to One address after approval | **BROKEN** | Total 10860¢ with tax became 10000¢ (recalc writes the pre-tax subtotal at any status: ClientOrders.php:2302) |
| Pay | An item with its own shipping charge ($25), shipped to one address | **BROKEN** | The page showed 12500¢ before tax; the invoice came to 10000¢ before tax (each item's ship charge isn't an invoice line: ClientOrders.php:1068) |
| Shop | A guest adds a GO Code to the cart | **WORKS** | In the cart at $4.95 |
| Shop | A guest checks out | **WORKS** | Order 370, total 538¢ |
| Shop | A guest presses Back to the cart | **BROKEN** | Crashes with "TypeError: Orders::cancelUnpaid(): Argument #4 ($actor) must be of type int, null given, called in /tmp/claude-0/-home-user-traversence/0255" (QrIssue.php:415 passes null to an int; the API doesn't catch TypeError, so the cart shows a parse error) |
| Shop | Manage GO Codes lists a client order and its Pay now | **BROKEN** | Listed: yes; Pay now went through: the card is charged the whole total, 10860¢ (QrIssue.php:390 calls chargeCard with no amount, so total_cents), while the order records only the 3801¢ left: the 7059¢ deposit is charged twice |
| Copies | Duplicate a paid order | **WORKS** | Draft 372 with 1 line(s), pay mode full |
| Copies | The copy's lines don't claim the old collateral | **WORKS** | collateral_id on the copy: NULL |
| Copies | A test copy | **PARTIAL** | Named "TEST · Ironclad Client", qb_env "mock" |

| Width | Path | Case | Result | What the page did |
|---|---|---|---|---|
| 1280 | Entry | Back-Office → Orders: placing an order | **BROKEN** | A 38×38 icon ("+", its name only in aria-label), 333px down, under two "Runs" lines and four count tiles |
| 1280 | Open | An expired link | **WORKS** | This order's link expired on November 23. Reply to our email and we'll send it again. |
| 1280 | Open | A wrong link | **WORKS** | This link doesn't work Check you opened the whole link from our email, or write to support@traversence. |
| 1280 | Open | The order on its link | **WORKS** | no sideways scroll: true |
| 1280 | Approve | Approve the order (a double click) | **WORKS** | Approved. Thank you! |
| 1280 | Pay | A refused card | **WORKS** |  Name on the card Card number Expires Security code Billing ZIP Your card was declined (test). Nothing was charged. |
| 1280 | Pay | Pay in full by card | **WORKS** | Paid $108.60 Paid Thank you. |
| 1280 | Pay | The back button after paying | **WORKS** | Shows "Paid", no card form (no-store) |
| 1280 | Pay | An order paid outside QuickBooks (cash, check) | **BROKEN** | The card form is offered on its page although it has no invoice; a payment there is a real card charge with nowhere to post in the books |
| 390 | Entry | Back-Office → Orders: placing an order | **BROKEN** | A 38×38 icon ("+", its name only in aria-label), 671px down, under two "Runs" lines and four count tiles |
| 390 | Open | An expired link | **WORKS** | This order's link expired on November 23. Reply to our email and we'll send it again. |
| 390 | Open | A wrong link | **WORKS** | This link doesn't work Check you opened the whole link from our email, or write to support@traversence. |
| 390 | Open | The order on its link | **WORKS** | no sideways scroll: true |
| 390 | Approve | Approve the order (a double click) | **WORKS** | Approved. Thank you! |
| 390 | Pay | A refused card | **WORKS** |  Name on the card Card number Expires Security code Billing ZIP Your card was declined (test). Nothing was charged. |
| 390 | Pay | Pay in full by card | **WORKS** | Paid $108.60 Paid Thank you. |
| 390 | Pay | The back button after paying | **WORKS** | Shows "Paid", no card form (no-store) |
| 390 | Pay | An order paid outside QuickBooks (cash, check) | **BROKEN** | The card form is offered on its page although it has no invoice; a payment there is a real card charge with nowhere to post in the books |

## 3. What's broken, by harm (each checked in the code)

### a. Money

| # | What | Where | Seen |
|---|---|---|---|
| 1 | An order paid outside QuickBooks (cash, check) shows the card form. `pay()` charges it, though it has no invoice to post to | `order.php:395`; `CO::pay` 1174 | ran, at both widths |
| 2 | Manage GO Codes lists a client's order and its Pay now charges the card the **whole total**. The order records only what was left, so a deposit already paid is charged twice | `QR::orders` 439, `payCard` 390 (no amount) | ran |
| 3 | A part payment below the deposit on QuickBooks' page, then the card form asks for the **whole balance**, not the rest of the deposit | `CO::dueNow` 116 | ran |
| 4 | Changing shipping after approval rewrites the total to the **pre-tax subtotal** | `CO::recalc` 2302 via `Shipments::setMode` 60, `saveDest`, `deleteDest` | ran |
| 5 | An item's own shipping charge is left off the QuickBooks invoice: the client is shown $125, invoiced $100 | `CO::approve` 1068 | ran |
| 6 | Deposit terms: a second submit seconds after the deposit charges the balance too. The server can't tell a double click from "Pay the rest" | `CO::pay` 1185-1188 | ran (partial) |
| 7 | A real order approved while Payments is in Sandbox is invoiced and "paid" in the sandbox, which releases production and the vendor | `CO::approve` 1049, 1083 (`configured()`, not `open()`/`sameEnv`) | code |
| 8 | A card payment can be recorded twice when QuickBooks' webhook lands between the charge and the ledger | `CO::pay` 1194-1198 vs `fromQuickBooks` 1221 | code |
| 9 | One-offs, plans and domains: a retry after QuickBooks didn't answer makes a new order and a new key, so it may charge again. Plans, one-offs and domains have no lock | `OneOffs.php` 68-73, `Billing.php` 198-204, `AccountDomains.php` 434-443, `CartPlans::pay` | code |
| 10 | GO Codes: paying on QuickBooks' page, then by card before the webhook lands, charges twice | `QR::payCard` 384-390 | code |

### b. Wrong records or crashes

| # | What | Where | Seen |
|---|---|---|---|
| 11 | A guest's **Back to the cart** crashes (TypeError: null to an int). The cart shows a parse error and the order stays pending | `QR::cancelMine` 415 → `Orders::cancelUnpaid` 460 | ran |
| 12 | An order whose items were all removed after sending can be approved with nothing in it | `CO::approve` (no line check) | ran |
| 13 | Unticking Taxable on a catalog line is undone by the next page load | `CO::refreshPrices` 258 | ran |
| 14 | Quantity "abc" (or 0, or -5) on Add item is taken silently as 1 | `client-order.php` add actions; `addProduct` 272 | ran |
| 15 | A double click on Add it makes two identical lines (no busy state, no request key in the builder) | `client-order.php` | ran |
| 16 | Duplicate gives the copy's lines the old `collateral_id` | `CO::duplicate` 173 | code (the harness had none) |
| 17 | The webhook stops at the first failing order and still answers 200, so QuickBooks never retries | `qb-webhook.php` 23-34 | code |
| 18 | A cancelled GO Codes order's QuickBooks invoice stays open and payable | `Orders::cancelUnpaid` 469 | code |

### c. False words

| # | What | Where |
|---|---|---|
| 19 | Send says "The client has the link by email" when the email didn't go | `client-order.php:94`, `CO::mail` 2360 (ran) |
| 20 | QuickBooks down, not connected, or `guardReal` refusing all read "We couldn't finish approving just now…", and staff aren't told | `CO::approve` 1076-1078 |
| 21 | Entry point 1: placing an order is an unlabeled 38px "+", 333px/671px down (ran) | `widgets.php`, `tools.php:74` |

## 4. Decisions for Jason (one list, each with a recommendation)

1. **The Runs lines on Orders.**
   - Moving them to Scheduled tasks goes against his own rule of 2026-11-17 (`widgets.php:99`).
   - Recommend: keep each page's line, but fold it to one small "Runs ▾" under the table on this page, so Place an order
     and the table come first.
   - Or move them, as the session manager proposed, with a note on that rule.
2. **Sales tax from the ship-to, not the billing address**, when it's shipped.
   - Today `invoiceFor` sends billing as both addresses (`QBP` 599, 618).
   - Recommend: the ship-to when there is one (QuickBooks' automated sales tax uses it).
3. **Deposit orders: confirm the amount on the server.**
   - The page sends the amount it showed; the server refuses if what's due now differs ("The amount changed: here's
     what's due now"). This closes #3 and #6 and the "price changed since" case.
   - Recommend: yes.
4. **Orders paid outside QuickBooks:** no card form; staff record the payment (#1). Recommend: yes.
5. **Manage GO Codes shows only GO Code orders.** A client's order is paid on its own page (#2). Recommend: yes.
6. **A sandbox can't take a real order's approval:** approve refuses with the reason when Payments isn't open (#7).
   Recommend: yes.
7. **Staging test run:**
   - test-account orders on staging, sandbox only, `guardReal` holding, nothing reaching a client, cleaned up afterwards.
   - Recommend: yes, after this list is fixed.

Not built and out of this scope (each its own build, from the model):
- Send cart and the cart route (1.1–1.3).
- Place order from the Shop (1.3).
- Nothing in a cart expires (1.15 reminders).

## 5. The fix, once the list is answered

One release, at the causes above, proved by these same cases turning WORKS. The page:
1. **Place an order**: a large labelled primary button at the top, the same words on phones.
2. The table as it is (Jason: "Table view like it currently is is fine"), with columns Order, Client, Stage, Total,
   **Balance due**, **Next step** (a button) and Updated. Open orders by default; cancelled and completed sit behind the
   Stage filter. Today's stage names, saying so, until the model's stage names are built.
3. The Runs lines, per decision 1.
