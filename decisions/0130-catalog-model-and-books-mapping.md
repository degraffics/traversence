# 0130: The catalog model, and how it maps to the books

Status: Agreed in discussion (Jason, 2026-10-28); to be built in steps. Builds on 0101 (catalog and client orders),
0106 (vendors and work orders), 0116 (business catalogs), 0128 (QuickBooks roles, tags and links).

## The rule

**Traversence keeps its own catalog, departments, bundles and money accounts. QuickBooks (or any bookkeeper) is
connected to them by tags, and is never needed for them to work.** Whatever the bookkeeper's plan or features
(QuickBooks Online Plus has bundles, Simple Start doesn't; none has real assemblies), Traversence does the same thing,
and the connection writes what that bookkeeper can take.

| Traversence | Tagged to (QuickBooks) |
|---|---|
| Department tree (Print Procurement › Finishing Department) | Its product categories, any depth |
| Product (Gift Certificate Vouchers, Logo Creation) | One item: a general one standing for many products (Print Procurement), or a specific one (Logo Creation) |
| Variant (Blank tee · Black · L) | Its parent's item |
| An option that is a service of its own (shrink wrap) | Its own item (Finishing Department:Shrink Wrapping) |
| Bundle | A bundle where the plan has them; else its pieces as lines under the bundle's name |
| Funding account (where money lands: card processor, operating checking, petty cash) | An account: Undeposited Funds or a bank |
| Client (a listing) | A customer |

## Catalog model

- **Departments** are a tree of any depth, read from the bookkeeper's categories when connected and kept here either way.
  Products, services and reports group by it (a top level, or one branch).
- **Products** are the parent: name, description, media, department, kind (print, service, other; a business's item,
  service, class), service type (creative, print, additional services, used by promotions), tax treatment.
- **Options, made to order** (paper, coating, sides, turnaround, proof, finishing): price add-ons, no SKU per combination.
  **Option sets** are defined once and shared by many products (a "business card specs" set); a product adds or overrides.
  An option can be **a service of its own**: it carries its own item (so its income and tax go where the books say),
  can have its own vendor, and can be reordered on its own.
- **Variants, stocked** (apparel blanks by size and color, stocked envelopes): real rows with SKU (`PARENT-COLOR-SIZE`),
  attributes, stock, cost and price adjustment. Not EAV.
- **Bundles**: one line to the client, pieces underneath, each piece its own product or service and item, so income
  splits correctly in the books.
- **Later, assemblies**: a product's bill of materials (blanks, vendor printing, finishing), with stock counted down when a
  job goes to production. Ours, not the bookkeeper's.

## Production chains: finishing and mailing are steps of their own (Jason, 2026-10-28)

Finishing isn't only an option on the printer's job. One printer prints; a finishing house does what the printer can't;
a mailing house takes the finished pieces and sorts them for postage. So an order line can have **production steps**,
each a service with its own vendor and work order, in order: print → finish → mail. Each step:
- knows the line it works on and where its input comes from (the printer ships to the finisher);
- is billed as its own line (or a bundle piece) with its own item;
- can be **reordered on its own** (finishing or mailing again, on pieces already printed) or with the whole chain.
A finishing option the printer does itself stays an option on the printer's work order.

## Money: funding accounts (part of setting up the bookkeeper)

Traversence has its own **funding accounts**: where a payment lands (the card processor's clearing, operating checking,
cash). Every recorded payment goes to one. Connecting a bookkeeper tags each funding account to the bookkeeper's account
(Undeposited Funds, or a bank like M2 Checking), and payments are written there. Without a bookkeeper they're still
tracked here.

## Order lines keep a snapshot

Product, variant, each option with its price effect and item, run size, steps and their vendors, and the bundle's
pieces. A later catalog change never rewrites a past order.

## On screen

Choosing options or a variant updates the price as you go. Attributes are filters in Quick Search (decisions/0058 §39).
The same engine serves the business portal's catalogs (0116).

## Build order

1. Departments and items: the department tree, general and specific items, suggestions from each item's income account,
   tax category and parent; reading bundle lines on pulled invoices; funding accounts tagged to accounts.
2. Option sets, options that are services of their own (with item and vendor), bundles with pieces tied to items,
   production steps (print → finish → mail) with their own work orders and reorder.
3. Variants with stock, then bills of materials and stock counted down.
