# Reconciliation of j13-gates pass1 and pass2

**Citation basis.** Neither pass has line numbers. Both say the source documents have none and cite by document and section. So the "file:line" column cites each pass's own location (for example `p1 G1#2` is pass1, gate 1, item 2). Where a pass quotes a code `file:line`, I add it. Those code references are claims taken from the source documents. Neither pass checked them against code.

**Pass1 is truncated.** Its last section ("Superseded or inconsistent items") ends mid-bullet. Any items after that cut are missing.

**Severity is mine.** Neither pass assigns severity. I use:
- **High:** a document states the item blocks a gate.
- **Medium:** the gate link is inferred or "not shown," or it is a scheduling or ownership risk.
- **Low:** housekeeping.

---

## 1. Where they agree

Both passes independently report the following.

**Method**
- Both cite by document and section.
- Both treat `file:line` references as unverified quotes.

**Scope**
- 0181 §1's gate 3 (1A towns only) conflicts with Plan §3 (all towns plus micro-cluster guides).
- Both follow 0181 because it is the accepted decision.

**Cross-gate blockers**
- The release pipeline has false blocks (#58, with duplicates #52–#55). Owner: Piece 2.
- The before-and-after check is Off, and R2 is held on it.
- Brief A was approved, but whether Jason has switched the check On is not shown.
- Code freeze is Dec 22.
- R2–R5 get at most one slot a day.

**Gate 1**
- Path checker repair (`paths.mjs:310` plus the staff two-step), Nov 27–30.
- Device place read as "outside" (`UniversalSearch.php:1036`), Nov 27–30.
- Search relevance and speed (C7, F10: "urgent" read as a time word, ER synonym, urgent care ranking, about 1 second on a phone). Both call it "live harm, first within the gates."
- Promise check. "Monday's four" is undefined in both.
- Listing-page defects F1–F9:
  - F1 needs a note on 0143.
  - F6 needs Jason's note on 0070.
  - F4 goes in search release 2, slot #9.
- Front-end fixes C.4–C.7, Dec 8–14. Whether layout shift and contrast are pass/fail criteria is not shown.
- Open now (C.8), Dec 15–21, tied to F4.
- Search excellence test per 1A town, blocked by gate 3's health data.
- Theater walk: map due Dec 3, walk about Dec 15–16, fixes before Dec 18.

**Gate 2**
- New paths in `paths.mjs`, Dec 1–7.
- Order tests #47, Dec 1–7.
- Plans and the Shop buyable end to end; Jason picks Jan 1 items in Brief D.
- Marketplace sandbox keys: not shown whether set, because `.env` wasn't seen.
- Town paths need gate 3's guides.
- Theater-walk breaks become gate 2 tickets.

**Gate 3**
- Five of seven 1A towns were never built. Holbrook, Winslow and the Petrified Forest gateway have no record.
- Build is blocked by the W1 trips (#10, #11; Piece 2).
- Backlog clearing and identity check.
- W15 runs after the 1A data work and needs a Tier 4 review.
- Health data:
  - C5 before C4.
  - C4 needs parent data that `npi_orgs` lacks.
  - ADHS is refused by network policy.
  - Full audit about Dec 2; the top 50 by taps was skipped.
- Jurisdiction records: AZ and NM by team review, then a `Jurisdictions::regionOpen()` release.
- 1A guides:
  - publish at 70 (C7);
  - ring order puts 1A third;
  - 0131's St. Johns fact-check still applies.
- No false "no listings": C.1 plus the C.9 scorecard check ("show low" returns 0).

**Gate 4**
- #314 is done (12/12).
- Backups and a restore drill (#32). The 0163:460 vs `session-manager.md:651` conflict goes to Jason in Brief C by Dec 7.
- Scheduled-jobs fix:
  - needs a Tier 4 review;
  - is bundled with `releases_tick`;
  - must ship by about Dec 24. Both passes reached this date by their own arithmetic and labelled it as such.

**Gate 5**
- Sandbox payments checklist "not run yet," Dec 8–14.
- One real $4.95 purchase and refund by Jason, Dec 15–21, with holiday risk.

**Gate 6**
- Leaks: home, `login.php`, `register.php`, `forgot-password.php`, `directory/classic.php`, and storefront `X-Robots-Tag: all`.
- FAQ fix (`faq.md:983`, sync, delete `faq.md.orig`). Ownership is split between Data & Content and Frontend & Shop.
- Per-page holds, then `page-shell.php:51`, Dec 8–14.
- Sitemap and tags (G.3); the gate link is not shown.
- Lift on Jason's date with a live check.
- Other public routes may lack noindex; not shown.

**Outside or adjacent to the gates**
- Sales tax (F.6).
- Legal texts (F.7; `/terms.php` is a placeholder).
- Capacity test with no target (B.6).
- Mail (B.5).
- Order #12.

## 2. Where they differ or contradict

| # | Topic | Pass1 | Pass2 | Better supported |
|---|---|---|---|---|
| D1 | Jurisdiction attorney sign-off | 0181's team-reviewed-records note "supersedes the Plan's attorney sign-off" (p1 G3#6; Superseded list) | Whether it replaces Brief C's "Arizona attorney sign-off" is "not shown explicitly" (p2 G3#6) | **Pass2.** Both cite the same note, which is quoted as being about *Colorado and Utah* opening. Pass1 extends it to AZ/NM sign-off without quoting text that does so. Keep this open for the reviewer. |
| D2 | Health-data order | Gives the Audit's *harm* order (C3+C4, then C5, then C1+C2, then C6) and separately the dependency "C4 after C5" (p1 G3#4) | Gives a *fix* order: C5, then C4, then C3/C6, then C1/C2. Only "C5 before C4" is attributed to the audit (p2 G3#5) | **Pass1** separates the harm ranking (sourced) from the one stated dependency. Pass2's grouping of C3 with C6 is unsourced. Merge: harm order from pass1, C5→C4 dependency from both. |
| D3 | Hours metric | "1 of 88 pages shows any" (C2) (p1 G3#4) | "4 of 852 health listings carry hours" (D.4, audit counts) (p2 G3#9) | **Not a contradiction.** These are different denominators (rendered pages vs. health listings) from different sections. Keep both and flag that they need a live read. |
| D4 | Pipeline details | `releases_tick` runs every 24 min instead of 10 (Plan §1.1, A.2) | Releases take 63–80 min each with the check on (plan §4 A.1, §8) | **Compatible.** Different facts; each is single-pass. |
| D5 | Brief A approval source | A.4 approved 2026-11-27 (Plan §7) | Brief A approved (0181 Status) | Same substance, different citations. Either source works. |
| D6 | Load-pacing citation | Plan §8 | Plan §2a | Minor citation difference that can't be resolved without the docs. The substance agrees. |
| D7 | Alarm truth (B.7) | Listed as outside gate 4's wording; asks how "on time" is measured (p1 G4#3–4) | Linked to gate 4 as needed to measure the 7 days, labelled as inference (p2 G4#4) | **Compatible.** Both admit the link is unproven. Pass2 adds the evidence: the health snapshot was missing on 11-26. Rate it Medium. |
| D8 | Gate 6 lift owner | Founder decides; Frontend & Shop builds | Jason | Pass1 is more complete. The release has to be built. |

## 3. Merged findings

| file:line (pass locations; quoted code refs) | Finding | Passes | Severity | Fix |
|---|---|---|---|---|
| p1 X1; p2 Before#1 | Pipeline false blocks (#58; dups #52–55) | both | High | Fix #58, close the duplicates (Piece 2) |
| p1 X1 | `releases_tick` runs every 24 min, not 10 | single-pass (p1) | Medium | Ship with the Scheduler bundle (A.2) |
| p2 Before#1 | Releases take 63–80 min with the check on | single-pass (p2) | Medium | Same pipeline work (A.1) |
| p1 X1; p2 Before#2 | Before-and-after check is Off; R2 held; whether Jason switched it On is not shown | both | High | Confirm Jason pressed On (Brief A.4) |
| p2 Before#4 | Whether the Nov 27–30 items (A.1, A.2, C.1, C.2, G.1) landed is not shown | single-pass (p2) | Medium | Live status read |
| p1 X3 | $0 budget: $40 AI cap; E2 December allowance not approved | single-pass (p1) | Medium | CEO revisits E2 only on week-1 costs |
| p2 G3#7 | AI cap over 80% this month | single-pass (p2) | Medium | Watch guide spend under the cap |
| p1 X2 | Three build sessions at the cap | single-pass (p1) | Medium | Hold the queue rules (0181 §2) |
| p1 G1#1; p2 G1#1 (`paths.mjs:310`) | Path checker sign-in selector and staff two-step broken | both | High | Repair (Frontend & Shop, Nov 27–30) |
| p1 G1#2; p2 G1#2 (`UniversalSearch.php:1036`) | Device place read as "outside our areas" | both | High | Fix (Frontend & Shop, Nov 27–30) |
| p1 G1#3–4; p2 G1#3 (`UniversalSearch.php:355`, quoted by p1 only) | Search harm: "urgent" read as time, no ER synonym, urgent care ranked below clinics; speed about 1 s | both | High | Relevance fixes first within the gates (IT lane + Frontend & Shop); C7 goes to Frontend & Shop |
| p1 G1#4 | Speed measured from `search-probe.yml`; current timing not shown | single-pass (p1) | Medium | Read the probe results |
| p1 G1#9; p2 G1#4 | Promise check: per-message `link` queued after the undefined "Monday's four" | both | High | Define "Monday's four"; build the check (lane 11); advocates run it |
| p1 G1#5; p2 G1#5 | Listing defects F1–F9 | both | High | Gate tickets in harm order (Frontend & Shop; IT 11 for pipeline code) |
| p1 G1#5 | F1's widened scope (government, memorial, religious, cemetery) goes beyond the CMO's recorded approval | single-pass (p1) | Medium | Note on 0143; get approval |
| p1 G1#7–8; p2 G1#6 | C.4 "Set my place" fails 7/7; C.5 layout shift; C.6 contrast/focus; C.7 Get Local times out at about 10k rows | both | High | Frontend & Shop, Dec 8–14; confirm whether C.5/C.6 fail paths |
| p1 G1#6; p2 G1#7 | Open now (C9 ruling), shown only where hours are known; depends on F4 `Hours::now` | both | High | Dec 15–21, after F4 |
| p1 G1#10; p2 G1#8 | Search excellence test per 1A town, blocked by gate 3 health data | both | High | Build the reference lists (CMO 7, Editorial 12) |
| p1 G1#11; p2 G1#9 | Theater walk: map Dec 3, walk Dec 15–16, fixes before Dec 18 | both | High | Run on schedule |
| p1 G1#12 | Five green runs in a row is the final step | single-pass (p1) | High | Schedule last, Dec 15–21 |
| p2 G1 "not confirmable" | "Join a group" stops; no groups exist (D.5); gate link not shown | single-pass (p2) | Medium | Decide whether it is a core path |
| p1 G2#1; p2 G2#1 | New paths in `paths.mjs` per 1A town plus Shop, plan-buy, Marketplace-buy | both | High | Frontend & Shop, Dec 1–7 |
| p1 G2#3; p2 G2#2 | Order tests #47 not green | both | High | Fix before any order work |
| p1 G2#4; p2 G2#3 | QR plan and Maps add-on not buyable | both | High | Frontend & Shop; Jason picks Jan 1 items (Brief D) |
| p1 G2#5; p2 G2#4 | Sandbox keys not shown set; `.env` unseen | both | High | Confirm sandbox keys and accounts |
| p2 G2#5 | `/shop/` live status unknown (`pages.md`, dated 11-13) | single-pass (p2) | Medium | Live check |
| p1 G2#6 | Marketplace's four sections for Jan 1; owner and status not shown | single-pass (p1) | Medium | Assign an owner |
| p1 G2#7 | Minimum events build before Jan 1 is open | single-pass (p1) | Medium | Founder decision |
| p1 G3#1; p2 G3#1–2 | 1A towns unbuilt (5 of 7); blocked by W1 trips #10, #11 | both | High | Clear the trips (Piece 2), then build (Data & Content, Dec 1–7) |
| p1 G3#1 | Springerville and Eagar list 47 of 134 businesses | single-pass (p1) | High | Covered by the 1A build |
| p1 G3#2; p2 G3#3 | Backlog: 700 stuck, 2,107 with no kind; identity check | both | High | Data & Content, Dec 1–7 |
| p2 G3#3 | St. Johns spelled four ways | single-pass (p2) | Medium | Identity check / merge |
| p1 G3#3; p2 G3#4 | W15 needs a Tier 4 review; runs after 1A data starts | both | Medium | Schedule the review |
| p1 G3#4; p2 G3#5 | Health data C1–C6: C5 before C4; `npi_orgs` lacks parent data; ADHS blocked | both | High | Fix in harm order respecting C5→C4 (see D2); request ADHS access |
| p1 G3#4 | C1 wrong-town About (11 of 65); C5 about 240 people as places | single-pass (p1) | High | Data & Content |
| p2 G3#5 | Staff re-file can be overwritten until the category-writer rule ships | single-pass (p2) | High | Ship the category-writer rule |
| p1 Superseded list | C5 count 123 → about 240; IT says neither NPI path can load a person, so the entry path is open | single-pass (p1) | Medium | Trace the entry path |
| p1 G3#6; p2 G3#6 | Jurisdiction records not team-reviewed; `regionOpen()` release pending | both | High | Team review, then one release. Attorney sign-off status is open (D1). |
| p1 G3#5; p2 G3#7 | No live 1A guide; St. Johns draft needs fact-check (0131) | both | High | Editorial (12, 26), Dec 8–14 |
| p1 G3#5 | Why the 116 drafts wait has not been read | single-pass (p1) | Medium | Investigate |
| p1 G3#7; p2 G3#8 | False "no listings": "show low" returns 0; C.9 scorecard check missing | both | High | Fix C.1; add C.9 |
| p1 G3#4 (C2); p2 G3#9 | Hours missing: 1/88 pages vs 4/852 health listings (D3) | both | Medium | Fill task; live read |
| p1 G4#1; p2 G4#1 | #314 access hole done, 12/12 | both | — (closed) | Optional live re-check |
| p1 G4#2; p2 G4#2 (0163:460 vs `session-manager.md:651`) | No backups or restore drill (#32) | both | High | Jason confirms Bluehost coverage (Brief C, Dec 7); Piece 2 builds |
| p1 G4#3; p2 G4#3 | Jobs crowd the hour (#13, #45, #57); must ship by about Dec 24 (inferred) | both | High | Scheduler bundle with Tier 4 review |
| p1 G4#3 | Billing missed 11-26 (#309 live); the +7 offset moves fixed-minute jobs only; "on time" measure not defined | single-pass (p1) | Medium | Define the measure |
| p1 G4#4; p2 G4#4 | Alarm truth (B.7): snapshot missing 11-26; gate link inferred | both | Medium | Fix alarms before the 7-day window |
| p1 G4#4; p2 Outside | Capacity test has no target (B.6) | both | Medium | Set a target |
| p1 G4#4 | Migration runner (B.4); admin pages timing out (B.8) | single-pass (p1) | Medium | Stream B |
| p1 G4#4; p2 Outside | Mail: SES granted; live switch is Jason's; "not a December blocker" | both | Low | Jason's call |
| p1 G5#1; p2 G5#1 | Sandbox payments checklist not run; p1 says who runs it is not shown | both | High | Dec 8–14, after F.1 |
| p1 G5#2; p2 G5#2 | No real $4.95 purchase and refund | both | High | Jason, Dec 15–21, planned around the holiday |
| p1 G5#3; p2 Outside/G5#2 (`QuickBooksPay.php:1218`, p1 only) | Sales tax not set | both | Medium | Founder and accountant (F.6) |
| p1 G5#3; p2 Outside | Legal texts are drafts; `/terms.php` and `/privacy.php` are placeholders | both | Medium | Attorney (F.7) |
| p2 Outside | `GITHUB_SYNC_TOKEN` expires Dec 16 (Brief D.13) | single-pass (p2) | High | Renew before Dec 16, which falls before the freeze |
| p1 G6#1; p2 G6#1 (`storefront-view.php:106`, p1 only) | Noindex leaks on the home page, auth pages, `directory/classic.php` and storefronts | both | High | G.1, Frontend & Shop; confirm shipped |
| p1 G6#1; p2 G6#6 | Other public routes may lack noindex | both | Medium | Audit `tv_page_start` coverage |
| p1 G6#2; p2 G6#2 (`faq.md:983`) | FAQ says "Yes"; repo and live differ; `faq.md.orig` present; ownership split | both | Medium | Correct, sync, delete; assign one owner |
| p1 G6#3; p2 G6#3 (`page-shell.php:51`) | Per-page holds needed before the lift | both | High | Dec 8–14 |
| p1 G6#3 | Multiplier-kit enhanced pages stay noindex until the business says yes | single-pass (p1) | Medium | Include in holds |
| p1 G6#4; p2 G6#4 | Sitemap and tags (G.3); gate link not shown | both | Medium | Dec 15–21 |
| p1 G6#5; p2 G6#5 | Lift needs Jason's date and Search Console account | both | High | Brief D item 11; one release, live check, Dec 22–31 |
| p1 Superseded list | Plan E.1 "person publishes," B.5/C.2 SES request, and §9.1/§9.6 St. Johns pilot are superseded | single-pass (p1) | Low | Update the Plan |