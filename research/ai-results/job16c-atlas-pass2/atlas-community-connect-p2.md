```markdown
# Platform atlas: community-connect

*Draft for human review. Sources: the charter, brand.md, prd.md, site-audit/pages.md, decisions 0047, 0102, 0136, 0137 and 0172, and the code files listed in §b. Code paths are cited as the sync mirror names them (`sync-app/lib/…` = `app/lib/…`; `sync-storage/community/…` = `website/community/…`).*

*Partly shown: `Community.php` is cut at line 536, `group.php` at line 349, and 0137 and 0172 are cut partway. The following are not shown: `/user/api/community.php`, `hub/index.php`, `Notices.php`, `Notify.php`, `Reports.php`, `People.php`, `Links.php` and `/api/group-act.php`.*

*Three attached files are not part of community groups, despite their names:*
- *`SupportGroups.php` groups directory categories for support resources (`SupportGroups.php:5-21`).*
- *`SystemTags.php` holds QuickBooks references (`SystemTags.php:2-5`).*
- *`Groups.php` holds private Address Book circles (`Groups.php:7-14`). It is covered below as part of Connect.*

## (a) What each person can do here

Visitors reach this area through the mobile menu label **"Social"**, which leads into the Community ("Connect") Track (brand.md §3a).

### Pages and routes

| Route | Status (pages.md) | What it is |
|---|---|---|
| `/community/` | Live | Find, filter and start groups |
| `/community/group.php?id=` | Live | One group's page |
| `/join.php?i=` | Live | Invite link to sign up |
| `/user/dashboard.php` | Live | Pulse, Address Book, links |
| `/user/profile.php?id=` | Live | A person's public profile |
| `/admin/reports.php` | Live | Staff reports queue |
| `/api/tags.php` | Live | The @ picker |
| `/user/api/community.php` | Live (code not shown) | The groups API |
| `/api/group-act.php` | Listed **Coming** (release comms-all) | Buttons inside group messages |

There is a conflict on `/api/group-act.php`. pages.md lists it as Coming, but 0172's status says steps 3–7 are live as #135. `GroupNotices.php:24` already points its buttons at it. Someone needs to reconcile these.

### Resident (Get Local)
- **Find local groups.** `/community/?place=` lists groups "Here", then "Nearby", then groups that talk about places nearby, then "Anywhere" (`index.php:120`, `Community.php:252-263`).
- **See groups on a place's page.** Place pages list local groups (0047 Progress). `hub/index.php` is not shown.
- **Get told about new local groups.** Following a place brings a notice when a new local group starts there (`Community.php:364-375`).

### Traveler (Let's Explore)
- **Find interest groups anywhere.** Searching by topic or tag returns interest groups marked "Anywhere" (`Community.php:223`, `:255`).
- **See who talks about a place.** Groups can name up to 10 places they're about. These show on listings, landmarks and outdoor places under **Featured in** (`ContentTags.php:50-52`, `GroupGrowth.php:251-268`) and on towns under **Talked about by** (`GroupGrowth.php:271-279`).
- **Read before joining.** Anyone can read a group's pinned welcome post (`Community.php:479-490`, `group.php:91-96`).

### Member (signed in)
- **Join a group.**
  - Join, or Ask to join, sits top right on the group page (`group.php:55-59`).
  - Joining opens the charter first, with **Agree and join** (`group.php:165-169`).
  - Joining records the charter version agreed to (`Community.php:415-417`, `:443`).
- **Start a group.**
  - A member can run up to 5 groups (`Community.php:44`, `:331-333`).
  - Starting one needs Verification (`Community.php:315`).
  - The charter's purpose is required (`GroupCharters.php:141`).
- **Post in a group.**
  - Posts are for members only and can carry @place, @topic and @person tags (`group.php:236-240`).
  - The same words twice in a day are held (0137 Step 1).
- **Report** a post or a group (`group.php:232`, `:244`).
- **Leave** a group, with an offer to leave its chapters too (`group.php:342-347`).
- **Hide own name** from the member list (`Community.php:505-509`).
- **Invite connections.**
  - Once per person per group, again after 30 days (`GroupGrowth.php:23`, `:302-330`).
  - Invitations pause after many are declined (`:308-310`).
- **Use invite codes** (`Community.php:427-432`).
- **Run chapters.** A member can open or request a chapter (`GroupChapters.php:92-146`).
- **Manage group posts in Pulse.**
  - New group posts arrive in Pulse, gathered into one unread message per group (`GroupNotices.php:4-7`, `:102`).
  - **Mute its posts** is a button inside the message; mentions still arrive when muted (`:96`, `:179-182`).
- **Private circles in the Address Book.** Up to 30 circles of 50 connections each, with a group conversation and card sharing (`Groups.php:17-18`, `:139-166`).

### Business (a listing's team)
- **One group per business.** The listing owns it, and it doesn't use up anyone's 5 (`Community.php:322-329`; 0137 §1).
- **Team roles run it** (`Community.php:47-50`):

  | Business role | Part in the group |
  |---|---|
  | Owner | Runs it; only one who can hide it |
  | Manager | Runs it |
  | Creator | Moderates; posts as the business |
  | Support | Moderates |
  | Sales | Posts as the business's rep |

- **Post as the business**, or as "Name · Sales, \<business\>" (`group.php:238-239`).
- **Starting charter for a business group** sets selling to its own items and fills in "publishes" (`GroupCharters.php:64-71`).
- **When a group names the listing,** its Owner and Managers are told once. They can take it off, and it can't be re-added without them (`GroupGrowth.php:215-217`, `:226-241`).
- **Moderation duties** reach the business's Pulse (`GroupNotices.php:55-59`, `:66-71`).
- **Join counts** show where joins came from, as counts only, along with page views (`GroupGrowth.php:405-423`).

## (b) How it works underneath

### Front end
- **`/community/` page (`index.php`)**
  - Calls `/user/api/community.php` (`:98`, `:164`).
  - Sort options are "Most active" (joins in the last 7 days, then members) and "Nearest first" (`:100-102`, `:134-137`).
  - Shows popular tags (`:176`) and the start form with the charter fields (`:72-93`, `:200-211`).
- **Group page (`group.php`)**
  - Server-drawn top: hero, kind/topic/tag pills that search, engage bar without likes or comments, and "at a glance" (`:69-90`).
  - Counts views (`:67`).
  - Share-card meta tags (`:61-64`).
  - The JS draws the charter card and editor (`:148-209`), posts, members, the reports queue (`:261-270`) and "Ask Traversence for help" (`:271-273`).

### Libraries
- **`Community.php`**
  - `directory()`: search across name, about and tags; topic filter; near-ranking by place hierarchy (`:205-267`, `nearKeys` `:270-295`).
  - `join()`: the chapter rule, the charter version check, invitation and code skips, join answers, counting, welcome message, the parent cascade, and a moderator notice (`:403-453`).
  - `can()`: permission checks, with parent moderators able to moderate in chapters (`:160-170`).
- **`GroupCharters.php`**
  - Fixed ground rules (`:18-24`) and the inactivity rule (`:25`).
  - Starting templates by kind (`:53-82`).
  - Numbered versions. Transfer, successor and opening the member list wait 14 days (`:15`, `:41`, `:170-204`).
  - The "What you're agreeing to" summary (`:234-247`).
- **`GroupChapters.php`**
  - Which groups can have chapters (`:48-51`).
  - One chapter per place or per topic, inside the parent's region (`:104-112`).
  - The 5-group cap applies to a person's chapters (`:117-120`).
  - Chapters inherit the parent's charter and tags (`:122-136`).
  - Offer and accept (`:217-269`), 14-day leave (`:272-298`), join cascade (`:304-311`).
- **`GroupGrowth.php`**
  - Tag normalising (`:48-56`).
  - Tags that may name sensitive sites are held for staff review (`:92`).
  - "Groups like this" (`:121-139`).
  - The places picker, which respects `Landmarks::visibleSql` and `SENSITIVE` (`:148-194`).
  - Invite codes (`:354-400`).
- **`GroupNotices.php`**
  - Post, mention, join and report messages through `Notify::send` (`:77-137`).
  - Message bodies with Accept/Decline and Keep/Remove buttons (`:150-249`, `:254-293`).
  - Reports of danger (`unsafe`) skip the group and go to Traversence Safety (`:123`).
- **`Tags.php`**
  - @tag tokens (`:23`).
  - For group posts, it captures only counts plus a keyed one-way hash per person (`:97-126`).
  - A new tag used by 3 different people becomes a lead for staff review (`:22`, `:128-140`).
- **`ContentTags.php`**
  - Groups are a "Featured in" source (`:22`, `:50-52`).
  - Mentions in group posts never appear in Featured in (`:175-195`).
  - A member's piece reaches a business's followers only if they also follow its author or its place (`:140`).

### Tables seen in the code
`community_groups`, `community_group_members`, `community_posts`, `community_charters`, `community_group_tags`, `community_group_places`, `community_group_invites`, `community_invite_codes`, `community_group_joins`, `community_chapter_offers`, `content_reports`, `user_links`, `tag_counts`, `tag_people`, `tag_leads`, `content_tags`, `people_groups`.

## (c) Decisions

| Decision | What it rules |
|---|---|
| **0047** | Link as "follow"; feeds built from links; the Address Book (connections, contact cards, circles, message controls); public groups, with joining as consent to be seen by members; businesses see counts, not names. Its "3 reports hide until an admin decides" is superseded by 0137 §5. |
| **0102** | RBAC. §4b: group roles. §4c: business groups, the sales rep, contact outside the group only when the member starts it or Links, and guardrails. Its "groups per business set by plan" conflicts with 0137 §1 (one group per business), and 0137 rules. |
| **0136** | Tags (5), chapters, places a group is about (10), search by topic and place, growing a group, selling in groups. Internal inconsistency: its build order says email invites from "Strategic and up", but its §5a and Jason's answers say "Core and up". |
| **0137** | Group ownership (person, listing or Nexus), chapters by place or topic, the charter, the group page layout, group-led moderation with Traversence by case, notices sent once, no caps on posts or events, email through SES (Core gets 500 a month), flyer orders. |
| **0172** | Pulse as the inbox. A group is a channel. Group posts, joins and reports arrive as messages with actions inside them (§2.8). |

The code also cites decisions whose texts are not shown: 0007 (no pay for rank), 0010, 0051, 0052, 0058, 0062, 0066, 0079, 0103, 0132, 0134.

## (d) Live vs coming

### Live (built per decision logs, and present in code)
- Groups, places and topics (0047 Progress).
- Charters and business-owned groups (0137 Step 1).
- Group page layout (Step 2).
- Group-led reports and "Group help" cases (Step 3).
- Notices sent once (Step 4).
- SES sending side, in **mock mode until AWS production access** (Step 5).
- From 0136: tags, places, chapters, topic and place search, welcome post and message, invitations, codes, join counts (0136 "Built").

### Not yet built (per the docs)
- **Chapter lifecycle:** chapter endings and reactivation, Steward nudges, the 0103 inactivity job.
- **Sharing tools:** flyers and badges.
- **Outreach:** bulk business invitations (needs the bulk SES account), "Groups near you", notices to tag followers.
- **Moderation:** appeals, warnings and muting, blocking a removed member from rejoining, timed agent access (0137 Step 3).
- **Group types:** Nexus- and Co-Op-owned groups.
- **Media:** group cover photos (`group.php:68`, "coming").
- **Group events:** 0137 says the events store "isn't built yet".
- **Post replies:** group posts have no replies or comments (`GroupNotices.php:16`).
- **Mail:** `via` reply forwarding and business email allowances in `Billing`.

### Not shown either way
- Selling cards in groups (0136 §5).
- Sales attribution from group posts.
- Flyer printing orders (0137 §9).
- "Connect your domain".

### Doc drift
prd.md still files "Communities & Groups" as Tier 2.7, "not yet schedulable, no schema". The code and 0136/0137 show it built. prd.md needs updating.

## (e) Charter promises, economics, gaps

### Immersion and connection
The charter's Community ("Connect") Track is "the living, streaming layer binding dialogue and gatherings to place" (charter, Tri-Track).

**Delivered:**
- Groups bind to places through `place_key`.
- Chapters localise topic groups.
- "Talked about by" puts groups on place pages.

**Gaps:**
- brand.md §3's third layer, "Gatherings (real-time chat streams)", is not shown in any attached code.
- Group events are unbuilt.

### True locality
The charter promises "local relevance that feels grounded" and natural geographic clustering.

**Delivered:**
- Nearness follows the hub → geo-hub → cluster hierarchy (`Community.php:270-295`).
- Chapters must sit inside the parent's region (`GroupChapters.php:104-105`).

**Gaps:**
- Ranking is by hierarchy level, not distance.
- 0137 §