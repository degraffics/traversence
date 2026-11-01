# 0086. The crawler finds the websites listings are missing; Sales shows each prospect's web presence

> **Crawler rules: superseded by `decisions/0133` (2026-10-30), the crawler's one rule book.** This record stays as history; where it and 0133 disagree, 0133 rules.

- **Status:** Accepted
- **Date:** 2026-10-14
- **Builds on:** 0062 (the identity job), 0081 (Sales), 0084 (web search allowance), 0085 (the Google check)

## Context

Jason asked whether the Google check could pull website addresses. Google's terms don't allow keeping Places content,
or using it as the starting point for our own records (passing it through our crawler doesn't change where it came
from). So the websites are found the way the crawler already finds them, from public pages: a web search, then the
business's own site confirming its address or phone. The identity job did this only for listings with neither a phone
nor a website, so a listing with a phone and no website (most Sales prospects) was never looked for.

## Decision

1. **Unclaimed listings with no website of their own are queued to find one**
   (`IdentityJobs::queueWebsites`, topped up whenever the queue runs low).
   - A Facebook, Instagram or other social page as the website counts as none (`WebIdentity::brand`).
   - **Order:** being worked in Sales or viewed in the last 30 days first, then the rest of the unclaimed. Owners add
     their own.
   - A listing looked up before that still has no website comes back after 30 days.
   - **The worker is unchanged.** It runs one web search (paced under the month's allowance, 0084), opens the likely
     result, and keeps it only when the business's own site shows its address or phone.
   - **When the business's own site is found and trusted:** it becomes the website, and a social page that stood in
     for it moves to the listing's social links. Anything a person entered is never replaced.
2. **Sales shows each prospect's web presence:** "Has a website", "Facebook or social only", "No website yet", or
   "No website found" (once the crawler has looked).
   - **Filter:** no website of its own, none at all, social only, or has a website.
   - **"Best to call first"** gives a little more weight to prospects with no website of their own: the businesses a
     listing helps most, and the Facebook-only pitch.

- Checked in the sandbox:
  - 300 unclaimed listings were queued, those in Sales first.
  - A listing whose website was a Facebook page was asked for a website. Its school's own page, confirmed by its
    address, became the website, with the Facebook page kept under its social links.
  - The Sales filter counted 47 with a website, 5 social only and 4,672 with none.
