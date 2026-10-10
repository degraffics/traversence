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

5. **Obvious duplicates merge on their own** (added 2026-10-10, Jason: "Why am I having to merge these when it is obvious
   it is a duplicate?"). A found listing is the same business when one existing listing has the same phone, the same
   street address and ZIP, and nearly the same name: at least three in four of the words of the longer name, with
   short forms read in full ("Svc" is "Services", "Ctr" "Center", "Mntns" "Mountains") and "of", "and", "Inc", "LLC"
   ignored. It then merges the way a person's "Same business" does: it fills only what the existing listing is missing
   and never overwrites; with nothing new, the found one is closed. The crawler does this as it stages, and Review
   sweeps whatever is already waiting each time it opens, saying how many it merged. When two existing listings both
   look the same, a person decides. Each automatic merge keeps its reason in the row's note ("auto-merge: the same
   business as #7091 (same phone, street address and ZIP; 100% of the name in common)"). The first sweep of the local
   copy merged about 80 waiting rows, every one plainly the same business.
6. **Same place is not a duplicate.** A clinic or center often shares its address and phone with the providers and
   offices in it (Apache Behavioral Health Services, a counselor there, the tribe's office). An existing listing that
   shares the phone or address under a clearly different name (under two in five of the words) is shown as **Same place,
   different name**: "most likely providers or offices at the same facility, not a duplicate", in green on the card,
   last in the comparison, and it no longer holds the found listing back as a possible duplicate. Linking a facility
   and its providers is in `future-considerations.md`.
7. **Category guesses use what the business says it is.** Guesses already weigh words in the name (3), the offerings,
   registry taxonomy included (2), and the description (1). A business's own website often declares its kind in
   schema.org terms ("Dentist", "GeneralContractor"); the crawler now keeps it and weighs it most (6). A guess with only
   the name to go on (like "Home Builders" for "Bonney Home Inc", from the word "Home") says so: "isn't a sure match (only
   its name to go on)", and a person picks.
8. Review lists the most confident waiting listings (it read the first 500 by source before, so some never showed),
   and a pop-up opens any waiting row, however many are waiting.

## Consequences

- Listing Intake stays for crawling a site and for the rare field-by-field merge; Review no longer sends people there.
- Code: `website/admin/review-item.php`, `website/admin/api/find-listing.php`; changes in `website/admin/crawler-review.php`,
  `website/js/tv-ask.js` (peek: wider, done/close messages, Esc inside), `app/ui/page-shell.php` (embedded pages load
  tv-ask.js; no back link inside a pop-up).
- Code for 5–8: `app/lib/crawler/AutoImport.php` (`sameName`, `relation`, `matchIds`, `obvious`, `fillGaps`,
  `sweepObvious`; `evaluate` and `categoryFits` read the declared kind), `app/lib/crawler/IntakeStager.php` (`merge` and
  `reject` take a null person for automatic work; `oneRow`; `listRows` best-first; category guesses weigh the declared
  kind), `app/lib/crawler/ListingExtractor.php` (keeps the schema.org kind as `what.types`), `website/admin/review-item.php`,
  `website/admin/crawler-review.php`.
