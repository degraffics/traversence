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

**Addendum (2026-10-16, one Orders list, delivery, paying outside QuickBooks; Jason).**
- *One list.* "Orders" and "Client orders" were two ways into the same thing. E-Commerce → Orders is now the only
  list, with **New order** at its top; orders still being built or out with the client sit first ("Being built or with
  the client"); once approved they join the list with every other order, marked "For a client" (and their stage). A
  client's order always opens its own page (proofs, production, payments); a draft or sent one opened by its number
  goes there too, so a draft never looks like a placed order. GO Codes on a client order aren't "in a cart".
- *Delivery.* Terms say how it reaches the client: shipped, picked up, or nothing to deliver (unset = shipped when the
  order has a shipping line). Shipped: the client gives the ship-to address when approving ("to the billing address" by
  default); it shows on both pages (`2026-12-08_delivery_outside.sql`: `delivery`, `ship_*`). The shipping line is
  still how shipping is priced.
- *Paid outside QuickBooks* (`pay_mode = 'outside'`): no QuickBooks invoice and no card form; no sales tax is added
  (a tax line if it's owed). Our team can **approve it for the client** (in person, by phone or email: the proofs count
  as approved, "recorded by our team"), and **records each payment** (cash, check, Zelle, Venmo, bank transfer, another
  card terminal, other; amount and reference): a deposit while some is left, paid when it's all in, with the usual
  effects (GO Codes live, production, the client emailed). Recording a payment works on any approved client order; on
  one with a QuickBooks invoice the page says to receive it there too.
- *Proof lightbox, final:* a bar across the top of the window, "PROOF: title", Approve, Make changes, ×; the proof 60%
  of the window's width, whole (the backdrop scrolls), square to its edges with a red dashed cut line.

**Addendum (2026-10-16, invoice-style order page, pass 1; Jason).** The order page is laid out like an invoice
(after invoice 112907 and Jason's Traversence example): Billed From (Traversence.com, PO Box 3570, Saint Johns, AZ
85936, support@traversence.com: `ClientOrders::FROM`), Billed To, and Invoice Details (invoice # `TRV-<year>-<order>`,
date, due date from payment terms Due on receipt / Net 15 / Net 30, P.O. #), then the item table (item & description,
status, qty, unit price, amount). **+ Add item** opens a pop-up with Catalog, New product (makes a catalog product
and adds it, without leaving the order), GO Code, Custom and Shipping. **Each item has its own status** (the order is
the carrier): Incoming, Under Review, Pending Approval, Changes Requested, Response Required; Approved (Production
Queue), Creative Queue, Creative In-progress, Pre-production, Holding for resources, In Production, In Finishing, Post
Production; Ready for pickup, Shipped, Delivery In Route, Delivered, Complete; Payment Hold, Collections, Cancelled;
Archived, Deactivated, Deleted (hidden, kept). "Changes Required" was dropped (Jason). Until set, an item's status
follows the order (draft → Incoming, sent → Pending Approval, approved → Approved). Set by hand with a tracking note;
the client is told only when our team taps **Send update** (each item, its status and tracking). The Orders list sums
a client order's items ("2 In Production · 1 Shipped"). `2026-12-09_items_invoice.sql`. Pass 2: ship-to and shipping
cost per item, and the client's page in the same invoice look.

**Addendum (2026-10-16, pass 2: drop ship per item, the client's invoice; Jason).** An order keeps its own ship-to
addresses (`order_addresses`: a name like "Store 2", the name on the package, the address). Each item ships to the
client's address, one of those, or pickup, with its own shipping charge and the vendor's charge
(`order_lines.ship_to`, `ship_cents`, `ship_cost_cents`; `2026-12-10_item_shipping.sql`). Shipping is part of the
order's total (so payments, deposits and QuickBooks work as before) and shows as one Shipping row with the per-item
breakdown; on QuickBooks it's one untaxed "Shipping" line naming each item and destination. The destination can change
any time; the charge only until the client approves. The client is asked for a ship-to address at approval only when
some item ships to them. The client's page now reads like the invoice: the Traversence header ("Order", then
"Invoice" once approved), Billed From / Billed To / details (TRV number, date, due date, P.O. #), each item with where
it ships and, once approved, its status and tracking; items marked Deleted are hidden from them. The order-wide
"Where it is" steps give way to the per-item statuses.

**Addendum (2026-10-16, the order edited in place; Jason, after QuickBooks' invoice screen).** No forms on the order
page: values are edited where they show and save when you leave them (`/api/admin/client-order.php`, JSON + CSRF,
the E-Commerce staff area). Top: **Customer** (a past client by email fills the order; a new one is typed into Bill
to), the email, then **Bill to**, **Ship to** (with "Same as Bill to"; the order's default destination) and **Order
details**: invoice no. `YYYY-MMDD-<order, 5 digits>` (e.g. 2026-1016-00922), terms, invoice date, due date (from the
terms, or set directly), order/P.O. no., payment; then a message to the client. Items: a drag handle (the order is
kept: `order_lines.position`), the number, name and description (in place), status, qty (a catalog item's run size),
rate, amount, a Tax box, and on the right edge the **ship icon** and **delete**. The ship icon's pop-up sets the item's
shipping: style (Drop Ship, Courier, Pick Up), carrier (USPS, UPS, FedEx, Other), a free-text address (empty: the
order's Ship to; replaces pass 2's saved addresses, which still read), our charge, our cost, tracking, and shipping
labels as files (`order_files`, staff only, listed under the item; shipping never has proofs). "+ Upload proof" sits
under each item. A new order opens on the page, not the Add pop-up. `2026-12-11_inline_order.sql`. Still to come:
picking from the QuickBooks customer list.

**Addendum (2026-10-17).** The client's page heads with Traversence and its address under the name, then **Bill to**,
**Ship to** (the order's Ship to, or "each item says where it ships") and the order or invoice details, as on the order
page. "What the client sees" opens the client's page in a new tab: on the live server the page wouldn't load inside
the pop-up frame.

## Addendum, 2026-10-21: shipping as manifests (step 1)

- An order's shipping mode is **No shipping** (with a reason: Electronic delivery, Customer will call, None), **One
  address** (one manifest) or **Multiple destinations** (a manifest each). `orders.ship_mode`, `orders.no_ship_reason`.
- Destinations are `order_addresses` (`is_main` = the order's Ship to), each with an intake estimate (`est_cents`) and
  a method. Estimates add into the subtotal; the actual is the sum of the destination's pack costs, and the variance shows.
- An item splits into **lots** (`order_lots`), each going to one destination. **Packs** (`order_packs`, contents in
  `order_pack_items`) carry box, weight, carrier, service, cost, tracking and a label file.
- Printable pages at `/admin/manifest.php`: the master manifest, one destination, or a pack's packing slip.
- Code: `app/lib/Shipments.php`, `app/ui/pack-form.php`, migration `2026-12-12_shipping_manifests.sql`.
- Next: step 2, product shipping profiles (weight, fit per box, learned from packs); step 3, vendor and carrier APIs
  (4OVER, Signs365, EasyPost or Shippo). Later: gang running; the QuickBooks customer list.

## Addendum (2026-10-26): a catalog line's Rate is each item's

Jason: "The rate per item × the quantity should calculate." On a catalog line, the order builder showed the run size as
the quantity (500) and the stored price of the whole run as the Rate. So a rate typed per item (17.59) was saved as
the run's price, and the line came to $17.59 instead of $8,795.00.

- A catalog line is still stored as one run: `qty` 1, `unit_cents` the run's price, and the run size in its title
  (`… · 500`).
- **Rate** now shows and takes each item's price: the run's price ÷ the run size, up to 4 decimals (17.59, 0.0425).
  A rate typed in is saved as rate × run size (`ClientOrders::rateTotal`). Changing the quantity still prices the run
  from the catalog, as before.
- `ClientOrders::runQty()` and `rate()` are used by the builder's line, the item's details ("500 × $17.59 =
  $8,795.00") and the client's order page ("500 × $17.59").
- A line saved under the old behavior keeps its wrong price and shows a tiny per-item rate (e.g. 0.0352), so it's
  plain to see. Type the rate again, or change the quantity to re-price it from the catalog.

## Addendum (2026-10-26): proof and file uploads: size checked first, a progress bar, the result beside the item

Jason uploaded a 36 MB press proof. It went up whole, with no progress shown, then the page jumped to the top to say
"over 15 MB". He asked for a progress bar, a confirmation before a large upload, and the notice "inline with the
update, not having to jump on the page."

- **The limit:** `PROOF_MAX` is now 100 MB, for proofs and item files alike. The real limit is
  `ClientOrders::uploadMax()`: that, or the server's `upload_max_filesize` / `post_max_size` if lower. The page shows
  it and checks it.
- **Before sending** (every upload form on the order page): a file over the limit is refused at once, under the
  upload, before anything is sent. Over 10 MB in total asks first (`TvAsk.confirm`: what, how big, keep the page open).
- **Sending:** in the background, with a bar under the upload ("Uploading MP5-Manual.pdf: 18 MB of 36 MB (50%)",
  then "Uploaded. Saving…").
- **The result:** an error shows right under the upload, with no reload. A success reloads the page at the same
  place, hides the notice at the top, and says it beside the item (`TvAsk.say` anchored to the line).
- **Not the same as the gallery's uploader.** The gallery (decisions/0126) and a business's big files (0122) send
  files straight to S3 in parts, so the web server's limits don't apply. Order uploads still pass through the server
  in one piece. Moving them onto the same direct-to-S3 uploader is the next step if files bigger than the server
  allows are needed.


## Addendum 2026-10-27: projects, bundles, and files released by approval

Jason: a client added a German version of a manual, which needs a creative service and its own printed run, each with
its own setup. "Proofs assigned to item, so if the creative services has a proof to approve vs the print ready file
assigned to the printed manual." "Only the final versions approved are ever available once the approval is received
and clients don't have messy folders or access to unapproved concepts. I see approval as the release for use on files
once payment is confirmed." "Client can see cost per item within a project, but only its subtotal" — a bundled item
shows as one item and price, never its lines.

- **Projects** (`order_projects`, `order_lines.project_id`): a heading for an order's items, with a subtotal. An item
  in no project stands on its own, so existing orders are unchanged. In the order: **+ New project**, **+ Add item
  here**, each item's **Project** menu (or **New project…**), **Take off project** (its items stay).
- **Bundles** (`order_line_parts`): a custom item can be made of lines only our team sees (translation 6 × $85,
  typesetting…), each with our cost. The item becomes one of it at their total, so tax, QuickBooks, promo codes and
  shipping keep working on the item. The client's page never shows the lines.
- **Change options** on a catalog item: priced again from the catalog, unless its rate was typed (the rate stays).
- **Proofs stay on the item**, kept apart by purpose: a creative item's concept rounds (v1, v2…), a print item's
  print-ready file. An item's kind is its type (Creative, Print, Additional; a catalog product's own, else Print).
- **What the client may open** (`ClientOrders::released()` / `clientMay()`, enforced by `api/order-proof.php`, not
  only the page): the current proof, to view. Earlier rounds are ours alone, even by a direct link. Nothing is
  downloaded before the order is **paid in full**. Then: in a project with print, the print items' approved files;
  a creative item's approved artwork stays view only. In a project with no print, the creative item's **print-ready
  file** (`order_proofs.kind = 'final'`), added by our team after the artwork is approved (upload target
  `order_final`), is released. A deposit doesn't release files.
- Viewing in the browser can't stop a screenshot; "view only" means no download offered or served. A watermark on
  creative proofs would be the next step if needed.

Migration: `app/migrations/2026-12-27_order_projects.sql`.

## Addendum 2026-10-27 (later): one page, test copies, run sizes, proof files

- **An order is a whole page in Back-Office** at `/admin/backoffice/orders/<id>` (and `/new`): the router draws the
  order page itself, with its module header and menu, no frame inside the module (Jason: "frames inside frames…
  have the page scroll as a whole"). `/admin/client-order.php` forwards there; it's no longer listed as archived. The
  Pulse's Open on an order goes to that page. Admin pages have no site footer.
- **Duplicate** on the order: **Test copy, billed to you** (made in the QuickBooks mode that's on: switch to Sandbox
  first; nothing reaches the client) or **Copy for the same client** (a reorder). It copies projects, items, options,
  bundled lines, rates, run sizes and proofs (each item's latest version waiting again); GO Codes aren't copied.
- **Run sizes** are kept on the item (`order_lines.run_qty`, `2026-12-28_order_run_qty.sql`), not read from its name,
  so renaming an item ("… – DE version") keeps its size. An item renamed before this: set its quantity once and its
  price stays.
- **Proof files**: the item panel's Proofs tab lists every version; each file can be **replaced** (same version) or
  **removed** (a version left empty is gone; the one before it is current again).
- **Messages show where you're working**: an Add item error reopens its tab with what you typed; a change to an item
  or project shows its message beside it. A new service product needs no run size.

## Addendum 2026-10-27 (evening): Edit, the project icon, order documents, quantities that stay

Jason: "the items need an edit button to change item configurations easily, instead of title with toggle"; "Move [the
project choice] to the order toolkit right edge make it an icon that click opens the dropdown"; "I need a way to
attach documents to the order not just items"; "Something keeps resetting the quantity and rate."

- **Edit** (the pencil, first in an item's right-edge icons) opens one form with all of the item's setup: name,
  description, how many (a catalog item's run), rate each or **the catalog's price for this run**, its options, its
  kind (custom items) and tax (`ClientOrders::editLine()`). Typing a rate unticks the catalog price, so a rate typed
  stays. A bundle's price stays the sum of its lines. An error reopens the form with what was typed. Values can still
  be changed in place; the Kind and Change options rows left the item body for the form.
- **The project icon** (the folder) sits with the item's icons and opens a menu: On its own, each project (the
  current one ticked), + New project…. The Project select under the item's name is gone.
- **Files & versions** on the order: the order's own documents (`order_files.line_id = 0`, kinds Document, Reference,
  Artwork; a contract, a P.O., a brief), sent through the one uploader (target `order_file`, line 0), each with
  Remove; and a table of each item's proofs (current version, how many earlier) and files, opening the item's
  Proofs or Files tab. Item files can be removed from their tab too (`ClientOrders::removeFile()`; a pack keeps its
  place without the label). Word, Excel, PowerPoint, text, CSV and InDesign files are accepted for documents and item
  files (stored under short type names, so `order_files.mime` needs no change).
- **Quantities that stay**: the cause was a run size read only from an item's name ending in "· N". A name like
  "… · 1,000 - EN version" read as no size, so the quantity showed 0 and the rate was the whole price; and while
  `2026-12-28_order_run_qty.sql` hasn't been run, a size typed had nowhere to be kept. Now the size is the first
  number after the name's last "·"; until the migration runs, a size typed is written into the name ("… · 250");
  a draft's catalog prices aren't refreshed for an item whose size is unknown; and a quantity is read leniently
  ("1,000", "1 000", "500 pcs", and the hard space an in-place edit can leave), which is what was being refused.

## Addendum 2026-10-27 (night): sandbox orders on their own, item icons, the side panel

Jason: "we should have a section within the orders that are sandbox orders. We should use a different ordering number
system for sandbox orders so they dont consume real order sequencing. It can be toggle collapsed until we need them.
Also include a delete button"; "Status Icon - use the gear and progress circle one"; "Convert items to project icon:
… the one to three tier icon"; "use the folder icon for file management"; "Side panel blows out the invoice page."

- **Order numbers** (`2026-12-29_order_numbers.sql`): a real order's number is `orders.order_no`, the next in the real
  sequence; a sandbox order (made while E-Commerce → Payments is on the QuickBooks sandbox, `qb_env = 'sandbox'`) is
  **S-1, S-2…** (`orders.test_no`). Orders from before keep the number they had (their id). `Orders::no()` is the
  number shown everywhere (pages, lists, emails, QuickBooks notes, the invoice reference); `Orders::number()` gives
  one when an order is made and again at approval if its mode changed. The id stays the address
  (`/admin/backoffice/orders/<id>`). Before the migration runs, numbers are ids, as before.
- **Sandbox orders** are out of the Orders lists, counts and site orders, in their own **Sandbox orders** section at
  the foot of Back-Office → Orders, folded until opened, each with **Delete** (`ClientOrders::deleteTest()`: the order,
  items, proofs and files from storage, projects, shipping, history and test money; only a sandbox order; a real one
  is cancelled, never deleted). What was made in the QuickBooks sandbox stays there.
- **An item's icons**: Edit (pencil), **Status** (gear in a progress circle: the item panel's Status tab), **Files**
  (folder: its Proofs tab, every version, then Files), **Project** (the one-to-three tier icon: its menu), Ship,
  Delete. Two rows of three on a wide screen, pairs on a phone. The order has a **Files** button (folder) at the top,
  to its Files & versions card.
- **The item panel** opens over the page unless the screen is wide enough (1500px) to sit beside it; the order's
  three header boxes wrap instead of squeezing, and their labels don't break.
- **Shortcuts** and the filter menus sit under the site's top bar when the page scrolls.
- **Later the same night:** the items lay out by the room they have (a container query on the items card), not the
  screen's width, so with the item panel beside them they switch to the stacked layout instead of squeezing the
  description; the header boxes wrap with a wrapped box taking the whole row; and a message beside an item sits inside
  its description (it had been dropped into the row's grid, pushing the icons out under the menu). Removing a file
  deletes it from S3 too (`Store::remove`), checked against the S3 test server.
- **Open as the client** (Jason: "I want a way to process sandbox orders directly to the proofing/cart"): a sandbox
  order has a button at its top, and a row button in Sandbox orders, that opens the client's page in a new tab to look
  at the proofs, approve and pay with a test card. A draft (or a sent link that's expired) is marked sent first, with
  no email (`ClientOrders::send(…, mail: false)`, logged "Opened as the client by our team"). A real order is refused:
  it's sent.

## Addendum 2026-10-28: wire, check and cash; the payment hold; progress emails

Jason: "for this client I will have them wire me the money … select wire and then a screen to enter the wire
confirmation code or an email that lets them click and enter code once sent. Once I get the wire I will need to release
the order from the payment hold … cash, check, or other payment methods would bypass the quickbooks processing as
well." And: "We email you as your order moves along. this should be a check box".

- **Also pay by** (`orders.pay_offer`, `2026-12-30_pay_methods.sql`), ticked by our team on the order: Wire transfer,
  Check, Cash. The client's **Pay** box then offers Card (unless the order is paid outside QuickBooks) and those.
- **Choosing one** (`ClientOrders::choosePay()`) shows how to pay on the page and emails it, with the order number for
  the reference and a link back to the page; our team is emailed. The details are `WIRE_INSTRUCTIONS`,
  `CHECK_INSTRUCTIONS`, `CASH_INSTRUCTIONS` in `.env` (" | " between lines), set by hand only and never editable from
  a page, so a stolen admin login can't swap the bank details.
- **Telling us it's sent** (`reportPay()`): the wire's confirmation code or the check number, kept on the order
  (`pay_report`, `pay_reported_at`); our team is emailed.
- **The payment hold**: an approved order waiting on payment stays out of production (stage empty) until our team
  records the money: **Payment received: release to production** (`recordPayment()`, the method and the client's
  reference filled in), which marks it paid, starts production and emails the receipts. **Release to production before
  payment** (`releaseHold()`) is there for a client we trust. Orders paid outside QuickBooks now wait on the hold too
  (they went straight to production at approval). A card or QuickBooks payment releases it as before.
- The order's QuickBooks invoice is still made at approval when the order isn't "paid outside QuickBooks" (sales tax
  and the books); a wire recorded on it says to receive it on the invoice in QuickBooks too.
- **Progress emails** (`orders.notify_progress`): **Email me as my order moves along** is a checkbox when the client
  approves and under Pay (and after it's paid). Off: no stage emails and no "Send update"; receipts always go.

## Addendum 2026-10-28 (later): a proof's final approval, signed; how a proof is shown

Jason: "For proof artwork approvals: I want a pop-up to confirm …" (the terms below), "Final proofs need a download
option, so in this confirmation window allow a download link here", "Proofs need to be resizable on screen, at least
able to fit full page to window top/bottom or with multiple pages 2, 3, 4 columns with rows."

- **Approve** on a proof (in the proof's top bar) opens the **final approval** pop-up: the terms
  (`ClientOrders::PROOF_TERMS`: 100% Client Responsibility, All Sales Are Final, Error Liability), two boxes to tick
  (`PROOF_CHECKS`) and the client's initials. **Submit Final Approval** stays off until both are ticked and two
  letters are typed; the server checks the same (`decideProof(…, $sign)`), so nothing approves without them.
  **Cancel & Request Changes** goes back to the proof with its Make changes box open.
- The approval is kept with the proof (`order_proofs.client_initials`, `client_ip`, beside `decided_at`;
  `2026-12-31_proof_signoff.sql`) and in the order's history with the terms' version (`PROOF_TERMS_V`). Our team sees
  "final approval initialled …" on each version in the item's Proofs tab. Changing the wording: change
  `PROOF_TERMS_V` too.
- **Download**: the proof being approved (waiting for the client's OK) downloads from the pop-up. Earlier rounds stay
  ours; approved and print-ready files still release on payment in full.
- **How a proof is shown** (`js/tv-proof.js`, the bar's buttons, remembered per device): Width (as before), Page (each
  page whole, top to bottom of the window), 2, 3 or 4 pages across in rows (2 at most on a phone). Our team's viewer
  has the same.
- **Wire details** (Jason: "I really dont want my bank info out there"): `WIRE_INSTRUCTIONS` is optional. Without it
  the page and email say we'll send the details ourselves and confirm them by phone, and our team's email says to;
  a wire's page always warns that our bank details are never changed by email.

## Addendum 2026-10-28 (evening): the client chooses how to pay; our team's bypass

Jason: "This is in the order side, it should be on the checkout side. We should have a way to bypass the checkout as an
admin and push the order into quickbooks/production but we should not configure the order payment method the client
should."

- **The client chooses at checkout.** The per-order "Ways to pay" boxes are gone; every client order offers Card plus
  the site-wide ways in Settings (`CLIENT_PAY_WAYS`, "Ways clients can pay": wire, check, cash; unset: wire and check;
  empty: card only). `orders.pay_offer` is no longer read.
- **Our team's side shows the client's choice, read only:** "The client's checkout: paying by wire transfer, they say
  it's sent (reference …)". **When the money arrives**, our team records it (How it came in starts at Choose…, or the
  client's choice), which releases the hold.
- **The bypass: Approve for the client (skip their checkout)**, on any order (not only those paid outside QuickBooks),
  draft or sent: their proofs count as approved and the QuickBooks invoice is made (sales tax from Bill to), as if
  they'd approved it on their page. **Release to production now** (a box on it) starts production without waiting for
  the payment; otherwise the client pays on their page as usual.

## Addendum 2026-10-28 (night): wire details, shown safely

Jason: "Wire information should be available but super secure"; "for only 2500 or larger. yes only in .env.
6027083795 is the phone number. make it where the screen can not be screen shot, printed, or copied."

- **Wire is offered only on orders of $2,500 or more** (`ClientOrders::WIRE_MIN_CENTS`); below that, card or check.
- **Never emailed, never on the page as text.** Choosing Wire emails that the details are on the order page. There,
  **Email me a code** sends a 6-digit code to the order's email (`wireSendCode()`: 10 minutes, 5 an hour, 5 tries);
  entering it (`wireCheckCode()`) shows the details for 10 minutes as a picture (`/api/wire-card.php`,
  `wireCard()`: drawn on the server, never cached), stamped across with the order number, the client's email, the
  time and their IP. **Hide now** ends it; the 10 minutes ending does too.
- **Logged and told:** each code and each viewing is in the order's history, and our team is emailed on each viewing
  ("If that wasn't your client, call them before any money moves").
- **Hard to copy, print or capture:** a picture (no text to select or scrape), no right-click or drag, copy and print
  blocked, hidden when printed, covered (blurred) whenever the window isn't in front or a capture key is pressed.
  A website can't truly stop a screenshot or a phone camera; the stamp makes any copy traceable to who, when and where.
- **Only in .env, by hand:** `WIRE_INSTRUCTIONS` (" | " between lines) and `WIRE_VERIFY_PHONE` ((602) 708-3795 when
  unset). No page shows or edits them. Without `WIRE_INSTRUCTIONS`, the client is told we'll send the details
  ourselves.
- **The habit that matters most:** the page, the code email and the payment email all say to call (602) 708-3795 to
  confirm before sending, and that we never send or change bank details by email.

## Addendum 2026-10-28 (late): thank you, your orders, one account with the business; QuickBooks on a recorded payment

Jason: "the page doesnt feel like the order was processed. If it complete move to thank you for your order page, link
to account for client to see orders, also this will prompt non-registered clients to login/create an account so we need
to consider how business clients get listing with personal accounts in one process"; "should have a confirm next to
payment details so I can easily push order payment received"; "quickbooks should be updated once marked paid".

- **Thank you.** Once the client has done their part (`ClientOrders::placed()`: paid, or a wire or check they've said
  is sent, or cash), their page opens on **Thank you, … order #… is placed / paid**: what happens next, the steps
  (Proofs approved → Order approved → Payment → In production → Shipped or ready), and the payment box folded away.
- **Their account.** Signed in as the client: **See all your orders**. Not signed in: **Create your free account** (the
  order's email filled in) or **sign in**, both coming back to **Your orders** (`/user/orders.php`, in the member
  menu): every order we prepared for them, newest first, each with where it stands. Orders sent to their email join
  the account once that email is confirmed (`QrIssue::claim()`).
- **One account, the business on it.** A personal account is the person; a business is a listing that account
  manages (decisions/0071, 0104), never a second login. For an order with a company, **Put [company] on Traversence**
  goes to Add a business (`/claim.php?new=1&order=…`), filled in from the order (name, phone, email, address); not
  signed in, it goes through Create your account first and comes back to it. The add-a-business flow's own checks
  (Is this yours?, the email code, the phone) apply as usual.
- **Confirm received.** Beside the client's payment details on our order page: **✓ Confirm $… received** records the
  amount due by the way they chose, with their reference, releases the order to production and emails the receipt.
- **QuickBooks.** Recording a payment (that button, or the form) also receives it on the order's QuickBooks invoice
  (`QuickBooksPay::recordOtherPayment()`: a payment linked to the invoice, the client's reference as its number), in
  the order's own mode (sandbox or production). If QuickBooks won't take it, the payment is still recorded here, and
  the message and history say to receive it on the invoice by hand.
- **A changed proof is approved again** (Jason: "if the proof is changed it needs to be reapproved, so it should happen
  on new proofs or changes made to a file"). Replacing a file in a proof version, or taking one out with others left
  (`ClientOrders::reproof()`), puts the version back to waiting: its approval, initials and IP, or its changes
  request, are cleared, and the history says "the client approves it again". A new version now also marks an approved
  earlier one replaced, so only the latest version can count as approved.
- **Our team sees a real order's client page read only** (Jason, 2026-10-28). When someone on staff (`TwoStep::isStaff`)
  opens `/order.php?t=…` for a real order that isn't their own (`ClientOrders::staffView()`), a banner says so and every
  action on the page is greyed out with the reason: approving proofs or the order, Make changes, promo codes, choosing
  how to pay, the card form, the wire code and details, reporting a payment, and the progress-email box. The server
  refuses them too (`order.php` and the card charge in `/api/client-order.php`, 403 `staff_view`), so nothing done while
  looking changes the order. Viewing and downloading proofs still work, and the client's own link is unaffected. To act
  for a client, staff use the order in Back-Office (Approve for the client, Confirm received). Sandbox orders stay fully
  usable from end to end for testing.
- **Work orders to vendors, and orders handled outside** (Jason, 2026-10-28: "orders that are handled outside QBO and
  TRV I need a way to process the order and send the work order to the vendor, without duplicating the qbo transaction
  or creating unneeded friction"). `app/lib/WorkOrders.php`, `order_work_orders` (2027-01-01_work_orders.sql).
  - **Handled outside: create the work order** (an unapproved order): one step approves it for the client with *no
    invoice made here*, keeps the hand-made QuickBooks invoice number for reference only (`invoice_no`, never
    `invoice_id`, so nothing is posted to it and Confirm received doesn't touch QuickBooks), puts it into production,
    and sends the work order. Where it ships goes on the work order, so approval doesn't ask for a ship-to address.
  - **Work orders** (an approved order): the items (printed ones ticked; creative work and services can be added),
    their files (print-ready files, else the current proof; artwork too), ship to, needed by, notes; Send to the vendor
    or Save without sending; Send again; each shows when it was sent and when the vendor opened it.
  - The vendor gets an email **from orders@trvgo.com** (`ORDERS_FROM_EMAIL`, with `ORDERS_SMTP_*` when that mailbox
    signs in on its own; `Mailer::send(..., 'ORDERS')`) and a private page, `/wo.php?w=…` (60 days, noindex): what to
    make, how many, specs, ship to, due date, notes and each file to download. **No prices and no client billing.**
    Printable. "Ordered from the printer" is set on the order (the client hears only if they asked for progress emails).
- **Vendors** (Back-Office → Vendors, `app/lib/Vendors.php`, `vendors`, `order_lines.vendor_id`): name, orders email,
  contact, phone, website, our account number, notes; retired ones stay for history. An item's Vendor tab picks its
  vendor (or it follows the catalog product's vendor) and links to Send a work order; the work order form picks from
  the list, and a new vendor typed there joins it.
- **Smaller fixes, same round:** rates take up to 4 decimals in any form (`.1225`, `0.12250`, a hard space from an
  in-place edit); several files uploaded at once say "3 files added to proof v1" with their names; the proofs table
  counts each version's files; the staff order page no longer applies promo codes (the client enters one at checkout)
  and offers Make a promo code instead.
- **One notice style, site-wide** (Jason, 2026-10-28): `TvAsk.say()` draws a green ✓ "Success:" or red ! "Error:" notice
  with × beside what was done; with nowhere to put it, a pop-up in the middle (tap the background or × to close). A
  page's message after a form posts (`data-tv-flash="ok|error"`) shows beside the form that was sent (remembered as
  it's sent), opening the section it's in.
- **Pulse and orders** (Jason, 2026-10-28): sandbox (and mock) test orders no longer show in the Pulse; they're in
  Orders → Sandbox orders. A new order being built (a draft our team started in the last 30 days) shows as "New order,
  not sent yet" (Sales, Finish it), so an order like #12 is visible before it's sent.
- **Work orders, step 1: the vendor moves it along** (Jason, 2026-10-28, from how his old system worked). `2027-01-02_work_order_status.sql`.
  - **Numbered `YYYYMMDD-WO####`**: the day it's made and a running number across all work orders (`WO_START_NO` in
    `.env` can continue an old count; Jason: start fresh).
  - **Fields:** project name, total cost (our cost of goods for that vendor; **required to send**), must-have date and
    time, delivery method (Will Call, Internal Transfer, Blind Drop Ship, Local Delivery, Next-day Courier, Ship USPS / UPS
    / FedEx, Freight), P.O. number, production notes, and for each item our product code (the catalog SKU) with the
    vendor's part number, remembered per product and vendor (`product_vendor_parts`).
  - **File types:** Production setup (imposed and output-configured, for a vendor that skips preflight and prepress, like
    Competition) and Print file, 1-up (for a vendor that does prepress, like 4OVER), plus Native, Estimate and Invoice.
    Each vendor says which it takes (Vendors → Files they take) and the work order ticks those.
  - **The vendor's page** (`/wo.php?w=…`, no login): download the files; **Accept at our cost** (their job # and ship
    date) **or Decline** (why); no counter-offers (Jason). Then In production → Shipped (carrier, tracking) or Ready for
    pickup → Complete. Each step is logged, emails our orders inbox, and moves the order's items forward (never back,
    never out of a hold); when every printed item is out the door the order is marked shipped / ready (the client hears
    only if they asked for progress emails).
  - **Ours:** Receive (who, the date) or Reject on receipt (why; the vendor is told), Reprint / Make it again (a new
    work order with the same details), Cancel (the vendor is told to stop), and each work order's history.
  - **Reminders** (`app/scripts/work_orders_tick.php`, hourly), each once: not accepted by 9:00 am the next business day
    after it went; 24 hours before the must-have time; at the must-have time.
  - **Pulse:** declined (critical), past its must-have time (critical), not accepted by that 9 am (alert), rejected on
    receipt (alert), and on its way to us or ready to pick up (Receive it).
  - **Next:** the order management view and the work orders list; then the Collateral Center (collateral tied to the
    item and reusable for reorders; a reorder with the same art shows "Confirm reuse" next to See proof on the client's
    approval page and still opens the sign-off pop-up with initials; clients see only approved collateral released for
    use).
- **Work orders, step 2: the lists** (Jason, 2026-10-28, like his old Order Management dashboard).
  - **Back-Office → Order management** (`backoffice/production`): every item on an approved order, soonest due first:
    order, client, product (with how many), product code, status, vendor and work order, due (the work order's must-have
    date, else the order's). Its Status filter counts each: In the queue, In production, Shipped or ready, Delivered.
    Delivered items stay in the list for 90 days.
  - **Back-Office → Work orders** (`backoffice/work_orders`): every work order with its order, client, project, vendor
    (and their job #), status, must-have date and cost; filters by status (with counts) and vendor. Each opens its order
    at Work orders. Sandbox orders are left out of both.
  - A list filter can now show counts (`count_by` on the tool).
  - **Fix:** the Orders "To make" card stands for paid, in production and ordered together; it used to filter to Paid
    only, so it showed nothing (and the page remembered it).
- **Admin lists remember filters only for a reload or Back** (Jason, 2026-10-28: "orders page still not working"): arriving
  by a link or the menu starts a list fresh, so a filter left from earlier can't hide everything; and a list that
  filters down to nothing says so with a Clear filters button. (`js/admin-kit.js`, every admin list.)
- **Work orders, step 3: the Collateral Center and Reorder** (Jason, 2026-10-28). `app/lib/Collateral.php`,
  `2027-01-03_collateral.sql`.
  - **A collateral item** is a client's configured piece: the artwork (the approved proof, print-ready files, production
    setups, 1-up print files, natives) with its product, options, quantity, vendor and notes. It's **tied to the order
    item** it came from (`order_lines.collateral_id`) and made when an order is approved (`ClientOrders::approve`; past
    approved orders are brought in the first time the Collateral Center opens). A reorder that's approved moves it to
    the newest order of it (its files and specs) and counts the times ordered.
  - **Back-Office → Collateral:** every item with its client, product, quantity, files and whether the client sees it
    (filter: in use / released / not released / archived, with counts). Its drawer lists and downloads the files, opens
    its first and newest orders, and does **Reorder** (quantity; the same art, or with changes), **Release to the client**
    / stop, **Rename**, **Archive**.
  - **Reorder** makes a new draft for the client with the items at their quantities, priced from today's catalog, files
    copied over. **The same art:** the approved proof goes along as a reuse (`order_proofs.reuse_of`); the client sees
    "The same art as before" and **Confirm reuse** next to Review the proof, which opens the usual sign-off with their
    initials (Jason). **With changes:** no proof or output files; a new proof round. The order page's Duplicate menu
    has **Reorder with the same art** for all of an order's pieces.
  - **The client** sees only items released to them, with only what's theirs (the approved proof; print-ready files once
    that order is paid in full), under **Your artwork** on Your orders, with **Ask to reorder** (quantity, changes or not,
    a note): it makes the draft, emails our orders inbox, and shows in the Pulse as a new order not sent yet.
  - Old system: start fresh (Jason).
- **Clients are listings** (Jason, 2026-10-28). `app/lib/Clients.php`, `2027-01-04_clients.sql` (`client_listings`;
  `orders.listing_id` and `ordered_by`; `collateral.listing_id`; `listing_invites` gains name, phone, token, sent_at).
  - **The business is the client.** A client order belongs to a listing (`orders.listing_id`; 0 = a person alone, NULL =
    not settled yet). Its people are the listing's team, with the listing's roles (owner, manager, accountant, sales,
    support, creator, developer, viewer): the business owns its orders and artwork, and whoever orders is a contact.
  - **Tagging = connecting a listing already on Traversence.** Nothing about it changes: not claimed, not published.
  - **Checked from the jump:** opening a staff order connects it when the match is clear (the same phone, the website of
    the client's email, or the same name in the same town); possible matches are offered on the order's **Business**
    row (Connect / a private listing / the person alone); with a business name and no listing at all, a **private
    listing** is made (`is_suppressed`, never shown on the site, never pushed public). **Back-Office → Clients → Add a
    client** checks the same way before it makes anything.
  - **Orders to match** (`backoffice/client_match`, in Clients): our orders not settled yet, grouped by company (else the
    email's business domain, folded into the company that uses it; else the email). Each group is settled at once:
    connect to a listing it may be, by listing #, make a private listing, or a person with no business.
  - **Contacts:** our team (Back-Office → Clients drawer) or the listing's Owner or Manager (business portal → Your team)
    add them; no account needed. **The invite** emails a link to `/join.php`, where they tick **Activate my contact** and
    **Create my Traversence account** (or sign in, when the email has one); the link proves the email. It **claims the
    contact's place, not the listing**: on a public listing nobody has claimed, a contact sees it, but editing its
    details, catalog, look, publishing or deleting it waits for the listing claim (`Team::can`). The portal's Add uses
    the same invite (with **Send again**).
  - **Individuals:** "do you have a listing?" is a checkout step (sign in or guest, then a listing or just me), not a
    gate while configuring. Person-only clients work on their user account; their collateral is theirs alone, like a
    gallery, and a listing's collateral follows its roles. (Next: QuickBooks customer per listing, with "Ordered by"
    on the invoice; reorders across a listing's items; the client views by role; the checkout step.)
