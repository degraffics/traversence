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
