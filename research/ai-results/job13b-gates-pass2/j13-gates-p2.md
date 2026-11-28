# Open items against 0181 §1's six launch gates

This is a draft for human review. The attached documents carry no printed line numbers, so I cite each by document and section. A `file:line` that appears below is quoted from the document, not checked by me.

**Sources:**
- `decisions/0181` (the decision)
- `research/launch-plan-jan1-2026-11-26.md` (the plan)
- `research/listing-audit-2026-11-27.md` (the audit)
- `site-audit/pages.md` (pages)

**"In order"** means each gate's own sequence: dependencies first, then the plan's §5 week. Where a document gives no order, I say so.

## Before the gates

**One discrepancy to flag.** 0181 §1 gate 3 differs from the plan.
- The plan's §3 gate 3 asks for every town within 200 miles built, plus a guide per micro-cluster.
- 0181 §1 gate 3 asks only for "1A's towns built, each with a guide…".
- 0181 is the accepted text, so I use it. The 340-town build and the ring guides are listed only where they block 1A.

**Cross-cutting blockers (every gate):**
1. **The release pipeline (plan §4 A.1, §8).**
   - False blocks are #58, with duplicates #52–#55.
   - Releases take 63–80 minutes each with the check on.
   - Owner: the Piece 2 build session (plan §5).
2. **The before-and-after check is Off (plan §8; Brief A.4).**
   - Only Jason can press it On, and R2 can't ship until he does.
   - Brief A was approved (0181 Status), but whether he pressed the switch is **not shown**.
3. **Two scheduling limits (0181 §1, §2).**
   - The code freeze starts Dec 22. After that, only fixes for failing gates ship.
   - R2–R5 get at most one slot a day.
4. **The Nov 27–30 items.** Whether any of them landed (A.1, A.2, C.1, C.2, G.1) is **not shown**.

## Gate 1: every core path passes as guest and member, at 390px, 5 runs in a row

1. **Repair the path checker** (plan §2 "Joining: Not measured"; §4 C.2).
   - Fix the sign-in selector (`paths.mjs:310`) and the staff two-step.
   - Owner: the Frontend & Shop session.
   - Blocks: without it, joining and claiming "can't be called ready". It depends on the pipeline.
   - Week: Nov 27–30.
2. **The device's place is read as "outside"** (plan §2 "Arriving and search"; §4 C.1; `UniversalSearch.php:1036`).
   - Owner: Frontend & Shop.
   - Week: Nov 27–30.
3. **Search relevance and speed** (0181 note "Search must give superior results"; audit C7, F10).
   - What's needed:
     - the exact kind first;
     - hospitals and ERs labelled;
     - "urgent" no longer read as a time word;
     - an ER-to-hospital synonym;
     - urgent care ranked before clinics;
     - results in about a second on a phone.
   - Owners:
     - relevance: the IT lane with Frontend & Shop;
     - speed: the IT lane;
     - C7: Frontend & Shop.
   - Order: none given. The note calls it "live harm, first within the gates".
4. **The promise check** (0181 note "The promise check, part of gate 1").
   - Every message kind is tested in the path checker.
   - The first fix is the per-message `link`, which is queued.
   - Owners:
     - the CMO (7) for trust and the brand promise;
     - Editorial (12) for the words;
     - the IT Manager's (11) lane for the build;
     - advocates A1–A12 to run it.
   - Blocks: "After Monday's four". What those four are is **not shown**.
5. **The listing-page defects from the audit** (audit F1–F9, Frontend & Shop's part).
   - The defects:
     - F1: a stock photo as a hospital's hero;
     - F2: the kind line hidden under the photo credit;
     - F3: Edit and ⋯ over the title;
     - F4: hours, "24/7" and the server's time zone;
     - F5: Suggest an edit;
     - F6: the claim kind for a hospital, and Claim offered to staff;
     - F7: food starters on every listing;
     - F8: location access;
     - F9: Directions opens a pin.
   - Owner: Frontend & Shop. F1 was ruled by the CMO (7).
   - Blocks:
     - F1 needs a note on 0143.
     - F6 needs a note on 0070, which is Jason's.
     - F4 goes in "search release 2 (slot #9)".
     - Every listing count is pending a live read.
6. **The front-end fixes** (plan §4 C.4–C.7; §2).
   - The fixes:
     - C.4: a town as a point with Within 10 mi. "Set my place" fails 7 of 7 runs.
     - C.5: layout shift.
     - C.6: contrast and focus.
     - C.7: Get Local's filter timing out.
   - Owner: Frontend & Shop.
   - Week: Dec 8–14.
   - Whether contrast and layout shift are pass/fail criteria for gate 1 is **not shown**.
7. **Open now** (0181 note "Open now reopens"; plan §4 C.8).
   - Owner: Frontend & Shop (C.8).
   - Week: Dec 15–21.
   - It overlaps F4's time-zone fix.
8. **The search excellence test per 1A town** (0181 note).
   - A town that fails it fails gate 1.
   - Owners: the CMO (7) and Editorial (12).
   - Blocks: it needs the reference lists from licensed sources and gate 3's health data.
9. **The Hometown theater walk** (0181 note "The machine work that counts").
   - Owners:
     - the CMO (7) owns the touch-point map;
     - Data & Content and Frontend & Shop draw it;
     - the advocates run the walk.
   - Dates:
     - the map is due Dec 3;
     - the walk on staging is about Dec 15–16;
     - its tickets must be fixed before Dec 18.

Not confirmable for gate 1:
- **Community groups.** "Join a group" stops because no groups exist (plan §2; D.5). It isn't among gate 1's named paths, so its link to the gate is **not shown**.

## Gate 2: new paths pass (each 1A town; Shop, plan-buy and Marketplace-buy in the sandbox)

1. **Write the new paths in `paths.mjs`** (plan §4 C.3).
   - One path per 1A town, plus the Shop, plan-buy and Marketplace-buy in the sandbox.
   - Owner: Frontend & Shop.
   - Week: Dec 1–7.
   - Whether it formally depends on C.2 is **not shown**.
2. **Order tests green** (plan §4 F.1; #47).
   - "Nothing more on orders ships before it."
   - Owner: Frontend & Shop.
   - Week: Dec 1–7.
3. **Plans and the Shop buyable end to end** (plan §4 F.3).
   - The QR plan and the Maps add-on can't be bought.
   - Owner: Frontend & Shop.
   - Blocks: Jason decides "which sell on Jan 1" (Brief D, Dec 14–21).
4. **Marketplace payments** (plan §4 F.4).
   - The gate is sandbox-only, but the plan lists live keys as the Founder's.
   - Whether sandbox keys are set is **not shown**. The plan says `.env` wasn't seen.
5. **Is the Shop live?** `pages.md` lists `/shop/` as "Built (channel-shop-earned zip)", which means live only once its release publishes. Whether it's live today is **not shown**. `pages.md` is dated 2026-11-13.
6. **The town paths need gate 3.** "Read its guide" needs each 1A town's guide (see gate 3).
7. **Theater-walk breaks** become gate 2 tickets, fixed before Dec 18 (0181 note).

## Gate 3: 1A's towns built, each with a guide (0164 checks plus a person's read), and no false "no listings"

1. **Clear the W1 load trips** (plan §4 D.1, which "Depends on" #10 and #11; B.6, B.7).
   - Owner: Piece 2. The plan has no explicit owner beyond stream B.
2. **Build 1A's towns** (plan §2 "1A data"; §1; D.1).
   - Five of the seven 1A towns have never been built.
   - Holbrook, Winslow and the Petrified Forest gateway have no record at all.
   - Owner: Data & Content.
   - Blocks:
     - item 1;
     - pacing for server load (§2a);
     - the $0 limit (0181 note E1).
   - Week: Dec 1–7.
3. **Clean the backlog and duplicates** (plan §4 D.2).
   - Clear the backlog and Review, and run the identity check on 1A's duplicates.
   - St. Johns is spelled four ways (audit, counts section).
   - Owner: Data & Content.
4. **W15** (0181 note "W15 is released").
   - It starts after the 1A data work.
   - Owner: Data & Content.
   - Blocks: a Tier 4 review before live.
5. **Health data** (0181 search note; audit C1–C6). Owners: Data & Content, with the IT Manager (11) for the code paths. The fixes run in this order:
   - **C5 before C4**, as the audit states.
   - **C3 and C6:** the kind and the confidence label.
   - **C1 and C2:** About text and hours.
   - Blocks:
     - The full audit is due about Dec 2. The top 50 by taps was skipped.
     - Counts await a live read.
     - C4 needs NPI parent data that `npi_orgs` doesn't keep.
     - ADHS's licensed list is refused by network policy, pending the session manager.
     - A staff re-file can be overwritten until the category-writer rule ships.
6. **Jurisdiction records** (0181 note "Colorado and Utah open on team-reviewed records"; plan E.2).
   - AZ's and NM's drafts must be completed by team review.
   - One release moves `Jurisdictions::regionOpen()`.
   - Owner: Data & Content.
   - Unclear: whether this replaces Brief C's "Arizona attorney sign-off" is **not shown** explicitly.
7. **1A guides at 70 with a person's read** (0181 notes "Guides publish themselves at 70" and "Ground notes").
   - Owner: the Editorial Director's lane (12, 26).
   - Blocks:
     - The $40 AI cap was over 80% this month (plan E.1).
     - The ring order puts 1A's towns third, after the zone and about 13 cluster guides.
     - Ground notes may come after Jan 1.
     - 0131's St. Johns fact-check before live still applies.
8. **No false "no listings"** (plan §4 C.1, C.9; §2).
   - The scorecard check is still needed.
   - "show low" alone returns 0 listings.
   - Owner: Frontend & Shop.
9. **Hours** (plan D.4; audit counts).
   - Only 4 of 852 health listings carry hours. The audit calls it "a fill task".
   - Whether hours are a gate 3 criterion is **not shown**.

## Gate 4: Safety

1. **The access hole: done.** It's live as #314 and verified 12/12 (plan B.1; 0181 §1). Nothing is missing.
2. **Nightly backups and one restore drill** (plan §2; B.2; queue #32).
   - Owner: Piece 2.
   - Blocks: Jason confirms what Bluehost backs up (Brief C, by Dec 7), because 0163:460 and `session-manager.md:651` conflict.
3. **Jobs spread across the hour** (plan B.3: #13, #45, #57), with `releases_tick` every 10 minutes (A.2) bundled in.
   - Owner: Piece 2.
   - Blocks: a Tier 4 review (money territory).
   - Timing: the 7 on-time days must fit before the Dec 31 go/no-go, so this must ship by about Dec 24 at the latest. That date is my arithmetic, not stated in the documents.
4. **Alarm truth** (B.7): the health snapshot was missing on 11-26. It's needed to measure the 7 days. That link to the gate is my inference.

## Gate 5: Money

1. **The payments checklist in the sandbox** (plan §2 "not run yet"; F.2).
   - Owner: Frontend & Shop.
   - Week: Dec 8–14.
   - Whether sandbox keys are set is **not shown**.
2. **One real $4.95 purchase and refund** (plan §2 "never recorded"; F.5; 0180 §4.1).
   - Owner: Jason himself.
   - Blocks: Brief D (Dec 14–21) and the holiday (plan §8).
   - Whether the unset sales tax (F.6) blocks it is **not shown**.

## Gate 6: Search lock

1. **Close the leaks** (plan §2; G.1, approved in B2).
   - Leaking now:
     - the home page;
     - `login.php`, `register.php` and `forgot-password.php`;
     - `directory/classic.php`, which is archived but still reachable (`pages.md`);
     - storefronts, which send `X-Robots-Tag: all`;
     - robots.txt, the sitemap and the FAQ.
   - Owner: Frontend & Shop.
   - Week: Nov 27–30. Whether it's done is **not shown**.
2. **Correct the FAQ** (plan E.3).
   - Fix `faq.md:983`, add an entry on the lock, and sync the repo's FAQ with live.
   - Delete `faq.md.orig`. The audit puts that in search release 2.
   - Owner: stream E (Data & Content). The audit's housekeeping section also takes the `.orig` deletion.
3. **Per-page holds** (G.2).
   - Then `page-shell.php:51` prints noindex only for held pages and staging.
   - Owner: Frontend & Shop.
   - Week: Dec 8–14. It must come before the lift (0181 §4).
4. **Sitemap and tags** (G.3).
   - Week: Dec 15–21.
   - As a gate 6 criterion it's **not shown**.
5. **Lift the lock** (G.4; 0181 §4).
   - One release, then a live check: a guide is indexable and a trial listing isn't.
   - Owner: Jason decides the date and the Search Console account (Brief D).
6. **Coverage beyond the named leaks** is **not shown**. Whether other pages in `pages.md` are drawn by `tv_page_start` and send noindex is unknown. Examples: `/plans.php`, `/market/*`, `/trip/`, `/guide/personal.php`, `/terms.php`.

## Outside the six gates (no gate link shown)

- Legal texts are drafts, and `/terms.php` is a placeholder (F.7; `pages.md`).
- Sales tax (F.6).
- The capacity test, which has no target (B.6).
- Mail (B.5): "not a December blocker".
- Order #12.
- `GITHUB_SYNC_TOKEN` expires Dec 16 (Brief D.13).

## Summary table

| Gate | Missing item | Evidence | Owner | Order |
|---|---|---|---|---|
| All | Pipeline false blocks; check switched On | plan §4 A.1, §8; Brief A.4 | Piece 2; Jason (switch) | 0 |
| 1 | Path checker repair | plan §2, §4 C.2 | Frontend & Shop | 1 (Nov 27–30) |
| 1 | Device place read as "outside" | plan §4 C.1 | Frontend & Shop | 2 (Nov 27–30) |
| 1 | Search relevance and speed; C7, F10 | 0181 search note; audit C7, F10 | IT lane, Frontend & Shop | 3 (no date given) |
| 1 | Promise check | 0181 promise-check note | CMO 7, Editorial 12, IT 11, A1–A12 | 4 (after "Monday's four", not shown) |
| 1 | Listing-page defects F1–F9 | audit F1–F9 | Frontend & Shop; Jason (0070 note) | 5 |
| 1 | C.4–C.7 front-end fixes | plan §4, §5 | Frontend & Shop | 6 (Dec 8–14) |
| 1 | Open now | 0181 note; plan C.8 | Frontend & Shop | 7 (Dec 15–21) |
| 1 | Search excellence test | 0181 search note | CMO 7, Editorial 12 | 8 (needs gate 3) |
| 1/2 | Theater walk and its fixes | 0181 machine-work note | CMO 7; advocates | 9 (Dec 15–18) |
| 2 | New paths in `paths.mjs` | plan C.3 | Frontend & Shop | 1 (Dec 1–7) |
| 2 | Order tests green (#47) | plan F.1 | Frontend & Shop | 2 (Dec 1–7) |
| 2 | Shop items buyable | plan F.3 | Frontend & Shop; Jason (Brief D) | 3 |
| 2 | Sandbox keys / Shop live | plan F.4; `pages.md` | not shown | not shown |
| 3 | Clear W1 trips | plan D.1 dependency | Piece 2 | 1 |
| 3 | Build 1A towns | plan §2, D.1 | Data & Content | 2 (Dec 1–7) |
| 3 | Backlog and duplicates | plan D.2 | Data & Content | 3 |
| 3 | W15 | 0181 W15 note | Data & Content (Tier 4 review) | 4 |
| 3 | Health data C5 → C4 → C3/C6 → C1/C2 | 0181 search note; audit | Data & Content; IT 11 | 5 (audit ~Dec 2) |
| 3 | Jurisdiction records | 0181 CO/UT note; plan E.2 | Data & Content | 6 |
| 3 | 1A guides at 70 plus a person's read | 0181 guide and ground-notes notes; plan E.1 | Editorial 12/26 | 7 (AI cap) |
| 3 | No false "no listings" | plan C.1, C.9 | Frontend & Shop | 8 |
| 4 | Backups and restore drill | plan B.2 | Piece 2; Jason (Brief C) | 1 |
| 4 | Jobs on time for 7 days | plan B.3, A.2 | Piece 2 (Tier 4 review) | 2 (by ~Dec 24, inferred) |
| 4 | Alarm truth | plan B.7 | Piece 2 | 3 (gate link inferred) |
| 5 | Payments checklist in sandbox | plan §2, F.2 | Frontend & Shop | 1 (Dec 8–14) |
| 5 | Real $4.95 purchase and refund | plan F.5; 0180 §4.1 | Jason | 2 (Dec 15–21) |
| 6 | Close leaks | plan §2, G.1 | Frontend & Shop | 1 (done: not shown) |
| 6 | FAQ truth and `faq.md.orig` | plan E.3; audit housekeeping | Data & Content / Frontend & Shop | 2 |
| 6 | Per-page holds | plan G.2 | Frontend & Shop | 3 (Dec 8–14) |
| 6 | Sitemap and tags | plan G.3 | Frontend & Shop | 4 (gate link not shown) |
| 6 | Lift on Jason's date, live check | 0181 §4; plan G.4 | Jason | 5 (Dec 22–31) |
| 6 | Noindex on other pages | `pages.md` | not shown | not shown |