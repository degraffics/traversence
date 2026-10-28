# 0128: QuickBooks invoices into Traversence orders, and one product list across both

Status: proposed (Jason, 2026-10-28), for review before building
Builds on: 0096 (QuickBooks connector), 0101 (client orders, catalog, work orders, collateral)

## The problem

Some orders start in QuickBooks: an invoice made there and paid there, like invoice 112906 for Sonoran Hospitality
Group. The site's order for the same job was typed in by hand, and it drifted: $0.0899 a piece instead of $0.1098, and
no shrink wrap, shipping or tax. The site can't record what the client really paid, can't track the job, and can't
offer it again as a reorder.

Products have the same split. QuickBooks has its own items. Our catalog has products with specs, tiers, proofs and
vendors. Today an invoice the site makes finds its QuickBooks item **by name** (`QuickBooksPay::namedItem`) and makes
one if the name isn't there. Rename either side and they drift apart; a typo makes a duplicate item in QuickBooks.

## The rule

**Whoever started the order owns its numbers until Traversence takes it over. From then on Traversence processes it:
production, work orders, shipping, history, reorders.** QuickBooks stays the books.

## 1. Pull an invoice by its number

A **Pull from QuickBooks** box on a client's order page and on the Orders list: type the invoice number and see a
side-by-side preview first. Nothing changes until you press the button.

| Situation | What pulling does |
|---|---|
| **No site order** (it started in QuickBooks), the best first method | Builds the site order from the invoice: client, bill-to and ship-to, lines, quantities, prices, tax, total, due date, payments. The invoice is the source of every number. We fill in what the site needs to process it (product, vendor, project, files), and from then on the site tracks it. |
| **A site order exists, invoice not paid** | Merges into one order linked to the invoice. The preview shows each line both ways; matching lines take the invoice's quantity and price (their proofs and work orders stay), lines only on the invoice are added, lines only on the order are flagged for you to keep or drop. Then the site processes it. A payment in QuickBooks later marks it paid on its own (the webhook already does this for linked invoices). |
| **A site order exists, invoice already paid** | The order is rebuilt to match the invoice exactly (lines, prices, tax, total), the payment is recorded as made in QuickBooks (nothing is posted back, so it isn't doubled), and the site carries on as processor and history. |

Every pull leaves an order event ("Pulled from QuickBooks invoice 112906: 5 lines, $1,111.14, paid") and keeps the
invoice's own line text, so the record shows what QuickBooks said.

**The client:** found by the invoice's customer email, then by company name, through `Clients` (the same matching as the
Clients tool). If none fits, the preview asks: pick a client or make one from the invoice.

**Reorder items:** each product line on a pulled order becomes one of the client's reorder items (the Collateral
Center), as approved orders do now. Shipping, fees and tax stay on the order only. So an invoice with no site order
still gives the client a Reorder.

## 2. One product list, linked to QuickBooks items by id

- **A link, not a copy.** A product links to one QuickBooks item by its **id** (`products.qb_item_id`, already there),
  never by name. Names can differ and be renamed on either side without breaking anything.
- **Each side owns its part.** QuickBooks owns the item's name on invoices, its income account and whether it's taxed.
  Our catalog owns specs, price tiers, proofs, vendor, turnaround and files.
- **Sending an invoice** uses the product's linked item. A product with no link yet: the site creates the QuickBooks
  item from the product and saves the link at once, so it's made only once (your choice: create, then link).
- **Pulling an invoice** maps each QuickBooks item to its linked product. An item with no product yet: the preview offers
  **Link to a product** (pick one) or **Make a product from it** (name, description, price from the item). The answer
  is remembered for every later invoice.
- **Shipping, shrink wrap, fees, tax:** QuickBooks items marked as *charges* map to order charges, not products, so they
  never become reorder items. The list of which items are charges is kept on the mapping page.

## 3. Mapping and migration, so history isn't disturbed

Done once, before any historical invoice is pulled, from a new **Products → QuickBooks items** page (a section in
Back-Office, built from the table widget):

1. **Read** QuickBooks' item list (read only) into a staging table: id, name, description, price, taxable, active.
2. **Suggest** a match for each one: an existing link first, then the same name, then a close name or same price. Nothing
   is linked until you confirm.
3. **Decide** per item: link to a product, make a product from it, mark it as a charge (shipping, fees), or leave it
   (inactive or never sold again).
4. **Report** what's left unlinked. Pulling an invoice with an unlinked item still works: it asks right there.

Guarantees for history:
- **Old orders keep their own lines.** A line stores its title, quantity and price; linking or renaming a product never
  rewrites a past order or invoice.
- **Nothing is written to QuickBooks** during mapping. No items are renamed, merged or deleted there.
- **Links are kept by mode.** Sandbox and production items have different ids (`qb_item_env`), so a sandbox test never
  touches production links.
- **Undo:** each mapping change is logged and can be taken back.

## Build order

1. **Item links and the mapping page** (read QuickBooks items, suggest, decide, report); invoices the site sends use the
   link and save it when they create an item.
2. **Pull an invoice with no site order**: preview, client match, build the order, payments, reorder items.
3. **Merge into an existing order**: unpaid (merge) and paid (rebuild to match), with the line-by-line preview.
4. **Historical pass**: pull past invoices by number, or a date range for one client, once mapping is done.

Each step is its own delivery, tested in the sandbox company before production.

## Open points

- Which QuickBooks items are charges (shipping, shrink wrap, setup, rush): set on the mapping page, starting with
  any item named like "Shipping" or "Shrink" suggested as a charge.
- Credit memos and refunds on a pulled invoice: shown in the preview and recorded, as the refund webhook does now.
