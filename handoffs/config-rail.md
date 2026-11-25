# config-rail: System Configuration's pages in the side menu (built and held, 2026-11-25)

Jason, 2026-11-25: "the team should be a page in the side menu for the system configuration … Development, board,
workflow, services, team." Built on Sync 18:38 UTC, held for the daily batch (Tier 2).

- **`app/ui/section-rails.php`:**
  - `TV_CONFIG_RAIL` is the one list: key, label, icon, address, module, section, and what it is.
  - `tv_config_rail_children()` turns it into the menu's children. The page open now is marked: Development and the
    Board are told apart by `?tab=development`. One that Access refuses is greyed with why: `Modules::canSection`, then
    `canTool` for a section's table, since Team needs an Executive.
  - `tv_admin_rail()` adds them to System Configuration while it's the open module.
- **`app/ui/app-shell.php`:** a menu item can be `off` (with its reason). It's drawn as the existing greyed "soon" item,
  with the reason as its title and without the "Soon" label. Never hidden (decisions/0109).
- **`app/ui/faq.md`:** "Where are Development, the Board, Workflows, Services and the Team?"
- **The phone's menu** shows them the same way: the shell's own group, opened in place.
- **Tests:** `tools/config-rail-tests/config-rail.cjs` with `router.php`, 20 checks at 1280 and 390:
  - Jason's order and the addresses.
  - The current page marked.
  - Not listed under another module.
  - An Engineering Director (who opens System Configuration but isn't an Executive) sees Team greyed with why, and the
    other four as links.
  - The menu's items stay inside the window. On the bench, the unstyled signed-out header is 412px wide with or without
    this release.
  - No page errors.
- **Screens:** `handoffs/config-rail/config-rail-*.png`.
- **Roll back:** proved.
- **Claims:** taken at send time (`app/ui/app-shell.php::tv_app_open`, `section-rails.php`).
