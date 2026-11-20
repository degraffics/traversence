# Draft decision: one cart is the invoice, each line released to it by its own process (2026-11-20)

Status: **draft, for the session manager's review and then Jason's OK.** Nothing here is built. Orders session.
Built on: the live mirror at "Sync: 2026-11-20 17:54 UTC" (`6f955cf`); `main` at `a0f0db6`; the map
(`research/order-system-map-2026-11-19.md`, Jason's accepted baseline) and the crosswalk
(`research/order-review-crosswalk-2026-11-20.md`).

**Form:** a new decision (0177) that extends 0101 and 0154 §6, not a 0101 addendum: it changes how three decisions'
flows end (0101 client orders, 0154 §6 the one cart, 0096 GO Codes checkout) and several parts depend on it
(builder-playbook §3: "a big gap … is asked as 'draft a decision for this?'"). 0101 keeps every rule this doesn't name.

## Sources (every behaviour below cites one of these)

- **J1** Jason, 2026-11-20: "We dont quote and build, our orders either configured by us with separate building
  processes to yield something in a cart or the client can configure it and have us support their item needs ending up
  as a cart as well."
- **J2** Jason, 2026-11-20: "it can be graphic design, printed product, etc handles as separate processes, but becomes
  on cart (Invoice) for approval(confirmation) and payment"
- **J3** Jason, 2026-11-20: "Process of an item should address requirements to release to cart, so proofs get approved,
  production files get uploaded, vendor is selected, drops ship is configured, etc. then released"
- **T** 0101's note and 0135's note of 2026-11-20: terms before money, for our own sales too ("Yes, both").
- **0154 §6**: "one cart for everything Traversence sells" (`/qr/cart.php`, `CartPlans.php` header).
- **0101**: the client order rules (map §3), kept unless named below.
- **CLAUDE.md**: stages are workflows (System Configuration → Workflows), not hard-coded; Pulse: one case per kind of
  chore, a step per item, a step doable from the case is a button.

## The decision, in five parts

### 1. Every line has a process, and the process ends in "Released to the cart" (J1, J2, J3)

- A line's **process** is set by its kind: printed product, graphic design, GO Code, custom, shipping; later kinds add
  their own (J2: "handles as separate processes").
- Each process lists its **release requirements**. J3 names four for a printed product: **proof approved**, **production
  files uploaded**, **vendor selected**, **drop-ship (delivery) configured**. A line is **Released** only when every
  one is met (J3: "then released").
- The requirements live in the workflow (System Configuration → Workflows, `admin/workflows.php`), as checks like the
  `proofs_approved` that exists today, not in code (CLAUDE.md). Nothing new is invented as a requirement: the printed
  product's four come from J3; any other kind's list is a question (Q-B below).
- Kinds with nothing to prove or produce (a GO Code bought in the cart, a one-off, a domain, a plan) are released the
  moment they're added. That is how the one cart works today (0154 §6, 0096, 0169 §1a); kept.

### 2. The cart is the invoice the client confirms and pays (J2, T)

- Released lines are the cart. **Confirm** is 0101's "Approve the order" (billing address, name, "I approve"), now over
  released lines: it's the moment QuickBooks sees the order and the invoice with tax is made, as 0101 decided ("QuickBooks
  sees the order only when the client approves … not an Estimate before"). Kept.
- 0101's ways to pay are kept as they are: in full, deposit then the rest, invoice later, wire/check/cash, outside,
  payment hold, Release to production before payment (0101 10-28).
- **Terms before money (T):** each line in the cart says, before Confirm, what's delivered, when it counts as
  delivered, whether it's final, and what was confirmed. For a printed product: the approved proof, the turnaround, the
  delivery set in its process. The cart already does this for domains ("Domain names aren't refundable once
  registered", `qr/cart.php:60`); the same line, per kind.
- Proof approval stays the client's signed approval (initials, two boxes, 0101 10-28), now as a release requirement
  before the cart rather than beside Confirm. The page they use doesn't change.

### 3. Two ways in, one end (J1)

| | Team-configured (today's client order, map §3) | Client-configured (new) |
|---|---|---|
| Starts | Staff build it in Back-Office → Orders (kept: map §3 steps 1–4) | The client configures a shown catalog product on the site (options, run size, priced by `Products::priceFor`, as staff are) |
| Becomes | an `orders` draft with `created_by` = staff (kept) | an `orders` draft for our team, as `Collateral::askReorder` already makes one from a client's request (map §2 row 2) |
| Our team supports it | proofs, production files, vendor, delivery (J3) | the same (J1: "have us support their item needs") |
| Ends | released lines in the cart → Confirm → pay | the same |

The Shop's "Design, video, print and signs: Ask for a quote" card (`shop/index.php:68`, a `mailto:`) is replaced by
configuring the product, for products our team marks shown in the catalog (J1: "We dont quote and build"). Products not
shown stay as today. That replaces a live card, so it's named here for Jason's OK.

### 4. What is kept, moved or replaced

| Part (map §3 step) | Today | Here |
|---|---|---|
| Catalog, pricing, run sizes, first choice standard (0101) | as built | **Kept** |
| Building, projects, parts (steps 1–3) | staff | **Kept**; also reached from a client's configuration (part 3) |
| Proofs, versions, signed approval (steps 4, 6–7a) | client approves on the order's page | **Kept**; now a release requirement (part 1) |
| Send (step 5) | emails the link for proofs and approval | **Kept** for proofs; a second notice, "Ready for you to confirm", when lines are released (Q-A says how) |
| Production files (step 11, `order_file` setup/print 1-up) | uploaded any time; required by nothing | **Moved before the cart**: a release requirement for a printed product (J3) |
| Vendor (step 11) | picked when the work order is made, after payment | **Moved before the cart**: the vendor (and so its cost) is **selected** before release (J3). The work order is still **sent** only after payment or Release to production before payment (0101, T: no production before money unless our team releases it) |
| Delivery / per-item ship-to (step 3, `setLineShip`, `WorkOrders::DELIVERY` incl. `blind_drop`) | set any time; the client's approval form ticks "To the billing address" (map §5 item 1) | **Moved before the cart**: configured as a release requirement (J3). The approval form's default then has to be settled (Q-A) |
| Approve the order (step 8) | after every proof | **Replaced by Confirm** over released lines: same fields, same invoice moment (part 2) |
| Pay, receipts, ledger, refunds (steps 9–10, 16) | as built | **Kept** |
| Work orders, vendor's page, 4over paste, shipping, completion, reorder (steps 11–15) | as built | **Kept**, starting from the vendor and files already chosen |
| The one cart `/qr/cart.php` (GO Codes, one-offs, domains, plans) | as built | **Kept**; an order waiting on the client's Confirm is listed there for a signed-in client (Q-A) |
| Workflow `client_order` stages (`workflows.php:15-72`) | Draft → With the client → Approved … | A per-line "Released" check and an order stage "Ready to confirm" between With the client and Approved (Q-A) |
| Pulse: one case per order (0105) | steps per item | **Kept**; each unmet requirement is that item's step, with its button (Select vendor, Upload production file, Set delivery) (CLAUDE.md Pulse rule) |
| Business sales (0135, Marketplace, `market_orders`) | a separate system | **Untouched** (map §1). Only T applies to both |

### 5. Not decided here (asked one at a time, below)

Anything not in J1–J3, T or an earlier decision is a question, never a build choice (builder-playbook §3).

## The questions for Jason, one area at a time (each reviewed by the session manager first)

- **Q-A, the cart's shape** (this proposal): (1) where a team-configured cart lives: the order's own link (no account
  needed, 0101) with a line in a signed-in client's `/qr/cart.php`, or moved into `/qr/cart.php`; (2) all lines released
  before Confirm, or Confirm and pay what's released while the rest is still in process; (3) the approval form's
  ship-to once delivery is configured before release (map §5 item 1 in its new form); (4) the Shop card replaced by
  configuring shown products.
- **Q-B, each process's requirements:** J3's four for a printed product; is a proof required on every printed line
  (map §5 item 2 seems answered by J3: confirm); what releases a graphic-design line, a custom line, a shipping line;
  design capacity (crosswalk: no decision).
- **Q-C, money between design and production** (crosswalk row; map §5 item 5): when design is earned, when production
  is refundable, what a refund does to an open work order.
- **Q-D, payment and stage gates** (map §5 items 3, 4): a part payment on QuickBooks' own page; which callers must check
  paid in full before Shipped/Ready.
- **Q-E, the client's files:** client artwork upload and nudges when assets are missing (crosswalk; 0176 Q3 already
  decided Replace file on the order's link); a fingerprint of the approved file (crosswalk); 0099's watermark rule vs
  0101's download rule (map §3a); the order file check's limits (0176 R4, Jason: "I'll set the limits").
- **Q-F, order mail and dead code:** moving order mail from `Mailer` onto the one dispatcher (0172, 0176); deleting the
  six uncalled builder actions (map §5 item 6).

## The first release: the chargeFor fix only (Jason: "Fix in first release")

- `QuickBooksPay::chargeFor` (`QuickBooksPay.php:871-888`, mirror 17:54) calls `guardReal` at 874 before looking the
  charge up. `guardReal`'s own comment (109-114, Jason 2026-11-10): "refunds and credits only send money back, so a
  test copy that once took real money can still be refunded." `chargeFor`'s only caller is `refundCard` (898).
- The fix: take the `guardReal` line out of `chargeFor` (the lookup only reads QuickBooks; `refundCard` sends money
  back). `Orders::refundThroughQuickBooks` still runs `sameEnv` first (Orders.php:197), so a sandbox order is never
  looked up in production; a "TEST · " copy that never took real money has no live invoice, so the lookup finds no
  charge and the existing "no card to refund from here" message shows.
- Words: `chargeFor`'s comment line "never real money for a test" goes with it; `guardReal`'s comment already states the
  rule. FAQ: none (staff-only, no visitor-facing change); asked of the session manager.
- Test: a sandbox order paid on QuickBooks' page refunds from `admin/order.php` (the hand-off's test), and a unit check
  that a test-marked order with a real charge reaches `refundCard`'s call in mock.
- Admin's test: the only new state is that Refund on such an order now works; before, it showed "is a test order: it
  never goes through live QuickBooks".
