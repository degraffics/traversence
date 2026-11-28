# ADHS's licensed health facilities as a crawler source (brief, 2026-11-28)

*Data & Content, for the session manager and the PM (8). Jason's direction through the CEO (2026-11-28): "Arizona's
licensed health-facility list (azcarecheck.azdhs.gov, www.azdhs.gov) is read by our crawler, not from a session. Add both
as sources under 0164: honour robots.txt and the site's terms, and let the licence decide the use … Match against our 1A
health listings to set the true kind (hospital, urgent care, clinic) and licensed status. Never overwrite a kind a person
set. Run it at the crawler's normal pace with the $0 spend." It feeds C3, C5 and C6 (`research/listing-audit-2026-11-27.md`)
and gate 3. Design only: nothing here is built or live. Built on Sync 2026-11-28 01:15 UTC.*

## What's already set (cited, nothing invented)

- **How a licence source is added:** 0164 §7.1 (W14 "Authority sources", `app/lib/Automation.php:44`): find, read its
  terms (robots.txt, terms of use, data licence, rate limits), classify its real use (bulk data, lookup only, blocked,
  claim-time check), test the reader on a handful of records, then stage it switched off.
- **Reading rules:** public, unauthenticated pages only; robots.txt obeyed with `Crawl-delay`; 1.5 s at least between
  reads (0164 §1.2, :467). A block (401/403/406/418, `AUTH_WALL`, robots, a challenge) is recorded, held for a person,
  and never retried or worked around (:564–569, :1176; builder-playbook principle 1's exception).
- **The licence decides the use:** R9. A state register is a public record. Its facts are kept and shown with the
  source and the date.
- **Never overwrite a person's words:** 0164. The kind changes only where the crawler set it, with Undo (the
  health-corrections ruling, 2026-11-27, `handoffs/data-content-2026-11-27.md` §3).
- **The pattern to copy:** the NPI and IRS loads. `workers/crawler/crawler.py:2791` `npi_bulk()` reads the file and
  sends batches to `/api/crawl/npi.php`; `app/lib/crawler/NpiBulk.php` stages them; it has a switch (`NPI_BULK`, :3802).
  Arizona's other licence sources are already registered switched off in `app/lib/service-defs.php` (`azcc`, `nmsos`,
  `azlicences`, :316–348).
- **Staging:** a new outside call asks `StagingGate::hold()` first (R13), and a new table names its class in
  `app/lib/staging-tables.php` (R14). Cost is $0 (a public site, no key).

## The unknown that decides the build

This environment's network refuses both hosts (2026-11-27), so how AZ Care Check can be read isn't known yet. It is a
lookup site at `/s/`, and may need JavaScript. ADHS may also publish a downloadable facility list on `www.azdhs.gov`.
0164's step 2 ("read its terms") and step 4 ("test the reader") answer it, and the crawler runs them from Railway. So
there are two releases, not one.

## Release A1: the source registered and its terms read (no listing changes)

1. `service-defs.php`: an `azdhs` entry (role source; hosts `azcarecheck.azdhs.gov`, `www.azdhs.gov`; Free ($0),
   public records; `staging` read; `added_off`), generated lists checked (`tools/page-audit/service-defs.php --check`).
2. Crawler step `adhs_terms` (switch `ADHS_SOURCE`, off by default like `NPI_BULK`). It runs only when the switch is on,
   then: fetches robots.txt and the terms page; checks whether a downloadable list is linked from the licensing pages;
   reads up to 5 records for Show Low (85901) through what robots.txt allows; and sends to a new
   `/api/crawl/adhs.php` its finding: real use, fields found, any block with its reason. Nothing is kept beyond the test
   (§7.1 step 4).
3. The source's row records the real use. A block means the source is held and its row says "blocked: by hand"
   (a public-records request, A.R.S. §39-121, per 0164's 2026-11-17 note step 1), never a workaround.
4. FAQ: one entry saying we check health facilities against Arizona's licence list ("coming").
- **Admin's test:** the source's card on the Service register shows Staged · off, or the terms finding with its date,
  or "Blocked by the site: <reason>" with the public-records step. Switch on and Pause, each with its way back (0173).
- **Tests:** a fixture robots.txt that allows, one that disallows, a 403 page, and a list page with 5 records. No network.

## Release A2: the load and the match (built after A1's finding)

- If A1 finds bulk data: a monthly load like `npi_bulk()`. If it finds lookup only: paced lookups for 1A's health
  listings, at most `licence_lookups_daily` (the cap `azcc` already uses).
- **Match** to 1A health listings by the one identity check (name, street, phone; 0163 §12, 0164 §3.1). A match records
  the licence (type, number, status, date) as a fact with its source. Where the crawler set the listing's kind and the
  licence type differs, the kind is re-filed (licence type → our category; the mapping table goes in the release for
  review), with Undo and History. Where a person set the kind, it is never touched: the difference goes to the
  identity queue as a task.
- A licensed facility we don't list is staged as a candidate (`IntakeStager::stage`), never published directly. Summit
  Healthcare Urgent Care is the first test case.
- C6: a licence match is a registry source in `ListingConfidence`, and a kind that differs from the licence holds the
  label at "Being checked" (the audit's C6 fix).
- **Report:** the first run's counts (records read, matched, kinds re-filed, held for a person, candidates, refused)
  go to the session manager and into the audit file.

## Lane and order

Data & Content builds both. A2's re-filing touches C3, which the IT Manager's search release 1 also fixes (#1629). So
the kind mapping is agreed with IT (11) before A2, so the code has one mapping, not two. Tier 4 for A2: the IT Manager (11),
since it changes listing data. Slots: the session manager's global queue.
