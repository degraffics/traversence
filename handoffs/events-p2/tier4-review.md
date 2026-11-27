**Superseded, 2026-11-27 (decisions/0134's notes #1605 then #1606, Jason):** first the code was to be published by its
owner like a post, then the pilot's scope dropped participant codes altogether: only the host's tickets sell, each
participant shows its owner's own offer line, given in person. Everything below is the history of the code design and no
longer applies to the build. With no read of a participant's business data, the CTO's Tier 4 condition is moot; the
session manager asked for one fresh QA check that no `b_biz_codes` or tenant code remains (recorded at the end).

# Tier 4 review: a business's discount code on a public page (superseded: no codes in the pilot) (`BusinessData::publicCode`, `Events::liveCode`)

Asked by the session manager, 2026-11-27 (a data wall, decisions/0110, is a high-risk area: CLAUDE.md "Five tiers").
Each reviewer was a fresh, independent agent, given the code, the tests and 0110, never this session's conclusions:
the CTO's Tier 4 (a security and data-wall review) and QA's independent check (its own probes, on MariaDB 10.11 and PHP 8.3).
Four passes each, all on 2026-11-27 (UTC), the fixes made between passes.

| Pass | Commit reviewed | CTO (Tier 4) | QA |
|---|---|---|---|
| 1 | e34d7815 | Approved with conditions: a passed-in connection filled the page's cache; a failed connection retried per business; the host's row read codes for businesses no longer taking part; 0110 needs a note; tests miss the page's own path | Pass with issues: the cache (as the CTO); "today" is the server's date |
| 2 | cf498b41 | Approved with conditions: **0110 conflict (blocking)**, move the read into the realm's gateway and add a note to 0110; refuse the portal's connection; test a read-only login; show the minimum order | Pass with issues: the date (kept: checkout's own rule, BizCodes::check); put back the caller's tenant, not NULL |
| 3 | c5761a9e | Approved with conditions: drop a connection whose reset fails; refuse main's connection; 0110 note required | Pass with issues: the tenant restored as an integer, not exactly |
| 4 | 2a1e218d | Approved with conditions: the switch could be saved on-but-killed by anyone; Lift all stopped half way; "with your offer" while codes are off; a read-only session; tests for the Founder rule and the dropped connection | Pass with issues: a passed-in connection skipped the switch; Lift all half way (as the CTO) |

**All fixed** (this commit): the read lives in `BusinessData::publicCode` (the realm's gateway): one `SELECT … FROM b_biz_codes
WHERE code = ? LIMIT 1` on the listings login's own connection, `SET SESSION TRANSACTION READ ONLY`, the caller's
`@tv_tenant` saved and put back exactly in SQL (also on failure), a connection whose reset fails dropped for the request,
main's and the portal's connections refused, only `code`, `ends` and `min_cents` returned, dates as checkout counts them.
`Events::liveCode` waits for the **`event_codes` switch** for every caller: off as it ships; only the Founder sets it to
anything that is or could become on (anyone may kill it; Lift all and Kill all never stop half way). Off: the owner's line
only, no code chosen, stored or shown, and no query reaches the listings database.
**Tests:** `tools/event-tests/live-code.php` 31/31 (one code, read only, the tenant, public terms, the page's path, a
read-only login, a connection killed mid-read, both connections refused); `switch-gate.php` 12/12 (the Founder's switch);
`portal-parts.cjs` 51/51 and `event-parts-page.cjs` 51/51 (the switch off and on, at 1280 and 390).

**Before the switch goes on:** the Founder's ruling (Tier 5) on a note to 0110, with the CEO since 2026-11-27. The CTO's
lines to amend: 0110:54 ("`tv_tenant()` … set once, by the router … can't be changed afterwards in the same request"),
0110:150 ("Public pages … can only read the main database"), 0110:142 (the `trv_listings` row). The note names the one
exception as tested above, says wall 4's gateway rule holds (the read is in BusinessData) and that it applies only while
`event_codes` is on. **Until then P1/P2 ship with the switch off.**

**Release conditions (the CTO):** `release.json` must not set `event_codes` (`Releases::switchTo` writes switches
directly); suggested for later, kept out of this release: `feature_switches.event_codes` left out of staging's publish
copy (StagingPublish.php:183). Noted, no change: `@tv_publiccode_was` is publicCode's own name; a code chosen before the
switch went off comes back when it's on again, checked again by its owner's next visit.

## QA check after the pilot-scope strip (2026-11-27, against the mirror at Sync 19:50 UTC, dd02bdd)

Verdict: pass, one defect fixed. No code remnants in P1 or P2: none of the 17 code names in `Events.php`,
`business/events.php`, `event/index.php`, `listing/view.php`, `Notify.php` or the migration; the other hits
(`business/api.php`'s `BusinessData` and `Scope::setPeople`, `staging-tables.php`'s `biz_codes`, the FAQ's own "Discount
codes" entries) are byte-identical to live. The migration creates `event_participants` only, with no code columns. The
page reads only main tables (events, entities, zip_coordinates, event_participants, short_codes, market_*), never a
`b_` view or BusinessData. The offer prints as written through `tv_e`; an unclaimed listing's offer is blanked. All 12
PHP files pass `php -l`.
Fixed: both builds' `faq.md` had an old line 487 (live gained a sentence on the before-and-after look since); rebuilt from
live with only the four event entries added. Dropped P2's unused `.evp-offer code{…}` rule.
