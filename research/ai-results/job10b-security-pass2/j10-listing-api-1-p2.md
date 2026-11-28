# Security review: listing-api public endpoints (part 1)

**Draft for human review.** I could see only the seven endpoint files. The decision documents (0054, 0062, 0070, 0071, 0073, 0172, "Section 4/20.2") and every library they call (`Claims`, `Identity`, `ListingMessages`, `GuestInquiries`, `Verification`, `AddBusiness`, `VerifyRequests`, `Response`, `Auth`) are not shown. Where a rule is cited, it is the rule as summarised in the file's own header comment, not the decision text. Many checks may happen inside those libraries. Where an endpoint depends on one, I mark it **verify** rather than calling it a defect.

---

## 1. `listing/api/claim.php`

**Access control.** The endpoint requires sign-in (`require_auth`, line 24), POST (line 32) and a CSRF token (line 33). That is good.

**Defects and open points**

1. **Ownership is checked in only one place (verify).**
   - `locations` checks that the claim belongs to the caller at line 109.
   - These actions pass a caller-supplied `claim_id` (`$cid`, line 43) to library calls whose ownership checks are not shown: `details` (96), `domain_start` (99), `domain_confirm` (102), `appointment` (105), `add_locations` (113), `activate` (116), `withdraw` (119) and `verify_request` (144).
   - Four of those then return `$out(Claims::get($pdo, $cid))` (lines 97, 103, 106, 117) with no endpoint-side check. That output includes `claimant_phone`, `listed_phone` and `text_code` (lines 48–50).
   - If any of those library functions skips the `user_id` check, a signed-in user can read another claimant's private phone and text code, or act on their claim. That includes `activate`, which is the money path.
   - Rule: 0070 (claims belong to the claimant). Severity: **high if the library check is missing, otherwise none.**
   - Smallest fix: one guard before the `switch` for every `$cid` action: `$c = Claims::get($pdo,$cid); if (!$c || (int)$c['user_id'] !== $uid) 404`. This is the same as line 109.

2. **`locations` returns sibling addresses at any claim status.**
   - Line 109 checks only ownership, not claim status. Line 110 returns `address` for every sibling.
   - A held or unconfirmed claimant can therefore list the addresses of other locations. Whether those include private places is not shown (`Claims::siblings`).
   - Rule: 0070, plus the private-address rule (text not shown). Severity: **med.**
   - Fix: require a confirmed claim status before returning siblings, or drop `address` until the claim is confirmed.

3. **`dispute` reveals the disputed claim's id.**
   - Line 125 returns `claim_id` from `disputes` to the person filing the dispute. That may be the other party's claim.
   - Combined with point 1, this hands the disputer a valid id for someone else's claim. The comment says proof "follows through the uploader (target claim_doc)". Whether the uploader checks ownership is not shown.
   - The SQL there concatenates `(int) $id`, so it is not injectable, but it should be a prepared statement for consistency.
   - Rule: 0070. Severity: **med.**
   - Fix: return only `dispute_id`, and let the uploader key on `dispute_id` with an ownership check.

4. **`activate` (money).**
   - The endpoint passes `plan` and `agreed` through (line 116). Plan validation, price, whether the claim must be confirmed first, and whether a charge happens are all in `Claims::activate`, which is not shown.
   - Severity: **verify.**
   - Fix: covered by the guard in point 1. Also confirm in the library that `plan` is checked against an allow-list and that `agreed` is enforced server-side for paid plans.

5. **`account_phone` saved without endpoint validation.**
   - Line 69 saves `account_phone` without checking its format; `setAccountPhone` is not shown.
   - Severity: **low.**
   - Fix: reject the value if `Claims::phone10($body['account_phone']) === ''`.

6. **`resend_email` rate limit is per session only.**
   - Lines 128–129 limit resends per session. A user with several sessions can send more confirmation emails.
   - Severity: **low.**
   - Fix: key the limit on `$uid` (database or cache) instead of `$_SESSION`.

7. **`document` action is advertised but missing.**
   - It is documented at line 9 but has no `case`, so it falls through to "Unknown action" (line 158). This is functional, not a security hole. Noted so nobody adds an upload path without checks.
   - Severity: **low.**

**Injection.** None found. All input reaches SQL through library calls or an `(int)` cast. **Leaks.** The user's own email is echoed back at lines 79, 133 and 138, which is fine.

---

## 2. `listing/api/evidence.php`

**Access control.** The endpoint requires sign-in (lines 15–16) and the listing owner (line 22). POST requires CSRF (line 27), and that check runs before any write.

1. **No dispute freeze.**
   - `update-metadata.php:33` blocks edits while a dispute is open (header lines 4–6 there).
   - Here, `use` (line 30) changes the listing with no `Claims::frozen` check.
   - Rule: 0070 ("While an admin is looking into a dispute, edits are frozen"). Severity: **med.**
   - Fix: add the same `Claims::ready && Claims::frozen` check before line 30.

2. **`use` may bypass the admin queue for protected fields (verify).**
   - An unverified "Managed by owner" listing must send name, phone, address and website to an admin (`update-metadata.php:4-5, 70-72`).
   - `Identity::ownerUse` (not shown) is called with no verified-status check.
   - Rule: 0070 together with 0062. Severity: **med if `ownerUse` writes directly.**
   - Fix: for unverified listings and protected fields, have `ownerUse` call `Claims::queueChange`.

3. **Owner-only, not managers.**
   - Line 22 checks only `owner_user_id`. This is stricter, not a leak, so there is no security defect. It is noted for consistency with `update-metadata.php:32`.

**Injection.** None; the queries are prepared (line 20).

---

## 3. `listing/api/leave.php`

**Access control.** Signed-out callers are allowed by design (0172 §3.3, header lines 2–4). POST requires CSRF (line 37). The `mine` view requires sign-in (line 26).

1. **No visible rate limit on guest-code actions.**
   - `check` (line 46), `reply` with a code (line 48) and `resend` (line 57) all accept a bearer `code` with no endpoint-side limit.
   - `check` returns the full thread, which may hold the guest's email or phone and the message text.
   - Code length and entropy, and any limit inside `GuestInquiries`, are not shown.
   - Rule: 0172 §3.3 (private sender details). Severity: **med (verify entropy).**
   - Fix: rate-limit failed code lookups per IP and session.

2. **`resend` can be repeated.**
   - Line 57 needs only the code. Whether `resendConfirm` throttles is not shown, so it may be usable to spam the guest's inbox.
   - Severity: **low.**
   - Fix: throttle per code.

3. **Unclaimed-only rule not checked here (verify).**
   - `leave` (line 44) passes any `entity_id`. Enforcing the "unclaimed listing" rule from the header (line 2) is left to `ListingMessages::leave`, which is not shown.
   - Severity: **verify.**

4. **Only one exception type is caught.**
   - Line 61 catches only `InvalidArgumentException`. Other errors go to the global handler, which is not shown; it may expose details.
   - Severity: **low.**
   - Fix: add `catch (Throwable)` that logs the error and returns a generic 500.

**Injection.** None visible.

---

## 4. `listing/api/referral/confirm.php`

**Access control.** There is deliberately no session (lines 8–13); possession of the token is the proof. A CSRF check is not needed, because an attacker would need the token.

1. **Check-then-update race.**
   - Lines 33–41 read `confirmed_at` and then run an `UPDATE ... WHERE id` without `AND confirmed_at IS NULL`.
   - Two concurrent requests can both pass and both run `checkReferralThreshold` (line 43).
   - Rule: Section 4 (text not shown). Severity: **low.**
   - Fix: `UPDATE ... SET confirmed_at = NOW() WHERE id = :id AND confirmed_at IS NULL`, then continue only if `rowCount() === 1`.

2. **Token never cleared; no limit on guessing.**
   - The token is not cleared after use (line 40), and there is no limit on guesses. Token entropy is not shown.
   - Severity: **low.**
   - Fix: null `confirmation_token` in the same `UPDATE`.

3. **Self-referral not checked here (verify).**
   - Whether a claimant can refer themselves (for example from a second inbox) is decided where referrals are created, which is not shown.

**Injection.** None; the query is prepared.

---

## 5. `listing/api/update-metadata.php`

**Access control.** The endpoint requires sign-in (line 10) and CSRF (line 15), and the caller must be the owner or have a `listing_access` row (line 32). The freeze check is at line 33.

1. **Website accepts `javascript:` URLs (stored XSS).**
   - Line 58 accepts any URL that passes `FILTER_VALIDATE_URL`, and a `javascript://…%0Aalert(1)` value does pass.
   - Verified owners save this immediately (line 74). If the site renders it as an `href`, it executes for visitors.
   - Severity: **high.**
   - Fix: also require `parse_url($u, PHP_URL_SCHEME)` to be `http` or `https`.

2. **Protected fields are written directly when claims are off.**
   - Line 70 sends fields to the admin queue only when `Claims::ready($pdo)` is true. If it is false, an unverified owner's name, phone, address and website go straight to `writeField` (line 74). The check fails open.
   - Rule: 0070 (header lines 4–5). Severity: **med.**
   - Fix: when `!$verified` and the key is protected and claims are not ready, reject the edit (503) instead of saving.

3. **Any access level can edit.**
   - Line 32 accepts any truthy `lvl`. Whether read-only levels exist is not shown.
   - Severity: **low (verify).**
   - Fix: allow-list the access levels that may edit.

4. **Offerings skip the content check.**
   - The content-safety check at line 59 covers only `description` and `name`. Offerings are public free text and are saved immediately with no check (lines 60–63).
   - Severity: **low.**
   - Fix: run `ListingSafety::contentProblem` on each offerings line.

5. **City and state may not be protected (verify).**
   - Whether `city` and `state` are in `Claims::PROTECTED` is not shown. The header (line 4) lists only name, phone, address and website, so an unverified owner may be able to relocate a listing at once.
   - Severity: **low (verify).**

6. **Partial saves.**
   - A validation error part-way through the loop (lines 55–59) returns 422 after earlier fields, and categories (line 47), are already saved.
   - Severity: **low.**
   - Fix: validate every field first, then write.

**Injection.** None; all SQL is prepared (lines 29, 38, 62).

---

## 6. `listing/api/verify/tier1.php`

**Nothing found.** The endpoint is retired and only returns 410 (line 8).

---

## 7. `listing/api/vouch.php`

**Access control.** The endpoint requires sign-in (line 6) and CSRF (line 10). The voucher must be owned by the caller (line 34), verified (line 37) and at tier 2 or above (line 47).

1. **Self-vouching across your own listings.**
   - Line 20 blocks only an entity vouching for itself. A user who owns a tier-2 verified business can vouch for their own other claimed listing.
   - A user who owns several verified businesses can also cast several vouches toward the threshold.
   - Rule: Section 4/20.2, Peer Validation as "real accountability" (comment, lines 26–29 and 40–46). Severity: **med.**
   - Fix: fetch the target's `owner_user_id` and return 403 if it equals the caller. In `checkVoucherThreshold` (not shown), count distinct voucher owners.

2. **All database errors reported as duplicates.**
   - Line 67 treats every `PDOException` as a duplicate vouch, which hides real errors.
   - Severity: **low.**
   - Fix: check for SQLSTATE `23000` and rethrow anything else.

**Injection.** None; the queries are prepared.

---

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| update-metadata.php:58 | website accepts `javascript://` URLs; stored XSS | not shown (output safety) | high | require http/https scheme |
| claim.php:96–117,144 | `claim_id` actions rely on library ownership checks (not shown); `$out` returns claimant phone and text code | 0070 | high if library lacks check (verify) | one guard before switch: claim's `user_id === $uid` |
| claim.php:109–110 | sibling addresses returned at any claim status | 0070 / private places | med | require confirmed claim or omit `address` |
| claim.php:125 | dispute returns other party's `claim_id`; SQL concatenation | 0070 | med | return only `dispute_id`; prepare the query |
| claim.php:116 | `activate` (money) logic not shown | 0070 | verify | guard above; allow-list plan; enforce `agreed` server-side |
| claim.php:69 | `account_phone` not validated | none shown | low | `phone10` check |
| claim.php:128 | resend limit per session only | none shown | low | limit per `$uid` |
| evidence.php:30 | owner `use` skips dispute freeze | 0070 | med | add `Claims::frozen` check |
| evidence.php:30 | `use` may bypass admin queue for protected fields | 0070 / 0062 | med (verify) | queue protected fields when not verified |
| leave.php:46,48,57 | no visible limit on guest-code guessing; thread may expose contact details | 0172 §3.3 | med (verify) | rate-limit failed lookups |
| leave.php:57 | `resend` may spam guest inbox | 0172 §3.3 | low | throttle per code |
| leave.php:61 | only `InvalidArgumentException` caught | none shown | low | catch `Throwable`, generic 500 |
| referral/confirm.php:40 | confirm race; token not cleared | Section 4 | low | `AND confirmed_at IS NULL` plus `rowCount`; null the token |
| update-metadata.php:70 | protected fields written directly when claims are off | 0070 | med | reject when not ready |
| update-metadata.php:32 | any access level may edit | 0070 | low (verify) | allow-list editing levels |
| update-metadata.php:60–63 | offerings skip content check | 0073 / safety | low | run `contentProblem` per line |
| update-metadata.php:55–59 | partial save on error | none shown | low | validate all fields, then write |
| vouch.php:20 | owner can vouch for own other listing; multi-vouch | Section 4/20.2 | med | reject same owner; count distinct owners |
| vouch.php:67 | all PDO errors reported as duplicate | none shown | low | check SQLSTATE 23000 |
| verify/tier1.php:8 | none (retired, 410) | none | none | none |