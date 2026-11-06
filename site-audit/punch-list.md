# Punch list: everything Jason provides for the one build

Decision 0145 (§7: one build). Tick items here, or reply with the numbers.
- **Part A** decides how the build is made: please answer before it starts.
- **Part B** sets up access: do it whenever you can.
- **Part C** is done when the build tells you, after its site parts are live.
- **Part D** is for during the build.

## A. Decisions (before the build starts)

| # | Decision | Suggested answer |
|---|---|---|
| 1 | Who may publish a release, schedule one, or roll one back | You only, plus anyone you name. Turn off and Roll back are also open to Executives in an emergency. |
| 2 | The rollout steps | Staff and test accounts, then 25% of members, then everyone |
| 3 | When a release advances "on schedule" | 24 hours clean to members, then 72 hours clean to everyone. "On publish" works any time. |
| 4 | Release windows for scheduled releases | Weekdays, 6 am to 6 pm Arizona time |
| 5 | Your top 10 journeys (the list is in `phase-4-journeys-and-review.md`; add any it's missing) | My suggestion: find a place to eat; read a town's guide; search with a typo; sign up; sign in and out; like and comment; claim a business; edit a listing; post on the Marketplace; message a business |
| 6 | The measures: how many steps is too many | 3 or fewer from the home page for "find a place to eat" and "read a town's guide"; 5 or fewer for the rest |
| 7 | Deploy from git (Phase 6): include it in this build, or later | Later, once the audit has run for a few weeks |
| 8 | Batch reviews (Claude's Message Batches API, paid per use) | Off. Turn on later with a budget if wanted. |

## B. Facts and access (any time before the build needs them)

| # | What | Where to find it or do it |
|---|---|---|
| 9 | Does your Bluehost plan allow a subdomain of trvgo.us (`staging.trvgo.us`) and one more MySQL database? | cPanel → Domains, and cPanel → MySQL Databases, show what's used and what's free |
| 10 | Where trvgo.us's DNS is managed: Bluehost or Cloudflare | The domain's settings in Bluehost; or, if trvgo.us is in your Cloudflare account, there |
| 11 | Can test payments use QuickBooks sandbox (decision 0099)? | Yes/no. Sandbox is already set up for E-Commerce → Payments. |
| 12 | Does your mailbox accept "+" addresses (`check+member@traversence.com`)? | Send one a test email. If not, give me 8 addresses you control for test accounts. |
| 13 | **`MIRROR_READ_TOKEN`**, so the checks can read the mirror. | 1. GitHub → Settings → Developer settings → Fine-grained tokens → Generate. 2. Repository: `degraffics/traverse-3way-sync` only. 3. Permission: Contents, Read-only. 4. Paste it into `degraffics/traversence` → Settings → Secrets and variables → Actions → New secret, named `MIRROR_READ_TOKEN`. |
| 14 | Confirm GitHub Actions is allowed in `degraffics/traversence` | Settings → Actions → General → "Allow all actions" (or allow GitHub's own and Playwright) |

## C. When the build tells you (after its site parts are live)

| # | What | How |
|---|---|---|
| 15 | **`HEALTH_TOKEN`**, for the locked health report | I give you a long random value. Put it in two places: the live `.env` as `HEALTH_TOKEN=…` (cPanel File Manager), and as a GitHub secret named `HEALTH_TOKEN` in `degraffics/traversence`. |
| 16 | **Create the member test accounts** | Create account on traversence.com for each, confirming the email: new member, established member, business owner, business manager, contributor/steward. I mark them as test accounts and give them their roles and the test listing. |
| 17 | **Create the staff test logins** | Staff admin → add a login for each level (staff, manager, director, executive) in a "Test" department. When each is set up, copy its two-step secret key (the "can't scan?" text) for item 19. |
| 18 | **Test vendor and test client** | Vendors list → add "Test vendor". Clients → add "Test client" with a contact from item 12. (Or tell me to add them in the build.) |
| 19 | **`CHECK_ACCOUNTS`**, all the test sign-ins in one secret | I give you the layout. You fill in each account's email or staff ID, password, and (staff) two-step key, then save it as a GitHub secret named `CHECK_ACCOUNTS`. Nobody can read it back. |
| 20 | **Staging's `.env`** | I give you the lines. You add the staging database password and a viewing password you choose, in the staging folder's `.env` (cPanel File Manager). |
| 21 | **Turnstile for staging** | Cloudflare → Turnstile → your widget → Hostnames → add `staging.trvgo.us` |
| 22 | Optional: Google, Microsoft and Facebook sign-in on staging | Add staging's sign-in address in each provider's console (I give you the exact addresses). Skip this and staging uses email sign-in only. |
| 23 | **`STAGING_AUTH`**, the staging viewing password, for the checks | A GitHub secret named `STAGING_AUTH`, the same password as item 20 |

## D. During the build

| # | What |
|---|---|
| 24 | Upload the build's zips when it says, and say "uploaded". I sync and check every file. The release log will show anything still pending. |
| 25 | Look at the first scorecard and say which failures to fix first. |
| 26 | Approve the generated page tree (it replaces `routes.md`) and the decision register (`decisions/index.md`). |
| 27 | Decide the first proposals: approve, reject or reword. That's how the review learns what you want. |
| 28 | Watch the first real release go through: staging, then Publish or its schedule, then its steps. Try Turn off and Roll back once. |

## What's never needed

- No passwords or keys in chat or in code. Everything goes into GitHub secrets or `.env`.
- No new paid services. GitHub's free run time, the existing Bluehost plan, and Claude Code sessions only.
- No cPanel steps beyond `.env`, which is the one thing done by hand, as always.
