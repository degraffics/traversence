# C.5b: a guide's alerts and road reports drawn with the page (0145 V06; the launch plan's C.5, gate 2: the 1A guide shifts on /hub/)

Two files, built on **C.8's** `website/hub/index.php` (`handoffs/c8-open-now/`; E2 also carries hub/index.php, so the
later one is rebuilt on the live copy) and the live `app/lib/Roads511.php` (Sync 2026-11-27 16:23 UTC).

**What moved:** `#wx-alerts` and `#roads` (and, when a guide has no essentials of its own, the whole Essentials section,
`data-gd-wait`) were revealed only when `api/guide-live.php` answered, ~4.5 s after the page on live, above everything
below them. The weather card itself was already drawn from what's stored (`Weather::stored`), so it doesn't move.

**The fix:** the page draws what's already stored, asking no service: the alerts in force from the stored weather
(`$wxNow['alerts']`, an alert past its `ends` left out) and the road reports through the new `Roads511::stored()` (near(),
only when every feed it reads is stored, else null: nothing is fetched while the page is drawn). The live answer still
comes and brings them up to date: the same ones stay put (no second copy); an alert it no longer has goes (the words follow
the state); with nothing stored (a cold cache), it's as before.

**Measured** (`tools/design-tests/c5a/shift.cjs`, `LIVE_DELAY=3000 DELAY=-1`, St. Johns' area stand-in `cluster:5` with a
stored Winter Storm Warning and an SR 260 work zone, seeded by `tools/design-tests/c5a/seed-wx.php`):
390 **0.233 → 0.001**; 1280 **0.085 → 0.017**.

**Tests:** `tools/design-tests/c5a/hub-stored.cjs`: 20/20 at 390 and 1280 (drawn before the answer, Essentials not held,
one of each after it, an ended alert gone, an expired alert and an ended road report not drawn (near() drops items past their end as the page is drawn, Roads511.php:74, :100), a cold cache as before, no script errors).
Bench gaps healed (bench only): `page_cache.body` was VARCHAR(191).

**Screens** (`screens/`): before-/after- at the moment before the live answer and after it, 390 and 1280.

Design: approved (7cb1da9), its note covered and tested.
