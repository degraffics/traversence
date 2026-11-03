# ADR 0140: Market 360 for a Business: One Module, Guided Segments

**Status:** Accepted (Jason, 2026-11-02: "Market 360° yes. Move customers and sells under it too. Stages, if you need
to."), from his ask the same day: "address the Market 360 for the listing as one module where all of the segments fall
underneath it and have intuitive onboarding for each one". Builds on 0110/0115 (the business portal), 0116 (catalog),
0135 (marketplace and checkout), 0139 (storefronts).

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

## Jason's answers (2026-11-02)

- The name is **Market 360°**, as on the admin side.
- **Customers** and **Quotes & invoices** (sales) move under it too. Inbox and Plan & settings stay outside it.
- Built in stages.

## What the reference guides added

Jason shared common "start an online store" guides (theme, store setup, products, shipping and payments, go live;
brand, register, name and domain, design, payments, optimize, market, support). What they add here: **name and
domain** belong in the Store segment (trvgo.us/<shop> and Connect your domain, 0139); **optimize** (phone view, speed,
search) belongs in Test & launch; **market** and **support** are the "Run your store" half (Marketing; Inbox and
Customers). Registering the business and choosing a niche are the owner's own steps outside the site; the guided steps
can point to them but don't track them.

## Stage 1 built (2026-11-02): the module and Get selling

`market360-stage1_20261102.zip` (4 files; built on the 15:40 UTC sync): `app/business/market360.php` (new),
`app/business/portal.php`, `website/business/router.php`, `app/ui/faq.md`.
- **The menu:** the business portal's menu has a **Market 360°** group: Get selling, then every selling page (Customers,
  Quotes & invoices, Catalog, Marketplace, E-commerce setup, Orders, Sales tax, Shipping & pickup, Storefront & pages,
  Discount codes, Marketing, Reports; each module marks `'in' => 'market'`). Inbox stays under Business tools.
  Every page keeps its address.
- **Get selling** (`/business/<id>/market`): a progress bar ("3 of 7 done · next: Store"), then the seven segments,
  each with what it is and why, where it stands now (from real data), and the button to the page where it's done, the
  next one highlighted. Segment 6 links to the gateway's own dashboard and says its status here is coming.
- **Run your store:** Customers, Quotes & invoices, Orders, Discount codes, Marketing, Reports, greyed with the reason
  where a role or plan doesn't open them.
- Checked with sample data at 390px and desktop: no sideways scroll.

**One button (Jason, 2026-11-02: "like the admin: one button for Market 360").** The menu has a single **Market
360°** button, lit on all its pages; its pages are in a **Market 360° ▾** dropdown beside each page's title, as the
admin's section dropdown: Set up (Get selling, Storefront & pages, Catalog, Marketplace, E-commerce setup, Shipping &
pickup, Sales tax), then Run your store (Customers, Quotes & invoices, Orders, Discount codes, Marketing, Reports),
greyed with the reason where a role or plan doesn't open them (`tv_biz_m360_sections()`). The business Overview's
tiles show Market 360° once, not each of its pages. `market360-onebutton_20261102.zip` (3 files; built on the 15:55
UTC sync).

**Its address is `/business/<id>/market` (2026-11-02).** `/business/<id>/market360` gave the server's own "Not Found":
`business/.htaccess` (not mirrored) passes only letter-only module names to the router, so the module key is `market`
(the label stays Market 360°). `market360-address_20261102.zip` replaces `market360-onebutton_20261102.zip` (the same 3
files with the one-button change and the new key; built on the 15:55 UTC sync).

## Next stages

2. **Verification & payouts:** read the connected account's status from Stripe (can take payments, can pay out,
   what's still due) and Square, and tick segment 6 from it.
3. Each segment's own guided checklist at the top of its page (as step 5 of E-commerce setup has): Business profile,
   Store (logo, colors, pages, address, domain, phone preview), Catalog (first item with price, photo, stock), Shipping
   & tax (moved out of E-commerce setup's steps 2–4 into their own pages' checklists).

**Since 2026-11-03 (decisions/0141):** **Widened by 0141** (2026-11-03): the seven Get selling segments become the onboarding across 0141's six modules; segment 6 (Verification & payouts) is 0141's Trust score; the stage plan continues as 0141's phases A–E.
