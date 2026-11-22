# 3f's leftovers: sign-in, long text, standalone pages, member pages and the portal on the tokens (2026-11-22)

Design and trips session (session_01CXLi5grMsUgrRMJv3a71F9). **Built on Sync 20:28 UTC (72e2070)** by
`handoffs/r3f-leftovers/build.py` with 3e's `recolor.py` (two more mappings: #F7F1E3 fields → `--tv-card-a`, #7A6348 words
→ `--tv-meta`; and a declaration that changes no colour is left exactly as it was). Zip `design-3f-leftovers.zip`.
Scope reviewed by the session manager (2026-11-22: "the scope is right. Keep it as one release").

## What changes, with sources
- **Colours only, layouts kept** (css-theme-brand §4 Release 3; admin-design.md "3f sign-in, long text, standalone pages,
  leftovers"; brand.md §4's three levels): login, register, verify-email, reset-password, two-step, faq, pages/view,
  guide/how, guide/resources, plans, q, nexus, verify-listing, claim, ai/index, listing/location, listing/businessportal,
  hub/build, user/notice, user/profile, user/accounts, user/market, user/experiences, user/journeys (index, compose),
  user/trips/trip, business/router, `app/business/portal.php` (the overview card) and `js/tv-password.js` (the checklist
  every password box shares). Proved: with the three fixes below put back, undoing each token gives every one of the 29
  files back exactly (no other change).
- **Three contrast failures, fixed only with existing tokens** (the session manager's condition):
  - Create account: white on #C75A28 read 4.27:1 → `--tv-btn` (the site's primary button, #181's standard), hover `--tv-btn-hover`.
  - A trip stop that's off: white "–" on #CBB98D read 1.93:1 → `--tv-meta` on `--tv-sec`.
  - The password checklist's words: #8A7355 read 3.62:1 → `--tv-meta` (by the mapping).
- **Left out:** order.php, wo.php, user/orders.php and vendor.php (a vendor's work orders): the orders hold (Jason,
  2026-11-21). vendor.php was claimed, then released.
- Left as they were and listed by build.py: status colours (greens, reds, ambers) and a few edges.
- No FAQ entry: nothing a visitor does or reads changes, only colours (decisions/0057 asks for what changed).

## Kept / moved / dropped
Everything kept (the byte check); nothing moved or dropped.

## Admin's test
No new state. Every page looks as before on the site's three levels; the words read better.

## Proof (harness, rendered with sample state; `handoffs/r3f-leftovers/probe-results.txt`, `tools/design-tests/r3e/probe.cjs`)
Words under 4.5:1, before → after at 1280 and 390 (the same both widths); no sideways scroll on any page; no script errors.
| Page | Before → after |
|---|---|
| /faq.php | 526 → 1 |
| /q.php (a sent quote) | 12 → 0 |
| /business/10 (the portal overview) | 10 → 0 |
| /login.php?tab=create | 8 → 0 |
| /reset-password.php | 7 → 0 |
| /user/experiences/ | 7 → 0 |
| /user/market/ | 5 → 0 |
| /claim.php, /user/accounts.php | 3 → 0 |
| /login.php, /nexus.php (a seeded agency), /user/trips/trip.php (a trip with a stop) | 1 → 0 |
| /user/journeys/, /user/journeys/compose.php | 1 → 1 |
| verify-email, pages/view (a seeded page), guide/how, guide/resources, verify-listing, ai, listing/location, hub/build, user/notice, user/profile, plans, two-step, listing/businessportal | 0 → 0 |
The 1 left on the FAQ and journeys is `app/ui/page-shell.php`'s "|" divider (taupe, `aria-hidden`, theme-tokens.php keeps
taupe for rules): not in this release. register.php only redirects to login.php?tab=create. Signed-in pages were seen as a
member (user 2) who owns listing 10 (Core).

## State
Built and sent; waiting for the session manager's review, then Jason's Approve.
