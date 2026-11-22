# The gauges' criteria, defined by the page audit (a spec for Jason's review)

Jason (2026-11-22), on past work and "reviewed": "Page audit, let's define this and determine if it completes the reviewed or
if we need a human review to complete." On proof and weights: "I think this is the page audit process."

This defines each criterion of a development gauge (`gauge-lifecycle.md` §4) by the step of the existing page audit that makes
its evidence: decisions/0145 (the audit and the release process), `site-audit/` (its phases, rules, scorecard, punch list and
proposals), the page review skill (`.claude/skills/page-review/`), `tools/page-audit/` and the release records
(`deliveries/releases/`, decisions/0159 §5). It's a spec; the mockup (`board/dev-gauges.html`) applies it.

## 1. Each criterion and the audit step behind it

| Criterion | The audit step that makes its evidence | Machine or human | The record it leaves |
|---|---|---|---|
| **decided** | A decision (`decisions/*.md`, Accepted), or a manifest section marked coming that an accepted decision covers | **human** (Jason accepts the decision) | the decision's Status line; `app/admin/manifests.php` |
| **built** | A release or zip: `tools/page-audit/send.py` → the zip check (`zipcheck.py`) → the release pipeline | **machine** | `deliveries/releases/<n>.json`, `deliveries/` (each file matched to live) |
| **checked** | The code checks R01–R15 (`audit.py` on the mirror after every sync; `zipcheck.py` on every zip), the rendered check at 1280 and 390 (`rendered.mjs`), and the paths check where a path runs through it (`paths.mjs`) | **machine** | `site-audit/scorecard.md`; `site-audit/results/rendered.json`, `paths.json` |
| **reviewed** | Phase 4's page review: Claude's verdicts (fix, amend, missing, conflict) on each finding, appended to `site-audit/proposals.md`; then **Jason's ruling** on each proposal, and his **UI, UX and Functional** approval on a release sent for review | the verdicts are **machine** (a Claude Code session); the ruling and the approval are **human** | `site-audit/proposals.md` (the "Decided (Jason, …)" lines); `results/findings.json` (open findings); the release log's "Review UI / UX / Functional: approved" lines |
| **live** | The live mirror after the sync, or a release record in state live | **machine** | the mirror (`degraffics/traverse-3way-sync`); `deliveries/releases/` |
| **proved** | The paths check on the live site (a visitor's whole task, end to end), or a function reading on real data (a runtime snapshot, the health report, the alarms) | **machine** | `results/paths.json`; the runtime snapshots |

## 2. Does the audit's review complete "reviewed"? No: decision 0145 needs Jason's sign-off

The page review is Claude's. Its verdicts are proposals, and the decisions that set up the audit give the approval to Jason:

- **decisions/0145 §5, the release process, lines 181–182 and 190:**
  > "1. **Review:** the audit runs and produces findings and drafted amendments.
  > 2. **Proposal:** findings are grouped into improvements. Jason approves, rejects or rewords them."

  > "It moves on only with no new failures and Jason's sign-off."
- **decisions/0145 §2, line 149:** "Nothing in a decision changes without Jason's approval."
- **site-audit/phase-4-journeys-and-review.md, line 51:** the proposals are "ready for Jason to approve, reject or reword."
- **.claude/skills/page-review/SKILL.md, lines 10 and 78:** the review "changes nothing on the site and no decision: it only
  appends to `site-audit/proposals.md`", and each proposal has "no **Decided** line: that's Jason's."
- **decisions/0159, the build order's note (lines 173–176):** "A push for review (Jason, 2026-11-13: 'this should be a push
  for review process so it can be something that triggers me having to go to the stage to interact with the site and then
  approve it as UI/UX/Functional and then publish it')".

So **the audit alone doesn't complete "reviewed"**: it completes once Jason has signed off.

A note on the release log's other line: 218 releases carry "Approved in chat before it was sent through git, and its checks on
staging passed: publishing by itself." The code writes it for every build that publishes itself (`Releases::autoPublish`,
`app/lib/Releases.php:749`), following 0159's note (lines 168–171: "this feels redundant, since I would have already approved
it in claude before it got pushed"). It records the rule, not a look at a page, so it isn't counted as a review here. It's
offered to Jason as an option (§3).

## 3. The recommendation

- **checked:** the audit's code, rendered and path checks pass at 1280 and 390. An item with a page needs its rendered check
  passing; an item with a path needs its path passing; an item with neither (a workflow, a library) needs the code checks,
  at 0 failures on the scorecard (2026-11-21).
- **reviewed:** the page review has **no open finding** for it (`results/findings.json`), **and** Jason has signed off, through
  his UI, UX and Functional approval on a release, or his ruling on that page's proposal in `site-audit/proposals.md`.
- **proved:** the path check (for a page or a flow) or a function reading (for a data job, a workflow or an integration) on
  live data.
- **Past work:** an item counts as reviewed where his sign-off is on record. The rest shows **"audited, awaiting sign-off"**,
  never "reviewed". This replaces the "accepted at sign-off" mark.
- **The other readings, offered as choices:**
  1. **Claude's review alone, with no open finding, completes "reviewed".** This departs from 0145 §5 ("Jason approves,
     rejects or rewords them"; "Jason's sign-off") and would need a note in 0145.
  2. **His chat approval of a build counts as sign-off** for what the build carried (the 218 releases above). It would need
     the same note: 0145 asks for his sign-off on what the audit found, which the chat approval doesn't record per page.

## 4. Proof: a page against a data job

decisions/0145 §1 separates the two:
- **A page or a flow is proved by its path:** "A path is what someone comes to do, run end to end in a browser… Each is
  measured for steps to the goal, dead ends, accuracy (what's shown is true and complete, what exists shows) and clarity."
- **A data job is proved by a function reading:** "Every function and tool is used on its own… and its result is checked",
  and "Data and integrations… calls, failures, throttling and cost… spend against each cap". On the board that's a runtime
  snapshot (W1's records in, a cap's spend, the alarms), read on live data.

## 5. Weights: keep 1

The audit's rankings rank **fixes**, not items: findings by "how many paths a finding blocks… then severity, then how many
paths it touches" (`SKILL.md` §4; `findings.py`), the scorecard by rule, the punch list by what Jason provides. None says
how much an item matters to a section's build. **Recommended: every item weighs 1.** The option: an item a core path runs
through (the paths check's core list, 0145 §1) weighs 2, so a gauge leans toward what visitors come to do.

## 6. Today, under the recommendation

Of the 292 in-scope items (the 16 gauges, Proposed left out):

| Criterion | Met today | Where its evidence comes from |
|---|---|---|
| decided | 292 | every in-scope item has a decision or a manifest section |
| built | 247 | live or held |
| checked | 197 | the rendered, path and code checks |
| reviewed | **14** | 18 items have Jason's sign-off on record: 8 from UI, UX and Functional approvals (#54, #55, #56, #58, #68) and 10 from his rulings in proposals.md (P2, P7, P9, P10, P11, P12). 4 of the 18 still have an open finding (Get Local, the Service register, Costs, the Workflow board), so they wait. |
| live | 239 | the mirror and the release records |
| proved | 7 | a passing path or function check |

**The preview % (if each draft were signed off), before → after the audit's criteria:**

| Gauge | Before | After | Stage of the work |
|---|---|---|---|
| Discovery | 44 | 57 | Phased Development |
| Directory | 51 | 63 | Phased Development |
| Community | 38 | 46 | Phased Development |
| Marketplace | 44 | 53 | Phased Development |
| TRVGO – Storefront | 41 | 52 | Phased Development |
| Accounts | 46 | 59 | Phased Development |
| Dashboard & Pulse | 49 | 65 | Audit & Review |
| Get Local 360° | 48 | 62 | Phased Development |
| Let's Explore 360° | 46 | 58 | Phased Development |
| Market 360° | 47 | 64 | Phased Development |
| Social 360° | 42 | 56 | Phased Development |
| Sales & Growth | 46 | 61 | Phased Development |
| White Glove Concierge | 23 | 27 | Phased Development |
| Back-Office | 43 | 53 | Phased Development |
| Insights & Research | 46 | 59 | Phased Development |
| System Configuration | 49 | 59 | Phased Development |

The rise is "checked": a page whose rendered check passes now meets it (the audit's own check), where before only a path or
function check did. "Proved" still needs a path or function check, so **a page that only renders isn't complete**.

## 7. For Jason

1. "Reviewed" as recommended (no open finding, and your sign-off on record), or Claude's review alone, or your chat approval?
2. Weights all 1, or 2 for items on a core path?
3. The 18 sign-offs mapped here: release B's parts and Start the system (#60–#69) carried several items each and aren't
   mapped; their owners can map them.
