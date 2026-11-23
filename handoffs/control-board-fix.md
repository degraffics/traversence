# Fix after #258: the false /api/0 trip, plain endpoint names, money in dollars

2026-11-23, the Control Board build session. `control-board-fix.zip`, 4 site files, built on Sync 2026-11-23 00:15 UTC
(`791f665`, #258 live). Found live from Jason's screenshots.

| Defect (where seen) | Cause | Fix | Source |
|---|---|---|---|
| A trip "SYSTEM ALARM NOTICE: /api/0: Down…" in Pulse (case #54, 00:15 UTC) | The worker reports `site_blocked` as a count (`crawler.py` `SITE_BLOCKED`); #258 read it as a map of endpoints, so the count `0` became "/api/0" on every run report | A count over 0 is one refusal on the crawler's endpoints (`/api/crawl/`); a map is read by its named endpoints only | 02 row 23; g1 rows 25–26 |
| That trip stays open (its check calls a page that doesn't exist) | — | Closed on its thread as "Closed: raised in error: …", never Recovered (`ControlAlarms::raisedInError`, as Retire's close, 02 row 116); no push | principle 4 (words follow the state) |
| A close that isn't a recovery read "Recovered: Closed: …" (Retire too) | `noticeBody`'s recovered state prefixed the action | No "Recovered" prefix on a "Closed:" story | principle 4 |
| "Holds: ep:crawl" for an endpoint with no register row | `partName` had no case for `ep:` | Its path, "/api/crawl/" | 02 §5.4 |
| "Railway 469,266, 65.2¢" read as $62 (Jason: "Railway=$62 WHAT?", "Cents vs dollars") | Amounts under $1 shown in cents; the count had no unit | Money always in dollars ("$0.65"; under a cent to 4 places), counts as "469,266 calls" (Insights → Crawler and AI Assist) | Jason, 2026-11-23 |

**Proof (bench: the live mirror with the fix):** the scenario test 135/135, 0 fail, with S-B23b (the worker's own shape:
0 raises nothing, 3 raises one refusal on /api/crawl/, a map read by named endpoints only) and S-B23c (the /api/0 trip
closes as raised in error, its Pulse item "…: Closed", no push, once); workflows, r3b, routes and critical pass; lines.php's
one failure is the same on live code without the fix. zipcheck clean.

**Admin's test:** the /api/0 alarm leaves the board; its Pulse item reads "SYSTEM ALARM NOTICE: /api/0/: Closed" with
"Closed: raised in error: …"; spend lines read in dollars.

**Next, its own item:** Insights → Crawler's task table clipped on the left in the case's side panel at phone width
(Jason's third screenshot); and W0's Railway cost from Railway's own usage (this month ≈ $1.37 so far, Railway's
estimate ≈ $3), not the board's per-call estimate.
