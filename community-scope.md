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
| **Events, a site-wide tool** (below) | **0134**: Jason's answers all in; ready to accept | Jason, 2026-11-01; 0134 |
| **Tags on groups:** a group carries its own @tags under its topic ("Food & cooking" → baking, cast iron), so search and topic pages narrow to them | New proposal | Jason, 2026-11-01; uses 0132 |
| **Local chapters:** a topic group opens chapters tied to a place ("World Travelers: White Mountains"); a chapter is a local group with a parent, shown on its place's page, listed on the parent | New proposal; needs a decision | Jason, 2026-11-01 |
| **Places a group names:** a group lists places it's about (trips planned, places visited) on its public page, so those place pages show "Discussed by: <group>". The group's choice only, never inferred from members-only posts; passes 0066's checks | New proposal | Jason, 2026-11-01; 0053, 0066 |
| **Topic plus place in search:** "cooking groups near Show Low" returns local groups and chapters by distance, and interest groups by topic without a distance | New proposal | Jason, 2026-11-01; 0053 |
| Creation gated by trust score; signal-based visibility for new groups | Only Verification and the cap of 5 are live | architecture §27 |
| Community threads into the AI corpus (classified at intake, abstraction only) | Not built | 0011, 0010, architecture §27–28 |
| Rest of gap-review step 8: waypoints and GPX/KML, Trip Planner, Single Home Rule queue, emergency map | Not built | `aos-gap-review.md` §8 |

### Events: one tool for the whole site (Jason, 2026-11-01)

Events aren't a Community feature. Creating an event is one tool used across the site; `decisions/0134` covers all of
it:
- **Who creates:** members, groups (including chapters), businesses and listings, and our team for places; the
  crawler's Live events (0133) land in the same place.
- **Where one shows:** its venue's listing, its place's page and guide, the group or business that runs it, a public
  calendar, and search.
- **For a group:** owner and moderators post; members suggest. A group event can be public or members-only.
- **Settled (Jason, 2026-11-01):** creating an event needs a verified account at minimum; the host sets who sees who's
  going when creating it; one kind plus tags; the admin sees only system events and event support tickets (Let's
  Explore → Events; Social → Events removed).
- **People RSVP, listings sell tickets:** members and person-run groups get Going / Interested and free registration,
  with no outside ticket links. Ticket Sales is a listing's module with its own onboarding: Free 50 tickets a month,
  Basic 150 (no QR or door support), Core 250 (GO Codes and door support), Strategic and Enterprise full access and
  support (0134 §6b). A group that tries to charge is offered a listing and the onboarding.

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

**Nextdoor.** A neighborhood social network. You join by verifying your address and see your neighborhood's feed:
recommendations, lost pets, safety alerts, local news, for-sale items, and neighborhood groups. Businesses get free
pages and pay for advertising. Neighbors endorse them ("Faves"). Its July 2025 redesign added AI-picked
recommendations from years of neighbors' posts, local news and emergency alerts. Its September 2026 update added a Local
Faves hub ranked by neighbor endorsements, AI search that summarizes 15 years of neighbors' posts, redesigned business
pages with AI summaries, and paid Opportunity Alerts that show businesses neighbors' requests.
- *Strong at:* trust between neighbors; real local talk; endorsements from verified neighbors, not paid reviews.
- *Short of our mission:* bound to where you live, with nothing for travelers or interests beyond the neighborhood;
  advertising pays for it; an endorsement is a count, not a record of who vouched; neighbors' posts feed its AI
  search and summaries.

**Google (Business Profile, Search and Maps).** The listing people find when they search: hours, phone, directions,
website, photos, reviews, plus the business's short posts. Google ended its business chat on July 31, 2024 (contact is
calls, the website or the business's own channels). It is removing the public Q&A on profiles (its API ended November
3, 2025) in favor of Gemini answering questions about a place ("Ask about this place" in Maps).
- *Strong at:* being found; getting there (directions, call, open now).
- *Short of our mission:* a one-off transaction, not a relationship; no groups or community; ranking and ads decide who
  is seen; reviews are stars from strangers; questions about a business are now answered by Google's AI, not by the
  business or its neighbors.

**Meta (Facebook Pages and Groups, Instagram).** A business Page with a feed, followers and reviews; Groups on any topic,
public or private, run by admins with few fixed rules; ads that reach people far beyond followers.
- *Strong at:* reach; groups for every interest; social proof.
- *Short of our mission:* the algorithm decides who sees what; ads and boosted posts; members can be targeted and
  messaged; groups have no charter. When admins go quiet or leave, Meta itself suggests or appoints new admins, or
  archives the group: members have no say set in advance.

**Messenger (and WhatsApp).** Private conversations, one to one or in small groups; businesses get an inbox and bots.
- *Strong at:* quick, direct answers.
- *Short of our mission:* no shared space, rules or place around the conversation; nothing others can learn from;
  either side can start it, so it can turn into outreach nobody asked for.

**AI chats (ChatGPT, Gemini, Claude and others).** Answer questions about a place or business, recommend businesses and
plan trips, from the web and from listing data. Gemini and Google's AI Mode draw on Google's Business Profiles and Maps.
Other assistants draw on other directories and review sites. Industry surveys report that a fast-growing share of
people now ask AI for local recommendations, and that the businesses named tend to be those with the most reviews.
- *Strong at:* a fast summary; trip ideas.
- *Short of our mission:* a business can shape its listings but not what the answer says; answers can be stale or wrong,
  with no local check; the most-reviewed win; no relationship, community or follow-up; little care for whose words
  went into the answer.

### Side by side

| | What it's for | Who starts contact | Tied to place | Can visibility be bought? | Who controls the data |
|---|---|---|---|---|---|
| Nextdoor | Neighbors where you live | Either side; ads reach people | Your neighborhood only | Yes (ads, sponsored posts) | Nextdoor; posts feed its AI search |
| Google | Finding and getting to a business | The customer, one-off | Yes, the listing's address | Yes (ads, ranking) | Google |
| Meta Pages and Groups | Reach and social proof; groups on anything | Either side; ads reach people | Loosely (location tags, local groups) | Yes (ads, boosts) | Meta; used for ad targeting |
| Messenger | Private conversations | Either side | No | No, but anyone can message | Meta |
| AI chats | Answers, recommendations, plans | The asker | Through the listing data they draw on | Not directly; listings and review counts decide | The AI provider |
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

### Sources (checked 2026-11-01)

- Nextdoor: [September 2026 announcement](https://www.nasdaq.com/press-release/nextdoor-deepens-its-commitment-local-business-discovery-and-recommendations-2026-09)
  (the company's own claims), [July 2025 redesign](https://techcrunch.com/2025/07/15/nextdoor-redesigns-app-with-ai-recommendations-local-news-and-real-time-emergency-alerts),
  [address verification](https://www.digitalunite.com/technology-guides/social-networking-blogs/how-join-local-social-network-nextdoor).
- Google: [business chat ended July 31, 2024](https://developers.google.com/business-communications/business-messages/resources/release-notes/update-on-gbm?hl=en)
  ([9to5Google](https://9to5google.com/2024/05/28/google-business-messages-shutting-down/)); Q&A removal
  ([Birdeye](https://birdeye.com/blog/google-business-profile-qa-api-discontinued/),
  [Accrisoft](https://www.accrisoft.com/blog/2026/01/28/main/google-removes-business-profile-q-a-what-it-means-and-what-to-do-now/)).
- Meta groups without active admins: [Social Media Today](https://www.socialmediatoday.com/news/meta-warns-group-admins-maintain-activity-or-theyll-be-replaced/690606/).
  Secondary reporting; Meta's own current help pages weren't found.
- AI recommendations: [Arfadia](https://www.arfadia.com/blog/how-chatgpt-gemini-perplexity-pick-local-businesses/),
  [RankRabbit](https://rankrabbit.ai/blog/business-listings-backbone-ai-search). Marketing firms citing industry
  surveys; the trend is consistent, the exact figures vary, so none are quoted here.

## Decisions that cover the build

- **Core:** `architecture.md` §27 (the governance model), 0047 (what was built), 0102 §4b–4c (group roles, business
  groups), 0103 (charter transfer terms, inactivity; Accepted, terms set).
- **Around it:** 0055 (Steward role and dashboard), 0062 (Verification), 0053 (search), 0132 (group membership as a
  link; tags), 0052 (messaging between group members), 0058 (Steward badge), 0109 (admin module 6), 0007 (no paid
  placement), 0134 (events, Proposed).
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
5. Events is written up (0134, Proposed); until it's accepted, the admin still has it in two places (Let's Explore → Live
   events, and Social → Events "coming").
6. `/community/index.php` still passes breadcrumbs (`crumbs`), which 0058 rules out on content pages.

## Next build

1. Accept **0134 Events** and write a **chapters** decision (tags on groups and places a group names can
   ride with it).
2. **Charter and transfer terms** on the group page, shown before joining.
3. **Inactivity and ending:** the `lifecycle` table and a daily `Scheduler::JOBS` entry for groups.
4. **Admin → Social → Groups** and **Business groups**; Steward member reports and escalation.
5. **Business-run groups** (0102 §4c).
6. **Tags on groups**, **chapters**, **places a group names**, topic plus place in search.
7. **Events**, in 0134's build order.
