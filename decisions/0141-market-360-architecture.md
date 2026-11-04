# ADR 0141: Market 360° Architecture: Four Levels, Six Modules, Nine Item Kinds

**Status:** Accepted (Jason, 2026-11-02/03, in review of his "Traversence Master Architecture & Platform Specification"
and the module map). Builds on and widens 0140 (Market 360° for a business; its Get selling segments become this
module map's onboarding). Plan prices stay as in `commercial.md`, not the spec's.

## 1. The shape

| Level | Who it's between | Modules |
|---|---|---|
| 1. Platform | Traversence ↔ the business | **Business Identification**, **Money & Processing** |
| 2. Operations | The business's own work | **Products & Inventory**, **E-commerce** |
| 3. Storefront | The business ↔ its customers | **Storefront** (the public face only: no billing or admin forms) |
| 4. Growth | Reaching people | **Marketing** |

Market 360° is one button in the business menu (0140); its dropdown groups these six modules. A shared action bar
(Add, Edit, Download, Duplicate, Delete, Archive, Lock) works the same in every section, as the admin's command strip
(0129). AI Assist and Launch (go live) sit on every Market 360° page.

## 2. Settled rules

1. **Money never pools.** Every sale is the business's own, charged by its own Stripe or Square; Traversence takes
   0% and never holds, routes or settles a business's money. NEXUS routing only chooses which branch's gateway and
   fulfillment an order goes to; each branch connects its own gateway.
2. **One invoice per business.** A cart with several businesses is paid business by business, each step naming whose
   account it goes to ("1 of 2 paid"); each business sends its own receipt. The buyer also gets one combined "your
   orders" summary (page and email) linking each receipt. It is a summary, never an invoice.
3. **Going live opens the shop only.** The listing and its map pin stay in discovery whether the shop is open,
   paused or never set up.
4. **Reach is what we already do:** posts to the feeds of people who linked the business, tag followers, community
   groups, published events, deals and stories, GO Codes, mentions. No pushing to people who didn't choose it; no paid
   reach (0007, 0031). **No caps** on what a business posts: the person's own controls are the cap (link or not,
   frequency, unsubscribe, the master switch). Offers are **grouped per business** into one card in feeds and the
   local-deals digest, so posting more never takes more space.
5. **Unsubscribe is the 0047 framework**: per business (with when they may ask again), frequency, sharing, the master
   switch, and unlinking. Every email, text and push links straight to it.
6. **Prospects and customers** (the clear line):
   - A **prospect** linked the business. Linking is the consent to be known to it (the Linking consent says so); contact
     details only as the person shares them. The business sees a **live view** of the person's own settings, never a
     copy, so unlinking or changing sharing takes effect at once. **No export** of prospects.
   - A **customer** transacted (bought, booked, ordered, asked for a quote). The business keeps what the transaction
     needs in its own walls (0110), and can export its customers. Marketing to a customer needs the checkout opt-in.
   - Anonymous browsing: counts only, never who.
7. **No income, no expense** (CLAUDE.md): email, texts, push, ID checks and AI cost only inside a paying plan's
   allowance, a paid top-up, or a fee charged first.

## 3. Business Identification

- **Legal identity:** legal name, DBA, entity type (sole prop, LLC, corporation…), operating address.
- **Trust score (the KYC hub):** tasks the owner does to raise it, each a button: verify the listing's phone (the
  claim, built); add legal identity; match state records (Arizona Corporation Commission, Arizona TPT license lookup,
  New Mexico Secretary of State; free, our own checks; access confirmed before building); connect Stripe or Square with
  their verification complete (read from them); verify the owner's ID; accept the agreements. The public badge names
  only what's done ("Verified: phone · registered business · payments · owner ID"); we never claim more.
- **Owner ID check:** Stripe Identity (pay per check, about $1.50; the hosted page takes the ID photo and selfie; no
  Social Security numbers, no knowledge questions). Included for a business on a **paid, active** plan (paid, current;
  trials and 100%-off promotions don't count); otherwise a **$1.95** one-time fee, paid before the check starts. We keep
  only "Owner ID verified · date · Stripe Identity"; the images stay with Stripe.
- **Roles:** Team & roles as built (Owner, Manager, Accountant, Sales, Support, Creator, Developer, Viewer; White Glove).
- **Plan & billing:** Plans as built. **Support:** Pulse becomes the business's line to Traversence.

## 4. Money & Processing

- **Getting paid:** Stripe or Square (card, Apple Pay, Google Pay). **Direct payments:** Zelle, bank transfer, check,
  pay on pickup.
- **Tax & exemption:** rates as built; **tax-exempt buyers** when the business switches it on. Collecting tax, and
  deciding who's exempt, is the business's responsibility, not Traversence's: no gate or review on our side, just a
  plain record of **who** (the buyer and the business), **what** (the form, e.g. Arizona Form 5000A resale or Form 5000,
  and its certificate number), **how long** (valid until) and **proof** (the uploaded form). The business chooses
  whether a certificate applies at once or after it looks; it can revoke one any time. Tax comes off that buyer's
  orders with that business while it's valid. Kept in the business's walls (Jason, 2026-11-03).
- **Checkout disclosures:** refund window, deposits, holds, chargeback notes (the terms work, Jason drafting).

## 5. Products & Inventory: nine item kinds

One catalog (built: price, cost, SKU, stock, options, Square sync, gallery). An item has a **kind**, and a kind is a full
configuration: its editor fields, its item-page layout, its checkout steps and disclosures, what the buyer gets after
paying, how Orders handles it, its default policy wording, and which gateways support it (greyed with the reason where
one doesn't yet). The business's main kinds suggest its storefront theme (Retail, Venue, Lodging, Service).

| Kind | Adds |
|---|---|
| Physical goods | Weight, dimensions, stock alerts, pickup |
| Service / labor | Area served, hourly or flat, travel fee, scope note |
| Made to order | Spec fields, deposit, proof sign-off, balance invoice (quotes as built) |
| Event tickets | Date and time, capacity, terms, GO Code ticket scanned at the door |
| Digital goods | Secure file, link after payment, expiry by time and clicks |
| Subscriptions & memberships | Billing cadence, manage and cancel (Stripe first; Square after) |
| Bookings & reservations | Check-in calendar, occupancy, lodging tax, house-rules consent |
| Harvest & perishables | Pickup windows, freshness and cottage-food notices |
| Rental equipment | Availability, duration, damage waiver, deposit |

**Deposits:** the business chooses; the form offers only what works: a **hold on the card** (short rentals: holds last
about a week, the limit differs by gateway) or **charge and refund on return** (any length).
**Gateways differ** (subscriptions, holds); a kind can reach one gateway first, greyed with the reason on the other.

## 6. E-commerce

Orders (built), Customers (built), Bundles (new: several items as one offer, each item's stock reduced), Routing
(single listing; NEXUS branch choice; the multi-business checkout of §2), Revenue (reports, payout status from the
gateway), Coupons (built), and the **chargeback packet** (an order with its acceptance record, for the business to
send its gateway).

## 7. Storefront

Branding, Pages, Domains (our edge, 0139), Publish (shop open, paused, draft), Mentions: built. Layout themes: new.

## 8. Marketing

Offers and news to linkers (0047, built), feeds, tag followers, groups, published content: built. New: a **local
deals** digest people opt into (place, categories, frequency; one card per business), **email campaigns** to customers
who opted in (plan allowance), then texts (explicit written consent; attorney-reviewed wording) and push.

## 9. Phases

| Phase | What |
|---|---|
| A | The six modules in the Market 360° dropdown; the Trust score page with what we hold; Stripe and Square status read; the linkers view matching the Linking consent (names, contacts as shared, no export) |
| B | The terms work: the acceptance record, store policies, checkout disclosures, the seller agreement before Go live, the chargeback packet (waits on Jason's draft) |
| C | The item-kind framework, then all nine kinds in turn: physical and service, made to order, tickets, digital, perishables, rentals, bookings, subscriptions |
| D | Bundles, tax-exempt buyers (who, what, how long, proof), legal identity, state-record checks, the owner ID check, the Pulse support line |
| E | Storefront themes, the local-deals digest, email campaigns, then texts and push |

**Phase B is decisions/0142** (2026-11-03): legal texts as versioned templates filled from business identity, the acceptance log, the Marketplace section of the Terms, and the rulebook of what can be sold.

## Built: zip 1, Phase A (2026-11-03, built on the 13:53 UTC sync)

- **Six modules in the dropdown** (`tv_biz_m360_sections()`): Get selling first, then Business Identification (Trust
  score, Legal identity), Money & Processing (E-commerce setup, Sales tax, Store policies), Products & Inventory
  (Catalog, Marketplace, Bundles), E-commerce (Orders, Customers, Quotes & invoices, Shipping & pickup, Discount codes,
  Reports), Storefront (Storefront & pages), Marketing (Linked people, Marketing). Pages not built yet (`legal`,
  `policies`, `bundles`, `marketing`, `reports`) are listed greyed with "coming" and what they'll do. Get selling's
  "Run your store" tiles became the six modules. On a phone the dropdown spans the header's width.
- **Trust score** at `/business/<id>/trust` (`app/business/trust.php`): six tasks; phone (the claim: entity status
  `verified`, button Confirm by text) and payments tick from real data; legal identity, state records, owner ID (its
  price rule shown) and agreements show as coming. The badge text names only what's done ("Verified: phone ·
  payments"); showing it on storefronts and items comes later.
- **Payments verified:** `MarketPay::accountStatus()` (Stripe `GET /v1/accounts/{id}` with the platform key:
  `charges_enabled`, `payouts_enabled`, `requirements` in plain words, a dashboard link; Square `GET /v2/merchants/me`,
  ACTIVE). Cached on `market_gateways` (`acct_json`, `acct_checked_at`; migration `2027-02-01_gateway_status.sql`)
  by `MarketCheckout::payStatus()`, tied to the account id; asked daily by `market_tick`, hourly at most from Get
  selling, every 10 minutes from E-commerce setup (a status line under the connected gateway), and on Check now (busy
  spinner, at most once a minute). Get selling's segment 6 ticks from it and says what's still needed.
- **Linked people** at `/business/<id>/linkers` (`Marketing::linkers()`): names under the open-links rule of
  `Links::linkedBy` (0052: made under the open terms or the notice seen; paused and suspended people hidden), offers
  status, contact fields only as each person shares them, read live (`cardForBusiness`); search and pages of 50; no
  export. Counts stay counts.
- **Restored in the same zip:** the admin's Storefronts and Custom domains sections (`manifests.php`, `tools.php`) and the
  FAQ's two storefront entries, lost to the Nov 2 01:36 UTC upload, re-applied from the 01:27 UTC sync onto the live
  copies (nothing newer undone).

## Built: the nine item kinds, Phase C in one build (2026-11-04, built on the 12:55 UTC sync)

Jason, 2026-11-04: "the 9 were decided, so they should be represented properly where ever they present, doing something
that is half done is not a good practice." Zips 4, 5 and 6 became one: `item-kinds_20261104.zip`.

- **One definition:** `app/lib/ItemKinds.php`: the nine kinds (name, help, what the price is per, how it's bought: cart,
  quote, dates, subscribe), each kind's fields (§5's table), the notice or conditions it brings to the rulebook
  (Made to order, Digital, Harvest → perishable, Bookings → lodging), the gateways that take it (Subscriptions: Stripe),
  readiness (a ticket's date and seats, a digital file, a harvest window, house rules), and what buyers see (`facts()`).
  Earlier kinds carry over: item and other → Physical goods, class → Event tickets.
- **Catalog:** the Kind dropdown lists the nine; **Kind details** on an item's card asks its kind's fields
  (`b_products.kind_data`, BusinessData version 8). The Marketplace page shows each item's kind and what it still needs,
  and a Digital item's file (upload target `market_digital`: private in the business's folder, never public). Track stock
  only for kinds counted in stock.
- **Publishing** copies the kind and its details (`market_items.kind`, `kind_data`); a kind not ready doesn't publish,
  with what's missing. A kind's own rule (lodging) asks for its conditions without the "is it?" question. Fixed in the
  same build: notice flags weren't being set on publish since the recall check went in.
- **Shown:** the item page (kind tag, price per night, day, month or hour, a section of the kind's details), Marketplace
  cards, the storefront grid, the Marketplace's Kind filter (the nine), Get selling (published items by kind).
- **Buying** (`app/lib/KindCheckout.php`, one checkout path): Service adds its travel fee; an hourly service is quoted.
  Made to order asks its questions per line and takes its deposit (`market_orders.pay_now_cents`; Stripe and Square
  charge only that; the rest is invoiced). Tickets hold seats from capacity. Harvest asks a pickup window. Bookings and
  rentals are Buy now with dates (overlaps read from orders, no separate calendar), nights or days as the quantity,
  lodging tax as its own line, house rules agreed (an Acceptance record, `house_rules`), a rental's waiver and deposit.
  Subscriptions are card only, Stripe Checkout in subscription mode (trial days). "How you get it" shows only when
  something ships.
- **After payment:** a ticket code per seat (`market_tickets`; `/market/ticket.php` with its QR; the business's team checks
  it in once); a download link per digital item (`market_downloads`; `/market/download.php`, uses and days counted);
  a subscription's state and Cancel at period end on the order page; a rental's deposit **held** on the card (Stripe, 6
  days or fewer, captured on return) or **charged and refunded**, settled by the team's **Mark returned** with any damage
  kept. A physical item's stock alert tells the business when a sale reaches it.
- **Orders:** the business's order list shows dates, guests, windows, answers, deposits and tickets checked in, with
  Open (the order page). Square import maps product types (appointments → Service, events → Tickets, digital,
  memberships → Subscriptions, room reservations → Bookings).
- **Migration** `2027-02-05_item_kinds.sql` (main database): the item's kind as text with its details, orders' amount
  due now, payment mode and subscription reference, and the tickets and downloads tables. Safe to run twice.
- **Also in this zip:** the notice-layers fix (`js/tv-ask.js`, `js/admin-kit.js`), not uploaded yet on its own.
- **Not yet:** Square subscriptions (greyed with the reason), a booking calendar view for the business, proofs sent from
  Made-to-order orders beyond Quotes & invoices as built.


## Deleting a catalog item (Jason, 2026-11-04: "how do I remove or delete items"; built on the 14:00 UTC sync)

An item's card in Catalog has **Delete** (business portal), confirmed with "Delete it for good" and what it does. The
item comes off the Marketplace, the storefront and the Shop (`MarketBiz::forget`: its Marketplace record stays,
unpublished, for the orders that name it), out of the catalog (`Products::delete`); quotes, invoices and orders keep their
own copy of its lines. A Square-linked item's link is kept as `removed`, so the next import doesn't bring it back; a
digital file no published item still sells is removed. **Hidden** (Details → Shown) stays for keeping an item without
showing it. The admin kit gained an action that removes its record (`['gone' => true]`): the drawer closes and the list
reloads.

## Photos in the catalog card (Jason, 2026-11-04: "what about photos?"; built on the 14:38 UTC sync)

An item's card in Catalog has **Photos**: its draft photos (the first its cover), **+ Photos** through the one uploader
(target `catalog_photo`) and × to take one off (`MarketBiz::setDraftPhotos`). The same draft the Marketplace page shows,
so there's one set of photos, wherever they're added; a published item shows them after Publish changes. The admin kit
draws a `photos` block for any record that carries one (`items`, `max`, `target`, `ctx`, `note`).

## Publishing from the catalog card (Jason, 2026-11-04: "another place to finish my work for putting items online. Make this seamless."; built on the 14:45 UTC sync)

An item's card in Catalog does everything to put it online: **Online** says where it stands (not online yet, online and
up to date, online with changes not published, unpublished, waiting for our team, taken down) with the version and
date; **Publish** (the card's primary button) asks the Marketplace category and what buyers can do right there, and is
**Publish changes** after an edit; **Unpublish** takes it off. A licensed or conditional item asks its questions in place
("Is the item itself alcohol?", then its conditions), the same path as the Marketplace page (`MarketBiz::publish`,
`MarketRuleAsk`). The Marketplace page stays, for working through many items and for stock. The admin kit gained a
question an action can ask first (`['ask' => [text, yes, no, yes_input, no_input]]`): answered, the action is sent again
with the answer.

## The Acting-as mode bar (market360-build-briefs.md zip 4; built on the 15:39 UTC sync)

`tv_acting_bar()` (`app/ui/context-switcher.php`) replaces the band inside the page (`tv_acting_strip()`, removed from
`app/ui/app-shell.php`): a slim full-width bar at the very top of the page, above the header, drawn by `page-shell.php`
and the dashboard before their top bar, so it never sits between the bar and a hero. In the kind's colour (amber listing,
blue Steward, sage Admin) with its icon glowing softly (none with reduced motion): "Acting as **M2 ServCore** · Owner".
**End** (busy while it answers) posts `{context: "member"}` to `/user/api/context.php` and reloads the same page as you,
still signed in; **Switch** beside it. Never for just you or a staff login; one line at 390px (the name ellipsed, the role
dropped under 420px). Not on the home page, which starts no sign-in session (it shows no badge either), nor the old
directory page (`directory/classic.php`), whose bar is fixed to the top of the screen.

## The account bar for everyone signed in (Jason, 2026-11-04; built on the 15:46 UTC sync)

"The end is like saying I am done working as this listing and should go back the users dashboard on exit", and "the bar
at the top is good for all users and listings … switch between accounts easily and add a sign out to the bar too".
`tv_acting_bar()` now draws the bar for everyone signed in: as yourself, "Signed in as **name**" in cream with
**Switch** and **Sign out**; acting as a listing, Steward or Admin, as before in its colour with **End**, **Switch** and
**Sign out**; a staff login, "Traversence staff · name" in sage with Switch and Sign out. **End** goes to your own
dashboard as you (the business portal's own pages switch you back into the business as they load, so staying on the same
page couldn't work) and says "Done as M2 ServCore: you're back to your own account" there. **Sign out** uses the header's
own (`siteLogout()`). Where the menu bar shrinks to icons pinned to the top (the signed-in side on a phone, app-shell.php),
the bar is pinned above them and the page starts below both.

