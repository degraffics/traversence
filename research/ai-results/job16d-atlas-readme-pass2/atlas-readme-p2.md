# Traversence platform atlas: README

*Draft for human review. Built only from the 18 atlas entries in `research/ai-results/job16c-atlas-pass2/` and `charter.md`. Each claim cites its atlas file, shortened to its area name (for example `get-local` means `atlas-get-local-p2.md`).*

**Read this first.**
- **The atlas entries are incomplete.** Every entry is a pass-2 draft over a partial, capped slice of the code. Most are cut off partway, often inside section (e), "Charter promises." The gap list in Part 3 ranks only what is visible.
- **The "five promises" are the atlas entries' own framing.** The charter has one mission line ("an immersion and connection engine") and four guarantees under "Why This Matters." The atlases add the mission line to the four guarantees to make five:
  1. Immersion and connection
  2. True locality
  3. Discovery that can't be bought
  4. Frictionless agency
  5. Data dignity

  This README uses the same five.

---

## 1. The whole platform on two pages

**What it is for.** The charter describes an "immersion and connection engine" for residents and travelers (charter, Mission). It is built on natural geographic clusters rather than categories, and on one permanent home per business, the Single Home Rule (charter, Two Principles).

**The front doors.**
- **Home.** The home page has four doors under the hero search, and Get Local is one of them (`get-local`, citing 0138 §3).
- **Get Local** is `/directory/`. It offers List | Map, filters inside search, and cards with Call, Directions, Website and Link (`get-local`).
- **Let's Explore** is `/discovery/`. It offers place guides for each tier of the funnel, suggested journeys, landmarks, how-to and resource guides, and a Trip Planner (`lets-explore`). Place guides at `/hub/` are still "Live, placeholder" (`lets-explore`).
- **Community ("Connect")** is `/community/`. On mobile it is reached through the menu label "Social." It holds groups with charters, chapters and business-owned groups (`community-connect`).

**Shared spine used by every track.**
- **Search.** Search opens in place on every page and reads each query as Why, What, Where, When and Who (the "5W" reading). It matches situations ("power went out"), and a danger situation puts a 911 line on top (`search-situations`).
  - Businesses lead on Get Local, and the place's own content leads on Let's Explore (`search-situations`).
  - The meaning layer is rule-based, not AI (`search-situations`).
- **Maps.** There is one shared map. Approximate pins are labelled honestly, visitors suggest pin fixes, and confidential places such as shelters are never pinned (`maps`).
- **Listings and claims.**
  - The crawler creates public listings marked "unclaimed" (`knowledge-engine-crawler`).
  - A business claims a listing with an email code, which shows "Managed by owner," then a text code, which shows "✓ Verified" (`listings-claims-business-portal`).
  - A confidence ring shows "How we know." A closure signal never closes a listing on its own (`listings-claims-business-portal`).
- **Engage bar** (`engage`):
  - views are counted, never who viewed;
  - comments serve as reviews, and there are no stars;
  - listings have no Like.
- **Pulse (the inbox).** Every message passes through one dispatcher, `Notify`, with six levels (`communications`).
  - The channels are Pulse, email and push. There is no text channel (`communications`).
  - Members set per-kind channel choices and quiet hours (`members`).
- **Identity** (`identity-trust-nations`):
  - accounts are for people 18 and over, and email must be confirmed;
  - sign-in options are Google, Apple, Microsoft, Facebook and passkeys;
  - "Verified" means a confirmed email plus 18+ confirmation.
- **Media.** One uploader, a Gallery with album modes, Moments, and a video Channel (`media`).
- **Trips.** Members add stops from many pages, arrange them by day against opening hours, Send to Maps, share a link, or publish a Personal Guide (`trips-send-to-maps`).

**Money.**
- **Personal plans:** Free, Plus at $4.95 and Pro at $9.95 (`plans-pricing-money`).
- **Business plans,** per listing per month: Free, Basic at $29.95, Core at $79.95, Strategic at $229.95 and Cornerstone at $399.95. Tools open by plan tier (`plans-pricing-money`).
- **Marketplace.** No payment passes through Traversence. Businesses use their own Stripe or Square at 0%, or manual payment (`marketplace-shop-checkout`; `plans-pricing-money`).
- **Traversence's own sales** go through one cart and a QuickBooks ledger (`plans-pricing-money`).
- **Storefronts** run at `trvgo.us/<shop>` from the Core plan up. Domains are sold through Name.com. The storefront's custom-domain connection is off by default (`storefronts-domains-trvgo`).
- **GO Codes** are QR codes and short links on trvgo.us (`engage`).
- **Events.** Crawler-found "Live events" appear on guides and listings. Ticketing is a pilot riding on the Marketplace (`events-tickets`).

**Underneath.**
- **The crawler** has two layers. A Railway worker fetches pages. The site stages the results, applies a register of rules for each source, and decides what is published (`knowledge-engine-crawler`).
- **The admin side** (`admin-control-board`):
  - staff use separate logins;
  - staff cannot browse member accounts, must give a reason to open one, and every opening is logged;
  - a Control Board logs alarms;
  - data is split into three realms across four databases.

**Patterns that recur across the entries.**
- **Counts, never who:** `engage`, `search-situations`, `get-local`, `trips-send-to-maps`.
- **A person decides on sensitive things,** such as sacred sites and nation rules: `admin-control-board`, `knowledge-engine-crawler`, `maps`.
- **Claimed fields are never overwritten** by the crawler: `knowledge-engine-crawler`, `listings-claims-business-portal`.

---

## 2. The Tri-Track as people live it

**The lived Tri-Track is not defined in these files.** The charter gives only one line per track and points to `architecture.md` and `brand.md` for detail (charter, Tri-Track). Neither file is attached; the atlas entries cite `brand.md` only at second hand. So this section follows the charter's three-track structure and fills each track with what the atlas entries show.

**Resident: "Get Local," bottom-up everyday utility**
- **Where it lives:** `/directory/`. Its filters are Near me, Open now, Has a phone and Has a website. Signed-in members also get Favorites (`get-local`).
- **Opening hours.** Hours that can't be read never count as "open" (`get-local`).
- **Local words.** Search spelling draws first on a dictionary of local words (`get-local`; `search-situations`).
- **Situation search** serves urgent local needs and leads with Call, open now and distance (`search-situations`).

**Traveler: "Let's Explore," top-down regional narrative**
- **Where it lives:** `/discovery/`, with place guides for each funnel tier and suggested journeys timed for the next Saturday (`lets-explore`).
- **Landmarks.** Each landmark comes with its story. Protected or sacred places are held back or told without being placed on the map (`lets-explore`; `maps`).
- **Trips and Send to Maps** finish the journey (`trips-send-to-maps`).

**Community: "Connect," the living layer binding dialogue and gatherings to place**
- **Where it lives:** `/community/`. Groups are bound to places. Groups show on place pages under "Talked about by," and on listings under "Featured in." Chapters localise topic groups (`community-connect`).
- **Group posts** arrive in Pulse as messages (`community-connect`).

**How the tracks cross in practice**
- **Guides.** A Visiting / Live here dial on the guides switches between the traveler and resident views. Arriving from Get Local starts on "Live here" (`lets-explore`).
- **Search** sends each need to its track (`search-situations`).
- **Get Local cards** offer "Add to a trip" (`trips-send-to-maps`). `brand.md` §5 is quoted as saying Get Local serves "residents and explorers alike" (`get-local`).
- **Some areas don't separate the tracks at all.** Several entries found no difference between resident and traveler in the code: `marketplace-shop-checkout`, `communications`, `trips-send-to-maps`.

**Two things to check**
- The home page has four doors (`get-local`), but the charter has three tracks. The other three doors are not named in what is visible.
- The Connect track's "gatherings" layer is the weakest part as built (see gap 1).

---

## 3. Ten biggest gaps between the five promises and what's delivered

| # | Promise | Gap | Atlas file | Fix direction |
|---|---|---|---|---|
| 1 | Immersion & connection | **The Connect track has no gatherings.** Group events are unbuilt. `group.php` has no events block. `brand.md`'s "Gatherings (real-time chat streams)" appears in no code shown. | `community-connect`; `events-tickets` | Ship the group events block ("next event" in at-a-glance) before any chat work. |
| 2 | Immersion & connection | **Events are not yet "one tool."** The `events` store and the `/event/` and `/events/` pages are not built and are missing from `pages.md`. The merge with Live events is deferred to Q1. Search has no events or "upcoming" yet. | `events-tickets`; `search-situations` | Register the event routes in `pages.md` now and fix a date for the Q1 merge. |
| 3 | Immersion & connection | **Email doesn't actually reach people.** SES is in mock mode, so ticket, event-change and group mail is not sent. | `events-tickets`; `community-connect` | Finish AWS production access and test SES end to end on staging. |
| 4 | True locality | **The Single Home Rule isn't enforced for new finds.** Finds outside built areas are filed under the pilot zone wherever they really are. Decision 0170, which fixes this, is "not built." Unmatched ZIPs seed "(auto-seeded, needs review)" clusters. | `get-local`; `knowledge-engine-crawler` | Build 0170's placement step and clear the auto-seeded clusters waiting for review. |
| 5 | True locality | **Coverage and precision are thin.** Place scope parses only hub, geo-hub and cluster, not `?town=` or `?sub=`. OpenStreetMap data covers only 5 states since Overpass was retired. Milepost lookup is unbuilt. Event venues match by exact name only. | `lets-explore`; `maps`; `events-tickets` | Add town and sub levels to `PlaceScope`, and plan extract coverage beyond the 5 states. |
| 6 | Discovery that can't be bought | **"Can't be bought" isn't proven.** The ranking code (`search_listings.php`, `UniversalSearch`) and decision 0007 are not shown. The index adds +10 for verified and +5 for claimed listings. "About" add-ons grow with `plan_tier`. | `get-local`; `search-situations` | Attach the ranking code and add a test proving that plan tier never changes result order. |
| 7 | Frictionless agency | **The page register disagrees with the code.** Live vs Coming conflicts include `comms.php`, `comment-act.php`, `group-act.php`, `adminportal.php` and `admin/login.php`. Media and domain routes are missing from `pages.md`. | `admin-control-board`; `communications`; `engage`; `community-connect`; `media` | Do one reconciliation pass of `pages.md` against the 0172 status and the sync mirror. |
| 8 | Frictionless agency | **"Who is in charge" is tested many different ways.** There are three different tests of "owner/managed" (0172 gap 5 is only partly closed). Admin access is checked at least six different ways across files. | `listings-claims-business-portal`; `admin-control-board` | Use one shared `managed()` test and one admin access gate, and point every caller at them. |
| 9 | Data dignity | **Identity safeguards can fail open.** `AgeGate` treats everyone as confirmed if its table is missing. Email-confirmation tokens are stored in plain text, while other tokens are hashed. | `identity-trust-nations` | Make `AgeGate` fail closed, and hash confirmation tokens the way `StepUpAuth` already does. |
| 10 | Data dignity | **The business data wall has a known hole.** The scoped-tenant exception for `MarketCheckout::writeOrder` is "not met yet" in live code, and the tenant switch is still an open Founder question. | `admin-control-board`; `events-tickets` | Resolve the tenant switch before ticket checkout goes live. |

**Next in line for review**
- **GO Codes vs decision 0094.** 0094 says codes are free and "nothing typed is kept," but the code charges for codes and stores what each one holds (`engage`).
- **Trips are not reliably deleted.** The 30-day purge runs only when the owner reopens their list. Account deletion removing trips is not shown (`trips-send-to-maps`). DSAR export is still Coming (`members`).
- **Nations wording.** The crawler prompt accepts ceremonies announced by a "nation or parish," while 0134 says only the nation itself may post them. `NATION_TOOLS` is set to false (`events-tickets`; `maps`).
- **Market is invisible to search.** The `market` section matches nothing in search, even though `/market/` is live (`search-situations`).
- **An alarm may be silently dropped.** `sitehealth.finding` appears to be filed in the wrong table and may be dropped (`communications`).