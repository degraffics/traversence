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
  Sync 22:26 (FAQ entry only re-applied) and sent.

## Release 3: public pages, one area at a time (order approved by the session manager)

3a the site frame; 3b Home and Explore (Discovery's hero, stock bylines); 3c Get Local; 3d Community, the member
dashboard and Pulse's new look (pulse-ui.php is ours now, look only, notify-tests must pass); 3e Marketplace, checkout
and storefronts (business colours kept, owner warned below 4.5:1); 3f sign-in, long text, standalone pages, leftovers.

### 3a: design-r3a-frame.zip (built on Sync 22:55 UTC)

- `header.php`, `brand.php`, `footer.php`, `context-switcher.php`, `page-shell.php`, `auth-nav.php::tv_nav_head`,
  `js/tv-ask.js`: every colour on the tokens (mapped by property: text, background, line). Text 4.5:1 or better,
  checked by a probe in the render. Errors, the remove confirm and the spelling mark use Crimson Thread; System Red stays
  for the 911 line and a final account warning. `header.php` and `tv_nav_head` draw the tokens for pages with their own
  head (the home page, the dashboard, sign-in): without them the home page's bar went see-through (Jason saw it).
- Headings: Tailwind's `font-serif` and `font-display` mapped once in `page-shell.php`.
- Edit page switch: `Blocks::editingOn()` (cookie `tv_edit=1`, set from the profile menu, `[data-cx-edit]`); `zone()` and
  `site()` edit only with it on; storefronts unchanged. Test: `tools/design-tests/edit-switch.php`, 9 pass.
- The empty zone above the footer: `Blocks::site()`'s wrapper gets `width:100%` (auto margins in the flex column shrank it).
- Found and shipped first: `faq-restore.zip` (#150), the FAQ text #148 removed by building on an older sync.
- Left for their areas (contrast below 4.5:1 found by the probe, same before and after): the home hero's text (3b), the
  admin Shortcuts list's group labels (admin-hub.php) and the dashboard's small labels, Pulse's column headers (3d), the
  FAQ's anchors and "Help" kicker (3f).
- Approved by Jason 2026-11-18 (reviewed by the session manager): 3a as built; headings everywhere now; errors in
  Crimson Thread, red for emergencies; trails and routes to a new nature/navigation colour in the map's release (3b/3c,
  brand.md §4 note). Next kit-css release: fold in the admin contrast items above (Shortcuts labels, dashboard labels).
