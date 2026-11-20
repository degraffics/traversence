# The System Control Board session: hand-off, 2026-11-20 (everything held)

Jason, 2026-11-20: "once release is live hold building anything more", then "every session is held … hand off to new
session so we can reduce token use without logic loss … make sure all governing docs are updated and that we can
effectively restart building." This file is the whole state of this session's area: what's live, what's held and its
sources, what to watch, the open questions and the lessons. Read `handoffs/builder-playbook.md` first, then this, then
`handoffs/control-board.md` (the board's history, releases 1–5 of decisions/0171).

## How every build works now (the rules learned the hard way, 2026-11-20)

- **Sourced form.** Every behaviour in a build summary sits on its own line with its source: a decision and section, or
  Jason's quoted words with their date. Anything without a source is removed or asked as a question. The session manager
  reads each cited line before clearing. Check each line against the text yourself, never from memory (Jason: "Why is
  this session inventing things instead of check what's already set by adr?"; "We dont need to reinvent rules").
- **His answer becomes the source:** write it into the decision as a dated note, quoting him, before building.
- **Ask with an accurate picture.** A question built on a wrong premise gets re-asked (the Census backup, below).
- **Never flip a switch from the system.** A fault pauses (decisions/0175 §2–3); only a person's press changes a switch.
- **Claims tie to the release.** `checkout.py claim` per file, then `send.py`; a claim left open after its release went
  live blocks others (ask its session to release it).
- **faq.md is shared:** only one release carrying it can wait in the inbox. Whichever merges second re-applies its own
  entries on the newest sync. Before zipping, fetch the mirror; before merging, check the newest sync again.
- **Branches:** start each from the newest `origin/main` (the delivery log there decides the zip check's holds). After a
  rejected or replaced send, rebuild the branch from main with only the final files; never rebase over old inbox commits.
- **Workers deploy from main** (Railway's `watchPatterns: /workers/crawler/**`). A worker change that needs a site change
  merges only after the site's release is live. Watch the deployment by id (`deployments(serviceId: 8d254c0d…)`), not
  the newest row: commits outside the worker show as SKIPPED.

## What's live (this session, 2026-11-19 → 20)

| Release | What | Decision |
|---|---|---|
| #207 | staging guard (`StagingGate::here()` public) | 0165 |
| #209, #210, #212 | 0173 R1 One home, R2 the key on its card, R3 defined once | 0173 (complete) |
| #214 | push-polish: one push per thing, a pace per person, badge, app manifest | 0172 |
| #216 | board-phone: the board at 390px; a switched-off task's Switch on on its card | 0171, 0058 §11 |
| #222 | 0175 R1, Paused by the system: a trip places a pause, never a switch; it lifts when its check passes (Recovered, escalated or not); switches a trip wrote into put back | 0175 |
| #223 | feeds-words: a trip's hold says "Paused by the system (a trip)"; a wait that's over says so; S3 SlowDown is a rate wait | 0175 §2, playbook §4, 0171 5a |
| #224 | quiet-jobs: `system_start_tick` in `Scheduler::QUIET`; quiet jobs are never "late" | Scheduler::QUIET |
| #225 + crawler 2.15.0 | Common Crawl's bulk reads from its URL Index (site keeps the file map in PageCache `cc:map:<crawl>`; worker PR #1159, Railway deployment cbad9a84) | 0164's note, 0163's note (`CC_MAP_PER_RUN`) |
| #228 | w1-findings: the watch keeps the hour (`ControlAlarms::expireTries`); a handled Review item says what happened; a critical part's words say Recovered | 0171's note "Keep the hour", 0175 note |

Tests added: `tools/control-board-tests/` `board-phone.cjs`, `task-on.php`, `paused.php`, `feeds-words.php`,
`quiet-job.php`, `expire-tries.php`; `tools/listing-tests/handled-review.php`; `workers/crawler/tests/test_cc_columnar.py`.

## Things to watch

- **Crawler 2.15.0's first Common Crawl run.** Railway logs should show "Common Crawl CC-MAIN-2026-43: its URL Index map,
  N of 300 file(s) read; the reads start once it's whole" for about 8 runs, then "… site(s) under az.us …". The
  commoncrawl task runs only when the plan hands it out. If it holds data.commoncrawl.org, the issue is on its card.
- **Geofabrik's first night load** (1–5 AM Arizona; bulk tasks run only then). Report Arizona's rows and MB (and the
  database size, `site-audit/results/server.json` `db`) to Jason. Its task `geofabrik_bulk` should be on after 0175 R1's
  put-back (Jason last switched it on).
- **The put-back and the board's watch** (`control_tick`): the session manager asked Jason for a screenshot of
  https://traversence.com/admin/config/scheduler#job-control_tick to confirm the watch runs every 5 minutes.

## What's held (build only when Jason says)

### 0175 release 2a: the board as the one place to act (scope cleared by the session manager, 2026-11-20)

Each item with its source:
1. Every alarm's main button opens its fix in the side panel (card, key, pre-flight line, task switch, Take out/Put
   back, Activate/Pause/Resume/Deactivate). 0175 §6; Jason 2026-11-20: "The board should provide me direct action
   capabilities to fix issue, by using, drawers, pop-ups, side-panel".
2. One panel at a time: a link inside a panel replaces it, with ← Back; never stacked, never leaving the board. 0175 §6.
   **Built, unsent:** branch `claude/0175-r2a-wip`, `handoffs/0175-r2a-wip/` (tv-ask.js) and
   `tools/control-board-tests/one-panel.cjs` (12/12). The dispatcher session's Fix-it releases reuse it.
3. The board's top line becomes live diagnostics (what's paused now, by the system or a person, and why, each opening its
   fix); the pre-flight snapshot moves to the Start-up stage. 0175's note "pre-flight before a module is active,
   diagnostics once it is" ("once something is active it is not longer preflight, it is diagnostics").
4. The same line at the top of Automation, replacing "Now: Everything is on" (false while tasks are off). 0175 §6;
   builder-playbook §4 (words follow the state).
5. Active modules say "Diagnostics", never "pre-flight"; "Check before switching on" only before Start or while the
   master is off. 0175's note.
6. A need on its backup is a working state: never "Holding", never red, its backup named. 0175 §3; Jason 2026-11-20:
   "if a workflow has a component that is failing it needs to either reroute to a back up service or trip". Find why
   W1's row read Holding (`ControlBoard::boardLight` turns a 'bad' row light into 'hold').
7. A process row's alarms show their level's mark, not a green ✓. Playbook §4.
8. A task's switch on its workflow's page, with each step's services and backup; the card lists what it serves and links
   there; Automation's Tasks table becomes one line per workflow. 0175 §6 (his "Workflows are multi-step tasks …"); 0163 §9's note.
9. The route line's stale "next release records what passes along each hop" names the Ledger instead (built, in the wip
   branch). Playbook §4.
10. No Acknowledge on a warning the system is still handling. 0171 3c's note ("only a trip waits for a person"); his
    words in 0171's critical note ("This is a notification, not need for me to acknowledge").
11. The trend strips say what they count: uptime is hours green or amber over hours due (`ControlHistorian::trend`), the
    same for a system and a process; a system's strip paints an hour red if any one of its parts was red, and counts
    "Holding" as up. Label each strip, show a system hour amber with the cause named ("1 of 20 held: Common Crawl").
    Playbook §4. **Open question for Jason** (below): when a system hour turns red.

Files likely: `admin/control-console.php`, `lib/ControlBoard.php`, `admin/automation-console.php`, `js/tv-ask.js`,
`lib/ControlRegister.php`, `lib/ControlHistorian.php`, `ui/faq.md`. If it needs `OutsideServices.php` or
`SystemStart.php`, ask the session manager to order it after the dispatcher's 0176 R2, which claims them. A split is
allowed: panels (1–2) first, then the words and lines (3–11).

### 0175 release 2b: one control board in the menu (last)

0175's note (Jason: "arent we combining things into one control boards …", "yes. fold into 2"):
- **System Control Board:** Alarms, Logbook, Ledger, Upkeep and Reports as its tabs; Scheduled tasks as a tab;
  Automation's master, Start and Pause everything as its Start-up and Shut-down stages.
- **Modules** (the Service register): each card holds its stage-2 pre-flight and its caps, budget and cost (the
  Pre-flight and Costs pages today). The system pre-flight goes into the board's Start-up stage.
- **Workflows:** the Workflows and Workflow board pages merge.
- **Permissions and Data separation stay.**

Every old address redirects to its new spot; old sections are archived in `archive.md`; every behaviour is listed with
where it moved; a drop needs `--drops` and his OK.

### The Census backup (Jason: "Yes, build it", re-asked with the corrected picture)

0175 §3's note: there's no Railway path today. The crawler's `geocode()` asks the site, which calls the Census
(`/api/crawl/geocode.php` → `Geocoder.php`). The backup: when the site's call to geocoding.geo.census.gov fails on the
connection (reset or no answer), the worker sends the batch to the Census itself (`addressbatch`) and hands the answers
to the site to place; a batch neither can send waits. Files: `workers/crawler/crawler.py` (geocode), `api/crawl/geocode.php`,
`lib/Geocoder.php`. Order: after the dispatcher's 0176 R2 (it claims `ServiceHealth.php`) and after 2a.

### 0175 releases 3–4

R3: the eight stages and the actions on each card (the strip; Activate, Pause, Resume, Deactivate, Delete; the register's
stage filter in his words). R4: one definition (`level`, `kind`, `touch_points`, `repair`, `fallback` in
`service-defs.php`; the zip check refuses a module without them; the appendix's hooks "to come" built as needed). Then
0169's App store builds on them.

## Open questions for Jason (each sourced)

1. **When should an hour on a system's strip turn red?** No decision sets it. Options: only when a critical part
   (search, sign-in, payments, page load, database) is down (recommended, 0171's duties); any part, labelled; more than
   half the parts. Ask it in 2a's pop-up.
2. **The dashboard-ideas discussion** (queued by the session manager; not started).

## Decisions touched

- 0163: note on `CC_MAP_PER_RUN`; §9's note (task switches move to their workflow's page).
- 0164: note on Common Crawl's URL Index (built: #225 and crawler 2.15.0).
- 0169: note (the App store builds on 0175).
- 0171: notes on a fault pausing (0175), on a nightly task keeping the hour (w1-findings).
- 0173: note (stages renamed to 0175's eight). Complete.
- 0175: accepted; R1 live (#222); notes: Trip and fault, pre-flight vs diagnostics, escalated → Recovered, the Census
  backup (corrected, then "Yes, build it"), one control board in the menu. R2a, R2b, R3, R4 held.
