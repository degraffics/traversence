# Phase 3: The staging copy of the site

Decision 0145 §5 (step 5), §6.

## Goal

Every change is simulated on a full copy of the site, with real data shapes and every account type, before it reaches
anyone.

## What gets built

1. **`staging.trvgo.us`** (Jason, 2026-11-06): a subdomain of trvgo.us on the existing Bluehost plan, with its own copy
   of the files and its own database. Set up through the cPanel API we already use, as far as the API allows.
   - **Why it works well:**
     - trvgo.us is already on the account, so there's no new domain and no cost;
     - a separate domain keeps staging's sign-in cookies apart from traversence.com, so a staging session can never
       carry over to the live site, or the other way round.
   - **What it must not disturb:**
     - trvgo.us's short codes, GO Codes and storefronts live at `trvgo.us/<code>`, as paths, not subdomains, so
       `staging` can't collide with a code;
     - QR codes printed for customers never point at it;
     - the word `staging` is reserved so no code or storefront can take it.
   - **The trvgo.us router itself** is staged too: its copy answers at `staging.trvgo.us/go/…`, so changes to short codes
     and storefronts are tested before they reach the real trvgo.us.
2. **A refresh job.** It copies live to staging on demand and weekly:
   - files from the mirror;
   - the database with personal details stripped (names, emails, phone numbers, addresses, messages replaced with test
     values);
   - the test accounts kept as they are.
3. **Staging is safe by default:**
   - email is held, not sent, and readable on a staging page;
   - payments and SES are in sandbox;
   - not indexed by search engines (no-index and `robots.txt`);
   - behind a password;
   - the crawler and paid AI calls are off unless switched on for a test.
4. **Release to staging:** a release's files go to staging first, and its migrations run there first.
5. **Function checks for every tool that changes something.** Run on staging, for each account type allowed and refused:
   - save, edit, post, comment, like, upload, claim, invite, message, order, pay (sandbox), refund, delete and restore;
   - each admin module tool and Pulse step, and each scheduled job run on demand;
   - each migration run twice.

   Each result is compared with the tool inventory: the response, the change in the data, what the person sees, and the
   refusal for those not allowed. On live, a few of these run through the test accounts with their own test records,
   cleaned up after.
6. **Database speed on staging:**
   - the queries each page and tool runs are timed, and their query plans read;
   - full scans, missing indexes and one-query-per-row patterns are flagged, with a proposed fix;
   - each release's queries are compared with the last.
7. **The audit runs on both** staging and live. The scorecard shows them side by side, and what a release changes on
   staging.

## What Jason provides

The build adds a **Set up staging** button in System Configuration (Founder only). Through the cPanel API the site
already uses, it:
- checks the plan has room for a subdomain and a database;
- creates `staging.trvgo.us`, its database and database user, and copies the site;
- writes staging's own `.env`, so the database password is made on the server and never leaves it;
- sets the staging viewing password;
- adds the DNS record when trvgo.us's DNS is on Bluehost.

Staging uses Cloudflare Turnstile's official test keys, which always pass, so nothing changes in your Cloudflare
account.

1. **Approval** of this phase.
2. **One exception to the ".env by hand" rule:** the setup button writes **staging's** `.env` itself. Live's `.env`
   stays by hand, as always.
3. **Only if trvgo.us's DNS isn't on Bluehost** (for example, Cloudflare): add the one DNS record the button shows you.
4. **The staging viewing password goes in the same shown-once block as the test accounts**, which you paste into
   `CHECK_ACCOUNTS`. There's no separate secret.
5. **Optional:** Google, Microsoft and Facebook sign-in on staging. Skip it, and staging uses email sign-in.

## Done when

- [ ] `staging.trvgo.us` loads, asks for its password, and isn't indexed. `trvgo.us` codes and storefronts still work
      exactly as before.
- [ ] A refresh copies live to staging with no personal details left. Spot-checked.
- [ ] Email and payments on staging go nowhere real.
- [ ] A test release (a harmless change) goes to staging, is audited there, and the scorecard shows the difference.
- [ ] Every tool in the inventory that changes something has been checked on staging, for each account type, and its
      result is in the scorecard.

## Rollout and rollback

- Staging is separate from live. Nothing on live changes except the refresh job reading from it.
- Rollback: remove the subdomain and database (cPanel API).

## Cost

None, if the plan includes another subdomain and database. If it doesn't, the folder option costs nothing either.
