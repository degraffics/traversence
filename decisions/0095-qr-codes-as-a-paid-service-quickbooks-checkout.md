# 0095. QR codes as a paid service, sold through QuickBooks

- **Status:** Accepted
- **Date:** 2026-10-15
- **Builds on:** 0094 (TRVGO short links and QR codes), commercial.md §1.3
- **Amends:** 0094's "plain static codes free for anyone, signed in or not"

## Context

Jason, seeing the free generator live: "Free doesn't really help our revenue. How can we offer QR codes for free to an
active user account for a limited number of basic static codes, but for advanced codes like vCards, events, text, a
one-time purchase of $4.95 plus tax?" He compared online-qr-generator.com: account first, then a 3-step builder
(type, content, design), My QR Codes, and a subscription whose lapse kills the codes.

His decisions (2026-10-15):
- **Free:** 3 basic codes a month per active account. Basic means link, phone, email and Wi-Fi.
- **$4.95 once, plus tax, per code:** contact card, event, text, text message.
- **Payments through his QuickBooks merchant account.** Stripe is on hold, to circle back to.
- **QR plan** (changeable trvgo.us codes): $14.95 a month or $119.40 a year. It stays a trial until billing opens.
- **Logo upload included** on the $4.95 codes.
- **Previews aren't restricted:** "let's not worry about preview scans; that's too much restriction. We can deal with it
  if we see a ton of abandoned carts."

## Decision

### Who gets what (`app/lib/QrIssue.php`)

- **Anyone** can try any code at /qr/: it's drawn as they type. Nothing is kept.
- **Getting a code needs an active account:** signed in, email confirmed, not suspended or limited (`QrIssue::blocked`).
- **Free:** a basic type (link, phone, email, Wi-Fi) in plain black on white, `FREE_PER_MONTH` = 3 per calendar month.
  It's issued at once.
- **$4.95 once, plus tax** (`PRICE_CENTS`), into the cart:
  - the other types: contact card, **event** (new: an iCalendar event, so the phone offers "Add to calendar"), text and
    text message;
  - any code with colors, round dots or a **logo**;
  - a basic code once the month's free ones are used.
- **Our team can grant** a code in someone's cart, at no charge (Admin → Sales → QR orders).
- **Issued codes are kept** (`qr_issued`), with what they hold, their look and logo. They can be downloaded any time:
  SVG for print, PNG for screens (`/api/qr.php?issued=`, owner only).
- **A typo can be fixed for 24 hours** after a code is issued. The look can't change once it's issued.
- **They're static:** they hold what was typed, so they work forever and never depend on us.
- **Downloads are only of issued codes.** The old download of anything typed is gone; the preview stays open.

### Checkout through QuickBooks (`app/lib/QuickBooksPay.php`)

QuickBooks has no hosted checkout like Stripe's. The route that keeps card data off our server and gets tax right:
1. The cart is on our site. At checkout the buyer gives name, email and billing address.
2. We create a **QuickBooks invoice** for the order: one taxable line per code, the "Designed QR code" product (made
   once in QuickBooks), and the customer "Name · TRVGO {member}". **Automated Sales Tax** works out the tax from the
   address.
3. The buyer pays on **QuickBooks' own secure payment page**: the invoice link (`InvoiceLink`), opened in a new tab.
   Card always; bank transfer or Apple Pay if the account offers them. We never see a card number.
4. QuickBooks tells us it's paid: a webhook to `/api/qb-webhook.php`, signed with the app's verifier token and checked
   first. The buyer's own page also asks every 10 seconds while an order waits, so a missed webhook only delays.
   Paid means the invoice's balance is 0; then the codes are issued and a receipt email goes out.
5. Every sale lands in the books with its tax.

**The connection:**
- One company, connected by an admin at `/admin/quickbooks.php` (OAuth 2, accounting scope).
- Tokens are encrypted (sodium) and refreshed by themselves. QuickBooks asks for a reconnect after 100 days without
  use.
- Until it's connected, checkout says **"Buying opens soon"**. The cart keeps its codes, and staff can grant.
- `.env`: `QB_CLIENT_ID`, `QB_CLIENT_SECRET`, `QB_WEBHOOK_TOKEN`, `QB_ENV` (production | sandbox | mock, a local test
  with a made-up tax), optionally `QB_INCOME_ACCOUNT_ID`.
- If the invoice can't be made, nothing is charged and the codes go back to the cart.

**Not chosen for now:** a card form on our own page through the QuickBooks Payments API. Developers report it's fiddly
(sandbox CORS issues, opaque 400s), it puts us closer to card data, and QuickBooks' tax works on invoices. Revisit if
the hop to QuickBooks' page hurts sales. Stripe Checkout with Stripe Tax remains the plan for the QR plan and the
business tiers' recurring billing.

### Records and deletion

- `qr_orders` keeps name, email, billing address, items, subtotal, tax and total: they're sales and tax records, so
  they stay when an account is deleted.
- A deleted account's codes and logos are erased. Its own changeable codes are switched off and emptied; a listing's
  stay with the business.

### Admin → Sales → QR orders

Shows:
- the connection (or connect);
- this month's paid orders, money taken (and tax) and free codes issued;
- the orders, with Check payment and Cancel (its codes go back to the cart; void the invoice in QuickBooks too);
- the codes in carts, with Grant.

## What Jason sets up

1. **An Intuit developer app** (developer.intuit.com):
   - scope Accounting;
   - redirect URI `https://traversence.com/admin/quickbooks.php`;
   - webhook `https://traversence.com/api/qb-webhook.php` for Invoice and Payment;
   - keys into `.env`.
2. **In QuickBooks:** Automated Sales Tax on, and online payments (cards) on for invoices.
3. **In the site:** run `2026-11-28_qr_orders.sql`, then Admin → Sales → QR orders → Connect QuickBooks.
4. **First a sandbox company** (`QB_ENV=sandbox`) for one test order, then production.

## Checked on a copy of the site (QB_ENV=mock)

- **Gating:**
  - three free links issued;
  - the fourth link, a colored Wi-Fi, an event, and a contact card with a logo went to the cart at $4.95;
  - a non-image "logo" was refused and nothing was kept.
- **Downloads:**
  - a free code reads back exactly;
  - a code in the cart, someone else's code, and a typed-content download are refused.
- **Checkout:**
  - refused without an address;
  - 4 codes at $19.80 + $1.70 tax = $21.50;
  - unpaid stays waiting; paid issues all four on the next check;
  - the contact card with its logo reads back exactly.
- **Fixes:** a fix works within 24 hours and is refused after.
- **Cart, grants and checks:**
  - remove from the cart works;
  - a grant issues at no charge;
  - an unsigned webhook is refused (401); a signed one is accepted and a tampered one refused;
  - without QuickBooks, checkout says buying opens soon.
- **Event codes** carry date, time, place and details; an all-day event ends the next day.
- **At 390px:**
  - the builder, cart, checkout form and My codes fit with no sideways scroll;
  - cards sit 3px from the edges;
  - the actions are a column of round buttons on each card.
