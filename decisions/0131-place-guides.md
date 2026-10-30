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
- **Roads:** Arizona's 511, two sources. The **WZDx work zone feed** (`az511.com/api/wzdx`, the national Work Zone
  Data Exchange format, v4: `core_details`, `vehicle_impact`, cross streets or mileposts, dates, reduced speed,
  workers present, restrictions) is public and needs no key: road work, lane closures and detours, now or starting
  within three days. The **developer API** (`AZ511_API_KEY` in .env, a free key) adds its Events (incidents, closures)
  and Alerts (statewide notices, shown when they name the guide's roads); a keyed road-work event a work zone already
  shows is left out. Both are filtered to the roads the guide names and those within reach of the place, and kept 10
  minutes. The API's cameras, weather stations, message boards and rest areas are for later. Other states' 511
  services and WZDx feeds follow as their regions open.

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

**Addendum (2026-10-30):** a listing marked closed (`decisions/0072`) is never a pick, pinned or not, and a guide that
names one shows it as **Closed** in the editor, which keeps the guide from going live until the name is taken out or
the listing reopened. `/api/guide-live.php` builds the weather, roads and town strip each on its own (a failure in one
is logged with where, and never empties the others), and only an answer that has weather is cached.

**Addendum (2026-10-30, Jason):** Visiting now reads Essentials, Things to do, then At a glance and the rest; on a
wide screen the places one tier down (towns, neighborhoods) run in a third column beside the guide. The weather card
opens a compact forecast on the page (24 hours as temperature, rain or wind, seven days; folded on load); the weather
service is a link inside it, never the first tap. Weather is asked for in one parallel batch, kept 40 minutes and
renewed ahead of visitors by the `guide_live_tick` job (every 15 minutes, with the AZ 511 feeds); when it's out of
date or the service is down, the last good forecast (up to 12 hours) shows at once. A community of one town and no
neighborhoods (Farmington) opens that town's guide, unless one has been written for the community itself.

**Addendum (2026-10-30, Jason): Essentials by who needs them.** Essentials lists the nearest places for what someone
might need, three each, nearest first (`Guides::ESSENTIALS`): for everyone, emergency & urgent care (the two nearest
ERs or hospitals and a walk-in clinic within 15 miles), pharmacies, police/fire/ambulance, fuel & charging, groceries,
banks & ATMs, visitor info & libraries; Visiting adds car trouble, getting around, pet emergencies, laundry/mail; Live
here adds town hall & government, utilities, schools & childcare, doctors/dentists/counseling, home repairs and
community help. A listing that keeps its address private (`ListingSafety::isConfidential`) is never one of them. At a
glance and Landmarks & heritage join the places one tier down in the third column.

**Addendum (2026-10-30, Jason): the weather right now.** `Weather::conditions` reads the stored forecast (no wait) and
says what it means today: indoor places lead in Things to do and Shopping when there's a severe alert, thunder, rain
now or likely in the next hours (50%+), 95°+, 20° or colder without snow, or wind of 30 mph+; the outdoors leads on a
fair day (50–88°, sun or clear, under 30% rain); snow without a warning is left to the season. A **Right now** line
at the top of Things to do says why (amber; red with an alert). The weather placeholder is drawn at once (stored
weather, else a shimmer) and the live call fills it in.

**Addendum (2026-10-30, Jason): Claude drafts, a person confirms.** "I want the LLM and site to do the work; I will
review and confirm." Each guide's editor has **Write a draft with Claude**, and the guide job drafts one guide a run
on its own (asked-for ones first, then the busiest city and community guides nobody has written; one-town communities
are their town's guide). Claude writes only the empty sections, never one a person wrote, from what we have (the place,
its listings by section, landmarks, outdoor places, towns) and what it confirms on the web, naming only real places in
bold; no invented phone numbers, hours or prices; tribal nations by their own names, sacred sites never. The names are
matched as on any save. The draft stays a draft: the Guides list shows **Claude's draft to review** first, and the
editor shows what Claude says to check and its sources. A person publishes. Cost goes through the AI monthly cap.
Migration `2027-01-14_guide_drafts.sql`. A draft is a guide, not a
directory: Claude first learns what the place is known for and names only places worth the time, never filler from our
lists. Elevation comes from the weather service's forecast for the place. **Rewrite Claude's sections** redoes only the
sections still exactly as Claude wrote them; anything a person changed stays. Claude's drafts are written in the
Traversence voice (`brand.md` §1: atmospheric, intellectual, authoritative, enduring, deeply grounded) as a chapter of
the Human Journey read through the Living Geography: it researches the place's history first (the nations whose land it
is, Spanish and Mexican-era settlers, pioneers, ranching and railroad families, the old routes its roads follow), gives
it depth with names and dates, and respects rural life as a way of living, never framing a place by what it lacks. The
editor says whether Claude searched the web for a draft and, if the search was refused, why.
