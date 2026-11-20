# Hand-off: the dispatcher (decisions/0176)

decisions/0176, "the dispatcher presents and routes every thread", was accepted by Jason on 2026-11-20. This is the
complete record for the next session that builds it. Written by the dispatcher session
(session_01CXLi5hJ4C32FpJfNTaTa2z) when Jason held every session to save usage (2026-11-20: "review each session and hand
off to new session so we can reduce token use without logic loss").

**Read first:**
- decisions/0176 (whole);
- 0172 "The message" and §4;
- 0171 §5 and §7, and their notes;
- 0175 §2–3 and §6, and its notes of 2026-11-20 ("Trip, and fault", "an escalated trip closes as Recovered",
  pre-flight vs diagnostics);
- 0127;
- handoffs/builder-playbook.md (§3: every behaviour has a source, or it is a question).

## Where it stands

| # | Release | State |
|---|---|---|
| 1 | **The thread and the shape, for alarms** (`0176-thread.zip`) | **live as #226**, verified on Sync b94c979 (14:37 UTC) |
| 2 | Service issues as threads | **built** (Jason restarted it, 2026-11-20); `0176-issues.zip`, built on Sync 16:32 UTC, sent by the Operations session |
| 3 | Fix it routing | not started; waits for 0175 release 2a's one-panel drawer (the Control Board session) |
| 4 | Uploads name their thread and landing spot | not started; the order file check waits for Jason's limits |

Restart only on Jason's word. Then build R2, R3 and R4 in order, each its own release, reviewed by the session manager
before the pop-up.

## Jason's answers on 0176 (2026-11-20, in the decision pop-up; recorded at the end of the decision)

1. **Approve.**
2. **Keep post first.** A visitor's photo of a place stays a journey, live at once, with reports after (`Journeys.php`).
   Staff get a thread, "New photo of <place>", offering **Hide** (`Journeys::setHidden`).
3. **"I'll set the limits".** Customers may replace a file on the order's link. He gives the file check's minimum
   resolution, bleed and size. **He hasn't given the numbers yet.**
4. **Incidents first.** Alarms, service issues and uploads move to the one shape. Comments, bookings and groups follow in
   a later release.

Also settled in review (the session manager, from the sources): who is told is unchanged by 0176.
- A fault the system is still trying to fix goes to the board and card only.
- Pulse, email and push go to:
  - held or out, at once;
  - an escalation;
  - a critical part's first failure.
- The sources: 0171 §7's note of 2026-11-19 (control-board-routes); 0175 §3.
- The brief's "a push only for critical parts" was loose wording, not a rule.

## Release 1, live (#226): what it does

- **`app/lib/Thread.php`** (new): the one shape (0176 §2).
  - `Thread::html($facts, $surface, $buttonsHtml = null, $endpoint = '', $affectsHtml = '')`.
  - `Thread::push($facts)` gives the title and status.
  - `Thread::text($facts, 'pulse'|'logbook')`.
  - Facts: `title, level (trip|warning|notice|closed), pill, pill_class, acts_class, holds, status, affects[], doing,
    todo, buttons[[label, act, id, confirm?]] ('href' as act opens a drawer link), details[[label, text]]`.
  - Times in words are `{t:ISO}`: on a page, a labelled `<time data-tv-time data-tz="UTC">`; in text, "Nov 20, 09:12 UTC".
  - Its CSS is printed once per page; tokens only.
- **`ControlAlarms::facts($pdo, $alarm)`**: the alarm's six parts, read from its row now.
  - Status: "Warning since …, seen n times"; "Paused by the system (a trip) since …. It closes as Recovered when its check
    passes."; "Tripped at once …: a critical part…"; "Escalation Notice …" (from the logbook, `escalatedAt`);
    "Closed …: how"; plus acknowledged, good again and Not resolved.
  - `affects()`: what the trip paused, and for `svc:` each `Preflight::NEEDS` entry that uses it, with its other sources
    or "no backup (<fallback>)".
  - `pulseBody()` draws through Thread.
  - `statusLine()` puts "Status: …" first in an email's words; the push reads that first line.
- **`Notify::IN_PLACE`** (`alarm.trip`, `alarm.warning`, `alarm.escalated`, `alarm.recovered`): one thread per thing.
  - The trip, its Escalation Notice and Recovered share the thing `alarm:<id>`.
  - A send updates the message in place: kind, level, words, `pulse_ref`.
  - It's unread again only when louder; the push renotifies on the same tag.
  - A thread's push body is its status line.
- **Pulse:** `Pulse::items`' alarm item reads `Thread::text(…, 'pulse')`.
- **The board:** `tv_cb_alarms` draws each row through Thread. Every part and button it had is kept; the reading and
  counts are in Details ▾.
- **The logbook:** the cleared line keeps `Thread::text(…, 'logbook')`.
- **Words:** Notify's `alarm.recovered` is "A part recovered by itself"; `alarm.escalated` "closes as Recovered when its
  check passes".
- **On purpose:** the Escalation Notice updates the same Pulse row, louder, instead of a second row (0176 §4 step 4b,
  which Jason approved). The old comment "its own row… as Jason asked" had no decision behind it.

## Release 2, held: service issues as threads

**Goal** (0176 §3, A3): the card's issue box, Pulse's `svc:<id>` case, Outside services' "Issue #n" lines and the Start
card's issue line become **one thread**, drawn by Thread. There's no × that looks like it dismisses the problem, and
"Its alarm on the board" goes.

**Files** (cleared by the session manager, claims released at the hold; claim again as `0176-issues.zip`):

| File | What changes |
|---|---|
| `app/lib/ServiceHealth.php` | a new `facts($pdo, $issue)` beside `describe()` and `steps()`, which stay for their other callers (`SystemStart`, `Preflight`, `Pulse`) |
| `app/admin/operations-console.php::tv_ops_issues` | the card's issue box drawn by `Thread::html(…, 'card', $btns)` |
| `app/lib/Pulse.php::items` | the `svc:<id>` case's words from `Thread::text` (about line 377); its steps (`ServiceHealth::steps`, about line 1279) unchanged |
| `app/lib/OutsideServices.php` | `working()`'s "Issue #n" line (about line 451) from the same facts |
| `app/lib/SystemStart.php` | the Start card's issue line (about line 855, `describe()`) from the same facts |
| `app/lib/Thread.php` | only if a small addition is needed |
| `app/ui/faq.md` | one entry |

**Behaviours, with their sources:**

1. One thread per issue, in the one shape:
   - **Title:** what failed, in plain words ("Common Crawl isn't answering"; `ServiceHealth::KINDS[kind][0]` and the
     service's name).
   - **Status:** read now. Use `ServiceHealth::waitWords()` from feeds-words (#223), never the stored `hold_until`
     (0176 §7). Examples: "Backing off since …, next try …", "Paused by the system (a trip) since …" when its alarm
     tripped, "Held since …: waits for a person" for a stop kind.
   - **What it affects:** `ControlAlarms::affects()` for its alarm, else the needs that use the service (the same
     `Preflight::NEEDS` reading).
   - **What the system is doing:** `KINDS[kind][2]` and the back-off words.
   - **What to do:** today's `$todo`, kept word for word.
   - **Buttons:** today's, Check it now or Check and start again.
   - **Details ▾:** What came back (through `OutsideServices::plainWhy`), How often, Since and last, the caller.
   - Sources: 0176 §2, §3, §7; A2, A3.
2. **No ×:** the issue closes only when its check passes, or by a person's act on its cause (0171 §7's notes of
   2026-11-19). **Open question (below):** where the × in Jason's screenshot was drawn.
3. **"Its alarm on the board" goes.** The thread is the alarm, so there's nothing else to open (0176 §3's note for
   Jason). Its alarm's buttons are already on the card.
4. **Pulse's `svc:` case** reads the same words (everything but the details; 0176 §2). Its steps are unchanged.
5. **Outside services' and Start's lines** read the thread's title and status, and link to the card's issue
   (`/admin/config/register#issue-<id>`) instead of `/admin/pulse/stream#case=svc:<id>` (0175 §6: the board and card
   are where you act). **This is a link change: check it with the session manager.**

**What it replaces** (playbook §3), for the card's issue box:

| The issue box today (`tv_ops_issues`) | Where it goes |
|---|---|
| Heading "Issue #n: <kind> (tripped)" | Title, with the pill "Trip" or "Warning"; the issue number in Details |
| What came back | Details ▾ (raw, through `plainWhy`) |
| How often, from <caller> | Details ▾ |
| Since, last | Status (since) and Details (last) |
| Next try (or `waitWords`) | Status |
| What it means | What the system is doing |
| What to do (`data-tv-status` info or bad) | What to do, word for word |
| Check it now (`data-ops-cb="svccheck"`) | Buttons, kept |
| Check and start again (`data-ops-cb="resolve"` on a trip) | Buttons, kept |
| "Its alarm on the board" (link, `target=_top`) | **dropped** (0176 §3's note; it caused the drawer inside a drawer) |
| The box's id `issue-<id>` | kept, so the links land on it |
| Its script (busy spinner, the answer beside the button, reload) | kept as is |
| × (from the screenshot) | not found in `tv_ops_issues`; see the open question |

**Tests to write:** `tools/dispatcher-tests/r2.php`.
- Each surface shows the same words for one issue.
- A back-off whose wait is over says so, not a past date.
- A trip's issue says Paused by the system.
- No `target="_top"` link, and no ×.
- The buttons keep their `data-ops-cb`.
- The card at 390px.

Run the Control Board's `card-issue.php`, `start-issue.php`, `feeds-words.php`, `task-on.php`, `register.php` and
`backoff-gate.php` against the live mirror first, for the baseline.

## Release 3, not started: Fix it routing

Source: 0176 §5, A5.
- **Each thread carries its cause, and a cause → landing map gives one spot:**

  | Cause | Where Fix it lands |
  |---|---|
  | key rejected | the card's Its key, with Replace focused (exists) |
  | quota or budget | Caps and budget (its anchor is to come) |
  | site blocked us | its source's rule on its process page, with Reactivate (exists) |
  | no backup | the workflow's Steps and backups (to come, 0175 release 2) |
  | repair needs shutdown | Maintenance, Take out (exists) |
  | server down | no Fix it; Check it now, plus Use backup or Pause it |

- **Fix it shows only when a person can fix something.**
- **One panel at a time, with Back.** Reuse the Control Board session's drawer from **0175 release 2a** (`at_hand`):
  never build a second one, and match its words (the session manager, 2026-11-20). Ask through the session manager for
  any hook.
- **From a push:** open the app at the thread's Pulse row, with the panel on the fix.
- **After acting:** the check runs on the spot. Fixed updates the thread everywhere; a failure says what came back and
  offers the old value back (Put back, 0173 release 2).
- **A spot marked "to come"** shows a greyed Fix it with the reason (playbook principle 3).

## Release 4, not started: uploads

Source: 0176 §6, A6.
- **Each target in `upload-targets.php`** names its thread (kind, who's told, the facts) and its landing spot.
- **A photo of a place** (post first):
  - the uploader's notice beside the button;
  - the staff thread "New photo of <place>" with Hide and Open it;
  - a report joins the same thread.
  - The live camera on a place starts a journey (`journey_photo`).
- **An order file:**
  - the staff thread "New artwork on Order #N", with Open the order → Files;
  - "Ask for a new file" is to come;
  - the file check (resolution, size, bleed) and the customer's **Replace file** wait for **Jason's limits**.
  - Today only staff can upload to an order (`$tvOrderStaff`), so a customer target on the order's link is new.
- **Fixes in place for upload errors** (0127's answer beside the button).

## Open questions and pending Jason items

1. **Release 2's restart:** Jason's word (he held it).
2. **The order file check's limits:** minimum resolution, bleed and size (Jason: "I'll set the limits").
3. ~~**The × in Jason's screenshot of Common Crawl's card.**~~ **Found (Operations, 2026-11-20):** `tv-ask.js`'s status lines carry a ×; R2 draws the thread without one and adds `data-tv-keep`. No × is drawn in `tv_ops_issues`. Check:
   - `ui/outside-services.php`'s "Is it working" lines;
   - the card's notices (`news`, closed with ×);
   - the drawer chrome.

   Ask the session manager before guessing.
4. ~~**R2's link change**~~ cleared by the session manager (2026-11-20), with the Pulse case kept reachable (Its case in Pulse).
5. ~~`ControlSensor.php`'s "Only Resolve clears it" is untrue since 0175.~~ **Closed (Operations, 2026-11-20):** w1-findings (#228)
   fixed ControlSensor's words; the same words left in `ControlAlarms.php` (Pulse's escalation line, three comments) and
   `ControlMaintenance.php` are fixed in 0175 release 2a part 1 (`0175-r2a-panel.zip`).

## Coordination

- **ServiceHealth:**
  - The Control Board session's Census fallback (later) touches it. The session manager puts it **after** this R2.
  - Its feeds-words (#223) added `ServiceHealth::waitWords()`, `TRIP_HOLD` and the trip words. Build on them; don't
    repeat them.
- **0175 release 2a** (Control Board session): the one-panel drawer and `ui/outside-services.php`'s steps. If it needs
  `OutsideServices.php`, it waits for this R2.
- **faq.md** is shared by every release. Claim nothing for it, rebuild it on the newest sync just before sending, and
  add only this build's entries. At the hold the queue was: trip-share, then hero-tidy (the trips session), then
  whoever is ready.
- **Hotspot files** need entry-level claims: `app/lib/Pulse.php::items`, `app/admin/operations-console.php::tv_ops_issues`.

## Pitfalls learned

- **The Notify tests overwrite files in the app folder they're given** (stand-ins for `Database.php`, `WebPush.php` and
  others), and leave them if they stop early. Run them only on a throwaway copy, never on the root you build or test
  release files from.
- **Old migrations are gone from the mirror** once they've run.
  - `control_ledger`: a stand-in made from `ControlLedger.php`'s INSERT columns.
  - `comms_*`: `CREATE`s gathered from `tools/notify-tests/*.php`.
  - `system_pauses`: from `0175-paused.zip` in git history (commit `ba782ba`).
- **`.env` is found four folders above `lib/`** (`Database.php`: `dirname(__DIR__, 4)`). The dispatcher tests copy the
  app beside its root for that reason.
- **MariaDB isn't installed in a fresh container.**
  - Install: `apt-get install -y mariadb-server`.
  - Start: `mariadbd --user=root --datadir=/var/lib/mysql &`.
  - Database user: `tv` / `tv`, named in the `.env`.
- **The Control Board tests have failures that exist on the live mirror too** (routes, proctable, start-issue, evidence,
  and fatals in r3e, r4f, links, backoff-gate, offregion, filter, r12). Compare against a baseline run on the mirror
  alone before calling anything yours.
- **A second release carrying `faq.md` can reach main while yours waits for its OK.** The zip check then blocks.
  - Take your inbox commit back off the branch.
  - Wait for theirs to be Live and synced.
  - Re-apply only your entry on the new live copy, send again and merge under the same OK.
  - Done once for R1 (ccol, #225).
- **Python replaces with `str.replace(..., 1)` hit the first match.** `Notify::BODIES` and `KINDS` both start lines with
  `'alarm.recovered' =>`, so edit by line, and diff against the live copy before zipping.
- **`checkout.py claim` refuses a whole hotspot file.** Name the entry (`Pulse.php::items`).

## Tests

- `tools/dispatcher-tests/r1.php`: 43 checks.
  - Run: `mariadb < tools/control-board-tests/r2a-schema.sql && php tools/dispatcher-tests/r1.php <root>/app`.
  - `<root>/app` is the mirror's `sync-app` with the release laid over it, and a `.env` four folders above `lib/`.
  - It copies the app beside its root, with `Mailer.php` and `WebPush.php` stood in.
