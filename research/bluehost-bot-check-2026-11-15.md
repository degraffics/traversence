# Bluehost's bot check (HTTP 409 `humans_`): which callers it hits (2026-11-15)

Asked by Jason through the session manager ("start 3"). Read-only: no code changed, nothing sent or switched.

**What it is.** Bluehost answers some requests to traversence.com with `HTTP 409` and the body
`<script>document.cookie = "humans_21909=1"; document.location.reload(true)</script>`. A browser runs the script, gets the
cookie and reloads without the visitor noticing. Anything that isn't a browser (a worker, a webhook sender, a link-preview
fetcher) can't run it, so it just gets a 409. **The 409 comes before PHP runs:** our code never sees the request, so it
logs nothing, and a valid token doesn't help.

## How it was tested

- Endpoints came from the live mirror (`degraffics/traverse-3way-sync` at `259a50c`, `sync-storage/`, `sync-app/lib/`)
  and from the workers in this repo (`workers/crawler`, `workers/video`, `workers/storefront-edge`).
- Each endpoint got **10 harmless requests**: a GET, or a POST with an empty `{}` body and no token or signature, so
  nothing could be accepted. The list was walked once per round, 1.2 s between requests (under 1 a second overall), for
  280 requests in all, from the Claude cloud on 2026-11-15 around 17:30 UTC.
- The default user agent was `traversence-bot-check/1.0`. Public pages were also fetched as Facebook's link-preview bot
  (`facebookexternalhit/1.1`) and as Googlebot. Only the user agent was changed; nothing else imitated those services.
- A response counted as blocked only when it was a 409 **and** its body had `humans_`. Every 409 we got had that body.
  Every other answer was the endpoint's own reply: 401 (no token), 400 (bad signature), 405 (wrong method) and so on.

**Result: 28 of 280 requests (10%) got the bot check** (plus 4 of 60 in a follow-up run on staging.trvgo.us and trvgo.us). The rate was about 13% on API endpoints. The 1 in 3 seen earlier
on `usage.php` looks like a bad stretch, not the usual rate. Blocked requests landed at random, on GET and POST, on every
kind of endpoint. Nothing about the path or method protected an endpoint. Endpoints with 0 out of 10 are most likely just
luck.

## Results

| Endpoint | Who calls it | Sent | 409s | Effect if blocked |
|---|---|---|---|---|
| `POST /api/crawl/results.php` | Railway crawler worker | 10 | 3 | `site_call()` raises `site … said 409`; the task's finds are lost for that run (redone later only if the job goes back in the queue) |
| `GET /api/crawl/targets.php` | crawler | 10 | 3 | the run gets no work and finds nothing |
| `GET /api/crawl/jobs.php` | crawler | 10 | 1 | same |
| `GET /api/crawl/usage.php` | crawler | 10 | 1 | the worker can't read its budget; the run stops |
| other `/api/crawl/*` (sources, service, learn, identity, npi, irs, landmarks, refresh, rec, guides, verify, geocode, heritage, places, commoncrawl, loads) | crawler | not sent (same folder, same server rule) | expect ~10–13% | the same as above: one failed call fails its task |
| `GET /api/video/jobs.php` | Railway video worker | 10 | 0 | no videos picked up that run (it tries again next run) |
| `POST /api/video/results.php` | video worker | 10 | 2 | `api()` prints the error and returns `None`: **the finished copies are uploaded but the site never hears about them**; the site gives the job out again, so the work is done twice |
| `POST /api/qb-webhook.php` | QuickBooks (Intuit) | 10 | 1 | a payment, refund or credit memo notice is missed. Intuit retries, and the buyer's own page and "Check QuickBooks" also catch it, so it's **late, not lost** |
| `POST /api/market-webhook.php?p=stripe` | Stripe (Connect) | 10 | 3 | Stripe retries for up to 3 days and the daily reconcile catches the rest: late, not lost. Stripe emails about failing endpoints and may turn one off if it keeps failing |
| `POST /api/market-webhook.php?p=square` | Square | 10 | 3 | Square retries for a limited time; the daily reconcile catches the rest |
| `POST /api/ses-events.php` | Amazon SES through SNS | 10 | 2 | **bounces and complaints can be lost for good.** SNS's default HTTPS delivery retries only a few times, over seconds, so an address that bounced keeps getting mail, which hurts SES sending reputation. The subscription confirmation can also be missed |
| `GET /api/health.php` | GitHub Actions (site audit, `paths.mjs`) | 10 | 3 | that day's health report fails: `server.json` (PHP version for R04), the jobs list and the crawler snapshot go stale |
| `GET /api/guide-paths.php` | GitHub Actions (`paths.mjs`) | 10 | 1 | the guides fall back to Discovery's links, with only a `::notice::` |
| `GET /api/search.php` | GitHub Actions (search probe, scorecard, layout probe) | 10 | 0 | the probes call `.json()` on the answer, so a 409 throws: that probe fails or scores 0 and **looks like a search fault** |
| `GET /api/csp-report.php` (browsers POST reports to it) | the visitor's browser (Reporting API, no script runs) | 10 | 2 | about 1 in 10 CSP reports is dropped. Low impact: the reports are counts only |
| `GET /api/vault-ext.php` | Vault browser extension | 10 | 0 | the extension's `fetch` can't run the script; a blocked call fails for staff (not seen in this run) |
| `GET /api/auth/google.php` (OAuth returns) | the visitor's browser, redirected back | 10 | 0 | a browser passes the check by itself; the token exchange runs from our server outward, so it isn't affected |
| `GET /data-deletion.php` | Facebook's app review (the data deletion page) | 10 | 0 | the page fails Facebook's check now and then |
| `GET /` as Facebook preview | Facebook, Messenger, WhatsApp, iMessage, Slack… | 10 | 0 | — |
| `GET /faq.php` as Facebook preview | the same | 10 | 3 | **a shared link shows no title or picture.** Facebook keeps that empty card until someone asks it to fetch the page again |
| `GET /` as Googlebot | Google search | 10 | 0 | blocked crawls slow down indexing; none blocked in this run |
| `GET /` as a plain client | — | 10 | 0 | — |
| `GET /robots.txt` (plain, Googlebot) | search engines | 20 | 0 | **answers 404 every time: there is no robots.txt** (see below) |
| `GET /sitemap.xml` | search engines | 10 | 0 | **answers 404: there is no sitemap** |
| `https://trvgo.us/`, `/hours` (plain and Facebook preview) | storefront edge (Fly.io → Caddy `reverse_proxy https://trvgo.us`), GO codes, shared short links | 30 | 0 | none blocked in 30 (follow-up run, after trvgo.us was allowed in this session's network): `/` answers 302, `/hours` 404. trvgo.us may not have the check, or 30 requests was too few to catch it; ask Bluehost to confirm it's exempt. If it ever gets the check, the edge passes the 409 page on to the shopper's browser, which probably gets through after one reload |
| `POST https://staging.trvgo.us/api/staging-updates.php`, `GET /api/stg-probe.php`, `/` | the live server (Releases: staging migrations, release checks via `Releases::fetch`, `StagingSite` probes) | 30 | 4 (1 on staging-updates, 3 on stg-probe, 0 on `/`) | **the check runs before staging's viewing password** (the other answers were 401). These are curl calls with no cookie. A 409 shows as "Staging didn't run them (HTTP 409)" or a failed check, so a release stops, loudly but with the wrong reason. Our curl calls here came from the Claude cloud, not from the server, so whether Bluehost checks its own server's calls is still open |

Not affected, because the call goes from our server out: the GitHub inbox pickup (`DocsRepo`, `AuditClock`: our server
reads raw.githubusercontent.com and api.github.com), the OAuth token exchanges, QuickBooks and Stripe API calls, and the
sync (FTP).

## Already failing without anyone seeing it

1. **SES bounces and complaints** (`/api/ses-events.php`). SNS gives up quickly, so a blocked bounce is never sent again.
   This is the one place where information is lost for good. Check `email_events` against the SES console's bounce count.
2. **Video results** (`/api/video/results.php`). The work is done twice and the copies are stored again. Railway's logs
   will show `HTTP 409`.
3. **Crawler tasks**. About 1 site call in 8 fails. A run makes many calls, so most runs likely have a failed task. Railway's
   logs show `said 409: b'<script>document.cookie = "humans_…'`.
4. **Link previews**. About 3 in 10 preview fetches of a page get no card. Facebook keeps the empty card until someone asks
   it to fetch again (Sharing Debugger).
5. **The daily checks** from GitHub (search probe, scorecard, health report). A 409 looks like a site fault, so findings
   and Pulse cases can be false.
6. Payment webhooks (QuickBooks, Stripe, Square) are **only delayed**: the senders retry and our reconciles catch the
   rest. Keep the reconciles in place until the exemption is confirmed.

## What to ask Bluehost to exempt

Ask them to turn the `humans_` JavaScript check off for these paths on **traversence.com** (and the same on
**staging.trvgo.us** and **trvgo.us**). We can't do it with `.htaccess`: the check runs before our rules.

- `/api/crawl/*`
- `/api/video/*`
- `/api/qb-webhook.php`
- `/api/market-webhook.php`
- `/api/ses-events.php`
- `/api/health.php`, `/api/guide-paths.php`
- `/api/csp-report.php`, `/api/stg-csp.php`
- `/api/vault-ext.php`, `/api/vault-lock.php`
- `/api/staging-updates.php`, `/api/stg-probe.php` (staging.trvgo.us)
- `/robots.txt`, `/sitemap.xml` (once they exist)
- **trvgo.us as a whole**, or at least requests carrying `X-TV-Edge`, since the storefront edge is one machine calling
  for many visitors
- And for **link-preview and search crawlers on public pages**: Bluehost should let through `facebookexternalhit`,
  `Facebot`, `Twitterbot`, `LinkedInBot`, `Slackbot`, `WhatsApp`, `Googlebot` and `bingbot`, ideally checking their
  published address ranges. If they won't, ask for the check to be off on the public pages too.

If Bluehost won't exempt paths, the fallback is a retry in our own callers: the crawler, the video worker and the GitHub
checks treat a 409 with `humans_` as "try again in a few seconds, up to 3 times". The worker could also send the
`humans_21909=1` cookie, though that relies on how Bluehost's check works today and the number may change. Neither helps
with SNS or link previews: only an exemption fixes those.

## Also found

- **No `robots.txt` and no `sitemap.xml`** on traversence.com (404 to everyone). Search engines get no sitemap and no
  rules (staging is kept out by `noindex` and its password, not by robots). This deserves its own decision and release.
- trvgo.us and staging.trvgo.us were tested in a second run, once Jason allowed them in this session's environment.

## After Bluehost's change (rerun, 2026-11-15, ~18:10–18:34 UTC)

Jason asked Bluehost to turn the check off for `/api/`, `/admin/api/` and `/data-deletion.php` on traversence.com,
`/api/` on staging.trvgo.us, all of trvgo.us, and link-preview and search bots on public pages. Bluehost said it was
done. The same test was run again: 320 requests, 10 per endpoint (20 for the staging and trvgo.us endpoints, which
appeared twice in the list).

| Host | Requests | 409s | Before |
|---|---|---|---|
| traversence.com: every `/api/` endpoint above, `/data-deletion.php`, `/`, `/faq.php` (plain, Facebook preview, Googlebot) | 220 | **0** | 28 of 280 |
| trvgo.us (`/`, `/hours`, Facebook preview) | 50 | 0 | 0 of 30 |
| staging.trvgo.us `/api/staging-updates.php`, `/api/stg-probe.php` | 30 | **4** | 4 of 20 |
| staging.trvgo.us `/` | 20 | 0 | 0 of 10 |

- **traversence.com is fixed:** the crawler, video worker, QuickBooks, Stripe, Square, SES, health report, guide paths,
  the Vault and link previews all got their own endpoint's answer every time.
- **staging.trvgo.us `/api/` is still blocked**, at the same rate as before. Ask Bluehost again for staging.trvgo.us.
  Staging is a separate cPanel domain, so the change on traversence.com may not have carried over to it.
- `/robots.txt` still answers 404 (there isn't one), so it can't show whether bots reach it.
- **Staging rechecked at ~18:38–18:40 UTC**, after Bluehost applied the same whitelist to staging.trvgo.us: 0 of 30 blocked
  (`/api/staging-updates.php`, `/api/stg-probe.php` and `/` each answered 401, the viewing password). All three hosts
  are now clear. Bluehost described the fix as a ModSecurity whitelist for the rule; the rule ID wasn't recorded.
