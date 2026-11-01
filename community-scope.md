# Community: what's live, what's proposed, and the decisions behind it

Scope of the Community ("Connect") build, checked on 2026-11-01 against the live mirror
(`degraffics/traverse-3way-sync`, `sync-storage/` = `website/`, `sync-app/` = `app/`). Update it as each part ships.

## Live

| What | Where | Decision |
|---|---|---|
| Public community groups: find, join, start (up to 5 per person); open or approval joining; about and member count for anyone, posts and member names for members only | `/community/`, `/community/group.php`, `app/lib/Community.php`, `user/api/community.php` | 0047 (progress notes) |
| Two kinds of group: **interest groups** (the topic is their home, no place) and **local groups** (tied to a hub, geo-hub or town cluster, shown on that place's page). A place is optional | `Community::TOPICS` (11 broad topics), place picker, `hub/index.php` | 0047 |
| Group roles: owner, moderator, member (pick moderators, accept requests, remove posts or members, hide the group) | `Community::manage` | 0102 §4b |
| Starting a group needs Verification | `MemberVerification::require(…, 'steward')` | 0062 §4 |
| Reports on posts and groups (3 reports hide a post until an admin decides) → Admin → Social → Reports, Standing | `app/lib/Reports.php`, admin `social/reports`, `social/standing` | 0047, 0109 |
| Steward role in the dashboard: "Your communities" with members and join requests | `user/dashboard.php`, `app/lib/Roles.php` | 0055 |
| Private circles (a person's own lists of connections) | `app/lib/Groups.php` | 0047 §4 |
| Groups in Universal Search (posts never searched); the Community feed | `app/lib/SearchIndex.php` | 0053, 0055 |
| Live events from the crawler (a place's official pages), in guides and on venue listings | `app/lib/LiveEvents.php` | 0133 |

## Proposed, not built

| What | Status | Source |
|---|---|---|
| Per-group charter (purpose, house rules, transfer terms) | Steward dashboard shows "Charter — soon"; no charter field in the code | architecture §27, 0102 §4c, 0103 §2a |
| Transfer terms: end the group (default), named successor, longest-serving moderator, moderators choose | Not built | 0103 §2a |
| Inactivity: no posts or comments for 30 days, or no owner/moderator activity for 90 days → Notice → Hidden → Archived → Removed | Not built; no scheduled job | 0103 §3–4 |
| Business-run groups: the business owns the group, team roles map to group roles, posting as the business, sales rep, contact only when the member starts it, post caps by plan | Not built; groups have no business owner | 0102 §4c |
| Steward member reports and Level 2 escalation (3-tier moderation) | Steward dashboard "soon"; admin (Level 3) is live | architecture §27, 0055, master-aos §6 |
| Admin → Social → Groups (table, charter drawer) | "Coming" card in `app/admin/manifests.php` | admin-architecture §6, 0109 |
| Admin → Social → Business groups | Not yet a section | admin-architecture §6 |
| **Events, a site-wide tool** (below) | "Coming" under Social → Events, waiting on its definition; **no decision covers it** | Jason, 2026-11-01; admin-architecture §6, master-aos |
| **Tags on groups:** a group carries its own @tags under its topic ("Food & cooking" → baking, cast iron), so search and topic pages narrow to them | New proposal | Jason, 2026-11-01; uses 0132 |
| **Local chapters:** a topic group opens chapters tied to a place ("World Travelers: White Mountains"); a chapter is a local group with a parent, shown on its place's page, listed on the parent | New proposal; needs a decision | Jason, 2026-11-01 |
| **Places a group names:** a group lists places it's about (trips planned, places visited) on its public page, so those place pages show "Discussed by: <group>". The group's choice only, never inferred from members-only posts; passes 0066's checks | New proposal | Jason, 2026-11-01; 0053, 0066 |
| **Topic plus place in search:** "cooking groups near Show Low" returns local groups and chapters by distance, and interest groups by topic without a distance | New proposal | Jason, 2026-11-01; 0053 |
| Creation gated by trust score; signal-based visibility for new groups | Only Verification and the cap of 5 are live | architecture §27 |
| Community threads into the AI corpus (classified at intake, abstraction only) | Not built | 0011, 0010, architecture §27–28 |
| Rest of gap-review step 8: waypoints and GPX/KML, Trip Planner, Single Home Rule queue, emergency map | Not built | `aos-gap-review.md` §8 |

### Events: one tool for the whole site (Jason, 2026-11-01)

Events aren't a Community feature. Creating an event is one tool used across the site, and its decision covers all of
it:
- **Who creates:** members, groups (including chapters), businesses and listings, and our team for places; the
  crawler's Live events (0133) land in the same place.
- **Where one shows:** its venue's listing, its place's page and guide, the group or business that runs it, a public
  calendar, and search.
- **To settle in the decision:** who may create what, approval and reporting, recurring and seasonal events, RSVPs,
  sacred-site checks (0066), and where Events sits in the admin (today a "coming" section under Social; as a site-wide
  tool it may need its own home, opened in a drawer from the modules that use it).

### Not controlled

- **Brand names in group names** (Jason, 2026-11-01): no rule. People name groups freely ("Cooking with Crisco");
  problems are dealt with case by case through reports. Only a business's own business-run group shows "Run by
  <listing name>".
- **Where members are:** never shown or mapped by any group (0047).

## How Traversence Community compares

### The standard we hold to

The charter's mission is to connect people to "the heartbeat of every community", linking residents, local
businesses and regional events into one network, and binding "dialogue and gatherings to place" (the Connect track).
It promises four things the big platforms don't: **true locality without noise**, **discovery that can't be bought**,
**frictionless agency** and **data dignity**. Community is judged against those.

### What each offers

**Nextdoor.** A neighborhood social network. You join with a verified address and see your neighborhood's feed:
recommendations, lost pets, safety posts, for-sale items, and neighborhood groups. Businesses get free pages and pay
for ads and sponsored posts; neighbors recommend them ("Faves"). Its September 2026 redesign added a Local Faves hub
ranked by neighbor endorsements and AI search over neighbors' posts.
- *Strong at:* trust between neighbors; real local talk.
- *Short of our mission:* bound to where you live, with nothing for travelers or interests beyond the neighborhood;
  paid ads in the feed; endorsements are a popularity count; neighbors' posts feed its AI search.

**Google (Business Profile, Search and Maps).** The listing people find when they search: hours, phone, directions,
website, photos, reviews and Q&A, plus the business's short posts. Google retired its business chat in 2024; contact is
calls, the website or the business's own channels.
- *Strong at:* being found; getting there (directions, call, open now).
- *Short of our mission:* a one-off transaction, not a relationship; no groups or community; ranking and ads decide who
  is seen; reviews are anonymous stars.

**Meta (Facebook Pages and Groups, Instagram).** A business Page with a feed, followers and reviews; Groups on any topic,
public or private, run by admins with few fixed rules; ads that reach people far beyond followers.
- *Strong at:* reach; groups for every interest; social proof.
- *Short of our mission:* the algorithm decides who sees what; ads and boosted posts; members can be targeted and
  messaged; groups have no charter, no say in succession, and abandoned ones stay up for years.

**Messenger (and WhatsApp).** Private conversations, one to one or in small groups; businesses get an inbox and bots.
- *Strong at:* quick, direct answers.
- *Short of our mission:* no shared space, rules or place around the conversation; nothing others can learn from;
  either side can start it, so it can turn into outreach nobody asked for.

**AI chats (ChatGPT, Gemini, Claude and others).** Answer questions about a place or business from what's on the web, and
increasingly plan trips.
- *Strong at:* a fast summary; trip ideas.
- *Short of our mission:* the business doesn't control what's said about it; answers can be stale or wrong, with no
  local check; no relationship, community or follow-up; little care for whose words trained the answer.

### Side by side

| | What it's for | Who starts contact | Tied to place | Can visibility be bought? | Who controls the data |
|---|---|---|---|---|---|
| Nextdoor | Neighbors where you live | Either side; ads reach people | Your neighborhood only | Yes (ads, sponsored posts) | Nextdoor; posts feed its AI search |
| Google | Finding and getting to a business | The customer, one-off | Yes, the listing's address | Yes (ads, ranking) | Google |
| Meta Pages and Groups | Reach and social proof; groups on anything | Either side; ads reach people | Loosely (location tags, local groups) | Yes (ads, boosts) | Meta; used for ad targeting |
| Messenger | Private conversations | Either side | No | No, but anyone can message | Meta |
| AI chats | Answers and plans | The asker | Only as text | Not directly; sources decide | The AI provider |
| **Traversence Community** | Groups for places and for interests, with charters; businesses that teach and earn trust | **The member** for anything from a business (contact, quote, message the rep, or Link) | Yes when it matters: local groups and chapters on place pages; interest groups stand on their topic | **No** (0007: paid tiers sell tools, not placement) | **The person**: posts members-only, never searched, never ad fuel; AI use by consent and abstraction (0010, 0011) |

### How we deliver better

| Charter promise | How Community keeps it | Status |
|---|---|---|
| **True locality without noise** | Local groups and chapters sit on the place's own page beside its listings, guides and events; interest groups aren't forced onto a map; inactive groups close (no ghost towns) | Local groups live; chapters and inactivity coming |
| **Discovery that can't be bought** | No ads or boosted posts in groups; business posts stay in the group and its members' feeds, never pushed to non-members or made to look like our recommendations | Built into 0102 §4c; business groups coming |
| **Frictionless agency** | One account: join, post, message the rep, Link with a business, RSVP to an event, all from the place you're looking at; the member decides when a business may reach them | Joining and posting live; the rest coming |
| **Data dignity** | Posts and member names for members only; posts never searched; no member maps; no exporting member lists; AI uses community talk only as abstractions and by consent | Live (privacy); AI corpus rules decided |
| **Accountability without top-down control** | Every group has a charter its members see before joining, including what happens when the owner leaves; Stewards moderate first, staff only on escalation | Charters and escalation coming |
| **Linking residents, businesses and events** | Business-run groups that educate and win leads honestly; events as one site-wide tool shown on places, listings and groups | Coming |

**In one line:** the others sell reach or answers. Traversence gives a place, or an interest, a home with its own rules,
where the people decide who reaches them and nothing can be bought to the top.

## Decisions that cover the build

- **Core:** `architecture.md` §27 (the governance model), 0047 (what was built), 0102 §4b–4c (group roles, business
  groups), 0103 (charter transfer terms, inactivity; Accepted, terms set).
- **Around it:** 0055 (Steward role and dashboard), 0062 (Verification), 0053 (search), 0132 (group membership as a
  link; tags), 0052 (messaging between group members), 0058 (Steward badge), 0109 (admin module 6), 0007 (no paid
  placement).
- **Safety and data:** 0009 (guest abuse in communities), 0010 (authorship), 0011 (AI corpus), 0066 (no sacred or
  restricted sites).
- **Sequencing:** 0020, 0030 (Connect after Directory and Discovery).

## Out of step

1. 0047's header still says "Design only; nothing built yet", though its progress notes record the build.
2. 0020 calls Communities "unschedulable" until a design pass; the groups are live.
3. §27 says "Steward" and trust-score gating; the code has owner/moderator (both count as Steward in `Roles.php`) and
   Verification.
4. 0103's header read "Proposed" though its terms were set and in use (0120); corrected 2026-11-01. Built so far:
   hand transfer for businesses only (`transfers` table, `/user/transfer.php`); the `lifecycle` table and the daily
   job are still to come.
5. Events has no decision, and the admin files it under Social though it's a site-wide tool.
6. `/community/index.php` still passes breadcrumbs (`crumbs`), which 0058 rules out on content pages.

## Next build

1. Write the **Events** decision (site-wide) and a **chapters** decision (tags on groups and places a group names can
   ride with it).
2. **Charter and transfer terms** on the group page, shown before joining.
3. **Inactivity and ending:** the `lifecycle` table and a daily `Scheduler::JOBS` entry for groups.
4. **Admin → Social → Groups** and **Business groups**; Steward member reports and escalation.
5. **Business-run groups** (0102 §4c).
6. **Tags on groups**, **chapters**, **places a group names**, topic plus place in search.
7. **Events**, once defined.
