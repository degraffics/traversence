# Order intake, from the request to the purchase confirmation (draft, 2026-11-19)

Status: **draft, set aside** (2026-11-19: Jason answered "Needs changes": map the whole order system first). Read
`research/order-system-map-2026-11-19.md` first; its §5 and §7 correct this draft (the option default and tax at approval
are 0101's own rules). Kept as the record of the 4over comparison; the improvements get rewritten on top of the map.
Order processing session; Phase 1, no code. Read against the live mirror at
"Sync: 2026-11-19 16:21 UTC" and `main` at `afd0d26`. It extends 0098, 0101, 0106, 0107 and 0130 in place; where it
touches an earlier rule, the line is quoted (§6). Nothing here is decided until Jason's OK.

Jason (2026-11-19): "an order processing agent session for reviewing how the order intake process works and how to
build it from the 4over ordering process example." Then: "This example is for you to see how their process handles
ordering business cards. You need to review our order processing from request through purchase confirmation to ensure
we can make our system easy and accurate. We have a lot already built so let's review how we can improve it."

The example is his walk-through (20 screenshots) of reordering 500 business cards for a client on 4over: the client's
email, the reorder, the specs and price table, turnaround, sets and drop-ship, the address check, artwork with its print
checks, the cart and the finished order. Names and addresses are left out here.

## 0. In short

The span from request to confirmation is **built end to end** for our own client orders (0101): staff build the order
from the catalog, send a private link, the client approves each proof and the order, the invoice is made with tax, the
client pays, and both get a receipt. It isn't missing parts; it has **places where it can be wrong without anyone
noticing** and **places where a person retypes what the system already has**. The worst: the approval form ships to
the billing address by default, even when our team already entered a different ship-to (the exact case in Jason's
example). The proposal is five releases that fix those in place, safest and most accurate first, taking from 4over
only what makes ours easier or more accurate.

## 1. Today's flow, step by step (live code)

| # | Step | How it works | File / function |
|---|---|---|---|
| 1 | **Request arrives** | By email (the Shop's print card is `mailto:support@…`, shop/index.php:68), or a client's reorder request from Your orders | `Collateral::askReorder` (Collateral.php:141) makes a draft and emails staff |
| 2 | **Order started** | Staff type the client's name, email, company, phone; a member with that email is linked; "Find the client" attaches their listing | `ClientOrders::create` (:121); `client_find`, `client_attach` (admin/client-order.php:226-234) |
| 3 | **Items and specs** | A catalog product with its options and run size, priced by the catalog (run-size tiers × markup, plus priced choices such as turnaround); or a custom line; or a reorder of past collateral (same art, re-costed) | `addProduct` (:250), `Products::priceFor` (:225), `addCustom` (:275), `Collateral::reorder` (:183) |
| 4 | **Terms and delivery** | Pay in full, deposit, invoice later, or outside; shipped, pickup or none; per-item ship-to and manifests | `saveTerms`, `PAY_MODES` (:23), `Shipments`, `setLineShip` (:1397) |
| 5 | **Artwork and proofs** | Staff upload files and proofs (versions kept); the client never uploads | targets `order_proof`, `order_file` (upload-targets.php:21, :35); `proofFromUpload` (:397) |
| 6 | **Sent to the client** | Prices refreshed from the catalog (typed prices kept), a private link by email, "The total is $X before tax" | `send` (:629), `refreshPrices` (:227) |
| 7 | **Client reviews** | `/order.php?t=…`: items, proofs to approve (initials, two ticks, `PROOF_TERMS`) or changes asked (Pulse case) | `decideProof` (:891) |
| 8 | **Client approves** | Billing address (for tax), where to ship, their name, "I approve this order and its proofs as shown"; makes the QuickBooks invoice with tax | `approve` (:919), `shipFrom` (:1450), `QuickBooksPay::invoiceFor` (:590) |
| 9 | **Client pays** | Card on the page (all, or the deposit), invoice later, or wire/check/cash with reminders | `pay` (:1023), `choosePay` (:1645), `payWaitTick` (:1674) |
| 10 | **Confirmation** | Thank-you on the order page (order.php:194-215); receipt email "Thank you: order #N is paid", amount, "in production", link; staff told | `received` (:1774) |

## 2. What can go wrong, or be retyped, in that span

In order of risk: wrong goods or a wrong address first, then money, then retyping.

1. **The ship-to can be overwritten by the billing address.** The approval form's "To the billing address above" is
   ticked by default (order.php:375) and its ship-to fields start empty; `shipFrom` (:1454) then returns the billing
   address and `approve` writes it over the order's ship-to (unless every item carries its own ship-to, `needsClientAddress` :1443). A ship-to our team entered from the client's email (Jason's
   example: cards for an employee at another address) is replaced unless the client notices and unticks. **Wrong
   address, silently.**
2. **A printed item can be approved and paid with no proof.** `send` doesn't require one per printed item, and `approve`
   only blocks proofs that are *waiting* (:927); an item with no proof at all passes. 0098 rests on "final once the
   order (proof) is approved". **Wrong goods, signed for nothing.**
3. **An option the builder misses is quietly set to its first choice.** `addProduct` replaces a missing or unknown
   choice with `choices[0]` (:259), so "Coating" becomes whatever is listed first. **Wrong spec, silently.**
4. **No address is checked.** Typos reach the vendor; 4over charges a correction fee (its screen: $11) or the box is
   lost.
5. **Artwork is checked by eye.** Nothing reads bleed, size, resolution, colour mode or fonts; 4over's checks caught
   "The artwork does not extend all the way to the black bleed line" on this very card. A preventable reprint is ours to
   pay.
6. **A deposit paid on QuickBooks' own invoice page isn't seen** until the whole invoice is paid (`QuickBooksPay::paid`
   :731 acts at balance zero): the client has paid and the order doesn't move, and they get no receipt.
7. **The client approves without the full total.** Tax is "worked out from your billing address when you approve"
   (order.php:358), and shipping is often typed later; 4over shows tax and shipping in the cart before checkout.
8. **The request is retyped.** An email with art and an address becomes a client order by hand: name, email, company,
   the file saved and uploaded, the address typed.
9. **No promised date.** Turnaround is a priced option or plain text (`products.turnaround`); nothing says "ready by
   Thu, Oct 1" before or after paying.
10. **The confirmation doesn't confirm the order.** The receipt email says the amount and "in production" but not the
    items, specs, run, ship-to or date; to check, the client opens the page.

Found on the way, **after** the purchase (outside this span; noted for a later phase, not proposed now):
- The Shipped / Ready stage buttons call `ClientOrders::setStage` directly, skipping the workflow's `paid_in_full`
  check (`admin/workflows.php:37-38`): a deposit order can be marked Shipped with its balance unpaid.
- A refund on printed work doesn't stop the open work order unless staff cancel it.
- `QuickBooksPay::chargeFor` (:871) guards before it looks up the charge, so a test copy paid on QuickBooks' page can't
  be refunded from the site, against its own rule (:108-112).
- Tracking is plain text, not a carrier link; pack tracking isn't copied to the items.
- Every 4OVER order is placed by hand and pasted back (0106's API step waits on keys).

## 3. The 4over example against ours

**We don't copy its look or name it on our pages.** What each step teaches:

| 4over's step | Ours | Take / have / doesn't fit |
|---|---|---|
| Client's email with the art and the address | Retyped | **Take (our side of the counter):** the email becomes the draft (R3) |
| Reorder: new project name, "Reorder with new artwork", specs kept | `Collateral::reorder` (same art), `askReorder` | **Have.** Take: "same specs, new art" |
| Product options, each required (size, shape, stock, colour, coating, spot UV) | Options per product, picked by staff | **Have.** Take: required means required (gap 3) |
| Run-size table: unit and total price per run | `products.tiers`, `priceFor` | **Have.** Take: show the table where the run is picked. Its markup column is a reseller's view: **doesn't fit** (our price already carries the markup) |
| Turnaround choices with a price, then a completion date | Turnaround as a priced option | **Take:** the promised date (gap 9) |
| Sample (+$9.99), PDF proof (+$5) | Options can carry a price | **Have.** Our proof is part of custom work, so a paid proof add-on **doesn't fit** |
| Sets per shipment, several shipments, drop-ship, address book | Manifests, per-item ship-to, `order_addresses` | **Have**, staff-side |
| Verify your address: suggested (ZIP+4) or yours, fee if wrong | None | **Take** (gap 4) |
| Shipping quote failed; "Something went wrong"; live chat | Typed by staff | **Take the lesson:** a quote that can't be had never stops the order |
| Artwork: trim, bleed and safe lines; checks (fonts embedded, CMYK, RGB converted); bleed warning; "approved & locked"; "I understand, print as is" | Staff upload, no checks; proof approval with initials | **Take** the checks, their words and "print as is" (gap 5). The editing tools and "Create Design" **don't fit**: our design is a service, staff make the proof |
| Cart: specs, subtotal, shipping, tax, total; promo | Order page; tax after approval | **Take:** the total before approving (gap 7). Rewards points **don't fit** |
| Order page: status, specs, ship-to, completion date, job, tracking, Reorder | Order page, Your orders | **Have** most. Take: the promised date, and the confirmation carrying the order (gap 10) |

## 4. The releases, in order

Each is one coherent release with its tests, the phone at 390px, the FAQ updated, and the admin's test for each new
state (playbook §2.5).

**R1 · Accurate as built** (gaps 1, 2, 3, 6). Fixes in place; no new pages.
- The approval form starts from the order's own ship-to when it has one ("Ship to: the address on file · Change"),
  and ticks "To the billing address" only when the order has none. Approving never replaces a ship-to the team entered
  without the client choosing to.
- A printed item needs a proof before **Send**: the button is greyed with the reason ("Business cards · 500 has no
  proof yet · Add a proof") or our team ticks "No proof for this item" with a reason (a reprint, a stock item), kept on
  the order.
- `addProduct` refuses a missing or unknown choice instead of picking the first ("Choose a coating"); the builder
  shows each option as a required pick with no preselected value unless the product names a default.
- A partial payment on the QuickBooks invoice page is recorded as a deposit (the webhook reads the payment, not only a
  zero balance), with the deposit receipt.
- Admin's test: an order with a typed ship-to, opened as the client, shows that address on the approval form; an item
  with no proof shows Send greyed with Add a proof beside it.

**R2 · The total and the date before approving** (gaps 7, 9, 10).
- The client's page shows **tax and shipping before Approve**: shipping from the order's lines; tax as an estimate for
  the billing ZIP as the client types it (the rate QuickBooks used on our last invoice to that ZIP, else "Tax added at
  approval"), then the exact figure from the invoice. No QuickBooks Estimate is made (0101).
- **A promised date**: products carry their turnaround in business days (the turnaround option's choices too); the
  order shows "Ready by Thu, Oct 1, if approved today" before approval and the fixed date after (from approval or the
  last proof approved, whichever is later; weekends and our holidays skipped). It's a date, not a moment, so it carries
  no zone; any time shown with it uses `TvTime` with its zone (CLAUDE.md).
- **The confirmation carries the order**: the receipt and the thank-you list each item with its specs and run, the
  ship-to, the promised date, what's paid and what's left, and what happens next.

**R3 · From the request to a draft in one press** (gap 8). Builds on 0172 §4.7 (mailboxes in Pulse; "link it to a
customer or an order") and 0101 ("Clients are listings").
- On an email in Pulse: **Make an order**. It finds the client (email, then listing), opens a draft client order in a
  drawer, copies the attachments in as the order's files (the one uploader), reads a ship-to from the text as an
  address to confirm, and offers **Reorder** with the client's matching collateral (same specs; same or new art).
- The email links to the order and the order to the email.
- **Make an order** is an item in the row's ⋯ (design/table-standard.md), not a new icon. Pulse's rows are being
  changed by the design session (T2, filters, Group by in `pulse-ui.php`), so R3 is built after those are live and
  coordinated with it.
- Admin's test: an email with a JPG and an address → one press → a draft with the client, the file and the ship-to,
  each marked "to confirm".

**R4 · Addresses checked** (gap 4).
- Every ship-to (staff's and the client's) is checked when it's entered, against USPS's free address check: the
  suggested form or keep yours, the choice recorded. A check that can't run says so and lets it through, raising
  nothing beyond a board notice.
- USPS's Addresses API is free but needs a USPS developer account and its keys (client credentials): sign up at
  https://developers.usps.com/ (Get started → create an app); the keys go in through the secure credential form, never
  chat. The call asks `StagingGate::hold()` first (zip check R13).
- The client's saved addresses (`order_addresses`) are offered first.

**R5 · Artwork checked for print** (gap 5). Extends 0101's proofs and 0127's uploader.
- Products carry their print size: trim, bleed and safe margin, sides (business cards seeded at 2 × 3.5 in,
  1/8 in bleed).
- Every artwork file (staff's, and the client's once they can send one) gets a **print check**: page size against
  trim + bleed, image resolution at size, colour mode, fonts embedded, pages against sides. The result is words, file by
  file ("Passes"; or "The artwork stops short of the bleed line on the right: extend it to the edge"), with a preview of
  the trim, bleed and safe lines.
- A warning never blocks. Staff see it before making the proof; the client sees the proof with its check, and may
  approve it as it is, recorded with the proof terms.
- The client can **send their artwork** on their order page, before the proof: a new target in
  `app/lib/upload-targets.php` with its `staging` rule (0127), for the client's own order only.
- Runs on our server if it has the tools, else as a job on the existing Railway worker; never a per-use service
  ("No income, no expense").

## 5. What stays as it is

The catalog model and pricing (0101, 0130), proof approval with initials and terms, payment terms and methods, the
QuickBooks invoice at approval, the test guards (0145), work orders and the 4OVER paste (0106). Nothing here asks the
client to do more than today; R3 and R5 add the client's artwork only as an option.

## 6. Where this meets earlier decisions

- **0101: "QuickBooks sees the order only when the client approves (an invoice then, as checkout does), not an
  Estimate before."** R2's tax is our estimate from our own past invoices; no Estimate is sent to QuickBooks.
- **0098: "Made-to-order and custom products are final once the order (proof) is approved."** R1's proof rule makes
  sure there is a proof to approve; "No proof for this item" is our team's recorded choice.
- **0101: "Printed products are made by a vendor; Jason places the order with them himself."** Unchanged.
- **0099: "Traversence makes no physical products in-house."** R5 checks files; it doesn't design.
- Drift found on the way (notes only, no question): 0098 "We don't keep a second double-entry ledger" against 0166 §2;
  0098's subscriptions by invoice + AutoPay against 0108 "the card on file, charged by us"; 0101's 60-day work-order
  links against 0150's 30. Each gets a one-line note pointing to the later decision.

## 7. The questions for Jason, gathered

1. **The order of releases**: R1 accurate as built, R2 total and date before approving, R3 request to draft, R4
   addresses checked, R5 artwork checked. Approve, or move one?
2. **"No proof for this item" (R1)**: may our team skip a proof for an item with a recorded reason (a reprint of
   approved art, a stock item), or must every printed item have one? Recommended: the skip, with its reason shown to the
   client.
3. **The client sending artwork (R5)**: today only our team uploads. Offer the client "Send your artwork" on their
   order page? Recommended: yes, before the proof; our team still makes the proof.
4. **Later, outside this span**: the after-purchase findings in §2 (balance before Shipped, refunds stopping work
   orders, the test refund, tracking links), and ordering print from a product page by clients themselves. Take them as
   the next phase in this session once R1-R5 are live?

## 8. Sources

- Decisions: 0098, 0099, 0101, 0105, 0106, 0107, 0127, 0128, 0130, 0145, 0150, 0166, 0172.
- Live code (mirror): `sync-app/lib/ClientOrders.php`, `Orders.php`, `QuickBooksPay.php`, `Products.php`,
  `Collateral.php`, `WorkOrders.php`, `VendorOrders.php`, `Shipments.php`, `upload-targets.php`;
  `sync-app/admin/workflows.php`; `sync-storage/order.php`, `admin/client-order.php`, `shop/index.php`.
- Jason's 4over walk-through (20 screenshots, 2026-11-19), in his chat; not committed (it holds a client's details).
