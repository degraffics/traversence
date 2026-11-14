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

Updated 2026-11-14 (Jason: "address how we recognize our failures and outline the specific fixes we are implementing to
ensure this wont happen again"; contact support@traversence.com; a quarter of the public limits; a 3-month cache). It
promises only what's built (the table under it says where each stands).

> **Subject: Traversence: our Overpass requests get 406. What we did wrong, what we've changed, and a question**
>
> Hello. I run Traversence (traversence.com), a local directory and travel guide for rural towns in Arizona and New
> Mexico. Our crawler uses the Overpass API to find the places in a town (cafés, garages, clinics, campgrounds) so we can
> check each one on its own website. Since 2026-11-13 our requests to overpass-api.de get 406 Not Acceptable. Our
> User-Agents were `TraversenceCrawler/1.0 (+https://traversence.com; local directory of community resources)` (the
> crawler, on Railway) and `Traversence/1.0 (+https://traversence.com)` (our site, when a visitor's search finds little
> nearby); both now end with our contact, `support@traversence.com`.
>
> **How we use OpenStreetMap.** It's a layer of its own on our site, always credited "© OpenStreetMap contributors" with
> a link to the copyright page. We don't copy its data into our listings: a place OpenStreetMap shows tells us where to
> look, and what we list comes from the business's own website or official records.
>
> **How we recognized our failures.** Our crawler's logs show every request to overpass-api.de answered 406 from
> 16:51 UTC on 2026-11-13, and again at 12:54 UTC on 2026-11-14. Reading your guidance, we saw that the fault was ours:
> - our queries were large: every kind of place in a town in one query, over a box around all its ZIP codes, with
>   `[timeout:110]`;
> - our crawler ran every 5 minutes and retried failed towns run after run, with no back-off;
> - when your server answered 406, our code asked again at once with the same query as a GET;
> - our site also asked overpass-api.de first, before any other instance;
> - nothing told us a server had refused us: the 406s were logged as ordinary failures, and nothing stopped.
>
> **What we've changed so it won't happen again.**
> - We've stopped asking overpass-api.de, from the crawler and from our site, until we hear from you. We won't change
>   our User-Agent or address to get round the block.
> - Each server has a circuit breaker. A 406, 403 or 401 stops every request to that server until a person on our team
>   marks it resolved. A 429, a timeout or a server error backs off (10 minutes, doubling to 6 hours) before trying again.
> - Each refusal opens a case for our team naming the server, the status and what it said.
> - While blocked, we check `/api/status` at most once a day, with the same User-Agent that was refused.
> - Our queries are split by kind (one kind of place per query), kept to a small area, with `[timeout:25]`, and sent
>   one at a time.
> - A town and kind that has been answered isn't asked again for 3 months.
> - Our own daily budget is a quarter of the published guidance for the public instances (2,500 queries and 250 MB a day,
>   crawler and site together), and half that while any server blocks us and for two weeks after.
>
> Could you tell us whether we're banned, and why, and what you'd like us to change to be allowed back? If our use is
> more than the public instance should carry, we'll stay on another instance or run our own.
>
> Jason, Traversence, support@traversence.com

**Where each fix stands** (all live 2026-11-14 14:21 UTC; the statement can be sent):

| Fix | Status |
|---|---|
| overpass-api.de off the site's lists (OpenPlaces, DeepLook) | live: Release #43 (`service-health_20261114`), verified Sync 13:59 UTC |
| A breaker per server; stop on 406/403/401/quota, back off on 429/timeouts/5xx; a Pulse case naming the issue | live: site Release #43; crawler worker 2.5 on Railway since 14:01 UTC (main 84ceba9). Holds across runs and the Pulse cases start once the site has run its database update (`service_issues`) |
| No GET retry after a 406; overpass-api.de off the crawler's list | live: worker 2.5 |
| One small query per kind, `[timeout:25]`, one at a time | live: worker 2.5; the site, one ask at a time (Release #43) |
| `/api/status` once a day with the refused agent | live; the first probe goes out with the first run after the database update |
| support@traversence.com in both User-Agents | live: site Release #43; crawler worker 2.5 with `CRAWLER_CONTACT` on Railway |
| A town and kind not asked again for 3 months | live: Release #44 (`service-health2_20261114`), verified Sync 14:21 UTC |
| The daily budget (2,500 queries / 250 MB, crawler 1,500, site 1,000; half during a ban and two weeks after), in Thresholds & gates | live: Release #44 (counted once its database update `osm_usage` runs); the worker keeps to 5 queries a run meanwhile |

**The published limits we build to** (verify against the OSM wiki's "Overpass API" page and FOSSGIS's terms before
relying on them): overpass-api.de, under about 10,000 queries and 1 GB of downloads a day per user, a few slots per
address (`/api/status`); 429 means no free slot (wait the time `/api/status` gives, never retry at once); 406 is a ban set
by hand on a User-Agent or Referer (stop, never change the agent; ignoring it leads to an IP ban); defaults
`[timeout:180]`, `[maxsize]` 512 MiB. Nominatim (we don't use it): at most 1 request a second, a real agent, cached, no
bulk geocoding. tile.openstreetmap.org (visitors' maps): attribution, a real Referer, no bulk or offline download (our map
code to check, below).

## 3. How to reach them

- **The OpenStreetMap Community Forum**, community.openstreetmap.org: post in **Help and support** with the tag
  **overpass**. That's where the maintainers answer 406 reports (threads "Overpass API error 406", "Getting 406 and 504
  errors with query attempts", "Blocked IP on Overpass API").
- **FOSSGIS**, which runs overpass-api.de: its OSM server terms of use page (fossgis.de, OSM server working group).
- Before posting, check the usage guidance on the OSM wiki's "Overpass API" page and the status page
  (wiki.openstreetmap.org/wiki/Overpass_API/status).

## 4. What we change either way (for the crawler session)

Built 2026-11-14 (the table in §2 says where each stands): per-kind queries one at a time, the breaker per server,
overpass-api.de off both lists, the daily probe, the contact in both User-Agents; next, the 3-month cache and the daily
budget. Still to check: our map code against the tile usage policy (attribution, a real Referer, no bulk download).
