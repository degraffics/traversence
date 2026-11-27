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

## Notes

- **The launch area runs at $0 extra** (Jason, 2026-11-27, Brief E: "E1"):
  - all 340 towns come from free sources;
  - Google's listing checks stay inside the existing 1,000 a month, 1A first;
  - guides stay inside the $40 monthly AI cap, in rings: first the zone, then the ~13 cluster guides, then 1A's towns.

  A one-time December allowance (E2) isn't approved. The CEO brings it back only if week 1's measured costs show the
  gates can't be met at $0.
- **Colorado and Utah open on team-reviewed records** (Jason, 2026-11-27; 0164's R8/R17 note "advised, not required").
  - CO's and UT's towns in the area open once their jurisdiction records are reviewed by the team, and AZ's and NM's
    drafts are completed the same way.
  - One release moves `Jurisdictions::regionOpen()` onto the reviewed records (the Data & Content session's, after this
    note).
- **The pilot's people and trials** (Jason, 2026-11-27, in his words):
  - "Founder is first point of contact for pilot oversight, foot traffic, and in person meetings." The pilot's funnel,
    visits and in-person meetings are his. There's no rep for now.
  - "Free trials for core subscribers only. That will limit our expenses while we beta test our pilot." During the
    pilot, 0108's trials and 0034's grants are Core only (notes added to both).
  - **Still to confirm with him:** the decided success ladder's step 4a wording, which counts Cornerstone grants
    converting, and the approach he named ("go codes and live enhanced listings, prototype stores, etc."). No decision
    describes "prototype stores" or "live enhanced listings" yet; he's been asked to describe them.
- **The pilot is the Ancient Borderlands zone** (Jason, 2026-11-27: "Ancient borderlands should be pilot").
  - The pilot market is the whole Ancient Borderlands & Painted Desert zone (regions.md 1A, 1B, 1C), not St. Johns
    alone. This amends claude-docs/competitive-positioning.md's "Pilot market: St. Johns (Cluster 1A)" (note added
    there).
  - St. Johns' cornerstones stand within it. Other towns' cornerstones are found by spec §30's method.
  - The success ladder applies to the zone.
  - 0066 governs 1B's Navajo Nation towns and Zuni: public businesses only, and nation-specific content held.
- **The system leads, and in-person time goes to multipliers** (Jason, 2026-11-27, in his words):
  - "Flip your assumption from in-person driving interest and focus on the system pushing content generation and
    linking content to search for relevance and promoting rural businesses getting online and doing business with us
    driving local interest and experience connection to them. That drives claims."
  - "In-person should focus on key multiple contact relations. Chamber of commerce, regional or town guide influences,
    venues...etc."
  - The pilot runs in order: content (0164, 0131, 0146), then search, then counted demand, then demand-carrying
    invites (0172 §3.3, 0108), then claims.
  - The Founder's in-person time goes to multipliers: chambers, town and regional guide influencers, and venues. It
    doesn't go to cold visits to one business at a time.
  - This refines the "Founder is first point of contact" note above.
- **The pilot's success ladder** (Jason, 2026-11-27: "Yes", to the CEO's proposal in research/launch-plan §9). It
  replaces the cold-start note's 4a ("10 Cornerstone grants convert") and 4b ($83,760, 10% of a hub) in
  claude-docs/competitive-positioning.md:
  - **4a:** 10 Core trials convert to paid, by about April 2027.
  - **4b:** the pilot zone's full share, about 73 paying businesses, or about $69,800 a year, by about October 2027.
  - **Stretch:** 100 paying by Dec 31, 2027.
  - **Reported weekly** (counts only): visitors, taps per listing, invites, claims, and multipliers partnered.
- **The multiplier kit, and the co-op for chambers** (Jason, 2026-11-27: "Yes, also look at co-op for chamber
  model"). For a meeting with a chamber, guide influencer or venue, the Founder brings existing parts only:
  1. a live enhanced page, built before the meeting and noindex until they say yes (0034);
  2. a prototype store: a draft storefront with Preview (0139);
  3. GO Codes for the door, handouts and each event, counted back (0094–0096);
  4. a leave-behind with the claim link and QR code (0081);
  5. members' Core trials, and invites that carry the counted demand.
- **For chambers, the model is the co-op, which is already set:** Master AOS §6B's Co-Op Master Hub, 0137's co-op
  group and members-only chapters, 0108's Co-Op/Chamber model chosen at claim, and commercial §1.1 (Cornerstone carries
  the Master Hub).
- **Still open with him:** chambers are nonprofits, so Free under 0108's claim kinds, but the Master Hub is a
  Cornerstone feature and trials are Core only.
- **Chambers get the Co-Op Master Hub by grant** (Jason, 2026-11-27: "Your suggestion", option A).
  - During the pilot, up to **3** partnering chambers in the pilot zone get the Co-Op Master Hub (a Cornerstone
    feature) through a comped 0034 grant.
  - Each grant is recorded, carries a review date, and continues only by the Founder's review.
  - Its AI credits are capped to fit E1.
  - This is a named exception to the Core-only trials rule, for chambers only.
  - Members keep their own plans (Master AOS §6B, dual compliance).
- **The selling order after the pilot zone** (Jason, 2026-11-27: "Yes"). Selling moves on, ring by ring, only after
  the pilot zone clears its ladder:
  1. **The Four Corners core:** 2A Mesa Verde and the San Juan Basin, and 11B Bluff and Blanding.
  2. **The gateways:** 3A Santa Fe and Taos, 4A/4B Albuquerque, 7A/7B Flagstaff, the Grand Canyon and Page, 11A Moab,
     and 12B/12C the High Plateaus.

  The order is for selling only. Visitors see the whole 200-mile area from Jan 1.
- **Businesses on nation land, under 0066** (Jason, 2026-11-27: "Yes"):
  - Public businesses on a nation's land are listed and can claim their listing like any other business.
  - There's no outreach to a nation as a segment.
  - Nothing nation-specific is shown until the nation's own tools exist and it says so.
  - An owner on nation land who joins is welcome on the same terms, with the nation's visitor rules linked on their
    page (`Nations::rules()`).
- **Nations' visitor rules at launch are pointers only** (Jason, 2026-11-27, ruling Q9: "A"). Each nation's page
  links to the nation's own visitor rules. After launch, a person checks the four nearest nations (Navajo, Hopi, Zuni,
  White Mountain Apache), as 0058 §26 asks.
- **SES production access when it's needed** (Jason, 2026-11-27: "Aws can be done when necessary. Check it in the
  registry also."). Jason requests Amazon SES production access when it's needed: before demand-carrying invites run
  at volume, after the search lock lifts. It isn't a December blocker. SES's current state is read from the Service
  register.
  - **Correction (2026-11-27):** the register shows production access is already granted. Only `SES_MODE=mock` stops
    real sending, so "when necessary" now means switching SES_MODE to live. That's Jason's call when invites need it.
    B.5 moves the switch into the register, verifies DKIM, and then moves DMARC to quarantine.
- **Guides publish themselves at 70, and the team gets them there** (Jason, 2026-11-27, C7: "Guides should be able to
  reach 70 or better, especially now that we have a team in place"):
  - Guides keep publishing themselves at a score of 70 (0131's 2026-10-30 addendum; note added to 0131).
  - The Editorial Director's lane (Front-End Content Writers, 26) works the pilot zone's drafts under 70, does 0164
    R10's review and quarterly sample, and meets 0131's "St. Johns fact-checked before live" with its own check.
  - No person publishes a guide that scores 70 or more.
- **W15 is released** (Jason, 2026-11-27, C8: "Yes"; note added to 0170). Data & Content builds it after the 1A data
  work starts:
  - every find and listing goes to its real area;
  - nothing auto-seeds duplicate areas;
  - the existing duplicates (13+ Tucsons, Phoenix ×3, "New York", the ZIP-named ones) are merged into their real areas,
    with Undo, never deleted.

  It changes existing records (0180 territory 2), so its Tier 4 review comes before live.
- **"Open now" reopens** (Jason, 2026-11-27, C9: "Reopen"; notes added to 0060 and to P10 in site-audit/proposals.md).
  Open now is marked and filtered only where hours are known (the owner's or OpenStreetMap's). Everything else says
  "hours not known", never a guess. It's the Frontend & Shop session's C.8.
- **Order #12 waits for the Founder** (Jason, 2026-11-27: "I will address this after holiday"). Nothing on it changes
  until he answers.
- **The Marketplace's four sections and Native made vs regional authenticity** (Jason, 2026-11-27: "Yes."; notes on
  0135 and 0164). For Jan 1: Shops (B2C) and the Aftermarket (C2C) live, Experiences & Regional Goods showing what
  qualifies, Trade (B2B) named as "coming" and built in Q1. The pilot's content plans how to navigate the Indian Arts and
  Crafts Act, from official sources, with relationships with individual Native makers (launch plan §10).
