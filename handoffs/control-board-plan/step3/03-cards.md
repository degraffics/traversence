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
   - **Its recipient** (the session manager, 2026-11-21, §20.6): the address set as a GitHub secret named `ALARM_TO`,
     never hard-coded; unset, the heartbeat's run fails visibly on GitHub. Pages a, c1 and d draw it as "<the address set
     as ALARM_TO>".
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

---

## 15. Anthropic's card (mockups `anthropic-*.html`)

Built like the hosts' cards (`mockups/src/anthropic.py`). Basis marks as in 02: **obs** (the live reading or the live
code as it stands), **sim**, **inf** (worked out from code or decisions, not run), **des** (designed). Real values come from
the live code at mirror `aeb5f31` and the live alarms of 10:19 UTC (3:19 AM MST). No `.env` was opened and no call was
made to Anthropic.

### 15.1 The manifest

| Field | Value | Basis |
|---|---|---|
| id · name | `anthropic` · Anthropic (Claude) | obs (`service-defs.php`) |
| role | **Service**: it does a platform job (writing and reading for us) | des (02 §2.1) |
| criticality | **None**: `ControlRegister::CRITICAL` has no Anthropic entry; no duty (page, search, payments, database, procedures) stops without it | obs |
| purpose | "AI Assist, Find it, writing guides and journeys, reading business cards" | obs |
| plan | "Per word read and written (tokens); each answer's cost is worked out as it comes back. The site stops all AI at its monthly cap (AI use has each feature's own limit)." | obs |
| used by | guides (W4 research briefs, drafts, names, fixes, Check my facts), guides on demand (W5), heritage and topics (W6), journeys' names and Find it (W8), place descriptions (W9), live events (W10), Write it for me (catalog); six more off by default (W1, W3's second tier, the vector index). From `AiClient::FEATURES` with `CrawlService::FEATURE_WF` | obs (code defaults); the live per-feature settings: not read yet |
| governing decisions | 0131 (place guides), 0147 §5 (half the day's allowance on demand; the rest queued), 0160 §5 (the always-on R&D features inside the cap; every call counted) | obs |
| caps | `ai_month_usd` (the AI monthly cap; default $40, decisions/0163 §7; the live reading shows $40.00) · `guide_ondemand_share` (50% by default) · `ai_free_asks` · `ai_cover` · `po_claude_prose` | obs (`Thresholds.php`, `service-defs.php`) |
| the monthly cap | `AiClient::capUsd()`: the setting `ai_month_usd`; with no database, `.env`'s retired `AI_MONTHLY_CAP_USD`, else $25 (brought to $40 in step 4: Jason, 2026-11-21). `allow()` refuses every feature once the month's spend reaches it: "The month's AI cap ($…) is reached: it starts again on the 1st." | obs (code) |
| the 50% on-demand rule | the day's allowance = the cap ÷ the days in the month; Guides on demand may spend up to `guide_ondemand_share` of it (`OnDemand::allowance()`); a build that would pass it **queues for tomorrow**, most-searched first, never stopping part way; the place still gets its free work at once (0147 §5) | obs (code, decision) |
| every call counted | each call's cost is kept in `ai_jobs.cost_micros`, including a call that failed after a paid answer ("Paid, then failed: …", `AiClient.php:364`; 0160 §5, D16) | obs (code) |
| required keys | `ANTHROPIC_API_KEY` (`.env`) | obs |
| its answers | 401/403 → "Claude refused the key: check ANTHROPIC_API_KEY." · 400 "credit" → "The Claude account is out of credit…" · 429, 529, 5xx → "Claude is busy; trying again shortly." (`AiClient.php:383–386`) | obs (code) |
| portal_links | Billing · Usage https://console.anthropic.com/usage and Billing · Limits https://console.anthropic.com/settings/limits · Keys https://console.anthropic.com/settings/keys · Console https://console.anthropic.com/ · Sign in: not registered. All documented ones "Documented · release #212" (#212 carried `service-defs.php`; the mirror's earliest copy has all four links). Limits is a second billing page (04 §5's approved ruling). `portal_domains`: claude.com alongside anthropic.com (§7 Q7, approved) | obs; des (slot rules) |

### 15.2 The four zones

| Zone | Anthropic |
|---|---|
| 1 Identity and role | **Anthropic (Claude)** · Service · "Not critical: no duty stops without it" · state · "Stage 5 of 8 · Operational · Next allowed: Check now · Pause · Raise or lower our cap · Take out · Deactivate" · setup capture notice · spend chip "Claude (AI): $27.05 of $40.00 (68%) (the live reading) · Every call counted, failed calls included · Billing · Usage ↗" (BL: round(spend ÷ cap × 100)) · its radius fold · Setup capture fold |
| 2 What uses it | one row per workflow, listing each feature with its default model and runs a day, and those off by default marked; then its rules: the monthly cap, the on-demand half with the rest queued, each feature's own runs and budget, every call counted |
| 3 Sensors | the month's spend against the cap · today's on-demand spend against half the day's allowance ($40.00 ÷ 30 = $1.33; half $0.67: inf) · its key (one authenticated call, 02 §2.7) · its answers (refused, out of credit, busy) |
| 4 Actions | **On our system:** Check now · Pause our calls (automatic calls only; ruling 3) · Raise the cap · Lower the cap · Take out; on a trip Check and start again, Acknowledge; on its key Replace key. **At Anthropic (Claude):** Billing (Usage, and Limits) · Keys · Console · Sign in (not registered, Publish from your Vault) |

### 15.3 Its Incident Radius

| Fault | Held | On a backup | Unaffected | Basis |
|---|---|---|---|---|
| Its key refused, or out of credit (rows 29, 38) | every call with `ANTHROPIC_API_KEY`: all Claude features | none (Gemini reads pages first already; nothing backs up Claude's writing) | pages, search, sign-in, payments; the crawler; journeys built by rules; guides already written | des |
| Busy (429, 529, 5xx; row 27, 24) | nothing: a rate wait or the system's tries | — | everything | des |
| The monthly cap reached (row 40) | every Claude call, a person's press included (Jason, 2026-11-21); the owner's Raise the cap (logged) is the way past it | — | the queue is kept for the 1st; pages, search, the crawler | des, matching today's code ( refuses a person's asks: obs) |
| Today's on-demand half used (0147 §5) | new on-demand guide builds, until tomorrow (queued, most-searched first) | — | every other feature (the other half and their own budgets); a build already started; the place's free work | obs (code, decision) |
| A workflow's share used (`shareLeft`) | that workflow's features until the 1st | — | the other workflows' features | obs (code) |

### 15.4 The states (mockups)

| Mockup | Row (02) | New words | Today's words, beside them | Fix it |
|---|---|---|---|---|
| a Working | — | "On · Working"; spend illustrative ($12.40 of $40.00, 31%) since today's real reading is past 50% | — | — |
| b Near the cap (real) | 39, BL | title and body "Claude (AI): $27.05 of $40.00 (68%)", "past its 50% warning" | title "Anthropic (Claude) at 50%", reading "67%", means "Claude (AI): $27.05 of $40.00 (67%)" (obs, the live alarm since Nov 19, 12:20 AM MST; `Operations.php` L605, L614) | Billing (Usage; Limits), then Check now |
| c At the cap: today's on-demand half | 0147 §5; a **Notice** (02 §3c; ruling 1) | "New builds wait until midnight (the on-demand half is used); the queue carries on". No message; it lifts by itself at midnight | the place's page: "Claude's write-up is queued for tomorrow" (0147) | none (nothing is wrong); our share or cap, on our system |
| c At the cap: the monthly cap | 40, 41, 109 | "Claude (AI): $40.00 of $40.00 (100%) · Paused by the system until our cap is raised or the month turns." | code: "The month's AI cap ($40.00) is reached: it starts again on the 1st." (obs, `AiClient::allow()`), refusing a person's asks too, which is now the rule (Jason, 2026-11-21) | Raise the cap (the owner's, logged: the one way past it); Billing shows the account's own limit and doesn't raise ours |
| d A refused key | 29, 106, 129 | "Claude (AI) refused our key · Paused by the system: calls with ANTHROPIC_API_KEY wait for a new key." | code: "Claude refused the key: check ANTHROPIC_API_KEY." (obs); board: "api.anthropic.com: Our key was refused (the site, on Bluehost)" with "Nothing could be paused from here (it only trips)" (sim, g4 row 9a) | **Keys** (registered, Documented · release #212), then Replace key and its check |

Out of credit (row 38) routes to Billing; it isn't drawn as its own page.

**A workflow's share of the cap** (`shareLeft`, 02 row 41a): a **warning on that workflow only**, never on Claude
(ruling 6). Its words: "<workflow> has used its <n>% share of the AI cap ($<x> this month, with last month's unused
part): its Claude features wait until the 1st." Today's words: "… has used its …% share of the AI cap (…): more on the
1st, or raise its share on the Workflow board." (obs, `AiClient::shareLeft()`). Not drawn as its own page.

**Journey writing** shows on the card as *coming*: 0147 §5 decided it, and it isn't built (ruling 5).

### 15.5 Setup capture (§13.x)

Notice in zone 1: **"Setup capture: Sign in not recorded"**. Billing, Keys and Console are registered.

Where its keys live, names only:
- `ANTHROPIC_API_KEY`: `.env`.
- `ANTHROPIC_BASE_URL`: `.env`, optional; unset, it's api.anthropic.com.
- `AI_MONTHLY_CAP_USD`: `.env`, retired. It's read only when the database can't be; the cap now lives in `ai_month_usd`.

Its settings, not keys: `ai_month_usd`, `guide_ondemand_share`, `ai_free_asks`, `ai_cover`, `po_claude_prose`.

### 15.6 Open words for Anthropic, and the session manager's rulings (2026-11-21)

**Ruled by the session manager, applied:**
1. **The on-demand half used** is a **Notice**: "New builds wait until midnight (the on-demand half is used); the queue
   carries on". It sends no message and lifts by itself (page c, first card).
3. **"Pause our calls" for a Service** pauses **automatic calls only**; a person's press is still allowed while under
   the monthly cap (every Anthropic card's buttons say so).
5. **Journey writing** stays *coming* on the card (0147 decided it; not built). No note.
6. **A workflow's share of the cap** (`shareLeft`) is a warning on that workflow only: 02 row 41a (§15.4).
- **The BL rule:** the percentage shown matches the reading everywhere: 68%. The only "67%" left on a page is in page
  b's Details, quoted as today's words being replaced.

**Answered by Jason (2026-11-21, Approve, reviewed by the session manager):**
2. **At the monthly cap nothing passes, a person's press included.** The owner gets **Raise the cap** (logged) on the
   card, and that's the way past it. 02 row 40 now says so; mockup c's monthly-cap frame is redrawn to match.
4. **The cap is $40** (decisions/0163 §7). `AiClient::capUsd()`'s $25 fallback is brought to $40 in step 4. The dated
   note on decisions/0160 §5 is the coordinator's.

---

## 16. Google's cards: Google Places, Google Cloud and Google AI Studio (Gemini) (mockups `google-*.html`)

**Jason (2026-11-21): Places is split into its own module.** The live code registers Google as two modules in
`service-defs.php`: 'google' (Places, Document AI, Continue with Google) and 'gemini'. The board draws three:
- **Google Places**, a **Source**: it supplies data, its fault reroutes on the source map, and it has its own key
  `GOOGLE_PLACES_KEY` and its own radius.
- **Google Cloud**, a **Service**: Document AI and Continue with Google.
- **Google AI Studio (Gemini)**, a **Service**.

Places and Cloud link **Google's one billing account**. **This changes the live code's module boundary in step 4:** a new
'places' entry in `service-defs.php` takes Places' key, caps, ledger and hosts out of 'google'. Basis marks as in 02
(obs, sim, inf, des). Real values come from the live code at mirror `aeb5f31` and the live alarms of 10:19 UTC (3:19 AM
MST). No `.env` was opened and no call was made to Google.

### 16.1 The manifests

| Field | Google Places | Google Cloud | Google AI Studio (Gemini) | Basis |
|---|---|---|---|---|
| role | **Source** (Jason) | **Service** | **Service** (02 §2.1) | des |
| criticality | none | none: **sign-in's critical duty is email and password**; Continue with Google is one of four providers | none | obs; ruling 5 |
| stage today | Operational (its reading is live) | Operational | **Stage 2, Integration**, until its key is checked and its pre-flight passes; added, off, "Not called yet" | obs; ruling 3 |
| what uses it | **Still open? (W2)**: our copies first, then Places; **Confirm a business (W1, W2)**: a paid backup only (`po_places_confirm`, off) | **Reading menus**: Google, then Amazon (Textract), then Claude; **Sign in with Google, Microsoft, Apple or Facebook**; **Videos in journeys** (after Vimeo) | **Page prose into facts (W1–W3, W10, W11)**: Gemini, then Claude only under a director's override (`po_claude_prose`, off); else "waiting to be read; nothing lost". **Semantic search (W3)** (the vector index is off) | obs (`Preflight::NEEDS`, `AiClient::ROUTES`) |
| plan and spend | about $0.032 a check (`GOOGLE_PLACES_USD_PER_CALL`), ledger `google_places`; the live reading 544 of 1,000 checks (54%) | Document AI per page (use not read yet); sign-in free | Free ($0) on its free tier; every call counted, failed calls included, at $0 | obs |
| caps | `worker_places_per_run` 5 · `places_monthly` 1,000 · `places_stop_pct` 90% · `paid_spread` · `po_places_confirm` | none metered | `gemini_daily` 1,000 · `gemini_per_minute` 15 · `gemini_reserve_pct` 20% | obs (defaults; live settings not read yet) |
| budget alert | **"Google's own budget alert: $10 (set at Google, not read here)"**, words only, in zone 1 beside the Billing link (ruling 6) | the same words (the same billing account) | — | obs (plan text); ruling 6 |
| keys | `GOOGLE_PLACES_KEY` on Railway, reported by name; "not checked yet" until the worker reports | `GOOGLE_DOCAI_KEY_FILE`, `GOOGLE_DOCAI_PROCESSOR`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` in `.env` | `GEMINI_API_KEY` in `.env`, **"not checked yet"**: whether it's set isn't known, and `.env` is never opened | obs; ruling 3 |
| the privacy rule | — | — | **Nothing private to Gemini's free tier** (CLAUDE.md; 0164 R5, §0.8): `Gemini::refused()` sends only public-page features (Read a public page into facts, Read a page through Tavily, Source terms, Read a scanned PDF, Live events, the vector index); `Gemini::safe()` cuts R6's private details from each page before sending | obs |
| portal_links | Billing · Billing, Keys · Credentials, Console · Console: each **Documented · release #212**; Sign in not registered (the same pages as Cloud's: one Google account) | the same four | Keys · Keys and Console · AI Studio, each #212; Billing not registered (the free tier has none linked); Sign in not registered. Rate limits: documentation, zone 2 | obs; des |

### 16.2 The four zones

| Zone | Google Places | Google Cloud | Gemini |
|---|---|---|---|
| 1 | Source · not critical · state · Stage 5 · setup notice · chip "Google Places check: 544 of 1,000 checks (54%) · about $0.032 a check · Billing ↗ · Google's own budget alert: $10 (set at Google, not read here)" · radius | Service · "Not critical: sign-in's critical duty is email and password" · Stage 5 · setup notice · chip "Document AI: per page · use not read yet · Sign-in: free · Billing ↗ · Google's own budget alert: $10 (set at Google, not read here)" · radius | Service · not critical · "Off · added, not called yet" · "Stage 2 of 8 · Integration (pre-flight) · Next allowed: Run pre-flight · Edit · Activate (offered once its key is checked and its pre-flight passes)" · setup notice · chip "Free ($0) · Nothing private goes to it · Calls: n of 1,000 today · 15 a minute · 20% held for visitors waiting" · radius |
| 2 | Places check · Still open? (W2) · Confirm a business (paid backup, off) | Reading menus (Document AI ↗) · Continue with Google · Videos in journeys | Page prose into facts · Semantic search · What may go to it (the guard) · Its limits (Rate limits ↗; our limits stopping background work are a Notice) |
| 3 | the month's checks · its key on Railway | Document AI and sign-in keys set | calls today and this minute · removed before sending · refused by the guard · its key (not checked yet) |
| 4 | Check now · Pause our calls (automatic only) · Raise or lower the cap · its four slots | Check now · Take out · its four slots | Run pre-flight · Activate (greyed) · its four slots |

### 16.3 Incident Radius

| Fault | Held | On a backup | Unaffected | Basis |
|---|---|---|---|---|
| Places' key refused (rows 29, 31) | Places checks; Still open? only where our copies can't say (Places is its last source) | Still open?: our copies, its first source | Confirm a business (paid backup off); Google Cloud and Gemini (their own keys); everything else | des |
| Places at its cap (row 40) | the crawler's Places checks until the month turns | as above | everything else | des |
| Google Cloud down | Document AI, Continue with Google | menus: Amazon, then Claude; sign-in: email and password (the critical duty), Microsoft, Apple, Facebook | pages, search, payments, Places, Gemini | des |
| Gemini's day used at Google (row 36) | Gemini's calls until 00:00 Pacific: pages wait "to be read; nothing lost" | none (no fallback to Claude, Q13, unless a director's override) | every Claude feature; Places; Cloud | des |
| Gemini's minute (row 27); our limits stopping background work | nothing: a rate wait; a Notice for our own limits | — | everything | des; ruling 4 |
| The privacy guard refuses a feature (row 37a) | nothing: it stays on Claude | — | everything | obs (code) |

### 16.4 The states (mockups)

| Mockup | Row (02) | New words | Today's words, beside them | Fix it |
|---|---|---|---|---|
| a Working | — | Google Places "On · Working", count illustrative (312 of 1,000, 31%); Google Cloud "On · Working"; Gemini as it is today, stage 2, its key not checked yet | — | — |
| b Places near the cap (**real**), on Places' card | 39, BL | "Google Places check: 544 of 1,000 checks (54%)" · "past its 50% warning" | title "Google Cloud at 50%", reading "54%" (obs, since Nov 19, 3:21 PM MST) | Billing (Google's one account), then Check now |
| b Gemini's day at Google | 36, 37 | "Google AI Studio (Gemini) used its day at the provider · Paused by the system until 00:00 Pacific; its check runs then." | code: "Gemini's allowance answered "too many": the work waits." (obs); board: "Over its quota or plan", held for good (sim, g2 row 6) | Billing: "No billing page registered for Google AI Studio (Gemini) yet", never another slot |
| c Places' key refused, on Places' card | 29, 31, 106, 129 | "Google Places refused our key · Paused by the system: calls with GOOGLE_PLACES_KEY wait for a new key." Still open? "On a backup: our copies only" | "places.googleapis.com: Our key was refused (the crawler, on Railway)" (sim, g2 row 2) | Keys · Credentials (registered), then Replace key on Railway |
| d The privacy guard | 37a | "Kept on Claude: this carries a person's own words (nothing private to Gemini's free tier)", a Notice on Gemini's card only, no message | code: "Gemini's free tier lets Google use what it's sent, …, so it stays on Claude." (obs, `Gemini::refused()`) | none |

Gemini's faults (b's second card, d) are drawn as they'd read once it's active, marked as a scenario.

### 16.5 Setup capture (§13.x)

**Google Places.** Notice: "Setup capture: Sign in not recorded". Keys, names only:
- `GOOGLE_PLACES_KEY`: Railway.
- `GOOGLE_PLACES_MONTHLY`: Railway, retired; now `places_monthly`.
- `GOOGLE_PLACES_USD_PER_CALL`: `.env`.
- `PLACES_STOP_PCT`: `.env`, retired; now `places_stop_pct`.

**Google Cloud.** Notice: "Setup capture: Sign in not recorded". Keys: `GOOGLE_DOCAI_KEY_FILE`, `GOOGLE_DOCAI_PROCESSOR`,
`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, all in `.env`.

**Gemini.** Notice: "Setup capture: Sign in not recorded · Billing: why not recorded". Key: `GEMINI_API_KEY`, in
`.env`; whether it's set isn't known.

### 16.6 Open words for Google: answered (2026-11-21)

1. **Places' role** (Jason): split into its own module, a Source; Google Cloud is a Service; one billing account links
   both. The live code's module boundary changes in step 4.
2. **The privacy guard** (the session manager): a Notice on Gemini's card only, no message: 02 row 37a.
3. **Gemini's stage** (the session manager): stage 2 until its key is checked and its pre-flight passes; its key reads
   "not checked yet".
4. **Our limits stopping Gemini's background work** (the 80% reserve, the day's pace) (the session manager): a Notice.
5. **Continue with Google** (the session manager): not critical; sign-in's critical duty is email and password.
6. **Google's $10 budget alert** (the session manager): words only in zone 1, beside the Billing link: "Google's own
   budget alert: $10 (set at Google, not read here)".

No open words remain for Google.

---

## 17. Tavily's card (mockups `tavily-*.html`)

Built like the others (`mockups/src/tavily.py`). Basis marks as in 02. Real values come from the live code at mirror
`aeb5f31` and the worker's code (`workers/crawler/crawler.py`, 2.16.1). No `.env` was opened and no call was made to Tavily.

### 17.1 The manifest

| Field | Value | Basis |
|---|---|---|
| id · name | `tavily` · Tavily | obs |
| role | **Source**: it supplies search results for the crawler and research | des (02 §2.1 lists Tavily as a Source) |
| criticality | none (not in `ControlRegister::CRITICAL`) | obs |
| purpose | "The crawler's web search." | obs (`service-defs.php`) |
| plan | "A monthly allowance of searches on its plan; the site stops at its stop line and paces it. Keep PAYGO and auto-upgrade off in its dashboard." The worker's notes say "free plan: 1,000 searches a month" | obs |
| the month's use | **26 searches, 20.8¢** this month: from the approved report (2026-11-21). The ledger (`tavily`, `tavily_extract`) lives in the database, not the mirror, so it **can't be re-read here**. It matches the code's price: 26 × `TAVILY_USD_PER_CREDIT` $0.008 = 20.8¢. Failed calls are counted | obs (report); inf (the check) |
| caps and pace | `worker_searches_per_run` 10 · `websearch_monthly` (**0 by default: "0 stops web search"**; the live value: not read yet) · `websearch_stop_pct` 90% · `paid_spread` on (each run uses only the day's share so far) · the worker waits about one second between searches | obs (defaults) |
| what uses it | **Web search (W8, W10–W12)**: Tavily, then Brave. **A missing phone or website (W2)**: our open-data copies, then Tavily, then Brave. **Confirm a business (W1, W2)**: our copies, CMS, the Arizona Corporation Commission, then Tavily, then Brave, then the paid backup. **Read a page through Tavily** (Tavily Extract, second tier, off by default) | obs (`Preflight::NEEDS`, `AiClient::FEATURES`) |
| reroute | the worker: "Tavily, then Brave when Tavily fails or is held (decisions/0163's source map: free before paid, the first that works wins, each switch logged)". Today the switch counts (`search_switches`) are dropped by the site (g2 row 27) | obs |
| key | `TAVILY_API_KEY` on Railway, reported by name only (`WORKER_KEYS`); presence only today, so "not checked yet" (Q4, row 30) | obs |
| its answers | 401 refused key · **432 plan limit** · 429 rate · 5xx and timeouts. **Today `ServiceHealth::classify()` returns nothing for a 432** (any 4xx it doesn't name is "not the service's"), so Tavily's plan limit raises no issue; the worker falls to Brave each time | obs (code) |
| portal_links | Console · Dashboard https://app.tavily.com/, **Documented · release #212**; Billing, Keys, Sign in: **not registered** (02 §8; 04 §5), each with Publish from your Vault | obs |

### 17.2 The four zones

| Zone | Tavily |
|---|---|
| 1 | **Tavily** · Source · not critical · state · Stage 5 · setup notice · chip "Tavily, this month: 26 searches · 20.8¢ (the approved report) · of `websearch_monthly`: not read yet · stops at 90% · paced through the day · failed calls counted · Billing: No billing page registered for Tavily yet." · radius |
| 2 | Web search (with what each need does after it) · Read a page through Tavily (off by default) |
| 3 | the month's searches · switches to Brave (logged) · its key on Railway (not checked yet) · its answers |
| 4 | On our system: Check now · Pause our calls (automatic calls only) · Raise or lower the cap. At Tavily: Billing (not registered) · Keys (not registered) · Console · Dashboard · Sign in (not registered) |

### 17.3 Incident Radius (a Source: reroutes on the source map)

| Fault | Held | On a backup | Unaffected | Basis |
|---|---|---|---|---|
| Tavily can't be called (key, plan limit, down) | Tavily's calls; and **W3's task Read a page through Tavily** (when it's on), its pages waiting "to be read": a real hold on that task only (ruling 5). Nothing else is held | **Brave Search** for Web search (W8, W10–W12), A missing phone or website (W2), Confirm a business (W1, W2), while Brave works | the crawler's other work; every other source; pages, search, sign-in, payments | obs (worker), des |
| Both Tavily and Brave paused | the tasks that need web search: Web search waits ("queued"); A missing phone or website waits; Confirm a business goes to the person queue | — | everything else | des (row 45) |
| Our cap reached (`websearch_stop_pct`) | Tavily's automatic calls; at the cap nothing passes, a person's press included (row 40) | Brave Search, within its own allowance | everything else | des |

### 17.4 The states (mockups)

| Mockup | Row (02) | New words | Today's words, beside them | Fix it |
|---|---|---|---|---|
| a Its two states (**needs live data**) | — | "On · Working" with a monthly allowance; and, with `websearch_monthly` = 0 (its default), "Off on purpose: websearch_monthly is 0": web search stopped, Brave too (they share the allowance), nothing a fault (ruling 3) | — | — |
| b Its plan's limit (432) | 36, 37, 43, 44 | a quota (ruling 2): "Tavily used its month at the provider · Paused by the system until the 1st; its check runs then." Web search "On a backup: Brave Search" | none: a 432 is classified as nothing (obs); the crawler logs "search: brave instead (tavily failed: …)" (obs) | **Billing: "No billing page registered for Tavily yet"**, never the Dashboard |
| c A refused key | 29, 31, 43, 106, 129 | "Tavily refused our key · Paused by the system: calls with TAVILY_API_KEY wait for a new key." | "api.tavily.com: Our key was refused (the crawler, on Railway)"; its check confirms only presence (sim, g2 row 2) | **Keys: "No API keys page registered for Tavily yet"**; the key lives on Railway |
| d A 5xx or timeout | 24–26, 43, 83, 130 | "Tavily isn't answering · Trying again (2 of 3): it answered 502 Bad Gateway at <time>. Next check <time>." Each search goes to Brave at once | "api.tavily.com: Its server failed (the crawler, on Railway)", counting the reports' failures (sim, g2 rows 16–18); the switch isn't logged (g2 row 27) | their trouble: no Fix it; Console · Dashboard, then Check now (row 130) |

### 17.5 Setup capture (§13.x)

Notice: **"Setup capture: Sign in not recorded · Billing, Keys: why not recorded"**.

Keys, names only:
- `TAVILY_API_KEY`: Railway, reported by name only.
- `TAVILY_USD_PER_CREDIT`: `.env`.
- `WEBSEARCH_USD_PER_SEARCH`: `.env`, the old name.
- `SEARCH_MONTHLY`: Railway, retired; now `websearch_monthly`.
- `WEBSEARCH_STOP_PCT`: `.env`, retired; now `websearch_stop_pct`.

Settings, not keys: `worker_searches_per_run`, `websearch_monthly`, `websearch_stop_pct`, `paid_spread`.

### 17.6 Open words for Tavily: answered by the session manager (2026-11-21)

1. **The month's figure:** a **step-4 spec item**: a read-only ledger export, written like `alarms.json`, holding
   **counts and money only, per service and per month**: no query text, no person, no key. The card reads its figure
   from that export; until then it carries the approved report's 26 searches and 20.8¢, labelled.
2. **432 is a quota** (row 36): a wait until the month resets, said on the card (page b).
   **Finding (step-2 style):** `ServiceHealth::classify()` (`ServiceHealth.php:183–200`) returns `''` for any 4xx it
   doesn't name, so Tavily's 432 is read as "not the service's": no issue, no alarm, nothing on the board. The worker
   falls to Brave on every search, and the plan limit is invisible. Step 4's engine classifies 432 as a quota (P2).
3. **`websearch_monthly` = 0 live?** Both states are drawn on page a (On, and Off on purpose), marked "needs live data".
   It's with Jason through the session manager's asks.
4. **Fallback searches from Tavily** are their own line in Brave's spend (§18).
5. **Tavily Extract:** a real hold on W3's task Read a page through Tavily only (row 39's shape), its pages waiting "to
   be read". Nothing else is held (pages b and c).

---

## 18. Brave Search's card (mockups `brave-*.html`)

Built like the others (`mockups/src/brave.py`). Basis marks as in 02. Real values come from the live code at mirror
`aeb5f31` and the worker (`workers/crawler/crawler.py`, 2.16.1). No `.env` was opened and no call was made to Brave or
Tavily.

### 18.1 The manifest

| Field | Value | Basis |
|---|---|---|
| id · name | `brave` · Brave Search | obs |
| role | **Source**, and **Tavily's backup**: the second source of each web search need | obs (needs); des |
| criticality | none (not in `ControlRegister::CRITICAL`) | obs |
| purpose | **"Web search when Tavily's key is missing, and the backup on any Tavily failure or hold."** (ruling 1; the live definition says only "when it has a Brave key instead of Tavily's", and the worker does both) | obs; ruling 1 |
| plan and price | "Its free plan or paid plan, per search; the site counts each one (about $0.005) inside the same web search allowance as Tavily (SEARCH_MONTHLY)." Price `BRAVE_USD_PER_SEARCH` **$0.005** (`CrawlService::PRICES`) | obs |
| caps and pace | **none of its own**: it shares Tavily's allowance (`websearch_monthly`, `websearch_stop_pct` 90%, `worker_searches_per_run` 10, `paid_spread`). The worker counts **one search per query, whichever engine answers**, before trying them, and waits about one second between searches. So with `websearch_monthly` = 0, Brave is off too | obs (code); inf |
| its key | `BRAVE_API_KEY` on **Railway** (the crawler service), reported by name only (`WORKER_KEYS`); "not checked yet" until the worker's key test exists (Q4, row 30) | obs |
| spend | the cap counts searches, as the code does, with a **dollars line beside it** (ruling 2); the ledger's `brave`, in **two lines**: its own searches, and **Tavily's fallbacks** (the worker's `search_switches`, today dropped by the site, g2 row 27) (ruling 4). Not readable here: the ledger is in the database; the step-4 export (§17.6 item 1) carries it | des; obs |
| what uses it | Web search (W8, W10–W12), A missing phone or website (W2), Confirm a business (W1, W2), each after Tavily | obs (`Preflight::NEEDS`) |
| its answers | 401 refused key · **429: a rate wait (row 27) unless its body names the plan, then a quota (row 36)** (ruling 3; **inf**: Brave's real wording isn't recorded; step 4 checks it on a real response) · 5xx and timeouts | obs (`classify()`); inf |
| portal_links | Console · API dashboard https://api-dashboard.search.brave.com/, **Documented · release #212**; Billing, Keys, Sign in: **not registered**, each with Publish from your Vault | obs |

### 18.2 The four zones

| Zone | Brave Search |
|---|---|
| 1 | **Brave Search** · Source · not critical · state · Stage 5 · setup notice · chip "Brave Search, this month (about $0.005 a search): its own searches: not read yet · Tavily's fallbacks (their own line): not read yet · one shared web search allowance with Tavily; failed calls counted · Billing: No billing page registered for Brave Search yet." · radius (with what happens if Tavily is paused too) |
| 2 | Web search, as the second source ("standing by: Tavily answers first") |
| 3 | the month's searches (two lines) · its key on Railway (not checked yet) · its answers |
| 4 | On our system: Check now · Pause our calls (automatic calls only). At Brave Search: Billing (not registered) · Keys (not registered) · Console · API dashboard · Sign in (not registered) |

### 18.3 Incident Radius, and when Brave fails too while Tavily is held

| Fault | Held | On a backup | Unaffected | Words | Basis |
|---|---|---|---|---|---|
| Brave's own fault (quota, key, down) while Tavily works | Brave's calls only | none needed | every need: Tavily, the first source, answers | "Nothing waits on it: its need's first choice is working." (row 42) | des |
| **Brave fails too while Tavily is held** | **only the tasks that need web search, in every workflow the needs name** (row 45; P6): Web search (W8, W10–W12) waits ("queued"); A missing phone or website (W2) waits where our open-data copies can't say; Confirm a business (W1, W2) goes to the person queue after our copies, CMS and the Arizona Corporation Commission | none: Brave was the last search source; the paid Confirm backup is off | every workflow's other tasks (they read "Partly working", row 3b roll-up); every other source; pages, search, sign-in, payments | on each held task's row: **"Waiting on Web search: no source works (Tavily paused, Brave Search paused)."** (row 45). A workflow reads "W<n>: every task waits on <part>" only if every task waits (row 46). **No new alarm**: the two trips already told Jason; the need is a roll-up (§2.2) | des |
| Either source's check passes | — | the need answers again; "Back on its first choice: Tavily" when Tavily's does (row 44) | — | | des |

### 18.4 The states (mockups)

| Mockup | Row (02) | New words | Today's words, beside them | Fix it |
|---|---|---|---|---|
| a Working | — | "On · Working · standing by: Tavily answers first"; spend in two lines, not read yet | — | — |
| b Its own quota | 36, 37, 42, 128 | "Brave Search used its month at the provider · Paused by the system until its plan's month turns." "Nothing waits on it: its need's first choice is working." | "…: Over its quota or plan (the crawler, on Railway)" (sim, g2 row 6); its own calls weren't paused (g1 row 19) | Billing: "No billing page registered for Brave Search yet" |
| c A refused key | 29, 31, 42, 106, 129 | "Brave Search refused our key · Paused by the system: calls with BRAVE_API_KEY wait for a new key." | "api.search.brave.com: Our key was refused (the crawler, on Railway)" (sim, g2 row 2) | Keys: "No API keys page registered for Brave Search yet"; the key lives on Railway |
| d Both sources down (both Fix its side by side, Tavily first: "Either fix lifts the hold", ruling 4) | 45, 46 | "Web search: no source works (Tavily paused, Brave Search paused)" · "Paused by the system, waiting on its sources" · on each held task: "Waiting on Web search: no source works (Tavily paused, Brave Search paused)." | "Paused by the system: <task> (<first workflow only>)": the first workflow named, whole (sim, g1 row 18) | the first source first: Tavily's key; then Brave's billing (not registered) |

### 18.5 Setup capture (§13.x)

Notice: **"Setup capture: Sign in not recorded · Billing, Keys: why not recorded"**.

Keys, names only:
- `BRAVE_API_KEY`: Railway, the crawler service.
- `BRAVE_USD_PER_SEARCH`: `.env`.

It has no settings of its own: it shares Tavily's web search allowance.

### 18.6 Open words for Brave Search: answered by the session manager (2026-11-21)

1. **The purpose line** says both: "Web search when Tavily's key is missing, and the backup on any Tavily failure or
   hold." (zone 2 and §18.1).
2. **One allowance, two prices:** keep the count, as the code does, with a dollars line beside it (the spend chip).
   **The cap's unit (searches or dollars) is a possible question for Jason, not asked now.**
3. **A 429** is a rate wait (row 27) unless its body names the plan, when it's a quota (row 36). Marked **inf**; step 4
   checks it on a real response. (The ruling cited row 37; row 37 is the quota's reset, and the rate wait is row 27.)
4. **Fix it with two trips** (page d): both sit side by side, Tavily first, with the words "Either fix lifts the hold".

---

## 19. QuickBooks' card (mockups `quickbooks-*.html`)

Built like the others (`mockups/src/quickbooks.py`). Basis marks as in 02. Real values come from the live code at mirror
`aeb5f31`, its tests (`tools/control-board-tests/sensor.php`, `r3f.php`), and the live alarms of 10:19 UTC. No `.env` was
opened and no call was made to QuickBooks.

### 19.1 The manifest

| Field | Value | Basis |
|---|---|---|
| id · name | `quickbooks` · QuickBooks (Intuit) | obs |
| role | **Service**: it does a platform job (our orders' invoices, card payments, refunds and disputes) | des (02 §2.1 lists Payments as a Service) |
| criticality | **Critical: payments.** `ControlRegister::CRITICAL`: `svc:quickbooks` → payments, `job:billing_tick` → payments (plans and renewals), `site:payments` → payments (checkout's and QuickBooks' paths, and QuickBooks' token). Sign-in's duty is separate (`site:signin` → procedures) | obs |
| purpose · plan | "Invoices, card payments, refunds and disputes for our own orders." "Our subscription is paid to Intuit. Each card payment carries a processing fee, ours on our own orders; it shows in QuickBooks (not recorded here yet)." | obs |
| how it works | a checkout becomes a QuickBooks invoice; its Automated Sales Tax adds the tax; the buyer pays on QuickBooks' own payment page; no card number touches our server. A create runs **once per request id**, so a retry or a double click never makes a second invoice (0098) | obs (`QuickBooksPay.php` header, `call()`) |
| **the no-real-money guard** | `guardReal()` before every charge, invoice and payment on an order: a test order (made in the sandbox, a "TEST · …" copy, or by a test account) never goes through live QuickBooks. `testHere()` runs staging and test accounts in the sandbox. Refunds and credits only send money back. CLAUDE.md "No test moves real money"; decisions/0145 | obs |
| staging | `guardStaging()`: payments refuse to start on staging while a live payment key is in its `.env` (`StagingGate::LIVE_PAY_KEYS`: `QB_CLIENT_ID`, `QB_CLIENT_SECRET`, and `QB_ENV=production`), decisions/0165 §1; the r3f tests read the names the code reads | obs |
| the OAuth token | an access token refreshed when it runs out (QuickBooks hands a new refresh token each time); a 401 before its time gets a fresh token once; a refused refresh (`invalid_grant`) marks the connection "needs reconnecting" and only the owner's **Reconnect** (E-Commerce → Payments) restores it. The tokens are kept sealed in the database (`qb_connection`), never in `.env` | obs (`access()`, `markReconnect()`, `call()`) |
| retries | each call retries once after 2 seconds on 429 or 5xx (`httpRetry()`) | obs |
| the sensor | `ControlSensor::probePayments()`: the token read locally every 5 minutes; a real check (the company's info) at most once an hour, held on staging. "held" (needs reconnecting) is a trip at once | obs |
| webhooks | `/api/qb-webhook.php`, signed with the verifier token (`intuit-signature`), refused unsigned (401). Payments, invoice changes, refunds, credit memos. **A missed webhook only delays, never loses, an order**: the buyer's page asks QuickBooks, and "Check QuickBooks" on Orders catches it | obs |
| backup | the need "Our orders' payments": QuickBooks, else **"wire or check"**, until Stripe exists as its own stage-1 module (ruling 4) | obs (`Preflight::NEEDS`); ruling 4 |
| portal_links | **Console · QuickBooks** https://qbo.intuit.com/ and **Keys · Developer app** https://developer.intuit.com/app/developer/dashboard (04 §5 rulings), each **Documented · release #212**; Billing and Sign in: not registered | obs |

### 19.2 The four zones

| Zone | QuickBooks |
|---|---|
| 1 | **QuickBooks (Intuit)** · Service · **Critical: payments** · state · Stage 5 · setup notice · chip "Our subscription is paid to Intuit; each card payment carries a processing fee · **fees not read yet** · Console · QuickBooks ↗ (ruling 3) · Billing: No billing page registered for QuickBooks (Intuit) yet." · **the guard: "No test moves real money"** with `guardReal()` and `testHere()` · radius |
| 2 | Our orders' payments · Plans and renewals (`billing_tick`) · Its webhooks · The mode (production, sandbox or mock) |
| 3 | Its token (local every 5 min, a real check hourly; **a warning 14 days before the refresh token runs out**, ruling 5; Intuit's lifetime inf, a step-4 check) · Checkout's paths (site:payments) · Webhooks (signed or refused) · Staging's keys |
| 4 | On our system: Check now · **Reconnect** (the owner) · Take out. At QuickBooks (Intuit): Billing (not registered) · Keys · Developer app · Console · QuickBooks · Sign in (not registered) |

### 19.3 Incident Radius: when payments can't reach QuickBooks

| Held | On a backup | Unaffected |
|---|---|---|
| our orders' payments: new invoices and pay links, card charges (a waiting charge goes once QuickBooks answers, never twice), recorded payments; checkout for our own orders shows "opens soon"; renewals (`billing_tick`) | "wire or check" for our orders, until Stripe exists as its own stage-1 module | **Marketplace checkout** (businesses' own Stripe and Square); orders already paid and codes already issued; pages, search, sign-in; everything else |

### 19.4 The states (mockups)

| Mockup | Row (02) | New words | Today's words, beside them | Fix it |
|---|---|---|---|---|
| a Working | — | "On · Working"; the guard on the card. No QuickBooks or payments alarm in the 10:19 UTC snapshot (obs) | — | — |
| b The token refused (`invalid_grant`) | 70, 106, 129 | "Checkout can't take cards: QuickBooks needs reconnecting · Paused by the system." Closes "Fixed by <name>: reconnected QuickBooks. Its check passed: <proof>." | code "QuickBooks needs reconnecting (Admin → E-Commerce → Payments)."; sensor "QuickBooks' connection ran out or was refused: it needs reconnecting." (obs); board "SYSTEM ALARM NOTICE: Checkout and payments: Paused by the system", closing "Recovered: … the system fixed it" after a person reconnected (sim, g3 C12) | **Its key: Reconnect** (the owner); Keys · Developer app if the app's keys changed |
| c QuickBooks 5xx while a charge waits | 24–26, 69, 87, 88 | "Checkout and payments: QuickBooks isn't answering · Paused by the system (our orders' payments): still trying, 2 of 3, next at <time>." (critical: a trip at once while trying; words follow the state, 12.6a) | code "QuickBooks (503): …" after one retry; sensor "QuickBooks didn't answer: …" (obs); board "Tripped at once … still trying" with "…: Paused by the system" though nothing paused (sim, g4 row 4) | their trouble: Console · QuickBooks, then Check and start again |
| d A test order stopped | **70a**, a Notice | "Order #<n> is a test order: it never goes through live QuickBooks. No real money moved." · "Notice · its invoice was stopped before it reached the live gateway." No message | code "Order #… is a test order: it never goes through live QuickBooks, so no real money moves. Switch E-Commerce → Payments to Sandbox to run it through." (obs, `guardReal()`), to the person who pressed | none |

### 19.5 Setup capture (§13.x)

Notice: **"Setup capture: Sign in not recorded · Billing: why not recorded"**.

Keys, names only:
- `QB_ENV`: `.env`.
- `QB_CLIENT_ID`, `QB_CLIENT_SECRET`: `.env`. These are a live payment key, never on staging.
- `QB_SANDBOX_CLIENT_ID`, `QB_SANDBOX_CLIENT_SECRET`: `.env`.
- `QB_WEBHOOK_TOKEN` and its sandbox twin: `.env`.
- `QB_REDIRECT_URI`: `.env`, optional.
- `QB_INCOME_ACCOUNT_ID`: `.env`, optional.
- The connection's access and refresh tokens: sealed in the database (`qb_connection`), never in `.env`.
- `QB_TEST_API` is for tests only and isn't listed.

### 19.6 Open words for QuickBooks: answered by the session manager (2026-11-21)

1. **The test-order guard** is 02 row 70a: a Notice on QuickBooks' card only, with no message, the same shape as 37a (page d).
2. **A payments outage** follows the GitHub rule: words follow the state. Page c reads "Paused by the system (our
   orders' payments): still trying, n of 3", as drawn.
3. **The fees:** the spend chip reads "fees not read yet", with the Console slot. Reading QuickBooks' fees joins the
   step-4 ledger export as a spec item: a read of our own account's data, at no cost. It's in "For Jason's review" at the
   end of this document; not asked now.
4. **The backup** is "wire or check" until Stripe exists as its own stage-1 module.
5. **The token's date:** a warning 14 days before the refresh token runs out, the same threshold as GitHub's. Intuit's
   refresh lifetime is **inf**, with a step-4 check.

---

## 20. The Communications Dispatcher's card (mockups `dispatcher-*.html`)

A Service module, from the report's §5 as corrected: **channels are email, Pulse and push only; no SMS, no webhooks**.
Built from decisions/0172 and 0176, the live code at mirror `aeb5f31` (`Notify`, `Mailer`, `SesMail`, `WebPush`,
`Mailboxes`, `ControlRegister`), step 2's g4 §c, and the tests in `tools/notify-tests/` and `tools/dispatcher-tests/`. No
`.env` was opened and no message was sent.

### 20.1 The manifest

| Field | Value | Basis |
|---|---|---|
| id · name | `dispatcher` · The Communications Dispatcher (`Notify`, with `Thread`, `Mailer`, `SesMail`, `WebPush`) | obs (g4 §c) |
| role | **Service**: it delivers every thread (alarms, uploads, people's messages) | des (02 §7) |
| criticality | **The code and the spec differ.** `ControlRegister::CRITICAL` has **no dispatcher part** (and `processes` has no Notify part); the procedures duty names `svc:aws` (SES, "system email"). 02 §7 and the report call the dispatcher critical (procedures). The card shows the spec, labelled "Critical: procedures (by the spec; not in the register today)" (§20.6) | obs; des |
| channels | **Pulse** (always; `comms_messages`), **email**, **push** (`Notify::CHANNELS`) | obs |
| email | **Bluehost's SMTP first, then Amazon SES "the backup"** (`Mailer`, 0163's note). SES also sends notices and the weekly digest from its personal account, and later bulk mail from its own account (`SesMail`). A bounce or complaint stops all mail to that address (SES's suppression list). So the code doesn't send alarm email through SES first; SES is its backup | obs |
| push | our own VAPID keys (`WebPush`, RFC 8291 and 8292); on by default for Alert and up once a person has a device; **pace**: past 3 pushes in 5 minutes per person the rest fold into one summary; Critical always on its own; one tag per thread, alerting again only on a rise or Critical | obs |
| quiet hours | hold email for Notice, General and Special, and push for Alert too; Critical and Urgent go anyway | obs |
| one thread | one record per recipient; alarm kinds are updated in place (`IN_PLACE`), one thread per alarm on every channel (0176 §1) | obs |
| mailboxes (receiving) | cPanel, IMAP, POP, Gmail, Microsoft (Graph), SES inbound, read and answered in Pulse every 5 minutes; passwords sealed (`COMMS_SEAL_KEY`) | obs |
| fallback between channels | **today: none.** Email's only fallback is SMTP → SES; when both fail, a `mail.failure` message goes to Pulse and reaches the overseers by the same failing email (root cause 6; g4 §c). **Spec (row 81):** the message falls back to the next channel, logged "Fell back to push (email failed: <answer>)" | obs; des |
| message control | pauses a kind, a channel or a sender. **Today** it holds Critical too (g4 row 15); **spec (row 82)**: Critical is sent anyway | obs; des |
| spend | Bluehost's mail and our push keys: free; SES about $0.10 per 1,000 emails, at `SES_MONTHLY_CAP_USD`; use not read yet | obs |
| portal_links | through AWS (`service-defs.php` 'aws'): Billing, Keys · The site's key (IAM), Console (its address `{console}` filled in by the site, not read here), each **Documented · release #212**; Sign in not registered. "SES" and "S3" aren't slots: SES's page is a link on zone 2's email row | obs |

### 20.2 The four zones

| Zone | The Communications Dispatcher |
|---|---|
| 1 | Service · "Critical: procedures (by the spec; not in the register today)" · state · Stage 5 · setup notice · spend · **"Channels: email, Pulse, push · no SMS, no webhooks"** · radius |
| 2 | **the channel adapters:** Pulse · Email (SMTP, then SES; quiet hours; one email per conversation while unread; suppression) · Push (VAPID; pace; tags) · Mailboxes (receiving) · Fallback between channels (today none; spec row 81) · Message control (today holds Critical; spec row 82) |
| 3 | the route (each sender's last sent and failed, 24 h) · queue depth (*spec*) · bounces and complaints (*spec*; today an address is stopped, no rate) · push keys and dead devices · reached no one (*spec*, row 80) · founders without a device |
| 4 | On our system: **Send test dispatch (to the owner only)** · **Open the routing matrix** (who gets what, by role and level; https://traversence.com/admin/config/communications#cs-routing, email only today) · **Fix it** (a sender → https://traversence.com/admin/config/communications#cs-controls and its sender's card; push keys → Its key) · Check now. At Amazon Web Services (SES): Billing · Keys (IAM) · Console · Sign in (not registered) |

### 20.3 Incident Radius

| Fault | Held | On a backup | Unaffected | Basis |
|---|---|---|---|---|
| SES down | notices and the weekly digest by email; system email's backup | system email (sign-in codes, alarms) still goes by Bluehost's SMTP, first anyway; Pulse and push carry the rest | Pulse, push, the site | obs (code); des |
| SMTP and SES both refuse (row 81) | email of every kind | Pulse and push carry every message, each saying "Fell back to push (email failed: …)" | Pulse, push | des |
| Push keys refused | every push | Pulse and email | Pulse, email | des |
| A trip reaches no one (row 80) | every alarm's message (no active receiver) | none | the board shows every trip | des |
| **The dispatcher itself failing** (its database: `Notify` never throws, so a send is lost with no trace, g4 §c) | every message, on every channel | — | the site's pages and work | obs; des |

**Who watches the watcher:** when the dispatcher can't tell anyone, the outside heartbeat on GitHub (Bluehost's card,
§14.6a) emails **"<the address set as ALARM_TO>"**, a GitHub secret named `ALARM_TO`, never hard-coded (if it's unset, the heartbeat's run fails visibly on GitHub), through SES with its own sending-only secret, from outside the host (§20.6, 3).

### 20.4 The states (mockups)

| Mockup | Row (02) | New words | Today's words, beside them | Fix it |
|---|---|---|---|---|
| a Working | — | "On · Working"; the criticality difference shown as an observed note | — | — |
| b Email failing (SMTP 535, SES refused) | 81, 82 | "Email isn't sending (SMTP 535, SES refused) · Alarms go by Pulse and push." · "Paused by the system (email): still trying, 1 of 3, next at <time>." Each failed email "Fell back to push" | a `mail.failure` in Pulse, reaching overseers by the same failing email; no board alarm; nothing falls back (inf, g4 §c) | the sender's card (#cs-controls) and AWS's Console for SES; Check and start again |
| c Push keys refused | 29, 81, 106 | "Push isn't sending (VAPID keys refused) · Alarms go by Pulse and email." · "Paused by the system (push)" | the route `skipped` or `failed`; nothing tells anyone (g4 §c) | Its key (Replace key); no portal page (our own keys) |
| d A trip reached no one | 80; P11 | "The Communications Dispatcher: a trip reached no one (no active founder) · Down." The trip's row: "Reached no one: no active founder", logged | nothing sent, nothing logged (g4 row 14) | the routing matrix (#cs-routing); the outside heartbeat's email as the last way |

Under Jason's words-follow-the-state rule (12.6a), b and c read "Paused by the system (<channel>)" because the channel is
held. d reads "Down": nothing is held, and the alarms stand on the board.

### 20.5 Setup capture (§13.x)

Notice: **"Setup capture: Sign in not recorded"**.

Keys, names only. All of these are in `.env`:
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME`, `SMTP_REPLY_TO`.
- `<SENDER>_SMTP_USERNAME`, `<SENDER>_SMTP_PASSWORD`, for a sender with its own mailbox.
- `SES_MODE`, `SES_REGION` (else `AWS_REGION`).
- `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`, or `SES_PERSONAL_KEY_ID` and `SES_PERSONAL_SECRET`.
- `SES_BULK_KEY_ID`, `SES_BULK_SECRET`.
- `SES_MONTHLY_CAP_USD`, `SES_USD_PER_1000`, `EMAIL_LINK_KEY`.
- `VAPID_PRIVATE_PEM_B64`, `VAPID_PUBLIC_B64U` (write-only).
- `COMMS_SEAL_KEY` (write-only).

Mailboxes' passwords and Microsoft's tokens are sealed in the database, never in `.env`.

### 20.6 Open words for the dispatcher: answered by the session manager (2026-11-21)

1. **Registering the dispatcher as a critical part** (procedures), with `svc:aws` as one of its channels' providers, is a
   **step-4 build item**. It's on "For Jason's review". The card keeps "Critical: procedures (by the spec; not in the
   register today)".
2. **Email's order** stays as the code has it: Bluehost's SMTP first, SES the backup.
3. **With "no active founder",** the outside heartbeat emails the address set as a GitHub secret named **`ALARM_TO`**,
   never hard-coded. If it's unset, the heartbeat's run fails visibly on GitHub. Drawn as "<the address set as
   ALARM_TO>" on page d, and on Bluehost's card wherever the heartbeat's recipient appears (pages a, c1, d; §14.6a).
4. **The routing matrix** extends to Pulse and push, by role and level, as a **step-4 item** (0172 already decided it).
5. **SES's bounce and complaint rates** are read at no cost. Warnings come at AWS's review levels, **5% bounce and 0.1%
   complaint**, marked **inf**, with a step-4 check against AWS's docs. They're on "For Jason's review".

---

## 21. The AWS account's card (mockups `aws-*.html`)

**One module, two parts:** email (SES) and storage (S3). It has one key (IAM) and one bill. Built like the others
(`mockups/src/aws.py`) from the live code at mirror `aeb5f31`. No `.env` was opened and no call was made to AWS.

### 21.1 The manifest

| Field | Value | Basis |
|---|---|---|
| id · name | `aws` · Amazon Web Services | obs |
| purpose | "Email (SES), tables and forms (Textract), and file storage until the move to R2 is finished." | obs (`service-defs.php`) |
| role | **Service** | des |
| criticality | **Critical: procedures, through its email.** `ControlRegister::CRITICAL` maps `svc:aws` → procedures ("system email (SES): sign-in links, codes and notices") | obs |
| part 1: email (SES) | **Sending:** the Dispatcher's email backup (Bluehost's SMTP first, `Mailer`), and the sender for notices and the weekly digest (`SesMail`, a personal account; a bulk account when bulk mail is built). **Receiving:** SES inbound, through SNS to `/api/ses-inbound.php`, into its mailbox's Pulse inbox. **The suppression list:** a bounce or complaint stops all mail to that address and may pause its sender (`/api/ses-events.php`). **The rates:** bounce and complaint, read at no cost, warnings at 5% and 0.1% (inf; §20.6, 5) | obs; des |
| part 2: storage (S3) | every file the site keeps, by owner (`Store::KINDS`: members, businesses, guests, orders, system): photos, a business's files, GO Code logos, orders' proofs and artwork, the stock library's copies, posted videos and their 720p and 1080p copies (the video worker reads and writes through short-lived links). Uploads go from the browser straight to the store (`S3::presign`). Staging's publish copies its files there. **The code keeps no backups there.** **Until the move to Cloudflare R2** (decisions/0151, accepted; `S3_ENDPOINT` points the store at R2 with R2's own key): **drawn as S3 until `S3_ENDPOINT` (set or not, the name only) is answered** (§21.6, 1) | obs; needs live data |
| Textract | reading tables, forms and scanned PDFs (`scan_read`); menus after Google, before Claude | obs |
| spend | one bill: SES about $0.10 per 1,000 emails (cap `SES_MONTHLY_CAP_USD`, `ses_month_usd`); S3 $0.023 a GB-month and about $0.09 a GB downloaded after 100 GB free; Textract per page. **Use not read yet:** each billing lookup costs about a cent | obs |
| key | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` in `.env`: SES, S3 (while on AWS) and Textract | obs |
| its answers | 503 SlowDown → **a rate wait** (`ServiceHealth::classify()`, tested in `feeds-words.php`); 403 InvalidClientTokenId and the like → a refused key; SES's account-level pause → a stop kind | obs; inf (SES's pause wording) |
| portal_links | **the same as the Dispatcher's AWS slots:** Billing, Keys · The site's key (IAM), Console (its address `{console}` filled in by the site, not read here), each Documented · release #212; Sign in not registered. The "SES" and "S3" pages aren't slots | obs |
| the Dispatcher | its card names SES as one of its email providers and links here; this card links back | des |

### 21.2 The four zones

| Zone | Amazon Web Services |
|---|---|
| 1 | Service · "Critical: procedures, through its email (svc:aws, "system email")" · state · Stage 5 · setup notice · spend (use not read yet) · "Two parts: email (SES) and storage (S3) · one key (IAM), one bill · The Communications Dispatcher's card" · radius per part |
| 2 | Email: sending (SES) · Email: receiving (SES inbound) · Storage (S3) · Textract |
| 3 | SES's sending (status, bounce and complaint rates) · the file store (a signed check; SlowDown a rate wait) · its key |
| 4 | On our system: Check now · Pause a part (email or storage, automatic calls only). At Amazon Web Services: the Dispatcher's four slots |

### 21.3 Incident Radius, per part

| Fault | Held | On a backup | Unaffected | Basis |
|---|---|---|---|---|
| **SES down or paused** | notices and the weekly digest by email; inbound mail waits at AWS; bounce reports wait | alarms and sign-in codes by Bluehost's SMTP, first anyway; Pulse and push | storage; pages, search, sign-in, payments | obs (code); des |
| **S3 SlowDown** | nothing: a rate wait, never a trip (row 27); uploads and video copies wait a few minutes and go on | — | everything | obs (`classify()`, test) |
| **S3 down** (while storage is on AWS) | new uploads (the browser sends them straight to the store); video copies; staging's publish of files; **a Held line under the page duty: "Photos stored on S3 don't show"**, a partial hold, never a page-load trip (`media.php` points the browser at the store; §21.6, 3) | none | files still on the server (not moved yet) keep serving; SES; pages, search, payments | inf (from `Store.php`'s notes) |
| **The key refused** | both parts and Textract (while storage is on AWS; once it's on R2 with its own key, only email and Textract) | alarms by SMTP; menus by Claude | Pulse and push; files on the server; pages | des |

### 21.4 The states (mockups)

| Mockup | Row (02) | New words | Today's words, beside them | Fix it |
|---|---|---|---|---|
| a Working | — | "On · Working"; two parts, one key, one bill; the Dispatcher linked | — | — |
| b SES sending paused by AWS | 38a, 81 | "Amazon SES paused our sending · Paused by the system (email by SES)" | a failed SES send on the route; SES's rates not read; nothing on the board (g4 §c) | Console (SES's status and AWS's case), then Check and start again |
| c S3 asked us to slow down | 27, 28, 130 | "Amazon S3 asked us to slow down · Calls wait until <time>, then start again by themselves." | Common Crawl's S3 today: "Its server failed" with a SlowDown reading, never changed (obs, L12) | their trouble: Console (S3) |
| d The IAM key refused | 29, 106, 129 | "Amazon Web Services refused our key · Paused by the system: calls with AWS_ACCESS_KEY_ID wait for a new key." Both parts held | each caller fails on its own; no one alarm names the key | Keys · The site's key (IAM), registered; Replace key. Told: email by Bluehost's SMTP first, then Pulse and push, never through SES itself |

### 21.5 Setup capture (§13.x)

Notice: **"Setup capture: Sign in not recorded"**.

Keys, names only. All of these are in `.env`:
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION`.
- `S3_BUCKET`.
- `S3_ENDPOINT`, `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY`, `S3_REGION`: R2's own key, optional.
- `SES_MODE`, `SES_REGION`, `SES_PERSONAL_KEY_ID`, `SES_PERSONAL_SECRET`, `SES_BULK_KEY_ID`, `SES_BULK_SECRET`.
- `SES_MONTHLY_CAP_USD`, `SES_USD_PER_1000`.

### 21.6 Open words for AWS: answered (the session manager, 2026-11-21)

1. **Storage:** drawn as S3 until `S3_ENDPOINT` (set or not, the name only) is answered. **Needs live data.** If it's set
   live, storage moves to Cloudflare's card and this card keeps email and Textract.
2. **SES's account-level pause:** 02 row **38a** (row 38's shape): a stop kind that waits for a person; AWS lifts it.
   AWS's own wording stays **inf** until it's read. Mockup b cites it.
3. **S3 down:** a Held line under the page duty, "Photos stored on S3 don't show". A partial hold, never a page-load trip
   (§21.3; the radius on every AWS mockup).
4. **Textract** stays a line in zone 2, not a part of its own.
5. **Who's told when the key is refused:** confirmed. The alarm goes by Bluehost's SMTP first, then Pulse and push, never
   through SES itself. Mockup d says so in its intro and its thread's Told line.

---

## 22. The batched sources: Overture, Foursquare, Geofabrik, NPI (CMS) and the IRS (mockups `sources-*.html`)

Five free Sources the Railway worker loads in bulk for W1. Built like the others (`mockups/src/sources.py`) from the live
code at mirror `aeb5f31` and the crawler in this repo. No `.env` was opened and no outside call was made. **Shared by
all five:**
- **Role** Source; **criticality** "Not critical: no duty stops without it" (none is in `ControlRegister::CRITICAL`).
- **Spend** Free ($0), no bill; our cost is the worker's time, counted on Railway's card.
- **Stage** 5 · Operational, inferred: Overture, Foursquare and Geofabrik are `added_off` in their records, and they ran
  (IR-0001; Geofabrik's 2026-11-15 IPv6 fix), so a person switched them on (inf).
- **Last load and its counts:** read from each load table (`open_loads` seen and listed per town or state; `npi_loads`,
  `npi_orgs`; `irs_loads` rows seen and removed). **Needs live data**, so every mockup shows "not read yet".
- **Slots (04), the session manager's ruling (2026-11-21):** a download page isn't a portal slot (§5: no docs or our own
  runs in slots), so it's in zone 2 as **"Its files ↗"**. For open data with no account, all four slots read Not
  registered, each with the why **"Open data: no account"**. Foursquare's whys: Billing "Open data: no bill"; Keys "its
  key is a Hugging Face token: Hugging Face is a line on this card"; Console "Open data: its files are linked in zone 2";
  Sign in "a Hugging Face account: a line on this card".
- **Setup capture (§13):** every slot's why is recorded, so the notice clears ("Setup capture: each slot's why
  recorded"); names and places stay in the Details (§22.6's table).
- **Licence:** Overture's and Foursquare's cards show "licence: confirmation not recorded (0164 Decision 9)".
- **IR-0001** (`step2/ir-0001.md`): both W1 timeouts were reading **our** `/api/crawl/loads` reply. Under the contract
  it's a warning on the Site part (02 rows 11–13), the tasks aren't held, and they run at the next night's window
  (row 14). Today (live L2, L3) each one tripped and paused all of W1, twice.

### 22.1 Overture Maps (Places) (mockup b)

| Field | Value | Basis |
|---|---|---|
| purpose | "The base layer of businesses by town for Arizona and New Mexico … name, address, phone, website, category and confidence. Loaded monthly by the Railway worker, by town box, through the one identity check." | obs |
| licence and use | per record: CDLA-Permissive-2.0 (Meta, Microsoft, BrightQuery, DAC, Uberall, Overture), Apache-2.0 (Foursquare), CC0 (AllThePlaces). 0164 R9: corroboration, stored and shown with credit as its licences allow, **once they're confirmed** (Decision 9). Its emails field is never imported | obs |
| credit | "Overture Maps Foundation" plus the upstream; each record keeps its source and licence | obs |
| feeds | W1 · `overture_bulk`: towns by box, biggest gap first, `load_towns_per_run` a run (3 by default) | obs |
| cadence | the monthly release; `OVERTURE_RELEASE` (name only) pins one, else the newest on the bucket's listing | obs |
| priority map | Places by kind and town (W1): our OSM copy, **then Overture**, then it waits. Businesses in bulk (W1): our open-data copies, Overture, Foursquare, Geofabrik; the last load is kept | obs (`Preflight::NEEDS`) |
| hosts | declared `overturemaps-us-west-2.s3.amazonaws.com`, `overturemaps.org`; the release listing calls `…s3.us-west-2.amazonaws.com`, filed today under AWS (g2 row 31). Under the contract its calls count on Overture's card | obs; des |
| faults | the bucket down or slow (DuckDB's reads: not classified today, g1 row 5) → rows 24–26 on Overture, only `overture_bulk` waits, the last load kept; **no release found** (`_overture_release()` returns "" and the load reads `release//`) → a malformed answer, rows 24–26 (open word 2); our load endpoint slow → the Site part (IR-0001) | obs; des |
| no release found | its own words, a malformed answer (rows 24–26): "Overture: no release found · Its bucket listed no release at <time>. Trying again (1 of 3)." | des (the session manager, 2026-11-21) |
| mockup b | "On · Working", a "Linked" line naming IR-0001 as the Site part's warning; zone 3's endpoint line amber "Warning (Slow)"; today's words beside (L2) | des |

### 22.2 Foursquare open places (mockup c)

| Field | Value | Basis |
|---|---|---|
| purpose | "More businesses by town, where Overture doesn't already carry Foursquare's record … counted as the same source when it does." | obs |
| licence and use | Apache-2.0: its licence and NOTICE.txt kept with data we pass on, changes marked; R9 as Overture's | obs |
| credit | "Foursquare" | obs |
| feeds | W1 · `foursquare_bulk`, towns a run shared with Overture; read from Hugging Face, gated | obs |
| key | `HF_TOKEN` on Railway (a token whose account accepted Foursquare's terms). Set: the task table skips the task without it, and it ran (inf). A refused token is a task failure today (g2 row 3); under the contract rows 29–31, only `foursquare_bulk` held | obs; inf; des |
| cadence | `FSQ_RELEASE` (name only); unset, **the code reads the fixed `dt=2026-10-15`**, though its note says "the newest on Hugging Face" (open word 1) | obs |
| priority map | Businesses in bulk (W1), the third source; the last load is kept | obs |
| mockup c | Working, IR-0001's first member (1:20 AM MST), linked to IR-0001 and Overture's card | obs (times); des |

### 22.3 Geofabrik, our OpenStreetMap copy (mockup d)

| Field | Value | Basis |
|---|---|---|
| purpose | OpenStreetMap for Arizona, New Mexico, Utah, Colorado and Nevada as weekly state files; our only OpenStreetMap source since Overpass was retired (2026-11-19) | obs |
| licence and use | ODbL: its own layer, kept in its own tables, never copied into our listings (decisions/0065, 0164 R9); within it, show all it gives | obs |
| credit | "© OpenStreetMap contributors" | obs |
| feeds | W1 · `geofabrik_bulk`: one state a run, each state every `geofabrik_days` (7) at most; osmium-tool reads it; 500-place batches, the site's 502/503/504 waited out (30, 90, 180 s) | obs |
| priority map | Places by kind and town (W1): **our OSM copy first**; Businesses in bulk (W1): the fourth source | obs |
| faults | a state's download fails: **today it's logged on Railway only, the run moves on, and the site lists the state as due again, so the next run downloads it again** (`geofabrik_load`, `geofabrikDue()`). Its record says "a refused download is held for a person, never retried (a free mirror instead, by hand)"; only the test download (`geofabrik_probe`) raises a case. Under the contract: a server answer → rows 24–26 on **that state's file only**; a refusal → row 32 (a ban, never retried) | obs; des |
| by classification (the session manager, 2026-11-21) | **a failure** (a timeout or a 5xx) follows row 32's probe: held, one download a day for at most 3 days, then a person's call. **A refusal** (a 403 or a block) waits for a person at once, under Jason's refusal rule. **One state's file is the held part** | des |
| mockup d | Utah's file, a scenario 503 (a failure): "Geofabrik: Utah's file didn't download · Paused by the system (Utah's file). The system checks once a day for 3 days; after that it's your call." The refusal case named beside it. The other four states unaffected; Utah's last load kept (if its first load finished: needs live data); their trouble, no Fix it, its files in zone 2 | des |

### 22.4 NPI Registry (CMS) (mockup e)

| Field | Value | Basis |
|---|---|---|
| purpose | "Health providers' names, addresses and phones: the monthly file, the second look and identity checks." | obs |
| licence and use | public domain: owned, stored with the source (R9) | obs |
| feeds | W1 · `npi_bulk` (the monthly file); W1, W2 · `second_look`, `identity`, `npi_town` (single lookups, one at a time) | obs (`OutsideServices`) |
| cadence | **the monthly load is off since 2026-11-02** (`NpiBulk::OFF`, Jason: CMS took its file page down "until further notice"). Drawn as **"Off on purpose: CMS took its file page down (Jason, 2026-11-02)"**, a real switch with its why in step 4 (the session manager, 2026-11-21). Lookups go on | obs |
| priority map | Health providers (W1): our NPI copy, then CMS; the last load is kept. Confirm a business (W1, W2): CMS after our copies | obs |
| faults | the file page without a monthly file ("no monthly file found on the NPI download page") → a malformed answer, rows 24–26; the API down → rows 24–26 on the lookups | obs; des |
| mockup e | Working; the monthly file row "Off"; the NPI Registry linked on the record, not a slot | des |

### 22.5 IRS exempt organizations (mockup f)

| Field | Value | Basis |
|---|---|---|
| purpose | "Confirms a nonprofit exists (the monthly EO BMF file); its search linked on a listing's identity record." | obs |
| licence and use | public domain; **evidence only: never shown, never creates a listing** (R9, §12 C2) | obs |
| feeds | W1 · `irs_bulk`: `eo_{state}.csv` for Arizona and New Mexico, columns EIN, NAME, STREET, CITY, STATE, ZIP, NTEE_CD | obs |
| cadence | monthly: due when the last finished load is 30 days old | obs |
| priority map | Nonprofits (W1): our IRS copy, then the IRS; the last load is kept | obs |
| faults | **a file that changed shape.** Today columns are read by name, so a missing one loads as blank, silently; a missing EIN or NAME drops every row and "the IRS files had no rows the site accepted" keeps the old copy. **If one state's file fails and the other loads, `IrsBulk::finish()` removes every record not in this month's file: that state's nonprofits are deleted.** **02 row 38b:** the shape is checked before any write; a changed shape is a stop that waits for a person (the loader updated); **nothing is deleted** when the check fails or the load's rows fall below a floor. The code holds no typical counts, so the floor is **a share of last month's rows (90% proposed, des)** | obs (code); des |
| mockup f | "The IRS's exempt-organization file changed shape · Paused by the system: nothing of this month's load was kept." New Mexico's file without NTEE_CD (scenario), the floor in its Details. Fix it: its files (zone 2), then a build, then Check and start again | des |

### 22.6 Open words for the sources: answered (the session manager, 2026-11-21; the IRS delete with Jason)

Setup capture, names and places only:

| Source | Names | Place |
|---|---|---|
| Overture | `OVERTURE_RELEASE` (optional); `load_towns_per_run` | Railway; a setting |
| Foursquare | `HF_TOKEN`; `FSQ_RELEASE` (optional); `load_towns_per_run` | Railway; a setting |
| Geofabrik | `OSM_EXTRACT_BASE` (optional, a free mirror); `geofabrik_days`; osmium-tool | Railway; a setting; the worker's image |
| NPI (CMS) | `NPI_BULK`, `NPI_TIME`; `NpiBulk::OFF` | Railway; the site's code |
| IRS | `IRS_BULK` | Railway |

**All five**
1. **Console as the download page: no.** It isn't a slot (§5); it's "Its files ↗" in zone 2, and all four slots read Not
   registered with "Open data: no account" (Foursquare's whys in §22's head). The setup notice clears.
2. **Live data:** each load table's newest row, `OVERTURE_RELEASE` and `FSQ_RELEASE` (set or not, names only), and
   Utah, Colorado and Nevada's first loads go on the session manager's list of asks. Marked "needs live data".
3. **Licences:** the cards show "licence: confirmation not recorded (0164 Decision 9)", and "For Jason's review" has its
   own item (6).

**Overture.** 1: its S3 host goes on Overture's card. 2: "No release found" is a malformed answer (rows 24–26), with
its own words (§22.1).

**Foursquare.** 1: the fixed `dt=2026-10-15` default is a step-4 code finding. 2: Hugging Face is a line on this card,
not a module.

**Geofabrik.** 1: by classification. A refusal (a 403 or a block) waits for a person; a failure (a timeout or a 5xx)
follows row 32's probe. Page d shows the failure and names the refusal. 2: one state's file is the held part. 3: live
data.

**NPI (CMS).** 1: in step 4, a real switch with its why, "Off on purpose: CMS took its file page down (Jason,
2026-11-02)". 2: live data.

**IRS.** 1: 02 row **38b**: a file that changed shape is a stop that waits for a person; the shape is checked before any
write, and nothing is deleted when the check fails or the rows fall below the floor (a share of last month's rows, 90%
proposed). Page f shows it. 2: **the partial delete is with Jason** (the session manager is asking him); it stays on
"For Jason's review" (item 5).

---

## 23. The video worker's card (mockups `video-*.html`)

A **Service hosted on Railway** (the report): 720p and 1080p copies of posted videos (decisions/0153 §8, 0154 §2;
`workers/video/`). Built in `mockups/src/video.py` from the repo, the live code at mirror `aeb5f31`, and Railway's
read-only API (8:51 AM MST). No variables were read.

### 23.1 The manifest and four zones

| Field | Value | Basis |
|---|---|---|
| role · criticality | Service, hosted on Railway · **not critical** (`ControlRegister::CRITICAL` leaves it out, Jason) | obs |
| what it does | every 5 minutes (`*/5 * * * *`), a run of up to 240 s asks for 2 jobs: fetches the original through a short-lived link, makes a 720p copy (and a 1080p one when the poster's plan plays it) with ffmpeg, uploads through short-lived links, reports. It never holds the store's keys | obs (`video.py`) |
| the queue | `VideoJobs`: a job leased 20 minutes; a failure tries again, **3 times**, then "failed"; each copy checked in the store before it's recorded. Counts (`video_jobs` queued, done today, failed): **needs live data** | obs |
| endpoints | `/api/video/jobs.php`, `/api/video/results.php` (Site parts `video/jobs`, `video/results`) | obs |
| storage | the file store, originals and copies side by side (`v720`, `v1080`): links to the AWS card (S3 until the move to R2) | obs |
| on Railway | service `video-worker`, root `/workers/video`; latest deploy `628caa0d` SUCCESS Nov 17, 6:30 PM MST (commit `430d4c3`) | obs (Railway API) |
| **restart rule** | **Railway: On failure, up to 10. The repo: Never.** Drawn amber, "for Jason to choose"; the next deploy from the repo sets Never | obs |
| heartbeat | **missing today** (step 2, root cause 4). Spec: it checks in after each run; silent past its window (15 minutes, three runs) → row 50: a warning, a trip after 3 tries, **only this service trips** | des |
| spend | Railway's compute, "use not priced yet", counted on Railway's card | obs |
| slots | Railway's: Billing · Usage, Console · Dashboard (Documented · release #212); Keys and Sign in not registered | obs (02 §8) |
| zones | 1: Service · hosted on Railway, not critical, state, stage 5, setup notice, spend, radius. 2: copies of posted videos, its endpoints, its storage. 3: its heartbeat, on Railway (deploy, next run, the restart rule), its key. 4: Check now · Pause; At Railway, its slots | des |

**Incident Radius:** Held: new copies (they queue). On a backup: each video plays its original (`MemberPosts`: feeds
play `v720`, else the original). Unaffected: everything else, including the crawler and W0 on the same host.

### 23.2 States (mockups)

| Mockup | Row (02) | New words | Today's words | Fix it |
|---|---|---|---|---|
| a Working | — | "On · Working"; the restart rule amber | — | — |
| b Silent | 50 (47–48's shape) | "The video worker hasn't checked in · Paused by the system: no heartbeat since <time>. Its 3 tries failed; new video copies queue." | nothing: no heartbeat exists | Console (Railway): the service's deploy and cron runs, then Check and start again |
| c One copy failing | 49's shape | a Notice: "One video's copies failed: Moment #… after 3 tries (ffmpeg: …). It plays its original; nothing else waits." [Try this video again] | the job's row keeps its error; nothing on the board | none (no alarm) |

### 23.3 Setup capture

Notice "Setup capture: Sign in not recorded · Keys: why not recorded" (Railway's slots). Names and places:
`VIDEO_WORKER_TOKEN` (.env and Railway, the same value), `TRAVERSENCE_SITE`, `RUN_SECONDS`, `JOBS_PER_ASK` (Railway),
`VIDEO_WORKER_PAUSED` (.env, Settings; staging sets it).

### 23.4 Open words for the video worker

1. **The restart rule:** On failure (Railway) or Never (the repo)? Jason to choose.
2. **One job failing has no 02 row:** drawn as row 49's shape (no fault on the worker) with a Notice naming the video.
   Confirm, and whether [Try this video again] belongs on the card or on the post.
3. **The heartbeat's window:** 15 minutes (row 50) is three of its 5-minute runs. Keep it, or tie it to the cron?
4. **The queue's counts** need live data (`video_jobs`).

## 24. Cloudflare's card (mockups `cloudflare-*.html`)

Built in `mockups/src/cloudflare.py` from the live code at mirror `aeb5f31`. No `.env` opened; no outside call (a DNS
lookup of the site would be one, so DNS is "needs live data").

### 24.1 What the code uses it for

| Part | What the code shows | State drawn | Basis |
|---|---|---|---|
| **Sign-in check (Turnstile)** | sign-in, sign-up, Forgot password, likes' check (decisions/0097). Off until `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` are set. **Fails open** when Cloudflare doesn't answer ("don't lock everyone out"); **refuses** when its answer isn't a success | on or off: needs live data | obs (`Captcha.php`) |
| **File storage (R2)** | decisions/0151; `S3_ENDPOINT` points the store at R2 with its own key; the pre-flight's R2 line and CORS rule; `files_tick` every 10 minutes | **Proposed (stage 1)** until `S3_ENDPOINT` is answered (needs live data); the AWS card carries storage meanwhile (§21.6, 1) | obs; des |
| **cdnjs** | browsers load Leaflet (maps) and pdf.js (PDF previews) from `cdnjs.cloudflare.com` (13 files) | "not checked by us": the server never calls it | obs |
| **DNS** | `ControlRegister::CRITICAL` maps `svc:cloudflare` → page, "unsure: in front of the site's address"; the captcha's notes say DNS "doesn't have to be on Cloudflare"; `RegionGate` reads `CF-IPCountry` only when behind it | needs live data | obs |
| **The storefront edge** | **not Cloudflare:** Fly.io with Caddy (`workers/storefront-edge`, decisions/0139): its own Host card | — | obs |

**Slots (04):** Billing · Billing and Console · Dashboard (Documented · release #212, 02 §8); Keys and Sign in not
registered. "R2 buckets" and "Turnstile" are pages, linked on their zone 2 lines, not slots. `Pulse::SERVICES` links
the Dashboard. **Spend:** R2's prices and Turnstile free, "use not read yet". **Criticality:** "Critical: page load
(svc:cloudflare), flagged 'unsure' in the code".

**Incident Radius, per part:** R2 down (once in use): uploads wait on the server, stored files don't show. cdnjs down:
maps and PDF previews don't draw. Turnstile down: sign-in goes on without the check (its backup by design). Unaffected:
pages, search, payments (unless DNS is on it), the storefront edge.

### 24.2 States (mockups)

| Mockup | Row (02) | New words | Today's words | Fix it |
|---|---|---|---|---|
| a Working | — | "On · Working"; R2 Proposed; DNS needs live data | — | — |
| b Turnstile secret refused (its most real fault: the pre-flight already tests for it) | 29, 106 | "Cloudflare refused our Turnstile secret · Paused by the system: the sign-in check with TURNSTILE_SECRET_KEY waits for a new key." Held: password sign-in, sign-up, Forgot password; On a backup: Google, Microsoft, Facebook sign-in; touches `site:signin` (procedures) | each visitor: "The "verify you're human" check didn't pass. Please try again."; the log "captcha refused: invalid-input-secret"; the pre-flight only when it runs | Console (Turnstile's secret), then Replace key |

### 24.3 Setup capture

Notice "Setup capture: Sign in not recorded · Keys: why not recorded". Names: `TURNSTILE_SITE_KEY`,
`TURNSTILE_SECRET_KEY` (.env); `S3_ENDPOINT`, `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY`, `S3_REGION` (.env, optional,
R2's key; listed on the AWS card too).

### 24.4 Open words for Cloudflare

1. **Is the site's DNS on Cloudflare?** Needs live data. If not, `svc:cloudflare` shouldn't carry the page duty.
2. **Is R2 live?** `Pulse::SERVICES` says R2 holds "every photo, video and file"; the AWS record says S3 "until the
   move to R2 is finished". The same `S3_ENDPOINT` ask as §21.6, 1.
3. **A refused Turnstile secret locks out password sign-in**, while an outage lets people through. Should a refused
   secret fail open too (with the alarm), or stay closed? Jason's call.
4. **cdnjs** isn't checked by any sensor. A line only, or a browser-side check in step 4?
5. **The storefront edge** (Fly.io) needs its own Host card: not in this batch.

---

## For Jason's review

Items noted for Jason's review of the step-3 design. None is asked now.

1. **QuickBooks' processing fees** (§19.6, 3): reading them joins the step-4 ledger export as a spec item. It's a read of
   our own account's data at no cost, and the spend chip reads "fees not read yet" until then.
2. **The shared web search cap's unit** (§18.6, 2): it counts searches today, as the code does, with a dollars line
   beside it. Should the cap be a count or dollars, since Brave costs $0.005 a search and Tavily $0.008 a credit?
3. **Registering the Communications Dispatcher as a critical part** (procedures) in step 4, with `svc:aws` (SES) as one
   of its channels' providers (§20.6, 1). Today the register has no dispatcher part.
4. **SES's bounce and complaint warnings** at AWS's review levels: 5% bounce, 0.1% complaint (inf; a step-4 check
   against AWS's docs). The rates are read at no cost (§20.6, 5).
5. **The IRS load's partial delete** (§22.5, §22.6 IRS 2): when one state's file loads and the other's rows are all
   refused, the finish removes the second state's nonprofits. A code fix for step 4 (finish per state, or not at all
   unless every state loaded), not a card word.
6. **Confirm Overture (CDLA-Permissive-2.0) and Foursquare (Apache-2.0) licences — loads already running** (0164
   Decision 9; §22.6, 3). The session manager's note: W1 added 205 in run #1290. Step 2's `ir-0001.md` reads #1290 as
   stopped before its first town, so that count isn't confirmed from the evidence here.
