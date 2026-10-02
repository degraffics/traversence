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

### 18. The Explore hero, on Let's Explore and every place page

**One name, said once.** The hero's title is **Explore: <place>** with a pencil to change the place. It replaces:
- "Let's Explore: <place>";
- the "Exploring <place>" line and its pills;
- "around <place>" in the journeys heading;
- on place pages, the breadcrumbs, the "Regional hub / Geo-hub / Town cluster" label and the separate title.

**One component, `includes/explore-hero.php`,** used by:
- Let's Explore (the overview, and Regions, Destinations and National Parks);
- every place page (`/hub/`: region, area and town area).

**What the hero shows:**
- **Photos:** from published journeys tagged with that place or a place inside it (`Journeys::hero` by scope key).
  A town area shows its own journeys, an area also its towns', a region all of them. With none, it shows the
  brand's art and "Share a journey".
- **"in <region> · <area>":** on a place page, each one a link up.
- **The Link button.**
- **Arrows** when there's more than one photo.

**The pencil** opens the place picker. On a place page, choosing a place opens that place's page; on Let's Explore,
the page follows the place.

**Under the hero:** first the Regions / Destinations / National Parks strip, with the current page marked in sage.
Then a carousel of cards: two side by side on a phone, three or four on wider screens, swiped left and right
(arrows on wider screens). Whole cards snap into place.
- **The Discovery Dashboard:** "Explore: <place>", with the regions carousel under it.
- **Regions, Destinations and National Parks:** each is titled by the page ("Explore: Regions"), with a line about it.
  The hero shows journey photos from everywhere, since these pages cover the continent, and the page's own cards
  sit in the carousel.

Each card has a journey photo (else the brand's art, by name), an icon, the name, and a line about it. It also
shows:
- **Regions:** areas, town areas and journeys, with a Pilot or Coming soon badge. Coming-soon regions show quieter,
  without a link.
- **Destinations:** the region, town areas, listings, journeys and the first towns.
- **National Parks:** Recreation.gov National Park Service units plus the parks, monuments, landmarks and state
  parks we list, so it isn't empty before the Recreation.gov load.

**Phones:** the hero and the strip run edge to edge under the tool bar. Carousel cards are 3px from the edges and
3px apart. With no photos yet, the continent pages say "No photos of North America yet".

**Update (ADR 0059):** Destinations became **Experiences**. The strip reads Regions / Experiences / National Parks.

### 19. Search follows the section (the Chameleon Filter), and a cleaner panel

- **On Let's Explore** (`track=explore`, from the page's `tv-sec`) search puts the place's own content first:
  - published journeys that mention the words, in the place and the places inside it;
  - then stories and guides, outdoors, groups and topics;
  - businesses follow as connections.

  A business whose name matches isn't made the top answer there. "None here" shows only when nothing at all is
  found, not just no businesses. On Get Local, businesses lead as before (brand.md §5).
- **The panel's top is one row:** the place (and any category or time it read) on the left, each with ×, and
  **☆ Save** on the right. "Understood as", the Distance menu and the Map button are gone. The directory has its own
  List/Map, and Let's Explore has its map.
- **Destination** lists only names where a word starts with what was typed: "Sport" no longer offers Breesport or
  Bucksport.

**Decisions recorded:**
- **Search words (§13):** kept as built.
- **Unverified claimants (§15):** their pin goes to Review like a visitor's. An admin's "Use this map point" applies it
  at once.

### 20. The content page standard: header, Link, Engage bar, comments

Every content page follows one layout. The pages are listings, outdoor places, experiences, place pages and
journeys:
1. **A header** that says what it is:
   - its kind or category, which searches for more like it;
   - its title, left aligned;
   - its place;
   - the **Link button** in the top-right corner.

   No breadcrumbs. Journeys and experiences now have the Link button too (`place:jr:<id>`, `place:exp:<id>`).
2. **The Engage bar** right under the header:
   - **views:** visits that saw it, from the platform's counts, never who;
   - **likes:** how many, never who;
   - **comments:** the count, linking to them;
   - **Share:** the phone's share sheet, else the link is copied;
   - **Report**, where the item itself can be reported (experiences).

   Listings have no Like, because the Link button is how you keep a business.
3. The page's own content.
4. **Comments** at the end:
   - public; a signed-in member who is 18 or older can comment, up to 30 an hour;
   - the writer or staff can remove one;
   - anyone signed in can report one (Admin → Reports).
   - On listings, comments are how people review a business, without stars (§ "Star ratings out").

Journeys keep their own likes and comments. Code: `api/lib/Engage.php`, `/api/engage.php`,
`includes/engage-ui.php`, `js/engage.js`; Reports gained `content_comment` and `experience`. Migration:
`2026-10-14_engage.sql` (`content_comments`, `content_likes`).

Also fixed: the listing header's category and name were centered and wrapped oddly. They're buttons, and now align
left.

### 21. The hero on listings and profiles; their own photos and video; maps open with the panel

- **Every page with a hero uses one template** (`includes/explore-hero.php`): Let's Explore, place pages, listings and
  profiles.
  - **Listings and profiles** title it with the name alone (no "Explore:", no pencil). A listing shows its category
    and town under the name.
  - The name is said once: the listing's details card and the profile card no longer repeat it.
- **Listings and profiles choose their own hero photos and videos.** A **Photos & video** button on the hero opens a
  panel where you can add photos (several at once), add a YouTube or Vimeo link, put one first, or remove one. Up to
  8.
  - **Who:** a listing's owner or managers, or Traversence staff (`ListingLocation::role`); on a profile, the person.
  - **Photos** are resized and stored as new files; nothing from the original is kept, so no location metadata.
    Videos are embedded with privacy-enhanced YouTube or Vimeo players.
  - **Privacy:** a private profile shows its photos only to connections. Confidential listings have none.
  - **With none yet,** the hero shows the brand's art (and, to the owner, a prompt).
  - **Code:** `api/lib/HeroMedia.php`, `/api/hero-media.php`, `js/hero-media.js`; migration
    `2026-10-15_hero_media.sql`.
- **A page's title is the page, not a search.** The listing's name and the outdoor place's name no longer open the
  search; their category, agency and activity pills still do (§10).
- **The hero sits flush under the tool bar on every page.** On the live site, the page wrapper's Tailwind spacing
  pushed it down; it's now held at the top.
- **Maps opened as their own view start with the info panel open:** Let's Explore's Map, the directory's Map, "See on
  map" from a listing or an outdoor place. On a phone that's the bottom sheet (`TvMap.open({panel: true})`).

### 22. Actions over the hero, About as the add-on area, and search that widens on Let's Explore

- **The page's actions sit on the hero's foot** (a dark band along its bottom):
  - **Place pages:** views, likes, comments and Share.
  - **Listings:** views, comments, Share, Save (private, to your Address Book) and **Claim** for an unclaimed
    listing, or **Manage** for its owner.
  - **The owner's Photos & video button** is on the right.
  - The side card keeps the guest questions, "Are you the owner? Claim it" and "Closed, moved or listed twice?
    Tell us".
- **The Link button and the pencil no longer overlap.** The title keeps clear of the Link button, and the pencil sits
  right after the title.
- **A listing's About is the area that grows with its plan** (`entities.plan_tier`, commercial.md §1):
  - **Every listing:** the description, with "Read more" when it's long, and Hours.
  - **Core and up:** "What they offer".
  - **Strategic and up:** "Owner spotlight" and a photo gallery from its hero photos.

  Each add-on is a row that opens. The owner sees what the next plan would add. Add-ons show only when there's
  something in them.
- **Search on Let's Explore widens when the place has nothing real.** If only category links match in the place,
  the search looks across the open regions and says "Nothing in <place> yet. These are from across the open
  regions." Real places come before categories.
- **Places to visit rank first:** among businesses, Let's Explore puts parks, monuments, landmarks, museums,
  historic sites, visitor centers and observatories first.
- **Landscape words** (`synonyms.php`) find where they are: badlands, canyon, mesa, hoodoo, petroglyph and rock art,
  ruins and cliff dwellings, fossil and dinosaur, waterfall, hot spring, dark sky and stargazing, overlook, scenic
  drive. For example, "badlands" from St. Johns finds Painted Desert Inn, the Painted Desert Visitor Center, the
  Petrified Forest museum and the national park.

### 23. Outdoor place pages on the hero; a readable hero; the map panel folds; × on a search place

- **Outdoor place pages** (`place/recreation.php`) open with the hero, like listings:
  - the name, said once;
  - under it, what it is and where ("Campground · About 10 mi from St. Johns");
  - views, likes, comments and Share on the hero's foot;
  - the card below keeps the kind and agency (each searches for more), the description, the activities, the
    contact details and the round action icons.
- **"Near" means near.** A town area more than 40 miles away isn't named as near. The page says the place's own town
  instead ("Near Nambe, NM"), not "About 120 mi from Grants".
- **The hero's title is always readable:**
  - the line under the title sits in the same block, so a long name pushes it down instead of covering it;
  - a long name is cut at three lines;
  - the top of the photo is darker, so the title reads over a bright sky.
- **The hero's actions fit one row on a phone:**
  - views show as an eye with the number;
  - comments and likes keep their numbers, in bold, light on the dark band;
  - Share and the owner's Photos & video become round icons (their titles say what they are);
  - Report moves off the hero on a phone.
- **The map panel folds instead of closing** (phones). Its corner button is a chevron: it folds the panel down to
  its title line, and tapping the title or the chevron brings it back. Tapping a pin unfolds it to show the place.
  The map's controls show while it's folded.
- **The map panel says the count once:** the title says what's shown ("Places around Empty Pockets Saloon ·
  “bars”"), and the line under it says how many are on the map.
- **× on a search's place searches everywhere.** Removing the place chip ("Grants, NM") drops the place words from
  the search and doesn't fall back to the page's own place. "Health & Medical near Grants" without Grants is Health &
  Medical everywhere.
- **"Did you mean" leaves place and category searches alone.** When a search read a place or a category, the words
  aren't a typo, so it doesn't offer a respelling ("health medical neal grounds").

### 24. Natural landmarks: badlands, arches, canyons and more

Search could only find natural places that were also a business or a Recreation.gov site. "Badlands" found nothing
real. Natural landmarks are now their own source.

- **Where they come from.** Once a month, the worker (`workers/crawler`) loads them:
  - **USGS Geographic Names (GNIS):** every named feature in the pilot states, keeping the natural kinds (areas,
    arches, rock pillars, cliffs, craters, falls, summits, canyons, springs, lakes …).
  - **Census reservation boundaries (AIANNH):** marks each feature inside a tribal nation's lands ("On Navajo Nation
    land").
  - **Wikidata:** the feature's Wikipedia article and photo, by its GNIS id.
  - **Wikipedia:** the article's first sentences.
  - **Wikimedia Commons:** the photo, but only if it's freely licensed (CC0, public domain, CC BY, CC BY-SA). It's
    credited on the page with its author, license and a link.
- **Only notable ones are kept:**
  - one with a Wikipedia article or a photo;
  - one whose name has a landscape word: badlands, natural bridge, falls, hot springs, dunes, crater, volcano, hoodoos,
    gorge, slot or box canyon, lava, petrified, cave …;
  - an arch, a rock pillar, a crater or falls;
  - a rock formation's name (rock, spire, pinnacle, needle, chimney, castle …), but only on a summit, cliff or area,
    so "Rock Spring" isn't a landmark;
  - not Wikidata's description alone, since it describes nearly every feature ("summit in Arizona");
  - not "(historical)" names.

  In the sandbox's test run, 14,557 Arizona and 12,236 New Mexico features gave 900 landmarks.
- **The site decides what shows, not the worker** (`Landmarks::decide`):
  - **On a sovereign nation's land, it waits for a person.** An Editor approves it only after ticking "The nation
    welcomes visitors here", from the nation's own visitor information. The page then says so, and tells visitors to
    follow the nation's rules and permits.
  - **A name that may mark a sacred, burial or archaeological site waits for a person:** shrine, burial, grave,
    cemetery, kiva, ruin, petroglyph, pictograph, rock art, cliff dwelling, ceremonial, medicine wheel.
  - **Everything else shows.**
  - A person's approve or hide is kept across monthly reloads; Undo puts one back.
  - **The boundary check is deliberately cautious.** Bisti Badlands' point falls on a Navajo trust parcel in the
    checkerboard, so it waits for a person, who can approve it.
- **Admin → Landmarks** (`admin/landmarks.php`):
  - tabs: Waiting, Shown, Approved, Hidden;
  - find by name;
  - each has a photo or the brand art, its kind, nation or nearest town, why it's waiting, and links to the map and
    Wikipedia;
  - Approve (an Editor), Hide (with a reason), Undo.
- **Its page** (`/place/landmark.php?id=<GNIS id>`), on the content standard:
  - the hero with its photo and credit; the name; "kind · near town" or "On <nation> land";
  - views, likes, comments and Share on the hero;
  - the card: the kind and county (each searches for more), the Wikipedia summary, the nation's note, "Leave it as you
    found it", nearest town and county, the sources and licenses, and "Something wrong, or shouldn't be shown? Tell
    us";
  - the round icons: See on map, Directions, Wikipedia;
  - comments;
  - the Link button (`place:lm:<id>`) and counts (`landmark:<id>`).
- **The kind comes from the end of the name** ("Bisti Badlands" is Badlands, "Waterfall Canyon" a canyon), else from
  its GNIS class.
- **"Near" is the nearest ZIP's town within 40 miles.** That ZIP also puts it in the right place for search.
- **In search:**
  - indexed as Outdoors (ids `g` plus the GNIS id), after Recreation.gov places;
  - ones with a photo or article rank first;
  - on the map they're pins with a peek card.
- **Migration:** `2026-10-16_landmarks.sql`. After it runs, the worker loads them on its next run, then rebuild the
  search index's Outdoors (Admin → Search index). The worker setting `LANDMARKS=off` stops the load.
- **Also fixed:** the phone menu now opens over the tool bar, and signed in it shows My account, not Login.
- **Still to build:**
  - landmarks on place pages (an "Outdoors & public lands" companion);
  - a person adding a landmark the data doesn't have (like the Nambe Badlands);
  - landmarks as a source for experiences.

### 25. Search doesn't pick a far-off town on its own; staff add landmarks

- **A town outside our areas is the place only when you clearly mean it.** Search used to read the last word of
  anything typed as a town anywhere in the US:
  - "Four corn", while typing, became Corn, Oklahoma;
  - "Four corners" became Four Corners, Wyoming.

  Now a town outside our town areas is read as the place only when:
  - it's the whole search, it comes after "in", "near", "at" or "around", or its state is given;
  - and no outdoor place or landmark has that name ("Four Corners" is the monument).

  Towns in our areas read as before ("pizza show low"), and the pilot states' town comes first when several states have
  one.
- **Staff add the landmarks the data doesn't have:** Admin → Landmarks → "+ Add a landmark the data doesn't have".
  - **The form asks for:**
    - name, kind (monument, park, viewpoint, badlands …), state, county, latitude and longitude;
    - the nation if it's on tribal land, with "The nation welcomes visitors here";
    - what it's like, in their own words;
    - optionally, a Wikipedia title and a Wikimedia Commons photo with its credit and free license.
  - **An Editor's shows at once,** unless it's on a nation's land without the tick. A Content Creator's waits for an
    Editor.
  - The monthly reload never removes one added by hand (ids from 900,000,000; `file = 'manual'`).
  - Its page says "Added by Traversence".
- **The Destination picker lists landmarks** (after towns, areas and regions): "Four corners" offers Four Corners
  Monument, near Teec Nos Pos, AZ.
- **"Where are you looking?" fills the phone's screen.** Destination's matches sit in the page's flow, so every match
  shows (they used to be cut off at the panel's edge), with bigger rows to tap.
- **Not in the USGS data:** GNIS's DomesticNames file holds natural features only, so monuments, parks and viewpoints
  are added by hand, or come later from another source.

### 26. On a nation's land: only its public places, its rules, and no pins elsewhere

Decided by Jason on 2026-10-02. The working draft is the Claude Doc "Sovereign lands: what Traversence shows, and who
decides".

- **Only the nation's public places.** On a nation's land Traversence shows only the places the nation itself lists for
  visitors: its parks, museums, events and enterprises.
- **Opening a place needs the nation's own word.** Approving a landmark on a nation's land (or adding one by hand) needs
  a link to the nation's own page that lists it for visitors. A county's, state's or travel site's page isn't enough.
  The link is kept with the place and shown on its page.
- **Closed by the nation.** "Closed by the nation" needs who at the nation asked, and how. The place stops showing at
  once. No reload, Undo or staff approval reopens it: only "Reopen at the nation's request", which puts it back to
  Waiting for an Editor.
- **The nations' log** (`nation_decision_log`) records every opening, closing and reopening on a nation's land: who,
  when, the evidence or the nation's contact. It's written once and never edited. Admin → Landmarks shows the last three
  entries under each place.
- **Admin → Nations** (`admin/nations.php`) lists the 47 nations whose Census areas touch our regions. For each:
  - its name as it uses it, and its Census areas (`match_names`);
  - its website and visitor page;
  - its tourism or parks office;
  - the rules visitors most often miss: permits and licenses, fees, guides, photography, drones, alcohol, time, and
    anything else.

  The rules show on its places' pages only after someone ticks "Checked against the nation's own site". That needs
  its website or visitor page, and any later save without the tick hides them again. Saving needs an Editor.
- **Pages:** a place on a nation's land says "On <the nation's own name> land". It shows the nation's checked rules with
  a link to its visitor information, or, until they're checked, a pointer to the nation's own visitor information.
  Search results and map cards use the nation's own name too.
- **Journey and photo pins:** a pin on a nation's land is kept only at a public place within half a mile, and is moved
  onto it. Public places are a landmark the nation opened, a public listing (never a confidential one) and a public
  outdoor place. Anywhere else on the nation's land, the pin is dropped and the writer is told why ("Name the place in
  your words instead"). Pins posted earlier follow the same rule when shown.
- **Boundaries:** the worker sends the Census reservation and trust-land boundaries, simplified to about 50 m (21,961
  points for the 70 areas in Arizona and New Mexico), with the monthly landmarks load (`nation_shapes`). A pin check
  takes a few milliseconds.
- **Not crawled:** nations' websites aren't crawler sources. They're the evidence for opening a place, kept per nation,
  and are read by a person.
- **Migration:** `2026-10-17_nations.sql` (after `2026-10-16_landmarks.sql`). It seeds the 47 nations' names and Census
  areas. Contacts and rules stay empty until someone fills them in from each nation's own site.
- **Still to build:**
  - boundary checks on Recreation.gov places, experiences and listings;
  - nation accounts, so a nation's own office can open and close its places;
  - notices to a nation's office when one of its places opens.

### 27. No review queue until the nation tools exist; on a nation's land, only its public places and businesses

Decided by Jason on 2026-10-02, building on §26. (The first build of this read it as "nothing on a nation's land
shows". Jason corrected it the same day; this is the rule.)

- **On a nation's land, only its public places and public businesses show:**
  - places opened with the nation's own page as evidence (Four Corners Monument);
  - public business listings (never one with a confidential address).
- **Waiting and closed places never show.** That covers pages (they answer "not found"), search, map pins, the
  Destination picker, and the places a journey pin may snap to.
- **No review queue until the nations have their own tools (nation accounts):**
  - Admin → Landmarks has no Waiting tab and no Approve for crawled items.
  - What was waiting is kept, unused, marked "Not shown: kept, unused, until the nation tools": places on a nation's
    land, and names that may mark a sacred site. In the sandbox that's 270.
  - Reopening a closed place waits for the nation tools too.
- **An Editor opens a nation's public place by adding it by hand,** with the link to the nation's own page that lists
  it. It shows at once. Without that link, or for a Content Creator, it's refused.
- **Staff can still hide anything,** and close a place when a nation asks.
- **Admin → Landmarks has three tabs, by who decided:**
  - **Public:** what shows, whether from the data or added by hand. Tags say "Added by hand" and "Opened by a person".
  - **Hidden by us:** staff hid it, with a reason.
  - **Closed by a nation:** the nation asked.

  One line under the tabs counts what's kept, unused, until the nation tools.
- **The data stays;** nothing is deleted.
- **One switch:** `Landmarks::NATION_TOOLS` (false). Every reader of landmarks uses `Landmarks::visibleSql()` /
  `visible()` (shown or approved). Turning the switch on brings back the Waiting tab and approving with evidence.
- **Not yet covered:** boundary checks on Recreation.gov places and experiences (§26 "Still to build").

### 28. A clean hero: credit bottom left, buttons bottom right

This matches Jason's concept (2026-09-29): the title top left, the Link button top right, and the photo left clear.

- **The photo's credit** ("Photo: Autopilot, CC BY-SA 3.0", or a journey's caption and who shared it) is a small cream
  card in the bottom-left corner, as in the concept image (no wider than about 44% of the hero).
- **The card and the button pills are see-through:** a light glass tint with a thin light border and a blur behind,
  with light words. Claim keeps a gold edge.
- **"No photos yet"** shrinks to a line and a small "Share a journey" button in the same corner.
- **The page's buttons** (views, likes, comments, Share, Save, Claim, Photos & video) sit in the bottom-right corner.
  They wrap upward on the right when there are many (a listing).
- **No credit card (no photo yet): the buttons get the whole row.** On a phone, Save is a round icon like Share, so a
  listing's buttons fit one line with Claim.
- **A soft shade** along the bottom keeps the words and buttons readable over a bright photo. The full-width band is
  gone.
- **A tap on a hero photo opens it in a lightbox:**
  - full screen, at full size, with its credit or caption under it, over a see-through dark backdrop (the page
    shows faintly behind, softly blurred);
  - with several photos, "2 of 5" plus arrows, swiping and the arrow keys;
  - closes with ×, Esc or a tap outside the photo;
  - the page's social bar comes along at the bottom (views, Like, Comments, Share, Report): a like made there shows on
    the page, and Comments closes the lightbox and goes to them.

  Photos that link to a journey (Let's Explore) still open the journey. Videos play in place. Buttons and links on
  the hero never open it.
- **On landmark and outdoor place cards, the action icons** (map, directions, call, Wikipedia) are pinned in a column
  under the Link button: the same 30px, the same right edge, an even gap. They used to start with the text and crowd
  the Link button.
- **Code:** `includes/explore-hero.php` (CSS only), so every hero follows: places, listings, profiles, landmarks,
  outdoor places, Let's Explore.

### 29. The journey card, as in the concept

Journey cards follow Jason's concept image (2026-09-29), wherever they show: Let's Explore and its "More", listings,
profiles, experiences.

- **Title bar:** the kind and the title ("Outdoor recreation | Petrified Forest at dusk"), with the **Link button** in
  its corner (`place:jr:<id>`).
- **On the photo:** "Posted <date>" at the top left, the read time in a pill with a clock at the top right, and the
  play button when there's a video. The photo sits inset in the card's frame.
- **The foot:**
  - the author's initial in a ringed circle, their name in capitals and the place;
  - the Traversence, Contributor or Business label (moved here from the title bar);
  - gold pills for views and comments, and the likes with a heart.

  These are the brand's flat icons, not emoji.
- **The Link button sits beside the card's link, never inside it:** a wrap (`.jr-wrap`, `data-link-host`) holds both.
  `js/link-button.js` now also stops a tap from following any link around it, and loads only once per page.
- **Code:** `includes/journey-ui.php` (`tv_journey_card`), the same card in Let's Explore's "More" (`discovery/index.php`),
  and `js/link-button.js`.

### 30. "Share a photo" on places, and directions on our own map (planned)

**Share a photo (built).** The prompt went missing when outdoor place and landmark pages moved onto the hero. It's
back, and better:
- **A place with no photo** shows the hero's corner card: "No photos of <place> yet. Be the first to share one", with
  **Share a photo**.
- **The button opens the journey editor** with the place, its town area and its pin filled in. The editor reads
  `?about=`, `key=` and `pin=`; the nation's pin rule (§26) still applies.
- **That place's hero shows the photos** from published journeys pinned within half a mile of it, or with a photo
  tagged there: credited, most liked first, mature ones never (`Journeys::heroNear`, the hero's `near` option).
- A landmark with a Wikimedia Commons photo keeps that photo first.
- **Listings get it too.** A listing's hero shows the owner's own photos first, then photos from published journeys that
  tag it (`Journeys::heroTagged`, the hero's `tagged` option), credited and linking to the journey. With none at all, it
  shows the card with **Share a photo**, which opens the editor with the business tagged (`biz=`). A confidential
  listing gets neither.
- **Landmarks likewise:** the Commons photo first, then journeys shared there.

**Directions on our own map (decided, waiting for a key).** Use OpenRouteService (OpenStreetMap routing: a free key, about
2,000 routes a day, driving and hiking). The plan:
- Directions opens our full-screen map with its panel. The route from your location (or a place typed) is drawn on
  our map, with distance, time and the turns.
- Warnings with the route:
  - unpaved or seasonal roads near the end;
  - "On <nation> land: stay on public roads, follow the nation's rules";
  - little or no cell service.
- "Navigate in Google Maps / Apple Maps" stays for live driving. We don't build voice navigation.
- The site calls OpenRouteService itself (`api/route.php`), so the key stays in the site's `.env`
  (`ORS_API_KEY`), never in the page. Recent routes are remembered to stay under the limit.
- Counting stays counts only. No route to anything not public (§27).
- Until the key is in `.env`, Directions keeps opening Google Maps.

### 31. Full words, a search box on phones, and finding your way back

- **Crawled names in full words** (`api/lib/NameText.php`). Government and directory data shorten words; visitors read
  them in full:
  - "Painted Desert Visitor Ctr" becomes Visitor Center;
  - "Petrified Forest Natl Pk" becomes National Park;
  - also Mtn, Hwy, Svc, Assn, Dept, Hosp, Univ, Mgmt, Intl, Otfttrs, Rec, Hist and more, and "Mt" before a name
    (Mount).

  Whole words only. Only what's shown changes; the stored data stays as crawled, and links keep the stored town. A
  listing its owner has claimed keeps the owner's own name, exactly. It applies to:
  - the listing page;
  - the directory and its map pins;
  - search results and peek cards;
  - Destination.

  Search finds them by the full words once the index is rebuilt (Admin → Search index → Build the index).
- **Landmark photos:** visitors' journey photos first, then the Wikimedia photo (the hero's `own_last` option). With no
  journeys, the Wikimedia photo is the hero.
- **A search box on phones.** The top bar shows a box ("Search places, listings…") between the logo and the bell,
  instead of a lone magnifier. A tap opens the full search panel.
- **Finding your way back (phones): tried, then taken out.** A Back chip ("‹ <the page you came from>") at the start of
  the tool bar and a "Recently viewed" list in ☰ were built, then reverted the same day: they didn't work as Jason
  wanted. The phone's own Back is the way back for now. Waiting on Jason's thoughts before trying again.

### 32. Parks & Landmarks; search results open the page

- **Parks & Landmarks.** The "National Parks" tab under the Explore hero is now **Parks & Landmarks** (still
  `?view=parks`), and so is its entry in the Let's Explore tools.
  - **Parks first:** the hero carousel keeps the National Park Service units from Recreation.gov and the park,
    monument and landmark listings.
  - **Then landmarks:**
    - with a place chosen, "Landmarks near <place>": `Landmarks::near()` within 40 miles, ones with a photo or an
      article first;
    - with no place, "Natural landmarks": `Landmarks::top()`, the best-known ones with a photo.
  - Each card links to `/place/landmark.php?id=` and shows its photo (credited, Wikimedia Commons) or the brand's art,
    its kind and the town it's near, and with a place, how far.
  - Only what people can see (`Landmarks::visibleSql()`): never waiting or closed, and on a nation's land only what the
    nation opened (§26, §27).
  - National Park Service data (NPS API) comes later, once there's a key in Railway.
- **Search results open the page.** Tapping a listing in the search panel opened its peek card, not its page. Now:
  - the row opens the page;
  - only the row's **Peek** button opens the peek card (a pill; Enter or Space works on it too);
  - Enter in the search box opens the first result's page.
- **Phone width fix:** the place picker kept its laid-out size when hidden if Tailwind's `hidden` hadn't loaded, which
  could make the page scroll sideways. It now has its own `#location-modal.hidden{display:none}` and `border-box`.

### 33. Wider screens: your account at the person icon; the hero as on a phone

- **Your account at the top bar.** On wider screens the name-and-role block at the top of the toolbar is gone. It now
  lives at the top bar's person icon:
  - a **click** on the icon opens your dashboard;
  - **hovering** it (or ArrowDown from the keyboard) opens the menu: who you are and the role you're acting as, the
    roles you can switch to, Your profile, Dashboard and Sign out.
  - Phones are unchanged: your initial at the right of the tool bar opens the same menu.
  - `includes/header.php` renders the menu once (`tv_context_switcher(false)`); the toolbar's button uses it on phones.
- **The hero, as on a phone.** On wider screens the hero runs flush with the top bar and the toolbar, with square
  corners, and its tabs run full width under it. Its height fits the screen (300 to 480px, half the window) instead
  of a wide 21:9 frame that filled the screen on a big monitor.
- **No photos, a smaller hero.** With no photos or video at all, the hero shrinks to a band (12.5rem; 14rem on a
  phone) that holds the title, the line under it, "be the first to share one" and the buttons.

### 34. No-photo hero as small as it can be; Claim in the corner; one camera

- **No photos, a short hero (phone and desktop).** The band is now as tall as the title and the line under it, plus
  the buttons' row: about 135px on a phone. It replaces §33's fixed 12.5rem.
- **Claim (or Manage, for the owner)** sits in the hero's top-right corner (the hero's `corner` option), not in the
  button row.
- **"Be the first" is a camera button,** an icon at the end of the button row like Share, not a text box. Its title
  says "No photos of <place> yet. Be the first: share a photo or video". It shows on photo heroes too.
- **One camera per person.** Two ways to add photos looked like two processes. They are two on purpose, and now each
  person sees only one:
  - whoever runs the page (the owner, or staff) gets **Photos & video**: they choose the page's own photos and video,
    which lead the hero;
  - everyone else gets the **camera button**: it starts a journey about the place, already filled in, and once
    published, its photos follow the owner's in the hero, credited to the person. A visitor's photo goes through a
    journey so it has an author, a credit, comments and reports, and follows a nation's rules on its land.
  - A listing with a confidential address, and profiles, show no camera for visitors.

### 35. The search panel and the account menu, as in the mockups

- **The search bar (wider screens):** a cream field, then a gold block with two round buttons: **Search** and the
  **place pin**. The panel hangs from the bar, wider than it, with a gold edge.
- **The search panel:**
  - **head row:** "Location: <place>" with ✎ (opens the place chooser; "Everywhere" with none), and
    "Search Directory:" a drop-down of **All, Discovery, Directory, Community, Marketplace** (the four sections: Let's
    Explore, Get Local, Social, Marketplace). It replaces the "Search in" pills.
  - **left:** **Search Results** (before typing, a few searches to try), then **Suggestions**: a likely word ("Did you
    mean"), the standard filters (Near me, Open now, Has a phone, Has a website, Favorites; §11's "filters live in the
    search" now means here), and more of the place's searches.
  - **right:** **Quick Search** with ⊕ (the typed words become one of yours) and ✎ (change yours): your saved searches
    (★, on your account), your own (on this device), then the place's, each with how many it finds; **Recent
    Searches** with ⊗ to clear; and at the foot "Looking for a specific person? Search Your Address Book".
  - Phones stack the same parts in one column.
- **The account menu:** "Current Account / Acting as <role>" with ✎ for your profile, then every account you can act
  as, each a bar saying what kind it is: Personal Account, Listing Account, Steward Account, System Admin Account. The
  current one has a sage edge and a ✓. Dashboard and Sign out left the menu: a click on the icon opens the dashboard,
  and Sign out is in ☰.

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

- **2026-10-01, the Explore hero (§18):** built.
- **2026-10-01, search by section and the panel's top row (§19):** built.
- **2026-10-01, the hero on listings and profiles, their own media, maps with the panel open (§21):** built.
- **2026-10-01, actions over the hero, About add-ons, search that widens (§22):** built.
- **2026-10-01, the content page standard (§20):** built on listings, outdoor places, experiences, place pages and
  journeys.

### Open items at the end of the 2026-10-01 session

**Decisions:** both made (§19).

**To check on the live site (the sandbox can't reach the Census or show real area names):**
- The Subway pin on W Cleveland St moves to the street when opened on the map (§14).
- An owner or staff address change finds the new address (§15).
- Destination order with real area names: type "Show" (§17).
- "Sports" no longer lists the post office; "pizza" lists Dittys and Fire Stone (§15, §16).

**Data, not code:**
- Grand Canyon and Disneyland appear in Destination once their Recreation.gov records or listings are loaded (§17).

**Still to build (Consequences):** profile identity, Personal Guides and follower count, reputation levels,
Recommendations and Personal Insights, outbound API sharing.

**Next session (2026-10-02, Jason):** rebuild the admin dashboard and its menu. Search index, Recreation and other
data pages are reached only through links in page text today (Crawler → Overview, Cluster tools).

**Decided 2026-10-02 (Jason), built in §26: on a nation's land, show only the nation's public places.** Only what the nation
itself lists for visitors (its parks, museums, events, enterprises), each with the rules visitors miss: permits and
licenses, fees, where a tribal guide is required, photography and drones, alcohol, the nation's time zone. Each nation is
approached on its own; the Navajo Nation's Parks & Recreation first, with Four Corners Monument as the pilot. The
working draft (workflow, data model, nations, Apache County worked example, open questions) is the Claude Doc
"Sovereign lands: what Traversence shows, and who decides". Still to build: the `nations` table, per-nation rules on
pages, boundary checks on Recreation.gov places, experiences and journey pins, and nation accounts.

**Housekeeping:**
- The `claude/magical-clarke-cn5pqi` branch is far ahead of `main`. Merge it when ready.
- Revoke the GitHub token exposed in the OneDrive repo-local config, if not done.

### Open items at the end of the 2026-10-02 session

**To upload, in this order:**
1. SQL: `2026-10-16_landmarks.sql`, then `2026-10-17_nations.sql`.
2. Zips: hero-map-search, landmarks, four-corners, location-panel, nations, nation-public-places. The last one replaces
   nation-switch; skip that one.
3. Then:
   - let the worker run its landmarks load (it also sends the nations' boundaries);
   - rebuild Outdoors in Admin → Search index;
   - add Four Corners Monument in Admin → Landmarks with the Navajo Nation's own page;
   - fill in the Navajo Nation in Admin → Nations from its own site.

**To check on the live site:**
- The worker log for the first landmarks load: Wikidata, Wikipedia and Commons only ran on sample data here.
- The hero title on the Grants listing, and × on a search's place chip.
- The location panel fills the phone screen.
- The Search index page's "Last full build" date updates after a build.
- The earlier list still applies: Subway pin, address change, "Show", "Sports", "pizza".

**Next session:**
1. Directions on our map with OpenRouteService (§30), once `ORS_API_KEY` is in the site's `.env`.
1. Rebuild the admin dashboard and its menu, with Search index, Recreation and the data pages in it.
2. Boundary checks on Recreation.gov places and experiences on nation land (§26).
3. Parks & Landmarks is built (§32). National Park Service data still waits on an NPS key in Railway.
4. Temporarily closed places (seasonal, weather, a nation's closure notice): proposed, not decided.
5. Nation accounts (the nation tools), which turn `Landmarks::NATION_TOOLS` on.

**Housekeeping:** merge `claude/magical-clarke-cn5pqi` into `main` when ready. Revoke the GitHub token exposed in the
OneDrive repo-local config, if not done.
