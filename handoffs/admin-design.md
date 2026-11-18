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

## The brief

Jason's final directive, `design/css-theme-brand.md` (it replaced `design/admin-design-directive-2026-11-18.md`):
brand.md's established palette, plus red #a62b2b and amber #8f5600; three releases. The token NAMES (`--tv-canvas`,
`--tv-ink`, `--tv-line`, `--tv-fail`, `--tv-hold`…) are shared with the Control Board and Communications sessions.

## Release 1: design-r1-tokens.zip (built on Sync 21:45 UTC)

- `app/ui/theme-tokens.php` (new): `tv_theme_tokens()`, the tokens, the fonts' @font-face, focus rings, reduced motion.
- `app/ui/page-shell.php`: draws it in the head (replacing the inline vellum background).
- `app/ui/app-shell.php` (`tv_app_css`): the rail and workspace on the tokens; also draws them for a page without the head.
- `website/fonts/`: Inter (variable), Merriweather 300/300 italic/400/700, Playfair Display 400/400 italic/600/700, with
  their OFL licences (Jason's CDN link named these; self-hosted so card pages' `font-src 'self'` allows them).
- `app/ui/faq.md`: one entry on the fonts.
- Contrast (against #EFE3C8 / #E6D8B5 / #D8C79D): ink 12.8; taupe 4.11 / 3.70 / 3.14 (fails as text, so `--tv-meta` =
  taupe + 35% ink: 6.2 / 5.59 / 4.73); `--tv-line-strong` 3.35; rail labels 2.97 → 7.4 on umber.
- Rebuilt on Sync 22:06 UTC. Reviewed by the session manager; Jason approved 2026-11-18 (darker metadata mix, #92400E kept and added to brand.md, fonts on our own server). Send waits for control-board-3e (also carries faq.md) to go live.

## Release 1: live as #146 (2026-11-18 22:12 UTC)

Checked on traversence.com: the fonts served as font/woff2; the tokens in the page; Inter loaded; no sideways scroll at
390px and 1280px.

## Release 2: design-r2-admin.zip (built on Sync 22:13 UTC)

- `app/admin/kit-css.php`: the kit on Release 1's tokens. Cards are the micro-gradient, inputs the vellum. OK text is
  moss + ink (6.75:1). The command header is one Deep Umber row joined to the top bar.
- `website/admin/router.php`: the header row (filter slot `#ak-cmdf`, Shortcuts on the right) and the measured top
  (`--ak-top`).
- `website/js/admin-kit.js`: the `[data-ak-cmd]` slots.
- `app/ui/command-strip.php`: the record strip in the same look, with the measured top.
- `app/ui/faq.md`: the bar's entry; 10 modules.
- Pressed in a local render at 390px and 1280px: the sections menu, Shortcuts, an At hand drawer, Ctrl K, and the slot.
  No sideways scroll.
- Reviewed by the session manager; Jason approved 2026-11-18 and retired the 2026-11-12 sage bar and peach tab. Rebuilt on
  Sync 22:18 (FAQ entry only re-applied); sending waits for control-board-3f (also carries faq.md) to go live.
