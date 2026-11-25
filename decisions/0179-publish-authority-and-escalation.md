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
