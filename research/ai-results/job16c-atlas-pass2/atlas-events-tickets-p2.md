# research/platform-atlas/events-tickets.md

*Draft for human review. Sources: `decisions/0134`, `0133`, `0136`, `0137`, `0180`, `site-audit/pages.md`, `prd.md`, `charter.md`, `brand.md`, and three code files: `app/lib/LiveEvents.php` (shown in full, 273 lines), `website/community/index.php` (shown in full) and `website/community/group.php` (truncated at line 349). No other events or ticket code was attached. Anything else is marked "not shown".*

---

## (a) What each person can do, page by page

**A note on routes.** The designed event routes, `/event/?id=` and `/events/` (0134 §5, §9), do **not** appear in `pages.md`. They are not in its Live tables and not in its "Coming" table. Below, they are treated as designed only.

**Resident (Get Local)**
- **A listing's page, `/listing/view.php?id=` (Live).** Shows "Coming up" events at that venue. The data comes from `LiveEvents::forEntity` (`LiveEvents.php:219-225`). The listing page's own code is not shown.
- **A guide's Events section.** Lists what is coming up in a place and its towns (`forPlace`, `:206-216`; `keysFor`, `:198-203`). The guide page route and code are not shown.
- **Takedowns, `/takedown.php` (Live).** Anyone can ask for something to be taken down (0133 item 11). Whether this is wired to events is not shown.

**Traveler (Let's Explore)**
- **Event cards in guides.** Each card shows the date, title, when, venue, price and summary (`html()`, `:236-253`).
- The title links out to the page that announced the event, with `rel="noopener nofollow"` (`:244`).
- The venue links to its listing when one is matched (`:242`).
- **Designed, not built:**
  - Things to do → Events (0058).
  - The header's Events entry (0055).
  - Search's Events kind and its When words, such as "tonight" and "this weekend" (0134 §5).

**Member**
- **`/community/` (Live).** Find, join or start a group. The ground-rules text mentions "Public places and events only" (`community/index.php:91`).
- **`/community/group.php?id=` (Live).** Shows members, posts this week, the join mode and the group's start date (`group.php:84-89`).
  - There is **no events block** in the visible code.
  - 0137 Step 2 lists events as "Not yet".
- **`/market/ticket.php` (Live).** "A ticket bought on the Marketplace", per `pages.md`. Code not shown.
- **`/user/orders.php` (Live).** Code not shown.
- **Designed (0134 §6, §9):**
  - Going / Interested.
  - The who's-going setting.
  - Hiding your own name from that list.
  - Adding an event to your calendar (.ics) and reminders.
  - Dashboard → "Your events".
- **Hosting.** A verified member can host RSVP and free-registration events only, with no charging (0134 §2, §6b).

**Business**
- **`/business/` and `/listing/businessportal.php` (Live).** No Events or Ticket Sales section is shown in attached code.
- **`/market/item.php`, `/market/cart.php`, `/market/order.php` (Live).** These are the Marketplace path that the pilot's ticket item rides on (0134 note, 2026-11-27).
- **Designed:**
  - Business portal → Events.
  - Ticket Sales onboarding (6 steps).
  - Door check-in on Core and up.
  - Participating businesses for an event (0134 §6a–6b, notes).

**Admin**
- **Live events review.** 0134 §9 calls Let's Explore → Live events "built", and `LiveEvents::list`/`setStatus` support it (`:256-271`). The route is not shown. It is presumably under `/admin/router.php` (`/admin/<module>/<section>`), but that is unconfirmed.

---

## (b) How it works underneath

### The crawler's Live events (`app/lib/LiveEvents.php`)

**Storage**
- Two tables: `live_events` and `live_event_places`. `ready()` probes both (`:19-24`).
- Four statuses: Showing, Waiting for a person, Past, Taken down (`:17`).

**Template**
- `ContentTemplates::get('live_events')` is layered over the code defaults (`:27-34`).

**Schedule (`tick`, `:44-72`)**
- Runs only when the template status is `active` (`:49`).
- Runs only while `AiClient::allow(..., 'live_events')` passes (`:51`).
- Covers places whose guides have tiers 4 and 5 by default (`:53`).
- Each place is refreshed every `refresh_days`, default 7, through the `Clocks` W10 cutoff (`:55`).
- At most 5 places per run (`:58`).
- A failed check is retried after one day, not after the full interval (`:65-69`).

**Finding events (`compose`, `:81-134`)**
- Reads official sources first: the place's website from its dossier, otherwise its towns' sites (`sites`, `:180-187`).
- Reads up to 4 event pages through `Dossier::eventPages` (`:94`).
- Lists our own venues from four guide sections: do, eat, stay, shop (`:190-195`).
- Prompt rules:
  - never guess a date (`:101`);
  - cite the announcing page (`:102`);
  - list a sacred ceremony only if the nation or parish announces it as public, with no directions (`:103`).
- The prompt is capped at 50,000 characters (`:108`).
- Uses a strict JSON schema (`:118-119`) through `AiClient::claudeAsk`.
- Cost is recorded with `AiClient::record` (`:120-121`).
- Modes: `preview` spends nothing (`:111-117`); `try` does not save (`:128`).

**Checks (`check`, `:141-163`)**
- Must have a name and an ISO date.
- Must not already be past and must fall within the window (default 120 days, `:91`).
- The source must be an http(s) URL (`:152`).
- The venue is matched by exact name to an unsuppressed entity inside the place (`:154-156`).
- If `Landmarks::isProtected` matches, the event's status becomes `held` (`:159-162`).

**Saving (`keep`, `:166-177`)**
- Deduplicated by md5 of place, title and start date (`:168`).
- **Once taken down, it stays down** (`:170`).
- A held row stays held on refresh (`:174`).

**Expiry and display**
- `expire` marks events past the day after they end (`:37-41`).
- `forPlace` orders soonest first and removes duplicates across a community and its towns (`:211-214`).

**Admin actions**
- `setStatus` allows only `live` and `removed`, records who removed it and clears the guide cache (`:256-263`).

### Designed but not shown in code
- One `events` store with `event_dates`, `event_sources`, `event_rsvps`, `event_tickets` and `event_ticket_codes`.
- A migration that moves `live_events` into it (0134 Data).
- The 2026-11-27 note **defers the merge to Q1**. Live events stay separate for Jan 1.

### Tickets
- A ticket is a catalog item of kind "Class or event", later "Event tickets", sold through the order system (0098) (0134 §6a; 0141 §5).
- The 0134 note cites `ItemKinds.php:23`. That file is not shown.
- Checkout for the pilot is `MarketCheckout::writeOrder`. Not shown.

### Email
- Tickets and event changes go out as SES per-person mail (0137 §8).
- `SesMail` is built in mock mode (0137 Step 5). Not shown in code.

---

## (c) The decisions and what each rules

**0134 Events, one tool for the whole site (Accepted, design)**
- **§1:** one store; the host's words win over the crawler's.
- **§2:** who may host. Verification is required. A nation's ceremonies appear only if the nation posts them.
- **§3:** what an event holds. **No outside ticket links and no "pay at the door" notes.** The crawler's source page is a citation, not a ticket link.
- **§4:** visibility.
- **§5:** where events show, ordered by date and distance, **never by payment (0007)**.
- **§6:** RSVP and privacy of who is going.
- **§6a:** tickets on the existing order system, with a money table by plan.
- **§6b:** Ticket Sales module.
  - Monthly limits: Free 50, Basic 150, Core 250, Strategic and above unlimited.
  - 0% fees.
  - Members and person-run groups get RSVP only.
- **§7:** checks before an event shows.
- **§8:** past and cancelled events.
- **§9:** three homes for events. The admin sees system events only.
- **Amendment (Eventbrite):** connect (option B) or move to Traversence (option C).
- **Note, 2026-11-27 (Hometown theater pilot):**
  - minimum build before Jan 1;
  - Live events stay separate;
  - participating businesses get exposure only, and only the host's tickets sell.

**0133 (superseded by 0164)**
- The crawler's rule book: Live events and the takedown path.
- Event clocks are "every month" (Phase 6). The code runs weekly (`:55`); 0164 W10 governs. 0164 itself is not attached.

**0164** — amends 0134 (W10, R16). Not attached.

**0136 §5**
- Tickets are shared from group posts with Buy now.
- Events are "the front door" to groups.
- No outside selling, except 0134's option B.

**0137**
- **§3:** the charter's ground rules include "no selling outside Traversence".
- **§6:** notices to people going when an event changes; the 80% ticket-limit notice.
- **§7:** no cap on events posted (this replaces 0134 §7's per-day cap); tickets stay capped.
- **§8:** SES carries ticket and event-change mail; ticket mail doesn't count against a business's allowance.

**0180 §4.1**
- The hard money line: real charges and refunds always need a person.
- Ticket refunds fall under it, with 0178's "with Jason's OK".

---

## (d) Live vs coming

| Item | Status | Evidence |
|---|---|---|
| Crawler Live events: find, check, hold, take down, show on guides and listings | Built in code; running depends on template status and AI allowance | `LiveEvents.php:44-72`, `:236-253` |
| `/market/ticket.php`, `/market/item.php`, cart, order | Live | `pages.md` §1 |
| `/community/`, `/community/group.php` | Live, with no events section in the visible code | `group.php:84-89` |
| Group notices and SES sending | Built; SES in mock mode | 0137 Steps 4–5 |
| `events` store, `/event/` page, host-posted events (pilot "option a") | Planned for ~Dec 10–12; build status not shown | 0134 note |
| Participating businesses (invite, accept, trail) | 0134 says "stay as built in P1"; code not shown | 0134 note |
| `/events/` calendar, Going/Interested, repeats, Ticket Sales module, door check-in, merge of Live events | Coming (Q1 for the merge and the full tool) | 0134 build order and note |
| Card checkout through a business's own gateway, Eventbrite B/C | Waits for the Market gateway step | 0134 §6a, amendment |
| Event notices (changes, ticket limit) | Not yet | 0137 Step 4 "Not yet" |

---

## (e) Charter promises, economics and gaps

### Immersion and connection
*Charter: "Connect Communities — link residents, local businesses, and regional events into a single, accessible network."*
- Events tie into guides and venue listings (`:206-225`).
- The pilot's "Dinner and a movie" trail links a theater to nearby restaurants (0134 note).
- 0136 §5 makes events the front door to groups.
- **Gap:** group pages show no events yet. The "next event" item in 0137 §4's at-a-glance is missing from `group.php:84-89`.

### True locality
*Charter: "local relevance that feels grounded."*
- Official local sources are read first (`:93-94`).
- Venues are matched to our own listings inside the place (`:154-156`).
- Events are scoped to the place and its towns (`:198-203`).
- **Gap:** venue matching is exact-name only (`e.name = ?`, `:155`), so near-miss spellings won't link.

### Discovery that can't be bought
*Charter: "a system where visibility can [not] be bought," Transparent, Non-Manipulative Discovery (0007).*
- `forPlace` orders by `starts_on, title` only (`:211`). No payment field exists in the shown schema.
- 0134 §5 and §6a: selling tickets never moves an event up.
- Participants appear by invitation, never by paid placement (0134 note).

### Frictionless agency
*Charter: "spend time exploring rather than fighting a bloated UI."*
- Cards say when an event is in plain words (`when`, `:228-233`).
- The designed .ics export, reminders and door scanning are not built.
- **Gap:** the event card's styles are hard-coded hex values (`#FFFBF2`, `#F7EFDC`, `#92400E`; `:248-252`). They are not the `--tv-*` tokens that `brand.md` §4 (2026-11-18/19) binds every page to.

### Data dignity
*Charter: "data minimization, clear boundaries."*
- Designed protections: who's going is host-only by default, each person can hide their own name, and no one's home is ever shown (0134 §6). A private address is shown only to people going (§3).
- Views are counted, never who viewed (0058 / 0134 §5).
- Protected-place names are held for a person (`:159`), consistent with 0066 and 0133's protected tiers.
- **Possible tension for review:** the crawler prompt lists ceremonies a "nation **or parish**" announces as public (`:103`). 0134 §2 says a nation's ceremonies are never listed unless **the nation posts them itself**.

### Economic results

**For businesses**
- 0% system fees. Plan tiers pay, not ticket money (0134 §6b).
- Free plans can sell 50 tickets a month with manual payment.
- Outside-ticket events the crawler finds become Ticket Sales leads in Growth → Prospects.
- Pilot participants get exposure plus a GO Code routing customers to their location, with no e-commerce bar to clear.

**For members**
- Free RSVP and registration.
- The path to charging is to create a listing (a free one is enough) — "how a group becomes a business".

**For the platform**
- Revenue comes from plan upgrades driven by the monthly limits and by the QR and door tools from Core up.

### Gaps and questions for review
1. `/event/` and `/events/` are absent from `pages.md`, even from its "Coming" table. That breaks `pages.md`'s own rule that a release adding a page updates the register.
2. The crawler's `price_text` is free text shown on cards (`:161`, `:245`). Nothing shown filters "at the door" wording, which 0134 §3 forbids for hosted events. It may not apply to crawler citations; this needs a ruling.
3. Card checkout for Core waits on the Market gateway. Until then, every business uses manual payment.
4. The tenant switch in `MarketCheckout::writeOrder` is an open Founder question (0134 note).
5. A real ticket refund always needs a person (0180 §4.1, 0178). No refund code is shown.
6. SES is in mock mode, so ticket and event-change emails are not actually sent yet (0137 Step 5).
7. The cadence differs across documents: 0133 says monthly, the code says weekly (`refresh_days` 7). The code reads the 0164 W10 clock, which is not attached.
8. The Live events admin route and the listing and guide render points are not shown.