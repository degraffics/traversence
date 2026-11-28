# Security review: listing-api public endpoints (part 1)

**Scope:** `claim.php`, `evidence.php`, `leave.php`, `referral/confirm.php`, `update-metadata.php`, `verify/tier1.php`, `vouch.php`.

**Limits:**
- The library code (`Claims`, `Identity`, `ListingMessages`, `GuestInquiries`, `Verification`, `Response`, `AddBusiness`, `VerifyRequests`) is not shown. Where a check may live there, I say so and do not assume it either way.
- No rule or decision documents were attached. The only "rule §" I can cite is a decision named in the file comments. Otherwise I write "not shown".
- I assume `Response::success`/`error`/`json` end the request. Every `switch` in `claim.php` and `leave.php` depends on this, because no case has a `break`. `Response` itself is not shown.

---

## claim.php

**Access control:**
- Sign-in is enforced at `claim.php:24`.
- The method is restricted to POST at `claim.php:32`.
- CSRF is checked at `claim.php:33`.

**1. Possible leak of another user's claim, including the SMS code (med; high if the library doesn't check).**
- Several actions return `$out(Claims::get($pdo, $cid))` without checking in this file that the claim belongs to `$uid`:
  - `claim.php:97` (details)
  - `claim.php:103` (domain_confirm)
  - `claim.php:106` (appointment)
  - `claim.php:117` (activate)
- `$out` returns `text_code`, `claimant_phone` and `listed_phone` (`claim.php:48-50`).
- `text_code` is the code texted to the Verify number to finish a claim (decisions/0070, per `tier1.php:2-3`). Leaking it would let someone finish another person's claim.
- The `locations` action does check ownership explicitly (`claim.php:109`). That suggests `Claims::get` does not.
- Whether `Claims::details`, `domainConfirm`, `appointment` and `activate` throw for a non-owner is not shown.
- `claim.php:125` also hands the disputer the `claim_id` of the disputed (someone else's) claim. That gives them a valid id to try against these actions.
- **Rule:** decisions/0070 (claim flow); the rule text is not shown.
- **Fix:** add the same guard as line 109 before each of these `$out(...)` calls: `if (!$c || (int)$c['user_id'] !== $uid) 404`.

**2. No attempt limit visible on email or domain codes (med, needs check).**
- `confirm` (`claim.php:82`), `domain_confirm` (`claim.php:102`) and `add_confirm` (`claim.php:140`) accept codes.
- No attempt counter or lockout appears in this file. The library is not shown.
- **Rule:** not shown.
- **Fix:** confirm that the `Claims`/`AddBusiness` code limits wrong-code attempts per claim. If it doesn't, add a counter and lock after N tries.

**3. Sibling addresses returned (low/med, needs check).**
- `claim.php:110` returns `address` for every sibling location.
- It only requires that the caller owns *a* claim, in any status, possibly an unconfirmed or held one.
- If a sibling is a private place, such as a home-based business with a hidden address, this leaks it. Whether `Claims::siblings` filters private addresses is not shown.
- **Rule:** not shown.
- **Fix:** omit `address` here, or return it only for listings whose address is public.

**4. Log injection (low).**
- `claim.php:155` writes the user-supplied `$action` straight into `error_log`. A CR or LF in it can forge log lines.
- **Fix:** log `json_encode($action)` or strip control characters.

**5. Internal paths disclosed (low).**
- `claim.php:40` and `claim.php:143` tell any signed-in caller migration file paths.
- **Fix:** use a generic "not available yet" message and log the path server-side.

**6. Resend throttle is per session (low).**
- `claim.php:128-129` stores the 2-minute throttle in `$_SESSION`. A new sign-in gets a new session and bypasses it.
- The mail only goes to the user's own address, so the impact is mail volume.
- **Fix:** keep the timestamp on the user row instead.

**7. Documented action missing (low, correctness).**
- The header documents `action=document` (`claim.php:3,9`), but there is no `case 'document'` in the switch (`claim.php:73-149`). It falls through to "Unknown action".
- This is not a security defect. It is noted because the upload path for claim documents is therefore not in this file and was not reviewed.

**Money:**
- The only money-related action is `activate` (`claim.php:115-117`).
- Nothing in this file charges, refunds or credits. Whether `Claims::activate` checks ownership, `agreed`, and the plan price is not shown.

**Injection:**
- `claim.php:125` concatenates SQL, but the value is cast with `(int)`, so it is not injectable. Style only; a prepared statement would be better.
- The email at `claim.php:131-132` builds its URL from `APP_URL`, not the Host header, so it is fine.

---

## evidence.php

**Access control:**
- Sign-in is checked at `evidence.php:15-16`.
- Only the owner may proceed (`evidence.php:22`).
- CSRF is checked on POST (`evidence.php:27`).
- No injection was found; queries are prepared (`evidence.php:20-21`).

**8. "Use" can bypass the dispute freeze and the admin queue (med, needs check).**
- `evidence.php:30` (`Identity::ownerUse`) changes the listing.
- Unlike `update-metadata.php:33`, it does not check `Claims::frozen`.
- It also does not route name, phone, address or website through admin review for unverified owners (`update-metadata.php:4-5,70-72`).
- `Identity::ownerUse` is not shown.
- **Rule:** decisions/0070 as described at `update-metadata.php:4-6`.
- **Fix:**
  - Before the POST branch, add: `if (Claims::ready($pdo) && Claims::frozen($pdo,$id)) 423`.
  - Also ensure that `ownerUse` queues protected fields when the listing is not verified.

---

## leave.php

**Access control:**
- Signed-out callers are allowed by design (decisions/0172 §3.3, `leave.php:2-4`).
- CSRF is checked on POST (`leave.php:37`).
- `mine` requires sign-in (`leave.php:26`).

**9. No visible rate limit on bearer reply codes (low/med, needs check).**
- `check`, `reply` and `resend` (`leave.php:46,48,57`) accept only a `code`, signed out.
- `check` returns the thread (`leave.php:46`), which may include the guest's email or phone.
- `resend` (`leave.php:57`) sends email.
- Code length and entropy, and any rate limiting, are not shown.
- **Rule:** decisions/0172 §3.3 (text not shown).
- **Fix:** confirm codes are long and random, and rate-limit failed lookups per IP.

**10. Unhandled errors on a malformed body (low).**
- `leave.php:38`: if the body is not JSON, `$b` may be null.
- Only `InvalidArgumentException` is caught (`leave.php:61`). Other errors may surface as raw 500s; display settings are not shown.
- `GuestInquiries` is used at `leave.php:34,46,48` but not `require_once`'d here. It may be autoloaded (not shown).
- **Fix:** add `$b = Response::jsonBody() ?: [];` and a `catch (Throwable)` that logs and returns a generic 500.

---

## referral/confirm.php

**Access control:**
- No sign-in is required by design; possession of the token is the proof (`confirm.php:8-13`).
- No CSRF check is needed, because an attacker cannot know the token.
- Queries are prepared, so no injection was found.

**11. Check-then-update race (low/med).**
- `confirm.php:33` checks `confirmed_at`, and `confirm.php:40` updates it, without a condition tying the two together.
- Two concurrent requests with the same token can both pass. `checkReferralThreshold` (`confirm.php:43`) then runs twice and could trigger verification side effects twice.
- **Fix:** use `UPDATE … SET confirmed_at = NOW() WHERE id = :id AND confirmed_at IS NULL`, and if `rowCount() === 0`, return 409.

**12. Referral path may conflict with decisions/0070 (needs human check).**
- This endpoint can still mark a listing verified (`confirm.php:43,47`).
- decisions/0070 retired self-email verification (`tier1.php:2-3`). Whether it also changed referral verification is not shown.
- The endpoint also does not check whether the listing is frozen by a dispute.
- **Fix:** confirm against decisions/0070. If the referral path should not verify a frozen listing, check `Claims::frozen` before line 43.

---

## update-metadata.php

**Access control:**
- Sign-in is enforced at `update-metadata.php:10`.
- POST and CSRF are checked at `update-metadata.php:14-15`.
- Owner or manager access is checked at `update-metadata.php:32`.
- Keys are whitelisted at `update-metadata.php:53`.
- SQL is prepared.

**13. Admin review fails open when the claims tables are missing (med).**
- `update-metadata.php:70` queues protected fields only when `Claims::ready($pdo)`.
- If claims are not switched on, an unverified owner's name, phone, address or website changes save at once, locked against the crawler (`update-metadata.php:74`).
- **Rule:** decisions/0070 (`update-metadata.php:4-5`).
- **Fix:** when `!$verified && in_array($k, Claims::PROTECTED)` and claims are not ready, reject the field (add it to `$rejected`) instead of writing it.

**14. Offerings skip the content-safety check (med).**
- `update-metadata.php:59` runs `ListingSafety::contentProblem` only on `description` and `name`.
- `offerings` saves at once, locked, with trust 100 (`update-metadata.php:60-63`), without that check.
- **Rule:** decisions/0073 (`update-metadata.php:19`); the safety rule itself is not shown.
- **Fix:** add `'offerings'` to the array on line 59. The check runs before the offerings branch, so it will apply.

**15. Website check accepts non-http schemes (med, depends on how it's rendered).**
- `update-metadata.php:58` uses `FILTER_VALIDATE_URL`, which accepts values such as `javascript://host/%0Aalert(1)`.
- If the website is rendered as an `href` (not shown), this is stored XSS.
- **Fix:** also require `in_array(strtolower(parse_url($url, PHP_URL_SCHEME)), ['http','https'], true)`.

**16. Any access level can edit (low, needs check).**
- `update-metadata.php:32` accepts any truthy `listing_access.access_level`.
- If a read-only level exists (not shown), those users can edit.
- **Fix:** check against an explicit list of levels allowed to edit.

**17. Protected-field coverage (needs check).**
- `city` and `state` are editable (`update-metadata.php:17`).
- The header lists only name, phone, address and website as needing admin review (`update-metadata.php:4-5`).
- Whether `Claims::PROTECTED` includes `city` and `state` is not shown. If it doesn't, an unverified owner can move a listing's town at once.

**18. Non-string field values (low).**
- `update-metadata.php:54` casts `(string) $v`. An array value becomes `"Array"` with a warning.
- **Fix:** `if (!is_scalar($v)) { $rejected[] = $k; continue; }`.

---

## verify/tier1.php

**No defects found.** The endpoint is retired and always returns 410 (`tier1.php:8`). It reads no input and touches no data.

---

## vouch.php

**Access control:**
- Sign-in is enforced at `vouch.php:6`.
- POST and CSRF are checked at `vouch.php:9-10`.
- The caller must own the voucher entity, and it must be verified at tier 2 or higher (`vouch.php:34-49`).
- Queries are prepared.

**19. An owner can vouch for their own other listing (med/high).**
- The target lookup at `vouch.php:51` fetches only `status`.
- Nothing stops a user from vouching for a target they also own or manage.
- That lets one person push their own listings toward peer validation, which the comment at `vouch.php:26-29,40-46` says the feature must prevent.
- **Rule:** Section 4/20.2 (Peer Validation), as cited at `vouch.php:27`. The text is not shown.
- **Fix:** select `owner_user_id` at line 51. Return 403 if it equals `$currentUser['id']`, or if the user has a `listing_access` row on the target.

**20. Every database error reported as a duplicate (low).**
- `vouch.php:67-68` catches every `PDOException` and reports "already vouched", which hides real failures.
- **Fix:** check for SQLSTATE `23000` (integrity constraint violation). Otherwise log the error and return 500.

---

## Not found in this part

- **Command or header injection:** none found.
- **Path injection:** none found in the shown code. The upload paths are not in these files.
- **Nations' land data:** none of these endpoints returns it.
- **Money:** no direct charge, refund or credit appears. Only `claim.php:116` touches paid plans, and its library is not shown.

---

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| claim.php:97,103,106,117 (+125) | Claim (incl. `text_code`, phones) returned without an ownership check in this file; dispute response gives out another user's `claim_id` | decisions/0070 (text not shown) | med (high if the library doesn't check) | Same `user_id === $uid` guard as line 109 before `$out(...)` |
| claim.php:82,102,140 | No visible limit on wrong-code attempts | not shown | med (needs check) | Attempt counter and lockout in Claims/AddBusiness |
| claim.php:110 | Sibling addresses returned to any claimant, any status | not shown | low/med (needs check) | Drop `address`, or return it only for public-address listings |
| claim.php:155 | User `$action` written raw to the log | not shown | low | `json_encode($action)` |
| claim.php:40,143 | Migration paths disclosed to callers | not shown | low | Generic message; log details server-side |
| claim.php:128-129 | Resend throttle per session, bypassable | not shown | low | Store the throttle on the user row |
| evidence.php:30 | "Use" ignores the dispute freeze and the admin queue for unverified owners | decisions/0070 (per update-metadata.php:4-6) | med (needs check) | Add a `Claims::frozen` check; route protected fields through `queueChange` |
| leave.php:46,48,57 | Bearer-code actions, no visible rate limit or entropy guarantee | decisions/0172 §3.3 | low/med (needs check) | Rate-limit lookups; confirm code length |
| leave.php:38,61 | Null body or unknown errors not caught | not shown | low | `?: []` and `catch (Throwable)` |
| referral/confirm.php:33-43 | Check-then-update race; threshold can fire twice | not shown | low/med | `UPDATE … WHERE confirmed_at IS NULL` and check `rowCount()` |
| referral/confirm.php:43 | Still verifies listings; no frozen check; may conflict with decisions/0070 | decisions/0070 (needs human check) | needs check | Confirm the policy; add a frozen check |
| update-metadata.php:70 | Protected fields save at once when claims aren't ready (fails open) | decisions/0070 | med | Reject protected fields when `!Claims::ready` and not verified |
| update-metadata.php:59-63 | Offerings skip `ListingSafety` | decisions/0073 / safety rule not shown | med | Add `'offerings'` to line 59 |
| update-metadata.php:58 | `javascript:`-style URLs pass validation | not shown | med (depends on rendering) | Allow only the http/https schemes |
| update-metadata.php:32 | Any access level may edit | not shown | low (needs check) | Explicit list of edit-capable levels |
| update-metadata.php:17,70 | `city`/`state` may not be protected | decisions/0070 | needs check | Confirm `Claims::PROTECTED` contents |
| update-metadata.php:54 | Array values cast to `"Array"` | not shown | low | Reject non-scalars |
| vouch.php:51-57 | Owner can vouch for their own or managed target | Section 4/20.2 (per vouch.php:27) | med/high | Reject if the target's owner or manager is the current user |
| vouch.php:67-68 | All PDO errors reported as duplicate | not shown | low | Check SQLSTATE 23000; otherwise 500 |
| verify/tier1.php | none found | — | — | — |

*Draft for human review. Severities are my judgment. Items marked "needs check" depend on library code or rule text that was not shown.*