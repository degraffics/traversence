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
