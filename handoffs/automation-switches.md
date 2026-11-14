# Hand-off: the automation switches (decisions/0163 §9)

Session `session_01CXLi5kK8GzhmtW4etwGUwx`, 2026-11-14, from Jason's ask through the session manager: "Please update the
site settings to be off until switched back on, also give me a master switch to turn on and off. Also I see the services
as routed services connected to a task so we should be able to turn those on and off as well."

**State: finished.** Release #48 (`automation-switches_20261114.zip`, built on Sync 2026-11-14 15:39 UTC), merged on
Jason's "merge it" (PR #658), live 2026-11-14 17:57 UTC: its migration ran first, staging and live checks passed. Sync
17:59 UTC: all 19 files match the live mirror byte for byte (the migration ran and was deleted); `deliveries.py verify`
recorded, claims released. Still to see with a sign-in: System Configuration → Automation showing the master paused,
and a first Save there (MySQL at run time). Nothing else waits on Jason.

## What it does

System Configuration → **Automation** (`app/admin/automation-console.php`, the library `app/lib/Automation.php`).
One rule: a call happens only when the master, its workflow, its task and its service are all on.

| Layer | Stored in | Who honours it |
|---|---|---|
| Master | `automation_switches` (master/all) | `CrawlQueue::pausedWhy()` → every crawler endpoint and `CrawlService::plan` (a Railway run ends at once); `Scheduler::run` (jobs in `Automation::JOBS` skip, recorded "Paused: …"); `AiClient::allow` (features in `CrawlService::FEATURE_WF`, except `Automation::PERSON_FEATURES`); the site's gathering calls (`OpenPlaces::overpass`, `Dossier::get`, `PlaceFinder::get`, `TravelLegs::post`, `SecondTier::fetch`, `Landmarks::findPhotos`); W5 (`OnDemand::paused`) |
| Workflow W1–W13 | `crawl_workflows.paused` (+ by, at, why); the migration adds the website's own rows | the crawler's plan and requests (as before); `Scheduler::run` (a job skips when all its workflows are paused); `AiClient::allow` by the feature's workflow; W5 on demand; `PlaceFinder::get` (W9), `TravelLegs::post` (W7), `Landmarks::findPhotos` (W9) |
| Task | `crawl_capture_tasks.enabled` (+ `off_why`, `off_by`, `off_at`) | the crawler's plan (as before) |
| Service | `automation_switches` (service/<key>) | `ServiceHealth::allowed` (site callers), `ServiceHealth::holds` (its hosts sent to the worker as holds: the task reports "held"), `AiClient::providerFor` and before each Claude request (`post`, `claudeRead`), the gathering calls above (by the URL's host), its Outside services card |

- **Never stopped by the master:** business operations (orders, billing, payments, email, releases, staging, Moments),
  members' and businesses' own actions (card scans, Write it for me), a staff press (Write a draft, Check my facts),
  guides' weather and roads (`guide_live_tick`), the admin. Services those depend on are in `Automation::NOT_PAUSABLE`,
  greyed on the page with the reason.
- **Visitors (W5 paused):** search's offer says new guides are paused and still takes the ask; `/hub/build.php` keeps
  the ask (`guide_requests` queued, note `Automation::VISITOR_NOTE`), shows Notify me and "See what we list near …"
  (`/discovery/?near=…`). Nothing is gathered until it's back on; then the queued asks build, most asked first.
- **Never a failure, never a Pulse warning:** `CrawlService::stalled()` is quiet while paused (through `CrawlQueue::paused()`);
  AI use shows a paused feature as "paused" (info, not bad); jobs record a run that worked.
- **Shipped OFF:** the migration `2026-11-14_automation_switches.sql` seeds the master paused, "Paused by Jason,
  2026-11-14, until the crawler review" (INSERT IGNORE, safe twice). Before it runs, `Automation` reads the master as off.
- Every change is logged (`automation_log`) and listed on the page; pausing asks why. Permission `config.automation`
  (Executives and the Founder by default).

## Files in the zip

New: `app/lib/Automation.php`, `app/admin/automation-console.php`, `app/migrations/2026-11-14_automation_switches.sql`.
Changed: `app/lib/Scheduler.php` (run), `app/lib/crawler/CrawlQueue.php` (paused, pausedWhy), `app/lib/crawler/CrawlService.php`
(plan, setTask), `app/lib/ServiceHealth.php` (allowed, holds), `app/lib/AiClient.php` (allow, providerFor, post,
claudeRead), `app/lib/OpenPlaces.php` (overpass), `app/lib/OnDemand.php` (paused, offer, start, step), `app/lib/Dossier.php`
(get), `app/lib/PlaceFinder.php` (get), `app/lib/TravelLegs.php` (post), `app/lib/crawler/SecondTier.php` (fetch),
`app/lib/Landmarks.php` (findPhotos), `app/admin/manifests.php` (the section), `app/admin/sections.php` (require, the
permission row, AI use's paused tone), `app/ui/outside-services.php` (a paused card), `website/hub/build.php` (the paused
view), `app/ui/faq.md` (three entries).

Tested here: the switch logic on SQLite (24 checks: master, jobs, features, person features, workflows, services,
not-pausable, hosts, missing table) and the section's render. Not tested here: MySQL syntax at run time (the migration
and `Automation::set` use the patterns live code already uses).

## Not done, on purpose

- `workers/crawler/` untouched: a worker deploy restores Railway's cron (`handoffs/crawler-revamp.md`). Railway's
  schedule is still removed; the master makes any run that starts end at once.
- Weather, roads, billing, email and sign-in calls aren't wired to the service switch (business operations).
- The register (0163 §1) will later hold the switches' state beside each service's caps.

## To switch it back on (Jason)

System Configuration → Automation → **Switch automation on**. Then, for the crawler, set Railway's cron back
(`handoffs/crawler-revamp.md`, top).
