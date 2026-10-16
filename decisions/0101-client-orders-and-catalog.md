# 0101 — Orders we build for a client, and the product catalog

Status: accepted (Jason, 2026-10-16). Builds on 0098 (the order system) and 0099 (E-Commerce operations).

## Context

Jason builds orders for clients (business cards with a QR, design work, GO Codes) and needs the client to review and
approve them, proofs included, before anything is made or charged. Until now the only cart was the GO Codes cart a
customer fills themselves, and GO Codes were the only product.

## Decision (Jason)

- **Payment, chosen per order:** in full on approval, a deposit on approval and the rest before delivery, or approve
  now and pay the invoice later.
- **Printed products are made by a vendor; Jason places the order with them himself** and marks the order's stage.
  (A purchase order sent by the system can come later.)
- **Prices come from the catalog and can be changed on any order** (the difference shows as a discount).
- **QuickBooks sees the order only when the client approves** (an invoice then, as checkout does), not an Estimate
  before.

## How it works

- **Catalog** (E-Commerce → Products, `products`): name, code, kind, description, options ("Paper: Matte, Glossy"),
  a price and cost for each quantity (a quantity between tiers is priced from the tier below), taxable, vendor,
  turnaround, shown or hidden. Its QuickBooks product is found or made by name on the first invoice.
- **Building** (E-Commerce → Client orders → New order for a client, `admin/client-order.php`): the client (a member
  is linked by email; no account needed), lines (a catalog product with quantity and options, a GO Code made with
  what it holds, or a custom line), a proof per line (PDF or image, kept outside the web root, a new version per
  round, history kept), the terms (payment, deposit %), a note, and how many days the link works. **Send** emails the
  client their private link (`/order.php?t=…`); **Send again** after changes.
- **The client's page:** every line with its price (a discount shows the catalog price struck through) and its proof;
  **Approve this proof** or **Ask for changes** (our team is emailed; the order shows "Changes requested"). With every
  proof approved: billing address + name + "I approve" → the QuickBooks invoice is made (tax from the address); the
  approval is recorded with name, email and IP in the order's history.
- **Paying:** the card form from checkout, for the amount due now (the deposit, then the rest; or the whole). The
  ledger records a deposit and later the charge; the order stays "waiting for payment" until it's paid in full
  (`Orders::settle` keeps a part-paid order pending). Paid on QuickBooks' page instead: the webhook or "Check payment"
  records the rest (`QrIssue::markPaid` hands client orders to `ClientOrders::paidInQuickBooks`).
- **After payment:** GO Codes are issued through trvgo.us and download from the client's page; printed products move
  through In production → Ordered from the printer → Shipped / Ready for pickup → Complete, each emailing the client
  (with a tracking number). With invoice-later terms, production starts on approval.
- Refunds, credits, chargebacks and the history stay on the order page (`admin/order.php`), as for every order.
- Staff roles: the pages are E-Commerce's (Owner, Accountant; 0100).

## Not yet

- Purchase orders emailed to the vendor with the approved proof files.
- A short trvgo.us address for the client's link (the full link is emailed and can be copied).
- Shipping charges as their own line type (a custom line for now).

## Addendum (2026-10-16): priced like the print vendor quotes

Jason's vendor quotes a cost per run size (25 … 2,500), turnaround as a share of the run price (+10% two days, +30%
next day…), flat extras (PDF proof +$5.00, samples +$9.99) and shipping per shipment. The catalog now matches:

- **Run sizes** are rows of vendor cost and our price; our price left empty is the cost plus the product's **markup %**
  (stored with the rows: `tiers = {"markup", "rows"}`; older plain lists still read). The form shows the margin.
- **Choices can carry a price:** `Turnaround: 4 Business Days, 2 Business Days +10%` (a % of the run's price and cost)
  or `PDF proof: No, Yes +5.00` (a vendor cost, marked up). The first choice is the standard one. Fixed specs (size,
  stock, colors, coating) go in the description.
- **Shipping** is its own line on a client order (method, the vendor's charge, what the client pays, ship-to, not
  taxed unless ticked); it's not a production item. Custom lines take an optional cost for margins.
- The forms no longer show the browser's own "fill out this field" bubbles; the page says what's missing.

## Addendum (2026-10-16): Jason's first real orders

- **GO Codes on a client order are made at once**, with their trvgo.us address, so the real QR goes on the proofs: the
  line shows it with SVG / PNG downloads and Copy its link (staff only before payment; the address reads "not active"
  until the order is paid). A code's name and content can be changed until the client approves. Adding one works like
  the GO Codes page: pick what it does, then its fields.
- **A draft follows the catalog:** until the order is sent, its catalog lines take the catalog's current price and cost
  (a price typed by our team stays; only the struck-through catalog price moves). Once sent, never.
- **Deposit** is off unless chosen; choosing it asks for the %. The builder's forms show the site's messages, not the
  browser's bubbles (a CSS rule had also been showing every hidden field group).
- **Past clients:** starting an order offers everyone we've had an order from (name, business, email, phone from their
  latest order). There's no separate client list yet; QuickBooks keeps the customer when they approve.
- **QuickBooks inbox by mode** (`2026-12-07_inbox_mode.sql`): items remember the mode they came from; Production never
  shows the sandbox company's refunds and credit memos.

**Addendum (2026-10-16, GO Code proofs).** A GO Code on a client order works before it's paid, as a proof. While its
code is `pending` and its order is a client order (`created_by` set) in draft, sent or pending, trvgo.us serves it
instead of "This code isn't active": a page marked PROOF with the code's name, what it does (`QrIssue::holds`) and a
button that does it (`?go=1`: the link, the contact card, the call…). Proof scans are never counted. Once the order is
paid the code goes live (scans act directly and count); a cancelled order's code reads "not active" again. The admin
line has "Try it" (opens the trvgo.us address). The extra tap and the PROOF label keep a proof from standing in for a
paid code.
Revised the same day (Jason): the proof page shows everything the code holds, as it will arrive: a contact card as a
card (name, job title and business, then each phone, email, website, the address on one line, the note), any other
type field by field (`QrIssue::FIELDS`), with the kind of code under its name. Downloaded files are named
`go-code-<name>-<trvgo code>` (e.g. `go-code-paul-s-card-na76j2-1in.svg`), not `qr-…`.

**Addendum (2026-10-16, the proof lightbox).** A proof opens in its own lightbox (`js/tv-proof.js`, `data-proof`),
for the client and our team alike: a dark backdrop (86%), × in the corner, only the proof, and under it a bar that
never scrolls away holding Approve this proof / Ask for changes (the client's, while the order is out for review; our
team's view has none). The proof fills the window's width less 50 px a side (12 px below 768 px, so it isn't tiny on a
phone) and scrolls inside when taller than the window. Images show at their own resolution; a PDF is drawn page by page
with pdf.js 3.11.174 (cdnjs) at the shown width × the screen's density (at least ×2), so small type stays crisp, with
the browser's own viewer as the fallback. On the order page each proof is a card (a thumbnail, or a PDF mark) with
"Review the proof". GO Codes are proofed the same way: adding one (or changing what it holds) makes a proof row with
mime `go-code` and no file, approved like any proof before the order can be; sending an order makes them for codes
added earlier. Its lightbox shows the vector QR beside everything it holds (`QrIssue::detailsHtml`, shared with the
trvgo.us proof page); the client sees it by their order's link (`/api/qr.php?…&proof=1`, view only, no download
until paid). Privacy page: cdnjs also serves the PDF viewer.
