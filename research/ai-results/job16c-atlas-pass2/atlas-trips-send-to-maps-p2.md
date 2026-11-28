# research/platform-atlas/trips-send-to-maps.md

> **Draft for human review.** This draft is built only from the attached files:
> - `charter.md`, `brand.md`, `prd.md`, `site-audit/pages.md`
> - decisions 0171, 0174, 0175, 0176, 0135
> - `trip/index.php`
> - `app/lib/Trips.php`, which is cut off at line 546
>
> Anything else is marked **not shown**.

## Coverage of the code

| File | What is visible |
|---|---|
| `sync-storage/trip/index.php` (lines 1–5) | The whole file. |
| `sync-app/lib/Trips.php` (lines 1–546) | Partial. The file is cut off inside `plan()` at line 546. The rest of `plan()`, and anything after it, is **not shown**. |

The following files are **not shown**, so this entry describes them only from the documents:
- `user/trips/trip.php` (the page itself)
- `user/trips/` (the list page)
- `guide/personal.php`
- `js/tv-trips.js` (named in the `Trips.php` header, line 6)
- the Send to Maps code (release 1)
- whatever endpoint the pages use to call `Trips`
- `TravelLegs.php`, `JourneyPlan.php`, `PlatformCounts.php`, `Hours.php`, `ListingSafety.php`, `Nations.php`
- the migrations

The `/user/api/*` list in `pages.md` §6 has no trips entry.

---

## (a) What each person can do, page by page

**Routes, from `pages.md` §1 and §3:**

| Route | What it is | Status in pages.md |
|---|---|---|
| `/user/trips/` | Your trips: a member's trips (a guest's one trip on the device), stops, Send to Maps, Delete | Live (release 2) |
| `/user/trips/trip.php?id=` | A trip's own page: days, order, times against hours, the map, Print (owner only) | Live (release 3) |
| `/trip/?t=` | A shared trip, view only | Live (release 4) |
| `/guide/personal.php?id=` | A Personal Guide: published, credited, likes, comments, Report | Live (release 4) |
| `/discovery/?view=plan` | The Trip Planner on Let's Explore | Live |

**Entry points named in 0174's notes.** "Add to a trip" appears in these places:
- the ⋯ menu on businesses (`/listing/view.php?id=`), places (`/place/landmark.php?id=`), outdoor places (`/place/recreation.php?id=`) and towns (`/hub/…`);
- Get Local's cards (`/directory/`);
- "Make it my trip" on a ready-made journey;
- the map's card and Peek (release 3);
- the Trip Planner's "Save as a trip".

"Send to Maps" opens from a suggested journey's route icon (`/journeys/suggested.php?id=`) and from a place's "Make a day of it" (0174, release 1 note). The page code behind all of these entry points is **not shown**.

**Traveler (Let's Explore)**
- Gather places into a trip from any of the entry points above.
- Copy a ready-made journey's stops into a new trip ("Make it my trip"). `Trips::fromJourney` (`Trips.php:288–299`) maps the stop refs `entity:`, `landmark:`, `osm:` and `rec:`.
- Arrange the trip on its page:
  - order stops by day (`move`, `:461–474`);
  - set a start date, the number of days (up to 14) and a day start time (`configure`, `:477–506`);
  - set how long to stay at each stop (`setStay`, `:509–517`, with choices from `STAYS` at `:26`).
- See each stop timed against its hours, or against daylight for outdoor places. Legs are straight-line estimates marked "about" (`plan`, `:519–546`, partly shown).
- Send the whole trip to Google Maps or Apple Maps. Per the 0174 release 1 note:
  - stops go in legs of a start, 8 stops between, and an end;
  - the person picks Google or Apple, and whether to start from "My location" or the first stop;
  - both choices are remembered on the device;
  - Apple's link needs iOS 18.4 or macOS 15.4.

**Resident (Get Local)**
- The same tools apply to local errands and day plans, starting from Get Local cards.
- No resident-specific behavior is shown in the code.

**Member (signed in)**
- Keep up to 100 trips, each with up to 60 stops (`MAX_TRIPS` and `MAX_STOPS`, `:19–20`).
- Rename a trip, remove a stop with Undo (`remove` returns what Undo needs, `:220–228`), and delete a trip. A deleted trip is kept 30 days with Put back (`delete`, `:251–255`).
- Share a view-only link and turn it off at any time. Turning it on again makes a new link (`share`, `:347–353`).
- Publish a trip as a Personal Guide (`publishGuide`, `:398–424`), and withdraw it or put it back (`withdrawGuide`, `:427–434`).

**Guest**
- Keeps one trip on the device. Signing in moves it into the account through `claim()` (`:261–272`), which reports which stops were left out and why.
- The device-side code (`js/tv-trips.js`) is **not shown**.

**Business**
- Sees counts only: "Added to trips" in its insights (0174, release 4 note), written through `ListingActions::count($pdo, id, 'trip')` (`:321–324`).
- The business insights page and its route are **not shown**.
- Its own people and the platform team are not counted (`:320–322`).
- There is no paid or business-controlled way to appear in trips.

**Staff**
- Hide or show a Personal Guide in Admin → Journeys (`/admin/journeys.php`; `hideGuide` and `guidesForStaff`, `:437–448`).
- See place counts in Admin → Insights (`/admin/insights.php`).
- The staff permission check is not inside `hideGuide`. The caller's check is **not shown**.

---

## (b) How it works underneath

**Data**
- `trips` and `trip_stops` are the tables from 0174 §6.
- Columns added in later releases (0174 notes):
  - release 2: `deleted_at`, plus `trip_stops.id` and `added_at`;
  - release 3: `start_min`, `place_key` and `stay`.
- `personal_guides` holds published guides (`Trips.php:366–448`).
- The tables are checked lazily (`ready()`, `:30–35`; `guidesReady()`, `:368–373`).
- Migrations and the `staging-tables.php` entries are **not shown**.

**A stop is a kind and a ref, resolved fresh on every read**
- The kinds (`KINDS`, `:18`) are:
  - `listing` (an `entities.id`);
  - `lm` (a landmark's GNIS id);
  - `rec` (a Recreation.gov id);
  - `place` (a hub, geo-hub, cluster, town or sub key);
  - `osm` (a place from our OpenStreetMap copy).
- `key()` (`:38–47`) checks the format of each ref.
- `resolve()` (`:53–134`) applies the day's rules each time a stop is read:
  - suppressed listing → "No longer listed." and flagged `private` (`:74`);
  - confidential (shelter) listing → "Its location is kept private: call them." and flagged `private` (`:81`, `ListingSafety::isConfidential`);
  - marked closed → greyed (`:82`);
  - mailbox-only address → greyed (`:83`);
  - no map point → greyed (`:84`, `:98`, `:108`, `:117`);
  - landmark that is told but not placed → "Told, not placed" (`:94–96`, through `Landmarks::get`, which per the code comment applies `visibleSql`);
  - a point on a nation's land is replaced by that nation's public place (`Nations::pin`, `:59`);
  - an OSM stop is credited "OpenStreetMap contributors" and links to openstreetmap.org (`:121–129`).
- A stop that can no longer be shown keeps its place in the trip, greyed with the reason (`stops()`, `:231–239`; `addable()`, `:137–141`).

**Adding a stop**
- `add()` (`:194–217`):
  - checks the trip is the person's own;
  - resolves the stop and refuses it with the reason if it can't go in a trip;
  - puts it at the end of the last day that has stops;
  - enforces the 60-stop limit;
  - returns the existing id as a negative number if the stop is already in the trip;
  - then calls `touch()` and `counted()`.
- `touch()` (`:302–309`) sets the trip's time zone from the first stop that resolves, looking at up to 5 stops, through `JourneyPlan::timezone` (`tzOf`, `:451–456`).

**Counting**
- `counted()` (`:316–327`) records the add:
  - for a business, through `ListingActions` (its insights);
  - for any other place, through `PlatformCounts 'trip'`.
- Bots, the team and a listing's own people are skipped.
- `addedThisMonth()` (`:336–341`) shows the count on a place's page. Business counts stay in the business's insights. `SHOW_ZERO_TRIPS` (`:330`) shows the count even at 0.

**Planning (`plan`, `:524–546`, partly shown)**
- The time zone is the trip's `tz`, falling back to `America/Phoenix` (`:528–529`).
- The day starts at `start_min`, defaulting to 540 minutes (9:00 AM, `:531`).
- Each leg uses `TravelLegs::miles`, then `modeFor`, then `estimate`, with `'about' => true` (`:542–545`).
- The open/closed checks and the day totals come after the cut-off and are **not shown**.

**Sharing**
- `share()` makes a 32-hex token from `random_bytes(16)`.
- `byToken()` (`:356–362`) opens only a trip that is shared and not deleted.
- `/trip/index.php` is just `require …/user/trips/trip.php` (`trip/index.php:5`). Its comment says the shared view has "no controls and none of its owner's details" (`:2–3`). How `trip.php` strips those, and whether it leaves out stops flagged `private`, is **not shown**.

**Personal Guides**
- `publishGuide()` (`:398–424`) requires all of the following:
  - the account passes `Standing::guard`;
  - the person is a contributor (`Journeys::contributor`);
  - a title;
  - an intro of at least 40 characters;
  - at least one stop that resolves.
- `ListingSafety::contentProblem` can hold the guide (`held`) with the reason.
- A guide hidden by staff can't be republished by its owner (`:414`, `:431`).
- `guide()` serves only a published guide whose trip isn't deleted (`:384–390`).

**Map styling (`brand.md` §4, 2026-11-22 note)**
- The chosen day's route is drawn in Electric Lime `#B6F22E` over a Deep Umber casing.
- Other days show the casing only, faded.
- A "Route: Day 1 / Day 2" row chooses the day. This is named as the first map to draw routes, in `user/trips/trip.php`, which is **not shown**.

---

## (c) Decisions

| Decision | What it rules here |
|---|---|
| **0174** (accepted 2026-11-19) | The area's own decision. It covers: what a trip and a stop are (§1); Add to a trip, the greyed reasons and guests (§2); the trip page and time zones (§3); Send to Maps in legs (§4); routing on our own site with openrouteservice's free tier chosen (§5); tables and deletion (§6); five releases in order (§7). |
| **0058 §1** | Favorites and Personal Guides, the basis for publishing a trip (cited in 0174, not attached). |
| **0058 §13** | Counts, never who. Applied in `counted()` and `addedThisMonth()` (cited in code, not attached). |
| **0146** | Suggested journeys timed against hours. Trips reuse the stop model and the time-zone rule (`Trips.php:8`, `:450`; not attached). |
| **0056 §8** | One route model shared by itineraries and the Trip Planner (cited in 0174, not attached). |
| **0065** | OSM data is credited and never mixed into our records (`:121`; not attached). |
| **0066** | Nations' land uses a public pin (`:59`; not attached). |
| **0173** | Service register. Required for the openrouteservice key in release 5 (cited in 0174 §5, not attached). |
| **0171 / 0175** | The Control Board counts the routing service's calls (0174 §5). 0175 §5's integration procedure (fallback named, `StagingGate::hold()`, staging test) would apply to a routing integration. This is inferred from 0175's general rule; 0174 doesn't cite 0175. |
| Others cited in the code only (records not attached) | 0072 / 0164 R12 (the 'closed' mark, `:82`); 0094 (the `save` pattern for business counts); 0087 (a business's own people not counted); 0079 (Standing); 0058 §20 (counts shown at 0). |

0135 (Marketplace) and 0176 (dispatcher) were attached but rule nothing in this area.

---

## (d) Live vs coming

**Live** (0174's status line "releases 1–4 are live", with PR numbers, and `pages.md`):

| Release | What shipped |
|---|---|
| 1 (#219) | Send to Maps. Code **not shown**. |
| 2 (#220) | Trips, Add to a trip, Your trips, the guest's trip, Delete with 30 days to restore. |
| 3 (#221) | The trip page: days, reorder, stays, times, straight-line legs, Print, Save as a trip, map card and Peek. |
| 4 (#227) | Share link, Personal Guides, counts. |

**Coming**
- **Release 5, routes on our own site (§5).**
  - Engine: openrouteservice free tier.
  - Its terms (commercial use, storing routes, credit) are to be checked at release 5.
  - Status: "not started; building is held (Jason, 2026-11-20)".
  - Until then, legs are straight-line estimates marked "about" (`Trips.php:9`, `:545`).
  - 0174 §5 lists the ODbL obligations for kept routes: their own table, credited, offered on request.

**Mismatches between the decision and the visible code (to verify)**
1. **Stop kinds.** 0174 §1 lists "an experience" as a stop. `KINDS` (`:18`) has no experience kind, and `fromJourney` drops any journey stop that isn't entity, landmark, osm or rec.
2. **Time-zone fallback.** 0174 §3 says a trip falls back to the device's zone when there's no place yet. `plan()` falls back to `America/Phoenix` (`:528`). Whether the client passes the device zone into `create()` is **not shown**.
3. **The 30-day purge.** Deleted trips are purged only inside `mine()` (`:160–161`), which runs when the owner opens their list. A trip deleted by an owner who never comes back isn't removed by any code shown. A scheduled job may exist but is **not shown**.
4. **Account deletion.** 0174 §6 says deleting an account deletes the person's trips. This is **not shown**.
5. **Closed listings.** `remove()` (`:222`) doesn't exclude trips that are deleted, unlike `move` and `setStay`. This is minor.
6. **Count month.** `addedThisMonth` uses the server's `date('j')` (`:339`). The server zone is **not shown**.

---

## (e) Charter promises and economic results

**Immersion and connection.** The charter calls Traversence "an immersion and connection engine" that removes "friction from how people find and interact with the places around them" (`charter.md`, The Mission & Purpose).
-