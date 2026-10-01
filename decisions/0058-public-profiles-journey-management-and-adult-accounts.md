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

  It replaces the swipeable row of icons.
- **Auto-detect and ZIPs on Let's Explore:** a device location or a ZIP now opens the town area it belongs to (the
  cluster whose ZIPs include it, else the nearest within 40 miles). `/api/place.php` returns it as `place.cluster`.
- **Satellite** uses the USGS imagery with towns, roads and boundaries labelled.

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
