# ADR 0131: Place Guides on the Six-Tier Funnel

**Status:** Accepted (2026-10-30) by Jason. Builds on `decisions/0043` (a guide at every level), `decisions/0056` §8
(the six-tier discovery funnel), `decisions/0003` (the Chameleon Filter), `decisions/0061` (resource guides),
`decisions/0005` (a person approves what the system writes) and `decisions/0007` (money never buys placement).

## Context

The hub pages (`/hub/`) already carry most of a guide (a story, the Census profile, support resources, outdoors,
community groups, a Local count), but in the order the data arrived, with a placeholder box and sample tiles on the live
site, a one-pill way down the funnel, and the first three names of each support group alphabetically. The resource
guides ("who to call") were a separate tool keyed to "everywhere" or one town area. Jason (2026-10-30): "There should be
a regional guide, city guide. One anchor location and everything associated to that location… mirror the structure we
have for the top down discovery funnel. Each step getting its own guide." And: "the discovery kind of already does this,
but I feel it is disorganized and clunky."

## Decision

### 1. One guide per place, one per funnel tier

| Tier | Place | Guide | Address |
|---|---|---|---|
| 1 Continent | North America | (the Discovery dashboard) | `/discovery/` |
| 2 Region | a hub (Ancient America) | **Regional guide** | `/hub/?hub=slug` |
| 3 Geographic hub | a geo-hub (Ancient Borderlands) | **Area guide** | `/hub/?geo=slug` |
| 4 Cluster | a town cluster (Round Valley) | **Community guide** | `/hub/?cluster=id` |
| 5 Anchor | a town in a cluster (Greer, St. Johns) | **City guide** | `/hub/?town=slug` |
| 6 Sub-group | an accepted local name (Pinetop Lakeside) | **Neighborhood guide** | `/hub/?sub=id` |

Towns are the `anchors` table, kept in step with each cluster's ZIP towns. Each place has its guide from the start
(nothing to "create"); what our team writes fills it in.

### 2. One template, the same order at every tier

1. **Header:** the name, the kind of guide, the place above it, Link, Share; the **current weather** card.
2. **At a glance:** the story (ours, reviewed), elevation, best time to visit.
3. **Essentials:** **weather alerts** first (National Weather Service), **road conditions** (AZ 511) for the roads
   the guide names and those near it, **Who to call** (the resource guide: this place's lines first, then those of
   every place above it), nearest care.
4. **Getting there & around.**
5. **Next stops:** the places one tier down, as cards.
6. **Landmarks & heritage**, 7. **Eat & drink**, 8. **Things to do** (outdoors, family, shopping, seasons).
9. **Events & seasons** (hidden until there are events). 10. **Itineraries & insider tips.**
11. **Living here** (the Census profile, folded). 12. **Local** (browse businesses; Support & wellness is one of its
    groups). 13. Comments.

A section with nothing in it is hidden, never a placeholder. Higher tiers carry less detail (a region: story, next
stops, highlights, one long itinerary; counts, not lists).

### 3. The Chameleon Filter in a guide

- **The dial: Visiting / Live here.** The same guide, two orders and two accents: Visiting leads with At a glance,
  Getting there, Eat & drink, Things to do; Live here leads with Essentials, Local and Support & wellness. The choice
  is remembered; arriving from Get Local starts on Live here.
- **Picks:** each listing section asks the filter for a few listings by relevance (the section's categories), proximity
  (a range per tier: a region's whole zone, a community's 20 minutes, a city's own town) and kind of experience, with the
  season (and the weather) leading. Businesses the written guide names are pinned first; the editor can pin, reorder or
  hide. Paid tiers never rank higher (`decisions/0007`); only public listings appear.

### 4. Anything a guide names links to something we have

Every name in a guide (business, town, neighborhood, landmark, lake, trail, road, museum, event, organization) links to
a listing, place, landmark, outdoor place, resource line or event. On save, the names are picked out and matched (name,
other names, distance from the guide's anchor):

- **matched:** linked, and pinned as that section's pick;
- **matched but thin** (no hours, a raw name): the record is queued for the crawler to fill in;
- **not found:** a **Find it** job: the system searches official sources (the business's own site, OpenStreetMap,
  agency and state pages, Claude with web search), confirms it is real, open and in the area, and puts what it found in
  Review as a suggestion. A person approves before anything new is shown (`decisions/0005`).

A guide publishes when every name is linked, or removed. A sacred or protected name is never created this way
(`decisions/0066`). The monthly re-check covers links: a closed or hidden listing flags its guides for an edit.

### 5. Weather and roads

- **Weather:** the National Weather Service (free, official): the latest observation, today and tonight, three days,
  and active alerts for the place's point. Fetched by our server for the place (never the visitor's location), kept
  30 minutes. A region shows a strip of towns side by side. Climate averages (NOAA normals) feed "best time to visit".
- **Roads:** Arizona's 511 (`AZ511_API_KEY` in .env, a free developer key): closures, incidents and restrictions on
  the roads the guide names and within reach of the place, and winter road conditions; kept 10 minutes; hidden without
  a key. Other states' 511 services follow as their regions open.

### 6. Resource guides fold in

The resource guides tool keeps its entries and rules (`decisions/0061`: a number is shown only once found on the
provider's own page), but each guide is now a place in the funnel, and a place's Essentials shows its own lines and its
ancestors'.

## Consequences

The hub page becomes the guide page for tiers 2–6; the admin gets one Guides tool (the funnel tree, each guide's
sections and the state of its names). Find it uses the AI monthly cap (`decisions/0108`). The Greer and St. Johns
drafts Jason supplied are the first two city guides, fact-checked and linked before they go live.

## Built (2026-10-30)

`app/lib/Guides.php` (places, tiers, sections, picks), `app/lib/GuideLinks.php` (names, matching, Find it),
`app/lib/Weather.php`, `app/lib/Roads511.php`, `/hub/` (the guide page), `/api/guide-live.php` (weather, alerts,
roads, loaded after the page), Let's Explore → **Guides** (the list) and its editor (`/admin/guide-edit.php`), the
`guide_finds_tick` scheduled job, migration `2027-01-11_place_guides.sql`. A name matches one we have when the words
left over are generic ("Molly Butler Lodge" is "Molly Butler Lodge & Rstrnt"; "Mount Baldy Wilderness" isn't the peak;
"Apache County" isn't "Apache County Road Yards"). Towns are kept from each cluster's ZIP towns. The Greer and St. Johns
drafts are Jason's text, corrected where our data disagreed (elevations), pasted in as drafts.
