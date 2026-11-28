# Security review of public API endpoints, part 1 (draft for human review)

**Basis of the "rule §" column.** No decision documents or CLAUDE.md were attached. Where a finding cites a rule, it is the decision or rule named in the code's own comments. The decision text itself is not shown, so each citation should be checked against the real document. Library code (`app/lib/*`) is also not shown. When a fix may already exist inside a library call, the finding says so and is marked "verify".

## Findings

### 1. The map view sends the exact point of confidential (private-address) listings to anyone — **High**
- **Where:** `api/search_listings.php:408-424`.
- **What happens:** The card view hides the address and point of confidential listings: the address at `:464`, and lat/lon set to null at `:495` ("phone only (decisions/0044)"). The map view (`view=map`) does neither:
  - `:410` selects `x.lat, x.lon, x.phone` for every match.
  - `:421` returns them with no `ListingSafety::isConfidential` check.
- **Result:** A shelter's exact location is one public GET away.
- **Rule:** decisions/0044 (cited at `:464`, `:486`). Private data: addresses of private places.
- **Smallest fix:** Add `x.category_id` to the `:410` SELECT. In the `:418` loop, skip the row, or leave out lat/lon, when `ListingSafety::isConfidential((int)$r['category_id'], $r['business_name'])` is true.
- **Related, verify:** `api/search.php:56-58` also returns pins with lat/lon from `SearchIndex::rows`. Whether that index excludes confidential listings is not shown.

### 2. `locate.php` returns a fresh exact point for any listing id, signed out, with no CSRF — **High (verify)**
- **Where:** `api/locate.php:10-17`.
- **What happens:**
  - Any POST `id=` runs `Geocoder::locateOne` and returns `lat, lon`.
  - Nothing here checks that the listing is public, not suppressed, or not confidential.
  - The 20-an-hour limit (`:13-14`) lives in the session, so dropping the cookie resets it. That leaves unlimited outside geocoder calls.
- **Rule:** decisions/0053 ("confidential listings are never geocoded", per `api/crawl/geocode.php:5`) and decisions/0044.
- **Smallest fix:** Before `locateOne`, look up the entity and return `{ok:false}` unless it is a public, non-suppressed merchant and `!ListingSafety::isConfidential(...)`. Also add `Response::requireCsrf()`. Whether `locateOne` already refuses these listings is not shown.

### 3. `pin.php` GET returns a listing's location for any id, to anyone — **High (verify)**
- **Where:** `api/pin.php:18-22`.
- **What happens:** `ListingLocation::get($pdo, $id)` is returned whole to every caller, including visitors. Whether it leaves out the street and point of confidential or suppressed listings is not shown.
- **Rule:** decisions/0044, decisions/0058 §15.
- **Smallest fix:** When `role === 'visitor'` and the listing is confidential or suppressed, return 404 or a stripped record.

### 4. `domains.php` `wallet_replace` has no wallet permission check — **High/Med (verify)**
- **Where:** `api/domains.php:136-140`.
- **What happens:**
  - `wallet_add`, `wallet_default` and `wallet_remove` each check `Wallet::canManage` first (`:125`, `:129`, `:133`).
  - `wallet_replace` does not. A team member without billing rights, or anyone naming another listing's `id`, can move what renews on which card.
  - Whether `Wallet::replace` checks permission internally is not shown.
- **Rule:** decisions/0166 §6a (wallet) and the file's own rule at `:38` ("Domain and Plan & billing").
- **Smallest fix:** Add the same line as `:133` before `:137`:
  `if (!Wallet::canManage($pdo, $uid, $type, $id)) throw new InvalidArgumentException(...)`.

### 5. `domains.php` `cart_add` takes the price from the browser — **High if trusted (verify)**
- **Where:** `api/domains.php:82`.
- **What happens:** `(int) ($b['price'] ?? 0)` is passed into `AccountDomains::addToCart`. The header (`:7`) says the real total comes from Name.com's quote. If `addToCart` doesn't re-quote, a member sets their own price.
- **Rule:** decisions/0169 §1a. Money.
- **Smallest fix:** Ignore the client price. Call `AccountDomains::quote($pdo, $domain, $years, $type, $id)` server-side and store that, or refuse when the two differ.

### 6. `blocks.php` publish, rollback, schedule and discard check only `pages.edit` — **Med/High (verify)**
- **Where:** `api/blocks.php:25-26` (edit check), `:49-53`.
- **What happens:**
  - The header (`:2-3`) requires `pages.publish` to publish, and Team "Publish" for storefronts (`:11-12`).
  - Only the `page` action (`:55`) checks `pages.publish`. The `publish` (`:49`), `rollback` (`:50`), `schedule` (`:52`) and `unschedule` (`:53`) actions don't.
  - `:28` computes `$pub` but only for display.
  - Whether `Blocks::publish` and the others check permission internally is not shown.
- **Rule:** decisions/0159 §3 and step 5a.
- **Smallest fix:** Before the switch, for `publish|rollback|schedule|unschedule`:
  `[$pub,$w]=Blocks::can('pages.publish',$zone); if(!$pub) Response::error($w,403,'not_allowed');`
- **Also, low:** `:55` calls `Blocks::can('pages.publish')` without `$zone`. Pass `$zone`.

### 7. `search.php?removed=` changes state on