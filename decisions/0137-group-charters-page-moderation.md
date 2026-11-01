# ADR 0137: Group Charters, the Group Page, Group-Led Moderation, and Notices

**Status:** Proposed (2026-11-01), from Jason's answers on `community-scope.md` → "Missing before the build". Design;
not built. Settles B1–B5 and C5 there, and A1 and A3–A6. Changes 0102 §4b–4c (who handles reports, business group
allowance, post caps), 0134 §7 (events per day) and 0136 §2 (chapters by place or topic). Proposals waiting for Jason
are listed at the end.

## 1. A business owns its group as a listing, and has one

Jason, 2026-11-01: listings as entities own groups; a business has **one group**, and uses **chapters** instead of
more groups.

- A group's owner is **a person or a listing** (`owner_kind`, `owner_id`). A business's group belongs to its listing
  (the entity), shows "Run by <listing name>", and is run by the business's roles (0102 §4c). Staff changes never
  touch it.
- **One group per business.** A business with several listings (a Location Group, 0036) has one group, owned by its
  main listing.
- **Chapters do what more groups would have done:** one for each location, and one for each part of what it does
  (Support, Sales, How-tos, a product line). A member joins the business once and picks the chapters they want.
- This replaces 0102 §4c's "own allowance by plan": there's nothing to count.

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
- **Without a ticket, only where the law or someone's safety makes us** (proposed, see the end): a report can choose
  **"This breaks the law or puts someone in danger"** (a threat of violence, child abuse material, someone's private
  information exposed, a court order, a copyright notice through the DMCA queue). That report comes to Traversence's
  Safety team as well as the group. Reports on **a group as a whole** (a group set up to harass someone) come to us too,
  because the group can't judge itself. Standing across the site (0079) stays ours.
- **This changes 0047 and 0102 §4b:** ordinary reports no longer come to Admin → Social → Reports, and 3 reports no
  longer hide a post for an admin to decide; the group's charter decides.

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

## 8. Email: allowances, add-ons and the sending service

### Allowance (A1)

- **Core: 500 emails a month** (Jason, 2026-11-01), with **add-on packs** for more. Strategic 10,000 and Cornerstone
  50,000 pooled stay as `commercial.md` has them.
- The allowance counts invitations, campaigns and newsletters. Receipts, tickets, sign-in codes and in-app messages
  don't count.
- **Add-ons follow the existing pattern.** Plans already carry storage and AI-credit allowances with monthly add-on
  packs (`Billing::PLANS`, `Billing::ADDONS`: +25 GB … +5 TB storage, +50 AI credits). Email has no allowance or
  add-on in the code yet: it's added the same way, an email column on each plan and email packs in `ADDONS`.
- **Proposed packs:** +1,000 emails a month $2.95; +5,000 $9.95; +25,000 $29.95. Sending costs us about $0.10 per
  1,000, so the price is for the tool, not the cost.

### The sending service: Amazon SES

**Cost (AWS's published pricing as reported in 2026; the AWS site couldn't be reached from this session to confirm):**
- About **$0.10 per 1,000 emails**, plus a small charge for attachment data (our invitations have none).
- New AWS accounts get starting credits; the free allowance depends on when the account was made, so it's checked in
  the billing console.
- A dedicated IP address is optional, about $24.95 a month: not needed until we send hundreds of thousands a month.
- At Core's 500 and Strategic's 10,000, a month's mail costs cents to a dollar per business.

**What integrating it needs:**
1. **The AWS account we already use** for S3 (0123): `app/lib/Aws.php` already signs requests (Signature Version 4,
   no Composer), so SES's API can be called the same way. Or SES's SMTP endpoint plugs into `Mailer` as its own
   sender (`Mailer::send(..., $as)` already reads a sender's own SMTP settings by prefix, e.g. `CAMPAIGN_SMTP_*`).
2. **Our domain verified in SES:** three DKIM records, SPF including Amazon SES, a DMARC record, and a mail-from
   subdomain (e.g. `mail.traversence.com`), added in DNS.
3. **Production access:** new SES accounts start in a sandbox (only verified addresses, about 200 a day); AWS is asked
   to lift it, explaining that we send consented invitations with unsubscribe and handle bounces.
4. **Bounces and complaints:** SES reports them (through SNS) to an endpoint on our site, which marks the address
   (never mail it again), and pauses a business's sending if its bounce or complaint rate climbs (complaints above
   0.3% is the line Gmail and Yahoo hold bulk senders to).
5. **One-click unsubscribe** in every bulk email (the List-Unsubscribe headers Gmail and Yahoo require), tied to
   `Marketing` (a business's "no more from this business") and the person's master switch.
6. **A restricted key:** the site's AWS key gets permission to send only, from our domain.
7. **The queue:** a `Scheduler::JOBS` job sends in batches within SES's rate, counts each send against the
   business's allowance, and stops at the allowance.
8. **Bluehost stays for the rest:** sign-in codes, receipts and one-to-one mail keep going through `Mailer` as now.

Where Jason does it: the AWS console (production access request, the key's permissions) and DNS records; the
`.env` keys go in through File Manager, as for S3.

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
| `community_groups` | `owner_kind` (person, listing), `anchor_kind` for chapters (place, topic), `sub_topic` |
| `community_charters` | group, version, purpose, who for, house rules, joining settings, member-list visibility, report settings, chapter settings, business publishing, transfer terms, changed by, changed at, takes effect at |
| `community_reports` | the group's own report queue: item, reason, count, hidden, handled by, action, appeal |
| `notices_sent` | person, thing, reason, sent: one row = "once" |
| `Billing` | an email allowance per plan; email packs in `ADDONS` |
| `email_sends` | business, campaign or invitation, address hash, sent, bounced, complained |

## Build order

1. Business ownership (listings) and the charter (with the 0103 lifecycle job, `community-scope.md` C4).
2. The group page (section 4) and `/community/` without breadcrumbs.
3. Group-led moderation and the "Group help" ticket type; legal and safety reports to Safety.
4. Notices sent once (`notices_sent`), used by everything above.
5. Email allowance and packs; SES (domain, production access, bounces, unsubscribe, the sending job).
6. Flyer print products and ordering.

Each step adds its FAQ entries (0057).

## For Jason

1. **Legal and safety reports** (section 5): a report marked "breaks the law or puts someone in danger", and reports on
   a group as a whole, come to Traversence without a ticket. Agree? (Recommended: without it we can't act on threats,
   child abuse material or court orders inside a group.)
2. **Report defaults:** a post hides after 3 reports; moderators reminded at 2 days, owner at 7.
3. **Charter changes:** members told, nobody re-accepts; 14 days' notice for transfer terms and member-list visibility.
4. **Email packs:** +1,000 $2.95, +5,000 $9.95, +25,000 $29.95 a month.
