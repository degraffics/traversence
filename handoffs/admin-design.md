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

### Edit site icon: design-r3a2-edit-site.zip (PR #985, approved by Jason)

The Edit page switch moved from the account menu to an icon in the account bar, beside ⌂ and sign-out (Jason, 2026-11-18).

### Headings sweep: design-headings.zip (built on Sync 00:09 UTC)

3a's single setting reached only Tailwind's `font-serif` (on the live FAQ, 14 of 15 headings stayed the device serif). The
sweep replaces each serif stack after `font:`/`font-family:` with `var(--tv-font-head,<the same stack>)`: 288 places in
141 files, font only (undoing it returns every live file exactly). Left: `Mailer.php` (emails), `MarketCheckout.php`
(the order packet), storefront themes' `var(--tvb-hf,…)`. Rendered: FAQ 15 of 15 and home 4 of 4 serif headings in
Merriweather. `sections.php` claimed per function (the Control Board holds `tv_admin_mod_dbupdates`).

### Queue for the next admin (kit-css / command header) release

- At 390px the module name is cut off ("10. System Co…") beside a section dropdown that says nearly the same thing
  (session manager, 2026-11-19). One header per view (css-theme-brand §1): on a phone, show only the sections ▾ (it
  names the place), or the module's short name; never a truncated duplicate.
- Contrast below 4.5:1 found by 3a's probe: the Shortcuts list's group labels (`admin-hub.php`, #8A7355 on #FFFBF2,
  4.36:1), the dashboard's small labels (`.ad-k`, `.ad-*` in `admin-dashboard.php`), Pulse's column headers
  (`.ps-sort`, `.ps-flt`; 3d).

### 3b-1: design-r3b1-explore-2.zip (built on Sync 00:22 UTC, by `build3b1.sh`, replayable)

- `explore-hero.php`: `fallback_slides` (the page's own landmark photos) before stock; stock only with no real photo.
  Test: `tools/design-tests/hero-order.php` (6 pass; the live code fails 4).
- `discovery/index.php`: Parks passes its place and "national park", Experiences "outdoor experience"; its styles on the
  tokens; the timeline's dates in link brown; "Join the team" white on brand brown.
- `stock-ui.php`: the credit on the photo with an i (Jason, 2026-11-17); styles on the tokens with fallbacks (storefronts).
- `website/index.php`: its own styles on the tokens (the door tone table kept, data); kickers in link brown.
- FAQ: the stock photo answer (question unchanged, its anchor is linked).
- Jason's answer (2026-11-19): "Mostly ok. I dont care for the button colors under the hero": the tabs under the hero
  (`.xh-tabs`) back to light cream (white 55% into the vellum) with copper icons (`--tv-icon`), the sage bar on the open
  one. The cause: the recolor mapped the old gold accent `#B8863B` as text to `--tv-on-umber-meta` (a light-on-dark
  colour) on light surfaces; it now reads as `--tv-icon` across Discovery (tiles, places, the plan's ▾), and "More" is
  white on brand brown. For later sweeps: `#B8863B` as an icon is `--tv-icon`, as a fill `--tv-link`.
- Route colour (Jason, 2026-11-19: "Electric lime for activity like trail"): `--tv-route:#B6F22E` over
  `--tv-route-casing:#3E2B1F` in `theme-tokens.php`, routes and trails only; never text (1.05:1 on the vellum). brand.md
  has the note. The first map that draws routes uses both (casing ~2px wider under the lime).
- Live as Release #158, but the 00:31 UTC sync ran during its publish and put the four `app/` files back (kept in the
  sync repo's `conflicts/2026-11-19/`); the website files passed. Cause: the sync's docs checkout was older than #158's
  delivery record, so the new bytes looked like an unreleased edit. Re-sent as `design-r3b1-tabs.zip` (built on Sync
  00:35, `--allow-pending design-r3b1-explore-2`, which `send.py` now passes on to the zip check).
- The open tab (Jason, 2026-11-19: "The active state feels lacking"; options in `design/review/design-r3b1-active-tab.png`,
  he chose B): a sage fill (sage 38% into white), a full-width 4px moss-sage bar, the icon umber with the active glow.
- 3b-1 complete: live as #158 and #159, verified in Sync 00:42 UTC.

### Admin command header on phones: design-admin-cmd-phone.zip (built on Sync 00:47 UTC; waiting for Jason's OK)

- Jason, 2026-11-19, on the Control Board at 390: "Command needs help" (three rows, 127px, "10. System Co" cut).
  `kit-css.php`'s phone block only: row one Back, the sections dropdown, the board light, Shortcuts (`.ak-cmdr` as
  `display:contents`, ordered); row two the page's filter and the tools at hand (a zero-height break, `.ak-cmd::after`).
  The module's name is hidden on phones (css-theme-brand §1, one header per view); Back returns to the module. 127 → 94px.
  Render: `design/review/design-admin-cmd-phone-390.png`.

### 3b-2: design-r3b2-content.zip (built on Sync 00:47 UTC; waiting for Jason's OK)

- On the tokens: `listing/view.php`, `hub/index.php`, `place/landmark.php`, `place/recreation.php`, `gallery/index.php`,
  `experiences/view.php`, `journeys/view.php`, `app/ui/engage-ui.php`. `recolor.py` learned the gold `#B8863B` (icon →
  `--tv-icon`, fill → `--tv-link`) and a few more tans, greens and ambers.
- Contrast: 37 probe failures → 0 real ones (what's left: text over photos and gradients the probe can't read; the
  harness's compiled Tailwind lacks classes the CDN makes). Hold text on `--tv-hold-bg` is 4.04:1 with the token as is:
  these pages use hold 75% into ink (5.25:1). For the tokens' next release: darken `--tv-hold` text use, or lighten
  `--tv-hold-bg`, platform-wide.
- Listing badges in the palette: Verified on moss mixed into ink; Managed by owner white on Grounded Amber (white on
  Tailwind's amber was 3.2:1).
- Park cards' photos (`dv_park_cards` → `dv_card_photos`, Jason's screenshot 2026-11-19), decisions/0143's order: a
  listing's hero photos, the Commons photo of the visible landmark of the same name and state (`Landmarks::visibleSql`),
  a stock photo labelled "Stock photo (representative)" with its credit (slot `card`, owner `park:<id>`), else the
  pattern. Three queries for the set. FAQ: "Where do I find parks and landmarks?" describes it.
- Rendered on the harness with seed records (a listing, landmarks, a hub, a journey, an experience):
  `design/review/design-r3b2-390.png`, `-1280.png`.
- 3c next: Get Local (directory, its own Find a business box and Refine row).
