# Railway and GitHub to the plug-in contract

Values are taken from the live setup: the mirror at Sync 12:26 UTC (service-defs, ControlRegister, Preflight,
Operations, Releases, ControlAlarms), Railway's API (read-only) and GitHub's Actions runs. *Spec* means the contract asks
for it and it isn't built yet.

## 1. Railway

### Manifest

| Field | Value |
|---|---|
| id · name | `railway` · Railway |
| role | **Host**: runs our workers |
| criticality | Not critical by itself (Jason). The live register's critical parts don't include it |
| plan · spend | Hobby, $5 a month including $5 of use (setting: Railway plan, a month). The month's use: *spec*, read from Railway (until then "use not read yet", with railway.com/account/usage) |
| stage · state | Operational · Working |
| what it hosts | Project "gracious-sparkle": **the crawler** (every 5 min, not restarted), **W0 Indexer** (always on, restarted up to 10 times), **the video worker** (every 5 min, restarted up to 10 times; Railway says on failure, the repo says never). The video worker becomes its own **Service** plug-in hosted here, as the report sets out |
| deploys from | `degraffics/traversence` `main`: `workers/crawler/` → the crawler and W0; `workers/video/` → the video worker |
| required keys | In `.env`: `CRAWLER_API_TOKEN` (also on Railway), `RAILWAY_API_TOKEN`, `RAILWAY_PROJECT_ID`, `RAILWAY_SERVICE_ID`. On Railway: the outside sources' keys, reported by name at each run |
| caps | W0's monthly budget and pace (`indexer_month_usd`, `indexer_gap_s`, `indexer_found_batch`) |
| sensors | **Runs:** each crawler run reports, and one 35 min late or failing outright is the host's fault. **Heartbeat:** W0's check-in (silent 15 min); the video worker's (*spec*). **Deploys:** the latest deploy of each service, hourly (built for the crawler only; *spec* for all three); failed, crashed, or building over 30 min is stalled. **Keys:** the worker's report at each run. **Spend:** *spec* |
| feeds | The crawler → W1–W12; W0 Indexer → W0; the video worker → posted videos |
| fix_it routes | A key → Railway's variables for that service. A failed deploy → that deploy's log (*spec*). Runs failing → the crawler's own page, with its last error (*spec*). Spend → Caps and budget |
| actions | Check now · Check and resume · Take out / Put back · Pause everything (also stops Railway's schedule) · Assign workflows |

### Four zones

1. **Identity and role:** Railway · Host · Not critical to the site · Hobby · $x of $5 (*spec*) · Operational · Working.
2. **Services and feeds:** the three services, each with its schedule, its restart rule as Railway has it beside the
   repo's (a mismatch in amber), and what it feeds.
3. **Sensors and keys:** runs, a heartbeat per service, a deploy per service, and the reported keys by name ("not
   checked yet" until the first report).
4. **Actions and Fix it:** the actions above, and one Fix it for the problem that's open.

### Incident Radius rules

| Fault | Held | Unaffected |
|---|---|---|
| Railway down (no service runs or reports) | Everything it hosts: all gathering (W1–W12), W0, new video copies (they queue) | Pages, search, sign-in, payments (Bluehost); releases (GitHub); everything already stored |
| One service down | What that service feeds only | The other two services, and the site |
| A deploy failed or stalled | Nothing: the old version keeps running. A warning, a trip only if runs then fail | Everything |
| A key missing or refused on Railway | Only the tasks that call that key's source | The rest of every workflow |
| W0 at its monthly budget | W0 waits | Everything else |
| The fault lies where the worker *sends*: our site's endpoint slow or failing | Not Railway's radius: the incident moves to the **Site** plug-in, under its own rules (slow is a warning, never a trip) | Railway and every source |

### Worked Incident Report: this morning's two W1 timeouts, as one correlated incident

**IR-0001 · Correlated · W1 bulk loads timed out · opened 2026-11-21 09:25 UTC**

| | |
|---|---|
| Plug-ins | The crawler on Railway (worker 2.16.0 then; 2.16.1 from 12:22 UTC) |
| Signature | The same in both: `TimeoutError('The read operation timed out')`, a bulk load sending its rows, within 11 minutes |
| Members | `foursquare_bulk` (run #1288, first failure 08:20 UTC, trip 09:25); `overture_bulk` (run #1290, first failure 08:31, trip 09:35) |
| Root cause | A read that timed out while a bulk load sent a large batch of rows to our site's load endpoint (case #51's notes). The fault sits on the **Site** endpoint the worker sends to, not on Railway and not on Overture or Foursquare. *To confirm in step 2 from the two runs' logs.* |
| Retry timeline | One failure each, then the hour ran out with no second try (each task runs nightly), and the watch tripped them at 09:25 and 09:35 |
| **Incident Radius, as it happened** | **Held:** all of W1 (both trips paused the whole workflow). **Unaffected:** pages, search, sign-in, payments, W0, W2–W12 |
| **Incident Radius, under the contract** | **Held:** nothing. Our endpoint being slow is a warning on the Site part, and the two bulk loads' next nightly runs go ahead. If the endpoint were failing, not slow: **held** only those two tasks; **unaffected:** the rest of W1 and everything else |
| Proportionate actions | Pause only these tasks · Check and resume (re-checks the endpoint, then both tasks) · Fix it: the Site endpoint's own page, with its timings |
| Recovery | Closes as Recovered when the endpoint's check passes and the tasks next do their work |
| What would stop it happening again | Smaller batches to the endpoint, and a timeout reported as slow, not as a crash (case #51's draft, held for this build) |

## 2. GitHub

### Manifest

| Field | Value |
|---|---|
| id · name | `github` · GitHub |
| role | **Host**: our code, our records, our scheduled jobs |
| criticality | **Critical**, duty "system-critical procedures": releases and the sync come through it |
| plan · spend | Actions minutes within the free allowance. The month's minutes: *spec* (the tokens can't read billing; until then "use not read yet", with github.com/settings/billing) |
| stage · state | Operational · Working |
| what it holds | `degraffics/traversence` (decisions, hand-offs, `releases/inbox`, the delivery log, the workers' code); `degraffics/traverse-3way-sync` (the sync and the live mirror) |
| what it runs | **The sync:** 06:00 and 18:00 UTC, or on demand, one at a time. **Release ping:** when a build lands in the inbox. **Site audit and the delivery log**, **site checks**, **search probe and health report:** asked by the site, with fallbacks (twice a day; daily; daily) |
| what depends on it | Releases (the site's look every 10 min, the release records it writes back); the sync and the live mirror; Railway's deploys (no new builds while it's down) |
| required keys | `GITHUB_DOCS_TOKEN` (`.env`: reads the inbox, writes release records, asks for the checks); `GITHUB_SYNC_TOKEN` (`.env`: watches the sync); `HEALTH_TOKEN` (a GitHub secret and its `.env` copy, which must match); the sync's own secrets on GitHub (OneDrive, FTP, its token), names only |
| sensors | **Tokens:** each one reads its repository, with its expiry, checked daily, amber 14 days before. **Release look:** each look and what it found (*today a look GitHub didn't answer raises nothing*; *spec*: a fault). **Release job quiet:** no look in 30 min (built). **The sync's runs:** failed, stuck over 15 min, none in 14 h (built, but as a Pulse case; *spec*: under the trip model). **A missed slot:** *spec*. **The other Actions:** *spec* |
| fix_it routes | A token → Its key with Replace ready, plus GitHub's token page. A stuck sync → the run's page with Cancel. A failed check → its run's log. A failed release look → Check for builds |
| actions | Check now · Check for builds · Run the sync (*spec*: list the runs first, never two at once) |

### Four zones

1. **Identity and role:** GitHub · Host · Critical (releases and the sync) · Actions: x of N minutes (*spec*) ·
   Operational · Working.
2. **What it runs and feeds:** the sync, release ping, the release look, the audit, checks and probe, each with its
   schedule and what it feeds.
3. **Sensors and keys:** both tokens with their expiry, HEALTH_TOKEN, the last release look, the last sync, the next slot
   expected, and the last run of each Action.
4. **Actions and Fix it:** the actions above, and one Fix it for the open problem.

### Incident Radius rules

| Fault | Held | Unaffected |
|---|---|---|
| GitHub down (its API doesn't answer) | Releases (builds wait in the inbox; records aren't written), the sync and the mirror, new Railway deploys | Pages, search, sign-in, payments, gathering, and the workers on their current versions |
| A token expired or refused | What that token does: releases (docs token), watching the sync (sync token), the health report (health token) | Everything else |
| The sync failed, stuck or stalled | The sync, and the mirror and OneDrive it keeps | Releases, which publish on their own, and the site |
| A scheduled sync slot missed | Nothing: the next run catches up. A warning | Everything |
| A check run failed (audit, checks, probe) | Nothing held: that report waits. A warning | Everything |

### Worked Incident Report: the missed 06:00 sync

**Is it an Incident Report?** Incident Reports are for trips. A missed slot is a **warning** under the contract,
because nothing is held and the next run catches up. So today it is a warning record, not an IR. Had it passed 14 hours
with no run (17:19 UTC), it would have tripped the Sync part and opened this report. The record:

**W-sync-0621 · Warning · The 06:00 UTC sync didn't run · raised 07:00 UTC (*spec*: an hour past the slot)**

| | |
|---|---|
| Plug-in | GitHub · the sync (`sync.yml` on `traverse-3way-sync`) |
| What happened | The last runs: #689 at 03:17 UTC (by hand), then #690 at 12:24 (by hand). No scheduled run came for 06:00. Yesterday's 18:00 run started at 23:48, about six hours late |
| Root cause | GitHub's own scheduler: scheduled runs start late or are dropped at busy times. GitHub's doing, not ours |
| Timeline | 06:00 slot passed; 07:00 the warning (*spec*); 12:24 a run by hand succeeded, so the warning clears |
| **Incident Radius** | **Held:** nothing. **Late:** the live mirror and OneDrive were nine hours behind the server (03:19 → 12:26), and a session building in that window had to run the sync first. **Unaffected:** releases (#247 went live at 12:22 on its own), the site, gathering |
| Actions | Run the sync (lists the runs first) · Fix it: the sync's runs page |
| Recovery | Clears when a sync run succeeds |
| What would stop it happening again | A missed-slot warning an hour after each slot, so a lagging mirror is seen at 07:00, not found later |

## 3. The report's five states on the eight stages

| Report's state | Jason's stages | Actions there (Jason's four, and the report's names) |
|---|---|---|
| **Registered / Draft** | 1. Selection and configuration · 2. Integration (pre-flight) | Run pre-flight · Edit configuration · Discard (a person's Delete, before it ever ran) |
| **Active (Operational)** | 3. Activation · 4. Assigned to workflows · 5. Operational | Activate (person) · Assign workflows · Check now · Pause (system or person) |
| **Maintenance / Draining** | 6. Maintenance | Take out · Put back · Extend the window. Leases finish, and its dependants reroute or wait without false trips |
| **Deprecated (phase-out)** | 7. Deactivation | Deactivate (person). "Migrate traffic" folds in here: its workflows' assignments move to the next source on the priority map before it's switched off, and no new assignments are taken |
| **Removed (archived)** | 8. Removal | Delete (person). "View archive" and "Re-install" are 0173's existing Removed list and Bring it back. Its Incident Reports, ledger and spend stay |

The corrections apply: no SMS (0171: "no text messages"; no income, no expense) and no webhooks channel until it's
decided. The Communications Dispatcher's card lists email, Pulse and push only.
