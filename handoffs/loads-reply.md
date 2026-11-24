# loads-reply.zip: the IR-0001 fix (plan, 2026-11-24)

Gauge request #1 (rt:o-gather, "Its check passing"), taken by the Control Board build session (`releases/gauge-pickups.json`).
Evidence: `handoffs/control-board-plan/step2/ir-0001.md` (runs #1288, #1290: the worker's 120 s read of `/api/crawl/loads.php`
timed out; the site's page shows rows stored from run #1290; no "records sent" in Railway's log).

## What the code shows (live mirror, Sync 22:02 UTC)

- `crawler.py` `open_load`: a town's rows go in POSTs of **500**, each with `timeout=120`; `site_call` retries only the bot check.
- `OpenLoads::batch` (app/lib/crawler/OpenLoads.php): per row `ListingIdentity::resolve` (database reads), then `fold`,
  `IntakeStager::stage`, then `ListingIdentity::recheckTick` on every id touched, all inside the one request. 500 rows of that
  can pass 120 s while PHP carries on to the end: **rows stored, the reply too late**. That fits #1290 better than a lost reply.
- A resend is **not safe today**: `open_loads` adds its counts on every call (`seen = seen + VALUES(seen)`), and the rows are
  staged again.

## The fix (one release: site + worker)

1. **Per-call timings** (worker): each site call logs its path, bytes sent, ms and answer, so the next failure names the call.
2. **Batch keys** (site + worker): each POST carries `batch` (its index in the town). The site keeps `(dataset, key, release,
   batch)` with its result in `open_load_batches` (new table, staging class OWN); a batch already done answers its kept result
   and does nothing. Safe to send twice.
3. **Smaller batches, one retry** (worker): 100 rows per POST; a timeout or connection error on a POST is retried once with
   the same batch key and a 240 s read.
4. **The recheck after the reply** (site): `recheckTick` moves out of the request into the open-loads finish (the town's
   `done`), so a batch's request is resolve + stage only.
5. **The site's clock asks for gauge-requests hourly** (`AuditClock::FLOWS`, every UTC hour), since GitHub skipped the
   workflow's schedule (session manager, 2026-11-23).

## Proof before hand-off

- Bench: a batch resent gives the same counts and no second staging; a 100-row batch's time on MariaDB; the town's done
  runs the recheck once.
- Worker tests (`workers/crawler/tests/`): batch keys, 100-row chunks, the retry once on timeout, never on a 4xx.
- After live: the next overture_bulk run's per-call times; the trip closes as Recovered by its check.

## Waiting on

- The result of Jason's Check and start again on W1's trip (2026-11-23, "I'll press it now"): if the re-run passed, this is
  still worth doing (the margin is thin) but not urgent.
- The session manager's view on reading run #1290's ledger rows (`control_ledger.ms` for `/api/crawl/loads.php`) through
  `live-read.yml` to confirm the handler's time before building.

## Confirmed on live data (2026-11-24, live-read.yml run 45292817775, read and its log deleted)

The ledger's POST /api/crawl/loads: 101,841 to 130,249 ms per 500-row batch, every one 200 (run #1290: 117,115 / 118,781 /
124,427; run #1288: 111,473 to 130,249); the opening GET 9 ms. The handler's own time past the worker's 120 s read; the reply
path is fine. Both W1 trips (overture_bulk #1290, foursquare_bulk #1288) still open: Check and start again not pressed yet.

## Built (branch claude/loads-reply; the site part in `loads-reply/build/`)

- Worker 2.17.0 (`crawler.py`): `LOAD_BATCH` 100, each POST with `batch`; `load_batch()` sends once more on a timeout or
  connection error with the same number and a 240 s read, never on an HTTP answer; each failed or slow (>10 s) site call
  logged with path, size and time. `tests/test_load_batches.py` 8/8; the other worker tests pass (test_cc_columnar needs duckdb).
- Site: `OpenLoads::batch($pdo, …, $no, &$recheck)`: a taken batch answers its kept result with `again`; `recheck()`;
  `batchDone()`/`batchKeep()` on `open_load_batches` (migration `2026-11-24_open_load_batches.sql`). `loads.php` replies first,
  then rechecks (fastcgi_finish_request / litespeed_finish_request, as Weather.php and admin/api.php do), else rechecks first.
  `AuditClock::FLOWS` gauge-requests every hour. `tools/control-board-tests/load-batches.php` 10/10; run-all.sh only the 7
  known live failures; staging unit 65/65.
- **Held:** `staging-tables.php` (the new table's class) is claimed by Orders M1b (both FOLLOWS and OWN). Per CLAUDE.md, wait
  for M1b to go live, add only `open_load_batches` on the live copy (OWN), then zip, check and send.
