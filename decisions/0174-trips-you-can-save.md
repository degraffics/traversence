# 0174: Trips you can save: Add to a trip, the trip page, sending it to Maps, and routes on our own site

Status: **accepted** (Jason, 2026-11-19, in the decision pop-up, reviewed by the session manager). His answers:
- the five releases in order (§7);
- routing: **(b) openrouteservice's free tier** (§5), its terms checked at release 5;
- guests: **one trip on the device** (§2).

He answered "Yes" earlier to "Draft one decision for trips you can save". **Add to a trip** stays greyed, "Coming", until
release 2. **Next:** release 1, Send to Maps.

Builds on:
- 0058 §1: Favorites (private) and **Personal Guides** ("published collections" of listings, places and journeys).
- 0146: suggested journeys, i.e. stops ordered for travel, each timed against its hours.
- 0056 §8: "creator-curated itineraries sharing one route model with the Trip Planner".
- The Trip Planner on Let's Explore (`dv_planner`): where, when, how long and what, "kept with the plan on this device
  until trip plans (save, share, day by day) arrive". This decision is that arrival.
- The work queue's two items (`handoffs/session-manager.md`, "Routes and trip plans on our own site"):
  - "cant we offer a route or trip plan using OSM and keep people on our own site?";
  - "take a planned trip and send the entire thing to google maps or apple maps on request of the user to start
    navigation".
- 0065 (OpenStreetMap, credited), 0066 (nations' land), 0058 §13 (counts, never who), CLAUDE.md "No income, no expense".

## Why

Jason, 2026-11-19, on a place's photo viewer: "maybe a way to create a trip right from here." Today a person can find a
place, save a business to their Address Book, and read a ready-made journey. They can't gather the places they want into
their own plan, or take that plan on the road.

## 1. What a trip is

- **A trip** is a member's own list of stops in order, grouped by day when it runs over several days. It has a name
  ("Trip to Show Low" by default), dates when known, and a note per stop.
- **A stop** is anything with a page and a point: a listing, a place or landmark, an outdoor place, an experience, or a
  ready-made journey's stop.
- **One model.** A stop has the same shape as a ready-made journey's stop (0146). So **Make it my trip** on a ready-made
  journey copies its stops into a new trip, and a trip later published as a Personal Guide reads like one.
- **Private by default**, like Favorites. Nobody sees a trip unless its owner chooses one of these:
  - **Share a link:** anyone with the link can view it, not edit it. The link can be turned off any time.
  - **Publish as a Personal Guide** (0058 §1): public, credited, and checked like a journey before it shows.
- **Never shown, and never counted per person.** Businesses and places get counts only: "added to 12 trips this month",
  never who (0058 §13).

## 2. Add to a trip

- **Where it appears:**
  - ⋯ on every content page (the place-page release's greyed item, switched on);
  - ⋯ on a Get Local card;
  - the map's card;
  - a ready-made journey ("Make it my trip");
  - the Trip Planner's results.
- **The picker:** your trips, the newest first, plus **New trip** (named for the place). It confirms with
  "Added to Trip to Show Low · Open it", and the action can be undone.
- **Guests** (never a dead end): one trip is kept on the device, as the planner keeps its plan today. They're offered an
  account to keep it and see it on another device. Signing in moves it into the account.
- **What can't be added is greyed, with the reason, never hidden:**
  - a place kept private (shelters, ListingSafety): "Its location is kept private: call them";
  - a story-only place (0066, R7: named and told, no pin): "Told, not placed: it has no point on the map";
  - a business closed for good: "Closed for good".
  - Stops on a nation's land use the public place `Nations::pin()` gives (0066).

## 3. The trip page

- **The stops:** in order, by day. Drag or ↑ ↓ to reorder (keyboard and phone); × to remove, with Undo.
- **The map:** numbered pins in the same style as Get Local's list beside the map, the route between them (§5), and the
  miles and time per leg and for the day.
- **Times:**
  - With a start time, each stop is timed against its hours, as ready-made journeys are (0146).
  - A stop that would be closed when you arrive says so ("Closed when you'd arrive: opens 11 AM"), with a way to move it.
  - **Stored and shown with the trip's time zone** (CLAUDE.md "Times where they happened"). A trip's days and times are
    kept with the time zone of its first stop's place, falling back to the device's when there's no place yet. They're
    always shown labeled ("Sat, Nov 22, 9:00 AM MST"); a stop in another zone shows its own.
- **Actions:** Share, Print, Send to Maps (§4), Publish as a Personal Guide, Delete (with a yes, and a way back for 30 days).
- **Where trips live:**
  - **Your trips** is reached from the account menu and from Let's Explore's Trip Planner.
  - The planner gains **Save as a trip**: its where, when and how long become a new trip, with the ready-made journeys
    for that place offered as a start.

## 4. Send the whole trip to Google Maps or Apple Maps

From the work queue, unchanged. It's free: a link the person taps, with no key and no call from our server. It can be
built first, before trips, on guides and ready-made journeys.
- Google Maps links take an origin, a destination and stops in order. Apple Maps' support for several stops in one link
  is checked against Apple's current documentation at build.
- **Too many stops for one link:** the trip is sent in legs ("Leg 1 of 3"), each opening the next. Nothing is ever cut
  silently.
- The person picks Google or Apple, and the choice is remembered on the device. The route starts from "My location" or
  from the first stop.

## 5. Routes on our own site, from OpenStreetMap

The route drawn on our map, with its miles and time, so people stay on the site to plan. **Open in Google Maps** stays as
the way to get turn-by-turn directions on the road.

Until an engine is chosen, legs show as a straight-line estimate, labeled "about".

The engine is Jason's choice. These are the queue's options, under "No income, no expense" and OSM's usage policies:
- **(a) Self-hosted OSRM or Valhalla on Railway**, from the Geofabrik extracts we already download. Unlimited, but it has
  a monthly server cost, so it needs revenue or Jason's OK.
- **(b) openrouteservice's free tier** (a key and a daily cap), registered in the Service register (0173). It goes through
  `StagingGate::hold()` and is counted on the Control Board. It costs nothing until we outgrow its cap.
  - **Its terms are to check at release 5,** from its own site: the free plan's limits, whether commercial use is
    allowed, the credit it asks for, and whether routes may be stored. openrouteservice.org isn't reachable from the
    session that wrote this draft, and these aren't stated from memory. If storing isn't allowed, routes aren't kept
    (see below), and each trip asks again within the cap.
- **(c) FOSSGIS's public routing**, for light use only under its policy and with the same care as Overpass (0065). It was
  retired once for the same reason, so it's not recommended.

**Recommendation:** start with (b), and move to (a) when routes are used enough to outgrow its cap and revenue covers the
server.

**OpenStreetMap's licence (ODbL) on routes:**
- **Showing a route** on our map is a Produced Work, so it needs credit only: "© OpenStreetMap contributors" on every route.
- **Keeping routes once asked for** (as 0131's town routes are, so the same trip doesn't ask again) makes a database
  derived from OSM. So:
  - kept routes stay in their own table, credited, and are never mixed into our own records (0065);
  - they're offered under the ODbL on request, as the queue's "how our copy is made" note describes for our OSM copy;
  - a route kept from (b) is kept only if openrouteservice's terms allow it (checked at release 5).

## 6. Data and rules

- **Tables:**
  - `trips`: id, user_id, name, starts_on, days, tz, share_token, shared, guide_id, created_at, updated_at.
  - `trip_stops`: trip_id, day, position, kind, ref, note, planned_at.
  - Both are named in `app/lib/staging-tables.php` (zip check R14).
  - The migrations are safe to run twice.
- **Account deletion** deletes the person's trips. A trip published as a Personal Guide follows the journeys' rules
  (0056) for what stays.
- **No AI** in any of it. No cost but (b)'s free tier, or (a) once approved.
- **The FAQ** gets entries for each release, saying "coming" for what isn't built.

## 7. Releases, in order (each its own release and OK)

1. **Send to Maps** on guides and ready-made journeys (§4). Free, small, no tables.
2. **Trips and Add to a trip** (§1–2): the tables, the picker, the guest's trip on the device, and Your trips. (Delete, with its 30 days, came forward from 3; Add to a trip on the map's card and in the Trip Planner's results moved to 3, 2026-11-20.)
3. **The trip page** (§3): days, reorder, times, the map with straight-line legs, Save as a trip from the planner, and Add to a trip on the map's card and in the Trip Planner's results (moved from 2).
4. **Sharing and Personal Guides:** the link, publishing, and counts for businesses and places.
5. **Routes on our own site** (§5), with the engine Jason chooses.

## Questions for Jason (answered 2026-11-19: Approve; openrouteservice's free tier; one trip on the device)

1. Approve this decision, with its five releases in that order?
2. The routing engine:
   - **(b) openrouteservice's free tier** (recommended to start): no cost within its cap. The stops' points go to
     openrouteservice (HeiGIT, Heidelberg), with no names or who; its terms are checked at release 5.
   - **(a) self-hosted** (OSRM or Valhalla on Railway): a monthly server cost. Nothing leaves our own servers.
   - **(c) FOSSGIS's public routing:** no cost, light use only. The points go to FOSSGIS in Germany. Not recommended.
   - Or decide at release 5.
3. Guests: keep one trip on the device until they make an account (recommended), or trips only with an account?

**Note (2026-11-20): release 1, Send to Maps (`send-to-maps.zip`, reviewed by the session manager; `handoffs/send-to-maps.md`).**
- **What it does:** a suggested journey's route icon and a place's "Make a day of it" now open Send to Maps.
  - The visitor picks Google Maps or Apple Maps, and My location or the first stop. Both are remembered on the device.
  - Every stop goes, in order, in legs of a start, 8 between and an end.
- **Apple:** its link was checked against Apple's current page. It takes repeated `waypoint`s, and needs iOS 18.4 or
  macOS 15.4.
- **Google:** its documentation couldn't be reached from the session, so the site's proven count was kept.
- **Also fixed, the same rules as Directions:**
  - a shelter or a mailbox could be a stop in a suggested journey;
  - a private place could be a landmark's place to eat.

**Note (2026-11-20): release 2, trips and Add to a trip (`trips.zip`; `handoffs/trips.md`).**
- **Where Add to a trip is:** ⋯ on businesses, places, outdoor places and towns, Get Local's cards, and Make it my trip on
  a ready-made journey.
- **Your trips:** remove with Undo, rename, Send to Maps, and Delete, kept 30 days (brought forward from §3 so every
  action has a way back).
- **Guests:** one trip on the device, moved into the account at sign-in.
- **The data:** §6's tables, plus `trips.deleted_at` and `trip_stops.id`/`added_at`.
- **Moved to release 3:** the map's card and the Trip Planner's results, beside the trip page's map and Save as a trip.

**Note (2026-11-20): release 3, the trip page (`trip-page.zip`; `handoffs/trip-page.md`).**
- **The page:** each trip has its own page with days, order (drag, ↑ ↓, a day), stays, and times against hours and
  daylight in the first stop's zone, labelled.
- **The map:** numbered pins and "about" straight-line legs.
- **Also:** Print, Save as a trip from the Trip Planner (the place's ready-made journeys offered), and Add to a trip on
  the map's card and in Peek.
- **The data:** `trips.start_min`, `trips.place_key` and `trip_stops.stay` were added.

**Note (2026-11-20): release 4, sharing, Personal Guides and counts (`trip-share.zip`; `handoffs/trip-share.md`, each
behaviour with its source).**
- **Share:** a view-only link; turning it on again makes a new link.
- **Personal Guides:** live with the trip, checked like a journey (a contributor, the automated check, reportable,
  hidden by staff in Admin → Journeys), and a content page.
- **Shown publicly:** a private place never shows, nor its name; a business marked closed shows greyed.
- **Counts:** a business's in its insights ("Added to trips"), a place's in Admin → Insights; never who.
- **Jason's answers to the pop-up's four questions are recorded here.**

