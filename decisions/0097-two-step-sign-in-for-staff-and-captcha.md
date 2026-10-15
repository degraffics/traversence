# 0097. Two-step sign-in for staff, and a captcha on sign-in

- **Status:** Accepted (Jason, 2026-10-15: "MFA now and the rest to come in future considerations"; "why not captcha?")
- **Date:** 2026-10-15
- **Context from:** Intuit's app assessment for the GO Codes QuickBooks app (decisions/0096) asks whether the app uses
  multi-factor authentication and a captcha. It didn't. Letting businesses connect their own QuickBooks later
  (future-considerations) makes two-step sign-in a requirement, not an extra.

## Decision

**Two-step sign-in for staff** (`app/lib/TwoStep.php`, `/two-step.php`):
- **Who:** anyone who can use the admin side: `users.is_admin`, an `admin_access` row, or an admin role
  (`role_grants.side = 'admin'`). Members and business owners aren't asked yet (owners will be, before they can connect
  payments).
- **When:** after signing in any way (password, Google, Apple, Microsoft, Facebook), the first visit to the admin side.
  Every page under `/admin/` and every admin API (`/admin/api/`, `/api/admin/`, and `Auth::requireAdmin()`) is gated:
  pages go to `/two-step.php?next=…` and come back; APIs answer `401 two_step`. Member pages are unaffected.
- **The code:** 6 digits, emailed ("Your Traversence sign-in code: 123456"), kept only as a hash in the session; 10
  minutes, 5 tries, a new one at most every 45 seconds. The first visit sends it straight away.
- **Trust this browser for 30 days** (ticked by default): a random token in the HttpOnly cookie `tv_2s`, its SHA-256
  in `two_step_devices`. The two-step page lists the trusted browsers and has "Forget all of them". Changing or
  resetting the password forgets them all.
- **Emergency switch:** `TV_TWO_STEP=off` in `.env` (for when email delivery is down and nobody can get a code).
  Admin → Settings shows it red when off.

**Captcha** (`app/lib/Captcha.php`): **Cloudflare Turnstile** on sign-in, sign-up and "forgot password", checked by
their APIs (`/api/auth/login.php`, `register.php`, `password/request-reset.php`; `400 captcha` when it fails). Free,
usually no puzzle, no ad tracking; the site's DNS needn't be on Cloudflare. **Off until** `TURNSTILE_SITE_KEY` and
`TURNSTILE_SECRET_KEY` are in `.env`. Not on the Google/Apple/Microsoft/Facebook finish step (the provider has
checked). If Cloudflare can't be reached, sign-in isn't blocked (logged instead).

**Contact:** `support@traversence.com` is the site's contact everywhere (footer, Terms, Privacy, deletion pages, emails'
Reply-To, the GO Codes receipt, the FAQ), replacing privacy@ and hello@, which weren't live inboxes.

## Checked on a copy of the site

- An admin signs in → an admin page goes to two-step; an admin API answers 401 two_step; a wrong code says "4 tries
  left"; the right code (typed with a space) returns to the page asked for; the `tv_2s` cookie is HttpOnly.
- Signed out and in again on the trusted browser: straight in. Another browser: asked for a code. Member dashboard:
  never asked. 390px: no sideways scroll.
- Captcha off: sign-in and "forgot password" work as before. Captcha on (Cloudflare's test keys): both refuse a request
  without a token (`400 captcha`), and the widget is on the sign-in page. (Cloudflare itself can't be reached from the
  test copy, so the widget's own check wasn't run.)
- Migration `2026-11-30_two_step.sql` run twice on the test database. Settings shows the migration, the captcha and
  two-step rows. Privacy lists `tv_2s` and Cloudflare Turnstile; the FAQ has both.

## For Intuit's questionnaire

Multi-factor authentication: **Yes** (staff, who are the only people who reach the QuickBooks connection). Captcha:
**Yes** once the Turnstile keys are in `.env`.
