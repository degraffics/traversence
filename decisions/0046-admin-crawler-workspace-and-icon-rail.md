# ADR 0046: Admin → Crawler Workspace and Icon-Rail Navigation

**Status:** Accepted (2026-09-29). Reorganizes the admin pages built for `decisions/0042`, `0044` and `0045`.

## Context

The crawler work of `decisions/0042`–`0045` grew one admin page at a time: Cluster tools (population loads,
the one-time merge, clusters, coverage and the crawl queue), Auto-imports (what published, plus a quick-
approve list), Listing Intake (crawl a website, full edit, merge, reject) and Sources. Each worked, but
finishing one piece of work meant hopping between four pages ("populate here, accept over here"), the
admin portal was a grid of large tiles, and several pages overflowed a phone screen, where most of the
reviewing actually happens.

Direction (Jason, 2026-09-29): this is crawler work, so **Admin → Crawler** is where the website's data
infrastructure is managed; tools belong in an **icon rail**, not big boxes; on a phone the rail runs across
the top of the content and swipes sideways when it's longer than the screen; icons use the main nav's states,
flat single color at rest and a glow when active.

## Decision

**One Crawler workspace, one icon rail, one list of decisions.**

- **Admin → Crawler** (`includes/admin-crawler.php`, `tv_crawler_start()` / `tv_crawler_end()`) frames every
  page about the site's data. Its rail, in order:

  | Icon | Page | For |
  |---|---|---|
  | activity line | **Overview** (`admin/crawler.php`) | worker health, crawl-queue progress, what published, what's waiting |
  | inbox (with a count) | **Review** (`admin/crawler-review.php`) | everything that needs a person, in one list |
  | check circle | **Published** (`admin/auto-imports.php`) | what the crawler published on its own; Pull from site |
  | globe | **Sources** (`admin/sources.php`) | websites that confirm places (`decisions/0045`) |
  | map | **Places** (`cluster-tools.php?view=places`) | geo-hubs, clusters, ZIPs, anchors, local names |
  | target | **Coverage** (`cluster-tools.php?view=coverage`) | gaps and the crawl queue |
  | bug | **Crawl a site** (`admin/listing-intake.php`) | point the crawler at one website; full edit of one listing |
  | database | **Data loads** (`cluster-tools.php?view=data`) | Census populations and place profiles |

- **Review is the one place to decide.** Listings to approve, possible duplicates to merge, sources to sort,
  suggested local names, and clusters still marked "needs review" arrive as compact cards, each with its usual
  action on the card (Approve / Reject; Compare & merge; Read regularly / Cite only / Ignore; Accept / Not a
  local name; Looks right). **Edit** opens just that listing's full form. Filters by kind and by place. After
  an action the page reloads where you were, with a short confirmation.
- **Icon states match the main nav:** flat gold (`#B8863B`) line icons at rest; the current page's icon turns
  green (`green-500`) with a soft glow. Line icons, one color, drawn to the brand palette.
- **Layout:** a sticky column on the left on wider screens; on a phone, a single row across the top of the
  content that scrolls sideways (with a fade hint when more icons are off-screen) and starts scrolled to the
  current page. Pages never scroll sideways as a whole; wide tables scroll inside their own box or stack.
- The admin portal's four crawler tiles become one **Crawler** tile.

## Consequences

- Nothing moved on disk that other links depend on: the existing pages keep their URLs and gained the frame.
  Cluster tools serves three rail pages by `?view=`. Old bookmarks still work.
- Quick approve moved from Auto-imports into Review; its POST still works for any open tab.
- "Queue these gaps" now also retries failed crawl jobs whose gap is still there.
- Open: whether the rest of admin (Listings, Claims, Categories, Users, Site settings) adopts the same rail,
  with Crawler as one section (recommended, for one consistent admin); and whether the phone row starts at
  the right edge (Jason mentioned "right to left") or the left (built: left, easy to flip).
