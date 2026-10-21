# 0104: Admin workspaces, Wave 0 (foundations)

Status: Built (2026-10-21). Implements the foundations in `admin-rebuild-batch-plan.md` §6 (Wave 0), following the
Master AOS v43 §8–9 and `workspace-map.md`.

## What changed

- **The admin menu is the 11 workspaces** (Dashboard & Quick Actions … System Configuration), numbered and named as in
  the AOS. Each workspace is one page (`/admin/w.php?w=…`; the Dashboard stays at `/admin/`) whose tabs are its tools.
- **Registry:** `app/lib/Workspaces.php` lists every workspace's sections: each tool's address, whether this is its
  **home** (set up here) or a **use** (worked here), and its status. A section not built yet shows what it will do,
  marked "Coming" or "In part". Every existing admin tool has a place; the older area pages still work at their
  addresses, and a workspace can show one section of one (`?sec=`).
- **Staff levels and departments** (AOS §9A): `staff_accounts.level`, `.department`, `.assists`.
  - Founders and Executives open every workspace.
  - Directors, Managers, Editors, Staff and Creators open their department's, plus Dashboard and Pulse.
  - An Assistant opens what the person they assist opens (not delete or managing staff).
  - A Vendor opens none.

  Old roles map across (owner → Founder, admin → Executive, manager → Operations Manager, editor → Content Editor,
  creator → Content Creator, accountant → Finance Staff, general → Service Staff). Owners set levels at Back-Office →
  Levels & departments (`/admin/staff-levels.php`).
- **The staff gate** (`app/bootstrap.php`) now opens a page when the person's workspaces include it
  (`Workspaces::allowsArea`). People needs the Operations department, and Settings needs Engineering, besides
  Founders and Executives. Inside a workspace, a tab shows only if the person's role reaches its tool.
- **Business teams** (0102 §3–4, AOS §9B):
  - `listing_memberships` holds each business's team and roles. It's seeded from `listing_access` and
    `entities.owner_user_id`.
  - `role_perms` holds the permission table.
  - `Team::can($pdo, $user, $listing, $perm, $need)` is the one check.

  No business page uses it yet; they adopt it as their waves are built.
- **Action log:** `audit_log`, written through `Audit::log()`. Rows are only added.

## Migration

`app/migrations/2026-12-13_workspaces_and_roles.sql` (MySQL-safe, re-runnable). Settings lists it.

## Not in Wave 0

The tools marked "Coming" or "In part" in each workspace are built in Waves 1–6. Support grants and the ticket queue
(0102 §5) come with Service's wave.
