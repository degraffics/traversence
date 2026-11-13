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

Five layers, run on every page, for every kind of account:

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
- **Function and tool checks** (Jason, 2026-11-06: "determine if the functions and tools are working properly too").
  Every function and tool is used on its own, not only where a journey passes through it, and its result is checked.
  - **What counts as a tool:** every button, form, switcher, filter, upload, endpoint (`/api/`, `/admin/api/`), admin
    module tool and section (`app/admin/manifests.php`, `tools.php`), Pulse step, scheduled job, and migration.
  - **The tool inventory** (`site-audit/tools.md`) is built from the code. Each entry lists who may use it and what
    should happen: the response, what changes in the data, what the person sees (busy, then the notice), and what a
    refusal looks like for someone not allowed.
  - **How each is checked:** used by each account type that may use it, and refused, greyed out with the reason, for
    each that may not; with good input, bad input and empty input; and its result compared with what the inventory
    says. A tool that does nothing visible, answers wrongly, changes the wrong thing, or lets in someone it shouldn't
    fails.
  - **Where:**
    - tools that only read are checked on live;
    - tools that change anything (save, post, pay, delete, send) on staging (Phase 3), and on live only through the
      test accounts with their own test records, cleaned up after.
- **UI and UX expectations on every page**, whether or not a journey passes through it. Each page and each part of it
  is held to the standards in `CLAUDE.md`, `brand.md`, decision 0058 and `ux-review.md`:
  - the shell, account bar, header, rail;
  - card anatomy, icons, phone edges;
  - search and filters;
  - notices, busy states, dialogs and status lines;
  - forms (spell check, password boxes, lists with ⓘ), the one uploader, the command strip on record pages;
  - plain wording, one clear next action, empty states that say what to do next, consistency with the same thing
    elsewhere on the site.

  Most of these are rendered checks; the rest go to the review.
- **Data and integrations** (Jason, 2026-11-06: "APIs and databases … used correctly and effectively").
  - **Our database:**
    - code against schema: every table and column used exists, in the right database (`Database::t`, decision 0110);
      tables, columns and indexes nothing uses are listed for removal;
    - speed: the queries pages run are timed on staging and their query plans read (`EXPLAIN`); full scans, missing
      indexes and one-query-per-row patterns are flagged; the slowest queries and pages are listed;
    - data health: orphans, duplicates, impossible values, empty required fields; retention rules actually running
      (like records, guest connections); each table's size and growth;
    - privacy: personal details only where the privacy decisions put them.
  - **Our own APIs (endpoints):**
    - the basics: method, security token, permissions and input checks, and one answer format;
    - called as every account type; one that answers someone it shouldn't, or returns more than the page needs, fails;
    - error rates and response times from the server's logs; endpoints nothing calls are listed for removal.
  - **Outside services** (Claude, Tavily, Google Places, Wikimedia, QuickBooks, Stripe, Square, SES, Name.com, the
    weather and road services, Cloudflare):
    - calls, failures, throttling and cost per service, and cost per useful result (per listing found, per guide);
    - caching hit rates, retries that back off, sandbox and live keys not mixed;
    - licence and terms followed (decision 0133's source register), and "no income, no expense" for every paid call;
    - spend against each cap, from the existing Services & usage job.
  - **The health report:** a locked admin endpoint returns only totals: table sizes and row counts, slow-query and
    error summaries, service usage and cache rates. No personal data. GitHub's check run fetches it with a token kept as
    a secret. Speed and query-plan tests run on staging, never heavy on live.
- **Paths and review.** A path is what someone comes to do, run end to end in a browser. They're called paths so they
  aren't confused with Journeys, the content people write about their trips.
  - **Paths come from the system, not a hand-picked list** (Jason, 2026-11-06):
    - **Guide paths:** for every system-written guide (a city such as Show Low, AZ), and for the suggested journey
      built from it (decision 0146), a visitor follows the route. Each stop's page loads; its facts are accurate (open,
      phone, distance); directions and links work; the route makes sense. The list grows as guides do.
    - **Core paths:** a short fixed list for what every visitor or member does: sign up, sign in and out, search
      (including a typo), like and comment, claim a business, edit a listing, post on the Marketplace, message a
      business, and the staff tasks.
  - Each is measured for steps to the goal, dead ends, accuracy (what's shown is true and complete, what exists shows)
    and clarity;
  - a review in Claude Code sessions (no cost to Traversence) reads the flagged pages, their screenshots and the
    decisions that govern them.

## 1a. It keeps the decisions and the page tree (Jason, 2026-11-06)

The audit doesn't only read the decisions and the pages. It keeps both up to date.

- **The decision register** (`decisions/index.md`, generated and checked on every run). For each decision it records:
  - its number, title and status (accepted, amended, replaced);
  - what replaced it, or what it replaces;
  - the pages and tools it governs;
  - its open findings;
  - when it was last checked against the site.

  Every run flags:
  - decisions no page follows any more (stale);
  - decisions that contradict each other (conflict);
  - pages and tools no decision covers (missing);
  - a later decision that changed an earlier one without a note on the earlier one.

  Drafted amendments and new decisions go to Jason (§2). Once he approves, the register, the README's decision count and
  the checks are updated together.
- **The site page tree** (`routes.md`, generated from the live mirror and checked on every run). Every page appears
  with:
  - its address and its status: live, archived, or removed (with `archive.md`);
  - who may see it (which account types);
  - its governing decisions;
  - its tools and its journeys.

  Every run flags:
  - a page on the server that's not in the tree (unlisted);
  - a page in the tree that's gone (missing);
  - an archived page still in a menu;
  - a removed page still reachable;
  - menus and rails linking to pages that don't exist.

  A release that adds, moves or removes a page updates the tree in the same release.

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

**Who may publish, schedule, turn off and roll back is role-based** (Jason, 2026-11-08), like every admin action.
- **The four permissions:** `releases.publish`, `releases.schedule`, `releases.turn_off` and `releases.rollback`. Each
  is a rule in System Configuration → Permissions (the minimum level and departments, `Access::can`).
- **Default:** Founder only.
- **Granting:** once a role is given one, everyone with that role has it. Adding a staff member only assigns their
  role; nobody is asked again per person.

**Urgent fixes** can be published straight away with a reason, then recorded and checked like any release.

## 6. Rules the release process needs

- **Feature switches.** Every new feature checks its switch (on, off, who sees it). A switch is a kill switch without
  re-uploading anything.
- **Add-only database changes within a release.** New tables and columns only; never a drop or rename in the same
  release. Removals come in a later release once the first has proven itself. The previous version always runs on the
  new database, so a rollback never loses data.
- **Staging is a full copy** at `staging.trvgo.us` (Jason, 2026-11-06), with its own database:
  - refreshed from live with personal details stripped;
  - email held back;
  - payments in sandbox;
  - not indexed and behind a password.
- **No income, no expense.** Everything runs free:
  - GitHub's free run time;
  - the existing Bluehost plan for staging;
  - Claude Code sessions for the review.

  If the run time ever exceeds the free allowance, the checks run less often rather than adding a cost.
- **Building and reviewing happen in Claude Code sessions, not batches** (Jason, 2026-11-06).
  - **Builds:** a build reads the live files, runs the checks, tests, fixes and re-checks; a batch can't. Independent
    parts of a release can run as parallel agents inside one session.
  - **Batch reviews:** Claude's Message Batches API (half price, paid per use) fits large, simple reviews, such as the
    wording of every page or FAQ answer against the brand guide. It is off unless Jason approves a budget for it.
  - **Claude inside GitHub's check runs** is also paid per use, and is left out until the free version has proven
    itself.

**Note (Jason, 2026-11-13):** a release's files go up on System Configuration → Releases, not by FTP. The server
puts them on staging first, publishes them, and keeps the previous copy of every file, so Roll back is one tap. The page
also offers the release's zip and its rollback zip, for an upload by FTP instead. Built in releases_20261113
(site-audit/phase-5-releases.md, "Built").

**Note (Jason, 2026-11-13): how releases are run.**
1. **Every push is a release**, small fixes included, unless a system issue needs immediate attention: then **Publish
   now (urgent)** with its reason, checked straight after.
2. **Jason looks before sign-off**, for now: Sign it off needs the release opened on staging since it was last put there
   (Open on staging, or the staging bar on it).
3. **What else pauses a rollout** (member reports and support emails about its pages): to answer once explained.
4. **The share at step 2** waits until there are members. Until then a release goes from staff and test accounts to
   everyone in effect.
Open on staging signs Jason in as himself; the test accounts stay the audit's.

## 7. Phases

**One build** (Jason, 2026-11-06): all of it is built together, from one punch list (`site-audit/punch-list.md`). It is
still rolled out in the phase order, each part behind its own switch, because each phase relies on the one before.
Phase 6 is included only if Jason says yes.

| Phase | What | File |
|---|---|---|
| 1 | Code checks, zip check, delivery and release log, scorecard, the decision register and the page tree | `site-audit/phase-1-foundations.md` |
| 2 | Feature switches, test-account flag, test accounts for every role, rendered checks, UI and UX expectations on every page, the tool inventory, and checks of tools that only read | `site-audit/phase-2-switches-and-accounts.md` |
| 3 | Staging copy of the site at `staging.trvgo.us`; the audit on staging and live; checks of every tool that changes something | `site-audit/phase-3-staging.md` |
| 4 | Journeys, the review, drafted amendments, the Pulse "Site health" case | `site-audit/phase-4-journeys-and-review.md` |
| 5 | Planned releases: plan, publish or schedule, phased steps, pause, turn off, roll back | `site-audit/phase-5-releases.md` |
| 6 | Deploy from git (optional; Jason decides when) | `site-audit/phase-6-deploy-from-git.md` |

What Jason provides for each phase is listed in its file and collected in `site-audit/README.md`. From Phase 2 on, each
phase is itself released through the process above, as far as the earlier phases allow.

## Built: Phase 2, the site part (2026-11-09, `phase2-switches-tests_20261109.zip`)

- **Switches** (`Switches`, System Configuration → Switches, table `feature_switches`): off, staff and test accounts,
  a share of members (the same members each time), or everyone; a kill switch per switch and for all. Permission
  `config.switches` (executives by default). The first switch, `switch_test`, shows a line on the FAQ page.
- **Test accounts** (`users.is_test`, `test_records`, `TestAccounts`, System Configuration → Test accounts, Founder
  only): the members, the business team on the hidden "Traversence Test Listing", and 13 staff logins at each level
  with two-step keys (`StaffLogin::makeTest`, `resetTest`). One block, shown once, for the GitHub secret
  `CHECK_ACCOUNTS`, holding each sign-in and the health report's token. Test accounts are left out of the platform's
  counts (`PlatformCounts::isTeam`), likes (`Engage::likes`), people search (`People::search`), digests and the
  dashboard's new members; `Mailer` and `SesMail` send nothing to `check+…@traversence.com` on the live site
  (`TEST_MAIL=on` on staging does); a test payer's charge runs in QuickBooks sandbox (`Billing::charge`).
- **The health report** (`/api/health.php`, `HEALTH_TOKEN`): PHP and MySQL versions (proposal P1), table sizes, the
  scheduled jobs' last runs and failures, the month's AI spend by feature, the page cache, the admin's status checks,
  switches and the test-account count. Totals only. The token is made by Create test accounts and written into `.env`
  (`EnvSettings::setSecret`, never shown on Settings).
- **Sign-in for the checks:** a test address with the checks' token (`X-Check-Token`, the health token) skips the
  "verify you're human" check at member sign-in (`TestAccounts::checkRequest`, `api/auth/login.php`); staff logins sign
  in with their password and a two-step code made from their key.
- **The browser checks** (`tools/page-audit/rendered.mjs`, `.github/workflows/site-checks.yml`, daily at 09:43 UTC, in
  full on Mondays): the front-end pages from the page tree and the guide paths found on Discovery (each guide, its first
  suggested journey, its first named place), signed out at phone and desktop size; member and business pages as the
  member and the owner; every admin section as the test Executive, and each other staff login's home. Each page is held
  to: opens, no script errors, its own requests succeed, no sideways scroll at 390px, cards 3px from the edge, no
  browser dialogs, images with alt text, buttons and links with a name. Results in `site-audit/rendered.md` and
  `results/rendered.json`; screenshots with the run.
- **Still to build:** the steward's group, the test client and vendor; read-only tool checks per account type and the
  endpoint permission checks (Phase 2 items 7 and 9).

## No test makes a real transaction (Jason, 2026-11-10)

"Ensure no test can create actual transactions outside the sandbox." `QuickBooksPay::testHere()`: with a test account
signed in (a member's or a staff test login's, `users.is_test`), QuickBooks always runs in the sandbox, whatever the
Payments switch says; Name.com runs in its sandbox with no card charged (`Domains::noRealMoney()`); a business checkout
uses its gateway's test mode (`MarketPay::env()`). `QuickBooksPay::guardReal()`: a test order (made in the sandbox, a
"TEST · …" copy even if made while payments were live, or a test account's) is refused before any live charge, invoice
or payment is recorded; refunds and credits stay open, since they only send money back. "Test copy, billed to you" now
always makes a sandbox order. A test account's plan receipt goes to the sandbox's books, not the live ones (it went to
the live books before). Customers leaves test orders out of its numbers (`customers-no-tests_20261110.zip`).

**Note (2026-11-11, Jason: "12 hour audit is fine"):** the scheduled site audit (`.github/workflows/site-audit.yml`) runs
twice a day, at 07:17 and 19:17 UTC (an hour after the scheduled syncs), instead of hourly. GitHub Actions' included
minutes ran out on Nov 11 and the account's $0 Actions budget blocked every run (syncs included) until Jason set it to
$10 a month; the zip check still runs the code checks before every delivery, and Run workflow runs the audit on demand.

**Note (2026-11-13, decisions/0159):** the bar on staging becomes the site manager, where Upload puts a release straight on staging and Publish is the sign-off after a look. Phase 6 (deploy from git) is brought forward so that builds land on staging without uploads.

**Note (Jason, 2026-11-13, P6 settled):** the page tree's register is `site-audit/pages.md` (it replaced `routes.md`,
kept as `site-audit/archive/routes-2026-11-13.md`). Every page on the server is in it or in `archive.md`; routes designed
and not built are under its **Coming**. The audit checks the live mirror against it (R09) and `routes-check.py` lists
what's out of step, knowing the pages reached by rewrite (routers, `api.php` endpoints, sitemaps). Jason's calls:
9 designed routes archived (replaced), the data export and the DMCA queue coming, `gallery/join.php` archived, the
other 69 pages live.

**Note (2026-11-13, the audit's clock):** GitHub skips or delays scheduled runs when busy, so the site starts the
audit's workflows itself (`AuditClock`, job `audit_clock_tick`, `repository_dispatch` with `GITHUB_DOCS_TOKEN`); each
workflow's own schedule is a later fallback that does nothing when a run was already made. Site health in Pulse says
when one hasn't run in 26 hours, with Run it now. Switch `audit_clock`.

