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
