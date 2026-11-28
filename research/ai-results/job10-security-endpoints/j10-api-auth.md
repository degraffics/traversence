# Security review: `api/auth` sign-in endpoints (draft for human review)

**Scope.** These are the 18 files attached. The project's rule and decision documents are not attached, so "rule §" cites only what the code comments reference. Where a comment names a rule I cannot see, I quote the comment and mark the text as not shown. Behaviour inside these libraries is also **not shown**: `Auth`, `SocialAuth`, `GoogleAuth`, `StepUpAuth`, `Captcha`, `TestAccounts`, `Mailer`, `Response`, and `bootstrap.php`. Findings that depend on them are marked conditional.

---

## 1. Access control

**A1. Email revert does not force a password reset (HIGH)**
- **Where:** revert.php:61–75 and 76–84.
- **Rule:** The comment at revert.php:61–64 says three things must "happen together, per Section 20.1's design". Item 3 is "Force a password reset before the account can be used again" (Section 20.1 text not shown).
- **Problem:** Lines 69–75 then drop item 3. The transaction at 76–84 only:
  - restores the email, and
  - calls `bumpSessionVersion`.
- **Impact:** The attacker still knows the password, as line 62 itself says. They can sign in again and repeat the email change. The response at 87–88 asks the user to reset the password but does not enforce it.
- **Smallest fix:** In the same transaction, set a `must_reset_password` flag, or replace `password_hash` with an unusable value. Then have login refuse that account until a reset completes.
- **Note:** The comment at 70–71 argues an unusable hash is "too destructive". A flag avoids that concern, but this is a design decision for a human.

**A2. Other pending revert tokens stay valid, so the attacker can undo the owner's recovery (HIGH)**
- **Where:** revert.php:76–84 and confirm-change.php:49–57.
- **Rule:** revert.php:61–63: "Reverting the email alone would let them just repeat the change" (Section 20.1).
- **Attack path:**
  1. The attacker changes the email A→B. A revert token goes to A, the owner's address.
  2. The attacker changes it again B→C. A revert token goes to B, which the attacker controls.
  3. The owner reverts to A.
  4. The attacker then uses B's token, which is still unconfirmed and unexpired, and reverts to B.
- **Smallest fix:** At revert.php:77, mark every `email_revert` row for this user as used, not just this one: `UPDATE email_confirmations SET confirmed_at = NOW() WHERE user_id = :uid AND action_type = 'email_revert' AND confirmed_at IS NULL`.

**A3. Revert does not check whether the old address is now used by another account (MED)**
- **Where:** revert.php:80–81.
- **Rule:** No rule shown. confirm-change.php:35–41 does make this exact check in the other direction.
- **Problem:** Within the 72-hour window, another account could register the old address.
  - If a unique index exists (not shown), the result is an unhandled 500 and the revert fails.
  - If there is no unique index, two accounts end up sharing one email.
- **Smallest fix:** Add the same `SELECT id FROM users WHERE email = :email AND id != :self` check inside the transaction. Return 409 if it finds a row.

**A4. Revert does not invalidate outstanding password-reset tokens (MED, conditional)**
- **Where:** revert.php:76–84.
- **Rule:** None shown. The intent is in revert.php:61–75.
- **Problem:** While the email pointed at the attacker, the attacker could request reset links via request-reset.php. Nothing shown here invalidates those tokens. Whether `Auth::bumpSessionVersion` or `Auth::resetPassword` does so is not shown.
- **Smallest fix:** Inside the transaction, mark every unconsumed password-reset token for `$userId` as used.

**A5. verify-email grants the setup window even to suspended or closing accounts (LOW)**
- **Where:** verify-email.php:25.
- **Problem:** `tv_setup` is set before the suspended/closing check at line 35. As a result, setup.php:15 lets this browser write country and consent data for a suspended or closing account for one hour.
- **Smallest fix:** Move line 25 inside the `if` block at line 35, or apply the same conditions to it.

**A6. Setup window is never cleared after use (LOW)**
- **Where:** setup.php:15.
- **Problem:** `tv_setup` stays valid for the full hour after the questions are answered. On a shared browser, a later user can still write to that account.
- **Smallest fix:** `unset($_SESSION['tv_setup'])` before line 43.

**Social sign-in (apple.php, facebook.php, microsoft.php):** Each file is a one-line delegation to `SocialAuth::endpoint()`, which is not shown. I found nothing to review in these three files.

---

## 2. Brute force and rate limits

**B1. Login has no rate limiter, and the captcha may be off (HIGH)**
- **Where:** login.php:11–14, 27–30.
- **Rule:** login.php:28–30: "A brute-force rate limiter belongs in front of this endpoint (Phase 9 security hardening); not yet implemented here." Also decisions/0097, where the captcha is "off until its keys are in .env" (text not shown).
- **Impact:** If the captcha keys are absent, nothing visible limits password guessing.
- **Second concern:** Line 14 also skips the captcha whenever `TestAccounts::checkRequest(email)` returns true. Whether that check needs a secret beyond the email is not shown (decisions/0145 §3).
- **Smallest fix:** Before `attemptLogin`, add a counter per email and per IP, for example a DB row with attempts and window. Return 429 above a threshold. Confirm that `TestAccounts::checkRequest` requires a secret token.

**B2. Reset request and registration have no rate limit, and the captcha may be off (MED)**
- **Where:** request-reset.php:14, 21–35 and register.php:14, 34–94.
- **Rule:** decisions/0097 (not shown).
- **Impact:** Unlimited reset emails to any address with an account, unlimited account creation, and verification emails sent to third-party addresses.
- **Smallest fix:** Rate-limit per IP and per target email before sending mail.

**B3. Resend-confirmation limit is per session only (LOW)**
- **Where:** resend-confirmation.php:14–16.
- **Rule:** resend-confirmation.php:3 says "At most 3 a session per hour". The code matches this, but the limit is bypassed by dropping the cookie.
- **Smallest fix:** Also key the limit on the target email or user id, stored server-side.

**B4. Current-password checks have no attempt limit (LOW)**
- **Where:** request-change.php:29–34 and change-password.php:24.
- **Rule:** request-change.php:27–28 says the check "defends against a stolen/unattended session" (Section 20.1).
- **Problem:** Unlimited guesses from a stolen session weaken that defence. Whether `Auth::changeCurrentPassword` limits attempts is not shown.
- **Smallest fix:** Count failures per user. After N failures, lock the check or end the session.

**Not shown:**
- Attempt limits on OTP verification (`StepUpAuth::verify`, confirm-change.php:25).
- OTP issuance limits (request-change.php:45).
- Reset-token entropy (reset.php:19).

---

## 3. Session fixation

**S1. CSRF token is not rotated when the session id is regenerated on email-link sign-in (LOW)**
- **Where:** verify-email.php:36, 43.
- **Problem:** `session_regenerate_id(true)` correctly changes the id. The pre-login `csrf_token` carries over, though, and is returned at line 43.
- **Smallest fix:** After line 36, set `$_SESSION['csrf_token'] = bin2hex(random_bytes(32));`.

**Not shown:**
- Whether `Auth::attemptLogin` (login.php:23), `GoogleAuth::signIn` (google.php:31, 96) and `SocialAuth` regenerate the session id and rotate the CSRF token. login.php:36 returns whatever token is in the session.

---

## 4. Redirects

**R1. Google sign-in redirects to `next` without re-validating it (LOW, conditional)**
- **Where:** google.php:39 and google.php:104.
- **Problem:** Both lines redirect to `$p['next']` without passing it through `GoogleAuth::safeNext()`. By contrast, line 74 does sanitise the pending `next`, and lines 78/120 sanitise it before `start()`.
- **Condition:** This is safe only if `finish()` and `finishIdToken()` return exactly the value stored by `start()`. That is not shown.
- **Smallest fix:** Use `GoogleAuth::safeNext($p['next']) ?: '/user/dashboard.php'` at both lines.

**Lines 38 and 103:** `next` is `rawurlencode`d into the query string, so I found no issue there. What `/register.php` and `/login.php` do with `next` is not shown.

---

## 5. Injection

**Nothing found.**
- All SQL uses prepared statements with bound parameters.
- setup.php:32 builds a `LIKE` pattern only from `$zip`, which line 22 strips to digits.
- session.php:75 concatenates `Database::t('admin_access')`, which takes a constant argument.
- The HTML page at google.php:110–116 echoes no server or user data.
- Mail subjects are constant.

**Not shown:**
- How `$_SESSION['tv_google_error']` (google.php:34, 99) is later rendered. It contains exception text and must be HTML-escaped wherever it is displayed.

---

## 6. Token handling

**T1. `hash_equals` can throw on malformed metadata (LOW)**
- **Where:** revert.php:40, 55.
- **Problem:** Under `strict_types`, `hash_equals` throws a `TypeError` if `revert_to_email` is not a string, for example malformed JSON metadata. That surfaces as an unhandled 500.
- **Smallest fix:** At line 41, use `if (!is_string($revertToEmail))`.

**T2. Null password hash may crash the email-change request (LOW, conditional)**
- **Where:** request-change.php:31–32.
- **Problem:** If `password_hash` is NULL, for example for a social-only account (not shown whether that is possible), `fetchColumn()` returns `null`. Under `strict_types`, `password_verify(..., null)` then throws a `TypeError`.
- **Smallest fix:** `if (!is_string($hash) || !password_verify(...))`.

**T3. Revert token is placed in a URL query string (LOW)**
- **Where:** confirm-change.php:63.
- **Problem:** Tokens in query strings can leak through logs and Referer headers. Whether `/email-revert.php` sets `Referrer-Policy` is not shown.
- **Smallest fix:** Send `Referrer-Policy: no-referrer` on that page.

**Fine as shown:**
- revert.php has no CSRF check (line 11), but it is an unauthenticated POST whose 256-bit token (confirm-change.php:31) is the proof.
- The idtoken POST at google.php:20–23 relies on `state` as its guard. How `state` is bound to the session is not shown.

---

## 7. Private data and enumeration

**P1. Registration status code reveals whether an email has an account (MED)**
- **Where:** register.php:38–41 vs 96–100.
- **Rule:** register.php:39: "Deliberately generic message … to avoid email enumeration."
- **Problem:** The message may be generic, but a taken email returns status 409 and a new one returns 201. That difference is an existence oracle, assuming `Auth::register` throws for duplicates (not shown).
- **Smallest fix:** Return the same 201 response either way. For an existing address, email its owner instead of creating an account.

**P2. Login error codes may reveal account existence (MED, conditional)**
- **Where:** login.php:25–26.
- **Rule:** login.php:27–28: "avoids leaking which emails have accounts."
- **Problem:** The `closing` (409) and `unconfirmed` (403) responses reveal existence if `Auth::attemptLogin` throws them before verifying the password (not shown).
- **Smallest fix:** Throw codes 4090 and 4030 only after the password has verified.

**P3. Email change reveals whether an address is registered (LOW)**
- **Where:** request-change.php:36–40.
- **Problem:** The 409 `email_taken` tells a signed-in user whether any address is registered. It is limited by the password check, but B4 means that check is unlimited.
- **Smallest fix:** Always answer "code sent", and send nothing when the address is taken.

**P4. Timing reveals account existence (LOW)**
- **Where:** request-reset.php:23–35 and resend-confirmation.php:22–28.
- **Problem:** The response wording is identical, but a mail is sent only when an account exists, so response time differs.
- **Smallest fix:** Queue the mail asynchronously, or send it after the response.

**P5. Email addresses are written to the error log (LOW)**
- **Where:** change-password.php:50, request-change.php:59, confirm-change.php:71 and 85, request-reset.php:33, register.php:93.
- **Rule:** No rule shown.
- **Smallest fix:** Log the user id instead of the address.

**P6. Reset mail goes to the typed address, not the stored one (LOW)**
- **Where:** request-reset.php:27.
- **Problem:** The mail is sent to the address as typed rather than the stored address. The lookup at line 21 is not lower-cased, unlike other endpoints.
- **Smallest fix:** Have `requestPasswordReset` return the stored email and send to that.

**Not shown:**
- Which fields `$user` contains in login.php:35, register.php:97 and session.php:84.
- The CORS policy on session.php, which returns `csrf_token` on GET.

---

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| revert.php:61–84 | Password reset not forced; attacker keeps password and can repeat the change | Section 20.1 as cited at revert.php:61–64 (text not shown) | high | Set a must-reset flag in the transaction; login refuses until reset |
| revert.php:77 | Other pending `email_revert` tokens stay valid; attacker can re-revert to own address | Section 20.1 intent, revert.php:61–63 | high | Consume all of the user's unconfirmed `email_revert` rows |
| login.php:14, 27–30 | No brute-force limiter; captcha off until keys set; test-account captcha bypass keyed on email (details not shown) | "Phase 9 security hardening" (login.php:29); decisions/0097; 0145 §3 (not shown) | high | Per-IP/per-email attempt counter with 429; confirm TestAccounts needs a secret |
| revert.php:80–81 | No collision check for old address; 500 or duplicate email | none shown (mirrors confirm-change.php:35–41) | med | Add the same `id != self` check, return 409 |
| revert.php:76–84 | Outstanding reset tokens not invalidated (conditional on Auth, not shown) | Section 20.1 intent | med | Expire the user's unused reset tokens in the transaction |
| register.php:38–41 / 96–100 | 409 vs 201 reveals whether an email has an account | register.php:39 comment | med | Same 201 response; email the existing owner instead |
| login.php:25–26 | 409/403 may reveal account existence (conditional on Auth) | login.php:27–28 comment | med | Raise 4090/4030 only after password verifies |
| request-reset.php:14,21 / register.php:14 | No rate limit; captcha may be off; mail bombing | decisions/0097 (not shown) | med | Per-IP and per-target rate limit |
| resend-confirmation.php:14–16 | Limit is per session, bypassed by dropping the cookie | comment at line 3 | low | Also limit per email server-side |
| request-change.php:29–34; change-password.php:24 | Unlimited current-password guesses from a stolen session | Section 20.1 (request-change.php:27–28) | low | Per-user failure counter and lockout |
| verify-email.php:36,43 | CSRF token not rotated on sign-in | none shown | low | Regenerate `csrf_token` after line 36 |
| verify-email.php:25 | Setup window granted to suspended/closing accounts | none shown | low | Set `tv_setup` only inside the line-35 conditions |
| setup.php:15 | `tv_setup` not cleared after use | none shown | low | `unset` before success |
| google.php:39, 104 | Redirect to `next` not passed through `safeNext` (conditional) | none shown | low | Wrap with `GoogleAuth::safeNext()` |
| revert.php:40,55 | Non-string metadata causes `TypeError` and 500 | none shown | low | `is_string` check at line 41 |
| request-change.php:31–32 | NULL hash causes `TypeError` (conditional) | none shown | low | `is_string($hash)` check |
| confirm-change.php:63 | Revert token in query string (Referer/log leak; page not shown) | none shown | low | `Referrer-Policy: no-referrer` on revert page |
| request-change.php:36–40 | Authenticated email-existence oracle | none shown | low | Uniform "code sent" response |
| request-reset.php:23–35; resend-confirmation.php:22–28 | Timing reveals account existence | request-reset.php:36–38 comment | low | Send mail asynchronously |
| change-password.php:50; request-change.php:59; confirm-change.