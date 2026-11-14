# ADR 0152: Outside services, one card per company; Settings rebuilt in the new admin

**Note (2026-11-14, decisions/0164):** Amended by `decisions/0164` R1, R19, §10 (Services & usage (independence reported), now with 0163's register). 0133, named below, is closed and its rules are in 0164. The rest of this record stands; where it and 0164 disagree, 0164 rules.

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

**Note (2026-11-11):** each card also links to where the company is set up or used in our own admin ("In our admin":
QuickBooks → Payments and QuickBooks items, Stripe and Square → Gateways, Name.com and Fly.io → Custom domains, Tavily,
Google and Railway → the Crawler, Bluehost → Scheduled tasks, Staff logins, the Support inbox…), from
`OutsideServices::ADMIN`; Jason: "can the configuration page for each service be accessed here as well?"

**Note (2026-11-11):** each card has **How to sign in** (Jason: "need a way to see credentials for outside services so
login can be easier"): which account, which sign-in, where the second step goes, who holds it. Owners and executives
only; kept in `staff_settings`; a value that looks like a password, key or token is refused. Passwords and keys live in
the password manager (decisions/0155), never in the admin.

**Note (2026-11-11, later):** the sign-in note is one line at the top of each card, its fields behind ✎ (Jason: "I dont like
how much space the how to sign in is taking up"), with **Sign in ↗** to the company's own login page (`OutsideServices::LOGIN`;
Amazon's account link when set) and **1Password ↗**, the saved login's private link ("I really need login and passwords"):
the link opens the login in the person's own 1Password, where the password stays; the admin never holds it (0155).

**Note (2026-11-13, Jason approved; built `outside-services-all_20261114.zip`):** **every outside service gets a card**, keyed
or not. Jason: "the cards under outside services will include everything pertaining to that service, linked [where it] is
controlled by another section. Also each service will be searchable so I can easily find their cards or sections."
- **The cards** (`OutsideServices::COMPANIES`, 17 → 36): besides the keyed companies, every open-data and free service the
  site, its pages or the crawler call: OpenStreetMap (Overpass and its mirrors, the map tiles), Wikimedia (Wikipedia,
  Wikidata, Commons), openrouteservice (HeiGIT), the National Weather Service, AZ 511, Recreation.gov (RIDB), the National
  Park Service (linked only), USGS (The National Map's Terrain and Satellite layers, GNIS), the NPI Registry (CMS), the
  IRS, CPSC (SaferProducts.gov), the Library of Congress, Common Crawl, Brave Search, DB-IP, Pixabay, the Tailwind CDN,
  Vimeo and 1Password; the Census card gained the geocoder and TIGER, Cloudflare's cdnjs, Google's Maps links and YouTube.
  A free one's cost is **$0**, with its usage rules in plain words.
- **Everything about it on its card:** what we use it for; **Is it working**, from the site's and the crawler's own logs
  (the crawler's tasks that call it: their last outcome and the last 24 hours; its paid calls in the crawler's ledger this
  month, failed ones included; its scheduled tasks' last runs) and what's known that the logs don't show yet
  (`NOTICES`: overpass-api.de's 406 since 2026-11-13 16:51 UTC, with research/osm-overpass-2026-11-13.md); **Set in other
  sections** (`CONTROLS`): its rows on Sources (licence, use, limits, credit, whether its terms were read) and its
  switches, shown as they stand and linked there, never a second copy of the control; the addresses it's **called at**
  (`HOSTS`); cost; settings; **In our admin** (each linked section: Sources rows `#src-…`, the Crawler, scheduled tasks
  `#job-…`, switches `#sw-…`); and **Terms and who to ask** (`CONTACT`: its support, or its community, e.g. the OSM
  Community Forum's Help and support, tag overpass). Each standing source on Sources links back to its card.
- **Searchable:** the admin search (Ctrl K) finds each card by its name, company or any of its hosts (`#svc-<key>` opens
  it); one clear match also lists the sections linked from it, just under it.
- **Kept in step:** the site audit's code check **R12** (`tools/page-audit/audit.py`, non-blocking, into Site health
  through `findings.json`) flags a host the site, its pages or the workers call that's on no card, and a standing source
  on Sources with no card. Hosts that aren't services (our own, examples, namespaces, sample sources) are in
  `site-audit/services-ignore.txt`, each with why.
- **Next** (decisions/0160 note, same day): each crawler run's calls and failures counted by service, on its card.
