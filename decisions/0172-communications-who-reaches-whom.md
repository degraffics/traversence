# 0172: Communications: who reaches whom, about what, by which channel, and one way to deliver

Status: accepted (Jason, 2026-11-18, draft 16, approved with the decision pop-up). **Building (2026-11-18):** step 2 live
as #130; steps 3–7 live as one release, #135 (§6's note); the admin's Pulse Stream as one row per thing, opening in
place, with the row icons and views, live as #138, then as a table with needs as rows and the Operational Task Queue
replaced by the bells, #141 (Sync 20:38 UTC). The gaps found against this decision on 2026-11-18 (the row's ↗ and 🛡, real opened rows, ⊕'s Address Book and Keep,
the phone's ⋯, the bell opening the row, stewards folded in; staff messages and mail as admin rows, grouped alarms,
Previous / Next, A task) are live as one release, #147 (`comms-gaps.zip`, Sync 22:18 UTC). **Still open:** step 8, texting, needs its own
decision; Journeys and the Marketplace feed in the member's chips are their own builds (2.10, 2.6); Pulse's new look
is the design session's. Progress: `handoffs/communications.md`.
Drafts 2 and 3 take in Jason's changes: Pulse is the inbox, and keeps its established look (0105): it collects, organizes and
lets you read, while each message carries its own process; outside mailboxes in Pulse (§4.7); six levels, with flags and tags kept
apart from them; three touch points; a community group is a channel.
Builds on: 0047 and 0052 (links, messages, marketing preferences), 0049 (consent), 0066 (nations' land), 0102 and
0100 (roles), 0108 (sales, SMS and push), 0141 (Market 360°'s unsubscribe), 0154 (who pays), 0167 (system email in one
place), 0171 (the Control Board's alarms).
Amends (each marked **Note for Jason** where it happens): 0052 step 6 (messages held for unclaimed listings, §3.3), 0105's case
drawer (its role and contents, below) and its Operational Task Queue, 0055 §5's needs block (both to the bell), `Pulse::SEVERITY` (four levels become six), 0167 §2's
Sent view (§4.3) and 0167 §2a's "Outside addresses aren't connected" (§4.7).
Nothing else is reversed.
Hand-off: `handoffs/communications.md`. Checked against the live mirror at "Sync: 2026-11-18 02:36 UTC".

## What Jason asked for

Jason, 2026-11-18:
- "I would like to talk about the communications system. 1. Internal System Communications (pulse, alerts,
  notifications, email, push, text) 2. Between accounts communications (Comments, Messages, Push, Text, email)
  3. listings communications (Comments, Messages, Push, Text, email)".
- "Who: we need communication outlines for each WHO and then for each WHAT regarding services."
- On listings: "how can an [un]claimed listing have a message sent, this is a listing card layout and functions issue."
- On texting: "need to look at adr's to fully understand where texting services should be applied."
- On the first draft: "the pulse is the inbox. […] I would also like to see how the inbox (Pulse) can be used for
  external email services (Gmail, SES, pop/imap)." And: "Event Levels: 1. Critical 2. Urgent 3. Alert 4. Notice
  5. General 6. Special; each with their list of means. […] Are these supposed to be tags or flags?"

## What exists (checked in the live code)

| Channel | Today |
|---|---|
| **In the site** | Members: `Notices` (`notices_sent`, kinds in `Notices.php:13-24`) shown in the member's Pulse (`MemberPulse`). Staff: admin Pulse, read live from orders, claims, reports and the crawler (`Pulse::items`). |
| **The bell** | `header.php:363`. Its badge counts only person messages and requests (`header-nav.js:30-40`), not notices or business messages. |
| **Email** | `Mailer` (Bluehost SMTP, SES fallback) from about 100 call sites; `SesMail` for notices and the weekly digest, with `email_sends`, `email_suppress` and one-click unsubscribe (`email/unsubscribe.php`). Bluehost sends aren't logged (0167's gap). |
| **Web push** | None. No service worker, keys or subscriptions. The browser's own pop-up is used only in-page (guide ready, upload done). |
| **Text** | None sent by us. `sms:` links open the phone's own app: GO Codes (`Qr.php:46`), member invites (`People::invite`), a claim texted in to `CLAIM_VERIFY_NUMBER` (`Claims.php:42`), checked by hand. |
| **A central dispatcher** | None. `Notices::send` is the nearest (notice, then email by preference). Everything else calls `Mailer::send` itself. |

**Preferences that exist:** notice kinds and delivery (`notice_prefs`, `notice_delivery`: Pulse, email or digest;
App Settings → Notices); marketing per business with frequency and a master switch (`marketing_optins`,
`user_settings.no_marketing`); message types muted per business (`business_message_mutes`); who can message me
(`message_policy`); tool consents (`Consent.php`); the email suppression list. **No quiet hours anywhere.**

**Gaps found on the way** (each confirmed in the code):
1. **A business isn't told about a new message.** `BizMessages::staffUnread` (`BizMessages.php:235`) is never called;
   no notice, email or push. Only the business's Pulse count shows it.
2. **A business isn't emailed about a new marketplace order or booking.** `MarketCheckout::notify` (:644) writes an
   in-site message as if from the buyer; nothing else.
3. **Comments tell no one.** A comment on a listing or place reaches neither its owner nor anyone else; only a member's
   own post notifies its author (`MemberPosts.php:1013`). Comments are flat, so there are no replies to be told about.
4. **Escalating a Pulse case tells no one** (`Pulse::mark` adds a note only). Staff are reached by email only through
   the daily report and the scattered `tellAdmins` calls.
5. **"Claimed" and "managed" mean two things.** Search uses `entities.status !== 'unclaimed'`
   (`search_listings.php:413`); the listing page and messages use a `listing_access` row (`view.php:180`,
   `Marketing::staff`).
6. **The bell counts only person messages** (`header-nav.js:30-40`), not notices, business messages or guest questions.

## Decision

**Pulse is the inbox.** Every message, notice, feed item and alarm for a person lands in their Pulse, organized there;
email, push and text only tell them something is waiting, and a reply from any of them threads back into Pulse.
**Every message is one record, with one sender, one recipient, one kind and one level, routed by the recipient's
preferences, along one route (§4.2), watched by one message control (§4.3).** Who may reach whom is decided by the WHO tables (§1); what each event sends
is decided by the WHAT tables (§2). Cards show only what a listing can do (§3). One dispatcher delivers (§4). Outside
mailboxes can be read and answered in Pulse (§4.7). Email, push and text are delivery engines, each its own build, on one contract (§4.6).

### Pulse is the inbox (Jason, 2026-11-18)

Jason: "the pulse is the inbox. The inbox is supposed to be where all messaging can be found and organized. The feeds
and messages are centrally located."

- **Three touch points in the site** (Jason, 2026-11-18): **Members** (each person's own Pulse; creators are members
  here), **Listings** (each business's Pulse, shared by its team by role, 0102 §4's Inbox row) and **Admin** (our
  staff). One person can hold several and switches between them, as the site switches context today (0055 §5).
  Guests have no Pulse (the reply code, or email).
- **A community group is a channel, not an inbox** (Jason: "I see the community group as more of a channel than a silo
  for communications"). Its posts, events and chats reach each member's own Pulse, labelled with the group's name.
  Moderation (join requests, reports) lands as Alerts in the moderators' own Pulse, or in the business's Pulse for a
  business's own groups (0102 §4c). Today's separate steward context (`MemberPulse::sources`, `'steward'`) folds into
  the member's Pulse as the group's label and filter.
- **Everything incoming is there:** messages (members, businesses, guest questions, order and booking threads),
  notices, feed activity (comments, group posts, Moments), staff items and alarms (0171), and connected mailboxes
  (§4.7). Today the member's Pulse already gathers messages, community, discovery, listings, marketplace and guest
  questions (`MemberPulse::SOURCES`); notices, order threads and mailboxes join it.
- **Two separate things: Pulse, and the message** (Jason, 2026-11-18, after the first drafts: "Needs Me... I am not
  finding this feature helpful honestly. The purpose of the pulse is to allow me to find the information i need and
  allow me to act upon it as I see fit." Then: "outlook doesnt have a way to process what an email is supposed to do,
  it simply collects, organizes, and allows the message to be read. So as for the pulse it is the outlook and as for
  the message we can craft how our system handles process within the message. I see these are two separate things."):
  - **Pulse collects, organizes and lets you read.** It knows nothing about orders, claims or
    checks, and never reorders anything for you.
  - **The message carries the process.** What a message is for (ship an order, review a claim, fix a failed check) is
    built into the message by the part of the system that sent it.

#### Pulse: collect, organize, read, in its established look

**Pulse keeps the look Jason has set.** This decision adds nothing to it and moves nothing in it.

- **The admin's Pulse** (0105, built): the header (Working as, the count chips, the bell, **+**, Search), the
  Operational Task Queue, Quick Actions and the Pulse Stream table (From, Severity, Subject, Received); its rows now
  open in place (below).
- **The member's and the listing's Pulse, on the profile page** (Jason's mockup, 2026-11-18,
  `design/pulse-profile-mockup.png`):
  - **Header:** the Pulse mark, **Pulse ▾** (whose Pulse: your own, or a business you work for), a count chip per
    channel (**Messages, Community, Marketplace, Listings, Journeys**), the bell and settings (⚙).
  - **+** (write or post) and the search box.
  - **The list:** From (their picture), the source's link mark, Subject, Received; and on each row its icons: read or
    unread, forward or share, pin, the shield, file to a folder, **+**, and **▾** to open it.
  - **A row opens in place, under itself:** what it holds on the left (a post's photos with views and comments, opening
    full size), its words on the right (with **See more**), and under them its own answer box: here **Comment as
    (you)**, with attach, photo, video, the broadcast mark and **Publish ▾** (Publish, Save as draft, Schedule).
  - The next row's ◂ shows it's closed; one opens at a time.

What changes is what flows into it, and what an opened row or case holds:
- **Everything comes in**, each from its sender: a person, a business, a group, an order's thread, a connected mailbox,
  or the system under the name of the part that sent it ("Fulfillment", "Claims", "Site health"). Feeds, comments and
  group posts arrive the same way, so Search finds all of it.
- **One row is one thing**, never a bundle: "The engine needs a person" (26 identity queue, 1 outside service,
  37 sites, 15 finds) becomes one row each, as 0171 §8 already decided for alarms.
  **Except alarms** (Jason, 2026-11-18, the design directive): related alarms group under one row that opens to them,
  on the Board and in Pulse (the Control Board session's, 0171 §8 note). Everything else stays one row per thing.
- **The person's own marks** (flags, importance, tags, below) sit on the row and in Search.

#### The row's icons: how Pulse manages any message (proposed)

Jason, 2026-11-18, on the mockup's row icons: "These represent how the pulse handles managing actions, so based on our
build I would expect you to consider this and suggest how best to handle an email from pulse construct."

**The same seven icons on every row, whatever the kind.** They manage the message (Pulse's part); what the message is
for stays inside it (the message's part, below).

| Icon | Pulse's action | On a post or comment | On a message (member, business, guest) | On a case (admin, business team) | On an email (§4.7) |
|---|---|---|---|---|---|
| ✉ | **Read or unread** | the same | the same | the same | kept in step with the mailbox |
| ↗ | **Pass it on** | share | forward | refer to a teammate (Assign) | forward |
| 📌 | **Pin** in your own list (your choice; the site never pins) | the same | the same | the same | also flags it in the mailbox |
| 🛡 | **Protect** | report, hide, mute the poster or the group | report, block | escalate | junk, block the sender, report phishing to our team |
| 🗂 | **File** in a folder, or Archive | the same | the same | the same | moves it to the mailbox's own folder |
| ⊕ | **Add to** | Keep, a journey, Follow up | Follow up, the Address Book | Follow up, a task, a note | Follow up, make it a case or a task, the sender to the Address Book, link it to a customer or an order (0111) |
| ▾ | **Open** in place | | | | |

- **Follow up** (a date, and it comes back unread at that time) lives under ⊕, so flags need no icon of their own.
- **Forwarding a person's message** (Jason, 2026-11-18, decision pop-up, reviewed by the session manager): within the
  account or the business's team, **and by email to any address**, with a line saying who forwarded it (he chose this
  over team only and notices only). A notice from the site forwards freely. Email forwards are system emails, capped per
  person per day so they can't be used to spam. **Report, block and mute** use the existing report path (Engage's reports
  and the moderation queue); a blocked person is never told, and a mute is the viewer's own.
- Greyed with the reason where an action doesn't apply (CLAUDE.md: never silently hidden).
- On a phone the six action icons fold into one **⋯** beside ▾.

**An email in Pulse, opened:**
1. **The row:** the sender's picture or initial; the link mark shows which mailbox it came to (support@, a Gmail);
   Subject; Received; a paperclip for attachments. A conversation is one row with its count.
2. **Opened in place:** From, To, Cc, when, the mailbox; the email as sent, shown safely (scripts removed, outside
   pictures held with **Show pictures**); attachments open in a peek, and **Save to** puts one on a record (on admin
   pages files belong to the record, 0143).
3. **Its answer box,** the same shape as the post's: **Reply ▾** (Reply, Reply all, Forward), **From ▾** (only the
   mailboxes you oversee, 0167 §2a), attach, spell check, and **Send ▾** (Send, Save as draft, Schedule), as
   Publish ▾ is for a post.
4. **An outside email has no process of its own.** ⊕ **Make it a case** or **a task** turns it into one of ours,
   keeping the email inside it, so its replies stay in the same thread.

**How a row opens: three views, each with its own role in processing information.** Jason, 2026-11-18,
on the drawer, the expanded toggle and the pop-up: "DRAWER: the concept is good but Ive struggled with the drawer being
able to accommodate my needs. EXPANDED TOGGLE: this seems to be helping me more as long as it is viewing reasonable
amount of information and set up to for proper actions/notices/logs. POP UP: I like this too as long as the window is
able to work the way I need it to." Then: "each have a unique interaction. Maybe we consider how each one's role plays
into how information is processed."

Information moves through four steps: **scan → look → work → fix.** The list is for scanning; each view serves one of
the other three, and holds only what its step needs.

| | **Expanded toggle: look** | **Drawer: work** | **Pop-up: fix** |
|---|---|---|---|
| **Its role** | see what it is and decide, without leaving the list | carry one case through, over several steps or days, with the list still beside it | do one task on the whole record, then close |
| **Like** | Start the system's steps; the profile mockup | 0105's case drawer | the admin search (Ctrl K); Review's pop-ups (0074: "find, compare, fix, save, close") |
| **Opens by** | the row's ▾ | **Work on it** on the expanded row | a button that needs a tool: Open the order, Compare, Edit; or ⤢ |
| **Holds** | what happened (one line); where it stands (the stepper); up to 5 quick actions, each a button; its latest notice (closed with ×); the last 3 log entries; a short reply | the case's header (Case #, level, who has it, escalation); every step to clear it, each a button; the conversation (reply to the sender, notes to the team); the whole history; **Previous / Next** to move through the list | the record or tool itself: the order with its work orders, a claim's evidence, a listing to compare and fix, an email to write at length |
| **Never holds** | tools, long histories | tools (they open as pop-ups over it), so it never has to be wide | the list, the conversation |
| **After acting** | the answer beside the button; the row stays open | the answer beside the step; the step ticks off; the drawer stays | ✓, then it closes back to where it was opened, which shows the change; Esc or × closes without changing anything |
| **On a phone** | opens under the row | full screen, with Back to the list | full screen, with × |

**By kind of message:**

| Kind | Look | Work | Fix |
|---|---|---|---|
| A post, comment, message, notice | ✓ (reply in place) | — | photos at full size |
| An email (§4.7) | ✓ (read, short reply) | — | write at length, attachments |
| A case: an order, a claim, an alarm, a report | ✓ (quick actions) | ✓ | the order, the evidence, the tool |

- **What fixes the drawer's struggle:** it no longer holds tools, which needed the width it didn't have. Tools open as
  pop-ups over it, and it gains Previous / Next. (Amends 0105's case drawer and its split view.)
- **Kept as proposed** (Jason had no preference between keeping all three and folding the drawer in, 2026-11-18). The
  build tries it on real cases first; if the drawer still doesn't serve, its work folds into the expanded toggle.

#### The message: what it's for, and acting on it inside it

Each kind of message (`Notify::KINDS`, §4.1) has a **body made by the part of the system that owns it**, shown where
the row opens, in place, in every Pulse. The mockup's post is one kind: its
photos and words, with a comment box and Publish. The pattern is the one 0105's order case already has (its stepper and **Next step** buttons acting
through `ClientOrders::setStage`); every kind follows it:

1. **What happened**, in a sentence.
2. **Everything about it**, not a count: the order and its items; the claim and its evidence; the 17 findings
   themselves, each with its page.
3. **Its actions as buttons in the message** (Mark shipped, Approve, Re-run the check, Accept, Decline). Each acts
   through the owning library (`Pulse::manage`, `app/admin/workflows.php`) and answers beside the button
   (`TvAsk.say`). It never sends you to another page: the drawer's **Open the tool** stays for the rare case the message can't hold.
4. **Where it stands now, read live:** a message opened after its order shipped says "Shipped Nov 11 by Jason", with
   **Undo**; its buttons change to match. Acting on a message doesn't move or delete it. The person files it as they
   like.
5. **Its history and notes** underneath, and **Reply** for anything that takes a conversation.

- **Messages from people** are just their words, attachments and Reply.
- **Mail from connected mailboxes** shows as sent, with outside pictures held until asked for.
- **Feeds and comments** show the post or comment itself, with Reply (a comment) and its own small actions (Like,
  Report).

- **Nothing in Pulse is pinned, moved or held on top by the site; the bell and the item carry the signal** (Jason,
  2026-11-18: "the notification bell should be used for these items. we dont pin or control how the inbox works, so
  when an incoming item is marked a certain way the bell and the item itself holds the visual queues"):
  - **The item** shows its level on its row (Critical red, Urgent amber, Alert marked) wherever the person has filed it.
  - **The bell** counts everything unread at Alert or above (today it counts only person messages, gap 6 below), takes
    the colour of the highest level waiting, and lists those items; tapping one opens its row in place in Pulse.
  - **A notice of action on an account** (0079: a warning, or posting and messaging stopped) is no exception: a
    Critical item, on the bell until it's dealt with, and by email.
  - **Inside and outside:** a Critical or Urgent item is in Pulse (with the bell) **and** emailed (Jason confirmed,
    2026-11-18).
  - **Changes to what's built** (Jason, 2026-11-18):
    - the member's fixed "needs" block above the stream (`MemberPulse::needs`, the old Notification Center, 0055 §5)
      goes; its items arrive as rows and on the bell;
    - the admin's **Operational Task Queue** (0105) goes; the bell's list replaces it;
    - **Your day** and **Quick Actions** stay, opening collapsed; Quick Actions may become a **⋯** in the Pulse header
      and Your day an icon that opens it on screen as a pop-up, decided at the build.
- **The other channels point back.** An email, push or text says what happened and links to the item in Pulse; replying
  to the email (or the text, where texts are on) posts the reply in the same thread: each email carries a reply
  address tied to its thread, read by the site from one of our cPanel mailboxes (§4.7).

### The channels, and who pays

| Channel | Costs us | Who pays |
|---|---|---|
| **Pulse** (the inbox, the bell) | nothing | free, always on; can't be switched off for things that need an answer |
| **System email** (Bluehost, the site's own notices) | nothing | free (CLAUDE.md: system emails cost nothing) |
| **Business email** (SES: a business's campaigns, offers) | per send | the business's plan allowance or email pack (`commercial.md`) |
| **Outside mailboxes in Pulse** (§4.7) | server time; Google's yearly check if Gmail is connected by sign-in | §4.7 |
| **Web push** (our own keys, 0108) | nothing | free for a person's own notices; a business's marketing push counts against its plan's push allowance |
| **Text** | per message, plus the number | its own decision (§5); only where revenue covers it |
| **Voice** | per minute | not proposed |

### Levels, flags and tags: three different things

Jason asked whether this section is tags or flags. It's neither; there are three things, and each answers a different
question:

| | Answers | Set by | Changes delivery? |
|---|---|---|---|
| **Level** | "How should this reach you?" | the system, per kind of event (§2) | **yes:** channels, and whether it waits for quiet hours |
| **Flag** | "What do I need to do with it?" (like Outlook's Follow up, High and Low importance) | the person, on their own copy | no; a Follow up brings it back at the time chosen |
| **Tag** | "What is it about, for me?" | the person (staff: shared team tags too) | no; sorting and filtering only |

### The six levels (Jason, 2026-11-18)

| Level | Means | Examples |
|---|---|---|
| **1. Critical** | something is broken or at risk now: security, money, the system | a sign-in from a new device; password or email changed; a payment failed; a system trip (0171); a domain failed to renew; an account closed |
| **2. Urgent** | needs you within hours, or you're waiting for it | a sign-in or claim code; a new order or booking for a business; a booking changed or cancelled; a booking in 2 hours (0171's warning unanswered 24 hours was here until 2026-11-19: warnings stay on the board) |
| **3. Alert** | someone wrote to you, or something needs your action | a message or message request; a guest question; a question on an item; a claim to review; a report; a join request; a mail failure |
| **4. Notice** | information about something of yours; nothing to do | a claim verified; an order's stage, a receipt; a guide ready; a domain renewed; a booking tomorrow; low stock |
| **5. General** | activity around you | comments; group posts; Moments and Snaps activity; a tag; a group near you; your business named in a guide |
| **6. Special** | offers and announcements, only for people who chose them | a business's offers and news (0047 §3); a claim invite (0108); Traversence's own news and events |

**What each level does:**

Nothing in this table moves a message in the list; the Pulse column is how it's marked.

| Level | Pulse | Email | Push | Text | During the person's quiet hours | Can be turned off |
|---|---|---|---|---|---|---|
| Critical | red mark | always | always | only where paid and chosen | goes anyway | no |
| Urgent | amber mark | yes | yes | only where paid and chosen | goes anyway | codes no; the rest by channel |
| Alert | marked, counted on the bell | by choice | by choice | no | push waits | by channel |
| Notice | listed | by choice | by choice | no | waits | yes |
| General | in the list | by choice | by choice | no | waits | yes |
| Special | marked Special | by the person's marketing choice | by choice | only with that business's text consent | waits | always, one tap |

- **No clock is set on anyone's messages** (Jason, 2026-11-18: "This infers we mandate action, i dont think that is
  our responsibility, its and individual election"). A level says how a message reaches you, never how fast you must
  act; a person who wants a reminder sets a Follow up. (0105's clocks on staff cases are as built; this decision adds
  none, including for the new Urgent.)
- **No new digests** (Jason, 2026-11-18: "I am not finding them useful right now, so maybe we consider this in the
  future"). The weekly notice digest that exists (`Notices::digest`) stays as it is; more digests are a future idea.
- **Note for Jason (amends `Pulse::SEVERITY`):** Pulse has four today: Critical, Alert, Update, Event
  (`Pulse.php:47`). They become the six: Urgent and Special are added; Update is shown as **Notice** and Event as
  **General** (the stored keys stay, so nothing already recorded changes). 0171's trip stays Critical and its warning
  Alert.

### Flags and tags in Pulse

- **Follow up** (a flag, under the row's ⊕): Today, Tomorrow, This week, Next week, a date, or no date. At its time it comes
  back to the top of the list as unread, as a Notice to that person (by their channels), until the flag is marked complete. **Clear flag** takes it
  off.
- **Importance:** High or Low, the person's own; they can sort or filter by it, and it changes nothing for anyone else.
- **Tags:** the person's own words with a colour (Outlook's categories); a business's team and our staff can also
  share tags (e.g. "VIP", "refund"). Search by any of them.
- **Assign** and **escalate** stay for staff and business teams (`Pulse::mark`); a message assigned to someone arrives in
  their inbox. **Done** belongs to the message's own process (§"The message"), not to Pulse.
- Flags and tags are kept per person (`pulse_flags`: who, item, flag, due, importance, tags), never shown to the sender.

## 1. Each WHO

### 1.1 Guest (signed out)

| | |
|---|---|
| **Can reach** | a managed listing, by a guest question (0052 step 6); our team, by the contact form |
| **Reached by** | the listing's answer, by email if they chose it, or by the reply code on the listing page |
| **Channels** | email (only for their own question), the reply code. No push, no text. |
| **Preferences** | per question: email, call me, or neither |
| **Privacy** | device and network kept only as keyed hashes; deleted after 180 days quiet (0052) |
| **Pays** | free |

### 1.2 Member

| | |
|---|---|
| **Can reach** | members (direct, or a message request: `message_policy`, 10 a day); managed listings (50 a day); groups they're in; our team (support) |
| **Reached by** | members by the same rules; businesses they **linked** (Pulse only, 0047 §3), and by email or text only with the detail **shared** with that business; groups; us (account, security, orders) |
| **Channels** | Pulse, system email, web push (new). Text only from a business they shared their number with and opted in to texts. |
| **Preferences** | notice kinds and delivery (exist); marketing per business, frequency, master switch (exist); muted message types (exist); **new:** per kind × channel choices, quiet hours, "push on this device", all in their Communications setting (§4.4) |
| **Privacy** | businesses see counts, names only where shared (0047); contact details never copied (0141); nothing about a member on nation land made public (0066) |
| **Pays** | free; Moments, Snaps and Keeps paid from the community budget (0154) |

### 1.3 Creator

A member who publishes (guides, journeys, stories, Moments with followers).

| | |
|---|---|
| **Can reach** | everything a member can; their followers only through posts in feeds (links pull, never push, 0052) |
| **Reached by** | comments and reports on their work, guide status, payouts if any |
| **Channels** | as a member |
| **Preferences** | as a member, plus "activity on my work": each, daily, or off |
| **Pays** | free; a creator never sends paid push or text to followers |

### 1.4 Business owner

| | |
|---|---|
| **Can reach** | people who linked it (Pulse, typed messages, 0052 step 4); anyone who wrote first (30 days); guests who asked; its customers about their orders and bookings; people who shared email or phone and opted in, by that channel |
| **Reached by** | members and guests (only once managed); its customers; us (claims, billing, plan, policy, Pulse support); the system (orders, bookings, low stock, domain) |
| **Channels** | the business inbox and Pulse, system email, web push (new); text **to** them only for paid alerts they choose (§5) |
| **Preferences** | **new:** per kind × channel for the business (new message, new order, new booking, comment, review) and who on the team gets each (§1.5) |
| **Privacy** | never sees a person's email or phone unless shared (0047); a guest's device never |
| **Pays** | business email, marketing push and text from its plan or packs; its own alerts by Pulse, email and push free |

### 1.5 Business staff (0102 §3 roles)

**Who on a team gets what is set in the RBAC configuration** (0102 §4, its Inbox row: all, billing, Sales view,
Support view; Jason, 2026-11-18: "This should be addressed in the RBAC configuration"). This decision sets no defaults
of its own: a message to a business goes to the roles 0102 gives that kind of message. A business with one person
sends everything to the owner (0102: "a business with one person sees no roles"). Each person then chooses their own
channels and quiet hours.

### 1.6 A business's customer

Anyone who bought, booked, got a quote or a work order from a business (member or not).

| | |
|---|---|
| **Can reach** | that business, about that order (a reply in the order's thread) |
| **Reached by** | that business, about that order only: receipt, changes, ready, reminders. **Not marketing** unless they also linked or opted in (0047: "buying from it never opts anyone in"). |
| **Channels** | system email (exists), Pulse if a member, web push if a member who allowed it; text only as the text decision allows (§5) |
| **Preferences** | per order: email (always), push, text; STOP ends texts |
| **Pays** | the business's text allowance for texts; email and push free |

### 1.7 Our staff, by role (0100, 0102, 0109; `Access::RANK`)

| Who | Gets |
|---|---|
| **Founder** | the daily report (exists); every trip (0171 §7) by email and the hourly push; anything escalated to the top |
| **Executive, Director** | escalations in their departments (`DEPT_NAMES`: ops, fin, sal, svc, con, eng); trips in their systems |
| **Manager** | Pulse items in their areas; escalations from their staff |
| **Editor, Content Creator** | content: reports, takedowns, guide audits, comments held |
| **Accountant** | payments, refunds, failed charges, payouts |
| **Assistant, General** | the Pulse items assigned to them |
| **Vendor** | work orders (exist, from orders@) |

- **Pulse is the staff inbox.** An item assigned or escalated to someone becomes a **notice to that person** (new:
  today escalating only adds a note), delivered by their own channel choices.
- **Who oversees which mailbox** is 0167 §2a.
- Staff choose channels and quiet hours on their staff login; Critical and Urgent items ignore quiet hours.
- **Pays:** free (Pulse, system email, our own web push). Never text (0100: "no text messages"; 0171).

### 1.8 The system itself

It only sends; it never receives except the inbound hooks (SES events, payments, and later incoming texts).
- **To staff:** 0171's alarms (`control_alarms`), the daily report, Pulse.
- **To people:** codes, account notices, receipts, reminders: always system email, plus push where allowed.
- Everything goes through the one dispatcher (§4) as the sender "Traversence".

## 2. Each WHAT: the services and their events

Key: **B** built (live code), **D** decided not built, **G** gap (proposed here). Pays: **F** free, **P** the business's
plan or pack, **$** needs Jason's budget. Reply: whether the message can be answered where it lands.

### 2.1 Listings and claims

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Claim started, code | claimant | email | Urgent | — | F | B (`Claims::start`) |
| Claim by text to us | staff | text in → Pulse | Alert | — | $ (the number) | B by hand; D automatic (0070, 0133) |
| Claim code sent by text or voice | claimant | text | Urgent | — | $ | D, end state (0015, 0016) |
| Claim verified, held, declined, more asked | claimant | email + notice | Notice | in Pulse | F | B (`tellClaimant`) |
| Claim to review | claim staff | Pulse + email | Alert | — | F | B (`tellAdmins`) |
| Claim invite (sales) | business | email, GO Code; text with "Reply YES" | Special | yes | F; text $ | email B; text D (0108) |
| Calls, emails, website and directions taps on an unclaimed listing | Sales | counts in Pulse | General | — | F | G (§3.3; call, directions and website counted today) |
| A message left on an unclaimed listing | the listing's public email (if it has one), else held on the listing; the sender; Sales (count only) | email or held; Pulse | General | the business's email reply, back to the sender | F | G (§3.3) |
| The listing is claimed: its waiting messages | the new owner; each sender (delivered) | Pulse, email | Alert; Notice to the sender | yes | F | G (§3.3) |
| Listing changed by us or a source | owner | notice | Notice | — | F | G |

### 2.2 Messages

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Member → member | recipient | Pulse, bell | Alert | yes | F | B |
| Message request | recipient | Pulse, bell | Alert | accept, decline, block | F | B |
| Member → business | business staff by role (§1.5) | Pulse, **email, push** | Alert | yes | F | Pulse B; **email/push G** (gap 1) |
| Guest question | business staff | Pulse, **email, push** | Alert | yes | F | Pulse B; email/push G |
| Business answers a guest | guest | email (if chosen), reply code | Alert | yes | F | B (`bizReply`) |
| Business → linked people (typed) | members | Pulse; email or text only if shared + opted in | Special, or Alert for a reply | yes | Pulse F; email, text P | Pulse B; email, text D (0047, 0141) |
| Message reported | Safety staff | Pulse | Alert | — | F | B |

### 2.3 Comments

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Comment on a member's post | author | notice | General | yes | F | B |
| Comment on a listing | the business, as a **notice in its Pulse, sent the same way as a message** (Jason, 2026-11-18); who on the team by RBAC (§1.5) | Pulse; email or push by choice | General | **inside the notice:** reply to the comment, hide it, report it | F | G (gap 3) |
| Comment on a guide, journey or place | its creator; else nobody | notice | General | — | F | G |
| A reply to my comment | commenter | notice | General | yes | F | G (comments are flat; needs replies first) |
| Comment reported | Safety staff | Pulse | Alert | — | F | B |

### 2.4 Orders and payments

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Client order (ours) placed, stage, done | buyer; staff | email | Notice | in thread | F | B (`ClientOrders::mail`) |
| Marketplace order placed | buyer | email | Notice | — | F | B (`mailBuyer`) |
| Marketplace order placed | **business** | Pulse, **email, push** | Urgent | yes | F | Pulse B; **email/push G** (gap 2) |
| Order status for the customer by text | customer | text | Notice | STOP | P | for the text decision (§5) |
| Payment failed, refund, credit | buyer; Accountant | email + notice | Critical | — | F | B (`Orders::tell`) |
| Quote or invoice | customer; business on approval | email | Alert | yes | F | B (`BizQuotes`, `q.php`) |
| Work order | vendor | email from orders@ | Alert | yes | F | B |

### 2.5 Bookings and appointments

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Booking or rental placed | customer; business | as orders (§2.4) | Urgent | yes | F | B for the customer; G for the business |
| Reminder before it (24 h, 2 h) | customer | email, push; text if ticked | Notice (24 h), Urgent (2 h) | — | F; text P | G |
| Changed or cancelled | the other side | email, push, notice | Urgent | yes | F | G |
| Claim appointment | staff | email | Alert | — | F | B (`Claims::appointment`) |

### 2.6 The marketplace and storefronts

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| A question on an item | business | Pulse; email, push | Alert | yes | F | Pulse B (`MarketBiz::message`); G for email/push |
| Offer made, countered, accepted | both sides | email | Alert | yes | F | B (`MarketOffers`) |
| Low stock, item paused | business | notice | Notice | — | F | G |
| Storefront "Message us" | visitor → business | opens the listing page | — | — | F | B (see §3.2) |

### 2.7 Domains

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Bought, verified, renewal due, renewed, failed | owner; Developer role | email + notice | Notice; Critical when it fails | — | F | email B (`AccountDomains`); notice G |

### 2.8 Community and groups

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Group invite, group near you, about a group | member | notice | General | — | F | B (`GroupGrowth`, `Community`) |
| New post, message or comment in my group | its members, as their link settings say | Pulse | General | yes | F | G |
| Join request | group moderators | notice | Alert | accept, decline | F | G |
| A post or member reported in my group | owner, moderators | notice | Alert | remove, keep | F | G (Safety staff's case is B) |
| Mentioned, or a reply to my post or comment | that member | notice | Alert | yes | F | G |
| A question to the business in its group | its Sales role (0102 §4c) | business Pulse | Alert | yes | F | G |
| Group going quiet (no posts 30 days, no owner or moderator 90 days, 0103 §4) | owner, moderators | notice | Notice, then Urgent before it ends | act | F | G |
| Handing over, made a moderator | the people named | notice | Alert | accept | F | G |
| Post hidden | its author | notice | Alert | appeal | F | B (`tools.php:1934`) |

**Group roles and what they bring (0102 §4b, §4c)** (Jason, 2026-11-18: "does it warrant a separate inbox for the
community, maybe alerting the user on a need to act basis"):

- **No separate inbox.** The group stays a channel (§"Pulse is the inbox"). What a role adds is **work**, and work comes
  to a person by **need to act**: each duty arrives as its own message from the group (a join request, a report), carrying its
  buttons (Accept, Decline, Remove, Keep). The person finds and files them like any other message.
- **What reaches whom, by role:**

| Role | Gets as Alerts (need to act) | Gets as General (activity) |
|---|---|---|
| Member | mentions, replies to them, a post of theirs hidden | the group's posts, by their own setting |
| Moderator | the above, plus join requests and reports | the same |
| Group owner | the above, plus handing over and the group going quiet | the same |
| A business's group (4c) | its duties go to the **business's** Pulse by role: Support and Creator moderate, Sales answers questions, Owner and Manager get the rest | each team member's own setting, if they also joined |

- **Joining a group works like Link** (Jason, 2026-11-18: "I see this like I see the "link to" function where when the
  link is established any post, message or comment made will be notified to its follows, configuring how messages are
  handled falls back to the link settings"): once joined, every post, message and comment in the group reaches the
  member as a notice, and how they're handled is the member's **link settings** (0047, 0052), not a separate setting
  per group. A role's duties come as their own messages while the person holds the role.
- **Up to 5 groups each** (0102 §4b): each duty's message comes from that group by name, so a search or a search folder
  shows one group at a time.
- **Joining is consent only inside the group** (0102 §4c, the charter): a business's group posts reach the member in
  that group's channel, never by email, push or text unless the member chose that for the group.

### 2.9 Moments, Snaps and Keeps (0153)

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Comment on my Moment | author | notice | General | yes | F | B |
| My Moment is ending | author | notice | General | — | F | B (`moment_ending`) |
| Someone kept my Snap | sender | in the chat | General | — | F | B (0153) |
| Daily limit reached | author | in-page | Notice | — | F | B |
| Tagged in a Moment | the person tagged | notice, push | General | remove tag | F | G |

### 2.10 Guides and journeys

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| My guide is ready | who asked | notice + email | Notice | — | F (inside the AI cap) | B (`OnDemand::mailReady`) |
| A business named in a guide | owner | notice | General | — | F | G |
| Guide audit, takedown | content staff | email, Pulse | Alert | — | F | B |

### 2.11 Sign-in and security

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Verify email, reset, password or email changed | member | email | Critical | — | F | B |
| Staff and admin codes | staff | email | Urgent | — | F | B (`TwoStep`, `StaffLogin`) |
| Members' sign-in codes | member | email | Urgent | — | F | not built; text left to the text decision (§5) |
| New device or sign-in | member | email, push | Critical | "Not me" | F | G |
| Account deleted, standing notices | member | email | Critical | appeal | F | B |
| Vault codes | staff | email | Urgent | — | F | B |

### 2.12 Our system alerts (0171)

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Trip | Founder; the process's staff | board light, Pulse item, email at once, the hourly push | Critical | acknowledge | F | D (0171 §7, being built) |
| Warning unacknowledged 24 h | Founder | email | Urgent | acknowledge | F | D (0171) |
| Notice | — | board and reports only | Notice | — | F | D (0171) |
| Daily report | Founder | email | Notice | — | F | B |
| Mail failure, bounce | mailbox's overseers | Pulse | Alert | fix | F | D (0167 §2) |

## 3. Listing cards show only what the listing can do

### 3.1 The rule

Each action on any card shows **only when it works for that listing, for that viewer**:

| Action | Shows when |
|---|---|
| **Message** | **on the listing page only** (Jason, 2026-11-18), when the listing is **managed** (someone holds `listing_access`) and the viewer isn't its owner; **Leave a message** there when it isn't (§3.3) |
| **Ask a question** (guests) | managed, and the viewer is signed out |
| **Call** | it has a phone |
| **Directions** | it has a place on the map, and isn't held (0066) |
| **Website**, **Contact or book** | it has one |
| **Claim it** | unclaimed, and the viewer isn't staff |

**"Managed" is the one test** (gap 5): `listing_access` exists. One helper (e.g. `Listings::actions($pdo, $id,
$viewer)`) returns the list, and every card asks it. The search API returns `managed` beside `is_claimed`.

### 3.2 The cards, checked

`listing/view.php` follows the rule (`if ($managed)` at :343 and :467). The rest:

| Card | file | Today | Breaks the rule? |
|---|---|---|---|
| Directory list card | `directory/index.php:515` | Call, Directions, Website, Link | **No Message on managed listings** |
| Directory "not listed yet" | `directory/index.php:543` | Call, Directions, Website | fine (OSM rows, never managed) |
| Directory map popup | `directory/index.php:644` | Call, Peek, Open | no Message on managed |
| Classic directory card, popup | `directory/classic.php:1610`, `:1501` | Call, Claim it | uses `is_claimed`, not managed |
| Search peek | `js/search-panel.js:1082` | Call, Directions, Website, Contact, Link, Save | no Message on managed |
| Map card, lead pins | `js/map-view.js:640`, `:485` | Call, Directions, Website, Link, Save | no Message on managed |
| Guide and Get Local card | `hub/index.php:188` | Call, Directions, Website, Link | no Message on managed |
| Hub row, essentials | `hub/index.php:208`, `:313` | Call | fine (short rows) |
| Editor blocks' business card | `js/tv-blocks.js:34` | Call, Directions, Website, Link | no Message on managed |
| Tag hover card, nexus, resources, recreation | `js/tv-tagcard.js:55`, `nexus.php:65`, `guide/resources.php:74`, `place/recreation.php:97` | Call (and Directions) | fine |
| Storefront | `app/ui/storefront-view.php:157-179` | "Message us" → the listing page | **Yes, if access is removed:** the button leads to a page with no Message |
| Dashboard → Marketing | `user/dashboard.php:1217` | "Message them" always | **Yes:** shown for unmanaged listings, then refused ("No one manages this listing yet") |
| Address Book contact | `js/address-book.js:134` | Message when `can_message` | fine |
| Marketplace item | `market/item.php:102` | Message | fine (only managed businesses sell) |

**So:** two cards offer a Message that can't work (storefront edge case, Dashboard → Marketing); both are fixed by the
one helper in §3.1. Grids, maps, search and guide cards **keep no Message** (Jason, 2026-11-18: messaging stays on the
listing page); their "no Message on managed" above is as intended, and they keep Call, Directions and Website.

### 3.3 Unclaimed and claimed listings: what each can do

Jason, 2026-11-18: "A listing offers communications via its data, so email, phone, website, address which we can route
and track when it is unclaimed and information is available. As for active claimed listings it grants the ability to
be integrated into the communication system, because the user and listing will now have an id and assigned owner."

| | Unclaimed (no one manages it) | Claimed and managed |
|---|---|---|
| **What it offers** | its own data, where we have it: **Call**, **Email**, **Website**, **Directions** (the address); and **Leave a message**, held until it's claimed (below) | all of that, **plus** the communication system: Message, guest questions, its Pulse, replies, notices, orders and bookings |
| **How it's reached** | on the visitor's own device: the phone app, their mail app, the website, the map | inside Traversence, to the listing's own id and its owner and team (§1.4, §1.5) |
| **Tracked** | every tap counted (`data-out="call|email|website|directions"` with the listing id; counts only, never who) | the same counts, plus every message and its delivery (§4.3) |
| **Where the counts go** | the listing's activity, and **Sales** (0108) as a reason to invite: "37 people called or emailed you through Traversence this month." The claim invite carries it; at most one invite every 30 days. | the business's Insights |

- **Email is new on cards:** `data-out` counts call, directions, website and contact today; **email** is added, shown
  only where the listing's public email is known (from its sources or its own site).
- **A message form on an unclaimed listing, held until it's claimed** (Jason, 2026-11-18: "This could be a message
  form we offer, that would store message assigned to a listing or user, since unclaimed listing dont have these the
  temporary listing id could hold messages as long as the sender doesnt think it is actually going the a live
  listing"):
  - **Where:** on the unclaimed listing's page only, **Leave a message** in Message's place.
  - **What the sender is told, before and after**, by the route below. Held: "This business isn't on Traversence yet,
    so no one reads this here. We'll keep your message on this listing and deliver it only if the business claims it.
    For anything now, call them directly." The button reads **Leave it for them**, never Send. Emailed: "This business
    isn't on Traversence yet. We'll send your message to the email this listing gives, and bring any reply back here."
    The button reads **Send by email**.
  - **Who, and what's required of the sender** (Jason, 2026-11-18: "message to unclaimed listing should require
    1. email 2. phone 3. user account. if none exist it shouldnt be accepted"): a message is accepted only with a way
    to answer its sender: **a member account** (the message is in their Pulse, marked **Waiting: not delivered** or
    **Sent by email**), **a confirmed email**, or **a phone** (for "call me"; confirmed by a text code once the text engine is built,
    §5; until then given to the business only with the guest's say-so). With none of them, the form doesn't send. The reply-code-only choice of 0052 isn't
    offered here.
  - **A guest leaving a message** (built on 0052 step 6's guest questions):
    - **The form:** their first name, the message, and how they want the answer: **by email** (we send it; the
      business never sees their address) or **by phone** (the business calls them), at least one required. The self-hosted check and limits stay (3 a day
      per device, 6 per network).
    - **Their email is confirmed first:** a link to it, and nothing goes to the business until it's pressed, so no one
      can send in someone else's name. (Jason agreed, 2026-11-18; new here, 0052's guest questions didn't confirm.)
    - **A reply code** (8 characters) is shown and emailed. **Check a reply** on the listing page shows where it
      stands: held, sent by email, delivered, answered.
    - **Answers reach them** by email (if they chose it) and under the code. A business's email reply (the email
      route) or its answer in Pulse (after a claim) is stored on the guest's thread.
    - **When either side joins later:**
      - **The guest signs up:** the reply code moves the thread into their account (0052), and it becomes an ordinary
        conversation in their Pulse, with everything said so far.
      - **The business claims the listing:** every thread on it comes into the business's Pulse with its history:
        held messages are delivered then (above), and requests already sent by email arrive with the business's email
        replies, so it can carry on in Traversence. The guest's side doesn't change: answers still reach them by
        email or the code.
    - **How it's held when guest data is purged quickly** (Jason asked, 2026-11-18). Checked in the live code: the
      form's check lives only in the session; the device and network hashes behind guests' limits and likes are
      deleted after 30 days (`guest_tick`, 0009); guest questions themselves are kept until 180 days quiet
      (`GuestInquiries::KEEP_DAYS`). So a guest's message is held **as its own record**, never in the session or
      the cookie:
      - it's a guest thread (`guest_inquiries`, the table guest questions use) on the listing's id, with a status
        (held, sent by email, delivered, answered, expired) and its own clock;
      - it holds only what the thread needs: the first name, the words, the confirmed email if they chose email, the
        reply code (stored hashed, as now), the listing, and the dates;
      - the 30-day purge of device hashes doesn't touch it (those only ever counted the limits);
      - **its own clock:** held and never claimed, deleted at 90 days, the guest emailed first if they gave an email;
        once delivered, the usual 180 days quiet;
      - with no email and a lost code, the thread can't be found again by the guest; the form says so and shows the
        code to save ("Keep this code: it's the only way back to your message").
      - The email is deleted with the thread.
  - **Two routes, by what the listing's data holds** (Jason, 2026-11-18: "unless we assign the email from the listing
    to the message"):
    - **The listing has a public email:** the message is **delivered to that email by us as a visitor's request**
      (Jason, 2026-11-18: "route that message to that email as a request from a visitor"), from the system sender:
      - subject: "A visitor asked about <business name> on Traversence";
      - the visitor's first name and their words (never their email or phone), and what it's typed as (General
        inquiry, 0052 step 4);
      - **Reply to this email to answer them**, and **Claim your listing** (free) to see and answer every request in
        Traversence;
      - an unsubscribe ("Stop requests to this address"). The whole route is tracked (§4.2). The business's email reply comes back into the
      sender's Pulse through the reply address (§"Pulse is the inbox"). The sender is told "Sent to the email this
      listing gives; they may not have joined Traversence." The pace and the protections are below.
    - **No email on the listing:** the message is **held on the listing's id** (it belongs to the listing record, not
      to a person) until someone claims it. Its route (§4.2) stops at **Held: waiting for a claim**.
  - **The pace of emails to an unclaimed listing** (Jason, 2026-11-18: "first email. then queued if the que gets to
    5 messages one email sent saying 5 messages waiting"):
    - the **first** request goes at once, as above;
    - later ones **wait in a queue** on the listing; when **5** are waiting, **one** email says "5 visitors have left
      you messages on Traversence": a **notice of messages waiting**, not the messages. Reading and answering them
      **needs an account** (Jason, 2026-11-18): the email's **Claim your listing** (or sign in, if the address already
      has an account) opens them. Then the count starts again;
    - **a queue that doesn't reach 5:** after **10 days** from the oldest waiting message, one email says how many are
      waiting, in the same form (Jason, 2026-11-18: "after 10 days as one message notification to listing email");
      then the count starts again. Each waiting message still expires at 90 days.
  - **Protecting our name and the business** (Jason: "this could also be spam or abuse so we need a way to ensure our
    reputation and client is protected"):
    - **Its own sender:** these emails go from their own address (e.g. `requests@traversence.com`, a Bluehost mailbox,
      free), never the system sender, so a complaint can't touch sign-in codes or receipts (0167 §1's routing).
    - **Screened before sending:** no attachments; links removed; a length cap; the site's word checks; a
      message that looks like spam or abuse is held for Safety in Pulse instead of sent.
    - **Sender limits:** one waiting message per sender per listing until it's answered; the guest limits (3 a day
      per device, 6 per network); a member's 10 new conversations a day (as message requests, 0052).
    - **The business decides:** every email has **Stop requests to this address** (then messages are held for a
      claim) and **Report this as spam**, which follows the abuse process already decided: Reports to
      Safety in Pulse (0052, 0137) and the sender's standing notices (0079), never a separate rule here.
    - **Bounces and complaints** stop emails to that address at once (`email_suppress`) and the listing falls back to
      holding.
    - **Watched by message control (§4.3):** sends, bounces and complaints for this kind are counted; complaints
      over **0.1%** of the last 1,000, or any bounce surge, raise an alarm (0171) and **pause the kind by itself**
      until an owner resumes it; held messages wait meanwhile.
  - **When it's claimed and verified:** each waiting message is delivered to the new owner's Pulse as an Alert, with
    when it was left, and the sender is told it was delivered (a Notice; email for a guest). Replies then work as any
    business conversation.
  - **If it's never claimed:** it expires after **90 days**, and the sender is told it wasn't delivered.
    The sender can withdraw it at any time.
  - **Unsubscribed or bounced:** a listing whose email bounced or unsubscribed falls back to held.
  - **Private until delivered:** no one at Traversence reads the words; Sales sees only the count, as a reason to
    invite ("4 people have left you messages on Traversence"), never the messages or who left them. Expired and
    withdrawn messages are deleted.
  - **Reportable:** the claimed owner can report one as spam or abuse, as any message (0052).
  - **The listing's own data still works:** Call, Email (opening the visitor's own mail app, only the tap seen),
    Website and Directions, each counted (above).
  - **Note for Jason (extends 0052 step 6):** guest questions were for managed listings only; unclaimed listings now
    hold messages, as above, from members and guests.

## 4. One way to deliver

Builds on 0167 (system email in one place) and 0171 (alarms). Neither is replaced.

### 4.1 The record

`Notify::send($pdo, $kind, $to, $about, $words)` is the one entry point. It writes one row in **`comms_messages`**:
who sent it (a person, a business, or the system), who it's for (a person, a business role, a staff role, a guest's
address), the kind (from §2's tables, each with its level), what it's about, and the words.

- **Kinds are a register in code** (`Notify::KINDS`: key, name, what it is, level, default channels, who may turn it
  off), so the preferences page and the admin read the same list (CLAUDE.md "Words and lists").
- `Notices::send` becomes a caller of it, keeping `notices_sent`'s "once per person, kind and thing".
- Mail that's not a notice (receipts, work orders, codes) moves to `Notify` call site by call site, starting with the
  gaps in §2. `Mailer::send` stays as the email driver underneath.

Jason, 2026-11-18: "routing is how a message is creation through delivery of a message and to its ultimate end point
(deleted/archived/etc.). The creation, tracking, monitoring, verification, reporting, and control of a message is a
separate function." So there are two parts: **the route** every message travels (§4.2), and **message control**, the
function that watches over every route (§4.3).

### 4.2 The route: from creation to its end point

Every message, on every channel, travels the same stages. Each stage is written to the message's record with when and
how, so a message can always be found where it is now.

| Stage | What happens | Can end here as |
|---|---|---|
| **1. Created** | `Notify::send` (or a person writing) makes the one record: sender, recipient, kind, level, words | refused (blocked, `message_policy`, no consent, suppressed, STOP), with the reason |
| **2. Addressed** | the channels are chosen: the kind's defaults, then the person's choices (kind × channel), then what's available (push only with a subscription) | — |
| **3. Held** | waiting for a claim (a message left on an unclaimed listing, §3.3), or for the person's quiet hours if they set them, by level (Critical and Urgent never wait; Alert holds only push; Notice, General and Special wait) | withdrawn by the sender before it goes; expired (an unclaimed listing's after 90 days) |
| **4. Sent** | handed to each channel: Pulse, email (Bluehost or SES), push, a connected mailbox | failed (retried by the channel's rule; a dead address or subscription is marked and not tried again) |
| **5. Delivered** | the channel confirms: it's in Pulse; the mail server accepted it; the push service took it | bounced, complained, unsubscribed (SES events, 0137 §8) |
| **6. Read** | opened in Pulse, or the email opened where that's known | — |
| **7. Answered or acted on** | a reply in the thread, or a button in the message used (§"The message") | — |
| **8. Its end point** | where the recipient puts it: **filed** in a folder, **archived**, **deleted** (to Deleted, emptied after 30 days), or **expired** by its kind's keeping rule (a guest question after 180 days quiet, 0052) | the end |

- **Pulse always:** every message reaches stage 4 in the recipient's Pulse, whatever else is sent.
- **The same route for a message that comes in** (a connected mailbox, an incoming email reply): it starts at
  **Received** instead of Created, and goes on from stage 5.

### 4.3 Message control: creating, tracking, monitoring, verifying, reporting and controlling

A separate function that watches over every route, set up in **System Configuration → Communications** (0167 §1) and
shown as one system on the Control Board (0171 §1, with a process per channel: Pulse, Bluehost email, SES, push,
connected mailboxes).

| Function | What it does |
|---|---|
| **Creating** | the kinds register (`Notify::KINDS`, §4.1): what each kind says, its level, its default channels, its keeping rule; changed in one place |
| **Tracking** | **`comms_route`**: one row per stage per channel (§4.2), with when, how and the provider's id; any message's whole route shown on one line |
| **Monitoring** | live, as 0171 §5: a channel failing, slow or refused raises an alarm (a mailbox refusing its password, SES bounces climbing, push keys refused, a digest not sent) |
| **Verifying** | the channel's own proof: SES's delivery and bounce events, the mail server's acceptance, the push service's answer, the connected mailbox's sync; a message with no proof in time is marked "not confirmed" |
| **Reporting** | per kind, channel, sender and business: sent, delivered, read, answered, failed, unsubscribed; in 0171's reports, and each business's share in its Insights |
| **Controlling** | pause or resume a kind, a channel or a sender; retry a failed delivery; withdraw a held message; quiet hours and limits; all owner-level and logged (0171 §13) |

- **Note for Jason (refines 0167 §2):** 0167's **Sent view** in Pulse reads `comms_route`, and `Mailer::send`'s
  record of each Bluehost send lands there too (with `email_sends` kept for SES), so there is one record, not two.
- For staff, a channel's failures reach them as 0171's alarms; a single failed message to a person is shown on its
  route, and on the sender's own copy.

### 4.4 The person's Communications setting: channels, quiet hours, opt-outs

- **Quiet hours** are a feature each person sets, **with no default** (Jason, 2026-11-18: "this is a feature the user
  can configure, not a default"): off until they turn it on, in their own time zone (the device's, stored with their
  actions, per CLAUDE.md "Times"). Staff on their login.
- **Digests:** none new (above).
- **One Communications setting** (Jason, 2026-11-18) is where each person configures all of it: per kind and channel,
  quiet hours, push on this device. It takes in today's Notices settings (App Settings → Notices).
- **Opt-outs:** in that setting; a business's marketing stays where 0047 put it (Privacy → Marketing); every email, push and text links to
  the right one (0141 §5). Things that can't be turned off (codes, receipts, security) say why, greyed with the reason.

### 4.5 Staff and the system

- **0171's alarms** stay their own table (`control_alarms`) and their own three ways. When an alarm must reach a person
  (the Founder's email, a role's Pulse item), it calls `Notify` with the kind `alarm.trip` or `alarm.warning`, so it
  appears in the same log and respects the person's channels, with Critical and Urgent ignoring quiet hours.
  **Since 2026-11-19 only `alarm.trip` reaches a person** (0171 §7's note, Jason: "everything else should be cleared by
  the system so why send and pulse?"); `alarm.warning` stays defined but is no longer sent. A trip's message carries its
  alarm's Pulse item (`pulse_ref`), so Pulse shows the alarm once, not again as a stream row.
- **Pulse items** assigned or escalated call `Notify` (gap 4).

### 4.6 The delivery engines: email, push and text are separate builds

Jason, 2026-11-18: "Text, Email, Push engines... i think this part of the communications system are separte builds."
This decision sets the communications system (who, what, Pulse, the route, message control); each **engine** that
carries a message off the site is its own build, plugged into stage 4 of the route (§4.2).

**What every engine does** (the one contract, so the route and message control treat them alike):

| | Each engine |
|---|---|
| **Takes** | a message from the route at stage 4, already addressed and cleared to go (consent, opt-outs, quiet hours) |
| **Sends** | by its own provider, under its own sender (0167's routing for email) |
| **Answers back** | sent, delivered, failed, bounced or refused, with the provider's id, into `comms_route` (stages 4–5) |
| **Takes in** | its own opt-outs and replies (an unsubscribe, a STOP, a dead push subscription, an email reply), handed to the route and the person's Communications setting |
| **Is watched** | as a process on the Control Board (0171: its readings, alarms, pause and resume) and by message control (§4.3) |
| **Is set up** | on its card under Outside services (0152) and in Communications (0167), keys in `.env`, checked by the pre-flight |
| **On staging** | never sends (`StagingGate::hold()`, 0165) |
| **Costs** | counted per send, failed ones too; only where a plan, pack or Jason's budget pays (No income, no expense) |

**The three engines and where each stands:**

| Engine | Its build | Stands |
|---|---|---|
| **Email** | **0167** (accepted, queued): senders, mailboxes, Check and test, routing by kind, the Sent view; Bluehost and SES | decided; this decision adds only the contract above (its Sent view reads `comms_route`, §4.3) |
| **Push** | **its own build**, from what's decided here: our own keys (0108: "needs no outside account"), a service worker and a subscription per device; set in the person's Communications setting and asked for when an action turns it on (§4.4); each push links to its message in Pulse and to "Stop these" | ready to build; free to us |
| **Text** | **its own decision, then its build.** The research from the decisions and the open questions (who pays for our own texts; customers' order texts; no sign-in codes by text; the provider, the number and who registers; STOP, HELP, quiet hours and consent records; plans and packs) are kept in `handoffs/communications.md` | to be decided; until then no texts are sent, and what's decided stands (0108, 0047, 0100, 0171) |

### 4.7 Outside mailboxes in Pulse: Gmail, Microsoft, IMAP, POP and SES (Jason, 2026-11-18)

Jason: "I would also like to see how the inbox (Pulse) can be used for external email services (Gmail, SES, pop/imap)."

**What it means:** a person connects a mailbox they already have, and its mail appears in their Pulse beside everything
else, labelled with the mailbox. They read, flag, tag and answer it there; a reply goes out **from that mailbox**, so it
reaches the other person from their real address and lands in that mailbox's own Sent folder. The mailbox stays the
original: Pulse is a window onto it, not a copy that takes over.

**Today:** Pulse → Support inbox opens support@'s webmail in a new tab, signed in through cPanel
(`sections.php:1411`); 0167 §2a plans "My inbox" across our cPanel mailboxes and says "Outside addresses aren't
connected."

| Service | How it connects | Reads | Sends as | Cost to us | Notes |
|---|---|---|---|---|---|
| **Our own mailboxes** (cPanel: support@, orders@, staff) | IMAP, signed in by the site (0167 §4 writes the passwords) | folders, read state both ways | that mailbox, through its SMTP | none | replaces opening webmail in a new tab; 0167 §2a's "My inbox" is built on this |
| **Any IMAP mailbox** (a business's own host, Yahoo, iCloud, Zoho, Bluehost) | server, port 993, login with an **app password** | folders, read state both ways | that mailbox's SMTP (port 587) | server time only | the general case; covers most providers |
| **POP3** | server, port 995 | the inbox only, no folders, no read state back | that provider's SMTP | server time | only where IMAP isn't offered; leaves a copy on the server |
| **Gmail and Google Workspace** | **first:** IMAP with an app password (needs Google's 2-Step Verification on); **later:** "Sign in with Google" | as IMAP | Gmail's SMTP; later the Gmail API | none by IMAP; by sign-in, Google requires a yearly outside security assessment to read mail, which costs money | sign-in only once revenue covers the assessment (No income, no expense); a Workspace admin can block app passwords |
| **Microsoft 365 and Outlook.com** | "Sign in with Microsoft" (Microsoft Graph); Microsoft no longer accepts passwords for IMAP | folders, read state both ways | the mailbox, through Graph | none (publisher verification is free) | the one service that needs sign-in from the start |
| **Amazon SES** | not a mailbox: SES **sends** (0137 §8) and can **receive** for a domain whose mail is pointed at it | SES stores each message and tells our webhook; it lands in Pulse | the business's own domain, through our SES | per 1,000 messages and their storage: counted against the business's email allowance | gives a business an address on its own domain (hello@theirdomain) with no mail host; the webhook joins the Bluehost exemption list (`research/bluehost-bot-check-2026-11-15.md`) |

**Who can connect:**

| Who | May connect | Pays |
|---|---|---|
| Our staff | our cPanel mailboxes they oversee (0167 §2a), and an outside mailbox **once their manager approves it** (Jason, 2026-11-18); this changes 0167 §2a's "Outside addresses aren't connected" | free |
| A business | its own mailboxes, shared with the team by role (0102 §4): **how it's offered is decided in the App store build** (0169; Jason, 2026-11-18), queued there by the session manager | on paid plans; SES receiving counts against the email allowance |
| A member | their personal mailbox, as an **add-on from the App store** (0169; Jason, 2026-11-18); its set-up and standards are decided with the app's build | the add-on's price |
| Guests | no | — |

**Rules:**
- **Passwords and sign-ins** are stored encrypted, never shown back, and removed by **Disconnect**. Sign-in (Google,
  Microsoft) is preferred wherever it's free, because no password is kept.
- **How fresh:** the site checks each connected mailbox every 5 minutes (a `Scheduler` job; Bluehost can't hold a
  connection open), and at once when the person opens Pulse. SES mail arrives the moment the webhook is called.
- **We keep a working copy** of the last 90 days, for search and threading; the mailbox stays the original. Disconnecting
  deletes our copy.
- **Private:** a person's mailbox is theirs; a business's is seen only by the roles allowed; staff mailboxes only by
  their overseers (0167 §2a). Nothing from a mailbox goes to AI unless the person asks (0164), never to Gemini's free
  tier. No support role reads it (0102 §5).
- **One way to fix a failure:** a refused password, an expired sign-in or a full mailbox becomes an Alert in that
  person's Pulse with **Reconnect** on it (never a dead end).
- **Staging** never connects to a mailbox (`StagingGate::hold()`, 0165).
- **Build check:** Bluehost's PHP may not have the IMAP extension (it left PHP's core in 8.4); the reader is written
  over a plain socket, as `Mailer` already does for SMTP.

## 5. Texting

**Its own decision and build** (§4.6). Where this decision's tables say "text", it's a place a text could go once that
decision allows it: a guest's phone confirmed by a text code (§3.3), Critical and Urgent items where a person chose
texts and a plan pays, a business's texts to people who opted in, and customers' order texts. The research and the
open questions are in `handoffs/communications.md`.

## 6. The build order

Each a release Jason reviews; free channels first.

**Note (Jason, 2026-11-18): "can you have the entire build pushed for one release."** Steps 3–7 and 0167's four parts go
as **one release** (`comms-all.zip`), not one release per step. Step 8 (texting) stays its own decision first, then its
build. Built: see `handoffs/communications.md`, "Steps 3–7 and 0167".

1. **0171's alarms** (being built by the Control Board session), then **0167**'s four releases: the email engine.
   Unchanged.
2. **One dispatcher, into Pulse:** `Notify`, `comms_messages`, `comms_route`, the kinds register, the preferences
   setting (kind × channel, quiet hours), the bell counting everything. Closes gaps 1–4 and 6 in Pulse and by system
   email: a business told of new messages, orders and comments; Pulse escalations told.
3. **Flags and tags in Pulse; the six levels** (`Pulse::SEVERITY`, the bell), and **our own mailboxes read in Pulse**
   by IMAP (with 0167's step 4).
4. **Cards (§3):** the one actions helper, Message and Leave a message on the listing page only, Call, Email, Website and Directions
   counted on unclaimed ones with the counts in Sales, Leave a message held until a claim, the two broken cards fixed.
5. **The push engine** (its own build, §4.6; free).
6. **Outside mailboxes** (§4.7): IMAP and POP, Microsoft, then SES receiving; Gmail by sign-in only when revenue
   covers its assessment.
7. **Comments, bookings, groups:** replies on comments, booking reminders and changes, group posts and join requests.
8. **The text engine:** its own decision first (§5), then its build, only where revenue covers it.

## What this doesn't do

- No voice calls placed by us.
- No app push (comes with an app, 0108).
- Nothing about a person's contact details changes: shared only as 0047 says.
- No approvals in Pulse (0171 §8): Jason's OKs stay in each session's own chat.

## Consequences

- One place answers "did they get it?" for every message, on every channel.
- A business finally hears about its messages, orders and comments.
- Cards stop offering what can't work, and offer Message where it can.
- Texts stay off until revenue pays for them, and their rules (consent, STOP, quiet hours) are set before the first one.

**Note (2026-11-19, Jason: "default is by when received, on sort it should priority by most important or least"): the
stream's order.** The admin's Pulse stream is newest received first (resolved at the bottom; a person's own pins on top),
as this decision's "Nothing in Pulse is pinned, moved or held on top by the site" has it: the site no longer lifts
escalated or higher-level items itself; the bell, the row's tint and its clock carry the signal. Tapping ! is the
person's own sort: the most important first (overdue rows, then by level), again the least important, a third time
newest again (release pulse-overdue-first).
