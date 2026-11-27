# B.2 step 1: what Bluehost already backs up (2026-11-27)

The launch plan's B.2 step 1 (`research/launch-plan-jan1-2026-11-26.md`, gate B), from the CEO's brief. Design: approved
by the Design reviewer (screens in `screens/`, found, none and unread at 1280 and 390). Backend only, read-only.

| File | Change |
|---|---|
| `app/lib/HostBackups.php` (new) | reads cPanel's `Backup::list_backups` and `Features::list_features` once a day (`CPanel::uapi`), kept in SearchIndex's state; `words()` gives the line: found, none, unread (cPanel's words), staging |
| `app/admin/module-cards.php` | `tv_mc_host_backups()`: the **Backups:** status line under the Bluehost card's **The databases** row; labelled UTC times; ↗ to cPanel on none and unread, no × there |
| `app/ui/faq.md` | new entry, "Is the site's database backed up?" |

Proof: `php tools/backup-tests/host-backups.php <root>` (14, no database or network); the harness in `harness/`.
Way back: Roll back on System Configuration → Releases (no data, no migration).
Next in B.2: the nightly backup and restore drill on staging, and the missed-night alarm (Tier 4 check before sending).
