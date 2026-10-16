# 0100. Staff logins, separate from member accounts

- Status: accepted (Jason, 2026-10-16)
- Builds on: 0097 (two-step sign-in for staff), 0078 (admin dashboard), 0090 (sign in with Apple, Microsoft, Facebook)

## Context

Admins opened the admin side with their member account (any way they signed in: password, Google, Apple, Microsoft,
Facebook) plus an emailed code. Linking Facebook to Jason's account showed the problem: admin access followed every
personal sign-in, and the emailed code went to the same personal Gmail. And people leave: someone who stops working
for Traversence keeps their member account, and their admin access must end cleanly without touching it.

## Decision (Jason)

- **A staff login is separate from any member account**: a staff ID (`TRV-0001`, numbered), a password, and a 6-digit
  code from an authenticator app on the phone (TOTP, RFC 6238; Google Authenticator, Microsoft Authenticator,
  1Password). No fingerprint or face (Jason doesn't use them), no text messages. Asked at every staff sign-in.
- **No email needed.** Email is an add-on, off by default: an owner turns it on, then a staff member may add an address
  for an emailed code instead of the app.
- **Ten one-time recovery codes** at setup, for a lost phone; an owner can reset anyone; the server's terminal is the
  last resort (`php app/scripts/staff-reset.php TRV-0001`, or `--owner "Name"`).
- **Owners manage staff** (People & roles → Staff logins): add (next staff ID and a one-time setup link, 72 hours,
  handed over in person), reset (new setup link; their sessions end), change role (owner, admin), **End access** (switched
  off at once, signed out everywhere, admin roles removed, history kept; can't end the only owner).
- **Staff link their own member account** (Dashboard → Privacy → Staff login, with a code from their authenticator), so
  staff notices can reach them there. The link never gives the member account admin access.
- A TRVGO authenticator app of our own: not now (an app-store product to build and maintain); any later one would use the
  same open standard, so nothing on the site changes.

## How it works

- Each staff login has a hidden user record (`trv-0001@staff.traversence.invalid`, never mailed, no usable password)
  holding its admin roles, so every existing admin check works unchanged. Signing in as staff signs the browser in as
  that record (`$_SESSION['tv_staff']`).
- **The switch-over:** until an owner's staff login is active, admins use their member account and the emailed code as
  before (0097). From then on `app/bootstrap.php` sends every /admin page to `/admin/login.php` (admin APIs answer 401
  `staff_login`) unless the browser is signed in with an active staff login. Old admin roles on member accounts can be
  removed from the Staff logins page.
- Secrets: the authenticator key is sealed (site secret); passwords hashed; recovery codes and setup links stored only
  as SHA-256; a code is accepted once (replay-proof) within ±30 seconds; 5 wrong tries lock the login for 15 minutes.
  Every sign-in, failure, reset, end and link is logged (`staff_log`) with the browser, never a password or code.
- Migration `2026-12-05_staff_logins.sql` (staff_accounts, staff_recovery_codes, staff_log, staff_settings).

## Not yet

- Staff notices delivered into the linked member's Pulse (the member side has no notification store yet); the link is
  in place for it.
- Finer staff roles than owner/admin (regional admins, editors) on staff logins.

## Addendum (2026-10-16): passwords and the setup key

- Every password box on the site has a Show / Hide button; new-password boxes have a live checklist (length, not
  containing the staff ID, the two the same) and can't be sent until it's ticked (`js/tv-password.js`, CLAUDE.md rule).
- The setup page hides the authenticator key until asked ("Can't scan? Show the setup key") and has **Get a new key** for
  a key someone else saw, so nobody needs a private window or a new link for that.
