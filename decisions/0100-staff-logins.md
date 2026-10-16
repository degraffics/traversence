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

## Addendum (2026-10-16): a waiting setup link can always be found again

A login waiting for setup keeps its link on its row in **Staff logins** (*Open setup link*, *Copy link*, and when it
stops working), for anyone who manages staff logins, until it's used, replaced or expires. The link is stored sealed
(the same site-secret box as authenticator keys) beside its hash, and is forgotten when setup finishes or the login
ends. During the switch-over, a signed-in admin member can also make a **New setup link** for a waiting login.
From the server, `php app/scripts/staff-reset.php --show TRV-0001` prints the waiting link without changing anything;
without `--show` it makes a new one.

## Addendum (2026-10-16): rewritten — work email first, the app as a fallback, owner/admin reset; one menu for both accounts

Jason found recovery codes too abstract to rely on ("too abstract in requiring admins to keep something like this
accessible") and the authenticator step odd. His rule, replacing the recovery parts above:

1. **A work email is required** to create a staff login: `first.last@trvgo.com` (the domain is `STAFF_EMAIL_DOMAIN`
   in `.env`, default `trvgo.com`; one address per active login). The setup link is emailed there (and stays on the
   Staff logins row until used). Setup = choose a password + type the code we email to that address, so the mailbox
   is proven before the login works.
2. **Sign-in is MFA by email:** staff ID + password, then a 6-digit code emailed to the work email (10 minutes,
   5 tries, "Send a new code"). The **authenticator app is an optional fallback**, added by the person under Staff
   logins → Your staff login (QR or key, proven with a code); its code works at sign-in when email is slow.
3. **Recovery is a reset by an owner or admin:** owners reset anyone; an admin can reset another admin's login (not
   an owner's). A reset ends the login's sessions, clears the password and app, and emails a new setup link. If no
   owner can sign in, the server's terminal still can (`app/scripts/staff-reset.php`, now with
   `--email first.last@…` and `--owner "Name" first.last@…`).

**Recovery codes are gone** (none issued, none accepted; the table stays, unused). The email add-on switch is gone:
email is no longer optional. Owners change a login's work email on its row; your own password and authenticator
are changed under Your staff login. The member-side link now proves the staff login with a code emailed to its work
email (or its app's code). The email-link self-recovery page tried in between was dropped in favour of rule 3.

**One account menu (Jason: "there should be all accounts even though admin have separate creds"):** a staff login's
own record now acts as **Traversence staff** (owner → the admin side's Owner; admin → everything but `*`), not as a
plain member. Its menu shows the linked member account; switching moves this browser to that account without a
password (the link was proven), and the member's menu offers **Traversence staff** to switch back without signing in
for 12 hours after the staff sign-in (stopped at once if the staff login is reset or ended). Unlinked, the menu says
how to link; a member whose staff login isn't signed in here gets **Sign in**.

**Listings aren't listed one by one in the menu** (a Nexus owner may have dozens): the menu shows the listing you're
acting as and **Your listings (N)**, which opens `/user/accounts.php`: every account you can act as, listings grouped
by Nexus, with a search box past six.

## Addendum (2026-10-16): staff roles open their own areas; mailboxes made from Staff logins

**Roles.** A staff login now takes any of the admin side's roles (0055 §8), each opening its own areas of the admin
menu (0078), enforced for pages and admin APIs in `app/bootstrap.php` ("Not part of your role" otherwise), with the
menu and the dashboard showing only those areas:

| Role | Opens |
|---|---|
| Owner | everything, and managing staff |
| Manager | dashboard, Sales, Listings & places, Search, Safety, Data & crawler, Insights, People |
| Editor | dashboard, Listings & places, Search, Insights |
| Accountant | dashboard, E-Commerce, Sales, Insights |
| Executive Assistant | dashboard, Sales, Insights |
| Content Creator | dashboard, Listings & places, Search |
| General | dashboard, Sales, Listings & places, Safety |

Settings is the Owner's. Everyone reaches their own login (Staff logins → Your staff login). The earlier "Admin" role
stays for logins that have it (everything but managing staff) and isn't offered for new ones. Owners reset anyone;
Managers reset another login that isn't an owner. The map is `StaffLogin::AREAS` / `areaFor()`.

**Mailboxes.** With `CPANEL_HOST`, `CPANEL_USER` and `CPANEL_TOKEN` (a cPanel API token) in `.env`, adding someone
can make their `first.last@<STAFF_EMAIL_DOMAIN>` mailbox through cPanel's UAPI (`app/lib/CPanelMail.php`) before the
setup link is emailed to it; its password is shown once (hidden until asked) for the owner to pass on. Ending
someone's access suspends the mailbox's sign-in; its mail is kept. Without the token, mailboxes are made in cPanel by
hand. One staff login per person: a person's member account is linked to it, never duplicated as a second login.
