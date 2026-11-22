# The lifecycle of a development gauge (a spec for Jason's review)

Jason (2026-11-22): "the measurement of the gauge should be configured, so let's create a process that would be initially 0
and determine what is needed to make the gauge reach 100. That way we can see this from implementation of a new gauge through
configuration established everything to reach 100 and how it will continue to monitor it." And, on the band words: "Planning,
Phased Development, Audit & Review, Implementation, Monitoring."

**This replaces** the "Complete = live and passing its check" rule and options A, B and C for the development gauges (recorded
in 03 §27). The mockup is `board/dev-gauges.html` and the Board view's Development rows (`mockups/src/devgauges.py`). It's a
spec and a mockup: nothing is built.

## 1. One gauge, one measure

- **The needle is one number:** the share of the gauge's configured criteria that are met, weighted per item (default weight 1).
- **The word under the needle is a label,** never a second needle and never a second measure. Anything else (items by phase,
  criteria by kind, the regression log) is in the gauge's drawer.
- Build gauges use only Jason's five words. They never use the runtime words (Working, Less than optimal, Trip, Paused…),
  so the two kinds of gauge can't be confused. A check enforces it (`devgauges-check.cjs`).

## 2. The five words: the stage of the work (Jason's choice)

Jason gave the five words as the answer to the band question, and then chose how to read them (2026-11-22): **"(a) Stage of
the work".** The word under the needle is the gauge's stage, from its process state (§3), whatever the %.

**Rejected alternative (b), % bands:** 0–25 Planning, 25–50 Phased Development, 50–75 Audit & Review, 75–99 Implementation,
100 Monitoring. Kept here as the record; the mockup's switch between the two readings is removed.

## 3. The stages

A gauge's stage comes from its process state, never from fixed % bands.

| Stage | Entry | Exit | What the gauge shows |
|---|---|---|---|
| **Planning** | A new gauge is created. It starts at **0**. | Jason signs off the configuration ("what 100 means"). His sign-off is written to the Control Board's logbook with its date. | 0 · "Planning" · "configuration drafted: N items, M criteria, awaiting sign-off" (or "no configuration yet") |
| **Phased Development** | Sign-off, while any in-scope item isn't built. | Every item meets "built". | criteria met / configured · "Phased Development" |
| **Audit & Review** | Every item is built, while any item lacks its check or its review. **Audit & Review checks the work before it goes live:** staging, the checks (path or function), the review. | Every item meets "checked" and "reviewed". | the % · "Audit & Review" |
| **Implementation** | Checked and reviewed, while any item isn't live or isn't proved. **Implementation is putting it live and proving it on real data.** | Every item meets "live" and "proved". | the % · "Implementation" |
| **Monitoring** | 100: every configured criterion met. | A regression: a criterion that stops holding. The needle drops, the stage moves back to where that criterion belongs, with its reason, and the drop is logged. | 100 · "Monitoring" · the last time every criterion was checked |

**The rule, in order:** not signed off → Planning; else any item not built → Phased Development; else any item without its
check or review → Audit & Review; else any item not live or not proved → Implementation; else Monitoring. A gauge sits at the
earliest stage any of its items is still in.

## 4. The configuration of a gauge

- **Scope:** the items it answers for (from `inventory.json`'s 16-gauge ownership). Items waiting on Jason's own definition
  (Proposed) stay out until he defines them.
- **Each item's definition of done:** a list of criteria. The default list, in the lifecycle's order:
  **decided · built · checked (a path or function check) · reviewed · live · proved on real data (a function check)**.
  An item can drop or add criteria (a page with no data to prove drops "proved"; a payment adds "a test order passes").
- **Phases:** each item sits in a phase. The draft puts what's live in Phase 1, what's built and held in Phase 2, and what's
  decided and not built in Phase 3.
- **Weights:** 1 by default; Jason may weight a core feature higher.
- **The %** = the weighted criteria met ÷ the weighted criteria configured.

### The first draft, filled from the inventory

| What the inventory knows | Criteria met in the draft |
|---|---|
| status Decided, not built | decided |
| status Built, held | decided, built |
| status Live | decided, built, live |
| the audit's code, rendered and path checks passing | + checked |
| a passing function check | + proved |
| a review | **reviewed**: no open finding, and Jason's sign-off on record (`gauge-criteria-from-audit.md`) |
| a render-only pass | checked, but not proved: a page that only renders isn't complete |

### The criteria come from the page audit

Each criterion is defined by the step of the page audit that makes its evidence (Jason, 2026-11-22: "Page audit… I think this
is the page audit process"): **`gauge-criteria-from-audit.md`**. In short: checked is the audit's code, rendered and path
checks; reviewed is no open finding **and** Jason's sign-off on record (his UI, UX and Functional approval on a release, or
his ruling on the page's proposal), as decisions/0145 §5 requires; proved is the path or function check on live data. Past
work without his sign-off shows "audited, awaiting sign-off". This replaces the "accepted at sign-off" mark.

- **One item not yet built keeps the whole gauge in Phased Development; the needle still shows progress.**

## 5. Where the configuration lives (a proposal)

- **On the gauge:** the gauge holds its configuration. **Configure** opens from the gauge in a drawer on the board, on its
  Configuration tab. The drawer's tabs are **Configuration** (items, criteria, phases, weights, and Sign off (Jason)),
  **Progress** (criteria met, by phase) and **Monitoring** (the regression log; "No history yet").
- **The list of every gauge:** one admin section, **System Configuration → Gauges** (`config/gauges` in `manifests.php`),
  built from the table widget: each gauge, its stage, its %, its scope size, its sign-off date, the last change.
- **Sign-off:** Jason's sign-off of each scope goes in the **logbook**, with its date.
- **A scope change after sign-off** needs his OK again, and **shows on the gauge** ("scope changed: +3 items, awaiting OK"),
  so the denominator never moves quietly. Until he OKs it, the needle keeps the signed scope.

## 6. Today, in the mockup

Every gauge is in **Planning at 0**, its configuration drafted from the inventory and awaiting sign-off. **"Preview with the
draft configuration"** shows what each would read if the draft were signed off, with the criteria from the page audit:
Discovery 57%, Directory 63%, Community 46%, Marketplace 53%, TRVGO – Storefront 52%, Accounts 59%, Dashboard & Pulse 65%,
Get Local 360° 62%, Let's Explore 360° 58%, Market 360° 64%, Social 360° 56%, Sales & Growth 61%, White Glove Concierge 27%,
Back-Office 53%, Insights & Research 59%, System Configuration 59%. All are in Phased Development but Dashboard & Pulse
(Audit & Review: every item built, few reviewed). The nine placements Jason marked "Needs changes" stay where they are,
pending Jason, under the Questions pill.

## 7. Runtime gauges under the lifecycle (approved by Jason, 2026-11-22)

Jason approved the runtime lifecycle: "Approve". Every runtime snapshot gauge on the Board view now follows it:
- **Its setup:** the probe built, its target set, its check passing, its alarm wired to the board (its reading comes from the
  alarms). Configure (on each card) opens the setup.
- **Planning to Implementation:** the needle is its setup %, with its stage under it; the live reading it has stays on the
  card, marked live.
- **At Monitoring:** the needle switches to the live reading against its target; setup shows only in Configure.
- **The stage:** Planning with none of the four; Phased Development while the probe isn't built; Audit & Review while its
  target isn't set or its check isn't passing; Implementation while its alarm isn't wired; Monitoring with all four.
- **Today (the 23 runtime gauges):** 1 at Monitoring (Trips reach a person), 1 in Implementation (Sign-in works end to end),
  13 in Audit & Review, 4 in Phased Development, 4 in Planning (Pages load fast, Email is delivered, Within our means: web
  search, The system recovers by itself).
- **The strip on the Development page** shows Pages load fast across all five stages: Planning is today, the other four are
  illustrations (Monitoring's reading is a scenario).

## 8. Open questions

1. **Reviewed:** as recommended in `gauge-criteria-from-audit.md` (no open finding and your sign-off on record), or Claude's
   review alone, or your chat approval of each build?
2. **Weights:** all 1, or 2 for items on a core path?
3. **Release B and Start the system:** their approvals (#60–#69) don't map to one item each. Should their owners map them?
4. **The nine placements:** "Needs changes"; they wait for your detail.
