# Review: `workers/crawler/indexer.py` (W0 Indexer) against 0164, 0163 and 0066

*Draft for human review.*

## Scope and what isn't shown

`indexer.py` relies on `crawler.py` for these functions: `fetch_page_meta`, `fetch_page`, `sitemap_urls`, `site_call`, `robots_cache`, `host_last`, the private-address check, the user agent, Crawl-delay handling and Retry-After handling. `crawler.py` is **not shown**. The site side is also **not shown**: `/api/crawl/index.php`, `Frontier.php`, `Sources::machineCall`, how holds are handed out, normalization, and the outside-region filter.

Where this file depends on those parts to keep a rule, the finding says so. It doesn't assume they do or don't keep it.

## Part by part

**Listings (create, change, publish, hide).** Nothing in this file creates, changes, publishes or hides a listing. It only posts found URLs, page answers and site descriptions to the site (lines 136–147, 156). No defect found.

**A person's words and AI (R5, §0.8, R13).** There are no AI calls and no member content. No defect found.

**Page bodies (Decision 1, §1.3 "200").** Only these are sent: a fingerprint (`fp`, line 271), the title (`ti`, line 271), link text cut to 160 characters (line 224) and meta descriptions (lines 305–306). The body is never sent. No defect found.

**Batching (§1.4's note).** These match the note: 250 / 25 / 100 KB / 10 s / 500 ceiling (line 79), halving and doubling (lines 98–102), an hour-clean growth (lines 104–112), and receipts with resend (lines 167–181). There is one stall risk (L8).

**Nations' land (0066, R7, C12).** No nation-specific logic appears here. Keeping a nation's site at "suggested / person's call" depends on what the site hands out (**not shown**). Defects H1, H2 and M2 would also affect a nation's site that blocks us.

**Licence-bound sources (R9, R14).** Restrictions such as cite-only, terms-restricted and Facebook/Instagram are enforced only by the site's hand-out (**not shown**). See L5 and L7.

## Defects

### H1. Lines 269–284: a refused page is still read, and its host is hit again

`why_not_read` returns `"auth"` for 401/403/451 and `"ban"` for 406/418 (lines 234–237). However, line 269 only excludes `parked` and `challenge`. For a refusal page that has a body, the code still does four things:
- it fingerprints the page (line 271);
- it queues every link on it (lines 277–278);
- for a home page, it fetches the sitemap (lines 279–282), which is a new request to a host that just refused us;
- the same applies to `"thin"` pages (Tier 5).

**Breaks:** R18 ("401/403/451 never tried another way"); §1.2 step 5 (a block holds the site for a person); §1.3 status routing; §1.4 (Tier 5 dropped before further work).

**Severity:** high.

**Fix:** at line 269, use `if page and not why and 200 <= row["s"] < 300:`. The sitemap call already sits under that branch, so it inherits the guard.

### H2. Lines 254 and 392–400: no local hold after a block

`holds` is refreshed only at check-in (line 343). If a URL on host X answers 401/403/406/451 or a challenge, the remaining `work` items for X in the same round are still fetched. A round can last about 5 minutes (see M1).

**Breaks:** §1.2 step 5 ("a site that blocks us is never retried by itself… holds the site"); R18; §6.1 (service return).

**Severity:** high.

**Fix:**
- After line 264, add `if why in ("auth","ban","challenge"): holds[host] = why`.
- At line 343, merge holds instead of clearing them, so local holds survive until the site's list includes them.

### M1. Line 393 (and line 50): Pause takes up to about 5 minutes to take effect

The round runs until `round_end + 240`, which is about 300 s after the last check-in. During that time no check-in happens, so W0's switch, Pause everything and new holds are not seen. The comment on line 50 says "a check-in at least this often", but the code doesn't do that.

**Breaks:**
- §1.2 step 1 (switches checked before any GET);
- 0163 §9 ("a Railway run ends at once");
- §1.4's note ("Pause everything stops it").

**Severity:** medium.

**Fix:** change line 393 to `time.monotonic() > round_end`, so the loop checks in every 60 s.

### M2. Lines 288–311: `describe()` skips every pre-fetch guard

`describe()` calls `C.fetch_page(home)` without four guards that `read_one` uses:
- **No hold check** (compare line 254).
- **No `ADMIN_PATH` check** (compare line 257).
- **No status or `why` reported.** A home page answering 401/403/451, a ban or a challenge is never reported as a block, so the site cannot hold it.
- **Links are queued even from a parked domain** (lines 307–310). `read_one` skips these.

**Breaks:** §1.2 steps 1–5; R18.

**Severity:** medium.

**Fix:**
- Return early if `holds` matches the host.
-