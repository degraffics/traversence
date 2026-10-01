# ADR 0053: Universal Search, In Place

**Status:** Accepted (2026-09-30) by Jason. Works with `decisions/0050`
(data use), `decisions/0054` (Nexus grouping), `decisions/0055` (app shell). Amended 2026-09-30: §8, the 5W search
model.

## Context

Search today is the directory's listing search, reached by leaving the page you're on. It treats most queries as
business names: "Greer Arizona" returned four businesses and didn't move the search to Greer. But people look for more
than listings: a place, a group, a story, a topic, facts about a town. And searching shouldn't pull anyone off the page
they're on until they choose to go.

## Decision

### 1. Search opens in place, like a small landing page

- **A search bar on every page** except dashboards and admin pages (their lists have their own search,
  `decisions/0055` §3) and sign-in and registration pages. Desktop: after the logo. Phone: an icon by the bell that
  opens the panel full-screen.
- **Typing opens a panel over the page**, which stays where it was underneath. Results update as you type and are
  grouped by kind; you can retype, and add or remove chips, without leaving.
- **A peek before you go:** a listing opens as a card in a lightbox (phone, hours, directions, Link, Save to Address
  Book). Opening a full page is always the person's choice.
- **Closing returns you exactly where you were:** ✕, Esc, a click outside, or the phone's Back button (Back closes the
  panel, not the page). Reopening shows the last search.
- **Before typing:** recent searches, saved searches, presets (§5), and the current place.
- **It knows where you are:** on a place page, results default to that place ("in Round Valley ×"); on a listing,
  "similar nearby" is offered; everywhere else, the set location is the default area.

### 2. It searches everything, and routes each result to its home

| Kind | Examples | Goes to |
|---|---|---|
| **Places** | a region, area, town, local name, public tribal-nation pages | the place page |
| **Place facts** | "Show Low population", "St. Johns median age", "demographics Round Valley" | an answer in the panel ("Show Low: 11,732 people, census 2020") with a link to the place's facts section |
| **Listings** | businesses, services, parks, trailheads | a peek card; the listing page; "See all" opens the directory with the filters |
| **Stories and guides** | discovery guides, place stories | the story |
| **Groups** | community groups (name, about, topic, place) | the group page (members-only posts are never searched) |
| **Topics** | categories, community topics, accepted @tags | the topic's page or filtered view |
| **Events, Marketplace** | when they exist | their pages |

**People are not in universal search.** Finding a person stays in the Address Book, under that person's own "how
people can find me" settings (`decisions/0047`). Public profiles could opt in later.

**Looking for a person? A quick link hands off to the Address Book.** The panel always carries a "People" row:
"Looking for a person? Search your Address Book for 'Maria'". It opens the Address Book's Find with the words already
typed. That search covers the person's own contacts and connections, plus members who allow being found that way. A
query that reads as a person's name (the "person" chip) puts this row near the top. Signed out, the row offers
sign-in first. (Jason, 2026-09-30.)

Each kind shows its top few, with "See all" for that kind. A **top result** leads when one answer is clearly it: an
exact place, an exact business name, or a facts question.

### 3. Query understanding: the query becomes chips

The query is read into chips the person can see and change:
- **Place:** "Greer Arizona", "near Show Low", "in Round Valley". A place name wins over a business name unless it
  exactly matches a business. When it's truly ambiguous, both are shown.
- **Category:** "physical therapy", "gas", "trailhead".
- **Kind:** "groups", "stories", "events".
- **Conditions:** "open now", "open Sunday", "has phone", "has website".
- **Facts:** "population", "demographics", "median age", "income".
- **Organization:** "VA", "White Mountain PT". Grouped as a Nexus (`decisions/0054` §3).

Words left over after the chips are the search text. Removing a chip searches again at once.

### 4. The full results page (the directory)

"See all" for listings opens the directory's full view, which gets a filter panel:
- place, with a radius slider;
- category checkboxes;
- open now, or open on a chosen day;
- has phone, has website;
- a sort menu;
- active filters as removable chips;
- map and list, with the layout toggle from `decisions/0055` §3.

### 4b. "Where should I go" searches get a map and a radius (Jason, 2026-09-30)

Discovery-type queries ("things to do near Show Low", "hikes within 50 miles", "where to go this weekend") are about
distance and direction, not a list:
- **In the panel:** when a place is set, a radius chip ("near Show Low · 25 mi ▾": in town, 10, 25, 50, 100 miles)
  widens every kind of result. A **Map** button shows everything found as pins: places, trailheads and parks, stories,
  and listings.
- **On the Discovery page** (`decisions/0055` step 5): the map and the radius are built in, next to the category rail.

### 5. Presets come from the system, not admins

One-tap searches ("Open now near you", "Trailheads within 20 miles", "Groups in Round Valley") are generated per
place from aggregate signals:
- what's listed there;
- what's commonly searched and linked there, as counts;
- the season and the time of day.

They are never personal targeting (`decisions/0050`), and no admin curates them.

### 6. Saved searches

Members can save a search (its chips and text) with a name. Saved searches appear before typing and in the dashboard.
Alerts for new matches may come later, only if asked for.

### 7. What's kept

- Recent searches live in the browser only.
- Saved searches are on the account, by choice.
- Query text is never stored against a person. Traversence keeps only aggregate counts (a term, the place, the day)
  to build presets and to spot missing places or listings, the same way as `decisions/0051`'s tags.

### 8. The search model is the 5 Ws, not kinds (Jason, 2026-09-30)

This changes §2 and §3. The query is read into **Who, What, When, Where and Why**, the same "5 Ws and actions" the
crawler already reads from pages (`architecture.md`). Anything that matches comes back, whatever its kind. Kinds
(place, listing, story, group, topic, event) become a way to group or filter the results (the `decisions/0055` §3
collection component), not the first question.

| W | Reads | Examples |
|---|---|---|
| **Where** | a place, a distance, along a route | "near Show Low", "within 50 miles", "Round Valley" |
| **What** | a category, activity, item or topic | "physical therapy", "lakes", "fry bread", "powwow" |
| **When** | open hours, dates, seasons | "open now", "this weekend", "in winter", "Sunday" |
| **Who** | a public role or who it's for (below) | "the mayor of St. Johns", "for veterans", "tribally owned" |
| **Why** | the intent | eat, heal, explore, connect, buy, learn, get help |

- Each W that was read shows as a chip you can remove, as in §3.
- **Why orders; alone, it chooses.** With other words ("pizza near Show Low, for dinner") Why only changes the order,
  because it is the weakest guess. Asked alone ("places to eat in St. Johns"), it chooses what is shown. If nothing is
  tagged for it, everything in the place is shown instead.
- **Nouns and verbs.** The nouns in a query name the Who, What and Where. The verbs ("eat", "stay", "explore", "get
  help") carry the Why.
- **How is the sixth question:** how to reach it, contact it, or use it. It shows as conditions (has a phone, open,
  accessible, free) and as the actions on each result (Call, Directions, Website, Link, Save).
- **When needs data:** hours (`api/lib/Hours.php`), event dates, seasons. Where these are missing, results still show;
  they just aren't boosted.
- **Presets (§5) are 5Ws filled in ahead of time:** "Where should I go", "What's open now", "What's happening this
  weekend", "Where can I get help". **Saved searches (§6) save the Ws.**

**Who: public roles, never personal profiles.** Who someone is in public is searchable:
- a business or organization name;
- a person in a public role, as the source published it:
  - the author or reporter of a story;
  - an elected or appointed official, in their office ("Mayor, City of St. Johns");
  - a business's published staff or providers ("physical therapist at White Mountain PT");
- who something is for or run by: families, veterans, seniors, tribally owned, locally owned.

The result routes to the **public thing**: the story, the office or government listing, the business. It is shown
with the role ("Author of …", "Mayor, City of St. Johns"). The rules:
- There is **no page about a person**, and nothing that gathers one person's roles into a profile.
- Nothing personal is indexed: no member profiles, no home addresses, no personal contacts, no message content.
- **A name alone goes to the Address Book** (§2, unchanged). If the name also matches a public role, both show: the
  public result, and the Address Book row.
- Staff mentions come only from what the business or source published. A person can ask to be removed from one.
  Officials stay listed in their office while they hold it.

**How results are presented** (Jason, 2026-09-30, from the Five Ws, DIKW and progressive disclosure):
- **Feedback loop:** the chips say back what was understood ("Somewhere to eat · St. Johns · Open now"). Removing
  one corrects it at once.
- **The answer first:** a clear top result or a facts answer leads (Minto), then everything else.
- **In small chunks:** five results at a time in one list, three per kind when grouped, with "Show more". People take
  in 3 to 5 things at once.
- **Progressive disclosure:** the panel shows the few that matter. The peek card and the map show more, and the page
  shows everything.
- **Context:** the visitor's place and local time are always part of the reading ("open now" is their now). Later,
  so is the role they are acting in (`decisions/0055` §4).
- **From data to knowledge:** listings and places are the data. The 5W index turns them into information. Answers
  (facts, open now, nearest) are knowledge. Presets built from aggregate signals (§5) are the "why" layer: what is
  worth doing here, now.

**Build: one index.** Every item gets its 5W tags when it is saved, imported or crawled. They go in one search table
(item kind and id, What terms, Who roles and audiences, When data, Where point and place keys, Why intents, plus
text)
that MySQL 5.7 FULLTEXT can search. It is filled first from existing data: categories, hours, place keys,
coordinates, story bylines, group topics. The current place, category and distance reading, the map and the peek
card carry over.

Build order: (a) the index and the 5W reading, (b) presets as 5W presets (§5), (c) saved searches (§6).

## Consequences

Build order:
1. A universal search endpoint (`/api/search.php`) that returns chips and grouped results by kind.
2. The in-place panel: desktop dropdown, phone full-screen, Back closes it, peek cards.
3. Query understanding, including the Greer fix (place before business name).
4. The directory's filter panel.
5. Presets.
6. Saved searches.

The directory's current bar becomes the shared bar. Its full results view stays as the "See all" destination.

Open: which place facts to answer directly (start with population, median age, median household income, from the
census data already on place pages); whether events and marketplace join at launch or later.

## Progress (2026-09-30): steps 1–3

- **The engine:** `api/lib/UniversalSearch.php` and `/api/search.php`. It returns chips, a top result, grouped
  results and the Address Book hand-off.
- **Query understanding:**
  - a place at the start or end of the query, with an optional state; towns are matched through their ZIPs to
    their cluster;
  - an exact business name;
  - a category by name, everyday word or word root ("physical therapy" becomes Physical Therapists);
  - a kind ("groups", "stories");
  - has phone / has website;
  - facts words.
- **"Greer Arizona"** is now the place Greer, AZ (28 listings), with the business named "Greer Arizona" listed
  under it. That business name is likely why the old search showed four results.
- **Facts** are answered from the census data already on place pages ("Show Low: 18,824 people, median age 49,
  median household income $60,313").
- **The panel** (`js/search-panel.js`) is on every page except dashboards, admin pages, sign-in pages and the
  directory. It has:
  - removable chips;
  - listing peek cards (a confidential location shows its phone only);
  - Back, Esc or ✕ to close it;
  - the page's or visitor's place as the default area;
  - recent searches kept in the browser.
- **"See all"** opens the directory with its place, words and category (`?cluster=&q=&cat=&catname=`).
- **People:** a "Looking for a person?" row opens the Address Book's Find with the words already typed
  (`#addressbook:find=`). The full row shows when the query looks like a name or nothing else matched; otherwise it
  is a footer link.

**Also fixed:**
- the header's loop variables overwrote the dashboard's icon helper, which crashed the account dashboard; header
  variables are now all `$tv_`-prefixed;
- the bell now reopens the Notification Center after ×.

## Progress (2026-09-30): step 4, the directory's Filters panel

- **Filters button** beside Sort, with a count of the filters in use.
- **Open:** any time, open now (the visitor's own local time), or open on a chosen day. Read by `api/lib/Hours.php`
  from hours as people write them: "Mon–Fri 9am–5pm", "Monday - Thursday 6am - 5pm", "Mo-Fr 09:00-15:00",
  "Weekends 10-4", 24 hours, past-midnight hours, "Closed". Unreadable hours never count as open.
- **Has** a phone number or a website.
- **Categories:** tick several to see them together.
- Each filter shows as a removable chip in the results sentence.
- `api/search_listings.php` takes `category=a,b`, `has=phone,website`, and `open=now&at=DOW,MIN` or `open=mon…sun`.
- The existing radius and nearby-towns tools stay, linked from the panel.

**Also:**
- The search panel's peek card now has **Link** and **Save to Address Book**, with the same consent steps.
- The universal bar has the **place pin**, and its placeholder names the place ("Search in St. Johns…").

Next: step 4b (radius chip and map in the panel), presets (step 5) and saved searches (step 6).

### Progress: step 4b, the full-screen map and radius (2026-09-30)

- **One map for the site** (`js/map-view.js`, `TvMap`). It fills the screen under the header. A tool strip floats on
  top with the title, distance (5–100 mi) and List. A card floats above the map for the chosen pin: details, Call,
  Directions, Website, Open, Link and Save, with previous and next through the pins in view, nearest first (swipe on
  phones). After the map is moved, **Search this area** appears. Back, Esc or List close it. Leaflet loads on first
  use.
- **Directory:** the Map view opens the full-screen map, with the same search and filters.
- **Search panel:** when a place is understood, a **Distance** menu appears: in the place, or within 10, 25, 50 or
  100 mi. The place chip shows the distance ("Show Low, AZ + 25 mi"). A **Map** button opens the full-screen map for
  the same search. List or Back returns to the results as they were.
- **"See all"** keeps the distance: the directory opens on a point with that radius, named for the place ("within
  25 mi of Show Low, AZ").
- `api/search.php` takes `radius`. `UniversalSearch::search` measures from the place's first ZIP and returns `map`
  (the query for the map's pins) and `area`.

Next: the 5W index and reading (§8).

### Progress: step 5W-1, the index and the 5W reading (2026-09-30)

- **The index:** `search_index`, filled by `api/lib/SearchIndex.php`.
  - It holds listings, Recreation.gov outdoors places (hidden ones left out), stories, community groups, and topics
    (categories and community topics).
  - Each row has What terms, public Who (the managing agency; who it's for or run by, as it says itself), hours for
    When, ZIP, town, cluster, place key and point for Where, and Why intents read from its category.
  - Built from **Admin > Crawler > Data loads > Search index**. After that it keeps itself current with no cron: at
    most every 15 minutes, a search re-reads changed listings and rebuilds stories, groups and topics.
  - Until the first build, search works as before.
- **The reading:**
  - **When:** open now, tonight, today, this weekend, a day of the week.
  - **Who:** "for veterans", "for kids", "veteran-owned"; public offices ("the mayor" is searched as the city
    government, city offices first).
  - **Why:** "places to eat", "things to do", "where to stay", "get help", "to buy", "to learn".
  - Each is a chip that can be removed.
- **The panel:**
  - One list of everything that matches, with a kind tag on each row, and "Group by kind" (remembered).
  - Chunks with "Show more".
  - "Open" or "Hours not listed" when When was asked.
  - "See all … listings in the directory" at the foot.
- **Also:**
  - Tapping the category on a map card or a peek card narrows the search to everything like it in the same place.
  - The header, its suggestions and the search panel now stay above the full-screen map.
- **Known gaps:**
  - **Cuisine (fixed the same day):** a food place's whole description, offerings and name are read for what it
    serves ("tacos", "tamales", "fry bread", "pho", "brisket"). The words and the cuisine they point to (Mexican,
    Southwestern, Native foods, BBQ…) become What terms, so "mexican in St. Johns" finds a place that never says
    "Mexican" but lists tamales. A place with no description or offerings still needs one: from its website
    (crawler) or OpenStreetMap.
  - **"See all" and When:** "See all" doesn't carry When to the directory yet.
  - **Public roles:** officials and staff come in as sources publish them (crawler).

Next: presets as 5W presets (step 5), then saved searches (step 6).

### Progress: step 5, presets, and results by section (2026-09-30)

**Presets** (`api/lib/SearchPresets.php`). Before typing, the panel shows one-tap questions for the visitor's place
("Around St. Johns: Dinner 5 · Things to do 6 · Health care 23 · Where to get help 3").
- **What's there:** each preset shows only if something in the place answers it, with how many.
- **The time:** at meal times the food preset becomes Breakfast, Lunch or Dinner and moves up. From Thursday, "Things
  to do this weekend". In the evening, "Open now" (when enough listings publish hours). Friday and Saturday, "Where to
  stay".
- **What's asked here:** `search_counts` keeps how often each reading is acted on, per place per day (why:eat,
  cat:pharmacies, when:now). It stores no words and no person. A count is sent only when someone picks a result, once
  per question per 10 minutes per visit, and rows are removed after 90 days. A category asked about 3 or more times in
  30 days in a place becomes its own preset there.

**Results by section** (Jason: "funnel Discovery / Directory / Community / Marketplace within the 5 Ws").
- Every result belongs to a section of the site, named as in the header:
  - **Let's Explore:** places, stories, outdoors.
  - **Get Local:** listings and categories.
  - **Social:** groups and community topics.
  - **Market:** shown as "soon" when someone asks to buy.
- **Tabs with counts** filter the results to one section, with "Open Get Local →" and the like at the foot.
  "Group by section" lists them section by section.
- **Naming a section** in the query narrows to it: "in the directory", "in the community", "on the marketplace". The
  words only count with "in", "on" or "from" in front, so "farmers market", "community college" and "social services"
  stay words.

**Better reading** (from "remodel my home" finding nothing):
- **Question words are left out:** my, the, find, need, near, best…
- **Soft words only help the order:** home, house, service.
- **If every word is required and nothing matches, any word will do,** with the most matched first.
- **Verbs for work people want done** find the trades: remodel, renovate, fix, repair, paint, landscaping, moving,
  cleaning, pest. The new Why "Repairs and projects" covers them.
- **A direct match ranks above an alternative word.** A short word matches whole ("car" isn't "carpet").
- **Names:** "Looking for a person?" leads only when the words look like a name: no everyday words, and nothing
  found except by the loose try.
- **The Map button** shows only when there are listings to map.

### Progress: the search panel's home, saved searches (step 6), and the directory rebuilt (2026-09-30)

From Jason's review (with Google's "businesses near me" and "places to go near me" as reference).

**The search panel before typing:** two columns on a computer, stacked on a phone.
- **Where:** "📍 Searching in St. Johns", and why it's set: still set from earlier in this visit (session active),
  your default place, the region you came from, or chosen by you. It has Change and Search everywhere. A place
  kept from earlier is never a surprise.
- **Left, personal:**
  - **Recent searches:** a clock icon, ✕ on each, and Clear all. They stay in the browser only.
  - **Saved searches (step 6, on the account):** ★ with the place. Remove one with ✕. Save a search with
    "☆ Save this search" on its results, up to 20.
  - **Search in:** All, Places, Listings, Stories, Groups, Topics. The chosen scope becomes a token in the field
    ("In: Stories ×"), and Backspace at the start of the field removes it.
- **Right, discovery:** the place's presets (§5), each with its count as a grey badge and a ✎ to put it in the field
  and change it before searching.
- **Readability:** the helper text is darker, and the Address Book link is underlined with an arrow.
- **Still to come:** a "New" count on saved searches (`last_total` is stored for it).

**The directory (Get Local) rebuilt on the layout.** Like a "businesses near me" search:
- **The place line:** why it's set, Change, "◎ Use precise location" (the device's location, nearest first), and
  Search everywhere.
- **Search and filters:** a search field, and filters as chips: the category, the words, 🕒 Open now, 📞 Has a phone,
  🌐 Has a website, distance, and sort.
- **The category picks** show until something is chosen.
- **Results beside a map:** numbered results match numbered pins, and hovering or clicking one highlights the other.
  "Full map" opens the full-screen map. Phones show the map above the list.
- **Each result:** its name (Verified if it is), category and distance, address and phone, and **open or closed right
  now** ("Open · Closes 5 PM", "Closed · Opens tomorrow 8 AM", from `Hours::status`). Then **Call, Directions,
  Website, Peek**. A confidential location shows its phone only.
- **"Show more"** loads the next 20.
- **The address keeps the search** (it can be shared, and Back works). Links from elsewhere (`?q`, `?cat`, a place,
  a distance) open straight into results.
- **Kept:** the earlier directory is at `/directory/classic.php`, linked from the toolbar.
- **The listings API** now also returns each card's website, point (the middle of its ZIP area when it has none of
  its own), today's hours text, and whether its location is private. The header's universal search now shows on the
  directory too.
- **Search matches singular and plural** ("pharmacy" finds Pharmacies, "therapy" finds therapies).


**"Tell us": what visitors know, feeding Review and the crawler** (migration `2026-10-06_suggestions.sql`).
- **One short form, everywhere** (`js/suggest.js`, `TvSuggest.open()`, or any `data-tv-suggest` button). It covers
  more than businesses: a lake, trail, park, campground, landmark or event; information for a place's guide story;
  feedback on search results. Without an account it works too, limited to 12 a day per device or person; a hidden field
  catches bots. We never publish who sent a suggestion.
- **Kinds:** something missing, a fix, closed or moved, listed twice (with a picker for the other listing), for a
  guide story, and search feedback. Each keeps its **topic** (business, outdoors, culture, food, event, story), the
  **section** it was missing from ("Lakes & water" near St. Johns), the place, and the page or search it came from.
- **Where it's offered:**
  - the search dropdown's footer: "Tell us what's missing · Feedback on these results";
  - the peek card: "Suggest an edit or tell us something";
  - every collection: "Missing a lake or river? Tell us" and "Know a place that isn't here?";
  - the place page: "Share it for the guide" under the story, or "Know something we should add?";
  - the listing and recreation pages: "Closed, moved or listed twice?" and "Something wrong or missing?";
  - the directory: in the empty state and under the results.
- **Review:** a "From visitors" chip, counted in the Review badge. Each card shows what was sent and offers the next
  step: **Crawl this website** (Listing Intake with the address filled in), Edit the listing, Open the place, **Open
  the story editor** for the place, Look it up, Done, or Dismiss. Nothing changes on the site by itself.
- **Match context for the crawler:** a website or phone a visitor gives for a business is kept. When a crawl finds
  that business's own website or phone (never the directory site it was crawled from), `IntakeStager` offers the
  listing as a possible duplicate ("a visitor gave this website for it"), or flags that a visitor suggested the
  business, so the person merging sees it. Only for business and food topics.
- The endpoint is `/api/suggestion.php`. `/api/suggest.php` remains the search box's type-ahead.
- **Layout:** on desktop the side toolbar is now fixed like the header: it stays in place while the workspace and
  footer scroll, and it starts where the header ends. Phones keep the swipeable row.

**Directory: one search, List or Map; "Search in" by section.**
- **The directory has no search box of its own.** The header's search is the search: on the directory, pressing Enter
  shows the words in the list (`window.tvSearchHere`). The title and place line are gone too, because the header
  already shows the place. What's left is the filter chips (◎ Near me, Open now, Has a phone, Has a website, distance,
  sort), the category picks (one swipeable row on phones), and the results.
- **List or Map toggle** (remembered on the device). The list is full width. The map is full width, with numbered
  pins. A pin's popup shows the name, category, today's hours and the address, with Call, Peek and Open. "Show 20 more"
  and "Full screen" sit on the map.
- **"Search in"** is now the brand sections plus topics: All, Let's Explore, Get Local, Social, Marketplace, Topics
  (`scope=f:explore|f:local|f:social|f:market` narrows by section; `scope=topics` by kind). The section's name reads
  "Marketplace".
- **✎ on a suggested search** puts it in the field and shows its results straight away, ready to change.

**Directory header, map fills the workspace; Quick picks and "Your searches" in the dropdown.**
- **The directory's category pills are gone.** Categories come through the search ("food", "auto repair"). The page is
  now a header line (the title, the count, and the filters in use, each with ×, plus sort when there's more than one
  result) with the **List | Map** toggle on the same line.
- **Map fills the workspace**, from under the header to the bottom and from the toolbar to the right edge. The title
  card and the toggle float over it, and "Show 20 more" sits bottom-left. The page doesn't scroll behind it.
- **Quick picks** (◎ Near me, 🕒 Open now, 📞 Has a phone, 🌐 Has a website) moved from the page into the search
  dropdown. On the directory they switch its filters in place (`window.tvQuick`); anywhere else they open the directory
  with that filter (`?near=me`, `?open=now`, `?has=phone|website`, plus the words typed).
- **Your searches:** one ✎ Edit on the list of one-tap searches opens a small editor. People can add their own
  phrases (bookmark terms such as "vegan food in Show Low"), change or remove them, or add one of the place's
  suggestions with +. Their phrases (🔖) show first, then the place's suggestions. They're kept on the device (up to 12,
  `tv_my_searches`); saving them to the account goes with the other settings to move there.

**Dropdown and directory, round 3.**
- **Dropdown:** the columns are now two-thirds and one-third, with tighter spacing, so the home view fits without
  scrolling.
- **On the directory**, results while typing start with "Show '…' in the directory list" (Enter does the same). When
  the dropdown closes, the list follows whatever is in the field, so the title, list and map always match the search.
  The dropdown's reading ("Understood as") is still offered, so both are there: the literal list, and the 5W reading
  with its other kinds.
- **The "Ask about" pills on Discover work now.** The click that opened the dropdown was reaching the "click outside
  closes it" handler; `TvSearch.open` now waits for the click to finish.
- **Directory filters sit between the title and List | Map:** the words or category in use (×), 🕒 Open now,
  📞 Phone, 🌐 Website, **★ Favorites** (the businesses you linked or saved to your Address Book; the listings API
  takes `ids=`), distance and sort. On the map they float as one strip.
- **Map pins:** listings without their own point share their ZIP's middle, so 20 pins stacked into one. They're now
  spread in a small spiral, and the map zooms in until each shows. Real points need street addresses geocoded (see the
  follow-up below).
- **Follow-up, geocoding:** the crawler's refresh (decisions/0045) fills empty fields, addresses included, for
  listings with a website, at most every 60 days. It doesn't turn an address into a map point. The U.S. Census
  geocoder (free, batches of 10,000) can, for every listing with a street address.
