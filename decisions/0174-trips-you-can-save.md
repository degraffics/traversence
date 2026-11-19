# 0174: Trips you can save: Add to a trip, the trip page, sending it to Maps, and routes on our own site

Status: **draft for Jason's review** (2026-11-19). Jason answered "Yes" to the decision pop-up "Draft one decision for
trips you can save" (reviewed by the session manager). The place-page release shows **Add to a trip** greyed, as
"Coming: trips you can save and add places to", until this is approved and built.

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
  - Times are shown in the place's time zone, labeled (CLAUDE.md "Times where they happened").
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
- **(c) FOSSGIS's public routing**, for light use only under its policy and with the same care as Overpass (0065). It was
  retired once for the same reason, so it's not recommended.

**Recommendation:** start with (b), and move to (a) when routes are used enough to outgrow its cap and revenue covers the
server. Routes are kept with us once asked for (as 0131's town routes are), so the same trip doesn't ask again. OSM is
credited on every route.

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
2. **Trips and Add to a trip** (§1–2): the tables, the picker, the guest's trip on the device, and Your trips.
3. **The trip page** (§3): days, reorder, times, the map with straight-line legs, Save as a trip from the planner.
4. **Sharing and Personal Guides:** the link, publishing, and counts for businesses and places.
5. **Routes on our own site** (§5), with the engine Jason chooses.

## Questions for Jason

1. Approve this decision, with its five releases in that order?
2. The routing engine: (b) openrouteservice's free tier to start (recommended), (a) self-hosted now, or decide at
   release 5?
3. Guests: keep one trip on the device until they make an account (recommended), or trips only with an account?
