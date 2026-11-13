# OpenStreetMap: is Overpass blocking us, and a statement for the OSM community (2026-11-13)

For Jason (his ask, 21:20 UTC: "a written statement and how to reach OpenStreetMap community … also need to verify this is
actually blocked and why").

## 1. What's blocked, with the evidence

**Only one service, and only one server of it:** the Overpass API at `overpass-api.de` (the main public instance, run
by FOSSGIS). Nothing shows a block on OpenStreetMap's own API, Nominatim or the map tiles; we don't call the API or
Nominatim at all.

| What we call | From | User-Agent we send | What it answers |
|---|---|---|---|
| `overpass-api.de/api/interpreter`, POST form `data=` | the crawler (Railway) | `TraversenceCrawler/1.0 (+https://traversence.com; local directory of community resources)`, `Accept: */*` | **406 Not Acceptable** (nginx's HTML page), every time since at least 16:51 UTC today, and the GET fallback the same |
| `overpass.private.coffee` (Railway's `OVERPASS_URL`), `overpass.kumi.systems` | the crawler | the same | time out (no answer in the time left), not refused |
| `maps.mail.ru` Overpass | the crawler | the same | 504 |
| `overpass.openstreetmap.fr` | the crawler | the same | 403 "only available to white-listed usages" (removed from our list) |
| `overpass-api.de` | the website (Bluehost), when a search finds little nearby (`OpenPlaces::near`) and the deep look | `Traversence/1.0 (+https://traversence.com)` | not known: the site's own log isn't in the mirror |
| `tile.openstreetmap.org` | visitors' browsers (our maps) | each browser's own | no sign of a problem |

Source: the crawler's Railway logs (deployments 2026-11-13 16:00–22:10 UTC), e.g. `overpass-api.de GET <HTTPError 406:
'Not Acceptable'> | overpass-api.de 406 b'<html>…<h1>406 Not Acceptable</h1>…'`.

**Why, from what its maintainers say:** on overpass-api.de a 406 is a ban set by hand on a User-Agent or Referer; one that
keeps getting 406s and keeps asking is banned by IP next. Only the maintainers can lift it, and they ask to be contacted.
Bans have mostly been for heavy or badly behaved clients (repeated large queries, retries without waiting).

**What we did that would draw one:** until today's revamp (decisions/0160), the crawler ran a fixed order every 5 minutes
and retried failed town jobs run after run, each sending large queries (every kind of business in a town in one query,
`[timeout:110]`, up to 120 results), first to overpass-api.de, then the mirrors. Since worker 2.1 (18:29 UTC) a lookup
that doesn't answer in the task's time is handed back, not retried at once.

**Still to confirm (one step):** whether it's our crawler's User-Agent or Railway's address. One test request from a
different network with our exact User-Agent tells: 406 there too means the agent is banned. This session couldn't make it
(its network doesn't reach overpass-api.de; allowing it needs Jason's OK in the session's network settings). Never get
round a ban by changing the agent: that's what turns it into an IP ban.

**The test, 2026-11-13 23:12 UTC (Jason: "allow the OSM test"):** from a Claude cloud session (a cloud provider's
network), one small query with each of our two User-Agents. Both connections were reset by overpass-api.de during the TLS
handshake, before any request was sent (the session proxy: "tunnel closed after 2s; 517 B sent, 39 B received"), so the
server refused that network, not our name. That fits the maintainers' blocks of whole cloud networks, and it can't tell
whether our User-Agent is banned. Railway is different: it gets through the handshake and is refused with 406 after the
request, which is where a User-Agent or Referer ban acts.

**Two ways to settle it:**
- From a home or office connection (not a cloud), one request with the crawler's exact User-Agent:
  `curl -s -o /dev/null -w "%{http_code}\n" -A "TraversenceCrawler/1.0 (+https://traversence.com; local directory of community resources)" "https://overpass-api.de/api/status"`.
  406 means the name is banned; 200 means it's Railway's address.
- Or the crawler asks `https://overpass-api.de/api/status` once from Railway and logs the answer (the status page names
  the caller's address and its rate-limit slots). That's a small worker change for the next session.

## 2. The statement (to send as it is, or adapt)

> **Subject: Traversence: our Overpass requests get 406, can we fix how we use it?**
>
> Hello. I run Traversence (traversence.com), a local directory and travel guide for rural towns in Arizona and New
> Mexico. Our crawler uses the Overpass API to find the places in a town (cafés, garages, clinics, campgrounds) so we can
> check each one on its own website. Since today our requests to overpass-api.de get 406 Not Acceptable. Our User-Agents
> are `TraversenceCrawler/1.0 (+https://traversence.com; local directory of community resources)` (the crawler) and
> `Traversence/1.0 (+https://traversence.com)` (our site, when a visitor's search finds little nearby).
>
> How we use OpenStreetMap: it's a layer of its own on our site, always credited "© OpenStreetMap contributors" with a
> link to the copyright page. We don't copy its data into our own listings: a place OpenStreetMap shows tells us where to
> look, and what we list comes from the business's own website or official records.
>
> Until today our crawler sent large queries (all of a town's kinds at once) and retried failures too often. We've
> changed it so a slow query is put back for later instead of retried. We'll also:
> - split our queries by kind and keep each to a small area, with a short server timeout;
> - send at most one query at a time, and stay well under the published limits for the public instance;
> - cache what a query returns and not ask again for the same town and kind for a month;
> - add a contact address to our User-Agent.
>
> Could you tell us whether we're banned and why, and what you'd like us to change to be allowed back? If our use is
> more than the public instance should carry, we'll move to another instance or run our own.
>
> Jason, Traversence, [contact email]

## 3. How to reach them

- **The OpenStreetMap Community Forum**, community.openstreetmap.org: post in **Help and support** with the tag
  **overpass**. That's where the maintainers answer 406 reports (threads "Overpass API error 406", "Getting 406 and 504
  errors with query attempts", "Blocked IP on Overpass API").
- **FOSSGIS**, which runs overpass-api.de: its OSM server terms of use page (fossgis.de, OSM server working group).
- Before posting, check the usage guidance on the OSM wiki's "Overpass API" page and the status page
  (wiki.openstreetmap.org/wiki/Overpass_API/status).

## 4. What we change either way (for the crawler session)

The four promises above, built before any request goes back to overpass-api.de: per-kind queries, one at a time, a month's
cache per town and kind (`town_crawl_jobs` / `crawl_jobs` already record when a kind was done), and a contact in both
User-Agents. Until the maintainers answer, take overpass-api.de off the crawler's list (one line in `OVERPASS_URLS`; not done yet).
