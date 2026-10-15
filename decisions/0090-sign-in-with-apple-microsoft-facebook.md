# 0090. Sign in with Apple, Microsoft and Facebook; the new sign-in card

- **Status:** Accepted
- **Date:** 2026-10-15
- **Builds on:** 0085 (Continue with Google), 0079 (revoked accounts), 0058 §7 (18+), §11 (phone layout)

## Context

Jason drew a sign-in card: email, password, Sign In, Register and Forgot password, then "or" and a row of sign-in
buttons. He chose Google, Apple, Microsoft and Facebook. Amazon is left for checkout (Amazon Pay) and for sellers
(Selling Partner API); see future-considerations.md, "Connections". Signing in only says who someone is. Connecting a
platform's data is a separate, later step.

## Decision

### The sign-in card (`website/login.php`, `website/register.php`, `app/ui/social-buttons.php`)

- **Email and Password:** labeled fields, with Show on the password.
- **Sign In:** a brown (#92400E) button.
- **Register** on the left and **Forgot password?** on the right.
- **Then "or" and the tiles:** square white tiles with each company's own logo. All four allow a logo-only button. Each
  is named for screen readers and on hover ("Continue with Apple"). Only the ones that are set up show.
- **Phones:** below 768px the card runs edge to edge with 3px each side and small corners. No sideways scroll at 390px.
- **Sign-up** shows the same tiles ("Sign up with …"), and finishes sign-up for any of the four
  (`/register.php?social=1`).

### Apple, Microsoft and Facebook (`app/lib/SocialAuth.php`, `/api/auth/{apple,microsoft,facebook}.php`)

**What each gives:** its ID for the person, their email and their name. Nothing else, ever.

**How each one works:**
- **Apple:** OpenID Connect. Apple posts back its signed ID token. Its signature is checked against Apple's
  published keys, built from Apple's modulus and exponent. Also checked: issuer, audience (the Services ID), expiry,
  the one-time state and the nonce. Apple confirms every email; "Hide My Email" gives a relay address. The name comes
  once, the first time.
- **Microsoft:** the same, with Microsoft's keys (their certificates). Personal, work and school accounts all work.
  The issuer must match the account's tenant.
  - **Email, personal account:** Microsoft's own, so it counts as confirmed.
  - **Email, work or school account:** whatever its organisation typed, so it is **never** used to open an existing
    account (that would let anyone who controls a tenant take over someone's account by naming their email). A new
    account made with it gets the usual confirmation email. Microsoft's `xms_edov` (the organisation owns that email
    domain) also counts as confirmed.
- **Facebook:** OAuth. The code is exchanged server to server with our secret (`appsecret_proof` on the API call),
  then Facebook is asked for id, name and email. Facebook shares only a confirmed email. No email means no sign-in.

**Answers posted from Apple's and Microsoft's sites:** the browser doesn't send our session cookie with them
(SameSite=Lax). The endpoint first answers, before any session starts, with a small page that posts the same fields on
from our own site. So the person's session is never replaced, and the state check has its cookie. Nothing in these
posts is a link, so Bluehost's firewall lets them through.

**Who gets signed in:** the same rules as Google.
1. The account linked to that provider's ID.
2. Otherwise, the account with that **confirmed** email, which is then linked. If that account's email was never
   confirmed, its password is cleared and its sessions ended first.
3. An existing account already linked to a different account at the same provider is refused.
4. Otherwise, the person finishes signing up: 18 or older (checked on the server), verification, choices, the terms.
5. A revoked account gets the revoked message (0079).

**Where it's kept:** `user_identities` (provider, its ID, the email it gave, `user_id`, created, last used), from
`2026-11-23_social_sign_in.sql`. Google keeps `users.google_sub`.

**.env:** `APPLE_CLIENT_ID`, `MICROSOFT_CLIENT_ID` (+ `MICROSOFT_TENANT`, default `common`), `FACEBOOK_APP_ID`,
`FACEBOOK_APP_SECRET` (+ `FACEBOOK_GRAPH_VERSION`, default v21.0). Each is off until its keys are set and the database
update has run. Settings shows each one and the update.

**Pages:**
- **`/data-deletion.php`** (Facebook requires it): how to have an account and its data deleted (by email to
  privacy@traversence.com for now), and how to disconnect Traversence at each of the four.
- **The Privacy Policy** covers all four.
- **The FAQ** covers them, and says that connecting a platform's data is a separate step, coming.

## Checked on a copy of the site

21 checks, with stand-ins that sign like Apple and Microsoft and answer like Facebook:
- **Apple:**
  - a new person finishes sign-up and is signed in (confirmed email, identity kept), then signs straight in the next
    time;
  - a forged signature, another site's token and another attempt's token are each refused;
  - cancel comes back as cancelled;
  - the first post sets no cookie.
- **Microsoft:**
  - a personal account links to the existing account with that email;
  - a work account naming an existing account's email is refused and nothing is linked;
  - a new work account is created unconfirmed, and the page says a confirmation link is coming;
  - a wrong issuer is refused.
- **Facebook:** new sign-up, wrong state refused, cancel.
- **The JWK-to-PEM key matches the real key exactly.**
- **Pages:** the card at 390px and 1200px (no sideways scroll); Google's sign-up still posts to its own endpoint; Settings
  shows the new rows.

## Not done

- Self-serve account deletion (by email for now).
- Linking a second sign-in from inside the account (Settings → "Connect Apple"). Today the link happens by confirmed
  email.
- Connections to platforms' data (future-considerations.md).
