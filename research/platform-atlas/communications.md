# research/platform-atlas/communications.md

```markdown
# Platform atlas: Communications

*Draft for human review. Built only from the attached files. Some files are cut off at 40,000 bytes: `Notify.php` (after :455), `SesMail.php` (after :521), decisions 0172 (§2.8 onward, including §3–§6), 0137 (part of the AWS setup) and 0164 (after R17). Anything beyond those points is marked **not shown**. Code is cited as `file:line`. Documents are cited by section, because they carry no line numbers.*

---

## (a) What each person can do here

The decisions do not give residents and travelers separate communications tools. Both are either a **guest** (signed out) or a **member** (signed in). No resident-only or traveler-only communication route is shown.

### Resident or traveler, signed out (guest)

| Page / route | What they can do | Status (pages.md) |
|---|---|---|
| `/listing/view.php?id=` → `/api/guest-inquiry.php` | Ask a managed listing a question. The answer arrives by email if chosen, or by the reply code (0172 §1.1). | Live |
| `/listing/view.php?id=` (unclaimed listing) | Leave a message. It goes to the listing's public email from `requests@` (`CommsSetup.php:25`). Otherwise it is held until a claim (0172 §2.1, kinds `listing.held` / `listing.left` at `Notify.php:101-102`). | Pages.md says the confirm step `/listing/confirm.php?t=` is "Coming (release comms-all)" |
| `/email/unsubscribe.php?t=` | One-click "Stop these" for SES mail (`email/unsubscribe.php:24-25`) | Live |
| `/email/stop.php?t=` | Stop one kind of dispatcher email. It still reaches Pulse (`email/stop.php:14-16`). Account-safety kinds cannot be stopped (`:24-25`). | Live (#130) |

Guests have **no Pulse**. Their device and network are kept only as keyed hashes (0172 §1.1).

### Member

| Page / route | What they can do |
|---|---|
| `/user/dashboard.php` (Pulse, the bell, App Settings → Communications) | Read every message, notice, feed item and connected mailbox in one inbox. Set choices per kind × channel and quiet hours (0172 §1.2; `Notify.php:323-354`). |
| `/user/notice.php`, `/user/post.php?id=` | Open one notice, or one post that a notice links to |
| `/user/profile.php?id=` | The member's and the listing's Pulse on the profile page (0172, mockup section). Its build state is **not shown**. |
| `/community/group.php?id=` | Group posts, join requests, reports and mentions arrive in Pulse labelled with the group (`Notify.php:108-111`) |
| `/user/orders.php` | Order threads and progress emails, with a client opt-in checkbox (0101, 2026-10-28) |
| Push on a device | Subscribe a browser (`WebPush.php:123-138`) and send a test push (`:217-224`) |
| `/user/api/comms`, `bell`, `pulse`, `marks`, `pulse-msg`, `push`, `mailboxes` | The endpoints behind the above. Pages.md marks the last four "coming (release comms-all)". |

### Business (owner and team)

| Page / route | What they can do |
|---|---|
| `/listing/businessportal.php`, `/business/` | The business's own Pulse, shared by role (0172 §1.5). The routes are Live. How Pulse is drawn inside them is **not shown**. |
| Kinds received | `biz.message`, `biz.question`, `biz.order`, `biz.comment`, `biz.booking`, `listing.held` (`Notify.php:77-80, 100-101`). Each goes only to team members whose role capability takes it (`Notify.php:269-276`). |
| `/listing/requests.php?t=` | For an unclaimed listing's email: stop visitors' requests, or report one as spam | Coming |
| Comments on the listing | A notice in the business's Pulse with reply, hide and report inside it (0172 §2.3; `Notify.php:47`) |

### Our staff

- **Admin Pulse.** Alarms, cases and mail failures arrive here (`Notify.php:83-98, 118`).
- **System Configuration → Communications.** Senders, mailboxes, routing, domain health, pause and resume, overseers, and the Sent view (`CommsSetup.php:2-16`). It is linked as `/admin/config/communications` (`CommsSetup.php:164`). That path is not listed in pages.md; it is presumably drawn by `/admin/router.php`.

---

## (b) How it works underneath

**One dispatcher.** `Notify::send` (`Notify.php:174-253`) does the following for each recipient:
- writes one `comms_messages` row (`:228-230`);
- writes each stage to `comms_route` (`:312-318`);
- routes by level and preference.

The parts it relies on:
- **Six levels:** `Notify.php:27`.
- **Quiet hours:** hold Notice, General and Special email (`:29`) and Alert-and-below push (`:30`). Critical and Urgent pass (`:208, 239`).
- **Critical always reaches email and push:** `:32, 332, 347`.
- **Push on by default** at Alert and above: `:34`.
- **Recipients:** suspended users are excluded (`:280`). Business recipients are chosen by `Roles::CAN` (`:270-276`).
- **Threads:**
  - IN_PLACE kinds (alarms, uploads) update one row in place. A row turns unread again only on a louder level (`:127, 201-221`).
  - Other threaded kinds add to an unread row instead of sending another email (`:222-227`).
- **Message bodies:** `Notify::BODIES` maps a kind to its owner's `pulseBody()` (`:43-68`). `CommsBody` holds the shared button and reply contract (`CommsBody.php:5-8`):
  - a disabled button shows its reason (`:26-27`);
  - a message is returned only to its own recipient (`:95-102`).

**Email (two engines).**
- **`Mailer::send`:** Bluehost SMTP first, then Amazon SES as backup (`Mailer.php:112-127`).
  - Every send is logged to `comms_route` with a hashed address (`:144-155`).
  - Staging and test accounts are never mailed (`:89-91`).
  - Alarm emails carry `Message-ID`, `In-Reply-To` and `References` thread headers (`:270-291`).
- **`SesMail::send`** (`SesMail.php:89-138`):
  - checks suppression first (`:108`) and sender pause second (`:109`);
  - bulk mail needs a postal address and stops at a monthly cap (`:110-115`);
  - adds one-click `List-Unsubscribe` headers and a "why you got this" footer (`:151-172`);
  - lets a bounce or complaint suppress the address (`:224-244`);
  - auto-pauses a sender at 3% bounces or 0.08% complaints after at least 50 sends, never Traversence itself (`:34-36, 256-267`).
- **Sender and kind routing:** `CommsSetup::SENDERS` (`:22-28`) and `MAIL_KINDS` (`:31-40`), overridable in `comms_routing` (`:350-358`).
- **Failure alerts:** a failed send alerts the mailbox's overseers, or the Founders, at most once an hour (`:155-166`).
- **Message control:** pauses by kind, channel or sender (`:458-480`). The dispatcher checks it (`Notify.php:362-369`).

**Push.** `WebPush.php`:
- own VAPID keys, created once under a lock (`:41-57`);
- only known push hosts are accepted (`:23, 111-120`);
- payloads are encrypted per RFC 8291 (`:256-279`);
- a 404 or 410 deletes the device, and 5 failures mark it failed (`:207-210, 368-372`).

The dispatcher adds:
- one notification per thing (tag at `Notify.php:383`);
- a renotify only on a louder level (`:387`);
- a pace of 3 pushes in 5 minutes, after which pushes fold into a summary, with Critical exempt (`:137-138, 400-409`).

**Inbound mail.** `SesInbound.php`:
- receives only for `in.traversence.com` (`:13`);
- sets up SNS, receipt rules and DNS through cPanel (`:28-62`);
- files spam and virus failures to Junk (`:70, 77`).

Connected mailbox secrets are sealed with AES-256-GCM. If no key is available, nothing is stored (`CommsCrypt.php:2-6, 25-31`). The Gmail, IMAP and Microsoft paths (`Mailboxes.php`, `/api/ms-mail.php`) are **not shown**.

---

## (c) Decisions

| Decision | What it rules |
|---|---|
| **0172** Communications | Pulse is the inbox. One record per message and one route. WHO tables (§1) and WHAT tables (§2). Six levels, kept separate from flags and tags. Rows show seven icons. Each message carries its own process. The site never pins or reorders. Critical and Urgent are also emailed. No clocks on anyone's messages and no new digests. Text is its own decision (§5, **not shown**). §3–§6 are truncated (**not shown**). |
| **0137** §6, §8 | Notices are sent once. Bluehost sends system mail only and SES sends everything else. `via.` and `send.` subdomains. Two AWS accounts. Early pause at 3% / 0.08%. CAN-SPAM postal footer. Core allowance of 500 emails a month plus top-ups. |
| **0176** | The dispatcher presents and routes every thread. One thread per thing, updated in place. One six-part shape. Cause → Fix it landing map. Upload targets name their thread. Words follow the state. Alarm email uses the `SYSTEM ALARM NOTICE:` subject. |
| **0101** | Client-order emails, private order links, and the client's "Email me as my order moves along" checkbox (receipts always go). |
| **0164** R4, R12 | Crawler disagreements go to the owner by system email. Owners get a "Still right?" email twice a year. |
| 0047, 0052, 0102, 0108, 0141, 0167, 0171 | Cited throughout as the base (links, guest questions, RBAC Inbox row, push and SMS, Market unsubscribe, system email in one place, alarms). **Not shown.** |

---

## (d) Live vs coming

| Item | State | Source |
|---|---|---|
| Dispatcher (`Notify`), `/email/stop.php` | Live, #130 | 0172 status; pages.md |
| 0172 steps 3–7 (push, mailboxes, comment, booking and group bodies) | Live, #135, #138, #141, #147 per 0172 | 0172 status line |
| ↳ the same endpoints (`marks`, `pulse-msg`, `push`, `mailboxes`, `/api/ses-inbound.php`, `/api/ms-mail.php`, `*-act.php`, `/admin/api/comms.php`, `/push-sw.js`, `/listing/confirm.php`, `/listing/requests.php`) | "Coming (release comms-all)" | pages.md |
| SES sending and unsubscribe | Built. Runs in mock mode until AWS production access is granted (0137 step 5; `SesMail.php:42-47, 118-123`). | 0137 |
| `/api/ses-events.php` | Built per 0137 step 5, but **not in pages.md** | 0137; pages.md |
| SES bulk account, campaigns | Coming (`CommsSetup.php:27, 38`) | code |
| Business email allowance and top-ups in `Billing` | "Not yet" | 0137 step 5 |
| `via` reply forwarding | "Not yet" | 0137 step 5 |
| 0176 release 1 (alarm thread) | Live, #226 | 0176 |
| 0176 release 2 | Part 1 live, #234. Part 2 built. | 0176 notes |
| 0176 releases 3 and 4 | Built | 0176 notes |
| Order file check, customer Replace file | To come; Jason sets the limits | 0176 Q3 |
| Text (step 8) | Needs its own decision. No channel in code (`Notify.php:36`). | 0172 status |
| Journeys and Marketplace chips in the member's Pulse | Own builds (2.10, 2.6) | 0172 status |
| Pulse's new look | The design session's | 0172 status |
| PRD Tier 2.5 "Centralized Communications Center" | The PRD still lists it as Tier 2.5. The PRD is dated 2026-09-25 and predates 0172. | prd.md |

---

## (e) The charter's promises: how they are delivered, and the gaps

**Immersion and connection.** The charter's mission is "an immersion and connection engine", and its Community ("Connect") Track is "the living, streaming layer binding dialogue and gatherings to place" (charter, Mission; Tri-Track).
- Delivered: groups act as channels into each member's Pulse, labelled by group (0172, "Pulse is the inbox"; `Notify.php:108-111`). Guest-to-business questions, member-to-business messages and comment notices connect visitors to places (0172 §2.2–2.3).
- Gap: PRD Tier 2.5's person search and connection mechanics are **not shown** in this area.

**True locality.** The charter promises understanding "without wading through sponsored listings" (Why This Matters).
- Delivered: a message to an unclaimed listing is not lost. It reaches the listing's own public email or