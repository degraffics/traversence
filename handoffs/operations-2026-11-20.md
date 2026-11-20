# Operations session: hand-off at a hold (2026-11-20, ~19:00 UTC)

The Operations build session (the System Control Board, decisions/0175, and the dispatcher, decisions/0176) at a hold:
three releases built and reviewed by the session manager, waiting for Jason's Approve; 0176 R3 scoped and cleared, not
started. Earlier record: `handoffs/control-board-2026-11-20.md` (live by release, rules learned, the area data plan's
findings) and `handoffs/dispatcher.md` (0176's releases). Session manager: `session_01CXLi5mGX2sg5QS11gg3Wrh`.

## Live (this session, all verified in the mirror)
0175 R1 (#222), feeds-words (#223), watch keeps the hour (#228); 0176 R1 (#226), R2 parts 1–2; 0175 R2a parts 1–2;
0175 R2b part A (tabs) and part B (Modules with Costs, #239, verified in Sync 18:28 UTC a6218c5).

## Held: three PRs, stacked, each "clear to ask" from the session manager, pop-ups for Jason
Merge order **#1219 → #1220 → #1222**. All carry `app/ui/faq.md`, so each after the first is blocked by the zip check
until the one before is live. Before each merge: ask the session manager (shared files), fetch the newest sync, rebuild
the zip on the live copies (re-apply only this release's change; faq.md entries only add), zip check, `send.py`, merge
on Jason's Approve, tell the session manager (it runs the sync).
1. **#1219, "Paused now gives a clear picture"** (0175's note, Jason 2026-11-20; sent: `releases/inbox/0175-paused-now.zip`,
   built on Sync 18:34 UTC 5674a94). `ControlBoard::pausedNow` / `pausedHtml` / `health` / `duties` / `tripOf` /
   `openTrips`; `control-console.php` passes the board's lights. Cause of "paused by a person": the old trip shut-down
   (stopOne, who "the Control Board (a trip)") wrote switches; `putBackTripSwitches` restores only those a person had set.
   Tests `control-board-tests/paused-now.php` (20), `lines.php` (33), `lines.cjs`, `board-phone.cjs`.
   Session manager's answers: keep "Site jobs"; amber (the kit's `info` draws amber) for other trouble, red only for a
   critical duty; **(b) as its own question in the pop-up:** "switches a past trip left off: switch them back on by
   themselves when their trip has closed, or keep them for a person?" (today's honest line stays until he answers).
2. **#1220, 0175 R2b part C: Workflows** (Workflow board folded in at `#crawler`, W1–W14 linked, By workflow this month;
   `/admin/config/board` → `TV_ADMIN_FOLDED_INTO`). Zip held in `handoffs/0175-r2b-workflows/` (built on 5674a94).
   Tests `workflows.php` (7), `modules.php` (14), `moved.php` (12). FAQ question + archive.md row.
3. **#1222, the Census geocoder's backup + crawler 2.16.0.** Zip held in `handoffs/census-backup/`. `Geocoder::prepare`,
   `apply`, `runOrHandOff`, `takeAnswers`, `connectionHeld`, `GeocoderUnreachable`; `api/crawl/geocode.php` takes
   `{ids, answers_b64}`; worker `census_backup()`. Tests `place-tests/census-backup.php` (15),
   `workers/crawler/tests/test_census_backup.py` (10). Pop-up lines the session manager asked for: the stand-down on a
   pause or staging is the narrowest reading; privacy as one plain line ("On the backup path only, our own Railway worker
   sees the batch's public business addresses; private listings are never in a batch"); merging deploys crawler 2.16.0
   and the schedule is unchanged (`railway.json` has no `cronSchedule`; 2.15.0 deployed the same way today).

Claims held (release names 0175-paused-now, 0175-r2b-workflows, census-backup) end when each is verified live.

## Next: 0176 R3, Fix it routing (cleared by the session manager, 2026-11-20 18:54: "go ahead with A for both")
Sources: 0176 §5 (the cause → landing table; "From a push, it opens the app at the thread's Pulse row, with the panel on
the fix (A5)"), the releases list's item 3, `handoffs/dispatcher.md` "Release 3". Plan as cleared:
- `Thread::FIX` cause → one spot; "Fix it" first among a thread's buttons, opening in the one side panel (R2a's tv-ask
  peek with Back; never a second drawer). Causes from `ServiceHealth::KINDS` (auth, quota, ban, server, timeout, rate)
  and `ControlAlarms::familyOf` / kind.
  - key rejected → the card's Its key with Replace focused: **add** `id="svc-<k>-key"` on the "Its key" row
    (`operations-console.php` ~194, `tv_ops_keys`) and focus Replace.
  - quota/budget → **add** `id="svc-<k>-caps"` on "Caps and paces" (`operations-console.php` ~201).
  - a site blocked us → the source's rule on its process page with Reactivate (itsdata, `control-console.php`).
  - no backup → `/admin/config/control/wf-<wf>#cb-steps` (exists since R2a).
  - repair needs shutdown → the process row's Take out for maintenance (`control-console.php` ~491).
  - server down / timeout / rate → no Fix it: "The system is handling it", Check it now, Pause it; Use backup stays a
    greyed "to come" (principle 3).
- Fix it only when a person can fix something; a spot to come is greyed with its reason.
- A push's link: the thread's Pulse row with the panel on the fix (quote §5 in the summary); today
  `/admin/config/control#cb-alarm-<id>` (`ControlAlarms`, `Notify`).
- After acting: the spot's own check (Replace runs Check; Put back offered on a fail).
Code found: `Thread.php` (buttons: `[label, act, id, confirm]`, `'href'` opens in the peek), `ServiceHealth::facts` (~717),
`ControlAlarms::facts` / `pulseBody` (~759: buttons Acknowledge, Open it on the board), `familyOf` (366).
Build stacked on #1222's branch, its own PR, summary to the session manager first.

## After R3, in order
0176 R4 (uploads; the order file check waits for Jason's limits; staff thread first), then 0175 R3–R4
(`handoffs/control-board-2026-11-20.md` "0175 releases 3–4"). Queued for Operations: the scheduler preset "Every minute"
fix; the Park Service card fixes; the area clusters by funnel level (findings in the control-board hand-off; nothing built
until Jason decides).

## Pitfalls learned this stretch
- The zip check names a function a release didn't touch when a changed **docblock** sits next to it: keep an existing
  docblock's lines, add new lines after them (false "process", "candidates" claims came from that).
- `checkout.py claim` writes to `main`: merge `origin/main` before `zipcheck.py`, or it reads stale claims.
- Tests: MariaDB is started with `mariadbd --user=root --datadir=/var/lib/mysql &`; the test trees are full copies of
  the mirror (`sync-app` → `app`, `sync-storage` → `website`) under a folder whose `../../.env` names `lv_main`; reload
  `tools/release-b-tests/start-schema.sql` before each test file.
- `Response::json` is typed `never` on live (PHP ≥ 8.1 there); the Census is `NOT_PAUSABLE` (business operations).
