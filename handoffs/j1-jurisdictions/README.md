# J1: jurisdictions reviewed by the team (work in progress, not sent)

Data & Content session, 2026-11-27. Built on the live mirror at Sync 2026-11-27 02:27 UTC (`31261dc`). Sources: Jason's R8
ruling (2026-11-27, "advisory, not a mandate"; 0164 R8/R17 note, PR #1492), Q8 "Show" (PR #1508), the session manager's
plan review (J1 → review → G1 → R-D1 → J1b; OPENED kept until each state is reviewed).

Done in `build/app/lib/Jurisdictions.php`: `review()` takes a team role (`reviewed_as`) and the attorney is optional;
`undoReview()` back to draft, logged; `reviewer()`; words follow the ruling. Copied, not yet changed: `HubOpening.php`
(`lawSigned()` compares `UPPER(code)` with 'AZ' though codes are 'US-AZ', so R17's gate never passes: fix to read the
reviewed record), `sections.php` (the Jurisdictions page), `faq.md`.

Still to do: the migration (the `reviewed_as` column, US-CO and US-UT opened, each law with citation and official link),
the page's words and Undo, tests on MariaDB, the CEO's Tier 4 check, the release summary to the session manager.

**Blocked:** every law must be read against its official text before a record is written reviewed (the session
manager's rule), and this environment refuses the statute hosts (uscode.house.gov, www.azleg.gov, nmonesource.com,
leg.colorado.gov, le.utah.gov, www.govinfo.gov). Until they're allowed, the records stay drafts.
