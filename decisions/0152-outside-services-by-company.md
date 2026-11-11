# ADR 0152: Outside services, one card per company; Settings rebuilt in the new admin

**Status:** Accepted (Jason, 2026-11-11: "yes, by company, build it"). Built: `outside-services_20261111.zip`. Replaces
decisions/0133's Services & usage page (everything it showed is kept, on the cards and at the foot of the page) and
changes how decisions/0125's Settings is laid out (its "Change here" settings are kept, each where it belongs).

## Why

Moving files to Cloudflare R2 (decisions/0151) showed how scattered the outside services were. Cloudflare was listed as
"the captcha" under Services & usage, its captcha keys sat in Settings' sign-in list, and R2 storage showed up in Settings
under "AI services". One company could appear on four or five pages (Services & usage, Settings, AI use, and the Storage
and Notifications placeholders), and nothing said what we use it for, whether it works and what it costs, all in one place.
Settings itself was the old `admin/settings.php` shown inside the new admin (a bridge). Jason: "I dont like things all
over the place … they need to be consolidated into a easy to find one place", and asked that it include "their usage
and fees/costings".

## Decision

1. **System Configuration → Outside services** (`/admin/config/services`, the section that was Services & usage): one card
   per company, grouped by company (where keys, bills and dashboards live). Each card has:
   - **what we use it for**, in one line (Cloudflare: file storage (R2) and the captcha);
   - **each part, checked**: storage for real (the bucket answers, with its CORS rule); the rest say whether their keys
     are set (a Test button per part is a later step). The `.env` names show, never the values;
   - **what it costs**: its flat plan, this month's use and cost from the site's own counts (Services), the cap and the
     80% / 100% alerts, and how its pricing and fees work in plain words, including whose fees they are (card fees on
     our own orders are ours; on the Marketplace they're the business's, with 0% to us);
   - **its settings** changed here (EnvSettings), moved off the Settings page: the AWS sign-in link and region on
     Amazon's card, the storage bucket and public address on the storage company's (Cloudflare with R2), the plan
     prices on Railway's and Bluehost's, the AI cap on Anthropic's, the domain switches on Name.com's;
   - **its links**: dashboard, billing, keys.
   Cards needing a look come first, then those in use, then the rest. On top, the month so far: plans + use = total
   (a plan whose price isn't set is named, so the total is never quietly short). At the foot, kept from 0133: how
   independent we are this month, and the report for last month.
2. **The companies** are one list, `app/lib/OutsideServices.php` (COMPANIES, with each part's check in `parts()`): Cloudflare,
   Amazon Web Services, Anthropic, Google Cloud, Tavily, Railway, Bluehost, QuickBooks, Stripe, Square, Name.com, Fly.io,
   GitHub, Microsoft, Apple, Meta and the U.S. Census Bureau. A new outside service is added there once.
   `Pulse::SERVICES` stays as the links Pulse steps carry (`ext`), its Amazon and Cloudflare lines updated for R2.
3. **Settings** (`/admin/config/settings`) is rebuilt in the new admin: what's about the site itself, in groups: the
   site's email, orders and payments, legal identity, claims (each changed here), then publishing, privacy, the site and
   the server, with links to Outside services, Database updates, Scheduled tasks and AI use. `admin/settings.php` is
   archived (archive.md); its old phpMyAdmin checklist is replaced by Database updates, which run themselves.
4. **New settings:** `RAILWAY_USD_MONTH` and `BLUEHOST_USD_MONTH` (dollars a month), changed on their cards, so the
   monthly total holds every flat plan.
5. **Not yet:** each company's own bill read through its billing API (Cloudflare, Anthropic, Railway, Stripe, Square;
   Amazon at about a cent a lookup, once a day at most) beside the site's own count; card fees per payment recorded from
   QuickBooks; views and plays of stored files counted (Cloudflare's bucket Metrics has them meanwhile).
