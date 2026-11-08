# Phase 2: Feature switches, test accounts, rendered checks

Decision 0145 §1, §3, §6.

## Goal

The site can turn a feature on or off without an upload. Every kind of account can be checked in a real browser, at
phone and desktop size.

## What gets built

1. **Feature switches.** A small library on the site and a **Switches** page in System Configuration. Each switch has:
   - on or off;
   - who sees it: staff and test accounts, a share of members, or everyone;
   - a kill switch.

   New features from here on check their switch.
2. **The test-account flag.** Accounts marked as tests are:
   - left out of counts, likes, views, emails, digests and billing;
   - hidden from search, people lists and the public;
   - only able to pay in sandbox mode.

   Staff see a small "Test" badge on them.
3. **A test business listing**, hidden from the public, owned by the test owner, with a test manager.
4. **Rendered checks** in the GitHub Action, in a real browser (Playwright):
   - every page at 390px and desktop, signed out and as each test account;
   - console errors, failed requests, sideways scroll, card edges, account bar, stacked dialogs, accessibility basics;
   - screenshots kept with the run.
5. **UI and UX expectations on every page.** Not only the pages a journey passes through. Each page and each part of it
   is held to the standards (decision 0145 §1):
   - the shell, account bar, header, rail;
   - card anatomy, icons, phone edges;
   - search and filters;
   - notices, busy states, dialogs and status lines;
   - forms, uploads, the command strip on record pages;
   - wording and empty states.

   Each finding names the standard it misses.
6. **The tool inventory** (`site-audit/tools.md`). Built from the code, it lists every tool:
   - buttons, forms, switchers, filters, uploads;
   - endpoints;
   - admin modules' tools and sections;
   - Pulse steps;
   - scheduled jobs.

   Each entry says who may use it and what should happen. It shows which tools are checked and which are not yet.
7. **Function checks, for tools that only read** (search, filters, maps, peek, pages, exports to view):
   - used by each account type that may use them, with good, bad and empty input;
   - their answers compared with the inventory;
   - refused, greyed out with the reason, for each account type that may not.

   Tools that change something are checked in Phase 3, on staging.
8. **The health report:** a locked admin endpoint that returns only totals:
   - table sizes and row counts;
   - slow-query and error summaries;
   - service usage and cache rates.

   No personal data. The Action fetches it with the `HEALTH_TOKEN` secret.
9. **Endpoint permission checks:** every endpoint called as every account type. One that answers someone it shouldn't,
   or returns more than the page needs, fails.
10. **Staff sign-in for the checks.** The Action generates each staff test login's two-step code from its secret key.
   Nobody types a code.

## What Jason provides

1. **Approval** of this phase. Its site changes (switches, the test-account flag) are released as the first phased
   release, to test accounts first.
2. **The test accounts.** The full list is in [test-accounts.md](test-accounts.md): 11 member and business accounts,
   13 staff logins, and a test business, client and vendor. You don't create them by hand. Once the site part is live,
   press **Create test accounts** in System Configuration → Test accounts (Founder only).
3. **The secrets.** In this repository: Settings → Secrets and variables → Actions → New repository secret, one secret
   named `CHECK_ACCOUNTS`, pasted from the block the Test accounts page shows once. It holds each account's email or
   staff ID, its generated password, and for staff logins its two-step key. Nobody can read it back, and it never
   appears in code, chat or logs.
4. **Confirmed (Jason, 2026-11-08):** payments run in QuickBooks sandbox for the test client (decision 0099).

## Done when

- [ ] Switches work. A test switch turns a small change on for test accounts only, then off, with no upload.
- [ ] Test accounts don't appear in any count, email, search or bill. Checked on the live site.
- [ ] The rendered checks run for every account type and are in the scorecard with screenshots.
- [ ] Staff test logins sign in through the Action using generated two-step codes.
- [ ] Every page has its UI and UX expectations checked, with findings in the scorecard.
- [ ] The tool inventory lists every tool, and every tool that only reads is checked for each account type.

## Rollout and rollback

- **Site part:** released to test accounts first, then everyone.
- **Rollback:** the switches page turns everything off. The database changes are add-only, so the previous files still
  run.
- **Checks:** switched off in the Action.

## Cost

None. GitHub's free run time. Test payments are sandbox only.
