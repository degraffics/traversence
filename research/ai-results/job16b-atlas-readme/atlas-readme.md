# Traversence platform atlas: README

*`research/platform-atlas/README.md`. **Draft for human review.***

**Sources.** This README draws only on the eighteen atlas entries in `research/platform-atlas/` and on `charter.md`. Each claim cites the atlas file it comes from. Where the atlas could not see something, this README says **not shown**.

**Limits.**
- Most atlas entries say their own sources were cut off at about 40 KB.
- Several entries are themselves cut off partway through their last section: admin-control-board, communications, community-connect, engage, events-tickets, get-local, identity-trust-nations, knowledge-engine-crawler, lets-explore, maps, marketplace-shop-checkout, media, members, plans-pricing-money, search-situations, storefronts-domains-trvgo and trips-send-to-maps.
- So "not shown" here often means "not visible to the atlas". It does not mean "absent from the platform".
- Neither `architecture.md` nor `commercial.md` was attached. The charter points to both for track detail and pricing.

---

## 1. The whole platform on two pages

### Who it is for

The charter names residents, travelers, local businesses and communities (charter.md, Mission). The atlas works with five audiences:
- residents (Get Local);
- travelers (Let's Explore);
- signed-in members;
- businesses, meaning a listing's owner and team;
- staff (every atlas entry, section (a)).

Some areas have no traveler-only or resident-only surface:
- Communications treats both as either a guest or a member (communications.md).
- Money flows are not split by track (plans-pricing-money.md).
- Trips has no resident-specific surface (trips-send-to-maps.md).

### What exists, by area

"Live" means the route is marked Live in `site-audit/pages.md`, as reported by the atlas entry.

| Area (atlas file) | What exists | Main audience | Live | Coming / not built |
|---|---|---|---|---|
| **Get Local** (get-local.md) | `/directory/`, List \| Map. Cards with Call, Directions and Website. "Open · Closes 5 PM" from parsed hours; unreadable hours show as "unknown", never "open". A "None here" box. Pin-fix suggestions. | Resident | `/directory/`, `/listing/view.php`, `/listing/location.php`, `/claim.php` | Search internals and the directory page code are **not shown** |
| **Let's Explore** (lets-explore.md) | `/discovery/`, place guides by tier, landmark and recreation pages, member and rule-built journeys, how-to and resource guides | Traveler | `/discovery/`. `/hub/` is "Live, placeholder". `/hub/build.php` and `/journeys/suggested.php` are "Live once uploaded". | Clean hub addresses, `/guide/{slug}` articles, `#tag` pages, Events under Things to do, the business opt-out switch for suggested journeys |
| **Search & situations** (search-situations.md) | A situation reader ("stuck on the highway") routes needs to a track. A 5W index. "Did you mean" is offered, never applied. Learns from counts, never from who searched. | All | `/api/search.php` and the admin search tools | Market search returns nothing. Events search waits until events exist. Public topic pages. |
| **Maps** (maps.md) | One shared map. Census geocoding. Labelled approximate pins. Owner and visitor pin fixes. The trip route map. | All | Map tools on `/directory/` and `/discovery/` | A tile-provider agreement is needed before heavy traffic |
| **Trips / Send to Maps** (trips-send-to-maps.md) | Add to a trip, a trip page of up to 14 days, a share link, Personal Guides, and Send to Maps in legs | Traveler, member | Releases 1–4 | Release 5, routes drawn on our own site: "building is held" |
| **Community / Connect** (community-connect.md) | Groups ordered by place (Here, Nearby…). Joining means consenting to the group's charter. Chapters, invites, one group per business. | Member, business | `/community/`, `/community/group.php` | `/api/group-act.php` is "Coming (release comms-all)" |
| **Engage** (engage.md) | Views, likes, comments in place of star reviews, Share, QR. GO Codes at $4.95 each. Dynamic codes. | All | `/api/engage.php`, `/qr/*` | Maker notices for comments on guides and journeys, Add to a trip from the Engage bar, `#` tag pages |
| **Communications** (communications.md) | One dispatcher (`Notify`). Pulse as the single inbox. Six levels. Quiet hours. Push. Email on two engines. | Member, business, staff | Dispatcher and `/email/stop.php` | Many endpoints are "Coming (release comms-all)". SES runs in mock mode until AWS production access. No text channel. |
| **Members** (members.md) | Dashboard, Pulse, Address Book, saved searches, Moments, account close and restore, orders, QR, AI Assist (paid plan) | Member | `/user/*` per pages.md | Several pages it calls are **not shown** |
| **Identity, trust, nations** (identity-trust-nations.md) | 18+ accounts, social sign-in, passkeys, consent records, the Verified mark, claim tiers, protected nation land | All | Register, login, two-step, claims, the admin nations and landmarks pages | Phone verification is "coming" |
| **Media** (media.md) | Gallery, albums, guest drop-in uploads, hero media, stock photos (always labelled), a video worker | Member, business | `/gallery/*`, `/api/hero-media.php` | `/user/post.php`, the Videos tab and `/shop/` are built but awaiting release. Order file checks. |
| **Events & tickets** (events-tickets.md) | Crawler-found Live events on guides and venues. Ticket pages on the Marketplace. | Resident, traveler | `LiveEvents` code. `/market/ticket.php`. | `/event/` and `/events/` are not in pages.md. Going/Interested, .ics files, group events and event notices are all not built. |
| **Listings, claims, portal** (listings-claims-business-portal.md) | A confidence ring with "How we know". Claim by email code (Managed) or text code (✓ Verified). Business portal with quotes, invoices, codes and walled data. | Business | `/claim.php`, `/listing/businessportal.php`, `/business/` | `/listing/confirm.php` and `/listing/requests.php` |
| **Knowledge engine / crawler** (knowledge-engine-crawler.md) | Off-site worker with plans, a cost ledger, a pause switch, a source register and identity checks. Never overwrites locked fields. | Staff, feeds all | Admin crawler pages | The portal's "Check these" is decided but not built |
| **Marketplace & checkout** (marketplace-shop-checkout.md) | Member posts and business items. Publish gates and rulebook. Each business's own Stripe or Square at a 0% cut. One Traversence cart. | All | `/market/*` (`/market/` is "Live, placeholder"), `/qr/cart.php` | `/shop/` is Built. Several routes are missing from pages.md. |
| **Plans, pricing, money** (plans-pricing-money.md) | Personal: Free, $4.95 and $9.95 a month. Listing plans: Free, $29.95, $79.95, $229.95 and $399.95. Wallet, an add-only ledger, refunds that wait for Jason's OK. | Member, business | `/plans.php`, `/user/orders.php` | `/order.php` is not in pages.md. Catalog caps are not in `Billing::PLANS`. |
| **Storefronts, domains, trvgo.us** (storefronts-domains-trvgo.md) | A storefront at `trvgo.us/<shop>` (Core and up). Bring-your-own or bought domains. | Business, visitor | Code shown | Status conflict: the route is not in pages.md, and the marketplace entry calls storefronts "Not built" (marketplace-shop-checkout.md) |
| **Admin control board** (admin-control-board.md) | Separate staff sign-in, 11 workspaces, an add-only action log, a daily consistency check | Staff | `/admin/*` | Access checks are not uniform across the code shown. `/api/health.php` is not in pages.md. |

### The shape in one paragraph

The **discovery core** is live and substantial:
- directory, guides, search, maps, trips, listings, claims and the crawler that feeds them;
- each built around counts-not-identity and no-pay-for-rank rules (search-situations.md; engage.md; events-tickets.md).

The **connection layer** is the least finished:
- communications endpoints, events, group gatherings, live email sending and text messaging are all coming or in mock mode (communications.md; events-tickets.md).

The **money layer** is built but sits behind switches and sandboxes:
- business payments use each business's own gateway with no custody (plans-pricing-money.md);
- Traversence's own sales go through QuickBooks (marketplace-shop-checkout.md).

---

## 2. The Tri-Track as people live it

The charter defines the three tracks (charter.md, "The Tri-Track Architecture"):
- **Resident ("Get Local")**: bottom-up, everyday utility.
- **Traveler ("Let's Explore")**: top-down, regional narrative.
- **Community ("Connect")**: "the living, streaming layer binding dialogue and gatherings to place".

The charter defers detail to `architecture.md` and `brand.md`. `architecture.md` is **not shown**. The atlas has no separate "member track" or "business track". Members and businesses live mostly in Connect, and they reach the other two tracks as well. The three accounts below follow that structure.

### Get Local: a resident's errand

**Finding a place.** A resident opens the Get Local door on the home page and lands on `/directory/` (get-local.md).
- "Open now" and "Near me" live in the search, not on the page.
- Each card says "Open · Closes 5 PM". Hours the parser can't read show as unknown rather than open (get-local.md).
- If nothing matches, the "None here" box offers Recommend one or Add a listing, plus nearby areas (get-local.md).

**Typing a situation.** Typing "bit by something" gives situation answers:
- danger situations lead with "If anyone is in danger, call 911";
- a listing missing the phone or hours a need requires isn't offered for that need (search-situations.md).

**Fixing and following.** A resident who spots a wrong pin can drag it and send it for review without signing in (maps.md). Following a place brings a notice when a local group starts there (community-connect.md).

**Where it thins out.**
- The Get Local search, directory and suggestion handler code is **not shown** (get-local.md).
- Resource lines don't yet pull in lines from the places above the resident's own (lets-explore.md).

### Let's Explore: a traveler's trip

**Planning.** A traveler opens `/discovery/` with an "Explore: \<place\>" hero (lets-explore.md).
- Place guides run from region down to neighborhood. `/hub/` is still "Live, placeholder" (lets-explore.md).
- For an uncovered town, `/hub/build.php` builds a first-look guide in about 2 minutes (lets-explore.md).
- Landmarks on a nation's land say "On \<nation\> land" and show only after an Editor approves them (identity-trust-nations.md).
- Sacred or protected landmarks are story-only, with no pin (maps.md).

**Building the trip.** Rule-built journeys are suggested (lets-explore.md). The traveler adds stops with Add to a trip. Anything that can't be added is greyed with the reason, never hidden (trips-send-to-maps.md).

**On the road.** The traveler plans days, shares a view-only link, and uses Send to Maps (trips-send-to-maps.md):
- Google or Apple Maps;
- all stops sent in order, in legs of a start, 8 stops and an end;
- nothing cut silently.

**Where it thins out.**
- Legs between stops are still straight-line estimates, because release 5 is held (trips-send-to-maps.md).
- Suggested journeys are timed for a Saturday only (lets-explore.md).
- Outside AZ, NM, UT, CO and NV the OpenStreetMap layer is empty, so places to eat may not appear (lets-explore.md; maps.md).
- A crawler event card's only action sends the traveler off the site (events-tickets.md).

### Connect: a member's community life

**Groups.** A member browses groups by place: Here, Nearby, then Anywhere (community-connect.md).
- "Join group" opens the group's charter, so joining is consent to it.
- Starting a group needs Verification (email confirmed plus 18+), and one person can run up to 5 groups (community-connect.md; identity-trust-nations.md).

**Pulse.** Everything arrives in Pulse, the single inbox (communications.md):
- group posts, mentions and replies to comments;
- quiet hours apply;
- Critical messages always reach email and push.

**Posting.** Moments last 3 or 7 days unless kept (members.md). Comments are 18+ and limited to 30 an hour (engage.md).

**Where it thins out.**
- Groups cannot yet host events or show "Upcoming events" (events-tickets.md).
- The group action endpoint and several Pulse endpoints are still "Coming (release comms-all)" (community-connect.md; communications.md).
- Text messaging has no channel (communications.