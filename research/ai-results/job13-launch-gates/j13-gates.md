# Draft for human review: what's still missing for each of 0181's six launch gates

**How to read this.** None of the attached files has printed line numbers, so I cite each by document and section. The file:line references inside the documents (for example `UniversalSearch.php:1036`) are the documents' own claims. I haven't checked them against code, because no code is attached.

Abbreviations:
- **0181**: `decisions/0181-the-jan-1-launch-gates-area-and-search-lock.md`
- **Plan**: `research/launch-plan-jan1-2026-11-26.md`
- **Audit**: `research/listing-audit-2026-11-27.md`
- **Pages**: `site-audit/pages.md`

**About "order".** Each number is the sequence the documents give: the Plan's §5 week, an explicit dependency, or the Audit's harm ranking. Where none is given, I say "order not shown".

**About status.** The Plan is a snapshot from 2026-11-26/27, and the Audit runs to 2026-11-28. Only release #314 is recorded as done. Anything else may have moved since; current live state is not shown.

---

## Cross-gate blockers (apply to every gate)

- **X1. The release pipeline.**
  - Releases are false-blocking (#58; #52–#55 are duplicates), and `releases_tick` runs every 24 minutes instead of 10 (Plan §4 A.1–A.2, §1.1).
  - Owner: Piece 2.
  - Plan §8: "the before-and-after check is Off right now, and R2 is held on it." Brief A.4 (Jason turns the check On) was approved on 2026-11-27 (Plan §7). Whether he has actually turned it On is not shown.
- **X2. Queue and freeze rules.**
  - Launch work comes first. R2–R5 get at most one slot a day (0181 §2).
  - The code freeze is Dec 22, with the go/no-go on Dec 31 (0181 §1).
  - Three build sessions, at the cap (0181 Consequences).
- **X3. The $0 budget.**
  - There is a $40 monthly AI cap, and the Google checks stay within the existing 1,000 a month (0181 Notes, "E1").
  - E2, the one-time December allowance, isn't approved. The CEO brings it back only if week 1's measured costs show the gates can't be met at $0.

---

## Gate 1: every core path passes (guest and member, 390px, 5 runs in a row)

1. **Repair the path checker**: the sign-in selector (`paths.mjs:310`) and the staff two-step.
   - Evidence: Plan §2 ("Joining… Not measured"), §4 C.2.
   - Owner: Frontend & Shop.
   - Blocks: without it, joining and claiming "can't be called ready." Scheduled Nov 27–30 (§5).
2. **Search: the device's place is read as "outside our areas"** (`UniversalSearch.php:1036`). The guest "eat near me, open now" path fails.
   - Evidence: Plan §2, §4 C.1.
   - Owner: Frontend & Shop.
   - Order: Nov 27–30.
3. **Search harm from the Audit.**
   - "Urgent" is read as a time word (`UniversalSearch.php:355`).
   - "ER" and "emergency room" have no synonym for hospital.
   - Urgent care ranks below clinics (C7).
   - Evidence: 0181 Notes ("Search must give superior results…", gate 1 relevance), Audit F10 and C7.
   - Owner: the IT lane with Frontend & Shop ("ranking is IT's release 1"). C7 is assigned to "Search's lane (Frontend & Shop)."
   - Order: first within the gates ("treated as live harm, first within the gates").
4. **Search speed**: results in about a second on a phone, measured from `search-probe.yml`.
   - Evidence: 0181 Notes.
   - Owner: the IT lane.
   - Current timing: not shown.
5. **The Audit's listing-page fixes F1–F9**, as gate tickets in order of harm.
   - F1: a stock storefront photo on a hospital. The CMO approved this fix.
   - F2: the kind line hides under the photo credit.
   - F3: Edit, ⋯ and the Link overlap the title.
   - F4: hours. "24/7" isn't read, and times use the server's zone; this goes in search release 2, slot #9.
   - F5: "Suggest an edit" is missing on unclaimed listings.
   - F6: the claim kinds. Hospital roles need a note on 0070 "for Jason."
   - F7: food comment starters show on every listing.
   - F8: `ListingLocation::get` visibility.
   - F9: Directions opens a pin, not a route.
   - Evidence: 0181 Notes ("An end-to-end audit…"), Audit F1–F9.
   - Owners: Frontend & Shop for the page, layout, photo, links and hours; the IT Manager (11) for pipeline code.
   - Blocks: F6 waits on Jason's 0070 note. F1's widened scope (government, memorial, religious and cemetery kinds, in Audit "Rendered") goes beyond the CMO's approval as recorded, and needs a note on 0143. Approval of the widened scope is not shown.
   - Order: full audit "by about Dec 2."
6. **Open now, marked and filtered**: reopened by ruling C9, marked only where hours are known.
   - Evidence: 0181 Notes; Plan §4 C.8; Audit F4.
   - Owner: Frontend & Shop (C.8).
   - Order: Dec 15–21 (Plan §5). This depends on F4's `Hours::now` fix.
7. **Explore**: "Set my place and distance" fails 7 of 7 runs (P9, C.4). Get Local's filter times out at about 10,000 rows (C.7).
   - Evidence: Plan §2, §4 C.4, C.7.
   - Owner: Frontend & Shop.
   - Order: Dec 8–14.
8. **Layout shift (C.5) and contrast and focus (C.6).**
   - Evidence: Plan §2, §4.
   - Owner: Frontend & Shop.
   - Order: Dec 8–14.
   - Whether the path checker fails a path on these: not shown.
9. **The promise check.** Every outbound message kind is tested in the path checker.
   - Evidence: 0181 Notes ("The promise check, part of gate 1").
   - Owners: the CMO (7), Editorial (12), the IT Manager's (11) lane for the build, and the advocates A1–A12 to run it.
   - Blocks: the per-message `link`, which is queued. Order: "after Monday's four." What "Monday's four" are is not shown.
10. **The search excellence test**: for each 1A town, the common needs checked against a reference list built from licensed sources.
    - Evidence: 0181 Notes.
    - Owners: the CMO (7) and Editorial (12).
    - Blocked by: gate 3's health data (item 3.4 below).
11. **The Hometown theater walk.**
    - The touch-point map is due Dec 3. The walk on staging is about Dec 15–16. Breaks become gate 1 or gate 2 tickets, fixed before Dec 18.
    - Evidence: 0181 Notes ("The machine work that counts…").
    - Owners: the CMO (7) for the map; Data & Content and Frontend & Shop draw it; the advocates walk it.
12. **Five green runs in a row.**
    - Evidence: Plan §5, Dec 15–21 ("All six launch gates green for 5 runs").
    - Order: last.

## Gate 2: new paths pass (each 1A town; Shop, plan-buy and Marketplace-buy in the sandbox)

1. **New paths in `paths.mjs`** for each 1A town, plus the Shop, plan-buy and Marketplace-buy.
   - Evidence: Plan §4 C.3.
   - Owner: Frontend & Shop.
   - Order: Dec 1–7, "failing honestly."
2. **The town paths need gate 3's data and guides.** "Read its guide" needs a live 1A guide, and none exists.
   - Evidence: Plan §2 ("1A content: Not built").
   - Blocked by: gate 3, items 1 and 5.
3. **Order tests green** (#47). "Nothing more on orders ships before it."
   - Evidence: Plan §2, §4 F.1.
   - Owner: Frontend & Shop.
   - Order: Dec 1–7. Whether plan-buy depends on #47 is not shown.
4. **Plans and the Shop buyable end to end.** The QR plan and the Maps add-on can't be bought.
   - Evidence: Plan §2, §4 F.3.
   - Owner: Frontend & Shop. Founder call: which items sell on Jan 1 (Brief D, due Dec 14–21).
   - Order: Dec 8–14.
5. **Marketplace payments in the sandbox.**
   - Evidence: Plan §4 F.4. Live keys are "likely not set" (Plan §2, marked **I**).
   - Owner: Frontend & Shop. The keys are the Founder's, entered by him.
   - Whether sandbox keys and accounts exist: not shown. The Plan says the live `.env` wasn't seen.
6. **The Marketplace's four sections for Jan 1.**
   - Shops and the Aftermarket live; Experiences & Regional Goods showing what qualifies; Trade named as "coming."
   - Evidence: 0181 Notes; Plan §10.1.
   - Owner and build status: not shown.
7. **Events build.** Whether a minimum events build ships before Jan 1 is open for the Founder.
   - Evidence: 0181 Notes ("Content to commerce…").
   - Owners named for the build: IT (11) and Frontend & Shop.
8. **Theater-walk breaks** become gate 2 tickets, fixed before Dec 18 (see gate 1, item 11).

## Gate 3: data (1A's towns built, each guide passed 0164's checks and a person's read, no false "no listings")

**Scope conflict to resolve.** 0181 §1 gate 3 covers 1A's towns only. Plan §3 gate 3 covers all 340 towns plus a guide per micro-cluster. 0181 is the accepted decision. Which wording the go/no-go uses is not shown.

1. **Build 1A's towns.**
   - Holbrook, Winslow and the Petrified Forest gateway have no record at all. Five of the seven 1A towns have never been built. In Springerville and Eagar we list 47 of 134 businesses.
   - Evidence: Plan §1.3, §2, §4 D.1.
   - Owner: Data & Content.
   - Blocked by: the W1 trips (#10, #11), which sit in the B stream under Piece 2. Plan §8 flags load pacing.
   - Order: Dec 1–7.
2. **Clear 1A's staged backlog and Review, and run the identity check.**
   - Evidence: Plan §2 (700 finds ready but stuck, 2,107 with no kind), §4 D.2.
   - Owner: Data & Content.
   - Order: Dec 1–7.
3. **W15**: put finds under their real area and merge duplicates, with Undo. Released by ruling C8.
   - Evidence: 0181 Notes; Plan §4 D.3.
   - Owner: Data & Content.
   - Blocks: it needs a Tier 4 review before live. It is built "after the 1A data work starts."
4. **1A health data and the Audit's root causes.** The Audit's harm order:
   - C3 + C4: hospitals filed under the wrong kind; one address with 59 health listings.
   - C5: about 240 people listed as places.
   - C1 + C2: an About naming the wrong town (11 of 65 listings with a description); hours missing (1 of 88 pages shows any).
   - C6: the confidence label vouches for more than it checks.
   - Evidence: 0181 Notes (gate 3, health data); Audit Data & Content's part and the IT Manager's code paths.
   - Owners: Data & Content, with the IT Manager (11) for the code paths.
   - Blocks:
     - C4's fix comes after C5's.
     - C4 needs NPPES parent and authorized-official data that `npi_orgs` lacks.
     - ADHS's hosts are refused by this environment's network policy, and access has been requested.
   - Order: Show Low, Lakeside and Pinetop first. Full audit by about Dec 2. The top 50 by taps is skipped by ruling (b).
5. **A guide for each 1A town, passing 0164's checks and a person's read.**
   - No live guide is in 1A. The St. Johns draft isn't live.
   - The Editorial lane (12, 26) takes the guides to 70 on public sources. Guides self-publish at 70, per ruling C7.
   - Evidence: Plan §2, §4 E.1; 0181 Notes ("Guides publish themselves at 70…", "Ground notes…").
   - Owners: Data & Content and the Editorial lane.
   - Blocks:
     - The rings put 1A's towns third, after the zone guide and the ~13 cluster guides, all inside the $40 cap (X3).
     - 0131 requires "St. Johns fact-checked before live."
     - Why the 116 drafts wait is not yet read.
   - Order: Dec 8–14 (Plan §5).
6. **Jurisdiction records.** AZ's and NM's drafts must be completed by team review, then one release moves `Jurisdictions::regionOpen()` onto the reviewed records. 1A's held and story-only names must be audited.
   - Evidence: 0181 Notes ("Colorado and Utah open on team-reviewed records"); Plan §4 E.2. The 0181 ruling supersedes the Plan's attorney sign-off.
   - Owner: Data & Content.
   - Order: Dec 1–7 in the Plan. The current position is not shown.
7. **No "no listings" note where we have listings.**
   - Fix the device-place bug (gate 1, item 2). Add the scorecard check (C.9). "show low" alone gives 0 listings.
   - Evidence: Plan §2, §4 C.1, C.9.
   - Owner: Frontend & Shop.
   - Order: not shown for C.9.

## Gate 4: safety

1. **The access hole: recorded as done**, live as #314, synced and verified, 12/12.
   - Evidence: 0181 §1; Plan §4 B.1.
   - Nothing missing per these documents. Independent live re-verification is not shown.
2. **Nightly backups and one restore drill.** Not built, and no restore has been tested.
   - Evidence: Plan §2, §4 B.2 (#32).
   - Owner: Piece 2.
   - Blocks: the Founder must confirm what Bluehost's plan already backs up. 0163:460 conflicts with `session-manager.md:651`. This is in Brief C, due by Dec 7.
   - Order: Dec 1–7.
3. **Scheduled jobs on time for 7 days.**
   - Jobs crowd at :17 and :00. The +7 offset moves only fixed-minute jobs. Billing missed 11-26 (fix #309 live).
   - Evidence: Plan §2, §4 B.3, A.2.
   - Owner: Piece 2.
   - Blocks: a Tier 4 review (money territory). `releases_tick` ships in the same Scheduler bundle.
   - Order: Dec 1–7.
   - My arithmetic, not a documented date: 7 on-time days before the Dec 31 go/no-go means the fix must be live and holding by Dec 24 at the latest. How "on time" is measured (`jobs.json`? alarms?) is not shown.
4. **Outside gate 4's wording but in stream B.** Whether any of these counts toward the gate is not shown.
   - Capacity test (B.6): no target exists.
   - Alarm truth (B.7).
   - Migration runner fix (B.4).
   - Mail (B.5): SES production access is already granted, and the live switch is Jason's call (0181 Notes correction).
   - Admin pages timing out (B.8).

## Gate 5: money

1. **The payments checklist passed in the sandbox.** It says "not run yet."
   - Evidence: Plan §2, §4 F.2.
   - Owner: Frontend & Shop (F code). Who runs the checklist is not shown.
   - Order: Dec 8–14, after F.1 (gate 2, item 3).
2. **One real $4.95 purchase and refund by Jason.** Never recorded.
   - Evidence: Plan §2, §4 F.5; 0181 §1.
   - Owner: the Founder's press (0180 §4.1).
   - Order: Dec 15–21. Plan §8 flags the holiday clustering of his presses.
3. **Adjacent items not in gate 5's wording.** Whether they gate go/no-go is not shown.
   - Sales tax: not set (`QuickBooksPay.php:1218`). Owners: the Founder and the accountant (F.6).
   - Legal texts: drafts for the attorney (F.7). Pages §1 marks `/terms.php` and `/privacy.php` "not real legal text, to replace before launch."
   - Order #12: waits for the Founder "after holiday" (0181 Notes).

## Gate 6: search (per-page noindex holds on live, then the lock lifts on Jason's date)

1. **Close today's leaks.**
   - The home page, `login.php`, `register.php`, `forgot-password.php` and `directory/classic.php` aren't noindexed.
   - Storefronts send `X-Robots-Tag: all` (`storefront-view.php:106`).
   - Evidence: Plan §2, §4 G.1. Approved in Brief B2.
   - Owner: Frontend & Shop.
   - Order: Nov 27–30. Whether it shipped is not shown.
   - Coverage gap: Pages §1–§2 list other public routes, such as `/reset-password.php`, `/shop/`, `/plans.php`, `/market/*`, `/pages/view.php`, `/vendor.php` and `/wo.php`. Whether `tv_page_start` draws these is not shown, so whether G.1's list is complete is not shown.
2. **Make robots.txt, the sitemap and the FAQ say the same.**
   - Correct `faq.md:983` ("Yes"). Add a lock entry. Sync the repo's FAQ with the live one. Delete `faq.md.orig`.
   - Evidence: 0181 §4; Plan §4 E.3; Audit, "Housekeeping."
   - Owner: the E stream (Data & Content per Plan §5), but the Audit places the `.orig` removal in search release 2 (Frontend & Shop). Ownership is split; not resolved in these documents.
   - Order: "The FAQ" is scheduled Dec 15–21.
3. **Per-page holds.**
   - Trial and unverified listings, first-look guides, drafts, story-only places, user posts and search results. Then `page-shell.php:51` prints noindex only for those and for staging.
   - The multiplier kit's enhanced pages also stay noindex until the business says yes (0181 Notes).
   - Evidence: Plan §4 G.2; 0181 §4.
   - Owner: Frontend & Shop.
   - Order: Dec 8–14.
4. **Sitemap and tags** (G.3).
   - Evidence: Plan §4 G.3.
   - Owner: Frontend & Shop.
   - Order: Dec 15–21. Whether the lift depends on it is not shown.
5. **Lift the lock.**
   - Jason gives the date and the Search Console account (Brief D, item 11). Then one release, checked on live: a guide is indexable and a trial listing isn't.
   - Evidence: 0181 §4; Plan §4 G.4.
   - Owner: the Founder decides; Frontend & Shop builds.
   - Order: Dec 22–31, last.

---

## Superseded or inconsistent items noticed (for the reviewer)

- **Gate 3's scope**: the Plan's §3 wording differs from 0181 §1 (see gate 3).
- **Plan E.1's suggestion** that "1A's first guides are published by a person" is superseded by ruling C7 (publish at 70).
- **Plan E.2's attorney sign-off** is superseded by 0181's team-reviewed records note.
- **Plan B.5 and Brief C.2** (request SES production access) are superseded: access is already granted.
- **Plan §9.1/§9.6** (St. Johns as the pilot) are superseded by "the pilot is the Ancient Borderlands zone."
- **The Audit's C5 count**: 123 people in the first pass, about 240 later. The IT Manager says neither NPI path can load a person, so the entry path is still open.
- **