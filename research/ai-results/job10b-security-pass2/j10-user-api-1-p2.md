# Security review: user-api public endpoints, part 1 (draft for human review)

**Scope and limits.** I reviewed the 27 endpoint files attached. No decision documents were attached. Where I cite a rule, I use the decision the file's own header points to; I could not check what that section actually says (decision text: not shown). Almost every check lives in `app/lib/*`, which was also not shown. So "relies on the library" means I could not confirm the check exists, not that it is missing.

**Money.** The only money-adjacent path is `qr.php` `trial` (QR plan). No endpoint in this part charges, refunds or credits.

---

## Cross-cutting: database error text sent to the client

`PDOException` extends `RuntimeException`. Several files catch `RuntimeException` and return `getMessage()` to the caller. A database error therefore reaches the client as SQL error text, which can include table names, columns and constraint names.

- `posts.php:81-83` sends the message with a 500.
- `qr.php:57-58` sends it with a 400.
- `pulse-act.php:62-63` sends it with a 429 labelled `limited`.
- `staff-link.php:21-22` sends it with a 400.
- `delete-account.php:21,31` and `restore-account.php:22,29` send it with a 409.
- `context.php:75-77` returns any `Throwable` message (160 chars). Its lines 73-74 already handle PDO correctly.
- `mailboxes.php:47-49` returns any `Throwable` message (200 chars). Mail-server errors can include host, login and server replies.

**Smallest fix:** add `if ($e instanceof PDOException) { error_log(...); Response::error('That didn\'t work.', 500, 'server_error'); }` before returning the message. `context.php:73` is the existing model. For `context.php:77` and `mailboxes.php:49`, return a fixed message.

---

## Per file

**bell.php**: nothing found. Signed in (`:17`), read-only, and all queries are keyed to `$uid`.

**business-messages.php**
- `:27` The "Run app/migrations/…" message goes out before the sign-in check, so a signed-out caller learns internal file paths. Low. Fix: move `:28-29` above `:27`.
- `:56-57` Sending an offer or ask to every subscriber needs only `BizMessages::isStaff` (`:33`), so any team role can do it. Whether 0047 §3 limits bulk sends to Owner/Manager is not shown. Low; needs checking against 0047 §3. Fix if confirmed: add a role check in `$staffOf` for `offer`/`ask`.

**comms.php**: nothing found.

**community.php** (GET is open to signed-out callers)
- `:61,73` `owner` is returned to anyone, signed out included. Its contents (`Community::owner`) are not shown. If it carries a person's name or contact details for a person-owned group, that leaks owner data. Med, pending a check against 0137 §3. Fix: return only the name for listing-owned groups and a public display name for people.
- `:82-83` Members, posts and the report queue go to any signed-in user. The header (`:5`) says "members and posts only for members", but the endpoint itself does not check membership; it relies on the library. Med until the library is confirmed. Fix: gate with `$mrow` / `$can['moderate']` here, e.g. `'members' => $mrow ? … : []`.
- `:120,127,153-159,162` Edit, manage, chapter, welcome and invite rely entirely on the library. In the same file, tags and codes are guarded at the endpoint with `$mod()` (`:144,165`). Low, since the guarding is inconsistent rather than shown to be missing. Fix: call `$mod()` for `welcome` and `chapter_rule` as with `tags`, after confirming the rule in 0136.
- `:37` Migration path shown pre-auth. Low.

**consent.php**
- `:12` Migration path shown pre-auth. Low.

**context.php**
- `:66` `Roles::grant` takes an email and returns that account's name. If the permission check inside the library is wrong, a team member could map any email to a name. Med (email to identity). Fix: return the name only after the grant succeeds, and the same "invited" reply whether or not the email has an account.
- `:77` Raw error text (see cross-cutting).

**delete-account.php**
- `:19` When `AccountDeletion::ready()` is false, the account is erased at once even though the person did not ask for "now". The header (`:3`) and 0091 make a 30-day close the default, so this is an irreversible action the person did not choose. Med. Fix: if `!ready` and `now` is not set, return a 503 ("try again shortly") instead of running the deletion.

**links.php**: nothing found. The check view is public but returns `false` for every key when signed out (`:23`).

**mailboxes.php**
- `:31` `connect` takes a user-supplied `host`, `port`, `smtp_host` and `smtp_port`, and the server then connects to them (`:23` `syncFor`, `:37` `send`). This is a server-side request forgery risk: the server could be pointed at internal addresses or ports. Med/high, pending the library. Fix: refuse private, loopback and link-local addresses and allow only standard mail ports before saving.
- `:31` The box kind is hard-coded to `'staff'` for every caller, including members. The header (`:3`) says "their own, or ours they oversee". Med; check what `'staff'` grants in the library. Fix: pass the caller's real context (member or staff) from `Roles`.
- `:22-23` A GET writes read state and triggers a mail sync. That is a state change without CSRF, and a repeated GET can be used for load. Low. Fix: rate-limit `syncFor` per user.
- `:49` Raw error text (see cross-cutting).

**marketing.php**
- `:26` The admin test uses `$me['is_admin']`. `community.php:42` uses an `admin_access` table row instead. If `is_admin` is not populated, admins lose access; if it is stale, former admins keep it. Low. Fix: use the same `admin_access` check.
- `:37` `(bool) "false"` is true. This fails safe (marketing turns off). Low. Fix: `!empty($b['no_marketing']) && $b['no_marketing'] !== 'false'`, or require a JSON boolean.

**marks.php**: nothing found.

**member-profile.php**
- `:29` `use_photo` passes a client-supplied path (`'/uploads/…'`) to the library. Without checks this is a path traversal risk, or a way to use another person's upload. Med, pending the library. Fix: accept an upload id owned by `$uid`, not a path.
- `:31,33` Only `avatar_src` is stripped before `save`. All other keys rely on the library's allow-list. Low. Fix: `array_intersect_key($b, MemberProfile::FIELDS)`.

**notice.php**
- `:25` No `requireMethod('POST')`, so any non-GET method reaches the actions. Low. Fix: add `Response::requireMethod('POST')` before `:25`.
- `:43-44` `resend` is open to anyone and sends email to any address given. That allows email bombing; no rate limit is shown. Med. Fix: rate-limit per IP and per address.
- `:35` A token lookup with no attempt limit is shown. Low. Fix: rate-limit failed token attempts.

**notices.php**: nothing found.

**people.php**
- `:48-50` `?view=profile&id=N` returns any user's name to any signed-in user. Ids are sequential, so someone can list every member's name, private profiles included (`profile.php` has `private`). Med; this privacy rule (0047 §4) is not shown. Fix: return the name only when `People::relation` allows it or the profile is public; otherwise "not available".
- `:72-73` `invite` sends a message to any contact given; no rate limit is shown. Low/med. Fix: a per-user daily cap.

**post-media.php**: nothing found at the endpoint. Unposted media goes to its uploader only (`:19`), posted media goes through `canSee` (`:23`), and held copies need a staff check (`:26`). This assumes `serve` exits (not shown).

**posts.php**
- `:44` `?comments=N` has no `canSee` check at the endpoint, while `?id` does (`:41`). Comments on a post the viewer may not see could leak. Med, pending the library. Fix: load the post and check `canSee` as in `:40-41`.
- `:75` `report` skips the `Reports::ready` check that the other files have. Low.
- `:83` Raw error text (see cross-cutting).

**profile.php**: nothing found. The whole body goes to `Profile::save`, which relies on the library's allow-list.

**pulse-act.php**
- `:63` Every `RuntimeException`, PDO errors included, comes back as 429 with its message (see cross-cutting).

**pulse-msg.php**
- `:36` `href` is escaped with `htmlspecialchars`, which does not stop `javascript:` links. If a sender can set `url`, this is cross-site scripting. Med if sender-controlled (not shown). Fix: allow only `/…` or `https://` schemes.
- `:34` A GET marks the message read. Low.

**pulse-views.php**, **pulse.php**: nothing found.

**push.php**
- `:30,42` The subscription endpoint URL comes from the client, and `test` makes the server POST to it. That is a server-side request forgery risk unless the library allows only known push-service hosts. Med, pending the library. Fix: check the endpoint host against an allow-list at subscribe time.

**qr.php** (money)
- `:52-53` `trial` starts the QR plan. Whether it can be restarted to stretch the free trial is not shown. Med; check against 0094. Fix: refuse if the user has ever had a trial.
- `:45` A dynamic code's `target` can be changed after printing, which creates an open-redirect or phishing risk. Low. Fix: allow only http/https and run the same checks as on create.
- `:58` Raw error text (see cross-cutting).

**referrals.php**
- `:37` `u.email AS referrer_email` exposes the referrer's account email to the person who asked for the referral. Med (emails). Fix: return the referrer's display name, or nothing.
- `:15-18,47-51` Confirmation tokens are returned without a `Cache-Control: no-store` header. Low. Fix: add the header as the other files do.

**restore-account.php**
- `:28-33` With the emailed token alone, `now` erases the account permanently. No recent sign-in is required, unlike `delete-account.php:16`. Med. Fix: for `now`, require a sign-in within the last 15 minutes and do not accept the token alone.
- `:21-26` The token alone signs the person in and sets `logged_in_at`, which then also passes delete-account's re-auth check. This is by design per the header. Whether the token is single-use or expires is not shown. Low/med. Fix: make sure `restore` consumes the token.

**saved-searches.php**: nothing found. All queries are parameterised and scoped to `user_id`.

**sign-ins.php**
- `:15` A sign-in method can be removed with no recent-sign-in check, and nothing shown prevents removing the last one. Low/med. Fix: apply the same 15-minute re-auth as `delete-account.php:16`, and refuse to remove the last method.

**staff-link.php**
- `:22` Raw error text (see cross-cutting).

---

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| posts.php:83, qr.php:58, pulse-act.php:63, staff-link.php:22, delete-account.php:21,31, restore-account.php:22,29 | PDOException (a RuntimeException) message returned to client | not shown (general) | med | handle PDOException separately, as context.php:73 does |
| context.php:77, mailboxes.php:49 | any Throwable message returned to client | not shown | med | fixed message, log the detail |
| mailboxes.php:31 | user-supplied IMAP/SMTP host and port (server-side request forgery) | 0172 §4.7 (text not shown) | med/high | block private/loopback addresses; allow standard mail ports only |
| mailboxes.php:31 | box kind hard-coded `'staff'` for every caller | 0172 §4.7 / 0167 §2 | med | pass the caller's real context |
| mailboxes.php:22-23 | GET writes state and triggers sync | — | low | rate-limit sync |
| referrals.php:37 | referrer's email returned to requester | not shown (private data) | med | return display name only |
| referrals.php:15 | tokens without no-store | — | low | add `Cache-Control: no-store` |
| people.php:49-50 | any member's name by id, private profiles included | 0047 §4 | med | check relation/public before returning the name |
| people.php:73 | invite sends with no shown cap | 0047 §4 | low/med | daily cap |
| community.php:61,73 | `owner` shown to signed-out callers | 0137 §3 | med (pending lib) | public-safe owner fields only |
| community.php:82-83 | members/posts/reports not membership-gated at endpoint | 0047, 0137 §5 | med (pending lib) | gate on `$mrow` / `$can` |
| community.php:159,154 | welcome/chapter_rule lack the `$mod()` used at :144,165 | 0136 | low | add `$mod()` after confirming the rule |
| community.php:37, consent.php:12, business-messages.php:27 | migration paths shown before sign-in | — | low | check auth first / generic message |
| context.php:66 | grant by email returns the account's name | 0055 §8 | med (pending lib) | same reply whether or not the account exists |
| delete-account.php:19 | erases at once when `!ready` without "now" | 0091 | med | return 503 unless `now` |
| restore-account.php:28-33 | token alone permanently deletes | 0091 | med | require recent sign-in for `now` |
| restore-account.php:21-26 | token signs in; single-use not shown | 0091 | low/med | consume token on restore |
| member-profile.php:29 | client-supplied upload path | 0127 | med (pending lib) | take an owned upload id |
| member-profile.php:33 | body to `save` with only `avatar_src` stripped | draft 2026-10-09 | low | intersect with FIELDS |
| notice.php:25 | no method check on writes | 0079 | low | `requireMethod('POST')` |
| notice.php:44 | open resend email, no rate limit shown | 0079 | med | per-IP and per-address limit |
| notice.php:35 | no limit on token guessing shown | 0079 | low | rate-limit failures |
| posts.php:44 | comments without `canSee` at endpoint | 0153 | med (pending lib) | check `canSee` as at :41 |
| posts.php:75 | report skips `Reports::ready` | 0153 | low | add the check |
| pulse-msg.php:36 | `href` allows `javascript:` | 0172 | med if sender-set | allow `/` and `https:` only |
| push.php:30,42 | client push endpoint POSTed to by server | 0172 §4.6 | med (pending lib) | push-service host allow-list |
| qr.php:53 | trial may be restartable (money) | 0094 | med (pending lib) | one trial ever per user |
| qr.php:45 | dynamic target changeable (redirect) | 0094 | low | http/https only, re-checked |
| marketing.php:26 | admin test differs from `admin_access` | 0047 §3 | low | use `admin_access` |
| marketing.php:37 | `(bool)"false"` is true | 0047 §3 | low | strict boolean |
| business-messages.php:56-57 | any team role can bulk-send offers/asks | 0047 §3 | low (check) | role check if the decision limits it |
| sign-ins.php:15 | unlink without re-auth; last method not shown as protected | 0090 | low/med | 15-min re-auth; keep one method |

**Nothing found at the endpoint level in:** bell.php, comms.php, links.php, marks.php, notices.php, post-media.php, profile.php, pulse-views.php, pulse.php, saved-searches.php.

**Not seen anywhere in this part:** SQL injection (every SQL shown is parameterised; table names are constants), header injection, and command injection.