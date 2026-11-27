# M-sections: the Marketplace's four sections (built on Sync 2026-11-27 02:50 UTC)

**Sources:**
- decisions/0135's note (Jason, 2026-11-27);
- the CEO's rulings (Tier 4, recorded on 0135, PR #1527).

**The order** (the session manager): C.4 → place-list → C.8 → **M-sections** → C.5a → C.5b → the stylesheet's zip-check rule → the stylesheet.

## What's in it

| File | Change |
|---|---|
| website/market/index.php | Tabs: Browse all (the landing, unchanged) and the four sections. `?section=` sets the grid's source (shops = businesses, aftermarket = people) or adds `regional`; Trade is a "coming" panel with a way to the Shops. Each section has its line and FAQ link, and the heading names the section. `?from=` from Filters lights its section. |
| app/lib/MarketBiz.php | `REGIONAL_KINDS` (ticket, booking, harvest, madetoorder); browseWhere()'s `regional` filter (those kinds plus their legacy aliases, with geo_hub_id in geo_hubs); `madeIn()` gives "Made in <geo hub name>". |
| website/market/item.php | The "Made in" label under "From … in …", opening the section. |
| app/ui/section-rails.php | The four sections in the Marketplace menu. The current line is chosen by `section`. The Your posts badge is found by key. |
| app/ui/faq.md | Three new entries; "What is the Marketplace?" now names the sections. No question is reworded. |

## Tests
- `tools/market-tests/m-sections.cjs` with `m-sections-seed.sql`: rendered at 390 and 1280 on the design harness, **50 checks passing**.
- Screens: `screens/`.

## Before sending
1. Rebuild faq.md on the live copy. It's blocked by b2-backups-3, and C.8 adds FAQ entries too.
2. Re-run plan-buy, shop-buy, market-buy and the post path. /market/'s landing is unchanged, so the runners open as before.

## Not in it (the CEO's rulings)
These come in Q1:
- services in Regional (a "guided" flag);
- physical goods (a "made here" tick);
- the Native-made field, notice and attestation box.
