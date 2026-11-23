# Order processing: the session's hand-off (2026-11-23)

**For the fresh Orders session.** The Orders session (`session_01CXLi5iruQZSTYKyuR7b1SN`) hands over at a hold because it
grew too large (CLAUDE.md, the session manager: "at a hold, it writes a complete hand-off"). Jason's go-ahead for the
next build: "Go for it" (2026-11-23), after R0 went live. Session manager: `session_01CXLi5mGX2sg5QS11gg3Wrh`.

## Live and decided

- **R0 is live** as release #259 (the chargeFor fix; verified in mirror 791f665, recorded in #1274).
- **Jason's order model is confirmed** ("Good to go", 2026-11-23): `research/order-model-2026-11-21.md`, written into
  decisions/0101's note "the order model" and 0098's note "credits applied to balances" (both 2026-11-23). It is the
  source for every order release from here. Read its §0 (changes to earlier decisions) and §1 (the model, each point in
  his words) before building anything.
- The map (`research/order-system-map-2026-11-19.md`) and the rulings (`research/order-rulings-2026-11-20.md`, B.1–B.7)
  stand where the model doesn't change them.

## How Jason wants to work (learned the hard way in this session)

- **Start from what's built, in plain steps; change only what his words change.** Never design the shape and ask him to
  approve it ("It is obvious you are not able to see how the order system should work", 2026-11-21).
- One open point at a time, plainly in chat first; write each answer in his words into the model or decision; then build.
- **Check decisions before asking** (he had to point to 0098's "Fix it, credit, then refund").
- Gemini's text is a review to weigh, never his answer, until he confirms it.
- **The session manager reviews the summary before the pop-up**, every time (this session once asked first).

## Now (Orders session `session_01CXLi5kEUy5gPJ4PG34SZhy`, 2026-11-23)

**Built, waiting for the session manager's review, then Jason's Approve:** `releases/inbox/orders-rulings-2.zip` (branch
`claude/orders-rulings-2`), the rulings release rebuilt on the model with the seven changes below. It replaces the held
`orders-rulings.zip` (#1224, never sent; close #1224 when this merges). Built on Sync 01:52 UTC (4a94342); its files were
the same at 00:15 (791f665). Files: `app/lib/{ClientOrders,QuickBooksPay,QrIssue,WorkOrders,VendorOrders,Collateral,Pulse}.php`,
`app/admin/Workflow.php`, `app/ui/faq.md`, `website/order.php`, `website/admin/{order,client-order}.php`,
`website/api/qb-webhook.php`. Tests: `tools/order-tests/rulings.php` (83/83, MariaDB 10.11) and `rulings.cjs` with
`rulings-seed.php` (59/59 in the design harness at 1280 and 390). Proof pictures: `handoffs/orders-rulings-2/`.
- Orders still on invoice later on live: no live read (the session manager, 2026-11-23: it lists clients' names). Once
  live, one Pulse case ("Open invoice-later orders: choose for each") lists them, a step each; their count is known then.
- Open, for Jason: deleting `saveAddress`, `setLineShip`, `setOptions` (uncalled since B.6).
- Before merging: fetch the mirror, rebuild `faq.md` if it moved, rerun `zipcheck.py`.

## Next build: the held rulings release, rebuilt on the model

Held: `releases/inbox/orders-rulings.zip` on branch `claude/orders-rulings`, PR #1224 (draft, stacked on the old #1212
branch: rebase it onto `main` or rebuild fresh). It was built on Sync 18:34 UTC 2026-11-20 (5674a94); **rebuild every
file from the newest mirror**. Its files: `app/lib/{ClientOrders,QuickBooksPay,QrIssue,WorkOrders}.php`,
`app/admin/Workflow.php`, `app/ui/faq.md`, `website/order.php`, `website/admin/{order,client-order}.php`,
`website/api/qb-webhook.php`; test `tools/order-tests/rulings.php` (24/24 on MariaDB 10.11; it installs and runs with
`apt-get install mariadb-server`, then `mariadbd --user=root --skip-grant-tables`, a test root holding the live lib with
the release's files over it, a `.env` two folders above it, and a stub `Mailer.php`).

**Keep as built** (each still fits the model): B.1 (ship-to prefill, `website/order.php`); B.2 paths 1–2 (the proof gate
in `ClientOrders::send`/`approve`, `proofGaps`, reuse only with the same product and options); B.3 (part payments on
QuickBooks' page recorded as they arrive, `QuickBooksPay::invoicePaid`, `ClientOrders::fromQuickBooks`,
`QrIssue::checkPaid`, used by the webhook, Check payment and the buyer's refresh); B.5 (refund shows each open work
order's status, then "Cancel the work order and notify the vendor"); B.6 (the six dead builder actions deleted; three
library functions then uncalled, `saveAddress`, `setLineShip`, `setOptions`, need Jason's OK to delete: still unasked).

**Change, per the model (0101 note 2026-11-23):**
1. **The vendor work order waits for payment in full** (model 1.9: "Paid in full before order is released to vendor"),
   except (a) a deposit order with its deposit paid (`ClientOrders::releaseAt` reached), (b) an admin's override, (c) the
   outside path (`WorkOrders::processOutside`). Gate `WorkOrders::create`/`send` (today only "Approve the order first").
2. **Release for delivery (Shipped/Ready) waits for payment in full** (1.9: "Balance must be paid before order is release
   for delivery"), except the admin override and outside terms. **Remove the invoice-later exemption** (§0 item 3) from
   `ClientOrders::shipHold` and the workflow's `paid_in_full`.
3. **The override is admins only, with a reason recorded** (1.9). Today `releaseHold` ("Release to production before
   payment", `website/admin/client-order.php` lines 104–106 and 1201) is open to staff with order edit rights. Find how
   the admin checks a role (`Access::can`, `tv_admin_require`) on the live mirror, gate it, ask the reason
   (`TvAsk.ask`), keep the `released` event with the reason. `processOutside` calls `releaseHold` too: that's the outside
   path, not the override; keep it working and say so in its event.
4. **The vendor's own Shipped on a deposit order with the balance unpaid** (the old Q2, now settled by 1.9: "delays may
   result in orders being held for payment before shipping"): save the vendor's status and tracking on the work order,
   hold the order's stage (don't tell the client it shipped), and give staff a step to collect the balance (Pulse rule:
   one case per kind of chore, a button that does it).
5. **Deposit default 65%** (model 1.8; only in code: `ClientOrders::setField`, line 1227, `= 50`).
6. **Invoice later stops being offered** for new orders (§0 item 1; `ClientOrders::PAY_MODES['invoice']`, `saveTerms`
   line 211). Keep it readable for existing orders. Side effect to state in the summary: existing open invoice-later
   orders then need the admin override to reach the vendor or ship. Count them on live first and list them for Jason.
   Credit accounts (its replacement) aren't built: a separate build.
7. **Words stay true:** the deposit email ("due before delivery"), the client page's notes ("printing starts right
   away" for invoice later, order.php ~367), the builder's pay-mode list, the FAQ's "Do I have to pay for the whole order
   up front?". Rebuild `faq.md` on the live copy just before zipping.

Before code (CLAUDE.md, "End to end, verified"): map every state transition and entry point (order page, Pulse, work
orders, the vendor's page, the drawer, staff approve, outside path), every exit and side effect; test each against the
model, including failures (a refused vendor work order says why and offers the override to an admin, greyed with the
reason to others).

## After that, in order (each its own build, each from the model)

1. The stage names (1.13: Staged for production, Production queue at the vendor's Accept with its job number,
   processed, pack and ship, released for delivery, delivered; then Complete per 0101 2026-10-30) and the cancel window
   (1.9: closes at the vendor's Accept; before it, pull, change, resubmit).
2. Credit accounts (1.8) and credits applied to balances (0098 note 2026-11-23): the credit process is still to walk
   through with Jason.
3. The cart route (1.1, 1.3): Send cart / Send invoice, Place order and the ordering queue, approval moving items to the
   cart or invoice, deposits in the cart, no wire or check there, guests.
4. Client artwork upload, the file preflight (0176; Jason sets the limits when the build reaches them), our proof of it.
5. The shipping module (1.14), waiting states and reminders (1.15), reorder alerts.
6. Still open, to walk through with Jason: design capacity, a fingerprint of the approved file, nudges for missing art,
   order mail onto the one dispatcher, the credit process.

---

# The earlier record (2026-11-20)

**Since the restart (Orders session `session_01CXLi5iruQZSTYKyuR7b1SN`, 2026-11-20):** the map is confirmed and every §5 item
is ruled, in Jason's confirmed text (`research/order-rulings-2026-11-20.md`), written into decisions/0101's note "rulings
on the order map" and 0166's note of 2026-11-20. The one-cart proposal is `research/one-cart-proposal-2026-11-20.md` (draft decision 0177, its questions Q-A to
Q-G). R0, the chargeFor fix, is built as `releases/inbox/orders-chargefor.zip` (built on Sync 18:05 UTC; files:
`app/lib/QuickBooksPay.php`; test `tools/order-tests/chargefor.php`), waiting for Jason's Approve. Next: B.1 and
B.3–B.6 (and B.2's first two proof paths as a Send gate) as one release on what's built; then the cart, after Q-A and Q-B. The sections below are the earlier session's
record.

The order processing session (opened by Jason 2026-11-19: "an order processing agent session for reviewing how the
order intake process works and how to build it from the 4over ordering process example") hands off to a fresh session
at Jason's instruction (2026-11-20, through the session manager: every session hands off to a fresh one to save
tokens, without losing any logic). Nothing has been built; no release, no decision. This file is the whole state.

## Read first

1. `research/order-system-map-2026-11-19.md`: **the map of the order system as built and decided.** Jason is
   reviewing it ("i will need to review thorughly", 2026-11-19). It's the base for everything that follows.
2. Its folder `research/order-system-map-2026-11-19/`: the nine full readings it was built from (four on decisions,
   0101 addendum by addendum; five on the live code, function by function with file:line). Use them instead of
   re-reading the system; check a line against the mirror before building on it.
3. `research/order-intake-2026-11-19.md`: the first draft (the 4over comparison and five releases), **set aside**. Its
   status line says so; the map's §5 and §7 correct it.
4. `decisions/0176` (the dispatcher), its "An order file" part and Question 3's answer (below).
5. `handoffs/builder-playbook.md` and CLAUDE.md, as for any build.

Built on: the live mirror at "Sync: 2026-11-19 16:21 UTC"; `main` at `0cda5f7` when merged. Fetch the newest sync
before anything else; the map's line numbers may have moved.

## What the map covers

- §1 The two order systems, kept apart on purpose: **ours** (`orders`, `order_lines`, `order_ledger`; QuickBooks
  Payments; 0098, 0101, 0108) and **a business's own sales** (`market_orders`, the business's `b_biz_quotes`; its own
  Stripe or Square; 0135, 0118). They share no order tables or code. Then each part of client orders with its decision
  and its code.
- §2 The seven ways one of our orders is made: client order built by staff; reorder (staff, or asked by the client);
  duplicate / test copy; QuickBooks pull; GO Codes cart; one-offs; domains. Plans and packs are subscriptions, not
  `orders`. Print can't be bought by a client on the site (the Shop's print card is a `mailto:`).
- §3 The client order in 16 steps (who acts, the state, what the client sees, money, file:line); the three status
  layers; which moves go through the workflow (only the Back-Office → Orders drawer); the 22 emails; the three jobs.
- §3a Artwork and files (added at Jason's question "what about artwork/gallery file handling?"): three staff-only upload
  targets (`order_proof`, `order_file`, `order_final`); the Gallery panel on an order (This order's files, This
  client's earlier orders; staff have no Gallery, 0143); storage in the order's own folder (0124, R2 per 0151); what
  the client sees before paying (no watermark: 0099's rule isn't built, 0101's download rule runs); release after paid
  in full; Your artwork and the Orders catalog in the client's Gallery; files to vendors by their file preference; no
  print check.
- §4 Which decisions changed which (0098 → 0166, 0108, 0101; 0099 → 0101; 0101 → 0150, 0128, 0130; 0105 → 0172).
- §5 Intent checked before calling anything a gap (deliberate / bug / unclear), with the evidence.
- §6 What isn't known (the schema, live permission overrides, files not read, uncalled functions).
- §7 The first draft's errors, owned.

## Jason's answers so far

| Asked (2026-11-19, reviewed by the session manager) | Answer |
|---|---|
| The first draft's plan and its five releases | **Needs changes**: map the whole order system first (his words to the session manager: "I feel like it is making decisions that arent in line with what has been build already. The ordering system is massive and I dont want this new session half understanding it.") |
| "No proof for this item" with a reason | **Needs changes** (same reason) |
| The client sending artwork | No preference given then; since answered through 0176 (below) |
| After R1–R5, the after-purchase findings and a client product page as the next phase | **Not now** |
| Is the map right? | "i will need to review thorughly." **Open.** |
| The six "is this intended?" items (map §5) | "need to review". **Open.** |
| **Bug:** `QuickBooksPay::chargeFor` calls `guardReal` before looking the charge up (QuickBooksPay.php:874), so a test order paid on QuickBooks' own page can't be refunded from the site in production, against its own comment (109-114, Jason 2026-11-10: "a test copy that once took real money can still be refunded") | **"Fix in first release."** |

## PR #1074

PR #1074 (https://github.com/degraffics/traversence/pull/1074) carries this branch, `claude/order-intake-review`:
the first draft, the map and its folder, this hand-off and their README lines. It was a draft until 2026-11-20 and
is merged now so the record is in `main`, at Jason's hand-off instruction. Merging records the research; it approves
nothing: the first draft stays "set aside", and the map stays "for Jason to confirm".

## The open questions (each waits on Jason; none to be re-asked before he's reviewed the map)

From the map's §5, each with its evidence there:
1. **The ship-to on an order that already has one.** The client's approval form ticks "To the billing address above"
   (order.php:375) with empty ship fields, so approving writes the billing address over the order's Ship to. The default
   is 0101's own rule ("the client gives the ship-to address when approving ('to the billing address' by default)"),
   written the same day the order's own Ship to came in; the two were never reconciled. The staff "Approve for the
   client" form starts from the order's Ship to.
2. **A proof per printed line.** 0101 describes "a proof per line" and "With every proof approved"; nothing enforces it
   (`send` and `approve` only block proofs waiting or in changes).
3. **A part payment on QuickBooks' own invoice page** records nothing until the balance is zero
   (`QuickBooksPay::paid` 729). 0101 covers "the rest" paid there, not a part.
4. **Shipped / Ready before paid in full.** Only the Orders drawer runs the workflow's `paid_in_full` check; the order
   page, Pulse, work orders and the vendor's page call `ClientOrders::setStage` directly. Which callers should check?
5. **A refund of printed work** doesn't cancel or tell the open work order.
6. **Six builder actions nothing calls** (`terms`, `line_save`, `addr_save`, `line_ship`, `options_save`, `stage` in
   `admin/client-order.php`): dead code to delete? (CLAUDE.md deletes dead code once checked against the mirror.)

Also open in the map, not yet asked: whether 0099's watermarked-proof rule still stands or 0101's download rule
replaced it.

Settled by decisions, not questions (don't re-ask): an option not picked takes its first choice ("The first choice is
the standard one", 0101); tax shows only at approval ("not an Estimate before", 0101).

## The order file check (0176, Question 3, answered 2026-11-20)

`decisions/0176` (the dispatcher presents and routes every thread), "An order file":
1. The order's file check acts as its pre-flight: resolution, size and bleed. **To come**; no check exists today.
2. Staff get a thread "New artwork on Order #N" with **Open the order** (the order's Files in the panel, with
   **Approve proof** or **Ask for a new file**).
3. A failed check gives the customer **Fix it → Replace file**.

Jason's answer: **"I'll set the limits"**: customers may replace a file on the order's link, and **Jason gives the
check's minimum resolution, bleed and size**. Until he has, the order file check and Replace file stay "to come", and
0176's release 4 builds the staff thread first. So:
- The customer upload on the order's link is now decided (it needs a new customer target in
  `app/lib/upload-targets.php`, the order's link as its check, 0127). It was "no rule either way" in the map's §3a.
- The check's limits are Jason's to give. Don't propose numbers; ask for them only when the build reaches them, and only
  through the session manager.
- 0176's releases are owned by the dispatcher session (`handoffs/dispatcher.md`). Coordinate the order file thread and
  Replace file with it; don't build them twice.

## Next steps, in order

1. **Wait for Jason's review of the map.** He confirms it or says what's wrong. Fix the map first if he does.
2. With the map confirmed and his answers to the six items, **rewrite the improvements on top of the map**: extend or
   replace in place, quote each decision touched, and nothing against a decision without naming it. Keep from the first
   draft only what still fits: the 4over comparison (§3 there: what we'd take, have, and what doesn't fit a trade
   printer's flow) and the ideas Jason hasn't turned down: the request from an email in Pulse to a draft order (0172
   §4.7), a USPS address check (free; needs a developer account at https://developers.usps.com/, keys through the
   secure form, the call behind `StagingGate::hold()`), a promised ready-by date, the confirmation listing the order.
3. **The first release includes the chargeFor fix** (Jason: "Fix in first release"): look the charge up before the
   guard, so a test copy that took real money can be refunded, as the comment says. Its test: a sandbox order paid on
   QuickBooks' page refunds from `admin/order.php`.
4. Send the rewrite to the session manager; ask Jason in your own pop-up once it answers "clear to ask"; then turn it
   into a decision (extending 0101) and the releases, one coherent release at a time, each through the playbook.
5. The order file check and Replace file follow 0176 and Jason's limits, with the dispatcher session.

Not to repeat: proposing changes before the map is confirmed; calling a decided rule a gap (check the map's §5 and the
d-parts first); asking Jason the same question twice.
