# The gauge panel as a drill-down (Control Board build session, 2026-11-23): mockup for review

Session `session_01CXLi5hL7DpeN9WxT3oMXQK` took over from `session_01CXLi5iMkDf1xiJ4V9rScRe` (`handoffs/control-board-build-2026-11-23.md`,
Next §1). Built on Sync 19:01 UTC (130f41a). Data: `gauges-items.json` of 2026-11-23 14:39 UTC. **A mockup only: nothing
here is sent or live.**

## The ask

- Jason: "The gauge opened side panel and presents a ton of overwhelming text. I want a visual drill down into things I can
  act on." And: "every single listed item is a dead end."
- The session manager's shape (2026-11-23): the gauge, its stage and "What moves it: N things"; one stacked bar by status,
  each segment filtering the list; "What you can do", 3–5 cards with one button each; items as compact rows with 6 dots,
  opening in place with pass/fail chips and the three paths; sources behind a Details fold. Every item and reading leads
  somewhere you can act. Applied to every gauge panel, runtime gauges included, with a rendered test that nothing is inert.

## The mockup

`handoffs/gauge-panel/mockup.html` (drawn by `gen.py` from the real files; open it in a browser). Screens:
`dev-1280.png`, `dev-390.png` (Discovery), `dev-{1280,390}-fail-open.png` (filtered to Failing, one row open),
`dev-{1280,390}-finding.png` (a failing check opened in place, with Back), `rt-{1280,390}.png` (Search finds what they came
for). No sideways scroll at 390 (scrollWidth 390); 1280 likewise.

### A development gauge (Discovery, 30 things)

1. **Top:** the dial, its stage pill, "What moves it: 30 things", and the draft preview in words ("44% once you sign off the
   draft").
2. **One bar** of the 30 by where each stands, in the lifecycle's order (gauge-lifecycle.md §3: an item sits where its
   earliest unmet criterion is). Each segment and its legend chip is a button that filters the list; pressed again, all.
   - **Failing a check** (amber; 3): a check ran and failed, or an open finding.
   - **Not built yet** (taupe; 6): decided, or built and held.
   - **Awaiting your sign-off** (copper; 20): live and audited, "reviewed" waits only for Jason (§8 decided 2).
   - **Needs a check built** (river blue; 1): signed off, but a check it needs doesn't exist yet.
   - **Live and checked** (sage; 0 today).
   - No red: css-theme-brand.md §2 keeps System Red for trips and halting failures; a build gauge's failing check is amber.
3. **What you can do:** up to five cards, one sentence and one button: Sign off the scope; Show the 3 failing; Review the 20
   awaiting sign-off; TOOLS isn't built and 23 wait on it → What it needs; Show the 6 not built.
4. **The list,** what needs you first: each row its name (the link), 6 dots (decided · built · live · checked · reviewed ·
   proved: filled met, amber failing, dashed no check yet, hatched awaiting sign-off, hollow not yet) and ▾.
5. **A row opened in place:** one sentence of where it stands; a chip per check (passes ↗ its result; fails → its finding;
   no check yet → what building it needs; partly built → what's missing); its one next action; Open the page; its row in
   Site health; Workflows · Core Services · Registered Services; "Where this comes from" folded, every reference a link.
6. **A finding or a check opens over the panel with ← Back** (one panel at a time, 0175 release 2a): a finding's detail,
   its screenshot, paths blocked, Open the page, its fix route (the proposal), Send to a build, Mark fixed, Set aside; a
   check's last run, what passes, when it runs, its full result; a check not built: what building it needs and where it is
   in the queue.
7. **Details** (folded): the gauges' files and the rule, as links. The three paths at the foot.

### A runtime gauge (Search finds what they came for, Audit & Review, 2 of 4 setup steps)

The same pattern: the dial and stage; "What moves it: 2 of 4 setup steps, then its reading"; a 4-part bar of its setup;
What you can do (Approve the proposed target; See the failing cases; each Fix it's service card); its setup as rows, each
with its action; its needs with each source a chip opening that service's card in the Service register; an unmapped line
gets Send to a build in place of "association not mapped".

## Revision 2: the Design reviewer's 9 changes (2026-11-23)

The Design reviewer asked for changes on 4ba32ad and c75238a and approved Q4 (five segments). Each change, as made:
1. **One filled button per box:** brand brown #92400E with page-colour text (5.18:1); the rest outlined in brown. Copper is
   no longer used.
2. **Awaiting your sign-off is amethyst:** #7D6B8D as a fill, #6B5A7C where it's text (5.51:1 on the card).
3. **The dots have a legend:** an ⓘ beside the key opens the five states with a sample of each, and closes with ×, Esc or
   a click outside. Each dot has a title, and the row's dots carry one aria-label. At 390 the dots become one phrase
   ("Failing at checked").
4. **Runtime's colour-only setup bar is gone.** "Its setup" lists the four steps in words (✓ done, needs you, failing,
   not wired yet).
5. **Each action is in one place: the cards.** A setup row reads its state, with a small link that brings its card into
   view.
6. **One time format,** labelled: "Nov 23, 10:20 AM UTC". The site will use TvTime.
7. **Plain names:** "The tools check", "Page speed", "Code checks", "Runs daily with the page check". The ids stay in
   "Where this comes from".
8. **No gauge paths in an opened row.** The three paths stay at the panel's foot.
9. **First view:** the first 10 rows, then "Show all 30". A segment shows all of its own. Runtime's needs fold to one line
   ("7 needs, 12 sources ▸").

Also applied, from Jason's rule of 2026-11-23 ("a finished step replaces its form"): Sign off, Send to a build, Approve
the target, Mark fixed and Set aside each turn into their line in place, for example "Signed off by Jason, Nov 23, 7:21 PM
UTC · Undo", and the button goes. Undo puts the button back.

A failing check's row says "failed on this page on its last run (time)", never the check's site-wide totals.

**Walked:** `gauge-panel/walk.cjs`, at 1280 and 390, all passed. It:
- presses every segment, every card button, every chip in a failing row and Back;
- opens and closes the legend by ⓘ, Esc, × and a click outside;
- signs off and presses Undo, and Show all 30;
- checks no sideways scroll, no page error, and no inert row name, chip or segment.

Screens: `dev-{1280,390}*.png`, `rt-{1280,390}*.png`, including `-legend`, `-fail-open`, `-finding`, `-signed` and `-needs`.

## Revision 3: Jason's answer on Q2, and the session manager's rulings (2026-11-23)

**Jason on Q2** (relayed by the session manager): "1. I need to view the issue. 2. then press a button to fix it. 3. once
fixed it should clear the issue. So it is a process not just an accounting of someone's actions." Written into
gauge-lifecycle §8, decided 2, as a note.

What changed in the mockup:
- **No Sign off on any item**, and no "awaiting sign-off" state.
- **The bar has four segments:** Issues to fix · Not built yet · No check yet · Done. Discovery reads 6 · 6 · 18 · 0.
- **"Reviewed" now means live with no open issue.** The 17 items that read "awaiting sign-off" become:
  - issues, where a check fails;
  - "No check yet", where no check reaches them;
  - done, where their checks pass (none on Discovery yet, since the tools check reaches none of them).
- **An issue opened in place is the view:** its evidence chips and one sentence, ending "It clears by itself once it
  passes again".
- **Fix it** is the one filled button. The row then reads "Fixing: <the issue> · sent to a build for its owning session ·
  Jason, <time>", with Undo. Only the pressed button goes; "See what it found" stays.
- **No check yet** has "Add a check" (a build request). Not built has "Fix it: build it".
- **Mark fixed and Set aside are no longer in the panel**, because an issue clears only by its check. They stay on Site
  health's own rows until that list follows the same process (Q5's release).

The session manager's rulings:
- **Q1:** Fix it, where there's no direct action, is a logbook request. The row names the owning session; health.php
  reports it; the daily fetch writes `site-audit/results/build-requests.json`; the session manager queues it under its
  session. A press needs no further OK.
- **Q3:** approved as recommended. The logbook's sign-off overrides the file's; health.php feeds it to gauges.py. The scope
  sign-off and "Approve the target" stay, and the target card shows its evidence ("Today: 94 of 106 cases pass").
- **Q5:** approved for a later release: one finding per check and page, BRAND and LOAD at "update" severity.
- **The gauge files:** held for the release.

The build also moves gauges.py to the new rule: reviewed is met on a live item with no open finding; no sign-off reading.

Walked again: `walk.cjs`, all passed at 1280 and 390. It now also checks that no item has a Sign off, and that Fix it
turns into its Fixing line, which Undo turns back.

## Revision 4: one item, one cycle (Jason, CLAUDE.md #1308 and #1309, 2026-11-23)

Jason: "Don't convolute processes or tasks. Fixing an alarm concludes with the single alarm being fixed, whereas a process
that covers fixing all alarms is multiple single cycles of the process linked to a workflow." CLAUDE.md #1309: a single
task ends at its confirmation, with Undo, and the lists and counts update; a "Next" button appears only within a chained
process.

- **One item is one cycle:**
  1. view its evidence;
  2. Fix it;
  3. "Fixing: <the issue> · sent to the Discovery build · Jason, <time> · Undo";
  4. "Cleared: its check passed, <time>". It ends there.
- **No "Next" anywhere.** The "Next issue ▸", "All 6 sent" and the cleared row's "Next: …" were tried and withdrawn. An
  item's next task, if it has one, shows as its own item in its own group, with its own button.
- **Counts follow the state.** A cleared item leaves its group. The bar, the chip, the card's "Show the 5", the heading
  ("5 · Issues to fix (1 just cleared, still shown)") and its dots all drop at once. That's how the person sees the cycle
  close. The row stays in view until the list is filtered again. An empty group's segment and chip hide.
- **The mockup shows Cleared** with a "(mockup: show it cleared)" link. On the site it comes from the check's next pass.
- **Wording:** "sent to the Discovery build"; "Brand standards failed".
- **The scope card stays,** as "Approve the scope" (the session manager: "Approving a scope is a real decision of Jason's,
  not an issue being cleared"). It's what moves a gauge out of Planning (§3). Its line reads "Scope approved by Jason,
  <time> · Undo". "Approve the target" works the same way.
- **walk.cjs** checks, at 1280 and 390, that:
  - no item has a Sign off;
  - Fix it turns into its Fixing line, which Undo turns back;
  - there's no Next after Fixing or after Cleared;
  - the counts drop from 6 to 5.

Joining this release (the session manager, 2026-11-23): Jason's "a finished step replaces its form" across every
tv_ops_form on the cards.
- After a press, one line: "Signs in as … · recorded by …, <time>", with Change and Undo. The form folds away.
- No notice repeats the line, and no notice promises a button that isn't on screen.

## Every element and where it leads

| Element | Leads to | Today |
|---|---|---|
| An item's name | the page itself, Peek (`data-peek-frame`) | **Gap:** `route` is set for 134 of 292 items. gauges.py adds it from pages.md's row (62 refs) and the admin manifests; else the name opens its source |
| Its row in Site health | `#site-health-<id>` on the Development page | exists (tv_cb_site_health) |
| A passing check chip | the check's result (scorecard.md, rendered.md, paths.md…) | exists (register `evidence`) |
| A failing check chip | its finding (screenshot, fix route) | **Gap:** findings.json holds J/V/D findings; a RENDER, LAYOUT or SEARCH fail on an item has no finding id. gauges.py links each failing check to its finding, else to its report |
| "no check yet: X" | X's register entry: what building it needs, its queue place | the register exists; **no queue place** (no session owns TOOLS, LOAD, PHOTOS-SHOWN, BRAND, AUDIT yet) |
| An open finding | its evidence and fix route | exists (findings.json: shot_url, proposal_url) |
| Awaiting sign-off | Sign off, in place | **new** (Q2) |
| Not built / a failing finding / no check | Send to a build | **new** (Q1) |
| Sign off the scope; Approve the target | written to the logbook, the gauge moves | **new**; source: gauge-lifecycle.md §3 (approved), §5 (proposal) (Q3) |
| A runtime need's source | that service's card (`/admin/config/register#svc-…`) | exists |
| An unmapped line | Send to a build (map it) | today "association not mapped", inert |
| The three paths | the path panels (rows open their own page) | exists (tv_cb_path_panels) |

## Today's panel: kept, moved, dropped

- **Kept:** the dial and stage pill; the × and Esc and a click outside; the lazy-loaded detail (`?gauge=` fragment); the
  three path buttons; Fix it links (as "What you can do" cards); "no check yet"; the draft preview (now in the top line);
  Configure's four setup lines (runtime: now rows with actions).
- **Moved:** Measure, Target, Trend and "What it counts" into Details; each item's source line into "Where this comes from"
  as links; items by status (`<details>` per status) into the bar's filters.
- **Dropped:** nothing, pending review.

## Data defects the mockup found (fixed on this branch: gauges.py, checkreg.py, the gauges regenerated)

The gauges on main were already stale (`gauges.php` 11a fails on main). Regenerated here; `gauges.php` 77/77 on the bench.
Previews: Dashboard & Pulse 50 → 49, System Configuration 49 → 48 (lifecycle §6 and test 5b updated, with the reason).
After it, Discovery reads 6 failing (BRAND's contrast on its pages), 6 not built, 17 awaiting sign-off, 1 needs a check.

0. **LOAD, PHOTOS-SHOWN, BRAND, TOOLS and AUDIT are built** (results since 2026-11-23 15:48–16:14 UTC, from the previous
   session's 421e6ab) but the register still said "not built". checkreg.py now reads each check's results file. An item
   one of them doesn't reach (TOOLS runs on `/admin/api/…` endpoints, not pages) reads "doesn't reach it yet", never
   "not built". Several rows per item (each account, phone and desktop): the worst stands.
00. **The new checks' failures aren't in Site health:** 202 of their finding ids aren't in `findings.json` (findings.py
   doesn't read them). The panel links only a finding Site health has; the rest open the check's run. Q5 below.
1. **LAYOUT reads a stale failure.** The register and the gauges say "5px above the hero … (2026-11-21)", failing two
   Discovery items; `site-audit/layout-probe.md` of 2026-11-23 10:20 UTC shows a 0px gap on the landmark, Discovery and
   Parks. Words follow the state (playbook principle 4).
2. **The step-3 register's latest lines are older than the gauges'** (SEARCH 101 of 106 there, 94 of 106 in
   gauges-items.json). The panel reads gauges-items.json's `checks` only.
3. **158 items have no route** and **failing checks have no finding id** (table above).

## Questions (one review, then Jason once): five

- **Q1. Send to a build: what it does.** No decision states it. Proposed: it writes a request (the item, the gauge, the
  reason, who pressed, when) to the board's logbook and shows "In a build: queued <time>" with Undo; health.php carries open
  requests; the daily health fetch writes them to `site-audit/results/build-requests.json`; the session manager places each
  in its working queue under the owning session (by area) and the row then names that session. Alternative: a Pulse step
  for staff only (no build session reads Pulse).
- **Q2. Sign off on an item: what it records.** Reviewed needs "his sign-off on record (a release approval, or his ruling on
  the page's proposal)" (lifecycle §8 decided 2; 0145 §5 asks UI, UX and Functional approval on a release). Proposed: Sign
  off in the panel is his ruling, written to the logbook with his name and time, Executive only (greyed with the reason for
  others), Undo for a day. Or: it opens the item's release record for the three approvals instead.
- **Q3. Sign off the scope / Approve the target.** Lifecycle §3 (approved) writes the scope sign-off to the logbook with its
  date. Today `signed` comes only from gauges.json (repo). Proposed: the site reads its logbook's sign-off over the file's,
  at once, and health.php reports it so gauges.py records it.
- **Q5. The new checks' failures in Site health?** BRAND alone fails 198 page views (contrast). Feeding them all to
  findings.py would flood Site health and Pulse. Proposed: one finding per check and page (not per view), BRAND and LOAD
  as "update" severity.
- **Q4. Five bar segments, not four:** approved by the Design reviewer (2026-11-23). "Failing a check" separated from "Needs a check", so a fix and a missing check
  aren't one colour.

## Also in this release (the hand-off's queue)

- "Retire it…'s spinner": asked the session manager what it saw (2026-11-23, no answer yet); reproduce on the bench first.
- Anthropic's card: "Recording a why is to come: the manifest's setup_capture field … isn't built" (`app/admin/module-cards.php:409`):
  either it does the thing or it isn't shown.
- "Library of Congress: No answer: Operation timed out after 15004 milliseconds…" → "Library of Congress isn't answering",
  the timeout in the Reading, through `ServiceHealth::alarmTitle()` (line 365) so `redrawTitles()` fixes open ones; a case
  in `old-titles.php`.

## The admin's test (each new state)

- Bar segment pressed: sees only those things; the heading says "3 failing a check"; pressing again or Show all, all.
- A failing row: reads why in one sentence, presses "Open the finding" or "See what LAYOUT found", sees the screenshot,
  presses Send to a build or Mark fixed; ← Back returns to the row.
- Awaiting sign-off row: presses Sign off; the dot fills, the counts move, Undo is beside the notice.
- No check yet: presses the chip; reads what building it needs and whether it's queued; presses Send to a build.
- Runtime, target not set: presses Approve the proposed target; the setup bar's second step fills.

## Next after review

The build (one release): `app/admin/control-console.php` (tv_cb_gauge_card, tv_cb_gauge_detail, the development panel, CSS),
`app/lib/ControlGauges.php` (the logbook's sign-offs), the board's script, `tools/page-audit/gauges.py` (routes, finding
links, the newest check results), the three queued fixes, `faq.md`; a rendered test (`gauge-panel.cjs`) that every row,
chip and segment in every gauge panel is a link or a button, at 1280 and 390.
