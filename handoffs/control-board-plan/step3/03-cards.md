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
readiness, 4 Actions and Fix it. **One exception, the fix for the lift's §(f) (approved, Jason, 2026-11-21):** while a fault is open on the module
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
| Spend chip | "Hobby · $5 a month including $5 of usage · Use since Nov 1: CPU_USAGE 87.17 · MEMORY_USAGE_GB 2,626.44 · NETWORK_TX_GB 2.39 · NETWORK_RX_GB 22.56 · not priced yet · Billing · Usage ↗" | `plan`, `caps`, ledger | §3 below |
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
| Spend | the month's use against the plan | Railway's real usage in its own measures, since Nov 1, "not priced yet", with Billing · Usage ↗ (§3) |
| Railway's own keys | `CRAWLER_API_TOKEN` (.env and Railway), `RAILWAY_API_TOKEN` (.env, watching deploys): the token check (row 52) | not read yet |
| Keys kept on Railway for other modules | `TAVILY_API_KEY`, `BRAVE_API_KEY`, `GOOGLE_PLACES_KEY`, `RIDB_API_KEY`, `HF_TOKEN` (from `service-defs.php`): the worker's key test (row 30) | each "Not checked yet" (grey): "<KEY> on Railway: not checked yet (the worker reports at its next run)." |

**A rule the card makes visible:** a key kept on Railway belongs to its own module (02 §2.2: "`TAVILY_API_KEY` on
Railway, its parent: its module"). It's listed on Railway's card because that's where it lives, under its own heading
("each belongs to its module; its state never colours Railway"), and its state never rolls up into Railway's light.
Presence alone is **Not checked yet**: grey, holding nothing, never red (Q4, row 30).

### Zone 4: Actions and Fix it, in two rows that never mix (02 PL; 04 §6)

**On our system** (board actions, each with its guard): Check now (rows 91–92), **Check and start again** (a trip only,
row 93; the one wording everywhere, never "Check and resume", Jason, 2026-11-21), Acknowledge (a trip only, row 96; never on a warning, R80), Take out / Put back (rows 103–104), Pause everything
(row 111, which also stops Railway's schedule), and for a capped part Raise the cap / Lower the cap (row 39). A board action
never claims to change anything at Railway.

**At Railway** (portal actions): four slots in a fixed order, **Billing · Keys · Console · Sign in**, two per row at
390 (Billing · Keys, then Console · Sign in). Each slot is either:
- a link labelled by its type, with the provider's label in small type where it differs, and a source chip; or
- the words "No <type> page registered for Railway yet", greyed, with **Publish from your Vault** (owner only).

Railway today (from `service-defs.php`, mapped by 02 §8's label rule; none from the Vault yet):

| Slot | Shows | Chip |
|---|---|---|
| Billing | "Billing · Usage" → **Open Railway billing ↗** https://railway.com/account/usage | Documented · release #212 |
| Keys | "No API keys page registered for Railway yet." + Publish from your Vault | — |
| Console | "Console · Dashboard" → **Open Railway console ↗** https://railway.com/dashboard | Documented · release #212 |
| Sign in | "No sign-in page registered for Railway yet." + Publish from your Vault | — |

**The link-label rules, locked by Jason (04 §5, 2026-11-21), and his §7 answers, applied on every card:**
- **Label format:** the slot type is the main label. The provider's own label follows in small type only where it differs
  ("Billing · Usage", "Console · Dashboard").
- **Source tag, one of four (locked by Jason, 04 rule 3):**
  1. **"From your Vault · <date>"**: published from his unlocked Vault.
  2. **"Site settings"**: built from a `.env` value. Bluehost's Console comes from `CPANEL_HOST`, so mockup e's
     "Console · cPanel" carries "Site settings".
  3. **"Documented · release #<real number>"**: added to `service-defs.php` by that approved release, never a "#N"
     placeholder. Railway's Billing · Usage and Console · Dashboard are **"Documented · release #212"** (0173 release 3,
     which created `service-defs.php`). The tag's tooltip reads "Written by a Claude session from Railway's own pages".
     "Claude session" appears only in that tooltip, never as a separate badge.
  4. **"Not registered"**: the slot reads "No <type> page registered for <Provider> yet", with "Publish from your Vault".
  For Railway today both links are Documented · release #212, and none comes from the Vault. The same #212 tag is drawn on
  the other documented links the mockups show (Tavily's Console · Dashboard in c4, Bluehost's Sign in · Account in e):
  both are in `service-defs.php`'s earliest copy on the mirror (`86c278d`, Nov 21 03:19 UTC). That copy came after
  #212, so it can't prove #212 added them; confirm with the release record.
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
| A key missing or refused on Railway (rows 29, 31, 129) | key | **Keys** (approved, Jason, 2026-11-21): not registered, so it says "No API keys page registered for Railway yet" and never borrows Console or Billing. The key itself lives in the crawler service → Variables, said as text. Row 129 in 02 is being corrected to match |
| Railway's API refuses our token (row 52) | key | Keys (same) |

---

## 2. The stage badge and state: compact, not eight boxes (approved, Jason, 2026-11-21)

One line: **"Stage 5 of 8 · Operational"** (§3a's name) and **"Next allowed: …"**, the actions §3a allows from here.
- Working: "Next allowed: Check now · Pause everything · Take out (stage 6) · Deactivate (stage 7)".
- On a trip: "Next allowed: Check and start again · Acknowledge · Take out (stage 6)".
- The current state sits beside the name (zone 1), so stage and state are read together: "On · Working · Stage 5 of 8 ·
  Operational".

The card shows only this compact badge. The full eight-stage breakdown stays in 02's state section (§3a), not on the card
(Jason, 2026-11-21).

## 3. The spend chip, computed from the numbers shown (02 BL)

- **Shown:** `<plan> · $<spend> of $<cap> (<pct>%)`, where pct = **round(spend ÷ cap × 100)**, computed from the same two
  numbers printed beside it. The title, body, chip and sensor row all print that one figure.
- **Level:** the band reached (50, 75, 95) decides Warning; the number shown never does (row 39). At 100% it's a trip (row 40).
- **Railway today (Jason, 2026-11-21, answer 6):** the chip shows Railway's **real usage in its own measures**, read
  from its API (read-only, the `usage` query, never variables), marked **"not priced yet"**, with the Billing · Usage link.
  It never says "use not read yet", and it shows **no dollar figure until a price source is registered**. Read Nov 21,
  16:27 UTC (9:27 AM MST), since Nov 1 00:00 UTC, as the API names them: `CPU_USAGE` 87.17, `MEMORY_USAGE_GB` 2,626.44,
  `NETWORK_TX_GB` 2.39, `NETWORK_RX_GB` 22.56. No disk measure was returned. The API gives no units beyond these names,
  so none are added.
- **Mockup c5** shows the near-cap state as it will read **once a price source exists**: the dollars are illustrative,
  dotted and labelled. $3.85 of $5.00 = round(77) = 77%, past its 75% warning.

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
| 0173's seven stages ("Added · In use", which disagree) | Jason's eight, as the compact badge; the breakdown stays in 02 §3a | **replaced** |
| No role, no criticality on the card | Role badge and criticality in zone 1 | **added** |
| Workers aren't cards; Railway lists one service's deploy | Three services as rows, each with schedule, restart rule against the repo, deploy and feeds | **added** |
| "On <company>" links as one list | Four typed slots, fixed order, one of four source tags, "not registered" words, Publish from your Vault | **replaced** |
| "Nothing could be paused from here", "Couldn't look: HTTP 401", "nobody had set this switch before" | 02's words by state | **dropped** |
| The module list at 390 showing only mark, name and cost | The whole card at 390, stacked | **replaced** |
| Mark resolved, Use backup | — | **dropped** (rows 95; Q17) |

## 10. Jason's answers to the open words (Jason, 2026-11-21)

Each was an open word in the first draft. Jason answered all 13 ("Accept, then GitHub"):
1. **The top line's answers:** as drawn (counts and the hold's cause; his three questions as the labels).
2. **A Railway key reported missing:** as drawn, "Tavily: TAVILY_API_KEY isn't on Railway".
3. **W0 Indexer's trip status:** as drawn, "Paused by the system (a trip) since …", Held: W0.
4. **The crawler's try line:** as drawn, "no run reported" / "no heartbeat" as the <answer>.
5. **The push body:** as drawn, row 48's "All gathering waits."
6. **The spend chip:** Railway's real usage in its own measures, "not priced yet", with Billing · Usage; never "use not read
   yet"; no dollar figure until a price source is registered (§3; applied to every Railway page).
7. **Senders:** the Pulse sender is **"Control Board"**; the email's From is **"Traversence Control Board"** (as drawn).
8. **Recovered's proof line:** as drawn, "Its check passed: <what was checked>."
9. **"Pause our calls" on a Host:** as drawn, left off; the Host's stop is Pause everything.
10. **The keys slot's words:** as drawn, "No API keys page registered for <Provider> yet" for every provider.
11. **The deploy's log link:** built from Railway's own ids, `https://railway.com/project/<project id>/service/<service id>?id=<deployment id>`,
    marked *spec*. Each service's latest deploy in zone 2 now carries "its log ↗ (spec)", and c1's Fix it line carries the
    failed deploy's log link from the alarm's evidence.
12. **Recovered names every switch still off inside what the trip held,** each with its own trip ("left off by W3's trip
    of Nov 20"), as drawn in the 2 AM walkthrough.
13. **"Opened by itself" and the radius fold's label:** as drawn.

**Open words now:** none for Railway. GitHub's are in §12.6.

## 11. What the mockups show and where

| # | Item | Pass/fail | Where |
|---|---|---|---|
| 1 | Railway as a Host with its radius; the light honest after Acknowledge | pass | a (host tile, side panel, "After Acknowledge"); b (radius fold); c2 (second card) |
| 2 | All 7 states | pass | b, c1, c2, c3, c4, c5, c6 |
| 3 | Zone 4 in two rows; four slots; "not registered" words; verifying action after each | pass | every card's zone 4; a (Common Crawl's slots) |
| 4 | 2 AM on four surfaces, Recovered closing all four | pass | d |
| 5 | IR-0001, root cause Unconfirmed, ids, timeline, radius | pass | e |
| 6 | No sideways scroll at 390, worst cases | pass | all pages (§8) |

---

## 12. GitHub's card (mockups `github-*.html`)

Built the same way as Railway's: one generator (`mockups/src/github.py`), the four zones, Jason's rulings (02 §0a),
the locked label rules and source tags (04 §5), and §1–§10 above. **Real data:** GitHub Actions read-only (Nov 21, about
9:30 AM MST): `sync.yml` runs #682–#691 on `degraffics/traverse-3way-sync`; Release ping #367–#370, Site audit #46–#49,
Site checks (browser) #30–#33 and Search probe #60–#63 on `degraffics/traversence`. Also the live alarms' `releases_looked`
(10:19 UTC), `service-defs.php` ('github'), and `ControlRegister` (`'svc:github' => 'procedures'`). Token values and
expiry dates weren't read; the mockups say "not read yet".

### 12.1 The four zones

| Zone | GitHub |
|---|---|
| 1 Identity and role | **GitHub** · Host · "Critical: procedures (releases and the sync)" · state · "Stage 5 of 8 · Operational · Next allowed: Check now · Check for builds · Run the sync · Take out · Deactivate" · spend chip "Actions minutes: within the free allowance on the current plan · use not read yet (our tokens can't read billing) · Billing ↗" · "If GitHub itself is down: its Incident Radius ▸" |
| 2 What it runs and feeds | one row per run, each with schedule, what it feeds, its last real run and "Its runs ↗": **the sync** (`sync.yml`, 06:00 and 18:00 UTC or on demand, one at a time → the live mirror and OneDrive; last #691, scheduled, success, Nov 21 7:25 AM MST); **Release ping** (#370, 5:22 AM MST, "Release #247…"); **the release look** (the site's 10-minute job; last looks 3:00 AM MST no answer `found: -1`, Nov 20 8:17 PM MST found 0, Nov 20 11:28 AM MST found 0); **Site audit** (#49, 9:23 AM MST); **Site checks** (#33, 2:17 AM MST, 26 min); **Search probe and health report** (#63, 3:17 AM MST). The two repositories it holds, and that Railway deploys from `degraffics/traversence` `main` |
| 3 Sensors and keys | the release look; the release job quiet (built); the sync's runs (failed, stuck over 15 min, none in 14 h, a slot missed an hour after it); the other Actions' last runs; tokens by name only: `GITHUB_DOCS_TOKEN`, `GITHUB_SYNC_TOKEN`, `HEALTH_TOKEN` (a GitHub secret and its `.env` copy, which must match), each "reads its repository: not read yet · expiry: not read yet", amber 14 days before expiry |
| 4 Actions and Fix it | **On our system:** Check now · Check for builds · Run the sync (lists the runs first, never two at once) · on a trip Check and start again, Acknowledge · on a token Replace key. **At GitHub**, fixed order: Billing · Billing → **Open GitHub billing ↗** https://github.com/settings/billing, "Documented · release #212" · Keys: "No API keys page registered for GitHub yet." · Console: "No console page registered for GitHub yet." · Sign in: "No sign-in page registered for GitHub yet." · each missing slot with "Publish from your Vault" (owner only) |

**"The sync" is not a portal slot** (Jason's ruling, 04 §5): `service-defs.php`'s "The sync" link
(https://github.com/degraffics/traverse-3way-sync/actions) is shown as the sync row's **"Its runs ↗"** in zone 2.
**#212:** release #212 carried `app/lib/service-defs.php` (its record, `deliveries/releases/2026-11-19-212-…json`, live Nov 19
23:25 UTC). The mirror's earliest copy (`86c278d`) already has the Billing link. The record lists files, not lines, so it
shows #212 shipped the file, not that it added this line.

### 12.2 Its Incident Radius (manifest §2; 02 §6)

| Fault | Held | Unaffected |
|---|---|---|
| GitHub down | releases (builds wait in the inbox; records aren't written), the sync and the mirror and OneDrive, **new Railway deploys** (they build from GitHub) | Bluehost's pages, search, sign-in, payments, databases; gathering on current versions; everything stored |
| A token refused | what that token does (docs: releases; sync: watching the sync; health: the health report) | everything else |
| The sync failed or stuck (at its trip) | the sync, the mirror and OneDrive | releases, the site |
| A sync slot missed | nothing (a warning; the mirror is late) | everything |
| A check run failed | nothing (that report waits) | everything |

### 12.3 The states

| Mockup | Row | State and words | Fix it |
|---|---|---|---|
| a The board | 61 | GitHub's tile "On · Warning: The 06:00 UTC sync didn't run; the mirror is behind since 03:19 UTC.", real at 3:19 AM MST; its radius; Open GitHub's card (side panel) | — |
| b Working | — | "On · Working", the real runs | — |
| c1 API not answering | 57 | critical, trip at once: title "GitHub", "Down: the system is still trying since …. Releases wait in the inbox."; second card after Acknowledge, still red | Console: "No console page registered for GitHub yet", never Billing |
| c2 A token | 58, 59 | "GITHUB_SYNC_TOKEN expires <date> (in <n> days)" (warning, date a scenario), then "GitHub refused GITHUB_DOCS_TOKEN · Paused by the system: releases wait." | Keys: "No API keys page registered for GitHub yet", then Replace key |
| c3 The sync stuck | 60 | "The sync's run #<n> is stuck · Trying again (1 of 3)" (run number a scenario) | the run's page with Cancel (from the alarm's evidence); no slot outlined |
| c4 A slot missed (real) | 61 | "The 06:00 UTC sync didn't run; the mirror is behind since 03:19 UTC." Held nothing; Late: mirror and OneDrive. Cleared: "the sync's run #690 finished well (5:26 AM MST)". The 07:00 UTC raise is *spec* (nothing was raised live) | the sync's runs |
| c5 A check run failed | — (manifest) | "Site checks (browser) failed: run #<n>" (a scenario; every real run today succeeded); its next run is its check | its run's log |
| c6 The release look (real `found: -1`) | 57 | the contract's trip on today's 10:00 UTC look, under a banner **"Not an observed trip"**: live, nothing was raised, and release #247 went live at 5:22 AM MST | Console: not registered |
| d API down, every surface | 57, §7 | board → Pulse ("Control Board", Critical) → email ("Traversence Control Board", "SYSTEM ALARM NOTICE: GitHub: Down: the system is still trying") → push ("SYSTEM ALARM: GitHub" / "Down: releases wait in the inbox.") → Fix it in the side panel → Recovered on all four ("Recovered: GitHub was down <n>, the system fixed it."; "Still off: none"; the push replaced silently) | Console: not registered |

### 12.4 Real findings the card shows

- **The 06:00 UTC slot:** no run came for it. #690 was started by hand at 12:24 UTC and caught the mirror up. A run GitHub
  marks "schedule" (#691) started at 14:25 UTC; GitHub doesn't record which slot it served. Yesterday's 18:00 slot ran at
  23:48 UTC, about six hours late.
- **The 10:00 UTC release look** got no answer (`found: -1`) and raised nothing. Under the contract (GitHub is critical)
  it would be a trip at once (c6).
- **No check run failed today:** the last runs of the audit, checks and probe all succeeded.

### 12.5 The phone at 390

The same rules as §8. GitHub's four slots sit two per row (Billing · Keys, Console · Sign in), and the long workflow
paths, commit titles and `found: -1` lines wrap. Every page passes `scrollWidth ≤ 390`.

### 12.6a Jason's decisions (2026-11-21, in the decision pop-up, reviewed by the session manager)

1. **railway-c5's dollars: kept, labelled.** Its banner says plainly "Illustrative: no price source yet; today the chip
   shows units, not priced yet", and today's real units chip is shown beside it.
2. **GitHub's API down: "Paused, then trying".** Words follow the state (P9). Once a hold is placed the status is
   "Paused by the system (releases and the sync)", and the trying is a second clause: "Paused by the system (releases
   and the sync): still trying, 2 of 3, next at <time>". GitHub is critical, so it trips at once while it tries; the
   words say both. 02's row 57 is corrected to match.
3. **GitHub's Actions minutes: yes, a read-only token** (spec only; nothing is added now).
   - A separate fine-grained token, read-only, with only the billing or plan read it needs.
   - Entered later through Its key, never in chat, at no cost.
   - Until then the chip stays "use not read yet" with the billing link.
   - It's a required key in GitHub's manifest once built.

### 12.6 Open words for GitHub

1. **Row 57's "Down" while something is held.** §5.4 says "Paused by the system" whenever a hold exists, and "Down: the
   system is still trying" for a critical part with nothing held. Row 57 uses "Down" while releases and the sync are held.
   The mockups follow row 57.
2. **Row 58's URL.** Row 58's words end "Replace it: https://github.com/settings/tokens". Under the locked rules that page
   would be GitHub's Keys slot, which isn't registered, so the mockup leaves the URL out of the alarm's words.
3. **A stuck sync's title.** Row 60 has words for "failed"; the mockup composes "The sync's run #<n> is stuck".
4. **A failed check run's words.** No 02 row names it; the mockup uses row 62's shape, "<name> failed: run #<n>".
5. **GitHub's spend chip.** The tokens can't read Actions minutes, so the chip keeps the manifest's "use not read yet".
   Jason's answer 6 covered Railway, where the API reads usage; whether GitHub needs a token with billing read access is open.
6. **The sync's "Cancel the run"** is a board action on GitHub's run (manifest fix_it "with Cancel"); 02 has no row for it.

## 13. Setup & Access Capture on the card (Jason, 2026-11-21; `04-vault-portal-links.md` §10; 02 §3a stage 1's exit guard)

**What this section holds:**
- Setting **names** only, never a value.
- Every key name below is read from the live code (`service-defs.php`, `Operations.php`, `Preflight.php`) or the
  repositories' workflow files.

**Zone 1's badge, for a new module in stage 1:**

"Stage 1 of 8 · Selection and configuration · Next allowed: Complete setup capture"

The missing items are listed by name, each with its own button. Examples:
- "Sign in not recorded" [Record sign in]
- "Where TAVILY_API_KEY lives not recorded" [Record]
- "Billing: Not registered, no reason given" [Give a reason]

The module can't move to stage 2 until all three are recorded (02 §3a). Credentials go only into the Vault.

**Today's modules** went live before the rule, so their gaps are a **notice** in zone 1, under the stage badge. For
example:
- "Setup capture: Sign in not recorded · Keys: why not recorded".
- Grey words with a small "Record ▸", never red or amber.
- Never an alarm, a hold or a Pulse item.
- It stays until the module's next setup change, which then has to complete it.

### 13.1 Railway: its setup capture

| Item | What's recorded now | Gap shown as notice |
|---|---|---|
| **Where to sign in** | Sign in slot: Not registered | "Sign in not recorded" |
| **Where its keys live** | `CRAWLER_API_TOKEN`: `.env` and Railway (the crawler's pass into our site) · `RAILWAY_API_TOKEN`: `.env` (watching deploys, setting the schedule) · `RAILWAY_PROJECT_ID`, `RAILWAY_SERVICE_ID`: `.env` (ids, not secrets). Keys the worker holds **for other modules** (e.g. `TAVILY_API_KEY`) are recorded on those modules' cards, with Railway as their place | — |
| **Keys slot** | Not registered (Railway's project Variables page isn't recorded) | "Keys: why not recorded" |
| **Billing slot** | Billing · Usage · Documented · release #212 | — |
| **Console slot** | Console · Dashboard · Documented · release #212 | — |
| **Unregistered, with why** | Keys and Sign in have no why yet | Both listed. Each is filled by Jason's Vault publish, or a one-line why |

The notice reads: "Setup capture: Sign in not recorded · Keys: why not recorded". Its [Record ▸] opens the Setup
capture panel. There, the two slots show "Publish from your Vault" (owner only), or a field for a one-line why.

### 13.2 GitHub: its setup capture

| Item | What's recorded now | Gap shown as notice |
|---|---|---|
| **Where to sign in** | Sign in slot: Not registered | "Sign in not recorded" |
| **Where its keys live** | `GITHUB_DOCS_TOKEN`: `.env` (reads the inbox, writes release records, asks for the checks) · `GITHUB_SYNC_TOKEN`: `.env` (watches the sync) · `HEALTH_TOKEN`: a GitHub secret on degraffics/traversence **and** its `.env` copy (they must match) · `MIRROR_READ_TOKEN`, `CHECK_ACCOUNTS`, `CLAUDE_CODE_OAUTH_TOKEN`: GitHub secrets on degraffics/traversence (the audit, the checks, Claude's workflow) · the sync's own secrets on degraffics/traverse-3way-sync: `ONEDRIVE_CLIENT_ID`, `ONEDRIVE_TENANT_ID`, `ONEDRIVE_REFRESH_TOKEN`, `FTP_HOST`, `FTP_USER`, `FTP_PASS`, `FTP_REMOTE_PATH`, `GH_PAT` (optional) · **planned:** a read-only billing token for Actions minutes (Jason, 2026-11-21: Vault, then Its key; not added yet) | — |
| **Keys slot** | Not registered (GitHub's token settings page isn't recorded) | "Keys: why not recorded" |
| **Billing slot** | Billing · Billing · Documented · release #212 | — |
| **Console slot** | Not registered ("The sync" is our own workflow's page: it sits on the Sync part as "Its runs", not in a slot) | "Console: why not recorded" |
| **Unregistered, with why** | Keys, Console and Sign in have no why yet | All three listed |

The notice reads: "Setup capture: Sign in not recorded · Keys, Console: why not recorded".

**Proof (step 4):**
- A new module can't leave stage 1 while any item is missing.
- Today's modules show the notice and are never held.
- No credential value appears on any page, record or message: the mockups show names only.

---

## 14. Bluehost's card (mockups `bluehost-*.html`)

Built like Railway's and GitHub's (`mockups/src/bluehost.py`), starting from step 2's g3 §3, which already wrote Bluehost to
the contract. Each claim was checked against the live code at mirror `aeb5f31` (cited below). Live readings come only
from the health report of 10:19 UTC (3:19 AM MST, `site-audit/results/server.json`, written by Search probe run #63):
PHP 8.0.31, MySQL 5.7.44-48, 414 tables, 630.89 MB. No `.env` was opened and no live page was called. Everything else
reads "not read yet".

### 14.1 What it is (checked)

| Claim | The live code |
|---|---|
| A **Host**, **critical** | `ControlRegister::CRITICAL`: `svc:bluehost` → page, `site:env` → page, `site:db` and `site:dbup` → database, `site:sched` → procedures (L88–104). The duties are `DUTIES` (L75–81) |
| Its duties | pages load, search, sign-in, payments, the database, the scheduler (cPanel cron), and the site's own email (the need "The site's own email": Bluehost, then AWS; `Preflight.php` NEEDS) |
| Its definition | `service-defs.php` 'bluehost': "The web host: the site itself, cPanel (jobs, mailboxes, through its API), and the site's own email"; "A flat hosting plan. Its price goes in the monthly total once it's set (Settings: Bluehost plan, a month)" (`BLUEHOST_USD_MONTH`, `EnvSettings.php:22`); links Account and cPanel |
| cPanel's address | `CPanel.php:36–39`: `https://<CPANEL_HOST>:2083`, with `CPANEL_USER` and `CPANEL_TOKEN` |
| The scheduler | `Scheduler::JOBS` 50, `AUTO` 40, `QUIET` 4; `billing_tick` isn't in `AUTO`, so nothing checks its cron line (g3 J8) |
| **Today's mismatch** | `svc:bluehost`'s line tests the site's email sign-in and cPanel's cron list (`Preflight.php:978–987`) but carries the Page load duty; its title is the part that passed ("Bluehost: The site's email: signed in."); `site:env` carries Page load while its test also fails on an unwritable `.env` (`Preflight.php:744–747`). g3 D6, F5. Shown on page b as an observed note; the card is drawn as the spec says |

### 14.2 The four zones

| Zone | Bluehost |
|---|---|
| 1 Identity and role | **Bluehost** · Host · "Critical: page, database, procedures" · state · "Stage 5 of 8 · Operational · Next allowed: Check now · Set them up now · Run database updates now · Take out (stage 6, *spec*)" · Setup capture notice · spend chip "A flat hosting plan · price not read yet (the setting "Bluehost plan, a month", `BLUEHOST_USD_MONTH`) · Billing: No billing page registered for Bluehost yet." · its radius fold · Setup capture fold |
| 2 What it hosts | one row per hosted part, **each with its own duty** (the fix for D6): the site (pages; Page load); the databases (4 realms; Database connections); `.env` (Page load to read, Settings and Its key to write); the scheduler (procedures); cPanel's API (no duty: setting up jobs and mailboxes); the site's own email (procedures, through its need, backup Amazon SES); our endpoints for the workers (no duty: gathering) |
| 3 Sensors | the outside heartbeat (GitHub, every 15–30 min, loads the public home page with no token, *spec*; 14.6a §5); one query per realm at each watch (*spec*); the watch's tick and each critical job's cron line; cPanel's cron list; the email sign-in without sending; `.env` readable, writable, each name once; the bot check (*spec*) |
| 4 Actions | **On our system:** Check now · Set them up now · Run database updates now · Take out (greyed: "a module can't be taken out today", g3 M5). **At Bluehost:** Billing: not registered · Keys: not registered · **Console · cPanel**, `https://‹CPANEL_HOST›:2083`, **Site settings** · **Sign in · Account** https://my.bluehost.com/, **Documented · release #212** (release #212 carried `service-defs.php`; the mirror's earliest copy has the link). Each missing slot carries "Publish from your Vault" (owner only) |

`service-defs.php` also lists cPanel as `https://my.bluehost.com/hosting/app#/cpanel` (and `Preflight.php:124` uses it
for .env fixes). Jason's tag rule makes Bluehost's Console the Site settings link built from `CPANEL_HOST`, so only that
is drawn. IR-0001's page now uses the same form.

### 14.3 Setup capture (§13; 04 §10)

Notice in zone 1: **"Setup capture: Billing, Keys: why not recorded"** (grey, Record ▸; never an alarm, a hold or a
Pulse item). Sign in is recorded (Account) and Console is recorded (Site settings), so only Billing and Keys lack a why.
Where its keys live, names only (`Database.php` L120–160, `CPanel.php`, `EnvSettings.php`, the endpoints):
- `DB_HOST`, `DB_PORT`, `DB_CHARSET`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (also read as `DB_DATABASE-main` and the like)
- `DB_ADMIN_*`, `DB_LISTINGS_*`, `DB_USERS_*` (database, username, password each)
- `CPANEL_HOST`, `CPANEL_USER`, `CPANEL_TOKEN`
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`, `SMTP_REPLY_TO`
- `STAFF_EMAIL_DOMAIN`
- `CRAWLER_API_TOKEN`, `VIDEO_WORKER_TOKEN` (.env and Railway)
- `HEALTH_TOKEN` (.env and a GitHub secret)
- `BLUEHOST_USD_MONTH`

All of them live in `.env`, above `public_html`.

### 14.4 Its Incident Radius (02 §6; g3 §3)

| Fault | Held | Unaffected |
|---|---|---|
| Bluehost down | everything that runs on it: pages, search, sign-in, payments, the admin, Pulse and the board, every site job, staging, trvgo.us; our crawl and video endpoints, so the workers' reports wait | Railway's workers keep running but can't report; GitHub (releases wait in the inbox); the mirror and OneDrive; everything stored |
| One realm refused | what reads it (listings: search and listing pages, and the endpoints writing listings) | the other realms' pages, sign-in, the admin |
| cPanel's API refuses its key | setting up or changing cron lines and mailboxes | every job (on its existing line), everything else |
| A critical job's cron line missing | that job's work (renewals for `billing_tick`) | every other job, pages, checkout |
| Its email refused | sending through Bluehost; the need goes to Amazon SES ("On a backup") | sign-in links still go out; everything else |
| A `.env` name missing (`HEALTH_TOKEN`) | what that name does (the daily health report) | everything else |

### 14.5 The states

| Mockup | Row | Status words | Fix it |
|---|---|---|---|
| a The board | — | Bluehost's tile "On · Warning (Slow): /api/crawl/loads is slow" (02 group A) with its radius, plus **"When Bluehost is down, the board can't load"**: the email only, from the outside heartbeat through Amazon SES, with no incident number yet; no push (14.6a) | — |
| b Working | — | as above; the observed mismatch note | — |
| c1 Pages not loading | 53 | "Page load · Down: the system is still trying" (critical, a trip at once) | Console · cPanel and Sign in · Account: both open off the host |
| c2 A realm refused | 54 | "The listings database · Paused by the system (the listings endpoints): still trying, 1 of 3, next at <time>" (14.6a) | Console · cPanel (MySQL databases) |
| c3 cPanel's key | 29, 56, **14.6a §4** | "cPanel's key refused: mailboxes and cron jobs wait · Paused by the system (setting up mailboxes and cron lines): waits for a new key." Existing jobs keep running on their lines | **On our system:** Its key with Replace ready (`CPANEL_TOKEN` is in `.env`). **At Bluehost:** "No API keys page registered for Bluehost yet", Publish from your Vault; never borrows Console (row 129) |
| c4 A cron line missing | 55 | "The site's scheduled jobs · Paused by the system (renewals): still trying, 1 of 3, next at <time>" (14.6a; scenario on the real gap, `billing_tick`) | Set them up now; Console · cPanel (Cron jobs) |
| c5 Its email | 29, 43 | "Bluehost's email refused our key · Paused by the system: calls with SMTP_PASSWORD wait for a new key." The need: "The site's own email is on its backup (Amazon SES) while Bluehost's email is paused (IR-####)." | Keys: not registered; Replace key |
| c6 A .env name missing | 31, 71, 107 | "HEALTH_TOKEN isn't in .env · Paused by the system: the daily health report waits." | Its key (Add its key); Keys: not registered |
| d Pages down | 53, §7 | the board and Pulse can't answer; **email only** from the outside heartbeat through Amazon SES ("SYSTEM ALARM NOTICE: Page load: Down: the system is still trying"), no number yet; **no push**; Fix it by the email's links to cPanel and Account, then the card once pages load; back: the record is taken in and numbered, Pulse shows it as "Raised while the site was down, by the outside heartbeat" with its real times, and Recovered closes it on every channel ("Recovered: Page load was down <n>, the system fixed it."; no push to replace) (14.6a) | Console · cPanel; Sign in · Account |

### 14.6a Jason's answers (2026-11-21, all as recommended, reviewed by the session manager)

1. **When Bluehost is down (open words 1–3).**
   - The outside heartbeat (GitHub Actions, every 15–30 min) sends **email only**, through the site's existing Amazon
     email (SES), with a GitHub secret limited to sending.
   - The push keys stay on Bluehost, so there's **no push** while the host is gone.
   - It writes the incident as an **unnumbered record in the repo**: its times, what failed, its checks. The email names
     no T- or IR- number.
   - When the site is back, it takes that record in, **assigns the T- and IR- numbers then**, and shows it in Pulse as
     **"Raised while the site was down, by the outside heartbeat"**, with the real times. Recovered then closes it on
     every channel.
   - SES costs fractions of a cent a message, inside the email budget, under the system-email exception (Jason confirmed).
   - Applied: pages a and d (email only, no push frame during the outage; the Pulse row after return carries the words and
     times; no push to replace on Recovered), and c1's thread.
2. **"Down" while something is held (open word 4).** Words follow the state.
   - Once something is held, the status reads "Paused by the system (<what's held>): still trying, n of 3, next at
     <time>". Applied: c2 "Paused by the system (the listings endpoints)…", c4 "Paused by the system (renewals)…".
   - "Down: the system is still trying" only when the host itself is gone and nothing could place a hold: c1 and d (and
     the outage email on page a).
3. **cPanel (open word 7).** The Console keeps `CPANEL_HOST` (Site settings). The documented
   `https://my.bluehost.com/hosting/app#/cpanel` link folds into Sign in · Account and isn't shown separately (as drawn).
4. **cPanel's key refused (open word 5; session manager's ruling, 2026-11-21, applying 02 row 129).** A row of its own:
   "cPanel's key refused: mailboxes and cron jobs wait". On our system, Fix it opens **Its key** with Replace ready (the
   key lives in `.env`). The At Bluehost Keys slot stays "No API keys page registered for Bluehost yet", with Publish
   from your Vault, and never borrows another slot. Jobs already on their cron lines keep running (14.4). Applied: c3.
5. **HEALTH_TOKEN and the heartbeat (open word 6; session manager's ruling).** The outside heartbeat loads a **public
   page with no token**, so it measures "pages load". `HEALTH_TOKEN` stays the health report's key on GitHub's card
   (§12). *inf:* whether today's `/api/health.php` needs the token is a **step-4 check against the live file**.
   Applied: pages a, c1 and d, and the sensor row.
6. **The documented cPanel link (open word 7; session manager's ruling, from Jason's answer).** Not a second page: Sign
   in shows **Account** only (Documented · release #212). The separate link isn't drawn. Its URL,
   `https://my.bluehost.com/hosting/app#/cpanel` (`service-defs.php` 'bluehost'; `Preflight.php:124` uses it for `.env`
   fixes), is recorded here as **folded into Sign in · Account**, so nothing is lost.

### 14.6 Open words for Bluehost (all answered: 1–4 and 7 by Jason, 5–7 ruled by the session manager; see 14.6a)

1. **How the outside sender emails and pushes.** Row 53 says "E/Pu from the heartbeat's own sender", and O3 sets it on
   GitHub every 15–30 min. 02 doesn't say how GitHub sends the email (which mail service, with what key) or the push
   (the push keys, VAPID, live in Bluehost's `.env`). Drawn as *spec*.
2. **Ids from outside.** Who numbers the T- and IR- ids while the site is down? 02 says the incident "is opened outside
   the host and finished when the site is back". The mockup shows T-#### and IR-####.
3. **Pulse while Bluehost is down.** Pulse lives on Bluehost. The mockup shows its row written, already closed, when the
   site is back; 02 doesn't say.
4. **"Down" while something is held.** Rows 53–55 say "Down: the system is still trying". Jason's ruling for GitHub
   (12.6a) puts "Paused by the system (…)" first once a hold is placed. For Bluehost down, the engine can't place holds
   (it's on the host), so "Down" fits; for c2 (endpoints writing listings held) and c4 (renewals wait), confirm whether
   the GitHub ruling applies.
5. **cPanel's key refused** has no row of its own; the mockup uses row 29's shape with row 56's part name.
6. **HEALTH_TOKEN and the heartbeat.** 02 §2.7's page heartbeat reads `/api/health.php`. If that read needs
   `HEALTH_TOKEN`, a missing token would also blind the heartbeat. 02 doesn't say whether the heartbeat's page check uses it.
7. **Two cPanel links.** `service-defs.php` documents `https://my.bluehost.com/hosting/app#/cpanel`; the rule draws the
   Console from `CPANEL_HOST` (Site settings). Is the documented link kept somewhere (e.g. as Account's second page), or dropped?
