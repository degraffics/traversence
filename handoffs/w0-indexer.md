# W0 Indexer (decisions/0164 §1.4's note of 2026-11-17, R3, the W0 row)

Session: session_01CXLi5jAPsvr7mrCV6roykv, started 2026-11-17 on Jason's go (handoffs/session-manager.md, queue item 1).
Check-out session name: `w0-indexer`.

## State

- **Release 1 (site side): built, tested, sent to the inbox on branch `claude/w0-indexer`, waiting for Jason's OK** to
  merge. Built on Sync: 2026-11-17 12:57 UTC (mirror `33083ea`). Zip `releases/inbox/w0-indexer.zip`, 16 files.
- Release 2 (the worker): not started. Release 3 (the Railway service): ask Jason first.
- Jason's calls (2026-11-17, "ok. go."): chambers, tourism boards, organizations and other non-public sites → Read
  regularly by the machine (facts only as licence and terms allow); the key is sha1 (the page log's). Both noted in 0164.

## Release 1: what's in it

- `app/migrations/2027-03-12_w0_frontier.sql`: `crawl_frontier`, `crawl_junk` (hash, site, reason, pattern),
  `crawl_skip_rules`, `crawl_audit_log`, `crawl_batches`. New tables only; `crawl_*` follow live on staging.
- `app/lib/crawler/Frontier.php`: normalize (§1.1), `found()` (batches, keyed, receipt: count + sha1 over sorted sha1 of
  each address as sent), `pages()` (status routing §1.3: 200/304 read; 401/403/451/406/challenge hold the site for a
  person; 402 paywall a year; 404/410 gone, twice → junk; 0/429/5xx back off 2^tries), `work()` (pending best first, then
  due re-reads; 3 a site; 15-minute lease; etag from the page log), verdicts + `undo()` + audit log (a person outranks the
  machine), skip rules mined from junk (25 in a week), `compress()` (180 days, hash only), `size()`/`growth()`, `needs()`
  for Pulse, `tick()` hourly.
- Scores: site_score (not readable −20000: not Read regularly, held, junk, Tier 5, Facebook/Instagram; else tier 200/150/
  100/50, +400 a place we have, ±300 site verdict) + url_score (±300 verdict, +4 a link in up to 50, −40 a step of depth;
  deeper than `indexer_max_depth` from a trusted source −10000, waits).
- `app/lib/crawler/Sources.php`: `machineCall()` (nation → suggested/judged_by 'nation'; judgeOf; else needs its home
  page read; parked → ignore Tier 5; else read at its tier), `tierOf()`, `addFound()` (no cap), `sweep()` uses it,
  `addLinked()` goes through the frontier (no cap), `update()` (a person's call) rescores and audits; new columns tier,
  verdict, held_at, held_why, place (added on first use, like the others on that table).
- `SourceIndex::logPages`: links kept up to 5,000 (was 60), every link into the frontier, a page with places kept →
  promoted.
- `website/api/crawl/index.php`: GET work/settings/describe/holds (+ the worker's check-in, its Railway time on the
  ledger at INDEXER_USD_HOUR, default $0.005); POST found|pages|about|test → receipt.
- W0 in `Automation::WORKFLOWS` (row added paused by `Frontier::ensureWorkflow`), first in `SystemStart::ORDER`; its
  turn waits for the frontier and a check-in from Railway within 15 minutes; its gate counts matched receipts.
- Thresholds `indexer_*` (batch 250/25, 100 KB, 10 s, max 500, 3 a site, depth 3, link net 2,000, 180 days, 1,024 MB,
  $5 a month); Operations: Railway's caps and the W0 budget line, Pulse case `ops:w0` (receipts, blocked sites with
  Reactivate, flagged pages, size). Scheduler `frontier_tick` (hourly, AUTO, database only).
- Admin: Insights → The index (W0), `app/admin/index-console.php`. FAQ: "Does your crawler keep every link it finds?",
  the sources answer and the switches answer updated.
- Tests: `tools/w0-tests/frontier.php` (55 of 55 on MariaDB 10.11), `tools/staging-tests/unit.php` 49 of 49.

## Release 2 (next): the worker

`workers/crawler/indexer.py` beside crawler.py (imports its fetch, robots, `allowed`, `public_host`, pace, `site_call`
cookie and bot-check handling), one loop: GET `/api/crawl/index.php` (check-in), sleep when off or empty; fetch each URL
under §1.2 (robots, Crawl-delay ≥ 1.5 s a site, never > 30 s sites, held hosts, non-public paths), every `<a href>`,
canonical and sitemap `<loc>` sent as found (f = the page, t = link text), pages' answers (status, fingerprint, title,
why: robots/auth/challenge/parked/thin/paywall), describe new sites (post `about` even when the fetch fails, so they're
classified). Batches: 250 found / 25 pages / 100 KB / 10 s; 409, 413 or slow → halve and double the gap; an hour clean
grows back, never past 500; receipt compare → resend; 3 mismatches → slow (the site opens the Pulse case). Link net: a
page over 2,000 links sends them over several passes and flags it. Own Railway config (restart on failure), its own
service; crawler.py's 60/40 caps go (2.8.0). Then ask Jason before creating the service in gracious-sparkle.
