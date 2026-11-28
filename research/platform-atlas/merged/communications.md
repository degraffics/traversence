# Platform atlas: Communications (merged from pass 1 and pass 2)

*Draft for human review. Both passes worked only from the attached files.*

**How to read the markers**
- **[both]** means both passes support the point. Where their citations differ, each is given as "P1: … / P2: …".
- **single-pass (P1)** or **single-pass (P2)** means only one pass states it.
- **passes differ: …** marks a disagreement.

**Both passes are truncated**
- Pass 1 stops partway through (e).
- Pass 2 stops partway through (b), in the SesMail bounces and complaints bullet. It has no (c), (d) or (e).
- So everything in (c), (d) and (e), and much of the end of (b), is single-pass (P1) by necessity, not because pass 2 disagreed.

**Source coverage**
- **Notify.php** is cut off after :455 [both].
- **SesMail.php** is cut off at about :521 [both].
- **Shown in full, single-pass (P2):**
  - `Mailer.php`, `CommsSetup.php`, `WebPush.php`, `SesInbound.php`, `CommsBody.php`, `CommsCrypt.php`
  - `email/stop.php` and `email/unsubscribe.php`
- **Decision truncation, passes differ:**
  - P1 says 0172 is cut from §2.8 onward (so §3–§6 are not shown), 0137 is cut partway through the AWS setup, and 0164 is cut after R17.
  - P2 says only that 0172 is cut after §2.8.
- **Not attached, cited only:**
  - P1 lists 0047, 0052, 0102, 0108, 0141, 0167 and 0171.
  - P2 lists the same plus **0175**. That is single-pass (P2).
- **Not shown, single-pass (P2):**
  - Pulse pages and scripts: `MemberPulse`, `Pulse.php`, `js/pulse-msg.js`, `header-nav.js`, `push-sw.js`.
  - Endpoints: `/user/api/*`, `/api/ses-events.php`, `/api/ses-inbound.php`, `/api/guest-inquiry.php`, `/admin/api/comms.php`.
  - Body-owning libraries: `CommentNotices`, `Bookings`, `GroupNotices`, `ControlAlarms`, `UploadThreads`, `Mailboxes`, `PulseMarks`, `Thread.php`.
- **Not shown, single-pass (P1):** the Gmail, IMAP and Microsoft paths (`Mailboxes.php`, `/api/ms-mail.php`).
- **Paths, single-pass (P2):** code lives under `app/lib/…`. Mirror copies sit under `sync-app/lib/` and `sync-storage/email/`. P1 uses bare file names.

---

## (a) What each person can do here

**Residents and travelers are not distinguished.** Both are either a guest or a member, and both reach businesses the same way. No resident-only or traveler-only route is shown [both].

### Guest (signed out)

| Route | What it does | Status |
|---|---|---|
| `/listing/view.php?id=` → `/api/guest-inquiry.php` | Ask a listing a question. The answer comes by email or by a reply code (0172 §1.1) [both]. | Live [both] |
| `/listing/view.php?id=` (unclaimed listing) | See the confirm-step row below for the agreed part. Single-pass (P1): the message goes from `requests@` to the listing's public email (`CommsSetup.php:25`), or is held until the listing is claimed (0172 §2.1; kinds `listing.held` and `listing.left` at `Notify.php:101-102`). | Page Live. Confirm step Coming. |
| `/listing/confirm.php?t=` | Confirm step for a guest message to an unclaimed listing [both]. P2 says it confirms the guest's email. | Coming, release comms-all (pages.md) [both] |
| `/email/unsubscribe.php?t=` | One-click stop for SES mail. P1 cites `email/unsubscribe.php:24-25`. | Live [both] |
| `/email/stop.php?t=` | Stop one kind of dispatcher email [both]. Single-pass (P1): the message still reaches Pulse (`:14-16`), and account-safety kinds cannot be stopped (`:24-25`). | Live. Passes differ on the label: P1 says "#130", P2 says "release notify-1". |

- Guests have no Pulse [both].
- Single-pass (P1): a guest's device and network are kept only as keyed hashes (0172 §1.1).

### Member (signed in)

| Route | What it does |
|---|---|
| `/user/dashboard.php` | The member's Pulse, which is the inbox (0172) [both]. App Settings → Communications lives here [both]. Passes differ on the cite: P1 gives 0172 §1.2 and `Notify.php:323-354`; P2 gives `email/stop.php:23,28`. |
| `/user/notice.php`, `/user/post.php?id=` | Open one notice, or one post linked from Pulse or a notice [both]. |
| `/user/api/*` | The endpoints behind Pulse [both]. Passes differ on the list: P1 names `comms`, `bell`, `pulse`, `marks`, `pulse-msg`, `push`, `mailboxes`. P2 names `comms`, `pulse`, `business-messages`, `marketing`, `consent`. Both say `marks`, `pulse-msg`, `push` and `mailboxes` are "coming (release comms-all)" in pages.md. |
| `/push-sw.js`, `/api/ms-mail.php`, `/api/comment-act.php`, `booking-act.php`, `group-act.php` | Push service worker, Microsoft mailbox sign-in, and the buttons inside messages. Coming per pages.md. Single-pass (P2) here; P1 lists them only in (d). |
| `/user/profile.php?id=` | The member's and the listing's Pulse on the profile (0172 mockup). Build state not shown. Single-pass (P1). |
| `/community/group.php?id=` | Group posts, joins, reports and mentions arrive in Pulse labelled with the group (`Notify.php:108-111`). Single-pass (P1). |
| `/user/orders.php` | Order threads and progress emails, with a client opt-in (0101). Single-pass (P1). |
| Device push | Subscribe a browser (`WebPush.php:123-138`) and send a test push (`:217-224`). Single-pass (P1). |

**What a member can choose**
- Choices per kind and per channel (`Notify.php:323-354`) [both].
- Quiet hours [both]. Passes differ on the cite: P1 gives `Notify.php:323-354`; P2 gives `:447-453`.

**Messaging limits, single-pass (P2), from 0172 §1.2**
- Direct messages or message requests to other members: up to 10 a day.
- Messages to managed listings: up to 50 a day.
- Posting in groups the member belongs to.

**Status conflict.** P2 flags it explicitly: pages.md marks the routes above as Coming, but 0172's status line says steps 3–7 are live (#135, and #147 for the "gaps" release). P1 records the same two sources side by side in (d) without calling it a conflict. Both passes report the facts the same way.

### Business (owner and team)

| Route | What it does | Status |
|---|---|---|
| `/listing/businessportal.php`, `/business/` | The business's Pulse, shared by role [both]. Passes differ on the cite: P1 gives 0172 §1.5; P2 gives 0172 "three touch points". Single-pass (P1): how Pulse is drawn inside these routes is not shown. | Live [both] |
| `/listing/requests.php?t=` | For an unclaimed listing's email: stop requests, or report one as spam [both]. | Coming [both] |

**Kinds the team receives** [both] (`Notify.php:77-80, 100-101`)
- `biz.message`, `biz.question`, `biz.order`, `biz.comment`, `biz.booking`, `listing.held`.
- Single-pass (P2): `biz.order` is sent at Urgent level.
- Each kind goes only to team members whose role takes it [both]. P1 cites `:269-276` and `:270-276`; P2 cites `:269-276`.

**Comments, single-pass (P1).** A comment on the listing arrives as a notice with reply, hide and report inside it (0172 §2.3; `Notify.php:47`).

### Our staff

**Admin Pulse**
- Single-pass (P1): alarms, cases and mail failures arrive here (`Notify.php:83-98, 118`).
- Single-pass (P2): the route is `/admin/dashboard/stream`, with links like `#case=alarm:<id>&fix=1` (0176 release 3 note).

**System Configuration → Communications** at `/admin/config/communications` [both]
- The link is in code [both]. P1 cites `CommsSetup.php:164`; P2 cites `:164,178`.
- It has no row in pages.md [both].
- Passes differ on how sure the routing is: P1 says it is "presumably" drawn by `/admin/router.php`; P2 states it is.
- Single-pass (P1): it covers senders, mailboxes, routing, domain health, pause and resume, overseers, and the Sent view (`CommsSetup.php:2-16`).
- Single-pass (P2): its buttons call `/admin/api/comms.php` (`CommsSetup.php:177`), which pages.md lists as Coming.

**Mail-service hooks**
- `/api/ses-events.php` (bounce and complaint reports) is built per 0137 step 5 but is not in pages.md [both].
- `/api/ses-inbound.php` is marked Coming [both].

---

## (b) How it works underneath

### Dispatcher: `Notify::send` [both]

**Core behaviour**
- Runs over `Notify.php:174-253`.
- Writes one `comms_messages` row per recipient and one `comms_route` row per stage (`:312-318`).
- Passes differ on what `:228-230` shows: P1 says the `comms_messages` write; P2 says the kind, level and sender each message carries.
- Single-pass (P2): it never throws back to the caller (`:21`, `:250`). P2 also cites the file header `:2-4`.

**Levels and channels**
- Six levels (`:27`) [both].
- Quiet hours hold email at Notice, General and Special (`:29`), and hold push at Alert and below (`:30`) [both].
- Single-pass (P1): Critical and Urgent pass through quiet hours (`:208, 239`).
- Critical always reaches email and push (`:32, 332, 347`) [both].
- Push is on by default at Alert and above [both]. P1 cites `:34`; P2 cites `:34, :353`.
- There is no text channel (`:36`) [both]. P1 places this in (d).

**Register of kinds, single-pass (P2)**
- `KINDS` (`:76-119`) holds each kind's level, audience, email default, whether email can be turned off, mail engine, and team capability.
- Notices join the register as `notice.<kind>` (`:151-158`).
- The "once per person, kind and thing" rule uses `notices_sent` and respects the person's off switch (`:285-295`).

**Recipients**
- Business recipients are chosen by `Roles::CAN` [both]. P2 adds `Roles::team`.
- Suspended accounts are excluded (`:280`) [both].

**Threads**
- `IN_PLACE` kinds update one row in place (`:127, 201-221`) [both].
- A row turns unread again when the level rises [both]. Single-pass (P2): it also turns unread for a Critical message (`:203-204`).
- Other threaded kinds add to the existing unread row instead of sending another email (`:222-227`) [both].

**Bodies**
- `BODIES` maps each kind to its owning library (`:43-68`) [both].
- Single-pass (P2): `Notify::body` (`:421-427`) calls it.
- Single-pass (P1): `CommsBody` holds the shared button and reply contract (`CommsBody.php:5-8`). A disabled button shows its reason (`:26-27`), and a message is returned only to its own recipient (`:95-102`).

**Message control**
- The dispatcher checks pauses at `Notify.php:362-369` [both]. P2 says it reads `CommsSetup::paused`.
- Pausing by kind or by channel [both].
- Single-pass (P1): pausing by sender, at `:458-480`. P1 does not name the file; the context suggests CommsSetup.

**Push via the dispatcher** [both]
- One tag per thing (`:383`).
- Renotify only when the level rises (`:387`).
- Pacing: more than 3 pushes in 5 minutes fold into one summary push, with Critical exempt (`:137-138`). The second cite differs: P1 gives `:400-409`; P2 gives `:399-410`.
- Single-pass (P2): an alarm push is titled "SYSTEM ALARM: <item>" (`:393`).

**Possible defects, single-pass (P2)**
- `sitehealth.finding` sits in `BODIES` at `:64` with a `KINDS`-shaped array, and it is not in `KINDS`. `send()` would then log it as an unknown kind and drop it (`:177`), and `body()` would return null. This needs checking.
- The header comments are stale. They say push is "a later build" (`:7-8`) and that Alert holds "only push, which isn't built" (`:18`). Both conflict with `CHANNELS` (`:35-36`).

### Email: `Mailer` (Bluehost SMTP)

- SMTP is tried first, with SES as backup (`Mailer.php:112-127`) [both].
- Single-pass (P2): the SES fallback runs only when SES is live (`:174-194`). If both fail, the SMTP error is thrown.
- Staging and test accounts are never mailed [both].
  - P1 cites `:89-91`.
  - P2 splits it: staging keeps the mail without sending (`:89`), and test accounts are skipped (`:91`).
- Single-pass (P2): `.invalid` addresses are redirected to the staff member's work email or refused (`:96-100`).
- Every send is logged to `comms_route` (`:144-155`) [both]. Single-pass (P1): the address is logged hashed.
- A failed send alerts the sender mailbox's overseers [both].
  - Passes differ on the cite: P1 gives `:155-166` with no file named; P2 gives `Mailer.php:158-168`.
  - Single-pass (P1): it falls back to the Founders, at most once an hour.
- Alarm emails thread by Message-ID and In-Reply-To [both].
  - Passes differ on the cite: P1 gives `:270-291`; P2 gives `:272, :290-291`.
  - Single-pass (P1): the emails also carry a `References` header.
- Single-pass (P2): sign-in code emails put the code in the subject, with a button (`:51-75`).
- Single-pass (P2): no attach