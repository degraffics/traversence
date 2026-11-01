# ADR 0134: Events, One Tool for the Whole Site

**Status:** Proposed (2026-11-01), direction from Jason: "Event creation is a site-wide tool, not just community."
Design; not built. Jason's answers of 2026-11-01 are in: Verification, who's going, and tickets (section 6a). Builds on `decisions/0133` (Live events), and fills the "events, when they exist" gaps left in
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
| **A member** | A member with **Verification** on (0062), as for running a community | Jason, 2026-11-01: "event creation requires a verified user's account at minimum." Every host's poster is a verified member; a business also needs its claim |
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
- **Cost:** free, free with registration (a limited number of places), tickets sold through Traversence (section 6a),
  or a link to tickets sold elsewhere.
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

- **Going / Interested**, with counts shown. Registering or buying a ticket counts as Going.
- **Who sees who's going is set by the host when creating the event** (Jason, 2026-11-01), and can be changed later
  with notice to those going:
  - **Host only** (the default);
  - **Everyone going** (people who are going see each other);
  - **Anyone who can see the event**.
  Each person can still hide their own name from the list (anything about a member is private unless they say); the
  count always includes them. Never where anyone lives.
- **Add to calendar** (an .ics file) and reminders through the Notification Center, if the person wants them.
- **Changes reach people:** a time, place or cancellation is sent to everyone going or interested.
- **Report** an event (spam, unsafe, a sacred or restricted site, someone's private information, other), as for posts.

### 6a. Tickets and registration, on the order system we already have

Jason, 2026-11-01: we have the ecommerce framework, so buying is part of the event. It is: a ticket is a catalog item
sold through the order system, with nothing new needed for the money.

**What's already built and used:**
- **The order system** (0098): one order record whatever it sells, an append-only money ledger, refunds, credits,
  deposits and chargebacks, and the order's history.
- **The business catalog** (0116): it already has the kind **"Class or event"** (`Products::BUSINESS_KINDS`).
- **A business's own sales settings:** tax rates, pay link and other ways to pay (0118, 0119), its own discount codes
  (0121).
- **Our own checkout** through QuickBooks (0095): tax from the buyer's address, paid on QuickBooks' page, no card
  on our server.

**How it works:**
- **Ticket types** on the event: a name (General, Child, VIP, Table for 8), price, how many, when sales open and close,
  a limit per order. Each is a "Class or event" item in the host's catalog, tied to the event (and to one date of a
  repeating event).
- **Registration** is a free ticket type: the same tool, for events that need a head count or have limited places.
- **Buying:** the buyer picks tickets, uses the host's discount code if they have one, pays, and the order lands on the
  ledger with its tax. Each ticket gets a code (a QR) shown in the buyer's account and email.
- **Sold out** closes the type; a **waiting list** (later) offers places that come back.
- **At the door:** the host opens the event's check-in on a phone, scans or types the code, and it's marked used.
- **Refunds:** the host's refund terms are shown before buying; refunds and credits run through the order system's
  policy (0098). **A cancelled event refunds or credits everyone**, the buyer's choice where the host allows a credit.

**How the money moves (the rules the Market already set):**

| Host | Paid by | Status |
|---|---|---|
| Traversence (our own events, workshops) | Our QuickBooks checkout (0095) | Built: usable at step 1 |
| A business, Free or Basic plan | **Manual payment**: its pay link, Zelle, check, or pay at the door, marked paid by the business (0118, 0119) | Built for quotes and invoices; the same way for tickets |
| A business, Core and up | **Card checkout through the business's own gateway**, straight to the business, 0% system fees, no money held by us | The gateway step of the Market (`marketplace-architecture.md`): until it's built, Core businesses use manual payment too |
| A member, or a group run by a person | **No ticket sales: RSVP and free registration** (section 6b) | |
| A business-run group | The business's own checkout, as above | |

So the only part that waits is card checkout through a business's own gateway, which waits for every Market sale, not
just tickets. Everything else can be built with the events.

- **Never paid placement:** selling tickets never moves an event up the calendar or search (0007).

### 6b. Who needs what: RSVP for people, tickets as a business tool that grows with the plan

Jason, 2026-11-01: we make our money on scaling access and tools, so tickets follow that. We take no cut of ticket money
(0% system fees, as for every Market sale), so a ticket sale earns us nothing by itself: the plan is what pays, and each
step up has to save the host real work or bring in more money.

**Members and groups run by a person: RSVP, not tickets.** What they need is a head count and a reminder: a potluck, a
trail clean-up, a club meetup. They get everything in section 6 (Going / Interested, free registration with a limit on
places, reminders, the who's-going setting) and a plain cost note ("$10 at the door") or a link to tickets sold
elsewhere. Traversence handles no money for them. A club or cause that wants to sell tickets gets a listing (a free
one is enough to start): that's the way in.

**Posting events is never limited by plan.** Every public event fills the calendar, places and guides, which is
what Let's Explore needs; only the per-day spam cap applies (section 7). The ladder is in the ticket tools:

| Plan | Ticket tools (proposed limits) | The reason to move up |
|---|---|---|
| **Free** | **Try it:** 1 ticketed event at a time, up to 50 tickets, manual payment (marked paid by the business), the guest list | Sells out a small event; the guest list shows who came |
| **Basic** $29.95 | 3 ticketed events at a time, up to 250 tickets each; QR tickets and check-in at the door; discount codes, taxes, order emails, customers (all already Basic) | Runs several events and the door without a paper list |
| **Core** $79.95 | **Card checkout through its own gateway** (paid straight to the business, at once); unlimited ticketed events and tickets; repeating series; waiting lists; the event's QR code and short link (trvgo.us); tickets on its storefront and own domain; tags and segments for attendees | Money in hand when the ticket sells, and no more chasing payments |
| **Strategic** $229.95 | Attendees into the CRM and leads pipeline; campaigns to Linked followers and past attendees (the plan's email, SMS and push volume); automations (reminders, thank-yous, "next time" offers); tickets sold on its own site (widgets, API); AI help with event pages | Fills the next event from the last one |
| **Cornerstone** $399.95+ | Events across locations; joint events with other businesses (a festival with many vendors, a Co-Op's market day) with shared ticketing; door-staff roles; pooled communications | Runs the region's big events |

- **What counts:** "ticketed events at a time" are upcoming events with paid tickets on sale. Free registration
  (a head count) doesn't count, on any plan.
- **A business-run group** sells tickets under its business's plan.
- **Our own events** (Traversence) use our QuickBooks checkout and no plan.


### 7. Checks before an event shows

The same checks Live events already make, applied to every event:
- the date is ahead; the place exists; the venue matches our listings where it can;
- **0066:** a name that may mark a sacred, burial or protected site waits for a person (`Landmarks::SENSITIVE`); pins
  on nation land move to a public place (`Nations::pin()`), with the nation's visitor rules shown;
- spam and safety checks; a business's events per day capped by plan; repeated identical events held;
- three reports hide an event until an admin decides (as for posts), through Social → Reports. Our team can take one
  down, and **it stays down** (0133).

### 8. After it's over, and when the host ends

- An event becomes **Past** the day after it ends. Past events stay on the host's page as its history; they leave the
  calendar, search and place pages.
- A repeating event rolls on to its next date.
- **Cancelled** events show as cancelled until their date, and everyone going is told.
- When a host ends (a group closes, a business closes, an account is deleted; 0103), its future events are cancelled with
  notice. A listing going back to unclaimed keeps its Traversence- and crawler-listed events.

### 9. Where events live: three homes, for three kinds of people

| Home | For | What it shows |
|---|---|---|
| **The Events page** `/events/` (public) | Visitors and members | Every public event: by place, date and kind, with the calendar view; Things to do → Events and the header's Events open it |
| **Your own events** | Whoever hosts or goes | **Members:** dashboard → "Your events" (hosting, going, tickets). **Businesses:** business portal → Events (create, repeat, cancel, ticket sales, who's going, check-in). **Groups:** the group page's Events tab, and the Steward dashboard |
| **The admin's Events section** | Our team | **System events only** (Jason, 2026-11-01): what the crawler found, the events Traversence lists for places, and our own events with their ticket sales |

**What our team doesn't see.** Hosts run their own events; the admin doesn't list every business's, group's or
member's events. Our team reaches one only when:
- it's **reported** (Social → Reports, as for any content), or held by the checks in section 7; or
- the host asks for help: a **support ticket** of the type "Event help" in the support queue (0102 §5), giving the
  agent timed access to that business's events with the role the ticket type sets. (Event tickets and support tickets
  are different things; the admin always says "support ticket" for the second.)

**The admin's two event places today, and what becomes of them:**
- **Let's Explore → Live events** (built) reviews what the crawler finds. It becomes **Let's Explore → Events**: the
  crawler's events, Traversence-listed events and our own, waiting ones first. Content templates → Live events stays as
  the crawler's settings.
- **Social → Events** ("coming") was drawn when events were planned as a Community feature: "group and business
  events, and a public calendar". Hosts now run those themselves and the public calendar is `/events/`, so it has no
  job left: **it's removed**, and Social keeps Reports.
- **System Configuration → Content templates → Live events** stays as is: it shapes the crawler's source.

## Data (sketch)

| Table | Key fields |
|---|---|
| `events` | host kind and ref, title, about, kind, starts, ends, all day, repeat rule, venue (listing, landmark, outdoor place, place key or online), address shown to going only, cost, age, visibility, status (draft, live, held, cancelled, past, removed), why |
| `event_dates` | each date of a repeating event, and its own changes or cancellation |
| `event_sources` | the event, the source (host, crawler page, staff, Facebook Page), when seen |
| `event_rsvps` | the event (or date), the person, going or interested, name hidden or shown, reminder wanted |
| `event_tickets` | a ticket type: the event (or date), the catalog item, price, how many, sales window, per-order limit |
| `event_ticket_codes` | each ticket sold: its order line (0098), its code, used at |

`live_events` moves into `events` (with its sources) by a migration that's safe to run twice; `LiveEvents` then writes to
`events`.

## Build order

1. The `events` store, with Live events moved in; the event page; events on listings, places and guides from the new
   store.
2. The calendar and search (Events kind, When words).
3. Business and staff events (business portal, admin section).
4. Group and member events; visibility.
5. Going / Interested, who's going settings, reminders, .ics, change notices.
6. **Tickets and registration** on the order system: Traversence's own and manual payment first; check-in.
7. Repeating events.
8. Later: card checkout through a business's own gateway (with the Market's gateway step), waiting lists, events from
   a connected Facebook Page.

Each step adds its FAQ entries (0057).

## For Jason

Settled 2026-11-01:
1. **Verification:** required to create an event.
2. **Who's going:** the host chooses at creation.
3. **The admin:** system events and event support only (section 9); Let's Explore → Events, Social → Events removed.
4. **Event kinds:** one required kind plus @tags: Festivals & fairs; Markets; Music & performance; Arts & culture;
   Rodeo & sports; Outdoors & adventure; Food & drink; Classes & workshops; Family & kids; Faith; Community & civic;
   Meetups & social; Business & networking; Holidays & seasonal.

Still to confirm:
5. **The ticket ladder** (section 6b): members RSVP only, and the limits per plan.
