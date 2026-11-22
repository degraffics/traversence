# The read-only checks the build needs (step 3 closed, 2026-11-22)

This list replaces the earlier visits list, which the session manager's asks and step 3's own had built up. Each item is
now a **check**:
- what it reads;
- why the build (step 4) needs it;
- that it is read-only;
- who can run it.

**Read-only, every one:** nothing is pressed, switched, edited or written on the site, Railway, GitHub or any provider. A
check reads **names and states only, never values**: a key's name and whether it's set, never the key.

**Who can run it:**
- **Runner:** a check on GitHub's runners (Actions), the way the site audit runs.
  - For admin pages it signs in as the audit's executive test account, from the `CHECK_ACCOUNTS` secret, as
    `tools/page-audit/rendered.mjs` already does (System Configuration → Test accounts).
  - It opens each page and reads it. Its results are written to the repo.
- **Jason:** the same page, opened by him, with the readings given back.
- **Runner or Jason:** either works.

**Blocks the build:** five checks, C2, C3, C6, C15 and C17. The build can't settle the live state into the one engine, or
cover every workflow and task, without them (close.md §4). The rest settle a card's words or a spec line, and the build
reads them live anyway.

| # | What it reads (names and states only) | Why the build needs it | Who can run it | Blocks? |
|---|---|---|---|---|
| C1 | **IR-0001's timings:** every call to `/api/crawl/loads.php` from 08:15 to 08:21 and 08:26 to 08:32 UTC on 2026-11-21; each one's time, duration, status and row count. Page: the Ledger, https://traversence.com/admin/config/ledger | Settles IR-0001's root cause (handler or response path) for the loads component's check. The model holds either way (02 §10) | Runner or Jason | No |
| C2 | **Every open alarm (all 21):** its id, part, level and since. Page: the Alarms tab, https://traversence.com/admin/config/alarms | The build settles every open alarm into the one engine (R83). The health report showed only 13; the other 8 may be kinds the map doesn't cover yet | Runner or Jason | **Yes** |
| C3 | **Pauses and switches written by trips:** `system_pauses`' open rows (and those for W1 and W3, open or cleared); `automation_log` for task/source, task/commoncrawl and workflow/W3; each W1 and W3 task's on or off, with who and why. Pages: the Data browser, https://traversence.com/admin/insights/data, and Insights → Crawler | The build moves switches a trip wrote into "Off by the system (left by a closed trip)" (02 §0) and must never touch a person's setting. It has to know which is which | Runner or Jason | **Yes** |
| C4 | **The lift's reads:** the Logbook's `resolve` and `clear` lines for wf:W3, svc:commoncrawl, wf:W1 and so:commoncrawl, and their order; the time of the `resolve` POST; `service_issues` #22 and #35 (state, resolved at, by and how, hold until); `comms_messages` and `comms_route` for the W3, #35 and W1 alarms | Settles `01-lift-reconstruction.md` (a)–(f). The model already covers what it found | Runner or Jason | No |
| C5 | **Run #1290's records by task:** which task made "Added 205 · Changed 4 · Sent to Review 709". Page: Insights → Crawler, https://traversence.com/admin/insights/crawler | Whether the 205 were Overture's rows, for the licence item (close.md B5) | Runner or Jason | No |
| C6 | **The seeded workflow → task table** (`crawl_capture_tasks`: each task's workflow). Page: the Data browser | The scenario test must cause every cell for every workflow and task. The map's workflow → task links come from the research doc and `SystemStart::TASKS`, while the database's seed is the live truth | Runner or Jason | **Yes** |
| C7 | **Common Crawl's index:** its pre-flight line (Ready, or "Partly working") and time; and whether index.commoncrawl.org answers a public read of its index list. Page: Pre-flight, https://traversence.com/admin/config/preflight | W3's two switches wait on this re-read (Jason, 2026-11-22: "After the re-read") | Runner (the public read and the page) or Jason (the page) | No |
| C8 | **W2's count:** the call that raised `ep:crawl/refresh:count`: what it received and the records it touched. Page: the Ledger | Confirms the cause read from the code (03 §28.2) | Runner or Jason | No |
| C9 | **The pre-flight's lines, by name:** whether each key is set, never its value (`S3_ENDPOINT`, `GEMINI_API_KEY`, `CENSUS_API_KEY`, `NPS_API_KEY`, `AZ511_API_KEY`, `PIXABAY_KEY`, `SUPABASE_URL`, `SUPABASE_SERVICE_KEY`); `MARKET_PAY_ENV`'s mode (live, sandbox or mock); the Railway lines naming a missing key (`OVERTURE_RELEASE`, `FSQ_RELEASE`, `RIDB_API_KEY`); the §26 verify lines' states and times (the National Weather Service, USGS, RIDB, AZ 511, Pixabay). Page: Pre-flight | Each card's stage and words (03 §16, §21, §22, §26). The build reads these live | Runner or Jason | No |
| C10 | **Two settings' values:** `websearch_monthly` and `ors_matrix_daily` (numbers, not secrets). Page: Thresholds & gates, https://traversence.com/admin/config/thresholds | Tavily's and openrouteservice's drawn states (03 §17, §18, §26.16) | Runner or Jason | No |
| C11 | **Two modes:** `DOMAINS_MODE` and `DOMAIN_SALES_MODE` (off, test or live). Page: Settings, https://traversence.com/admin/config/settings | The Fly.io edge's and Name.com's stages (03 §25, §26.20) | Runner or Jason | No |
| C12 | **Custom domains by stage:** the counts (Added, DNS pointed, Certificate coming, Live, Failed, Removed). Page: https://traversence.com/admin/market/domains | The edge card's zone 2; any domain stuck at "Certificate coming" | Runner or Jason | No |
| C13 | **The open Pulse cases:** each one's title, since and who it's with. Page: https://traversence.com/admin/pulse/stream | One Pulse item per alarm (R129): any older case still gathering alarms is listed for the build's settle step | Runner or Jason | No (read with C2) |
| C14 | **`video_jobs` by status:** queued or leased, done today, failed. Page: the Data browser | The video worker's zone 2 (03 §23.4) | Runner or Jason | No |
| C15 | **`irs_loads`:** each load's file, finished at, rows seen and rows removed. Page: the Data browser | The IRS fix is in the build (Jason, 2026-11-21). Whether the partial delete already removed nonprofits decides whether the build also carries their reload, which belongs in the release's scope | Runner or Jason | **Yes** |
| C16 | **The sources' last loads:** `open_loads` (the newest per dataset; Geofabrik's rows for Utah, Colorado and Nevada), `npi_loads` and `rec_loads`, the newest rows. Page: the Data browser; NPI and IRS also on https://traversence.com/admin/dashboard/today | The "Last load" on each source card (03 §22.6, §26.6) | Runner or Jason | No |
| C17 | **`ALARM_TO` set or not** on degraffics/traversence (name only). A workflow step tests that the secret is non-empty and prints only "set" or "not set" | The outside heartbeat's cell of the scenario test (close.md A1) | Runner (the test step) or Jason (https://github.com/degraffics/traversence/settings/secrets/actions) | **Yes** |
| C18 | **W0 started?** Its switch, who and when, and its heartbeat's last time. Page: https://traversence.com/admin/config/control/wf-W0 | W0's state on the Workflows page (held as live data by the session manager) | Runner or Jason | No |
| C19 | **Is traversence.com's DNS on Cloudflare?** Its public name-server records | Whether Cloudflare carries a page-load duty (03 §24.4) | Runner (public DNS) or Jason (https://dash.cloudflare.com/) | No |
| C20 | **Is CMS's monthly NPI file back?** The public page https://download.cms.gov/nppes/NPI_Files.html | The monthly NPI load stays "Off on purpose" until it is (03 §22.4) | Runner | No |

## Where the earlier visits went

Older documents cite "asks.md, visit N" or an item "[N]". They map to the checks like this:

| Earlier visit | Now |
|---|---|
| 1. Ledger | C1, C4, C8 |
| 2. Alarms | C2, C4 |
| 3. Logbook | C4 |
| 4. Insights → Crawler | C3, C5 |
| 5. Pre-flight | C7, C9 |
| 6. Thresholds | C10 |
| 7. Settings | C11 |
| 8. Custom domains | C12 |
| 9. Pulse | C13 |
| 10. Data browser | C3, C4, C6, C14, C15, C16 |
| 11. Railway's variables | C9 (the pre-flight's Railway lines) |
| 12. GitHub's secrets | C17 |
| 13. Cloudflare | C19 |
| 14. CMS | C20 |
| 15. The Vault | below |
| 16. Apple and Meta | below |
| 17. W0 | C18 |

## Not checks: Jason's own accounts and his Vault

These aren't reads a runner can make, and none blocks the build:
- **Provider pages from your Vault** (close.md B21): open each from https://traversence.com/admin/config/vault and use
  **Publish from your Vault** (owner only). Until then the slot says "No <type> page registered for <Provider> yet".
  The pages still to publish:

  | Module | Pages |
  |---|---|
  | Bluehost | Billing, Keys |
  | Railway | Keys, Sign in |
  | GitHub | Keys, Console, Sign in |
  | Anthropic | Sign in |
  | Google Cloud | Sign in |
  | Gemini | Sign in |
  | QuickBooks | Billing, Sign in |
  | AWS | Sign in |
  | Tavily | Billing, Keys, Sign in |
  | Brave Search | Billing, Keys, Sign in |
  | Cloudflare | Keys, Sign in |
  | Fly.io | Sign in |
- **Apple's Developer Program:** is the $99 program active for Sign in with Apple? Check at
  https://developer.apple.com/account, under Membership details (03 §26.21).
- **Meta's app review:** is the app for Sign in with Facebook live, or still in development or review? Check at
  https://developers.facebook.com/apps/, under App Review (03 §26.23).
