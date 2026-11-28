# ADHS A1: W14 reads Arizona's licensed health facilities' terms (built, held for its slot)

Brief: `handoffs/adhs-source.md`. Built on Sync 2026-11-28 01:15 UTC. Zip: `adhs-a1.zip`. Build: `build/`, with its `release.json`.

- **Site:** `app/lib/service-defs.php` (`azdhs`), plus the lists generated from it (`OutsideServices`, `Operations`,
  `StagingGate`); `OutsideServices` maps `azdhs` to `w14_terms`; `ControlRegister` registers `crawl/w14`;
  `HubOpening.php` (the task added off, `termsOff`, `termsDue`, `termsRead`, `blockOf`, `termsWords`, the Pulse step's
  words); `website/api/crawl/w14.php`; `app/migrations/2026-11-28_w14_adhs.sql`; `app/ui/faq.md` (one entry).
- **Worker:** `workers/crawler/crawler.py` 2.18.0 (`w14_terms`) and its README note. It goes to main with the release.
- **Tests:** `php tools/w14-tests/terms.php handoffs/adhs-a1/build` on MariaDB (26 checks: the migration twice, the task
  off, every switch, due, kept, refused, the words, the Pulse step); `python3 workers/crawler/tests/test_w14_terms.py`
  (8 checks, no network); `test_held_task.py` and `test_site_call.py` still pass.
- **Zip check:** clean except `app/ui/faq.md`, held by writeorder-tenant in flight. Once that's live and synced,
  re-apply only this entry on the live copy and rebuild the zip.
- **Before sending:** re-fetch the mirror and rebuild every file on the newest live copy; re-claim (claims expire
  05:28 UTC); record the IT Manager's (11) Tier 4 here; `send.py` in its slot.
- **After live:** someone switches on W14, the `azdhs` service and the `w14_terms` task (who presses is the session
  manager's to name). The first read's finding decides A2.
