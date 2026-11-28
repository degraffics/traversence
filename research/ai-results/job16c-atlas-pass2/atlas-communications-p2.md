```markdown
# Platform atlas: Communications

*research/platform-atlas/communications.md. Draft for human review. Built only from the attached code and documents.*

**What the attached code covers.** Eight library files and two pages, several of them cut off:
- `Notify.php` (shown to line 455)
- `SesMail.php` (shown to about line 521)
- `Mailer.php`, `CommsSetup.php`, `WebPush.php`, `SesInbound.php`, `CommsBody.php` and `CommsCrypt.php` (shown in full)
- `email/stop.php` and `email/unsubscribe.php` (shown in full)

**What is not shown.**
- **Pulse's own pages and scripts:** `MemberPulse`, `Pulse.php`, `js/pulse-msg.js`, `header-nav.js`, `push-sw.js`.
- **The endpoints:** `/user/api/*`, `/api/ses-events.php`, `/api/ses-inbound.php`, `/api/guest-inquiry.php`, `/admin/api/comms.php`.
- **The libraries that own message bodies:** `CommentNotices`, `Bookings`, `GroupNotices`, `ControlAlarms`, `UploadThreads`, `Mailboxes`, `PulseMarks`, `Thread.php`.
- **The decisions only cited:** 0167, 0171, 0175, 0102, 0108, 0047, 0052 and 0141 are referenced but not attached. Decision 0172 itself is cut off after §2.8.

Code paths below follow each file's own header (`app/lib/…`). The mirror copies sit under `sync-app/lib/` and `sync-storage/email/`.

---

## (a) What each person can do, page by page

### Resident or traveler, signed out (a guest)

Guests have no Pulse. They are reached by email or by a reply code (0172 §1.1).

| Route | What it does | Status |
|---|---|---|
| `/listing/view.php?id=` | A business's page. Its engage bar reaches the business. | Live (pages.md) |
| `/api/guest-inquiry.php` | A guest asks a listing a question. | Live (pages.md) |
| `/listing/confirm.php?t=` | Confirms the email on a guest's message to an unclaimed listing. | Coming, release comms-all (pages.md) |
| `/email/unsubscribe.php?t=` | One-click stop for SES mail. | Live |
| `/email/stop.php?t=` | Stops one kind of email from the dispatcher. | Live, release notify-1 |

Nothing in the attached files is specific to a traveler as opposed to a resident. Both reach businesses and get messages the same way.

### Member (signed in)

| Route | What it does |
|---|---|
| `/user/dashboard.php` | The member's Pulse, the inbox (0172). The setting **App Settings → Communications** lives here (`email/stop.php:23,28`). |
| `/user/notice.php` | One notice. |
| `/user/post.php?id=` | One post, opened from Pulse or a notice. |
| `/user/api/*` | Pulse, the bell, `comms`, `pulse`, `business-messages`, `marketing`, `consent`. |
| `/user/api/marks`, `pulse-msg`, `push`, `mailboxes` | Marked "coming (release comms-all)" in pages.md. |
| `/push-sw.js` | Push service worker. Marked "coming" in pages.md. |
| `/api/ms-mail.php` | Microsoft sign-in for a mailbox read in Pulse. Marked "coming" in pages.md. |
| `/api/comment-act.php`, `booking-act.php`, `group-act.php` | Buttons inside messages. Marked "coming" in pages.md. |

**Status conflict to check:** pages.md marks the rows above as coming, but 0172's status line says steps 3–7 went live as #135 and the "gaps" release as #147. The push engine itself (`WebPush.php`) is present in the code.

**What a member can do (0172 §1.2):**
- Message other members directly, or send a message request (up to 10 a day).
- Message listings someone manages (up to 50 a day), and post in groups they belong to.
- Choose, per kind of message and per channel, whether it comes by email or push (`Notify.php:323-354`).
- Set quiet hours (`Notify.php:447-453`).

### Business owner and staff

| Route | What it does | Status |
|---|---|---|
| `/listing/businessportal.php`, `/business/` | The business's Pulse is shared by its team, by role (0172, "three touch points"). | Live |
| `/listing/requests.php?t=` | For an unclaimed listing's email: Stop requests, or Report this as spam. | Coming |

The business-facing kinds a team receives (`Notify.php:77-80, 100-101`):
- `biz.message`: a new message to the business
- `biz.question`: a guest question
- `biz.order`: a Marketplace order (Urgent level)
- `biz.comment`: a comment on the listing
- `biz.booking`: a booking changed or cancelled
- `listing.held`: messages left on the listing before it was claimed

### Our staff (admin)

- **Admin Pulse:** `/admin/dashboard/stream`, with links of the form `#case=alarm:<id>&fix=1` (0176 release 3 note).
- **Communications settings:** System Configuration → Communications, at `/admin/config/communications`. It is linked from code (`CommsSetup.php:164,178`) and drawn through `/admin/router.php`. It has no row of its own in pages.md.
- **Its buttons:** they call `/admin/api/comms.php` (`CommsSetup.php:177`). pages.md lists that endpoint as coming.
- **Mail-service hooks:** `/api/ses-events.php` handles SES bounce and complaint reports (0137 step 5). It does not appear in pages.md. `/api/ses-inbound.php` handles received mail and is marked coming.

---

## (b) How it works underneath

### The one dispatcher: `app/lib/Notify.php`

**What it is.** Every message to a person passes through `Notify::send`:
- It writes one row per recipient to `comms_messages` and one row per stage to `comms_route` (`:2-4`, `:174-253`, `:312-318`).
- Each message carries a kind, a level and a sender (`:228-230`).
- It never throws an error back to the caller (`:21`, `:250`).

**Levels and channels.**
- Six levels (`:27`).
- Email waits for quiet hours to end at Notice, General and Special (`:29`). Push also waits at Alert (`:30`).
- Critical always reaches email and push (`:32`, `:332`, `:347`).
- Push is on by default at Alert and above (`:34`, `:353`).
- The channels are Pulse, email and push. There is no text channel (`:36`).

**The register of kinds.**
- `KINDS` (`:76-119`) holds, for each kind: its name, level, audience, whether email is on by default, whether email can be turned off, which mail engine sends it, and which team capability receives it.
- Notices join the register as `notice.<kind>` (`:151-158`).

**Who receives a message.**
- A business's team is chosen through `Roles::team` and `Roles::CAN` (`:269-276`).
- Suspended accounts are left out (`:280`).

**Once only, and one thread per thing.**
- Notices keep the rule "once per person, kind and thing" (`notices_sent`) and respect the person's own off switch (`:285-295`).
- Kinds in `IN_PLACE` update one thread where it is (`:127`, `:201-221`). They become unread again only when the level rises or the message is Critical (`:203-204`).
- Other kinds with a `thread` add to the unread message already in Pulse instead of sending another email (`:222-227`).

**Message control.** A kind or a channel can be paused (`:362-369`, reading `CommsSetup::paused`).

**Push delivery (`:372-418`).**
- Each push is tagged by its thing, so an update replaces the earlier notification (`:383`).
- It alerts again only when the level rises (`:387`).
- An alarm's push title is rewritten as "SYSTEM ALARM: <item>" (`:393`).
- Pacing: more than 3 pushes in 5 minutes fold into one summary push, except Critical (`:137-138`, `:399-410`).

**Message bodies.** Each kind's body is drawn by the library that owns it, listed in `BODIES` (`:43-68`) and called by `Notify::body` (`:421-427`).

**Possible defects, as shown:**
- **`sitehealth.finding` is filed in the wrong table.** At `:64` it sits in `BODIES` with a `KINDS`-shaped array, and it is not in `KINDS` (`:76-119`).
  - `send()` would therefore log it as an unknown kind and drop it (`:177`).
  - `body()` would find no file for it and return null.
  - The rest of `Notify.php` is not shown, so this needs checking.
- **Stale header comments.**
  - The header still says push is "a later build" (`:7-8`).
  - It says Alert would hold "only push, which isn't built" (`:18`).
  - Both conflict with `CHANNELS` (`:35-36`), which includes push.

### Email

**`app/lib/Mailer.php` (Bluehost SMTP).**
- **Fallback:** Bluehost SMTP is tried first. If it fails, the same message goes through Amazon SES as a backup, but only when SES is live (`:112-127`, `:174-194`). If both fail, the SMTP error is thrown to the caller.
- **Guards before sending:**
  - Staging only keeps the mail and never sends it (`:89`).
  - Test accounts are skipped (`:91`).
  - Addresses ending in `.invalid` are redirected to the staff member's work email, or refused (`:96-100`).
- **Logging:** every send is written on the one route, `comms_route` (`:144-155`). A failure tells the sender mailbox's overseers in Pulse (`:158-168`).
- **Email threads:** one email thread per alarm, using Message-ID and In-Reply-To (`:272`, `:290-291`).
- **Sign-in code emails:** the code is in the subject, with a button that opens the sign-in page (`:51-75`).
- **Limits:** no attachments and no DKIM signing of its own (`:21-26`).

**`app/lib/SesMail.php` (Amazon SES).**
- **Mode and accounts:**
  - The mode is off, mock or live; staging is always forced to mock (`:42-47`).
  - There are two accounts: personal and bulk (`:26-28`).
- **Checks on every send (`:89-138`):**
  - The suppression list is checked first (`:108`, `:214-219`).
  - A paused sender is held (`:109`).
  - Bulk mail needs the sender's postal address, for CAN-SPAM (`:111`).
  - Bulk mail stops at the month's cost cap (`:112-114`).
- **Unsubscribing:**
  - Every message carries one-click unsubscribe headers, List-Unsubscribe and List-Unsubscribe-Post (`:151-172`).
  - The unsubscribe links are signed tokens (`:176-196`).
- **Bounces and complaints:**
  - A permanent bounce or a complaint