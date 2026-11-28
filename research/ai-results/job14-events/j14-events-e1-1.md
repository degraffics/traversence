# Events E1 review: the host's form against decisions/0134 and the Hometown Theater pilot

*Draft for human review.* Line numbers are as printed in the attached files. The pilot handoffs and 0134 have no printed line numbers, so I cite them by section.

## What this covers

| Part | Covered by the attached code? |
|---|---|
| **E1** (the store and the host's form) | **Yes.** `Events.php`, `business/events.php`, the portal, router and API wiring, `upload-targets.php` (event_photo), `staging-tables.php`. The migration `2026-12-05_events.sql` is listed (README:6) but **not shown**. The two FAQ entries (README:13) are **not shown**: faq.md is cut off at 80000 bytes. |
| **E2** (the event page) | **No.** `website/event/index.php` and the `listing/view.php` hook are not attached. The code itself says the page "come[s] with the next release" (business/events.php:5, :24). |
| **P1** (invitations, offers) | **No.** Nothing in these files: no `Events::invite` or `answer`, no Notify kind. |
| **P2** (taking part) | **No.** Nothing in these files. |

For E2, P1 and P2: **nothing found because nothing is shown.** They need their own review.

---

## E1 defects

### High severity

**1. No member Verification check on the poster**
- **Where:** Events.php:39–46 (`canPost`)
- **Rule:** 0134 §2 says "Every host's poster is a verified member; a business also needs its claim." Jason's answer #1: "Verification: required to create an event."
- **Defect:** `canPost` checks only that the listing is not `unclaimed` and the role's `catalog` edit. It never checks the person's Verification (0062).
- **Fix:** In `canPost`, also require the user's Verification to be on. Refuse with "Turn on Verification to post events" and a link to where it's done.

**2. Editing a published event skips the automated checks**
- **Where:** Events.php:95–103 (the `save` update path), compared with :132–136
- **Rule:** 0134 §2: a business event "publishes at once after the automated checks (0005)". §7: the date must be ahead.
- **Defect:** `checkWords` (MarketRules) and the past-date check run only on `publish`. A host can publish clean text, then edit a published event to add held words or a past date. The edit goes live unchecked.
- **Fix:** In `save`, when `$old['status'] === 'published'`, run the same `checkWords` and past-date checks before the UPDATE, and throw on a hold. Alternatively, move the event back to draft with the reason.

**3. Cancelling a ticketed event leaves its tickets on sale and buyers untold**
- **Where:** Events.php:125 and :137–140 (`act`, cancel); also save :95–103 for time changes
- **Rule:** 0134