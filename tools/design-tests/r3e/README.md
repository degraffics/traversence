# Release 3e's rendered checks (2026-11-22)

Run on the design harness (`handoffs/design-harness/README.md`), the release's `out/` laid over the mirror on :8790 and the
live mirror alone on :8791 (trvgo.us's router on :8792 and :8793), with Tailwind's browser build from
`@tailwindcss/browser@4` served in place of its CDN.
- `probe.cjs`: each page before and after at 1280 and 390: words under 4.5:1 (3:1 for large), sideways scroll, script
  errors, the page and card colours, screenshots. `PAGES=…` (comma list), `SID=` for a signed-in cookie, `BP`/`AP` ports.
- `accent-note.cjs`: the owner's note in Storefront & pages, pressed: on load for a saved accent (#C2701F, 3.6:1 on Canyon),
  typing a dark one hides it, a light one (#E8B070) says saving asks for another, the Night theme re-reads it, no sideways
  scroll, Save keeps the colour and says the note, Save refuses one under 3:1 as before. 16 checks.
Seed: member user 2 owns listing 10 (plan `core`, `listing_memberships` owner), a live storefront `pine-cafe` (canyon,
accent #C2701F), five `market_posts`, two `market_items` in user 2's cart, one manual (Zelle) `market_orders` row with
its lines and `hold_until`.
