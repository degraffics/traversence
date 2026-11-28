# Security review: user-api public endpoints (part 1, draft for human review)

**Scope and limits.** I could see only the 26 endpoint files. None of the libraries they call (`Response`, `Auth`, `Roles`, `Community`, `Mailboxes`, `MemberPosts`, `WebPush`, `ShortCodes`, `AccountDeletion`, and others) and none of the rules or decision documents were attached. So:

- The "rule §" column cites the decision section the file's own header names, or says *rules doc not shown*.
- Findings that depend on a library are marked **verify**.

## Defects

### delete-account.php
- **19**: The account is deleted permanently whenever `!AccountDeletion::ready($pdo)`, even if the person did not ask for `now`. The header (3) says the default is a 30-day close. While the migration hasn't run, a person who expects 30 days to change their mind loses everything for good. **Med.**
  - Fix: if `now` was not asked for and `ready()` is false, return 503 "Try again shortly" instead of calling `run()`.

### restore-account.php
- **16, 28–33**: The emailed Restore token alone allows `action: 'now'`, an irreversible delete, with no sign-in. delete-account.php:4/16 requires a sign-in within 15 minutes for the same act "so a phone left signed in can't be used". Here a leaked or forwarded email can do it. **Med.**
  - Fix: allow `now` only through the session path (line 17), not the token path (line 16).

### referrals.php
- **37, 50**: Returns `u.email AS referrer_email` (the referrer's account email) to whoever requested the referral. This exposes another member's email. The rows are also chosen by `requested_by_user_id` (42), not by current ownership of the listing. **Med** (private-data rule; rules doc not shown).
  - Fix: return the referrer's display name, or a masked email.

### posts.php
- **44**: `?comments=N` is served without the `canSee` check that `?id` applies at 41. **Verify** whether `MemberPosts::comments` checks visibility. If it doesn't, comments on private posts leak. **Med.**
  - Fix: fetch the post and apply `canSee` before returning comments.
- **81–83**: `PDOException` extends `RuntimeException`, so database errors reach this handler and their message is sent to the client with a 500. The generic handler at 84 never sees them. **Low.**
  - Fix: catch `PDOException` first and return the generic message (as context.php:73 does).

### pulse-msg.php
- **36**: `$m['url']` is HTML-escaped but its scheme isn't checked, so a `javascript:` URL in a message would run when clicked. **Verify** who can set `comms_messages.url`. **Med.**
  - Fix: accept only URLs starting with `/` or `https://`; otherwise leave the link out.
- **32–34**: A GET marks a message read (a state change) with no CSRF check. Ids are integers, so a cross-site image tag could clear someone's unread items. **Low.**
  - Fix: mark read only on a POST with CSRF, or require an explicit `&read=1` from the page.

### mailboxes.php
- **31, 23**: `connect` takes a user-supplied `host`, `port`, `smtp_host` and `smtp_port`, and `syncFor` then connects to them from the server. This is a possible SSRF to internal hosts. **Verify** validation in `Mailboxes::connect`. **Med.**
  - Fix: refuse private, loopback and link-local addresses; allow only IMAP/SMTP ports.
- **31**: The scope `'staff'` is hard-coded for every signed-in caller, not derived from `Roles`. The header (3) says only the person's own mailboxes, or ours they oversee, are allowed. **Verify** how `connect` uses this argument. **Low/Med.**
- **49**: Any `Throwable` message (up to 200 characters, including IMAP and SQL errors) is returned to the client. **Low.**
  - Fix: return the generic message and log the detail (as marks.php:42 does).

### push.php
- **30, 42**: The server POSTs to whatever `endpoint` the client subscribes, which is a possible SSRF. **Verify** `WebPush::subscribe`. **Med.**
  - Fix: require `https` and an allow-list of push-service hosts.

### people.php
- **48–50**: Any signed-in user can call `?view=profile&id=N` for any id and get the person's name. There is no visible check for blocks or for a private profile (profile.php:4 has a `private` flag), so names can be enumerated by id. **Verify** `People::user`. **Med** (privacy; rules doc not shown).
  - Fix: return 404 when the person is blocked or private and not connected.

### post-media.php
- **17 → 20/23/26**: `$part` (from `?s=`) goes to `serve()` unchecked. The header (2) lists only `full|card|video|poster`. A path injection is possible if `serve` builds a file path from it. **Verify.** **Low/Med.**
  - Fix: reject anything outside those four values.

### member-profile.php
- **29**: `use_photo` takes a path string. **Verify** that `useUpload` confirms the file is this user's own upload and blocks `..`. **Low/Med.**

### context.php
- **74, 77**: Raw exception messages go to the client. At 77, a `TypeError` or `Error` message can include file paths. **Low.**
  - Fix: generic message at 77; at 74, show only messages the libraries deliberately throw for users.

### pulse-act.php, qr.php, staff-link.php
- **pulse-act.php:62–63, qr.php:57–58, staff-link.php:21–22**: `RuntimeException` messages, including `PDOException`, are returned to the client. pulse-act.php labels them 429 "limited". **Low.**
  - Fix: catch `PDOException` separately and return a generic message.

### qr.php (money)
- **52–54**: `trial` starts the QR plan. This is the only money-related path in this part. **Verify** that `startTrial` allows one trial per account (and per listing), so the free trial can't be restarted indefinitely. **Low/Med.**
- **41**: QR `target` is user-chosen, so codes could point to phishing URLs. **Verify** scheme checks in `ShortCodes::create`. **Low.**

### community.php
- **42, 57, 137–138**: Any row in `admin_access` sets `$admin`, which shows hidden groups and allows hide/unhide, whatever the admin's role. **Low.**
  - Fix: check a specific permission rather than any row.
- **61, 73**: `owner` is sent to signed-out visitors. **Verify** that `Community::owner` returns no email or phone. **Low.**
- **65, 109, 161**: `People` and `Consent` are used but not `require`d in this file. Whether they are autoloaded is not shown. Robustness only.

### marketing.php
- **26**: The admin test is `$me['is_admin']`, while community.php:41–42 uses the `admin_access` table ("same test as the dashboard"). The two tests can disagree. **Low.**
  - Fix: use the same `admin_access` check.

### notice.php
- **25**: No `requireMethod('POST')` (only CSRF). **Low.**
- **44**: `resend` is unauthenticated and sends email to the given address, with no rate limit visible. **Low.**
  - Fix: rate-limit by IP and by address.

### sign-ins.php
- **15**: A sign-in provider can be unlinked with no recent-sign-in check and no visible check that another way to sign in remains. **Verify** `SignInLinks::unlink`. **Low.**

### Cross-cutting
- These files do not send `Cache-Control: no-store`, although they return private data and CSRF tokens: business-messages, community, consent, links, marketing, member-profile, notices, people, profile, referrals, post-media, qr. Whether `Response::json` or `serve` sets it is not shown. **Low.**
  - Fix: add `header('Cache-Control: no-store')` as bell.php:15 does.
- These files reveal migration filenames to callers before sign-in (`not_configured` errors): business-messages:27, community:37, consent:12, links:15, marketing:18, people:21, saved-searches:17. **Low.**

## No defects found (from what's shown)
bell.php, comms.php, consent.php, links.php, marks.php, notices.php, profile.php, pulse-views.php, pulse.php, saved-searches.php (all SQL is parameterised), business-messages.php (apart from the cross-cutting items).

- **SQL injection:** none seen in the endpoint files. All inline SQL is prepared, and table names come from code.
- **Nations' land / private-place addresses:** nothing visible in this part. Place lookups (`Community::findPlaces`, `GroupGrowth::findAbout`) are in libraries not shown.
- **Charges, refunds, credits:** none in this part apart from the QR trial. Whether account deletion cancels a paid plan is not shown.

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| delete-account.php:19 | Permanent delete when 30-day close isn't ready | decisions/0091 (per header :3) | Med | 503 unless `now` |
| restore-account.php:16,28–33 | Emailed token alone allows irreversible delete; skips 15-min re-sign-in | decisions/0091; delete-account.php:4 | Med | `now` only via session path |
| referrals.php:37 | Referrer's email sent to requester | private-data rule (not shown) | Med | Name or masked email |
| posts.php:44 | Comments served without `canSee` (verify) | decisions/0153 | Med | Check `canSee` first |
| pulse-msg.php:36 | `javascript:` href possible | not shown | Med | Allow only `/` or `https://` |
| mailboxes.php:31,23 | SSRF via IMAP/SMTP host (verify) | decisions/0172 §4.7 | Med | Block private IPs, fix ports |
| mailboxes.php:31 | Scope `'staff'` hard-coded (verify) | decisions/0172 §4.7 | Low/Med | Derive from `Roles` |
| push.php:30 | SSRF via push endpoint (verify) | decisions/0172 §4.6 | Med | https + push-host allow-list |
| people.php:48–50 | Names enumerable by id; no block/private check (verify) | decisions/0047 §4 | Med | 404 if blocked/private |
| post-media.php:17 | `s=` not validated (verify path) | decisions/0153 | Low/Med | Allow-list 4 values |
| member-profile.php:29 | `use_photo` path ownership (verify) | decisions/0127 | Low/Med | Check owner, no `..` |
| qr.php:52–54 | Trial may be restartable (verify) | decisions/0094 | Low/Med | One trial per account |
| qr.php:41 | Unchecked QR target URLs (verify) | decisions/0094 | Low | Scheme check |
| posts.php:83 | DB error text leaked | not shown | Low | Catch `PDOException` |
| pulse-act.php:62–63 | DB errors shown as 429 with message | not shown | Low | Catch `PDOException` |
| qr.php:57–58 | Exception text leaked | not shown | Low | Generic message |
| staff-link.php:21–22 | Exception text leaked | not shown | Low | Generic message |
| context.php:74,77 | Exception text and paths leaked | not shown | Low | Generic message |
| mailboxes.php:49 | Exception text leaked | not shown | Low | Generic message |
| pulse-msg.php:32–34 | Mark-read on GET, no CSRF | not shown | Low | POST + CSRF |
| community.php:42,137–138 | Any `admin_access` row = full group admin | decisions/0137 §5 | Low | Specific permission |
| community.php:61,73 | Owner data to signed-out visitors (verify) | decisions/0137 §3 | Low | No contact fields |
| marketing.php:26 | Admin test differs from `admin_access` | decisions/0047 §3 | Low | Same test as dashboard |
| notice.php:25 | No POST method check | decisions/0079 | Low | `requireMethod('POST')` |
| notice.php:44 | Unauthenticated email resend, no rate limit seen | decisions/0079 | Low | Rate-limit |
| sign-ins.php:15 | Unlink without re-sign-in or last-method check (verify) | decisions/0090 | Low | Recent auth + keep one method |
| (12 files, see above) | No `Cache-Control: no-store` on private data | not shown | Low | Add header |
| (7 files, see above) | Migration names revealed before sign-in | not shown | Low | Generic 503 |