# Jason's order model (2026-11-21), for review

Status: **Jason's model, written from his words in the Orders session (2026-11-20 to 21), for his review.** It is not a
decision yet, and nothing is built from it. Each point quotes him. Where a point is my description that he didn't
object to but hasn't confirmed either, it is marked **(described, to confirm)**. It replaces the draft
`research/one-cart-proposal-2026-11-20.md`, which drew its own shape of the cart (Jason, 2026-11-21: "It is obvious you
are not able to see how the order system should work").

Checked against: `research/order-system-map-2026-11-19.md` (the map, Jason's confirmed baseline), its nine readings,
and `research/order-rulings-2026-11-20.md` (his confirmed rulings B.1–B.7, §1, §3).

## 1. The model

**1.1 Two routes in the middle.** "Middle is two routes, not a change."
- The **invoice route** (today's, map §3): staff build the order, the client approves proofs and the order on its
  private page, and pays the invoice. Jason: "Our invoice is built for what I've been doing for years."
- The **cart route**: items are prepared, released into the client's cart, and the client checks out.
- Both start the same way (an order, its items, proofs) and finish the same way (receipt, work order, vendor,
  shipping, completion).
- **Which route** (2026-11-22): "Send cart or send invoice as send option". On an order staff build, Send offers both:
  **Send cart** (the cart route) or **Send invoice** (the invoice route), chosen when sending. An order the client
  configures in the Shop is already in the cart **(described, to confirm)**.
- **How the cart is paid** (2026-11-22): "Dont alllow wire and check in the cart." The cart takes a card or QuickBooks'
  own page, as today; wire, check, cash and the payment hold stay on the invoice route.

**1.2 Ways into the cart route.**
- Staff configure it (2026-11-20: "our orders either configured by us with separate building processes to yield
  something in a cart").
- Or the client configures it: "the product was selected by the client from the shop, its configuration, shipping, art
  details are set and the item is put into the cart".

**1.3 Held, then released.** "items requiring processing to be released for purchase are handled by the system
supported by admin and once approved, released, and the cart can be finalized."
- **The ordering queue and the cart** (2026-11-22, replacing "held in the cart"): Jason chose option B, "B: yes,
  approval moves item to cart or invoice."
  - **Add to cart** (or Buy now) for an item that needs no processing (a GO Code, a plan, a domain, a stocked item).
  - **Place order** for an item that needs processing: it goes into the client's **ordering queue** and is prepared
    there (proof, preflight, our backend work). The queue is the order while it's being prepared, on its order page.
  - **Approval moves the item** to the cart (cart route) or the invoice (invoice route), by itself.
  - **The cart only ever holds items ready to buy;** checkout pays everything in it.
  - On the cart page, the ordering queue is shown first ("Being prepared": each item, where it stands, its proof to
    approve), the ready items below with Checkout.
- Each line keeps its own process (2026-11-20): "it can be graphic design, printed product, etc handles as separate
  processes, but becomes on cart (Invoice) for approval(confirmation) and payment".
- What releases a printed item (2026-11-20): "proofs get approved, production files get uploaded, vendor is selected,
  drops ship is configured, etc. then released."

**1.4 What the client sees, and what stays ours.** "our files and vendor: our backend, not seen by client."
- The client sees and approves: the proof, and the item as configured (product, options, quantity, where it ships).
- Our backend only: production files, vendor, drop-ship.

**1.5 The cart without an account.** "Cart no account means create an account or as guest."

**1.6 Approve for the client.** "This is something we dont really want. It was a back up to push orders when they have
been handled by payments outside what the system can handle." So the client always checks out themselves; staff never
check out for them. Orders handled outside the system go through the staff's outside path.
- **Kept for approvals given another way** (2026-11-22): "I see email approvals mostly, so having a way to click
  approval based on alternative means is important for not. It will require orders to be processed as if client
  approved it so payments must still function properly." So staff can still record the client's approval given by
  email (or another way); the order is then processed as if the client approved it, and payment still runs as normal
  (the invoice is made and the client pays it). It approves; it never pays.
- Who and the record (2026-11-22): "Any staff with order edit rights, and attach the email." Today's rights stay;
  new is the client's email attached to the order as the record of the approval.

**1.7 Estimate → quote → transaction.** "if service is priced at time of ordering its a transaction if it can be priced
its an estimate, if its a tentative price which requires processing to generate final price its a quote." Then:
"Estimate is firmed up by processing to render a quote." Confirmed ("Yes") as:
- **Estimate:** a rough price. Processing firms it up into a **quote:** the final price. The client accepts the quote
  and pays: a **transaction**.
- A price already set when ordering goes straight to the transaction.

**1.8 Money: paid in full, or a deposit** (2026-11-21 and 22).
Jason: "A deposit required is terms may change after approval is released into production vs paid in full upon approval
all terms are final and releasing to production." And: "Orders are expected to be paid in full at the time of
approval, so a deposit lowers the payment required to a less than full amount, but only until it is released into the
productions actual schedule."
- **Paid in full** is the rule: at approval, with all terms final; then released to production.
- **A deposit** is the exception, for orders whose terms may still change after approval: taken at approval, it lowers
  what's due until the order is released into production's actual schedule. Then the balance is due.
- A set price is paid when ordered (2026-11-21: "Paid at time of order").
- **How long a quote holds** (2026-11-22): "14 days, then the quote must be refreshed". A quote released to the cart or
  sent as an invoice holds its price for 14 days; after that it's refreshed before checkout. (Today an order's link
  works 14 days, `ClientOrders::DAYS`, and prices refresh from the catalog at Send.)
- **Delayed payment** (2026-11-22, on invoice-later terms): "Credit account terms needed for delayed payments. Or admin
  override to proceed without payment." So invoice later, as a pay mode anyone can be given, is replaced by a client's
  credit account terms, or an admin's override on the order.
- **Credit accounts** (2026-11-22): "Admin grants credit to account upon cridet process compliance and completion.
  Apporvals required, credit terms confirmed, balance due per terms. Deposits used to offset risks."
  - An admin grants credit to an account once a credit process is complied with and completed.
  - Approvals are required, and the credit terms are confirmed.
  - The balance is due per those terms (not before delivery, as in 1.9).
  - Deposits can still be taken to offset risk.
  - To walk through later: what the credit process asks for, and who approves.
- **How much** (2026-11-22): "Percentage set per order by staff, 65% default." Today staff set the % per order with a
  50% default (`ClientOrders::setField`, 1227); the default becomes 65%.
- **A deposit on an estimate** (2026-11-22): "an estimate is used for when we dont have all things confirmed, so it could
  easily be used a tool to secure the deposit and enter into the stage where we can firm up things before releasing an
  estimate to become an invoice/order." So a deposit may also be taken before approval, on an estimate, to secure the
  work of firming it up into the quote that becomes the order.
- **By kind** (2026-11-22): "1. A deposit for made to order is non-refundable, so design due at time of ordering. 2.
  Product, due at time of ordering/approval with small windows to cancel before becoming non-refundable."
  - **Made to order (design and the like):** due when ordered; a deposit on it is non-refundable.
  - **Product:** due when ordered or approved, with a small window to cancel before it becomes non-refundable (1.9).
  - A deposit on an estimate counts toward the order it becomes (2026-11-22: "Yes, the estimate deposit counts toward
    the order.").

**1.9 Cancelling, the vendor and the balance** (2026-11-22): "Deposit required at time of approval. There is a small
window where an order can be canceled while we process it for production, but once production schedules it and enters
into the in production phase it can no longer be canceled. Deposits are non-refundable at this point and the remaining
balance becomes due. Balance must be paid before order is release for delivery, delays may result in orders being held
for payment before shipping." And: "(a) says the vendor work order waits for payment in full: a deposit overrides this
statement."
- While the order is staged, it can still be cancelled.
- Once production schedules it (the work order to the vendor), it can't be cancelled; the deposit is non-refundable and
  the balance is due.
- The vendor work order waits for payment in full (2026-11-21: "Paid in full before order is released to vendor"),
  except on a deposit order, where the deposit overrides it, and except an admin's override (2026-11-21: "Admin can
  override payment requirements to initiate vendor work orders pre funding").
- The override is for **admins only, with a reason recorded** (2026-11-22: "Admins only, with a reason recorded."). Today
  any staff member with order edit rights can release an order to production before payment (c2); that narrows.
- **When the cancel window ends** (2026-11-22): "When the work order issued has been confirmed by the vendor or admin as
  entered into the production schedule. My past experience is that a client will call asking if production has
  started, i contact the vendor for a status check and if printing has not started I can pull the order allowing it to
  be modified and then resubmitted. It does require real time status and it cant always be stopped so if there is a
  specific trigger I would say the vendor must be addressed and if able can cancel the progress without requiring
  compensation."
  - The window closes once the vendor, or an admin, confirms the work order has entered the production schedule.
  - Until then, a client's request to cancel or change goes to the vendor for a real-time status check. If printing
    hasn't started and the vendor can stop it without compensation, the order is pulled, can be modified, and is
    resubmitted.
  - It can't always be stopped: the vendor's answer decides.
  - Built today: the vendor's Accept and its statuses on /wo.php; Cancel on a work order emails the vendor "Please stop
    work order … Reply if anything is already done" (`WorkOrders::cancel`); Reopen for changes only works with no work
    order out.
  - What closes it (2026-11-22): "once the vendor gives the job number and has enter scheduled the production, so
    production queue set." The vendor's Accept, with its job number and the production scheduled, sets the order's
    **Production queue**; that ends the window. (Today's Accept already asks for the job number and who received it,
    and accepts the must-have date.)
- **Changes after release** (2026-11-22):
  - In the cart, before checkout: the item goes back to the ordering queue ("held"), is prepared again (a new proof if
    needed), and moves to the cart again on approval ("Yes").
  - After payment, before the vendor accepts: "if cancelled, we go back to order pending approval, and follow the
    held/prepared process to adjust the cart for approval again. Payments take are applied to current cart, so due is
    easy to understand." The pulled order returns to pending approval, goes through held and prepared again, and comes
    back to the cart for approval; what's already paid is applied to the new cart, which shows what's left due.
  - When the changed order costs less than what's paid (2026-11-22: "we have a rule for this already"): decisions/0098's
    "Fix it, credit, then refund" applies. The difference is a credit on the client's account (12 months, not cash,
    0098's addendum of 2026-10-28), used toward anything we sell; a refund only when a credit doesn't work. Gap: credits
    aren't applied at checkout today (`credit_used` is read but never written; map §6).
  - Needed (2026-11-22): "will need a way to handle account credits applied to ordering balances." Account credits are
    applied to what an order owes, on both routes.
- The balance must be paid before the order is released for delivery; a late balance holds it before shipping.
- What a deposit is: "Deposit does not control progress, it is a requirement of partial payment when needed to ensure
  we risk incurring expenses that might be incurred upon entering to production stage."

**1.10 File preflight.** "we will need file preflight checks." The checks are 0176's (resolution, bleed, size); the
limits are Jason's to set ("I'll set the limits", 0176).

**1.14 Shipping** (2026-11-22): "Need a shipping module integrated into the platform for calculating shipping costs
and shipping vendor management." Shipping costs are calculated by the platform (so a client configuring an item sees
its shipping before checkout), and shipping vendors (carriers) are managed there. Built today: shipping set by hand per
item, manifests, carriers named on lines (`Shipments`, `setLineShip`, `CARRIERS`); the Marketplace has its own,
separate. A module of its own, to be scoped.

**1.15 Waiting states and their notices** (2026-11-22): "Abandoned cart, stalled processing, saved for later,
reorders... all need notifications and definitions." Then: "4hr, 24hr, 72hr, 7 business days, 14 days." and "Reorder:
suggestions 3 to 6 months after initial order, give client a way to set when to reorder alert."
- **Reminder schedule:** 4 hours, 24 hours, 72 hours, 7 business days, 14 days (the 14th day is also when a quote's
  price lapses, 1.8). To confirm: that it applies to an abandoned cart and to stalled processing alike, to whoever
  holds the next step.
- **Saved for later:** an item moved out of the cart on purpose (definition described; its notices to confirm).
- **Reorder:** a suggestion 3 to 6 months after the first order; the client can set when their reorder alert comes.
- The definitions (from the Orders session's table, 2026-11-22): **stalled processing**, an item in the ordering queue
  that hasn't moved, waiting on the client, us or the vendor; **abandoned cart**, released items not checked out;
  **saved for later**, moved out of the cart on purpose; **reorder**, a completed item that can be ordered again.

**1.13 The stages after approval** (2026-11-22): "Staged for production when an order is approved. Deposit does not
control progress, it is a requirement of partial payment when needed to ensure we risk incurring expenses that might be
incurred upon entering to production stage. Vendors only get the jump once we confirm product and order meets the
production requirements (since staging). Upon stage confirm we will have the created work order that will be released
to the vendor which marks the order has entered the production queue. Workorders will be processed and its step of
movement will be monitored. Once production is complete, pack and ship happens. Once completed, the orders is released
for delivery. Once received it marks delivered."
1. **Staged for production:** set when the order is approved. We confirm the product and order meet the production
   requirements.
2. **Production queue:** on confirming the stage, the work order is created and released to the vendor; the queue is
   set once the vendor accepts it with its job number and has scheduled the production (2026-11-22).
3. **Work orders processed,** each step of their movement monitored.
4. **Pack and ship,** once production is complete.
5. **Released for delivery,** once that's done.
6. **Delivered,** once it's received.

**1.11 Reorders (described, to confirm).**
- Exact: the system checks it's the same (product, options, and our vendor in the backend), and the earlier approved
  proof is reused, confirmed with initials (B.2 path 2).
- Changed: prepared like a new item (new proof, preflight on new art).
- Either way it ends in the cart.

**1.12 The order page as the conversation (my suggestion, to confirm).** Jason asked how the conversation should be
handled if we use the cart. Suggested: the same order record and private order page on both routes, keeping proofs,
changes, notes, files, status and receipts there, with the cart only the checkout and each released item linking back
to its order page. Not yet confirmed.

**Kept from the confirmed rulings, not changed today:** B.1 (ship-to prefill), B.2 (three proof paths), B.5 (refund
with an open work order), B.6 (delete the six actions after a live search), B.7 (no watermark), §3 (the two ledger
layers; plans have no dunning; the invariants: first choice is standard, tax at approval, our sales walled off from
businesses'). Also kept: **terms before money**, for our own sales too (0101's and 0135's notes of 2026-11-20, "Yes,
both"): a physical item is sold only as stocked and ready, or back-ordered, known before paying; made to order, custom
and services state what's promised before money is taken, with two-way confirmation where needed; tickets are final,
the pass is the product. It shapes 1.2's client-configured items and 1.8's money at ordering.

## 2. Against the map (what's built and decided)

| Point | The map | The model | Result |
|---|---|---|---|
| Two routes | Client order ends on the private order page as the invoice (§2 row 1, §3); the one cart is a separate way for GO Codes, one-offs, domains, plans (§2 rows 5–7) | 1.1 | Fits: both exist; new is client orders ending in the cart |
| Checkout makes the invoice | The cart's checkout makes the QuickBooks invoice with tax (c3 §5.1); the order page at approval (0101) | 1.3 | Fits: same moment |
| Ways to pay in the cart | Card or QuickBooks' page only; deposit, wire, check, cash, payment hold, invoice later only on the order page (§3 step 9) | 1.1, 1.8 | Wire and check stay out of the cart (fits). **Gap:** the cart can't take a deposit today |
| Guests | GO Codes yes; plans, one-offs, domains need a member (c3 §1.10) | 1.5 | Fits for GO Codes; new for the rest |
| Ordering queue | The order while draft or sent, on its private page, with "Before approval" item statuses (Incoming, Under Review, Pending Approval, Changes Requested, Response Required) | 1.3 | Fits: the queue is the order being prepared; new are Place order from the Shop, the queue shown on the cart page, and approval moving items to the cart |
| Client vs backend | Uploads staff-only; the client sees proofs, downloads only the one awaiting approval; vendor and files on the work order (§3a) | 1.4 | Fits |
| Approve for the client | Its page says "Use it when the client said yes in person, on the phone or by email; their proofs count as approved." The outside path is "Handled outside: create the work order" (c2) | 1.6 | **Fits:** kept for a yes given by email or another way; payment runs as normal |
| Estimate, quote | None in our sales: 0101 "not an Estimate before"; the Shop's print card emails for a quote; Back-Office "Quotes & deals" coming (§2). Businesses have Quotes & invoices (`b_biz_quotes`, 0118, 0120), walled off (§1) | 1.7 | New for our sales; must not share the businesses' tables |
| What a deposit does | Any money in puts print "In production" and a work order can go (`received`, §3 steps 10–11) | 1.8, 1.9 | **Fits more closely than thought:** a deposit order can go to the vendor; new are the cancel window, the balance due on scheduling, and the hold before shipping |
| Vendor needs full payment | Work order allowed from paid or in production (§3 step 11; `workflows.php`) | 1.9 | **Changes built behaviour** for orders on full-payment terms (and invoice later); deposit orders keep today's behaviour |
| Invoice later | 0101: "With invoice-later terms, production starts on approval"; due terms on receipt, Net 15, Net 30 (`DUE_TERMS`) | 1.8 | **Changes 0101:** replaced by credit account terms (new: no credit account exists; today's "credits" are credit memos) or an admin override |
| Who can override | Release to production before payment: staff with order edit rights (c2) | 1.9: admins only, reason recorded | Narrows who can |
| Reorders | Same art reuses the proof with initials; changes start a new round; today's prices; becomes a draft for staff (`Collateral::reorder`) | 1.11 | Fits; only where it ends changes |
| Design-only | Finals released when there's no print, once paid in full (§3a) | 1.8 | Delivery fits; the money rule (paid in full, or a deposit when terms may change) applies |
| Preflight, client upload | Nothing checks art; the client can't upload (§3a); 0176 to come, Replace file decided | 1.10 | Fits 0176; to build |
| Shipping price | Set by staff per item (`Shipments`, `setLineShip`) | 1.14 | **Gap:** a shipping module (costs, carriers) to build |
| Payment reminders | Three on the order page (`pay_wait_tick`); none in the cart | 1.3 | **Gap** on the cart route |

## 3. What this changes in the confirmed rulings

- **B.3** ("A recorded part payment that reaches the required deposit amount releases production"): fits 1.8–1.9 for
  deposit orders. New: the balance is due once production schedules it, and must be paid before delivery.
- **B.4** (paid in full before Shipped or Ready, except Release to production before payment, invoice later, outside):
  under 1.9 the balance is checked before release for delivery (close to B.4), and also before the vendor work order on
  full-payment terms. Release to production before payment becomes the admin override; invoice later is to confirm.

## 4. Open, to walk through with Jason one at a time

1. ~~"Production" now means two things~~ Answered by 1.13 and 1.9 (2026-11-22). To confirm: whether Delivered ends the
   order, or the client's "Yes, I have it" (Complete) still follows. Invoice-later terms: answered in 1.8 (credit
   account terms or an admin override).
2. ~~The admin override~~ Answered: admins only, with a reason recorded (1.9).
3. ~~Approve for the client~~ Answered (1.6): kept for approvals given by email or another way, processed as if the
   client approved, payment as normal.
   Who and the record answered too: staff with order edit rights; the email attached.
4. ~~Which route an order takes~~ Answered (1.1): Send cart or Send invoice when sending.
   Wire and check: not in the cart, invoice route only (1.1). (B.4 as
   confirmed exempts "invoice later" from paid in full; 1.9 removes that exemption.)
5. ~~The deposit's amount~~ Answered (1.8): a % per order set by staff, 65% default. Cancel window: answered (1.9); the vendor's
   Accept with its job number and the production scheduled ends it.
6. ~~How long a quote's price holds~~ Answered (1.8): 14 days, then refreshed.
7. ~~A change after release~~ Answered (1.9), with 0098's "Fix it, credit, then refund" for a lower total.
8. ~~Shipping price~~ Answered (1.14): a shipping module, to be scoped.
9. ~~A mixed cart~~ Answered (1.3): the cart only holds ready items; items being prepared are in the ordering queue.
10. ~~Reminders~~ Answered (1.15); to confirm the schedule covers both an abandoned cart and stalled processing, and
    saved-for-later's notices.
11. The order page as the conversation (1.12), and reorders (1.11): to confirm.
12. Still open from before: the file check's limits (at the build), design capacity, a fingerprint of the approved file,
    nudges for missing art, order mail onto the one dispatcher.

## 5. The parked work

- **#1212** (the chargeFor refund fix): unaffected; rests on "Fix in first release".
- **#1224** (the rulings release): B.1, B.2, B.5 and B.6 fit. Its B.3 (production at the deposit) fits 1.8–1.9; its B.4
  (checked at Shipped and Ready) is close to 1.9's "before release for delivery" but its exceptions change, and the
  vendor check on full-payment terms, the cancel window and the balance due on scheduling are new.
