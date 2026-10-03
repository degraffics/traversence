# 0062: Identities: one record per thing, every fact with its sources, verification by consent

**Status:** Accepted, 2026-10-03. To be built in steps (below), listings first.
**Source:** discussion with Jason on 2026-10-03 (the admin helper: duplicates that aren't clear, listings missing
phone numbers with no way to find them, and "this shouldn't be a single phone number search, it's an identity builder
and confirmed").

## Context

The work queues keep asking the same question in different shapes. Is this listing who it says it is? Are these two
listings the same business? What's its phone number? Which website confirms it? Each has its own tool today: the
crawler's second look (decisions/0044), refreshing from a listing's own website, duplicates and merge, sources
(decisions/0045), claims, and the resource-guide phone check (decisions/0061). None of them shows a person what is
known about a thing, where each piece came from, and what's still missing. So deciding means opening several pages,
and a missing phone number has nowhere to come from.

## Decision

### 1. An identity is one record per thing, shaped by the 5 W's

Every thing on Traversence has **one identity**: a business, organization, outdoor place, landmark, resource line,
event, marketplace item, or one of our users (opt-in, §4). Its facts are grouped by the 5 W's, with How to reach
added:

| | Facts |
|---|---|
| **Who** | name; other names (doing-business-as, legal name, former names); kind of entity; who runs it; parent or chain; claimed or not |
| **What** | category; what it offers; what it's known for |
| **Where** | address; service area; map point. A confidential address stays phone-only (as now). |
| **When** | hours; seasons; open, closed or gone; when it started |
| **Why** | its purpose; the situations it answers (decisions/0061) |
| **How to reach** | phone; website; email; booking; social and directory pages (Facebook, Instagram, Yelp, Google profile) as links |

### 2. Every fact carries its sources

- **No fact without a source.** Each fact records its value, **each source that gives it** (the page or registry,
  how it was found, when it was checked), and **who added it**: the crawler, the owner, a member's suggestion, or
  staff. A person's own fix is a source too, recorded as, for example, "Admin, called them", with their note.
- **Sources are independent or they don't count twice.** Two pages on the same website, or a copy site repeating a
  registry, count once (as decisions/0044 already does).
- **Visitors see** "Confirmed by 3 sources", with links to the public ones. **Staff see** every source, including
  private ones like a phone call, which are never shown publicly.
- **Confidence per fact:** confirmed (independent sources agree, or the owner confirmed it), single source, in
  conflict (sources disagree; both values are shown with their sources), or missing.
- **An identity is confirmed** when its key facts (name, where, how to reach) are confirmed, using the confirmation
  rules auto-import already uses (decisions/0044): a trusted registry, or two or more independent sources, or the
  owner's claim.

### 3. Where facts come from

In this order, as for situations (decisions/0061):
1. **Our own records and the sources we read regularly** (decisions/0045).
2. **The thing's own website** (its contact and about pages) and **official registries**: the NPI Registry for
   health care, IRS nonprofits, Recreation.gov, state and county sites.
3. **The owner**, through a claim: the strongest source for their own facts.
4. **Web search** (Tavily, already in the worker), only for what's still missing or in conflict.

**Google, Yelp and Facebook are used as evidence, not copied.** Their terms don't allow scraping:
- Google's business data costs money per lookup and lets us store only its place ID;
- Yelp's data can be cached for only 24 hours, with attribution;
- Facebook's page data needs an app review and is narrow.

So when a search result shows their page with the **same name, phone and address**, that counts as a confirming
source, and we keep the **link** (in How to reach and as the source), not their content. Paid access can be added
later as one more source if it proves worth it.

### 4. People: elected profiles, verified only with consent

- **A person's identity exists only because they made an account.** Members are elected profiles: they choose to be
  here, and they control their own facts.
- **Consent to verification is part of creating an account**, as its own step in plain words: "We'll check the
  details you give us (name, email, phone, and for owners the business) against the sources we list. Nothing else
  about you is looked up." It's recorded with the date and the exact wording, in the consent log
  (`tool_consents` / `tool_consent_log`). It can be withdrawn in Privacy; withdrawing removes the "verified" mark,
  not the account.
- **Asked of everyone, required only for some roles:** contributors (decisions/0056), owners claiming a listing,
  stewards, and staff. Nobody needs it to browse, search, save, comment or message.
- **Verification checks only what the person gave us.** It never searches for anything more about them.
- **Under-18 accounts are not verified** beyond the age gate (decisions/0056).
- **Public roles** (a mayor, a county office) are identities of **the office**, from official sources, not of the
  private person.

### 5. Identities only for what's ours

An identity exists only when it belongs to **one of our users (opt-in), listings, places, items or things**. Nothing
standalone:
- People who merely appear in data (staff named on a website, a name in a registry) are **not** made into identities.
  They stay as text on the listing, or are left out.
- The crawler's identity work always starts **from one of our records** and fills in that record. It never goes
  looking for new people.
- The rules for tribal nations' land (decisions/0058 §26) and confidential addresses apply to every fact.

### 6. One tool instead of several

| Today | Becomes |
|---|---|
| second look, refresh from website, "find the phone" | one **build the identity** job for the crawler: fill what's missing, check what's single-source, flag what conflicts |
| duplicates and merge | "these records are **one identity**", decided on the card with the facts side by side: matches in green, differences in amber, a suggested keeper and a suggested value for each fact |
| sources | each fact lists its sources; the Sources page shows which identities each source confirmed |
| claims | the owner confirms their own identity: the strongest source |
| the resource-guide phone check | the same per-fact check |
| quick fixes | "✎ I know this" on any fact, saved with the person as its source |

The admin **helper** (Dashboard → Today → Start) shows each item as its identity card: what's confirmed, what's
missing, what's in conflict, and the decision (Ask → Decide → Act).

## Build, in steps

1. **The reports fix** (found on 2026-10-03, independent of the rest): reports on page comments and experiences were
   saved with a blank type, so they counted but showed nothing. Add the missing types, show group reports, and let the
   helper dismiss the blank ones in one decision.
2. **Facts and sources for listings:** the data model (a fact table keyed to its record, a fact-source table). Fill it
   from what we already hold: listing fields, `entity_metadata`, crawler references, sources.
3. **The identity card and the helper for listings:** Ask → Decide → Act; duplicates side by side; "✎ I know this".
4. **The crawler's build-the-identity job:** own website, registries, sources, then web search (with Google, Yelp and
   Facebook as evidence); missing items are parked until it's done.
5. **Consent at sign-up** and verification for the roles that require it.
6. **Places, events, items and resource lines** on the same card.

## Consequences

- One place shows what's known about anything and how we know it. Decisions get faster, and credibility is visible.
- More is stored per record (each fact and its sources), and it can grow without limit, so stale sources are re-checked
  and pruned on a schedule.
- Facts are only as good as their sources. A single-source fact is shown as such, never as confirmed.
- Building profiles of private people is ruled out by design, not just by policy.
- Sign-up gains one step; nobody is blocked from ordinary use.

### Progress, 2026-10-03: Step 1, the reports fix

- **Cause, reproduced on MariaDB:** `content_reports.kind` was a fixed list (`ENUM`) that never gained
  `content_comment` or `experience`. On a database that isn't strict (shared hosting), MySQL saves an unknown value
  as a blank, with only a warning, so those reports counted but showed nothing. On a strict database the report would
  fail outright.
- **Second fault:** `Reports::open()` treated any kind it didn't name as a group, so a blank report showed a wrong
  group or was skipped while still counted. Reports whose content had been deleted were skipped the same way.
- **Fix:**
  - The kind and reason become text (`2026-10-25_reports_kinds.sql`), so a new kind of report can't be blanked again.
  - `Reports::repairBlank()` gives a blank report its kind back when its target can only be one thing: a page comment
    or an experience.
  - The ones it can't tell are **one item**, "Reports that lost what they were about", with their reasons and notes
    and **Dismiss all**. They count as one item on the dashboard too.
  - A report whose content was deleted says "gone" and can be dismissed.
  - Group reports have their own branch.
  - A kind the page doesn't know is shown, never left out.
  - Headings name what was reported (Comment on a page, Experience, Journey…), with the matching button ("Hide
    comment", "Hide experience").
  - Any report can be dismissed.
- **Checked in the sandbox:** a recoverable blank report came back as a page comment with its text; two unrecoverable
  ones became one item and "Dismiss all 2" cleared them; a deleted journey showed as gone; a group report showed the
  group. Also checked on MariaDB: the migration keeps the blank rows, and new reports keep their kind.

### Progress, 2026-10-03: Web search usage on the dashboard

Web search is **Tavily**: `TAVILY_API_KEY` is set in Railway (confirmed by Jason), and Brave is the fallback.
After each run the worker reports which search it has and how many searches it made (`POST /api/crawl/usage.php`),
including runs that stopped early. The site keeps the month's total in `search_index_state` (`websearch_*`,
reset when the month turns). System status shows "Tavily: 340 of 1,000 searches this month": amber at 80%, red when
the allowance is used up, and Off with the reason when there's no key. The allowance is `SEARCH_MONTHLY` on the
worker (1000 by default; change it in Railway if the plan changes). Checked in the sandbox: 850 + 2 searches showed
852 of 1,000, amber.

### Progress, 2026-10-03: Step 2, facts and sources for listings

- **Tables** (`2026-10-26_identity_facts.sql`):
  - `identity_facts`: one row per value of a fact, keyed to its record (`record_kind`, `record_id`), with its W, a
    value for comparing (a phone's 10 digits, a website's host, an address's number and street), its status, and
    whether it's the value shown.
  - `identity_fact_sources`: each source of that value: kind, website, link, label, private or not, who added it,
    note, when checked.
  - Places, events, items and resource lines use the same tables later.
- **Built from what we already hold** (`Identity::fromListing`):
  - the listing itself (name, kind, map point, ZIP, the owner's claim);
  - its details (`entity_metadata`, each with who added it);
  - what the crawler found: the page it came from and the websites that confirmed it, and what each matched (phone,
    address or both);
  - the sources we read (`source_facts`, by phone, or by street number and ZIP, with the name);
  - the NPI Registry (by phone, or street number and ZIP);
  - IRS nonprofits (by name in the ZIP). A registry's legal name that differs is kept as an "other name".
- **How a fact is confirmed:**
  - two independent websites agree (each website counts once, `Sources::site`); or
  - an official registry or the owner (a verified claim) gives it; or
  - staff checked it, which comes with "✎ I know this" in step 3.
- **What counts as one source only:**
  - our own records;
  - a value staff typed in the listing editor ("Entered by staff");
  - a member's suggestion.
- **A website counts as the listing's own** only when the crawler matched it to the listing's name or website and it
  isn't a government page, directory or social page. Directory and social pages are kept as links under How to
  reach, never copied.
- **A confidential address** (a shelter) is never stored, not even for staff: phone only. Its card doesn't call the
  address missing.
- **A rebuild** clears what the builder added and keeps what a person added by hand.
- **Admin → Listings & places → Identities** (`/admin/identities.php`):
  - the numbers (built, names confirmed, no phone or website, sources disagree, sources recorded);
  - **Build from what we hold**, in batches of 150 from the page;
  - the listings where sources disagree, and the newest with no phone or website;
  - a lookup by name or number showing the card by W, with each fact's status and its sources (staff see private
    ones), and **Rebuild**.
  - Deciding and "✎ I know this" are step 3.
- **Checked in the sandbox:**
  - all 4,381 listings built, about 10 ms each;
  - the safe house kept phone only;
  - Gallup Indian Medical Center confirmed by IHS, OpenStreetMap and the NPI Registry;
  - Villa De Gallup showed its two addresses as a conflict;
  - the page at 390px: cards 3px from the edges, no sideways scroll.
- **Also checked on MariaDB:** the migration runs twice safely, and a clinic matched by its own website, a
  government page and the NPI Registry came out confirmed, with the same 7 facts on a rebuild.

### Progress, 2026-10-03: Step 3, the identity card and the helper

- **The helper** (`/admin/helper.php`, Dashboard → Today → **Start**, or **Work through** on a Today line, or Admin →
  Helper):
  - one item at a time, always in three steps:
    - **Ask:** what we know, already gathered;
    - **Decide:** the choices, with the one we'd make marked **Recommended** and the reason in plain words;
    - **Act:** one tap does it and moves to the next.
  - **Undo** takes the last choice back, and stays offered until the next choice. **Skip for now** leaves an item for
    later; "Bring back what I skipped" returns them.
  - A progress bar, and queue chips to work one queue only.
  - Today's order: reports, possible duplicates, listings to approve, facts where sources disagree, websites to sort,
    failed crawl jobs.
  - Queues it doesn't work itself yet (sent in by visitors, experiences, missed searches, local names, areas to name)
    show their count and **Open it**.
- **Library:** `Helper` (`queues`, `next`, `act`, `undo`, `approveGroup`). It calls the same code the Review page
  uses (`AutoImport`, `IntakeStager`, `Verify`, `Sources`, `Reports`), so the rules are the same.
- **Reports:**
  - always one at a time, by a person: Remove / Keep it / Bring it back;
  - "nothing left to look at" is recommended as Dismiss;
  - Undo reopens the report.
- **Possible duplicates, side by side:**
  - the proposed listing and the existing one, field by field: same in green, different in amber;
  - tap the value to keep (the existing one is suggested, the new one where the existing is empty);
  - a name counts as the same only when it is identical. "Heritage House West" and "Heritage House" are alike, not the
    same.
  - The recommendation says what matches and what differs, for example "Same ZIP. Different name and kind (Pharmacies
    vs Animal Protection Organizations)".
  - **Merge** adds what's new to the existing listing and keeps what a person entered. With nothing new, it closes the
    proposal as a duplicate, and its page counts as a source for the existing listing.
  - **Not the same place** moves it to the listings to approve. That decision is kept when the crawler finds the
    listing again.
  - Merge can't be undone from the helper; the others can.
- **Listings to approve:**
  - the facts, the four checks with ✓ and ✗, the sources, and its kind (pick from the suggestions);
  - Approve / **Find more** / Reject, with the reason taken from the checks.
  - **Find more is automatic:** a listing with one source that the crawler hasn't looked at a second time yet stays
    with the crawler (up to 2 days), and the helper says how many. "Find more" sends one back for another look; it
    returns when that's done.
  - **Group approval:** when 2 or more listings pass every check that matters (2+ sources or a registry, a sure kind,
    clean text, no duplicate) and only the score is short, the helper first offers them as a group, with 5 samples:
    "Approve all N" or "Go one by one". Any it can't approve go one by one.
  - Undo after approving hides the new listing; it can be brought back from its page.
- **Facts where sources disagree:**
  - each value with its sources;
  - "Use this one" saves it with you as a source and writes it to the listing. The fact is then confirmed and the
    other value is kept as "not shown".
  - "✎ Neither: I know the right one" takes a different value.
  - Undo puts the old value and its source back.
- **"✎ I know this"** on every fact on the Identities page, and "✎ I know something about it" for a missing one
  (phone, website, email, address, town, state, hours, about, name):
  - saved with the person as its source, their note ("Called them"), and "only staff see this note" if wanted;
  - written to the listing, as staff and locked so the crawler won't overwrite it;
  - kept through rebuilds;
  - never a confidential address.
- **Websites to sort:** Read regularly / Cite only / Ignore. Read is recommended for government, tourism, chamber and
  community sites; Cite only for the rest.
- **Failed crawl jobs:** Try again now (recommended when the source was busy or slow) / Drop it.
- **Today and the menu:**
  - "Facts where sources disagree" is a Today line;
  - the reports, listings, websites and failed-jobs lines open the helper;
  - its count is shared with the menu badge, so ☰ still equals Today's total.
- **Checked in the sandbox (390px and desktop):**
  - each queue's choices, Undo and Skip;
  - Start from Today, with ☰ 67 = Today 67;
  - a conflict settled and undone;
  - "✎ I know this" on a website;
  - approve then Undo (hidden), merge, and group approval with one row that couldn't be approved (it went one by one).
- **Also checked on MariaDB:** "✎ I know this" and its Undo, and a rebuild keeping the person's fix.

### Progress, 2026-10-03: Step 4, the crawler's build-the-identity job

- **Queue** (`2026-10-27_identity_jobs.sql`, `IdentityJobs`):
  - every public listing with no phone and no website is queued, those with a street address first;
  - the site tops the queue up on its own when it runs low;
  - **Look now** on a listing's card puts it first;
  - a look that found nothing waits 30 days, then tries again.
- **The worker** (`identity_jobs()` in `workers/crawler/crawler.py`, `IDENTITY_PER_RUN` 5, `IDENTITY_TIME` 60s), in
  order:
  1. **its own website:** home and contact pages; the phone, email, social links and hours;
  2. **the NPI Registry:** the phone at the address we hold;
  3. **one web search**, only for what's still missing:
     - its own website: a domain carrying the name, on a page that shows the address or town;
     - a phone from any other page that names the place **and** shows its street address.
- **Google, Yelp and Facebook** (§3):
  - their pages are never fetched;
  - their snippet can only confirm a value another source gave. The site refuses a value only they give.
  - Their page is kept as a link under How to reach.
- **What the site does with a find:**
  - each value is stored as a fact with its source: the page, what it matched, and "identity job" (`added_by` 0, kept
    through rebuilds);
  - **straight onto the listing**, when the listing has none: a value from its own website, a registry or a government
    page, or one two independent websites agree on;
  - **to the helper** otherwise: a new queue, **Found by the crawler**, with "Yes, put it on the listing" (recommended
    when the page showed the name and address) and **Not right**;
  - "Not right" keeps the value marked, so the worker skips it and the site won't show it.
- **Never touched:** anything a person entered. A confidential address is never sent to the worker.
- **Admin:**
  - Identities shows the job's numbers, "Queue every listing with no phone or website", and on each card what the
    crawler did and when, with **Look now**;
  - System status has an "Identity job" line;
  - "Found by the crawler" is a Today line, its count shared with the menu badge.
- **FAQ:** "Where do a listing's phone number and website come from?"
- **Checked in the sandbox,** with the worker running against the site:
  - Hillcrest Apartments: search found its own website; its phone, email, Facebook page and website went on the
    listing.
  - St Johns Ambulance: a chamber page gave the phone and a Yelp snippet confirmed it, so it was confirmed by 2 sources
    and went on the listing, with the Yelp page kept as a link.
  - St Johns Indl Air Park: one directory page gave the phone, so it waited in the helper. "Not right" kept the next
    run from bringing it back; "Yes" put it on the listing.
- **Also checked on MariaDB:** the migration runs twice safely, and lease → complete puts the own-site phone and
  website on the listing while refusing a phone only Yelp gave.
