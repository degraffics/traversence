# Security review: public endpoints in `api/` (part 2)

**Scope and limits.** I reviewed the 20 files attached, using only what they show. No rules document or decisions text was attached. The "rule §" column therefore cites the decision each file names in its own header comment, or the file's own stated promise. Where neither applies, it says "not shown." Many checks happen inside library classes (`Trips`, `Uploads`, `ShortCodes`, `PlaceScope`, `ClientOrders`, `Vault`, and others) that are not shown. I flag those as things to confirm, not as defects. This is a draft for human review.

---

## Findings by file

### short.php
- **Access, CSRF and injection:** Public POST with no CSRF check. That is consistent with the stated design (`short.php:4`, "anyone may ask"). Validation of "only our own pages" is inside `ShortCodes::forPage` (not shown).
- **D1 (low). The rate limit lives in the session.** `short.php:14-16` keeps the 120/hour counter in `$_SESSION`. A script that drops cookies gets a fresh session on every call, so the limit does not stop "a script asking for thousands" (`short.php:12`).
  - Breaks: decisions/0094, as the file comment states.
  - Smallest fix: also key the counter on `REMOTE_ADDR`, using whatever store the site already uses for per-IP limits.
- **Private data and money:** none shown.

### spell.php
- **Access:** Signed in (`spell.php:10`). GET, read-only.
- **D2 (low). The input text has no length cap.** `spell.php:11` passes `$_GET['t']` of any length to `Spelling::check` and `ItemKinds::fit`. Whether those functions cap it is not shown.
  - Breaks: not shown.
  - Smallest fix: `$t = mb_substr(..., 0, 500);`
- **Injection, private data and money:** nothing found.

### staging-updates.php
- **Access:** Staging only (`:11`). POST only. Requires the `HEALTH_TOKEN` header, compared with `hash_equals`, and fails closed when the token is empty (`:14-16`). CSRF is not applicable because a custom header carries the token.
- **Nothing found.**

### stg-bar.php
- **D3 (med, conditional). The signing key is not checked for emptiness.** `stg-bar.php:16` reads `RELEASE_BAR_KEY` but never checks that it is set.
  - If it is empty, `Releases::verify($bar, '')` (`:18`) and the request signature `hash_hmac('sha256', $body, '')` (`:29`) both use an empty key. Anyone could then forge a bar cookie and a request the live site would accept.
  - Whether `Releases::verify` refuses an empty key is not shown.
  - Breaks: decisions/0145 Phase 5.
  - Smallest fix: `if ($key === '') Response::error('Not configured.', 503);` before line 17. Compare `staging-updates.php:16`, which already does this.
- **D4 (low). Header injection from the upstream reply.** `stg-bar.php:31-32` copies a cookie value out of the upstream 409 body into an outgoing `Cookie:` header. The regex `[^;"\']*` does not exclude CR or LF, so a crafted challenge body could inject extra request headers.
  - Breaks: not shown.
  - Smallest fix: change the value class to `[A-Za-z0-9._-]*`.
- **CSRF:** POST requires `X-TV-STG-BAR: 1` (`:20`), which a cross-site form cannot send. GET is fixed to the action `state` (`:22`). Adequate.

### stg-csp.php
- **D5 (low). Anyone can write to the Held ledger without limit.** `stg-csp.php:8-16` accepts any POST with no token, no rate limit and no origin check. Each one writes a row to the Held ledger (`StagingGate::record`).
  - A caller can flood Held with fake "csp" entries.
  - `$blocked` is stored as-is. If the Held page does not escape it, that becomes stored XSS (the Held page is not shown).
  - Whether this path sits behind staging's viewing password is not shown.
  - Breaks: decisions/0165 §1.
  - Smallest fix: drop a report whose `document-uri` host is not staging's own host, and cap rows per minute.

### stg-probe.php
- **Access:** Token-gated (`:21-23`). Good.
- **D6 (low). The output includes values, which the file says it never does.** The file promises "only shapes and yes/no, never a value" (`stg-probe.php:5`), but it returns several values:
  - `env path` is the full server filesystem path (`:32`).
  - `REMOTE_USER` and `PHP_AUTH_USER` are the viewing-password usernames (`:38-39`).
  - `PHP_VERSION` (`:31`).
  - The exact character count of `STAGING_PASS`, in both the "wrong shape" branch (`:12`, `:34`) and the right-shape branch (`:34`).
  - Breaks: the file's own rule (`:5`) and decisions/0159 step 1.
  - Smallest fix: return `basename(Database::envPath())`, report the usernames as `!== ''` booleans, and drop the lengths.

### stock.php
- **Access:** Signed in (`:20`). POST requires CSRF (`:25`). `?mine` falls back to the caller's own gallery unless `Gallery::canManage` passes (`:60`). `?record` is gated by `RecordFiles::canOrders` (`:43`).
- **D7 (low). Database error text can reach the user.** `stock.php:31` catches `RuntimeException` and echoes `getMessage()`. `PDOException` extends `RuntimeException`, so a database error raised inside `StockLibrary::adopt` would send SQL error text to the user.
  - Breaks: not shown.
  - Smallest fix: add `catch (PDOException $x) { error_log(...); Response::error('That didn\'t work.', 500); }` before line 31.
- **D8 (low). Any member can trigger external source searches without limit.** `stock.php:115` lets any signed-in member make unlimited `StockLibrary::discover` calls to external photo sources. That can use up API quota. Whether `discover` or `adopt` (`:30`) limit this is not shown.
  - Breaks: decisions/0143.
  - Smallest fix: a per-user, per-hour counter before `:115` and `:30`.
- **Private data:** `?record` returns files from the client's earlier orders (`:47-52`). That is staff-only via `canOrders`, whose rules are not shown. Nothing else found.
- **Money:** none shown.

### suggest.php
- **Injection:** User values are bound throughout, and the LIKE escaping is consistent. `$statusList` (`:90`), `$ph` (`:147`) and `$in` (`:153`) are built from constants or integers.
- **D9 (check). A SQL fragment comes from request input.** `suggest.php:96` concatenates `$scope['sql']` from `PlaceScope::resolve($pdo, $_GET)` (`:75`). No bound arguments are added to `$boxArgs` for it, so any request values must be embedded in that fragment. Whether `PlaceScope` casts to int or quotes them is not shown.
  - Severity is high if it does not.
  - Smallest fix: confirm that `PlaceScope` only ever embeds `(int)` ids, or have it return `['sql', 'args']`.
- **D10 (check, low). City and state are shown for every listed merchant.** `suggest.php:276-277` returns city and state for every merchant in the status whitelist. `vcard.php:3` says a shelter's location can be private. Whether a private-location flag exists on `entities`, and whether city counts as "location," is not shown.
  - Breaks: decisions/0094 (vcard's rule) and the "private places" rule (not shown).
  - Smallest fix: if such a flag exists, blank `sub` for those rows.
- **Money:** none.

### suggestion.php
- **Access:** Public. POST requires CSRF (`:25`). The honeypot is checked at `:27`. Reading the token through GET (`:21`) is safe under same-origin rules.
- **Note:** Unlike `trips.php:36`, this file never creates `$_SESSION['csrf_token']`, so the token it returns may be `null`. Whether `Response::requireCsrf` then fails closed is not shown. See D12 for why this matters.
- **No defect found** in what is shown.

### tag-card.php
- **Injection:** Strict regex on `ref` (`:12`). The cache key is built only from validated input.
- **D11 (med). A thing that becomes hidden stays visible for up to an hour.** `tag-card.php:15` caches the card server-side for 3600 s, and `:17` adds `Cache-Control: public, max-age=600`. A place or listing that is hidden or held after being cached is still served for up to 60 minutes, plus up to 10 minutes in shared and browser caches. This breaks "nothing for a hidden, held or unknown thing." That matters for private places and nations' land.
  - Breaks: decisions/0132 §2a.
  - Smallest fix: clear the `tagcard:<ref>` key wherever a thing is hidden or held, and change the header to `private`.

### tags.php
- **Open redirect:** Not possible from what is shown. `go` is restricted to `p:(hub|geo_hub|cluster):\d+` (`:16`), and the URL comes from `Links::resolve`, which is not shown.
- **Private data:** "People only when signed in" is enforced inside `Tags::suggest`. Checks on `group` membership are not shown.
- **No defect found.**

### trips.php
- **Access:** Guest actions `addable`, `resolve` and `journey` are read-only. All other actions need sign-in (`:67`) and CSRF (`:41`). Trip ownership is checked explicitly only for `use_journey` (`:105`). Every other action relies on `Trips::add`, `move`, `share` and so on checking `$uid` against the trip. That is not shown and should be confirmed for `add` (`:77-78`, with a client-supplied `trip`) and `restore` (`:88`, with a client-supplied `undo.trip`).
- **Injection:** The table name at `:125` comes from a fixed whitelist. Safe.
- **D12 (med). Database error text can reach guests.** `trips.php:157-159` echoes `RuntimeException::getMessage()` to the client. `PDOException` is a `RuntimeException`, so any database error, including on the guest-reachable `addable`, `resolve` and `journey` paths, returns raw SQL error text and table names.
  - Breaks: not shown. The generic path at `:160-163` shows the intent.
  - Smallest fix: add `catch (PDOException $e)` before `:157` and route it to the generic 500.
- **D13 (low). Unvalidated timezone is stored.** Line `:127` validates `$tz` into `$tzName`, but the raw `$tz` is what gets stored at `:77`, `:134`, `:147` and `:152`. Whether `Trips::create` or `claim` validates it is not shown.
  - Breaks: decisions/0174.
  - Smallest fix: validate `$tz` once at `:44` with the same regex.
- **Money:** none.

### upgrade.php
- **Money:** Read-only (`:4`).
- **D14 (check, low). Listing plan status may be readable by anyone.** `upgrade.php:13` accepts any `listing` id from anyone, signed out included. Whether `Upgrades::offer` reveals a listing's plan or features (`{have}`) to a non-owner is not shown.
  - Breaks: decisions/0154 §5.
  - Smallest fix: pass `listing` only when the caller manages it; otherwise use 0.

### upload-act.php
- **Access:** Auth is checked before the method check and CSRF (`:17-22`), and CSRF is present.
- **D15 (low, check). A second, broader staff path that hides its failures.** `upload-act.php:19` adds a fallback that admits any `regional_admin`. That is wider than the comment's "as the admin's own endpoint" (`:3`, `:18`). It also swallows every exception silently. Per-action permission is said to be in `Workflow::move`, which is not shown.
  - Breaks: decisions/0176 §6.
  - Smallest fix: confirm that `admin/api.php` admits `regional_admin` the same way; if not, remove line 19.

### upload.php
- **D16 (med, conditional). CSRF passes when the session has no token.** `upload.php:38` runs `hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))`. If a signed-in session has no token, `'' === ''` passes.
  - `do=piece` takes a normal form POST (`:28-29`), so a cross-site form could reach it with no header.
  - Whether sign-in always sets `csrf_token` is not shown.
  - Breaks: the file's rule at `:11`, "Signed in with the page's X-CSRF-Token."
  - Smallest fix: add `($_SESSION['csrf_token'] ?? '') === '' ||` to the refusal condition.
- **D17 (low). Internal details can reach the client.** `upload.php:52-54` echoes `RuntimeException`, including `PDOException`, messages to the client. `:24` also reveals the internal file name and line number.
  - Breaks: not shown.
  - Smallest fix: log the detail and return a generic message for `PDOException`. Drop the file and line from `:24`.
- **Guest path:** Guest targets bypass both sign-in and CSRF (`:35-36`). The file relies on "the target checks its own key (and the token is signed)." `Uploads::targetOf` and the signature check are not shown, and should be confirmed.

### vault-ext.php
- **Access:** The real gate is the `X-Vault-Token` check in `Vault::extAuth` (`:24`). The Origin check (`:17`) stops web pages, but a non-browser client can set any Origin, so it adds nothing against a stolen token. That is acceptable as long as the token is strong (not shown).
- **D18 (check, low). No visible limit on token guessing.** Nothing in this file throttles failed tokens; any limit inside `extAuth` is not shown. Every validation error also returns 401 (`:35`).
  - Breaks: decisions/0156 §8.
  - Smallest fix: add a per-IP failure counter in front of `extAuth`.
- **Private data:** The email is returned only to the token holder (`:28`). Fine.

### vault-lock.php
- **D19 (low). A GET request changes state.** `vault-lock.php:9` locks the vault on a GET request. Email link scanners and "safe link" prefetchers follow GET links automatically, so a vault can be locked without anyone clicking. The impact is availability only, because the endpoint can only lock (`:3`).
  - Breaks: decisions/0156 §4.
  - Smallest fix: have GET show a "Lock now" button that POSTs the same `t`.

### vcard.php
- **D20 (low). Save counts can be inflated.** `vcard.php:17` counts a "save" on every non-bot GET, with no per-visitor dedupe or rate limit, so anyone can inflate a listing's save count. Whether counts affect billing or ranking is not shown.
  - Breaks: decisions/0094.
  - Smallest fix: dedupe per device or IP per listing per day.
- **Private data:** Shelter addresses are handled inside `VCard::listingCard` (not shown).

### video/_auth.php, video/jobs.php, video/results.php
- **Access:** Token-based. Fails closed when the token is unset (`_auth.php:13`) and uses `hash_equals` (`:15`). `results.php:7` repeats the check, which is harmless.
- **D21 (low, check). The lease limit is not clamped here.** `jobs.php:11` passes `limit` unclamped to `VideoJobs::lease`. Whether `lease` clamps it is not shown.
  - Smallest fix: `max(1, min(10, ...))`.
- **No other defect found.**

### wire-card.php
- **Money:** Shows the company's wire details.
- **D22 (med, check). The "entered the code" step is not checked in this file.** The header says the card is shown only "after they've entered the code we emailed them" (`wire-card.php:3`). Line 12 checks only the order token, `pending` status, `pay_method` and `ClientOrders::wireLeft($o)`; it never refers to the session.
  - If `wireLeft` tracks the code entry on the order rather than on the browser that entered it, anyone holding the order link (a forwarded email, a shared device) can fetch the wire details during that window. That is not shown.
  - Breaks: decisions/0101, addendum 2026-10-28.
  - Smallest fix: when the code is accepted, store the order id in `$_SESSION`, and require it here.

---

## Parts with nothing found
- **Charges, refunds and credits:** None of the shown files performs one. `upgrade.php` is read-only, and `wire-card.php` only displays details.
- **Command or path injection:** None found in the shown files.
- **SQL injection:** None found, apart from the unconfirmed `PlaceScope` fragment (D9).

---

## Summary table

| file:line | defect | rule § | severity | fix |
|---|---|---|---|---|
| short.php:14-16 | Rate limit lives in the session; a cookieless script bypasses it | decisions/0094 | low | Also limit per IP |
| spell.php:11 | No length cap on `t` | not shown | low | `mb_substr($t, 0, 500)` |
| stg-bar.php:16-18,29 | Empty `RELEASE_BAR_KEY` not refused; empty HMAC key is forgeable (if `verify` doesn't refuse) | decisions/0145 Ph.5 | med (conditional) | 503 if `$key === ''` |
| stg-bar.php:31-32 | Upstream cookie value may carry CR/LF into the request header | not shown | low | Restrict to `[A-Za-z0-9._-]` |
| stg-csp.php:8-16 | Unauthenticated, unlimited writes to Held; raw `$blocked` stored | decisions/0165 §1 | low | Check the report's host; rate cap |
| stg-probe.php:12,31-34,38-39 | Reveals path, usernames, PHP version and password length despite "never a value" | file :5; decisions/0159 | low | Basename, booleans, no lengths |
| stock.php:31 | `PDOException` message echoed | not shown | low | Catch `PDOException` and return a generic error |
| stock.php:30,115 | Unlimited external source discover/adopt per member | decisions/0143 | low | Per-user hourly cap |
| suggest.php:75,96 | Raw `PlaceScope` SQL fragment built from `$_GET` (unconfirmed) | not shown | check (high if unsafe) | Confirm int-only, or return args |
| suggest.php:276 | City/state shown for every merchant; private-