# research/platform-atlas/events-tickets.md (merged)

*Draft for human review. This merges two independent passes, P1 and P2.*

**How to read the tags**
- **[both]**: both passes support the item.
- **[single-pass: P1]** or **[single-pass: P2]**: only one pass says it.
- **"passes differ"**: the passes disagree, and both versions are given.
- Each pass's own citations are kept. Where the line numbers match, they are cited once.

**Sources [both]**
- `charter.md`, `brand.md`, `prd.md`, `site-audit/pages.md`, and decisions 0133, 0134, 0136, 0137 and 0180.
- `app/lib/LiveEvents.php`, shown in full (273 lines).
- `website/community/index.php`, shown in full.
- `website/community/group.php`, cut off at line 349. P1 says "about line 349".

**Not shown [both]:** all other events and ticket code, including `ItemKinds.php`, `MarketCheckout`, the `/market/*` pages, the business portal and the guide and listing render points.

**Not attached [both]:** 0164 and 0141.
- P1 also lists 0135, 0098, 0007, 0181, `commercial.md` and `marketplace-architecture.md`, plus the code files `Products`, `AiClient`, `Landmarks`, `Clocks` and `ContentTemplates` [single-pass: P1].

**Note on P1:** P1's text is cut off partway through its section (e) "Rule gap" paragraph. Anything P1 might have said after that point is not available.

---

## (a) What each person can do, page by page

**Route note [both]:** The designed routes `/event/?id=` and `/events/` (0134 §5, §9) are not in `pages.md`. They are absent even from its §8 "Coming" table. Both are treated here as designed only.

### Resident or traveler (guest or signed in)

- **A guide's Events section [both].**
  - It lists events found by the crawler, soonest first, through `LiveEvents::forPlace` (LiveEvents.php:206–216).
  - A community's guide includes its towns' events, and duplicates are shown once:
    - P1 cites :198–203 and :213–214.
    - P2 cites `keysFor` :198-203 and :211-214.
  - The guide's route and code are not shown.
  - P1 notes that `pages.md` lists `/hub/?hub=…` and `/hub/build.php` [single-pass: P1].
- **A listing's "Coming up" section, `/listing/view.php?id=` (Live) [both].**
  - The data comes from `LiveEvents::forEntity` (:219–225).
  - The listing page's own call is not shown.
  - P1 cites 0134 Context for the claim that the listing page shows these events [single-pass: P1].
- **An event card [both].** It is drawn by `html()` (:236–253) and shows:
  - the title, linking to the announcing page with `rel="noopener nofollow"` (:244);
    - P1 also notes `target="_blank"` [single-pass: P1];
  - the date, plus a time in plain words when one is given (:228–233);
  - the venue, linking to `/listing/view.php?id=` when matched (:242);
  - the price and summary (P1 :245–246).
- **Buying a ticket [both].**
  - `/market/ticket.php` ("A ticket bought on the Marketplace"), `/market/item.php`, `/market/cart.php` and `/market/order.php` are Live (`pages.md` §1).
  - Their code is not shown.
  - Passes differ on which persona these pages sit under:
    - P1 puts them under resident/traveler.
    - P2 puts `ticket.php` under Member, and item, cart and order under Business, as the path the pilot's ticket item uses (0134 note, 2026-11-27).
- **Takedowns, `/takedown.php` (Live) [single-pass: P2].**
  - Anyone can ask for something to be taken down (0133 item 11).
  - Whether this is wired to events is not shown.
- **Designed, not built [both]:**
  - the event page and the calendar;
  - search's Events kind and When words;
  - Going / Interested;
  - adding an event to a calendar as an .ics file.
  - P2 adds three more designed items [single-pass: P2]:
    - Things to do → Events (0058);
    - a header Events entry (0055);
    - example When words, "tonight" and "this weekend" (0134 §5).

### Member (signed in)

- **`/user/orders.php` (Live) [both].** Its code is not shown.
  - 0134 §6a puts tickets in the buyer's account and email [single-pass: P1].
- **`/community/` and `/community/group.php?id=` (Live) [both].**
  - The group page's at-a-glance shows members, posts this week, join mode and the date the group started (group.php:84–89).
  - There is no events block in the visible code. 0137 Step 2 lists events as "Not yet".
  - 0137 §4 planned a "next event" item and an "Upcoming events" block [both; P2 mentions only "next event"].
- **The ground-rules text. Passes differ on the line and the quote:**
  - P1 cites "selling only through Traversence" at community/index.php:86 and says it mirrors 0134 §3.
  - P2 cites "Public places and events only" at community/index.php:91, and attributes "no selling outside Traversence" to 0137 §3.
  - These may be different lines that are both present. The source should be checked.
- **Designed [both]:**
  - Dashboard → "Your events" (0134 §9).
  - Verified members can host RSVP and free-registration events only, with no charging (0134 §2, §6b).
  - P2 adds the who's-going setting, hiding your own name and reminders (0134 §6) [single-pass: P2].

### Business (owners and roles)

- **`/business/` and `/listing/businessportal.php` (Live) [both].** No events or Ticket Sales features are shown in the attached code.
- **The 2026-11-27 pilot note [both] describes:**
  - host-posted event pages on claimed listings that link to the host's ticket item. The note cites `ItemKinds.php:23`, which is not attached.
  - participating businesses.
    - P1 says each gets an offer line and a GO Code [single-pass: P1].
    - P2 mentions the GO Code in (e).
- **Designed [both]:**
  - Business portal → Events (0134 §9);
  - Ticket Sales onboarding in 6 steps (§6b);
  - door check-in from Core up (§6a);
  - Eventbrite options B (connect) and C (move to Traversence), from the Amendment.

### Our team (admin)

- **Admin → Let's Explore → Live events is called built in 0134 [both].**
  - P1 cites 0134 Context and §9. P2 cites §9.
  - It is backed by `LiveEvents::list` (held events first, P1) and `setStatus` (:256–271).
  - Its route is not in `pages.md`. It is presumably under `/admin/router.php`, but this is unconfirmed [both].
- **System Configuration → Content templates → Live events holds the crawler's settings [single-pass: P1].**
  - Sources: 0134 §9 and `template` :27–34.
  - Staff can preview and try a template there (:75, :78).
  - P2 places preview and try elsewhere in the code. See (b).

---

## (b) How it works underneath

### The crawler's Live events (`LiveEvents.php`) [both]

**Storage**
- The tables are `live_events` and `live_event_places`. `ready()` checks for both (:19–24).
- There are four statuses (:17): `live` (Showing), `held` (Waiting for a person), `expired` (Past) and `removed` (Taken down).

**Template**
- The Live events template is read at :27–34.
- P2 adds that `ContentTemplates::get('live_events')` is layered over the code's defaults [single-pass: P2].

**Expiry (:37–41). Passes differ on the wording:**
- P1: an event expires once its end date, or its start date if there is no end, is before today.
- P2: events are marked past "the day after they end".
- These may describe the same rule. P1 also says `tick` runs expiry first [single-pass: P1].

**Schedule (`tick`, :44–72)**
- Runs only when the template's status is `active` (:49) and `AiClient::allow(…,'live_events')` passes (:51).
- Covers guide tiers 4 and 5 by default (:53).
- Refreshes each place every 7 days by default, through the `Clocks` W10 cutoff (:55).
- Handles at most 5 places per run (:58).
- Retries a failed place after one day (:65–69).
- P1 adds that places must have a live guide and are taken oldest check first [single-pass: P1].

**Finding events (`compose`, :81–134)**
- **Sources.**
  - Official sources first: the place's site, otherwise its towns' sites (`sites`, :180–187).
  - Up to 4 pages through `Dossier::eventPages` (:93–94).
  - P1 adds that the web is searched only for what official sites don't list (:92–94, :105) [single-pass: P1].
- **Venue list.** Our own venues are taken from the guide's do, eat, stay and shop picks (:190–195).
- **Window.** The horizon is 120 days by default (:91).
- **Prompt rules. The content agrees, but the passes cite different lines:**
  - Rules: no guessed dates, cite the announcing page, and list sacred ceremonies only if publicly announced, with no directions.
  - P1 cites :101–105.
  - P2 cites :101, :102 and :103, and adds that the ceremony must be announced by "the nation or parish". See (e).
- **The call.**
  - The prompt is capped at 50,000 characters (:108).
  - The model gets a strict JSON schema. P1 cites :118–120; P2 cites :118–119 and names `AiClient::claudeAsk`.
  - Cost is recorded by `AiClient::record`. P1 cites :121; P2 cites :120–121.
- **Preview and try modes. Passes differ on the lines:**
  - P1 cites :75 and :78.
  - P2 cites `preview` spending nothing at :111–117 and `try` not saving at :128.

**Checks (`check`, :141–163)**
- An event is dropped if it has no name or date, is already past, falls outside the window, or lacks an http(s) source (P2 :152).
- **Venue match.** The venue is matched by exact name to an unsuppressed entity inside the place, with `e.name = ?` at :155.
  - P1 cites :153–156. P2 cites :154–156.
- **Protected names.** `Landmarks::isProtected` sets the status to `held`.
  - P1 cites :157–162. P2 cites :159–162.

**Saving (`keep`, :166–177)**
- The identity is a hash of place, title and start date (:168).
  - P1 says the title is lowercased. P2 says the hash is md5.
- An event that was taken down stays down (:170).
- A held event stays held when it is found again (:174).

**Admin actions (:256–263)** [single-pass: P2]
- `setStatus` allows only `live` and `removed`.
- It records who removed the event and clears the guide cache.

**Styling.** `html()` uses hard-coded hex colours. See the brand gap in (e).

### Groups [single-pass: P1]

- `community/index.php` calls `/user/api/community.php` (index.php:98).
- `group.php` uses:
  - `tv_explore_hero` (:69);
  - `tv_engage_bar` with kind `group` (:83);
  - `tv_count_item('group:'…)` (:67).

### Designed data model [both]

- One `events` store, plus `event_dates`, `event_sources`, `event_rsvps`, `event_tickets` and `event_ticket_codes`.
- A migration moves `live_events` into `events`.
- The merge is deferred to Q1 by the 2026-11-27 note [both]. P2 adds that Live events stay separate through Jan 1.

### Tickets (ADRs only) [both]

- A ticket is a "Class or event" catalog item, which becomes "Event tickets" (0141 §5). It is sold through the order system (0098), per 0134 §6a.
  - P1 adds the 2026-11-03 date, `Products::BUSINESS_KINDS` and 0116 [single-pass: P1].
- **Payment [single-pass: P1]:**
  - QuickBooks (0095) for Traversence's own events;
  - manual payment (0118, 0119) for businesses.
- Card checkout through a business's own gateway waits for the gateway step [both]. P1 calls it "0135's gateway step"; P2 calls it the "Market gateway step".
- The pilot's checkout is `MarketCheckout::writeOrder`, which is not shown [single-pass: P2].

### Email [both]

- Ticket and event-change mail goes through SES on the per-person account (0137 §8).
- SES is in mock mode until AWS production access is granted (0137 Step 5). P1 gives the reason; P2 says `SesMail` is built in mock mode.
- Ticket mail does not count against a business's allowance [single-pass: P2].

---

## (c) Decisions

### 0134, Events: one tool for the whole site [both]

| Section | What it rules |
|---|---|
| §1 | One store, with crawler events as one source. P2: the host's words win over the crawler's [single-pass: P2]. |
| §2 | Who may host. Verification is required. P2: a nation's ceremonies appear only if the nation posts them [single-pass: P2]. |
| §3 | No outside ticket links and no pay-at-the-door notes. P2: the crawler's source page is a citation, not a ticket link. |
| §4 | Visibility: public, members-only or invited. |
| §5 | Where events show. Never ordered by payment (0007). P2: ordered by date and distance. |
| §6 | RSVP. Who's going is visible to the host only by default. |
| §6a | Tickets on the existing order system. |
| §6b | Ticket Sales module. Monthly limits: Free 50, Basic 150, Core 250, Strategic and up unlimited. 0% fees. Members and groups get RSVP only (P2). |
| §7 | Checks before an event shows. |
| §8 | Past and cancelled events. |
| §9 | Three homes for events. P1 adds that Social → Events is removed [single-pass: P1]. P2 adds that the admin sees system events only [single-pass: P2]. |
| Amendment | Eventbrite options B and C. |
| 2026-11-27 note | The Hometown theater pilot. Participants get exposure only; only the host sells tickets. |

### Other decisions

- **0133, superseded by 0164 [both].** The Live events template, the crawler rules and the rule that takedowns stay down.
  - **Passes differ on cadence:**
    - P1 says events are "kept monthly by the date check".
    - P2 says 0133's event clocks are "every month" (Phase 6), while the code runs weekly (:55) under 0164 W10. P2 treats this as a tension; P1 does not.
- **0164 [both].** Amends 0134 through W10 and R16. Not attached.
  - P1 adds that 0164 rules where the two disagree [single-pass: P1].
- **0136 [both].** Events are a group's "front door". Tickets are shared like items. The one exception to "no outside selling" is Eventbrite option B.
  - Passes differ on section: P1 splits this across §5 and "Selling in groups"; P2 puts it all under §5. P2 adds the Buy now button.
- **0137 [both].**
  - §6: event-change notices and the ticket-limit notice. P1 says each is sent once; P2 puts the limit notice at 80%.
  - §7: no cap on events posted, while tickets stay capped. P2 adds that this replaces 0134 §7's per-day cap [single-pass: P2].
  - §8: SES, as described in (b).
  - §3: the ground rules [single-pass: P2].
- **0180 §4.1 [both].** The hard money line: no agent makes a real charge or refund without a person.
  - P1 adds that this is high-risk territory 1 [single-pass: P1].
  - P2 adds 0178's "with Jason's OK" for ticket refunds [single-pass: P2].
- **Cited but not attached [single-pass: P1 list]:** 0007, 0098, 0116, 0118, 0119, 0121, 0095, 0135, 0141 §5, 0066, 0058 §20–21, 0174, 0172 §3.3, 0110, 0181. P2 also cites 0055, 0058, 0066 and 0178.

---

## (d) Live vs coming

| Item | Status | Evidence |
|---|---|---|
| Crawler Live events: find, check, hold, expire, take down, show | Built in code. Whether it runs in production depends on the template being `active` and the AI allowance [both]. | P1: LiveEvents.php in full, :49, :51. P2: :44-72, :236-253. |
| `/market/ticket.php`, `item`, `cart`, `order`; `/user/orders.php` | Live; code not shown [both] | `pages.md` §1 |
| `/community/` and `group.php` | Live, with no events section [both] | group.php:84–89 |
| Admin → Live events | Built per the ADR; route not in `pages.md` [both] | 0134 Context/§9 |
| `/event/` host pages, free events, link to ticket item | Planned for about Dec 10–12; build status not shown [both] | 0134