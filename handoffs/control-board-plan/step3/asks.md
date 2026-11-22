# Live-data asks for step 3 (for Jason, through the session manager)

The session manager's list of asks, merged with step 3's own. **Settings by name only, never values.**

The reads are grouped by the page they come from, so each page is one visit. Every page is read only: nothing is
pressed, switched or edited. Where a read has no admin page today, it goes through the admin's **Data browser**, read
only. Step 4's ledger export is proposed to replace those reads later.

The numbers in brackets are the 16 asks as listed:
1. IR-0001's timings and run #1290's task
2. alarms, pauses and Pulse cases
3. the lift's reads
4. `OVERTURE_RELEASE`, `FSQ_RELEASE`, `S3_ENDPOINT`
5. `websearch_monthly`
6. `GEMINI_API_KEY`
7. `ALARM_TO`
8. `irs_loads`' removed counts
9. the sources' loads and CMS's page
10. the video queue
11. DNS on Cloudflare
12. `DOMAINS_MODE` and domains
13. the Vault's provider pages
14. §26's names (the session manager, 2026-11-21): the services' keys and modes, `ors_matrix_daily`, Apple's Developer
    Program and Meta's app review
15. the seeded workflow → task table (the association map)
16. Gemini's hand-off note (2026-11-22): W2's count call and §26's verify steps' live parts (03 §28). Answered, so not
    asked: Railway runs crawler 2.16.1 and `pf:wf:W3` is closed (the session manager, 2026-11-22)

---

## Visit 1. The Control Board's Ledger: https://traversence.com/admin/config/ledger

- **[1] IR-0001's timings.**
  - **Needed:** every call to `/api/crawl/loads.php` from 08:15 to 08:21 and 08:26 to 08:32 UTC on 2026-11-21. For each, its time, duration, answer (status) and rows.
  - **Why:** it settles IR-0001's root cause, which stays Unconfirmed until then.
    - A call that finished with stored rows and a 2xx after the worker's 120 s points to the response path.
    - No row, or one still running, points to the handler.
    - Run #1290's page shows rows stored with no "records sent" line on Railway, which already leans to the response path.
  - **Settles:** `step2/ir-0001.md`; 03 §7, §22.1 (Overture's page b).
  - **If the ledger has no rows for those minutes:** Bluehost's access log for the same requests. A session can read it through the cPanel API with your OK, so you don't open cPanel.
- **[3] The lift, item 10.** `POST /admin/api/config/control` with `action=resolve`: its time. This places the press against #35's clearing. *Settles:* `01-lift-reconstruction.md` (a)–(f).
- **[16] W2's count (live L9, `ep:crawl/refresh:count`).** The call that raised it: what it received and the records it
  touched. **Why:** the cause is read from the code (03 §28.2: the refreshed listing counted as an outcome, never as
  received); this call confirms it. **Settles:** 03 §28.2.


## Visit 2. The Control Board's Alarms: https://traversence.com/admin/config/alarms

- **[2] The 8 open alarms the snapshot left out.**
  - **Needed:** all 21 open alarms, not only the 13 the health report showed. For each: its id, part, level and since.
  - **Why:** step 2 audited only 13, and the other 8 may hold parts nobody has seen.
  - **Settles:** `step2/live-snapshot.md`, `step2/README.md` (the last open item).
- **[3] The lift, item 1.** These rows:
  - `pf:wf:W3`, `svc:issue:35`, `svc:issue:22`;
  - `task:failed:overture_bulk`, `task:failed:foursquare_bulk`;
  - `pf:so:commoncrawl-common-crawl-a5bbfe76`.

  For each, its level, cleared at and how, resolved by and its note, acknowledged by, stopped, and last seen. *Settles:* `01-lift-reconstruction.md` (what "Check and start again" closed, and when).


## Visit 3. The Control Board's Logbook: https://traversence.com/admin/config/log

- **[3] The lift, item 8.**
  - **Needed:** the `resolve` and `clear` lines, and their order, for `wf:W3`, `svc:commoncrawl`, `wf:W1` and `so:commoncrawl-…`.
  - **Settles:** whether #35 cleared before or after the press.

## Visit 4. Insights → Crawler: https://traversence.com/admin/insights/crawler

- **[1] Run #1290's W1 source task.**
  - **Needed:** open run #1290 and read which task's records make up "Added 205 · Changed 4 · Sent to Review 709".
  - **Why:** the 205 is cited for Overture's and Foursquare's licences (For Jason's review, 6), but `overture_bulk` failed at 380 s. The run's records say whether the 205 were Overture's rows or another W1 task's in the same run.
  - **Settles:** for-jason-review.md item 6; `step2/ir-0001.md`.
- **[3] The lift, items 3 and 9.**
  - W1's and W3's paused state and why.
  - The `source` and `commoncrawl` tasks' and W1's tasks' on/off, with who and why.
  - The runs after the press: did any W3 task run?

## Visit 5. Pre-flight: https://traversence.com/admin/config/preflight

- **[4] `S3_ENDPOINT`: set or not.**
  - **Needed:** Cloudflare's line. It reads either "R2 isn't in use (S3_ENDPOINT)" or "R2: …".
  - **Why:** it decides whether file storage is drawn on AWS's card or as Cloudflare's R2. Right now storage is drawn on S3, and R2 as Proposed.
  - **Settles:** 03 §21.6, 1; §24.4, 2.
- **[6] `GEMINI_API_KEY`: set or not.**
  - **Needed:** Gemini's line (Google AI Studio): whether its key is in `.env`.
  - **Settles:** Gemini's stage (drawn at stage 2, pre-flight) and its card, 03 §16.
- **[3] The lift, item 6.** The times and answers of the `svc:commoncrawl`, `wf:W3` and `nd:sources` lines just before and after the press. Was Common Crawl's line older than 60 minutes?

- **[14] §26's keys: set or not (names only).** Each service's line on this page says whether its key is in `.env`
  ("Not set up (…)" when it isn't):
  - `CENSUS_API_KEY` (the Census data API's higher daily limit; the geocoder needs none);
  - `NPS_API_KEY` (the Park Service's photos);
  - `AZ511_API_KEY` (AZ 511's events API; the WZDx feed needs none);
  - `PIXABAY_KEY` (Pixabay's photos for the Stock library);
  - `SUPABASE_URL` and `SUPABASE_SERVICE_KEY` (the vector index, added off).
  - **Settles:** 03 §26.1, 26.5, 26.13, 26.15, 26.17.
- **[14] `MARKET_PAY_ENV`:** live, sandbox or mock (a `.env` name; the Stripe and Square lines show which mode they
  test). **Settles:** 03 §26.18, 26.19.
- **[16] §26's verify steps' lines:** Common Crawl (Ready or "Partly working"), the National Weather Service, USGS (its
  two parts), RIDB, AZ 511 and Pixabay: each line's state and time. **Settles:** 03 §28.3, §26.6–26.15.


## Visit 6. Thresholds & gates: https://traversence.com/admin/config/thresholds

- **[5] `websearch_monthly`'s value.**
  - **Why:** its default, 0, stops web search, Brave included. It decides which of Tavily's two drawn states is live: a monthly allowance, or "Off on purpose: websearch_monthly is 0".
  - **Settles:** 03 §17, §18.

- **[14] `ors_matrix_daily`'s value** (openrouteservice's travel-time asks a day; its free plan allows 500).
  **Settles:** 03 §26.16.

## Visit 7. Settings: https://traversence.com/admin/config/settings

- **[12] `DOMAINS_MODE`:** off, test or live.
  - **Why:** it decides the Fly.io edge's stage, which is drawn as "needs live data".
  - **Settles:** 03 §25.1, §25.4, 5.

- **[14] `DOMAIN_SALES_MODE`:** off, test or live (Domain sales on Name.com's card). **Settles:** 03 §26.20.

## Visit 8. Market → Custom domains: https://traversence.com/admin/market/domains

- **[12] Domains by stage:** the counts by status (Added, DNS pointed, Certificate coming, Live, Failed, Removed).
  - **Settles:** the edge card's zone 2, and whether any domain is stuck at "Certificate coming" (03 §25.3, b).

## Visit 9. Pulse: https://traversence.com/admin/pulse/stream

- **[2] The open Pulse cases:** each case's title, since and who it's with.
  - **Why:** step 2 couldn't read them.
  - **Settles:** `step2/README.md`.

## Visit 10. The Data browser, read only: https://traversence.com/admin/insights/data

These tables have no admin page of their own. Choose each table in the browser and read; don't edit. **Proposed:** step
4's ledger export carries them, so this visit isn't needed again.

- **[2], [3] `system_pauses`.**
  - **Needed:** every open row, and the rows with kind `wf` and ref W1 or W3, open or cleared.
  - **Settles:** the open system pauses; the lift's item 2.
- **[3] The rest of the lift's reads:**
  - `automation_log` for `task/source`, `task/commoncrawl` and `workflow/W3`, all time;
  - `service_issues` #22 and #35: state, resolved at, by and how, hold until, and any new issue at `index.commoncrawl.org` after the press;
  - `comms_messages` and `comms_route` for the W3, #35 and W1 alarms.
- **[8] `irs_loads`.**
  - **Needed:** each load's file, finished at, rows seen and **removed**.
  - **Why:** it shows whether the partial delete has already removed nonprofits. The fix is decided; this tells us whether any data was lost.
  - **Settles:** 03 §22.5; For Jason's review, 5.
- **[9] The sources' last loads.**
  - `open_loads`: the newest row per dataset (overture, foursquare, geofabrik), and Geofabrik's rows for `utah`, `colorado` and `nevada` (has a first load finished?);
  - `npi_loads`: the newest row.
  - **Settles:** the "Last load" column on `sources-a-list` and each source card; 03 §22.6.
  - The NPI and IRS last-finished times also show on the admin's Today page: https://traversence.com/admin/dashboard/today.
- **[15] The seeded workflow → task table** (`crawl_capture_tasks`: each task's workflow, read only). It's the database's
  seed: no live code holds it.
  - **Why:** it settles the association map's workflow → task links (`association-map.md`), which today come from
    `research/crawler-how-it-runs-2026-11-14.md` and `SystemStart::TASKS`.
- **[10] `video_jobs`.**
  - **Needed:** counts by status: queued or leased, done today, failed.
  - **Settles:** the video worker's zone 2, 03 §23.4, 4.
- **[16] RIDB's last load:** `rec_loads`, its newest row's time (this month's load or not). **Settles:** 03 §26.6, §28.3.


## Visit 11. Railway, the crawler service's Variables

https://railway.com/project/a445f0be-b6de-46bb-a422-51ff835ca6e4/service/8d254c0d-abcb-4520-b9ae-dfbdd362b243/variables

- **[4] `OVERTURE_RELEASE` and `FSQ_RELEASE`:** set or not (names only).
  - **Why:**
    - Unset, Overture lists its bucket for the newest release. That's a call in IR-0001's window, and it decides whether Overture's S3 listing ran.
    - Unset, Foursquare reads the fixed `dt=2026-10-15`.
  - **Settles:** 03 §22.1, §22.2; `step2/ir-0001.md`.

- **[14] `RIDB_API_KEY`:** set or not (Recreation.gov's monthly load). **Settles:** 03 §26.6.
- **[16] `RIDB_API_KEY` in the run report:** the crawler's latest run log names it present or not (names only).
  **Settles:** 03 §28.3.


## Visit 12. GitHub, degraffics/traversence → Settings → Secrets

https://github.com/degraffics/traversence/settings/secrets/actions

- **[7] `ALARM_TO`:**
  - Is a secret by that name set? GitHub shows names only.
  - Which mailbox should it be? The cards say only "<the address set as ALARM_TO>".
  - **Why:** the outside heartbeat emails this address when Bluehost is down.
  - **Settles:** 03 §14, §20.6.

## Visit 13. Cloudflare's dashboard: https://dash.cloudflare.com/

- **[11] Is traversence.com a site (zone) on this account, with its DNS on Cloudflare?** Yes or no.
  - **Why:** until it's answered, Cloudflare carries no page-load duty.
  - **Settles:** 03 §24.4, 1.

## Visit 14. CMS's NPI files page: https://download.cms.gov/nppes/NPI_Files.html

- **[9] Is the monthly file back?** It was taken down 2026-11-02 "until further notice".
  - **Why:** the monthly NPI load stays "Off on purpose" until it is.
  - **Settles:** 03 §22.4.

## Visit 15. Your Vault: https://traversence.com/admin/config/vault

- **[13] The priority modules' provider pages** that aren't registered: open each from your Vault and use **Publish from your Vault** (owner only). Each registers as "From your Vault · <date>".

| Module | Not registered |
|---|---|
| Bluehost | Billing, Keys |
| Railway | Keys, Sign in |
| GitHub | Keys, Console, Sign in |
| Anthropic | Sign in |
| Google Cloud | Sign in |
| Gemini (AI Studio) | Sign in |
| QuickBooks (Intuit) | Billing, Sign in |
| AWS | Sign in |
| Tavily | Billing, Keys, Sign in |
| Brave Search | Billing, Keys, Sign in |
| Cloudflare | Keys, Sign in |
| Fly.io | Sign in |

The open-data sources (Overture, Geofabrik, NPI, IRS) need none: their slots say "Open data: no account".

## Visit 16. Apple's and Meta's developer accounts

- **[14] Apple's Developer Program:** is the $99 program active for Sign in with Apple?
  Sign in at https://developer.apple.com/account, then Membership details. **Settles:** 03 §26.21.
- **[14] Meta's app review state:** is the app for Sign in with Facebook live or still in development or review?
  Sign in at https://developers.facebook.com/apps/, open the app, then App Review. **Settles:** 03 §26.23.

## Visit 17. W0 Indexer's state: https://traversence.com/admin/config/control/wf-W0

- **[17] Has W0 been started?** It was built and shipped switched off (release #79). Read its switch, who and when, and
  its heartbeat's last time on its own page. Read only. **Settles:** `workflows-page.md` §5 and §6 (W0's state), held as
  live data by the session manager (2026-11-22).
