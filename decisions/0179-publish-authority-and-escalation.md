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

## Note (Jason, 2026-11-25): Piece 3's decisions

On `handoffs/piece3-check.md`'s four briefs, reviewed with Gemini: "DB1 A, DB2 A, DB3 A, DB4 A, Staff Views yes ... Lock
these in for Piece 3."
- **DB1:** "before" is staging just before the change, never live.
- **DB2:** a block withdraws the release itself and puts the failure in the Work queue.
- **DB3:** data fixes stay in the daily batch at first; a row limit comes later, Jason's choice.
- **DB4:** the check's GitHub Actions minutes have a daily budget; once it's spent, releases wait for the batch.
- **Staff Views:** an advocate bound to the staff test logins checks admin pages.

Piece 3's releases are not pre-approved: each goes out through the daily batch (rule 1). Jason presses staging's three
switches himself. Step 1 (Keep staging caught up) finishes its fresh copy before steps 2 and 3.

## Note (Jason with Gemini's review, 2026-11-25): Tier 2 publishes on its own once Piece 3 is live; amends rule 2

Jason chose A: "only Tier 3 stops for a Decision Brief". Once Piece 3's before-and-after check is live:
- **Tier 2** publishes after staging's checks and the before-and-after check pass, as the low-risk kinds already do. It
  appears in the daily summary with Undo and Roll back. Rule 2's "everything else still requires my approval" now means
  Tier 3 only.
- **Tier 3** always stops for a Decision Brief.
- **Until Piece 3 is live, rule 1 holds:** the daily batch.

Jason's own touchpoints are "+" for new work, Tier 3 briefs, and Undo or Roll back.

**What each ceiling unlocks, for when he raises it** (his Planning limit stands until then):
- **Building:** the Project Manager moves tickets by itself and takes the ones the audit raised. A ticket a person entered still
  waits for "Give it to the Team".
- **Implementation:** the Architect's build brief goes straight to a build session, which builds, tests and sends the release
  to staging's checks.
- **Confirmation:** Tier 1 and Tier 2 publish on their own after the before-and-after check.

The decision tree's fingerprint covers rules only, so a wording fix never clears the approval.

## Note (Jason, 2026-11-25): Piece 3's last two briefs

"A: A, B: B".
- **A:** a Publish of staging's own work isn't before-and-after checked. It stays under the daily batch, and is
  revisited before Jason raises agents to Confirmation.
- **B:** 0066 is proved by a locked, staging-only read of places live already holds, hashes only. No fake test
  landmark is added.
- **Also recorded (the session manager's call, from this decision's "safe additive"):** an add-only migration counts as
  safe additive. That's CREATE TABLE IF NOT EXISTS, or a guarded ADD COLUMN or INDEX. Any UPDATE, DELETE, DROP, MODIFY
  or RENAME stays Tier 3.

## Note (Jason, 2026-11-26): agents work on the backend first

Jason: "How can we get the agents active working on this without them pushing live file, except for the backend, so I
can see them work before we point them on the front end." And: "Admin does count for backend".
- **Backend** is the admin (every `/admin/` page), the site's internal code and the workers, where a change reaches
  no public page. **Front end** is any page a visitor, member or business sees. `tools/page-audit/reach.py` decides it
  from the live mirror.
- **The fence (P3-6):** when Jason raises agents to Implementation, a ticket whose change stays in the backend goes
  to a build session. One that reaches a public page stops at its build brief and waits for him. It's a setting on the
  Team page, "Backend only" or "Everywhere", that only the Founder changes, and it ships set to "Backend only". It's
  part of the decision tree's rules, so changing it clears the tree's approval.
- Rule 1 still holds: agents' backend releases wait for the batch until Piece 3 is live. Tier 3 is unchanged.
- The order stays the same: the batch (P3-4 brings Undo and the spending limit), then the decision tree's approval,
  then Building, then Implementation behind the fence.
- **Backend releases go live without the batch** (Jason, 2026-11-26: "Let the backen release go live. Thats what I will
  evaluate as seeing their work."):
  - Under Backend only, an agent's backend release publishes on its own once staging's checks and the before-and-after
    check pass, with Undo and Roll back in the daily summary. This replaces the line above that sends them through the
    batch.
  - Front-end tickets still stop at the brief, and Tier 3 still stops for a Decision Brief.
  - Raising the ceiling stays Jason's own press on the Team page (approve the decision tree, then Implementation).
- **The decision tree approved in chat** (Jason, 2026-11-26, about 03:30 UTC: "Approve tree", after reading
  `decision-tree.md` drawn from the live code at Sync 02:36 UTC). The approval that counts is his press of
  **Approve the decision tree** on the Team page with his Founder login, which ties it to the rules' fingerprint. No
  release in flight changes those rules.

## Note (Jason, 2026-11-26, 18:30 UTC): the before-and-after check paused for four releases

After it blocked its own fix four times on staging's own changing data, Jason chose A: "A".
- **Paused for four releases only:** ba-proof-fixes, switch-readback, the staff-mail fix and R1. All four are admin
  only, and none is built by an agent.
- **Each still gets** staging's own checks and Roll back.
- **Fixed in the meantime:** the check's last noise sources (admin to-do links appearing, a known slow page timing out
  once).
- **It switches back on** before any agent-built release, and before R2.
- No agent release publishes while it's off: Releases::agentGate already waits for a person when the check is off.

**Note (Jason, 2026-11-27: "Yes, route their questions through you"):** the line above now reads through the CEO
session. A build session's question for Jason goes to the session manager for review, then to the CEO session, which
puts it to him in its chat in the same form (written for him and Gemini, never a pop-up). Sessions work silently in
their own chats (CLAUDE.md, "Work silently" and "Asking Jason").

## Note (Jason, 2026-11-28): rollout on passing checks; amends rule 1

Jason, 2026-11-28, through the CEO: "Rollout can be upon release passing its checks." A release goes live once its checks pass: no daily batch wait and no one-slot-a-day limit. Unchanged: a high-risk area's Tier 4 review, and data changes that take content off the site (`data_reason` migrations, e.g. F9), which still go in his daily batch until he says otherwise. The before-and-after check is unchanged.

## Note (Jason, 2026-11-28): the before-and-after check looks only where the release's code runs; amends rule 2

Jason: "if every release checks every unaffected page the really slows down the process… This is an obvious blockage with
an obvious misconfigured workflow, so it should have been adjusted by middle manage on diagnosis", and "The found issue should
be passed to the audit and the specific issue should be addressed in a subsequent work queue fix." Built by the session
manager for the IT Manager (11) on the CEO's order (#330: blocked twice on `/admin/people.php`'s overlap at 390, a page no
changed code runs):
- **Where it looks:** `reach.py` walks a functions file the release changes only from the callers of the functions it
  changes (closed over the file's own calls). A change to the file's top-level code, a class or a script reaches every page
  that loads it, as before. Pause-1a-3's pages expected to change went from 148 to 65, and people.php dropped out.
- **What blocks:** only what the release made worse on those pages. Layout, data and drift found on any other page go to
  `site-audit/results/beforeafter/audit.json` (the daily audit) as a Work-queue fix for the owning lane, never a block.
- **Never weakened:** the access rule still blocks on every page it looks at: an account's reach, another account's
  email, an error on a view that worked, and 0066's held names.
- Tests: `tools/piece3-tests/reach_test.py` (5 new checks) and `beforeafter.test.mjs` (its cases for unreached pages now
  expect the audit).

## Note (Jason, 2026-11-28): the advocates leave the release path; amends rule 2

Jason: "The account advocates should also act like the audit, and independently operations vs attached to key deployment work." (1) The advocates come out of the release path: a release's before-and-after check is only the narrowed regression check (pages running the changed code, worse only; 0179's note of the same date). (2) The advocates run on their own schedule as an independent audit of the live site: each walks its own account type's paths (0180's appendix) at 1280 and 390, and each finding becomes a Work-queue ticket in the owning lane, its gate or harm named, in the daily audit and the daily report. (3) Advocates never block a release and no deployment session runs them; their tickets follow the normal tiers, a live harm escalating at once. Owners: IT Manager (11) and PM (8). The scheduled audit is the release line's build after the check change (batch-drafted).

## Note (Jason, 2026-11-28, 15:55 UTC): publish the moment the check passes

Jason: "Push to live should be done immediately, upon pass." The before-and-after check tells the site when its verdict is on main, and the site reads it and moves the release on then (a pass publishes), not at its next 10-minute run. It replaces the CEO's Tier 4 option (a) of 2026-11-27 (verdicts moved on by the job only). Never unchecked: only a pass on main publishes (`handoffs/publish-on-pass/README.md`).
