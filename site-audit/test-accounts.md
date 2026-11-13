# Test accounts

Decision 0145 §3, Phase 2. The accounts the checks sign in as, so every kind of person's view and every permission is
covered.

**What is in git:** this list, meaning which accounts exist, their role, what they cover, and their address.

**What is never in git, chat or code:** passwords and two-step keys. Those are generated on the server and go straight
into one GitHub Actions secret, `CHECK_ACCOUNTS`. A GitHub secret is stored encrypted by GitHub, outside the
repository's files, and can't be read back by anyone.

## How they're made

The build adds a **Test accounts** page in System Configuration. Only the Founder can use it.
1. **Create test accounts** makes every account below:
   - emails confirmed, flagged as tests, roles and memberships set;
   - a strong random password for each, and a two-step key for each staff login.
2. It shows one block of text, **once**, holding every sign-in. You copy it into the GitHub secret `CHECK_ACCOUNTS`
   (repository `degraffics/traversence` → Settings → Secrets and variables → Actions). It is never stored readable on
   the server.
3. **Replace sign-ins** makes new passwords and keys at any time, and shows the block once again. Do this if the secret
   is ever lost or exposed.

**Test accounts are**:
- left out of counts, likes, views, emails, digests and billing;
- hidden from search and people lists;
- able to pay in sandbox only (QuickBooks sandbox for our own orders; for a business's Stripe or Square, the test set,
  `STRIPE_TEST_…` and `SQUARE_SANDBOX_…` in `.env`, beside the live set `STRIPE_LIVE_…` / `SQUARE_LIVE_…`, with
  `MARKET_PAY_ENV` picking (Jason, 2026-11-12); test mode never uses a live key, `MarketPay::key()`);
- marked "Test" for staff.

**Addresses:** `check+<name>@traversence.com`. The build creates the mailbox `check@traversence.com` through the cPanel
API (free), so anything sent to a test account lands in a mailbox you own. Live sends nothing to test accounts.
Staging holds all email on its own page, where the checks read confirmation links.

**"New member" stays new:** a test account can be set to count as under 7 days old, so the new-member checks don't need
a fresh account each week.

## Members (11)

| Account | Address | Covers |
|---|---|---|
| Guest | (no account) | Browsing, search, guest likes and the account panel, sign-up |
| New member | `check+new@` | The first 7 days: closer like checks, the sign-up questions |
| Member | `check+member@` | Default place, saved places, likes past 100, comments, messages, groups |
| Contributor | `check+contributor@` | Journeys and stories: write, edit, publish |
| Steward | `check+steward@` | Stewarding a test community group |
| Client | `check+client@` | The test client's orders: proofs, approve, pay (sandbox), "did it arrive" |
| Business owner | `check+owner@` | The test listing's owner: business portal, Edit listing, storefront, plans, team |
| Business manager | `check+manager@` | Manager on the test listing |
| Business accountant | `check+accountant@` | Accountant: money views, no listing edits |
| Business sales | `check+sales@` | Sales role |
| Business viewer | `check+viewer@` | Sees, changes nothing |

Optional, add later if wanted: business Support, Creator and Developer roles. Their permission rules are still checked
from the code.

## The test business

**"Traversence Test Listing"**, hidden from the public and search:
- owned by `check+owner@`, with the business roles above;
- a test storefront and a test Marketplace post;
- a test client business with `check+client@` as its contact;
- a test vendor (`check+vendor@`, using the work-order link page like any vendor).

## Staff logins (13)

Staff sign in with a staff ID (TRV-####), password and two-step code. The checks make the code themselves from each
login's key in `CHECK_ACCOUNTS`.

| Login | Level | Department | Covers |
|---|---|---|---|
| Test Executive | Executive | (all) | Everything an executive sees and does |
| Test Ops Director | Director | Operations | Director-level actions and escalations |
| Test Ops Manager | Manager | Operations | Manager-level actions |
| Test Ops Staff | Staff | Operations | Operations at staff level: Local, Market, Social, Back-Office, Insights |
| Test Finance Staff | Staff | Finance | Back-Office and Growth money tools |
| Test Sales Staff | Staff | Sales | Growth, Concierge |
| Test Service Staff | Staff | Service | Growth, Concierge, Back-Office |
| Test Content Staff | Staff | Content | Local, Explore, Social, Insights |
| Test Engineering Staff | Staff | Engineering | Insights, System Configuration |
| Test Editor | Editor | Content | Articles and guides: edit and publish |
| Test Content Creator | Content Creator | Content | Creating, not publishing |
| Test Assistant | Assistant | (none) | The assistant's limited view |
| Test Vendor login | Vendor | (none) | A vendor's staff view, if vendors sign in |

The Founder level is never tested with a test login. It has every permission, which Executive covers, and your own
login stays yours.

These levels and departments don't each get a login, but they're still checked:
- **Director and Manager outside Operations:** their permissions follow the same rules (`Access::can`), checked from the
  code for every pairing.
- **Staging only:** the logins that change money (refunds, payouts) act on staging.

## Total

- 11 member and business accounts;
- 13 staff logins;
- the test business, client and vendor.

All of it is made by one button, and all of its sign-ins go into one secret.
