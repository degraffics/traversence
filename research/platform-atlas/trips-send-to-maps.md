```markdown
# Platform atlas: trips-send-to-maps

*Draft for human review. Sources: `decisions/0174` (the governing decision), `site-audit/pages.md`, `charter.md`,
`brand.md`, `prd.md`, and nearby decisions (0171, 0175, 0176, 0135). The only code attached is
`website/trip/index.php` (complete, 5 lines) and `app/lib/Trips.php`, which is cut off at line 546, partway through
`plan()`.*

**Not shown, so not described from code:**
- the trip pages `user/trips/index.php`, `user/trips/trip.php` and `guide/personal.php`;
- the Send to Maps code and `js/tv-trips.js`;
- the trips API;
- `TravelLegs`, `JourneyPlan`, `PlatformCounts`, `ListingActions`, `ListingSafety` and `Nations`;
- the migrations, the FAQ and the hand-offs (`handoffs/send-to-maps.md`, `trips.md`, `trip-page.md`, `trip-share.md`).

Behaviour below that comes only from 0174's notes is labelled as documented, not verified in code.

---

## (a) What each person can do, page by page

### Traveler (the main audience; Let's Explore)

**Send to Maps** (0174 §4 and its release-1 note; code not shown):
- **Where it opens from:**
  - a suggested journey's route icon (`/journeys/suggested.php?id=`);
  - a place's "Make a day of it" (the page is not named in the docs);
  - Your trips (release-2 note).
- **The choices:**
  - Google Maps or Apple Maps;
  - start from "My location" or from the first stop.
  - Both choices are remembered on the device.
- **How the stops are sent:** every stop goes, in order, in legs of a start, 8 between and an end ("Leg 1 of 3"). Nothing
  is cut silently.
- **Apple:** needs iOS 18.4 or macOS 15.4.

**Add to a trip** (release-2 and release-3 notes):
- **Where it appears:**
  - ⋯ on businesses (`/listing/view.php?id=`);
  - ⋯ on places (`/place/landmark.php?id=`);
  - ⋯ on outdoor places (`/place/recreation.php?id=`);
  - ⋯ on towns (route not stated);
  - Get Local cards (`/directory/`);
  - the map's card and Peek;
  - the Trip Planner's results (`/discovery/?view=plan`).
- **Make it my trip** on a ready-made journey copies its stops into a trip (`Trips::fromJourney`, `Trips.php:288`).
- **What can't be added is greyed with the reason, never hidden.** `Trips::addable` (`Trips.php:137`) returns the reason
  from `resolve()`:
  - kept private: "call them";
  - told, not placed;
  - marked closed;
  - only a mailbox address;
  - no point on the map;
  - no longer listed or shown.

**Save as a trip** from the Trip Planner. The place's ready-made journeys are offered as a start (release-3 note).

**View a shared trip** at `/trip/?t=` (`trip/index.php:2-5`):
- It is the same page as the owner's, with no controls and none of the owner's details.
- Places kept private are left out (`pages.md`).

**Read a Personal Guide** at `/guide/personal.php?id=`: credited, with likes, comments and Report (`pages.md`). The page
code is not shown.

### Member (signed in)

**Your trips** at `/user/trips/` (`pages.md`, "Live (release 2)"):
- a list, newest first;
- rename;
- remove a stop with Undo;
- Send to Maps;
- Delete, kept 30 days with Put back.

**The trip page** at `/user/trips/trip.php?id=` (owner only; "Live (release 3)"):
- days, up to 14;
- reorder by drag, ↑ ↓, or moving a stop to another day;
- how long at each stop;
- a start date and a start time;
- times against hours or daylight, in the first stop's time zone;
- a map with numbered pins and "about" straight-line legs;
- Print.

**Share:** a view-only link. Turning it on again makes a new link, and turning it off kills the old one
(`Trips.php:347-353`).

**Publish as a Personal Guide:**
- The member must be a contributor (18+, a public profile, the guidelines).
- The guide needs a title, an intro of at least 40 characters and at least one showable stop.
- An automated check may hold it.
- The member can Withdraw it and put it back (`Trips.php:398-434`).

**Limits** (`Trips.php:19-22`): 100 trips and 60 stops per trip.

### Guest

- One trip is kept on the device (0174 §2; `Trips.php:6` names `js/tv-trips.js`, not shown).
- Signing in moves it into the account. `Trips::claim` (`Trips.php:261`) returns what was added and what was left out,
  with the reason.

### Resident

- No resident-specific surface is shown or documented for this area.
- A resident uses the same Add to a trip and Send to Maps entry points, including from Get Local cards.

### Business

- **No controls of its own.**
- **Count only:** an "Added to trips" figure in its insights (release-4 note; `Trips.php:316-327` counts into
  `listing_actions` as `trip`). The route of the insights page is not shown.
- **Not counted:** the team, the business's own people and bots (`Trips.php:320-322`).
- **Never shown:** who added it. A business's count is not shown on its public page (`Trips.php:333-339`).

### Staff

- Personal Guides are hidden or shown again in Admin → Journeys (`/admin/journeys.php`; `Trips::hideGuide` and
  `guidesForStaff`, `Trips.php:437-448`).
- Counts for non-business places are in Admin → Insights (`/admin/insights.php`).

---

## (b) How it works underneath

**Pages:**
- `website/trip/index.php:5` requires `user/trips/trip.php`. The owner/shared split lives inside that file, which is not
  shown.

**Library:** `app/lib/Trips.php` (one `final class Trips`).

**Kinds of stop** (`:18`), each a kind plus a ref, checked by `key()` (`:38-47`):
- `listing`: an `entities.id`;
- `lm`: a GNIS id;
- `rec`: a RIDB id;
- `place`: `hub|geo_hub|cluster|town|sub:N`;
- `osm`: `node|way|relation/N`.

**Resolved fresh every time** by `resolve()` (`:53-134`), so the day's rules apply to stored stops:
- **listing:** joins `entities`, `categories` and `entity_metadata`. In order, it refuses:
  - a suppressed listing (`:74`);
  - a confidential category (`ListingSafety::isConfidential`, `:81`);
  - a listing marked closed (0072, 0164 R12, `:82`);
  - a mailbox-only address (`:83`);
  - a listing with no coordinates (`:84`).
  
  It parses hours (`:87`).
- **lm:** `Landmarks::get`, which goes through `visibleSql()`. A story-only landmark becomes "Told, not placed"
  (`:93-96`).
- **rec:** `RecBulk::get` (`:105`).
- **place:** `Guides::place` (`:115`).
- **osm:** our `open_places` copy, credited "OpenStreetMap contributors", linking out to openstreetmap.org (`:122-129`).
- **Every point** passes `Nations::pin()`, the public place for a nation's land (`:59`).

**Writing a trip:**
- `create()` enforces the 100-trip cap (`:183-184`).
- `add()` (`:194-217`):
  - checks ownership;
  - refuses unresolvable stops with the reason;
  - returns a duplicate as a negative id (`:208`);
  - places the stop on the last day that has stops;
  - `touch()`es the trip;
  - counts the add.
- `touch()` (`:302-309`) sets the trip's `tz` from the first resolvable stop among the first five, through
  `JourneyPlan::timezone` (`:451-456`).

**Trip page operations:**
- `move()` (`:461-474`);
- `configure()` (`:477-506`): when days are cut, their stops go to the end of the last day, so nothing is dropped
  (`:496`);
- `setStay()` (`:509-517`): choices in `STAYS`, defaults by kind in `STAY` (`:24-26`).

**`plan()`** (`:524-`):
- Builds each day from `start_min`, which defaults to 540 (9:00) (`:531`).
- Legs come from `TravelLegs::miles/modeFor/estimate`, flagged `'about' => true` (`:542-545`).
- The rest of the function is truncated and not shown.

**Delete:**
- A soft delete through `deleted_at` (`:251-255`).
- The purge of deletes older than 30 days runs inside `mine()` (`:160-161`), that is, when the owner next lists their
  trips.

**Sharing:**
- The token is 16 random bytes in hex (`:350`).
- `byToken()` requires `shared = 1` and the trip not deleted (`:356-362`).

**Personal Guides:**
- Kept in a `personal_guides` table, with statuses published, held, hidden and withdrawn (`:366`).
- Publishing (`:398-424`) runs:
  - `Standing::guard`;
  - `Journeys::contributor`;
  - `ListingSafety::contentProblem` (held with the reason).

**Counts:**
- Listings go through `ListingActions::count(…, 'trip')`.
- Other places go through `PlatformCounts::add('trip', kind:ref)` (`:316-327`).
- `addedThisMonth()` gives a place's monthly count, shown even at 0 (`:330-341`).

**Tables** (0174 §6 and its notes):
- `trips`: plus `deleted_at`, `start_min` and `place_key`;
- `trip_stops`: plus `id`, `added_at` and `stay`;
- `personal_guides`: its columns are inferred only from queries; the migration is not shown.
- `ready()` and `guidesReady()` (`:30-35`, `:368-373`) degrade gracefully until the migrations run.

---

## (c) Decisions

| Decision | What it rules here |
|---|---|
| **0174** | The governing record. Defines a trip and a stop (§1); Add to a trip and the guest's one trip (§2); the trip page and its time zones (§3); Send to Maps with legs and no silent cuts (§4); routes from OSM, engine (b) openrouteservice's free tier with terms checked at release 5, and ODbL credit and separate storage (§5); tables and account deletion (§6); five ordered releases (§7). Jason's answers: approve, (b), one trip on the device. |
| 0058 §1, §13 | Personal Guides as published collections; counts only, never who. |
| 0146 | Ready-made journeys: the stop shape and timing against hours. |
| 0056 §8 | Itineraries share one route model with the Trip Planner; journeys' rules for what stays after deletion. |
| 0065 | The OSM copy is credited and never mixed into our records (`Trips.php:121`). |
| 0066 | `Nations::pin()`, and landmarks' `visibleSql()` (`Trips.php:59`, `:93`). |
| 0072 / 0164 R12 | The 'closed' mark (`Trips.php:82`). |
| 0079, 0056/0058 | Standing and contributor checks before publishing (`Trips.php:393-405`). |
| 0087, 0094 | A business's own people aren't counted; the count uses the same mechanism as `save` (`Trips.php:312-315`). |
| 0173 / 0175 / 0171 | For release 5: register the routing service, `StagingGate::hold()`, Control Board counting (0174 §5b). Design only. |
| brand.md §4 notes | Routes in Electric Lime over Deep Umber casing; trip map option "B" (the chosen day lime, other days casing only). See the discrepancy in (d). |

---

## (d) Live vs coming

**Live** (0174 status of 2026-11-20 and `pages.md`):
- Release 1, Send to Maps (#219).
- Release 2, trips and Add to a trip (#220).
- Release 3, the trip page (#221).
- Release 4, sharing, Personal Guides and counts (#227).

The code shown matches releases 2–4. Release 1's code is not shown.

**Coming:**
- **Release 5, routes on our own site** (§5): "not started; building is held (Jason, 2026-11-20)."
  - Legs stay straight-line