# Phase 3: The staging copy of the site

Decision 0145 §5 (step 5), §6.

## Goal

Every change is simulated on a full copy of the site, with real data shapes and every account type, before it reaches
anyone.

## What gets built

1. **`staging.traversence.com`:** a subdomain on the existing Bluehost plan, with its own copy of the files and its own
   database. Set up through the cPanel API we already use, as far as the API allows.
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
5. **The audit runs on both** staging and live. The scorecard shows them side by side, and what a release changes on
   staging.

## What Jason provides

1. **Approval** of this phase.
2. **Confirmation that your Bluehost plan allows another subdomain and database.** cPanel → Domains (subdomains) and
   cPanel → MySQL Databases show what's free. Most plans allow both. If yours doesn't, staging can live in a folder on
   the main domain instead, with the same protections.
3. **The staging database password in the staging `.env`.** This is the one step done by hand: cPanel File Manager,
   as with all secrets. I'll give you the exact lines.
4. **The staging password.** Choose the password for viewing staging; it goes into `.env` too.
5. **Cloudflare Turnstile:** add `staging.traversence.com` to the widget's allowed hostnames (Cloudflare → Turnstile →
   the widget → Hostnames). Optional: Google, Microsoft and Facebook sign-in on staging need its address added in each
   provider. Without them, staging uses email sign-in only.

## Done when

- [ ] `staging.traversence.com` loads, asks for its password, and isn't indexed.
- [ ] A refresh copies live to staging with no personal details left. Spot-checked.
- [ ] Email and payments on staging go nowhere real.
- [ ] A test release (a harmless change) goes to staging, is audited there, and the scorecard shows the difference.

## Rollout and rollback

- Staging is separate from live. Nothing on live changes except the refresh job reading from it.
- Rollback: remove the subdomain and database (cPanel API).

## Cost

None, if the plan includes another subdomain and database. If it doesn't, the folder option costs nothing either.
