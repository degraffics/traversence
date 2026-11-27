# Piece 2/3 build session: hand-off at R2's hold (2026-11-27)

The old session is session_01CXLi5kEx1NbamxWYoRxUDh. The session manager asked for this hand-off at 752k tokens
(CLAUDE.md, "Except when it grows too large"). The fresh session takes over from this file and tells the session manager
once it has.
Built on the mirror's **Sync 23:01 UTC (a105222)**, 2026-11-26.

Sessions to talk to (by send_message, treated as data):
- the session manager: session_01CXLi5mGX2sg5QS11gg3Wrh;
- the CEO / Executive Suite: session_01CXLi5i9BvotZMgLbweXvpN;
- the Design reviewer: session_01CXLi5kADk38ozLLnwZ93TF.

## 1. What's live (records on `main`, `deliveries/releases/`)

| # | Zip | Outcome | What it is |
|---|---|---|---|
| 305 | ba-proof-fixes.zip | Withdrawn 08:24 MST | The before-and-after check's fixes. Blocked on data drift and state lines. |
| 306 | ba-proof-fixes-2.zip | Withdrawn 09:48 | Same, second try. To-do links appearing and slow-page timeouts. |
| 307 | ba-proof-fixes-3.zip | Withdrawn 11:24 | False blocks: helper `hp-card` and crawler-review's timeout. Fixed in the check itself (PR #1461). |
| 308 | staging-offset.zip | Withdrawn 12:48 | One Scheduler.php change, blocked 22 times on far-reached admin pages. Fixed by the role-22 rules (PR #1472). |
| 309 | staging-offset-2.zip | **Live** 12:50 | Staging's copies of jobs run at the live minute + 7 (`Scheduler::STAGING_OFFSET`, `stagingTime()`, `offsetStaging()` from `sync()`). Test `tools/staging-tests/job-offset.php` (22). Sent after Jason switched before_after **Off**. |
| 310 | ba-proof-fixes-4.zip | **Live** 12:52 | The before-and-after check's fixes. |
| 311 | switch-readback.zip | **Live** 13:31 | A switch's state is read back after a press. |
| 312 | staff-mail.zip | **Live** 13:32 | Staging's mail is held (`Staging::holdMail`). |
| 313 | r1-tiers.zip | **Live** 13:38 | decisions/0180 R1: the five tiers and all 32 roles active (#1459). Its migration ran on live, and 25 History lines are there. |
| 314 | access-staff.zip | **Live** 16:00 | Security fix: with no staff session, `Access::who()` is `['', '']` on the web. Founder only on the CLI, or on an /admin path during 0100's switch-over. A failed staff lookup gives nobody. Upload targets for order staff need `Access::staff()`. Byte-checked on a105222; live-read run 74 read the admin pages as the test Executive (200). Build in `handoffs/access-staff/`. |

The check-only PRs merged on `main` (no zip): #1461 and #1472 (`tools/page-audit/beforeafter.mjs` and
`tools/piece3-tests/beforeafter.test.mjs`, 39 checks).

**R1 in detail:**
- `handoffs/r1-tiers/rostergen.py` generates the roster.
  - ACTIVE is 1–32.
  - History lines are signed "Jason (decisions/0180, #1459)": 7 "on standby → active" and 18 "added, active".
  - Tickets read "Routed by decisions/0180's appendix (#1460)".
- The removed constants stay as aliases, so the zip check's "earlier work removed" passes:
  - `TeamRunner::TIER3_AREAS = self::TERRITORIES`;
  - `TeamRunner::TIER3 = self::TIER5_WORDS`;
  - `ReleaseKind::tier3()` points to `territory()`.
- Tests: `tools/team-tests/r1.php` (35), `roster.php` (54), `roster.cjs` (44), `run-migration.php`.
- The tree's fingerprint is `dad19a7886a410654465758054f2efa38858a9a63396c37a382db7393e4723f2` (reach backend), shared by R1 and R2. Live's `team_settings` 'tree' still stores `0a2859…` until Jason approves the tree.

## 2. Held: R2, swimlanes and locks (decisions/0180 §5, §7, rollout item 2)

**Where:**
- Branch `claude/r2-design` at **12cd2526**, built on a105222.
- The design for review is `handoffs/r2-swimlanes/README.md`, its lane table citing the appendix's mandates.
- The build is in `handoffs/r2-swimlanes/build/`, the screens at 1280 and 390 in `handoffs/r2-swimlanes/screens/`.
- The Design reviewer gave "Design: approved" after its four fixes were made.
- No PR is open yet.

**Why it's held:** before_after is **Off** on live; Jason switched it off for #309. R2 changes what people see, so it
goes out with the check **On**. Turning it on is Jason's own press, at
https://traversence.com/admin/config/switches. This session refused to switch it through a release; don't. Check its
state with `live-read.yml`: rows `feature_switches`, q `before_after`.

**What it carries:**
- `app/lib/TeamRoster.php`:
  - `LANES`, `LANE_ROUTES`, `LANE_NAMES`, `lane()`, `laneOf()`, `laneName()`, `ticketTargets()`, `mayHandOff()`;
  - the lane is computed at read; a hand-off stores the lane it moved to.
- `app/lib/GaugeTasks.php`: `handOff($pdo,$id,$to,$note,$who,$actor,$tz)`.
- `app/lib/TeamRunner.php`:
  - `mayWork(array $t, ?array $role = null)`: one owner at a time, the lock lapses after `GAP_MINUTES`;
  - `pick()` uses it.
- `app/admin/control-console.php`:
  - the lane chip `.wq-lane` and "Lane: …" filter items;
  - ⋯ → Hand off, greyed with why for anyone but the Founder;
  - the "Handed off:" line, `data-wq-lane`, and the menu's max-width.
- `website/admin/api.php`: `qhandoff` (Founder only).
- `website/js/admin-control.js`:
  - Hand off is `TvAsk.choose`, then a required note;
  - the `lane:` filter;
  - the ⋯ menu is nudged inside the window, and a click outside closes it.
- `website/js/tv-ask.js`: `ask()` takes `o.required` (the notice sits inside the box, above the buttons).
- `app/ui/faq.md`: "What is a ticket's lane, and how is it handed to another lane?". It was re-applied on #314's faq.md.
- `app/migrations/2026-11-26_0180_r2_lanes.sql`:
  - guarded ADD `lane VARCHAR(40) NULL` and `handoff VARCHAR(400)`;
  - seven seeded tickets (§4);
  - two UPDATEs that fill `goal` (TEXT) only where it's empty, for R1's two tickets whose `why` live cut at 400 characters.
  - `release.json` carries the `data_reason` the UPDATEs need.
- Tests:
  - `tools/team-tests/r2.php` (41);
  - `tools/team-tests/r2.cjs` (32; needs the env `R1MIG` and `R1SEED`);
  - `board-router.php` (has `qhandoff`).

**To send once the switch is On:**
1. Fetch the mirror. If it has moved past a105222, rebuild every file from its live copy and re-apply R2's changes, faq.md most of all.
2. Claim the files.
3. Run `send.py`, then open the PR, merge it, and watch it (§6).
4. After it's live, check on live: the `lane` and `handoff` columns, the seven tickets once each, and the two `goal`s.

## 3. Next, in order (agreed by the session manager and the CEO)

1. **The Scheduler bundle**:
   - billing_tick and earned_storage_tick to :02;
   - the carry job left out of "slow";
   - the 6:13 spread.
   - The 6:13 misses' root cause isn't proven: `scheduler-missed.json` can't be read from here.
   - First confirm on live whether the 6:13 billing_tick and earned_storage runs happened on Nov 27.
2. **no-undo**: no Undo after an alarm's acknowledgement. It's built and not sent (`handoffs/no-undo.md`, tests in `handoffs/no-undo/tests/`). Rebuild it on the live mirror.
3. **R3**: the Tier 4 review recorded before live for high-risk areas, the Prior Ruling Check, and deadlock detection (decisions/0180 §6–7, rollout item 3).
4. **R4**: the advocates' (A1–A12) experience tickets. Each takes the lane of the screen it names (R2's table) (0180 rollout item 4).
5. **R5**: rule-based auto-rollback and the fidelity fallback (0180 rollout item 5; decisions/0179 Roll back).
6. **The "To: the CEO" channel** on the Work queue's + Add. It's built with Work queue phase 2's thread (`handoffs/work-queue-phase2.md`), after R2.
7. **The conflict release** (decisions/0165 note #1455):
   - auto Keep live ships **ON** for conflicts no person made;
   - a conflict a person made becomes a Work queue ticket in Ops & Reconciliation's lane (role 23, which reports to the IT Manager).
8. **The Storage tile's silent catch** (IT Manager's lane ticket, the session manager and the CEO, 2026-11-27, after #319's look):
   - **The fault:** `website/business/router.php:163–166` returns `''` from `catch (Throwable)` with no log, so `/business/`'s Storage used tile vanishes when `BizFiles::used()` or `Billing::allowance()` fails. It's a database read; the likely cause is catch-up mid-copy, unconfirmed because nothing is logged.
   - **The fix:** log `error_log('biz storage tile: …')` and draw the tile with its label and "Storage figures unavailable right now" (words follow the state).
   - **Delivery:** its own small release after the check release, b2-backups-2, r2-lanes-3 and the pipeline release. The Design reviewer sees it first. Its migration seeds the Work ticket in the IT Manager's lane, once, by `what`.
9. **A3's scope adds the staging night job's collation stop** (IT lane ticket, the session manager, 2026-11-27, from a live read of Scheduled tasks):
   - **The fault:** `staging_night_tick.php` (2:13 am Arizona) stopped at 09:13 UTC on `Illegal mix of collations (utf8mb4_general_ci,IMPLICIT) and (utf8mb4_unicode_ci,IMPLICIT) for operation '='` at `app/lib/StagingSync.php:608`, so last night's fingerprint pass didn't finish.
   - **The fix:** build it in A3 (staging holds still during a look, 0165's note) and test it against the 0165 suites (`tools/staging-tests/`). A3's migration seeds the ticket once, by `what`. The CEO's Tier 4 comes before sending.
   - **Words follow the state (the CEO, 2026-11-27):** Keep staging caught up's card (`admin/sections.php:1685`, `StagingSite::mirrorState()` at StagingSite.php:1311) says "(on): … A full check runs nightly" even when last night's check stopped. The fix in the same release: the card reads staging_night_tick's last outcome and says it stopped, with its error once and its fix there.
10. **The verdict read** (#320, 2026-11-27): the site waited on #320's after verdict for over an hour, because `DocsRepo::raw($path, true)` falls back to its kept copy (the before-done verdict) whenever raw.githubusercontent doesn't answer 200. The pipeline release's `DocsRepo::now` (the contents API with the token) is the fix, so it moves up to right after B.2 (the session manager, 2026-11-27).
11. **Webhooks' live proof** (IT lane ticket, the CEO, 2026-11-27, after the #314 re-audit):
   - **The gap:** the re-audit proved no webhook regressed from the code alone. Their last success on live couldn't be read, because /api/health.php needs HEALTH_TOKEN and live-read doesn't have it.
   - **The fix:** live-read, or the daily health snapshot, reports each webhook's last-received time and its 24-hour count, read server-side through HEALTH_TOKEN and never exposing the token.
   - **Jason's call:** whether live-read gets that read is his, when he's back (the session manager won't ask him over the holiday). Its migration seeds the ticket once, by `what`.
12. **Order from the session manager, 2026-11-27 (after #321 Live):**
   1. the pipeline release;
   2. r2-lanes-3;
   3. **"Recovered" only for a notice that was sent:** its own small release. 14 "Releases (job): Recovered" emails went out with no raise ever sent, because ControlAlarms.php:607 sends Recovered whenever `tripped_at` is set. It goes with a test. **Tier 3, in the IT Manager's (11) lane** (the CEO, 2026-11-27): not one of 0180 §4's territories, and Founder alarm emails aren't territory 5's messages; a bug fix inside 0176 R1. The ticket records the lane and the ruling, with a daily-batch line;
   4. task-seed-4 (data only, the list from the session manager);
   5. A3, with the collation fix and the card's words;
   6. the Scheduler bundle: Pre-flight's self-continue, DailyReport keeping its items with sources in its `dailyrep:` record (Executive-readable), the :02 move, the 6:13 spread;
   7. phase 2 release 1 (Tier 3, the PM's lane: the thread, task-notes.json, task-rulings.json, the four-stage redesign, and role queues with counts and SLA, jason-spec §7);
   8. the Executive channel;
   9. a parallel week, then cutover about Dec 15.
13. **Gates first (CLAUDE.md #1553, #1554; 0181 §1–2, 2026-11-27):** a build goes ahead only when it names the 0181 gate (1–6) it moves, a live harm to people or money, or the revenue goal or money it serves, with the link stated. This re-sorts item 12:
   1. #322, the pipeline release (gate 4);
   2. r2-lanes-3 (Monday's rollout slot);
   3. gate 4's work: B.2's nightly backups and restore drill; the Scheduler bundle (jobs on time for 7 days, Pre-flight's self-continue), built from the live Scheduler.php only after Frontend & Shop's goal-check release is live; A3 only if a withdrawal blocks gate work;
   4. after Monday's four: phase 2 release 1, the Executive channel, and the AI items 1, 2 and 4.
   - **Phase 2 release 1 also takes a task's documents** (the session manager, 2026-11-27; 0127's note, #1580): a new upload
     target, "a task's documents", in `app/lib/upload-targets.php`, owned by GaugeTasks. `task-notes.json` gains an optional
     `file`. The hourly pull fetches it through DocsRepo's contents-API read (never raw) and stores it through `Uploads.php`
     on the task, with Open and Download on the task's page. Files belong to the task, never a person. Its table is named in
     `staging-tables.php` (R14), and the one-uploader grep still matches only `Uploads.php` and `api/upload.php`.
   - **Logged for the IT Manager's (11) lane** (the session manager, 2026-11-27): `/admin/crawler-review.php` times out on
     staging's before looks (#322 and #323's looks; it also timed out in #305's). A slow admin page; the check now leaves its
     drift and layout out when its before looks time out, and the 3rd release in a row becomes a ticket (`priorSkips`).
   - **Logged under the promise check (gate 1), fixed when that check is built, not before** (the session manager,
     2026-11-27): `tools/notify-tests/` no longer runs against live's Notify (Notices isn't required there; its comms
     migration ran and was deleted on live, kept in `notify-1.zip`), and it writes its stand-ins into the app folder it's
     given (run it only on a throwaway copy). Bring it up to live so the promise check can lean on it.
   - **Held for after the goal check:** founder-link (`claude/founder-link`, `handoffs/founder-link/`). The Founder's
     message emails open their own `link` (ours only) or their `task` (`#wq-<id>`); gauges.py warns on neither (the session
     manager's option (a), 2026-11-27). IT lane.
   - **Parked:** the "Recovered" fix (no gate; #322 removes its cause).
   - **20:00 UTC daily to the session manager:** each item with its gate and the revenue or money it serves, plus gate 4's status with its evidence.

## 4. Seeded tickets (R2's migration; each added once, by `what`) and their lanes

The lane is read by `TeamRoster::laneOf()`: the first match in `LANE_ROUTES` wins, otherwise the Project Manager.

| Ticket | Lane |
|---|---|
| The Scheduler bundle: two jobs to :02, the carry job out of slow… | IT Manager (Scheduler, jobs `*`) |
| No Undo after an alarm is acknowledged | IT Manager (ControlAlarms, /admin/control) |
| Deadlock detection in the Tier 4 review (R3) | IT Manager (TeamRunner) |
| The zip check refuses seed text longer than its column | IT Manager (words: zip check, tools/page-audit; tested in r2.php) |
| The kit's choose and ask boxes have no close button (×) | Design reviewer's note. Expected: Editorial Director (design words). Check on the Lane filter after R2. |
| The Work queue's rows at 390 sit 15px from the edge, not 3px | Design reviewer's note. Expected: IT Manager (/admin/control). Check after R2. |
| The Work queue's filter greys an item with none | Design reviewer's note. Expected: IT Manager. Check after R2. |

The three design notes are tickets, not fixed in R2 (the Design reviewer agreed). The live Team counts can't be proved
through the data search (#1416 held); Jason checks them on the page.

## 5. Open Founder presses (Jason's own; never pre-seeded, never worked around)

At https://traversence.com/admin/config/team:
- **Approve the decision tree.**
- **Raise to Building.**
- **Raise to Implementation.**

At https://traversence.com/admin/config/switches:
- **before_after On.** It gates R2.

This session's safety check twice refused to pre-seed the tree approval or a ceiling raise. A later rule change clears
an approval, which then needs Jason again (CLAUDE.md, Standing OKs).

A scheduled reminder, trig_01KTAkovjobXQoRqo4rVTCJq, fires at 10:00 UTC on Nov 27. It checks #308, which is moot since #309
is Live. Answer it with that and delete it.

## 6. The release watcher (fixed)

- Find N from "Release #N: <zip> taken from the inbox" on `main`.
- Then grep the whole log for "Release #N: (Live|Withdrawn)", every 20 s.
- Never start the watch window after the send: #310's Live landed a second before its "taken" commit, and a watch keyed on time missed it.
- Zip names can't be reused, so a resend gets `-2`, `-3`, ….

## 7. Zip check and data lessons

- **NOT_ADD_ONLY's CHANGE regex** reads words inside SQL strings: "change that", "CHANGE are", "MODIFY x", "RENAME TO", "TRUNCATE". Keep them out of seed text. R1 was refused for "change that" and reworded.
- **Earlier work removed**: removing a constant or function is blocked without `--drops` and Jason's OK. Keep an alias instead.
- **VARCHAR(400) cut:**
  - `control_gauge_tasks.why` is VARCHAR(400), and live's MySQL is non-strict, so longer text is cut silently;
  - the bench's strict mode refused it instead;
  - `run-migration.php` and `r2.php` now set `sql_mode='NO_ENGINE_SUBSTITUTION'` to behave like live;
  - long reasons go in `goal` (TEXT);
  - there's a ticket for the zip check to refuse seed text longer than its column (§4).
- **The `!preg_match` test bug:**
  - `!preg_match(...)` passed when the pattern failed to compile;
  - write `preg_match(...) === 0`, and check each UPDATE explicitly.
- **The mirror behind `main`** blocks a zip: re-sync first.
- **A conflict in `deliveries/README.md`** at merge: resolve it by hand, keeping both sides.
- `send.py` takes `--allow-pending` (a comma list) and `--session`.
- **Merge only by the number the create call returns** (2026-11-27): twice a merge was sent by a guessed PR number
  before the create call came back. #1536 was already merged by its owner. #1570, the session manager's own, was merged
  in the same second as the session manager's merge, so nothing was lost. Never merge a number you didn't get back.
- **A resend whose predecessor was withdrawn:** mark the old `deliveries/<zip>.json` `"status": "replaced"` with
  `replaced_by`, or the zip check blocks the resend as another zip in flight. Add `"replaces"` in `release.json`.
- **Match a live-read run's READ and ORDER_ID before reading or deleting its log** (2026-11-27: runs #131 and #133,
  someone else's, were deleted by mistake).

## 8. Live reads (`live-read.yml`)

- It's read-only. Delete each run's log once you've read it.
- Its guard stays: tables matching user|member|staff|contact|client|order|payment|vault|message|mail are refused.
- Never invent another access path.
- CHECK_ACCOUNTS' credentials never appear in code or chat.
- Its concurrency group keeps one pending run, so queue reads one at a time.
- The data search doesn't cover status columns.
- A query of digits is read as row ids.
- The proxy refuses an artifact blob download (403), so read the job's log instead.

## 9. Bench and harness

- MariaDB: restart with `(mysqld_safe --user=mysql > /dev/null 2>&1 &)`. The user is `tv`/`tv`; each bench has its `.env` at `/home/user/bench/*/.env`.
- The Piece 2 seed is `/home/user/bench/r1/seed`.
- R1's migration is `/home/user/bench/r1/root/app/migrations/2026-11-26_0180_tiers_roster.sql`.
- R2's bench is `/home/user/bench/r2/root` and `/test`.
- The send worktree is `/home/user/tv-send`. The mirror is `/home/user/traverse-3way-sync`, shallow and sparse.
- These are in the old container only. A fresh session rebuilds them from the builds in `handoffs/` and the tests' READMEs.
- r2.cjs lessons:
  - the bench router needs `qhandoff`;
  - a row past the first page is hidden, so click `data-wq=all`;
  - dispatch the outside click on `body`;
  - create `feature_switches` on the bench;
  - never put a `//` comment mid-line in inline JS.

## 10. Rules learned (each from a real miss)

- Live-site actions only on Jason's explicit call.
- Switching before_after, approving the tree and raising the ceiling are his presses alone.
- Standby/active stays the Founder's (#1460). The CEO doesn't reassign work. Tickets route by 0180's appendix.
- Tell the session manager before changing TeamRunner's rule data: STEPS, STEP_AT, CEILINGS, the TERRITORIES data, TIER5_WORDS, TIER1, STEP_COST, reach.
- These hold:
  - the reach fence stays Backend only;
  - $0 spend (the CEO's $20 aside);
  - the hard money line;
  - Tier 5 only on its real triggers.
- Migrations only add.
  - A migration that writes rows creates nothing that already exists (#284).
  - UPDATE or DELETE needs `data_reason`.
  - Every new table goes in `staging-tables.php` (R14).
  - Every outside call asks `StagingGate::hold()` first (R13).
  - Write for PHP 8.0 and MySQL 5.7.
- Merge only the PR number the create call returned.
- Every release that changes what people see gets the Design reviewer's "Design: approved" first.
- Never invent what's set: cite the decision. A suggestion is labelled "Suggest a change to <decision>".
- A check that blocks wrongly is fixed in the check, with its own test, never worked around in the release (#307, #308 → #1461, #1472).
