# The board's daily consistency check (step 5): the release summary

2026-11-23, the Control Board build session (session_01CXLi5iMkDf1xiJ4V9rScRe). `control-board-health.zip`, 2 site files,
built on Sync 2026-11-23 00:15 UTC (`791f665`, with #258 live). Built on what's there, as the session manager asked:
the existing health report (decisions/0145), no new endpoint.

## What it does, each with its source

| Behaviour | Source |
|---|---|
| `/api/health.php` gains **consistency**: every alarm drawn on the board's row, its Pulse item and its module card; on each, its title and status, plain names (never `job:`, `wf:`, `svc:`), no raw time token, Fix it where its cause names one spot, a closed alarm off the board, a trip with its Incident Report. Returns alarm ids, keys, words and counts | control-board-plan.md step 5 ("each open alarm's hold, words and Fix it match the map"); 0176 §2–3; 02 §5.4, P12 |
| The check is one class, `app/lib/ControlConsistency.php`, which the bench's scenario test now calls too (`views()`), so live and the tests run the same code | the session manager, 2026-11-23 ("your views() checks") |
| **ledger**: worker calls over 24 hours by endpoint and by the workflow it serves: calls, OK, 4xx, 5xx, received, sent, stored, updated, review, refused, average time | D6; live-reads C3 2 (closes the Ledger look) |
| **irs_loads**: the last 20 loads, rows seen and removed, and the total removed | live-reads C15 |
| GitHub's daily health job (`search-probe.yml` → `towns.mjs`) writes them to `site-audit/results/consistency.json`. No Pulse case and no email: a mismatch is for the building session and the session manager | plan step 5 ("never for Jason"); the session manager, 2026-11-23 |

**Admin's test:** nothing new on any screen. The session manager reads `site-audit/results/consistency.json` daily and
reports the week to Jason (plan step 5).

## Proof (bench: the live mirror with #258, MariaDB, PHP 8.3)

- `tools/control-board-tests/health-report.php`, **11 of 11**. It calls the real `/api/health.php` through PHP's server:
  - locked without the token (401);
  - a clean trip passes every surface;
  - an alarm with a raw id in its title is reported with its id, key, surface and check;
  - the ledger's counts by endpoint and workflow, leaving out the 2-day-old row;
  - irs_loads' rows seen and removed, and the total removed (300);
  - nothing is sent, and nothing about a person is in the three sections.
- The scenario test through the shared class: **135 of 135 run, 0 fail; 173 of 173 cells; 159 drawn.**
- zipcheck: clean. No FAQ entry (staff-only, like R0).

## Repo files in the same PR

`tools/page-audit/towns.mjs` and `.github/workflows/search-probe.yml` (write and commit `consistency.json`),
`tools/control-board-tests/health-report.php`, `scenario/run.php` (views() calls the class).
