# Reconciliation of j10 security reviews: pass1 (P1) vs pass2 (P2)

**Coverage caveats. Read these before treating an absence as disagreement.**
- **api-1:** Both passes are truncated.
  - P1 ends partway through #5 (`market-pay.php`).
  - P2 ends at the heading of #7 (`search.php?removed=`).
  - So any api-1 item that only one pass reports may simply have been cut off.
- **api-auth (P1):** The summary table is truncated, but the body is complete.
- **api-2 (P1):** Same as api-auth: the summary table is truncated, but the body is complete.
- **File counts differ:** api-2 is 20 files in P1 and 21 in P2; user-api is 26 in P1 and 27 in P2. Some single-pass gaps may come from these different file sets.
- **Unchecked by either pass:** Neither pass saw the decision texts or `app/lib/*`. Every "verify" item depends on code neither pass could check.

---

## (1) Where they agree

**api-1**
- **Map view leaks confidential listings.** `search_listings.php` map view sends exact lat/lon for confidential listings, with no `isConfidential` check. Both rate it **high**. Both give the same fix: add the category to the SELECT and skip or null the point.
- **`search.php?removed=` changes state on a GET.** Both flag it. P2's detail is lost to truncation.

**api-2**
- `short.php`: the rate limit lives in the session.
- `stg-bar.php`: an empty `RELEASE_BAR_KEY` is not refused. Both rate it med (conditional). Both point to `staging-updates.php:16` as the model.
- `stg-bar.php:31-32`: CR/LF can travel from the upstream body into the `Cookie:` header.
- `stg-csp.php`: anyone can write to the Held ledger without authentication.
- `stg-probe.php`: returns lengths, the env path and `REMOTE_USER`, despite promising "never a value".
- `stock.php`: unlimited `discover` and `adopt` calls.
- `suggest.php:96`: the `PlaceScope` SQL fragment is inserted raw. Both say verify, and high if unsafe.
- `trips.php:157-159`: PDOException text is echoed. Both rate it **med**.
- `trips.php`: unvalidated `tz` is stored.
- `upload.php:38`: the CSRF check passes when the session token is empty.
- `upload.php:52-54` and `:24`: internal details are echoed.
- `upload-act.php:19`: the `regional_admin` fallback.
- `vault-lock.php:9`: a GET locks the vault.
- `vcard.php:17`: the save count can be inflated.
- `wire-card.php`: the code-entry step is not tied to the requester. Both rate it med and both propose a session marker.
- Both found nothing in `staging-updates.php` or `tags.php`.

**api-auth**
- `revert.php`: the forced password reset is not implemented. Both rate it **high**.
- `login.php`: no brute-force limiter while the captcha is off. Both rate it **high**. Both also flag the `TestAccounts` captcha bypass keyed on email.
- `login.php`: the 409/403 responses may reveal that an account exists (conditional).
- `register.php` and `request-reset.php`: no throttle while the captcha is off.
- `resend-confirmation.php`: the limit is per session.
- Current-password checks can be guessed without limit (`change-password.php`, `request-change.php`).
- `verify-email.php:25`: setup rights are granted before the suspended/closing check.
- `verify-email.php:36`: the CSRF token is not rotated at sign-in.
- `setup.php:15`: `tv_setup` is never cleared.
- `google.php:39,104`: `next` is not passed through `safeNext`.
- `request-change.php:36-40`: reveals whether an address is registered.
- Timing differences reveal accounts (`request-reset.php`, `resend-confirmation.php`).
- Email addresses are written to logs. Both cite the same six lines.
- Both say the social endpoints contain nothing reviewable.

**listing-api**
- `claim.php`: claim data, including `text_code` and phones, is returned without an ownership check at the endpoint. Both say high if the library has no check.
- `claim.php:125`: the dispute response hands out the other party's `claim_id`.
- `claim.php:110`: sibling addresses are returned at any claim status.
- `claim.php:128-129`: the resend throttle is per session.
- `claim.php`: the documented `document` action is missing.
- `activate` (money) depends on the library.
- `evidence.php:30`: skips the dispute freeze and the admin queue.
- `leave.php`: no visible rate limit on guest codes.
- `leave.php`: catches only one exception type.
- `referral/confirm.php`: check-then-update race.
- `update-metadata.php:58`: `javascript:` URLs pass validation.
- `update-metadata.php:70`: fails open when claims are not ready.
- `update-metadata.php`: offerings skip the content check.
- `update-metadata.php:32`: any access level may edit.
- `city`/`state` may not be protected.
- `vouch.php`: an owner can vouch for their own other listing.
- `vouch.php:67`: every PDO error is reported as a duplicate.
- Both found nothing in `tier1.php`.

**user-api**
- `delete-account.php:19`: deletes permanently when the 30-day close is not ready.
- `restore-account.php`: the emailed token alone allows `now` (irreversible delete).
- `referrals.php:37`: the referrer's email is returned.
- `posts.php:44`: comments are served without `canSee`.
- `pulse-msg.php:36`: `javascript:` href.
- `pulse-msg.php`: a GET marks a message read.
- `mailboxes.php`: SSRF through the IMAP/SMTP host, and a hard-coded `'staff'` scope.
- `push.php`: SSRF through the push endpoint.
- `people.php:48-50`: member names can be enumerated by id.
- `member-profile.php:29`: `use_photo` takes a path.
- `qr.php`: the trial may be restartable.
- `community.php:61,73`: `owner` is shown to signed-out callers.
- `marketing.php:26`: the admin test differs from the dashboard's.
- `notice.php`: no POST method check, and `resend` is open.
- `sign-ins.php:15`: unlink needs no re-sign-in.
- PDOException text is echoed in `posts.php`, `pulse-act.php`, `qr.php`, `staff-link.php`, `context.php` and `mailboxes.php`.
- Migration paths are revealed before sign-in in `business-messages.php`, `community.php` and `consent.php`.

## (2) Where they differ or contradict

| Topic | P1 | P2 | Better supported / reconciliation |
|---|---|---|---|
| `upload.php:38` empty-token CSRF | med (conditional) | high (conditional) | Both rest on the same unknown: whether token-less sessions exist. Both cite lazy token creation at `trips.php:36` as plausibility. Use **med, high if token-less sessions confirmed**. They also name different reachable actions: P1 says `do=piece` (:28-29); P2 says `do=start`, `done` and `gallery` (:29). Confirm which actions accept form posts. |
| `tag-card.php` cache | med; adds private places and nations' land | low/med | Same evidence in both. P1's link to private-place exposure gives it a reason for the higher rating. Use **med**. |
| `stock.php` `?record` | Reviewed; accepted `canOrders` as the gate (no defect) | med: any order id, no per-order scope (:41-47) | P2 names a concrete gap (the order id is not scoped). P1 passed it without addressing scope. Neither can see `canOrders`. Keep as P2's **verify** item. |
| `spell.php` | low: no length cap (:11) | nothing found | P1 names a visible absence, but its impact is unshown. Keep as low/verify. |
| `suggest.php:276-277` city/state | check, low | "rest of file looks correct" | P1's concern rests on `vcard.php:3` plus an unseen flag. Keep as a check item. |
| `vault-ext.php`, `video/jobs.php` | D18 (token-guess throttle) and D21 (lease limit) | nothing found | Both items depend on library code. Keep as P1-only checks. |
| `stg-bar.php` regex fix | `[A-Za-z0-9._-]` | `[A-Za-z0-9]` | P1's class is safer for real cookie values that contain `.`, `_` or `-`. Both remove CR/LF. |
| `revert.php` collision | med (:80-81) | low (:80) | P1 cites the mirror check at `confirm-change.php:35-41` and the duplicate-email outcome if there is no unique index. Use **med**. |
| Revert comment lines | Three steps listed at :61-64 | :64-69 | This is a citation difference only, not a disagreement about substance. |
| `resend-confirmation.php`, `change-password.php`, `request-change.php` limits | low | med | P2 notes that resend has no captcha and that the password oracle defeats the defence cited for Section 20.1. Use **med**. |
| Test-account captcha bypass | Folded into the high login item | Separate item, med | The substance is the same. List it separately as med so it is not lost inside the limiter fix. |
| `update-metadata.php:58` javascript URL | med, depends on rendering | high | Rendering is not shown, so P1's conditional is better calibrated. Use **high if rendered as href**. |
| Offerings content check | med | low | P1 notes that offerings save immediately at trust 100. Use **med**. |
| `leave.php` codes | low/med | med (verify) | Use **med (verify)**: `check` returns contact details. |
| PDOException echo in user-api | low | med | P1 itself rates the guest-reachable `trips.php` med and signed-in cases low. Use **low–med**: med where reachable signed out. |
| `community.php` owner fields | low | med (pending lib) | Unresolved without `Community::owner`. Use **low–med (verify)**. |
| `notice.php:44` resend | low | med | Unauthenticated mail to any address. Use **med**. |
| `post-media.php:17` `s=` | low/med: unchecked part could form a path | nothing found at endpoint | P1 names an unvalidated input against a four-value list in the header. P2 did not address `$part`. Keep P1's **verify**. |
| `qr.php` target | :41 at create; scheme check in `ShortCodes::create` | :45 change after printing; "same checks as on create" | These are different angles. P2 implies create is checked; P1 says it is not shown. Merge into one row and verify both paths. |
| Migration paths, `links.php`, `saved-searches.php` | Lists `links:15`, `marketing:18`, `people:21`, `saved-searches:17` | Found nothing in `links.php` or `saved-searches.php` | The overlap is limited to `business-messages`, `community` and `consent`. Keep P1's extra files as single-pass. |
| `vouch.php` self-vouch lines | :51 (target lookup) | :20 (self-check), and adds counting distinct owners | Both are correct sites. Combine the fixes. |

## (3) Merged findings

### api-1 (both passes truncated)

| file:line | finding | passes | severity | fix |
|---|---|---|---|---|
| P1 search_listings.php:407–424 / P2 :408-424 | Map view returns exact lat/lon (and phone) of confidential listings, signed out | P1, P2 | high | Add category_id to the pin SELECT; skip or null the point when `isConfidential` |
| P2 search.php:56-58 | Pins from `SearchIndex::rows` may include confidential listings (verify) | P2 single-pass | verify | Confirm the index excludes them |
| P1 search.php:68–71 / P2 §7 (no line, truncated) | `?removed=` GET does a staff block, no CSRF | P1, P2 | med | null user id on GET; move the staff block to a CSRF'd POST |
| P1 open-places.php:31–38; search.php:90–99, 111–118 | Anonymous GETs queue crawler work; no limit shown | P1 single-pass | med | Per-town daily cap in `TownCrawl::fromSearch`, or a throttle |
| P1 listing-confirm.php:17–21 | Confidence "yes" deduped only per session; score orders search | P1 single-pass | med | Cap per listing, fact and day, or key on deviceHash |
| P1 market-pay.php:91 | `(int) round($dollars) * 100` drops cents in damage amount | P1 single-pass | med (money) | Round after ×100 (P1's fix text truncated) |
| P2 locate.php:10-17 | Geocodes and returns a point for any id, signed out, no CSRF; session-only limit | P2 single-pass | high (verify) | Refuse non-public or confidential listings; add `requireCsrf` |
| P2 pin.php:18-22 | `ListingLocation::get` returned whole to visitors | P2 single-pass | high (verify) | Strip or 404 for confidential or suppressed listings |
| P2 domains.php:136-140 | `wallet_replace` lacks the `Wallet::canManage` check | P2 single-pass | high/med (verify) | Add the same check as :133 |
| P2 domains.php:82 | `cart_add` takes price from the client | P2 single-pass | high if trusted (money) | Re-quote server-side |
| P2 blocks.php:25-26, 49-53 | publish, rollback, schedule and unschedule check only `pages.edit` | P2 single-pass | med/high (verify) | Require `pages.publish` for these actions |
| P2 blocks.php:55 | `Blocks::can('pages.publish')` called without `$zone` | P2 single-pass | low | Pass `$zone` |

### api-2

| file:line | finding | passes | severity | fix |
|---|---|---|---|---|
| P1 upload.php:38 (+:28-29) / P2 upload.php:38 (+29) | CSRF passes when the session token is empty; form posts accepted | P1, P2 | med, high if token-less sessions exist | Reject an empty session token |
| P1 upload.php:52-54 / P2 :52-54 | PDOException message echoed | P1, P2 | low–med | Catch PDOException and return a generic message |
| P1 upload.php:24 / P2 :24 | File and line number in error JSON | P1, P2 | low | Generic message |
| P1 trips.php:157-159 / P2 :157-159 | PDOException text echoed, including guest paths | P1, P2 | med | Catch PDOException and return a generic 500 |
| P1 trips.php:77,134,147,152 / P2 same | Unvalidated tz stored | P1, P2 | low | Validate once at :44 |
| P1 trips.php:77-78, 88 / P2 trips.php:80,135 | Trip ownership for add and restore relies on the library (P1); a null `own()` result causes a TypeError (P2) | P1 verify; P2 robustness single-pass | verify / low | Confirm ownership in `Trips`; null-check |
| P1 stg-bar.php:16-18,29 / P2 :16-18 | Empty RELEASE_BAR_KEY not refused | P1, P2 | med (conditional) | Refuse if `$key === ''` |
| P1 stg-bar.php:31-32 / P2 :31-32 | CR/LF in an upstream value reaches the Cookie header | P1, P2 | low | `[A-Za-z0-9._-]*` |
| P1 stg-csp.php:8-16 / P2 :8-16 | Unauthenticated Held writes; P1 adds possible stored XSS of `$blocked` | P1, P2 | low | Host or content-type check plus rate cap; escape on the Held page |
| P1 stg-probe.php:12,31-34,38-39 / P2 :12,32,34,38 | Reveals password length, env path, REMOTE_USER (P1 also PHP_VERSION, PHP_AUTH_USER) | P1, P2 | low | Basename, booleans, no lengths |
| P1 short.php:14-16 / P2 :13-16 | Rate limit stored in the session | P1, P2 | low | Key on IP or device hash |
| P1 stock.php:30,115 / P2 :27-30,115 | Unlimited adopt and discover (P2: no role check on adopt) | P1, P2 | low | Per-user cap; role check for adopt |
| P1 stock.php:31 | PDOException message echoed | P1 single-pass | low | Catch PDOException |
| P2 stock.php:41-47 | `?record` returns any order's files with no per-order scope | P2 single-pass (P1 accepted `canOrders`) | med (verify) | Per-order permission |
| P1 suggest.php:75,96 / P2 :75,96 | Raw `PlaceScope` SQL fragment from `$_GET` | P1, P2 | verify (high if unsafe) | Confirm int-only, or bind args |
| P1 suggest.php:276-277 | City and state for every merchant; private-location flag unknown | P1 single-pass | check, low | Blank `sub` if a flag exists |
| P1 tag-card.php:15,17 / P2 :15,17 | Hidden or held thing served from cache for up to ~70 min | P1, P2 | med | Clear the key on hide or hold; `private` header |
| P1 upload-act.php:19 / P2 :19 | `regional_admin` fallback is broader than stated; swallows exceptions (P1); no region scope (P2) | P1, P2 | low (check) | Confirm parity with admin/api and `Workflow::move` scope |
| P1 vault-lock.php:9 / P2 :9 | GET locks the vault (link scanners) | P1, P2 | low | GET shows a button that POSTs |
| P1 vcard.php:17 / P2 :17 | Save count can be inflated | P1, P2 | low | Dedupe per device per day |
| P1 wire-card.php:3,12 / P2 :11-12 | Code-entry step not tied to the requester | P1, P2 | med (verify) | Session marker set at code verification |
| P1 upgrade.php:13 (P2 raises the same open question) | Plan or features may be readable for any listing | P1 (P2 note only) | check, low | Pass the listing only if the caller manages it |
| P1 spell.php:11 | No length cap on `t` | P1 single-pass | low | `mb_substr(…, 0, 500)` |
| P1 vault-ext.php:24,35 | No visible throttle on token guessing | P1 single-pass | check, low | Per-IP failure counter |
| P1 video/jobs.php:11 | Lease `limit` not clamped | P1 single-pass | low (check) | `max(1, min(10, …))` |
| P2 suggestion.php:21, trips.php:38 | CSRF token in a GET JSON response; no-store not shown | P2 single-pass | low | `Cache-Control: no-store` |

### api-auth

| file:line | finding | passes | severity | fix |
|---|---|---|---|---|
| P1 revert.php:61–84 / P2 revert.php:76-84 | Forced password reset stated in the comment but not done | P1, P2 | high | must-reset flag or unusable hash in the transaction (human decision) |
| P1 revert.php:76–84, 77; confirm-change.php:49–57 | Other pending `email_revert` tokens stay valid, so the attacker can re-revert | P1 single-pass | high | Consume all of the user's unconfirmed revert rows |
| P1 revert.php:80–81 / P2 :80 | No collision check on the restored address | P1, P2 | med | Same `id != self` check, return 409 |
| P1 revert.php:76–84 | Outstanding reset tokens not invalidated (conditional) | P1 single-pass | med | Expire them in the transaction |
| P2 revert.php:32,76 | Used-token check outside the transaction (race) | P2 single-pass | low | Conditional UPDATE plus rowCount |
| P2 revert.php:55 | No limit on `confirm_email` guesses | P2 single-pass | low | Invalidate after N failures |
| P1 revert.php:40,55 | `hash_equals` TypeError on non-string metadata | P1 single-pass | low | `is_string` check |
| P1 login.php:11–14, 27–30 / P2 login.php:22-31 | No brute-force limiter; captcha off until keys set | P1, P2 | high | Per-IP and per-account counter, 429 |
| P1 login.php:14 / P2 login.php:13-14 | Captcha bypass keyed on email only (TestAccounts) | P1, P2 | med (verify) | Require a server secret via `hash_equals` |
| P1 login.php:25–26 / P2 :25-26 | 409/403 may reveal that an account exists | P1, P2 | med (verify) | Raise those codes only after the password verifies |
| P1 register.php:38–41 vs 96–100 | 409 vs 201 reveals whether an email has an account | P1 single-pass | med | Uniform 201; email the existing owner |
| P1 request-reset.php:14,21–35; register.php:14,34–94 / P2 request-reset.php:14,26; register.php:14,82 | No throttle while captcha is off; mail bombing | P1, P2 | med | Per-IP and per-target limits |
| P1 resend-confirmation.php:14–16 / P2 :14-16 | Limit is per session only | P1, P2 | med | Server-side per email and IP |
| P1 request-change.php:29–34, change-password.php:24 / P2 change-password.php:24-28, request-change.php:32 | Unlimited current-password guessing | P1, P2 | med | Per-user failure counter |
| P1 request-change.php:36–40 / P2 :36-39 | Reveals whether an address is registered | P1, P2 | low | Uniform "code sent" |
| P1 request-reset.php:23–35, resend-confirmation.php:22–28 / P2 same | Timing reveals existing accounts | P1, P2 | low | Async mail |
| P1 change-password.php:50; request-change.php:59; confirm-change.php:71,85; request-reset.php:33; register.php:93 / P2 same | Email addresses in error_log | P1, P2 | low | Log the user id |
| P1 request-reset.php:27 | Mail sent to the typed address; lookup not lower-cased | P1 single-pass | low | Send to the stored email |
| P1 request-change.php:31–32 | NULL password hash causes a TypeError (conditional) | P1 single-pass | low | `is_string($hash)` |
| P1 confirm-change.php:63 | Revert token in a query string | P1 single-pass | low | `Referrer-Policy: no-referrer` |
| P2 confirm-change.php:55 | Revert token stored in plaintext | P2 single-pass | low | Store sha256 |
| P2 confirm-change.php:59-60 | PDOException returned as 409 "email taken" with its message | P2 single-pass | low | Dedicated exception class |
| P2 confirm-change.php:25 | OTP attempt cap not shown | P2 single-pass (P1 lists as not shown) | verify | Confirm in `StepUpAuth` |
| P2 change-password.php:53 | "Other sessions signed out" not verifiable | P2 single-pass | verify | Confirm in `Auth` |
| P2 verify-email.php:35-41 | Login CSRF: an attacker's verification link signs the victim into the attacker's account | P2 single-pass | med | Auto sign-in only for the registering session |
| P1 verify-email.php:25 / P2 :25 | Setup rights granted before the suspended/closing check | P1, P2 | low | Move inside the `if` at :35 |
| P1 verify-email.php:36,43 / P2 :36 | CSRF token not rotated at sign-in | P1, P2 | low | Regenerate after :36 |
| P1 setup.php:15 / P2 :15 | `tv_setup` never cleared | P1, P2 | low | Unset on success |
| P1 google.php:39,104 / P2 same | `next` not passed through `safeNext` | P1, P2 | low (conditional) | Wrap with `safeNext` |
| P2 google.php:34,59,99 | Raw exception text reaches users | P2 (P1 only notes escaping of :34/:99 is not shown) | low | Generic text; escape |
| P2 google.php:31,96 (P1 notes not shown) | Session regeneration on sign-in not shown | P1, P2 (verify) | verify | Confirm in `GoogleAuth::signIn` |

### listing-api

| file:line | finding | passes | severity | fix |
|---|---|---|---|---|
| P1 claim.php:97,103,106,117 / P2 claim.php:96–117,144 | Claim actions return or act on any `claim_id`, exposing text_code and phones; no endpoint ownership check | P1, P2 | high if the library lacks the check (verify) | Guard as at :109 before the switch |
| P1 claim.php:125 / P2 :125 | Dispute returns the other party's `claim_id` | P1, P2 | med | Return only `dispute_id` |
| P1 claim.php:110 / P2 :109-110 | Sibling addresses returned at any claim status | P1, P2 | med | Require a confirmed claim or omit the address |
| P1 claim.php:82,102,140 | No visible limit on wrong codes | P1 single-pass | med (verify) | Attempt counter and lockout |
| P1 claim.php:115-117 / P2 claim.php:116 | `activate` (money) depends on the library | P1, P2 | verify | Ownership guard; plan allow-list; enforce `agreed` |
| P1 claim.php:155 | User `$action` written raw to the log | P1 single-pass | low | `json_encode` |
| P1 claim.php:40,143 | Migration paths disclosed | P1 single-pass | low | Generic message |
| P2 claim.php:69 | `account_phone` not validated | P2 single-pass | low | `phone10` check |
| P1 claim.php:128-129 / P2 :128 | Resend throttle per session | P1, P2 | low | Per `$uid` |
| P1 claim.php:3,9,73-149 / P2 :9,158 | Documented `document` action missing | P1, P2 | low | Note only |
| P1 evidence.php:30 / P2 :30 | `use` skips the dispute freeze and the admin queue for protected fields | P1, P2 | med (queue part verify) | `Claims::frozen` check; queue protected fields |
| P1 leave.php:46,48,57 / P2 same | Bearer-code actions with no visible rate limit; check exposes contact details; resend can repeat mail | P1, P2 | med (verify) | Rate-limit failures; throttle resend per code |
| P2 leave.php:44 | Unclaimed-only rule not enforced here | P2 single-pass | verify | Confirm in `ListingMessages::leave` |
| P1 leave.php:38,61 / P2 :61 | Only InvalidArgumentException caught; null body (P1) | P1, P2 