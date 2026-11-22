# Step 3 closed: the control board's design, for step 4 (the build)

2026-11-22. The session manager asked for one closing summary, so step 4 (the build) can start. It covers:

- what Jason has approved;
- what's still open, split into what blocks the build and what doesn't;
- the build's scope as one release;
- the read-only checks the build needs;
- the pop-up text for Jason.

Specs only: nothing here is built, sent or switched. Each line names its source. The step's documents are 01–04,
`sections/`, `workflows-page.md`, `association-map.md`, `for-jason-review.md` and the mockups in `board/` (generated
by `mockups/src/`).

---

## 1. Everything Jason has approved

| # | What | Date | Source |
|---|---|---|---|
| 1 | The step-3 frame: his module harmony report, with three corrections (his eight stages, no SMS, no webhooks yet) | 2026-11-21 | `module-harmony-report.pdf`; README |
| 2 | **Closed trips and switches** (§0): a hold is its own record; the system never writes a person's switch; "Off by the system (left by a closed trip)" stays off until a person switches it on | 2026-11-21 | 02 §0, §0a ruling 4 (O4) |
| 3 | **Toggles in folded groups:** counts on the header, a group with a switch still off opens by itself, Switch on never hidden | 2026-11-21 | 02 §0a ruling 1 |
| 4 | **Retry spacing:** about 5, 15 and 35 minutes inside the 60-minute "Keep the hour" (des, the existing back-off) | 2026-11-21 | 02 §0a ruling 2 |
| 5 | **A part whose next run is its check:** never held against its own next run; a repeat failure is a Trip that says so | 2026-11-21 | 02 §0a ruling 3 (O2) |
| 6 | **Words while something is held:** "Paused by the system (<what's held>): still trying, n of 3, next at <time>"; "Down" only when a host is gone and nothing could hold | 2026-11-21 | 02 §0a ruling 5; 03 §12.6a 2 |
| 7 | **When Bluehost is down:** an outside heartbeat on GitHub (every 15–30 min) sends email only through SES, writes an unnumbered record, imported with T- and IR- numbers when the site is back | 2026-11-21 | 02 §0a ruling 6, O3 |
| 8 | **Bluehost's cPanel** link: Console from `CPANEL_HOST`; the my.bluehost.com link folded into Sign in · Account | 2026-11-21 | 02 §0a ruling 7 |
| 9 | **Q19:** alarm and logbook history 90 days, trip and IR records 365 days; `control_cost_factor` 2.0× the 30-day average. **O1:** a 15-minute correlation window | 2026-11-21 | 02 §0a answers |
| 10 | **O5:** provider pages from what's documented and from his Vault, strictly by type, never invented or borrowed | 2026-11-21 | 02 §0a answers |
| 11 | **The stage badge:** "Stage 5 of 8 · Operational" and "Next allowed: …", compact | 2026-11-21 | 03 §2 |
| 12 | **The 13 open words on the cards** ("Accept, then GitHub"): the top line's answers, the push body, senders "Control Board" / "Traversence Control Board", Recovered's proof line, the deploy's log link (spec), Recovered naming every switch still off, and the rest | 2026-11-21 | 03 §10 |
| 13 | **GitHub's three decisions:** railway-c5's dollars kept and labelled; "Paused, then trying"; a read-only token for Actions minutes (spec, later, through Its key) | 2026-11-21 | 03 §12.6a |
| 14 | **Setup & Access Capture** on the card (stage 1's exit guard) | 2026-11-21 | 03 §13; 04 §10 |
| 15 | **The video worker's restart rule:** on failure, up to 10 | 2026-11-21 | 03 §23 |
| 16 | **04's locked link-label rules:** fixed order Billing · Keys · Console · Sign in; the slot type as the label; four source tags; "No <type> page registered" with Publish from your Vault; two slots a row at 390; the label mappings | 2026-11-21 | 04 §5 |
| 17 | **04's seven answers:** show both and record his pick; the label rulings; owner-only publishing; no account label; the 180-day note as words; the Vault's one-time page first; an unknown domain confirmed on the spot | 2026-11-21 | 04 §7 |
| 18 | **For Jason's review, item 5: the IRS load's partial delete: "Fix in the build"** (shape check before any write; no delete below 90% of last month's rows, the number des) | 2026-11-21 | for-jason-review item 5; 03 §22.5 |
| 19 | **For Jason's review, item 9: "Is it delivering?"** adopted as the board's first view, objectives in seven groups | 2026-11-21 | for-jason-review item 9; 03 §27 |
| 20 | **Every snapshot is one gauge card,** with drill paths (superseding step 1's "skip big gauges") | 2026-11-22 | 03 §27 |
| 21 | **One gauge, one measure:** "if a two needs are required it's no longer a gauge" | 2026-11-22 | 03 §27; `sections/crossover.md` |
| 22 | **The 16 development gauges:** 6 front end, 10 back end (the admin's modules as manifests.php has them) | 2026-11-22 | `sections/development-gauges.md` |
| 23 | **The gauge lifecycle:** each starts at 0 in Planning, configured to say what 100 means; the five words read as "(a) Stage of the work" | 2026-11-22 | `sections/gauge-lifecycle.md` §1–4; 03 §27 |
| 24 | **The criteria come from the page audit:** "I think this is the page audit process" | 2026-11-22 | `sections/gauge-criteria-from-audit.md` |
| 25 | **The runtime lifecycle:** setup % (probe, target, check, alarm) through Planning to Implementation, the live reading at Monitoring ("Approve") | 2026-11-22 | gauge-lifecycle §7 |
| 26 | **Three drill paths:** Workflows, Core Services, Registered Services | 2026-11-22 | gauge-lifecycle §4; 03 §27 |
| 27 | **Site health on one path,** through the dispatcher | 2026-11-22 | gauge-lifecycle §8, 1 |
| 28 | **"Reviewed" per 0145:** no open finding plus his sign-off on record; else "audited, awaiting sign-off" | 2026-11-22 | gauge-lifecycle §8, 2; gauge-criteria-from-audit §3 |
| 29 | **The check register's targets and schedule** (26 checks, five runs); AUDIT's 20% ours, approved with the schedule | 2026-11-22 | `sections/check-register.md`; gauge-lifecycle §8, 3 |
| 30 | **Score gauges, no needle;** then **Arc, his choice** | 2026-11-22 | 03 §27; gauge-lifecycle §1 |
| 31 | **The Workflows page: "Approve"** | 2026-11-22 | `workflows-page.md` §11; 03 §27 |
| 32 | **The workflow targets: "Approve as proposed"** (W0–W12, W14; W13 a list) | 2026-11-22 | `workflows-page.md` §5, §11 |
| 33 | **W3's two switches: "After the re-read"** (they stay off until IR-0001's timings and Common Crawl's index are read; no live action) | 2026-11-22 | `workflows-page.md` §11 |

The session manager's rulings that the build carries (not Jason's, recorded for completeness):
- R118 wins in the case.
- `/api/crawl/loads.php` is a component with its own check (a build requirement).
- W13 is a list.
- "Alarm wired" means through the one alarm engine.
- W0's start state is held as live data.

Source: `workflows-page.md` §10.

---

## 2. What's still open

**(A) blocks the build: 1 item. (B) doesn't: 22 items**, each folded into an answer from the model where one exists.
Every open "For Jason's review" item (1–4, 6–8, 10, 11), the nine placements and every other open question in 02 to 03
is placed below.

### (A) Blocks the build

**A1. The outside heartbeat's two GitHub secrets: `ALARM_TO` and an SES send-only credential.**
- **Why it blocks:** step 4's scenario test must pass every cell of the failure map. One cell is "Bluehost is down": the
  heartbeat on GitHub's runners emails through SES (02 §0a ruling 6). That cell can't run without:
  - an address to send to;
  - a credential limited to sending.

  Only Jason can set either (GitHub → Settings → Secrets; AWS IAM). `asks.md` check C17 reads whether `ALARM_TO` is set,
  by name only.
- **From the model:**
  - **Who:** R126 says a trip reaches "the Founder's email". So `ALARM_TO` is the address trips already reach, not a new
    choice.
  - **What it may cost:** the credential sends only, at SES's fraction-of-a-cent system-email cost. Jason confirmed that
    exception (2026-11-09; 02 §0a ruling 6).
- **Recommended:**
  - Set `ALARM_TO` to the Founder's address trips already use.
  - Create an SES send-only credential, its secret names given by the build, entered on GitHub at
    https://github.com/degraffics/traversence/settings/secrets/actions.
  - Values never pass through chat.
  - Until both are set, the build sends with "review": true and that one cell marked "not set up (ALARM_TO)".

### (B) Doesn't block: folded into answers from the model

| # | Item | The answer from the model (or why it waits without blocking) | Source |
|---|---|---|---|
| B1 | For Jason's review 1: QuickBooks' processing fees | The build's ledger export reads them (our own account, no cost); the chip says "fees not read yet" until then | 03 §19.6; CLAUDE.md "No income, no expense" |
| B2 | Review 2: the web search cap's unit | Keep the count the code enforces, with a dollars line on both cards; dollars later is a threshold change, logged (R29's pattern) | 03 §18.6; R164 |
| B3 | Review 3: the dispatcher as a critical part | Every alarm reaches a person through the one dispatcher (R134); critical parts carry a duty set in code (R29): the build registers it critical for procedures, SES one of its providers | R29, R134; 03 §20.6 |
| B4 | Review 4: SES's 5% and 0.1% | Ship as editable thresholds (des), checked against AWS's docs in the build | 03 §20.6; R29 |
| B5 | Review 6: Overture's and Foursquare's licences | The board shows "licence: confirmation not recorded" (0164 Decision 9, R9: the licence decides the use). The engine doesn't depend on it; his confirmation stays a data question | 03 §22.6; decisions/0164 |
| B6 | Review 7: the trvgo.us redirect while the edge is down | The board draws the true radius either way (the edge's addresses fail with it). Serving shops on trvgo.us is a storefront change for its own release | 03 §25.2, §25.4 |
| B7 | Review 8: thresholds marked des (24-hour certificate notice, SES's rates, the IRS 90%) | Ship as des defaults, editable on Thresholds & gates, each change logged (R29, R154) | 03 §25.4; item 5 |
| B8 | Review 10: 1Password for our staff | Retire it through the register once the Vault is in use (a person's Retire, R157); the build changes nothing | 03 §26.24; decisions/0156 |
| B9 | Review 11: the two sages | The board keeps both as they are; the design session's question | 03 §28.5; brand.md §4 |
| B10–B18 | **The nine placements** ("Needs changes", waiting for his detail): Search Phase C, the legal texts, Home, DMCA, the FAQ, Ask Traversence, Universal search, the admin engine, Terms and privacy | A gauge's scope is signed off later: each development gauge sits in Planning until its configuration is signed (gauge-lifecycle §3). The build ships the gauges, not their signed scope | gauge-lifecycle §8; `sections/inventory.json` questions16 |
| B19 | The 16 gauges' scope sign-off itself | The same: Planning ends at his sign-off, gauge by gauge, after the build | gauge-lifecycle §3 |
| B20 | The runtime gauges' targets still "set by Jason" (Pages answer, Paths complete, Fresh, Complete, Knowledge keeps growing, Things recover, Self-recovery, Search) | Those gauges stay at or below Audit & Review until a target is set (the approved lifecycle); the register's approved targets cover the checks | gauge-lifecycle §7; `sections/check-register.md` |
| B21 | 02 §10: provider pages from his Vault | The cards say "No <type> page registered for <Provider> yet" with Publish from your Vault (04's locked rules) | 02 §10; 04 §5 |
| B22 | 02 §10: IR-0001's root cause | "It doesn't change the model: row 11 holds either way." The read is check C1 below | 02 §10; `step2/ir-0001.md` |

GitHub's open words (03 §12.6) are all folded already:
- row 57's "Down": 12.6a 2;
- the token URL: never borrowed (04 rule 4);
- a stuck sync's and a failed check's titles: row 60's and row 62's shapes (P9);
- GitHub's spend chip: 12.6a 3;
- "Cancel the run": a build row.

None is open.

---

## 3. The build's scope, as one release (handoffs/control-board-plan.md, step 4)

> "One session builds it, start to finish. No other session touches the board's files until it's live. A scenario test
> causes every cell of the failure map on staging, for every workflow, task, service and job … Every cell must pass.
> Rendered checks of every board view at 1280px and 390px. Sent with "review": true."

**What it contains:**

| Part | What | Source |
|---|---|---|
| **The one engine** | One library applying 02's state model and its 196 transition rows; every caller (crawler reports, pre-flight, service checks, jobs, the worker) raises through it; the five separate versions removed | 02 §1 P1, §4, §5; plan step 3 |
| **Holds and the radius** | A trip holds only what its cause touches; `/api/crawl/loads.php` as a component holding only overture_bulk, foursquare_bulk and geofabrik_bulk; the interlock; On a backup | 02 §6; R86–R99; `workflows-page.md` §3 |
| **The board's views** | Is it delivering? (runtime gauge cards, the Development rows: 16 gauges, the five-stage strip); Modules (four-zone cards); Workflows (`workflows-page.md`, W0–W14, each one's own page); Diagnostics; Pages and Coverage; Paused now; Start-up; Arc gauges throughout | 03 §1–§27; `workflows-page.md`; `board/index.html` |
| **The cards** | Railway, GitHub, Bluehost, Anthropic, Google (Places, Cloud, Gemini), Tavily, Brave, QuickBooks, the dispatcher, AWS, the batched sources, the video worker, Cloudflare, Fly.io, §26's services | 03 §5, §12–§26 |
| **The Vault's portal links** | Publish from your Vault, the source tags, strict typing | 04 |
| **The notices** | Every alarm through the one dispatcher: board, Pulse, email, push, in one thread shape | 02 §7; R102–R135 |
| **The outside heartbeat** | GitHub every 15–30 min, email only through SES | 02 §0a ruling 6 |
| **The fixes decided** | The IRS shape check and 90% floor; the video worker's restart rule; the dispatcher registered critical | for-jason-review 5; 03 §23; B3 |
| **The ledger export** | Read-only; replaces the Data browser reads (QuickBooks' fees, the load tables) | asks.md; B1 |
| **The check register's missing checks** | TOOLS first, then LOAD, PHOTOS-SHOWN, BRAND, AUDIT, with PHOTOS' results in the one format; in this repo, with the release | `sections/check-register.md` |

**Requirements:** all 189 in `step1-requirements.md`, mapped in 02 §9 and cited by page (`workflows-page.md` §2: 64 for the
Workflows page).

**Its scenario test:** every cell of 02's failure map is caused on staging (safe mode, `StagingGate::hold()`). This covers
every workflow (W0–W14), task, service and job. Each cell is checked against the map:
- what's held;
- the alarm;
- its words on all four channels;
- Fix it or Check it now;
- recovery.

It includes the case drawn end to end in `workflows-page.md` §8 (Common Crawl's index down holds only W3's per-site copy).

**Staging first:** sent with `"review": true`. Jason sees it on staging with the scenario results, and approves UI, UX and
Functional before Publish.

**The checks after (plan step 5):**
- The scenario test runs again at every release touching the board.
- A daily consistency check on live: each open alarm's hold, words and Fix it match the map.
- An Incident Report for each trip.
- A week's report from the session manager.

---

## 4. The read-only checks the build needs

`asks.md` is reworked as checks C1–C20. Each one says:
- what it reads;
- why the build needs it;
- that it is read-only;
- who can run it: Jason on an admin page, or a check on GitHub's runners signed in as the audit's executive test
  account (as the rendered check already does).

They read names only, never values. **Five block the build:** C2, C3, C6, C15 and C17.

---

## 5. The pop-up for Jason (drafted, not asked)

**Q1. Step 3: approve it as complete, and merge `claude/control-board-step1` into main?** It holds specs and mockups
only; nothing is built or sent.
- Approve and merge (Recommended)
- Not now
- Needs changes

**Q2. The outside heartbeat's two GitHub secrets (A1).** The "Bluehost is down" cell of the scenario test needs:
- `ALARM_TO`, the address trips already reach (R126: the Founder's email);
- an SES send-only credential.

You set both on GitHub (https://github.com/degraffics/traversence/settings/secrets/actions); the values never pass
through chat.
- Set both before the build's staging run (Recommended)
- Build now, with that one cell marked "not set up (ALARM_TO)" until you set them
- Needs changes

**Q3. The read-only checks (C1–C20 in `asks.md`; five block the build).**
- Run them now on GitHub's runners (Recommended). They sign in as the audit's executive test account, read only, names
  never values, and the results are written to the repo.
- I'll give the readings myself, from the admin pages listed
- Needs changes
