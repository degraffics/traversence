# Events E2 review: `handoffs/events-e2` against decisions/0134 and the Hometown Theater pilot

*Draft for human review. Severities are my judgement. Where the code isn't in the attached files, I say "not shown".*

## Coverage

| Part | What's attached | Covered? |
|---|---|---|
| **E1** (the host's form) | `app/business/events.php`, the E1 half of `app/lib/Events.php` (canPost, save, act, clean, checkWords) | Yes. The migration `2026-12-05_events.sql` and `tv_biz_can` are not shown. |
| **E2** (the event page) | `website/event/index.php`, the public half of `Events.php`, `Engage.php`, `Links.php`, `staging-tables.php`, `listing/view.php`, `hub/index.php`, `faq.md` (truncated at 80,000 bytes) | Yes. `engage-ui.php`, `explore-hero.php`, `MarketRules`, `Team::can` and `KindCheckout` are not shown. |
| **P1** (invitations, offers) | No code | **Nothing to review.** `Events::invite`, `answer` and the Notify kind `biz.event_invite` (touch-points F6) aren't in these files. One document conflict is noted below. |
| **P2** (taking part) | No code | **Nothing to review.** The event page (`event/index.php`) has no participant section, and the listing has no "Part of <event>" line. |

---

## E1: the host's form

**E1-1. No Verification check on who posts. High.**
- **Where:** `Events.php:39-46` (canPost) checks only that the listing isn't `unclaimed` (:43) and that the role has `catalog`/`edit` (:44).
- **Rule broken:**
  - 0134 §2: "Every host's poster is a verified member; a business also needs its claim."
  - Jason's answer 1: "Verification: required to create an event."
  - §6b's onboarding: "Owner or Manager, on a claimed listing, with Verification on."
- **Also:** `claimed-unverified` and `pending-verification` listings pass the claim check (compare `view.php:155-156`, "claimed by email, not confirmed yet").
- **Fix:** in canPost, also require the user's Verification flag (the 0062 