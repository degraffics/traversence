# The control board: step 3, the four-zone card, first spec: Railway (2026-11-21)

**What this is:** the card spec and its mockups for one module, Railway, in every state, and one failure walked through
every surface. It's a design document with static mockups, not site code. Every word on a card comes from
`02-state-model.md` (cited as 02 §… or "row N"). Where 02 has no words, the gap is listed under **Open words** and isn't
made up. Mockups: `mockups/index.html`. Screenshots at 1280 and 390: `mockups/shots/`.

**Built from:** 02 (§0, §0a, §3, §4, §4.8, §6, §7, §8); `manifests-railway-github.md` §1; the report Jason approved
(four zones; eight stages, no SMS, no webhooks); `04-vault-portal-links.md` §6 (the slot rendering; its §5 label
label rulings and §7 answers, approved by Jason on 2026-11-21, are applied); `design/css-theme-brand.md`, `brand.md` §4, `design/table-standard.md`;
step 2's g4 §(d) and the lift's §(f).

**Real data only.** Railway's values were read from its API (read-only GraphQL, 2026-11-21 15:51 UTC): the project, its
three services, each one's schedule, restart rule, root folder, start command, next run and latest deploy with its
commit. No variable was read (the API returns names only together with values), and nothing was changed. The live alarms
come from `site-audit/results/alarms.json` (10:19 UTC). Where a value couldn't be read, the mockup says **not read yet**.
Scenario values (a failure's time, an illustrative spend) are drawn with a dotted copper underline and a legend, and
never presented as live.

---

## 1. The card: four zones, field by field

The zones always appear in the report's order: 1 Identity and role, 2 Capabilities and feeds, 3 Sensors and key
readiness, 4 Actions and Fix it. **One exception, the fix for the lift's §(f):** while a fault is open on the module
or any of its parts, **zone 4 moves up under zone 1** and carries the thread (02 §2.6's six parts). The problem, what
it holds and its buttons are then the first thing under the name. The part's own row in zone 2 or 3 is tinted
(amber or red) and says "its actions are in the thread above ↑". When everything is Working, zone 4 sits last.

### Zone 1: Identity and role

| Field | Shows (Railway today) | Manifest field | State rule |
|---|---|---|---|
| Name | "Railway", brand brown `#92400E`, underlined, serif | `name` | P9: the plain name |
| Role badge | **Host** (filled umber pill) | `role` | 02 §2.1: a Host fault holds what runs on it (§6 step 1) |
| Criticality | "Not critical by itself" | `criticality` (none) | P8: decided by duty; Railway has none (Jason) |
| State | the light and first words: "On · Working" | from `state()` | 02 §3b's precedence; `<switch> · <run-state>`; the light is never colour alone (dot and word) |
| Stage badge | "Stage 5 of 8 · Operational" + "Next allowed: …" | `stage` | §2 below |
| Spend chip | "Hobby · $5 a month including $5 of usage · use not read yet · Railway's usage page ↗" | `plan`, `caps`, ledger | §3 below |
| Its radius | "If Railway itself is down: its Incident Radius ▸" | `hosts`, `feeds` | §6; see §5 below |

### Zone 2: Capabilities and feeds (for a Host: the services it hosts)

One row per hosted service, each a part (02 §2.2, "Worker", parent Railway):

| Field | The crawler | W0 Indexer | The video worker | Source |
|---|---|---|---|---|
| Railway service · root | `traversence` · `workers/crawler` | `w0-indexer` · `workers/crawler` | `video-worker` · `/workers/video` | Railway API |
| Feeds | W1–W12 (gathering) | W0 | posted videos (720p and 1080p copies) | manifest `feeds` |
| Schedule, Railway · repo | every 5 min · **not set in `railway.json`** | always on (`python -u indexer.py`) · same | every 5 min · every 5 min | API; repo files |
| Restart, Railway · repo | Never · Never (match) | On failure, up to 10 · same | **On failure, up to 10 · Never: "! they differ"** (amber) | API; repo files; report §3 |
| Latest deploy | `22f18475` SUCCESS, Nov 21 5:22 AM MST, `b2ab69e` (crawler 2.16.1) | `d0e9413f` SUCCESS, same commit | `628caa0d` SUCCESS, Nov 17 6:30 PM MST, `430d4c3` | API |
| Next run · heartbeat | next run 8:55 AM MST (Railway) | heartbeat: not read yet | next run 8:55 AM; heartbeat *spec* | API; manifest |
| State | "On · Working" | "On · Working" | "On · Working" | `state()` |
| Button | Check now | Check now | Check now | row 92 |

A switch that's off inside what a service feeds is shown on that service's row as a count ("2 switches still off"),
and opens to the switch rows (§4 below).

### Zone 3: Sensors and key readiness

| Sensor | Its check (02 §2.7) | Shown today |
|---|---|---|
| Runs | the crawler: a run started within its schedule plus 5 min that reported back | not read yet |
| Heartbeats | W0 Indexer: a heartbeat within 15 min; the video worker: *spec* | not read yet · not built |
| Deploys | Railway's API: each service's latest deploy | 3 of 3 SUCCESS (read 8:51 AM MST) |
| Spend | the month's use against the plan | use not read yet + usage link |
| Railway's own keys | `CRAWLER_API_TOKEN` (.env and Railway), `RAILWAY_API_TOKEN` (.env, watching deploys): the token check (row 52) | not read yet |
| Keys kept on Railway for other modules | `TAVILY_API_KEY`, `BRAVE_API_KEY`, `GOOGLE_PLACES_KEY`, `RIDB_API_KEY`, `HF_TOKEN` (from `service-defs.php`): the worker's key test (row 30) | each "Not checked yet" (grey): "<KEY> on Railway: not checked yet (the worker reports at its next run)." |

**A rule the card makes visible:** a key kept on Railway belongs to its own module (02 §2.2: "`TAVILY_API_KEY` on
Railway, its parent: its module"). It's listed on Railway's card because that's where it lives, under its own heading
("each belongs to its module; its state never colours Railway"), and its state never rolls up into Railway's light.
Presence alone is **Not checked yet**: grey, holding nothing, never red (Q4, row 30).

### Zone 4: Actions and Fix it, in two rows that never mix (02 PL; 04 §6)

**On our system** (board actions, each with its guard): Check now (rows 91–92), Check and start again (a trip only,
row 93), Acknowledge (a trip only, row 96; never on a warning, R80), Take out / Put back (rows 103–104), Pause everything
(row 111, which also stops Railway's schedule), and for a capped part Raise the cap / Lower the cap (row 39). A board action
never claims to change anything at Railway.

**At Railway** (portal actions): four slots in a fixed order, **Billing · Keys · Console · Sign in**, two per row at
390 (Billing · Keys, then Console · Sign in). Each slot is either:
- a link labelled by its type, with the provider's label in small type where it differs, and a source chip; or
- the words "No <type> page registered for Railway yet", greyed, with **Publish from your Vault** (owner only).

Railway today (from `service-defs.php`, mapped by 02 §8's label rule; none from the Vault yet):

| Slot | Shows | Chip |
|---|---|---|
| Billing | "Billing · Usage" → **Open Railway billing ↗** https://railway.com/account/usage | Documented |
| Keys | "No API keys page registered for Railway yet." + Publish from your Vault | — |
| Console | "Console · Dashboard" → **Open Railway console ↗** https://railway.com/dashboard | Documented |
| Sign in | "No sign-in page registered for Railway yet." + Publish from your Vault | — |

**The link-label rules, locked by Jason (04 §5, 2026-11-21), and his §7 answers, applied on every card:**
- **Label format:** the slot type is the main label. The provider's own label follows in small type only where it differs
  ("Billing · Usage", "Console · Dashboard").
- **Source chip:** "Documented", or "From your Vault · <date>". For Railway today every link is Documented.
- **§5's mappings** where other providers appear: cPanel and AI Studio → Console; QuickBooks (the app) → Console;
  Developer app → Keys; Limits → Billing. Google's Rate limits and GitHub's The sync are **not portal slots**. Rate limits
  goes to zone 2's "Its limits" line; The sync goes to the Sync part's "Its runs". Mockup e shows Bluehost's cPanel as
  "Console · cPanel".
- **A Vault link that differs from a documented one:** both show, with "Your Vault has a different <type> page", and
  Jason's pick is recorded. Neither replaces the other by itself. (No Railway case today, so no mockup shows it.)
- **Publish from your Vault** is owner-only ("owner only" beside it), with no account label. Publishing happens on the
  Vault's one-time page first, then in the staff extension. An unknown domain is confirmed on the spot by the owner
  ("This is <Provider>'s domain"). Domains match by registrable domain, with `portal_domains` alternates (railway.app for
  Railway, claude.com for Anthropic, the cPanel host for Bluehost).
- **Staleness:** after 180 days, "not confirmed since <date>" shows as words only, never an alarm.
- Every portal link opens in a new tab; Sign in never fills anything.

**Each portal action is followed by the board action that verifies it** (PL), written under the slots: Billing →
Check now; Console → Check now (or Check and start again on a trip); Keys → Replace key → its Check. Opening a portal page
changes nothing on the board (row 134).

**Fix it names its slot and never borrows another.** The slot Fix it lands on is outlined copper and headed "Fix it lands
here", and the line under the slots names it ("Fix it: Railway's console page"). Routing by fault kind (02 PL, rows
128–133):

| Railway fault | Kind | Fix it lands on |
|---|---|---|
| A deploy failed (row 51) | host | Console (Dashboard). The deploy's own log page is *spec* and not registered |
| The crawler down (rows 47–48), W0 silent (row 50) | host | Console |
| Spend near the cap (row 39) | budget | Billing (Usage) |
| A key missing or refused on Railway (rows 29, 31, 129) | key | **Keys**: not registered, so it says "No API keys page registered for Railway yet" and never borrows Console or Billing (Jason's check, 2026-11-21) |
| Railway's API refuses our token (row 52) | key | Keys (same) |

---

## 2. The stage badge and state: compact, not eight boxes

One line: **"Stage 5 of 8 · Operational"** (§3a's name) and **"Next allowed: …"**, the actions §3a allows from here.
- Working: "Next allowed: Check now · Pause everything · Take out (stage 6) · Deactivate (stage 7)".
- On a trip: "Next allowed: Check and start again · Acknowledge · Take out (stage 6)".
- The current state sits beside the name (zone 1), so stage and state are read together: "On · Working · Stage 5 of 8 ·
  Operational".

§3a's full eight-stage strip ("✓ for each stage reached… an unreached one naming what's missing with its button") moves
to the card's Details and the module's own page. That's a change against §3a's rule that the card shows the strip;
see Open words, item 9.

## 3. The spend chip, computed from the numbers shown (02 BL)

- **Shown:** `<plan> · $<spend> of $<cap> (<pct>%)`, where pct = **round(spend ÷ cap × 100)**, computed from the same two
  numbers printed beside it. The title, body, chip and sensor row all print that one figure.
- **Level:** the band reached (50, 75, 95) decides Warning; the number shown never does (row 39). At 100% it's a trip (row 40).
- **Railway today:** the dollars aren't read, so the chip says **"use not read yet"** with **Railway's usage page ↗**
  (manifest). Mockup c5 shows the rule with illustrative numbers, dotted and labelled: $3.85 of $5.00 = round(77) = 77%,
  past its 75% warning.
- **Finding:** Railway's API *does* answer usage, but in resource units, not dollars (`usage`: CPU 86.6, memory 2,617
  GB, this period; `estimatedUsage` also gives network). Turning them into dollars needs Railway's unit prices, which
  aren't in our records. Until a price source is registered, the chip stays "use not read yet" (Open words, item 6).

## 4. The board's first view: healthy, held, needs me (mockup a)

The first view answers three questions, in this order, in one line at the top:

| Is it healthy | What's held and why | What needs me |
|---|---|---|
| the light and counts: "1 trip · 1 warning · 2 switches still off" | each hold by its cause: "Calls to Common Crawl's index: it isn't answering (IR-####). Nothing in W3 uses it." | trips and switches still off, by count |

**The light stays honest** (g4 F1, row 15): it's red while any trip's hold stands, acknowledged or not. After
Acknowledge it reads "1 trip (seen by Jason)" and stays red. Acknowledge never turns it green, and Check and start again
stays on the trip's row. Mockup a shows both lines.

Then **What needs me**: each item is a row with its buttons on the row. No banner comes first, and nothing actionable sits
in a closed group (the lift's §(f); Jason's ruling 1):
1. **The trip:** "Common Crawl's index isn't answering": its status, Held, Unaffected, then On our system [Check and start
   again] [Acknowledge] and At Common Crawl's four slots (all four unregistered, said in words).
2. **The closed trip's row,** "W3's trip of Nov 20 · Closed Nov 21 · 2 switches still off", **opened by itself**, with
   CS-5's Still off line and a [Switch on] on each switch.
3. **Paused now → Needs attention**, opened by itself, the same two switches with [Switch on].
4. **Warnings**, folded with the count kept ("1"), because no switch is off inside.

The task's own row on W3's page carries the same Switch on (CS-4; mockup c6 shows it in Railway's crawler row). All three
press the one switch.

**Hosts** follow: Bluehost, Railway and GitHub, each with role and criticality. **Railway's host tile** shows its three
services and what each feeds, the restart mismatch in amber, the spend chip, and its radius if it goes down:
- **Held:** the crawler (all gathering, W1–W12), W0, new video copies (they queue).
- **Unaffected:** Bluehost's pages, search, sign-in, payments and databases; GitHub; everything stored.

"Open Railway's card" opens the full card in the side panel (×, Esc, click outside).

Left out of the first view rather than guessed, and said in a mockup note: the 8 other alarms of the 10:19 snapshot,
whose state under the model needs their live readings; GitHub's state (its last release look got no answer at 3:00 AM
MST, `found: -1`, and whether that became row 57's trip depends on looks the snapshot doesn't hold); and the workflows
other than W1 and W3.

## 5. Railway's states (mockups b, c1–c6)

| Mockup | State (02) | Light and first words | Held · On a backup · Unaffected | Told |
|---|---|---|---|---|
| b Working | §3b Working | "On · Working" | — (its radius if down, folded) | — |
| c1 A deploy failed | row 51, Warning | "On · Warning (the system is trying)"; thread "Railway: the crawler's deploy <id> failed at <time>; the old version is running." | nothing · none · everything | board and card only |
| c2 The crawler down | rows 47–48, Trip; the second card after Acknowledge | "On · Paused by the system: the crawler (a trip)"; thread "The crawler is down · Paused by the system (a trip) since …" | all gathering the crawler runs (W1–W12's crawler tasks) · none · W0 Indexer, the video worker, Bluehost's pages, search, sign-in, payments, databases and jobs, GitHub, everything stored | the Founder: Pulse, email, push |
| c3 W0 Indexer silent | row 50: Warning ("Trying again (2 of 3)"), then Trip | amber, then red | warning: nothing; trip: W0 · none · the crawler, the video worker, Bluehost, GitHub | trip only |
| c4 A key missing | rows 30, 31 (as 29), 43 | Railway stays "On · Working"; the key's row red, "Paused by the system"; the other keys "Not checked yet" (grey) | calls that use `TAVILY_API_KEY` · Web search (W8, W10–W12), A missing phone or website (W2), Confirm a business (W1, W2) on Brave Search while its check passes · Railway's three services, the rest of every workflow, the site | trip (a stop kind: no tries) |
| c5 Spend near the cap | row 39 | "On · Warning (Near its cap)"; title "Railway: $3.85 of $5.00 (77%)" (illustrative) | nothing | board only |
| c6 A switch left off | §0 CS-3 case None, row 7 | Railway "On · Working · Needs attention: 2 switches still off"; each switch "Off by the system (left by a closed trip)" | the two W3 tasks don't run | — |

## 6. One failure on every surface: the crawler down at 2 AM (mockup d)

All times are a scenario (dotted). The names, radius, links and switches are real.

| Time (MST) | Board | Pulse | Email | Push |
|---|---|---|---|---|
| 2:05 warning (no run since 1:55) | "The crawler is down · The crawler hasn't reported since 1:55 AM MST · Trying again (1 of 3)." | — | — | — |
| 2:10, 2:20 tries fail; 2:40 the 3rd fails: trip | "Paused by the system: all gathering waits. Since 2:40 AM MST." + Held/Unaffected + [Check and start again] [Acknowledge] [Fix it: Railway's console page] | one row, Critical, unread: "SYSTEM ALARM NOTICE: The crawler is down: Paused by the system", the same Held/Unaffected line, its buttons | a new thread, the same subject, the six parts, the Fix it link `https://traversence.com/admin/dashboard/stream#case=alarm:T-####&fix=1` and "Console at Railway" | "SYSTEM ALARM: The crawler is down" / "Paused by the system (a trip) since 2:40 AM MST. All gathering waits." |
| morning: Fix it | Railway's card opens in the side panel, the thread first, Console's slot outlined | its Fix it opens the same panel | its link opens the same panel | — |
| 3:10 after-trip check passes | "Recovered: The crawler was down 1 h 5 min, the system fixed it." + the check's proof + Still off with two named [Switch on] buttons | the same row's last note, the same words and Still off; it leaves the open list | a reply in the thread, "…: Recovered", the same words, Still off and W3's link | the push already shown is **replaced silently under its tag**: "Recovered. 2 switches still off: open to switch on." |

Nobody is escalated, because it recovered inside Pulse's 2-hour clock (rows 121–122).

## 7. IR-0001 (mockup e)

Rendered from `step2/ir-0001.md` and the manifest's worked report:
- **Title:** "IR-0001 · Correlated · 2 parts: foursquare_bulk, overture_bulk · Paused by the system" (row 124).
- **Root cause:** **Unconfirmed**, in its own row, with Jason's open question and the data that settles it. Its "Confirmed
  so far" row follows: the worker's request to our load endpoint got no reply in time; it wasn't a source and wasn't Railway.
- **Members** with warning and trip ids, and real times: foursquare_bulk (#1288), first failure 08:20:35 UTC, trip 09:25:02;
  overture_bulk (#1290), first failure 08:31:41, trip 09:35:01. The live alarms carry keys, not W-/T- numbers, so the ids
  show as W-#### and T-#### with that said.
- **Timeline:** from Railway's logs.
- **Radius:** as it happened (all of W1 held), and under the contract, both branches.
- **Actions** (both rows), recovery, and prevention.

## 8. The phone at 390

- Cards, rows and frames sit edge to edge, 3px from each side and each other, with .5rem corners. Text keeps its margin.
- Every two- and three-column grid becomes one column: the Held/On a backup/Unaffected boxes stack, and a row's name,
  details and button stack.
- The four portal slots go **two per row**: Billing · Keys, then Console · Sign in.
- The action rows' label goes above its buttons, and the buttons wrap.
- Long strings wrap, never widen the page: URLs, key names (`TAVILY_API_KEY`), commit messages, and badge and status
  strings all wrap (`overflow-wrap: anywhere` on the body; `min-width: 0` on every grid child).
- **Checked:** `document.documentElement.scrollWidth ≤ 390` on every page, plus a scan for any element past the right
  edge (none). Mockups a and d open the side panel, and it closes by ×, Esc and a click outside (all pass).

## 9. Kept, moved or dropped against today's board

| Today | Here | |
|---|---|---|
| The strip first ("Paused now", health line), alarms last; trips' Check and start again only on alarm rows (lift §f) | Three questions first; each trip and switch is a row with its buttons | **moved** |
| "3 engine feeds failing", a closed `<details>` with only "Acknowledge all 3" | Grouped by cause only (row 127); a group with a switch still off opens by itself; each member keeps its buttons | **replaced** |
| Acknowledge turns the light green (g4 F1) | The light stays red: "1 trip (seen by Jason)" | **replaced** |
| The service card's parts in no zone order (g4 §d: stage, status, Used by, Its key, Known price, This month, Caps and paces, Is it working, settings, In our admin, On <company>, Terms, Waiting on them) | Placed in zones: stage, status → 1; Used by → 2; Its key, Is it working → 3; Caps and paces, In our admin, On <company> → 4; Known price, This month → the spend chip; Terms, contact, settings → Details | **kept, moved** |
| 0173's seven stages ("Added · In use", which disagree) | Jason's eight, compact badge; the full strip in Details | **replaced** |
| No role, no criticality on the card | Role badge and criticality in zone 1 | **added** |
| Workers aren't cards; Railway lists one service's deploy | Three services as rows, each with schedule, restart rule against the repo, deploy and feeds | **added** |
| "On <company>" links as one list | Four typed slots, fixed order, source chip, "not registered" words, Publish from your Vault | **replaced** |
| "Nothing could be paused from here", "Couldn't look: HTTP 401", "nobody had set this switch before" | 02's words by state | **dropped** |
| The module list at 390 showing only mark, name and cost | The whole card at 390, stacked | **replaced** |
| Mark resolved, Use backup | — | **dropped** (rows 95; Q17) |

## 10. Open words (the spec has no words for these; none were made up for the UI except where marked)

1. **The top line's answers.** 02 has no sentence for "Is it healthy / What's held and why / What needs me". The
   mockup uses counts and the hold's cause only. The question labels are Jason's (what-the-board-is.md §5).
2. **A Railway key reported missing:** the title. Row 31 says "as 29, 'isn't on Railway'". The mockup composes "Tavily:
   TAVILY_API_KEY isn't on Railway"; it needs confirming.
3. **W0 Indexer's trip status.** Row 50 gives "in rows 47–48's shape". The mockup composes "Paused by the system (a trip)
   since …" with Held: W0. A short form like row 48's "all gathering waits" for W0 has no words.
4. **The crawler's warning try line.** Row 83's "Trying again (2 of 3): <answer> at <time>" needs an <answer> for a
   missing run. The mockup uses "no run reported" / "no heartbeat".
5. **The push body's counts.** §7's "<n> wait, <m> on a backup" needs the crawler's task count, which no manifest field
   holds. The mockup uses row 48's "All gathering waits."
6. **The spend chip in resource units.** Railway's API reports usage in units, not dollars (§3). There are no words for
   "read but not priced"; the chip keeps "use not read yet".
7. **The Pulse sender's name** ("Control Board") and the email's From ("Traversence Control Board"): 0172 decides; 02 doesn't say.
8. **Recovered's proof line.** §3c's system Recovered carries no proof. The mockup adds "Its check passed: a crawler run
   started at <time> and reported back." (§2.7's proof shape).
9. **The eight-stage strip.** §3a says the card shows it; the brief says a compact badge. The mockup keeps the badge and
   moves the strip to Details. Which one stands needs Jason's call.
10. **"Check and resume" vs "Check and start again".** The manifest and Jason's checklist say "Check and resume"; 02 PL
    and rows 93–94 say "Check and start again". The mockups use 02's.
11. **"Pause our calls" on a Host.** Row 39 lists it for a capped part, but for Railway it has no defined meaning (the
    host's stop is Pause everything). It's left off c5's buttons.
12. **The keys slot's words.** 02 PL and 04 §5's rule 4 say "No <type> page registered…" with the type "keys"; Jason's
    Railway check says "No API keys page registered for Railway yet". The mockups use "API keys" for every provider. Confirm
    that this one wording holds everywhere.
13. **Fix it for a Railway key.** Row 129 sends it to "At Railway: Open Railway console ↗ (the crawler service →
    Variables)". Jason's check (2026-11-21) says it points to the Keys slot and never borrows Console. The mockups
    follow Jason: Keys, unregistered, said in words, with "The key itself lives in the crawler service → Variables" as text.
14. **The deploy's own log link** (manifest fix_it "that deploy's log", *spec*): no `portal_links` type holds a per-deploy
    URL, and none is registered.
15. **CS-5's Still off on a trip that left no switch.** In the 2 AM scenario the two W3 switches were left by an earlier
    trip, not the crawler's. The mockup still names them on Recovered, with their own trip ("left off by W3's trip of
    Nov 20"), because they sit inside what this trip held. CS-5 is written for switches the closing trip left; whether it
    covers these too needs confirming.
16. **"Opened by itself"** beside an auto-opened group, and the "If Railway itself is down: its Incident Radius" fold
    label, aren't spec words.

## 11. What the mockups show and where

| # | Item | Pass/fail | Where |
|---|---|---|---|
| 1 | Railway as a Host with its radius; the light honest after Acknowledge | pass | a (host tile, side panel, "After Acknowledge"); b (radius fold); c2 (second card) |
| 2 | All 7 states | pass | b, c1, c2, c3, c4, c5, c6 |
| 3 | Zone 4 in two rows; four slots; "not registered" words; verifying action after each | pass | every card's zone 4; a (Common Crawl's slots) |
| 4 | 2 AM on four surfaces, Recovered closing all four | pass | d |
| 5 | IR-0001, root cause Unconfirmed, ids, timeline, radius | pass | e |
| 6 | No sideways scroll at 390, worst cases | pass | all pages (§8) |
