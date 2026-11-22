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

**1.2 Ways into the cart route.**
- Staff configure it (2026-11-20: "our orders either configured by us with separate building processes to yield
  something in a cart").
- Or the client configures it: "the product was selected by the client from the shop, its configuration, shipping, art
  details are set and the item is put into the cart".

**1.3 Held, then released.** "items requiring processing to be released for purchase are handled by the system
supported by admin and once approved, released, and the cart can be finalized."
- An item that needs preparing waits in the cart, not buyable, until it is released. One that needs none (a GO Code, a
  plan, a domain) is buyable at once, as today **(described, to confirm)**.
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

**1.7 Estimate → quote → transaction.** "if service is priced at time of ordering its a transaction if it can be priced
its an estimate, if its a tentative price which requires processing to generate final price its a quote." Then:
"Estimate is firmed up by processing to render a quote." Confirmed ("Yes") as:
- **Estimate:** a rough price. Processing firms it up into a **quote:** the final price. The client accepts the quote
  and pays: a **transaction**.
- A price already set when ordering goes straight to the transaction.

**1.8 Money at ordering, and if it stops.** "Paid at time of order." And: "paid a time of ordering, if its an estimate,
a deposit could be an option, because starts a process, paid in full at approval. Rejection or cancelation means only
to deposit is paid no balance owned if not approved."
- Set price: paid in full when ordered.
- Estimate: a deposit may be taken when ordered (it starts the work); paid in full at approval.
- Rejected or cancelled before approval: only the deposit is kept; no balance is owed.

**1.9 Paid in full before the vendor.** "Paid in full before order is released to vendor." And: "Admin can override
payment requirements to initiate vendor work orders pre funding."
- The vendor gets the work order only once it's paid in full. An admin can override that and send the vendor work
  order before funding.
- What a deposit is (2026-11-22, correcting my "a deposit starts our own work"): "Deposit does not control progress, it
  is a requirement of partial payment when needed to ensure we risk incurring expenses that might be incurred upon
  entering to production stage." See 1.13.

**1.10 File preflight.** "we will need file preflight checks." The checks are 0176's (resolution, bleed, size); the
limits are Jason's to set ("I'll set the limits", 0176).

**1.13 The stages after approval** (2026-11-22): "Staged for production when an order is approved. Deposit does not
control progress, it is a requirement of partial payment when needed to ensure we risk incurring expenses that might be
incurred upon entering to production stage. Vendors only get the jump once we confirm product and order meets the
production requirements (since staging). Upon stage confirm we will have the created work order that will be released
to the vendor which marks the order has entered the production queue. Workorders will be processed and its step of
movement will be monitored. Once production is complete, pack and ship happens. Once completed, the orders is released
for delivery. Once received it marks delivered."
1. **Staged for production:** set when the order is approved. We confirm the product and order meet the production
   requirements.
2. **Production queue:** on confirming the stage, the work order is created and released to the vendor.
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
| Ways to pay in the cart | Card or QuickBooks' page only; deposit, wire, check, cash, payment hold, invoice later only on the order page (§3 step 9) | 1.8 | **Gap:** the cart can't take a deposit or wire today |
| Guests | GO Codes yes; plans, one-offs, domains need a member (c3 §1.10) | 1.5 | Fits for GO Codes; new for the rest |
| Held item | No held state in the cart; items have "Before approval" statuses (Incoming, Under Review, Pending Approval, Changes Requested, Response Required) | 1.3 | New in the cart; the item statuses could carry the staff side |
| Client vs backend | Uploads staff-only; the client sees proofs, downloads only the one awaiting approval; vendor and files on the work order (§3a) | 1.4 | Fits |
| Approve for the client | Its page says "Use it when the client said yes in person, on the phone or by email; their proofs count as approved." The outside path is "Handled outside: create the work order" (c2) | 1.6 | **Differs:** today it also covers a yes given off the site. Is that use dropped? |
| Estimate, quote | None in our sales: 0101 "not an Estimate before"; the Shop's print card emails for a quote; Back-Office "Quotes & deals" coming (§2). Businesses have Quotes & invoices (`b_biz_quotes`, 0118, 0120), walled off (§1) | 1.7 | New for our sales; must not share the businesses' tables |
| What a deposit does | Any money in puts print "In production" and a work order can go (`received`, §3 steps 10–11) | 1.9 | **Changes built behaviour** |
| Vendor needs full payment | Work order allowed from paid or in production (§3 step 11; `workflows.php`) | 1.9 | **Changes built behaviour** |
| Invoice later | 0101: "With invoice-later terms, production starts on approval" | 1.9 | **Changes 0101** (needs a dated note) |
| Who can override | Release to production before payment: staff with order edit rights (c2) | 1.9 "Admin" | Narrows who can (to confirm) |
| Reorders | Same art reuses the proof with initials; changes start a new round; today's prices; becomes a draft for staff (`Collateral::reorder`) | 1.11 | Fits; only where it ends changes |
| Design-only | Finals released when there's no print, once paid in full (§3a) | 1.8 | Delivery fits; the money rule is new |
| Preflight, client upload | Nothing checks art; the client can't upload (§3a); 0176 to come, Replace file decided | 1.10 | Fits 0176; to build |
| Shipping price | Set by staff per item (`Shipments`, `setLineShip`) | 1.2 (client sets shipping) | **Gap:** no price without staff |
| Payment reminders | Three on the order page (`pay_wait_tick`); none in the cart | 1.3 | **Gap** on the cart route |

## 3. What this changes in the confirmed rulings

- **B.3** ("A recorded part payment that reaches the required deposit amount releases production"): under 1.9 a
  deposit releases our own work only, never the vendor. To reword: "releases our own work; the vendor waits for payment
  in full".
- **B.4** (paid in full before Shipped or Ready, except Release to production before payment, invoice later, outside):
  under 1.9 the check moves earlier, to the vendor work order, and its exceptions become the admin override and the
  outside path. Invoice later is no longer an exception.

## 4. Open, to walk through with Jason one at a time

1. ~~"Production" now means two things~~ Answered by 1.13 (2026-11-22). To confirm: whether payment in full (or the
   admin override) is one of the "production requirements" checked at staging, and whether Delivered ends the order or
   the client's "Yes, I have it" (Complete) still follows.
2. The admin override: admins only (and managers?), with a reason recorded.
3. Approve for the client: is the "yes given in person, by phone or email" use dropped (1.6 vs the map)?
4. Which route an order takes, and whether invoice-later, wire and check stay on the invoice route only. (B.4 as
   confirmed exempts "invoice later" from paid in full; 1.9 removes that exemption.)
5. The estimate's deposit: how much and who sets it, shown before paying.
6. How long a quote's price holds, and a vendor cost that changes.
7. A change after release (back to held), and after payment but before the vendor.
8. Shipping price on a client-configured order.
9. A mixed cart: pay for what's ready, or wait for all.
10. Reminders and an end date for released items not paid.
11. The order page as the conversation (1.12), and reorders (1.11): to confirm.
12. Still open from before: the file check's limits (at the build), design capacity, a fingerprint of the approved file,
    nudges for missing art, order mail onto the one dispatcher.

## 5. The parked work

- **#1212** (the chargeFor refund fix): unaffected; rests on "Fix in first release".
- **#1224** (the rulings release): B.1, B.2, B.5 and B.6 fit. Its B.3 starts production at the deposit and its B.4
  checks at Shipped and Ready with invoice later exempt; both change under 1.9 once Jason confirms §3 above.
