# Fix #261: each service's monthly count in its own unit

2026-11-23, the Control Board build session. `spend-units.zip`, 1 file (`app/admin/crawler-console.php`), built on Sync
2026-11-23 01:52 UTC (`4a94342`, #262 live).

#261 labelled every count "calls". Railway records the seconds its services run (`Frontier.php`: W0 always on;
`CrawlService::report`: a run's own seconds), so W0's line read "Railway 469,266 calls, $0.65": it is 130.4 hours running.
Now `tv_cr_units()`: Railway in hours running, web searches as searches, Google Places as checks, Tavily's extracts as pages,
anything else (Claude) as calls. Source: principle 4 (words follow the state); Jason, 2026-11-23 ("Railway=$62 WHAT?").

**Proof:** `crawler-phone.cjs` 12/12 on the fix (a new check: "Railway 130.4 h running, $0.65", never calls or cents); on
today's live code it fails on "Railway 469,266 calls, $0.65". zipcheck clean.

**Admin's test:** Insights → Crawler's spend lines read "This month: Railway 130.4 h running, $0.65 · …".

**(b), for the session manager's queue, with a recommendation:** the board's W0 cost is an estimate (hours × a set price,
$0.65 so far), Railway's own usage for W0 is about $1.31 (read 2026-11-23 from Railway's API: memory is most of it).
Taking the real figure needs `RAILWAY_API_TOKEN` in the site's .env (it's the key Watching deploys already names; not set
today). Recommendation: when that key is set, read W0's and the crawler's usage from Railway once a day (the same GraphQL
call, `usage` by service) and show it beside the estimate; until then, the line says it's an estimate (it does).
