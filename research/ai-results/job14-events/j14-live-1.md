# Events review: LiveEvents.php against decisions/0134 and the Hometown Theater pilot

*Draft for human review.*

## What I could review

The only code attached is `LiveEvents.php`. It is the crawler's Live events, synced from `app/lib/LiveEvents.php`. None of the E1, E2, P1 or P2 code is attached. That includes:

- `app/business/events.php`, `app/lib/Events.php` and `2026-12-05_events.sql` (E1)
- `website/event/index.php` and `listing/view.php` (E2)
- `Events::invite`, `answer` and `codeChoices` (P1)
- the Taking part cards and Send the evening to Maps (P2)

So I can't say whether any of them meets 0134. The findings below come from `LiveEvents.php` and the pilot documents. LiveEvents still matters for the pilot for two reasons:

- **Coming up:** its events show in the listing's "Coming up" (`forEntity`, :219) and in the guide (`forPlace`, :206).
- **Kept separate:** for Jan 1, 0134's note keeps Live events separate from host events.

I can't see `decisions/0164`, which amends 0134 for Live events (R16). Any finding below may already be settled there.

## E1 (host's form)

Code not shown. Nothing to review.

## E2 (event page)

`website/event/index.php` and `listing/view.php` are not shown, so the page itself isn't reviewed. LiveEvents feeds the listing and guide sections that E2 hooks into. Defects 1 to 4 below are on that path.

## P1 (invitations, offers)

Code not shown. One gap is in the documents rather than the code:

- **D. Superseded discount-code scope (med).** 0134's last note ("The pilot's scope: the organizer sells, participants get exposure") drops participant discount codes, publish-a-code and the `event_codes` switch. It says the participant card shows its name, the owner's offer line and a GO Code routing to its location.
  - **Conflict:** `touch-points.md` row F6 still describes P1 as "each takes part with its own discount code and/or line… `codeChoices`". Its "Updates" paragraph says the D3 card "with the restaurant's own code and/or line is built (P2)". Row D3 still points to `BizCodes.php`.
  - **Can't confirm:** whether `codeChoices` exists in code is not shown.
  - **Fix:** remove `codeChoices` and any code display from P1/P2 if they exist. Update F6 and D3 to the offer-line-plus-location-GO-Code scope.

## P2 (taking part)

Code not shown. Gap D above applies here too.

## Defects in LiveEvents.php

**1. A nation's ceremonies can be listed by the crawler (high).**
- **Code:** `LiveEvents.php:103` tells Claude a ceremony or feast at a sacred place "is listed only if the nation or parish announces it as open to the public".
- **Rule broken:** 0134 §2, the tribal nation row: "A nation's ceremonies and sacred observances are never listed unless the nation posts them itself". A crawler listing from an announcement is not the nation posting it, and "parish" widens it further.
- **Existing hold:** `:159` holds protected names for a person, but `setStatus` (:258) lets staff show them anyway.
- **Smallest fix:** change :103 to "Never list a tribal nation's ceremonies or sacred observances; leave them out."
- **Caveat:** 0164 may restate this; not shown.

**2. "Pay at the door" and outside-seller wording can show in price and summary (med).**
- **Code:** `price_text` (:161) and `summary` (:158) are copied from crawled pages after only `strip_tags` and length clipping (:143). They are shown on cards at :245–246.
- **Rule broken:** 0134 §3: "no links to outside ticket sellers and no 'pay at the door' notes". The only exception is the source page as a citation, which is the title link at :244.
- **Pilot risk:** if the crawler picks up the theater's showtimes, #8807's Coming up could read "tickets at the box office". That works against the pilot's "information only… no door note".
- **Smallest fix:** in `check()` (:158–161), clear `price_text`, and remove the matching sentences from `summary`, when they match door/box-office/outside-seller patterns or contain a URL.

**3. The listing's Coming up can show the same event twice (med; pilot touch point D2).**
- **Code:** `keep()` hashes by place key (:168). So a community and its town each store their own row for the same event at the same venue.
- **Inconsistency:** `forPlace` removes such duplicates (:213–214), but `forEntity` (:222–224) does not.
- **Pilot risk:** #8807's listing could show a showtime twice.
- **Rule:** 0134 §1: same venue, same day and near-identical title count as the same event.
- **Smallest fix:** in `forEntity`, apply the same `$seen` title-and-date key as :214 before returning.

**4. A newly held event loses its reason (low).**
- **Code:** at :174, the `ON DUPLICATE KEY UPDATE` assigns `status` before `why`. MySQL evaluates these assignments left to right, so `why = IF(status = 'held', …)` sees the new status.
- **Effect:** a row that was `live` and is now re-found as `held` keeps its old empty `why`. A person in the queue then sees no reason.
- **Rule:** 0134 §7, "waits for a person", needs the why.
- **Smallest fix:** put the `why = …` assignment before `status = …` at :174.

**5. Outside-ticket finds don't become leads (low; Q1).**
- **Code:** nothing in `compose()`/`keep()` (:129–131, :166–176) routes an event with outside tickets to Growth → Prospects.
- **Rule:** 0134 §6b, "Outside tickets we find are leads".
- **Timing:** the Ticket Sales module is moved to Q1 by the pilot note, so this isn't a Jan 1 gap.
- **Smallest fix (Q1):** add a flag in `check()` when the source or price names an outside seller, and hand it to Prospects.

## Open checks (not defects; the code isn't shown)

- **Crawler and host duplicates on #8807:**
  - **Why it matters:** for Jan 1 the two stores are separate. The crawler could list the theater's own community night next to the host's post, in crawler words and with an outside link. That would go against 0134 §1, "the host's words win".
  - **What to check:** how `listing/view.php`:647 merges the two "Coming up" sources.
  - **Does it run for St. Johns?** That depends on whether St. Johns' guide is live and inside the template's `auto_tiers` (:53, default [4, 5]). Neither is shown.
- **Purchase email:** F3 says `MarketCheckout::mailBuyer` is plain text, not the branded template. That code is not attached.

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| LiveEvents.php:103 | Crawler may list a nation's ceremony if the nation or a parish announces it | 0134 §2 (tribal nation); 0066 | high | Prompt: never list a nation's ceremonies or sacred observances |
| LiveEvents.php:158–161, :245–246 | Crawled price/summary can carry "pay at the door" or outside-seller wording | 0134 §3; pilot note (no door note) | med | Clear or trim those fields in `check()` on door/seller/URL patterns |
| LiveEvents.php:222–224 (vs :213–214, :168) | Listing's Coming up can show the same event twice | 0134 §1; pilot D2 (#8807) | med | Apply `forPlace`'s title-and-date duplicate check in `forEntity` |
| LiveEvents.php:174 | `why` assigned after `status`, so a newly held row keeps an empty reason | 0134 §7 | low | Assign `why` before `status` |
| LiveEvents.php:129–131 | Outside-ticket events not sent to Prospects as leads | 0134 §6b | low (Q1) | Flag in `check()`, hand to Growth → Prospects |
| touch-points.md F6, D3, "Updates" (no line numbers) | P1/P2 still described with participant discount codes / `codeChoices` | 0134 last note (pilot scope) | med | Drop codes from P1/P2 (code not shown); update the map |
| E1, E2, P1, P2 code | Not shown | n/a | n/a | Attach the files listed under "What I could review" |