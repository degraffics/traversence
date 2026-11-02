# ADR 0140: Market 360 for a Business: One Module, Guided Segments

**Status:** Proposed (Jason, 2026-11-02: "address the Market 360 for the listing as one module where all of the segments
fall underneath it and have intuitive onboarding for each one"). Waiting for his OK on the layout below before building.
Builds on 0110/0115 (the business portal), 0116 (catalog), 0135 (marketplace and checkout), 0139 (storefronts).

## Why

Selling is spread over ten separate items in the business portal's menu (Catalog, Marketplace, E-commerce setup,
Orders, Sales tax, Shipping & pickup, Storefront & pages, Discount codes, Marketing, Reports). Each is fine alone, but a
business can't see where it stands or what comes next, and E-commerce setup repeats parts of the others (tax,
shipping). Onboarding follows the usual e-commerce path (profile, store, catalog, payments, shipping and tax,
verification, test and launch), so the portal should too.

## The module

**Market 360** is one menu item in the business portal, like Market 360° on our admin side. It opens on a **Get
selling** overview: seven segments, in the order a business does them, each a card that shows where it stands, ticks
itself from real data, and opens the segment. Segments sit under it as its sections (one line each in its sub-menu);
the old menu items move under it and their links keep working (redirects, archive register).

| # | Segment | What's in it (today's pages) | Done when |
|---|---|---|---|
| 1 | Business profile | Name, contact, hours, about (Plan & settings, the listing) | Claimed, with contact and hours |
| 2 | Store | Storefront & pages, logo, colors, `trvgo.us/<shop>`, own domain (0139) | Storefront published |
| 3 | Catalog & inventory | Catalog, Marketplace items, photos, stock, Square import | One item published with a price and a photo |
| 4 | Getting paid | Connect Stripe or Square, direct ways (E-commerce setup step 1) | A way to be paid is on |
| 5 | Shipping & tax | Shipping & pickup, Sales tax, holding items | A way to get it and a tax rate (0% counts) |
| 6 | Verification & payouts | Stripe's or Square's own checks (identity, tax ID, bank) | The gateway says it can take payments and pay out (new: read from Stripe/Square) |
| 7 | Test & launch | Test purchase (0135), phone preview of the store, Go live / pause | A test paid, then live |

After launch the overview becomes the running view: **Orders**, **Discount codes**, **Marketing**, **Reports** sit
under the same module as its "Run" half.

## Onboarding in each segment

- Each segment opens with a short **what this is and why** line, then a checklist of its own steps that tick
  themselves, with the button that does each step right there (CLAUDE.md, Pulse: a step doable here is a button).
- What a role or plan can't do is greyed with the reason (0109). Nothing is locked by order: segments can be done in
  any order, but the overview suggests the next one.
- A segment that needs an outside service (Stripe, Square) says so and carries its link.
- Test mode is visible throughout: while payments are in sandbox, every segment that touches money says so.

## Questions for Jason

1. The name in the business portal: **Market 360** (as in the admin) or **Selling**?
2. Should **Customers** and **Quotes & invoices** move under it too, or stay as their own (they serve quotes and
   services as well as the Marketplace)?
3. Build order: the overview and segment shells first (one zip), then each segment's own checklist in turn?
