# Sharing, Personal Guides and counts (decisions/0174 release 4)

Built on Sync 14:01 UTC (6915f42), as `trip-share.zip` (17 files). Replayable: `handoffs/trip-share/build-t4.py`. Each
behaviour is listed with its source.

## Share a link
- **On a trip's page, Share makes a view-only link**, `https://traversence.com/trip/?t=<32 hex>` (`Trips::share`). Turn
  off stops it at once. Source: 0174 §1, "anyone with the link can view it, not edit it. The link can be turned off any
  time".
- **Turning it on again makes a new link**, so the old one stays dead. Source: the session manager's recommendation; it's
  in the pop-up for Jason.
- **The view** is `trip.php` in view-only mode, with no controls and nothing about its owner.
  - The owner opening their own link is sent to their page.
  - A dead link says "This trip isn't shared", with the Trip Planner and our email (CLAUDE.md "Never a dead end").
- **Shown publicly:**
  - A place kept private (or no longer listed) never shows, nor its name: "N stops can't be shown". Source: CLAUDE.md
    "Anything about a user or owner is private" and `ListingSafety`.
  - A business marked closed shows greyed. Source: 0058's note of 2026-11-19 (Get Local R2).
  - `resolve()` marks the first case `private`.

## Personal Guides
- **What one is:** a trip published by its owner, public and credited, live with the trip, at `/guide/personal.php?id=`.
  It's the trip page in guide mode. Source: 0174 §1 "Publish as a Personal Guide … public, credited", and 0058 §1.
  "Live, re-checked on change" is in the pop-up for Jason.
- **Checked like a journey** (0174 §1), with the checks `Journeys::save` uses:
  - `Standing::guard` (0079);
  - a contributor only (`Journeys::contributor`);
  - a title and at least 40 characters of words;
  - `ListingSafety::contentProblem`, which holds it, not shown, with why.
  - It's reportable (`Reports` kind `personal_guide`), and staff hide or restore it in Admin → Journeys (0058 §2; no
    page outside the manifests). The Admin → Journeys view is in the pop-up for Jason.
- **A content page** (CLAUDE.md "Content pages", 0058 §20):
  - the kind pill (it searches), the title, the place, the Link button (`Links` `place:pg:N`);
  - the engage bar (`Engage` kind `pg`), with Report;
  - comments at the end;
  - `tv_count_item('personal_guide:N')`.
- **The owner's controls:** Withdraw, and Publish again. A guide our team hid can't be published over; its owner is told
  to ask our team.
- **Account deletion** hides it and empties its words, as journeys are (0174 §6).
- **Data:** `personal_guides` (a migration safe to run twice, named in staging-tables as records) and `trips.guide_id`.

## Counts (0174 §1, 0058 §13)
- **A business:** the `trip` action in `listing_actions`, shown in Business Insights as "Added to trips", following 0094's
  `save`.
- **Any other place:** `PlatformCounts` metric `trip` (key `kind:ref`), listed in Admin → Insights as "Places added to
  trips", and on its own page beside its views ("Added to N trips this month", `Trips::addedThisMonth`). Source: Jason's
  pop-up answer, 2026-11-20, "Also on the place's page".
- **Not counted:** bots, the team, or a business's own people (0087, as `vcard.php` does).
- **Never stored:** who.

## Tests
- `tools/trip-tests/sharing.php`: 26 checks on MariaDB.
- `tools/trip-tests/sharing.cjs` with `sharing-seed.php`: 44 checks at 1280 and 390.
- Releases 2 and 3's tests still pass (28, 67, 21, 56). `trip-page.cjs` now counts only visible cards, because the new
  Share and Publish boxes reuse the day card's style.

## Jason's answers (pop-up, 2026-11-20)
- Approve.
- Q1: a new link.
- Q2: also on the place's page.
- Q3: live with the trip.
