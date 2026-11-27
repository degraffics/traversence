# 0181: The Jan 1 launch: its gates, its area and the search lock

Status: **accepted** (Jason, 2026-11-27, to the CEO, in his words: "A and b is good, expand to include surrounding 200
miles from the four corners"). Drafted by the session manager from the CEO's launch plan,
`research/launch-plan-jan1-2026-11-26.md` (Briefs A and B, §2a, §3), which was reviewed by the session manager before
Jason saw it.

Builds on:
- **0145:** the paths and the page audit; "ready" is measured by them.
- **0149:** listings on demand. This decision widens it for the launch area (note added there).
- **0164:** the Knowledge Engine's rules.
- **0066:** tribal nations' land.
- **0165:** staging (note added there).
- **0179** and **0180:** publish authority, the five tiers, and the hard money line (note added to 0180).
- "No income, no expense" (CLAUDE.md).

## Context

Jason, 2026-11-26: "I want the frontend and the shop ready from end to end so we can strategies how to penetrate 1a
properly", and "both the frontend and back end being ready by Jan 1 for market roll out". No decision defined what
"ready" means, the launch area, or the search lock. This one does.

## Decision

### 1. Launch gates (Brief A1)

"Ready" is measured, never judged. All six gates must pass on live:
1. **Every core path** in `site-audit/paths.md` passes as guest and member, at 390px, 5 runs in a row: search,
   explore, read, act, sign up, sign in, claim, edit, post and message (0145).
2. **New paths pass:**
   - one per 1A town: eat, stay, see, read its guide;
   - the Shop, plan-buy and Marketplace-buy, in the sandbox only. No test moves real money.
3. **Data:** 1A's towns built, each with a guide that passed 0164's checks and a person's read, and no "no listings"
   note where we have listings.
4. **Safety:** the access hole closed (live as #314), nightly backups with one restore drill done, and the scheduled jobs
   on time for 7 days.
5. **Money:** the payments checklist passed in the sandbox, and one real $4.95 purchase and refund done by Jason himself
   (0096, 0098; 0180 §4.1).
6. **Search:** the per-page noindex holds on live, then the lock is lifted on Jason's date (§4).

**Code freeze Dec 22.** From Dec 22 to 31, only fixes for failing gates ship. **Go/no-go on Dec 31**, against the
gates.

### 2. Launch work first (Brief A2)

Until the gates pass, launch work comes first in the one queue. 0180's rollout releases R2–R5 get at most one slot a
day. This qualifies 0180's "R1–R5 ship as each passes" (note added there).

### 3. The launch area (Brief B1, widened by Jason)

- **Area:** every town within **200 miles of the Four Corners Monument** (36.999, -109.045). Measured from the site's
  `app/data/US.txt`, that's 419 ZIPs and 340 towns (NM 126, CO 106, AZ 60, UT 48). All of 1A is inside; Eagar sits on
  the line.
- **Towns are built ahead of demand** inside the area, not only when searched (note added to 0149).
- **Costs, under "No income, no expense":** free bulk sources come first (Overture, our OpenStreetMap copy, NPI; the rural
  test §8.9). Google's paid check is used on 1A only, unless Jason funds more.
- **Guides go in rings:** first the Ancient Borderlands zone, then one per micro-cluster inside the area, then 1A's towns,
  then others as the AI cap allows (0131, 0147).
- **Selling stays on 1A first.** The wider area is what a visitor sees.
- **0066 governs every nation's land inside the area.** Nothing it holds is shown.

### 4. The search lock (Brief B2)

- **Until Jason lifts it, the site isn't indexed.** That covers every page `tv_page_start` draws, plus the home page,
  sign-in, sign-up, password reset, the classic directory and storefronts (today `X-Robots-Tag: all` on storefronts).
  robots.txt, the sitemap and the FAQ say the same.
- **Lifting it is Jason's call alone:** the date and the Search Console account. It's one release, checked on live after
  (a guide indexable, a trial listing not).
- **Pages held after the lift:** trial and unverified listings, first-look guides, drafts and story-only places stay
  noindex (0034, 0070, 0071, 0147).

## Consequences

- The plan's streams A–H are the work list. Its §6 moves the rest of the queue after launch: deferred, nothing deleted.
- **Three build sessions run it:** Piece 2 (backend), Frontend & Shop, and Data & Content. That's CLAUDE.md's cap. The
  session manager keeps one queue, and releases go one at a time.
- The CEO reports to Jason against the gates. Briefs C (by Dec 7) and D (by Dec 14–21) come to him as Founder rulings.
