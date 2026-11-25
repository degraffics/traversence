# Piece 3: the before-and-after check (design, for Jason's review)

Written 2026-11-25 by the Piece 2 build session, on the session manager's brief.

**Decided (Jason, 2026-11-25, reviewed with Gemini; decisions/0179's note "Piece 3's decisions"):** "DB1 A, DB2 A, DB3 A,
DB4 A, Staff Views yes ... Lock these in for Piece 3." As the note records them, and as this design now reads:
- **DB1:** "before" is staging just before the change, never live.
- **DB2:** a block withdraws the release itself, and puts the failure in the Work queue (an Audit ticket at Found, with the
  report: P3-2).
- **DB3:** data fixes stay in the daily batch at first. A row limit comes later, Jason's choice (§4's `data_fix` is
  recognised but not published alone).
- **DB4:** the check's GitHub Actions minutes have a daily budget. Once it's spent, releases wait for the batch.
- **Staff Views:** an advocate bound to the staff test logins checks admin pages (P3-1 adds it to the roster).

Piece 3's releases are **not pre-approved**: each is built, tested and held, its summary goes to the session manager,
and it's sent only once Jason approves the daily batch it's in. Jason presses staging's three switches himself; step 1
(Keep staging caught up) finishes its fresh copy before steps 2 and 3.

The briefs in §9 are kept as he read them. Rule 2 of decisions/0179 (low-risk kinds publish alone) stays off until this check exists *and* Jason switches it on.

Sources:
- decisions/0179: rule 2; "How it works"; the note "How the Team's capacity grows", §3 and §4.
- decisions/0145: the audit's browser checks, `tools/page-audit/rendered.mjs`, `paths.mjs`, `paths-staging.mjs`,
  `check-kit.mjs`, `layout-probe.mjs`.
- decisions/0165: staging. Its §3: a test account's work is never carried to live.
- The live release pipeline: `app/lib/Releases.php` (`checks()`, `pagesOf()`, `autoPublish()`, `selfWithdraw()`,
  `rollBack()`, the review hold); `app/lib/AuditClock.php`; `app/lib/StagingPublish.php` (`preview()`).
- Piece 2, live as #287–#290:
  - `team_runs.touches`;
  - the 11 advocates in `team_roster` with their `test_account`;
  - `TeamRunner::TIER3_AREAS` and `touches()`;
  - `team_runs.cost_micros`;
  - the runner's Undo.
- `handoffs/piece2-team.md` (piece 3's pieces: "Undo on every agent action", "a spending limit per agent").
- `tools/page-audit/zipcheck.py` and `checkout.py` (add vs change claims, `find_callers`).

## 0. In one paragraph

Every release that reaches staging, and every Publish of staging's own work, waits there for one more check:
- Each advocate opens the pages the change touches, signed in as its test account, at 1280 and 390, before the change
  and after it.
- The two looks are compared on 0179's four rules.
- Any rule broken: the release doesn't publish (it withdraws itself, as a failed check does today), and the report
  says which advocate, which page, which rule, with both screenshots.
- All clear: the release publishes as today, or waits in the daily batch.

The check reuses the audit's sign-ins and page checks. It runs on GitHub Actions (the web host can't run a browser),
started by the site as AuditClock already starts the audit's runs. The verdict comes back as a file, the way
`gauges.json` does.

## 1. What runs

### 1.1 When

| Trigger | Today | With piece 3 |
|---|---|---|
| A release zip comes from git | `fromGit()` → `straightOn()` (on staging, `checks()`) → `autoPublish()` or `selfWithdraw()` | Same, but after `checks()` pass it waits as **"Being checked by the advocates"** until the verdict comes. A pass goes on to `autoPublish()` (or the batch); a block goes to `selfWithdraw()`. |
| Publish of staging's own work (agents' records, what's made on staging) | `StagingPublish::preview()` then Publish | Publish waits for the same check on the pages the preview's records show on (§1.2) |
| An agent's step on staging (team_runs) | logged with Undo | Nothing new on its own. A step changes records, and they reach live only by the Publish above, so that's where they're checked. |

### 1.2 Which pages ("the pages it touches")

All of these, merged, at most 40 pages a run:

1. **Its own pages:** `Releases::pagesOf()`, i.e. the release's `website/*.php` that aren't endpoints, plus `/` and `/faq.php`.
2. **Pages that load a changed library or UI part:** for each changed `app/` file, the `website/` pages that reach it,
   found with `checkout.py`'s `find_callers` walked up to a page. **Gap G3:** `pagesOf()` covers `website/` files only.
   This walk is new: release P3-1 builds it as `reach.py`, cached per mirror sync.
3. **Admin sections:** for a changed admin tool or manifest entry, its `/admin/<module>/<section>` (rendered.mjs's
   `adminSections()` already lists them).
4. **What its `release.json` names:** each `changes[].where` that's one of our addresses.
5. **For a staging Publish:** each record in `StagingPublish::preview()` mapped to the page that shows it, from the table's
   page in `staging-tables.php`'s classes and `team_runs.touches` (`task:N` → the Work queue's ticket page).
   **Gap G3b:** not every table names its page yet. P3-4 adds a page to each class entry that has one.

### 1.3 Who looks

| Advocate (team_roster) | Test account | Looks at |
|---|---|---|
| Guest Visitor | signed out | every public page in the list |
| New Member, Resident & Member, Editorial Contributor, Group Steward, Client & Sandbox Buyer | `new`, `member`, `contributor`, `steward`, `client` | the public pages, plus their own member pages in the list (`/user/…`, `/dashboard.php`, `/business/…`) |
| Business Owner, Manager, Accountant, Sales, Viewer | `owner`, `manager`, `accountant`, `sales`, `viewer` | the same, plus the business portal pages in the list |

- **Staff views:** admin pages are looked at as the staff test logins (`TestAccounts::STAFF`). The Test Executive sees
  every admin page in the list; the department logins see the sections they open.
- **Gap G8:** no advocate is bound to a staff login. Suggest a change to Piece 2's roster: one "Staff Views" advocate
  bound to the staff test logins, so the report names a role for staff views too. Until then the report says "staff
  test login".
- The Founder is never a test login (TestAccounts), and nothing here signs in as Jason.

### 1.4 Before and after

- **After:** staging with the change on it.
- **Before:** see Decision brief 1.
  - **Recommended:** staging just before the change. The release waits as "Waiting for its before look" while the check
    reads staging as it is, then goes on.
  - **The other choice:** live, as the same advocates (test sign-ins work on both, per check-kit). It needs no pause,
    but live's data differs from staging's, so more is noise.
- **Noise:**
  - The before look is taken **twice**. Anything that differs between the two (times, live counts, random photos) is
    marked as moving, and never counts as a change.
  - Times drawn with `data-tv-time` are left out of the text compare.
- **Sizes:** 1280×900 and 390×844, as rendered.mjs.

### 1.5 What each look records, per advocate × page × size

- The HTTP status, the final address, and whether it landed on a sign-in page or a refusal.
- From rendered.mjs's `check()`: script errors, failed own requests, sideways scroll, cards off the 3px edge, browser
  dialogs, images without alt, unnamed controls.
- **New in P3-1:**
  - **Controls:** every visible link and button: its name, its target, whether it's enabled.
  - **Content:** visible text by region (header, main, each card); list counts read from the table standard's
    "1–10 of 12", the number of cards, rows and pins.
  - **Overlap and clipping:**
    - Primary controls and cards whose boxes overlap another visible control.
    - Text cut off by `overflow:hidden`, measured as scrollWidth > clientWidth on text elements.
    - A control outside the window.
  - **Privacy markers:** a scan of the page text for every *other* advocate's private markers (§2.1).
  - A full-page screenshot.

Everything is kept with the run as an artifact for a day, as live-read's are, and never committed. Only the verdict file
is committed.

## 2. How each rule is measured

A rule **blocks** when the after look shows something the before look didn't. A problem that was already there before is
reported but doesn't block: it's Site health's to fix, not this release's.

### 2.1 Access or privacy (0066 included)

| What | How | From today's checks? |
|---|---|---|
| A leak: an advocate reaches what it couldn't before | the page was a refusal or sign-in redirect before, and opens after (status, final address) | status and final address: yes (rendered.mjs) |
| Lost access or an error on a valid view | opened before; after it's 4xx, 5xx, a sign-in page, a script error or a failed request | yes (rendered.mjs) |
| Another person's details shown | **canaries:** each test account on staging carries unique markers in its private fields (an email `check+<key>@…`, a phone, a note, a saved place). Any of them in another advocate's after view, absent before, blocks. | **Gap G4:** the markers aren't seeded yet. P3-1 seeds them on staging only; test accounts' work is never carried to live (0165 §3). |
| 0066: a held place shown | a **sensitive canary landmark** on staging, held by `Landmarks::SENSITIVE`: its name or pin in any public view blocks. Each page's public pins are counted before and after; more pins on nation land (`Nations::pin()`) with no new public place is flagged. | **Gap G5:** the canary landmark doesn't exist. It's seeded on staging only, never carried. |
| A Tier 3 area reached | the change's files run through `TeamRunner::touches()`: Tier 3 never reaches rule 2 (§4). The check still runs, and the report names the area. | yes (P2-4) |

### 2.2 Layout and phone

| What | How | From today's checks? |
|---|---|---|
| Sideways scroll, cards off the edge, dialogs, unnamed controls, images without alt | rendered.mjs's standards, compared before and after: a new miss blocks | yes |
| Overlap, clipping, a control off screen | the new box measures (§1.5) | **Gap G1:** not measured today. layout-probe.mjs measures two specific things only. |
| Broken controls | a control that was enabled before and is disabled or gone after, by name. P3-1 clicks only menus and filters that open in place, and never sends a form (read-only, as live-read). | **Gap G1b:** new |
| Missing primary content or buttons | the page's primary action (the one `.ak-b.go`, or the command strip's Save/Close) and its heading, present before and missing after | new, from the same capture |
| Navigation | the header's menus, ☰ and the way back (`tv-trail.js`) present before and after | new |

### 2.3 Missing data and broken core flows

| What | How | From today's checks? |
|---|---|---|
| Fewer rows, cards or pins than before | list counts and card counts per region, after against before, beyond what moved between the two before looks | **Gap G2:** not measured today. New in P3-1. |
| Text gone from a region | a region's text before that's missing after (not reworded: missing), unless the release's `changes` names that page | new |
| Core flows | `paths.mjs`'s core paths and `paths-staging.mjs`'s runners that pass through any touched page: run after on staging, and before (they already run on staging twice a week). A path that passed before and fails after blocks. | yes, the paths exist. Staging's paths change test accounts' own records only (0165 §3). |

### 2.4 Out-of-scope drift without a reason

| What | How |
|---|---|
| A page changed that the change doesn't name | Each page looked at that differs (controls, region text or counts, beyond what moves on its own) must be expected: in §1.2's own-pages list, `changes[].where`, or the ticket's `team_runs.touches`. A page that changed and wasn't expected blocks, unless `release.json` gives it under a new key `"also_changes": [{"page": "/x", "why": "…"}]`. |
| A file the ticket didn't plan | for an agent's change: the zip's files against the build brief's named files (P2-3's `build_brief`). Extra files are flagged for a person, not blocked: a brief can be short. |

**Gap G3**, the reach from `app/` files to pages, decides how well drift is measured. Without it, a library change's
pages can't all be "expected", so P3-1 builds the reach before the drift rule blocks anything. Until then, drift only
reports.

## 3. Where the verdict shows, and how a block stops publish

**How the verdict travels:**
1. The site dispatches `beforeafter.yml` with the release number, its pages and its expected list. It uses
   `repository_dispatch` with `GITHUB_DOCS_TOKEN`, exactly as `AuditClock` asks for the audit's runs. Never from
   staging (`Staging::here()`).
2. The workflow writes `site-audit/results/beforeafter/<release>.json`: the verdict, each block with advocate, page,
   size, rule and evidence, and the run's link. It commits that file to `main`.
3. `releases_tick` (every 10 minutes) and Check for builds read it, the way `ControlGauges` reads `gauges.json`.

**How it shows and blocks:**
- **The Releases page:** a new line in the release's checks, "Before and after (advocates)": passed / blocked: <the
  first block> / waiting, with a link to the report.
  - It's a real check item, so `checks_ok` is false on a block. `autoPublish()` already refuses anything not
    `checks_ok`, and `selfWithdraw()` already withdraws a failed git release by itself.
  - **Gap G11:** `checks()` counts an item that is `null` (not known) as passing. The waiting state must not, so P3-2
    adds the state "Being checked by the advocates", in which `autoPublish()` waits. It never treats the missing
    verdict as a pass.
- **A block:**
  - It withdraws itself as a failed check does today. Nothing on live changes, and staging is put back.
  - The release's record (`deliveries/releases/`) carries the report's first lines.
  - The session that sent it fixes it and sends it again.
  - See Decision brief 2 for the other choice (hold on staging for a person).
- **No verdict in 60 minutes** (the run failed or GitHub is down): the release keeps waiting, never publishes, and
  Site health raises "The before-and-after check didn't answer for release #N", with **Ask for it again**.
- **The daily batch:** each line's `checks` field gets the verdict and the report's link.
- **A ticket's page:** a Work ticket at Implementation shows its release's verdict. Its "every step confirmed" move to
  Done (P2-1) also needs the check passed.

## 4. Rule 2's low-risk kinds, recognized from release.json and the zip check

A release names its kind: `"kind": "faq" | "wording" | "data_fix" | "phone_layout" | "additive"`. The zip check
verifies it. If the zip doesn't match, it's not low-risk and goes in the batch, as today; the zip check says why, and
nothing is refused for that. Every kind also needs these three:
- `TeamRunner::touches()` on the zip's files and its migration's tables is empty: no Tier 3 area.
- No `"review"` in `release.json`.
- The before-and-after check passes.

| Jason's words (0179 rule 2) | `kind` | The zip check proves |
|---|---|---|
| FAQ | `faq` | the only file is `app/ui/faq.md`; no question's heading changed (anchors stay: `grep "faq.php#"`); lines only added or changed inside answers |
| wording | `wording` | in each changed PHP file, the token streams before and after are equal except inside string literals and inline HTML text, and no tag, attribute, `href` or `data-` changed. Checked by tokenizing (PHP's `token_get_all`, the way `checks()` already parses). |
| data fixes | `data_fix` | one migration; only `UPDATE` or `INSERT … WHERE NOT EXISTS`; no `DELETE`, `DROP`, `ALTER` or `TRUNCATE`; its tables outside TIER3_AREAS; `data_reason` given; at most the row limit (Decision brief 3). The rows are counted when it runs on staging. **Gap G9:** the runner logs statements, not rows changed; P3-3 adds the count. |
| mobile-layout tweaks | `phone_layout` | only CSS (files or `<style>` blocks), each changed rule inside an `@media (max-width: 767px)` block. The before-and-after check finds every 1280 view unchanged. |
| tests | (none needed) | tests live in this repo, not in a site zip, so they never become a release. A tests-only PR is merged as today. |
| safe additive changes | `additive` | 0179's own definition: the zip check shows every file `-0 +N` (only added lines); every claim is an add (`checkout.py`), none a change; no R08 drops; migrations only `CREATE TABLE` or guarded `ADD COLUMN`; new tables classed (R14). |

A release that passes publishes alone, with no batch. It's listed in that day's summary as "Published alone (low-risk:
faq)" with **Undo**, which is the Releases page's existing Roll back.

Rule 2 stays behind its own switch, `rule2_autopublish`, shipped off. Only the Founder turns it on, and the History keeps it.

## 5. The ceiling's Confirmation evidence

P2-3's raise to Confirmation is greyed, saying "Needs the before-and-after check (Piece 3)". With piece 3 it reads
these lines. As with the other stages, the Founder judges them; there are no pass marks.

| Line | Reading | Source |
|---|---|---|
| Agent changes checked | in the last 14 days: N checked · N passed · N blocked | `beforeafter/*.json` for releases whose ticket a Team role worked (`team_runs.task_id`) |
| Blocks a person judged wrong | N blocks later sent again unchanged and passed, or marked "the check was wrong" | the releases' records |
| Escapes | N passed, then rolled back, or a Site health finding opened on a page they touched, within 7 days | `Releases` (`rolled_back`), `findings.json` against the report's pages |
| Undo end to end | the daily summary's Undo on a low-risk release, tried on staging: rolled back, and its check after Undo passes | P3-3's test run, recorded |
| Holds intact | outside calls, payments and email still held on staging during the checks | `StagingGate` held list: nothing let through during a run |

## 6. Undo on every agent action, and a spending limit per agent

From 0179's "What comes next" and `handoffs/piece2-team.md`:
- **Undo:**
  - Every runner step already logs its Undo (`team_runs.undo_how`, `undo_data`).
  - P3-4 adds a test that fails the build if any step type can write without one. A step that truly can't be undone
    must say why in `undo_how`, and is then never allowed to act alone; it only proposes.
  - For a low-risk release published alone, Undo is its Roll back (§4).
- **Spending limit:**
  - Agents spend nothing today (Q4 B: no AI for agents; `cost_micros` is always 0).
  - P3-4 adds a monthly limit per role in `team_settings`, default $0. The runner refuses a step whose estimate
    (`AiClient::estimate`) would pass it, saying so on the ticket.
  - The check's own cost is GitHub Actions minutes (see Decision brief 4). It's counted per run in the verdict file,
    and shown on the Team page next to the evidence.

## 7. Releases in order, each with its tests

| # | What | Where it runs | Its tests |
|---|---|---|---|
| **P3-1** | The check itself: `beforeafter.yml` and `beforeafter.mjs`, sharing check-kit and rendered.mjs's page check; the new captures (§1.5); the four rules (§2); the reach map `reach.py` (G3); staging canaries (G4, G5) seeded by a staging-only script; the verdict file | this repo (GitHub Actions) and staging's test accounts. No live change. | A fixture site on the bench (PHP's server, as the team tests use): one page made worse in each way (a leak, a lost page, sideways scroll, overlap, a missing button, fewer rows, an unexpected page change, another account's canary, the sensitive landmark shown). Each must block, naming its rule. A neutral change and a moving clock must pass. Reach map tests against the mirror. Run twice on real staging with no change: both pass. |
| **P3-2** | The site's side: the "Waiting for its before look" and "Being checked by the advocates" states; the dispatch; reading the verdict; the checks line; block → withdraw; no answer in 60 minutes → Site health; the batch line and the ticket line; FAQ | a site release (Releases.php, the Releases page, the Work queue's batch strip and ticket page) | `tools/staging-tests/` style on MariaDB: each state; a pass publishes; a block withdraws and staging is put back; no verdict never publishes; a verdict for another release is ignored; a forged file (not from main) is ignored. Rendered at 1280 and 390. Roll back proved as Piece 2's were. |
| **P3-3** | Rule 2's kinds: `"kind"` in release.json and its proof in `zipcheck.py` (§4); the migration runner's row count (G9); `rule2_autopublish` (off); "Published alone" in the daily summary with Undo | this repo (zipcheck) and a site release | `test_zipcheck_kind.py`: each kind's true and false cases (a wording change that sneaks in an `href`; an additive file with one changed line; a data fix with a `DELETE`; a phone tweak outside its media query; a file in a Tier 3 area). On MariaDB: the switch off keeps everything in the batch; on, a passing low-risk release publishes alone and its Undo rolls it back. |
| **P3-4** | The agents' side: the Undo test across every step; the spending limit per role; staging Publish waits for the check (§1.1); each class entry's page (G3b); the Confirmation evidence lines (§5), with Raise to Confirmation open to the Founder | a site release | runner.php additions: a step without Undo fails the build; a cap refuses with its words; Publish of staging waits and blocks. team-head.cjs: Confirmation's evidence and its raise (Founder only). |

Each goes through the daily batch (rule 1) like any other release. None publishes alone: rule 2 isn't on until
Jason turns it on after P3-3.

## 8. Gaps named

| # | Gap | Where it's closed |
|---|---|---|
| G1 | overlap, clipping and off-screen controls aren't measured | P3-1 |
| G1b | broken controls (enabled → disabled or gone) aren't measured | P3-1 |
| G2 | data counts per page aren't measured | P3-1 |
| G3 | no map from an `app/` file to the pages that load it (`pagesOf()` is `website/` only) | P3-1 (`reach.py`, from `find_callers`) |
| G3b | not every staging table names the page that shows it | P3-4 |
| G4 | no private markers in the test accounts to catch a leak | P3-1, staging only |
| G5 | no sensitive test landmark to prove 0066 holds | P3-1, staging only |
| G6 | a release is staged at once, so there's no moment for a before look on staging | P3-2 (Decision brief 1) |
| G7 | a browser can't run on the web host | by design: GitHub Actions, dispatched as AuditClock does |
| G8 | no advocate for staff views | a suggested change to Piece 2's roster (§1.3) |
| G9 | the migration runner doesn't count rows changed | P3-3 |
| G10 | a deadlock between decisions has no source in the code | recorded by the session manager for Jason (Piece 2): a word flag only |
| G11 | `checks()` lets an unknown (`null`) item pass | P3-2's waiting state |

## 9. Decision briefs for Jason (0179's form)

### Decision brief 1: where the "before" look comes from

- **The Decision:** what a change is compared against.
- **Why it needs you:** it sets how long every release waits on staging, and how much of what the check reports is
  noise. That decides how often a good change is held up.
- **Option A: staging, just before the change (recommended).** The release waits a few minutes while the advocates look
  at staging as it is, then the change goes on and they look again.
  - Pros: same data, same server. Almost only the change differs, so few false blocks.
  - Cons: every release takes about 10 minutes longer to reach live. A new state on the Releases page.
- **Option B: live as the "before".**
  - Pros: no pause; nothing new in the pipeline.
  - Cons: live's data differs from staging's (staging is a copy, and test accounts' work stays on staging). Lists,
    counts and cards differ for reasons that aren't the change, so more false blocks, or a looser check to avoid them.
- **Recommendation:** A.
- **Impact if delayed:** none on the site. Piece 3 can't start building until this is set. Rule 2 stays off meanwhile,
  so everything keeps going through the daily batch. Cost: none.

### Decision brief 2: what a block does

- **The Decision:** when the advocates find a page made worse, does the release withdraw itself, or wait on staging for
  a person?
- **Why it needs you:** it's the first time a check reads pages *as people see them*, and a false block costs a day.
- **Option A: withdraws itself, as a failed check does today (recommended).** Nothing on live changes; staging is put
  back; the report goes with the release's record; the session fixes it and sends it again.
  - Pros: one rule for every failed check; nothing waits half-done on staging; no new button.
  - Cons: a false block needs a new send, even when a person can see it's fine.
- **Option B: waits on staging,** with **Publish anyway** (the Founder only, with a reason, in its History) and
  **Withdraw**.
  - Pros: a false block costs one press.
  - Cons: a way round the check exists. It's yours alone, but it's a second path for a release to go live.
- **Recommendation:** A for now. Revisit with the "blocks judged wrong" evidence line (§5) after a few weeks.
- **Impact if delayed:** none until P3-2 is built. Cost: none.

### Decision brief 3: how big a data fix may publish alone

- **The Decision:** your rule 2 lists "data fixes" as low-risk. "Deleting or reshaping major data" is always Tier 3.
  Where's the line?
- **Why it needs you:** the line between a small fix and major data is yours, and nothing in the decisions sets a
  number.
- **Option A: a data fix publishes alone only while it changes at most N rows, in tables outside the Tier 3 areas, with
  no deletes.** You choose N; 50 would cover a typo fix across a town's listings. Above N, it goes in the batch.
  - Pros: small fixes flow; the count is measured on staging before publish.
  - Cons: a number to set, and change later.
- **Option B: data fixes stay in the batch until the check has run for a while.**
  - Pros: the safest start.
  - Cons: the most common agent fix waits a day each time.
- **Recommendation:** B at first, then A with your N, once the Confirmation evidence (§5) has two clean weeks.
- **Impact if delayed:** data fixes go through the batch as today. Cost: none.

### Decision brief 4: the check's running cost

- **The Decision:** every release now also runs a browser check on GitHub Actions: about 4–8 minutes for 11 advocates
  over up to 40 pages at two sizes.
- **Why it needs you:** "No income, no expense". If the repository's Actions minutes pass GitHub's free allowance, it
  costs money.
- **Option A: run the check within a daily minute budget,** counted in each verdict file. Over the budget, releases
  wait for the next day's budget, never publishing unchecked.
  - Pros: never spends.
  - Cons: on a heavy day releases wait.
- **Option B: run the check on every release whatever the minutes,** and show the month's minutes on the Team page.
  - Pros: no waiting.
  - Cons: may spend money.
- **Recommendation:** A, with the budget set from P3-1's measured minutes per run. The repository's plan and its free
  minutes aren't recorded in the decisions. This is a gap; the session manager can read them from GitHub's billing
  page for you.
- **Impact if delayed:** none until P3-1. Cost: none until then.

## 10. What's unchanged

- Rule 1: everything not low-risk waits for the daily batch.
- Tier 3: always a Decision Brief to Jason.
- 0066 and the privacy guards: the check only reads, as the test accounts and staff test logins. It sends no form and
  changes nothing on live. The canaries exist on staging only, and test accounts' work is never carried (0165 §3).
- The urgent path for live harm (0165) still applies. It's checked straight after, as today, and the check runs then too.
