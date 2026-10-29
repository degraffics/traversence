# ADR 0132: Tags and Links — What Content Is About, and Who It Reaches

**Status:** Accepted (2026-10-30), direction from Jason. Design; not built as one system yet (see "Building it").
Takes the tag/link rule out of `decisions/0128` (Tags and links, Jason 2026-10-28) into its own record, and
refines `decisions/0047` §2 (connection points), `decisions/0051` §3 (@tags) and `decisions/0052` §1 (open links).
Everything in `decisions/0050` (what a link is used for, and what Traversence never does with it) stands unchanged.

## Context

"Link" had come to mean two things. The Link button (`decisions/0047`) is a person's connection to a business,
place or person: it fills their feeds, lets a business message them, and is how content reaches people. And
`decisions/0128` defined a link as a one-to-one relationship that grants rights (a manager to a business, staff to a
listing, a contact to a client), with tags as references that can't. Meanwhile the relationships that do grant
rights grew up as separate tables (`listing_memberships`, `role_grants`, `connections`, `contact_shares`,
`community_group_members`, `contributors`, client contacts…), and the things content is about grew up as separate
tables too (`guide_mentions`, `experience_links`, `tag_people`, `system_tags`, CRM tags).

Jason (2026-10-30): linking is essential to the notification and communication system, from content to the person,
so the framework stays; it needs cleaning up into a complete tag versus link system. And the Chameleon Filter is
what oversees how content is associated with its sources, so that whenever a person, listing or admin posts something,
the association is always there and leads to the deeper content.

## Decision

### 1. The rule

- **A tag says what something is about.** It ties a piece of content (or a record) to one or many things: a place, a
  listing, a landmark, a topic, a person, an outside system's record. **A tag grants nothing.** It can notify once,
  and it lets the thing it names show the content ("Featured in").
- **A link is a relationship between exactly two parties, and it grants rights.** One person (or listing, or staff
  member) on one side, one thing on the other; each **kind of link** says what it lets either side do, who may
  start it, whether the other side must accept, who can see it and how it ends.
- **The test** (unchanged from `decisions/0128`): if a tag can't give what's needed (a privilege, an ongoing right to
  reach someone, or data the reference doesn't carry), it takes a link.

**The bridge, which is the point of having both:** tags say what content is about; links say who hears about it and
who may act on it. When something is posted, its tags find the things it's about, and everyone linked to those
things is reached the way their link allows. Neither half works without the other.

### 2. Tags

| Kind | Made by | Example | Does |
|---|---|---|---|
| **Content tags** (the Chameleon Filter's) | the Chameleon Filter, on save; a person can add or remove | a guide names Lyman Lake; a journey stops at a café; a post is about St. Johns | the content shows on that thing's page ("Featured in"), reaches the people linked to it, and is how a reader goes deeper |
| **Mention tags** (@) | the author, with the picker | @Maria Begay in a comment; @Trail conditions | notifies the person (or the people who follow a topic) **once**; no ongoing right |
| **System tags** | staff or a sync | a client's QuickBooks customer; an order's invoice | reference only, no notifications (`decisions/0128`) |
| **Team tags** | staff | a customer tagged Wholesale | sort and filter inside a tool; never shown publicly |

Tag rules:
- **Every content tag points to something we have.** A name we don't have goes to Find it and Review; a person
  approves before anything new is created (`decisions/0005`, `decisions/0131` §4).
- **Held, private and closed stay out.** A name that may mark a sacred or protected place is held (`decisions/0066`);
  a listing that keeps its address private is never tagged; a closed or merged listing flags the content that tags it,
  and a Nexus merge moves the tags to the listing that stays.
- **Tags never infer anything about a person** (`decisions/0050`). Tagging a clinic says something about the
  content, never about the author or a reader.
- A tag is the same record whoever made it (a member, a listing, staff, the crawler); who made it is kept with it.

### 2a. Which content the Chameleon Filter reads (Jason, 2026-10-30)

| Content | Associations come from |
|---|---|
| **Attached to an item**: a comment, a review, a photo caption, a post in a group | **the author's @tags only.** It already belongs to the thing it's on; the Chameleon Filter adds nothing |
| **Authored discovery content**: a member's journey (a local's day out), a guide, a story, an experience, a how-to, an event, a listing's description | **the Chameleon Filter**: it reads the piece, finds what it names, and inserts the associations, plus any @tags the author added |

- **Inserted associations show in the text:** a name it found ("Lyman Lake") is highlighted, and hovering it (tapping,
  on a phone) opens a preview card of that thing with **See more**.
- **Reading a member's words:** with AI & Learning consent (`decisions/0051`), Claude reads the piece to find the
  names; without it, only our own name dictionary and the author's @tags are used, and nothing is sent to Claude.
  Staff, listing and crawler content is always read in full.
- **Publishing is never held up by tagging.** Publish puts the piece live with its tags at once. The confirmation
  the author lands on shows the published piece with its tags as chips (**Lyman Lake ×**), each removable, and one
  **Looks good** button. Removing a tag takes the highlight out of the text and the piece off that thing's page;
  the author can do the same any time later from the piece's edit page.
- **Only things we have are tagged.** A name we don't have goes to Find it and Review (§2).
- **A member's piece on a business's page:** it shows under "Featured in" on the page, but reaches that business's
  followers' feeds only for those who also follow the author or the place, so naming a business never puts a post
  in front of its whole audience. Report and Hide apply to the piece as anywhere.
- **Every kind of discovery content goes through it**, whatever section it's in; the order they move onto it is
  only the order of building.

### 3. Links

Every link is one of these kinds. A new kind is added to this table, never as a table of its own with its own rules.

| Kind | Between | Grants | Started by · accepted? | Seen by the other side? |
|---|---|---|---|---|
| **Follow** (the Link button) | a person → a person, business, place, topic, guide, event | **communication:** the followed thing's content comes to you (feeds, notifications); a business may message you under your marketing preferences (`decisions/0047` §3, `decisions/0052` §4) | the person · no | yes for a person or business (open links, `decisions/0052` §1); places and topics have no other side |
| **Connection** | person ↔ person | message each other directly; share contact cards field by field | either · yes, both accept | yes |
| **Contact** (Address Book) | a person → anyone or anything | keep within reach privately; message; share details when you choose | the person · no | **no**, the private route (`decisions/0052` §2) |
| **Group member** | person → community group | see members and posts; post; moderators moderate | the person (open) or a moderator (approval) | the group's members |
| **Contributor** | person → journey or guide | write and edit what they're credited on | an owner or staff · yes | yes |
| **Listing role** | person → listing (owner, manager, accountant, sales…) | manage the listing within the role (`decisions/0104`, `Team::can`) | the owner or staff · yes | yes |
| **Client contact** | person → client business | see and approve its orders, proofs and invoices | staff or the client's owner · yes (the invite) | yes |
| **Vendor** | vendor → work order or product | accept, quote and update the jobs sent to it | staff | yes |
| **Staff role** | staff member → module / tool | use the admin tools the role allows (`Access::can`) | an owner or director | n/a |

Link rules:
- **Rights come only from links**, and from nothing else: not from a tag, not from having bought something, not
  from having messaged.
- **Every link can be ended** by the person who holds it, and by the other side where it grants them something
  (remove a follower, end a connection, take back a role); ending it ends the rights at once. Blocking ends every
  link between the two.
- **The 0050 promises bind every kind:** links are never sold, never used for ad targeting, never used to infer
  sensitive things, never mined for "people you may know", never used to train AI; only AI Assist reads them, for
  the person, if they turn it on.
- **Follows stay communication only.** A follow never grants a role, a view of private details or a location
  (`decisions/0047` §2: location is never exposed by following).

### 4. How content reaches people (the communication framework)

1. Someone posts a piece of content (a person, a listing, staff, the crawler).
2. **The Chameleon Filter tags it**: its place in the funnel, the names it mentions, its @mentions, its topics.
3. **Mention tags notify** the people named, once.
4. **Everyone who follows a tagged thing** gets it in their feeds and notifications, the way their follow is set
   (muted, digest, off). A business's Sales messages still go only through its followers' marketing preferences.
5. **Each tagged thing's page shows it** ("Featured in" on a listing, landmark, town or person's profile).
6. **What a reader sees first comes from their context**, not a score: the Chameleon Filter delivers what fits the
   viewer's need (Visiting or Live here, where they are in the funnel, what they asked). Nothing is ranked, and paid
   plans never change what's delivered (`decisions/0007`).

### 5. The Chameleon Filter's job

The Chameleon Filter (`decisions/0003`) keeps its routing role (two tracks, the dial) and takes on the
associations: it **makes and keeps the content tags** for every kind of content, on save and again when what they
point to changes (closed, merged, hidden, held), and it **delivers by context** (§4.6). Links are made by people and
roles, never by the Chameleon Filter.

## Building it

- **One tag store and one link store**, each with a kind and its configuration, replacing the separate tables over
  time; each current table moves when its feature is next worked on, with its data. The current homes:
  - **tags:** `guide_mentions`, `experience_links`, `tag_people` and `tag_counts`/`tag_leads` (@tags), `system_tags`,
    `crm_tags`;
  - **links:** `user_links` (follows), `connections`/`people_connections`, `address_contacts`, `contact_shares`,
    `business_contact_shares`, `community_group_members`, `people_group_members`, `contributors`,
    `listing_memberships`, `role_grants`, client contacts and `vendors`.
- **First:** the guides' name-linking (`GuideLinks`, `decisions/0131`) becomes the Chameleon Filter's first user of
  the tag store, with "Featured in" on listings and landmarks.
- **Settled (2026-10-30):** see §2a: which content is read, how a member's words are read (consent), and
  publishing at once with the tags shown as removable chips.

## Consequences

- One answer to "can this person do that?" (look for the link) and one to "what is this about, and who hears of it?"
  (the tags, then the follows).
- The Link button keeps its name and job; its row becomes the **Follow** kind of link, so feeds, notifications and
  business messages keep working as they do.
- New features add a tag kind or a link kind with its configuration, never a new relationship table with its own rules.

## Progress

- **2026-10-30, the guides on the tag store:** the tag store is `content_tags` (migration `2027-01-12_content_tags.sql`,
  which copies the guides' names from `guide_mentions` with their ids; `app/lib/ContentTags.php`). The guides' names
  (`GuideLinks`) are its first source (`guide:<place key>`): matching, hand links, Find it and closed checks work as
  before, now as content tags. A highlighted name opens its **preview card** on hover or a first tap
  (`js/tv-tagcard.js`, `/api/tag-card.php`: what it is, where, phone, See more). **Featured in** on listings,
  landmarks and outdoor places (`app/ui/featured-in.php`) lists the published guides that name them. `guide_mentions`
  stays, unused, until Jason clears it.
