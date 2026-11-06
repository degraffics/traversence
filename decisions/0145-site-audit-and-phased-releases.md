# ADR 0145: The Site Audit and Phased Releases

**Status:** Accepted (Jason, 2026-11-06). The work is in six phases, each with its own file in `site-audit/`.

## Why

Most of the problems found on 2026-11-05 broke a rule that was already written down, and each was found by Jason after
upload, not before:
- a zip dropped `Engage::liked()`, so content pages failed for signed-in members;
- the home page had no session or account bar;
- sign-out and sign-in failed silently;
- the place picker stacked as a second pop-up;
- Stories & guides read an old table;
- a file in a zip was never uploaded.

Jason asked for a tool that reviews every page against the decisions and governing docs, keeps the whole site's scope in
check, raises the UI and UX practices that are failing, and improves the rules themselves. Every change it leads to
should be vetted on a copy of the site, rolled out in phases, and able to be rolled back.

## 1. The audit

Three layers, run on every page, for every kind of account:

- **Code checks**, on the live mirror (`degraffics/traverse-3way-sync`) after every sync, and on every zip before it
  is sent. Each check comes from a written rule and links back to it. Examples:
  - calls to functions that no longer exist;
  - pages without the shared shell or session;
  - browser dialogs;
  - actions with no busy state or answer;
  - uploads outside the one uploader;
  - PHP newer than 8.0;
  - FAQ links to missing entries;
  - pages not in `routes.md` or `archive.md`;
  - migrations not safe to run twice.
- **Rendered checks**, in a real browser on GitHub's runners (this session's network can't reach the site): every page
  at 390px and desktop, signed out and signed in as each test account. They look for:
  - console errors, failed requests and sideways scroll;
  - card edges, account bar, stacked dialogs and accessibility basics;
  - screenshots, kept with each run.
- **Journeys and review.** Journeys are what people come to do, run end to end:
  - each is measured for steps to the goal, dead ends, accuracy (what's shown is true and complete, what exists shows)
    and clarity;
  - a review in Claude Code sessions (no cost to Traversence) reads the flagged pages, their screenshots and the
    decisions that govern them.

## 2. It improves the rules, not only checks them

Every finding gets a verdict:
- **The page breaks a rule:** fix the page.
- **The page does better than the rule, or the rule no longer fits:** an amendment to the decision or `CLAUDE.md` is
  drafted, with the evidence.
- **No rule covers it, or two rules disagree:** a missing decision, or a conflict to settle.

Nothing in a decision changes without Jason's approval. Once he approves an amendment, the matching check changes with
it.

## 3. Every kind of account

The checks sign in as:
- a guest and a new member;
- an established member;
- a business owner and a manager;
- a contributor and a steward;
- staff at each level, using the two-step code generated from the login's secret;
- a vendor and a client.

Test accounts are marked as tests:
- left out of counts, emails and billing;
- hidden from search and the public;
- tied to a test listing and sandbox payments only.

Their sign-ins live only in GitHub Actions secrets, never in code.

## 4. Git is the record of status

- **The mirror** is what's live.
- **The delivery and release log** (`deliveries/`) is what was built, what it was built on, and whether every file later
  matched the live copy. A missed file shows as pending until it does.
- **The scorecard** (`site-audit/scorecard.md`) is each page's health and its open findings.

Deploying from git, so the server follows git instead of git recording the server, is Phase 6. That is a separate choice
for Jason.

## 5. The release process

1. **Review:** the audit runs and produces findings and drafted amendments.
2. **Proposal:** findings are grouped into improvements. Jason approves, rejects or rewords them.
3. **Plan:** approved improvements become a release plan, which Jason approves with a date:
   - its files and migrations;
   - its switch;
   - its checks;
   - its rollback.
4. **Build:** from the live files, with the zip check on every file.
5. **Simulate and vet:** the release goes to the staging copy of the site. The full audit and every journey run there,
   for every account type. It moves on only with no new failures and Jason's sign-off.
6. **Go live, scheduled or on publish:** at its date, or when Jason presses **Publish**. Neither is possible until
   steps 3 and 5 have passed.
7. **Phased steps:**
   - the steps are staff and test accounts, then a share of members, then everyone;
   - each step advances either on schedule (only if the audit at the current step is clean) or on **Publish next
     step**;
   - a failed check pauses the rollout and alerts Jason.
8. **Rollback:** always on demand and immediate:
   - **Turn off** flips the feature's switch for everyone;
   - **Roll back release** returns its files to the previous version.
9. **Record:** each release is written into git: plan, files, checks, results, kept or rolled back.

**Urgent fixes** can be published straight away with a reason, then recorded and checked like any release.

## 6. Rules the release process needs

- **Feature switches.** Every new feature checks its switch (on, off, who sees it). A switch is a kill switch without
  re-uploading anything.
- **Add-only database changes within a release.** New tables and columns only; never a drop or rename in the same
  release. Removals come in a later release once the first has proven itself. The previous version always runs on the
  new database, so a rollback never loses data.
- **Staging is a full copy** with its own database:
  - refreshed from live with personal details stripped;
  - email held back;
  - payments in sandbox;
  - not indexed and behind a password.
- **No income, no expense.** Everything runs free:
  - GitHub's free run time;
  - the existing Bluehost plan for staging;
  - Claude Code sessions for the review.

  If the run time ever exceeds the free allowance, the checks run less often rather than adding a cost.

## 7. Phases

| Phase | What | File |
|---|---|---|
| 1 | Code checks, zip check, delivery and release log, scorecard | `site-audit/phase-1-foundations.md` |
| 2 | Feature switches, test-account flag, test accounts for every role, rendered checks | `site-audit/phase-2-switches-and-accounts.md` |
| 3 | Staging copy of the site; the audit on staging and live | `site-audit/phase-3-staging.md` |
| 4 | Journeys, the review, drafted amendments, the Pulse "Site health" case | `site-audit/phase-4-journeys-and-review.md` |
| 5 | Planned releases: plan, publish or schedule, phased steps, pause, turn off, roll back | `site-audit/phase-5-releases.md` |
| 6 | Deploy from git (optional; Jason decides when) | `site-audit/phase-6-deploy-from-git.md` |

What Jason provides for each phase is listed in its file and collected in `site-audit/README.md`. From Phase 2 on, each
phase is itself released through the process above, as far as the earlier phases allow.
