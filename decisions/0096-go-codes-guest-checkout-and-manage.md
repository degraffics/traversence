# 0096. GO Codes: no account needed, a cart, guest checkout, Manage GO Codes

- **Status:** Accepted
- **Date:** 2026-10-15
- **Amends:** 0095 (an account was required; 3 free a month)
- **Builds on:** 0094 (trvgo.us), 0095 (QuickBooks checkout)

## Context

Jason, after the paid QR service was built:
- "I want this called **GO CODES**."
- "We should not require someone to be a user for this, but having an account is recommended, maybe required for
  dynamic or advanced codes."
- "We need a **cart and checkout** process. Once the purchase is complete they will land back on their **Manage GO
  Codes** page."
- "Checkout should allow for **guest checkout** if they don't want a user account, but the info they provide could be
  used to open their user account easily."
- "Not 3 a month: let's make it **5 in total**."
- On confirming payment when the buyer leaves Intuit's page: poll the invoice balance through the QuickBooks API, and
  take Intuit's webhooks; don't rely on the browser coming back.

## Decision

**Name.** People see **GO Codes** everywhere: the pages, the nav, the emails, the QuickBooks product ("GO Code"), the
invoice lines, Admin → Sales → GO Code orders. Changeable trvgo.us codes are **Dynamic GO Codes**. The addresses stay
`/qr/…` (and trvgo.us opens Create).

**Who can do what:**

| | Guest (no account) | Member |
|---|---|---|
| Make and preview any GO Code | yes | yes |
| Buy GO Codes ($4.95 once + tax each) | yes, guest checkout | yes |
| Free GO Codes | none | **5 in total** (link, phone, email, Wi-Fi in black and white), once the email is confirmed |
| Keep and download them | with this browser, and by the order's private link (any device) | in the account |
| Dynamic GO Codes | no (an account is needed) | QR plan or a listing on Core and up |

**A guest's codes:**
- They're theirs by a private key in their browser: cookie `tv_go`, 48 random hex characters, HttpOnly, about 400
  days. Only its SHA-256 is stored (`qr_issued.guest_key`, `qr_orders.guest_key`).
- Each order has a private **access token**, a link to Manage GO Codes in the receipt email. Opening it lets that
  visit see and download that order's codes on any device.
- **Signing in or creating an account moves them in** (`QrIssue::claim`): the browser's cart, codes and orders, and
  guest orders paid with the account's email once that email is confirmed. So nobody can take someone's codes by
  signing up with their email unconfirmed.
- The guest's checkout name and email open an account in one step: `register.php?email=&name=&next=/qr/manage.php`.
  The register page now fills those in.

**Pages:**
- **Create** (`/qr/`): type, content, look (colors, round dots, logo), and name. Price shown up front. "Add to cart ·
  $4.95", or "Get it free" for a member with free codes left. `?fix=ID` loads a code to fix a typo: any time while
  it's in the cart, and for 24 hours after it's issued.
- **Cart** (`/qr/cart.php`): the codes, with a picture of each, change or remove, the subtotal, and checkout. A guest
  is offered sign-in (the cart comes along). Checkout asks for name, email and billing address (sales tax depends on
  it), then "Pay securely with QuickBooks".
- **Manage GO Codes** (`/qr/manage.php`): waiting for payment, My GO Codes (SVG, PNG, fix), Dynamic GO Codes
  (members), and for guests the offer to keep their codes in an account.

**Landing back after payment.** QuickBooks' invoice payment page has no "return to site" setting, so:
1. Pay opens QuickBooks' page in a **new tab**, and **this tab goes straight to Manage GO Codes** for that order.
2. Manage GO Codes **polls our server every 6 seconds** while an order waits. Our server asks QuickBooks for that
   invoice at most every 5 seconds per order: **Balance 0 means paid**, and the codes are issued. The page then shows
   "Thank you! Your payment went through."
3. The **webhook** (`/api/qb-webhook.php`, Payment and Invoice events, signature checked) issues them even if every
   tab was closed.
4. The **receipt email** links back to Manage GO Codes.

Issuing twice is harmless (`markPaid` acts once).

**One endpoint for members and guests:** `/api/go-codes.php` (form token, as every page has). The members-only
`/user/api/qr-codes.php` from 0095 is retired (410).

**Unchanged from 0095:**
- **Pricing:** $4.95 once plus tax per code; colors or a logo make any code a paid one.
- **QuickBooks invoices:** Automated Sales Tax, the secure payment page, encrypted tokens.
- **Our team:** staff grants and cancels.
- **Records and deletion:** orders kept as tax records.
- **Previews:** not restricted.

## Checked on a copy of the site (QB_ENV=mock)

- **Guest:**
  - a basic link and an event both went to the cart at $4.95; the cookie is HttpOnly;
  - the cart's picture shows, its download is refused (402), and another browser can't see it (404);
  - guest checkout: $9.90 + $0.85 tax; paid, then issued.
- **Order link on a second device:**
  - opens the order, downloads its code, and fills in the checkout details;
  - a wrong link says it has expired.
- **Into an account:**
  - Pat made an account with the same email, confirmed, and signed in on a third browser: both codes and the order
    moved in, with 5 free codes still to use.
- **Member:**
  - codes 1–5 free, the 6th to the cart;
  - with the email unconfirmed, no free codes.
- **Browser at 390px, as a guest:**
  - Create → contact card in brand brown, "Add to cart · $4.95";
  - the cart count shows 1, and the cart shows the code;
  - checkout → Manage GO Codes "waiting" → paid → "Thank you! Your payment went through", the code ready, and the
    account offer with the email used;
  - no sideways scroll, cards 3px from the edges.

## Addendum (2026-10-15): the Intuit app's settings, and the Terms

Intuit asks for these before it gives production keys. They are listed on Admin → Sales → GO Code orders too.

| Intuit field | Value |
|---|---|
| Host domain | `traversence.com` |
| Launch URL | `https://traversence.com/admin/quickbooks.php` |
| Connect/Reconnect URL | `https://traversence.com/admin/quickbooks.php?connect=1` (starts connecting straight away for a signed-in admin) |
| Disconnect URL | `https://traversence.com/admin/quickbooks.php?disconnected=1` |
| Redirect URI | `https://traversence.com/admin/quickbooks.php` |
| Webhook | `https://traversence.com/api/qb-webhook.php` |
| End-user license agreement | `https://traversence.com/terms.php` |
| Privacy policy | `https://traversence.com/privacy.php` |
| Categories | Invoicing & payments; Orders & sales; eCommerce |

- **Disconnect** is where Intuit sends someone who disconnected the app inside QuickBooks. Following the link alone
  can't switch payments off: the page asks QuickBooks (`QuickBooksPay::stillConnected`), and only if QuickBooks refuses
  our tokens (400/401/403) is the connection forgotten here. A network failure changes nothing.
- A signed-out admin who opens these is sent to sign in and brought back (`login.php?next=`).
- **Terms of Use** (`/terms.php`) replace the placeholder and serve as the end-user license agreement: the license to
  use the service, accounts, posts, listings, GO Codes (yours to use for business; harmful codes switched off; Dynamic
  codes fall back when a plan ends; test before printing), buying (prices, QuickBooks, 24-hour fix, refunds), other
  companies' services, disclaimers, liability, law. A draft like the Privacy Policy: the [bracketed] items (legal name,
  address, refund policy, state and county) are to fill in, then an attorney's check.

## Addendum (2026-10-15): Intuit's security requirements, checked

Intuit's list (developer.intuit.com → go-live → security requirements): HTTPS on every page, TLS 1.1+ (1.2 with
AES-256/SHA-256 recommended), TRACE and unused methods off, session cookies Secure and HttpOnly, no-store on
sensitive pages, and its security test for CSRF, reflected and stored XSS, SQL/XML injection, session management, and
forwards and redirects. Where the site stands:

| Requirement | Status |
|---|---|
| CSRF | Every form and API call carries the session's form token (`Response::requireCsrf`); cookies SameSite=Lax. OAuth `state` is random, single-use, checked with `hash_equals`. |
| XSS | Output escaped (`tv_e`, `$e`); QuickBooks' `error` text is shortened and escaped. |
| SQL injection | PDO prepared statements; the few QuickBooks query strings are escaped (`QuickBooksPay::q`). |
| Session | HttpOnly, SameSite=Lax, Secure on HTTPS; new session id at sign-in (`session_regenerate_id`); ends when the browser closes. |
| Redirects | `?next=` takes only a path on this site; OAuth `code` is never shown, the callback redirects. |
| Tokens | QuickBooks tokens encrypted at rest (libsodium secretbox); keys only in `.env`. |
| Cache | Session pages send `Cache-Control: no-store`. |
| Errors | `display_errors` off; errors go to the log. |
| **New:** methods | `app/ui/security-headers.php` (via bootstrap and the eight pages that don't load it) refuses all but GET, HEAD, POST, OPTIONS with 405: TRACE, PUT, DELETE answered 405. |
| **New:** headers | `X-Frame-Options: SAMEORIGIN` (Peek still works), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` (pages with their own keep it), HSTS one year on HTTPS. |
| Host (Bluehost) | SSL Labs 2026-10-15: **A+**, TLS 1.3, HSTS seen. cPanel can't force HTTPS for the addon domains, so `security-headers.php` sends http://(www.)traversence.com to https (301; 308 for POST), and trvgo.us's own `.htaccess` already does. |

Removed from the server 2026-10-15: `website/_htaccess-staged.txt` and `website/error_log` (readable by anyone).

## Addendum (2026-10-15): QuickBooks connection handling (Intuit's OAuth questions)

- **Endpoints** come from Intuit's discovery document (production or sandbox), kept a day in
  `app/storage/qb-discovery-{env}.json`; the built-in addresses are only the fallback. Only `https://` addresses are taken.
- **Refresh:** the access token (1 hour) is refreshed when it has run out, on the next call; each refresh saves the new
  refresh token and its 100-day expiry. A token QuickBooks refuses early (401) is refreshed once and the call repeated.
- **Retry:** a call that gets no answer, 429 or a 5xx is tried once more after 2 seconds.
- **invalid_grant / expired refresh token:** the connection is marked as needing a reconnect: buying shows "opens
  soon", Admin → GO Code orders says "QuickBooks needs reconnecting" with a Reconnect button, and Admin → Today puts it
  first (also 14 days ahead: "Reconnect QuickBooks soon").
- **CSRF on the callback:** a `state` that doesn't match says "That sign-in to QuickBooks had expired. Connect again."
- No OAuth Playground or offline tools: tokens come only from the in-app Connect.
- Checked against a local stand-in for Intuit: valid token; token refused early (refresh, retry, ok); expired token;
  busy token server (retry, ok); refresh refused (reconnect asked, buying closed, no further calls).

## Checked live with Intuit's sandbox (2026-10-15)

- Connected Sandbox Company US aa2c (`QB_ENV=sandbox`, Development keys; redirect URI added under Development).
  Disconnect and reconnect worked.
- A contact-card GO Code bought by a member: invoice 1038 created ("Jason DeGraff · TRVGO 1", memo "Traversence GO
  Codes order #1, member #1", $4.95). The sandbox's invoice link goes to a "coming soon" 404 (Intuit's sandbox has no
  hosted payment page), so the invoice was paid with Receive payment in the sandbox company; Manage GO Codes showed it
  Paid and downloadable, and the receipt email arrived.
- No sales tax on the sandbox invoice: the sandbox company's sales tax isn't set up. The real company needs Automated
  Sales Tax on before going live.
- `QB_ENV` is now read forgivingly (first word, any case; a trailing comment ignored), and Admin → GO Code orders says
  when it isn't set or isn't recognised, naming the `.env` file the site reads (`Database::envPath()`).
- Still to do live: production keys after Intuit's review, the redirect URI under Production, `QB_ENV=production`,
  connect the real company, then one real $4.95 purchase with a card and a refund in QuickBooks.

## Addendum (2026-10-15): intuit_tid and the QuickBooks log

- Every call to Intuit keeps the response's `intuit_tid` and writes one line to `app/storage/logs/quickbooks.log`
  (never served): time, environment, method, host and path (no query string, no request body, so no tokens or
  customer details), status, `intuit_tid`, and for errors what QuickBooks said (its Fault code, message, detail and
  element, or the OAuth `error`). About 2 MB, then the older half moves to `.log.1`.
- Errors shown to admins carry the `intuit_tid`; buyers see only "Checkout couldn't start just now. Nothing was
  charged; your codes are still in your cart."
- Admin → GO Code orders: "Download the QuickBooks log" (to share with Intuit support) and the latest errors.
- Checked against the stand-in: a 401 then refresh and success; a ValidationFault 2020 (Line.Amount) reported with
  its element and `intuit_tid`; a refused refresh (invalid_grant) logged and reconnect asked.
- Webhooks are logged too (`QuickBooksPay::logWebhook`): each one's status (200 signed, 401 not), and the invoices it
  named, so "Download the QuickBooks log" shows whether Intuit's webhook reaches the site.

## Addendum (2026-10-16): Intuit's review, credentials; the admin page rewritten

Intuit's first assessment: "Any Intuit credentials including customer IDs, app client ID and client secret must be
stored securely and not be exposed within your app." The client ID and secret were already only in `.env` (outside
the web root) and never shown. Tightened:
- **The QuickBooks company id (realmId) is stored encrypted** with the same secretbox key as the tokens
  (`2026-12-01_qb_realm_sealed.sql` widens `qb_connection.realm_id`). A plain id from before is still read and is
  encrypted at the next token refresh.
- **The QuickBooks log never holds the company id:** paths show `/company/{company}/`; lines written before that are masked when shown or downloaded.
- Checked: a connection with a plain id → refreshed → stored encrypted (76 chars) → the next call works; the log has
  no id.

**Admin → GO Code orders rewritten** (`website/admin/quickbooks.php`): Connection (status, connect / reconnect /
disconnect, the Intuit addresses folded away), Health (the `QB_ENV` setting, the webhook: token missing / none yet /
last one accepted or refused, and the QuickBooks log with its latest errors and download), This month, Orders, In
carts. Every call into the libraries is checked first, so an older library file on the server shows a note instead of
breaking the page. Checked at 390px: no sideways scroll.
