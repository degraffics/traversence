# Order processing: Jason's rulings on the map (2026-11-20)

Jason sent this to the session manager for the Orders session: "response from Gem for order pro" (his review of the
map with Gemini, written "ready to copy and paste directly to Claude"). Taken as his answers to the map's §5 items and
his direction for the rewrite. Kept verbatim below. The Orders session writes each ruling into decisions/0101 as a
dated note quoting it before building on it (builder-playbook §3).

**Still open after this** (ask one at a time): when design work is earned / non-refundable and when production is
refundable (Part 2 §3 states the aim, not the rule); the order file check's limits (0176 R4); design capacity; a
fingerprint of the exact approved file; moving order mail onto the one dispatcher (0172).

---

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
