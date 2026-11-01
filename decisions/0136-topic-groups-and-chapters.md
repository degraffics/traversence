# ADR 0136: Topic Groups, Chapters, and the Places a Group Is About

**Status:** Proposed (2026-11-01), from Jason's question: "Tie groups to the geography is good, but what if the topic
is the group's focus, say traveling the world or cooking with Crisco?" Design; not built. Builds on 0047 (community
groups), 0102 §4b–4c (group roles, business groups), 0103 (charters, transfer, inactivity), 0132 (tags and links), 0053
(search), 0066 (nations' land), 0134 (events). Jason's answers are at the end; section 5 (Growing a
group) is new, for his review before this is accepted.

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
- **Chapters are about geography, not size** (Jason, 2026-11-01): no member count to open one and no limit on how many
  a parent has. Instead:
  - **one chapter per place per parent** (one *World Travelers: White Mountains*, never two);
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

**Business-run groups (0102 §4c).** A business's group can have a chapter for each of its locations (a Location Group,
0036, or a Cornerstone business's units). Its chapters count toward the business's group allowance by plan; the
business's roles run them as they run the parent.

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
look. None messages people who didn't ask, and none can be bought (0007).

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

**Sharing it themselves**
- **Invite your connections:** members invite people from their Address Book or a circle (0047). An invite is from
  the member, to someone they're connected to, a few a day; it can't reach strangers.
- **A share link and QR:** each group has a short link (trvgo.us); a printable flyer with a QR uses one of the owner's
  GO Codes (5 free with an account, `commercial.md` §1.3).
- **Invite codes** for approval groups: someone with the code joins without waiting.
- **A badge or link** for the group's own website or social pages, pointing back to its Traversence page.

**Keeping the members it gets**
- **A welcome message** to each new member, written by the owner; **join questions** for approval groups.
- **Activity nudges to the Steward, not the members:** "No posts in 3 weeks: your group enters Notice in 9 days",
  with ideas (post an event, pin a welcome post).
- **How it's growing:** for the owner, page views, joins, and where joins came from (place page, search, event, invite,
  link), counted only, never who (as for outbound taps, 0058 §11).

**What it never does:** message non-members, sell placement, export member lists, or show where members live. A
business-run group's outreach follows 0102 §4c, and its campaigns come from its plan (Strategic and up).

## Data (sketch)

| Change | Fields |
|---|---|
| `community_groups` | `parent_id` (null for a parent or a stand-alone group), `chapter_name` (the part after the colon) |
| Group tags | in 0132's content tags, kind `group` |
| `community_group_places` | group, kind (place, listing, outdoor place, landmark), ref, note, added by, removed by owner |
| `community_groups` (growing) | welcome post id, welcome message, join questions, invite code, short link |
| `community_group_joins` | group, how the join came (place page, search, tag, event, invite, link, code), day: counts only |

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
5. Growing a group: Join everywhere, the public welcome post, invites, share link and flyer, invite codes, welcome
   message and join questions, "Groups like this" and "Groups near you", follower notices, the owner's growth counts.
6. Chapter endings and reactivation, with the 0103 build (the `lifecycle` table and its daily job), and the Steward's
   activity nudges.

Each step adds its FAQ entries (0057).

## Jason's answers (2026-11-01)

1. **Chapters are about geography, not size:** one per place per parent, inside the parent's place; no member minimum.
2. **No limit on chapters;** a chapter shows on its place's page from the start; inactivity handles ghost groups, and
   an inactive chapter can be brought back.
3. **Joining:** the parent's charter decides.
4. **Tags:** free words with suggestions as you type; **5 per group**, chapters included (Jason: "5 per"); 10 places it's about.
5. **Growing a group** (section 5): indirect tools so people find groups and join.
