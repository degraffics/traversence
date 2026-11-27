# The Jan 1 launch plan: front end and back end ready for the market roll-out, 200 miles around Four Corners

The CEO, for Jason (2026-11-26). Jason asked: "Work with your team to analyze the entire platform and let me see your best
plan for development based on both the frontend and back end being ready by Jan 1 for market roll out."

**Status:** Briefs A and B approved by Jason (2026-11-27, in chat: "A and b is good, expand to include surrounding 200
miles from the four corners"). B1 is widened from 1A's seven towns to every town within 200 miles of Four Corners
(§2a). Briefs C and D are still to come.

**Built on:**
- the live mirror at Sync 2026-11-26 21:32 UTC (`45926f0`);
- this repo's `main`;
- the live Work queue export (`site-audit/results/build-requests.json`, 21:17 UTC, 55 items);
- the daily checks (`site-audit/paths.md` 09:50 UTC, `rendered.md`, `results/load.json`, `jobs.json`, `alarms.json`).

**How it was made:** five read-only reviews (visitor front end, Shop and money, businesses and 1A's data, content and
search, back end and delivery), each citing file:line and decisions. Their marks are kept:
- **V** means verified in a file or result;
- **I** means inferred and still to be proved on staging.

**Not seen:** the live `.env` (which payment, mail and AI keys are set), the live `.htaccess`, and the live database.

**Reviewed by the session manager** (2026-11-26, 22:56 UTC): clear to ask, with its eight fixes made.

---

## 1. The short answer

Jan 1 is reachable for **a working 1A launch**, but not for everything in the queue, and only if four things happen
this week:
1. **Unblock the release pipeline.** Four releases in a row (#305–#308) were withdrawn on false blocks. Measured
   (the session manager's release records, Nov 20–26): about 10 a day sustained with the check off. With it on, a
   release takes 63–80 minutes, so under 10 a week while the false blocks last, and 4–6 a day once they're fixed.
2. **Close two safety holes** before anyone outside the company uses the site:
   - staff access defaults to "allowed" when no staff member is signed in;
   - there are no database backups.
3. **Fill 1A with real data and guides.** Today a guest finds nothing to eat near Show Low. Five of the seven 1A towns
   have never been built, and no live guide is in 1A.
4. **Decide what "launch" means.** No decision records the launch date, its pass/fail gates, the search-engine lock or
   how it lifts.

**The rest of the queue waits until after launch:** most of the control board (about 40 of the 55 items), the App
builder and App store, and orders M5–M7.

## 2a. The launch area: 200 miles around Four Corners (Jason, 2026-11-27)

Measured from the site's own ZIP data (`sync-app/data/US.txt`) around the Four Corners Monument (36.999, -109.045):
**419 ZIP codes and 340 towns**: New Mexico 126, Colorado 106, Arizona 60, Utah 48. By distance, 24 towns are within
50 mi, 74 within 100 mi and 156 within 150 mi.

**regions.md's micro-clusters inside the circle** (their estimated businesses), about 6,100 businesses against 1A's 600:
- **Wholly or almost wholly inside:**
  - 1A (~600): Eagar sits right on the line at 200 mi, so all of 1A is kept;
  - 1B (~340), 1C (~500), 2A (~450), 3A (~600; Las Vegas NM at 234 and Pecos at 212 lie outside);
  - 4A (~750), 4B (~500), 7A (~560; Williams at 201), 7B (~350), 11A (~450), 11B (~300), 12B (~250).
- **Partly inside:** 12C (Kanab, Panguitch, Tropic, Escalante, Boulder in; Springdale at 219 out) and 4C (Los Lunas at
  201, Belen at 209: on the edge).
- **Outside:** 6B (Sedona, about 213), 12A (Payson, about 230), 14C, 15A, 8A, 13B.

**What changes in the plan:**
- **Data (D.1) covers all 340 towns,** from the free bulk sources first: Overture, our OpenStreetMap copy and NPI
  (rural test §8.9). Google's paid check stays on 1A only unless Jason funds more ("No income, no expense"). At about
  $0.03 a check, the whole area would cost about $180.
- **Guides (E.1)** go in rings: the Ancient Borderlands zone and one guide per micro-cluster inside the circle (about 13),
  then 1A's towns, then other towns as the cap allows. 340 town guides won't fit December's AI cap.
- **Selling stays on 1A first:** Jason's "penetrate 1a properly". The wider area is what a visitor sees.
- **Load:** about 10 times 1A's records, into the same MySQL that took 1.78M index rows in a day (`consistency.json`).
  B.6's capacity test and pacing the loads matter more now.
- **Nations' land:** the circle takes in the Navajo Nation, Hopi, Ute Mountain Ute, Southern Ute, Jicarilla Apache,
  Zuni, and many Pueblos along the Rio Grande. E.2's 0066 audit covers every one, and nothing is shown that 0066 holds.

## 2. Where we stand

| Area | Verdict | Why (evidence) |
|---|---|---|
| Arriving and search | Partial | A place taken from the visitor's device is always marked "outside our areas" (`UniversalSearch.php:1036`) **V**. That switches off "did you mean" and says "We have no listings in Show Low, AZ yet" when we do have them **V**. The guest "eat near me, open now" path failed **V**. |
| Exploring (hub, directory, Get Local) | Partial | "Set my place and distance" failed 7 of 7 runs: a town gives an area with no Within (`js/location-scope.js:153`; P9, decided "fix" under 0053, not built) **V**. Pages jump on load (0.35–0.67; 0.212 on 8 shared pages) **V**. Get Local's filter times out at ~9,500 rows near St. Johns **V**. |
| Reading a page | Ready outside 1A | The 4 guides walked pass 6 of 6 stops. None is in 1A **V**. |
| Acting (call, directions, open now, trips) | Partial | Call and directions work. "Open now" is never marked (P10, held for the crawler revamp, Jason 2026-11-13) **V**. Routes on our site wait for two hosts (0174 R5) **V**. |
| Joining (sign up, sign in, claim) | Not measured | The path checker is out of date since the phone menu release (#253), and its staff two-step fails. The site's own link exists (`header.php:450`) **V**/**I**. |
| Member's space, comments | Ready | Sign out, comment, message, edit listing and post all pass **V**. |
| Community groups | Broken (data) | No groups are listed, so "Join a group" stops **V**. |
| Phones at 390px | Ready (public) | The 14 misses are admin pages **V**. |
| Accessibility | Partial | Contrast fails on 59 of 124 public views (`#8A7355` meta text, the `#B45309` "›"). Focus is invisible in "Town or ZIP" (`location-scope.js:249`) **V**. |
| Search-engine lock | Partial, leaks | Pages drawn by `tv_page_start` send noindex (`page-shell.php:51`, fixed, no switch). The home page, `login.php`, `register.php`, `forgot-password.php` and `directory/classic.php` don't, so they can be indexed today. Storefronts send `X-Robots-Tag: all` (`storefront-view.php:106`). robots.txt, the sitemap and the FAQ (`faq.md:983`, "Yes") all say the site is open **V**. |
| 1A data | Partial | St. Johns: 182 listings, 1 verified, its town finds sitting in Review (`towns.md`). Springerville and Eagar: we list 47 of the 134 businesses the rural test found (rural test §8.3). Show Low: searches by kind work, but "show low" alone gives 0 listings. Holbrook, Winslow and the Petrified Forest gateway: no record at all **V**. Hours come from OpenStreetMap for only 71 listings **V**. |
| Data upkeep | Partial | 31,057 finds staged (700 ready but stuck, 2,107 with no kind). Two W1 load trips open since 11-21 **V**. |
| 1A content | Not built | 6 live guides, none in 1A, and 116 drafts waiting. The St. Johns draft (0131:102) isn't live **V**. Guides publish by themselves at score 70 (`Guides.php:1346`) **V**. |
| 0066 for 1A | Partial | The rules are coded (`Landmarks.php:70–127`). Arizona is opened by a constant (`Jurisdictions.php:35`), but 0164 R8 wants an attorney's sign-off, and whether it happened isn't recorded **V**. Nearby: Fort Apache, Navajo, Hopi, Zuni, Petrified Forest, Homolovi **I**. |
| Business owner's path | Mostly built | Claim, edit, Market 360 zips 1–6, GO Codes and storefronts are built **V**. Verification is one person's phone or an admin's follow-up: no text provider, and the verify number is Jason's own line (`Claims.php:26`) **V**. |
| Field sales tools | Partial | CRM, Prospects, Territories, Promotions and claim invites are built. Quotes and deals, the White Glove board and a sales kit aren't (`manifests.php:101–108`) **V**. |
| Our Shop | Partial | Plans, packs, GO Codes and domains are in one cart (`shop/index.php:60–94`). Print and services are a quote by email (`shop/index.php:68`). The payments checklist says "not run yet" **V**. |
| Client orders (staff-built) | Close to ready | 30 of 31 survey cases work after #267/#270. M1, M1b and M2 are live. But the order tests are red against the harness (queue #47) **V**. |
| Marketplace checkout | Partial | Built at 0% commission with the Seller Agreement (`MarketPay.php`, `MarketBiz.php:250`). Live keys likely not set ("opens soon", `MarketPay.php:8`) **I**. |
| Plans and renewals | Partial | `Billing.php` matches commercial §1.1. Its daily job missed 11-26 (#57; fix #309 live) **V**. The QR plan and the Maps add-on can't be bought **V**. |
| Taking real money | Not proved | QuickBooks production was connected on 10-16 (0098). The real $4.95 purchase and refund was never recorded **V**. Sales tax on plans is not set (`QuickBooksPay.php:1218`) **V**. The legal texts are drafts for the attorney (`legal-seed.php:5`) **V**. |
| Server under traffic | Unknown | 14 of 25 connections were in use with no visitors. 55,683 aborted connects. No test of many visitors at once **V**. |
| Scheduled jobs | Partial | :17 and :00 crowding. The +7 offset (#309) moves only fixed-minute jobs (`Scheduler.php` stagingTime) **V**. |
| Backups and restore | Not built | 0163 line 460: hosting has "no backup". Nightly backups are only planned (0163:489). No restore has been tested **V**. |
| Security | **Partial: one hole** | `Access::who()` treats "no staff session" (and a database error) as the Founder (`Access.php:26,34`) **V**. Uploads ask only for a member sign-in (`api/upload.php:37`), and the order proof and file targets check with `Access::can()` (`upload-targets.php:17`). So a member could likely attach files to anyone's open order **I**. |
| Mail | Partial | DMARC `p=none`. DKIM unchecked (`mail-dns.md`). SES was in mock until production access (`session-manager.md:425`) **V**. |
| Release pipeline | **Bottleneck** | With the check on, 63–80 minutes per release. 4 of 5 checked on 11-26 were false blocks. `releases_tick` runs every 24 minutes, though the Piece 3 design says 10 (`jobs.json`; `piece3-check.md:176`) **V**. |

## 3. What "ready on Jan 1" means (for Jason to decide)

No decision defines it (gap). I propose these launch gates. Each is measured, never judged:
1. **Every core path passes** in `site-audit/paths.md`, as guest and member, at 390px, for 5 runs in a row. That
   covers search, explore, read, act, sign up, sign in, claim, edit, post and message (0145).
2. **New paths pass:**
   - one per 1A town: eat, stay, see, read its guide;
   - one each for the Shop, plan-buy and Marketplace-buy, in the sandbox, never real money (CLAUDE.md "No test moves
     real money").
3. **Data:**
   - every town within 200 miles of Four Corners built (§2a);
   - a guide for each micro-cluster inside the circle and for each 1A town, each passed by 0164's checks and a person's read;
   - no "no listings" note where we have listings.
4. **Safety:** the access hole closed; nightly backups running, with one restore drill done; the scheduled jobs on time
   for 7 days.
5. **Money:** the payments checklist passed in the sandbox, and one real $4.95 purchase and refund done by Jason (0096,
   0098; the hard money line, 0180 §4.1).
6. **Search:** the per-page noindex holds live, then the lock lifted on Jason's date.

**Code freeze Dec 22.** From Dec 22 to 31, only fixes for failing gates. Then the go/no-go on Dec 31, against the gates.

## 4. The work, in six streams

Each item gives its source, size (S = part of a release, M = one release, L = several), and whether it's the Founder's
call.

### A. Unblock the pipeline (first, this week)
1. **False blocks from the before-and-after check** (#58; #52–#55 are its duplicates; 0179's note of 11-26 18:30).
   Already the ticket R1 seeded. M.
2. **`releases_tick` every 10 minutes**, as Piece 3 designed (`piece3-check.md:176`). It carries out the design, so
   it's no change. It goes in the Scheduler bundle with B.3, not alone.
3. R2 (0180) once the check is back on. R3–R5 follow, but at most one slot a day for them until the launch gates pass
   (see §6, decision 3).

### B. Safety (backend, launch-critical)
1. **The access hole.** `Access::who()` and `can()` deny with no staff session or a database error. Scheduled jobs use
   `canAs`. Uploads to `order_proof`/`order_file` check the order's owner. It's the queue's #51, widened.
   - Decisions 0109, 0127. Privacy territory, so it needs a Tier 4 review (0180 §4.5).
   - **Done:** live as Release #314 (2026-11-26, 23:00 UTC), synced and verified. Proved first on the live code
     (`tools/access-tests/who.php`, 12/12). It also closed `/api/stock.php`'s order files and the crawler send for
     members without a staff login.
2. **Nightly database backups and one restore drill** (queue #32; 0163:489). M.
   - **Founder:** confirm what Bluehost's plan already backs up, since 0163 line 460 and `session-manager.md:651`
     conflict.
3. **Jobs spread across the hour,** with billing off 6:13 (#13, #45, #57; 0113, 0165 §1). Every-N-minutes jobs included,
   staging's too. M. Money territory, so a Tier 4 review.
4. **The migration runner's data-migration fix** (#42; 0162's note). S.
5. **Mail:** confirm `SES_MODE`, verify DKIM on both domains, then move DMARC to quarantine (0137 §8, 0172). S.
   - **Founder:** SES production access at https://console.aws.amazon.com/ses/ (Account dashboard → Request production
     access).
6. **Capacity:** one test of many visitors at once on the key public pages, on staging, and W0's loading paced in busy
   hours. Retry a 503 batch (#10). M.
   - Gap: no capacity target exists. *Suggestion for the gap, not a decision:* 50 visitors at once, with median
     time to first byte under 500 ms.
7. **Alarm truth:** the receipt counts (#11), the stale W1 trips, and the health snapshot missing on 11-26. S.
8. **The two admin pages timing out** (#56). S–M.

### C. Visitor front end (needs a front-end build session; agents are fenced to the backend)
1. **The device's place read as "outside"** (`UniversalSearch.php:1036`, against :934's cluster check). It's likely
   behind the Show Low dead end. CLAUDE.md "Places in search", 0060. S.
2. **Repair the path checker:** the sign-in selector (`paths.mjs:310`) and the staff two-step. Without it, joining and
   claiming can't be called ready. 0145. S.
3. **New paths in `paths.mjs`:** each 1A town (from regions.md), plus the Shop, plan-buy and Marketplace-buy in the
   sandbox. 0145 §2. M.
4. **A town as a point, with Within 10 mi** (P9, 0053). M.
5. **Layout shift:** find the shared 0.212, then fix the directory, hub, login and /trip/. 0145 V06. M.
6. **Contrast and focus** (`#8A7355`, `location-scope.js:249`; brand.md §4). S.
7. **Get Local's filter at around 10,000 rows** (0058 note 2026-11-19). M.
8. **Open now, marked and filtered** (P10, 0060). M. **Founder:** reopen P10 ahead of the crawler revamp?
9. **The scorecard:** a "no listings" note for a town that has listings fails the check. S.

### D. 1A's data
1. **Build every town within 200 miles of Four Corners** (§2a: 340 towns; approved, Brief B1). Use rural test §8.9's
   order: Overture and OpenStreetMap, then NPI, then Google's check on 1A only. 0149 (as changed by B1), 0164. L.
   The towns go in rings: 1A first, then nearest-out, paced so the server stays inside its limits.
   - Depends on the W1 trips being cleared (#10, #11).
   - **Approved** (Brief B1, 2026-11-27): towns are built ahead of demand, within 200 miles of Four Corners. It's
     recorded as a note on 0149.
2. **Clear the staged backlog and Review for 1A,** run the identity check on 1A's duplicates, and fix misfiled towns
   (0164 §3, R12; `handoffs/duplicates.md`). M.
3. **1A's towns under their real area** (`cluster-auto-show-low` against regions.md's 1A; Greer in
   `cluster-1b-round-valley` against regions.md's 1B). It's W15, 0170. M.
   - **Founder:** he's holding W15 for discussion (`handoffs/business-listings.md` §3).
4. **Hours:** owners add their hours at claim, the one fix the decisions allow (0164's Google rule). S, plus field work.
5. **Community:** one starter group per 1A town (0047, 0136, 0137). S, as content.
   - **Founder:** who starts them?

### E. Content
1. **Guides in rings:** the Ancient Borderlands zone, one per micro-cluster inside the circle (about 13), then each 1A
   town, then more as the cap allows. Read Admin → Guides (live read) to see why the 116
   drafts wait, then queue 1A. 0131, 0147, 0164. L.
   - **Founder:**
     - December's AI cap: $40 a month is shared with everything, and it was over 80% this month;
     - *Suggest a change to 0131: 1A's first guides are published by a person, not by score* (it conflicts with
       0131:102's St. Johns rule).
2. **0066 and Arizona's record:** confirm the attorney's sign-off (0164 R8) and audit 1A's held and story-only names.
   S.
   - **Founder and attorney.** It conflicts with `Jurisdictions::OPENED` (0048).
3. **FAQ truth:**
   - correct `faq.md:983`;
   - add an entry on the lock;
   - bring this repo's `faq.md` in step with the live `app/ui/faq.md`, a gap the README's claim hides;
   - delete `faq.md.orig`.

   0057. S.

### F. Shop and money
1. **Order tests green** (#47, M3's first fix). Nothing more on orders ships before it. M.
2. **The payments checklist on the test accounts,** sandbox only (`site-audit/payments-checklist.md`). S.
3. **Plans and the Shop buyable end to end.** The QR plan and the Maps add-on are priced (commercial §1.3, §2) but
   can't be bought. M.
   - **Founder:** which sell on Jan 1?
4. **Marketplace payments live:** Stripe platform, Square app, `MARKET_PAY_ENV=live` (`MarketPay.php:8–17`). S to set,
   M to prove.
   - **Founder:** the keys and the platform accounts, entered by him in `.env`, never by an agent.
5. **Real money once:** a $4.95 purchase and refund in production. S. **Founder's press** (0180 §4.1).
6. **Sales tax:** Automated Sales Tax in QuickBooks, plan taxability, Arizona TPT. S. **Founder and accountant.**
7. **Legal texts:** the attorney's review, then publish the Terms and Seller Agreement (0142; `legal-seed.php:5`). S.
   **Founder and attorney.**
8. **Print at launch:** keep quote by email, or build self-serve (M4 plus the order model's 1.1–1.3, L).
   **Founder.** My recommendation is quote by email for Jan 1.
9. **Order #12's books,** and M2b (paused by him). **Founder.**

### G. Search (the lock)
1. **Close today's leaks:** noindex on the home page, sign-in, sign-up, reset, the classic directory and storefronts,
   while the lock is on. S. **Approved** (Brief B2, 2026-11-27).
2. **Per-page holds:** trial and unverified listings (0034, 0070, 0071), first-look guides (0147), drafts, story-only
   places, user posts and search results. Then `page-shell.php:51` prints noindex only for those and for staging. M.
3. **Sitemap and tags:**
   - add guides, journeys and public landmarks, and drop noindex listings;
   - add canonical and Open Graph tags and real descriptions to hub, landmark, journey and experience pages (landmarks
     today say "Traversence placeholder page.", `page-shell.php:45`).

   M.
4. **Lift the lock:** one release removes the fixed noindex, with the FAQ. Then check on live that a guide is
   indexable and a trial listing isn't. Then Search Console and the sitemap.
   - **Founder:** the date and the Search Console account. No decision governs the lock today, so this is a new one.

### H. Going to market in 1A (business readiness)
1. **Verification that doesn't run through one personal phone,** and an in-person path for a rep. 0070, 0108. S–M.
   - **Founder:** *Suggest a change to 0070: a rep's in-person visit counts as verification, with evidence.* Also the
     verify number, and whether to pay for a text provider.
2. **The 1A territory:** a rep, prospects seeded from 1A listings, and GO Code promo codes (0108 "Promotions",
   "Territories"). S, as setup.
   - **Founder:** launch promos (30, 60 or 90 days free), and any printed outreach (spend).
3. **The revenue model** brought to the five tiers (commercial §4/§6, out of date since 10-15). S, as a document.
   **Founder:** the target mix.

## 5. The schedule

At 20–30 releases a week once A.1 is fixed (the back-end review's measure; the session manager plans to about 10 a
day), the four weeks hold about 80–110 releases. The plan below needs about 60, which leaves room for rework.

| Week | Focus | Gate at the end |
|---|---|---|
| **Nov 27–30** | A.1, A.2. B.1 proved on staging, then fixed. G.1 the leaks closed (once Brief B rules). C.1, C.2. Briefs A and B ruled. | The pipeline passes a clean release with the check on. Joining is measured again. |
| **Dec 1–7** | R2. B.2 backups, B.3 jobs, B.5 mail. D.1 and D.2 the seven towns built and cleaned. C.3 the new paths. F.1 the order tests. E.2 the Arizona sign-off. | Every 1A town has data. The new paths run, failing honestly. |
| **Dec 8–14** | C.4–C.7 front-end fixes. E.1 the 1A guides. F.2–F.4 the Shop and Marketplace in the sandbox. G.2 the per-page holds. D.3 1A's area (if W15 is cleared). B.6 the capacity test. R3. | The core and 1A paths pass. Sandbox buying passes. |
| **Dec 15–21** | G.3 sitemap and tags. F.5 real money once (Jason). F.6–F.7 tax and legal. H.1–H.2 the rep's tools and territory. C.8 open now (if reopened). The FAQ. R4. | All six launch gates green for 5 runs. |
| **Dec 22–31** | **Freeze.** Fixes for failing gates only. G.4 the lock lifted on Jason's date. R5 only if the gates hold. Go/no-go on Dec 31. | Launch. |

**Who builds** (CLAUDE.md: one session per area, at most about three build sessions; three is at the cap, and each sends its own release in turn, in the session manager's order):
1. **Piece 2 build session:** stream A, stream B and R2–R5 (backend).
2. **A new Frontend & Shop session:** streams C, F (code) and G. The Design reviewer approves every visible release.
3. **A new Data & Content session:** streams D and E, on the crawler and guides (0164's rules).

The session manager keeps the one queue. I report to Jason against the gates.

## 6. After launch (moved down, nothing deleted)

Work queue ids:
- the control board: 3–9, 12, 22, 44, 46;
- the backlog: 25–31, 33–41, 43;
- orders: M5–M7 (17–19), 20, 21, 50, and M3 Money (15) unless staff collect many orders at once;
- courier booking;
- Market 360 zip 7;
- Quotes and deals;
- the White Glove board (unless Strategic or Cornerstone is sold in January);
- W14 licence lookups;
- routes on our site (0174 R5, unless Jason allows its two hosts);
- 1B and 1C.

## 7. The Founder's calls, as four Decision Briefs (0180 §1's form)

Only true Founder rulings (0180 §3). Calls the team can settle (1A's community groups, sequencing, the session count)
are settled by the team. Each brief opens "Decision (nothing changes on the site yet)".

**Brief A, approved 2026-11-27: the launch and the pipeline.** Triggers: new policy (no decision defines a launch), a change to
0180's "R1–R5 back to back" note, a change to 0165, and the before-and-after switch (his press).
1. The launch definition: §3's six gates, the Dec 22 freeze and the Dec 31 go/no-go.
2. Launch work first in the one queue. R2–R5 get at most one slot a day until the gates pass.
3. *Suggest a change to 0165:* pause staging's own jobs and catch-up while a before or after look runs.
4. Turn the before-and-after check back **On** (System Configuration → Staging), so A.1 can be proved on R2.

Blocking only on a page's own files is partly done by the role-22 fix (#1472). It becomes a brief only if R2 still
false-blocks.

**Brief B, approved 2026-11-27, with B1 widened to 200 miles around Four Corners: the area and the lock.** Triggers: a change to 0149, and new policy (no decision governs the lock).
1. *Suggest a change to 0149:* build all seven 1A towns before launch, not only on demand (D.1).
2. The lock covers the home page, sign-in pages, the classic directory and storefronts until he lifts it (G.1).

**Brief C, by Dec 7: safety, content and data.**
1. Bluehost's own backups (B.2).
2. SES production access (B.5).
3. December's AI cap for the 1A guides, and *suggest a change to 0131:* 1A's first guides are published by a person
   (E.1).
4. The Arizona attorney sign-off (E.2).
5. W15 and 1A's area (D.3).
6. Reopen P10, open now (C.8).

**Brief D, by Dec 14–21: money and going to market.**
1. Which priced items sell on Jan 1 (F.3).
2. Marketplace keys (F.4).
3. Print at launch (F.8).
4. Order #12 and M2b (F.9).
5. Verification: the verify number, and *suggest a change to 0070:* a rep's in-person visit counts (H.1).
6. Real money once (F.5).
7. Tax (F.6).
8. Legal texts (F.7).
9. Promos and outreach spend (H.2).
10. The revenue mix (H.3).
11. The lock's lift date and Search Console (G.4).
12. Hosting, after B.6's test.
13. `GITHUB_SYNC_TOKEN`, which expires Dec 16.

## 8. Risks

- **The 200-mile area is ten times 1A.** If the loads outrun the server or the month, the rings stop where they've
  reached: 1A and the nearest clusters first, the rest after launch, and Jason is told.
- **The before-and-after check is Off right now, and R2 is held on it.** Unless Jason presses On (Brief A.4),
  A.1 can't be proved and R2 can't ship.
- **The pipeline stays blocked.** Then the plan halves. A.1 is the first thing done, and if it isn't fixed by Nov 30, I
  bring Jason the speed-ups as one Decision Brief.
- **The access hole is real.** If staging proves it, it ships at once on the urgent path, ahead of everything.
- **1A's data is thinner than the records show.** Holbrook, Winslow and the gateway have no record at all. D.1's first
  run tells us by Dec 7.
- **The AI cap.** The 1A guides may not fit in $40, shared with everything. E.1 needs Jason's cap call early.
- **The server under real traffic.** It's unmeasured. B.6's test decides whether the hosting question becomes urgent.
- **The holiday.** Jason's presses (money, legal, tax, the lock) cluster in late December. Brief D asks for them early
  on purpose.
