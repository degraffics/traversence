# Admin design: the palette, one command header, space between groups (2026-11-18)

The design build session's record (session_01CXLi5jkgtUikcdUQ4Wmv28), from Jason's directive
(`design/admin-design-directive-2026-11-18.md`) and brand.md §4's note. Decision note: decisions/0109, 2026-11-18.

## Scope

- Mine: the palette as tokens in the shared admin stylesheet (`app/admin/kit-css.php`), the command header
  (`website/admin/router.php`, its CSS, the `data-ak-cmd` slots in `website/js/admin-kit.js`), the admin kit's spacing,
  radiuses and shadows.
- Not mine: the Control Board's alarm grouping and trip/hold colours (session_01CXLi5ivMY6BdkfHK1xdLWS); the Pulse header
  and stream rows (session_01CXLi5jn2WGptsHBfmhEav6). Each drops its local token copies once this release is live.
- Later, page by page: the public site.

## Release 1: admin-design.zip

Built on Sync 21:45 UTC (2026-11-18). Files:
- `app/admin/kit-css.php`: `:root` tokens; every `.ak-*` colour onto them; the new command header; `--tv-gap` between
  groups; soft corners and warm shadows on containers; rows unchanged.
- `website/admin/router.php`: the header row (filter slot `#ak-cmdf`, Shortcuts moved into the right group), sticks
  right under what's pinned above it (measured, `--ak-top`). The board light was already on the tokens (Control Board 3c).
- `website/js/admin-kit.js`: `[data-ak-cmd]` moves into the header (left, or `="right"`).
- `app/ui/command-strip.php`: the record strip in the same look, measured top.
- `app/ui/faq.md`: one entry; "11 modules" corrected to 10.

Status (21:55 UTC): built and rendered locally at 390px and 1280px, then put on hold by the session manager: Jason is choosing between the #f8f5ec palette and brand.md's original tokens. No claims held, nothing sent. On his answer: re-point the token values, re-check contrast, rebuild on the newest sync, add the :root block to app/ui/page-shell.php (for the member and business Pulse), review again. The question to Jason keeps the 2026-11-12 sage bar.
