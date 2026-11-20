# Order processing: the session's hand-off (2026-11-20)

**Since the restart (Orders session `session_01CXLi5iruQZSTYKyuR7b1SN`, 2026-11-20):** Gemini's review of the map
(`research/order-rulings-2026-11-20.md`) answers every §5 item, but Jason sent it for review: it's proposals until he
confirms it (session manager, 2026-11-20). Nothing written into 0101. The one-cart proposal is `research/one-cart-proposal-2026-11-20.md` (draft decision 0177, its questions Q-A to
Q-G). R0, the chargeFor fix, is built as `releases/inbox/orders-chargefor.zip` (built on Sync 18:05 UTC; files:
`app/lib/QuickBooksPay.php`; test `tools/order-tests/chargefor.php`), waiting for Jason's Approve. Next, if Jason confirms the review: R1 and
R3–R6 (and R2's first two proof paths as a Send gate) as one release on what's built; then the cart, after Q-A and Q-B. The sections below are the earlier session's
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
