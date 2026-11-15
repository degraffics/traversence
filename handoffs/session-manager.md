# Hand-off: the session manager

For the Claude session that manages Jason's other sessions (CLAUDE.md, "The session manager"). Written 2026-11-14 by the
first one, moved to a fresh session at 600K context. Jason works mostly from his phone: short messages, one line per
item, decisions as "reply go".

## The job

Keep sessions fast and affordable, and keep Jason told only of what needs him.
- **Watch:** context size (hand off past ~400K), restarts, cost, sessions waiting on Jason, release clashes, file claims
  (`tools/page-audit/checkout.py list`, decisions/0161), stuck syncs (traverse-3way-sync `sync.yml`), Railway deploys.
- **Propose, Jason approves:** splitting, a fresh session per phase (the old one updates its `handoffs/` file and stops),
  starting a session for a problem he reports. At most about three working sessions at once.
- **Without asking:** archive a session that has said it's done or handed off, work in `main`, nothing waiting on him
  (Jason, 2026-11-14). Never archive one waiting on him, the session manager itself, or "Community Build" (he keeps it).
  If the permission check refuses an archive, ask him for "archive it".
- **Relay:** when Jason answers here for another session ("merge it", a choice), send it to that session with
  `send_message` in his words. Never approve anything on his behalf.

## How the checks run

- **Hourly, while he's checked in:** a scheduled task named "Hourly session check" (cron `14 * * * *`) fires into the
  session manager. "Checking out" disables it; "checking in" (or "check in") enables it. Its prompt lists every check.
- **Notify** (PushNotification) only when something needs him: a session waiting, or a proposal. One line.
- **Claims check without stray files:** `git status -s` empty, then `git checkout -B <own branch> origin/main`, push it,
  `checkout.py list`. Never leave main's files as uncommitted changes (the stop hook complains).
- **Railway:** a token for `backboard.railway.com` is saved on the "cloud" environment; query
  `https://backboard.railway.com/graphql/v2` (deployments, `deploymentLogs`). Project "gracious-sparkle": services
  `traversence` (the crawler worker, cron every 5 minutes) and `video-worker`. "CRASHED" on a cron service means a run
  exited non-zero; read the logs before anyone presses Restart.
- **Reading another session cheaply:** `get_session` (size, restarts, cost, last summary) and `list_events` with
  `kinds: ["result"]`. Large results land in a file: parse with python, don't load them whole. Reading never wakes it.

## Rules Jason set (all in CLAUDE.md)

- Every build is a release through git; he approves each merge in chat; no zips for him to upload.
- Both repos: sessions add `traversence` / `traverse-3way-sync` with add_repo without asking. The permission check may
  still refuse a session's own add or `create_session` with pre-approved tools: then he types "add the sync repo" there.
- FAQ clashes resolve themselves; check-out per 0161; "how it works" questions go to the documents, not to him.
- Cost: on Max 5x ($100/month); `cost_usd` on sessions is API-rate equivalent, not a bill. Watch the 5-hour window.
  The archived "Get" session is 903K context (~$2,700 equivalent): read its record, don't wake it.

## Paused (2026-11-14)

- **`decisions/0163` and `decisions/0164` are accepted** (Jason, 2026-11-14: "all approved"; no shadow mode; 0164 §14
  settled). **The crawler and the automation stay paused** until the build stages (0163 §8: operations stages 2–6, then
  7–11 for the Knowledge Engine's new parts) and the switch-on check (0163 §10) are done and Jason switches them on.
  Each stage is a release he approves, one session per stage from its handoff (`handoffs/crawler-revamp.md`).
- **The Railway crawler's cron** was removed through the API (~15:55 UTC); `railway.json` still says every 5 minutes, so
  a worker deploy restarts it (details at the top of `handoffs/crawler-revamp.md`); the automation master switch (release
  #48) makes any run that starts end at once. The hourly check expects no crawler runs meanwhile.

## Where things stand (2026-11-15, 12:30 UTC)

- **Done:** stage 1b, data cleanup, live as releases #50 to #54 (`handoffs/data-cleanup.md`, finished).
- **Running:** "Release A: operations, stages 2–6" (one session, one release, `"review": true`), with story-only
  landmarks and drop-offs out of place counts. When it's live it writes `handoffs/release-b.md`. The session manager
  then starts **Release B** (stages 7–11, shipped off) in a fresh session (Jason, 2026-11-15: "2 builds").
- **Queued after Release A** (Jason, 2026-11-15: "do that after Release A"): one session checks all 62 names in Pulse
  case #31, "Database tables and columns no code uses". It checks each one against the live mirror, including names
  built in code. It sends one release that drops the confirmed leftovers (copied first, decisions/0162, listed under
  `"removes"`). Anything doubtful goes to Jason to keep. It must not overlap Release B's files.
  The same session also clears Pulse case #22, Site health (Jason, 2026-11-15: "add them to the cleanup session"):
  - **D06 drift false alarm:** the check counts a migration that ran and deleted itself as a file removed without a
    release (`2026-11-14_crawl_service_calls.sql`). Teach it to ignore that (`tools/page-audit/drift.py`, `db_updates_log`).
  - **J03, "open now":** none of the results says it's open (proposal P10, approved).
  - **V06, layout shift 0.54:** on `/hub/?cluster=…` after the "swiming" typo search.
  - **D03, Scheduled tasks bad:** guide weather and roads fails nearly every run (proposal P7, approved).
  - **V01, sideways scroll at 390px:** on `/admin/market/gateways` (P12, approved). Check `/admin/config/services` after
    Release A, which rebuilds it.
  - **`staging_carry_tick`:** hasn't run since 2026-11-12; find out why.
  - **Left alone:** Crawler bad, NPI Registry warn and Search learning warn come from the pause and clear at switch-on.
- **Waiting on Jason:** Release A's question on the master switch. It stays refused until Release B's checks exist
  (0163 §10); the session builds it that way unless he says otherwise.
- **Jason's own, any time:** post the OSM forum statement (`research/osm-overpass-2026-11-13.md` §2). Mark the Overpass
  mirror cases resolved: their last failures were Nov 14, before the pause. Keep overpass-api.de's case open. Storefronts
  step 5c on his go.
