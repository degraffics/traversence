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
