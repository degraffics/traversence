# Order management, designed as one (2026-11-23)

Status: **design for review**: the Design reviewer first, then the session manager, then Jason, once, as one review.
Nothing here is built. Release B (0177's several destinations) and every other order release wait on this; only #1307
(Collect the balance) goes ahead, because it closes a real money gap on Order #12.

Why: Jason, 2026-11-23 (relayed by the session manager): "Our revenue comes from our ability to service our clients'
needs. I need this to be well thought out, highly effective, and super intuitive, so tighten up everyone." … "The flow
between pages and drill down is way off." He named what's missing: **issue a refund, credit memo, cancel, edit; credit
account, outstanding payments, take a payment; create a new service, product or catalog category.**

Built on: the map (`research/order-system-map-2026-11-19.md` and its nine readings), the ordering survey
(`research/ordering-ironclad-2026-11-23.md`), Jason's order model (`research/order-model-2026-11-21.md`, confirmed
2026-11-23), decisions 0098, 0101, 0108, 0128, 0129, 0130, 0177; the live mirror at Sync 2026-11-23 19:01 UTC
(`130f41a`); and live reads of Back-Office → Orders and Order #12 through the test accounts (`live-read.yml`; what was
read stays out of this file).

## 1. What Jason's screenshots show, checked

| # | Seen | Checked against | Cause |
|---|---|---|---|
| 1 | **Place an order** opens an old-style page: breadcrumbs "E-Commerce › Orders › New order", no command strip, Shortcuts in the corner; only a client form and **Start the order**; no products, price or what comes next | `admin/client-order.php` 459-481: with no order it calls `tv_admin_start(…, [['E-Commerce', '/admin/ecommerce.php'], …])` and draws the client form only | The builder's first step was never moved onto 0129's record page; the breadcrumbs still name the archived E-Commerce page |
| 2 | The archived **/admin/ecommerce.php#orders** is reachable with its own **New order**, and its words disagree: Order #7 "Link expired" there, "Waiting on the client · Send again" on Back-Office | `ecommerce.php` 176 (`sent` + `expired()` → "Link expired"), 187 (New order); `tools.php` `tv_order_next_step` 45 (`sent` → "Waiting on the client", no expiry) | Two lists of the same orders, each with its own wording; the board doesn't know a link expired |
| 3 | The tiles say **0 Waiting for payment** while Order #12 has $809.10 due | `tools.php` 98: the tile counts workflow stage `approved` only | Money is counted from the stage, so an order that moved on (ready, shipped) with money owed drops out of every money count; tiles don't filter the table |
| 4 | **Next step** mixes kinds of words: "Out the door", "At the printer", "Waiting on the client" | `tv_order_next_step` 39-53: one column carries a place, a stage and a whose-turn | Three state layers (money, stage, item statuses; map §3) squeezed into one phrase |
| 5 | Order #12's page: the notice stacks three states; Bill to's street and town empty; Shipping "One address" with empty fields on a pickup order; due date empty under "Due on receipt"; "Paid outside QuickBooks… we record it" while $809.10 is owed; the email loose at the top | `client-order.php` 559-563: `news` is a list of up to five strings, one per layer (money, stage, sandbox, out the door, opened); the terms and addresses are edit-in-place fields shown whether or not they apply | No single statement of where the order stands; fields shown by default rather than by what the order is (pickup, outside). The live read adds: the order is on pay mode *outside* (its QuickBooks invoice made by hand), released before payment, ready for pickup; "Paid outside QuickBooks… we record it" is the **pay mode's** name, read as if it were paid. Further down the page says the same thing again: **Complete the order** twice, and "Waiting on the client" beside "Still open: $809.10 still to be paid" |

## 2. Inventory: every order action today, and where it lives

Built = works end to end from where the problem shows. Partly = exists but in the wrong place, incomplete, or
without its way back. Missing = nothing.

### Placing and changing an order

| Action | Where today | State | Note |
|---|---|---|---|
| Place an order (staff) | `/admin/backoffice/orders/new` → `client-order.php` `create` | **Partly** | Client form only, old-style page (§1.1); items, terms and send come after, on the record page |
| Add items: catalog product, custom line, GO Code, shipping line, parts, projects | Order page `add_product`, `add_custom`, `add_go`, `add_ship`, `part_*`, `project_*` | Built | |
| New product or service from inside the order | Order page `new_product` (CO 218: "a product made from the order's + Add item, added to the order at once") | Built | Not offered in Place an order's first step |
| Edit before approval | Edit in place (`/api/admin/client-order.php` `setField`); `editable()` = draft or sent | Built | |
| **Edit after approval** | `reopen` (CO 1310) back to sent | **Partly** | Only when nothing is paid, no invoice payment and no work order out (`reopenBlock` 1296); otherwise "change it with Money (credit or refund)", a different page |
| Send (and send again) | Strip **Send**, workflow `send`, board **Send again** (`act: resend`) | Built | Board doesn't show an expired link (§1.2) |
| Approve for the client | Order page `staff_approve` | Built | Jason (model 1.6): a back-up, not wanted as a route |
| Split by address / Undo | Order page `split` / `unsplit` (release A) | Built | Release B replaces it with one invoice per destination |
| Duplicate, test copy, reorder same art | Strip ☰ `duplicate`, `reorder_art` | Built | |
| **Cancel** | Draft/sent: strip ☰ "Cancel the order" (`cancel`, CO 706). Approved and unpaid: `admin/order.php` `cancel` → `Orders::cancelUnpaid` 460 (voids the invoice, release A) | **Partly** | Two places, two pages; paid orders have no cancel (refund instead); no cancel window by stage (model 1.9) |
| Complete | Strip **Complete the order** (`complete`, CO 844); the client's "Yes, I have it" | Built | |

### Money

| Action | Where today | State | Note |
|---|---|---|---|
| Card checkout (client) | `order.php` → `ClientOrders::pay` | Built | Confirms the amount shown (release A) |
| **Take a payment** (staff: wire, check, cash, card by phone) | Order page **Record the payment** (`record_pay`, CO 1837), only while `pending` with money left | **Partly** | Only from inside one order; no card-by-phone; no one place to take a payment for a client with several orders |
| Release before payment (admins, with a reason) | Order page `release_hold` (CO 2129) | Built | |
| **Outstanding payments** (who owes what, how long) | Board's **Balance due** column (release A); tile "Waiting for payment" counts stage `approved` only | **Missing** | No list across orders, no aging, no total; the tile is wrong (§1.3) |
| **Issue a refund** | `admin/order.php` `refund` → `Orders::refundThroughQuickBooks` 194; refunds made in QuickBooks arrive by webhook | **Partly** | On a separate old page (☰ "Money: refund, credit, history", only once approved); admins only; no Undo (money can't be un-sent; it needs a confirm with the amount instead) |
| **Credit memo** | `admin/order.php` `credit` → `Orders::credit` 150 (a QuickBooks credit memo); credit memos made in QuickBooks arrive by webhook | **Partly** | Same old page; the policy order (fix, credit, refund) is in its header comment, not on screen |
| **Credit on account** (use it toward the next order) | `Orders::creditBalance` 123, shown on `admin/order.php` 118 | **Partly** | Issued, never **used**: `credit_used` is read but never written (map §6); credits aren't applied at checkout or on an invoice |
| **Credit account terms** (pay later on terms; model 1.8) | Pay mode `invoice` ("invoice later") | **Missing** | Jason: replaced by credit terms an admin grants after a credit process; nothing built |
| Card dispute (chargeback) and outcome | `admin/order.php` `chargeback`, `won`, `lost` | Built (old page) | |
| Check a payment with QuickBooks | `admin/order.php` `check`; order page QuickBooks box | Built | |
| Pause / resume a GO Code | `admin/order.php` `pause`, `resume` | Built (old page) | GO Codes only |
| Deposit (cost + 15%) | Order page Terms (release A, 0177 §3) | Built | Per destination in release B |
| Sales tax choice | Order page Terms (release A) | Built | |

### Catalog

| Action | Where today | State | Note |
|---|---|---|---|
| Create a product (options, run-size tiers) | Back-Office → **Products** (`backoffice/products`, `Products.php`); old `/admin/products.php` | Built | |
| **Create a service** | Products with `kind = service` (CO 221: "a service: one of it unless said") | **Partly** | A kind on a product, not its own entry point; only reachable from Products or an order's + Add item |
| **Create a catalog category** | — | **Missing** | Products have kind and status, no category (`Products.php` has no category; the list's columns: Item, Code, Kind, From, promo, Shown, Marketplace) |
| Materials & services (COGS) | Back-Office → Materials & services (`cost_list`) | Built | |
| Price lists, taxes & shipping, QuickBooks items | Back-Office sections | Built | |
| Plans & subscriptions | Back-Office → Plans | Built | Not orders (0108) |

### Pages that overlap

| Page | What it is | Proposal |
|---|---|---|
| `/admin/ecommerce.php` (archived, `archive.md` 49) | Old E-Commerce hub: its own orders tab, New order, words of its own | Redirect `#orders` and `?sec=orders` to Back-Office → Orders; its other tabs keep their own redirects; remove its New order. Jason clears the archive entry. |
| `/admin/order.php` | The money page for one order (fix, credit, refund, dispute, ledger, history) | Folds into the order page as its **Money** section (§4.3); the old address redirects to `…/orders/<id>#money` |
| `/admin/backoffice/production` ("Order management") | Every printed item across orders | Kept; renamed **Production** so "Order management" isn't two things |
| Back-Office → Payments (`/admin/quickbooks.php`), Money reports (`ecommerce.php?sec=reports`) | QuickBooks settings; old reports | Payments stays as settings; the new **Money** section (§4.4) takes the reports' place |

## 3. One state model

Every order has **three states, kept apart**, each one sentence, each implying one next action. The board shows them in
three columns; the order page states them once, in one notice line each, never stacked into one sentence.

### 3.1 Order (where the job stands)

| State | One sentence | Next action (who) |
|---|---|---|
| Draft | Being built; the client hasn't seen it. | **Finish and send** (us) |
| With the client | Sent; the client is reviewing proofs and the order. | **Send a reminder** (us), or wait for the client |
| Link expired | Sent, and its link ran out before the client approved. | **Send it again** (us) |
| Changes asked | The client asked for a change on a proof. | **Upload the new proof** (us) |
| Approved | The client approved it; it's staged for production. | **Confirm it's ready to make** (us) |
| In production | The work order is with the vendor. | **Follow the work order** (vendor), or wait |
| Out the door | Shipped, or ready for pickup. | **Confirm it arrived** (client) or **Complete** (us) |
| Complete | Delivered and closed. | **Reorder** (anyone), or nothing |
| Cancelled | Stopped before production; nothing owed. | — |

### 3.2 Money (what's owed)

| State | One sentence | Next action |
|---|---|---|
| Not due yet | Nothing is owed until the client approves. | — |
| Deposit due | Approved; the deposit (cost + 15%) is owed before production. | **Take a payment** |
| Balance due | Money is owed: the rest after a deposit, or all of it. | **Take a payment** (and, past due, **Send a reminder**) |
| Released before payment | An admin let it go ahead; the balance is still owed. | **Take a payment** |
| On credit terms | The client's account has terms; due by its date. | **Take a payment** by the due date |
| Paid | Paid in full. | — |
| Overpaid / credit | More came in than owed, or a credit was issued. | **Apply the credit** or **Refund** |
| Refunded / part refunded | Money went back. | — |
| Disputed | The card holder disputed a charge. | **Answer the dispute** |

### 3.3 Fulfilment (where the goods are)

| State | One sentence | Next action |
|---|---|---|
| Nothing to send | Digital, service or GO Codes only. | — |
| Not started | No work order yet. | **Create the work order** |
| At the vendor | The vendor has the work order. | **Follow it** |
| Shipped | On its way, with tracking. | **Confirm delivery** |
| Ready for pickup | Waiting at our address. | **Confirm pickup** |
| Delivered | The client has it. | — |

### 3.4 One next action, by rule

The order's **Next step** is the first of these that applies; it's one verb phrase, always a button, always landing on
the spot that does it:

1. A dispute is open → **Answer the dispute**.
2. Money is owed and the order is out the door or past due → **Collect the balance** (#1307).
3. The order state's next action (§3.1), unless it waits on someone else, when the step says who: "Waiting on the
   client", in words, with **Send a reminder** as the button.
4. Money owed (deposit or balance) → **Take a payment**.
5. Fulfilment's next action.

**Every Next step is a task** (Jason's standing rule, 2026-11-23, CLAUDE.md #1308): **1. see the task** (the row's
or line's one button, where the problem shows), **2. do the task** (the button does it there: a form in place or a
drawer, never a trip to another page), **3. the task completes and its status is confirmed in place** (the button is
replaced by what happened, checked against the record, with Undo: "✓ Paid $412.00 by check · Undo"), **4. the next
step** (the row's state and Next step move on by §3's rule; nothing claims success without the check). The same holds on
the money pages: Outstanding's Take a payment and Remind, Credits on account's Apply to an order, Refunds & credit
memos' Issue; each confirms in its row with Undo (a refund, which can't be unsent, confirms the amount before and shows
QuickBooks' reference after).

Board columns: **Order** (state + age), **Money** (state + amount), **Delivery** (state), **Next step** (one button).
The order page's notice: three short lines in the same words, in the strip's notices box; nothing repeated below.

## 4. Pages and drill-down

```
Back-Office
├── Orders (the job board)         tiles filter the table: Needs us · Waiting on the client · Money owed · In production · Out the door
│    └── Order #N                  one record page: strip, three state lines, Items, Money, Delivery, History
│         ├── an item (drawer)     proof, files, vendor, status
│         ├── a payment (drawer)   amount, how, reference, receipt; Refund / Credit from here
│         └── a shipment (drawer)  carrier, tracking, items
├── Place an order (guided)        client → items → ship-to and tax → review → send   (one record page that fills in)
├── Money                          Outstanding (who owes what, aging) · Take a payment · Credits on account · Refunds & credit memos
└── Catalog                        Products · Services · Categories · Materials & services · Price lists
```

### 4.1 Orders (the job board)

- Four state tiles count from the order state, not the workflow stage, and **are the state filter** (pressed = on):
  Needs us, Waiting on the client, In production, Out the door. **Money owed** stands apart as its own tile (the total
  and how many orders, in any stage), so the five never read as a sum.
- One filter button beside the search holds only the other filters (Everyone's / Mine, Open / All), showing its
  choice, with the brand's filter icon.
- One table (`design/table-standard.md`): Order, Client, Where it stands, Money (state + amount), Delivery, Next step;
  the count and paging under it ("1–4 of 4 that need us"); each tile's empty state with its way forward ("Nothing
  needs you" · Place an order · Show waiting on the client).
- Buttons (brand.md §4): **Place an order** is the page's one filled button; each row's Next step is outlined.
- A finished step replaces its button in the row, with its way back: "✓ Paid $412.00 by check · Undo", "✓ Sent Nov
  23 · Copy the link".

### 4.2 Place an order (guided)

One record page that fills in from the top; a finished step folds to one line with **Change** (Jason's rule, 2026-11-23:
a finished step replaces its form):

1. **Client**: find a client (listing, member, email) or add one; their billing address from their record.
2. **Items**: search the catalog; **New product or service** in place (the existing `new_product`), with a category.
3. **Where and tax**: pickup, shipped (to one or several addresses; release B), or nothing to send; the tax choice.
4. **Payment terms**: paid in full, deposit (cost + 15%, shown with how it's figured), or the client's credit terms.
5. **Review and send**: what the client will see, then **Send the order** (or Save as a draft).

Every step has Back; a draft is kept at every step (nothing lost).

### 4.3 The order page

- Strip (0129): Back, "Order #N", Save/Close; ☰ for Duplicate, Reorder, Test copy, Cancel.
- **Three state lines** (§3) in the notices box, each with its one button. The Money line's action (Collect the
  balance) is the page's one filled button. The Order line's Complete is greyed with "$X still owed" while money is
  owed (#1307: an admin's override with a reason).
- Sections: **Client** (Bill to, the email, never loose), **Items**, **Delivery** (only what applies: pickup shows our
  address and no fields), **Money** (§4.3a), **Work orders**, **Tell the client**, **History**.
- Fields that don't apply aren't shown (a pickup order has no ship-to fields; an order paid outside has no card text).

**4.3a Money on the order** (replaces `admin/order.php`):
- Total, Paid and Owed always; Credits and Refunded only when they aren't $0.00.
- Taking a payment is the Money line's button (one action, once). Here, outlined: **Apply a credit** (from the
  client's account), **Issue a credit memo**, **Refund…** (from a payment's ⋯, the amount and reason confirmed),
  **Cancel the order…** (by the cancel window, §5 Q2).
- After a payment: the Money line reads "Paid. $809.10 by check #2231 today" with "✓ Recorded · Undo", and the payment
  is a row with its ⋯.
- The policy order shown on screen: fix it first, then a credit, a refund only when neither works (0098).
- The ledger and history below, only ever added to.

### 4.4 Money

- Views (tabs that wrap on a phone): **Outstanding**, **Credits on account**, **Refunds & credit memos**. **Take a
  payment** is an action, not a view: the header's one filled button, and outlined on each row; it opens a drawer.
- **Outstanding**: every order with money owed, by client: amount, oldest due, aging (current, 1–30, 31–60, 61–90, 90+),
  last reminder; Take a payment and Remind on each row ("✓ Reminded today · Undo" after).
- **Take a payment** (drawer): the client, the amount, how it came in, the reference, the open orders ticked oldest
  first (0177 §4), a receipt; Record $X; Undo on each order afterwards.
- **Credits on account**: each client's credit, issued and used, expiring (90 days, 0098); Apply to an order.
- **Refunds & credit memos**: every one made, here or in QuickBooks (the webhook's), with its order.

### 4.5 Catalog

- Tabs: **Products**, **Services**, **Categories**, Materials & services (COGS), Price lists; the header's one filled
  button is New for the tab shown.
- **Products** and **Services** as two lists of one catalog (`Products`, by kind): name, category, from price, whether
  its cost is checked (0177 §3's deposit needs it), shown or hidden; ⋯ per row.
- **Categories**: a new list; a product or service has one; the Shop and Place an order's search group by it.
- Materials & services and Price lists stay where they are, under Catalog.

## 5. Questions for Jason: money policy only

Collected here for the one review, not asked one by one.

1. **Refunds without asking.** May an admin refund up to an amount (say $250) without Jason, larger ones needing his OK?
   Today any admin can refund any amount on `admin/order.php`.
2. **The cancel window.** Model 1.9: an order can be cancelled while staged, not once production schedules it. Is the
   window "until the work order is accepted by the vendor", or a set time after approval?
3. **Credit on account.** Should a client's credit apply **by itself** to their next invoice, or only when staff apply it?
4. **Credit terms.** Model 1.8: an admin grants credit terms after a credit process. Which terms are offered (Net 15,
   Net 30), and who besides Jason may grant them?
5. **Card by phone.** Should staff be able to take a card by phone (QuickBooks Payments, keyed in), or only send the
   client their link?
6. **"Yes, I have it" with money owed.** When the client confirms they have an order that still owes money, should it
   complete (today: it does, and staff are emailed what's still open), or stay open as "Delivered, balance due" until
   it's paid?

## 6. Releases, money paths first

Each release is one zip, tested end to end against §3's state model, at 1280 and 390, through the Design reviewer,
the session manager and Jason.

| # | Release | What it closes |
|---|---|---|
| M1 | **State model and the board**: the three states, Next step by rule, tiles that count from money and filter; the order page's three state lines | §1.3, §1.4, §1.5's stacked notice |
| M2 | **Money on the order page**: `admin/order.php` folded in (payments, credit memo, refund, dispute, cancel); Take a payment | Refund, credit memo, cancel, take a payment |
| M3 | **Money section**: Outstanding (aging), Take a payment across orders, Credits on account (and their use), Refunds & credit memos | Outstanding payments, credit account |
| M4 | **Place an order, guided**; the archived E-Commerce orders redirect | §1.1, §1.2 |
| M5 | **Release B**: one invoice per destination (0177 §1) inside the new flow | Several destinations |
| M6 | **Catalog**: Services as their own list, Categories | New service, catalog category |
| M7 | Credit terms on accounts (once §5 Q4 is answered) | Delayed payment by terms |

## 7. Mockups

`design/order-management/` (HTML, rendered at 1280 and 390), each with its after-state: the board (and after a
payment and a resend; and empty), Place an order (and a step folded after Next), the order page (and after a payment),
Money (Outstanding, the Take a payment drawer, after a reminder), the Catalog. They are what the Design reviewer checks against brand.md, `design/css-theme-brand.md`,
`design/table-standard.md` and 0058 §11.
