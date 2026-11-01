# ADR 0137: Group Charters, the Group Page, Group-Led Moderation, and Notices

**Status:** Proposed (2026-11-01), from Jason's answers on `community-scope.md` → "Missing before the build". Design;
not built. Settles B1–B5 and C5 there, and A1 and A3–A6. Changes 0102 §4b–4c (who handles reports, business group
allowance, post caps), 0134 §7 (events per day) and 0136 §2 (chapters by place or topic). Proposals waiting for Jason
are listed at the end.

## 1. Who owns a business's group: one group per business, with chapters

Jason, 2026-11-01: listings as entities own groups; a business has **one group**, and uses **chapters** instead of
more groups. A group's owner is **a person, a listing or a Nexus** (`owner_kind`, `owner_id`); a business's group shows
"Run by <name>" and is run by the business's roles (0102 §4c). Staff changes never touch it.

Businesses come in the three models of the Master AOS (§B: Nexus, Co-Op, Decentralized), and each owns its group
differently:

| Model | Who owns the group | Chapters | What it avoids |
|---|---|---|---|
| **Decentralized** (one business, one listing) | The listing | Topics (Support, Sales, How-tos) and, if it has them, places | — |
| **Nexus: brand or chain** (one operator, many locations, 0054) | **The Nexus**, not a "main listing" (a chain has none) | A place chapter for each location, led by that location's managers (a franchisee runs its own); topic chapters run by the brand | Picking one location to own the brand's community |
| **Nexus: system or agency** (a branch tree: VA → VA Northern Arizona → a clinic) | Each Nexus level that chooses to run a group owns one | Its own locations as place chapters, and topics. A child level's group shows "Part of <parent>" as an affiliation, not as a chapter (chapters stay one level deep, 0136) | Deep trees of groups inside groups |
| **Nexus: affiliation** (independent units under an umbrella: parishes under a diocese) | **Each unit owns its own group**; the umbrella may run its own | A unit's group becomes the umbrella's chapter only if both agree (0136 §2), never by being under it | The umbrella controlling units' groups (0054: "Affiliations never grant management") |
| **Co-Op** (a chamber, association or guild, run from its Master Hub) | **The Co-Op** (its listing and hub) owns the Co-Op's group | Committees and topics (Networking, Events, Advocacy) and places | Member businesses' groups being swallowed |

**Co-Ops, in more detail:**
- **Member businesses keep their own group** (each still one). Co-Op membership never gives the Co-Op any control of a
  member's group (Master AOS §B: dual compliance, independent tenants).
- **In the Co-Op's group, member businesses can post as their business** (the two voices, 0102 §4c), so a chamber's
  group is where its members speak to each other and to the town.
- **Members-only chapters:** the Co-Op can make a chapter (or the whole group) open only to businesses with an active
  Co-Op membership. If the membership lapses, the business leaves those chapters and keeps everything of its own.
- The Co-Op's group lists its member businesses' groups, so the chamber is a way into all of them.

**Moving between models:** when a listing joins a Nexus, its group can become the Nexus group's place chapter (both
agree), or stay its own if the Nexus is an affiliation. When a listing leaves a Nexus, its chapter can leave to become
its own group (0136 §2, Endings).

## 2. Chapters by place or by topic (amends 0136 §2)

Jason, 2026-11-01: "Single home or topic for chapters."

- A chapter is anchored by **a place** (a town, area or region; a Single Home Rule anchor once anchors are defined) **or
  a topic** (a sub-topic of the parent's: "How-tos", "Beginners", "Cast iron").
- **One chapter per place, and one per topic, per parent.**
- **Place chapters** show on their place's page, as 0136 says. **Topic chapters** show on the parent's page and in
  search by topic and tags; they don't appear on place pages.
- Everything else in 0136 §2 holds for both (one level deep, a verified lead, what's inherited, endings).

## 3. The charter

Every group has one. It's what a person reads before joining, and the rules the group runs by. Traversence doesn't
write or police it; it holds the group to it.

### What's in it

| Part | What it says | Who sets it |
|---|---|---|
| **Purpose** | What the group is for, in 1–3 sentences | Owner (required) |
| **Who it's for** | Anyone; locals; beginners; customers… | Owner |
| **House rules** | The group's own rules, as a list | Owner |
| **The ground rules** | Traversence's Terms; no harassment; no one's private information; no sacred or restricted sites (0066); no selling outside Traversence (0134) | Fixed: shown on every charter, can't be removed |
| **Joining** | Open or approval; join questions; whether invitations skip approval (never, from moderators, from any member) and whether sign-ups by code do (0136 §5c) | Owner |
| **Who sees who's in it** | Members see each other (the default), or **only the moderators see the member list** (for support, recovery, health groups) | Owner |
| **Reports** | Who handles them, when a reported post hides, how to appeal (section 5) | Owner |
| **Chapters** (a parent) | How joining a chapter works (0136 §2); whether chapters may add local rules | Owner |
| **What the business publishes** (a business's group) | Education, offers, events, product news; and the fixed contact rule: the business reaches a member outside the group only when the member starts it or Links (0102 §4c) | Business; the contact rule is fixed |
| **Transfer terms** | End the group (default), named successor, longest-serving moderator, or moderators choose (0103 §2a) | Owner |
| **Inactivity** | 30 days without posts, or 90 without owner or moderator activity, starts the ending stages (0103 §4) | Fixed: shown, not editable |

### Writing it

- **A starting charter** fills in by kind (interest group, local group, chapter, business group, support group), for
  the owner to edit. A group can't open without a purpose.
- **AI help** ("Write it for me") drafts the purpose and house rules from what the owner says the group is for: AI
  Assist, on plans with AI, from their credits (`commercial.md`; free accounts see it greyed out with the reason).
- The owner edits; moderators can suggest changes for the owner to accept.

### Changing it

- Each change is kept as a version: "Charter changed on 12 Nov: joining is now by approval".
- Members are told once (section 6). Nobody has to accept it again and nobody is removed by a change; anyone who
  doesn't like it can leave. New members accept the version they read.
- Changing the transfer terms or who sees the member list gives members 14 days' notice before it takes effect.

### Where it shows

- **Before joining:** a short "What you're agreeing to" card (purpose, house rules, who sees you, the transfer terms),
  with the whole charter one tap away. Joining is consent to it.
- **On the group page:** its own tab, with the version history.

## 4. The group page as a content page

The group page follows the content-page standard (0058 §11, §20–21), like listings and places. A mockup accompanies this
decision. Top to bottom:

1. **Hero** (`tv_explore_hero()`): the group's cover photo or a slideshow from the welcome post, flush under the tool
   bar; **manage** for the owner. No breadcrumbs.
2. **Header:** the kind as a pill that searches ("Interest group", "Local group", "Chapter of World Travelers", "Run by
   <listing>"), the name (not a link), the place (a local group) or topic, **Link** top right.
3. **Engage bar** (`tv_engage_bar`, kind `group`): Join (or Ask to join, or Joined ▾), Share, QR, Link, Invite (members).
4. **At a glance:** member count, how active it is ("12 posts this week"), the next event, the join mode.
5. **Tags** as pills that search (0136 §1).
6. **Welcome post** (public; 0136 §5).
7. **Charter card:** "What you're agreeing to", with Read the charter.
8. **Upcoming events** (0134), each with Going.
9. **Chapters** (a parent): a list with place or topic and member count; "Start a chapter".
10. **Places we're about** (0136 §3).
11. **Members only, below:** the posts, then members (as the charter allows), then files and photos.

- On phones: edge to edge, 3px cards (0058 §11); action buttons as round icons down the right of each card.
- **The share card** (`tv_page_start` `head`): name, photo, place or topic, member count, next event.
- **Views** counted (`tv_count_item('group:<id>')`); outbound taps counted, never who.
- `/community/` loses its breadcrumbs too.

## 5. Moderation is the group's own, with Traversence by support ticket

Jason, 2026-11-01: Traversence interacts with a group only by support ticket. Reporting follows the group's charter.

### Inside the group (set in the charter, "Reports")

- **A member reports a post or a member** with a reason (spam, harassment, someone's private information, a sacred or
  restricted site, off-topic, other). The reporter is never shown.
- **Who handles it:** the moderators (default), or the owner only. In a chapter, it goes to the chapter's moderators,
  then the parent's if the chapter's don't act within the time set.
- **When a reported post hides:** after 2, 3 (default) or 5 reports, or only when a moderator decides.
- **How fast:** moderators are reminded after 2 days; after 7 days, it goes to the owner (and then the parent).
- **What they can do:** remove the post, warn, mute for a time, remove the member, block them from rejoining.
- **Appeals:** a member whose post or membership was removed can ask the owner once; in a chapter, then the parent's
  owner. That's final inside the group.
- **Everything is logged** for the group's leaders.

### Where Traversence comes in

- **By support ticket, the normal way:** the owner or a moderator opens a "Group help" ticket (0102 §5) for anything
  they can't handle: a member harassing across groups, a moderator account taken over, a dispute they're stuck on. The
  agent gets timed access with the role the ticket sets.
- **Legal and safety reports open a ticket on their own** (Jason, 2026-11-01). A report marked **"This breaks the law or
  puts someone in danger"** (a threat of violence, child abuse material, someone's private information exposed, a
  court order, a copyright notice), and **any report about a group as a whole**, creates a **Safety ticket**
  automatically, in the support queue with the group and the item attached. The group is told a report went to
  Traversence; the reporter is never shown.
- **Our Terms are applied rigorously on those tickets:** the Safety team decides on the Terms, not the charter: remove
  the content, restrict or end accounts through the enforcement ladder and Standing (0079), hide or end the group, and
  report to the authorities where the law requires. Child abuse material is removed at once and reported as the law
  requires. Every action is in the ticket's log.
- **Everything else stays with the group.** Ordinary reports no longer come to Admin → Social → Reports, and 3 reports
  no longer hide a post for an admin to decide: the group's charter decides (this changes 0047 and 0102 §4b).

## 6. Notices that are sent once: to whom, and why

A notice is sent when someone asked for it or needs it to act, and **once**: one per person, per thing, per reason. It
goes to the Notification Center; the person chooses whether it also comes by email or in the weekly digest, and can
turn each kind off.

| Notice | To whom | Why |
|---|---|---|
| A new group or chapter with a tag, topic or place you follow starts near you | People who follow it (0132) | They asked to hear about it |
| A chapter of your group opens near you | The parent's members whose private home place is near (0136 §5) | They're already in the group |
| You're invited to a group | The person invited (again after 30 days at most, 0136 §5a) | Someone they're connected to asked |
| Your request to join was answered | The person who asked | They're waiting |
| The charter changed | The group's members | What they agreed to has changed |
| A group listed your business among the places it's about | The listing's owner | So they can remove it (0136 §3) |
| An event you're going to changed or was cancelled | People going or interested (0134) | They made plans |
| Your group goes quiet: it enters Notice in 9 days | The owner and moderators | So they can act before it ends (0103) |
| Your group is entering Notice / Hidden / Archived | The owner, moderators and members | 0103's stages |
| A report needs you / is still waiting | The group's moderators, then owner | Section 5 |
| You're near this month's ticket limit | The business's Owner and Manager | 0134 §6b |

## 7. Posts and events have no caps

- **Posts** (what a member or business writes in a group or feed; A4): no daily limit. Repeated identical posts are
  held, and floods are dealt with by the group's reports. This replaces 0102 §4c's "posts per day capped by plan".
- **Events** (A5): no limit on how many anyone posts; we step in only on abuse. Tickets stay capped by plan (0134 §6b).
  This replaces 0134 §7's "events per day capped by plan".

## 8. Email: two services, allowances and top-ups

### Which service sends what (Jason, 2026-11-01)

**Bluehost sends system email only. Everything else goes through Amazon SES (AWS).**

| Bluehost (system email, from traversence.com) | Amazon SES (everything else) |
|---|---|
| Sign-in and verification codes, password resets, two-step | Group invitations, from members and from businesses (0136 §5a) |
| Account notices: deletion, restore, security, Standing | Business and Co-Op campaigns and newsletters |
| Traversence's own sales: GO Code, print and plan receipts, invoices, payment failures | A business's own sales: order confirmations, tickets and their changes (0134) |
| Support tickets and their replies | Event changes and cancellations to people going |
| Staff and system reports (the daily report) | Notification emails and the weekly digest (0137 §6) |

Every place the site sends mail (39 files call `Mailer::send`) is marked system or not; the not-system ones move to SES.

### What "a separate sender" means

The site's mail code (`app/lib/Mailer.php`) has one function that pages call to send an email. It can send as more
than one **sender**: each sender is a from-address plus the mail server it signs in to, set in `.env` under a name
prefix. Today there are two: the default (`noreply@`, through Bluehost) and `ORDERS` (`orders@`). So adding Amazon SES
doesn't mean rewriting the pages that send mail: SES becomes another sender, and each page says which sender to use.
For SES we'd call its API through `app/lib/Aws.php` (which already signs AWS requests for S3) rather than its SMTP
server, because the API lets each message carry which business sent it, for counting and for bounce handling.

### Sending on behalf of people and businesses: whose domain?

Mail from a member, a group or a business isn't Traversence's own, but it can't be sent "from" their domain unless
they've let us (inboxes check the domain's DNS records, and fail mail that isn't authorized). So:

1. **By default, it comes from our sending domain, in their name.**
   - From: "Pine Cafe via Traversence" `<pine-cafe@via.traversence.com>`
   - Reply-To: the business's own email (from its listing), so replies go straight to them.
   - The footer says who sent it, why the person got it, and how to stop it.
   - Their DNS isn't touched. We manage the records for our sending subdomains, once.
2. **Two sending subdomains on SES, kept apart:** `via.traversence.com` for mail on behalf of members and
   businesses, and `send.traversence.com` for Traversence's own notifications and digest. A business with a bad list
   can't hurt the notifications, and neither can hurt sign-in codes, which stay on Bluehost from `traversence.com`.
3. **"Send from your own domain"** (Strategic and up, optional): the business adds three DKIM records to its own DNS
   (we show them and check them, and White Glove can do it on a ticket). Then its mail comes from `news@pinecafe.com`,
   on its own reputation; we still count, watch bounces and complaints, and pause if needed. Members never send from
   their own domain.

**Who does the DNS:** for our subdomains, the records go wherever `traversence.com`'s DNS is kept (Bluehost's Zone
Editor or Cloudflare), once; `app/lib/CPanel.php` has no DNS calls today, so either they're added by hand once or the
cPanel API's DNS functions are added. For a business's own domain, the business adds them, with our check.

### Allowance and top-ups

- **Core: 500 emails a month** (Jason, 2026-11-01). Strategic 10,000 and Cornerstone 50,000 pooled, as
  `commercial.md` §1.1 has them; a Nexus pools its locations' allowances (Master AOS §5B).
- **The top-ups already exist** in `commercial.md` ("Top-ups when a plan's allowance runs out"): **+10k $19.95,
  +25k $44.95, +50k $74.95, +100k $129.95**, with SMS and push alongside. Those are used; nothing new is priced here.
  (The code has storage and AI-credit packs in `Billing::ADDONS`, but no email yet: the allowance and these top-ups
  are added there.)
- The allowance counts the business's invitations, campaigns and newsletters. Its order confirmations and tickets
  don't count, and neither does anything Traversence sends.

### Amazon SES: cost and what it needs

**Cost** (AWS's published pricing as reported in 2026; the AWS site couldn't be reached from this session to confirm):
about **$0.10 per 1,000 emails**, plus a small charge for attachment data. A dedicated IP address (about $24.95 a
month) isn't needed at our volumes. A Core business's 500 a month costs us about 5 cents.

**What it needs:**
1. The AWS account we already use for S3, with a key allowed to send only, from our sending subdomains.
2. The DNS records for `via.traversence.com` and `send.traversence.com`: three DKIM records each, SPF, DMARC, and a
   mail-from record.
3. **Production access:** new SES accounts start in a sandbox (only verified addresses, about 200 a day); AWS is asked
   to lift it, explaining that we send consented mail with unsubscribe and handle bounces.
4. **Bounces and complaints:** SES reports them (through SNS) to an endpoint on our site, which stops mail to that
   address and pauses a business's sending if its rates climb (complaints above 0.3% is Gmail's and Yahoo's line).
5. **One-click unsubscribe** in every bulk email, tied to `Marketing` (a business's "no more from this business") and
   the person's master switch.
6. **The queue:** a `Scheduler::JOBS` job sends in batches within SES's rate, counts each send against the business's
   allowance, and stops at it.

Where Jason does it: the AWS console (production access, the key's permissions) and the DNS records; the `.env` keys
go in through File Manager, as for S3.

## 9. Flyers printed through us create orders (0136 §5b)

"Order it printed" is a purchase:
1. The owner picks the product (flyer, half sheet, table tent, card), size, paper and quantity: catalog items with
   the vendor's prices and our markup (`Products`, 0101, 0130).
2. **The flyer is the proof:** they approve it at checkout.
3. They pay through our QuickBooks checkout (0095), tax from their address; the order lands on the ledger (0098).
4. It becomes a work order to the print vendor (0106), and moves through its stages: in production → shipped or ready
   for pickup → complete, emailing at each step (`ClientOrders`).

Flyer products are added to the catalog for this (`community-scope.md` C7).

## Events storage (C6)

Not a question: where events are stored is decided (0134 §1, the `events` store, with the crawler's Live events moved
in). It's listed because it isn't built yet, and group events need it.

## Data (sketch)

| Change | Fields |
|---|---|
| `community_groups` | `owner_kind` (person, listing, nexus), `anchor_kind` for chapters (place, topic), `sub_topic`, `coop_only` |
| `community_charters` | group, version, purpose, who for, house rules, joining settings, member-list visibility, report settings, chapter settings, business publishing, transfer terms, changed by, changed at, takes effect at |
| `community_reports` | the group's own report queue: item, reason, count, hidden, handled by, action, appeal |
| `notices_sent` | person, thing, reason, sent: one row = "once" |
| `Billing` | an email allowance per plan; the existing email top-ups in `ADDONS` |
| `email_sends` | business, campaign or invitation, address hash, sent, bounced, complained |

## Build order

1. Business ownership (listings) and the charter (with the 0103 lifecycle job, `community-scope.md` C4).
2. The group page (section 4) and `/community/` without breadcrumbs.
3. Group-led moderation and the "Group help" ticket type; legal and safety reports to Safety.
4. Notices sent once (`notices_sent`), used by everything above.
5. Email: mark every send system or not; SES (subdomains, production access, bounces, unsubscribe, the sending job);
   the allowance and existing top-ups in `Billing`; later, sending from a business's own domain.
6. Flyer print products and ordering.

Each step adds its FAQ entries (0057).

## For Jason

Settled 2026-11-01: legal and safety reports open a Safety ticket automatically, and our Terms apply on it; Bluehost
for system email only, everything else on SES; email top-ups are the existing ones.

Still to confirm:
1. **Who owns a business's group** by model (section 1): the Nexus for a chain, each level for a system, each unit for
   an affiliation, the Co-Op for a chamber, with members-only Co-Op chapters.
2. **Notification emails and the weekly digest on SES** (section 8): they're Traversence's own, but not "system" in
   the sense of sign-in and receipts. Agree?
3. **Report defaults:** a post hides after 3 reports; moderators reminded at 2 days, owner at 7.
4. **Charter changes:** members told, nobody re-accepts; 14 days' notice for transfer terms and member-list visibility.
5. **Core's top-up step:** the smallest existing top-up is +10k; should Core get a smaller one (e.g. +1,000)?
