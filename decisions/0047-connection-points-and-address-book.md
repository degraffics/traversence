# ADR 0047: Connection Points, the Address Book, and the Account Hub

**Status:** Accepted (2026-09-29), from Jason's dashboard mockups. Design only; nothing built yet.

## Context

Jason's dashboard mockup puts every role behind one front door: a left panel with the person's name and
role, then collapsible sections (**User Dashboard**, **Business Portal**, **Admin Dashboard**); a
**Notification Center** strip across the top of the content; and four feed tabs (**Community**,
**Discovery**, **Listings**, **Marketplace**). The User Dashboard lists Saved Locations, Address Book,
Community Connections, Marketplace Listings, Travel Plans, Linked Connections, App Settings, Personal
Security and Help Center.

Two of those needed defining, and both turn out to be core ideas of the platform:

- **Linked Connections**: "the button the user clicks to create a connection between that item and them.
  Kind of like favorites, but connection point is the idea. It is what will feed the feeds. Link a
  person, topic, marketplace item, business, etc."
- **Address Book**: "the people we connect with, and our message controller", with the option to
  share contact info and revoke it: "the modern address/contact book."

## Decision

### 1. One account hub

`/user/dashboard.php` becomes the one front door for every role. Sections appear by role: everyone gets
User Dashboard; listing owners and managers get Business Portal; admins get Admin Dashboard, whose
Crawler area is the workspace from `decisions/0046`. On wide screens the sections are a labelled left
panel; on a phone they fold into the swipeable icon row across the top (`decisions/0046`), with the
Notification Center just beneath. Icon states everywhere: flat gold at rest, green glow when active.
The Notification Center gathers every section's counts (messages, dispute clocks, items to review).

### 2. Connection points (the Link action)

- **One Link button, on anything:** a person, business/listing, place (hub, geo-hub, cluster, anchor,
  local name), topic or category, guide or story, marketplace item, event. It is the chain icon, with the
  same states: flat gold unlinked, green glow linked. One tap links; another unlinks.
- **One list behind it:** each link is a row of who linked what and when (`target_type`, `target_id`), so
  new kinds of things become linkable without redesign. Links can be muted ("keep, but less in my feed").
- **The feeds are built from links:**

  | Feed | From links to |
  |---|---|
  | Community | people and places: their public posts, tips, events, local names |
  | Discovery | places, topics, guides: new stories, Chameleon picks, seasonal events |
  | Listings | businesses and categories: new listings nearby, updates and offers |
  | Marketplace | items, sellers and categories: new posts, price changes, sold |

- **Linking a person is a follow:** automatic, no consent needed, and it shows only what that person
  already made public, like following on Facebook. Joining a community group is itself consent to be
  seen by that group's members within the group. The person can remove a follower, block, make their
  profile private (following then needs approval) and choose which groups they appear in.
- **Location is never exposed by following.** A follow shows what someone posts, never their home area,
  saved places, live whereabouts or travel plans, unless they share that specific post or plan. The same
  principle as the phone-only display for confidential addresses (`decisions/0044`).
- **Recommendations:** using links to shape Chameleon suggestions respects the AI-consent setting
  (`decisions/0010`); feeds show what someone linked either way.
- **Businesses see counts, not names:** "142 people linked" in Business Insights; who linked stays
  private unless a person chooses to share. Businesses can also ask a person to link (§3), under the same rules as a person linking to them.

### 3. Businesses and people linking: the marketing and sales funnel

A listing can **ask** a person to link to it, and a person can **link to a business** on their own. Both
directions follow the same rules; either way the person becomes that business's audience, and this is
where marketing preferences live. There are two levels, so no one is surprised:

| Level | The person agreed to | The business can |
|---|---|---|
| **Linked** (tapped Link, or accepted a business's request) | see this business in my feeds | have its posts, events and offers appear in the person's Listings and Discovery feeds |
| **Linked + marketing** (a separate, explicit yes) | contact me directly | send offers and news as **direct messages in the Traversence inbox** |

- **DM only is the policy.** A business reaches people through the in-app inbox and nothing else. Email or
  text is possible **only when the person has shared that contact detail with that business** (their
  contact card, §4), and it stops the moment they revoke it.
- **Text and email compliance, always.** Whenever a business's message goes out by email or text: explicit
  opt-in for that channel (US telemarketing rules for texts), the sender identified, and a working
  unsubscribe in every email (US commercial email rules). No exceptions by plan or by business.
- **The person decides when a business may ask again.** Declining a link or marketing request offers:
  **Ask again in 1 month · 3 months · 6 months · Never.** "Never" is permanent unless the person reaches
  out first. The same choice appears when someone turns marketing off for a business.
- **Marketing preferences** (App Settings → Marketing, and on each business in Linked Connections): per
  business on/off, frequency (as it happens, weekly digest, big offers only) and topics (offers, events,
  news); one master switch, "No marketing from anyone"; a one-tap unsubscribe in every message and a
  one-tap unlink.
- **Nothing pre-ticked, nothing implied.** Visiting a listing, messaging a business, buying from it or
  linking to it never opts anyone into marketing; only an explicit yes does.
- **Messages go through Traversence.** A business never receives a person's email or phone unless that
  person shares their contact card; the business sends, Traversence delivers and enforces the preferences.
- **Business Insights** shows how many people are linked and how many opted into marketing; names only for
  people who chose to share them.
- Open: how marketing reach maps to the plan tiers in `commercial.md` (e.g. Core: in-feed posts; higher
  tiers: direct offers and larger monthly send allowances).

### 4. The Address Book (people and messages)

| Section | Holds |
|---|---|
| Connections | mutual connections: one person asks, the other accepts (the "friends" level, above a follow) |
| Requests | incoming and outgoing requests: Accept, Decline, Ignore |
| Contact cards | contact details people have shared with you, and what you've shared with whom |
| Groups | a person's own circles ("Family", "Trail crew") for sharing a plan or messaging several at once |
| Messages | the one inbox (the Centralized Communications Center): people, businesses, marketplace "connect" |
| Message controls | who can message you (connections only, group members, anyone, businesses you've linked), mute, archive, read receipts |
| Blocked & reported | blocked people with unblock; reports filed |

- **Sharing contact info is by choice, per person and per field.** Phone, email, a mailing address and
  social handles can each be shared with one connection or a group, and **revoked at any time**. A shared
  card **stays current**: change your number and everyone you've shared it with sees the new one. Revoking
  removes the card from their Address Book and stops updates.
- **"Who has my contact info"** lists every share, with a revoke button beside each.
- A connection can **request** your contact info; nothing is shared until you choose.
- **Copies outside Traversence** (exporting a card to a phone's contacts) can't be revoked; the export
  button says so plainly before it runs.
- A business's public listing details are public already; a business shares nothing personal.
- **Messaging defaults:** only connections and group members can message you, plus replies from
  businesses you contacted first. Anything wider is opt-in. A marketplace "connect" opens a conversation
  in the same inbox, so there is never a second messaging system.

## Consequences

- The feeds, the Chameleon engine, Business Insights, the Notification Center and each business's marketing
  audience all read from the same links, so the Link button is built first and everything else builds on it.
- Three new data areas: links (one table for every kind of target), connections and contact-card shares
  (with revocation), and the message store (the Communications Center already named in `architecture.md`).
- The current `admin/adminportal.php` becomes the Admin Dashboard section of the hub; its routes keep
  working.
- Open: whether groups are private circles only or can also be public community groups; the exact
  profile fields shareable on a contact card; how long message history is kept.

## Progress

- **2026-09-29, phase 1 built:** the account hub (`user/dashboard.php`: profile and role, User Dashboard /
  Business Portal / Admin Dashboard sections, the Notification Center, the four feed tabs; one swipeable
  icon row on phones), the Link button (`js/link-button.js` on place and listing pages; `user/api/links.php`;
  `api/lib/Links.php`; migration `2026-09-30_user_links.sql`), Linked Connections (list, mute, unlink), and
  the first feeds: Listings (posts from linked businesses, new listings in linked places) and Discovery
  (linked places and what's new there). Businesses and places can be linked today.
- **Next:** phase 2, the Address Book (people connections and follows, contact cards shared and revoked,
  the inbox and message controls; the Community feed); phase 3, businesses asking to link, marketing
  preferences and the ask-again choices.
