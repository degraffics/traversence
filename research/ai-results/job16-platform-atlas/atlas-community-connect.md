# Platform atlas: community-connect

*`research/platform-atlas/community-connect.md`. Draft for human review. Sources are only the attached files. Several are capped (noted where it matters). Anything not in them is marked **not shown**.*

**Coverage of the attached code.** Shown in full: `GroupCharters.php`, `GroupGrowth.php`, `GroupChapters.php`, `GroupNotices.php`, `ContentTags.php`, `Tags.php`, `Groups.php`, `community/index.php`.

Partly shown:
- `Community.php` is cut at about line 536, partway through `leave()`. Posting, members, reports, `decideReport` and `reportFiled` are **not shown**.
- `community/group.php` is cut at about line 349. `growUI()`, invites/codes UI and chapters UI are **not shown**.
- Decisions 0137 and 0172 are both truncated.

Not shown at all: `/user/api/community.php`, `/api/group-act.php`, `join.php`, `Notices.php`, `Notify.php`, `Reports.php`, `People.php`, `Links.php`, `Team.php` and `hub/index.php`.

Two attached files are **not part of this area**:
- `SupportGroups.php` is the "Support & wellness" *directory category* sets used by place pages and cluster tools (`SupportGroups.php:5-21`). It is not community groups.
- `SystemTags.php` holds QuickBooks reference tags (`SystemTags.php:2-18`).

---

## (a) What each person can do, page by page

### Routes in this area

| Route | Status in `site-audit/pages.md` |
|---|---|
| `/community/` | Live (0047) |
| `/community/group.php?id=` | Live (0047) |
| `/join.php?i=` | Live (invite link) |
| `/user/dashboard.php` (Address Book, links) | Live |
| `/user/profile.php?id=` | Live |
| `/user/api/*` (`people`, `links`, `community`, `marketing`, `business-messages`…) | Live |
| `/api/tags.php` (the @ picker) | Live |
| `/api/engage.php` | Live |
| `/admin/reports.php` | Live |
| `/api/group-act.php` | "Coming (release comms-all)" (see (d) for the conflict) |

### Resident (local)

- **Browse groups by place.** `/community/?place=` lists groups "Here", then "Nearby", then "Talks about places nearby", then "Anywhere" (`community/index.php:120`, `Community.php:250-257`).
  - Sorting is "Nearest first" or "Most active" (`index.php:152-156`).
- **Groups on place pages.** Local groups also show on their place's page (`index.php:147`). The place-page rendering itself (`hub/index.php`) is **not shown**.
- **Notices about places you follow.** Following (Linking) a place brings a notice when a new local group or chapter starts there (`Community.php:364-375`, `GroupChapters.php:142-144`).

### Traveler

- **Find groups anywhere.** Interest groups are marked "Anywhere" (`Community.php:255`).
- **"Featured in."** A listing, landmark or outdoor place shows the groups that name it (`ContentTags.php:50-52`, `GroupGrowth.php:252-268`).
- **"Talked about by."** Groups about a place are listed by `GroupGrowth::about()` (`GroupGrowth.php:271-279`).
- No traveler-specific group route is shown.

### Member (signed in)

**Find and join**
- Search groups by name, topic or tag (`index.php:62-68`).
- "Join group" on a card opens the group at its charter, so joining is consent to it (`index.php:130-132`).
- On the group page (`group.php`):
  - Join, Ask to join, Joined, or "Your team's" sits top right (`group.php:56-59`).
  - Kind, topic and tag pills search when tapped (`:74-81, 98`).
  - The engage bar shows views, Share and QR, with no likes or comments (`:83`).
  - "At a glance" shows members, posts this week, join mode and founding date (`:84-89`).
  - A pinned welcome post is readable by anyone (`:91-96`).
- The charter card covers "What you're agreeing to", the full charter, versions, and "Got it" after a change (`:148-188`).
- A member can hide their own name from the member list (`Community.php:505-509`).

**Take part**
- Posts are members only (`group.php:236-250`). Typing @ tags a place, topic or person (`Tags.php:8-18`).
- Report a post or a group (`group.php:232, 244`).
- Leave a group, optionally with its chapters (`:342-347`).
- Group posts reach your Pulse. You can mute a group, and mentions of you still arrive (`GroupNotices.php:4-7, 94-98, 278-282`).

**Invite**
- Invite your connections, once per group per 30 days (`GroupGrowth.php:302-330`).
- Join with an invite code (`Community.php:427-432`).

**Start and run**
- Starting a group needs Verification (`Community.php:315`, `index.php:89`). One person can run up to 5 groups (`Community.php:44, 331-333`).
- A charter purpose is required (`GroupCharters.php:141`).
- Owners can:
  - edit the charter (`group.php:189-208`);
  - pick moderators (`:255`);
  - set tags (max 5) and places the group is about (max 10) (`GroupGrowth.php:21-22`);
  - open chapters or approve requests for them (`GroupChapters.php:92-164`).
- Owners and moderators:
  - decide reports in "Reports to decide" (`group.php:261-270`);
  - can "Ask Traversence for help" (`:271-273`).

**Private circles**
- Address Book Groups are private circles of your own connections (`Groups.php:7-18, 93-102`).

### Business (listing team)

- **One group per business.** It is started by the Owner or a Manager and doesn't count toward anyone's 5 (`Community.php:322-329`).
- **Team roles map to group roles.** Owner and Manager run it, Creator moderates, Support moderates, Sales is the rep (`Community.php:47-50`).
- **Post as the business** (Owner, Manager, Creator) or as "Yourself · Sales, <business>" (`group.php:238-239`).
- **Being listed by other groups.**
  - The listing's Owner and Managers are told once when a group lists the business (`GroupGrowth.php:226-231`).
  - They can take it off, and it can't be re-added without them (`GroupGrowth.php:217, 235-241`).
- **Duties go to the business's Pulse.** Join requests and reports for its group arrive there (`GroupNotices.php:14-15, 55-59, 66-71`).
- **Growth counts.** Joins by source and page views are counts only (`GroupGrowth.php:414-423`).

---

## (b) How it works underneath

### Group core: `app/lib/Community.php`

- **Topics:** 11 fixed topics (`:41-43`).
- **Permissions:** `can()` checks the role table (`:49-50`). A chapter's parent can moderate in the chapter (`:164-168`).
- **`directory()` ranking:**
  - With a place, groups are ordered by distance bucket (`nearKeys`, `:270-295`: 0 here, 1 area, 2 neighbors, 3 region), then by members.
  - Without one, by members.
  - "Never by payment (0007)" (`:203, 263`).
  - "+N this week" is joins in 7 days, as a count (`:245-247`).
- **`create()`** (`:311-361`): Standing guard (`:313`), verification, the one-business-group check, the 5-group check, then the charter saved in the same transaction.
- **`join()`** (`:403-453`):
  - applies the chapter rule (`:410-414`);
  - requires that the charter version read is still current (`:417`);
  - lets an invitation or code skip approval only if the charter allows it (`:421-432`);
  - stores join answers (`:435-439`), counts the source (`:447`) and notifies moderators (`:451`).
- **Charter changes:** `saveCharter()` notifies members once per version (`:512-528`).

### Charters: `app/lib/GroupCharters.php`

- Versioned rows. Five fixed ground rules (`:18-24`) and a fixed inactivity text (`:25`).
- Starting templates per kind (`:53-82`). The support-group template makes the member list moderators-only (`:76`).
- Transfer-term changes, and opening the member list, wait 14 days (`:15, 41, 187-199`).

### Growth: `app/lib/GroupGrowth.php`

- **Tags:** normalized (`:48-56`). A tag matching `Landmarks::SENSITIVE` is held for staff (`:92`).
- **Discovery:** popular tags (`:111-118`) and "Groups like this" (`:121-139`).
- **Places a group is about** (`:148-268`), mirrored into `content_tags` as source `group:<id>` (`:252-268`).
- **Invitations:**
  - Pause rule: 10 or more declines in 30 days, and at least half declined (`:28, 308-310`).
  - Invite codes use an unambiguous alphabet (`:29, 354-400`).
- **Join counts by source** (`:25-26, 405-411`).

### Chapters: `app/lib/GroupChapters.php`

- **Who can be a parent:** an active group with no place or a hub place, one level deep (`:48-51`).
- **One per place or per topic, per parent** (`:110-112`). A place chapter must sit inside the parent's hub (`:104-105`).
- **What a chapter inherits:** the parent's charter (`:122-126`) and tags (`:136`).
- **Lifecycle:** existing groups can join by offer and answer (`:217-269`); a chapter leaves after 14 days' notice (`:272-298`).
- **Joining the parent:** `afterJoin` cascades into the parent when its rule is `both` (`:304-311`).

### Messages to members: `app/lib/GroupNotices.php`

- Message kinds: `group.post` (one unread message per group thread), `group.mention`, `group.join`, `group.report` (`:4-16, 77-137`).
- Each message carries its own buttons (Accept, Decline, Keep, Remove, Mute, Report), acting through `Community` (`:254-293`).
- `unsafe` reports bypass the group and go to Safety (`:123`).

### Tags and feeds

- **`app/lib/ContentTags.php`:**
  - `featuredIn()` (`:225-239`);
  - follow-based feeds (`:121-172`), where a member's piece reaches a business's followers only via author or place follows (`:140`);
  - group-post mentions (`:179-195`).
- **`app/lib/Tags.php`** keeps @tag counts with an HMAC per-person hash and no user id (`:11-18, 101-126`). A new tag used by 3 or more people becomes a review lead (`:22, 128-140`).

### UI

- `community/index.php`: client-rendered from `/user/api/community.php`, which is **not shown** (`:98, 164`).
- `community/group.php`: server-drawn top (`:41-96`), then JS-rendered body (`:133-282`).

### Data

The column names are inferred from the queries shown: `community_groups`, `community_group_members`, `community_charters`, `community_group_tags`, `community_group_places`, `community_group_invites`, `community_invite_codes`, `community_group_joins`, `community_chapter_offers`, `user_links`, `content_tags`, `tag_counts`, `tag_people`, `tag_leads`, `people_groups`. The migrations are **not shown**.

---

## (c) Decisions

| ADR | What it r