# 0179: Publish authority and escalation: what the team finishes alone, and what comes to Jason

Status: **accepted** (Jason, 2026-11-25, in chat, after a review with Gemini; written by the session manager).

Builds on:
- 0159 §5 and 0165: releases go through git onto staging, are checked there, and publish themselves once the checks pass.
- 0145: the site audit and the test accounts (§3).
- 0066: tribal nations' land and sensitive sites.

Amends (each a **Note for Jason** at its place):
- 0159 §5 and CLAUDE.md "Every build is a release": approval per release, in chat, becomes approval of a **daily batch**
  (rule 1 below).
- CLAUDE.md "Each session asks Jason itself": questions are written in the reply for Jason (and Gemini) to review, not
  put in a pop-up (Jason, 2026-11-25: "dont pop up questions. write your response and question so gemini and I can
  review"). The session manager's review before asking is kept.

## Why

Jason, 2026-11-25: "We need our platform to be more autonomous", with the team inside the platform able to do its jobs
before outside agents are used, and testing, development and management done "without harm to our UI/UX front end,
backend and databases". Today every release waits for his OK in chat, so nothing can be autonomous until the line is
written down.

## The decision (Jason's words, 2026-11-25)

1. **Interim rule, now (Option B):** "Nothing that requires approval goes live until I review the daily summary and
   approve the batch."
2. **Target rule, once the before-and-after checks are built (Option A):** "Low-risk items (wording, FAQ, tests, data
   fixes, mobile-layout tweaks, and safe additive changes) publish automatically after passing staging and
   before-and-after checks, and appear in a daily summary with Undo on each item. Everything else still requires my
   approval or Tier 3 decision."
3. **Always Tier 3 (Founder only):**
   - money, billing, refunds above the threshold, and contracts;
   - deleting or reshaping major data, and changing public addresses;
   - tribal nations' land and sensitive sites (0066), privacy, and the mission's boundaries;
   - real deadlocks between valid priorities.

   These arrive as a **Decision Brief**: (1) The Decision, (2) Why It Needs You, (3) Options A and B with pros, cons and
   a recommendation, (4) Impact if Delayed, with the cost when there is one.
4. A resolved Tier 3 brief becomes standing precedent, as a note in the decision it touches (or a new decision) plus a
   CLAUDE.md line where it changes how sessions work. There is no second rule book.

## How it works

- **The daily summary (rule 1):**
  - It lists every release waiting to go live: what it changes, why, the decision it serves, its checks on staging, and
    its way back.
  - Until the summary is a page in the platform (piece 2), the session manager writes it in its chat once a day, and
    Jason approves the batch in reply.
  - Releases are built and tested meanwhile. They are not sent to `releases/inbox/` until the batch is approved.
  - **Not affected:**
    - work Jason asks for directly ("push anything outstanding into the task queue");
    - urgent fixes for live harm (0165's urgent path, with a reason, and checked straight after).
- **Rule 2 switches on** only when piece 3's before-and-after check exists: the advocates' views (the test accounts)
  check every page a change touches, before and after, on staging, and stop anything that makes a page worse. The
  low-risk list may grow only by Jason's word, recorded here.
- **"Safe additive"** means it adds a file, column, table, setting or entry and removes or changes nothing a live caller
  uses, as the zip check (R-rules and `--drops`) already proves.
- **Who classifies:** the release's `release.json` names its kind, and the zip check verifies that the files match it.
  A release that is not clearly low-risk counts as needing approval.

## What comes next (recorded in `handoffs/decided-not-live.md`)

- **Piece 2:** the Team roster, the three ticket types (Work, Audit/Diagnostic, Resolution) and the agent runner, on
  staging only.
- **Piece 3:** the before-and-after check, Undo on every agent action, and a spending limit per agent. Rule 2 waits for
  it.

## Notes (Jason, 2026-11-25, confirming Piece 2's design points 1–4)

- **The paid AI cap:** "When the $40 paid AI cap is reached, pause paid external calls cleanly, fall back to
  internal/deterministic work, and raise a Tier 3 Decision Brief only if paid AI is strictly needed to move forward."
- **Gemini:** it stays on public material as the safe default. A ruling on internal text to the paid key (0164 §0.8)
  comes to Jason as a clear brief when it's needed.
- **Urgent fixes for live harm** still honour 0066 and the privacy guardrails. No exception path skips them.

## Note (Jason, 2026-11-25): Piece 2 approved and pre-approved to publish

Piece 2's design (`handoffs/piece2-team.md`) approved with Q1 A, Q2 A, Q3 A, Q4 B, Q5 B. Its releases P2-1 to P2-5 are
pre-approved: each publishes once its staging checks pass, "as long as we can roll back if there are issues", with a
summary emailed after. The build session may switch on staging's runner switches. Over the holiday the session manager
makes judgement calls "based on the adr's and the outlined plan"; Tier 3 still goes to Jason, by email.

## Note (Jason with Gemini's review, 2026-11-25): how the Team's capacity grows

"Proceed with the build on staging under these rules."
1. **Tier 3 is detected by what a change or finding touches:** files, tables, endpoints, routes and protected areas
   (money, major data, public addresses, 0066, privacy, mission, deadlocks). Word lists are only a secondary flag for a
   person to check, never an escalation by themselves.
2. **The decision tree's approval is tied to a fingerprint of its rules** (tiers, protected areas, role permissions),
   not its wording: a wording fix keeps the approval, any rule change clears it and lowers the ceiling to Planning.
3. **Stages open in order, each on evidence the Founder reviews:**
   - **Building:** Planning's suggestions prove accurate, staging isolation holds, and every action is logged with Undo.
   - **Implementation:** Building's outputs pass their checks, the agents' permission check is active (never a test
     account, never Founder by default), and the holds on outside calls, payments and email stay intact.
   - **Confirmation and safe publish:** staging's checks and Piece 3's before-and-after check pass, only the low-risk
     kinds publish alone, and the daily summary with Undo works end to end.
4. **Piece 3 blocks a change** when an advocate's view shows any of these after it:
   - an access or privacy leak, or lost access or errors on a valid view (0066 included);
   - a layout or phone regression (overlap, clipping, sideways scroll, broken controls or navigation, missing primary
     content or buttons);
   - missing data or broken core flows;
   - out-of-scope drift without a reason.
