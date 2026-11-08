# ADR 0146: Suggested Journeys from Guides

**Status:** Accepted (Jason, 2026-11-08). §6's questions are answered; §7 is what every journey needs and how we get it.

## Why

Jason: where we have a city guide like Show Low, Arizona, a journey would be likely places along the guide's outline,
using our APIs and the crawler to suggest a journey: a route and experiences. The same journeys become the site audit's
guide paths (decision 0145 §1), so what the system suggests is also what it tests.

## 1. What a suggested journey is

- **What it is:** a Traversence journey (decision 0056: our editorial voice, labelled as ours) that the system writes
  from a live guide.
- **What's in it:** a short route through the guide's own places, the things it already recommends:
  - what to see: landmarks, parks, heritage;
  - where to eat and stay;
  - what to do: experiences, outdoor places, events while they're on.
- **The route:**
  - the stops are ordered for travel (the nearest next, ending near where it began, or along the road for a drive);
  - each stop is timed against its open hours and the time of day, so breakfast comes in the morning and a museum
    falls inside its hours;
  - each stop has directions (a map link, decision 0058 §8) and its own page.
- **Shapes come from the guide** (Jason, 2026-11-08): the guide decides what kinds of journey it has, not a fixed list.
  - **Read the guide's themes:** its places and text are counted by theme (outdoors, shopping, food, heritage, arts,
    events, the drive itself). The themes that run most strongly through the guide become its journeys: an outdoors
    town gets outdoor journeys, a shopping town gets shopping ones, a road-trip town gets a drive.
  - **Only what's reachable:** a theme becomes a journey only when enough of its places are open, public and within
    reach of each other to make one (§6, question 2). A strong theme with too few usable places waits.
  - **Why follow it:** each journey opens with the reason to take it, drawn from the guide's own words about that theme
    (no AI), so it connects with why someone came.
  - **A day in [town]** mixes the guide's leading themes in their proportions, when there are places enough.
  - Built by rules: the theme counts, thresholds and order are fixed rules, re-run when the guide is renewed, so the
    journeys change when the guide does.

## 2. Where it comes from: data we already have

- **The places:** the guide's picks and mentions (decisions 0131, 0133), our listings, landmarks and outdoor places.
- **The facts:** open hours, phone, website and photos (the landmark photo finder, decision 0143 note).
- **Distances and travel times:** real road, walking and cycling times from openrouteservice (§7), straight-line only as
  the fallback.
- **The checks:** the crawler confirms each place is still open and still there before it's used, and again when the
  guide is renewed.
- **Writing:** the route and the stop order are built by rules, with no AI and no cost. A one-line introduction per
  stop can come from the guide's own text. The guide's AI writing budget is never used without its own allowance
  (decision 0133; no income, no expense).

## 3. The rules it follows

- **Decision 0133:** curators, not controllers. "Tell the story, never the way in": no protected or sensitive places
  (decision 0066, `Landmarks::visibleSql()`). The licence decides each fact's use.
- **Fairness:** a business is included because it fits the route, never because it pays. Paid placement, if ever
  offered, is labelled and kept apart.
- **Accuracy:** a stop that's closed, moved or gone is dropped at the next check and the route is rebuilt. A journey
  with too few stops left is taken down until it can be rebuilt.
- **Labelling:** suggested journeys are labelled "Suggested by Traversence" and kept apart from contributors' own
  journeys in feeds and counts.

## 4. Publishing

- **Like guides** (decision 0131): built and checked by the system, published by rule.
- **Staff:** may edit, pin or unpublish one, and their changes are kept.
- **People:** visitors can save a suggested journey to their trips (the Trip Planner), change it, and share it.

## 5. The site audit uses them

Every suggested journey is a guide path in the audit (decision 0145 §1). A visitor follows it in a browser: the
journey's page, then each stop. The audit checks:
- each page loads;
- its facts are true now;
- the directions open;
- the order makes sense.

A failure reaches the guide's Pulse case as well as the scorecard.

## 6. Questions for Jason: answered 2026-11-08

1. **Which shapes:** the guide's dominant themes decide its journeys (§1, Shapes).
2. **How many stops:** the guide decides. As many good stops as fit the time, the hours and the distances; no fixed
   range. A journey needs at least two usable stops to exist.
3. **Distances:** journeys, the system's and people's own, get everything they need to make the best journey possible
   (§7).
4. **Where they appear:** everywhere: the guide's page ("Suggested journeys"), Discovery, the Trip Planner and search,
   always labelled "Suggested by Traversence".
5. **Businesses:** a business can leave suggested journeys from its business portal (it stays in guides and search).
   Inclusion never depends on payment.

## 7. What a journey needs, and where each piece comes from

One journey engine (`app/lib/JourneyPlan.php`) serves both suggested journeys and the ones people make in the Trip
Planner. It answers: in what order, how long at each stop, how long between them, will it be open, and is it a good time.

**About each stop**

| Need | From | Cost |
|---|---|---|
| Where it is, what kind, which theme | our listings, landmarks, outdoor places, experiences | none |
| Open hours, closed days, seasons | listings; the crawler confirms them (§2) | none |
| How long people stay | a rule per kind (a café 45 min, a museum 90 min, a short trail 2 hr), or the experience's own `duration` | none |
| Effort and who it suits | the experience's `effort`; access notes on outdoor places | none |
| Why go, photo, its page | the guide's own words, the photo finder (0143), the stop's page | none |
| Still there, safe to show | the crawler's check; `Landmarks::visibleSql()` (0066) | none |

**Between stops**

| Need | From | Cost |
|---|---|---|
| Driving, walking or cycling time and distance | **openrouteservice** (OpenStreetMap data): free plan 2,000 routes and 500 matrices a day, a hard stop at the limit, never a bill | none |
| Kept as our own record | every leg found goes into `travel_legs` (below) and is reused everywhere, never asked for twice | none |
| When the service is down or the day's limit is reached | straight-line distance with a road factor (×1.3), marked "about" | none |
| The visitor's turn-by-turn directions | a map link that opens their own maps app (0058 §8) | none |

**Travel times are our own research (Jason, 2026-11-08).** We pull outside data once and keep it, so the site answers
from its own record:
- **`travel_legs`:** one row per pair of places and way of travel (drive, walk, cycle): minutes, distance, the road
  class and climb where given, its source and the date found. Kept, not cached: it doesn't expire.
- **Built ahead:** when a town gets a guide, the journey job asks once for the travel times among its places (the
  service's matrix, up to 50 places at a time) and stores them all. Later journeys, people's trips and "how far" on cards
  read the table and ask nothing.
- **Asked for only when missing:** a person's own trip with a place we have no leg for asks once, stores it, and the
  next person gets it free.
- **Renewed only when something changes:** a place moves, a person reports a route wrong, or the leg is two years old.
  Roads change slowly.
- **Reused across the site:** journeys, the Trip Planner, a guide's "getting around", "12 min drive" on cards, Near me,
  and the audit's checks that an order makes sense.
- **Licence (decision 0133's source register):** the times come from OpenStreetMap data (ODbL). They are used on our
  pages with the credit "© OpenStreetMap contributors"; if the table were ever shared outside the site, it would be
  under the same licence.
- Stay times per kind of place (above) are ours too, and learn from what people set in their own trips (counts only,
  never who).

**About the day**

| Need | From | Cost |
|---|---|---|
| Sunrise and sunset, daylight left | worked out on our server from the date and place | none |
| Weather and alerts | `Weather` (National Weather Service), already used by guides | none |
| Events on the day | our events and the guide's | none |
| Park and road closures | the National Park Service's alerts, once its key is in Railway (0058) | none |

**About the person** (Trip Planner, given by them, kept private): start point and time, time available, how they
travel, pace, and anything to avoid (long walks, stairs, steep trails).

**What the engine gives back:** the stops in order with arrive and leave times; each leg's time and distance; warnings
("closes at 5", "after sunset", "heat advisory", "90 minutes' drive"); a swap for any stop that's closed; and, for a
person's own journey, suggestions to fill a gap or reorder to save time. People can always override the order.

**Rules:**
- Only the server asks openrouteservice, from the job that builds journeys or when a person changes a trip, never
  for every page view. Its key is a secret in the live `.env` (Jason adds it, by hand).
- OpenStreetMap's credit ("© OpenStreetMap contributors") shows wherever its routes do.
- When the free daily limit runs out, the engine uses straight-line times and finishes the rest the next day. Nothing
  paid is used (no income, no expense). Driving routes for paying plans' heavier use can be added later, inside their
  allowance.
- Google Maps and Mapbox are not used for routing: both bill once past their free amounts.

## 8. On demand

Guides and journeys are also built where people search: decision 0147.

## Built (2026-11-08): `journeys-engine_20261108.zip`

- `app/lib/TravelLegs.php` and the `travel_legs` table (migration `2027-02-11_suggested_journeys.sql`): our own travel
  times. One openrouteservice matrix call per town (`ORS_API_KEY` in the live `.env`, punch list item 29; the
  service's address is `api.heigit.org/openrouteservice` since 2026, the old `api.openrouteservice.org` retired;
  `ORS_API_URL` overrides it), at most 400 a day, stopping for the day on a 403 or 429; straight-line estimates ("about") otherwise.
- `app/lib/JourneyPlan.php` and `suggested_journeys`: candidates from the guide's picks (eat, do, shop), its landmarks
  (never a protected one) and outdoor places, the ones its text names first; no closed business, none placed only at
  its ZIP's middle, none with `no_suggested_journeys` set. Themes by strength; "A day in" plus up to four themed
  journeys. Timed for the next Saturday in the place's time zone, with sunrise and sunset; meals at mealtimes.
- Job `journeys_tick` (hourly, three live guides a run, weekly or when the guide changes).
- Shown on the guide (a "Suggested journeys" section after Things to do) and on `/journeys/suggested.php?id=` with the
  engage bar and comments (engage kind `sj`).
- **Still to build:** the business portal switch for leaving journeys (the flag is read already), staff edit and pin
  in the admin (the columns exist), saving to the Trip Planner, and Claude's lines (decision 0147).
