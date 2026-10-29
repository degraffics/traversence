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

## Addendum, 2026-10-29: COGS checked before a work order goes (Jason)

The first step toward bills of materials, made for costing jobs rather than stock:

- **Cost list** (Back-Office → Cost list, `cost_items`): paper at the best price on the market (Competition Printing uses
  its own paper; we pay its cost a sheet at the best current price), binding and other materials. Each has a package
  price, how many in it, a discount, its source and link, and the date it was priced; its cost a sheet or each is worked
  out, never typed.
- **Recipe per product** (`cost_recipes`): printed parts (pages, one or both sides, how many to a sheet, printing a sheet,
  the stock) and other costs (a rate each or once a run: binding, labor, P&S), spoilage (0 outsourced; 2% was the
  in-house figure), and price tiers (run → multiplier on COGS, the price each rounded up to the cent). With none, a
  starting recipe from Jason's coil-bound manual worksheet. Sheets = run × leaves ÷ to a sheet (+ spoilage), rounded up.
- **Each item's Cost tab** (`order_line_costs`): the recipe at the item's run, saved with the item, laid out like the
  worksheet with each line's working, COGS against the item's price (margin and × COGS), and the tiers. **Save and
  mark checked** records who and when; any change after un-checks it. Lines are marked the vendor's or ours, so the work
  order's cost can be filled with the vendor's share of the ticked items' COGS.
- **The work order asks first** when a ticked printed item's COGS isn't checked ("Save and send to vendor").

First costed job: HK Parts MP5 manuals, 80 pages (4 cover, 76 guts = 38 leaves), 2-up on 11×17 and 12×18:
COGS $736.18 for 100, $7,316.86 for 1,000 (printing at .25 a sheet is two thirds of it).

### The cost list is the catalog's materials (Jason, 2026-10-29)

The cost list is not a side list: it is the **materials** layer of the catalog, what a Print Procurement product is made
of, and what bundles and projects are built from.

- **Material** (`cost_items`): a component with its market cost (paper, coil, shrink wrap), filed in a department
  (Print Procurement › Stock, › Binding, › Finishing). Not sold on its own, unless it is also an option that is a
  service of its own (shrink wrap): then the same entry carries a sell price and its own item.
- **Option → material.** An option ("Guts Stock: 100# Gloss Text", "Binding: 10mm black") points to a material, so
  choosing it sets that line of the product's costing; changing the option changes the COGS.
- **Product recipe = its bill of materials** (parts, sheets, printing, labor), the same one that later counts stock down.
- **Bundle COGS** = its pieces' COGS; **project COGS** = its items' COGS, each with its margin.

Next build: the Cost list moves under Products as Materials, with departments; options link to materials and drive the
recipe; bundles and projects show COGS and margin.

### Materials have variants, and an option resolves to one exact material (Jason, 2026-10-29)

Choosing an option is like choosing a variant: it must land on the exact material, automatically. A material is a
parent (Blazer Gloss Text) with variants by its attributes: **type and finish** (gloss text, matte cover), **weight**
(100#, 148g), **size** (11×17, 12×18), **color**, brand; each exact variant has its own package price, count, source
and SKU. A product's option sets some attributes ("Guts Stock: 100# Gloss Text" = type gloss text, weight 100#, white);
the printed part supplies the rest (it runs 2-up on 11×17, so size 11×17). Together they narrow to one variant, and its
cost a sheet is the stock line of the costing. No variant matches → the Cost tab says what's missing (add that
variant, or pick another), never a guess. The same attribute filters are how you find a stock by hand.

### One system: the catalog is the source, the order is the copy that can differ (Jason, 2026-10-29)

- **One catalog for everything**: products and services we sell, services we buy or do (translation, labor), and
  materials (paper, coil, boxes). Each entry may carry a **price** (to the client), a **cost** (to us), or both; service
  and product are kinds, costed the same way. The separate Cost list becomes this catalog's materials.
- **An item is made of entries** (its recipe / bill of materials, with quantities); its COGS is always that list summed.
- **The order pulls from the catalog**: adding an item copies its price and its cost lines from the catalog, and that
  copy is what the item, its Cost tab, the work order and the profit tile all use. One number: no "catalog cost" beside
  a different "COGS".
- **The order can override** any price or cost for itself. It's marked as overridden with the catalog value beside it,
  and the catalog changes only when someone chooses "Also update the catalog".
- **Adding an item, a price or a cost that the catalog doesn't have creates it there** (marked "added from Order #N"
  for review), so the next order picks it instead of typing it.

Built (2026-10-29, first step): an item's cost on the order is its COGS once costed (order_lines.cost_cents follows the
Cost tab; a draft's catalog refresh no longer overwrites it); an uncosted item's Cost tab starts from its product's
recipe, else one "From the catalog" line equal to the cost it came with; a listed rate typed on an order is that order's
own (shown with the list's beside it); typed costs join the cost list ("Added from Order #N"); "No hard cost" checks a $0
COGS (services done in-house: project rate, no time tracked); "Keep as the product's recipe" also writes the product's
run sizes (each tier's COGS as its cost, its tier price as its price); the product drawer shows "Made of"; Back-Office →
Products shows the materials and services under the products. Still to come: materials with variants, options linked
to materials, hourly services priced by hours.
