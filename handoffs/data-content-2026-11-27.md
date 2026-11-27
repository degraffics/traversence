# Data & Content: the lane's hand-off, 2026-11-27 ~20:40 UTC

*From the Data & Content build session (session_01CXLi5jsi4U8fUwJYPkZsMx) to the fresh session that takes over the
whole lane (the session manager's ruling, 2026-11-27 20:33: "a replacement for your whole lane, not an extra session").
Session manager: session_01CXLi5mGX2sg5QS11gg3Wrh. CEO: session_01CXLi5i9BvotZMgLbweXvpN. Design reviewer:
session_01CXLi5kADk38ozLLnwZ93TF. Mirror read last: Sync 2026-11-27 19:50 UTC. Everything below is cited; nothing in it
is live unless it says so.*

## 1. The brief

Data & Content for the Jan 1 launch (0181): E.2 (nations' land, done as research), D.1 (the area's 340 towns, 1A first),
D.2 (1A's backlog), E.1 (1A's guides to 70 and read: gate 3). Work silently; questions go to the session manager, never
Jason. Every ticket names its gate. $0 beyond 0181 and "No income, no expense". Never invent: cite the decision or the
live code; a gap is flagged with suggestions.

## 2. The queue (the session manager's global queue, 2026-11-27 20:32)

| Slot | Release | Where | State |
|---|---|---|---|
| #3 | **task-seed-4** (the goals as parent tasks, 62 children, #22 updated in place) | `claude/task-seed-4` @ **9eeb296d**, `handoffs/task-seed-4/` | Built, 20/20 (`tools/task-seed-tests/seed4.php`), zip check clean on Sync 16:23. Sends after R2 and writeOrder, before goal_check. **Re-fetch the mirror, re-run seed4.php and the zip check, then `send.py`.** If its slot comes before the hand-off, the old session sends it |
| #7 | **W15 r1** (Winslow's cluster on 86047) | `claude/w15-r1` @ 0e05df71, `handoffs/w15-r1/` | Built, 8/8 (`tools/w15-tests/winslow.php`). **To do before sending:** add `subzone = '1A'` to its INSERT (the subzone ruling below; the column comes with R-D1, so r1's insert sets it only when the column exists, or r1 and R-D1's migration order is arranged so the column exists first; decide and test). Re-check its migration against live just before zipping |
| #7, after r1 | **R-D1** (the launch area's loads) | `claude/rd1` @ 3252728f, `handoffs/rd1/` | Built, 17/17 (`tools/crawler-tests/rd1.php`), **but its 1A rule must change** (below). Claims: `app/lib/crawler/OpenLoads.php` (change, whole file) and `::area` (add), expire 2026-11-28 00:24 UTC: re-claim. Its faq.md line waits for R2 (#325) live, then re-apply on the live copy |
| after #7 | **Staging fixtures** | design in `handoffs/staging-fixtures.md` (main) | Design only. Build in its slot |
| later | **Health corrections** | from `research/listing-audit-2026-11-27.md` | One ranked ticket once the audit's first pass is ranked |
| later | **E.1 renews** (St. Johns, Round Valley, Show Low, Holbrook) | `claude/e1-st-johns` @ 43e9e71a, `handoffs/e1-*/` | Built, tested (`tools/e1-tests/st-johns-renew.php` 7/7), claimed earlier (re-claim). After the gate 3 data; cap resets Dec 1 |
| later | **J1** (jurisdictions reviewed by the team) | `claude/j1-jurisdictions-r` @ 2e7642e7, `handoffs/j1-jurisdictions/` | Built, 33/33 (`tools/jurisdiction-tests/j1.php`), Design approved, Tier 4 recorded. After gate 3. Rebuild faq.md and sections.php on the newest sync from the saved patches |
| later | **W15 r2** (slug redirects, Round Valley's slug, merges) | brief in `handoffs/w15-*` / earlier notes | Not built |

## 3. Rulings today (each the session manager's or the CEO's, 2026-11-27)

- **R-D1's 1A rule = (ii), a `micro_clusters.subzone` column** (20:31): regions.md's sub-zones (1A, 1B, 1C…), guarded on
  information_schema, safe twice; set '1A' only on clusters **1, 2, 47, 55** (St. Johns, Round Valley/Springerville &
  Eagar, Show Low, Holbrook), checked on live by id and name, citing regions.md, with `data_reason`; **W15 r1 sets
  Winslow's**. OpenLoads reads `subzone = '1A'`; **drop `R2_PENDING` and the slug rule**. Fixtures use **live's real
  slugs** (`cluster-1a-st-johns`, `cluster-1b-round-valley`, `cluster-auto-show-low`; Holbrook's to read), plus a test
  that a cluster without subzone isn't 1A. **Check micro_clusters' database first** (0110, `DataMove::GROUPS`). Tier 4:
  the IT Manager (11), with an independent check from Piece 2, both recorded in `handoffs/rd1/`. Keep the code comment:
  Eagar (200.3 mi) loads with 1A by its cluster, not the radius.
- **Winslow** (gates 2 and 3): no Winslow cluster exists (live read 20:17). W15 r1 places it; listings land with R-D1
  after the W1 trips clear (Piece 2's IT lane, targeted live Nov 29–30). **Target: listings Dec 1, gate 3's Winslow line
  about Dec 2–3.**
- **Staging fixtures = (a), both sell paths** (20:12–20:16): the test listing on Core (card, sandbox; card step waits for
  F.4) **and** a second "TEST · Direct-pay theater" on Free with pay at pickup; each one "TEST · Community night seats"
  ticket item; one public "TEST · Site checks group" stewarded by Test Steward; written in `StagingSite::finish()` with
  `@stg_test = 1`, refusing off staging. Tier 4: IT Manager (11) and CFO (4). Free can show Buy now with direct pay today
  (MarketBiz.php:36, Plan.php:18, MarketCheckout.php:200–210): **no pilot gap**.
- **Listing hours mismatch (Laguna Beach, Glendale):** the checker was wrong, our data right; **(b)** an open-now line on
  the listing page is Frontend & Shop's, passed with file:line.
- **Health corrections** (20:31): individuals become records, duplicates fold through the identity check, categories
  from NPI taxonomy only where the crawler set them, with Undo; one ranked ticket. **CMO (7):** the 123 individual
  providers become records attached to their practice or facility, under the privacy line, never a standalone place,
  never a home address; through the identity queue with Undo.
- **The Hometown Theater pilot** (0134 notes #1588–#1589; CMO's GO Code ruling): all in `handoffs/hometown-theater/`
  (offer, kit, trail, touch points, merged #1590–#1597). Listing #8807, St. Johns, unclaimed.

## 4. Open, for the next session

1. **ADHS's licensed list** for Summit Healthcare Urgent Care, Show Low (not in NPI, not in our listings): the next read.
2. **The full listing audit by about Dec 2** (#1620): `research/listing-audit-2026-11-27.md` has the first pass (C1–C7,
   #1625). **#1622 also holds that file with a section kept for this lane**: merge, don't overwrite. The plan: every 1A
   health listing and the top 50 by taps, one row each (source, identity, kind and why, About and its source, hours,
   confidence lines), counts per cause from live, where C4's departments and C5's individuals entered (file:line).
   Re-read the 8 entity rows lost in the first pass (#14779, 9207, 7332, 8938, 4934, 9359, 7750, 8985).
3. **R-D1's Google check for 1A** (0181 §3): no such check exists in the live loads; flagged, not built.
4. **W15 r1 and the subzone column's order** (§2 above).
5. **Data-content asks still logged:** Q7 and G1 (CO and UT nations' boundaries before their records).

## 5. Evidence on hand (all public or read-only)

- `handoffs/health-1a/counts-2026-11-27.md` (#1621): Show Low 3 missing / 17 wrong; Lakeside 0 / 2; 123 individuals.
- `research/listing-audit-2026-11-27.md` (#1625): #14779 traced (crawler.py:805, :1039; NpiBulk.php:46–71;
  IntakeStager.php:437; ListingConfidence.php:24–31).
- `handoffs/staging-fixtures.md` (#1610, #1611); `handoffs/hometown-theater/touch-points.md` (#1596, #1597).
- The NPI Registry API answers from this container (`npiregistry.cms.hhs.gov/api/?postal_code=…&enumeration_type=NPI-2`),
  and the site's public search (`/api/search_listings.php`, `/api/search.php`) is readable as a visitor.

## 6. Pitfalls (each from a real mistake here)

- **Live-read logs:** a big log is saved to a file; parse it, check the parse worked, *then* delete the file and the
  run's logs (`delete_workflow_run_logs`). Here a failed regex plus an early delete lost 8 rows' fields. Numeric `q`
  is read as row ids (up to 8); `rows` returns only labels and status; `data.php` searches only the first 12 text
  columns. One run waits at a time; a newer request cancels the waiting one.
- **Fixtures with invented slugs** hid a wrong rule (R-D1's `cluster-1a-` prefix missed `cluster-auto-show-low`). Build
  test data from live's real values.
- **PHP:** an array key that looks like a number becomes an int (ZIPs): `array_map('strval', array_keys(...))`.
- **Migrations:** DbUpdates splits on `;` (no `;` in comments or strings); records tables need named ids (`MAX(id)+1`
  with NOT EXISTS guards); JSON_SET re-spaces JSON, so `LIKE '%"renew":[%'` needs a compact CONCAT append.
- **The zip check:** a header comment counts as the file's "(top)": claim the whole file. Fetch origin/main before the
  zip check so claims made from this checkout are seen.
- **PR numbers:** read them from the create result before merging (another session merged #1621 by a guessed number).
- **Force-push is refused here:** add commits instead of rebasing a pushed branch.
