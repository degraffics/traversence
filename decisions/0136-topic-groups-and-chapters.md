# ADR 0136: Topic Groups, Chapters, and the Places a Group Is About

**Status:** Proposed (2026-11-01), from Jason's question: "Tie groups to the geography is good, but what if the topic
is the group's focus, say traveling the world or cooking with Crisco?" Design; not built. Builds on 0047 (community
groups), 0102 §4b–4c (group roles, business groups), 0103 (charters, transfer, inactivity), 0132 (tags and links), 0053
(search), 0066 (nations' land), 0134 (events). Jason's answers are at the end; section 5 (Growing a
group) is written out from his questions, for his review before this is accepted.

## Context

Groups come in two kinds today (0047, `app/lib/Community.php`):
- **Interest groups:** no place; the topic is their home. One of 11 broad topics (`Community::TOPICS`: Outdoors &
  recreation, Arts & crafts, Faith, Family & parenting, Health & wellness, Food & cooking, History & culture,
  Volunteering & causes, Business & careers, Hobbies & games, Other).
- **Local groups:** tied to one place (a town cluster, geo-hub or hub; `place_key`), and listed on that place's page
  (name and member count only).

What's missing for a topic-first group:
- **Finding it below the topic.** "Food & cooking" is too broad to find a group about baking with Crisco or cast-iron
  cooking. Search reads only the group's name and description.
- **Going local without splitting.** A large interest group (*World Travelers*) can't have a White Mountains branch
  that shows on that place's page while staying part of the whole. Today it would be a separate group with no tie back.
- **Saying where it's about.** A travel group plans trips to places we hold, but nothing connects it to them. Its posts
  can't be used for that: they're members-only and never read for search (0051, 0053).

Jason agreed (2026-11-01, `community-scope.md`) to four proposals: tags on groups, local chapters, places a group
names, and search by topic plus place. Brand names in group names aren't controlled (Jason, 2026-11-01): people
name groups freely; problems are handled through reports.

## Decision

### 1. Tags on groups

- A group carries **up to 5 tags** under its topic ("Food & cooking" → baking, cast iron, Crisco), set by its owner and
  moderators. **Free words, with existing tags suggested as you type** (Jason, 2026-11-01), so "cast iron",
  "cast-iron" and "castiron" end up one tag; the word data (lexicon) merges spellings that slip through.
- **What tags do:** find the group by what it's about, not just its name; show it on the tag's page beside the
  listings, guides, events and stories with the same tag (0132); tell people who follow the tag when a new group with
  it starts near them; and show related groups ("Groups like this"). They grant nothing, never rank a group higher, and
  are never taken from posts.
- **Why a limit:** without one, a group could tag itself with 40 popular words to show up everywhere.
- Tags are **what the group is about** (0132: a tag grants nothing). They show on the group's card and public page;
  tapping one searches for more (`TvSearch.open('<tag> groups')`, 0058).
- Search and the topic's page use them: "Popular in Food & cooking: baking, cast iron, canning".
- A tag can't name a sacred, burial or protected site (`Landmarks::SENSITIVE`, 0066); one that might waits for a
  person.

### 2. Chapters

A **chapter** is a local group that belongs to a larger group (its **parent**). *World Travelers* keeps its own
members and posts; *World Travelers: White Mountains* is its chapter there.

**Opening a chapter**
- A parent is any active group without a place, or with a hub-level place (a region's group can have town chapters).
  Chapters go **one level deep**: a chapter can't have chapters.
- The parent's owner opens a chapter, or approves a member's request to start one.
- **Chapters are about geography or topic, not size** (Jason, 2026-11-01; topic chapters added by 0137 §2): no member
  count to open one and no limit on how many a parent has. Instead:
  - **one chapter per place, or per topic, per parent** (one *World Travelers: White Mountains*, never two; a topic
    chapter such as *Cast Iron Cooks: Beginners* shows on the parent's page and in search, not on place pages);
  - **a chapter sits inside its parent's place:** a region's group has chapters in that region's towns and areas; a
    parent with no place can have chapters anywhere.
- **A chapter shows on its place's page from the day it opens,** so people there can find and join it. Empty or quiet
  chapters are handled by inactivity (Endings, below), not by hiding them.
- Each chapter has a **place** (a town cluster, geo-hub or hub; Single Home Rule anchors when they exist) and a
  **chapter lead**, the chapter's owner, who must have Verification on (0062). The lead's chapters count toward their
  5 groups (0047); the parent's owner doesn't use up their 5 on chapters they don't lead.
- **Name:** "<Parent>: <Place>" by default ("World Travelers: White Mountains"); the lead can change the part after the
  colon.
- **An existing local group can join a parent as its chapter,** when both owners agree. Its members are told, and
  nothing else changes for them.

**What a chapter takes from its parent, and what's its own**

| From the parent | Its own |
|---|---|
| Topic, and its tags to start with (a chapter has its own 5 and can change them) | Place, members, posts, moderators, lead |
| The charter's purpose and house rules (it can add local rules that don't contradict them) | Join mode (open or approval), its own events (0134) |
| The charter's transfer terms (0103 §2a), applied to the chapter | Its own inactivity clock (0103 §4) |

**Members**
- **How joining works is set by the parent's charter** (Jason, 2026-11-01), shown before anyone joins:
  - **Joining a chapter joins the parent too** (a person can leave the parent and stay in the chapter);
  - **Chapters are joined on their own** (the parent offers itself, nothing more); or
  - **Parent members only** (a person joins the parent first, then a chapter).
  The parent's owner can change it later; members already in are never removed by the change.
- Leaving the parent offers to leave its chapters too.
- Members see the chapter's posts, and the parent's posts if they're also parent members, as always.

**Posts and announcements**
- A chapter's posts stay in the chapter.
- The parent's owner and moderators can **post to all chapters** at once: it appears in each chapter, marked
  "From World Travelers".

**Moderation: the three levels, with the parent in between**
- Level 1 stays with members (block, mute, report).
- Level 2 goes to the chapter's lead and moderators, and **up to the parent's owner and moderators** if the chapter
  can't settle it or the problem is the chapter's own leadership.
- Level 3 is Traversence (Social → Reports, Standing), as for any group.
- The parent's owner can replace a chapter lead who has broken the charter, with notice to the chapter.

**Where chapters show**
- On their **place's page**, like any local group, with "Part of World Travelers".
- On the **parent's page**: a Chapters list (name, place, member count), and the chapters' public events (0134).
- In **search** by place (section 4).

**Endings (0103)**
- A chapter that goes inactive follows the stages on its own (0103 §3–4); its members are offered the parent.
- **It can be brought back** (Jason, 2026-11-01): posting or moderating during Notice stops the clock; while Hidden
  (30 days) the lead or the parent's owner restores it; while Archived (1 year) it's restored through a support ticket.
  Once removed, the place is free again and the parent can open a new chapter there.
- A chapter can **leave** to become an independent local group: the lead asks, members get 14 days' notice, and it drops
  the parent's name.
- When the **parent ends**, each chapter's lead is offered to carry it on as an independent group (14 days); a chapter
  whose lead doesn't answer ends with it.

**Business-run groups (0102 §4c, 0137 §1).** A business has **one group**, owned by its listing, and uses chapters for
each location (a Location Group, 0036, or a Cornerstone business's units) and each part of what it does (Support,
Sales, How-tos). The business's roles run them as they run the parent.

### 3. Places a group is about

- A group's owner and moderators can list **up to 10 places it's about**: towns, areas and regions, listings, outdoor
  places and public landmarks, each with an optional short note ("planned for June", "our home water").
- They show on the group's public page under **Places we're about**.
- Each place's page (or listing, or outdoor place) shows **Talked about by**: the group's name and member count, nothing
  more. It's a tag in 0132's sense ("Featured in"): it grants nothing.
- **Only the group's own choice puts it there.** It's never inferred from posts, which stay members-only and unread
  (0051, 0053).
- **A listing's owner can remove their listing** from a group's list; it can't be added back without the owner's
  agreement.
- **0066:** landmarks are offered only through `Landmarks::visibleSql()`; a name that may mark a sacred or protected site
  can't be added; places on a nation's land show with the nation's name and visitor rules.
- Local groups and chapters already belong to their own place; places they're about are in addition to it.

### 4. Searching by topic and place

"Cooking groups near Show Low" (and the same through Community's topic filters) returns, in this order:
1. **Local groups and chapters** whose place is that place or nearby, nearest first.
2. **Groups about places nearby** (section 3).
3. **Interest groups** that match the topic or tags, with no distance, marked "Anywhere".

Within each, groups with more active members come first. Never by payment (0007). Members-only posts are never
searched (0053).

### 5. Growing a group: indirect tools so people find it and join

Jason, 2026-11-01: groups need tools to secure members, as a marketing build for groups that's indirect: people find
them and join, rather than being pushed at. Every tool brings people to the group or the group to where people already
look. None messages people who didn't ask, and no one can buy a higher place or a wider reach (0007). Selling to the
group's own members is a different thing, and allowed ("Selling in groups", below).

**Being found where people already look**
- **Join from everywhere a group shows:** its card on place pages, chapter lists, search results, tag pages, "Talked
  about by" sections, the Community page and members' profiles (groups they choose to show) all carry Join (or Ask to
  join).
- **Events are the front door:** a group's public events (0134) show on the calendar, place pages and venue listings
  as "Hosted by <group>", with Join beside them.
- **Suggestions people asked for:** "Groups like this" (shared tags) on every group page; "Groups near you" on the
  Community page for the place a person is looking at; people who **follow** a tag, topic or place (0132) are told once
  when a new group or chapter with it starts near them.
- **Chapters reach the parent's members:** when a chapter opens, the parent's members whose own home place is near are
  told once. Only the system knows their home place; no one else sees it (0047).

**A page that makes people want to join**
- **A public welcome post:** the owner pins one post that non-members can read (what the group does, a recent outing,
  photos). Every other post stays members-only.
- **What a visitor sees:** about, charter and house rules, topic and tags, member count, how active it is ("active this
  week"), upcoming events, places it's about, and its chapters.

**Sharing it themselves** (the processes are in 5a–5d)
- **Invites:** members invite their connections; a business or chamber invites its own people in bulk (5a).
- **The group's short link, QR and flyer** (5b).
- **Joining by invite or invite code** (5c).
- **Share buttons, link previews and badges** that bring people back to the group's page (5d).

**Keeping the members it gets**
- **A welcome message** to each new member, written by the owner; **join questions** for approval groups.
- **Activity nudges to the Steward, not the members:** "No posts in 3 weeks: your group enters Notice in 9 days",
  with ideas (post an event, pin a welcome post).
- **How it's growing:** for the owner, page views, joins, and where joins came from (place page, search, event, invite,
  link), counted only, never who (as for outbound taps, 0058 §11).

**What it never does:** reach people with no tie to the inviter (a stranger only meets a group by finding it), sell
placement or reach, export member lists, or show where members live. A business-run group's outreach otherwise follows 0102 §4c.

#### Selling in groups

"Nothing can be bought" means **placement and reach**: no one pays to rank higher, show up more, or reach people who
didn't ask (0007). It doesn't stop a business selling to people who chose to join its group: 0102 §4c already says
joining a business's group is consent to its offers, events and product news inside it.

- **Publish once, share anywhere it's allowed** (Jason, 2026-11-01; the Marketplace's model, 0135 §2 and step 3):
  1. The item lives once, in the business's **catalog** (walled, with cost and supplier private).
  2. **Publish** puts it on the **Marketplace** and the business's **Shop** (0135), as a numbered version.
  3. **Share** posts a card of the *published* item, from the item or from the post box ("Add an item"), to:
     - the business's **own group** and its chapters;
     - its **feed**, which reaches people Linked to the business (0047, under their marketing settings);
     - **other groups whose charter allows businesses' items** (a Co-Op's group, say), by someone with a role on the
       business;
     - **outside social apps**, through the Share button (short link and preview card, 0136 §5d), bringing people back
       to the item's page.
  - **A share is a pointer, not a copy:** the card always shows the item as published now (price, photos, sold out).
    Publish changes and every card updates; **Unpublish** and every card shows "No longer offered", without the buy
    buttons. Only published items can be shared, and sharing doesn't count toward the catalog cap (0135 §7).
  - **What a buyer can do from the card** is what the item allows (0135 step 3): **Message** the business, **Make an
    offer** (if the business turned offers on), **Request a quote** (Basic and up), **Buy now** (Core and up, with
    checkout through its own gateway, 0135 step 4). Tickets work the same way (0134).
  - A **person's** Marketplace post (0135 step 1) shares the same way, to their feed and to groups whose charter allows
    members' items.
- **Only Traversence's own Marketplace and Shop:** a post can't link to another selling site (no outside shops or
  ticket sellers; 0134, Jason: never promote outside selling). The business's Shop on its own domain is ours, so it's
  fine.
- **Where it shows:** wherever it was shared, and nowhere else (0102 §4c: never pushed to people who didn't join, Link or
  follow, or made to look like our recommendations). It can't be boosted or pinned for pay.
- **No cap on product posts** (posts have no daily limit, 0137 §7); repeated identical posts are held, and members can
  mute a business's posts or report them as spam through the group's reports (0137 §5).
- **Counting what it earns:** a sale that starts from a group post is counted as coming from that group (counts only,
  never who), so the business can see what its community brings in.
- **In someone else's group** (an interest group, a Co-Op's group): selling follows that group's charter, **Selling**
  (0137 §3): not allowed, members' own Market items, or businesses' items too. The default for a person's group is
  not allowed; for a business's own group, its own items; for a Co-Op's group, its member businesses' items.

#### 5a. Invites, one at a time and in bulk

**Why invites are limited at all.** Not to slow anyone down: to stop one account (or a stolen one) from flooding
people with invitations. So the guard is on the pattern, not a daily count:
- **A member invites any of their connections** (Address Book, a circle, 0047), as many as they like, **once per
  person per group** (again after 30 days if not answered; Jason, 2026-11-01).
- The person invited can **turn off group invites**, from everyone or from that person. Someone who blocked the
  inviter never receives it.
- If many of one person's invites are reported or turned down, their invites pause and it goes to Standing (0079).

**A business or chamber inviting its own people.** A business (a chamber of commerce is a business listing too)
invites from the business portal → its group → **Invite your people**, choosing who:

| Who | How they're reached | Plans |
|---|---|---|
| **People Linked to the business** (0047) | In-app invitation, under each person's marketing settings (`Marketing`): their frequency, unsubscribe and "no marketing from anyone" are honored | Every plan |
| **Its customers who are members** (`Customers`) | In-app invitation, as above | Every plan |
| **Its customers who aren't members**, and **its own list** (a chamber's member businesses, a newsletter list, uploaded as a file) | **One invitation email per address per group**, in the business's name, with a one-click "no more from this business". The business confirms these people agreed to hear from it. Sending pauses if too many bounce or complain | **Core and up** (Jason, 2026-11-01), counted against the plan's monthly email allowance |

- **All at once:** the whole audience is sent in one go; email goes out in batches the mail system can carry, and
  the business sees sent, opened (where the mail app tells us) and joined, as counts.
- **Nothing leaks:** an uploaded list stays the business's (its CRM), is never shared with another business or used
  by Traversence for anything else, and can be deleted by the business.
- **The email allowance** is how many emails a business may send through Traversence in a month: invitations,
  campaigns and newsletters together. It exists because every email costs us to send, and every send puts our
  mail reputation (whether inboxes trust mail from us) on the line for everyone. Receipts, tickets and other
  transactional mail don't count; in-app invitations don't count. Strategic has 10,000 and Cornerstone 50,000 pooled
  (`commercial.md` §1.1); **Core needs a number** (proposed: 2,500 a month).
- On Free and Basic, the indirect tools (5b, 5d) and in-app invitations carry the rest; email invitations are a reason
  to move up to Core.

#### 5b. The short link, QR and flyer: making and using them

**What's live to build on (checked in the live mirror, 2026-11-01):** short links for any page (`/api/short.php`,
`js/short-link.js`, loaded on every page), the QR card (`tvQr`, `[data-qr]`, used by the engage bar) and GO Codes
(`QrIssue`). **None of it is on the group page yet:** it has no Share or QR button and no share card, and flyers,
invitations, invite codes and badges aren't built.

From the group page → **Share & invite** (owner and moderators; also in the Steward dashboard):
1. **Short link.** Made when the group is made: trvgo.us/{code} (`ShortCodes`, kind `page`, 0094), free, the same
   for everyone and never changed. A chapter has its own. Copy it anywhere.
2. **QR code.** "Make a QR" draws it on our server (`Qr`), pointing to the short link:
   - a plain black-on-white code uses one of the owner's **5 free GO Codes** (`QrIssue::FREE_TOTAL`);
   - a designed code (colors, the group's logo) is a GO Code at **$4.95**, through QuickBooks (0095);
   - a business-run group on **Core and up** gets **dynamic** codes it can repoint (to a chapter, an event), as its
     plan already gives.
3. **Flyer.** Pick a layout (letter flyer, half sheet, table tent, card), and it fills itself: group name, the
   welcome post's first lines and photo, place, next event, the QR and short link. The owner can edit every word.
   Download a print-ready PDF (or PNG for screens), or **order it printed** through our print catalog (`Products`,
   0101).
   - **Made by the owner** (the layout filled from the group's own details, then edited): **free** for every group.
     Filling a layout isn't AI.
   - **"Write it for me" (AI Assist):** Claude drafts the headline and a few lines from the group's about, charter,
     welcome post and next event, in a choice of tones, for the owner to edit. It follows the rule for every AI-model
     tool (`commercial.md`, "No AI model on free accounts"): it comes with a paid personal plan (Personal Plus or Pro)
     or a business on Basic and up, and draws on that plan's AI credits. On a free account the button shows greyed out
     with the reason. A draft costs us a few cents; there's no separate fee.
4. **Putting it out:** a short checklist on the page: venues the group uses (with their permission), its events
   (table tents, sign-in sheets), notice boards, newsletters.
5. **Keeping it working:** scans are counted per code (never who) and show in the group's growth counts as "QR". The
   short link always leads to the group page, so a printed flyer never goes stale; if the group ends, it shows that the
   group has closed and offers its parent or groups like it, never a dead end.

#### 5c. From invitation to member

How someone becomes a member depends on the group's join mode (0047) and how they arrived:

| How they arrive | Open group | Approval group |
|---|---|---|
| **Finds it** (place page, search, event, link, QR) | Join → member at once | Ask to join → answers the join questions → a moderator accepts or declines → member |
| **Invited by an ordinary member** | Accept → member at once | Accept → member at once **if the charter lets members' invitations skip approval**; otherwise a request marked "Invited by Maria" for a moderator |
| **Invited by the owner or a moderator**, or by the business (5a) | Accept → member at once | Accept → member at once **if the charter lets invitations skip approval**; otherwise a request marked with who invited them |
| **Uses an invite code** (a sign-up) | Member at once | Member at once **if the charter lets sign-ups by code skip approval** and the code is set that way; otherwise a request marked with the code |

**"Skipping approval" means skipping the wait in the request queue,** because someone the group trusts already said
yes when they sent the invitation or made the code. **Whether it's allowed is set in the charter** (Jason,
2026-11-01), shown to everyone before they join:
- **Invitations skip approval:** never; from the owner and moderators; or from any member.
- **Sign-ups by invite code skip approval:** yes or no.
The default is "never" and "no": every new member is approved, until the owner chooses otherwise. The person still accepts it themselves, still sees the charter first, and
joining is still their consent to be seen by members (0047).

**Invite codes:** made by the owner or moderators for a meeting, a class, a customer list. Each has: approved or not,
an end date, how many uses, and which chapter. A code shows as a link and a QR (as 5b), counts its uses, and can be
switched off at any time.

**Without an account:** the invitation or code opens the group's page; "Join" leads through sign-up and comes back to
finish joining. Joining needs an account, not Verification (that's for running a group).

#### 5d. Share buttons, link previews and badges

- **Share** on every group page, for anyone: copy the link, text, email, and the usual social apps; each carries the
  short link, so a share is counted (as "share") and arrives at the group page.
- **Link previews:** every group page has its own preview card (name, photo, place, member count, next event), so a
  link pasted into a social post or a message looks like the group.
- **Badges for the group's own site and social pages** (owner and moderators, Share & invite → Badges): "Join us on
  Traversence" buttons (light and dark, three sizes) with a copy-and-paste snippet, and a small **live card** (name,
  member count, next event, Join) to embed. Free for every group: each one brings people to Traversence.
- **The landing page is the group page.** A visitor arriving by a link, QR, badge or invitation sees what makes people
  join (the welcome post, charter, activity, events, chapters) with Join first; someone invited sees "Maria invited
  you".

## Data (sketch)

| Change | Fields |
|---|---|
| `community_groups` | `parent_id` (null for a parent or a stand-alone group), `chapter_name` (the part after the colon) |
| Group tags | in 0132's content tags, kind `group` |
| `community_group_places` | group, kind (place, listing, outdoor place, landmark), ref, note, added by, removed by owner |
| `community_groups` (growing) | welcome post id, welcome message, join questions, invite code, short link |
| `community_group_joins` | group, how the join came (place page, search, tag, event, invite, QR, share, badge, code), day: counts only |
| `community_groups` (charter) | invitations skip approval (never, moderators, any member), sign-ups by code skip approval |
| `community_group_invites` | group, from (member or business), to (member, or an email for 5a), approved or not, sent, answered |
| `community_invite_codes` | group or chapter, code, approved or not, ends, uses allowed, used, off |
| `community_invite_lists` | the business, the uploaded list (in its CRM), consent confirmed by, sends, bounces, complaints |

Migrations guarded with `information_schema` and safe to run twice. Group names stay unique site-wide; a chapter's full
name is unique by its parent and place.

## Admin

Social → Groups (when built, `community-scope.md`) shows chapters under their parent. Nothing else: hosts run their
groups (0102 §4b).

## Build order

1. Tags on groups, and tags in search and topic pages.
2. Places a group is about, with "Talked about by" on place, listing and outdoor-place pages.
3. Chapters: opening, joining (as an existing local group too), posting to all chapters, moderation levels, the
   parent's Chapters list.
4. Search by topic and place.
5. Growing a group: Join everywhere, the public welcome post, Share & invite (short link, QR, flyer, badges, link
   previews), invitations and invite codes, welcome message and join questions, "Groups like this" and "Groups near
   you", follower notices, the owner's growth counts; then business bulk invitations (in-app on every plan, email on
   Strategic and up).
6. Chapter endings and reactivation, with the 0103 build (the `lifecycle` table and its daily job), and the Steward's
   activity nudges.

Each step adds its FAQ entries (0057).

## Jason's answers (2026-11-01)

1. **Chapters are about geography, not size:** one per place per parent, inside the parent's place; no member minimum.
2. **No limit on chapters;** a chapter shows on its place's page from the start; inactivity handles ghost groups, and
   an inactive chapter can be brought back.
3. **Joining:** the parent's charter decides.
4. **Tags:** free words with suggestions as you type; **5 per group**, chapters included (Jason: "5 per"); 10
   places it's about.
5. **Invitations:** once per person per group, again after **30 days**; businesses invite in-app on every plan and
   by email from **Core and up**.
6. **Skipping approval:** the charter decides, for invitations and for sign-ups by code.
7. **Flyers:** made by the owner, free; "Write it for me" is AI Assist, on plans with AI, from their credits.

Still to confirm:
8. **Core's email allowance:** proposed 2,500 a month.
9. **Growing a group** (section 5) otherwise as written.
