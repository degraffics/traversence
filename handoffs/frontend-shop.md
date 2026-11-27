# Frontend & Shop build session (Jan 1 launch): its record

The session building the launch plan's streams C (visitor front end), F (Shop and money, code) and G (search)
(`research/launch-plan-jan1-2026-11-26.md` on `claude/launch-plan-jan1`; decisions/0181). Kept current as the record,
not as a hand-over (builder playbook §5).

## Releases, in order

| # | Release | Source | State |
|---|---|---|---|
| 1 | **G.1 the search lock's leaks** (`search-lock.zip`) | 0181 §4 | Built on Sync 2026-11-27 01:35 UTC; zip check clean; sent to the session manager for its order OK and to the Design reviewer |
| 2 | **C.1 the device's place read as "outside"** (`device-place.zip`, build in `c1-device-place/`) | plan C.1; CLAUDE.md "Places in search", 0060 | Built on Sync 01:35 UTC, tested; waits for G.1 to go live, then rebuilt on the newest sync |
| 3 | C.2 the path checker: sign-in selector (`paths.mjs`) and the staff two-step | plan C.2; 0145 | Queued |
| 4 | C.3 new 0145 paths: each 1A town; the Shop, plan-buy, Marketplace-buy in the sandbox | 0181 §1.2; 0145 §2 | Queued |
| 5+ | The rest of C and F in the plan's order | plan §4 C, F | Queued |

## G.1: the search lock (search-lock.zip)

Built on Sync 2026-11-27 01:35 UTC (`7a839cb`). Files: `app/ui/search-lock.php` (new), `app/ui/security-headers.php`,
`app/ui/storefront-view.php`, `app/ui/faq.md`, `website/index.php`, `login.php`, `register.php`, `forgot-password.php`,
`directory/classic.php`, `robots.txt`, `sitemap.php`.

Each behaviour and its source:
- One switch, `TV_SEARCH_LOCK` (true) in `app/ui/search-lock.php`, side-effect free so the trvgo.us router can load it.
  Lifting is one release setting it false (0181 §4: "It's one release").
- Every page and endpoint sends `X-Robots-Tag: noindex` (security-headers.php, loaded by bootstrap and by the pages that
  don't load it) (0181 §4: "the site isn't indexed").
- The home page, login, register, forgot-password and the classic directory print `<meta name="robots" content="noindex">`
  via `tv_robots_meta()` (0181 §4's named pages).
- Storefronts: `noindex` instead of `all`; a team's preview stays `noindex, nofollow`; a closed shop was already noindex
  (0181 §4: "storefronts (today X-Robots-Tag: all)").
- Sitemap: an empty `sitemapindex` with a comment while locked (0181 §4: "the sitemap … say the same").
- robots.txt: a comment saying the site isn't open yet; no `Disallow: /`, since a crawler barred from a page never sees
  its noindex and can still list its address (build choice, flagged to the session manager).
- FAQ "Can search engines find Traversence?": Not yet, and what stays out after the lift (0181 §4 "Pages held after
  the lift"). The anchor is unchanged (`grep faq.php#can-search-engines` finds no links).
- Not changed: `page-shell.php:51`'s fixed noindex (G.2 makes it per page; G.4 lifts).

Tested on the harness (MariaDB, the mirror with the zip laid over): each named page answers `X-Robots-Tag: noindex` and
carries the meta; register.php redirects to `login.php?tab=create`, also noindex; a seeded storefront answers 200 with
`noindex` (the plan check stubbed in the test copy only); the sitemap is empty; with the switch false, the storefront
answers `all`, no header or meta is sent, and the sitemap lists its parts as before. Screens at 1280 and 390 in
`search-lock/screens/`: no sideways scroll, no script errors, pages unchanged but for the FAQ answer.

Admin's test: nothing new to press. Jason sees the FAQ's answer say "Not yet"; a search engine sees noindex everywhere.

## C.1: the device's place (device-place.zip)

`UniversalSearch::loosePlace` (the place from Auto-detect, or a town or ZIP set with no area named) returned
`'outside' => true` always. It now checks the place's ZIPs, or for a bare point the nearest town's
(`Landmarks::nearestZip`), with `clusterForZips` (the check `placeNamed` and `countyNamed` already use) and, inside an
area, returns the shape `placeFromScope` gives a town chosen in its area (`'sub' => 'Your place'`, the cluster's key,
level, slug and url, `town`, `area`), with `'outside' => false`. Outside every area: unchanged. FAQ: one sentence added
to "Does Auto-detect share where I am?".

Tests: `tools/search-tests/device-place.php` 7/7 (Show Low by city, ZIP, an area ZIP (Pinetop), a bare point; Seattle
and a point at sea stay outside; an unknown slug falls through). The whole `UniversalSearch::search` on the harness:
before, "pizza" with Show Low set gave the place chip `out=true`; after, `out=false`; Seattle `out=true` both. The
effect on the live notes ("did you mean", "We have no listings in …") is checked on live after it publishes, with the
search probe.
