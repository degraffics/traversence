# Platform atlas: trips-send-to-maps (merged)

*Draft for human review. This entry merges two independent passes, P1 and P2.*

**How to read the tags:**
- Untagged: both passes support it.
- **single-pass (P1)** or **single-pass (P2)**: only one pass says it.
- **passes differ**: the passes disagree.

Line citations are given as each pass wrote them. Where the line ranges differ, both are shown.

**Truncation:**
- P1 stops partway through (d), at "Legs stay straight-line", and has no (e).
- P2 stops early in (e), after one paragraph.

**Sources both passes used:**
- 0174 (the governing decision);
- `site-audit/pages.md`, `charter.md`, `brand.md` and `prd.md`;
- decisions 0171, 0175, 0176 and 0135;
- the trip shared-view `index.php`, 5 lines, complete;
- `app/lib/Trips.php`, cut off at line 546 inside `plan()`.

**Passes differ on file paths.**
- P1 gives `website/trip/index.php` and `app/lib/Trips.php`.
- P2's coverage table gives `sync-storage/trip/index.php` and `sync-app/lib/Trips.php`.

**Not shown, so described from documents only:**
- the trip pages `user/trips/index.php`, `user/trips/trip.php` and `guide/personal.php`;
- the Send to Maps code (release 1);
- `js/tv-trips.js` (named at `Trips.php:6`);
- the trips API or endpoint;
- `TravelLegs`, `JourneyPlan`, `PlatformCounts`, `ListingSafety` and `Nations`;
- the migrations.

Items only one pass lists as not shown:
- `ListingActions`, the FAQ and the hand-offs (`handoffs/send-to-maps.md`, `trips.md`, `trip-page.md`, `trip-share.md`): **single-pass (P1)**.
- `Hours.php` and the `staging-tables.php` entries: **single-pass (P2)**.
- The `/user/api/*` list in `pages.md` §6 has no trips entry: **single-pass (P2)**.

---

## (a) What each person can do, page by page

**Routes** (`pages.md`):

| Route | What it is | Status |
|---|---|---|
| `/user/trips/` | Your trips | Live (release 2) |
| `/user/trips/trip.php?id=` | The trip page (owner only) | Live (release 3) |
| `/trip/?t=` | A shared trip, view only | Live (release 4) |
| `/guide/personal.php?id=` | A Personal Guide | Live (release 4) |
| `/discovery/?view=plan` | The Trip Planner | Live |

P2 sets these out as a table citing `pages.md` §1 and §3. P1 gives the same routes inline.

### Traveler (Let's Explore)

**Send to Maps** (0174 §4 and its release-1 note; code not shown)
- **Opens from:**
  - a suggested journey's route icon (`/journeys/suggested.php?id=`);
  - a place's "Make a day of it" (the page is not named);
  - Your trips (P1 cites the release-2 note).
- **The choices:**
  - Google Maps or Apple Maps;
  - start from "My location" or from the first stop.
  - Both choices are remembered on the device.
- **How stops are sent:** every stop goes, in order, in legs of a start, 8 between and an end.
  - "Leg 1 of 3" and "nothing is cut silently": **single-pass (P1)**.
- **Apple:** needs iOS 18.4 or macOS 15.4.

**Add to a trip** (0174's release-2 and release-3 notes)
- **Where it appears:**
  - the ⋯ menu on businesses (`/listing/view.php?id=`), places (`/place/landmark.php?id=`) and outdoor places (`/place/recreation.php?id=`);
  - the ⋯ menu on towns. **Passes differ:** P1 says the route is not stated; P2 gives `/hub/…`;
  - Get Local cards (`/directory/`);
  - the map's card and Peek (P2 places these in release 3);
  - the Trip Planner's results or "Save as a trip".
- **Make it my trip** copies a ready-made journey's stops into a trip.
  - Code: `Trips::fromJourney` (P1: `Trips.php:288`; P2: `:288–299`).
  - It maps the stop refs `entity:`, `landmark:`, `osm:` and `rec:`: **single-pass (P2)**.
- **What can't be added is greyed with the reason, never hidden.**
  - Code: `Trips::addable` returns `resolve()`'s reason (P1: `:137`; P2: `:137–141`).
  - The reasons are: kept private ("call them"); told, not placed; marked closed; only a mailbox address; no point on the map; no longer listed.
- **Save as a trip** from the Trip Planner.
  - The place's ready-made journeys are offered as a start (release-3 note): **single-pass (P1)**.

**View a shared trip** at `/trip/?t=` (P1: `trip/index.php:2-5`; P2: `:2–3`, `:5`)
- It is the same page as the owner's, with no controls and none of the owner's details.
- **Passes differ on private places:**
  - P1 says places kept private are left out, citing `pages.md`.
  - P2 says that how `trip.php` strips owner details, and whether it leaves out stops flagged `private`, is not shown.

**Read a Personal Guide** at `/guide/personal.php?id=`
- Credited, with likes, comments and Report (`pages.md`). The page code is not shown.

**Arrange the trip on its page.** P2 lists this under Traveler; P1 lists it under Member. See Member below.

### Member (signed in)

**Your trips** at `/user/trips/` (Live, release 2)
- a list:
  - newest first: **single-pass (P1)**;
  - including the guest's one trip on the device: **single-pass (P2)**;
- rename;
- remove a stop with Undo. `remove` returns what Undo needs, `:220–228`: **single-pass (P2)**;
- Send to Maps;
- Delete, kept 30 days with Put back. `delete`, `:251–255`, is cited by P2.

**The trip page** at `/user/trips/trip.php?id=` (owner only; Live, release 3)
- days, up to 14;
- reorder:
  - by drag, ↑ ↓, or moving a stop to another day: P1;
  - `move`, `:461–474`: P2;
- how long to stay at each stop (`setStay`, `:509–517`);
- a start date and a day start time (`configure`, `:477–506`);
- times against hours or daylight:
  - daylight for outdoor places: P2;
  - in the first stop's time zone: P1;
- a map with "about" straight-line legs:
  - numbered pins: **single-pass (P1)**;
- Print.

**Share:** a view-only link. Turning it on again makes a new link, and turning it off kills the old one (`share`, `:347–353`).

**Publish as a Personal Guide** (P1: `:398–434`; P2: publish `:398–424`, withdraw `:427–434`)
- The member must be a contributor.
  - The detail "18+, a public profile, the guidelines": **single-pass (P1)**.
- The guide needs a title, an intro of at least 40 characters, and at least one stop that can be shown or resolves.
- An automated check may hold it.
- The member can Withdraw it and put it back.

**Limits:** 100 trips and 60 stops per trip.
- P1 cites `:19-22`. P2 cites `MAX_TRIPS` and `MAX_STOPS` at `:19–20`.

### Guest

- One trip is kept on the device (0174 §2). `js/tv-trips.js` is not shown.
- Signing in moves it into the account.
  - `Trips::claim` reports what was left out, and why.
  - P1 cites `:261`. P2 cites `:261–272`.

### Resident

- No resident-specific behaviour is shown in the code.
- Residents use the same entry points, including from Get Local cards.
- **Passes differ in emphasis:**
  - P1 adds that none is documented either.
  - P2 frames the use as "local errands and day plans".

### Business

- **No controls of its own.** It sees an "Added to trips" count in its insights (0174, release-4 note).
  - Written through `ListingActions` as `trip`. P1 cites `:316-327`; P2 cites `:321–324`.
- The insights page and its route are not shown.
- **Not counted:** its own people, the platform team and bots (`:320–322`).
- Who added it is never shown.
- The count stays out of its public page and stays in its insights. P1 cites `:333-339`; P2 gives no line.
- There is no paid or business-controlled way to appear in trips: **single-pass (P2)**.

### Staff

- Personal Guides are hidden or shown again in Admin → Journeys (`/admin/journeys.php`; `hideGuide` and `guidesForStaff`, `:437–448`).
- Place counts are in Admin → Insights (`/admin/insights.php`).
- The staff permission check is not inside `hideGuide`, and the caller's check is not shown: **single-pass (P2)**.

---

## (b) How it works underneath

**Pages**
- The shared view at `/trip/index.php:5` requires `user/trips/trip.php`.
- The split between the owner's view and the shared view happens inside that file, which is not shown.

**Library:** `app/lib/Trips.php`, one `final class Trips` (the class detail is P1).

**Kinds of stop** (`KINDS`, `:18`). Each is a kind plus a ref, and `key()` checks the ref (`:38–47`).
- `listing`: an `entities.id`;
- `lm`: a GNIS id;
- `rec`: a RIDB or Recreation.gov id;
- `place`: hub, geo_hub, cluster, town or `sub:N`;
- `osm`: `node|way|relation/N`, a place from our OSM copy.

**Resolved fresh every time** by `resolve()` (`:53–134`), so the day's rules apply to stored stops.
- **Suppressed listing** (`:74`): refused.
  - The text "No longer listed." and the `private` flag: **single-pass (P2)**.
- **Confidential (shelter) listing** (`ListingSafety::isConfidential`, `:81`): refused.
  - The text "kept private: call them" and the `private` flag: P2.
- **Marked closed** (`:82`; 0072, 0164 R12): refused.
- **Mailbox-only address** (`:83`): refused.
- **No coordinates or map point** (`:84`): refused.
  - P2 also cites `:98`, `:108` and `:117`.
- **Hours** are parsed at `:87`: **single-pass (P1)**.
- **Landmarks:** `Landmarks::get`, through `visibleSql()`. A story-only landmark becomes "Told, not placed" (P1: `:93-96`; P2: `:94–96`).
- **Outdoor places:** `RecBulk::get` (`:105`); **places:** `Guides::place` (`:115`). Both are **single-pass (P1)**.
- **OSM stops:** our `open_places` copy, credited "OpenStreetMap contributors" and linking out to openstreetmap.org (P1: `:122-129`; P2: `:121–129`).
- **Every point** passes `Nations::pin()`, which gives the nation's public place on a nation's land (`:59`).
- An unshowable stop keeps its place in the trip, greyed (`stops()`, `:231–239`): **single-pass (P2)**.

**Writing a trip**
- `create()` enforces the 100-trip cap (`:183-184`): **single-pass (P1)**.
- `add()` (`:194–217`):
  - checks ownership;
  - refuses unresolvable stops with the reason;
  - returns a duplicate as a negative id (P1 cites `:208`);
  - places the stop on the last day that has stops;
  - `touch()`es the trip;
  - counts the add.
  - Enforces the 60-stop limit: **single-pass (P2)**.
- `touch()` (`:302–309`) sets `tz` from the first stop that resolves, among the first five, through `JourneyPlan::timezone` (`:451–456`).

**Trip page operations**
- `move()` (`:461–474`).
- `configure()` (`:477–506`).
  - When days are cut, their stops go to the end of the last day, so nothing is dropped (`:496`): **single-pass (P1)**.
- `setStay()` (`:509–517`).
  - P1 cites `STAYS`/`STAY` defaults by kind at `:24-26`. P2 cites `STAYS` at `:26`.

**`plan()`**
- **Line range: passes differ.** P1 gives `:524-`. P2 gives `:524–546` in (b) but `:519–546` in (a).
- The day starts at `start_min`, defaulting to 540, which is 9:00 (`:531`).
- Legs come from `TravelLegs::miles/modeFor/estimate`, with `'about' => true` (`:542–545`).
- The time zone is the trip's `tz`, falling back to `America/Phoenix` (`:528–529`): **single-pass (P2)**.
- The rest of `plan()` is truncated and not shown.

**Delete**
- A soft delete through `deleted_at` (`:251–255`).
- Deletes older than 30 days are purged only inside `mine()` (`:160–161`), that is, when the owner next lists their trips.

**Sharing**
- The token is 16 random bytes in hex. P1 cites `:350`.
- `byToken()` requires the trip to be shared and not deleted (`:356–362`).

**Personal Guides**
- Kept in the `personal_guides` table.
  - Statuses published, held, hidden and withdrawn (`:366`): P1.
  - P2 cites `:366–448`.
- Publishing (`:398–424`) runs `Standing::guard`, then `Journeys::contributor`, then `ListingSafety::contentProblem`, which holds the guide with the reason.
- A guide hidden by staff can't be republished by its owner (`:414`, `:431`): **single-pass (P2)**.
- `guide()` serves only a published guide whose trip isn't deleted (`:384–390`): **single-pass (P2)**.

**Counts**
- Listings go through `ListingActions::count(…, 'trip')`. Other places go through `PlatformCounts::add('trip', kind:ref)` (`:316–327`).
- Bots, the team and a listing's own people are skipped.
- `addedThisMonth()` gives a place's monthly count, shown even at 0.
  - P1 cites `:330-341`.
  - P2 cites `:336–341` plus `SHOW_ZERO_TRIPS` at `:330`.

**Tables** (0174 §6 and its notes)
- `trips` adds `deleted_at`, `start_min` and `place_key`.
- `trip_stops` adds `id`, `added_at` and `stay`.
  - The split by release (release 2: `deleted_at`, `id`, `added_at`; release 3: `start_min`, `place_key`, `stay`): **single-pass (P2)**.
- `personal_guides` columns are inferred only from queries. The migration is not shown.
- `ready()` (`:30–35`) and `guidesReady()` (`:368–373`) check the tables lazily, so the code degrades gracefully until the migrations run.

**Map styling** (`brand.md` §