# 0113: Scheduled tasks, set from the admin through the cPanel API

Status: Built (2026-10-23). Jason, 2026-10-22: "dont we have the cpanel api installed, if so can we use it to create …
things like cron jobs?", then "Scheduler. Then business portal."

## What it is

**System Configuration → Scheduled tasks** (`/admin/config/scheduler`): the jobs that keep the site running on time.

| Job | Script | Starts on |
|---|---|---|
| AI worker | `app/scripts/ai_worker.php` | every minute |
| Notices of action | `app/scripts/standing_tick.php` | every 10 minutes |
| Lead scores | `app/scripts/sales_tick.php` | every hour, at :17 |
| Plans and renewals | `app/scripts/billing_tick.php` | every day at 6:13 am |

- **Each job shows:**
  - its schedule in cPanel, or "Not set up in cPanel";
  - when it last ran, and what it said or why it stopped;
  - how many runs stopped in the last day;
  - "Late" when it should have run since;
  - its cron line, so it can also be set by hand.
- **Set up**, **Change** (a choice of schedules, from every minute to nightly) and **Remove** go to cPanel through its
  API, on the server's clock.
- Cron jobs in cPanel that aren't ours are listed, never changed here.
- **Who:** Directors in Engineering or Operations change them (`config.scheduler.edit`); the module's viewers see them.

## How

- **`CPanel`** (`app/lib/CPanel.php`) calls our own cPanel with the API token already in `.env` (`CPANEL_HOST`,
  `CPANEL_USER`, `CPANEL_TOKEN`; decisions/0100):
  - `uapi()` for the current API;
  - `api2()` for the older one, where cron jobs are still managed (module Cron: `listcron`, `add_line`, `edit_line`,
    `remove_line`).
- **`Scheduler`** (`app/lib/Scheduler.php`):
  - A job's cron line is found by its script path.
  - A new line uses the same PHP as our other jobs' lines (else `CRON_PHP` in `.env`, else `/usr/local/bin/php`) and
    the script's full path.
  - A new line sends its output to nowhere, so cPanel doesn't email every run.
  - A changed line keeps its command and only its time changes.
- **Runs:** each script calls `Scheduler::run('<job>')` at its top.
  - The run is written to `job_runs` (admin database, group 1 in Data separation), with whether it finished and its
    last line of output, or the error that stopped it.
  - Runs older than 14 days are cleared.
  - This works whether or not cPanel can be reached from the admin.
- **Without the token:** the page says what to add to `.env`, and shows each job's line to paste into cPanel → Cron
  Jobs by hand.
