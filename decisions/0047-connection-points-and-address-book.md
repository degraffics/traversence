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
| **Linked** (tapped Link, or accepted a business's request) | see this business in my feeds, and message each other | have its posts, events and offers appear in the person's feeds, and send offers and news as **direct messages in the Traversence inbox** (two-way) |
| **Linked + contact shared** (the person ticks fields for that business) | this business may also use my phone or email | email or text, under the compliance rules below |

Changed 2026-09-30 (Jason): linking a business **is** the opt-in to two-way in-app messages; a separate
"get offers?" question felt like a gate. Unlinking ends it just as easily, and the person can stay linked
but unsubscribe.

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
- **Nothing implied beyond the link.** Visiting a listing, messaging a business or buying from it never opts
  anyone in; only tapping Link (or accepting a business's request) does, and only to in-app messages.
  Email and text always need the person to share that detail with the business.
- **Messages go through Traversence.** A business never receives a person's email or phone unless that
  person shares their contact card; the business sends, Traversence delivers and enforces the preferences.
- **Business Insights** shows counts: linked, subscribers, unsubscribed, new links (30 days), people sharing
  contact info, people who asked never to be asked; names only for people who chose to share them.
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
- **2026-09-29, phase 2 built:** the Address Book (`js/address-book.js` in the hub; `user/api/people.php`;
  `api/lib/People.php`; migration `2026-09-30_address_book.sql`): find people (by name or exact email;
  only names are ever returned), public profiles (`user/profile.php`: name and join date only) with Link
  (follow), Connect, Message, Ask for contact info and Block; connection requests; the contact card with
  per-field, per-connection sharing, live updates and "Who has my contact info" with take-back; one inbox
  with mute and archive; message controls (connections only by default, or anyone) and a private-profile
  switch; blocking (ends follows, the connection and shared cards both ways; the blocker's profile
  disappears for the blocked person). The Community feed shows new connections and shared cards. Groups
  (circles) are not built yet.
- **2026-09-29, finding people and invites:** search by name (every word, anywhere in the name), by exact
  email (default on) or exact phone (default off; the phone on the person's card) — each governed by the
  person's "How people can find me" controls. Never by town or area. People not on Traversence are
  **invited**, never looked up: one email with a join link (or, for a phone number, a ready-made text the
  member sends themselves, since there's no texting service); joining through the link connects them.
  Inviting an address that already belongs to a member sends them a connection request instead, with the
  same reply, so no one learns who is registered. Up to 20 invites a day. Migration
  `2026-09-30_people_find_and_invite.sql`; `join.php`; `api/auth/register.php` accepts the invite.
- **2026-09-30, phase 3a built (the person's side), then revised the same day:** linking a business
  subscribes the person to its in-app messages (weekly digest by default), with a short note under the Link
  button instead of a question. App Settings → Marketing lists every linked business: name, status
  (Subscribed / Unsubscribed / Paused, and "sharing N"), and × to unlink. Tapping the name opens its
  settings: frequency or unsubscribe (with ask again in 1 / 3 / 6 months or never), and which contact
  details the business can see (shared with the people who manage its listing; untick to take back). The
  master switch "No marketing from anyone" pauses all. Relinking clears an earlier unsubscribe. Business
  Portal → Business Insights shows the counts. Migration `2026-09-30_marketing_optins.sql`;
  `api/lib/Marketing.php`; `user/api/marketing.php`; `api/lib/Links.php`; `js/link-button.js`;
  `user/dashboard.php`.
- **2026-09-30, phase 3b built (the business's side):** business conversations are always one person and
  one business (B2C, 1:1), stored apart from person-to-person messages (`business_messages`), and contact
  details shared with a business are its own list (`business_contact_shares`), never a share with its staff
  as people; unlinking takes them back. A person can message any business someone manages; the business
  replies while they're linked or within 30 days of their last message, and sees a name only for people who
  wrote to it. Offers go to subscribers' inboxes (at once, Monday 9am for the weekly digest, big-only people
  only for big offers), one a day, each with Unsubscribe. Requests to link or subscribe again go only to
  people who unsubscribed or wrote in, once their own wait has passed; one a week; an unanswered request
  can't repeat for 3 months; the person answers Yes, or No with when to ask again. Business Portal →
  Messages & offers per listing. Migration `2026-10-01_business_messages.sql`; `api/lib/BizMessages.php`;
  `user/api/business-messages.php`; `js/business-inbox.js`; `js/address-book.js` (Messages → Businesses).
- **2026-09-30:** a Message button (speech bubble) beside Link on listing pages that someone manages, opening
  that business's conversation; the Notification Center counts new business messages for the person and
  waiting customer messages for the people who manage listings.
- **2026-09-30, Groups built:** private circles of a person's own connections (Address Book → Groups):
  create, rename, delete; add or remove connections; message the group (one group conversation; members see
  each other's names there and can leave it; deleting the group ends it for everyone); share card fields with
  everyone in it (adds per person; taking back stays per person). Ending a connection or blocking takes the
  person out of the other's groups and their conversations. One-to-one conversations stay separate. Up to 30
  groups of 50. Migration `2026-10-01_groups.sql`; `api/lib/Groups.php`. Public community groups remain open.
- **2026-09-30:** the Link button on every directory search card (beside the category), and card names open
  the listing page; `js/link-button.js` now picks up cards drawn after the page loads.
- **2026-09-30, public community groups built:** `/community/` (find, join, start; up to 5 run per person)
  and `/community/group.php` (about and member count for anyone; posts and member names for members only;
  open or approval joining; the owner picks moderators, who accept requests and remove posts or members;
  owners or admins hide a group). Blocked people don't appear to each other. Posts from a person's groups
  show in their Community feed; Community Connections lists their groups. Migration
  `2026-10-01_community_groups.sql`; `api/lib/Community.php`; `user/api/community.php`.
- **Open:** email/text delivery of offers to people who shared those details (needs an email service's
  unsubscribe handling and a texting provider); tying a group to a place.
- **2026-09-30, reporting built:** Report on community posts (members) and groups (anyone signed in), with a
  reason (spam, harassment, a sacred or restricted site, someone's private information, other) and a note;
  one report per person per item; 3 reports hide a post until an admin decides. Admin → Reports
  (`admin/reports.php`): remove / hide, dismiss, or restore; the Notification Center counts open items.
  Reporters are never shown. Migration `2026-10-01_reports.sql`; `api/lib/Reports.php`.
