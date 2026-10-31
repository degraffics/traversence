# ADR 0131: Place Guides on the Six-Tier Funnel

> **Crawler rules: superseded by `decisions/0133` (2026-10-30), the crawler's one rule book.** This record stays as history; where it and 0133 disagree, 0133 rules.

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

**Addendum (2026-10-30, Jason): from the ground, history, visitors' intent; AI spending under control.** A draft is
written from, in this order: boots on the ground (the guide's **From the ground** notes, which only our team sees, and
the journeys and experiences published about the place, a member's only with AI & Learning consent), history (our
story of the place and Claude's research), and visitors' intent (what people search for there, from search demand
counts, never who). **System Configuration → AI use** sets each AI feature (guide drafts asked for, guide drafts on
their own, names in guides, names in journeys and stories, Find it): on or off, model, runs a day, its own monthly
budget and web searches a run, with what it has used, all under the overall `AI_MONTHLY_CAP_USD`
(`AiClient::FEATURES`, `allow()`, table `ai_features`, migration `2027-01-15_ai_use_and_field_notes.sql`).

**Addendum (2026-10-30, Jason): the crawler gathers, Claude writes, the guide publishes itself.** Two things cut the
cost: a **dossier** and a **crawler-first Find it**. The dossier (`app/lib/Dossier.php`, kept 90 days) holds the place's
Wikipedia article (its lead and its history, geography and culture sections, read for facts, never copied), its
Wikidata facts (founded, elevation, population, official website), a few pages of that official website, and the
landmarks and outdoor places we hold nearby with their summaries. A community's dossier is its two busiest towns'.
Claude writes from the dossier first and searches the web only for what it lacks. Default searches a run drop to 3
for drafts asked for and 2 for drafts on their own. Find it (`GuideLinks::crawlFind`) first asks OpenStreetMap near
the place (that it exists, its map link and its website as a pointer only, under ODbL), then reads that website as the
crawler reads any business's (name, address, phone, hours). It asks Claude only when the crawler finds nothing. Then
the guide **goes live by itself** (`Guides::autoPublish`, `decisions/0005` amendment 2026-10-30). It waits only for a
name that may mark a protected site or a closed business, after Claude has been asked once to write it without that
name. A name we don't have yet no longer holds a guide back: it stays plain text until Find it links it, and this
applies to publishing by hand too. A guide our team takes down (**Take it down**) stays down until someone publishes
it. A live written guide carries "Compiled by Traversence from public sources and local knowledge · updated {date}"
and **Suggest a change**. The Guides list shows **Live (compiled by Claude)** and **Claude's draft, waiting**. The switch
is System Configuration → AI use → **Guides publish themselves** (on). There's no migration: the state is kept in
`draft_info` (`auto_published`, `kept`, `dossier`).

**Addendum (2026-10-30, Jason): dossiers kept, confidence scores, finds approved by rule.** The dossier is the place's
own data, not a cache: table `place_dossiers` (migration `2027-01-16_place_dossiers.sql`) keeps its text, sources and
Wikidata facts (founded, elevation, population, official website), refreshed every six months, and a refresh that
can't reach Wikipedia keeps what's held. Towns and neighborhoods show Founded and Population under At a glance, and a
missing elevation comes from it. **A guide's confidence score** (`Guides::score`, 0-100, worked out each time) adds
Claude's own confidence (up to 35), the dossier (20), notes from the ground (10), its sources (up to 10) and how many
of its names link (up to 25), less 4 for each thing to check (up to 16) and 10 for a refused web search. A guide
publishes itself only at or over **Guides publish themselves → Lowest score** (70). Below that it waits as a draft and
publishes on a later guide job once Find it has linked enough of its names. The editor shows the score and what made
it, and the Guides list has a Score column. **Approval by rule** (Jason: "the standing rule is we approve, but not
review it unless required"): each find gets a score. The crawler gives 40 for the map, 20 for reading its own website,
15 for an address, 15 for a phone and 10 for its name on that site. Claude gives 50 or 70 by its confidence, plus 5 each
for a website, an address, a phone and two sources. At or over **Finds become listings by themselves → Lowest score**
(80), a business, museum or organization with a phone and an address or map point is added as a listing from its
suggestion (`AddBusiness::fromSuggestion`), and the name links. One we already list under a name like it (same phone
or website) is linked instead. If its phone or website is another listing's under another name (a county fairground on
the courthouse's line), a person looks. Anything else waits in Review, as before. Guides Claude wrote before the dossier and the score are written again from them by the guide job, one a run (the
oldest first, under *Guide drafts on their own*), Claude's own sections only. A guide that went live by itself stays
live only while its score holds: if a rewrite brings it under the lowest score, it goes back to a draft. A guide a
person published stays live.

**Addendum (2026-10-30, Jason): nothing about our sources reaches visitors.** St. Johns's draft told visitors the
city's website was suspended: Wikidata still gave the old sjaz.us, the crawler read its "Account Suspended" page as the
official site, and Claude passed it on (the city's site is stjohnsaz.gov). "If we post crap like this we will be fit to
be called out for it." Three guards: (1) **dead websites are caught** (`Dossier::dead`: suspended, parked, for sale, a
host's default page, next to nothing) and never read; the official site is the first that's alive and names the place,
from Wikidata (preferred rank first, never deprecated), our own listing of the city or town, then the usual government
addresses (`{town}{state}.gov`, `cityof{town}.org`…); dossiers built before this are built again. (2) Claude is told the
guide speaks about the place, never about our research: no dossier, lists, sources, Wikipedia, Wikidata, websites being
down, or what it couldn't confirm; those go in the notes for our team. (3) **Every draft is scrubbed** before it's saved
(`Guides::scrub`): a sentence about our research or a broken site, or one naming what may be a sacred, burial or
protected site (`Landmarks::SENSITIVE`, decisions/0066), is taken out and listed for our team as "Taken out of the
guide". Guides written before this are written again by the guide job (`Guides::DRAFT_V`).

**Addendum (2026-10-30, Jason): if a source publishes it, we can too.** A guide may name a place whose name sounds
protected (rock art, ruins, a cemetery: `Landmarks::SENSITIVE`) when a public source already publishes it: the Lyman Lake
Rock Art Site is run and promoted by Arizona State Parks. Our crawler checks it: the sentence stays only if every name in
it is found in the dossier or in the pages Claude cites (`Guides::publishedIn`, `Dossier::pageText`); otherwise it's
taken out as before. Names kept this way are recorded with the guide (`draft_info.published`), so the guide names them as
plain text and they don't hold it back. The landmark's own page stays held until a person approves it (`decisions/0066`).

**Addendum (2026-10-30, Jason): rich with history, culture and the experience of being there.** "I expected something rich
with history and culture as well as clear real life immersion aspects that make wanting to come to St. Johns and
experience it first hand." The first compiled St. Johns guide was thin: no research (the dossier was a short article and
a bill-pay home page), nine sections in one 8,000-token answer, and instructions that rewarded caution over substance.
Now: (1) **a research brief** per place (`Dossier::research`, feature *Research briefs*, kept a year in `place_dossiers`,
migration `2027-01-17_dossier_research.sql`): Claude researches the place in depth on the web once (its peoples and
founding, families and turning points, faith, food, crafts, the year's festivals, the land, what being there is like)
and every later guide draws on it; (2) **two writing passes** with room each (the story: at a glance, landmarks and
heritage, events, itineraries; then the practical sections), 16,000 tokens each; (3) **an immersive brief**: make a
reader want to come and help them do it well, history running through every section, concrete over general (the bells
across Commercial Street on Sunday, not "a historic church"); and each section's purpose rewritten the same way. The
score credits the brief (Claude 30, dossier 15, brief 10, ground 10, sources 10, names 25). Guides written before are
written again (`DRAFT_V` 3).

