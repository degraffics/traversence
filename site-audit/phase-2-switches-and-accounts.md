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
5. **Staff sign-in for the checks.** The Action generates each staff test login's two-step code from its secret key.
   Nobody types a code.

## What Jason provides

1. **Approval** of this phase. Its site changes (switches, the test-account flag) are released as the first phased
   release, to test accounts first.
2. **The test accounts.** Create them after the site part is live. They should be addresses you control, for example
   `check+member@traversence.com` if your mailbox accepts "+" addresses.

   | Account | How |
   |---|---|
   | New member | Create account; leave it under 7 days old by recreating it monthly (the checks can do this) |
   | Established member | Create account; it becomes established after 7 days |
   | Business owner | Create account; I attach it to the test listing |
   | Business manager | Create account; invited to the test listing |
   | Contributor or steward | Create account; given the role in People & roles |
   | Staff: staff, manager, director, executive | Staff logins made in the staff admin, one per level, in a test department |
   | Vendor | A test vendor in the vendors list |
   | Client | A test client with a sandbox order |

   I mark each one as a test account once you tell me it exists.
3. **The secrets.** In this repository: Settings → Secrets and variables → Actions → New repository secret, one secret
   named `CHECK_ACCOUNTS`. It holds each account's email or staff ID, its password, and for staff logins the two-step
   secret key, shown when the login is set up as "can't scan?". I'll give you the exact layout to paste. Nobody can read
   it back, and it never appears in code or logs.
4. **Confirmation** that payments can run in sandbox for the test client (QuickBooks sandbox, decision 0099).

## Done when

- [ ] Switches work. A test switch turns a small change on for test accounts only, then off, with no upload.
- [ ] Test accounts don't appear in any count, email, search or bill. Checked on the live site.
- [ ] The rendered checks run for every account type and are in the scorecard with screenshots.
- [ ] Staff test logins sign in through the Action using generated two-step codes.

## Rollout and rollback

- **Site part:** released to test accounts first, then everyone.
- **Rollback:** the switches page turns everything off. The database changes are add-only, so the previous files still
  run.
- **Checks:** switched off in the Action.

## Cost

None. GitHub's free run time. Test payments are sandbox only.
