# research/platform-atlas/events-tickets.md

*Draft for human review. Sources: the attached docs (`charter.md`, `brand.md`, `prd.md`, `site-audit/pages.md`, decisions 0133, 0134, 0136, 0137, 0180) and three code files.*

**Coverage of the code:**

- `app/lib/LiveEvents.php` is shown in full (273 lines).
- `website/community/index.php` is shown in full.
- `website/community/group.php` is cut off at about line 349.

**Not shown:** every other library or page this area relies on, including `ItemKinds.php`, `Products`, the order system, `MarketCheckout`, the `/market/*` pages, the business portal, the guide and listing pages that call `LiveEvents`, `AiClient`, `Landmarks`, `Clocks` and `ContentTemplates`.

**Not attached but referred to:** decisions 0164, 0141, 0135, 0098, 0007 and 0181, `commercial.md`, and `marketplace-architecture.md`.

---

## (a) What each kind of person can do here, page by page

"Live" means the route is marked Live in `pages.md`. "Designed" means it appears only in an ADR.

### Resident or traveler (guest or signed in)

- **A place's guide, Events section.** Shows crawler-found events, soonest first.
  - Source: `LiveEvents::forPlace` (LiveEvents.php:206–216).
  - A community's guide includes its towns' events, and an event found twice is shown once (:198–203, :213–214).
  - Which guide route draws this section: not shown. `pages.md` lists `/hub/?hub=…` and `/hub/build.php`, but the calling code is not attached.
- **A venue's listing, "Coming up".** `/listing/view.php?id=` is Live. Events come from `LiveEvents::forEntity` (:219–225). That the listing page shows them is stated in 0134 Context; the call itself is not shown.
- **An event card.** Each card shows:
  - the title, linking to the page that announced it (`target="_blank" rel="noopener nofollow"`, :244);
  - the date, plus a time if one was given (:228–233);
  - the venue, linking to `/listing/view.php?id=` when matched (:242);
  - the price text and a summary (:245–246).
- **Buying a ticket.**
  - Live: `/market/ticket.php` ("A ticket bought on the Marketplace"), `/market/item.php`, `/market/cart.php` and `/market/order.php` (`pages.md` §1).
  - Their code is not shown.
- **Designed, not in `pages.md`:**
  - an event's own page, `/event/?id=…`, and the calendar, `/events/` (0134 §5, §9);
  - search's Events kind and When words;
  - Going / Interested;
  - Add to calendar as an .ics file.

### Member (signed in)

- **Orders.** `/user/orders.php` is Live ("Your orders"); its code is not shown. 0134 §6a says each ticket goes in the buyer's account and email.
- **Community groups.** `/community/` and `/community/group.php?id=` are Live. The group page has no event content in the code shown:
  - "At a glance" shows members, posts this week, join mode and since when (group.php:84–89).
  - 0137 §4 planned a "next event" there, plus an "Upcoming events" block.
  - 0137's Step 2 build note lists "events" under "Not yet".
- **The charter's ground rules** include "selling only through Traversence" (community/index.php:86). This mirrors 0134 §3.
- **Designed, not built:**
  - Dashboard → "Your events" (0134 §9).
  - Members may host only with Verification, and only with RSVP and free registration. They cannot charge (0134 §2, §6b).

### Business (owner and roles)

- **Business portal.** `/listing/businessportal.php` and `/business/` are Live. Their event features are not shown.
- **0134's 2026-11-27 pilot note** describes:
  - host-posted event pages on claimed listings, linking to the host's existing ticket item ("Event tickets, ItemKinds.php:23", file not attached);
  - participating businesses, each with an offer line and a GO Code.
- **Designed:**
  - business portal → Events (0134 §9);
  - the Ticket Sales module and its 6-step onboarding (§6b);
  - door check-in from Core up (§6a);
  - "B. Connect your ticketing / C. Move to Traversence" (amendment).

### Our team (admin)

- **Admin → Let's Explore → Live events**, said to be built (0134 Context, §9). Its route is not listed in `pages.md`, though `/admin/router.php` draws module sections. It lists held events first (`LiveEvents::list`, :266–271), and staff can show an event or take it down (`setStatus`, :256–263).
- **System Configuration → Content templates → Live events** holds the crawler's settings (0134 §9; `LiveEvents::template`, :27–34). From it, staff can preview and try a template (:75, :78).

---

## (b) How it works underneath

### The crawler's Live events (`app/lib/LiveEvents.php`)

**Store**

- Tables `live_events` and `live_event_places`. Their presence is tested in `ready()` (:19–24).
- Statuses (:17): `live` (Showing), `held` (Waiting for a person), `expired` (Past) and `removed` (Taken down).

**Scheduler (`tick`, :44–72)**

1. Expires past events first. An event expires once both its end date (or its start date, if it has no end) are before today (:37–41).
2. Runs only when the template's status is `active` (:49) and `AiClient::allow($pdo,'live_events')` passes (:51).
3. Picks places that have a live guide and are due under clock `W10` (`Clocks::sqlCutoff`, :55), oldest check first.
4. Covers only the template's tiers (default `[4,5]`) on a default 7-day refresh (:53).
5. Handles at most 5 places a run (:58).
6. If a service errors, that place is retried after one day (:65–69).

**Gathering (`compose`, :81–134)**

- **Free sources first.** It reads the place's official site, or its towns' official sites, through `Dossier::eventPages`, up to 4 pages (:93–94, :180–187).
- **Venue list.** It builds a list of our venues from the guide's do/eat/stay/shop picks (:190–195).
- **Prompt rules:**
  - events dated only from today out to the horizon (default 120 days, :91);
  - every event needs a page that announces it;
  - a sacred-place ceremony is listed only if publicly announced, and never with directions (:101–105);
  - the prompt is capped at 50,000 characters (:108).
- **The call.** Claude receives a fixed JSON schema of name, dates, time, venue, price, summary and source (:118–120). The cost is recorded by `AiClient::record` (:121).

**Checks (`check`, :141–163)**

- An event is dropped if it has no name, no date, is already past, falls beyond the window, or has no http(s) source page.
- The venue is matched by exact name to an unsuppressed entity inside the place (:153–156).
- `Landmarks::isProtected` on the name, venue and summary sets `held` (:157–162).

**Saving (`keep`, :166–177)**

- An event's identity is a hash of place, lowercased title and start date (:168).
- An event staff removed stays removed (:170).
- A held event stays held when it is re-found (:174).

**Drawing (`html`, :236–253)** uses inline CSS with hardcoded hex colours.

### Groups, the future hosts of group events

- `community/index.php` calls `/user/api/community.php` (index.php:98).
- `group.php` draws the hero and engage bar (`tv_explore_hero`, `tv_engage_bar` kind `group`, group.php:69, :83) and counts views (`tv_count_item('group:'…)`, :67).

### Tickets, from the ADRs only (no code attached)

- A ticket is a "Class or event" catalog item (0116, `Products::BUSINESS_KINDS`) sold through the order system and its ledger (0098).
- Tickets are paid through QuickBooks (0095) for Traversence's own events, or by manual payment (0118, 0119) for businesses. Card checkout through a business's own gateway waits for 0135's gateway step (0134 §6a).
- From 2026-11-03, 0141 §5 makes them the catalog's "Event tickets" kind.

### Email for tickets

0137 §8 routes a business's ticket mail and event-change mail through Amazon SES. 0137's Step 5 note says SES runs in **mock** mode until AWS grants production access.

### Data model (designed)

0134 sketches the tables `events`, `event_dates`, `event_sources`, `event_rsvps`, `event_tickets` and `event_ticket_codes`. `live_events` is to be moved into `events` by a migration.

---

## (c) Decisions

| Decision | What it rules here |
|---|---|
| **0134** Events, one tool | One `events` store, with crawler events as one source (§1). Who hosts, and Verification required (§2). Fields; no outside ticket links and no pay-at-the-door notes (§3). Visibility: public, members-only, invited (§4). Where events show, never ordered by payment (§5). Going/Interested and the host's who's-going setting, default host only (§6). Tickets on the order system (§6a). The Ticket Sales module: Free 50, Basic 150, Core 250, Strategic and up no monthly limit, 0% cut (§6b). Checks before showing (§7). Past and cancelled (§8). Three homes; Social → Events removed (§9). Amendment: Eventbrite options B and C. 2026-11-27 notes: the Hometown theater pilot, participating businesses, "the organizer sells, participants get exposure". |
| **0133** (superseded by 0164) | The Live events template and crawler rules. Staff takedowns stay down. Events are kept monthly by the date check. |
| **0164** | Amends 0134 through §7 (W10) and R16, and rules where they disagree. **Not attached.** |
| **0137** | §6: event-change and ticket-limit notices, sent once. §7: no cap on how many events anyone posts; tickets stay capped by plan. §8: tickets and event changes go by SES on the per-person account. |
| **0136** | §5: events as a group's "front door". "Selling in groups": tickets share like items, and the Eventbrite (B) exception. |
| **0180** | Orders, checkout and refunds are high-risk territory 1. The hard money line (§4.1): no agent executes a real-money charge or refund. |
| Cited but not attached | 0007 (no paid placement), 0098, 0116, 0118, 0119, 0121, 0095, 0135, 0141 §5, 0066, 0058 §20–21, 0174, 0172 §3.3, 0110, 0181 (Jan 1 gates). |

---

## (d) Live vs coming

### Live, per the code or `pages.md`

- Crawler-found events, with checks, holds, expiry, takedowns and preview/try (LiveEvents.php in full).
  - The tick runs only while the template is `active` and the AI budget allows it (:49, :51), so whether events are actually being found in production is not shown.
- Marketplace ticket, item, cart and order pages, and `/user/orders.php` (`pages.md`). Code not shown.
- Community groups with charters (index.php, group.php), with no event features.

### Built per the ADRs; route not in `pages.md`

- Admin → Let's Explore → Live events (0134 Context).

### Coming

- **Before about Dec 10–12, per 0134's pilot note:**
  - host-posted event pages (`/event/`) for business hosts;
  - free events;
  - a link to the host's ticket item;
  - participants with a trail.
- **Gap to check.** The note says invitations and trail are "as built in P1", but `pages.md` has no `/event/` route, not even under §8 Coming.
- **Q1:** the merge of Live events into `events`, the `/events/` calendar, Going/Interested, repeating events, and the full Ticket Sales module (0134 pilot note).
- **Later:** card checkout through a business's own gateway, waiting lists, events from a connected Facebook Page, and Eventbrite options B and C (0134 Build order 8, Amendment).

### Unbuilt and named

- Group events and "Upcoming events" on the group page (0137 Step 2, "Not yet").
- Event notices (0137 Step 4, "Not yet").
- Live SES sending (0137 Step 5, mock mode).

---

## (e) Charter promises, economics and gaps

### Immersion and connection

*Charter, "The Mission & Purpose": "link residents, local businesses, and regional events into a single, accessible network."*

- Crawler events join guides to venue listings (LiveEvents.php:153–156, :242).
- Participating businesses and the trail tie a night out to nearby places (0134 pilot note).
- Gaps:
  - Venue matching is an exact name match only (:155), so an event at a listed venue with a slightly different name goes unlinked.
  - Groups, the Community track's "gatherings" (charter, "The Tri-Track Architecture"), cannot yet host events.

### True locality

*Charter, "True Locality & Context Without Noise".*

- Each place's own official site is read first, and the web is searched only for what it doesn't list (:92–94, :105).
- Events are scoped to the place and its towns (:198–203).
- Every event must cite an announcing page (:152).
- Gap: only guide tiers 4–5 are crawled by default (:53), so other places get no Live events.

### Discovery that can't be bought

*Charter, "Transparent, Non-Manipulative Discovery".*

- `forPlace` and `forEntity` order by date only (:211, :222). There is no payment field.
- 0134 §5 and §6a: "never by payment… selling tickets never moves an event up".
- 0136: participation by invitation, never paid placement.
- Gap: the `/events/` ordering by date and distance is unbuilt, so it can't be verified yet.

### Frictionless agency

*Charter, "Frictionless Agency".*

- Designed: .ics, reminders, change notices, and Add to a trip (0134 §6; 0174).
- Gaps:
  - None of these is built.
  - Search has no Events kind yet.
  - The crawler event's title link leaves the site for the source page (:244). This matches 0134 §3's citation exception, but it is the only action a card offers.

### Data dignity

*Charter, "Data Dignity and Control".*

- Who's going defaults to the host only; each person can hide their name; a private address shows only to people going; nothing ever says where anyone lives (0134 §3, §6).
- Live events store no personal data (:160–162).
- Sacred and protected names are held for a person (:157–162; 0134 §7).

### Brand gap

`html()` hardcodes colours (#FFFBF2, #EADFC4, #F7EFDC, #92400E, :248–252) instead of the tokens `brand.md` §4 binds every page to (`app/ui/theme-tokens.php`).

### Rule gap

Crawler events show `price_text` as written (:161, :245). A source that says, for example, "$10 at the door" could surface the kind of note 