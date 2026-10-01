# ADR 0058: Public Profiles, Journey Management, and Adult Accounts

**Status:** Accepted (2026-10-01) by Jason. Amends `decisions/0056` (who contributes, the age gate) and builds on
`decisions/0047` (profiles and connections), `decisions/0050` (linking and data use), `decisions/0052` (@ tags),
and `decisions/0055` (roles).

## Context

Members need a public profile that works like the ones on other content and directory platforms: identity,
published work, reputation and links. It has to fit three things we already decided: we don't build profiles of
what people do (0050), we never expose anyone's location (0056 §4), and credibility on Traversence is earned. Two
smaller issues surfaced at the same time:
- the admin menu's Journeys item opened the admin's own journeys, when it should manage everyone's;
- the age gate started at 13.

## Decision

### 1. The public profile

| Part | Decision |
|---|---|
| **Avatar and cover** | Photos are processed like journey photos: resized and re-encoded, with all hidden data (GPS, device, time) stripped. They can be reported. |
| **Display name** | Chosen by the member (built: Dashboard → Profile). 2–40 characters. Names that suggest someone speaks for Traversence are reserved. |
| **@handle** | Unique. **`@` reaches people, listings, places and topics**, and the picker sorts the four. Profiles get a clean address (`/u/handle`). |
| **Badges** | For now, the standard set: **Member**, **Contributor** and **Steward**, each with **Since MM/YY**. How further badges are earned (e.g. Local Guide) is decided later. |
| **Bio** | Every member gets one (up to 300 characters, checked like journey text). The contributor bio moves into it. |
| **Published content** | Tabs for **Journeys** (and **Guides** for Traversence editorial) and **Photos** (from the member's published journeys, tagged by them). |
| **Activity stream** | **Opt-in, off by default.** By default a profile shows only what the person chose to publish. |
| **Recommendations, Personal Insights, Personal Guides** | Approved. **Recommendations** are public and positive. **Personal Insights** are private feedback that goes to the business. **Personal Guides** are published collections (e.g. "Jane's coffee stops in Show Low") of listings, places and journeys; Favorites stay private until someone publishes a guide. |
| **Star ratings** | **Out.** They don't fit the earn-your-credibility model. |
| **Reviews** | A review belongs to the content or listing it reviews and is shown there. Profiles don't collect a log of someone's reviews. |
| **Reputation** | Approved: shown as levels and badges, never a raw number. It is built on the contributor trust score (0056 §3). Reports a person files never count either way. |
| **Followers** | A **follower count** on public profiles, which the person can hide. A "following" list or count is never shown. |
| **Social links** | Website, Instagram, TikTok, X, Facebook, YouTube: plain outbound links. **We count exits per link and destination** (link, day, count), never who clicked. Posting out through their APIs comes later, as a tool the person switches on (0049). |

### 2. Who contributes, and Journey Management

- **Contributors are people and listings.** A member can post a journey as themselves, or as a listing whose team
  they're on, if their role there includes posting (Owner, Manager or General). A listing's journey shows the
  listing's name, links to the listing, and carries a **Business** badge. The person who posted it is recorded for
  accountability. Business journeys don't count toward Top Contributors, which ranks people.
- **Traversence's own voice belongs to staff roles, managed in the admin backend.** Two new staff roles are given in
  Staff & roles:
  - **Content Creator** writes Traversence articles and guides, and sends them to an editor.
  - **Editor** publishes them, edits Traversence pieces, and looks after contributors' journeys (hide with a reason,
    restore) and their reports.
  - **Managers** and **Owners** can do both.
- **Admin → Journeys** is Journey Management, not anyone's own journeys. It has five views:
  - Waiting for an editor;
  - Traversence;
  - Contributors (published, people and businesses);
  - Reported;
  - Hidden.

  Staff never edit a contributor's words; they hide (the author sees the reason) or restore. Contributors' drafts
  are private and never listed.
- **Your journeys** (the member side) lists only the member's own contributor journeys.

### 3. Tags: `@` and `#`

- `@` reaches things that have a page: a person, a listing, a place or a topic.
- **`#` is for free-form themes and moments** (`#monsoon`, `#nightskies`, `#route66`), decided 2026-10-01. A tag
  gets its own page of public journeys once it recurs across independent people, counted the way 0052 counts tags,
  so new topics emerge without staff creating them first. (Not built yet.)

### 4. Adult accounts: 18 and older

**Accounts are for adults, 18 or older** (amends 0056 §7).
- **Sign-up:** requires "I am 18 years old or older", checked on the server.
- **Existing accounts:** anyone who confirmed the earlier 13+ gate, or none, confirms 18+ once, before commenting or
  becoming a contributor.
- **What this removes:** no teen defaults are needed.

### 5. The map

Used everywhere a map opens: Let's Explore's Map, the directory's Map, and the map from search results.
- **Nothing floats over the map but Exit and the map's controls.** The bar that sat over the top of the map is gone.
- **The header search says what to look for.** While the map is open, pressing Enter in the search above it changes
  the pins (on Let's Explore it looks across everything, not only the chosen kind).
- **Controls** (right):
  - zoom in and out;
  - **My location**: moves the map on this device only, never saved or sent;
  - **Search this area**: highlighted when the map has moved;
  - **Map style**.
- **A panel from the map's left edge** (a sheet from the bottom on phones) holds:
  - the chosen place;
  - what's shown (Things to do / Places to eat / Places to stay / Everything on Let's Explore; Open now, Phone,
    Website, Favorites in the directory);
  - the distance;
  - "Search this area as I move the map";
  - the map style;
  - the legend.

  It stays open or closed as you left it.
- **Map styles:**
  - Streets and Light (OpenStreetMap);
  - Terrain and Satellite (USGS The National Map: public domain, United States coverage).

  Heavy traffic will need a tile provider agreement before launch: OpenStreetMap's own tiles are for light use.
- **Favorites** (businesses you linked or saved) show as a heart in a circle and are never grouped into a cluster.
- The directory's Map now opens this map with every result as a pin, instead of only the page of the list.

### 6. Navigation

- **Let's Explore menu, in order:**
  - **Discovery Dashboard**, which always returns to the default landing (North America);
  - **Plan a trip**;
  - Map;
  - Regions;
  - Key destinations;
  - National parks;
  - Things to do: Outdoors, Food & drink, Culture & heritage, Events (inactive until built), and Stories & guides.

  "Your places" and "Your journeys" live in the Dashboard.
- **The menu's current item** is a flat, slightly tinted row with its icon lit, like the top bar, with no box. The
  menu keeps its scroll position from page to page.
- **Journey management lives at `/user/journeys/`**, in the member Dashboard. Old `/journeys/` and
  `/journeys/compose.php` links redirect there. Published journeys are still read at `/journeys/view.php`.

### 7. Journey details

Decided 2026-10-01.
- **Where:**
  - an optional **"Where it happened" pin** for the whole journey, set by the author;
  - each photo can have its own pin.
- **When:** the "Was here" date gets an optional **time**. Each photo gets a **"Taken"** date and time.
- **The photo's own date and location:**
  - read **in the author's browser, before upload**, and offered as suggestions ("This photo says it was taken Aug 12
    near …. Use it?");
  - kept only if the author ticks them; the stored photo is stripped either way;
  - the first photo that has a location can also fill the journey's "Where it happened".
- **Rights:**
  - **Whose photo** ("Taken by me", or "Used with permission" with a required credit line, shown as "Photo: …");
  - **Reuse** for the journey: **Shown on Traversence only (the default)**, "Others may share it, with credit", or
    "Free to use";
  - ownership stays with the author, shown as "© name".
- **Co-authors:**
  - up to 5, found by display name (never email; private profiles aren't listed; blocked people can't be invited);
  - invited when the journey is saved;
  - an invitation waits on the invitee's Your journeys page (they confirm 18+ if they haven't);
  - shown on the journey and on the co-author's profile only once accepted;
  - the author can remove a co-author.
- **Mature themes (gated):**
  - readers see a notice and choose "Show the journey" (remembered for the visit);
  - the card shows a "Mature themes" panel instead of its photo;
  - the journey is kept out of the hero.
- **Caution:** an optional note (terrain, weather, closures) shown as a banner at the top of the journey.
- **Categories and magazine sections:** Outdoor recreation and Shopping join the list. Each category belongs to a
  section:

  | Section | Categories |
  |---|---|
  | Outdoors | Hiking, Outdoors, Outdoor recreation, Night skies |
  | Food & drink | Dining |
  | Culture & heritage | History, Culture |
  | Events | Events |
  | Shopping | Shopping |
  | Trips | Exploring, Road trip, Family |

  The Outdoors, Food & drink and Culture views show that section's journeys for the place.
- Migration: `2026-10-09_journey_details.sql`.

### 8. Phones and places

- **The phone tool bar:**
  - one **tools button** on the left, showing where you are (e.g. "☰ Plan a trip ▾"), opens the full tool list as a
    drop-down over the page, with labels and section headings;
  - the person's profile and role switcher sit at the far right.

  It replaces the swipeable row of icons. The admin tools work the same way, on every admin and crawler page and on
  the Dashboard as Admin:
  - the Crawler's pages are a group in the list;
  - counts (Review's) show on their row and on the tools button;
  - on the Dashboard, the button follows the section you switch to.
- **Auto-detect and ZIPs on Let's Explore:** a device location or a ZIP now opens the town area it belongs to (the
  cluster whose ZIPs include it, else the nearest within 40 miles). `/api/place.php` returns it as `place.cluster`.
- **Satellite** uses the USGS imagery with towns, roads and boundaries labelled.

### 9. The directory, and counting what visitors do

- **Layout:**
  - top row: the **place** (left: "📍 St. Johns ▾", which opens the place picker) and **List | Map** (right);
  - then the title and the count;
  - then only what's narrowing the results, each removable (×).
- **Filters live in the search:** its Quick picks are Near me, Open now, Has a phone, Has a website and Favorites. The
  page no longer has its own filter row or sort: a town or "near me" lists nearest first; otherwise best match.
- **Cards:**
  - edge to edge, 3px apart;
  - the business name is the link (brand brown, underlined);
  - Call, Directions, Website and Peek sit left, under the name, in the brand colors;
  - the **Link** icon sits in each card's corner.
- **Counting:** Call, Directions, Website and contact-page taps, from the directory, the map, the search's peek card
  and the listing page, are counted per listing, action and day. Never who: no user, device or address is stored, and
  one visit counts once per listing and action a day. A business sees the last 30 days in **Business Insights**.
  (Outbound social links on profiles will be counted the same way, by link and destination, when profiles are built.)
- Migration: `2026-10-10_listing_actions.sql`.

### 10. Place pages: everything searches for more

The pattern starts with public recreation places (`place/recreation.php`), for listing and place pages to follow.
- **No breadcrumbs.** The page says where it is ("About 4 mi from The Show Low District").
- **Tap to search:**
  - the **kind** (Campground): more campgrounds near the place;
  - the **agency** (USDA Forest Service): other places it runs near there;
  - the **name**: more about it;
  - each **activity pill** (Boating, Fishing …): more of it near the place.

  Each tap opens the search with those words ("Fishing near The Show Low District"), so it reaches listings,
  outdoors places, stories and groups.
- **Actions** under the name:
  - **See on map**: the full-screen map, this place chosen, with the outdoors around it within 10 mi;
  - **Get directions**;
  - **Call**;
  - **Reserve a campsite**, where Recreation.gov takes reservations.
- **The Link icon** in the card's corner: a recreation place can be linked, as `place:rec:<id>`.
- **Layout:** one card, edge to edge on phones, with sizes tightened.
- **Phones:** the role menu under the person's initial opens leftwards, so it stays on the screen.

### 11. The phone standard: every page, from now on

Decided 2026-10-01: space and visuals are standard practice on every phone layout. The rules are kept in
`CLAUDE.md` so every build applies them, and the page frame applies the spacing site-wide:
- **Spacing:** cards and panels edge to edge, 3px each side and 3px apart, with small corners; text keeps a small
  margin.
- **Card anatomy:**
  - the name is the link, top left;
  - the Link button sits in the top-right corner;
  - the actions are a column of round icons down the right edge, under the Link button;
  - the details sit on the left.
- **Icons:** flat brand icons, with the sage active state.
- **Pages:** no breadcrumbs, and labels search for more.
- **Filters** live in the search.
- **Counts:** outbound actions are counted.

### 12. Nothing here? Say so, and show where it is

When a search finds none of what was asked for in the place you're in, the search panel shows a "none here" box:
- **It says so:** "No 'pizza' in St. Johns yet."
- **It offers two ways to fill the gap:**
  - **Recommend one** opens Tell us, with the words and the place filled in;
  - **Add a listing**.
- **"Nearby, with 'pizza'":**
  - the nearest town areas that have it, within 200 mi (300 for an area or region), nearest first;
  - each town shows its distance and how many it has, with up to 3 places listed;
  - each place belongs to the nearest town area we cover, else its own town.
- **Tapping a place** opens the full-screen map around it (15 mi), with its pin chosen and the other matches
  around it.
- **Code:** the server adds `elsewhere` to `/api/search.php` (`UniversalSearch::elsewhere`).

### 13. Counting how the platform is used (Insights)

We count what's used, never who used it. Each count is a day, a metric, a key and two numbers: how many times, and
how many distinct visits. No user id, device, IP address or session is stored. A visit is told apart only inside
its own session, in memory on the server. Bots and headless browsers aren't counted.
- **Views:**
  - every section (Home, Let's Explore, Get Local, Plan a trip, Dashboard …);
  - every listing, journey, outdoor place, place page and Let's Explore view.
  - A reload doesn't count again.
- **Routes:** the move from one section to the next, and how visits arrive ("entry → Let's Explore").
- **Search:**
  - the words, cleaned: lower case, with no email addresses and no long numbers;
  - the place it was read in, and how many results it found;
  - searches that found nothing; "none here" by place;
  - what was opened from a search; Recommend one and Add a listing.
- **Listing actions:** calls, directions, website and contact (§9), and now **visits to the listing**, in Business
  Insights.
- **Words are shown only once 3 or more visits searched them.** Rarer words are deleted after 90 days.
- **Admin → Insights** (`/admin/insights.php`, 7, 30 or 90 days) shows:
  - tiles, and visits per day;
  - where people go, how they arrive, and the routes they take;
  - listing actions and Let's Explore views;
  - what people search for, the gaps, and none here by place;
  - **searches by place:** the search term, the place, the number of searches and the results found;
  - what they open from a search, and recommendations;
  - the most viewed listings, journeys, outdoor places and place pages.
- A misspelling with nothing found ("pozza") gets "Did you mean pizza?" first, not the none-here box (§12).
- **Code:**
  - `PlatformCounts`, `/api/count.php` and `js/out-count.js`;
  - pages say what they are with `<meta name="tv-sec">` and `<meta name="tv-item">` (`tv_count_item()`).
- **Migration:** `2026-10-11_platform_counts.sql`.

### 14. Pins at the middle of a ZIP area

A listing whose address the Census batch hasn't placed yet sits at the middle of its ZIP area. Example: Subway on
W Cleveland St showed in the middle of 85936, not on Cleveland St.
- When such a listing is opened on the map, the card says "Approximate pin: the middle of its ZIP area".
- Its street address goes to the Census single-address service (`/api/locate.php`, `Geocoder::locateOne`). When it
  matches, the point is saved and the pin and Directions move to the street.
- **Each listing is tried once**, then again after 90 days or when the address changes. A visit can ask at most
  20 times an hour.
- **Never tried:** confidential listings, PO boxes, mileposts and roads with no number.
- When the Census can't place it, the pin stays and says so.

### 15. Moving a pin that's in the wrong place

One page, `/listing/location.php?id=`, for everyone. It opens from three places:
- "Wrong spot? Fix the pin" on the map card;
- "Wrong spot on the map? Fix the pin" on the listing page;
- "Location and map pin" in the Dashboard's Business box and in the business portal.

What each person can do there:
- **Visitors:** drag the pin, tap the map, or use "I'm here now", then Send. Signing in isn't needed.
  - This is a suggestion (kind fix, field "Map point"), with an optional note.
  - A person checks it in Review and uses it with "Use this map point".
  - The device limit for suggestions applies.
  - "I'm here now" uses the person's location only for this pin; it isn't kept anywhere else.
- **The business** can drag the pin, and it's saved for everyone at once. The business means:
  - a listing_access owner or manager;
  - an org owner or manager role on the listing;
  - a verified owner.

  The business can also change the street address, town, state and ZIP:
  - the fields are locked, so crawls don't overwrite them;
  - the Census places the new address, else the pin sits at the new ZIP's middle (shown as approximate) until it's
    dragged.
- **Traversence staff** (is_admin or admin_access) can do the same as the business.
- **Confidential listings are never pinned** (decisions/0044). No one can move them.

A point a person set or the Census matched stays put. Crawls keep it, and the Census only replaces an empty point or
a ZIP's middle. Changes by staff and owners are logged (`role_log`). The search index is updated at once.

**Search, same day:** a word that names a category ("pizza") now also finds listings with the word in them, such as
Dittys Pizza & Pie under Restaurants. Before, the search panel found only the Pizza category, while the directory
found both. The panel's "See all" and Map now use the same words as the directory.

**Code:**
- `ListingLocation`, `/api/pin.php`, `listing/location.php`;
- `Geocoder::setPoint` accepts owner;
- `CrawlerIngest` keeps a point a person set.

### 16. Sports, and other broad words

- **Descriptions match from the start of a word.** "Sports" found the post office because its description says
  "Passports". This applies to the search panel and the directory, in strict and loose modes.
- **Sports words** (`synonyms.php`) find the places to play and to watch.
  - **"Sports":** athletic fields and stadiums, arenas, gyms, golf, public pools, rodeos, gun clubs, bowling,
    skating, skiing, and sports and recreation clubs.
  - **Football, basketball, volleyball, wrestling, baseball, softball, soccer and track:** athletic fields,
    stadiums and gyms. Most local games are at the high schools, so these also find high schools. Ball fields
    also find parks.
  - **Also:** gym, workout, fitness, swimming, pool, tennis, pickleball, rodeo, skate, ski, hunting, fishing and
    shooting.
- **A broad word stays words.** It's never read as one category ("sports" isn't Gun Clubs). Only a word with up to
  3 meanings is read as a category ("pub" is Bars).
- **A category read from a word** also finds the word in names (§15), but only as the whole word ("pub" isn't
  "Public").
- **Near me reads "near you"** in the place tag, the title, the count and "Missing a business near you?". It used
  to read "your precise location". The point is never shown.
- **Phones:** the section's tools menu ends with "Switch to": Let's Explore, Get Local, Social, Marketplace and
  Dashboard (not the section you're in). On wider screens the top menu does this.

### 17. Destination: towns first, then landmarks

The place picker's Destination lists matches in this order. Within each group, names that start with what was typed
come first.
1. **Anchor towns.** The town a town area is built around: "Show Low, AZ · Town · The Show Low District". Each town
   is listed once, and a hand-made area comes before an automatic one.
2. **Town areas.**
3. **Local names** ("Wide Ruins").
4. **Other towns inside one of our town areas.**
5. **Areas and regions.**
6. **Landmarks:**
   - outdoor places from Recreation.gov (recreation areas first);
   - listings in attraction categories: museums, monuments, national and state parks, landmarks, resorts and ski
     resorts, casinos, stadiums, zoos, amusement and theme parks.

   Choosing a landmark sets the town area around it, under the area's own name; "Lyman Lake State Park" sets
   St. Johns. If a landmark is outside our areas, it sets its own town ("Disneyland" would set Anaheim, CA, once
   it's listed). Hidden places and confidential listings are never offered.
7. **Towns elsewhere** come last, from the type-ahead ("Showell, MD").

**Code:** `/api/place.php?action=find` and `js/location-scope.js`.

## Consequences

- New columns: `journeys.entity_id` and `journeys.submitted_at`, and `age_confirmations.min_age`. Two new staff roles
  (`role_grants.role` adds editor and creator). Migration: `2026-10-08_journey_management.sql`.
- Still to build, in this order:
  1. **Profile identity:** avatar, cover, handle, bio for all, the standard badges, social links with exit counts,
     the Journeys/Photos/About tabs.
  2. **Personal Guides and the follower count.**
  3. **Reputation levels, Recommendations and Personal Insights.**
  4. **Outbound API sharing.**

## Progress

- **2026-10-01:**
  - built the 18+ age gate, the Editor and Content Creator roles, Admin → Journeys (Journey Management) and
    business journeys ("Post as" a listing);
  - the editor hand-off: a Content Creator's "Send to an editor", then an Editor's Publish.
  - Display name, contributor bio and public/private were already built (Dashboard → Profile, 0047 progress note).
  - **Writing and managing journeys is Dashboard work:** Your journeys, Become a contributor and the editor now sit in
    the member Dashboard's menu. Reading a published journey stays on Let's Explore.
  - Published journeys are woven in where readers already are:
    - the Let's Explore feed for their place;
    - the writer's public profile (Journeys);
    - each linked listing's page ("Journeys that mention…"), plus "From <business>" for a business's own journeys.
  - The Let's Explore menu no longer has a "Your journeys" item.
- **2026-10-01, later:**
  - built the map redesign (§5) and the navigation changes (§6);
  - moved journey management to `/user/journeys/`;
  - `#` decided.
- **2026-10-01, journey details (§7) and phones (§8):**
  - built;
  - fixed the phone search's place button squeezing the search field.
- **2026-10-01, directory (§9):** built the layout and the action counts.
- **2026-10-01, place pages (§10):** built for recreation places.
- **2026-10-01, later:**
  - **Auto-detect is applied when tapped** (it was a suggestion at the bottom of the place picker, easy to miss on a
    phone). It's set as the town area it finds, shown as "St. Johns · near you", and every page follows it.
  - **The place picker's Destination** also searches our own regions, areas, town areas and local names
    (`/api/place.php?action=find`), forgivingly: "St. Johns", "st johns", "Saint Johns" and "The Greater St. Johns
    Area" find each other.
  - **The search no longer reads a feature word as a town** when it's part of a name: "Lyman lake" is the lake, not
    Lake, MI. Such words (lake, river, park, springs, canyon …) count as a place only on their own or after "in",
    "near", "at" or "around".
  - **Listing pages follow §10:**
    - no breadcrumbs;
    - group › category and the name search for more;
    - the actions (Call, Directions, See on map, Website, Contact) are a column of round icons at the top left,
      with the address beside them;
    - the Link icon is in the corner.

    Recreation places and directory cards use the same icon column.
  - **Trip Planner:**
    - "Where would you like to go?" finds our places as you type (regions, areas, towns, local names, matched
      forgivingly), and the Region menu is gone;
    - its phone tool button and heading use the brand route icon: flat cream at rest, sage with a glow while the
      planner is open, like the top bar.
- **2026-10-01, Insights and pins (§13, §14):**
  - built platform counts, Admin → Insights (with searches by place: term, place, results found) and visits in
    Business Insights;
  - approximate pins are now placed at their street address when opened;
  - a misspelling gets "Did you mean" first.
- **2026-10-01, pins (§15):**
  - built the location editor for visitors (suggest), owners and staff (set, or change the address);
  - search reads a category word as the category or the word.
- **2026-10-01, sports and switching (§16):** built.
- **2026-10-01, Destination order and landmarks (§17):** built.
- **2026-10-01, Let's Explore on phones (§11):**
  - The hero runs edge to edge right under the tool bar, with Regions, Destinations and National Parks directly
    beneath it as one strip.
  - "Exploring <place>" has a small pencil to change the place (it replaces the "Change place" pill).
  - "Open the place page" is gone; the ☰ menu's "This place" opens the same page.

### Open items at the end of the 2026-10-01 session

**Decisions Jason owes:**
- **Search words (§13).** They're kept cleaned, shown only at 3+ visits, and rare ones are deleted after 90 days. Or
  switch to readings only: the place and the results found, no words.
- **Unverified claimants (§15).** Should they move pins at once? Today only verified owners, listing_access
  owners and managers, and org owners and managers can; unverified claimants suggest, like visitors.

**To check on the live site (the sandbox can't reach the Census or show real area names):**
- The Subway pin on W Cleveland St moves to the street when opened on the map (§14).
- An owner or staff address change finds the new address (§15).
- Destination order with real area names: type "Show" (§17).
- "Sports" no longer lists the post office; "pizza" lists Dittys and Fire Stone (§15, §16).

**Data, not code:**
- Grand Canyon and Disneyland appear in Destination once their Recreation.gov records or listings are loaded (§17).

**Still to build (Consequences):** profile identity, Personal Guides and follower count, reputation levels,
Recommendations and Personal Insights, outbound API sharing.

**Housekeeping:**
- The `claude/magical-clarke-cn5pqi` branch is far ahead of `main`. Merge it when ready.
- Revoke the GitHub token exposed in the OneDrive repo-local config, if not done.

