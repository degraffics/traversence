# Traversence platform atlas: README (merged)

*Draft for human review. Merged from two independent drafts:*
- ***Draft A:** `job16b-atlas-readme/atlas-readme.md`. Built from the 18 entries in `research/platform-atlas/` plus `charter.md`. It is cut off mid-sentence in Part 2 and has no gap list.*
- ***Draft B:** `job16d-atlas-readme-pass2/atlas-readme-p2.md`. Built from the 18 pass-2 entries in `research/ai-results/job16c-atlas-pass2/` plus `charter.md`. It is complete.*

**How to read the markers.**
- Unmarked claims are supported by both drafts.
- **single-pass (A)** or **single-pass (B)** means only one draft makes the claim.
- **drafts differ:** marks a real disagreement or a different framing.
- Citations use atlas area names. For example, `get-local` means `get-local.md` in A and `atlas-get-local-p2.md` in B.

**Read this first.**
- **The drafts worked from different atlas sets.** A used `research/platform-atlas/`. B used the pass-2 entries. Where they disagree, the cause may be the atlas version rather than either draft.
- **The atlas is incomplete.** Both drafts say the entries are partial.
  - Most entries say their sources were capped at about 40 KB (A).
  - Many entries are cut off partway through, often inside section (e), "Charter promises" (B). A lists 17 of the 18 as truncated.
  - So "not shown" means "not visible to the atlas," not "absent from the platform."
- **Some charter references are missing.**
  - `architecture.md` is not attached (both drafts).
  - `brand.md` is not attached; the atlas cites it only second-hand (single-pass (B)).
  - `commercial.md` is not attached (single-pass (A)).
- **The "five promises" framing comes from B.** The charter has one mission line ("an immersion and connection engine") and four guarantees under "Why This Matters." The atlas entries combine them into five promises:
  1. Immersion & connection
  2. True locality
  3. Discovery that can't be bought
  4. Frictionless agency
  5. Data dignity

  Part 3 uses these five. Draft A never reached its gap section.

---

## 1. The whole platform on two pages

### Who it is for and how it is organised

**Audiences.**
- The charter names residents and travelers. Draft A also lists local businesses and communities (charter, Mission).
- The atlas works with five audiences: residents, travelers, signed-in members, businesses (a listing's owner and team) and staff. Single-pass (A).

**Organising principles.**
- Places are grouped by natural geographic clusters, not by categories.
- Each business has one permanent home: the Single Home Rule (charter, Two Principles).
- Single-pass (B).

**Front doors.**
- **Home page.** It has four doors under the hero search, and Get Local is one of them (`get-local`). Single-pass (B). Draft A mentions only "the Get Local door."
- **Get Local** is `/directory/`, with List | Map.
  - Cards carry Call, Directions and Website. Draft B adds a "Link" action.
  - Hours the parser can't read show as unknown, never as "open."
- **Let's Explore** is `/discovery/`. It holds place guides by tier, landmarks, suggested journeys, and how-to and resource guides.
  - `/hub/` is still "Live, placeholder."
- **Connect** is `/community/`, with groups, charters, chapters and business groups.
  - On mobile the menu label is "Social." Single-pass (B).

### What exists, by area

"Live" means the route is marked Live in `site-audit/pages.md`, as reported by the atlas.

| Area | What exists | Live | Coming / not built / conflicts |
|---|---|---|---|
| **Get Local** | `/directory/`, List \| Map, parsed hours ("Open · Closes 5 PM"). Filters sit inside search: Near me and Open now in both drafts; Has a phone, Has a website and Favorites single-pass (B). "None here" box and pin-fix suggestions single-pass (A). | `/directory/`, `/listing/view.php`, `/listing/location.php`, `/claim.php` (A) | Search internals and the directory code are not shown |
| **Let's Explore** | Place guides by tier, landmarks, member and rule-built journeys, guides. The `/hub/build.php` first-look guide takes about 2 minutes, single-pass (A). | `/discovery/`. `/hub/` is a placeholder. | Clean hub addresses, `/guide/{slug}`, `#tag` pages, business opt-out for suggested journeys: single-pass (A) |
| **Search & situations** | Situation reader with a 911 line first for danger. 5W index. Counts, never who searched. "Did you mean" is offered, never applied: single-pass (A). Meaning layer is rule-based, not AI: single-pass (B). | `/api/search.php` | Market search returns nothing. Events search waits for events. |
| **Maps** | One shared map. Approximate pins labelled. Owner and visitor pin fixes. Sacred places are story-only. Shelters never pinned: single-pass (B). Census geocoding: single-pass (A). | On `/directory/` and `/discovery/` | Tile-provider agreement needed before heavy traffic: single-pass (A) |
| **Trips / Send to Maps** | Add to a trip, day planning, share link, Personal Guides, Send to Maps. Up to 14 days, and legs of start + 8 stops + end: single-pass (A). | Releases 1–4 (A) | Release 5 (own-site routes) is held |
| **Community / Connect** | Groups ordered by place. Joining means consenting to the charter. Chapters. One group per business. Places show "Talked about by" and listings show "Featured in": single-pass (B). | `/community/`, `/community/group.php` | `/api/group-act.php` is "Coming (release comms-all)" |
| **Engage** | Views counted, never who viewed. Comments in place of star reviews. Share, QR, GO Codes. Price is $4.95 per code: single-pass (A). Listings have no Like: single-pass (B). | `/api/engage.php`, `/qr/*` | Maker notices and `#` tag pages: single-pass (A) |
| **Communications** | One `Notify` dispatcher, Pulse inbox, six levels, quiet hours, push. Email on two engines: single-pass (A). | Dispatcher, `/email/stop.php` | SES in mock mode. No text channel. Many endpoints "Coming." |
| **Members** | Dashboard, Pulse, Moments, orders, account close and restore, AI Assist on paid plans (A). Per-kind channel choices: single-pass (B). | `/user/*` | DSAR export is "Coming": single-pass (B) |
| **Identity, trust, nations** | 18+ accounts. Social sign-in (Google, Apple, Microsoft, Facebook) and passkeys. Verified = confirmed email + 18+. Protected nation land. | Register, login, two-step, claims | Phone verification "coming": single-pass (A) |
| **Media** | One uploader, Gallery and albums, Moments, video. Guest drop-ins and labelled stock photos: single-pass (A). | `/gallery/*`, `/api/hero-media.php` | `/user/post.php`, Videos tab and `/shop/` built but awaiting release (A) |
| **Events & tickets** | Crawler-found Live events. Ticket pilot on the Marketplace. | `LiveEvents`, `/market/ticket.php` | `/event/` and `/events/` not built and not in `pages.md`. No group events. |
| **Listings, claims, portal** | Crawler creates "unclaimed" listings. Email code gives "Managed by owner"; text code gives "✓ Verified." Confidence ring with "How we know." A closure signal never closes a listing on its own: single-pass (B). | `/claim.php`, business portal | `/listing/confirm.php` and `/listing/requests.php`: single-pass (A) |
| **Knowledge engine / crawler** | Off-site worker (Railway, per B). Site stages results under a per-source rule register. Never overwrites claimed or locked fields. Cost ledger and pause switch: single-pass (A). | Admin crawler pages | Portal "Check these" decided but not built: single-pass (A) |
| **Marketplace & checkout** | Businesses use their own Stripe or Square at 0%, or manual payment. No payment passes through Traversence. One Traversence cart. | `/market/*` (placeholder) | Several routes missing from `pages.md` |
| **Plans, pricing, money** | Personal: Free, Plus $4.95, Pro $9.95. Listing plans: Free, $29.95, $79.95, $229.95, $399.95 (names Basic, Core, Strategic and Cornerstone from B). Add-only ledger, and refunds wait for Jason's OK: single-pass (A). | `/plans.php`, `/user/orders.php` | `/order.php` not in `pages.md`. Catalog caps not in `Billing::PLANS`: single-pass (A). |
| **Storefronts & domains** | `trvgo.us/<shop>` from Core up. Bought (Name.com) or bring-your-own domains. | **drafts differ** (see below) | **drafts differ** (see below) |
| **Admin control board** | Separate staff logins. Add-only action log and daily consistency check, 11 workspaces: single-pass (A). Reason required to open a member account, three realms across four databases: single-pass (B). | `/admin/*` | Access checks not uniform. `/api/health.php` not in `pages.md`: single-pass (A). |

**drafts differ on storefronts:**
- Draft A reports a status conflict. The route is not in `pages.md`, and `marketplace-shop-checkout` calls storefronts "Not built."
- Draft B describes storefronts as running, with custom-domain connection off by default.

**drafts differ on citations:**
- QuickBooks for Traversence's own sales is cited to `marketplace-shop-checkout` in A and to `plans-pricing-money` in B.

### The shape

**Discovery core.** Directory, guides, search, maps, trips, listings, claims and the crawler are live and substantial. They are built around the rules "counts, never who" and "no pay-for-rank." Draft B adds a caveat: no pay-for-rank is not yet proven (gap 6).

**Connection layer.** This is the least finished part. Events, group gatherings, live email, text messaging and many communications endpoints are coming or in mock mode.

**Money layer.** It is built but sits behind switches and sandboxes:
- business payments use each business's own gateway, so Traversence holds no money;
- Traversence's own sales go through one cart and QuickBooks.

**Recurring patterns** (from Draft B):
- counts, never who;
- a person decides on sensitive matters such as sacred sites and nation rules;
- the crawler never overwrites claimed fields.

---

## 3. The Tri-Track as people live it

**The charter's definition.** The charter defines the three tracks in one line each:
- **Resident ("Get Local")**: bottom-up, everyday utility.
- **Traveler ("Let's Explore")**: top-down, regional narrative.
- **Community ("Connect")**: "the living, streaming layer binding dialogue and gatherings to place".

It defers detail to `architecture.md` and `brand.md`, and neither file is attached. Both drafts therefore fill the tracks from what the atlas shows.

**Members and businesses.** There is no separate member or business track. Both live mainly in Connect and reach the other two tracks. Single-pass (A).

**Areas that don't separate residents from travelers:**
- communications;
- trips;
- money flows: single-pass (A);
- the marketplace: single-pass (B).

### Get Local: a resident's errand

**Finding a place.**
- The resident lands on `/directory/`. The filters live in search.
- Cards show live hours. Unreadable hours never show as open.
- If nothing matches, the "None here" box offers Recommend one, Add a listing, or nearby areas. Single-pass (A).

**Typing a situation.** Typing "bit by something" or "power went out" gives situation answers.
- Danger situations lead with "If anyone is in danger, call 911."
- Results lead with Call, open now and distance: single-pass (B).
- Listings missing the phone or hours a need requires are not offered for that need: single-pass (A).

**Local words.** Search spelling draws first on a dictionary of local words. Single-pass (B).

**Fixing and following.**
- A resident can drag a wrong pin and send it for review without signing in.
- Following a place brings a notice when a local group starts there.
- Single-pass (A).

**Where it thins out.**
- The Get Local search and directory code is not shown.
- Resource lines don't pull in lines from the places above the resident's own. Single-pass (A).

### Let's Explore: a traveler's trip

**Planning.**
- The traveler starts at `/discovery/`, where place guides run from region down to neighborhood.
- For an uncovered town, `/hub/build.php` builds a first-look guide. Single-pass (A).
- Nation-land landmarks say "On \<nation\> land" and need an Editor's approval: single-pass (A).
- Sacred or protected places are story-only, with no pin.

**Building the trip.**
- Suggested journeys are timed for the next Saturday only.
- Items that can't be added are greyed with the reason, never hidden: single-pass (A).

**On the road.**
- The traveler shares a view-only link and uses Send to Maps (Google or Apple), with all stops sent in order.
- Nothing is cut silently: single-pass (A).

**Where it thins out.**
- Legs are still straight-line estimates because release 5 is held.
- OpenStreetMap data is empty outside AZ, NM, UT, CO and NV, so places to eat may not appear.
- A crawler event card's only action sends the traveler off-site. Single-pass (A).

### Connect: a member's community life

**Groups.**
- Groups are browsed by place: Here, Nearby, Anywhere.
- "Join group" opens the charter, so joining is consent to it.
- Starting a group needs Verification. One person may run up to 5 groups. Single-pass (A).
- Chapters localise topic groups: single-pass (B).

**Pulse.**
- Group posts, mentions and replies arrive in Pulse, and quiet hours apply.
- Critical messages always reach email and push: single-pass (A).

**Posting.**
- Moments last 3 or 7 days unless kept.
- Comments are 18+, with a limit of 30 an hour.
- Single-pass (A).

**Where it thins out.**
- Groups can't host events.
- The group action endpoint and several Pulse endpoints are "Coming."
- There is no text channel.

### Where the tracks cross

These points are single-pass (B):
- A Visiting / Live here dial on guides switches between the traveler and resident views. Arriving from Get Local starts on "Live here."
- Search routes each need to its track. Draft A agrees on this point.
- Get Local cards offer "Add to a trip." `brand.md` §5 is quoted, second-hand, as saying Get Local serves "residents and explorers alike."

### To check

- **Home doors vs tracks.** There are four home doors but three tracks. The other three doors are not named. Single-pass (B).
- **Text codes vs no text channel.** Draft A reports claims verified by text code. It also reports "no text channel" and "phone verification coming." The claim path may use a separate sender, but this should be confirmed.

---

## 4. Ten biggest gaps

**Source of the list.** The list, the ranking and the fix directions are **single-pass (B)**, because Draft A ends before its gap section. The "Draft A support" column shows where A's own body independently reports the same issue.

| # | Promise | Gap | Atlas file | Draft A support | Fix direction |
|---|---|---|---|---|---|
| 1 | Immersion & connection | **Connect has no gatherings.** Group events are unbuilt, and `group.php` has no events block. `brand.md`'s "Gatherings (real-time chat streams)" appears in no code shown. | `community-connect`, `events-tickets` | Yes: "groups cannot yet host events" | Ship the group events block before any chat work |
| 2 | Immersion & connection | **Events are not one tool.** The `events` store and `/event/` and `/events/` are not built and not in `pages.md`. The Live-events merge is deferred to Q1. Search has no events. | `events-tickets`, `search-situations` | Yes | Register the routes now and fix the Q1 date |
| 3 | Immersion & connection | **Email doesn't reach people.** SES is in mock mode, so ticket, event and group mail is not sent. | `events-tickets`, `community-connect` | Yes (communications) | Finish AWS production access and test end to end |
| 4 | True locality | **The Single Home Rule isn't enforced for new finds.** Out-of-area finds are filed under the pilot zone. Decision 0170 is "not built." Auto-seeded clusters await review. | `get-local`, `knowledge-engine-crawler` | No | Build 0170 placement and clear the review queue |
| 5 | True locality | **Coverage and precision are thin.** `PlaceScope` ignores `?town=` and `?sub=`. OpenStreetMap covers only 5 states. Milepost lookup is unbuilt. Venues match by exact name only. | `lets-explore`, `maps`, `events-tickets` | Partly (5-state OpenStreetMap) | Add town and sub scope, and plan wider extracts |
| 6 | Discovery that can't be bought | **"Can't be bought" is unproven.** The ranking code and decision 0007 are not shown. The index adds +10 for verified and +5 for claimed listings. "About" add-ons grow with `plan_tier`. | `get-local`, `search-situations` | No. A asserts no pay-for-rank as a design rule but does not test it. | Attach the ranking code and add a test that plan tier never reorders results |
| 7 | Frictionless agency | **`pages.md` disagrees with the code.** Live vs Coming conflicts appear in `comms.php`, `comment-act.php`, `group-act.php`, `adminportal.php` and `admin/login.php`. Media and domain routes are missing. | `admin-control-board`, `communications`, `engage`, `community-connect`, `media` | Partly. A lists other omissions: `/order.php`, `/api/health.php`, storefront route. | Do one reconciliation pass |
| 8 | Frictionless agency | **"Who is in charge" is tested many ways.** There are three owner/managed tests, and admin access is checked at least six ways. | `listings-claims-business-portal`, `admin-control-board` | Partly ("access checks not uniform") | Use one `managed()` test and one admin gate |
| 9 | Data dignity | **Identity safeguards fail open.** `AgeGate` passes everyone if its table is missing. Email-confirmation tokens are stored in plain text. | `identity-trust-nations` | No | Make `AgeGate` fail closed and hash the tokens |
| 10 | Data dignity | **The business data wall has a hole.** The scoped-tenant exception for `MarketCheckout::writeOrder` is not met. The tenant switch is an open Founder question. | `admin-control-board`, `events-tickets` | No | Resolve before ticket checkout goes live |

**Next in line.** All of these are single-pass (B) unless noted.
- **GO Codes vs decision 0094.** 0094 says codes are free and "nothing typed is kept." The code charges for codes and stores their contents. Draft A independently reports the $4.95 price.
- **Trips are not reliably deleted.** The 30-day purge runs only when the owner reopens their list. Account deletion is not shown removing trips. DSAR export is still "Coming."
- **Nations wording.** The crawler accepts ceremonies announced by a "nation or parish," but 0134 says only the nation may post them. `NATION_TOOLS` is set to false.
- **Market is invisible to search** even though `/market/` is live. Draft A agrees.
- **A health alarm may be dropped.** `sitehealth.finding` may be filed in the wrong table.

---

## Agreements and differences

**The drafts agree on:**
- The incompleteness of the atlas, and that `architecture.md` is missing.
- The tracks as the charter defines them, and that much of the code does not separate residents from travelers.
- Core features:
  - Get Local filters live in search, and unreadable hours never show as "open."
  - Situation search with a 911 line first for danger.
  - One shared map with honest approximate pins and story-only sacred places.
  - Claims by email code (Managed) and text code (✓ Verified).
  - Comments instead of stars, and counts, never who.
  - The `Notify` dispatcher and Pulse, with six levels and quiet hours.
  - Identity: 18+ accounts, and Verified means confirmed email + 18+.
  - All plan prices.
  - The 0% own-gateway marketplace.
  - Suggested journeys timed for Saturday only.
- The weakest areas: events, group gatherings, SES mock mode, no text channel, held trip routing, 5-state OpenStreetMap coverage, market search empty, `pages.md` drift, and non-uniform admin access checks.

**The drafts differ on:**
- **Source set.** A used `research/platform-atlas/`; B used the pass-2 entries.
- **Storefront status.** A reports a conflict ("Not built" vs code shown). B treats storefronts as running.
- **Scope of the missing files.** A names `commercial.md`; B names `brand.md`.
- **Citations.** The QuickBooks claim is cited to different files.
- **Coverage.** A is richer on lived detail: hours box, pin fixes, legs, Moments, comment limits. B alone has the five-promise framing, the gap table and the security findings (gaps 4, 6, 9, 10).
- **Completeness.** A is truncated mid-sentence in Part 2. Nothing from A after that point could be merged.