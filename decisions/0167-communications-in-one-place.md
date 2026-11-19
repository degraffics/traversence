# 0167: Communications in one place: set up in System Configuration, handled in Pulse

**Note (2026-11-19, decisions/0173 §3):** §4's writer is extended to every service's `.env` key (`EnvSettings::setSecret()`), with its guards inside the writer: owner-only, never on staging, never shown or logged, the old value kept until Check passes.

Status: accepted (Jason, 2026-11-16: "yes to your recommendations and the outline. Queue for future work."). **Built and live
2026-11-18** as part of Release #135 (`comms-all.zip`, verified in Sync 19:23 UTC); SES bulk sending "coming". What's
built and left: `handoffs/communications.md`.
Builds on: 0097 (two-step sign-in, the captcha), 0105 (Dashboard and Pulse), 0125 (settings changed from the admin),
0137 §8 (Amazon SES: two accounts, bounces through SNS), 0152 (outside services, one card per company), 0163 (operations).
Amends: the rule on Staff → System mailboxes that a mailbox password "goes in by hand, never through a web page" (§4).

## The problem

Found while tracing `.env` keys (2026-11-16). The site's email is spread over several pages, and none of them shows the
whole picture:
- **Staff → System mailboxes** (`/admin/staff.php#mailboxes`) only *makes* mailboxes. It doesn't list the ones that
  exist, so `donotreply@traversence.com` and `orders@trvgo.us` can't be seen there, and it's fixed to one domain
  (`STAFF_EMAIL_DOMAIN`, `@trvgo.us`).
- **Settings → The site's email** has the system sender's name, address and reply-to. The orders sender is under
  "Orders and payments". The server, port and login appear nowhere, and nothing ties a sender to its mailbox.
- **Nothing checks or tests** Bluehost mail. SES's test is one step inside a Pulse case.
- **Nothing says which email goes from which sender.**
- **Bluehost sends aren't recorded:** `Mailer.php` keeps no log, so a failed sign-in code or work order leaves no trace.
  SES sends are logged (`email_sends`).

Jason: "We need to ensure that our communication system is accessible like we did our outside services. I must be able
to see, manage, and create/edit these from a central location", and "For incoming and outgoing mail the pulse should be
where those are found and generated."

## Decision

**Communications is where the mail is set up. Pulse is where the mail is handled.**

### 1. System Configuration → Communications (`/admin/config/communications`): the setup

A new section in the manifests. Outside services' Bluehost and Amazon cards link to it.

- **Senders,** one card each: the system sender (`donotreply@traversence.com`), orders (`orders@trvgo.us`), SES personal,
  and SES bulk (shown as coming until it's built). Each card has:
  - the address, display name and reply-to, all editable there (moved from Settings and Orders and payments);
  - the mailbox it signs in with, and its server and port;
  - its status: **Check** (signs in to the mail server without sending, and gives the exact refusal: wrong password,
    port blocked, mailbox suspended), **Send me a test** (to the signed-in staff member), last sent, last failure.
- **Mailboxes,** read from cPanel (`CPanelMail`): every mailbox on **every domain** on the account (traversence.com,
  trvgo.us and any added later), each marked system, staff or inbox, with active or suspended and quota used.
  - **Make, suspend, unsuspend or reset** a mailbox from here, on any domain.
  - **Staff mailboxes are listed here**, with suspend and reset. They're still *made* on Staff logins, because a staff
    mailbox comes with a login.
- **Routing:** a table of each kind of email (sign-in codes, account notices, receipts, work orders, vendor email,
  digests, campaigns) and the sender it goes from, changeable there. Code asks for a kind, never a hard-coded sender.
- **Domain health:** SPF, DKIM and DMARC for traversence.com, trvgo.us, send.traversence.com and via.traversence.com,
  read through the cPanel API, with a fix button wherever cPanel can make the record (0137 §8's SES records included).
- **Later channels:** text messages and push shown as "coming", so this is their home when they're built.

### 2. Pulse: the mail itself

- **Incoming:** the Support inbox (`/admin/pulse/mail`, support@'s webmail through cPanel, since 2026-11-08) stays. Each
  new message also appears in the **Stream**, where it can become a case, a task or a lead. Any other mailbox that
  receives mail can be added the same way.
- **Bounces and complaints:** what SES already reports (`/api/ses-events.php`) shows in the Stream with steps (the address
  stopped; the sender paused, with a Resume button), not only in a table.
- **Outgoing, a Sent view:** everything the site sends, Bluehost and SES alike, with its sender, recipient, kind, status
  and any failure. Bluehost sends start being logged (`Mailer::send` records each one; the address kept as SES keeps it).
- **Compose:** write and send from Pulse as any sender the staff member may use (support@, orders@), with replies
  threaded back into the inbox.
- **Failures:** a send that fails (bad password, suspended mailbox, refused by the server) becomes a Pulse case with a
  button to fix it (Check again, Reset the password), linking to the sender's card in Communications.

### 2a. One inbox for each person, across the mailboxes they oversee (Jason, 2026-11-16)

Jason: "for people like me where I oversee multiple email accounts those will all be routed to my inbox and if I sent I
can select the account to send from."
- **Who oversees which mailbox** is set on each mailbox's card in Communications (one or more staff members per mailbox:
  support@, orders@, donotreply@'s bounces, a staff member's own mailbox).
- **My inbox** in Pulse shows the incoming mail of every mailbox the signed-in staff member oversees, together, each
  message labelled with the mailbox it came to, with a filter by mailbox. A staff member's own mailbox is always theirs.
- **Sending:** a **From** choice lists only the mailboxes that person oversees. A reply goes from the mailbox the message
  came to unless they pick another.
- Read and reply state is per mailbox, so two people overseeing support@ see what the other has handled.
- Only mailboxes on our cPanel account are read. Outside addresses aren't connected.

### 3. Access

Viewing Communications and Pulse mail follows `Access::can()`. Making, suspending or resetting a mailbox, changing
routing and fixing DNS are owner-level. Disallowed actions are greyed out with the reason.

### 4. Passwords are written by the site, never shown

Jason agreed (2026-11-16) to change the earlier "by hand" rule:
- When Communications makes or resets a mailbox, the password is **typed by the person or made by the site**
  (Jason, 2026-11-16: "user generated or system generated as an optional input"): the password box is optional, and left
  empty the site makes a strong one. A typed one gets the site's new-password box (`js/tv-password.js`: Show/Hide, the
  live checklist, a confirm box); cPanel's own strength rule is checked before anything changes.
- The site sets it through cPanel and **writes it straight into `.env` on the server** for that sender
  (`SMTP_PASSWORD`, `ORDERS_SMTP_PASSWORD`, …). A made one is never shown; a typed one is never shown back.
- Then it runs **Check** at once, so a wrong write shows immediately.
- The old value is kept in `.env` as a dated comment until the next Check passes, so a failed reset can be undone.
- Every change is logged (who, when, which mailbox), never the password itself.
- Staging never writes `.env` or calls cPanel's mailbox API (`StagingGate::hold()`, 0165).

## Build order (each a release Jason reviews)

**Note (Jason, 2026-11-18):** the four parts below go out together, with 0172's steps 3–7, as one release
(`comms-all.zip`; 0172 §6's note). Its Sent view reads the one route (`comms_route`, 0172 §4.3); `Mailer::send` writes
every Bluehost send there.

1. **Communications, read and check:** senders, mailboxes from cPanel, Check and Send me a test, domain health read-only.
   Bluehost sends logged.
2. **Manage:** make, suspend and reset on any domain, passwords written to `.env` (§4), routing table, DNS fix buttons.
   Settings' and Orders and payments' email rows move here (their settings kept, each where it now belongs).
3. **Pulse:** incoming mail in the Stream, bounces and complaints as items, the Sent view, failures as cases.
4. **Compose** in Pulse, with threading; each person's inbox across the mailboxes they oversee, and the From choice (§2a).

## Consequences

- One place to see and fix every sender and mailbox; one place to read and send mail.
- A failed sign-in code or work order is visible the moment it happens, instead of silently lost.
- The site can now write mailbox passwords to `.env`; that power is owner-only, logged, and never shows the secret.
- More for the cPanel token to do; if it's missing or refused, Communications says so and the mail itself keeps working
  from `.env` as today.
