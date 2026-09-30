# ADR 0053: Universal Search, In Place

**Status:** Accepted (2026-09-30) by Jason. Works with `decisions/0050`
(data use), `decisions/0054` (Nexus grouping), `decisions/0055` (app shell).

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
