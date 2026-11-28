# Security review: public API endpoints, part 2 (draft for human review)

**Scope and limits.** I reviewed only the 21 files attached. None of the decision documents were included, so where I cite a "rule §" I mean the decision as the file's own header describes it, quoted with that file:line. The decision text itself is not shown.

Several outcomes depend on library code that is also not shown, including:
- `Response::requireCsrf`, `Response::json`
- `ShortCodes`, `Trips`, `Tags`, `ContentTags`, `PlaceScope`, `RecordFiles`
- `ClientOrders`, `Releases::verify`, `Vault`, `Uploads`, `VCard`

Where a finding depends on one of these, I say so.

**Money.** None of these files charges, refunds or credits.
- `upgrade.php` says it only reads (`upgrade.php:4`).
- `wire-card.php` hands out payment instructions; see item 8.

---

## Findings

### 1. `upload.php:38`: the CSRF check passes when the session has no token (high, if such sessions exist)
- **Defect.** `hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))` compares `''` to `''` and returns true. This happens when a signed-in session has no `csrf_token` and the request carries no header.
- **Why such sessions are plausible.** `trips.php:36` creates tokens lazily, which suggests sessions can exist without one.
- **Why a cross-site attack works.** `upload.php:29` accepts form-encoded `$_POST`, so a plain cross-site HTML form can send `do=start|done|gallery…` with the victim's cookie and no header.
- **Rule.** decisions/0127, as stated at `upload.php:11`: "Signed in with the page's X-CSRF-Token".
- **Fix.** Require a non-empty token: `$t = (string)($_SESSION['csrf_token'] ?? ''); if ($t === '' || !hash_equals($t, …))`.
- **Also check.** Whether `Response::requireCsrf` has the same flaw (not shown).

### 2. `trips.php:157-159` and `upload.php:52-54`: database error text is echoed to the client (med)
- **Defect.** `PDOException` extends `RuntimeException`. Both handlers return `$x->getMessage()` to the caller (422 in trips, 409 in upload). That message can include SQL fragments, table and column names, and constraint names.
- **Missed fallback in trips.php.** The generic `Throwable` branch at `trips.php:160-163` logs instead of echoing, but it is never reached for DB errors.
- **Rule.** The site's own practice of "errors are logged and never echoed" (`suggest.php:16-17`). No decision for it is shown.
- **Fix.** Add `catch (PDOException $e)` before the `RuntimeException` catch, and send it to the generic log-only path.

### 3. `upload.php:24`: the shutdown handler reveals internals (low)
- **Defect.** The JSON error includes the internal file basename and line number.
- **Fix.** Log them (already done at line 21) and send a generic message.

### 4. `stg-bar.php:16-18`: no fail-closed check on an empty `RELEASE_BAR_KEY` (med; depends on `Releases::verify`, not shown)
- **Comparison.** `staging-updates.php:16` and `stg-probe.php:23` both refuse when their secret is `''`. `stg-bar.php` does not.
- **Risk.** If `verify()` accepts an HMAC computed with an empty key, anyone could forge the bar cookie.
- **Rule.** decisions/0145 Phase 5 (`stg-bar.php:2-4`).
- **Fix.** `if ($key === '') Response::error('Not allowed.', 401);` before line 18.

### 5. `stg-bar.php:31-32`: a value from the upstream response is placed into an outgoing request header (low)
- **Defect.** `([^;"\']*)` also matches CR/LF. Text taken from the 409 body becomes `Cookie:` in the next curl request.
- **Mitigation.** The source is the host's challenge page, not the user.
- **Fix.** Restrict the value to `[A-Za-z0-9]*`.

### 6. `stock.php:41-47`: any order's files for any role that passes `canOrders()` (med; scope not shown)
- **Defect.** `RecordFiles::forOrder(…, (int)$rm[1], …)` takes any order id. There is no check that this staff member may see this order or this client. The returned data includes the client's earlier orders' files (`stock.php:51`).
- **Unknown.** Whether `canOrders()` alone is the intended scope is not shown.
- **Rule.** decisions/0143 and the note at `stock.php:40`.
- **Fix.** Pass the order id into the permission check, e.g. `RecordFiles::canOrder($pdo, $uid, $orderId)`, or confirm that `canOrders` means all orders.

### 7. `stock.php:27-30, 115`: any signed-in member can add to the shared stock library and trigger outside source searches (low)
- **Defect.** `adopt` writes to a site-wide library. `discover` makes outbound calls to the sources, up to 36 results each time. Neither has a role check or rate limit.
- **Rule.** decisions/0143 (`stock.php:2`). Who may adopt is not shown.
- **Fix.** Restrict `adopt` to roles allowed to curate, or rate-limit it per user. Rate-limit `sources=1`.

### 8. `wire-card.php:11-12`: the "entered the emailed code" condition is not visibly tied to the requester (med; `ClientOrders::wireLeft` not shown)
- **What the header requires.** Only "the client with the order's link, after they've entered the code we emailed them" (`wire-card.php:2-4`).
- **What the code checks.** Only the order token, `pending`, `pay_method = wire`, and `wireLeft($o) > 0`.
- **Risk.** If `wireLeft` is a window stored on the order, anyone holding the link (forwarded email, shared device) gets the bank details during that window without entering the code.
- **Rule.** decisions/0101, addendum 2026-10-28.
- **Fix.** Also require a session marker set when the code is verified, e.g. `$_SESSION['wire_ok'][$o['id']] > time()`.

### 9. `short.php:13-16`: the rate limit is per session, so it can be bypassed (low)
- **Defect.** A script that sends no cookie gets a fresh session each time, so the 120-per-hour guard never triggers.
- **Mitigation.** Impact is bounded: there is one code per page (`short.php:4`).
- **Not shown.** Whether `ShortCodes::forPage` enforces "only our own pages" (no open-redirect codes).
- **Fix.** Key the limit on IP, or the device hash already used by `GuestInquiries::deviceHash()`.

### 10. `stg-csp.php:8-16`: unauthenticated writes into the Held ledger (low)
- **Defect.** Any POST to staging creates a `csp` entry with text the caller chooses (truncated). This allows ledger spam or misleading entries in Held.
- **Unknown.** Whether staging's viewing password covers this path. The file does not load bootstrap, and `staging-guard` is not shown.
- **Rule.** decisions/0165 §1.
- **Fix.** Require `Content-Type: application/csp-report` or `application/reports+json`, and rate-limit or deduplicate per blocked URI.

### 11. `stg-probe.php:12, 32, 34, 38`: details about secrets beyond yes/no (low; behind `HEALTH_TOKEN`)
- **Defect.** It returns the length of `STAGING_PASS`, the absolute `.env` path, and `REMOTE_USER` (the viewing-password username). The header promises "only shapes and yes/no, never a value" (`stg-probe.php:5`).
- **Rule.** decisions/0159 step 1.
- **Fix.** Drop the lengths, send `basename` of the env path, and report `REMOTE_USER` as set/not set.

### 12. `tag-card.php:15, 17`: a hidden or held thing keeps showing for up to about 70 minutes (low/med)
- **Defect.** The card is cached server-side for 3600 s and publicly for 600 s. If a listing is hidden or held, its card stays visible until the cache expires.
- **Rule.** decisions/0132 §2a, "nothing for a hidden, held … thing" (`tag-card.php:3-4`).
- **Fix.** Clear `tagcard:<ref>` from wherever hide or hold happens, or shorten the server TTL.

### 13. `trips.php:77, 134, 147, 152`: unvalidated `tz` is passed to `Trips::create` / `claim` (low)
- **Defect.** A validated `$tzName` exists only at line 127, and it isn't the value stored. Whether `Trips::create` validates is not shown.
- **Fix.** Validate `$tz` once at line 44 (same regex, plus a `DateTimeZone` try/catch).

### 14. `trips.php:80, 135`: a null `Trips::own(...)` result reaches `$tripOut`
- **Defect.** A null result causes a TypeError, which returns 500. This is a robustness issue, not a security one.
- **Not shown.** Whether `Trips::add` with a supplied trip id checks ownership; the code relies on it.

### 15. `suggest.php:75, 96`: `PlaceScope::resolve($pdo, $_GET)` returns SQL that is inserted raw (verify; not shown)
- **Defect.** Line 96 concatenates `$scope['sql']` into the query. If that fragment contains any value from `$_GET` rather than placeholders or ints, this is SQL injection on a public endpoint.
- **Fix.** Confirm that `PlaceScope` emits only integer literals; otherwise return `[sql, args]` and merge the args.
- **Rest of the file looks correct.** Status list from a constant, integer ids (lines 147, 153), bound LIKE values, errors logged.

### 16. `vault-lock.php:9`: lock is a GET (low, by design)
- **Defect.** Email link scanners and prefetchers can lock a vault without the owner acting. This is availability only; it can only lock.
- **Rule.** decisions/0156 §4.
- **Fix.** Make the GET show a "Lock the vault" button that POSTs the token.

### 17. `upload-act.php:19`: the `regional_admin` fallback grants the endpoint without a region scope (low; depends on `UploadThreads::act`)
- **Rule.** decisions/0176 §6.
- **Fix.** Confirm that `Workflow::move` limits regional admins to their region.

### 18. `suggestion.php:21` and `trips.php:38`: CSRF token in a JSON GET with no visible Cache-Control (low)
- **Not shown.** Whether `Response::json` sets `no-store`.
- **Fix.** Add `header('Cache-Control: no-store, private')`.

### 19. `vcard.php:17`: the save count is inflatable by anyone (low)
- **Defect.** Any non-bot visitor can raise a business's save count; there is no rate limit.
- **Rule.** decisions/0094, which says counts only.
- **Fix.** Deduplicate the count per device and listing per day.

---

## Nothing found
- **`spell.php`**: signed in, read-only, private cache.
- **`staging-updates.php`**: fails closed, constant-time token check.
- **`tags.php`**: redirect target comes from `Links::resolve` on a validated ref. People suggestions depend on `Tags::suggest` (not shown).
- **`upgrade.php`**: read-only. Whether `offer` reveals details of a listing the caller doesn't own is not shown.
- **`vault-ext.php`**: the token is the authentication; Origin is an extra gate. Email goes only to the token's owner.
- **`video/_auth.php`, `video/jobs.php`, `video/results.php`**: fail closed, `hash_equals`. Line 7 of `results.php` is redundant but harmless.

---

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| upload.php:38 (+29) | CSRF check passes when session token and header are both empty; form posts accepted | 0127 (upload.php:11) | high (if token-less sessions exist) | reject empty session token before `hash_equals` |
| trips.php:157-159 | PDOException message echoed (422) | log-not-echo practice (suggest.php:16-17); decision not shown | med | catch PDOException first → generic 500 |
| upload.php:52-54 | PDOException message echoed (409) | same | med | same |
| upload.php:24 | file and line in error JSON | same | low | generic message only |
| stg-bar.php:16-18 | no fail-closed on empty RELEASE_BAR_KEY | 0145 Ph5 | med (depends on verify) | refuse if `$key === ''` |
| stg-bar.php:31-32 | upstream value into Cookie header (CR/LF allowed) | 0145 Ph5 | low | restrict value to `[A-Za-z0-9]*` |
| stock.php:43-47 | any order's and client's files for any canOrders role | 0143 / note at stock.php:40 | med | per-order permission check |
| stock.php:27-30,115 | any member adopts into shared library / triggers source searches | 0143 | low | role check + rate limit |
| wire-card.php:11-12 | code-entry not visibly tied to requester | 0101 addendum 2026-10-28 | med | require session marker set at code verification |
| short.php:13-16 | per-session rate limit bypassable | 0094 | low | limit by IP or device hash |
| stg-csp.php:8-16 | unauthenticated writes to Held ledger | 0165 §1 | low | check content type, dedupe or rate-limit |
| stg-probe.php:12,32,34,38 | secret length, full env path, REMOTE_USER returned | 0159 step 1 | low | yes/no only, basename |
| tag-card.php:15,17 | hidden or held card served up to ~70 min | 0132 §2a | low/med | clear cache on hide or hold |
| trips.php:77,134,147,152 | unvalidated tz stored | 0174 | low | validate tz once |
| suggest.php:96 | raw `$scope['sql']` from `$_GET` (PlaceScope not shown) | none shown | verify (high if unsafe) | confirm ints only, or bind args |
| vault-lock.php:9 | state change on GET (scanners lock vault) | 0156 §4 | low | GET shows button → POST |
| upload-act.php:19 | regional_admin with no region scope here | 0176 §6 | low | confirm scope in Workflow::move |
| suggestion.php:21, trips.php:38 | CSRF token in GET JSON, cache headers not shown | none shown | low | `Cache-Control: no-store` |
| vcard.php:17 | save count inflatable | 0094 | low | dedupe per device per day |