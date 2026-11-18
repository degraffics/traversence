# 0172: Communications: who reaches whom, about what, by which channel, and one way to deliver

Status: **proposed, first draft** (2026-11-18). Waiting for Jason's OK. No code yet.
Builds on: 0047 and 0052 (links, messages, marketing preferences), 0049 (consent), 0066 (nations' land), 0102 and
0100 (roles), 0108 (sales, SMS and push), 0141 (Market 360°'s unsubscribe), 0154 (who pays), 0167 (system email in one
place), 0171 (the Control Board's alarms).
Amends (each marked **Note for Jason** where it happens): 0052 step 6 (guest questions, §3.3) and 0167 §2's Sent view
(§4.3). Nothing else is reversed.
Hand-off: `handoffs/communications.md`. Checked against the live mirror at "Sync: 2026-11-18 02:36 UTC".

## What Jason asked for

Jason, 2026-11-18:
- "I would like to talk about the communications system. 1. Internal System Communications (pulse, alerts,
  notifications, email, push, text) 2. Between accounts communications (Comments, Messages, Push, Text, email)
  3. listings communications (Comments, Messages, Push, Text, email)".
- "Who: we need communication outlines for each WHO and then for each WHAT regarding services."
- On listings: "how can an [un]claimed listing have a message sent, this is a listing card layout and functions issue."
- On texting: "need to look at adr's to fully understand where texting services should be applied."

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

## Decision

**Every message is one record, with one sender, one recipient, one kind, routed by the recipient's preferences, through
one delivery log.** Who may reach whom is decided by the WHO tables (§1); what each event sends is decided by the WHAT
tables (§2). Cards show only what a listing can do (§3). One dispatcher delivers (§4). Texts go out only where revenue
pays for them (§5).

### The channels, and who pays

| Channel | Costs us | Who pays |
|---|---|---|
| **In the site** (Pulse, the bell, inboxes) | nothing | free, always on, can't be switched off for things that need an answer |
| **System email** (Bluehost, the site's own notices) | nothing | free (CLAUDE.md: system emails cost nothing) |
| **Business email** (SES: a business's campaigns, offers) | per send | the business's plan allowance or email pack (`commercial.md`) |
| **Web push** (our own keys, 0108) | nothing | free for a person's own notices; a business's marketing push counts against its plan's push allowance |
| **Text** | per message, plus the number | only a plan's text allowance or a text pack, or a budget Jason sets (§5, Q1) |
| **Voice** | per minute | not proposed |

**Urgency levels** (one list for every event):

| Level | Means | Quiet hours | Digest |
|---|---|---|---|
| **Now** | security, a code, money moved, a trip | ignored | never |
| **Soon** | someone wrote to you, an order or booking changed | push and text wait; in-site and email go | never |
| **Daily** | activity: comments, likes, group posts | wait | can be gathered |
| **Offers** | a business's marketing (0047 §3) | wait | the person's chosen frequency |

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
| **Reached by** | members by the same rules; businesses they **linked** (in-site only, 0047 §3), and by email or text only with the detail **shared** with that business; groups; us (account, security, orders) |
| **Channels** | in the site, system email, web push (new). Text only from a business they shared their number with and opted in to texts. |
| **Preferences** | notice kinds and delivery (exist); marketing per business, frequency, master switch (exist); muted message types (exist); **new:** per kind × channel choices, quiet hours, digest time, "push on this device" |
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
| **Can reach** | people who linked it (in-site, typed messages, 0052 step 4); anyone who wrote first (30 days); guests who asked; its customers about their orders and bookings; people who shared email or phone and opted in, by that channel |
| **Reached by** | members and guests (only once managed); its customers; us (claims, billing, plan, policy, Pulse support); the system (orders, bookings, low stock, domain) |
| **Channels** | the business inbox and Pulse, system email, web push (new); text **to** them only for paid alerts they choose (§5) |
| **Preferences** | **new:** per kind × channel for the business (new message, new order, new booking, comment, review) and who on the team gets each (§1.5) |
| **Privacy** | never sees a person's email or phone unless shared (0047); a guest's device never |
| **Pays** | business email, marketing push and text from its plan or packs; its own alerts by in-site, email and push free |

### 1.5 Business staff (0102 §3 roles)

| Role | Gets, by default |
|---|---|
| Owner | everything; can change anyone's share |
| Manager | messages, orders, bookings, comments, reviews |
| Accountant | payments, invoices, refunds, plan and billing |
| Sales | Sales-typed messages, leads, quotes |
| Support | Support and inquiry messages, guest questions, orders' problems |
| Creator | comments and reports on content they made |
| Developer | domain, API keys, webhooks |

A business with one person sends everything to the owner (0102: "a business with one person sees no roles"). Each
person then chooses their own channels and quiet hours.

### 1.6 A business's customer

Anyone who bought, booked, got a quote or a work order from a business (member or not).

| | |
|---|---|
| **Can reach** | that business, about that order (a reply in the order's thread) |
| **Reached by** | that business, about that order only: receipt, changes, ready, reminders. **Not marketing** unless they also linked or opted in (0047: "buying from it never opts anyone in"). |
| **Channels** | system email (exists), in the site if a member, web push if a member who allowed it; text only if they ticked "Text me about this order" at checkout (§5, Q2) |
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
- Staff choose channels and quiet hours on their staff login; **Now** items ignore quiet hours.
- **Pays:** free (in-site, system email, our own web push). Never text (0100: "no text messages"; 0171).

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
| Claim started, code | claimant | email | Now | — | F | B (`Claims::start`) |
| Claim by text to us | staff | text in → Pulse | Soon | — | $ (the number) | B by hand; D automatic (0070, 0133) |
| Claim code sent by text or voice | claimant | text | Now | — | $ | D, end state (0015, 0016) |
| Claim verified, held, declined, more asked | claimant | email + notice | Soon | in Pulse | F | B (`tellClaimant`) |
| Claim to review | claim staff | Pulse + email | Soon | — | F | B (`tellAdmins`) |
| Claim invite (sales) | business | email, GO Code; text with "Reply YES" | Offers | yes | F; text $ | email B; text D (0108) |
| Someone tried to message an unclaimed listing | Sales | Pulse count | Daily | — | F | G (§3.3) |
| Listing changed by us or a source | owner | notice | Daily | — | F | G |

### 2.2 Messages

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Member → member | recipient | in-site, bell | Soon | yes | F | B |
| Message request | recipient | in-site, bell | Soon | accept, decline, block | F | B |
| Member → business | business staff by role (§1.5) | in-site, **email, push** | Soon | yes | F | in-site B; **email/push G** (gap 1) |
| Guest question | business staff | in-site, **email, push** | Soon | yes | F | in-site B; email/push G |
| Business answers a guest | guest | email (if chosen), reply code | Soon | yes | F | B (`bizReply`) |
| Business → linked people (typed) | members | in-site; email or text only if shared + opted in | Offers or Soon | yes | in-site F; email, text P | in-site B; email, text D (0047, 0141) |
| Message reported | Safety staff | Pulse | Soon | — | F | B |

### 2.3 Comments

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Comment on a member's post | author | notice | Daily | yes | F | B |
| Comment on a listing | owner and the role in §1.5 | notice, email per choice | Daily | G | F | G (gap 3) |
| Comment on a guide, journey or place | its creator; else nobody | notice | Daily | — | F | G |
| A reply to my comment | commenter | notice | Daily | yes | F | G (comments are flat; needs replies first) |
| Comment reported | Safety staff | Pulse | Soon | — | F | B |

### 2.4 Orders and payments

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Client order (ours) placed, stage, done | buyer; staff | email | Soon | in thread | F | B (`ClientOrders::mail`) |
| Marketplace order placed | buyer | email | Soon | — | F | B (`mailBuyer`) |
| Marketplace order placed | **business** | in-site, **email, push** | Now | yes | F | in-site B; **email/push G** (gap 2) |
| Order status for the customer by text | customer | text | Soon | STOP | P | G (§5, Q2) |
| Payment failed, refund, credit | buyer; Accountant | email + notice | Now | — | F | B (`Orders::tell`) |
| Quote or invoice | customer; business on approval | email | Soon | yes | F | B (`BizQuotes`, `q.php`) |
| Work order | vendor | email from orders@ | Soon | yes | F | B |

### 2.5 Bookings and appointments

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Booking or rental placed | customer; business | as orders (§2.4) | Soon | yes | F | B for the customer; G for the business |
| Reminder before it (24 h, 2 h) | customer | email, push; text if ticked | Soon | — | F; text P | G |
| Changed or cancelled | the other side | email, push, notice | Now | yes | F | G |
| Claim appointment | staff | email | Soon | — | F | B (`Claims::appointment`) |

### 2.6 The marketplace and storefronts

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| A question on an item | business | in-site; email, push | Soon | yes | F | in-site B (`MarketBiz::message`); G for email/push |
| Offer made, countered, accepted | both sides | email | Soon | yes | F | B (`MarketOffers`) |
| Low stock, item paused | business | notice | Daily | — | F | G |
| Storefront "Message us" | visitor → business | opens the listing page | — | — | F | B (see §3.2) |

### 2.7 Domains

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Bought, verified, renewal due, renewed, failed | owner; Developer role | email + notice | Now for failures | — | F | email B (`AccountDomains`); notice G |

### 2.8 Community and groups

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Group invite, group near you, about a group | member | notice | Daily | — | F | B (`GroupGrowth`, `Community`) |
| New post in my group | members who chose it | notice, digest | Daily | yes | F | G (each group's own setting) |
| Join request | group moderators | notice | Soon | accept, decline | F | G |
| Post hidden | its author | notice | Soon | appeal | F | B (`tools.php:1934`) |

### 2.9 Moments, Snaps and Keeps (0153)

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Comment on my Moment | author | notice | Daily | yes | F | B |
| My Moment is ending | author | notice | Daily | — | F | B (`moment_ending`) |
| Someone kept my Snap | sender | in the chat | Soon | — | F | B (0153) |
| Daily limit reached | author | in-page | Now | — | F | B |
| Tagged in a Moment | the person tagged | notice, push | Soon | remove tag | F | G |

### 2.10 Guides and journeys

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| My guide is ready | who asked | notice + email | Soon | — | F (inside the AI cap) | B (`OnDemand::mailReady`) |
| A business named in a guide | owner | notice | Daily | — | F | G |
| Guide audit, takedown | content staff | email, Pulse | Soon | — | F | B |

### 2.11 Sign-in and security

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Verify email, reset, password or email changed | member | email | Now | — | F | B |
| Staff and admin codes | staff | email | Now | — | F | B (`TwoStep`, `StaffLogin`) |
| Members' sign-in codes | member | email | Now | — | F | not built; text ruled out here (§5, Q3) |
| New device or sign-in | member | email, push | Now | "Not me" | F | G |
| Account deleted, standing notices | member | email | Now | appeal | F | B |
| Vault codes | staff | email | Now | — | F | B |

### 2.12 Our system alerts (0171)

| Event | Told | Channels | Level | Reply | Pays | State |
|---|---|---|---|---|---|---|
| Trip | Founder; the process's staff | board light, Pulse item, email at once, the hourly push | Now | acknowledge | F | D (0171 §7, being built) |
| Warning unacknowledged 24 h | Founder | email | Soon | acknowledge | F | D (0171) |
| Notice | — | board and reports only | Daily | — | F | D (0171) |
| Daily report | Founder | email | Daily | — | F | B |
| Mail failure, bounce | mailbox's overseers | Pulse | Soon | fix | F | D (0167 §2) |

## 3. Listing cards show only what the listing can do

### 3.1 The rule

Each action on any card shows **only when it works for that listing, for that viewer**:

| Action | Shows when |
|---|---|
| **Message** | the listing is **managed** (someone holds `listing_access`) and the viewer isn't its owner |
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

**So:** two cards offer a Message that can't work (storefront edge case, Dashboard → Marketing), and every grid, map
and guide card leaves Message off listings that **can** take one. Both are fixed by the one helper in §3.1.

### 3.3 Messaging an unclaimed listing: what the visitor sees instead

An unclaimed listing has no one to read a message, so it never offers **Message**. Instead, where Message would sit:

- **"Wish you could message them?"** (one tap, signed in or not, counted only: listing, day, never who). It answers:
  "No one manages this listing yet. Call them, or we'll let them know people are asking." With **Call** if there's a
  phone.
- The count goes to **Sales** (0108) as a reason to invite: "12 people tried to message you this month." The claim
  invite carries it, and the 30-day limit per business still holds.
- **Note for Jason (changes 0052 step 6's scope only by adding):** we **don't** hold a visitor's message for a business
  that may never claim; it would promise an answer we can't give. Guest questions stay managed-only, as built.

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

### 4.2 Routing

For each recipient, in order:
1. **May they be reached at all?** Blocks, `message_policy`, a business's link and consent (0047), suppression lists,
   STOP lists. If not, the record says why and stops.
2. **Which channels?** The kind's defaults, then the person's choices (kind × channel), then what's available (push
   only with a subscription; text only with consent and a paid allowance).
3. **When?** Now ignores quiet hours; Soon waits only for push and text; Daily and Offers go to the person's digest at
   their chosen time.
4. **In-site always**, for anything that needs an answer.

### 4.3 One delivery log

**`comms_deliveries`**: one row per channel tried (in-site, email, push, text), with its status, the provider's id,
the error and when. 0167's **Sent view** in Pulse reads this log.
- **Note for Jason (refines 0167 §2):** 0167 says `Mailer::send` records each Bluehost send. That stays true; the record
  lands in `comms_deliveries` (with `email_sends` kept for SES), so there is one Sent view, not two.
- A failed delivery: retried by the channel's own rule; a dead address or subscription is marked and not tried again.
  For staff, failures are 0167's Pulse cases.

### 4.4 Quiet hours, digests and opt-outs

- **Quiet hours:** default 9 pm–8 am in the person's own time zone (the device's, stored with their actions, per
  CLAUDE.md "Times"); each person can change them. Staff on their login.
- **Digests:** daily or weekly, at the person's time. The weekly notice digest (`Notices::digest`) becomes this.
- **Opt-outs:** per kind and channel in one page (App Settings → Notifications, which takes in today's Notices
  settings); a business's marketing stays where 0047 put it (Privacy → Marketing); every email, push and text links to
  the right one (0141 §5). Things that can't be turned off (codes, receipts, security) say why, greyed with the reason.

### 4.5 Staff and the system

- **0171's alarms** stay their own table (`control_alarms`) and their own three ways. When an alarm must reach a person
  (the Founder's email, a role's Pulse item), it calls `Notify` with the kind `alarm.trip` or `alarm.warning`, so it
  appears in the same log and respects the person's channels, with **Now** ignoring quiet hours.
- **Pulse items** assigned or escalated call `Notify` (gap 4).

### 4.6 Web push

- Our own VAPID keys (0108: "needs no outside account"), a service worker, `push_subscriptions` per device.
- Asked **only after an action that wants it** ("Tell me when they answer"), never on arrival.
- Each push carries a link to where it's answered and a "Stop these" link.
- Staging never sends push (`StagingGate::hold()`, 0165).

## 5. Texting

### 5.1 Where texts apply (from the decisions)

| Use | Decided in | Who pays |
|---|---|---|
| An owner texts **in** to prove a claim | 0070, 0071 (fallback), 0133 | $: our number, inbound fees |
| A claim code sent **out** by text | 0015, 0016 (end state) | $ (Q1) |
| Claim invites and sales outreach, "Reply YES" | 0108 | $ (Q1) |
| A business's marketing to people who shared their number and opted in | 0047, 0049, 0141, 0154 | P: plan allowance or text pack |
| A business's customers about their order or booking | **new here** (Q2) | P |
| GO Codes and member invites opening the phone's own app | 0094–0098, 0047 | free (built) |

**Ruled out** (kept): staff sign-in (0100), Control Board alarms (0171), any text with no revenue behind it, texting
someone who only tapped Link (0047), and members' sign-in codes (Q3).

### 5.2 The open questions

| # | Question | Proposed answer | For Jason? |
|---|---|---|---|
| Q1 | Who pays for our own texts (claim invites, claim codes out)? | Email and GO Codes stay first (free). Texts to businesses come from a **Sales texting budget** Jason sets in 0163's cost register, **default $0 (off)**; each text counted against it, failed ones too. The inbound claim number (~$1 a month) is the one standing cost. | **Yes:** the budget, and the $1 number |
| Q2 | Texts to a business's customers about orders and bookings | Allowed as **transactional** texts: the customer ticks "Text me about this order" at checkout (per order, unticked by default); only that order's updates; STOP ends it; paid from the business's text allowance. Never marketing. | Yes: confirm |
| Q3 | Members' sign-in codes by text | **No.** Members sign in by password or social login; codes stay email (free). Text codes cost with no revenue and are open to SIM swaps. Passkeys later. | Yes: confirm |
| Q4a | The provider | **Twilio** (0108: "Twilio first"), one card under Outside services (0152), keys in `.env` (`TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_MESSAGING_SID`), checked by the pre-flight (0171), held on staging (0165) | no |
| Q4b | The incoming-text webhook | `/api/sms-in.php`, checking Twilio's signature, logged in `comms_deliveries`; added to the paths Bluehost is asked to exempt (`research/bluehost-bot-check-2026-11-15.md`), since a blocked STOP must never be lost | no |
| Q4c | Toll-free or 10DLC; who registers | Start with **one toll-free number**, verified by Traversence (simpler and fast at low volume; no brand fee). Every business text starts with the business's name. Move to 10DLC, Traversence registering as the platform and each business as its own brand, only when a business's volume needs it. | **Yes:** who registers, and when to move |
| Q5a | One STOP list or one per business? | **Both, as the carriers require:** STOP to our number stops every text from that number (carriers enforce it). Per-business choices live in the person's preferences, so they can stop one business without stopping all. | Yes: confirm |
| Q5b | HELP wording | "Traversence: help at https://traversence.com/faq.php or support@traversence.com. Msg & data rates may apply. Reply STOP to stop." Attorney-reviewed before launch (0141). | Yes: attorney |
| Q5c | Whose time zone sets quiet hours | **The recipient's:** the device's stored zone; else the area code's; never earlier than 8 am or later than 9 pm there (US texting rules). | no |
| Q5d | How long consent records are kept | **5 years after consent ends** (the federal claim limit is 4), with wording, version, when, from where and how it ended (0108). Never deleted early by account deletion: kept as the proof, with nothing else of the person. | **Yes:** check against the privacy policy |
| Q6 | Which plans include texts, the pack price | Already set in `commercial.md` §1.1: **Strategic 500, Enterprise 2,500 a month**; packs **+500 $19.95, +2,500 $89.95, +5,000 $169.95, +10,000 $299.95**. Not in `Billing::ADDONS` yet (storage and AI only): they're added with the texting release. Basic and Core can buy a pack, with no allowance. | Yes: Basic and Core may buy packs? |

## 6. The build order

Each a release Jason reviews; free channels first.

1. **0171's alarms** (being built by the Control Board session), then **0167**'s four releases (system email in one
   place). Unchanged.
2. **One dispatcher, in-site:** `Notify`, `comms_messages`, `comms_deliveries`, the kinds register, the preferences
   page (kind × channel, quiet hours, digest), the bell counting everything. Closes gaps 1–4 in the site and by system
   email: a business told of new messages, orders and comments; Pulse escalations told.
3. **Cards (§3):** the one actions helper, Message on every managed listing's card, "Wish you could message them?" on
   unclaimed ones and its count in Sales, the two broken cards fixed.
4. **Web push** (free): keys, service worker, subscriptions, asked after an action; then the business and staff kinds.
5. **Comments, bookings, groups:** replies on comments, booking reminders and changes, group posts and join requests.
6. **Text, only where revenue covers it:** Twilio's card and webhook, consent and STOP, a business's texts to people who
   opted in and to customers who ticked it (plan allowance and packs in `Billing::ADDONS`); then the claim number
   automated. Our own outgoing texts only once Jason sets the budget (Q1).

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
