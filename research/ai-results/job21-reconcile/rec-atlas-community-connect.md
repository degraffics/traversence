# Platform atlas: community-connect (merged from pass 1 and pass 2)

*Draft for human review. Sources are only the files attached to each pass. Citations are each pass's own. Where both passes cite the same lines, the citation appears once. Where they differ, both are given as P1 and P2. Paths follow `app/lib/…` and `website/community/…`. P2 notes that its sync mirror names these `sync-app/lib/…` and `sync-storage/community/…`.*

**Both passes are truncated.**
- **Pass 1** ends at the start of (c), mid-table ("| ADR | What it r"). It has no content for (c), (d) or (e).
- **Pass 2** ends partway through (e), at "0137 §".
- So (c), (d) and most of (e) below are **single-pass (P2)**.

**Sources.**
- P2 lists the charter, brand.md, prd.md, site-audit/pages.md, decisions 0047, 0102, 0136, 0137 and 0172, and the code files.
- P1 names pages.md, 0137 and 0172 among its sources.

**Coverage of code**

Partly shown (both passes):
- `Community.php` is cut at line 536. P1 says "about line 536", partway through `leave()`. P1 adds that posting, members, reports, `decideReport` and `reportFiled` are not shown (single-pass).
- `community/group.php` is cut at about line 349. P1 adds that `growUI()`, the invites/codes UI and the chapters UI are not shown (single-pass).
- 0137 and 0172 are truncated.

Not shown:
- **Both passes:** `/user/api/community.php`, `/api/group-act.php`, `Notices.php`, `Notify.php`, `Reports.php`, `People.php`, `Links.php`, `hub/index.php`.
- **Single-pass (P1):** `join.php`, `Team.php`.

Shown in full (single-pass, P1): `GroupCharters.php`, `GroupGrowth.php`, `GroupChapters.php`, `GroupNotices.php`, `ContentTags.php`, `Tags.php`, `Groups.php`, `community/index.php`.

**Files that are not community groups**
- `SupportGroups.php` is the "Support & wellness" directory-category sets (`SupportGroups.php:5-21`).
- `SystemTags.php` holds QuickBooks reference tags. Passes differ on lines: P1 `SystemTags.php:2-18`, P2 `:2-5`.
- `Groups.php`: passes differ in framing.
  - P1 treats it as in-area and fully shown.
  - P2 says it is not community groups but private Address Book circles (`Groups.php:7-14`), covered here as part of Connect.
  - Both describe it under Member as private circles.

---

## (a) What each person can do, page by page

Single-pass (P2): visitors reach this area through the mobile menu label **"Social"**, which leads into the Community ("Connect") Track (brand.md §3a).

### Routes

| Route | Status (pages.md) | Notes |
|---|---|---|
| `/community/` | Live | P1 adds "(0047)". P2: find, filter, start groups |
| `/community/group.php?id=` | Live | One group's page |
| `/join.php?i=` | Live | Invite link |
| `/user/dashboard.php` | Live | Address Book and links. P2 adds Pulse |
| `/user/profile.php?id=` | Live | Public profile |
| `/admin/reports.php` | Live | P2: staff reports queue |
| `/api/tags.php` | Live | The @ picker |
| `/user/api/*` | Live | P1 lists `people`, `links`, `community`, `marketing`, `business-messages`…. P2 lists only `/user/api/community.php` (code not shown) |
| `/api/engage.php` | Live | **Single-pass (P1)** |
| `/api/group-act.php` | Listed "Coming (release comms-all)" | Conflict; see below |

**`/api/group-act.php` conflict (both passes flag it).**
- P1 notes the conflict and points to its (d), which is truncated.
- P2 gives the detail: pages.md says Coming, but 0172's status says steps 3–7 are live as #135, and `GroupNotices.php:24` already points its buttons at it. P2 says this needs reconciling.

### Resident (local)

- **Browse groups by place (both).** `/community/?place=` lists groups "Here", then "Nearby", then those that talk about places nearby, then "Anywhere".
  - Both cite `index.php:120`.
  - Passes differ on the `Community.php` lines: P1 `:250-257`, P2 `:252-263`.
- **Sort options (both): "Nearest first" and "Most active".**
  - P1 places this here (`index.php:152-156`).
  - P2 places it in (b) (`index.php:100-102, :134-137`) and defines Most active as joins in the last 7 days, then members.
  - Passes differ on the line cites.
- **Groups on place pages (both).** Rendering in `hub/index.php` is not shown.
  - P1 cites `index.php:147`.
  - P2 cites 0047 Progress.
- **Notice when something new starts at a followed place (both).** Covers a new local group (`Community.php:364-375`).
  - P1 also includes new chapters (`GroupChapters.php:142-144`). This part is single-pass.

### Traveler

- **Find interest groups anywhere (both).** They are marked "Anywhere".
  - P1 cites `Community.php:255`.
  - P2 cites `:223, :255` and adds that this is found by searching topic or tag.
- **"Featured in" (both).** Listings, landmarks and outdoor places show the groups that name them.
  - `ContentTags.php:50-52`.
  - Passes differ on lines: `GroupGrowth.php` P1 `:252-268`, P2 `:251-268`.
  - P2 adds that a group can name up to 10 places. P1 gives the cap at `GroupGrowth.php:21-22`.
- **"Talked about by" (both).** `GroupGrowth.php:271-279`. P2 says this shows on towns.
- **Read before joining (both).** The pinned welcome post is readable by anyone (`group.php:91-96`). P2 also cites `Community.php:479-490`.
- **No traveler-specific group route is shown.** Single-pass (P1).

### Member (signed in)

**Find and join**
- **Search groups by name, topic or tag (both).**
  - P1 cites `index.php:62-68`.
  - P2 cites the search in `directory()` across name, about and tags (`Community.php:205-267`).
- **Join control top right (both).**
  - P1 lists Join / Ask to join / Joined / "Your team's" (`group.php:56-59`).
  - P2 lists Join / Ask to join (`:55-59`).
  - Passes differ slightly on lines and states.
- **Joining goes through the charter (both).**
  - P1: "Join group" on a card opens the group at its charter, so joining is consent (`index.php:130-132`).
  - P2: the charter opens first with **Agree and join** (`group.php:165-169`), and the agreed version is recorded (`Community.php:415-417, :443`).
- **Group page top.** Kind/topic/tag pills that search, an engage bar with views, Share and QR but no likes or comments, and "At a glance" (members, posts this week, join mode, founding date).
  - P1 cites `group.php:74-81, 98; :83; :84-89`.
  - P2 cites `:69-90`.
- **Charter card (both).**
  - P1 `group.php:148-188`: "What you're agreeing to", full text, versions, and "Got it" after a change.
  - P2 `:148-209`, including the editor.
- **Hide your own name** from the member list (both): `Community.php:505-509`.

**Take part**
- **Posts are members only, with @place, @topic and @person tags (both).**
  - P1 cites `group.php:236-250` and `Tags.php:8-18`.
  - P2 cites `group.php:236-240`.
- **Duplicate posts held.** The same words twice in a day are held (0137 Step 1). Single-pass (P2).
- **Report a post or group** (both): `group.php:232, 244`.
- **Leave a group**, optionally with its chapters (both): `group.php:342-347`.
- **Group posts in Pulse (both).**
  - Posts arrive in Pulse (`GroupNotices.php:4-7`). P2 adds they are gathered into one unread message per group (`:102`).
  - You can mute a group, and mentions still arrive. P1 cites `:94-98, 278-282`. P2 cites `:96, :179-182`.

**Invite**
- **Invite connections (both).** Passes differ on wording:
  - P1: once per group per 30 days (`GroupGrowth.php:302-330`).
  - P2: once per person per group, again after 30 days (`:23, :302-330`).
- **Invitations pause after many declines** (`:308-310`). P1 gives the threshold in (b).
- **Join with an invite code** (both): `Community.php:427-432`.

**Start and run**
- **Starting a group needs Verification** (both): `Community.php:315`. P1 also cites `index.php:89`.
- **Up to 5 groups per person** (both): `Community.php:44, 331-333`.
- **A charter purpose is required** (both): `GroupCharters.php:141`.
- **Chapters: passes differ on who can open them.**
  - P1: owners can open chapters or approve requests (`GroupChapters.php:92-164`).
  - P2: "a member can open or request a chapter" (`:92-146`).
- **Further owner powers.** Single-pass (P1):
  - edit the charter (`group.php:189-208`);
  - pick moderators (`:255`);
  - set tags (max 5) and places (max 10) (`GroupGrowth.php:21-22`).
- **Owners and moderators: "Reports to decide" and "Ask Traversence for help"** (both): `group.php:261-270, 271-273`. P2 places these in (b) without naming the role.

**Private circles**
- **Address Book circles of your own connections (both).**
  - P1 cites `Groups.php:7-18, 93-102`.
  - P2 adds up to 30 circles of 50 connections each, with a group conversation and card sharing (`Groups.php:17-18, :139-166`).

### Business (listing team)

- **One group per business (both).** It doesn't count toward anyone's 5 (`Community.php:322-329`).
  - P1 adds that the Owner or a Manager starts it.
  - P2 adds that the listing owns it (0137 §1).
- **Team roles map to group roles (both),** `Community.php:47-50`:
  - Owner and Manager run it.
  - Creator moderates.
  - Support moderates.
  - Sales is the rep.
  - P2 adds that only the Owner can hide the group. Single-pass.
- **Post as the business, or as "Name · Sales, \<business\>"** (both): `group.php:238-239`.
  - P1: Owner, Manager and Creator post as the business.
  - P2's table names Creator.
- **Business starting charter.** Limits selling to the business's own items and fills in "publishes" (`GroupCharters.php:64-71`). Single-pass (P2).
- **When another group names the listing (both).** Owner and Managers are told once. They can take it off, and it can't be re-added without them.
  - P1 cites `GroupGrowth.php:217, 226-231, 235-241`.
  - P2 cites `:215-217, :226-241`.
- **Join requests and reports reach the business's Pulse (both):** `GroupNotices.php:55-59, 66-71`. P1 also cites `:14-15`.
- **Growth counts (both).** Joins by source and page views, as counts only.
  - P1 cites `GroupGrowth.php:414-423`.
  - P2 cites `:405-423`.

---

## (b) How it works underneath

### `Community.php`

- **Topics:** 11 fixed topics (`:41-43`). Single-pass (P1).
- **`can()` (both).** Role-table checks, with parent moderators able to moderate in chapters.
  - P1 cites `:49-50, :164-168`.
  - P2 cites `:160-170`.
- **`directory()` (both).** Near-ranking by place hierarchy using `nearKeys` (`:270-295`).
  - P2 cites `:205-267`, covering search and a topic filter.
  - P1 gives the buckets (0 here, 1 area, 2 neighbors, 3 region), then members. Without a place, it sorts by members.
  - P1 notes "Never by payment (0007)" (`:203, 263`) and "+N this week" as a 7-day join count (`:245-247`). Single-pass.
  - **Possible difference:** P2 defines the "Most active" sort as 7-day joins then members. P1's no-place default is members alone. These may describe different paths.
- **`create()` (`:311-361`).** Single-pass (P1). Runs a Standing guard (`:313`), verification, the business-group check and the 5-group check, then saves the charter in the same transaction.
- **`join()` (both), `:403-453`.**
  - Both: chapter rule, charter version check, invitation and code skips, join answers, counting, moderator notice.
  - P1 gives line cites and says invitations and codes skip