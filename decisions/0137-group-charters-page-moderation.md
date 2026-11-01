# ADR 0137: Group Charters, the Group Page, Group-Led Moderation, and Notices

**Status:** Accepted (Jason, 2026-11-01), from Jason's answers on `community-scope.md` → "Missing before the
build". Design; not built. Settles B1–B5 and C5 there, and A1 and A3–A6. Changes 0102 §4b–4c (who handles reports, business group
allowance, post caps), 0134 §7 (events per day) and 0136 §2 (chapters by place or topic). His answers are listed at the
end.

## 1. Who owns a business's group: one group per business, with chapters

Jason, 2026-11-01: listings as entities own groups; a business has **one group**, and uses **chapters** instead of
more groups. A group's owner is **a person, a listing or a Nexus** (`owner_kind`, `owner_id`); a business's group shows
"Run by <name>" and is run by the business's roles (0102 §4c). Staff changes never touch it.

Businesses come in the three models of the Master AOS (§B: Nexus, Co-Op, Decentralized), and each owns its group
differently:

| Model | Who owns the group | Chapters | What it avoids |
|---|---|---|---|
| **Decentralized** (one business, one listing) | The listing | Topics (Support, Sales, How-tos) and its service areas, if it serves more than its home town | — |
| **Nexus: brand or chain** (one operator, many locations, 0054) | **The Nexus**, not a "main listing" (a chain has none) | A place chapter for each location, led by that location's managers (a franchisee runs its own); topic chapters run by the brand | Picking one location to own the brand's community |
| **Nexus: system or agency** (a branch tree: VA → VA Northern Arizona → a clinic) | Each Nexus level that chooses to run a group owns one | Its own locations as place chapters, and topics. A child level's group shows "Part of <parent>" as an affiliation, not as a chapter (chapters stay one level deep, 0136) | Deep trees of groups inside groups |
| **Nexus: affiliation** (independent units under an umbrella: parishes under a diocese) | **Each unit owns its own group**; the umbrella may run its own | A unit's group becomes the umbrella's chapter only if both agree (0136 §2), never by being under it | The umbrella controlling units' groups (0054: "Affiliations never grant management") |
| **Co-Op** (a chamber, association or guild, run from its Master Hub) | **The Co-Op** (its listing and hub) owns the Co-Op's group | Committees and topics (Networking, Events, Advocacy) and places | Member businesses' groups being swallowed |

**Co-Ops, in more detail:**
- **Member businesses keep their own group** (each still one). Co-Op membership never gives the Co-Op any control of a
  member's group (Master AOS §B: dual compliance, independent tenants).
- **In the Co-Op's group, member businesses can post as their business** (the two voices, 0102 §4c), so a chamber's
  group is where its members speak to each other and to the town. Only someone who holds a role on that business can
  do it, and each such post is logged to that person, so the Co-Op's moderators know who said it.
- **Members-only chapters:** the Co-Op can make a chapter (or the whole group) open only to businesses with an active
  Co-Op membership. If the membership lapses, the business leaves those chapters and keeps everything of its own.
- The Co-Op's group lists its member businesses' **public** groups, so the chamber is a way into them; a member
  business can opt out of the list.

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
| **Selling** | Not allowed; members' own Market items; or businesses' items too. Defaults: a person's group, not allowed; a business's group, its own items; a Co-Op's group, its member businesses' items (0136 §5, Selling in groups) | Owner |
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
- Changing the transfer terms gives members 14 days' notice before it takes effect.
- **Who sees the member list:** making it more private takes effect at once; making it less private waits 14 days,
  and in that time each member can hide themselves from the list.

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
- **When a reported post hides:** after 2, 3 (default) or 5 reports, or only when a moderator decides. Guards:
  - only **different reporters** count, and only members of **more than 7 days**, so friends can't team up to hide a
    post;
  - **private information and sacred-site reports hide on the first report**, as takedowns do;
  - the author sees "Hidden while it's looked at";
  - **if nobody acts within 14 days,** a Group help ticket opens on its own, so nothing sits hidden forever.
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
   - **Replies to the From address aren't lost:** some mail apps ignore Reply-To. Mail to a `via` address is forwarded
     to the business's Reply-To address; if the business has none or has turned forwarding off, the sender gets a
     clear reply saying how to reach the business. Lost replies turn into complaints.
   - We never touch their DNS. We manage the records for our sending subdomains, once.
2. **Two sending subdomains on SES, kept apart:** `via.traversence.com` for mail on behalf of members and businesses,
   and `send.traversence.com` for Traversence's own notifications and digest. Sign-in codes stay on Bluehost from
   `traversence.com`.
3. **Connect your domain: one flow for the storefront and email, from Core.** Core and up can put their storefront on
   their own domain (`commercial.md`, `marketplace-architecture.md`); email from their own domain comes with it, so a
   Core business with `pinecafe.com` doesn't have its shop there and its mail from `via.traversence.com`.
   - One **Connect your domain** step in the business portal, using the same DNS check as the storefront
     (`DomainCheck`, `marketplace-architecture.md`): it shows the records for the storefront and the three DKIM
     records for email, checks them, and turns each on as it passes.
   - Their mail then comes from `news@pinecafe.com` on **their** reputation, not ours, which lowers our risk; we still
     count, watch bounces and complaints, and pause when needed.
   - White Glove can do it on a ticket. Members never send from their own domain.
   - Recorded in `commercial.md` (Core: own domain for the storefront and email).

### The legal footer

Every bulk email (an invitation, campaign or newsletter) carries:
- who sent it ("Pine Cafe, via Traversence") and why the person got it;
- **a valid postal address for the sender**, which US law (CAN-SPAM) requires: the business's address. A business
  whose address is hidden (a home business, `ListingSafety`) adds a **mailing address or PO box** in its settings, and
  can't send bulk email until it has one;
- one-click unsubscribe (below).

### Two AWS accounts, and pausing a business early

Separate subdomains protect each domain's reputation, but not the AWS account. AWS reviews an account at about **5%
bounces or 0.1% complaints** and can suspend it; if one business's bad list got the account suspended, everything on
it would stop.
- **Two AWS accounts for SES, at no extra cost:**
  - **Bulk:** invitations, campaigns, newsletters (`via` bulk mail).
  - **Per-person:** order confirmations, tickets, event changes, notifications and the digest (`send`, and `via`
    one-to-one mail).
  A suspended bulk account can't stop a ticket or a receipt.
- **A business is paused well below AWS's lines:** at **3% bounces or 0.08% complaints** over its recent sends. It's
  told why and what to fix (clean the list, confirm consent), and a person on our team can lift the pause.
- Gmail and Yahoo's 0.3% complaint line still matters, but AWS's 0.1% comes first, so our pause is set under both.

### Allowance and top-ups

- **Core: 500 emails a month** (Jason, 2026-11-01). Strategic 10,000 and Cornerstone 50,000 pooled, as
  `commercial.md` §1.1 has them; a Nexus pools its locations' allowances (Master AOS §5B).
- **Top-ups:** the existing ones in `commercial.md` (+10k $19.95, +25k $44.95, +50k $74.95, +100k $129.95), **plus
  +1,000 for $2.95** for Core (Jason, 2026-11-01): it fits the curve (+10k is about $2 per 1,000) and costs us about
  $0.10. All of them go into `Billing::ADDONS`, which today has storage and AI-credit packs but no email.
- The allowance counts the business's invitations, campaigns and newsletters. Its order confirmations and tickets
  don't count, and neither does anything Traversence sends.

### Amazon SES: cost, tracking and what it needs

**Cost** (AWS's published pricing as reported in 2026; the AWS site couldn't be reached from this session to confirm):
about **$0.10 per 1,000 emails**, plus a small charge for attachment data. A dedicated IP address (about $24.95 a
month) isn't needed at our volumes.

**Tracked like Claude** (no cost without revenue to cover it, `AiCredits`): SES gets a **monthly cap in Services &
usage** (`app/lib/Services.php`, decisions/0133), with its use and cost this month, the projection, and alerts at 80%
and 100%. So Free and Basic businesses' order emails can't add up unnoticed.

**What it needs:**
1. **Two AWS accounts** (bulk and per-person), each with a key allowed to send only, from our sending subdomains.
2. **DNS records** for `via.traversence.com` and `send.traversence.com`: three DKIM records each, SPF, DMARC, a
   mail-from record, and an MX record so `via` replies can be received and forwarded.
3. **Production access** for each account: new SES accounts start in a sandbox (only verified addresses, about 200 a
   day); AWS is asked to lift it, explaining consented mail, unsubscribe and bounce handling.
4. **Bounces and complaints:** SES reports them (through SNS) to an endpoint on our site, which stops mail to that
   address and applies the pause above.
5. **One-click unsubscribe** in every bulk email (the List-Unsubscribe headers Gmail and Yahoo require), tied to
   `Marketing` (a business's "no more from this business") and the person's master switch.
6. **The queue:** a `Scheduler::JOBS` job sends in batches within SES's rate, counts each send against the business's
   allowance, and stops at it.

### DNS and hosting

- **Where our DNS lives (confirmed by Jason, 2026-11-01):** `traversence.com` and `trvgo.us` are registered at
  whois.com, with their nameservers pointing to Bluehost, so **Bluehost's Zone Editor holds the DNS** for both.
- **So it's done through the cPanel API, not by hand** (CLAUDE.md): `app/lib/CPanel.php` gains the cPanel API's DNS
  functions (read the zone, add and change records) and a check that each record is live. The SES records for
  `via.traversence.com` and `send.traversence.com` go in that way.
- Renewals stay at whois.com (`trvgo.us` to Oct 15, 2027; `traversence.com` to Aug 21, 2028).
- **Storefront custom domains won't scale on Bluehost shared hosting:** each needs an addon domain and its own SSL,
  and those are capped per plan. When the Storefronts build reaches custom domains (`marketplace-architecture.md`
  step 5), plan on **Cloudflare's custom hostnames** (Cloudflare for SaaS) instead: the business points one record at
  us, and Cloudflare handles the certificate.
- **What that will need:** Cloudflare for SaaS runs on a zone that's on Cloudflare. Since both our domains are on
  Bluehost's nameservers, that step either moves `trvgo.us` (the storefront domain) to Cloudflare's nameservers or uses
  a separate domain for it; `traversence.com` and its mail can stay on Bluehost. Decided when the Storefronts build
  reaches custom domains.

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
| `email_sends` | business, campaign or invitation, AWS account (bulk, per-person), address hash, sent, bounced, complained |
| `listings` (email) | mailing address or PO box for the legal footer; Reply-To; forwarding of `via` replies on or off |
| `business_domains` | domain, storefront records checked, DKIM checked, live for storefront, live for email |

## Build order

1. Business ownership (listings) and the charter (with the 0103 lifecycle job, `community-scope.md` C4).
2. The group page (section 4) and `/community/` without breadcrumbs.
3. Group-led moderation and the "Group help" ticket type; legal and safety reports to Safety.
4. Notices sent once (`notices_sent`), used by everything above.
5. Email: add DNS to `CPanel.php` (our DNS is on Bluehost); mark every send system or not; two SES accounts
   (subdomains, production access, bounces and the early pause, unsubscribe, the legal footer and mailing address,
   `via` reply forwarding, the sending job); the allowance and top-ups in `Billing`; SES in Services & usage.
6. Connect your domain (storefront and email together, from Core), with the Storefronts build's custom domains on
   Cloudflare custom hostnames.
7. Flyer print products and ordering.

Each step adds its FAQ entries (0057).

## Step 1 built (2026-11-01): business-owned groups and the charter

Delivered as a zip (7 files that differ from the live mirror of 2026-11-01 16:40 UTC); live once uploaded and
`app/migrations/2027-01-16_group_charters.sql` has run.
- **Data:** `community_groups.owner_kind` / `owner_ref` (person or listing), `community_charters` (numbered versions),
  `community_group_members.charter_version` and `list_hidden`, `community_posts.voice` and `as_listing`. Safe to run twice.
- **Business-owned groups** (§1): a business's Owner or a Manager starts its **one** group from **Start a group → Start
  it as**; it doesn't use up anyone's 5. Its team runs it by role, through `Team::roleOf` (no membership rows needed):
  Owner (everything, the only one who can hide it), Manager (runs it), Creator (moderates, posts as the business),
  Support (moderates), Sales (posts as the rep, "Name · Sales, <business>"); Accountant, Developer and Viewer have no
  part unless they join. The page shows **Run by** the business. `Community::roleIn()` / `can()` hold the rules.
- **The charter** (§3, `app/lib/GroupCharters.php`): every new group opens with one (a purpose is required), filled
  from a starting charter by kind (interest, local, business, support). **What you're agreeing to** on the group page,
  **Read the whole charter** (with the ground rules, the inactivity rule and every version), **Agree and join** (joining
  records the version; a charter that changed while someone read it asks them to read again), **Edit the charter** for
  whoever runs the group. Transfer terms and opening the member list wait 14 days; members can hide their names
  meanwhile; a more private list is at once. Members are told once in their Community feed, until **Got it**. Groups
  from before charters show the ground rules, and their owner gets **Write the charter**.
- **Posts** (§7): the 30-a-day cap is gone; the same words twice in a day are held.
- **FAQ:** a new **Community groups** section (7 entries).
- **Tested** on MariaDB: the migration twice, 34 checks of the rules (charters, versions and notice, joining, roles,
  voices, one group per business), and the pages in a browser at 390px (no sideways scroll, no JS errors from these
  pages).
- **Not in this step:** Nexus- and Co-Op-owned groups (a Nexus has no managers in the code yet, and Co-Op memberships
  don't exist), chapters (0136), the group page's new layout (step 2: it still has breadcrumbs), group-led reports
  (step 3: reports still come to Traversence), the inactivity job (0103), and the charter settings that steer features
  not built yet (invitations and codes, selling, report thresholds): they're saved and shown, and take effect as those
  are built.

## Jason's answers (2026-11-01)

1. **Legal and safety reports** and reports on a group open a Safety ticket automatically; our Terms apply on it.
2. **Bluehost for system email only;** everything else on SES. Notifications and the digest go on SES
   (`send.traversence.com`); anything with a legal clock, such as Standing notices, stays on Bluehost.
3. **Who owns a business's group** by model (section 1), with the fixes: service areas for a single listing; Co-Op
   posts as a business only by its role holders, logged to the person; only public member groups listed, with opt-out.
4. **Report defaults:** hide after 3 reports from different members of more than 7 days; private-information and
   sacred-site reports hide at once; moderators reminded at 2 days, the owner at 7; a Group help ticket after 14 days.
5. **Charter changes:** members told, nobody re-accepts; transfer terms wait 14 days; the member list becomes more
   private at once and less private after 14 days, with members able to hide themselves.
6. **Email:** Core 500 a month, the existing top-ups plus +1,000 $2.95; one Connect your domain flow from Core for the
   storefront and email; two AWS accounts; a business paused at 3% bounces or 0.08% complaints; a postal address in
   every bulk email; `via` replies forwarded; DNS through the cPanel API (both domains are on Bluehost's nameservers); storefront
   domains on Cloudflare custom hostnames; SES capped and tracked in Services & usage.
