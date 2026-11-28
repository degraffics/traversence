# Security review: `api/auth` sign-in endpoints (draft for human review)

**Scope and limits.** No decision documents were attached. Wherever I give a rule, it is one of two things:
- a decision or section that the code's own comments cite, or
- marked **general** (common practice, not a written decision I can see).

Most of the security-relevant logic lives in files that are **not shown**:
- `Auth` (`attemptLogin`, `resetPassword`, `register`, `changeCurrentPassword`, `logout`, `currentUser`)
- `GoogleAuth`, `SocialAuth`, `StepUpAuth`, `Captcha`, `TestAccounts`, `Response`
- `bootstrap.php`

Findings that depend on those files are marked "verify".

---

## apple.php / facebook.php / microsoft.php
Each file only calls `SocialAuth::endpoint(...)` (apple.php:10, facebook.php:10, microsoft.php:10). `SocialAuth.php` is not shown, so **nothing reviewable in these files**.

## login.php
1. **No brute-force protection (high).** login.php:27-30 says outright that a rate limiter "belongs in front of this endpoint… not yet implemented." The only other control is the captcha, which is "off until its keys are in .env" (login.php:11, decisions/0097). Until those keys are set, password guessing is unlimited.
   - Rule: the code's own comment (Phase 9 hardening); decisions/0097.
   - Smallest fix: a per-IP and per-account failure counter that returns 429 after N failures in a window, checked before `Auth::attemptLogin` at :23.
2. **Captcha bypass keyed on the email (med, verify).** login.php:14 skips the captcha when `TestAccounts::checkRequest($email)` is true. The comment at :13 says decisions/0145 §3 requires "their token", but only the email is passed in. If `checkRequest` does not also check a secret token from the request (not shown), anyone who knows a test-account email bypasses the captcha.
   - Fix: make sure `checkRequest` requires a server-side secret, compared with `hash_equals`.
3. **Account enumeration via 409/403 (med, verify).**
   - login.php:25-26 return distinct `closing` and `unconfirmed` responses.
   - The comment at :27-28 says the endpoint must not reveal which emails have accounts.
   - If `attemptLogin` raises code 4090 or 4030 before the password is checked (not shown), those responses reveal that the account exists.
   - Fix: raise those codes only after the password has verified.
4. **Session fixation (not shown).** Whether `attemptLogin` calls `session_regenerate_id(true)` cannot be seen. It needs checking.

## logout.php
POST + CSRF (logout.php:7-8). `Auth::logout` is not shown. **Nothing found.**

## session.php
- **Nothing found** in the file itself.
- It returns `$user` (session.php:84). The fields returned by `Auth::currentUser()` are not shown, so whether private columns leak cannot be confirmed.

## register.php
5. **No abuse throttle while the captcha is off (med).**
   - register.php:14 depends on the captcha, which is off until keys are set (decisions/0097).
   - :82-88 sends a verification email to any address supplied.
   - Result: unlimited account creation and email-bombing of third parties.
   - Fix: a per-IP rate limit before :34.
6. **Private data in logs (low).** register.php:93 writes the email address to `error_log`. This is a general rule. Fix: log the user id instead.

Injection: :74 uses a prepared statement. No issue.

## resend-confirmation.php
7. **Rate limit is per session only (med).**
   - resend-confirmation.php:14-16 keeps the counter in `$_SESSION`.
   - Dropping the cookie resets it, so the "3 per hour" limit at :3 is not enforced against a scripted caller.
   - There is no captcha. Each call can send mail to an unconfirmed account's inbox (:25).
   - Fix: also key the limit on the target email and the client IP, stored server-side.
8. **Timing enumeration (low).** The DB write and mail send happen only for an existing, unconfirmed account (:22-27), so response time differs. This is general. Fix: send the mail after the response, or make the work constant.

## password/request-reset.php
9. **No throttle while the captcha is off (med).** request-reset.php:14 (decisions/0097). Reset mail can be sent to a victim without limit. Fix: per-IP and per-email limits.
10. **Timing difference reveals existing accounts (low).**
    - The send at :26 happens only when a token exists. This undercuts the no-enumeration promise in the comment at :36-38.
    - Fix: queue the mail, or send asynchronously.
11. **Email in logs (low).** request-reset.php:33. General. Fix: do not log the address.

## password/reset.php
- **Nothing found** in this file.
- Token entropy, single use, and invalidation of other sessions after a reset all live inside `Auth::resetPassword`, which is not shown.

## change-password.php
12. **No limit on guessing the current password (med).**
    - change-password.php:24-28 gives an unlimited oracle to anyone holding a stolen session.
    - Fix: count failures per user and return 429 after N.
13. **"Other sessions signed out" claim not shown (verify).** The message at :53 says other sessions were signed out. Whether `changeCurrentPassword` actually does this (and rotates this session's ID) is not shown.
14. **Email in logs (low).** change-password.php:50.

## email/request-change.php
15. **Password oracle with no limit (med).** request-change.php:32 has the same gap as #12. Section 20.1 is cited at :28 as the defence against stolen sessions. Fix: a failure counter.
16. **Reveals whether an address is registered (low).**
    - request-change.php:36-39 returns `email_taken` for any address.
    - It does require the caller's password first.
    - Fix: respond generically and resolve the collision at confirm time, which confirm-change.php:37-41 already does.
17. **Email in logs (low).** request-change.php:59.

## email/confirm-change.php
18. **OTP brute force not shown (verify).** confirm-change.php:25 relies on `StepUpAuth::verify` to cap attempts.
19. **Database errors leaked as "email taken" (low).**
    - confirm-change.php:59-60 catches `RuntimeException`. In PHP, `PDOException` is a subclass of `RuntimeException`.
    - So any DB error inside the transaction becomes a 409 response that shows the raw PDO message.
    - Fix: catch a dedicated exception class for the collision; return a generic 500 for anything else.
20. **Revert token stored in plaintext (low).**
    - confirm-change.php:55 stores the bearer token as-is. A database leak would let someone perform reverts.
    - General rule.
    - Fix: store `hash('sha256', $token)` and look it up by hash in revert.php:24.
21. **Emails in logs (low).** confirm-change.php:71 and :85.

## email/revert.php
22. **Stated step 3, "force a password reset", is not done (high).**
    - The comment at revert.php:64-69 lists three steps "per Section 20.1's design", including forcing a password reset.
    - The transaction at :76-84 only rolls back the email and bumps the session version. :69-75 admits nothing is forced.
    - The attacker, who still knows the password, can sign straight back in. They can then repeat the change if they also control a new inbox.
    - Fix: inside the transaction, set `password_hash` to an unusable random value, or a `must_reset` flag that login checks.
    - The comment calls this "too destructive". That trade-off needs a human decision against Section 20.1.
23. **Token can be used twice in a race (low).**
    - The `confirmed_at` check at :32 happens outside the transaction, with no row lock.
    - Fix: run `SELECT … FOR UPDATE` inside the transaction, or `UPDATE … WHERE id=? AND confirmed_at IS NULL` and check the affected row count.
24. **Restored address may collide (low).**
    - :80 does not check whether `revert_to_email` now belongs to another account.
    - A unique-constraint error (constraint not shown) would surface as an unhandled 500.
    - Fix: check for a collision first, as confirm-change.php:37 does.
25. **No limit on `confirm_email` guesses (low).** :55-56 has no attempt limit. Fix: mark the token used, or count failures, after N mismatches.

## verify-email.php
26. **Login CSRF through the auto sign-in link (med).**
    - verify-email.php:35-41 signs in whoever opens a registration link.
    - An attacker can register an account and send the link to a victim. The victim is then signed into the attacker's account.
    - Anything the victim then enters lands in that account, for example country and ZIP via setup.php:27 (`tv_setup` is set at :25). That exposes the victim's private data to the attacker.
    - Rule: the sign-in behaviour is per Jason, 2026-11-13 (P11). This risk is not addressed there as far as is shown.
    - Smallest fix: at register.php, record the new user's id in the session, and auto sign in only when it matches `$userId`.
27. **Setup rights granted before the eligibility check (low).**
    - :25 sets `tv_setup` before the suspended/closing check at :35, so a suspended or closing account's link still allows writes through setup.php.
    - If someone is already signed in, setup.php:13 writes to *that* user instead of the account just verified.
    - Fix: move :25 inside the `if` at :35.
28. **CSRF token not rotated at sign-in (low).** After `session_regenerate_id(true)` (:36), the pre-sign-in `csrf_token` carries over. General. Fix: regenerate `$_SESSION['csrf_token']` after :36.

Session fixation is handled here (:36).

## setup.php
29. **Unauthenticated write window never closed (low).**
    - setup.php:15 honours `tv_setup` for an hour and never clears it, so the same browser can rewrite home country and consents repeatedly.
    - Fix: clear `tv_setup` after a successful save, or keep it single-purpose.

Injection: the ZIP is reduced to digits (:22) and all queries are prepared (:27, :31-33). **No injection found.**

## google.php
30. **Redirect target not re-sanitised (low, verify).**
    - :39 and :104 redirect to `$p['next']` exactly as returned by `finish`/`finishIdToken`, which are not shown.
    - Only the start path (:78) and the create path (:74) pass through `safeNext`.
    - Fix: wrap with `GoogleAuth::safeNext($p['next']) ?: '/user/dashboard.php'` at both lines.
31. **Exception text shown to users (low, verify).**
    - :34 and :99 store raw exception messages in the session, presumably for display on `/login.php` (not shown).
    - :59 returns the message directly.
    - Fix: send generic text to users and keep the detail in the log only. Check that the page that shows these messages escapes them.
32. **Session fixation on Google sign-in (not shown).** It depends on `GoogleAuth::signIn` regenerating the session ID.
33. **ID-token POST has no CSRF check by design.** :20 relies on `state` being bound to the session (inside `finishIdToken`, not shown).

The account-linking checks at :25 and :90 correctly restrict linking to the signed-in user. The page at :109-117 echoes no request data. **No injection found.**

---

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| login.php:22-31 | No brute-force limit; captcha off until keys set | login.php:27-30 comment (Phase 9); decisions/0097 | high | Per-IP and per-account failure counter → 429 |
| login.php:13-14 | Captcha bypass keyed on email only (verify `TestAccounts`) | decisions/0145 §3 | med | Require a server secret token, compared with `hash_equals` |
| login.php:25-26 | 409/403 may reveal that an account exists (verify order) | login.php:27-28 comment | med | Raise 4090/4030 only after the password verifies |
| register.php:14,82 | No throttle with captcha off; email-bombing | decisions/0097 | med | Per-IP rate limit |
| register.php:93 | Email in logs | general | low | Log the user id |
| resend-confirmation.php:14-16 | Limit is per session, so it can be bypassed | resend-confirmation.php:3 | med | Limit per email and IP, server-side |
| resend-confirmation.php:22-27 | Timing reveals existing accounts | general | low | Constant work / async mail |
| password/request-reset.php:14,26 | No throttle with captcha off | decisions/0097 | med | Per-IP and per-email limit |
| password/request-reset.php:23-35 | Timing reveals existing accounts | request-reset.php:36-38 comment | low | Async/queued send |
| password/request-reset.php:33 | Email in logs | general | low | Do not log the address |
| change-password.php:24-28 | Unlimited current-password guessing | general | med | Failure counter |
| change-password.php:53 | "Other sessions signed out" not visible (verify) | — | verify | Confirm `Auth` bumps the session version |
| change-password.php:50 | Email in logs | general | low | Log the user id |
| email/request-change.php:32 | Unlimited password oracle | Section 20.1 (per :28) | med | Failure counter |
| email/request-change.php:36-39 | Reveals registered addresses | general | low | Generic response; rely on the confirm-time check |
| email/request-change.php:59 | Email in logs | general | low | Log the user id |
| email/confirm-change.php:25 | OTP attempt cap not shown | — | verify | Confirm `StepUpAuth` limits attempts |
| email/confirm-change.php:59-60 | `PDOException` returned as 409 with its message | general | low | Dedicated exception class; generic 500 otherwise |
| email/confirm-change.php:55 | Revert token stored in plaintext | general | low | Store a sha256 hash |
| email/confirm-change.php:71,85 | Emails in logs | general | low | Log the user id |
| email/revert.php:76-84 | Stated forced password reset not implemented | Section 20.1 (per :64-69) | high | Unusable `password_hash` or `must_reset` flag in the transaction |
| email/revert.php:32,76 | Used-token check outside the transaction (race) | general | low | Conditional UPDATE + row-count check |
| email/revert.php:80 | Restored email may collide → 500 | general | low | Collision check first |
| email/revert.php:55 | No limit on confirm_email guesses | general | low | Invalidate after N failures |
| verify-email.php:35-41 | Login CSRF via a sent verification link | Jason 2026-11-13 P11 (gap) | med | Auto sign-in only when the registering session matches |
| verify-email.php:25 | Setup rights granted before the suspended/closing check | general | low | Move inside the `if` at :35 |
| verify-email.php:36 | CSRF token not rotated at sign-in | general | low | Regenerate `csrf_token` after :36 |
| setup.php:15 | `tv_setup` never cleared | general | low | Unset after a successful save |
| google.php:39,104 | `next` not passed through `safeNext` (verify) | general | low | Wrap with `GoogleAuth::safeNext()` |
| google.php:34,59,99 | Raw exception text reaches users | general | low | Generic message; log the detail |
| google.php:31,96 | Session regeneration on sign-in not shown | — | verify | Confirm in `GoogleAuth::signIn` |
| apple/facebook/microsoft.php:10 | Logic not shown (`SocialAuth`) | — | — | Review `SocialAuth.php` |
| logout.php, session.php, password/reset.php | Nothing found in these files | — | — | — |