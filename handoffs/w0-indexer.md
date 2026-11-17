# W0 Indexer (decisions/0164 §1.4's note of 2026-11-17, R3, the W0 row)

Session: session_01CXLi5jAPsvr7mrCV6roykv, started 2026-11-17 on Jason's go (handoffs/session-manager.md, queue item 1).
Built on the sync of 2026-11-17 12:27 UTC (mirror `48237868`).

## State

- **Plan sent to Jason, waiting for his go.** Nothing claimed, built or sent yet.

## What the live code does today (read 2026-11-17)

- Worker `read_source()` (`workers/crawler/crawler.py`): a "Read regularly" source, 25 pages a run; same-site links kept
  60 a page (`links[:60]`); off-site links through `own_site_links()`, one per host, sent `[:40]` as `outlinks`.
- Site `/api/crawl/sources.php` → `SourceIndex::store/logPages` (page log `crawl_pages`, sha1 `url_hash`, links capped
  60 again on the site) and `Sources::addLinked()` (cap 40; new hosts land `suggested`, "Waiting for you", unless
  `judgeOf()` decides: public by address → read; commercial/social → cite only; nations → always a person).
- `Sources::sweep/toDescribe/describe`: home-page descriptions, then `judgeOf()`; undecided ones wait for a person.
- No frontier table, no `crawl_audit_log`, no verdicts, no receipts. `crawl_*` tables are "Follows live" on staging
  (`staging-tables.php` FOLLOWS_LIKE `/^crawl_/`), so new `crawl_*` tables need no new class line.
- Switches: `Automation::WORKFLOWS` W1–W14 (no W0), `SystemStart::ORDER` W1…W12, `pauseAll()` stops Railway's crawler
  schedule (`RAILWAY_SERVICE_ID`) only.

## The plan (as sent)

1. **Release 1, site side, shipped off:** the frontier (`crawl_frontier`, `crawl_junk`, `crawl_skip_rules`,
   `crawl_audit_log`, `crawl_batches`; add-only migration), `app/lib/crawler/Frontier.php` (normalize, store, rank,
   verdicts with undo, junk and skip rules, size guard, receipts), `/api/crawl/index.php` (work out, batches in, a receipt
   for each), the machine classifier replacing the "suggested" gate (nations' sites stay a person's call, R7), the
   40/60 caps removed on the site, W0 in Automation (switch, off), the register's budget line, Thresholds settings,
   Start the system (W0 first) and Pause everything, a Pulse case for receipt mismatches and flagged pages, FAQ.
2. **Release 2, worker:** `workers/crawler/indexer.py` (the continuous loop, reusing crawler.py's fetch, robots, pace
   and site_call) with its own Railway config (restart on failure); crawler.py's caps go (2.8.0).
3. **Railway service:** asked of Jason before anything is created; it stays stopped until Start the system.
