# Market 360° build briefs (decisions/0141, 0142)

Seven zips, built **one at a time**: each is uploaded, synced and checked by Jason before the next starts. Every
brief assumes the session has read `CLAUDE.md` (every-build rules: start from the live mirror, only changed files in
the zip, PHP 8.0, migrations that run themselves, FAQ current, no browser dialogs, busy spinners, 390px check, no
income no expense) and the decisions named. Each zip ends with its decision note (a dated "Built" section in 0141 or
0142), FAQ entries, and a merge into `main`.

**Starting a session:** paste the brief's one line, e.g. *"Build Market 360° zip 1 per market360-build-briefs.md."*

What's live today (2026-11-03): Market 360° is one menu button at `/business/<id>/market`
(`app/business/market360.php`, module key `market`, sections via `tv_biz_m360_sections()` in `app/business/portal.php`,
dispatch in `website/business/router.php`); Stripe checkout uses `ui_mode=embedded_page` pinned to `2026-10-28.endive`;
E-commerce setup step 5 runs a sandbox test purchase.

---

## Zip 1: Phase A, the modules and the Trust score

**Read:** 0141 §1–3, 0140, 0047, 0115. **Files:** `app/business/portal.php`, `app/business/market360.php`,
`website/business/router.php`, `app/lib/MarketPay.php`, `app/lib/Marketing.php`, a new `app/business/trust.php`,
`app/ui/faq.md`; one migration if a status cache is needed.

1. **Six modules in the Market 360° dropdown:** regroup `tv_biz_m360_sections()` into Business Identification, Money
   & Processing, Products & Inventory, E-commerce, Storefront, Marketing (0141 §1); Get selling stays first. Each
   existing page sits under its module; modules with nothing built yet show their coming pages greyed with what
   they'll do.
2. **Trust score page** (`/business/<id>/trust`, letters only in the key; `business/.htaccess` passes letters only): the
   tasks of 0141 §3, each ticking from real data with the button that does it: phone verified (the claim), legal
   identity (coming: zip 7), state records (coming), payments verified (item 3), owner ID (coming, its price rule
   shown), agreements (coming: zip 3). The public badge text it would show ("Verified: phone · payments").
3. **Payments verified:** read the connected account's status. Stripe: `GET /v1/accounts/{id}` with the platform key
   (`charges_enabled`, `payouts_enabled`, `requirements.currently_due`); Square: `GET /v2/merchants/me` (status
   ACTIVE). Cache it (a column on `market_gateways` or a small table), refresh daily and on the setup page; Get
   selling's segment 6 ticks from it, and shows what Stripe still needs with a link to the Stripe dashboard.
4. **Linkers view:** under Marketing, the business's linkers by name (as the Linking consent promises), with contact
   details only as each person shares them (`Marketing::cardForBusiness`, live, never copied), and **no export**.
   Anonymous reach stays counts only.

**Done when:** the dropdown shows six modules at 390px and desktop; the Trust page ticks phone and payments for M2
ServCore (sandbox); segment 6 ticks once Stripe says charges and payouts are on.

---

## Zip 2: Phase B core, the acceptance log and store policies

**Read:** 0142 §1–3, 0141 §2, 0135, 0057. **Files:** a migration (`terms_acceptances`, guarded), a new
`app/lib/Acceptance.php`, `app/lib/ContentTemplates.php` (a `legal` family), the Content templates admin pages,
`website/register.php` (and the other sign-up paths), `website/terms.php`, `website/privacy.php`,
`app/lib/MarketCheckout.php`, `app/business/checkout.php`, `website/market/cart.php`, `website/api/market-pay.php`,
`app/ui/faq.md`.

1. **`terms_acceptances`** exactly as 0142 §2 (MySQL; append-only: the library has no update or delete);
   `Acceptance::record(kind, user, entity, order, doc, version, text, snapshot)` stores the SHA-256 of the exact text.
2. **Legal templates** in Content templates: Platform Terms, Privacy Policy, Seller Agreement, Store policies master,
   Checkout disclosures; seeded from today's `terms.php` / `privacy.php` plus 0142 §3 (the Marketplace section) as
   **drafts**; placeholders fill from identity (Traversence's: System Configuration; the business's: its listing and,
   later, legal identity). Publishing gives a version label and effective date; "needs acceptance" flag. Versions are
   kept (the templates already do). The public pages render the published version; until Jason publishes, today's
   text stays.
3. **Sign-up records** `account_terms` with the Terms version shown.
4. **Store policies:** in E-commerce setup (Money & Processing → Checkout disclosures), the business chooses returns
   (final sale, 7, 14, 30 days, exchange only), cancellations, deposits, house rules (free text); the generated
   Store policies box shows on its items, storefront, checkout and receipt email. Locked clauses (0142 §1) shown, not
   editable.
5. **Checkout line** above Place order (0142 §1): Terms and the business's policies, linked; placing the order writes a
   `checkout` record and the order keeps a JSON snapshot of the policies.
6. Privacy Policy draft adds the acceptance records, their 7-year keeping, and account deletion keeping the record
   without name and email (0142 §2).

**Done when:** a sign-up and a sandbox test purchase each write a record; the order shows its policy snapshot; the
policies box appears on item, storefront and checkout at 390px.

---

## Zip 3: Phase B rest, the seller agreement, the rulebook, the chargeback packet

**Read:** 0142 §1, §4, 0141 §6, 0133 (takedowns), 0079 (standing). **Files:** `app/lib/MarketCheckout.php` (setLive),
`app/business/checkout.php`, `app/business/marketplace.php` (publish), a new `app/lib/MarketRules.php`, the admin
Review queue (manifests/tools), `app/ui/faq.md`.

1. **Seller Agreement before Go live:** Go live greyed with the reason until the current version is accepted (a
   `seller_agreement` record); a new version that "needs acceptance" asks again before the next publish.
2. **Rulebook** (0142 §4) as data in `MarketRules` (prohibited, restricted, notice; words and categories), checked when
   an item is published: prohibited isn't published (reason shown); restricted asks the business to confirm its
   license and conditions (a record) and limits card checkout where the gateway does; notice items show their notice;
   unclear ones go to the admin's Review. Reports use the takedown path; repeats the standing ladder; a shop is paused,
   never its listing or map pin.
3. **Chargeback packet:** on a business's order, "Download dispute packet": the order, receipt, policy snapshot and its
   acceptance record, as one PDF or HTML file.

**Done when:** Go live needs the agreement; a test item with a prohibited word won't publish and says why; the packet
downloads for the test order.

---

## Zip 4: Phase C framework, plus Physical, Service and Made to order

**Read:** 0141 §5, 0116, 0101 (proofs, deposits). **Files:** a migration (`kind` on catalog items / `market_items`,
a JSON `kind_data`), a new `app/lib/ItemKinds.php` (each kind: fields, item-page block, checkout steps and
disclosures, after-purchase, order handling, default policy text, gateway support), the catalog editor, the
Marketplace item page, `website/market/cart.php`, `app/lib/MarketCheckout.php`, the business's Orders, `faq.md`.

1. **Business model:** "What do you sell?" (the nine kinds) on Get selling; Add in the catalog offers only those.
2. **The framework:** one catalog; a kind turns on its fields and blocks; unknown kinds fall back to Physical.
3. **Physical** (weight, dimensions, stock alert, pickup), **Service** (area served, hourly or flat, travel fee, scope
   note), **Made to order** (spec fields, deposit, proof sign-off, balance invoice: reuse the quotes' proofs and
   deposits).

**Done when:** an item of each kind can be added, shown and bought (sandbox), with its fields on the item page and in
the order.

## Zip 5: Tickets, Digital, Perishables

**Read:** 0141 §5, 0134, 0096 (GO Codes), 0127 (uploads). **Builds on** zip 4's `ItemKinds`.
- **Event tickets:** date and time, capacity (sold-out), terms; after paying, a GO Code ticket per seat; Orders scans it
  at the door (once).
- **Digital goods:** file through the one uploader (a new target), link after payment that expires by time and clicks.
- **Harvest & perishables:** pickup windows, freshness and cottage-food notices (from the rulebook's notice tier).

## Zip 6: Rentals, Bookings, Subscriptions

**Read:** 0141 §5 (deposits, gateways differ). **Builds on** zip 4.
- **Rentals:** availability calendar, duration, damage waiver; deposit as **hold** (only for rentals within the
  gateway's hold window, greyed with the reason otherwise) or **charge and refund on return**.
- **Bookings:** check-in/out calendar, occupancy, lodging tax line, house-rules box ticked at checkout (a
  `house_rules` record), check-in/out in Orders.
- **Subscriptions:** Stripe first (Billing on the connected account; manage and cancel easily; auto-renewal terms
  shown); greyed "coming for Square" otherwise.

## Zip 7: Phase D and E

**Read:** 0141 §2–8. Items as separate commits inside one zip; split it if it grows past a normal build.
- **Bundles:** several items as one offer; each item's stock reduced.
- **Tax-exempt buyers** (0141 §4): who, what (form, number), how long, proof (uploaded); the business applies at once or
  after it looks, and can revoke; tax off while valid; in the business's walls.
- **Legal identity fields** (0141 §3): legal name, DBA, entity type, operating address; they fill the business's
  legal templates.
- **Support line:** a business opens a Pulse case to Traversence from Market 360°.
- **Storefront themes:** Retail, Venue, Lodging, Service, suggested by its main kinds.
- **Local deals digest:** people opt in (place, categories, frequency); one card per business; Marketing preferences
  apply.
- **Email campaigns:** to customers who opted in at checkout, inside the plan's email allowance (0137 §8, SES); counts
  only.

## Waiting on setup (not in any zip yet)

- **Owner ID check** (0141 §3): Stripe Identity switched on in Stripe; then $1.95 unless on a paid, active plan.
- **State-record checks:** confirm the Arizona Corporation Commission, Arizona TPT and New Mexico lookups are open and
  allowed.
- **Texts and push:** a provider, and attorney-reviewed consent wording for texts.
- **Publishing the legal texts:** the attorney's pass on the drafts; Traversence's legal entity name.
