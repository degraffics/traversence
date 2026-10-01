# ADR 0059: Experiences

**Status:** Accepted (2026-10-01) by Jason. Replaces Let's Explore's "Key destinations" (`decisions/0058` §18). Builds
on `decisions/0056` (journeys, the age gate), `decisions/0058` (roles, the Explore hero, the Chameleon Filter in
search) and `brand.md` §5.

## Context

Let's Explore's Destinations tab listed our areas (geo-hubs). That's a filing system, not a reason to travel. With
one open area it showed one lonely card, and the areas are already reached through Regions and the place pages.
What brings people somewhere is what they do there. National Parks stay separate: they're about places, while
experiences are about what people do.

## Decision

### 1. What an experience is

Something to do that's worth the trip, the human side of a place: "Paddle Lyman Lake at sunrise", "Rodeo weekend in
Springerville", "Stargaze in dark-sky country". The test:
- you take part, rather than just look;
- it belongs to the place (its land, history or people);
- it's worth half a day or more, or it's tied to a season or an event;
- someone local can make it happen.

Each one has:
- a title that starts with the doing;
- a place (a town area, area or region, as a scope key like journeys);
- what it's like, and how to do it;
- a kind: Adventure, On the water, Heritage & culture, Night skies, Food & craft, Festivals & events, Rodeo & game
  day, Slow & restorative, or With kids;
- its best seasons, how long it takes and how hard it is;
- an optional "Go with respect" note;
- its connections: the businesses that make it happen, the outdoor places it's at, and the journeys of people who
  did it.

### 2. Three sources, one gate

- **Members** post their own (Dashboard → Your experiences). They must be signed in and confirm they're 18 or older.
  A member can post up to five a day.
- **The system** suggests them from what the crawler already knows:
  - outdoor places, by their activities (paddling, fishing, hiking, stargazing …);
  - listings that offer an experience (outfitters, guides, stables, rodeos, museums, ski areas, tours, breweries and
    wineries);
  - journeys people shared about something worth doing.

  Each source is suggested once, and a rejected suggestion never comes back. "Find new suggestions" in Admin runs it
  again.
- **Staff** add them. A Content Creator's goes to review; an Editor's is published at once.

**A person approves every one** (Admin → Experiences) before it shows:
- an Editor approves;
- a Content Creator can edit, reject or hide;
- a member's experience can't be rejected without a note, which the member sees;
- a member's edit to their own experience goes back to review.

### 3. Where they show

- **Let's Explore → Experiences** (it replaces Destinations; old Destinations links open it):
  - the Explore hero, titled "Explore: Experiences";
  - the Regions / Experiences / National Parks strip;
  - a carousel of experiences in the place you're exploring, in season first, then the ones with the most journeys;
  - the kinds as a row of filters;
  - the rest as cards;
  - "Post an experience".

  With none in the place yet, it shows ones from across the open regions and says so.
- **The experience's own page** (`/experiences/view.php`):
  - its photo, from a linked journey (else the brand's art), kind, title, place, and "In season now";
  - its seasons, how long it takes and how hard it is;
  - what it's like, how to do it, and "Go with respect";
  - then its connections (the Chameleon Filter): who can take you, where it happens, and people who did it;
  - "Done it? Share your journey".
- **Search on Let's Explore** puts matching experiences first, before journeys and stories (`decisions/0058` §19).
- The ☰ menus: Let's Explore → Experiences; Dashboard → Your experiences; Admin → Experiences.

### 4. Safety

- Public places and events only.
- On a sovereign nation's land, only what the nation offers visitors publicly, and never the location of a sacred or
  restricted site (`decisions/0044`, `0056`).
- Confidential listings are never linked.
- A member is credited by their public name; a private profile reads "A member".
- The system's suggestions and staff posts read "From Traversence".

## Consequences

- **Migration:** `2026-10-12_experiences.sql` adds `experiences` and `experience_links`.
- **Code:**
  - `api/lib/Experiences.php`, `includes/experience-ui.php`;
  - `experiences/view.php`, `user/experiences/`, `admin/experiences.php`;
  - the Experiences view in `discovery/index.php`;
  - the shared card `tv_explore_card()` in `includes/explore-hero.php`.
- **Counts:** `experience:<id>` views (`decisions/0058` §13).
- **Still to build:**
  - photos uploaded straight onto an experience (today they come from linked journeys);
  - experiences on place pages and listing pages ("Experiences with this business");
  - events as a source, once events exist;
  - saving experiences to a trip plan.

## Progress

- **2026-10-01:** built all of the above and tested it in the sandbox. The system's first pass found 33 suggestions
  there. Some carry crawled business abbreviations ("Otfttrs") that editors tidy before approving.
