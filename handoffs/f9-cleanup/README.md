# F9: two Data cleanup rules (built, held: no run until the CEO rules scope and the run's mechanism)

Ruling: the CEO, 2026-11-28 (1A first; a migration-run pass with a data_reason, the ledger, Undo and Check these; one
line in Jason's daily batch; the pilot guard). Evidence: `research/data-capture-review-2026-12-02.md` F9. Built on Sync
2026-11-28 01:15 UTC. Build: `build/app/lib/DataCleanup.php`. Test: `php tools/f9-tests/cleanup.php handoffs/f9-cleanup/build
<mirror>/sync-app/lib` (14 checks on MariaDB).

- `person_place`: a person's name (NPI's "Last, First", or a provider's credential at the end; never a name with a
  business word, a number or "&"; not `looksPerson`, which takes two plain words such as "Taylor Pool"). Kept as a
  record off the public site (quarantine), naming its practice (the listing at the same street, ignoring the suite, or
  the same phone) when there is one. The CMO's ruling.
- `legacy_unsourced`: created before 2026-10-01, never through the intake (no published `crawler_raw_index` row), and
  no kept source for its phone or address (an identity fact from a registry, a person, or a page we may keep). It goes
  back to a sighting. Its scope is the setting `legacy_scope`: off (the default: found and shown, never acted on, "Waiting
  on the CEO's scope"), health (the Health & Medical group plus the safety kinds 0061's emergency needs name: Fire Departments, County Government-Fire Protection, Civil Defense Agencies, County Government-Public Order & Safety, Sheriff; `Situations.php:441`, `:446`; the session manager, 2026-11-28), or all. A government or agency listing (`Claims::KIND_GROUPS` public) with any source we keep stays up. The CEO ruled (c): person_place plus health.
- **Guard:** `pilotIds()` = #8807 plus the setting `pilot_ids` (Editorial (12) adds each invited participant). Those,
  and any claimed or person-touched listing, are "Check these", never acted on.
- **Acting and Undo:** both use the existing off-site case in `act()` (with `junk_name`, `no_floor`, `page_only`):
  meta `removed` with the reason, `is_suppressed`, the ledger row, and Undo per record or per pass. Nothing is deleted.
- **1A counts (read-only, 2026-11-28):** 3,099 of 1A's 3,324 public listings are from September. A sample of 300
  says ≈2,600 have no keepable source and ≈310 are people. The scope is the CEO's.
- **Not built: the run's mechanism.** No release step runs PHP at publish, and `data_cleanup_tick` isn't scheduled
  (it's off and skips while automation is paused). The pass needs either a person's press (Insights → Data cleanup), or
  a small once-flag (a migration sets `cleanup_settings.pass_once`, and a job that always runs takes it once). That's
  the session manager's to route.

- **Gaps flagged:** shelters aren't named in 0061's needs, so they're outside the safety scope. `Police Departments` is named
  at `Situations.php:442` but not in the session manager's list; it's left out until ruled.
