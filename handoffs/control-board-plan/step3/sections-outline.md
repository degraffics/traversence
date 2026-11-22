# The four build gauges: where each section stands (session manager's outline, 2026-11-22)

Jason (2026-11-22): "make 4 gauges as the top row: Discovery, Directory, Community, Marketplace. These gauges will
measure the development completion state… Have the session manage pull together to outline for where each section
stands and what completion is. From there we will review and outline the what is missing."

This is the cross-session view: what's live by release, built but held, decided but not built, and which session owns
each piece. Operations is building the item-by-item inventory from `site-audit/pages.md`, `prd.md`, `phases.md` and
the decisions. The two are merged before Jason sees them.

Sources:
- `site-audit/pages.md`;
- the release records (`deliveries/releases/`, #1–#247);
- the hand-offs: `design.md`, `design-and-trips-2026-11-20.md`, `order-processing.md`, `control-board-*`;
- `community-scope.md`, `market360-build-briefs.md`;
- decisions 0013, 0060, 0101, 0134–0139, 0141–0142, 0146–0147, 0154, 0159, 0169, 0174.

Where something couldn't be checked from the repo, it says "to confirm". Nothing here is guessed.

## What "complete" means (proposal; Jason decides)

- **Scope first:** each section's scope is a list of items that Jason signs off: pages, features and the workflows
  that feed them. The gauge's denominator is that list, so it can't drift. A new idea is added to the list, by his
  OK, before it counts.
- **An item counts as complete only when it's live and passes its check:** its page answers, its paths and rendered
  checks pass at 1280 and 390, and its data is flowing. "Built" isn't enough. Something built but held for a key,
  for setup or for approval counts as **not yet complete**, and is shown separately.
- **The gauge's needle** is the share of signed-off items complete, weighted where Jason wants (a page 1, a core
  feature 3).
- **The drill-down** goes by status, each with its source and owner session:
  - Live and passing;
  - Live with issues;
  - Built, held;
  - Decided, not built;
  - Proposed.
- **Bands:** under 50% red, 50–80% amber, 80% and up sage, all proposed until Jason sets them.
- **Kept apart from the runtime gauges:** these measure building, not how the live site is performing. A section can
  be 90% built while its runtime snapshot is red (e.g. Directory while W1 is paused).

## Ownership: where shared pieces sit (proposal; Jason decides)

Following brand.md §3b's four constructs:
- **Discovery** is "Let's Explore". **Directory** is "Get Local". **Community** is "Connect". **Marketplace** is
  "Market".
- **Journeys and Trips:** Discovery.
- **Guides** (how-to, resources, built on demand): Discovery.
- **Events (0134):** decided as a site-wide tool. Proposal: counted in **Community** (people host them), and shown in
  Discovery's "Things to do".
- **Listings, Claim, Verify and the business's listing tools:** Directory.
- **Market 360°, the Shop, Plans, GO Codes, Domains and Storefronts:** Marketplace.
- **The platform underneath:** sign-in, accounts, Pulse and messages, the Gallery, the Vault, the admin, staging and
  releases, the Knowledge Engine (W0–W14) and the control board. These aren't in any of the four. Proposal: a **fifth
  "Platform" gauge** later, or count each piece under the section it serves. The crawler's workflows feed Discovery
  (W3–W14) and Directory (W1, W2).

---

## 1. Discovery ("Let's Explore")

**Owner sessions:** Design and trips (pages, trips, the place page); Operations (the Knowledge Engine that fills it).

**Live:**
- `/discovery/`: overview, map, regions, outdoors, things to do, journeys, the Trip Planner (0053, 0055, 0056, 0058,
  0059). On the brand tokens since 3b-1 (#158–#159); the map goes back to the list (#202).
- Place pages:
  - landmark and recreation pages (0058, 0048);
  - the place page's viewer and toolkit (#211);
  - card photos (#205, #213, #217, #218);
  - hero tidy (#230).
- Experiences and journeys. Suggested journeys from guides (0146; "live once uploaded": to confirm).
- Guides: how-to and resource guides (0058, 0061, 0063). A guide built while you wait for a searched place (0147;
  "live once uploaded": to confirm).
- Trips (0174): R1 Send to Maps (#219), R2 trips and Add to a trip (#220), R3 the trip page (#221), R4 sharing and
  Personal Guides (#227).
- Hubs: one landing page per hub, geo-hub or cluster (`/hub/`), **live as a placeholder**, with the weather strip
  (#171).

**Built, held or needing setup:**
- Park Service photos are built but skipped until `NPS_API_KEY` is set (0143).
- Gathering that feeds Discovery is partly stopped:
  - W3's two switches are left off by an old trip;
  - Common Crawl's index trip.

  See the runtime board.

**Decided, not built:**
- 0174 R5, routes on our own site (openrouteservice; its terms to check first).
- The map release (trails and routes in their own colour, brand.md §4 note).
- Clean hub addresses, `/{hub}/{geohub}/{cluster}` (0013).
- Editorial guides at `/guide/{slug}` with the directory feed.
- The "Nationwide Narrative" layer (the Human Journey and the Authority Engine, brand.md §2): what's built of it is to
  confirm with Operations' inventory.

**Open items:**
- The area data (micro-clusters named "Albuquerque", "Abilene" under Ancient Borderlands; Phoenix under Ancient
  America; the Code Talkers memorial filed as a business): sent to the session manager to plan; not started.
- Journeys' Link button has no case in `Links::resolve` (design.md §4).

## 2. Directory ("Get Local")

**Owner sessions:** Design and trips (Get Local's page); Business listings (claim, the owner's tools); Operations (W1
and W2, which find and keep the listings).

**Live:**
- `/directory/`:
  - on the tokens (3c, #164);
  - the finder bar (#178);
  - **Get Local R2** (#208): Filters, Arrange, Sort, Table, the list beside the map, the comments box, the card tools.

  The classic directory is archived (#165).
- Listings:
  - `/listing/view.php` (hero, engage bar, comments, Link);
  - `/listing/location.php` (move the pin);
  - an owner opening their own hidden listing (case #22).
- Claim: by email or text code, "who might claim" by kind, and the form that fits them (#215).
- Verify a listing.
- Search: "near me" and the place found (0060 Phases A and B).

**Built, held or needing setup:**
- **Listings aren't flowing right now.** W1 is paused (IR-0001's two bulk-load trips), and the snapshot showed "0
  records in" for 24 hours. The runtime board has the detail.
- Data cleanup (0163 §8, stage 1b) and the duplicates fix (0164 §3.1–3.2): status by release is to confirm with
  Operations' inventory.

**Decided, not built:**
- Search Phase C (0060).
- Clean listing addresses, `/listing/{slug}`.
- The Single Home Rule queue (charter; aos-gap-review §8).
- The admin's pending-listing review page is covered today by crawler review and listing intake (pages.md §8).

**Open items:**
- The "3 within 5 miles" coverage promise (0060) isn't measured as defined yet. The runtime board lists it as not
  checked.

## 3. Community ("Connect")

**Owner sessions:** the Community build (finished, recorded in `community-scope.md`); Design (3d, its look);
Communications (0172 and 0176, the dispatcher).

**Live:**
- Groups: find, join and start (up to 5 per person); interest and local groups; owner, moderator and member roles;
  reports and standing (0047, 0102, 0109).
- Charters, business-owned groups, the group page as a content page, moderation run by the group, notices sent once
  (0137 steps 1–5).
- Tags on groups, places a group is about, chapters, search by topic and place, growing a group (welcome, join
  questions, invitations, codes, join counts), and Admin → Social → Groups (0136).
- Private circles, groups in Universal Search, the Community feed. Live events from the crawler in guides and on venue
  listings.
- Messages and notices through the one dispatcher (0172, notify-1, #130), the member's Pulse, and the Gallery.

**Built, held or needing setup:**
- Release 3d (`/community/` built to Jason's mockup, adapted; `handoffs/r3d.md`): built on 2026-11-20. **Whether it's
  live is to confirm.**
- Email through Amazon SES: the sending side ran in mock mode until its domains verified (community-scope's build
  status). Live mode is to confirm.

**Decided, not built:**
- **Events (0134)**, the site-wide tool.
- Inactivity and the lifecycle job (C4).
- A member's private home place (C3).
- Flyers and badges (0137 step 7).
- A business's bulk invitations (C1).
- Steward member reports and level-2 escalation.
- Admin → Social → Business groups.
- Creation gated by trust score.
- Community threads into the AI corpus (abstraction only).

## 4. Marketplace ("Market")

**Owner sessions:** Order processing (held, waiting on Jason's review of the order map); the Market 360° and
Storefronts builds (finished, recorded in 0141, 0142, 0139); Design (3e, its look).

**Live:**
- Marketplace pages: `/market/` (live, **a placeholder**), the cart, item, order, ticket, download, rules and post
  pages (0135; P6, 2026-11-13).
- Checkout: Stripe embedded, with the businesses' own Stripe or Square (0135 step 4a).
- Market 360° in the business portal:
  - zip 1, Phase A (modules and the Trust score);
  - zips 2–3, Phase B (the acceptance log, store policies, the seller agreement, rulebook, chargeback packet);
  - the nine item kinds, Phase C in one build;
  - the acting-as bar.

  Catalog photos, publishing and deleting are built too (0141, 0142).
- Storefronts on `trvgo.us/<shop>` (0139 step 5a) and Connect your domain (5b), through the Fly.io edge.
- The Shop (0154): plans and packs, GO Codes, one cart, the free week of Pro, a month of Pro, Keep in 4K.
- Domains in the Shop (0169 §1a, #71–#86), **with sales switched off until Domain sales is set**.
- Our own orders and work orders: the vendor page and work orders (0101, 0150), QuickBooks payments (0095).

**Built, held or needing setup:**
- **The order system's improvements:** held until Jason reviews the order-system map
  (`research/order-system-map-2026-11-19.md`), with his rulings (`research/order-rulings-2026-11-20.md`). The first
  release carries the chargeFor fix (Jason: "Fix in first release").
- Waiting on setup (market360-build-briefs):
  - the owner ID check (Stripe Identity);
  - the state-record checks (AZCC is blocked pending our records request; TPT and NM SoS);
  - texts and push consent;
  - **the legal texts** (an attorney's pass, and the legal entity's name).

  `/terms.php` and `/privacy.php` are placeholders "to replace before launch".
- Market 360° zips 5–7 (tickets, digital, perishables; rentals, bookings, subscriptions; Phases D and E): whether the
  Phase C build covered them is to confirm with Operations' inventory.

**Decided, not built:**
- **The App store and the app lifecycle (0169 §1–§6)**, first in the queue.
- Storefront Studio and the builder platform (0159): status by step is to confirm.
- Square's catalog sync (0135, 4b).
- The order system's rewrite, once the map is confirmed (`order-processing.md`).
- 3e: the Marketplace, checkout and storefronts on the tokens.

---

## Waiting on Jason, for this outline

1. Sign off each section's scope list (from the merged inventory), so the gauge has a fixed denominator.
2. The completion rule: live and passing counts, built-but-held doesn't. Weights, if any.
3. Ownership: Events counted in Community; a fifth Platform gauge, or the platform pieces spread across the four.
4. Bands (red under 50%, amber to 80%, sage above), proposed.
