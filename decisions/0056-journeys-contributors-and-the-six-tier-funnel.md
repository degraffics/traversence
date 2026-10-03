# ADR 0056: Journeys, Contributors and the Six-Tier Discovery Funnel

**Status:** Accepted (2026-10-01) by Jason. Builds on `decisions/0043` (guide stories), `decisions/0047`
(connection points), `decisions/0053` (universal search, North America cold start), `decisions/0055` (app shell and
roles). Amends the standing privacy rule (§4).

## Context

Let's Explore so far is a reference guide: places, outdoors, food, culture, and our own reviewed guide stories.
Discovery is also meant to capture the human journey: what it felt like to hike a trail, the diner someone stopped at.
That needs content from people, shown as theirs, next to our own editorial content, and a path for anyone to become a
contributor, including Jason, who will feed the first content.

## Decision

### 1. Two kinds of content, always labelled

- **Traversence Guides and Articles:** our vetted reference and editorial content, checked and sourced
  (`decisions/0043`). Labelled **Traversence**.
- **Journeys:** first-person stories by a named contributor, with a date. Labelled **Contributor** with the author's
  name. Journeys build the contributor's trust; Guides build the platform's.
- The front end always makes the difference clear (a badge on every card and page). Staff can post either: as
  Traversence (editorial) or as themselves (a journey).

### 2. Journeys

- **Shape:** a category ("Exploring", "Hiking", "Dining", "History", "Culture", "Road trip", "Events", "Family",
  "Night skies") and a title ("Exploring | The Arizona Trail"); the place it's about (a region, area, town cluster, or
  public recreation place); when they were there ("Was here", a date); the story; photos (hosted by us) and/or one
  video (a YouTube or Vimeo link); the listings it mentions.
- **Shown with:** the post date, read time, author, views, comments, likes, and a link button.
- **Connection points (the "Chameleon Engine"):** a journey links to the listings and destinations it mentions or
  matches. This is the bridge from Let's Explore to Get Local. Contributors pick listings; matching from the text
  comes later.
- **Comments are public** (anyone reads; signed-in members aged 13+ post).

### 3. Contributors: anyone can become one

- **Path:** sign up → **Become a contributor** (accept the contributor guidelines; a public profile; 13 or older) →
  post. The same path is in the member dashboard and for staff.
- **Private profiles can't be contributors** (and never appear in Top Contributors).
- **Top Contributors** rank by a contributor **trust score**: well-received recent journeys (likes and comments,
  weighted to the last 90 days), published journeys without upheld reports, account age, and a verified email. What
  each contributor makes is shown (video, photo, writing).
- **Join the Team** = become a contributor. Regional campaigns recruit contributors to fill each region's feed;
  until then, the feed mixes in our guides, place highlights and outdoors picks.

### 4. Privacy, amended: "We never expose anyone's location; a person may share their own."

The standing rule "a user's email and location are never exposed" becomes: **we never expose anyone's location; a
person may share their own.** Email stays private, always.

- **Photos:** everything hidden in a photo file (GPS coordinates, device, timestamps) is **stripped** on upload. The
  contributor **tags** where it was taken themselves (a place, or a pin they set), if they want to.
- **Was Here vs Am Here:**
  - **Was Here** (a journey about a past trip) is the default: the contributor shares what they choose, when they
    choose.
  - **Am Here** (a live check-in) is a **feature a person activates**, never on by default. Activating it shows its
    rules and inherent risks (anyone can see you are there now; your home may be empty), and asks for consent. At
    check-in time they choose **post now** or **delay** (for a duration they pick). Once activated, they can set it
    to post live automatically. (To be built; the rules are set here.)
- **Other people:** posting guidance and definite rules for people in photos (consent, especially for children),
  private homes and private property; each is also a report reason.

### 5. Tribal lands, private property and people: guidance, not control

- We follow the law and the restrictions needed to safeguard the platform, but we don't control the contributor.
  Upload shows **guidance** (not advice): many tribal nations restrict photography (some villages ban it outright;
  commercial filming needs permits), private property needs permission, and people should consent to being shown.
- **Flags vs reports:** contributors tag their own content at upload (on tribal land, at a public event, with
  permission, private property, people shown). Readers report after publishing (spam, harassment, sacred or
  restricted site, private information, people shown without consent, private property, other).
- **Under review (later, with the nations):** holding posts that tag nation-listed restricted places before they
  publish, using rules from the nations' tourism and cultural-preservation offices and the Census tribal-area
  boundaries for detection.

### 6. Moderation: post first, respond to flags and reports

Journeys publish when posted. Reports go to Admin → Reports; staff hide or restore, and the contributor is told.
Automated checks (unsafe text, as for listings) can hold a post. A hidden journey stays visible to its author.

### 7. Age gate: 13 or older

Creating an account requires confirming **"I am 13 or older"** (checked on the server, not only in the form;
children's privacy law, COPPA). Existing accounts confirm once before commenting or becoming a contributor.

### 8. The six-tier discovery funnel

| Phase | Tiers | What it is in Traversence | Navigation |
|---|---|---|---|
| Macro discovery (top) | 1 Continent · 2 Regions (by state and curated boundaries) | North America cold start; regional hubs | Regions stories |
| Contextual exploration (middle) | 3 Geographic hubs (corridors, commerce overlays) · 4 Clusters (micro-commerce, associations) | geo-hubs (areas); micro-clusters | Destinations, National parks |
| Hyper-local conversion (bottom) | 5 Anchors (single home-rule town) · 6 Sub-groups | each cluster's anchor town; local names and community groups | Trip Planner |

- The hero (top 5 photos) and the feed (12 pieces) follow the level the visitor is at.
- **Still to build:** a by-state view (tier 2); corridor overlays from the NPS National Historic Trails, the National
  Scenic Byways and Census metro/micro areas (tier 3); associations (chambers, Main Street programs, historical
  societies: the crawler's Sources) that listings belong to (tier 4, with the Nexus, `decisions/0054`); the
  cluster vs anchor naming cleanup (tier 5); creator-curated itineraries sharing one route model with the Trip
  Planner (tier 5–6).
- **Creators:** documentary creators for tiers 1–2, regional micro-creators for 3–4, local insiders for 5–6. Paid or
  comped content carries a **Partnership** label (FTC disclosure).

### 9. Layout (Let's Explore)

Hero (top 5 photos for the level, credited, with arrows) · guide tabs (Regions, Destinations, National Parks) · the
feed (12, "More" loads the next 12) · right column: the Trip Planner (folded), Top Contributors, Join the Team. On
phones the Trip Planner is a tool icon and the right column follows the feed.

## Consequences

- New tables: contributors, journeys, journey media, likes, comments, views; report kinds for journeys and comments.
- Photos are stored on the site, resized (2000 px and a 640 px card size), re-encoded, which drops all metadata.
- Contributor terms (ownership stays with the creator; Traversence gets a display license; takedown) need legal text,
  drafted for counsel.

## Progress

- **2026-10-01, phase 1:** the age gate; Become a contributor; compose and edit a journey (photos stripped and
  resized, a video link, place, "Was here", flags, linked listings); the journey page (views, likes, public comments,
  reports); the Let's Explore magazine layout (hero, guide tabs, feed, Trip Planner panel, Top Contributors, Join the
  Team); staff post as Traversence or as themselves.
- **2026-10-01, amended by `decisions/0058`:**
  - accounts are for adults, 18 or older (it was 13);
  - contributors are people and listings (a member can post as a listing whose team they're on);
  - Traversence's voice is written by staff with the new Content Creator and Editor roles, from Admin → Journeys,
    which replaces "staff post as Traversence or as themselves" in the member editor.
