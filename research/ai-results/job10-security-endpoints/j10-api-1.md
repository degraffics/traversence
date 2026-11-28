# Security review: public endpoints in `api/`, part 1 (draft for human review)

**Scope.** I read the 66 files attached. Library code (`app/lib/*`, `Response`, `Auth`, `bootstrap.php`) and the rules and decision documents are **not shown**. Where a finding depends on them, I say so.

**Rule citations.** These are the decision sections that the files themselves cite, plus the stated contract in each file's own header comment. I have not seen the decision texts.

---

## Defects

### 1. `api/search_listings.php:407–424`: map view sends the exact location of confidential listings (**high**)
- The card view hides a confidential listing's location:
  - `address` is set to null at :464.
  - `lat`/`lon` are set to null at :495.
  - :486 marks this "phone only (decisions/0044)".
- The `view=map` branch returns `x.lat`, `x.lon`, `x.phone`, `x.city` for every match (:410, :421–422). It never calls `ListingSafety::isConfidential`.
- This branch needs no sign-in. A shelter's exact point is one GET away.
- **Breaks:** decisions/0044 (as cited at :464/:486), and the file's own header :8–16 ("Public-safety rules enforced HERE").
- **Smallest fix:** in the pin loop, `continue` (or null `lat`/`lon`) when `ListingSafety::isConfidential($r['category_id'], $r['business_name'])`. Add `e.category_id` to the pin SELECT; `$buildInner` already selects it.

### 2. `api/search.php:68–71`: staff action taken by GET without CSRF (**med**)
- `?removed=…` calls `SuggestionFlags::remove(…, $canTeach ? Auth::currentUserId() : null)`.
- For staff, this blocks a search suggestion for those words (decisions/0063 §3).
- It is a GET with no token, so any page a signed-in staff member visits can trigger it, e.g. `<img src="/api/search.php?removed=…&q=…">`.
- **Breaks:** the CSRF contract every other write in these files follows ("POSTs need X-CSRF-Token"). The site's CSRF rule document is not shown.
- **Smallest fix:** pass `null` as the user id on this GET path, so it is only ever counted as a visitor's ×. Move the staff block to a POST with `Response::requireCsrf()`.

### 3. Anonymous GETs queue crawler work (**med**, spend)
- These calls run with no sign-in, no CSRF and no rate limit shown:
  - `api/open-places.php:31–38`: `?crawl=1` calls `TownCrawl::fromSearch`.
  - `api/search.php:90–99`: `?lacking`.
  - `api/search.php:111–118`: `?count&r=0`.
- By contrast, `api/send-crawler.php:2,20` limits "Send the crawler" to the Founder ("only staff, only me"). It cites CLAUDE.md "No income, no expense" at :6.
- What `TownCrawl::fromSearch` costs (paid search, fetches) is not shown.
- **Breaks:** decisions/0157 §3 as quoted in send-crawler.php, and "No income, no expense" (if `fromSearch` spends).
- **Smallest fix:** add a per-town daily cap inside `TownCrawl::fromSearch` (one queue per town per day), or a session/IP throttle at these three call sites.

### 4. `api/listing-confirm.php:17–21`: confidence score can be pumped by anyone (**med**)
- Each "Yes" is deduplicated only by `$_SESSION['tv_confirmed']`. A client that drops its cookie gets a new session and a new CSRF token, and can vote again without limit.
- The score orders public search: `search_listings.php:369–373` uses "Best match … how sure we are of a listing".
- Whether `ListingConfidence::yes` caps per-day counts is not shown.
- **Breaks:** decisions/0133, as cited in the header ("this browser says it once a day").
- **Smallest fix:** cap counted yeses per listing+fact+day inside `ListingConfidence::yes` (e.g. 3), or key the dedup on `GuestInquiries::deviceHash()` as `pin.php:34` does.

### 5. `api/market-pay.php:91`: damage amount loses its cents (**med**, money)
- The code is `(int) round(max(0, $dollars)) * 100`. The cast and `round` apply to whole dollars before the ×100.
- So $12.50 becomes 1300¢ and $12.40 becomes 1200¢. The deposit refund is off by up to 50¢ either way.
- **Breaks:** decisions/0135 (refunding a rental deposit "less damage in dollars", header :14