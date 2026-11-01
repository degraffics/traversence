# ADR 0138: The Home Page: Search First, Four Doors, Five Tabs, Honest When Thin

**Status:** Accepted (Jason, 2026-11-01), from his prototype ("Immersion & Connection Engine", Gemini) and his answers
the same day. Design; not built. Fills 0055's "not yet on the layout: the home page". The prototype is a layout sketch:
its wording, colors, icons and sample data are not carried over.

## 1. Branding

The site's own: cream background, brand brown `#92400E`, icons `#B45309` from the brand's flat set (`tv_icon()`), sage
`#8FBCA8` for what's active (`brand.md`, CLAUDE.md "Phone layout and visual standard"). The prototype's dark theme,
gold and Font Awesome icons are not used.

## 2. The search moves into the home page

- The hero holds **the** search: the same Quick Search (`TvSearch`) as every page, not a second search. What (words)
  and where (a place), with the Search button.
- **Where** is the visitor's place: the location picker, or set by autodetect (the crosshair), the same location scope
  the site keeps (`js/location-scope.js`). It fills itself for a returning visitor.
- The header's search is **hidden on the home page only**; every other page keeps it in the header.

## 3. Four doors: the site's four main sections

Under the search, one card each, opening that section's page (not filtering the home page):

| Door | Opens |
|---|---|
| **Let's Explore** | `/discovery/` |
| **Get Local** | `/directory/` |
| **Connect** (Community) | `/community/` |
| **Marketplace** | `/market/` |

The Community section is named **Connect** across the site (Jason, 2026-11-01; it was "Social": the header, its
menu, the section rail, search's sections and the FAQ; internal keys stay `social`, and the admin's Social 360° is
unchanged). Names, icons and one-line descriptions come from the section rails (`app/ui/section-rails.php`), so a renamed section
renames its door. Each card follows the card anatomy (0058 §11): the name is the link.

## 4. The category ribbon: five tabs over the home feed

Five tabs, no more (Jason): **Events**, **Deals**, **Gallery**, **Journey**, **Business**. Before a tab is chosen the
feed shows the newest of all five, mixed. On a phone the five sit in one row (scrolling sideways if needed). They are
section tabs over this page's feed, not filters, so the one-dropdown rule for filters (0058 §11) doesn't apply. Each tab
ends in "See all" to its full page.

| Tab | Shows, near the visitor's place | From |
|---|---|---|
| Events | coming events | `live_events` today; the `events` store (0134) when built |
| Deals | public offers businesses run | Promotions (discount codes a business makes public) |
| Gallery | public photos | Galleries (public albums only) |
| Journey | journeys and stories people made | journeys, stories (the tag store, 0132) |
| Business | businesses: newly added or claimed, and with something new | listings (Get Local) |

No paid or engagement ranking in any tab (0007, 0031): newest and nearest. Story cards open a **peek** (`TvAsk.peek`)
for a quick look; the full story is its own content page (hero, engage bar, comments; 0058 §20).

## 5. Thin sections: samples, invitations, never made-up activity

The site is thin until usage grows. Each section fills honestly:
- **Events:** real events first. With fewer than three, **sample** event cards fill in, each marked **Sample**, with
  Tickets greyed and the reason "Tickets: coming" until Ticket Sales are built (0134 §6a).
- **Places and things to see** may use sample cards marked as samples, and stock photos (section 6).
- **Never invented people or activity:** no made-up groups, members, member counts, reviews, likes or sales. Where a
  section is empty it **invites** instead, e.g. "Be the first: start a group for St. Johns" (the visitor's place),
  "Post the first item near Springerville", "Add your business".

## 6. A stock image library for preloads

A **Stock library** collection in Galleries' brand & documents mode (phase 3d), managed by our team:
- **Sources and licence:** public domain and US government first (Park Service, Forest Service, Library of Congress),
  then CC0 and Creative Commons (Wikimedia), then free-licence sites (Unsplash, Pexels) for general scenes. Each image
  keeps its source, licence, author and credit; the credit shows on hover and in the image's details. The licence
  decides the use (0133).
- **Tags:** subject (trail, café, festival, market…) and place, so a card picks a fitting, local image.
- **Honest use:** a stock image never stands in as a specific business's, person's or event's own photo. Where it shows
  a place, it's that place, or it's labelled "Representative photo".
- **A fallback only:** a card or hero uses stock only when there's no real image; the moment one exists (a business's
  gallery, a guide's hero, a member's post), it replaces the stock one, with nobody needing to swap it.
- **Nations' land (0066):** nothing showing sacred, burial or protected sites; held images follow `Landmarks::SENSITIVE`.

## 7. Also on the page

- **GO Code box:** type a GO Code (`trvgo.us/abc12` or just `abc12`) to jump to its page.
- Counts are counts only, never who (CLAUDE.md). The page passes the phone checks at 390px.

## Build order

1. The hero search on the home page (header search hidden there) and the four doors.
2. The ribbon with its five tabs and their feeds, the peek, and the invitations for empty sections.
3. The Stock library (its collection, sources and credits, the fallback in cards and heroes).
4. Sample event cards, marked, until real events fill them.

Each step adds its FAQ entries (0057).

## Step 1 built (2026-11-01): the search and the four doors

Delivered as `home-step1_20261101.zip` (3 files, built on the 20:17 UTC sync): `website/index.php`, `app/ui/header.php`,
`app/ui/faq.md`. Live once uploaded; no database change.
- **The search in the hero:** `header.php` captures its search bar as `$tv_search_bar` and leaves it out of the header
  on `/` and `/index.php` only (`$tv_home`); the home page prints it under the title, so it's the same Quick Search
  (`js/search-panel.js` binds to it as before). Under it, **Where:** the place the header's menu shows, with **Change**
  (the location picker) and **Use my location** (auto-detect).
- **Four doors:** Let's Explore, Get Local, Connect, Marketplace, their names, links and icons taken from the header's
  funnels (`$tv_funnels`, `$tv_i`), so a renamed section renames its door; a one-line description each (kept in
  `index.php`, as the rails have no section-level line). A section not yet live shows "Coming soon".
- **Have a GO Code?:** `trvgo.us/abc12`, a full link or just `abc12` opens `https://trvgo.us/<code>`; anything else is
  answered in place (`TvAsk.say`).
- **Removed:** the old landing's sample stories ("Maria's Bakery" and the heritage trail, which had no pages) and the
  Quick Feature Links (mostly "soon"), per §5; the directory counts stay.
- **Checked** locally at 390px and 1280px: one search on the home page and still one in the header elsewhere, no
  sideways scroll, the search panel opens from the hero, no script errors from the page.
- **Next:** step 2, the five-tab ribbon (§4) with its invitations; then the Stock library (§6) and sample events (§5).

## The full layout built (2026-11-01, after Jason's review of step 1)

Jason, on step 1: "this doesn't look close to what I asked for". The home page now follows the prototype's layout in our
branding, in one build: `home-full_20261101.zip` (2 files, built on the 20:45 UTC sync): `website/index.php`,
`app/ui/faq.md` (`header.php` from step 1 is unchanged).
- **Hero band:** brand dark brown with a dot texture, a small section badge, the title with its accents, a line on what
  the site is, the one search, Where (Change, Use my location), and "New here?" to the FAQ.
- **Four ways in:** tall picture-style doors, one brand tone each, with the section's icon large behind; the whole card
  opens the section. (Photos come with the Stock library, §6.)
- **Groups to join (Connect):** up to three real groups (`Community::directory`), member counts real; with fewer than
  three, "Be the first: start a group for <your town>" (the place the visitor chose).
- **Coming up:** real events (`live_events`, status live, from today); topped up to three with cards marked **Sample**;
  "Tickets: coming" greyed on every card.
- **What's new:** the five tabs over one feed, newest first: Events (`live_events`), Deals (none yet: an invitation),
  Gallery (public albums with a cover), Journey (`Journeys::feed`: journeys and guides, with a Peek button), Business
  (owner-run listings, newest). Nine shown, "Show more" for the rest; a tab shows only its kind (tapped again, all);
  an empty tab invites; an empty feed shows one invitation.
- The GO Code box and the directory counts follow.
- **Checked** locally at 390px and 1280px with no database (every section falls back to its invitation), tabs and
  invitations, no sideways scroll, no script errors. The real data runs on the live site.
- **Still to come:** the Stock library (§6) for photos on doors and cards, business deals, and "nearest first" by the
  visitor's place (today: newest across the region).

## Jason's changes to the full layout (2026-11-01)

`home-v3_20261101.zip` (2 files, built on the 20:54 UTC sync): `website/index.php`, `app/ui/faq.md`.
- **Split search** (the prototype's): **Discover more** (what) and **Let's get local** (where, with the target button to
  use the visitor's location), and the search button. It opens the one search panel (`TvSearch.open`, the header's bar
  kept hidden on the page so the panel runs): "<what> near <where>" when a new place is typed, the words alone when the
  place is the one already chosen. Replaces the Where / Change line.
- **First-time visitors line** as in the prototype: "First-time visitors: Click here to learn more about our discovery
  portal, local directory, community hub, and marketplace", each linking to its FAQ section.
- **Removed:** the section badge above the title and the "Where to start / Four ways in" and "What's on / Coming up"
  headings (the four doors and the events row stay).
- **Community Groups** in the prototype's layout (our colors): "Traversence Connect" over the title, **Explore all
  groups** top right; each card: what kind top left, a badge top right, the name, what it's about, members and
  **Join group**. Real groups first; then **anchors that need groups** to make six: the town's neighbors, outdoors,
  local history, families, volunteers, business owners (a topic already running isn't suggested), each "Needs a group"
  with **Start this group** (`/community/?topic=…#cg-new`).

## The split search done right (2026-11-01)

Jason, on v3 ("this is bad design"): typing in Discover more opened the search over the whole page with no box showing
the words, and asked "Where are you?" with the where box right there. Cause: the hero drove a hidden copy of the
header's bar, and the panel hangs from its bar. `home-v4_20261101.zip` (built on the 21:04 UTC sync):
- **The split box is the search itself** (`id="tv-search"`, Discover more is `.tvs-in`): results hang right under it as
  you type, the words staying in view and editable; it sits above the search's dimming on desktop, as the header's bar
  does. The search's own place wording doesn't replace "Discover more".
- **Let's get local opens the place picker** (a town, ZIP, area or region; the target auto-detects) and then shows the
  place chosen, so the search searches there; no free-text "near" guessing.
- **Phones:** a tap on Discover more opens the full-screen search at once with its own field, so no letter is lost.
- Rule for later home-page work: a search box that opens the site's search must be that search's own bar
  (`#tv-search`), never a second box driving a hidden one.
- **One piece** (v5, `home-v5_20261101.zip`, `index.php` only, built on the 21:24 UTC sync): Jason, "janky… the search
  needs to fit better and flow as attached to the search bar". The box is as wide as the results (1040px at most);
  when the results open they take the box's left edge and width, the box's bottom corners square off, and both share
  one surface and shadow (`.hm-sopen`). The place in Let's get local shows its name only.
