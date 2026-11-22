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

## 2. The five words: our inference, and two readings

Jason gave the five words as the answer to the band question. **Reading them as stages of a process is our inference.** So the
mockup shows both readings on one gauge, and Jason chooses:
- **(a) Stages of the process:** the word comes from the process state (§3), whatever the %.
- **(b) % bands:** 0–25 Planning, 25–50 Phased Development, 50–75 Audit & Review, 75–99 Implementation, 100 Monitoring
  (proposed numbers).

The rest of this spec is written for reading (a). Under (b), §3's rules still decide what the gauge's drawer says, but the
word follows the bands.

## 3. The stages (reading (a))

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
| a passing path or function check | + checked |
| a passing function check | + proved |
| a review | **reviewed**: Jason's UI, UX and Functional approvals where a release record holds them, or the session manager's recorded review (below) |
| a render-only pass | nothing more: a page that only renders isn't checked |

### Reviewed, and "accepted at sign-off"

- **Reviewed** means Jason's UI, UX and Functional approvals where a release record holds them (the log's "Review UI / UX /
  Functional: approved" lines in `deliveries/releases/`), or the session manager's recorded review.
- **The release records were rechecked:** 11 releases hold all three approvals (#54, #55, #56, #58, #60, #63, #64, #66, #67,
  #68, #69). Where what a release carried maps plainly to an item, that item is **reviewed**: 8 items (data cleanup, both
  faces; the duplicates fix; Modules (Service register), Costs and the Workflow board, #55; Staging, #56; the Stock library,
  #68). Release B's parts (#60–#67) and Start the system (#69) don't map to one item each; they're left for the owner to map.
- **Accepted at sign-off:** past work that's live with no recorded review carries a separate mark, "accepted at sign-off",
  shown in the drill-down. **It counts only if Jason says so.** His choice, at sign-off:
  1. **Count it:** every item marked "accepted at sign-off" meets "reviewed".
  2. **Don't count it:** it stays unmet until a review is recorded.
  3. **Review item by item:** each is reviewed in turn, its approval recorded, before it counts.
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
draft configuration"** shows what each would read if the draft were signed off (reading (a); the reading switch shows (b)).

| Gauge | Items · criteria | Preview % | Preview stage (a) | Preview band (b) |
|---|---|---|---|---|
| Discovery | 30 · 180 | 44% | Phased Development | Phased Development |
| Directory | 22 · 132 | 51% | Phased Development | Audit & Review |
| Community | 22 · 132 | 38% | Phased Development | Phased Development |
| Marketplace | 31 · 186 | 44% | Phased Development | Phased Development |
| TRVGO – Storefront | 9 · 54 | 41% | Phased Development | Phased Development |
| Accounts | 17 · 102 | 46% | Phased Development | Phased Development |
| Dashboard & Pulse | 13 · 78 | 49% | Audit & Review | Phased Development |
| Get Local 360° | 15 · 90 | 48% | Phased Development | Phased Development |
| Let's Explore 360° | 8 · 48 | 46% | Phased Development | Phased Development |
| Market 360° | 12 · 72 | 47% | Phased Development | Phased Development |
| Social 360° | 8 · 48 | 42% | Phased Development | Phased Development |
| Sales & Growth | 9 · 54 | 46% | Phased Development | Phased Development |
| White Glove Concierge | 5 · 30 | 23% | Phased Development | Planning |
| Back-Office | 25 · 150 | 43% | Phased Development | Phased Development |
| Insights & Research | 13 · 78 | 46% | Phased Development | Phased Development |
| System Configuration | 53 · 318 | 49% | Phased Development | Phased Development |

Fifteen preview in Phased Development because each has at least one decided item not built. Dashboard & Pulse has every
item built, so it's in Audit & Review: none of its items is reviewed yet, and most have only a render check. The nine placements Jason
marked "Needs changes" stay where they are, pending Jason, under the Questions pill.

## 7. Runtime gauges under the lifecycle (a proposal; not applied to the runtime gauges yet)

One runtime gauge, "Pages load fast", is drawn both ways in the mockup's "Runtime gauges under the lifecycle (proposal)" strip:
- **Planning to Implementation:** the needle is its **setup %**, what's configured toward its check: the probe built, its
  target set by Jason (about 2.5 s, 99.5% of the time; des), its check running on its clock and passing, its alarm wired to
  the board. Today: 0 of 4.
- **At Monitoring:** the needle switches to **the live reading against its target** (drawn as an illustration: 99.1% of
  pages under 2.5 s, against 99.5%; scenario). Setup then shows only in Configure.
- One needle at a time. It's a proposal and isn't applied to the runtime gauges yet.

## 8. Open questions

1. **The two readings:** stages of the process (a) or % bands (b)?
2. **Accepted at sign-off:** count it, don't count it, or review item by item (§4)?
3. **Release B and Start the system:** their approvals (#60–#69) don't map to one item each. Should their owners map them,
   or do they stay unmapped?
4. **Proved for pages:** does every page need a function check ("does its job on real data"), or does a path check prove a
   page? The draft asks both of every item.
5. **Weights:** all 1, or core features weighted (the outline's "a page 1, a core feature 3")?
6. **Runtime gauges too?** Should this lifecycle also govern the runtime snapshot gauges (page speed and the rest)? The
   recommendation is yes, one gauge model for both. It isn't applied to them yet.
7. **The nine placements:** Jason answered "Needs changes"; they wait for his detail.
