# 0074. Review works in pop-ups: find, compare, fix, save, close

- **Status:** Accepted
- **Date:** 2026-10-10
- **Builds on:** 0055 (admin layout), 0072 (acting on suggestions), 0073 (categories)

## Context

Review sent staff away to other screens to fix anything: Listing Intake to compare or edit a found listing, the listing
editor in another page, and there was no way to find an existing listing from Review at all. A possible duplicate said
"may already be listed" and then asked to merge into "#4636 Karim Victoria Jackson (same phone)" without showing that
listing, so it was hard to tell what was wrong or what the right answer was. Jason: "The best workflow is visual and
popup: fix, save, close."

## Decision

1. **Everything opens over Review.** Each card's button opens a pop-up (`TvAsk.peek`, the page in its embedded view),
   and when it's decided the pop-up shows ✓ for a moment, closes, and the card leaves the list. The page under it never
   reloads or loses its place. Esc or × closes without deciding.
2. **Compare side by side** (`/admin/review-item.php?row=N`). One screen per found listing:
   - **What needs you**, in plain words: "It may already be listed (#7091: similar name, same ZIP + same phone)",
     "No category: choose one", and any other check it failed.
   - The found listing's details as **editable fields**, with each listing it may duplicate (up to three) **in columns
     beside it**: values that match are ticked green, values that differ are amber, empty ones say so. On a phone the
     columns stack: each field, then each match's value under it, labelled with its number.
   - Three answers: **Same business: merge** (fills only what the existing listing is missing, never overwrites; if it
     has everything already, the found one is simply closed), **Different business: add it** (as its own listing, with
     any corrections and the chosen category), or **Reject** (with an optional reason).
   Duplicate cards name the listings they may match, so the reason shows before opening anything.
3. **Find any listing** at the top of Review (`/admin/api/find-listing.php`): by name, phone, address or #id, hidden,
   closed and unclaimed ones included. **Edit** opens the listing editor in the pop-up; the results refresh when it closes.
4. Pop-ups load the shared script (`js/tv-ask.js`) themselves, so inline questions and link-wrapping (0071 notes)
   work inside them too.

## Consequences

- Listing Intake stays for crawling a site and for the rare field-by-field merge; Review no longer sends people there.
- Code: `website/admin/review-item.php`, `website/admin/api/find-listing.php`; changes in `website/admin/crawler-review.php`,
  `website/js/tv-ask.js` (peek: wider, done/close messages, Esc inside), `app/ui/page-shell.php` (embedded pages load
  tv-ask.js; no back link inside a pop-up).
