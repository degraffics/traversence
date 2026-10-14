# 0079. Member standing: notices of action, reviews, and when staff open an account

- **Status:** Accepted
- **Date:** 2026-10-14
- **Builds on:** 0078 (admin dashboard, People & roles), 0062 §4 (Verification), 0077 (privacy choices)
- **Changes:** 0078 §3 (Members): no browsing, a reason to open an account, and notices instead of Suspend

## Context

The first People & roles let staff list the newest members, open any account, see their email and phone, and
suspend them with one button. Jason set the policy (2026-10-11):

- "We don't look at user accounts unless there is an issue or request." Staff see the sign-up basics, enough to check
  that an account meets the system's requirements.
- After sign-up, contact goes through the platform: messages, notifications, system requests and actions. Email,
  phone or address are for official direct contact only, when we can't reach the person on the platform, and only
  when necessary. Sales and support contact is at the user's request, with their key contact.
- User ID, display name, real name, phone and email are only for verification and communication.
- Suspensions: the trust score should flag most of them. The system warns the person through the platform, with
  the steps to put it right. Every action the system takes is a notice of action. When access is revoked, an email
  states what happened, how to recover, and links a request for an admin review.
- "Act, then react", with 24 hours to answer at each step:
  1. a warning, with steps to correct and 24 hours to confirm;
  2. no confirmation: limited functionality, with a final warning;
  3. access revoked. Serious violations go straight here, and "then figure it out, if disputed".
- Postal addresses are business addresses only.

## Decision

1. **The ladder** (`app/lib/Standing.php`, tables `member_actions` and `member_action_log`):

   | Step | What happens | The member can |
   |---|---|---|
   | 1. Warning | A notice: what happened, what to do, by when (24 hours) | Confirm "I'll follow these steps" (closes it), or ask for a review |
   | 2. Limited, final warning | No posting, commenting or messaging; browsing, saving and reading still work; 24 more hours | Confirm (closes it and lifts the limit), or ask for a review |
   | 3. Access revoked | Signed out everywhere; can't sign in; an email with what happened, how to recover, and a private review link | Ask for a review from the link (no sign-in needed), or have the link sent again |

   - **A serious violation** (threats, scams, someone's private information) starts at step 3. Staff choose it from
     the member's card. It takes effect at once, and the email goes out at once.
   - **Asking for a review stops the clock.** Staff decide:
     - **Lift:** everything is restored, and a revoked member is emailed that they can sign in;
     - **Uphold:** the 24 hours restart; a revoked account stays revoked and the notice closes.
   - A second problem while a notice is in force adds to the same notice, so one problem never stacks into several.
   - Posts aren't deleted at any step. Removing a post stays with Reports.
   - The clock runs on ordinary page loads, at most every five minutes, so no cron job is needed. For exact timing,
     add `app/scripts/standing_tick.php` as a cron job.
2. **Contact goes through the platform.** A notice shows in three places on Traversence:
   - a bar under the top bar on every page;
   - the first item in the member's Pulse, in every role;
   - the page `/user/notice.php`.

   Email is used only when access is revoked (the member can't sign in to see the notice) and to say a review restored
   them. The sign-in error for a revoked account points to that email and to `/user/notice.php`, where the review
   link can be sent again (only to the account's own email, at most once an hour).
3. **The trust score flags them.**
   - A staff take-down after reports (`Reports::decide` remove) lowers the author's trust by 5
     (`TrustScore::USER_CONTENT_REMOVED`). A restore gives it back.
   - Below `USER_REVIEW_THRESHOLD` (0), a warning opens on its own.
   - A confirmed email gives +10, so a new account with no confirmed email is warned on its first take-down, and a
     confirmed one on its third.
   - Staff can also send a warning themselves.
4. **Staff open an account only for a reason** (table `member_access_log`):
   - **Finding a member:** by name, #number, or the exact email from their request. There's no list of members to
     browse.
   - **Opening a member:** staff choose why (a report, their request for help, a claim, a trust-score flag, a sign-up
     check, a notice or review) and can add a note. It's recorded with their name, and the card lists who opened it,
     when and why.
   - **The card shows the sign-up basics:**
     - email confirmed;
     - 18 or older;
     - Verified, with each check;
     - roles, with their listings;
     - privacy choices;
     - standing (the notice in force and earlier ones).

     It no longer shows email, phone, area, comment count or reports sent.
   - **Email and phone** are a separate "Show email and phone" for official contact only, recorded on its own.
   - **Who can do it:** the People role (staff Owners and Managers). Not on yourself, and not on anyone with access to
     all of Traversence.
   - **Old suspensions:** an account suspended before notices existed can be restored from its card.
5. **Admin → Safety → Standing:** reviews asked first (with what the member wrote, and Lift and Uphold), then the
   notices in force with their next step, then the last week's closed ones. Today and the Safety badge count the
   reviews waiting.
6. **The FAQ and the Privacy Policy say exactly this:**
   - FAQ: "Who can see my email?", "What can Traversence staff see about my account?", "How will Traversence contact
     me?", "Can my account be limited or revoked?" (replacing "Can my account be suspended?") and "What lowers my
     standing?";
   - Privacy Policy: a new section, "When our staff look at your account", plus how we contact you and how long
     notices are kept.

## Consequences

- Run `app/migrations/2026-11-17_member_standing.sql` before uploading. Until it's run, the People card says so and
  no one is limited.
- The limit is enforced inside the posting functions (`Standing::guard`), so every page and endpoint that uses them is
  covered:
  - `Engage::comment`;
  - `Community::create` and `Community::post`;
  - `Journeys::save`, `Journeys::addPhoto` and `Journeys::comment`;
  - `Experiences::save` (except staff);
  - `BizMessages::personSend`;
  - `People::send`.

  A new way to post must call it too.
- `api/auth/session.php` now issues a fresh form token when a session has just ended (signed out elsewhere, or access
  revoked). Without it, a revoked member opening the review link in the same browser couldn't send the request.
- Next:
  - more trust signals (a report found malicious, a claim confirmed);
  - a notice sent as a message in the member's inbox, as well as on the page;
  - "Request admin review" for business owners' listing actions on the same pattern.
