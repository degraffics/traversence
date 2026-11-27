# R-D1: the launch area's loads (1A first)

Built on Sync 2026-11-27 19:50 UTC. Order OK: the session manager, 2026-11-27 20:19 UTC; 1A by slug (a), 20:22 UTC.
Slot: after W15 r1. Tier 4: the IT Manager (11), for the loads' order.

- `build/app/lib/crawler/OpenLoads.php`: `area()`, `zips1A()`, `due()` (the area first, then clusters as before).
- `build/app/ui/faq.md`: one sentence on the order, in "Do you use open business datasets?".
- `build/release.json`, `rd1.zip` (zip check: only waiting on r2-lanes-3's faq.md; re-apply on the live copy then).
- Test: `tools/crawler-tests/rd1.php <lib copy> <US.txt>` on MariaDB, 17/17.
- Claims: `app/lib/crawler/OpenLoads.php` (change, whole file: the header changes too), `::due` (change), `::area` (add).

Live read 2026-11-27 20:25 UTC (logs deleted): Round Valley (`cluster:2`, `cluster-1b-round-valley`) holds 85938, 85925
(Eagar) and 85927 (Greer), so Eagar and Greer load with 1A. `R2_PENDING` names that one slug until W15 r2 renames it;
the test fails once `cluster-1a-round-valley` is live and the constant is still there (`LIVE_SLUGS`).
