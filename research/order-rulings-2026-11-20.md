# Order processing: Jason's confirmed rulings (2026-11-20)

**Status: confirmed by Jason, 2026-11-20**, in the decision pop-up, after the session manager's review of Gemini's
answers. His confirmed text is first, verbatim; Gemini's earlier answers (below the line) are kept as history only.
The Orders session writes each ruling into decisions/0101 (and the accounting clarification into 0166) as dated notes
quoting this text before building on it.

**Still open** (ask one at a time, plainly first): design vs production money (Part 2 §3); the order file check's limits
(0176 R4, when the build reaches them); design capacity; a fingerprint of the approved file; order mail onto the one
dispatcher (0172).

## Confirmed text (Jason, 2026-11-20)

**1. Confirmed map baseline.** A. `order-system-map-2026-11-19.md`: confirmed as the accurate baseline snapshot of the live
codebase (Sync: 2026-11-19 16:21 UTC) and decision log (0035–0172). Rewrite direction: layer the 2026-11-20
Process-Before-Cart and Pre-Transaction Terms rules on top of this confirmed map, so each line item completes its required
preparation/confirmation gates (proofs/preflight, production files, vendor selection, drop-ship setup, or two-way
service/appointment confirmation) before releasing into the unified cart for final confirmation and payment.

**2. Settled rulings.**
- **B.1 Ship-to prefill:** change it to match the staff "Approve for the client" form. When an order already has a Ship to
  address (`orders.ship_*`) entered by staff, prefill the client's form with that destination and leave "To the billing
  address above" unticked. Default to the billing address only when Ship to is blank.
- **B.2 Proof gate before Send / cart release:** require proof approval for every printed item before Send or release to
  the cart/production, through one of three paths: (1) staff-built / custom proof, uploaded by staff and approved by the
  client (initials/sign-off); (2) exact reorder (`reuse_of`), reusing a previously approved proof with client
  confirmation, only when the product's specs and vendor are unchanged; if specs or vendor change, a fresh proof;
  (3) client-supplied artwork: the client's file passes our file check (0176) → we generate a proof showing what will
  actually print (trim and bleed) → the client approves that proof (not their raw file).
- **B.3 Part payments on QuickBooks' hosted invoice page:** record them in `order_ledger` as partial/deposit entries rather
  than waiting until the balance is $0.00. A recorded part payment that reaches the required deposit amount releases
  production as a deposit paid on our site does.
- **B.4 Centralized paid_in_full check:** enforce across every path (order page, Pulse, work orders, `/wo.php`, the
  Back-Office drawer), except where Release to production before payment, invoice later, or outside terms apply.
- **B.5 Refunds with an open work order:** offer "Cancel the work order and notify the vendor" in the refund flow, and show
  the vendor job's current status first (accepted or already-printed work carries unrecoverable vendor costs; what the
  client gets back is a separate decision).
- **B.6 Six unused builder actions** (`terms`, `line_save`, `addr_save`, `line_ship`, `options_save`, `stage`): delete as
  dead code, only after searching the live code to confirm nothing calls them.
- **B.7 Watermarks (0099 vs 0101):** 0101 replaced 0099: no watermark rule; no file downloads before the order is paid in
  full, except the active proof awaiting the client's approval.

**3. Architecture and accounting clarifications.**
- **Two layers (0166 vs QuickBooks Online).** The payment processing layer (the app + QuickBooks Payments) handles card
  charges, deposits, part payments, refunds and webhooks, updating `order_ledger` as the real-time operational
  transaction log that drives order states and production release gates. The books (QuickBooks Online) remain the
  authoritative accounting record for invoices, taxes, credits and financial reporting. Because 0166 §2's full
  double-entry ledger is not yet built (only the wallet is live) and QuickBooks holds years of accurate, verified data,
  QuickBooks stays primary for the books unless and until an internal double-entry ledger is completely built,
  reconciled in parallel against QuickBooks, and explicitly promoted.
- **Subscriptions (0108):** no dunning/retry loop on plans; a declined plan drops to Free.
- **Design vs production money (Part 2 §3):** remains an open question to ask, not an assumed rule.
- **Guard 0101's invariants:** unpicked options default to the first choice ("The first choice is the standard one"); tax
  only at approval/invoice creation; Traversence's own sales (`orders`) walled off from business/marketplace sales
  (`market_orders`, `b_biz_quotes`).

---

## History: Gemini's first answers (superseded by the confirmed text above)

## Part 1: Confirmed map and answers to open questions

**A. The map (`order-system-map-2026-11-19.md`).** Verdict: confirmed as accurate and complete as the baseline snapshot of
the live codebase (Sync: 2026-11-19 16:21 UTC) and decision log (0035–0172). Direction for the rewrite: layer the
2026-11-20 Process-Before-Cart and Pre-Transaction Terms rules on top of this confirmed map (where item-level
proofs/preflight, vendor setup, drop-ship configuration, and two-way confirmations gate release into the unified cart for
final confirmation and payment).

**B. Answers to open behavioral questions**

1. **Ship-to when the order already has one:** No, change it: match the staff "Approve for the client" form. When an
   order already has a Ship to address (`orders.ship_*`) entered by staff, prefill the client's approval/checkout form
   with that destination and leave "To the billing address above" unticked so it does not silently overwrite
   staff-configured drop-ship or venue addresses. Only default to the billing address when Ship to is blank.
2. **A proof for every printed item before Send / release to cart:** Yes, require a proof, satisfied through one of three
   explicit paths before Send or release to the cart/production: (1) Staff-built / custom proof: uploaded by staff and
   approved by the client (with initials/sign-off). (2) Exact reorder (`reuse_of`): uses previously approved artwork,
   confirmed by the client for reuse ("The same art as before", with initials) rather than a new proof round.
   (3) Client-supplied artwork: provided by the client, preflighted against our file-check limits (0176), and explicitly
   confirmed/approved by the client before release to the cart or production. New printed items and "Update & Reprint"
   reorders always go through proof approval; nothing goes to press without one of those three verified paths.
3. **Part payment on QuickBooks' own invoice page:** Yes, change it: record partial payments made on QuickBooks' hosted
   invoice page as deposit / partial `order_ledger` entries rather than waiting until the balance reaches $0.00. Keep
   `orders.status` and `order_ledger` synchronized with QuickBooks (0166) as partial payments arrive, only transitioning
   to `paid` once the balance clears.
4. **Shipped or Ready before the balance is paid:** Yes, enforce across all paths: centralize the payment check so the
   order page, Pulse, work orders and `/wo.php` enforce the same `paid_in_full` rule as the Back-Office → Orders drawer,
   except where the order explicitly has "Release to production before payment" (or invoice / outside pay terms) active.
5. **Refund on printed work and open work orders:** Yes, change it: when refunding printed work that has an active/open
   work order, surface an explicit "Cancel the work order and notify the vendor" action in the refund workflow so open
   vendor jobs are not left orphaned.
6. **Six unused builder actions** (`terms`, `line_save`, `addr_save`, `line_ship`, `options_save`, `stage`): Yes, delete
   them as dead code; edit-in-place superseded them (0101).
7. **Watermarks on proofs (0099 vs 0101):** 0101 replaced 0099: keep 0101's rule (no watermarking; restrict file
   downloads until the order is paid in full, allowing only the active proof awaiting sign-off to be viewed/downloaded).

## Part 2: Architectural and UX priorities for the rewrite

1. **Unify Process-Before-Cart into one checkout experience:** one Cart/Invoice flow for every entry path (today's private
   order approval page and the cart for GO Codes, one-offs, domains, plans); each line completes its pre-cart steps
   (proof/preflight approval, production file upload, vendor selection, drop-ship setup, or two-way service/appointment
   confirmation) before release into the cart for confirmation and payment. Staff- or client-configured, one review,
   explicit terms, one payment/invoice path.
2. **Explicit pre-transaction terms and availability:** stocked/ready vs back-ordered before payment; custom, made to
   order and services state deliverables, timelines and two-way confirmation before capture; passes, tickets and digital
   access define delivery and finality upfront.
3. **Design vs production tracks:** distinct tracks/lines under one parent order with explicit milestones and financial
   rules; clarify when design is earned/non-refundable vs when production/vendor costs apply; a refund of a printed item
   prompts work-order cancellation.
4. **Proof gates, collateral hygiene, reorders:** the three verified proof paths; paid, approved artwork stays in the
   client's Collateral Center / Your artwork; repeat orders fast, edits re-gated.
5. **Event-driven messages and reconciliation:** notifications from milestone events (intake/preflight needed, proof
   ready, approved/queued, shipped with blind tracking, payment/dunning/refund) without repeats; `order_ledger` and
   QuickBooks events aligned (idempotency by `request_key`, part payments recorded, the chargeFor test-refund bug fixed).
6. **Guard 0101's invariants:** unpicked options take the first choice; tax only at approval/invoice; Traversence's own
   sales (`orders`) kept apart from businesses' (`market_orders`, `b_biz_quotes`).
