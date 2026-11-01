# ADR 0134: Events, One Tool for the Whole Site

**Status:** Proposed (2026-11-01), direction from Jason: "Event creation is a site-wide tool, not just community."
Design; not built. Builds on `decisions/0133` (Live events), and fills the "events, when they exist" gaps left in
0047, 0053, 0055, 0058, 0059, 0060, 0061 and 0062. Choices marked **(Jason)** are proposals for him to confirm.

## Context

Events exist today in one form only: **Live events** (0133), which the crawler finds on a place's official pages.
They're stored in `live_events` (place, title, dates, time, venue, price, summary, source page; status Showing,
Waiting for a person, Past, Taken down). They show in a guide's Events section and as "Coming up" on the venue's listing,
and our team reviews them in Admin → Let's Explore → Live events.

Everything else is waiting on events:
- the admin's Social → Events section ("coming": group and business events, a public calendar);
- Things to do → Events (0058, inactive until built), Events in the header (0055, "soon");
- search's Events kind and its When words: tonight, this weekend, seasons (0053, 0060, 0061);
- the identity card's Events tab (0062, `Identity::COMING`);
- events in the feeds of people Linked to a business (0047), and events as a source for experiences (0059);
- GO Codes for events (`commercial.md`).

Community needs events too (groups' meetups), but so do businesses, places and members. One tool serves all of them,
the same way one uploader (0127) serves every upload.

## Decision

### 1. One kind of event, one store

Every event is the same kind of record, whoever made it, in one `events` store. The crawler's Live events become one
**source** feeding it, not a second list. An event found by the crawler and posted by its host is **one event**: the
host's words win, and the crawler's page is kept as a source (0133: never overwrite what a person entered). Same venue,
same day and a near-identical title count as the same event.

### 2. Who creates events

| Host | Who in it can post | Notes |
|---|---|---|
| **A business or listing** | Owner, Manager, Creator; Sales for offers-led events (0102 §4 roles) | Shows "Hosted by <listing name>". Self-generated partner content: publishes at once after the automated checks (0005) |
| **A community group** | Owner and moderators; members suggest, moderators approve | Public or members-only (section 4) |
| **A member** | Any signed-in member with **Verification** on **(Jason)**, as for running a community (0062) | An event invites strangers to a real place, so it needs the same accountability |
| **Traversence** | Content and Operations staff, for places (a town's festival, a park's program) | Shows "Listed by Traversence" with its source |
| **The crawler** | Live events (0133), and later a business's connected Facebook Page (0133) | Shows its source page; held for a person when the checks say so |
| **A tribal nation** | Its own account, once its tools exist (0066) | A nation's ceremonies and sacred observances are never listed unless the nation posts them itself |

### 3. What an event holds

- **What:** title, a short description, a kind (Festival, Market, Music, Arts, Sports & rodeo, Outdoors, Food & drink,
  Family, Faith, Learning, Meetup, Business, Volunteering, Civic), photos through the one uploader (0127, target
  `event`), @tags (0132).
- **When:** start and end (date, time or all day), and repeats: weekly, monthly, yearly or a season. Each date of a
  repeating event can be cancelled or changed on its own.
- **Where:** a venue listing, a landmark or outdoor place, a place (town or area), or online. A private home's address
  shows only to people going (section 6).
- **Cost:** free, a price, or a ticket link. Selling tickets through Traversence waits for the Market's gateways
  (`aos-gap-review.md` step 6) and its own decision.
- **Who for:** all ages, 18+ or 21+.
- **Host and sources:** who runs it, shown; where the details came from (0062 identity record).

### 4. Who can see it

| Visibility | Who sees it | Where it shows |
|---|---|---|
| **Public** (the default) | Anyone | Everywhere in section 5 |
| **Members only** | The group's members | The group page and members' feeds; never searched, never on place pages (like group posts, 0053) |
| **Invited** | People the host invites (a circle, 0047 §4, or chosen connections) | Their feeds and messages only |

### 5. Where a public event shows

- **Its own page** `/event/?id=…`, built to the content-page standard (0058 §20–21): hero, header with the kind
  (searches), the title, the place and the Link button; the engage bar; comments (public, 18+, reportable); views
  counted (`tv_count_item('event:<id>')`).
- **The venue's listing** ("Coming up", as today), **the place's page and guide** (its Events section), and **the
  host's page** (listing, group or profile).
- **The calendar** `/events/`: by place and date. Filters live in the search (0058 §11). Ordered by date and distance,
  **never by payment** (0007): no boosted or sponsored events.
- **Search:** the Events kind, and When words: today, tonight, this weekend, a day, a season (0053, 0060, 0061).
- **Feeds:** people Linked to the host (0047) and the group's members, under their own notification settings.
- **Things to do → Events** (0058) and **Events** in the header (0055) switch on.
- **GO Codes** and short links (trvgo.us) can point to an event.

### 6. People's part

- **Going / Interested**, with counts shown. Who is going is seen only by the host, and by other members inside a group
  event **(Jason)**. Never where anyone lives.
- **Add to calendar** (an .ics file) and reminders through the Notification Center, if the person wants them.
- **Changes reach people:** a time, place or cancellation is sent to everyone going or interested.
- **Report** an event (spam, unsafe, a sacred or restricted site, someone's private information, other), as for posts.

### 7. Checks before an event shows

The same checks Live events already make, applied to every event:
- the date is ahead; the place exists; the venue matches our listings where it can;
- **0066:** a name that may mark a sacred, burial or protected site waits for a person (`Landmarks::SENSITIVE`); pins
  on nation land move to a public place (`Nations::pin()`), with the nation's visitor rules shown;
- spam and safety checks; a business's events per day capped by plan; repeated identical events held;
- three reports hide an event until an admin decides (as for posts). Our team can take one down, and **it stays down**
  (0133).

### 8. After it's over, and when the host ends

- An event becomes **Past** the day after it ends. Past events stay on the host's page as its history; they leave the
  calendar, search and place pages.
- A repeating event rolls on to its next date.
- **Cancelled** events show as cancelled until their date, and everyone going is told.
- When a host ends (a group closes, a business closes, an account is deleted; 0103), its future events are cancelled with
  notice. A listing going back to unclaimed keeps its Traversence- and crawler-listed events.

### 9. Where events are run

- **Admin: one Events section** in Let's Explore, replacing Live events: every event from every source, waiting ones
  first, with its source and checks. Opened over the work (`at_hand`) from Social, Local and Growth. Social → Events
  ("coming") is removed **(Jason)**. Reports on events go through Social → Reports like any content.
- **Business portal:** an Events section (create, repeat, cancel, who's going).
- **Group page:** an Events tab. **Member dashboard:** "Your events" (going, hosting).
- **System Configuration → Content templates → Live events** stays as is: it shapes the crawler's source.

## Data (sketch)

| Table | Key fields |
|---|---|
| `events` | host kind and ref, title, about, kind, starts, ends, all day, repeat rule, venue (listing, landmark, outdoor place, place key or online), address shown to going only, cost, age, visibility, status (draft, live, held, cancelled, past, removed), why |
| `event_dates` | each date of a repeating event, and its own changes or cancellation |
| `event_sources` | the event, the source (host, crawler page, staff, Facebook Page), when seen |
| `event_rsvps` | the event (or date), the person, going or interested, reminder wanted |

`live_events` moves into `events` (with its sources) by a migration that's safe to run twice; `LiveEvents` then writes to
`events`.

## Build order

1. The `events` store, with Live events moved in; the event page; events on listings, places and guides from the new
   store.
2. The calendar and search (Events kind, When words).
3. Business and staff events (business portal, admin section).
4. Group and member events; visibility.
5. Going / Interested, reminders, .ics, change notices.
6. Repeating events.
7. Later: tickets through the Market; events from a connected Facebook Page.

Each step adds its FAQ entries (0057).

## For Jason

1. Members' events need Verification on?
2. Who sees who's going: the host only, or also other members in a group event?
3. One admin home in Let's Explore, with Social → Events removed?
4. The event kinds in section 3: right list?
