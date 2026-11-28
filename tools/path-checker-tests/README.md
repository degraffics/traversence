# tools/path-checker-tests/

The index of this folder, moved here from the root README to keep it a short map (CLAUDE.md "Keep this file lean"). Keep it current when files are added, moved or removed.

## Index (moved from the root README, 2026-11-28)

The path checker's own test (the launch plan's C.2): `staff-in.mjs` with a stand-in sign-in page in `fake/` (check-kit.mjs's staff sign-in never reuses a two-step step, and retries once on the next step; 4 checks); `buy-runners.mjs` (the buying paths' runners on the design harness in QuickBooks mock: plan-buy, then shop-buy, and the guard that refuses anywhere but staging); `token-log.mjs` (the runners' watch on the card's token request to Intuit: a refused request, Intuit's error status, CORS and CSP said with their cause, other URLs ignored; 10 checks, no network); `skip-questions.mjs` with `fixtures/verify-email.html` (sign-up's four questions skipped to "You're all set" and its link, signed in by the link or not, at 1280 and 390; the old first-Skip pick kept as the regression; 13 checks, no server); `slow-copy.mjs` (Staff: send an order's Test copy when Duplicate is slow: the click waited for, the copy and its Send found, a copy with none still said, the old 8 s way kept as the regression; 7 checks, its own stand-in server)
