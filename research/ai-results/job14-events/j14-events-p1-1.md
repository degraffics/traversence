# Events P1 review: draft for human review

**Scope read:** `app/lib/Events.php`, `app/business/events.php`, `app/lib/Notify.php`, `app/lib/staging-tables.php`, `website/business/api.php`, the README, and `faq.md` (truncated at about line 311).

**Coverage:**

| Part | Covered? |
|---|---|
| **E1** (the host's form) | Yes. Form at `events.php:45–68`; save and moves at `Events.php:88–141`, `:406–451`. |
| **E2** (the event page) | Partly. Only the library side: `publicGet`, `forEntity`, `forPlace`, `ticketOf`, `buyerOrder`, `html` (`Events.php:183–272`). `website/event/index.php`, the listing's "Coming up" hook, comments, the engage bar and view counts are **not shown**. |
| **P1** (invitations, offers) | Yes, fully (`Events.php:274–402`, `events.php:26–43`, `:100–110`, `Notify.php:100`). |
| **P2** (taking part) | **Not shown.** Only the `participants(..., $public = true)` query is present (`Events.php:288–294`). The participant cards, "Part of <event>", the trail and Send to Maps can't be reviewed. |

---

## Defects

### E1: the host's form

**1. Verification isn't checked before posting. Severity: high.**
- **Where:** `Events.php:39–46` (`canPost`) checks only that the listing is claimed and the role has Catalog edit.
- **Rule broken:** 0134 §2 ("Every host's poster is a verified member; a business also needs its claim") and Jason's answer 1 ("Verification: required to create an event").
- **Effect:** `save`, `act`, `invite` and `answer` all gate on `canPost`, so an unverified team member can post, publish and invite.
- **Fix:** In `canPost`, after the claim check, refuse when the user's Verification (0062) is off. Give the reason in words. The Verification lookup isn't shown; use the existing one.

**2. The word checks and the date check run only on Publish. Editing a live event, or Put back on, skips them. Severity: high.**
- **Where:** `checkWords` and the past-date check run only `if ($what === 'publish')` (`Events.php:132–136`).
- **Gap 1:** `save` on a published event (`Events.php:95–103`) updates the title, description and showtimes with no MarketRules check, and leaves it published.
- **Gap 2:** `restore` (cancelled → published, `:126`) re-publishes with neither check.
- **Rule broken:** 0134 §2 ("publishes at once after the automated checks (0005)") and §7 ("the date is ahead"; spam and safety checks "applied to every event").
- **Fix:** In `save`, when `$old['status'] === 'published'`, run `checkWords` and the date check on the new values and throw if held. Run the same two checks for `restore`.

**3. The rule against outside links and "pay at the door" notes is easy to slip past. Severity: medium.**
- **Where: