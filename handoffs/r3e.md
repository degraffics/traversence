# Release 3e: the Marketplace, checkout, storefronts and domains on the tokens (2026-11-22)

Design and trips session (session_01CXLi5grMsUgrRMJv3a71F9). **Built on Sync 14:38 UTC (b735f56)** by
`handoffs/r3e/build-3e.py` with 3d's `recolor.py` (each hex onto the token for what it colours, its hex kept as the
fallback; three additions: light words on the umber and badges → `--tv-on-umber`, the gallery's dark strip → `--tv-void`,
an error edge → `--tv-fail`). Zip: `handoffs/r3e/design-3e.zip`. Claimed for `design-3e.zip` (decisions/0161).

## The question first, and its answer
Rebuild or re-skin, brought to the session manager before building (its ask of 2026-11-22). Answer: re-skin, nothing to
ask Jason before building. The layouts are his choices (decisions/0135 "The landing page, reviewed", 2026-11-03, against
`design/marketplace-landing-mockup.png`; its exceptions of 2026-11-13), the re-skin is css-theme-brand.md §4 Release 3,
and the owner's warning is `admin-design.md`'s "business colours kept, owner warned below 4.5:1". His one pop-up is the
release's Approve, naming the warning as the one new behaviour.

## What changes, each with its source
- **Colours only, layouts kept** (css-theme-brand §4 Release 3; brand.md §4's three levels, 2026-11-19): `market/index,
  view, item, cart, order, rules, ticket, download`, `shop/index.php`, `app/ui/market-ui.php`, `app/ui/domains-view.php`.
  Proved: undoing each `var(--tv-…,#hex)` gives back the live file byte for byte (only colours changed).
- **trvgo.us's own pages** (a code not found, a GO Code's page, its proof, a storefront that isn't open): they draw no
  tokens, so the fallbacks carry the three levels' values (page #E4DCC5, card #F6F1E4, edge #E3DBC6, ink #2A1D14, meta
  #5E4F3C, the meta mix of theme-tokens.php).
- **Storefronts keep the business's colours** (admin-design.md): the storefront's own drawing is untouched.
- **The owner's note** (admin-design.md: "owner warned below 4.5:1"; WCAG's text ratio, css-theme-brand §3):
  `Storefront::accentNote()`; in Storefront & pages, under Accent color, an info line "Hard to read: Your accent reads at
  3.6:1 on the Canyon theme's cards…" on load for the saved accent and live as the owner types or picks, or changes
  theme; kept and saved (the Save answer says it too); no ×, it goes when the colour reads well (as 0176 §3's open-issue
  lines, `data-tv-keep`). Under 3:1 Save refuses, unchanged (Storefront.php's existing rule).
- **FAQ** (decisions/0057): "Why does it say my accent color is hard to read?", added before "Can I put my storefront on
  my own domain?".

## Kept / moved / dropped
Everything kept: no control, link, sort, filter, count, tile, rail item, gallery, button or script line changed in the 15
colour-only files (the byte-for-byte check above). Nothing moved, nothing dropped. Order logic untouched: 0135's note of
2026-11-20 (stocked or back-ordered, two-way confirmation) is the order session's, and the orders hold (Jason,
2026-11-21) isn't touched by colours on cart.php and order.php.

## Admin's test
- An owner with an accent under 4.5:1 sees "Hard to read: … 3.6:1 … A darker one fixes it, or keep it as it is." Means:
  their links may be hard to read. Presses: pick a darker colour (the line goes at once), or Save to keep it.
- An owner typing an accent under 3:1 sees "…too close to read. Saving asks for a darker or brighter one." Presses: pick
  another; Save says why it refuses (as before).

## Proof (harness, `tools/design-tests/r3e/`), rendered with sample state; the real run happens on live
| Page | 1280 and 390, before → after: words under 4.5:1 | sideways scroll |
|---|---|---|
| /market/ | 9 → 0 | none |
| /market/view.php (an item) | 6 → 0 | none |
| /market/item.php (a business item) | 3 → 0 | none |
| /market/cart.php (two items) | 2 → 0 | none |
| /market/order.php (a Zelle order) | 1 → 0 | none |
| /market/rules.php, /user/domains/, /shop/domains.php | 0 → 0 | none |
| trvgo.us not found, storefront not open | 0 → 0, page on #E4DCC5 | none |
| Storefront & pages, the accent note | 16 of 16 pressed checks | none |
Not rendered: ticket.php and download.php (colour-only, 8 and 1 values, the byte check covers them); a domain row in
tv-domains.js's list (the harness has no domains; its styles are in domains-view.php, checked by the byte check).
Found, not 3e's: /login.php's "(or your staff ID)" at 3.04:1 (`text-[#8A7355]`), for 3f's leftovers.

## State
Built and sent (this PR); waiting for the session manager's review, then Jason's Approve.
