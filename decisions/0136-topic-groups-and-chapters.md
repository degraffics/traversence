# ADR 0136: Topic Groups, Chapters, and the Places a Group Is About

**Status:** Proposed (2026-11-01), from Jason's question: "Tie groups to the geography is good, but what if the topic
is the group's focus, say traveling the world or cooking with Crisco?" Design; not built. Builds on 0047 (community
groups), 0102 §4b–4c (group roles, business groups), 0103 (charters, transfer, inactivity), 0132 (tags and links), 0053
(search), 0066 (nations' land), 0134 (events). Choices marked **(Jason)** wait for him; they're listed at the end.

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
  moderators with the tag picker (0132). New tags are allowed: people describe their group in their own words.
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
- The parent's owner opens a chapter, or approves a member's request to start one. The parent needs **at least 25
  active members (Jason)**, and can have **up to 50 chapters (Jason)**.
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
| Topic and tags (it can add 2 tags of its own) | Place, members, posts, moderators, lead |
| The charter's purpose and house rules (it can add local rules that don't contradict them) | Join mode (open or approval), its own events (0134) |
| The charter's transfer terms (0103 §2a), applied to the chapter | Its own inactivity clock (0103 §4) |

**Members**
- Joining a chapter **also joins the parent (Jason)**, so the whole group grows; the person can leave the parent and
  stay in the chapter. Leaving the parent offers to leave its chapters too.
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
- A chapter that goes inactive follows the stages on its own; its members are offered the parent.
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

## Data (sketch)

| Change | Fields |
|---|---|
| `community_groups` | `parent_id` (null for a parent or a stand-alone group), `chapter_name` (the part after the colon) |
| Group tags | in 0132's content tags, kind `group` |
| `community_group_places` | group, kind (place, listing, outdoor place, landmark), ref, note, added by, removed by owner |

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
5. Chapter endings, with the 0103 build (the `lifecycle` table and its daily job).

Each step adds its FAQ entries (0057).

## For Jason

1. A parent needs **25 active members** to open chapters?
2. **Up to 50 chapters** a parent?
3. Joining a chapter **also joins the parent**?
4. Up to **5 tags** a group (2 more for a chapter) and **10 places** it's about: right sizes?
