# 0176: The dispatcher presents and routes every thread

Status: **accepted** (Jason, 2026-11-20, draft 2, approved with the decision pop-up; reviewed by the session manager).
His answers to the questions are below them. **Built:** release 1 (the alarm thread) live as #226, 2026-11-20.
**Held** (Jason, 2026-11-20: "hold on r2 build"): releases 2–4, each planned in `handoffs/dispatcher.md`. Written by the
dispatcher session at Jason's request, relayed by the session manager.

Builds on:
- 0172: Communications. `Notify` is the one dispatcher (§4.1); the route (§4.2); the six levels; "The message".
- 0171: the System Control Board. Alarms (§7), trips and the escalation (§5's notes), Pulse (§8), the logbook (§10).
- 0175: one lifecycle for every module. Paused by the system (§3), the component and the workflow (§3, §6), the board
  as the one place to act (§6).
- 0127: one uploader (`tv-upload.js` → `/api/upload.php` → `Uploads.php`, targets in `upload-targets.php`).

Amends (each marked **Note for Jason** where it happens):
- 0172 "The message": an owner no longer draws its own Pulse body. It supplies facts and buttons, and Notify draws (§2).
- 0171 §8's "The issue keeps its evidence, Check it now and Mark resolved": the card's issue box becomes the thread (§3).
  Mark resolved is already gone (0171 §7's note, 2026-11-19).

Checked against the live mirror at "Sync: 2026-11-20 13:53 UTC", and 0175 release 1 (#222, `0175-paused.zip`) as merged.

## What Jason asked for

His words, 2026-11-20, in the order he gave them.

On a screenshot of Common Crawl's card (a drawer opened inside a drawer, raw S3 and nginx text, "Backing off until Jan
1", issue boxes with ×):
> I think we need to talk about the dispatcher. This is bad design and if the dispatcher controls how things are routed
> from action to view it should also handle how something like this is presented.

Then:
> walk me through how a trip could be managed by the dispatcher?

> how I would click a fix it button and how the dispatcher would take me to the correct display to fix it.

> compare that to how the dispatcher would handle the add a photo from a landmark page or upload file from an order page

> ok let's start with and see what else comes next.

> decision 0176: the dispatcher presents and routes every thread, let's make a new session to handle this.

**What he agreed to in discussion with the session manager** (2026-11-20, relayed to this session; cited below as
**A1** to **A7**):
- **A1.** One thread per thing (an incident, a photo, an order file), updated in place, never re-sent. Owners supply
  facts and buttons; Notify draws the one shape.
- **A2.** The one shape: Title, Status, What it affects, What the system is doing, Buttons, Details ▾. Each surface shows
  its part of it.
- **A3.** The card's "Is it working" service issues become dispatcher threads. No × that looks like it dismisses the
  problem.
- **A4.** A trip's walk-through, using 0171's and 0175's rules exactly as written.
- **A5.** Fix it routing: each thread carries its cause, and a cause → landing map gives one exact spot.
- **A6.** People's actions too: each upload target names its thread and its landing spot.
- **A7.** Words follow the state. The Control Board session's feeds release caps the back-off and fixes stale issues.

## What exists (checked in the live code)

**Who draws a message today:**
- `Notify::send` writes one `comms_messages` row per recipient. Its `thing` and `thread` fields exist.
  - A message sent on a `thread` while the last one is still unread updates that row (title, words, a count).
  - Once it's read, the next send is a new row.
- `Notify::BODIES` maps 18 kinds to their owner's `pulseBody()`. Each owner draws its own HTML: `ControlAlarms`,
  `CommsSetup`, `Mailboxes`, `PulseMarks`, `CommentNotices`, `Bookings`, `GroupNotices`. So every kind looks different.
- A push is tagged by its thing, so an update replaces it. It alerts again only when the level rises, or for Critical
  (`Notify::deliverPush`, 0172's push-polish note).

**Service issues bypass Notify.** `ServiceHealth` keeps `service_issues` and draws them in three places, each with its
own words:
- the card's issue box (`tv_ops_issues()` in `admin/operations-console.php`): What came back, How often, Since, Next
  try, What it means, What to do;
- Pulse's `svc:<id>` case (`Pulse.php`, `ServiceHealth::describe()` and `steps()`);
- Outside services' lines (`OutsideServices.php`).

**Where "Backing off until Jan 1" comes from:**
- A trip holds a back-off's calls by setting `hold_until = '2099-01-01'` (`ServiceHealth::holdForTrip`).
- `describe()` then prints "Backing off until" that date.
- The words were true of the code, not of the state.

**The drawer inside a drawer:** the card opens from the board in a drawer (`at_hand`), and its issue box links to "Its
alarm on the board" (`target="_top"`), which opens the board again. Nothing says there is one panel at a time.

**Uploads** (0127): every target's `done` returns a message shown beside the button (`TvUpload`). None names a thread,
and none tells anyone else.
- **A photo of a place:**
  - There's no landmark photo target. A place's photo is a journey: the camera on a place or business photo starts a
    journey (`journey_photo`, FAQ "How do I add a photo of a place?").
  - It's published at once. Journeys are "post first, respond to flags and reports" (`Journeys.php`'s header).
  - I haven't checked whether a landmark page has the camera.
- **An order's files:**
  - `order_proof`, `order_file` and `order_final` are staff only (`$tvOrderStaff`).
  - The client sees the current proof by the order's link and approves it (`ClientOrders::approve`). There's no way for
    a customer to upload to an order.
  - **No file check exists.** Uploads checks only the type, the content's first bytes and the most it takes (1 GB):
    nothing for resolution or bleed. `FILE_KINDS` names "Production setup" and "Print file (1-up)" but checks neither.
    So the order's file check is **to come**.

## Decision

### 1. One thread per thing

**What a thread is** (A1):
- It's the one record of one thing: an incident (an alarm, a service issue), a photo, an order's file.
- Its key is the thing's own key: `alarm:<id>`, `svc:issue:<id>`, `upload:<target>:<id>`.
- It's **updated in place**, read or not, and never re-sent as a new message (A1).
- The push keeps its tag per thing, and alerts again only on a level rise or for Critical (0172's push-polish note).

**Owners supply facts and buttons, never HTML** (A1): the board, the service monitor and the libraries that own uploads.
Notify draws the shape (§2).

**Note for Jason (amends 0172 "The message"):** 0172 has each kind's body "made by the part of the system that owns it".
That stays true of the **facts and buttons**, which still act through the owning library and answer beside the button
(0172 "The message" 3, `Pulse::manage`). Only the drawing moves to Notify, so every thread looks alike.

**Not changed:**
- the route's stages (0172 §4.2);
- the levels and who may turn a kind off (0172 "The six levels");
- `pulse_ref`, so Pulse shows an alarm once (0171 §7's note, 2026-11-19);
- `control_alarms`, which stays its own table (0172 §4.5).

### 2. The one shape

Six parts, in this order (A2):

| Part | What it says |
|---|---|
| **Title** | In plain words: what happened ("Common Crawl isn't answering") |
| **Status** | Who paused it, since when, the next try ("Paused by the system since 09:12 MST. Next check 09:42 MST.") |
| **What it affects** | The workflows that use it, and each one's backup, or "none" |
| **What the system is doing** | Trying again, rerouted to the backup, or waiting for a person, and why |
| **Buttons** | The fix (§5), and the thing's own actions |
| **Details ▾** | The raw evidence (status codes, server text), folded |

**What each surface shows** (A2):

| Surface | What it shows |
|---|---|
| Push | Title and status, only when the level warrants a push |
| Pulse | Everything except Details |
| The board and the card | All of it |
| The logbook | The finished story |

- **Push:** when a level warrants a push is 0172's table ("What each level does"). Staff get push by default at Alert
  and up (`Notify::PUSH_DEFAULT`).
- **Email:** 0176 doesn't change it. An email says what happened and links to the item in Pulse (0172 "The other
  channels point back"), and each trip still emails the Founder at once (0171 §7).

**Status reads 0175's states:**
- "Paused by the system (a trip)" or a person's pause (0175 §2 and its note "Trip, and fault": a fault is the part
  not working, a trip is the pause placed because of it);
- an active module's checks are **Diagnostics**, never "pre-flight" (0175's note, 2026-11-20);
- the next try from the system's own clock;
- never a stored date that no longer holds (§7).

**What it affects reads 0175 §3:** the workflow and its named backup ("On a backup"), or "none".

### 3. The card's service issues become threads

The card's issue box, Pulse's `svc:` case and Outside services' lines become **one thread**, drawn by Notify in the one
shape (A3).
- The card shows the thread, all of it (§2's table).
- **No × that looks like it dismisses the problem** (A3).
  - A thread closes only when its check passes (0171 §7's note, 2026-11-19: "a passing check closes it").
  - Or by a person's act on its cause (0171 §7: Reactivate, a key replaced).
- The buttons stay the ones the card has today: Check it now, and on a trip Check and start again (0171 §7's notes,
  2026-11-19).
- **Note for Jason:** "Its alarm on the board" goes. The thread is the alarm, so there is nothing else to open (A1, and
  §6's one panel).

### 4. A trip, managed by the dispatcher (A4)

Every step below is 0171's or 0175's rule, as written. The dispatcher adds only that it is one thread, drawn in one
shape, updated in place.

| Step | What happens | Source |
|---|---|---|
| **1. A fault** | A warning on the board and the card only. No Pulse row, email or push. The thread opens, status "Trying again (1 of 3)". | 0171 §7's note, 2026-11-19 ("only a trip reaches a person"); §5's 3c note |
| **2. The tries run out: a trip** | `control_fix_tries` (default 3) or `control_fix_minutes` (default 60), whichever comes first. A trip is the system placing its pause, so it reads **"Paused by the system (a trip): …"**: the same thread, raised; and since its tries ran out, 0175 §3's Escalation Notice. It needs a person now, so it reaches one: Pulse, the Founder's email and the push, at once. | 0171 §5's 3c note; 0175 §3 and its note "Trip, and fault" (2026-11-20); A4 |
| **3. Each check** | Updates the status in place: "Checked 09:42 MST: still refused. Next check 10:12 MST." No new message. | A1; 0171 §7 ("the same problem happening again updates its open alarm") |
| **4a. Recovered** | It closes quietly. "Recovered: <part> was down <n> min, the system fixed it." A notice with nothing to press (Notice level, so no push by default). | 0171 §5's note, 2026-11-19; 0175 §3 |
| **4b. Escalated** | 0171's Escalation Notice: "Retries unsuccessful, now admin required: <part>, <duty>". The level rise brings one push. On Pulse's clock it goes to the Manager, then the Director. If its check passes later, it still closes itself as Recovered (4a), its pause lifts, and the Recovered notice goes to the Founders: nothing is left to press (`ControlAlarms::ESCALATED_RECOVERS`). | 0171 §5's note; the note to §8, 2026-11-19; 0172's push-polish note; 0175's note "an escalated trip closes as Recovered" (2026-11-20) |
| **5. Closed** | It goes to the logbook as the finished story: raised, each check, how it closed, who. | 0171 §10; §7's note ("a cleared one is in the logbook") |

**Who is told, and when** (session manager's review, 2026-11-20, from these sources):
- 0171 §7's note (2026-11-19, control-board-routes), Jason: "I think trips that require the admin are sent immediately
  because they wont clear unless the admin clears them. Everything else should be cleared by the system so why send and
  pulse?"
- 0175 §3: a system pause "clears itself when its check passes"; "Its tries run out … the Escalation Notice … Only then
  does it wait for a person"; any other part is "escalated the same way".
- So **a fault the system is still trying to fix goes to the board and the card only.** Pulse, email and push go to a
  thread that needs a person:
  - **held or out** at once: a refusal, a refused key, a used-up quota or budget (0171 §5's 3c note: "there's nothing to
    try");
  - **an escalation**, when the tries run out (step 2);
  - **a critical part's first failure**, at once, while the system keeps trying (0171 §5's note, 2026-11-19), then
    Recovered or the Escalation Notice.
- A4's "a push only for critical parts" was the brief's loose wording, not a rule; the sources above decide it.
- **Stopping:** what it pauses is as narrow as its cause (0171 §7, 0175 §3).

### 5. Fix it: from the button to the right display (A5)

**The cause → landing map.** Each thread carries its **cause**, set by its owner from what it already knows: the issue's
kind (`ServiceHealth::KINDS`), the alarm's kind and family (`ControlAlarms::familyOf`). The map gives one exact spot:

| Cause | Fix it lands on | State of that spot |
|---|---|---|
| Key rejected | The card's **Its key**, with **Replace** focused | exists (0173 release 2) |
| Quota or budget used up | The card's **Caps and budget** | the card shows caps and paces today; its own anchor to come |
| A site blocked us | That source's rule on its process page, with **Reactivate** | Reactivate exists (0171 §7's note, itsdata); the process page exists (0171 §12) |
| No backup named | The workflow's **Steps and backups** | to come (0175 release 2: the workflow's own page) |
| Repair needs shutdown | **Maintenance → Take out** | exists (0171 release 4a; 0175 §3) |
| Server down (theirs) | **No Fix it**: "The system is handling it", with **Check it now** and **Use backup** / **Pause it** | Check it now exists; Pause is 0175 §2's action; Use backup as a button is to come (A5; 0175 §3 reroutes by itself) |

**When Fix it shows** (A5):
- It shows only when a person can fix something.
- A cause the system is handling has no Fix it. That is principle 1 (builder-playbook §2: "a person is the last resort").

**Where it opens:**
- **In the side panel, ready to act, one panel at a time** (A5). It replaces what's there, with **Back**: never a drawer
  inside a drawer (Jason's screenshot, quoted above).
- It's the same switch as the spot's home, recorded with who pressed it (0175 §6).
- The side panel's look is the design session's (`design/css-theme-brand.md`).
- **From a push**, it opens the app at the thread's Pulse row, with the panel on the fix (A5). Today a trip's link is
  the board (`/admin/config/control#cb-alarm-<id>`).

**After acting** (A5):
- The check runs on the spot.
- A pass shows **Fixed**, and the thread updates everywhere at once (A1).
- A fail says what came back, and offers **the old value back**: Put back, as 0173 release 2's keys have it.

### 6. People's actions: uploads name their thread and their landing spot (A6)

Each upload target in `upload-targets.php` (0127) gains two things:
- **its thread:** the kind, who is told, and the facts;
- **its landing spot:** where **Review it** or **Open the order** opens, in the side panel (§5).

**Fixes in place for upload errors** (A6): the error is shown beside the button with what to do, as 0127 already has
(`TvUpload`'s answer beside what you were working on). A failed file check offers its fix there.

**A photo of a landmark or place** (A6), on today's rule (CLAUDE.md "Build on what's there"): a place's photo is a
journey, live at once, with reports after (`Journeys.php`'s header: "post first, respond to flags and reports"; FAQ "How
do I add a photo of a place?"):
1. The uploader sees a notice beside the button that it's live (0127: the answer beside what you were working on).
2. Staff get a thread, "New photo of <place>", with **Hide** (`Journeys::setHidden`, which exists) and **Open it**, the
   photo in the panel.
3. A report on it joins the same thread (A1: one thread per thing).

A6's "review before live, then Approve or Hide, and the uploader told later" was an illustration, not a decision. It
would change today's post-first rule, so it stays Question 2. The session manager and I recommend keeping post first.

**An order file** (A6):
1. **The order's file check acts as its pre-flight:** resolution, size and bleed. **To come:** it doesn't exist today
   (What exists). Its limits are Question 3.
2. Staff get a thread, "New artwork on Order #N", with **Open the order**. It opens the order's **Files** in the panel,
   with **Approve proof** or **Ask for a new file**.
3. A failed check gives the customer **Fix it → Replace file**.

**Note for Jason:** customers can't upload to an order today; the order targets are staff only. **Replace file** needs a
customer target on the order's link (Question 3). **Ask for a new file** is to come. The client's Approve exists
(`ClientOrders::approve`).

### 7. Words follow the state (A7)

- Every part of the shape is read from the state as it is now, each time it's drawn: playbook principle 4, "Words
  follow the state as it is now".
- Nothing like "Backing off until Jan 1" is drawn from a stored date that no longer means a time.
- **The Control Board session's feeds release (to come): the back-off cap and stale issues** (A7). 0176 builds on it
  and doesn't repeat it.
- What it has to fix includes the 2099-01-01 hold found here: a trip holds a back-off's calls by setting
  `hold_until = '2099-01-01'` (`ServiceHealth::holdForTrip`), and `describe()` prints it as "Backing off until Jan 1".
  The session manager has forwarded this to that session.

## Releases, in order (each one Jason reviews)

1. **The thread and the shape, for alarms.**
   - Notify draws the six parts from facts and buttons; `ControlAlarms` supplies them instead of HTML.
   - The thread is updated in place; the board, Pulse, push and the logbook each show their part (§1, §2, §4).
   - Builds on 0175 release 1 (Paused by the system), live as #222.
2. **Service issues as threads.**
   - The card, Pulse's `svc:` case and Outside services' lines become the one thread; no ×; "Its alarm on the board"
     goes (§3).
   - Words read from the state (§7), after the Control Board session's feeds release.
3. **Fix it.**
   - The cause → landing map; the side panel one at a time with Back (with the design session); a push opening the
     Pulse row with its panel; the check on the spot, Fixed, and the old value back (§5).
   - Spots marked "to come" show a greyed Fix it with the reason until their own release lands (playbook principle 3: "A button
     that can't work yet is greyed with the reason").
4. **Uploads.**
   - Each target names its thread and its landing spot. The order file thread for staff first (§6).
   - The photo of a place on post first (unless Question 2 says review first); the order file check and Replace file
     follow Jason's answer to Question 3.

Releases 1 and 2 touch `ControlAlarms.php` and `ServiceHealth.php`, which the Control Board session owns. They're
claimed through `checkout.py` and coordinated through the session manager (decisions/0161).

## What this doesn't do

- It doesn't change when anything trips, pauses, escalates or reaches a person: 0171 and 0175 decide that.
- No new levels or channels, and no new kinds of push: 0172 decides those.
- No new upload path: 0127's one uploader stays.

## Questions for Jason

Reviewed by the session manager (2026-11-20).

1. **Approve this decision** and its four releases, in that order?
2. **A photo of a place: keep post first?** Today it's a journey, live at once, with reports after (`Journeys.php`).
   §6 keeps that, with a staff thread offering Hide (recommended). Or should every visitor's place photo wait for staff
   review (Approve or Hide) before it's live?
3. **An order's files.**
   - What does the file check test, and at what limits? The minimum resolution, the bleed, the most it takes. No
     decision sets these, and the code has none.
   - May a customer upload a replacement on the order's link? Today only staff can upload to an order.
4. **How far the one shape reaches.** Incidents and uploads first, and the other kinds (comments, bookings, groups,
   whose bodies have their own reply boxes) in a later release (recommended)? Or every kind at once?

**Answered (Jason, 2026-11-20, in the decision pop-up):**
1. **Approve.**
2. **Keep post first.** A place's photo stays live at once, with the staff thread "New photo of <place>" offering Hide
   (§6).
3. **"I'll set the limits"**: customers may replace a file on the order's link, and Jason gives the check's minimum
   resolution, bleed and size. Until he has, the order file check and Replace file stay "to come", and release 4 builds
   the staff thread first.
4. **Incidents first.** Alarms, service issues and uploads move to the one shape in these releases. Comments, bookings
   and groups follow in a later release.

**Note (2026-11-20, release 1 live as #226, `0176-thread.zip`, verified on Sync 14:37 UTC):** the alarm thread. `app/lib/Thread.php` draws
the one shape; `ControlAlarms::facts()` supplies an alarm's parts; the trip, its Escalation Notice and Recovered are one
message (`Notify::IN_PLACE`), updated in place; the board's rows, Pulse's item and body, the push and the logbook read the
same shape. Record: `handoffs/dispatcher.md`.

## Note (Jason, 2026-11-20): email takes the push's shape

On a screenshot of his phone (Gmail: "Traversence.com · W3 Sources and the place store: Its gate: 1 red line(s) in th…",
four separate emails), asked whether email should take the same shape as the push, Jason chose **"Same shape as push"**.
This replaces "Email: 0176 doesn't change it" above for alarm threads:
- **The subject** is the thread's title and status in plain words, as the push reads (e.g. "W3 Sources: Paused by the
  system"), never the check's raw text.
- **One email thread per alarm:** later news on the same alarm (raised, Escalation Notice, Recovered) goes as a reply in
  that thread, not a new email.
- The body still says what happened and links to the item (0172 "The other channels point back"); each trip still
  reaches the Founder's email at once (0171 §7). Built by Operations with release 2.

**Added (Jason, 2026-11-20): the alarm notice's format, one thread on every channel.** His words: "Wouldnt also be
wise to send SYSTEM ALARM NOTICE: (Item or Group): (Action) and then whats going on inside the message body?
THREAD:Push THREAD:Pulse THREAD:Email". Also: a trip is an immediate notification (it never waits for quiet hours;
that is what "any hour" meant). Then, in the decision pop-up, "Push shortened". So, for alarm threads:
- **Email subject and Pulse row:** `SYSTEM ALARM NOTICE: <item or group>: <action>`, e.g. "SYSTEM ALARM NOTICE: W3
  Sources: Paused by the system". The action is the thread's state: "Paused by the system", then "Retries unsuccessful,
  now admin required" (the Escalation Notice), then "Recovered".
- **Push:** title `SYSTEM ALARM: <item or group>`; the body starts with the action, then what's going on (a phone cuts a
  title at about 40 characters).
- **The body**, on every channel: what's going on, what it means, what to do, and the link to it.
- One thread per alarm on each channel; later states update it (Pulse, push) or reply in it (email). This sharpens the
  note above ("W3 Sources: Paused by the system" becomes the line here).

**Note (Jason, 2026-11-20, in the decision pop-up): release 2 restarted.** Asked "Restart it now?", he chose "Restart R2".

**Note (2026-11-20): release 2 built** (`0176-issues.zip`, built on Sync 16:32 UTC):
- `ServiceHealth::facts()` gives an issue's six parts, read from its state now; the card's issue box, Pulse's `svc:` case,
  Outside services' line and the Start card's issue line all read it.
- The card draws the thread with its buttons kept (Check it now, Check and start again) and **Its case in Pulse**. There's
  no "Its alarm on the board".
- **Where the × came from:** `js/tv-ask.js` turns every `data-tv-status` line into a notice box with its own ×. The old
  issue box's "What to do" was such a line, and so was the card's "Is it working" line for the issue. The thread draws
  plain lines, and those two lines carry `data-tv-keep`, which keeps the box and drops its ×.
- The lines link to the issue on the card (`#issue-<id>`); its Pulse case stays reachable (the session manager's review,
  2026-11-20).
- The issue's own words no longer say "mark resolved" (0171 §7's note: a passing check closes it).
- §7's "feeds release (to come)" is live as #223 (feeds-words); R2 reads `waitWords()` from it.

**Note (2026-11-20): release 2 in two parts** (agreed with the session manager). Part 1 (`0176-issues.zip`) is live as
#234, and part 1's FAQ lines are put back with part 2. Part 2 (`0176-notice.zip`, built on Sync 17:34 UTC) builds the
two notes above:
- `ControlAlarms::noticeTitle()` gives "SYSTEM ALARM NOTICE: <item>: <action>" from the thread's state
  (`ControlAlarms::ACTIONS`). It is the trip's, Escalation Notice's and Recovered's message title and Pulse's alarm row.
- The notice body starts with the action and its status, then what's going on, what it means and what to do.
- Notify sends it as the email subject, without " · Traversence". The push title is "SYSTEM ALARM: <item>".
- One email thread per alarm: `Mailer::$thread`, set only by Notify for alarm kinds, gives the first email a stable
  Message-ID and later ones In-Reply-To and References. Every other email keeps its random Message-ID.
- A trip goes at once: `alarm.trip` and `alarm.escalated` are Critical, which quiet hours never hold. A test checks it.
- Known limit: Gmail joins a reply to its conversation only when the subject also matches. The subject's action changes
  with the state, so Gmail may show Recovered as a new conversation; Outlook and Apple Mail follow the headers.
